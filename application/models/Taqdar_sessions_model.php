<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * محرك الحصص الخاصة: إتاحة المعلم، وطلب الطالب، وثمنه، ونصيب معلمه.
 *
 * الجدولان الحقيقيان في taqd_lms هما `availability_slots` و`tutoring_sessions`.
 * ولا وجود لـ `teacher_availability` ولا لـ `session_requests` — كانت الشاشتان
 * تنتظران اسمين لم ينشآ قط، والجدولان أمامهما. فالتسمية هنا تتبع القاعدة،
 * والقاعدة وحدها.
 *
 * ---------------------------------------------------------------------
 * TQ-SESSION-PAY — والحصة صارت تباع، وكانت تعطى.
 *
 * كان كل ما في هذه الطبقة موعدا يطلب ويؤكد: لا سعر، ولا فاتورة، ولا قيد
 * في دفتر أحد. فمعلم يدرس عشرين حصة في شهر لا يقرأ عنها ريالا في محفظته،
 * والمنصة تشغل بنيتها بلا إيراد، ولا شاشة في اللوحة تقول بكم تباع الحصة —
 * لأنها لم تكن تباع.
 *
 * ودورة الحياة الآن ست محطات، وكل محطة تجيب سؤالا عن المال:
 *
 *   requested         الطالب طلب — ولا شيء دفع، ولا شيء استحق
 *        │ يؤكد المعلم ويضع رابط اللقاء
 *        ▼
 *   awaiting_payment  الموعد محجوز له وحده، ومهلة الدفع تعد
 *        │ يدفع الطالب (تاب أو تحويل)
 *        ▼
 *   confirmed         دفع وحجز — والمال عند المنصة، ولا شيء في دفتر المعلم
 *        │ يحل الموعد
 *        ▼
 *   live              جارية — والرابط يفتح
 *        │ يعلن المعلم انتهاءها (أو تنتهي بمهلتها)
 *        ▼
 *   completed         انعقدت — **وهنا وحدها يقيد نصيب المعلم**، والرابط يموت
 *
 * والمخارج ثلاثة: `declined` يعتذر المعلم فلا مال تحرك أصلا، و`expired`
 * تمضي مهلة الرد أو مهلة الدفع فيعود الموعد مفتوحا، و`refunded` قرار
 * إدارة يعكس ما قيد إن كان قيد.
 *
 * **ولماذا يدفع بعد التأكيد لا قبله؟** لأن المعلم قد يعتذر، والاعتذار هو
 * الحال الشائعة لا النادرة — فالدفع أولا يعني استرداد بطاقة في كل اعتذار،
 * وكل استرداد رسم ومهلة أيام وسؤال «أين مالي؟». والشاشة تعد الطالب بهذا
 * حرفا («ولا يخصم مبلغ الحصة إلا بعد تأكيد المعلم») منذ كتبت، فبقي الوعد
 * وصار له سند.
 *
 * **ولماذا يقيد نصيب المعلم عند الانتهاء لا عند الدفع؟** لأن ما بيع وقت
 * لم يمض بعد. بيع الباقة يقيد حين يدفع لأن ما بيع محتوى قائم يسلم نفسه؛
 * والحصة لا تسلم نفسها. ولو قيدت عند الدفع لصار معلم غاب عن حصته يملك
 * مالها في دفتره، ولصار كل تخلف عكس قيد كان ينبغي ألا يكتب.
 *
 * ---------------------------------------------------------------------
 * والفترة **إتاحة** لا حصة: «مساء» خمس ساعات، وبيعها بسعر واحد يعني أن
 * معلما يبيع خمس ساعات بثمن ساعة أو أن طالبا يشتري ساعة بثمن خمس. فالفترة
 * تفرش إلى مواعيد بطول `tq_session_minutes` (افتراضه ساعة)، وشبكة المعلم
 * لا تتغير: يعلم «الأحد مساء» فيولد له خمسة مواعيد يحجزها خمسة طلاب.
 * وكان الموعد الواحد يحجز الفترة كلها فيرد الأربعة الباقين.
 *
 * الأسبوع يبدأ الأحد — السوق سعودي، و`date('w')` يعطي ٠ للأحد فيوافق الشبكة.
 *
 * والملكية تفحص في الاستعلام نفسه: `teacher_id = <المعلم الحالي>` شرط في
 * كل تحديث، لا إخفاء زر.
 */
class Taqdar_sessions_model extends CI_Model
{
    /**
     * طول النافذة التي تفرش فيها الشبكة مواعيد.
     *
     * وكانت ثمانية أياما تفرش **عند الحفظ وحده**، فما فرش ينفد بعد أسبوع
     * ولا شيء يجدده: معلم حفظ أوقاته مرة يظهر أسبوعا ثم يختفي من شاشة
     * الطالب بلا أن يغلق وقته أحد. والقاعدة الآن دائمة (`tq_teacher_windows`)
     * والفرش يتجدد كل ساعة من `lifecycle_tick()`، فأربعة عشر يوما تعني
     * أن الطالب يحجز أسبوعين قدما لا أن الشبكة تنفد.
     */
    const WINDOW_DAYS = 14;

    /** إصدار بنية الحصص — يمنع إعادة فحص الأعمدة في كل طلب. */
    const SCHEMA_V = '5';

    /** أقصر نافذة إتاحة تقبل، بالدقائق — أقل منها لا تسع حصة. */
    const MIN_WINDOW_MIN = 15;

    /**
     * مضيفو اللقاء المقبولون.
     *
     * قائمة بيضاء لا فحص «هل هو رابط»: الحقل يكتبه المعلم ويفتحه الطالب
     * بضغطة، فرابط حر هنا يجعل شاشة المنصة بابا إلى أي موقع — والطالب
     * قاصر في أغلب الحالات. والنطاقات هنا هي التي تشغل حصصا فعلا.
     */
    const MEET_HOSTS = array(
        'meet.google.com',
        'zoom.us',
        'teams.microsoft.com',
        'teams.live.com',
    );

    /** الحالات التي تشغل الموعد فلا يحجزه غير صاحبه. */
    public static $LIVE_STATES = array('requested', 'awaiting_payment', 'confirmed', 'live');

    /* =====================================================================
       البنية
       ===================================================================== */

    /**
     * ينشئ أعمدة المال ودورة الحياة عند أول استعمال.
     *
     * `tutoring_sessions.room_id` قائم من قبل وهو معرف غرفة BigBlueButton
     * (انظر `bbb_meetings`) — لا رابط لقاء خارجي. وحشر رابط فيه يخلط مصدرين
     * في عمود واحد، فيقرأ من ينتظر معرف غرفة عنوانا كاملا. فالعمود مستقل.
     *
     * والسعر والنسبة والنصيب **تجمد على الصف** وقت الطلب: تعديل التسعيرة
     * غدا لا يغير ثمن ما طلب اليوم ولا نصيب من درسه. وهو المبدأ نفسه الذي
     * تنسخ به `subscription_items` نطاق الباقة وقت التفعيل.
     */
    public function install_schema($force = false)
    {
        static $done = false;
        if ($done && !$force) return;
        $done = true;

        if (!$this->db->table_exists('tutoring_sessions')) return;

        if (!$force) {
            $v = $this->db->where('key', 'tq_session_schema_v')->get('settings')->row_array();
            if ($v && (string) $v['value'] === self::SCHEMA_V) return;
        }

        /* MariaDB تقبل `IF NOT EXISTS` على الأعمدة، فالترقية مأمونة التكرار
           بلا قراءة `list_fields` في كل مرة. */
        $cols = array(
            'meet_url'              => 'VARCHAR(512) NULL DEFAULT NULL',
            'price_halalas'         => 'BIGINT NOT NULL DEFAULT 0',
            'teacher_percent'       => 'DECIMAL(5,2) NOT NULL DEFAULT 0',
            'teacher_share_halalas' => 'BIGINT NOT NULL DEFAULT 0',
            'invoice_id'            => 'INT(10) UNSIGNED NOT NULL DEFAULT 0',
            'pay_deadline'          => 'DATETIME NULL DEFAULT NULL',
            'paid_at'               => 'DATETIME NULL DEFAULT NULL',
            'confirmed_at'          => 'DATETIME NULL DEFAULT NULL',
            'started_at'            => 'DATETIME NULL DEFAULT NULL',
            'completed_at'          => 'DATETIME NULL DEFAULT NULL',
            'credited_at'           => 'DATETIME NULL DEFAULT NULL',
            'cancel_reason'         => 'VARCHAR(255) NULL DEFAULT NULL',
            'created_at'            => 'DATETIME NULL DEFAULT NULL',
            'updated_at'            => 'DATETIME NULL DEFAULT NULL',
        );
        foreach ($cols as $name => $def) {
            $this->try_sql('ALTER TABLE `tutoring_sessions` ADD COLUMN IF NOT EXISTS `' . $name . '` ' . $def);
        }

        /* الحالة الثامنة — «أكد المعلم وينتظر الدفع». وبلا حالة مستقلة لها
           يقرأ كل ما في المنصة `confirmed` على أنها «قائمة ومدفوعة»:
           الرابط يفتح، والموعد يحجز، والدفتر ينتظر انتهاء حصة لم تشتر. */
        $this->try_sql(
            "ALTER TABLE `tutoring_sessions` MODIFY COLUMN `status`
             ENUM('requested','awaiting_payment','confirmed','declined','expired','live','completed','refunded')
             NOT NULL DEFAULT 'requested'"
        );

        $this->try_sql('ALTER TABLE `tutoring_sessions` ADD INDEX `idx_se_invoice` (`invoice_id`)');
        $this->try_sql('ALTER TABLE `tutoring_sessions` ADD INDEX `idx_se_deadline` (`status`,`pay_deadline`)');

        /* تسعيرة المعلم الواحد — استثناء لا قاعدة: العمود فارغ يعني
           «خذ التسعيرة العامة»، لا «صفر». فمن لم يحدد له شيء لا يبيع
           بلا ثمن، وهو الفرق الذي يضيع لو كتب صفر افتراضا. */
        $this->try_sql('ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `tq_session_price` BIGINT NULL DEFAULT NULL');
        $this->try_sql('ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `tq_session_percent` DECIMAL(5,2) NULL DEFAULT NULL');

        /* الصفوف القديمة بلا `created_at`: تؤخذ من موعدها لا تترك فارغة —
           مهلة الرد تحسب منه، وصف بلا تاريخ إنشاء لا تنتهي مهلته أبدا. */
        $this->try_sql(
            'UPDATE `tutoring_sessions` t
          LEFT JOIN `availability_slots` a ON a.`id` = t.`slot_id`
                SET t.`created_at` = COALESCE(a.`starts_at`, NOW())
              WHERE t.`created_at` IS NULL'
        );

        /* ---- TQ-SESSION-GRID — القاعدة الدائمة، والصف على الموعد -------
           `availability_slots` صف لموعد بعينه في يوم بعينه، وهو **حاصل**
           لا قاعدة: ينفد بمضي الأيام. والقاعدة التي يكتبها المعلم أسبوعية
           دائمة — «الأحد ١٠:٠٠ إلى ١٤:٠٠ للصف الثالث» — فلها جدولها،
           ومنها يفرش الموعد في كل دورة كرون.

           و`grade_id` على **الموعد** لا على النافذة وحدها: الموعد يحجز
           ويجمد، فتعديل النافذة غدا لا يغير صف حصة طلبت اليوم. وهو مبدأ
           تجميد السعر نفسه. */
        $this->try_sql(
            'CREATE TABLE IF NOT EXISTS `tq_teacher_windows` (
               `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
               `teacher_id` INT(10) UNSIGNED NOT NULL,
               `dow`        TINYINT(1) NOT NULL,
               `start_min`  SMALLINT(4) NOT NULL,
               `end_min`    SMALLINT(4) NOT NULL,
               `grade_id`   INT(10) UNSIGNED NOT NULL DEFAULT 0,
               `created_at` DATETIME NULL DEFAULT NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_win` (`teacher_id`,`dow`,`start_min`),
               KEY `idx_win_teacher` (`teacher_id`),
               KEY `idx_win_grade` (`grade_id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->try_sql('ALTER TABLE `availability_slots` ADD COLUMN IF NOT EXISTS `grade_id` INT(10) UNSIGNED NOT NULL DEFAULT 0');
        $this->try_sql('ALTER TABLE `availability_slots` ADD COLUMN IF NOT EXISTS `window_id` INT(10) UNSIGNED NOT NULL DEFAULT 0');
        $this->try_sql('ALTER TABLE `availability_slots` ADD INDEX `idx_slot_grade` (`grade_id`,`status`,`starts_at`)');

        /* والمادة مع الصف لا بعده: المعلم يدرس أكثر من مادة، ومن يفتح
           «الأحد ١٠–٢ للثالث» بلا مادة يستقبل طالبا جاء يسأل في الرياضيات
           وهو معلم لغة عربية. والعمودان يضافان على الجدولين معا — على
           النافذة لأنها القاعدة، وعلى الموعد لأنه يحجز ويجمد. */
        $this->try_sql('ALTER TABLE `tq_teacher_windows` ADD COLUMN IF NOT EXISTS `subject_id` INT(10) UNSIGNED NOT NULL DEFAULT 0');
        $this->try_sql('ALTER TABLE `availability_slots` ADD COLUMN IF NOT EXISTS `subject_id` INT(10) UNSIGNED NOT NULL DEFAULT 0');
        $this->try_sql('ALTER TABLE `availability_slots` ADD INDEX `idx_slot_subject` (`subject_id`,`status`,`starts_at`)');

        /* ما فتحه المعلمون قبل اليوم يصير قواعد تقرأ في شاشتهم — وبلا هذا
           يفتحون الشاشة فيجدونها فارغة وقد حفظوا، ثم تمحو أول دورة فرش
           مواعيدهم لأن لا قاعدة تسندها. والصف يبقى صفرا («كل الصفوف»)
           فلا يضيق على أحد ما كان مفتوحا. */
        $this->backfill_windows();

        $this->put_setting('tq_session_schema_v', self::SCHEMA_V);
    }

    /**
     * يشتق قواعد الأسبوع من المواعيد المفتوحة القائمة — مرة واحدة.
     *
     * والمواعيد المتلاصقة تجمع في نافذة واحدة: معلم فتح «مساء» كان له
     * خمسة مواعيد متتابعة، وكتابتها خمس قواعد يجعل شاشته تقرأ خمسة أسطر
     * لشيء واحد.
     */
    private function backfill_windows()
    {
        try {
            $has = $this->db->query('SELECT 1 FROM `tq_teacher_windows` LIMIT 1')->num_rows();
            if ($has > 0) return;

            $rows = $this->db->query(
                'SELECT `teacher_id`, `starts_at`, `duration_min`
                   FROM `availability_slots`
                  WHERE `starts_at` >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                  ORDER BY `teacher_id` ASC, `starts_at` ASC'
            )->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return;
        }

        /* (معلم، يوم) ← مدى الدقائق، يمدد ما دام التالي يلاصق السابق. */
        $runs = array();
        foreach ($rows as $r) {
            $ts  = strtotime((string) $r['starts_at']);
            if (!$ts) continue;
            $tid = (int) $r['teacher_id'];
            $dow = (int) date('w', $ts);
            $b   = (int) date('G', $ts) * 60 + (int) date('i', $ts);
            $e   = $b + max(1, (int) $r['duration_min']);
            $k   = $tid . ':' . $dow;

            $n = count($runs[$k] ?? array());
            if ($n > 0 && $runs[$k][$n - 1][1] >= $b && $runs[$k][$n - 1][1] <= $e) {
                $runs[$k][$n - 1][1] = max($runs[$k][$n - 1][1], $e);
            } elseif ($n === 0 || $runs[$k][$n - 1][1] < $b) {
                $runs[$k][] = array($b, $e);
            }
        }

        $now = date('Y-m-d H:i:s');
        foreach ($runs as $k => $spans) {
            list($tid, $dow) = array_map('intval', explode(':', $k));
            foreach ($spans as $sp) {
                $this->try_sql_args(
                    'INSERT IGNORE INTO `tq_teacher_windows`
                       (`teacher_id`,`dow`,`start_min`,`end_min`,`grade_id`,`created_at`)
                     VALUES (?, ?, ?, ?, 0, ?)',
                    array($tid, $dow, $sp[0], min(1440, $sp[1]), $now)
                );
            }
        }
    }

    /** كـ`try_sql` ولكن بمعاملات مربوطة. */
    private function try_sql_args($sql, $args)
    {
        try { $this->db->query($sql, $args); }
        catch (Throwable $e) { $this->db->reset_query(); }
    }

    /** ينفذ تعديل بنية ولا يسقط الطلب إن كان منفذا من قبل. */
    private function try_sql($sql)
    {
        try { $this->db->query($sql); } catch (Throwable $e) { /* منفذ من قبل */ }
    }

    /* =====================================================================
       التسعيرة — قرار إدارة، وقيمة تجمد على الصف
       ===================================================================== */

    /** الافتراضات. تكتب هنا مرة، وتقرأ منها الشاشات كلها. */
    public static $DEFAULTS = array(
        'tq_session_price_sar'       => 0,    // صفر = الحصص مجانية كما كانت
        'tq_session_teacher_percent' => 70,   // الحصة وقت معلم لا محتوى منصة
        'tq_session_minutes'         => 60,
        'tq_session_pay_hours'       => 12,
        'tq_session_join_lead_min'   => 30,
        'tq_session_grace_hours'     => 6,
    );

    private static $cfg_cache = null;

    /** كل مفاتيح الحصص بقيمها المحدودة — استعلام واحد لا ستة. */
    public function config($fresh = false)
    {
        if (self::$cfg_cache !== null && !$fresh) return self::$cfg_cache;

        $keys = array_keys(self::$DEFAULTS);
        $rows = $this->db->select('key, value')->where_in('key', $keys)
                         ->get('settings')->result_array();
        $have = array();
        foreach ($rows as $r) $have[$r['key']] = $r['value'];

        $c = array();
        foreach (self::$DEFAULTS as $k => $def) {
            $c[$k] = (!isset($have[$k]) || trim((string) $have[$k]) === '') ? $def : $have[$k];
        }

        /* الحدود تفرض هنا لا في الشاشة: قيمة كتبت مرة في القاعدة بيد أو
           بسكربت تبقى تقرأ إلى الأبد، وحصة بصفر دقيقة تفرش الفترة إلى
           عدد لا نهائي من المواعيد. */
        return self::$cfg_cache = array(
            'price'       => max(0, (int) round(((float) $c['tq_session_price_sar']) * 100)),
            'percent'     => max(0, min(100, (float) $c['tq_session_teacher_percent'])),
            'minutes'     => max(15, min(300, (int) $c['tq_session_minutes'])),
            'pay_hours'   => max(1, min(168, (int) $c['tq_session_pay_hours'])),
            'lead_min'    => max(0, min(240, (int) $c['tq_session_join_lead_min'])),
            'grace_hours' => max(1, min(72, (int) $c['tq_session_grace_hours'])),
        );
    }

    /** يحفظ مفاتيح التسعيرة العامة — upsert، فالمفتاح الغائب ينشأ. */
    public function save_config($vals)
    {
        $this->install_schema();
        foreach (self::$DEFAULTS as $k => $_) {
            if (!array_key_exists($k, $vals)) continue;
            $this->put_setting($k, trim((string) $vals[$k]));
        }
        self::$cfg_cache = null;
        return $this->config(true);
    }

    private function put_setting($key, $value)
    {
        $exists = $this->db->where('key', $key)->count_all_results('settings') > 0;
        if ($exists) $this->db->where('key', $key)->update('settings', array('value' => $value));
        else         $this->db->insert('settings', array('key' => $key, 'value' => $value));
    }

    /**
     * تسعيرة معلم بعينه: استثناؤه إن كتب، وإلا العام.
     *
     * والفرق بين «فارغ» و«صفر» محفوظ: معلم كتب له صفر يدرس مجانا بقرار،
     * ومعلم لم يكتب له شيء يبيع بسعر المنصة. وعمود واحد لا يفرق بينهما
     * يجعل كل معلم لم يمر عليه المسؤول يدرس مجانا.
     *
     * @return array price · percent · share · platform · from_teacher
     */
    public function pricing_for($teacher_id)
    {
        $c   = $this->config();
        $row = null;
        if ((int) $teacher_id > 0) {
            $row = $this->safe_row(
                'SELECT `tq_session_price`, `tq_session_percent` FROM `users` WHERE `id` = ? LIMIT 1',
                array((int) $teacher_id)
            );
        }

        $price   = $c['price'];
        $percent = $c['percent'];
        $own     = false;

        if ($row && isset($row['tq_session_price']) && $row['tq_session_price'] !== null && $row['tq_session_price'] !== '') {
            $price = max(0, (int) $row['tq_session_price']);
            $own   = true;
        }
        if ($row && isset($row['tq_session_percent']) && $row['tq_session_percent'] !== null && $row['tq_session_percent'] !== '') {
            $percent = max(0, min(100, (float) $row['tq_session_percent']));
            $own     = true;
        }

        return $this->split($price, $percent) + array('from_teacher' => $own);
    }

    /**
     * يقسم سعرا على نسبة. التقريب مرة واحدة والباقي للمنصة، فلا تضيع
     * هللة ولا تخترع — وهي القاعدة نفسها في `credit_path_sale()`.
     */
    public function split($price_halalas, $percent)
    {
        $price   = max(0, (int) $price_halalas);
        $percent = max(0, min(100, (float) $percent));
        $share   = (int) round($price * $percent / 100);
        return array(
            'price'    => $price,
            'percent'  => $percent,
            'share'    => $share,
            'platform' => $price - $share,
        );
    }

    /** يحفظ استثناء معلم. النص الفارغ يمحو الاستثناء ولا يكتب صفرا. */
    public function save_teacher_pricing($teacher_id, $price_sar, $percent)
    {
        $this->install_schema();
        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return false;

        $price_sar = trim((string) $price_sar);
        $percent   = trim((string) $percent);

        $this->db->where('id', $teacher_id)->update('users', array(
            'tq_session_price'   => $price_sar === '' ? null : max(0, (int) round(((float) $price_sar) * 100)),
            'tq_session_percent' => $percent === ''   ? null : max(0, min(100, (float) $percent)),
        ));
        return true;
    }

    /* =====================================================================
       رابط اللقاء
       ===================================================================== */

    /**
     * يطهر رابط اللقاء: يقبل https ومضيفا من القائمة البيضاء وحده.
     *
     * @return string الرابط إن صح، أو '' إن لم يصح أو كان فارغا
     */
    public function clean_meet_url($url)
    {
        $url = trim((string) $url);
        if ($url === '') return '';

        $p = parse_url($url);
        if (!$p || empty($p['scheme']) || empty($p['host'])) return '';
        if (strtolower($p['scheme']) !== 'https') return '';

        $host = strtolower($p['host']);
        if (strpos($host, 'www.') === 0) $host = substr($host, 4);

        foreach (self::MEET_HOSTS as $ok) {
            // النطاق نفسه أو نطاق فرعي منه — و`endsWith` بنقطة تمنع
            // `evil-zoom.us` من المرور بوصفه `zoom.us`.
            if ($host === $ok || substr($host, -(strlen($ok) + 1)) === '.' . $ok) {
                return mb_substr($url, 0, 512);
            }
        }
        return '';
    }

    /** أسماء المضيفين كما تعرض للمعلم في التلميح. */
    public function meet_hosts_text()
    {
        return 'Google Meet أو Zoom أو Microsoft Teams';
    }

    /* =====================================================================
       الأيام — مصدر واحد للشبكة وللترجمة
       ===================================================================== */

    /** أيام الأسبوع بترتيب `date('w')` نفسه: الأحد أولا. */
    public function days()
    {
        return tq_t_deep([
            0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء',
            4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
        ]);
    }

    /** أسماء الشهور الميلادية كما تكتب في السوق السعودي. */
    private function month_name($m)
    {
        static $names = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                         'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $i = ((int) $m) - 1;
        /* TQ-I18N — الترجمة عند الخروج: `static` لا يقبل نداء في تهيئته. */
        return isset($names[$i]) ? t($names[$i]) : '';
    }

    /** «الأحد 3 أغسطس · 17:00 – 18:00» — نص واحد يعرض كما هو. */
    public function when_text($starts_at, $minutes = 0)
    {
        $ts = strtotime((string) $starts_at);
        if (!$ts) return '';

        $days = $this->days();
        $out  = ($days[(int) date('w', $ts)] ?? '') . ' ' . date('j', $ts) . ' ' . $this->month_name(date('n', $ts));

        $len = (int) $minutes > 0 ? (int) $minutes : $this->config()['minutes'];
        return $out . ' · ' . date('H:i', $ts) . ' – ' . date('H:i', $ts + $len * 60);
    }

    /** حدا النافذة التي تدار فيها الشبكة. */
    private function window($now = null)
    {
        $now = $now ? (int) $now : time();
        return [
            date('Y-m-d H:i:s', $now),
            date('Y-m-d H:i:s', strtotime('+' . self::WINDOW_DAYS . ' day', $now)),
        ];
    }

    /* =====================================================================
       الصفوف — الحصة تعطى لصف بعينه لا لكل من طرق الباب
       ===================================================================== */

    /**
     * الصفوف الفعالة كما في `grades` — مرجع واحد تقرأ منه الشاشات الأربع.
     *
     * والاسم يقرأ كما كتب: هو بيان يحرر من اللوحة لا سلسلة تترجم، فلا
     * يمر بـ`t()` كما لا يمر اسم كورس.
     */
    public function grades()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $out = array();
        try {
            $rows = $this->db->select('id, name_ar')->where('active', 1)
                             ->order_by('`order`', 'ASC', false)
                             ->get('grades')->result_array();
            foreach ($rows as $r) $out[(int) $r['id']] = (string) $r['name_ar'];
        } catch (Throwable $e) {
            /* TQ-BUILDER-DIRTY — الاستثناء يترك بناء الاستعلام كما هو،
               فيرث كل استعلام تال في الطلب نفسه ضموم هذا. */
            $this->db->reset_query();
            $out = array();
        }
        return $cache = $out;
    }

    /** المواد الفعالة كما في `subjects` — كأختها في `grades`. */
    public function subjects()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $out = array();
        try {
            $rows = $this->db->select('id, name_ar')->where('active', 1)
                             ->order_by('`order`', 'ASC', false)
                             ->get('subjects')->result_array();
            foreach ($rows as $r) $out[(int) $r['id']] = (string) $r['name_ar'];
        } catch (Throwable $e) { $this->db->reset_query(); $out = array(); }
        return $cache = $out;
    }

    /** اسم المادة كما تعرض. والصفر «بلا مادة» — حال ما فتح قبل اليوم. */
    public function subject_name($subject_id)
    {
        $id = (int) $subject_id;
        if ($id <= 0) return '';
        $s = $this->subjects();
        return isset($s[$id]) ? $s[$id] : t('مادة') . ' #' . $id;
    }

    /** اسم الصف كما يعرض. والصفر ليس صفا مجهولا: هو «كل الصفوف» بقرار. */
    public function grade_name($grade_id)
    {
        $id = (int) $grade_id;
        if ($id <= 0) return t('كل الصفوف');
        $g = $this->grades();
        return isset($g[$id]) ? $g[$id] : t('صف') . ' #' . $id;
    }

    /**
     * نطاق المعلم: **ما يدرسه فعلا**، صفا ومادة معا.
     *
     * والزوج (صف، مادة) لا قائمتان مستقلتان: معلم له كورس رياضيات في
     * الثالث وكورس لغة عربية في الرابع، وقائمتان تسمحان له بفتح «رياضيات
     * للرابع» — وهو ما لا يدرسه. والزوج يأتي من صف واحد في `paths` أو في
     * `teacher_assignments`، فالمصدر هو الذي يعطيه لا استنتاج فوقه.
     *
     * @return array ['grades'=>[id=>اسم], 'subjects'=>[id=>اسم],
     *                'pairs'=>[صف => [مادة => اسم]]]
     */
    public function teacher_scope($teacher_id)
    {
        static $cache = array();
        $teacher_id = (int) $teacher_id;
        if (isset($cache[$teacher_id])) return $cache[$teacher_id];

        $empty = array('grades' => array(), 'subjects' => array(), 'pairs' => array());
        if ($teacher_id <= 0) return $empty;

        $rows = array();

        /* ١ — إسناد الإدارة الصريح، وهو أقوى المصادر. */
        try {
            $rows = array_merge($rows, $this->db->select('grade_id, subject_id')
                ->where('teacher_id', $teacher_id)
                ->get('teacher_assignments')->result_array());
        } catch (Throwable $e) { $this->db->reset_query(); }

        /* ٢ و٣ — المسار المسند إليه، والمسار الذي يقود إلى كورس يملكه.
           و`course.user_id` قائمة معرفات بفواصل في Academy، فالمعلم الثاني
           في كورس مشترك يعد من أصحابه. */
        try {
            $rows = array_merge($rows, $this->db->select('p.grade_id, p.subject_id')
                ->from('paths p')
                ->join('course c', 'c.id = p.course_id', 'left')
                ->group_start()
                    ->where('p.teacher_id', $teacher_id)
                    ->or_where('c.creator', $teacher_id)
                    ->or_where('FIND_IN_SET(' . $teacher_id . ', c.user_id) > 0', null, false)
                ->group_end()
                ->get()->result_array());
        } catch (Throwable $e) { $this->db->reset_query(); }

        $all_g = $this->grades();
        $all_s = $this->subjects();

        $out = $empty;
        foreach ($rows as $r) {
            $g = (int) ($r['grade_id']   ?? 0);
            $j = (int) ($r['subject_id'] ?? 0);
            if ($g <= 0 || !isset($all_g[$g])) continue;

            $out['grades'][$g] = $all_g[$g];
            if ($j > 0 && isset($all_s[$j])) {
                $out['subjects'][$j]  = $all_s[$j];
                $out['pairs'][$g][$j] = $all_s[$j];
            }
        }

        /* صف بلا مادة في مصدره (وهو أكثر ما في `paths` اليوم) لا يترك
           منتقي المادة فارغا فيقفل الشاشة: يأخذ مواد المعلم كلها. وحكم
           الحفظ يتبع هذا حرفا، فلا شاشة تعرض ما يرده الخادم. */
        foreach ($out['grades'] as $g => $_) {
            if (empty($out['pairs'][$g])) $out['pairs'][$g] = $out['subjects'];
        }

        return $cache[$teacher_id] = $out;
    }

    /**
     * الصفوف التي يجوز لهذا المعلم أن يفتح لها وقتا — **صفوف محتواه وحدها**.
     *
     * ولا ترتد إلى «كل الصفوف» متى خلت. وكان الارتداد أرفق ظاهرا وهو خطأ:
     * الحصة الخاصة شرح لمنهج صف بعينه، ومعلم الرابع الابتدائي الذي يفتح
     * وقتا لطالب في الثالث المتوسط يجلس معه ساعة لا يفيده فيها — والثمن
     * قبض، والشكوى تصل الإدارة بعد أن تنعقد الحصة لا قبلها. فالباب الذي
     * لا ينبغي أن يفتح يغلق في الشاشة وفي الحفظ معا.
     *
     * **والمصادر ثلاثة تجمع** لا واحد يغني عن الباقي:
     *
     *   ١ — `teacher_assignments` — إسناد الإدارة الصريح، وهو أقواها.
     *   ٢ — `paths.teacher_id` — البرنامج المسند إليه، وهو أكثر ما في
     *       القاعدة (تسعة من ثمانية عشر مسارا، ولا `course_id` لأكثرها).
     *   ٣ — `course.creator` و`course.user_id` — كورس يملكه، ومساره يقول
     *       صفه. و`user_id` قائمة معرفات بفواصل في Academy، فالمعلم
     *       الثاني في كورس مشترك يعد من أصحابه.
     *
     * وواحد منها وحده يترك معلما لا صف له وهو يدرس: الإسناد فارغ في أكثر
     * التركيبات، وأكثر المسارات بلا كورس. فالثلاثة تجمع.
     *
     * @return array صف ← اسمه. وفارغة تعني «لا يفتح وقتا حتى يسند إليه صف».
     */
    public function teacher_grades($teacher_id)
    {
        $sc = $this->teacher_scope($teacher_id);
        return $sc['grades'];
    }

    /** مواد هذا المعلم وحدها — انظر `teacher_scope()`. */
    public function teacher_subject_options($teacher_id)
    {
        $sc = $this->teacher_scope($teacher_id);
        return $sc['subjects'];
    }

    /** صف الطالب من `users.grade_id` — وصفر يعني «لم يحدد بعد». */
    public function student_grade($student_id)
    {
        $student_id = (int) $student_id;
        if ($student_id <= 0) return 0;
        try {
            $r = $this->db->select('grade_id')->where('id', $student_id)
                          ->get('users')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return 0; }
        return (int) ($r['grade_id'] ?? 0);
    }

    /* =====================================================================
       نوافذ الإتاحة — القاعدة الأسبوعية الدائمة
       ===================================================================== */

    /** «٦٣٠» ← «10:30». دقائق من منتصف الليل إلى ساعة تقرأ. */
    public function min_to_hhmm($m)
    {
        $m = max(0, min(1440, (int) $m));
        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    /** «10:30» ← ٦٣٠، و`null` لما لا يقرأ ساعة. */
    public function hhmm_to_min($txt)
    {
        $txt = trim((string) $txt);
        /* `<input type="time">` يرسل «HH:MM»، وبعض المتصفحات ترسل الثواني
           معها حين يضبط `step` — والثواني لا تعني شيئا هنا فتهمل. */
        if (!preg_match('/^([0-9]{1,2}):([0-9]{2})(?::[0-9]{2})?$/', $txt, $mm)) return null;
        $h = (int) $mm[1]; $i = (int) $mm[2];
        if ($h > 24 || $i > 59 || ($h === 24 && $i > 0)) return null;
        return $h * 60 + $i;
    }

    /** «10:00 – 14:00» — مدى واحد يعرض كما هو. */
    public function span_text($start_min, $end_min)
    {
        return $this->min_to_hhmm($start_min) . ' – ' . $this->min_to_hhmm($end_min);
    }

    /**
     * قواعد الأسبوع لهذا المعلم، مرتبة يوما بيوم ومعها ما يعرض.
     *
     * `slots` عدد المواعيد التي تفرش إليها النافذة — والمعلم يقرأه قبل أن
     * يحفظ: «من ١٠ إلى ٢» أربع ساعات تعني أربعة طلاب لا طالبا واحدا،
     * ونافذة أقصر من مدة الحصة لا تعطي موعدا واحدا وهو ما لا يخطر لأحد.
     */
    public function windows_for($teacher_id)
    {
        $this->install_schema();

        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return array();

        try {
            $rows = $this->db->where('teacher_id', $teacher_id)
                             ->order_by('dow', 'ASC')->order_by('start_min', 'ASC')
                             ->get('tq_teacher_windows')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }

        $days = $this->days();
        $len  = max(1, (int) $this->config()['minutes']);

        $out = array();
        foreach ($rows as $r) {
            $b = (int) $r['start_min'];
            $e = (int) $r['end_min'];
            $out[] = array(
                'id'         => (int) $r['id'],
                'dow'        => (int) $r['dow'],
                'day_name'   => $days[(int) $r['dow']] ?? '',
                'start_min'  => $b,
                'end_min'    => $e,
                'from_text'  => $this->min_to_hhmm($b),
                'to_text'    => $this->min_to_hhmm($e),
                'span_text'  => $this->span_text($b, $e),
                'grade_id'     => (int) $r['grade_id'],
                'grade_name'   => $this->grade_name((int) $r['grade_id']),
                'subject_id'   => (int) ($r['subject_id'] ?? 0),
                'subject_name' => $this->subject_name((int) ($r['subject_id'] ?? 0)),
                'slots'        => intdiv(max(0, $e - $b), $len),
            );
        }
        return $out;
    }

    /**
     * يحفظ قواعد الأسبوع كلها، ثم يفرشها مواعيد.
     *
     * والحفظ **استبدال كامل** لا إضافة: الشاشة تعرض ما هو محفوظ وتعيد
     * إرساله كله، فحذف سطر منها يعني حذفه من القاعدة. وإضافة صف بصف تجعل
     * الحذف يحتاج مسار كتابة ثانيا ومعرفا يرسل من متصفح.
     *
     * **ولا نافذتان تتقاطعان في يوم واحد** ولو اختلف صفهما: المعلم واحد لا
     * يجلس في حصتين معا، وقبولهما يفرش موعدا واحدا لصف واحد منهما بصمت —
     * فيقرأ المعلم أنه فتح للصفين وأحدهما لا يرى شيئا. والتقاطع يرد باليوم
     * والساعة لا بـ«خطأ في المدخلات».
     *
     * @param array $rows صفوف [dow, from, to, grade_id]
     * @return array ['ok'=>bool,'msg'=>string,'count'=>int,'slots'=>int]
     */
    public function save_windows($teacher_id, $rows)
    {
        $this->install_schema();

        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return array('ok' => false, 'msg' => 'طلب غير مكتمل.', 'count' => 0, 'slots' => 0);

        $days   = $this->days();
        $len    = max(1, (int) $this->config()['minutes']);
        $scope  = $this->teacher_scope($teacher_id);
        $mine   = $scope['grades'];
        $clean  = array();
        $by_day = array();

        $bad = function ($msg) {
            return array('ok' => false, 'msg' => $msg, 'count' => 0, 'slots' => 0);
        };

        /* معلم بلا صف واحد لا يفتح وقتا — ولا يقال له «حفظ» ثم لا يظهر
           لأحد. والرسالة تقول الطريق: الصفوف تشتق من كورساته، فمن لا كورس
           له يفتح كورسا أو يراجع الإدارة. */
        if (!$mine) {
            return $bad('لا صف لك بعد، فلا تفتح وقتا لأحد. الصفوف تشتق من كورساتك — '
                      . 'افتح كورسا في صفك أو راجع الإدارة لتسند إليك صفا.');
        }
        if (!$scope['subjects']) {
            return $bad('لا مادة لك بعد، فلا تفتح وقتا لأحد. المواد تشتق من كورساتك — '
                      . 'افتح كورسا أو راجع الإدارة لتسند إليك مادة.');
        }

        foreach ((array) $rows as $r) {
            $dow = isset($r['dow']) && $r['dow'] !== '' ? (int) $r['dow'] : -1;
            $b   = $this->hhmm_to_min(isset($r['from']) ? $r['from'] : '');
            $e   = $this->hhmm_to_min(isset($r['to'])   ? $r['to']   : '');
            $g   = isset($r['grade_id'])   ? (int) $r['grade_id']   : 0;
            $j   = isset($r['subject_id']) ? (int) $r['subject_id'] : 0;

            /* السطر الفارغ يتخطى ولا يرد: الشاشة تبقي سطرا فارغا في ذيلها
               ليكتب فيه التالي، ورده بخطأ يمنع الحفظ على من لم يخطئ. */
            if ($dow < 0 && $b === null && $e === null) continue;

            if ($dow < 0 || $dow > 6) return $bad('اختر يوما لكل موعد.');
            $day = isset($days[$dow]) ? $days[$dow] : '';

            if ($b === null || $e === null) {
                return $bad('اكتب وقت البداية والنهاية ليوم ' . $day . ' بصيغة الساعة (مثال 10:00).');
            }
            if ($e <= $b) {
                return $bad('وقت النهاية في ' . $day . ' يجب أن يكون بعد وقت البداية.');
            }
            if ($e - $b < self::MIN_WINDOW_MIN) {
                return $bad('مدة ' . $day . ' قصيرة جدا — أقلها ' . self::MIN_WINDOW_MIN . ' دقيقة.');
            }
            /* أقصر من مدة الحصة يحفظ ولا يفرش موعدا واحدا: صف في القاعدة
               وشاشة طالب فارغة، ولا شيء يقول لماذا. */
            if ($e - $b < $len) {
                return $bad('مدة الحصة ' . $len . ' دقيقة، فلا يفرش موعد واحد من ' . $day . ' '
                    . $this->span_text($b, $e) . '. وسع الوقت أو راجع الإدارة في مدة الحصة.');
            }
            /* **والصف مطلوب لا اختياري**: «كل الصفوف» تعني وقتا يقبل فيه
               صاحبه طالبا لا يدرس له، فيجلس معه ساعة لا تفيده — وقد دفع
               ثمنها. والصفر يبقى مقروءا في القاعدة لما فتح قبل اليوم، ولا
               يكتب صف جديد به. */
            if ($g <= 0) {
                return $bad('اختر صف يوم ' . $day . '. والصفوف المعروضة هي صفوف كورساتك '
                          . 'وحدها — الحصة شرح لمنهج صف بعينه.');
            }
            if (!isset($mine[$g])) {
                return $bad('صف لا كورس لك فيه، في يوم ' . $day . '. افتح كورسا في ذلك الصف '
                          . 'أو راجع الإدارة لتسنده إليك.');
            }

            /* **والمادة تفحص مع صفها لا وحدها**: معلم له رياضيات الثالث
               ولغة عربية الرابع، وفحصان مستقلان يمرران «رياضيات للرابع»
               وهو ما لا يدرسه. والزوج يأتي من صف واحد في المصدر. */
            if ($j <= 0) {
                return $bad('اختر مادة يوم ' . $day . '. والمواد المعروضة هي مواد كورساتك '
                          . 'وحدها — الطالب يحجز ليسأل في مادة بعينها.');
            }
            $pairs = isset($scope['pairs'][$g]) ? $scope['pairs'][$g] : array();
            if (!isset($pairs[$j])) {
                return $bad('لا كورس لك في هذه المادة لهذا الصف، في يوم ' . $day . ' ('
                          . $mine[$g] . ' · ' . $this->subject_name($j) . '). '
                          . 'اختر مادة تدرسها لهذا الصف، أو راجع الإدارة.');
            }

            foreach (isset($by_day[$dow]) ? $by_day[$dow] : array() as $prev) {
                if ($b < $prev[1] && $prev[0] < $e) {
                    return $bad('موعدان متداخلان في ' . $day . ': '
                        . $this->span_text($prev[0], $prev[1]) . ' و' . $this->span_text($b, $e)
                        . '. ولا تعطى حصتان في وقت واحد.');
                }
            }

            $by_day[$dow][] = array($b, $e);
            $clean[] = array('dow' => $dow, 'start_min' => $b, 'end_min' => $e,
                             'grade_id' => $g, 'subject_id' => $j);
        }

        $now = date('Y-m-d H:i:s');
        try {
            $this->db->where('teacher_id', $teacher_id)->delete('tq_teacher_windows');
            foreach ($clean as $c) {
                $this->db->query(
                    'INSERT IGNORE INTO `tq_teacher_windows`
                       (`teacher_id`,`dow`,`start_min`,`end_min`,`grade_id`,`subject_id`,`created_at`)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    array($teacher_id, $c['dow'], $c['start_min'], $c['end_min'],
                          $c['grade_id'], $c['subject_id'], $now)
                );
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
            return $bad('تعذر حفظ أوقاتك. حاول مرة أخرى.');
        }

        return array('ok' => true, 'count' => count($clean),
                     'slots' => $this->layout($teacher_id), 'msg' => '');
    }

    /* =====================================================================
       الفرش — من القاعدة إلى المواعيد
       ===================================================================== */

    /**
     * يفرش قواعد معلم إلى مواعيد في النافذة القادمة.
     *
     * ولا يحذف موعدا عليه طلب حي — ولو رفع المعلم قاعدته: الطالب طلبه
     * فعلا، وحذفه يترك طلبا معلقا بلا موعد. من أراد إلغاءه فليعتذر عنه.
     *
     * **والموعد المحجوز يبقى بصفه الذي حجز به**: تعديل صف النافذة اليوم
     * لا يغير صف حصة طلبت أمس — والتحديث مقصور على `open`.
     *
     * @return int عدد المواعيد المفتوحة القادمة بعد الفرش
     */
    public function layout($teacher_id, $now = null)
    {
        $this->install_schema();

        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return 0;

        $now = $now ? (int) $now : time();
        list($from, $to) = $this->window($now);
        $to_ts = strtotime($to);
        $len   = max(1, (int) $this->config()['minutes']);

        try {
            $wins = $this->db->where('teacher_id', $teacher_id)
                             ->get('tq_teacher_windows')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return 0; }

        /* موعد ← [مدته، صفه، نافذته]. والمفتاح هو الموعد نفسه، فنافذتان
           تلتقيان على ساعة (وهو ما يرده الحفظ، وقد يبقى من قاعدة قديمة)
           تعطيان موعدا واحدا لا موعدين على الساعة نفسها — والمفتاح الفريد
           في القاعدة يرد الثاني على كل حال. */
        $want = array();
        for ($d = 0; $d <= self::WINDOW_DAYS; $d++) {
            $midnite = strtotime(date('Y-m-d', strtotime('+' . $d . ' day', $now)) . ' 00:00:00');
            $dow     = (int) date('w', $midnite);

            foreach ($wins as $w) {
                if ((int) $w['dow'] !== $dow) continue;
                $end = (int) $w['end_min'];
                for ($m = (int) $w['start_min']; $m + $len <= $end; $m += $len) {
                    $ts = $midnite + $m * 60;
                    if ($ts <= $now || $ts > $to_ts) continue;
                    $k = date('Y-m-d H:i:s', $ts);
                    if (isset($want[$k])) continue;
                    $want[$k] = array($len, (int) $w['grade_id'], (int) $w['id'],
                                      (int) ($w['subject_id'] ?? 0));
                }
            }
        }

        $states = "'" . implode("','", self::$LIVE_STATES) . "'";
        $this->db->where('teacher_id', $teacher_id)
                 ->where('status', 'open')
                 ->where('starts_at >', $from)
                 ->where('starts_at <=', $to)
                 ->where("id NOT IN (SELECT slot_id FROM tutoring_sessions
                          WHERE slot_id IS NOT NULL AND status IN (" . $states . "))", null, false);
        if ($want) $this->db->where_not_in('starts_at', array_keys($want));
        $this->db->delete('availability_slots');

        // المفتاح الفريد (معلم، موعد) يمنع التكرار، فإعادة الفرش لا تضاعف
        // شيئا ولا ترجع محجوزا إلى `open`.
        foreach ($want as $dt => $meta) {
            $this->db->query(
                'INSERT IGNORE INTO availability_slots
                   (teacher_id, starts_at, duration_min, status, grade_id, window_id, subject_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                array($teacher_id, $dt, $meta[0], 'open', $meta[1], $meta[2], $meta[3])
            );
            /* والمفتوح يتبع قاعدته: من غير صف نافذته، أو تغيرت مدة الحصة
               تحته، يصحح صفه المفتوح — والمحجوز لا يمس. */
            $this->db->where('teacher_id', $teacher_id)->where('starts_at', $dt)
                     ->where('status', 'open')
                     ->update('availability_slots', array(
                         'duration_min' => $meta[0],
                         'grade_id'     => $meta[1],
                         'window_id'    => $meta[2],
                         'subject_id'   => $meta[3],
                     ));
        }

        return (int) $this->db->where('teacher_id', $teacher_id)->where('status', 'open')
                              ->where('starts_at >', date('Y-m-d H:i:s', $now))
                              ->count_all_results('availability_slots');
    }

    /**
     * يفرش لكل من له قاعدة — تنادى من دورة الكرون.
     *
     * وبلا هذا المرور تنفد الشبكة بعد أسبوعين من آخر حفظ، فيختفي المعلم
     * من شاشة الطالب بلا أن يغلق وقته أحد، ولا شيء في أي شاشة يقول لماذا.
     */
    public function layout_all($now = null)
    {
        $this->install_schema();

        try {
            $rows = $this->db->distinct()->select('teacher_id')
                             ->get('tq_teacher_windows')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return 0; }

        $n = 0;
        foreach ($rows as $r) { $this->layout((int) $r['teacher_id'], $now); $n++; }
        return $n;
    }

    /* =====================================================================
       المعلمون المتاحون — لشاشة الطالب
       ===================================================================== */

    /**
     * اشتقاق المعلم كما في `taqdar_role_helper`: مفعل، `is_instructor`، وليس
     * أدمن. الأدمن لا تفتح له بوابة المعلم فلا يعرض معلما للطلاب.
     */
    private function teacher_filter($alias = 'u')
    {
        $this->db->where($alias . '.status', 1)
                 ->where($alias . '.is_instructor', 1)
                 ->where($alias . '.role_id !=', 1);
    }

    /**
     * المعلمون الذين لهم مواعيد مفتوحة قادمة، ومعهم مواعيدهم وسعرهم.
     *
     * والسعر يعرض هنا لا في الشاشة: هو أول ما يسأل عنه الطالب، وشاشة
     * تعرض معلمين بلا أثمان تجعل الاختيار يقع ثم ينكشف الثمن بعده.
     *
     * **والصف يرشح في الاستعلام لا في الشاشة** (TQ-SESSION-GRID): المعلم
     * يفتح وقته لصف بعينه، فطالب الثالث الابتدائي لا يرى موعدا فتح لطلاب
     * الرابع فيطلبه ثم يعتذر عنه معلمه. والموعد بصفر يعني «كل الصفوف»
     * فيبقى معروضا للجميع كما كان — وهو صف كل ما فتح قبل اليوم.
     *
     * **والطالب بلا صف يرى الكل**: باب يرد من لا نعرف صفه ليس حراسة، وهو
     * لم يخطئ — والشاشة تدعوه إلى تحديد صفه بدل أن تفرغ أمامه.
     *
     * @param int $limit_teachers أقصى عدد معلمين
     * @param int $limit_slots    أقصى عدد مواعيد لكل معلم
     * @param int $subject_id     تصفية بمادة من `subjects` (٠ = الكل)
     * @param int $grade_id       صف الطالب (٠ = بلا ترشيح)
     */
    public function available_teachers($limit_teachers = 12, $limit_slots = 6, $subject_id = 0, $grade_id = 0)
    {
        $this->install_schema();
        $now = date('Y-m-d H:i:s');

        $this->db->select('s.id AS slot_id, s.starts_at, s.duration_min, s.grade_id, s.subject_id,
                           u.id AS teacher_id, u.first_name, u.last_name, u.image, u.title')
                 ->from('availability_slots s')
                 ->join('users u', 'u.id = s.teacher_id', 'inner')
                 ->where('s.status', 'open')
                 ->where('s.starts_at >', $now);
        if ((int) $grade_id > 0) $this->db->where_in('s.grade_id', array(0, (int) $grade_id));
        $this->teacher_filter('u');
        $rows = $this->db->order_by('s.starts_at', 'ASC')->limit(600)->get()->result_array();

        $subjects = $this->teacher_subjects();

        $out = [];
        foreach ($rows as $r) {
            $tid = (int) $r['teacher_id'];

            /* المادة تقرأ من **الموعد** متى أعلنها: هو أدق من مادة المعلم،
               ومعلم مادتين كان يظهر كله في ترشيح إحداهما بمواعيد الأخرى.
               والموعد بلا مادة (ما فتح قبل TQ-SESSION-GRID) يرتد إلى مواد
               معلمه كما كان، فلا يسقط من الشاشة. */
            $slot_subject = (int) ($r['subject_id'] ?? 0);
            if ($subject_id > 0) {
                if ($slot_subject > 0) {
                    if ($slot_subject !== (int) $subject_id) continue;
                } else {
                    $mine = $subjects['cats'][$tid] ?? [];
                    if (!in_array((int) $subject_id, $mine, true)) continue;
                }
            }

            if (!isset($out[$tid])) {
                if (count($out) >= (int) $limit_teachers) continue;
                $name = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
                $out[$tid] = [
                    'id'      => $tid,
                    'name'    => $name !== '' ? $name : 'معلم',
                    'image'   => (string) $r['image'],
                    'title'   => trim((string) $r['title']),
                    'subject' => $subjects['name'][$tid] ?? '',
                    'pricing' => $this->pricing_for($tid),
                    'slots'   => [],
                ];
            }
            if (count($out[$tid]['slots']) >= (int) $limit_slots) continue;

            $out[$tid]['slots'][] = [
                'id'           => (int) $r['slot_id'],
                'starts_at'    => $r['starts_at'],
                'when_text'    => $this->when_text($r['starts_at'], (int) $r['duration_min']),
                'minutes'      => (int) $r['duration_min'],
                'grade_id'     => (int) ($r['grade_id'] ?? 0),
                'grade_name'   => $this->grade_name((int) ($r['grade_id'] ?? 0)),
                'subject_id'   => $slot_subject,
                'subject_name' => $this->subject_name($slot_subject),
            ];
        }

        /* ومادة البطاقة تشتق من **المعروض** لا من أول مادة للمعلم: من يدرس
           مادتين كان يقرأ اسمه تحته «اللغة العربية» وكل مواعيده المعروضة
           رياضيات — والقائمة تحته تكذب رأسها. ومن لا مادة على مواعيده
           (ما فتح قبل TQ-SESSION-GRID) يبقى على ما كان يعرض حرفا بحرف. */
        foreach ($out as $tid => $t) {
            $names = array();
            foreach ($t['slots'] as $sl) {
                if ((string) $sl['subject_name'] !== '') $names[$sl['subject_name']] = true;
            }
            if ($names) $out[$tid]['subject'] = implode(' · ', array_keys($names));
        }

        return array_values($out);
    }

    /**
     * مادة كل معلم — من كورساته المنشورة عبر مسارها (`paths.subject_id`).
     * وهذا كل ما تعرفه القاعدة عن تخصصه؛ من لا كورس له لا مادة له، فلا ينسب
     * إلى مادة لم يدرسها.
     *
     * وكان المصدر `course.category_id` — وهو صفر في كل كورس منشور، فلا
     * ينسب معلم إلى مادة قط، وتصفية «اختر المادة» ترد الجميع.
     * و`category` جدول مراحل لا مواد؛ المواد في `subjects`.
     */
    public function teacher_subjects()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $rows = $this->db->select('c.user_id, p.subject_id, s.name_ar', false)
            ->from('paths p')
            ->join('course c', 'c.id = p.course_id', 'inner')
            ->join('subjects s', 's.id = p.subject_id', 'inner')
            ->where('c.status', 'active')
            ->get()->result_array();

        $by_teacher = [];
        $first      = [];
        foreach ($rows as $r) {
            /* `course.user_id` قائمة معرفات مفصولة بفواصل في Academy،
               فالمعلم الثاني في كورس مشترك ينسب إلى مادته كذلك. */
            foreach (explode(',', (string) $r['user_id']) as $raw) {
                $tid = (int) trim($raw);
                $sid = (int) $r['subject_id'];
                if ($tid <= 0 || $sid <= 0) continue;
                $by_teacher[$tid][] = $sid;
                if (!isset($first[$tid])) $first[$tid] = (string) $r['name_ar'];
            }
        }

        return $cache = ['cats' => $by_teacher, 'name' => $first];
    }

    /* =====================================================================
       الطلب
       ===================================================================== */

    /**
     * طلب حصة على موعد بعينه.
     *
     * كل شرط هنا يفحص في الخادم: الموعد قائم، ومفتوح، ولم يمض، وليس للطالب
     * طلب قائم عليه. والموعد يصير `held` لا `booked` — الحجز لا يتم إلا بتأكيد.
     *
     * **والسعر يجمد هنا** لا عند التأكيد: الطالب قرأ رقما وضغط عليه، فرفع
     * الإدارة للتسعيرة بين طلبه ورد معلمه لا يغير ما وافق عليه.
     *
     * @return array ['ok'=>bool, 'msg'=>string, 'id'=>int]
     */
    public function request_session($student_id, $slot_id)
    {
        $this->install_schema();

        $student_id = (int) $student_id;
        $slot_id    = (int) $slot_id;
        if ($student_id <= 0 || $slot_id <= 0) {
            return ['ok' => false, 'msg' => 'طلب غير مكتمل.', 'id' => 0];
        }

        $slot = $this->db->where('id', $slot_id)->get('availability_slots')->row_array();
        if (!$slot) {
            return ['ok' => false, 'msg' => 'هذا الموعد لم يعد موجودا.', 'id' => 0];
        }

        /* فحص التكرار **قبل** فحص الحال: طلب الطالب نفسه يجعل الموعد `held`،
           فلو قرئ الحال أولا لقيل لمن ضغط الزر مرتين «سبقك غيرك» — وقد سبق
           نفسه. والرسالتان تقودان إلى فعلين مختلفين: الأولى تدعوه إلى موعد
           آخر، والثانية تطمئنه أن طلبه وصل. */
        $dup = $this->db->where('slot_id', $slot_id)
                        ->where('student_id', $student_id)
                        ->where_in('status', self::$LIVE_STATES)
                        ->count_all_results('tutoring_sessions');
        if ($dup > 0) {
            return ['ok' => false, 'msg' => 'طلبك على هذا الموعد قائم بالفعل.', 'id' => 0];
        }

        if ($slot['status'] !== 'open') {
            return ['ok' => false, 'msg' => 'سبقك غيرك إلى هذا الموعد. اختر موعدا آخر.', 'id' => 0];
        }
        if (strtotime($slot['starts_at']) <= time()) {
            return ['ok' => false, 'msg' => 'هذا الموعد مضى.', 'id' => 0];
        }
        if ((int) $slot['teacher_id'] === $student_id) {
            return ['ok' => false, 'msg' => 'لا تحجز حصة مع نفسك.', 'id' => 0];
        }

        /* TQ-SESSION-GRID — الصف يفحص في الخادم كما يرشح في الاستعلام:
           الشاشة تخفي ما ليس لصفه، ومعرف الموعد يرسل من متصفح فيكتبه من
           يشاء. والطالب بلا صف يمر — لا يفحص ما لا يعرف. */
        $slot_grade = (int) ($slot['grade_id'] ?? 0);
        if ($slot_grade > 0) {
            $mine = $this->student_grade($student_id);
            if ($mine > 0 && $mine !== $slot_grade) {
                return ['ok' => false, 'id' => 0,
                        'msg' => 'هذا الموعد مفتوح لطلاب ' . $this->grade_name($slot_grade)
                                 . '. اختر موعدا من مواعيد صفك.'];
            }
        }

        $p   = $this->pricing_for((int) $slot['teacher_id']);
        $now = date('Y-m-d H:i:s');

        $this->db->insert('tutoring_sessions', [
            'slot_id'               => $slot_id,
            'student_id'            => $student_id,
            'teacher_id'            => (int) $slot['teacher_id'],
            'status'                => 'requested',
            'price_halalas'         => $p['price'],
            'teacher_percent'       => $p['percent'],
            'teacher_share_halalas' => $p['share'],
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
        $id = (int) $this->db->insert_id();
        if ($id <= 0) {
            return ['ok' => false, 'msg' => 'تعذر حفظ الطلب. حاول مرة أخرى.', 'id' => 0];
        }

        // شرط `status = open` في التحديث نفسه: طلبان متزامنان لا يعلقان موعدا واحدا مرتين.
        $this->db->where('id', $slot_id)->where('status', 'open')
                 ->update('availability_slots', ['status' => 'held']);

        return ['ok' => true, 'id' => $id, 'teacher_id' => (int) $slot['teacher_id'],
                'msg' => $p['price'] > 0
                    ? 'أرسل طلبك إلى المعلم. لم يخصم شيء بعد — تدفع بعد أن يؤكد.'
                    : 'أرسل طلبك إلى المعلم.'];
    }

    /**
     * طلبات معلم بعينه. الشرط في الاستعلام لا في العرض.
     *
     * @param array $statuses حالات مطلوبة (فارغ = المعلقة وحدها)
     */
    public function requests_for_teacher($teacher_id, $statuses = ['requested'], $limit = 50)
    {
        $this->install_schema();

        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return [];

        $this->db->select('t.*, a.starts_at, a.duration_min, a.grade_id, a.subject_id,
                           u.first_name, u.last_name, u.image')
                 ->from('tutoring_sessions t')
                 ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                 ->join('users u', 'u.id = t.student_id', 'left')
                 ->where('t.teacher_id', $teacher_id);
        if ($statuses) $this->db->where_in('t.status', $statuses);

        $rows = $this->db->order_by('a.starts_at', 'ASC')->order_by('t.id', 'ASC')
                         ->limit((int) $limit)->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
            $j    = $this->join_state($r);
            $out[] = [
                'id'           => (int) $r['id'],
                'status'       => $r['status'],
                'student_id'   => (int) $r['student_id'],
                'student_name' => $name !== '' ? $name : 'طالب',
                'image'        => (string) $r['image'],
                'starts_at'    => $r['starts_at'],
                'when_text'    => $r['starts_at'] ? $this->when_text($r['starts_at'], (int) $r['duration_min']) : 'بلا موعد',
                'minutes'      => (int) $r['duration_min'],
                'grade_id'     => (int) ($r['grade_id'] ?? 0),
                'grade_name'   => $this->grade_name((int) ($r['grade_id'] ?? 0)),
                'subject_id'   => (int) ($r['subject_id'] ?? 0),
                'subject_name' => $this->subject_name((int) ($r['subject_id'] ?? 0)),
                'meet_url'     => (string) ($r['meet_url'] ?? ''),
                'price'        => (int) $r['price_halalas'],
                'percent'      => (float) $r['teacher_percent'],
                'share'        => (int) $r['teacher_share_halalas'],
                'paid_at'      => $r['paid_at'],
                'pay_deadline' => $r['pay_deadline'],
                'credited_at'  => $r['credited_at'],
                'can_join'     => $j['can_join'],
                'can_complete' => $j['can_complete'],
                'is_over'      => $j['is_over'],
                'note'         => $j['note'],
            ];
        }
        return $out;
    }

    /* =====================================================================
       قرار المعلم
       ===================================================================== */

    /**
     * يحسم المعلم طلبا: تأكيدا أو اعتذارا.
     * الملكية والحالة شرطان في الاستعلام: لا يحسم طلب غيره، ولا يحسم محسوم.
     *
     * والتأكيد **لا يحجز الموعد إن كان بثمن**: يصير `awaiting_payment`
     * والموعد يبقى `held` حتى يدفع. وهذا ليس تفصيلا: لو حجز قبل الدفع
     * لكان كل من طلب ولم يدفع يسقط موعدا من جدول معلمه إلى أن تمضي المهلة.
     *
     * @param string $decision confirm|decline
     */
    public function decide($session_id, $teacher_id, $decision, $meet_url = '', $reason = '')
    {
        $this->install_schema();

        $session_id = (int) $session_id;
        $teacher_id = (int) $teacher_id;
        if ($session_id <= 0 || $teacher_id <= 0) {
            return ['ok' => false, 'msg' => 'طلب غير مكتمل.'];
        }
        if (!in_array($decision, ['confirm', 'decline'], true)) {
            return ['ok' => false, 'msg' => 'إجراء غير معروف.'];
        }

        /* الرابط شرط في التأكيد لا حقل اختياري.
           «مؤكد» بلا رابط يقول للطالب إن الحصة قائمة ولا يقول أين — فيقف
           في موعده أمام شاشة بلا باب. ورفض التأكيد هنا أهون من موعد ضائع. */
        $meet = '';
        if ($decision === 'confirm') {
            $meet = $this->clean_meet_url($meet_url);
            if ($meet === '') {
                return ['ok' => false, 'msg' => 'ضع رابط اللقاء (' . $this->meet_hosts_text() . ') قبل التأكيد — الطالب يدخل الحصة منه.'];
            }
        }

        $row = $this->db->where('id', $session_id)->where('teacher_id', $teacher_id)
                        ->get('tutoring_sessions')->row_array();
        if (!$row) {
            return ['ok' => false, 'msg' => 'هذا الطلب ليس لك أو لم يعد موجودا.'];
        }
        if ($row['status'] !== 'requested') {
            return ['ok' => false, 'msg' => 'هذا الطلب حسم من قبل.'];
        }

        $now   = date('Y-m-d H:i:s');
        $price = (int) $row['price_halalas'];

        if ($decision === 'decline') {
            $this->db->where('id', $session_id)->where('teacher_id', $teacher_id)
                     ->where('status', 'requested')
                     ->update('tutoring_sessions', array(
                         'status'        => 'declined',
                         'meet_url'      => null,
                         'cancel_reason' => trim((string) $reason) !== '' ? mb_substr(trim($reason), 0, 255) : 'اعتذر المعلم',
                         'updated_at'    => $now,
                     ));
            if ($this->db->affected_rows() < 1) {
                return ['ok' => false, 'msg' => 'تعذر تحديث الطلب. حاول مرة أخرى.'];
            }
            $this->release_slot($row);
            return ['ok' => true, 'state' => 'declined',
                    'msg' => 'اعتذر عن الطلب، وعاد الموعد متاحا.'];
        }

        /* الحصة بلا ثمن تؤكد كما كانت تؤكد قبل أن يصير للحصص ثمن: بلا
           فاتورة ولا مهلة ولا شاشة دفع. فمنصة لم تضبط تسعيرتها بعد تعمل
           اليوم كما عملت أمس، وهي القاعدة نفسها في بوابة تاب («بلا مفاتيح
           لا شيء يتغير»). */
        if ($price <= 0) {
            $this->db->where('id', $session_id)->where('teacher_id', $teacher_id)
                     ->where('status', 'requested')
                     ->update('tutoring_sessions', array(
                         'status'       => 'confirmed',
                         'meet_url'     => $meet,
                         'confirmed_at' => $now,
                         'updated_at'   => $now,
                     ));
            if ($this->db->affected_rows() < 1) {
                return ['ok' => false, 'msg' => 'تعذر تحديث الطلب. حاول مرة أخرى.'];
            }
            $this->book_slot($row);
            return ['ok' => true, 'state' => 'confirmed',
                    'msg' => 'أكد الطلب، وصار الموعد محجوزا.'];
        }

        /* المهلة أقصر الأجلين: مهلة الدفع، وبداية الحصة نفسها. مهلة تمتد
           بعد موعد الحصة تعني طالبا يدفع ثمن حصة فاتت. */
        $deadline = time() + $this->config()['pay_hours'] * 3600;
        $starts   = $row['slot_id'] ? strtotime((string) $this->slot_start($row['slot_id'])) : 0;
        if ($starts > 0 && $starts < $deadline) $deadline = $starts;

        $inv = $this->issue_invoice($row);
        if ($inv <= 0) {
            return ['ok' => false, 'msg' => 'تعذر إصدار فاتورة الحصة. حاول مرة أخرى.'];
        }

        $this->db->where('id', $session_id)->where('teacher_id', $teacher_id)
                 ->where('status', 'requested')
                 ->update('tutoring_sessions', array(
                     'status'       => 'awaiting_payment',
                     'meet_url'     => $meet,
                     'invoice_id'   => $inv,
                     'pay_deadline' => date('Y-m-d H:i:s', $deadline),
                     'confirmed_at' => $now,
                     'updated_at'   => $now,
                 ));
        if ($this->db->affected_rows() < 1) {
            return ['ok' => false, 'msg' => 'تعذر تحديث الطلب. حاول مرة أخرى.'];
        }

        return ['ok' => true, 'state' => 'awaiting_payment', 'invoice_id' => $inv,
                'deadline' => date('Y-m-d H:i:s', $deadline),
                'msg' => 'أكدت الحصة، وأرسلت فاتورتها إلى الطالب. تثبت الحصة حين يدفع.'];
    }

    /**
     * فاتورة حصة — بالباب الذي تصدر منه كل فاتورة في المنصة.
     *
     * و`subscription_id = 0`: الحصة ليست اشتراكا، ورقم اشتراك موضوع هنا
     * ليمر الاستعلام يجعل `activate_from_gateway()` تفعل اشتراكا لا وجود
     * له. والفاتورة اليتيمة تعرف بحصتها لا بالعكس (`by_invoice()`).
     */
    private function issue_invoice($row)
    {
        $this->load->model('taqdar_billing_model');
        try {
            return (int) $this->taqdar_billing_model->issue_invoice(
                0, (int) $row['student_id'], (int) $row['price_halalas'], null
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-SESSION: تعذر إصدار فاتورة الحصة #' . (int) $row['id'] . ' — ' . $e->getMessage());
            return 0;
        }
    }

    private function slot_start($slot_id)
    {
        $r = $this->db->select('starts_at')->where('id', (int) $slot_id)
                      ->get('availability_slots')->row_array();
        return $r ? $r['starts_at'] : null;
    }

    /**
     * يعيد الموعد مفتوحا — ولا يفتح موعدا يشغله طلب آخر حي.
     * الموعد الواحد قد يحمل طلبين: واحدا ألغي وواحدا قائما، وفتحه بالأول
     * يبيعه لثالث بينما صاحبه الثاني ينتظر رد معلمه.
     */
    private function release_slot($row)
    {
        if (empty($row['slot_id'])) return;

        $busy = (int) $this->db->where('slot_id', (int) $row['slot_id'])
                               ->where('id !=', (int) $row['id'])
                               ->where_in('status', self::$LIVE_STATES)
                               ->count_all_results('tutoring_sessions');
        if ($busy > 0) return;

        $this->db->where('id', (int) $row['slot_id'])
                 ->update('availability_slots', array('status' => 'open'));
    }

    private function book_slot($row)
    {
        if (empty($row['slot_id'])) return;
        $this->db->where('id', (int) $row['slot_id'])
                 ->where('teacher_id', (int) $row['teacher_id'])
                 ->update('availability_slots', array('status' => 'booked'));
    }

    /* =====================================================================
       الدفع
       ===================================================================== */

    /** الحصة التي تخص فاتورة — مفتاح تسوية الدفعة. */
    public function by_invoice($invoice_id)
    {
        $this->install_schema();
        if ((int) $invoice_id <= 0) return null;
        return $this->db->where('invoice_id', (int) $invoice_id)
                        ->order_by('id', 'DESC')->limit(1)
                        ->get('tutoring_sessions')->row_array();
    }

    /**
     * تثبيت حصة دفع ثمنها — تنادى من `Taqdar_tap_model::settle()` ومن
     * التفعيل اليدوي في اللوحة. مأمونة التكرار: حصة مثبتة ترد `already`.
     *
     * @param string $method tap|manual
     */
    public function settle_invoice($invoice_id, $transaction_id = null, $method = 'tap')
    {
        $this->install_schema();

        $row = $this->by_invoice($invoice_id);
        if (!$row) return array('ok' => false, 'msg' => 'لا حصة تقابل هذه الفاتورة.');

        if (in_array($row['status'], array('confirmed', 'live', 'completed'), true)) {
            return array('ok' => true, 'already' => true, 'session_id' => (int) $row['id'],
                         'msg' => 'هذه الحصة مثبتة من قبل.');
        }
        if ($row['status'] !== 'awaiting_payment') {
            /* دفعة وصلت على حصة اعتذر عنها أو مضت مهلتها. المال وصل فعلا،
               فلا يقال «لم يدفع» ولا تفتح حصة أغلقت — يسجل ويرده المسؤول.
               والسكوت هنا يعني طالبا خصم منه ولا أثر لخصمه في موضع. */
            log_message('error', 'TQ-SESSION-LATE: دفعت فاتورة #' . (int) $invoice_id
                . ' لحصة #' . (int) $row['id'] . ' وحالها ' . $row['status']);
            $this->audit('session.paid_late', (int) $row['id'], array(
                'invoice_id' => (int) $invoice_id, 'status' => $row['status'],
                'amount' => (int) $row['price_halalas'],
            ));
            return array('ok' => false, 'session_id' => (int) $row['id'], 'late' => true,
                         'msg' => 'وصل دفعك بعد أن أغلق الموعد. لا تعد الدفع — يراجع طلبك ويرد المبلغ.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $row['id'])->where('status', 'awaiting_payment')
                 ->update('tutoring_sessions', array(
                     'status'     => 'confirmed',
                     'paid_at'    => $now,
                     'updated_at' => $now,
                 ));
        if ($this->db->affected_rows() < 1) {
            return array('ok' => false, 'session_id' => (int) $row['id'],
                         'msg' => 'تعذر تثبيت الحصة.');
        }

        $this->book_slot($row);

        $this->load->model('taqdar_billing_model');
        $inv = $this->db->where('id', (int) $invoice_id)->get('invoices')->row_array();
        if ($inv && $inv['status'] !== 'paid') {
            $this->taqdar_billing_model->mark_invoice_paid((int) $invoice_id, $transaction_id);
            $this->db->where('id', (int) $invoice_id)->update('invoices', array('method' => $method));
        }

        $this->audit('session.paid', (int) $row['id'], array(
            'method' => $method, 'invoice_id' => (int) $invoice_id,
            'amount' => (int) $row['price_halalas'],
        ));

        return array('ok' => true, 'session_id' => (int) $row['id'],
                     'teacher_id' => (int) $row['teacher_id'],
                     'msg' => 'ثبتت الحصة، ورابط الدخول في شاشة حصصك.');
    }

    /* =====================================================================
       الانعقاد والانتهاء
       ===================================================================== */

    /**
     * حال الرابط: متى يفتح، ومتى يموت، ولماذا.
     *
     * والرابط **يموت بالانتهاء لا بالمرور وحده**: حصة أعلن معلمها انتهاءها
     * لا يفتحها الطالب بعدها ولو بقيت دقائق من وقتها. وحصة مضى وقتها ولم
     * يعلن انتهاؤها يموت رابطها بمهلة السماح — غرفة تركت مفتوحة أسبوعا
     * باب إلى لقاء لا يحرسه أحد.
     *
     * ويفتح قبل الموعد بمهلة: رابط يفتح قبل يومين يجعل الطالب يدخل غرفة
     * فارغة ويظن أن معلمه تخلف.
     */
    public function join_state($row, $now = null)
    {
        $now    = $now ? (int) $now : time();
        $c      = $this->config();
        $status = (string) ($row['status'] ?? '');
        $starts = !empty($row['starts_at']) ? strtotime((string) $row['starts_at']) : 0;
        $mins   = (int) ($row['duration_min'] ?? 0) ?: $c['minutes'];
        $ends   = $starts > 0 ? $starts + $mins * 60 : 0;
        $dead   = $ends > 0 ? $ends + $c['grace_hours'] * 3600 : 0;
        $opens  = $starts > 0 ? $starts - $c['lead_min'] * 60 : 0;

        $done    = in_array($status, array('completed', 'declined', 'expired', 'refunded'), true);
        $is_over = $done || ($dead > 0 && $dead < $now);
        $ready   = in_array($status, array('confirmed', 'live'), true);
        $link    = trim((string) ($row['meet_url'] ?? ''));

        $note = '';
        if ($status === 'awaiting_payment')      $note = 'ادفع لتثبيت الحصة — الرابط يفتح بعد الدفع.';
        elseif ($status === 'requested')         $note = 'بانتظار رد المعلم. لم يخصم منك شيء.';
        elseif ($status === 'completed')         $note = 'انتهت الحصة، وأغلق رابطها.';
        elseif ($status === 'refunded')          $note = 'استرد مبلغ هذه الحصة.';
        elseif ($ready && $link === '')          $note = 'لا رابط بعد — راسل معلمك.';
        elseif ($ready && $is_over)              $note = 'مضى وقت هذه الحصة، وأغلق رابطها.';
        elseif ($ready && $opens > $now)         $note = 'الرابط يفتح قبل الموعد بـ' . $c['lead_min'] . ' دقيقة.';

        return array(
            'can_join'     => $ready && !$is_over && $link !== '' && ($opens <= 0 || $opens <= $now),
            'can_complete' => $ready && $starts > 0 && $starts <= $now,
            'is_over'      => $is_over,
            'starts_ts'    => $starts,
            'ends_ts'      => $ends,
            /* TQ-I18N — الملاحظة تحسب في كل طلب ولا تخزن، فتترجم هنا بلغة
               من يقرؤها. وترجمتها عند الكتابة لا معنى له: لا كتابة أصلا. */
            'note'         => t($note),
        );
    }

    /**
     * يعلن المعلم انتهاء الحصة — **وهنا يقيد نصيبه**.
     *
     * وهي اللحظة الوحيدة التي يدخل فيها مال الحصة دفتر معلمها: لا عند
     * الدفع، ولا عند التأكيد. فما يقرؤه المعلم في محفظته حصص انعقدت.
     *
     * ولا يعلن انتهاء حصة لم تبدأ: زر ينهي حصة الغد يجعل «انتهت» تعني
     * «ضغطت الزر»، ويقيد مالا مقابل وقت لم يعط بعد.
     *
     * @param int    $teacher_id صفر للنداء الإداري أو الآلي
     * @param string $actor      teacher|admin|cron
     */
    public function complete($session_id, $teacher_id, $actor = 'teacher')
    {
        $this->install_schema();

        $session_id = (int) $session_id;
        $this->db->select('t.*, a.starts_at, a.duration_min')
                 ->from('tutoring_sessions t')
                 ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                 ->where('t.id', $session_id);
        if ((int) $teacher_id > 0) $this->db->where('t.teacher_id', (int) $teacher_id);
        $row = $this->db->get()->row_array();

        if (!$row) return array('ok' => false, 'msg' => 'هذه الحصة ليست لك أو لم تعد موجودة.');
        if ($row['status'] === 'completed') {
            return array('ok' => true, 'already' => true, 'msg' => 'هذه الحصة معلنة منتهية من قبل.');
        }
        if (!in_array($row['status'], array('confirmed', 'live'), true)) {
            return array('ok' => false, 'msg' => 'لا تنهى إلا حصة مثبتة.');
        }

        $j = $this->join_state($row);
        if ($actor === 'teacher' && !$j['can_complete']) {
            return array('ok' => false, 'msg' => 'لم يحن موعد هذه الحصة بعد.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('id', $session_id)
                 ->where_in('status', array('confirmed', 'live'))
                 ->update('tutoring_sessions', array(
                     'status'       => 'completed',
                     'completed_at' => $now,
                     'updated_at'   => $now,
                 ));
        if ($this->db->affected_rows() < 1) {
            return array('ok' => false, 'msg' => 'تعذر إنهاء الحصة. حدث الصفحة وأعد المحاولة.');
        }

        $credit = $this->credit($row);

        $this->audit('session.complete', $session_id,
            array('by' => $actor, 'credited' => (int) ($credit['share'] ?? 0)));

        return array('ok' => true, 'credited' => $credit,
                     'student_id' => (int) $row['student_id'],
                     'teacher_id' => (int) $row['teacher_id'],
                     'msg' => (int) ($credit['share'] ?? 0) > 0
                         ? 'أنهيت الحصة، وقيد نصيبك في محفظتك.'
                         : 'أنهيت الحصة، وأغلق رابطها.');
    }

    /**
     * يقيد نصيب المعلم من حصة انعقدت.
     *
     * وشرطه الدفع لا الانتهاء وحده: حصة بثمن لم يدفع لا تقيد لأحد، وحصة
     * بلا ثمن لا شيء فيها يقيد. **وفشل القيد لا يبطل الانتهاء** — الحصة
     * انعقدت فعلا، والدفتر يصالح بعدها؛ ومنع الانتهاء لأن قيدا لم يكتب
     * يبقي رابط الحصة مفتوحا عقوبة على عطل ليس من أحد.
     */
    private function credit($row)
    {
        $share = (int) ($row['teacher_share_halalas'] ?? 0);
        $price = (int) ($row['price_halalas'] ?? 0);
        if ($price <= 0 || empty($row['paid_at'])) return array('share' => 0);

        try {
            $this->load->model('taqdar_wallet_model');
            $name = $this->student_name((int) $row['student_id']);
            $when = !empty($row['starts_at']) ? date('Y-m-d', strtotime($row['starts_at'])) : date('Y-m-d');

            $r = $this->taqdar_wallet_model->credit_session(
                (int) $row['teacher_id'], (int) $row['id'], $price, $share,
                'حصة خاصة — ' . $name . ' — ' . $when
            );
            if (!empty($r['ok'])) {
                $this->db->where('id', (int) $row['id'])
                         ->update('tutoring_sessions', array('credited_at' => date('Y-m-d H:i:s')));
                return $r;
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-SESSION-CREDIT: تعذر قيد حصة #' . (int) $row['id'] . ' — ' . $e->getMessage());
        }
        return array('share' => 0);
    }

    private function student_name($student_id)
    {
        $u = $this->db->select('first_name, last_name')->where('id', (int) $student_id)
                      ->get('users')->row_array();
        if (!$u) return 'طالب';
        $n = trim((string) $u['first_name'] . ' ' . (string) $u['last_name']);
        return $n !== '' ? $n : 'طالب';
    }

    /* =====================================================================
       الإلغاء والاسترداد
       ===================================================================== */

    /**
     * يلغي الطالب حجزه — **قبل الدفع وحده**.
     *
     * وبعد الدفع لا يلغي بنفسه: المال خرج من بطاقته ودخل حساب المنصة،
     * وزر يلغي بلا رد يترك الطالب بلا حصة وبلا مال. فيوجه إلى الإدارة،
     * وهي التي ترد وتعكس.
     */
    public function student_cancel($session_id, $student_id, $reason = '')
    {
        $this->install_schema();

        $row = $this->db->where('id', (int) $session_id)
                        ->where('student_id', (int) $student_id)
                        ->get('tutoring_sessions')->row_array();
        if (!$row) return array('ok' => false, 'msg' => 'هذا الحجز ليس لك أو لم يعد موجودا.');

        if (!in_array($row['status'], array('requested', 'awaiting_payment'), true)) {
            return array('ok' => false, 'msg' => in_array($row['status'], array('confirmed', 'live'), true)
                ? 'هذه الحصة مدفوعة ومثبتة. راسل الإدارة لإلغائها واسترداد مبلغها.'
                : 'هذا الحجز مغلق أصلا.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $row['id'])->where('student_id', (int) $student_id)
                 ->where_in('status', array('requested', 'awaiting_payment'))
                 ->update('tutoring_sessions', array(
                     'status'        => 'declined',
                     'meet_url'      => null,
                     'cancel_reason' => trim((string) $reason) !== ''
                                        ? mb_substr(trim($reason), 0, 255) : 'ألغاه الطالب',
                     'updated_at'    => $now,
                 ));
        if ($this->db->affected_rows() < 1) {
            return array('ok' => false, 'msg' => 'تعذر إلغاء الحجز.');
        }

        $this->void_invoice($row);
        $this->release_slot($row);

        return array('ok' => true, 'teacher_id' => (int) $row['teacher_id'],
                     'msg' => 'ألغي حجزك، وعاد الموعد متاحا لغيرك.');
    }

    /**
     * يشطب فاتورة حصة لم تدفع.
     *
     * ولا تحذف: رقم الفاتورة متسلسل، وحذف صف يترك ثغرة في تسلسل يقرؤه
     * محاسب. فتوسم `refunded` — «صدرت ولم يعد لها ما يقابلها».
     */
    private function void_invoice($row)
    {
        $inv = (int) ($row['invoice_id'] ?? 0);
        if ($inv <= 0) return;
        $this->db->where('id', $inv)->where('status', 'unpaid')
                 ->update('invoices', array('status' => 'refunded'));
    }

    /**
     * قرار الإدارة: إلغاء حصة أو استرداد ثمنها.
     *
     * والفرق بين الحالين هو الدفع لا شيء آخر: حصة لم تدفع تلغى فتشطب
     * فاتورتها ويعود موعدها؛ وحصة دفعت **تسترد** فيعكس قيد معلمها إن كان
     * قيد، وتوسم فاتورتها مستردة.
     *
     * **ورد المال نفسه يقع خارج المنصة** — لا مسار استرداد في بوابة تاب
     * هنا — والرسالة تقول ذلك صراحة. ومسؤول يظن أن ضغطة زر أعادت مالا
     * إلى بطاقة يترك طالبا ينتظر تحويلا لن يأتي.
     *
     * **وحصة انعقدت تسترد.** كان `completed` مردودا مع `declined` و`expired`
     * بحجة أنها «مغلقة أصلا» — وهو خلط بين إغلاق لا شيء بعده وإغلاق بقي
     * فيه مال. والاسترداد أكثر ما يقع **بعد** الحصة لا قبلها: يتخلف المعلم،
     * أو تنقطع الشبكة، أو يشتكي الطالب. فكان الباب الوحيد المفتوح هو الباب
     * الذي لا يحتاجه أحد، والقيد يبقى في محفظة معلم عن حصة لم يعطها.
     */
    public function admin_cancel($session_id, $reason = '', $actor_id = 0)
    {
        $this->install_schema();

        $row = $this->db->select('t.*, a.starts_at, a.duration_min')
                        ->from('tutoring_sessions t')
                        ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                        ->where('t.id', (int) $session_id)->get()->row_array();
        if (!$row) return array('ok' => false, 'msg' => 'الحصة غير موجودة.');

        if (in_array($row['status'], array('declined', 'expired', 'refunded'), true)) {
            return array('ok' => false, 'msg' => 'هذه الحصة مغلقة أصلا، ولا مال فيها يرد.');
        }
        if ($row['status'] === 'completed' && empty($row['paid_at'])) {
            return array('ok' => false, 'msg' => 'هذه الحصة انتهت وكانت بلا ثمن، فلا شيء يرد.');
        }

        $paid = !empty($row['paid_at']);
        $new  = $paid ? 'refunded' : 'declined';
        $now  = date('Y-m-d H:i:s');

        $this->db->where('id', (int) $row['id'])
                 ->update('tutoring_sessions', array(
                     'status'        => $new,
                     'meet_url'      => null,
                     'cancel_reason' => trim((string) $reason) !== ''
                                        ? mb_substr(trim($reason), 0, 255) : 'ألغتها الإدارة',
                     'updated_at'    => $now,
                 ));

        if ($paid) {
            $this->db->where('id', (int) $row['invoice_id'])
                     ->update('invoices', array('status' => 'refunded'));
            try {
                $this->load->model('taqdar_wallet_model');
                $this->taqdar_wallet_model->reverse_session(
                    (int) $row['teacher_id'], (int) $row['id'], 'استردت الحصة');
            } catch (Throwable $e) {
                log_message('error', 'TQ-SESSION-REVERSE: تعذر عكس حصة #' . (int) $row['id']);
            }
        } else {
            $this->void_invoice($row);
        }

        $this->release_slot($row);
        $this->audit('session.' . ($paid ? 'refund' : 'cancel'), (int) $row['id'],
            array('reason' => $reason, 'actor' => (int) $actor_id, 'amount' => (int) $row['price_halalas']));

        return array('ok' => true, 'refunded' => $paid,
                     'student_id' => (int) $row['student_id'],
                     'teacher_id' => (int) $row['teacher_id'],
                     'amount'     => (int) $row['price_halalas'],
                     'msg' => $paid
                         ? 'وسمت الحصة مستردة وعكس قيد معلمها. رد المبلغ إلى الطالب من لوحة تاب — المنصة لا تردها بنفسها.'
                         : 'ألغيت الحصة، وشطبت فاتورتها، وحرر وقتها.');
    }

    /* =====================================================================
       دورة الحياة الآلية — الكرون
       ===================================================================== */

    /**
     * ينقل كل حصة إلى حالها الصحيح بمرور الوقت. أربع خطوات لا واحدة،
     * وكل خطوة تسد بابا كان يبقى مفتوحا إلى الأبد:
     *
     * ١ — طلب بلا رد: يخرج بمهلته من `created_at`، أو ببلوغ موعد الحصة.
     *     وكان الشرط `starts_at < NOW() - 24h` أي أنه لا ينتهي إلا بعد
     *     يوم من **فوات** الموعد — فيبقى وقت المعلم مشغولا يوما بعد أن
     *     مضى، والطالب ينتظر ردا على موعد فات.
     * ٢ — تأكيد بلا دفع: تمضي المهلة فيعود الموعد لغيره وتشطب فاتورته.
     * ٣ — حصة حل وقتها: `confirmed` ← `live` ليقرأ الجدول ما يقع فعلا.
     * ٤ — حصة مضت ولم يعلن معلمها انتهاءها: تنهى بمهلة السماح **ويقيد
     *     نصيبه**. ومعلم ينسى الزر لا يخسر ماله، ولا يبقى رابط حصة حيا
     *     لأن أحدا لم يضغط شيئا.
     */
    public function lifecycle_tick()
    {
        $this->install_schema();

        $c   = $this->config();
        $now = time();
        $out = array('expired_requests' => 0, 'expired_unpaid' => 0, 'went_live' => 0,
                     'completed' => 0, 'credited' => 0, 'laid_out' => 0);

        /* ٠ — فرش الشبكة. القاعدة أسبوعية دائمة والموعد صف في يوم بعينه،
           فبلا مرور يجدده ينفد ما فرش في آخر حفظ: يظهر المعلم أسبوعين ثم
           يختفي من شاشة الطالب بلا أن يغلق وقته أحد، ولا شيء يقول لماذا.
           والفرش مأمون التكرار — يدرج ما ينقص ولا يمس محجوزا. */
        $out['laid_out'] = $this->layout_all($now);

        /* ١ — طلب بلا رد. */
        $cut  = date('Y-m-d H:i:s', $now - $c['pay_hours'] * 3600);
        $rows = $this->db->select('t.id, t.slot_id, t.teacher_id, t.student_id, t.invoice_id, a.starts_at')
                         ->from('tutoring_sessions t')
                         ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                         ->where('t.status', 'requested')
                         ->group_start()
                             ->where('t.created_at <', $cut)
                             ->or_where('a.starts_at <=', date('Y-m-d H:i:s', $now))
                         ->group_end()
                         ->limit(200)->get()->result_array();
        foreach ($rows as $r) {
            $this->db->where('id', (int) $r['id'])->where('status', 'requested')
                     ->update('tutoring_sessions', array(
                         'status' => 'expired', 'updated_at' => date('Y-m-d H:i:s'),
                         'cancel_reason' => 'مضت مهلة رد المعلم'));
            if ($this->db->affected_rows() < 1) continue;
            $out['expired_requests']++;
            $this->release_slot($r);
            $this->tell((int) $r['student_id'], 'ألغي طلب حصتك',
                'مضت مهلة رد المعلم على طلبك، فألغي تلقائيا وعاد الموعد متاحا. '
                . 'لم يخصم منك شيء — اختر موعدا آخر من «حصص بالطلب».');
            $this->tell((int) $r['teacher_id'], 'ألغي طلب حصة لم ترد عليه',
                'مضت مهلة الرد على طلب حصة، فألغي وعاد الموعد متاحا في جدولك.');
        }

        /* ٢ — تأكيد بلا دفع. */
        $rows = $this->db->select('t.id, t.slot_id, t.invoice_id, t.teacher_id, t.student_id')
                         ->from('tutoring_sessions t')
                         ->where('t.status', 'awaiting_payment')
                         ->where('t.pay_deadline IS NOT NULL', null, false)
                         ->where('t.pay_deadline <', date('Y-m-d H:i:s', $now))
                         ->limit(200)->get()->result_array();
        foreach ($rows as $r) {
            $this->db->where('id', (int) $r['id'])->where('status', 'awaiting_payment')
                     ->update('tutoring_sessions', array(
                         'status' => 'expired', 'meet_url' => null,
                         'updated_at' => date('Y-m-d H:i:s'),
                         'cancel_reason' => 'مضت مهلة الدفع'));
            if ($this->db->affected_rows() < 1) continue;
            $out['expired_unpaid']++;
            $this->void_invoice($r);
            $this->release_slot($r);
            $this->tell((int) $r['student_id'], 'مضت مهلة دفع حصتك',
                'لم يصل دفع ثمن الحصة قبل انتهاء المهلة، فعاد الموعد متاحا لغيرك '
                . 'وشطبت فاتورته. لم يخصم منك شيء، ولك أن تطلب موعدا آخر.');
            $this->tell((int) $r['teacher_id'], 'لم يدفع الطالب ثمن الحصة',
                'مضت مهلة الدفع على حصة أكدتها، فعاد الموعد متاحا في جدولك.');
        }

        /* ٣ — حل وقتها. */
        $this->db->query(
            'UPDATE `tutoring_sessions` t
               JOIN `availability_slots` a ON a.`id` = t.`slot_id`
                SET t.`status` = "live", t.`started_at` = NOW(), t.`updated_at` = NOW()
              WHERE t.`status` = "confirmed" AND a.`starts_at` <= NOW()'
        );
        $out['went_live'] = (int) $this->db->affected_rows();

        /* ٤ — مضت ولم تنه. */
        $rows = $this->db->select('t.*, a.starts_at, a.duration_min')
                         ->from('tutoring_sessions t')
                         ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                         ->where_in('t.status', array('confirmed', 'live'))
                         ->where('a.starts_at IS NOT NULL', null, false)
                         ->limit(200)->get()->result_array();
        foreach ($rows as $r) {
            if (!$this->join_state($r, $now)['is_over']) continue;
            $this->db->where('id', (int) $r['id'])
                     ->where_in('status', array('confirmed', 'live'))
                     ->update('tutoring_sessions', array(
                         'status' => 'completed', 'completed_at' => date('Y-m-d H:i:s'),
                         'updated_at' => date('Y-m-d H:i:s')));
            if ($this->db->affected_rows() < 1) continue;
            $out['completed']++;
            $c = $this->credit($r);
            $out['credited'] += (int) ($c['share'] ?? 0);

            $this->tell((int) $r['student_id'], 'أغلقت حصتك',
                'مضى وقت الحصة ولم يعلن معلمها انتهاءها، فأغلقت تلقائيا وأغلق رابطها. '
                . 'إن لم تنعقد الحصة فراسل الإدارة.');
            if ((int) ($c['share'] ?? 0) > 0) {
                $this->tell((int) $r['teacher_id'], 'أغلقت حصة تلقائيا وقيد نصيبك',
                    'مضى وقت الحصة ولم تعلن انتهاءها، فأغلقت تلقائيا وقيد نصيبك في محفظتك. '
                    . 'وإعلان الانتهاء بنفسك أدق: به يعرف طالبك أن الحصة تمت.');
            }
        }

        return $out;
    }

    /**
     * إشعار طرف بما وقع على حصته.
     *
     * والباب واحد — `push_notification()` — لا كتابة مباشرة في
     * `notifications`: هو الذي يكتب الصف ويرسل البريد وواتساب بحسب النوع.
     * **وفشله لا يوقف الدورة**: ما تغير في القاعدة تغير، ورسالة لم تخرج
     * لا تعيد حصة إلى حالها.
     */
    private function tell($user_id, $title, $text)
    {
        if ((int) $user_id <= 0) return;
        try {
            $this->load->model('taqdar_admin_model');
            $this->taqdar_admin_model->push_notification((int) $user_id, $title, $text, 'session');
        } catch (Throwable $e) {
            log_message('error', 'TQ-SESSION-NOTIFY: تعذر إشعار #' . (int) $user_id);
        }
    }

    /* =====================================================================
       حجوزات الطالب
       ===================================================================== */

    /** حجوزات الطالب بمواعيدها ومعلميها وحالتها وثمنها. */
    public function bookings_for_student($student_id, $limit = 20)
    {
        $this->install_schema();

        $student_id = (int) $student_id;
        if ($student_id <= 0) return [];

        $rows = $this->db->select('t.*, a.starts_at, a.duration_min, a.grade_id, a.subject_id,
                                   u.id AS tutor_id, u.first_name, u.last_name, u.image,
                                   i.invoice_no, i.total AS invoice_total, i.status AS invoice_status')
            ->from('tutoring_sessions t')
            ->join('availability_slots a', 'a.id = t.slot_id', 'left')
            ->join('users u', 'u.id = t.teacher_id', 'left')
            ->join('invoices i', 'i.id = t.invoice_id', 'left')
            ->where('t.student_id', $student_id)
            ->order_by('a.starts_at', 'ASC')->order_by('t.id', 'DESC')
            ->limit((int) $limit)->get()->result_array();

        $subjects = $this->teacher_subjects();

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
            $j    = $this->join_state($r);

            $out[] = [
                'id'            => (int) $r['id'],
                'status'        => $r['status'],
                'tutor'         => $name !== '' ? $name : 'معلم',
                'tutor_id'      => (int) $r['tutor_id'],
                'image'         => (string) $r['image'],
                /* مادة **الموعد** أولا: هي التي فتح لها معلمه وقته، ومادة
                   المعلم الأولى تكذب على من يدرس مادتين. */
                'subject'       => $this->subject_name((int) ($r['subject_id'] ?? 0)) !== ''
                                     ? $this->subject_name((int) ($r['subject_id'] ?? 0))
                                     : ($subjects['name'][(int) $r['tutor_id']] ?? 'حصة خاصة'),
                'subject_id'    => (int) ($r['subject_id'] ?? 0),
                'starts_at'     => $r['starts_at'],
                'when_text'     => $r['starts_at'] ? $this->when_text($r['starts_at'], (int) $r['duration_min']) : 'بلا موعد',
                'grade_id'      => (int) ($r['grade_id'] ?? 0),
                'grade_name'    => $this->grade_name((int) ($r['grade_id'] ?? 0)),
                'minutes'       => (int) ($r['duration_min'] ?: $this->config()['minutes']),
                'meet_url'      => (string) ($r['meet_url'] ?? ''),
                'price'         => (int) $r['price_halalas'],
                'invoice_id'    => (int) $r['invoice_id'],
                'invoice_no'    => (string) ($r['invoice_no'] ?? ''),
                'invoice_total' => (int) ($r['invoice_total'] ?? 0),
                'pay_deadline'  => $r['pay_deadline'],
                'cancel_reason' => (string) ($r['cancel_reason'] ?? ''),
                'needs_pay'     => $r['status'] === 'awaiting_payment',
                'can_cancel'    => in_array($r['status'], array('requested', 'awaiting_payment'), true),
                'can_join'      => $j['can_join'],
                'is_over'       => $j['is_over'],
                'note'          => $j['note'],
            ];
        }
        return $out;
    }

    /* =====================================================================
       ملخصات
       ===================================================================== */

    /** أرقام شاشة المعلم — استعلام واحد لا خمسة. */
    public function teacher_summary($teacher_id)
    {
        $this->install_schema();
        $teacher_id = (int) $teacher_id;

        $r = $this->safe_row(
            'SELECT
                SUM(CASE WHEN `status` = "requested" THEN 1 ELSE 0 END)           pending,
                SUM(CASE WHEN `status` = "awaiting_payment" THEN 1 ELSE 0 END)    unpaid,
                SUM(CASE WHEN `status` IN ("confirmed","live") THEN 1 ELSE 0 END) booked,
                SUM(CASE WHEN `status` = "completed" THEN 1 ELSE 0 END)           done,
                COALESCE(SUM(CASE WHEN `status` = "completed" AND `paid_at` IS NOT NULL
                             THEN `teacher_share_halalas` ELSE 0 END), 0)         earned,
                COALESCE(SUM(CASE WHEN `status` IN ("confirmed","live")
                             THEN `teacher_share_halalas` ELSE 0 END), 0)         upcoming
               FROM `tutoring_sessions` WHERE `teacher_id` = ?',
            array($teacher_id)
        );

        $open = (int) $this->db->where('teacher_id', $teacher_id)->where('status', 'open')
                               ->where('starts_at >', date('Y-m-d H:i:s'))
                               ->count_all_results('availability_slots');

        /* عدد القواعد لا عدد المواعيد: «مواعيد مفتوحة» رقم يتحرك وحده كل
           يوم، و«أوقات أسبوعية» هو ما كتبه المعلم بيده — ورقم لا يطابق ما
           كتب يقرأ عطلا. */
        $wins = 0;
        try {
            $wins = (int) $this->db->where('teacher_id', $teacher_id)
                                   ->count_all_results('tq_teacher_windows');
        } catch (Throwable $e) { $this->db->reset_query(); }

        return array(
            'windows'  => $wins,
            'pending'  => (int) ($r['pending'] ?? 0),
            'unpaid'   => (int) ($r['unpaid'] ?? 0),
            'booked'   => (int) ($r['booked'] ?? 0),
            'done'     => (int) ($r['done'] ?? 0),
            'earned'   => (int) ($r['earned'] ?? 0),
            'upcoming' => (int) ($r['upcoming'] ?? 0),
            'open'     => $open,
        );
    }

    /**
     * شارة الحالة: نصها ونوعها. الحالات الثمان كما في `tutoring_sessions.status`.
     *
     * والأنواع الخمسة التي يقبلها `tq_badge()` وحدها — وأي اسم آخر يسقط
     * إلى `idle` الرمادي بصمت. و`due` البرتقالي معناه **يحتاج فعلك أنت**،
     * فهو لانتظار الدفع لا لانتظار رد المعلم: الأول عليك، والثاني ليس
     * لك فيه ما تفعله.
     */
    public function status_badge($status)
    {
        $map = [
            'requested'        => ['idle',     'بانتظار رد المعلم'],
            'awaiting_payment' => ['due',      'بانتظار الدفع'],
            'confirmed'        => ['mastered', 'مؤكدة ومدفوعة'],
            'live'             => ['progress', 'جارية الآن'],
            'completed'        => ['idle',     'انتهت'],
            'declined'         => ['late',     'ألغيت'],
            'expired'          => ['late',     'مضت مهلتها'],
            'refunded'         => ['idle',     'مستردة'],
        ];
        /* TQ-I18N — الشارة تسمية تعرض ولا تخزن، فتترجم عند الخروج.
           والمفتاح (`idle`/`late`) صنف CSS لا نص، فيمر كما هو. */
        return tq_t_deep($map[$status] ?? ['idle', $status]);
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    private function safe_row($sql, $args = array())
    {
        try {
            $q = $this->db->query($sql, $args);
            return $q ? $q->row_array() : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * أثر كل قرار مال على حصة.
     * حصة تلغى أو تسترد أو تقيد نقل مال، ومن وجد الفراغ غدا يجب أن يعرف
     * من غيره ولماذا — وهي القاعدة نفسها في حذف الكورس وقسمة الإيراد.
     */
    private function audit($action, $session_id, $payload = array())
    {
        try {
            $actor = 0;
            if (isset($this->session)) $actor = (int) $this->session->userdata('user_id');
            $cli = isset($this->input) && $this->input->is_cli_request();

            $this->db->insert('audit_log', array(
                'actor_id' => $actor,
                'action'   => $action,
                'entity'   => 'tutoring_sessions#' . (int) $session_id,
                'after'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ip'       => $cli ? 'cli' : $this->input->ip_address(),
                'at'       => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) { /* السجل زيادة لا شرط */ }
    }

    /** توافق: الاسم القديم بقي منادى في شيفرة قائمة. */
    public function ensure_schema() { $this->install_schema(); }
}
