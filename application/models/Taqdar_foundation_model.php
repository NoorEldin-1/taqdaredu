<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-FOUNDATION — قسم التأسيس: وحدة بيع خامسة، وهي **وقت** لا محتوى.
 *
 * المنصة تبيع اربعة: باقة صف، ومسارا، وكورسا مفردا، وكتابا. واربعتها
 * محتوى قائم يفتح لمن دفع. والتأسيس شيء اخر: طالب لا يسأل عن منهج صفه،
 * يسأل ان يتعلم القراءة بالعربية او الانجليزية من اولها — ولا صف له في
 * ذلك ولا مادة في `subjects` تصفه، والقياس عليه بمنهج الصف الثالث خطأ في
 * السؤال قبل ان يكون خطأ في الجواب.
 *
 * وكان الباب مغلقا مرتين لا مرة:
 *
 *   ١ — **الطالب** لا يجد ما يشتريه: صفحة «حصص بالطلب» ترشح بصفه
 *       (TQ-SESSION-GRID)، فمن لا يريد منهج صفه لا يرى شيئا يناسبه.
 *   ٢ — **المعلم** لا يجد ما يفتحه: `save_windows()` تشترط صفا ومادة من
 *       `teacher_scope()`، وهي تشتق من الكورسات والمسارات — ومعلم تأسيس
 *       لا كورس له، فيرد عليه «لا صف لك بعد» ويقف.
 *
 * ---------------------------------------------------------------------
 * **والقاعدة الحاكمة هي قاعدة TQ-COURSE-SALE وTQ-BOOK نفسها: لا محرك ثان.**
 *
 * حصة التأسيس صف في `tutoring_sessions` على موعد في `availability_slots`
 * فرش من قاعدة في `tq_teacher_windows` — الجداول الثلاثة نفسها بدورة
 * الحياة نفسها: يطلب الطالب، ويؤكد المعلم، وتصدر فاتورة، ويدفع، ويفتح
 * الرابط في وقته، ويعلن المعلم انتهاءها فيقيد نصيبه من
 * `Taqdar_wallet_model::credit_session()`. وتسويه تاب بالفرع نفسه
 * (`by_invoice()`)، وتنقله دورة الكرون نفسها (`lifecycle_tick()`)،
 * وتلغيه الادارة بالزر نفسه. ونظام ثان كان يحتاج نسخة ثانية من كل واحد
 * من هذه العشرة.
 *
 * **والذي يتغير بعد واحد: مصدر النطاق.**
 *
 *   حصة المنهج    نطاقها (صف، مادة) من `teacher_scope()` — اي من كورساته
 *   حصة التأسيس   نطاقها **مسار تأسيس** يسنده المسؤول، ولا صف ولا مادة
 *
 * ولذلك عمودان لا جدول: `kind` و`track_id` على النافذة وعلى الموعد وعلى
 * الحصة. والقديم كله `curriculum` بحكم الافتراض، فما كان يعمل يبقى يعمل
 * حرفا بحرف.
 *
 * ---------------------------------------------------------------------
 * **ولماذا لا صف ولا مادة؟** التأسيس **مستوى** لا مقرر — من يؤسس في
 * الانجليزية قد يكون في الرابع الابتدائي وقد يكون في الاول المتوسط،
 * والصف هنا لا يخبر عن شيء. فحقن `grade_id` في مسار التأسيس يجعل ترشيح
 * الطالب بصفه يمحو من شاشته معلمين يصلحون له تماما.
 *
 * فحقلاهما يكتبان صفرا في مواعيد التأسيس، وشاشة الطالب **لا ترشح بالصف**
 * في هذا القسم — وهو الفرق الجوهري بين البابين، وهو مكتوب في الاستعلام
 * (`available_teachers($kind)`) لا في الشاشة.
 *
 * ---------------------------------------------------------------------
 * **ومن يدرس التأسيس؟ من اسنده المسؤول، لا من له كورس.**
 *
 * `teacher_ids` قائمة معرفات بفواصل على صف المسار (`multiref`) — والاسناد
 * صريح لا مشتق: معلم التأسيس قد لا يملك كورسا واحدا في المنصة، والاشتقاق
 * من الكورسات هو بعينه الباب الذي كان مغلقا. وبلا اسناد لا يفتح احد وقتا
 * في المسار، فلا يعرض على الطلاب مسار بلا من يدرسه.
 *
 * **والسعر سعر المسار اولا.** المسار منتج معلن بسعر واحد في صفحة عامة،
 * وسعران لمسار واحد يجعلان الصفحة تعد بسعر ويطلب المعلم غيره. فان لم
 * يكتب للمسار سعر ارتد الى استثناء المعلم ثم الى التسعيرة العامة —
 * والترتيب مكتوب في `pricing_for()` وحدها، فلا شاشة تحسبه لنفسها.
 * **والفارغ غير الصفر**: فارغ يعني «خذ ما تحته»، وصفر يعني «مجانا بقرار».
 *
 * **وبلا مسار منشور لا شيء يتغير**: `enabled()` كاذبة، فلا بند في قائمة،
 * ولا صفحة عامة، ولا خيار في شاشة المعلم — وتعرض المنصة ما كانت تعرضه
 * حرفا بحرف. وهي قاعدة `tq_course_sales_enabled` نفسها، الا ان المفتاح
 * هنا **وجود مسار** لا صف في `settings`: مسؤول يفتح مفتاحا ثم لا يجد
 * مسارا يعرضه قد ترك القسم فارغا وهو يظنه يعمل.
 */
class Taqdar_foundation_model extends CI_Model
{
    /** اسم النوع كما يكتب في `kind` — وقيمته في القاعدة نصا لا رقما. */
    const KIND = 'foundation';

    /** اصدار بنية التأسيس — يمنع اعادة فحص الجدول في كل طلب. */
    const SCHEMA_V = '3';

    /** جدول المسارات. */
    const TABLE = 'tq_foundation_tracks';

    /** TQ-FND-PACK — جدول باقات الحصص. */
    const PACKS = 'tq_foundation_packs';

    /**
     * TQ-FND-PACK — الحالات التي **تستهلك** رصيدا من الباقة.
     *
     * والرصيد مستنتج من هذه القائمة لا معدود في عمود: عداد يزاد عند
     * الحجز وينقص عند الالغاء ينحرف عن الحقيقة عند اول استثناء يبتلع —
     * فيقرأ الطالب «تبقى لك ٢» وقد حجز ستا، او يقرأ «نفد رصيدك» وهو لم
     * يحجز شيئا. والقائمة تعد الحي والمنتهي معا، وتترك المعتذر عنه
     * والمنتهية مهلته والمسترد — فالحصة التي لم تنعقد تعود الى رصيد
     * صاحبها بلا سطر يكتب.
     */
    public static $SPENT = array('requested', 'awaiting_payment', 'confirmed', 'live', 'completed');

    private static $cache = null;
    private static $pack_cache = null;

    /* =====================================================================
       البنية
       ===================================================================== */

    /**
     * ينشئ جدول المسارات، ويزرع مسارين عند اول تشغيل.
     *
     * والزرع مرة واحدة وبشرط الجدول الفارغ: مسؤول حذف «تأسيس اللغة
     * الانجليزية» لانه لا يقدمه لا يريده ان يعود في كل نشر.
     *
     * والمساران هما اللذان طلبا بالاسم (عربي وانجليزي) — فالقسم يعمل من
     * اول لحظة، ولا يفتح المسؤول شاشة فارغة تطلب منه ان يخترع منتجا.
     */
    public function ensure_schema($force = false)
    {
        static $done = false;
        if ($done && !$force) return;
        $done = true;

        if (!$force) {
            try {
                $v = $this->db->where('key', 'tq_foundation_schema_v')->get('settings')->row_array();
                if ($v && (string) $v['value'] === self::SCHEMA_V) return;
            } catch (Throwable $e) { $this->db->reset_query(); }
        }

        $this->try_sql(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
               `id`              INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
               `name_ar`         VARCHAR(160) NOT NULL,
               `slug`            VARCHAR(120) NOT NULL,
               `tagline`         VARCHAR(255) NULL DEFAULT NULL,
               `description`     TEXT NULL DEFAULT NULL,
               `outcomes`        TEXT NULL DEFAULT NULL,
               `image`           VARCHAR(255) NULL DEFAULT NULL,
               `teacher_ids`     VARCHAR(512) NOT NULL DEFAULT "",
               `price_halalas`   BIGINT NULL DEFAULT NULL,
               `teacher_percent` DECIMAL(5,2) NULL DEFAULT NULL,
               `featured`        TINYINT(1) NOT NULL DEFAULT 0,
               `active`          TINYINT(1) NOT NULL DEFAULT 1,
               `order`           INT(11) NOT NULL DEFAULT 0,
               `created_at`      DATETIME NULL DEFAULT NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_ft_slug` (`slug`),
               KEY `idx_ft_active` (`active`,`order`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        /* ---- TQ-FND-PACK — باقات الحصص -----------------------------
           الحصة كانت تباع مفردة: يحجز الطالب ساعة، ويؤكد معلمه، وتصدر
           فاتورة بثمنها، ويدفع. وذلك صحيح لمن يريد حصة واحدة، وهو
           **اكثر ما يصد من يريد ان يؤسس**: التأسيس رحلة لا جلسة، ومن
           ينوي ست حصص يقرأ ستة اثمان وست فواتير وست مهل دفع — فينصرف او
           يجرب واحدة ولا يعود.

           فالباقة صف هنا: مسار، وعدد حصص، وثمن واحد اقل من مجموع افرادها.
           والاهم انها **لا تحجز مواعيد**: يدفع الطالب مرة، ويصير له
           **رصيد** يحجز به متى شاء في اي موعد يفتحه اي معلم في المسار —
           وهو عين ما طلب («يوزعهم بالشهر، مبلغ يدفع مرة واحدة»). وباقة
           تحجز المواعيد سلفا تلزم الطالب بجدول شهر لم يعشه بعد، وتلزم
           معلمه بست ساعات قد يعتذر عن احداها فتتعطل الست. */
        $this->try_sql(
            'CREATE TABLE IF NOT EXISTS `' . self::PACKS . '` (
               `id`              INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
               `track_id`        INT(10) UNSIGNED NOT NULL DEFAULT 0,
               `name_ar`         VARCHAR(160) NOT NULL,
               `tagline`         VARCHAR(255) NULL DEFAULT NULL,
               `sessions`        SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1,
               `price_halalas`   BIGINT NOT NULL DEFAULT 0,
               `teacher_percent` DECIMAL(5,2) NULL DEFAULT NULL,
               `validity_days`   SMALLINT(5) UNSIGNED NOT NULL DEFAULT 60,
               `featured`        TINYINT(1) NOT NULL DEFAULT 0,
               `active`          TINYINT(1) NOT NULL DEFAULT 1,
               `order`           INT(11) NOT NULL DEFAULT 0,
               `created_at`      DATETIME NULL DEFAULT NULL,
               PRIMARY KEY (`id`),
               KEY `idx_fp_track` (`track_id`,`active`,`order`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        /* والباقة **وحدة بيع خامسة على المحرك نفسه**: صف في `subscriptions`
           بفاتورته، تسويه تاب بالفرع نفسه، ويفعله المسؤول بالزر نفسه في
           «الاشتراكات»، وينتهي اجله بـ`expire_due()` نفسها. ونظام «رصيد»
           مستقل كان يحتاج نسخة ثانية من كل واحد من هذه الاربعة.

           وعمودان لا اكثر: `pack_id` يقول ما بيع، و`pack_sessions` يجمد
           **عدد الحصص وقت الشراء** — فرفع الباقة من ست الى ثمان غدا لا
           يزيد رصيد من اشترى امس ولا ينقصه. وهو مبدأ تجميد `days` و
           `cycle` في TQ-CYCLE-BUY نفسه. */
        /* **وبند `foundation` يضاف الى `ENUM` قبل ان يكتب.**
           `subscription_items.entity_type` سرد مغلق كتب يوم كانت انواع
           البنود ستة، و MySQL يكتب **نصا فارغا** لقيمة خارجه ولا يرمي
           خطأ (خارج الوضع الصارم). فبلا هذا السطر يفعل الاشتراك، ويكتب
           بنده، ويقرأ `entity_type = ''` — فتقول شاشة تفاصيل البيعة «لا
           يفتح شيئا» عن رصيد قائم، ولا شيء يخطئ في اي موضع. */
        $this->try_sql(
            "ALTER TABLE `subscription_items` MODIFY COLUMN `entity_type`
             ENUM('all','subject','path','course','trial','grade','book','foundation')
             NOT NULL DEFAULT 'all'"
        );

        $this->try_sql('ALTER TABLE `subscriptions` ADD COLUMN IF NOT EXISTS `pack_id` INT(10) UNSIGNED NOT NULL DEFAULT 0');
        $this->try_sql('ALTER TABLE `subscriptions` ADD COLUMN IF NOT EXISTS `pack_sessions` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0');
        $this->try_sql('ALTER TABLE `subscriptions` ADD INDEX `idx_sub_pack` (`pack_id`,`status`)');

        /* الاعمدة على جداول الحصص — يضيفها صاحبها لا هذا النموذج. */
        $this->load->model('taqdar_sessions_model');
        $this->taqdar_sessions_model->install_schema();

        $this->seed();
        $this->seed_math();
        $this->put_setting('tq_foundation_schema_v', self::SCHEMA_V);
    }

    /**
     * TQ-FOUNDATION-MATH — مسار ثالث: تأسيس الرياضيات، يزرع **مرة واحدة في
     * عمر القاعدة**.
     *
     * طلب بالاسم مع العربي والانجليزي حين اضيف قسم التأسيس الى الرئيسية
     * وصفحة الباقات. ولا يزرعه `seed()`: ذاك مشروط بالجدول الفارغ، وكل
     * قاعدة قائمة فيها المساران من قبل.
     *
     * **والحارس علم خاص به لا رقم الاصدار.** `SCHEMA_V` يرفع غدا لسبب لا
     * علاقة له بالرياضيات (عمود يضاف مثلا)، فزرع معلق على الاصدار يجري
     * عندها ثانية — ومسؤول حذف المسار لانه لا يقدمه يجده عائدا بعد نشر لم
     * يمسه. فالعلم `tq_foundation_seed_math` يكتب بعد اول مرور ولا يمحى:
     *
     *   · النشر (`git reset --hard`) لا يلمس `settings`، فلا يعيد شيئا.
     *   · من حذف المسار بعد الزرع لا يعود اليه ابدا.
     *   · من كتب مسارا بالاسم نفسه بيده لا يجد نسخة ثانية (الفحص بالاسم).
     *   · طلبان متزامنان في اول ثانية: المفتاح الفريد `uq_ft_slug` يرد
     *     الثاني، فلا صفان.
     *
     * والعلم لا يكتب إن فشل الادراج نفسه: قاعدة تعثرت لحظتها تحاول في
     * المرة التالية التي يجري فيها التجهيز، ولا تحرم المسار بخطأ عابر.
     */
    private function seed_math()
    {
        try {
            $done = $this->db->where('key', 'tq_foundation_seed_math')->count_all_results('settings') > 0;
        } catch (Throwable $e) { $this->db->reset_query(); return; }
        if ($done) return;

        try {
            if ($this->db->where('slug', 'math')->count_all_results(self::TABLE) > 0) {
                $this->put_setting('tq_foundation_seed_math', date('Y-m-d H:i:s'));
                return;
            }
            $order = (int) $this->db->select_max('`order`', 'm', false)->get(self::TABLE)->row('m');
        } catch (Throwable $e) { $this->db->reset_query(); return; }

        try {
            $this->db->insert(self::TABLE, array(
                'name_ar' => 'تأسيس الرياضيات', 'slug' => 'math', 'order' => $order + 1,
                'tagline' => 'من العد الى العمليات — فهم الارقام من الاساس',
                'description' => 'حصص مباشرة فردية مع معلم متخصص في تأسيس الرياضيات: '
                               . 'العد والارقام وقيمها، ثم الجمع والطرح، ثم الضرب والقسمة. '
                               . 'ولا يشترط صف ولا منهج — يبدأ الطالب من حيث هو.',
                'outcomes' => json_encode(array(
                    'يقرأ الارقام ويفهم قيمة المنزلة',
                    'يجمع ويطرح بثقة ذهنيا وكتابيا',
                    'يفهم الضرب والقسمة ويحفظ جداولهما',
                ), JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ));
            $this->put_setting('tq_foundation_seed_math', date('Y-m-d H:i:s'));
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    /** يزرع المسارين المعلنين — بشرط الجدول الفارغ. */
    private function seed()
    {
        try {
            $n = (int) $this->db->count_all_results(self::TABLE);
        } catch (Throwable $e) { $this->db->reset_query(); return; }
        if ($n > 0) return;

        $now  = date('Y-m-d H:i:s');
        $rows = array(
            array(
                'name_ar' => 'تأسيس اللغة العربية', 'slug' => 'arabic', 'order' => 1,
                'tagline' => 'من الحرف الى الجملة — قراءة وكتابة من الاساس',
                'description' => 'حصص مباشرة فردية مع معلم متخصص في تأسيس اللغة العربية: '
                               . 'الحروف واصواتها، ثم المقاطع، ثم القراءة والكتابة. '
                               . 'ولا يشترط صف ولا منهج — يبدأ الطالب من حيث هو.',
                'outcomes' => json_encode(array(
                    'يقرأ الحروف واصواتها ويميز بينها',
                    'يكتب الكلمات كتابة صحيحة',
                    'يقرأ جملا قصيرة قراءة سليمة',
                ), JSON_UNESCAPED_UNICODE),
            ),
            array(
                'name_ar' => 'تأسيس اللغة الإنجليزية', 'slug' => 'english', 'order' => 2,
                'tagline' => 'من الحروف الى المحادثة — بداية صحيحة بلا منهج صف',
                'description' => 'حصص مباشرة فردية مع معلم متخصص في تأسيس اللغة الإنجليزية: '
                               . 'الحروف والاصوات، ثم الكلمات، ثم القراءة والمحادثة البسيطة. '
                               . 'ولا يشترط صف ولا منهج — يبدأ الطالب من حيث هو.',
                'outcomes' => json_encode(array(
                    'يتعرف الحروف واصواتها (Phonics)',
                    'يقرأ كلمات وجملا قصيرة',
                    'يكون جملا بسيطة في المحادثة',
                ), JSON_UNESCAPED_UNICODE),
            ),
        );

        foreach ($rows as $r) {
            $r['created_at'] = $now;
            try { $this->db->insert(self::TABLE, $r); }
            catch (Throwable $e) { $this->db->reset_query(); }
        }
    }

    private function try_sql($sql)
    {
        try { $this->db->query($sql); } catch (Throwable $e) { $this->db->reset_query(); }
    }

    private function put_setting($key, $value)
    {
        try {
            $exists = $this->db->where('key', $key)->count_all_results('settings') > 0;
            if ($exists) $this->db->where('key', $key)->update('settings', array('value' => $value));
            else         $this->db->insert('settings', array('key' => $key, 'value' => $value));
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    /* =====================================================================
       المسارات
       ===================================================================== */

    /**
     * المسارات كلها، مفتاحها معرفها.
     *
     * ويقرأ الجدول **مرة واحدة لكل طلب**: الشاشة الواحدة تسأل عن اسم
     * المسار في كل سطر من قائمة مواعيد، واستعلام لكل سطر يجعل شاشة
     * فيها ثلاثون موعدا ثلاثين استعلاما لثلاثة صفوف.
     */
    public function all($fresh = false)
    {
        if (self::$cache !== null && !$fresh) return self::$cache;

        $out = array();
        try {
            $this->ensure_schema();
            $rows = $this->db->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                             ->get(self::TABLE)->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); $rows = array(); }

        foreach ($rows as $r) {
            $id  = (int) $r['id'];
            $ids = array_values(array_filter(array_map('intval',
                       explode(',', (string) ($r['teacher_ids'] ?? '')))));

            $out[$id] = array(
                'id'          => $id,
                'name'        => (string) $r['name_ar'],
                'slug'        => (string) $r['slug'],
                'tagline'     => (string) ($r['tagline'] ?? ''),
                'description' => (string) ($r['description'] ?? ''),
                'outcomes'    => $this->lines($r['outcomes'] ?? ''),
                'image'       => (string) ($r['image'] ?? ''),
                'teacher_ids' => $ids,
                /* والفارغ غير الصفر: `null` تعني «خذ ما تحتك». */
                'price'       => ($r['price_halalas'] === null || $r['price_halalas'] === '')
                                 ? null : (int) $r['price_halalas'],
                'percent'     => ($r['teacher_percent'] === null || $r['teacher_percent'] === '')
                                 ? null : (float) $r['teacher_percent'],
                'featured'    => (int) ($r['featured'] ?? 0) === 1,
                'active'      => (int) ($r['active'] ?? 0) === 1,
                'order'       => (int) ($r['order'] ?? 0),
            );
        }
        return self::$cache = $out;
    }

    /** «المخرجات» تخزن JSON كما تخزن مزايا الباقة — وتقرأ سطورا. */
    private function lines($raw)
    {
        $arr = json_decode((string) $raw, true);
        if (is_array($arr)) return array_values(array_filter(array_map('trim', $arr)));
        $raw = trim((string) $raw);
        return $raw === '' ? array() : array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }

    /** المنشورة وحدها — وهي ما يعرض على الطالب والزائر. */
    public function published()
    {
        $out = array();
        foreach ($this->all() as $id => $t) if ($t['active']) $out[$id] = $t;
        return $out;
    }

    /** مسار بمعرفه. و`null` لا مصفوفة فارغة: «لا يوجد» غير «يوجد وفارغ». */
    public function track($id)
    {
        $all = $this->all();
        return isset($all[(int) $id]) ? $all[(int) $id] : null;
    }

    /** مسار باسمه في الرابط — صفحة `/foundation/<slug>`. */
    public function by_slug($slug)
    {
        $slug = trim((string) $slug);
        if ($slug === '') return null;
        foreach ($this->all() as $t) if ($t['slug'] === $slug) return $t;
        return null;
    }

    /**
     * هل للقسم وجود اصلا؟
     *
     * والجواب **وجود مسار منشور** لا صف في `settings`: مفتاح يفتح ولا
     * مسار تحته يترك القسم بابا يفتح على شاشة فارغة، ومسؤول يظنه يعمل.
     */
    public function enabled()
    {
        return count($this->published()) > 0;
    }

    /** اسم المسار كما يعرض — و«التأسيس» لما حذف مساره وبقيت حصته. */
    public function name_of($track_id)
    {
        $t = $this->track($track_id);
        return $t ? $t['name'] : t('التأسيس');
    }

    /* =====================================================================
       من يدرس ماذا
       ===================================================================== */

    /**
     * مسارات هذا المعلم — ما اسنده اليه المسؤول وحده.
     *
     * والمعطل يسقط: مسار رفع عن الطلاب لا يفتح فيه المعلم وقتا جديدا،
     * وما فتح قبل رفعه يبقى قائما (لا تحذف مواعيد بيعت).
     */
    public function teacher_tracks($teacher_id)
    {
        $teacher_id = (int) $teacher_id;
        $out = array();
        if ($teacher_id <= 0) return $out;

        foreach ($this->published() as $id => $t) {
            if (in_array($teacher_id, $t['teacher_ids'], true)) $out[$id] = $t;
        }
        return $out;
    }

    /** الحارس: أيفتح هذا المعلم وقتا في هذا المسار؟ */
    public function may_teach($teacher_id, $track_id)
    {
        $t = $this->track($track_id);
        if (!$t || !$t['active']) return false;
        return in_array((int) $teacher_id, $t['teacher_ids'], true);
    }

    /** معلمو مسار بأسمائهم — لشاشة اللوحة ولصفحة المسار العامة. */
    public function track_teachers($track_id)
    {
        $t = $this->track($track_id);
        if (!$t || !$t['teacher_ids']) return array();

        try {
            $rows = $this->db->select('id, first_name, last_name, image, title')
                             ->where_in('id', $t['teacher_ids'])
                             ->where('is_instructor', 1)->where('status', 1)
                             ->get('users')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }

        $out = array();
        foreach ($rows as $r) {
            $name = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
            $out[(int) $r['id']] = array(
                'id'    => (int) $r['id'],
                'name'  => $name !== '' ? $name : t('معلم'),
                'image' => (string) $r['image'],
                'title' => trim((string) $r['title']),
            );
        }
        return $out;
    }

    /* =====================================================================
       الثمن
       ===================================================================== */

    /**
     * ثمن حصة تأسيس ونصيب معلمها.
     *
     * **والترتيب مقصود**: سعر المسار، ثم استثناء المعلم، ثم العام. المسار
     * منتج معلن بسعر واحد في صفحة عامة، فسعران لمسار واحد يجعلان الصفحة
     * تعد بسعر ويطلب المعلم غيره — والطالب يقرأ الرقمين ولا يعرف ايهما
     * يدفع. وحيث لا سعر للمسار يبقى الترتيب القديم كما هو.
     *
     * @return array price · percent · share · platform · from ('track'|'teacher'|'global')
     */
    public function pricing_for($track_id, $teacher_id = 0)
    {
        $this->load->model('taqdar_sessions_model');
        $base = $this->taqdar_sessions_model->pricing_for($teacher_id);

        $t = $this->track($track_id);
        if (!$t) return $base + array('from' => empty($base['from_teacher']) ? 'global' : 'teacher');

        $price   = ($t['price']   === null) ? (int) $base['price']     : (int) $t['price'];
        $percent = ($t['percent'] === null) ? (float) $base['percent'] : (float) $t['percent'];

        $from = 'global';
        if (!empty($base['from_teacher'])) $from = 'teacher';
        if ($t['price'] !== null || $t['percent'] !== null) $from = 'track';

        return $this->taqdar_sessions_model->split($price, $percent)
             + array('from' => $from, 'from_teacher' => !empty($base['from_teacher']));
    }

    /* =====================================================================
       الارقام — لشاشة اللوحة
       ===================================================================== */

    /**
     * لكل مسار: كم معلما، وكم موعدا مفتوحا، وكم حصة، وكم مالا.
     *
     * وهي تجيب سؤال الشاشة الاول: **لماذا يقرأ الطالب «لا معلم متاح» في
     * مسار منشور؟** — والجواب في اكثر الحالات «لا معلم اسند اليه» او
     * «اسند ولم يفتح وقتا»، وهما جوابان مختلفان يقودان الى فعلين
     * مختلفين. وشاشة تقول «صفر» بلا تفريق تترك المسؤول يخمن.
     *
     * وكل استعلام ملفوف: جدول لم يستعمل بعد يرمي استثناء يبيض الشاشة،
     * ورقم ناقص اهون.
     */
    public function track_stats()
    {
        $this->ensure_schema();

        $slots = $this->rows(
            'SELECT `track_id`,
                    SUM(CASE WHEN `status` = "open" AND `starts_at` >= NOW() THEN 1 ELSE 0 END) open_slots,
                    COUNT(DISTINCT `teacher_id`) teachers_open
               FROM `availability_slots`
              WHERE `kind` = ? GROUP BY `track_id`', array(self::KIND));

        $ses = $this->rows(
            'SELECT `track_id`,
                    COUNT(*) total,
                    SUM(CASE WHEN `status` IN ("requested","awaiting_payment") THEN 1 ELSE 0 END) pending,
                    SUM(CASE WHEN `status` IN ("confirmed","live") THEN 1 ELSE 0 END) upcoming,
                    SUM(CASE WHEN `status` = "completed" THEN 1 ELSE 0 END) completed,
                    COALESCE(SUM(CASE WHEN `paid_at` IS NOT NULL THEN `price_halalas` ELSE 0 END), 0) gross
               FROM `tutoring_sessions`
              WHERE `kind` = ? GROUP BY `track_id`', array(self::KIND));

        $by = array();
        foreach ($slots as $r) $by[(int) $r['track_id']] = array(
            'open_slots' => (int) $r['open_slots'], 'teachers_open' => (int) $r['teachers_open']);
        foreach ($ses as $r) {
            $id = (int) $r['track_id'];
            $by[$id] = (isset($by[$id]) ? $by[$id] : array()) + array(
                'total' => (int) $r['total'], 'pending' => (int) $r['pending'],
                'upcoming' => (int) $r['upcoming'], 'completed' => (int) $r['completed'],
                'gross' => (int) $r['gross']);
        }

        $out = array();
        foreach ($this->all() as $id => $t) {
            $out[$id] = $t + array(
                'open_slots'    => 0, 'teachers_open' => 0, 'total' => 0,
                'pending'       => 0, 'upcoming' => 0, 'completed' => 0, 'gross' => 0,
                'teachers'      => count($t['teacher_ids']),
                'pricing'       => $this->pricing_for($id, 0),
            );
            if (isset($by[$id])) $out[$id] = array_merge($out[$id], $by[$id]);
        }
        return $out;
    }

    /* =====================================================================
       TQ-FND-PACK — الباقات: ما يباع، وبكم، ولمن
       ===================================================================== */

    /**
     * الباقات كلها، مفتاحها معرفها — وقراءة واحدة لكل طلب.
     *
     * صفحة المسار تسأل عن باقاته، وبطاقة الطالب تسأل عن باقته، وشاشة
     * اللوحة تسأل عن كلها — واستعلام لكل سؤال يجعل صفحة فيها ثلاث بطاقات
     * ثلاثة استعلامات لجدول فيه خمسة صفوف.
     */
    public function packs($fresh = false)
    {
        if (self::$pack_cache !== null && !$fresh) return self::$pack_cache;

        $out = array();
        try {
            $this->ensure_schema();
            $rows = $this->db->order_by('`order`', 'ASC', false)
                             ->order_by('sessions', 'ASC')->order_by('id', 'ASC')
                             ->get(self::PACKS)->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); $rows = array(); }

        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[$id] = array(
                'id'       => $id,
                'track_id' => (int) $r['track_id'],
                'name'     => (string) $r['name_ar'],
                'tagline'  => (string) ($r['tagline'] ?? ''),
                'sessions' => max(1, (int) $r['sessions']),
                'price'    => max(0, (int) $r['price_halalas']),
                /* والفارغ غير الصفر هنا كما في المسار: فارغ «خذ ما تحتك»،
                   وصفر «لا نصيب بقرار». */
                'percent'  => ($r['teacher_percent'] === null || $r['teacher_percent'] === '')
                              ? null : (float) $r['teacher_percent'],
                'days'     => max(0, (int) $r['validity_days']),
                'featured' => (int) ($r['featured'] ?? 0) === 1,
                'active'   => (int) ($r['active'] ?? 0) === 1,
                'order'    => (int) ($r['order'] ?? 0),
            );
        }
        return self::$pack_cache = $out;
    }

    /** باقة بمعرفها — و`null` لا مصفوفة فارغة. */
    public function pack($id)
    {
        $all = $this->packs();
        return isset($all[(int) $id]) ? $all[(int) $id] : null;
    }

    /**
     * باقات مسار بعينه — المعروضة وحدها، مرتبة بعدد حصصها.
     *
     * والترتيب بالعدد مقصود: من يقرأ «٤ حصص» ثم «٨ حصص» يرى التدرج،
     * ومن يقرأهما مقلوبتين يقارن بلا سلم.
     */
    public function packs_of_track($track_id)
    {
        $out = array();
        foreach ($this->packs() as $id => $p) {
            if (!$p['active'] || (int) $p['track_id'] !== (int) $track_id) continue;
            $o = $this->pack_offer($id);
            if ($o['sellable']) $out[$id] = $o;
        }
        return $out;
    }

    /** هل في المنصة باقة معروضة اصلا؟ وبلا واحدة لا شيء يتغير في اي شاشة. */
    public function packs_enabled()
    {
        foreach ($this->packs() as $id => $p) {
            if (!$p['active']) continue;
            $o = $this->pack_offer($id);
            if ($o['sellable']) return true;
        }
        return false;
    }

    /**
     * **العرض من مصدره الواحد** — `offer()` الكتاب والكورس نفسها بوجه ثالث.
     *
     * صفحة المسار، وشاشة الطالب، وشاشة التأكيد، واللوحة، وولي الأمر،
     * **ومحرك الشراء نفسه** كلها تقرأ من هنا — فما يعد به الزر هو ما
     * تقيده الفاتورة بالهللة. وست نسخ من قاعدة سعر واحدة تجعل الشاشة
     * تعد بـ٢٠٠ والفاتورة تطلب ٢٤٠.
     *
     * وترد `sellable` مفتاحا يفرع عليه، و`why` نصا عربيا يعرض للمسؤول:
     * باقة لا تظهر لطالب لا تظهر لاسباب مختلفة تقود الى افعال مختلفة،
     * وشاشة تقول «لا تعرض» بلا سبب تترك من يقرؤها يخمن.
     *
     * @return array sellable · reason · why · price · unit · list · save
     *               · save_pct · sessions · days · percent · track
     */
    public function pack_offer($pack)
    {
        if (!is_array($pack)) $pack = $this->pack($pack);

        $none = array(
            'sellable' => false, 'reason' => 'missing', 'why' => t('لا باقة بهذا الرقم.'),
            'id' => 0, 'name' => '', 'tagline' => '', 'sessions' => 0, 'price' => 0,
            'unit' => 0, 'list' => 0, 'save' => 0, 'save_pct' => 0, 'days' => 0,
            'percent' => 0.0, 'track_id' => 0, 'track' => null, 'featured' => false,
        );
        if (!$pack) return $none;

        $track = $this->track((int) $pack['track_id']);
        $unit  = $this->pricing_for((int) $pack['track_id'], 0);

        /* السعر المشطوب **مشتق لا مخزن**: مجموع افراد الحصص بسعر المسار
           اليوم. وعمود ثان يحمل «كان ٤٠٠» يفترق عن سعر المسار اول ما
           يعدل، فتقرأ البطاقة توفيرا لا وجود له — وهو عطل TQ-PLAN-CYCLE
           نفسه («رقم يحسب هنا مرة وهناك مرة»). */
        $list = ((int) $unit['price']) * (int) $pack['sessions'];
        $save = max(0, $list - (int) $pack['price']);

        $out = array(
            'id'       => (int) $pack['id'],
            'name'     => (string) $pack['name'],
            'tagline'  => (string) $pack['tagline'],
            'sessions' => (int) $pack['sessions'],
            'price'    => (int) $pack['price'],
            'unit'     => (int) round(((int) $pack['price']) / max(1, (int) $pack['sessions'])),
            'list'     => $list,
            'save'     => $save,
            'save_pct' => $list > 0 ? (int) round($save * 100 / $list) : 0,
            'days'     => (int) $pack['days'],
            /* النصيب: نصيب الباقة، ثم نصيب المسار، ثم العام — والترتيب
               هو ترتيب `pricing_for()` نفسه، ونسخة ثانية منه تفترق. */
            'percent'  => $pack['percent'] === null ? (float) $unit['percent'] : (float) $pack['percent'],
            'track_id' => (int) $pack['track_id'],
            'track'    => $track,
            'featured' => !empty($pack['featured']),
            'single'   => (int) $unit['price'],
        );

        if (!$pack['active']) {
            return $out + array('sellable' => false, 'reason' => 'off',
                                'why' => t('الباقة موقوفة، فلا تعرض ولا تشترى.'));
        }
        if (!$track) {
            return $out + array('sellable' => false, 'reason' => 'no_track',
                                'why' => t('الباقة بلا مسار — اختر لها مسارا قبل عرضها.'));
        }
        if (!$track['active']) {
            return $out + array('sellable' => false, 'reason' => 'track_off',
                                'why' => t('مسار الباقة موقوف، فلا تعرض الباقة معه.'));
        }
        if ((int) $pack['price'] <= 0) {
            /* باقة بلا ثمن ليست هدية: هي صف نسي مسؤوله ان يسعره، وعرضها
               يفتح ست حصص مجانا لكل من يفتح الصفحة. */
            return $out + array('sellable' => false, 'reason' => 'no_price',
                                'why' => t('الباقة بلا سعر — اكتب لها ثمنا قبل عرضها.'));
        }
        if (!$track['teacher_ids']) {
            return $out + array('sellable' => false, 'reason' => 'no_teacher',
                                'why' => t('لا معلم مسند الى مسار الباقة، فرصيدها لا يحجز به شيء.'));
        }

        return $out + array('sellable' => true, 'reason' => 'ok', 'why' => t('معروضة للبيع.'));
    }

    /**
     * ثمن الحصة رقم كذا من باقة — والمجموع يساوي المدفوع **بالضبط**.
     *
     * ٢٠٠ ريال على ست حصص تساوي ٣٣٫٣٣ ولا تساوي ٣٣٫٣٣ ست مرات: الفارق
     * هللتان تضيعان من نصيب المعلم او تخترعان من لا شيء. فالقسمة صحيحة
     * وباقيها يوزع على **الاوائل**، تماما كما توزع `allocate()` بواقي
     * وعاء الباقة — والدليل ان `sum(unit_of(i)) == total` لكل i.
     *
     * والاشتقاق من الترتيب لا من عمود: الحصة تعرف رقمها من عدد ما استهلك
     * قبلها، وعمود يحمل ثمنها يفترق عن القسمة اول ما يلغى حجز في الوسط.
     */
    public function unit_of($total, $count, $index)
    {
        $total = max(0, (int) $total);
        $count = max(1, (int) $count);
        $index = max(0, (int) $index);

        $base = intdiv($total, $count);
        $rem  = $total - ($base * $count);
        return $base + ($index < $rem ? 1 : 0);
    }

    /* =====================================================================
       الرصيد
       ===================================================================== */

    /**
     * ما يملكه هذا الطالب من ارصدة باقات سارية.
     *
     * **والرصيد مستنتج لا معدود** (انظر `$SPENT`): المستهلك هو عدد
     * الحصص الحية والمنتهية المنسوبة الى الصف، والمعتذر عنه يعود بلا
     * سطر يكتب. فاستثناء يبتلع في مسار الالغاء لا يترك طالبا خصمت منه
     * حصة لم تقع.
     *
     * @param int $track_id مسار بعينه، او صفر لكل المسارات
     * @return array صفوف: sub_id · pack_id · name · track_id · track_name
     *               · total · used · left · ends_at · days_left
     */
    public function credits($user_id, $track_id = 0)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return array();
        $this->ensure_schema();

        try {
            $this->db->select('id, pack_id, pack_sessions, ends_at, started_at, price')
                     ->from('subscriptions')
                     ->where('user_id', $user_id)
                     ->where('pack_id >', 0)
                     ->where_in('status', array('active', 'cancelled'))
                     ->order_by('ends_at IS NULL', '', false)
                     ->order_by('ends_at', 'ASC');
            $subs = $this->db->get()->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
        if (!$subs) return array();

        $ids = array();
        foreach ($subs as $s) $ids[] = (int) $s['id'];

        /* استعلام واحد للعد لا استعلام لكل صف: طالب له ثلاث باقات يقرأ
           شاشته ثلاث مرات، وشاشة اللوحة تقرأ عشرات. */
        $used = array();
        try {
            $rows = $this->db->select('pack_sub_id, COUNT(*) c')
                             ->from('tutoring_sessions')
                             ->where_in('pack_sub_id', $ids)
                             ->where_in('status', self::$SPENT)
                             ->group_by('pack_sub_id')->get()->result_array();
            foreach ($rows as $r) $used[(int) $r['pack_sub_id']] = (int) $r['c'];
        } catch (Throwable $e) { $this->db->reset_query(); }

        $packs = $this->packs();
        $now   = time();
        $out   = array();

        foreach ($subs as $s) {
            $sid = (int) $s['id'];
            $pid = (int) $s['pack_id'];
            $p   = isset($packs[$pid]) ? $packs[$pid] : null;

            /* مضى اجله وان لم يمر عليه الكرون بعد — القاعدة نفسها في
               `active_subscriptions()`، ونسخة تقرأ الحال وحدها تعطي
               رصيدا انتهى امس. */
            if (!empty($s['ends_at']) && strtotime($s['ends_at']) < $now) continue;

            $tid = $p ? (int) $p['track_id'] : 0;
            if ((int) $track_id > 0 && $tid !== (int) $track_id) continue;

            $total = (int) $s['pack_sessions'];
            if ($total <= 0) $total = $p ? (int) $p['sessions'] : 0;
            $u    = isset($used[$sid]) ? $used[$sid] : 0;
            $left = max(0, $total - $u);

            $out[] = array(
                'sub_id'     => $sid,
                'pack_id'    => $pid,
                /* TQ-PLAN-DELETE — باقة حذفت يقال رقمها لا «باقة»: بالرقم
                   يقابل السجل المالي، والكلمة العامة لا تقابل شيئا. */
                'name'       => $p ? $p['name'] : t('باقة حصص') . ' #' . $pid,
                'track_id'   => $tid,
                'track_name' => $tid > 0 ? $this->name_of($tid) : t('التأسيس'),
                'total'      => $total,
                'used'       => $u,
                'left'       => $left,
                'price'      => (int) $s['price'],
                'unit_next'  => $this->unit_of((int) $s['price'], $total, $u),
                'ends_at'    => $s['ends_at'],
                'days_left'  => empty($s['ends_at']) ? null
                                : max(0, (int) ceil((strtotime($s['ends_at']) - $now) / 86400)),
            );
        }
        return $out;
    }

    /** مجموع ما بقي لهذا الطالب في مسار — سؤال الزر: «أيحجز برصيده؟». */
    public function credit_left($user_id, $track_id)
    {
        $n = 0;
        foreach ($this->credits($user_id, $track_id) as $c) $n += (int) $c['left'];
        return $n;
    }

    /**
     * اي رصيد يخصم منه الحجز التالي؟ **اقربها اجلا** لا اولها شراء.
     *
     * من له باقتان تنتهي احداهما بعد اسبوع والاخرى بعد شهرين يخصم من
     * الاولى: خصم من الابعد يترك الاقرب تنتهي بحصص لم تستعمل وقد دفع
     * ثمنها. والترتيب في الاستعلام (`ends_at ASC` والدائم اخيرا)، فلا
     * شاشة تختار لنفسها.
     *
     * @return array|null صف الرصيد، او `null` لمن لا رصيد له
     */
    public function credit_to_spend($user_id, $track_id)
    {
        foreach ($this->credits($user_id, (int) $track_id) as $c) {
            if ((int) $c['left'] > 0) return $c;
        }
        return null;
    }

    /* =====================================================================
       ارقام الباقات — لشاشة اللوحة
       ===================================================================== */

    /**
     * لكل باقة: كم بيعت، وكم حصلت، وكم حصة استهلكت — **وكم بقي معلقا**.
     *
     * والاخير هو الرقم الذي لا يقرأ في اي شاشة اخرى: حصص بيعت ولم تعط
     * بعد. وهو **التزام على المنصة** لا ايراد صاف — من يقرأ «حصلنا كذا»
     * وحده يظن المال ربحا وقد بقي عليه ان يعطي مقابله.
     */
    public function pack_stats()
    {
        $this->ensure_schema();

        $sold = $this->rows(
            'SELECT s.`pack_id`,
                    COUNT(*) subs,
                    COALESCE(SUM(s.`price`), 0) gross,
                    COALESCE(SUM(s.`pack_sessions`), 0) credits
               FROM `subscriptions` s
              WHERE s.`pack_id` > 0 AND s.`status` IN ("active","cancelled","expired")
              GROUP BY s.`pack_id`');

        $pending = $this->rows(
            'SELECT `pack_id`, COUNT(*) c FROM `subscriptions`
              WHERE `pack_id` > 0 AND `status` = "pending" GROUP BY `pack_id`');

        $spent = $this->rows(
            'SELECT s.`pack_id`, COUNT(*) c
               FROM `tutoring_sessions` t
               JOIN `subscriptions` s ON s.`id` = t.`pack_sub_id`
              WHERE t.`status` IN ("' . implode('","', self::$SPENT) . '")
              GROUP BY s.`pack_id`');

        $by = array();
        foreach ($sold as $r)    $by[(int) $r['pack_id']] = array(
            'subs' => (int) $r['subs'], 'gross' => (int) $r['gross'], 'credits' => (int) $r['credits']);
        foreach ($pending as $r) $by[(int) $r['pack_id']]['pending'] = (int) $r['c'];
        foreach ($spent as $r)   $by[(int) $r['pack_id']]['spent']   = (int) $r['c'];

        $out = array();
        foreach ($this->packs() as $id => $p) {
            $o = $this->pack_offer($id);
            $row = $o + array('subs' => 0, 'gross' => 0, 'credits' => 0, 'pending' => 0, 'spent' => 0);
            if (isset($by[$id])) $row = array_merge($row, $by[$id]);
            $row['open'] = max(0, (int) $row['credits'] - (int) $row['spent']);
            $out[$id] = $row;
        }
        return $out;
    }

    private function rows($sql, $args = array())
    {
        try { return $this->db->query($sql, $args)->result_array(); }
        catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }
}
