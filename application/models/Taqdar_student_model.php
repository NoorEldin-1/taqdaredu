<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * بوابة الطالب — قواعد القراءة التي كانت تعيش في القوالب.
 *
 * ثمان شاشات من بوابة الطالب — الإشعارات والرسائل والشهادات والمهام
 * والتقويم والمكتبة والتقارير والبحث — كانت تكتب استعلاماتها **داخل
 * ملف العرض**: `$this->db->select(...)` في أول القالب، ثم الوسم تحته.
 * وذلك يعمل ما دام القارئ متصفحا؛ فلما جاءت `Api_v1` تسأل الأسئلة نفسها
 * لم تجد ما تناديه إلا قالبا يطبع HTML.
 *
 * والمخرج الظاهر أن تكتب الواجهة استعلاماتها من جديد — ويفسده أن
 * **النسختين تفترقان عند أول تعديل**: يضاف نوع إشعار في القالب ولا
 * يضاف هناك، فيقرأ الطالب في التطبيق «تنبيهات أخرى» وفي الموقع «رسوب في
 * اختبار محطة» عن الحدث الواحد. وهو المبدأ نفسه الذي أخرج
 * `Taqdar_curriculum_model` من شاشتي المنهج و`Taqdar_quiz_model` من
 * محرري الأسئلة الثلاثة: **القواعد في النموذج، والشاشات تعرض ولا تحكم.**
 *
 * وما ليس هنا مقصود: كل ما له نموذج بالفعل ينادى من موضعه —
 * `Taqdar_favourites_model` للمفضلة، و`Taqdar_sessions_model` للحصص،
 * و`Taqdar_billing_model` للشراء، و`Taqdar_learn_model` للتهيئة،
 * و`Taqdar_diag_model` للتشخيص، و`Taqdar_repo_model` للتقدم والقفل.
 * ونسخة ثانية من أي منها هنا هي العطل الذي كتب هذا الملف لتفاديه.
 */
class Taqdar_student_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /* ================================================================
       الإشعارات
       ================================================================ */

    /**
     * تصنيف نوع الإشعار: تسمية وأيقونة وعائلة لون.
     *
     * والصدارة لأحداث تقدر الخمسة التي يكتبها `Taqdar_events_model`،
     * وعناوينها هي عناوينها في شاشة ولي الأمر حرفا بحرف: الطالب ووليه
     * يقرآن الحدث الواحد باسم واحد، وإلا صار الحديث بينهما عن حدثين.
     *
     * وكل منها عائلة مستقلة لا مندرجة تحت «تنبيهات أخرى»: دمجها يخفي
     * عن الطالب أن ما وصله رسوب لا إشعار عابر.
     */
    public function notification_kinds()
    {
        return array(
            // أحداث تقدر — تكتب من Taqdar_events_model وحده
            'exam_result'      => array(t('نتيجة امتحان'),        'check-badge', 'mint'),
            'station_failed'   => array(t('رسوب في اختبار محطة'), 'target',      'rose'),
            'inactivity_3days' => array(t('انقطاع عن الدراسة'),   'clock',       'peach'),
            'session_request'  => array(t('طلب حصة خاصة'),        'video',       'lilac'),
            'certificate'      => array(t('شهادة جديدة'),         'award',       'sky'),
            'weekly_report'    => array(t('التقرير الأسبوعي'),    'clipboard',   'sand'),
            'placement_result' => array(t('نتيجة تحديد المستوى'), 'target',      'sky'),

            /* أنواع تقدر التي يكتبها `push_notification` من كل بابها —
               وكانت ساقطة كلها من هذا التصنيف: ستة عشر نوعا يقع منها في
               القاعدة، ويقرؤها الطالب «تنبيهات أخرى» بأيقونة جرس واحدة.
               فيصير التبويب الجانبي كومة واحدة لا تصنيفا، ولا يفرق فيها
               إشعار فاتورة عن رسالة إدارية. */
            'subscription' => array(t('الاشتراك والباقات'), 'wallet',    'mint'),
            'invoice'      => array(t('الفواتير'),          'file',      'peach'),
            'payment'      => array(t('المدفوعات'),         'wallet',    'peach'),
            'wallet'       => array(t('المحفظة'),           'wallet',    'mint'),
            'payout'       => array(t('السحب من المحفظة'),  'wallet',    'mint'),
            'session'      => array(t('الحصص الخاصة'),      'video',     'lilac'),
            'task'         => array(t('المهام والواجبات'),  'clipboard', 'amber'),
            'review'       => array(t('المراجعة والاعتماد'),'check-badge','sky'),
            'content'      => array(t('محتوى جديد'),        'book',      'sky'),
            'admin'        => array(t('رسائل الإدارة'),     'users',     'sand'),
            'contact'      => array(t('التواصل'),           'chat',      'sand'),
            'parent_link_request' => array(t('ربط ولي الأمر'), 'users', 'lilac'),
            'parent_link_granted' => array(t('ربط ولي الأمر'), 'users', 'lilac'),
            'parent_link_revoked' => array(t('ربط ولي الأمر'), 'users', 'lilac'),
            'teacher_approved'    => array(t('حساب المعلم'),   'users', 'mint'),
            'teacher_rejected'    => array(t('حساب المعلم'),   'users', 'rose'),

            // أنواع Academy الأصلية
            'course_purchase'                => array(t('الدروس والكورسات'),  'book',      'sky'),
            'bundle_purchase'                => array(t('الدروس والكورسات'),  'book',      'sky'),
            'course_gift'                    => array(t('الدروس والكورسات'),  'book',      'sky'),
            'noticeboard'                    => array(t('لوحة المادة'),       'clipboard', 'lilac'),
            'instructor_followups'           => array(t('متابعة المعلم'),     'chat',      'mint'),
            'course_completion_mail'         => array(t('الإنجاز والشهادات'), 'award',     'mint'),
            'certificate_eligibility'        => array(t('الإنجاز والشهادات'), 'award',     'mint'),
            'offline_payment_suspended_mail' => array(t('المدفوعات'),         'wallet',    'peach'),
            'signup'                         => array(t('الحساب والأمان'),    'users',     'sand'),
            'email_verification'             => array(t('الحساب والأمان'),    'lock',      'sand'),
            'forget_password_mail'           => array(t('الحساب والأمان'),    'lock',      'sand'),
            'new_device_login_confirmation'  => array(t('الحساب والأمان'),    'lock',      'sand'),
        );
    }

    /** تصنيف نوع واحد — وما لا يعرف يقع على «تنبيهات أخرى» لا على فراغ. */
    public function notification_kind($type)
    {
        $map = $this->notification_kinds();
        return isset($map[$type]) ? $map[$type] : array(t('تنبيهات أخرى'), 'bell', 'rose');
    }

    /**
     * إشعارات الطالب مصنفة ومعدودة.
     *
     * والعدادات تحسب على **الكل** لا على المعروض: تبويب «غير مقروءة»
     * الذي يعد ما بداخله وحده يقول صفرا حين تفتحه وقد قرأت آخر إشعار،
     * فيبدو التبويب معطلا.
     */
    public function notifications($uid, $state = 'all', $limit = 120)
    {
        $uid = (int) $uid;
        if ($uid <= 0) {
            return array('items' => array(),
                         'counts' => array('all' => 0, 'unread' => 0, 'read' => 0),
                         'by_kind' => array());
        }

        $state = in_array($state, array('unread', 'read'), true) ? $state : 'all';

        $all = $this->db->where('to_user', $uid)
                        ->order_by('id', 'DESC')->limit((int) $limit)
                        ->get('notifications')->result_array();

        $counts  = array('all' => count($all), 'unread' => 0, 'read' => 0);
        $by_kind = array();

        foreach ($all as $n) {
            ((int) $n['status'] === 0) ? $counts['unread']++ : $counts['read']++;
            list($label, $icon, $tone) = $this->notification_kind($n['type']);
            if (!isset($by_kind[$label])) {
                $by_kind[$label] = array('count' => 0, 'icon' => $icon, 'tone' => $tone);
            }
            $by_kind[$label]['count']++;
        }

        $items = array_values(array_filter($all, function ($n) use ($state) {
            if ($state === 'unread') return (int) $n['status'] === 0;
            if ($state === 'read')   return (int) $n['status'] === 1;
            return true;
        }));

        return array('items' => $items, 'counts' => $counts, 'by_kind' => $by_kind);
    }

    /**
     * «هذا الإشعار وحده» مقروءا.
     *
     * و`to_user` في الشرط لا في الثقة بالطلب: المعرف يأتي من المتصفح،
     * وبدونه يمسح من خمن رقما نقطة «غير مقروء» عن غيره فيخفي عنهم خبرا
     * لم يفتحوه.
     */
    public function mark_notification_read($uid, $id)
    {
        $uid = (int) $uid; $id = (int) $id;
        if ($uid <= 0 || $id <= 0) return 0;
        $this->db->where('to_user', $uid)->where('id', $id)->where('status', 0)
                 ->update('notifications', array('status' => 1, 'updated_at' => (string) time()));
        return (int) $this->db->affected_rows();
    }

    /** تحديد الكل كمقروء — ويرد عدد ما تغير فعلا لا «تم». */
    public function mark_all_notifications_read($uid)
    {
        $uid = (int) $uid;
        if ($uid <= 0) return 0;
        $this->db->where('to_user', $uid)->where('status', 0)
                 ->update('notifications', array('status' => 1, 'updated_at' => (string) time()));
        return (int) $this->db->affected_rows();
    }

    /* ================================================================
       الرسائل
       ================================================================ */

    /**
     * محادثات الطالب: الطرف الآخر وآخر رسالة وعدد ما لم يقرأ.
     *
     * `message_thread.sender`/`receiver` **نصان** لا رقمان في مخطط
     * Academy، فالمقارنة بـ`(string) $uid` — ورقم خام هنا يرد قائمة
     * فارغة على بعض التركيبات بلا خطأ.
     */
    public function threads($uid)
    {
        $uid = (int) $uid;
        if ($uid <= 0) return array();

        $raw = $this->db
            ->group_start()->where('sender', (string) $uid)->or_where('receiver', (string) $uid)->group_end()
            ->order_by('last_message_timestamp', 'DESC')
            ->get('message_thread')->result_array();

        $out = array();
        foreach ($raw as $t) {
            $code  = $t['message_thread_code'];
            $other = ((int) $t['sender'] === $uid) ? (int) $t['receiver'] : (int) $t['sender'];

            $last = $this->db->where('message_thread_code', $code)
                             ->order_by('timestamp', 'DESC')->limit(1)
                             ->get('message')->row_array();

            $unread = (int) $this->db->where('message_thread_code', $code)
                                     ->where('receiver', $uid)->where('read_status', 0)
                                     ->count_all_results('message');

            $person = $this->db->select('id, first_name, last_name, image, is_instructor, role_id')
                               ->where('id', $other)->get('users')->row_array();

            $out[] = array(
                'code'   => $code,
                'other'  => $other,
                'person' => $person,
                'last'   => $last,
                'unread' => $unread,
                'ts'     => (int) (isset($last['timestamp']) ? $last['timestamp'] : $t['last_message_timestamp']),
            );
        }
        return $out;
    }

    /** أيملك هذا الطالب هذه المحادثة؟ — شرط كل قراءة وكل كتابة عليها. */
    public function owns_thread($uid, $code)
    {
        $uid  = (int) $uid;
        $code = (string) $code;
        if ($uid <= 0 || $code === '') return false;

        return (int) $this->db->where('message_thread_code', $code)
            ->group_start()->where('sender', (string) $uid)->or_where('receiver', (string) $uid)->group_end()
            ->count_all_results('message_thread') > 0;
    }

    /** رسائل محادثة بعينها، أقدمها أولا كما تقرأ. */
    public function messages($uid, $code, $limit = 200)
    {
        if (!$this->owns_thread($uid, $code)) return array();
        return $this->db->where('message_thread_code', (string) $code)
                        ->order_by('timestamp', 'ASC')->limit((int) $limit)
                        ->get('message')->result_array();
    }

    /** فتح المحادثة يجعلها مقروءة — كما يتوقع من فتحها فعلا. */
    public function read_thread($uid, $code)
    {
        if (!$this->owns_thread($uid, $code)) return false;
        $this->db->where('message_thread_code', (string) $code)
                 ->where('receiver', (int) $uid)->where('read_status', 0)
                 ->update('message', array('read_status' => 1));
        return true;
    }

    /**
     * من يجوز للطالب مراسلته: معلمو كورساته المسجلة، وحساب الإدارة.
     *
     * والقيد شرط في الخادم لا وعد في العرض: `send_new_private_message()`
     * تقرأ `receiver` من الطلب ولا تفحصه، فبدون هذه القائمة يبدل من شاء
     * معرفا في النموذج فيراسل أي حساب.
     *
     * و`course.user_id` **قائمة معرفات مفصولة بفواصل** لا معرفا واحدا،
     * فـ`(int)` عليها تقرأ «148,289» على أنها 148: يسقط كل معلم ثان في
     * كورس مشترك من القائمة — فيراسل الأول ويقال له عن الثاني إنه ليس
     * من معلميه، وهو معلمه فعلا.
     */
    public function messageable($uid)
    {
        $uid = (int) $uid;
        if ($uid <= 0) return array();

        $ids = tq_course_owner_ids($this->db->select('c.user_id')
            ->from('enrol e')->join('course c', 'c.id = e.course_id', 'inner')
            ->where('e.user_id', $uid)->get()->result_array());

        foreach ($this->db->select('id')->where('role_id', 1)->limit(1)
                          ->get('users')->result_array() as $a) {
            $ids[] = (int) $a['id'];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return array();

        return $this->db->select('id, first_name, last_name, image, is_instructor, role_id')
                        ->where_in('id', $ids)->get('users')->result_array();
    }

    /** أيجوز لهذا الطالب أن يراسل هذا الحساب؟ */
    public function may_message($uid, $to)
    {
        $to = (int) $to;
        foreach ($this->messageable($uid) as $p) {
            if ((int) $p['id'] === $to) return true;
        }
        return false;
    }

    /**
     * إرسال رسالة جديدة — **والمرسل معامل لا حالة جلسة.**
     *
     * `Crud_model::send_new_private_message()` تقرأ المرسل من
     * `$this->session->userdata('user_id')` والنص من `$this->input->post()`
     * — أي أنها تعمل من متصفح وحده. و`Api_v1` بلا جلسة بحكم أول قاعدة
     * فيها («لا جلسة ولا كعكة»)، فنداؤها من هناك يكتب صفا **مرسله صفر**
     * أو يسقط بخطأ لا يفسر نفسه.
     *
     * فصار المرسل والنص معاملين، والسلوك هو سلوكها حرفا: الخيط يبحث عنه
     * بالطرفين في الاتجاهين، وينشأ إن لم يكن، و`last_message_timestamp`
     * يكتب معه — وبلاه يظهر كل خيط جديد في ذيل أي قائمة مرتبة زمنيا،
     * فتدفن الرسالة الأحدث تحت الأقدم.
     *
     * و`Crud_model` لا تمس: هي مشتركة مع مسارات LMS الأصلية.
     *
     * @return string رمز الخيط
     */
    public function send_message($uid, $to, $body)
    {
        $uid  = (int) $uid;
        $to   = (int) $to;
        $now  = time();

        $a = $this->db->get_where('message_thread',
                array('sender' => $uid, 'receiver' => $to))->row_array();
        $b = $a ? null : $this->db->get_where('message_thread',
                array('sender' => $to, 'receiver' => $uid))->row_array();

        if ($a)      { $code = $a['message_thread_code']; }
        elseif ($b)  { $code = $b['message_thread_code']; }
        else {
            $code = random(30);
            $this->db->insert('message_thread', array(
                'message_thread_code'    => $code,
                'sender'                 => $uid,
                'receiver'               => $to,
                'last_message_timestamp' => $now,
            ));
        }

        $this->db->insert('message', array(
            'message_thread_code' => $code,
            'message'             => (string) $body,
            'sender'              => $uid,
            'receiver'            => $to,
            'timestamp'           => $now,
            'read_status'         => 0,
        ));

        /* الخيط القائم يرفع طابعه كذلك، وإلا بقي في ذيل القائمة بعد أن
           وصلت فيه رسالة — وهو العطل الذي كتب الطابع لأجله أصلا. */
        $this->db->where('message_thread_code', $code)
                 ->update('message_thread', array('last_message_timestamp' => $now));

        return $code;
    }

    /**
     * رد في محادثة قائمة — والملكية شرط.
     *
     * والطرف الآخر يستنتج من الخيط لا يرسل: مستقبل يأتي من الطلب يجعل
     * من خمن رمزا يوجه رسالته إلى من شاء تحت غطاء محادثة غيره.
     */
    public function reply_message($uid, $code, $body)
    {
        if (!$this->owns_thread($uid, $code)) return false;

        $uid = (int) $uid;
        $now = time();

        $t = $this->db->get_where('message_thread',
                array('message_thread_code' => (string) $code))->row_array();
        if (!$t) return false;

        $to = ((int) $t['sender'] === $uid) ? (int) $t['receiver'] : (int) $t['sender'];

        $this->db->insert('message', array(
            'message_thread_code' => (string) $code,
            'message'             => (string) $body,
            'sender'              => $uid,
            'receiver'            => $to,
            'timestamp'           => $now,
            'read_status'         => 0,
        ));

        $this->db->where('message_thread_code', (string) $code)
                 ->update('message_thread', array('last_message_timestamp' => $now));

        return true;
    }

    /**
     * حذف محادثة — بتحقق ملكية على الخادم.
     *
     * زر الخطر يجب أن يفعل ما يقوله، والصلاحية تفرض هنا لا في الواجهة.
     */
    public function delete_thread($uid, $code)
    {
        if (!$this->owns_thread($uid, $code)) return false;
        $this->db->where('message_thread_code', (string) $code)->delete('message');
        $this->db->where('message_thread_code', (string) $code)->delete('message_thread');
        return true;
    }

    /* ================================================================
       الشهادات
       ================================================================ */

    /**
     * الشهادات — على **إتقان مقاس** لا على مشاهدة.
     *
     * ولذلك لا تبنى من نسبة المشاهدة في `watch_histories`: الشهادة
     * تنتظر اجتياز امتحان المحطة (`assessments.type = 'exam'` و
     * `attempts.passed = 1`). ومن لم يجتز بعد يقرأ الحالة الفارغة
     * الصحيحة، لا شهادة مبنية على وقت تشغيل.
     */
    public function certificates($uid)
    {
        $uid = (int) $uid;
        if ($uid <= 0 || !$this->db->table_exists('attempts')) return array();

        try {
            return $this->db->query(
                "SELECT a.id, a.score, a.submitted_at, p.title AS path_title, m.title AS milestone_title
                   FROM attempts a
                   JOIN assessments s ON s.id = a.assessment_id AND s.type = 'exam'
              LEFT JOIN milestones m ON m.id = s.milestone_id
              LEFT JOIN paths p ON p.id = COALESCE(s.path_id, m.path_id)
                  WHERE a.student_id = ? AND a.passed = 1
               ORDER BY a.submitted_at DESC", array($uid)
            )->result_array();
        } catch (Throwable $e) {
            /* TQ-BUILDER-DIRTY — استثناء وسط سلسلة يترك حالة البناء كما
               هي، فيرث كل استعلام تال في الطلب نفسه ضمومها. */
            $this->db->reset_query();
            return array();
        }
    }

    /** رمز التحقق المطبوع — صيغة واحدة في الشاشة وفي التطبيق وفي صفحة التحقق. */
    public function certificate_code($id)
    {
        return 'TQ-' . str_pad((string) (int) $id, 6, '0', STR_PAD_LEFT);
    }

    /* ================================================================
       المهام — الواجبات
       ================================================================ */

    /**
     * واجبات الطالب في ثلاث مجموعات بحالات القاعدة نفسها.
     *
     * ولا حالة «متأخر»: لا موعد استحقاق في المخطط يقاس عليه التأخر،
     * وحالة تخترع موعدا تجعل الشاشة تقول للطالب إنه تأخر عن شيء لم
     * يحدد له وقت.
     *
     * وما يعرض من الدرجة يقرره `Taqdar_marking_model` لا هذا الملف:
     * الواجب عمل يقرؤه معلم، ودرجته لا تعرض قبل اعتماده — وكان الطالب
     * يقرأ رقما يحسبه درجته النهائية ثم يأتي الاعتماد فيغيره.
     */
    public function tasks($uid)
    {
        $uid = (int) $uid;

        $groups = array(
            'todo'     => array('label' => t('لم تبدأ'),     'dot' => 'idle', 'badge' => 'idle',     'items' => array()),
            'progress' => array('label' => t('قيد التنفيذ'), 'dot' => 'due',  'badge' => 'progress', 'items' => array()),
            'done'     => array('label' => t('مكتملة'),      'dot' => 'done', 'badge' => 'mastered', 'items' => array()),
        );
        if ($uid <= 0) return $groups;

        $hw = $this->db
            ->select('a.id AS assessment_id, a.time_limit_sec, a.pass_mark,'
                   . ' l.id AS lesson_id, l.title, l.course_id,'
                   . ' c.title AS course_title, c.level, c.category_id')
            ->from('assessments a')
            ->join('lesson l', 'l.id = a.lesson_id', 'inner')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->join('enrol e', 'e.course_id = c.id', 'inner')
            ->where('e.user_id', $uid)
            ->where('a.type', 'homework')
            ->order_by('c.id', 'ASC')->order_by('l.order', 'ASC')
            ->get()->result_array();

        if (!$hw) return $groups;

        $a_ids = array_map(function ($r) { return (int) $r['assessment_id']; }, $hw);
        $l_ids = array_map(function ($r) { return (int) $r['lesson_id']; }, $hw);

        $this->load->model('taqdar_marking_model');
        $this->taqdar_marking_model->ensure_schema();

        /* آخر محاولة لكل تقييم — الترتيب تصاعدي فالأحدث يغلب. */
        $att = array();
        foreach ($this->db->select('assessment_id, score, passed, started_at, submitted_at,'
                                 . ' teacher_score, teacher_note, approved_at')
                          ->from('attempts')->where('student_id', $uid)
                          ->where_in('assessment_id', $a_ids)
                          ->order_by('attempt_no', 'ASC')->get()->result_array() as $r) {
            $att[(int) $r['assessment_id']] = $r;
        }

        /* عدد بنود الواجب — الأسئلة معلقة بمعرف الدرس في `question.quiz_id`. */
        $items_n = array();
        foreach ($this->db->select('quiz_id, COUNT(*) AS n')->from('question')
                          ->where_in('quiz_id', $l_ids)->group_by('quiz_id')
                          ->get()->result_array() as $r) {
            $items_n[(int) $r['quiz_id']] = (int) $r['n'];
        }

        $cat_names = array();
        $cat_ids = array_values(array_unique(array_filter(array_map(function ($r) {
            return (int) $r['category_id'];
        }, $hw))));
        if ($cat_ids) {
            foreach ($this->db->select('id, name')->from('category')
                              ->where_in('id', $cat_ids)->get()->result_array() as $r) {
                $cat_names[(int) $r['id']] = $r['name'];
            }
        }

        foreach ($hw as $r) {
            $lid = (int) $r['lesson_id'];
            $a   = isset($att[(int) $r['assessment_id']]) ? $att[(int) $r['assessment_id']] : null;
            $max = isset($items_n[$lid]) ? $items_n[$lid] : 0;

            $submitted = ($a && !empty($a['submitted_at'])) ? (int) strtotime($a['submitted_at']) : 0;
            $started   = ($a && !empty($a['started_at']))   ? (int) strtotime($a['started_at'])   : 0;
            $key = $submitted ? 'done' : ($started ? 'progress' : 'todo');

            $item = array(
                'id'        => $lid,
                'course_id' => (int) $r['course_id'],
                'title'     => (string) $r['title'],
                'subject'   => isset($cat_names[(int) $r['category_id']])
                             ? $cat_names[(int) $r['category_id']] : (string) $r['course_title'],
                'stage'     => (string) $r['level'],
                'at'        => $submitted ?: $started,
                'minutes'   => (int) round(((int) $r['time_limit_sec']) / 60),
                'points'    => $max,
                'pass'      => (int) $r['pass_mark'],
                'type'      => 'homework',
                'href'      => base_url('student/lesson/' . (int) $r['course_id'] . '/' . $lid),
            );

            if ($key === 'done') {
                $view = $this->taqdar_marking_model->homework_student_view($a);
                $item['graded']  = $view['visible'];
                $item['score']   = $view['score'];
                $item['max']     = 100;   // مقياس الواجب في المنصة نسبة مئوية
                $item['note']    = $view['note'];
                $item['pass_ok'] = $view['visible'] ? $view['passed'] : null;
            }

            $groups[$key]['items'][] = $item;
        }

        return $groups;
    }

    /* ================================================================
       التقويم
       ================================================================ */

    /** الفئات الخمس الثابتة — ولون كل فئة ثابت لا يتغير بين الشاشات. */
    public function calendar_categories()
    {
        return array(
            'lessons'   => array(t('الدروس'),     'var(--tq-teal)',      'play',        t('انضم إلى الدرس'), 'student/lessons'),
            'exams'     => array(t('الاختبارات'), 'var(--tq-sky-ink)',   'check-badge', t('ابدأ الاختبار'),  'student/exams'),
            'tasks'     => array(t('المهام'),     'var(--tq-amber)',     'clipboard',   t('رفع الواجب'),     'student/tasks'),
            'on_demand' => array(t('حصص بالطلب'), 'var(--tq-navy)',      'video',       t('دخول الحصة'),     'student/on-demand'),
            'revisions' => array(t('المراجعات'),  'var(--tq-lilac-ink)', 'book',        t('بدء المراجعة'),   'student/materials'),
        );
    }

    /**
     * أحداث تقويم الطالب — خمسة مصادر مرتبة زمنيا.
     *
     * وكل حدث يحمل رابطه: تقويم يعرض موعدا ولا ينقر منه إلى شيء يقرأ
     * لوحة إعلانات لا شاشة عمل.
     */
    public function calendar_events($uid)
    {
        $uid = (int) $uid;
        if ($uid <= 0) return array();

        /** Academy يخزن الوقت نصا: طابعا زمنيا أحيانا وتاريخا أحيانا. */
        $ts = function ($value) {
            $v = trim((string) $value);
            if ($v === '' || $v === '0') return 0;
            if (ctype_digit($v)) return (int) $v;
            $t = strtotime($v);
            return $t ?: 0;
        };

        $mine = array();
        foreach ($this->db->select('c.id, c.title')->from('enrol e')
                          ->join('course c', 'c.id = e.course_id', 'inner')
                          ->where('e.user_id', $uid)->get()->result_array() as $c) {
            $mine[(int) $c['id']] = (string) $c['title'];
        }
        $cids   = array_keys($mine);
        $events = array();

        /* 1) الدروس — بداية الوحدة ونهايتها، وهما التاريخان الوحيدان
              المخزنان للمنهج. */
        if ($cids) {
            foreach ($this->db->select('id, title, course_id, start_date, end_date')
                              ->from('section')->where_in('course_id', $cids)
                              ->get()->result_array() as $s) {
                $cid = (int) $s['course_id'];
                foreach (array(array('start_date', t('بداية وحدة')),
                               array('end_date',   t('نهاية وحدة'))) as $pair) {
                    $at = $ts($s[$pair[0]]);
                    if ($at <= 0) continue;
                    $events[] = array(
                        'ts'    => $at,
                        'cat'   => 'lessons',
                        'title' => $pair[1] . ': ' . $s['title'],
                        'sub'   => isset($mine[$cid]) ? $mine[$cid] : '',
                        'href'  => base_url('student/lessons'),
                    );
                }
            }
        }

        /* 2) الاختبارات — من نتائج الطالب نفسه: تسليم أو بدء بلا تسليم. */
        if ($cids) {
            foreach ($this->db->select('qr.quiz_id, qr.is_submitted, qr.date_added, qr.date_updated,'
                                     . ' l.title, l.course_id')
                              ->from('quiz_results qr')
                              ->join('lesson l', 'l.id = qr.quiz_id', 'inner')
                              ->where('qr.user_id', $uid)
                              ->where_in('l.course_id', $cids)
                              ->get()->result_array() as $r) {
                $done = ((int) $r['is_submitted'] === 1);
                $at   = $ts($done ? $r['date_updated'] : $r['date_added']);
                if ($at <= 0) continue;
                $cid = (int) $r['course_id'];
                $events[] = array(
                    'ts'    => $at,
                    'cat'   => 'exams',
                    'title' => ($done ? t('سلمت: ') : t('بدأت: ')) . $r['title'],
                    'sub'   => isset($mine[$cid]) ? $mine[$cid] : '',
                    'href'  => base_url('student/lesson/' . $cid . '/' . (int) $r['quiz_id']),
                );
            }
        }

        /* 3) المهام — محاولات الطالب على تقييمات نوعها homework. */
        foreach ($this->db->select('ap.started_at, ap.submitted_at, l.id AS lesson_id, l.title, l.course_id')
                          ->from('attempts ap')
                          ->join('assessments a', 'a.id = ap.assessment_id', 'inner')
                          ->join('lesson l', 'l.id = a.lesson_id', 'inner')
                          ->where('ap.student_id', $uid)
                          ->where('a.type', 'homework')
                          ->get()->result_array() as $r) {
            $done = !empty($r['submitted_at']);
            $at   = $ts($done ? $r['submitted_at'] : $r['started_at']);
            if ($at <= 0) continue;
            $cid = (int) $r['course_id'];
            $events[] = array(
                'ts'    => $at,
                'cat'   => 'tasks',
                'title' => ($done ? t('سلمت واجب: ') : t('بدأت واجب: ')) . $r['title'],
                'sub'   => isset($mine[$cid]) ? $mine[$cid] : '',
                'href'  => base_url('student/lesson/' . $cid . '/' . (int) $r['lesson_id']),
            );
        }

        /* 4) حصص بالطلب — وقتها من الفترة المحجوزة لا من الحصة نفسها. */
        foreach ($this->db->select('sl.starts_at, sl.duration_min, ts.status, u.first_name, u.last_name')
                          ->from('tutoring_sessions ts')
                          ->join('availability_slots sl', 'sl.id = ts.slot_id', 'inner')
                          ->join('users u', 'u.id = ts.teacher_id', 'left')
                          ->where('ts.student_id', $uid)
                          ->where_in('ts.status', array('requested', 'confirmed', 'live', 'completed'))
                          ->get()->result_array() as $r) {
            $at = $ts($r['starts_at']);
            if ($at <= 0) continue;
            $who = trim((isset($r['first_name']) ? $r['first_name'] : '') . ' '
                      . (isset($r['last_name'])  ? $r['last_name']  : ''));
            $events[] = array(
                'ts'    => $at,
                'cat'   => 'on_demand',
                'title' => t('حصة') . ($who !== '' ? t(' مع ') . $who : ''),
                /* نص خام: العزل يقع عند العرض مرة واحدة، فلا يعزل الرقم مرتين */
                'sub'   => ((int) $r['duration_min']) . t(' دقيقة'),
                'href'  => base_url('student/on-demand'),
            );
        }

        /* 5) المراجعات — استحقاقات طابور التكرار المتباعد. */
        foreach ($this->db->select('rq.due_at, l.id AS lesson_id, l.title AS lesson_title, l.course_id')
                          ->from('review_queue rq')
                          ->join('question q', 'q.id = rq.question_id', 'inner')
                          ->join('lesson l', 'l.id = q.quiz_id', 'left')
                          ->where('rq.student_id', $uid)
                          ->order_by('rq.due_at', 'ASC')->limit(200)
                          ->get()->result_array() as $r) {
            $at = $ts($r['due_at']);
            if ($at <= 0) continue;
            $cid   = (int) $r['course_id'];
            $title = trim((string) $r['lesson_title']);
            $events[] = array(
                'ts'    => $at,
                'cat'   => 'revisions',
                'title' => t('مراجعة') . ($title !== '' ? ': ' . $title : ''),
                'sub'   => isset($mine[$cid]) ? $mine[$cid] : '',
                'href'  => $cid > 0 ? base_url('student/lesson/' . $cid . '/' . (int) $r['lesson_id'])
                                    : base_url('student/materials'),
            );
        }

        usort($events, function ($a, $b) { return $a['ts'] <=> $b['ts']; });
        return $events;
    }

    /* ================================================================
       المكتبة
       ================================================================ */

    /**
     * مكتبة الطالب — ما يقرؤه، وما يستطيع أن يفتحه بشراء.
     *
     * ═══ TQ-BOOK — وكانت تعرض كل كتب مرحلته على أنها مكتبته ═══
     *
     * يوم كان الكتاب **مجانيا كله** كان ذلك صحيحا: كل كتاب منشور مفتوح
     * لكل أحد، فمكتبة الطالب هي كتب مرحلته. وصار الكتاب يباع، فالقائمة
     * الواحدة تخلط ما دفع ثمنه بما لم يدفعه — ويضغط «افتح الكتاب» على
     * كتاب لا يملكه فلا يفتح، ولا شيء يقول لماذا.
     *
     * فصارت مجموعتين لا واحدة:
     *   · `books`      — ما يفتحه الآن: مجاني، أو اشتراه، أو في باقته
     *   · `locked`     — كتب مرحلته التي تحتاج شراء، بسعرها ورابطه
     *
     * والترشيح بالمرحلة (`category`) لا بالصف كما كان: كتاب صف ثالث في
     * مكتبة طالب ثانوي ضجيج. **ومن لا كتاب لمرحلته يرى الكل** — الترشيح
     * الذي يفرغ الشاشة أسوأ من ألا يكون. و`scoped` تقول أيهما وقع.
     *
     * **وما اشتراه يعرض ولو خرج عن مرحلته**: من اشترى كتابا بعينه دفع
     * ثمنه، وإخفاؤه لأن مرحلته غير مرحلة صاحبه سرقة صامتة.
     */
    public function library($uid, $limit_all = 24)
    {
        $uid = (int) $uid;
        $cat = 0;

        try {
            $row = $this->db->query(
                'SELECT c.`id` FROM `users` u
                   JOIN `grades` g ON g.`id` = u.`grade_id`
              LEFT JOIN `category` c ON c.`id` = g.`category_id`
                  WHERE u.`id` = ? LIMIT 1', array($uid))->row_array();
            $cat = $row ? (int) $row['id'] : 0;
        } catch (Throwable $e) {
            $this->db->reset_query();
            $cat = 0;
        }

        /* المخطط يركب من مسار العرض: الأعمدة تقرأ هنا، وقراءة عمود قبل
           إنشائه ترد «Unknown column» فتبيض شاشة الطالب. */
        $CI = get_instance();
        try {
            $CI->load->model('taqdar_book_model', 'tq_bk_lib');
            $CI->tq_bk_lib->install_schema();
        } catch (Throwable $e) {
            log_message('error', 'TQ-BOOK library schema: ' . $e->getMessage());
        }

        $cols  = 'b.*';
        $rows  = array();

        try {
            $this->db->select($cols)->from('books b')->where('b.status', 'published');
            if ($cat > 0) $this->db->where('b.category_id', $cat);
            $rows = $this->db->order_by('b.tq_order', 'ASC')->order_by('b.id', 'DESC')
                             ->get()->result_array();

            if (!$rows && $cat > 0) {
                $rows = $this->db->select($cols)->from('books b')->where('b.status', 'published')
                                 ->order_by('b.tq_order', 'ASC')->limit((int) $limit_all)
                                 ->get()->result_array();
                $cat = 0;
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
            $rows = array();
        }

        /* ما اشتراه أو فتحته باقته — استعلام واحد لا واحد لكل كتاب.
           و`granted_book_ids()` هي المصدر الواحد لسؤال «أيفتح؟»، وهي
           نفسها التي يسألها `has_book()` — فلا تعد الشاشة بما يمنعه
           الحارس ولا تقفل ما يفتحه. */
        $granted = array();
        try {
            $CI->load->model('taqdar_billing_model', 'tq_bill_lib');
            $granted = $CI->tq_bill_lib->granted_book_ids($uid);
        } catch (Throwable $e) {
            log_message('error', 'TQ-BOOK library grants: ' . $e->getMessage());
        }

        /* وما اشتراه خارج مرحلته يضم إلى القائمة: دفع ثمنه، وإخفاؤه
           لأن مرحلته غير مرحلته سرقة صامتة. */
        $have = array();
        foreach ($rows as $r) $have[(int) $r['id']] = true;
        $missing = array_values(array_diff(array_map('intval', $granted), array_keys($have)));
        if ($missing) {
            try {
                foreach ($this->db->select('b.*')->from('books b')
                                  ->where_in('b.id', $missing)
                                  ->where('b.status', 'published')
                                  ->get()->result_array() as $r) {
                    $rows[] = $r;
                }
            } catch (Throwable $e) { $this->db->reset_query(); }
        }

        $books = array();
        $locked = array();

        foreach ($rows as $r) {
            $bid = (int) $r['id'];

            $offer = null;
            try { $offer = $CI->tq_bk_lib->offer($r); } catch (Throwable $e) { $offer = null; }

            $free = !$offer || !empty($offer['free']);
            $open = $free || in_array($bid, array_map('intval', $granted), true);

            $r['sellable']   = $offer ? !empty($offer['sellable']) : false;
            $r['price']      = $offer ? (int) $offer['price'] : 0;
            $r['list_price'] = $offer ? (int) $offer['list_price'] : 0;
            $r['off']        = $offer ? (int) $offer['off'] : 0;
            $r['open']       = $open;

            if ($open) $books[] = $r;
            elseif ($r['sellable']) $locked[] = $r;
            /* وما ليس مفتوحا ولا يباع لا يعرض: كتاب معلن للبيع والباب
               مطفأ يعرض بلا سعر ولا زر — بطاقة لا تفعل شيئا. */
        }

        return array('books' => $books, 'locked' => $locked,
                     'category_id' => $cat, 'scoped' => $cat > 0,
                     'granted' => array_map('intval', $granted));
    }

    /* ================================================================
       التقارير
       ================================================================ */

    /**
     * أرقام «المتابعة والتقارير».
     *
     * والبسط والمقام من **مجموعة واحدة**: التقدم مقيد بالكورسات المسجلة
     * كما هو عدد الدروس. وكان يقرأ `watch_histories` كلها — البسط من كل
     * كورس مر به الطالب ولو حذف، والمقام من `enrol` وحده — فتطبع الشاشة
     * «٦ من ٠ درسا»: رقم يفضح نفسه ولا يفسر.
     */
    public function reports($uid)
    {
        $uid = (int) $uid;

        $enrolled = $uid > 0 ? $this->db->select('c.id, c.title, c.category_id')
            ->from('enrol e')->join('course c', 'c.id = e.course_id', 'inner')
            ->where('e.user_id', $uid)->get()->result_array() : array();

        $cids = array_map(function ($r) { return (int) $r['id']; }, $enrolled);

        $seconds = $uid > 0 ? (int) $this->db->select_sum('current_duration', 'total')
            ->where('watched_student_id', $uid)->get('watched_duration')->row('total') : 0;

        $history = $cids ? $this->db->where('student_id', $uid)->where_in('course_id', $cids)
                                    ->get('watch_histories')->result_array() : array();

        $sum = 0; $done_lessons = 0; $by_course = array();
        foreach ($history as $row) {
            $sum += (int) $row['course_progress'];
            $by_course[(int) $row['course_id']] = (int) $row['course_progress'];
            /* `completed_lesson` قائمة معرفات قد يتكرر فيها المعرف نفسه،
               فعدها خاما يعطي «١٤ من ١٢ درسا». والتفريد هو نفسه المعمول
               به في `tq_s_enrolled()` فلا يفترق رقم الشاشتين. */
            $d = json_decode($row['completed_lesson'], true);
            if (is_array($d)) $done_lessons += count(array_unique($d));
        }
        $completion = $history ? (int) round($sum / count($history)) : 0;

        /* عدد الدروس — والاختبار ليس درسا، فلا يحسب في مقام «المكتملة». */
        $total_lessons = 0; $lessons_by_course = array();
        if ($cids) {
            foreach ($this->db->select('course_id, COUNT(*) AS n')->from('lesson')
                              ->where_in('course_id', $cids)->where('lesson_type !=', 'quiz')
                              ->group_by('course_id')->get()->result_array() as $r) {
                $lessons_by_course[(int) $r['course_id']] = (int) $r['n'];
                $total_lessons += (int) $r['n'];
            }
        }

        /* الاختبارات: العلامة نسبة إلى عدد الأسئلة، ودرجة لم يعتمدها
           المعلم بعد لا تدخل متوسطا يقرؤه الطالب على أنه أداؤه. */
        $quizzes = $uid > 0 ? $this->db->where('user_id', $uid)->where('is_submitted', 1)
            ->order_by('date_added', 'ASC')->get('quiz_results')->result_array() : array();

        $points = array();
        foreach ($quizzes as $q) {
            $ans = json_decode($q['correct_answers'], true);
            $n   = is_array($ans) ? count($ans) : 0;
            if ($n < 1) continue;
            if (function_exists('tq_grade_visible') && !tq_grade_visible($q)) continue;
            $points[(int) $q['date_added']] =
                max(0, min(100, (int) round(((float) $q['total_obtained_marks'] / $n) * 100)));
        }
        $average = $points ? (int) round(array_sum($points) / count($points)) : 0;

        /* الأسابيع الثمانية الأخيرة — والأسبوع يبدأ الأحد. */
        $today  = strtotime('today');
        $wstart = $today - ((int) date('w', $today)) * 86400;
        $weeks  = array();
        for ($i = 7; $i >= 0; $i--) {
            $from = $wstart - $i * 7 * 86400;
            $weeks[] = array('from' => $from, 'to' => $from + 7 * 86400);
        }

        $grade_series = array();
        foreach ($weeks as $w) {
            $v = array();
            foreach ($points as $at => $pct) if ($at >= $w['from'] && $at < $w['to']) $v[] = $pct;
            $grade_series[] = $v ? (int) round(array_sum($v) / count($v)) : null;
        }

        /* قيمة مسجلة لا مستنتجة، ولذلك تترك فارغة في أسبوع بلا تحديث. */
        $completion_series = array();
        foreach ($weeks as $w) {
            $v = array();
            foreach ($history as $row) {
                $at = (int) $row['date_updated'];
                if ($at >= $w['from'] && $at < $w['to']) $v[] = (int) $row['course_progress'];
            }
            $completion_series[] = $v ? (int) round(array_sum($v) / count($v)) : null;
        }

        /* الدروس المكتملة تراكميا — مصدرها `lesson_progress.completed_at`،
           الطابع الزمني الوحيد في القاعدة لإكمال درس بعينه. والخط تراكمي
           لأن «المكتملة» عدد لا يتناقص. */
        $done_ts = array();
        if ($uid > 0) {
            foreach ($this->db->select('completed_at')->from('lesson_progress')
                              ->where('student_id', $uid)
                              ->where('completed_at IS NOT NULL', null, false)
                              ->get()->result_array() as $r) {
                $at = strtotime((string) $r['completed_at']);
                if ($at) $done_ts[] = $at;
            }
        }
        $lessons_series = array();
        foreach ($weeks as $w) {
            $n = 0;
            foreach ($done_ts as $at) if ($at < $w['to']) $n++;
            $lessons_series[] = $n > 0 ? $n : null;
        }

        /* الدلتا تحسب فقط حين يوجد قياس فعلي للأسبوعين — وصفر مخترع
           مكان الفراغ يجعل الشاشة تقول «تراجعت» لمن لم يقس له شيء. */
        $delta = function ($s) {
            $n = count($s);
            if ($n < 2 || $s[$n - 1] === null || $s[$n - 2] === null) return null;
            return $s[$n - 1] - $s[$n - 2];
        };

        /* أداؤك في المواد: تجميع الكورسات بالتصنيف. */
        $subjects = array();
        if ($enrolled) {
            $cat_ids = array_values(array_unique(array_filter(array_map(function ($r) {
                return (int) $r['category_id'];
            }, $enrolled))));
            $names = array();
            if ($cat_ids) {
                foreach ($this->db->where_in('id', $cat_ids)->get('category')->result_array() as $c) {
                    $names[(int) $c['id']] = $c['name'];
                }
            }
            foreach ($enrolled as $row) {
                $k = (int) $row['category_id'];
                if (!isset($subjects[$k])) {
                    $subjects[$k] = array('name' => isset($names[$k]) ? $names[$k] : $row['title'],
                                          'sum' => 0, 'courses' => 0, 'lessons' => 0);
                }
                $subjects[$k]['sum']     += isset($by_course[(int) $row['id']]) ? $by_course[(int) $row['id']] : 0;
                $subjects[$k]['lessons'] += isset($lessons_by_course[(int) $row['id']]) ? $lessons_by_course[(int) $row['id']] : 0;
                $subjects[$k]['courses']++;
            }
            $subjects = array_slice($subjects, 0, 5, true);
        }

        return array(
            'has_data'           => count($cids) > 0,
            'enrolled'           => $enrolled,
            'seconds'            => $seconds,
            'hours'              => intdiv($seconds, 3600),
            'minutes'            => intdiv($seconds % 3600, 60),
            'completion'         => $completion,
            'done_lessons'       => $done_lessons,
            'total_lessons'      => $total_lessons,
            'average'            => $average,
            'quizzes'            => $quizzes,
            'quiz_points'        => $points,
            'history'            => $history,
            'progress_by_course' => $by_course,
            'lessons_by_course'  => $lessons_by_course,
            'weeks'              => $weeks,
            'grade_series'       => $grade_series,
            'completion_series'  => $completion_series,
            'lessons_series'     => $lessons_series,
            'grade_delta'        => $delta($grade_series),
            'completion_delta'   => $delta($completion_series),
            'subjects'           => $subjects,
        );
    }

    /* ================================================================
       البحث داخل البوابة
       ================================================================ */

    /**
     * بحث الطالب في **ما يملكه** لا في الكتالوج.
     *
     * الكتالوج العام له `Taqdar_catalog_model` وبابه `/catalog`؛ وهذا
     * السؤال آخر: «أين ذلك الدرس الذي شاهدته؟». فالمصادر ثلاثة —
     * كورساته ودروسه وملفاته — من `tq_s_*` نفسها التي تبني شاشاتها،
     * فلا يفترق ما يجده البحث عما يفتحه بعده.
     */
    public function search($uid, $q, $limit = 10)
    {
        $uid = (int) $uid;
        $q   = trim((string) $q);

        $out = array('courses' => array(), 'lessons' => array(), 'materials' => array(), 'total' => 0);
        if ($uid <= 0 || $q === '') return $out;

        $hit = function ($hay) use ($q) { return mb_stripos((string) $hay, $q) !== false; };

        foreach (tq_s_enrolled($uid) as $c) {
            /* `tq_s_enrolled()` ترد `category_id` لا اسم المادة —
               و`tq_s_subject()` هي التي تسميه، وهي نفسها التي تسميه في
               بطاقة الكورس. فالبحث يطابق ما يقرؤه صاحبه على الشاشة. */
            $subject = tq_s_subject($c['category_id'], (string) $c['title'], (int) $c['id']);
            if (!$hit($c['title']) && !$hit($subject)) continue;
            $c['subject'] = $subject;
            $out['courses'][] = $c;
            if (count($out['courses']) >= $limit) break;
        }

        foreach (tq_s_lessons($uid) as $l) {
            if (!$hit($l['title']) && !$hit(isset($l['course']) ? $l['course'] : '')) continue;
            $out['lessons'][] = $l;
            if (count($out['lessons']) >= $limit) break;
        }

        foreach (tq_s_materials($uid) as $m) {
            if (!$hit($m['title']) && !$hit(isset($m['course']) ? $m['course'] : '')) continue;
            $out['materials'][] = $m;
            if (count($out['materials']) >= $limit) break;
        }

        $out['total'] = count($out['courses']) + count($out['lessons']) + count($out['materials']);
        return $out;
    }
}
