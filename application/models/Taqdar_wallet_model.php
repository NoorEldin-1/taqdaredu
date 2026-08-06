<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * دفتر محفظة المعلم — القيد أولا، والرصيد أثر له لا مصدرا.
 *
 * ما قبل هذا الملف كانت الشاشة تشتق رصيد المعلم من جدول `payment` عند كل
 * عرض: تجمع `instructor_revenue` وتقسمها بحسب تاريخ البيع. وهذا يظهر رقما
 * صحيحا ما دام لا شيء يحدث؛ فإذا حدث استرداد أو تسوية أو سحب جزئي انهار،
 * لأن `payment` سجل مبيعات لا سجل نقود: لا يعرف ما خرج ولا ما رد ولا ما
 * جمد. ولا يعطي كشف حساب — يعطي قائمة مبيعات وحسب.
 *
 * القاعدة الحاكمة هنا: **لا يتغير رصيد إلا بقيد.** كل حركة (بيع، عمولة
 * منصة، ما تحتفظ به المنصة من ضريبة/تقريب، تحرر بعد نافذة الاسترداد،
 * استرداد، حجز سحب، تحويل، إلغاء) صف في `wallet_entries`. وأعمدة
 * `wallets` الثلاثة ليست حسابا مستقلا بل مجموع قيود دلوها حرفيا:
 *
 *     balance_available = SUM(amount) WHERE bucket='available'
 *     balance_pending   = SUM(amount) WHERE bucket='pending'
 *     balance_locked    = SUM(amount) WHERE bucket='locked'
 *
 * فلا يمكن أن «ينحرف» الرصيد عن الدفتر: هو مشتق منه بدالة واحدة
 * (`recompute`) لا كاتب لتلك الأعمدة سواها.
 *
 * **النقود هللات صحيحة (BIGINT) في كل هذا الملف.** جدول `payment` القديم
 * يخزن الريالات في `double`/`varchar` ولا يصلح — لكن عيبه لا يورث:
 * التحويل يقع مرة واحدة عند حد القراءة منه (`halalas()`)، وبعدها لا `float`
 * ولا `round()` في أي حساب.
 *
 * لماذا الدلاء الثلاثة لا عمود «حالة» في القيد:
 *   pending   — بيع داخل نافذة الاسترداد، مال للمعلم لكنه ليس له بعد.
 *   available — تحرر، يسحب.
 *   locked    — محجوز مقابل طلب سحب قائم، خرج من المتاح ولم يغادر بعد.
 * الانتقال بين دلوين قيدان لا تعديل: خصم في دلو وإضافة في آخر. فالقيد
 * لا يعدل ولا يحذف أبدا — يقابل بقيد عكسي. هذا ما يجعل الاسترداد
 * ممكنا أصلا: رصيد سالب بعد استرداد بيع سحب ثمنه حقيقة محاسبية
 * تعرض، لا خطأ يخفى.
 *
 * التعافي الذاتي: `sync()` تصالح الدفتر مع الواقع في كل قراءة —
 * مبيعات جديدة لم تقيد، قيود نضجت ولم تتحرر، مدفوعات اختفت (استرداد)،
 * وطلبات سحب أنشأتها أو حدثتها شاشات Academy القديمة (`Admin.php`،
 * `Crud_model::add_withdrawal_request`) بلا علم بالدفتر. فالدفتر لا يفترض
 * أنه الكاتب الوحيد لجدول `payout`، بل يلتقط ما كتبه غيره ويقيده.
 *
 * ---------------------------------------------------------------------
 * نقطة الاستدعاء لمن يبني `POST teacher/wallet/withdraw`:
 *
 *     $this->load->model('taqdar_wallet_model');
 *     $r = $this->taqdar_wallet_model->request_payout(
 *              $user_id, $amount_halalas, $channel, $destination);
 *     // $r = ['ok'=>bool,'code'=>string,'message'=>'عربي','payout_id'=>int]
 *
 * والقناة إحدى: bank|mada|stcpay|urpay. والمبلغ بالهللات صحيحا؛ ولمن
 * يقرأ ريالات من نموذج المستخدم: `sar_to_halalas($input)`.
 * ---------------------------------------------------------------------
 */
class Taqdar_wallet_model extends CI_Model
{
    /** الدلاء الثلاثة — كل عمود رصيد مجموع قيود دلوه. */
    const B_PENDING   = 'pending';
    const B_AVAILABLE = 'available';
    const B_LOCKED    = 'locked';

    /** إصدار بنية الدفتر — يمنع إعادة فحص الأعمدة في كل طلب. */
    const SCHEMA_V = '1';

    /**
     * قنوات التحويل السعودية الأربع.
     * ما لا يسجل لا يحول: لا نقبل طلب مال بقناة لا تحفظ مع الطلب.
     */
    public static $CHANNELS = array(
        'bank'   => array('label' => 'تحويل بنكي',     'hint' => 'رقم الآيبان (يبدأ بـ SA)'),
        'mada'   => array('label' => 'بطاقة مدى',       'hint' => 'رقم البطاقة'),
        'stcpay' => array('label' => 'محفظة STC Pay',   'hint' => 'رقم الجوال المرتبط'),
        'urpay'  => array('label' => 'محفظة urpay',     'hint' => 'رقم الجوال المرتبط'),
    );

    private static $settings_cache = array();
    private $schema_checked = false;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /* ================================================================
     *  أدوات
     * ================================================================ */

    /**
     * الوقت في هذا الملف لا يعتمد على المنطقة الافتراضية لـPHP.
     *
     * `Taqdar_cron` يضبط Asia/Riyadh قبل التشغيل، والطلب من المتصفح لا
     * يضبط شيئا فيبقى على UTC. فلو كتب `released_at` من سطر الأوامر
     * وقورن بـ«الآن» من الويب لاختلفا ثلاث ساعات — ويتحرر مال قبل أوانه
     * أو يتأخر. المنطقة تقرأ من الإعدادات وتفرض هنا كتابة وقراءة.
     */
    private function tz()
    {
        static $tz = null;
        if ($tz === null) {
            $name = (string) $this->setting('timezone', 'Asia/Riyadh');
            try {
                $tz = new DateTimeZone($name !== '' ? $name : 'Asia/Riyadh');
            } catch (Exception $e) {
                $tz = new DateTimeZone('Asia/Riyadh');
            }
        }
        return $tz;
    }

    /** طابع يونكس ⇐ نص MySQL بتوقيت المنصة. */
    public function at($unix_ts)
    {
        $d = new DateTime('@' . (int) $unix_ts);
        $d->setTimezone($this->tz());
        return $d->format('Y-m-d H:i:s');
    }

    /** نص MySQL بتوقيت المنصة ⇐ طابع يونكس. */
    public function ts($mysql_datetime)
    {
        if (!$mysql_datetime) return 0;
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $mysql_datetime, $this->tz());
        return $d ? (int) $d->format('U') : 0;
    }

    /** الوقت الآن بصيغة MySQL. */
    public function now()
    {
        return $this->at(time());
    }

    /**
     * الحد الوحيد الذي يسمح فيه بالعشري: القراءة من عمود ريالات قديم
     * أو من مدخل مستخدم. ما بعده هللات صحيحة فقط.
     */
    public function sar_to_halalas($riyals)
    {
        return (int) round(((float) $riyals) * 100);
    }

    /** إعداد من `settings` بذاكرة طلب واحدة — get_settings تستعلم كل مرة. */
    private function setting($key, $default = null)
    {
        if (!array_key_exists($key, self::$settings_cache)) {
            $row = $this->db->select('value')->where('key', $key)
                            ->get('settings')->row_array();
            self::$settings_cache[$key] = $row ? $row['value'] : null;
        }
        $v = self::$settings_cache[$key];
        return ($v === null || $v === '') ? $default : $v;
    }

    /** نافذة الاسترداد بالأيام — من الإعدادات لا من الشيفرة. */
    public function refund_window_days()
    {
        $d = (int) $this->setting('taqdar_refund_window_days', 14);
        return $d > 0 ? $d : 14;
    }

    /** الحد الأدنى للسحب بالهللات — من الإعدادات. */
    public function payout_min_halalas()
    {
        return max(0, $this->sar_to_halalas($this->setting('taqdar_payout_min_sar', 100)));
    }

    /**
     * مفتاح القيد. فريد على مستوى المنصة كلها، ومقيد بالمحفظة لأن البيع
     * الواحد في كورس متعدد المعلمين يقيد في محفظتين لا في واحدة.
     */
    private function ref_key($wallet_id, $origin, $kind)
    {
        return 'w' . (int) $wallet_id . ':' . $origin . ':' . $kind;
    }

    /* ================================================================
     *  البنية — إضافية ومتكررة الأمان
     * ================================================================ */

    /**
     * يكمل بنية الجداول الثلاثة إن نقصت. إضافات فقط: أعمدة اختيارية
     * وفهارس. لا يحذف عمودا ولا يغير نوعا، فما كتبته شاشات Academy
     * القديمة في `payout` يبقى مقروءا لها كما هو.
     */
    public function install_schema($force = false)
    {
        if ($this->schema_checked && !$force) return false;
        $this->schema_checked = true;

        if (!$force && (string) $this->setting('taqdar_wallet_ledger_v', '') === self::SCHEMA_V) {
            return false;
        }

        // CodeIgniter يخبئ أسماء الأعمدة لكل جدول في الطلب الواحد، فلو
        // فحصت البنية بعد تعديلها في النداء نفسه لقرأ قائمة بائتة وأعاد
        // الإضافة على عمود موجود.
        $this->db->data_cache = array();

        $add = array();
        if (!$this->db->field_exists('bucket', 'wallet_entries')) {
            $add[] = "ADD COLUMN `bucket` varchar(16) NOT NULL DEFAULT 'available'"
                   . " COMMENT 'pending|available|locked' AFTER `type`";
        }
        if (!$this->db->field_exists('origin', 'wallet_entries')) {
            $add[] = "ADD COLUMN `origin` varchar(64) DEFAULT NULL"
                   . " COMMENT 'المستند: payment:{id} أو payout:{id}' AFTER `ref`";
        }
        if (!$this->db->field_exists('subject', 'wallet_entries')) {
            $add[] = "ADD COLUMN `subject` varchar(190) DEFAULT NULL"
                   . " COMMENT 'وصف السطر لحظة القيد — لا يتغير بتغير الكورس' AFTER `origin`";
        }
        if (!$this->db->field_exists('occurred_at', 'wallet_entries')) {
            $add[] = "ADD COLUMN `occurred_at` datetime NOT NULL DEFAULT current_timestamp()"
                   . " COMMENT 'وقت الحدث لا وقت الكتابة' AFTER `released_at`";
        }
        if ($add) $this->db->query('ALTER TABLE `wallet_entries` ' . implode(', ', $add));

        $this->add_index('wallet_entries', 'uq_we_ref',    'ADD UNIQUE KEY `uq_we_ref` (`ref`)');
        $this->add_index('wallet_entries', 'idx_we_book',  'ADD KEY `idx_we_book` (`wallet_id`,`bucket`)');
        $this->add_index('wallet_entries', 'idx_we_orig',  'ADD KEY `idx_we_orig` (`wallet_id`,`origin`)');

        $add = array();
        if (!$this->db->field_exists('amount_halalas', 'payout')) {
            // المبلغ الملزم. `amount` القديم `double` بالريالات يبقى مرآة
            // لشاشات Academy التي تقارنه وتدفع به — ولا يحسب عليه هنا.
            $add[] = "ADD COLUMN `amount_halalas` bigint(20) NOT NULL DEFAULT 0"
                   . " COMMENT 'المبلغ الملزم بالهللات' AFTER `amount`";
        }
        if (!$this->db->field_exists('destination', 'payout')) {
            $add[] = "ADD COLUMN `destination` varchar(190) DEFAULT NULL"
                   . " COMMENT 'آيبان أو جوال المحفظة كما أدخله المعلم' AFTER `payment_type`";
        }
        if (!$this->db->field_exists('requested_channel', 'payout')) {
            // `payment_type` تكتبه الإدارة لاحقا بوسيلة تحويلها
            // (`Crud_model::update_payout_status`) فتمحو قناة المعلم.
            // القناة المطلوبة تحفظ هنا كذلك فلا تضيع.
            $add[] = "ADD COLUMN `requested_channel` varchar(32) DEFAULT NULL"
                   . " COMMENT 'قناة المعلم وقت الطلب' AFTER `destination`";
        }
        if ($add) $this->db->query('ALTER TABLE `payout` ' . implode(', ', $add));
        $this->add_index('payout', 'idx_payout_user', 'ADD KEY `idx_payout_user` (`user_id`,`status`)');

        $this->seed_setting('taqdar_refund_window_days', '14');
        $this->seed_setting('taqdar_payout_min_sar', '100');
        $this->seed_setting('taqdar_wallet_ledger_v', self::SCHEMA_V);
        self::$settings_cache = array();

        return true;
    }

    private function add_index($table, $name, $clause)
    {
        $q = $this->db->query(
            'SELECT COUNT(*) c FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            array($table, $name)
        )->row_array();
        if ((int) $q['c'] === 0) {
            $this->db->query('ALTER TABLE `' . $table . '` ' . $clause);
        }
    }

    private function seed_setting($key, $value)
    {
        $row = $this->db->select('id')->where('key', $key)->get('settings')->row_array();
        if ($row) {
            $this->db->where('id', (int) $row['id'])->update('settings', array('value' => $value));
        } else {
            $this->db->insert('settings', array('key' => $key, 'value' => $value));
        }
    }

    /* ================================================================
     *  المحفظة والنطاق
     * ================================================================ */

    /** محفظة المستخدم — تنشأ صفرية إن لم تكن. */
    public function wallet_of($user_id)
    {
        $user_id = (int) $user_id;
        $w = $this->db->where('owner_user_id', $user_id)->get('wallets')->row_array();
        if (!$w) {
            $this->db->insert('wallets', array(
                'owner_user_id'     => $user_id,
                'balance_available' => 0,
                'balance_pending'   => 0,
                'balance_locked'    => 0,
            ));
            $w = $this->db->where('owner_user_id', $user_id)->get('wallets')->row_array();
        }
        foreach (array('id', 'owner_user_id', 'balance_available', 'balance_pending', 'balance_locked') as $k) {
            $w[$k] = (int) $w[$k];
        }
        return $w;
    }

    /**
     * كورسات المعلم — بنفس نطاق الشاشة السابقة حرفا بحرف: ما أنشأه
     * (`creator`) وما أسند إليه في كورس متعدد المعلمين (`user_id` قائمة).
     * تغيير النطاق هنا يغير رصيدا قائما، وهذا ليس موضع تغييره.
     */
    public function teacher_course_ids($user_id)
    {
        $user_id = (int) $user_id;
        $rows = $this->db->query(
            'SELECT id FROM course WHERE creator = ? OR FIND_IN_SET(?, user_id) > 0',
            array($user_id, $user_id)
        )->result_array();
        return array_map('intval', array_column($rows, 'id'));
    }

    /* ================================================================
     *  القيد
     * ================================================================ */

    /**
     * يكتب قيدا واحدا. متكرر الأمان بمفتاحه (`ref` فريد): استدعاؤه مرتين
     * يكتب صفا واحدا — وعليه يقوم الترحيل والمصالحة معا.
     *
     * @return int 1 إن كتب، 0 إن كان مكتوبا
     */
    private function post($wallet_id, $type, $bucket, $amount, $ref, $origin = null,
                          $subject = null, $occurred_at = null, $released_at = null)
    {
        $amount = (int) $amount;
        $this->db->query(
            'INSERT IGNORE INTO `wallet_entries`
                (`wallet_id`,`type`,`bucket`,`amount`,`ref`,`origin`,`subject`,`released_at`,`occurred_at`)
             VALUES (?,?,?,?,?,?,?,?,?)',
            array((int) $wallet_id, $type, $bucket, $amount, $ref, $origin,
                  $subject !== null ? mb_substr($subject, 0, 190) : null,
                  $released_at, $occurred_at ?: $this->now())
        );
        return (int) $this->db->affected_rows();
    }

    /** مجموع قيود دلو — هذه هي الدالة التي «تنتج» الرصيد. */
    public function bucket_sum($wallet_id, $bucket)
    {
        $r = $this->db->query(
            'SELECT COALESCE(SUM(`amount`),0) s FROM `wallet_entries`
              WHERE `wallet_id` = ? AND `bucket` = ?',
            array((int) $wallet_id, $bucket)
        )->row_array();
        return (int) $r['s'];
    }

    /**
     * يشتق أعمدة `wallets` من الدفتر. الكاتب الوحيد لتلك الأعمدة.
     * لو حذف هذا السطر لبقي الدفتر صحيحا — الأعمدة مرآة لا مصدر.
     */
    public function recompute($wallet_id)
    {
        $wallet_id = (int) $wallet_id;
        $this->db->query(
            'UPDATE `wallets` w SET
                w.`balance_available` = (SELECT COALESCE(SUM(e.`amount`),0) FROM `wallet_entries` e
                                          WHERE e.`wallet_id` = w.`id` AND e.`bucket` = "available"),
                w.`balance_pending`   = (SELECT COALESCE(SUM(e.`amount`),0) FROM `wallet_entries` e
                                          WHERE e.`wallet_id` = w.`id` AND e.`bucket` = "pending"),
                w.`balance_locked`    = (SELECT COALESCE(SUM(e.`amount`),0) FROM `wallet_entries` e
                                          WHERE e.`wallet_id` = w.`id` AND e.`bucket` = "locked")
              WHERE w.`id` = ?',
            array($wallet_id)
        );
        return $this->db->where('id', $wallet_id)->get('wallets')->row_array();
    }

    /* ================================================================
     *  البيع: من `payment` إلى الدفتر
     * ================================================================ */

    /**
     * يقيد كل بيع لم يقيد بعد في كورسات هذا المعلم.
     *
     * ثلاثة قيود للبيعة الواحدة، لا سطر صاف واحد: المعلم يجب أن يتتبع
     * أي ريال إلى مصدره — كم دفع الطالب، وكم أخذت المنصة، وما بقي.
     *   sale       + مبلغ البيع كاملا
     *   commission − عمولة المنصة
     *   retained   − ما تبقى للمنصة (ضريبة/تقريب) إن وجد
     * ومجموعها بحكم البناء = `instructor_revenue` بالضبط، فلا ينحرف الدفتر
     * عن الرقم الذي كان المعلم يراه ولو كانت أعمدة `payment` غير متسقة.
     *
     * وكلها في دلو `pending` بتاريخ تحرر محسوب من نافذة الاسترداد
     * **السارية وقت البيع** — تغيير الإعداد غدا لا يحرك بيع أمس.
     */
    public function post_sales($user_id)
    {
        $wallet = $this->wallet_of($user_id);
        $wid    = $wallet['id'];
        $ids    = $this->teacher_course_ids($user_id);
        if (!$ids) return 0;

        $in   = implode(',', $ids);
        $days = $this->refund_window_days();

        $rows = $this->db->query(
            "SELECT p.`id`, p.`amount`, p.`admin_revenue`, p.`instructor_revenue`,
                    p.`instructor_payment_status`, p.`date_added`, c.`title`
               FROM `payment` p
               JOIN `course` c ON c.`id` = p.`course_id`
              WHERE p.`course_id` IN ($in)
                AND NOT EXISTS (SELECT 1 FROM `wallet_entries` e
                                 WHERE e.`ref` = CONCAT('w', ?, ':payment:', p.`id`, ':sale'))
              ORDER BY p.`id` ASC",
            array($wid)
        )->result_array();

        $n = 0;
        foreach ($rows as $r) {
            $pid      = (int) $r['id'];
            $origin   = 'payment:' . $pid;
            $ts       = (int) $r['date_added'] ?: time();
            $occurred = $this->at($ts);
            $release  = $this->at($ts + $days * 86400);
            $subject  = $r['title'];

            $gross    = $this->sar_to_halalas($r['amount']);
            $share    = $this->sar_to_halalas($r['instructor_revenue']);
            $comm     = $this->sar_to_halalas($r['admin_revenue']);
            $retained = $gross - $comm - $share;

            $n += $this->post($wid, 'sale', self::B_PENDING, $gross,
                              $this->ref_key($wid, $origin, 'sale'), $origin, $subject, $occurred, $release);
            if ($comm !== 0) {
                $n += $this->post($wid, 'commission', self::B_PENDING, -$comm,
                                  $this->ref_key($wid, $origin, 'commission'), $origin, $subject, $occurred, $release);
            }
            if ($retained !== 0) {
                $n += $this->post($wid, 'retained', self::B_PENDING, -$retained,
                                  $this->ref_key($wid, $origin, 'retained'), $origin, $subject, $occurred, $release);
            }

            // بيع سوي مع المعلم قبل الدفتر (`instructor_payment_status=1`):
            // يتحرر فورا ثم يخرج بقيد تحويل سابق، فيبقى ما يراه المعلم
            // في «حول إليك» كما كان، ولا يظهر في المتاح ولا في المعلق.
            if ((int) $r['instructor_payment_status'] === 1 && $share !== 0) {
                $now = $this->now();
                $n += $this->post($wid, 'release_out', self::B_PENDING, -$share,
                                  $this->ref_key($wid, $origin, 'release:out'), $origin, $subject, $now);
                $n += $this->post($wid, 'release_in', self::B_AVAILABLE, $share,
                                  $this->ref_key($wid, $origin, 'release:in'), $origin, $subject, $now);
                $n += $this->post($wid, 'legacy_payout', self::B_AVAILABLE, -$share,
                                  $this->ref_key($wid, $origin, 'legacy_payout'), $origin, $subject, $now);
            }
        }
        return $n;
    }

    /**
     * يحرر ما نضج: قيدان لا تعديل — خصم من `pending` وإضافة إلى `available`.
     * يشتق المبلغ من صافي الدلو للمستند لا من صف البيع وحده، فلو استرد
     * جزء قبل التحرر تحرر الباقي فقط.
     */
    public function release_matured($wallet_id)
    {
        $wallet_id = (int) $wallet_id;
        $now = $this->now();

        $rows = $this->db->query(
            'SELECT `origin`, SUM(`amount`) net, MIN(`released_at`) rel, MAX(`subject`) subject
               FROM `wallet_entries`
              WHERE `wallet_id` = ? AND `bucket` = "pending" AND `origin` IS NOT NULL
              GROUP BY `origin`
             HAVING net <> 0 AND rel IS NOT NULL AND rel <= ?',
            array($wallet_id, $now)
        )->result_array();

        $n = 0;
        foreach ($rows as $r) {
            $net = (int) $r['net'];
            $n += $this->post($wallet_id, 'release_out', self::B_PENDING, -$net,
                              $this->ref_key($wallet_id, $r['origin'], 'release:out'),
                              $r['origin'], $r['subject'], $r['rel']);
            $n += $this->post($wallet_id, 'release_in', self::B_AVAILABLE, $net,
                              $this->ref_key($wallet_id, $r['origin'], 'release:in'),
                              $r['origin'], $r['subject'], $r['rel']);
        }
        return $n;
    }

    /* ================================================================
     *  الاسترداد
     * ================================================================ */

    /**
     * يعكس بيعا. لا يحذف قيدا ولا يعدله: يقيد عكسه في الدلو الذي
     * يقف فيه المال الآن. فإن كان قد تحرر وسحب صار المتاح سالبا —
     * وهذا هو الصواب: المنصة تطالب المعلم بما قبضه عن بيع رد،
     * ولا يخفى الرقم بجعله صفرا.
     */
    public function record_refund($payment_id, $reason = '')
    {
        $origin = 'payment:' . (int) $payment_id;
        $wallets = $this->db->query(
            'SELECT DISTINCT `wallet_id` FROM `wallet_entries` WHERE `origin` = ?',
            array($origin)
        )->result_array();

        $n = 0;
        foreach ($wallets as $w) {
            $n += $this->reverse_origin((int) $w['wallet_id'], $origin, $reason);
        }
        return $n;
    }

    private function reverse_origin($wallet_id, $origin, $reason = '')
    {
        $has = $this->db->query(
            'SELECT COUNT(*) c FROM `wallet_entries`
              WHERE `wallet_id` = ? AND `origin` = ? AND `type` = "refund"',
            array($wallet_id, $origin)
        )->row_array();
        if ((int) $has['c'] > 0) return 0;

        $rows = $this->db->query(
            'SELECT `bucket`, SUM(`amount`) net, MAX(`subject`) subject
               FROM `wallet_entries` WHERE `wallet_id` = ? AND `origin` = ?
              GROUP BY `bucket` HAVING net <> 0',
            array($wallet_id, $origin)
        )->result_array();

        $n = 0;
        $now = $this->now();
        foreach ($rows as $r) {
            $subject = $r['subject'];
            if ($reason !== '') $subject = $subject . ' — ' . $reason;
            $n += $this->post($wallet_id, 'refund', $r['bucket'], -(int) $r['net'],
                              $this->ref_key($wallet_id, $origin, 'refund:' . $r['bucket']),
                              $origin, $subject, $now);
        }
        return $n;
    }

    /**
     * مبيعات مقيدة اختفى صفها من `payment` — إلغاء تسجيل أو استرداد
     * نفذته الإدارة بحذف الصف. الدفتر لا يصدق الغياب صمتا: يقيد عكسه.
     */
    public function reconcile_refunds($wallet_id)
    {
        $wallet_id = (int) $wallet_id;
        $rows = $this->db->query(
            'SELECT DISTINCT e.`origin` FROM `wallet_entries` e
              WHERE e.`wallet_id` = ? AND e.`origin` LIKE "payment:%"
                AND NOT EXISTS (SELECT 1 FROM `payment` p
                                 WHERE CONCAT("payment:", p.`id`) = e.`origin`)
                AND NOT EXISTS (SELECT 1 FROM `wallet_entries` r
                                 WHERE r.`wallet_id` = e.`wallet_id`
                                   AND r.`origin` = e.`origin` AND r.`type` = "refund")',
            array($wallet_id)
        )->result_array();

        $n = 0;
        foreach ($rows as $r) {
            $n += $this->reverse_origin($wallet_id, $r['origin'], 'استرداد');
        }
        return $n;
    }

    /* ================================================================
     *  السحب
     * ================================================================ */

    /** رصيد متاح مقروء من الدفتر لا من عمود الرصيد. */
    public function available_of($user_id)
    {
        $w = $this->wallet_of($user_id);
        return $this->bucket_sum($w['id'], self::B_AVAILABLE);
    }

    /**
     * طلب سحب. يكتب صفا في `payout` بقناته وبياناته، ويحجز المبلغ في
     * الدفتر (خروج من `available` ودخول إلى `locked`) في معاملة واحدة —
     * فإما صف وقيود معا أو لا شيء. لا صف سحب بلا حجز، ولا حجز بلا صف.
     */
    public function request_payout($user_id, $amount_halalas, $channel, $destination)
    {
        $this->install_schema();
        $user_id = (int) $user_id;
        $amount  = (int) $amount_halalas;
        $channel = trim((string) $channel);
        $dest    = trim((string) $destination);

        if (!isset(self::$CHANNELS[$channel])) {
            return $this->fail('CHANNEL', 'اختر قناة تحويل معتمدة: تحويل بنكي أو مدى أو STC Pay أو urpay.');
        }
        if ($dest === '') {
            return $this->fail('DESTINATION', 'أدخل بيانات التحويل — لا نرسل طلب مال بقناة بلا وجهة.');
        }
        if ($channel === 'bank' && !preg_match('/^SA[0-9]{22}$/i', preg_replace('/\s+/', '', $dest))) {
            return $this->fail('IBAN', 'الآيبان السعودي يبدأ بـ SA ويتكون من 24 خانة.');
        }
        if ($amount <= 0) {
            return $this->fail('AMOUNT', 'أدخل مبلغا أكبر من صفر.');
        }

        $min = $this->payout_min_halalas();
        if ($amount < $min) {
            return $this->fail('MIN_AMOUNT',
                'الحد الأدنى للسحب ' . $this->sar($min) . ' ريال، والمطلوب ' . $this->sar($amount) . ' ريال.');
        }

        // المتاح يعاد حسابه من الدفتر داخل المعاملة، لا من عمود قد يكون بائتا.
        $this->sync($user_id);
        $wallet = $this->wallet_of($user_id);
        $wid    = $wallet['id'];

        $this->db->trans_begin();
        $this->db->query('SELECT `id` FROM `wallets` WHERE `id` = ? FOR UPDATE', array($wid));
        $available = $this->bucket_sum($wid, self::B_AVAILABLE);

        if ($amount > $available) {
            $this->db->trans_rollback();
            return $this->fail('INSUFFICIENT',
                'المتاح للسحب ' . $this->sar($available) . ' ريال، والمطلوب ' . $this->sar($amount) . ' ريال.',
                array('available_halalas' => $available));
        }

        $now = time();
        $this->db->insert('payout', array(
            'user_id'           => $user_id,
            'payment_type'      => $channel,   // القناة تكتب مع الطلب لا بعده
            'destination'       => mb_substr($dest, 0, 190),
            'requested_channel' => $channel,
            'amount'            => $amount / 100, // مرآة الريالات لشاشات Academy
            'amount_halalas'    => $amount,
            'date_added'        => $now,
            'last_modified'     => $now,
            'status'            => 0,
        ));
        $payout_id = (int) $this->db->insert_id();
        if (!$payout_id) {
            $this->db->trans_rollback();
            return $this->fail('INTERNAL', 'تعذر تسجيل طلب السحب، حاول مرة أخرى.');
        }

        $origin  = 'payout:' . $payout_id;
        $subject = 'طلب سحب — ' . self::$CHANNELS[$channel]['label'];
        $this->post($wid, 'payout_hold', self::B_AVAILABLE, -$amount,
                    $this->ref_key($wid, $origin, 'hold'), $origin, $subject);
        $this->post($wid, 'payout_lock', self::B_LOCKED, $amount,
                    $this->ref_key($wid, $origin, 'lock'), $origin, $subject);

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return $this->fail('INTERNAL', 'تعذر تسجيل طلب السحب، حاول مرة أخرى.');
        }
        $this->db->trans_commit();
        $this->recompute($wid);

        return array(
            'ok' => true, 'code' => 'OK', 'payout_id' => $payout_id,
            'amount_halalas' => $amount, 'channel' => $channel,
            'message' => 'استلمنا طلب سحب ' . $this->sar($amount) . ' ريال عبر '
                       . self::$CHANNELS[$channel]['label'] . '، وحجز المبلغ من رصيدك المتاح.',
        );
    }

    /**
     * محول لعقد `Taqdar::delegate()` — يمرر المتحكم (المعرف، حمولة).
     *
     * المتحكم يوقع كل كتابة بالتوقيع نفسه: `f($user_id, array $payload)`
     * ويعيد `['ok'=>..,'message'=>..]`. وهذا هو ذلك الباب إلى
     * `request_payout` بلا أن يعرف المتحكم شيئا عن الدلاء ولا الهللات.
     * الاسم مطابق لما يبحث عنه (`request_withdrawal`) فيكفي أن يضاف
     * `taqdar_wallet_model` إلى قائمة مرشحيه.
     */
    public function request_withdrawal($user_id, $payload = array())
    {
        $halalas = isset($payload['amount_halalas']) ? (int) $payload['amount_halalas'] : 0;
        if ($halalas <= 0 && isset($payload['amount_sar'])) {
            $halalas = $this->sar_to_halalas($payload['amount_sar']);
        }
        $channel = isset($payload['payment_type']) ? $payload['payment_type']
                 : (isset($payload['channel']) ? $payload['channel'] : '');
        $dest    = isset($payload['destination']) ? $payload['destination'] : '';

        $r = $this->request_payout($user_id, $halalas, $channel, $dest);
        if (empty($r['ok'])) {
            $r['errors'] = array($r['message']);
        }
        return $r;
    }

    /** الاسم الثاني الذي يبحث عنه المتحكم — البابان إلى الدالة نفسها. */
    public function wallet_withdraw($user_id, $payload = array())
    {
        return $this->request_withdrawal($user_id, $payload);
    }

    private function fail($code, $message, $extra = array())
    {
        return array_merge(array('ok' => false, 'code' => $code, 'message' => $message, 'payout_id' => 0), $extra);
    }

    /** عرض الهللات ريالات في نص رسالة — حد عرض لا حساب. */
    private function sar($halalas)
    {
        return number_format(((int) $halalas) / 100, 2, '.', ',');
    }

    /**
     * يصالح الدفتر مع جدول `payout` أيا كان من كتبه: هذا النموذج، أو
     * متحكم آخر، أو شاشة Academy القديمة. لكل صف سحب حجزه، ولكل صف
     * حول خروجه، ولكل حجز اختفى صفه رجوعه إلى المتاح.
     */
    public function reconcile_payouts($user_id)
    {
        $user_id = (int) $user_id;
        $wallet  = $this->wallet_of($user_id);
        $wid     = $wallet['id'];
        $n       = 0;

        $rows = $this->db->query(
            'SELECT `id`,`amount`,`amount_halalas`,`status`,`payment_type`,`requested_channel`,`date_added`
               FROM `payout` WHERE `user_id` = ? ORDER BY `id` ASC',
            array($user_id)
        )->result_array();

        foreach ($rows as $p) {
            $pid    = (int) $p['id'];
            $origin = 'payout:' . $pid;
            $amount = (int) $p['amount_halalas'];
            if ($amount <= 0) $amount = $this->sar_to_halalas($p['amount']);
            if ($amount <= 0) continue;

            $ch      = $p['requested_channel'] ?: $p['payment_type'];
            $label   = isset(self::$CHANNELS[$ch]) ? self::$CHANNELS[$ch]['label'] : 'قناة تحدد مع الإدارة';
            $subject = 'طلب سحب — ' . $label;
            $when    = $p['date_added'] ? $this->at((int) $p['date_added']) : $this->now();

            $n += $this->post($wid, 'payout_hold', self::B_AVAILABLE, -$amount,
                              $this->ref_key($wid, $origin, 'hold'), $origin, $subject, $when);
            $n += $this->post($wid, 'payout_lock', self::B_LOCKED, $amount,
                              $this->ref_key($wid, $origin, 'lock'), $origin, $subject, $when);

            if ((int) $p['status'] === 1) {
                $n += $this->post($wid, 'payout_paid', self::B_LOCKED, -$amount,
                                  $this->ref_key($wid, $origin, 'paid'), $origin, $subject);
            }
        }

        // حجز بقي بلا صف سحب — الطلب حذف (`delete_withdrawal_request`).
        $orphans = $this->db->query(
            'SELECT e.`origin`, SUM(e.`amount`) net, MAX(e.`subject`) subject
               FROM `wallet_entries` e
              WHERE e.`wallet_id` = ? AND e.`bucket` = "locked" AND e.`origin` LIKE "payout:%"
                AND NOT EXISTS (SELECT 1 FROM `payout` p
                                 WHERE CONCAT("payout:", p.`id`) = e.`origin`)
              GROUP BY e.`origin` HAVING net <> 0',
            array($wid)
        )->result_array();

        foreach ($orphans as $o) {
            $net = (int) $o['net'];
            $n += $this->post($wid, 'payout_cancel_out', self::B_LOCKED, -$net,
                              $this->ref_key($wid, $o['origin'], 'cancel:out'), $o['origin'], $o['subject']);
            $n += $this->post($wid, 'payout_cancel_in', self::B_AVAILABLE, $net,
                              $this->ref_key($wid, $o['origin'], 'cancel:in'), $o['origin'], $o['subject']);
        }
        return $n;
    }

    /** الإدارة حولت فعلا: المال يغادر الدفتر من دلو الحجز. */
    public function mark_payout_paid($payout_id, $payment_type = null)
    {
        $p = $this->db->where('id', (int) $payout_id)->get('payout')->row_array();
        if (!$p) return false;

        $updater = array('status' => 1, 'last_modified' => time());
        if ($payment_type !== null) $updater['payment_type'] = $payment_type;
        $this->db->where('id', (int) $payout_id)->update('payout', $updater);

        $this->reconcile_payouts((int) $p['user_id']);
        $w = $this->wallet_of((int) $p['user_id']);
        $this->recompute($w['id']);
        return true;
    }

    /** طلب رفض أو ألغي: المحجوز يعود إلى المتاح بقيدين. */
    public function cancel_payout($payout_id)
    {
        $p = $this->db->where('id', (int) $payout_id)->get('payout')->row_array();
        if (!$p || (int) $p['status'] === 1) return false;

        $user_id = (int) $p['user_id'];
        $wallet  = $this->wallet_of($user_id);
        $origin  = 'payout:' . (int) $payout_id;
        $net     = (int) $this->db->query(
            'SELECT COALESCE(SUM(`amount`),0) s FROM `wallet_entries`
              WHERE `wallet_id` = ? AND `origin` = ? AND `bucket` = "locked"',
            array($wallet['id'], $origin)
        )->row('s');

        if ($net !== 0) {
            $this->post($wallet['id'], 'payout_cancel_out', self::B_LOCKED, -$net,
                        $this->ref_key($wallet['id'], $origin, 'cancel:out'), $origin, 'إلغاء طلب سحب');
            $this->post($wallet['id'], 'payout_cancel_in', self::B_AVAILABLE, $net,
                        $this->ref_key($wallet['id'], $origin, 'cancel:in'), $origin, 'إلغاء طلب سحب');
        }
        $this->db->where('id', (int) $payout_id)->update('payout', array('status' => 2, 'last_modified' => time()));
        $this->recompute($wallet['id']);
        return true;
    }

    /* ================================================================
     *  المصالحة الشاملة والترحيل
     * ================================================================ */

    /** كل ما يجعل الدفتر مطابقا للواقع، في نداء واحد متكرر الأمان. */
    public function sync($user_id)
    {
        $this->install_schema();
        $wallet = $this->wallet_of($user_id);
        $wid    = $wallet['id'];

        $n  = $this->post_sales($user_id);
        $n += $this->reconcile_refunds($wid);
        $n += $this->release_matured($wid);
        $n += $this->reconcile_payouts($user_id);
        $this->recompute($wid);
        return $n;
    }

    /**
     * الترحيل: يبني الدفتر من `payment` القائم مرة واحدة لكل من يملك
     * كورسا. متكرر الأمان — إعادة تشغيله لا تضاعف قيدا لأن كل قيد
     * بمفتاحه الفريد. وهو نفسه المسار الذي تسلكه المبيعات الجديدة
     * (`sync`)، فلا منطق ترحيل منفصل ينحرف عن منطق التشغيل.
     */
    public function migrate_all($echo = false)
    {
        $this->install_schema(true);

        $owners = $this->db->query(
            'SELECT DISTINCT u.`id`
               FROM `users` u
              WHERE u.`is_instructor` = 1
                 OR u.`id` IN (SELECT DISTINCT `creator` FROM `course` WHERE `creator` IS NOT NULL)
              ORDER BY u.`id` ASC'
        )->result_array();

        $report = array('users' => 0, 'entries' => 0, 'skipped' => 0, 'wallets' => array());
        foreach ($owners as $o) {
            $uid = (int) $o['id'];

            // لا نفتح محفظة لمن لا مال له: صف صفري لكل من رفعت عنه راية
            // «معلم» يوما ما ضجيج في جدول نقود، لا سجل.
            if (!$this->teacher_course_ids($uid)
                && !$this->db->where('user_id', $uid)->count_all_results('payout')) {
                $report['skipped']++;
                continue;
            }

            $n   = $this->sync($uid);
            $w   = $this->wallet_of($uid);
            $report['users']++;
            $report['entries'] += $n;
            $report['wallets'][$uid] = array(
                'wallet_id' => $w['id'],
                'available' => $w['balance_available'],
                'pending'   => $w['balance_pending'],
                'locked'    => $w['balance_locked'],
            );
            if ($echo) {
                echo 'user=' . $uid . ' wallet=' . $w['id'] . ' entries+=' . $n
                   . ' available=' . $w['balance_available']
                   . ' pending=' . $w['balance_pending']
                   . ' locked=' . $w['balance_locked'] . "\n";
            }
        }
        return $report;
    }

    /**
     * فحص السلامة: الرصيد المعروض = مجموع القيود، لكل محفظة.
     * @return array المحافظ المنحرفة — فارغة يعني لا انحراف.
     */
    public function audit_balances()
    {
        return $this->db->query(
            'SELECT * FROM (
                SELECT w.`id`, w.`owner_user_id`,
                       w.`balance_available`, w.`balance_pending`, w.`balance_locked`,
                       COALESCE(a.s,0) sum_available, COALESCE(p.s,0) sum_pending, COALESCE(l.s,0) sum_locked
                  FROM `wallets` w
                  LEFT JOIN (SELECT `wallet_id`, SUM(`amount`) s FROM `wallet_entries`
                              WHERE `bucket` = "available" GROUP BY `wallet_id`) a ON a.`wallet_id` = w.`id`
                  LEFT JOIN (SELECT `wallet_id`, SUM(`amount`) s FROM `wallet_entries`
                              WHERE `bucket` = "pending"   GROUP BY `wallet_id`) p ON p.`wallet_id` = w.`id`
                  LEFT JOIN (SELECT `wallet_id`, SUM(`amount`) s FROM `wallet_entries`
                              WHERE `bucket` = "locked"    GROUP BY `wallet_id`) l ON l.`wallet_id` = w.`id`
             ) t
             WHERE t.`balance_available` <> t.sum_available
                OR t.`balance_pending`   <> t.sum_pending
                OR t.`balance_locked`    <> t.sum_locked'
        )->result_array();
    }

    /* ================================================================
     *  ما تعرضه الشاشة
     * ================================================================ */

    /**
     * كشف الحساب من الدفتر: سطر لكل مستند بيع، فيه مبلغه وعمولته وحصة
     * المعلم منه وحالته. الوصف مأخوذ من القيد لا من `course`، فبيع كورس
     * حذف أو غير عنوانه يبقى مقروءا في كشفه كما كان يوم وقع.
     */
    public function statement($wallet_id, $limit = 100)
    {
        $wallet_id = (int) $wallet_id;
        $limit     = (int) $limit;

        $sales = $this->db->query(
            'SELECT `origin`, `subject`, `amount`, `occurred_at`, `released_at`
               FROM `wallet_entries`
              WHERE `wallet_id` = ? AND `type` = "sale"
              ORDER BY `occurred_at` DESC, `id` DESC
              LIMIT ' . $limit,
            array($wallet_id)
        )->result_array();
        if (!$sales) return array();

        $origins = array_column($sales, 'origin');
        $in      = implode(',', array_fill(0, count($origins), '?'));
        $lines   = $this->db->query(
            'SELECT `origin`, `type`, `bucket`, SUM(`amount`) s
               FROM `wallet_entries`
              WHERE `wallet_id` = ? AND `origin` IN (' . $in . ')
              GROUP BY `origin`, `type`, `bucket`',
            array_merge(array($wallet_id), $origins)
        )->result_array();

        $by = array();
        foreach ($lines as $l) {
            $by[$l['origin']][$l['type']] = (int) $l['s'];
        }

        $now = time();
        $out = array();
        foreach ($sales as $s) {
            $o = $s['origin'];
            $k = isset($by[$o]) ? $by[$o] : array();

            $gross      = (int) $s['amount'];
            $commission = isset($k['commission']) ? -$k['commission'] : 0;
            $retained   = isset($k['retained'])   ? -$k['retained']   : 0;
            $share      = $gross - $commission - $retained;

            if (isset($k['refund'])) {
                $state = 'refunded';
            } elseif (isset($k['legacy_payout'])) {
                $state = 'paid';
            } elseif (isset($k['release_in'])) {
                $state = 'available';
            } else {
                $state = 'pending';
            }

            $rel  = $this->ts($s['released_at']);
            $left = $rel ? (int) ceil(($rel - $now) / 86400) : 0;

            $out[] = array(
                'origin'      => $o,
                'occurred_at' => $s['occurred_at'],
                'date'        => substr((string) $s['occurred_at'], 0, 10),
                'subject'     => $s['subject'],
                'gross'       => $gross,
                'commission'  => $commission,
                'retained'    => $retained,
                'share'       => $share,
                'state'       => $state,
                'released_at' => $s['released_at'],
                'days_left'   => max(0, $left),
            );
        }
        return $out;
    }

    /** طلبات السحب — مقيدة بمعرف صاحبها، والوجهة مقنعة إلا آخرها. */
    public function payouts_of($user_id, $limit = 20)
    {
        $rows = $this->db->query(
            'SELECT `id`,`amount`,`amount_halalas`,`payment_type`,`requested_channel`,
                    `destination`,`date_added`,`status`
               FROM `payout` WHERE `user_id` = ?
              ORDER BY `date_added` DESC, `id` DESC LIMIT ' . (int) $limit,
            array((int) $user_id)
        )->result_array();

        foreach ($rows as &$r) {
            $h = (int) $r['amount_halalas'];
            if ($h <= 0) $h = $this->sar_to_halalas($r['amount']);
            $r['amount_halalas'] = $h;
            $r['channel']        = $r['requested_channel'] ?: $r['payment_type'];
            $r['destination_masked'] = $this->mask($r['destination']);
            $r['date']           = substr($this->at((int) $r['date_added']), 0, 10);
        }
        unset($r);
        return $rows;
    }

    private function mask($value)
    {
        $v = preg_replace('/\s+/', '', (string) $value);
        if ($v === '') return '';
        if (mb_strlen($v) <= 4) return $v;
        return '••••' . mb_substr($v, -4);
    }

    /**
     * كل ما تحتاجه شاشة المحفظة، من الدفتر وحده. تصالح أولا ثم تقرأ،
     * فما يراه المعلم هو حالة الدفتر بعد المصالحة لا قبلها.
     */
    public function screen($user_id)
    {
        $this->sync($user_id);
        $wallet = $this->wallet_of($user_id);
        $wid    = $wallet['id'];

        $paid = (int) $this->db->query(
            'SELECT COALESCE(-SUM(`amount`),0) s FROM `wallet_entries`
              WHERE `wallet_id` = ? AND `type` IN ("legacy_payout","payout_paid")',
            array($wid)
        )->row('s');

        return array(
            'wallet_id'   => $wid,
            'available'   => (int) $wallet['balance_available'],
            'pending'     => (int) $wallet['balance_pending'],
            'locked'      => (int) $wallet['balance_locked'],
            'transferred' => $paid,
            'refund_days' => $this->refund_window_days(),
            'min_payout'  => $this->payout_min_halalas(),
            'channels'    => self::$CHANNELS,
            'statement'   => $this->statement($wid, 100),
            'payouts'     => $this->payouts_of($user_id, 20),
        );
    }


    /**
     * يقيد بيع مسار في محفظة معلمه.
     *
     * قيدان لا سطر صاف: `sale` بكامل المقبوض، ثم `commission` بما تأخذه
     * المنصة — فمجموعهما حصة المعلم **بحكم البناء**، ويبقى المقبوض
     * الكامل ظاهرا في كشفه بدل رقم صاف لا يراجع.
     *
     * الدلو `pending` حتى تمر نافذة الاسترداد، فتحرره `release_matured()`
     * كما تفعل ببقية المبيعات — لا آلية ثانية توازيها.
     *
     * متكرر الأمان: المفتاح `pathsub:<subscription_id>` فريد، فتفعيل
     * مكرر لا يضاعف المال.
     *
     * @return array ok · teacher_share · platform_cut · wallet_id
     */
    public function credit_path_sale($teacher_id, $path_id, $subscription_id, $gross_halalas, $share_percent = null)
    {
        $this->install_schema();

        $teacher_id = (int) $teacher_id;
        $gross      = (int) $gross_halalas;
        if ($teacher_id < 1 || $gross <= 0) {
            return array("ok" => false, "errors" => array("لا معلم للمسار أو لا مبلغ."));
        }

        if ($share_percent === null || $share_percent === "") {
            $share_percent = $this->setting("taqdar_teacher_share_default", 15);
        }
        $share_percent = max(0, min(100, (float) $share_percent));

        // التقريب مرة واحدة، والباقي للمنصة — فلا تضيع هللة ولا تخترع
        $teacher_share = (int) round($gross * $share_percent / 100);
        $platform_cut  = $gross - $teacher_share;

        $wallet = $this->wallet_of($teacher_id);
        $origin = "pathsub:" . (int) $subscription_id;
        $subject = "مسار #" . (int) $path_id;

        $this->post($wallet["id"], "sale", "pending", $gross,
                    $this->ref_key($wallet["id"], $origin, "sale"), $origin, $subject);

        if ($platform_cut > 0) {
            $this->post($wallet["id"], "commission", "pending", -$platform_cut,
                        $this->ref_key($wallet["id"], $origin, "commission"), $origin, $subject);
        }

        $this->recompute($wallet["id"]);

        return array(
            "ok"            => true,
            "wallet_id"     => $wallet["id"],
            "teacher_share" => $teacher_share,
            "platform_cut"  => $platform_cut,
            "percent"       => $share_percent,
        );
    }

}
