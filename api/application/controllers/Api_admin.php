<?php
require APPPATH . '/libraries/TokenHandler.php';
require APPPATH . 'libraries/REST_Controller.php';

/**
 * Admin API Controller
 * 
 * RESTful API for admin dashboard operations
 * All endpoints require admin authentication
 * 
 * @package Academy LMS
 * @version 2.0
 */
class Api_admin extends REST_Controller
{
    protected $tokenHandler;
    
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->load->model('api_admin_model');
        $this->tokenHandler = new TokenHandler();
        
        // CORS Headers — single source of truth in common_helper.php
        apply_api_cors();
    }

    // ========== DASHBOARD ==========
    
    /**
     * GET /api_admin/dashboard
     * Get dashboard statistics
     */
    public function dashboard_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $stats = $this->api_admin_model->get_dashboard_stats();
        return $this->response(['status' => true, 'data' => $stats], 200);
    }

    // ========== USERS MANAGEMENT ==========
    
    /**
     * GET /api_admin/users
     * Get users list with filters
     */
    public function users_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->get_users($this->input->get());
        return $this->response(['status' => true, 'data' => $result['users'], 'pagination' => $result['pagination']], 200);
    }
    
    /**
     * GET /api_admin/user/{id}
     * Get single user details
     */
    public function user_get($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'User ID required'], 400);
        
        $user = $this->api_admin_model->get_user($id);
        if (!$user) return $this->response(['status' => false, 'message' => 'User not found'], 404);
        
        return $this->response(['status' => true, 'data' => $user], 200);
    }
    
    /**
     * POST /api_admin/user
     * Create new user
     */
    public function user_post()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->create_user($this->input->post());
        return $this->response($result, $result['status'] ? 201 : 400);
    }
    
    /**
     * PUT /api_admin/user/{id}
     * Update user
     */
    public function user_put($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'User ID required'], 400);
        
        $result = $this->api_admin_model->update_user($id, $this->put());
        return $this->response($result, $result['status'] ? 200 : 400);
    }
    
    /**
     * DELETE /api_admin/user/{id}
     * Delete user
     */
    public function user_delete($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'User ID required'], 400);
        
        $result = $this->api_admin_model->delete_user($id);
        return $this->response($result, $result['status'] ? 200 : 400);
    }

    // ========== COURSES MANAGEMENT ==========
    
    /**
     * GET /api_admin/courses
     * Get courses list
     */
    public function courses_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->get_courses($this->input->get());
        return $this->response(['status' => true, 'data' => $result['courses'], 'pagination' => $result['pagination']], 200);
    }
    
    /**
     * GET /api_admin/course/{id}
     * Get single course details
     */
    public function course_get($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        
        $course = $this->api_admin_model->get_course($id);
        if (!$course) return $this->response(['status' => false, 'message' => 'Course not found'], 404);
        
        return $this->response(['status' => true, 'data' => $course], 200);
    }
    
    /**
     * PUT /api_admin/course/{id}/status
     * Update course status
     */
    public function course_status_put($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        
        $data = $this->put();
        $result = $this->api_admin_model->update_course_status($id, $data['status'] ?? '');
        return $this->response($result, $result['status'] ? 200 : 400);
    }
    
    /**
     * DELETE /api_admin/course/{id}
     * Delete course
     */
    public function course_delete($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        
        $result = $this->api_admin_model->delete_course($id);
        return $this->response($result, $result['status'] ? 200 : 400);
    }

    // ========== CATEGORIES MANAGEMENT ==========
    
    /**
     * GET /api_admin/categories
     * Get all categories
     */
    public function categories_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $categories = $this->api_admin_model->get_categories();
        return $this->response(['status' => true, 'data' => $categories], 200);
    }
    
    /**
     * GET /api_admin/category/{id}
     * Get single category
     */
    public function category_get($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Category ID required'], 400);
        
        $category = $this->api_admin_model->get_category($id);
        if (!$category) return $this->response(['status' => false, 'message' => 'Category not found'], 404);
        
        return $this->response(['status' => true, 'data' => $category], 200);
    }
    
    /**
     * POST /api_admin/category
     * Create category
     */
    public function category_post()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->create_category($this->input->post());
        return $this->response($result, $result['status'] ? 201 : 400);
    }
    
    /**
     * PUT /api_admin/category/{id}
     * Update category
     */
    public function category_put($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Category ID required'], 400);
        
        $result = $this->api_admin_model->update_category($id, $this->put());
        return $this->response($result, $result['status'] ? 200 : 400);
    }
    
    /**
     * DELETE /api_admin/category/{id}
     * Delete category
     */
    public function category_delete($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Category ID required'], 400);
        
        $result = $this->api_admin_model->delete_category($id);
        return $this->response($result, $result['status'] ? 200 : 400);
    }

    // ========== PAYMENTS MANAGEMENT ==========
    
    /**
     * GET /api_admin/payments
     * Get payments list
     */
    public function payments_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->get_payments($this->input->get());
        return $this->response(['status' => true, 'data' => $result['payments'], 'pagination' => $result['pagination']], 200);
    }
    
    /**
     * GET /api_admin/payment/{id}
     * Get single payment
     */
    public function payment_get($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Payment ID required'], 400);
        
        $payment = $this->api_admin_model->get_payment($id);
        if (!$payment) return $this->response(['status' => false, 'message' => 'Payment not found'], 404);
        
        return $this->response(['status' => true, 'data' => $payment], 200);
    }

    // ========== ENROLLMENTS MANAGEMENT ==========
    
    /**
     * GET /api_admin/enrollments
     * Get enrollments list
     */
    public function enrollments_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->get_enrollments($this->input->get());
        return $this->response(['status' => true, 'data' => $result['enrollments'], 'pagination' => $result['pagination']], 200);
    }
    
    /**
     * POST /api_admin/enrollment
     * Create manual enrollment
     */
    public function enrollment_post()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->create_enrollment($this->input->post());
        return $this->response($result, $result['status'] ? 201 : 400);
    }
    
    /**
     * DELETE /api_admin/enrollment/{id}
     * Delete enrollment
     */
    public function enrollment_delete($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Enrollment ID required'], 400);
        
        $result = $this->api_admin_model->delete_enrollment($id);
        return $this->response($result, $result['status'] ? 200 : 400);
    }

    // ========== PAYOUTS MANAGEMENT ==========
    
    /**
     * GET /api_admin/payouts
     * Get payouts list
     */
    public function payouts_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->get_payouts($this->input->get());
        return $this->response(['status' => true, 'data' => $result['payouts'], 'pagination' => $result['pagination']], 200);
    }
    
    /**
     * PUT /api_admin/payout/{id}/status
     * Update payout status
     */
    public function payout_status_put($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Payout ID required'], 400);
        
        $data = $this->put();
        $result = $this->api_admin_model->update_payout_status($id, $data['status'] ?? '');
        return $this->response($result, $result['status'] ? 200 : 400);
    }

    // ========== COUPONS MANAGEMENT ==========
    
    /**
     * GET /api_admin/coupons
     * Get coupons list
     */
    public function coupons_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $coupons = $this->api_admin_model->get_coupons();
        return $this->response(['status' => true, 'data' => $coupons], 200);
    }
    
    /**
     * GET /api_admin/coupon/{id}
     * Get single coupon
     */
    public function coupon_get($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Coupon ID required'], 400);
        
        $coupon = $this->api_admin_model->get_coupon($id);
        if (!$coupon) return $this->response(['status' => false, 'message' => 'Coupon not found'], 404);
        
        return $this->response(['status' => true, 'data' => $coupon], 200);
    }
    
    /**
     * POST /api_admin/coupon
     * Create coupon
     */
    public function coupon_post()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->create_coupon($this->input->post());
        return $this->response($result, $result['status'] ? 201 : 400);
    }
    
    /**
     * PUT /api_admin/coupon/{id}
     * Update coupon
     */
    public function coupon_put($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Coupon ID required'], 400);
        
        $result = $this->api_admin_model->update_coupon($id, $this->put());
        return $this->response($result, $result['status'] ? 200 : 400);
    }
    
    /**
     * DELETE /api_admin/coupon/{id}
     * Delete coupon
     */
    public function coupon_delete($id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$id) return $this->response(['status' => false, 'message' => 'Coupon ID required'], 400);
        
        $result = $this->api_admin_model->delete_coupon($id);
        return $this->response($result, $result['status'] ? 200 : 400);
    }

    // ========== SETTINGS ==========
    
    /**
     * GET /api_admin/settings
     * Get system settings
     */
    public function settings_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $settings = $this->api_admin_model->get_settings();
        return $this->response(['status' => true, 'data' => $settings], 200);
    }
    
    /**
     * PUT /api_admin/settings
     * Update system settings
     */
    public function settings_put()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->update_settings($this->put());
        return $this->response($result, $result['status'] ? 200 : 400);
    }

    // ========== REPORTS ==========
    
    /**
     * GET /api_admin/reports/sales
     * Get sales report
     */
    public function reports_sales_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $report = $this->api_admin_model->get_sales_report($this->input->get());
        return $this->response(['status' => true, 'data' => $report], 200);
    }
    
    /**
     * GET /api_admin/reports/users
     * Get users report
     */
    public function reports_users_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $report = $this->api_admin_model->get_users_report($this->input->get());
        return $this->response(['status' => true, 'data' => $report], 200);
    }
    
    /**
     * GET /api_admin/reports/courses
     * Get courses report
     */
    public function reports_courses_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $report = $this->api_admin_model->get_courses_report($this->input->get());
        return $this->response(['status' => true, 'data' => $report], 200);
    }

    // ========== INSTRUCTOR APPLICATIONS ==========
    
    /**
     * GET /api_admin/instructor_applications
     * Get instructor applications
     */
    public function instructor_applications_get()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $result = $this->api_admin_model->get_instructor_applications($this->input->get());
        return $this->response(['status' => true, 'data' => $result['applications'], 'pagination' => $result['pagination']], 200);
    }
    
    /**
     * POST /api_admin/instructor_application/{user_id}/action
     * Approve or reject instructor application
     */
    public function instructor_application_action_post($user_id = null)
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        if (!$user_id) return $this->response(['status' => false, 'message' => 'User ID required'], 400);
        
        $action = $this->input->post('action'); // approve or reject
        $result = $this->api_admin_model->handle_instructor_application($user_id, $action);
        return $this->response($result, $result['status'] ? 200 : 400);
    }

    // ========== ADMIN LOGIN ==========
    
    /**
     * POST /api_admin/login
     * Admin login
     */
    public function login_post()
    {
        $email = $this->input->post('email');
        $password = $this->input->post('password');
        
        if (empty($email) || empty($password)) {
            return $this->response(['status' => false, 'message' => 'Email and password required'], 400);
        }
        
        $user = $this->db->get_where('users', [
            'email' => $email,
            'role_id' => 1, // Admin only
            'status' => 1
        ])->row_array();
        
        $password_ok = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $password_ok = true;
            } elseif ($user['password'] === sha1($password)) {
                $password_ok = true;
                $this->db->where('id', $user['id'])->update('users', ['password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);
            }
        }
        
        if ($password_ok) {
            $userdata = [
                'user_id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'role_id' => $user['role_id'],
                'is_admin' => true
            ];
            $userdata['token'] = $this->tokenHandler->GenerateToken($userdata);
            return $this->response(['status' => true, 'data' => $userdata], 200);
        }
        
        return $this->response(['status' => false, 'message' => 'Invalid credentials or not an admin'], 401);
    }
    
    /**
     * POST /api_admin/refresh_token
     * Refresh admin token
     */
    public function refresh_token_post()
    {
        $admin = $this->authenticate_admin();
        if (!$admin) return;
        
        $new_token = $this->tokenHandler->GenerateToken($admin);
        return $this->response(['status' => true, 'token' => $new_token], 200);
    }

    // ========== HELPER METHODS ==========
    
    /**
     * Authenticate admin user
     */
    private function authenticate_admin()
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
            
            // Verify user is admin
            if (!isset($decoded['role_id']) || $decoded['role_id'] != 1) {
                $this->response(['status' => false, 'message' => 'Admin access required'], 403);
                return false;
            }
            
            // Verify user still exists and is active
            $user = $this->db->get_where('users', ['id' => $decoded['user_id'], 'role_id' => 1, 'status' => 1])->row_array();
            if (!$user) {
                $this->response(['status' => false, 'message' => 'Admin account not found or inactive'], 403);
                return false;
            }
            
            return $decoded;
        } catch (Exception $e) {
            $this->response(['status' => false, 'message' => 'Invalid or expired token'], 401);
            return false;
        }
    }
}
