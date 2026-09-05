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
            'أ' => t('ا'), 'إ' => t('ا'), 'آ' => t('ا'), 'ٱ' => t('ا'),
            'ة' => t('ه'), 'ى' => t('ي'), 'ؤ' => t('و'), 'ئ' => t('ي'),
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

if (!function_exists('tqs_cat_bare')) {
    /**
     * هل هذا عار من كل مرشح؟ — أي «لم يختر الزائر شيئا بعد».
     *
     * موضع واحد يقرؤه طرفان: `Taqdar_catalog_model::with_scope()` حين
     * يقرر حقن مرحلة الطالب، وبناء الروابط هنا حين يقرر كتابة
     * `scope=all`. ونسختان منه تعنيان رابطا يكتب «كل المحتوى» في حال
     * لا يقرؤها بها الخادم — فيضغط الزائر «مسح الكل» ولا يمسح شيء.
     *
     * والفرز ورقم الصفحة ليسا مرشحين: ترتيب لا شيء لا يزال لا شيء.
     * ويقرأ الشكلين معا — المرشحات مفسرة (قوائم) وقيم الرابط (نصوصا).
     */
    function tqs_cat_bare($f)
    {
        foreach (array('q', 'type', 'cat', 'grade', 'subject', 'teacher', 'price') as $k) {
            if (!empty($f[$k])) return false;
        }
        return true;
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

        /* «كل المحتوى» صراحة.
           للطالب مرحلة تحقن حين لا يختار شيئا (`with_scope`)، ورابط عار
           يعيد حقنها — فمن نزع آخر مرشح رأى الشبكة كما هي وظن أن نقرته
           ضاعت. و`scope=all` هو قوله «رأيت مرحلتي وأريد ما سواها»، ولا
           يكتب إلا حيث يلزم: أي مرشح آخر في الرابط يمنع الحقن أصلا. */
        if (!empty($f['mine']) && tqs_cat_bare($q)) $q['scope'] = 'all';

        $out = array();
        foreach ($q as $k => $v) if ((string) $v !== '') $out[$k] = $v;
        return $out ? base_url('catalog') . '?' . http_build_query($out) : base_url('catalog');
    }
}

if (!function_exists('tqs_cat_clear')) {
    /**
     * رابط «مسح الكل» — ويخرج من مرحلة الطالب أيضا، فالمسح مسح.
     *
     * و`base_url('catalog')` مكتوبا بيده لا يصلح بديلا: هو الرابط العاري
     * الذي يعيد حقن المرحلة، فيقرأ الزر «مسح الكل» ولا يمسح آخر مرشح.
     */
    function tqs_cat_clear($f)
    {
        return tqs_cat_query($f, array(
            'q'       => null, 'type'    => null, 'cat'   => null,
            'grade'   => null, 'subject' => null, 'teacher' => null,
            'price'   => null, 'sort'    => null,
        ));
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

if (!function_exists('tqs_cat_cover')) {
    /**
     * اسم غلاف البطاقة — صورة العنصر إن كانت، وإلا غلاف يشتق من نوعه ومادته.
     *
     * وهذا موضع عرض لا بيانات: القاعدة تكتب في `plans.image` القيمة النائبة
     * `logo`، فتعرض ست باقات **الشعار نفسه** ممطوطا 620×380 وجها للبطاقة؛
     * وسبعة عشر كورسا بلا مصغرة تسقط كلها على `subj-math` فيصير غلاف
     * الرياضيات وجه اللغة العربية والعلوم والإنجليزية معا.
     *
     * فالنائبة تعامل معاملة الفراغ، ويشتق الغلاف مما يعرفه العنصر عن نفسه:
     * الباقة من مرحلتها (`path-<المرحلة>`)، والكورس من مادته (`cov-<المادة>`).
     * وما لا يعرف يرد فارغا فيبقى البديل المخبوز كما هو — صفر تغير سلوك.
     */
    function tqs_cat_cover($it)
    {
        $img = isset($it['image']) ? trim((string) $it['image']) : '';
        if ($img === 'logo') $img = '';
        if ($img !== '') return $img;

        /* المرحلة: مسمى الفئة الجذر، وفيه واحد عربي (`المرحلة-المتوسطة`)
           لا يطابق اسم الملف — فبدون التطبيع تأخذ باقات المتوسطة غلاف
           الابتدائية بلا خطأ ولا أثر. */
        $stages = array(
            'primary'          => 'primary',
            'middle'           => 'middle',
            'المرحلة-المتوسطة' => 'middle',
            'secondary'        => 'secondary',
            'qudurat'          => 'qudurat',
            'digital'          => 'digital',
        );
        $cat   = isset($it['cat']) ? (string) $it['cat'] : '';
        $stage = isset($stages[$cat]) ? $stages[$cat] : '';

        if (isset($it['kind']) && $it['kind'] === 'plan') {
            /* الباقة لا تمر من هنا: غلافها من `tqs_plan_cover()` وحدها
               — وهي التي تقرأ `plans.image` أولا ثم تشتق. وكان هذا
               الفرع ينادي `tqs_plan_img()` قبل أن ينظر إلى ما رفعه
               المسؤول، فتفترق بطاقة الكتالوج عن بطاقة الرئيسية للباقة
               الواحدة. انظر `tqs_cat_media()`. */
            return '';
        }

        /* خريطة `subjects.name_ar` حرفية لا مشتقة من النص: الاسم يكتب من
           اللوحة، فأي مطابقة جزئية تكسر عند أول تعديل فيه. */
        $subjects = array(
            'الرياضيات'                         => 'math',
            'اللغة العربية'                     => 'arabic',
            'العلوم'                            => 'science',
            'القرآن الكريم والدراسات الإسلامية' => 'islamic',
            'الدراسات الإسلامية'                => 'islamic',
            'اللغة الإنجليزية'                  => 'english',
            'المهارات الرقمية'                  => 'digital',
            'الدراسات الاجتماعية'               => 'social',
        );
        $subj = isset($it['subject']) ? trim((string) $it['subject']) : '';
        if (isset($subjects[$subj])) return 'cov-' . $subjects[$subj];

        /* ملاذ أخير: الكورس يقرأ مادته من برنامجه، فكورس بلا برنامج
           (خمسة اليوم، منها ثلاثة للصف الخامس حقيقية) يخرج بلا مادة
           فيسقط على غلاف الرياضيات وهو إنجليزي أو رقمي. وعنوانه يبدأ
           بالمادة حرفيا، فتطابق **البداية** بالاسم الكامل — لا بجزء منه
           كي لا تلتبس «اللغة العربية» بـ«اللغة الإنجليزية». وما لا يبدأ
           باسم مادة يبقى على البديل المخبوز. */
        $title = isset($it['title']) ? trim((string) $it['title']) : '';
        if ($title !== '') {
            foreach ($subjects as $name => $slug) {
                if (strpos($title, $name) === 0) return 'cov-' . $slug;
            }
        }

        return '';
    }
}

if (!function_exists('tqs_cat_media')) {
    /**
     * وجه البطاقة — ويختلف بالنوع.
     *
     * الكتاب غلاف رأسي ملون بمادته، والباقة والبرنامج والكورس صورة
     * عريضة.
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

        $fallback = ($it['fallback'] !== '') ? $it['fallback'] : 'subj-math';

        /* TQ-PLAN-IMG — الباقة غلافها من مصدرها الواحد، فلا تعرض
           الرئيسية صورة والكتالوج أخرى للباقة نفسها. */
        if ($it['kind'] === 'plan' && function_exists('tqs_plan_cover')) {
            $src = tqs_plan_cover(array(
                'image' => isset($it['extra']['image']) ? $it['extra']['image'] : (string) $it['image'],
                'code'  => isset($it['extra']['code'])  ? $it['extra']['code']  : '',
                'stage' => isset($it['extra']['stage']) ? $it['extra']['stage'] : (string) $it['cat'],
            ), $fallback);
            return '<div class="ccard__media"><img src="' . html_escape($src)
                 . '" width="620" height="380" loading="lazy" decoding="async" alt=""></div>';
        }

        return '<div class="ccard__media"><img src="'
             . html_escape(tqs_img(tqs_cat_cover($it), $fallback))
             . '" width="620" height="380" loading="lazy" decoding="async" alt=""></div>';
    }
}

if (!function_exists('tqs_rich_text')) {
    /**
     * نص وصف من القاعدة، فقرات — لا وسوما ولا سطرا واحدا.
     *
     * الحقول الوصفية (`books.description` · `paths.description`)
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
            $h .= t('  <span class="ccard__flag">الاكثر طلبا</span>') . "\n";
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
            /* الشهريّ لا السنويّ بقرار المالك: البطاقة هنا كانت تطبع
               الرقم المخزَّن عاريًا بلا وسم زمنيّ، فيقرؤه الزائر بحسب
               ما رآه في الصفحة السابقة. و«شهريا» تُكتب صراحةً — رقمٌ
               بلا وحدته الزمنية يقرأ خطأ مهما كان صحيحًا.
               والحساب من `tqs_plan_cycle()` نفسها التي تحكم البطاقة
               في الرئيسية وصفحة الباقات — مصدر واحد لا ثلاثة. */
            /* TQ-PLAN-CYCLE — السعر بدورته هو، لا «شهريا» على كل شيء.
               كانت البطاقة تنادي `tqs_plan_cycle()` وحدها — وهي تفترض
               السنوية فتقسم على `12×0.8`؛ فباقة شهرية سعرها ٩٩٩ تقرأ
               «١٠٤ ر.س / شهريا»، ورقم لا يقابل ما يدفع. */
            $cy = tqs_plan_price(array(
                'price'  => max(0, (int) $it['price']),
                'period' => isset($it['extra']['period']) ? $it['extra']['period'] : '',
                'days'   => isset($it['extra']['days'])   ? (int) $it['extra']['days'] : 0,
            ));
            if ($cy['free']) {
                $h .= t('      <p class="ccard__price ccard__price--free">مجاني</p>') . "\n";
            } else {
                /* TQ-CYCLE-BUY — الشهري متى كان **يشترى**، وسعر الباقة
                   بدورته متى لم يكن. و`month` ترد سعر الباقة نفسه لمن لا
                   معادل له، فطباعتها بـ«شهريا» دائما تكتب «1,000 ر.س /
                   شهريا» على باقة ربع سنوية ثمنها ألف لثلاثة أشهر —
                   رقم صحيح بوحدة كاذبة، وهو أسوأ من رقم خاطئ يظهر. */
                $h .= '      <p class="ccard__price"><b class="tq-ltr">'
                    . number_format($cy['has_alt'] ? $cy['month'] : $cy['total'])
                    . t('</b><span>ر.س / ')
                    . ($cy['has_alt'] ? t('شهريا') : html_escape($cy['unit']))
                    . '</span></p>' . "\n";
            }
            $h .= t('      <span class="ccard__cta">تفاصيل الباقة')
                . '<svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></span>' . "\n";
        } elseif ($it['kind'] === 'book') {
            /* «اقرا وحمل» فوق كتاب لم يرفع ملفه وعد مكسور: الزائر يضغط
               فيجد زرا معطلا. والدعوة تقول ما يجده فعلا. */
            $has = (isset($it['extra']['file']) && (string) $it['extra']['file'] !== '');

            /* TQ-BOOK — **والسعر يقال متى كان الكتاب يباع.**
               كانت البطاقة تكتب «مجاني» على كل كتاب مهما كان — وهو صواب
               يوم كانت الكتب مجانية كلها. ومع البيع المفرد صارت تعد
               بالمجان ثم تفتح على زر «اشتر الكتاب بـ٤٥ ر.س»: وعد ينقضه
               ما بعده بنقرة، والزائر يشك في أيهما الصحيح.
               والسعر من `$it['price']` الذي كتبه
               `Taqdar_catalog_model::books()` من `offer()` نفسها. */
            $sellable = !empty($it['extra']['sellable']);
            if ($sellable && (int) $it['price'] > 0) {
                $h .= '      <p class="ccard__price"><b class="tq-ltr">'
                    . number_format(((int) $it['price']) / 100) . t('</b><span>ر.س</span></p>') . "\n";
            } else {
                $h .= t('      <p class="ccard__price ccard__price--free">مجاني</p>') . "\n";
            }

            /* والدعوة تتبع السعر: «اقرا وحمل» على كتاب مدفوع تعد بتحميل
               لا يقع — القارئ يعرض صفحة صفحة ولا ينزل ملفا. */
            $h .= '      <span class="ccard__cta">'
                . ($sellable ? t('تفاصيل الكتاب') : ($has ? t('اقرا وحمل') : t('تفاصيل الكتاب')))
                . '<svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></span>' . "\n";

        } elseif ($it['kind'] === 'course') {
            /* الكورس محتوى الباقة لا سلعة بجوارها: «ضمن الباقات» كما يقرأ
               البرنامج، إلا ما وسم مجانيا فيقرأ مجانا. وبطاقة تقول سعرا
               لشيء لا يباع مفردا توازن الزائر بين خيارين أحدهما وهم.
               والدعوة تقول ما يجده: كورس بلا درس واحد لا يعد بمنهج.

               TQ-COURSE-SALE — **إلا ما أعلن للبيع مفردا، فسعره يقال.**
               والصمت هنا كان أسوأ من رقم: بطاقة تقول «ضمن الباقات» ثم
               تفتح على زر «اشتر هذه الدورة بـ١٩٩ ر.س» تجعل الزائر يشك
               في أيهما الصحيح. والسعر من `$it['price']` الذي كتبه
               `Taqdar_catalog_model::courses()` من `offer()` نفسها. */
            if (!empty($it['free'])) {
                $h .= t('      <p class="ccard__price ccard__price--free">مجاني</p>') . "\n";
            } elseif ((int) $it['price'] > 0) {
                $h .= '      <p class="ccard__price"><b class="tq-ltr">'
                    . number_format(((int) $it['price']) / 100) . t('</b><span>ر.س</span></p>') . "\n";
            } else {
                $h .= t('      <p class="ccard__price ccard__price--in">ضمن الباقات</p>') . "\n";
            }
            $h .= '      <span class="ccard__cta">'
                . (!empty($it['ready']) ? t('استعرض دروسه') : t('تفاصيل الكورس'))
                . '<svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></span>' . "\n";
        } else {
            $h .= t('      <p class="ccard__price ccard__price--in">ضمن الباقات</p>') . "\n";
            $h .= t('      <span class="ccard__cta">ما في هذا البرنامج')
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

        $h  = t('<nav class="cpager" aria-label="صفحات النتائج">') . "\n";
        $h .= ($page > 1) ? $link($page - 1, $arrow, 'cpager__i--nav', t('الصفحة السابقة')) : $off('');

        $prev = 0;
        foreach ($win as $p) {
            if ($prev && $p > $prev + 1) $h .= '    <span class="cpager__gap" aria-hidden="true">…</span>' . "\n";
            $h .= $link($p, '<span class="tq-ltr">' . $p . '</span>');
            $prev = $p;
        }

        $h .= ($page < $pages)
            ? $link($page + 1, $arrow, 'cpager__i--nav cpager__i--next', t('الصفحة التالية'))
            : $off('cpager__i--next');

        return $h . '</nav>' . "\n";
    }
}

if (!function_exists('tqs_cat_count_line')) {
    /** سطر «تعرض ١٢ من ٣١» — ويقول «لا شيء» صراحة حين لا نتيجة. */
    function tqs_cat_count_line($res)
    {
        if ((int) $res['total'] === 0) return t('لا نتائج');
        if ((int) $res['total'] <= (int) $res['per']) {
            return t('كل النتائج: <b class="tq-ltr">') . (int) $res['total'] . '</b>';
        }
        return t('تعرض <b class="tq-ltr">') . (int) $res['from'] . '–' . (int) $res['to']
             . t('</b> من <b class="tq-ltr">') . (int) $res['total'] . '</b>';
    }
}
