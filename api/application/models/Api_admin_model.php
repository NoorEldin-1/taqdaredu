<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin API Model
 * 
 * Handles all admin-related API operations
 * 
 * @package Academy LMS
 * @version 2.0
 */
class Api_admin_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('crud_model');
        $this->load->model('user_model');
    }

    // ========== DASHBOARD ==========
    
    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats()
    {
        $stats = [];
        
        // Total users
        $stats['total_users'] = $this->db->count_all('users');
        
        // Total students
        $stats['total_students'] = $this->db->where('role_id', 2)->count_all_results('users');
        
        // Total instructors
        $stats['total_instructors'] = $this->db->where('is_instructor', 1)->count_all_results('users');
        
        // Total admins
        $stats['total_admins'] = $this->db->where('role_id', 1)->count_all_results('users');
        
        // Total courses
        $stats['total_courses'] = $this->db->count_all('course');
        
        // Active courses
        $stats['active_courses'] = $this->db->where('status', 'active')->count_all_results('course');
        
        // Pending courses
        $stats['pending_courses'] = $this->db->where('status', 'pending')->count_all_results('course');
        
        // Total enrollments
        $stats['total_enrollments'] = $this->db->count_all('enrol');
        
        // Total categories
        $stats['total_categories'] = $this->db->where('parent', 0)->count_all_results('category');
        
        // Total revenue
        $revenue = $this->db->select_sum('amount')->get('payment')->row_array();
        $stats['total_revenue'] = $revenue['amount'] ?? 0;
        
        // Revenue this month
        $first_day = strtotime(date('Y-m-01'));
        $last_day = strtotime(date('Y-m-t'));
        $monthly_revenue = $this->db->select_sum('amount')
            ->where('date_added >=', $first_day)
            ->where('date_added <=', $last_day)
            ->get('payment')->row_array();
        $stats['monthly_revenue'] = $monthly_revenue['amount'] ?? 0;
        
        // Enrollments this month
        $stats['monthly_enrollments'] = $this->db
            ->where('date_added >=', $first_day)
            ->where('date_added <=', $last_day)
            ->count_all_results('enrol');
        
        // New users this month
        $stats['monthly_new_users'] = $this->db
            ->where('date_added >=', $first_day)
            ->where('date_added <=', $last_day)
            ->count_all_results('users');
        
        // Recent activity chart data (last 7 days)
        $stats['chart_data'] = $this->get_chart_data();
        
        return $stats;
    }
    
    /**
     * Get chart data for last 7 days
     */
    private function get_chart_data()
    {
        $chart = ['labels' => [], 'enrollments' => [], 'revenue' => []];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $timestamp_start = strtotime($date . ' 00:00:00');
            $timestamp_end = strtotime($date . ' 23:59:59');
            
            $chart['labels'][] = date('M d', strtotime($date));
            
            // Enrollments
            $chart['enrollments'][] = $this->db
                ->where('date_added >=', $timestamp_start)
                ->where('date_added <=', $timestamp_end)
                ->count_all_results('enrol');
            
            // Revenue
            $revenue = $this->db->select_sum('amount')
                ->where('date_added >=', $timestamp_start)
                ->where('date_added <=', $timestamp_end)
                ->get('payment')->row_array();
            $chart['revenue'][] = floatval($revenue['amount'] ?? 0);
        }
        
        return $chart;
    }

    // ========== USERS MANAGEMENT ==========
    
    /**
     * Get users list with filters and pagination
     */
    public function get_users($params = [])
    {
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 20;
        $offset = ($page - 1) * $limit;
        
        $this->db->select('id, first_name, last_name, email, role_id, is_instructor, status, image, date_added');
        
        // Filters
        if (!empty($params['role'])) {
            if ($params['role'] == 'instructor') {
                $this->db->where('is_instructor', 1);
            } else {
                $this->db->where('role_id', $params['role']);
            }
        }
        
        if (!empty($params['status'])) {
            $this->db->where('status', $params['status']);
        }
        
        if (!empty($params['search'])) {
            $this->db->group_start();
            $this->db->like('first_name', $params['search']);
            $this->db->or_like('last_name', $params['search']);
            $this->db->or_like('email', $params['search']);
            $this->db->group_end();
        }
        
        // Count total
        $total = $this->db->count_all_results('users', false);
        
        // Get data
        $users = $this->db->order_by('id', 'DESC')
            ->limit($limit, $offset)
            ->get('users')
            ->result_array();
        
        foreach ($users as &$user) {
            $user['role'] = $user['role_id'] == 1 ? 'admin' : ($user['is_instructor'] ? 'instructor' : 'student');
            $user['image_url'] = $this->get_user_image($user['id']);
            $user['date_added_formatted'] = date('Y-m-d H:i:s', $user['date_added']);
        }
        
        return [
            'users' => $users,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get single user details
     */
    public function get_user($user_id)
    {
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        if (!$user) return null;
        
        unset($user['password']);
        $user['role'] = $user['role_id'] == 1 ? 'admin' : ($user['is_instructor'] ? 'instructor' : 'student');
        $user['image_url'] = $this->get_user_image($user_id);
        $user['enrolled_courses'] = $this->get_user_enrolled_courses_count($user_id);
        $user['total_spending'] = $this->get_user_total_spending($user_id);
        
        return $user;
    }
    
    /**
     * Create new user
     */
    public function create_user($data)
    {
        // Validate required fields
        if (empty($data['email']) || empty($data['password'])) {
            return ['status' => false, 'message' => 'Email and password are required'];
        }
        
        // Check if email exists
        if ($this->db->get_where('users', ['email' => $data['email']])->num_rows() > 0) {
            return ['status' => false, 'message' => 'Email already exists'];
        }
        
        $insert_data = [
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'role_id' => $data['role_id'] ?? 2,
            'is_instructor' => $data['is_instructor'] ?? 0,
            'status' => $data['status'] ?? 1,
            'date_added' => time()
        ];
        
        $this->db->insert('users', $insert_data);
        $user_id = $this->db->insert_id();
        
        return ['status' => true, 'message' => 'User created successfully', 'user_id' => $user_id];
    }
    
    /**
     * Update user
     */
    public function update_user($user_id, $data)
    {
        $update_data = [];
        
        $allowed_fields = ['first_name', 'last_name', 'email', 'role_id', 'is_instructor', 'status', 'phone', 'address', 'biography', 'title'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }
        
        // Handle password separately
        if (!empty($data['password'])) {
            $update_data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        
        if (empty($update_data)) {
            return ['status' => false, 'message' => 'No valid fields to update'];
        }
        
        $this->db->where('id', $user_id)->update('users', $update_data);
        
        return ['status' => true, 'message' => 'User updated successfully'];
    }
    
    /**
     * Delete user
     */
    public function delete_user($user_id)
    {
        // Don't delete admin users
        $user = $this->db->get_where('users', ['id' => $user_id])->row_array();
        if ($user && $user['role_id'] == 1) {
            return ['status' => false, 'message' => 'Cannot delete admin user'];
        }
        
        $this->db->where('id', $user_id)->delete('users');
        
        return ['status' => true, 'message' => 'User deleted successfully'];
    }
    
    /**
     * Get user image URL
     */
    private function get_user_image($user_id)
    {
        $user = $this->db->select('image')->get_where('users', ['id' => $user_id])->row_array();
        if ($user && !empty($user['image']) && file_exists('uploads/user_image/' . $user['image'])) {
            return base_url('uploads/user_image/' . $user['image']);
        }
        return base_url('uploads/user_image/default.png');
    }
    
    private function get_user_enrolled_courses_count($user_id)
    {
        return $this->db->where('user_id', $user_id)->count_all_results('enrol');
    }
    
    private function get_user_total_spending($user_id)
    {
        $result = $this->db->select_sum('amount')->where('user_id', $user_id)->get('payment')->row_array();
        return $result['amount'] ?? 0;
    }

    // ========== COURSES MANAGEMENT ==========
    
    /**
     * Get courses list with filters
     */
    public function get_courses($params = [])
    {
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 20;
        $offset = ($page - 1) * $limit;
        
        $this->db->select('c.*, u.first_name as instructor_first_name, u.last_name as instructor_last_name, cat.name as category_name');
        $this->db->from('course c');
        $this->db->join('users u', 'u.id = c.user_id', 'left');
        $this->db->join('category cat', 'cat.id = c.category_id', 'left');
        
        // Filters
        if (!empty($params['status'])) {
            $this->db->where('c.status', $params['status']);
        }
        
        if (!empty($params['category_id'])) {
            $this->db->where('c.category_id', $params['category_id']);
        }
        
        if (!empty($params['instructor_id'])) {
            $this->db->where('c.user_id', $params['instructor_id']);
        }
        
        if (!empty($params['is_free'])) {
            $this->db->where('c.is_free_course', $params['is_free']);
        }
        
        if (!empty($params['search'])) {
            $this->db->like('c.title', $params['search']);
        }
        
        // Count total
        $total = $this->db->count_all_results('', false);
        
        // Get data
        $courses = $this->db->order_by('c.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        
        foreach ($courses as &$course) {
            $course['instructor_name'] = $course['instructor_first_name'] . ' ' . $course['instructor_last_name'];
            $course['thumbnail_url'] = $this->crud_model->get_course_thumbnail_url($course['id']);
            $course['total_enrolled'] = $this->db->where('course_id', $course['id'])->count_all_results('enrol');
            $course['total_lessons'] = $this->db->where('course_id', $course['id'])->count_all_results('lesson');
        }
        
        return [
            'courses' => $courses,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get single course details
     */
    public function get_course($course_id)
    {
        $course = $this->db->get_where('course', ['id' => $course_id])->row_array();
        if (!$course) return null;
        
        $course['instructor'] = $this->get_user($course['user_id']);
        $course['category'] = $this->db->get_where('category', ['id' => $course['category_id']])->row_array();
        $course['thumbnail_url'] = $this->crud_model->get_course_thumbnail_url($course_id);
        $course['total_enrolled'] = $this->db->where('course_id', $course_id)->count_all_results('enrol');
        $course['sections'] = $this->get_course_sections($course_id);
        $course['total_lessons'] = $this->db->where('course_id', $course_id)->count_all_results('lesson');
        $course['total_revenue'] = $this->get_course_revenue($course_id);
        
        return $course;
    }
    
    /**
     * Update course status
     */
    public function update_course_status($course_id, $status)
    {
        $valid_statuses = ['active', 'pending', 'draft', 'inactive'];
        if (!in_array($status, $valid_statuses)) {
            return ['status' => false, 'message' => 'Invalid status'];
        }
        
        $this->db->where('id', $course_id)->update('course', ['status' => $status]);
        
        return ['status' => true, 'message' => 'Course status updated'];
    }
    
    /**
     * Delete course
     */
    public function delete_course($course_id)
    {
        // Delete related data
        $this->db->where('course_id', $course_id)->delete('section');
        $this->db->where('course_id', $course_id)->delete('lesson');
        $this->db->where('course_id', $course_id)->delete('enrol');
        $this->db->where('course_id', $course_id)->delete('rating');
        $this->db->where('id', $course_id)->delete('course');
        
        return ['status' => true, 'message' => 'Course deleted successfully'];
    }
    
    private function get_course_sections($course_id)
    {
        $sections = $this->db->order_by('order', 'ASC')->get_where('section', ['course_id' => $course_id])->result_array();
        foreach ($sections as &$section) {
            $section['lessons'] = $this->db->order_by('order', 'ASC')->get_where('lesson', ['section_id' => $section['id']])->result_array();
        }
        return $sections;
    }
    
    private function get_course_revenue($course_id)
    {
        $result = $this->db->select_sum('amount')
            ->where('course_id', $course_id)
            ->get('payment')->row_array();
        return $result['amount'] ?? 0;
    }

    // ========== CATEGORIES MANAGEMENT ==========
    
    /**
     * Get all categories
     */
    public function get_categories()
    {
        $categories = $this->db->where('parent', 0)->order_by('id', 'DESC')->get('category')->result_array();
        
        foreach ($categories as &$cat) {
            $cat['sub_categories'] = $this->db->where('parent', $cat['id'])->get('category')->result_array();
            $cat['courses_count'] = $this->db->where('category_id', $cat['id'])->count_all_results('course');
            $cat['thumbnail_url'] = $this->get_category_thumbnail($cat['id']);
        }
        
        return $categories;
    }
    
    /**
     * Get single category
     */
    public function get_category($category_id)
    {
        $category = $this->db->get_where('category', ['id' => $category_id])->row_array();
        if (!$category) return null;
        
        $category['sub_categories'] = $this->db->where('parent', $category_id)->get('category')->result_array();
        $category['courses_count'] = $this->db->where('category_id', $category_id)->count_all_results('course');
        $category['thumbnail_url'] = $this->get_category_thumbnail($category_id);
        
        return $category;
    }
    
    /**
     * Create category
     */
    public function create_category($data)
    {
        if (empty($data['name'])) {
            return ['status' => false, 'message' => 'Category name is required'];
        }
        
        $slug = url_title($data['name'], 'dash', TRUE);
        
        $insert_data = [
            'name' => $data['name'],
            'slug' => $slug,
            'parent' => $data['parent'] ?? 0,
            'font_awesome_class' => $data['icon'] ?? '',
            'date_added' => time()
        ];
        
        $this->db->insert('category', $insert_data);
        $category_id = $this->db->insert_id();
        
        return ['status' => true, 'message' => 'Category created successfully', 'category_id' => $category_id];
    }
    
    /**
     * Update category
     */
    public function update_category($category_id, $data)
    {
        $update_data = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
            $update_data['slug'] = url_title($data['name'], 'dash', TRUE);
        }
        if (isset($data['parent'])) $update_data['parent'] = $data['parent'];
        if (isset($data['icon'])) $update_data['font_awesome_class'] = $data['icon'];
        
        if (empty($update_data)) {
            return ['status' => false, 'message' => 'No valid fields to update'];
        }
        
        $this->db->where('id', $category_id)->update('category', $update_data);
        
        return ['status' => true, 'message' => 'Category updated successfully'];
    }
    
    /**
     * Delete category
     */
    public function delete_category($category_id)
    {
        // Delete sub-categories
        $this->db->where('parent', $category_id)->delete('category');
        $this->db->where('id', $category_id)->delete('category');
        
        return ['status' => true, 'message' => 'Category deleted successfully'];
    }
    
    private function get_category_thumbnail($category_id)
    {
        $category = $this->db->select('thumbnail')->get_where('category', ['id' => $category_id])->row_array();
        if ($category && !empty($category['thumbnail']) && file_exists('uploads/category_thumbnail/' . $category['thumbnail'])) {
            return base_url('uploads/category_thumbnail/' . $category['thumbnail']);
        }
        return base_url('uploads/category_thumbnail/default.png');
    }

    // ========== PAYMENTS MANAGEMENT ==========
    
    /**
     * Get payments list
     */
    public function get_payments($params = [])
    {
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 20;
        $offset = ($page - 1) * $limit;
        
        $this->db->select('p.*, u.first_name, u.last_name, u.email, c.title as course_title');
        $this->db->from('payment p');
        $this->db->join('users u', 'u.id = p.user_id', 'left');
        $this->db->join('course c', 'c.id = p.course_id', 'left');
        
        // Filters
        if (!empty($params['user_id'])) {
            $this->db->where('p.user_id', $params['user_id']);
        }
        
        if (!empty($params['course_id'])) {
            $this->db->where('p.course_id', $params['course_id']);
        }
        
        if (!empty($params['payment_type'])) {
            $this->db->where('p.payment_type', $params['payment_type']);
        }
        
        if (!empty($params['date_from'])) {
            $this->db->where('p.date_added >=', strtotime($params['date_from']));
        }
        
        if (!empty($params['date_to'])) {
            $this->db->where('p.date_added <=', strtotime($params['date_to'] . ' 23:59:59'));
        }
        
        // Count total
        $total = $this->db->count_all_results('', false);
        
        // Get data
        $payments = $this->db->order_by('p.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        
        foreach ($payments as &$payment) {
            $payment['user_name'] = $payment['first_name'] . ' ' . $payment['last_name'];
            $payment['date_added_formatted'] = date('Y-m-d H:i:s', $payment['date_added']);
        }
        
        return [
            'payments' => $payments,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get single payment
     */
    public function get_payment($payment_id)
    {
        $this->db->select('p.*, u.first_name, u.last_name, u.email, c.title as course_title');
        $this->db->from('payment p');
        $this->db->join('users u', 'u.id = p.user_id', 'left');
        $this->db->join('course c', 'c.id = p.course_id', 'left');
        $this->db->where('p.id', $payment_id);
        
        $payment = $this->db->get()->row_array();
        if ($payment) {
            $payment['user_name'] = $payment['first_name'] . ' ' . $payment['last_name'];
            $payment['date_added_formatted'] = date('Y-m-d H:i:s', $payment['date_added']);
        }
        
        return $payment;
    }

    // ========== ENROLLMENTS MANAGEMENT ==========
    
    /**
     * Get enrollments list
     */
    public function get_enrollments($params = [])
    {
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 20;
        $offset = ($page - 1) * $limit;
        
        $this->db->select('e.*, u.first_name, u.last_name, u.email, c.title as course_title');
        $this->db->from('enrol e');
        $this->db->join('users u', 'u.id = e.user_id', 'left');
        $this->db->join('course c', 'c.id = e.course_id', 'left');
        
        // Filters
        if (!empty($params['user_id'])) {
            $this->db->where('e.user_id', $params['user_id']);
        }
        
        if (!empty($params['course_id'])) {
            $this->db->where('e.course_id', $params['course_id']);
        }
        
        // Count total
        $total = $this->db->count_all_results('', false);
        
        // Get data
        $enrollments = $this->db->order_by('e.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        
        foreach ($enrollments as &$enrol) {
            $enrol['user_name'] = $enrol['first_name'] . ' ' . $enrol['last_name'];
            $enrol['date_added_formatted'] = date('Y-m-d H:i:s', $enrol['date_added']);
        }
        
        return [
            'enrollments' => $enrollments,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Create enrollment (manual)
     */
    public function create_enrollment($data)
    {
        if (empty($data['user_id']) || empty($data['course_id'])) {
            return ['status' => false, 'message' => 'User ID and Course ID are required'];
        }
        
        // Check if already enrolled
        $exists = $this->db->get_where('enrol', [
            'user_id' => $data['user_id'],
            'course_id' => $data['course_id']
        ])->num_rows();
        
        if ($exists > 0) {
            return ['status' => false, 'message' => 'User is already enrolled in this course'];
        }
        
        $insert_data = [
            'user_id' => $data['user_id'],
            'course_id' => $data['course_id'],
            'date_added' => time()
        ];
        
        $this->db->insert('enrol', $insert_data);
        
        return ['status' => true, 'message' => 'Enrollment created successfully'];
    }
    
    /**
     * Delete enrollment
     */
    public function delete_enrollment($enrol_id)
    {
        $this->db->where('id', $enrol_id)->delete('enrol');
        return ['status' => true, 'message' => 'Enrollment deleted successfully'];
    }

    // ========== PAYOUTS MANAGEMENT ==========
    
    /**
     * Get payouts list
     */
    public function get_payouts($params = [])
    {
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 20;
        $offset = ($page - 1) * $limit;
        
        $this->db->select('p.*, u.first_name, u.last_name, u.email');
        $this->db->from('payout p');
        $this->db->join('users u', 'u.id = p.user_id', 'left');
        
        // Filters
        if (!empty($params['user_id'])) {
            $this->db->where('p.user_id', $params['user_id']);
        }
        
        if (!empty($params['status'])) {
            $this->db->where('p.status', $params['status']);
        }
        
        // Count total
        $total = $this->db->count_all_results('', false);
        
        // Get data
        $payouts = $this->db->order_by('p.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        
        foreach ($payouts as &$payout) {
            $payout['user_name'] = $payout['first_name'] . ' ' . $payout['last_name'];
        }
        
        return [
            'payouts' => $payouts,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Update payout status
     */
    public function update_payout_status($payout_id, $status)
    {
        $valid_statuses = ['pending', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            return ['status' => false, 'message' => 'Invalid status'];
        }
        
        $this->db->where('id', $payout_id)->update('payout', ['status' => $status]);
        
        return ['status' => true, 'message' => 'Payout status updated'];
    }

    // ========== COUPONS MANAGEMENT ==========
    
    /**
     * Get coupons list
     */
    public function get_coupons()
    {
        $coupons = $this->db->order_by('id', 'DESC')->get('coupon')->result_array();
        
        foreach ($coupons as &$coupon) {
            $coupon['is_expired'] = $coupon['expiry_date'] < time();
            $coupon['expiry_date_formatted'] = date('Y-m-d', $coupon['expiry_date']);
        }
        
        return $coupons;
    }
    
    /**
     * Get single coupon
     */
    public function get_coupon($coupon_id)
    {
        $coupon = $this->db->get_where('coupon', ['id' => $coupon_id])->row_array();
        if ($coupon) {
            $coupon['is_expired'] = $coupon['expiry_date'] < time();
            $coupon['expiry_date_formatted'] = date('Y-m-d', $coupon['expiry_date']);
        }
        return $coupon;
    }
    
    /**
     * Create coupon
     */
    public function create_coupon($data)
    {
        if (empty($data['code']) || empty($data['discount'])) {
            return ['status' => false, 'message' => 'Code and discount are required'];
        }
        
        // Check if code exists
        if ($this->db->get_where('coupon', ['code' => $data['code']])->num_rows() > 0) {
            return ['status' => false, 'message' => 'Coupon code already exists'];
        }
        
        $insert_data = [
            'code' => strtoupper($data['code']),
            'discount' => $data['discount'],
            'discount_type' => $data['discount_type'] ?? 'percentage',
            'expiry_date' => strtotime($data['expiry_date'] ?? '+30 days'),
            'min_purchase' => $data['min_purchase'] ?? 0,
            'created_at' => time()
        ];
        
        $this->db->insert('coupon', $insert_data);
        $coupon_id = $this->db->insert_id();
        
        return ['status' => true, 'message' => 'Coupon created successfully', 'coupon_id' => $coupon_id];
    }
    
    /**
     * Update coupon
     */
    public function update_coupon($coupon_id, $data)
    {
        $update_data = [];
        
        if (isset($data['code'])) $update_data['code'] = strtoupper($data['code']);
        if (isset($data['discount'])) $update_data['discount'] = $data['discount'];
        if (isset($data['discount_type'])) $update_data['discount_type'] = $data['discount_type'];
        if (isset($data['expiry_date'])) $update_data['expiry_date'] = strtotime($data['expiry_date']);
        if (isset($data['min_purchase'])) $update_data['min_purchase'] = $data['min_purchase'];
        
        if (empty($update_data)) {
            return ['status' => false, 'message' => 'No valid fields to update'];
        }
        
        $this->db->where('id', $coupon_id)->update('coupon', $update_data);
        
        return ['status' => true, 'message' => 'Coupon updated successfully'];
    }
    
    /**
     * Delete coupon
     */
    public function delete_coupon($coupon_id)
    {
        $this->db->where('id', $coupon_id)->delete('coupon');
        return ['status' => true, 'message' => 'Coupon deleted successfully'];
    }

    // ========== SETTINGS MANAGEMENT ==========
    
    /**
     * Get system settings
     */
    public function get_settings()
    {
        $settings = $this->db->get('settings')->result_array();
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting['type']] = $setting['description'];
        }
        return $formatted;
    }
    
    /**
     * Update settings
     */
    public function update_settings($data)
    {
        foreach ($data as $key => $value) {
            $exists = $this->db->get_where('settings', ['type' => $key])->num_rows();
            if ($exists > 0) {
                $this->db->where('type', $key)->update('settings', ['description' => $value]);
            } else {
                $this->db->insert('settings', ['type' => $key, 'description' => $value]);
            }
        }
        
        return ['status' => true, 'message' => 'Settings updated successfully'];
    }

    // ========== REPORTS ==========
    
    /**
     * Get sales report
     */
    public function get_sales_report($params = [])
    {
        $date_from = $params['date_from'] ?? date('Y-m-01');
        $date_to = $params['date_to'] ?? date('Y-m-d');
        
        $this->db->select('DATE(FROM_UNIXTIME(date_added)) as date, SUM(amount) as total_sales, COUNT(*) as total_orders');
        $this->db->from('payment');
        $this->db->where('date_added >=', strtotime($date_from));
        $this->db->where('date_added <=', strtotime($date_to . ' 23:59:59'));
        $this->db->group_by('DATE(FROM_UNIXTIME(date_added))');
        $this->db->order_by('date', 'ASC');
        
        $report = $this->db->get()->result_array();
        
        // Summary
        $summary = $this->db->select_sum('amount', 'total_revenue')
            ->select('COUNT(*) as total_transactions', false)
            ->where('date_added >=', strtotime($date_from))
            ->where('date_added <=', strtotime($date_to . ' 23:59:59'))
            ->get('payment')->row_array();
        
        return [
            'report' => $report,
            'summary' => $summary,
            'date_from' => $date_from,
            'date_to' => $date_to
        ];
    }
    
    /**
     * Get users report
     */
    public function get_users_report($params = [])
    {
        $date_from = $params['date_from'] ?? date('Y-m-01');
        $date_to = $params['date_to'] ?? date('Y-m-d');
        
        $this->db->select('DATE(FROM_UNIXTIME(date_added)) as date, COUNT(*) as new_users');
        $this->db->from('users');
        $this->db->where('date_added >=', strtotime($date_from));
        $this->db->where('date_added <=', strtotime($date_to . ' 23:59:59'));
        $this->db->group_by('DATE(FROM_UNIXTIME(date_added))');
        $this->db->order_by('date', 'ASC');
        
        $report = $this->db->get()->result_array();
        
        return [
            'report' => $report,
            'date_from' => $date_from,
            'date_to' => $date_to
        ];
    }
    
    /**
     * Get courses report
     */
    public function get_courses_report($params = [])
    {
        // Top courses by enrollment
        $top_by_enrollment = $this->db->select('c.id, c.title, COUNT(e.id) as total_enrollments')
            ->from('course c')
            ->join('enrol e', 'e.course_id = c.id', 'left')
            ->group_by('c.id')
            ->order_by('total_enrollments', 'DESC')
            ->limit(10)
            ->get()
            ->result_array();
        
        // Top courses by revenue
        $top_by_revenue = $this->db->select('c.id, c.title, SUM(p.amount) as total_revenue')
            ->from('course c')
            ->join('payment p', 'p.course_id = c.id', 'left')
            ->group_by('c.id')
            ->order_by('total_revenue', 'DESC')
            ->limit(10)
            ->get()
            ->result_array();
        
        return [
            'top_by_enrollment' => $top_by_enrollment,
            'top_by_revenue' => $top_by_revenue
        ];
    }

    // ========== INSTRUCTOR REQUESTS ==========
    
    /**
     * Get instructor applications
     */
    public function get_instructor_applications($params = [])
    {
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $limit = isset($params['limit']) ? intval($params['limit']) : 20;
        $offset = ($page - 1) * $limit;
        
        $this->db->select('u.id, u.first_name, u.last_name, u.email, u.date_added, a.status, a.document, a.created_at');
        $this->db->from('instructor_applications a');
        $this->db->join('users u', 'u.id = a.user_id', 'left');
        
        if (!empty($params['status'])) {
            $this->db->where('a.status', $params['status']);
        }
        
        $total = $this->db->count_all_results('', false);
        
        $applications = $this->db->order_by('a.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        
        return [
            'applications' => $applications,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Approve/Reject instructor application
     */
    public function handle_instructor_application($user_id, $action)
    {
        if ($action == 'approve') {
            $this->db->where('id', $user_id)->update('users', ['is_instructor' => 1]);
            $this->db->where('user_id', $user_id)->update('instructor_applications', ['status' => 'approved']);
            return ['status' => true, 'message' => 'Application approved'];
        } else if ($action == 'reject') {
            $this->db->where('user_id', $user_id)->update('instructor_applications', ['status' => 'rejected']);
            return ['status' => true, 'message' => 'Application rejected'];
        }
        
        return ['status' => false, 'message' => 'Invalid action'];
    }
}
