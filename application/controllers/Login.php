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
        $page_data['page_title'] = 'تسجيل الدخول';
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
        $page_data['page_title'] = 'إنشاء حساب';
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
        $this->session->set_flashdata('error_message', 'تعذر إتمام الطلب. أعد المحاولة من الصفحة نفسها.');
        redirect(site_url($redirect_to), 'refresh');
    }

    /**
     * TQ-REMEMBER — «تذكرني على هذا الجهاز» كانت خانة بلا أثر.
     *
     * الوسم يرسل `remember_me`، و`validate_login` لم يكن يقرأها إطلاقا:
     * كل دخول يخرج بالكوكي نفسه، مؤقتا كان أو دائما. فخانة تعد بشيء
     * ولا تفعله — وهي في صفحة الدخول أول ما يجربه المستخدم.
     *
     * والفرق الحقيقي في **عمر الكوكي** لا في بيانات الجلسة: من لم يعلم
     * الخانة تنتهي جلسته بإغلاق المتصفح، ومن علمها يبقى إلى مدى
     * `sess_expiration`. والكوكي يعاد إرساله هنا لأن ضبط عمره وقت
     * `session_start` قرار عام لا يعرف من سيدخل.
     */
    private function tq_apply_remember_me()
    {
        $remembered = ((string) $this->input->post('remember_me') === '1');
        if ($remembered) {
            return;                       // العمر المضبوط في config هو المطلوب
        }

        $name = (string) config_item('sess_cookie_name');
        if ($name === '' OR ! isset($_COOKIE[$name]) OR headers_sent()) {
            return;
        }
        setcookie($name, $_COOKIE[$name], array(
            'expires'  => 0,              // كوكي جلسة: يموت بإغلاق المتصفح
            'path'     => config_item('cookie_path') ?: '/',
            'domain'   => (string) config_item('cookie_domain'),
            'secure'   => (bool) config_item('cookie_secure'),
            'httponly' => TRUE,
            'samesite' => 'Lax',
        ));
    }

    public function validate_login($from = "")
    {
        $this->tq_guard_origin();

        if ($this->crud_model->check_recaptcha() == false && (get_frontend_settings('recaptcha_status') == true || get_frontend_settings('recaptcha_status_v3') == true)) {
            $this->session->set_flashdata('error_message', 'تعذر التحقق من أنك لست آليا. أعد المحاولة.');
            redirect(site_url('login'), 'refresh');
        }

        $email = trim((string) $this->input->post('email'));
        $password = $this->input->post('password');

        /* البريد يعود إلى الحقل بعد الرفض: من أخطأ في كلمة المرور لا
           يعاقب بإعادة كتابة بريده أيضا. ولا تعاد كلمة المرور أبدا. */
        $this->session->set_flashdata('tq_old_email', $email);

        // Brute force throttle: 5 failures per (email, IP) or 25 per IP / 15 min
        if (tq_auth_is_throttled($email, 'login')) {
            $this->session->set_flashdata('error_message', tq_auth_throttle_message());
            redirect(site_url('login'), 'refresh');
        }

        // Fetch by e-mail only, then verify the hash in PHP so that both the
        // legacy sha1 digests and the new password_hash() ones are accepted.
        /* TQ-PENDING-TRUTH — الاستعلام لا يقيد بـ`status = 1` بعد اليوم.
           كان يقيد، فكل حساب موقوف يسقط في «البريد أو كلمة المرور غير
           صحيحة»: المعلم الذي سجل قبل دقيقة وينتظر الاعتماد يقرأ أن
           كلمة مروره خاطئة فيعيد التسجيل، ومن لم يؤكد بريده كذلك.
           والحالة تفحص **بعد** الاستيثاق لا قبله، فلا يعرف الحقيقة إلا
           من يملك كلمة المرور أصلا — ولا يكشف الرد شيئا لمن لا يملكها. */
        $query = $this->db->get_where('users', array('email' => $email));
        $row   = $query->num_rows() > 0 ? $query->row() : NULL;

        if ($row === NULL OR ! tq_password_authenticate((array) $row, $password)) {
            tq_auth_record_failure($email, 'login');
            $this->session->set_flashdata('error_message', 'البريد الإلكتروني أو كلمة المرور غير صحيحة.');
            redirect(site_url('login'), 'refresh');
        }

        tq_auth_clear_failures($email, 'login');

        if ((int) $row->status !== 1) {
            $this->session->set_flashdata('tq_old_email', $email);
            if (!empty($row->is_instructor) OR (string) $row->tq_gate === 'teacher') {
                $this->session->set_flashdata('error_message',
                    'طلب انضمامك معلما ما زال قيد المراجعة. نتواصل معك عند الاعتماد، ويفتح الدخول بعده.');
            } elseif (get_settings('student_email_verification') == 'enable') {
                $this->session->set_userdata('register_email', $email);
                $this->session->set_flashdata('error_message',
                    'بريدك لم يؤكد بعد. اكتب الرمز المرسل إليك ليفتح حسابك.');
                redirect(site_url('sign_up/verification_code'), 'refresh');
            } else {
                $this->session->set_flashdata('error_message',
                    'هذا الحساب موقوف. تواصل مع الإدارة.');
            }
            redirect(site_url('login'), 'refresh');
        }

        $this->session->set_flashdata('tq_old_email', NULL);
        $this->tq_apply_remember_me();

        /* TQ-GATE-CHECK — البوابة المختارة تقابل الدور الحقيقي.
           لا ترد الدخول: من كتب بريده وكلمته صحيحين يدخل. ولكن من
           اختار بابا ليس بابه يقال له ذلك ويوضع في لوحته، بدل أن يهبط
           في شاشة لا يتوقعها ويحسب المنصة أخطأت. */
        $tq_want = (string) $this->input->post('tq_gate');
        $tq_real = tq_role($row->id);
        if (in_array($tq_want, array('student', 'teacher', 'parent', 'admin'), TRUE)
            && $tq_want !== $tq_real) {
            $tq_names = array(
                'student' => 'طالب', 'teacher' => 'معلم',
                'parent'  => 'ولي أمر', 'admin' => 'إدارة',
            );
            if ($tq_want === 'teacher' && (string) $row->tq_gate === 'teacher') {
                /* سجل معلما وفتح حسابه ولم يضبط `is_instructor` بعد:
                   قول «هذا الحساب مسجل طالب» هنا يكذب عليه. */
                $this->session->set_flashdata('info_message',
                    'لوحة المعلم تفتح بعد اعتماد طلبك. وحتى ذلك الحين هذه لوحتك.');
            } else {
                $this->session->set_flashdata('info_message',
                    'دخلت من بوابة «' . $tq_names[$tq_want] . '»، وهذا الحساب مسجل «'
                    . $tq_names[$tq_real] . '». فتحنا لوحته.');
            }
        }

        $this->user_model->new_device_login_tracker($row->id);
        $this->user_model->set_login_userdata($row->id);
    }

    function new_login_confirmation($param1 = ""){
        $new_device_code_expiration_time = $this->session->userdata('new_device_code_expiration_time');
        if(!$new_device_code_expiration_time || $new_device_code_expiration_time < (time())){
            $this->session->set_flashdata('error_message', 'انتهت مهلة الرمز. أعد تسجيل الدخول من جديد.');
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
                $this->session->set_flashdata('error_message', 'رمز التحقق غير صحيح.');
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
            $this->session->set_flashdata('error_message', 'تعذر إتمام الطلب. أعد المحاولة.');
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
        $page_data['page_title'] = 'تأكيد الدخول من جهاز جديد';
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);
    }
    
    public function fb_validate_login($access_token = "", $fb_user_id = "") {
        $this->social_login_modal->fb_validate_login($access_token, $fb_user_id);
    }








    /**
     * TQ-PHONE-NORM — يطبع الرقم قبل فحصه: تزال المسافات والشرط والأقواس،
     * وتحول الأرقام العربية، وتقص البادئة الدولية (`+966` / `00966`) والصفر.
     * فيقبل `0501234567` و`+966 50 123 4567` و`٠٥٠١٢٣٤٥٦٧` جميعا، ويخزن
     * `501234567` موحدا.
     *
     * وهو دالة لا سطور مكررة: بوابتا المعلم وولي الأمر تسألان الرقم نفسه،
     * ونسختان من قاعدة واحدة تفترقان عند أول تعديل يصيب إحداهما.
     */
    private function tq_normalize_phone($raw)
    {
        $p = strtr((string) $raw, array(
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
            '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ));
        $p = preg_replace('/[^0-9]/', '', $p);
        $p = preg_replace('/^(?:00966|966)/', '', $p);
        return preg_replace('/^0/', '', $p);
    }

    /**
     * TQ-PHONE-DUP — رقم الجوال من الحقل الذي يخص البوابة المختارة.
     *
     * كان لوحا المعلم وولي الأمر في `sign_up.php` يحملان كلاهما
     * `name="phone"`. وكلاهما في الصفحة دائما — `hidden` يخفي عن العين
     * ولا يمنع الإرسال — فالطلب يحمل الاسم مرتين، وPHP يبقي الأخير:
     * حقل ولي الأمر الفارغ. أي أن **كل تسجيل معلم** كان يرد
     * «رقم الجوال غير صحيح» مهما كتب في الحقل.
     *
     * فصار لكل بوابة اسمها (`teacher_phone` · `parent_phone`)، و`phone`
     * تقرأ احتياطا: نموذج مخبأ في متصفح، أو تطبيق يرسل الاسم القديم،
     * لا ينبغي أن يكسر بترحيل في الواجهة.
     */
    private function tq_gate_phone($gate)
    {
        foreach (array($gate . '_phone', 'phone') as $field) {
            $raw = trim((string) $this->input->post($field));
            if ($raw !== '') {
                return $this->tq_normalize_phone($raw);
            }
        }
        return '';
    }

    public function register()
    {
        $this->tq_guard_origin('sign_up');

        if ($this->crud_model->check_recaptcha() == false && (get_frontend_settings('recaptcha_status') == true || get_frontend_settings('recaptcha_status_v3') == true)) {
            $this->session->set_flashdata('error_message', 'تعذر التحقق من أنك لست آليا. أعد المحاولة.');
            redirect(site_url('login'), 'refresh');
        }

        /* TQ-REGISTER-GUARD — التحقق في الخادم لا في المتصفح.
           النمط الصحيح مطبق في نموذج التواصل ومفقود هنا: يتحقق من
           صيغة البريد هناك ولا يتحقق منها في إنشاء حساب، ويطلب تأكيد
           كلمة المرور في الاستعادة ولا يطلب في الإنشاء. */
        $tq_first = trim((string) $this->input->post('first_name'));
        $tq_last  = trim((string) $this->input->post('last_name'));
        $tq_email = trim((string) $this->input->post('email'));
        $tq_pass  = (string) $this->input->post('password');
        $tq_conf  = (string) $this->input->post('password_confirm');
        /* البوابة قيمة مغلقة لا نص حر: تقبل من قائمة، وما سواها طالب.
           وكانت تمرر كما جاءت فتدخل عمود `tq_gate` وتقرر شروط التحقق.
           و«إدارة» ليست منها: لا تسجل من هذه الصفحة بحال. */
        $tq_gate  = (string) $this->input->post('tq_gate');
        if (!in_array($tq_gate, array('teacher', 'parent'), TRUE)) { $tq_gate = 'student'; }
        $tq_age   = (int) $this->input->post('age');
        $tq_guard = trim((string) $this->input->post('guardian_email'));
        $tq_grade = (int) $this->input->post('grade_id');
        $tq_phone = '';

        /* ما كتب يعود بعد الرفض — عدا كلمتي المرور. */
        $tq_old = array(
            'tq_gate'        => $tq_gate,
            'first_name'     => $tq_first,
            'last_name'      => $tq_last,
            'email'          => $tq_email,
            'age'            => ($tq_age > 0 ? $tq_age : ''),
            'grade_id'       => ($tq_grade > 0 ? $tq_grade : ''),
            'guardian_email' => $tq_guard,
            'phone'          => $this->tq_gate_phone($tq_gate),
            'message'        => trim((string) $this->input->post('message')),
        );

        $tq_err = '';
        if (mb_strlen($tq_first) < 2 || mb_strlen($tq_first) > 40) {
            $tq_err = 'اكتب اسمك الأول (حرفان على الأقل).';
        } elseif (mb_strlen($tq_last) < 2 || mb_strlen($tq_last) > 40) {
            $tq_err = 'اكتب اسم عائلتك (حرفان على الأقل).';
        } elseif (!filter_var($tq_email, FILTER_VALIDATE_EMAIL) || mb_strlen($tq_email) > 50) {
            /* خمسون: طول `users.email`. وما زاد كان يقص عند الحفظ
               فينشأ حساب ببريد لا يصل إليه شيء — ولا استعادة له. */
            $tq_err = 'البريد الإلكتروني غير صحيح، أو أطول من خمسين محرفا.';
        } elseif (mb_strlen($tq_pass) < 8) {
            $tq_err = 'كلمة المرور ثمانية محارف على الأقل.';
        } elseif (strlen($tq_pass) > 72) {
            /* bcrypt يقص عند اثنتين وسبعين بايت بلا إشعار: كلمة أطول
               تحفظ مقصوصة، فيدخل صاحبها بأولها ويظن الباقي محسوبا. */
            $tq_err = 'كلمة المرور أطول من اللازم. اجعلها دون اثنتين وسبعين خانة.';
        } elseif ($tq_pass !== $tq_conf) {
            $tq_err = 'كلمتا المرور غير متطابقتين.';
        } elseif ((string) $this->input->post('accept_terms') !== '1') {
            $tq_err = 'لا بد من الموافقة على الشروط وسياسة الخصوصية.';
        } elseif ($tq_gate === 'student') {
            /* TQ-AGE-REQUIRED — العمر محور التصنيف، وهو الذي يقرر هل
               يطلب بريد ولي الأمر أصلا. وكان يفحص بـ`$tq_age > 0` فقط:
               حقل فارغ يعطي صفرا فيمر الشرط كله، ويسجل قاصر بلا موافقة
               ولي أمر لأنه ترك خانة العمر خالية. */
            if ($tq_age < 5 || $tq_age > 99) {
                $tq_err = 'اكتب عمرا صحيحا بين 5 و99.';
            } elseif ($tq_age < 15 && !filter_var($tq_guard, FILTER_VALIDATE_EMAIL)) {
                /* دون الخامسة عشرة: بريد ولي الأمر شرط لا حقل اختياري. */
                $tq_err = 'دون الخامسة عشرة نحتاج بريد ولي أمرك.';
            } elseif ($tq_grade > 0
                      && $this->db->where(array('id' => $tq_grade, 'active' => 1))
                                  ->count_all_results('grades') === 0) {
                /* الصف يأتي من قائمة، والقائمة تحرر في الطلب: معرف لا
                   يقابل صفا فعالا كان يحفظ كما هو فيصير الحساب في صف
                   لا وجود له. */
                $tq_err = 'الصف الدراسي المختار غير متاح. اختر من القائمة.';
            }
        } elseif ($tq_gate === 'teacher') {
            /* TQ-TEACHER-GATE — التسجيل معلما يفحص كاملا **قبل** إنشاء
               الحساب. وكان فحص الوثيقة يأتي بعد بناء الصف وقبل حفظه،
               داخل `if(get_settings('allow_instructor'))` — فلو أغلق
               الإعداد مر الطلب بلا وثيقة ولا صف طلب: حساب معلم موقوف
               (`status=0`) لا يوجد في قائمة الطلبات ليعتمده أحد. */
            if (!get_settings('allow_instructor')) {
                $tq_err = 'التسجيل معلما متوقف حاليا. تواصل معنا للانضمام.';
            } else {
                $tq_phone = $this->tq_gate_phone('teacher');

                $tq_doc = isset($_FILES['document']) ? $_FILES['document'] : NULL;
                $tq_doc_ext = ($tq_doc && !empty($tq_doc['name']))
                            ? strtolower((string) pathinfo($tq_doc['name'], PATHINFO_EXTENSION)) : '';

                if (!preg_match('/^5[0-9]{8}$/', $tq_phone)) {
                    $tq_err = 'رقم الجوال غير صحيح. اكتبه هكذا: 0501234567';
                } elseif ($tq_doc === NULL || empty($tq_doc['name'])) {
                    $tq_err = 'مستند التعريف مطلوب لطلب الانضمام معلما.';
                } elseif ((int) $tq_doc['error'] !== UPLOAD_ERR_OK) {
                    /* رفع فشل يصل بـ`name` مملوءا و`tmp_name` فارغا؛
                       فبلا هذا الفحص ينشأ الحساب وتسجل الوثيقة باسم
                       ملف لم ينسخ — طلب اعتماد بمرفق غير موجود. */
                    $tq_err = ((int) $tq_doc['error'] === UPLOAD_ERR_INI_SIZE
                            || (int) $tq_doc['error'] === UPLOAD_ERR_FORM_SIZE)
                            ? 'حجم المستند أكبر مما يقبله الخادم. اختر ملفا أصغر.'
                            : 'تعذر رفع المستند. حاول مرة أخرى.';
                } elseif (!in_array($tq_doc_ext, array('pdf', 'jpg', 'jpeg', 'png'), TRUE)) {
                    $tq_err = 'صيغة المستند غير مقبولة. المقبول: PDF · JPG · PNG.';
                } elseif ((int) $tq_doc['size'] > 5 * 1024 * 1024) {
                    $tq_err = 'حجم المستند أكبر من خمسة ميغابايت.';
                }
            }
        } elseif ($tq_gate === 'parent') {
            /* ولي الأمر: حسابه يفتح فورا ولا ينتظر اعتمادا — صفته وحدها
               لا تكشف بيانات أحد، والتقارير لا تفتح إلا برابط يوافق عليه
               الطالب نفسه (`parent_links` ولها مشغلا موافقة في القاعدة).
               والجوال مطلوب: عليه تصل تنبيهات الأبناء. */
            $tq_phone = $this->tq_gate_phone('parent');
            if (!preg_match('/^5[0-9]{8}$/', $tq_phone)) {
                $tq_err = 'رقم الجوال غير صحيح. اكتبه هكذا: 0501234567';
            }
        }
        if ($tq_err !== '') {
            $this->session->set_flashdata('error_message', $tq_err);
            $this->session->set_flashdata('tq_old', $tq_old);
            redirect(site_url('sign_up') . ($tq_gate !== 'student' ? '?as=' . $tq_gate : ''),
                     'location', 302);
            return;
        }

        /* الخامسة عشرة فما فوق: بريد ولي الأمر يطرح ولا يحفظ. الحقل
           يخفى في الواجهة ولا يمسح، فقيمة كتبت ثم رفع العمر كانت تصل
           وتخزن — بيانات شخص ثالث بلا سبب ولا موافقة. */
        if ($tq_gate !== 'student' || $tq_age >= 15) {
            $tq_guard = '';
        }

        $data['first_name'] = html_escape($tq_first);
        $data['last_name']  = html_escape($tq_last);
        $data['email']  = html_escape($tq_email);
        $data['password']  = tq_password_hash($tq_pass);

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
            if ($tq_phone !== '') {
                $_POST['phone'] = $tq_phone;
                $data['phone']  = $tq_phone;   /* الرقم يحفظ على الحساب كما يحفظ على الطلب */
            }
            $data['tq_gate'] = $tq_gate;
            $data['terms_accepted_at'] = date('Y-m-d H:i:s');
            if ($tq_gate === 'student') {
                $data['age'] = $tq_age;
                if ($tq_guard !== '') { $data['guardian_email'] = $tq_guard; }
                if ($tq_grade > 0)    { $data['grade_id'] = $tq_grade; }
            }
            /* الفحص كله جرى أعلاه قبل بناء الصف — فلم يبق هنا إلا القرار.
               و`instructor_application()` تبحث عن المستخدم بـ`$_POST['email']`
               بينما يحفظ الصف `html_escape($email)`؛ فبريد فيه فاصلة عليا
               (وهي حرف مقبول) يكتب `&#39;` في العمود ولا يطابق ما تبحث به،
               فلا ينشأ صف الطلب ولا يظهر خطأ. فيوحد الاسمان هنا. */
            $instructor_apply = ($tq_gate === 'teacher');
            $_POST['email'] = $data['email'];
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

            /* ولي الأمر يخبر في المسارين: تأكيد البريد شأن الطالب،
               وإخطار ولي أمره لا يتوقف عليه. */
            $this->tq_tell_guardian($tq_guard, $tq_first . ' ' . $tq_last, $tq_email, $tq_age);

            if (get_settings('student_email_verification') == 'enable') {
                $this->email_model->send_email_verification_mail($data['email'], $verification_code);

                if ($validity === 'unverified_user') {
                    $this->session->set_flashdata('info_message', 'لهذا البريد حساب بالفعل. أكد بريدك بالرمز المرسل إليك.');
                } else {
                    $this->session->set_flashdata('flash_message', 'أنشئ حسابك. راجع بريدك واكتب رمز التأكيد ليفتح.');
                }
                $this->session->set_userdata('register_email', $this->input->post('email'));
                redirect(site_url('sign_up/verification_code'), 'refresh');
            } else {
                if(isset($user_id)){
                    $this->email_model->signup_mail($user_id);
                }
                /* TQ-GATE-MSG — المعلم يخرج من هنا بحساب `status=0`، وشاشة
                   الدخول ترد كل موقوف بـ«بيانات دخول غير صحيحة». فمن سجل
                   معلما ثم حاول الدخول يقرأ أن كلمة مروره خاطئة، ويعيد
                   التسجيل. الرسالة تقول له الحقيقة قبل أن يحاول. */
                if ($tq_gate === 'teacher') {
                    $tq_done = 'استلمنا طلبك للانضمام معلما. تراجعه الإدارة ونتواصل معك، ولن يفتح الدخول قبل الاعتماد.';
                } elseif ($tq_gate === 'parent') {
                    $tq_done = 'أنشئ حسابك. سجل الدخول ثم اربط أبناءك من لوحتك.';
                } else {
                    $tq_done = 'أنشئ حسابك بنجاح. سجل الدخول للمتابعة.';
                }
                $this->session->set_flashdata('flash_message', $tq_done);
                redirect(site_url('login'), 'refresh');
            }
        } else {
            $this->session->set_flashdata('error_message', 'لهذا البريد حساب بالفعل. سجل الدخول، أو استعد كلمة المرور إن نسيتها.');
            redirect(site_url('login'), 'refresh');
        }
    }

    /**
     * TQ-GUARDIAN-MAIL — بريد ولي الأمر كان يجمع ولا يستعمل.
     *
     * صفحة التسجيل تشترط بريد ولي الأمر لمن هو دون الخامسة عشرة، وتقول
     * له صراحة: «نرسل إليه طلب موافقة قبل تفعيل الحساب». والعمود
     * `users.guardian_email` يكتب فعلا — **ولا يرسل إليه شيء أبدا**.
     * أي أن الصفحة تعد بما لا يقع، وتجمع بريد شخص ثالث بلا أن تخبره.
     *
     * فترسل الرسالة هنا. وهي إخطار لا بوابة: الحساب يفتح كما كان يفتح،
     * ولا يوقف على رد لا يضمن أن يأتي — ولكن ولي الأمر يعلم، ويعرف كيف
     * يتصرف. والحساب لا ينشئ رابطا في `parent_links` من هنا: ذلك يفتح
     * تقارير الطالب، ولا يفتحها إلا الطالب بنفسه.
     *
     * وإن لم يكن البريد الصادر مضبوطا فلا شيء يقع ولا خطأ يظهر —
     * `Taqdar_mail_model` يرد `false` بهدوء.
     */
    private function tq_tell_guardian($guardian_email, $student_name, $student_email, $age)
    {
        $guardian_email = trim((string) $guardian_email);
        if ($guardian_email === '' || !filter_var($guardian_email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $this->load->model('taqdar_mail_model');
        $this->taqdar_mail_model->send_lines(
            $guardian_email,
            'حساب جديد في منصة تقدر باسم ' . trim($student_name),
            array(
                'السلام عليكم،',
                'أنشئ حساب في منصة تقدر التعليمية باسم ' . trim($student_name)
                . ' (' . $student_email . ')، وذكر أن عمره ' . (int) $age . ' سنة'
                . ' وأن بريدك هو بريد ولي أمره.',
                'نخبرك بذلك لأن المنصة لا تفتح حساب من هو دون الخامسة عشرة بلا علم ولي أمره.',
                'وإن أردت متابعة تقدمه فأنشئ حساب «ولي أمر» واطلب ربط حسابه بحسابك — '
                . 'ويفتح الربط بموافقته هو.',
                'وإن لم تكن تعرف هذا الحساب فتواصل معنا ونغلقه.',
            ),
            array('label' => 'أنشئ حساب ولي أمر', 'href' => site_url('sign_up') . '?as=parent')
        );
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
        $page_data['page_title'] = 'استعادة كلمة المرور';
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page_data);
    }

    function forgot_password($from = "")
    {
        $this->tq_guard_origin('login/forgot_password_request');

        if ($this->crud_model->check_recaptcha() == false && (get_frontend_settings('recaptcha_status') == true || get_frontend_settings('recaptcha_status_v3') == true)) {
            $this->session->set_flashdata('error_message', 'تعذر التحقق من أنك لست آليا. أعد المحاولة.');
            redirect(site_url('login'), 'refresh');
        }
        $email = trim((string) $this->input->post('email'));
        $back  = site_url('login/forgot_password_request');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->set_flashdata('error_message', 'اكتب بريدا إلكترونيا صحيحا.');
            $this->session->set_flashdata('tq_old_email', $email);
            redirect($back, 'refresh');
        }

        // Throttle reset requests as well, so the mailbox cannot be flooded
        // and the endpoint cannot be used to enumerate accounts at speed.
        if (tq_auth_is_throttled($email, 'reset_request')) {
            $this->session->set_flashdata('error_message', tq_auth_throttle_message());
            $this->session->set_flashdata('tq_old_email', $email);
            redirect($back, 'refresh');
        }
        tq_auth_record_failure($email, 'reset_request');

        /* TQ-RESET-QUIET — الجواب واحد سواء وجد البريد أو لم يوجد.
           كان يرد «المستخدم غير موجود» على المجهول و«أرسلنا الرابط» على
           المعروف: نموذج مفتوح للعموم يقول عن أي عنوان أله حساب هنا أم
           لا — وحسابات هذه المنصة لطلاب وأولياء أمور. والخنق يبطئ
           التجريب ولا يمنع الجواب من أن يكون جوابا.

           ولا يخرج المستعمل الحقيقي بشيء أقل: من له حساب يصله الرابط. */
        $_POST['email'] = $email;   /* النموذج يبحث بالمقصوص كما يبحث المتحكم */
        $query = $this->db->get_where('users', array('email' => $email, 'status' => 1));
        if ($query->num_rows() > 0) {
            $this->crud_model->forgot_password();
        }
        /* `Crud_model::forgot_password` يودع «المستخدم غير موجود» حين
           يتعذر إنشاء الرمز — وهو التسريب نفسه من باب آخر. */
        $this->session->set_flashdata('error_message', NULL);
        $this->session->set_flashdata('flash_message',
            'إن كان لهذا البريد حساب لدينا فقد أرسلنا إليه رابط إعادة التعيين. راجع بريدك.');
        redirect($back, 'refresh');
    }

    function change_password($verification_code = ""){

        // The reset token is now 64 hex chars produced by random_bytes(32) and
        // is stored hashed (sha256) in tq_auth_password_resets. It is single
        // use, expires after TQ_AUTH_RESET_TTL and guessing it is throttled.
        $verification_code = str_replace(array(' ', '%20', '='), '', (string) $verification_code);

        if ($verification_code === "") {
            $this->session->set_flashdata('error_message', 'رابط غير صالح. اطلب رابط استعادة جديدا.');
            redirect(site_url('login'), 'refresh');
        }

        if (tq_auth_is_throttled('reset-token', 'reset_consume')) {
            $this->session->set_flashdata('error_message', tq_auth_throttle_message());
            redirect(site_url('login/forgot_password_request'), 'refresh');
        }

        $reset_request = tq_auth_find_reset_token($verification_code);
        if ($reset_request === FALSE) {
            tq_auth_record_failure('reset-token', 'reset_consume');
            $this->session->set_flashdata('error_message', 'انتهت صلاحية هذا الرابط أو استعمل من قبل. اطلب رابطا جديدا.');
            redirect(site_url('login/forgot_password_request'), 'refresh');
        }

        if(isset($_POST['new_password']) && isset($_POST['confirm_password']) && !empty($_POST['confirm_password']) && $verification_code){
            $this->tq_guard_origin('login/forgot_password_request');
            $new_password = $this->input->post('new_password');
            $confirm_password = $this->input->post('confirm_password');
            /* TQ-RESET-STRENGTH — الاستعادة كانت تقبل أي طول، والتسجيل
               يشترط ثمانية. فباب واحد يفرض الحد وباب آخر يلغيه: من أراد
               كلمة من حرف واحد يطلب رابط استعادة ويضعها. والحدان يوحدان،
               والسقف كذلك — bcrypt يقص بعد اثنتين وسبعين بايت بصمت. */
            if (mb_strlen((string) $new_password) < 8 || strlen((string) $new_password) > 72) {
                $this->session->set_flashdata('error_message', 'كلمة المرور ثمانية محارف على الأقل.');
                redirect(site_url('login/change_password/'.$verification_code), 'refresh');
            }
            if($new_password == $confirm_password):
                if ($this->crud_model->change_password_from_forgot_passord($verification_code)) {
                    $this->session->set_flashdata('flash_message', 'تم تغيير كلمة المرور. سجل الدخول بها الآن.');
                } else {
                    $this->session->set_flashdata('error_message', 'انتهت صلاحية هذا الرابط أو استعمل من قبل. اطلب رابطا جديدا.');
                }
                redirect(site_url('login'), 'refresh');
            else:
                $this->session->set_flashdata('error_message', 'كلمتا المرور غير متطابقتين.');
                redirect(site_url('login/change_password/'.$verification_code), 'refresh');
            endif;
        }


        $page_data['verification_code'] = $verification_code;
        $page_data['page_name'] = 'change_password_from_forgot_password';
        $page_data['page_title'] = 'كلمة مرور جديدة';
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

            $this->session->set_flashdata('flash_message', 'تم تأكيد بريدك. سجل الدخول للمتابعة.');
            $this->session->set_userdata('register_email', null);

            echo true;
        } else {
            tq_auth_record_failure($email, 'verify_email');
            $this->session->set_flashdata('error_message', 'رمز التأكيد غير صحيح. راجع الرسالة أو اطلب رمزا جديدا.');
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
