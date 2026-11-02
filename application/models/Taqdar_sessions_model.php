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
    const SCHEMA_V = '9';

    /**
     * TQ-FOUNDATION — نوعا الوقت، وهما بعد واحد لا محركان.
     *
     * `curriculum` حصة منهج: نطاقها (صف، مادة) من كورسات المعلم.
     * `foundation` حصة تأسيس: نطاقها مسار يسنده المسؤول، ولا صف ولا مادة.
     *
     * والقديم كله `curriculum` بحكم الافتراض في القاعدة، فما كان يعمل
     * يبقى يعمل حرفا بحرف. وانظر `Taqdar_foundation_model`.
     */
    const KIND_CURRICULUM = 'curriculum';
    const KIND_FOUNDATION = 'foundation';

    public static $KINDS = array('curriculum', 'foundation');

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

        /* ---- TQ-FOUNDATION — نوع الوقت ومساره ---------------------------
           عمودان لا جدول ثالث: القسم كله يعمل على هذه الجداول الثلاثة
           نفسها، والذي يتغير مصدر النطاق وحده. و`VARCHAR` لا `ENUM` —
           نوع ثالث يضاف يوما لا ينبغي أن يحتاج تعديل بنية على ثلاثة
           جداول. والافتراض `curriculum` فكل صف قائم يبقى كما هو.

           و**النوع يجمد على الحصة** كما يجمد سعرها: الموعد قد يحذف بعد
           انتهائها (`layout()` تنظف ما لا قاعدة له)، وشاشة تقرأ النوع
           من الموعد وحده تقول «حصة منهج» عن حصة تأسيس انتهت. */
        foreach (array('tq_teacher_windows', 'availability_slots', 'tutoring_sessions') as $tbl) {
            $this->try_sql('ALTER TABLE `' . $tbl . '` ADD COLUMN IF NOT EXISTS '
                . '`kind` VARCHAR(16) NOT NULL DEFAULT "' . self::KIND_CURRICULUM . '"');
            $this->try_sql('ALTER TABLE `' . $tbl . '` ADD COLUMN IF NOT EXISTS '
                . '`track_id` INT(10) UNSIGNED NOT NULL DEFAULT 0');
        }
        $this->try_sql('ALTER TABLE `availability_slots` ADD INDEX `idx_slot_kind` (`kind`,`track_id`,`status`,`starts_at`)');
        $this->try_sql('ALTER TABLE `tutoring_sessions` ADD INDEX `idx_se_kind` (`kind`,`track_id`)');

        /* ---- TQ-FND-PACK — الحصة المدفوعة سلفا ضمن باقة ----------------
           عمود واحد: معرف صف `subscriptions` الذي دفع ثمن هذه الحصة.
           وصفر يعني «حصة مفردة» — فكل صف قائم يبقى كما هو حرفا بحرف.

           و**هو مصدر الرصيد**: المستهلك من الباقة عدد الحصص التي تشير
           اليها وحالها حي او منته (`Taqdar_foundation_model::$SPENT`).
           فلا عداد يزاد وينقص، ولا انحراف بين ما يقرؤه الطالب وما يحرسه
           الخادم — وهو مبدأ `granted_book_ids()` نفسه: الاستحقاق يستعلم
           حيا ولا يجسد. */
        $this->try_sql('ALTER TABLE `tutoring_sessions` ADD COLUMN IF NOT EXISTS '
            . '`pack_sub_id` INT(10) UNSIGNED NOT NULL DEFAULT 0');
        $this->try_sql('ALTER TABLE `tutoring_sessions` ADD INDEX `idx_se_pack` (`pack_sub_id`,`status`)');

        /* ما فتحه المعلمون قبل اليوم يصير قواعد تقرأ في شاشتهم — وبلا هذا
           يفتحون الشاشة فيجدونها فارغة وقد حفظوا، ثم تمحو أول دورة فرش
           مواعيدهم لأن لا قاعدة تسندها. والصف يبقى صفرا («كل الصفوف»)
           فلا يضيق على أحد ما كان مفتوحا. */
        $this->backfill_windows();

        /* ---- TQ-LIVE (الاصدار 8) — ختما الدخول ورابط المعلم الثابت ----
           `attended_at` تختمه اول نقرة «انضم» من الطالب، و`teacher_joined_at`
           نقرة المعلم. و`users.tq_meet_url` رابط افتراضي لكل حصص المعلم
           (D2-b). وحال الموعد `closed` («اغلق هذا الموعد») تحتاج عمودا
           نصيا: سرد مغلق يكتب قيمة خارجه نصا فارغا ولا يخطئ. */
        $this->try_sql('ALTER TABLE `tutoring_sessions` ADD COLUMN IF NOT EXISTS `attended_at` DATETIME NULL DEFAULT NULL');
        $this->try_sql('ALTER TABLE `tutoring_sessions` ADD COLUMN IF NOT EXISTS `teacher_joined_at` DATETIME NULL DEFAULT NULL');
        $this->try_sql('ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `tq_meet_url` VARCHAR(512) NULL DEFAULT NULL');
        $this->try_sql('ALTER TABLE `tutoring_sessions` ADD COLUMN IF NOT EXISTS `nolink_alerted_at` DATETIME NULL DEFAULT NULL');
        $this->extend_slot_status_enum();

        /* ---- TQ-LIVE (الاصدار 9) — التاجيل والالغاء بسبب والحضور والتسجيل
           اضافة لا تبديل: اعمدة فارغة او بافتراض، ولا صف يمس. و`cancel_reason`
           قائم منذ الاصدار الاول (اعلاه) فلا يعاد. و`attendance` نص لا سرد:
           قيمة خارج سرد تكتب فارغة ولا تخطئ، والقيم تحرس في `clean_attendance()`.
           و`rescheduled_from` معرف **الموعد** القديم لا الحصة. */
        $v9 = array(
            'rescheduled_from'      => 'INT(10) UNSIGNED NULL DEFAULT NULL',
            'rescheduled_at'        => 'DATETIME NULL DEFAULT NULL',
            'rescheduled_by'        => 'VARCHAR(16) NULL DEFAULT NULL',
            'reschedule_count'      => 'INT(10) UNSIGNED NOT NULL DEFAULT 0',
            'cancelled_by'          => 'VARCHAR(16) NULL DEFAULT NULL',
            'attendance'            => 'VARCHAR(8) NULL DEFAULT NULL',
            'recording_url'         => 'VARCHAR(500) NULL DEFAULT NULL',
            'recording_pending'     => 'TINYINT(1) NOT NULL DEFAULT 0',
            'recording_reminded_at' => 'DATETIME NULL DEFAULT NULL',
            'teacher_absent_at'     => 'DATETIME NULL DEFAULT NULL',
            'refund_state'          => 'VARCHAR(20) NULL DEFAULT NULL',
        );
        foreach ($v9 as $name => $def) {
            $this->try_sql('ALTER TABLE `tutoring_sessions` ADD COLUMN IF NOT EXISTS `' . $name . '` ' . $def);
        }

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

    /**
     * TQ-LIVE — يضيف `closed` الى سرد حال الموعد **اضافة لا تبديلا**: يقرأ
     * السرد الحي من `information_schema` ويلحق القيمة في اخره بترتيب ما
     * قبلها كما هو. ولا يمس صفا. وعمود ليس سردا لا يمس اصلا.
     */
    private function extend_slot_status_enum()
    {
        try {
            $r = $this->db->query(
                "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'availability_slots'
                    AND COLUMN_NAME = 'status'")->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return; }
        if (!$r) return;
        $sql = self::enum_with_closed((string) $r['COLUMN_TYPE']);
        if ($sql === null) return;
        $def = $r['COLUMN_DEFAULT'];
        if ($def !== null) $def = trim((string) $def, "'");
        $this->try_sql('ALTER TABLE `availability_slots` MODIFY COLUMN `status` ' . $sql
            . ($r['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL')
            . ($def !== null && $def !== 'NULL' ? ' DEFAULT ' . $this->db->escape($def) : ''));
    }

    /** السرد بعد الحاق `closed`، او null ان لم يكن سردا او كانت فيه. */
    public static function enum_with_closed($column_type)
    {
        $t = trim((string) $column_type);
        if (stripos($t, 'enum(') !== 0) return null;
        if (preg_match("/'closed'/", $t)) return null;
        return substr($t, 0, -1) . ",'closed')";
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
        /* TQ-LIVE — مهلتا دخول لا مهلة: المعلم يفتح غرفته قبل طالبه.
           والقديم `tq_session_join_lead_min` يبقى مرادفا: ان وجد صفه ولم
           يوجد الجديدان اخذ للطرفين — فلا يتغير سلوك قائم بالنشر. */
        'tq_session_join_lead_student' => 10,
        'tq_session_join_lead_teacher' => 15,
        'tq_session_instant_book'      => 1,   // D1 — الدفع يثبت الحجز بلا تاكيد معلم
        'tq_session_hold_min'          => 15,  // D1-b — مهلة الحجز المؤقت اثناء الدفع
        'tq_session_refund_hours'      => 24,  // D5-b — حد الاسترداد عند الغاء الطالب
        'tq_session_reschedule_hours'  => 12,  // D6 — اخر حد لتاجيل الطالب قبل الموعد
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
        /* TQ-LIVE — المرادف القديم: يقرأ لطرف لا صف لمفتاحه الجديد، ما دام
           صفه هو قائما. والافتراض الجديد (١٠ و١٥) لمن لا صف له أصلا. */
        $set    = function ($k) use ($have) { return isset($have[$k]) && trim((string) $have[$k]) !== ''; };
        $legacy = $set('tq_session_join_lead_min') ? (int) $have['tq_session_join_lead_min'] : null;
        $lead   = function ($k) use ($set, $have, $legacy) {
            if ($set($k))         $v = (int) $have[$k];
            elseif ($legacy !== null) $v = $legacy;
            else                  $v = (int) self::$DEFAULTS[$k];
            return max(0, min(240, $v));
        };

        return self::$cfg_cache = array(
            'price'       => max(0, (int) round(((float) $c['tq_session_price_sar']) * 100)),
            'percent'     => max(0, min(100, (float) $c['tq_session_teacher_percent'])),
            'minutes'     => max(15, min(300, (int) $c['tq_session_minutes'])),
            'pay_hours'   => max(1, min(168, (int) $c['tq_session_pay_hours'])),
            /* `lead_min` بقي اسما لمهلة الطالب: شاشات وواجهة قائمة تقرؤه
               نصا («يفتح قبل الموعد بـ…») والطالب هو قارئها. */
            'lead_min'     => $lead('tq_session_join_lead_student'),
            'lead_student' => $lead('tq_session_join_lead_student'),
            'lead_teacher' => $lead('tq_session_join_lead_teacher'),
            'grace_hours'  => max(1, min(72, (int) $c['tq_session_grace_hours'])),
            'instant_book' => (string) $c['tq_session_instant_book'] !== '0',
            'hold_min'     => max(5, min(120, (int) $c['tq_session_hold_min'])),
            'refund_hours' => max(0, min(168, (int) $c['tq_session_refund_hours'])),
            'reschedule_hours' => max(0, min(168, (int) $c['tq_session_reschedule_hours'])),
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
     * TQ-FOUNDATION — ثمن موعد بعينه: بنوعه ومساره ومعلمه.
     *
     * **وهي الحاسبة الواحدة**: تناديها بطاقة المعلم في شاشة الطالب،
     * ويناديها `request_session()` حين يجمد السعر على الصف. ورقم يحسب
     * هنا مرة وهناك مرة يجعل الشاشة تعد بـ٨٠ والفاتورة تطلب ١٢٠ — وهو
     * عطل TQ-CYCLE-BUY نفسه بوجه آخر.
     */
    public function pricing_of_slot($kind, $track_id, $teacher_id)
    {
        if ($this->clean_kind($kind) === self::KIND_FOUNDATION) {
            $this->load->model('taqdar_foundation_model');
            return $this->taqdar_foundation_model->pricing_for((int) $track_id, (int) $teacher_id);
        }
        return $this->pricing_for((int) $teacher_id);
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

    /* =====================================================================
       TQ-FOUNDATION — «ما هذه الحصة؟» يسأل في ثمانية مواضع
       ===================================================================== */

    /**
     * TQ-SESSION-LABEL — وصف الحصة أو الموعد في سطر، من مصدر واحد.
     *
     * سؤال «هذه الحصة، في ماذا؟» يسأل في ثمانية مواضع: قائمة طلبات
     * المعلم، ومؤكداته، ومنتهياته، وحجوزات الطالب، وقائمة اللوحة، وشاشة
     * الأوقات، وإشعار التأكيد، وبطاقة الحجز. وكان كل واحد منها يكتب
     * جوابه بيده (`grade_name · subject_name`) — وثمان نسخ من قاعدة
     * واحدة تعني أن **النوع الثاني يضاف في بعضها وينسى في بقيتها**، فيقرأ
     * من حجز حصة تأسيس «الصف الثالث · —» أو فراغا لا يفسره شيء. وهي علة
     * TQ-SOLD-NAME نفسها في طبقة أخرى.
     *
     * فالجواب هنا مرة، ويقرأ الصف كما جاء من أي من الجداول الثلاثة.
     *
     * @param array $row صف فيه `kind` و`track_id`، وربما `grade_id`/`subject_id`
     * @return array kind · is_foundation · track_id · track_name · label
     */
    public function scope_of($row)
    {
        $kind  = (string) ($row['kind'] ?? self::KIND_CURRICULUM);
        $track = (int) ($row['track_id'] ?? 0);

        if ($kind === self::KIND_FOUNDATION) {
            $this->load->model('taqdar_foundation_model');
            $name = $this->taqdar_foundation_model->name_of($track);
            return array(
                'kind'          => self::KIND_FOUNDATION,
                'is_foundation' => true,
                'track_id'      => $track,
                'track_name'    => $name,
                'grade_name'    => '',
                'subject_name'  => '',
                'label'         => $name,
            );
        }

        $g = (int) ($row['grade_id'] ?? 0);
        $s = (int) ($row['subject_id'] ?? 0);
        $bits = array();
        if ($s > 0) $bits[] = $this->subject_name($s);
        if ($g > 0) $bits[] = $this->grade_name($g);

        return array(
            'kind'          => self::KIND_CURRICULUM,
            'is_foundation' => false,
            'track_id'      => 0,
            'track_name'    => '',
            'grade_name'    => $g > 0 ? $this->grade_name($g) : '',
            'subject_name'  => $s > 0 ? $this->subject_name($s) : '',
            'label'         => $bits ? implode(' · ', $bits) : t('حصة خاصة'),
        );
    }

    /** اسم النوع كما يعرض — شارة في قائمة تخلط النوعين. */
    public function kind_label($kind)
    {
        return ((string) $kind === self::KIND_FOUNDATION) ? t('تأسيس') : t('منهج');
    }

    /** يسوي ما وصل من متصفح إلى أحد النوعين — وما سواهما منهج. */
    public function clean_kind($kind)
    {
        $kind = trim((string) $kind);
        return in_array($kind, self::$KINDS, true) ? $kind : self::KIND_CURRICULUM;
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
            $b  = (int) $r['start_min'];
            $e  = (int) $r['end_min'];
            $sc = $this->scope_of($r);
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
                'kind'         => $sc['kind'],
                'track_id'     => $sc['track_id'],
                'track_name'   => $sc['track_name'],
                'scope_text'   => $sc['label'],
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

        /* TQ-FOUNDATION — والنطاق بابان لا باب.
           معلم التأسيس قد لا يملك كورسا واحدا، والشرط القديم يرده كله:
           «لا صف لك بعد» على من أسند إليه المسؤول مسار تأسيس بالأمس.
           فالمنع إنما يقع على من لا هذا ولا ذاك. */
        $this->load->model('taqdar_foundation_model');
        $tracks = $this->taqdar_foundation_model->teacher_tracks($teacher_id);

        if (!$mine && !$tracks) {
            return $bad('لا نطاق لك بعد، فلا تفتح وقتا لأحد. حصص المنهج تحتاج صفا ومادة '
                      . 'يشتقان من كورساتك، وحصص التأسيس تحتاج مسارا تسنده إليك الإدارة. '
                      . 'افتح كورسا في صفك، أو راجع الإدارة لتسند إليك مسار تأسيس.');
        }
        if ($mine && !$scope['subjects']) {
            return $bad('لا مادة لك بعد، فلا تفتح وقتا لأحد. المواد تشتق من كورساتك — '
                      . 'افتح كورسا أو راجع الإدارة لتسند إليك مادة.');
        }

        foreach ((array) $rows as $r) {
            $dow  = isset($r['dow']) && $r['dow'] !== '' ? (int) $r['dow'] : -1;
            $b    = $this->hhmm_to_min(isset($r['from']) ? $r['from'] : '');
            $e    = $this->hhmm_to_min(isset($r['to'])   ? $r['to']   : '');
            $g    = isset($r['grade_id'])   ? (int) $r['grade_id']   : 0;
            $j    = isset($r['subject_id']) ? (int) $r['subject_id'] : 0;
            $kind = $this->clean_kind(isset($r['kind']) ? $r['kind'] : self::KIND_CURRICULUM);
            $trk  = isset($r['track_id']) ? (int) $r['track_id'] : 0;

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
            /* TQ-FOUNDATION — وقت التأسيس يفحص بمساره لا بصفه.
               والصف والمادة يكتبان صفرا: حقنهما هنا يجعل ترشيح الطالب
               بصفه يمحو من شاشته معلما يصلح له تماما — التأسيس مستوى
               لا مقرر صف. */
            if ($kind === self::KIND_FOUNDATION) {
                if ($trk <= 0) {
                    return $bad('اختر مسار التأسيس ليوم ' . $day . '. والمسارات المعروضة هي '
                              . 'ما أسندته إليك الإدارة.');
                }
                if (!isset($tracks[$trk])) {
                    return $bad('مسار تأسيس غير مسند إليك، في يوم ' . $day . '. '
                              . 'راجع الإدارة لتسنده إليك، أو اختر مسارا من مساراتك.');
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
                                 'grade_id' => 0, 'subject_id' => 0,
                                 'kind' => self::KIND_FOUNDATION, 'track_id' => $trk);
                continue;
            }

            if (!$mine) {
                return $bad('لا صف لك بعد فلا تفتح وقت منهج في يوم ' . $day . '. '
                          . 'الصفوف تشتق من كورساتك — أو اجعل هذا الوقت وقت تأسيس.');
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
                             'grade_id' => $g, 'subject_id' => $j,
                             'kind' => self::KIND_CURRICULUM, 'track_id' => 0);
        }

        $now = date('Y-m-d H:i:s');
        try {
            $this->db->where('teacher_id', $teacher_id)->delete('tq_teacher_windows');
            foreach ($clean as $c) {
                $this->db->query(
                    'INSERT IGNORE INTO `tq_teacher_windows`
                       (`teacher_id`,`dow`,`start_min`,`end_min`,`grade_id`,`subject_id`,
                        `kind`,`track_id`,`created_at`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    array($teacher_id, $c['dow'], $c['start_min'], $c['end_min'],
                          $c['grade_id'], $c['subject_id'], $c['kind'], $c['track_id'], $now)
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
                                      (int) ($w['subject_id'] ?? 0),
                                      $this->clean_kind($w['kind'] ?? self::KIND_CURRICULUM),
                                      (int) ($w['track_id'] ?? 0));
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
                   (teacher_id, starts_at, duration_min, status, grade_id, window_id,
                    subject_id, kind, track_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array($teacher_id, $dt, $meta[0], 'open', $meta[1], $meta[2], $meta[3],
                      $meta[4], $meta[5])
            );
            /* والمفتوح يتبع قاعدته: من غير صف نافذته، أو حولها من منهج
               إلى تأسيس، أو تغيرت مدة الحصة تحته، يصحح المفتوح — والمحجوز
               لا يمس، فحصة طلبت أمس تبقى بنوعها ومسارها كما طلبت. */
            $this->db->where('teacher_id', $teacher_id)->where('starts_at', $dt)
                     ->where('status', 'open')
                     ->update('availability_slots', array(
                         'duration_min' => $meta[0],
                         'grade_id'     => $meta[1],
                         'window_id'    => $meta[2],
                         'subject_id'   => $meta[3],
                         'kind'         => $meta[4],
                         'track_id'     => $meta[5],
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
     * **وTQ-FOUNDATION — والنوع يرشح قبل الصف.** البابان لا يخلطان:
     * شاشة «حصص بالطلب» تعرض حصص المنهج، وشاشة «التأسيس» تعرض التأسيس
     * وحده. وخلطهما يجعل طالبا فتح قسم التأسيس يرى مواعيد منهج صف لا
     * يريده، ومعلما فتح وقت تأسيس يصله طلب شرح لدرس في كتاب.
     *
     * **والصف لا يرشح في التأسيس أصلا**: مواعيده بصفر صف بحكم الحفظ،
     * والترشيح بصف الطالب كان يمرها (`IN (0, صفه)`) — والشرط يحذف هنا
     * صراحة لا يعتمد على الصفر: صفر بمعنى «كل الصفوف» في المنهج شيء،
     * و«لا صف لهذا القسم أصلا» شيء آخر، واتكاء أحدهما على الآخر يجعل
     * تشديد الترشيح غدا يفرغ قسم التأسيس بلا أن يقصد أحد.
     *
     * @param int    $subject_id تصفية بمادة من `subjects` (٠ = الكل)
     * @param int    $grade_id   صف الطالب (٠ = بلا ترشيح)
     * @param string $kind       نوع الوقت — منهج أو تأسيس
     * @param int    $track_id   مسار التأسيس (٠ = كل المسارات)
     */
    public function available_teachers($limit_teachers = 12, $limit_slots = 6, $subject_id = 0,
                                       $grade_id = 0, $kind = self::KIND_CURRICULUM, $track_id = 0)
    {
        $this->install_schema();
        $this->expire_holds();   // TQ-LIVE — موعد مضت مهلة دفعه يعود قبل العرض
        $now  = date('Y-m-d H:i:s');
        $kind = $this->clean_kind($kind);

        $this->db->select('s.id AS slot_id, s.starts_at, s.duration_min, s.grade_id, s.subject_id,
                           s.kind, s.track_id,
                           u.id AS teacher_id, u.first_name, u.last_name, u.image, u.title')
                 ->from('availability_slots s')
                 ->join('users u', 'u.id = s.teacher_id', 'inner')
                 ->where('s.status', 'open')
                 ->where('s.kind', $kind)
                 ->where('s.starts_at >', $now);
        if ($kind === self::KIND_FOUNDATION) {
            if ((int) $track_id > 0) $this->db->where('s.track_id', (int) $track_id);
        } elseif ((int) $grade_id > 0) {
            $this->db->where_in('s.grade_id', array(0, (int) $grade_id));
        }
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
            if ($subject_id > 0 && $kind === self::KIND_CURRICULUM) {
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
                    'subject' => $kind === self::KIND_FOUNDATION ? '' : ($subjects['name'][$tid] ?? ''),
                    'kind'    => $kind,
                    /* TQ-FOUNDATION — وثمن التأسيس ثمن مساره أولا: بطاقة
                       تعرض التسعيرة العامة ثم تصدر فاتورة بسعر المسار
                       تعد بسعر وتقبض غيره. والحاسبة واحدة للشاشة
                       وللفاتورة (`request_session()` تناديها بعينها). */
                    'pricing' => $this->pricing_of_slot($kind, (int) ($r['track_id'] ?? 0), $tid),
                    'slots'   => [],
                ];
            }
            if (count($out[$tid]['slots']) >= (int) $limit_slots) continue;

            $sc = $this->scope_of($r);
            $out[$tid]['slots'][] = [
                'id'           => (int) $r['slot_id'],
                'starts_at'    => $r['starts_at'],
                'when_text'    => $this->when_text($r['starts_at'], (int) $r['duration_min']),
                'minutes'      => (int) $r['duration_min'],
                'grade_id'     => (int) ($r['grade_id'] ?? 0),
                'grade_name'   => $this->grade_name((int) ($r['grade_id'] ?? 0)),
                'subject_id'   => $slot_subject,
                'subject_name' => $this->subject_name($slot_subject),
                'kind'         => $sc['kind'],
                'track_id'     => $sc['track_id'],
                'track_name'   => $sc['track_name'],
                'scope_text'   => $sc['label'],
            ];
        }

        /* وسطر البطاقة في التأسيس مساراته المعروضة لا مادته: معلم يدرس
           التأسيس ويملك كورس رياضيات كان يقرأ تحت اسمه «الرياضيات» وكل
           مواعيده المعروضة تأسيس لغة. */
        if ($kind === self::KIND_FOUNDATION) {
            foreach ($out as $tid => $t) {
                $names = array();
                foreach ($t['slots'] as $sl) {
                    if ((string) $sl['track_name'] !== '') $names[$sl['track_name']] = true;
                }
                $out[$tid]['subject'] = implode(' · ', array_keys($names));
            }
            return array_values($out);
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
    public function request_session($student_id, $slot_id, $card_payable = true)
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

        /* TQ-FOUNDATION — النوع والمسار يجمدان على الصف كما يجمد السعر.
           والموعد قد يحذف بعد انتهاء الحصة، وسعر المسار قد يعدل غدا —
           وما قرأه الطالب وضغط عليه هو ما يقيد عليه. */
        $kind  = $this->clean_kind($slot['kind'] ?? self::KIND_CURRICULUM);
        $track = (int) ($slot['track_id'] ?? 0);

        /* TQ-FOUNDATION-GUARD — مسار موقوف لا يحجز فيه. كان المسؤول يعطل
           المسار فتقول اللوحة «معطل»، وتبقى مواعيده المفتوحة قبل التعطيل
           تحجز وتدفع — والطالب يحجز مسارا رفعته الإدارة. والحكم `may_teach()`
           نفسها التي تحرس فتح الوقت: المسار فعال، والمعلم ما زال مسندا إليه. */
        if ($kind === self::KIND_FOUNDATION) {
            $this->load->model('taqdar_foundation_model');
            if (!$this->taqdar_foundation_model->may_teach((int) $slot['teacher_id'], $track)) {
                return ['ok' => false, 'id' => 0,
                        'msg' => 'هذا المسار موقوف الآن أو لم يعد هذا المعلم يدرسه، فلا يحجز فيه. اختر مسارا آخر.'];
            }
        }

        $p     = $this->pricing_of_slot($kind, $track, (int) $slot['teacher_id']);
        $now   = date('Y-m-d H:i:s');

        /* ---- TQ-FND-PACK — ارصيد الطالب يدفع عن هذه الحصة؟ ------------
           الباقة تشترى مرة ويحجز بها الطالب متى شاء، فالحجز هنا **لا
           يصدر فاتورة ولا ينتظر دفعا**: يؤكد المعلم فتثبت الحصة في
           الحال. والثمن يجمد على الصف كما يجمد في الحصة المفردة — وهو
           **حصة هذه الحصة من المدفوع** لا سعر المسار: من دفع ٢٠٠ عن ست
           حصص لا يقيد لمعلمه نصيب ٦ × ١٢٠، والا بيعت الباقة بخصم ودفع
           المعلمون ثمنه من غير علمهم.

           والاختيار **اقرب الارصدة اجلا** (`credit_to_spend()`)، فلا
           تنتهي باقة بحصص لم تستعمل بينما يخصم من باقة تمتد شهرين. */
        $pack = null;
        if ($kind === self::KIND_FOUNDATION) {
            $this->load->model('taqdar_foundation_model');
            $pack = $this->taqdar_foundation_model->credit_to_spend($student_id, $track);
        }
        if ($pack) {
            $unit  = (int) $pack['unit_next'];
            $pct   = (float) $p['percent'];
            /* نصيب المعلم نسبة **الباقة** حين تكتب لها نسبة: الباقة منتج
               مسعر بخصم، وقد يتفق على نصيب غير نصيب الحصة المفردة فيه. */
            $po    = $this->taqdar_foundation_model->pack_offer((int) $pack['pack_id']);
            if (!empty($po['id'])) $pct = (float) $po['percent'];
            $p     = $this->split($unit, $pct);
        }

        /* TQ-SESSION-RACE — الموعد يحجز **قبل** أن يكتب الطلب لا بعده.
           كان الطلب يدرج ثم يعلق الموعد بشرط `status = open`: طالبان يضغطان
           في اللحظة نفسها يقرآن «مفتوح» معا، فيكتب كل منهما طلبه، ويعلق
           الموعد مرة — ويقرأ كلاهما «بانتظار رد المعلم» على موعد واحد، ويرى
           المعلم طلبين لساعة واحدة. والتحديث المشروط ذري: من أصاب صفا حجز،
           ومن لم يصب سبقه غيره — قبل أن يكتب شيء. */
        $this->db->where('id', $slot_id)->where('status', 'open')
                 ->update('availability_slots', ['status' => 'held']);
        if ($this->db->affected_rows() < 1) {
            return ['ok' => false, 'msg' => 'سبقك غيرك إلى هذا الموعد. اختر موعدا آخر.', 'id' => 0];
        }

        /* TQ-LIVE (D1) — الحجز الفوري: فتح المعلم موعدا التزام به، فالحصة
           المفردة بثمن تنشأ `awaiting_payment` بفاتورتها ومهلة حجز قصيرة،
           والدفع يثبتها بلا تاكيد. والباقة والمجانية تمران بالمسار القديم
           (تثبتان بتاكيد المعلم كما اليوم)، والمفتاح مطفأ يعيد كل شيء كما كان. */
        $cfg     = $this->config();
        /* والعميل الذي لا يدفع الحصة بالبطاقة (سياسة التطبيق: بناء المراجعة
           وغيره) لا يحجز فوريا: حجز لا يدفع في مهلته قفل بلا مخرج. */
        $instant = self::instant_allowed($cfg['instant_book'], (bool) $pack, (int) $p['price'],
                                         $card_payable && $this->card_ready());

        $this->db->insert('tutoring_sessions', [
            'slot_id'               => $slot_id,
            'student_id'            => $student_id,
            'teacher_id'            => (int) $slot['teacher_id'],
            'status'                => $instant ? 'awaiting_payment' : 'requested',
            'kind'                  => $kind,
            'track_id'              => $track,
            'pack_sub_id'           => $pack ? (int) $pack['sub_id'] : 0,
            'price_halalas'         => $p['price'],
            'teacher_percent'       => $p['percent'],
            'teacher_share_halalas' => $p['share'],
            /* TQ-FND-PACK — والمدفوع سلفا **مدفوع**: `paid_at` يختم هنا
               لان المال وصل يوم اشتريت الباقة لا يوم حجزت الحصة. وبلاه
               ترد `credit()` صفرا فلا يقيد لمعلمها شيء وقد درسها،
               ويقرأ `admin_cancel()` «حصة بلا ثمن» فلا يعكس شيئا. */
            'paid_at'               => $pack ? $now : null,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
        $id = (int) $this->db->insert_id();
        if ($id <= 0) {
            /* تعذر الإدراج: يعاد الموعد مفتوحا، فلا يبقى معلقا بلا طلب. */
            $this->db->where('id', $slot_id)->where('status', 'held')
                     ->update('availability_slots', ['status' => 'open']);
            return ['ok' => false, 'msg' => 'تعذر حفظ الطلب. حاول مرة أخرى.', 'id' => 0];
        }

        /* TQ-FND-PACK-RACE — والرصيد يفحص **بعد** الادراج كما يفحص قبله.
           الرصيد مستنتج بعد (`COUNT`)، ونقرتان متزامنتان على موعدين
           مختلفين تقرآن «بقيت واحدة» معا فتحجزان اثنتين على واحدة.
           والموعد يحرسه تحديثه المشروط، والرصيد لا يحرسه شيء — فيعاد
           العد بعد الكتابة، وما تجاوز يرد عن نفسه في اللحظة. وذلك اهون
           من قفل على جدول يقرأ في كل شاشة. */
        if ($pack) {
            $spent = (int) $this->db->where('pack_sub_id', (int) $pack['sub_id'])
                                    ->where_in('status', Taqdar_foundation_model::$SPENT)
                                    ->count_all_results('tutoring_sessions');
            if ($spent > (int) $pack['total']) {
                $this->db->where('id', $id)->delete('tutoring_sessions');
                $this->db->where('id', $slot_id)->where('status', 'held')
                         ->update('availability_slots', ['status' => 'open']);
                return ['ok' => false, 'id' => 0,
                        'msg' => 'نفد رصيد باقتك. اشتر باقة جديدة أو احجز حصة مفردة.'];
            }
        }

        if ($pack) {
            $left = max(0, (int) $pack['left'] - 1);
            return ['ok' => true, 'id' => $id, 'teacher_id' => (int) $slot['teacher_id'],
                    'pack' => true, 'left' => $left,
                    'msg' => 'أرسل طلبك إلى المعلم، وخصمت حصة من باقتك — '
                           . ($left > 0
                               ? 'يتبقى لك ' . $left . ' حصة.'
                               : 'وهي آخر حصة في الباقة.')
                           . ' ولا دفع: باقتك مدفوعة، وإن اعتذر المعلم عادت الحصة إلى رصيدك.'];
        }

        if ($instant) {
            $inv = $this->issue_invoice(array('id' => $id, 'student_id' => $student_id,
                                              'price_halalas' => (int) $p['price']));
            if ($inv <= 0) {
                $this->db->where('id', $id)->delete('tutoring_sessions');
                $this->db->where('id', $slot_id)->where('status', 'held')
                         ->update('availability_slots', ['status' => 'open']);
                return ['ok' => false, 'id' => 0, 'msg' => 'تعذر إصدار فاتورة الحصة. حاول مرة أخرى.'];
            }
            /* المهلة اقصر الاجلين: مهلة الحجز، وبداية الحصة نفسها. */
            $deadline = time() + $cfg['hold_min'] * 60;
            $starts   = strtotime((string) $slot['starts_at']);
            if ($starts > 0 && $starts < $deadline) $deadline = $starts;
            $this->db->where('id', $id)->update('tutoring_sessions', [
                'invoice_id'   => $inv,
                'pay_deadline' => date('Y-m-d H:i:s', $deadline),
            ]);
            return ['ok' => true, 'id' => $id, 'teacher_id' => (int) $slot['teacher_id'],
                    'instant' => true, 'invoice_id' => $inv,
                    'deadline' => date('Y-m-d H:i:s', $deadline),
                    'msg' => 'حجز الموعد لك ' . $cfg['hold_min'] . ' دقيقة. ادفع بالبطاقة الآن لتثبيت الحصة — '
                           . 'وإن لم يصل الدفع في المهلة عاد الموعد متاحا ولم يخصم منك شيء.'];
        }

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
                           u.first_name, u.last_name, u.image, ut.tq_meet_url AS teacher_meet_url')
                 ->from('tutoring_sessions t')
                 ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                 ->join('users u', 'u.id = t.student_id', 'left')
                 ->join('users ut', 'ut.id = t.teacher_id', 'left')
                 ->where('t.teacher_id', $teacher_id);
        if ($statuses) $this->db->where_in('t.status', $statuses);

        $rows = $this->db->order_by('a.starts_at', 'ASC')->order_by('t.id', 'ASC')
                         ->limit((int) $limit)->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
            $j    = $this->join_state($r, null, 'teacher');
            $sc   = $this->scope_of($r);
            $out[] = [
                'id'           => (int) $r['id'],
                'status'       => $r['status'],
                'kind'         => $sc['kind'],
                'track_id'     => $sc['track_id'],
                'track_name'   => $sc['track_name'],
                'scope_text'   => $sc['label'],
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
                'meet_url'     => $this->meet_url_for($r),   // TQ-LIVE — والا رابط المعلم الثابت (D2-b)
                'price'        => (int) $r['price_halalas'],
                'percent'      => (float) $r['teacher_percent'],
                'share'        => (int) $r['teacher_share_halalas'],
                'paid_at'      => $r['paid_at'],
                /* TQ-FND-PACK — والمعلم يحتاج ان يعرفها: هذه لا تنتظر
                   دفعا بعد تأكيده، فتثبت في الحال ولا تسقط بمهلة. */
                'is_pack'      => (int) ($r['pack_sub_id'] ?? 0) > 0,
                'pay_deadline' => $r['pay_deadline'],
                'credited_at'  => $r['credited_at'],
                'can_join'     => $j['can_join'],
                /* TQ-LIVE — حالة العرض والباب والتقويم من مصدر واحد. */
                'state'        => $this->display_state($r, null, 'teacher'),
                'is_instant'   => $r['status'] === 'awaiting_payment' && empty($r['confirmed_at']),
                'opens_at'     => (int) $j['opens_at'],
                'starts_ts'    => (int) $j['starts_ts'],
                'has_link'     => $this->meet_url_for($r) !== '',
                'gcal_url'     => $r['starts_at'] ? $this->google_calendar_url($r) : '',
                'can_complete' => $j['can_complete'],
                'is_over'      => $j['is_over'],
                'note'         => $j['note'],
                'cancel_reason' => (string) ($r['cancel_reason'] ?? ''),
            ] + $this->live_extra($r, 'teacher');
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
            /* TQ-LIVE (D2-b) — بلا رابط خاص يؤخذ رابط المعلم الثابت. */
            if ($meet === '' && trim((string) $meet_url) === '') {
                $meet = $this->meet_url_for(array('teacher_id' => $teacher_id));
            }
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

        /* TQ-SESSION-RACE — والموعد الواحد لا يؤكد لطالبين. طلبان على موعد
           واحد (ما سبق هذا الإصلاح، أو ما أعيد فتحه بعد إلغاء) كان المعلم
           يؤكدهما معا فيصل الطالبان إلى غرفة واحدة في ساعة واحدة. */
        if ($decision === 'confirm' && !empty($row['slot_id'])) {
            $taken = (int) $this->db->where('slot_id', (int) $row['slot_id'])
                                    ->where('id !=', $session_id)
                                    ->where_in('status', array('awaiting_payment', 'confirmed', 'live'))
                                    ->count_all_results('tutoring_sessions');
            if ($taken > 0) {
                return ['ok' => false,
                        'msg' => 'أكدت هذا الموعد لطالب آخر من قبل، فلا يؤكد مرتين. اعتذر عن هذا الطلب ليختار صاحبه موعدا غيره.'];
            }
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
        /* TQ-FND-PACK — والمدفوع سلفا يثبت هنا كذلك، وللعلة نفسها: لا
           فاتورة تصدر لمن دفع، ولا مهلة تجري على مال وصل قبل شهر.
           وحصة باقة تقف في `awaiting_payment` تعني طالبا يطالب بثمن
           دفعه، ومهلة تنقضي فيسقط حجزه **ولا يعود رصيده** لانه استهلك.
           فالفرعان واحد: ما لا يدفع الان يؤكد الان. */
        $prepaid = (int) ($row['pack_sub_id'] ?? 0) > 0;
        if ($price <= 0 || $prepaid) {
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
            return ['ok' => true, 'state' => 'confirmed', 'prepaid' => $prepaid,
                    'msg' => $prepaid
                        ? 'أكدت الحصة وثبت موعدها — هي مدفوعة ضمن باقة الطالب، ويقيد نصيبك منها حين تعلن انتهاءها.'
                        : 'أكد الطلب، وصار الموعد محجوزا.'];
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
        /* TQ-LIVE — حجز فوري: لم يؤكده معلم قبل الدفع (`confirmed_at` فارغ)،
           فالدفع تاكيده، ويخبر الطرفان هنا — لا احد غيره يخبرهما. */
        $instant = empty($row['confirmed_at']);
        $upd = array('status' => 'confirmed', 'paid_at' => $now, 'updated_at' => $now);
        if ($instant) {
            $upd['confirmed_at'] = $now;
            /* D2-b — الحصة بلا رابط تاخذ رابط معلمها الثابت عند التثبيت،
               ويبقى للمعلم ان يخالفه لهذه الحصة بعدها. */
            if (trim((string) ($row['meet_url'] ?? '')) === '') {
                $fixed = $this->meet_url_for(array('teacher_id' => (int) $row['teacher_id']));
                if ($fixed !== '') $upd['meet_url'] = $fixed;
            }
        }
        $this->db->where('id', (int) $row['id'])->where('status', 'awaiting_payment')
                 ->update('tutoring_sessions', $upd);
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

        if ($instant) {
            $full = $this->session_for((int) $row['id'], (int) $row['student_id'], 'student');
            $when = ($full && !empty($full['starts_at']))
                    ? $this->when_text($full['starts_at'], (int) $full['duration_min']) : '';
            $this->tell((int) $row['student_id'], 'ثبتت حصتك',
                'وصل دفعك وثبتت الحصة ' . $when . '. زر الدخول يفتح في شاشة حصصك قبل الموعد بـ'
                . $this->join_lead_min('student') . ' دقائق.');
            $this->tell((int) $row['teacher_id'], 'حجزت حصة في جدولك',
                'حجز طالب موعدك ' . $when . ' ودفع ثمنه. '
                . ($full && $this->meet_url_for($full) === ''
                    ? 'ضع رابط لقائك الثابت في شاشة حصصك — لا رابط للحصة بعد.'
                    : 'يدخل الطالب من رابط لقائك.'));
        }

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
    public function join_state($row, $now = null, $actor = 'student')
    {
        $now    = $now ? (int) $now : time();
        $c      = $this->config();
        $lead   = $this->join_lead_min($actor);
        $status = (string) ($row['status'] ?? '');
        $starts = !empty($row['starts_at']) ? strtotime((string) $row['starts_at']) : 0;
        $mins   = (int) ($row['duration_min'] ?? 0) ?: $c['minutes'];
        $ends   = $starts > 0 ? $starts + $mins * 60 : 0;
        $dead   = $ends > 0 ? $ends + $c['grace_hours'] * 3600 : 0;
        $opens  = $starts > 0 ? $starts - $lead * 60 : 0;

        $done    = in_array($status, array('completed', 'declined', 'expired', 'refunded'), true);
        $is_over = $done || ($dead > 0 && $dead < $now);
        $ready   = in_array($status, array('confirmed', 'live'), true);
        /* D2-b — رابط الحصة، والا رابط المعلم الثابت ان جاء مع الصف. */
        $link    = $this->clean_meet_url($row['meet_url'] ?? '');
        if ($link === '') $link = $this->clean_meet_url($row['teacher_meet_url'] ?? '');

        $note = '';
        if ($status === 'awaiting_payment')      $note = 'ادفع لتثبيت الحصة — الرابط يفتح بعد الدفع.';
        elseif ($status === 'requested')         $note = 'بانتظار رد المعلم. لم يخصم منك شيء.';
        elseif ($status === 'completed')         $note = 'انتهت الحصة، وأغلق رابطها.';
        elseif ($status === 'refunded')          $note = 'استرد مبلغ هذه الحصة.';
        elseif ($ready && $link === '')          $note = 'لا رابط بعد — راسل معلمك.';
        elseif ($ready && $is_over)              $note = 'مضى وقت هذه الحصة، وأغلق رابطها.';
        elseif ($ready && $opens > $now)         $note = 'الرابط يفتح قبل الموعد بـ' . $lead . ' دقيقة.';

        return array(
            'can_join'     => $ready && !$is_over && $link !== '' && ($opens <= 0 || $opens <= $now),
            'can_complete' => $ready && $starts > 0 && $starts <= $now,
            'is_over'      => $is_over,
            'starts_ts'    => $starts,
            'ends_ts'      => $ends,
            'opens_at'     => $opens,
            /* TQ-I18N — الملاحظة تحسب في كل طلب ولا تخزن، فتترجم هنا بلغة
               من يقرؤها. وترجمتها عند الكتابة لا معنى له: لا كتابة أصلا. */
            'note'         => t($note),
        );
    }

    /* =====================================================================
       TQ-LIVE — المرحلة الاولى: مهلتان، حالة عرض، تقويم، باب دخول
       ===================================================================== */

    /**
     * مهلة فتح الرابط قبل الموعد لدور بعينه، بالدقائق.
     * والمعلم يفتح قبل طالبه: غرفة يدخلها الطالب اولا يقرؤها «تخلف معلمي».
     *
     * @param string $actor student|teacher — وما سواهما يقرأ مهلة الطالب
     */
    public function join_lead_min($actor)
    {
        $c = $this->config();
        return (string) $actor === 'teacher' ? (int) $c['lead_teacher'] : (int) $c['lead_student'];
    }

    /**
     * حالة العرض — المصدر الواحد لشارة كل بطاقة حصة.
     *
     * محسوبة لا مخزنة: «جاهزة للدخول» و«لم يحضر» حالات وقت لا حالات صف.
     * وتقرأ اعمدة المرحلتين الثانية والثالثة (`recording_url` ·
     * `recording_pending` · `attendance` · `rescheduled_from`) **ان وجدت**:
     * صف بلاها يقرأ كما يقرأ اليوم. و«لم يحضر» يشترط `attendance` صريحا
     * لا `attended_at` فارغا — كل حصة انعقدت قبل هذا العمود فارغة فيه،
     * وقراءتها غيابا تكتب «لم يحضر» على كل تاريخ الطالب.
     *
     * و`requested` (المسار القديم «طلب ← تاكيد») تقرأ `scheduled` بنصها
     * هي: المفاتيح الاثنا عشر ثابتة، والطلب المعلق «مجدول» بلا زر.
     *
     * @return array key · label · tone · button · starts_ts · deadline_ts
     */
    public function display_state($row, $now = null, $actor = 'student')
    {
        $now    = $now ? (int) $now : time();
        $j      = $this->join_state($row, $now, $actor);
        $status = (string) ($row['status'] ?? '');
        $starts = (int) $j['starts_ts'];
        $dl     = !empty($row['pay_deadline']) ? (int) strtotime((string) $row['pay_deadline']) : 0;

        $key = 'scheduled'; $label = ''; $tone = 'idle'; $button = '';

        $refund = (string) ($row['refund_state'] ?? '');
        if (in_array($status, array('declined', 'refunded'), true)) {
            $key = 'cancelled'; $tone = 'late';
            $label = $status === 'refunded' ? 'ألغيت واسترد مبلغها' : 'ألغيت';
            /* D5 — اعتذر المعلم او غاب بعد الدفع: الطالب يختار البديل او
               الاسترداد، والزر له وحده. */
            if ($status === 'declined' && $refund === 'choice') {
                $tone = 'due'; $label = 'اعتذر المعلم — اختر موعدا بديلا أو الاسترداد';
                $button = (string) $actor === 'student' ? 'teacher_fault' : '';
            } elseif ($status === 'declined' && $refund === 'review') {
                $tone = 'due'; $label = 'طلبك قيد مراجعة الإدارة';
            } elseif ($status === 'refunded' && $refund === 'pending') {
                $label = 'ألغيت — يرد مبلغها خلال 5 أيام عمل خارج المنصة';
            }
        } elseif ($status === 'expired') {
            $key = 'expired'; $tone = 'late'; $label = 'مضت مهلتها';
        } elseif ($status === 'awaiting_payment') {
            $attempt = $this->last_attempt_status($row);
            if ($dl > 0 && $dl < $now) {
                $key = 'expired'; $tone = 'late'; $label = 'مضت مهلة الدفع';
            } elseif (in_array($attempt, array('failed', 'mismatch'), true)) {
                $key = 'payment_failed'; $tone = 'late'; $label = 'تعذر الدفع'; $button = 'retry_pay';
            } else {
                $key = 'pending_payment'; $tone = 'due'; $label = 'بانتظار الدفع'; $button = 'pay';
            }
        } elseif ($status === 'requested') {
            $key = 'scheduled'; $label = 'بانتظار رد المعلم';
        } elseif ($status === 'completed') {
            $rec = trim((string) ($row['recording_url'] ?? ''));
            if (isset($row['attendance']) && in_array((string) $row['attendance'], array('absent', 'missed'), true)) {
                $key = 'missed'; $tone = 'late'; $label = 'لم يحضر';
            } elseif ($rec !== '') {
                $key = 'recording_available'; $tone = 'mastered'; $label = 'التسجيل متاح'; $button = 'recording';
            } elseif ((int) ($row['recording_pending'] ?? 0) === 1) {
                $key = 'recording_processing'; $label = 'جاري تجهيز التسجيل';
            } else {
                $key = 'completed'; $label = 'انتهت';
            }
        } elseif (in_array($status, array('confirmed', 'live'), true)) {
            if (!empty($row['rescheduled_from']) && $starts > $now
                && ($j['opens_at'] <= 0 || $j['opens_at'] > $now)) {
                $key = 'rescheduled'; $tone = 'due'; $label = 'تغير موعدها';
            } elseif ($j['is_over']) {
                $key = 'completed'; $label = 'انتهت';
            } elseif ($status === 'live' || ($starts > 0 && $starts <= $now)) {
                $key = 'live'; $tone = 'progress'; $label = 'مباشرة الآن';
                $button = $j['can_join'] ? 'join' : '';
                /* D10 — غياب المعلم: بعد عشر دقائق «تاخر»، وبعد عشرين زر
                   «المعلم لم يحضر» للطالب يفتح خياري D5. */
                $absent = self::absence_stage($row, $now, $this->config()['grace_hours']);
                if ($absent >= 1 && (string) $actor === 'student') {
                    $tone = 'late'; $label = 'المعلم تأخر';
                    if ($absent >= 2) $button = 'teacher_no_show';
                }
            } elseif ($j['opens_at'] > 0 && $j['opens_at'] <= $now) {
                $key = 'ready'; $tone = 'mastered'; $label = 'جاهزة للدخول';
                $button = $j['can_join'] ? 'join' : '';
            } else {
                $key = 'scheduled'; $tone = 'mastered'; $label = 'مؤكدة';
            }
        } else {
            $label = $status;
        }

        return array(
            'key'         => $key,
            'label'       => t($label),
            'tone'        => $tone,
            'button'      => $button,
            'starts_ts'   => $starts,
            'deadline_ts' => $key === 'pending_payment' || $key === 'payment_failed' ? $dl : 0,
        );
    }

    /**
     * حال اخر محاولة دفع لفاتورة الحصة — من الصف ان جاء معه، والا من
     * `payment_attempts`. والجدول قد لا يكون نشأ بعد، فالخطأ يقرأ «لا محاولة».
     */
    private function last_attempt_status($row)
    {
        if (array_key_exists('last_attempt', $row)) return (string) $row['last_attempt'];
        $inv = (int) ($row['invoice_id'] ?? 0);
        if ($inv <= 0 || !isset($this->db) || !method_exists($this->db, 'table_exists')) return '';
        try {
            $r = $this->db->select('status')->where('invoice_id', $inv)
                          ->order_by('id', 'DESC')->limit(1)
                          ->get('payment_attempts')->row_array();
            return $r ? (string) $r['status'] : '';
        } catch (Throwable $e) {
            $this->db->reset_query();
            return '';
        }
    }

    /**
     * حدث تقويم واحد (RFC 5545) — الوقت UTC، والرابط لا يكتب قبل الدفع:
     * ملف التقويم يحفظ ويرسل، ورابط حصة لم تدفع فيه باب لمن لم يشتر.
     */
    public function ics_for($row)
    {
        $starts = !empty($row['starts_at']) ? (int) strtotime((string) $row['starts_at']) : 0;
        $mins   = (int) ($row['duration_min'] ?? 0) ?: (int) $this->config()['minutes'];
        $ends   = $starts + $mins * 60;
        $title  = t('حصة تقدر') . ' — ' . $this->scope_of($row)['label'];

        $paid = !empty($row['paid_at']) || ((int) ($row['price_halalas'] ?? 0) <= 0
                && in_array((string) ($row['status'] ?? ''), array('confirmed', 'live', 'completed'), true));
        $link = $paid ? $this->meet_url_for($row) : '';

        $esc = function ($s) {
            return str_replace(array('\\', ';', ',', "\r\n", "\n"), array('\\\\', '\;', '\,', '\n', '\n'), (string) $s);
        };

        $lines = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Taqdar//Sessions//AR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:tq-session-' . (int) ($row['id'] ?? 0) . '@taqdaredu.com',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . gmdate('Ymd\THis\Z', $starts),
            'DTEND:' . gmdate('Ymd\THis\Z', $ends),
            'SUMMARY:' . $esc($title),
        );
        if ($link !== '') {
            $lines[] = 'URL:' . $link;
            $lines[] = 'LOCATION:' . $esc($link);
            $lines[] = 'DESCRIPTION:' . $esc(t('رابط الحصة') . ': ' . $link);
        } else {
            $lines[] = 'DESCRIPTION:' . $esc(t('رابط الحصة يظهر في شاشة حصصك بعد الدفع.'));
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }

    /** رابط «اضف الى تقويم جوجل» للحصة نفسها — بلا رابط اللقاء. */
    public function google_calendar_url($row)
    {
        $starts = !empty($row['starts_at']) ? (int) strtotime((string) $row['starts_at']) : 0;
        $mins   = (int) ($row['duration_min'] ?? 0) ?: (int) $this->config()['minutes'];
        return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . rawurlencode(t('حصة تقدر') . ' — ' . $this->scope_of($row)['label'])
            . '&dates=' . gmdate('Ymd\THis\Z', $starts) . '/' . gmdate('Ymd\THis\Z', $starts + $mins * 60)
            . '&details=' . rawurlencode(t('رابط الحصة في شاشة حصصك على تقدر.'));
    }

    /**
     * D2-b — رابط اللقاء: رابط الحصة، والا رابط المعلم الثابت.
     * ويطهر عند القراءة كما عند الكتابة: عمود كتب قبل القائمة البيضاء لا
     * يصير بابا الى اي موقع.
     */
    public function meet_url_for($row)
    {
        $own = $this->clean_meet_url($row['meet_url'] ?? '');
        if ($own !== '') return $own;
        if (array_key_exists('teacher_meet_url', $row)) {
            return $this->clean_meet_url($row['teacher_meet_url']);
        }
        $tid = (int) ($row['teacher_id'] ?? 0);
        if ($tid <= 0) return '';
        try {
            $u = $this->db->select('tq_meet_url')->where('id', $tid)->get('users')->row_array();
            return $this->clean_meet_url($u['tq_meet_url'] ?? '');
        } catch (Throwable $e) {
            $this->db->reset_query();
            return '';
        }
    }

    /** يحفظ رابط المعلم الثابت. الفارغ يمحوه، والمرفوض يرد برسالته. */
    public function save_teacher_meet_url($teacher_id, $url)
    {
        $this->install_schema();
        $url   = trim((string) $url);
        $clean = $this->clean_meet_url($url);
        if ($url !== '' && $clean === '') {
            return array('ok' => false, 'msg' => 'الرابط غير مقبول. ضع رابط ' . $this->meet_hosts_text() . ' يبدأ بـhttps.');
        }
        $this->db->where('id', (int) $teacher_id)
                 ->update('users', array('tq_meet_url' => $clean !== '' ? $clean : null));
        return array('ok' => true, 'url' => $clean,
                     'msg' => $clean !== '' ? 'حفظ رابط لقائك الثابت، ويستعمل لكل حصة بلا رابط خاص.'
                                            : 'محي رابط لقائك الثابت.');
    }

    /** رابط المعلم الثابت كما حفظ. */
    public function teacher_meet_url($teacher_id)
    {
        $this->install_schema();
        try {
            $u = $this->db->select('tq_meet_url')->where('id', (int) $teacher_id)->get('users')->row_array();
            return (string) ($u['tq_meet_url'] ?? '');
        } catch (Throwable $e) { $this->db->reset_query(); return ''; }
    }

    /**
     * صف الحصة بموعدها ورابط معلمها — لباب الدخول والتقويم.
     * والملكية شرط في الاستعلام: الطالب صاحبها، او المعلم صاحبها.
     */
    public function session_for($session_id, $user_id, $actor)
    {
        $this->install_schema();
        $col = (string) $actor === 'teacher' ? 't.teacher_id' : 't.student_id';
        try {
            return $this->db->select('t.*, a.starts_at, a.duration_min, a.grade_id, a.subject_id,
                                      ut.tq_meet_url AS teacher_meet_url')
                            ->from('tutoring_sessions t')
                            ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                            ->join('users ut', 'ut.id = t.teacher_id', 'left')
                            ->where('t.id', (int) $session_id)
                            ->where($col, (int) $user_id)
                            ->get()->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
    }

    /**
     * الزر يمر بالخادم لا برابط عار (1.5): يفحص الملكية والوقت، ويختم
     * الحضور، ثم يرد الرابط الخارجي ليحول اليه.
     *
     * والطالب يختم `attended_at` في اول نقرة وحدها، والمعلم يختم
     * `teacher_joined_at` **وينقل `confirmed` الى `live`** متى حل وقت
     * نافذته — فـ«مباشرة الآن» تعني ان المعلم دخل لا ان الكرون مر.
     *
     * @return array ok · url · msg
     */
    public function record_join($session_id, $user_id, $actor)
    {
        $actor = (string) $actor === 'teacher' ? 'teacher' : 'student';
        $row   = $this->session_for($session_id, $user_id, $actor);
        if (!$row) return array('ok' => false, 'url' => '', 'msg' => 'هذه الحصة ليست لك أو لم تعد موجودة.');

        $now = time();
        $j   = $this->join_state($row, $now, $actor);
        $url = $this->meet_url_for($row);
        if (!$j['can_join'] || $url === '') {
            $msg = trim((string) $j['note']) !== '' ? $j['note'] : 'لا يفتح رابط هذه الحصة الآن.';
            return array('ok' => false, 'url' => '', 'msg' => $msg);
        }

        $stamp = date('Y-m-d H:i:s', $now);
        if ($actor === 'student') {
            $this->db->where('id', (int) $row['id'])->where('attended_at IS NULL', null, false)
                     ->update('tutoring_sessions', array('attended_at' => $stamp));
        } else {
            $this->db->where('id', (int) $row['id'])->where('teacher_joined_at IS NULL', null, false)
                     ->update('tutoring_sessions', array('teacher_joined_at' => $stamp));
            if ($row['status'] === 'confirmed') {
                $this->db->where('id', (int) $row['id'])->where('status', 'confirmed')
                         ->update('tutoring_sessions', array(
                             'status' => 'live', 'started_at' => $stamp, 'updated_at' => $stamp));
            }
        }
        return array('ok' => true, 'url' => $url, 'msg' => '');
    }

    /**
     * D1 — «اغلق هذا الموعد»: فتح الموعد التزام به، والاغلاق مخرجه قبل
     * ان يحجز. ويغلق المفتوح غير المحجوز لصاحبه وحده — والحال `closed`
     * لا حذف: `layout()` تعيد ادراج المحذوف من القاعدة في دورتها التالية.
     */
    public function close_slot($teacher_id, $slot_id)
    {
        $this->install_schema();
        $teacher_id = (int) $teacher_id;
        $slot_id    = (int) $slot_id;
        if ($teacher_id <= 0 || $slot_id <= 0) return false;

        $busy = (int) $this->db->where('slot_id', $slot_id)->where_in('status', self::$LIVE_STATES)
                               ->count_all_results('tutoring_sessions');
        if ($busy > 0) return false;

        $this->db->where('id', $slot_id)->where('teacher_id', $teacher_id)->where('status', 'open')
                 ->update('availability_slots', array('status' => 'closed'));
        return $this->db->affected_rows() > 0;
    }

    /**
     * D2-b — احان تنبيه «حصة بلا رابط»؟ حصة مثبتة تبدأ خلال ساعة، بلا رابط
     * خاص ولا رابط معلم ثابت، ولم ينبه عنها من قبل. والصف يحمل
     * `teacher_meet_url` (ولو فارغا) فلا يسأل القاعدة.
     */
    public function nolink_alert_due($row, $now = null)
    {
        $now = $now ? (int) $now : time();
        if (!in_array((string) ($row['status'] ?? ''), array('confirmed', 'live'), true)) return false;
        if (!empty($row['nolink_alerted_at'])) return false;
        $starts = !empty($row['starts_at']) ? (int) strtotime((string) $row['starts_at']) : 0;
        if ($starts <= $now || $starts > $now + 3600) return false;
        if (!array_key_exists('teacher_meet_url', $row)) $row['teacher_meet_url'] = null;
        return $this->meet_url_for($row) === '';
    }

    /** ينبه المعلم والمسؤولين مرة واحدة لكل حصة بلا رابط قبل ساعة. */
    public function alert_missing_links($now = null)
    {
        $now = $now ? (int) $now : time();
        $n   = 0;
        try {
            $rows = $this->db->select('t.*, a.starts_at, a.duration_min, a.grade_id, a.subject_id,
                                       ut.tq_meet_url AS teacher_meet_url')
                             ->from('tutoring_sessions t')
                             ->join('availability_slots a', 'a.id = t.slot_id', 'inner')
                             ->join('users ut', 'ut.id = t.teacher_id', 'left')
                             ->where_in('t.status', array('confirmed', 'live'))
                             ->where('t.nolink_alerted_at IS NULL', null, false)
                             ->where('a.starts_at >', date('Y-m-d H:i:s', $now))
                             ->where('a.starts_at <=', date('Y-m-d H:i:s', $now + 3600))
                             ->limit(200)->get()->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return 0; }

        $admins = null;
        foreach ($rows as $r) {
            if (!$this->nolink_alert_due($r, $now)) continue;
            $this->db->where('id', (int) $r['id'])->where('nolink_alerted_at IS NULL', null, false)
                     ->update('tutoring_sessions', array('nolink_alerted_at' => date('Y-m-d H:i:s', $now)));
            if ($this->db->affected_rows() < 1) continue;
            $n++;
            $when = $this->when_text($r['starts_at'], (int) $r['duration_min']);
            $this->tell((int) $r['teacher_id'], 'حصتك بعد أقل من ساعة بلا رابط',
                'حصة ' . $when . ' لا رابط لقاء لها. ضع رابطك الثابت في شاشة «الحصص» الآن — الطالب لا يجد بابا يدخل منه.');
            if ($admins === null) {
                try {
                    $admins = array_column($this->db->select('id')->where('role_id', 1)
                                    ->get('users')->result_array(), 'id');
                } catch (Throwable $e) { $this->db->reset_query(); $admins = array(); }
            }
            foreach ($admins as $aid) {
                $this->tell((int) $aid, 'حصة بلا رابط لقاء',
                    'الحصة #' . (int) $r['id'] . ' ' . $when . ' تبدأ خلال ساعة ولا رابط لها ولا لمعلمها.');
            }
        }
        return $n;
    }

    /** ايحجز هذا الطلب فوريا؟ مفتاح مفعل، حصة مفردة بثمن، وعميل يدفع بالبطاقة. */
    public static function instant_allowed($switch_on, $is_pack, $price_halalas, $card_payable)
    {
        return (bool) $switch_on && !$is_pack && (int) $price_halalas > 0 && (bool) $card_payable;
    }

    /**
     * ايخبر المعلم ان حجزا لم يدفع؟ فقط ان كان اكده (`confirmed_at`): الحجز
     * الفوري لم يبلغه اصلا، وخبر عن شيء لم يعرفه ضجيج.
     */
    public static function hold_notifies_teacher($row)
    {
        return !empty($row['confirmed_at']);
    }

    /** مواعيد المعلم المفتوحة القادمة — لزر «اغلق هذا الموعد». */
    public function open_slots_for($teacher_id, $limit = 40)
    {
        $this->install_schema();
        try {
            return $this->db->select('id, starts_at, duration_min, grade_id, subject_id, kind, track_id')
                            ->where('teacher_id', (int) $teacher_id)->where('status', 'open')
                            ->where('starts_at >', date('Y-m-d H:i:s'))
                            ->order_by('starts_at', 'ASC')->limit((int) $limit)
                            ->get('availability_slots')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    /**
     * يفك الحجز المؤقت الذي مضت مهلة دفعه — كسولا عند كل قراءة لشبكة
     * المواعيد، فلا ينتظر موعد ساعة الكرون ليعود لغيره. وهو خطوة الكرون
     * الثانية نفسها، فقاعدة واحدة لا اثنتان.
     *
     * @return int عدد ما فك
     */
    public function expire_holds($now = null)
    {
        $now = $now ? (int) $now : time();
        $n   = 0;
        try {
            $rows = $this->db->select('t.id, t.slot_id, t.invoice_id, t.teacher_id, t.student_id, t.confirmed_at')
                             ->from('tutoring_sessions t')
                             ->where('t.status', 'awaiting_payment')
                             ->where('t.pay_deadline IS NOT NULL', null, false)
                             ->where('t.pay_deadline <', date('Y-m-d H:i:s', $now))
                             ->limit(200)->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return 0;
        }
        foreach ($rows as $r) {
            $this->db->where('id', (int) $r['id'])->where('status', 'awaiting_payment')
                     ->update('tutoring_sessions', array(
                         'status' => 'expired', 'meet_url' => null,
                         'updated_at' => date('Y-m-d H:i:s'),
                         'cancel_reason' => 'مضت مهلة الدفع'));
            if ($this->db->affected_rows() < 1) continue;
            $n++;
            $this->void_invoice($r);
            $this->release_slot($r);
            $this->tell((int) $r['student_id'], 'مضت مهلة دفع حصتك',
                'لم يصل دفع ثمن الحصة قبل انتهاء المهلة، فعاد الموعد متاحا لغيرك '
                . 'وشطبت فاتورته. لم يخصم منك شيء، ولك أن تطلب موعدا آخر.');
            if (self::hold_notifies_teacher($r)) $this->tell((int) $r['teacher_id'], 'لم يدفع الطالب ثمن الحصة',
                'مضت مهلة الدفع على حصة في جدولك، فعاد الموعد متاحا.');
        }
        return $n;
    }

    /**
     * ايدفع بالبطاقة الان؟ الحجز الفوري بطاقة وحدها (D1-b)، فبلا بوابة
     * يرجع الى المسار القديم بدل حجز لا يدفع في مهلته.
     */
    private function card_ready()
    {
        try {
            $this->load->model('taqdar_tap_model');
            return (bool) $this->taqdar_tap_model->ready();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** أهي فاتورة حجز فوري؟ شاشة دفعها تعرض البطاقة وحدها (D1-b). */
    public function is_instant_invoice($invoice_id)
    {
        $row = $this->by_invoice($invoice_id);
        return $row && $row['status'] === 'awaiting_payment' && empty($row['confirmed_at']);
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
    public function complete($session_id, $teacher_id, $actor = 'teacher', $extra = array())
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

        $j = $this->join_state($row, null, 'teacher');
        if ($actor === 'teacher' && !$j['can_complete']) {
            return array('ok' => false, 'msg' => 'لم يحن موعد هذه الحصة بعد.');
        }

        /* TQ-LIVE (D3/D4) — الحضور معلم مسبقا من النقرة ويصححه المعلم،
           والتسجيل رابط اختياري او «ساضيفه لاحقا». والغياب لا يغير المال. */
        $extra = (array) $extra;
        $rec   = '';
        if (trim((string) ($extra['recording_url'] ?? '')) !== '') {
            $rec = $this->clean_recording_url($extra['recording_url']);
            if ($rec === '') {
                return array('ok' => false, 'msg' => 'رابط التسجيل يكون من يوتيوب أو فيميو أو جوجل درايف ويبدأ بـhttps.');
            }
        }

        $now = date('Y-m-d H:i:s');
        $upd = array('status' => 'completed', 'completed_at' => $now, 'updated_at' => $now);
        $att = self::clean_attendance($extra['attendance'] ?? '');
        if ($att !== null) $upd['attendance'] = $att;
        if ($rec !== '') $upd['recording_url'] = $rec;
        elseif (!empty($extra['recording_later'])) $upd['recording_pending'] = 1;
        $this->db->where('id', $session_id)
                 ->where_in('status', array('confirmed', 'live'))
                 ->update('tutoring_sessions', $upd);
        if ($this->db->affected_rows() < 1) {
            return array('ok' => false, 'msg' => 'تعذر إنهاء الحصة. حدث الصفحة وأعد المحاولة.');
        }

        $credit = $this->credit($row);

        $this->audit('session.complete', $session_id,
            array('by' => $actor, 'credited' => (int) ($credit['share'] ?? 0),
                  'attendance' => $att, 'recording' => $rec !== '' ? 'url' : (!empty($upd['recording_pending']) ? 'later' : '')));
        if ($rec !== '') {
            $this->tell_parents((int) $row['student_id'], 'تسجيل حصة ابنك متاح', 'أضاف المعلم تسجيل الحصة.');
        }

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

            /* TQ-SESSION-LABEL — وسطر الدفتر يقول **ماذا درس**.
               كان «حصة خاصة» على كل حصة، وهو محتمل يوم كانت الحصص نوعا
               واحدا؛ وصار كاذبا يوم صار للتأسيس مساره: معلم يدرس المنهج
               والتأسيس معا يقرأ كشف حسابه فلا يعرف أي سطر من أيهما.
               والاسم من `scope_of()` وحدها — و`$row` يحمل `kind` من
               `t.*` فالفرع يقرؤه بلا استعلام ثان. */
            $sc   = $this->scope_of($row);
            $what = $sc['is_foundation'] ? $sc['track_name'] : t('حصة خاصة');

            $r = $this->taqdar_wallet_model->credit_session(
                (int) $row['teacher_id'], (int) $row['id'], $price, $share,
                $what . ' — ' . $name . ' — ' . $when
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

        $row = $this->db->select('t.*, a.starts_at')
                        ->from('tutoring_sessions t')
                        ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                        ->where('t.id', (int) $session_id)
                        ->where('t.student_id', (int) $student_id)
                        ->get()->row_array();
        if (!$row) return array('ok' => false, 'msg' => 'هذا الحجز ليس لك أو لم يعد موجودا.');

        /* TQ-FND-PACK — وحصة الباقة تلغى بعد تثبيتها كذلك، **ما لم يفتح
           رابطها بعد**.

           والفرق عن الحصة المفردة ليس تساهلا: تلك دفعت بالبطاقة فالغاؤها
           يحتاج ردا يقع خارج المنصة (ولذلك ترد الى الادارة)، وهذه ثمنها
           في رصيد **عند المنصة** — فالغاؤها لا ينقل مالا، وانما يعيد
           الحصة الى رصيد صاحبها وموعدها الى معلمه. ومنعها يترك طالبا
           يعرف انه لن يحضر فيحرق حصة من ست ويشغل ساعة معلمه.

           والحد رابط اللقاء لا الموعد: من الغى بعد ان فتح الباب ودخل
           معلمه الغرفة اخذ وقته فعلا. و`join_lead_min` هو الحد القائم
           لذلك، فلا مفتاح جديد يخترع لسؤال له مفتاحه. */
        $prepaid = (int) ($row['pack_sub_id'] ?? 0) > 0;
        $opens   = !empty($row['starts_at'])
                   ? strtotime($row['starts_at']) - $this->config()['lead_min'] * 60 : 0;
        $may_pack = $prepaid && $row['status'] === 'confirmed' && $opens > time();

        /* TQ-LIVE (D5-b) — حصة مفردة مدفوعة: قبل `refund_hours` من الموعد
           تسترد كاملة (او يؤجلها)، واقل منها لا استرداد — وقت المعلم حجز
           له — ويبقى التاجيل ان بقيت مرته. والمال يرد خارج المنصة. */
        if (!$prepaid && $row['status'] === 'confirmed' && !empty($row['paid_at']) && (int) $row['price_halalas'] > 0) {
            $starts = !empty($row['starts_at']) ? (int) strtotime((string) $row['starts_at']) : 0;
            $c      = $this->config();
            $dec    = self::refund_decision($starts, time(), $c['refund_hours'], true, false);
            if ($dec !== 'refund') {
                $can_move = self::reschedule_block('student', 'confirmed', $starts, time(),
                                (int) ($row['reschedule_count'] ?? 0), $c['reschedule_hours']) === '';
                return array('ok' => false, 'code' => 'no_refund',
                    'msg' => 'بقي على الموعد أقل من ' . $c['refund_hours'] . ' ساعة، فلا تلغى ولا يسترد مبلغها: وقت المعلم محجوز لك.'
                           . ($can_move ? ' يمكنك تأجيلها مرة واحدة إلى موعد آخر مع المعلم نفسه.' : ''));
            }
            $this->db->where('id', (int) $row['id'])->where('student_id', (int) $student_id)
                     ->where('status', 'confirmed')
                     ->update('tutoring_sessions', array(
                         'status'        => 'refunded',
                         'meet_url'      => null,
                         'cancel_reason' => trim((string) $reason) !== '' ? mb_substr(trim($reason), 0, 255) : 'ألغاه الطالب',
                         'cancelled_by'  => 'student',
                         'refund_state'  => 'pending',
                         'updated_at'    => date('Y-m-d H:i:s'),
                     ));
            if ($this->db->affected_rows() < 1) return array('ok' => false, 'msg' => 'تعذر إلغاء الحجز.');
            /* الحصة صارت مستردة؛ فشل ما بعدها لا يبتلع: يسجل ويعلم المسؤول
               فلا يضيع استرداد صامتا. */
            try {
                $this->release_slot($row);
                $this->mark_refunded($row, 'student_cancel');
            } catch (Throwable $e) {
                $this->db->reset_query();
                log_message('error', 'TQ-SESSION-REFUND: تعذر وسم استرداد حصة #' . (int) $row['id'] . ' — ' . $e->getMessage());
                foreach ($this->admin_ids() as $aid) {
                    $this->tell($aid, 'استرداد حصة لم يكتمل وسمه',
                        'ألغى الطالب الحصة #' . (int) $row['id'] . ' ويستحق الاسترداد، وتعذر وسم الفاتورة أو تحرير الموعد. راجعها ورد المبلغ من لوحة تاب.');
                }
            }
            $this->tell_parents((int) $student_id, 'ألغيت حصة ابنك', 'ألغى ابنك حصته، ويرد مبلغها خلال ٥ أيام عمل خارج المنصة.');
            return array('ok' => true, 'teacher_id' => (int) $row['teacher_id'], 'refunded' => true,
                         'msg' => 'ألغيت حصتك. يرد خلال ٥ أيام عمل خارج المنصة.');
        }

        if (!$may_pack && !in_array($row['status'], array('requested', 'awaiting_payment'), true)) {
            if ($prepaid && in_array($row['status'], array('confirmed', 'live'), true)) {
                return array('ok' => false,
                    'msg' => 'قرب موعد هذه الحصة ففتح رابطها، فلا تلغى الآن. راسل الإدارة إن تعذر حضورك.');
            }
            return array('ok' => false, 'msg' => in_array($row['status'], array('confirmed', 'live'), true)
                ? 'هذه الحصة مدفوعة ومثبتة. راسل الإدارة لإلغائها واسترداد مبلغها.'
                : 'هذا الحجز مغلق أصلا.');
        }

        $now   = date('Y-m-d H:i:s');
        $from  = $may_pack ? array('confirmed') : array('requested', 'awaiting_payment');
        $this->db->where('id', (int) $row['id'])->where('student_id', (int) $student_id)
                 ->where_in('status', $from)
                 ->update('tutoring_sessions', array(
                     'status'        => 'declined',
                     'meet_url'      => null,
                     'cancel_reason' => trim((string) $reason) !== ''
                                        ? mb_substr(trim($reason), 0, 255) : 'ألغاه الطالب',
                     'cancelled_by'  => 'student',
                     'updated_at'    => $now,
                 ));
        if ($this->db->affected_rows() < 1) {
            return array('ok' => false, 'msg' => 'تعذر إلغاء الحجز.');
        }

        $this->void_invoice($row);
        $this->release_slot($row);

        if ($prepaid) {
            /* والرصيد يعود بلا سطر يكتب: `declined` ليست من `$SPENT`.
               وذلك هو ثمن ان يستنتج الرصيد لا يعد — عداد هنا كان ينسى
               الزيادة في احد المسارات الاربعة التي تغلق حصة. */
            $this->audit('session.pack_return', (int) $row['id'],
                array('pack_sub_id' => (int) $row['pack_sub_id']));
            return array('ok' => true, 'teacher_id' => (int) $row['teacher_id'], 'pack' => true,
                         'msg' => 'ألغي حجزك، وعادت الحصة إلى رصيد باقتك، وعاد الموعد متاحا لغيرك.');
        }

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
        /* TQ-LIVE — سبب الالغاء مطلوب: يقرؤه الطالب والمعلم مع حال المال. */
        if (trim((string) $reason) === '') {
            return array('ok' => false, 'msg' => 'اكتب سبب الإلغاء — يقرؤه الطالب والمعلم.');
        }

        if (in_array($row['status'], array('declined', 'expired', 'refunded'), true)
            && !((string) ($row['refund_state'] ?? '') === 'choice' && $row['status'] === 'declined')) {
            return array('ok' => false, 'msg' => 'هذه الحصة مغلقة أصلا، ولا مال فيها يرد.');
        }
        if ($row['status'] === 'completed' && empty($row['paid_at'])) {
            return array('ok' => false, 'msg' => 'هذه الحصة انتهت وكانت بلا ثمن، فلا شيء يرد.');
        }

        $paid = !empty($row['paid_at']);
        /* TQ-FND-PACK — وحصة الباقة **تعاد الى الرصيد لا تسترد مالا**.
           المال دخل يوم اشتريت الباقة، ولم يخرج عنها فاتورة تشطب ولا
           دفعة ترد. فحالها `declined` — وهي ليست من `$SPENT`، فتعود
           الحصة الى رصيد صاحبها في اللحظة. ولو وسمت `refunded` لقرأ
           المسؤول «استردت» ولا مال استرد، ولانتظر الطالب تحويلا لا
           يجيء وهو يملك حصته في رصيده. وقيد المعلم يعكس ان كان قيد. */
        $pack = (int) ($row['pack_sub_id'] ?? 0) > 0;
        $new  = ($paid && !$pack) ? 'refunded' : 'declined';
        $now  = date('Y-m-d H:i:s');

        $this->db->where('id', (int) $row['id'])
                 ->update('tutoring_sessions', array(
                     'status'        => $new,
                     'meet_url'      => null,
                     'cancel_reason' => trim((string) $reason) !== ''
                                        ? mb_substr(trim($reason), 0, 255) : 'ألغتها الإدارة',
                     'cancelled_by'  => 'admin',
                     'refund_state'  => $new === 'refunded' ? 'pending' : null,
                     'updated_at'    => $now,
                 ));

        if ($paid) {
            if (!$pack) {
                $this->db->where('id', (int) $row['invoice_id'])
                         ->update('invoices', array('status' => 'refunded'));
            }
            try {
                $this->load->model('taqdar_wallet_model');
                $this->taqdar_wallet_model->reverse_session(
                    (int) $row['teacher_id'], (int) $row['id'],
                    $pack ? 'ألغيت حصة باقة' : 'استردت الحصة');
            } catch (Throwable $e) {
                log_message('error', 'TQ-SESSION-REVERSE: تعذر عكس حصة #' . (int) $row['id']);
            }
        } else {
            $this->void_invoice($row);
        }

        $this->release_slot($row);
        $this->audit('session.' . ($paid ? 'refund' : 'cancel'), (int) $row['id'],
            array('reason' => $reason, 'actor' => (int) $actor_id, 'amount' => (int) $row['price_halalas']));

        if ($pack) {
            return array('ok' => true, 'refunded' => false, 'pack' => true,
                         'student_id' => (int) $row['student_id'],
                         'teacher_id' => (int) $row['teacher_id'],
                         'amount'     => (int) $row['price_halalas'],
                         'msg' => 'ألغيت الحصة، وعادت إلى رصيد باقة الطالب، وعكس قيد معلمها إن كان قيد.'
                                . ' ولا مال يرد: ثمنها في رصيده يحجز به من جديد.');
        }

        return array('ok' => true, 'refunded' => $paid,
                     'student_id' => (int) $row['student_id'],
                     'teacher_id' => (int) $row['teacher_id'],
                     'amount'     => (int) $row['price_halalas'],
                     'msg' => $paid
                         ? 'وسمت الحصة مستردة وعكس قيد معلمها. رد المبلغ إلى الطالب من لوحة تاب — المنصة لا تردها بنفسها.'
                         : 'ألغيت الحصة، وشطبت فاتورتها، وحرر وقتها.');
    }

    /* =====================================================================
       TQ-LIVE — المرحلة الثانية والثالثة: التاجيل، الالغاء بسبب،
       الاسترداد بالساعات، غياب المعلم، الحضور، التسجيل.
       القواعد الخالصة ساكنة (تفحص بلا قاعدة)، والكتابة تحتها.
       ===================================================================== */

    /** الحالات التي تؤجل منها حصة: مدفوعة مثبتة، او محجوزة تنتظر الدفع. */
    public static $MOVABLE = array('confirmed', 'awaiting_payment');

    /**
     * ايجوز التاجيل؟ '' = نعم، والا مفتاح المانع:
     * status · past · count · window. والطالب مرة واحدة حتى `$hours` قبل
     * الموعد (D6)، والمعلم والمسؤول اي وقت قبله.
     */
    public static function reschedule_block($role, $status, $starts_ts, $now, $count, $hours)
    {
        if (!in_array((string) $status, self::$MOVABLE, true)) return 'status';
        if ((int) $starts_ts <= 0 || (int) $starts_ts <= (int) $now) return 'past';
        if ((string) $role === 'student') {
            if ((int) $count >= 1) return 'count';
            if ((int) $starts_ts - (int) $now < (int) $hours * 3600) return 'window';
        }
        return '';
    }

    /** نص المانع لمن يقرؤه. */
    public function reschedule_block_text($code)
    {
        $h = $this->config()['reschedule_hours'];
        $map = array(
            'status' => 'لا تؤجل إلا حصة مثبتة أو تنتظر الدفع.',
            'past'   => 'بدأ موعد هذه الحصة أو مضى، فلا تؤجل.',
            'count'  => 'أجلت هذه الحصة من قبل، والتأجيل مرة واحدة لكل حصة.',
            'window' => 'التأجيل متاح حتى ' . $h . ' ساعة قبل الموعد، وقد قرب الموعد.',
        );
        return $map[$code] ?? 'لا يجوز تأجيل هذه الحصة.';
    }

    /**
     * الموعد الجديد من نطاق القديم: المعلم نفسه والنوع نفسه، ومسار التاسيس
     * نفسه، او الصف والمادة نفسهما (والموعد المفتوح للكل يقبل).
     */
    public static function same_scope($old, $new)
    {
        if ((int) ($old['teacher_id'] ?? 0) !== (int) ($new['teacher_id'] ?? -1)) return false;
        $ko = (string) ($old['kind'] ?? 'curriculum') ?: 'curriculum';
        $kn = (string) ($new['kind'] ?? 'curriculum') ?: 'curriculum';
        if ($ko !== $kn) return false;
        if ($ko === 'foundation') {
            return (int) ($old['track_id'] ?? 0) === (int) ($new['track_id'] ?? 0);
        }
        foreach (array('grade_id', 'subject_id') as $k) {
            $n = (int) ($new[$k] ?? 0);
            if ($n > 0 && $n !== (int) ($old[$k] ?? 0)) return false;
        }
        return true;
    }

    /**
     * D5-b — الغاء الطالب لحصة مدفوعة: refund · no_refund · pack · unpaid.
     * والحد شامل: من الغى قبل الموعد بالمدة تماما يسترد.
     */
    public static function refund_decision($starts_ts, $now, $hours, $paid, $pack)
    {
        if ($pack) return 'pack';
        if (!$paid) return 'unpaid';
        return ((int) $starts_ts - (int) $now >= (int) $hours * 3600) ? 'refund' : 'no_refund';
    }

    /**
     * D10 — مرحلة غياب المعلم: 0 لا شيء · 1 تاخر عشر دقائق · 2 عشرون.
     * تشترط حصة مثبتة او جارية بلا `teacher_joined_at`، ولم يغلق رابطها.
     */
    public static function absence_stage($row, $now, $grace_hours = 6)
    {
        if (!in_array((string) ($row['status'] ?? ''), array('confirmed', 'live'), true)) return 0;
        if (!empty($row['teacher_joined_at'])) return 0;
        $starts = !empty($row['starts_at']) ? (int) strtotime((string) $row['starts_at']) : 0;
        if ($starts <= 0) return 0;
        $mins = (int) ($row['duration_min'] ?? 0) ?: 60;
        if ((int) $now > $starts + $mins * 60 + (int) $grace_hours * 3600) return 0;
        $late = (int) $now - $starts;
        if ($late >= 20 * 60) return 2;
        if ($late >= 10 * 60) return 1;
        return 0;
    }

    /**
     * D4 — ما يجب على تسجيل منتظر: '' · remind (بعد ٤٨ ساعة مرة) · drop
     * (بعد سبعة ايام يسقط الانتظار الى «لا تسجيل»).
     */
    public static function recording_step($row, $now)
    {
        if ((int) ($row['recording_pending'] ?? 0) !== 1) return '';
        if (trim((string) ($row['recording_url'] ?? '')) !== '') return '';
        $done = !empty($row['completed_at']) ? (int) strtotime((string) $row['completed_at']) : 0;
        if ($done <= 0) return '';
        if ((int) $now >= $done + 7 * 86400) return 'drop';
        if ((int) $now >= $done + 48 * 3600 && empty($row['recording_reminded_at'])) return 'remind';
        return '';
    }

    /**
     * D3 — الحضور الذي يعتمد بعد ٢٤ ساعة بلا تصحيح، او null (لا يمس).
     * النقرة حضور، وغيابها غياب **متى كان الدخول يقاس** (دخل المعلم من
     * بابنا)؛ وحصة لم يقس فيها شيء تبقى بلا حكم — لا يكتب غياب على تخمين.
     */
    public static function attendance_final($row, $now)
    {
        if (!empty($row['attendance'])) return null;
        if ((string) ($row['status'] ?? '') !== 'completed') return null;
        $done = !empty($row['completed_at']) ? (int) strtotime((string) $row['completed_at']) : 0;
        if ($done <= 0 || (int) $now < $done + 24 * 3600) return null;
        if (!empty($row['attended_at'])) return 'present';
        if (!empty($row['teacher_joined_at'])) return 'absent';
        return null;
    }

    /** D3 — يصحح الحضور خلال ٢٤ ساعة من الانتهاء وحدها، ضبط ام لم يضبط. */
    public static function attendance_editable($row, $now)
    {
        $done = !empty($row['completed_at']) ? (int) strtotime((string) $row['completed_at']) : 0;
        return $done > 0 && (int) $now <= $done + 24 * 3600;
    }

    /**
     * ايقيد الاغلاق الالي نصيب المعلم؟ لا لمعلم سجل غيابه ولم يدخل بعده،
     * ولا لحصة استردادها قيد مراجعة الادارة.
     */
    public static function may_auto_credit($row)
    {
        if ((string) ($row['refund_state'] ?? '') === 'review') return false;
        if (!empty($row['teacher_absent_at']) && empty($row['teacher_joined_at'])) return false;
        return true;
    }

    public static function clean_attendance($v)
    {
        $v = (string) $v;
        return in_array($v, array('present', 'absent'), true) ? $v : null;
    }

    /** رابط تسجيل مقبول: https على يوتيوب او فيميو او درايف. */
    public static function recording_host_ok($url)
    {
        $url = (string) $url;
        if (stripos($url, 'https://') !== 0) return false;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (array('youtube.com', 'youtu.be', 'vimeo.com', 'drive.google.com') as $h) {
            if ($host === $h || substr($host, -strlen('.' . $h)) === '.' . $h) return true;
        }
        return false;
    }

    public function clean_recording_url($raw)
    {
        $u = function_exists('tq_clean_url') ? tq_clean_url($raw) : trim((string) $raw);
        return self::recording_host_ok($u) ? $u : '';
    }

    /** صف الحصة مع موعدها — بلا شرط ملكية (يفحصه النادي). */
    private function live_row($session_id)
    {
        try {
            return $this->db->select('t.*, a.starts_at, a.duration_min, a.grade_id, a.subject_id,
                                      ut.tq_meet_url AS teacher_meet_url')
                            ->from('tutoring_sessions t')
                            ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                            ->join('users ut', 'ut.id = t.teacher_id', 'left')
                            ->where('t.id', (int) $session_id)
                            ->get()->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
    }

    /** ايملك هذا الفاعل الحصة؟ الطالب صاحبها، والمعلم معلمها، والمسؤول كلها. */
    private function actor_owns($row, $actor_id, $role)
    {
        if ((string) $role === 'admin') return true;
        if ((string) $role === 'teacher') return (int) $row['teacher_id'] === (int) $actor_id;
        if ((string) $role === 'student') return (int) $row['student_id'] === (int) $actor_id;
        return false;
    }

    /** الصف والمادة والمسار التي ينسب اليها الموعد القديم. */
    private function scope_row($row)
    {
        return array(
            'teacher_id' => (int) $row['teacher_id'],
            'kind'       => (string) ($row['kind'] ?? 'curriculum'),
            'track_id'   => (int) ($row['track_id'] ?? 0),
            'grade_id'   => (int) ($row['grade_id'] ?? 0),
            'subject_id' => (int) ($row['subject_id'] ?? 0),
        );
    }

    /**
     * مواعيد بديلة: المفتوحة القادمة للمعلم نفسه في النطاق نفسه — شبكة
     * `available_teachers()` نفسها مرشحة بالمعلم.
     */
    public function reschedule_options($session_id, $actor_role, $actor_id = 0)
    {
        $this->install_schema();
        $row = $this->live_row($session_id);
        if (!$row) return array();
        if ((string) $actor_role !== 'admin' && !$this->actor_owns($row, $actor_id, $actor_role)) return array();

        $this->expire_holds();
        try {
            $slots = $this->db->select('id, teacher_id, starts_at, duration_min, grade_id, subject_id, kind, track_id')
                              ->where('teacher_id', (int) $row['teacher_id'])
                              ->where('status', 'open')
                              ->where('starts_at >', date('Y-m-d H:i:s'))
                              ->order_by('starts_at', 'ASC')->limit(60)
                              ->get('availability_slots')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }

        $old = $this->scope_row($row);
        $out = array();
        foreach ($slots as $s) {
            if ((int) $s['id'] === (int) $row['slot_id']) continue;
            if (!self::same_scope($old, $s)) continue;
            $out[] = array(
                'id'        => (int) $s['id'],
                'starts_at' => $s['starts_at'],
                'when_text' => $this->when_text($s['starts_at'], (int) $s['duration_min']),
            );
        }
        return $out;
    }

    /**
     * ينقل الحصة الى موعد جديد — قاعدة واحدة للتاجيل ولبديل خطا المعلم.
     * التحديث المشروط على الموعد الجديد حارس التزاحم، ثم يفك القديم. والسعر
     * والفاتورة لا يتغيران.
     *
     * @param array $set اعمدة تكتب مع النقل (الحال، العداد…)
     * @param array $from الحالات المقبولة للتحديث المشروط
     */
    private function move_to_slot($row, $new_slot_id, $set, $from)
    {
        $new_slot_id = (int) $new_slot_id;
        $slot = $this->db->where('id', $new_slot_id)->get('availability_slots')->row_array();
        $now  = time();
        if (!$slot || (string) $slot['status'] !== 'open' || strtotime((string) $slot['starts_at']) <= $now) {
            return array('ok' => false, 'msg' => 'هذا الموعد لم يعد متاحا. اختر غيره.');
        }
        if (!self::same_scope($this->scope_row($row), $slot)) {
            return array('ok' => false, 'msg' => 'الموعد البديل يكون من مواعيد المعلم نفسه وفي النطاق نفسه.');
        }

        $target = ($set['status'] ?? $row['status']) === 'awaiting_payment' ? 'held' : 'booked';
        $this->db->where('id', $new_slot_id)->where('status', 'open')
                 ->update('availability_slots', array('status' => $target));
        if ($this->db->affected_rows() < 1) {
            return array('ok' => false, 'msg' => 'سبقك أحد إلى هذا الموعد. اختر غيره.');
        }

        $stamp = date('Y-m-d H:i:s', $now);
        $upd = array_merge(array(
            'slot_id'           => $new_slot_id,
            'rescheduled_from'  => (int) $row['slot_id'],
            'rescheduled_at'    => $stamp,
            'teacher_joined_at' => null,
            'attended_at'       => null,
            'teacher_absent_at' => null,
            'nolink_alerted_at' => null,
            'updated_at'        => $stamp,
        ), $set);
        $new_start = strtotime((string) $slot['starts_at']);
        if (($upd['status'] ?? $row['status']) === 'awaiting_payment' && !empty($row['pay_deadline'])
            && strtotime((string) $row['pay_deadline']) > $new_start) {
            $upd['pay_deadline'] = date('Y-m-d H:i:s', $new_start);
        }

        /* حارس التزاحم: الصف كما قرئ — موعده، وعداد الطالب ان كان هو. فطلبان
           متزامنان ينجح اولهما، ويطابق الثاني صفرا فيفك ما حجزه. */
        $this->db->where('id', (int) $row['id'])->where_in('status', $from)
                 ->where('slot_id', (int) $row['slot_id']);
        if (array_key_exists('reschedule_count', $set)) {
            $this->db->where('reschedule_count', (int) ($row['reschedule_count'] ?? 0));
        }
        $this->db->update('tutoring_sessions', $upd);
        if ($this->db->affected_rows() < 1) {
            $this->db->where('id', $new_slot_id)->where('status', $target)
                     ->update('availability_slots', array('status' => 'open'));
            return array('ok' => false, 'msg' => 'تغير حال الحصة. حدث الصفحة وأعد المحاولة.');
        }
        $this->release_slot($row);
        return array('ok' => true, 'slot' => $slot);
    }

    /**
     * التاجيل (spec §2.2). الطالب مرة حتى `reschedule_hours` قبل الموعد،
     * والمعلم والمسؤول اي وقت قبله. ويخبر الطرف الاخر وولي الامر.
     */
    public function reschedule($session_id, $new_slot_id, $actor_id, $actor_role)
    {
        $this->install_schema();
        $role = in_array((string) $actor_role, array('student', 'teacher', 'admin'), true) ? (string) $actor_role : '';
        $row  = $this->live_row($session_id);
        if (!$row || $role === '' || !$this->actor_owns($row, $actor_id, $role)) {
            return array('ok' => false, 'msg' => 'هذه الحصة ليست لك أو لم تعد موجودة.');
        }
        $starts = !empty($row['starts_at']) ? (int) strtotime((string) $row['starts_at']) : 0;
        $block  = self::reschedule_block($role, $row['status'], $starts, time(),
                                         (int) ($row['reschedule_count'] ?? 0), $this->config()['reschedule_hours']);
        if ($block !== '') return array('ok' => false, 'code' => $block, 'msg' => $this->reschedule_block_text($block));

        /* العداد يحسب تاجيل الطالب وحده: مرته له، وتاجيل المعلم لا ياكلها. */
        $set = array('rescheduled_by' => $role);
        if ($role === 'student') $set['reschedule_count'] = (int) ($row['reschedule_count'] ?? 0) + 1;
        $m = $this->move_to_slot($row, $new_slot_id, $set, array($row['status']));
        if (empty($m['ok'])) return $m;

        $old_when = $starts > 0 ? $this->when_text($row['starts_at'], (int) $row['duration_min']) : '';
        $new_when = $this->when_text($m['slot']['starts_at'], (int) $m['slot']['duration_min']);
        $this->audit('session.rescheduled', (int) $row['id'], array(
            'by' => $role, 'actor' => (int) $actor_id,
            'from_slot' => (int) $row['slot_id'], 'to_slot' => (int) $new_slot_id,
        ));

        $text = 'تغير موعد الحصة من ' . $old_when . ' إلى ' . $new_when . '. السعر والفاتورة كما هما.';
        if ($role !== 'student') $this->tell((int) $row['student_id'], 'تغير موعد حصتك', $text);
        if ($role !== 'teacher') $this->tell((int) $row['teacher_id'], 'تغير موعد حصة في جدولك', $text);
        $this->tell_parents((int) $row['student_id'], 'تغير موعد حصة ابنك', $text);

        return array('ok' => true, 'student_id' => (int) $row['student_id'], 'teacher_id' => (int) $row['teacher_id'],
                     'starts_at' => $m['slot']['starts_at'],
                     'msg' => 'أجلت الحصة إلى ' . $new_when . '.');
    }

    /**
     * الغاء المعلم حصة (D5) — السبب مطلوب. المدفوعة تفتح للطالب خياري
     * البديل او الاسترداد (`refund_state = choice`)، والباقة تعود الى الرصيد،
     * وغير المدفوعة تشطب فاتورتها.
     */
    public function teacher_cancel($session_id, $teacher_id, $reason)
    {
        $this->install_schema();
        $reason = trim((string) $reason);
        if ($reason === '') return array('ok' => false, 'msg' => 'اكتب سبب الإلغاء — يقرؤه الطالب.');
        $row = $this->live_row($session_id);
        if (!$row || (int) $row['teacher_id'] !== (int) $teacher_id) {
            return array('ok' => false, 'msg' => 'هذه الحصة ليست لك أو لم تعد موجودة.');
        }
        if (!in_array($row['status'], array('confirmed', 'awaiting_payment'), true)) {
            return array('ok' => false, 'msg' => 'لا تلغى إلا حصة مثبتة أو تنتظر الدفع لم تبدأ.');
        }
        $starts = !empty($row['starts_at']) ? (int) strtotime((string) $row['starts_at']) : 0;
        if ($starts > 0 && $starts <= time()) {
            return array('ok' => false, 'msg' => 'بدأ موعد هذه الحصة، فلا تلغى الآن. راسل الإدارة.');
        }

        $paid = !empty($row['paid_at']) && (int) $row['price_halalas'] > 0;
        $pack = (int) ($row['pack_sub_id'] ?? 0) > 0;
        /* مفردة مثبتة (مدفوعة او مجانية): للطالب موعد بديل، وللمدفوعة استرداد. */
        $choice = !$pack && $row['status'] === 'confirmed';
        $now  = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $row['id'])->where('status', $row['status'])
                 ->update('tutoring_sessions', array(
                     'status'        => 'declined',
                     'cancel_reason' => mb_substr($reason, 0, 255),
                     'cancelled_by'  => 'teacher',
                     'refund_state'  => $choice ? 'choice' : null,
                     'updated_at'    => $now,
                 ));
        if ($this->db->affected_rows() < 1) return array('ok' => false, 'msg' => 'تعذر الإلغاء. حدث الصفحة.');

        if (!$paid) $this->void_invoice($row);
        $this->release_slot($row);
        $this->audit('session.teacher_cancel', (int) $row['id'], array('reason' => $reason, 'paid' => $paid, 'pack' => $pack));

        $when = $starts > 0 ? $this->when_text($row['starts_at'], (int) $row['duration_min']) : '';
        $body = 'ألغى المعلم الحصة ' . $when . ' — ' . $reason . '. '
              . ($choice ? 'اختر من شاشة حصصك موعدا بديلا مع المعلم نفسه بلا رسوم' . ($paid ? '، أو الاسترداد.' : '.')
                                 : ($pack ? 'عادت الحصة إلى رصيد باقتك.' : 'لم يخصم منك شيء.'));
        $this->tell((int) $row['student_id'], 'ألغى المعلم حصتك', $body);
        $this->tell_parents((int) $row['student_id'], 'ألغيت حصة ابنك', $body);

        return array('ok' => true, 'student_id' => (int) $row['student_id'], 'choice' => $choice,
                     'msg' => 'ألغيت الحصة، وأخبر الطالب بالسبب.'
                            . ($choice ? ' يختار الطالب موعدا بديلا معك' . ($paid ? ' أو الاسترداد.' : '.') : ''));
    }

    /**
     * D10 — فحص كسول لغياب المعلم عند قراءة الشاشة: بعد عشر دقائق بلا دخوله
     * يخبر المسؤول والطالب مرة واحدة (`teacher_absent_at`). يرد المرحلة.
     */
    public function check_teacher_absence($row, $now = null)
    {
        $now   = $now ? (int) $now : time();
        $stage = self::absence_stage($row, $now, $this->config()['grace_hours']);
        if ($stage < 1 || !empty($row['teacher_absent_at']) || empty($row['id'])) return $stage;

        try {
            $this->db->where('id', (int) $row['id'])->where('teacher_absent_at IS NULL', null, false)
                     ->where('teacher_joined_at IS NULL', null, false)
                     ->update('tutoring_sessions', array('teacher_absent_at' => date('Y-m-d H:i:s', $now)));
            if ($this->db->affected_rows() < 1) return $stage;
        } catch (Throwable $e) { $this->db->reset_query(); return $stage; }

        $this->tell((int) $row['student_id'], 'المعلم تأخر',
            'لم يدخل معلمك الحصة بعد عشر دقائق من موعدها. انتظر قليلا، وإن لم يحضر خلال عشرين دقيقة '
            . 'يظهر لك في شاشة حصصك زر «المعلم لم يحضر» لتختار موعدا بديلا أو الاسترداد.');
        foreach ($this->admin_ids() as $aid) {
            $this->tell($aid, 'معلم لم يدخل حصته', 'الحصة #' . (int) $row['id'] . ' بدأت منذ عشر دقائق ولم يدخلها معلمها.');
        }
        $this->audit('session.teacher_late', (int) $row['id'], array());
        return $stage;
    }

    /**
     * خيار الطالب بعد خطا المعلم (اعتذار بعد الدفع او غياب — D5/D10):
     * reschedule الى موعد للمعلم نفسه بلا رسوم، او refund يسجل استردادا
     * ينفذ خارج المنصة. ولا يقيد للمعلم شيء.
     */
    public function choose_after_teacher_fault($session_id, $student_id, $choice, $slot_id = 0)
    {
        $this->install_schema();
        $row = $this->live_row($session_id);
        if (!$row || (int) $row['student_id'] !== (int) $student_id) {
            return array('ok' => false, 'msg' => 'هذه الحصة ليست لك أو لم تعد موجودة.');
        }
        if (!in_array($choice, array('reschedule', 'refund'), true)) {
            return array('ok' => false, 'msg' => 'اختر موعدا بديلا أو الاسترداد.');
        }

        /* غياب: الزر يظهر بعد عشرين دقيقة، والخادم يعيد الحكم لا يصدق الزر.
           والغياب دعوى الطالب و`teacher_joined_at` شاهد واحد (قد يدخل المعلم
           برابط محفوظ)، فالاسترداد لا يقع هنا: يرفع الى الادارة (`review`)
           والمال لا يمس. والبديل يقع في الحال — لا مال فيه — والحال لا
           تكتب قبل نجاح النقل. */
        if (in_array($row['status'], array('confirmed', 'live'), true)) {
            if (self::absence_stage($row, time(), $this->config()['grace_hours']) < 2) {
                return array('ok' => false, 'msg' => 'لم تمض عشرون دقيقة على الموعد بلا معلم بعد.');
            }
            $absent_at = !empty($row['teacher_absent_at']) ? $row['teacher_absent_at'] : date('Y-m-d H:i:s');
            if ($choice === 'reschedule') {
                $m = $this->move_to_slot($row, $slot_id, array(
                    'status' => 'confirmed', 'rescheduled_by' => 'teacher_fault',
                ), array('confirmed', 'live'));
                if (empty($m['ok'])) return $m;
                $this->audit('session.teacher_no_show', (int) $row['id'], array('choice' => 'reschedule', 'absent_at' => $absent_at));
                $when = $this->when_text($m['slot']['starts_at'], (int) $m['slot']['duration_min']);
                $this->tell((int) $row['teacher_id'], 'سجل غيابك عن حصة',
                    'لم تدخل الحصة #' . (int) $row['id'] . ' في موعدها، فاختار الطالب موعدا بديلا: ' . $when . '.');
                $this->tell_parents((int) $row['student_id'], 'موعد بديل لحصة ابنك', 'الموعد الجديد: ' . $when . '.');
                return array('ok' => true, 'msg' => 'ثبت الموعد البديل ' . $when . ' بلا رسوم.');
            }
            $this->db->where('id', (int) $row['id'])->where_in('status', array('confirmed', 'live'))
                     ->where('teacher_joined_at IS NULL', null, false)
                     ->update('tutoring_sessions', array(
                         'status' => 'declined', 'cancel_reason' => 'أعلن الطالب غياب المعلم',
                         'cancelled_by' => 'teacher', 'refund_state' => 'review',
                         'teacher_absent_at' => $absent_at, 'updated_at' => date('Y-m-d H:i:s')));
            if ($this->db->affected_rows() < 1) return array('ok' => false, 'msg' => 'تغير حال الحصة. حدث الصفحة.');
            $this->release_slot($row);
            $this->audit('session.teacher_no_show', (int) $row['id'], array('choice' => 'refund', 'state' => 'review'));
            $this->tell((int) $row['teacher_id'], 'بلاغ غياب عن حصة',
                'أبلغ الطالب أنك لم تدخل الحصة #' . (int) $row['id'] . '. تراجع الإدارة البلاغ قبل أي قرار.');
            foreach ($this->admin_ids() as $aid) {
                $this->tell($aid, 'بلاغ غياب معلم ينتظر قرارك',
                    'الحصة #' . (int) $row['id'] . ' — أعلن الطالب غياب المعلم وطلب الاسترداد. قرر من شاشة الحصص.');
            }
            return array('ok' => true, 'review' => true,
                         'msg' => 'وصل بلاغك إلى الإدارة، وتراجعه وتخبرك بقرارها.');
        }

        if ((string) $row['status'] !== 'declined') {
            return array('ok' => false, 'msg' => 'لا خيار ينتظرك في هذه الحصة.');
        }
        /* حصة الباقة او المجانية لا خيار فيها: الاعتذار اعادها الى الرصيد
           (`declined` ليست من `$SPENT`)، واعادة حجزها هنا تنفق الحصة مرتين. */
        if ((int) ($row['pack_sub_id'] ?? 0) > 0 && (string) ($row['cancelled_by'] ?? '') === 'teacher') {
            return array('ok' => true, 'pack' => true, 'msg' => 'عادت الحصة إلى رصيد باقتك، احجز بها موعدا متى شئت.');
        }
        if ((string) ($row['refund_state'] ?? '') !== 'choice') {
            return array('ok' => false, 'msg' => 'لا خيار ينتظرك في هذه الحصة.');
        }

        if ($choice === 'reschedule') {
            $m = $this->move_to_slot($row, $slot_id, array(
                'status' => 'confirmed', 'refund_state' => null, 'rescheduled_by' => 'teacher_fault',
                'cancel_reason' => null, 'cancelled_by' => null,
            ), array('declined'));
            if (empty($m['ok'])) return $m;
            $when = $this->when_text($m['slot']['starts_at'], (int) $m['slot']['duration_min']);
            $this->audit('session.rescheduled', (int) $row['id'], array('by' => 'teacher_fault',
                'from_slot' => (int) $row['slot_id'], 'to_slot' => (int) $slot_id));
            $this->tell((int) $row['teacher_id'], 'موعد بديل لحصة ألغيت',
                'اختار الطالب موعدا بديلا: ' . $when . '. احضر في موعدك.');
            $this->tell_parents((int) $row['student_id'], 'موعد بديل لحصة ابنك', 'الموعد الجديد: ' . $when . '.');
            return array('ok' => true, 'msg' => 'ثبت الموعد البديل ' . $when . ' بلا رسوم.');
        }

        if (empty($row['paid_at']) || (int) $row['price_halalas'] <= 0) {
            $this->db->where('id', (int) $row['id'])->where('refund_state', 'choice')
                     ->update('tutoring_sessions', array('refund_state' => null, 'updated_at' => date('Y-m-d H:i:s')));
            return array('ok' => true, 'msg' => 'أغلقت الحصة. لم تدفع فيها شيئا فلا مبلغ يرد.');
        }
        $this->db->where('id', (int) $row['id'])->where('status', 'declined')->where('refund_state', 'choice')
                 ->update('tutoring_sessions', array('status' => 'refunded', 'refund_state' => 'pending',
                                                     'updated_at' => date('Y-m-d H:i:s')));
        if ($this->db->affected_rows() < 1) return array('ok' => false, 'msg' => 'تغير حال الحصة. حدث الصفحة.');
        $this->mark_refunded($row, 'student_choice');
        return array('ok' => true, 'refunded' => true,
                     'msg' => 'سجل طلب الاسترداد. يرد خلال ٥ أيام عمل خارج المنصة.');
    }

    /**
     * قرار الادارة في بلاغ غياب المعلم (`refund_state = review`): approve →
     * مسار الاسترداد نفسه مع عكس قيد المعلم؛ deny → تعود الحصة منتهية
     * ويقيد نصيب معلمها. والملاحظة مطلوبة، والطرفان يعلمان.
     */
    public function admin_noshow_decide($session_id, $decision, $note, $actor_id = 0)
    {
        $this->install_schema();
        $note = trim((string) $note);
        if (!in_array($decision, array('approve', 'deny'), true)) return array('ok' => false, 'msg' => 'قرار غير معروف.');
        if ($note === '') return array('ok' => false, 'msg' => 'اكتب ملاحظة القرار — يقرؤها الطالب والمعلم.');
        $row = $this->live_row($session_id);
        if (!$row || $row['status'] !== 'declined' || (string) ($row['refund_state'] ?? '') !== 'review') {
            return array('ok' => false, 'msg' => 'لا بلاغ ينتظر قرارا في هذه الحصة.');
        }
        $now = date('Y-m-d H:i:s');
        $paid = !empty($row['paid_at']) && (int) $row['price_halalas'] > 0;
        if ($decision === 'approve') {
            $this->db->where('id', (int) $row['id'])->where('refund_state', 'review')
                     ->update('tutoring_sessions', array(
                         'status' => $paid ? 'refunded' : 'declined', 'refund_state' => $paid ? 'pending' : null,
                         'updated_at' => $now));
            if ($this->db->affected_rows() < 1) return array('ok' => false, 'msg' => 'تغير حال الحصة. حدث الصفحة.');
            if ($paid) $this->mark_refunded($row, 'noshow_review');
            $s_txt = 'ثبت غياب المعلم' . ($paid ? '، ويرد مبلغ الحصة خلال ٥ أيام عمل خارج المنصة' : '') . '. ' . $note;
            $t_txt = 'ثبت غيابك عن الحصة #' . (int) $row['id'] . '، فلا يقيد لك شيء عنها. ' . $note;
        } else {
            $this->db->where('id', (int) $row['id'])->where('refund_state', 'review')
                     ->update('tutoring_sessions', array(
                         'status' => 'completed', 'refund_state' => 'denied',
                         'completed_at' => !empty($row['completed_at']) ? $row['completed_at'] : $now,
                         'cancel_reason' => null, 'cancelled_by' => null, 'updated_at' => $now));
            if ($this->db->affected_rows() < 1) return array('ok' => false, 'msg' => 'تغير حال الحصة. حدث الصفحة.');
            $this->credit($row);
            $s_txt = 'راجعت الإدارة بلاغك عن غياب المعلم ولم يثبت، فاعتمدت الحصة منتهية. ' . $note;
            $t_txt = 'لم يثبت بلاغ الغياب عن الحصة #' . (int) $row['id'] . '، واعتمدت منتهية وقيد نصيبك. ' . $note;
        }
        $this->audit('session.noshow_review', (int) $row['id'], array('decision' => $decision, 'note' => $note, 'actor' => (int) $actor_id));
        $this->tell((int) $row['student_id'], 'قرار الإدارة في بلاغ الغياب', $s_txt);
        $this->tell((int) $row['teacher_id'], 'قرار الإدارة في بلاغ الغياب', $t_txt);
        return array('ok' => true, 'msg' => $decision === 'approve'
            ? ($paid ? 'ثبت الغياب ووسمت الحصة مستردة. رد المبلغ من لوحة تاب.' : 'ثبت الغياب وأغلقت الحصة.')
            : 'رفض البلاغ واعتمدت الحصة منتهية وقيد نصيب المعلم.');
    }

    /** الفاتورة مستردة، وقيد المعلم يعكس ان وجد، والمسؤول يعلم ليرد من تاب. */
    private function mark_refunded($row, $why)
    {
        if ((int) ($row['invoice_id'] ?? 0) > 0) {
            $this->db->where('id', (int) $row['invoice_id'])->update('invoices', array('status' => 'refunded'));
        }
        try {
            $this->load->model('taqdar_wallet_model');
            $this->taqdar_wallet_model->reverse_session((int) $row['teacher_id'], (int) $row['id'], 'استردت الحصة');
        } catch (Throwable $e) {
            log_message('error', 'TQ-SESSION-REVERSE: تعذر عكس حصة #' . (int) $row['id']);
        }
        $this->audit('session.refund', (int) $row['id'], array('why' => $why, 'amount' => (int) $row['price_halalas']));
        foreach ($this->admin_ids() as $aid) {
            $this->tell($aid, 'استرداد حصة ينتظر التنفيذ',
                'الحصة #' . (int) $row['id'] . ' وسمت مستردة (' . number_format((int) $row['price_halalas'] / 100, 2)
                . ' ريال). رد المبلغ إلى الطالب من لوحة تاب خلال ٥ أيام عمل.');
        }
    }

    /**
     * تسجيل الحصة بعد انتهائها، او تصحيح الحضور خلال ٢٤ ساعة (D3/D4).
     * الرابط يمر بـ`tq_clean_url()` ويقبل يوتيوب وفيميو ودرايف.
     */
    public function set_recording($session_id, $teacher_id, $url, $attendance = null, $later = false)
    {
        $this->install_schema();
        $row = $this->live_row($session_id);
        if (!$row || ((int) $teacher_id > 0 && (int) $row['teacher_id'] !== (int) $teacher_id)) {
            return array('ok' => false, 'msg' => 'هذه الحصة ليست لك أو لم تعد موجودة.');
        }
        if ($row['status'] !== 'completed') return array('ok' => false, 'msg' => 'التسجيل يضاف لحصة انتهت.');

        $upd = array();
        $raw = trim((string) $url);
        if ($raw !== '') {
            $clean = $this->clean_recording_url($raw);
            if ($clean === '') return array('ok' => false, 'msg' => 'رابط التسجيل يكون من يوتيوب أو فيميو أو جوجل درايف ويبدأ بـhttps.');
            $upd['recording_url'] = $clean;
            $upd['recording_pending'] = 0;
        } elseif ($later) {
            $upd['recording_pending'] = 1;
        }
        $att = self::clean_attendance($attendance);
        if ($att !== null) {
            if (!self::attendance_editable($row, time())) {
                return array('ok' => false, 'msg' => 'مضت ٢٤ ساعة على الحصة، واعتمد الحضور كما سجل.');
            }
            $upd['attendance'] = $att;
        }
        if (!$upd) return array('ok' => false, 'msg' => 'لا شيء يحفظ.');
        $upd['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $row['id'])->update('tutoring_sessions', $upd);

        if (!empty($upd['recording_url'])) {
            $this->tell((int) $row['student_id'], 'تسجيل حصتك متاح', 'أضاف معلمك تسجيل الحصة. تجده في شاشة حصصك.');
            $this->tell_parents((int) $row['student_id'], 'تسجيل حصة ابنك متاح', 'أضاف المعلم تسجيل الحصة.');
        }
        return array('ok' => true, 'msg' => !empty($upd['recording_url']) ? 'حفظ رابط التسجيل، وأخبر الطالب.' : 'حفظ.');
    }

    /**
     * التسجيل لمن يحق له: الطالب صاحب الحصة، او ولي امره، او معلمها. يرد
     * الصف ورابطه النظيف، او null — والعرض بـ`tqs_video_embed()` لا رابط عار.
     */
    public function recording_for($session_id, $user_id, $role)
    {
        $this->install_schema();
        $row = $this->live_row($session_id);
        if (!$row || trim((string) ($row['recording_url'] ?? '')) === '' || $row['status'] !== 'completed') return null;
        $ok = false;
        if ($role === 'student') $ok = (int) $row['student_id'] === (int) $user_id;
        elseif ($role === 'teacher') $ok = (int) $row['teacher_id'] === (int) $user_id;
        elseif ($role === 'admin') $ok = true;
        elseif ($role === 'parent') {
            $this->load->model('taqdar_parent_model');
            $ok = $this->taqdar_parent_model->owns((int) $user_id, (int) $row['student_id']);
        }
        if (!$ok) return null;
        $row['scope'] = $this->scope_of($row);
        $row['when_text'] = !empty($row['starts_at']) ? $this->when_text($row['starts_at'], (int) $row['duration_min']) : '';
        return $row;
    }

    /**
     * حقول المرحلتين الثانية والثالثة على صف حصة — يضاف الى الصفوف ولا
     * يغير مفتاحا قائما.
     */
    private function live_extra($r, $actor)
    {
        $starts = !empty($r['starts_at']) ? (int) strtotime((string) $r['starts_at']) : 0;
        $role   = (string) $actor;
        $block  = self::reschedule_block($role, (string) $r['status'], $starts, time(),
                                         (int) ($r['reschedule_count'] ?? 0), $this->config()['reschedule_hours']);
        $paid   = !empty($r['paid_at']) && (int) ($r['price_halalas'] ?? 0) > 0;
        $pack   = (int) ($r['pack_sub_id'] ?? 0) > 0;
        $refund = '';
        if ($role === 'student' && $r['status'] === 'confirmed' && $paid && !$pack) {
            $refund = self::refund_decision($starts, time(), $this->config()['refund_hours'], true, false);
        }
        return array(
            'can_reschedule'    => $block === '',
            'reschedule_block'  => $block,
            'reschedule_count'  => (int) ($r['reschedule_count'] ?? 0),
            'rescheduled_from'  => (int) ($r['rescheduled_from'] ?? 0),
            'rescheduled_at'    => $r['rescheduled_at'] ?? null,
            'cancelled_by'      => (string) ($r['cancelled_by'] ?? ''),
            'refund_state'      => (string) ($r['refund_state'] ?? ''),
            /* D5-b — للطالب على حصة مدفوعة: refund · no_refund · '' */
            'cancel_refund'     => $refund,
            'attendance'        => isset($r['attendance']) && $r['attendance'] !== '' ? (string) $r['attendance'] : null,
            'attended'          => !empty($r['attended_at']),
            'has_recording'     => trim((string) ($r['recording_url'] ?? '')) !== '' && $r['status'] === 'completed',
            'recording_pending' => (int) ($r['recording_pending'] ?? 0) === 1,
            'teacher_absent'    => !empty($r['teacher_absent_at']),
        );
    }

    private function admin_ids()
    {
        static $ids = null;
        if ($ids !== null) return $ids;
        try {
            $ids = array_map('intval', array_column($this->db->select('id')->where('role_id', 1)
                                ->get('users')->result_array(), 'id'));
        } catch (Throwable $e) { $this->db->reset_query(); $ids = array(); }
        return $ids;
    }

    /** اولياء امر الطالب بربط نشط — رسالة التاجيل والتسجيل تبلغهم. */
    private function tell_parents($student_id, $title, $text)
    {
        try {
            $rows = $this->db->query(
                'SELECT DISTINCT pl.`parent_user_id` id FROM `parent_links` pl
                  WHERE pl.`student_id` = ? AND pl.`status` = "active"', array((int) $student_id))->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return; }
        foreach ($rows as $r) $this->tell((int) $r['id'], $title, $text);
    }

    /** خطوة الكرون: تذكير التسجيل، واسقاط انتظاره، واعتماد الحضور. */
    private function followups_tick($now)
    {
        $n = array('rec_reminded' => 0, 'rec_dropped' => 0, 'attendance_set' => 0);
        try {
            $rows = $this->db->select('id, teacher_id, status, completed_at, recording_pending, recording_url,
                                       recording_reminded_at, attendance, attended_at, teacher_joined_at')
                             ->where('status', 'completed')
                             ->where('completed_at >=', date('Y-m-d H:i:s', $now - 9 * 86400))
                             ->limit(500)->get('tutoring_sessions')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return $n; }

        foreach ($rows as $r) {
            $step = self::recording_step($r, $now);
            if ($step === 'remind') {
                $this->db->where('id', (int) $r['id'])->where('recording_reminded_at IS NULL', null, false)
                         ->update('tutoring_sessions', array('recording_reminded_at' => date('Y-m-d H:i:s', $now)));
                if ($this->db->affected_rows() > 0) {
                    $n['rec_reminded']++;
                    $this->tell((int) $r['teacher_id'], 'تسجيل حصة ينتظرك',
                        'وعدت برفع تسجيل الحصة #' . (int) $r['id'] . '. أضف رابطه من شاشة الحصص — بعد سبعة أيام يقرأ الطالب «لا تسجيل».');
                }
            } elseif ($step === 'drop') {
                $this->db->where('id', (int) $r['id'])->where('recording_pending', 1)
                         ->update('tutoring_sessions', array('recording_pending' => 0));
                $n['rec_dropped'] += (int) $this->db->affected_rows();
            }
            $att = self::attendance_final($r, $now);
            if ($att !== null) {
                $this->db->where('id', (int) $r['id'])->where('attendance IS NULL', null, false)
                         ->update('tutoring_sessions', array('attendance' => $att));
                $n['attendance_set'] += (int) $this->db->affected_rows();
            }
        }
        return $n;
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
        /* TQ-FOUNDATION — والنوع يقرأ مع الصف: الرسالة تدل على الشاشة
           التي فيها زر الحجز الجديد، وشاشة لا يجد فيها صاحبها ما يبحث
           عنه تقرأ عطلا. */
        $rows = $this->db->select('t.id, t.slot_id, t.teacher_id, t.student_id, t.invoice_id,
                                   t.kind, t.track_id, a.starts_at')
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
            $sc = $this->scope_of($r);
            $this->tell((int) $r['student_id'], 'ألغي طلب حصتك',
                'مضت مهلة رد المعلم على طلبك، فألغي تلقائيا وعاد الموعد متاحا. '
                . 'لم يخصم منك شيء — اختر موعدا آخر من شاشة '
                . ($sc['is_foundation'] ? '«التأسيس»' : '«حصص بالطلب»') . '.');
            $this->tell((int) $r['teacher_id'], 'ألغي طلب حصة لم ترد عليه',
                'مضت مهلة الرد على طلب حصة، فألغي وعاد الموعد متاحا في جدولك.');
        }

        /* ٢ — تأكيد بلا دفع، او حجز فوري مضت مهلته (TQ-LIVE): الباب
           نفسه الذي تناديه شبكة المواعيد كسولا. */
        $out['expired_unpaid'] = $this->expire_holds($now);

        /* TQ-LIVE (D3/D4) — تذكير التسجيل واسقاطه، واعتماد الحضور. */
        $out = array_merge($out, $this->followups_tick($now));

        /* D2-b — حصة بلا رابط قبل ساعة: تنبيه واحد للمعلم والمسؤول. */
        $out['nolink_alerts'] = $this->alert_missing_links($now);

        /* D10 — غياب المعلم: الفحص الكسول عند القراءة، وهذا لمن لا يفتح شاشته. */
        try {
            $abs = $this->db->select('t.*, a.starts_at, a.duration_min')
                            ->from('tutoring_sessions t')
                            ->join('availability_slots a', 'a.id = t.slot_id', 'inner')
                            ->where_in('t.status', array('confirmed', 'live'))
                            ->where('t.teacher_absent_at IS NULL', null, false)
                            ->where('t.teacher_joined_at IS NULL', null, false)
                            ->where('a.starts_at <=', date('Y-m-d H:i:s', $now - 600))
                            ->limit(200)->get()->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); $abs = array(); }
        $out['absence_alerts'] = 0;
        foreach ($abs as $r) {
            if ($this->check_teacher_absence($r, $now) >= 1) $out['absence_alerts']++;
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
            /* معلم سجل غيابه ولم يدخل، او حصة قيد مراجعة: لا قيد آلي. */
            $c = self::may_auto_credit($r) ? $this->credit($r) : array('share' => 0);
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

    /**
     * طلبات مادة لكل مادة: كم معلما **فتح وقتا** فيها — لا كم معلما يدرسها.
     *
     * TQ-SUBJECT-COUNT — بطاقة «العلوم — ٥ معلم» كانت تعد من الكورسات
     * والمسارات، والقائمة تحتها تعرض من فتح موعدا وحده: فيضغطها الطالب فلا
     * يجد معلما واحدا. والعد هنا بقاعدة المرشح نفسها في `available_teachers()`
     * — مادة الموعد، وإلا مواد معلمه — فالرقم عدد ما سيراه بعد النقرة.
     */
    public function open_subject_counts($grade_id = 0, $limit = 5)
    {
        $this->install_schema();
        $teachers = $this->available_teachers(500, 500, 0, (int) $grade_id, self::KIND_CURRICULUM);
        $cats     = $this->teacher_subjects();

        $n = array();
        foreach ($teachers as $t) {
            $mine = array();
            foreach ($t['slots'] as $s) {
                if ((int) $s['subject_id'] > 0) {
                    $mine[(int) $s['subject_id']] = true;
                } else {
                    foreach (($cats['cats'][(int) $t['id']] ?? array()) as $sid) $mine[(int) $sid] = true;
                }
            }
            foreach (array_keys($mine) as $sid) $n[$sid] = ($n[$sid] ?? 0) + 1;
        }
        arsort($n);

        $out = array();
        foreach ($n as $sid => $count) {
            $name = $this->subject_name((int) $sid);
            if ($name === '') continue;
            $out[] = array('id' => (int) $sid, 'name' => $name, 'tutors' => (int) $count);
            if (count($out) >= (int) $limit) break;
        }
        return $out;
    }

    /**
     * حجوزات الطالب بمواعيدها ومعلميها وحالتها وثمنها.
     *
     * TQ-BOOKINGS-KIND — `$kind` يرشح **في الاستعلام**. كانت شاشة التأسيس
     * تجلب أحدث ثلاثين حجزا من كل الأنواع ثم ترشح التأسيس منها: فطالب له
     * عشرون حصة منهج لا يرى إلا بعض حجوزات تأسيسه، أو لا يرى منها شيئا.
     * والترتيب **القادم أولا بأقربه، ثم الماضي بأحدثه**: كان تصاعديا مطلقا
     * فتتصدر القائمة حصص انتهت منذ أشهر ويغيب تحتها الموعد القريب.
     */
    public function bookings_for_student($student_id, $limit = 20, $kind = null)
    {
        $this->install_schema();

        $student_id = (int) $student_id;
        if ($student_id <= 0) return [];

        $this->expire_holds();   // TQ-LIVE — الحجز المؤقت يفك كسولا

        $this->db->select('t.*, a.starts_at, a.duration_min, a.grade_id, a.subject_id,
                                   u.id AS tutor_id, u.first_name, u.last_name, u.image, u.tq_meet_url AS teacher_meet_url,
                                   i.invoice_no, i.total AS invoice_total, i.status AS invoice_status')
            ->from('tutoring_sessions t')
            ->join('availability_slots a', 'a.id = t.slot_id', 'left')
            ->join('users u', 'u.id = t.teacher_id', 'left')
            ->join('invoices i', 'i.id = t.invoice_id', 'left')
            ->where('t.student_id', $student_id);
        if ($kind !== null) $this->db->where('t.kind', $this->clean_kind($kind));
        $rows = $this->db
            ->order_by('CASE WHEN a.starts_at >= NOW() THEN 0 ELSE 1 END', '', false)
            ->order_by('CASE WHEN a.starts_at >= NOW() THEN a.starts_at END ASC, a.starts_at DESC, t.id DESC', '', false)
            ->limit((int) $limit)->get()->result_array();

        $subjects = $this->teacher_subjects();

        $out = [];
        foreach ($rows as $r) {
            $this->check_teacher_absence($r);   // TQ-LIVE (D10) — فحص كسول
            $name = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
            $j    = $this->join_state($r, null, 'student');
            $sc   = $this->scope_of($r);

            $out[] = [
                'id'            => (int) $r['id'],
                'status'        => $r['status'],
                'kind'          => $sc['kind'],
                'track_id'      => $sc['track_id'],
                'track_name'    => $sc['track_name'],
                'scope_text'    => $sc['label'],
                'tutor'         => $name !== '' ? $name : 'معلم',
                'tutor_id'      => (int) $r['tutor_id'],
                'image'         => (string) $r['image'],
                /* مادة **الموعد** أولا: هي التي فتح لها معلمه وقته، ومادة
                   المعلم الأولى تكذب على من يدرس مادتين. وحصة التأسيس
                   اسم مسارها — لا مادة لها أصلا. */
                'subject'       => $sc['is_foundation']
                                     ? $sc['track_name']
                                     : ($this->subject_name((int) ($r['subject_id'] ?? 0)) !== ''
                                        ? $this->subject_name((int) ($r['subject_id'] ?? 0))
                                        : ($subjects['name'][(int) $r['tutor_id']] ?? 'حصة خاصة')),
                'subject_id'    => (int) ($r['subject_id'] ?? 0),
                'starts_at'     => $r['starts_at'],
                'when_text'     => $r['starts_at'] ? $this->when_text($r['starts_at'], (int) $r['duration_min']) : 'بلا موعد',
                'grade_id'      => (int) ($r['grade_id'] ?? 0),
                'grade_name'    => $this->grade_name((int) ($r['grade_id'] ?? 0)),
                'minutes'       => (int) ($r['duration_min'] ?: $this->config()['minutes']),
                'meet_url'      => $this->meet_url_for($r),   // TQ-LIVE — والا رابط المعلم الثابت (D2-b)
                'price'         => (int) $r['price_halalas'],
                'invoice_id'    => (int) $r['invoice_id'],
                'invoice_no'    => (string) ($r['invoice_no'] ?? ''),
                'invoice_total' => (int) ($r['invoice_total'] ?? 0),
                'pay_deadline'  => $r['pay_deadline'],
                'cancel_reason' => (string) ($r['cancel_reason'] ?? ''),
                'needs_pay'     => $r['status'] === 'awaiting_payment',
                /* TQ-FND-PACK — ومدفوعة سلفا: لا فاتورة لها ولا زر دفع،
                   وشارتها تقول ذلك للطالب. */
                'pack_sub_id'   => (int) ($r['pack_sub_id'] ?? 0),
                'is_pack'       => (int) ($r['pack_sub_id'] ?? 0) > 0,
                'can_cancel'    => in_array($r['status'], array('requested', 'awaiting_payment'), true)
                                   || ((int) ($r['pack_sub_id'] ?? 0) > 0 && $r['status'] === 'confirmed'
                                       && !empty($r['starts_at'])
                                       && strtotime($r['starts_at']) - $this->config()['lead_min'] * 60 > time()),
                'can_join'      => $j['can_join'],
                /* TQ-LIVE — حالة العرض والباب والتقويم من مصدر واحد. */
                'state'         => $this->display_state($r, null, 'student'),
                'is_instant'    => $r['status'] === 'awaiting_payment' && empty($r['confirmed_at']),
                'opens_at'      => (int) $j['opens_at'],
                'starts_ts'     => (int) $j['starts_ts'],
                'gcal_url'      => $r['starts_at'] ? $this->google_calendar_url($r) : '',
                'is_over'       => $j['is_over'],
                'note'          => $j['note'],
            ] + $this->live_extra($r, 'student');
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
