<?php
require APPPATH . '/libraries/TokenHandler.php';
require APPPATH . 'libraries/REST_Controller.php';

class Api_frontend extends REST_Controller
{

    protected $token;

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->load->model('api_frontend_model');
        $this->tokenHandler = new TokenHandler();

        // CORS Headers — single source of truth in common_helper.php
        apply_api_cors();
        // JSON request bodies are merged into $_POST centrally by REST_Controller::__construct().
    }

    // ========== AUTH ==========
    public function login_post()
    {
        $email = $this->input->post('email');
        $password = $this->input->post('password');

        if (empty($email) || empty($password)) {
            return $this->set_response(['status' => false, 'message' => 'Email and password required'], 400);
        }

        // --- Rate limiting / brute-force protection ---
        $ip = $this->input->ip_address();
        $window = 15 * 60;   // 15 minutes
        $max_attempts = 5;   // lock after 5 failed attempts from the same IP
        $now = time();
        $this->db->where('ip', $ip)->where('attempted_at >=', $now - $window);
        $recent_failures = $this->db->count_all_results('login_attempts');
        if ($recent_failures >= $max_attempts) {
            return $this->set_response(['status' => false, 'message' => 'Too many failed login attempts. Please try again in about 15 minutes.'], 429);
        }

        // H-06 fix: fetch user by email first, then verify password
        $user = $this->db->get_where('users', ['email' => $email])->row_array();

        $password_ok = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $password_ok = true;
            } elseif ($user['password'] === sha1($password)) {
                $password_ok = true;
                // Upgrade hash to bcrypt on successful sha1 login
                $this->db->where('id', $user['id'])->update('users', ['password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);
            }
        }

        if ($password_ok) {
            if ($user['status'] == 0) {
                return $this->set_response(['status' => false, 'message' => 'Please verify your email first. Check your inbox for the verification code.'], 403);
            }
            // Successful login — clear this IP's failed attempts
            $this->db->where('ip', $ip)->delete('login_attempts');
            $userdata = $this->api_frontend_model->format_user($user);
            $userdata['token'] = $this->tokenHandler->GenerateToken($userdata);
            return $this->set_response(['status' => true, 'data' => $userdata], 200);
        }

        // Record the failed attempt
        $this->db->insert('login_attempts', ['ip' => $ip, 'email' => $email, 'attempted_at' => $now]);
        $remaining = $max_attempts - ($recent_failures + 1);
        $msg = 'Invalid email or password';
        if ($remaining <= 2 && $remaining > 0) {
            $msg .= ". You have {$remaining} attempt(s) left before a temporary lock.";
        }
        return $this->set_response(['status' => false, 'message' => $msg], 200);
    }

    public function login_with_otp_post()
    {
        $email = $this->input->post('email');
        if (empty($email)) {
            return $this->set_response(['status' => false, 'message' => 'Email required'], 400);
        }
        $result = $this->api_frontend_model->send_login_otp($email);
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function verify_login_otp_post()
    {
        $email = $this->input->post('email');
        $otp = $this->input->post('otp');
        if (empty($email) || empty($otp)) {
            return $this->set_response(['status' => false, 'message' => 'Email and OTP required'], 400);
        }
        $result = $this->api_frontend_model->verify_login_otp($email, $otp);
        if ($result['status']) {
            $result['data']['token'] = $this->tokenHandler->GenerateToken($result['data']);
        }
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function register_post()
    {
        $first_name = trim($this->input->post('first_name'));
        $last_name = trim($this->input->post('last_name'));
        $email = trim($this->input->post('email'));
        $password = $this->input->post('password');

        if (empty($first_name) || empty($email) || empty($password)) {
            return $this->set_response(['status' => false, 'message' => 'First name, email and password are required'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->set_response(['status' => false, 'message' => 'Invalid email format'], 400);
        }

        // Enforce a strong password policy (min 8 chars, upper + lower + number)
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $this->set_response(['status' => false, 'message' => 'Password must be at least 8 characters and include uppercase, lowercase and a number.'], 400);
        }

        // M-03 fix: Unified response to prevent account enumeration
        if ($this->db->get_where('users', ['email' => $email])->num_rows() > 0) {
            // Send OTP anyway to the existing email to mimic the "requires verification" flow,
            // or just return success with "If this email is not registered..." message.
            // For registration, we can just say "If this email is not registered, you will receive a verification code."
            // But standard practice for register is to just say "Email already exists" with a generic message.
            return $this->set_response(['status' => false, 'message' => 'If this email is not registered, you will receive a verification code. Otherwise, please login.'], 400);
        }

        $data = [
            'first_name' => $first_name,
            'last_name'  => $last_name ?: '',
            'email'      => $email,
            // H-06 fix: bcrypt instead of sha1
            'password'   => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role_id'    => 2,
            'status'     => get_settings('student_email_verification') == 'enable' ? 0 : 1,
            'date_added' => time(),
            'wishlist'   => json_encode([]),
            'social_links'  => json_encode(['facebook' => '', 'twitter' => '', 'linkedin' => '']),
            'payment_keys'  => json_encode([]),
            // C-03 fix: random_int instead of rand
            'verification_code' => random_int(100000, 999999)
        ];

        if (!$this->db->insert('users', $data)) {
            $error = $this->db->error();
            return $this->set_response(['status' => false, 'message' => 'Registration failed. Please try again.'], 500);
        }

        $user_id = $this->db->insert_id();

        if (get_settings('student_email_verification') == 'enable') {
            $this->api_frontend_model->send_verification_otp($email);
            return $this->set_response(['status' => true, 'message' => 'Registration successful. Please verify your email.', 'data' => ['user_id' => $user_id, 'requires_verification' => true]], 201);
        }
        return $this->set_response(['status' => true, 'message' => 'Registration successful', 'data' => ['user_id' => $user_id, 'requires_verification' => false]], 201);
    }

    // ----- OTP rate-limiting helper (C-03, M-03) -----
    // Max $max_req requests per $window_seconds from the same IP.
    private function check_otp_rate_limit($max_req = 5, $window_seconds = 900)
    {
        $ip  = $this->input->ip_address();
        $now = time();
        $this->db->where('ip', $ip)->where('route', 'otp')->where('attempted_at >=', $now - $window_seconds);
        $count = $this->db->count_all_results('login_attempts');
        if ($count >= $max_req) {
            return false;
        }
        $this->db->insert('login_attempts', ['ip' => $ip, 'email' => '', 'route' => 'otp', 'attempted_at' => $now]);
        return true;
    }

    public function send_otp_post()
    {
        // C-03 / M-03: rate-limit OTP sends (max 5 per 15 min per IP)
        if (!$this->check_otp_rate_limit()) {
            return $this->set_response(['status' => false, 'message' => 'Too many OTP requests. Please wait 15 minutes.'], 429);
        }
        $email = $this->input->post('email');
        $type = $this->input->post('type') ?: 'verification';
        if (empty($email)) {
            return $this->set_response(['status' => false, 'message' => 'Email required'], 400);
        }
        $result = $this->api_frontend_model->send_otp($email, $type);
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function verify_otp_post()
    {
        $email = $this->input->post('email');
        $otp   = $this->input->post('otp');
        $type  = $this->input->post('type') ?: 'verification';
        if (empty($email) || empty($otp)) {
            return $this->set_response(['status' => false, 'message' => 'Email and OTP required'], 400);
        }
        $result = $this->api_frontend_model->verify_otp($email, $otp, $type);
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function resend_otp_post()
    {
        // C-03 / M-03: rate-limit resend too
        if (!$this->check_otp_rate_limit()) {
            return $this->set_response(['status' => false, 'message' => 'Too many OTP requests. Please wait 15 minutes.'], 429);
        }
        $email = $this->input->post('email');
        $type  = $this->input->post('type') ?: 'verification';
        if (empty($email)) {
            return $this->set_response(['status' => false, 'message' => 'Email required'], 400);
        }
        $result = $this->api_frontend_model->send_otp($email, $type);
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function reset_password_post()
    {
        $email        = $this->input->post('email');
        $otp          = $this->input->post('otp');
        $new_password = $this->input->post('new_password');
        if (empty($email) || empty($otp) || empty($new_password)) {
            return $this->set_response(['status' => false, 'message' => 'Email, OTP and new password required'], 400);
        }
        $result = $this->api_frontend_model->reset_password_with_otp($email, $otp, $new_password);
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function forgot_password_post()
    {
        // M-03 fix: rate-limit + always return same message (account enumeration)
        if (!$this->check_otp_rate_limit()) {
            return $this->set_response(['status' => false, 'message' => 'Too many requests. Please wait 15 minutes.'], 429);
        }
        $email = $this->input->post('email');
        if (empty($email)) {
            return $this->set_response(['status' => false, 'message' => 'Email required'], 400);
        }
        // Always send same success response regardless of whether email exists (M-03)
        $this->api_frontend_model->send_otp($email, 'reset_password');
        return $this->set_response(['status' => true, 'message' => 'If this email is registered, you will receive a password reset code.'], 200);
    }

    public function social_login_post()
    {
        $provider = $this->input->post('provider');
        $social_id = $this->input->post('social_id');
        $email = $this->input->post('email');
        $first_name = $this->input->post('first_name');
        $last_name = $this->input->post('last_name');
        $image = $this->input->post('image');

        if (empty($provider) || empty($social_id)) {
            return $this->set_response(['status' => false, 'message' => 'Provider and social_id required'], 400);
        }
        $result = $this->api_frontend_model->social_login($provider, $social_id, $email, $first_name, $last_name, $image);
        if ($result['status']) {
            $result['data']['token'] = $this->tokenHandler->GenerateToken($result['data']);
        }
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== COURSES ==========
    public function courses_get()
    {
        $result = $this->api_frontend_model->get_courses($this->input->get());
        return $this->set_response(['status' => true, 'data' => $result['courses'], 'pagination' => $result['pagination']], 200);
    }

    public function course_get($id = null)
    {
        if (!$id) return $this->set_response(['status' => false, 'message' => 'Course ID required'], 400);
        // Optional auth: if a valid token is sent we personalise is_purchased / is_wishlisted,
        // otherwise the course is still returned for guests (both flags false).
        $user_id = $this->optional_user_id();
        $course = $this->api_frontend_model->get_course_details($id, $user_id);
        if (!$course) return $this->set_response(['status' => false, 'message' => 'Course not found'], 404);
        return $this->set_response(['status' => true, 'data' => $course], 200);
    }

    public function top_courses_get()
    {
        $courses = $this->api_frontend_model->get_top_courses($this->input->get('limit') ?: 8);
        return $this->set_response(['status' => true, 'data' => $courses], 200);
    }

    public function latest_courses_get()
    {
        $courses = $this->api_frontend_model->get_latest_courses($this->input->get('limit') ?: 8);
        return $this->set_response(['status' => true, 'data' => $courses], 200);
    }

    public function free_courses_get()
    {
        $courses = $this->api_frontend_model->get_free_courses($this->input->get('limit') ?: 8);
        return $this->set_response(['status' => true, 'data' => $courses], 200);
    }

    // ========== CATEGORIES ==========
    public function categories_get()
    {
        $categories = $this->api_frontend_model->get_categories();
        return $this->set_response(['status' => true, 'data' => $categories], 200);
    }

    public function category_get($id = null)
    {
        if (!$id) return $this->set_response(['status' => false, 'message' => 'Category ID required'], 400);
        $category = $this->api_frontend_model->get_category($id);
        if (!$category) return $this->set_response(['status' => false, 'message' => 'Category not found'], 404);
        return $this->set_response(['status' => true, 'data' => $category], 200);
    }

    public function category_courses_get($id = null)
    {
        if (!$id) return $this->set_response(['status' => false, 'message' => 'Category ID required'], 400);
        $courses = $this->api_frontend_model->get_category_courses($id);
        if ($courses === false) return $this->set_response(['status' => false, 'message' => 'Category not found'], 404);
        return $this->set_response(['status' => true, 'data' => $courses], 200);
    }

    // ========== INSTRUCTORS ==========
    public function instructors_get()
    {
        $result = $this->api_frontend_model->get_instructors($this->input->get());
        return $this->set_response(['status' => true, 'data' => $result['instructors'], 'pagination' => $result['pagination']], 200);
    }

    public function instructor_get($id = null)
    {
        if (!$id) return $this->set_response(['status' => false, 'message' => 'Instructor ID required'], 400);
        $instructor = $this->api_frontend_model->get_instructor_details($id);
        if (!$instructor) return $this->set_response(['status' => false, 'message' => 'Instructor not found'], 404);
        return $this->set_response(['status' => true, 'data' => $instructor], 200);
    }

    // ========== TESTIMONIALS (real student reviews of the academy) ==========
    public function testimonials_get()
    {
        $limit = (int) ($this->input->get('limit') ?: 10);
        $testimonials = $this->api_frontend_model->get_testimonials($limit);
        return $this->set_response(['status' => true, 'data' => $testimonials], 200);
    }

    // ========== USER PROFILE (Protected) ==========
    public function profile_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $profile = $this->api_frontend_model->get_user_profile($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $profile], 200);
    }

    public function profile_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $profile_result = $this->api_frontend_model->update_user_profile($user['user_id'], $this->input->post());
        if (is_array($profile_result) && ($profile_result['status'] ?? true) === false) {
            return $this->set_response(['status' => false, 'message' => $profile_result['message']], 400);
        }

        // Optional avatar upload (multipart file "image"/"user_image" or base64 "image")
        $image_result = $this->api_frontend_model->save_profile_image($user['user_id']);
        if (is_array($image_result) && $image_result['status'] === false) {
            return $this->set_response(['status' => false, 'message' => $image_result['message']], 400);
        }

        $response = ['status' => true, 'message' => 'Profile updated'];
        if (is_array($image_result)) {
            $response['data'] = ['image' => $image_result['image']];
        }
        return $this->set_response($response, 200);
    }

    public function change_password_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->change_password($user['user_id'], $this->input->post('current_password'), $this->input->post('new_password'));
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== MY COURSES (Protected) ==========
    public function my_courses_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $courses = $this->api_frontend_model->get_user_courses($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $courses], 200);
    }

    public function enroll_free_course_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->enroll_free_course($user['user_id'], $this->input->post('course_id'));
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== WISHLIST (Protected) ==========
    public function wishlist_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $wishlist = $this->api_frontend_model->get_wishlist($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $wishlist], 200);
    }

    public function wishlist_toggle_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->toggle_wishlist($user['user_id'], $this->input->post('course_id'));
        return $this->set_response($result, 200);
    }

    // ========== CART (Protected) ==========
    public function cart_get()
    {
        $user = $this->authenticate(false);
        if (!$user) return $this->set_response(['status' => true, 'data' => []], 200);
        $cart = $this->api_frontend_model->get_cart($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $cart], 200);
    }

    public function cart_add_post()
    {
        $user = $this->authenticate(false);
        if (!$user) return $this->set_response(['status' => false, 'message' => 'Please log in first to add courses to your cart.', 'require_login' => true], 401);
        $result = $this->api_frontend_model->add_to_cart($user['user_id'], $this->input->post('course_id'));
        return $this->set_response($result, 200);
    }

    public function cart_remove_post()
    {
        $user = $this->authenticate(false);
        if (!$user) return $this->set_response(['status' => true, 'message' => 'Removed (Guest)', 'data' => ['cart_count' => 0]], 200);
        $result = $this->api_frontend_model->remove_from_cart($user['user_id'], $this->input->post('course_id'));
        return $this->set_response($result, 200);
    }

    // ========== LESSONS (Protected) ==========
    public function course_sections_get($course_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        $sections = $this->api_frontend_model->get_course_sections($user['user_id'], $course_id);
        if (isset($sections['error'])) return $this->set_response(['status' => false, 'message' => $sections['error']], 403);
        return $this->set_response(['status' => true, 'data' => $sections], 200);
    }

    public function lesson_get($lesson_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        $lesson = $this->api_frontend_model->get_lesson($user['user_id'], $lesson_id);
        if (isset($lesson['error'])) return $this->set_response(['status' => false, 'message' => $lesson['error']], 403);
        return $this->set_response(['status' => true, 'data' => $lesson], 200);
    }

    public function lesson_complete_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->mark_lesson_complete($user['user_id'], $this->input->post('lesson_id'));
        return $this->set_response($result, 200);
    }

    // ========== REVIEWS ==========
    public function course_reviews_get($course_id = null)
    {
        if (!$course_id) return $this->set_response(['status' => false, 'message' => 'Course ID required'], 400);
        $reviews = $this->api_frontend_model->get_course_reviews($course_id);
        return $this->set_response(['status' => true, 'data' => $reviews], 200);
    }

    public function course_review_post($course_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->add_course_review($user['user_id'], $course_id, $this->input->post('rating'), $this->input->post('review'));
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // Public "Notify Me" email capture for upcoming courses (no login required)
    public function notify_me_post()
    {
        $course_id = $this->input->post('course_id');
        $email = $this->input->post('email');
        $name = $this->input->post('name');
        if (!$email) {
            $body = json_decode($this->input->raw_input_stream, true);
            if (is_array($body)) {
                $course_id = $course_id ?: ($body['course_id'] ?? null);
                $email = $email ?: ($body['email'] ?? null);
                $name = $name ?: ($body['name'] ?? null);
            }
        }
        if (!$email) return $this->set_response(['status' => false, 'message' => 'Email is required'], 400);
        $result = $this->api_frontend_model->add_notify_me($course_id, $email, $name);
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== BLOG ==========
    public function blogs_get()
    {
        $result = $this->api_frontend_model->get_blogs($this->input->get());
        return $this->set_response(['status' => true, 'data' => $result['blogs'], 'pagination' => $result['pagination']], 200);
    }

    public function blog_get($id = null)
    {
        if (!$id) return $this->set_response(['status' => false, 'message' => 'Blog ID required'], 400);
        $blog = $this->api_frontend_model->get_blog_details($id);
        if (!$blog) return $this->set_response(['status' => false, 'message' => 'Blog not found'], 404);
        return $this->set_response(['status' => true, 'data' => $blog], 200);
    }

    public function blog_categories_get()
    {
        $categories = $this->api_frontend_model->get_blog_categories();
        return $this->set_response(['status' => true, 'data' => $categories], 200);
    }

    // ========== SETTINGS & PAGES ==========
    public function settings_get()
    {
        $settings = $this->api_frontend_model->get_settings();
        return $this->set_response(['status' => true, 'data' => $settings], 200);
    }

    public function page_get($slug = null)
    {
        if (!$slug) return $this->set_response(['status' => false, 'message' => 'Page slug required'], 400);
        $page = $this->api_frontend_model->get_page($slug);
        if (!$page) return $this->set_response(['status' => false, 'message' => 'Page not found'], 404);
        return $this->set_response(['status' => true, 'data' => $page], 200);
    }

    public function faqs_get()
    {
        $faqs = json_decode(get_frontend_settings('website_faqs')) ?: [];
        return $this->set_response(['status' => true, 'data' => $faqs], 200);
    }

    public function languages_get()
    {
        $languages = $this->api_frontend_model->get_languages();
        return $this->set_response(['status' => true, 'data' => $languages], 200);
    }

    // ========== CONTACT ==========
    public function contact_post()
    {
        $result = $this->api_frontend_model->submit_contact($this->input->post());
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== SEARCH ==========
    public function search_get()
    {
        $query = $this->input->get('q');
        if (empty($query)) return $this->set_response(['status' => false, 'message' => 'Search query required'], 400);
        $results = $this->api_frontend_model->search($query);
        return $this->set_response(['status' => true, 'data' => $results], 200);
    }

    // ========== COUPON ==========
    public function apply_coupon_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->apply_coupon($this->input->post('code'));
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== HOME PAGE DATA ==========
    public function home_get()
    {
        $data = $this->api_frontend_model->get_home_data();
        return $this->set_response(['status' => true, 'data' => $data], 200);
    }

    // ========== UPCOMING COURSES ==========
    public function upcoming_courses_get()
    {
        $courses = $this->api_frontend_model->get_upcoming_courses($this->input->get('limit') ?: 8);
        return $this->set_response(['status' => true, 'data' => $courses], 200);
    }

    // ========== DISCOUNTED COURSES ==========
    public function discounted_courses_get()
    {
        $courses = $this->api_frontend_model->get_discounted_courses($this->input->get('limit') ?: 8);
        return $this->set_response(['status' => true, 'data' => $courses], 200);
    }

    // ========== SUB CATEGORIES ==========
    public function sub_categories_get($parent_id = null)
    {
        if (!$parent_id) return $this->set_response(['status' => false, 'message' => 'Parent category ID required'], 400);
        $sub_categories = $this->api_frontend_model->get_sub_categories($parent_id);
        return $this->set_response(['status' => true, 'data' => $sub_categories], 200);
    }

    // ========== TOP INSTRUCTORS ==========
    public function top_instructors_get()
    {
        $instructors = $this->api_frontend_model->get_top_instructors($this->input->get('limit') ?: 8);
        return $this->set_response(['status' => true, 'data' => $instructors], 200);
    }

    // ========== FOLLOW INSTRUCTOR (Protected) ==========
    public function follow_instructor_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->toggle_follow_instructor($user['user_id'], $this->input->post('instructor_id'));
        return $this->set_response($result, 200);
    }

    public function following_instructors_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $instructors = $this->api_frontend_model->get_following_instructors($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $instructors], 200);
    }

    // ========== COURSE CURRICULUM (Public Preview) ==========
    public function course_curriculum_get($course_id = null)
    {
        if (!$course_id) return $this->set_response(['status' => false, 'message' => 'Course ID required'], 400);
        $curriculum = $this->api_frontend_model->get_course_curriculum_preview($course_id);
        return $this->set_response(['status' => true, 'data' => $curriculum], 200);
    }

    // ========== NEWSLETTER ==========
    public function newsletter_subscribe_post()
    {
        $result = $this->api_frontend_model->subscribe_newsletter($this->input->post('email'));
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== NOTIFICATIONS (Protected) ==========
    public function notifications_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $notifications = $this->api_frontend_model->get_notifications($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $notifications], 200);
    }

    public function notification_read_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->mark_notification_read($user['user_id'], $this->input->post('notification_id'));
        return $this->set_response($result, 200);
    }

    public function notifications_read_all_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->mark_all_notifications_read($user['user_id']);
        return $this->set_response($result, 200);
    }

    // ========== MESSAGES (Protected) ==========
    public function messages_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $messages = $this->api_frontend_model->get_message_threads($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $messages], 200);
    }

    public function message_thread_get($thread_code = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$thread_code) return $this->set_response(['status' => false, 'message' => 'Thread code required'], 400);
        $messages = $this->api_frontend_model->get_thread_messages($user['user_id'], $thread_code);
        if (isset($messages['error'])) {
            return $this->set_response(['status' => false, 'message' => 'Thread not found'], 404);
        }
        return $this->set_response(['status' => true, 'data' => $messages], 200);
    }

    public function message_send_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->send_message($user['user_id'], $this->input->post('receiver_id'), $this->input->post('message'));
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== PAYMENT GATEWAYS ==========
    public function payment_gateways_get()
    {
        $gateways = $this->api_frontend_model->get_payment_gateways();
        return $this->set_response(['status' => true, 'data' => $gateways], 200);
    }

    // ========== PURCHASE HISTORY (Protected) ==========
    public function purchase_history_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $history = $this->api_frontend_model->get_purchase_history($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $history], 200);
    }

    // ========== QUIZ (Protected) ==========
    public function quiz_get($lesson_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$lesson_id) return $this->set_response(['status' => false, 'message' => 'Lesson ID required'], 400);
        $quiz = $this->api_frontend_model->get_quiz($user['user_id'], $lesson_id);
        if (isset($quiz['error'])) return $this->set_response(['status' => false, 'message' => $quiz['error']], 403);
        return $this->set_response(['status' => true, 'data' => $quiz], 200);
    }

    public function quiz_submit_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->submit_quiz($user['user_id'], $this->input->post('lesson_id'), $this->input->post('answers'));
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function quiz_results_get($lesson_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$lesson_id) return $this->set_response(['status' => false, 'message' => 'Lesson ID required'], 400);
        $results = $this->api_frontend_model->get_quiz_results($user['user_id'], $lesson_id);
        return $this->set_response(['status' => true, 'data' => $results], 200);
    }

    // ========== COURSE PROGRESS (Protected) ==========
    public function course_progress_get($course_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$course_id) return $this->set_response(['status' => false, 'message' => 'Course ID required'], 400);
        $progress = $this->api_frontend_model->get_course_progress_details($user['user_id'], $course_id);
        return $this->set_response(['status' => true, 'data' => $progress], 200);
    }

    // ========== BECOME INSTRUCTOR (Protected) ==========
    public function instructor_application_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->submit_instructor_application($user['user_id'], $this->input->post());
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function instructor_application_status_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $status = $this->api_frontend_model->get_instructor_application_status($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $status], 200);
    }

    // ========== BLOG COMMENTS (Protected) ==========
    public function blog_comment_post($blog_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$blog_id) return $this->set_response(['status' => false, 'message' => 'Blog ID required'], 400);
        $result = $this->api_frontend_model->add_blog_comment($user['user_id'], $blog_id, $this->input->post('comment'), $this->input->post('parent_id'));
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== POPULAR BLOGS ==========
    public function popular_blogs_get()
    {
        $blogs = $this->api_frontend_model->get_popular_blogs($this->input->get('limit') ?: 5);
        return $this->set_response(['status' => true, 'data' => $blogs], 200);
    }

    public function recent_blogs_get()
    {
        $blogs = $this->api_frontend_model->get_recent_blogs($this->input->get('limit') ?: 5);
        return $this->set_response(['status' => true, 'data' => $blogs], 200);
    }

    // ========== CUSTOM PAGES ==========
    public function custom_pages_get()
    {
        $pages = $this->api_frontend_model->get_custom_pages();
        return $this->set_response(['status' => true, 'data' => $pages], 200);
    }

    // ========== CURRENCIES ==========
    public function currencies_get()
    {
        $currencies = $this->api_frontend_model->get_currencies();
        return $this->set_response(['status' => true, 'data' => $currencies], 200);
    }

    // ========== COURSE LEVELS ==========
    public function course_levels_get()
    {
        return $this->set_response(['status' => true, 'data' => ['beginner', 'intermediate', 'advanced']], 200);
    }

    // ========== BADGES ==========
    public function badges_get()
    {
        $badges = $this->api_frontend_model->get_badges();
        return $this->set_response(['status' => true, 'data' => $badges], 200);
    }

    public function my_badges_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $badges = $this->api_frontend_model->get_user_badges($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $badges], 200);
    }

    // ========== LESSON COMMENTS (Protected) ==========
    public function lesson_comments_get($lesson_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$lesson_id) return $this->set_response(['status' => false, 'message' => 'Lesson ID required'], 400);
        $comments = $this->api_frontend_model->get_lesson_comments($lesson_id);
        return $this->set_response(['status' => true, 'data' => $comments], 200);
    }

    public function lesson_comment_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->add_lesson_comment($user['user_id'], $this->input->post('lesson_id'), $this->input->post('comment'));
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // ========== UPDATE WATCH PROGRESS (Protected) ==========
    public function update_watch_progress_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->update_watch_progress($user['user_id'], $this->input->post('lesson_id'), $this->input->post('duration'));
        return $this->set_response($result, 200);
    }

    // ========== FILTER OPTIONS ==========
    public function filter_options_get()
    {
        $options = $this->api_frontend_model->get_filter_options();
        return $this->set_response(['status' => true, 'data' => $options], 200);
    }

    // ========== CHECKOUT & PAYMENT ==========
    public function checkout_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $checkout = $this->api_frontend_model->get_checkout_data($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $checkout], 200);
    }

    public function checkout_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->process_checkout($user['user_id'], $this->input->post());
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function payment_initiate_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $gateway = $this->input->post('gateway');
        $course_ids = $this->input->post('course_ids');
        $coupon_code = $this->input->post('coupon_code');
        $result = $this->api_frontend_model->initiate_payment($user['user_id'], $gateway, $course_ids, $coupon_code);
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    // Stripe / PayPal redirect here after the hosted payment page.
    // Entitlement is granted ONLY after server-side verification with the
    // gateway — never from the client-known token alone.
    public function payment_success_get()
    {
        $token = $this->input->get('token');
        $payment = $token ? $this->db->get_where('payment', ['session_id' => $token])->row_array() : null;

        if (!$payment) {
            redirect(site_url('../cart?payment=failed'));
            return;
        }
        // Replay guard: a completed payment was already fulfilled.
        if (($payment['status'] ?? '') === 'completed') {
            redirect(site_url('../my-courses?payment=success'));
            return;
        }
        // Verify the charge with the gateway before granting anything.
        if (!$this->verify_payment_with_gateway($payment)) {
            redirect(site_url('../cart?payment=failed'));
            return;
        }
        $this->finalize_purchase($payment);
        redirect(site_url('../my-courses?payment=success'));
    }

    /**
     * Server-side confirmation that the payment row was actually paid.
     * Returns true only on a captured charge of at least the expected amount.
     */
    private function verify_payment_with_gateway($payment)
    {
        $txn = $payment['transaction_id'] ?? '';
        $amount = (float) ($payment['amount'] ?? 0);
        if ($txn === '') return false;
        try {
            if ($payment['payment_type'] === 'paypal') {
                $paypal = json_decode(get_settings('paypal'), true);
                if (!$paypal || !isset($paypal[0])) return false;
                $sandbox = ($paypal[0]['mode'] ?? 'sandbox') == 'sandbox';
                $cid = $sandbox ? $paypal[0]['sandbox_client_id'] : $paypal[0]['production_client_id'];
                $sec = $sandbox ? $paypal[0]['sandbox_secret_key'] : $paypal[0]['production_secret_key'];
                $base = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
                $t = curl_init($base . '/v1/oauth2/token');
                curl_setopt_array($t, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_USERPWD => $cid . ':' . $sec,
                    CURLOPT_POSTFIELDS => 'grant_type=client_credentials'
                ]);
                $tr = json_decode(curl_exec($t), true);
                curl_close($t);
                if (empty($tr['access_token'])) return false;
                $o = curl_init($base . '/v2/checkout/orders/' . urlencode($txn));
                curl_setopt_array($o, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tr['access_token']]
                ]);
                $order = json_decode(curl_exec($o), true);
                curl_close($o);
                if (!$order || ($order['status'] ?? '') !== 'COMPLETED') return false;
                $paid_amount = (float) ($order['purchase_units'][0]['amount']['value'] ?? 0);
                $paid_currency = strtoupper($order['purchase_units'][0]['amount']['currency_code'] ?? '');
                $expected_currency = strtoupper(get_settings('paypal_currency') ?: 'USD');
                if ($paid_currency && $paid_currency !== $expected_currency) return false;
                return $paid_amount + 0.01 >= $amount;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /**
     * Enroll the buyer in exactly the snapshotted courses (idempotent, with
     * expiry), write one correctly-attributed payment row per course, clear
     * those items from the cart, and mark the pending row completed.
     */
    private function finalize_purchase($payment)
    {
        if (($payment['status'] ?? '') === 'completed') return;
        $user_id = $payment['user_id'];

        $course_ids = json_decode($payment['course_ids'] ?? '[]', true);
        if (!is_array($course_ids) || empty($course_ids)) {
            $rows = $this->db->select('course_id')->get_where('cart', ['user_id' => $user_id])->result_array();
            $course_ids = array_map(function ($r) {
                return (int) $r['course_id'];
            }, $rows);
        }

        $default_pct = (float) (get_settings('instructor_revenue') ?: 70);
        $reused = false;
        foreach ($course_ids as $cid) {
            $course = $this->db->get_where('course', ['id' => $cid])->row_array();
            if (!$course) continue;

            // Idempotent enrollment with expiry.
            if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $cid])->num_rows() == 0) {
                $expiry = ((int) ($course['expiry_period'] ?? 0) > 0)
                    ? strtotime('+' . (int) $course['expiry_period'] . ' months') : null;
                $this->db->insert('enrol', ['user_id' => $user_id, 'course_id' => $cid, 'date_added' => time(), 'expiry_date' => $expiry]);
            }

            $price = ($course['is_free_course'] == 1) ? 0
                : (($course['discount_flag'] == 1 && $course['discounted_price'] > 0) ? (float) $course['discounted_price'] : (float) $course['price']);
            $inst = $this->db->get_where('users', ['id' => $course['user_id']])->row_array();
            $pct = (isset($inst['instructor_revenue_percentage']) && $inst['instructor_revenue_percentage'] !== '')
                ? (float) $inst['instructor_revenue_percentage'] : $default_pct;
            $inst_rev = $price * $pct / 100;

            if (!$reused) {
                // Reuse the aggregate pending row as the first course's receipt.
                $this->db->where('id', $payment['id'])->update('payment', [
                    'course_id' => $cid,
                    'amount' => $price,
                    'status' => 'completed',
                    'instructor_revenue' => $inst_rev,
                    'admin_revenue' => $price - $inst_rev,
                ]);
                $reused = true;
            } elseif ($price > 0) {
                $this->db->insert('payment', [
                    'user_id' => $user_id,
                    'course_id' => $cid,
                    'amount' => $price,
                    'payment_type' => $payment['payment_type'],
                    'transaction_id' => $payment['transaction_id'],
                    'session_id' => $payment['session_id'],
                    'status' => 'completed',
                    'instructor_revenue' => $inst_rev,
                    'admin_revenue' => $price - $inst_rev,
                    'date_added' => time(),
                ]);
            }
        }
        // Mark completed even if the snapshot was empty, to stop replay.
        if (!$reused) {
            $this->db->where('id', $payment['id'])->update('payment', ['status' => 'completed']);
        }
        if (!empty($course_ids)) {
            $this->db->where('user_id', $user_id)->where_in('course_id', $course_ids)->delete('cart');
        }
    }

    public function payment_cancel_get()
    {
        redirect(site_url('../cart?payment=cancelled'));
    }

    /**
     * Tap redirects here after payment completion.
     * Verifies the charge via Tap API, enrolls user, clears cart.
     * GET /api_frontend/tap_success?token=xxx&tap_id=chg_xxx
     */
    public function tap_success_get()
    {
        $token  = $this->input->get('token');
        $tap_id = $this->input->get('tap_id');

        if (!$tap_id) {
            $tap_id = $this->input->get('charge_id') ?: $this->input->get('id');
        }

        $payment = $this->db->get_where('payment', [
            'session_id'   => $token,
            'payment_type' => 'tap'
        ])->row_array();

        if (!$payment) {
            redirect(site_url('../cart?payment=failed'));
            return;
        }

        // H-04 fix: replay guard — if already completed, do nothing (idempotent)
        if (($payment['status'] ?? '') === 'completed') {
            redirect(site_url('../my-courses?payment=success'));
            return;
        }

        if (!$tap_id) {
            $tap_id = $payment['transaction_id'];
        }

        if (!$tap_id) {
            redirect(site_url('../cart?payment=failed'));
            return;
        }

        $this->load->model('Tap_payment_model');
        $gateway = $this->db->get_where('payment_gateways', ['identifier' => 'tap'])->row_array();
        $keys    = json_decode($gateway['keys'], true);
        $api_key = ($gateway['enabled_test_mode'] == 1)
            ? $keys['test_secret_key']
            : $keys['live_secret_key'];

        $charge = $this->Tap_payment_model->retrieve_tap_charge_public($tap_id, $api_key);

        if (!$charge || !in_array(isset($charge['status']) ? $charge['status'] : '', ['CAPTURED', 'AUTHORIZED'])) {
            log_message('error', 'Tap verification failed: charge=' . $tap_id
                . ' status=' . (isset($charge['status']) ? $charge['status'] : 'unknown'));
            redirect(site_url('../cart?payment=failed'));
            return;
        }

        // H-04 fix: verify amount AND currency match (prevents currency substitution attacks)
        $expected_amount   = (float) $payment['amount'];
        $received_amount   = (float) ($charge['amount'] ?? 0);
        $expected_currency = strtoupper(get_settings('system_currency') ?: 'USD');
        $received_currency = strtoupper($charge['currency'] ?? '');

        if (abs($received_amount - $expected_amount) > 0.01) {
            log_message('error', 'Tap amount mismatch: expected='
                . $expected_amount . ' got=' . $received_amount);
            redirect(site_url('../cart?payment=failed'));
            return;
        }

        if ($received_currency && $received_currency !== $expected_currency) {
            log_message('error', 'Tap currency mismatch: expected='
                . $expected_currency . ' got=' . $received_currency);
            redirect(site_url('../cart?payment=failed'));
            return;
        }

        // H-04 fix: bind tap_id to this session — reject if tap_id was already used for another session
        $existing_tap = $this->db
            ->where('transaction_id', $tap_id)
            ->where('status', 'completed')
            ->where('session_id !=', $token)
            ->count_all_results('payment');
        if ($existing_tap > 0) {
            log_message('error', 'Tap replay attack detected: tap_id=' . $tap_id . ' session=' . $token);
            redirect(site_url('../cart?payment=failed'));
            return;
        }

        // Tap charge verified above — enroll from the snapshot (idempotent,
        // with expiry + correct per-course revenue) and clear those items.
        $this->finalize_purchase($payment);

        redirect(site_url('../my-courses?payment=success'));
    }

    public function payment_verify_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $result = $this->api_frontend_model->verify_payment($user['user_id'], $this->input->post());
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function payment_callback_post($gateway = null)
    {
        $result = $this->api_frontend_model->handle_payment_callback($gateway, $this->input->post());
        return $this->set_response($result, $result['status'] ? 200 : 400);
    }

    public function invoice_get($payment_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$payment_id) return $this->set_response(['status' => false, 'message' => 'Payment ID required'], 400);
        $invoice = $this->api_frontend_model->get_invoice($user['user_id'], $payment_id);
        if (!$invoice) return $this->set_response(['status' => false, 'message' => 'Invoice not found'], 404);
        return $this->set_response(['status' => true, 'data' => $invoice], 200);
    }

    // ========== COURSE PAGE DATA ==========
    public function courses_page_get()
    {
        $data = $this->api_frontend_model->get_courses_page_data($this->input->get());
        return $this->set_response(['status' => true, 'data' => $data], 200);
    }

    public function course_by_slug_get($slug = null)
    {
        if (!$slug) return $this->set_response(['status' => false, 'message' => 'Course slug required'], 400);
        $course = $this->api_frontend_model->get_course_by_slug($slug);
        if (!$course) return $this->set_response(['status' => false, 'message' => 'Course not found'], 404);
        return $this->set_response(['status' => true, 'data' => $course], 200);
    }

    public function featured_courses_get()
    {
        $courses = $this->api_frontend_model->get_featured_courses($this->input->get('limit') ?: 8);
        return $this->set_response(['status' => true, 'data' => $courses], 200);
    }

    public function related_courses_get($course_id = null)
    {
        if (!$course_id) return $this->set_response(['status' => false, 'message' => 'Course ID required'], 400);
        $courses = $this->api_frontend_model->get_related_courses($course_id, $this->input->get('limit') ?: 4);
        return $this->set_response(['status' => true, 'data' => $courses], 200);
    }

    // ========== LESSON VIDEO/CONTENT ==========
    public function lesson_video_get($lesson_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$lesson_id) return $this->set_response(['status' => false, 'message' => 'Lesson ID required'], 400);
        $video = $this->api_frontend_model->get_lesson_video($user['user_id'], $lesson_id);
        if (isset($video['error'])) return $this->set_response(['status' => false, 'message' => $video['error']], 403);
        return $this->set_response(['status' => true, 'data' => $video], 200);
    }

    // Returns embeddable HTML for a lesson video (used by React iframe)
    public function lesson_embed_get($lesson_id = null)
    {
        $user = $this->authenticate();
        if (!$user) {
            echo 'Unauthorized';
            return;
        }
        if (!$lesson_id) {
            echo 'Lesson ID required';
            return;
        }

        $video = $this->api_frontend_model->get_lesson_video($user['user_id'], $lesson_id);
        if (isset($video['error'])) {
            echo $video['error'];
            return;
        }

        $video_url = $video['video_url'] ?? '';
        $video_type = strtolower($video['video_type'] ?? '');
        $lesson_type = strtolower($video['lesson_type'] ?? 'video');

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head>';
        echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<link rel="stylesheet" href="' . base_url('assets/global/plyr/plyr.css') . '">';
        echo '<style>body{margin:0;background:#000;overflow:hidden;color:#fff;font-family:system-ui,sans-serif} .plyr,.plyr__video-embed,.plyr__video-embed iframe{width:100%;height:100vh} .text-content{padding:40px;max-width:800px;margin:0 auto;background:#111;min-height:100vh;overflow-y:auto}</style>';
        echo '</head><body>';

        // Handle non-video lessons (quiz, text, other)
        if ($lesson_type == 'quiz') {
            echo '<div class="text-content"><h2>' . htmlspecialchars($video['title']) . '</h2><p style="color:#aaa">This is a quiz lesson. Please complete it in the course player.</p></div>';
            echo '</body></html>';
            exit;
        }
        if (empty($video_url) || $lesson_type == 'text' || $lesson_type == 'other') {
            $summary = $video['summary'] ?? '';
            echo '<div class="text-content"><h2>' . htmlspecialchars($video['title']) . '</h2>';
            if ($summary) echo '<div>' . $summary . '</div>';
            else echo '<p style="color:#aaa">This lesson has no video content.</p>';
            echo '</div></body></html>';
            exit;
        }

        if ($video_type == 'vimeo' || strpos($video_url, 'vimeo.com') !== false) {
            preg_match('/vimeo\.com\/(\d+)/', $video_url, $m);
            $vid = $m[1] ?? '';
            echo '<div class="plyr__video-embed" id="player">';
            echo '<iframe src="https://player.vimeo.com/video/' . $vid . '?loop=false&byline=false&portrait=false&title=false&speed=true&transparent=0&gesture=media" allowfullscreen allowtransparency allow="autoplay"></iframe>';
            echo '</div>';
        } elseif ($video_type == 'youtube' || strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
            preg_match('/(?:v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $m);
            $vid = $m[1] ?? '';
            echo '<div class="plyr__video-embed" id="player">';
            echo '<iframe src="https://www.youtube.com/embed/' . $vid . '?origin=https://plyr.io&iv_load_policy=3&modestbranding=1&playsinline=1&showinfo=0&rel=0&enablejsapi=1" allowfullscreen allow="autoplay"></iframe>';
            echo '</div>';
        } elseif ($video_type == 'html5' || preg_match('/\.(mp4|webm|ogg)/', $video_url)) {
            echo '<video id="player" playsinline controls style="width:100%;height:100vh"><source src="' . $video_url . '"></video>';
        } else {
            echo '<div style="color:#fff;text-align:center;padding-top:40vh">Video type not supported</div>';
        }

        echo '<script src="' . base_url('assets/global/plyr/plyr.js') . '"></script>';
        echo '<script>var player = new Plyr("#player");</script>';
        echo '</body></html>';
        exit;
    }

    public function lesson_attachments_get($lesson_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$lesson_id) return $this->set_response(['status' => false, 'message' => 'Lesson ID required'], 400);
        $attachments = $this->api_frontend_model->get_lesson_attachments($user['user_id'], $lesson_id);
        if (isset($attachments['error'])) return $this->set_response(['status' => false, 'message' => $attachments['error']], 403);
        return $this->set_response(['status' => true, 'data' => $attachments], 200);
    }

    // ========== COURSE LEARN PAGE ==========
    public function course_learn_get($course_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$course_id) return $this->set_response(['status' => false, 'message' => 'Course ID required'], 400);
        $data = $this->api_frontend_model->get_course_learn_data($user['user_id'], $course_id);
        if (isset($data['error'])) return $this->set_response(['status' => false, 'message' => $data['error']], 403);
        return $this->set_response(['status' => true, 'data' => $data], 200);
    }

    // ========== INSTRUCTOR PAGE ==========
    public function instructor_page_get($id = null)
    {
        if (!$id) return $this->set_response(['status' => false, 'message' => 'Instructor ID required'], 400);
        $data = $this->api_frontend_model->get_instructor_page_data($id);
        if (!$data) return $this->set_response(['status' => false, 'message' => 'Instructor not found'], 404);
        return $this->set_response(['status' => true, 'data' => $data], 200);
    }

    // ========== CATEGORY PAGE ==========
    public function category_page_get($id = null)
    {
        if (!$id) return $this->set_response(['status' => false, 'message' => 'Category ID required'], 400);
        $data = $this->api_frontend_model->get_category_page_data($id, $this->input->get());
        if (!$data) return $this->set_response(['status' => false, 'message' => 'Category not found'], 404);
        return $this->set_response(['status' => true, 'data' => $data], 200);
    }

    // ========== BLOG PAGE ==========
    public function blog_page_get()
    {
        $data = $this->api_frontend_model->get_blog_page_data($this->input->get());
        return $this->set_response(['status' => true, 'data' => $data], 200);
    }

    // ========== USER DASHBOARD ==========
    public function dashboard_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        $data = $this->api_frontend_model->get_user_dashboard($user['user_id']);
        return $this->set_response(['status' => true, 'data' => $data], 200);
    }

    // ========== COMPARE COURSES ==========
    public function compare_courses_post()
    {
        $course_ids = $this->input->post('course_ids');
        if (empty($course_ids)) return $this->set_response(['status' => false, 'message' => 'Course IDs required'], 400);
        $data = $this->api_frontend_model->compare_courses($course_ids);
        return $this->set_response(['status' => true, 'data' => $data], 200);
    }

    // ========== CERTIFICATE ==========
    public function certificate_get($course_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        if (!$course_id) return $this->set_response(['status' => false, 'message' => 'Course ID required'], 400);
        $certificate = $this->api_frontend_model->get_certificate($user['user_id'], $course_id);
        if (isset($certificate['error'])) return $this->set_response(['status' => false, 'message' => $certificate['error']], 403);
        return $this->set_response(['status' => true, 'data' => $certificate], 200);
    }

    // ========== HELPER ==========
    private function authenticate($return_401 = true)
    {
        $auth_token = $this->input->get('auth_token') ?: $this->input->post('auth_token');
        if (!$auth_token) {
            $headers = $this->input->request_headers();
            $auth_token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        }
        if (!$auth_token) {
            if ($return_401) $this->set_response(['status' => false, 'message' => 'Authentication required'], 401);
            return false;
        }
        try {
            return (array)$this->tokenHandler->DecodeToken($auth_token);
        } catch (Exception $e) {
            if ($return_401) $this->set_response(['status' => false, 'message' => 'Invalid token'], 401);
            return false;
        }
    }

    // Like authenticate(), but for endpoints that are public yet personalise their
    // response when a token happens to be present. Returns the user id or null —
    // it never emits a 401, so guests still get a normal response.
    private function optional_user_id()
    {
        $auth_token = $this->input->get('auth_token') ?: $this->input->post('auth_token');
        if (!$auth_token) {
            $headers = $this->input->request_headers();
            $auth_token = isset($headers['Authorization']) ? trim(str_replace('Bearer', '', $headers['Authorization'])) : null;
        }
        if (!$auth_token) return null;
        try {
            $decoded = (array)$this->tokenHandler->DecodeToken($auth_token);
            return $decoded['user_id'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
}
