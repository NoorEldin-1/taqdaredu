<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Api_frontend_model extends CI_Model {

    function __construct() {
        parent::__construct();
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->output->set_header('Pragma: no-cache');
    }

    // ========== H-03: XSS SANITIZATION HELPER ==========
    /**
     * Strip dangerous HTML while keeping safe formatting tags.
     * Used for any rich-text content returned in API responses.
     * Server-side sanitisation is the authoritative defence;
     * the React frontend also applies DOMPurify as a second layer.
     */
    private function sanitize_html($html) {
        if (empty($html)) return $html;
        // Tags we allow (no script, no iframe, no object, no embed, no form, no on* attrs)
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><h5><h6>';
        $allowed .= '<a><img><table><thead><tbody><tr><td><th><blockquote><pre><code><span><div>';
        // strip_tags removes disallowed tags
        $stripped = strip_tags($html, $allowed);
        // Remove event handlers (onclick, onerror, onload, etc.) from remaining tags
        $stripped = preg_replace('/\s+on\w+\s*=\s*(["\'])[^"\']*(\1)/i', '', $stripped);
        // Remove javascript: and data: URLs from href/src attributes
        $stripped = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|data|vbscript):[^"\']*(\2)/i', '$1=$2#$2', $stripped);
        return $stripped;
    }

    // ========== USER ==========
    public function format_user($user) {
        return [
            'user_id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'role_id' => $user['role_id'],
            'is_instructor' => $user['is_instructor'],
            'image' => $this->get_user_image($user['id']),
            'validity' => 1
        ];
    }

    public function get_user_profile($user_id) {
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        return [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'image' => $this->get_user_image($user['id']),
            'biography' => $user['biography'] ?? '',
            'title' => $user['title'] ?? '',
            'is_instructor' => $user['is_instructor'],
            'social_links' => json_decode($user['social_links'] ?? '{}'),
            'skills' => json_decode($user['skills'] ?? '[]')
        ];
    }

    public function update_user_profile($user_id, $data) {
        $allowed = ['first_name', 'last_name', 'biography', 'title'];
        $update = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) $update[$field] = $data[$field];
        }
        // Email is the login identifier — only accept a valid address that no
        // other account already uses, otherwise report the problem to the caller.
        if (isset($data['email'])) {
            $email = trim((string) $data['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['status' => false, 'message' => 'Please enter a valid email address'];
            }
            $taken = $this->db->where('email', $email)->where('id !=', $user_id)->count_all_results('users');
            if ($taken > 0) {
                return ['status' => false, 'message' => 'That email is already in use'];
            }
            $update['email'] = $email;
        }
        if (isset($data['skills'])) $update['skills'] = json_encode($data['skills']);
        if (isset($data['social_links'])) $update['social_links'] = json_encode($data['social_links']);
        if (!empty($update)) {
            $this->db->where('id', $user_id)->update('users', $update);
        }
        return ['status' => true];
    }

    /**
     * Save a profile image for the user. Accepts either a multipart file upload
     * (field "image" or "user_image") or a base64 string (data-URI or raw) in the
     * "image" POST field. Normalises to JPEG under uploads/user_image/.
     * Returns: null when no image was supplied, or ['status'=>bool, 'image'/'message'].
     */
    public function save_profile_image($user_id) {
        $CI =& get_instance();
        $img = null;

        $file = $_FILES['image'] ?? ($_FILES['user_image'] ?? null);
        if ($file && !empty($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            if ($file['size'] > 5 * 1024 * 1024) {
                return ['status' => false, 'message' => 'Image too large (max 5MB)'];
            }
            $img = @imagecreatefromstring(file_get_contents($file['tmp_name']));
            if (!$img) return ['status' => false, 'message' => 'Unsupported image format'];
        } else {
            $b64 = $CI->input->post('image');
            if (!empty($b64) && is_string($b64) && strlen($b64) > 100) {
                if (($p = strpos($b64, 'base64,')) !== false) $b64 = substr($b64, $p + 7);
                $bin = base64_decode($b64, true);
                if ($bin !== false) $img = @imagecreatefromstring($bin);
                if (!$img) return ['status' => false, 'message' => 'Invalid image data'];
            }
        }

        if (!$img) return null; // nothing to upload — not an error

        // Cap dimensions to keep avatars small
        $w = imagesx($img); $h = imagesy($img);
        $max = 600;
        if ($w > $max || $h > $max) {
            $scale = $max / max($w, $h);
            $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);
            $resized = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }

        $base = 'user_' . (int) $user_id . '_' . time();
        $dir = FCPATH . 'uploads/user_image/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $ok = imagejpeg($img, $dir . $base . '.jpg', 90);
        imagedestroy($img);
        if (!$ok) return ['status' => false, 'message' => 'Could not save the image on the server'];

        // users.image stores the base filename; get_user_image() appends .jpg
        $this->db->where('id', $user_id)->update('users', ['image' => $base]);
        return ['status' => true, 'image' => $this->get_user_image($user_id)];
    }

    public function change_password($user_id, $current, $new) {
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        if (!$user) return ['status' => false, 'message' => 'User not found'];

        // H-06 fix: support both legacy sha1 (existing users) and bcrypt (new users)
        $password_ok = false;
        if (password_verify($current, $user['password'])) {
            $password_ok = true; // bcrypt
        } elseif ($user['password'] === sha1($current)) {
            $password_ok = true; // legacy sha1
        }
        if (!$password_ok) {
            return ['status' => false, 'message' => 'Current password incorrect'];
        }
        // Always re-hash with bcrypt on password change
        $hashed = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->where('id', $user_id)->update('users', ['password' => $hashed]);
        return ['status' => true, 'message' => 'Password changed'];
    }

    // ========== COURSES ==========
    public function get_courses($params) {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 12;
        $offset = ($page - 1) * $limit;

        $this->db->where_in('status', ['active', 'upcoming']);
        // A category filter matches either the parent category OR the sub-category id
        if (!empty($params['category_id'])) {
            $cid = (int) $params['category_id'];
            $this->db->group_start()->where('category_id', $cid)->or_where('sub_category_id', $cid)->group_end();
        }
        if (!empty($params['sub_category_id'])) $this->db->where('sub_category_id', $params['sub_category_id']);
        if (!empty($params['search'])) {
            $this->db->group_start();
            $this->db->like('title', $params['search']);
            $this->db->or_like('short_description', $params['search']);
            $this->db->group_end();
        }
        if (!empty($params['level']) && $params['level'] != 'all') $this->db->where('level', $params['level']);
        if (isset($params['price'])) {
            if ($params['price'] == 'free') $this->db->where('is_free_course', 1);
            elseif ($params['price'] == 'paid') {
                // Group the OR so it doesn't defeat the other WHERE filters.
                $this->db->group_start()->where('is_free_course IS NULL', null, false)->or_where('is_free_course', 0)->group_end();
            }
        }
        if (!empty($params['language']) && $params['language'] != 'all') $this->db->where('language', $params['language']);

        $sort = $params['sort'] ?? 'newest';
        switch ($sort) {
            case 'popular': $this->db->order_by('is_top_course', 'DESC'); break;
            case 'price_low': $this->db->order_by('price', 'ASC'); break;
            case 'price_high': $this->db->order_by('price', 'DESC'); break;
            default: $this->db->order_by('date_added', 'DESC');
        }

        $this->db->from('course');
        $total = $this->db->count_all_results('', false);
        $this->db->limit($limit, $offset);
        $courses = $this->db->get()->result_array();

        return [
            'courses' => $this->format_courses($courses),
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }

    public function get_course_details($id, $user_id = null) {
        // Allow both active and upcoming (Coming Soon) courses to be viewed
        $this->db->where('id', $id)->where_in('status', ['active', 'upcoming']);
        $course = $this->db->get('course')->row_array();
        if (!$course) return null;

        $instructor = $this->db->get_where('users', ['id' => $course['user_id']])->row_array();
        $sections = $this->db->get_where('section', ['course_id' => $id])->result_array();
        
        foreach ($sections as $key => $section) {
            $lessons = $this->db->get_where('lesson', ['section_id' => $section['id']])->result_array();
            $sections[$key]['lessons'] = $lessons;
            $sections[$key]['total_lessons'] = count($lessons);
        }

        $result = $this->format_single_course($course);
        $result['instructor'] = [
            'id' => $instructor['id'],
            'name' => $instructor['first_name'] . ' ' . $instructor['last_name'],
            'image' => $this->get_user_image($instructor['id']),
            'biography' => $instructor['biography'] ?? '',
            'title' => $instructor['title'] ?? ''
        ];
        $result['sections'] = $sections;
        $parent_cat = $this->db->get_where('category', ['id' => $course['category_id']])->row_array();
        $sub_cat = !empty($course['sub_category_id']) ? $this->db->get_where('category', ['id' => $course['sub_category_id']])->row_array() : null;
        // Show the specific sub-category on the detail page; fall back to parent
        $result['category'] = $sub_cat ?: $parent_cat;
        $result['parent_category'] = $parent_cat;
        $result['sub_category'] = $sub_cat;
        $result['ratings'] = $this->get_course_ratings($id);
        $result['enrollment_count'] = $this->db->get_where('enrol', ['course_id' => $id])->num_rows();
        $result['requirements'] = json_decode($course['requirements']) ?: [];
        $result['outcomes'] = json_decode($course['outcomes']) ?: [];
        $result['faqs'] = json_decode($course['faqs']) ?: [];

        // Per-user flags. For a guest (no valid token) both are simply false.
        $is_purchased  = false;   // user is enrolled / owns the course
        $is_wishlisted = false;   // course is in the user's wishlist
        if ($user_id) {
            $is_purchased = $this->db->where(['user_id' => $user_id, 'course_id' => $id])
                                     ->count_all_results('enrol') > 0;
            $wl = $this->db->select('wishlist')->get_where('users', ['id' => $user_id])->row_array();
            $wishlist_ids = json_decode($wl['wishlist'] ?? '[]', true) ?: [];
            $is_wishlisted = in_array((int)$id, array_map('intval', $wishlist_ids), true);
        }
        $result['is_purchased']  = $is_purchased;
        $result['is_enrolled']   = $is_purchased; // alias for clients expecting this name
        $result['is_wishlisted'] = $is_wishlisted;

        // "This course includes" summary for the detail page
        $result['includes'] = [
            'total_lessons'    => $result['total_lessons'] ?? 0,
            'total_sections'   => count($sections),
            'duration'         => $result['duration'],
            'duration_seconds' => $result['duration_seconds'] ?? 0,
            'level'            => $result['level'],
            'language'         => $result['language'],
            'lifetime_access'  => true,
            'mobile_access'    => true,
            'certificate'      => true,
        ];

        return $result;
    }

    // Store a "Notify Me" email capture for an upcoming course (idempotent per course+email)
    public function add_notify_me($course_id, $email, $name = null) {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'Please enter a valid email address'];
        }
        $exists = $this->db->get_where('notify_me', ['course_id' => $course_id, 'email' => $email])->row();
        if ($exists) {
            return ['status' => true, 'message' => "You're already on the list — we'll notify you when this course launches."];
        }
        $this->db->insert('notify_me', [
            'course_id' => $course_id ?: null,
            'email' => $email,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['status' => true, 'message' => "You'll be notified when this course launches."];
    }

    public function get_top_courses($limit) {
        // Featured = only active courses (not upcoming)
        $this->db->where('status', 'active')->order_by('id', 'DESC')->limit($limit);
        return $this->format_courses($this->db->get('course')->result_array());
    }

    public function get_latest_courses($limit) {
        // Latest = active first, then upcoming, with status field
        $this->db->where_in('status', ['active', 'upcoming']);
        $this->db->order_by("FIELD(status, 'active', 'upcoming')", '', false);
        $this->db->order_by('date_added', 'DESC')->limit($limit);
        return $this->format_courses($this->db->get('course')->result_array());
    }

    public function get_free_courses($limit) {
        $this->db->where('is_free_course', 1)->where('status', 'active')->limit($limit);
        return $this->format_courses($this->db->get('course')->result_array());
    }

    public function get_user_courses($user_id) {
        $this->db->select('course.*, enrol.date_added as enrolled_date');
        $this->db->from('enrol')->join('course', 'course.id = enrol.course_id');
        $this->db->where('enrol.user_id', $user_id)->where('course.status', 'active');
        $courses = $this->db->get()->result_array();

        $result = [];
        foreach ($courses as $course) {
            $formatted = $this->format_single_course($course);
            $formatted['enrolled_date'] = $course['enrolled_date'];
            $formatted['progress'] = $this->get_course_progress($user_id, $course['id']);
            $result[] = $formatted;
        }
        return $result;
    }

    public function enroll_free_course($user_id, $course_id) {
        $course = $this->db->get_where('course', ['id' => $course_id, 'status' => 'active'])->row_array();
        if (!$course) return ['status' => false, 'message' => 'Course not found'];
        if ($course['is_free_course'] != 1) return ['status' => false, 'message' => 'Course is not free'];
        if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $course_id])->num_rows() > 0) {
            return ['status' => false, 'message' => 'Already enrolled'];
        }

        $data = ['user_id' => $user_id, 'course_id' => $course_id, 'date_added' => time()];
        if ($course['expiry_period'] > 0) $data['expiry_date'] = strtotime("+{$course['expiry_period']} months");
        $this->db->insert('enrol', $data);
        return ['status' => true, 'message' => 'Enrolled successfully'];
    }

    // ========== CATEGORIES ==========
    public function get_categories() {
        $this->db->where('parent', 0);
        $categories = $this->db->get('category')->result_array();

        foreach ($categories as $key => $cat) {
            $categories[$key]['thumbnail'] = $this->get_category_thumbnail($cat['thumbnail']);
            $subs = $this->db->get_where('category', ['parent' => $cat['id']])->result_array();
            foreach ($subs as $sk => $sub) {
                $subs[$sk]['thumbnail'] = $this->get_category_thumbnail($sub['thumbnail']);
                $this->db->where('sub_category_id', $sub['id'])->where_in('status', ['active', 'upcoming']);
                $subs[$sk]['course_count'] = $this->db->count_all_results('course');
            }
            $categories[$key]['sub_categories'] = $subs;
            $this->db->where('category_id', $cat['id'])->where_in('status', ['active', 'upcoming']);
            $categories[$key]['course_count'] = $this->db->count_all_results('course');
        }
        return $categories;
    }

    public function get_category($id) {
        $category = $this->db->get_where('category', ['id' => $id])->row_array();
        if (!$category) return null;
        $category['thumbnail'] = $this->get_category_thumbnail($category['thumbnail']);
        if ($category['parent'] == 0) {
            $category['sub_categories'] = $this->db->get_where('category', ['parent' => $id])->result_array();
        }
        return $category;
    }

    public function get_category_courses($id) {
        $category = $this->db->get_where('category', ['id' => $id])->row_array();
        if (!$category) return false; // unknown category -> caller maps to 404
        if ($category['parent'] > 0) $this->db->where('sub_category_id', $id);
        else $this->db->where('category_id', $id);
        $this->db->where('status', 'active');
        return $this->format_courses($this->db->get('course')->result_array());
    }

    // ========== INSTRUCTORS ==========
    public function get_instructors($params) {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 12;
        $offset = ($page - 1) * $limit;

        $this->db->where('is_instructor', 1)->where('status', 1);
        $this->db->from('users');
        $total = $this->db->count_all_results('', false);
        $this->db->limit($limit, $offset);
        $instructors = $this->db->get()->result_array();

        $result = [];
        foreach ($instructors as $i) {
            $this->db->where('user_id', $i['id'])->where_in('status', ['active', 'upcoming']);
            $course_count = $this->db->count_all_results('course');

            $this->db->select('COUNT(DISTINCT enrol.user_id) as cnt')->from('enrol');
            $this->db->join('course', 'course.id = enrol.course_id')->where('course.user_id', $i['id']);
            $student_count = $this->db->get()->row()->cnt;

            $this->db->select('AVG(rating.rating) as avg')->from('rating');
            $this->db->join('course', 'course.id = rating.ratable_id AND rating.ratable_type = "course"');
            $this->db->where('course.user_id', $i['id']);
            $avg_rating = $this->db->get()->row()->avg;

            $result[] = [
                'id' => $i['id'],
                'name' => trim($i['first_name'] . ' ' . $i['last_name']),
                'first_name' => $i['first_name'],
                'last_name' => $i['last_name'],
                'image' => $this->get_user_image($i['id']),
                'title' => $i['biography'] ? mb_substr(strip_tags($i['biography']), 0, 80) : 'Instructor',
                'rating' => round($avg_rating ?? 0, 1),
                'total_courses' => $course_count,
                'course_count' => $course_count,
                'total_students' => (int)$student_count,
                'student_count' => (int)$student_count
            ];
        }

        return [
            'instructors' => $result,
            'pagination' => ['current_page' => (int)$page, 'per_page' => (int)$limit, 'total' => $total, 'total_pages' => ceil($total / $limit)]
        ];
    }

    public function get_instructor_details($id) {
        $i = $this->db->get_where('users', ['id' => $id, 'is_instructor' => 1])->row_array();
        if (!$i) return null;

        $this->db->where('user_id', $id)->where_in('status', ['active', 'upcoming']);
        $courses = $this->db->get('course')->result_array();

        $this->db->select('COUNT(DISTINCT enrol.user_id) as cnt')->from('enrol');
        $this->db->join('course', 'course.id = enrol.course_id')->where('course.user_id', $id);
        $student_count = $this->db->get()->row()->cnt;

        $this->db->select('AVG(rating.rating) as avg, COUNT(rating.id) as cnt')->from('rating');
        $this->db->join('course', 'course.id = rating.ratable_id')->where('course.user_id', $id)->where('rating.ratable_type', 'course');
        $rating_data = $this->db->get()->row();

        $skills = json_decode($i['skills'] ?? '[]', true);
        $social = json_decode($i['social_links'] ?? '{}', true);

        return [
            'id' => $i['id'],
            'name' => trim($i['first_name'] . ' ' . $i['last_name']),
            'first_name' => $i['first_name'],
            'last_name' => $i['last_name'],
            'email' => $i['email'],
            'image' => $this->get_user_image($i['id']),
            'title' => $i['biography'] ? mb_substr(strip_tags($i['biography']), 0, 80) : 'Instructor',
            'biography' => $i['biography'] ? strip_tags($i['biography']) : '',
            'skills' => is_array($skills) ? $skills : [],
            'social_links' => is_array($social) ? $social : (is_object($social) ? (array)$social : null),
            'total_courses' => count($courses),
            'course_count' => count($courses),
            'total_students' => (int)$student_count,
            'student_count' => (int)$student_count,
            'rating' => round($rating_data->avg ?? 0, 1),
            'avg_rating' => round($rating_data->avg ?? 0, 1),
            'total_reviews' => (int)($rating_data->cnt ?? 0),
            'review_count' => (int)($rating_data->cnt ?? 0),
            'courses' => $this->format_courses($courses)
        ];
    }

    // ========== WISHLIST ==========
    public function get_wishlist($user_id) {
        $user = $this->db->select('wishlist')->get_where('users', ['id' => $user_id])->row_array();
        $wishlist_ids = json_decode($user['wishlist'] ?? '[]', true) ?: [];
        if (empty($wishlist_ids)) return [];
        $this->db->where_in('id', $wishlist_ids)->where('status', 'active');
        return $this->format_courses($this->db->get('course')->result_array());
    }

    public function toggle_wishlist($user_id, $course_id) {
        $user = $this->db->select('wishlist')->get_where('users', ['id' => $user_id])->row_array();
        $wishlist = json_decode($user['wishlist'] ?? '[]', true) ?: [];
        $course_id = (int)$course_id;
        if (in_array($course_id, $wishlist)) {
            $wishlist = array_values(array_diff($wishlist, [$course_id]));
            $this->db->where('id', $user_id)->update('users', ['wishlist' => json_encode($wishlist)]);
            return ['status' => true, 'message' => 'Removed from wishlist', 'data' => ['action' => 'removed']];
        }
        $wishlist[] = $course_id;
        $this->db->where('id', $user_id)->update('users', ['wishlist' => json_encode($wishlist)]);
        return ['status' => true, 'message' => 'Added to wishlist', 'data' => ['action' => 'added']];
    }

    // ========== CART (Database-based, not session) ==========
    public function get_cart($user_id) {
        $user = $this->db->select('cart_items')->get_where('users', ['id' => $user_id])->row_array();
        $cart_ids = json_decode($user['cart_items'] ?? '[]', true) ?: [];
        $courses = [];
        $total = 0;

        foreach ($cart_ids as $id) {
            $course = $this->db->get_where('course', ['id' => $id])->row_array();
            if ($course) {
                $formatted = $this->format_single_course($course);
                $courses[] = $formatted;
                if ($course['is_free_course'] != 1) {
                    $total += ($course['discount_flag'] == 1 && $course['discounted_price'] > 0) ? (float)$course['discounted_price'] : (float)$course['price'];
                }
            }
        }
        return ['items' => $courses, 'total' => round($total, 2), 'currency' => get_settings('currency_code') ?: 'GBP'];
    }

    public function add_to_cart($user_id, $course_id) {
        $user = $this->db->select('cart_items')->get_where('users', ['id' => $user_id])->row_array();
        $items = json_decode($user['cart_items'] ?? '[]', true) ?: [];
        $course_id = (int)$course_id;

        // Check if already enrolled
        if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $course_id])->num_rows() > 0) {
            return ['status' => false, 'message' => 'Already enrolled in this course'];
        }

        if (!in_array($course_id, $items)) {
            $items[] = $course_id;
            $this->db->where('id', $user_id)->update('users', ['cart_items' => json_encode($items)]);
        }
        return ['status' => true, 'message' => 'Added to cart', 'data' => ['cart_count' => count($items)]];
    }

    public function remove_from_cart($user_id, $course_id) {
        $user = $this->db->select('cart_items')->get_where('users', ['id' => $user_id])->row_array();
        $items = json_decode($user['cart_items'] ?? '[]', true) ?: [];
        $items = array_values(array_diff($items, [(int)$course_id]));
        $this->db->where('id', $user_id)->update('users', ['cart_items' => json_encode($items)]);
        return ['status' => true, 'message' => 'Removed from cart', 'data' => ['cart_count' => count($items)]];
    }

    // ========== LESSONS ==========
    public function get_course_sections($user_id, $course_id) {
        if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $course_id])->num_rows() == 0) {
            return ['error' => 'Not enrolled'];
        }

        // Completed lessons come from the canonical watch_histories row.
        $wh = $this->db->get_where('watch_histories', ['course_id' => $course_id, 'student_id' => $user_id])->row_array();
        $completed = $wh ? array_map('strval', json_decode($wh['completed_lesson'], true) ?: []) : [];

        // Order by the admin-defined `order` column — same as the web sidebar
        // (Api_courses) and admin panel — so every surface lists sections and
        // lessons in the SAME sequence (this endpoint previously had no ORDER BY,
        // so it fell back to insertion/id order and drifted out of sync).
        $sections = $this->db->order_by('order', 'ASC')->order_by('id', 'ASC')->get_where('section', ['course_id' => $course_id])->result_array();
        foreach ($sections as $key => $section) {
            $lessons = $this->db->order_by('order', 'ASC')->order_by('id', 'ASC')->get_where('lesson', ['section_id' => $section['id']])->result_array();
            foreach ($lessons as $lkey => $lesson) {
                $lessons[$lkey]['is_completed'] = in_array((string) $lesson['id'], $completed, true) ? 1 : 0;
                $lessons[$lkey]['watched_duration'] = 0;
                // Resource lessons (PDF/PPTX/doc) expose a full download URL so the
                // client doesn't have to guess the path. Returns null for video
                // lessons (empty attachment) and quiz/exam lessons (JSON settings blob).
                $lessons[$lkey]['attachment_url'] = lesson_attachment_url($lesson['attachment'] ?? '');
            }
            $sections[$key]['lessons'] = $lessons;
        }
        return $sections;
    }

    public function get_lesson($user_id, $lesson_id) {
        $lesson = $this->db->get_where('lesson', ['id' => $lesson_id])->row_array();
        if (!$lesson) return ['error' => 'Lesson not found'];

        $section = $this->db->get_where('section', ['id' => $lesson['section_id']])->row_array();
        if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $section['course_id']])->num_rows() == 0) {
            return ['error' => 'Not enrolled'];
        }

        $lesson['attachments'] = json_decode($lesson['attachment']) ?: [];
        return $lesson;
    }

    public function mark_lesson_complete($user_id, $lesson_id) {
        $lesson = $this->db->get_where('lesson', ['id' => $lesson_id])->row_array();
        if (!$lesson) return ['status' => false, 'message' => 'Lesson not found'];
        $course_id = $lesson['course_id'];
        if (empty($course_id)) {
            $section = $this->db->get_where('section', ['id' => $lesson['section_id']])->row_array();
            $course_id = $section['course_id'] ?? null;
        }
        if (!$course_id) return ['status' => false, 'message' => 'Course not found'];

        // Canonical store shared with progress/certificates/badges.
        $row = $this->db->get_where('watch_histories', ['course_id' => $course_id, 'student_id' => $user_id])->row_array();
        $completed = $row ? (json_decode($row['completed_lesson'], true) ?: []) : [];
        if (!in_array((string) $lesson_id, array_map('strval', $completed), true)) {
            $completed[] = (int) $lesson_id;
        }

        $total_lessons = (int) $this->db->where('course_id', $course_id)->count_all_results('lesson');
        $progress = $total_lessons > 0 ? (int) round(count($completed) / $total_lessons * 100) : 0;
        if ($progress > 100) $progress = 100;

        if ($row) {
            $update = [
                'completed_lesson' => json_encode($completed),
                'course_progress' => $progress,
                'watching_lesson_id' => $lesson_id,
                'date_updated' => time(),
            ];
            if ($progress >= 100) $update['completed_date'] = time();
            $this->db->where('watch_history_id', $row['watch_history_id'])->update('watch_histories', $update);
        } else {
            $this->db->insert('watch_histories', [
                'course_id' => $course_id,
                'student_id' => $user_id,
                'completed_lesson' => json_encode($completed),
                'course_progress' => $progress,
                'watching_lesson_id' => $lesson_id,
                'quiz_result' => '[]',
                'completed_date' => $progress >= 100 ? time() : null,
                'date_added' => time(),
            ]);
        }
        return ['status' => true, 'message' => 'Lesson completed', 'data' => ['course_progress' => $progress]];
    }

    // ========== REVIEWS ==========
    public function get_course_reviews($course_id) {
        $this->db->select('rating.*, users.first_name, users.last_name, users.image');
        $this->db->from('rating')->join('users', 'users.id = rating.user_id');
        $this->db->where('rating.ratable_id', $course_id)->where('rating.ratable_type', 'course');
        $this->db->order_by('rating.date_added', 'DESC');
        $reviews = $this->db->get()->result_array();

        foreach ($reviews as $key => $r) {
            $reviews[$key]['user'] = ['name' => $r['first_name'] . ' ' . $r['last_name'], 'image' => $this->get_user_image_by_filename($r['image'])];
            unset($reviews[$key]['first_name'], $reviews[$key]['last_name'], $reviews[$key]['image']);
        }
        return $reviews;
    }

    public function add_course_review($user_id, $course_id, $rating, $review) {
        if (!$rating || $rating < 1 || $rating > 5) return ['status' => false, 'message' => 'Rating must be 1-5'];

        $existing = $this->db->get_where('rating', ['user_id' => $user_id, 'ratable_id' => $course_id, 'ratable_type' => 'course'])->row();
        if ($existing) {
            $this->db->where('id', $existing->id)->update('rating', ['rating' => $rating, 'review' => $review, 'date_added' => time()]);
            return ['status' => true, 'message' => 'Review updated'];
        }
        $this->db->insert('rating', ['user_id' => $user_id, 'ratable_id' => $course_id, 'ratable_type' => 'course', 'rating' => $rating, 'review' => $review, 'date_added' => time()]);
        return ['status' => true, 'message' => 'Review added'];
    }

    // ========== BLOG ==========
    public function get_blogs($params) {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $offset = ($page - 1) * $limit;

        $this->db->where('status', '1');
        if (!empty($params['category_id'])) $this->db->where('blog_category_id', $params['category_id']);
        $this->db->from('blogs');
        $total = $this->db->count_all_results('', false);
        $this->db->order_by('added_date', 'DESC')->limit($limit, $offset);
        $blogs = $this->db->get()->result_array();

        foreach ($blogs as $key => $b) {
            $blogs[$key]['id'] = $b['blog_id'];
            $blogs[$key]['title'] = html_entity_decode($b['title'], ENT_QUOTES, 'UTF-8');
            $blogs[$key]['thumbnail'] = $this->optimized_path('/uploads/blog/thumbnail/' . $b['thumbnail']);
            $blogs[$key]['banner'] = $this->optimized_path('/uploads/blog/banner/' . $b['banner']);
            $author = $this->db->get_where('users', ['id' => $b['user_id']])->row_array();
            $blogs[$key]['author'] = ['name' => $author ? $author['first_name'] . ' ' . $author['last_name'] : '', 'image' => $author ? $this->get_user_image($author['id']) : null];
            $cat = $this->db->get_where('blog_category', ['blog_category_id' => $b['blog_category_id']])->row_array();
            $blogs[$key]['category'] = ['name' => $cat ? $cat['title'] : 'Blog'];
            $blogs[$key]['created_at'] = date('Y-m-d H:i:s', $b['added_date']);
            // Clean blog description for excerpt
            $blogs[$key]['excerpt'] = $this->clean_excerpt($b['description']);
            $blogs[$key]['read_time'] = $this->calc_read_time($b['description']);
        }

        return ['blogs' => $blogs, 'pagination' => ['current_page' => (int)$page, 'per_page' => (int)$limit, 'total' => $total, 'total_pages' => ceil($total / $limit)]];
    }

    public function get_blog_details($id) {
        $blog = $this->db->get_where('blogs', ['blog_id' => $id, 'status' => '1'])->row_array();
        if (!$blog) return null;

        $blog['id'] = $blog['blog_id'];
        $blog['title'] = html_entity_decode($blog['title'], ENT_QUOTES, 'UTF-8');
        // Decode and clean the content for proper display
        $blog['content'] = $this->clean_blog_content($blog['description']);
        $blog['created_at'] = date('Y-m-d H:i:s', $blog['added_date']);
        $blog['excerpt'] = $this->clean_excerpt($blog['description']);
        $blog['read_time'] = $this->calc_read_time($blog['description']);
        $blog['thumbnail'] = $this->optimized_path('/uploads/blog/thumbnail/' . $blog['thumbnail']);
        $blog['banner'] = $this->optimized_path('/uploads/blog/banner/' . $blog['banner']);
        $author = $this->db->get_where('users', ['id' => $blog['user_id']])->row_array();
        $blog['author'] = ['name' => $author ? $author['first_name'] . ' ' . $author['last_name'] : '', 'image' => $this->get_user_image($blog['user_id'])];
        $cat = $this->db->get_where('blog_category', ['blog_category_id' => $blog['blog_category_id']])->row_array();
        $blog['category'] = ['name' => $cat ? $cat['title'] : 'Blog'];

        $comments = $this->db->get_where('blog_comments', ['blog_id' => $id, 'parent_id' => 0])->result_array();
        foreach ($comments as $key => $c) {
            $u = $this->db->get_where('users', ['id' => $c['user_id']])->row_array();
            $comments[$key]['user'] = ['name' => $u ? $u['first_name'] . ' ' . $u['last_name'] : 'Anonymous', 'image' => $this->get_user_image($c['user_id'])];
            $replies = $this->db->get_where('blog_comments', ['parent_id' => $c['blog_comment_id']])->result_array();
            foreach ($replies as $rk => $r) {
                $ru = $this->db->get_where('users', ['id' => $r['user_id']])->row_array();
                $replies[$rk]['user'] = ['name' => $ru ? $ru['first_name'] . ' ' . $ru['last_name'] : 'Anonymous', 'image' => $this->get_user_image($r['user_id'])];
            }
            $comments[$key]['replies'] = $replies;
        }
        $blog['comments'] = $comments;
        return $blog;
    }

    public function get_blog_categories() {
        $cats = $this->db->get('blog_category')->result_array();
        foreach ($cats as $key => $c) {
            $cats[$key]['id'] = (int)$c['blog_category_id'];
            $cats[$key]['name'] = $c['title'];
            $this->db->where('blog_category_id', $c['blog_category_id'])->where('status', '1');
            $cats[$key]['blog_count'] = $this->db->count_all_results('blogs');
        }
        return $cats;
    }

    // ========== SETTINGS ==========
    public function get_settings() {
        return [
            'site_name' => get_settings('system_name'),
            'site_title' => get_settings('system_title'),
            'site_email' => get_settings('system_email'),
            'currency' => get_settings('currency_code'),
            'currency_symbol' => get_settings('currency_symbol'),
            'language' => get_settings('language'),
            'banner_title' => get_frontend_settings('banner_title'),
            'banner_subtitle' => get_frontend_settings('banner_sub_title'),
            'light_logo' => '/uploads/system/' . get_frontend_settings('light_logo'),
            'dark_logo' => '/uploads/system/' . get_frontend_settings('dark_logo'),
            'favicon' => '/uploads/system/' . get_frontend_settings('favicon'),
            'facebook' => get_frontend_settings('facebook'),
            'twitter' => get_frontend_settings('twitter'),
            'linkedin' => get_frontend_settings('linkedin'),
            'contact_info' => json_decode(get_frontend_settings('contact_info'))
        ];
    }

    public function get_page($slug) {
        // FAQ content comes from the shared faq helper (same source as the SEO page).
        if ($slug === 'faq') {
            $this->load->helper('faq');
            return ['slug' => 'faq', 'title' => 'الأسئلة الشائعة', 'content' => mycom_faq_html()];
        }
        $pages = [
            'about' => 'about_us', 'about-us' => 'about_us',
            'privacy' => 'privacy_policy', 'privacy-policy' => 'privacy_policy',
            'terms' => 'terms_and_condition', 'terms-and-conditions' => 'terms_and_condition',
            'refund' => 'refund_policy', 'refund-policy' => 'refund_policy',
            'cookie' => 'cookie_policy', 'cookie-policy' => 'cookie_policy',
        ];
        // Arabic titles for the built-in pages. The previous fallback
        // title-cased the slug, which shipped English headings ("Privacy
        // Policy") onto an Arabic site.
        $titles = [
            'about' => 'من نحن', 'about-us' => 'من نحن',
            'privacy' => 'سياسة الخصوصية', 'privacy-policy' => 'سياسة الخصوصية',
            'terms' => 'الشروط والأحكام', 'terms-and-conditions' => 'الشروط والأحكام',
            'refund' => 'سياسة الاسترجاع', 'refund-policy' => 'سياسة الاسترجاع',
            'cookie' => 'سياسة ملفات تعريف الارتباط', 'cookie-policy' => 'سياسة ملفات تعريف الارتباط',
        ];
        if (isset($pages[$slug])) {
            $content = get_frontend_settings($pages[$slug]);
            return ['slug' => $slug, 'title' => $titles[$slug], 'content' => $content];
        }
        $page = $this->db->get_where('custom_page', ['page_url' => $slug, 'status' => 1])->row_array();
        if ($page) return ['title' => $page['page_title'], 'content' => $page['page_content']];
        return null;
    }

    public function get_languages() {
        $courses = $this->db->select('language')->distinct()->get('course')->result_array();
        return array_values(array_filter(array_column($courses, 'language')));
    }

    // ========== CONTACT ==========
    public function submit_contact($data) {
        if (empty($data['first_name']) || empty($data['email']) || empty($data['message'])) {
            return ['status' => false, 'message' => 'Required fields missing'];
        }
        // GDPR consent must be given
        if (empty($data['i_agree']) || $data['i_agree'] != '1') {
            return ['status' => false, 'message' => 'Please agree to your data being stored so we can respond.'];
        }
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'Please enter a valid email address.'];
        }
        // Sanitize inputs (strip tags + trim) to prevent XSS / injection
        $clean = function ($v) { return trim(strip_tags((string) $v)); };
        $this->db->insert('contact', [
            'first_name' => $clean($data['first_name']),
            'last_name'  => $clean($data['last_name'] ?? ''),
            'email'      => $clean($data['email']),
            'phone'      => $clean($data['phone'] ?? ''),
            'message'    => $clean($data['message']),
            'has_read'   => 0,
            'created_at' => time()
        ]);
        return ['status' => true, 'message' => 'Message sent'];
    }

    // ========== SEARCH ==========
    public function search($query) {
        $this->db->like('title', $query)->or_like('short_description', $query)->where('status', 'active')->limit(10);
        $courses = $this->format_courses($this->db->get('course')->result_array());

        $this->db->like('first_name', $query)->or_like('last_name', $query)->where('is_instructor', 1)->where('status', 1)->limit(5);
        $instructors = array_map(function($i) {
            return ['id' => $i['id'], 'name' => $i['first_name'] . ' ' . $i['last_name'], 'image' => $this->get_user_image($i['id'])];
        }, $this->db->get('users')->result_array());

        $this->db->like('title', $query)->where('status', 'active')->limit(5);
        // Arrow fn inherits $this from enclosing scope (PHP 7.4+)
        $blogs = array_map(fn($b) => [
            'id' => $b['blog_id'],
            'title' => $b['title'],
            'thumbnail' => $this->optimized_path('/uploads/blog/thumbnail/' . $b['thumbnail']),
        ], $this->db->get('blogs')->result_array());

        return ['courses' => $courses, 'instructors' => $instructors, 'blogs' => $blogs];
    }

    // ========== COUPON ==========
    public function apply_coupon($code) {
        $coupon = $this->db->get_where('coupons', ['code' => $code])->row_array();
        if (!$coupon) return ['status' => false, 'message' => 'Invalid coupon'];
        // Treat NULL/0 expiry as "no expiry"; only a positive past timestamp expires.
        $exp = $coupon['expiry_date'];
        if (!empty($exp) && is_numeric($exp) && (int) $exp > 0 && (int) $exp < time()) {
            return ['status' => false, 'message' => 'Coupon expired'];
        }
        // Clamp the percentage to a sane [0,100] range.
        $pct = (float) $coupon['discount_percentage'];
        if ($pct < 0) $pct = 0;
        if ($pct > 100) $pct = 100;
        return ['status' => true, 'message' => 'Coupon applied', 'data' => ['discount' => $pct]];
    }

    // ========== HOME PAGE DATA ==========
    public function get_home_data() {
        return [
            'banner' => [
                'title' => get_frontend_settings('banner_title'),
                'subtitle' => get_frontend_settings('banner_sub_title'),
                'image' => '/uploads/system/' . (json_decode(get_frontend_settings('banner_image'))->home_1 ?? 'home-1.png')
            ],
            'top_courses' => $this->get_top_courses(8),
            'latest_courses' => $this->get_latest_courses(8),
            'free_courses' => $this->get_free_courses(4),
            'top_categories' => $this->get_top_categories(8),
            'top_instructors' => $this->get_top_instructors(4),
            'upcoming_courses' => $this->get_upcoming_courses(4),
            'stats' => [
                'total_courses' => $this->db->where('status', 'active')->count_all_results('course'),
                'total_students' => $this->db->where('role_id', 2)->where('status', 1)->count_all_results('users'),
                'total_instructors' => $this->db->where('is_instructor', 1)->where('status', 1)->count_all_results('users'),
                'total_enrollments' => $this->db->count_all_results('enrol')
            ],
            'recent_blogs' => $this->get_recent_blogs(3),
            'testimonials' => $this->get_testimonials(10),
            'faqs' => json_decode(get_frontend_settings('website_faqs')) ?: [],
            'motivational_speech' => json_decode(get_frontend_settings('motivational_speech')) ?: [],
            'sections' => [
                'top_course' => get_frontend_settings('top_course_section') == '1',
                'latest_course' => get_frontend_settings('latest_course_section') == '1',
                'top_category' => get_frontend_settings('top_category_section') == '1',
                'upcoming_course' => get_frontend_settings('upcoming_course_section') == '1',
                'top_instructor' => get_frontend_settings('top_instructor_section') == '1',
                'faq' => get_frontend_settings('faq_section') == '1',
                'blog' => get_frontend_settings('blog_visibility_on_the_home_page') == '1',
                'review' => get_frontend_settings('review_section') == '1'
            ]
        ];
    }

    public function get_top_categories($limit) {
        $this->db->where('parent', 0)->limit($limit);
        $categories = $this->db->get('category')->result_array();
        foreach ($categories as $key => $cat) {
            $categories[$key]['thumbnail'] = $this->get_category_thumbnail($cat['thumbnail']);
            $this->db->where('category_id', $cat['id'])->where('status', 'active');
            $categories[$key]['course_count'] = $this->db->count_all_results('course');
        }
        return $categories;
    }

    // ========== UPCOMING COURSES ==========
    public function get_upcoming_courses($limit) {
        $this->db->where('status', 'upcoming')->order_by('publish_date', 'ASC')->limit($limit);
        $courses = $this->db->get('course')->result_array();
        $result = [];
        foreach ($courses as $c) {
            $result[] = [
                'id' => $c['id'],
                'title' => $c['title'],
                'short_description' => $c['short_description'],
                'thumbnail' => $c['upcoming_image_thumbnail'] ? $this->optimized_path('/uploads/thumbnails/upcoming_thumbnails/' . $c['upcoming_image_thumbnail']) : $this->get_course_thumbnail($c['id']),
                'publish_date' => $c['publish_date'],
                'instructor_name' => $this->get_instructor_name($c['user_id'])
            ];
        }
        return $result;
    }

    // ========== DISCOUNTED COURSES ==========
    public function get_discounted_courses($limit) {
        $this->db->where('discount_flag', 1)->where('status', 'active')->limit($limit);
        return $this->format_courses($this->db->get('course')->result_array());
    }

    // ========== SUB CATEGORIES ==========
    public function get_sub_categories($parent_id) {
        $this->db->where('parent', $parent_id);
        $subs = $this->db->get('category')->result_array();
        foreach ($subs as $key => $sub) {
            $subs[$key]['thumbnail'] = $this->get_category_thumbnail($sub['sub_category_thumbnail']);
            $this->db->where('sub_category_id', $sub['id'])->where('status', 'active');
            $subs[$key]['course_count'] = $this->db->count_all_results('course');
        }
        return $subs;
    }

    // ========== TOP INSTRUCTORS ==========
    public function get_top_instructors($limit) {
        $this->db->select('users.*, COUNT(enrol.id) as student_count')
            ->from('users')
            ->join('course', 'course.user_id = users.id', 'left')
            ->join('enrol', 'enrol.course_id = course.id', 'left')
            ->where('users.is_instructor', 1)
            ->where('users.status', 1)
            ->group_by('users.id')
            ->order_by('student_count', 'DESC')
            ->limit($limit);
        $instructors = $this->db->get()->result_array();

        $result = [];
        foreach ($instructors as $i) {
            $this->db->where('user_id', $i['id'])->where_in('status', ['active', 'upcoming']);
            $course_count = $this->db->count_all_results('course');

            $this->db->select('AVG(rating.rating) as avg, COUNT(rating.id) as cnt')->from('rating');
            $this->db->join('course', 'course.id = rating.ratable_id AND rating.ratable_type = "course"');
            $this->db->where('course.user_id', $i['id']);
            $rating = $this->db->get()->row();

            $skills = !empty($i['skills']) ? json_decode($i['skills'], true) : [];

            $result[] = [
                'id' => $i['id'],
                'name' => $i['first_name'] . ' ' . $i['last_name'],
                'image' => $this->get_user_image($i['id']),
                'title' => $i['biography'] ? mb_substr(strip_tags($i['biography']), 0, 80) : 'Instructor',
                'biography' => $i['biography'] ? strip_tags($i['biography']) : '',
                'skills' => is_array($skills) ? $skills : [],
                'total_courses' => $course_count,
                'total_students' => (int)$i['student_count'],
                'rating' => round($rating->avg ?? 0, 1),
                'review_count' => (int)($rating->cnt ?? 0),
                'course_count' => $course_count,
                'student_count' => (int)$i['student_count']
            ];
        }
        return $result;
    }

    // ========== TESTIMONIALS ==========
    public function get_testimonials($limit = 10) {
        // Real homepage testimonials = ratings NOT tied to a specific course
        // (ratable_type IS NULL) — genuine student reviews of the academy.
        $this->db->select('rating.*, users.first_name, users.last_name, users.id as user_id')
            ->from('rating')
            ->join('users', 'users.id = rating.user_id')
            ->where('rating.ratable_type IS NULL', NULL, false)
            ->order_by('rating.id', 'ASC')
            ->limit($limit);
        $ratings = $this->db->get()->result_array();

        $result = [];
        foreach ($ratings as $r) {
            $course = null;
            if ($r['ratable_type'] == 'course') {
                $course = $this->db->select('title')->get_where('course', ['id' => $r['ratable_id']])->row_array();
            }
            $result[] = [
                'id' => $r['id'],
                'name' => trim($r['first_name'] . ' ' . $r['last_name']),
                'image' => $this->get_user_image($r['user_id']),
                'rating' => (int)$r['rating'],
                'review' => $r['review'] ?? '',
                'course_title' => $course ? $course['title'] : '',
                'date' => isset($r['date_added']) ? date('Y-m-d', $r['date_added']) : null
            ];
        }
        return $result;
    }

    // ========== FOLLOW INSTRUCTOR ==========
    public function toggle_follow_instructor($user_id, $instructor_id) {
        $existing = $this->db->get_where('instructor_followings', ['user_id' => $user_id, 'instructor_id' => $instructor_id])->row();
        if ($existing) {
            $new_status = $existing->is_following == 1 ? 0 : 1;
            $this->db->where('id', $existing->id)->update('instructor_followings', ['is_following' => $new_status, 'updated_at' => time()]);
            return ['status' => true, 'message' => $new_status ? 'Following' : 'Unfollowed', 'data' => ['is_following' => $new_status]];
        }
        $this->db->insert('instructor_followings', ['user_id' => $user_id, 'instructor_id' => $instructor_id, 'is_following' => 1, 'created_at' => time(), 'updated_at' => time()]);
        return ['status' => true, 'message' => 'Following', 'data' => ['is_following' => 1]];
    }

    public function get_following_instructors($user_id) {
        $this->db->select('users.*')->from('instructor_followings')
            ->join('users', 'users.id = instructor_followings.instructor_id')
            ->where('instructor_followings.user_id', $user_id)
            ->where('instructor_followings.is_following', 1);
        $instructors = $this->db->get()->result_array();

        $result = [];
        foreach ($instructors as $i) {
            $this->db->where('user_id', $i['id'])->where('status', 'active');
            $course_count = $this->db->count_all_results('course');
            $result[] = [
                'id' => $i['id'],
                'name' => $i['first_name'] . ' ' . $i['last_name'],
                'image' => $this->get_user_image($i['id']),
                'title' => $i['title'] ?? '',
                'course_count' => $course_count
            ];
        }
        return $result;
    }

    // ========== COURSE CURRICULUM PREVIEW ==========
    public function get_course_curriculum_preview($course_id) {
        $sections = $this->db->get_where('section', ['course_id' => $course_id])->result_array();
        $total_lessons = 0;
        $total_duration = 0;

        foreach ($sections as $key => $section) {
            $lessons = $this->db->get_where('lesson', ['section_id' => $section['id']])->result_array();
            $sections[$key]['lessons'] = array_map(function($l) {
                return [
                    'id' => $l['id'],
                    'title' => $l['title'],
                    'duration' => $l['duration'],
                    'lesson_type' => $l['lesson_type'],
                    'is_free' => $l['is_free'] == 1
                ];
            }, $lessons);
            $sections[$key]['lesson_count'] = count($lessons);
            $total_lessons += count($lessons);
            foreach ($lessons as $l) {
                $total_duration += (int)$l['duration'];
            }
        }

        return [
            'sections' => $sections,
            'total_sections' => count($sections),
            'total_lessons' => $total_lessons,
            'total_duration' => $total_duration
        ];
    }

    // ========== NEWSLETTER ==========
    public function subscribe_newsletter($email) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => false, 'message' => 'Invalid email'];
        }
        if ($this->db->get_where('newsletter_subscriber', ['email' => $email])->num_rows() > 0) {
            return ['status' => false, 'message' => 'Already subscribed'];
        }
        $this->db->insert('newsletter_subscriber', ['email' => $email, 'created_at' => time(), 'updated_at' => time()]);
        return ['status' => true, 'message' => 'Subscribed successfully'];
    }

    // ========== NOTIFICATIONS ==========
    public function get_notifications($user_id) {
        $this->db->where('to_user', $user_id)->order_by('created_at', 'DESC')->limit(50);
        $notifications = $this->db->get('notifications')->result_array();
        $unread_count = $this->db->where('to_user', $user_id)->where('status', 0)->count_all_results('notifications');
        return ['notifications' => $notifications, 'unread_count' => $unread_count];
    }

    public function mark_notification_read($user_id, $notification_id) {
        $this->db->where('id', $notification_id)->where('to_user', $user_id)->update('notifications', ['status' => 1]);
        return ['status' => true, 'message' => 'Marked as read'];
    }

    public function mark_all_notifications_read($user_id) {
        $this->db->where('to_user', $user_id)->update('notifications', ['status' => 1]);
        return ['status' => true, 'message' => 'All marked as read'];
    }

    // ========== MESSAGES ==========
    public function get_message_threads($user_id) {
        $this->db->where('sender', $user_id)->or_where('receiver', $user_id);
        $this->db->order_by('last_message_timestamp', 'DESC');
        $threads = $this->db->get('message_thread')->result_array();

        foreach ($threads as $key => $t) {
            $other_id = $t['sender'] == $user_id ? $t['receiver'] : $t['sender'];
            $other = $this->db->get_where('users', ['id' => $other_id])->row_array();
            $threads[$key]['other_user'] = [
                'id' => $other['id'] ?? 0,
                'name' => ($other['first_name'] ?? '') . ' ' . ($other['last_name'] ?? ''),
                'image' => $this->get_user_image($other_id)
            ];
            $this->db->where('message_thread_code', $t['message_thread_code'])->where('receiver', $user_id)->where('read_status', 0);
            $threads[$key]['unread_count'] = $this->db->count_all_results('message');
        }
        return $threads;
    }

    public function get_thread_messages($user_id, $thread_code) {
        // Authorization: only a participant of the thread may read it.
        $is_member = $this->db->where('message_thread_code', $thread_code)
            ->group_start()->where('sender', $user_id)->or_where('receiver', $user_id)->group_end()
            ->count_all_results('message') > 0;
        if (!$is_member) {
            // Also allow an empty-but-owned thread (recorded in message_thread).
            $thread = $this->db->where('message_thread_code', $thread_code)
                ->group_start()->where('sender', $user_id)->or_where('receiver', $user_id)->group_end()
                ->get('message_thread')->row();
            if (!$thread) return ['error' => 'Not authorized'];
        }

        $this->db->where('message_thread_code', $thread_code)->order_by('timestamp', 'ASC');
        $messages = $this->db->get('message')->result_array();
        $this->db->where('message_thread_code', $thread_code)->where('receiver', $user_id)->update('message', ['read_status' => 1]);
        return $messages;
    }

    public function send_message($user_id, $receiver_id, $message) {
        if (empty($message)) return ['status' => false, 'message' => 'Message required'];

        $thread = $this->db->group_start()
            ->where('sender', $user_id)->where('receiver', $receiver_id)
            ->group_end()->or_group_start()
            ->where('sender', $receiver_id)->where('receiver', $user_id)
            ->group_end()->get('message_thread')->row();

        if (!$thread) {
            $thread_code = md5($user_id . $receiver_id . time());
            $this->db->insert('message_thread', [
                'message_thread_code' => $thread_code,
                'sender' => $user_id,
                'receiver' => $receiver_id,
                'last_message_timestamp' => time()
            ]);
        } else {
            $thread_code = $thread->message_thread_code;
            $this->db->where('message_thread_code', $thread_code)->update('message_thread', ['last_message_timestamp' => time()]);
        }

        $this->db->insert('message', [
            'message_thread_code' => $thread_code,
            'message' => $message,
            'sender' => $user_id,
            'receiver' => $receiver_id,
            'timestamp' => time(),
            'read_status' => 0
        ]);
        return ['status' => true, 'message' => 'Message sent'];
    }

    // ========== PAYMENT GATEWAYS ==========
    public function get_payment_gateways() {
        $this->db->where('status', 1)->where('is_addon', 0);
        $gateways = $this->db->get('payment_gateways')->result_array();
        return array_map(function($g) {
            return ['id' => $g['id'], 'identifier' => $g['identifier'], 'title' => $g['title'], 'currency' => $g['currency']];
        }, $gateways);
    }

    // ========== PURCHASE HISTORY ==========
    public function get_purchase_history($user_id) {
        $this->db->select('payment.*, course.title as course_title')->from('payment')
            ->join('course', 'course.id = payment.course_id', 'left')
            ->where('payment.user_id', $user_id)->order_by('payment.date_added', 'DESC');
        return $this->db->get()->result_array();
    }

    // ========== QUIZ ==========
    public function get_quiz($user_id, $lesson_id) {
        $lesson = $this->db->get_where('lesson', ['id' => $lesson_id, 'lesson_type' => 'quiz'])->row_array();
        if (!$lesson) return ['error' => 'Quiz not found'];

        $section = $this->db->get_where('section', ['id' => $lesson['section_id']])->row_array();
        if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $section['course_id']])->num_rows() == 0) {
            return ['error' => 'Not enrolled'];
        }

        // Question/option text is frequently pasted from Word: HTML-encoded tags +
        // mso-* inline styles. Decode entities, strip tags, collapse whitespace so
        // the player shows clean text instead of raw markup.
        $clean = function ($v) {
            $t = strip_tags(html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8'));
            return trim(preg_replace('/\s+/', ' ', $t));
        };
        $questions = $this->db->get_where('question', ['quiz_id' => $lesson_id])->result_array();
        foreach ($questions as $key => $q) {
            $questions[$key]['title']   = $clean($q['title']);
            $opts = json_decode($q['options']) ?: [];
            $questions[$key]['options'] = array_map($clean, (array) $opts);
            unset($questions[$key]['correct_answers']);
        }

        // Quiz settings (total/pass marks) are stored as a JSON blob in `attachment`.
        $settings  = json_decode($lesson['attachment'] ?? '', true);
        $pass_mark = (is_array($settings) && isset($settings['pass_mark'])) ? (int) $settings['pass_mark'] : null;

        return [
            'quiz_id' => $lesson_id,
            'title' => $lesson['title'],
            'questions' => $questions,
            'total_questions' => count($questions),
            'quiz_attempt' => $lesson['quiz_attempt'],
            'pass_mark' => $pass_mark
        ];
    }

    public function submit_quiz($user_id, $lesson_id, $answers) {
        $lesson = $this->db->get_where('lesson', ['id' => $lesson_id, 'lesson_type' => 'quiz'])->row_array();
        if (!$lesson) return ['status' => false, 'message' => 'Quiz not found'];

        $questions = $this->db->get_where('question', ['quiz_id' => $lesson_id])->result_array();
        $correct = 0;
        $answers = is_array($answers) ? $answers : json_decode($answers, true);

        foreach ($questions as $q) {
            $correct_ans = json_decode($q['correct_answers'], true);
            if (!is_array($correct_ans)) $correct_ans = $correct_ans === null ? [] : [$correct_ans];
            $submitted = $answers[$q['id']] ?? null;
            $submitted = is_array($submitted) ? $submitted : ($submitted === null ? [] : [$submitted]);
            // Compare the full answer set (order-independent), strictly.
            $a = array_map('strval', $correct_ans); sort($a);
            $b = array_map('strval', $submitted);   sort($b);
            if (!empty($a) && $a === $b) {
                $correct++;
            }
        }

        $score = count($questions) > 0 ? round(($correct / count($questions)) * 100) : 0;

        $this->db->insert('quiz_results', [
            'quiz_id' => $lesson_id,
            'user_id' => $user_id,
            'user_answers' => json_encode($answers),
            'correct_answers' => json_encode(array_column($questions, 'id')),
            'total_obtained_marks' => $score,
            'date_added' => time(),
            'date_updated' => time(),
            'is_submitted' => 1
        ]);

        return ['status' => true, 'message' => 'Quiz submitted', 'data' => ['score' => $score, 'correct' => $correct, 'total' => count($questions)]];
    }

    public function get_quiz_results($user_id, $lesson_id) {
        $this->db->where('quiz_id', $lesson_id)->where('user_id', $user_id)->order_by('date_added', 'DESC');
        return $this->db->get('quiz_results')->result_array();
    }

    // ========== COURSE PROGRESS DETAILS ==========
    public function get_course_progress_details($user_id, $course_id) {
        $sections = $this->db->get_where('section', ['course_id' => $course_id])->result_array();
        $total_lessons = 0;
        $completed_lessons = 0;

        foreach ($sections as $key => $section) {
            $lessons = $this->db->get_where('lesson', ['section_id' => $section['id']])->result_array();
            foreach ($lessons as $lkey => $lesson) {
                $total_lessons++;
                $watch = $this->db->get_where('watch_histories', ['course_id' => $course_id, 'student_id' => $user_id])->row();
                $completed = $watch ? json_decode($watch->completed_lesson) ?: [] : [];
                if (in_array($lesson['id'], $completed)) {
                    $completed_lessons++;
                    $lessons[$lkey]['is_completed'] = true;
                } else {
                    $lessons[$lkey]['is_completed'] = false;
                }
            }
            $sections[$key]['lessons'] = $lessons;
        }

        $progress = $total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0;

        return [
            'course_id' => $course_id,
            'progress' => $progress,
            'completed_lessons' => $completed_lessons,
            'total_lessons' => $total_lessons,
            'sections' => $sections
        ];
    }

    // ========== INSTRUCTOR APPLICATION ==========
    public function submit_instructor_application($user_id, $data) {
        $existing = $this->db->get_where('applications', ['user_id' => $user_id])->row();
        if ($existing) return ['status' => false, 'message' => 'Application already submitted'];

        $this->db->insert('applications', [
            'user_id' => $user_id,
            'address' => $data['address'] ?? '',
            'phone' => $data['phone'] ?? '',
            'message' => $data['message'] ?? '',
            'status' => 0
        ]);
        return ['status' => true, 'message' => 'Application submitted'];
    }

    public function get_instructor_application_status($user_id) {
        $app = $this->db->get_where('applications', ['user_id' => $user_id])->row_array();
        if (!$app) return ['has_application' => false];
        $status_map = [0 => 'pending', 1 => 'approved', 2 => 'rejected'];
        return ['has_application' => true, 'status' => $status_map[$app['status']] ?? 'pending', 'application' => $app];
    }

    // ========== BLOG COMMENTS ==========
    public function add_blog_comment($user_id, $blog_id, $comment, $parent_id = 0) {
        if (empty($comment)) return ['status' => false, 'message' => 'Comment required'];
        $this->db->insert('blog_comments', [
            'blog_id' => $blog_id,
            'user_id' => $user_id,
            'parent_id' => $parent_id ?: 0,
            'comment' => $comment,
            'likes' => '[]',
            'added_date' => time(),
            'updated_date' => time()
        ]);
        return ['status' => true, 'message' => 'Comment added'];
    }

    // ========== POPULAR/RECENT BLOGS ==========
    public function get_popular_blogs($limit) {
        $this->db->where('status', 'active')->where('is_popular', 1)->limit($limit);
        $blogs = $this->db->get('blogs')->result_array();
        return $this->format_blog_list($blogs);
    }

    public function get_recent_blogs($limit) {
        $this->db->where('status', 'active')->order_by('added_date', 'DESC')->limit($limit);
        $blogs = $this->db->get('blogs')->result_array();
        return $this->format_blog_list($blogs);
    }

    private function format_blog_list($blogs) {
        foreach ($blogs as $key => $b) {
            $blogs[$key]['thumbnail'] = $this->optimized_path('/uploads/blog/thumbnail/' . $b['thumbnail']);
            $cat = $this->db->get_where('blog_category', ['blog_category_id' => $b['blog_category_id']])->row_array();
            $blogs[$key]['category'] = $cat ? $cat['title'] : '';
            $blogs[$key]['read_time'] = $this->calc_read_time($b['description'] ?? '');
        }
        return $blogs;
    }

    // Estimate reading time from word count (~200 words/minute)
    private function calc_read_time($html) {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $html, ENT_QUOTES, 'UTF-8'))));
        $words = $text === '' ? 0 : str_word_count($text);
        $minutes = max(1, (int) round($words / 200));
        return $minutes . ' min read';
    }

    // ========== CUSTOM PAGES ==========
    public function get_custom_pages() {
        $this->db->where('status', 1);
        $pages = $this->db->get('custom_page')->result_array();
        return array_map(function($p) {
            return ['id' => $p['custom_page_id'], 'title' => $p['page_title'], 'url' => $p['page_url'], 'button_title' => $p['button_title'], 'button_position' => $p['button_position']];
        }, $pages);
    }

    // ========== CURRENCIES ==========
    public function get_currencies() {
        return $this->db->get('currency')->result_array();
    }

    // ========== BADGES ==========
    public function get_badges() {
        $badges = $this->db->get('badges')->result_array();
        foreach ($badges as $key => $b) {
            $badges[$key]['image'] = $b['image'] ? '/uploads/badges/' . $b['image'] : '';
        }
        return $badges;
    }

    public function get_user_badges($user_id) {
        $completed = $this->db->where('student_id', $user_id)->where('course_progress', 100)->count_all_results('watch_histories');
        $badges = $this->db->get('badges')->result_array();
        $earned = [];
        foreach ($badges as $b) {
            if ($completed >= $b['condition_from'] && $completed <= $b['condition_to']) {
                $b['image'] = $b['image'] ? '/uploads/badges/' . $b['image'] : '';
                $earned[] = $b;
            }
        }
        return ['earned_badges' => $earned, 'completed_courses' => $completed];
    }

    // ========== LESSON COMMENTS ==========
    public function get_lesson_comments($lesson_id) {
        $this->db->select('comment.*, users.first_name, users.last_name, users.image')
            ->from('comment')
            ->join('users', 'users.id = comment.user_id', 'left')
            ->where('comment.commentable_id', $lesson_id)
            ->where('comment.commentable_type', 'lesson')
            ->order_by('comment.date_added', 'DESC');
        $comments = $this->db->get()->result_array();

        foreach ($comments as $key => $c) {
            $comments[$key]['user'] = [
                'name' => ($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''),
                'image' => $this->get_user_image_by_filename($c['image'])
            ];
        }
        return $comments;
    }

    public function add_lesson_comment($user_id, $lesson_id, $comment) {
        if (empty($comment)) return ['status' => false, 'message' => 'Comment required'];
        $this->db->insert('comment', [
            'body' => $comment,
            'user_id' => $user_id,
            'commentable_id' => $lesson_id,
            'commentable_type' => 'lesson',
            'date_added' => time(),
            'last_modified' => time()
        ]);
        return ['status' => true, 'message' => 'Comment added'];
    }

    // ========== WATCH PROGRESS ==========
    public function update_watch_progress($user_id, $lesson_id, $duration) {
        $lesson = $this->db->get_where('lesson', ['id' => $lesson_id])->row_array();
        $section = $this->db->get_where('section', ['id' => $lesson['section_id']])->row_array();

        $existing = $this->db->get_where('watched_duration', ['watched_student_id' => $user_id, 'watched_lesson_id' => $lesson_id])->row();
        if ($existing) {
            $this->db->where('watched_id', $existing->watched_id)->update('watched_duration', ['current_duration' => $duration]);
        } else {
            $this->db->insert('watched_duration', [
                'watched_student_id' => $user_id,
                'watched_course_id' => $section['course_id'],
                'watched_lesson_id' => $lesson_id,
                'current_duration' => $duration
            ]);
        }
        return ['status' => true, 'message' => 'Progress updated'];
    }

    // ========== FILTER OPTIONS ==========
    public function get_filter_options() {
        $categories = $this->get_categories();
        $languages = $this->get_languages();
        $levels = ['beginner', 'intermediate', 'advanced'];
        $prices = ['all', 'free', 'paid'];
        $sorts = ['newest', 'popular', 'price_low', 'price_high'];

        return [
            'categories' => $categories,
            'languages' => $languages,
            'levels' => $levels,
            'prices' => $prices,
            'sorts' => $sorts
        ];
    }

    // ========== HELPERS ==========
    private function format_courses($courses) {
        $result = [];
        foreach ($courses as $c) $result[] = $this->format_single_course($c);
        return $result;
    }

    private function format_single_course($course) {
        $ratings = $this->get_course_ratings($course['id']);

        // Synthetic display rating for ACTIVE courses with no real reviews yet
        // (mirrors Api_courses::enrich_course so ratings are consistent site-wide).
        if (($course['status'] ?? '') === 'active' && (int)($ratings['count'] ?? 0) === 0) {
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
            $ratings['average'] = $rating_map[$course['title']] ?? 4.0;
            $ratings['count']   = $count_map[$course['title']] ?? 11;
        }
        $instructor = $this->db->get_where('users', ['id' => $course['user_id']])->row_array();
        $enrolled = $this->db->where('course_id', $course['id'])->count_all_results('enrol');

        // Prefer the specific sub-category name for the badge; fall back to parent category
        $cat = null;
        $cat_lookup_id = !empty($course['sub_category_id']) ? $course['sub_category_id'] : ($course['category_id'] ?? null);
        if (!empty($cat_lookup_id)) {
            $cat = $this->db->select('name')->get_where('category', ['id' => $cat_lookup_id])->row_array();
        }

        // Total duration computed from lesson durations (HH:MM:SS); empty string when no lessons
        $dur_row = $this->db->query("SELECT COALESCE(SUM(TIME_TO_SEC(duration)),0) AS secs, COUNT(*) AS cnt FROM lesson WHERE course_id = " . (int)$course['id'])->row();
        $dur_secs = (int)($dur_row->secs ?? 0);
        $lesson_count = (int)($dur_row->cnt ?? 0);
        $duration_text = '';
        if ($dur_secs > 0) {
            $h = floor($dur_secs / 3600);
            $m = floor(($dur_secs % 3600) / 60);
            $duration_text = $h > 0 ? trim($h . 'h ' . ($m > 0 ? $m . 'm' : '')) : ($m . 'm');
        }

        $price = (float)($course['price'] ?? 0);
        $disc = ($course['discount_flag'] == 1 && (float)$course['discounted_price'] > 0) ? (float)$course['discounted_price'] : null;

        return [
            'id'                   => (int)$course['id'],
            'title'                => $course['title'],
            'short_description'    => html_entity_decode(strip_tags($course['short_description'] ?? ''), ENT_QUOTES, 'UTF-8'),
            // H-03: sanitize HTML content to prevent stored XSS
            'description'          => $this->sanitize_html($course['description'] ?? ''),
            'thumbnail' => $this->get_course_thumbnail($course['id']),
            'level' => $course['level'],
            'language' => $course['language'],
            'is_free' => $course['is_free_course'] == 1,
            'price' => $course['is_free_course'] == 1 ? 0 : $price,
            'discounted_price' => $disc,
            'discount_flag' => $course['discount_flag'] == 1,
            'is_top' => $course['is_top_course'] == 1,
            'video_url' => $course['video_url'] ?? null,
            'course_overview_provider' => $course['course_overview_provider'] ?? null,
            'rating' => $ratings['average'],
            'avg_rating' => $ratings['average'],
            'total_ratings' => $ratings['count'],
            'total_students' => $enrolled,
            'enrolled_count' => $enrolled,
            'category' => $cat ? ['name' => $cat['name']] : null,
            'category_id' => $course['category_id'] ?? null,
            'sub_category_id' => $course['sub_category_id'] ?? null,
            'duration' => $duration_text !== '' ? $duration_text : null,
            'duration_seconds' => $dur_secs,
            'total_lessons' => $lesson_count,
            'instructor_name' => $instructor ? trim($instructor['first_name'] . ' ' . $instructor['last_name']) : '',
            'instructor_image' => $instructor ? $this->get_user_image($instructor['id']) : null,
            'instructor' => $instructor ? [
                'id' => $instructor['id'],
                'name' => trim($instructor['first_name'] . ' ' . $instructor['last_name']),
                'first_name' => $instructor['first_name'],
                'last_name' => $instructor['last_name'],
                'image' => $this->get_user_image($instructor['id'])
            ] : null,
            'status' => $course['status'],
            'date_added' => $course['date_added'] ?? null
        ];
    }

    private function clean_blog_content($raw) {
        // Decode HTML entities (may be double or triple encoded)
        $html = $raw;
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
            if ($decoded === $html) break;
            $html = $decoded;
        }

        // If the content is a full HTML document, extract just the body
        if (preg_match('/<body[^>]*>(.*)<\/body>/si', $html, $m)) {
            $html = $m[1];
        }
        // Remove <head>...</head>, <meta>, <title>, <link>, <!DOCTYPE>, <html>, <body> tags
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $html = preg_replace('/<head[^>]*>.*?<\/head>/si', '', $html);
        $html = preg_replace('/<\/?html[^>]*>/i', '', $html);
        $html = preg_replace('/<\/?body[^>]*>/i', '', $html);
        $html = preg_replace('/<meta[^>]*>/i', '', $html);
        $html = preg_replace('/<title[^>]*>.*?<\/title>/si', '', $html);
        $html = preg_replace('/<link[^>]*>/i', '', $html);
        // Remove JSON-LD schema scripts but keep other scripts
        $html = preg_replace('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>/si', '', $html);

        return trim($html);
    }

    private function clean_excerpt($html, $length = 150) {
        // Double-decode HTML entities
        $text = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Remove style/script blocks and their content
        $text = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $text);
        $text = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $text);
        // Remove all HTML tags
        $text = strip_tags($text);
        // Remove CSS-like content (body { ... })
        $text = preg_replace('/[a-z-]+\s*\{[^}]*\}/si', '', $text);
        // Remove meta/title-like text remnants
        $text = preg_replace('/\b(body|html|head|meta|title|charset|viewport|font-family|margin|padding)\b[^.;]*[;:][^.]*?(?=\b[A-Z]|$)/si', '', $text);
        // Normalize whitespace
        $text = trim(preg_replace('/\s+/', ' ', $text));
        // Skip if too short after cleaning (probably all CSS)
        if (mb_strlen($text) < 20) return '';
        return mb_substr($text, 0, $length) . '...';
    }

    private function get_course_ratings($course_id) {
        $this->db->select('AVG(rating) as avg, COUNT(id) as cnt')->from('rating');
        $this->db->where('ratable_id', $course_id)->where('ratable_type', 'course');
        $result = $this->db->get()->row();
        return ['average' => round($result->avg ?? 0, 1), 'count' => (int)$result->cnt];
    }

    private function get_course_progress($user_id, $course_id) {
        // Read the canonical progress recorded in watch_histories.
        $wh = $this->db->get_where('watch_histories', ['course_id' => $course_id, 'student_id' => $user_id])->row_array();
        if ($wh && isset($wh['course_progress'])) {
            return (int) $wh['course_progress'];
        }
        // Derive from completed_lesson if course_progress wasn't stored.
        $total = (int) $this->db->where('course_id', $course_id)->count_all_results('lesson');
        if ($total == 0 || !$wh) return 0;
        $completed = count(json_decode($wh['completed_lesson'], true) ?: []);
        return (int) round(($completed / $total) * 100);
    }

    private function get_course_thumbnail($course_id) {
        $course = $this->db->select('last_modified')->get_where('course', ['id' => $course_id])->row_array();
        if (!$course) return null;

        $theme = get_frontend_settings('theme') ?: 'default-new';
        $last_modified = $course['last_modified'] ?? '';
        $base_name = 'course_thumbnail_' . $theme . '_' . $course_id . $last_modified;
        $opt_dir = 'uploads/thumbnails/course_thumbnails/optimized/';
        $orig_dir = 'uploads/thumbnails/course_thumbnails/';

        if (file_exists(FCPATH . $opt_dir . $base_name . '.webp')) {
            return '/' . $opt_dir . $base_name . '.webp';
        }
        if (file_exists(FCPATH . $opt_dir . $base_name . '.jpg')) {
            return '/' . $opt_dir . $base_name . '.jpg';
        }
        if (file_exists(FCPATH . $orig_dir . $base_name . '.jpg')) {
            return '/' . $orig_dir . $base_name . '.jpg';
        }
        // Return full URL as fallback
        return base_url() . $opt_dir . $base_name . '.jpg';
    }

    private function get_category_thumbnail($filename) {
        if ($filename) return '/uploads/category_thumbnails/' . $filename;
        return '/uploads/category_thumbnails/default.png';
    }

    /**
     * Given a source path like "/uploads/user_image/abc.jpg", return the
     * optimized WebP if it exists on disk (e.g. "/uploads/user_image/optimized/abc.webp"),
     * otherwise fall back to the original path. Uses FCPATH for reliable file_exists.
     */
    private function optimized_path($original_path) {
        if (!$original_path) return $original_path;
        $dir = dirname($original_path);           // /uploads/user_image
        $base = pathinfo($original_path, PATHINFO_FILENAME); // abc
        $opt_webp = $dir . '/optimized/' . $base . '.webp';
        $opt_jpg  = $dir . '/optimized/' . $base . '.jpg';
        if (file_exists(FCPATH . ltrim($opt_webp, '/'))) return $opt_webp;
        if (file_exists(FCPATH . ltrim($opt_jpg, '/')))  return $opt_jpg;
        return $original_path;
    }

    private function get_user_image($user_id) {
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        if ($user && $user['image']) {
            return $this->optimized_path('/uploads/user_image/' . $user['image'] . '.jpg');
        }
        return '/uploads/user_image/placeholder.png';
    }

    private function get_user_image_by_filename($filename) {
        if ($filename) return $this->optimized_path('/uploads/user_image/' . $filename . '.jpg');
        return '/uploads/user_image/placeholder.png';
    }

    private function get_instructor_name($user_id) {
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        return $user ? $user['first_name'] . ' ' . $user['last_name'] : '';
    }

    // ========== OTP SYSTEM ==========
    public function send_otp($email, $type = 'verification') {
        $user = $this->db->get_where('users', ['email' => $email])->row_array();
        if (!$user && $type != 'verification') {
            return ['status' => false, 'message' => 'Email not found'];
        }

        // C-03 fix: cryptographically secure OTP
        $otp = random_int(100000, 999999);
        $expiry = time() + 600; // 10 minutes

        // Reset attempt counter when new OTP is issued
        $this->db->where('email', $email)->delete('otp_verifications');
        $this->db->insert('otp_verifications', [
            'email'      => $email,
            'otp'        => $otp,
            'type'       => $type,
            'expiry'     => $expiry,
            'attempts'   => 0,
            'created_at' => time()
        ]);

        $sent = $this->send_otp_email($email, $otp, $type);
        if (!$sent) {
            // Don't claim success when the mail never left the server
            $this->db->where('email', $email)->where('type', $type)->delete('otp_verifications');
            return ['status' => false, 'message' => 'Could not send the verification email. Please check the email/SMTP settings and try again.'];
        }
        return ['status' => true, 'message' => 'OTP sent to your email', 'data' => ['expires_in' => 600]];
    }

    public function verify_otp($email, $otp, $type = 'verification') {
        $record = $this->db->get_where('otp_verifications', ['email' => $email, 'type' => $type])->row();
        if (!$record) {
            return ['status' => false, 'message' => 'Invalid OTP'];
        }
        if ($record->expiry < time()) {
            $this->db->where('id', $record->id)->delete('otp_verifications');
            return ['status' => false, 'message' => 'OTP expired'];
        }

        // C-03 fix: brute-force protection — max 5 attempts per OTP
        $max_attempts = 5;
        $current_attempts = (int)($record->attempts ?? 0);
        if ($current_attempts >= $max_attempts) {
            $this->db->where('id', $record->id)->delete('otp_verifications');
            return ['status' => false, 'message' => 'Too many failed attempts. Please request a new OTP.'];
        }

        // Increment attempt counter before checking (fail-safe)
        $this->db->where('id', $record->id)->update('otp_verifications', ['attempts' => $current_attempts + 1]);

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals((string)$record->otp, (string)$otp)) {
            $remaining = $max_attempts - ($current_attempts + 1);
            return ['status' => false, 'message' => $remaining > 0 ? "Invalid OTP. {$remaining} attempt(s) remaining." : 'Too many failed attempts. Please request a new OTP.'];
        }

        // OTP matched — delete immediately (single use)
        $this->db->where('id', $record->id)->delete('otp_verifications');

        if ($type == 'verification') {
            $this->db->where('email', $email)->update('users', ['status' => 1]);
        }

        return ['status' => true, 'message' => 'OTP verified successfully'];
    }

    public function send_login_otp($email) {
        $user = $this->db->get_where('users', ['email' => $email, 'status' => 1])->row_array();
        if (!$user) {
            return ['status' => false, 'message' => 'Email not found or account not active'];
        }
        return $this->send_otp($email, 'login');
    }

    public function verify_login_otp($email, $otp) {
        $result = $this->verify_otp($email, $otp, 'login');
        if (!$result['status']) return $result;

        $user = $this->db->get_where('users', ['email' => $email, 'status' => 1])->row_array();
        if (!$user) {
            return ['status' => false, 'message' => 'User not found'];
        }

        return ['status' => true, 'message' => 'Login successful', 'data' => $this->format_user($user)];
    }

    public function send_verification_otp($email) {
        return $this->send_otp($email, 'verification');
    }

    public function reset_password_with_otp($email, $otp, $new_password) {
        $result = $this->verify_otp($email, $otp, 'reset_password');
        if (!$result['status']) return $result;

        // H-06 fix: bcrypt instead of sha1
        $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->where('email', $email)->update('users', ['password' => $hashed]);
        return ['status' => true, 'message' => 'Password reset successfully'];
    }

    private function send_otp_email($email, $otp, $type) {
        $CI =& get_instance();
        $CI->load->library('email');
        $CI->email->clear();

        $subjects = [
            'verification'   => 'Email Verification Code',
            'login'          => 'Login Verification Code',
            'reset_password' => 'Password Reset Code'
        ];

        // Use the same SMTP settings the rest of the platform uses (see Email_model).
        // Without this the CI email library falls back to PHP mail(), which is disabled
        // on most hosts — so the OTP "sends" successfully but never arrives.
        $config = [
            'protocol'     => get_settings('protocol') ?: 'mail',
            'smtp_host'    => get_settings('smtp_host'),
            'smtp_port'    => get_settings('smtp_port'),
            'smtp_user'    => get_settings('smtp_user'),
            'smtp_pass'    => get_settings('smtp_pass'),
            'smtp_crypto'  => get_settings('smtp_crypto'),
            'smtp_timeout' => '30',
            'mailtype'     => 'html',
            'newline'      => "\r\n",
            'charset'      => 'utf-8',
        ];
        $CI->email->initialize($config);
        $CI->email->set_newline("\r\n");

        $from = get_settings('smtp_from_email') ?: get_settings('system_email');
        $CI->email->from($from, get_settings('system_name'));
        $CI->email->to($email);
        $CI->email->subject($subjects[$type] ?? 'Verification Code');
        $CI->email->message("Your verification code is: <strong>$otp</strong><br>This code expires in 10 minutes.");

        $sent = $CI->email->send();
        if (!$sent) {
            // Surface the real reason in the application log so OTP-delivery issues are debuggable
            log_message('error', 'OTP email failed to ' . $email . ': ' . $CI->email->print_debugger(['headers']));
        }
        return $sent;
    }

    // ========== SOCIAL LOGIN ==========
    public function social_login($provider, $social_id, $email, $first_name, $last_name, $image = null) {
        $user = $this->db->get_where('users', ['email' => $email])->row_array();

        if ($user) {
            return ['status' => true, 'message' => 'Login successful', 'data' => $this->format_user($user)];
        }

        $data = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
            // H-06 fix: bcrypt + truly random placeholder (sha1(uniqid()) is predictable)
            'password'   => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]),
            'role_id'    => 2,
            'status'     => 1,
            'date_added' => time()
        ];

        $this->db->insert('users', $data);
        $user_id = $this->db->insert_id();
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();

        return ['status' => true, 'message' => 'Registration successful', 'data' => $this->format_user($user)];
    }

    // ========== CHECKOUT & PAYMENT ==========
    public function get_checkout_data($user_id) {
        $cart = $this->get_cart($user_id);
        $gateways = $this->get_payment_gateways();

        return [
            'cart' => $cart,
            'payment_gateways' => $gateways,
            'tax_percentage' => get_settings('course_selling_tax') ?: 0,
            'currency' => get_settings('system_currency') ?: 'USD',
            'currency_symbol' => get_settings('currency_symbol') ?: '$'
        ];
    }

    public function process_checkout($user_id, $data) {
        $cart = $this->get_cart($user_id);
        if (empty($cart['items'])) {
            return ['status' => false, 'message' => 'Cart is empty'];
        }

        $coupon_discount = 0;
        if (!empty($data['coupon_code'])) {
            $coupon = $this->apply_coupon($data['coupon_code']);
            if ($coupon['status']) {
                $coupon_discount = $coupon['data']['discount'];
            }
        }

        $subtotal = $cart['total'];
        $tax = ($subtotal * (get_settings('course_selling_tax') ?: 0)) / 100;
        $discount = min(($subtotal * $coupon_discount) / 100, $subtotal); // never exceed subtotal
        $total = max(0, $subtotal + $tax - $discount);                    // never negative

        return [
            'status' => true,
            'data' => [
                'items' => $cart['items'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => round($total, 2),
                'currency' => $cart['currency']
            ]
        ];
    }

    public function initiate_payment($user_id, $gateway, $course_ids, $coupon_code = null) {
        if (empty($gateway) || empty($course_ids)) {
            return ['status' => false, 'message' => 'Gateway and course IDs required'];
        }

        $course_ids = is_array($course_ids) ? $course_ids : json_decode($course_ids, true);
        $total = 0;
        $courses = [];

        foreach ($course_ids as $id) {
            $course = $this->db->get_where('course', ['id' => $id, 'status' => 'active'])->row_array();
            if ($course) {
                $price = $course['discount_flag'] == 1 ? $course['discounted_price'] : $course['price'];
                if ($course['is_free_course'] != 1) {
                    $total += $price;
                }
                $courses[] = $course;
            }
        }

        $discount = 0;
        if ($coupon_code) {
            $coupon = $this->apply_coupon($coupon_code);
            if ($coupon['status']) {
                $discount = min(($total * $coupon['data']['discount']) / 100, $total); // cap at subtotal
            }
        }

        $tax = ($total * (get_settings('course_selling_tax') ?: 0)) / 100;
        $final_amount = max(0, $total + $tax - $discount); // never negative

        $session_id = uniqid('pay_');
        $this->load->library('session');
        $this->session->set_userdata('payment_session_' . $session_id, [
            'user_id' => $user_id,
            'course_ids' => $course_ids,
            'amount' => $final_amount,
            'gateway' => $gateway,
            'coupon' => $coupon_code
        ]);

        return [
            'status' => true,
            'message' => 'Payment initiated',
            'data' => [
                'session_id' => $session_id,
                'amount' => round($final_amount, 2),
                'currency' => get_settings('system_currency') ?: 'USD',
                'gateway' => $gateway,
                'courses' => count($courses)
            ]
        ];
    }

    public function verify_payment($user_id, $data) {
        $session_id = $data['session_id'] ?? '';
        $transaction_id = $data['transaction_id'] ?? '';

        if (empty($session_id) || empty($transaction_id)) {
            return ['status' => false, 'message' => 'Session ID and transaction ID required'];
        }

        $this->load->library('session');
        $payment_data = $this->session->userdata('payment_session_' . $session_id);

        if (!$payment_data || $payment_data['user_id'] != $user_id) {
            return ['status' => false, 'message' => 'Invalid payment session'];
        }

        // Never enroll without confirming a real captured charge with the gateway.
        if (!$this->gateway_charge_ok($payment_data['gateway'], $transaction_id, (float) $payment_data['amount'])) {
            return ['status' => false, 'message' => 'Payment verification failed'];
        }

        foreach ($payment_data['course_ids'] as $course_id) {
            if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $course_id])->num_rows() == 0) {
                $course = $this->db->get_where('course', ['id' => $course_id])->row_array();
                $enrol_data = ['user_id' => $user_id, 'course_id' => $course_id, 'date_added' => time()];
                if ($course['expiry_period'] > 0) {
                    $enrol_data['expiry_date'] = strtotime("+{$course['expiry_period']} months");
                }
                $this->db->insert('enrol', $enrol_data);

                $this->db->insert('payment', [
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'payment_type' => $payment_data['gateway'],
                    'amount' => $payment_data['amount'] / count($payment_data['course_ids']),
                    'date_added' => time(),
                    'transaction_id' => $transaction_id,
                    'session_id' => $session_id,
                    'coupon' => $payment_data['coupon']
                ]);
            }
        }

        $this->session->unset_userdata('payment_session_' . $session_id);
        $this->session->unset_userdata('cart_items');

        return ['status' => true, 'message' => 'Payment successful, courses enrolled'];
    }

    /**
     * Confirm a captured charge of at least $amount with the named gateway.
     * Fails closed (returns false) on any error, missing config, or mismatch.
     */
    private function gateway_charge_ok($gateway, $transaction_id, $amount) {
        if (empty($transaction_id)) return false;
        try {
            if ($gateway === 'stripe') {
                $stripe = json_decode(get_settings('stripe_keys'), true);
                if (!$stripe || !isset($stripe[0])) return false;
                $secret = (($stripe[0]['testmode'] ?? '') == 'on') ? $stripe[0]['secret_key'] : $stripe[0]['secret_live_key'];
                $is_session = (strpos($transaction_id, 'cs_') === 0);
                $url = $is_session
                    ? 'https://api.stripe.com/v1/checkout/sessions/' . urlencode($transaction_id)
                    : 'https://api.stripe.com/v1/payment_intents/' . urlencode($transaction_id);
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $secret . ':']);
                $res = json_decode(curl_exec($ch), true);
                curl_close($ch);
                if (!$res) return false;
                $paid = $is_session ? (($res['payment_status'] ?? '') === 'paid') : (($res['status'] ?? '') === 'succeeded');
                $minor = (int) ($is_session ? ($res['amount_total'] ?? -1) : ($res['amount_received'] ?? -1));
                return $paid && $minor >= (int) round($amount * 100);
            }
            if ($gateway === 'paypal') {
                $paypal = json_decode(get_settings('paypal'), true);
                if (!$paypal || !isset($paypal[0])) return false;
                $sandbox = ($paypal[0]['mode'] ?? 'sandbox') == 'sandbox';
                $cid = $sandbox ? $paypal[0]['sandbox_client_id'] : $paypal[0]['production_client_id'];
                $sec = $sandbox ? $paypal[0]['sandbox_secret_key'] : $paypal[0]['production_secret_key'];
                $base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
                $t = curl_init($base . '/v1/oauth2/token');
                curl_setopt_array($t, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                    CURLOPT_USERPWD => $cid . ':' . $sec, CURLOPT_POSTFIELDS => 'grant_type=client_credentials']);
                $tr = json_decode(curl_exec($t), true); curl_close($t);
                if (empty($tr['access_token'])) return false;
                $o = curl_init($base . '/v2/checkout/orders/' . urlencode($transaction_id));
                curl_setopt_array($o, [CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tr['access_token']]]);
                $order = json_decode(curl_exec($o), true); curl_close($o);
                if (!$order || ($order['status'] ?? '') !== 'COMPLETED') return false;
                return (float) ($order['purchase_units'][0]['amount']['value'] ?? 0) + 0.01 >= $amount;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public function handle_payment_callback($gateway, $data) {
        return ['status' => true, 'message' => 'Callback received', 'gateway' => $gateway];
    }

    public function get_invoice($user_id, $payment_id) {
        $payment = $this->db->get_where('payment', ['id' => $payment_id, 'user_id' => $user_id])->row_array();
        if (!$payment) return null;

        $course = $this->db->get_where('course', ['id' => $payment['course_id']])->row_array();
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();

        return [
            'invoice_id' => 'INV-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT),
            'date' => date('Y-m-d', $payment['date_added']),
            'user' => ['name' => $user['first_name'] . ' ' . $user['last_name'], 'email' => $user['email']],
            'course' => ['title' => $course['title'], 'price' => $payment['amount']],
            'payment_method' => $payment['payment_type'],
            'transaction_id' => $payment['transaction_id'],
            'amount' => $payment['amount'],
            'tax' => $payment['tax'] ?? 0,
            'total' => $payment['amount'] + ($payment['tax'] ?? 0),
            'currency' => get_settings('system_currency') ?: 'USD',
            'company' => [
                'name' => get_settings('system_name'),
                'email' => get_settings('system_email'),
                'address' => get_settings('address')
            ]
        ];
    }

    // ========== COURSE PAGE DATA ==========
    public function get_courses_page_data($params) {
        $courses = $this->get_courses($params);
        $categories = $this->get_categories();
        $languages = $this->get_languages();

        return [
            'courses' => $courses['courses'],
            'pagination' => $courses['pagination'],
            'filters' => [
                'categories' => $categories,
                'languages' => $languages,
                'levels' => ['beginner', 'intermediate', 'advanced'],
                'prices' => ['all', 'free', 'paid'],
                'sorts' => [
                    ['value' => 'newest', 'label' => 'Newest'],
                    ['value' => 'popular', 'label' => 'Most Popular'],
                    ['value' => 'price_low', 'label' => 'Price: Low to High'],
                    ['value' => 'price_high', 'label' => 'Price: High to Low']
                ]
            ],
            'total_courses' => $courses['pagination']['total']
        ];
    }

    public function get_course_by_slug($slug) {
        $this->db->where('status', 'active');
        $this->db->where("REPLACE(LOWER(title), ' ', '-') = ", strtolower($slug));
        $course = $this->db->get('course')->row_array();
        if (!$course) return null;
        return $this->get_course_details($course['id']);
    }

    public function get_featured_courses($limit) {
        return $this->get_top_courses($limit);
    }

    public function get_related_courses($course_id, $limit) {
        $course = $this->db->get_where('course', ['id' => $course_id])->row_array();
        if (!$course) return [];

        $this->db->where('category_id', $course['category_id'])
            ->where('id !=', $course_id)
            ->where('status', 'active')
            ->limit($limit);
        return $this->format_courses($this->db->get('course')->result_array());
    }

    // ========== LESSON VIDEO/CONTENT ==========
    public function get_lesson_video($user_id, $lesson_id) {
        $lesson = $this->db->get_where('lesson', ['id' => $lesson_id])->row_array();
        if (!$lesson) return ['error' => 'Lesson not found'];

        $section = $this->db->get_where('section', ['id' => $lesson['section_id']])->row_array();
        
        if ($lesson['is_free'] != 1) {
            if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $section['course_id']])->num_rows() == 0) {
                return ['error' => 'Not enrolled in this course'];
            }
        }

        return [
            'id' => $lesson['id'],
            'title' => $lesson['title'],
            'video_type' => $lesson['video_type'],
            'video_url' => $lesson['video_url'],
            'duration' => $lesson['duration'],
            'lesson_type' => $lesson['lesson_type'],
            'summary' => $lesson['summary'],
            'is_free' => $lesson['is_free'] == 1
        ];
    }

    public function get_lesson_attachments($user_id, $lesson_id) {
        $lesson = $this->db->get_where('lesson', ['id' => $lesson_id])->row_array();
        if (!$lesson) return ['error' => 'Lesson not found'];

        $section = $this->db->get_where('section', ['id' => $lesson['section_id']])->row_array();
        if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $section['course_id']])->num_rows() == 0) {
            return ['error' => 'Not enrolled'];
        }

        $attachments = json_decode($lesson['attachment']) ?: [];
        $resources = $this->db->get_where('resource_files', ['lesson_id' => $lesson_id])->result_array();

        return [
            'attachments' => $attachments,
            'resources' => array_map(function($r) {
                return [
                    'id' => $r['id'],
                    'title' => $r['title'],
                    'file_url' => '/uploads/resources/' . $r['file_name']
                ];
            }, $resources)
        ];
    }

    // ========== COURSE LEARN PAGE ==========
    public function get_course_learn_data($user_id, $course_id) {
        if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $course_id])->num_rows() == 0) {
            return ['error' => 'Not enrolled in this course'];
        }

        $course = $this->db->get_where('course', ['id' => $course_id])->row_array();
        $sections = $this->get_course_sections($user_id, $course_id);
        $progress = $this->get_course_progress_details($user_id, $course_id);

        $watch_history = $this->db->get_where('watch_histories', ['course_id' => $course_id, 'student_id' => $user_id])->row();
        $current_lesson_id = $watch_history ? $watch_history->watching_lesson_id : null;

        if (!$current_lesson_id && !empty($sections) && !empty($sections[0]['lessons'])) {
            $current_lesson_id = $sections[0]['lessons'][0]['id'];
        }

        return [
            'course' => [
                'id' => $course['id'],
                'title' => $course['title'],
                'thumbnail' => $this->get_course_thumbnail($course['id'])
            ],
            'sections' => $sections,
            'progress' => $progress,
            'current_lesson_id' => $current_lesson_id,
            'instructor' => $this->get_instructor_details($course['user_id'])
        ];
    }

    // ========== INSTRUCTOR PAGE ==========
    public function get_instructor_page_data($id) {
        $instructor = $this->get_instructor_details($id);
        if (!$instructor) return null;

        $this->db->where('user_id', $id)->where('status', 'active');
        $courses = $this->format_courses($this->db->get('course')->result_array());

        return [
            'instructor' => $instructor,
            'courses' => $courses,
            'total_courses' => count($courses)
        ];
    }

    // ========== CATEGORY PAGE ==========
    public function get_category_page_data($id, $params) {
        $category = $this->get_category($id);
        if (!$category) return null;

        $params['category_id'] = $id;
        $courses = $this->get_courses($params);

        return [
            'category' => $category,
            'courses' => $courses['courses'],
            'pagination' => $courses['pagination'],
            'sub_categories' => $this->get_sub_categories($id)
        ];
    }

    // ========== BLOG PAGE ==========
    public function get_blog_page_data($params) {
        $blogs = $this->get_blogs($params);
        $categories = $this->get_blog_categories();
        $popular = $this->get_popular_blogs(5);
        $recent = $this->get_recent_blogs(5);

        return [
            'blogs' => $blogs['blogs'],
            'pagination' => $blogs['pagination'],
            'categories' => $categories,
            'popular_blogs' => $popular,
            'recent_blogs' => $recent,
            'page_title' => get_frontend_settings('blog_page_title'),
            'page_subtitle' => get_frontend_settings('blog_page_subtitle'),
            'page_banner' => '/uploads/system/' . get_frontend_settings('blog_page_banner')
        ];
    }

    // ========== USER DASHBOARD ==========
    public function get_user_dashboard($user_id) {
        $user = $this->get_user_profile($user_id);
        $enrolled_courses = $this->get_user_courses($user_id);
        $wishlist = $this->get_wishlist($user_id);
        $notifications = $this->get_notifications($user_id);
        $purchase_history = $this->get_purchase_history($user_id);

        $completed_courses = 0;
        $in_progress_courses = 0;
        foreach ($enrolled_courses as $c) {
            if ($c['progress'] >= 100) $completed_courses++;
            else $in_progress_courses++;
        }

        return [
            'user' => $user,
            'stats' => [
                'enrolled_courses' => count($enrolled_courses),
                'completed_courses' => $completed_courses,
                'in_progress_courses' => $in_progress_courses,
                'wishlist_count' => count($wishlist)
            ],
            'recent_courses' => array_slice($enrolled_courses, 0, 4),
            'notifications' => $notifications,
            'recent_purchases' => array_slice($purchase_history, 0, 5)
        ];
    }

    // ========== COMPARE COURSES ==========
    public function compare_courses($course_ids) {
        $course_ids = is_array($course_ids) ? $course_ids : json_decode($course_ids, true);
        $courses = [];

        foreach ($course_ids as $id) {
            $course = $this->get_course_details($id);
            if ($course) {
                $courses[] = [
                    'id' => $course['id'],
                    'title' => $course['title'],
                    'thumbnail' => $course['thumbnail'],
                    'price' => $course['price'],
                    'discounted_price' => $course['discounted_price'],
                    'is_free' => $course['is_free'],
                    'level' => $course['level'],
                    'language' => $course['language'],
                    'avg_rating' => $course['avg_rating'],
                    'total_ratings' => $course['total_ratings'],
                    'instructor_name' => $course['instructor']['name'] ?? '',
                    'total_lessons' => count($course['sections'] ?? []),
                    'requirements' => $course['requirements'],
                    'outcomes' => $course['outcomes']
                ];
            }
        }

        return $courses;
    }

    // ========== CERTIFICATE ==========
    public function get_certificate($user_id, $course_id) {
        $enrol = $this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $course_id])->row();
        if (!$enrol) return ['error' => 'Not enrolled in this course'];

        $watch = $this->db->get_where('watch_histories', ['course_id' => $course_id, 'student_id' => $user_id])->row();
        if (!$watch || $watch->course_progress < 100) {
            return ['error' => 'Course not completed yet'];
        }

        $course = $this->db->get_where('course', ['id' => $course_id])->row_array();
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        $instructor = $this->db->get_where('users', ['id' => $course['user_id']])->row_array();

        return [
            'certificate_id' => 'CERT-' . strtoupper(substr(md5($user_id . $course_id), 0, 10)),
            'student_name' => $user['first_name'] . ' ' . $user['last_name'],
            'course_title' => $course['title'],
            'instructor_name' => $instructor['first_name'] . ' ' . $instructor['last_name'],
            'completion_date' => $watch->completed_date ? date('F d, Y', $watch->completed_date) : date('F d, Y'),
            'issue_date' => date('F d, Y'),
            'verify_url' => base_url('certificate/verify/' . md5($user_id . $course_id))
        ];
    }
}
