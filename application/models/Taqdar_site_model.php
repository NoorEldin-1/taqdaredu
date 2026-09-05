<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * قراءة المحتوى العام — للزائر المجهول.
 *
 * قبل هذا الملف كان الاستعلام الوحيد الصالح للكتالوج مكتوبا **داخل عرض**
 * (`plans.php`)، فلا يعاد استعماله ولا يختبر ولا يصلح إلا في مكانه.
 * وهنا مصدر واحد تقرأ منه الصفحات كلها، فلا يفترق ما تعرضه صفحة عما
 * تعرضه أختها.
 *
 * وكل دالة هنا تفترض **زائرا بلا حساب**: لا شيء يعتمد على جلسة، ولا صف
 * غير منشور يخرج منها.
 */
class Taqdar_site_model extends CI_Model
{
    /** الفئات المنشورة مرتبة، بالشكل الذي تتوقعه مولدات الوسم. */
    public function categories()
    {
        /* العد من `paths` لا من `course`: صفحة الأقسام كانت تعد دورات
           Academy، وفي القاعدة دورة واحدة بلا قسم — فتقول كل قسم «صفر»
           بينما الكتالوج يعرض خمسة عشر مسارا. كيانان يعدان في صفحتين. */
        $rows = $this->db->select('c.id, c.slug, c.name, c.subtitle, c.font_awesome_class,
                                   c.thumbnail, COUNT(p.id) AS n', false)
                         ->from('category c')
                         ->join('paths p', "p.category_id = c.id AND p.status = 'published'", 'left')
                         ->where('c.parent', 0)
                         ->group_by('c.id')
                         ->order_by('c.tq_order', 'ASC')->order_by('c.id', 'ASC')
                         ->get()->result_array();

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'    => ((string) $r['slug'] !== '') ? (string) $r['slug'] : (string) $r['id'],
                'label' => $r['name'],
                'sub'   => (string) $r['subtitle'],
                'icon'  => ((string) $r['font_awesome_class'] !== '') ? (string) $r['font_awesome_class'] : 'i-book',
                'image' => (string) $r['thumbnail'],
                'n'     => (int) $r['n'],
            );
        }
        return $out;
    }

    /**
     * المسارات المنشورة بوصفها «مواد» الكتالوج.
     *
     * عدد الدروس والاختبارات يحسب من الدورة المرتبطة — ولا يخزن رقما
     * في المسار. الرقم المخزن يشيخ في صمت: يكتب مرة ثم يبقى بعد أن
     * يتغير ما يصفه. وحين لا دورة، يعود صفرا والعرض يقول «قريبا» بدل
     * أن يعد بمحتوى لا وجود له.
     */
    public function materials($cat = null)
    {
        $this->db->select('p.id, p.title, p.slug, p.short_description, p.thumbnail, p.badge,
                           p.price, p.course_id, c.slug AS cat_slug', false)
                 ->from('paths p')
                 ->join('category c', 'c.id = p.category_id', 'left')
                 ->where('p.status', 'published')
                 ->order_by('p.tq_order', 'ASC')->order_by('p.id', 'ASC');

        if ($cat !== null && $cat !== '') $this->db->where('c.slug', $cat);
        $rows = $this->db->get()->result_array();

        $out = array();
        foreach ($rows as $r) {
            $cid = (int) $r['course_id'];
            $lessons = $quizzes = 0;
            if ($cid > 0) {
                $lessons = (int) $this->db->where('course_id', $cid)->count_all_results('lesson');
                $quizzes = (int) $this->db->where('course_id', $cid)->where('lesson_type', 'quiz')
                                          ->count_all_results('lesson');
            }

            $out[] = array(
                'id'      => (int) $r['id'],
                'cat'     => (string) $r['cat_slug'],
                'title'   => $r['title'],
                'blurb'   => (string) $r['short_description'],
                'image'   => ((string) $r['thumbnail'] !== '') ? (string) $r['thumbnail'] : 'subj-math',
                'badge'   => (string) $r['badge'],
                'lessons' => $lessons,
                'quizzes' => $quizzes,
                'price'   => (int) $r['price'],
                'href'    => base_url('path/' . (((string) $r['slug'] !== '') ? (string) $r['slug'] : (string) $r['id'])),
            );
        }
        return $out;
    }

    /** مسار واحد بمسماه، ومعه فئته ومعلمه ودورته. */
    public function path_by_slug($slug)
    {
        $this->db->select('p.*, c.name AS cat_name, c.slug AS cat_slug,
                           TRIM(CONCAT(COALESCE(u.first_name,""), " ", COALESCE(u.last_name,""))) AS teacher_name,
                           u.image AS teacher_image, u.teacher_slug, co.title AS course_title,
                           co.status AS course_status', false)
                 ->from('paths p')
                 ->join('category c', 'c.id = p.category_id', 'left')
                 ->join('users u', 'u.id = p.teacher_id', 'left')
                 ->join('course co', "co.id = p.course_id AND co.status = 'active'", 'left')
                 ->where('p.status', 'published');
        /* شرط حالة الدورة في الضم لا في `where`: مسار بلا دورة منشورة
           يبقى معروضا (بلا زر شراء)، لكن عنوان دورة قيد المراجعة
           لا يعرض لزائر — وقد كان `[UPTEST]` يظهر منه. */

        if (ctype_digit((string) $slug)) $this->db->where('p.id', (int) $slug);
        else                             $this->db->where('p.slug', $slug);

        return $this->db->get()->row_array();
    }

    /** الكتب المنشورة. */
    public function books($cat = null)
    {
        /* `id` و`slug` معهما: صفحة الكتاب (`/book/<slug>`) صارت موجودة،
           وبطاقة تعرض كتابا ولا تعرف رابطه تنتهي إلى قائمة الكتب كلها —
           فيبحث الزائر مرة ثانية عما وجد. */
        $this->db->select('b.id, b.slug, b.title, b.subject, b.pages, b.tone, b.cover, b.file,
                           c.slug AS cat_slug', false)
                 ->from('books b')
                 ->join('category c', 'c.id = b.category_id', 'left')
                 ->where('b.status', 'published')
                 ->order_by('b.tq_order', 'ASC')->order_by('b.id', 'ASC');

        if ($cat !== null && $cat !== '') $this->db->where('c.slug', $cat);
        $rows = $this->db->get()->result_array();

        $out = array();
        foreach ($rows as $r) {
            $slug = ((string) $r['slug'] !== '') ? (string) $r['slug'] : (string) $r['id'];
            $out[] = array(
                'cat'     => (string) $r['cat_slug'],
                'title'   => $r['title'],
                'subject' => (string) $r['subject'],
                'pages'   => (int) $r['pages'],
                'tone'    => (string) $r['tone'],
                'cover'   => (string) $r['cover'],
                'file'    => (string) $r['file'],
                'href'    => base_url('book/' . $slug),
            );
        }
        return $out;
    }

    /**
     * المعلمون المعروضون علنا.
     *
     * العرض العام اختيار صريح (`is_public`) لا أثر جانبي لكون المستخدم
     * معلما: من يدرس لا يلزم أن ينشر اسمه وصورته على صفحة عامة.
     */
    public function teachers()
    {
        $rows = $this->db->select('id, first_name, last_name, image, biography, title, skills,
                                   teacher_slug, teacher_stage, teacher_rating, teacher_reviews, teacher_courses')
                         ->from('users')->where('is_instructor', 1)->where('is_public', 1)
                         ->order_by('teacher_rating', 'DESC')->order_by('id', 'ASC')
                         ->get()->result_array();

        $out = array();
        foreach ($rows as $r) {
            $name = trim($r['first_name'] . ' ' . $r['last_name']);
            $out[] = array(
                'id'      => (int) $r['id'],
                'name'    => $name,
                'bio'     => (string) $r['biography'],
                'title'   => (string) $r['title'],
                'chips'   => array_filter(array_map('trim', explode('·', (string) $r['skills']))),
                'img'     => (string) $r['image'],
                'stage'   => (string) $r['teacher_stage'],
                'rating'  => (float) $r['teacher_rating'],
                'reviews' => (int) $r['teacher_reviews'],
                'courses' => (int) $r['teacher_courses'],
                'url'     => base_url('instructor/' . (int) $r['id']),
            );
        }
        return $out;
    }

    /** المقالات المنشورة، وأول المميز منها إن وجد. */
    public function posts($cat = null, $limit = 0)
    {
        $this->db->select('b.blog_id, b.title, b.slug, b.description, b.thumbnail, b.is_popular,
                           b.added_date, bc.title AS cat_name', false)
                 ->from('blogs b')
                 ->join('blog_category bc', 'bc.blog_category_id = b.blog_category_id', 'left')
                 ->where('b.status', '1')
                 ->order_by('b.is_popular', 'DESC')->order_by('b.blog_id', 'ASC');

        if ($cat !== null && $cat !== '') $this->db->where('bc.title', $cat);
        if ($limit > 0) $this->db->limit((int) $limit);
        $rows = $this->db->get()->result_array();

        $out = array();
        foreach ($rows as $r) {
            $slug = ((string) $r['slug'] !== '') ? (string) $r['slug'] : 'post';
            $out[] = array(
                'id'      => (int) $r['blog_id'],
                'cat'     => (string) $r['cat_name'],
                'title'   => $r['title'],
                'excerpt' => tqs_excerpt((string) $r['description'], 165),
                'img'     => (string) $r['thumbnail'],
                'feat'    => (int) $r['is_popular'] === 1,
                'url'     => base_url('blog/' . $slug . '/' . (int) $r['blog_id']),
            );
        }
        return $out;
    }

    /** تصنيفات المدونة التي لها مقال منشور واحد على الأقل. */
    public function post_categories()
    {
        return $this->db->select('bc.title, COUNT(b.blog_id) AS n', false)
                        ->from('blog_category bc')
                        ->join('blogs b', 'b.blog_category_id = bc.blog_category_id AND b.status = "1"', 'inner')
                        ->group_by('bc.blog_category_id')
                        ->order_by('bc.blog_category_id', 'ASC')
                        ->get()->result_array();
    }

    /** أحدث الدروس المنشورة عبر المسارات — «القادم» يعني الجديد لا المجدول. */
    public function latest_lessons($limit = 4)
    {
        $rows = $this->db->select('l.id, l.title, l.course_id, p.title AS path_title, p.slug AS path_slug', false)
                         ->from('lesson l')
                         ->join('paths p', 'p.course_id = l.course_id', 'inner')
                         ->where('p.status', 'published')
                         ->order_by('l.id', 'DESC')->limit((int) $limit)
                         ->get()->result_array();
        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'title' => (string) $r['title'],
                'path'  => (string) $r['path_title'],
                'href'  => base_url('path/' . (string) $r['path_slug']),
            );
        }
        return $out;
    }

    /** مسارات مميزة: ما له دورة موصولة أولا — فلا نبرز ما لا يفتح. */
    public function featured_paths($limit = 6)
    {
        $all = $this->materials(null);
        $withCourse = array();
        $rest = array();
        foreach ($all as $m) {
            if ((int) $m['lessons'] > 0) $withCourse[] = $m; else $rest[] = $m;
        }
        $out = array_merge($withCourse, $rest);
        return array_slice($out, 0, (int) $limit);
    }


    /**
     * بحث في المحتوى العام.
     *
     * **بلا استعلام جديد**: يعيد استعمال `materials()` و`posts()`
     * و`books()` و`teachers()` ثم يرشح في PHP. والمجموع بضع عشرات من
     * الصفوف، فالفرق في الأداء لا يقاس — والمكسب أن نتيجة البحث هي
     * **نفسها** ما تعرضه الصفحات: لا استعلام ثان ينقح شرط نشر أو
     * ترتيبا ثم يتباعد عنها، ولا نص من المستخدم يدخل SQL.
     */
    public function site_search($q)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return array('paths' => array(), 'posts' => array(),
                         'books' => array(), 'teachers' => array(), 'total' => 0);
        }
        $needle = mb_strtolower($q, 'UTF-8');

        $hit = function ($hay) use ($needle) {
            return mb_strpos(mb_strtolower((string) $hay, 'UTF-8'), $needle, 0, 'UTF-8') !== false;
        };
        $pick = function ($rows, $fields) use ($hit) {
            $out = array();
            foreach ((array) $rows as $r) {
                foreach ($fields as $f) {
                    if (isset($r[$f]) && $hit($r[$f])) { $out[] = $r; break; }
                }
            }
            return $out;
        };

        $res = array(
            'paths'    => $pick($this->materials(), array('title', 'blurb')),
            'posts'    => $pick($this->posts(),     array('title', 'excerpt', 'cat')),
            'books'    => $pick($this->books(),     array('title', 'subject')),
            'teachers' => $pick($this->teachers(),  array('name', 'bio')),
        );
        $res['total'] = count($res['paths']) + count($res['posts'])
                      + count($res['books']) + count($res['teachers']);
        return $res;
    }

    /**
     * الباقة كاملة بمحتواها — مصدر الحقيقة الواحد لصفحاتها الثلاث.
     *
     * صفحة الباقة العامة، وشاشة تأكيد الشراء، وصفحة «محتوى باقتي» للمشترك
     * تقرأ كلها من هنا. ولولا ذلك لافترقت الأرقام: صفحة تعد بمئة درس
     * وصفحة تعرض ثمانين، والفرق سطر استعلام نسخ ثم شاخ.
     *
     * وكل عدد هنا **يحسب من القاعدة لحظة الطلب** — لا رقم مخزن ولا
     * رقم مكتوب في العرض. فما لم يستورد بعد يظهر صفرا، والعرض يخفي
     * الصفر بدل أن يعد بما لا وجود له.
     *
     * خمسة استعلامات مهما كبرت الباقة (لا استعلام لكل برنامج):
     *   الباقة · الصفوف · البرامج · الوحدات · الدروس
     *
     * @param  string $code  رمز الباقة (`plans.code`) أو رقمها
     * @return array|null    null إن لم توجد أو كانت معطلة
     */
    /**
     * الباقات التي تفتح دورة بعينها — مرتبة بالأرخص.
     *
     * صفحة الدورة كانت تعرض زر «اشتر الدورة» يخاطب سلة Academy، وهي
     * سلة لا قالب لها في ثيم تقدر: من ضغطه وصل إلى `home/shopping_cart`
     * فقرأ «Page Not Found 404». والأصل أن الشراء المفرد لم يعد وحدة
     * البيع أصلا — انظر `site_path.php`: «لا سعر ولا شراء هنا: وحدة
     * البيع صارت الباقة». فصفحة الدورة كانت آخر موضع يبيع بالقطعة،
     * ويبيع إلى لا مكان.
     *
     * والجسر بين الدورة والباقة هو `paths`: هو الوحيد الذي يربط الدورة
     * بصفها، والباقة ذات النطاق `grade` تحمل قائمة صفوفها في `scope_ids`.
     * وهذه هي علاقة المنح نفسها التي يقرأها
     * `Taqdar_billing_model::subscription_grants()` عند فتح الدرس —
     * فما تعد به هذه الصفحة هو ما يفتحه الاشتراك فعلا، لا وعدا مكتوبا
     * في العرض.
     *
     * ونطاق `all` يدخل معها: باقة تفتح الكتالوج كله تفتح هذه الدورة.
     * و`trial` لا يدخل: وحدته الدرس المجاني لا الدورة.
     *
     * @return array صفوف `plans` (قد تكون فارغة)
     */
    public function plans_for_course($course_id)
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) return array();

        $grades = array();
        foreach ($this->db->select('grade_id')->from('paths')
                          ->where('course_id', $course_id)
                          ->where('grade_id >', 0)->get()->result_array() as $r) {
            $grades[] = (int) $r['grade_id'];
        }

        return $this->plans_for_grades($grades);
    }

    /**
     * TQ-BOOK — الباقات التي تفتح صفا بعينه.
     *
     * وهي جوف `plans_for_course()` بعد أن استخرج منه: صفحة الكتاب تسأل
     * السؤال نفسه (**«أي باقة تفتح هذا؟»**) وتعرف صفها مباشرة، فلا
     * تحتاج أن تمر بـ`paths`. واستعلام ثان بالقاعدة نفسها في موضع آخر
     * يفترق عن هذا عند أول تعديل، فتعد صفحة بباقة تنكرها أختها.
     *
     * ونطاق `all` يدخل معها: باقة تفتح الكتالوج كله تفتح هذا الكتاب.
     * و`trial` لا يدخل: وحدته الدرس المجاني.
     *
     * @return array صفوف `plans` (قد تكون فارغة)
     */
    public function plans_for_grades($grade_ids)
    {
        $grades = array_values(array_unique(array_filter(
            array_map('intval', (array) $grade_ids))));

        $rows = $this->db->from('plans')->where('active', 1)
                         ->where_in('scope', array('grade', 'all'))
                         ->order_by('price', 'ASC')->order_by('`order`', 'ASC', false)
                         ->get()->result_array();

        $out = array();
        foreach ($rows as $p) {
            if ((string) $p['scope'] === 'all') { $out[] = $p; continue; }
            if (!$grades) continue;

            $ids = array_filter(array_map('intval', explode(',', (string) $p['scope_ids'])));
            if (!$ids && (int) $p['scope_id'] > 0) $ids = array((int) $p['scope_id']);
            if (array_intersect($grades, $ids)) $out[] = $p;
        }
        return $out;
    }

    /**
     * تفصيل برنامج واحد — منهجه وأرقامه وما يفتحه.
     *
     * ═══ لماذا وجدت ═══
     *
     * كانت صفحة البرنامج تعرض سطر وصف وقائمة مسطحة بثلاثين عنوان درس بلا
     * وحدات ولا مدد ولا تمييز بين درس فيديو واختبار. والمشتري هنا ولي أمر
     * يسأل سؤالا واحدا: **ماذا سيدرس ابني؟** — والقائمة المسطحة لا تجيبه:
     * لا يعرف كم وحدة، ولا كم ساعة، ولا اين ينتهي.
     *
     * ═══ الصفر يخفي نفسه ═══
     *
     * كل رقم هنا محسوب من صفوف فعلية. وبرنامج لم تستورد دروسه لا يعد
     * بأربعين درسا: الوعد الذي لا يقابله صف في القاعدة يكتشفه المشتري بعد
     * الدفع لا قبله — وهو المبدأ نفسه المكتوب في `bundle_by_code()`.
     *
     * ═══ باستعلامين لا باستعلام لكل وحدة ═══
     *
     * الوحدات ثم الدروس دفعة واحدة، وتجمع في الذاكرة. واستعلام لكل وحدة
     * يعني خمسة عشر استعلاما في صفحة عامة تقرأ آلاف المرات في اليوم.
     *
     * @return array units · totals · plans — والدروس غير المنشورة مستبعدة
     */
    public function path_detail($path)
    {
        $out = array(
            'units'  => array(),
            'totals' => array('units' => 0, 'lessons' => 0, 'quizzes' => 0,
                              'free' => 0, 'minutes' => 0),
            'plans'  => array(),
        );
        if (!$path) return $out;

        $course_id = (int) $path['course_id'];

        /* الباقات تقرأ ولو لم يربط محتوى: هي جواب «كيف احصل عليه؟»،
           وبرنامج قيد الاعداد له باقته التي ستفتحه. */
        $out['plans'] = $this->plans_for_course($course_id);

        if ($course_id <= 0) return $out;

        /* `tq_status` يستبعد ما لم ينشر: درس مسودة في صفحة عامة يعد بما
           لا يفتح، ويعده في المجموع فيكذب الرقم كذلك. */
        $lessons = $this->db->select('id, title, duration, lesson_type, is_free,
                                     section_id, video_url, tq_status, `order`', false)
                            ->from('lesson')
                            ->where('course_id', $course_id)
                            ->where('(COALESCE(`tq_status`, "published") = "published"'
                                  . ' OR `tq_status` = "soon")', null, false)
                            ->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                            ->get()->result_array();

        $sections = $this->db->select('id, title, `order`', false)
                             ->from('section')
                             ->where('course_id', $course_id)
                             ->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                             ->get()->result_array();

        /* وعاء لكل وحدة، ووعاء «بلا وحدة» للدروس اليتيمة: درس بمعرف قسم
           محذوف كان يسقط من العرض بلا اثر — يعد في المجموع ولا يظهر في
           قائمة، فيقرأ الزائر «١٢ درسا» تحتها احد عشر. */
        $bag = array();
        foreach ($sections as $s) {
            $bag[(int) $s['id']] = array('title' => (string) $s['title'], 'lessons' => array(),
                                         'minutes' => 0, 'quizzes' => 0);
        }
        $bag[0] = array('title' => 'دروس البرنامج', 'lessons' => array(), 'minutes' => 0, 'quizzes' => 0);

        foreach ($lessons as $l) {
            $sid  = (int) $l['section_id'];
            if (!isset($bag[$sid])) $sid = 0;

            $quiz = ((string) $l['lesson_type'] === 'quiz');
            $soon = ((string) $l['tq_status'] === 'soon');

            /* TQ-EMPTY-LESSON — هنا كذلك، لا في صفحة الباقة وحدها. كانت
               هذه الصفحة تعرض دروس البذرة الفارغة كاملة («٢٤ درسا» بأسماء
               وحدات ومدد) وكلها «يفتح بالاشتراك»، بينما صفحة الباقة تقول
               عن المادة نفسها «قيد الإعداد». سطحان يتناقضان في وعد واحد،
               والزائر يصدق الأسخى. */
            $has_video = (trim((string) $l['video_url']) !== '');
            if (!$quiz && !$has_video && !$soon) continue;

            $mins = $soon ? 0 : (int) round($this->hms_seconds((string) $l['duration']) / 60);

            $bag[$sid]['lessons'][] = array(
                'id'      => (int) $l['id'],
                'title'   => (string) $l['title'],
                'quiz'    => $quiz,
                'free'    => (!$soon && (int) $l['is_free'] === 1),
                'minutes' => $mins,
                'soon'    => $soon,
            );
            if ($soon) continue;          // يعرض ولا يعد

            $bag[$sid]['minutes'] += $mins;
            if ($quiz) $bag[$sid]['quizzes']++;

            $out['totals']['lessons']++;
            $out['totals']['minutes'] += $mins;
            if ($quiz) $out['totals']['quizzes']++;
            if ((int) $l['is_free'] === 1) $out['totals']['free']++;
        }

        /* وحدة بلا درس واحد لا تعرض: عنوان يفتح على فراغ يقرأ عطلا. */
        foreach ($bag as $sid => $u) {
            if (!$u['lessons']) continue;
            $out['units'][] = $u;
        }
        $out['totals']['units'] = count($out['units']);

        return $out;
    }

    /**
     * المدة نصا الى ثوان — `HH:MM:SS` او `MM:SS` او رقم.
     *
     * عمود `lesson.duration` نصي حر يكتبه الرافع: كل صيغة من هذه وردت فيه
     * فعلا. وقراءته بـ`(int)` وحدها تجعل «01:20:00» عشرين دقيقة… لا،
     * تجعلها ثانية واحدة.
     */
    private function hms_seconds($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') return 0;

        if (strpos($raw, ':') === false) return max(0, (int) $raw);

        $p = array_reverse(array_map('intval', explode(':', $raw)));
        $s = 0;
        foreach ($p as $i => $v) {
            if ($i > 2) break;
            $s += $v * pow(60, $i);
        }
        return max(0, $s);
    }

    /**
     * صف الباقة كما يقرؤه **عنوانها العام** — بالرمز أو بالمعرف.
     *
     * وهذه هي **بوابة `/plan/<كذا>` نفسها** لا نسخة منها: من أراد أن
     * يعرف هل تفتح باقة لمن ينقر رابطها فليسألها هي. والقيد `active = 1`
     * مقصود: الموقوفة سحبت من البيع، فصفحة تعرضها بسعرها تعرض
     * ما لا يباع.
     */
    public function plan_row($code)
    {
        $code = trim((string) $code);
        if ($code === '') return null;

        $this->db->from('plans')->where('active', 1);
        if (ctype_digit($code)) $this->db->where('id', (int) $code);
        else                    $this->db->where('code', $code);

        $row = $this->db->get()->row_array();
        return $row ? $row : null;
    }

    /**
     * العنوان العام لباقة — أو `null` إن لم يكن لها عنوان **يفتح**.
     *
     * رابط يبنى من `plans.code` رأسا يرد 404 في ثلاث حالات وقعت كلها:
     * باقة أوقفت بعد أن ربطت، وباقة كتب صفها من خارج اللوحة بلا رمز
     * (فيصير الرابط `‎/plan/‎` ولا قاعدة توجيه تلتقطه)، ورمز فيه ما يرده
     * `permitted_uri_chars`. والرقم مخرج للحالتين الأخيرتين لأن
     * `plan_row()` تقبل المعرف كما تقبل الرمز.
     *
     * ورمز كله أرقام يكتب بالمعرف أيضا: `plan_row()` تقرأ المقطع الرقمي
     * معرفا، فرابط `‎/plan/2024‎` يفتح الباقة رقم 2024 لا التي رمزها «2024».
     *
     * @param array|int $plan صف باقة أو معرفها
     */
    public function plan_url($plan)
    {
        if (!is_array($plan)) $plan = $this->plan_row($plan);
        if (!$plan || (int) $plan['id'] < 1) return null;

        /* الصف قد يأتي من قارئ لا يفحص `active` — والفحص هنا لا هناك،
           فمن يطلب رابطا يأخذ رابطا يفتح أو لا يأخذ شيئا. */
        if (isset($plan['active']) && (int) $plan['active'] !== 1) return null;

        $seg = trim((string) (isset($plan['code']) ? $plan['code'] : ''));
        if ($seg === '' || ctype_digit($seg) || !preg_match('#^[A-Za-z0-9._~-]+$#', $seg)) {
            $seg = (string) (int) $plan['id'];
        }

        return base_url('plan/' . $seg);
    }

    public function bundle_by_code($code)
    {
        /* ١ · الباقة ------------------------------------------------- */
        $plan = $this->plan_row($code);
        if (!$plan) return null;

        $features = array();
        if ((string) $plan['features'] !== '') {
            $decoded = json_decode($plan['features'], true);
            if (is_array($decoded)) $features = $decoded;
        }

        $b = array(
            'plan_id'  => (int) $plan['id'],
            'code'     => (string) $plan['code'],
            'name'     => (string) $plan['name_ar'],
            'note'     => (string) $plan['note'],
            'image'    => (string) $plan['image'],
            'price'    => (int) $plan['price'],          // بالهللات
            'period'   => (string) $plan['period'],
            'days'     => (int) $plan['duration_days'],
            'scope'    => (string) $plan['scope'],
            'stage'    => (string) $plan['stage'],
            'featured' => ((int) $plan['featured'] === 1),
            'features' => $features,
            'grades'   => array(),
            'subjects' => array(),
            'teachers' => array(),
            'books'    => array(),
            'totals'   => array('grades' => 0, 'subjects' => 0, 'units' => 0, 'lessons' => 0,
                                'quizzes' => 0, 'free' => 0, 'teachers' => 0, 'weeks' => 0,
                                'books' => 0),
        );

        /* ٢ · الصفوف ------------------------------------------------- *
           `scope_ids` قائمة بفواصل لأن الباقة تشمل مرحلة كاملة، و
           `scope_id` رقم واحد. وما عدا `grade` لا يقيد بصف: الباقة
           المجانية والشاملة تفتحان الكتالوج المنشور كله. */
        $gids = array();
        if ($b['scope'] === 'grade') {
            $gids = array_values(array_unique(array_filter(array_map('intval',
                        explode(',', (string) $plan['scope_ids'])))));
            if (!$gids && (int) $plan['scope_id'] > 0) $gids = array((int) $plan['scope_id']);
            /* باقة صفوف بلا صفوف: لا محتوى تصفه — ولا كتب كذلك،
               فالكتاب يدخل الباقة بصفه (TQ-BOOK-GRADE). */
            if (!$gids) return $this->bundle_books($b, array());
        }

        $grades = array();
        if ($gids) {
            $rows = $this->db->select('id, name_ar, name_en, `order`', false)
                             ->from('grades')->where_in('id', $gids)
                             ->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                             ->get()->result_array();
            foreach ($rows as $r) {
                $grades[(int) $r['id']] = array(
                    'id' => (int) $r['id'], 'name' => (string) $r['name_ar'], 'subjects' => array(),
                );
            }
        }

        /* ٣ · البرامج ------------------------------------------------ */
        $this->db->select('p.id, p.title, p.slug, p.short_description, p.thumbnail, p.badge,
                           p.grade_id, p.subject_id, p.teacher_id, p.course_id, p.expected_weeks,
                           s.name_ar AS subject_ar,
                           g.name_ar AS grade_ar,
                           TRIM(CONCAT(COALESCE(u.first_name,""), " ", COALESCE(u.last_name,""))) AS teacher_name,
                           u.image AS teacher_image, u.teacher_slug, u.is_public AS teacher_public', false)
                 ->from('paths p')
                 ->join('subjects s', 's.id = p.subject_id', 'left')
                 ->join('grades   g', 'g.id = p.grade_id',   'left')
                 ->join('users    u', 'u.id = p.teacher_id', 'left')
                 ->where('p.status', 'published');
        if ($gids) $this->db->where_in('p.grade_id', $gids);
        $this->db->order_by('p.tq_order', 'ASC')->order_by('p.id', 'ASC');
        $paths = $this->db->get()->result_array();
        /* ولا برنامج منشور في صفوفها — **وقد يكون فيها كتب**: باقة صف
           كتبه فيه ولا برنامج بعد كانت تعود فارغة، فتبيع محتوى وتصفه
           عدما. */
        if (!$paths) return $this->bundle_books($b, $gids);

        $cids = array();
        foreach ($paths as $p) if ((int) $p['course_id'] > 0) $cids[] = (int) $p['course_id'];
        $cids = array_values(array_unique($cids));

        /* ٤ · الوحدات — أول قراءة لـ`section` في واجهة للمستخدم ----- *
           كانت `ordered_lessons()` تضمها وترجع عنوانها، ثم يهدر في كل
           عرض. والمنهج بلا وحدات قائمة مسطحة من ثمانين سطرا لا يقرأ. */
        $units = array();   // course_id → section_id → عقدة
        if ($cids) {
            $rows = $this->db->select('id, title, course_id, `order`', false)
                             ->from('section')->where_in('course_id', $cids)
                             ->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                             ->get()->result_array();
            foreach ($rows as $r) {
                $units[(int) $r['course_id']][(int) $r['id']] = array(
                    'id' => (int) $r['id'], 'title' => (string) $r['title'],
                    'order' => (int) $r['order'], 'lessons' => array(),
                );
            }
        }

        /* ٥ · الدروس ------------------------------------------------- */
        $byCourse = array();   // course_id → إحصاء + دروس بلا وحدة
        if ($cids) {
            $rows = $this->db->select('id, title, duration, course_id, section_id,
                                       lesson_type, is_free, video_url, tq_status, `order`', false)
                             ->from('lesson')->where_in('course_id', $cids)
                             ->where('(COALESCE(`tq_status`, "published") = "published"'
                                   . ' OR `tq_status` = "soon")', null, false)
                             ->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                             ->get()->result_array();
            foreach ($rows as $r) {
                $cid  = (int) $r['course_id'];
                $sid  = (int) $r['section_id'];
                $quiz = ((string) $r['lesson_type'] === 'quiz');

                /* TQ-SOON-LESSON — نائب يحجز موضع درس لم يرفع مقطعه بعد.
                   يعرض موسوما «قيد الإعداد» ولا يعد في أي مجموع ولا يفتح
                   لأحد. وهو غير الدرس البذرة الفارغ: ذاك يبقى مخفيا لأن
                   لا أحد وعد به، وهذا وعد صريح بموعد. والعلامة في
                   `tq_status` لا في عمود جديد، لأن `Preview::free_lesson()`
                   يقارن `!== 'published'` فيرد النائب ٤٠٤ بلا سطر يضاف. */
                $soon = ((string) $r['tq_status'] === 'soon');
                $free = (!$soon && (int) $r['is_free'] === 1);

                /* TQ-EMPTY-LESSON — درس فيديو بلا مقطع لا يعرض ولا يعد.
                   وليس هذا تجميلا: الدرس الذي لا `video_url` له **لا يفتح
                   لأحد** — لا لزائر ولا لمشترك — فعده في «٢٤ درسا» وعد
                   يكتشفه المشتري بعد الدفع. ووسم «معاينة مجانية» عليه
                   أسوأ: يفتح صفحة تقول «لا يوجد مقطع لهذا الدرس بعد».
                   والاختبار مستثنى: المقطع ليس شرطه، وأسئلته هي محتواه.
                   و`video_url` يقرأ للعد وحده ولا يوضع في العقدة — فالعقدة
                   تطبع في صفحة عامة، ووضعه فيها يسرب مقاطع المدفوع. */
                $has_video = (trim((string) $r['video_url']) !== '');
                if (!$quiz && !$has_video && !$soon) continue;

                $node = array(
                    'id'       => (int) $r['id'],
                    'title'    => (string) $r['title'],
                    'duration' => $soon ? '' : (string) $r['duration'],
                    'type'     => (string) $r['lesson_type'],
                    'is_quiz'  => $quiz,
                    'is_free'  => $free,
                    'is_soon'  => $soon,
                );

                if (!isset($byCourse[$cid])) {
                    $byCourse[$cid] = array('lessons' => 0, 'quizzes' => 0, 'free' => 0,
                                            'videos' => 0, 'loose' => array());
                }
                /* النائب يعرض ولا يعد: رقم فوق قائمة فيها موضع محجوز
                   يجب أن يقابل ما يفتح فعلا، لا ما سيفتح. */
                if (!$soon) {
                    $byCourse[$cid]['lessons']++;
                    if ($quiz)      $byCourse[$cid]['quizzes']++;
                    if ($free)      $byCourse[$cid]['free']++;
                    if ($has_video) $byCourse[$cid]['videos']++;
                }

                /* درس بلا وحدة ليس خطأ يسكت عنه: يجمع في وحدة ضمنية
                   تسمى «الدروس» فيظهر بدل أن يسقط من المنهج صامتا. */
                if ($sid > 0 && isset($units[$cid][$sid])) $units[$cid][$sid]['lessons'][] = $node;
                else                                       $byCourse[$cid]['loose'][] = $node;
            }
        }

        /* التجميع ---------------------------------------------------- */
        $teachers = array();
        $weeks = 0;

        foreach ($paths as $p) {
            $cid   = (int) $p['course_id'];
            $stat  = isset($byCourse[$cid]) ? $byCourse[$cid]
                                            : array('lessons' => 0, 'quizzes' => 0, 'free' => 0,
                                                    'videos' => 0, 'loose' => array());

            $uts = array();
            if ($cid > 0 && isset($units[$cid])) {
                foreach ($units[$cid] as $u) {
                    if (!$u['lessons']) continue;        // وحدة فارغة لا تعرض
                    $uts[] = $u;
                }
            }
            if ($stat['loose']) {
                $uts[] = array('id' => 0, 'title' => 'الدروس', 'order' => 999,
                               'lessons' => $stat['loose']);
            }

            $slug = ((string) $p['slug'] !== '') ? (string) $p['slug'] : (string) $p['id'];
            $node = array(
                'id'           => (int) $p['id'],
                'title'        => (string) $p['title'],
                'blurb'        => (string) $p['short_description'],
                'image'        => ((string) $p['thumbnail'] !== '') ? (string) $p['thumbnail'] : 'subj-math',
                'badge'        => (string) $p['badge'],
                'grade_id'     => (int) $p['grade_id'],
                'grade'        => (string) $p['grade_ar'],
                'subject_id'   => (int) $p['subject_id'],
                'subject'      => (string) $p['subject_ar'],
                'teacher_id'   => (int) $p['teacher_id'],
                'teacher'      => trim((string) $p['teacher_name']),
                'course_id'    => $cid,
                'weeks'        => (int) $p['expected_weeks'],
                'href'         => base_url('path/' . $slug),
                'units'        => $uts,
                'lessons'      => (int) $stat['lessons'],
                'quizzes'      => (int) $stat['quizzes'],
                'free'         => (int) $stat['free'],
                /* TQ-EMPTY-LESSON — «جاهز» = فيه مقطع واحد على الأقل، لا
                   «فيه صفوف». فمادة كل دروسها عناوين بلا فيديو تقرأ «قيد
                   الإعداد»، وتعود وحدها حين ترفع مقاطعها. */
                'ready'        => ($cid > 0 && (int) $stat['videos'] > 0),
            );

            $b['subjects'][] = $node;
            $gid = (int) $p['grade_id'];
            if (isset($grades[$gid])) $grades[$gid]['subjects'][] = $node;

            /* والمجاميع تعد **الجاهز وحده**: ترويسة «ن مادة · م وحدة»
               فوق قائمة نصفها «قيد الإعداد» رقم لا يقابله ما تحته —
               والقارئ يعد بعينه فيجد الفرق. */
            if ($node['ready']) {
                $b['totals']['units']   += count($uts);
                $b['totals']['lessons'] += (int) $stat['lessons'];
                $b['totals']['quizzes'] += (int) $stat['quizzes'];
                $b['totals']['free']    += (int) $stat['free'];
            }
            $weeks = max($weeks, (int) $p['expected_weeks']);

            $tid = (int) $p['teacher_id'];
            if ($tid > 0 && trim((string) $p['teacher_name']) !== '') {
                if (!isset($teachers[$tid])) {
                    /* `public` يقرر هل يربط اسمه بصفحته: `instructor_page()`
                       تقرأ من `teachers()` التي تشترط `is_public`، فرابط
                       لمعلم غير معلن ينتهي إلى 404 من داخل صفحتنا. */
                    $teachers[$tid] = array(
                        'id'     => $tid,
                        'name'   => trim((string) $p['teacher_name']),
                        'image'  => (string) $p['teacher_image'],
                        'slug'   => (string) $p['teacher_slug'],
                        'public' => ((int) $p['teacher_public'] === 1),
                        'n'      => 0,
                    );
                }
                $teachers[$tid]['n']++;
            }
        }

        /* صف بلا برنامج منشور لا يعرض: الباقة تعد بما تفتحه فعلا */
        foreach ($grades as $g) if ($g['subjects']) $b['grades'][] = $g;

        $b['teachers'] = array_values($teachers);
        $b['totals']['grades']   = count($b['grades']);
        $b['totals']['subjects'] = 0;
        foreach ($b['subjects'] as $sx) if (!empty($sx['ready'])) $b['totals']['subjects']++;
        $b['totals']['teachers'] = count($b['teachers']);
        $b['totals']['weeks']    = $weeks;

        return $this->bundle_books($b, $gids);
    }

    /**
     * TQ-BOOK — يلحق بالباقة كتب صفوفها.
     *
     * وينادى من **مخارج `bundle_by_code()` الثلاثة** لا من آخرها وحده:
     * الدالة تعود مبكرا حين لا صفوف للباقة وحين لا برنامج منشور فيها،
     * وباقة صف كتبه فيه ولا برنامج بعد كانت تعود بلا كتاب واحد — تبيع
     * محتوى وتصفه فارغا.
     */
    private function bundle_books($b, $gids)
    {
        /* ═══ TQ-BOOK — وكتب صفوف الباقة معها ═══

           الباقة تفتح كتب صفوفها كما تفتح برامجها (TQ-BOOK-GRADE)،
           وصفحتها كانت تعد بالبرامج وحدها. فمن يشتري باقة صف فيها
           أربعة كتب لا يعرف بها حتى يفتح مكتبته بعد الدفع — وهي قيمة
           دفع ثمنها ولم تعرض عليه، وهو عين ما جاءت هذه الصفحة تصلحه
           في البرامج («ماذا سيدرس ابني؟»).

           **والمنشور بملف وحده يعد**: كتاب بلا PDF يعرض «قريبا» في
           الكتالوج ولا يفتح لأحد، وعده في «٤ كتب» يعد بما لا يسلم —
           وهو مبدأ `ready` في البرنامج نفسه. */
        $b['books'] = array();
        try {
            $CI = get_instance();
            $CI->load->model('taqdar_book_model', 'tq_bk_plan');

            $rows = ($b['scope'] === 'all')
                  ? $CI->tq_bk_plan->all_published()
                  : ($gids ? $CI->tq_bk_plan->books_for_grades($gids, true) : array());

            foreach ($rows as $r) {
                if (trim((string) $r['file']) === '') continue;   // لا يفتح، فلا يعد

                $bslug = ((string) $r['slug'] !== '') ? (string) $r['slug'] : (string) $r['id'];
                $b['books'][] = array(
                    'id'      => (int) $r['id'],
                    'title'   => (string) $r['title'],
                    'subject' => (string) $r['subject'],
                    'author'  => (string) $r['author'],
                    'pages'   => (int) $r['pages'],
                    'tone'    => (string) $r['tone'],
                    'cover'   => (string) $r['cover'],
                    'grade_id' => (int) $r['grade_id'],
                    'href'    => base_url('book/' . rawurlencode($bslug)),
                );
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-BOOK bundle books: ' . $e->getMessage());
        }
        $b['totals']['books'] = count($b['books']);
        return $b;
    }
}