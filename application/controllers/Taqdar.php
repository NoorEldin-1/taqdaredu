<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * متحكّم بوّابات تقدّر.
 *
 * كل مسار هنا يفرض دوره في الخادم لا في الواجهة — إخفاء زرّ ليس صلاحية.
 * والعرض يمرّ بغلاف الثيم نفسه (frontend/<theme>/index) فلا يخرج عن النظام.
 */
class Taqdar extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        $this->load->database();
        $this->load->model('crud_model');
        $this->load->model('user_model');

        /**
         * المنطقة الزمنية تُضبط هنا كما تُضبط في Taqdar_admin وTaqdar_cron.
         *
         * غيابها كان يجعل هذا المتحكّم يكتب بتوقيت الخادم (UTC) بينما تكتب
         * اللوحة والمهامّ الدورية بتوقيت المنصّة — فيختلف الطابعان ثلاث ساعات
         * في `audit_log` نفسه، ويصير السجلّ غير قابل للترتيب ولا للتدقيق.
         * الإعداد الناقص لا يبرّر طابعًا عشوائيًا، فله بديل صريح.
         */
        $tz = trim((string) get_settings('timezone'));
        if ($tz === '' || !in_array($tz, DateTimeZone::listIdentifiers(), true)) {
            $tz = 'Asia/Riyadh';
        }
        date_default_timezone_set($tz);
    }

    /* ---------------------------------------------------------------- */

    private function theme()
    {
        return 'taqdar'; // بوّابات تقدّر تعرض بثيمها دائمًا، فلا تنتظر تبديل الموقع العام
    }

    /** يعرض صفحة داخل غلاف الثيم. */
    private function show($page, $title, $data = [])
    {
        $data['page_name']  = $page;
        $data['page_title'] = $title;
        $this->load->view('frontend/' . $this->theme() . '/index', $data);
    }

    /** يشترط تسجيل الدخول — وإلا إلى صفحة الدخول. */
    private function require_login()
    {
        if (!$this->session->userdata('user_id')) {
            redirect(site_url('login'), 'refresh');
        }
    }

    /**
     * يشترط دورًا بعينه — عبر tq_guard الموحّدة في taqdar_role_helper.
     *
     * قبل هذا كان كل متحكّم يشتقّ الدور بطريقته: هنا `is_instructor` وحدها،
     * وفي Taqdar_gate `role_id` أيضًا — فمرّ الأدمن من باب ومُنع من آخر.
     * الاشتقاق الآن في موضع واحد، والأدمن لا يُستثنى من الفصل.
     */
    private function require_role($role)
    {
        tq_guard($role);
        return $this->user_model->get_all_user($this->session->userdata('user_id'))->row_array();
    }

    /** أعداد الشارات في القائمة والترويسة — استعلام واحد لكل نوع. */
    private function counts($uid)
    {
        // أعمدة Academy الفعلية: message(receiver, read_status) و notifications(to_user, status)
        $unread_msgs = (int) $this->db->where('receiver', $uid)
                                      ->where('read_status', 0)
                                      ->count_all_results('message');

        $unread_notif = (int) $this->db->where('to_user', $uid)
                                       ->where('status', 0)
                                       ->count_all_results('notifications');

        // المراجعة المستحقّة من المستودع نفسه الذي تقرأ منه الصفحة،
        // فلا يفترق رقم الشارة عن عدد ما تعرضه
        $due = 0;
        try {
            $this->load->model('taqdar_repo_model');
            $due = (int) $this->taqdar_repo_model->count_due_reviews($uid);
        } catch (Throwable $e) {
            $due = 0;   // شارةٌ ناقصة أهون من قائمة مبتورة
        }

        return ['messages' => $unread_msgs, 'notifications' => $unread_notif, 'reviews' => $due];
    }

    /* ---- بوّابة الطالب --------------------------------------------- */

    public function index()          { redirect(site_url('taqdar/home'), 'refresh'); }
    public function home()           { $this->student('tq_home', 'الرئيسية'); }
    public function lessons()        { $this->student('tq_lessons', 'دروسي'); }
    // القائمة الجانبية تصدر `student/reviews` وشاشتها `tq_reviews.php` قائمة،
    // ولم تكن لها نقطة في المتحكّم — فكان بندٌ ثابت في قائمة كل طالب يقود إلى 404.
    public function reviews()        { $this->student('tq_reviews', 'المراجعة'); }
    public function tasks()          { $this->student('tq_tasks', 'مهامي'); }
    public function exams()          { $this->student('tq_exams', 'اختباراتي'); }
    public function materials()      { $this->student('tq_materials', 'المواد التعليمية'); }
    public function reports()        { $this->student('tq_reports', 'المتابعة والتقارير'); }
    public function favourites()     { $this->student('tq_favourites', 'المفضلة'); }
    public function messages()       { $this->student('tq_messages', 'رسائلي'); }
    public function notifications()  { $this->student('tq_notifications', 'الإشعارات'); }
    public function calendar()       { $this->student('tq_calendar', 'التقويم'); }
    public function certificates()   { $this->student('tq_certificates', 'الشهادات'); }
    public function payments()       { $this->student('tq_payments', 'المدفوعات'); }
    public function settings()       { $this->student('tq_settings', 'الإعدادات'); }

    /**
     * مشغّل الدرس. لا يستعلم عن الدرس هنا — الصفحة تطلبه من `taqdar_gate`
     * الذي يحسم القفل في الخادم. فلو بُني القفل هنا لأمكن تجاوزه بتعديل
     * الواجهة، ولصار «قفلًا» بصريًّا لا آلية.
     */
    public function lesson($course_id = 0, $lesson_id = 0)
    {
        $this->require_role('student');
        $uid = $this->session->userdata('user_id');
        $this->show('tq_lesson', 'الدرس', array(
            'tq_counts' => $this->counts($uid),
            'user_id'   => $uid,
            'course_id' => (int) $course_id,
            'lesson_id' => (int) $lesson_id,
        ));
    }

    /** حصص بالطلب: طبقة مستقلّة بجوار المنهج لا داخله. */
    public function on_demand()      { $this->student('tq_on_demand', 'حصص بالطلب'); }

    private function student($page, $title)
    {
        // الطالب محروس كغيره: الأدمن الذي يفتح بوّابة الطالب يرى شاشات فارغة
        // فيظنّها معطوبة، والاختبار من حسابه يعطي نتيجة كاذبة.
        $this->require_role('student');
        $uid = $this->session->userdata('user_id');
        $this->show($page, $title, [
            'tq_counts' => $this->counts($uid),
            'user_id'   => $uid,
        ]);
    }

    /* ---- صفحات عامّة داخل الثيم ------------------------------------ */

    /** عامّتان: تُعرضان لأي زائر، فالباقات والأقسام جزء من الموقع لا من بوّابة. */
    public function plans()      { $this->show('plans', 'الاشتراكات'); }
    public function categories() { $this->show('categories', 'الأقسام'); }

    /**
     * نتائج البحث العامّ.
     *
     * منفصلةٌ عن `search()`: تلك تخصّ من له بوّابة وتُبقيه فيها، وهذه
     * لكلّ زائر. وكانت `search()` تُرسل الزائر إلى `/courses?query=` —
     * وقد صارت تحويلًا إلى `/plans` ولا تقرأ `query`، فكان بحثه يضيع.
     */
    public function site_search()
    {
        $q = trim((string) $this->input->get('q', true));
        $this->load->model('taqdar_site_model', 'tq_m');
        $this->show('site_search', $q === '' ? 'البحث' : 'نتائج البحث عن: ' . $q,
                    array('tq_q' => $q, 'tq_res' => $this->tq_m->site_search($q)));
    }

    /**
     * البحث — من داخل البوّابة لا خارجها.
     *
     * كان يحوّل إلى `home/courses`: صفحة الكتالوج العامّة بترويسة الموقع
     * وتذييله لا بشريط البوّابة. فأوّل بحث يُخرج الطالب من بوّابته إلى شاشة
     * لا رابط فيها يعيده. المقصد الآن يُشتقّ من الدور ويبقى داخل البوّابة،
     * ويُمرَّر النصّ في `q` فتلتقطه شاشة النتائج متى بُنيت.
     *
     * والزائر وحده يُرسل إلى الكتالوج العامّ — لأنه لا بوّابة له أصلًا.
     */
    public function search()
    {
        $q    = trim((string) $this->input->get('q', true));
        $role = tq_role();

        if ($role === 'guest') {
            /* كان `/courses?query=` — وهي الآن تحويلٌ إلى `/plans` لا تقرأ
               `query`. فيُرسل الزائر إلى صفحة النتائج العامّة. */
            redirect(site_url('search') . ($q === '' ? '' : '?q=' . rawurlencode($q)), 'location', 302);
        }

        // شاشة نتائج داخل البوّابة إن رُكِّبت — وإلّا لا نخرج بالمستخدم منها
        if (in_array($role, array('student', 'teacher', 'parent'), true)
            && is_file(APPPATH . 'views/frontend/' . $this->theme() . '/tq_search.php')) {
            $uid = (int) $this->session->userdata('user_id');
            $this->show('tq_search', 'نتائج البحث', array(
                'tq_counts' => $this->counts($uid),
                'user_id'   => $uid,
                'tq_query'  => $q,
            ));
            return;
        }

        $landing = array(
            'student' => 'student/lessons',
            'teacher' => 'teacher/courses',
            'parent'  => 'parent/children',
        );
        if (!isset($landing[$role])) {
            redirect(tq_home_for($role), 'location', 302);
        }

        if ($q !== '') {
            $this->session->set_flashdata('flash_message',
                'بحثت عن «' . $q . '» — وشاشة النتائج داخل البوّابة قيد الإعداد.');
        }
        redirect(site_url($landing[$role]) . ($q === '' ? '' : '?q=' . rawurlencode($q)), 'location', 302);
    }

    /**
     * تصدير بيانات الحساب — ملفّ JSON بكل ما يخصّ المستخدم.
     * حقّ يُنفَّذ لا سياسة تُكتب.
     */
    /* =====================================================================
       الاشتراك
       ===================================================================== */

    public function subscription()
    {
        $this->require_role('student');
        $uid = $this->session->userdata('user_id');
        $this->load->model('taqdar_billing_model');

        $cur = $this->taqdar_billing_model->active_subscription($uid);
        if (!$cur) {
            // آخر اشتراك مهما كانت حاله: المعلّق تنتظره فاتورة، والمنتهي
            // يحتاج صاحبه أن يعرف أنه انتهى — لا أن يُقال له «لا اشتراك لك».
            $cur = $this->db->where('user_id', (int) $uid)
                            ->order_by('id', 'DESC')->limit(1)
                            ->get('subscriptions')->row_array();
        }
        if ($cur) {
            $plan = $this->taqdar_billing_model->plan($cur['plan_id']);
            $cur['plan_name'] = $plan ? $plan['name_ar'] : '—';
        }

        $this->show('tq_subscription', 'اشتراكي', array(
            'tq_counts' => $this->counts($uid),
            'user_id'   => $uid,
            'current'   => $cur,
            'invoices'  => $this->taqdar_billing_model->invoices_of($uid),
        ));
    }

    /** الاشتراك يغيّر الحساب، فلا يُنفَّذ بطلب GET قد يجلبه زاحف أو صورة. */
    /** شراء مسار — وحدة البيع الأساسية. POST وحده. */
    /** POST teacher/courses/save — إنشاء كورس أو تعديله. */
    public function courses_save()
    {
        $this->require_role('teacher');
        if ($this->input->method(true) !== "POST") show_404();

        $tid = (int) $this->session->userdata('user_id');
        $this->load->model('taqdar_teacher_model');
        $r = $this->taqdar_teacher_model->save_course($tid, $this->input->post(null, false));

        $this->session->set_flashdata($r['ok'] ? 'flash_message' : 'error_message',
            $r['ok'] ? $r['message'] : implode(' ', $r['errors']));
        $this->session->set_flashdata($r['ok'] ? 'tq_ok' : 'tq_error',
            $r['ok'] ? $r['message'] : implode(' ', $r['errors']));
        redirect(base_url('teacher/courses'));
    }

    /**
     * POST student/parent-link — يوافق الطالب على ربط وليّ أمره أو يرفضه.
     *
     * الموافقة بيان قانوني يوقّعه صاحبه: النموذج يرفض أن يوقّعها وليّ الأمر،
     * وهذا المسار يمنح الطالب القدرة على توقيعها فعلًا.
     */
    public function parent_link_respond()
    {
        $this->require_role('student');
        if ($this->input->method(true) !== "POST") show_404();

        $uid     = (int) $this->session->userdata('user_id');
        $link_id = (int) $this->input->post('link_id');
        $action  = (string) $this->input->post('act');

        $this->load->model('taqdar_parent_model');

        if ($action === 'reject') {
            $r = $this->taqdar_parent_model->revoke_link($link_id, $uid);
            $msg_ok = 'رُفض الطلب.';
        } else {
            $r = $this->taqdar_parent_model->grant_consent($link_id, $uid);
            $msg_ok = 'وافقتَ على الربط، ويستطيع وليّ أمرك متابعة تقدّمك الآن.';
        }

        $ok = !empty($r['ok']);
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message',
            $ok ? $msg_ok : (isset($r['errors']) ? implode(' ', $r['errors']) : 'تعذّر تنفيذ الطلب.'));
        redirect(base_url('student/settings?s=profile'));
    }

    public function subscribe_path()
    {
        $this->require_role('student');
        if ($this->input->method(true) !== "POST") show_404();

        $this->load->model('taqdar_billing_model');
        $r = $this->taqdar_billing_model->subscribe_path(
            $this->session->userdata('user_id'),
            (int) $this->input->post('path_id'),
            'manual'
        );

        if (!$r['ok']) {
            $this->session->set_flashdata('error_message', implode(' ', $r['errors']));
            redirect(base_url('student'));
            return;
        }

        $this->session->set_flashdata('flash_message',
            'صدرت فاتورتك. حوّل قيمتها ويُفتح المسار بعد التحقّق من الحوالة.');
        redirect(base_url('student/subscription'));
    }

    public function subscribe()
    {
        $this->require_role('student');
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_billing_model');
        $r = $this->taqdar_billing_model->subscribe(
            $this->session->userdata('user_id'),
            (int) $this->input->post('plan_id'),
            'manual'
        );

        if (!$r['ok']) {
            $this->session->set_flashdata('error_message', implode(' ', $r['errors']));
            redirect(base_url('plans'));
        }

        $this->session->set_flashdata('flash_message', !empty($r['free'])
            ? 'فُعِّلت باقتك المجّانية.'
            : 'صدرت فاتورتك. حوّل قيمتها ويُفعَّل اشتراكك بعد التحقّق من الحوالة.');
        redirect(base_url('student/subscription'));
    }

    public function subscription_cancel()
    {
        $this->require_role('student');
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_billing_model');
        $sub = $this->taqdar_billing_model->active_subscription($this->session->userdata('user_id'));

        if ($sub) {
            $this->taqdar_billing_model->cancel($sub['id'], 'ألغاه الطالب');
            $this->session->set_flashdata('flash_message',
                'أُوقف التجديد — ويبقى اشتراكك صالحًا حتى تاريخ انتهائه.');
        }
        redirect(base_url('student/subscription'));
    }


    /** كل نماذج صفحة الإعدادات تصبّ هنا؛ القرار في النموذج لا في الباب. */
    public function settings_save()
    {
        $this->require_role('student');
        if ($this->input->method(true) !== "POST") show_404();

        $uid = (int) $this->session->userdata('user_id');
        $this->load->model('taqdar_settings_model');
        $r = $this->taqdar_settings_model->handle($uid, (string) $this->input->post('action', true));

        $r['ok']
            ? $this->session->set_flashdata('flash_message', $r['message'])
            : $this->session->set_flashdata('error_message', implode(' ', $r['errors']));

        $section = (string) $this->input->post('s', true);
        if (!in_array($section, array("profile","security","alerts","prefs","billing","offline"), true)) {
            $section = isset($r['section']) ? $r['section'] : 'profile';
        }
        redirect(base_url('student/settings?s=' . $section));
    }


    /**
     * POST teacher/students/message
     * النطاق يُعاد فرضه هنا كاملًا: العرض يُخفي، والخادم وحده يمنع.
     */
    public function students_message()
    {
        $this->require_role('teacher');
        if ($this->input->method(true) !== "POST") show_404();

        $tid        = (int) $this->session->userdata('user_id');
        $student_id = (int) $this->input->post('student_id');
        $course_id  = (int) $this->input->post('course_id');
        $body       = trim((string) $this->input->post('message'));
        $back       = 'teacher/students' . ($course_id > 0 ? '?course=' . $course_id : '');

        if ($body === '') {
            $this->session->set_flashdata('error_message', 'اكتب نصّ الرسالة قبل الإرسال.');
            redirect(base_url($back));
            return;
        }
        if (mb_strlen($body) > 1000) $body = mb_substr($body, 0, 1000);

        $in_scope = (int) $this->db->query(
            'SELECT COUNT(*) AS n FROM `enrol` e JOIN `course` c ON c.id = e.course_id
              WHERE e.user_id = ? AND (c.creator = ? OR FIND_IN_SET(?, c.user_id) > 0)',
            array($student_id, $tid, $tid)
        )->row('n');

        if ($student_id < 1 || $in_scope < 1) {
            $this->session->set_flashdata('error_message', 'لا تُرسَل الرسائل إلا إلى طلاب كورساتك.');
            redirect(base_url($back));
            return;
        }

        $now  = time();
        $code = $this->db->query(
            'SELECT message_thread_code FROM `message_thread`
              WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?) LIMIT 1',
            array($tid, $student_id, $student_id, $tid)
        )->row('message_thread_code');

        if (!$code) {
            $code = random(30);
            $this->db->insert('message_thread', array(
                'message_thread_code'    => $code,
                'sender'                 => $tid,
                'receiver'               => $student_id,
                'last_message_timestamp' => $now,
            ));
        } else {
            $this->db->where('message_thread_code', $code)
                     ->update('message_thread', array('last_message_timestamp' => $now));
        }

        $this->db->insert('message', array(
            'message_thread_code' => $code,
            'message'             => html_escape($body),
            'sender'              => $tid,
            'receiver'            => $student_id,
            'timestamp'           => $now,
            'read_status'         => 0,
        ));

        $this->session->set_flashdata('flash_message', 'وصلت رسالتك إلى صندوق الطالب.');
        redirect(base_url($back));
    }

    public function export_data()
    {
        $this->require_login();
        $uid = (int) $this->session->userdata('user_id');

        $out = array('generated_at' => date('c'), 'account' => null, 'learning' => array(), 'payments' => array());

        $u = $this->db->where('id', $uid)->get('users')->row_array();
        if ($u) {
            unset($u['password'], $u['verification_code'], $u['payment_keys'], $u['sessions'], $u['temp']);
            $out['account'] = $u;
        }

        foreach (array('enrol', 'lesson_progress', 'attempts', 'answers', 'review_queue', 'skill_state') as $t) {
            if (!$this->db->table_exists($t)) continue;
            $col = in_array($t, array('enrol'), true) ? 'user_id' : 'student_id';
            if (!in_array($col, $this->db->list_fields($t), true)) continue;
            $out['learning'][$t] = $this->db->where($col, $uid)->get($t)->result_array();
        }

        $pcols = $this->db->list_fields('payment');
        $pid = in_array('user_id', $pcols, true) ? 'user_id' : null;
        if ($pid) $out['payments'] = $this->db->where($pid, $uid)->get('payment')->result_array();

        $this->output
             ->set_content_type('application/json; charset=utf-8')
             ->set_header('Content-Disposition: attachment; filename="taqdar-my-data-' . $uid . '.json"')
             ->set_output(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * حذف الحساب — **تجهيل لا محو**.
     * تُستبدل حقول الهوية بقيم مجهولة وتبقى القيود المالية بمعرّف مجهول،
     * لأن الالتزام الضريبي يوجب حفظ الفواتير. وهذا ما تنصّ عليه الوثيقة.
     */
    public function delete_account()
    {
        $this->require_login();
        $uid = (int) $this->session->userdata('user_id');

        if (tq_role($uid) === 'admin') {
            show_error('لا يُحذف حساب إداري من هنا. استعمل لوحة الإدارة.', 403, 'غير مسموح');
        }

        // التأكيد بـ POST وحده. رابط GET يحذف حسابًا قد يفتحه زاحف أو
        // استباق تحميل في المتصفّح — فيُجهَّل حساب بلا أن يطلب صاحبه شيئًا.
        if ($this->input->method(true) !== 'POST' || $this->input->post('confirm') !== 'yes') {
            $this->show('tq_delete_account', 'حذف الحساب', array(
                'tq_counts' => $this->counts($uid),
                'user_id'   => $uid,
            ));
            return;
        }

        $anon = 'deleted_' . $uid . '_' . substr(md5($uid . microtime(true)), 0, 8);
        $this->db->where('id', $uid)->update('users', array(
            'first_name'   => 'حساب',
            'last_name'    => 'محذوف',
            'email'        => $anon . '@deleted.invalid',
            'phone'        => '',
            'address'      => '',
            'biography'    => '',
            'image'        => '',
            'social_links' => '{}',
            'status'       => 0,
        ));

        if ($this->db->table_exists('audit_log')) {
            $this->db->insert('audit_log', array(
                'actor_id' => $uid, 'action' => 'account.anonymised',
                'entity'   => 'users:' . $uid,
                'before'   => json_encode(array('by' => 'self'), JSON_UNESCAPED_UNICODE),
                'after'    => json_encode(array('handle' => $anon), JSON_UNESCAPED_UNICODE),
                'ip'       => $this->input->ip_address(),
            ));
        }

        $this->session->sess_destroy();
        redirect(site_url('login'), 'location', 302);
    }

    /* ---- بوّابة المعلم ---------------------------------------------- */

    public function teacher($section = 'dashboard')
    {
        $map = [
            'dashboard' => ['tq_teacher_dashboard', 'لوحة المعلّم'],
            'courses'   => ['tq_teacher_courses',   'كورساتي'],
            'upload'    => ['tq_teacher_upload',    'رفع الدروس'],
            'questions' => ['tq_teacher_questions', 'بنك الأسئلة'],
            'marking'   => ['tq_teacher_marking',   'الواجبات والتصحيح'],
            'students'  => ['tq_teacher_students',  'طلابي'],
            'sessions'  => ['tq_teacher_sessions',  'الحصص'],
            'wallet'    => ['tq_teacher_wallet',    'المحفظة والأرباح'],
        ];
        if (!isset($map[$section])) show_404();

        $user = $this->require_role('teacher');
        [$page, $title] = $map[$section];

        $this->show($page, $title, [
            'tq_counts' => $this->counts($user['id']),
            'user_id'   => $user['id'],
        ]);
    }

    /* ---- بوّابة وليّ الأمر ------------------------------------------ */

    public function parent_portal($section = 'children')
    {
        $map = [
            'children' => ['tq_parent_children', 'أبنائي'],
            'child'    => ['tq_parent_child',    'تفاصيل الابن'],
            'reports'  => ['tq_parent_reports',  'التقارير'],
            'weekly'   => ['tq_parent_weekly',   'التقرير الأسبوعي'],
            'payments' => ['tq_parent_payments', 'المدفوعات'],
            'messages' => ['tq_parent_messages', 'الرسائل'],
            'alerts'   => ['tq_parent_alerts',   'الإشعارات'],
            'settings' => ['tq_parent_settings', 'الإعدادات'],
        ];
        if (!isset($map[$section])) show_404();

        $user = $this->require_role('parent');
        [$page, $title] = $map[$section];

        $this->show($page, $title, [
            'tq_counts' => $this->counts($user['id']),
            'user_id'   => $user['id'],
        ]);
    }

    /* =====================================================================
       مسارات الكتابة
       ---------------------------------------------------------------------
       دالّة العرض تعرض ولا تكتب.

       قبل هذا كان كل نموذج في بوّابتَي المعلّم ووليّ الأمر يرسل إلى مسار
       العرض نفسه، و`teacher()`/`parent_portal()` لا تفحصان نوع الطلب أصلًا:
       فيُستدعى الحارس، ثم تُعرض الصفحة كأن شيئًا لم يكن. لا كتابة، ولا خطأ،
       ولا رسالة — وهذا أسوأ من العطل الظاهر لأن المستخدم يظنّ أنه حفظ.

       فالكتابة الآن مسارات مستقلّة صريحة: POST وحده، ثم الدور، ثم الملكية،
       ثم تفويض العمل إلى نموذج، ثم تحويل 302 برسالة. و`redirect(...,'refresh')`
       متروك عمدًا: لا يتبعه curl فيبدو الفحص ناجحًا وهو لم يصل، ويكسر زرّ
       الرجوع في المتصفّح.
       ===================================================================== */

    /** حارس الكتابة. الرابط الذي يكتب بـ GET يستدعيه زاحف أو استباق تحميل. */
    private function write_guard($role)
    {
        if ($this->input->method(true) !== 'POST') show_404();
        return $this->require_role($role);
    }

    /**
     * ردّ المستخدم إلى صفحته برسالة — 302 دائمًا.
     *
     * الرسالة تُكتب بمفتاحَي المنصّة (`flash_message`/`error_message`) وبمفتاحَي
     * شاشات تقدّر (`tq_ok`/`tq_error`) معًا: الشاشات في البوّابات الثلاث لا
     * تقرأ بالاصطلاح نفسه، ورسالة لا تُقرأ كأنها لم تُكتب.
     */
    private function done($path, $ok, $message)
    {
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message', $message);
        $this->session->set_flashdata($ok ? 'tq_ok' : 'tq_error', $message);
        redirect(site_url($path), 'location', 302);
    }

    /** رسالة النتيجة من عقد النموذج الموحّد. */
    private function result_message($r, $success)
    {
        if (!empty($r['ok'])) {
            return isset($r['message']) && $r['message'] !== '' ? $r['message'] : $success;
        }
        // النماذج تشرح فشلها بمفتاحين مختلفين — وابتلاع الشرح أسوأ من الفشل
        if (!empty($r['errors']))  return implode(' ', (array) $r['errors']);
        if (!empty($r['message'])) return (string) $r['message'];
        return 'تعذّر تنفيذ الطلب.';
    }

    /**
     * تفويض العمل إلى نموذج قد لا يكون وُلد بعد.
     *
     * المسارات تُبنى قبل النماذج. ولو نودي على نموذج غائب لسقطت الصفحة بخطأ
     * قاتل بدل أن تقول ما ينقص — فيظنّ المجرِّب أن المسار نفسه مكسور.
     * فالفحص على ثلاث مراحل: وجود الملفّ، ثم إعلانه الصنف (ملفّ يُكتب الآن
     * قد يكون نصفه فيقتل `load->model`)، ثم وجود الدالّة.
     *
     * وتوقيع كل نموذج شأنه: فالثالث في الزوج وسائطه هو إن اختلفت عن العامّة —
     * لأن إجبار نموذج قائم على توقيع المتحكّم يكسره، والعكس أصحّ.
     *
     * @param array $candidates [اسم النموذج، اسم الدالّة، وسائط خاصّة؟] بترتيب الأفضلية
     * @param array $args       الوسائط العامّة
     * @return array عقد موحّد: ok | errors | message
     */
    private function delegate(array $candidates, array $args)
    {
        $wanted = array();

        foreach ($candidates as $pair) {
            $model  = $pair[0];
            $method = $pair[1];
            $call   = isset($pair[2]) && is_array($pair[2]) ? $pair[2] : $args;
            $wanted[] = ucfirst($model) . '::' . $method . '()';

            $file = APPPATH . 'models/' . ucfirst($model) . '.php';
            if (!is_file($file)) continue;

            $src = @file_get_contents($file);
            if ($src === false
                || !preg_match('/\bclass\s+' . preg_quote(ucfirst($model), '/') . '\b/i', $src)) {
                continue; // ملفّ قيد الكتابة: تحميله الآن خطأ قاتل
            }

            $this->load->model($model);
            if (!isset($this->$model) || !method_exists($this->$model, $method)) continue;

            $out = call_user_func_array(array($this->$model, $method), $call);

            if (is_array($out) && array_key_exists('ok', $out))  return $out;
            if (is_array($out) && isset($out['error'])) {
                return array('ok' => false, 'errors' => array((string) $out['error']));
            }
            /* نموذج يعيد عددًا لا حكمًا: صفرٌ نتيجةٌ صحيحة لا فشل — من مسح
               كل فتراته حفظ صفرًا وقد نجح. فـ(bool) هنا كانت تقلب النجاح فشلًا. */
            if (isset($pair[3]) && $pair[3] === 'numeric_ok' && is_numeric($out)) {
                return array('ok' => true, 'errors' => array(), 'count' => (int) $out);
            }
            return array('ok' => (bool) $out, 'errors' => array(), 'result' => $out);
        }

        return array(
            'ok'      => false,
            'pending' => true,
            'errors'  => array(
                'وصل الطلب وصلاحيتك صحيحة، والنموذج المنفِّذ لم يُركَّب بعد ('
                . implode(' أو ', array_unique($wanted)) . ').'
            ),
        );
    }

    /** أثر في audit_log لكل كتابة — عبر نموذج المستودع القائم إن وُجد. */
    private function trace($action, $entity, $payload = null)
    {
        if (!is_file(APPPATH . 'models/Taqdar_repo_model.php')) return;
        $this->load->model('taqdar_repo_model');
        if (!method_exists($this->taqdar_repo_model, 'audit')) return;

        $this->taqdar_repo_model->audit(
            (int) $this->session->userdata('user_id'), $action, $entity, null, $payload
        );
    }

    /* ---- الملكية: تُقرأ من قاعدة البيانات لا من الحقول المرسَلة --------- */

    /**
     * ملكية الكورس.
     *
     * `course.user_id` **قائمة معرّفات مفصولة بفواصل** لا معرّفًا واحدًا،
     * ومعه عمود `creator` للمنشئ. فمقارنة `(int) user_id` كانت تقرأ «12,37»
     * على أنها 12: تردّ المعلّم الثاني في كورس مشترك، وتردّ المنشئ نفسه إن
     * لم يكن أوّل القائمة. وهذا يمنع صاحب الحقّ لا المتطفّل — ويقول له عن
     * كورسه إنه «ليس كورسك». والصيغة هنا هي المعمول بها في بقيّة المشروع.
     */
    private function teacher_owns_course($teacher_id, $course_id)
    {
        $teacher_id = (int) $teacher_id;
        $course_id  = (int) $course_id;
        if ($teacher_id < 1 || $course_id < 1) return false;

        $row = $this->db->query(
            'SELECT `id` FROM `course`
              WHERE `id` = ? AND (`creator` = ? OR FIND_IN_SET(?, `user_id`) > 0)
              LIMIT 1',
            array($course_id, $teacher_id, $teacher_id)
        )->row_array();

        return (bool) $row;
    }

    private function teacher_owns_lesson($teacher_id, $lesson_id)
    {
        $lesson_id = (int) $lesson_id;
        if ($lesson_id < 1) return false;

        $row = $this->db->select('id, course_id')->where('id', $lesson_id)
                        ->get('lesson')->row_array();
        return $row && $this->teacher_owns_course($teacher_id, $row['course_id']);
    }

    /** أبناء وليّ الأمر بموافقة نشطة — لا غيرهم. */
    private function parent_owns_child($parent_id, $student_id)
    {
        $student_id = (int) $student_id;
        if ($student_id < 1 || !$this->db->table_exists('parent_links')) return false;

        return (int) $this->db->where('parent_user_id', (int) $parent_id)
                              ->where('student_id', $student_id)
                              ->where('status', 'active')
                              ->count_all_results('parent_links') > 0;
    }

    /** قائمة نصّية نظيفة من حقل مكرّر — بلا مصفوفات متداخلة تُسقط trim. */
    private function post_list($field)
    {
        $out = array();
        foreach ((array) $this->input->post($field) as $v) {
            if (!is_scalar($v)) continue;
            $v = trim((string) $v);
            if ($v !== '') $out[] = $v;
        }
        return $out;
    }

    /* ---- بوّابة المعلّم: الكتابة ------------------------------------- */

    /** POST teacher/upload/save */
    public function upload_save()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $course_id = (int) $this->input->post('course_id');
        $title     = trim((string) $this->input->post('title'));

        if (!$this->teacher_owns_course($tid, $course_id)) {
            $this->done('teacher/upload', false,
                'اختر كورسًا من كورساتك — لا يُرفع درس إلى كورس ليس لك.');
        }
        if ($title === '') {
            $this->done('teacher/upload', false, 'عنوان الدرس مطلوب.');
        }

        $payload = array(
            'course_id'        => $course_id,
            'section_id'       => (int) $this->input->post('section_id'),
            'title'            => $title,
            'duration_minutes' => (int) $this->input->post('duration_minutes'),
            'summary'          => (string) $this->input->post('summary'),
            'objectives'       => $this->post_list('objectives'),
            'action'           => $this->input->post('action') === 'draft' ? 'draft' : 'review',
        );

        $r = $this->delegate(array(
            array('taqdar_teacher_model', 'save_upload'),
            array('taqdar_teacher_model', 'upload_save'),
            array('taqdar_repo_model',    'teacher_save_upload'),
        ), array($tid, $payload, isset($_FILES) ? $_FILES : array()));

        $this->trace('teacher.upload.save', 'course:' . $course_id,
            array('title' => $title, 'ok' => !empty($r['ok'])));

        $this->done('teacher/upload', !empty($r['ok']),
            $this->result_message($r, 'حُفظ الدرس وأُرسل للمراجعة.'));
    }

    /** POST teacher/marking/approve */
    public function marking_approve()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $result_id = (int) $this->input->post('result_id');
        if ($result_id < 1) {
            $this->done('teacher/marking', false, 'لم يُحدَّد التسليم المراد اعتماده.');
        }

        $res = $this->db->where('quiz_result_id', $result_id)->get('quiz_results')->row_array();
        if (!$res) {
            $this->done('teacher/marking', false, 'لا يوجد تسليم بهذا المعرّف.');
        }
        // التصحيح ملك صاحب الكورس: الدرجة تُغيّر سجلّ طالب، فلا تُترك لمن مرّ بالرابط
        if (!$this->teacher_owns_lesson($tid, $res['quiz_id'])) {
            $this->done('teacher/marking', false, 'هذا التسليم في كورس ليس لك.');
        }

        $score = $this->input->post('score');
        if ($score !== null && $score !== '' && (!is_numeric($score) || (float) $score < 0)) {
            $this->done('teacher/marking', false, 'الدرجة تُكتب عددًا موجبًا.');
        }

        $mastery = (string) $this->input->post('mastery');
        if (!in_array($mastery, array('mastered', 'progress', 'late'), true)) {
            $mastery = 'progress';
        }

        $r = $this->delegate(array(
            array('taqdar_marking_model', 'approve_marking'),
            array('taqdar_marking_model', 'marking_approve'),
            array('taqdar_teacher_model', 'approve_marking'),
            array('taqdar_teacher_model', 'marking_approve'),
            array('taqdar_repo_model',    'teacher_approve_marking'),
        ), array($tid, array(
            'result_id' => $result_id,
            'student_id' => (int) $res['user_id'],
            'quiz_id'   => (int) $res['quiz_id'],
            'score'     => ($score === null || $score === '') ? null : (float) $score,
            'mastery'   => $mastery,
            'note'      => (string) $this->input->post('note'),
        )));

        $this->trace('teacher.marking.approve', 'quiz_results:' . $result_id,
            array('mastery' => $mastery, 'ok' => !empty($r['ok'])));

        $this->done('teacher/marking', !empty($r['ok']),
            $this->result_message($r, 'اعتُمدت الدرجة وأُبلغ الطالب.'));
    }

    /** POST teacher/sessions/save */
    public function sessions_save()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        // قيمة الخانة «فهرس اليوم:مفتاح الفترة» — وما خالف الشكل لا يُمرَّر
        $slots = array();
        foreach ($this->post_list('slots') as $s) {
            if (preg_match('/^[0-6]:[A-Za-z0-9_-]{1,24}$/', $s)) $slots[] = $s;
        }

        // إن أُرسلت معرّفات فترات قائمة فلا بدّ أن تكون فترات هذا المعلّم
        $slot_ids = array();
        foreach ($this->post_list('slot_id') as $sid) {
            $sid = (int) $sid;
            if ($sid < 1) continue;
            $row = $this->db->select('id, teacher_id')->where('id', $sid)
                            ->get('availability_slots')->row_array();
            if (!$row || (int) $row['teacher_id'] !== $tid) {
                $this->done('teacher/sessions', false, 'إحدى الفترات المرسلة ليست من فتراتك.');
            }
            $slot_ids[] = $sid;
        }

        $r = $this->delegate(array(
            array('taqdar_sessions_model', 'save_week', array($tid, $slots), 'numeric_ok'),
            array('taqdar_teacher_model',  'save_sessions'),
            array('taqdar_teacher_model',  'sessions_save'),
            array('taqdar_repo_model',     'teacher_save_sessions'),
        ), array($tid, array('slots' => $slots, 'slot_ids' => $slot_ids)));

        $this->trace('teacher.sessions.save', 'users:' . $tid,
            array('slots' => count($slots), 'ok' => !empty($r['ok'])));

        $done = isset($r['count'])
            ? 'حُفظت أوقاتك المتاحة — ' . (int) $r['count'] . ' فترة مفتوحة هذا الأسبوع.'
            : 'حُفظت أوقاتك المتاحة.';

        $this->done('teacher/sessions', !empty($r['ok']), $this->result_message($r, $done));
    }

    /** POST teacher/wallet/withdraw */
    public function wallet_withdraw()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $amount = $this->input->post('withdrawal_amount');
        if (!is_numeric($amount) || (float) $amount <= 0) {
            $this->done('teacher/wallet', false, 'اكتب مبلغًا موجبًا.');
        }
        $amount = (float) $amount;

        $dest = trim((string) $this->input->post('destination'));
        if ($dest === '') {
            $this->done('teacher/wallet', false,
                'اكتب بيانات التحويل — لا يُرسل طلب مال بقناة لا تُسجَّل.');
        }

        $channel = (string) $this->input->post('payment_type');
        if (!preg_match('/^[a-z0-9_-]{2,24}$/i', $channel)) {
            $this->done('teacher/wallet', false, 'اختر قناة تحويل صحيحة.');
        }

        /* الملكية هنا بنيوية: المحفظة تُقصد بمعرّف صاحب الجلسة دائمًا، ولا
           يُقرأ من النموذج المرسل معرّف مستخدم ولا معرّف محفظة.

           وسقف الرصيد يحكم به `Taqdar_wallet_model` من الدفتر داخل معاملة —
           وعمود `balance_available` قد يكون بائتًا، فالحكم به هنا يردّ سحبًا
           مشروعًا. فلا نقرأ العمود إلا حين لا يوجد النموذج أصلًا. */
        $halalas = (int) round($amount * 100);
        $wallet  = $this->db->where('owner_user_id', $tid)->get('wallets')->row_array();

        if (!is_file(APPPATH . 'models/Taqdar_wallet_model.php')) {
            if (!$wallet) {
                $this->done('teacher/wallet', false, 'لا محفظة لحسابك بعد — لا رصيد يُسحب.');
            }
            if ($halalas > (int) $wallet['balance_available']) {
                $this->done('teacher/wallet', false,
                    'المبلغ أكبر من رصيدك المتاح — والمعلّق لا يُسحب قبل أن يتحرّر.');
            }
        }

        $r = $this->delegate(array(
            array('taqdar_wallet_model',  'request_payout', array($tid, $halalas, $channel, $dest)),
            array('taqdar_teacher_model', 'request_withdrawal'),
            array('taqdar_teacher_model', 'wallet_withdraw'),
            array('taqdar_billing_model', 'request_withdrawal'),
            array('taqdar_repo_model',    'request_withdrawal'),
        ), array($tid, array(
            'amount_sar'     => $amount,
            'amount_halalas' => $halalas,
            'payment_type'   => $channel,
            'destination'    => $dest,
            'wallet_id'      => $wallet ? (int) $wallet['id'] : 0,
        )));

        $this->trace('teacher.wallet.withdraw', 'wallets:' . ($wallet ? (int) $wallet['id'] : 0),
            array('amount_sar' => $amount, 'channel' => $channel, 'ok' => !empty($r['ok'])));

        $this->done('teacher/wallet', !empty($r['ok']),
            $this->result_message($r, 'سُجِّل طلب السحب وينتظر المراجعة.'));
    }

    /** POST teacher/questions/import */
    public function questions_import()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $lesson_id = (int) $this->input->post('lesson_id');
        if ($lesson_id > 0 && !$this->teacher_owns_lesson($tid, $lesson_id)) {
            $this->done('teacher/questions', false, 'هذا الدرس في كورس ليس لك.');
        }

        $course_id = (int) $this->input->post('course_id');
        if ($course_id > 0 && !$this->teacher_owns_course($tid, $course_id)) {
            $this->done('teacher/questions', false, 'هذا الكورس ليس لك.');
        }

        if (empty($_FILES['csv']['name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $this->done('teacher/questions', false, 'اختر ملفّ CSV قبل الاستيراد.');
        }
        if ((int) $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            $this->done('teacher/questions', false, 'تعذّر رفع الملفّ — أعد المحاولة.');
        }
        if ((int) $_FILES['csv']['size'] > 2 * 1024 * 1024) {
            $this->done('teacher/questions', false, 'حجم الملفّ يتجاوز ٢ ميغابايت.');
        }
        $ext = strtolower((string) pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv', 'txt'), true)) {
            $this->done('teacher/questions', false, 'الملفّ لا بدّ أن يكون بصيغة CSV.');
        }

        $r = $this->delegate(array(
            array('taqdar_teacher_model', 'import_questions'),
            array('taqdar_teacher_model', 'questions_import'),
            array('taqdar_repo_model',    'import_questions'),
        ), array($tid, array(
            'lesson_id' => $lesson_id,
            'course_id' => $course_id,
        ), $_FILES['csv']));

        $this->trace('teacher.questions.import', 'lesson:' . $lesson_id,
            array('file' => (string) $_FILES['csv']['name'], 'ok' => !empty($r['ok'])));

        $this->done('teacher/questions', !empty($r['ok']),
            $this->result_message($r, 'استُوردت الأسئلة.'));
    }

    /* ---- بوّابة وليّ الأمر: الكتابة ---------------------------------- */

    /** POST parent/messages/compose */
    public function parent_message_send()
    {
        $user = $this->write_guard('parent');
        $pid  = (int) $user['id'];

        $body = trim((string) $this->input->post('message'));
        if ($body === '') {
            $this->done('parent/messages', false, 'اكتب نصّ الرسالة.');
        }

        $child_id = (int) $this->input->post('child_id');
        if ($child_id > 0 && !$this->parent_owns_child($pid, $child_id)) {
            $this->done('parent/messages', false, 'هذا الطالب ليس من أبنائك المرتبطين.');
        }

        // المرسَل إليه: إدارة أو معلّم أو ابن مرتبط — لا أي حساب في المنصّة
        $to = (int) $this->input->post('to');
        if ($to < 1) $to = (int) $this->input->post('receiver');

        if ($to > 0) {
            $row = $this->db->select('id, role_id, is_instructor, status')
                            ->where('id', $to)->get('users')->row_array();
            $allowed = $row && (int) $row['status'] === 1 && (
                (int) $row['role_id'] === 1
                || !empty($row['is_instructor'])
                || $this->parent_owns_child($pid, $to)
            );
            if (!$allowed) {
                $this->done('parent/messages', false,
                    'لا تُرسَل الرسائل إلا إلى معلّمي أبنائك أو الإدارة.');
            }
        }

        $r = $this->delegate(array(
            array('taqdar_parent_model', 'compose_message'),
            array('taqdar_parent_model', 'start_thread', array($pid, $to, $body)),
            array('taqdar_parent_model', 'send_message'),
            array('taqdar_repo_model',   'parent_compose_message'),
        ), array($pid, array(
            'to'       => $to,
            'child_id' => $child_id,
            'thread'   => trim((string) $this->input->post('thread')),
            'subject'  => trim((string) $this->input->post('subject')),
            'message'  => $body,
        )));

        $this->trace('parent.messages.compose', 'users:' . $to,
            array('child_id' => $child_id, 'ok' => !empty($r['ok'])));

        $this->done('parent/messages', !empty($r['ok']),
            $this->result_message($r, 'أُرسلت رسالتك.'));
    }

    /**
     * POST parent/children/link
     *
     * ثلاثة أفعال على مسار واحد لأنها فعل واحد في نظر وليّ الأمر: علاقته
     * بابنه — يطلبها، أو يسحب طلبه، أو يفكّها. و`tq_action` يفصلها.
     */
    public function parent_child_link()
    {
        $user = $this->write_guard('parent');
        $pid  = (int) $user['id'];
        $act  = (string) $this->input->post('tq_action');

        if ($act === 'link_cancel') {
            $link_id = (int) $this->input->post('link_id');
            // الطلب المسحوب لا بدّ أن يكون طلبه هو — لا رقم رابط عابر
            $row = $link_id > 0
                ? $this->db->where('id', $link_id)->get('parent_links')->row_array()
                : null;
            if (!$row || (int) $row['parent_user_id'] !== $pid) {
                $this->done('parent/settings', false, 'هذا الطلب ليس طلبك.');
            }
            $r = $this->delegate(array(
                array('taqdar_parent_model', 'cancel_request', array($pid, $link_id)),
            ), array($pid, $link_id));

            $this->trace('parent.children.link_cancel', 'parent_links:' . $link_id,
                array('ok' => !empty($r['ok'])));
            $this->done('parent/settings', !empty($r['ok']),
                $this->result_message($r, 'سُحب طلب الربط.'));
        }

        if ($act === 'link_revoke') {
            $sid = (int) $this->input->post('student_id');
            if (!$this->parent_owns_child($pid, $sid)) {
                $this->done('parent/settings', false, 'هذا الطالب ليس من أبنائك المرتبطين.');
            }
            $r = $this->delegate(array(
                array('taqdar_parent_model', 'revoke_link', array($pid, $sid)),
            ), array($pid, $sid));

            $this->trace('parent.children.link_revoke', 'users:' . $sid,
                array('ok' => !empty($r['ok'])));
            $this->done('parent/settings', !empty($r['ok']),
                $this->result_message($r, 'فُكّ الربط.'));
        }

        // الرابط يُنشأ باسم صاحب الجلسة دائمًا — أيّ parent_user_id مرسل يُهمَل
        $identifier = '';
        foreach (array('identifier', 'student_email', 'student_code', 'student_id') as $f) {
            $v = trim((string) $this->input->post($f));
            if ($v !== '' && $v !== '0') { $identifier = $v; break; }
        }
        if ($identifier === '') {
            $this->done('parent/settings', false, 'اكتب بريد حساب ابنك في المنصّة أو رقم حسابه.');
        }

        $sid = ctype_digit($identifier)
            ? (int) $identifier
            : (int) $this->db->select('id')->where('email', $identifier)
                             ->get('users')->row('id');

        if ($sid > 0 && $sid === $pid) {
            $this->done('parent/settings', false, 'لا يكون المستخدم وليَّ أمر نفسه.');
        }
        if ($sid > 0 && $this->parent_owns_child($pid, $sid)) {
            $this->done('parent/children', false, 'هذا الابن مرتبط بحسابك بالفعل.');
        }

        $r = $this->delegate(array(
            array('taqdar_parent_model', 'link_child'),
            array('taqdar_parent_model', 'request_link', array($pid, $identifier)),
            array('taqdar_repo_model',   'parent_link_child'),
        ), array($pid, array(
            'student_id'    => $sid,
            'identifier'    => $identifier,
        )));

        $this->trace('parent.children.link', 'users:' . $sid,
            array('identifier' => $identifier, 'ok' => !empty($r['ok'])));

        $this->done(!empty($r['ok']) ? 'parent/children' : 'parent/settings', !empty($r['ok']),
            $this->result_message($r, 'أُرسل طلب الربط — ويُفعَّل بعد موافقة ابنك.'));
    }

    /**
     * POST parent/settings/save
     *
     * شاشة الإعدادات ثلاثة نماذج: الملفّ الشخصي، وكلمة المرور، والتنبيهات.
     * و`tq_action` يفصل بينها — فحفظ التنبيهات لا يمرّ بمسار كلمة المرور.
     */
    public function parent_settings_save()
    {
        $user = $this->write_guard('parent');
        $pid  = (int) $user['id'];
        $act  = (string) $this->input->post('tq_action');

        if ($act === 'password_change') {
            $r = $this->delegate(array(
                array('taqdar_parent_model', 'change_password', array(
                    $pid,
                    (string) $this->input->post('current_password'),
                    (string) $this->input->post('new_password'),
                    (string) $this->input->post('confirm_password'),
                )),
            ), array($pid));

            $this->trace('parent.settings.password', 'users:' . $pid,
                array('ok' => !empty($r['ok'])));
            $this->done('parent/settings', !empty($r['ok']),
                $this->result_message($r, 'غُيّرت كلمة المرور.'));
        }

        if ($act === 'profile_save') {
            $profile = array(
                'first_name' => $this->input->post('first_name'),
                'last_name'  => $this->input->post('last_name'),
                'email'      => $this->input->post('email'),
                'phone'      => $this->input->post('phone'),
                'address'    => $this->input->post('address'),
            );
            $r = $this->delegate(array(
                array('taqdar_parent_model', 'save_profile', array($pid, $profile)),
            ), array($pid, $profile));

            $this->trace('parent.settings.profile', 'users:' . $pid,
                array('ok' => !empty($r['ok'])));
            $this->done('parent/settings', !empty($r['ok']),
                $this->result_message($r, 'حُفظ ملفّك.'));
        }

        // الافتراض: تفضيلات التنبيه. وحقول الهوية والصلاحية لا تُقبل من النموذج
        $blocked = array('id', 'user_id', 'parent_user_id', 'role_id', 'is_instructor',
                         'status', 'password', 'sessions', 'payment_keys',
                         'verification_code');
        $payload = array();
        foreach ((array) $this->input->post(null, true) as $k => $v) {
            if (in_array($k, $blocked, true)) continue;
            $payload[$k] = $v;
        }

        $notify    = (array) ($this->input->post('notify')    ?: array());
        $plan_days = (array) ($this->input->post('plan_days') ?: array());

        $r = $this->delegate(array(
            array('taqdar_parent_model', 'save_settings'),
            array('taqdar_parent_model', 'save_prefs', array($pid, $notify, $plan_days)),
            array('taqdar_parent_model', 'settings_save'),
            array('taqdar_repo_model',   'parent_save_settings'),
        ), array($pid, $payload));

        $this->trace('parent.settings.save', 'users:' . $pid,
            array('fields' => array_keys($payload), 'ok' => !empty($r['ok'])));

        $this->done('parent/settings', !empty($r['ok']),
            $this->result_message($r, 'حُفظت إعداداتك.'));
    }

    /* =====================================================================
       الشهادات
       ---------------------------------------------------------------------
       `tq_certificates.php` كان يشير إلى `taqdar/certificate/{id}` و
       `taqdar/verify/{id}` ولا وجود للدالّتين — فكل زرّ في الشاشة يقود إلى
       404. والقاعدة الحاكمة للشهادة أنها تُصدر على إتقان مُقاس: محاولة
       امتحان ناجحة في `attempts`، لا نسبة مشاهدة.

       وحين لا يوجد بعد محتوى بأهداف وبوّابات، الصواب حالة فارغة مفهومة
       لا شاشة خطأ: الرابط سليم، والشهادة هي التي لم تُستحقّ بعد.
       ===================================================================== */

    /** الشهادة الواحدة — لصاحبها ولوليّ أمره وللإدارة. */
    public function certificate($id = 0)
    {
        $this->require_login();
        $uid  = (int) $this->session->userdata('user_id');
        $cert = $this->certificate_row($id);

        if ($cert && (int) $cert['student_id'] !== $uid
            && tq_role($uid) !== 'admin'
            && !$this->parent_owns_child($uid, (int) $cert['student_id'])) {
            $this->session->set_flashdata('error_message', 'هذه الشهادة ليست لك.');
            redirect(tq_home_for(tq_role($uid)), 'location', 302);
        }

        $data = array(
            'tq_counts'   => $this->counts($uid),
            'user_id'     => $uid,
            'certificate' => $cert,
            'cert_id'     => (int) $id,
        );

        if (is_file(APPPATH . 'views/frontend/' . $this->theme() . '/tq_certificate.php')) {
            $this->show('tq_certificate', 'الشهادة', $data);
            return;
        }
        $this->certificate_fallback($cert, false);
    }

    /** صفحة التحقّق العامّة — بلا تسجيل دخول، وبلا بيانات تعريف زائدة. */
    public function verify($code = '')
    {
        $cert = $this->certificate_row($code);

        if (is_file(APPPATH . 'views/frontend/' . $this->theme() . '/tq_verify.php')) {
            $this->show('tq_verify', 'التحقّق من شهادة', array(
                'certificate' => $cert,
                'cert_code'   => (string) $code,
            ));
            return;
        }
        $this->certificate_fallback($cert, true);
    }

    /**
     * صفّ الشهادة من رمزها. الرمز `TQ-000012` أو `12` — كلاهما يفتح الشهادة
     * نفسها، فالرمز المطبوع هو ما ينسخه المتحقّق لا المعرّف العاري.
     */
    private function certificate_row($code)
    {
        $id = (int) preg_replace('/\D+/', '', (string) $code);
        if ($id < 1 || !$this->db->table_exists('attempts')) return null;

        $rows = $this->db->query(
            "SELECT a.id, a.student_id, a.score, a.submitted_at,
                    p.title AS path_title, m.title AS milestone_title,
                    TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS holder
               FROM attempts a
               JOIN assessments s ON s.id = a.assessment_id AND s.type = 'exam'
               LEFT JOIN milestones m ON m.id = s.milestone_id
               LEFT JOIN paths p ON p.id = COALESCE(s.path_id, m.path_id)
               LEFT JOIN users u ON u.id = a.student_id
              WHERE a.id = ? AND a.passed = 1
              LIMIT 1", array($id))->result_array();

        return $rows ? $rows[0] : null;
    }

    /**
     * عرض احتياطي حين لا تكون شاشة الشهادة قد رُكِّبت بعد.
     * صفحة قائمة بذاتها — لا 404، ولا شاشة بيضاء، ولا إطار ثيم ناقص.
     */
    private function certificate_fallback($cert, $public)
    {
        $brand = 'منصّة تقدّر';
        $title = $public ? 'التحقّق من شهادة' : 'الشهادة';

        if ($cert) {
            $code = 'TQ-' . str_pad((string) (int) $cert['id'], 6, '0', STR_PAD_LEFT);
            $body = '<p class="ok">شهادة صحيحة وصادرة عن ' . html_escape($brand) . '.</p>'
                  . '<dl>'
                  . '<dt>حاملها</dt><dd>' . html_escape($cert['holder'] ?: '—') . '</dd>'
                  . '<dt>المحطة</dt><dd>' . html_escape($cert['milestone_title'] ?: ($cert['path_title'] ?: '—')) . '</dd>'
                  . '<dt>نسبة الإتقان</dt><dd>' . (int) $cert['score'] . '%</dd>'
                  . '<dt>تاريخ الإصدار</dt><dd>' . html_escape((string) $cert['submitted_at']) . '</dd>'
                  . '<dt>رمز التحقّق</dt><dd>' . html_escape($code) . '</dd>'
                  . '</dl>';
        } else {
            $body = '<p class="empty">لا توجد شهادة بهذا الرمز.</p>'
                  . '<p>الشهادة تُصدر على إتقان مُقاس لا على مشاهدة: تُنهي المحطة، '
                  . 'وتجتاز اختبارها، فتصلك شهادة بأهدافها ورمز تحقّق.</p>';
        }

        $back = $public ? base_url() : base_url('student/certificates');
        $backLabel = $public ? 'الصفحة الرئيسية' : 'شهاداتي';

        $html = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
              . '<meta name="viewport" content="width=device-width, initial-scale=1">'
              . '<title>' . html_escape($title . ' — ' . $brand) . '</title>'
              . '<style>'
              . 'body{margin:0;background:#f6f7fb;color:#132549;font:16px/1.7 system-ui,"Segoe UI",Tahoma,sans-serif}'
              . '.wrap{max-width:44rem;margin:4rem auto;padding:2.5rem;background:#fff;'
              . 'border:1px solid #e4e7f0;border-radius:1rem}'
              . 'h1{margin:0 0 1.5rem;font-size:1.5rem}'
              . 'dl{display:grid;grid-template-columns:auto 1fr;gap:.6rem 1.5rem;margin:1.5rem 0}'
              . 'dt{color:#6b7495;font-size:.9rem}dd{margin:0;font-weight:600}'
              . '.ok{color:#0f7b53;font-weight:700}.empty{color:#6b7495;font-weight:700}'
              . 'a{display:inline-block;margin-top:1.5rem;padding:.7rem 1.4rem;background:#132549;'
              . 'color:#fff;border-radius:.6rem;text-decoration:none}'
              . '@media print{body{background:#fff}a{display:none}}'
              . '</style></head><body><div class="wrap">'
              . '<h1>' . html_escape($title) . '</h1>' . $body
              . '<a href="' . $back . '">' . $backLabel . '</a>'
              . '</div></body></html>';

        $this->output->set_content_type('text/html; charset=utf-8')->set_output($html);
    }

    /**
     * عارض الصفحات العامّة الثابتة في التصميم الجديد.
     *
     * قائمة بيضاء لا وسيط حرّ: `$page_name` يُدرَج ملفًّا في الغلاف، فتمريرُ
     * ما يأتي من الرابط إليه بلا فحص يفتح الباب لقراءة ملفّات لم تُقصد.
     */
    public function site_page($name = '')
    {
        $allowed = array(
            'site_books'    => 'كتب المنهج',
            'site_teachers' => 'المعلمون',
            'site_students' => 'الطلاب',
            'site_parents'  => 'أولياء الأمور',
        );
        if (!isset($allowed[$name])) show_404();

        $this->show($name, $allowed[$name]);
    }

    /**
     * صفحة تفصيل المسار — وبها يكتمل طريق الشراء.
     *
     * الزرّ في الكتالوج كان يقود إلى لا شيء، فكان الزائر يرى ما يُعجبه
     * ولا يجد بابًا يدخل منه.
     */
    public function path_page($slug = '')
    {
        $this->load->model('taqdar_site_model', 'tq_m');
        $path = $this->tq_m->path_by_slug($slug);
        if (!$path) show_404();

        $this->show('site_path', $path['title'], array('tq_path' => $path));
    }


    /**
     * ملفّ المعلّم العامّ.
     *
     * المعروض علنًا اختيارٌ صريح (`is_public`) لا أثرٌ جانبيّ لكون
     * المستخدم معلّمًا — فمن يدرّس لا يلزم أن يُنشَر اسمه وصورته.
     */
    public function instructor_page($id = 0)
    {
        $this->load->model('taqdar_site_model', 'tq_m');
        $id = (int) $id;

        $found = null;
        foreach ($this->tq_m->teachers() as $t) {
            if ((int) $t['id'] === $id) { $found = $t; break; }
        }
        if (!$found) show_404();

        $this->show('instructor_page', $found['name'], array('tq_teacher' => $found));
    }


    /** صفحة المسابقات. */
    public function competitions()
    {
        $this->show('competitions', 'المسابقات');
    }

    /**
     * التسجيل في مسابقة.
     *
     * للمسجَّلين وحدهم: مسابقةٌ يدخلها المجهولون لا تُقاس نتائجها ولا
     * تُنسب شهاداتها. والمفتاح الفريد يمنع التسجيل مرّتين.
     */
    public function competition_join()
    {
        if ($this->input->method(true) !== "POST") show_404();
        $uid = (int) $this->session->userdata('user_id');
        if ($uid <= 0) { redirect(site_url('login'), 'location', 302); return; }

        $cid = (int) $this->input->post('competition_id');
        $c = $this->db->where('id', $cid)->where('status', 'open')->get('competitions')->row_array();

        if (!$c) {
            $this->session->set_flashdata('error_message', 'هذه المسابقة غير متاحة للتسجيل.');
        } elseif (!empty($c['ends_at']) && $c['ends_at'] < date('Y-m-d')) {
            $this->session->set_flashdata('error_message', 'انتهى موعد التسجيل في هذه المسابقة.');
        } else {
            $n = (int) $this->db->where('competition_id', $cid)->count_all_results('competition_entries');
            if ((int) $c['seats'] > 0 && $n >= (int) $c['seats']) {
                $this->session->set_flashdata('error_message', 'اكتمل عدد المشاركين.');
            } else {
                $exists = $this->db->where('competition_id', $cid)->where('user_id', $uid)
                                   ->count_all_results('competition_entries') > 0;
                if ($exists) {
                    $this->session->set_flashdata('flash_message', 'أنت مسجَّل في هذه المسابقة سلفًا.');
                } else {
                    $this->db->insert('competition_entries', array(
                        'competition_id' => $cid, 'user_id' => $uid,
                        'created_at' => date('Y-m-d H:i:s')));
                    $this->session->set_flashdata('flash_message',
                        'سُجّلت في المسابقة. سنُذكّرك قبل موعدها.');
                }
            }
        }
        redirect(site_url('competitions'), 'location', 302);
    }

    /* ---- الباقة: صفحتُها وشراؤها ومحتواها ------------------------- */

    /**
     * صفحة الباقة — ما يُشترى، مفصَّلًا، قبل الدفع.
     *
     * كان الكتالوج ينتهي عند بطاقةٍ ومرساة: يقرأ الزائر اسمًا وسعرًا وستّ
     * كلماتٍ تسويقية ثم يُطلب منه ثلاثمئةٍ وتسعون ريالًا. وهذه الصفحة
     * تعرض المنهج نفسه — مادّةً مادّة ووحدةً وحدة — قبل أن يُطلب شيء.
     */
    public function plan_page($code = '')
    {
        $this->load->model('taqdar_site_model', 'tq_m');
        $b = $this->tq_m->bundle_by_code($code);
        if (!$b) show_404();

        /* حالةُ الزائر تُقرَّر هنا لا في العرض: العرض يعرض، والقرار قرار. */
        $uid = (int) $this->session->userdata('user_id');
        $own = false;
        if ($uid > 0) {
            $this->load->model('taqdar_billing_model');
            $cur = $this->taqdar_billing_model->active_subscription($uid);
            $own = ($cur && (int) $cur['plan_id'] === (int) $b['plan_id']
                          && $cur['status'] === 'active');
        }

        $this->show('site_plan', $b['name'], array('tq_bundle' => $b, 'tq_owns' => $own));
    }

    /**
     * تأكيد الشراء — شاشةٌ واحدة بلا سلّة.
     *
     * السلّة تُجمِّع أصنافًا، والطالب يشتري باقةً واحدة تُغطّي سنته. فلا
     * شيء يُجمَّع، وكلّ خطوةٍ زائدة بين الرغبة والدفع تُسقط مشترين.
     *
     * والدخول شرطٌ هنا لا في الزرّ السابق: من يعود بعد التسجيل يجد الشاشة
     * نفسها لا الكتالوج من أوّله.
     */
    public function checkout($code = '')
    {
        $this->load->model('taqdar_site_model', 'tq_m');
        $b = $this->tq_m->bundle_by_code($code);
        if (!$b) show_404();

        $uid = (int) $this->session->userdata('user_id');
        if ($uid <= 0) {
            $this->session->set_userdata('tq_next', 'checkout/' . $b['code']);
            redirect(site_url('login?next=' . rawurlencode('checkout/' . $b['code'])), 'location', 302);
            return;
        }

        /* المعلّم وليّ الأمر لا يشتريان باقةَ طالب: الشراء يفتح محتوًى
           يُقاس تقدّمُه لصاحب الحساب، ولا معنى له لغير الطالب. */
        if (function_exists('tq_role') && tq_role() !== 'student') {
            $this->session->set_flashdata('error_message',
                'الاشتراك في الباقات لحسابات الطلاب. سجّل الدخول بحساب طالب.');
            redirect(site_url('plans'), 'location', 302);
            return;
        }

        $this->load->model('taqdar_billing_model');
        $cur = $this->taqdar_billing_model->active_subscription($uid);

        $this->show('site_checkout', 'تأكيد الاشتراك — ' . $b['name'], array(
            'tq_bundle'  => $b,
            'tq_current' => $cur,
            'user_id'    => $uid,
        ));
    }

    /**
     * محتوى الباقة للمشترك — ما دفع ثمنه، مرتَّبًا كما يُدرَس.
     *
     * صفحة الاشتراك تقول «نشط حتى كذا» ولا تقول ماذا فُتح. وهذه تقوله:
     * الموادّ بوحداتها ودروسها وتقدّم الطالب في كلٍّ منها.
     */
    public function bundle()
    {
        $this->require_role('student');
        $uid = (int) $this->session->userdata('user_id');

        $this->load->model('taqdar_billing_model');
        $this->load->model('taqdar_site_model', 'tq_m');
        $this->load->model('taqdar_repo_model');

        $sub = $this->taqdar_billing_model->active_subscription($uid);
        if (!$sub) {
            /* `active_subscription()` تستثني المعلَّق عمدًا — فهو لا يمنح
               شيئًا. لكنّ صفحةً تقول لمن دفع للتوّ «لا اشتراك بعد» وتدلّه
               على الباقات تدعوه إلى الشراء مرّتين. فيُقرأ آخرُ اشتراكٍ
               مهما كانت حاله، ويُقال له أين هو منه — كما تفعل
               `subscription()` بالارتداد نفسه. */
            $sub = $this->db->where('user_id', (int) $uid)
                            ->order_by('id', 'DESC')->limit(1)
                            ->get('subscriptions')->row_array();
        }
        $b   = null;
        $prog = array();

        if ($sub) {
            $plan = $this->taqdar_billing_model->plan($sub['plan_id']);
            if ($plan) $b = $this->tq_m->bundle_by_code($plan['code']);
        }

        /* التقدّم من المستودع نفسه الذي يقرأ منه المشغّل، فلا يفترق رقمٌ
           هنا عن رقمٍ هناك. */
        if ($b) {
            foreach ($b['subjects'] as $s) {
                $cid = (int) $s['course_id'];
                if ($cid <= 0 || isset($prog[$cid])) continue;
                $p = $this->taqdar_repo_model->path_progress($uid, $cid);
                $prog[$cid] = (int) $p['percent'];
            }
        }

        $this->show('tq_bundle', 'محتوى باقتي', array(
            'tq_counts'  => $this->counts($uid),
            'user_id'    => $uid,
            'tq_sub'     => $sub,
            'tq_bundle'  => $b,
            'tq_progress'=> $prog,
        ));
    }

    /**
     * نقطة عودة بوّابة الدفع — جاهزةٌ ومغلقة.
     *
     * `activate_from_gateway()` مكتوبةٌ في نموذج الفوترة ولا مُنادِيَ لها،
     * لأنّ لا بوّابة مفعّلة بعد. وهذه النقطة تُبقي الفجوة **واحدة**: يوم
     * يُفتح حساب ميسر أو تاب يُكتب التحقّق من التوقيع هنا وحده.
     *
     * ولا تفعل شيئًا اليوم: نقطةٌ تُفعّل اشتراكًا بلا تحقّقٍ من توقيع
     * البوّابة بابٌ يُفتح بطلبٍ مصنوع.
     */
    public function gateway_callback($gateway = '')
    {
        log_message('error', 'TQ-GATEWAY: نداءٌ على نقطة عودةٍ غير مفعّلة — ' . $gateway);
        show_404();
    }

}
