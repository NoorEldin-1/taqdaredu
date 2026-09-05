<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-BOOK — الكتاب وحدة بيع رابعة، ومحتوى باقة، ومحتوى معلم.
 *
 * ═══ ما كان ═══
 *
 * الكتاب على هذه المنصة كان **ملحقا تسويقيا لا سلعة ولا محتوى**: صف في
 * `books` بعنوان ومادة ومرحلة وملف PDF، يعرض في `/catalog` بسعر صفر
 * وشارة «تحميل مجاني»، ويقرؤه الطالب في `student/library` بقارئ
 * `pdf.js`. ولا معلم له، ولا سعر، ولا نصيب لأحد فيه، ولا باب يضيفه منه
 * معلم، ولا مراجعة تمر عليه.
 *
 * وثلاثة أبواب مغلقة تترتب على ذلك:
 *
 * ١ — **من يريد الكتاب وحده لا يجد ما يشتريه.** وهو الباب نفسه الذي
 *     فتحه TQ-COURSE-SALE للمادة الواحدة.
 * ٢ — **والمعلم الذي ألف كتابا لا يرفعه.** يرفع الدرس والاختبار
 *     والكورس، وكتابه يصل إلى المسؤول بالبريد ليضعه بيده — أو لا يصل.
 * ٣ — **والباقة تفتح كتبا ولا يقيد عنها أحد شيئا.** الوعاء يقسم على
 *     أوزان الدروس وحدها، فمعلم كتابه في باقة صف يفتح للمشتركين ولا
 *     يظهر له وزن ولا ريال.
 *
 * ═══ والقاعدة الحاكمة: لا محرك ثان ═══
 *
 * هذا الملف يملك **العرض والتأليف** لا دورة الحياة. والشراء المفرد صف
 * `subscriptions` كما هو صف الكورس المفرد (`subscribe_course`) وصف
 * المسار وصف الباقة: بفاتورته، وبنده في `subscription_items` نوعه
 * `book`. فتاب تسوي دفعته بالفرع نفسه (`subscription_id > 0`)، والتحويل
 * البنكي يفعله بالزر نفسه في «الاشتراكات»، وينتهي أجله بـ`expire_due()`
 * نفسها، ويعكسه الاسترداد بالباب نفسه. ونظام ثان كان يحتاج نسخة ثانية
 * من كل واحد منها.
 *
 * ═══ TQ-BOOK-GRADE — والصف هو ما يدخل الكتاب في الباقة ═══
 *
 * `books.category_id` مرحلة (`category`) وهي وحدة **الكتالوج**: بها
 * يرشح الزائر، وهي المفردة المشتركة بين الأنواع الأربعة. والباقة نطاقها
 * **صفوف** (`plans.scope_ids`)، فلا تلتقي بالمرحلة إلا باشتقاق نصي
 * (`stages_of_grade()`) لا يصلح في مسار استحقاق: قاعدة تقرر من يفتح ما
 * دفع ثمنه لا تبنى على مطابقة كلمات في اسم صف.
 *
 * فأضيف `books.grade_id`، وهو **الشرط الوحيد** لدخول الكتاب في باقة:
 * كتاب بصف تفتحه كل باقة تشمل ذلك الصف، وكتاب بلا صف لا تفتحه باقة
 * أبدا — يبقى مجانيا للتحميل كما كان، أو يباع مفردا. وتقال هذه القاعدة
 * في شاشة الكتاب حرفا («بلا صف لا تفتحه باقة»)، فلا يحفظ مسؤول كتابا
 * يظنه في باقته وهو ليس فيها.
 *
 * ═══ وثلاث درجات للكتاب، لا واحدة ═══
 *
 *   مجاني        — `tq_sell = 0` وبلا سعر: يحمل بلا تسجيل كما كان.
 *   يباع مفردا   — `tq_sell = 1` بسعر: يشترى بفاتورته.
 *   في باقة      — له `grade_id`: يفتح لمشترك ذلك الصف.
 *
 * والثلاث تجتمع: كتاب بصف وسعر يفتح لمشترك الباقة **ويشترى مفردا** لمن
 * لا باقة له. وهو حكم الكورس نفسه.
 *
 * ═══ والنسبة نسبتان لوظيفتين ═══
 *
 * · **الشراء المفرد** — نسبة واحدة لمعلم واحد (`books.teacher_id`)،
 *   تحسب هنا وتقيد في `Taqdar_wallet_model::credit_book_sale()`. ولا
 *   وعاء ولا أوزان: البيعة لصاحب الكتاب وحده. وهو حكم
 *   `credit_course_sale()` و`credit_session()` نفسه.
 *
 * · **الباقة** — وزن في وعاء `Taqdar_revenue_model` مع دروس المعلمين
 *   الآخرين، لأن الباقة تفتح محتواهم جميعا. ووزن الكتاب يقاس
 *   **بمعادل الدروس** (`tq_book_weight_lessons`، افتراضه ٣): الوعاء
 *   يقسم بوحدة واحدة، وإقحام «صفحة» بجوار «درس» يجعل كتابا من مئتي
 *   صفحة يبتلع الوعاء كله.
 *
 * · **وكتاب بلا معلم كتاب منصة**: لا وزن في الوعاء ولا قيد في دفتر،
 *   والسعر كله للمنصة. وهو أكثر ما في القاعدة اليوم — كتب المنهج
 *   الرسمي — فالافتراض هو الصمت لا الخطأ.
 *
 * ═══ وبلا مفتاح لا شيء يتغير ═══
 *
 * `tq_book_sales_enabled` مطفأ افتراضا، وحينها ترد `offer()` «لا يباع»
 * لكل كتاب، فتعرض `/books` و`/catalog` و`student/library` ما كانت
 * تعرضه حرفا بحرف: كتبا مجانية تحمل بلا تسجيل. وهي قاعدة تاب نفسها
 * وقاعدة بيع الكورسات نفسها.
 */
class Taqdar_book_model extends CI_Model
{
    /** إصدار المخطط — يرفع متى أضيف عمود، فيركب عند أول قراءة بعده. */
    const SCHEMA_V = '3';   /* ٣ — TQ-BOOK-DRIVE و TQ-BOOK-KIND */

    /** حد النسبة العامة حين لا يكتب المسؤول شيئا. */
    const DEFAULT_PERCENT = 60;

    /** وزن الكتاب في وعاء الباقة، بمعادل الدروس. */
    const DEFAULT_WEIGHT = 3;

    /** الافتراضات العامة. تكتب هنا مرة وتقرأ منها الشاشات كلها. */
    public static $DEFAULTS = array(
        /* مطفأ: بلا مفتاح لا شيء يتغير في صفحة واحدة. */
        'tq_book_sales_enabled'   => 0,
        'tq_book_teacher_percent' => self::DEFAULT_PERCENT,
        /* أجل الشراء المفرد حين لا يحدد الكتاب مدته. صفر = وصول دائم،
           وهو ما يتوقعه من اشترى كتابا بعينه بثمنه. */
        'tq_book_default_days'    => 0,
        /* وزن الكتاب في وعاء الباقة بمعادل الدروس. */
        'tq_book_weight_lessons'  => self::DEFAULT_WEIGHT,
        /* أينشر المعلم كتابه مباشرة؟ مطفأ — والمراجعة هي الأصل، كما في
           `tq_teacher_direct_publish` للدروس. */
        'tq_book_direct_publish'  => 0,
    );

    private static $cfg_cache    = null;
    private static $schema_ready = false;

    /** حالات الكتاب — وهي حالات الدرس نفسها بأسمائها. */
    public static function statuses()
    {
        return tq_t_deep(array(
            'draft'     => array('مسودة',            'muted'),
            'review'    => array('بانتظار المراجعة', 'amber'),
            'published' => array('منشور',            'green'),
            'rejected'  => array('مرفوض',            'red'),
        ));
    }

    /* =====================================================================
       المخطط — يركب وقت التشغيل كأخواته
       ===================================================================== */

    /**
     * أعمدة على `books` وعمود على `subscriptions` وقيمة في `enum`.
     *
     * ولا جدول `book_purchases`: الشراء اشتراك، وجدول ثان يعني مصدري
     * حقيقة لسؤال «أيملك هذا الطالب هذا الكتاب؟» — وهما يفترقان.
     */
    public function install_schema($force = false)
    {
        if (self::$schema_ready && !$force) return false;
        self::$schema_ready = true;

        if (!$force && (string) $this->setting('tq_book_schema_v', '') === self::SCHEMA_V) {
            return false;
        }

        /* CodeIgniter يخبئ أسماء أعمدة كل جدول في الطلب الواحد، فلو فحص
           بعد تعديل في النداء نفسه لقرأ قائمة بائتة وأعاد الإضافة. */
        $this->db->data_cache = array();

        $add = array();
        if (!$this->field('grade_id', 'books')) {
            /* TQ-BOOK-GRADE — الشرط الوحيد لدخول الكتاب في باقة. */
            $add[] = "ADD COLUMN `grade_id` int(10) NOT NULL DEFAULT 0"
                   . " COMMENT 'grade — plan opens the book by this alone; 0 = never'";
        }
        if (!$this->field('teacher_id', 'books')) {
            $add[] = "ADD COLUMN `teacher_id` int(10) NOT NULL DEFAULT 0"
                   . " COMMENT 'owner teacher; 0 = platform book, no share and no weight'";
        }
        if (!$this->field('price', 'books')) {
            /* بالهللات كـ`plans.price` لا بالريال كـ`course.price`:
               الكتاب حقل جديد بلا شاشة تسعير موروثة تكتب فيه، والعمود
               الذي يخزن بالوحدة التي يحسب بها لا يحتاج محولا. */
            $add[] = "ADD COLUMN `price` bigint(20) NOT NULL DEFAULT 0"
                   . " COMMENT 'price in halalas — TQ-BOOK'";
        }
        if (!$this->field('discount_price', 'books')) {
            $add[] = "ADD COLUMN `discount_price` bigint(20) NOT NULL DEFAULT 0"
                   . " COMMENT 'discounted price in halalas; 0 = none'";
        }
        if (!$this->field('tq_sell', 'books')) {
            $add[] = "ADD COLUMN `tq_sell` tinyint(1) NOT NULL DEFAULT 0"
                   . " COMMENT 'sold on its own — a declared trait, not a price side effect'";
        }
        if (!$this->field('tq_teacher_percent', 'books')) {
            /* NULL يعني «خذ العام»، والصفر يعني «صفر بقرار». وعمود
               `NOT NULL DEFAULT 0` يخلطهما فيحرم كل معلم لم يمر عليه
               المسؤول — وهي قاعدة `course.tq_teacher_percent` نفسها. */
            $add[] = "ADD COLUMN `tq_teacher_percent` decimal(5,2) DEFAULT NULL"
                   . " COMMENT 'teacher cut on single sale — NULL means take the global'";
        }
        if (!$this->field('tq_weight', 'books')) {
            $add[] = "ADD COLUMN `tq_weight` int(10) DEFAULT NULL"
                   . " COMMENT 'weight in the plan pool, in lesson equivalents — NULL = global'";
        }
        if (!$this->field('access_days', 'books')) {
            $add[] = "ADD COLUMN `access_days` int(10) NOT NULL DEFAULT 0"
                   . " COMMENT 'single-purchase access in days; 0 = permanent'";
        }
        /* ولا `preview_pages` هنا: معاينة الكتاب تعني تسليم صفحاته
           الأولى وحدها، وتقطيع PDF يحتاج مكتبة لا وجود لها في هذا
           المستودع (لا Composer ولا `vendor/`). والبديل الظاهر —
           تسليم الملف كله ووقف القارئ عند صفحة — يهدي الملف كاملا لكل
           من يفتح لوحة الشبكة، أي أنه ليس معاينة بل بيعا بلا ثمن.
           فالحقل لا يكتب أصلا: عمود يعد بما لا يقع أسوأ من غيابه.
           ومدخل المعاينة يوم تضاف: صفحات تصير صورا وقت الرفع. */
        if (!$this->field('tq_review_note', 'books')) {
            $add[] = "ADD COLUMN `tq_review_note` varchar(500) DEFAULT NULL";
        }
        if (!$this->field('tq_reviewed_at', 'books')) {
            $add[] = "ADD COLUMN `tq_reviewed_at` datetime DEFAULT NULL";
        }
        if (!$this->field('tq_reviewed_by', 'books')) {
            $add[] = "ADD COLUMN `tq_reviewed_by` int(10) NOT NULL DEFAULT 0";
        }
        if (!$this->field('file_size', 'books')) {
            $add[] = "ADD COLUMN `file_size` bigint(20) NOT NULL DEFAULT 0"
                   . " COMMENT 'file bytes — shown to the student before opening'";
        }
        if (!$this->field('last_modified', 'books')) {
            $add[] = "ADD COLUMN `last_modified` int(11) NOT NULL DEFAULT 0";
        }

        /* TQ-BOOK-DRIVE — وضع تخزين ثان، لا بديل عن الاول.
 
           كتب المنهج ١١٣ ملفا وخمسة غيغا وثلث، و٤٥ منها فوق سقف الرفع
           (٤٠ ميغا) — فالرفع المحلي ليس اختيارا مرفوضا بل بابا مغلقا.
 
           و`book_file()` يرفض ما ليس تحت `uploads/` عمدا، لان الرابط
           العاري يوزع الكتاب المدفوع «فيصير الشراء اقتراحا». وذلك السبب
           لا يقوم هنا: هذه كتب وزارة مكتوب على غلافها «يوزع مجانا ولا
           يباع»، و`has_book()` يفتح المجاني للزائر بلا تسجيل اصلا —
           فلا حماية يلتف عليها احد.
 
           **والقاعدة تبقى**: ما يباع يرفع محليا ويمر بالحارس. وهذا
           العمود للمجاني وحده، والعمودان يتعايشان في صف واحد. */
        /* TQ-BOOK-KIND — المجلد فيه ثلاثة انواع لا نوعا واحدا: كتاب
           الطالب، وكراسة النشاط، ودليل المعلم. وهي لا تعرض سواء: من
           يبحث عن كتابه لا يريد دليل معلمه في اول النتائج.

           ولا يشتق من العنوان: الاشتقاق يقرأ نصا كتبه انسان فيخطئ عند
           اول عنوان لا يحمل الكلمة — والوسم يقال مرة عند الادخال.

           والاسم `tq_book_kind` لا `tq_kind`: الثاني مفتاح قائم في
           حمولة طابور المراجعة (`:1445`) معناه «نوع الكيان = كتاب»،
           وعمود يحمل اسمه يقرؤه من يقرأ ذاك فيظنهما واحدا. */
        if (!$this->field('tq_book_kind', 'books')) {
            $add[] = "ADD COLUMN `tq_book_kind` varchar(24) NOT NULL DEFAULT 'student'"
                   . " COMMENT 'student|activity|exercise|guide — TQ-BOOK-KIND'";
        }
        if (!$this->field('tq_drive_id', 'books')) {
            $add[] = "ADD COLUMN `tq_drive_id` varchar(64) DEFAULT NULL"
                   . " COMMENT 'Google Drive file id — free books hosted off-server; TQ-BOOK-DRIVE'";
        }
        if ($add) $this->try_sql('ALTER TABLE `books` ' . implode(', ', $add));

        /* الحالتان الجديدتان: `review` و`rejected`. وعمود واحد لا اثنان
           — `books.status` هو ما يرشح به الكتالوج ومكتبة الطالب منذ
           كتبا، وعمود ثان يعني صفحة تعرض ما لا تعرضه أختها. */
        $this->try_sql(
            "ALTER TABLE `books` MODIFY `status`"
          . " enum('draft','review','published','rejected')"
          . " NOT NULL DEFAULT 'draft'"
        );

        $this->try_sql('ALTER TABLE `books` ADD KEY `ix_books_grade` (`grade_id`)');
        $this->try_sql('ALTER TABLE `books` ADD KEY `ix_books_teacher` (`teacher_id`)');
        $this->try_sql('ALTER TABLE `books` ADD KEY `ix_books_sell` (`tq_sell`, `status`)');

        $this->db->data_cache = array();

        if (!$this->field('book_id', 'subscriptions')) {
            $this->try_sql(
                'ALTER TABLE `subscriptions`'
              . ' ADD COLUMN `book_id` int(10) NOT NULL DEFAULT 0'
              . " COMMENT 'book bought on its own — TQ-BOOK' AFTER `course_id`,"
              . ' ADD KEY `ix_sub_book` (`book_id`)'
            );
        }

        /* `entity_type` قائمة محدودة، والقيمة التي ليست فيها تكتب
           فراغا بلا خطأ في وضع MySQL المتساهل — فبند استحقاق يكتب
           ولا يمنح شيئا، ولا سطر يقول لماذا. */
        $this->try_sql(
            "ALTER TABLE `subscription_items` MODIFY `entity_type`"
          . " enum('all','subject','path','course','trial','grade','book')"
          . " NOT NULL DEFAULT 'all'"
        );

        foreach (self::$DEFAULTS as $k => $v) $this->seed_setting($k, (string) $v);

        $this->put_setting('tq_book_schema_v', self::SCHEMA_V);
        $this->db->data_cache = array();
        return true;
    }

    /**
     * اسم ثان تناديه `Taqdar_admin_model::ensure_table()` قبل أي
     * استعلام على وحدة موصوفة — والوحدات تعلن نموذجها في `ensure`،
     * فالاسم عقد لا تفضيل.
     */
    public function ensure_schema()
    {
        return $this->install_schema();
    }

    /** ينفذ تعديل بنية ولا يسقط الطلب إن كان منفذا من قبل. */
    private function try_sql($sql)
    {
        try { $this->db->query($sql); } catch (Throwable $e) { /* منفذ من قبل */ }
        /* TQ-BUILDER-DIRTY — الاستثناء يترك بناء الاستعلام نظيفا خلفه. */
        $this->db->reset_query();
    }

    private function field($col, $table)
    {
        try { return $this->db->field_exists($col, $table); }
        catch (Throwable $e) { return true; }   // لا نضف على المجهول
    }

    private function setting($key, $default = null)
    {
        try {
            $r = $this->db->select('value')->where('key', $key)->get('settings')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return $default; }
        if (!$r) return $default;
        return ($r['value'] === null || trim((string) $r['value']) === '') ? $default : $r['value'];
    }

    /** يكتب المفتاح إن لم يكن — ولا يمس قيمة كتبها مسؤول. */
    private function seed_setting($key, $value)
    {
        try {
            if ($this->db->where('key', $key)->count_all_results('settings') === 0) {
                $this->db->insert('settings', array('key' => $key, 'value' => $value));
            }
        } catch (Throwable $e) { $this->db->reset_query(); }
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

    /* =====================================================================
       الإعدادات العامة
       ===================================================================== */

    /** كل مفاتيح الكتب بقيمها المحدودة — استعلام واحد لا خمسة. */
    public function config($fresh = false)
    {
        if (self::$cfg_cache !== null && !$fresh) return self::$cfg_cache;

        $have = array();
        try {
            foreach ($this->db->select('key, value')
                              ->where_in('key', array_keys(self::$DEFAULTS))
                              ->get('settings')->result_array() as $r) {
                $have[$r['key']] = $r['value'];
            }
        } catch (Throwable $e) { $this->db->reset_query(); }

        $c = array();
        foreach (self::$DEFAULTS as $k => $def) {
            $c[$k] = (!isset($have[$k]) || trim((string) $have[$k]) === '') ? $def : $have[$k];
        }

        /* الحدود تفرض هنا لا في الشاشة: قيمة كتبت مرة بيد أو بسكربت تبقى
           تقرأ إلى الأبد، ونسبة ١٤٠٪ تعطي المعلم أكثر مما قبضت المنصة. */
        return self::$cfg_cache = array(
            'enabled'        => ((string) $c['tq_book_sales_enabled'] === '1'),
            'percent'        => max(0, min(100, (float) $c['tq_book_teacher_percent'])),
            'default_days'   => max(0, min(3650, (int) $c['tq_book_default_days'])),
            'weight'         => max(0, min(500, (int) $c['tq_book_weight_lessons'])),
            'direct_publish' => ((string) $c['tq_book_direct_publish'] === '1'),
        );
    }

    /** يحفظ المفاتيح العامة — upsert، فالمفتاح الغائب ينشأ. */
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

    /** أمفتوح باب البيع المفرد أصلا؟ تسأله الشاشات قبل أن تعرض سعرا. */
    public function enabled()
    {
        $c = $this->config();
        return $c['enabled'];
    }
    /* =====================================================================
       العرض — السؤال الواحد الذي يقرأ منه كل شيء
       ===================================================================== */

    /**
     * ما عرض هذا الكتاب؟ — الجواب الكامل في مصفوفة واحدة.
     *
     * وهو **المصدر الواحد**: صفحة الكتاب و`/books` والكتالوج وشاشة
     * التأكيد ومكتبة الطالب وبوابة المعلم ولوحة الإدارة كلها تقرأ منه.
     * ومحرك الشراء (`Taqdar_billing_model::subscribe_book()`) يقرأ منه
     * هو نفسه لا من نسخة ثانية — فما يعد به الزر هو ما تقيده الفاتورة
     * بالهللة.
     *
     * @param  array|int $book صف الكتاب أو معرفه
     * @return array
     *   sellable   — أيشترى مفردا الآن؟
     *   reason     — ولماذا لا، إن لم يكن (مفتاح ثابت يفرع عليه)
     *   why        — ونصها العربي، لشاشة الإدارة وبوابة المعلم
     *   price      — المخصوم بالهللات (بعد الخصم إن فعل)
     *   list_price — السعر قبل الخصم بالهللات (0 إن لا خصم)
     *   off        — نسبة الخصم المئوية (0 إن لا خصم)
     *   free       — كتاب مجاني: يحمل بلا تسجيل ولا يشترى
     *   days       — أجل الوصول بالأيام (0 = دائم)
     *   percent    — نصيب المعلم %
     *   share      — نصيبه بالهللات · platform — عمولة المنصة
     *   teacher_id — صاحبه (0 = كتاب منصة) · marked — أعلن للبيع؟
     *   in_plans   — أتفتحه باقة؟ (له صف)
     *   weight     — وزنه في وعاء الباقة
     */
    public function offer($book)
    {
        $this->install_schema();

        if (!is_array($book)) $book = $this->book((int) $book);

        $out = array(
            'book_id' => 0, 'title' => '', 'sellable' => false, 'reason' => 'no_book',
            'why' => t('لا كتاب بهذا المعرف.'), 'price' => 0, 'list_price' => 0, 'off' => 0,
            'free' => true, 'days' => 0, 'percent' => 0.0, 'share' => 0, 'platform' => 0,
            'teacher_id' => 0, 'marked' => false, 'in_plans' => false, 'weight' => 0,
            'has_file' => false,
        );
        if (!$book) return $out;

        $cfg = $this->config();

        $out['book_id']    = (int) $book['id'];
        $out['title']      = (string) $book['title'];
        $out['teacher_id'] = (int) $this->col($book, 'teacher_id', 0);
        $out['marked']     = ((int) $this->col($book, 'tq_sell', 0) === 1);
        $out['days']       = $this->access_days($book);
        $out['percent']    = $this->teacher_percent($book);
        $out['weight']     = $this->weight_of($book);
        $out['in_plans']   = ((int) $this->col($book, 'grade_id', 0) > 0);
        $out['has_file']   = (trim((string) $this->col($book, 'file', '')) !== '');

        /* السعر يحسب دائما ولو لم يبع: شاشة الإدارة تريه لتقول «سعرته
           ولم تعلنه»، وهي أنفع من صفر لا يفسر. */
        $list = max(0, (int) $this->col($book, 'price', 0));
        $sale = max(0, (int) $this->col($book, 'discount_price', 0));

        /* الخصم يقبل إن كان **أقل** من الأصل وأكبر من صفر: سعر خصم صفر أو
           أعلى من الأصل يعني حقلا ترك لا خصما قصد، والبيع به يفتح الكتاب
           بلا ثمن أو يبيعه أغلى مما أعلن. */
        if ($sale > 0 && $sale < $list) {
            $out['price']      = $sale;
            $out['list_price'] = $list;
            $out['off']        = (int) round((($list - $sale) / $list) * 100);
        } else {
            $out['price'] = $list;
        }

        /* «مجاني» صفة تحسب من الإعلان لا من خلو السعر: كتاب لم يعلن
           للبيع هو المحتوى المجاني الذي عرضته المنصة منذ كتبت، وحمله
           بلا تسجيل هو ما تعد به صفحته. */
        $out['free'] = (!$out['marked'] || $out['price'] <= 0);

        $split           = $this->split($out['price'], $out['percent']);
        $out['share']    = $split['share'];
        $out['platform'] = $split['platform'];

        /* ترتيب الأسباب هو ترتيب معالجتها: المسؤول يقرأ **أول** ما يمنع
           لا آخره، فيصلحه ثم يقرأ ما بعده. */
        if (!$cfg['enabled']) {
            $out['reason'] = 'disabled';
            $out['why']    = t('بيع الكتب مطفأ في شاشة «بيع الكتب».');
            return $out;
        }
        if (!$out['marked']) {
            $out['reason'] = 'not_marked';
            $out['why']    = t('لم يعلن للبيع — علم «يباع مفردا» في شاشة الكتاب. وحتى ذلك يحمل مجانا.');
            return $out;
        }
        if ((string) $book['status'] !== 'published') {
            $out['reason'] = 'unpublished';
            $out['why']    = t('الكتاب غير منشور، فلا يشترى — ولا يباع ما لا يفتح.');
            return $out;
        }
        if ($out['price'] <= 0) {
            $out['reason'] = 'unpriced';
            $out['why']    = t('أعلن للبيع ولم يسعر — وكتاب بسعر صفر يفتح بلا ثمن.');
            return $out;
        }
        if (!$out['has_file']) {
            /* الكورس لا يباع بلا درس، والكتاب لا يباع بلا ملف: كلاهما
               بيع محتوى لم يرفع. */
            $out['reason'] = 'no_file';
            $out['why']    = t('لا ملف مرفوع — لا يباع كتاب لا صفحة فيه تقرأ.');
            return $out;
        }

        $out['sellable'] = true;
        $out['reason']   = 'ok';
        $out['why']      = t('معروض للبيع المفرد.');
        return $out;
    }

    /** قيمة عمود قد لا يكون ركب بعد — فلا يسقط العرض على قاعدة قديمة. */
    private function col($row, $key, $default = null)
    {
        return (is_array($row) && array_key_exists($key, $row) && $row[$key] !== null)
             ? $row[$key] : $default;
    }

    /** صف الكتاب كاملا. */
    public function book($book_id)
    {
        $this->install_schema();
        try {
            return $this->db->where('id', (int) $book_id)->get('books')->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
    }

    /** الكتاب بمسماه في الرابط، أو بمعرفه حين لا مسمى له. */
    public function book_by_slug($slug)
    {
        $this->install_schema();
        $slug = trim((string) $slug);
        if ($slug === '') return null;
        try {
            $r = $this->db->where('slug', $slug)->get('books')->row_array();
            if (!$r && ctype_digit($slug)) {
                $r = $this->db->where('id', (int) $slug)->get('books')->row_array();
            }
            return $r ?: null;
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
    }

    /**
     * نصيب المعلم من هذا الكتاب: ما كتب له، وإلا العام.
     * والفارغ غير الصفر — انظر رأس الملف.
     */
    public function teacher_percent($book)
    {
        if (!is_array($book)) $book = $this->book((int) $book);
        $cfg = $this->config();
        if (!$book) return $cfg['percent'];

        /* كتاب المنصة بلا نصيب أصلا: لا محفظة يقيد فيها، ونسبة تعرض
           لصاحب لا وجود له تقرأ وعدا. */
        if ((int) $this->col($book, 'teacher_id', 0) <= 0) return 0.0;

        $v = $this->col($book, 'tq_teacher_percent', null);
        if ($v === null || trim((string) $v) === '') return $cfg['percent'];

        return max(0, min(100, (float) $v));
    }

    /**
     * وزن الكتاب في وعاء الباقة، بمعادل الدروس.
     *
     * وكتاب بلا معلم وزنه صفر: الوزن سبب استحقاق، ولا مستحق. ولو حسب
     * له وزن لسحب من الوعاء نصيبا لا يقيد في دفتر أحد — فيقل نصيب
     * المعلمين الحاضرين ولا يظهر أين ذهب الفرق.
     */
    public function weight_of($book)
    {
        if (!is_array($book)) $book = $this->book((int) $book);
        if (!$book) return 0;
        if ((int) $this->col($book, 'teacher_id', 0) <= 0) return 0;

        $v = $this->col($book, 'tq_weight', null);
        if ($v === null || trim((string) $v) === '') {
            $c = $this->config();
            return (int) $c['weight'];
        }
        return max(0, min(500, (int) $v));
    }

    /**
     * أجل الوصول بالأيام للشراء المفرد.
     *
     * وصفر يعني دائما، فلا `ends_at` على الاشتراك — و`expire_due()`
     * تشترط `IS NOT NULL` فلا تلمسه. وتاريخ بعيد مخترع ينتهي يوما
     * ويقفل ما بيع على أنه دائم.
     */
    public function access_days($book)
    {
        if (!is_array($book)) $book = $this->book((int) $book);
        $d = $book ? (int) $this->col($book, 'access_days', 0) : 0;
        if ($d > 0) return $d;

        $c = $this->config();
        return $c['default_days'];   // صفر افتراضا = دائم
    }

    /**
     * يقسم سعرا على نسبة. التقريب مرة واحدة والباقي للمنصة، فلا تضيع
     * هللة ولا تخترع — وهي القاعدة نفسها في `credit_course_sale()`.
     */
    public function split($price_halalas, $percent)
    {
        $price   = max(0, (int) $price_halalas);
        $percent = max(0, min(100, (float) $percent));
        $share   = (int) round($price * $percent / 100);
        return array('price' => $price, 'percent' => $percent,
                     'share' => $share, 'platform' => $price - $share);
    }

    /* =====================================================================
       القوائم — للكتالوج وللوحة ولبوابة المعلم
       ===================================================================== */

    /**
     * عروض الكتب المعلنة للبيع، مفهرسة بالمعرف.
     *
     * الكتالوج يعرض عشرات البطاقات، ونداء `offer()` لكل واحدة يعني
     * استعلاما لكل بطاقة. فهذه تقرأ الصفوف دفعة، وتشكل كل صف من
     * `offer()` **نفسها** — لا من نسخة ثانية من قواعدها.
     */
    public function offers($only_sellable = true)
    {
        $this->install_schema();
        if ($only_sellable && !$this->enabled()) return array();

        try {
            $q = $this->db->from('books');
            if ($only_sellable) $q->where('tq_sell', 1)->where('status', 'published');
            $rows = $q->order_by('tq_order', 'ASC')->order_by('id', 'DESC')->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }

        $out = array();
        foreach ($rows as $r) {
            $o = $this->offer($r);
            if ($only_sellable && !$o['sellable']) continue;
            $out[(int) $r['id']] = $o;
        }
        return $out;
    }

    /** معرفات ما يشترى الآن — للكتالوج ولمرشحاته. */
    public function sellable_ids()
    {
        return array_keys($this->offers(true));
    }

    /**
     * كتب معلم بعينه — لبوابته ولشاشة الإدارة.
     *
     * وترد الحالة والسعر والمبيعات معا: صف القائمة يجيب سؤال من يفتحها
     * («أين كتابي الآن؟ وكم باع؟»)، لا اسما وتاريخا يبحث عما وراءهما
     * في شاشتين أخريين.
     */
    public function books_of($teacher_id, $limit = 200)
    {
        $this->install_schema();
        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return array();

        try {
            $rows = $this->db->query(
                'SELECT b.*,
                        g.`name_ar` AS grade_name,
                        c.`name`    AS cat_name,
                        (SELECT COUNT(*) FROM `subscriptions` s
                          WHERE s.`book_id` = b.`id` AND s.`status` = "active") AS sales
                   FROM `books` b
              LEFT JOIN `grades`   g ON g.`id` = b.`grade_id`
              LEFT JOIN `category` c ON c.`id` = b.`category_id`
                  WHERE b.`teacher_id` = ?
               ORDER BY b.`id` DESC
                  LIMIT ' . (int) $limit, array($teacher_id)
            )->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }

        foreach ($rows as &$r) $r['offer'] = $this->offer($r);
        unset($r);
        return $rows;
    }

    /**
     * كتب صفوف بعينها — وهي التي تفتحها الباقة (TQ-BOOK-GRADE).
     *
     * @param array $grade_ids
     * @param bool  $only_published
     */
    public function books_for_grades($grade_ids, $only_published = true)
    {
        $this->install_schema();
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $grade_ids))));
        if (!$ids) return array();

        try {
            $q = $this->db->from('books')->where_in('grade_id', $ids);
            if ($only_published) $q->where('status', 'published');
            return $q->order_by('tq_order', 'ASC')->order_by('id', 'ASC')->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
    }

    /**
     * المراحل — لمنتقي «المرحلة» في شاشتي المعلم والإدارة.
     *
     * وتقرأ هنا لا من `Taqdar_admin_model::options('categories')`:
     * ذاك نموذج لوحة، وبوابة المعلم لا تحمله — ونداؤه منها يسقط الشاشة
     * بخطأ قاتل على خاصية غير معرفة.
     *
     * @return array معرف => اسم
     */
    public function categories()
    {
        static $c = null;
        if ($c !== null) return $c;

        $c = array();
        try {
            foreach ($this->db->where('parent', 0)->order_by('id', 'ASC')
                              ->get('category')->result_array() as $r) {
                $c[(int) $r['id']] = (string) $r['name'];
            }
        } catch (Throwable $e) { $this->db->reset_query(); }
        return $c;
    }

    /** كل الكتب المنشورة — لنطاق الباقة الشامل (`all`). */
    public function all_published()
    {
        $this->install_schema();
        try {
            return $this->db->from('books')->where('status', 'published')
                            ->order_by('tq_order', 'ASC')->order_by('id', 'ASC')
                            ->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
    }
    /* =====================================================================
       الملكية — «أيملك هذا الطالب هذا الكتاب؟»
       ===================================================================== */

    /**
     * الكتب التي اشتراها هذا الطالب مفردة وما زالت سارية.
     *
     * والسؤال يقرأ من `subscriptions` لا من الاستحقاق العام: قول
     * «اشتريت هذا الكتاب» عن كتاب فتحته باقته خطأ في **العرض** وإن كان
     * الوصول صحيحا. وهذه تجيب سؤال العرض وحده؛ وسؤال الوصول يجيبه
     * `Taqdar_billing_model::has_book()`.
     *
     * @return array معرف الكتاب => صف الاشتراك
     */
    public function owned_by($user_id)
    {
        $this->install_schema();
        $user_id = (int) $user_id;
        if ($user_id <= 0) return array();

        try {
            $rows = $this->db->where('user_id', $user_id)
                             ->where('book_id >', 0)
                             ->where_in('status', array('active', 'cancelled'))
                             ->order_by('id', 'DESC')->get('subscriptions')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }

        $out = array();
        foreach ($rows as $r) {
            if (!empty($r['ends_at']) && strtotime($r['ends_at']) < time()) continue;
            if ($r['status'] === 'cancelled' && empty($r['ends_at']))       continue;
            $bid = (int) $r['book_id'];
            if (!isset($out[$bid])) $out[$bid] = $r;
        }
        return $out;
    }

    /** أاشترى هذا الكتاب بعينه مفردا؟ */
    public function owns($user_id, $book_id)
    {
        $own = $this->owned_by($user_id);
        return isset($own[(int) $book_id]);
    }

    /**
     * اشتراك معلق لهذا الكتاب بفاتورة لم تدفع — إن وجد.
     *
     * تقرأ منه صفحة الكتاب فتقول «فاتورتك صدرت ولم تسدد» وتدل على
     * سدادها، بدل أن تعرض زر شراء ثانيا لمن اشترى قبل دقيقة ولم يحول
     * بعد — فيصدر صفان وفاتورتان، وتبقى إحداهما «غير مدفوعة» أبدا.
     */
    public function pending_of($user_id, $book_id)
    {
        $this->install_schema();
        try {
            $s = $this->db->where('user_id', (int) $user_id)
                          ->where('book_id', (int) $book_id)
                          ->where('status', 'pending')
                          ->order_by('id', 'DESC')->limit(1)
                          ->get('subscriptions')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return null; }
        if (!$s) return null;

        $CI = get_instance();
        $CI->load->model('taqdar_billing_model');
        $inv = $CI->taqdar_billing_model->invoice_of_subscription((int) $s['id']);
        if (!$inv || $inv['status'] !== 'unpaid') return null;

        $s['invoice'] = $inv;
        return $s;
    }

    /* =====================================================================
       المبيعات — للوحة ولبوابة المعلم
       ===================================================================== */

    /**
     * مبيعات الكتب المفردة صفا بصف — مشتريها وحالها وفاتورتها.
     *
     * @param int $teacher_id قصرها على كتب معلم بعينه، أو 0 لكلها
     */
    public function sales($teacher_id = 0, $limit = 200)
    {
        $this->install_schema();

        $where = 's.`book_id` > 0';
        $args  = array();
        if ((int) $teacher_id > 0) { $where .= ' AND b.`teacher_id` = ?'; $args[] = (int) $teacher_id; }

        try {
            return $this->db->query(
                'SELECT s.`id`, s.`user_id`, s.`book_id`, s.`status`, s.`price`,
                        s.`method`, s.`started_at`, s.`ends_at`, s.`created_at`,
                        b.`title` AS book_title, b.`teacher_id`, b.`tq_teacher_percent`,
                        TRIM(CONCAT(COALESCE(u.`first_name`,""), " ",
                                    COALESCE(u.`last_name`,""))) AS buyer_name,
                        TRIM(CONCAT(COALESCE(t.`first_name`,""), " ",
                                    COALESCE(t.`last_name`,""))) AS teacher_name,
                        i.`id` AS invoice_id, i.`invoice_no`,
                        i.`status` AS invoice_status, i.`total` AS invoice_total
                   FROM `subscriptions` s
                   JOIN `books` b ON b.`id` = s.`book_id`
              LEFT JOIN `users` u ON u.`id` = s.`user_id`
              LEFT JOIN `users` t ON t.`id` = b.`teacher_id`
              LEFT JOIN `invoices` i ON i.`id` = (
                        SELECT MAX(`id`) FROM `invoices` WHERE `subscription_id` = s.`id`)
                  WHERE ' . $where . '
               ORDER BY s.`id` DESC
                  LIMIT ' . (int) $limit, $args
            )->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-BOOK sales(): ' . $e->getMessage());
            $this->db->reset_query();
            return array();
        }
    }

    /** أرقام شاشة «بيع الكتب» — استعلام واحد لا خمسة. */
    public function sales_stats()
    {
        $this->install_schema();
        $zero = array('sold' => 0, 'active' => 0, 'pending' => 0,
                      'gross' => 0, 'teachers' => 0, 'listed' => 0);
        try {
            $r = $this->db->query(
                'SELECT COUNT(*) sold,
                        SUM(CASE WHEN s.`status` = "active"  THEN 1 ELSE 0 END) act,
                        SUM(CASE WHEN s.`status` = "pending" THEN 1 ELSE 0 END) pen,
                        COALESCE(SUM(CASE WHEN s.`status` = "active"
                                          THEN s.`price` ELSE 0 END), 0) gross
                   FROM `subscriptions` s WHERE s.`book_id` > 0')->row_array();
            $l = $this->db->query(
                'SELECT COUNT(*) n, COUNT(DISTINCT `teacher_id`) t
                   FROM `books` WHERE `tq_sell` = 1 AND `status` = "published"')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return $zero; }

        return array(
            'sold'     => (int) ($r['sold'] ?? 0),
            'active'   => (int) ($r['act'] ?? 0),
            'pending'  => (int) ($r['pen'] ?? 0),
            'gross'    => (int) ($r['gross'] ?? 0),
            'listed'   => (int) ($l['n'] ?? 0),
            'teachers' => (int) ($l['t'] ?? 0),
        );
    }

    /* =====================================================================
       TQ-BOOK-DELETE — كتاب بيع لا يحذف، يرفع إعلانه
       ===================================================================== */

    /**
     * ما يمنع حذف هذا الكتاب — بالرقم لا بـ«غير مسموح».
     *
     * والمبدأ مبدأ TQ-PLAN-DELETE وTQ-COURSE-SALE-DELETE نفسه، وأشد
     * لزوما هنا: بند الاستحقاق يشير إلى `books.id`، فحذف الصف **يقطع
     * وصولا اشتري** ويترك كشف حساب يقول «كتاب #١٤» لا يعرف أحد ما كان.
     * ومن قرأ «لا يحذف» بلا سبب يظن الشاشة معطلة، ومن قرأ «بيع سبع
     * مرات» يعرف ما يفعل.
     */
    public function delete_blockers($book_id)
    {
        $this->install_schema();
        $book_id = (int) $book_id;
        $out = array('subs' => 0, 'items' => 0, 'entries' => 0, 'why' => array());
        if ($book_id <= 0) return $out;

        $n = function ($sql, $args) {
            try { $r = $this->db->query($sql, $args)->row_array(); return (int) ($r['n'] ?? 0); }
            catch (Throwable $e) { $this->db->reset_query(); return 0; }
        };

        $out['subs'] = $n('SELECT COUNT(*) n FROM `subscriptions` WHERE `book_id` = ?', array($book_id));
        $out['items'] = $n('SELECT COUNT(*) n FROM `subscription_items`
                             WHERE `entity_type` = "book" AND `entity_id` = ?', array($book_id));
        $out['entries'] = $n('SELECT COUNT(*) n FROM `wallet_entries`
                               WHERE `origin` IN (SELECT CONCAT("booksub:", `id`)
                                                    FROM `subscriptions` WHERE `book_id` = ?)',
                             array($book_id));

        /* الرقم عاريا لا بـ`tq_num()`: هذه الجمل تركب في
           `Taqdar_admin_model::$delete_error` وتطبع مهربة
           (`html_escape`)، فوسم `<span class="tq-num">` يقرأه المسؤول
           حرفا حرفا وسط الرسالة. والوسم موضعه العرض لا النموذج. */
        if ($out['subs'] > 0) {
            $out['why'][] = t('بيع ') . (int) $out['subs'] . t(' مرة — وحذفه يقطع وصولا اشتري.');
        }
        if ($out['entries'] > 0) {
            $out['why'][] = t('له ') . (int) $out['entries'] . t(' قيدا في دفاتر المعلمين.');
        }
        return $out;
    }

    /** أيحذف؟ */
    public function may_delete($book_id)
    {
        $b = $this->delete_blockers($book_id);
        return empty($b['why']);
    }

    /**
     * يحذف كتابا لم يبع — ومعه ملفه وغلافه.
     *
     * والحذف مقصور على `uploads/` (`tq_img_drop`): العمود قد يحمل اسم
     * أصل من السمة يشترك فيه غير صف.
     */
    public function delete_book($actor, $book_id)
    {
        $this->install_schema();
        $book_id = (int) $book_id;

        $row = $this->book($book_id);
        if (!$row) return $this->fail(t('لا كتاب بهذا المعرف.'));

        if (!$this->may_delete($book_id)) {
            $b = $this->delete_blockers($book_id);
            return $this->fail(t('لا يحذف هذا الكتاب: ') . implode(' ', $b['why'])
                             . t(' والمخرج أن ترفع عنه «منشور» — فيختفي من كل صفحة عامة ولا يشترى، ويبقى لمن اشتراه.'));
        }

        try {
            $this->db->where('id', $book_id)->delete('books');
        } catch (Throwable $e) {
            $this->db->reset_query();
            return $this->fail(t('تعذر حذف الكتاب.'));
        }

        foreach (array('cover', 'file') as $c) {
            $p = trim((string) $this->col($row, $c, ''));
            if ($p !== '') tq_img_drop($p);
        }

        $this->log($actor, 'book.delete', 'book:' . $book_id,
                   array('title' => (string) $row['title']));
        return array('ok' => true, 'message' => t('حذف الكتاب «') . $row['title'] . t('».'));
    }
    /* =====================================================================
       التأليف — طبقة واحدة تكتبها لوحتان
       ---------------------------------------------------------------------
       TQ-BOOK-SPLIT — الإدارة والمعلم يفعلان **الشيء نفسه** بالكتاب:
       عنوانا ومادة وصفا وملفا وغلافا وسعرا. وكتابة ذلك مرتين تنتهي إلى
       شاشتين تفترقان — حقل يضاف في واحدة وينسى في الأخرى، فيرفع المعلم
       كتابا ترفضه اللوحة. فالقواعد هنا وحدها، والشاشتان تعرضان ولا
       تحكمان. وهو مبدأ `Taqdar_curriculum_model` نفسه.

       ولكل حقل صاحبه:
         any   — يملكه من يحرر الكتاب، معلما كان أو مسؤولا
         admin — قرار عمل لا قرار محتوى: السعر و«يباع مفردا» والنسبة
                 والوزن والصف الذي يدخله في الباقات وصاحب الكتاب.

       والقسمة ليست حجرا: من يضع سعر كتابه بنفسه يضع سعر المنصة، ومن
       يسند كتابه إلى صف بنفسه يضعه في باقة مباعة ويأخذ من وعائها.
       ===================================================================== */

    /**
     * حقول الكتاب، وما يراه هذا الفاعل منها.
     *
     * `col` عمود القاعدة، و`kind` شكل الحقل، و`owner` من يملكه.
     */
    public function book_fields($actor = null)
    {
        $admin = (isset($actor['role']) ? $actor['role'] : '') === 'admin';

        $f = array(
            'title' => array('col' => 'title', 'kind' => 'text', 'owner' => 'any',
                'label' => 'عنوان الكتاب', 'required' => true, 'max' => 190, 'full' => true,
                'section' => 'تعريف الكتاب',
                'hint' => 'كما يقرؤه الطالب. وما بعد الشرطة «—» يقرأ صفا في وجه الغلاف، مثل: الرياضيات — الصف الأول الابتدائي.'),

            'subject' => array('col' => 'subject', 'kind' => 'text', 'owner' => 'any',
                'label' => 'المادة', 'max' => 160,
                'hint' => 'اسم المادة كما تكتب على الغلاف. وهي وجه الغلاف حين لا صورة.'),

            'author' => array('col' => 'author', 'kind' => 'text', 'owner' => 'any',
                'label' => 'المؤلف أو الجهة', 'max' => 160,
                'hint' => 'اترك الحقل فارغا ليقرأ اسمك أنت.'),

            'description' => array('col' => 'description', 'kind' => 'textarea', 'owner' => 'any',
                'label' => 'الوصف', 'full' => true, 'max' => 4000,
                'hint' => 'ما يقرؤه من يفتح صفحة الكتاب قبل أن يقرر.'),

            'category_id' => array('col' => 'category_id', 'kind' => 'category', 'owner' => 'any',
                'label' => 'المرحلة', 'section' => 'أين يظهر',
                'hint' => 'بها يرشح الكتاب في «المواد والبرامج التعليمية» وفي صفحة الكتب.'),

            /* TQ-BOOK-GRADE — الصف قرار عمل لا قرار محتوى: به يدخل
               الكتاب في كل باقة تشمله، فيأخذ صاحبه من وعائها. */
            'grade_id' => array('col' => 'grade_id', 'kind' => 'grade', 'owner' => 'admin',
                'label' => 'الصف',
                'hint' => 'وبه وحده تفتح الباقة هذا الكتاب لمشتركيها، ويدخل صاحبه في قسمة وعائها. وبلا صف لا تفتحه باقة أبدا — يبقى مجانيا أو يباع مفردا.'),

            'tone' => array('col' => 'tone', 'kind' => 'enum', 'owner' => 'any',
                'label' => 'لون الغلاف', 'default' => 'math',
                'options' => array('math' => 'رياضيات', 'arabic' => 'لغة عربية',
                                   'science' => 'علوم', 'islamic' => 'دراسات إسلامية',
                                   'english' => 'لغة إنجليزية'),
                'hint' => 'يستعمل حين لا صورة غلاف مرفوعة.'),

            'cover' => array('col' => 'cover', 'kind' => 'image', 'owner' => 'any',
                'label' => 'صورة الغلاف', 'section' => 'الملفات',
                'bucket' => 'books', 'img_w' => 600, 'img_h' => 840,
                'hint' => 'ترفع من جهازك وتقص تلقائيا إلى نسبة غلاف الكتاب. وبلا صورة يرسم الغلاف باسم المادة ولونها.'),

            'file' => array('col' => 'file', 'kind' => 'doc', 'owner' => 'any',
                'label' => 'ملف الكتاب (PDF)', 'bucket' => 'books', 'max_mb' => 40,
                'hint' => 'يقرأ في بوابة الطالب صفحة صفحة بلا تحميل. وبلا ملف لا يباع الكتاب ولا يفتح — يعرض «قريبا».'),

            'pages' => array('col' => 'pages', 'kind' => 'number', 'owner' => 'any',
                'label' => 'عدد الصفحات', 'default' => 0,
                'hint' => 'يقرأ من الملف تلقائيا حين ترفعه ويترك فارغا.'),

            /* --- قرار العمل: الثمن ونصيبه ------------------------------ */

            'teacher_id' => array('col' => 'teacher_id', 'kind' => 'teacher', 'owner' => 'admin',
                'label' => 'المعلم صاحب الكتاب', 'section' => 'البيع والنصيب',
                'hint' => 'اتركه بلا تحديد ليكون كتاب منصة: السعر كله للمنصة، ولا نصيب ولا وزن في وعاء الباقة.'),

            'tq_sell' => array('col' => 'tq_sell', 'kind' => 'bool', 'owner' => 'admin',
                'label' => 'يباع مفردا', 'default' => 0,
                'hint' => 'صفة تعلن لا نتيجة سعر. وبلا تعليمها يبقى الكتاب مجانيا للتحميل كما كان، مهما كتب في السعر.'),

            'price' => array('col' => 'price', 'kind' => 'money', 'owner' => 'admin',
                'label' => 'السعر', 'default' => 0,
                'hint' => 'بالريال. ويخزن بالهللات.'),

            'discount_price' => array('col' => 'discount_price', 'kind' => 'money', 'owner' => 'admin',
                'label' => 'السعر بعد الخصم', 'default' => 0,
                'hint' => 'اتركه صفرا إن لا خصم. وخصم أعلى من السعر أو مساو له يهمل.'),

            'tq_teacher_percent' => array('col' => 'tq_teacher_percent', 'kind' => 'percent',
                'owner' => 'admin', 'label' => 'نصيب المعلم % (بيع مفرد)',
                'hint' => 'من سعر الشراء المفرد وحده — لا من الباقة. اتركه فارغا ليأخذ النسبة العامة. والفارغ غير الصفر: الصفر قرار.'),

            'tq_weight' => array('col' => 'tq_weight', 'kind' => 'number', 'owner' => 'admin',
                'label' => 'وزن الكتاب في الباقة',
                'hint' => 'بمعادل الدروس: كتاب وزنه ٣ يحسب في وعاء الباقة كثلاثة دروس لصاحبه. اتركه فارغا ليأخذ الوزن العام.'),

            'access_days' => array('col' => 'access_days', 'kind' => 'number', 'owner' => 'admin',
                'label' => 'أجل الوصول (أيام)', 'default' => 0,
                'hint' => 'للشراء المفرد وحده. صفر يعني وصولا دائما.'),

            /* --- العرض ------------------------------------------------- */

            'slug' => array('col' => 'slug', 'kind' => 'text', 'owner' => 'admin',
                'label' => 'المسمى في الرابط', 'ltr' => true, 'max' => 191,
                'section' => 'العرض والترتيب',
                'hint' => 'حروف لاتينية وشرطات. يترك فارغا ليولد من العنوان.'),

            'tq_order' => array('col' => 'tq_order', 'kind' => 'number', 'owner' => 'admin',
                'label' => 'الترتيب', 'default' => 0,
                'hint' => 'الأصغر أولا.'),

            'status' => array('col' => 'status', 'kind' => 'status', 'owner' => 'any',
                'label' => 'الحالة', 'default' => 'draft',
                'hint' => 'ما تعلنه «منشورا» يمر بالمراجعة إن لم تكن الإدارة.'),
        );

        if ($admin) return tq_t_deep($f);

        /* المعلم يرى حقوله وحدها، ويقرأ ما ليس له في لوح «قرار الإدارة»
           بشاشته — فلا يظن أن كتابه بلا سعر لأنه نسي أن يكتبه. */
        $out = array();
        foreach ($f as $k => $v) if ($v['owner'] !== 'admin') $out[$k] = $v;
        return tq_t_deep($out);
    }

    /**
     * هل ينشر هذا الفاعل مباشرة؟
     *
     * الإدارة نعم. والمعلم لا — إلا أن يفتح المسؤول `tq_book_direct_publish`.
     * والافتراض مغلق: ما يرفعه المعلم يمر بالمراجعة، كما يمر درسه.
     */
    public function may_publish($actor)
    {
        if ((isset($actor['role']) ? $actor['role'] : '') === 'admin') return true;
        $c = $this->config();
        return $c['direct_publish'];
    }

    /**
     * يحفظ كتابا — إنشاء أو تعديلا — من أي من اللوحتين.
     *
     * وثلاث قواعد تحكمه:
     *
     * ١ — **يكتب ما أرسل وحده.** حفظ لوح لا يمحو ما في لوح آخر
     *     (TQ-TAB-WIPE): الحقل الذي لم يصل لا يمس عموده.
     *
     * ٢ — **الحالة تمر بـ`may_publish()`.** ما يعلنه المعلم «منشورا»
     *     يحفظ `review`، ولا يرد بخطأ — هو لم يخطئ، وإنما القرار ليس
     *     له. **والخفض يقال**: رسالة «حفظ الكتاب» وحدها تجعله يظن أنه
     *     نشر، وهو ينتظر.
     *
     * ٣ — **والملكية تفحص هنا لا في الشاشة.** معلم يعدل كتابا لا
     *     يملكه ترد عليه الطبقة، فلا يصير تعديل صف تخمين معرف.
     *
     * @param array $actor id · role
     * @param int   $id    صفر = إنشاء
     * @param array $post  · $files
     */
    public function save_book($actor, $id, $post, $files = array())
    {
        $this->install_schema();

        $id    = (int) $id;
        $role  = isset($actor['role']) ? (string) $actor['role'] : '';
        $uid   = (int) (isset($actor['id']) ? $actor['id'] : 0);
        $admin = ($role === 'admin');

        if (!$admin && $role !== 'teacher') {
            return $this->fail(t('لا صلاحية لك على الكتب.'));
        }

        $row = $id > 0 ? $this->book($id) : null;
        if ($id > 0 && !$row) return $this->fail(t('لا كتاب بهذا المعرف.'));

        /* الملكية: المعلم يحرر كتابه هو. */
        if (!$admin && $row && (int) $this->col($row, 'teacher_id', 0) !== $uid) {
            return $this->fail(t('هذا الكتاب ليس لك.'));
        }

        $spec   = $this->book_fields($actor);
        $data   = array();
        $errors = array();
        $drop   = array();

        foreach ($spec as $key => $f) {
            $col  = $f['col'];
            $sent = array_key_exists($key, $post);
            $raw  = $sent ? $post[$key] : null;

            switch ($f['kind']) {

                case 'image':
                    /* ثلاث حالات لا واحدة (TQ-IMG-NORM): رفع جديد يحل
                       ويحذف القديم، و«احذف» يفرغ ويحذف، ولا شيء **لا
                       يمس العمود** — و`$_FILES` تصل فارغة في كل حفظ لا
                       يختار فيه صاحبه ملفا، وهو أكثر الحفظ. */
                    $keep = $row ? (string) $this->col($row, $col, '') : '';
                    if ($this->uploaded($files, $key)) {
                        $up = tq_img_store($files[$key], array(
                            'bucket' => isset($f['bucket']) ? $f['bucket'] : 'books',
                            'w'      => isset($f['img_w']) ? (int) $f['img_w'] : 600,
                            'h'      => isset($f['img_h']) ? (int) $f['img_h'] : 840,
                            'min_w'  => 200, 'min_h' => 260,
                            'prefix' => 'book' . ($id ? '-' . $id : ''),
                        ));
                        if (!$up['ok']) { $errors[] = $up['error']; break; }
                        $data[$col] = $up['path'];
                        if ($keep !== '' && $keep !== $up['path']) $drop[] = $keep;
                    } elseif (!empty($post[$key . '__clear'])) {
                        $data[$col] = '';
                        if ($keep !== '') $drop[] = $keep;
                    }
                    break;

                case 'doc':
                    $keep = $row ? (string) $this->col($row, $col, '') : '';
                    if ($this->uploaded($files, $key)) {
                        $up = tq_doc_store($files[$key], array(
                            'bucket' => isset($f['bucket']) ? $f['bucket'] : 'books',
                            'max_mb' => isset($f['max_mb']) ? (float) $f['max_mb'] : 40,
                            'prefix' => 'book' . ($id ? '-' . $id : ''),
                        ));
                        if (!$up['ok']) { $errors[] = $up['error']; break; }
                        $data[$col]     = $up['path'];
                        $data['file_size'] = (int) $up['size'];
                        if ($keep !== '' && $keep !== $up['path']) $drop[] = $keep;
                    } elseif (!empty($post[$key . '__clear'])) {
                        $data[$col]        = '';
                        $data['file_size'] = 0;
                        if ($keep !== '') $drop[] = $keep;
                    }
                    break;

                case 'money':
                    if (!$sent) break;
                    // يدخل بالريال ويخزن بالهللات — التقريب مرة واحدة هنا
                    $data[$col] = max(0, (int) round(
                        ((float) str_replace(',', '', (string) $raw)) * 100));
                    break;

                case 'percent':
                    if (!$sent) break;
                    /* والفارغ غير الصفر: فراغ يعني «خذ العام»، وصفر
                       يعني «صفرا بقرار». وعمود لا يفرق بينهما يحرم كل
                       معلم لم يمر عليه المسؤول. */
                    $t = trim((string) $raw);
                    $data[$col] = ($t === '') ? null : max(0, min(100, (float) $t));
                    break;

                case 'number':
                    if (!$sent) break;
                    $t = trim((string) $raw);
                    if ($col === 'tq_weight') {
                        $data[$col] = ($t === '') ? null : max(0, min(500, (int) $t));
                    } else {
                        $data[$col] = max(0, (int) $t);
                    }
                    break;

                case 'bool':
                    if (!$sent) break;
                    $data[$col] = ((string) $raw === '1' || $raw === 1 || $raw === true) ? 1 : 0;
                    break;

                case 'enum':
                    if (!$sent) break;
                    $data[$col] = isset($f['options'][(string) $raw])
                                ? (string) $raw : (string) $f['default'];
                    break;

                case 'category':
                case 'grade':
                case 'teacher':
                    if (!$sent) break;
                    $data[$col] = max(0, (int) $raw);
                    break;

                case 'status':
                    if (!$sent) break;
                    $st = (string) $raw;
                    if (!isset(self::statuses()[$st])) $st = 'draft';
                    $data[$col] = $st;
                    break;

                case 'textarea':
                    if (!$sent) break;
                    $data[$col] = $this->cut(trim((string) $raw),
                                             isset($f['max']) ? (int) $f['max'] : 4000);
                    break;

                default: // text
                    if (!$sent) break;
                    $v = trim(preg_replace('/\s+/u', ' ', (string) $raw));
                    $data[$col] = $this->cut($v, isset($f['max']) ? (int) $f['max'] : 190);
                    break;
            }
        }

        if ($errors) return $this->fail($errors);

        /* --- ما لا يأتي من حقل ---------------------------------------- */

        $title = array_key_exists('title', $data) ? (string) $data['title']
               : ($row ? (string) $row['title'] : '');
        if ($this->len($title) < 3) {
            return $this->fail(t('اكتب عنوان الكتاب — ثلاثة أحرف على الأقل.'));
        }

        /* المعلم صاحب كتابه بحكم إنشائه، ولا يسأل عنه: الحقل `admin`،
           وكتاب ينشئه معلم بلا صاحب يعني معلما يؤلف ولا يقيد له شيء. */
        if (!$admin && $id === 0) $data['teacher_id'] = $uid;

        /* المسمى في الرابط: ما كتب، وإلا يولد من العنوان. وهو **فريد**
           — و`book_by_slug()` تقرأ أول ما تجد، فمسميان متطابقان يجعلان
           رابط أحدهما يفتح الآخر. */
        $slug = array_key_exists('slug', $data) ? (string) $data['slug']
              : ($row ? (string) $this->col($row, 'slug', '') : '');
        $slug = $this->clean_slug($slug !== '' ? $slug : $title);
        if ($slug === '') $slug = 'book';
        $data['slug'] = $this->unique_slug($slug, $id);

        /* الحالة تمر بالحارس، والخفض يقال. */
        $note = '';
        if (array_key_exists('status', $data) && $data['status'] === 'published'
            && !$this->may_publish($actor)) {
            $data['status'] = 'review';
            $note = ' ' . t('وهو الآن بانتظار مراجعة الإدارة قبل أن يظهر للطلاب.');
        }
        /* وما رد إلى صاحبه ثم عدل يعود إلى الطابور لا يبقى «مرفوضا». */
        if ($row && (string) $this->col($row, 'status', '') === 'rejected'
            && (!array_key_exists('status', $data) || $data['status'] === 'rejected')) {
            $data['status'] = $this->may_publish($actor) ? 'draft' : 'review';
        }

        $data['last_modified'] = time();

        /* --- الكتابة --------------------------------------------------- */

        try {
            if ($id > 0) {
                $this->db->where('id', $id)->update('books', $data);
            } else {
                $data['date_added'] = time();
                if (!isset($data['status'])) {
                    $data['status'] = $this->may_publish($actor) ? 'draft' : 'draft';
                }
                $this->db->insert('books', $data);
                $id = (int) $this->db->insert_id();
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-BOOK save_book(): ' . $e->getMessage());
            return $this->fail(t('تعذر حفظ الكتاب.'));
        }

        /* عدد الصفحات يقرأ من الملف حين يترك فارغا — TQ-PROBE بوجهه
           الثاني: القراءة اقتراح لا فرض، تملأ الفارغ ولا تمس ما كتب. */
        $fresh = $this->book($id);
        if ($fresh && (int) $this->col($fresh, 'pages', 0) <= 0
            && trim((string) $this->col($fresh, 'file', '')) !== '') {
            $n = tq_doc_pages((string) $fresh['file']);
            if ($n > 0) {
                try { $this->db->where('id', $id)->update('books', array('pages' => $n)); }
                catch (Throwable $e) { $this->db->reset_query(); }
            }
        }

        foreach ($drop as $p) tq_img_drop($p);

        $this->log($actor, $row ? 'book.update' : 'book.create', 'book:' . $id,
                   $data, $row);

        /* كتاب نشر أو رفع عنه النشر يبلغ من يملك نطاقه: مكتبة الطالب
           تقرأ حيا فلا تجسيد، لكن الكتالوج له كاشه. */
        return array('ok' => true, 'id' => $id,
                     'message' => ($row ? t('حفظت تعديلات الكتاب.') : t('أضيف الكتاب.')) . $note);
    }

    /** أوصل ملف فعلا في هذا الحفظ؟ */
    private function uploaded($files, $key)
    {
        return isset($files[$key]) && is_array($files[$key])
            && (int) $files[$key]['error'] !== UPLOAD_ERR_NO_FILE
            && (string) $files[$key]['name'] !== '';
    }

    /** مسمى لاتيني للرابط. */
    private function clean_slug($raw)
    {
        $s = strtolower(trim((string) $raw));
        if (function_exists('slugify')) {
            $s = slugify($s);
        } else {
            $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
        }
        $s = trim(preg_replace('/-+/', '-', (string) $s), '-');
        return $this->cut($s, 191);
    }

    /** يضمن فرادة المسمى — والمكرر يلحق به رقمه. */
    private function unique_slug($slug, $skip_id = 0)
    {
        $base = $slug;
        $i    = 1;
        for ($n = 0; $n < 50; $n++) {
            try {
                $q = $this->db->where('slug', $slug);
                if ((int) $skip_id > 0) $q->where('id !=', (int) $skip_id);
                $hit = $q->count_all_results('books');
            } catch (Throwable $e) { $this->db->reset_query(); return $slug; }
            if ($hit === 0) return $slug;
            $i++;
            $slug = $base . '-' . $i;
        }
        return $base . '-' . time();
    }
    /* =====================================================================
       المراجعة — الطابور نفسه لا طابور ثان
       ---------------------------------------------------------------------
       TQ-BOOK-REVIEW. `save_book()` تحول ما يعلنه المعلم `published`
       إلى `review` بحكم `may_publish()`، وشاشته تقول له «بانتظار مراجعة
       الإدارة». ولو لم يكن في اللوحة ما يقرأ ذلك لجلس الكتاب في
       `review` إلى الأبد والمعلم ينتظر قرارا لا يعلم أحد أنه مطلوب —
       وهو عين TQ-COURSE-REVIEW قبل أن يصلح.

       فـ`Taqdar_curriculum_model::pending()` تنادي `pending_books()`،
       و`pending_count()` تنادي `pending_count_books()`، و`approve()`
       و`reject()` تفرعان على `entity = 'book'` إلى ما هنا. والقرار في
       هذا الملف لأن الكتاب شأنه، والطابور هناك لأن المسؤول يقرأ طابورا
       واحدا لا ثلاثة.
       ===================================================================== */

    /** الكتب التي تنتظر قرارا — بصيغة صف الطابور نفسها. */
    public function pending_books($limit = 200)
    {
        $this->install_schema();
        try {
            $rows = $this->db->query(
                'SELECT b.`id`, b.`title`, b.`subject`, b.`pages`, b.`file`, b.`cover`,
                        b.`date_added`, b.`last_modified`, b.`teacher_id`,
                        b.`grade_id`, b.`category_id`, b.`price`, b.`tq_sell`,
                        g.`name_ar` AS grade_name, c.`name` AS cat_name,
                        TRIM(CONCAT(COALESCE(u.`first_name`,""), " ",
                                    COALESCE(u.`last_name`,""))) AS author_name
                   FROM `books` b
              LEFT JOIN `users`    u ON u.`id` = b.`teacher_id`
              LEFT JOIN `grades`   g ON g.`id` = b.`grade_id`
              LEFT JOIN `category` c ON c.`id` = b.`category_id`
                  WHERE b.`status` = "review"
               ORDER BY COALESCE(NULLIF(b.`last_modified`,0), b.`date_added`) ASC
                  LIMIT ' . (int) $limit
            )->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-BOOK pending_books(): ' . $e->getMessage());
            $this->db->reset_query();
            return array();
        }

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'kind'        => 'book',
                'entity'      => 'book',
                'entity_id'   => (int) $r['id'],
                'revision_id' => 0,
                'title'       => (string) $r['title'],
                'course_id'   => 0,
                'course'      => (string) $r['subject'],
                'section'     => (string) $r['cat_name'],
                'author'      => trim((string) $r['author_name']),
                'tq_kind'     => 'book',
                'objectives'  => 0,
                'duration'    => '',
                'pages'       => (int) $r['pages'],
                'has_file'    => (trim((string) $r['file']) !== ''),
                'grade'       => (string) $r['grade_name'],
                'subject'     => (string) $r['subject'],
                'price'       => (int) $r['price'],
                'sell'        => ((int) $r['tq_sell'] === 1),
                'linked'      => ((int) $r['grade_id'] > 0),
                'at'          => (int) ($r['last_modified'] ?: $r['date_added']),
                'note'        => '',
            );
        }
        return $out;
    }

    /** كم كتابا ينتظر — للشارة في الشريط. */
    public function pending_count_books()
    {
        try {
            $r = $this->db->query(
                'SELECT COUNT(*) n FROM `books` WHERE `status` = "review"')->row_array();
            return (int) ($r['n'] ?? 0);
        } catch (Throwable $e) { $this->db->reset_query(); return 0; }
    }

    /**
     * يعتمد كتابا ينتظر.
     *
     * والاعتماد ينشر ولا يبيع: «يباع مفردا» وسعره قرار مال يبقى بيد
     * الإدارة في شاشة الكتاب — واعتماد يبيع بضغطة يجعل كل كتاب يمر
     * سلعة بسعر لم يقرأه أحد.
     */
    public function approve_book($actor, $book_id)
    {
        $this->install_schema();
        if ((isset($actor['role']) ? $actor['role'] : '') !== 'admin') {
            return $this->fail(t('الاعتماد قرار إدارة.'));
        }

        $row = $this->book((int) $book_id);
        if (!$row) return $this->fail(t('لا كتاب بهذا المعرف.'));
        if ((string) $row['status'] !== 'review') {
            $st = self::statuses();
            $lb = isset($st[(string) $row['status']]) ? $st[(string) $row['status']][0]
                                                      : (string) $row['status'];
            return $this->fail(t('هذا الكتاب ليس في انتظار المراجعة — حالته «') . $lb . '».');
        }

        try {
            $this->db->where('id', (int) $book_id)->update('books', array(
                'status'         => 'published',
                'tq_review_note' => null,
                'tq_reviewed_at' => date('Y-m-d H:i:s'),
                'tq_reviewed_by' => (int) (isset($actor['id']) ? $actor['id'] : 0),
                'last_modified'  => time(),
            ));
        } catch (Throwable $e) {
            $this->db->reset_query();
            return $this->fail(t('تعذر اعتماد الكتاب.'));
        }

        $this->log($actor, 'book.approve', 'book:' . (int) $book_id,
                   array('status' => 'published'));
        $this->notify_author((int) $this->col($row, 'teacher_id', 0), (string) $row['title'],
                             true, '');

        $msg = t('نشر الكتاب «') . $row['title'] . t('».');
        if ((int) $this->col($row, 'grade_id', 0) > 0) {
            $msg .= ' ' . t('ويفتح الآن لمشتركي باقات صفه.');
        } else {
            /* الصمت هنا يجعل المسؤول يظن أنه أدخل الكتاب في الباقات وهو
               لم يدخله — وهو عين ما تقوله شاشة الكورس عن الصف والمادة. */
            $msg .= ' ' . t('وهو بلا صف، فلا تفتحه باقة — أسنده إلى صف من شاشة الكتاب إن أردت ذلك.');
        }
        return array('ok' => true, 'message' => $msg);
    }

    /**
     * يرد كتابا إلى صاحبه بسببه.
     *
     * وينزل إلى `rejected` لا إلى `draft`: خلاف الكورس، لـ`books` عمود
     * يحمل ملاحظة المراجعة (`tq_review_note`) — فصاحبه يقرأ السبب في
     * شاشته لا في إشعار يمضي. **والرفض يطلب سببه**: «مرفوض» وحدها
     * تعيد الكتاب بلا ما يفعله صاحبه، فيعيد إرساله كما هو.
     */
    public function reject_book($actor, $book_id, $reason)
    {
        $this->install_schema();
        if ((isset($actor['role']) ? $actor['role'] : '') !== 'admin') {
            return $this->fail(t('الرفض قرار إدارة.'));
        }

        $reason = trim(preg_replace('/\s+/u', ' ', (string) $reason));
        if ($this->len($reason) < 5) {
            return $this->fail(t('اكتب سبب الرفض — بلا سبب يعيد المعلم إرسال الكتاب كما هو.'));
        }
        $reason = $this->cut($reason, 500);

        $row = $this->book((int) $book_id);
        if (!$row) return $this->fail(t('لا كتاب بهذا المعرف.'));

        try {
            $this->db->where('id', (int) $book_id)->update('books', array(
                'status'         => 'rejected',
                'tq_review_note' => $reason,
                'tq_reviewed_at' => date('Y-m-d H:i:s'),
                'tq_reviewed_by' => (int) (isset($actor['id']) ? $actor['id'] : 0),
                'last_modified'  => time(),
            ));
        } catch (Throwable $e) {
            $this->db->reset_query();
            return $this->fail(t('تعذر رد الكتاب.'));
        }

        $this->log($actor, 'book.reject', 'book:' . (int) $book_id,
                   array('reason' => $reason));
        $this->notify_author((int) $this->col($row, 'teacher_id', 0), (string) $row['title'],
                             false, $reason);

        return array('ok' => true, 'message' => t('رد الكتاب إلى صاحبه مع السبب.'));
    }

    /**
     * يخبر صاحب الكتاب بالقرار.
     *
     * والباب واحد (`Taqdar_admin_model::push_notification`): يكتب الصف
     * أولا ثم يرسل، وفشل القناة لا يبطل القرار. والنوع `content` لا
     * `payment` — `Taqdar_wa_model::$PAY_TYPES` تسمي أنواع المال وحدها،
     * وقرار مراجعة ليس مالا.
     */
    private function notify_author($teacher_id, $title, $ok, $reason)
    {
        if ((int) $teacher_id <= 0) return;
        try {
            $CI = get_instance();
            $CI->load->model('taqdar_admin_model', 'tq_admin_m');
            if (!method_exists($CI->tq_admin_m, 'push_notification')) return;

            $CI->tq_admin_m->push_notification(
                (int) $teacher_id,
                $ok ? t('اعتمد كتابك ونشر') : t('كتابك يحتاج تعديلا'),
                $ok
                    ? t('اعتمدت الإدارة كتاب «') . $title . t('» وصار منشورا، ويقرؤه الطلاب من مكتبتهم.')
                    : t('رد كتاب «') . $title . t('» إليك للتعديل. السبب: ') . $reason,
                'content'
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-BOOK notify: ' . $e->getMessage());
        }
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    private function fail($errors, $extra = array())
    {
        if (!is_array($errors)) $errors = array($errors);
        return array_merge(array('ok' => false, 'errors' => $errors,
                                 'message' => implode(' ', $errors)), $extra);
    }

    private function len($s)
    {
        return function_exists('mb_strlen') ? mb_strlen((string) $s, 'UTF-8') : strlen((string) $s);
    }

    private function cut($s, $n)
    {
        return function_exists('mb_substr') ? mb_substr((string) $s, 0, (int) $n, 'UTF-8')
                                            : substr((string) $s, 0, (int) $n);
    }

    /**
     * سجل التدقيق — ويبتلع عطله: حفظ كتاب لا يوقف لأن سطرا لم يكتب.
     *
     * والعمودان `before` و`after` عليهما `CHECK (json_valid(...))` في
     * المخطط، فالفارغ يجب أن يكون `NULL` لا `''`.
     */
    private function log($actor, $action, $entity, $after = null, $before = null)
    {
        try {
            $this->db->insert('audit_log', array(
                'actor_id' => (int) (isset($actor['id']) ? $actor['id'] : 0),
                'action'   => $action,
                'entity'   => $entity,
                'before'   => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
                'after'    => $after  === null ? null : json_encode($after,  JSON_UNESCAPED_UNICODE),
                'ip'       => $this->input->ip_address(),
                'at'       => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-BOOK audit: ' . $e->getMessage());
        }
    }
}
