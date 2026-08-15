<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * متحكم بوابات تقدر.
 *
 * كل مسار هنا يفرض دوره في الخادم لا في الواجهة — إخفاء زر ليس صلاحية.
 * والعرض يمر بغلاف الثيم نفسه (frontend/<theme>/index) فلا يخرج عن النظام.
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
         * المنطقة الزمنية تضبط هنا كما تضبط في Taqdar_admin وTaqdar_cron.
         *
         * غيابها كان يجعل هذا المتحكم يكتب بتوقيت الخادم (UTC) بينما تكتب
         * اللوحة والمهام الدورية بتوقيت المنصة — فيختلف الطابعان ثلاث ساعات
         * في `audit_log` نفسه، ويصير السجل غير قابل للترتيب ولا للتدقيق.
         * الإعداد الناقص لا يبرر طابعا عشوائيا، فله بديل صريح.
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
        return 'taqdar'; // بوابات تقدر تعرض بثيمها دائما، فلا تنتظر تبديل الموقع العام
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
     * يشترط دورا بعينه — عبر tq_guard الموحدة في taqdar_role_helper.
     *
     * قبل هذا كان كل متحكم يشتق الدور بطريقته: هنا `is_instructor` وحدها،
     * وفي Taqdar_gate `role_id` أيضا — فمر الأدمن من باب ومنع من آخر.
     * الاشتقاق الآن في موضع واحد، والأدمن لا يستثنى من الفصل.
     */
    private function require_role($role)
    {
        tq_guard($role);
        return $this->user_model->get_all_user($this->session->userdata('user_id'))->row_array();
    }

    /**
     * أعداد الشارات في القائمة والترويسة — استعلام واحد لكل نوع.
     *
     * والمفاتيح تطابق **مفاتيح بنود القائمة** لا أسماء الجداول: بند
     * الإشعارات في بوابة ولي الأمر مفتاحه `alerts` (لأن مساره
     * `parent/alerts`)، و`portal_rail.php` يقرأ `$tq_counts[$key]` بمفتاح
     * البند. فكان العدد يحسب صحيحا تحت `notifications` ولا يقرؤه أحد:
     * شارة ميتة في قائمة كل ولي أمر، وجرس في ترويسته يعد ولا يظهر رقما.
     *
     * ولا تحسب أعداد دور لدور آخر: `reviews` و`tasks` استعلاما طالب،
     * وتشغيلهما لولي أمر أو معلم عمل ضائع في كل صفحة يفتحها.
     */
    private function counts($uid, $role = 'student')
    {
        // أعمدة Academy الفعلية: message(receiver, read_status) و notifications(to_user, status)
        $unread_msgs = (int) $this->db->where('receiver', $uid)
                                      ->where('read_status', 0)
                                      ->count_all_results('message');

        $unread_notif = (int) $this->db->where('to_user', $uid)
                                       ->where('status', 0)
                                       ->count_all_results('notifications');

        if ($role !== 'student') {
            /* بوابتا المعلم وولي الأمر: الرسائل والإشعارات وحدهما.
               و`alerts` هو مفتاح بند الإشعارات في قائمة ولي الأمر. */
            return [
                'messages'      => $unread_msgs,
                'notifications' => $unread_notif,
                'alerts'        => $unread_notif,
            ];
        }

        // المراجعة المستحقة من المستودع نفسه الذي تقرأ منه الصفحة،
        // فلا يفترق رقم الشارة عن عدد ما تعرضه
        $due = 0;
        try {
            $this->load->model('taqdar_repo_model');
            $due = (int) $this->taqdar_repo_model->count_due_reviews($uid);
        } catch (Throwable $e) {
            $due = 0;   // شارة ناقصة أهون من قائمة مبتورة
        }

        /* المهام المستحقة — واجب في كورس مسجل لم تسلم محاولته بعد.
           شارة «مهامي» كانت مكتوبة في القائمة بشرط `$tq_counts['tasks']`
           ولا أحد يكتب هذا المفتاح، فبقيت ميتة: يسند إلى الطالب واجب فلا
           يعلم به إلا إن فتح الشاشة بنفسه. والعد من الجداول نفسها التي
           تقرأ منها `tq_tasks.php` فلا يفترق الرقمان. */
        $tasks = 0;
        try {
            $tasks = (int) $this->db->query(
                'SELECT COUNT(*) AS n
                   FROM `assessments` a
                   JOIN `lesson` l ON l.id = a.lesson_id
                   JOIN `enrol` e  ON e.course_id = l.course_id AND e.user_id = ?
                  WHERE a.type = ?
                    AND NOT EXISTS (
                        SELECT 1 FROM `attempts` t
                         WHERE t.assessment_id = a.id AND t.student_id = ?
                           AND t.submitted_at IS NOT NULL)',
                array($uid, 'homework', $uid)
            )->row('n');
        } catch (Throwable $e) {
            $tasks = 0;   // شارة ناقصة أهون من صفحة مبتورة
        }

        return [
            'messages'      => $unread_msgs,
            'notifications' => $unread_notif,
            'reviews'       => $due,
            'tasks'         => $tasks,
        ];
    }

    /* ---- بوابة الطالب --------------------------------------------- */

    public function index()          { redirect(site_url('taqdar/home'), 'refresh'); }
    public function home()           { $this->student('tq_home', 'الرئيسية'); }
    /* شاشتان لا واحدة: `courses` للكورسات المسجلة (الغلاف والحالة والنسبة)،
       و`lessons` للدروس أنفسها (كورسا فوحدة فدرسا). كانت `lessons` تعرض
       الكورسات تحت عنوان «دروسي»، فلم يكن في البوابة مدخل إلى درس مفرد
       أصلا — وشاشة المفضلة تعد الطالب بقلب على «أي درس» ولا موضع له. */
    public function courses()        { $this->student('tq_courses', 'كورساتي'); }
    public function lessons()        { $this->student('tq_lessons', 'دروسي'); }
    // القائمة الجانبية تصدر `student/reviews` وشاشتها `tq_reviews.php` قائمة،
    // ولم تكن لها نقطة في المتحكم — فكان بند ثابت في قائمة كل طالب يقود إلى 404.
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
     * مشغل الدرس. لا يستعلم عن الدرس هنا — الصفحة تطلبه من `taqdar_gate`
     * الذي يحسم القفل في الخادم. فلو بني القفل هنا لأمكن تجاوزه بتعديل
     * الواجهة، ولصار «قفلا» بصريا لا آلية.
     */
    public function lesson($course_id = 0, $lesson_id = 0)
    {
        $this->require_role('student');
        $uid = (int) $this->session->userdata('user_id');

        $course_id = (int) $course_id;
        $lesson_id = (int) $lesson_id;

        /* كورس بلا درس محدد يفتح على موضع التوقف.
           كانت الشاشة تمرر `lesson_id = 0` إلى البوابة، فترد `NOT_FOUND`
           ويقرأ الطالب «العنصر المطلوب غير موجود» على كورس مسجل فيه —
           وهو ما يقع كلما فتح كورسا من رابط بلا رقم درس (`home/lesson/<اسم>/<كورس>`
           أو `student/lesson/<كورس>`). فالموضع يحسم هنا قبل العرض:
           آخر درس كان عنده، وإلا أول درس في الكورس. */
        if ($course_id > 0 && $lesson_id < 1) {
            $lesson_id = (int) $this->db->select('watching_lesson_id')
                ->where('student_id', $uid)->where('course_id', $course_id)
                ->get('watch_histories')->row('watching_lesson_id');

            /* والدرس المحفوظ لا بد أن يكون من هذا الكورس فعلا: صف قديم قد
               يحمل معرفا نقل أو حذف، فيعود العطل من باب آخر. */
            if ($lesson_id > 0) {
                $ok = (int) $this->db->where('id', $lesson_id)
                    ->where('course_id', $course_id)->count_all_results('lesson');
                if ($ok < 1) $lesson_id = 0;
            }

            if ($lesson_id < 1) {
                $lesson_id = (int) $this->db->select('id')
                    ->where('course_id', $course_id)
                    ->where('lesson_type !=', 'quiz')
                    ->order_by('id', 'ASC')->limit(1)
                    ->get('lesson')->row('id');
            }
        }

        $this->show('tq_lesson', 'الدرس', array(
            'tq_counts' => $this->counts($uid),
            'user_id'   => $uid,
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
        ));
    }

    /** حصص بالطلب: طبقة مستقلة بجوار المنهج لا داخله. */
    public function on_demand()      { $this->student('tq_on_demand', 'حصص بالطلب'); }

    /**
     * الشاشات التي تبقى مفتوحة قبل أداء الاختبار التشخيصي.
     *
     * قائمة سماح لا قائمة منع: شاشة تضاف إلى البوابة غدا تدخل تحت الحارس
     * افتراضا، وهو الوجه الآمن. والعكس — قائمة ممنوعات — يعني أن كل شاشة
     * جديدة ثغرة حتى يذكرها أحد.
     *
     * وفيها الإعدادات والرسائل والإشعارات عمدا: الطالب الذي وضع في صف غير
     * صفه يحتاج بابا يصل منه إلى الإدارة. وحبسه في شاشة واحدة بلا مخرج
     * يجعل الخطأ الإداري سجنا.
     */
    private static $placement_open = array(
        'tq_placement', 'tq_settings', 'tq_messages', 'tq_notifications', 'tq_delete_account',
    );

    private function student($page, $title)
    {
        // الطالب محروس كغيره: الأدمن الذي يفتح بوابة الطالب يرى شاشات فارغة
        // فيظنها معطوبة، والاختبار من حسابه يعطي نتيجة كاذبة.
        $this->require_role('student');
        $uid = $this->session->userdata('user_id');

        /* الاختبار التشخيصي أول ما يواجه الطالب الجديد.
           و`gate()` هي مصدر القرار الوحيد — تنادى هنا وفي شاشة التأكيد
           وفي نموذج الفوترة، فلا يفترق ما يخفيه العرض عما يمنعه الخادم.
           وترد `null` لمن لا صف له، أو لا اختبار منشور لصفه، أو أداه —
           أي أن هذا السطر لا يمس أحدا في منصة بلا اختبارات. */
        if (!in_array($page, self::$placement_open, true)) {
            $this->load->model('taqdar_diag_model');
            if ($this->taqdar_diag_model->gate($uid)) {
                redirect(base_url('student/placement'), 'location', 302);
                return;
            }
        }

        $this->show($page, $title, [
            'tq_counts' => $this->counts($uid),
            'user_id'   => $uid,
        ]);
    }

    /* ---- الاختبار التشخيصي ---------------------------------------- */

    /**
     * شاشة الاختبار التشخيصي — ثلاث حالات في مسار واحد.
     *
     * الحالة تشتق من القاعدة لا من معامل في الرابط: تمهيد لمن لم يبدأ،
     * وأسئلة لمن بدأ ولم يسلم، ونتيجة لمن سلم. ولو كانت في الرابط لأمكن
     * فتح شاشة النتيجة بلا نتيجة، وشاشة الأسئلة بعد التسليم.
     *
     * والاختبار لا يعرض `correct_answers` أبدا: التصحيح في الخادم، وصفحة
     * تحمل الإجابات تحملها إلى متصفح الطالب.
     */
    public function placement()
    {
        $this->require_role('student');
        $uid = (int) $this->session->userdata('user_id');

        $this->load->model('taqdar_diag_model');
        $this->load->model('taqdar_billing_model');

        $grade_id = (int) $this->db->select('grade_id')->where('id', $uid)
                                   ->get('users')->row('grade_id');
        $exam = $this->taqdar_diag_model->exam_for_grade($grade_id);

        /* لا اختبار لصفه — أو لا صف له أصلا. لا تعرض شاشة فارغة تقول
           «لا شيء هنا»: تلك وجهة ميتة في قائمة. يعاد إلى الباقات، وهو ما
           كان سيصل إليه لولا هذه الميزة. */
        if (!$exam || array_sum($this->taqdar_diag_model->level_tally((int) $exam['id'])) < 1) {
            redirect(base_url('plans'), 'location', 302);
            return;
        }

        /* المحاولة المفتوحة تقرأ **قبل** المسلمة لا بعدها.
           والفرق يظهر في الإعادة وحدها: من فتح محاولة ثانية بإذن المسؤول
           له محاولة مفتوحة ومحاولة مسلمة معا — فقراءة المسلمة أولا تعيده
           إلى نتيجته القديمة أبدا، ويصير مفتاح «يسمح بالإعادة» في اللوحة
           إعدادا لا باب له. */
        $open = $this->db->where('student_id', $uid)->where('exam_id', (int) $exam['id'])
                         ->where('submitted_at IS NULL', null, false)
                         ->order_by('id', 'DESC')->limit(1)
                         ->get('tq_diag_attempts')->row_array();

        $done = $this->taqdar_diag_model->latest_attempt($uid, (int) $exam['id']);

        $state = $open ? 'exam' : ($done ? 'result' : 'intro');

        /* الباقة الموصى بها تقرأ كاملة لا بمعرفها: الشاشة تعرض بطاقتها
           بسعرها ومزاياها، فالنتيجة تنتهي إلى خطوة تالية لا إلى اسم. */
        $plan = null;
        if ($done && (int) $done['plan_id'] > 0) {
            $plan = $this->taqdar_billing_model->plan((int) $done['plan_id']);
        }

        $this->show('tq_placement', 'اختبار تحديد المستوى', array(
            'tq_counts'    => $this->counts($uid),
            'user_id'      => $uid,
            'tq_state'     => $state,
            'tq_exam'      => $exam,
            'tq_questions' => ($state === 'exam') ? $this->taqdar_diag_model->ordered_questions((int) $exam['id']) : array(),
            'tq_attempt'   => $done,
            'tq_plan'      => $plan,
            'tq_levels'    => Taqdar_diag_model::levels(),
        ));
    }

    /** بدء المحاولة — POST وحده، وهو ما يضبط لحظة البدء. */
    public function placement_start()
    {
        $this->write_guard('student');
        $uid = (int) $this->session->userdata('user_id');

        $this->load->model('taqdar_diag_model');
        $grade_id = (int) $this->db->select('grade_id')->where('id', $uid)
                                   ->get('users')->row('grade_id');
        $exam = $this->taqdar_diag_model->exam_for_grade($grade_id);

        if ($exam) $this->taqdar_diag_model->start($uid, (int) $exam['id']);

        redirect(base_url('student/placement'), 'location', 302);
    }

    /**
     * تسليم الاختبار.
     *
     * ما يرسله المتصفح اختيار الطالب وحده: `answers[<معرف السؤال>]` نص
     * الخيار. والصحة تقرر في `submit()` من `correct_answers` المقروءة من
     * القاعدة — فمن أضاف حقلا في نموذجه لم يضف إلى نتيجته شيئا.
     */
    public function placement_submit()
    {
        $this->write_guard('student');
        $uid = (int) $this->session->userdata('user_id');

        $this->load->model('taqdar_diag_model');
        $grade_id = (int) $this->db->select('grade_id')->where('id', $uid)
                                   ->get('users')->row('grade_id');
        $exam = $this->taqdar_diag_model->exam_for_grade($grade_id);

        if (!$exam) {
            redirect(base_url('plans'), 'location', 302);
            return;
        }

        $given = $this->input->post('answers');
        $r = $this->taqdar_diag_model->submit($uid, (int) $exam['id'], is_array($given) ? $given : array());

        if (!$r['ok']) {
            $this->session->set_flashdata('error_message', implode(' ', $r['errors']));
        } else {
            $this->trace('placement_submit', 'tq_diag_attempts#' . (int) $r['attempt_id'],
                         array('level' => $r['level'], 'score' => $r['score'], 'total' => $r['total']));
        }

        redirect(base_url('student/placement'), 'location', 302);
    }

    /* ---- صفحات عامة داخل الثيم ------------------------------------ */

    /** عامتان: تعرضان لأي زائر، فالباقات والأقسام جزء من الموقع لا من بوابة. */
    public function plans()      { $this->show('plans', 'الاشتراكات'); }
    public function categories() { $this->show('categories', 'الأقسام'); }

    /**
     * نتائج البحث العام.
     *
     * منفصلة عن `search()`: تلك تخص من له بوابة وتبقيه فيها، وهذه
     * لكل زائر. وكانت `search()` ترسل الزائر إلى `/courses?query=` —
     * وقد صارت تحويلا إلى `/plans` ولا تقرأ `query`، فكان بحثه يضيع.
     */
    public function site_search()
    {
        $q = trim((string) $this->input->get('q', true));
        $this->load->model('taqdar_site_model', 'tq_m');
        $this->show('site_search', $q === '' ? 'البحث' : 'نتائج البحث عن: ' . $q,
                    array('tq_q' => $q, 'tq_res' => $this->tq_m->site_search($q)));
    }

    /**
     * البحث — من داخل البوابة لا خارجها.
     *
     * كان يحول إلى `home/courses`: صفحة الكتالوج العامة بترويسة الموقع
     * وتذييله لا بشريط البوابة. فأول بحث يخرج الطالب من بوابته إلى شاشة
     * لا رابط فيها يعيده. المقصد الآن يشتق من الدور ويبقى داخل البوابة،
     * ويمرر النص في `q` فتلتقطه شاشة النتائج متى بنيت.
     *
     * والزائر وحده يرسل إلى الكتالوج العام — لأنه لا بوابة له أصلا.
     */
    public function search()
    {
        $q    = trim((string) $this->input->get('q', true));
        $role = tq_role();

        if ($role === 'guest') {
            /* كان `/courses?query=` — وهي الآن تحويل إلى `/plans` لا تقرأ
               `query`. فيرسل الزائر إلى صفحة النتائج العامة. */
            redirect(site_url('search') . ($q === '' ? '' : '?q=' . rawurlencode($q)), 'location', 302);
        }

        // شاشة نتائج داخل البوابة إن ركبت — وإلا لا نخرج بالمستخدم منها
        if (in_array($role, array('student', 'teacher', 'parent'), true)
            && is_file(APPPATH . 'views/frontend/' . $this->theme() . '/tq_search.php')) {
            $uid = (int) $this->session->userdata('user_id');
            $this->show('tq_search', 'نتائج البحث', array(
                'tq_counts' => $this->counts($uid, $role),
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
                'بحثت عن «' . $q . '» — وشاشة النتائج داخل البوابة قيد الإعداد.');
        }
        redirect(site_url($landing[$role]) . ($q === '' ? '' : '?q=' . rawurlencode($q)), 'location', 302);
    }

    /**
     * تصدير بيانات الحساب — ملف JSON بكل ما يخص المستخدم.
     * حق ينفذ لا سياسة تكتب.
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
            // آخر اشتراك مهما كانت حاله: المعلق تنتظره فاتورة، والمنتهي
            // يحتاج صاحبه أن يعرف أنه انتهى — لا أن يقال له «لا اشتراك لك».
            $cur = $this->db->where('user_id', (int) $uid)
                            ->order_by('id', 'DESC')->limit(1)
                            ->get('subscriptions')->row_array();
        }
        if ($cur) {
            $plan = $this->taqdar_billing_model->plan($cur['plan_id']);
            $cur['plan_name'] = $plan ? $plan['name_ar'] : '—';
        }

        $this->load->model('taqdar_tap_model');

        /* نتيجة التشخيص تعرض هنا لا في بند ثالث في القائمة.
           والسبب أن هذه هي الشاشة التي يقرر فيها الطالب أمر باقته — وهو
           السؤال الذي أجاب عنه الاختبار. وبند دائم في القائمة لشيء يؤدى
           مرة واحدة يبقى معلقا في كل صفحة بعد أن انتهى أمره، ويقود عند
           من لا اختبار لصفه إلى صفحة أخرى غير اسمه. */
        $this->load->model('taqdar_diag_model');

        $this->show('tq_subscription', 'اشتراكي', array(
            'tq_counts' => $this->counts($uid),
            'user_id'   => $uid,
            'current'   => $cur,
            'invoices'  => $this->taqdar_billing_model->invoices_of($uid),
            'tq_card'   => $this->taqdar_tap_model->ready(),
            'tq_level'  => $this->taqdar_diag_model->latest_result($uid),
        ));
    }

    /** الاشتراك يغير الحساب، فلا ينفذ بطلب GET قد يجلبه زاحف أو صورة. */
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
     * POST student/parent-link — يوافق الطالب على ربط ولي أمره أو يرفضه.
     *
     * الموافقة بيان قانوني يوقعه صاحبه: النموذج يرفض أن يوقعها ولي الأمر،
     * وهذا المسار يمنح الطالب القدرة على توقيعها فعلا.
     */
    public function parent_link_respond()
    {
        $this->require_role('student');
        if ($this->input->method(true) !== "POST") show_404();

        $uid     = (int) $this->session->userdata('user_id');
        $link_id = (int) $this->input->post('link_id');
        $action  = (string) $this->input->post('act');

        $this->load->model('taqdar_parent_model');

        /* الوسائط بمعناها.
           كان الرفض ينادي `revoke_link($link_id, $uid)` وتوقيعها
           `($parent_id, $student_id)` — فيبحث الاستعلام عن رابط
           `parent_user_id = <رقم الطلب>` ولا يطابق شيئا أبدا. كل ضغطة
           «أرفض» كانت ترد «لا رابط نشط بهذا الابن في حسابك» ويبقى الطلب
           معلقا. ولكل فعل الآن دالته الموقعة باسمه. */
        if ($action === 'reject') {
            $r = $this->taqdar_parent_model->reject_request($link_id, $uid);
            $msg_ok = 'رفضت الطلب، ولم يفتح شيء من بياناتك.';
        } elseif ($action === 'withdraw') {
            $r = $this->taqdar_parent_model->withdraw_consent($uid, $link_id);
            $msg_ok = 'سحبت موافقتك، ولم يعد ولي أمرك يرى شيئا من بياناتك.';
        } else {
            $r = $this->taqdar_parent_model->grant_consent($link_id, $uid);
            $msg_ok = 'وافقت على الربط، ويستطيع ولي أمرك متابعة تقدمك الآن.';
        }

        $this->trace('student.parent_link.' . ($action ?: 'approve'), 'parent_links:' . $link_id,
            array('ok' => !empty($r['ok'])));

        $ok = !empty($r['ok']);
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message',
            $ok ? $msg_ok : (isset($r['errors']) ? implode(' ', $r['errors']) : 'تعذر تنفيذ الطلب.'));
        redirect(base_url('student/settings?s=profile'));
    }

    public function subscribe_path()
    {
        $this->require_role('student');
        if ($this->input->method(true) !== "POST") show_404();

        $uid = (int) $this->session->userdata('user_id');

        $this->load->model('taqdar_tap_model');
        $by_card = ((string) $this->input->post('pay_method') === 'tap')
                && $this->taqdar_tap_model->ready();

        $this->load->model('taqdar_billing_model');
        $r = $this->taqdar_billing_model->subscribe_path(
            $uid,
            (int) $this->input->post('path_id'),
            $by_card ? 'tap' : 'manual'
        );

        if (!$r['ok']) {
            $this->session->set_flashdata('error_message', implode(' ', $r['errors']));
            redirect(base_url('student'));
            return;
        }

        /* المسار يدفع بالبطاقة بالمسار نفسه الذي تدفع به الباقة: الفاتورة
           هي المرساة في الحالين، فلا فرع ثان في `Taqdar_tap_model`. */
        if ($by_card) {
            $pay = $this->taqdar_tap_model->start((int) $r['invoice_id'], $uid);
            if (!empty($pay['ok'])) {
                redirect($pay['url'], 'location', 302);
                return;
            }
            $this->session->set_flashdata('error_message',
                implode(' ', $pay['errors']) . ' وفاتورتك صدرت، فيمكنك تحويل قيمتها بنكيا أو إعادة المحاولة من هنا.');
            redirect(base_url('student/subscription'));
            return;
        }

        $this->session->set_flashdata('flash_message',
            'صدرت فاتورتك. حول قيمتها ويفتح المسار بعد التحقق من الحوالة.');
        redirect(base_url('student/subscription'));
    }

    /**
     * تأكيد الاشتراك — يصدر الفاتورة، ثم يدفعها بالبطاقة أو يترك تحويلها.
     *
     * الترتيب مقصود: **الفاتورة تصدر أولا في الحالين.** لو أنشئت الدفعة
     * عند تاب قبل أن تكتب الفاتورة، لصار من دفع ثم سقط الاتصال قد دفع
     * بلا صف عندنا يقابل دفعته. والعكس مأمون: فاتورة صدرت ولم تدفع تحول
     * بنكيا أو تدفع لاحقا بزر «ادفع بالبطاقة» في صفحة الاشتراك.
     */
    public function subscribe()
    {
        $this->require_role('student');
        if ($this->input->method(true) !== 'POST') show_404();

        $uid = (int) $this->session->userdata('user_id');

        $this->load->model('taqdar_tap_model');
        $by_card = ((string) $this->input->post('pay_method') === 'tap')
                && $this->taqdar_tap_model->ready();

        $this->load->model('taqdar_billing_model');
        $r = $this->taqdar_billing_model->subscribe(
            $uid,
            (int) $this->input->post('plan_id'),
            $by_card ? 'tap' : 'manual'
        );

        if (!$r['ok']) {
            $this->session->set_flashdata('error_message', implode(' ', $r['errors']));
            redirect(base_url('plans'));
            return;
        }

        if (!empty($r['free'])) {
            $this->session->set_flashdata('flash_message', 'فعلت باقتك المجانية.');
            redirect(base_url('student/subscription'));
            return;
        }

        if ($by_card) {
            $pay = $this->taqdar_tap_model->start((int) $r['invoice_id'], $uid);
            if (!empty($pay['ok'])) {
                redirect($pay['url'], 'location', 302);
                return;
            }
            /* تعذر بدء الدفع: الفاتورة صدرت ولم تضع، فيقال ما وقع ويدل
               على البديل القائم — لا يعاد الطالب إلى الباقات وقد صار
               له اشتراك معلق. */
            $this->session->set_flashdata('error_message',
                implode(' ', $pay['errors']) . ' وفاتورتك صدرت، فيمكنك تحويل قيمتها بنكيا أو إعادة المحاولة من هنا.');
            redirect(base_url('student/subscription'));
            return;
        }

        $this->session->set_flashdata('flash_message',
            'صدرت فاتورتك. حول قيمتها ويفعل اشتراكك بعد التحقق من الحوالة.');
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
                'أوقف التجديد — ويبقى اشتراكك صالحا حتى تاريخ انتهائه.');
        }
        redirect(base_url('student/subscription'));
    }


    /** كل نماذج صفحة الإعدادات تصب هنا؛ القرار في النموذج لا في الباب. */
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
     * POST teacher/settings/save
     *
     * صفحة إعدادات المعلم تصب هنا. النموذج واحد للدورين
     * (`Taqdar_settings_model`) لأن الملف الشخصي وكلمة المرور والتنبيهات
     * والتفضيلات معنى واحد لا يختلف بالدور — والاختلاف الوحيد قسم
     * «صفحتي العامة»، وله معالجه `teacher` في الموزع نفسه.
     *
     * ولا يعاد استعمال `settings_save()` بحارسها: حارسها `student`، ونداؤها
     * من بوابة المعلم يرد المعلم إلى بوابة الطالب برسالة أنها ليست له.
     */
    public function teacher_settings_save()
    {
        $user = $this->write_guard('teacher');
        $uid  = (int) $user['id'];

        $this->load->model('taqdar_settings_model');
        $r = $this->taqdar_settings_model->handle($uid, (string) $this->input->post('action', true));

        $this->trace('teacher.settings.save', 'users:' . $uid,
            array('action' => (string) $this->input->post('action', true), 'ok' => !empty($r['ok'])));

        $section = (string) $this->input->post('s', true);
        if (!in_array($section, array('profile','teacher','security','alerts','prefs','payouts'), true)) {
            $section = isset($r['section']) ? $r['section'] : 'profile';
        }

        $this->done('teacher/settings?s=' . $section, !empty($r['ok']),
            $this->result_message($r, 'حفظت إعداداتك.'));
    }


    /**
     * POST teacher/students/message
     * النطاق يعاد فرضه هنا كاملا: العرض يخفي، والخادم وحده يمنع.
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
            $this->session->set_flashdata('error_message', 'اكتب نص الرسالة قبل الإرسال.');
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
            $this->session->set_flashdata('error_message', 'لا ترسل الرسائل إلا إلى طلاب كورساتك.');
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

    /**
     * POST student/favourite
     *
     * قلب التفضيل. كان القلب في `tq_favourites.php` زرا بلا نموذج ولا معالج،
     * وتحته سطر يعد صراحة بأن الضغط عليه يزيل العنصر — فالوعد مكتوب والفعل
     * غائب. وهذا مسار الفعل.
     *
     * والملكية تفحص في النموذج لا هنا: `write_guard` يثبت الدور، والنموذج
     * يثبت أن العنصر داخل كورس مسجل لصاحب الطلب.
     */
    public function favourite_toggle()
    {
        $user = $this->write_guard('student');
        $uid  = (int) $user['id'];

        $kind = (string) $this->input->post('kind', true);
        $id   = (int) $this->input->post('item_id');

        $this->load->model('taqdar_favourites_model');
        $r = ($kind === 'course')
            ? $this->taqdar_favourites_model->toggle_course($uid, $id)
            : $this->taqdar_favourites_model->toggle($uid, $kind, $id);

        $this->trace('student.favourite.toggle', $kind . ':' . $id,
                     array('ok' => !empty($r['ok']), 'on' => !empty($r['on'])));

        /* العودة إلى الشاشة التي ضغط فيها القلب لا إلى المفضلة دائما — ومعها
           تصفيتها وترتيبها كما كانا، فمن رتب قائمته ثم أزال عنصرا لا يعاد
           إلى أولها.

           والوجهة تبنى من **قائمة بيضاء** لا من `$_POST` خاما: `redirect()`
           بقيمة يرسلها الطالب تفتح تحويلا مفتوحا إلى أي موقع. */
        $pages = array(
            'favourites' => 'student/favourites',
            /* شاشتان لا واحدة منذ فصل الدرس عن الكورس: `lessons` قائمة
               الدروس و`courses` شبكة الكورسات، ولكل منهما قلبها ومصفياتها. */
            'lessons'    => 'student/lessons',
            'courses'    => 'student/courses',
            'materials'  => 'student/materials',
        );
        $from = (string) $this->input->post('back', true);

        /* صفحة الكورس العامة تعيد إلى نفسها: القلب فيها كان رابطا إلى
           `home/toggleWishlistItems/<id>` وهي نقطة AJAX ترد JSON وتحمل
           قالبا لا وجود له في ثيم تقدر — فيسقط الطلب بـ«Unable to load the
           requested file». والوجهة تبنى من عنوان الكورس في القاعدة لا من
           المدخل، فلا تصير هذه النقطة بابا للتحويل إلى أي موقع. */
        if ($from === 'course' && $kind === 'course' && $id > 0) {
            $title = (string) $this->db->select('title')->where('id', $id)
                                       ->get('course')->row('title');
            $back  = $title !== ''
                ? 'home/course/' . rawurlencode(slugify($title)) . '/' . $id
                : 'student/favourites';
            $this->done($back, !empty($r['ok']), $r['msg']);
            return;
        }

        $back = $pages[$from] ?? 'student/favourites';

        $qs   = array();
        $type = (string) $this->input->post('back_type', true);
        $sort = (string) $this->input->post('back_sort', true);
        $qsrc = (string) $this->input->post('back_q', true);
        if ($from === 'favourites' && in_array($type, array('lessons', 'materials', 'courses'), true)) $qs['type'] = $type;
        if ($from === 'materials'  && in_array($type, array('pdf','video','slide','audio','image','link'), true)) $qs['type'] = $type;
        if ($from === 'favourites' && in_array($sort, array('recent', 'title', 'progress'), true)) $qs['sort'] = $sort;
        if ($qsrc !== '') $qs['q'] = mb_substr($qsrc, 0, 120);

        /* تصفية قائمة الدروس تسافر معها: من صفى على كورس ثم ضغط قلبا كان
           يعود إلى القائمة كلها من أولها فيفقد موضعه. والكورس يقرأ رقما،
           والحالة من قائمة بيضاء — لا يبنى شيء من المدخل كما هو. */
        if ($from === 'lessons') {
            $back_course = (int) $this->input->post('back_course');
            $back_state  = (string) $this->input->post('back_state', true);
            if ($back_course > 0) $qs['course'] = $back_course;
            if (in_array($back_state, array('todo', 'current', 'done'), true)) $qs['state'] = $back_state;
        }
        if ($qs) $back .= '?' . http_build_query($qs);

        $this->done($back, !empty($r['ok']), $r['msg']);
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
     * تستبدل حقول الهوية بقيم مجهولة وتبقى القيود المالية بمعرف مجهول،
     * لأن الالتزام الضريبي يوجب حفظ الفواتير. وهذا ما تنص عليه الوثيقة.
     */
    public function delete_account()
    {
        $this->require_login();
        $uid = (int) $this->session->userdata('user_id');

        if (tq_role($uid) === 'admin') {
            show_error('لا يحذف حساب إداري من هنا. استعمل لوحة الإدارة.', 403, 'غير مسموح');
        }

        // التأكيد بـ POST وحده. رابط GET يحذف حسابا قد يفتحه زاحف أو
        // استباق تحميل في المتصفح — فيجهل حساب بلا أن يطلب صاحبه شيئا.
        if ($this->input->method(true) !== 'POST' || $this->input->post('confirm') !== 'yes') {
            $this->show('tq_delete_account', 'حذف الحساب', array(
                'tq_counts' => $this->counts($uid, tq_role($uid)),
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

    /* ---- بوابة المعلم ---------------------------------------------- */

    public function teacher($section = 'dashboard')
    {
        $map = [
            'dashboard'     => ['tq_teacher_dashboard',      'لوحة المعلم'],
            'courses'       => ['tq_teacher_courses',        'كورساتي'],
            /* الدرس وحدة عمل المعلم اليومية، ولم تكن له شاشة: رقم مجمل في
               جدول الكورسات، وآخر خمسة في زاوية شاشة الرفع. */
            'lessons'       => ['tq_teacher_lessons',        'دروسي'],
            'upload'        => ['tq_teacher_upload',         'رفع الدروس'],
            'questions'     => ['tq_teacher_questions',      'بنك الأسئلة'],
            'marking'       => ['tq_teacher_marking',        'الواجبات والتصحيح'],
            'students'      => ['tq_teacher_students',       'طلابي'],
            'sessions'      => ['tq_teacher_sessions',       'الحصص'],
            'wallet'        => ['tq_teacher_wallet',         'المحفظة والأرباح'],
            /* الثلاثة الأخيرة كانت غائبة عن البوابة كلها: لا رسائل يقرأ فيها
               المعلم رد طالبه، ولا إشعارات رغم أن `counts()` يعدها له، ولا
               إعدادات — ولا تسجيل خروج معها، فلم يكن للمعلم باب يخرج منه. */
            'messages'      => ['tq_teacher_messages',       'الرسائل'],
            'notifications' => ['tq_teacher_notifications',  'الإشعارات'],
            'settings'      => ['tq_teacher_settings',       'الإعدادات'],
        ];
        if (!isset($map[$section])) show_404();

        $user = $this->require_role('teacher');
        [$page, $title] = $map[$section];

        $this->show($page, $title, [
            'tq_counts' => $this->counts($user['id'], 'teacher'),
            'user_id'   => $user['id'],
        ]);
    }

    /* ---- بوابة ولي الأمر ------------------------------------------ */

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
            'tq_counts' => $this->counts($user['id'], 'parent'),
            'user_id'   => $user['id'],
        ]);
    }

    /* =====================================================================
       مسارات الكتابة
       ---------------------------------------------------------------------
       دالة العرض تعرض ولا تكتب.

       قبل هذا كان كل نموذج في بوابتي المعلم وولي الأمر يرسل إلى مسار
       العرض نفسه، و`teacher()`/`parent_portal()` لا تفحصان نوع الطلب أصلا:
       فيستدعى الحارس، ثم تعرض الصفحة كأن شيئا لم يكن. لا كتابة، ولا خطأ،
       ولا رسالة — وهذا أسوأ من العطل الظاهر لأن المستخدم يظن أنه حفظ.

       فالكتابة الآن مسارات مستقلة صريحة: POST وحده، ثم الدور، ثم الملكية،
       ثم تفويض العمل إلى نموذج، ثم تحويل 302 برسالة. و`redirect(...,'refresh')`
       متروك عمدا: لا يتبعه curl فيبدو الفحص ناجحا وهو لم يصل، ويكسر زر
       الرجوع في المتصفح.
       ===================================================================== */

    /** حارس الكتابة. الرابط الذي يكتب بـ GET يستدعيه زاحف أو استباق تحميل. */
    private function write_guard($role)
    {
        if ($this->input->method(true) !== 'POST') show_404();
        return $this->require_role($role);
    }

    /**
     * رد المستخدم إلى صفحته برسالة — 302 دائما.
     *
     * الرسالة تكتب بمفتاحي المنصة (`flash_message`/`error_message`) وبمفتاحي
     * شاشات تقدر (`tq_ok`/`tq_error`) معا: الشاشات في البوابات الثلاث لا
     * تقرأ بالاصطلاح نفسه، ورسالة لا تقرأ كأنها لم تكتب.
     */
    private function done($path, $ok, $message)
    {
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message', $message);
        $this->session->set_flashdata($ok ? 'tq_ok' : 'tq_error', $message);
        redirect(site_url($path), 'location', 302);
    }

    /** رسالة النتيجة من عقد النموذج الموحد. */
    private function result_message($r, $success)
    {
        if (!empty($r['ok'])) {
            return isset($r['message']) && $r['message'] !== '' ? $r['message'] : $success;
        }
        // النماذج تشرح فشلها بمفتاحين مختلفين — وابتلاع الشرح أسوأ من الفشل
        if (!empty($r['errors']))  return implode(' ', (array) $r['errors']);
        if (!empty($r['message'])) return (string) $r['message'];
        return 'تعذر تنفيذ الطلب.';
    }

    /**
     * تفويض العمل إلى نموذج قد لا يكون ولد بعد.
     *
     * المسارات تبنى قبل النماذج. ولو نودي على نموذج غائب لسقطت الصفحة بخطأ
     * قاتل بدل أن تقول ما ينقص — فيظن المجرب أن المسار نفسه مكسور.
     * فالفحص على ثلاث مراحل: وجود الملف، ثم إعلانه الصنف (ملف يكتب الآن
     * قد يكون نصفه فيقتل `load->model`)، ثم وجود الدالة.
     *
     * وتوقيع كل نموذج شأنه: فالثالث في الزوج وسائطه هو إن اختلفت عن العامة —
     * لأن إجبار نموذج قائم على توقيع المتحكم يكسره، والعكس أصح.
     *
     * @param array $candidates [اسم النموذج، اسم الدالة، وسائط خاصة؟] بترتيب الأفضلية
     * @param array $args       الوسائط العامة
     * @return array عقد موحد: ok | errors | message
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
                continue; // ملف قيد الكتابة: تحميله الآن خطأ قاتل
            }

            $this->load->model($model);
            if (!isset($this->$model) || !method_exists($this->$model, $method)) continue;

            $out = call_user_func_array(array($this->$model, $method), $call);

            if (is_array($out) && array_key_exists('ok', $out))  return $out;
            if (is_array($out) && isset($out['error'])) {
                return array('ok' => false, 'errors' => array((string) $out['error']));
            }
            /* نموذج يعيد عددا لا حكما: صفر نتيجة صحيحة لا فشل — من مسح
               كل فتراته حفظ صفرا وقد نجح. فـ(bool) هنا كانت تقلب النجاح فشلا. */
            if (isset($pair[3]) && $pair[3] === 'numeric_ok' && is_numeric($out)) {
                return array('ok' => true, 'errors' => array(), 'count' => (int) $out);
            }
            return array('ok' => (bool) $out, 'errors' => array(), 'result' => $out);
        }

        return array(
            'ok'      => false,
            'pending' => true,
            'errors'  => array(
                'وصل الطلب وصلاحيتك صحيحة، والنموذج المنفذ لم يركب بعد ('
                . implode(' أو ', array_unique($wanted)) . ').'
            ),
        );
    }

    /** أثر في audit_log لكل كتابة — عبر نموذج المستودع القائم إن وجد. */
    private function trace($action, $entity, $payload = null)
    {
        if (!is_file(APPPATH . 'models/Taqdar_repo_model.php')) return;
        $this->load->model('taqdar_repo_model');
        if (!method_exists($this->taqdar_repo_model, 'audit')) return;

        $this->taqdar_repo_model->audit(
            (int) $this->session->userdata('user_id'), $action, $entity, null, $payload
        );
    }

    /* ---- الملكية: تقرأ من قاعدة البيانات لا من الحقول المرسلة --------- */

    /**
     * ملكية الكورس.
     *
     * `course.user_id` **قائمة معرفات مفصولة بفواصل** لا معرفا واحدا،
     * ومعه عمود `creator` للمنشئ. فمقارنة `(int) user_id` كانت تقرأ «12,37»
     * على أنها 12: ترد المعلم الثاني في كورس مشترك، وترد المنشئ نفسه إن
     * لم يكن أول القائمة. وهذا يمنع صاحب الحق لا المتطفل — ويقول له عن
     * كورسه إنه «ليس كورسك». والصيغة هنا هي المعمول بها في بقية المشروع.
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

    /** أبناء ولي الأمر بموافقة نشطة — لا غيرهم. */
    private function parent_owns_child($parent_id, $student_id)
    {
        $student_id = (int) $student_id;
        if ($student_id < 1 || !$this->db->table_exists('parent_links')) return false;

        return (int) $this->db->where('parent_user_id', (int) $parent_id)
                              ->where('student_id', $student_id)
                              ->where('status', 'active')
                              ->count_all_results('parent_links') > 0;
    }

    /** قائمة نصية نظيفة من حقل مكرر — بلا مصفوفات متداخلة تسقط trim. */
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

    /* ---- بوابة المعلم: الكتابة ------------------------------------- */

    /** POST teacher/upload/save */
    public function upload_save()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $course_id = (int) $this->input->post('course_id');
        $title     = trim((string) $this->input->post('title'));

        if (!$this->teacher_owns_course($tid, $course_id)) {
            $this->done('teacher/upload', false,
                'اختر كورسا من كورساتك — لا يرفع درس إلى كورس ليس لك.');
        }
        if ($title === '') {
            $this->done('teacher/upload', false, 'عنوان الدرس مطلوب.');
        }

        /**
         * الحمولة تنقل ما اختاره المعلم كما هو.
         *
         * كان هذا السطر يكتب `$this->input->post('action') === 'draft' ? 'draft' : 'review'`
         * — أي يمحو `published` قبل أن يصل النموذج. فزر «حفظ ونشر» يحفظ
         * «قيد المراجعة» ويقال لصاحبه «أرسل للمراجعة»، وهو ضغط زر النشر.
         * والقرار في `Taqdar_teacher_model::save_lesson()`: هي التي تعرف
         * الكورس وحالته، وتنشر داخل المنشور وتنزل إلى المراجعة فيما عداه
         * **وتقول لماذا**. والباب ينقل ولا يحكم.
         */
        $tq_action = strtolower(trim((string) $this->input->post('action')));
        if (!in_array($tq_action, array('draft', 'review', 'published'), true)) {
            $tq_action = 'draft';
        }

        $payload = array(
            'course_id'        => $course_id,
            'section_id'       => (int) $this->input->post('section_id'),
            'title'            => $title,
            'duration_minutes' => (int) $this->input->post('duration_minutes'),
            'summary'          => (string) $this->input->post('summary'),
            'objectives'       => $this->post_list('objectives'),
            'action'           => $tq_action,
        );

        $r = $this->delegate(array(
            array('taqdar_teacher_model', 'save_upload'),
            array('taqdar_teacher_model', 'upload_save'),
            array('taqdar_repo_model',    'teacher_save_upload'),
        ), array($tid, $payload, isset($_FILES) ? $_FILES : array()));

        $this->trace('teacher.upload.save', 'course:' . $course_id,
            array('title' => $title, 'ok' => !empty($r['ok'])));

        $this->done('teacher/upload', !empty($r['ok']),
            $this->result_message($r, 'حفظ الدرس وأرسل للمراجعة.'));
    }

    /** POST teacher/marking/approve */
    public function marking_approve()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $result_id = (int) $this->input->post('result_id');
        if ($result_id < 1) {
            $this->done('teacher/marking', false, 'لم يحدد التسليم المراد اعتماده.');
        }

        $res = $this->db->where('quiz_result_id', $result_id)->get('quiz_results')->row_array();
        if (!$res) {
            $this->done('teacher/marking', false, 'لا يوجد تسليم بهذا المعرف.');
        }
        // التصحيح ملك صاحب الكورس: الدرجة تغير سجل طالب، فلا تترك لمن مر بالرابط
        if (!$this->teacher_owns_lesson($tid, $res['quiz_id'])) {
            $this->done('teacher/marking', false, 'هذا التسليم في كورس ليس لك.');
        }

        /**
         * سحب الاعتماد.
         *
         * `Taqdar_marking_model::unapprove()` مكتوبة منذ بنيت الشاشة ولا
         * مسار إليها ولا زر: حاجز يوضع ولا يرفع. والشاشة تقول للمعلم إن
         * الدرجة المعتمدة «تعديلها يحل محلها» — وهو صحيح، لكن من اعتمد
         * محاولة بالخطأ لا يملك أن يعيدها إلى الحجب.
         */
        if ((string) $this->input->post('act') === 'unapprove') {
            $r = $this->delegate(array(
                array('taqdar_marking_model', 'unapprove', array($result_id, $tid)),
            ), array($result_id, $tid));

            $this->trace('teacher.marking.unapprove', 'quiz_results:' . $result_id,
                array('ok' => !empty($r['ok'])));

            $this->done('teacher/marking?result=' . $result_id, !empty($r['ok']),
                $this->result_message($r, 'سحب الاعتماد، والدرجة محجوبة عن الطالب من جديد.'));
        }

        $score = $this->input->post('score');
        if ($score !== null && $score !== '' && (!is_numeric($score) || (float) $score < 0)) {
            $this->done('teacher/marking', false, 'الدرجة تكتب عددا موجبا.');
        }

        /* لا حقل `mastery` هنا: النموذج لا يرسله والموديل لا يقرؤه —
           كان يقرأ من POST ويمرر ويسجل في `audit_log` ولا يغير شيئا،
           فيقرأ المدقق في السجل قيمة لم يخترها أحد. وحالة الإتقان تشتق
           من الدرجة والعتبة في `Taqdar_marking_model::mastery()`. */
        $r = $this->delegate(array(
            array('taqdar_marking_model', 'approve_marking'),
            array('taqdar_marking_model', 'marking_approve'),
            array('taqdar_teacher_model', 'approve_marking'),
            array('taqdar_teacher_model', 'marking_approve'),
            array('taqdar_repo_model',    'teacher_approve_marking'),
        ), array($tid, array(
            'result_id'  => $result_id,
            'student_id' => (int) $res['user_id'],
            'quiz_id'    => (int) $res['quiz_id'],
            'score'      => ($score === null || $score === '') ? null : (float) $score,
            'note'       => (string) $this->input->post('note'),
        )));

        $this->trace('teacher.marking.approve', 'quiz_results:' . $result_id,
            array('score' => $score, 'ok' => !empty($r['ok'])));

        /* الطالب ووليه يعلمان بالنتيجة — انظر notify_marking(). */
        if (!empty($r['ok'])) {
            $this->notify_marking((int) $res['user_id'], $result_id, $tid);
        }

        $this->done('teacher/marking', !empty($r['ok']),
            $this->result_message($r, 'اعتمدت الدرجة وأبلغ الطالب.'));
    }

    /**
     * POST teacher/marking/homework — اعتماد درجة واجب أو سحبها.
     *
     * مسار مستقل عن `marking_approve()` لأن المصدر مختلف: ذاك على
     * `quiz_results` وهذا على `attempts`، ومعرف «٧» في أحدهما ليس معرف
     * «٧» في الآخر. وخلطهما في باب واحد يجعل رقما في نموذج يعتمد صفا
     * في جدول لا يقصده صاحبه.
     *
     * والملكية تفحص في النموذج داخل الاستعلام
     * (`Taqdar_marking_model::homework_attempt()`)، لا هنا.
     */
    public function marking_homework()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $attempt_id = (int) $this->input->post('attempt_id');
        if ($attempt_id < 1) {
            $this->done('teacher/marking', false, 'لم يحدد الواجب المراد اعتماده.');
        }

        $this->load->model('taqdar_marking_model');

        if ((string) $this->input->post('act') === 'unapprove') {
            $r = $this->taqdar_marking_model->unapprove_homework($attempt_id, $tid);

            $this->trace('teacher.marking.homework.unapprove', 'attempts:' . $attempt_id,
                array('ok' => !empty($r['ok'])));

            $this->done('teacher/marking?hw=' . $attempt_id, !empty($r['ok']),
                $this->result_message($r, 'سحب الاعتماد.'));
        }

        $r = $this->taqdar_marking_model->approve_homework($tid, array(
            'attempt_id' => $attempt_id,
            'score'      => $this->input->post('score'),
            'note'       => (string) $this->input->post('note'),
        ));

        $this->trace('teacher.marking.homework.approve', 'attempts:' . $attempt_id,
            array('ok' => !empty($r['ok'])));

        if (!empty($r['ok']) && !empty($r['attempt'])) {
            $this->notify_homework($r['attempt'], $tid, !empty($r['passed']));
        }

        $this->done('teacher/marking', !empty($r['ok']),
            $this->result_message($r, 'اعتمدت درجة الواجب وأبلغ الطالب.'));
    }

    /** إشعار الطالب ووليه باعتماد درجة واجب — كإشعار الاختبار سواء. */
    private function notify_homework($attempt, $teacher_id, $passed)
    {
        try {
            $this->load->model('taqdar_events_model');
            $score = $attempt['teacher_score'] !== null
                ? (float) $attempt['teacher_score'] : (float) $attempt['score'];
            $total = max(1, (int) $attempt['total_marks']);

            $this->taqdar_events_model->notify_student_and_parents(
                (int) $attempt['student_id'],
                $passed ? 'exam_result' : 'station_failed',
                array(
                    'key'         => 'homework:' . (int) $attempt['id'],
                    'from_user'   => (int) $teacher_id,
                    'window_days' => 30,
                    'title'       => $passed ? 'صحح واجبك' : 'واجبك يحتاج إعادة',
                    'text'        => 'واجب «' . $attempt['lesson_title'] . '» في '
                                   . $attempt['course_title'] . ': '
                                   . $score . ' من ' . $total
                                   . ($passed ? ' — اجتزته.' : ' — لم تبلغ درجة العبور.'),
                )
            );
        } catch (Throwable $e) {
            // الدرجة محفوظة، والإشعار زيادة لا شرط
        }
    }

    /**
     * إشعار الطالب ووليه باعتماد درجة.
     *
     * كان الاعتماد يكتب الدرجة ثم ينتهي: الطالب لا يعلم أنها ظهرت إلا إن
     * فتح شاشة اختباراته من تلقاء نفسه، وولي أمره لا يعلم أصلا. والأداة
     * قائمة منذ زمن (`Taqdar_events_model::notify_student_and_parents()`)
     * ونوع الحدث في كتالوجها (`exam_result`)، والوصلة وحدها كانت ناقصة.
     *
     * ونوع الحدث يفرق بالنتيجة لا بالفعل: من رسب يصله `station_failed`
     * لأنه ما يستدعي تدخلا، ومن نجح يصله `exam_result`. والعتبة من
     * `Taqdar_marking_model` نفسه فلا تفترق عن عتبة الشاشة.
     *
     * وأي تعثر هنا يبتلع: الدرجة حفظت فعلا، وإسقاط الصفحة بعد الحفظ
     * يجعل المعلم يظن أن اعتماده لم يقع فيعيده.
     */
    private function notify_marking($student_id, $result_id, $teacher_id)
    {
        try {
            $this->load->model('taqdar_marking_model');
            $row = $this->taqdar_marking_model->attempt($result_id, $teacher_id);
            if (!$row) return;

            $score   = $row['teacher_score'] !== null ? (float) $row['teacher_score']
                                                      : (float) $row['total_obtained_marks'];
            $total   = max(1, (int) $row['total_marks']);
            $mastery = $this->taqdar_marking_model->mastery($score, $total);
            $passed  = ($mastery['key'] === 'mastered');

            $this->load->model('taqdar_events_model');
            $this->taqdar_events_model->notify_student_and_parents(
                (int) $student_id,
                $passed ? 'exam_result' : 'station_failed',
                array(
                    'key'         => 'attempt:' . (int) $result_id,
                    'from_user'   => (int) $teacher_id,
                    'window_days' => 30,
                    'text'        => 'اختبار «' . $row['quiz_title'] . '» في '
                                   . $row['course_title'] . ': '
                                   . $score . ' من ' . $total . ' — ' . $mastery['label'] . '.',
                )
            );
        } catch (Throwable $e) {
            // الدرجة محفوظة، والإشعار زيادة لا شرط
        }
    }

    /** POST teacher/sessions/save */
    public function sessions_save()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        // قيمة الخانة «فهرس اليوم:مفتاح الفترة» — وما خالف الشكل لا يمرر
        $slots = array();
        foreach ($this->post_list('slots') as $s) {
            if (preg_match('/^[0-6]:[A-Za-z0-9_-]{1,24}$/', $s)) $slots[] = $s;
        }

        // إن أرسلت معرفات فترات قائمة فلا بد أن تكون فترات هذا المعلم
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
            ? 'حفظت أوقاتك المتاحة — '
              . tq_count_units((int) $r['count'], 'فترة', 'فترتان', 'فترتين', 'فترات', 'فترة', 'لا فترة', 'obl', true)
              . ' مفتوحة هذا الأسبوع.'
            : 'حفظت أوقاتك المتاحة.';

        $this->done('teacher/sessions', !empty($r['ok']), $this->result_message($r, $done));
    }

    /**
     * POST teacher/sessions/decide — تأكيد طلب حصة أو الاعتذار عنه.
     *
     * كانت هذه الكتابة تقع **داخل العرض**: `tq_teacher_sessions.php` يقرأ
     * `tq_action` من POST ويحدث الجدول ثم يحول، والنماذج ترسل إلى مسار
     * العرض `teacher/sessions`. وهو مخالف صريح لقاعدة هذا المشروع
     * («مسارات الكتابة قبل مسارات العرض») ويلتف على `write_guard` كله.
     * وأغرب منه أن `sessions_save()` والمسار `teacher/sessions/save` كانا
     * موجودين ولا نموذج يرسل إليهما — تنفيذان لعمل واحد، أحدهما ميت.
     *
     * والملكية والحالة تفحصان داخل الاستعلام في `Taqdar_sessions_model::decide()`،
     * فما هنا حراسة الباب لا حراسة البيانات.
     */
    public function sessions_decide()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $session_id = (int) $this->input->post('session_id');
        $decision   = (string) $this->input->post('decision');
        if (!in_array($decision, array('confirm', 'decline'), true)) {
            $this->done('teacher/sessions', false, 'إجراء غير معروف.');
        }

        $this->load->model('taqdar_sessions_model');
        $r = $this->taqdar_sessions_model->decide(
            $session_id, $tid, $decision, (string) $this->input->post('meet_url')
        );

        $this->trace('teacher.sessions.' . $decision, 'tutoring_sessions:' . $session_id,
            array('ok' => !empty($r['ok'])));

        /* الطالب يعلم بالرد — كان يؤكد له المعلم موعدا فلا يصله شيء،
           ويعتذر عنه فلا يصله شيء، ولا يعرف إلا إن فتح شاشة الحجوزات. */
        if (!empty($r['ok'])) {
            $this->notify_session_decision($session_id, $tid, $decision);
        }

        $this->done('teacher/sessions', !empty($r['ok']),
            isset($r['msg']) ? $r['msg'] : 'حفظ ردك على الطلب.');
    }

    /** إشعار الطالب برد معلمه على طلب حصته. */
    private function notify_session_decision($session_id, $teacher_id, $decision)
    {
        try {
            $row = $this->db->query(
                'SELECT s.student_id, a.starts_at
                   FROM `tutoring_sessions` s
              LEFT JOIN `availability_slots` a ON a.id = s.slot_id
                  WHERE s.id = ? AND s.teacher_id = ? LIMIT 1',
                array((int) $session_id, (int) $teacher_id)
            )->row_array();
            if (!$row) return;

            $this->load->model('taqdar_sessions_model');
            $when = !empty($row['starts_at'])
                ? $this->taqdar_sessions_model->when_text($row['starts_at']) : '';

            $this->load->model('taqdar_events_model');
            $this->taqdar_events_model->notify(
                (int) $row['student_id'],
                'session_request',
                array(
                    'key'         => 'session:' . (int) $session_id . ':' . $decision,
                    'from_user'   => (int) $teacher_id,
                    'window_days' => 30,
                    'title'       => $decision === 'confirm' ? 'أكدت حصتك الخاصة' : 'اعتذر المعلم عن حصتك',
                    'text'        => $decision === 'confirm'
                        ? 'أكد معلمك الحصة' . ($when !== '' ? ' — ' . $when : '') . '. رابط الدخول في صفحة حصصك.'
                        : 'اعتذر معلمك عن الموعد' . ($when !== '' ? ' — ' . $when : '') . '، واختر موعدا آخر من حصص بالطلب.',
                )
            );
        } catch (Throwable $e) {
            // الرد حفظ فعلا، والإشعار زيادة لا شرط
        }
    }

    /** POST teacher/wallet/withdraw */
    public function wallet_withdraw()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $amount = $this->input->post('withdrawal_amount');
        if (!is_numeric($amount) || (float) $amount <= 0) {
            $this->done('teacher/wallet', false, 'اكتب مبلغا موجبا.');
        }
        $amount = (float) $amount;

        $dest = trim((string) $this->input->post('destination'));
        if ($dest === '') {
            $this->done('teacher/wallet', false,
                'اكتب بيانات التحويل — لا يرسل طلب مال بقناة لا تسجل.');
        }

        $channel = (string) $this->input->post('payment_type');
        if (!preg_match('/^[a-z0-9_-]{2,24}$/i', $channel)) {
            $this->done('teacher/wallet', false, 'اختر قناة تحويل صحيحة.');
        }

        /* الملكية هنا بنيوية: المحفظة تقصد بمعرف صاحب الجلسة دائما، ولا
           يقرأ من النموذج المرسل معرف مستخدم ولا معرف محفظة.

           وسقف الرصيد يحكم به `Taqdar_wallet_model` من الدفتر داخل معاملة —
           وعمود `balance_available` قد يكون بائتا، فالحكم به هنا يرد سحبا
           مشروعا. فلا نقرأ العمود إلا حين لا يوجد النموذج أصلا. */
        $halalas = (int) round($amount * 100);
        $wallet  = $this->db->where('owner_user_id', $tid)->get('wallets')->row_array();

        if (!is_file(APPPATH . 'models/Taqdar_wallet_model.php')) {
            if (!$wallet) {
                $this->done('teacher/wallet', false, 'لا محفظة لحسابك بعد — لا رصيد يسحب.');
            }
            if ($halalas > (int) $wallet['balance_available']) {
                $this->done('teacher/wallet', false,
                    'المبلغ أكبر من رصيدك المتاح — والمعلق لا يسحب قبل أن يتحرر.');
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
            $this->result_message($r, 'سجل طلب السحب وينتظر المراجعة.'));
    }

    /**
     * POST teacher/wallet/cancel — إلغاء طلب سحب قائم.
     *
     * شاشة المحفظة تعد صراحة: «إلغاء الطلب يعيده إلى المتاح»، وكان الوعد
     * بلا زر. و`Taqdar_wallet_model::cancel_payout()` مكتوبة وتقيد
     * القيدين العكسيين كما ينبغي — الباب وحده كان ناقصا.
     *
     * والملكية تفحص هنا لأن `cancel_payout()` تقبل معرف الطلب وحده ولا
     * تسأل عن صاحبه: تمرير معرف من المتصفح إليها بلا فحص يلغي طلب معلم
     * آخر ويعيد ماله إلى دلو متاحه — أي يعبث بمال غيره من رابط.
     */
    public function wallet_cancel()
    {
        $user = $this->write_guard('teacher');
        $tid  = (int) $user['id'];

        $payout_id = (int) $this->input->post('payout_id');
        $row = $payout_id > 0
            ? $this->db->select('id, user_id, status')->where('id', $payout_id)
                       ->get('payout')->row_array()
            : null;

        if (!$row || (int) $row['user_id'] !== $tid) {
            $this->done('teacher/wallet', false, 'هذا الطلب ليس من طلباتك.');
        }
        if ((int) $row['status'] === 1) {
            $this->done('teacher/wallet', false, 'هذا الطلب حول بالفعل، فلا يلغى.');
        }
        if ((int) $row['status'] === 2) {
            $this->done('teacher/wallet', false, 'هذا الطلب ملغى من قبل.');
        }

        $this->load->model('taqdar_wallet_model');
        $ok = (bool) $this->taqdar_wallet_model->cancel_payout($payout_id);

        $this->trace('teacher.wallet.cancel', 'payout:' . $payout_id, array('ok' => $ok));

        $this->done('teacher/wallet', $ok, $ok
            ? 'ألغي الطلب، وعاد مبلغه إلى رصيدك المتاح.'
            : 'تعذر إلغاء الطلب — أعد المحاولة.');
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
            $this->done('teacher/questions', false, 'اختر ملف CSV قبل الاستيراد.');
        }
        if ((int) $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            $this->done('teacher/questions', false, 'تعذر رفع الملف — أعد المحاولة.');
        }
        if ((int) $_FILES['csv']['size'] > 2 * 1024 * 1024) {
            $this->done('teacher/questions', false, 'حجم الملف يتجاوز ٢ ميغابايت.');
        }
        $ext = strtolower((string) pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv', 'txt'), true)) {
            $this->done('teacher/questions', false, 'الملف لا بد أن يكون بصيغة CSV.');
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
            $this->result_message($r, 'استوردت الأسئلة.'));
    }

    /* ---- بوابة ولي الأمر: الكتابة ---------------------------------- */

    /** POST parent/messages/compose */
    public function parent_message_send()
    {
        $user = $this->write_guard('parent');
        $pid  = (int) $user['id'];

        $body = trim((string) $this->input->post('message'));
        if ($body === '') {
            $this->done('parent/messages', false, 'اكتب نص الرسالة.');
        }

        $child_id = (int) $this->input->post('child_id');
        if ($child_id > 0 && !$this->parent_owns_child($pid, $child_id)) {
            $this->done('parent/messages', false, 'هذا الطالب ليس من أبنائك المرتبطين.');
        }

        // المرسل إليه: إدارة أو معلم أو ابن مرتبط — لا أي حساب في المنصة
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
                    'لا ترسل الرسائل إلا إلى معلمي أبنائك أو الإدارة.');
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
            $this->result_message($r, 'أرسلت رسالتك.'));
    }

    /**
     * POST parent/children/link
     *
     * ثلاثة أفعال على مسار واحد لأنها فعل واحد في نظر ولي الأمر: علاقته
     * بابنه — يطلبها، أو يسحب طلبه، أو يفكها. و`tq_action` يفصلها.
     */
    public function parent_child_link()
    {
        $user = $this->write_guard('parent');
        $pid  = (int) $user['id'];
        $act  = (string) $this->input->post('tq_action');

        if ($act === 'link_cancel') {
            $link_id = (int) $this->input->post('link_id');
            // الطلب المسحوب لا بد أن يكون طلبه هو — لا رقم رابط عابر
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
                $this->result_message($r, 'سحب طلب الربط.'));
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
                $this->result_message($r, 'فك الربط.'));
        }

        // الرابط ينشأ باسم صاحب الجلسة دائما — أي parent_user_id مرسل يهمل
        $identifier = '';
        foreach (array('identifier', 'student_email', 'student_code', 'student_id') as $f) {
            $v = trim((string) $this->input->post($f));
            if ($v !== '' && $v !== '0') { $identifier = $v; break; }
        }
        if ($identifier === '') {
            $this->done('parent/settings', false, 'اكتب بريد حساب ابنك في المنصة أو رقم حسابه.');
        }

        $sid = ctype_digit($identifier)
            ? (int) $identifier
            : (int) $this->db->select('id')->where('email', $identifier)
                             ->get('users')->row('id');

        if ($sid > 0 && $sid === $pid) {
            $this->done('parent/settings', false, 'لا يكون المستخدم ولي أمر نفسه.');
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
            $this->result_message($r, 'أرسل طلب الربط — ويفعل بعد موافقة ابنك.'));
    }

    /**
     * POST parent/settings/save
     *
     * شاشة الإعدادات ثلاثة نماذج: الملف الشخصي، وكلمة المرور، والتنبيهات.
     * و`tq_action` يفصل بينها — فحفظ التنبيهات لا يمر بمسار كلمة المرور.
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
                $this->result_message($r, 'غيرت كلمة المرور.'));
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
                $this->result_message($r, 'حفظ ملفك.'));
        }

        // الافتراض: تفضيلات التنبيه. وحقول الهوية والصلاحية لا تقبل من النموذج
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
            $this->result_message($r, 'حفظت إعداداتك.'));
    }

    /* =====================================================================
       الشهادات
       ---------------------------------------------------------------------
       `tq_certificates.php` كان يشير إلى `taqdar/certificate/{id}` و
       `taqdar/verify/{id}` ولا وجود للدالتين — فكل زر في الشاشة يقود إلى
       404. والقاعدة الحاكمة للشهادة أنها تصدر على إتقان مقاس: محاولة
       امتحان ناجحة في `attempts`، لا نسبة مشاهدة.

       وحين لا يوجد بعد محتوى بأهداف وبوابات، الصواب حالة فارغة مفهومة
       لا شاشة خطأ: الرابط سليم، والشهادة هي التي لم تستحق بعد.
       ===================================================================== */

    /** الشهادة الواحدة — لصاحبها ولولي أمره وللإدارة. */
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
            'tq_counts'   => $this->counts($uid, tq_role($uid)),
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

    /** صفحة التحقق العامة — بلا تسجيل دخول، وبلا بيانات تعريف زائدة. */
    public function verify($code = '')
    {
        $cert = $this->certificate_row($code);

        if (is_file(APPPATH . 'views/frontend/' . $this->theme() . '/tq_verify.php')) {
            $this->show('tq_verify', 'التحقق من شهادة', array(
                'certificate' => $cert,
                'cert_code'   => (string) $code,
            ));
            return;
        }
        $this->certificate_fallback($cert, true);
    }

    /**
     * صف الشهادة من رمزها. الرمز `TQ-000012` أو `12` — كلاهما يفتح الشهادة
     * نفسها، فالرمز المطبوع هو ما ينسخه المتحقق لا المعرف العاري.
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
     * عرض احتياطي حين لا تكون شاشة الشهادة قد ركبت بعد.
     * صفحة قائمة بذاتها — لا 404، ولا شاشة بيضاء، ولا إطار ثيم ناقص.
     */
    private function certificate_fallback($cert, $public)
    {
        $brand = 'منصة تقدر';
        $title = $public ? 'التحقق من شهادة' : 'الشهادة';

        if ($cert) {
            $code = 'TQ-' . str_pad((string) (int) $cert['id'], 6, '0', STR_PAD_LEFT);
            $body = '<p class="ok">شهادة صحيحة وصادرة عن ' . html_escape($brand) . '.</p>'
                  . '<dl>'
                  . '<dt>حاملها</dt><dd>' . html_escape($cert['holder'] ?: '—') . '</dd>'
                  . '<dt>المحطة</dt><dd>' . html_escape($cert['milestone_title'] ?: ($cert['path_title'] ?: '—')) . '</dd>'
                  . '<dt>نسبة الإتقان</dt><dd>' . (int) $cert['score'] . '%</dd>'
                  . '<dt>تاريخ الإصدار</dt><dd>' . html_escape((string) $cert['submitted_at']) . '</dd>'
                  . '<dt>رمز التحقق</dt><dd>' . html_escape($code) . '</dd>'
                  . '</dl>';
        } else {
            $body = '<p class="empty">لا توجد شهادة بهذا الرمز.</p>'
                  . '<p>الشهادة تصدر على إتقان مقاس لا على مشاهدة: تنهي المحطة، '
                  . 'وتجتاز اختبارها، فتصلك شهادة بأهدافها ورمز تحقق.</p>';
        }

        $back = $public ? base_url() : base_url('student/certificates');
        $backLabel = $public ? 'الصفحة الرئيسية' : 'شهاداتي';

        $html = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
              . '<meta name="viewport" content="width=device-width, initial-scale=1">'
              . '<title>' . html_escape($title . ' — ' . $brand) . '</title>'
              . '<style>'
              /* `#132549` كان كحليا لا وجود له في هوية الموقع — لون العلامة `#023331`. */
              . 'body{margin:0;background:#f6f7fb;color:#023331;font:16px/1.7 system-ui,"Segoe UI",Tahoma,sans-serif}'
              . '.wrap{max-width:44rem;margin:4rem auto;padding:2.5rem;background:#fff;'
              . 'border:1px solid #e4e7f0;border-radius:1rem}'
              . 'h1{margin:0 0 1.5rem;font-size:1.5rem}'
              . 'dl{display:grid;grid-template-columns:auto 1fr;gap:.6rem 1.5rem;margin:1.5rem 0}'
              . 'dt{color:#6b7495;font-size:.9rem}dd{margin:0;font-weight:600}'
              . '.ok{color:#0f7b53;font-weight:700}.empty{color:#6b7495;font-weight:700}'
              . '.acts{margin-top:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap}'
              . 'a,button{font:inherit;display:inline-block;padding:.7rem 1.4rem;background:#023331;'
              . 'color:#fff;border:0;border-radius:.6rem;text-decoration:none;cursor:pointer}'
              . 'a.ghost{background:#fff;color:#023331;border:1px solid #e4e7f0}'
              . '@media print{body{background:#fff}.acts{display:none}}'
              . '</style></head><body><div class="wrap">'
              . '<h1>' . html_escape($title) . '</h1>' . $body
              . '<div class="acts">'
              /* «احفظ نسخة PDF» هو ما تفعله طباعة المتصفح فعلا، والورقة أعلاه
                 تخفي الأزرار عند الطباعة. وكانت الشاشة تعد بـ«تنزيل» ولا تنزل
                 شيئا — فهذا هو الباب الذي يخرج منه ملف. */
              . ($cert ? '<button type="button" onclick="window.print()">احفظ نسخة PDF</button>' : '')
              . '<a class="ghost" href="' . $back . '">' . $backLabel . '</a>'
              . '</div>'
              . '</div></body></html>';

        $this->output->set_content_type('text/html; charset=utf-8')->set_output($html);
    }

    /**
     * عارض الصفحات العامة الثابتة في التصميم الجديد.
     *
     * قائمة بيضاء لا وسيط حر: `$page_name` يدرج ملفا في الغلاف، فتمرير
     * ما يأتي من الرابط إليه بلا فحص يفتح الباب لقراءة ملفات لم تقصد.
     */
    public function site_page($name = '')
    {
        $allowed = array(
            'site_teachers' => 'المعلمون',
            'site_students' => 'الطلاب',
            'site_parents'  => 'أولياء الأمور',
        );
        if (!isset($allowed[$name])) show_404();

        $this->show($name, $allowed[$name]);
    }

    /* ---- الكتالوج الموحد ----------------------------------------- */

    /**
     * كل ما تقدمه المنصة في صفحة واحدة.
     *
     * كان لكل نوع بابه: البرامج في `/plans`، والكتب في `/books`،
     * والمسابقات في `/competitions`. ومن يبحث عن «رياضيات الصف الرابع»
     * لا يعرف أي باب يفتح — ولا مرشح في باب يشبه مرشح الباب الآخر.
     *
     * والحال هنا يعيش في **الرابط وحده**: المرشحات والبحث ورقم الصفحة
     * كلها معاملات `GET`، فالنتيجة تشارك برابطها وتحفظ في المفضلة
     * وترجع بزر «رجوع». والبحث الحي في `site.js` يكتب الرابط نفسه
     * بـ`pushState` ثم يستبدل الجزء — فلا حالة ثانية في الجافاسكربت
     * تفترق عما يفهمه الخادم.
     */
    public function catalog()
    {
        $this->load->model('taqdar_catalog_model', 'tq_cat');
        $f   = $this->tq_cat->filters_from($this->input->get());
        $res = $this->tq_cat->search($f);

        $this->show('site_catalog', 'المواد والبرامج التعليمية', array(
            'tq_f'   => $f,
            'tq_res' => $res,
        ));
    }

    /**
     * جزء النتائج وحده — لصندوق البحث الحي والمرشحات والترقيم.
     *
     * يعيد **الوسم مطبوعا من المولدات نفسها** التي تطبع الصفحة الكاملة
     * (`tqs_cat_card` · `tqs_cat_pager`)، فلا يفترق ما يراه من يبحث عما
     * يراه من يفتح الرابط مباشرة. وثلاثة أجزاء لا واحد: الشبكة
     * والمرشحات وسطر العد — لأن عدادات المرشحات تتغير مع كل بحث، ولوحة
     * مرشحات تقول «الكتب (٨)» فوق نتيجة فيها كتاب واحد مرشد كاذب.
     *
     * والمخرج JSON لا HTML خام: الأجزاء ثلاثة، ورد واحد يحمل الثلاثة
     * أوفر من ثلاثة طلبات، والرابط الجديد يرد معها فلا يحسب مرتين.
     */
    public function catalog_results()
    {
        $this->load->model('taqdar_catalog_model', 'tq_cat');
        $f   = $this->tq_cat->filters_from($this->input->get());
        $res = $this->tq_cat->search($f);

        $out = array(
            'grid'    => $this->load->view('frontend/taqdar/site/site_catalog_grid',
                                           array('tq_f' => $f, 'tq_res' => $res), true),
            'filters' => $this->load->view('frontend/taqdar/site/site_catalog_filters',
                                           array('tq_f' => $f, 'tq_res' => $res), true),
            'count'   => tqs_cat_count_line($res),
            'total'   => (int) $res['total'],
            'page'    => (int) $res['page'],
            'url'     => tqs_cat_query($f, array('page' => $res['page'])),
        );

        $this->output->set_content_type('application/json; charset=utf-8')
                     ->set_output(json_encode($out, JSON_UNESCAPED_UNICODE));
    }

    /**
     * صفحة الكتاب.
     *
     * الكتاب كان بطاقة وزر تحميل لا أكثر: لا وصف، ولا مؤلف، ولا رابط
     * يشارك. وهو محتوى مجاني — أي أنه أول ما يصل إليه الزائر من محرك
     * البحث، فصفحة له تعني بابا يدخل منه إلى بقية المنصة.
     */
    public function book_page($slug = '')
    {
        $this->load->model('taqdar_catalog_model', 'tq_cat');
        $b = $this->tq_cat->book_by_slug($slug);
        if (!$b) show_404();

        $this->show('site_book', $b['title'], array(
            'tq_book' => $b,
            'tq_more' => $this->tq_cat->books_like($b, 4),
        ));
    }

    /**
     * صفحة المسابقة الواحدة.
     *
     * والتسجيل يبقى في `competition_join` نفسها: النموذج هنا يرسل إلى
     * المسار ذاته الذي ترسل إليه صفحة القائمة، فلا مساران يكتبان في
     * `competition_entries` بشرطين مختلفين.
     */
    public function competition_page($slug = '')
    {
        $this->load->model('taqdar_catalog_model', 'tq_cat');
        $c = $this->tq_cat->competition_by_slug($slug);
        if (!$c) show_404();

        $mine = false;
        $uid  = (int) $this->session->userdata('user_id');
        if ($uid > 0) {
            $mine = $this->db->where('competition_id', (int) $c['id'])->where('user_id', $uid)
                             ->count_all_results('competition_entries') > 0;
        }

        $this->show('site_competition', $c['title'], array(
            'tq_comp' => $c,
            'tq_mine' => $mine,
        ));
    }

    /**
     * صفحة تفصيل المسار — وبها يكتمل طريق الشراء.
     *
     * الزر في الكتالوج كان يقود إلى لا شيء، فكان الزائر يرى ما يعجبه
     * ولا يجد بابا يدخل منه.
     */
    public function path_page($slug = '')
    {
        $this->load->model('taqdar_site_model', 'tq_m');
        $path = $this->tq_m->path_by_slug($slug);
        if (!$path) show_404();

        /* المنهج والأرقام والباقات كلها من النموذج لا من العرض: كان القالب
           يستعلم عن دروسه بنفسه بـ`get_instance()` — ثلاثون عنوانا مسطحا
           بلا وحدات ولا مدد. والاستعلام في العرض يعني أن أي شاشة ثانية
           تريد الرقم نفسه تكتبه من جديد، فيفترق الرقمان. */
        $detail = $this->tq_m->path_detail($path);

        /* ما يجاور هذا البرنامج في قسمه — برامج وكتبا ومسابقات. والمادة
           نفسها أولا: من يقرأ «رياضيات السادس» يريد ما يقربه منها لا ما
           يشترك معها في المرحلة وحدها. */
        $this->load->model('taqdar_catalog_model', 'tq_cat');
        $near = $this->tq_cat->near_path($path, 6);

        $this->show('site_path', $path['title'], array(
            'tq_path'   => $path,
            'tq_detail' => $detail,
            'tq_near'   => $near,
        ));
    }


    /**
     * ملف المعلم العام.
     *
     * المعروض علنا اختيار صريح (`is_public`) لا أثر جانبي لكون
     * المستخدم معلما — فمن يدرس لا يلزم أن ينشر اسمه وصورته.
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
     * للمسجلين وحدهم: مسابقة يدخلها المجهولون لا تقاس نتائجها ولا
     * تنسب شهاداتها. والمفتاح الفريد يمنع التسجيل مرتين.
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
                    $this->session->set_flashdata('flash_message', 'أنت مسجل في هذه المسابقة سلفا.');
                } else {
                    $this->db->insert('competition_entries', array(
                        'competition_id' => $cid, 'user_id' => $uid,
                        'created_at' => date('Y-m-d H:i:s')));
                    $this->session->set_flashdata('flash_message',
                        'سجلت في المسابقة. سنذكرك قبل موعدها.');
                }
            }
        }
        redirect(site_url('competitions'), 'location', 302);
    }

    /* ---- الباقة: صفحتها وشراؤها ومحتواها ------------------------- */

    /**
     * صفحة الباقة — ما يشترى، مفصلا، قبل الدفع.
     *
     * كان الكتالوج ينتهي عند بطاقة ومرساة: يقرأ الزائر اسما وسعرا وست
     * كلمات تسويقية ثم يطلب منه ثلاثمئة وتسعون ريالا. وهذه الصفحة
     * تعرض المنهج نفسه — مادة مادة ووحدة وحدة — قبل أن يطلب شيء.
     */
    public function plan_page($code = '')
    {
        $this->load->model('taqdar_site_model', 'tq_m');
        $b = $this->tq_m->bundle_by_code($code);
        if (!$b) show_404();

        /* حالة الزائر تقرر هنا لا في العرض: العرض يعرض، والقرار قرار. */
        $uid = (int) $this->session->userdata('user_id');
        $own = false;
        if ($uid > 0) {
            $this->load->model('taqdar_billing_model');
            $cur = $this->taqdar_billing_model->active_subscription($uid);
            $own = ($cur && (int) $cur['plan_id'] === (int) $b['plan_id']
                          && $cur['status'] === 'active');
        }

        /* أخوات الباقة — لتبديلها من عنوانها نفسه.
           كان الخروج من باقة إلى أختها يمر بالكتالوج ثم بالعودة: ثلاث
           صفحات لمقارنة رقمين. والقائمة تحت العنوان تجعلها نقرة، وهي
           الموضع الذي تسأل فيه العين «وما البقية؟». */
        $siblings = array();
        $this->load->model('taqdar_billing_model');
        foreach ((array) $this->taqdar_billing_model->plans(true) as $p) {
            if ((string) $p['scope'] !== 'grade') continue;   // الباقات المباعة وحدها
            $siblings[] = array(
                'code'    => (string) $p['code'],
                'name'    => (string) $p['name_ar'],
                'tier'    => tqs_bundle_tier($p['name_ar']),
                'stage'   => (string) $p['stage'],
                'price'   => (int) $p['price'],
                'current' => ((string) $p['code'] === $b['code']),
            );
        }

        /* والبطاقات تحت الصفحة غير المبدل في عنوانها: المبدل قائمة أسماء
           وأسعار لمن قرر أن يوازن، والبطاقات عرض لمن لم يقرر بعد — بصورتها
           وسطر وصفها ومدتها وشارة «الأكثر طلبا». وهي من مولد بطاقات
           الكتالوج نفسه، فما يراه الزائر هنا هو ما رآه هناك حرفا. */
        $this->load->model('taqdar_catalog_model', 'tq_cat');
        $related = $this->tq_cat->plans_like($b['code'], 3);

        $this->show('site_plan', $b['name'], array(
            'tq_bundle'   => $b,
            'tq_owns'     => $own,
            'tq_siblings' => $siblings,
            'tq_related'  => $related,
        ));
    }

    /**
     * تأكيد الشراء — شاشة واحدة بلا سلة.
     *
     * السلة تجمع أصنافا، والطالب يشتري باقة واحدة تغطي سنته. فلا
     * شيء يجمع، وكل خطوة زائدة بين الرغبة والدفع تسقط مشترين.
     *
     * والدخول شرط هنا لا في الزر السابق: من يعود بعد التسجيل يجد الشاشة
     * نفسها لا الكتالوج من أوله.
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

        /* المعلم ولي الأمر لا يشتريان باقة طالب: الشراء يفتح محتوى
           يقاس تقدمه لصاحب الحساب، ولا معنى له لغير الطالب. */
        if (function_exists('tq_role') && tq_role() !== 'student') {
            $this->session->set_flashdata('error_message',
                'الاشتراك في الباقات لحسابات الطلاب. سجل الدخول بحساب طالب.');
            redirect(site_url('plans'), 'location', 302);
            return;
        }

        /* الاختبار التشخيصي قبل الدفع لا بعده — وهو كل الغرض منه: أن
           يعرف الطالب موضعه قبل أن يختار، لا أن يكتشفه بعد أن دفع.
           والمنع الحقيقي في `Taqdar_billing_model::subscribe()`؛ وهذا
           هنا لئلا يقرأ شاشة تأكيد كاملة ثم يرد عند الزر. */
        $this->load->model('taqdar_diag_model');
        if ($this->taqdar_diag_model->gate($uid)) {
            $this->session->set_flashdata('error_message',
                'قبل الاشتراك: اختبار قصير يحدد موضعك فنرشح لك الباقة المناسبة. لا رسوب فيه.');
            redirect(base_url('student/placement'), 'location', 302);
            return;
        }

        $this->load->model('taqdar_billing_model');
        $cur = $this->taqdar_billing_model->active_subscription($uid);

        /* جهوزية البطاقة تقرأ في المتحكم لا في القالب: القالب يقرر ما
           يعرض، ولا يفتح نموذجا ولا يحمل بوابة ليعرف. وحين تكون كاذبة
           تعرض الشاشة ما كانت تعرضه قبل تاب حرفا بحرف. */
        $this->load->model('taqdar_tap_model');

        $this->show('site_checkout', 'تأكيد الاشتراك — ' . $b['name'], array(
            'tq_bundle'  => $b,
            'tq_current' => $cur,
            'user_id'    => $uid,
            'tq_card'    => $this->taqdar_tap_model->ready(),
            'tq_card_test' => $this->taqdar_tap_model->is_test_ready(),
        ));
    }

    /**
     * محتوى الباقة للمشترك — ما دفع ثمنه، مرتبا كما يدرس.
     *
     * صفحة الاشتراك تقول «نشط حتى كذا» ولا تقول ماذا فتح. وهذه تقوله:
     * المواد بوحداتها ودروسها وتقدم الطالب في كل منها.
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
            /* `active_subscription()` تستثني المعلق عمدا — فهو لا يمنح
               شيئا. لكن صفحة تقول لمن دفع للتو «لا اشتراك بعد» وتدله
               على الباقات تدعوه إلى الشراء مرتين. فيقرأ آخر اشتراك
               مهما كانت حاله، ويقال له أين هو منه — كما تفعل
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

        /* التقدم من المستودع نفسه الذي يقرأ منه المشغل، فلا يفترق رقم
           هنا عن رقم هناك. */
        if ($b) {
            foreach ($b['subjects'] as $s) {
                $cid = (int) $s['course_id'];
                if ($cid <= 0 || isset($prog[$cid])) continue;
                $p = $this->taqdar_repo_model->path_progress($uid, $cid);
                $prog[$cid] = (int) $p['percent'];
            }
        }

        /* `tq_subscription` لا `tq_sub` — الأخير هو سطر ما تحت العنوان في
           غلاف البوابة، فتسميته بالاشتراك تجعل الغلاف يطبع مصفوفة. */
        $this->show('tq_bundle', 'محتوى باقتي', array(
            'tq_counts'        => $this->counts($uid),
            'user_id'          => $uid,
            'tq_subscription'  => $sub,
            'tq_bundle'        => $b,
            'tq_progress'      => $prog,
        ));
    }

    /**
     * `pay/<اسم البوابة>` — بادئة قديمة لا نقطة عودة.
     *
     * نقطة عودة تاب هي `payment/tap/return` في
     * [Taqdar_pay.php](Taqdar_pay.php) — والبادئة `payment/` مقصودة: هي
     * المستثناة من حماية CSRF، فالويبهوك يصل منها وحدها.
     *
     * وتبقى هذه الدالة لتسجل من يطرق الباب القديم: بوابة تنسخ عنوان
     * عودتها من وثيقة قديمة تظهر هنا في السجل بدل أن تصمت.
     */
    public function gateway_callback($gateway = '')
    {
        log_message('error', 'TQ-GATEWAY: نداء على بادئة pay/ القديمة — ' . $gateway
            . ' — والنقطة العاملة payment/tap/return');
        show_404();
    }

}
