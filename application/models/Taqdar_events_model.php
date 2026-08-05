<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أحداث تقدّر — **الموضع الوحيد الذي يُكتب فيه إشعار**.
 *
 * شاشة وليّ الأمر تفرز خمسة أنواع بقائمة بيضاء على `notifications.type`،
 * ولم يكن أيٌّ منها يُكتب من أي مكان في الخادم — فقسم «يستحقّ انتباهك»
 * فارغ بنيةً لا عرضًا. هذا الملفّ يسدّ ذلك الفراغ بواجهة واحدة، فلا
 * يتكرّر منطق الكتابة في كل مُطلِق حدث ولا تتباعد صيغ العناوين.
 *
 * ثلاث قواعد تحكم ما هنا:
 *
 * ١. **الأعمدة الحقيقية لا المفترضة.** الجدول `notifications` أعمدته
 *    `from_user, to_user, type, title, description, status, created_at,
 *    updated_at`، و`created_at` **طابع زمني يونكس مخزَّن نصًّا** — هكذا
 *    كتبته Academy وهكذا تقرؤه شاشتا الطالب ووليّ الأمر
 *    (`tq_since((int) $n['created_at'])`). فمن يكتب تاريخًا بصيغة أخرى
 *    يجعل كل إشعاراته تظهر «منذ ٥٦ سنة».
 *
 * ٢. **منع التكرار ببصمة لا بالحدس.** «نفس الحدث» ليس «نفس النوع»:
 *    نتيجتا امتحانين في يوم واحد حدثان، وانقطاع ابنين حدثان. فلكل نداء
 *    بصمة `<!--tq:HASH-->` مخبوءة في نهاية الوصف تُشتقّ من النوع ومن
 *    مُميِّز يمرّره المُطلِق (`key`)، والبحث عنها هو ما يمنع التكرار.
 *    والبصمة تعليق HTML: يمحوها `strip_tags` في شاشة الطالب، ولا يعرضها
 *    متصفّح، ولا تحتاج عمودًا جديدًا في جدول مشترك مع السكربت الأصلي.
 *
 * ٣. **البريد خلف مفتاح مُطفأ.** `smtp_user` و`smtp_pass` فارغان على هذا
 *    الخادم، فأي إرسال فعلي إمّا يفشل صامتًا أو يعلّق المهمّة الدورية عند
 *    مهلة الاتصال. الإشعار داخل المنصّة يُكتب دائمًا، والبريد لا يُحاوَل
 *    إلّا إذا ضُبط `taqdar_events_email = 1` في `settings` **و** كان
 *    `smtp_user` غير فارغ. لا يُنشئ هذا الملفّ ذلك المفتاح.
 *
 * وتحذير أخير: هذا النموذج يُستدعى من مسار الكرون، فلا يقرأ `$this->session`
 * بحال — لا جلسة في سطر الأوامر، وقراءتها هناك تُسقط المهمّة بعد أن تكون
 * قد غيّرت البيانات. مَن أراد الفاعل مرّره في `from_user`.
 */
class Taqdar_events_model extends CI_Model
{
    /**
     * الأنواع الخمسة التي تقاطع وليّ الأمر — والقائمة نفسها التي تفرز بها
     * `tq_parent_alerts.php`. وأي نوع خارجها يُكتب أيضًا لكنه يهبط في
     * «ينتظر التقرير الأسبوعي»، وهو الصواب لا نقص.
     */
    private $catalog = array(
        'exam_result' => array(
            'label'  => 'نتيجة امتحان',
            'self'   => 'ظهرت نتيجة امتحانك',
            'parent' => 'نتيجة امتحان {name}',
        ),
        'station_failed' => array(
            'label'  => 'رسوب في اختبار محطة',
            'self'   => 'لم تجتز اختبار المحطة',
            'parent' => 'لم يجتز {name} اختبار المحطة',
        ),
        'inactivity_3days' => array(
            'label'  => 'انقطاع ثلاثة أيام',
            'self'   => 'انقطعت عن الدراسة ثلاثة أيام',
            'parent' => 'انقطع {name} عن الدراسة ثلاثة أيام',
        ),
        'session_request' => array(
            'label'  => 'طلب حصة خاصة',
            'self'   => 'أُرسل طلب حصّة خاصّة',
            'parent' => 'طلب حصّة خاصّة لـ{name}',
        ),
        'certificate' => array(
            'label'  => 'شهادة جديدة',
            'self'   => 'حصلت على شهادة جديدة',
            'parent' => 'شهادة جديدة لـ{name}',
        ),
        'weekly_report' => array(
            'label'  => 'التقرير الأسبوعي',
            'self'   => 'تقريرك الأسبوعي جاهز',
            'parent' => 'تقريرك الأسبوعي جاهز',
        ),
    );

    /** الأنواع وعناوينها العربية — تقرؤها الشاشات ولا تكرّرها. */
    public function types()
    {
        $out = array();
        foreach ($this->catalog as $type => $meta) {
            $out[$type] = $meta['label'];
        }
        return $out;
    }

    /* =====================================================================
       الواجهة: نداء واحد يكتب إشعارًا
       ===================================================================== */

    /**
     * يكتب إشعارًا واحدًا لمستخدم واحد.
     *
     * @param int    $user_id  مستقبِل الإشعار (`notifications.to_user`)
     * @param string $type     نوع الحدث (`notifications.type`)
     * @param array  $payload  اختياري كلّه:
     *        - key         string  مُميِّز الحدث داخل نوعه («attempt:91»)،
     *                              وهو ما يمنع خلط حدثين من نوع واحد.
     *        - text        string  جسم الإشعار، تُستبدل فيه المتغيّرات.
     *        - title       string  عنوان صريح يتجاوز عنوان الكتالوج.
     *        - vars        array   بدائل `{name}` وأخواتها.
     *        - from_user   int     الفاعل؛ NULL إن كان النظام.
     *        - window_days int     نافذة منع التكرار بالأيام؛ الافتراض
     *                              «اليوم الجاري» وحده.
     *        - audience    string  self|parent — أيّ صيغة عنوان تُختار.
     *
     * @return int معرّف الصفّ المكتوب، أو 0 إن مُنع بوصفه مكرّرًا.
     */
    public function notify($user_id, $type, $payload = array())
    {
        $user_id = (int) $user_id;
        $type    = trim((string) $type);
        if ($user_id <= 0 || $type === '') {
            return 0;
        }
        if (!is_array($payload)) {
            $payload = array();
        }

        $vars     = (isset($payload['vars']) && is_array($payload['vars'])) ? $payload['vars'] : array();
        $audience = (isset($payload['audience']) && $payload['audience'] === 'parent') ? 'parent' : 'self';

        $title = (isset($payload['title']) && $payload['title'] !== '')
            ? (string) $payload['title']
            : $this->default_title($type, $audience);
        $title = $this->fill($title, $vars);

        $text = isset($payload['text']) ? $this->fill((string) $payload['text'], $vars) : '';

        $fingerprint = $this->fingerprint($type, isset($payload['key']) ? (string) $payload['key'] : '');

        $window = isset($payload['window_days']) ? (int) $payload['window_days'] : 0;
        $since  = ($window > 0) ? (time() - $window * 86400) : strtotime('today');

        if ($this->already_sent($user_id, $type, $fingerprint, $since)) {
            return 0;
        }

        $from = (isset($payload['from_user']) && (int) $payload['from_user'] > 0)
            ? (int) $payload['from_user'] : null;

        $this->db->insert('notifications', array(
            'from_user'   => $from,
            'to_user'     => $user_id,
            'type'        => $type,
            'title'       => mb_substr($title, 0, 250),
            'description' => $this->body($text, $fingerprint),
            'status'      => 0,                 // 0 = غير مقروء، وهي قراءة الشاشتين
            'created_at'  => (string) time(),   // طابع يونكس نصًّا كما يكتب السكربت
            'updated_at'  => null,
        ));

        $id = (int) $this->db->insert_id();
        if ($id > 0) {
            $this->maybe_email($user_id, $title, $text);
        }
        return $id;
    }

    /**
     * يكتب الإشعار لكل وليّ أمر مرتبط بهذا الطالب بموافقة **نشطة**.
     * الرابط المعلَّق أو المسحوب لا يفتح بيانات أحد — ولا يستقبل إشعارًا عنه.
     *
     * @return int عدد أولياء الأمر الذين كُتب لهم فعلًا.
     */
    public function notify_parents($student_id, $type, $payload = array())
    {
        $student_id = (int) $student_id;
        if ($student_id <= 0) {
            return 0;
        }
        if (!is_array($payload)) {
            $payload = array();
        }

        $vars = (isset($payload['vars']) && is_array($payload['vars'])) ? $payload['vars'] : array();
        if (!isset($vars['name'])) {
            $vars['name'] = $this->student_name($student_id);
        }
        $payload['vars']     = $vars;
        $payload['audience'] = 'parent';

        // الابن جزء من هوية الحدث: ابنان ينقطعان في يوم حدثان لا حدث.
        $key = isset($payload['key']) ? (string) $payload['key'] : '';
        $payload['key'] = 'student:' . $student_id . ($key !== '' ? '|' . $key : '');

        $written = 0;
        foreach ($this->parents_of($student_id) as $parent_id) {
            if ($this->notify($parent_id, $type, $payload) > 0) {
                $written++;
            }
        }
        return $written;
    }

    /** يكتب للطالب ولأوليائه معًا — أكثر الأحداث تخصّ الاثنين. */
    public function notify_student_and_parents($student_id, $type, $payload = array())
    {
        $student_id = (int) $student_id;
        $self       = $payload;
        $self['audience'] = 'self';

        $n = ($this->notify($student_id, $type, $self) > 0) ? 1 : 0;
        return $n + $this->notify_parents($student_id, $type, $payload);
    }

    /* =====================================================================
       قراءات مساعدة — يستعملها الكرون ويمكن أن يستعملها غيره
       ===================================================================== */

    /** أولياء أمر الطالب بموافقة نشطة، وحساباتهم مفعّلة. */
    public function parents_of($student_id)
    {
        if (!$this->db->table_exists('parent_links')) {
            return array();
        }
        $rows = $this->db->query(
            'SELECT DISTINCT pl.`parent_user_id` AS id
               FROM `parent_links` pl
               JOIN `users` u ON u.`id` = pl.`parent_user_id` AND u.`status` = 1
              WHERE pl.`student_id` = ? AND pl.`status` = "active"',
            array((int) $student_id)
        )->result_array();

        $ids = array();
        foreach ($rows as $r) {
            $ids[] = (int) $r['id'];
        }
        return $ids;
    }

    /** كل طالب تربطه بوليّ أمر موافقة نشطة — وحدهم من يُراقب خمولهم. */
    public function watched_students()
    {
        if (!$this->db->table_exists('parent_links')) {
            return array();
        }
        $rows = $this->db->query(
            'SELECT DISTINCT pl.`student_id` AS id
               FROM `parent_links` pl
               JOIN `users` u ON u.`id` = pl.`student_id` AND u.`status` = 1
              WHERE pl.`status` = "active"'
        )->result_array();

        $ids = array();
        foreach ($rows as $r) {
            $ids[] = (int) $r['id'];
        }
        return $ids;
    }

    public function student_name($student_id)
    {
        $u = $this->db->select('first_name, last_name')
                      ->where('id', (int) $student_id)
                      ->get('users')->row_array();
        if (!$u) {
            return 'ابنك';
        }
        $name = trim((string) $u['first_name'] . ' ' . (string) $u['last_name']);
        return $name !== '' ? $name : 'ابنك';
    }

    /**
     * آخر نشاط للطالب — طابع يونكس، أو 0 إن لم يُسجَّل له نشاط قطّ.
     *
     * يُجمَع من كل ما يكتب الطالبُ فيه وقتًا: مشاهدة الدروس واختبارات
     * Academy (`watch_histories`, `quiz_results`) — وهما المصدران اللذان
     * يبني عليهما التقرير الأسبوعي «أيام نشاطه» — ومحاولات بوّابة الإتقان
     * وتقدّم الدروس وحالة المهارات. ولا يُحتسب الدخول وحده نشاطًا: مَن
     * يفتح المنصّة ولا يدرس منقطع.
     */
    public function last_activity_at($student_id)
    {
        $sid  = (int) $student_id;
        $last = 0;

        // أعمدة تحمل طابع يونكس نصًّا (إرث Academy)
        $unix = array(
            array('watch_histories', 'date_updated', 'student_id'),
            array('watch_histories', 'date_added',   'student_id'),
            array('quiz_results',    'date_updated', 'user_id'),
            array('quiz_results',    'date_added',   'user_id'),
        );
        foreach ($unix as $s) {
            if (!$this->db->table_exists($s[0])) {
                continue;
            }
            $row = $this->db->query(
                'SELECT MAX(CAST(`' . $s[1] . '` AS UNSIGNED)) AS t
                   FROM `' . $s[0] . '` WHERE `' . $s[2] . '` = ?',
                array($sid)
            )->row_array();
            $t = isset($row['t']) ? (int) $row['t'] : 0;
            if ($t > $last) {
                $last = $t;
            }
        }

        // أعمدة DATETIME (جداول تقدّر)
        $dates = array(
            array('attempts',        'submitted_at', 'student_id'),
            array('attempts',        'started_at',   'student_id'),
            array('lesson_progress', 'mastered_at',  'student_id'),
            array('lesson_progress', 'completed_at', 'student_id'),
            array('skill_state',     'last_seen_at', 'student_id'),
        );
        foreach ($dates as $s) {
            if (!$this->db->table_exists($s[0])) {
                continue;
            }
            $row = $this->db->query(
                'SELECT MAX(UNIX_TIMESTAMP(`' . $s[1] . '`)) AS t
                   FROM `' . $s[0] . '` WHERE `' . $s[2] . '` = ?',
                array($sid)
            )->row_array();
            $t = isset($row['t']) ? (int) $row['t'] : 0;
            if ($t > $last) {
                $last = $t;
            }
        }

        return $last;
    }

    /** تاريخ إنشاء الحساب — مرجع الخمول لمن لم يبدأ بعد أصلًا. */
    public function registered_at($user_id)
    {
        $u = $this->db->select('date_added')->where('id', (int) $user_id)
                      ->get('users')->row_array();
        return $u ? (int) $u['date_added'] : 0;
    }

    /* =====================================================================
       الداخل
       ===================================================================== */

    private function default_title($type, $audience)
    {
        if (!isset($this->catalog[$type])) {
            return 'إشعار جديد';
        }
        $meta = $this->catalog[$type];
        return isset($meta[$audience]) ? $meta[$audience] : $meta['self'];
    }

    private function fill($template, $vars)
    {
        if (!$vars) {
            return $template;
        }
        $search  = array();
        $replace = array();
        foreach ($vars as $k => $v) {
            $search[]  = '{' . $k . '}';
            $replace[] = (string) $v;
        }
        return str_replace($search, $replace, $template);
    }

    /** بصمة الحدث: النوع ومُميِّزه. قصيرة تكفي، والاصطدام هنا لا يفقد بيانات. */
    private function fingerprint($type, $key)
    {
        return 'tq:' . substr(sha1($type . '|' . $key), 0, 16);
    }

    private function body($text, $fingerprint)
    {
        $text = trim((string) $text);
        return ($text !== '' ? $text . "\n" : '') . '<!--' . $fingerprint . '-->';
    }

    /**
     * هل كُتب هذا الحدث بعينه لهذا المستخدم داخل النافذة؟
     *
     * `created_at` نصّ، فالمقارنة تجري على قيمته العددية صراحةً: المقارنة
     * النصّية تصحّ اليوم لأن كل الطوابع عشر خانات، وتنكسر صامتةً يوم تصير
     * إحدى عشرة — وهذا نوع الخطأ الذي لا يُكتشف إلّا بعد وقوعه.
     */
    private function already_sent($user_id, $type, $fingerprint, $since)
    {
        $row = $this->db->query(
            'SELECT `id` FROM `notifications`
              WHERE `to_user` = ?
                AND `type` = ?
                AND CAST(`created_at` AS UNSIGNED) >= ?
                AND `description` LIKE ?
              LIMIT 1',
            array((int) $user_id, (string) $type, (int) $since, '%' . $fingerprint . '%')
        )->row_array();

        return !empty($row);
    }

    /**
     * البريد — مُطفأ ما لم يُضبط مفتاحان معًا.
     *
     * `taqdar_events_email = 1` في `settings` يعبّر عن النيّة، و`smtp_user`
     * غير الفارغ يعبّر عن القدرة. وبلا الثاني يعلّق الإرسالُ المهمّةَ الدورية
     * عند مهلة اتصال SMTP، فتموت المهمّة قبل أن تكمل أحداثها.
     */
    private function maybe_email($user_id, $title, $text)
    {
        if ((string) get_settings('taqdar_events_email') !== '1') {
            return false;
        }
        if (trim((string) get_settings('smtp_user')) === '') {
            return false;
        }

        $u = $this->db->select('email')->where('id', (int) $user_id)->get('users')->row_array();
        if (!$u || empty($u['email'])) {
            return false;
        }

        try {
            $this->load->library('email');
            $this->email->clear(true);
            $this->email->initialize(array(
                'protocol'  => 'smtp',
                'smtp_host' => get_settings('smtp_host'),
                'smtp_port' => get_settings('smtp_port'),
                'smtp_user' => get_settings('smtp_user'),
                'smtp_pass' => get_settings('smtp_pass'),
                'smtp_crypto' => get_settings('smtp_crypto'),
                'mailtype'  => 'html',
                'charset'   => 'utf-8',
                'newline'   => "\r\n",
            ));
            $this->email->from(get_settings('smtp_from_email'), 'تقدّر');
            $this->email->to($u['email']);
            $this->email->subject($title);
            $this->email->message('<div dir="rtl">' . html_escape($title) . '<br>' . html_escape($text) . '</div>');
            return (bool) $this->email->send(false);
        } catch (Exception $e) {
            // الإشعار داخل المنصّة كُتب already — وفشل البريد لا يُسقط المهمّة
            log_message('error', 'taqdar_events mail failed: ' . $e->getMessage());
            return false;
        }
    }
}
