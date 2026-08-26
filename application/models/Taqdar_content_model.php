<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * نصوص الموقع العام.
 *
 * ═══ لماذا وجد هذا النموذج ═══
 *
 * تسع صفحات عامة منشورة (`home` · `site_students` · `site_teachers` ·
 * `site_parents` · `site_books` · `plans` · `about_us` · `competitions` ·
 * `contact_us`) كانت نصوصها كلها مكتوبة في القوالب: العنوان الرئيسي،
 * ونص الهيرو، وتسميات الأزرار، وعناوين الأقسام. قيست فوجد فيها **صفر
 * مرجع ديناميكي** في ثمان منها.
 *
 * أي: تغيير كلمة في الصفحة الرئيسية كان يحتاج تحرير ملف ودفعا إلى
 * `origin/main` ونشرا على الخادم. ولا شيء في «لوحة الإدارة» يديره —
 * وهي بحسب تعريفها مصدر الحقيقة لما ينشر.
 *
 * ═══ كيف يعمل ═══
 *
 * مخزن مفاتيح: صف لكل (صفحة، مفتاح، قيمة). و`tq_text()` تقرأ منه،
 * فإن لم تجد المفتاح **رجعت إلى النص المكتوب في القالب**. ولذلك:
 *
 *   ١ — لا صفحة تنكسر بإضافة هذا النظام: الجدول الفارغ يعني أن كل
 *       صفحة تعرض ما كانت تعرضه حرفا بحرف.
 *   ٢ — الترحيل يجري صفحة صفحة بلا يوم تعطل.
 *   ٣ — من يفرغ حقلا في اللوحة يرجع النص الأصلي لا يمسح العنوان —
 *       وهذا هو السلوك المتوقع من «إعادة إلى الافتراضي».
 *
 * والقيم تخرج **مهربة** في العرض (`html_escape` في القالب): محرر النص
 * في اللوحة يكتب نصا لا HTML، وقبول الوسوم هنا يجعل حقل عنوان بابا
 * لحقن سكربت في كل صفحة عامة.
 */
class Taqdar_content_model extends CI_Model
{
    /** يفحص مرة واحدة لكل طلب — لا في كل قراءة مفتاح. */
    private $schema_checked = false;

    /**
     * سجل الحقول القابلة للتحرير.
     *
     * هذا **العقد** بين القالب واللوحة: المفتاح المكتوب هنا هو نفسه
     * المكتوب في `tq_text()` داخل القالب. ومفتاح في أحدهما بلا الآخر
     * إما حقل يحرر ولا يظهر، أو نص يظهر ولا يحرر — وكلاهما يكتشف
     * بالمصادفة وحدها. فالسجل واحد ولا يكرر.
     *
     * `type`: line (سطر) · text (فقرة) · label (تسمية زر قصيرة)
     */
    public function registry($page = null)
    {
        $pages = array(

            'home' => array(
                'title'  => 'الصفحة الرئيسية',
                'route'  => '',
                'icon'   => 'home',
                'lead'   => 'أول ما يراه الزائر: الهيرو وشريط المميزات.',
                'fields' => array(
                    'hero_eyebrow'  => array('label' => 'السطر الذهبي فوق العنوان', 'type' => 'label',
                                             'default' => 'منصة تعليمية سعودية'),
                    'hero_title_1'  => array('label' => 'العنوان — السطر الأول', 'type' => 'line',
                                             'default' => 'نبني العقول'),
                    'hero_title_2'  => array('label' => 'العنوان — السطر الثاني', 'type' => 'line',
                                             'default' => 'ونصنع'),
                    'hero_title_3'  => array('label' => 'الكلمة المذهبة', 'type' => 'label',
                                             'default' => 'المستقبل',
                                             'hint' => 'تعرض بلون الهوية الذهبي في آخر العنوان.'),
                    'hero_lede'     => array('label' => 'نص الهيرو', 'type' => 'text',
                                             'default' => 'منصة تعليمية سعودية على المنهج الرسمي: برامج يبنيها معلمون، وتقييم يقيس الإتقان لا الحضور، وتقارير يراها ولي الأمر أولا بأول.'),
                    'hero_cta_1'    => array('label' => 'الزر الأول', 'type' => 'label',
                                             'default' => 'ابدأ رحلة التعلم'),
                    'hero_cta_2'    => array('label' => 'الزر الثاني', 'type' => 'label',
                                             'default' => 'تصفح البرامج'),
                    /* ثلاث لا أربع: شريط الهيرو ثلاثة أعمدة، وحقل بلا
                       أثر في الصفحة يضلل من يحرره. */
                    'feat_1_t'      => array('label' => 'الميزة الأولى — العنوان', 'type' => 'label', 'default' => 'تعلم تفاعلي'),
                    'feat_1_d'      => array('label' => 'الميزة الأولى — الوصف', 'type' => 'label', 'default' => 'تجربة ممتعة وفعالة'),
                    'feat_2_t'      => array('label' => 'الميزة الثانية — العنوان', 'type' => 'label', 'default' => 'معلمون متميزون'),
                    'feat_2_d'      => array('label' => 'الميزة الثانية — الوصف', 'type' => 'label', 'default' => 'ذوو خبرة عالية'),
                    'feat_3_t'      => array('label' => 'الميزة الثالثة — العنوان', 'type' => 'label', 'default' => 'متابعة المنهاج'),
                    'feat_3_d'      => array('label' => 'الميزة الثالثة — الوصف', 'type' => 'label', 'default' => 'تقرير يومي للأهل'),
                ),
            ),

            'site_students' => array(
                'title'  => 'صفحة الطلاب',
                'route'  => 'students',
                'icon'   => 'users',
                'lead'   => 'ما يقال للطالب قبل أن يسجل.',
                'fields' => array(
                    'hero_title' => array('label' => 'العنوان', 'type' => 'line', 'default' => 'الطلاب'),
                    'hero_sub'   => array('label' => 'السطر تحت العنوان', 'type' => 'line',
                                          'default' => 'رحلتك التعليمية تبدأ من هنا'),
                    'hero_lede'  => array('label' => 'نص التعريف', 'type' => 'text',
                                          'default' => 'منصة تقدر تمنحك كل ما تحتاجه لتتعلم بذكاء، وتطور مهاراتك، وتحقق طموحاتك بثقة.'),
                    'hero_cta_1' => array('label' => 'الزر الأول', 'type' => 'label', 'default' => 'ابدأ التعلم الآن'),
                    'hero_cta_2' => array('label' => 'الزر الثاني', 'type' => 'label', 'default' => 'استكشاف البرامج'),
                ),
            ),

            'site_teachers' => array(
                'title'  => 'صفحة المعلمين',
                'route'  => 'teachers',
                'icon'   => 'graduation',
                'lead'   => 'ما يقال للمعلم قبل أن يتقدم بطلب.',
                'fields' => array(
                    'hero_title' => array('label' => 'العنوان', 'type' => 'line', 'default' => 'المعلمون'),
                    'hero_sub'   => array('label' => 'السطر تحت العنوان', 'type' => 'line',
                                          'default' => 'خبرات ملهمة.. تعليم يرتقي'),
                    'hero_lede'  => array('label' => 'نص التعريف', 'type' => 'text',
                                          'default' => 'نخبة من المعلمين المتخصصين المتميزين في تقديم تجارب تعليمية فريدة تلهم العقول وتنمي المهارات.'),
                ),
            ),

            'site_parents' => array(
                'title'  => 'صفحة أولياء الأمور',
                'route'  => 'parents',
                'icon'   => 'heart',
                'lead'   => 'ما يقال لولي الأمر قبل أن يربط ابنه.',
                'fields' => array(
                    'hero_title' => array('label' => 'العنوان', 'type' => 'line', 'default' => 'أولياء الأمور'),
                    'hero_sub'   => array('label' => 'السطر تحت العنوان', 'type' => 'line',
                                          'default' => 'شركاء في رحلة تعليم أبنائنا'),
                    'hero_lede'  => array('label' => 'نص التعريف', 'type' => 'text',
                                          'default' => 'نوفر لكم كل ما تحتاجونه لمتابعة تقدم أبنائكم التعليمي، لدعمهم وتحفيزهم نحو مستقبل مشرق.'),
                    'hero_cta_1' => array('label' => 'الزر الأول', 'type' => 'label', 'default' => 'ابدأ الآن'),
                    'hero_cta_2' => array('label' => 'الزر الثاني', 'type' => 'label', 'default' => 'استكشف المنصة'),
                    'quotes_title' => array('label' => 'عنوان قسم الآراء', 'type' => 'line',
                                            'default' => 'ماذا يقول أولياء الأمور؟',
                                            'hint' => 'والآراء نفسها تحرر من شاشة «آراء أولياء الأمور».'),
                ),
            ),

            /* صفحة الكتب صارت الكتالوج الموحد: الكتب نوع فيه لا صفحة
               وحدها. ومفاتيح `site_books` القديمة تبقى في `site_content`
               بلا قارئ — وحذفها من هنا يكفي: السجل هو ما يعرض، والصف
               اليتيم لا يظهر في اللوحة ولا يقرؤه قالب. */
            'site_catalog' => array(
                'title'  => 'صفحة المواد والبرامج',
                'route'  => 'catalog',
                'icon'   => 'book',
                'lead'   => 'رأس الكتالوج الموحد — والمحتوى نفسه يحرر من شاشات «البرامج» و«الكتب» و«الباقات» و«المسابقات».',
                'fields' => array(
                    'hero_title' => array('label' => 'العنوان', 'type' => 'line',
                                          'default' => 'المواد والبرامج التعليمية'),
                    'hero_lede'  => array('label' => 'نص التعريف', 'type' => 'text',
                                          'default' => 'كل ما تقدمه تقدر في مكان واحد: باقات المراحل وبرامج المواد وكتب المنهج والمسابقات — ابحث ورشح حتى تصل إلى ما يخص صفك.'),
                ),
            ),

            'plans' => array(
                'title'  => 'صفحة الباقات',
                'route'  => 'plans',
                'icon'   => 'card',
                'lead'   => 'رأس صفحة الباقات — وأسعارها ومزاياها تحرر من شاشة «الباقات».',
                'fields' => array(
                    'hero_title' => array('label' => 'العنوان', 'type' => 'line',
                                          'default' => 'الباقات'),
                    'hero_lede'  => array('label' => 'نص التعريف', 'type' => 'text',
                                          'default' => 'منهج المرحلة كاملا في باقة واحدة: مواد الصف وبرامجها ودروسها واختباراتها — تختار مرة، ويبقى الباب مفتوحا العام الدراسي كله.'),
                ),
            ),

            'about_us' => array(
                'title'  => 'صفحة من نحن',
                'route'  => 'about',
                'icon'   => 'globe',
                'lead'   => 'قصة المنصة ورسالتها.',
                'fields' => array(
                    'hero_title' => array('label' => 'العنوان', 'type' => 'line', 'default' => 'عن منصة تقدر'),
                    'hero_sub'   => array('label' => 'السطر تحت العنوان', 'type' => 'line',
                                          'default' => 'تعليم يلهم، وتمكين يبني المستقبل'),
                    'hero_lede'  => array('label' => 'نص التعريف', 'type' => 'text',
                                          'default' => 'تقدر منصة تعليمية سعودية رائدة تهدف إلى تقديم تجربة تعلم متكاملة تجمع بين الجودة والتقنية والقيم، لنسهم في بناء جيل واع ومبدع وقادر على صناعة المستقبل.'),
                ),
            ),

            'competitions' => array(
                'title'  => 'صفحة المسابقات',
                'route'  => 'competitions',
                'icon'   => 'trophy',
                'lead'   => 'رأس الصفحة — والمسابقات نفسها تحرر من شاشة «المسابقات».',
                'fields' => array(
                    'hero_title' => array('label' => 'العنوان', 'type' => 'line', 'default' => 'المسابقات'),
                    'hero_lede'  => array('label' => 'نص التعريف', 'type' => 'text',
                                          'default' => 'تحديات دورية على نمط أسئلة نافس، يقيس بها الطالب مستواه ويقارن تقدمه.'),
                ),
            ),

            'contact_us' => array(
                'title'  => 'صفحة تواصل معنا',
                'route'  => 'contact',
                'icon'   => 'mail',
                'lead'   => 'رأس الصفحة — والبريد والهاتف يحرران من «إعدادات المنصة».',
                'fields' => array(
                    'hero_title' => array('label' => 'العنوان', 'type' => 'line', 'default' => 'تواصل معنا'),
                    'hero_sub'   => array('label' => 'السطر تحت العنوان', 'type' => 'line',
                                          'default' => 'نحن هنا لمساعدتك'),
                    'hero_lede'  => array('label' => 'نص التعريف', 'type' => 'text',
                                          'default' => 'فريق تقدر جاهز للإجابة على استفساراتك وتقديم الدعم والمساعدة في رحلتك التعليمية. لا تتردد في التواصل معنا في أي وقت.'),
                ),
            ),
        );

        if ($page === null) return $pages;
        return isset($pages[$page]) ? $pages[$page] : null;
    }

    /**
     * ينشئ الجدول عند أول استعمال.
     *
     * لا هجرات في هذا المستودع، والبديل ملف SQL يستورد بيد على الخادم —
     * وينسى، فتسقط الشاشة عند من نشر ولم يستورد. والإنشاء هنا متكرر
     * الأمان (`IF NOT EXISTS`) ويفحص مرة واحدة لكل طلب.
     */
    public function ensure_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `site_content` (
                    `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `page`       varchar(64)  NOT NULL,
                    `ckey`       varchar(64)  NOT NULL,
                    `value`      text         DEFAULT NULL,
                    `updated_at` datetime     DEFAULT NULL,
                    `updated_by` int(10) unsigned DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_page_key` (`page`, `ckey`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            // بلا جدول تعرض الصفحات نصوص قوالبها — وهو ما كانت تفعله أصلا
        }

        /* الآراء صفوف لا مفاتيح، فجدولها الثاني.
           وهي محتوى الموقع العام نفسه، فموضعها هذا النموذج لا نموذج
           ثالث: من يحرر نص صفحة أولياء الأمور يحرر آراءها في الشاشة
           المجاورة، ومن ينشر الموقع لا يستورد ملفين. */
        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_testimonials` (
                    `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `name`       varchar(120) NOT NULL,
                    `role`       varchar(160) DEFAULT NULL,
                    `body`       text         NOT NULL,
                    `rating`     tinyint(1) unsigned NOT NULL DEFAULT 5,
                    `status`     varchar(16)  NOT NULL DEFAULT \'published\',
                    `tq_order`   int(11)      NOT NULL DEFAULT 0,
                    `created_at` datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `ix_live` (`status`, `tq_order`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            // بلا جدول تعرض الصفحة آراءها المكتوبة في القالب
        }
    }

    /**
     * آراء أولياء الأمور المنشورة — للزائر.
     *
     * والفارغ ليس عطلا: قاعدة بلا صف تعني أن الصفحة تعرض الآراء
     * المكتوبة في قالبها حرفا بحرف، كما يفعل `tq_text()` بالنصوص. فلا
     * يوم واحد تفتح فيه الصفحة على قسم فارغ لأن أحدا لم يملأ اللوحة بعد.
     */
    public function testimonials($limit = 24)
    {
        $this->ensure_schema();

        try {
            $rows = $this->db->where('status', 'published')
                             ->order_by('tq_order', 'ASC')->order_by('id', 'ASC')
                             ->limit((int) $limit)
                             ->get('tq_testimonials')->result_array();
        } catch (Throwable $e) {
            return array();
        }

        $out = array();
        foreach ($rows as $r) {
            if (trim((string) $r['body']) === '') continue;
            $stars = (int) $r['rating'];
            $out[] = array(
                'name'   => (string) $r['name'],
                'role'   => (string) $r['role'],
                'body'   => (string) $r['body'],
                'rating' => ($stars < 0 ? 0 : ($stars > 5 ? 5 : $stars)),
            );
        }
        return $out;
    }

    /**
     * كل نصوص صفحة، مقروءة مرة واحدة.
     *
     * القالب يستدعي `tq_text()` عشرين مرة في الصفحة الواحدة، واستعلام
     * لكل مفتاح يعني عشرين رحلة إلى القاعدة في صفحة عامة تقرأ آلاف
     * المرات في اليوم. فتقرأ الصفحة كلها بضمة واحدة وتخزن في الذاكرة.
     */
    public function page_values($page)
    {
        static $cache = array();
        if (isset($cache[$page])) return $cache[$page];

        $this->ensure_schema();

        $out = array();
        try {
            $rows = $this->db->where('page', $page)->get('site_content')->result_array();
            foreach ($rows as $r) {
                // الفارغ ليس قيمة: يعني «أرجع إلى نص القالب»
                if (trim((string) $r['value']) !== '') $out[$r['ckey']] = $r['value'];
            }
        } catch (Throwable $e) {
            $out = array();
        }

        $cache[$page] = $out;
        return $out;
    }

    /**
     * يحفظ نصوص صفحة.
     *
     * الحقل الفارغ **يحذف صفه** ولا يخزن فراغا: القيمة الفارغة في
     * القاعدة تعني عنوانا فارغا في الصفحة، والمقصود دائما «أرجع إلى
     * الافتراضي». ولا يقرأ إلا ما ورد في السجل — فحقل يضاف من متصفح
     * المستخدم لا يجد له مفتاحا يكتب فيه.
     */
    public function save_page($page, $post)
    {
        $spec = $this->registry($page);
        if (!$spec) return array('ok' => false, 'errors' => array('صفحة غير معروفة.'));

        $this->ensure_schema();

        $now    = date('Y-m-d H:i:s');
        $actor  = (int) $this->session->userdata('user_id');
        $set    = 0;
        $reset  = 0;

        foreach ($spec['fields'] as $ckey => $f) {
            $raw = isset($post[$ckey]) ? trim((string) $post[$ckey]) : '';

            // الوسوم تجرد: الحقل يكتب نصا، وقبول HTML هنا يفتح كل صفحة
            // عامة لحقن سكربت من شاشة تحرير عنوان
            $raw = strip_tags($raw);

            $exists = $this->db->where('page', $page)->where('ckey', $ckey)
                               ->count_all_results('site_content') > 0;

            if ($raw === '') {
                if ($exists) { $this->db->where('page', $page)->where('ckey', $ckey)->delete('site_content'); $reset++; }
                continue;
            }

            $row = array('value' => $raw, 'updated_at' => $now, 'updated_by' => $actor);
            if ($exists) {
                $this->db->where('page', $page)->where('ckey', $ckey)->update('site_content', $row);
            } else {
                $this->db->insert('site_content', array_merge($row, array('page' => $page, 'ckey' => $ckey)));
            }
            $set++;
        }

        $this->load->model('taqdar_admin_model');
        $this->taqdar_admin_model->audit('site_content_update', 'site_content:' . $page, null,
                                          array('set' => $set, 'reset' => $reset));

        return array('ok' => true, 'set' => $set, 'reset' => $reset);
    }

    /** كم حقلا حرر في كل صفحة — الشاشة تعرضه ليعرف المسؤول ما لمسه. */
    public function edited_counts()
    {
        $this->ensure_schema();
        $out = array();
        try {
            $rows = $this->db->select('page, COUNT(*) n', false)
                             ->group_by('page')->get('site_content')->result_array();
            foreach ($rows as $r) $out[$r['page']] = (int) $r['n'];
        } catch (Throwable $e) {
            $out = array();
        }
        return $out;
    }
}
