<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * TQ-I18N — اللغة والاتجاه والترجمة، مصدر حقيقة واحد.
 *
 * كانت اللغة تشتق في ثلاثة مواضع لا تتفق: غلاف الموقع
 * (`frontend/taqdar/index.php`) وغلاف اللوحة (`backend/index.php`) و
 * `get_phrase()` الموروثة — وكل واحد منها يقرأ `settings` و`session` بترتيبه
 * ويشتق الاتجاه من `language_dirs` وهو صف في القاعدة كتب فيه `hindi: rtl`
 * ومفتاح فارغ بقيمة `null`. فاتجاه الصفحة يقرر بصف يحرره من يشاء، وبلا صف
 * صحيح تفتح الصفحة بالاتجاه الخطأ ولا شيء يخطئ.
 *
 * وهنا اللغة تحل **مرة واحدة لكل طلب** بترتيب صريح، والاتجاه يشتق من
 * **الرمز الأيزو** لا من صف. والترجمة **مفتاحها النص العربي نفسه**: فما لا
 * يترجم يعرض عربيا كما كان يعرض حرفا بحرف — وهي قاعدة «بلا مفتاح لا شيء
 * يتغير» نفسها التي تحكم تاب وبيع الكورسات.
 *
 * ولا قاعدة بيانات في مسار القراءة: القاموس ملفات PHP يقرؤها opcache مرة،
 * و`get_phrase()` الموروثة كانت تستعلم القاعدة **لكل نداء** — ٢٢٠٠ نداء في
 * الشجرة يعني مئات الاستعلامات في الصفحة الواحدة.
 */

/* ====================================================================
   اللغات المتاحة
   ==================================================================== */

if (!function_exists('tq_languages')) {
    /**
     * اللغات المعروضة في المبدل.
     *
     * المفتاح اسم اللغة كما يخزن في `session` و`tq_prefs_user.language` وعمود
     * جدول `language` — والثلاثة تستعمل الاسم لا الرمز، فتغييره هنا يقطعها.
     *
     * @return array<string, array{label:string, iso:string, dir:string, short:string}>
     */
    function tq_languages()
    {
        return array(
            'arabic'  => array('label' => 'العربية', 'iso' => 'ar', 'dir' => 'rtl', 'short' => 'ع'),
            'english' => array('label' => 'English', 'iso' => 'en', 'dir' => 'ltr', 'short' => 'EN'),
        );
    }
}

if (!function_exists('tq_lang_default')) {
    /** لغة المنصة حين لا يختار أحد — من `settings` ثم العربية. */
    function tq_lang_default()
    {
        static $d = null;
        if ($d !== null) return $d;

        $s = function_exists('get_settings') ? strtolower(trim((string) get_settings('language'))) : '';
        $d = isset(tq_languages()[$s]) ? $s : 'arabic';
        return $d;
    }
}

/* ====================================================================
   حل اللغة — مرة واحدة لكل طلب
   ==================================================================== */

if (!function_exists('tq_lang')) {
    /**
     * لغة هذا الطلب.
     *
     * الترتيب مقصود:
     *   ١) الجلسة    — ما اختاره صاحبها في هذا المتصفح الآن.
     *   ٢) تفضيل الحساب (`tq_prefs_user.language`) — يتبعه على كل جهاز.
     *   ٣) كعكة الزائر — من بدل قبل أن يسجل، فلا يعود إلى العربية عند كل زيارة.
     *   ٤) إعداد المنصة.
     *
     * والقراءة من القاعدة **مرة واحدة**: تكتب في الجلسة بعدها، فلا استعلام
     * ثان في الطلب نفسه ولا في الطلبات التالية.
     */
    function tq_lang()
    {
        $lang = tq_i18n_state();
        if ($lang !== null) return $lang;

        $CI    = function_exists('get_instance') ? get_instance() : null;
        $langs = tq_languages();

        /* ١ — الجلسة. */
        if ($CI && isset($CI->session)) {
            $s = strtolower(trim((string) $CI->session->userdata('language')));
            if (isset($langs[$s])) return tq_i18n_state($s);
        }

        /* ٢ — تفضيل الحساب. الجدول ينشأ وقت التشغيل، فقد لا يكون بعد:
              استعلام على جدول غائب يرمي استثناء يبيض الصفحة، والافتراض أهون. */
        if ($CI && isset($CI->session) && isset($CI->db)) {
            $uid = (int) $CI->session->userdata('user_id');
            if ($uid) {
                try {
                    if ($CI->db->table_exists('tq_prefs_user')) {
                        $row = $CI->db->select('language')->where('user_id', $uid)
                                      ->get('tq_prefs_user')->row_array();
                        $p = $row ? strtolower(trim((string) $row['language'])) : '';
                        if (isset($langs[$p])) {
                            $CI->session->set_userdata('language', $p);
                            return tq_i18n_state($p);
                        }
                    }
                } catch (Exception $e) {
                    $CI->db->reset_query(); // TQ-BUILDER-DIRTY
                }
            }
        }

        /* ٣ — كعكة الزائر. */
        if (!empty($_COOKIE['tq_lang'])) {
            $c = strtolower(trim((string) $_COOKIE['tq_lang']));
            if (isset($langs[$c])) {
                if ($CI && isset($CI->session)) $CI->session->set_userdata('language', $c);
                return tq_i18n_state($c);
            }
        }

        /* ٤ — إعداد المنصة. */
        return tq_i18n_state(tq_lang_default());
    }
}

if (!function_exists('tq_lang_set')) {
    /**
     * يثبت لغة هذا الطلب — وينسى ما حل من قبل.
     *
     * ينادى من مبدل اللغة ومن حفظ التفضيلات، ومن أي مسار يعرف اللغة قبل أن
     * تحل (مهام الكرون التي تكتب إشعارا باسم صاحبه). ويصفر الكاش لأن سلاسل
     * الطلب نفسه بعده يجب أن تقرأ الجديد لا القديم.
     */
    function tq_lang_set($lang)
    {
        $lang  = strtolower(trim((string) $lang));
        $langs = tq_languages();
        if (!isset($langs[$lang])) return false;

        $CI = function_exists('get_instance') ? get_instance() : null;
        if ($CI && isset($CI->session)) $CI->session->set_userdata('language', $lang);

        tq_i18n_state($lang);
        return true;
    }
}

if (!function_exists('tq_i18n_state')) {
    /**
     * حامل اللغة المحلولة — الموضع الوحيد الذي تسكن فيه.
     *
     * و`static` داخل `tq_lang()` لا يصلح: لا سبيل إلى تصفيرها من الخارج،
     * فمن يبدل اللغة وسط الطلب (مبدل اللغة، وحفظ التفضيلات، ومهمة الكرون
     * التي تكتب اشعارا بلغة صاحبه) يبقى يقرأ القديم إلى نهاية الطلب.
     *
     * @param string|null|false $set  قيمة للتثبيت، أو `false` للتصفير
     */
    function tq_i18n_state($set = null)
    {
        static $lang = null;
        if (func_num_args()) {
            $lang = ($set === false) ? null : $set;
        }
        return $lang;
    }
}

/* ====================================================================
   الاتجاه والرموز
   ==================================================================== */

if (!function_exists('tq_dir')) {
    /** اتجاه الكتابة لهذه اللغة — `rtl` أو `ltr`. */
    function tq_dir($lang = null)
    {
        $lang  = $lang ?: tq_lang();
        $langs = tq_languages();
        return isset($langs[$lang]) ? $langs[$lang]['dir'] : 'rtl';
    }
}

if (!function_exists('tq_is_rtl')) {
    function tq_is_rtl($lang = null) { return tq_dir($lang) === 'rtl'; }
}

if (!function_exists('tq_iso')) {
    /** رمز اللغة الأيزو — يكتب في `<html lang>` وفي ترويسات البريد. */
    function tq_iso($lang = null)
    {
        $lang  = $lang ?: tq_lang();
        $langs = tq_languages();
        return isset($langs[$lang]) ? $langs[$lang]['iso'] : 'ar';
    }
}

if (!function_exists('tq_locale')) {
    /** الموضع الكامل — للتواريخ والأرقام. */
    function tq_locale($lang = null)
    {
        return tq_iso($lang) === 'en' ? 'en_US' : 'ar_SA';
    }
}

if (!function_exists('tq_opposite_dir')) {
    /** الاتجاه المعاكس — يستعمل لحقول تكتب دائما بغير اتجاه الصفحة (روابط، بريد). */
    function tq_opposite_dir($lang = null) { return tq_dir($lang) === 'rtl' ? 'ltr' : 'rtl'; }
}

/* ====================================================================
   القاموس
   ==================================================================== */

if (!function_exists('tq_i18n_key')) {
    /**
     * تسوية المفتاح.
     *
     * النص نفسه يكتب في قالب بمسافات بادئة وأسطر، وفي نموذج بسطر واحد —
     * ومفتاحان لنص واحد يعنيان ترجمة تظهر في شاشة وتغيب عن أختها. فالمسافات
     * تطوى والأطراف تقص، والباقي يقارن حرفا بحرف.
     */
    function tq_i18n_key($s)
    {
        $s = (string) $s;
        $s = preg_replace('/\x{00A0}/u', ' ', $s);       // مسافة غير كاسرة = مسافة
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }
}

if (!function_exists('tq_catalog')) {
    /**
     * قاموس اللغة — يحمل مرة واحدة لكل طلب.
     *
     * الملفات في `application/language/tq/<اللغة>/*.php`، كل واحد يرد مصفوفة
     * `[النص العربي => الترجمة]`. وتفريقها ملفات لا ملفا واحدا مقصود: ملف
     * بثمانية آلاف مدخل يستحيل مراجعته في طلب سحب، وتعارضه في الدمج مضمون.
     */
    function tq_catalog($lang = null)
    {
        static $cache = array();
        $lang = $lang ?: tq_lang();

        if (isset($cache[$lang])) return $cache[$lang];

        /* العربية هي لغة المصدر: مفاتيحها قيمها، فلا ملف ولا بحث. */
        if ($lang === 'arabic') return $cache[$lang] = array();

        $dir = APPPATH . 'language/tq/' . $lang;
        $out = array();
        if (is_dir($dir)) {
            $files = glob($dir . '/*.php');
            sort($files);
            foreach ($files as $f) {
                $part = include $f;
                if (is_array($part)) {
                    foreach ($part as $k => $v) {
                        if ($v === '' || $v === null) continue;   // فارغ = لم يترجم بعد
                        $out[tq_i18n_key($k)] = $v;
                    }
                }
            }
        }
        return $cache[$lang] = $out;
    }
}

if (!function_exists('t')) {
    /**
     * الترجمة — والنص العربي هو المفتاح.
     *
     * `t('احفظ')` يرد «احفظ» بالعربية و«Save» بالإنجليزية. وما لا مدخل له في
     * القاموس يرد **كما كتب** — فصفحة بلا ترجمة تعرض ما كانت تعرضه، ولا تعرض
     * مفتاحا أعرج ولا فراغا.
     *
     * والبدائل: `t('لديك ____ درسا', array(5))` — والعلامة `____` هي نفسها
     * التي يعرفها `get_phrase_()` الموروث، فلا اصطلاح ثان يتعلم. ويقبل
     * `%s`/`%d` كذلك لمن كتب `sprintf` من قبل.
     *
     * @param string $ar   النص العربي — مفتاح ونص افتراضي معا
     * @param mixed  $args بديل واحد أو قائمة بدائل
     */
    function t($ar, $args = null)
    {
        $out = tq_translate($ar);

        if ($args === null || $args === array()) return $out;
        if (!is_array($args)) $args = array($args);

        /* `____` أولا — واحدا واحدا بالترتيب. */
        if (strpos($out, '____') !== false) {
            foreach ($args as $a) {
                $pos = strpos($out, '____');
                if ($pos === false) break;
                $out = substr_replace($out, (string) $a, $pos, 4);
            }
            return $out;
        }

        /* ثم `%s` — و`@vsprintf` لأن عدد بدائل خاطئا في ترجمة لا يجوز أن
           يرمي تحذيرا في وجه المستخدم. */
        if (preg_match('/%[sdfu\d]/', $out)) {
            $try = @vsprintf($out, $args);
            if ($try !== false) return $try;
        }
        return $out;
    }
}

if (!function_exists('tq_translate')) {
    /** البحث الخام بلا بدائل — يستعمله `t()` وكل من يترجم نصا جاهزا. */
    function tq_translate($ar, $lang = null)
    {
        $ar   = (string) $ar;
        $lang = $lang ?: tq_lang();
        if ($lang === 'arabic' || $ar === '') return $ar;

        $cat = tq_catalog($lang);
        $key = tq_i18n_key($ar);
        if (isset($cat[$key])) {
            /* المسافات البادئة والأسطر التي طويت في المفتاح تعاد كما كانت
               حول الترجمة — فالوسم يبقى منسقا كما كتبه صاحبه. */
            return tq_i18n_reindent($ar, $cat[$key]);
        }
        return $ar;
    }
}

if (!function_exists('tq_i18n_reindent')) {
    /** يلبس الترجمة مسافات الأصل البادئة واللاحقة. */
    function tq_i18n_reindent($src, $translated)
    {
        preg_match('/^(\s*)/u', $src, $a);
        preg_match('/(\s*)$/u', $src, $b);
        return $a[1] . $translated . $b[1];
    }
}

if (!function_exists('te')) {
    /** `t()` مهربا — للطباعة داخل الوسم. النص من القاموس لا من مستخدم، ولكن
        قاعدة التهريب عند العرض لا تستثنى لمصدر يظن أمينا. */
    function te($ar, $args = null) { return html_escape(t($ar, $args)); }
}

if (!function_exists('tq_n')) {
    /**
     * المفرد والمثنى والجمع.
     *
     * العربية ست صور والإنجليزية اثنتان، وسلسلة `if` في كل موضع تعد تنتهي
     * إلى «1 دروس» و«2 درس». والقاعدة هنا مصدر واحد: العربية تفرق بين
     * الصفر والواحد والاثنين والقلة (٣–١٠) والكثرة، والإنجليزية بين الواحد
     * وما سواه.
     *
     * @param int   $n
     * @param array $forms العربية: [صفر, مفرد, مثنى, قلة, كثرة]
     *                     الإنجليزية تقرأ من القاموس بمفتاح `$forms[1]`
     */
    function tq_n($n, $forms)
    {
        $n = (int) $n;
        if (tq_lang() !== 'arabic') {
            /* الإنجليزية: المفرد مفتاح، والجمع مدخل ثان بلاحقة `|plural`. */
            $one  = tq_translate($forms[1]);
            $many = tq_translate($forms[1] . '|plural');
            if ($many === $forms[1] . '|plural') $many = $one; // لم يترجم الجمع
            return $n === 1 ? $one : $many;
        }
        $n100 = $n % 100;
        if ($n === 0)                      return $forms[0] ?? $forms[4];
        if ($n === 1)                      return $forms[1];
        if ($n === 2)                      return $forms[2] ?? $forms[1];
        if ($n100 >= 3 && $n100 <= 10)     return $forms[3] ?? $forms[4];
        return $forms[4];
    }
}

/* ====================================================================
   الأرقام والتواريخ
   ==================================================================== */

if (!function_exists('tq_num')) {
    /**
     * رقم بفواصل الموضع.
     *
     * والأرقام تبقى **هندية-عربية غربية** (0-9) في اللغتين: القاعدة تخزنها
     * كذلك، والمنصة تكتبها كذلك في كل شاشة عربية اليوم — وقلبها إلى ٠١٢
     * للعربية وحدها يجعل رقما يقرؤه المسؤول في اللوحة غير الرقم الذي يبحث
     * به في القاعدة.
     */
    function tq_num($n, $decimals = 0)
    {
        return number_format((float) $n, (int) $decimals, '.', ',');
    }
}

if (!function_exists('tq_date_fmt')) {
    /**
     * تاريخ مقروء بلغة الصفحة.
     *
     * `date()` تطبع أسماء الشهور بالإنجليزية دائما، وشاشة عربية تكتب
     * «15 August» وسط نصها. فالأسماء من جدول هنا لا من `strftime` — وهي
     * مهملة في PHP 8.1 ومحذوفة فيما بعده، وتعتمد على locale مثبت على
     * الخادم قد لا يكون.
     */
    function tq_date_fmt($ts, $with_time = false)
    {
        $ts = (int) $ts;
        if ($ts <= 0) return '—';

        $months_ar = array('يناير','فبراير','مارس','أبريل','مايو','يونيو',
                           'يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر');
        $months_en = array('January','February','March','April','May','June',
                           'July','August','September','October','November','December');

        $m = (int) date('n', $ts) - 1;
        $d = (int) date('j', $ts);
        $y = date('Y', $ts);

        if (tq_lang() === 'arabic') {
            $out = $d . ' ' . $months_ar[$m] . ' ' . $y;
        } else {
            $out = $months_en[$m] . ' ' . $d . ', ' . $y;
        }
        if ($with_time) $out .= ' — ' . date('H:i', $ts);
        return $out;
    }
}

/* ====================================================================
   الجسر إلى الجافاسكربت
   ==================================================================== */

if (!function_exists('tq_i18n_js')) {
    /**
     * قاموس المتصفح.
     *
     * السكربتات تحمل نصوصها العربية مكتوبة فيها («تعذر الحفظ»، «هل أنت
     * متأكد؟»)، ونسخة ثانية من القاموس في ملف `.js` تفترق عن أختها عند أول
     * تعديل. فالقاموس واحد، ويطبع هنا في وسم الصفحة، و`TQ.t()` في
     * `taqdar.js` تقرؤه — وما لا مدخل له يرد كما كتب كما في PHP بالضبط.
     *
     * ولا يطبع القاموس كله: مفاتيح `js/*.php` وحدها.
     */
    function tq_i18n_js()
    {
        $lang = tq_lang();
        $map  = array();

        if ($lang !== 'arabic') {
            $f = APPPATH . 'language/tq/' . $lang . '/js.php';
            if (is_file($f)) {
                $part = include $f;
                if (is_array($part)) {
                    foreach ($part as $k => $v) {
                        if ($v === '' || $v === null) continue;
                        $map[tq_i18n_key($k)] = $v;
                    }
                }
            }
        }

        return '<script>window.TQ_I18N=' . json_encode(array(
            'lang' => $lang,
            'dir'  => tq_dir($lang),
            'iso'  => tq_iso($lang),
            'map'  => (object) $map,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
    }
}
