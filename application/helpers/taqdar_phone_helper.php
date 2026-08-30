<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * رقم الجوال — رمز دولة ورقم وطني، لا رقم سعودي وحده.
 *
 * ----------------------------------------------------------------------
 * TQ-PHONE-INTL — لماذا
 *
 * كان الحقل يقبل صورة واحدة: `05XXXXXXXX` سعوديا، ويفحص
 * `^5[0-9]{8}$` في `Login::register`. فمعلم في القاهرة أو ولي أمر في
 * عمان يكتب رقمه الصحيح فيرد عليه «رقم الجوال غير صحيح. اكتبه هكذا:
 * 0501234567» — وهي رسالة لا تقول له إن بلده غير مقبول، فيظن العطب في
 * الرقم ويعيد كتابته بصور شتى حتى ييأس. وليس في الصفحة ما يقول إن
 * المنصة سعودية الأرقام.
 *
 * وهو أثقل من مجرد حقل يرفض: **واتساب هو القناة الثانية للرمز** وقناة
 * إشعارات المال كلها. فرقم يخزن بلا رمز دولة يرسل إلى الدولة الخطأ أو
 * يرد `131026` بلا سبب مفهوم — وهو ما تحذر منه `Taqdar_wa_model` نصا.
 *
 * ----------------------------------------------------------------------
 * القرارات
 *
 * ١ — **الرمز منتق لا مكتوب.** حقل نصي واحد يقبل `+20` و`0020` و`0` و
 *     `٢٠` — أربع صور لشيء واحد، وكل صورة تفتح طريقا إلى خطأ. والمنتقي
 *     يجعل الرمز قيمة مغلقة، والحقل النصي يبقى **للرقم الوطني وحده**.
 *
 * ٢ — **الوصف بيانات لا سلسلة `if`.** الدولة صف في `tq_dial_codes()`
 *     فيه رمزها وطول رقمها وبدايات جوالها ومثاله. فإضافة دولة سطر
 *     واحد، ويظهر أثره في النموذج وفي الفحص وفي رسالة الخطأ معا. وهو
 *     مبدأ `Taqdar_wallet_model::$CHANNELS` نفسه.
 *
 * ٣ — **الطول يفحص دائما، والبداية حيث تعرف.** قواعد بدايات الجوال
 *     تتغير بإصدار مشغل جديد، وقائمة قديمة ترد رقما صحيحا — ورد رقم
 *     صحيح يمنع التسجيل كله. فحيث لا يقين تترك `starts` فارغة ويكفي
 *     الطول، وحيث اليقين قائم (السعودية ومصر والإمارات …) تفحص.
 *
 * ٤ — **يخزن بصورة واحدة: `+<رمز><وطني>`.** والبادئة `+` ليست زينة:
 *     `Taqdar_wa_model::to_e164()` تقبل الرقم الدولي الصريح بطوله
 *     المعقول (٨–١٥)، وبلا `+` تشترط أحد عشر رقما فأكثر — فرقم لبناني
 *     (`9613123456` عشرة) أو قمري (`2693212345` عشرة) يسقط صامتا.
 *
 * ٥ — **وما خزن قبل هذا يبقى صالحا.** الأرقام في القاعدة اليوم وطنية
 *     عارية (`501234567`)، و`to_e164()` تعرفها سعودية كما كانت. فلا
 *     ترحيل ولا رقم يفقد.
 */

if (!function_exists('tq_dial_codes')) {
    /**
     * الدول العربية اثنتان وعشرون — رمزا وطولا ومثالا.
     *
     * `len`    : أقل وأكثر طول للرقم الوطني (بلا الصفر ولا رمز الدولة).
     * `starts` : بدايات الجوال المعروفة، وفارغة تعني «الطول وحده يفحص».
     * `ex`     : مثال يطبع في الحقل — رقم حقيقي الشكل لا `XXXX`.
     */
    function tq_dial_codes()
    {
        static $c = null;
        if ($c !== null) return $c;

        /* TQ-I18N — اسم الدولة يعرض في منتقي رمز الاتصال، فيترجم. والرمز
           والعلم والأطوال بلا نص، فتمر كما هي. */
        return $c = tq_t_deep(array(
            'SA' => array('dial' => '966', 'name' => t('السعودية'),    'flag' => '🇸🇦', 'len' => array(9, 9),  'starts' => array('5'),                'ex' => '512345678'),
            'AE' => array('dial' => '971', 'name' => t('الإمارات'),    'flag' => '🇦🇪', 'len' => array(9, 9),  'starts' => array('5'),                'ex' => '501234567'),
            'EG' => array('dial' => '20',  'name' => t('مصر'),         'flag' => '🇪🇬', 'len' => array(10, 10), 'starts' => array('1'),               'ex' => '1012345678'),
            'KW' => array('dial' => '965', 'name' => t('الكويت'),      'flag' => '🇰🇼', 'len' => array(8, 8),  'starts' => array('5', '6', '9'),      'ex' => '51234567'),
            'QA' => array('dial' => '974', 'name' => t('قطر'),         'flag' => '🇶🇦', 'len' => array(8, 8),  'starts' => array('3', '5', '6', '7'), 'ex' => '33123456'),
            'BH' => array('dial' => '973', 'name' => t('البحرين'),     'flag' => '🇧🇭', 'len' => array(8, 8),  'starts' => array('3', '6'),           'ex' => '36123456'),
            'OM' => array('dial' => '968', 'name' => t('عمان'),        'flag' => '🇴🇲', 'len' => array(8, 8),  'starts' => array('7', '9'),           'ex' => '92123456'),
            'JO' => array('dial' => '962', 'name' => t('الأردن'),      'flag' => '🇯🇴', 'len' => array(9, 9),  'starts' => array('7'),                'ex' => '791234567'),
            'PS' => array('dial' => '970', 'name' => t('فلسطين'),      'flag' => '🇵🇸', 'len' => array(9, 9),  'starts' => array('5'),                'ex' => '592123456'),
            'LB' => array('dial' => '961', 'name' => t('لبنان'),       'flag' => '🇱🇧', 'len' => array(7, 8),  'starts' => array(),                   'ex' => '71123456'),
            'SY' => array('dial' => '963', 'name' => t('سوريا'),       'flag' => '🇸🇾', 'len' => array(9, 9),  'starts' => array('9'),                'ex' => '944567890'),
            'IQ' => array('dial' => '964', 'name' => t('العراق'),      'flag' => '🇮🇶', 'len' => array(10, 10), 'starts' => array('7'),               'ex' => '7912345678'),
            'YE' => array('dial' => '967', 'name' => t('اليمن'),       'flag' => '🇾🇪', 'len' => array(9, 9),  'starts' => array('7'),                'ex' => '712345678'),
            'SD' => array('dial' => '249', 'name' => t('السودان'),     'flag' => '🇸🇩', 'len' => array(9, 9),  'starts' => array('9', '1'),           'ex' => '912345678'),
            'LY' => array('dial' => '218', 'name' => t('ليبيا'),       'flag' => '🇱🇾', 'len' => array(9, 9),  'starts' => array('9'),                'ex' => '912345678'),
            'TN' => array('dial' => '216', 'name' => t('تونس'),        'flag' => '🇹🇳', 'len' => array(8, 8),  'starts' => array('2', '4', '5', '9'), 'ex' => '20123456'),
            'DZ' => array('dial' => '213', 'name' => t('الجزائر'),     'flag' => '🇩🇿', 'len' => array(9, 9),  'starts' => array('5', '6', '7'),      'ex' => '551234567'),
            'MA' => array('dial' => '212', 'name' => t('المغرب'),      'flag' => '🇲🇦', 'len' => array(9, 9),  'starts' => array('6', '7'),           'ex' => '612345678'),
            'MR' => array('dial' => '222', 'name' => t('موريتانيا'),   'flag' => '🇲🇷', 'len' => array(8, 8),  'starts' => array('2', '3', '4'),      'ex' => '22123456'),
            'SO' => array('dial' => '252', 'name' => t('الصومال'),     'flag' => '🇸🇴', 'len' => array(7, 9),  'starts' => array(),                   'ex' => '612345678'),
            'DJ' => array('dial' => '253', 'name' => t('جيبوتي'),      'flag' => '🇩🇯', 'len' => array(8, 8),  'starts' => array('7'),                'ex' => '77123456'),
            'KM' => array('dial' => '269', 'name' => t('جزر القمر'),   'flag' => '🇰🇲', 'len' => array(7, 7),  'starts' => array('3', '4'),           'ex' => '3212345'),
        ));
    }
}

if (!function_exists('tq_phone_default_iso')) {
    /** بلد الافتراض. المنصة سعودية، وأكثر من يسجل سعودي. */
    function tq_phone_default_iso()
    {
        return 'SA';
    }
}

if (!function_exists('tq_phone_digits')) {
    /**
     * أرقام لاتينية وحدها من أي صورة كتبت.
     *
     * الأرقام العربية والفارسية تحول، وكل ما ليس رقما يسقط: المسافة
     * والشرطة والقوس ونقطة الترقيم — كلها زينة يكتبها الإنسان ولا
     * تحمل معنى في رقم.
     */
    function tq_phone_digits($raw)
    {
        $p = strtr((string) $raw, array(
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ));
        return preg_replace('/\D+/', '', $p);
    }
}

if (!function_exists('tq_phone_iso_ok')) {
    /** مفتاح دولة مقبول، أو الافتراض. قيمة مغلقة لا نص حر. */
    function tq_phone_iso_ok($iso)
    {
        $k = strtoupper(trim((string) $iso));
        $all = tq_dial_codes();
        return isset($all[$k]) ? $k : tq_phone_default_iso();
    }
}

if (!function_exists('tq_phone_national')) {
    /**
     * الرقم الوطني من أي صورة كتبها صاحبه، ضمن دولة معلومة.
     *
     * من ينتقي «مصر» ثم يكتب `+20 100 123 4567` أو `0020...` أو
     * `01001234567` يقصد الرقم نفسه — ورد اثنتين من الثلاث لأن صاحبها
     * كتب ما تعود عليه ليس فحصا، بل عناد. فرمز الدولة يقص إن كتب،
     * والصفر الوطني يقص، ويبقى الوطني عاريا.
     */
    function tq_phone_national($raw, $iso)
    {
        $iso = tq_phone_iso_ok($iso);
        $all = tq_dial_codes();
        $c   = $all[$iso];
        $d   = tq_phone_digits($raw);
        if ($d === '') return '';

        /* **قص رمز الدولة تخمين، فيرشح ولا يفرض.**
           قصه بلا شرط يكسر أرقاما صحيحة: رمز تونس `216` وجوالها قد
           يبدأ بـ`21612345` — فالقص يترك `12345` ويرد رقما صحيحا.
           فيبنى مرشحان ويقبل من يقع طوله في مدى البلد: الكامل أولا،
           والمقصوص بعده. وترتيبهما هو ما يحفظ الرقم التونسي.

           والصفر الوطني يقص من كليهما: `0501…` سعودي و`0201…` مصري
           كتبهما صاحباهما كما يكتبان في بلديهما. */
        $cand = array();
        $bare = preg_replace('/^0+/', '', $d);
        if ($bare !== '') $cand[] = $bare;

        $dial = $c['dial'];
        if (strpos($bare, $dial) === 0) {
            $cut = preg_replace('/^0+/', '', substr($bare, strlen($dial)));
            if ($cut !== '') $cand[] = $cut;
        }

        /* المرشح الذي يقع في المدى **ويبدأ ببداية جوال معروفة** يعلو،
           ثم من يقع في المدى وحده، ثم الأول كما هو ليقرأ الفاحص طوله
           في رسالة الخطأ. */
        foreach (array(true, false) as $strict) {
            foreach ($cand as $n) {
                $len = strlen($n);
                if ($len < $c['len'][0] || $len > $c['len'][1]) continue;
                if ($strict && $c['starts'] && !in_array($n[0], $c['starts'], true)) continue;
                return $n;
            }
        }
        return $cand ? $cand[0] : '';
    }
}

if (!function_exists('tq_phone_check')) {
    /**
     * يفحص الرقم الوطني في دولته.
     *
     * @return array ok · e164 (`+9665...`) · national · iso · error
     */
    function tq_phone_check($raw, $iso, $label = 'رقم الجوال')
    {
        $iso  = tq_phone_iso_ok($iso);
        $all  = tq_dial_codes();
        $c    = $all[$iso];
        $nat  = tq_phone_national($raw, $iso);
        $out  = array('ok' => false, 'e164' => '', 'national' => $nat,
                      'iso' => $iso, 'error' => '');

        if ($nat === '') {
            $out['error'] = $label . t(' مطلوب.');
            return $out;
        }

        $len = strlen($nat);
        if ($len < $c['len'][0] || $len > $c['len'][1]) {
            /* الرسالة تقول البلد والطول والمثال: «غير صحيح» وحدها تترك
               صاحبها يخمن أي جزء أخطأ فيه. */
            $need = ($c['len'][0] === $c['len'][1])
                  ? ($c['len'][0] . t(' أرقام'))
                  : (t('من ') . $c['len'][0] . t(' إلى ') . $c['len'][1] . t(' أرقام'));
            $out['error'] = $label . t(' في ') . $c['name'] . t(' يكون ') . $need
                          . t(' بعد رمز الدولة — مثل: ') . $c['ex'] . '.';
            return $out;
        }

        if ($c['starts'] && !in_array($nat[0], $c['starts'], true)) {
            $out['error'] = t('هذا ليس رقم جوال في ') . $c['name']
                          . t(' — جوالها يبدأ بـ') . implode(t(' أو '), $c['starts'])
                          . t('، مثل: ') . $c['ex'] . '.';
            return $out;
        }

        $out['ok']   = true;
        $out['e164'] = '+' . $c['dial'] . $nat;
        return $out;
    }
}

if (!function_exists('tq_phone_split')) {
    /**
     * رقم مخزن ⟵ (دولته، وطنيه) لملء النموذج ثانية.
     *
     * وثلاث صور تصل إلى هنا: `+9665…` وهي ما يكتبه هذا الحقل اليوم،
     * و`9665…` بلا `+` مما كتبته شاشة قديمة، و`5…` وطنيا عاريا وهو
     * **كل ما في القاعدة قبل هذا الحقل** — فالأخير يقرأ سعوديا كما
     * كان يقرأ، ولا يفقد رقم.
     *
     * والأطول يطابق أولا: `+970` (فلسطين) و`+97…` ليست بادئة لبعضها،
     * ولكن `+96` بادئة لسبع دول — فترتيب الطول يمنع أن يقرأ رمز دولة
     * نصف رمز أخرى.
     */
    function tq_phone_split($stored)
    {
        $d = tq_phone_digits($stored);
        $def = tq_phone_default_iso();
        if ($d === '') return array($def, '');

        $all = tq_dial_codes();
        $by_len = $all;
        uasort($by_len, function ($a, $b) {
            return strlen($b['dial']) - strlen($a['dial']);
        });

        foreach ($by_len as $iso => $c) {
            $dial = $c['dial'];
            if (strpos($d, $dial) !== 0) continue;
            $nat = substr($d, strlen($dial));
            $len = strlen($nat);
            if ($len >= $c['len'][0] && $len <= $c['len'][1]) {
                return array($iso, $nat);
            }
        }

        /* لا رمز يطابق: وطني عار — والافتراض بلد المنصة، وهو ما كانت
           القاعدة تعنيه بالضبط قبل أن يوجد هذا الحقل. */
        return array($def, preg_replace('/^0+/', '', $d));
    }
}

if (!function_exists('tq_phone_pretty')) {
    /** الرقم كما يعرض لإنسان: `+966 512345678`. */
    function tq_phone_pretty($stored)
    {
        list($iso, $nat) = tq_phone_split($stored);
        if ($nat === '') return '';
        $all = tq_dial_codes();
        return '+' . $all[$iso]['dial'] . ' ' . $nat;
    }
}

if (!function_exists('tq_phone_field')) {
    /**
     * حقل الجوال — منتقي دولة وحقل رقم، بجلد الموقع أو جلد البوابة.
     *
     * موضع واحد يطبعه التسجيل وإعدادات المعلم وإعدادات ولي الأمر
     * وإعدادات الطالب. ونسخة ثانية من هذا الوسم تعني قائمة دول تشيخ في
     * شاشة ولا تشيخ في أختها، ورقما يقبل هنا ويرفض هناك.
     *
     * **والحاوية تحمل `form-field` مع `form-phone`** في جلد الموقع: فاحص
     * النموذج في `site.js` يمسك `.form-field` ليضع حد الخطأ، ويقرأ
     * `.sr-only` بداخلها ليسمي الحقل في رسالته. فحاوية باسم جديد وحده
     * تفقد الاثنين — يرفض الحقل بلا حد أحمر، وتقول الرسالة اسم
     * `placeholder` وهو رقم مثال.
     *
     * **وقواعد الدولة تسافر مع خياراتها** (`data-len` · `data-starts` ·
     * `data-ex`): الفاحص في المتصفح كان يكتب `^5[0-9]{8}$` في سطره،
     * فيرد رقما مصريا صحيحا سيقبله الخادم — وشرطان مختلفان لحقل واحد
     * أسوأ من شرط واحد ضيق.
     *
     * @param string $name  اسم حقل الرقم؛ ومنتقي الدولة `<name>_cc`.
     * @param array  $o     skin (`site`|`portal`) · value · iso · national ·
     *                      required · id · label · hint
     */
    function tq_phone_field($name, $o = array())
    {
        $skin = isset($o['skin']) ? $o['skin'] : 'site';
        $val  = isset($o['value']) ? (string) $o['value'] : '';
        $req  = !empty($o['required']);
        $lbl  = isset($o['label']) ? (string) $o['label'] : t('رقم الجوال');
        $id   = isset($o['id']) ? (string) $o['id'] : ('tqph-' . preg_replace('/\W+/', '-', $name));
        $all  = tq_dial_codes();

        /* الدولة المصرحة تعلو، والوطني يشتق **في ضوئها** لا في ضوء
           تخمين `tq_phone_split()`. وهذا يظهر عند إعادة النموذج بعد رفض:
           صاحبه انتقى «مصر» وكتب `01012345678`، فلو قرئ الوطني بتخمين
           عام لعاد إليه رقم آخر تحت علم مصر. */
        if (!empty($o['iso'])) {
            $iso = tq_phone_iso_ok($o['iso']);
            $nat = tq_phone_national($val, $iso);
        } else {
            list($iso, $nat) = tq_phone_split($val);
        }
        if (isset($o['national'])) $nat = (string) $o['national'];

        $c  = $all[$iso];
        $ex = $c['ex'];

        $opts = '';
        foreach ($all as $k => $x) {
            $opts .= '<option value="' . $k . '"'
                  . ' data-dial="' . html_escape($x['dial']) . '"'
                  . ' data-min="' . (int) $x['len'][0] . '"'
                  . ' data-max="' . (int) $x['len'][1] . '"'
                  . ' data-starts="' . html_escape(implode('', $x['starts'])) . '"'
                  . ' data-ex="' . html_escape($x['ex']) . '"'
                  . ' data-cname="' . html_escape($x['name']) . '"'
                  . (($k === $iso) ? ' selected' : '') . '>'
                  . html_escape($x['flag'] . ' ' . $x['name'] . ' +' . $x['dial'])
                  . '</option>';
        }

        $num = '<input type="tel" dir="ltr" inputmode="numeric" autocomplete="tel-national"'
             . ' id="' . html_escape($id) . '"'
             . ' name="' . html_escape($name) . '"'
             . ' data-tq-phone-num maxlength="15"'
             . ' placeholder="' . html_escape($ex) . '"'
             . ($req ? ' data-req="1"' : '')   /* لا `required` ثابتة: سكربت
                البوابة يضعها وينزعها بحسب اللوح الظاهر، وثابتة تبقى على
                لوح مطوي فيرفض المتصفح إرسال نموذج لا يرى صاحبه خطأه. */
             . ' value="' . html_escape($nat) . '"';

        if ($skin === 'portal') {
            $h  = '<div class="tq-phone" data-tq-phone>';
            $h .= '<select class="tq-input tq-phone__cc" name="' . html_escape($name) . '_cc"'
                . t(' data-tq-phone-cc aria-label="رمز الدولة">') . $opts . '</select>';
            $h .= $num . ' class="tq-input tq-phone__num"></div>';
            if (!empty($o['hint'])) {
                $h .= '<p class="tq-caption">' . html_escape($o['hint']) . '</p>';
            }
            return $h;
        }

        $h  = '<div class="form-field form-phone" data-tq-phone>';
        $h .= '<svg aria-hidden="true"><use href="#i-phone"></use></svg>';
        $h .= '<label class="sr-only" for="' . html_escape($id) . '">' . html_escape($lbl) . '</label>';
        $h .= '<select class="form-phone__cc" name="' . html_escape($name) . '_cc"'
            . t(' data-tq-phone-cc aria-label="رمز الدولة">') . $opts . '</select>';
        $h .= $num . ' class="form-phone__num"></div>';
        if (!empty($o['hint'])) {
            $h .= '<p class="form-hint">' . html_escape($o['hint']) . '</p>';
        }
        return $h;
    }
}
