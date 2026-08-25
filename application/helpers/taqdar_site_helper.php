<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * مساعدات الواجهة العامة الجديدة.
 *
 * المولدات هنا تخرج **الوسم نفسه** الذي كان يخرجه البناء المحلي
 * (`build/build.py`) — لأن `site.js` يفلتر ويبحث ويفرز اعتمادا على
 * معرفات وأصناف وسمات `data-*` بعينها، ومطابقتها **حرفية تامة**.
 * حرف زائد في اسم صنف يعطل الفلترة بلا خطأ في الكونسول.
 *
 * وكل قيمة قادمة من القاعدة تمر بـ`html_escape` — كما كان البناء
 * يمررها بـ`html.escape`.
 */

if (!function_exists('tq_site_asset')) {
    /**
     * رابط أصل من أصول التصميم، مبصوما بزمن تعديله.
     *
     * الأصول في `assets/taqdar/site/` — منفصلة عن `assets/taqdar/` التي
     * تخدم البوابات. والفصل ليس ترتيبا: ملف `taqdar.js` في التصميم يحمل
     * اسم `taqdar.js` في البوابات ووظيفة أخرى، فدمجهما يسقط درج القائمة
     * في أربع وثلاثين شاشة بلا أثر ظاهر.
     */
    function tq_site_asset($path)
    {
        $rel = 'assets/taqdar/site/' . ltrim($path, '/');
        $abs = FCPATH . $rel;
        $v   = is_file($abs) ? filemtime($abs) : 1;
        return base_url($rel) . '?v=' . $v;
    }
}

if (!function_exists('tqs_nav')) {
    /** روابط التنقل التسعة، وعلى الصفحة الحالية `aria-current`. */
    function tqs_nav($active = '')
    {
        /* «المواد والبرامج» قبل «الباقات»: الزائر يتصفح قبل أن يشتري،
           والترتيب في القائمة هو ترتيب الرحلة لا ترتيب الأهمية عندنا.
           وبند الكتب حذف لا لأن الكتب حذفت بل لأنها صارت نوعا في
           الكتالوج — وبند يقود إلى مرشح واحد من أربعة يقسم ما جمع. */
        $items = array(
            array('home',     '',          'الرئيسية'),
            array('catalog',  'catalog',   'المواد والبرامج التعليمية'),
            array('plans',    'plans',     'الباقات'),
            array('teachers', 'teachers',  'المعلمون'),
            array('students', 'students',  'الطلاب'),
            array('parents',  'parents',   'أولياء الأمور'),
            array('competitions', 'competitions', 'المسابقات'),
            array('blog',     'blog',      'المدونة'),
            array('about',    'about',     'عن المنصة'),
            array('contact',  'contact',   'تواصل معنا'),
        );

        $out = array();
        foreach ($items as $it) {
            $cur = ($it[0] === $active) ? ' aria-current="page"' : '';
            $out[] = '      <a href="' . base_url($it[1]) . '"' . $cur . '>' . $it[2] . '</a>';
        }
        return implode("\n", $out);
    }
}

if (!function_exists('tqs_badge')) {
    /**
     * شارة البطاقة: معرف أيقونة يبدأ بـ`i-` يصير رمزا، وما عداه حرف مجرد.
     * (الحرف مثل «ض» أو «EN» يستعمل حين لا أيقونة تكفي.)
     */
    function tqs_badge($v)
    {
        $v = (string) $v;
        if ($v !== '' && strpos($v, 'i-') === 0) {
            return '<svg><use href="#' . html_escape($v) . '"></use></svg>';
        }
        return html_escape($v);
    }
}

if (!function_exists('tqs_categories')) {
    /**
     * منتقي الفئات — أزرار.
     *
     * `id="catPicker"` هو الحارس الأعلى في `site.js`: بدونه لا فلترة إطلاقا.
     * وزر «جميع الفئات» أولا وبـ`data-cat` **فارغة** لأن الفلترة تقرأ
     * الفراغ بمعنى «لا تصفية». والعنوان في `<b>` لأن الجافاسكربت يقرؤه منه.
     */
    function tqs_categories($cats)
    {
        $h  = '<div class="stage-picker" id="catPicker">' . "\n";
        $h .= '        <button class="stage-card reveal" type="button" aria-pressed="true" data-cat="">' . "\n";
        $h .= '          <span class="ico"><svg aria-hidden="true"><use href="#i-grid"></use></svg></span>' . "\n";
        $h .= '          <b>جميع الفئات</b><span>اعرض كل المواد</span>' . "\n";
        $h .= '        </button>' . "\n";

        foreach ($cats as $c) {
            $h .= '        <button class="stage-card reveal" type="button" aria-pressed="false" data-cat="'
                . html_escape($c['id']) . '">' . "\n";
            $h .= '          <span class="ico"><svg aria-hidden="true"><use href="#'
                . html_escape($c['icon']) . '"></use></svg></span>' . "\n";
            $h .= '          <b>' . html_escape($c['label']) . '</b><span>'
                . html_escape($c['sub']) . '</span>' . "\n";
            $h .= '        </button>' . "\n";
        }

        return $h . '      </div>';
    }
}

if (!function_exists('tqs_category_links')) {
    /**
     * الفئات على الرئيسية — روابط لا أزرار، وبلا `id` فلا تفعل الفلترة هنا.
     * ووجهتها `/courses?cat=` وإلا صارت بطاقات الرئيسية كذبا.
     */
    function tqs_category_links($cats)
    {
        $h = '<div class="stage-picker">' . "\n";
        foreach ($cats as $c) {
            $h .= '        <a class="stage-card reveal" href="'
                . base_url('courses') . '?cat=' . html_escape($c['id']) . '">' . "\n";
            $h .= '          <span class="ico"><svg aria-hidden="true"><use href="#'
                . html_escape($c['icon']) . '"></use></svg></span>' . "\n";
            $h .= '          <b>' . html_escape($c['label']) . '</b><span>'
                . html_escape($c['sub']) . '</span>' . "\n";
            $h .= '        </a>' . "\n";
        }
        return $h . '      </div>';
    }
}

if (!function_exists('tqs_materials')) {
    /**
     * بطاقات المواد.
     *
     * `data-cat` على `<article>` **نفسه** لا على ابن له: الفلترة تلتقط كل
     * حفيد يحمل السمة داخل الشبكة، فوضعها على عنصر داخلي يخفي نصف البطاقة
     * ويبقي نصفها. و`alt=""` فارغ عمدا — الصورة زخرفية والعنوان في `<h3>`.
     */
    function tqs_materials($items)
    {
        $h = '<div class="grid-4" id="materialGrid">' . "\n";

        foreach ($items as $m) {
            $href = (isset($m['href']) && $m['href'] !== '') ? $m['href'] : '#';
            $h .= '        <article class="subject-card reveal" data-cat="' . html_escape($m['cat'])
                . '" data-title="' . html_escape($m['title']) . '">' . "\n";
            $h .= '          <div class="subject-card__media">' . "\n";
            $h .= '            <img src="' . tqs_asset_img($m['image'], 'subj-math')
                . '" width="620" height="620" loading="lazy" decoding="async" alt="">' . "\n";
            $h .= '          </div>' . "\n";
            /* خارج `__media`: تلك تقص ما يبرز عنها، والشارة تبرز عمدا */
            $h .= '          <span class="subject-card__badge" aria-hidden="true">'
                . tqs_badge($m['badge']) . '</span>' . "\n";
            $h .= '          <div class="subject-card__body">' . "\n";
            $h .= '            <h3>' . html_escape($m['title']) . '</h3>' . "\n";
            $h .= '            <p>' . html_escape($m['blurb']) . '</p>' . "\n";
            $h .= '            <div class="subject-card__meta">' . "\n";
            $h .= '              <span><svg aria-hidden="true"><use href="#i-play"></use></svg>'
                . html_escape($m['lessons']) . ' درسا</span>' . "\n";
            $h .= '              <span><svg aria-hidden="true"><use href="#i-clipboard"></use></svg>'
                . html_escape($m['quizzes']) . ' اختبارا</span>' . "\n";
            $h .= '            </div>' . "\n";
            $h .= '            <a class="btn btn--primary" href="' . html_escape($href) . '">ابدأ البرنامج</a>' . "\n";
            $h .= '          </div>' . "\n";
            $h .= '        </article>' . "\n";
        }

        return $h . '      </div>' . "\n"
             . '      <p class="dir-empty" id="materialEmpty" hidden>لا توجد مواد في هذه الفئة بعد.</p>';
    }
}

if (!function_exists('tqs_books')) {
    /**
     * بطاقات الكتب.
     *
     * `data-tone` تلون الغلاف، والقيم المدعومة في الورقة **حصرا**:
     * math · arabic · science · islamic · english. أي قيمة أخرى تنتج غلافا
     * بلا لون — لذلك تقصر هنا على المعروف بدل الوثوق بما في القاعدة.
     */
    function tqs_books($items)
    {
        $tones = array('math', 'arabic', 'science', 'islamic', 'english');
        $h = '<div class="grid-4" id="bookGrid">' . "\n";

        foreach ($items as $b) {
            $tone = (isset($b['tone']) && in_array($b['tone'], $tones, true)) ? $b['tone'] : 'math';

            $h .= '        <article class="book-card reveal" data-cat="' . html_escape($b['cat'])
                . '" data-title="' . html_escape($b['title']) . '">' . "\n";
            $h .= '          <div class="book-card__cover" data-tone="' . $tone . '">';

            if (!empty($b['cover'])) {
                $h .= '<img src="' . tqs_asset_img($b['cover'], 'subj-math')
                    . '" width="420" height="560" loading="lazy" decoding="async" alt="">';
            } else {
                $h .= '<span class="book-card__spine" aria-hidden="true"></span>'
                    . '<span class="book-card__label">' . html_escape($b['subject']) . '</span>';
            }

            $h .= '</div>' . "\n";
            $h .= '          <div class="book-card__body">' . "\n";
            $h .= '            <h3>' . html_escape($b['title']) . '</h3>' . "\n";
            $h .= '            <p><svg aria-hidden="true"><use href="#i-book"></use></svg>'
                . html_escape($b['subject']) . ' · <span class="tq-ltr">'
                . html_escape($b['pages']) . '</span> صفحة</p>' . "\n";

            if (!empty($b['file'])) {
                $h .= '            <a class="btn btn--primary" href="' . tq_site_asset('files/' . $b['file'])
                    . '" download><svg aria-hidden="true"><use href="#i-download"></use></svg>تحميل الكتاب</a>' . "\n";
            } else {
                // زر معطل أصدق من رابط يقود إلى لا شيء
                $h .= '            <button class="btn btn--primary" type="button" disabled>'
                    . '<svg aria-hidden="true"><use href="#i-clock"></use></svg>قريبا</button>' . "\n";
            }

            $h .= '          </div>' . "\n";
            $h .= '        </article>' . "\n";
        }

        return $h . '      </div>' . "\n"
             . '      <p class="dir-empty" id="bookEmpty" hidden>لا توجد كتب في هذه الفئة بعد.</p>';
    }
}

if (!function_exists('tqs_social')) {
    /**
     * روابط التواصل الاجتماعي من الإعدادات.
     *
     * **ما لم يضبط لا يعرض.** أيقونة تقود إلى `#` وعد مكسور للزائر،
     * وحساب وهمي أسوأ منه — فالغياب أصدق من كليهما.
     */
    function tqs_social()
    {
        /* الترتيب ترتيب الحضور: ما للمنصة عليه حساب نشط أولا.
           وما لا رابط له يحذف في الحلقة أدناه، فبقاؤه هنا لا يضر. */
        $nets = array(
            'instagram' => array('i-instagram', 'إنستغرام'),
            'tiktok'    => array('i-tiktok',    'تيك توك'),
            'facebook'  => array('i-facebook',  'فيسبوك'),
            'x'         => array('i-x',         'إكس'),
            'youtube'   => array('i-youtube',   'يوتيوب'),
            'snapchat'  => array('i-snapchat',  'سناب شات'),
        );

        $out = array();
        foreach ($nets as $key => $meta) {
            $url = trim((string) get_settings('social_' . $key));
            if ($url === '') continue;
            $out[] = '<a href="' . html_escape($url) . '" target="_blank" rel="noopener noreferrer"'
                   . ' aria-label="' . $meta[1] . '"><svg aria-hidden="true"><use href="#'
                   . $meta[0] . '"></use></svg></a>';
        }

        return $out ? '<div class="socials">' . implode('', $out) . '</div>' : '';
    }
}

if (!function_exists('tqs_img')) {
    /** صورة محتوى: اسم مجرد ⇒ أصل تصميم، وبرنامج فيه شرطة ⇒ رفع حقيقي. */
    function tqs_img($name, $fallback = 'blog-1')
    {
        $name = trim((string) $name);
        if ($name === '') $name = $fallback;
        if (strpos($name, '/') !== false) return base_url(ltrim($name, '/'));
        return tqs_asset_img($name, $fallback);
    }
}

if (!function_exists('tqs_person_img')) {
    /**
     * صورة شخص — والحقل `users.image` يحمل **ثلاثة أشكال** لا شكلا واحدا.
     *
     * وهذا سبب مربعات الصور المكسورة في الموقع: كل موضع نداء كان يخمن
     * شكلا ويبني عليه رابطا.
     *
     *   `teacher-3`                        اسم أصل مبذور ⟵ assets/…/img/teacher-3.webp
     *   `68dd67f385fb55e7f7457370c3a45927` رفع فعلي      ⟵ uploads/user_image/<هو>.jpg
     *   `uploads/…/x.png` أو رابط كامل     مسار صريح     ⟵ كما هو
     *   NULL أو فارغ                       لا صورة       ⟵ البديل
     *
     * فصفحة الباقة كانت تكتب `base_url($image)` فتنتج `…/teacher-3` — عنوانا
     * يرد 404، ويرى الزائر خمسة مربعات مكسورة تحت «من يدرس؟». وبطاقات
     * المعلمين كانت تمر بـ`tqs_img` فتنتج `img/68dd….webp` لمن رفع صورته
     * فعلا — مكسور بالعكس تماما.
     *
     * وأشهر صور هذا الخلل: عشرة مواضع في البوابة تكتب
     * `base_url('uploads/user_image/' . $image)` **بلا `.jpg`** — فكل صورة
     * رمزية في القائمة والترويسة والرسائل وقوائم الطلاب مكسورة، على
     * حساب فيه صورة مرفوعة سليمة على القرص.
     *
     * والاختيار هنا مرة واحدة، ومرتب من الأرخص إلى الأغلى: الأشكال
     * الصريحة تحسم بالنص، والرفع وحده يلمس القرص (`is_file`).
     *
     * @param string $image    قيمة `users.image` كما هي
     * @param string $fallback اسم أصل في سمة الموقع؛ وبلا اسم يستعمل
     *                         بديل Academy القائم (`placeholder.png`)
     */
    function tqs_person_img($image, $fallback = '')
    {
        $miss = ($fallback !== '')
              ? tqs_asset_img('', $fallback)
              : base_url('uploads/user_image/placeholder.png');

        $name = trim((string) $image);
        if ($name === '') return $miss;

        // رابط كامل أو مسار صريح: يخدم كما هو
        if (preg_match('~^(https?:)?//~i', $name)) return $name;
        if (strpos($name, '/') !== false)          return base_url(ltrim($name, '/'));

        /* رفع فعلي — Academy تكتب `<hash>.jpg` وتصنع نسخة مصغرة بجوارها.
           والمصغرة أولا: هي 220px بدل الأصل الذي قد يكون ميغابايتين. */
        foreach (array('uploads/user_image/optimized/', 'uploads/user_image/') as $dir) {
            if (is_file(FCPATH . $dir . $name . '.jpg')) return base_url($dir . $name . '.jpg');
        }

        // اسم أصل مبذور في سمة الموقع
        if (is_file(FCPATH . 'assets/taqdar/site/img/' . $name . '.webp')) {
            return tq_site_asset('img/' . $name . '.webp');
        }

        /* بقي اسم لا يقابله ملف. البديل صورة عامة لا مربع مكسور:
           الصورة المفقودة تقرأ عطبا في الموقع، والبديل يقرأ «لا صورة». */
        return $miss;
    }
}

if (!function_exists('tqs_teachers')) {
    /**
     * بطاقات المعلمين.
     *
     * `data-name`/`data-stage`/`data-rating`/`data-reviews`/`data-courses`
     * هي ما يبحث ويفرز به `site.js` — والفرز يعيد ترتيب عناصر DOM فعليا،
     * فغياب سمة يجعل بطاقة تهبط إلى آخر القائمة أبدا بلا سبب ظاهر.
     *
     * والتقييم يعرض **فقط إن وجد**: منصة جديدة بلا مراجعات تظهر «0.0»
     * فتبدو رديئة لا جديدة.
     */
    function tqs_teachers($items, $fold = 0)
    {
        $h = '';
        $i = 0;
        foreach ($items as $t) {
            /* ما بعد الطية يطبع مخفيا لا محذوفا: الزر يكشفه بلا طلب ثان،
               والبحث يفتشه كما يفتش غيره — فبطاقة وراء الطية تجدها كلمة
               البحث ولو لم تكشف بعد. */
            $folded = ($fold > 0 && $i++ >= $fold);
            $h .= '        <article class="teacher-card reveal'
                . ($folded ? ' is-folded" data-fold="1" hidden' : '"')
                . ' data-name="' . html_escape($t['name']) . '"'
                /* المواد والنبذة في سمة البحث: النص الإرشادي يعد بالبحث
                   بالمادة، وكانت السمة تحمل الاسم وحده — فوعد لا ينفذ. */
                . ' data-search="' . html_escape(trim($t['name'] . ' '
                    . implode(' ', (array) $t['chips']) . ' ' . $t['bio'])) . '"'
                . ' data-stage="' . html_escape($t['stage']) . '"'
                . ' data-rating="' . html_escape($t['rating']) . '"'
                . ' data-reviews="' . html_escape($t['reviews']) . '"'
                . ' data-courses="' . html_escape($t['courses']) . '">' . "\n";
            /* الصورة رابط كالاسم: البطاقة كلها تبدو قابلة للنقر، وكان
               الاسم وحده ينقر — فمن ضغط الصورة ظنها معطلة. و`tabindex=-1`
               كي لا يمر الوصول بالمفتاح على رابطين إلى وجهة واحدة. */
            $h .= '          <a class="teacher-card__media" href="' . html_escape($t['url']) . '"'
                . ' tabindex="-1" aria-hidden="true">'
                . '<img src="' . tqs_person_img($t['img'], 'teacher-1') . '" width="360" height="360"'
                . ' loading="lazy" decoding="async" alt=""></a>' . "\n";
            $h .= '          <div class="teacher-card__body">' . "\n";
            /* الاسم رابط: النموذج يبني `url` ولم يستعمله أحد،
               فبطاقات المعلمين تبدو قابلة للنقر ولا تنقر. */
            $h .= '            <h3><a href="' . html_escape($t['url']) . '">'
                . html_escape($t['name']) . '</a></h3>' . "\n";

            if ($t['chips']) {
                $h .= '            <div class="teacher-card__chips">';
                foreach ($t['chips'] as $c) {
                    $h .= '<span class="teacher-card__chip">' . html_escape($c) . '</span>';
                }
                $h .= '</div>' . "\n";
            }
            if ($t['bio'] !== '') {
                $h .= '            <p>' . html_escape($t['bio']) . '</p>' . "\n";
            }

            $h .= '            <div class="teacher-card__meta">';
            if ($t['rating'] > 0) {
                $h .= '<span class="teacher-card__rate"><svg aria-hidden="true"><use href="#i-star"></use></svg>'
                    . '<span class="tq-ltr">' . html_escape(number_format($t['rating'], 1)) . '</span>'
                    . ' (<span class="tq-ltr">' . html_escape(number_format($t['reviews'])) . '</span>)</span>';
            }
            if ($t['courses'] > 0) {
                $h .= '<span class="teacher-card__courses"><svg aria-hidden="true"><use href="#i-play"></use></svg>'
                    . html_escape($t['courses']) . ' دورة</span>';
            }
            $h .= '</div>' . "\n";
            $h .= '          </div>' . "\n";
            $h .= '        </article>' . "\n";
        }
        return $h;
    }
}

if (!function_exists('tqs_post_tabs')) {
    /** تبويبات تصنيفات المدونة — أولها «الكل» بـ`data-cat` فارغة. */
    function tqs_post_tabs($cats)
    {
        $h = '            <a href="#posts" class="is-active" data-cat="">الكل</a>' . "\n";
        foreach ($cats as $c) {
            $h .= '            <a href="#posts" data-cat="' . html_escape($c['title']) . '">'
                . html_escape($c['title']) . '</a>' . "\n";
        }
        return $h;
    }
}

if (!function_exists('tqs_side_cats')) {
    /** قائمة التصنيفات الجانبية بعدد مقالات كل. */
    function tqs_side_cats($cats)
    {
        $h = '';
        foreach ($cats as $c) {
            $h .= '            <a href="#posts" data-cat="' . html_escape($c['title']) . '">'
                . html_escape($c['title']) . ' <span class="tq-ltr">' . (int) $c['n'] . '</span></a>' . "\n";
        }
        return $h;
    }
}

if (!function_exists('tqs_feat_post')) {
    /** المقال المميز — أول ما وسم كذلك، وإلا أحدثها. */
    function tqs_feat_post($posts)
    {
        if (!$posts) return '';
        $p = $posts[0];
        foreach ($posts as $x) { if (!empty($x['feat'])) { $p = $x; break; } }

        return '<article class="feat-post reveal" data-cat="' . html_escape($p['cat'])
             . '" data-title="' . html_escape($p['title']) . '">' . "\n"
             . '          <div class="feat-post__media"><img src="' . tqs_img($p['img'])
             . '" width="900" height="600" loading="lazy" decoding="async" alt=""></div>' . "\n"
             . '          <div class="feat-post__body">' . "\n"
             . '            <span class="post-tag">' . html_escape($p['cat']) . '</span>' . "\n"
             . '            <h2>' . html_escape($p['title']) . '</h2>' . "\n"
             . '            <p>' . html_escape($p['excerpt']) . '</p>' . "\n"
             . '            <a class="btn btn--primary" href="' . html_escape($p['url']) . '">اقرأ المقال</a>' . "\n"
             . '          </div>' . "\n"
             . '        </article>';
    }
}

if (!function_exists('tqs_post_cards')) {
    /** بطاقات المقالات — تتخطى المميز فلا يظهر مرتين. */
    function tqs_post_cards($posts, $skip_feat = true, $limit = 3)
    {
        $h = ''; $n = 0; $seen = false;
        foreach ($posts as $p) {
            if ($skip_feat && !$seen && !empty($p['feat'])) { $seen = true; continue; }
            if ($n >= $limit) break;
            $n++;
            $h .= '          <article class="post-card reveal" data-cat="' . html_escape($p['cat'])
                . '" data-title="' . html_escape($p['title']) . '">' . "\n"
                . '            <div class="post-card__media"><img src="' . tqs_img($p['img'])
                . '" width="620" height="420" loading="lazy" decoding="async" alt=""></div>' . "\n"
                . '            <div class="post-card__body">' . "\n"
                . '              <span class="post-tag">' . html_escape($p['cat']) . '</span>' . "\n"
                . '              <h3>' . html_escape($p['title']) . '</h3>' . "\n"
                . '              <p>' . html_escape($p['excerpt']) . '</p>' . "\n"
                . '              <a class="post-card__more" href="' . html_escape($p['url']) . '">اقرأ المزيد</a>' . "\n"
                . '            </div>' . "\n"
                . '          </article>' . "\n";
        }
        return $h;
    }
}

if (!function_exists('tqs_post_rows')) {
    /** صفوف المقالات الباقية. */
    function tqs_post_rows($posts, $offset = 4)
    {
        $h = ''; $i = 0;
        foreach ($posts as $p) {
            $i++;
            if ($i <= $offset) continue;
            $h .= '          <a class="post-row reveal" href="' . html_escape($p['url'])
                . '" data-cat="' . html_escape($p['cat'])
                . '" data-title="' . html_escape($p['title']) . '">' . "\n"
                . '            <img src="' . tqs_img($p['img']) . '" width="220" height="150"'
                . ' loading="lazy" decoding="async" alt="">' . "\n"
                . '            <span class="post-row__body"><span class="post-tag">' . html_escape($p['cat'])
                . '</span><b>' . html_escape($p['title']) . '</b></span>' . "\n"
                . '          </a>' . "\n";
        }
        return $h;
    }
}

if (!function_exists('tqs_side_reads')) {
    /** روابط «الأكثر قراءة» الجانبية. */
    function tqs_side_reads($posts, $limit = 4)
    {
        $h = ''; $n = 0;
        foreach ($posts as $p) {
            if ($n >= $limit) break;
            $n++;
            $h .= '            <a class="side-read" href="' . html_escape($p['url'])
                . '" data-cat="' . html_escape($p['cat'])
                . '" data-title="' . html_escape($p['title']) . '">'
                . html_escape($p['title']) . '</a>' . "\n";
        }
        return $h;
    }
}

if (!function_exists('tqs_tel_href')) {
    /** رابط الهاتف من الإعدادات — وما لم يضبط لا ينقر. */
    function tqs_tel_href()
    {
        $p = trim((string) get_settings('phone'));
        return $p === '' ? '#' : 'tel:' . preg_replace('/[^0-9+]/', '', $p);
    }
}

if (!function_exists('tqs_phone_text')) {
    function tqs_phone_text()
    {
        $p = trim((string) get_settings('phone'));
        return $p === '' ? 'قريبا' : html_escape($p);
    }
}

if (!function_exists('tqs_whatsapp_href')) {
    function tqs_whatsapp_href()
    {
        $w = preg_replace('/[^0-9]/', '', (string) get_settings('social_whatsapp'));
        return $w === '' ? '#' : 'https://wa.me/' . $w;
    }
}

if (!function_exists('tqs_whatsapp_text')) {
    function tqs_whatsapp_text()
    {
        $w = trim((string) get_settings('social_whatsapp'));
        return $w === '' ? 'قريبا' : html_escape($w);
    }
}

if (!function_exists('tqs_asset_img')) {
    /**
     * صورة أصل بحارس اسم فارغ.
     *
     * الاسم الفارغ ينتج `img/.webp` — رابطا مكسورا بحالة 404 لا يظهر
     * في سجل خطأ ولا في الكونسول، ويرى صورة مفقودة فحسب. فالبديل
     * يختار هنا مرة، لا في كل موضع نداء.
     */
    function tqs_asset_img($name, $fallback)
    {
        $name = trim((string) $name);
        return tq_site_asset('img/' . ($name !== '' ? $name : $fallback) . '.webp');
    }
}

if (!function_exists('tqs_nav_key')) {
    /**
     * اسم العرض ⟵ مفتاح التنقل.
     *
     * كانت الخريطة مكتوبة داخل الترويسة، ويقرؤها الشريط السفلي بمتغير
     * `$tq_active` — وهو اسم **معاد استعماله** (لغة الجلسة في الغلاف،
     * ثم مفتاح التنقل في الترويسة). فأي صفحة تسنده تكسر الوسم النشط
     * بصمت. والدالة تجعل للمصدر موضعا واحدا.
     */
    function tqs_nav_key($page_name)
    {
        $map = array(
            'home' => 'home', 'home_elegant' => 'home',
            'site_teachers' => 'teachers', 'site_students' => 'students',
            'site_parents' => 'parents', 'blogs' => 'blog', 'blog_details' => 'blog',
            'about_us' => 'about', 'contact_us' => 'contact',
            /* الكتالوج ومفرداته: البرنامج والكتاب صفحتا تفصيل يصلهما
               الزائر من الكتالوج وإليه يعود، فيبقى بنده مضاء وهو فيهما.
               وصفحة الدورة معهما لأنها آخر ما يفتح من البرنامج. */
            'site_catalog' => 'catalog', 'site_path' => 'catalog', 'site_book' => 'catalog',
            'courses_page' => 'catalog', 'course_page' => 'catalog', 'categories' => 'catalog',
            /* الباقة وشاشة تأكيدها تحت «الباقات» — وهو الفرق الذي بني
               عليه فصل الصفحتين: التصفح باب، والشراء باب آخر. */
            'plans' => 'plans', 'site_plan' => 'plans', 'site_checkout' => 'plans',
            'competitions' => 'competitions', 'site_competition' => 'competitions',
            'instructor_page' => 'teachers', 'site_search' => '',
        );
        $k = (string) $page_name;
        return isset($map[$k]) ? $map[$k] : '';
    }
}

if (!function_exists('tqs_stat')) {
    /**
     * بند رقم واحد — أو لا شيء.
     *
     * **ما لم يضبط لا يعرض.** صفر معروض ادعاء معكوس: «صفر طالب»
     * أسوأ من غياب البند، و«٠٪ رضا» أسوأ من الصمت. والغياب هنا قرار
     * لا نقص — على نمط شارتي المتجرين في `site_stores.php`.
     *
     * @param string $key   مفتاح `settings` بلا البادئة
     * @param string $icon  معرف رمز من المكتبة
     * @param string $label الوصف تحت الرقم
     * @param string $cls   صنف البند (stats-band__item أو stat-strip__item)
     */
    function tqs_stat($key, $icon, $label, $cls = 'stats-band__item')
    {
        $v = trim((string) get_settings('taqdar_stat_' . $key));
        if ($v === '' || $v === '0') return '';

        $num = '<svg aria-hidden="true"><use href="#' . html_escape($icon) . '"></use></svg>';
        if ($cls === 'stat-strip__item') {
            return '<div class="stat-strip__item">' . $num
                 . '<div><b>' . html_escape($v) . '</b><span>'
                 . html_escape($label) . '</span></div></div>';
        }
        return '<div class="stats-band__item">' . $num
             . '<b>' . html_escape($v) . '</b><span>'
             . html_escape($label) . '</span></div>';
    }
}

if (!function_exists('tqs_path_cards')) {
    /**
     * بطاقات برامج للصفحة الرئيسية — نفس وسم الكتالوج بلا مشبكه.
     * `$grid` يحدد عدد الأعمدة، والمعرفات محذوفة عمدا: `materialGrid`
     * يخص فلترة الكتالوج، وتكراره يجعل الفلتر يمس أول شبكة وحدها.
     */
    function tqs_path_cards($items, $grid = 'grid-3 cards-3')
    {
        if (empty($items)) return '';
        $h = '<div class="' . html_escape($grid) . '">' . "\n";
        foreach ($items as $m) {
            $href = (isset($m['href']) && $m['href'] !== '') ? $m['href'] : '#';
            $h .= '  <article class="subject-card reveal">' . "\n";
            $h .= '    <div class="subject-card__media"><img src="' . tqs_asset_img($m['image'], 'subj-math')
                . '" width="620" height="620" loading="lazy" decoding="async" alt=""></div>' . "\n";
            $h .= '    <span class="subject-card__badge" aria-hidden="true">' . tqs_badge($m['badge']) . '</span>' . "\n";
            $h .= '    <div class="subject-card__body">' . "\n";
            $h .= '      <h3>' . html_escape($m['title']) . '</h3>' . "\n";
            $h .= '      <p>' . html_escape($m['blurb']) . '</p>' . "\n";
            $h .= '      <div class="subject-card__meta">' . "\n";
            $h .= '        <span><svg aria-hidden="true"><use href="#i-play"></use></svg>'
                . html_escape($m['lessons']) . ' درسا</span>' . "\n";
            $h .= '        <span><svg aria-hidden="true"><use href="#i-clipboard"></use></svg>'
                . html_escape($m['quizzes']) . ' اختبارا</span>' . "\n";
            $h .= '      </div>' . "\n";
            $h .= '      <a class="btn btn--primary" href="' . html_escape($href) . '">ابدأ البرنامج</a>' . "\n";
            $h .= '    </div>' . "\n";
            $h .= '  </article>' . "\n";
        }
        return $h . '</div>' . "\n";
    }
}

if (!function_exists('tqs_universities')) {
    /**
     * شعارات الجامعات من المجلد لا من قائمة مكتوبة.
     * إضافة شعار = رفع ملف؛ وحذفه = حذف ملف. وقائمة في الشيفرة
     * تفترق عن المجلد عند أول تعديل، فتظهر صورة مكسورة أو يغيب شعار.
     */
    function tqs_universities()
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $dir = FCPATH . 'assets/taqdar/site/img/uni/';
        if (!is_dir($dir)) return $cache = array();
        $files = glob($dir . '*.webp');
        if (!$files) return $cache = array();
        sort($files);
        $out = array();
        foreach ($files as $f) $out[] = tq_site_asset('img/uni/' . basename($f));
        return $cache = $out;
    }
}

if (!function_exists('tqs_bundles')) {
    /** الباقات الفعالة مرتبة — تقرأ مرة وتحفظ. */
    function tqs_bundles()
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $CI = get_instance();
        $CI->load->database();
        $rows = $CI->db->select('code, name_ar, note, image, price, duration_days, features, featured, stage')
                       ->from('plans')->where('active', 1)->where('scope', 'grade')
                       ->order_by('`order`', 'ASC', false)->get()->result_array();
        $out = array();
        foreach ($rows as $r) {
            $f = json_decode((string) $r['features'], true);
            $out[] = array(
                'code'     => (string) $r['code'],
                'name'     => (string) $r['name_ar'],
                'note'     => (string) $r['note'],
                'price'    => (int) $r['price'],
                'days'     => (int) $r['duration_days'],
                'features' => is_array($f) ? $f : array(),
                'featured' => (int) $r['featured'] === 1,
                'stage'    => (string) $r['stage'],
                'image'    => (string) $r['image'],
            );
        }
        return $cache = $out;
    }
}

if (!function_exists('tqs_bundle_cards')) {
    /**
     * بطاقات الباقات — درجات صاعدة والوسطى مبرزة.
     * السعر يقسم على مئة **مرة واحدة**: القيمة هللات في القاعدة،
     * وقسمتها في موضعين تنتج رقما يختلف بين صفحتين.
     */
    function tqs_bundle_cards($items = null)
    {
        $items = ($items === null) ? tqs_bundles() : $items;
        if (empty($items)) return '';
        /* أول مرحلة ظاهرة والباقي `hidden`: يعمل بلا سكربت — وإلا رأى
           الزائر ست بطاقات لمرحلتين لو فشل تحميله. */
        $tq_stages = tqs_bundle_stages();
        $tq_hide   = (count($tq_stages) > 1);
        $tq_ks     = array_keys($tq_stages);
        $tq_first  = $tq_ks ? $tq_ks[0] : '';
        $h = '<div class="bundles" data-tq-bundles>' . "\n";
        foreach ($items as $b) {
            $cls = 'bundle' . ($b['featured'] ? ' bundle--featured' : '');
            $h .= '  <article class="' . $cls . ' reveal" data-stage="' . html_escape($b['stage']) . '"'
                . (($tq_hide && $b['stage'] !== $tq_first) ? ' hidden' : '') . '>' . "\n";
            if ($b['featured']) {
                $h .= '    <span class="bundle__flag">الأكثر طلبا</span>' . "\n";
            }
            if ((string) $b['image'] !== '') {
                /* الصورة تقول ما في الباقة، ولا تحمل معلومة لا يقولها النص —
                   فـ`alt` فارغة عمدا: وصفها يجعل قارئ الشاشة يعيد العنوان. */
                /* التفاصيل **فوق** الصورة لا تحتها: البطاقة طويلة، ومن يمسح
                   الصفحة بعينه يرى الصورة أولا — فما يميز الباقة يقرأ معها. */
                $h .= '    <div class="bundle__media">'
                    . '<img src="' . tqs_asset_img($b['image'], 'path-primary')
                    . '" alt="" width="1100" height="733" loading="lazy" decoding="async">'
                    . '<span class="bundle__over">'
                    . '<b>' . html_escape(tqs_bundle_tier($b['name'])) . '</b>'
                    . '<i>' . html_escape(tqs_stage_label($b['stage'])) . '</i>'
                    . '</span></div>' . "\n";
            }
            $h .= '    <h3 class="bundle__name">' . html_escape($b['name']) . '</h3>' . "\n";
            if ($b['note'] !== '') {
                $h .= '    <p class="bundle__note">' . html_escape($b['note']) . '</p>' . "\n";
            }
            $h .= '    <p class="bundle__price"><b class="tq-ltr">' . number_format($b['price'] / 100)
                . '</b> <span>ر.س</span>'
                . '<small>' . ($b['days'] >= 360 ? 'للعام الدراسي كاملا' : 'لكل ' . (int) $b['days'] . ' يوما') . '</small></p>' . "\n";
            if ($b['features']) {
                $h .= '    <ul class="bundle__list">' . "\n";
                foreach ($b['features'] as $f) {
                    $h .= '      <li><svg aria-hidden="true"><use href="#i-check"></use></svg>'
                        . html_escape($f) . '</li>' . "\n";
                }
                $h .= '    </ul>' . "\n";
            }
            $h .= '    <a class="btn ' . ($b['featured'] ? 'btn--primary' : 'btn--ghost')
                . '" href="' . base_url('plan/' . $b['code']) . '">اشترك الآن</a>' . "\n";
            $h .= '  </article>' . "\n";
        }
        return $h . '</div>' . "\n";
    }
}

if (!function_exists('tqs_carousel')) {
    /**
     * غلاف كاروسل حول وسم جاهز.
     * أساسه `scroll-snap` لا جافاسكربت يحرك: المتصفح يعرف السحب والزخم
     * وحد التمرير أحسن مما نكتب، ويعمل بلا سكربت إن فشل تحميله.
     */
    function tqs_carousel($inner, $label = '', $class = '')
    {
        if (trim((string) $inner) === '') return '';
        $h  = '<div class="carousel2' . ($class !== '' ? ' ' . html_escape($class) : '')
            . '" data-tq-carousel>' . "\n";
        $h .= '  <button class="carousel2__nav carousel2__nav--prev" type="button" data-tq-car-prev'
            . ' aria-label="السابق"><svg aria-hidden="true"><use href="#i-arrow"></use></svg></button>' . "\n";
        $h .= '  <div class="carousel2__track" data-tq-car-track tabindex="0" role="region"'
            . ($label !== '' ? ' aria-label="' . html_escape($label) . '"' : '') . '>' . "\n";
        $h .= $inner . "\n";
        $h .= '  </div>' . "\n";
        $h .= '  <button class="carousel2__nav carousel2__nav--next" type="button" data-tq-car-next'
            . ' aria-label="التالي"><svg aria-hidden="true"><use href="#i-arrow"></use></svg></button>' . "\n";
        return $h . '</div>' . "\n";
    }
}

if (!function_exists('tqs_program_slides')) {
    /**
     * شرائح برامج المواد — **محتوى لا سلعة**.
     * بلا سعر وبلا «اشتر»: البيع صار بالباقة، وسعر على بطاقة مادة
     * يعيد الزائر إلى النموذج الذي تركناه.
     */
    function tqs_program_slides($items, $limit = 12)
    {
        $h = ''; $n = 0;
        foreach ($items as $m) {
            if ($n >= $limit) break;
            $n++;
            $h .= tqs_program_card($m);
        }
        return $h;
    }
}

if (!function_exists('tqs_bundle_stages')) {
    /** المراحل التي لها باقات — بترتيب ظهورها، ولا تكتب أسماؤها هنا مرتين. */
    function tqs_bundle_stages()
    {
        $seen = array();
        foreach (tqs_bundles() as $b) {
            $k = (string) $b['stage'];
            if ($k === '' || isset($seen[$k])) continue;
            $seen[$k] = tqs_stage_label($k);   /* الأسماء في موضع واحد */
        }
        return $seen;
    }
}

if (!function_exists('tqs_stage_tabs')) {
    /**
     * تبويب المرحلة.
     * المرحلة سؤال يجاب مرة، والدرجة سؤال يوازن فيه — فعرض الست
     * معا يقارن ما لا يعني ولي الأمر.
     */
    function tqs_stage_tabs($active = '')
    {
        $stages = tqs_bundle_stages();
        if (count($stages) < 2) return '';
        if ($active === '') { $keys = array_keys($stages); $active = $keys[0]; }
        $h = '<div class="stage-tabs" role="tablist" aria-label="اختر المرحلة">' . "\n";
        foreach ($stages as $k => $label) {
            $on = ($k === $active);
            $h .= '  <button class="stage-tab' . ($on ? ' is-on' : '') . '" type="button" role="tab"'
                . ' aria-selected="' . ($on ? 'true' : 'false') . '"'
                . ' data-tq-stage="' . html_escape($k) . '">' . html_escape($label) . '</button>' . "\n";
        }
        return $h . '</div>' . "\n";
    }
}

if (!function_exists('tqs_universities_grid')) {
    /**
     * شبكة الجامعات — خمس في الصف.
     * الشريط المنزلق يخفي أكثرها ويطلب انتظارا؛ والشبكة ترى دفعة.
     */
    function tqs_universities_grid()
    {
        $u = tqs_universities();
        if (empty($u)) return '';
        $h = '<div class="unigrid">' . "\n";
        foreach ($u as $src) {
            $h .= '  <span class="unigrid__item"><img src="' . html_escape($src)
                . '" alt="" loading="lazy" decoding="async" width="300" height="140"></span>' . "\n";
        }
        return $h . '</div>' . "\n";
    }
}

if (!function_exists('tqs_excerpt')) {
    /**
     * مقتطف من متن المقال.
     *
     * كان المتن يعرض كاملا مجردا من الوسوم: مر حين كانت المقالات
     * سطرا واحدا، وانكشف حين صارت ١٣٠٠ حرف — فطالت البطاقة عمودا.
     * والقطع **عند حد كلمة** لا عند حرف: قطع الكلمة نصفين يقرأ خطأ
     * مطبعيا لا اختصارا.
     */
    function tqs_excerpt($html, $len = 165)
    {
        $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html)));
        if ($t === '') return '';
        if (mb_strlen($t, 'UTF-8') <= $len) return $t;
        $cut = mb_substr($t, 0, $len, 'UTF-8');
        $sp  = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($sp !== false && $sp > $len * 0.6) $cut = mb_substr($cut, 0, $sp, 'UTF-8');
        return rtrim($cut, " \t\n\r\0\x0B،.:;-") . '…';
    }
}

if (!function_exists('tqs_universities_slides')) {
    /**
     * شعارات الجامعات شرائح للكاروسل.
     * الشبكة تعرض ثمانية وخمسين دفعة فتطول الصفحة بلا معنى؛ والكاروسل
     * يعرض صفا يمرر — والمكون قائم (`tqs_carousel`) فلا يبنى ثانيا.
     */
    function tqs_universities_slides()
    {
        $u = tqs_universities();
        if (empty($u)) return '';
        $h = '';
        foreach ($u as $src) {
            $h .= '    <span class="unislide"><img src="' . html_escape($src)
                . '" alt="" loading="lazy" decoding="async" width="300" height="140"></span>' . "\n";
        }
        return $h;
    }
}

if (!function_exists('tqs_program_card')) {
    /**
     * بطاقة برنامج واحد — صورة وشرح وعدادان ورابط.
     *
     * كانت مكتوبة داخل `tqs_program_slides` وحدها؛ ثم احتاجتها الشبكة.
     * ونسخها يجعل لها متنين يتباعدان عند أول تعديل في أحدهما — فهي
     * هنا مرة واحدة، والشرائح والشبكة تختلفان في **الغلاف** لا في
     * البطاقة.
     */
    function tqs_program_card($m)
    {
        $href = (isset($m['href']) && $m['href'] !== '') ? $m['href'] : '#';
        $h  = '    <article class="pcard">' . "\n";
        $h .= '      <div class="pcard__media"><img src="' . tqs_asset_img($m['image'], 'subj-math')
            . '" width="620" height="620" loading="lazy" decoding="async" alt=""></div>' . "\n";
        $h .= '      <div class="pcard__body">' . "\n";
        $h .= '        <h3>' . html_escape($m['title']) . '</h3>' . "\n";
        $h .= '        <p>' . html_escape($m['blurb']) . '</p>' . "\n";
        /* الصفر يخبر عن حالة الاستيراد لا عن المادة — فيغيب حتى يملأ */
        $meta = '';
        if ((int) $m['lessons'] > 0) {
            $meta .= '<span><svg aria-hidden="true"><use href="#i-play"></use></svg>'
                  . html_escape($m['lessons']) . ' درسا</span>';
        }
        if ((int) $m['quizzes'] > 0) {
            $meta .= '<span><svg aria-hidden="true"><use href="#i-clipboard"></use></svg>'
                  . html_escape($m['quizzes']) . ' اختبارا</span>';
        }
        if ($meta !== '') {
            $h .= '        <div class="pcard__meta">' . $meta . '</div>' . "\n";
        }
        $h .= '        <a class="pcard__link" href="' . html_escape($href) . '">ما في هذا البرنامج</a>' . "\n";
        $h .= '      </div>' . "\n";
        $h .= '    </article>' . "\n";
        return $h;
    }
}

if (!function_exists('tqs_subject_grid')) {
    /**
     * المواد شبكة — كلها ظاهرة دفعة.
     *
     * الصفحة عنوانها «المواد والبرامج التعليمية»، وكانت تعرضها شريطا
     * يمرر: خمس من خمس عشرة، والباقي وراء زر. والشبكة تنجز ما يعد
     * به العنوان — ولا حد للعدد هنا عمدا: الحد صواب في شريط ضيق،
     * وحذف صامت في شبكة تدعي العرض كله.
     */
    function tqs_subject_grid($items)
    {
        if (empty($items)) return '';
        $h = '<div class="subjgrid">' . "\n";
        foreach ($items as $m) $h .= tqs_program_card($m);
        return $h . '</div>' . "\n";
    }
}

if (!function_exists('tqs_competitions_strip')) {
    /**
     * المسابقات المفتوحة — ويخفي القسم كله إن لم تكن هناك واحدة.
     *
     * `open_competitions()` ترجع المفتوحة فقط، والمسابقتان الحاليتان
     * تغلقان في ٣١ أغسطس. فقسم يطبع بلا شرط يصير عنوانا فوق فراغ
     * في تاريخ معلوم — والإخفاء هنا لا في العرض، كي لا ينسى.
     */
    function tqs_competitions_strip($limit = 3)
    {
        $CI = &get_instance();
        $CI->load->model('taqdar_site_model', 'tq_m');
        $rows = (array) $CI->tq_m->open_competitions($limit);
        if (empty($rows)) return '';

        $h  = '<section class="section section--tint">' . "\n";
        $h .= '  <div class="shell">' . "\n";
        $h .= '    <div class="section-head">' . "\n";
        $h .= '      <h2><span>مسابقات مفتوحة الآن</span></h2>' . "\n";
        $h .= '      <p>يشترك فيها طلاب الباقات بلا رسوم إضافية — تدريب على المنافسة، وجوائز لأوائل كل مرحلة.</p>' . "\n";
        $h .= '    </div>' . "\n";
        $h .= '    <div class="compgrid">' . "\n";
        foreach ($rows as $r) {
            $h .= '      <article class="compcard">' . "\n";
            if ((string) $r['stage'] !== '') {
                $h .= '        <span class="post-tag">' . html_escape($r['stage']) . '</span>' . "\n";
            }
            $h .= '        <h3>' . html_escape($r['title']) . '</h3>' . "\n";
            if ((string) $r['blurb'] !== '') {
                $h .= '        <p>' . html_escape($r['blurb']) . '</p>' . "\n";
            }
            if ((string) $r['when'] !== '') {
                /* التاريخ بأرقام لاتينية داخل `tq-ltr`: الأرقام العربية
                   الشرقية تنقلب ترتيبا في بعض الخطوط، والتاريخ يقرأ خطأ. */
                $h .= '        <p class="compcard__when"><svg aria-hidden="true"><use href="#i-calendar"></use></svg>'
                    . 'تبدأ <b>' . html_escape(tqs_date_ar($r['when'])) . '</b></p>' . "\n";
            }
            $h .= '        <a class="pcard__link" href="' . html_escape($r['href']) . '">تفاصيل المسابقة</a>' . "\n";
            $h .= '      </article>' . "\n";
        }
        $h .= '    </div>' . "\n";
        $h .= '  </div>' . "\n";
        $h .= '</section>' . "\n";
        return $h;
    }
}

if (!function_exists('tqs_stage_label')) {
    /**
     * اسم المرحلة من مفتاحها — موضع واحد للأسماء، تقرؤه البطاقة والتبويب.
     *
     * والمفتاح **مسمى قسم** (`category.slug`): `Taqdar_catalog_model` يبحث
     * به في `cat_by_slug()` ليضع الباقة تحت مرشح المرحلة في الكتالوج. فمن
     * أضاف قسما جديدا في اللوحة — «اختبارات القدرات» مثلا — وأسند إليه
     * باقة، كان يرى `qudurat` نصا لاتينيا وسط عربية الصفحة. فالثلاثة
     * المعروفة تجاب بلا استعلام، وما عداها يسأل عنه الجدول مرة واحدة.
     */
    function tqs_stage_label($key)
    {
        $labels = array(
            'primary'   => 'المرحلة الابتدائية',
            'middle'    => 'المرحلة المتوسطة',
            'secondary' => 'المرحلة الثانوية',
        );
        $k = (string) $key;
        if ($k === '') return '';
        if (isset($labels[$k])) return $labels[$k];

        static $cats = null;
        if ($cats === null) {
            $cats = array();
            $CI = get_instance();
            $CI->load->database();
            try {
                foreach ($CI->db->select('slug, name')->where('parent', 0)
                                ->get('category')->result_array() as $r) {
                    $cats[(string) $r['slug']] = (string) $r['name'];
                }
            } catch (Exception $e) { /* اسم ناقص أهون من صفحة بيضاء */ }
        }
        return isset($cats[$k]) ? $cats[$k] : $k;
    }
}

if (!function_exists('tqs_bundle_tier')) {
    /**
     * درجة الباقة من اسمها الكامل.
     *
     * الاسم في القاعدة «الباقة المميزة — المرحلة الابتدائية»: يجمع
     * الدرجة والمرحلة. وعرضه كاملا فوق الصورة يطيل السطر ويكرر ما
     * تقوله الشارة تحته. فيقتطع ما بعد الشرطة — والقاعدة تبقى مصدرا
     * واحدا، ولا يكتب الاسم هنا ثانية.
     */
    function tqs_bundle_tier($name)
    {
        $n = (string) $name;
        $pos = mb_strpos($n, '—', 0, 'UTF-8');
        if ($pos !== false) $n = mb_substr($n, 0, $pos, 'UTF-8');
        return trim($n);
    }
}

if (!function_exists('tqs_date_ar')) {
    /**
     * تاريخ يقرأ لا يفك.
     *
     * كان `2026-08-10` خاما على صفحة المسابقات: صيغة للآلة لا للقارئ،
     * وتقرأ في العربية من اليسار على غير عادة العين. والأشهر تكتب هنا
     * ولا تترك لـ`IntlDateFormatter`: الامتداد غير مضمون على كل خادم،
     * وغيابه كان سيرجع سلسلة فارغة بلا خطأ ظاهر.
     */
    function tqs_date_ar($date)
    {
        $ts = is_numeric($date) ? (int) $date : strtotime((string) $date);
        if (!$ts) return (string) $date;
        $m = array('', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                   'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر');
        return (int) date('j', $ts) . ' ' . $m[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }
}

/* ═════════════════════════════════════════════════════════════════════
   الباقة — سعرها ومدتها ومنهجها وبيانات تحويلها
   ═══════════════════════════════════════════════════════════════════ */

if (!function_exists('tqs_money')) {
    /**
     * السعر من الهللات إلى ريال مقروء.
     *
     * القسمة على مئة **هنا وحدها**: كانت مكتوبة في بطاقة الباقة وفي
     * صفحة الاشتراك وفي الفاتورة، فأي إصلاح في واحدة يترك أختيها.
     */
    function tqs_money($halalas, $unit = true)
    {
        $v = number_format(((int) $halalas) / 100, 0, '.', ',');
        return '<b class="tq-ltr">' . $v . '</b>' . ($unit ? ' <span>ر.س</span>' : '');
    }
}

if (!function_exists('tqs_period_label')) {
    /** مدة الاشتراك بعبارة يفهمها ولي الأمر لا بعدد أيام. */
    function tqs_period_label($days)
    {
        $d = (int) $days;
        if ($d >= 360) return 'للعام الدراسي كاملا';
        if ($d >= 180) return 'لفصل دراسي';
        if ($d >= 28 && $d <= 31) return 'لشهر واحد';
        if ($d === 14) return 'أربعة عشر يوما';
        return 'لمدة ' . $d . ' يوما';
    }
}

if (!function_exists('tqs_bank')) {
    /**
     * بيانات التحويل البنكي — أو `null` إن لم تملأ بعد.
     *
     * `offline_bank_information` في Academy حقل نصي حر قيمته هنا
     * «Enter your bank information» الافتراضية، فلا يصلح مصدرا: عرضها
     * على مشتر سعودي أسوأ من ألا يعرض شيء. والمفاتيح الثلاثة أدناه
     * صريحة تملأ من لوحة تقدر، وتقرأ هنا وحدها.
     */
    function tqs_bank()
    {
        $b = array(
            'beneficiary' => trim((string) get_settings('tq_bank_beneficiary')),
            'iban'        => trim((string) get_settings('tq_bank_iban')),
            'bank'        => trim((string) get_settings('tq_bank_name')),
            'note'        => trim((string) get_settings('tq_bank_note')),
        );
        return ($b['iban'] !== '' && $b['beneficiary'] !== '') ? $b : null;
    }
}

if (!function_exists('tqs_bank_block')) {
    /**
     * صندوق «حول إلى هذا الحساب» — بمرجع الحوالة وزر نسخ.
     *
     * ومرجع الحوالة رقم الفاتورة: بدونه تصل حوالة بلا اسم يطابق،
     * فيفتح الاشتراك يدويا بالتخمين أو لا يفتح.
     */
    function tqs_bank_block($ref = '', $amount = null)
    {
        $b = tqs_bank();

        $h  = '<div class="bankbox">' . "\n";
        $h .= '  <h3 class="bankbox__h"><svg aria-hidden="true"><use href="#i-bank"></use></svg>'
            . ' بيانات التحويل البنكي</h3>' . "\n";

        if (!$b) {
            /* لا يعرض نص افتراضي ولا حساب مخترع: يقال الحق ويعطى باب
               يطرق. الصمت هنا يوقف الشراء، والكذب يوقف الثقة. */
            $h .= '  <p class="bankbox__empty">لم تنشر بيانات الحساب بعد. '
                . '<a href="' . base_url('contact') . '">تواصل معنا</a> '
                . 'ونرسل لك تفاصيل التحويل فورا.</p>' . "\n";
            return $h . '</div>' . "\n";
        }

        $rows = array(
            array('المستفيد', $b['beneficiary'], false),
            array('الآيبان',  $b['iban'],        true),
        );
        if ($b['bank'] !== '') $rows[] = array('البنك', $b['bank'], false);
        if ((string) $ref !== '') $rows[] = array('مرجع الحوالة', (string) $ref, true);
        if ($amount !== null)     $rows[] = array('المبلغ', trim(strip_tags(tqs_money($amount))), true);

        $h .= '  <dl class="bankbox__rows">' . "\n";
        foreach ($rows as $r) {
            $h .= '    <div class="bankbox__row">' . "\n";
            $h .= '      <dt>' . html_escape($r[0]) . '</dt>' . "\n";
            $h .= '      <dd' . ($r[2] ? ' dir="ltr" class="tq-ltr"' : '') . '>'
                . '<span>' . html_escape($r[1]) . '</span>'
                . '<button type="button" class="bankbox__copy" data-tq-copy="' . html_escape($r[1]) . '"'
                . ' aria-label="انسخ ' . html_escape($r[0]) . '">'
                . '<svg aria-hidden="true"><use href="#i-copy"></use></svg></button>'
                . '</dd>' . "\n";
            $h .= '    </div>' . "\n";
        }
        $h .= '  </dl>' . "\n";

        if ($b['note'] !== '') {
            $h .= '  <p class="bankbox__note">' . html_escape($b['note']) . '</p>' . "\n";
        }
        $h .= '  <p class="bankbox__note">اكتب مرجع الحوالة في خانة الملاحظات، '
            . 'ويفعل اشتراكك بعد التحقق منها.</p>' . "\n";
        return $h . '</div>' . "\n";
    }
}

if (!function_exists('tqs_dur')) {
    /**
     * مدة الدرس بالعربية — لا "00:12:00".
     *
     * Academy تخزنها نصا بصيغة الساعة، وعرضها كما هي يجعل درسا من
     * اثنتي عشرة دقيقة يبدو ساعتين لمن يمسح القائمة بعينه.
     */
    function tqs_dur($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') return '';

        $p = explode(':', $raw);
        if (count($p) === 3)      $sec = ((int) $p[0]) * 3600 + ((int) $p[1]) * 60 + (int) $p[2];
        elseif (count($p) === 2)  $sec = ((int) $p[0]) * 60 + (int) $p[1];
        elseif (ctype_digit($raw)) $sec = ((int) $raw) * 60;   // رقم مجرد = دقائق
        else return $raw;                                      // صيغة لا نعرفها تعرض كما هي

        if ($sec <= 0) return '';
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        if ($h > 0) return $m > 0 ? $h . ' س ' . $m . ' د' : $h . ' س';
        if ($m > 0) return $m . ' د';
        return $sec . ' ث';
    }
}

if (!function_exists('tqs_curriculum')) {
    /**
     * منهج الباقة — مادة تطوى على وحداتها، والوحدة على دروسها.
     *
     * `<details>` لا جافاسكربت: الطي سلوك يعرفه المتصفح، ويعمل بلا
     * سكربت ومع قارئ الشاشة بلا `aria` نكتبها ثم ننساها.
     *
     * وضعان:
     *   `public`  — للزائر: المجاني بزر معاينة، وما سواه بقفل صريح
     *   `student` — للمشترك: كل درس رابط، والمقفول يقول سببه
     *
     * @param array $bundle  ناتج `Taqdar_site_model::bundle_by_code()`
     * @param array $o       mode · open · progress (course_id → نسبة)
     */
    function tqs_curriculum($bundle, $o = array())
    {
        if (empty($bundle['subjects'])) return '';

        $mode = isset($o['mode']) ? $o['mode'] : 'public';
        $open = isset($o['open']) ? (int) $o['open'] : 1;   // كم مادة مفتوحة ابتداء
        $prog = isset($o['progress']) && is_array($o['progress']) ? $o['progress'] : array();

        $h = '<div class="curric">' . "\n";
        $i = 0;

        foreach ($bundle['subjects'] as $s) {
            $i++;
            $meta = array();
            if (count($s['units']) > 0) $meta[] = count($s['units']) . ' وحدة';
            if ($s['lessons'] > 0)      $meta[] = $s['lessons'] . ' درسا';
            if ($s['quizzes'] > 0)      $meta[] = $s['quizzes'] . ' اختبارا';

            /* برنامج بلا محتوى لا يخفى: الباقة تشمله والمشتري يجب أن
               يعرف أنه قيد الإعداد — إخفاؤه وعد ضمني بأنه جاهز. */
            $ready = !empty($s['ready']);

            $h .= '  <details class="curric__subj' . ($ready ? '' : ' is-soon') . '"'
                . ($i <= $open && $ready ? ' open' : '') . '>' . "\n";
            $h .= '    <summary class="curric__sum">' . "\n";
            $h .= '      <span class="curric__name">' . html_escape($s['title']) . '</span>' . "\n";
            if ($meta) {
                $h .= '      <span class="curric__meta">' . html_escape(implode(' · ', $meta)) . '</span>' . "\n";
            } else {
                $h .= '      <span class="curric__meta curric__meta--soon">قيد الإعداد</span>' . "\n";
            }
            if ($mode === 'student' && isset($prog[$s['course_id']])) {
                $p = (int) $prog[$s['course_id']];
                $h .= '      <span class="curric__prog">'
                    . '<span class="curric__track"><i style="inline-size:' . $p . '%"></i></span>'
                    . '<b class="tq-ltr">' . $p . '%</b></span>' . "\n";
            }
            $h .= '      <svg class="curric__chev" aria-hidden="true"><use href="#i-chevron"></use></svg>' . "\n";
            $h .= '    </summary>' . "\n";

            $h .= '    <div class="curric__body">' . "\n";
            if (!$s['units']) {
                $h .= '      <p class="curric__soon">دروس هذه المادة قيد التجهيز، '
                    . 'وتفتح لك تلقائيا بمجرد نشرها.</p>' . "\n";
            }
            foreach ($s['units'] as $u) {
                $h .= '      <section class="curric__unit">' . "\n";
                $h .= '        <h4 class="curric__unit-h">' . html_escape($u['title'])
                    . ' <small>' . count($u['lessons']) . ' درسا</small></h4>' . "\n";
                $h .= '        <ol class="curric__lessons">' . "\n";
                foreach ($u['lessons'] as $l) {
                    $icon = $l['is_quiz'] ? 'i-clipboard' : 'i-play';
                    $cls  = 'curric__lesson' . ($l['is_free'] ? ' is-free' : '');
                    $h .= '          <li class="' . $cls . '">' . "\n";
                    $h .= '            <svg class="curric__ico" aria-hidden="true"><use href="#' . $icon . '"></use></svg>' . "\n";

                    $title = html_escape($l['title']);
                    if ($mode === 'student') {
                        $h .= '            <a class="curric__t" href="'
                            . base_url('student/lesson/' . (int) $s['course_id'] . '/' . (int) $l['id'])
                            . '">' . $title . '</a>' . "\n";
                    } else {
                        $h .= '            <span class="curric__t">' . $title . '</span>' . "\n";
                    }

                    $dur = tqs_dur($l['duration']);
                    if ($dur !== '') {
                        $h .= '            <span class="curric__dur">' . html_escape($dur) . '</span>' . "\n";
                    }

                    if ($mode === 'public') {
                        if ($l['is_free']) {
                            $h .= '            <a class="curric__free" href="'
                                . tqs_preview_url($s['course_id'], $l['id'])
                                . '"><svg aria-hidden="true"><use href="#i-unlock"></use></svg> معاينة مجانية</a>' . "\n";
                        } else {
                            /* الشارة مرئية عمدا: المشتري يمسح المنهج
                               بعينه ليعرف كم فيه مما يدفع مقابله. وقفل
                               صامت يقرأ عطبا لا سياسة. */
                            $h .= '            <span class="curric__soonlock">'
                                . '<svg class="curric__lock" aria-hidden="true"><use href="#i-lock"></use></svg>'
                                . 'يفتح بالاشتراك</span>' . "\n";
                        }
                    }
                    $h .= '          </li>' . "\n";
                }
                $h .= '        </ol>' . "\n";
                $h .= '      </section>' . "\n";
            }
            $h .= '    </div>' . "\n";
            $h .= '  </details>' . "\n";
        }
        return $h . '</div>' . "\n";
    }
}

if (!function_exists('tqs_free_preview')) {
    /**
     * أول درس مجاني في الباقة — أو null.
     *
     * صورة الباقة كانت صورة صامتة: لوحة جميلة لا تفعل شيئا، بينما في
     * الباقة نفسها ستة دروس مفتوحة للمعاينة **مدفونة داخل المنهج** خلف
     * طي مادة ثم طي وحدة. فمن جاء ليعرف «ما هذا؟» يرى صورة، ومن يريد
     * أن يرى درسا عليه أن يبحث عنه.
     *
     * وهذه ترفع أول درس مجاني إلى السطح، فتصير الصورة مشغلا: عليها
     * علامة تشغيل وشارة «معاينة مجانية» — وعد يرى ويضغط.
     *
     * والترتيب ترتيب المنهج نفسه (مادة فوحدة فدرس)، فأول مجاني هنا هو
     * أول مجاني هناك — لا اختيارا عشوائيا يخالف ما تحته.
     *
     * @return array|null  course_id · lesson_id · title · duration
     */
    function tqs_free_preview($bundle)
    {
        if (empty($bundle['subjects'])) return null;

        foreach ($bundle['subjects'] as $s) {
            foreach ((array) $s['units'] as $u) {
                foreach ((array) $u['lessons'] as $l) {
                    /* الاختبار المجاني ليس معاينة: لا فيديو فيه يشاهد،
                       وشارة تشغيل فوقه تعد بما لا يفتح. */
                    if (empty($l['is_free']) || !empty($l['is_quiz'])) continue;
                    return array(
                        'course_id' => (int) $s['course_id'],
                        'lesson_id' => (int) $l['id'],
                        'title'     => (string) $l['title'],
                        'subject'   => (string) $s['title'],
                        'duration'  => tqs_dur($l['duration']),
                    );
                }
            }
        }
        return null;
    }
}

if (!function_exists('tqs_stat_strip')) {
    /**
     * شريط إحصاء — **وما كان صفرا يسقط منه**.
     *
     * الرقم المكتوب نصا في العرض («١٢٠٠ درس») يكذب يوم يتغير ما يصفه.
     * وهذه تقرأ ما مرر إليها من القاعدة، فإن كان صفرا لم تعد به.
     *
     * @param array $items  [ [العدد, المفرد, الأيقونة], … ]
     */
    function tqs_stat_strip($items, $class = 'bstats')
    {
        $out = array();
        foreach ($items as $it) {
            if ((int) $it[0] <= 0) continue;
            $out[] = '  <div class="' . $class . '__i">'
                   . '<svg aria-hidden="true"><use href="#' . $it[2] . '"></use></svg>'
                   . '<b class="tq-ltr">' . (int) $it[0] . '</b>'
                   . '<span>' . html_escape($it[1]) . '</span></div>';
        }
        if (!$out) return '';
        return '<div class="' . $class . '">' . "\n" . implode("\n", $out) . "\n</div>\n";
    }
}

if (!function_exists('tq_text')) {
    /**
     * نص من محرر اللوحة، وإلا نص القالب.
     *
     * ═══ العقد ═══
     *
     *   tq_text('home', 'hero_lede', 'النص المكتوب في القالب')
     *
     * المفتاح نفسه يسجل في `Taqdar_content_model::registry()` ليظهر في
     * شاشة «نصوص الصفحات». ومفتاح في أحدهما بلا الآخر إما حقل يحرر ولا
     * يظهر أو نص يظهر ولا يحرر — وكلاهما يكتشف بالمصادفة وحدها.
     *
     * والافتراضي يبقى مكتوبا هنا في القالب لا في القاعدة: قاعدة فارغة
     * (أو جدول لم ينشأ بعد، أو قاعدة تعثرت) تعني أن الصفحة تعرض ما
     * كانت تعرضه حرفا بحرف — لا صفحة بعنوان فارغ.
     *
     * ويرجع النص **مهربا** جاهزا للطباعة: النص يكتب في اللوحة نصا لا
     * HTML، وطباعة قيمة قادمة من نموذج بلا تهريب تفتح كل صفحة عامة
     * لحقن سكربت من شاشة تحرير عنوان. ومن أراد الخام فله `tq_text_raw`.
     */
    function tq_text($page, $key, $default = '')
    {
        return html_escape(tq_text_raw($page, $key, $default));
    }
}

if (!function_exists('tq_testimonials')) {
    /**
     * آراء أولياء الأمور المنشورة — من اللوحة.
     *
     * تلف نداء النموذج كما تلفه `tq_text_raw()`: القالب لا ينادي
     * `$this->load->model()` بنفسه، ولا تسقط صفحة عامة لأن جدولا
     * لم ينشأ بعد — الفارغ يعني «اعرض ما في القالب».
     */
    function tq_testimonials($limit = 24)
    {
        static $rows = null;
        if ($rows !== null) return $rows;

        $rows = array();
        try {
            $CI = &get_instance();
            $CI->load->model('taqdar_content_model', 'tq_content');
            $rows = $CI->tq_content->testimonials($limit);
        } catch (Throwable $e) {
            $rows = array();
        }
        return $rows;
    }
}

if (!function_exists('tq_text_raw')) {
    /** القيمة بلا تهريب — لمن يضعها في `alt` أو `title` أو وسم meta. */
    function tq_text_raw($page, $key, $default = '')
    {
        static $loaded = array();

        if (!isset($loaded[$page])) {
            $loaded[$page] = array();
            try {
                $CI = &get_instance();
                $CI->load->model('taqdar_content_model', 'tq_content');
                $loaded[$page] = $CI->tq_content->page_values($page);
            } catch (Throwable $e) {
                // الصفحة العامة لا تسقط لأجل نص: القالب يحمل نصه
                $loaded[$page] = array();
            }
        }

        return isset($loaded[$page][$key]) && trim((string) $loaded[$page][$key]) !== ''
            ? $loaded[$page][$key]
            : $default;
    }
}

if (!function_exists('tqs_preview_url')) {
    /**
     * وجهة «معاينة مجانية» للزائر.
     *
     * كانت كل شارة معاينة في الصفحات العامة تشير إلى `student/lesson/…`،
     * وهو مسار محروس: فمن ضغطها بلا حساب ارتد إلى صفحة الدخول. والوعد
     * المكتوب على الشارة أن الدرس مجاني، فوجهته `/preview/…` — انظر
     * `controllers/Preview.php`.
     *
     * ومن كان داخلا يبقى في مشغل الدرس الكامل: هناك تقدمه وأسئلته
     * وبوابته، وإرساله إلى صفحة معاينة عارية خسارة لا مكسب.
     */
    function tqs_preview_url($course_id, $lesson_id)
    {
        $course_id = (int) $course_id;
        $lesson_id = (int) $lesson_id;

        $ci = &get_instance();
        if ((int) $ci->session->userdata('user_id') > 0 && tq_role($ci->session->userdata('user_id')) === 'student') {
            return base_url('student/lesson/' . $course_id . '/' . $lesson_id);
        }
        return base_url('preview/' . $course_id . '/' . $lesson_id);
    }
}

if (!function_exists('tqs_video_embed')) {
    /**
     * وسم المشغل لمقطع درس — يوتيوب أو فيميو أو ملف على المنصة.
     *
     * نسخة الخادم من `mountPlayer()` في `taqdar-lesson.js`: القواعد نفسها
     * (نطاق `youtube-nocookie` بلا كوكيز، ومعرف يستخرج من أي صيغة رابط)،
     * لأن صفحة المعاينة تفتح بلا جافاسكربت المشغل ولا بوابته.
     */
    function tqs_video_embed($type, $url, $title = '')
    {
        $url  = trim((string) $url);
        $type = strtolower(trim((string) $type));
        if ($url === '') return '';

        $t = html_escape($title);

        if ($type === 'youtube' || preg_match('~youtu\.?be~i', $url)) {
            /* الرابط قد يأتي بـ`&amp;` مكتوبة في القاعدة (كما في `pp=`)،
               فيفك الترميز قبل قراءة المعرف وإلا التقط ما بعده معه. */
            $clean = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
            preg_match('~(?:v=|youtu\.be/|embed/)([\w-]{6,})~', $clean, $m);
            $id = isset($m[1]) ? $m[1] : '';
            if ($id === '') return '';
            return '<iframe src="https://www.youtube-nocookie.com/embed/' . html_escape($id)
                 . '?rel=0&amp;modestbranding=1" title="' . $t . '"'
                 . ' allow="accelerometer; encrypted-media; picture-in-picture"'
                 . ' allowfullscreen loading="lazy"></iframe>';
        }

        if ($type === 'vimeo' || preg_match('~vimeo~i', $url)) {
            preg_match('~vimeo\.com/(\d+)~', $url, $m);
            $id = isset($m[1]) ? $m[1] : '';
            if ($id === '') return '';
            return '<iframe src="https://player.vimeo.com/video/' . html_escape($id) . '"'
                 . ' title="' . $t . '" allowfullscreen loading="lazy"></iframe>';
        }

        return '<video controls preload="metadata" playsinline'
             . ' src="' . html_escape($url) . '"></video>';
    }
}

/* ══════════════════════════════════════════════════════════════════
   TQ-P26 · بطاقات الباقات في الرئيسية — نظام تصميم المالك
   تُستعمل في `home.php` وحدها. ولم يُمس `tqs_bundle_cards()` القائمة،
   فصفحة `/plans` تُرسم كما هي بلا تغيير.
   ══════════════════════════════════════════════════════════════════ */

if (!function_exists('tqs_p26_tier')) {
    /** درجة الباقة من رمزها: basic-primary ⇒ basic */
    function tqs_p26_tier($code)
    {
        $c = (string) $code;
        foreach (array('basic', 'plus', 'full') as $t) {
            if (strpos($c, $t) === 0) return $t;
        }
        return 'basic';
    }
}

if (!function_exists('tqs_p26_cards')) {
    /**
     * بطاقات الباقات.
     *
     * البيانات من `tqs_bundles()` نفسها — لا استعلام جديد ولا عمود جديد.
     * وصورة الطالب تُشتق من **درجة الباقة** في العرض لا من القاعدة:
     * الصورة قرار واجهة، وتخزينها في عمود يجعل تبديلها تعديل بيانات.
     */
    function tqs_p26_cards($items = null, $opts = array())
    {
        /* `cta` وجهة الزر: `plan` صفحة الباقة (الرئيسية) · `checkout` شاشة
           التأكيد (صفحة الباقات). و`more` رابط «ما في هذه الباقة؟» تحته.
           خياران لا نسختان من الدالّة: نسخةٌ ثانية تشيخ وحدها. */
        $cta  = isset($opts['cta'])  ? $opts['cta']  : 'plan';
        $more = !empty($opts['more']);
        $items = ($items === null) ? tqs_bundles() : $items;
        if (empty($items)) return '';

        /* أول مرحلة ظاهرة والباقي `hidden`: يعمل بلا سكربت — وإلا رأى
           الزائر ست بطاقات لمرحلتين معًا لو فشل تحميله. */
        $stages = tqs_bundle_stages();
        $hide   = (count($stages) > 1);
        $ks     = array_keys($stages);
        $first  = $ks ? $ks[0] : '';

        /* أيقونة لكل درجة — من سبرايت الموقع، بلا سبرايت ثانٍ */
        $ico = array('basic' => 'i-book', 'plus' => 'i-quality', 'full' => 'i-badge');

        $h = '<div class="p26__grid" data-tq-bundles>' . "\n";
        foreach ($items as $b) {
            $tier = tqs_p26_tier($b['code']);
            $hot  = !empty($b['featured']);
            $h .= '  <article class="p26-card' . ($hot ? ' p26-card--hot' : '') . '"'
                . ' id="' . html_escape($b['code']) . '"'
                . ' data-stage="' . html_escape($b['stage']) . '"'
                . (($hide && $b['stage'] !== $first) ? ' hidden' : '') . '>' . "\n";
            if ($hot) {
                $h .= '    <span class="p26-card__badge">الأكثر اختيارا</span>' . "\n";
            }
            /* الجسم صندوق تنسيق مستقل: الصورة تطفو داخله فتلتف حولها
               الكتابة، والزر خارجه فيلتصق بقاع البطاقة. */
            $h .= '    <div class="p26-card__body">' . "\n";
            /* الصورة عنصر مساعد لا بطل: `alt` فارغة و`aria-hidden` — وصفها
               يجعل قارئ الشاشة يقرأ «طالب» قبل اسم الباقة بلا فائدة. */
            $h .= '    <span class="p26-card__art" aria-hidden="true">'
                . '<img src="' . tq_site_asset('img/p26-' . $tier . '.webp') . '" alt=""'
                . ' width="480" height="620" loading="lazy" decoding="async"></span>' . "\n";
            $h .= '    <span class="p26-card__ico" aria-hidden="true">'
                . '<svg><use href="#' . $ico[$tier] . '"></use></svg></span>' . "\n";
            /* اسم الدرجة وحده: `plans.name` يحمل «الباقة المميزة — المرحلة
               الابتدائية»، والمرحلة تُعرض سطرًا تحته — فذكرها مرتين يكسر
               العنوان سطرين بلا فائدة. و`tqs_bundle_tier()` قائمة أصلًا. */
            $h .= '    <h3 class="p26-card__title">' . html_escape(tqs_bundle_tier($b['name'])) . '</h3>' . "\n";
            $h .= '    <p class="p26-card__sub">' . html_escape(tqs_stage_label($b['stage'])) . '</p>' . "\n";
            /* السعر بنسختين: سنويّة كما هي في القاعدة، ومعادِل شهريّ **عرضًا
               لا فوترة**. كل الباقات `period='annual'`، فالشهريّ يقول صراحةً
               «يُدفع سنويًّا» — رقم بلا سياقه وعدٌ مضلّل. */
            $tq_sar   = (int) round($b['price'] / 100);
            $tq_year  = ($b['days'] >= 360);
            $tq_month = $tq_year ? (int) round($tq_sar / 12) : 0;

            $h .= '    <p class="p26-card__price" data-cycle="year"><b class="tq-ltr">'
                . number_format($tq_sar) . '</b> ريال / '
                . ($tq_year ? 'سنويا' : 'كل ' . (int) $b['days'] . ' يوما') . '</p>' . "\n";
            if ($tq_year) {
                $h .= '    <p class="p26-card__price" data-cycle="month" hidden><b class="tq-ltr">'
                    . number_format($tq_month) . '</b> ريال / شهريا'
                    . '<small class="p26-card__note">تدفع سنويا '
                    . number_format($tq_sar) . ' ر.س</small></p>' . "\n";
            }
            if (!empty($b['features'])) {
                $h .= '    <ul class="p26-card__list">' . "\n";
                foreach ($b['features'] as $f) {
                    $h .= '      <li><svg aria-hidden="true"><use href="#i-check"></use></svg>'
                        . '<span>' . html_escape($f) . '</span></li>' . "\n";
                }
                $h .= '    </ul>' . "\n";
            }
            $h .= '    </div>' . "\n";
            $h .= '    <span class="p26-card__cta"><a href="'
                . base_url(($cta === 'checkout' ? 'checkout/' : 'plan/') . $b['code'])
                . '">اشترك الآن</a></span>' . "\n";
            if ($more) {
                $h .= '    <a class="p26-card__more" href="' . base_url('plan/' . $b['code'])
                    . '">ما في هذه الباقة؟'
                    . '<svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></a>' . "\n";
            }
            $h .= '  </article>' . "\n";
        }
        return $h . '</div>' . "\n";
    }
}
