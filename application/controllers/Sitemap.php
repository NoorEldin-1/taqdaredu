<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * خريطة الموقع — فهرس وأقسام.
 *
 * ═══ لماذا أعيدت كتابتها 2026-09-09 ═══
 *
 * كانت خريطةً واحدة بـ١٤٧ رابطًا تحمل ثلاثة عيوب مقيسة:
 *
 * ١ — **تدعو إلى فهرسة ما لا يُفهرس**: `/login` و`/sign_up`
 *     و`login/forgot_password_request` تحمل `noindex` في ترويستها،
 *     والخريطة **طلبُ فهرسة**. فالوسم يقول لا والخريطة تقول نعم.
 * ٢ — **تعلن نسخًا لا أصولًا**: ٣١ رابط `‎/home/course/{عنوان}/{معرف}`
 *     و٥ روابط `‎/home/courses?category=` — وكلّها إمّا تعيد التوجيه إلى
 *     الروابط النظيفة أو صارت بعد `TQ-SEO-LEGACY` تشير بكنونيكالها إلى
 *     `‎/path/{slug}`. فالخريطة تعلن ما ليس بأصل.
 * ٣ — **تُغفل أكبر أقسام الموقع**: ١٠٩ كتب و٣٠٦ ملخّصات و٢١ معلّمًا
 *     وصفحاتها كلّها حيّة، ولا رابط منها في الخريطة.
 *
 * وكلّها بلا `lastmod` — والزاحف يستعمله ليقرّر ما يعيد زيارته.
 *
 * ═══ لماذا فهرس لا ملفّ واحد ═══
 *
 * ليس للحدّ (٥٠ ألفًا بعيد)، بل **للتشخيص**: Search Console يعرض
 * «مُفهرَس / مُستبعَد» لكلّ ملفّ على حدة. فحين تُستبعَد صفحات، يقول
 * الفهرس المقسَّم أيَّ نوعٍ استُبعد — كتبٌ أم برامج أم معلّمون — بدل
 * رقمٍ واحد لا يُفكَّك.
 *
 * ═══ الملخّصات دخلت بعد أن استوفت الحدّ ═══
 *
 * كانت خارج الخريطة عمدًا يوم كانت كلّ صفحة إطارَ درايف وسطرَ وصف
 * مولَّد — وإعلان ٣٠٦ صفحات نحيفة دفعةً واحدة يجرّ تقييم النطاق كلّه.
 * ثمّ أُثري نصّها (‏٧٤–١٠١ كلمة فريدة لكلّ صفحة، من بيانات صفّها
 * نفسه) وأُضيف لها وسمُ `Book`+`LearningResource` وروابطُ داخلية،
 * فدخلت. **والبوّابة تبقى**: صفحة بلا وصف لا تُعلَن.
 */
class Sitemap extends CI_Controller {

    /** ما لا يُعلَن: مسارات تحمل `noindex` أو تعيد التوجيه. */
    private $skip = array(
        'login', 'sign_up', 'login/forgot_password_request', 'checkout',
        'cart', 'payment', 'competitions',
    );

    /** الروابط القديمة ومقابلها النظيف — الأصل يُعلَن، لا الذي يوجّه إليه. */
    private $clean = array(
        'home/courses'              => 'catalog',
        'home/contact_us'           => 'contact',
        'home/about_us'             => 'about',
        'home/privacy_policy'       => 'privacy',
        'home/terms_and_condition'  => 'terms',
        'home/faq'                  => 'faq',
        'home/refund_policy'        => 'refund',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
        $this->load->helper('taqdar_site');
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');
        $this->user_model->check_session_data();
    }

    /** الفهرس: لا يُعلن قسمًا فارغًا — ملفٌّ بلا روابط خطأٌ في Search Console. */
    public function index()
    {
        $parts = array();
        foreach (array('static', 'paths', 'books', 'summaries', 'teachers', 'blog', 'plans') as $p) {
            $rows = $this->rows($p);
            if (!$rows) continue;
            $last = 0;
            foreach ($rows as $r) if (!empty($r['lastmod'])) $last = max($last, (int) $r['lastmod']);
            $parts[] = array('loc' => base_url('sitemap-' . $p . '.xml'), 'lastmod' => $last);
        }

        header('Content-Type: application/xml; charset=UTF-8');
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');
        foreach ($parts as $p) {
            $n = $xml->addChild('sitemap');
            $n->addChild('loc', htmlspecialchars($p['loc'], ENT_XML1));
            if ($p['lastmod']) $n->addChild('lastmod', date('c', $p['lastmod']));
        }
        echo $xml->asXML();
    }

    /** قسم واحد. الاسم المجهول يردّ ٤٠٤ لا خريطةً فارغة. */
    public function part($name = '')
    {
        $name = preg_replace('~[^a-z]~', '', (string) $name);
        $rows = $this->rows($name);
        if ($rows === null) { show_404(); return; }

        header('Content-Type: application/xml; charset=UTF-8');
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');
        foreach ($rows as $r) {
            $n = $xml->addChild('url');
            $n->addChild('loc', htmlspecialchars($r['loc'], ENT_XML1));
            if (!empty($r['lastmod'])) $n->addChild('lastmod', date('c', (int) $r['lastmod']));
            $n->addChild('changefreq', isset($r['freq']) ? $r['freq'] : 'monthly');
            $n->addChild('priority', isset($r['pri']) ? $r['pri'] : '0.6');
        }
        echo $xml->asXML();
    }

    /* ═══════════════════════ الأقسام ═══════════════════════ */

    /** @return array|null صفوف القسم، أو `null` لاسم لا وجود له. */
    private function rows($name)
    {
        switch ($name) {
            case 'static':   return $this->rows_static();
            case 'paths':    return $this->rows_paths();
            case 'books':    return $this->rows_books();
            case 'summaries':return $this->rows_summaries();
            case 'teachers': return $this->rows_teachers();
            case 'blog':     return $this->rows_blog();
            case 'plans':    return $this->rows_plans();
        }
        return null;
    }

    private function rows_static()
    {
        $out = array(array('loc' => base_url(), 'freq' => 'weekly', 'pri' => '1.0'));

        /* الثابتة من الإعداد نفسه — فما يضيفه المسؤول من اللوحة يظهر —
           لكن بعد تنقيته: المُستبعَد يُسقَط، والقديم يُترجَم إلى نظيفه. */
        $routes = json_decode((string) get_settings('sitemap_xml'), true);
        if (!is_array($routes)) $routes = array();
        /* ما لقسمه ملفٌّ خاصّ لا يُعلَن هنا ثانيةً. */
        $seen = array('' => true, 'blog' => true, 'books' => true,
                      'teachers' => true, 'plans' => true, 'summaries' => true);
        foreach ($routes as $r) {
            $r = trim((string) $r, '/ ');
            if (isset($this->clean[$r])) $r = $this->clean[$r];
            if ($r === '' || in_array($r, $this->skip, true) || isset($seen[$r])) continue;
            if (strpos($r, 'home/course/') === 0) continue;   /* نسخة، وكنونيكالها البرنامج */
            $seen[$r] = true;
            $out[] = array('loc' => base_url($r), 'pri' => '0.7');
        }

        /* الأقسام التي بُنيت بعد آخر تحرير للإعداد فغابت عنه.
           ⚠ بلا `books` و`teachers` و`plans`: لكلٍّ قسمُه الخاصّ يعلن
           صفحته الأولى، وتكرار الرابط في ملفَّين إرباكٌ لا فائدة فيه. */
        foreach (array('catalog',
                       'about', 'contact', 'faq', 'privacy', 'terms', 'refund') as $r) {
            if (isset($seen[$r])) continue;
            $seen[$r] = true;
            $out[] = array('loc' => base_url($r), 'pri' => '0.8');
        }

        /* تصنيفات الكتالوج — بالمعامِل الذي **يرشّح فعلًا**.

           ⚠ الوارث كان يعلن `?category=`، وقياسه اليوم: `/catalog`
           و`/catalog?category=primary` يعرضان الشيء نفسه حرفًا بحرف —
           المعامِل ميّت. والكتالوج يرشّح بـ`?cat=` و`?grade=` (‏٩ عناصر
           مقابل ٦ لغير المرشَّح). **فمعامِل لا يرشّح ليس صفحةً، وإعلانه
           إعلانُ نسخة.** */
        if ($this->db->table_exists('category')) {
            $cats = $this->db->select('slug')->where('parent', 0)->get('category')->result_array();
            foreach ($cats as $c) {
                if ((string) $c['slug'] === '') continue;
                $out[] = array('loc' => base_url('catalog?cat=' . rawurlencode($c['slug'])), 'pri' => '0.6');
            }
        }
        return $out;
    }

    /**
     * البرامج — المنشور **الذي فيه درس** وحده.
     *
     * ٢٠ من ٤٤ برنامجًا منشورًا بلا مقرَّر: صفحاتها تقول «قيد الإعداد»
     * ولا شيء فيها يُفهرَس. وإعلانها في الخريطة دعوةٌ إلى صفحة فارغة —
     * وهو ما يقيسه جوجل «محتوى ضعيفًا» على النطاق لا على الصفحة.
     * فتبقى حيّةً للزائر (رابطها يعمل ويقول متى تُفتح) وخارج الخريطة
     * حتى يدخلها درس. و`lastmod` من المقرَّر، فجدول `paths` بلا تاريخ.
     */
    private function rows_paths()
    {
        if (!$this->db->table_exists('paths')) return array();
        $rows = $this->db->select('p.id, p.slug, c.last_modified, c.date_added', false)
                         ->from('paths p')
                         ->join('course c', 'c.id = p.course_id', 'inner')
                         ->where('p.status', 'published')
                         ->where('(SELECT COUNT(*) FROM lesson l WHERE l.course_id = c.id) > 0', null, false)
                         ->get()->result_array();
        $out = array();
        foreach ($rows as $r) {
            $slug = ((string) $r['slug'] !== '') ? $r['slug'] : $r['id'];
            $out[] = array(
                'loc'     => base_url('path/' . $slug),
                'lastmod' => (int) ($r['last_modified'] ?: $r['date_added']),
                'freq'    => 'weekly',
                'pri'     => '0.9',
            );
        }
        return $out;
    }

    /**
     * الكتب — دون الملخّصات.
     *
     * التمييز بالبادئة `sum-` في `slug`: هي ما تكتبه أداة الاستيراد،
     * ولا عمود نوع في الجدول. والملخّصات تنتظر حدّ المحتوى (انظر رأس
     * الملفّ)، فاستثناؤها هنا **مؤقّت ومقصود** لا سهو.
     */
    private function rows_books()
    {
        if (!$this->db->table_exists('books')) return array();
        $rows = $this->db->select('slug, grade_id, last_modified, date_added')
                         ->from('books')
                         ->where('status', 'published')
                         ->where("(slug IS NOT NULL AND slug <> '' AND slug NOT LIKE 'sum-%')", null, false)
                         ->get()->result_array();
        $out = array(array('loc' => base_url('books'), 'pri' => '0.8'));
        $grades = array();
        foreach ($rows as $r) {
            $out[] = array(
                'loc'     => base_url('book/' . $r['slug']),
                'lastmod' => (int) ($r['last_modified'] ?: $r['date_added']),
                'pri'     => '0.6',
            );
            if ((int) $r['grade_id'] > 0) $grades[(int) $r['grade_id']] = true;
        }

        /* فهارس الصفوف: صفحات حقيقية تجمع كتب صفٍّ واحد.

           الرابط **لا يُبنى هنا**: `tqs_grade_books_url()` هي التي تبنيه
           للشريط في `/books`، وسبيكته مشتقّة من `grades`.`order` لا من
           الاسم (‏`primary-4-10`) — فبناؤه ثانيةً بـ`slugify` كان يُخرج
           سبيكة عربية مرمَّزة، أي ٨ روابط ٤٠٤ داخل الخريطة. */
        if ($grades && $this->db->table_exists('grades')
            && function_exists('tqs_grade_books_url')) {
            $gr = $this->db->select('id, `order`', false)->where_in('id', array_keys($grades))
                           ->get('grades')->result_array();
            foreach ($gr as $g) {
                $out[] = array('loc' => tqs_grade_books_url($g), 'pri' => '0.7');
            }
        }
        return $out;
    }

    /**
     * الملخّصات — ما استوفى منها حدّ المحتوى.
     *
     * البوّابة صريحة في الاستعلام لا في التعليق: صفحةٌ وصفُها فارغ أو
     * أقصر من مئة حرف ليست صفحةً في نظر الفهرس، فلا تُعلَن حتى تُثرى.
     */
    private function rows_summaries()
    {
        if (!$this->db->table_exists('books')) return array();
        $rows = $this->db->select('slug, last_modified, date_added')
                         ->from('books')
                         ->where('status', 'published')
                         ->where("(slug LIKE 'sum-%')", null, false)
                         ->where('CHAR_LENGTH(description) >= 100', null, false)
                         ->get()->result_array();
        $out = array(array('loc' => base_url('summaries'), 'pri' => '0.8'));
        foreach ($rows as $r) {
            $out[] = array(
                'loc'     => base_url('book/' . $r['slug']),
                'lastmod' => (int) ($r['last_modified'] ?: $r['date_added']),
                'pri'     => '0.6',
            );
        }
        return $out;
    }

    /** المعلّمون — ملفّات العرض وحدها؛ الحسابات الحقيقية ليست صفحات. */
    private function rows_teachers()
    {
        $out = array(array('loc' => base_url('teachers'), 'pri' => '0.8'));
        $rows = $this->db->select('id')->where('is_public', 1)
                         ->where('is_instructor', 1)->get('users')->result_array();
        foreach ($rows as $r) {
            $out[] = array('loc' => base_url('instructor/' . (int) $r['id']), 'pri' => '0.6');
        }
        return $out;
    }

    /**
     * المدوّنة — الرابط اللاتينيّ المخزَّن في `blogs`.`slug`.
     *
     * ⚠ للمقال **رابطان حيّان**: `‎/blog/{slug}/{id}` وهو ما تربطه صفحة
     * المدوّنة نفسها، و`‎/blog/details/{عنوان عربيّ}/{id}` وهو ما كانت
     * الخريطة تولّده بـ`slugify($title)`. وكلاهما يردّ ٢٠٠ ويشير
     * بكنونيكاله إلى نفسه — أي **تسع نسخ مزدوجة** أعلنَت الخريطةُ منها
     * الوجهَ الذي لا يربطه الموقع. فالمعلَن الآن ما في القاعدة.
     */
    private function rows_blog()
    {
        $out = array(array('loc' => base_url('blog'), 'freq' => 'weekly', 'pri' => '0.7'));
        foreach ($this->crud_model->get_all_blogs()->result_array() as $b) {
            $slug = trim((string) (isset($b['slug']) ? $b['slug'] : ''));
            if ($slug === '') continue;   /* بلا سبيكة لا رابط قانونيّ، فلا يُعلَن */
            $out[] = array(
                'loc'     => base_url('blog/' . $slug . '/' . (int) $b['blog_id']),
                'lastmod' => (int) (!empty($b['updated_date']) ? $b['updated_date']
                                    : (isset($b['added_date']) ? $b['added_date'] : 0)),
                'pri'     => '0.6',
            );
        }
        /* ⚠ `/blogs?category=` **لا يرشّح**: أربع تصنيفات وأربع صفحات
           تعرض المقالات الثمانية نفسها. فهي أربع نسخ من `/blogs`، ولا
           تُعلَن. وتعود يوم يعمل الترشيح. */
        return $out;
    }

    private function rows_plans()
    {
        $out = array(array('loc' => base_url('plans'), 'freq' => 'weekly', 'pri' => '0.9'));
        if ($this->db->table_exists('plans')) {
            foreach ($this->db->select('code')->where('active', 1)->get('plans')->result_array() as $p) {
                if ((string) $p['code'] === '') continue;
                $out[] = array('loc' => base_url('plan/' . $p['code']), 'pri' => '0.8');
            }
        }
        return $out;
    }
}
