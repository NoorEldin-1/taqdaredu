<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * إعدادات الطالب — القراءة والكتابة في موضع واحد.
 *
 * الصفحة قبل هذا الملف كانت تعرض قيما حرفية مكتوبة في العرض («مفعل»،
 * «مطفأ»، «العربية»، «ساعات صمت من 10 مساء إلى 7 صباحا») كأنها إعدادات
 * صاحب الحساب. وهذا أسوأ من نقص: النقص يرى فيطلب، والقيمة المفبركة تصدق
 * فلا تطلب. فكل قيمة تظهر الآن إما من `users` أو من جدولي التفضيلات
 * أدناه، وما لا مصدر له حذف ولم يلون بلون الإعداد.
 *
 * الجدولان من نصيب هذا الملف (بادئة tq_prefs_):
 *   tq_prefs_user    صف واحد لكل مستخدم: الوجه واللغة وساعات الصمت.
 *   tq_prefs_notify  صف لكل (مستخدم، نوع، قناة).
 *
 * والقنوات **ثلاث**: `inapp` لها جدول `notifications`، و`email` له
 * `Taqdar_mail_model`، و`whatsapp` له `Taqdar_wa_model` (TQ-WA-ALL).
 * ولا قناة «إشعار الجهاز» لأن المنصة لا تملك بنية دفع — ومفتاح لقناة
 * غير موجودة وعد لا إعداد.
 */
class Taqdar_settings_model extends CI_Model
{
    /** الحد الأقصى لصورة الحساب — نفس ما تعلنه الصفحة للمستخدم. */
    const IMAGE_MAX_BYTES = 2097152; // 2 ميجابايت

    /** حد تنبيه ولي الأمر حين لا يضبطه — وهو الرقم الذي كان ثابتا في الشاشة. */
    const ALERT_THRESHOLD_DEFAULT = 60;

    /** ساعة تذكير المذاكرة حين لا تضبط — الرابعة عصرا. */
    const STUDY_HOUR_DEFAULT = 16;

    /* ================================================================
       المخطط
       ================================================================ */

    /**
     * ينشئ الجدولين إن غابا. يستدعى من كل مدخل قراءة أو كتابة، ويحرسه
     * علم ساكن فلا يتكرر في الطلب الواحد.
     */
    public function ensure_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        if (!$this->db->table_exists('tq_prefs_user')) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `tq_prefs_user` (
                    `user_id`     INT(11) UNSIGNED NOT NULL,
                    `theme`       VARCHAR(10)  NOT NULL DEFAULT 'auto',
                    `language`    VARCHAR(32)  NOT NULL DEFAULT 'arabic',
                    `quiet_on`    TINYINT(1)   NOT NULL DEFAULT 0,
                    `quiet_from`  TINYINT(2)   NOT NULL DEFAULT 22,
                    `quiet_to`    TINYINT(2)   NOT NULL DEFAULT 7,
                    `updated_at`  INT(11)      NOT NULL DEFAULT 0,
                    PRIMARY KEY (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        /* والأعمدة الأربعة تضاف على الجدول القائم كذلك — من نصب المنصة
           قبل هذا العمل يبقى جدوله بلا `alert_threshold` ولا حقول
           التذكير، وأول قراءة عليها ترمي «Unknown column» فتبيض شاشة
           الإعدادات كلها. وهي قاعدة `ensure_progress_schema()` نفسها. */
        $add = array(
            /* TQ-NULLNUM — والفارغ غير الصفر هنا كذلك: `NULL` تعني «لم
               يختر أحد شيئا فخذ العام»، والصفر يعني «نبهني على كل شيء».
               وعمود لا يفرق بينهما يجعل كل ولي أمر لم يمر على الشاشة
               يقرأ حدا لم يضبطه. */
            'alert_threshold' => 'TINYINT(3) UNSIGNED DEFAULT NULL COMMENT "حد تنبيه ولي الأمر — 0..100، فارغ ⇒ العام"',
            'study_on'        => 'TINYINT(1) NOT NULL DEFAULT 0',
            'study_hour'      => 'TINYINT(2) NOT NULL DEFAULT 16',
            'study_days'      => 'VARCHAR(20) NOT NULL DEFAULT "" COMMENT "أيام الأسبوع بفواصل — 0 الأحد"',
        );
        foreach ($add as $col => $ddl) {
            try {
                if (!$this->db->field_exists($col, 'tq_prefs_user')) {
                    $this->db->query('ALTER TABLE `tq_prefs_user` ADD COLUMN `' . $col . '` ' . $ddl);
                }
            } catch (Throwable $e) {
                log_message('error', 'TQ-PREFS: تعذر تركيب العمود ' . $col . ' — ' . $e->getMessage());
            }
        }

        if (!$this->db->table_exists('tq_prefs_notify')) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `tq_prefs_notify` (
                    `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id`     INT(11) UNSIGNED NOT NULL,
                    `notify_type` VARCHAR(48)  NOT NULL,
                    `channel`     VARCHAR(16)  NOT NULL,
                    `enabled`     TINYINT(1)   NOT NULL DEFAULT 1,
                    `updated_at`  INT(11)      NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_user_type_channel` (`user_id`,`notify_type`,`channel`),
                    KEY `ix_user` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    /* ================================================================
       القوائم المرجعية
       ================================================================ */

    /**
     * أنواع التنبيه المعروضة — رمزها هو ما يمرره المرسل إلى allows().
     *
     * **والنوع قد يخص بوابة بعينها.** «التقرير الأسبوعي» خبر عن ابن فلا
     * معنى له عند طالب، و«تذكير التصحيح» عن طابور معلم فلا معنى له عند
     * ولي أمر. وعرض ما لا يخص القارئ يجعله يطفئ مفتاحا لا يصله شيء منه
     * أصلا ثم يشك في الشاشة كلها.
     *
     * والفرز بعنصر ثالث في الصف (`audience`) لا بقائمة ثانية: قائمتان
     * لشيء واحد تفترقان عند أول نوع يضاف — وهي علة TQ-SOLD-NAME نفسها.
     * و`null` يرد الكل كما كانت ترد قبل اليوم حرفا بحرف.
     *
     * @param string|null $role بوابة القارئ: student · teacher · parent
     */
    public function notify_types($role = null)
    {
        $all = array(
            /* المراجعة والمحطة حدثان في يوم الطالب وحده: لا يكتب لولي
               الأمر منهما إشعار (`notify_keys()` لا تعرفهما)، فمفتاحهما في
               شاشته مفتاح لا يحكم شيئا. */
            'review_due'        => array('تذكير المراجعة',   'حين يحين موعد مراجعة درس سبق',   'student'),
            'station_unlocked'  => array('فتح محطة جديدة',   'حين تفتح محطة تالية في مسارك',   'student'),
            'quiz_result'       => array('نتيجة اختبار',     'حين ترصد نتيجة اختبار أديته'),
            'purchase_confirmed'=> array('تأكيد الشراء',     'حين يسجل اشتراك أو دفعة على حسابك'),
            'session_confirmed' => array('تأكيد حصة',        'حين تثبت حصة بالطلب أو يتغير موعدها'),
            /* والاثنان التاليان كانا مفتاحين في شاشتين يحركان ولا يكتب
               بهما شيء: لا صف في `tq_prefs_notify` ولا فرع في `allows()`.
               فصارا نوعين كسائر الأنواع — يقرآن ويكتبان ويحكمان. */
            'weekly_digest'     => array('التقرير الأسبوعي', 'ملخص أسبوعي عن تقدم ابنك',       'parent'),
            'marking_due'       => array('تذكير التصحيح',    'حين ينتظر في طابور تصحيحك عمل طالب', 'teacher'),
        );

        /* **والفرز قبل الترجمة لا بعدها**: `tq_t_deep()` تترجم كل نص في
           الشجرة، فلو مر عليها الجمهور أولا لكفى مفتاح قاموس اسمه
           `parent` ليصير `'parent' !== $role` صادقا أبدا — فيختفي صف
           «التقرير الأسبوعي» من شاشة كل ولي أمر بلا خطأ في أي موضع. */
        if ($role !== null) {
            foreach ($all as $key => $t) {
                /* والنوع بلا جمهور معلن يخص الجميع — فالأنواع الخمسة
                   القديمة تبقى معروضة لكل بوابة كما كانت. */
                if (isset($t[2]) && $t[2] !== $role) unset($all[$key]);
            }
        }

        return tq_t_deep($all);
    }

    /**
     * بوابة صاحب الحساب — يفرز بها `notify_matrix()` و`save_alerts()` بلا
     * أن يمرر كل مستدع دوره.
     *
     * و`tq_role()` مساعد محمل تلقائيا؛ وغيابه لا يكسر شيئا: الرد `null`
     * يعني «اعرض الكل» وهو ما كان يقع قبل هذا العمل.
     */
    private function role_of($user_id)
    {
        if (!function_exists('tq_role')) return null;
        try {
            $r = tq_role((int) $user_id);
            return in_array($r, array('student', 'teacher', 'parent'), true) ? $r : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * القنوات القائمة فعلا.
     *
     * وواتساب يعرض **متى كان مضبوطا على هذه المنصة وحده**: عمود مربعات
     * لقناة لا رمز وصول لها وعد لا إعداد — يطفئه صاحبه ويظن أنه صنع
     * شيئا، أو يشعله فلا يصله شيء ويحسب العطل في حسابه.
     */
    public function notify_channels()
    {
        $out = array(
            'inapp' => t('داخل المنصة'),
            'email' => t('بريد إلكتروني'),
        );

        try {
            $this->load->model('taqdar_wa_model');
            if ($this->taqdar_wa_model->ready()) $out['whatsapp'] = t('واتساب');
        } catch (Throwable $e) {
            // قناة لا تقرأ لا تعرض
        }

        return $out;
    }

    /**
     * الافتراض حين لا يكون للمستخدم صف محفوظ.
     * وهو إعداده الساري فعلا حتى يغيره — لا قيمة تزيينية.
     */
    public function notify_defaults()
    {
        /* وواتساب يبدأ من حيث يبدأ البريد — القناتان تخرجان الشيء
           نفسه، فافتراضان مختلفان لهما يعنيان أن الطالب يقرأ نتيجته في
           بريده ولا يقرؤها في جواله ولا يعرف لماذا. ومن أراد التضييق
           ضيق بمربعه، ومن أراد إطفاء عائلة كاملة أطفأها من اللوحة.

           وما هو مطفأ في البريد يبقى مطفأ هنا (تذكير المراجعة، وفتح
           المحطة): ذانك تنبيهان يتكرران كل يوم، وقناة تطرق الباب بهما
           يوميا يسدها صاحبها — فتضيع معها إشعارات المال نفسها. */
        return array(
            'review_due'         => array('inapp' => 1, 'email' => 0, 'whatsapp' => 0),
            'station_unlocked'   => array('inapp' => 1, 'email' => 0, 'whatsapp' => 0),
            'quiz_result'        => array('inapp' => 1, 'email' => 1, 'whatsapp' => 1),
            'purchase_confirmed' => array('inapp' => 1, 'email' => 1, 'whatsapp' => 1),
            'session_confirmed'  => array('inapp' => 1, 'email' => 1, 'whatsapp' => 1),
            /* التقرير الأسبوعي بريد قبل كل شيء — هو رسالة صباح الأحد لا
               شارة في التطبيق. وتذكير التصحيح داخل المنصة وبريدا، ولا
               واتساب: ليس من أنواع المال ولا رموز التحقق. */
            'weekly_digest'      => array('inapp' => 1, 'email' => 1, 'whatsapp' => 0),
            'marking_due'        => array('inapp' => 1, 'email' => 1, 'whatsapp' => 0),
        );
    }

    /** الوجوه الثلاثة: يتبع الجهاز، أو يثبت فاتحا، أو داكنا. */
    public function themes()
    {
        return tq_t_deep(array(
            'auto'  => 'يتبع جهازي',
            'light' => 'فاتح دائما',
            'dark'  => 'داكن دائما',
        ));
    }

    /**
     * اللغات المتاحة — من `tq_languages()` وحدها (TQ-I18N).
     *
     * وكانت تشتق من **أعمدة جدول `language`**، وهو يحمل اليوم `english` و
     * `arabic` وقد يحمل غدا عمودا أنشأه `get_phrase()` وحده لأن مسؤولا فتح
     * لغة مرة ثم تركها: فتظهر في منتقي اللغة لغة **بلا قاموس واحد**، يختارها
     * الطالب فتعرض له الواجهة عربية كما هي ولا شيء يقول لماذا.
     * فالقائمة صارت ما نملك له ترجمة، لا ما بقي في المخطط من أثر.
     */
    public function languages()
    {
        $out = array();
        foreach (tq_languages() as $code => $meta) {
            $out[$code] = $meta['label'];
        }
        return $out;
    }

    /* ================================================================
       القراءة
       ================================================================ */

    /** تفضيلات المستخدم العامة، مع الافتراضات حين لا صف له. */
    public function prefs($user_id)
    {
        $this->ensure_schema();

        $row = $this->db->where('user_id', (int) $user_id)
                        ->get('tq_prefs_user')->row_array();

        $langs = $this->languages();
        $site  = function_exists('get_settings') ? (get_settings('language') ?: 'arabic') : 'arabic';
        if (!isset($langs[$site])) $site = key($langs);

        $out = array(
            'theme'      => 'auto',
            'language'   => $site,
            'quiet_on'   => 0,
            'quiet_from' => 22,
            'quiet_to'   => 7,
            /* حد التنبيه: دونه يقرأ ولي الأمر أن ابنه يحتاج التفاتة.
               والافتراض ستون لأنه هو الرقم الذي كان مكتوبا في التطبيق
               ثابتا («إتقان أقل من ٦٠٪») — فمن لم يضبط شيئا يقرأ ما كان
               يقرؤه حرفا بحرف. */
            'alert_threshold' => self::ALERT_THRESHOLD_DEFAULT,
            'study_reminder'  => array(
                'enabled'  => false,
                'hour'     => self::STUDY_HOUR_DEFAULT,
                /* الأحد = 0، فالأسبوع السعودي يبدأ به. والافتراض خمسة
                   أيام دراسة لا سبعة. */
                'weekdays' => array(0, 1, 2, 3, 4),
            ),
            'saved'      => false,
        );

        if ($row) {
            $out['theme']      = isset($this->themes()[$row['theme']]) ? $row['theme'] : 'auto';
            $out['language']   = isset($langs[$row['language']]) ? $row['language'] : $out['language'];
            $out['quiet_on']   = (int) $row['quiet_on'];
            $out['quiet_from'] = (int) $row['quiet_from'];
            $out['quiet_to']   = (int) $row['quiet_to'];
            $out['saved']      = true;

            /* والصف قد يسبق الأعمدة: `ensure_schema()` تضيفها الآن، لكن
               صفا قرئ من قاعدة لم تمر عليها بعد لا يحملها. */
            if (array_key_exists('alert_threshold', $row) && $row['alert_threshold'] !== null) {
                $out['alert_threshold'] = $this->clamp_pct($row['alert_threshold']);
            }
            if (array_key_exists('study_on', $row)) {
                $out['study_reminder'] = array(
                    'enabled'  => (bool) (int) $row['study_on'],
                    'hour'     => $this->clamp_hour($row['study_hour'], self::STUDY_HOUR_DEFAULT),
                    'weekdays' => $this->weekdays_in($row['study_days'], array(0, 1, 2, 3, 4)),
                );
            }
        }
        return $out;
    }

    /** حد التنبيه عدد صحيح في [0,100] — وما خرج عنه يقص لا يرفض. */
    private function clamp_pct($v)
    {
        return max(0, min(100, (int) $v));
    }

    private function clamp_hour($v, $fallback)
    {
        $h = (int) $v;
        return ($h >= 0 && $h <= 23) ? $h : (int) $fallback;
    }

    /**
     * أيام الأسبوع من نص بفواصل إلى قائمة أعداد مرتبة بلا تكرار.
     *
     * والنص الفارغ يرد `$fallback` لا مصفوفة فارغة: «لا يوم» تعني تذكيرا
     * لا يقع أبدا، ومن شغل المفتاح ولم يختر يوما يريد أيامه المعتادة.
     * ومن أراد إيقافه أطفأ `enabled` — وهو الحقل الذي يقول ذلك.
     */
    private function weekdays_in($raw, $fallback = array())
    {
        $out = array();
        foreach (explode(',', (string) $raw) as $d) {
            $d = trim($d);
            if ($d === '' || !ctype_digit($d)) continue;
            $d = (int) $d;
            if ($d >= 0 && $d <= 6) $out[$d] = $d;
        }
        if (!$out) return array_values($fallback);

        $out = array_values($out);
        sort($out);
        return $out;
    }

    /** مصفوفة [نوع][قناة] => 0|1 — المحفوظ يعلو الافتراض. */
    public function notify_matrix($user_id)
    {
        $this->ensure_schema();

        $matrix   = $this->notify_defaults();
        $channels = array_keys($this->notify_channels());
        $types    = $this->notify_types($this->role_of($user_id));

        /* والمصفوفة تقتصر على أنواع بوابته: مفتاح «تذكير التصحيح» في
           شاشة ولي أمر يطفأ ولا يمنع شيئا. */
        foreach (array_keys($matrix) as $type) {
            if (!isset($types[$type])) unset($matrix[$type]);
        }

        foreach (array_keys($types) as $type) {
            if (!isset($matrix[$type])) $matrix[$type] = array();
            foreach ($channels as $ch) {
                if (!isset($matrix[$type][$ch])) $matrix[$type][$ch] = 0;
            }
        }

        $rows = $this->db->where('user_id', (int) $user_id)->get('tq_prefs_notify')->result_array();
        foreach ($rows as $r) {
            if (isset($matrix[$r['notify_type']][$r['channel']])) {
                $matrix[$r['notify_type']][$r['channel']] = (int) $r['enabled'];
            }
        }
        return $matrix;
    }

    /**
     * هل يسمح المستخدم بهذا التنبيه على هذه القناة؟
     *
     * هذه هي البوابة التي يجب أن يمر بها كل مرسل (المنشئ في
     * `notifications` ومرسل البريد في `Email_model`) وإلا بقي المفتاح
     * تفضيلا محفوظا لا أثر له. النوع المجهول يسمح به — فالإعداد يمنع
     * ما عرض على صاحبه، لا ما لم يعرض عليه قط.
     */
    public function allows($user_id, $notify_type, $channel)
    {
        $notify_type = $this->pref_key_of($notify_type);

        $types = $this->notify_types();
        if (!isset($types[$notify_type])) return true;

        $matrix = $this->notify_matrix($user_id);
        if (!isset($matrix[$notify_type][$channel])) return true;

        return (bool) $matrix[$notify_type][$channel];
    }

    /**
     * نوع `notifications.type` إلى مفتاح التفضيل الذي يحكمه.
     *
     * TQ-PREF-BRIDGE. وبلا هذا الجسر كانت مربعات شاشة الإعدادات **زينة
     * لا تحكم شيئا**: مفاتيحها خمسة (`quiz_result` · `purchase_confirmed`
     * …) وما يمرره المرسل أنواع أخرى (`subscription` · `session` ·
     * `exam_result`)، فلا يطابق مفتاح نوعا واحدا و`allows()` ترد `true`
     * لكل شيء. أي أن الطالب يطفئ «تأكيد الشراء» فتظل تصله تأكيدات
     * الشراء، ولا شيء يخطئ.
     *
     * والاتجاه واحد: كل نوع يقع على مفتاح أو على نفسه. وما لا مفتاح له
     * يسمح به — الإعداد يمنع ما عرض على صاحبه، لا ما لم يعرض عليه قط.
     */
    public function pref_key_of($type)
    {
        $map = array(
            // الشراء والمال
            'subscription' => 'purchase_confirmed',
            'invoice'      => 'purchase_confirmed',
            'payment'      => 'purchase_confirmed',
            'course_purchase' => 'purchase_confirmed',
            'bundle_purchase' => 'purchase_confirmed',

            // الحصص
            'session'           => 'session_confirmed',
            'session_request'   => 'session_confirmed',

            // النتائج
            'exam_result'      => 'quiz_result',
            'station_failed'   => 'quiz_result',
            'placement_result' => 'quiz_result',

            // المراجعة والمحطات
            'review_due'       => 'review_due',
            'station_unlocked' => 'station_unlocked',

            /* التقرير الأسبوعي: يكتب بنوع `weekly_report` ومفتاحه في الشاشة
               `weekly_digest` — وبلا الجسر كان إطفاؤه لا يوقف شيئا. */
            'weekly_report'    => 'weekly_digest',
        );

        $t = (string) $type;
        return isset($map[$t]) ? $map[$t] : $t;
    }

    /**
     * هل نحن داخل ساعات صمت المستخدم الآن؟ يستعمل مع allows() عند الإرسال.
     * النافذة قد تعبر منتصف الليل، فمن 22 إلى 7 نافذة واحدة لا نافذتان.
     */
    public function in_quiet_hours($user_id, $hour = null)
    {
        $p = $this->prefs($user_id);
        if (empty($p['quiet_on'])) return false;

        if ($hour === null) $hour = (int) date('G');
        $from = (int) $p['quiet_from'];
        $to   = (int) $p['quiet_to'];
        if ($from === $to) return false;

        return ($from < $to) ? ($hour >= $from && $hour < $to)
                             : ($hour >= $from || $hour < $to);
    }

    /* ================================================================
       الكتابة — كل دالة تعيد ok/errors/message
       ================================================================ */

    /**
     * موزع واحد لكل النماذج، فيبقى المتحكم سطورا معدودة والقرار هنا.
     * @return array('ok'=>bool,'message'=>string,'errors'=>array,'section'=>string)
     */
    public function handle($user_id, $action)
    {
        switch ($action) {
            case 'profile':  return $this->save_profile($user_id);
            case 'password': return $this->save_password($user_id);
            case 'alerts':   return $this->save_alerts($user_id);
            case 'prefs':    return $this->save_prefs($user_id);
            case 'teacher':  return $this->save_teacher_public($user_id);
        }
        return $this->fail('طلب غير معروف.', 'profile');
    }

    private function ok($message, $section)
    {
        return array('ok' => true, 'message' => $message, 'errors' => array(), 'section' => $section);
    }

    private function fail($errors, $section)
    {
        if (!is_array($errors)) $errors = array($errors);
        return array('ok' => false, 'message' => '', 'errors' => $errors, 'section' => $section);
    }

    /* ---- الملف الشخصي ---------------------------------------------- */

    public function save_profile($user_id)
    {
        $user_id = (int) $user_id;
        $in      = $this->input;

        $first = trim((string) $in->post('first_name', true));
        $last  = trim((string) $in->post('last_name', true));
        $email = trim((string) $in->post('email', true));
        $phone = trim((string) $in->post('phone', true));

        /* TQ-LRS-NID — رقم الهوية الوطنية / الإقامة.

           **اختياري**: هو ما تعرف به الجهة المتعلم في رسائل xAPI، ومن
           لم يكتبه يعرف ببريده. وجعله إلزاميا هنا يعني ان كل من سجل
           قبل اليوم لا يستطيع حفظ ملفه حتى يكتبه — وهو ثمن لا يدفعه
           حقل اختياري.

           والفحص يقبل الفراغ ويرفض الخطأ: عشرة أرقام تبدأ بواحد
           (سعودي) أو اثنين (مقيم). ورقم ناقص يمر صامتا يصل الجهة
           فيرفض هناك بعد شهر ولا يعرف صاحبه. */
        $nid = preg_replace('/\D/', '', (string) $in->post('national_id', true));

        $errors = array();
        if ($first === '')                          $errors[] = 'اكتب اسمك الأول.';
        if (mb_strlen($first) > 120)                $errors[] = 'الاسم الأول أطول من المسموح.';
        if (mb_strlen($last) > 120)                 $errors[] = 'الاسم الأخير أطول من المسموح.';
        if ($email === '')                          $errors[] = 'اكتب بريدك الإلكتروني.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'صيغة البريد غير صحيحة — مثال: name@example.com';
        elseif (mb_strlen($email) > 50)             $errors[] = 'البريد أطول من الحقل المتاح (50 حرفا).';
        /* TQ-PHONE-INTL — الرقم يفحص في دولته ويخزن `+<رمز><وطني>`.
           كان الفحص «أرقاما و+ و- ومسافات، من ست إلى خمس وعشرين» —
           وهو يقبل `123456` ويخزنه كما جاء، فيقرأه `to_e164()` رقما
           غير صالح ولا يخرج له واتساب أبدا ولا يقول أحد لماذا. */
        if ($phone !== '') {
            $ph = tq_phone_check($phone, $in->post('phone_cc', true));
            if (!$ph['ok']) $errors[] = $ph['error'];
            else            $phone = $ph['e164'];
        }

        if (!$errors) {
            $taken = $this->db->where('email', $email)->where('id !=', $user_id)
                              ->count_all_results('users');
            if ($taken > 0) $errors[] = 'هذا البريد مسجل لحساب آخر — اختر بريدا غيره.';
        }

        $image_code = null;
        if (!$errors && !empty($_FILES['user_image']['name'])) {
            $img = $this->store_image($user_id);
            if (!$img['ok']) $errors[] = $img['error'];
            else             $image_code = $img['code'];
        }

        if ($nid !== '' && !preg_match('/^[0-9]{10}$/', $nid)) {
            $errors[] = 'رقم الهوية عشرة أرقام.';
        }

        if ($errors) return $this->fail($errors, 'profile');

        $data = array(
            'first_name'    => $first,
            'last_name'     => $last,
            'email'         => $email,
            'phone'         => $phone,
            'last_modified' => time(),
        );
        if ($image_code !== null) $data['image'] = $image_code;

        /* والعمود يفحص قبل الكتابة: نسخة لم يمر عليها `ensure_columns()`
           بعد ترمي «Unknown column» على من يحفظ ملفه. */
        try {
            if ($this->db->field_exists('national_id', 'users')) $data['national_id'] = $nid;
        } catch (Throwable $e) { $this->db->reset_query(); }

        $this->db->where('id', $user_id)->update('users', $data);

        return $this->ok('حفظت بيانات ملفك.', 'profile');
    }

    /* ---- الملف العام للمعلم ---------------------------------------- */

    /**
     * ما يراه الناس عن المعلم في صفحته العامة: صفته ونبذته.
     *
     * منفصل عن `save_profile()` عمدا. ذاك يكتب الهوية (اسم وبريد وجوال)
     * وهي بيانات حساب لا تعرض لأحد، وهذه تكتب ما ينشر على `/instructor/<id>`
     * ويقرؤه ولي أمر يختار لابنه معلما. وخلطهما في نموذج واحد يجعل تعديل
     * رقم الجوال ينشر نبذة نصف مكتوبة.
     *
     * والحقول من `users` القائمة (`title` و`biography`)، ولا يخترع لها جدول.
     * أما `is_public` فلا تكتب من هنا: ظهور المعلم على الموقع قرار إدارة
     * لا تبديل مفتاح في إعداداته.
     */
    public function save_teacher_public($user_id)
    {
        $user_id = (int) $user_id;
        $title   = trim((string) $this->input->post('title', true));
        $bio     = trim((string) $this->input->post('biography', true));

        $errors = array();
        if (mb_strlen($title) > 160) $errors[] = 'الصفة أطول من المسموح (160 حرفا).';
        if (mb_strlen($bio) > 1500)  $errors[] = 'النبذة أطول من المسموح (1500 حرف).';
        if ($errors) return $this->fail($errors, 'teacher');

        /* النص يعرض في صفحة عامة، فيجرد من الوسوم عند الحفظ لا عند العرض:
           تجريد عند العرض ينسى في شاشة، وتجريد عند الحفظ يقع مرة. */
        $this->db->where('id', $user_id)->update('users', array(
            'title'         => strip_tags($title),
            'biography'     => strip_tags($bio),
            'last_modified' => time(),
        ));

        return $this->ok('حفظت ما يظهر في صفحتك العامة.', 'teacher');
    }

    /**
     * صورة الحساب. تفحص كصورة حقيقية لا بامتدادها، وتعاد ترميزا إلى JPEG
     * فلا يمر ملف يحمل شفرة داخل ترويسة صورة. والاسم رمز عشوائي كما يفعل
     * باقي السكربت (uploads/user_image/<code>.jpg).
     *
     * وهي عامة لا خاصة: شاشة «إضافة معلم» في اللوحة ترفع الصورة نفسها
     * إلى المجلد نفسه، ونسخة ثانية من فحص الصورة وإعادة ترميزها تفترق
     * عن هذه عند أول تشديد يصيب إحداهما — فتقبل اللوحة ما يرده الطالب.
     */
    public function store_image($user_id)
    {
        $f = $_FILES['user_image'];

        if (!empty($f['error']) && $f['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'error' => 'تعذر رفع الصورة — أعد المحاولة.');
        }
        if ($f['size'] > self::IMAGE_MAX_BYTES) {
            return array('ok' => false, 'error' => 'الصورة أكبر من 2 ميجابايت — اختر صورة أصغر.');
        }
        if (!is_uploaded_file($f['tmp_name'])) {
            return array('ok' => false, 'error' => 'ملف الصورة غير صالح.');
        }

        $info = @getimagesize($f['tmp_name']);
        $allowed = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP);
        if (!$info || !in_array($info[2], $allowed, true)) {
            return array('ok' => false, 'error' => 'الصورة يجب أن تكون JPG أو PNG أو WebP.');
        }

        $dir = rtrim(FCPATH, '/') . '/uploads/user_image/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $code = md5($user_id . '-' . microtime(true) . '-' . mt_rand());
        $dest = $dir . $code . '.jpg';

        $made = false;
        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $raw = @file_get_contents($f['tmp_name']);
            $im  = $raw ? @imagecreatefromstring($raw) : false;
            if ($im) {
                $w = imagesx($im); $h = imagesy($im);
                $flat = imagecreatetruecolor($w, $h);
                $white = imagecolorallocate($flat, 255, 255, 255);
                imagefilledrectangle($flat, 0, 0, $w, $h, $white);
                imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
                $made = @imagejpeg($flat, $dest, 88);
                imagedestroy($im);
                imagedestroy($flat);
            }
        }
        if (!$made && !@move_uploaded_file($f['tmp_name'], $dest)) {
            return array('ok' => false, 'error' => 'تعذر حفظ الصورة على الخادم.');
        }
        @chmod($dest, 0644);

        // الصورة القديمة تحذف بنسختيها حتى لا تبقى نسخة مصغرة لصورة استبدلت.
        $old = (string) $this->db->select('image')->where('id', (int) $user_id)
                                 ->get('users')->row('image');
        if ($old !== '' && $old !== $code) {
            @unlink($dir . $old . '.jpg');
            @unlink($dir . 'optimized/' . $old . '.jpg');
        }

        return array('ok' => true, 'code' => $code);
    }

    /* ---- كلمة المرور ------------------------------------------------ */

    public function save_password($user_id)
    {
        $user_id = (int) $user_id;
        $cur     = (string) $this->input->post('current_password');
        $new     = (string) $this->input->post('new_password');
        $again   = (string) $this->input->post('confirm_password');

        /* TQ-QUICK-BUY — حساب أنشئ من شاشة الدفع كلمته عشوائية لا يعرفها
           صاحبه، فطلب «الحالية» منه باب مغلق. ولا يضعف ذلك شيئا: شاشة
           الإكمال (`/account/complete`) تضعها له بجلسته نفسها. */
        $this->load->model('taqdar_signup_model');
        $quick = $this->taqdar_signup_model->is_quick($user_id);

        $errors = array();
        if ($cur === '' && !$quick)      $errors[] = 'اكتب كلمة المرور الحالية.';
        if (mb_strlen($new) < 8)         $errors[] = 'اجعل كلمة المرور الجديدة ثمانية محارف فأكثر.';
        if ($new !== $again)             $errors[] = 'الحقلان لا يتطابقان — أعد كتابة التأكيد.';
        if ($new !== '' && $new === $cur) $errors[] = 'الجديدة مطابقة للحالية — اختر غيرها.';

        /* التحقق يمر بدالة المنصة لا بمقارنة sha1 مباشرة: الدخول يرقي
           كل تلبيدة قديمة إلى password_hash عند أول نجاح، فمقارنة sha1
           الصريحة تفشل لكل من سجل دخوله مرة — ويقال له إن كلمته خطأ وهي
           صحيحة. والكتابة بالتلبيدة الحديثة نفسها لا بالقديمة. */
        if (!$errors && !$quick) {
            $row = $this->db->select('password')->where('id', $user_id)->get('users')->row_array();
            $good = $row && (function_exists('tq_password_matches')
                ? tq_password_matches($cur, $row['password'])
                : hash_equals((string) $row['password'], sha1($cur)));
            if (!$good) $errors[] = 'كلمة المرور الحالية غير صحيحة.';
        }
        if ($errors) return $this->fail($errors, 'security');

        $this->db->where('id', $user_id)->update('users', array(
            'password'      => function_exists('tq_password_hash') ? tq_password_hash($new) : sha1($new),
            'last_modified' => time(),
        ));

        /* TQ-SOCIAL-LASTDOOR — ومن وضع كلمة بيده صار له باب ثان.
           الحساب المنشأ بجوجل أو أبل كلمته عشوائية لا يعرفها أحد، ولا
           يميزها هاشها عن هاش حقيقي. فالعلامة أثر لا عمود، وتكتب هنا
           وفي «نسيت كلمة المرور» — وبها وحدها يسمح بفصل آخر ربط. */
        $this->load->model('taqdar_social_model');
        $this->taqdar_social_model->mark_own_password($user_id);

        return $this->ok('غيرت كلمة مرورك.', 'security');
    }

    /* ---- التنبيهات --------------------------------------------------- */

    public function save_alerts($user_id)
    {
        $this->ensure_schema();

        $user_id  = (int) $user_id;
        $posted   = $this->input->post('notify');
        if (!is_array($posted)) $posted = array();
        $now      = time();
        $channels = array_keys($this->notify_channels());

        foreach (array_keys($this->notify_types($this->role_of($user_id))) as $type) {
            foreach ($channels as $ch) {
                $on = !empty($posted[$type][$ch]) ? 1 : 0;
                $this->db->replace('tq_prefs_notify', array(
                    'user_id'     => $user_id,
                    'notify_type' => $type,
                    'channel'     => $ch,
                    'enabled'     => $on,
                    'updated_at'  => $now,
                ));
            }
        }

        $quiet_on   = $this->input->post('quiet_on') ? 1 : 0;
        $quiet_from = (int) $this->input->post('quiet_from');
        $quiet_to   = (int) $this->input->post('quiet_to');
        if ($quiet_from < 0 || $quiet_from > 23) $quiet_from = 22;
        if ($quiet_to   < 0 || $quiet_to   > 23) $quiet_to   = 7;

        $this->upsert_prefs($user_id, array(
            'quiet_on'   => $quiet_on,
            'quiet_from' => $quiet_from,
            'quiet_to'   => $quiet_to,
        ));

        return $this->ok('حفظت تفضيلات تنبيهاتك.', 'alerts');
    }

    /* ---- التفضيلات العامة -------------------------------------------- */

    public function save_prefs($user_id)
    {
        $this->ensure_schema();

        $user_id = (int) $user_id;
        /* الوضع الليلي أزيل — الوجه واحد فاتح. يثبت ولا يقرأ من المدخل. */
        $theme   = 'auto';
        $lang    = (string) $this->input->post('language', true);

        $langs = $this->languages();
        if (!isset($this->themes()[$theme])) $theme = 'auto';
        if (!isset($langs[$lang]))           return $this->fail('لغة غير متاحة.', 'prefs');

        $data = array('theme' => $theme, 'language' => $lang);

        /* **وما لم يرسل لا يمس** (TQ-TAB-WIPE): شاشة اللغة ترسل `language`
           وحدها، وكتابة الحقول كلها في كل حفظ تمحو حد التنبيه وتذكير
           المذاكرة على أول تبديل للغة — ولا شيء يقول إنهما ذهبا. */
        $th = $this->input->post('alert_threshold');
        if ($th !== null && $th !== '') {
            if (!is_numeric($th) || (int) $th < 0 || (int) $th > 100) {
                return $this->fail('حد التنبيه رقم بين صفر ومئة.', 'prefs');
            }
            $data['alert_threshold'] = (int) $th;
        }

        /* التذكير يصل بثلاثة حقول أو بلا واحد، ومفتاحه هو الذي يقرر:
           `study_reminder_on` يرسل دائما حين ترسل الشاشة قسمها — وبلا ذلك
           لا يستطيع أحد أن يطفئه أبدا (خانة غير معلمة لا ترسل). */
        $on = $this->input->post('study_reminder_on');
        if ($on !== null) {
            $data['study_on'] = (int) ((string) $on !== '0' && $on !== false && $on !== '');

            $hour = $this->input->post('study_reminder_hour');
            if ($hour !== null && $hour !== '') {
                if (!is_numeric($hour) || (int) $hour < 0 || (int) $hour > 23) {
                    return $this->fail('ساعة التذكير رقم بين صفر وثلاثة وعشرين.', 'prefs');
                }
                $data['study_hour'] = (int) $hour;
            }

            $days = $this->input->post('study_reminder_weekdays');
            if ($days !== null) {
                if (is_string($days)) $days = explode(',', $days);
                $clean = array();
                foreach ((array) $days as $d) {
                    if (!is_numeric($d)) continue;
                    $d = (int) $d;
                    if ($d >= 0 && $d <= 6) $clean[$d] = $d;
                }
                if (!$clean && (array) $days) {
                    return $this->fail('أيام التذكير أرقام من صفر (الأحد) إلى ستة.', 'prefs');
                }
                if ($clean) {
                    sort($clean);
                    $data['study_days'] = implode(',', $clean);
                }
            }
        }

        $this->upsert_prefs($user_id, $data);

        /* اللغة إعداد يسري فورا: الجلسة هي ما يقرؤه get_phrase واشتقاق dir.
           والكتابة مشروطة بوجود الجلسة أصلا — فواجهة البرمجة تنادي هذه
           الدالة نفسها وهي **بلا جلسة عمدا** (`Api_v1` لا يحمل المكتبة،
           والتطبيق لا يحمل كعكة). وبلا الشرط كان النداء يرمي
           `Call to a member function set_userdata() on null` فيرد 500 صفحة
           HTML على طلب حفظ نجح فعلا في القاعدة.
           والصف محفوظ في الحالين، وهو المرجع؛ والجلسة نسخة عاجلة لطلب
           الويب وحده.

           والفحص على `get_instance()` لا على `$this`: `CI_Model` يعرف
           `__get` ولا يعرف `__isset`، فـ`isset($this->session)` كاذبة
           **دائما** — حتى في الويب حيث الجلسة محملة. والمتحكم يحمل
           مكتباته خصائص حقيقية، فالفحص عليه يقول الحقيقة. */
        $CI = get_instance();
        if ($CI && isset($CI->session)) {
            $CI->session->set_userdata('language', $lang);
        }

        return $this->ok('حفظت تفضيلاتك.', 'prefs');
    }

    /**
     * يثبت لغة الحساب — يناديه مبدل اللغة في ترويسة اللوحات الأربع.
     *
     * وهو `save_prefs()` نفسه منزوعا عنه قراءة `$_POST` ورسالة الشاشة:
     * المبدل يكتب سطرا واحدا ولا يعرض نموذجا، ونداؤه `save_prefs()` كان
     * سيمحو تفضيلات أخرى لأنها لا ترسل معه.
     */
    public function set_language($user_id, $lang)
    {
        $langs = $this->languages();
        $lang  = strtolower(trim((string) $lang));
        if (!isset($langs[$lang])) return false;

        $this->upsert_prefs((int) $user_id, array('language' => $lang));
        return true;
    }

    /** كتابة جزئية في صف التفضيلات — تنشئه إن لم يكن. */
    private function upsert_prefs($user_id, $data)
    {
        $this->ensure_schema();
        $user_id = (int) $user_id;
        $data['updated_at'] = time();

        $exists = $this->db->where('user_id', $user_id)->count_all_results('tq_prefs_user');
        if ($exists) {
            $this->db->where('user_id', $user_id)->update('tq_prefs_user', $data);
        } else {
            $base = array(
                'user_id'    => $user_id,
                'theme'      => 'auto',
                'language'   => function_exists('get_settings') ? (get_settings('language') ?: 'arabic') : 'arabic',
                'quiet_on'   => 0,
                'quiet_from' => 22,
                'quiet_to'   => 7,
            );
            $this->db->insert('tq_prefs_user', array_merge($base, $data));
        }
    }
}
