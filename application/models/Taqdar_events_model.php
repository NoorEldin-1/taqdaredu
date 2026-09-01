<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أحداث تقدر — **الموضع الوحيد الذي يكتب فيه إشعار**.
 *
 * شاشة ولي الأمر تفرز خمسة أنواع بقائمة بيضاء على `notifications.type`،
 * ولم يكن أي منها يكتب من أي مكان في الخادم — فقسم «يستحق انتباهك»
 * فارغ بنية لا عرضا. هذا الملف يسد ذلك الفراغ بواجهة واحدة، فلا
 * يتكرر منطق الكتابة في كل مطلق حدث ولا تتباعد صيغ العناوين.
 *
 * ثلاث قواعد تحكم ما هنا:
 *
 * ١. **الأعمدة الحقيقية لا المفترضة.** الجدول `notifications` أعمدته
 *    `from_user, to_user, type, title, description, status, created_at,
 *    updated_at`، و`created_at` **طابع زمني يونكس مخزن نصا** — هكذا
 *    كتبته Academy وهكذا تقرؤه شاشتا الطالب وولي الأمر
 *    (`tq_since((int) $n['created_at'])`). فمن يكتب تاريخا بصيغة أخرى
 *    يجعل كل إشعاراته تظهر «منذ ٥٦ سنة».
 *
 * ٢. **منع التكرار ببصمة لا بالحدس.** «نفس الحدث» ليس «نفس النوع»:
 *    نتيجتا امتحانين في يوم واحد حدثان، وانقطاع ابنين حدثان. فلكل نداء
 *    بصمة `<!--tq:HASH-->` مخبوءة في نهاية الوصف تشتق من النوع ومن
 *    مميز يمرره المطلق (`key`)، والبحث عنها هو ما يمنع التكرار.
 *    والبصمة تعليق HTML: يمحوها `strip_tags` في شاشة الطالب، ولا يعرضها
 *    متصفح، ولا تحتاج عمودا جديدا في جدول مشترك مع السكربت الأصلي.
 *
 * ٣. **البريد خلف مفتاح مطفأ.** `smtp_user` و`smtp_pass` فارغان على هذا
 *    الخادم، فأي إرسال فعلي إما يفشل صامتا أو يعلق المهمة الدورية عند
 *    مهلة الاتصال. الإشعار داخل المنصة يكتب دائما، والبريد لا يحاول
 *    إلا إذا ضبط `taqdar_events_email = 1` في `settings` **و** كان
 *    `smtp_user` غير فارغ. لا ينشئ هذا الملف ذلك المفتاح.
 *
 * وتحذير أخير: هذا النموذج يستدعى من مسار الكرون، فلا يقرأ `$this->session`
 * بحال — لا جلسة في سطر الأوامر، وقراءتها هناك تسقط المهمة بعد أن تكون
 * قد غيرت البيانات. من أراد الفاعل مرره في `from_user`.
 */
class Taqdar_events_model extends CI_Model
{
    /**
     * الأنواع الخمسة التي تقاطع ولي الأمر — والقائمة نفسها التي تفرز بها
     * `tq_parent_alerts.php`. وأي نوع خارجها يكتب أيضا لكنه يهبط في
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
            'self'   => 'أرسل طلب حصة خاصة',
            'parent' => 'طلب حصة خاصة لـ{name}',
        ),
        'certificate' => array(
            'label'  => 'شهادة جديدة',
            'self'   => 'حصلت على شهادة جديدة',
            'parent' => 'شهادة جديدة لـ{name}',
        ),
        /* نتيجة اختبار تحديد المستوى: تقع مرة واحدة لكل طالب، وهي التي
           يقرر عليها من يدفع أي باقة يشتري. فمكانها «يستحق انتباهك» لا
           تقرير الأحد — الباقة تشترى قبل الأحد. */
        'placement_result' => array(
            'label'  => 'نتيجة تحديد المستوى',
            'self'   => 'ظهرت نتيجة اختبار تحديد مستواك',
            'parent' => 'نتيجة تحديد مستوى {name}',
        ),
        'weekly_report' => array(
            'label'  => 'التقرير الأسبوعي',
            'self'   => 'تقريرك الأسبوعي جاهز',
            'parent' => 'تقريرك الأسبوعي جاهز',
        ),
    );

    /** الأنواع وعناوينها العربية — تقرؤها الشاشات ولا تكررها. */
    public function types()
    {
        $out = array();
        foreach ($this->catalog as $type => $meta) {
            $out[$type] = $meta['label'];
        }
        return tq_t_deep($out);   // TQ-I18N — التسمية تعرض، والمفتاح يخزن
    }

    /* =====================================================================
       التصنيف — من يقاطع الليل ومن ينتظر الصباح
       =====================================================================

       الوثيقة تقول: «ساعات الصمت تحترم **إلا المالي والحصص**». فالتصنيف
       ليس زينة بل هو ما يفرق بين ما يوقظ صاحبه وما ينتظر.

         financial  فاتورة · سحب · استرداد        — لا يؤجل أبدا
         session    طلب حصة · تأكيدها · قرب موعدها — لا يؤجل أبدا
         learning   نتيجة · رسوب · شهادة · انقطاع  — يؤجل في الصمت
         marketing  عرض · تذكير تسويقي · بث إداري  — يؤجل في الصمت،
                                                     ويسقط في وضع الامتحان

       والافتراض `learning` لا `marketing`: نوع لم يصنف بعد حدث تعليمي في
       هذه المنصة، ومعاملته إعلانا تسقطه عن صاحبه في وضع الامتحان بلا أن
       يقصد أحد ذلك. */
    private $categories = array(
        'exam_result'       => 'learning',
        'station_failed'    => 'learning',
        'inactivity_3days'  => 'learning',
        'certificate'       => 'learning',
        'placement_result'  => 'learning',
        'weekly_report'     => 'learning',
        'session_request'   => 'session',
        'session_confirmed' => 'session',
        'session_declined'  => 'session',
        'session_soon'      => 'session',
        'invoice_due'       => 'financial',
        'invoice_paid'      => 'financial',
        'payout_approved'   => 'financial',
        'payout_paid'       => 'financial',
        'subscription_on'   => 'financial',
        'refund'            => 'financial',
        'promo'             => 'marketing',
        'announcement'      => 'marketing',
        'broadcast'         => 'marketing',
    );

    /** تصنيف نوع. الحمولة تعلو الجدول: مطلق الحدث أدرى بما أطلق. */
    public function category_of($type, $payload = array())
    {
        if (is_array($payload) && !empty($payload['category'])) {
            $c = (string) $payload['category'];
            if (in_array($c, array('financial', 'session', 'learning', 'marketing'), true)) return $c;
        }
        return isset($this->categories[$type]) ? $this->categories[$type] : 'learning';
    }

    /**
     * هل هذا المستخدم في ساعات صمته الآن؟
     *
     * الأعمدة موجودة في `tq_prefs_user` منذ أن كتبت شاشتا الإعدادات، ولم
     * يكن في المستودع قارئ واحد لها: يوقف المستخدم الإشعارات من العاشرة
     * مساء فتصله كما كانت. إعداد يحفظ ولا يفعل أسوأ من إعداد غائب — الأول
     * يكذب على صاحبه والثاني يعترف.
     *
     * والمدى الذي يعبر منتصف الليل (22 ← 7) شرطه «أو» لا «و»، وهو الحالة
     * الشائعة لا النادرة.
     */
    public function is_quiet_now($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return false;

        try {
            if (!$this->db->table_exists('tq_prefs_user')) return false;
            $row = $this->db->select('quiet_on, quiet_from, quiet_to')
                            ->where('user_id', $user_id)
                            ->get('tq_prefs_user')->row_array();
        } catch (Throwable $e) {
            return false;
        }

        if (!$row || (int) $row['quiet_on'] !== 1) return false;

        $from = (int) $row['quiet_from'];
        $to   = (int) $row['quiet_to'];
        if ($from === $to) return false;   // مدى بلا اتساع ليس صمتا

        $h = (int) date('G');
        return ($from < $to) ? ($h >= $from && $h < $to) : ($h >= $from || $h < $to);
    }

    /** أول لحظة بعد انتهاء الصمت — إليها يؤجل ما أجل، لا إلى «بعد ساعة». */
    private function quiet_ends_at($user_id)
    {
        try {
            $row = $this->db->select('quiet_to')->where('user_id', (int) $user_id)
                            ->get('tq_prefs_user')->row_array();
        } catch (Throwable $e) {
            $row = null;
        }
        $to = $row ? (int) $row['quiet_to'] : 7;
        $to = max(0, min(23, $to));

        $today = mktime($to, 0, 0);
        return ($today > time()) ? $today : $today + 86400;
    }

    /**
     * هل هذا الطالب في وضع الامتحان؟
     * المصدر `Taqdar_learn_model` وحده — الشاشة والمحرك يقرآن الحكم نفسه.
     */
    public function exam_mode_active($user_id)
    {
        try {
            $this->load->model('taqdar_learn_model', 'tq_learn');
            $m = $this->tq_learn->exam_mode((int) $user_id);
            return !empty($m['active']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /* =====================================================================
       الواجهة: نداء واحد يكتب إشعارا
       ===================================================================== */

    /**
     * يكتب إشعارا واحدا لمستخدم واحد.
     *
     * @param int    $user_id  مستقبل الإشعار (`notifications.to_user`)
     * @param string $type     نوع الحدث (`notifications.type`)
     * @param array  $payload  اختياري كله:
     *        - key         string  مميز الحدث داخل نوعه («attempt:91»)،
     *                              وهو ما يمنع خلط حدثين من نوع واحد.
     *        - text        string  جسم الإشعار، تستبدل فيه المتغيرات.
     *        - title       string  عنوان صريح يتجاوز عنوان الكتالوج.
     *        - vars        array   بدائل `{name}` وأخواتها.
     *        - from_user   int     الفاعل؛ NULL إن كان النظام.
     *        - window_days int     نافذة منع التكرار بالأيام؛ الافتراض
     *                              «اليوم الجاري» وحده.
     *        - audience    string  self|parent — أي صيغة عنوان تختار.
     *        - email       bool    `false` تمنع البريد العام من هنا. يمررها
     *                              من يرسل بريده بنفسه بجسم أوفى من سطر
     *                              الإشعار — وبلا هذا المفتاح يصل المستلم
     *                              رسالتان عن حدث واحد.
     *
     * @return int معرف الصف المكتوب، أو 0 إن منع بوصفه مكررا.
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

        /* وضع الامتحان يسقط التسويق وحده.
           `F2.5` نصه «كل الإشعارات التسويقية تتوقف تلقائيا بهذا الوضع» —
           وحدها. نتيجة امتحان ورسوب محطة وطلب حصة تصل كما كانت، وإسقاطها
           هنا يجعل الوضع الذي يفترض أن يخدم الامتحان يخفي نتيجته. */
        $category = $this->category_of($type, $payload);
        if ($category === 'marketing' && $this->exam_mode_active($user_id)) {
            log_message('debug', 'TQ-EVENTS: أسقط إشعار تسويقي في وضع الامتحان — ' . $type);
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
            'created_at'  => (string) time(),   // طابع يونكس نصا كما يكتب السكربت
            'updated_at'  => null,
        ));

        $id = (int) $this->db->insert_id();
        if ($id <= 0) {
            return 0;
        }

        /* الصف في `notifications` كتب — وهو **السجل**، يقرؤه صاحبه متى فتح
           شاشته ولا يقاطعه. أما الإرسال الخارج (البريد اليوم، والدفع
           والواتساب غدا) فهو **المقاطعة**، وعليه وحده تسري ساعات الصمت:
           التأجيل يؤجل الطرق على الباب ولا يمحو الرسالة.

           ومن مرر `email => false` لا يريد إرسالا خارجا أصلا، فلا يودع. */
        if (isset($payload['email']) && $payload['email'] === false) {
            return $id;
        }

        $delay_to = null;
        if (in_array($category, array('learning', 'marketing'), true)
            && $this->is_quiet_now($user_id)) {
            $delay_to = $this->quiet_ends_at($user_id);
        }

        $this->enqueue($user_id, $type, $title, $text, $category, $delay_to, 'email');

        /* والواتساب صفا ثانيا بقناته (TQ-WA-ALL).
           وكانت أحداث تقدر السبعة — نتيجة امتحان، ورسوب محطة، وشهادة،
           والتقرير الأسبوعي — تخرج بالبريد وحده: تكتب هنا ولا تمر
           بـ`push_notification()` أصلا، فبوابة الواتساب فيها لا تراها.
           فالطالب يقرأ في جواله خبر كل فاتورة ولا يقرأ خبر رسوبه.

           و`wa_ready()` تفحص قبل الإيداع: بلاها يمتلئ الطابور صفوفا
           تشطب في كل دورة على منصة لا واتساب فيها أصلا. */
        if ($this->wa_ready()) {
            $this->enqueue($user_id, $type, $title, $text, $category, $delay_to, 'whatsapp');
        }

        return $id;
    }

    /* =====================================================================
       طابور الإرسال — تأجيل وإعادة محاولة
       =====================================================================

       `B4.4` يطلب «طابور إشعارات (dedupe + retry) + ساعات صمت». والتكرار
       كان محجوبا ببصمة (`already_sent`) وهو الشق المتقن؛ والناقص شقان:
       إعادة المحاولة عند الفشل، وموضع تنتظر فيه المقاطعة حتى ينتهي الصمت.
       والاثنان طابور واحد لأنهما سؤال واحد: «متى يخرج هذا؟».

       والتراجع أسي: دقيقة، ثم أربع، ثم تسع… حتى خمس محاولات. خادم بريد
       يرفض الآن قد يقبل بعد ربع ساعة، وإعادة المحاولة كل ثانية تحرق
       الحصة وتزيد الرفض.

       والقناة عمود لا ثابت: يوم يضاف الواتساب (`B4.3`) يصير صفا بقناة
       أخرى في الطابور نفسه، بلا طابور ثان يعاد فيه التأجيل والإعادة. */

    public function ensure_queue()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_notify_queue` (
               `id`          INT(11)      NOT NULL AUTO_INCREMENT,
               `user_id`     INT(11)      NOT NULL,
               `type`        VARCHAR(64)  NOT NULL,
               `category`    VARCHAR(16)  NOT NULL DEFAULT "learning",
               `channel`     VARCHAR(16)  NOT NULL DEFAULT "email",
               `title`       VARCHAR(250) NOT NULL,
               `body`        TEXT         NULL,
               `state`       VARCHAR(16)  NOT NULL DEFAULT "queued",
               `attempts`    INT(11)      NOT NULL DEFAULT 0,
               `last_error`  VARCHAR(250) NULL,
               `next_try_at` INT(11)      NOT NULL DEFAULT 0,
               `created_at`  INT(11)      NOT NULL DEFAULT 0,
               `sent_at`     INT(11)      NULL,
               PRIMARY KEY (`id`),
               KEY `ix_due` (`state`,`next_try_at`),
               KEY `ix_user` (`user_id`,`type`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /**
     * يضع رسالة في الطابور. `$delay_to` طابع يونكس أو null للفور.
     *
     * و`$channel` معامل لا ثابت (TQ-WA-ALL): الصف يحمل قناته، و
     * `drain()` يوجهه إلى منفذها. وطابور ثان للواتساب كان يعني نسخة
     * ثانية من التأجيل وإعادة المحاولة والتراجع الأسي — تفترق عن أختها
     * عند أول تعديل، فيعاد المحاولة في قناة ولا يعاد في الأخرى.
     */
    public function enqueue($user_id, $type, $title, $text, $category = 'learning',
                            $delay_to = null, $channel = 'email')
    {
        try {
            $channel = in_array((string) $channel, array('email', 'whatsapp'), true)
                     ? (string) $channel : 'email';
            $this->ensure_queue();
            $this->db->insert('tq_notify_queue', array(
                'user_id'     => (int) $user_id,
                'type'        => (string) $type,
                'category'    => (string) $category,
                'channel'     => $channel,
                'title'       => mb_substr((string) $title, 0, 250),
                'body'        => (string) $text,
                'state'       => 'queued',
                'next_try_at' => $delay_to ? (int) $delay_to : time(),
                'created_at'  => time(),
            ));
            return (int) $this->db->insert_id();
        } catch (Throwable $e) {
            /* تعذر الطابور يفقد بريدا لا سجلا: الإشعار داخل المنصة كتب
               قبل هذا السطر، وهو ما يراه صاحبه في شاشته. */
            log_message('error', 'TQ-QUEUE enqueue: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * يصرف ما استحق من الطابور. ينادى من الكرون.
     * @return array sent · failed · dead · held
     */
    /**
     * هل الإرسال الخارج ممكن أصلا؟
     *
     * مفتاحان: النية (`taqdar_events_email`) والقدرة (إعداد SMTP). وهذا
     * الفحص **قبل** المحاولة لا بعدها — والفرق ليس أداء:
     *
     * منصة أطفأت البريد عمدا كانت رسائلها تعامل «محاولة أخفقت»، فتعاد
     * خمس مرات ثم تموت. فيمتلئ الطابور صفوفا حالها `dead` وسببها «تعذر
     * الإرسال» — وهو وصف كاذب: لم يتعذر شيء، بل لم يطلب أحد الإرسال.
     * ثم يفتح المسؤول شاشة الطابور فيقرأ عطلا لا وجود له.
     *
     * فما لا قناة له يوسم `skipped` من أول نظرة، بسبب يقرأ.
     */
    public function outbound_ready()
    {
        if ((string) get_settings('taqdar_events_email') !== '1') return false;

        try {
            $this->load->model('taqdar_mail_model');
            return (bool) $this->taqdar_mail_model->configured();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * ونظيره للواتساب — القدرة وحدها هنا.
     *
     * ولا مفتاح نية ثالث يخترع له: النية تعبر عنها **عائلة النوع** في
     * `Taqdar_wa_model::$FAMILIES`، وهي التي يقلبها المسؤول في شاشة
     * واحدة. ومفتاح `taqdar_events_wa` بجوارها يعني موضعين يطفئان الشيء
     * نفسه — فيطفئ المسؤول أحدهما ويقرأ القناة صامتة ولا يعرف أيهما.
     */
    public function wa_ready()
    {
        try {
            $this->load->model('taqdar_wa_model');
            return (bool) $this->taqdar_wa_model->ready();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function drain($limit = 50)
    {
        $out = array('sent' => 0, 'failed' => 0, 'dead' => 0, 'held' => 0, 'skipped' => 0);

        try {
            $this->ensure_queue();
            $rows = $this->db->where('state', 'queued')
                             ->where('next_try_at <=', time())
                             ->order_by('next_try_at', 'ASC')
                             ->limit(max(1, min(500, (int) $limit)))
                             ->get('tq_notify_queue')->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-QUEUE drain: ' . $e->getMessage());
            return $out;
        }

        /* لا قناة: يوسم ما استحق `skipped` ولا يحاول ولا يعاد. والإشعار
           داخل المنصة كتب قبل هذا، وهو ما يراه صاحبه فعلا. */
        /* الفحص المسبق **لكل قناة على حدة**.
           وكان `outbound_ready()` واحدا يحكم على الطابور كله: منصة أطفأت
           البريد وأبقت واتساب كانت تشطب صفوف الواتساب معه بحجة «البريد
           غير مفعل» — قناة تعمل توسم معطلة بسبب قناة أخرى. */
        if ($rows) {
            $ready = array(
                'email'    => $this->outbound_ready(),
                'whatsapp' => $this->wa_ready(),
            );
            $dead_ch = array();
            foreach ($rows as $r) {
                $ch = isset($r['channel']) ? (string) $r['channel'] : 'email';
                if (empty($ready[$ch])) $dead_ch[$ch][] = (int) $r['id'];
            }
            foreach ($dead_ch as $ch => $ids) {
                $this->db->where_in('id', $ids)->update('tq_notify_queue', array(
                    'state'      => 'skipped',
                    'last_error' => ($ch === 'whatsapp' ? 'واتساب' : 'البريد')
                                  . ' غير مفعل على هذه المنصة — الإشعار داخل المنصة كتب.',
                ));
                $out['skipped'] += count($ids);
            }
            if ($out['skipped']) {
                $skipped = array_merge(array(), ...array_values($dead_ch));
                $rows = array_values(array_filter($rows,
                    function ($r) use ($skipped) { return !in_array((int) $r['id'], $skipped, true); }));
            }
            if (!$rows) return $out;
        }

        $max = 5;

        foreach ($rows as $r) {
            $id  = (int) $r['id'];
            $uid = (int) $r['user_id'];

            /* الصمت يفحص عند الصرف أيضا لا عند الإيداع وحده: من غير
               ساعاته بعد أن أودعت رسالته يجد إعداده الجديد ساريا عليها. */
            if (in_array($r['category'], array('learning', 'marketing'), true)
                && $this->is_quiet_now($uid)) {
                $this->db->where('id', $id)->update('tq_notify_queue',
                    array('next_try_at' => $this->quiet_ends_at($uid)));
                $out['held']++;
                continue;
            }

            $attempts = (int) $r['attempts'] + 1;
            $ok  = false;
            $err = '';

            try {
                $ok = ((string) $r['channel'] === 'whatsapp')
                    ? (bool) $this->maybe_whatsapp($uid, $r['title'], (string) $r['body'], (string) $r['type'])
                    : (bool) $this->maybe_email($uid, $r['title'], (string) $r['body']);
            } catch (Throwable $e) {
                $ok  = false;
                $err = $e->getMessage();
            }

            if ($ok) {
                $this->db->where('id', $id)->update('tq_notify_queue', array(
                    'state' => 'sent', 'attempts' => $attempts,
                    'sent_at' => time(), 'last_error' => null));
                $out['sent']++;
                continue;
            }

            if ($attempts >= $max) {
                $this->db->where('id', $id)->update('tq_notify_queue', array(
                    'state'      => 'dead',
                    'attempts'   => $attempts,
                    'last_error' => mb_substr($err ?: 'تعذر الإرسال بعد المحاولات كلها', 0, 250)));
                $out['dead']++;
                continue;
            }

            // تراجع أسي: دقيقة · أربع · تسع · ست عشرة
            $this->db->where('id', $id)->update('tq_notify_queue', array(
                'attempts'    => $attempts,
                'next_try_at' => time() + ($attempts * $attempts * 60),
                'last_error'  => mb_substr($err ?: 'تعذر الإرسال', 0, 250)));
            $out['failed']++;
        }

        return $out;
    }

    /** إحصاء الطابور — تقرؤه اللوحة فلا يبقى الطابور صندوقا مغلقا. */
    public function queue_stats()
    {
        $out = array('queued' => 0, 'sent' => 0, 'dead' => 0, 'failed' => 0, 'skipped' => 0);
        try {
            $this->ensure_queue();
            $rows = $this->db->query(
                'SELECT `state`, COUNT(*) n FROM `tq_notify_queue` GROUP BY `state`')->result_array();
            foreach ($rows as $r) $out[$r['state']] = (int) $r['n'];
        } catch (Throwable $e) {
            // جدول لم يستعمل بعد: أصفار أهون من شاشة بيضاء
        }
        return $out;
    }

    /**
     * يكتب الإشعار لكل ولي أمر مرتبط بهذا الطالب بموافقة **نشطة**.
     * الرابط المعلق أو المسحوب لا يفتح بيانات أحد — ولا يستقبل إشعارا عنه.
     *
     * @return int عدد أولياء الأمر الذين كتب لهم فعلا.
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
            /* تفضيل ولي الأمر يفرض هنا — عند الكتابة لا عند العرض.
               شاشة الإعدادات تقول له صراحة «هذه الشاشة تحدد أيها تريد»،
               وكانت الخانة تحفظ في `parent_links.scope` ولا يقرؤها أحد:
               يوقف «رسوب في اختبار محطة» فيصله كل رسوب كما كان، وتكتفي
               شاشة الإشعارات بأن تعلق عليه شارة «نوع أوقفته». إعداد لا
               يغير شيئا أسوأ من إعداد غائب — الأول يكذب والثاني يعترف. */
            if (!$this->parent_wants($parent_id, $type)) {
                continue;
            }
            if ($this->notify($parent_id, $type, $payload) > 0) {
                $written++;
            }
        }
        return $written;
    }

    /**
     * هل اختار ولي الأمر أن يصله هذا النوع؟
     *
     * الافتراض «نعم»: الكتم قرار المستخدم لا قرارنا، وما لم يحفظ تفضيلا
     * بعد تصله الأحداث كلها. وما ليس من الأنواع القابلة للإيقاف — كأحداث
     * الربط نفسه — لا يكتم أصلا: لا يحجب عن أحد خبر فتح بياناته أو إغلاقها.
     */
    public function parent_wants($parent_id, $type)
    {
        $parent_id = (int) $parent_id;
        if ($parent_id <= 0) return false;

        $this->load->model('taqdar_parent_model');

        $switchable = array_keys($this->taqdar_parent_model->notify_keys());
        $switchable[] = 'weekly_report';
        if (!in_array($type, $switchable, true)) return true;

        $prefs = $this->taqdar_parent_model->prefs($parent_id);
        $key   = ($type === 'weekly_report') ? 'weekly' : $type;

        return !isset($prefs[$key]) || !empty($prefs[$key]);
    }

    /** يكتب للطالب ولأوليائه معا — أكثر الأحداث تخص الاثنين. */
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

    /** أولياء أمر الطالب بموافقة نشطة، وحساباتهم مفعلة. */
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

    /** كل طالب تربطه بولي أمر موافقة نشطة — وحدهم من يراقب خمولهم. */
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
     * آخر نشاط للطالب — طابع يونكس، أو 0 إن لم يسجل له نشاط قط.
     *
     * يجمع من كل ما يكتب الطالب فيه وقتا: مشاهدة الدروس واختبارات
     * Academy (`watch_histories`, `quiz_results`) — وهما المصدران اللذان
     * يبني عليهما التقرير الأسبوعي «أيام نشاطه» — ومحاولات بوابة الإتقان
     * وتقدم الدروس وحالة المهارات. ولا يحتسب الدخول وحده نشاطا: من
     * يفتح المنصة ولا يدرس منقطع.
     */
    public function last_activity_at($student_id)
    {
        $sid  = (int) $student_id;
        $last = 0;

        // أعمدة تحمل طابع يونكس نصا (إرث Academy)
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

        // أعمدة DATETIME (جداول تقدر)
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

    /** تاريخ إنشاء الحساب — مرجع الخمول لمن لم يبدأ بعد أصلا. */
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

    /** بصمة الحدث: النوع ومميزه. قصيرة تكفي، والاصطدام هنا لا يفقد بيانات. */
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
     * هل كتب هذا الحدث بعينه لهذا المستخدم داخل النافذة؟
     *
     * `created_at` نص، فالمقارنة تجري على قيمته العددية صراحة: المقارنة
     * النصية تصح اليوم لأن كل الطوابع عشر خانات، وتنكسر صامتة يوم تصير
     * إحدى عشرة — وهذا نوع الخطأ الذي لا يكتشف إلا بعد وقوعه.
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
     * البريد — مطفأ ما لم يضبط مفتاحان معا.
     *
     * `taqdar_events_email = 1` في `settings` يعبر عن النية، و`smtp_user`
     * غير الفارغ يعبر عن القدرة. وبلا الثاني يعلق الإرسال المهمة الدورية
     * عند مهلة اتصال SMTP، فتموت المهمة قبل أن تكمل أحداثها.
     */
    private function maybe_email($user_id, $title, $text)
    {
        /* المفتاح يعبر عن النية، و`Taqdar_mail_model::configured()` عن
           القدرة. وكان الفحص هنا `smtp_user` وحده — وهي لا تكفي: خادم
           بلا كلمة مرور يعلق الاتصال إلى المهلة ثم يفشل، وذلك في مسار
           كرون يمر على مئة طالب. */
        if ((string) get_settings('taqdar_events_email') !== '1') {
            return false;
        }

        $this->load->model('taqdar_mail_model');
        if (!$this->taqdar_mail_model->configured()) {
            return false;
        }

        $u = $this->db->select('email')->where('id', (int) $user_id)->get('users')->row_array();
        if (!$u || empty($u['email'])) {
            return false;
        }

        /* والنموذج لا يرمي بحال: فشل البريد لا يسقط المهمة الدورية بعد أن
           تكون قد غيرت البيانات. والإشعار داخل المنصة كتب قبل هذا السطر. */
        return $this->taqdar_mail_model->send_lines(
            $u['email'],
            $title,
            array($text),
            array('label' => 'افتح لوحتك', 'href' => site_url('student'))
        );
    }

    /**
     * واتساب — وسياسة العائلة وتفضيل صاحبه يفحصان **عند الصرف** لا عند
     * الإيداع وحده.
     *
     * وهو مبدأ الصمت نفسه في `drain()`: من أطفأ عائلة بعد أن أودعت
     * رسالته لا ينبغي أن تخرج إليه، ورسالة قد تنتظر في الطابور ساعات
     * قبل أن يحل وقتها.
     *
     * ولا يرمي: النداء في مسار كرون يمر على مئة طالب، واستثناء واحد
     * يقطع الدورة على من بعده.
     */
    private function maybe_whatsapp($user_id, $title, $text, $type = '')
    {
        $this->load->model('taqdar_wa_model');
        if (!$this->taqdar_wa_model->type_on((string) $type)) return false;

        try {
            $this->load->model('taqdar_settings_model');
            if (!$this->taqdar_settings_model->allows((int) $user_id, (string) $type, 'whatsapp')) {
                return false;
            }
        } catch (Throwable $e) {
            // تعذر قراءة التفضيل لا يصادر قرارا لم يتخذ
        }

        $to = $this->taqdar_wa_model->phone_of((int) $user_id);
        if ($to === '') return false;   /* لا رقم: لا محاولة ولا سطر سجل */

        return (bool) $this->taqdar_wa_model->send_notice(
            $to, (string) $title, strip_tags((string) $text),
            array('purpose' => mb_substr((string) $type, 0, 24), 'user_id' => (int) $user_id));
    }
}
