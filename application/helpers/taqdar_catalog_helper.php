<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * وسم الكتالوج الموحد.
 *
 * ملف مستقل عن `taqdar_site_helper.php` لأن الكتالوج ليس مكونا في صفحة
 * بل صفحة كاملة بمرشحاتها وترقيمها وبحثها الحي — وخلطه بمولدات الموقع
 * العامة يجعل ملفا من ألفي سطر لا يفتح إلا بالبحث.
 *
 * والوسم كله هنا لا في العرض: الصفحة الكاملة والجزء الذي يعيده البحث
 * الحي يطبعان **من الدالة نفسها**. ولو كتب الوسم في القالب لاحتاج
 * الجزء نسخة ثانية منه، فتتباعد النسختان عند أول تعديل — فيرى من
 * يبحث بطاقة غير التي رآها عند فتح الصفحة.
 */

if (!function_exists('tqs_norm_ar')) {
    /**
     * تطبيع النص العربي للبحث.
     *
     * الزائر يكتب «عربيه» ويبحث عن «العربية»، ويكتب «احياء» ويبحث عن
     * «أحياء». والمقارنة الحرفية تخيب في الحالتين، فيقرأ «لا نتائج»
     * عن محتوى موجود — وهو أسوأ ما يقوله كتالوج.
     *
     * فيوحد: الهمزات إلى ألف · التاء المربوطة إلى هاء · الألف المقصورة
     * إلى ياء، ويسقط التشكيل والتطويل وعلامات الترقيم.
     */
    function tqs_norm_ar($s)
    {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        /* التشكيل والتطويل: النص في القاعدة مجرد منهما، لكن ما يكتبه
           الزائر في صندوق البحث ليس كذلك — ولوحة مفاتيح الجوال تدرجهما. */
        $s = preg_replace('/[\x{064B}-\x{0652}\x{0640}\x{0670}]/u', '', $s);
        $s = strtr($s, array(
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        ));
        /* الترقيم يصير فراغا لا يحذف: «الرياضيات — السادس» كلمتان لا واحدة */
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }
}

if (!function_exists('tqs_norm_words')) {
    /** كلمات البحث مطبعة — والحرف الواحد يسقط فلا يطابق كل شيء. */
    function tqs_norm_words($q)
    {
        $out = array();
        foreach (explode(' ', tqs_norm_ar($q)) as $w) {
            if (mb_strlen($w, 'UTF-8') >= 2) $out[] = $w;
        }
        return array_slice($out, 0, 8);
    }
}

if (!function_exists('tqs_cat_query')) {
    /**
     * رابط الكتالوج بمرشحاته — مع تعديل واحد.
     *
     * كل رابط في الصفحة (رقم صفحة · حذف رقاقة · تبديل فرز) يمر بها،
     * فلا يكتب في موضع رابط ينسى مرشحا: وهو الموضع الذي ينشأ فيه
     * «الصفحة الثانية تنسى البحث».
     *
     * `$set` قيمها: نص يستبدل، و`null` يحذف المفتاح.
     * و`page` يعاد إلى الأول تلقائيا عند تغيير أي مرشح — الصفحة السابعة
     * من نتيجة قديمة لا وجود لها في نتيجة جديدة، فتبقى الرقاقة مضغوطة
     * والشبكة فارغة بلا سبب ظاهر.
     */
    function tqs_cat_query($f, $set = array())
    {
        $q = array(
            'q'       => $f['q'],
            'type'    => implode(',', $f['type']),
            'cat'     => implode(',', $f['cat']),
            'grade'   => implode(',', $f['grade']),
            'subject' => implode(',', $f['subject']),
            'teacher' => implode(',', $f['teacher']),
            'price'   => $f['price'],
            'sort'    => ($f['sort'] === 'featured') ? '' : $f['sort'],
            'page'    => ((int) $f['page'] > 1) ? (int) $f['page'] : '',
        );

        if (!array_key_exists('page', $set)) {
            foreach (array_keys($set) as $k) { if ($k !== 'page') { $q['page'] = ''; break; } }
        }
        foreach ($set as $k => $v) $q[$k] = ($v === null) ? '' : $v;

        /* `page=1` تحذف دائما: هي الافتراض، ووجودها يجعل للصفحة الأولى
           عنوانين — واحد للزائر الأول وآخر لمن رجع إليها من الثانية.
           والفهرس يعدهما صفحتين بمحتوى واحد. */
        if ((string) $q['page'] === '1') $q['page'] = '';

        $out = array();
        foreach ($q as $k => $v) if ((string) $v !== '') $out[$k] = $v;
        return $out ? base_url('catalog') . '?' . http_build_query($out) : base_url('catalog');
    }
}

if (!function_exists('tqs_cat_toggle')) {
    /** رابط يقلب قيمة في مرشح متعدد — يضيفها إن غابت ويحذفها إن حضرت. */
    function tqs_cat_toggle($f, $key, $value)
    {
        $cur = array_map('strval', (array) $f[$key]);
        $v   = (string) $value;
        $new = in_array($v, $cur, true)
             ? array_values(array_diff($cur, array($v)))
             : array_merge($cur, array($v));
        return tqs_cat_query($f, array($key => implode(',', $new)));
    }
}

if (!function_exists('tqs_cat_media')) {
    /**
     * وجه البطاقة — ويختلف بالنوع.
     *
     * الكتاب غلاف رأسي ملون بمادته، والباقة والبرنامج صورة عريضة،
     * والمسابقة لوح مذهب برمزها: لا صورة لواحدة منها في القاعدة، وصورة
     * بديلة واحدة تعطى لكل المسابقات تكذب أكثر مما تزين.
     */
    function tqs_cat_media($it)
    {
        $tones = array('math', 'arabic', 'science', 'islamic', 'english');

        if ($it['kind'] === 'book') {
            $tone = in_array($it['tone'], $tones, true) ? $it['tone'] : 'math';
            $h = '<div class="ccard__media ccard__media--book book-card__cover" data-tone="' . $tone . '">';
            if ((string) $it['image'] !== '') {
                $h .= '<img src="' . html_escape(tqs_img($it['image'], 'subj-math'))
                    . '" width="420" height="560" loading="lazy" decoding="async" alt="">';
            } else {
                /* بلا غلاف: اسم المادة على لوح ملون بلونها — أصدق من
                   غلاف كتاب آخر يوضع بديلا فيظن الزائر أنه هو. */
                $h .= '<span class="book-card__spine" aria-hidden="true"></span>'
                    . '<span class="book-card__label">'
                    . html_escape($it['subject'] !== '' ? $it['subject'] : $it['title'])
                    . '</span>';
            }
            return $h . '</div>';
        }

        if ($it['kind'] === 'competition') {
            if ((string) $it['image'] !== '') {
                return '<div class="ccard__media"><img src="' . html_escape(tqs_img($it['image'], 'blog-1'))
                     . '" width="620" height="380" loading="lazy" decoding="async" alt=""></div>';
            }
            return '<div class="ccard__media ccard__media--comp" aria-hidden="true">'
                 . '<svg><use href="#i-badge"></use></svg></div>';
        }

        $fallback = ($it['fallback'] !== '') ? $it['fallback'] : 'subj-math';
        return '<div class="ccard__media"><img src="'
             . html_escape(tqs_img($it['image'], $fallback))
             . '" width="620" height="380" loading="lazy" decoding="async" alt=""></div>';
    }
}

if (!function_exists('tqs_rich_text')) {
    /**
     * نص وصف من القاعدة، فقرات — لا وسوما ولا سطرا واحدا.
     *
     * الحقول الوصفية (`competitions.description` · `books.description`)
     * تحرر بعضها بمحرر غني فتخزن `<p>…</p>`، وبعضها نص خام بأسطر. فطباعتها
     * بـ`html_escape` تعرض `<p>` حرفا في الصفحة — وقد عرضت. وطباعتها
     * خاما تفتح كل صفحة عامة لحقن سكربت من شاشة تحرير.
     *
     * فالوسوم تسقط، وحدود الفقرات تحفظ: كل فقرة `<p>` مهربة على حدة.
     * والنتيجة واحدة من المصدرين — نص خام بأسطر ونص غني بفقرات.
     */
    function tqs_rich_text($html, $fallback = '')
    {
        $t = (string) $html;
        /* حدود الفقرات تصير أسطرا قبل نزع الوسوم، وإلا التصق آخر فقرة
           بأول التي تليها كلمة واحدة بلا فاصل. */
        $t = preg_replace('#<(?:br\s*/?|/p|/div|/li|/h[1-6])\s*>#i', "\n", $t);
        $t = trim(preg_replace('/\x{00A0}/u', ' ', strip_tags($t)));

        $out = array();
        foreach (preg_split('/\R+/u', $t) as $line) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line));
            if ($line !== '') $out[] = '<p>' . html_escape($line) . '</p>';
        }
        if (!$out && $fallback !== '') $out[] = '<p>' . html_escape($fallback) . '</p>';
        return implode("\n", $out);
    }
}

if (!function_exists('tqs_book_file')) {
    /**
     * رابط ملف الكتاب.
     *
     * موضعان مسموحان: أصل تصميم (`assets/taqdar/site/files/…`) لما رفع
     * مع الثيم، ومسار تحت `uploads/` لما يرفعه المسؤول من اللوحة —
     * وهو ما تصفه شاشة «الكتب». وكان العرض يفترض الأول وحده، فملف
     * يرفعه المسؤول ينتج زر تحميل يقود إلى 404.
     *
     * والتمييز بالشرطة كما في `tqs_img`: اسم مجرد ⇒ أصل، ومسار فيه
     * شرطة ⇒ رفع حقيقي.
     */
    function tqs_book_file($file)
    {
        $file = trim((string) $file);
        if ($file === '') return '';
        if (strpos($file, '//') !== false) return $file;                 // رابط كامل
        if (strpos($file, '/')  !== false) return base_url(ltrim($file, '/'));
        return tq_site_asset('files/' . $file);
    }
}

if (!function_exists('tqs_cat_card')) {
    /**
     * بطاقة عنصر واحد.
     *
     * البنية واحدة والوجه يختلف: `data-kind` يحمل النوع، والورقة تلونه
     * وتغير نسبة صورته وذيله. ولو نسخت بطاقة لكل نوع لصارت أربع بطاقات
     * تتباعد عند أول تعديل في الحشو أو الظل — وقد وقع هذا قبلا بين
     * `tqs_materials` و`tqs_path_cards`، فكتبت `tqs_program_card` لتجمعهما.
     *
     * والرابط طبقة تغطي البطاقة (`__hit`) لا وسم `<a>` يلفها: اللف يجعل
     * كل ما في البطاقة نصا لرابط واحد يقرؤه قارئ الشاشة دفعة، والطبقة
     * تبقي العنوان عنوانا والوصف وصفا.
     */
    function tqs_cat_card($it)
    {
        /* نماذج CI3 لا تحمل بالاسم: من ينادي هذه الدالة من عرض لم يحمل
           فيه المتحكم النموذج يسقط بخطأ قاتل «class not found» — لا
           بشبكة ناقصة. والتحميل هنا مرة واحدة وبلا كلفة إن كان محملا. */
        if (!class_exists('Taqdar_catalog_model', false)) {
            get_instance()->load->model('taqdar_catalog_model');
        }
        $kinds = Taqdar_catalog_model::kinds();
        $kind  = isset($kinds[$it['kind']]) ? $kinds[$it['kind']] : array('one' => '', 'icon' => 'i-grid');

        $h  = '<article class="ccard reveal" data-kind="' . html_escape($it['kind']) . '">' . "\n";
        $h .= '  <a class="ccard__hit" href="' . html_escape($it['href']) . '">'
            . '<span class="sr-only">' . html_escape($it['title']) . '</span></a>' . "\n";
        $h .= '  ' . tqs_cat_media($it) . "\n";

        $h .= '  <span class="ccard__kind"><svg aria-hidden="true"><use href="#'
            . html_escape($kind['icon']) . '"></use></svg>' . html_escape($kind['one']) . '</span>' . "\n";

        if ($it['kind'] === 'plan' && !empty($it['featured'])) {
            $h .= '  <span class="ccard__flag">الاكثر طلبا</span>' . "\n";
        }

        $h .= '  <div class="ccard__body">' . "\n";
        if ((string) $it['tag'] !== '') {
            $h .= '    <span class="ccard__tag">' . html_escape($it['tag']) . '</span>' . "\n";
        }
        $h .= '    <h3 class="ccard__title">' . html_escape($it['title']) . '</h3>' . "\n";
        if ((string) $it['blurb'] !== '') {
            $h .= '    <p class="ccard__blurb">' . html_escape(tqs_excerpt($it['blurb'], 118)) . '</p>' . "\n";
        }

        if ($it['stats']) {
            $h .= '    <div class="ccard__meta">' . "\n";
            foreach ($it['stats'] as $s) {
                $h .= '      <span><svg aria-hidden="true"><use href="#' . html_escape($s[0])
                    . '"></use></svg>' . html_escape($s[1]) . '</span>' . "\n";
            }
            $h .= '    </div>' . "\n";
        }

        /* الذيل: السعر حيث يوجد، وسطر يقول لماذا لا سعر حيث لا يوجد.
           فبطاقة البرنامج بلا شيء في موضع السعر تقرأ «مجاني» بالصمت،
           وهي ليست مجانية بل تفتح بالباقة. */
        $h .= '    <div class="ccard__foot">' . "\n";
        if ($it['kind'] === 'plan') {
            $h .= '      <p class="ccard__price"><b class="tq-ltr">'
                . number_format(max(0, (int) $it['price']) / 100) . '</b><span>ر.س</span></p>' . "\n";
            $h .= '      <span class="ccard__cta">تفاصيل الباقة'
                . '<svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></span>' . "\n";
        } elseif ($it['kind'] === 'book') {
            /* «اقرا وحمل» فوق كتاب لم يرفع ملفه وعد مكسور: الزائر يضغط
               فيجد زرا معطلا. والدعوة تقول ما يجده فعلا. */
            $has = (isset($it['extra']['file']) && (string) $it['extra']['file'] !== '');
            $h .= '      <p class="ccard__price ccard__price--free">مجاني</p>' . "\n";
            $h .= '      <span class="ccard__cta">' . ($has ? 'اقرا وحمل' : 'تفاصيل الكتاب')
                . '<svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></span>' . "\n";
        } elseif ($it['kind'] === 'competition') {
            $h .= '      <p class="ccard__price ccard__price--free">'
                . (((string) $it['state'] === 'open') ? 'التسجيل مفتوح' : 'التسجيل مغلق') . '</p>' . "\n";
            $h .= '      <span class="ccard__cta">تفاصيل المسابقة'
                . '<svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></span>' . "\n";
        } elseif ($it['kind'] === 'course') {
            /* الكورس محتوى الباقة لا سلعة بجوارها: «ضمن الباقات» كما يقرأ
               البرنامج، إلا ما وسم مجانيا فيقرأ مجانا. وبطاقة تقول سعرا
               لشيء لا يباع مفردا توازن الزائر بين خيارين أحدهما وهم.
               والدعوة تقول ما يجده: كورس بلا درس واحد لا يعد بمنهج. */
            $h .= '      <p class="ccard__price ccard__price--'
                . (!empty($it['free']) ? 'free">مجاني' : 'in">ضمن الباقات') . '</p>' . "\n";
            $h .= '      <span class="ccard__cta">'
                . (!empty($it['ready']) ? 'استعرض دروسه' : 'تفاصيل الكورس')
                . '<svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></span>' . "\n";
        } else {
            $h .= '      <p class="ccard__price ccard__price--in">ضمن الباقات</p>' . "\n";
            $h .= '      <span class="ccard__cta">ما في هذا البرنامج'
                . '<svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></span>' . "\n";
        }
        $h .= '    </div>' . "\n";

        $h .= '  </div>' . "\n";
        return $h . '</article>' . "\n";
    }
}

if (!function_exists('tqs_cat_pager')) {
    /**
     * ترقيم الصفحات — ومعه كل مرشح قائم.
     *
     * الروابط تبنى بـ`tqs_cat_query` فتحمل البحث والمرشحات كما هي:
     * الترقيم الذي يبني رابطه بنفسه ينسى أحدها، فيقفز الزائر من الصفحة
     * الثانية إلى نتيجة غير التي كان يتصفحها. وهذا أشيع تعارض بين
     * بحث ومرشح وترقيم، وموضعه هنا مرة واحدة.
     *
     * والنافذة خمسة أرقام حول الحالي، والطرفان ظاهران دائما — فمن في
     * الصفحة الثامنة من اثنتي عشرة يرى أين هو وأين الحافتان.
     */
    function tqs_cat_pager($f, $page, $pages)
    {
        $pages = (int) $pages;
        $page  = (int) $page;
        if ($pages < 2) return '';

        $link = function ($p, $label, $cls = '', $aria = '') use ($f, $page) {
            $on = ((int) $p === $page);
            return '    <a class="cpager__i' . ($cls !== '' ? ' ' . $cls : '') . ($on ? ' is-on' : '') . '"'
                 . ' href="' . html_escape(tqs_cat_query($f, array('page' => $p))) . '"'
                 . ' data-tq-page="' . (int) $p . '"'
                 . ($on ? ' aria-current="page"' : '')
                 . ($aria !== '' ? ' aria-label="' . html_escape($aria) . '"' : '') . '>'
                 . $label . '</a>' . "\n";
        };
        $off = function ($cls) {
            return '    <span class="cpager__i cpager__i--nav ' . $cls . ' is-off" aria-hidden="true">'
                 . '<svg><use href="#i-arrow"></use></svg></span>' . "\n";
        };
        $arrow = '<svg aria-hidden="true"><use href="#i-arrow"></use></svg>';

        $win = array();
        for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) $win[] = $i;
        if (!in_array(1, $win, true))      array_unshift($win, 1);
        if (!in_array($pages, $win, true)) $win[] = $pages;

        $h  = '<nav class="cpager" aria-label="صفحات النتائج">' . "\n";
        $h .= ($page > 1) ? $link($page - 1, $arrow, 'cpager__i--nav', 'الصفحة السابقة') : $off('');

        $prev = 0;
        foreach ($win as $p) {
            if ($prev && $p > $prev + 1) $h .= '    <span class="cpager__gap" aria-hidden="true">…</span>' . "\n";
            $h .= $link($p, '<span class="tq-ltr">' . $p . '</span>');
            $prev = $p;
        }

        $h .= ($page < $pages)
            ? $link($page + 1, $arrow, 'cpager__i--nav cpager__i--next', 'الصفحة التالية')
            : $off('cpager__i--next');

        return $h . '</nav>' . "\n";
    }
}

if (!function_exists('tqs_cat_count_line')) {
    /** سطر «تعرض ١٢ من ٣١» — ويقول «لا شيء» صراحة حين لا نتيجة. */
    function tqs_cat_count_line($res)
    {
        if ((int) $res['total'] === 0) return 'لا نتائج';
        if ((int) $res['total'] <= (int) $res['per']) {
            return 'كل النتائج: <b class="tq-ltr">' . (int) $res['total'] . '</b>';
        }
        return 'تعرض <b class="tq-ltr">' . (int) $res['from'] . '–' . (int) $res['to']
             . '</b> من <b class="tq-ltr">' . (int) $res['total'] . '</b>';
    }
}
