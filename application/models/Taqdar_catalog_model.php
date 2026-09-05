<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * الكتالوج الموحد — كل ما تقدمه المنصة في قائمة واحدة.
 *
 * ═══ لماذا وجد ═══
 *
 * كان لكل نوع صفحته: البرامج في `/plans`، والكتب في `/books`. ومن لا
 * يعرف اسم النوع لا يعرف أي صفحة يفتح — يبحث عن «رياضيات الصف الرابع»
 * فيجد بابين، وفي كل باب مرشح لا يشبه المرشح الذي في الباب الآخر.
 *
 * وهنا مصدر واحد يقرأ الأنواع الأربعة (باقة · برنامج · كورس · كتاب)
 * ويعيدها **بشكل واحد**، فالترشيح والبحث والصفحات تكتب مرة لا أربعا.
 *
 * ═══ لماذا يرشح في PHP لا في SQL ═══
 *
 * الأنواع الأربعة في أربعة جداول لا يجمعها `UNION` معقول: أعمدتها
 * مختلفة، وصفوفها المنشورة كلها بضع عشرات. فالقراءة كاملة مرة، ثم
 * الترشيح والعد في الذاكرة — وهذا ما يجعل **عدادات المرشحات** ممكنة
 * أصلا: عداد كل خيار يحسب على المجموعة نفسها بعد تطبيق بقية المرشحات،
 * وهو استعلام مستقل لكل خيار لو كتب في SQL.
 *
 * والحد الأعلى معروف ومحدود: `paths` خمسة عشر، و`books` ثمانية،
 * و`plans` ستة. ولو صارت ألوفا فالموضع الذي يتغير موضع واحد.
 *
 * ═══ التصنيف مشتق لا مخزن ═══
 *
 * `category` تحمل المراحل الثلاث ومسارين آخرين (القدرات · المهارات
 * الرقمية)، وهي المفردة الوحيدة المشتركة بين الأنواع الأربعة: البرنامج
 * والكتاب يحملان `category_id`، والباقة تحمل `stage` وهو مسمى الفئة
 * نفسه. فالقسم واحد في المرشح لا اثنان.
 */
class Taqdar_catalog_model extends CI_Model
{
    /** ما يعرض في الصفحة الواحدة. */
    const PER_PAGE = 12;

    /** TQ-BOOK-KIND — انواع الكتب بتسمياتها. */
    public static $KINDS = array(
        'student'  => 'كتاب الطالب',
        'activity' => 'كراسة النشاط',
        'exercise' => 'كتاب التمارين',
        'guide'    => 'دليل المعلم',
    );

    /** الأنواع بترتيب ظهورها الافتراضي ومسمياتها. */
    public static function kinds()
    {
        return array(
            'plan'        => array('label' => 'الباقات',   'one' => 'باقة',   'icon' => 'i-card'),
            'path'        => array('label' => 'البرامج',   'one' => 'برنامج', 'icon' => 'i-play'),
            /* الكورس وعاء الدروس في `course` — وهو ما تديره شاشة
               `admin/courses`. وكان لا يقرأ هنا اطلاقا: يقول
               [Taqdar_course_link_model](Taqdar_course_link_model.php) صراحة
               ان «من انشأ كورسا من اللوحة ونشره وجده لا يظهر في `/catalog`»،
               فالجسر الوحيد `paths`. فصار يقرأ بنفسه، ويرث تصنيف برنامجه
               ان كان له برنامج — فيرشح بالصف والمادة والمعلم كما ترشح بقية
               الانواع، لا قائمة ثانية بلا مرشحات. */
            'course'      => array('label' => 'الكورسات',  'one' => 'كورس',   'icon' => 'i-curriculum'),
            'book'        => array('label' => 'الكتب',     'one' => 'كتاب',   'icon' => 'i-book'),
        );
    }

    /** ترتيب النوع في الفرز الافتراضي — أصغر أولا. */
    private static function kind_rank($kind)
    {
        $r = array_keys(self::kinds());
        $i = array_search($kind, $r, true);
        return ($i === false) ? 99 : $i;
    }

    /* ================================================================
       القراءة
       ================================================================ */

    /** ذاكرة الطلب: الصفحة تبني المرشحات وتعد وترشح على المجموعة نفسها. */
    private $items = null;
    private $cats  = null;

    /** الفئات بمسمياتها — مفتاحها المسمى في الرابط. */
    private function categories()
    {
        if ($this->cats !== null) return $this->cats;
        $this->cats = array();
        foreach ($this->db->select('id, slug, name, subtitle')->from('category')
                          ->where('parent', 0)
                          ->order_by('tq_order', 'ASC')->order_by('id', 'ASC')
                          ->get()->result_array() as $r) {
            $slug = ((string) $r['slug'] !== '') ? (string) $r['slug'] : (string) $r['id'];
            $this->cats[(int) $r['id']] = array('slug' => $slug, 'name' => (string) $r['name']);
        }
        return $this->cats;
    }

    /** فئة بمسماها في الرابط — للقراءة العكسية (الباقة تحمل المسمى لا الرقم). */
    private function cat_by_slug($slug)
    {
        foreach ($this->categories() as $id => $c) {
            if ($c['slug'] === (string) $slug) return array('id' => $id) + $c;
        }
        return null;
    }

    /**
     * كل ما ينشر، بشكل واحد.
     *
     * يقرأ مرة لكل طلب: الصفحة تناديه للنتائج **ولعدادات المرشحات**،
     * وقراءته مرتين تعني ضعف الاستعلامات على شيء لا يتغير بينهما.
     */
    public function all()
    {
        if ($this->items !== null) return $this->items;

        $out = array();
        foreach ($this->plans()        as $r) $out[] = $r;
        foreach ($this->paths()        as $r) $out[] = $r;
        foreach ($this->courses()      as $r) $out[] = $r;
        foreach ($this->books()        as $r) $out[] = $r;

        return $this->items = $out;
    }

    /* ---- الباقات ------------------------------------------------- */
    private function plans()
    {
        $rows = $this->db->from('plans')->where('active', 1)->where('scope', 'grade')
                         ->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                         ->get()->result_array();

        $grades = $this->grades();
        $out = array();
        foreach ($rows as $r) {
            $feat = json_decode((string) $r['features'], true);
            $feat = is_array($feat) ? $feat : array();

            $cat  = $this->cat_by_slug((string) $r['stage']);
            $gids = array_values(array_unique(array_filter(array_map('intval',
                        explode(',', (string) $r['scope_ids'])))));
            if (!$gids && (int) $r['scope_id'] > 0) $gids = array((int) $r['scope_id']);

            $out[] = $this->shape(array(
                'kind'     => 'plan',
                'id'       => (int) $r['id'],
                'title'    => (string) $r['name_ar'],
                'blurb'    => (string) $r['note'],
                'image'    => (string) $r['image'],
                'fallback' => 'path-primary',
                'href'     => base_url('plan/' . (string) $r['code']),
                'cat'      => $cat ? $cat['slug'] : '',
                'cat_name' => $cat ? $cat['name'] : tqs_stage_label((string) $r['stage']),
                'grades'   => $gids,
                'price'    => (int) $r['price'],
                'featured' => ((int) $r['featured'] === 1),
                'order'    => (int) $r['order'],
                /* درجة الباقة تسمية تميزها عن أختيها في الشبكة نفسها */
                'tag'      => tqs_bundle_tier((string) $r['name_ar']),
                'stats'    => array(
                    array('i-check', tq_count_units(count($feat), 'ميزة', 'ميزتان', 'ميزتين',
                                                    'مزايا', 'ميزة', null, 'obl', true)),
                    array('i-calendar', ((int) $r['duration_days'] >= 360)
                        ? 'العام الدراسي كاملا' : tq_days((int) $r['duration_days'])),
                ),
                /* الدورة والمرحلة والرمز يسافرون مع العنصر: بطاقة الكتالوج
                   كانت تستخرج الرمز من `href` بـ`basename()` وتخمن الدورة
                   من لا شيء — فتطبع «شهريا» على باقة شهرية سعرها ٩٩٩ وكأنه
                   معادل سنوي. والمصدر عمود، فيمر عمودا. */
                'extra'    => array('features' => $feat, 'code' => (string) $r['code'],
                                    'days'   => (int) $r['duration_days'],
                                    'period' => (string) $r['period'],
                                    'stage'  => (string) $r['stage'],
                                    'image'  => (string) $r['image']),
                'text'     => implode(' ', array_merge(
                    array((string) $r['name_ar'], (string) $r['note'], (string) $r['name_en']),
                    $feat,
                    array_map(function ($g) use ($grades) {
                        return isset($grades[$g]) ? $grades[$g] : '';
                    }, $gids)
                )),
            ));
        }
        return $out;
    }

    /* ---- البرامج ------------------------------------------------- */
    private function paths()
    {
        $rows = $this->db->select('p.id, p.title, p.slug, p.short_description, p.thumbnail, p.badge,
                                   p.category_id, p.grade_id, p.subject_id, p.teacher_id, p.course_id,
                                   p.expected_weeks, p.tq_order,
                                   s.name_ar AS subject_ar, g.name_ar AS grade_ar,
                                   TRIM(CONCAT(COALESCE(u.first_name,""), " ", COALESCE(u.last_name,""))) AS teacher_name,
                                   u.is_public AS teacher_public', false)
                         ->from('paths p')
                         ->join('subjects s', 's.id = p.subject_id', 'left')
                         ->join('grades   g', 'g.id = p.grade_id',   'left')
                         ->join('users    u', 'u.id = p.teacher_id', 'left')
                         ->where('p.status', 'published')
                         /* TQ-CATALOG-CONTENT — ومسار بلا وعاء محتوى لا
                            يسرد هنا. مسارات «قيد الإعداد» المبذورة لكل
                            (صف × مادة) وعد يقال في شجرة صفحة الباقة، أما
                            الكتالوج فيتصفح بحثا عما يشاهد — وبطاقة تفتح
                            على صفحة خالية تقرأ عطبا لا وعدا. */
                         ->where('COALESCE(p.course_id, 0) >', 0)
                         ->order_by('p.tq_order', 'ASC')->order_by('p.id', 'ASC')
                         ->get()->result_array();
        if (!$rows) return array();

        /* عدد الدروس والاختبارات باستعلامين لا باستعلامين لكل برنامج:
           `Taqdar_site_model::materials()` تعد داخل الحلقة، وهي مقبولة
           هناك لأنها تقرأ نوعا واحدا. وهنا الحلقة على الكتالوج كله. */
        $cids = array();
        foreach ($rows as $r) if ((int) $r['course_id'] > 0) $cids[] = (int) $r['course_id'];
        $cids = array_values(array_unique($cids));

        $count = array();
        if ($cids) {
            $q = $this->db->select('course_id,
                                    SUM(CASE WHEN lesson_type = "quiz"
                                              OR COALESCE(video_url, "") <> ""
                                             THEN 1 ELSE 0 END) AS n,
                                    SUM(CASE WHEN lesson_type = "quiz" THEN 1 ELSE 0 END) AS q', false)
                          ->from('lesson')->where_in('course_id', $cids)
                          ->where('COALESCE(`tq_status`, "published") =', 'published')
                          ->group_by('course_id')->get()->result_array();
            foreach ($q as $r) {
                $count[(int) $r['course_id']] = array('n' => (int) $r['n'], 'q' => (int) $r['q']);
            }
        }

        $cats = $this->categories();
        $out  = array();
        foreach ($rows as $r) {
            $cid  = (int) $r['course_id'];
            $st   = isset($count[$cid]) ? $count[$cid] : array('n' => 0, 'q' => 0);
            $cat  = isset($cats[(int) $r['category_id']]) ? $cats[(int) $r['category_id']] : null;
            $slug = ((string) $r['slug'] !== '') ? (string) $r['slug'] : (string) $r['id'];

            $stats = array();
            /* الصفر يخبر عن حالة الاستيراد لا عن المادة، فيغيب حتى يملأ */
            if ($st['n'] > 0) $stats[] = array('i-play',      tq_lessons_word($st['n']));
            if ($st['q'] > 0) $stats[] = array('i-clipboard', tq_exams_word($st['q']));
            if ((int) $r['expected_weeks'] > 0) {
                $stats[] = array('i-clock', tq_count_units((int) $r['expected_weeks'],
                                 'أسبوع', 'أسبوعان', 'أسبوعين', 'أسابيع', 'أسبوعا'));
            }

            $out[] = $this->shape(array(
                'kind'       => 'path',
                'id'         => (int) $r['id'],
                'title'      => (string) $r['title'],
                'blurb'      => (string) $r['short_description'],
                'image'      => (string) $r['thumbnail'],
                'fallback'   => 'subj-math',
                'href'       => base_url('path/' . $slug),
                'cat'        => $cat ? $cat['slug'] : '',
                'cat_name'   => $cat ? $cat['name'] : '',
                'grades'     => ((int) $r['grade_id'] > 0) ? array((int) $r['grade_id']) : array(),
                'grade_name' => (string) $r['grade_ar'],
                'subject'    => (string) $r['subject_ar'],
                'teacher'    => trim((string) $r['teacher_name']),
                'teacher_id' => ((int) $r['teacher_public'] === 1) ? (int) $r['teacher_id'] : 0,
                'badge'      => (string) $r['badge'],
                /* لا سعر على بطاقة برنامج: وحدة البيع الباقة، وسعر هنا
                   يعرض منتجا لم يعد يباع — انظر `site_path.php`. */
                'price'      => -1,
                'order'      => (int) $r['tq_order'],
                'ready'      => ($cid > 0 && $st['n'] > 0),
                'tag'        => (string) $r['grade_ar'],
                'stats'      => $stats,
                'text'       => implode(' ', array((string) $r['title'], (string) $r['short_description'],
                                                   (string) $r['subject_ar'], (string) $r['grade_ar'],
                                                   trim((string) $r['teacher_name']))),
            ));
        }
        return $out;
    }

    /* ---- الكورسات ------------------------------------------------- */

    /**
     * كورسات `course` — وعاء الدروس كما تديره شاشة `admin/courses`.
     *
     * ═══ لماذا يقرأ هنا اصلا ═══
     *
     * `course` لا يحمل صفا ولا مادة، ولا يقرؤه هذا الكتالوج، ولا يعرفه
     * محرك الاشتراكات — الجسر الوحيد `paths.course_id`. فمن انشأ كورسا
     * ونشره ولم يربط له برنامجا وجده لا يظهر في `/catalog` ولا شيء يقول
     * له لماذا. وصفحته `‎/course/<المسمى>/<الرقم>‎` قائمة تفتح وتعمل — ولا
     * باب اليها من الكتالوج.
     *
     * ═══ التصنيف يورث من البرنامج ═══
     *
     * الصف والمادة والمعلم تقرأ من `paths` المربوط بالكورس ان وجد. ولولا
     * ذلك لخرجت بطاقات الكورسات من كل مرشح: تظهر في «الكل» وتختفي متى
     * اختار الزائر صفا او مادة، وهو ما يقرأ عطلا لا ترشيحا.
     *
     * والقراءة باستعلامين لا باستعلام لكل كورس: `LEFT JOIN` على `paths`
     * يضاعف الصف متى كان للكورس برنامجان، فيعد الكورس مرتين في المرشحات.
     */
    private function courses()
    {
        $rows = $this->db->select('c.id, c.title, c.short_description, c.thumbnail, c.category_id,
                                   c.price, c.discount_flag, c.discounted_price, c.is_free_course,
                                   c.level, c.date_added, c.is_top_course, c.creator, c.last_modified,
                                   TRIM(CONCAT(COALESCE(u.first_name,""), " ", COALESCE(u.last_name,""))) AS teacher_name,
                                   u.is_public AS teacher_public', false)
                         ->from('course c')
                         ->join('users u', 'u.id = c.creator', 'left')
                         ->where('c.status', 'active')
                         ->order_by('c.is_top_course', 'DESC')->order_by('c.id', 'DESC')
                         ->get()->result_array();
        if (!$rows) return array();

        $cids = array();
        foreach ($rows as $r) $cids[] = (int) $r['id'];

        /* البرنامج المرتبط: اصغر معرف لكل كورس — وهو ما تختاره
           `Taqdar_course_link_model::path_of()`، فلا يفترق ما يقرؤه
           الكتالوج عما تقرؤه شاشة الكورس. */
        $link = array();
        $prow = $this->db->select('p.course_id, p.grade_id, p.subject_id, p.status, p.slug, p.id,
                                   s.name_ar AS subject_ar, g.name_ar AS grade_ar', false)
                         ->from('paths p')
                         ->join('subjects s', 's.id = p.subject_id', 'left')
                         ->join('grades   g', 'g.id = p.grade_id',   'left')
                         ->where_in('p.course_id', $cids)
                         ->order_by('p.id', 'ASC')
                         ->get()->result_array();
        foreach ($prow as $p) {
            $cid = (int) $p['course_id'];
            if (!isset($link[$cid])) $link[$cid] = $p;   // الاول يفوز — وهو الاصغر معرفا
        }

        /* عدد الدروس والاختبارات باستعلام واحد للكورسات كلها. */
        $count = array();
        $q = $this->db->select('course_id,
                                SUM(CASE WHEN lesson_type = "quiz"
                                          OR COALESCE(video_url, "") <> ""
                                         THEN 1 ELSE 0 END) AS n,
                                SUM(CASE WHEN lesson_type = "quiz" THEN 1 ELSE 0 END) AS q', false)
                      ->from('lesson')->where_in('course_id', $cids)
                      ->where('COALESCE(`tq_status`, "published") =', 'published')
                      ->group_by('course_id')->get()->result_array();
        foreach ($q as $r) {
            $count[(int) $r['course_id']] = array('n' => (int) $r['n'], 'q' => (int) $r['q']);
        }

        /* عروض البيع المفرد دفعة واحدة لا نداء لكل بطاقة — الكتالوج
           يعرض عشرات الصفوف. وترد فارغة متى كان الباب مطفأ، فتعرض
           البطاقات ما كانت تعرضه حرفا بحرف. */
        $CI = get_instance();
        $CI->load->model('taqdar_course_sale_model', 'tq_cs_cat');
        $sale_offers = $CI->tq_cs_cat->offers(true);

        $cats = $this->categories();
        $out  = array();
        foreach ($rows as $r) {
            $cid = (int) $r['id'];
            $st  = isset($count[$cid]) ? $count[$cid] : array('n' => 0, 'q' => 0);
            $p   = isset($link[$cid]) ? $link[$cid] : null;
            $cat = isset($cats[(int) $r['category_id']]) ? $cats[(int) $r['category_id']] : null;

            $stats = array();
            if ($st['n'] > 0) $stats[] = array('i-play',      tq_lessons_word($st['n']));
            if ($st['q'] > 0) $stats[] = array('i-clipboard', tq_exams_word($st['q']));

            /* السعر: الكورس محتوى الباقة **إلا أن يعلن للبيع مفردا**.
               فما دام له برنامج وحده فهو يفتح بالاشتراك (`-1` أي «ضمن
               الباقات»)، والحر (`is_free_course`) يقرأ مجانا.

               TQ-COURSE-SALE — والمعلن للبيع يقرأ سعره: بلا هذا يسقط من
               مرشح «مدفوع» (الشرط `price < 0` يسقطه من الوجهين)، ولا
               يرتب مع الأسعار، وتعرض بطاقته «ضمن الباقات» بينما صفحته
               تعرض زر شراء بمئة وتسعين. والعرض يقرأ من `offer()` نفسها
               التي تقرأ منها الصفحة وشاشة الدفع. */
            $free  = ((int) $r['is_free_course'] === 1);
            $price = $free ? 0 : -1;
            if (!$free && isset($sale_offers[$cid])) $price = (int) $sale_offers[$cid]['price'];

            $out[] = $this->shape(array(
                'kind'       => 'course',
                'id'         => $cid,
                'title'      => (string) $r['title'],
                'blurb'      => tqs_excerpt(strip_tags((string) $r['short_description']), 120),
                'image'      => $this->course_image($r),
                'fallback'   => 'subj-math',
                /* المسار الكامل `course/<المسمى>/<الرقم>` كما تبنيه بقية
                   الواجهة (`public_parts.php`): الرقم هو ما يفتح، والمسمى
                   للقراءة والمحركات. */
                'href'       => base_url('course/' . rawurlencode(slugify((string) $r['title'])) . '/' . $cid),
                'cat'        => $cat ? $cat['slug'] : '',
                'cat_name'   => $cat ? $cat['name'] : '',
                'grades'     => ($p && (int) $p['grade_id'] > 0) ? array((int) $p['grade_id']) : array(),
                'grade_name' => $p ? (string) $p['grade_ar'] : '',
                'subject'    => $p ? (string) $p['subject_ar'] : '',
                'teacher'    => trim((string) $r['teacher_name']),
                'teacher_id' => ((int) $r['teacher_public'] === 1) ? (int) $r['creator'] : 0,
                'price'      => $price,
                'featured'   => ((int) $r['is_top_course'] === 1),
                'ready'      => ($st['n'] > 0),
                'date'       => (int) $r['date_added'],
                'tag'        => $p ? (string) $p['grade_ar'] : (string) $r['level'],
                'stats'      => $stats,
                'extra'      => array('lessons' => $st['n'], 'quizzes' => $st['q'],
                                      'path_id' => $p ? (int) $p['id'] : 0,
                                      'free'    => $free),
                'text'       => implode(' ', array((string) $r['title'],
                                                   strip_tags((string) $r['short_description']),
                                                   $p ? (string) $p['subject_ar'] : '',
                                                   $p ? (string) $p['grade_ar'] : '',
                                                   trim((string) $r['teacher_name']))),
            ));
        }
        return $out;
    }

    /**
     * صورة الكورس — مسارا يفتح، لا اسما يخمن.
     *
     * `course.thumbnail` يخزن **اسم ملف مجردا** داخل
     * `uploads/thumbnails/course_thumbnails/`، وهو ما تكتبه
     * `Taqdar_curriculum_model` وما تقرؤه شاشة `course_media_add.php`.
     * وتمريره كما هو الى `tqs_img()` يجعله يقرأ اسم أصل من السمة — فيبني
     * `assets/taqdar/site/img/<الاسم>.jpg.webp`، وهو ملف لا وجود له:
     * فيخرج وجه البطاقة **فارغا** بلا صورة ولا بديل، ولا خطأ في الصفحة.
     *
     * والاسمان يقرآن: اسم عمود `thumbnail` (طبقة تقدر)، واسم Academy
     * الموروث `course_thumbnail_<السمة>_<الرقم><آخر تعديل>.jpg` لكورس رفعت
     * صورته من الشاشة القديمة والعمود عندها فارغ — والمصغرة أولا ان وجدت.
     *
     * وما لم يوجد على القرص يرد فارغا، فتعرض البطاقة أصل السمة البديل
     * كما تعرضه بقية الأنواع بلا صورة.
     */
    private function course_image($r)
    {
        $name = trim((string) $r['thumbnail']);
        if ($name !== '') {
            $rel = 'uploads/thumbnails/course_thumbnails/' . basename($name);
            if (is_file(FCPATH . $rel)) return $rel;
        }

        $legacy = 'course_thumbnail_' . get_frontend_settings('theme') . '_'
                . (int) $r['id'] . (string) $r['last_modified'] . '.jpg';
        foreach (array('uploads/thumbnails/course_thumbnails/optimized/' . $legacy,
                       'uploads/thumbnails/course_thumbnails/' . $legacy) as $rel) {
            if (is_file(FCPATH . $rel)) return $rel;
        }

        return '';
    }

    /* ---- الكتب --------------------------------------------------- */
    private function books()
    {
        /* TQ-BOOK — المخطط يركب من **مسار العرض** لا من مسار الكتابة
           وحده: الأعمدة تقرأ في هذه الصفحة العامة، وقراءة عمود قبل
           إنشائه ترد «Unknown column» فتبيض الصفحة لكل زائر. وهي قاعدة
           `Taqdar_course_sale_model::install_schema()` نفسها. */
        try {
            $CI = get_instance();
            $CI->load->model('taqdar_book_model', 'tq_bk_cat');
            $CI->tq_bk_cat->install_schema();
        } catch (Throwable $e) {
            log_message('error', 'TQ-BOOK catalog schema: ' . $e->getMessage());
        }

        try {
            $rows = $this->db->select('*', false)
                             ->from('books')->where('status', 'published')
                             ->order_by('tq_order', 'ASC')->order_by('id', 'ASC')
                             ->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }

        $cats   = $this->categories();
        $grades = $this->grades();
        $out    = array();

        foreach ($rows as $r) {
            $cat  = isset($cats[(int) $r['category_id']]) ? $cats[(int) $r['category_id']] : null;
            $slug = ((string) $r['slug'] !== '') ? (string) $r['slug'] : (string) $r['id'];
            $gid  = (int) (isset($r['grade_id']) ? $r['grade_id'] : 0);

            /* العرض من مصدره الواحد (`offer()`): السعر والخصم و«أيباع»
               كلها منه، فما تكتبه البطاقة هو ما تقيده الفاتورة. ونسخة
               ثانية من قواعد السعر هنا تعرض رقما وتقبض غيره. */
            $offer = null;
            try {
                $offer = $CI->tq_bk_cat->offer($r);
            } catch (Throwable $e) { $offer = null; }

            $stats = array();
            if ((int) $r['pages'] > 0) {
                $stats[] = array('i-file', tq_count_units((int) $r['pages'], 'صفحة', 'صفحتان',
                                 'صفحتين', 'صفحات', 'صفحة', null, 'obl', true));
            }

            /* TQ-BOOK-DRIVE — «له ملف» صارت تعني موضعين لا واحدا:
               مرفوعا تحت `uploads/`، او محفوظا على Drive. وقراءة `file`
               وحدها كانت تسم كل كتب المنهج بـ«قريبا» وهي تفتح وتقرأ. */
            $has_file = ((string) $r['file'] !== ''
                         || (string) (isset($r['tq_drive_id']) ? $r['tq_drive_id'] : '') !== '');
            $sellable = ($offer && !empty($offer['sellable']));

            /* السطر الثاني يقول ما يفعله الزائر بهذا الكتاب:
                 · لا ملف     ⇐ «قريبا» — ولا يوعد بتحميل لا يقع
                 · يباع       ⇐ «كتاب مدفوع» والسعر تحته في البطاقة
                 · وإلا       ⇐ «تحميل مجاني» كما كان منذ كتبت الصفحة */
            if (!$has_file) {
                $stats[] = array('i-clock', 'قريبا');
            } elseif ($sellable) {
                $stats[] = array('i-book', 'يقرأ في مكتبتك');
            } else {
                $stats[] = array('i-download', 'تحميل مجاني');
            }

            /* TQ-BOOK-KIND — والنوع يقال حيث يقرأ: «دليل المعلم» على
               بطاقة يظنها الطالب كتابه هو اسوأ من الا يقال. وكتاب
               الطالب لا يوسم — فهو الاصل، ووسم الاصل ضجيج. */
            $kind = (string) (isset($r['tq_book_kind']) ? $r['tq_book_kind'] : 'student');
            if (isset(self::$KINDS[$kind]) && $kind !== 'student') {
                $stats[] = array('i-file', self::$KINDS[$kind]);
            }

            /* والباقة تقال حيث تفتح: كتاب بصف تفتحه باقة صفه، وهو
               ثالث ما يشتريه الزائر — ويغيب عنه أن اشتراكه يشمله. */
            if ($gid > 0 && isset($grades[$gid])) {
                $stats[] = array('i-package', 'ضمن باقة ' . $grades[$gid]);
            }

            $out[] = $this->shape(array(
                'kind'     => 'book',
                'id'       => (int) $r['id'],
                'title'    => (string) $r['title'],
                'blurb'    => tqs_excerpt((string) $r['description'], 120),
                'image'    => (string) $r['cover'],
                'fallback' => '',
                'href'     => base_url('book/' . $slug),
                'cat'      => $cat ? $cat['slug'] : '',
                'cat_name' => $cat ? $cat['name'] : '',
                'grades'   => $gid > 0 ? array($gid) : array(),
                'grade_name' => ($gid > 0 && isset($grades[$gid])) ? $grades[$gid] : '',
                'subject'  => (string) $r['subject'],
                /* السعر بالهللات كما في الباقة والكورس، وصفر يعني
                   مجانا — والبطاقة تكتب «مجاني» عندها. */
                'price'    => $sellable ? (int) $offer['price'] : 0,
                'order'    => (int) $r['tq_order'],
                'date'     => (int) $r['date_added'],
                'tone'     => (string) $r['tone'],
                'tag'      => (string) $r['subject'],
                'ready'    => $has_file,
                'stats'    => $stats,
                'extra'    => array('file' => (string) $r['file'], 'author' => (string) $r['author'],
                                    'pages' => (int) $r['pages'],
                                    'sellable' => $sellable,
                                    'list_price' => $offer ? (int) $offer['list_price'] : 0,
                                    'off' => $offer ? (int) $offer['off'] : 0,
                                    'in_plans' => ($gid > 0),
                                    'book_kind' => $kind),
                'text'     => implode(' ', array((string) $r['title'], (string) $r['subject'],
                                                 (string) $r['author'], (string) $r['description'])),
            ));
        }
        return $out;
    }

    /* ---- الشكل الموحد -------------------------------------------- */

    /**
     * يملأ الغائب بقيمه المحايدة.
     *
     * ولولا هذا لاحتاج كل موضع يقرأ عنصرا أن يفحص وجود كل مفتاح: عرض
     * يقرأ `$it['teacher']` في بطاقة كتاب يرمي تنبيها في كل صف.
     */
    private function shape($a)
    {
        $d = array(
            'kind' => '', 'id' => 0, 'title' => '', 'blurb' => '', 'image' => '', 'fallback' => '',
            'href' => '#', 'cat' => '', 'cat_name' => '', 'grades' => array(), 'grade_name' => '',
            'subject' => '', 'teacher' => '', 'teacher_id' => 0, 'badge' => '', 'tone' => '',
            'price' => -1, 'featured' => false, 'ready' => true, 'order' => 0, 'date' => 0,
            'tag' => '', 'state' => '', 'stats' => array(), 'extra' => array(), 'text' => '',
        );
        $it = array_merge($d, $a);
        $it['key']  = $it['kind'] . '-' . $it['id'];
        $it['free'] = ((int) $it['price'] === 0);
        $it['rank'] = self::kind_rank($it['kind']);
        /* مفتاح البحث يطبع مرة عند البناء لا عند كل مقارنة: الترشيح
           يقارنه بكل حرف يكتبه الزائر، والتطبيع في الحلقة يضاعف العمل. */
        $it['hay']  = tqs_norm_ar($it['text'] . ' ' . $it['cat_name'] . ' ' . $it['tag']);
        return $it;
    }

    /** أسماء الصفوف — مفتاحها الرقم. */
    public function grades()
    {
        static $g = null;
        if ($g !== null) return $g;
        $g = array();
        foreach ($this->db->select('id, name_ar')->from('grades')->where('active', 1)
                          ->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                          ->get()->result_array() as $r) {
            $g[(int) $r['id']] = (string) $r['name_ar'];
        }
        return $g;
    }

    /* ================================================================
       الترشيح والبحث والصفحات
       ================================================================ */

    /**
     * المرشحات من الرابط، مطهرة.
     *
     * تقرأ هنا لا في المتحكم ولا في العرض: الصفحة الكاملة والجزء المحدث
     * بالبحث الحي يمران بالدالة نفسها، فلا يفترق ما يفهمه أحدهما عما
     * يفهمه الآخر — وهو الموضع الذي ينشأ فيه تعارض «البحث يلغي المرشح».
     */
    public function filters_from($get)
    {
        /* كل قيمة تعامل كما قد تأتي لا كما يفترض أن تأتي: `?q[]=x` يجعل
           `$get['q']` مصفوفة، و`(string)` عليها تنبيه «Array to string»
           في كل طلب — سطر في السجل يطبعه أي زائر يكتب رابطا بيده. */
        $str = function ($v) {
            if (is_array($v))  $v = reset($v);
            if (!is_scalar($v)) return '';
            return trim((string) $v);
        };
        $csv = function ($v) use ($str) {
            $v = is_array($v) ? $v : explode(',', $str($v));
            $out = array();
            foreach ($v as $x) { $x = $str($x); if ($x !== '') $out[] = $x; }
            return array_slice(array_values(array_unique($out)), 0, 40);   // حد يمنع رابطا مصنوعا يطيل الحلقة
        };

        $sorts = array('featured', 'newest', 'title', 'price_asc', 'price_desc');
        $sort  = $str(isset($get['sort']) ? $get['sort'] : '');
        if (!in_array($sort, $sorts, true)) $sort = 'featured';

        $price = $str(isset($get['price']) ? $get['price'] : '');
        if (!in_array($price, array('free', 'paid'), true)) $price = '';

        return array(
            'q'       => mb_substr($str(isset($get['q']) ? $get['q'] : ''), 0, 120, 'UTF-8'),
            'type'    => $csv(isset($get['type'])    ? $get['type']    : ''),
            'cat'     => $csv(isset($get['cat'])     ? $get['cat']     : ''),
            'grade'   => array_map('intval', $csv(isset($get['grade']) ? $get['grade'] : '')),
            'subject' => $csv(isset($get['subject']) ? $get['subject'] : ''),
            'teacher' => array_map('intval', $csv(isset($get['teacher']) ? $get['teacher'] : '')),
            'price'   => $price,
            'sort'    => $sort,
            'page'    => max(1, (int) (isset($get['page']) ? $get['page'] : 1)),

            /* «كل المحتوى» صراحة — انظر `with_scope()`. وهو المفتاح الوحيد
               الذي لا يرشح شيئا بل يمنع ترشيحا يحقن نفسه. */
            'scope'    => ($str(isset($get['scope']) ? $get['scope'] : '') === 'all') ? 'all' : '',

            /* يملأها `with_scope()` — وتعرف هنا كي يجدها بناء الروابط
               مهما ناداه من ناداه، فلا يفحص كل موضع وجود المفتاح. */
            'mine'       => array(),
            'mine_label' => '',
            'auto'       => false,
            'injected'   => false,
        );
    }

    /* ================================================================
       مرحلة الطالب — المرشح الذي يحقن نفسه
       ================================================================ */

    /** ذاكرة الطلب لاشتقاق المرحلة: الصفحة تسأل عنها مرتين على الأقل. */
    private $stage_memo = array();

    /**
     * مرحلة صف بعينه — مسميات الأقسام التي يقع فيها.
     *
     * ═══ لماذا مشتقة لا مخزنة ═══
     *
     * `grades` أربعة أعمدة: الاسم عربيا وإنجليزيا والترتيب والتفعيل. ولا
     * عمود يقول «هذا الصف في المرحلة الابتدائية» — ولا ينبغي أن يضاف
     * لأجل هذه الشاشة وحدها، فعمود يكتب باليد يشيخ متى أضيف صف ولم
     * يملأ، وحينها تصير الشاشة تكذب بلا أن يظهر خطأ.
     *
     * ═══ مصدران يجمعان لا يتناوبان ═══
     *
     * **الأول: الاسم.** «الصف الرابع الابتدائي» يحمل اسم مرحلته في اسمه،
     * فتقابل الجذور: «المرحلة الابتدائية» ⇒ «ابتدائي» وهي فيه. والحد
     * خمسة أحرف يسقط «مرحل» من «المرحلة» — وهي في اسم كل مرحلة، فبلا
     * الحد ينسب الصف إلى الخمس جميعا. وهذا يجيب عن نصف الصفوف التي لا
     * محتوى لها بعد، وطالب فيها يبقى بلا ترشيح أصلا.
     *
     * **والثاني: المعروض نفسه.** كل عنصر يحمل قسمه وصفوفه معا، فمن حمل
     * هذا الصف حمل قسمه. وهو الذي يكشف **المسارات المتخصصة** — «اختبارات
     * القدرات» و«المهارات الرقمية» — ولا اسم صف يدل عليها.
     *
     * ولا يغني أحدهما: المحتوى وحده جعل الثالث الثانوي «قدرات» وحدها
     * والثاني المتوسط «مهارات رقمية» وحدها — لأن ذلك كل ما استورد لصفيهما
     * اليوم. فمسار متخصص ببرنامج واحد يحجب المرحلة كلها عن صاحبها. والاسم
     * أولا لأنه المرحلة، والمحتوى بعده لأنه الزيادة.
     *
     * @return array مسميات أقسام (`category.slug`) — المرحلة أولا
     */
    public function stages_of_grade($grade_id)
    {
        $grade_id = (int) $grade_id;
        if ($grade_id <= 0) return array();
        if (isset($this->stage_memo[$grade_id])) return $this->stage_memo[$grade_id];

        $out  = array();
        $name = tqs_norm_ar((string) $this->db->select('name_ar')->where('id', $grade_id)
                                              ->get('grades')->row('name_ar'));
        if ($name !== '') {
            foreach ($this->categories() as $c) {
                foreach (explode(' ', tqs_norm_ar($c['name'])) as $w) {
                    $w = preg_replace('/^ال/u', '', $w);
                    $w = preg_replace('/ه$/u', '', $w);
                    if (mb_strlen($w, 'UTF-8') < 5) continue;
                    if (mb_strpos($name, $w) !== false) { $out[] = $c['slug']; break; }
                }
            }
        }

        $hit = array();
        foreach ($this->all() as $it) {
            if ((string) $it['cat'] === '' || in_array($it['cat'], $out, true)) continue;
            if (!in_array($grade_id, $it['grades'], true)) continue;
            $hit[$it['cat']] = isset($hit[$it['cat']]) ? $hit[$it['cat']] + 1 : 1;
        }
        arsort($hit);
        $out = array_merge($out, array_keys($hit));

        /* ثلاثة سقفا: مرحلة ومساران متخصصان غاية ما يعقل، وما زاد يرشح
           كل شيء — أي لا يرشح — ويطيل الرابط بلا فائدة. */
        return $this->stage_memo[$grade_id] = array_slice($out, 0, 3);
    }

    /**
     * يضيف إلى المرشحات مرحلة صاحب الصف — إن لم يكن اختار شيئا.
     *
     * ═══ لماذا المرحلة لا الصف ═══
     *
     * الصف أدق، لكنه ليس في الأنواع الأربعة: الكتاب لا `grade_id` له
     * أصلا. فترشيح بالصف يمسح الكتب الثمانية من الشاشة — أي يخفي عن
     * الطالب المحتوى المجاني كله باسم تقريبه منه. والقسم هو المفردة
     * الوحيدة المشتركة بين الأنواع (انظر رأس الملف)، وهو «المرحلة»
     * نفسها التي يسأل عنها. والصف يبقى مرشحا
     * ظاهرا في اللوحة لمن أراد أن يضيق أكثر.
     *
     * ═══ ثلاثة شروط قبل الحقن ═══
     *
     * 1. **لم يختر الزائر شيئا** (`tqs_cat_bare`) — أي مرشح في الرابط
     *    يعني يدا تقود، ولا تزاحم.
     * 2. **لم يقل «كل المحتوى»** (`scope=all`) — وهو الباب الذي يخرج
     *    منه، وبلاه يعيد الحقن نفسه كلما نزع آخر مرشح.
     * 3. **المرحلة فيها شيء** — مرحلة بلا عنصر واحد تعطي طالبا فتح
     *    الكتالوج أول مرة شاشة «لا نتائج»، وهي أسوأ من كتالوج لم يرشح.
     *
     * و`auto` يقول «المعروض هو مرحلتك» لا «حقنت الآن»: المتحكم يحول إلى
     * الرابط الصريح بعد الحقن، فلو قرأ الإشعار من الحقن وحده لاختفى قبل
     * أن يقرأ — ويبقى الترشيح بلا سطر يفسره.
     *
     * @param array $f       المرشحات من `filters_from()`
     * @param int   $grade_id صف الزائر — والصفر لمن لا صف له
     */
    public function with_scope($f, $grade_id)
    {
        $f['mine'] = $this->stages_of_grade($grade_id);
        if (!$f['mine']) return $f;

        $names = array();
        foreach ($this->categories() as $c) {
            if (in_array($c['slug'], $f['mine'], true)) $names[] = $c['name'];
        }
        $f['mine_label'] = implode(' و', $names);

        if ($f['scope'] !== 'all' && tqs_cat_bare($f)) {
            $probe = $f;
            $probe['cat'] = $f['mine'];
            $any = false;
            foreach ($this->all() as $it) {
                if ($this->passes($it, $probe)) { $any = true; break; }
            }
            if ($any) {
                $f['cat']      = $f['mine'];
                $f['injected'] = true;
            }
        }

        $f['auto'] = ($f['cat'] && !array_diff($f['cat'], $f['mine'])
                                && !array_diff($f['mine'], $f['cat']));
        return $f;
    }

    /** هل يجتاز العنصر مرشحا بعينه؟ — موضع واحد يقرؤه الترشيح والعد. */
    private function passes($it, $f, $skip = '')
    {
        if ($skip !== 'type' && $f['type'] && !in_array($it['kind'], $f['type'], true)) return false;
        if ($skip !== 'cat'  && $f['cat']  && !in_array($it['cat'],  $f['cat'],  true)) return false;

        if ($skip !== 'grade' && $f['grade']) {
            if (!array_intersect($f['grade'], $it['grades'])) return false;
        }
        if ($skip !== 'subject' && $f['subject']) {
            if ($it['subject'] === '' || !in_array($it['subject'], $f['subject'], true)) return false;
        }
        if ($skip !== 'teacher' && $f['teacher']) {
            if ((int) $it['teacher_id'] <= 0 || !in_array((int) $it['teacher_id'], $f['teacher'], true)) return false;
        }
        if ($skip !== 'price' && $f['price'] !== '') {
            /* السعر السالب يعني «لا يسعر هنا» (البرنامج يفتح بالباقة)،
               فلا هو مجاني ولا مدفوع — ويسقط من كلا الوجهين بدل أن
               يعد الزائر بمجاني ثم يجد بابا يطلب اشتراكا. */
            if ((int) $it['price'] < 0) return false;
            if ($f['price'] === 'free' && !$it['free']) return false;
            if ($f['price'] === 'paid' &&  $it['free']) return false;
        }
        if ($skip !== 'q' && $f['q'] !== '') {
            foreach (tqs_norm_words($f['q']) as $w) {
                if (strpos($it['hay'], $w) === false) return false;   // كل كلمة تلزم
            }
        }
        return true;
    }

    /**
     * النتيجة كاملة: العناصر وعدادات المرشحات وحال الصفحات.
     *
     * العدادات تحسب **بعد استبعاد المرشح الذي تصفه** (facet counting):
     * عداد «الكتب» تحت مرشح النوع يقول كم كتابا يبقى لو اخترت الكتب
     * الآن — لا كم كتابا في النتيجة الحالية، وهو صفر دائما حين يكون
     * النوع المختار البرامج. والفرق بينهما هو الفرق بين مرشح يرشد
     * ومرشح يقول «صفر» في كل خانة.
     */
    public function search($f)
    {
        $all = $this->all();

        $matched = array();
        foreach ($all as $it) if ($this->passes($it, $f)) $matched[] = $it;

        $matched = $this->sort_items($matched, $f['sort']);

        $total = count($matched);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min(max(1, (int) $f['page']), $pages);
        $slice = array_slice($matched, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        return array(
            'items'  => $slice,
            'total'  => $total,
            'page'   => $page,
            'pages'  => $pages,
            'per'    => self::PER_PAGE,
            'from'   => $total ? (($page - 1) * self::PER_PAGE + 1) : 0,
            'to'     => min($page * self::PER_PAGE, $total),
            'facets' => $this->facets($all, $f),
            'active' => $this->active_chips($f),
        );
    }

    /** الفرز — والافتراضي «المميز»: الباقات المبرزة أولا ثم بترتيب اللوحة. */
    private function sort_items($rows, $sort)
    {
        $cmp = array(
            'title'     => function ($a, $b) { return strcmp($a['title'], $b['title']); },
            'newest'    => function ($a, $b) {
                if ($a['date'] !== $b['date']) return ($b['date'] < $a['date']) ? -1 : 1;
                return ($b['id'] < $a['id']) ? -1 : (($b['id'] > $a['id']) ? 1 : 0);
            },
            'price_asc' => function ($a, $b) {
                $x = ($a['price'] < 0) ? PHP_INT_MAX : $a['price'];
                $y = ($b['price'] < 0) ? PHP_INT_MAX : $b['price'];
                return ($x === $y) ? 0 : (($x < $y) ? -1 : 1);
            },
        );
        if ($sort === 'price_desc') {
            usort($rows, function ($a, $b) use ($cmp) { return -$cmp['price_asc']($a, $b); });
            return $rows;
        }
        if (isset($cmp[$sort])) { usort($rows, $cmp[$sort]); return $rows; }

        usort($rows, function ($a, $b) {
            if ($a['rank'] !== $b['rank']) return ($a['rank'] < $b['rank']) ? -1 : 1;
            if ($a['featured'] !== $b['featured']) return $a['featured'] ? -1 : 1;
            if ($a['order'] !== $b['order']) return ($a['order'] < $b['order']) ? -1 : 1;
            return ($a['id'] < $b['id']) ? -1 : (($a['id'] > $b['id']) ? 1 : 0);
        });
        return $rows;
    }

    /**
     * خيارات المرشحات وعدادها.
     *
     * الخيارات تشتق من **المحتوى** لا من جداول التصنيف: `paths` فيها
     * مادة برقم لا صف له في `subjects` (الدراسات الإسلامية)، فقائمة
     * مبنية من الجدول تسقطها، وقائمة مبنية من المعروض لا تسقط شيئا
     * يعرض. وما لا عنصر له لا يظهر خيارا أصلا.
     */
    private function facets($all, $f)
    {
        $grades = $this->grades();
        $kinds  = self::kinds();

        $groups = array(
            'type'    => array('label' => 'النوع',   'icon' => 'i-grid',    'items' => array()),
            'cat'     => array('label' => 'القسم',   'icon' => 'i-curriculum', 'items' => array()),
            'grade'   => array('label' => 'الصف',    'icon' => 'i-cap',     'items' => array()),
            'subject' => array('label' => 'المادة',  'icon' => 'i-book',    'items' => array()),
            'teacher' => array('label' => 'المعلم',  'icon' => 'i-teacher', 'items' => array()),
        );

        /* الترتيب يثبت من مصدره لا من أول ظهور: الصفوف بترتيب `order`
           والأنواع بترتيب `kinds()` — وإلا تبدل ترتيب المرشح كلما
           تبدل ترتيب النتائج، فيبحث الزائر عن خيار كان مكانه. */
        $seed = array(
            'type'  => array_keys($kinds),
            'grade' => array_map('strval', array_keys($grades)),
        );

        foreach ($groups as $key => $_) {
            $order = isset($seed[$key]) ? array_flip($seed[$key]) : array();
            $bag   = array();

            foreach ($all as $it) {
                if (!$this->passes($it, $f, $key)) continue;

                $vals = array();
                if     ($key === 'type')    $vals = array(array($it['kind'], $kinds[$it['kind']]['label']));
                elseif ($key === 'cat')     { if ($it['cat'] !== '') $vals = array(array($it['cat'], $it['cat_name'])); }
                elseif ($key === 'subject') { if ($it['subject'] !== '') $vals = array(array($it['subject'], $it['subject'])); }
                elseif ($key === 'teacher') { if ((int) $it['teacher_id'] > 0) $vals = array(array((string) $it['teacher_id'], $it['teacher'])); }
                elseif ($key === 'grade') {
                    foreach ($it['grades'] as $g) {
                        if (isset($grades[$g])) $vals[] = array((string) $g, $grades[$g]);
                    }
                }

                foreach ($vals as $v) {
                    if (!isset($bag[$v[0]])) {
                        $bag[$v[0]] = array('value' => $v[0], 'label' => $v[1], 'n' => 0,
                                            'pos' => isset($order[$v[0]]) ? $order[$v[0]] : 900 + count($bag));
                    }
                    $bag[$v[0]]['n']++;
                }
            }

            uasort($bag, function ($a, $b) { return ($a['pos'] === $b['pos']) ? 0 : (($a['pos'] < $b['pos']) ? -1 : 1); });

            $sel = ($key === 'grade' || $key === 'teacher')
                 ? array_map('strval', $f[$key]) : $f[$key];
            foreach ($bag as $v => $row) $bag[$v]['on'] = in_array((string) $v, $sel, true);

            $groups[$key]['items'] = array_values($bag);
        }

        /* مرشح لا خيار فيه إلا واحدا لا يرشح شيئا — يخفى بدل أن يشغل
           عمودا بزر لا يغير النتيجة. والنوع يبقى دائما: هو مفتاح الصفحة. */
        foreach ($groups as $k => $g) {
            if ($k !== 'type' && count($g['items']) < 2) unset($groups[$k]);
        }
        return $groups;
    }

    /** المرشحات المفعلة رقائق تحذف بنقرة — ومعها ما تحذفه من الرابط. */
    private function active_chips($f)
    {
        $out    = array();
        $kinds  = self::kinds();
        $grades = $this->grades();
        $cats   = array();
        foreach ($this->categories() as $c) $cats[$c['slug']] = $c['name'];

        foreach ($f['type'] as $v) {
            $out[] = array('key' => 'type', 'value' => $v,
                           'label' => isset($kinds[$v]) ? $kinds[$v]['label'] : $v);
        }
        foreach ($f['cat'] as $v) {
            $out[] = array('key' => 'cat', 'value' => $v,
                           'label' => isset($cats[$v]) ? $cats[$v] : $v);
        }
        foreach ($f['grade'] as $v) {
            $out[] = array('key' => 'grade', 'value' => (string) $v,
                           'label' => isset($grades[$v]) ? $grades[$v] : $v);
        }
        foreach ($f['subject'] as $v) $out[] = array('key' => 'subject', 'value' => $v, 'label' => $v);
        foreach ($f['teacher'] as $v) {
            $name = '';
            foreach ($this->all() as $it) {
                if ((int) $it['teacher_id'] === (int) $v) { $name = $it['teacher']; break; }
            }
            $out[] = array('key' => 'teacher', 'value' => (string) $v, 'label' => $name !== '' ? $name : $v);
        }
        if ($f['price'] !== '') {
            $out[] = array('key' => 'price', 'value' => $f['price'],
                           'label' => ($f['price'] === 'free') ? 'مجاني' : 'مدفوع');
        }
        if ($f['q'] !== '') $out[] = array('key' => 'q', 'value' => $f['q'], 'label' => 'بحث: ' . $f['q']);
        return $out;
    }

    /* ================================================================
       الصفحات المفردة
       ================================================================ */

    /** كتاب واحد بمسماه — ومعه فئته. */
    public function book_by_slug($slug)
    {
        $this->db->select('b.*, c.name AS cat_name, c.slug AS cat_slug', false)
                 ->from('books b')
                 ->join('category c', 'c.id = b.category_id', 'left')
                 ->where('b.status', 'published');

        if (ctype_digit((string) $slug)) $this->db->where('b.id', (int) $slug);
        else                             $this->db->where('b.slug', (string) $slug);

        return $this->db->get()->row_array();
    }

    /**
     * كل كتب صف بعينه — **بلا ترقيم**.
     *
     * `search()` تقطع اثني عشر، وصف المنهج ثلاثة عشر كتابا او اربعة
     * عشر — فصفحة الصف كانت تعرض المنهج ناقصا كتابا وتحيل بقيته الى
     * صفحة ثانية. والصف وحدة تقرأ كاملة، وترقيمه يقسم ما لا يقسم.
     */
    public function books_of_grade($grade_id, $kind = '')
    {
        $gid  = (int) $grade_id;
        $kind = trim((string) $kind);
        $out  = array();
        if ($gid <= 0) return $out;

        foreach ($this->all() as $it) {
            if ($it['kind'] !== 'book') continue;
            if (!in_array($gid, $it['grades'], true)) continue;
            if ($kind !== '' && (string) $it['extra']['book_kind'] !== $kind) continue;
            $out[] = $it;
        }
        return $out;
    }

    /** كتب أخرى في الفئة نفسها — وما نقص يكمل من خارجها. */
    public function books_like($book, $limit = 4)
    {
        $out = array();
        foreach ($this->all() as $it) {
            if ($it['kind'] !== 'book' || (int) $it['id'] === (int) $book['id']) continue;
            $same = ((string) $it['cat'] === (string) $book['cat_slug']);
            $out[$same ? 0 : 1][] = $it;
        }
        $rows = array_merge(isset($out[0]) ? $out[0] : array(), isset($out[1]) ? $out[1] : array());
        return array_slice($rows, 0, (int) $limit);
    }

    /**
     * باقات اخرى الى جوار باقة معروضة — بترتيب القرب.
     *
     * المرحلة نفسها اولا ثم ما سواها: من يقرأ «الباقة المميزة — المرحلة
     * المتوسطة» يوازن اولا باختيها في المتوسط، لا بباقة ابتدائية لا تخصه.
     * وبعد اخواتها تعرض البقية بدل شبكة تنتهي ببطاقة واحدة.
     *
     * ومصدرها `all()` نفسه الذي تبنى منه بطاقات الكتالوج، فالبطاقة هنا
     * هي البطاقة هناك: سعرا وصورة وشارة «الاكثر طلبا». ونسخة ثانية من
     * منطق البطاقة تفترق عن الاولى عند اول تعديل.
     *
     * @param string $code مسمى الباقة المعروضة — تستثنى من النتيجة
     */
    public function plans_like($code, $limit = 3)
    {
        $code = (string) $code;

        /* المرحلة تؤخذ من عنصر الباقة نفسه لا من وسيط ثان: وسيطان لشيء
           واحد يفترقان متى نادى احد الدالة بمرحلة غير مرحلة الباقة. */
        $cat = null;
        foreach ($this->all() as $it) {
            if ($it['kind'] === 'plan' && isset($it['extra']['code'])
                && (string) $it['extra']['code'] === $code) {
                $cat = (string) $it['cat'];
                break;
            }
        }

        $same = $rest = array();
        foreach ($this->all() as $it) {
            if ($it['kind'] !== 'plan') continue;
            if (isset($it['extra']['code']) && (string) $it['extra']['code'] === $code) continue;

            if ($cat !== null && $cat !== '' && (string) $it['cat'] === $cat) $same[] = $it;
            else $rest[] = $it;
        }

        return array_slice(array_merge($same, $rest), 0, (int) $limit);
    }

    /**
     * ما يجاور برنامجا في الكتالوج — برامج وكورسات وكتبا.
     *
     * الترتيب بالقرب لا بالنوع: المادة نفسها اولا، ثم القسم نفسه، ثم
     * البقية. فمن يقرأ «رياضيات السادس» يجد تحتها ما يقربه منها لا ما
     * يشترك معها في المرحلة وحدها.
     *
     * والباقات مستثناة عمدا: لها قسمها الخاص في الصفحة («ما يفتح هذا
     * البرنامج») وهو جواب سؤال اخر — «كيف احصل عليه؟» لا «وماذا ايضا؟».
     * وخلطهما يجعل بطاقة باقة تقرأ منتجا موازيا للبرنامج لا الباب اليه.
     */
    public function near_path($path, $limit = 6)
    {
        if (!$path) return array();

        $pid     = (int) $path['id'];
        $cat     = (string) (isset($path['cat_slug']) ? $path['cat_slug'] : '');
        $subject = '';

        /* اسم المادة من عنصر البرنامج نفسه في الكتالوج: `paths` تحمل
           `subject_id` لا اسمه، والمقارنة هنا بالاسم كما في `passes()`. */
        foreach ($this->all() as $it) {
            if ($it['kind'] === 'path' && (int) $it['id'] === $pid) {
                $subject = (string) $it['subject'];
                break;
            }
        }

        $same_subject = $same_cat = $rest = array();
        foreach ($this->all() as $it) {
            if ($it['kind'] === 'plan') continue;                       // للباقات قسمها
            if ($it['kind'] === 'path' && (int) $it['id'] === $pid) continue;

            if     ($subject !== '' && (string) $it['subject'] === $subject) $same_subject[] = $it;
            elseif ($cat !== ''     && (string) $it['cat'] === $cat)         $same_cat[] = $it;
            else                                                            $rest[] = $it;
        }

        return array_slice(array_merge($same_subject, $same_cat, $rest), 0, (int) $limit);
    }

    /** برامج الكتالوج التي تشبه عنصرا — لذيل الصفحات المفردة. */
    public function related($kind, $cat, $subject = '', $limit = 3, $skip_id = 0)
    {
        $hit = $rest = array();
        foreach ($this->all() as $it) {
            if ($it['kind'] !== $kind) continue;
            if ((int) $it['id'] === (int) $skip_id) continue;
            if ($cat !== '' && $it['cat'] !== $cat) continue;
            if ($subject !== '' && $it['subject'] === $subject) $hit[] = $it;
            else $rest[] = $it;
        }
        return array_slice(array_merge($hit, $rest), 0, (int) $limit);
    }
}
