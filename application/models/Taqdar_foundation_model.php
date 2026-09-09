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
    const SCHEMA_V = '1';

    /** جدول المسارات. */
    const TABLE = 'tq_foundation_tracks';

    private static $cache = null;

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

        /* الاعمدة على جداول الحصص — يضيفها صاحبها لا هذا النموذج. */
        $this->load->model('taqdar_sessions_model');
        $this->taqdar_sessions_model->install_schema();

        $this->seed();
        $this->put_setting('tq_foundation_schema_v', self::SCHEMA_V);
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

    private function rows($sql, $args = array())
    {
        try { return $this->db->query($sql, $args)->result_array(); }
        catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }
}
