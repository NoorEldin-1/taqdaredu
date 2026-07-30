<?php
require APPPATH . '/libraries/TokenHandler.php';
require APPPATH . 'libraries/REST_Controller.php';

/**
 * Reports API Controller
 * 
 * RESTful API for generating reports and statistics
 * 
 * @package Academy LMS
 * @version 2.0
 */
class Api_reports extends REST_Controller
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

    // ========== INSTRUCTOR REPORTS ==========
    
    /**
     * GET /api_reports/instructor/dashboard
     * Instructor dashboard statistics
     */
    public function instructor_dashboard_get()
    {
        $user = $this->authenticate_instructor();
        if (!$user) return;
        
        $user_id = $user['user_id'];
        
        // Total courses
        $total_courses = $this->db->where('user_id', $user_id)->count_all_results('course');
        
        // Active courses
        $active_courses = $this->db->where('user_id', $user_id)->where('status', 'active')->count_all_results('course');
        
        // Total students (unique enrollments)
        $course_ids = $this->db->select('id')->where('user_id', $user_id)->get('course')->result_array();
        $course_ids_array = array_column($course_ids, 'id');
        $total_students = 0;
        if (!empty($course_ids_array)) {
            $total_students = $this->db->where_in('course_id', $course_ids_array)->select('user_id')->distinct()->get('enrol')->num_rows();
        }
        
        // Total enrollments
        $total_enrollments = 0;
        if (!empty($course_ids_array)) {
            $total_enrollments = $this->db->where_in('course_id', $course_ids_array)->count_all_results('enrol');
        }
        
        // Total revenue
        $total_revenue = 0;
        if (!empty($course_ids_array)) {
            $revenue = $this->db->select_sum('instructor_revenue')->where_in('course_id', $course_ids_array)->get('payment')->row_array();
            $total_revenue = $revenue['instructor_revenue'] ?? 0;
        }
        
        // This month revenue
        $first_day = strtotime(date('Y-m-01'));
        $monthly_revenue = 0;
        if (!empty($course_ids_array)) {
            $m_revenue = $this->db->select_sum('instructor_revenue')
                ->where_in('course_id', $course_ids_array)
                ->where('date_added >=', $first_day)
                ->get('payment')->row_array();
            $monthly_revenue = $m_revenue['instructor_revenue'] ?? 0;
        }
        
        // Average rating
        $avg_rating = 0;
        if (!empty($course_ids_array)) {
            $rating = $this->db->select_avg('rating')->where_in('course_id', $course_ids_array)->get('rating')->row_array();
            $avg_rating = round($rating['rating'] ?? 0, 1);
        }
        
        // Total reviews
        $total_reviews = 0;
        if (!empty($course_ids_array)) {
            $total_reviews = $this->db->where_in('course_id', $course_ids_array)->count_all_results('rating');
        }
        
        // Pending payouts
        $pending_payouts = $this->db->select_sum('amount')
            ->where('user_id', $user_id)
            ->where('status', 'pending')
            ->get('payout')->row_array();
        
        return $this->response([
            'status' => true,
            'data' => [
                'total_courses' => $total_courses,
                'active_courses' => $active_courses,
                'total_students' => $total_students,
                'total_enrollments' => $total_enrollments,
                'total_revenue' => floatval($total_revenue),
                'monthly_revenue' => floatval($monthly_revenue),
                'average_rating' => $avg_rating,
                'total_reviews' => $total_reviews,
                'pending_payouts' => floatval($pending_payouts['amount'] ?? 0)
            ]
        ], 200);
    }
    
    /**
     * GET /api_reports/instructor/sales
     * Instructor sales report
     */
    public function instructor_sales_get()
    {
        $user = $this->authenticate_instructor();
        if (!$user) return;
        
        $date_from = $this->input->get('date_from') ?: date('Y-m-01');
        $date_to = $this->input->get('date_to') ?: date('Y-m-d');
        
        // Get instructor courses
        $course_ids = $this->db->select('id')->where('user_id', $user['user_id'])->get('course')->result_array();
        $course_ids_array = array_column($course_ids, 'id');
        
        if (empty($course_ids_array)) {
            return $this->response([
                'status' => true,
                'data' => [
                    'report' => [],
                    'summary' => ['total_sales' => 0, 'total_revenue' => 0],
                    'date_from' => $date_from,
                    'date_to' => $date_to
                ]
            ], 200);
        }
        
        // Daily sales
        $this->db->select('DATE(FROM_UNIXTIME(date_added)) as date, SUM(instructor_revenue) as revenue, COUNT(*) as sales');
        $this->db->from('payment');
        $this->db->where_in('course_id', $course_ids_array);
        $this->db->where('date_added >=', strtotime($date_from));
        $this->db->where('date_added <=', strtotime($date_to . ' 23:59:59'));
        $this->db->group_by('DATE(FROM_UNIXTIME(date_added))');
        $this->db->order_by('date', 'ASC');
        
        $report = $this->db->get()->result_array();
        
        // Summary
        $summary = $this->db->select_sum('instructor_revenue', 'total_revenue')
            ->select('COUNT(*) as total_sales', false)
            ->where_in('course_id', $course_ids_array)
            ->where('date_added >=', strtotime($date_from))
            ->where('date_added <=', strtotime($date_to . ' 23:59:59'))
            ->get('payment')->row_array();
        
        return $this->response([
            'status' => true,
            'data' => [
                'report' => $report,
                'summary' => $summary,
                'date_from' => $date_from,
                'date_to' => $date_to
            ]
        ], 200);
    }
    
    /**
     * GET /api_reports/instructor/courses
     * Instructor courses performance
     */
    public function instructor_courses_get()
    {
        $user = $this->authenticate_instructor();
        if (!$user) return;
        
        $courses = $this->db->select('c.id, c.title, c.status, c.price, c.is_free_course')
            ->from('course c')
            ->where('c.user_id', $user['user_id'])
            ->get()
            ->result_array();
        
        foreach ($courses as &$course) {
            // Enrollments
            $course['enrollments'] = $this->db->where('course_id', $course['id'])->count_all_results('enrol');
            
            // Revenue
            $revenue = $this->db->select_sum('instructor_revenue')->where('course_id', $course['id'])->get('payment')->row_array();
            $course['revenue'] = floatval($revenue['instructor_revenue'] ?? 0);
            
            // Rating
            $rating = $this->db->select_avg('rating')->where('course_id', $course['id'])->get('rating')->row_array();
            $course['rating'] = round($rating['rating'] ?? 0, 1);
            
            // Reviews count
            $course['reviews'] = $this->db->where('course_id', $course['id'])->count_all_results('rating');
            
            // Lessons count
            $course['lessons'] = $this->db->where('course_id', $course['id'])->count_all_results('lesson');
            
            // Thumbnail
            $course['thumbnail'] = $this->crud_model->get_course_thumbnail_url($course['id']);
        }
        
        // Sort by revenue
        usort($courses, function($a, $b) {
            return $b['revenue'] - $a['revenue'];
        });
        
        return $this->response(['status' => true, 'data' => $courses], 200);
    }
    
    /**
     * GET /api_reports/instructor/students
     * Instructor students list
     */
    public function instructor_students_get()
    {
        $user = $this->authenticate_instructor();
        if (!$user) return;
        
        $page = $this->input->get('page') ?: 1;
        $limit = $this->input->get('limit') ?: 20;
        $offset = ($page - 1) * $limit;
        
        // Get instructor courses
        $course_ids = $this->db->select('id')->where('user_id', $user['user_id'])->get('course')->result_array();
        $course_ids_array = array_column($course_ids, 'id');
        
        if (empty($course_ids_array)) {
            return $this->response([
                'status' => true,
                'data' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 0]
            ], 200);
        }
        
        $this->db->select('u.id, u.first_name, u.last_name, u.email, u.image, COUNT(DISTINCT e.course_id) as courses_enrolled, MIN(e.date_added) as enrolled_since');
        $this->db->from('enrol e');
        $this->db->join('users u', 'u.id = e.user_id', 'left');
        $this->db->where_in('e.course_id', $course_ids_array);
        $this->db->group_by('e.user_id');
        
        $total = $this->db->count_all_results('', false);
        
        $students = $this->db->order_by('enrolled_since', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        
        foreach ($students as &$student) {
            $student['name'] = $student['first_name'] . ' ' . $student['last_name'];
            $student['enrolled_since_formatted'] = date('Y-m-d', $student['enrolled_since']);
        }
        
        return $this->response([
            'status' => true,
            'data' => $students,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => ceil($total / $limit)
            ]
        ], 200);
    }
    
    /**
     * GET /api_reports/instructor/payouts
     * Instructor payout history
     */
    public function instructor_payouts_get()
    {
        $user = $this->authenticate_instructor();
        if (!$user) return;
        
        $payouts = $this->db->order_by('id', 'DESC')
            ->get_where('payout', ['user_id' => $user['user_id']])
            ->result_array();
        
        // Balance
        $course_ids = $this->db->select('id')->where('user_id', $user['user_id'])->get('course')->result_array();
        $course_ids_array = array_column($course_ids, 'id');
        
        $total_earned = 0;
        if (!empty($course_ids_array)) {
            $earned = $this->db->select_sum('instructor_revenue')->where_in('course_id', $course_ids_array)->get('payment')->row_array();
            $total_earned = floatval($earned['instructor_revenue'] ?? 0);
        }
        
        $total_withdrawn = $this->db->select_sum('amount')
            ->where('user_id', $user['user_id'])
            ->where('status', 'completed')
            ->get('payout')->row_array();
        
        $balance = $total_earned - floatval($total_withdrawn['amount'] ?? 0);
        
        return $this->response([
            'status' => true,
            'data' => [
                'payouts' => $payouts,
                'balance' => $balance,
                'total_earned' => $total_earned,
                'total_withdrawn' => floatval($total_withdrawn['amount'] ?? 0)
            ]
        ], 200);
    }

    // ========== STUDENT REPORTS ==========
    
    /**
     * GET /api_reports/student/dashboard
     * Student dashboard statistics
     */
    public function student_dashboard_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $user_id = $user['user_id'];
        
        // Enrolled courses
        $enrolled_courses = $this->db->where('user_id', $user_id)->count_all_results('enrol');
        
        // Completed courses
        $enrolled = $this->db->select('course_id')->where('user_id', $user_id)->get('enrol')->result_array();
        $course_ids = array_column($enrolled, 'course_id');
        
        $completed_courses = 0;
        foreach ($course_ids as $course_id) {
            $progress = $this->get_course_progress($user_id, $course_id);
            if ($progress >= 100) {
                $completed_courses++;
            }
        }
        
        // In progress courses
        $in_progress = $enrolled_courses - $completed_courses;
        
        // Certificates earned
        $certificates = $this->db->where('user_id', $user_id)->count_all_results('certificates');
        
        // Total spending
        $spending = $this->db->select_sum('amount')->where('user_id', $user_id)->get('payment')->row_array();
        
        // Total watch time (in minutes)
        $watch_time = $this->db->select_sum('watched_duration')
            ->where('user_id', $user_id)
            ->get('watch_histories')->row_array();
        
        return $this->response([
            'status' => true,
            'data' => [
                'enrolled_courses' => $enrolled_courses,
                'completed_courses' => $completed_courses,
                'in_progress' => $in_progress,
                'certificates' => $certificates,
                'total_spending' => floatval($spending['amount'] ?? 0),
                'total_watch_time' => intval($watch_time['watched_duration'] ?? 0)
            ]
        ], 200);
    }
    
    /**
     * GET /api_reports/student/progress
     * Student course progress
     */
    public function student_progress_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $enrolled = $this->db->select('e.course_id, e.date_added, c.title, c.user_id as instructor_id')
            ->from('enrol e')
            ->join('course c', 'c.id = e.course_id', 'left')
            ->where('e.user_id', $user['user_id'])
            ->get()
            ->result_array();
        
        foreach ($enrolled as &$course) {
            $course['progress'] = $this->get_course_progress($user['user_id'], $course['course_id']);
            $course['enrolled_date'] = date('Y-m-d', $course['date_added']);
            $course['thumbnail'] = $this->crud_model->get_course_thumbnail_url($course['course_id']);
            
            // Instructor info
            $instructor = $this->db->select('first_name, last_name')->get_where('users', ['id' => $course['instructor_id']])->row_array();
            $course['instructor_name'] = $instructor ? $instructor['first_name'] . ' ' . $instructor['last_name'] : '';
            
            // Lessons completed
            $total_lessons = $this->db->where('course_id', $course['course_id'])->count_all_results('lesson');
            $completed_lessons = $this->get_completed_lessons_count($user['user_id'], $course['course_id']);
            $course['lessons_total'] = $total_lessons;
            $course['lessons_completed'] = $completed_lessons;
        }
        
        // Sort by progress (in progress first)
        usort($enrolled, function($a, $b) {
            if ($a['progress'] == 100 && $b['progress'] < 100) return 1;
            if ($a['progress'] < 100 && $b['progress'] == 100) return -1;
            return $b['progress'] - $a['progress'];
        });
        
        return $this->response(['status' => true, 'data' => $enrolled], 200);
    }
    
    /**
     * GET /api_reports/student/certificates
     * Student certificates
     */
    public function student_certificates_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $certificates = $this->db->select('cert.*, c.title as course_title')
            ->from('certificates cert')
            ->join('course c', 'c.id = cert.course_id', 'left')
            ->where('cert.user_id', $user['user_id'])
            ->order_by('cert.created_at', 'DESC')
            ->get()
            ->result_array();
        
        foreach ($certificates as &$cert) {
            $cert['created_at_formatted'] = date('Y-m-d', $cert['created_at']);
            $cert['download_url'] = base_url('certificate/' . $cert['shareable_code']);
        }
        
        return $this->response(['status' => true, 'data' => $certificates], 200);
    }
    
    /**
     * GET /api_reports/student/activity
     * Student activity timeline
     */
    public function student_activity_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $limit = $this->input->get('limit') ?: 20;
        
        // Get recent watch history
        $activities = $this->db->select('w.lesson_id, w.watching_time, w.date_added, l.title as lesson_title, c.title as course_title, c.id as course_id')
            ->from('watch_histories w')
            ->join('lesson l', 'l.id = w.lesson_id', 'left')
            ->join('course c', 'c.id = l.course_id', 'left')
            ->where('w.user_id', $user['user_id'])
            ->order_by('w.date_added', 'DESC')
            ->limit($limit)
            ->get()
            ->result_array();
        
        foreach ($activities as &$activity) {
            $activity['date_formatted'] = date('Y-m-d H:i', $activity['date_added']);
            $activity['type'] = 'lesson_watched';
        }
        
        return $this->response(['status' => true, 'data' => $activities], 200);
    }

    // ========== HELPER METHODS ==========
    
    private function get_course_progress($user_id, $course_id)
    {
        $total_lessons = $this->db->where('course_id', $course_id)->count_all_results('lesson');
        if ($total_lessons == 0) return 0;
        
        $completed = $this->get_completed_lessons_count($user_id, $course_id);
        return round(($completed / $total_lessons) * 100);
    }
    
    private function get_completed_lessons_count($user_id, $course_id)
    {
        // Canonical union of watch_histories + lesson_progress — the same
        // source student_activity_get()/get_course_curriculum() use, so every
        // screen agrees on the same completion count.
        return count(mycom_completed_lesson_ids($user_id, $course_id));
    }
    
    /**
     * Authenticate user
     */
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
    
    /**
     * Authenticate instructor
     */
    private function authenticate_instructor()
    {
        $user = $this->authenticate();
        if (!$user) return false;
        
        // Check if user is instructor
        $user_data = $this->db->get_where('users', ['id' => $user['user_id']])->row_array();
        if (!$user_data || $user_data['is_instructor'] != 1) {
            $this->response(['status' => false, 'message' => 'Instructor access required'], 403);
            return false;
        }
        
        return $user;
    }
}
