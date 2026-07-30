<?php
require APPPATH . '/libraries/TokenHandler.php';
require APPPATH . 'libraries/REST_Controller.php';

/**
 * Courses API Controller
 * 
 * Extended RESTful API for course-related operations
 * 
 * @package Academy LMS
 * @version 2.0
 */
class Api_courses extends REST_Controller
{
    protected $tokenHandler;
    
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->load->model('crud_model');
        $this->tokenHandler = new TokenHandler();
        
        // CORS Headers — single source of truth in common_helper.php
        apply_api_cors();
    }

    /**
     * The single account that is allowed to view ALL course content
     * without purchasing, and for whom student commerce UI is hidden.
     */
    const ADMIN_VIEWER_EMAIL = 'admin@my-communication.uk';

    /**
     * Resolve the requesting user id from the JWT (query auth_token / token
     * or the Authorization: Bearer header). Returns null for guests.
     */
    private function _uid_from_request()
    {
        $token = $this->input->get('auth_token')
            ?: $this->input->get('token')
            ?: $this->input->server('HTTP_AUTHORIZATION');
        if (!$token) return null;
        $token = str_replace('Bearer ', '', $token);
        try {
            $decoded = $this->tokenHandler->DecodeToken($token);
            if ($decoded && isset($decoded['user_id'])) return (int)$decoded['user_id'];
        } catch (\Exception $e) {}
        return null;
    }

    /**
     * True when the given user id is the super-viewer admin account.
     */
    private function _is_admin_viewer($uid)
    {
        if (!$uid) return false;
        $u = $this->db->select('email')->get_where('users', ['id' => $uid])->row_array();
        return $u && strtolower(trim($u['email'])) === self::ADMIN_VIEWER_EMAIL;
    }

    /**
     * True when the user has an `enrol` row for this course that hasn't
     * expired yet. Mirrors the expiry check in _can_watch_respond() so
     * detail/curriculum can't report is_enrolled=true (and hand back
     * video_url) for a subscription that _can_watch_respond would reject.
     */
    private function _is_enrolled_active($user_id, $course_id)
    {
        $enrol = $this->db->select('expiry_date')
            ->get_where('enrol', ['user_id' => $user_id, 'course_id' => $course_id])
            ->row_array();
        if (!$enrol) return false;
        $exp = $enrol['expiry_date'];
        return empty($exp) || (int)$exp === 0 || (is_numeric($exp) && (int)$exp > time());
    }

    // ========== COURSE LISTING ==========
    
    /**
     * GET /api_courses/list
     * Get courses with advanced filtering
     */
    public function list_get()
    {
        $page = $this->input->get('page') ?: 1;
        $limit = $this->input->get('limit') ?: 20;
        $offset = ($page - 1) * $limit;
        
        // Filters
        $category_id = $this->input->get('category_id');
        $subcategory_id = $this->input->get('subcategory_id');
        $instructor_id = $this->input->get('instructor_id');
        $level = $this->input->get('level');
        $language = $this->input->get('language');
        $price_type = $this->input->get('price_type'); // free, paid, all
        $min_price = $this->input->get('min_price');
        $max_price = $this->input->get('max_price');
        $min_rating = $this->input->get('min_rating');
        $search = $this->input->get('search');
        $sort = $this->input->get('sort'); // newest, popular, rating, price_low, price_high
        
        $this->db->select('c.*, cat.name as category_name, u.first_name, u.last_name');
        $this->db->from('course c');
        $this->db->join('category cat', 'cat.id = c.category_id', 'left');
        $this->db->join('users u', 'u.id = c.user_id', 'left');
        $this->db->where_in('c.status', ['active', 'upcoming']);
        
        // Apply filters
        // A category filter matches either the parent category OR the sub-category id
        if ($category_id) {
            $this->db->group_start()
                ->where('c.category_id', $category_id)
                ->or_where('c.sub_category_id', $category_id)
                ->group_end();
        }
        if ($subcategory_id) {
            $this->db->where('c.sub_category_id', $subcategory_id);
        }
        if ($instructor_id) {
            $this->db->where('c.user_id', $instructor_id);
        }
        if ($level) {
            $this->db->where('c.level', $level);
        }
        if ($language) {
            $this->db->where('c.language', $language);
        }
        if ($price_type == 'free') {
            $this->db->where('c.is_free_course', 1);
        } elseif ($price_type == 'paid') {
            $this->db->where('c.is_free_course', 0);
        }
        if ($min_price !== null) {
            $this->db->where('c.price >=', $min_price);
        }
        if ($max_price !== null) {
            $this->db->where('c.price <=', $max_price);
        }
        if ($search) {
            $this->db->group_start();
            $this->db->like('c.title', $search);
            $this->db->or_like('c.short_description', $search);
            $this->db->group_end();
        }
        
        // Get total before pagination
        $total = $this->db->count_all_results('', false);
        
        // Always sort active courses first, upcoming last
        $this->db->order_by("FIELD(c.status, 'active', 'upcoming')", '', false);

        // Then apply user sorting
        switch ($sort) {
            case 'popular':
                $this->db->order_by('(SELECT COUNT(*) FROM enrol WHERE course_id = c.id)', 'DESC', false);
                break;
            case 'rating':
                $this->db->order_by('(SELECT AVG(rating) FROM rating WHERE ratable_id = c.id AND ratable_type = "course")', 'DESC', false);
                break;
            case 'price_low':
                $this->db->order_by('CASE WHEN c.discounted_price > 0 THEN c.discounted_price ELSE c.price END', 'ASC', false);
                break;
            case 'price_high':
                $this->db->order_by('CASE WHEN c.discounted_price > 0 THEN c.discounted_price ELSE c.price END', 'DESC', false);
                break;
            default:
                $this->db->order_by('c.id', 'DESC');
        }
        
        $courses = $this->db->limit($limit, $offset)->get()->result_array();
        
        // Enrich course data
        foreach ($courses as &$course) {
            $course = $this->enrich_course($course);
        }
        
        // Filter by rating after enrichment if needed
        if ($min_rating) {
            $courses = array_filter($courses, function($c) use ($min_rating) {
                return ($c['rating'] ?? 0) >= $min_rating;
            });
        }
        
        return $this->response([
            'status' => true,
            'data' => array_values($courses),
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => ceil($total / $limit)
            ],
            'filters_applied' => [
                'category_id' => $category_id,
                'level' => $level,
                'price_type' => $price_type,
                'sort' => $sort
            ]
        ], 200);
    }
    
    /**
     * GET /api_courses/detail/{id}
     * Get full course details
     */
    public function detail_get($id = null)
    {
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        }
        
        $course = $this->db->select('c.*, cat.name as category_name, 
            subcat.name as subcategory_name,
            u.id as instructor_id, u.first_name, u.last_name, u.image as instructor_image, u.biography')
            ->from('course c')
            ->join('category cat', 'cat.id = c.category_id', 'left')
            ->join('category subcat', 'subcat.id = c.sub_category_id', 'left')
            ->join('users u', 'u.id = c.user_id', 'left')
            ->where('c.id', $id)
            ->get()
            ->row_array();
        
        if (!$course) {
            return $this->response(['status' => false, 'message' => 'Course not found'], 404);
        }
        
        // Enrich with additional data
        $course = $this->enrich_course($course, true);

        // Detect the requester early. The admin super-viewer (and the course
        // owner / enrolled students) get the full curriculum with video URLs.
        $uid = $this->_uid_from_request();
        $is_admin_viewer = $this->_is_admin_viewer($uid);
        $is_enrolled = false;
        if ($uid) {
            $is_enrolled = $this->_is_enrolled_active($uid, $id);
            if (!$is_enrolled && ((int)$course['user_id'] === $uid || (int)$course['creator'] === $uid)) {
                $is_enrolled = true;
            }
        }
        $full_access = $is_admin_viewer || $is_enrolled;

        // Get sections and lessons (video URLs unlocked for full-access users)
        $course['curriculum'] = $this->get_course_curriculum($id, $full_access, $uid);
        
        // Get requirements and outcomes
        $course['requirements'] = $this->get_course_meta($id, 'requirements');
        $course['outcomes'] = $this->get_course_meta($id, 'outcomes');
        
        // Get recent reviews
        $course['recent_reviews'] = $this->get_course_reviews($id, 5);
        
        // Get instructor info
        $course['instructor'] = [
            'id' => $course['instructor_id'],
            'name' => trim($course['first_name'] . ' ' . $course['last_name']),
            'first_name' => $course['first_name'],
            'last_name' => $course['last_name'],
            'image' => $this->get_user_image($course['instructor_id']),
            'title' => $course['biography'] ? mb_substr(strip_tags($course['biography']), 0, 80) : 'Instructor',
            'biography' => $course['biography'] ? strip_tags($course['biography']) : '',
            'total_students' => $this->get_instructor_students($course['instructor_id']),
            'total_courses' => $this->get_instructor_courses($course['instructor_id']),
            'rating' => $this->get_instructor_rating($course['instructor_id'])
        ];
        
        // Sections alias for React — curriculum already has parsed durations
        $course['sections'] = $course['curriculum'];
        $course['total_sections'] = count($course['sections']);
        $total_lessons = 0;
        $total_secs = 0;
        foreach ($course['sections'] as &$sec) {
            $dur = 0;
            if (!empty($sec['lessons'])) {
                $total_lessons += count($sec['lessons']);
                foreach ($sec['lessons'] as &$l) {
                    $l['type'] = $l['lesson_type'] ?? 'video';
                    $dur += (int)($l['duration_seconds'] ?? 0);
                }
                unset($l);
            }
            $sec['total_duration'] = $this->format_duration($dur);
            $total_secs += $dur;
        }
        $course['total_lessons'] = $total_lessons;
        $course['duration'] = $this->format_duration($total_secs);
        $course['total_duration'] = $course['duration'];

        // FAQs
        $course['faqs'] = json_decode($course['faqs'] ?? '[]', true) ?: [];

        // Instructor social links
        $inst_data = $this->db->select('social_links')->get_where('users', ['id' => $course['instructor_id']])->row_array();
        $course['instructor']['social_links'] = json_decode($inst_data['social_links'] ?? '{}', true) ?: [];

        // Enrollment / admin-viewer flags (computed above from the token).
        // The admin super-viewer is treated as enrolled so the frontend swaps
        // the purchase buttons for "Continue Learning"; is_admin_viewer also
        // tells the UI to hide the price / money-back / student markers.
        $course['is_enrolled']     = $is_enrolled || $is_admin_viewer;
        $course['is_admin_viewer'] = $is_admin_viewer;
        $course['is_wishlisted']   = false;
        if ($uid && !$is_admin_viewer) {
            $user_wish = $this->db->select('wishlist')->get_where('users', ['id' => $uid])->row_array();
            $wish_arr = json_decode($user_wish['wishlist'] ?? '[]', true) ?: [];
            $course['is_wishlisted'] = in_array((int)$id, $wish_arr);
        }

        // Related courses
        $course['related_courses'] = $this->get_related_courses($id, $course['category_id'], 4);

        return $this->response(['status' => true, 'data' => $course], 200);
    }
    
    /**
     * GET /api_courses/curriculum/{id}
     * Get course curriculum (sections and lessons).
     * Lesson video_url + video_type are included only for enrolled students,
     * for free lessons (preview), or for the course owner. Otherwise null.
     */
    public function curriculum_get($id = null)
    {
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        }

        // Detect requester (optional auth) and enrollment in this course.
        $is_enrolled = false;
        $uid = null;
        $auth_token = $this->input->get('auth_token') ?: $this->input->server('HTTP_AUTHORIZATION');
        if ($auth_token) {
            $auth_token = str_replace('Bearer ', '', $auth_token);
            try {
                $decoded = $this->tokenHandler->DecodeToken($auth_token);
                if ($decoded && isset($decoded['user_id'])) {
                    $uid = (int)$decoded['user_id'];
                    // Admin super-viewer sees every course's full curriculum.
                    if ($this->_is_admin_viewer($uid)) {
                        $is_enrolled = true;
                    }
                    $is_enrolled = $is_enrolled || $this->_is_enrolled_active($uid, $id);
                    // Course owner / admin sees full curriculum too.
                    if (!$is_enrolled) {
                        $course_row = $this->db->select('user_id, creator')->get_where('course', ['id' => $id])->row_array();
                        if ($course_row && ((int)$course_row['user_id'] === $uid || (int)$course_row['creator'] === $uid)) {
                            $is_enrolled = true;
                        }
                    }
                }
            } catch (\Exception $e) {}
        }

        $sections = $this->get_course_curriculum($id, $is_enrolled, $uid);

        // Add total_duration per section and map lesson_type to type
        foreach ($sections as &$sec) {
            $dur = 0;
            if (!empty($sec['lessons'])) {
                foreach ($sec['lessons'] as &$lesson) {
                    $lesson['type'] = $lesson['lesson_type'] ?? 'video';
                    $dur += (int)($lesson['duration_seconds'] ?? 0);
                }
            }
            $sec['total_duration'] = $this->format_duration($dur);
        }

        return $this->response(['status' => true, 'data' => ['sections' => $sections]], 200);
    }

    /**
     * GET /api_courses/can_watch/{course_id}/{video_id}
     *
     * Access check for a single lesson/video. Returns whether the
     * requesting user is allowed to watch this video.
     *
     * Token (JWT) is optional and read from ?auth_token= , ?token= ,
     * or the "Authorization: Bearer <token>" header.
     *
     * can_watch = true when ANY of:
     *   - the course is free (is_free_course = 1)
     *   - the lesson is a free preview (lesson.is_free = 1)
     *   - the user purchased / is enrolled in the course (not expired)
     *   - the user owns the course (instructor / creator)
     * Otherwise it is a paid lesson and can_watch = false.
     *
     * The real video_url is returned ONLY when can_watch = true, so
     * paid video URLs can't be harvested by non-buyers.
     */
    public function can_watch_get($course_id = null, $video_id = null)
    {
        $token = $this->input->get('auth_token')
            ?: $this->input->get('token')
            ?: $this->input->server('HTTP_AUTHORIZATION');
        return $this->_can_watch_respond($course_id, $video_id, $token);
    }

    /**
     * POST /api_courses/can_watch
     * Same as the GET version but everything comes from the request body
     * (JSON or x-www-form-urlencoded): course_id, video_id, token.
     */
    public function can_watch_post()
    {
        $body      = $this->_body_params();
        $course_id = $body['course_id'] ?? null;
        $video_id  = $body['video_id']  ?? ($body['lesson_id'] ?? null);
        $token     = $body['token']     ?? ($body['auth_token'] ?? $this->input->server('HTTP_AUTHORIZATION'));
        return $this->_can_watch_respond($course_id, $video_id, $token);
    }

    /**
     * Read params from the request body, supporting both raw JSON and
     * x-www-form-urlencoded / form-data.
     */
    private function _body_params()
    {
        $params = $_POST ?: [];
        $raw = file_get_contents('php://input');
        if ($raw && (empty($params) || $this->input->server('CONTENT_TYPE') && stripos($this->input->server('CONTENT_TYPE'), 'json') !== false)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $params = array_merge($params, $json);
            }
        }
        return $params;
    }

    /**
     * Shared access-decision used by both the GET and POST variants.
     */
    private function _can_watch_respond($course_id, $video_id, $token)
    {
        if (!$course_id || !$video_id) {
            return $this->response([
                'status'  => false,
                'message' => 'course_id and video_id are required'
            ], 400);
        }

        // The lesson must exist AND belong to the given course.
        $lesson = $this->db->select('id, course_id, title, is_free, video_url, video_type')
            ->get_where('lesson', ['id' => $video_id, 'course_id' => $course_id])
            ->row_array();
        if (!$lesson) {
            return $this->response([
                'status'    => false,
                'can_watch' => false,
                'message'   => 'Video not found in this course'
            ], 404);
        }

        $course = $this->db->select('id, user_id, creator, is_free_course')
            ->get_where('course', ['id' => $course_id])
            ->row_array();
        if (!$course) {
            return $this->response([
                'status'    => false,
                'can_watch' => false,
                'message'   => 'Course not found'
            ], 404);
        }

        // Identify the user from the token (optional).
        $uid = null;
        if ($token) {
            $token = str_replace('Bearer ', '', $token);
            try {
                $decoded = $this->tokenHandler->DecodeToken($token);
                if ($decoded && isset($decoded['user_id'])) {
                    $uid = (int)$decoded['user_id'];
                }
            } catch (\Exception $e) {}
        }

        $can    = false;
        $reason = 'locked';

        if ($this->_is_admin_viewer($uid)) {
            // Admin super-viewer can watch every video.
            $can = true; $reason = 'admin_viewer';
        } elseif ((int)$course['is_free_course'] === 1) {
            // Whole course is free.
            $can = true; $reason = 'free_course';
        } elseif ((int)($lesson['is_free'] ?? 0) === 1) {
            // Free preview lesson.
            $can = true; $reason = 'free_preview';
        } elseif ($uid && ((int)$course['user_id'] === $uid || (int)$course['creator'] === $uid)) {
            // Course owner / instructor.
            $can = true; $reason = 'owner';
        } elseif ($uid) {
            // Paid course: must be enrolled (and not expired).
            $enrol = $this->db->select('expiry_date')
                ->get_where('enrol', ['user_id' => $uid, 'course_id' => $course_id])
                ->row_array();
            if ($enrol) {
                $exp = $enrol['expiry_date'];
                if (empty($exp) || (int)$exp === 0 || (is_numeric($exp) && (int)$exp > time())) {
                    $can = true; $reason = 'enrolled';
                } else {
                    $reason = 'enrollment_expired';
                }
            } else {
                $reason = 'not_enrolled';
            }
        } else {
            // Paid lesson and no valid token.
            $reason = 'login_required';
        }

        $data = [
            'status'    => true,
            'can_watch' => $can,
            'reason'    => $reason,
            'course_id' => (int)$course_id,
            'video_id'  => (int)$video_id,
        ];

        if ($can) {
            $data['video_url']  = $lesson['video_url'];
            $data['video_type'] = $lesson['video_type'];
        } else {
            $data['video_url'] = null;
            $data['message']   = 'This is a paid lesson. Please purchase the course to watch it.';
        }

        return $this->response($data, 200);
    }

    /**
     * GET /api_courses/reviews/{id}
     * Get course reviews
     */
    public function reviews_get($id = null)
    {
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        }
        
        $page = $this->input->get('page') ?: 1;
        $limit = $this->input->get('limit') ?: 10;
        $offset = ($page - 1) * $limit;
        
        $this->db->select('r.*, u.first_name, u.last_name, u.image');
        $this->db->from('rating r');
        $this->db->join('users u', 'u.id = r.user_id', 'left');
        $this->db->where('r.ratable_id', $id);
        $this->db->where('r.ratable_type', 'course');
        
        $total = $this->db->count_all_results('', false);
        
        $reviews = $this->db->order_by('r.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        
        foreach ($reviews as &$review) {
            $review['user'] = [
                'name' => trim($review['first_name'] . ' ' . $review['last_name']),
                'image' => $this->get_user_image($review['user_id'])
            ];
            $review['user_name'] = $review['user']['name'];
            $review['user_image'] = $review['user']['image'];
            $review['created_at'] = date('Y-m-d H:i:s', $review['date_added']);
            $review['time_ago'] = $this->time_ago($review['date_added']);
        }
        
        // Get rating breakdown
        $breakdown = $this->get_rating_breakdown($id);
        
        // Convert breakdown to percentages
        $breakdown_pct = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        if ($total > 0) {
            foreach ($breakdown['breakdown'] as $star => $count) {
                $breakdown_pct[$star] = round(($count / $total) * 100);
            }
        }
        
        return $this->response([
            'status' => true,
            'data' => [
                'reviews' => $reviews,
                'stats' => [
                    'average_rating' => $breakdown['average'],
                    'total' => $total,
                    'rating_breakdown' => $breakdown_pct
                ],
                'summary' => [
                    'average' => $breakdown['average'],
                    'total' => $total,
                    'breakdown' => $breakdown['breakdown']
                ]
            ],
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => ceil($total / $limit)
            ]
        ], 200);
    }
    
    /**
     * POST /api_courses/review/{id}
     * Add course review
     */
    public function review_post($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        }
        
        $rating = $this->input->post('rating');
        $review_text = $this->input->post('review');
        
        if (!$rating || $rating < 1 || $rating > 5) {
            return $this->response(['status' => false, 'message' => 'Rating (1-5) is required'], 400);
        }
        
        // Check if enrolled
        $enrolled = $this->db->get_where('enrol', [
            'user_id' => $user['user_id'],
            'course_id' => $id
        ])->row_array();
        
        if (!$enrolled) {
            return $this->response(['status' => false, 'message' => 'You must be enrolled to review'], 403);
        }
        
        // Check existing review
        $existing = $this->db->get_where('rating', [
            'user_id' => $user['user_id'],
            'ratable_id' => $id,
            'ratable_type' => 'course'
        ])->row_array();
        
        if ($existing) {
            // Update existing
            $this->db->where('id', $existing['id'])->update('rating', [
                'rating' => $rating,
                'review' => $review_text,
                'date_added' => time()
            ]);
            $message = 'Review updated';
        } else {
            // Create new
            $this->db->insert('rating', [
                'user_id' => $user['user_id'],
                'ratable_id' => $id,
                'ratable_type' => 'course',
                'rating' => $rating,
                'review' => $review_text,
                'date_added' => time()
            ]);
            $message = 'Review added';
        }
        
        return $this->response(['status' => true, 'message' => $message], 200);
    }
    
    /**
     * GET /api_courses/progress/{id}
     * Get course progress
     */
    public function progress_get($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        }
        
        // Check enrollment
        $enrolled = $this->db->get_where('enrol', [
            'user_id' => $user['user_id'],
            'course_id' => $id
        ])->row_array();
        
        if (!$enrolled) {
            return $this->response(['status' => false, 'message' => 'Not enrolled in this course'], 403);
        }
        
        // Get total lessons
        $total_lessons = $this->db->where('course_id', $id)->count_all_results('lesson');
        
        // Completed lessons — union of both stores so the count matches every screen.
        $completed_ids = mycom_completed_lesson_ids($user['user_id'], $id);
        $completed_count = count($completed_ids);
        
        $progress_percentage = $total_lessons > 0 ? round(($completed_count / $total_lessons) * 100) : 0;
        
        // Get current lesson (first incomplete)
        $current_lesson = null;
        $lessons = $this->db->select('l.id, l.title, l.lesson_type')
            ->from('lesson l')
            ->join('section s', 's.id = l.section_id')
            ->where('l.course_id', $id)
            ->order_by('s.order', 'ASC')
            ->order_by('l.order', 'ASC')
            ->get()
            ->result_array();
        
        foreach ($lessons as $lesson) {
            if (!in_array($lesson['id'], $completed_ids)) {
                $current_lesson = $lesson;
                break;
            }
        }
        
        return $this->response([
            'status' => true,
            'data' => [
                'total_lessons' => $total_lessons,
                'completed_lessons' => $completed_count,
                'progress_percentage' => $progress_percentage,
                'completed_lesson_ids' => $completed_ids,
                'current_lesson' => $current_lesson,
                'is_completed' => $progress_percentage == 100
            ]
        ], 200);
    }
    
    /**
     * POST /api_courses/complete_lesson
     * Mark lesson as complete
     */
    public function complete_lesson_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $lesson_id = $this->input->post('lesson_id');
        $course_id = $this->input->post('course_id');
        
        if (!$lesson_id || !$course_id) {
            return $this->response(['status' => false, 'message' => 'Lesson ID and Course ID required'], 400);
        }
        
        // Check enrollment
        $enrolled = $this->db->get_where('enrol', [
            'user_id' => $user['user_id'],
            'course_id' => $course_id
        ])->row_array();

        if (!$enrolled) {
            // Free courses / free lessons are watchable without enrolling, so completion
            // must work for them too. Auto-enrol into a free course (also makes it appear
            // under "My Courses" and lets progress/certificates accrue), otherwise keep the gate.
            $course = $this->db->select('is_free_course, expiry_period')->get_where('course', ['id' => $course_id])->row_array();
            $lesson = $this->db->select('is_free')->get_where('lesson', ['id' => $lesson_id])->row_array();
            $is_free = ($course && (int)$course['is_free_course'] === 1) || ($lesson && (int)$lesson['is_free'] === 1);
            if (!$is_free) {
                return $this->response(['status' => false, 'message' => 'Not enrolled'], 403);
            }
            $enrol_data = ['user_id' => $user['user_id'], 'course_id' => $course_id, 'date_added' => time()];
            if (!empty($course['expiry_period']) && $course['expiry_period'] > 0) {
                $enrol_data['expiry_date'] = strtotime("+{$course['expiry_period']} months");
            }
            $this->db->insert('enrol', $enrol_data);
        }
        
        // Check if already completed
        $existing = $this->db->get_where('lesson_progress', [
            'user_id' => $user['user_id'],
            'lesson_id' => $lesson_id
        ])->row_array();
        
        if (!$existing) {
            $this->db->insert('lesson_progress', [
                'user_id' => $user['user_id'],
                'course_id' => $course_id,
                'lesson_id' => $lesson_id,
                'completed_at' => time()
            ]);
        }

        // Mirror into the canonical watch_histories store (used by My Courses, the
        // mobile app, progress % and certificates). mark_lesson_complete creates the
        // row with the correct columns and adds this lesson; we then reconcile its
        // completed_lesson to the FULL union so the web and mobile never drift apart.
        $this->load->model('api_frontend_model');
        $this->api_frontend_model->mark_lesson_complete($user['user_id'], $lesson_id);

        $total = $this->db->where('course_id', $course_id)->count_all_results('lesson');
        $completed_ids = mycom_completed_lesson_ids($user['user_id'], $course_id);
        $completed = count($completed_ids);
        $progress = $total > 0 ? min(100, (int) round(($completed / $total) * 100)) : 0;

        $this->db->where(['student_id' => $user['user_id'], 'course_id' => $course_id])
            ->update('watch_histories', [
                'completed_lesson' => json_encode(array_values($completed_ids)),
                'course_progress'  => $progress,
                'date_updated'     => time(),
            ]);

        $course_completed = ($total > 0 && $completed >= $total);

        return $this->response([
            'status' => true,
            'message' => 'Lesson completed',
            'data' => [
                'progress' => $progress,
                'course_completed' => $course_completed
            ]
        ], 200);
    }
    
    /**
     * GET /api_courses/certificate/{id}
     * Get course certificate
     */
    public function certificate_get($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        }
        
        // Check completion
        $total = $this->db->where('course_id', $id)->count_all_results('lesson');
        $completed = $this->db->where(['user_id' => $user['user_id'], 'course_id' => $id])
            ->count_all_results('lesson_progress');
        
        if ($completed < $total) {
            return $this->response([
                'status' => false,
                'message' => 'Complete all lessons to get certificate',
                'data' => ['progress' => round(($completed / $total) * 100)]
            ], 400);
        }
        
        // Get or create certificate
        $certificate = $this->db->get_where('certificates', [
            'user_id' => $user['user_id'],
            'course_id' => $id
        ])->row_array();
        
        if (!$certificate) {
            // Create certificate
            $code = strtoupper(substr(md5(uniqid()), 0, 12));
            $this->db->insert('certificates', [
                'user_id' => $user['user_id'],
                'course_id' => $id,
                'shareable_code' => $code,
                'issued_at' => time(),
                'created_at' => time()
            ]);
            $certificate_id = $this->db->insert_id();
            $certificate = $this->db->get_where('certificates', ['id' => $certificate_id])->row_array();
        }
        
        // Get course and user info
        $course = $this->db->select('title')->get_where('course', ['id' => $id])->row_array();
        $user_info = $this->db->select('first_name, last_name')
            ->get_where('users', ['id' => $user['user_id']])->row_array();
        
        return $this->response([
            'status' => true,
            'data' => [
                'certificate_id' => $certificate['id'],
                'shareable_code' => $certificate['shareable_code'],
                'verify_url' => base_url('certificate/' . $certificate['shareable_code']),
                'issued_at' => date('Y-m-d', $certificate['issued_at']),
                'student_name' => $user_info['first_name'] . ' ' . $user_info['last_name'],
                'course_title' => $course['title'],
                'download_url' => base_url('certificate/download/' . $certificate['shareable_code'])
            ]
        ], 200);
    }
    
    /**
     * GET /api_courses/featured
     * Get featured courses
     */
    public function featured_get()
    {
        $limit = $this->input->get('limit') ?: 8;
        
        $courses = $this->db->select('c.*, u.first_name, u.last_name')
            ->from('course c')
            ->join('users u', 'u.id = c.user_id', 'left')
            ->where_in('c.status', ['active', 'upcoming'])
            ->where('c.is_top_course', 1)
            ->order_by('c.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
        
        foreach ($courses as &$course) {
            $course = $this->enrich_course($course);
        }
        
        return $this->response(['status' => true, 'data' => $courses], 200);
    }
    
    /**
     * GET /api_courses/popular
     * Get popular courses
     */
    public function popular_get()
    {
        $limit = $this->input->get('limit') ?: 8;
        
        $courses = $this->db->select('c.*, u.first_name, u.last_name, COUNT(e.id) as enrollment_count')
            ->from('course c')
            ->join('users u', 'u.id = c.user_id', 'left')
            ->join('enrol e', 'e.course_id = c.id', 'left')
            ->where_in('c.status', ['active', 'upcoming'])
            ->group_by('c.id')
            ->order_by('enrollment_count', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
        
        foreach ($courses as &$course) {
            $course = $this->enrich_course($course);
        }
        
        return $this->response(['status' => true, 'data' => $courses], 200);
    }
    
    /**
     * GET /api_courses/levels
     * Get available course levels
     */
    public function levels_get()
    {
        $levels = [
            ['id' => 'beginner', 'name' => 'Beginner'],
            ['id' => 'intermediate', 'name' => 'Intermediate'],
            ['id' => 'advanced', 'name' => 'Advanced'],
            ['id' => 'all_levels', 'name' => 'All Levels']
        ];
        
        return $this->response(['status' => true, 'data' => $levels], 200);
    }
    
    /**
     * GET /api_courses/languages
     * Get available course languages
     */
    public function languages_get()
    {
        $languages = $this->db->select('DISTINCT language')
            ->from('course')
            ->where('status', 'active')
            ->where('language !=', '')
            ->get()
            ->result_array();
        
        return $this->response(['status' => true, 'data' => array_column($languages, 'language')], 200);
    }

    // ========== HELPER METHODS ==========
    
    private function enrich_course($course, $full = false)
    {
        $course['thumbnail_url'] = $this->get_course_thumbnail($course['id']);
        $course['thumbnail'] = $course['thumbnail_url'];
        $course['instructor_name'] = trim(($course['first_name'] ?? '') . ' ' . ($course['last_name'] ?? ''));
        $course['instructor_image'] = isset($course['user_id']) ? $this->get_user_image($course['user_id']) : null;
        $course['instructor'] = [
            'name' => $course['instructor_name'],
            'first_name' => $course['first_name'] ?? '',
            'last_name' => $course['last_name'] ?? '',
            'image' => $course['instructor_image']
        ];
        
        // Get category name — prefer the specific sub-category, fall back to parent
        $cat_lookup_id = !empty($course['sub_category_id']) ? $course['sub_category_id'] : ($course['category_id'] ?? null);
        if (!empty($cat_lookup_id)) {
            $cat = $this->db->select('name')->get_where('category', ['id' => $cat_lookup_id])->row_array();
            $course['category'] = $cat ? ['name' => $cat['name']] : null;
        }

        // Total duration from lessons so catalog cards can show it (detail overrides with curriculum sum)
        $dur_row = $this->db->query("SELECT COALESCE(SUM(TIME_TO_SEC(duration)),0) AS secs, COUNT(*) AS cnt FROM lesson WHERE course_id = " . (int)$course['id'])->row();
        $course['duration'] = ((int)($dur_row->secs ?? 0)) > 0 ? $this->format_duration((int)$dur_row->secs) : null;
        $course['total_lessons'] = (int)($dur_row->cnt ?? 0);

        // Get ratings
        $ratings = $this->db->select('AVG(rating) as avg, COUNT(*) as count')
            ->from('rating')
            ->where('ratable_id', $course['id'])
            ->where('ratable_type', 'course')
            ->get()
            ->row_array();
        
        $course['rating'] = round($ratings['avg'] ?? 0, 1);
        $course['avg_rating'] = $course['rating'];
        $course['rating_count'] = (int)($ratings['count'] ?? 0);
        $course['total_ratings'] = $course['rating_count'];

        // --- Synthetic display ratings for ACTIVE courses with no real reviews yet ---
        // Upcoming courses are intentionally left as N/A (no rating).
        if (($course['status'] ?? '') === 'active' && (int)$course['rating_count'] === 0) {
            $rating_map = [
                'Integrated Telecommunications Engineering Course' => 5.0,
                'Optical Fiber Transmission Course (END to END)'   => 5.0,
                'Telecommunications for Non-Engineers'             => 4.0,
                'Microwave Transmission Course (END to END)'       => 4.0,
                '(Wireless Course) END to END'                     => 3.0,
            ];
            $count_map = [
                'Integrated Telecommunications Engineering Course' => 23,
                'Optical Fiber Transmission Course (END to END)'   => 17,
                'Telecommunications for Non-Engineers'             => 12,
                'Microwave Transmission Course (END to END)'       => 9,
                '(Wireless Course) END to END'                     => 8,
            ];
            $title = $course['title'] ?? '';
            $course['rating']        = $rating_map[$title] ?? 4.0; // default for any other active course
            $course['avg_rating']    = $course['rating'];
            $course['rating_count']  = $count_map[$title] ?? 11;
            $course['total_ratings'] = $course['rating_count'];
        }

        // Get enrollment count
        $course['enrolled_count'] = $this->db->where('course_id', $course['id'])->count_all_results('enrol');
        $course['total_students'] = $course['enrolled_count'];
        
        // Field aliases for React compatibility
        $course['is_free'] = $course['is_free_course'] == 1;

        // Ensure video_url is available for preview
        if (!empty($course['video_url'])) {
            $course['video_url'] = $course['video_url'];
        } elseif (!empty($course['course_overview_url'])) {
            $course['video_url'] = $course['course_overview_url'];
        }
        
        // Cast prices to proper numeric types
        $course['price'] = (float)($course['price'] ?? 0);
        $course['discounted_price'] = (float)($course['discounted_price'] ?? 0);
        // Set discounted_price to null when 0 so React || operator works correctly
        if ($course['discounted_price'] == 0) {
            $course['discounted_price'] = null;
        }
        
        // Calculate effective price
        $course['effective_price'] = $course['is_free_course'] == 1 ? 0 : 
            ($course['discounted_price'] > 0 ? $course['discounted_price'] : $course['price']);
        
        if ($full) {
            // Get lessons count
            $course['lessons_count'] = $this->db->where('course_id', $course['id'])->count_all_results('lesson');
            
            // Get sections count
            $course['sections_count'] = $this->db->where('course_id', $course['id'])->count_all_results('section');
            
            // Get total duration
            $duration = $this->db->select('SUM(duration) as total')
                ->from('lesson')
                ->where('course_id', $course['id'])
                ->get()
                ->row_array();
            $course['total_duration'] = $this->format_duration($duration['total'] ?? 0);
        }
        
        return $course;
    }
    
    /**
     * Build curriculum tree for a course.
     * When $include_video_urls is true, every lesson exposes video_url + video_type.
     * When false, only is_free lessons expose them (paid lessons get null).
     * This is the gate that prevents non-enrolled users from harvesting paid video URLs.
     */
    private function get_course_curriculum($course_id, $include_video_urls = false, $uid = null)
    {
        // Lessons this user has already completed — drives the is_completed flag
        // the player uses for checkmarks and the "X of N completed" progress.
        // Union of both stores so the web sidebar matches My Courses / mobile.
        $completed_ids = $uid ? mycom_completed_lesson_ids($uid, $course_id) : [];

        $sections = $this->db->where('course_id', $course_id)
            ->order_by('order', 'ASC')
            ->order_by('id', 'ASC')
            ->get('section')
            ->result_array();

        foreach ($sections as &$section) {
            $section['lessons'] = $this->db->select('id, title, lesson_type, duration, is_free, video_url, video_type, summary, attachment, attachment_type')
                ->where('section_id', $section['id'])
                ->order_by('order', 'ASC')
                ->order_by('id', 'ASC')
                ->get('lesson')
                ->result_array();

            foreach ($section['lessons'] as &$lesson) {
                $lesson['duration_seconds'] = $this->parse_duration_to_seconds($lesson['duration'] ?? '0');
                $lesson['duration'] = $this->format_duration($lesson['duration_seconds']);
                $lesson['duration_formatted'] = $lesson['duration'];
                $lesson['title'] = html_entity_decode($lesson['title'], ENT_QUOTES, 'UTF-8');
                $lesson['is_completed'] = in_array((int)$lesson['id'], $completed_ids, true);

                // Non-video "resource" lessons (PDF / PPTX / docs) expose a downloadable
                // attachment_url. Returns null for quiz/exam lessons whose `attachment`
                // holds a settings JSON blob rather than a real filename.
                $lesson['attachment_url'] = lesson_attachment_url($lesson['attachment'] ?? '');

                // Text lessons ('text' + attachment_type 'description') keep their HTML
                // body HTML-escaped in `attachment`, while `summary` is left empty. Surface
                // that body (decoded) as `summary` so the player renders the reading content
                // instead of an empty "No playable content" screen. Only fill when summary
                // is blank so a genuine summary is never overwritten. (This is why the
                // "Final Quiz" text lesson showed nothing — it isn't a quiz, it's reading.)
                if (($lesson['lesson_type'] ?? '') === 'text'
                    && ($lesson['attachment_type'] ?? '') === 'description'
                    && trim((string) ($lesson['summary'] ?? '')) === ''
                    && !empty($lesson['attachment'])) {
                    $lesson['summary'] = html_entity_decode($lesson['attachment'], ENT_QUOTES, 'UTF-8');
                }

                // H-03: sanitize the lesson body before it reaches the SPA (it is rendered
                // via dangerouslySetInnerHTML). Covers the decoded text-lesson body above
                // and any pre-existing summary. The React client also runs DOMPurify.
                if (!empty($lesson['summary'])) {
                    $lesson['summary'] = mycom_sanitize_html($lesson['summary']);
                }

                // Gate paid video URLs: only enrolled users (or free lessons) get the URL.
                $can_see_video = $include_video_urls || ((int)($lesson['is_free'] ?? 0) === 1);
                if (!$can_see_video) {
                    $lesson['video_url'] = null;
                    $lesson['video_type'] = null;
                    $lesson['attachment_url'] = null;
                }
            }
        }

        return $sections;
    }
    
    private function get_course_reviews($course_id, $limit = 5)
    {
        return $this->db->select('r.*, u.first_name, u.last_name')
            ->from('rating r')
            ->join('users u', 'u.id = r.user_id', 'left')
            ->where('r.ratable_id', $course_id)
            ->where('r.ratable_type', 'course')
            ->order_by('r.id', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
    }
    
    private function get_rating_breakdown($course_id)
    {
        $ratings = $this->db->select('rating, COUNT(*) as count')
            ->from('rating')
            ->where('ratable_id', $course_id)
            ->where('ratable_type', 'course')
            ->group_by('rating')
            ->get()
            ->result_array();
        
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $total = 0;
        $sum = 0;
        
        foreach ($ratings as $r) {
            $breakdown[$r['rating']] = (int)$r['count'];
            $total += $r['count'];
            $sum += $r['rating'] * $r['count'];
        }
        
        return [
            'average' => $total > 0 ? round($sum / $total, 1) : 0,
            'breakdown' => $breakdown
        ];
    }
    
    private function get_course_meta($course_id, $type)
    {
        $course = $this->db->select($type)->get_where('course', ['id' => $course_id])->row_array();
        if ($course && !empty($course[$type])) {
            return json_decode($course[$type], true) ?: [];
        }
        return [];
    }
    
    private function get_related_courses($course_id, $category_id, $limit = 4)
    {
        // Shuffle in PHP over just the id column rather than ORDER BY RAND(),
        // which forces MySQL to score and sort every matching row.
        $ids = array_column(
            $this->db->select('id')
                ->from('course')
                ->where('status', 'active')
                ->where('category_id', $category_id)
                ->where('id !=', $course_id)
                ->get()
                ->result_array(),
            'id'
        );
        if (empty($ids)) return [];

        shuffle($ids);
        $ids = array_slice($ids, 0, $limit);

        $courses = $this->db->select('id, title, thumbnail, price, discounted_price, is_free_course')
            ->from('course')
            ->where_in('id', $ids)
            ->get()
            ->result_array();

        foreach ($courses as &$course) {
            $course['thumbnail_url'] = $this->get_course_thumbnail($course['id']);
        }

        return $courses;
    }
    
    private function get_instructor_students($instructor_id)
    {
        return $this->db->select('e.user_id')
            ->from('enrol e')
            ->join('course c', 'c.id = e.course_id')
            ->where('c.user_id', $instructor_id)
            ->group_by('e.user_id')
            ->count_all_results();
    }
    
    private function get_instructor_courses($instructor_id)
    {
        // Count both published and upcoming courses (an instructor with only
        // upcoming courses still teaches them — matches Api_frontend_model).
        return $this->db->where('user_id', $instructor_id)
            ->where_in('status', ['active', 'upcoming'])
            ->count_all_results('course');
    }
    
    private function get_instructor_rating($instructor_id)
    {
        $result = $this->db->select('AVG(r.rating) as avg')
            ->from('rating r')
            ->join('course c', 'c.id = r.ratable_id AND r.ratable_type = "course"')
            ->where('c.user_id', $instructor_id)
            ->get()
            ->row_array();
        
        return round($result['avg'] ?? 0, 1);
    }
    
    private function get_course_thumbnail($course_id)
    {
        $course = $this->db->select('last_modified')->get_where('course', ['id' => $course_id])->row_array();
        if (!$course) return null;

        $theme = get_frontend_settings('theme') ?: 'default-new';
        $last_modified = $course['last_modified'] ?? '';
        $base_name = 'course_thumbnail_' . $theme . '_' . $course_id . $last_modified;
        $opt_dir = 'uploads/thumbnails/course_thumbnails/optimized/';
        $orig_dir = 'uploads/thumbnails/course_thumbnails/';

        // Use FCPATH for absolute path checks — file_exists() with relative paths
        // is unreliable inside controllers (CWD depends on how PHP was invoked).
        // Prefer WebP first (90%+ smaller), then optimized JPG, then original.
        if (file_exists(FCPATH . $opt_dir . $base_name . '.webp')) {
            return '/' . $opt_dir . $base_name . '.webp';
        }
        if (file_exists(FCPATH . $opt_dir . $base_name . '.jpg')) {
            return '/' . $opt_dir . $base_name . '.jpg';
        }
        if (file_exists(FCPATH . $orig_dir . $base_name . '.jpg')) {
            return '/' . $orig_dir . $base_name . '.jpg';
        }

        // Fallback: return expected optimized path (may 404; caller shows placeholder)
        return '/' . $opt_dir . $base_name . '.jpg';
    }
    
    private function get_user_image($user_id)
    {
        $user = $this->db->select('image')->get_where('users', ['id' => $user_id])->row_array();
        if ($user && !empty($user['image'])) {
            $base = $user['image'];
            // Prefer optimized WebP (tiny), fall back to JPG, then original
            $opt_webp = 'uploads/user_image/optimized/' . $base . '.webp';
            $opt_jpg  = 'uploads/user_image/optimized/' . $base . '.jpg';
            $orig     = 'uploads/user_image/' . $base . '.jpg';
            if (file_exists(FCPATH . $opt_webp)) return '/' . $opt_webp;
            if (file_exists(FCPATH . $opt_jpg))  return '/' . $opt_jpg;
            if (file_exists(FCPATH . $orig))     return '/' . $orig;
        }
        // Fallback to placeholder.png (default.png doesn't exist on disk)
        return '/uploads/user_image/placeholder.png';
    }
    
    private function format_duration($seconds)
    {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . 'm';
        } else {
            $hours = floor($seconds / 3600);
            $mins = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $mins . 'm';
        }
    }
    
    private function parse_duration_to_seconds($duration) {
        if (is_numeric($duration)) return (int)$duration;
        // Handle HH:MM:SS or MM:SS format
        $parts = explode(':', $duration);
        if (count($parts) == 3) {
            return ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
        } elseif (count($parts) == 2) {
            return ((int)$parts[0] * 60) + (int)$parts[1];
        }
        return 0;
    }

    private function time_ago($timestamp)
    {
        $diff = time() - $timestamp;
        
        if ($diff < 86400) {
            return 'Today';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . ' days ago';
        } elseif ($diff < 2592000) {
            return floor($diff / 604800) . ' weeks ago';
        } else {
            return date('M d, Y', $timestamp);
        }
    }
    
    private function authenticate()
    {
        $auth_token = $this->input->get('auth_token') ?: $this->input->post('auth_token');
        if (!$auth_token) {
            $headers = $this->input->request_headers();
            $auth_token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        }
        
        if (!$auth_token) {
            $this->response(['status' => false, 'message' => 'Authentication required'], 401);
            return false;
        }
        
        try {
            $decoded = $this->tokenHandler->DecodeToken($auth_token);
            return $decoded;
        } catch (Exception $e) {
            $this->response(['status' => false, 'message' => 'Invalid or expired token'], 401);
            return false;
        }
    }
}
