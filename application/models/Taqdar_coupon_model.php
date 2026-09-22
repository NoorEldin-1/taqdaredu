<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-COUPON — أكواد الخصم: التوليد والتحقق والحجز والتسوية والأثر.
 *
 * ═══ القاعدة الحاكمة: لا محرك ثان ═══
 *
 * الكود **لا يشتري شيئا**. هو معامل في الشراء القائم كالدورة
 * (TQ-CYCLE-BUY): وحدات البيع الخمس (`subscribe()` · `subscribe_path()` ·
 * `subscribe_course()` · `subscribe_book()` · `subscribe_foundation_pack()`)
 * تقرأ سعرها من مصدرها الواحد كما كانت، ثم تسأل هذا الملف «كم يخصم منه؟»
 * وتكتب في `subscriptions.price` **المحصل** لا المعروض. فكل ما بعد الصف
 * يتبعه بلا سطر: الفاتورة تصدر بالصافي، وتاب تحصل الصافي، وقسمة الإيراد
 * وقيد المعلم على ما دفع فعلا — فمجموع الأنصبة يساوي المقبوض بالهللة.
 *
 * ═══ ثلاث حالات للاستعمال لا اثنتان ═══
 *
 *   held · حجز وقت إصدار الفاتورة. بلاه يصدر لعشرة طلاب كود «لأول خمسين»
 *          بقي منه واحد، ويدفعون كلهم. والحجز **يسقط وحده بعد مهلة**
 *          (`HOLD_HOURS`) فلا يحبس فاتورة تركها صاحبها كودا عن غيره إلى
 *          الأبد — ولا يلغى بها ما صدر: من حول بعد المهلة يسوى بخصمه.
 *   paid · تسوية وقت التفعيل (`settle()` من `activate()` وحدها).
 *   void · فاتورة ألغيت أو حلت محلها أخرى للشيء نفسه.
 *
 * ═══ والفحص في الخادم وحده ═══
 *
 * نافذة «طبق» في شاشة الدفع **معاينة** لا حكم: الكود يعاد فحصه كاملا
 * عند التأكيد، والسعر يقرأ من مصدره لا من حقل في النموذج. ومن عدل
 * رقما في المتصفح لم يفعل شيئا.
 *
 * والمال هللات صحيحة في هذا الملف كله، والخصم يقرب **لصالح المنصة**
 * (`intdiv`): كسر هللة لا يعطى مرتين في الشاشة والفاتورة.
 */
class Taqdar_coupon_model extends CI_Model
{
    const SCHEMA_V   = '1';

    /** مهلة الحجز بالساعات — فوقها لا يحبس فاتورة معلقة كودا عن غيرها. */
    const HOLD_HOURS = 72;

    /** محاولات فاشلة لكل عنوان في ربع ساعة — ضد تخمين الأكواد. */
    const MAX_TRIES  = 25;

    /** حروف الأكواد المولدة — بلا ما يلتبس على العين (0/O · 1/I/L). */
    const ALPHABET   = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private static $schema_ready = false;

    /* =====================================================================
       البنية
       ===================================================================== */

    /**
     * الجدولان والأعمدة الثلاثة على `subscriptions` — وقت التشغيل كأخواتها.
     *
     * والاسم `ensure_schema` هو ما تناديه وحدات اللوحة (`'ensure'` في
     * `spec()`)، فلا اسم ثان لفعل واحد. وينادى **قبل كل قراءة** لا من مسار
     * الكتابة وحده: شاشة الدفع تسأل «أفي المنصة كود يعمل؟» في كل عرض،
     * وقراءة جدول قبل إنشائه ترد «Table doesn't exist» فتبيض صفحة الدفع.
     */
    public function ensure_schema($force = false)
    {
        if (self::$schema_ready && !$force) return false;
        self::$schema_ready = true;

        if (!$force && (string) $this->setting('tq_coupon_schema_v', '') === self::SCHEMA_V) {
            return false;
        }

        $this->try_sql("CREATE TABLE IF NOT EXISTS `tq_coupons` (
            `id`             int(10) unsigned NOT NULL AUTO_INCREMENT,
            `code`           varchar(40)  NOT NULL,
            `label`          varchar(190) NOT NULL DEFAULT '',
            `batch`          varchar(60)  NOT NULL DEFAULT '',
            `percent`        tinyint(3) unsigned NOT NULL DEFAULT 10,
            `on_plans`       tinyint(1) NOT NULL DEFAULT 1,
            `on_paths`       tinyint(1) NOT NULL DEFAULT 1,
            `on_courses`     tinyint(1) NOT NULL DEFAULT 1,
            `on_books`       tinyint(1) NOT NULL DEFAULT 1,
            `on_packs`       tinyint(1) NOT NULL DEFAULT 1,
            `plan_ids`       varchar(500) NOT NULL DEFAULT '',
            `course_ids`     varchar(500) NOT NULL DEFAULT '',
            `book_ids`       varchar(500) NOT NULL DEFAULT '',
            `min_amount`     bigint(20) DEFAULT NULL,
            `max_discount`   bigint(20) DEFAULT NULL,
            `max_uses`       int(10) DEFAULT NULL,
            `per_user`       int(10) NOT NULL DEFAULT 1,
            `first_purchase` tinyint(1) NOT NULL DEFAULT 0,
            `starts_at`      datetime DEFAULT NULL,
            `ends_at`        datetime DEFAULT NULL,
            `active`         tinyint(1) NOT NULL DEFAULT 1,
            `note`           text,
            `created_by`     int(10) NOT NULL DEFAULT 0,
            `created_at`     datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_coupon_code` (`code`),
            KEY `ix_coupon_batch` (`batch`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='أكواد الخصم — TQ-COUPON'");

        $this->try_sql("CREATE TABLE IF NOT EXISTS `tq_coupon_redemptions` (
            `id`              int(10) unsigned NOT NULL AUTO_INCREMENT,
            `coupon_id`       int(10) unsigned NOT NULL,
            `code`            varchar(40) NOT NULL DEFAULT '',
            `user_id`         int(10) NOT NULL,
            `subscription_id` int(10) NOT NULL DEFAULT 0,
            `invoice_id`      int(10) NOT NULL DEFAULT 0,
            `kind`            varchar(12) NOT NULL DEFAULT '',
            `item_id`         int(10) NOT NULL DEFAULT 0,
            `gross`           bigint(20) NOT NULL DEFAULT 0,
            `discount`        bigint(20) NOT NULL DEFAULT 0,
            `net`             bigint(20) NOT NULL DEFAULT 0,
            `status`          enum('held','paid','void') NOT NULL DEFAULT 'held',
            `created_at`      datetime DEFAULT NULL,
            `paid_at`         datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `ix_cr_coupon` (`coupon_id`, `status`),
            KEY `ix_cr_user` (`user_id`, `coupon_id`),
            KEY `ix_cr_sub` (`subscription_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='استعمالات أكواد الخصم — TQ-COUPON'");

        /* محاولات فاشلة لكل عنوان — ضد تخمين الأكواد. جدول لا جلسة:
           من يخمن بسكربت يرمي كعكته مع كل طلب. */
        $this->try_sql("CREATE TABLE IF NOT EXISTS `tq_coupon_tries` (
            `id`  int(10) unsigned NOT NULL AUTO_INCREMENT,
            `ip`  varchar(45) NOT NULL DEFAULT '',
            `at`  datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ix_ct_ip` (`ip`, `at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        /* ثلاثة أعمدة على الصف الذي بيع: ما كان سيدفع، وما خصم، وبأي
           كود. و`price` يبقى المحصل كما كان — فكل قارئ قائم له يقرأ ما
           دفع فعلا بلا سطر يعدل. */
        $this->db->data_cache = array();
        $add = array();
        if (!$this->field('coupon_id', 'subscriptions')) {
            $add[] = "ADD COLUMN `coupon_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'كود الخصم — TQ-COUPON'";
        }
        if (!$this->field('list_price', 'subscriptions')) {
            $add[] = "ADD COLUMN `list_price` bigint(20) NOT NULL DEFAULT 0 COMMENT 'السعر قبل الخصم — صفر بلا كود'";
        }
        if (!$this->field('discount', 'subscriptions')) {
            $add[] = "ADD COLUMN `discount` bigint(20) NOT NULL DEFAULT 0 COMMENT 'ما خصمه الكود بالهللات'";
        }
        if ($add) $this->try_sql('ALTER TABLE `subscriptions` ' . implode(', ', $add));

        $this->put_setting('tq_coupon_schema_v', self::SCHEMA_V);
        $this->db->data_cache = array();
        return true;
    }

    /* =====================================================================
       الأنواع — ما يقبل كودا من وحدات البيع
       ===================================================================== */

    /**
     * وحدات البيع التي يعمل عليها الكود، والعمود الذي يعلنها في الصف.
     *
     * والحصة المفردة ليست منها عمدا: ثمنها يجمد وقت الطلب ومعه نصيب
     * المعلم (`teacher_share_halalas`) قبل أن تصدر فاتورتها، والخصم بعدها
     * إما ينقص المعلم ما وعد به أو يدفعه من المنصة بلا قرار. وباقة حصص
     * التأسيس تحمل الكود — وهي الطريق الأرخص إلى الحصص أصلا.
     */
    public function kinds()
    {
        return tq_t_deep(array(
            'plan'   => array('col' => 'on_plans',   'label' => 'الباقات',            'noun' => 'الباقة'),
            'path'   => array('col' => 'on_paths',   'label' => 'المسارات',           'noun' => 'المسار'),
            'course' => array('col' => 'on_courses', 'label' => 'الكورسات المفردة',   'noun' => 'الكورس'),
            'book'   => array('col' => 'on_books',   'label' => 'الكتب',              'noun' => 'الكتاب'),
            'pack'   => array('col' => 'on_packs',   'label' => 'باقات حصص التأسيس',  'noun' => 'باقة الحصص'),
        ));
    }

    /** عمود القائمة التي تحصر الكود في عناصر بعينها — لثلاثة أنواع. */
    private static $ITEM_COL = array('plan' => 'plan_ids', 'course' => 'course_ids', 'book' => 'book_ids');

    /* =====================================================================
       القراءة
       ===================================================================== */

    /**
     * الكود كما يخزن: حروف كبيرة وأرقام وشرطة، بلا فراغ.
     *
     * والتسوية في الاتجاهين: من يكتب «ramadan 25» في الجوال يقصد
     * `RAMADAN25`، ومن ينسخه من واتساب ينسخ معه فراغا في آخره. ورد ما
     * قصده صاحبه بـ«لا كود بهذا الاسم» يجعله يظن الكود مزورا.
     * والأرقام العربية تحول: لوحة المفاتيح العربية تكتب «٢٥» لا «25».
     */
    public function normalize($code)
    {
        $c = strtr((string) $code, array(
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '–' => '-', '—' => '-', 'ـ' => '',
        ));
        $c = strtoupper(preg_replace('/\s+/u', '', $c));
        return preg_replace('/[^A-Z0-9\-]/', '', $c);
    }

    public function find($code)
    {
        $this->ensure_schema();
        $c = $this->normalize($code);
        if ($c === '') return null;
        try {
            $row = $this->db->where('code', $c)->get('tq_coupons')->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
        return $row ?: null;
    }

    public function get($id)
    {
        $this->ensure_schema();
        try {
            return $this->db->where('id', (int) $id)->get('tq_coupons')->row_array() ?: null;
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
    }

    /**
     * هل في المنصة كود واحد يعمل على هذا النوع الآن؟
     *
     * وبه وحده يظهر حقل «لديك كود خصم؟» في شاشة الدفع. **وحقل بلا كود
     * يعمل يضر لا ينفع**: من رآه يترك الشراء ويبحث عن كود في جوجل، ثم
     * يعود أو لا يعود. فبلا كود فعال لا يتغير في الشاشة شيء — وهي قاعدة
     * «بلا مفتاح لا شيء يتغير» نفسها. ومن جاء برابط فيه كود يرى الحقل
     * ولو كان الكود منتهيا، ليقرأ لماذا لا يعمل.
     */
    public function any_live($kind = '')
    {
        static $memo = array();
        if (isset($memo[$kind])) return $memo[$kind];

        $this->ensure_schema();
        $kinds = $this->kinds();
        $now   = date('Y-m-d H:i:s');
        try {
            $this->db->where('active', 1)
                     ->group_start()->where('starts_at IS NULL', null, false)->or_where('starts_at <=', $now)->group_end()
                     ->group_start()->where('ends_at IS NULL', null, false)->or_where('ends_at >=', $now)->group_end();
            if (isset($kinds[$kind])) $this->db->where($kinds[$kind]['col'], 1);
            $n = (int) $this->db->count_all_results('tq_coupons');
        } catch (Throwable $e) {
            $this->db->reset_query();
            $n = 0;
        }
        return $memo[$kind] = ($n > 0);
    }

    /* =====================================================================
       السعر — من مصدره الواحد لا من الشاشة
       ===================================================================== */

    /**
     * سعر العنصر قبل الخصم — **من الدالة نفسها التي يشتري بها المحرك**.
     *
     * نافذة «طبق» تحتاج الرقم قبل الشراء، ورقم تحسبه بنفسها مرة
     * وتحسبه `subscribe_*()` مرة يجعل الشاشة تعد بـ٣٢٠ والفاتورة تطلب
     * ٣٣٦. فالسعر هنا يقرأ من `cycle_of()` و`offer()` و`pack_offer()`
     * كما يقرؤه الشراء حرفا بحرف.
     *
     * @return array ok · gross (هللات) · title · why
     */
    public function gross_of($kind, $item_id, $cycle = '')
    {
        $item_id = (int) $item_id;
        $out = array('ok' => false, 'gross' => 0, 'title' => '', 'why' => t('العنصر غير معروض للبيع.'));
        if ($item_id <= 0) return $out;

        try {
            switch ($kind) {
                case 'plan':
                    $this->load->model('taqdar_billing_model');
                    $plan = $this->taqdar_billing_model->plan($item_id);
                    if (!$plan || (int) $plan['active'] !== 1) return $out;
                    $cy = $this->taqdar_billing_model->cycle_of($plan, $cycle);
                    return array('ok' => true, 'gross' => (int) $cy['price'],
                                 'title' => (string) $plan['name_ar'], 'why' => '');

                case 'path':
                    $this->load->model('taqdar_billing_model');
                    $p = $this->taqdar_billing_model->path($item_id);
                    if (!$p || (string) $p['status'] !== 'published') return $out;
                    return array('ok' => true, 'gross' => (int) $p['price'],
                                 'title' => (string) $p['title'], 'why' => '');

                case 'course':
                    $this->load->model('taqdar_course_sale_model', 'tq_cs');
                    $o = $this->tq_cs->offer($item_id);
                    if (empty($o['sellable'])) return $out;
                    return array('ok' => true, 'gross' => (int) $o['price'],
                                 'title' => (string) $o['title'], 'why' => '');

                case 'book':
                    $this->load->model('taqdar_book_model', 'tq_bk');
                    $o = $this->tq_bk->offer($item_id);
                    if (empty($o['sellable'])) return $out;
                    return array('ok' => true, 'gross' => (int) $o['price'],
                                 'title' => (string) $o['title'], 'why' => '');

                case 'pack':
                    $this->load->model('taqdar_foundation_model', 'tq_fnd');
                    $o = $this->tq_fnd->pack_offer($item_id);
                    if (empty($o['sellable'])) return $out;
                    return array('ok' => true, 'gross' => (int) $o['price'],
                                 'title' => (string) $o['name'], 'why' => '');
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-COUPON gross_of ' . $kind . '#' . $item_id . ': ' . $e->getMessage());
        }
        return $out;
    }

    /* =====================================================================
       الحكم — أيعمل هذا الكود على هذا الشراء؟
       ===================================================================== */

    /**
     * يحكم على كود لشراء بعينه، ويحسب الخصم.
     *
     * والرفض **يقول لماذا وما العمل**: «الكود غير صالح» وحدها تجعل من
     * كتب كودا صحيحا لسلعة أخرى يظن الكود مزورا. فلكل سبب مفتاح ثابت
     * (`reason`) يفرع عليه التطبيق، ونص عربي (`message`) يعرض كما هو.
     *
     * والترتيب ترتيب ما يستطيع صاحبه أن يفعله: ما لا حيلة فيه (لا كود،
     * موقوف، منته) قبل ما يتغير بسلعة أخرى (النوع، العنصر، الحد الأدنى)،
     * وحدود الاستعمال آخرا — فلا يقرأ «استنفد» عن كود لا ينطبق أصلا.
     *
     * @param int $user_id صفر للزائر: حدود صاحب الحساب تفحص عند الشراء
     * @return array ok · reason · message · coupon · code · percent · gross
     *               · discount · net · capped
     */
    public function quote($code, $user_id, $kind, $item_id, $gross)
    {
        $this->ensure_schema();

        $gross = max(0, (int) $gross);
        $kinds = $this->kinds();
        $no    = function ($reason, $msg, $row = null) use ($gross) {
            return array('ok' => false, 'reason' => $reason, 'message' => $msg,
                         'coupon' => $row, 'code' => $row ? (string) $row['code'] : '',
                         'percent' => 0, 'gross' => $gross, 'discount' => 0,
                         'net' => $gross, 'capped' => false);
        };

        $c = $this->normalize($code);
        if ($c === '') return $no('empty', t('اكتب كود الخصم أولا.'));

        if ($this->throttled()) {
            return $no('throttled', t('محاولات كثيرة في وقت قصير. انتظر ربع ساعة ثم أعد المحاولة.'));
        }

        $row = $this->find($c);
        if (!$row) {
            $this->note_try();
            return $no('not_found', t('لا كود خصم بهذا الاسم. تأكد من كتابته كما وصلك، حرفا حرفا.'));
        }

        if ((int) $row['active'] !== 1) {
            return $no('inactive', t('هذا الكود موقوف ولا يعمل الآن.'), $row);
        }

        $now = time();
        if (!empty($row['starts_at']) && strtotime($row['starts_at']) > $now) {
            return $no('not_started', t('هذا الكود يبدأ العمل في ____.', array(
                $this->day($row['starts_at']))), $row);
        }
        if (!empty($row['ends_at']) && strtotime($row['ends_at']) < $now) {
            return $no('expired', t('انتهت صلاحية هذا الكود في ____.', array(
                $this->day($row['ends_at']))), $row);
        }

        if (!isset($kinds[$kind]) || (int) $row[$kinds[$kind]['col']] !== 1) {
            $on = array();
            foreach ($kinds as $k => $d) if ((int) $row[$d['col']] === 1) $on[] = $d['label'];
            return $no('kind', $on
                ? t('هذا الكود لا ينطبق على هذا الشراء — يعمل على: ____.', array(implode(t('، '), $on)))
                : t('هذا الكود لا ينطبق على هذا الشراء.'), $row);
        }

        /* حصر في عناصر بعينها: قائمة فارغة تعني كل عناصر النوع. */
        if (isset(self::$ITEM_COL[$kind])) {
            $ids = $this->ids((string) $row[self::$ITEM_COL[$kind]]);
            if ($ids && !in_array((int) $item_id, $ids, true)) {
                return $no('item', t('هذا الكود لا ينطبق على ____ الذي اخترته، بل على عناصر محددة غيره.',
                    array($kinds[$kind]['noun'])), $row);
            }
        }

        if ($gross <= 0) {
            return $no('free', t('هذا الشراء بلا ثمن أصلا، فلا خصم عليه.'), $row);
        }

        if ($row['min_amount'] !== null && (int) $row['min_amount'] > 0 && $gross < (int) $row['min_amount']) {
            return $no('min', t('هذا الكود يعمل على المشتريات من ____ ر.س فأكثر.', array(
                $this->sar((int) $row['min_amount']))), $row);
        }

        /* حدود الاستعمال — والمحجوز يعد مع المسوى: كود «لأول خمسين» بقي
           منه واحد لا يصدر لعشرة فواتير معا. والحجز القديم لا يعد
           (`HOLD_HOURS`). ومحجوز هذا الطالب للعنصر نفسه لا يعد عليه ولا
           على غيره: هو الفاتورة نفسها يعود ليكملها، أو يحل محلها. */
        $used = $this->used_count((int) $row['id'], (int) $user_id, $kind, (int) $item_id);
        if ($row['max_uses'] !== null && (int) $row['max_uses'] > 0 && $used['all'] >= (int) $row['max_uses']) {
            return $no('exhausted', t('استنفد هذا الكود عدد مرات استعماله.'), $row);
        }

        if ((int) $user_id > 0) {
            $per = (int) $row['per_user'];
            if ($per > 0 && $used['mine'] >= $per) {
                return $no('per_user', $per === 1
                    ? t('استعملت هذا الكود من قبل — وهو يستعمل مرة واحدة لكل حساب.')
                    : t('بلغت حد استعمالك لهذا الكود (____ مرات).', array($per)), $row);
            }
            if ((int) $row['first_purchase'] === 1 && $this->has_bought((int) $user_id)) {
                return $no('first', t('هذا الكود لأول شراء على المنصة، ولحسابك مشتريات سابقة.'), $row);
            }
        }

        $pct      = max(1, min(100, (int) $row['percent']));
        $discount = intdiv($gross * $pct, 100);
        $capped   = false;
        if ($row['max_discount'] !== null && (int) $row['max_discount'] > 0 && $discount > (int) $row['max_discount']) {
            $discount = (int) $row['max_discount'];
            $capped   = true;
        }
        /* **والخصم بريال كامل متى كان السعر بريال كامل.** كل شاشة في
           المنصة تطبع الريال بلا كسر (`tqs_money()`)، وإشعار الفاتورة
           كذلك — فخصم ١٠٪ من ٣٩٩ يكتب ٣٥٩ في الشاشة ويطلب ٣٥٩٫١٠ في
           الفاتورة. والتقريب إلى أسفل: لصالح المنصة لا ضدها. */
        if ($gross % 100 === 0) $discount = intdiv($discount, 100) * 100;
        $discount = min($discount, $gross);
        if ($discount <= 0) {
            return $no('tiny', t('الخصم على هذا المبلغ أقل من ريال، فلا يطبق.'), $row);
        }

        return array(
            'ok'       => true,
            'reason'   => 'ok',
            'message'  => $capped
                ? t('طبق الكود — خصم ____٪ بحد أقصى ____ ر.س.', array($pct, $this->sar($discount)))
                : t('طبق الكود — خصم ____٪.', array($pct)),
            'coupon'   => $row,
            'code'     => (string) $row['code'],
            'percent'  => $pct,
            'gross'    => $gross,
            'discount' => $discount,
            'net'      => $gross - $discount,
            'capped'   => $capped,
        );
    }

    /**
     * كم استعمل هذا الكود — للكل ولهذا الطالب.
     *
     * المسوى كله، والمحجوز ما لم تمض مهلته. ومحجوز هذا الطالب **للعنصر
     * نفسه** يستثنى: هو يعود ليكمل فاتورته، أو يشتري الشيء نفسه بكود
     * آخر فتحل الجديدة محلها — وعده عليه يرده عن فاتورته هو.
     */
    private function used_count($coupon_id, $user_id, $kind, $item_id, $before_id = 0)
    {
        $since = date('Y-m-d H:i:s', time() - self::HOLD_HOURS * 3600);
        $all = 0; $mine = 0;
        try {
            /* `$before_id` — إعادة العد بعد الحجز تعد ما سبقه وحده: نقرتان
               متزامنتان على آخر استعمال يرى كل منهما حجز الآخر، فلو عد كل
               واحد الكل لرد الاثنان ولم يأخذه أحد. الأسبق يأخذه. */
            if ((int) $before_id > 0) $this->db->where('id <', (int) $before_id);
            $rows = $this->db->select('user_id, kind, item_id, status, created_at')
                             ->where('coupon_id', (int) $coupon_id)
                             ->where_in('status', array('held', 'paid'))
                             ->get('tq_coupon_redemptions')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array('all' => 0, 'mine' => 0);
        }
        foreach ($rows as $r) {
            $is_mine = ($user_id > 0 && (int) $r['user_id'] === (int) $user_id);
            if ($r['status'] === 'held') {
                if ((string) $r['created_at'] < $since) continue;
                if ($is_mine && $r['kind'] === $kind && (int) $r['item_id'] === (int) $item_id) continue;
            }
            $all++;
            if ($is_mine) $mine++;
        }
        return array('all' => $all, 'mine' => $mine);
    }

    /** هل دفع هذا الحساب شيئا من قبل؟ — لشرط «أول شراء». */
    private function has_bought($user_id)
    {
        try {
            return (int) $this->db->where('user_id', (int) $user_id)
                                  ->where('status', 'paid')->where('total >', 0)
                                  ->count_all_results('invoices') > 0;
        } catch (Throwable $e) {
            $this->db->reset_query();
            return false;
        }
    }

    /* =====================================================================
       دورة الاستعمال: حجز · ربط · تسوية · إلغاء
       ===================================================================== */

    /**
     * يحجز استعمالا **قبل** أن يكتب صف الشراء — ثم يعيد العد بعده.
     *
     * الحد مستنتج بـ`COUNT`، ونقرتان متزامنتان على آخر استعمال تقرآن
     * «بقي واحد» معا. فيكتب الحجز ثم يعد من جديد، وما تجاوز يرد عن نفسه
     * — وهي قاعدة TQ-FND-PACK-RACE نفسها.
     *
     * @return array ok · id · message
     */
    public function hold($q, $user_id, $kind, $item_id)
    {
        $row = $q['coupon'];
        $this->db->insert('tq_coupon_redemptions', array(
            'coupon_id'  => (int) $row['id'],
            'code'       => (string) $row['code'],
            'user_id'    => (int) $user_id,
            'kind'       => (string) $kind,
            'item_id'    => (int) $item_id,
            'gross'      => (int) $q['gross'],
            'discount'   => (int) $q['discount'],
            'net'        => (int) $q['net'],
            'status'     => 'held',
            'created_at' => date('Y-m-d H:i:s'),
        ));
        $id = (int) $this->db->insert_id();

        $again = $this->used_count((int) $row['id'], (int) $user_id, $kind, (int) $item_id, $id);
        /* الحجز نفسه لا يعد في `used_count()` (هو للعنصر نفسه)، فالمقارنة
           بالحد كما هي: ما عداه بلغ الحد ⇐ هذا زائد. */
        $over = ($row['max_uses'] !== null && (int) $row['max_uses'] > 0 && $again['all'] >= (int) $row['max_uses'])
             || ((int) $row['per_user'] > 0 && $again['mine'] >= (int) $row['per_user']);
        if ($over) {
            $this->db->where('id', $id)->delete('tq_coupon_redemptions');
            return array('ok' => false, 'id' => 0,
                         'message' => t('سبقك غيرك إلى آخر استعمال لهذا الكود. أكمل بلا كود أو جرب كودا آخر.'));
        }
        return array('ok' => true, 'id' => $id, 'message' => '');
    }

    /**
     * يربط الحجز بالصف والفاتورة، ويسقط ما سبقه للعنصر نفسه.
     *
     * من أصدر فاتورة كتاب بكود ثم عاد فاشتراه بكود آخر أو بلا كود، لا
     * يبقى له استعمالان محجوزان لشيء واحد — الثاني يحل محل الأول.
     * و`$hold_id` صفر في شراء بلا كود: يسقط السابق ولا يربط شيئا.
     */
    public function attach($hold_id, $user_id, $kind, $item_id, $sid, $inv)
    {
        try {
            if ((int) $hold_id > 0) {
                $this->db->where('id', (int) $hold_id)->update('tq_coupon_redemptions', array(
                    'subscription_id' => (int) $sid,
                    'invoice_id'      => (int) $inv,
                ));
            }
            $this->db->where('user_id', (int) $user_id)
                     ->where('kind', (string) $kind)->where('item_id', (int) $item_id)
                     ->where('status', 'held')->where('id !=', (int) $hold_id)
                     ->update('tq_coupon_redemptions', array('status' => 'void'));
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-COUPON attach: ' . $e->getMessage());
        }
    }

    /**
     * التسوية — تنادى من `activate()` وحدها، وهي باب التفعيل الواحد.
     *
     * **والملغى يسوى كذلك**: حجز أسقطته مهلة أو فاتورة أحدث، ثم حول
     * صاحبه قيمة الفاتورة القديمة بخصمها — فالمال وصل بذلك الخصم، والسجل
     * يقول ما وقع لا ما كان يجب أن يقع.
     */
    public function settle($subscription_id)
    {
        if (!$this->table_ready()) return;
        try {
            $this->db->where('subscription_id', (int) $subscription_id)
                     ->where('status !=', 'paid')
                     ->update('tq_coupon_redemptions', array(
                         'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')));
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-COUPON settle #' . (int) $subscription_id . ': ' . $e->getMessage());
        }
    }

    /** إلغاء فاتورة معلقة يرد الاستعمال — ينادى من أبواب الإلغاء. */
    public function release($subscription_id)
    {
        if (!$this->table_ready()) return;
        try {
            $this->db->where('subscription_id', (int) $subscription_id)
                     ->where('status', 'held')
                     ->update('tq_coupon_redemptions', array('status' => 'void'));
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
    }

    /* =====================================================================
       التوليد
       ===================================================================== */

    /**
     * يولد دفعة أكواد بإعدادات واحدة — «خمسون كودا لحملة المدارس».
     *
     * **والكود فردي الاستعمال لكل حساب**، ودفعة لمئة طالب مئة كود لا كود
     * واحد يتداول: الكود العام يصل إلى مجموعات واتساب في يومه، والدفعة
     * تعرف من أخذ ماذا ويوقف منها ما تسرب وحده.
     *
     * والعشوائية من `random_int` على حروف لا تلتبس (بلا 0/O/1/I/L):
     * الكود يقرأ من صورة ويكتب بيد، و«O أم صفر؟» سؤال يرد عليه بشكوى.
     *
     * @param array $base حقول الكود كما يقبلها `clean()`
     * @return array ok · codes · batch · errors
     */
    public function generate($base, $count, $prefix = '', $length = 6)
    {
        $this->ensure_schema();

        $count  = (int) $count;
        $length = max(5, min(12, (int) $length));
        $prefix = trim($this->normalize($prefix), '-');

        $errors = array();
        if ($count < 1 || $count > 500) $errors[] = t('عدد الأكواد من ١ إلى ٥٠٠ في الدفعة الواحدة.');
        if (strlen($prefix) > 16)       $errors[] = t('البادئة ستة عشر حرفا على الأكثر.');

        $clean = $this->clean($base, 0, true);
        $errors = array_merge($errors, $clean['errors']);
        if ($errors) return array('ok' => false, 'errors' => $errors, 'codes' => array(), 'batch' => '');

        $batch = trim((string) (isset($base['batch']) ? $base['batch'] : ''));
        if ($batch === '') $batch = ($prefix !== '' ? $prefix : 'BATCH') . '-' . date('ymd-His');
        $batch = mb_substr($batch, 0, 60);

        $codes = array();
        $now   = date('Y-m-d H:i:s');
        $by    = $this->actor_id();
        $abc   = self::ALPHABET;
        $n_abc = strlen($abc);

        for ($i = 0, $guard = 0; $i < $count && $guard < $count * 20; $guard++) {
            $s = '';
            for ($j = 0; $j < $length; $j++) $s .= $abc[random_int(0, $n_abc - 1)];
            $code = ($prefix !== '' ? $prefix . '-' : '') . $s;

            /* الفرادة يحكم بها الفهرس لا فحص قبله: نداءان متزامنان
               يقرآن «غير مستعمل» معا، والثاني يرتد على المفتاح. */
            $row = $clean['data'];
            $row['code']       = $code;
            $row['batch']      = $batch;
            $row['created_by'] = $by;
            $row['created_at'] = $now;
            try {
                $this->db->insert('tq_coupons', $row);
                $codes[] = $code;
                $i++;
            } catch (Throwable $e) {
                $this->db->reset_query();   // تكرار نادر — يولد غيره
            }
        }

        $this->audit('coupon.generate', 'tq_coupons:batch=' . $batch, null,
                     array('count' => count($codes), 'percent' => $clean['data']['percent']));
        return array('ok' => count($codes) > 0, 'codes' => $codes, 'batch' => $batch,
                     'errors' => count($codes) ? array() : array(t('تعذر توليد الأكواد.')));
    }

    /**
     * ينقي حقول كود واحد — ويرد الأخطاء بما يصلحها.
     *
     * يناديه التوليد، ويناديه حفظ الوحدة الموصوفة في اللوحة
     * (`Taqdar_admin_model::save()`) — فقاعدة «النسبة من ١ إلى ١٠٠» تكتب
     * مرة، ولا تقبل شاشة ما ترده الأخرى.
     *
     * @param bool $no_code التوليد يصنع الكود بنفسه
     */
    public function clean($data, $id = 0, $no_code = false)
    {
        $errors = array();
        $out    = array();
        $kinds  = $this->kinds();

        if (!$no_code) {
            $code = $this->normalize(isset($data['code']) ? $data['code'] : '');
            if (strlen($code) < 3 || strlen($code) > 40) {
                $errors[] = t('الكود من ٣ إلى ٤٠ حرفا — حروف لاتينية وأرقام وشرطة.');
            }
            $out['code'] = $code;
        }

        $pct = (int) (isset($data['percent']) ? $data['percent'] : 0);
        if ($pct < 1 || $pct > 100) $errors[] = t('نسبة الخصم عدد من ١ إلى ١٠٠.');
        $out['percent'] = max(0, min(100, $pct));

        $any = false;
        foreach ($kinds as $k => $d) {
            $out[$d['col']] = !empty($data[$d['col']]) ? 1 : 0;
            if ($out[$d['col']]) $any = true;
        }
        if (!$any) $errors[] = t('كود لا ينطبق على نوع واحد لا يعمل على شيء — اختر ما يخصم منه.');

        foreach (array('plan_ids', 'course_ids', 'book_ids') as $col) {
            $v = isset($data[$col]) ? $data[$col] : '';
            $out[$col] = implode(',', $this->ids(is_array($v) ? implode(',', $v) : (string) $v));
        }

        foreach (array('min_amount', 'max_discount') as $col) {
            $v = isset($data[$col]) ? $data[$col] : null;
            $out[$col] = ($v === null || $v === '') ? null : max(0, (int) $v);
            if ($out[$col] === 0) $out[$col] = null;
        }

        $mu = isset($data['max_uses']) ? $data['max_uses'] : null;
        $out['max_uses'] = ($mu === null || $mu === '' || (int) $mu <= 0) ? null : (int) $mu;
        $out['per_user'] = max(0, (int) (isset($data['per_user']) ? $data['per_user'] : 1));
        $out['first_purchase'] = !empty($data['first_purchase']) ? 1 : 0;
        $out['active']   = array_key_exists('active', $data) ? (!empty($data['active']) ? 1 : 0) : 1;

        foreach (array('starts_at', 'ends_at') as $col) {
            $v = trim((string) (isset($data[$col]) ? $data[$col] : ''));
            $out[$col] = ($v !== '' && strtotime($v)) ? date('Y-m-d H:i:s', strtotime($v)) : null;
        }
        if ($out['starts_at'] && $out['ends_at'] && strtotime($out['ends_at']) <= strtotime($out['starts_at'])) {
            $errors[] = t('تاريخ الانتهاء قبل تاريخ البدء — فلا يعمل الكود يوما واحدا.');
        }

        /* خصم مئة بالمئة بلا حد استعمال: يفتح المنصة كلها مجانا لكل من
           وصله. يقبل لأنه قد يقصد (هدية لمعلم أو منحة) — لكن لا بالسكوت. */
        if ($out['percent'] === 100 && $out['max_uses'] === null && $out['per_user'] !== 1
            && empty($data['confirm_full'])) {
            $errors[] = t('خصم ١٠٠٪ بلا حد لعدد الاستعمال يجعل كل من وصله الكود يأخذ بلا ثمن. ضع حدا للاستعمال، أو اجعله مرة لكل حساب.');
        }

        $out['label'] = mb_substr(trim((string) (isset($data['label']) ? $data['label'] : '')), 0, 190);
        if (array_key_exists('note', $data)) $out['note'] = trim((string) $data['note']);

        if (!$no_code && !$errors) {
            $this->db->where('code', $out['code']);
            if ((int) $id > 0) $this->db->where('id !=', (int) $id);
            if ($this->db->count_all_results('tq_coupons') > 0) {
                $errors[] = t('هذا الكود مستعمل لكود آخر — والكود فريد.');
            }
        }

        return array('data' => $out, 'errors' => $errors);
    }

    /* =====================================================================
       اللوحة
       ===================================================================== */

    /**
     * أرقام كل كود في استعلام واحد — لا استعلام لكل صف في القائمة.
     *
     * @return array coupon_id ⇒ paid · held · discount · revenue
     */
    public function usage_map($ids = null)
    {
        $this->ensure_schema();
        $since = date('Y-m-d H:i:s', time() - self::HOLD_HOURS * 3600);
        $out = array();
        try {
            $this->db->select("coupon_id,
                SUM(status = 'paid') AS paid,
                SUM(status = 'held' AND created_at >= " . $this->db->escape($since) . ") AS held,
                SUM(CASE WHEN status = 'paid' THEN discount ELSE 0 END) AS discount,
                SUM(CASE WHEN status = 'paid' THEN net ELSE 0 END) AS revenue", false)
                     ->group_by('coupon_id');
            if (is_array($ids)) {
                if (!$ids) return array();
                $this->db->where_in('coupon_id', array_map('intval', $ids));
            }
            foreach ($this->db->get('tq_coupon_redemptions')->result_array() as $r) {
                $out[(int) $r['coupon_id']] = array(
                    'paid' => (int) $r['paid'], 'held' => (int) $r['held'],
                    'discount' => (int) $r['discount'], 'revenue' => (int) $r['revenue'],
                );
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-COUPON usage_map: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * حال الكود كما يقرؤه المسؤول: أيعمل الآن؟ ولم لا؟
     *
     * والحال مشتقة لا مخزنة: «منته» عمود يكتبه كرون ينسى يوما، والتاريخ
     * يقرأ الآن. وأسباب العطل مرتبة كما يحكم بها `quote()` — فما يقرؤه
     * المسؤول هنا هو أول ما يرد به الطالب.
     *
     * @return array tone: ok|warn|no · label · why · key
     */
    public function status_of($row, $use = null)
    {
        $use = $use ?: array('paid' => 0, 'held' => 0);
        $now = time();

        if ((int) $row['active'] !== 1) {
            return array('key' => 'off', 'tone' => 'no', 'label' => t('موقوف'), 'why' => t('لا يقبل في أي شراء حتى يفعل.'));
        }
        if (!empty($row['ends_at']) && strtotime($row['ends_at']) < $now) {
            return array('key' => 'expired', 'tone' => 'no', 'label' => t('منته'),
                         'why' => t('انتهى في ____.', array($this->day($row['ends_at']))));
        }
        if ($row['max_uses'] !== null && (int) $row['max_uses'] > 0
            && ($use['paid'] + $use['held']) >= (int) $row['max_uses']) {
            return array('key' => 'exhausted', 'tone' => 'no', 'label' => t('استنفد'),
                         'why' => t('استعمل ____ من ____.', array($use['paid'] + $use['held'], (int) $row['max_uses'])));
        }
        if (!empty($row['starts_at']) && strtotime($row['starts_at']) > $now) {
            return array('key' => 'scheduled', 'tone' => 'warn', 'label' => t('مجدول'),
                         'why' => t('يبدأ في ____.', array($this->day($row['starts_at']))));
        }
        $left = ($row['max_uses'] !== null && (int) $row['max_uses'] > 0)
              ? t('بقي ____ استعمالا.', array((int) $row['max_uses'] - $use['paid'] - $use['held']))
              : t('بلا حد لعدد الاستعمال.');
        $end  = !empty($row['ends_at']) ? ' ' . t('حتى ____.', array($this->day($row['ends_at']))) : '';
        return array('key' => 'live', 'tone' => 'ok', 'label' => t('يعمل'), 'why' => $left . $end);
    }

    /**
     * القائمة بمرشحاتها — والمرشح يقرأ من الحال المشتقة لا من عمود.
     *
     * @return array rows (ومع كل صف `use` و`state`) · total · batches
     */
    public function listing($f = array())
    {
        $this->ensure_schema();
        $q     = trim((string) (isset($f['q']) ? $f['q'] : ''));
        $batch = trim((string) (isset($f['batch']) ? $f['batch'] : ''));
        $state = trim((string) (isset($f['state']) ? $f['state'] : ''));

        try {
            if ($q !== '') {
                $this->db->group_start()->like('code', $this->normalize($q))
                         ->or_like('label', $q)->or_like('batch', $q)->group_end();
            }
            if ($batch !== '') $this->db->where('batch', $batch);
            $rows = $this->db->order_by('id', 'DESC')->limit(1000)->get('tq_coupons')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            $rows = array();
        }

        $use = $this->usage_map(array_map(function ($r) { return (int) $r['id']; }, $rows));
        $out = array();
        foreach ($rows as $r) {
            $u = isset($use[(int) $r['id']]) ? $use[(int) $r['id']]
               : array('paid' => 0, 'held' => 0, 'discount' => 0, 'revenue' => 0);
            $st = $this->status_of($r, $u);
            if ($state !== '' && $st['key'] !== $state) continue;
            $r['use']   = $u;
            $r['state'] = $st;
            $out[] = $r;
        }

        $batches = array();
        try {
            foreach ($this->db->select('batch, COUNT(*) AS n', false)->where('batch !=', '')
                              ->group_by('batch')->order_by('MAX(id)', 'DESC', false)
                              ->get('tq_coupons')->result_array() as $b) {
                $batches[$b['batch']] = (int) $b['n'];
            }
        } catch (Throwable $e) { $this->db->reset_query(); }

        return array('rows' => $out, 'batches' => $batches);
    }

    /** أرقام الشاشة الأولى — ما وفره الطلاب وما جلبه الكود من بيع. */
    public function totals()
    {
        $this->ensure_schema();
        $t = array('codes' => 0, 'live' => 0, 'paid' => 0, 'discount' => 0, 'revenue' => 0, 'held' => 0);
        try {
            $t['codes'] = (int) $this->db->count_all_results('tq_coupons');
        } catch (Throwable $e) { $this->db->reset_query(); }
        foreach ($this->usage_map() as $u) {
            $t['paid'] += $u['paid']; $t['held'] += $u['held'];
            $t['discount'] += $u['discount']; $t['revenue'] += $u['revenue'];
        }
        $t['live'] = 0;
        foreach ($this->listing(array('state' => 'live'))['rows'] as $r) $t['live']++;
        return $t;
    }

    /**
     * من استعمل هذا الكود، وفي أي شراء، وبكم — شاشة الكود الواحد.
     *
     * والاسم من `sold()` وحدها (TQ-SOLD-NAME): وحدة بيع سادسة يوما تصل
     * هذه الشاشة معها بلا سطر.
     */
    public function redemptions($coupon_id, $limit = 300)
    {
        $this->ensure_schema();
        try {
            $rows = $this->db->select('r.*, u.first_name, u.last_name, u.email,
                                       s.status AS sub_status, i.invoice_no, i.status AS inv_status', false)
                             ->from('tq_coupon_redemptions r')
                             ->join('users u', 'u.id = r.user_id', 'left')
                             ->join('subscriptions s', 's.id = r.subscription_id', 'left')
                             ->join('invoices i', 'i.id = r.invoice_id', 'left')
                             ->where('r.coupon_id', (int) $coupon_id)
                             ->order_by('r.id', 'DESC')->limit((int) $limit)
                             ->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-COUPON redemptions: ' . $e->getMessage());
            return array();
        }
        $this->load->model('taqdar_billing_model');
        $since = date('Y-m-d H:i:s', time() - self::HOLD_HOURS * 3600);
        foreach ($rows as &$r) {
            $r['sold'] = ((int) $r['subscription_id'] > 0)
                       ? $this->taqdar_billing_model->sold((int) $r['subscription_id'])
                       : array('label' => '—', 'title' => '—');
            $r['stale'] = ($r['status'] === 'held' && (string) $r['created_at'] < $since);
        }
        unset($r);
        return $rows;
    }

    /** يوقف الكود أو يفعله — وأثره في السجل. */
    public function toggle($id, $on)
    {
        $before = $this->get($id);
        if (!$before) return false;
        $this->db->where('id', (int) $id)->update('tq_coupons', array('active' => $on ? 1 : 0));
        $this->audit($on ? 'coupon.enable' : 'coupon.disable', 'tq_coupons#' . (int) $id, $before, $this->get($id));
        return true;
    }

    /** يوقف دفعة كاملة — لما تسرب منها إلى مجموعة لم تقصد. */
    public function toggle_batch($batch, $on)
    {
        $batch = trim((string) $batch);
        if ($batch === '') return 0;
        $this->db->where('batch', $batch)->update('tq_coupons', array('active' => $on ? 1 : 0));
        $n = (int) $this->db->affected_rows();
        $this->audit($on ? 'coupon.batch_enable' : 'coupon.batch_disable', 'tq_coupons:batch=' . $batch,
                     null, array('rows' => $n));
        return $n;
    }

    /**
     * ما يمنع الحذف — بالرقم لا بـ«غير مسموح».
     *
     * كود استعمل مرة لا يحذف، يوقف: `subscriptions.coupon_id` ورد
     * الاستعمال يشيران إليه، وحذفه يترك «خصم ٨٠ ر.س بكود #12» في سجل
     * بيعة لا يعرف أحد أي كود كان. وهو مبدأ TQ-PLAN-DELETE نفسه.
     */
    public function delete_blockers($id)
    {
        $this->ensure_schema();
        $n = 0;
        try {
            $n = (int) $this->db->where('coupon_id', (int) $id)->where('status !=', 'void')
                                ->count_all_results('tq_coupon_redemptions');
        } catch (Throwable $e) { $this->db->reset_query(); }
        return $n > 0
            ? array(t('استعمل في ____ شراء، وسجل كل بيعة يشير إليه.', array($n)))
            : array();
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    /** هل هذا العنوان تجاوز حد المحاولات الفاشلة؟ */
    private function throttled()
    {
        if ($this->input->is_cli_request()) return false;
        try {
            return (int) $this->db->where('ip', (string) $this->input->ip_address())
                                  ->where('at >=', date('Y-m-d H:i:s', time() - 900))
                                  ->count_all_results('tq_coupon_tries') >= self::MAX_TRIES;
        } catch (Throwable $e) {
            $this->db->reset_query();
            return false;
        }
    }

    private function note_try()
    {
        if ($this->input->is_cli_request()) return;
        try {
            $this->db->insert('tq_coupon_tries', array(
                'ip' => (string) $this->input->ip_address(), 'at' => date('Y-m-d H:i:s')));
            /* تنظيف رخيص في الطريق — لا كرون ثامن لجدول صغير. */
            if (random_int(1, 50) === 1) {
                $this->db->where('at <', date('Y-m-d H:i:s', time() - 86400))->delete('tq_coupon_tries');
            }
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    private function table_ready()
    {
        $this->ensure_schema();
        try { return $this->db->table_exists('tq_coupon_redemptions'); }
        catch (Throwable $e) { return false; }
    }

    /** قائمة معرفات من نص بفواصل — أعداد موجبة بلا تكرار، مرتبة. */
    private function ids($csv)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $csv)))));
        sort($ids);
        return $ids;
    }

    private function sar($halalas)
    {
        $v = ((int) $halalas) / 100;
        return (floor($v) == $v) ? number_format($v, 0) : number_format($v, 2);
    }

    private function day($dt)
    {
        return date('Y-m-d', strtotime((string) $dt));
    }

    private function actor_id()
    {
        $CI = function_exists('get_instance') ? get_instance() : null;
        if (!$CI || !isset($CI->session) || !is_object($CI->session)) return 0;
        return (int) $CI->session->userdata('user_id');
    }

    private function audit($action, $entity, $before, $after)
    {
        try {
            $this->db->insert('audit_log', array(
                'actor_id' => $this->actor_id(),
                'action'   => $action,
                'entity'   => $entity,
                'before'   => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                'after'    => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
                'ip'       => $this->input->is_cli_request() ? 'cli' : $this->input->ip_address(),
                'at'       => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    private function try_sql($sql)
    {
        try { $this->db->query($sql); } catch (Throwable $e) {
            log_message('error', 'TQ-COUPON schema: ' . $e->getMessage());
        }
    }

    private function field($col, $table)
    {
        try { return $this->db->field_exists($col, $table); }
        catch (Throwable $e) { return true; }
    }

    private function setting($key, $default = null)
    {
        try {
            $r = $this->db->select('value')->where('key', $key)->get('settings')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return $default; }
        return $r ? $r['value'] : $default;
    }

    private function put_setting($key, $value)
    {
        try {
            if ($this->db->where('key', $key)->count_all_results('settings') > 0) {
                $this->db->where('key', $key)->update('settings', array('value' => $value));
            } else {
                $this->db->insert('settings', array('key' => $key, 'value' => $value));
            }
        } catch (Throwable $e) { $this->db->reset_query(); }
    }
}
