<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Login extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();

        date_default_timezone_set(get_settings('timezone'));
        
        // Your own constructor code
        $this->load->database();
        $this->load->library('session');
        /*cache control*/
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');


        //Check custom session data
        $this->user_model->check_session_data();
    }

    public function index()
    {
        //Check custom session data
        $this->user_model->check_session_data('login');

        $page_data['page_name'] = 'login';
        $page_data['page_title'] = site_phrase('login');
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);
    }

    public function sign_up()
    {
        if ($this->session->userdata('admin_login')) {
            redirect(site_url('admin'), 'refresh');
        } elseif ($this->session->userdata('user_login')) {
            /* بوابة صاحبها لا `user`: تلك صفحة من القالب القديم ترجع ٤٠٤. */
            redirect(tq_home_for(tq_role()), 'location', 302);
        }
        $page_data['page_name'] = 'sign_up';
        $page_data['page_title'] = site_phrase('sign_up');
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);
    }


    /**
     * Same-site guard for the authentication POSTs of this controller.
     * See the CSRF note in application/config/config.php: the views cannot
     * carry a CI CSRF token yet, so cross-site POSTs are rejected by
     * comparing the Origin/Referer host with the requested host.
     */
    private function tq_guard_origin($redirect_to = 'login')
    {
        if (tq_auth_verify_origin()) {
            return;
        }
        $this->session->set_flashdata('error_message', get_phrase('something_is_wrong') . '! ' . site_phrase('please_try_again'));
        redirect(site_url($redirect_to), 'refresh');
    }

    public function validate_login($from = "")
    {
        $this->tq_guard_origin();

        if ($this->crud_model->check_recaptcha() == false && (get_frontend_settings('recaptcha_status') == true || get_frontend_settings('recaptcha_status_v3') == true)) {
            $this->session->set_flashdata('error_message', get_phrase('recaptcha_verification_failed'));
            redirect(site_url('login'), 'refresh');
        }

        $email = $this->input->post('email');
        $password = $this->input->post('password');

        // Brute force throttle: 5 failures per (email, IP) or 25 per IP / 15 min
        if (tq_auth_is_throttled($email, 'login')) {
            $this->session->set_flashdata('error_message', tq_auth_throttle_message());
            redirect(site_url('login'), 'refresh');
        }

        // Fetch by e-mail only, then verify the hash in PHP so that both the
        // legacy sha1 digests and the new password_hash() ones are accepted.
        $query = $this->db->get_where('users', array('email' => $email, 'status' => 1));

        if ($query->num_rows() > 0 && tq_password_authenticate($query->row_array(), $password)) {
            $row = $query->row();
            tq_auth_clear_failures($email, 'login');
            $this->user_model->new_device_login_tracker($row->id);
            $this->user_model->set_login_userdata($row->id);
        } else {
            tq_auth_record_failure($email, 'login');
            $this->session->set_flashdata('error_message', get_phrase('invalid_login_credentials'));
            redirect(site_url('login'), 'refresh');
        }
    }

    function new_login_confirmation($param1 = ""){
        $new_device_code_expiration_time = $this->session->userdata('new_device_code_expiration_time');
        if(!$new_device_code_expiration_time || $new_device_code_expiration_time < (time())){
            $this->session->set_flashdata('error_message', get_phrase('time_over').'! '.site_phrase('please_try_again'));
            redirect(site_url('login'), 'refresh');
        }

        if($param1 == 'submit'){
            $this->tq_guard_origin();

            $device_throttle_key = 'device-' . $this->session->userdata('new_device_user_id');
            if (tq_auth_is_throttled($device_throttle_key, 'device')) {
                $this->session->set_flashdata('error_message', tq_auth_throttle_message());
                redirect(site_url('login'), 'refresh');
            }

            $new_device_verification_code = $this->input->post('new_device_verification_code');
            if($new_device_verification_code != $this->session->userdata('new_device_verification_code')){
                tq_auth_record_failure($device_throttle_key, 'device');
                $this->session->set_flashdata('error_message', get_phrase('verification_code_is_wrong'));
                redirect(site_url('login/new_login_confirmation'), 'refresh');
            }
            tq_auth_clear_failures($device_throttle_key, 'device');

            // Checking login credential for admin
            $query = $this->db->get_where('users', array('id' => $this->session->userdata('new_device_user_id')));

            if ($query->num_rows() > 0) {
                $row = $query->row();

                // For device login tracker
                $this->user_model->new_device_login_tracker($row->id, true);
                $this->user_model->set_login_userdata($row->id);
            }
            $this->session->set_flashdata('error_message', get_phrase('something_is_wrong').'! '.site_phrase('please_try_again'));
            /* كل إلى لوحته: التوجيه إلى الرئيسية يجعل من دخل
               يبحث عن بوابته بنفسه. */
            /* TQ-GATE-MSG — من ينتظر اعتمادا يقال له ذلك، لا يترك أمام
               شاشة دخول ترده بلا سبب. */
            redirect(tq_home_for(tq_role()), 'location', 302);
        }

        if($param1 == 'resend'){
            $this->email_model->new_device_login_alert();
            return;
        }

        $page_data['page_name'] = 'new_login_confirmation';
        $page_data['page_title'] = site_phrase('new_login_confirmation');
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);
    }
    
    public function fb_validate_login($access_token = "", $fb_user_id = "") {
        $this->social_login_modal->fb_validate_login($access_token, $fb_user_id);
    }








    public function register()
    {
        $this->tq_guard_origin('sign_up');

        if ($this->crud_model->check_recaptcha() == false && (get_frontend_settings('recaptcha_status') == true || get_frontend_settings('recaptcha_status_v3') == true)) {
            $this->session->set_flashdata('error_message', get_phrase('recaptcha_verification_failed'));
            redirect(site_url('login'), 'refresh');
        }

        $data['first_name'] = html_escape($this->input->post('first_name'));
        $data['last_name']  = html_escape($this->input->post('last_name'));
        /* TQ-REGISTER-GUARD — التحقق في الخادم لا في المتصفح.
           النمط الصحيح مطبق في نموذج التواصل ومفقود هنا: يتحقق من
           صيغة البريد هناك ولا يتحقق منها في إنشاء حساب، ويطلب تأكيد
           كلمة المرور في الاستعادة ولا يطلب في الإنشاء. */
        $tq_email = trim((string) $this->input->post('email'));
        $tq_pass  = (string) $this->input->post('password');
        $tq_conf  = (string) $this->input->post('password_confirm');
        $tq_gate  = (string) $this->input->post('tq_gate');
        $tq_age   = (int) $this->input->post('age');
        $tq_guard = trim((string) $this->input->post('guardian_email'));
        $tq_err = '';
        if (!filter_var($tq_email, FILTER_VALIDATE_EMAIL) || mb_strlen($tq_email) > 50) {
            $tq_err = 'البريد الإلكتروني غير صحيح.';
        } elseif (mb_strlen($tq_pass) < 8) {
            $tq_err = 'كلمة المرور ثمانية محارف على الأقل.';
        } elseif ($tq_pass !== $tq_conf) {
            $tq_err = 'كلمتا المرور غير متطابقتين.';
        } elseif ((string) $this->input->post('accept_terms') !== '1') {
            $tq_err = 'لا بد من الموافقة على الشروط وسياسة الخصوصية.';
        } elseif ($tq_gate === 'student' && $tq_age > 0 && $tq_age < 15
                  && !filter_var($tq_guard, FILTER_VALIDATE_EMAIL)) {
            /* دون الخامسة عشرة: بريد ولي الأمر شرط لا حقل اختياري. */
            $tq_err = 'دون الخامسة عشرة نحتاج بريد ولي أمرك.';
        } elseif ($tq_gate === 'teacher') {
            /* TQ-PHONE-NORM — يطبع قبل الفحص: تزال المسافات والشرط
               والأقواس، وتحول الأرقام العربية، وتقص البادئة الدولية
               (`+966` / `00966`) والصفر. فيقبل `0501234567` و`+966 50 123 4567`
               و`٠٥٠١٢٣٤٥٦٧` جميعا، ويخزن `501234567` موحدا. */
            $tq_phone = (string) $this->input->post('phone');
            $tq_phone = strtr($tq_phone, array(
                '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
                '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            ));
            $tq_phone = preg_replace('/[^0-9]/', '', $tq_phone);
            $tq_phone = preg_replace('/^(?:00966|966)/', '', $tq_phone);
            $tq_phone = preg_replace('/^0/', '', $tq_phone);
            if (!preg_match('/^5[0-9]{8}$/', $tq_phone)) {
                $tq_err = 'رقم الجوال غير صحيح. اكتبه هكذا: 0501234567';
            } elseif (empty($_FILES['document']['name'])) {
                $tq_err = 'مستند التعريف مطلوب لطلب الانضمام معلما.';
            }
        }
        if ($tq_err !== '') {
            $this->session->set_flashdata('error_message', $tq_err);
            redirect(site_url('sign_up') . ($tq_gate !== 'student' ? '?as=' . $tq_gate : ''),
                     'location', 302);
            return;
        }
        $data['email']  = html_escape($this->input->post('email'));
        // NOTE: emptiness is tested on the plain value, because a hash of an
        // empty string is not empty and would defeat the check below.
        $plain_password = (string) $this->input->post('password');
        $data['password']  = ($plain_password === '') ? '' : tq_password_hash($plain_password);

        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['password'])) {
            $this->session->set_flashdata('error_message', site_phrase('your_sign_up_form_is_empty') . '. ' . site_phrase('fill_out_the_form with_your_valid_data'));
            redirect(site_url('sign_up'), 'refresh');
        }

        $verification_code =  rand(100000, 200000);
        $data['verification_code'] = $verification_code;

        if (get_settings('student_email_verification') == 'enable') {
            $data['status'] = 0;
        } else {
            $data['status'] = 1;
        }

        $data['wishlist'] = json_encode(array());
        $data['date_added'] = strtotime(date("Y-m-d H:i:s"));
        $social_links = array(
            'facebook' => "",
            'twitter'  => "",
            'linkedin' => ""
        );
        $data['social_links'] = json_encode($social_links);
        $data['role_id']  = 2;

        $data['payment_keys'] = json_encode(array());

        $validity = $this->user_model->check_duplication('on_create', $data['email']);

        if ($validity === 'unverified_user' || $validity == true) {


            //Check instructor application document
            /* TQ-TEACHER-PENDING — الحساب يبقى مغلقا حتى تراجع أوراقه.
               Academy يفتحه فورا، فمن سجل معلما يرى لوحته قبل أن يراه
               أحد — ومعلمو هذه المنصة يتعاملون مع قاصرين. */
            /* TQ-GATE-BRIDGE — Academy ينشئ صف الطلب بشرط `instructor=yes`،
                       والبوابة الجديدة ترسل `tq_gate=teacher`. فيجسر الاسمان
                       هنا بدل تكرار منطق إنشاء الطلب. */
                    if ($tq_gate === 'teacher') {
                        $data['status'] = 0;
                        /* TQ-INSTRUCTOR-FLAG — `register()` يبني `$data` بيده
                           ولا يضبط هذا العمود أبدا (تضبطه `add_user()` وحدها
                           حين تنادى من الإدارة). فبدونه يبقى المعلم — حتى
                           بعد اعتماده — طالبا في اشتقاق الدور. */
                        $data['is_instructor'] = 1;
                        $_POST['instructor'] = 'yes';
                    }
            if (isset($tq_phone) && $tq_phone !== '') { $_POST['phone'] = $tq_phone; }
            $data['tq_gate'] = $tq_gate;
            $data['terms_accepted_at'] = date('Y-m-d H:i:s');
            if ($tq_gate === 'student') {
                if ($tq_age > 0)  { $data['age'] = $tq_age; }
                if ($tq_guard !== '') { $data['guardian_email'] = $tq_guard; }
                $tq_gr = (int) $this->input->post('grade_id');
                if ($tq_gr > 0) { $data['grade_id'] = $tq_gr; }
            }
            if(get_settings('allow_instructor')):
                if(isset($_POST['instructor']) && $_POST['instructor'] == 'yes'){
                    if(empty($_POST['phone'])){
                        $this->session->set_flashdata('error_message', 'أدخل رقم جوالك — وهو لازم لطلب الاعتماد.');
                        redirect(site_url('sign_up'), 'refresh');
                    }
                    if(empty($_FILES['document']['name'])){
                        $this->session->set_flashdata('error_message', 'أرفق وثيقة تثبت مؤهلك أو خبرتك (PDF أو صورة) — فطلب الاعتماد لا يقبل بدونها.');
                        redirect(site_url('sign_up'), 'refresh');
                    }
                    $accepted_ext = array('doc', 'docs', 'pdf', 'txt', 'png', 'jpg', 'jpeg');
                    $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                    if (in_array(strtolower($ext), $accepted_ext)) {
                        $instructor_apply = true;
                    }else{
                        $this->session->set_flashdata('error_message', 'صيغة الوثيقة غير مقبولة. المقبول: PDF · DOC · TXT · PNG · JPG.');
                        redirect(site_url('sign_up'), 'refresh');
                    }
                }
            endif;
            //End Check  instructor application document

            if ($validity === true) {
                $user_id = $this->user_model->register_user($data);
            } else {
                $this->user_model->register_user_update_code($data, $data['status']);
            }

            //instructor application
            if(isset($instructor_apply) && $instructor_apply == true):
                $this->user_model->instructor_application();
            endif;
            //End instructor application

            if (get_settings('student_email_verification') == 'enable') {
                $this->email_model->send_email_verification_mail($data['email'], $verification_code);

                if ($validity === 'unverified_user') {
                    $this->session->set_flashdata('info_message', get_phrase('you_have_already_registered') . '. ' . get_phrase('please_verify_your_email_address'));
                } else {
                    $this->session->set_flashdata('flash_message', get_phrase('your_registration_has_been_successfully_done') . '. ' . get_phrase('please_check_your_mail_inbox_to_verify_your_email_address') . '.');
                }
                $this->session->set_userdata('register_email', $this->input->post('email'));
                redirect(site_url('sign_up/verification_code'), 'refresh');
            } else {
                if(isset($user_id)){
                    $this->email_model->signup_mail($user_id);
                }
                $this->session->set_flashdata('flash_message', get_phrase('your_registration_has_been_successfully_done'));
                redirect(site_url('login'), 'refresh');
            }
        } else {
            $this->session->set_flashdata('error_message', get_phrase('you_have_already_registered'));
            redirect(site_url('login'), 'refresh');
        }
    }

    public function logout($from = "")
    {
        //destroy sessions of specific userdata. We've done this for not removing the cart session
        $this->user_model->session_destroy();
        redirect(site_url('login'), 'refresh');
    }

    public function forgot_password_request()
    {
        if ($this->session->userdata('admin_login')) {
            redirect(site_url('admin'), 'refresh');
        } elseif ($this->session->userdata('user_login')) {
            /* بوابة صاحبها لا `user`: تلك صفحة من القالب القديم ترجع ٤٠٤. */
            redirect(tq_home_for(tq_role()), 'location', 302);
        }
        $page_data['page_name'] = 'forgot_password';
        $page_data['page_title'] = site_phrase('forgot_password');
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);
    }

    function forgot_password($from = "")
    {
        $this->tq_guard_origin('login/forgot_password_request');

        if ($this->crud_model->check_recaptcha() == false && (get_frontend_settings('recaptcha_status') == true || get_frontend_settings('recaptcha_status_v3') == true)) {
            $this->session->set_flashdata('error_message', get_phrase('recaptcha_verification_failed'));
            redirect(site_url('login'), 'refresh');
        }
        $email = $this->input->post('email');

        // Throttle reset requests as well, so the mailbox cannot be flooded
        // and the endpoint cannot be used to enumerate accounts at speed.
        if (tq_auth_is_throttled($email, 'reset_request')) {
            $this->session->set_flashdata('error_message', tq_auth_throttle_message());
            redirect(site_url('login'), 'refresh');
        }
        tq_auth_record_failure($email, 'reset_request');

        $query = $this->db->get_where('users', array('email' => $email, 'status' => 1));
        if ($query->num_rows() > 0) {
            $this->crud_model->forgot_password();
            redirect(site_url('login'), 'refresh');
        } else {
            $this->session->set_flashdata('error_message', get_phrase('user_not_found'));
            redirect(site_url('login'), 'refresh');
        }
    }

    function change_password($verification_code = ""){

        // The reset token is now 64 hex chars produced by random_bytes(32) and
        // is stored hashed (sha256) in tq_auth_password_resets. It is single
        // use, expires after TQ_AUTH_RESET_TTL and guessing it is throttled.
        $verification_code = str_replace(array(' ', '%20', '='), '', (string) $verification_code);

        if ($verification_code === "") {
            $this->session->set_flashdata('error_message', get_phrase('invalid_verification_code').'. '.get_phrase('please_send_a_new_forgot_password_request'));
            redirect(site_url('login'), 'refresh');
        }

        if (tq_auth_is_throttled('reset-token', 'reset_consume')) {
            $this->session->set_flashdata('error_message', tq_auth_throttle_message());
            redirect(site_url('login/forgot_password_request'), 'refresh');
        }

        $reset_request = tq_auth_find_reset_token($verification_code);
        if ($reset_request === FALSE) {
            tq_auth_record_failure('reset-token', 'reset_consume');
            $this->session->set_flashdata('error_message', get_phrase('This link has been expired.').' '.get_phrase('Please send a new request'));
            redirect(site_url('login/forgot_password_request'), 'refresh');
        }

        if(isset($_POST['new_password']) && isset($_POST['confirm_password']) && !empty($_POST['confirm_password']) && $verification_code){
            $this->tq_guard_origin('login/forgot_password_request');
            $new_password = $this->input->post('new_password');
            $confirm_password = $this->input->post('confirm_password');
            if($new_password == $confirm_password):
                if ($this->crud_model->change_password_from_forgot_passord($verification_code)) {
                    $this->session->set_flashdata('flash_message', get_phrase('password_has_changed_successfully'));
                } else {
                    $this->session->set_flashdata('error_message', get_phrase('This link has been expired.').' '.get_phrase('Please send a new request'));
                }
                redirect(site_url('login'), 'refresh');
            else:
                $this->session->set_flashdata('error_message', get_phrase('the_confirmed_password_is_not_matching_with_the_new_password'));
                redirect(site_url('login/change_password/'.$verification_code), 'refresh');
            endif;
        }


        $page_data['verification_code'] = $verification_code;
        $page_data['page_name'] = 'change_password_from_forgot_password';
        $page_data['page_title'] = site_phrase('change_password');
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);

    }

    public function resend_verification_code()
    {
        if ( ! tq_auth_verify_origin()) {
            echo false;
            return;
        }
        $email = $this->input->post('email');
        if (tq_auth_is_throttled($email, 'resend_code')) {
            echo false;
            return;
        }
        tq_auth_record_failure($email, 'resend_code');

        $verification_code = $this->db->get_where('users', array('email' => $email))->row('verification_code');
        $this->email_model->send_email_verification_mail($email, $verification_code);

        return true;
    }

    public function verify_email_address()
    {
        if ( ! tq_auth_verify_origin()) {
            echo false;
            return;
        }
        $email = $this->input->post('email');
        $verification_code = $this->input->post('verification_code');

        // The e-mail verification code is only 6 digits, so it must be throttled.
        if (tq_auth_is_throttled($email, 'verify_email')) {
            $this->session->set_flashdata('error_message', tq_auth_throttle_message());
            echo false;
            return;
        }

        if (empty($verification_code)) {
            tq_auth_record_failure($email, 'verify_email');
            echo false;
            return;
        }

        $user_details = $this->db->get_where('users', array('email' => $email, 'verification_code' => $verification_code));
        if ($user_details->num_rows() > 0) {
            tq_auth_clear_failures($email, 'verify_email');
            $user_details = $user_details->row_array();

            $updater = array(
                'status' => 1
            );
            $this->db->where('id', $user_details['id']);
            $this->db->update('users', $updater);

            $this->email_model->signup_mail($user_details['id']);

            $this->session->set_flashdata('flash_message', get_phrase('congratulations') . '! ' . get_phrase('your_email_address_has_been_successfully_verified') . '.');
            $this->session->set_userdata('register_email', null);

            echo true;
        } else {
            tq_auth_record_failure($email, 'verify_email');
            $this->session->set_flashdata('error_message', get_phrase('the_verification_code_is_wrong') . '.');
            echo false;
        }
    }


    function check_recaptcha_with_ajax()
    {
        if ($this->crud_model->check_recaptcha()) {
            echo true;
        } else {
            echo false;
        }
    }

}
