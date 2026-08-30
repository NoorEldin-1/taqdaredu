<?php
/**
 * TQ-I18N — مقطع الشيفرة: يقول أين يقع كل نص ظاهر بالضبط.
 *
 * ملف واحد يقرؤه **الجرد** (`i18n_extract.php`) و**التعديل الآلي**
 * (`i18n_wrap.php`) و**الفحص** (`i18n_lint.php`). ونسختان من قواعد
 * «ما النص الظاهر؟» تفترقان عند أول تعديل: يجرد ما لا يعدل، أو أسوأ —
 * يعدل ما لم يجرد فيدخل القالب مفتاح لا ترجمة له.
 *
 * والقاعدة المركزية: **الموضع لا الحروف.** النص نفسه يترجم في وسم ويكسر
 * الشيفرة في شرط، ويعرض في تسمية ويخزن في عمود.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only.\n"); }

function tq_has_arabic($s) { return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', (string) $s); }

/** تسوية المفتاح — تطابق `tq_i18n_key()` في المساعد حرفا بحرف. */
function tq_key($s)
{
    $s = preg_replace('/\x{00A0}/u', ' ', (string) $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/* ======================================================================
   ١ — سلاسل PHP
   ====================================================================== */

/**
 * أسباب رفض سلسلة عربية.
 *
 * @return string|null  سبب الرفض، أو null إن كانت تترجم
 */
function tq_php_reject($toks, $i)
{
    $val = tq_tok_value($toks[$i]);

    /* استعلام SQL — العربية فيه تعليق عمود أو ثابت داخل `CONCAT`، وترجمته
       تكتب اسم عمود بالإنجليزية في `CREATE TABLE` أو تكسر الاستعلام. */
    if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|REPLACE)\b/i', $val)) return 'sql';
    if (substr_count($val, '`') >= 2) return 'sql';

    /* رمز أو مسار أو وسم لا نص. */
    if (preg_match('#^[a-z0-9_./-]+$#i', $val)) return 'slug';

    /* سطر سجل — يكتب في `log_message()` ويقرؤه من يفتح سجل الخادم، وهو
       مبرمج لا مستخدم. وترجمته تضخم القاموس بما لا يعرض في شاشة، وتجعل
       البحث في السجل عن نص عربي يقع على نصف السطور. والعلامة صريحة:
       بادئة الوسم في أول النص. */
    if (preg_match('/^(TQ-[A-Z][A-Z-]*|tq_mail|tq_wa|TQ)[:.]/', $val)) return 'log';

    /* شذرة مخطط: تعليق عمود أو نوعه. تكتب في `CREATE TABLE`/`ALTER`، وهي
       شيفرة لا نص — وترجمتها تكتب اسم نوع بالإنجليزية داخل تعليق عربي،
       أو تكسر الاستعلام. */
    if (strpos($val, "COMMENT '") !== false || strpos($val, 'COMMENT "') !== false) return 'schema';
    if (preg_match('/^(datetime|int\(|bigint|varchar|text|decimal|tinyint)\b/i', $val)) return 'schema';

    $prev = tq_tok_near($toks, $i, -1);
    $next = tq_tok_near($toks, $i, +1);
    $ptxt = tq_tok_value($prev, true);
    $ntxt = tq_tok_value($next, true);

    /* مفتاح مصفوفة — قد يخزن في القاعدة. */
    if ($ntxt === '=>') return 'array-key';

    /* فهرسة `$a['نص']`. */
    if ($ptxt === '[' && $ntxt === ']') return 'array-index';

    /* طرف مقارنة — الترجمة تجعل الشرط لا يصدق أبدا. */
    $cmp = array('==','===','!=','!==','<>','<=>');
    if (in_array($ptxt, $cmp, true) || in_array($ntxt, $cmp, true)) return 'comparison';

    /* حالة `switch` — مقارنة بوجه آخر. */
    if (is_array($prev) && $prev[0] === T_CASE) return 'comparison';

    /* تعبير ثابت: `static $a = [...]` و`const` وقيمة معامل افتراضية وخاصية
       صنف. PHP لا يقبل نداء دالة في أي منها — والقالب الملفوف لا يفتح
       أصلا: «Constant expression contains invalid operations». */
    if (tq_in_const_expr($toks, $i)) return 'const-expr';

    $call = tq_enclosing_call($toks, $i);
    if ($call !== null) {
        $c = strtolower($call);
        /* ملفوف بقاموس الملفات — مفتاحه يجب أن يكون في `application/language/tq`. */
        if (in_array($c, array('t','te','tq_translate'), true)) return 'already';

        /* ملفوف بالقاموس الموروث — مفتاحه صف في جدول `language`، لا مدخل في
           ملف. وخلط المصدرين يجعل الفاحص يطلب ترجمة ملف لنص يترجم من
           القاعدة، فلا يسكت عداد «ينتظر ترجمة» أبدا. */
        if (in_array($c, array('get_phrase','site_phrase','api_phrase'), true)) return 'db-phrase';
        if (in_array($c, array('where','where_in','or_where','like','set','order_by','having','group_by',
                               'in_array','array_search','array_key_exists','strpos','str_contains',
                               'preg_match','preg_replace','preg_split','explode','query'), true)) return 'call:' . $c;
    }
    return null;
}

function tq_tok_value($tok, $raw = false)
{
    if ($tok === null) return null;
    $x = is_array($tok) ? $tok[1] : $tok;
    if ($raw) return $x;
    if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
        $q = $x[0];
        $v = substr($x, 1, -1);
        return $q === "'"
            ? str_replace(array("\\'", '\\\\'), array("'", '\\'), $v)
            : stripcslashes($v);
    }
    return $x;
}

function tq_tok_near($toks, $i, $dir)
{
    $n = count($toks);
    for ($j = $i + $dir; $j >= 0 && $j < $n; $j += $dir) {
        $t = $toks[$j];
        if (is_array($t) && in_array($t[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) continue;
        return $t;
    }
    return null;
}

/**
 * هل الرمز داخل تعبير ثابت لا يقبل نداء دالة؟
 *
 * يمشي إلى بداية الجملة، فإن بدأت بـ`static` أو `const` أو محدد رؤية فهي
 * تهيئة ثابتة. ويفحص كذلك قائمة معاملات دالة — القيمة الافتراضية فيها
 * تعبير ثابت مثلها.
 */
function tq_in_const_expr($toks, $i)
{
    $depth = 0;
    for ($j = $i - 1; $j >= 0; $j--) {
        $t = $toks[$j];
        $x = is_array($t) ? $t[1] : $t;

        if ($x === ')' || $x === ']') { $depth++; continue; }
        if ($x === '[') { if ($depth > 0) $depth--; continue; }
        if ($x === '(') {
            if ($depth > 0) { $depth--; continue; }
            /* قوس فاتح بلا اسم دالة قبله = قائمة معاملات أو تجميع. */
            for ($k = $j - 1; $k >= 0; $k--) {
                $u = $toks[$k];
                if (is_array($u) && $u[0] === T_WHITESPACE) continue;
                if (is_array($u) && in_array($u[0], array(T_FUNCTION, T_FN), true)) return true;
                if (is_array($u) && $u[0] === T_STRING) {
                    /* اسم دالة — قد يكون تعريفا: `function f('..')`. */
                    for ($z = $k - 1; $z >= 0; $z--) {
                        $w = $toks[$z];
                        if (is_array($w) && $w[0] === T_WHITESPACE) continue;
                        return (is_array($w) && $w[0] === T_FUNCTION);
                    }
                }
                break;
            }
            return false;
        }
        if ($x === ';' || $x === '{' || $x === '}') return false;

        if (is_array($t) && in_array($t[0], array(T_STATIC, T_CONST, T_PUBLIC, T_PRIVATE, T_PROTECTED, T_VAR), true)) {
            return true;
        }
    }
    return false;
}

/** اسم الدالة التي يقع الرمز داخل قوسيها مباشرة — أو null. */
function tq_enclosing_call($toks, $i)
{
    $depth = 0;
    for ($j = $i - 1; $j >= 0; $j--) {
        $x = is_array($toks[$j]) ? $toks[$j][1] : $toks[$j];
        if ($x === ')' || $x === ']') { $depth++; continue; }
        if ($x === '[') { if ($depth === 0) return null; $depth--; continue; }
        if ($x === '(') {
            if ($depth > 0) { $depth--; continue; }
            for ($k = $j - 1; $k >= 0; $k--) {
                $u = $toks[$k];
                if (is_array($u) && $u[0] === T_WHITESPACE) continue;
                if (is_array($u) && $u[0] === T_STRING) return $u[1];
                return null;
            }
            return null;
        }
        if ($x === ';' || $x === '{' || $x === '}') return null;
    }
    return null;
}

/* ======================================================================
   ٢ — نصوص الوسم
   ====================================================================== */

/**
 * يقطع ملفا إلى شرائح، ويسم كل شريحة بما هي.
 *
 * والقطع على **الملف كله** لا على رمز `T_INLINE_HTML` واحد: النص الواحد
 * ينقسم رمزين متى تخلله وسم PHP —
 *
 *     <p>لديك <?php echo $n; ?> درسا</p>
 *
 * فمن يعالج الرموز واحدا واحدا يخرج بمفتاحين «لديك» و«درسا»، وترجمتهما
 * منفصلتين تطبع «You have 5 lesson» بترتيب لا يصلح في لغة أخرى أصلا.
 * فالنص المتقطع يعلم `dynamic` ويترك لليد، والباقي يقص نظيفا.
 *
 * @return array  شرائح: ['kind'=>text|attr|php|tag, 'text'=>..., 'start'=>, 'len'=>, 'dynamic'=>bool]
 */
/**
 * أفي هذه الشريحة عربية **خارج** كتل PHP؟
 *
 * وبلا هذا السؤال يقرأ المقطع نصا ملفوفا من قبل نصا خاما: النص المترجم صار
 * `<?php echo t('احفظ'); ?>` وهو كتلة PHP كلها، فالمقنع `\x01` من طرفه إلى
 * طرفه — ولكن الأصل الذي يقرأ منه لا يزال يحمل «احفظ». فيعده «نصا متقطعا»
 * ينتظر يدا، ويرتفع عداد ما ينتظر بعد كل تشغيل بدل أن ينزل.
 */
function tq_visible_arabic($raw, $masked)
{
    $out = '';
    $n = min(strlen($raw), strlen($masked));
    for ($i = 0; $i < $n; $i++) {
        if ($masked[$i] !== "\x01") $out .= $raw[$i];
    }
    return tq_has_arabic($out);
}

/**
 * نص تخللته كتلة PHP: أهو جملة واحدة مقطوعة، أم نص نظيف بجواره كتلة؟
 *
 * والفرق يقرر مصير أربعمئة وخمسين موضعا. فأكثر ما وقع في هذه الصورة ليس
 * جملة مركبة أصلا —
 *
 *     <?php echo tq_icon('clock', 18); ?> الاختبارات القادمة
 *
 * أيقونة ثم نص تام. وعده «متقطعا» يتركه عربيا في لوحة إنجليزية بلا سبب.
 * وهذه جملة مركبة حقا:
 *
 *     أنجزت <?php echo $done; ?> من <?php echo $target; ?> اليوم
 *
 * والفارق **عدد أجزائها العربية**: واحد فهو نص بجواره كتلة، وأكثر فهي
 * جملة ترتيب أجزائها يختلف بين اللغتين — فتترك لليد بعلامات `____`.
 *
 * @return array شرائح
 */
/**
 * أكل ما بين الأجزاء العربية بنية تحكم؟
 *
 * ينظر إلى الشيفرة الواقعة بين كل جزأين متتاليين في **الأصل** (لا المقنع)،
 * فإن كانت `if`/`else`/`endif`/`foreach` ولا `echo` فيها فالجزءان فرعان
 * لا يجتمعان. وكتلة `echo` واحدة بينهما تكفي لعدهما جملة واحدة.
 */
function tq_runs_are_branches($src, $start, $arabic)
{
    for ($i = 1; $i < count($arabic); $i++) {
        $from = $start + $arabic[$i - 1][0] + $arabic[$i - 1][1];
        $to   = $start + $arabic[$i][0];
        $between = substr($src, $from, $to - $from);

        if (preg_match('/<\?(?:php\s+|=)\s*echo\b/', $between)) return false;
        if (!preg_match('/<\?php\s*(?:\}|if|else|elseif|endif|foreach|endforeach|for|endfor|while|endwhile|switch|case|endswitch)\b/', $between)) return false;
    }
    return true;
}

function tq_split_runs($src, $maskedInner, $start)
{
    /* أجزاء النص بين كتل PHP، بإزاحة كل جزء. */
    $runs = array();
    $n = strlen($maskedInner);
    $i = 0;
    while ($i < $n) {
        if ($maskedInner[$i] === "\x01") { $i++; continue; }
        $j = $i;
        while ($j < $n && $maskedInner[$j] !== "\x01") $j++;
        $runs[] = array($i, $j - $i);
        $i = $j;
    }

    $arabic = array();
    foreach ($runs as $r) {
        $txt = substr($src, $start + $r[0], $r[1]);
        if (tq_has_arabic($txt)) $arabic[] = array($r[0], $r[1], $txt);
    }

    /* الفاصل بين جزأين: أهو **قيمة** تحقن في الجملة، أم **بنية تحكم**
       تفصل جملتين لا تجمعهما؟

           <?php if ($x): ?> صدرت فاتورتك <?php else: ?> اشترك من جديد <?php endif; ?>

       جملتان مستقلتان، لا يقرأ القارئ منهما إلا واحدة — وربطهما مفتاحا
       واحدا بـ`____` يعطي مفتاحا لا يقع أبدا في شاشة. والفارق أن الكتلة
       بينهما `if`/`else`/`endif` لا `echo`. */
    if (count($arabic) > 1 && tq_runs_are_branches($src, $start, $arabic)) {
        $out = array();
        foreach ($arabic as $a) {
            preg_match('/^(\s*)(.*?)(\s*)$/su', $a[2], $mm);
            if ($mm[2] === '' || !tq_has_arabic($mm[2])) continue;
            $out[] = array(
                'kind'    => 'text',
                'text'    => $mm[2],
                'start'   => $start + $a[0] + strlen($mm[1]),
                'len'     => strlen($mm[2]),
                'dynamic' => false,
            );
        }
        if ($out) return $out;
    }

    /* جزءان عربيان فأكثر = جملة مركبة، تترك كما هي. */
    if (count($arabic) !== 1) {
        return array(array(
            'kind' => 'text', 'text' => substr($src, $start, strlen($maskedInner)),
            'start' => $start, 'len' => strlen($maskedInner), 'dynamic' => true,
        ));
    }

    list($off, $len, $txt) = $arabic[0];
    preg_match('/^(\s*)(.*?)(\s*)$/su', $txt, $mm);
    if ($mm[2] === '' || !tq_has_arabic($mm[2])) {
        return array();
    }
    return array(array(
        'kind'    => 'text',
        'text'    => $mm[2],
        'start'   => $start + $off + strlen($mm[1]),
        'len'     => strlen($mm[2]),
        'dynamic' => false,
    ));
}

function tq_segment_view($src)
{
    /* ---- ١) التقنيع ----
       كل كتلة PHP تستبدل بحرف حارس بطولها نفسه (`\x01`)، فالإزاحات تبقى
       صحيحة والوسم يقرأ نظيفا. وبلا التقنيع يخدع البحث عن `>` أول سمة فيها
       كتلة PHP —

           <a href="<?php echo $u; ?>">تفاصيل</a>

       فيقرأ المقطع `">تفاصيل` نصا واحدا يبدأ بعقب وسم، ويعلمه «متقطعا»
       لأن ما قبله `?>`. فيسقط من الترجمة نص **نظيف تماما** — وهو أكثر
       نصوص الشجرة: كل رابط له وجهة محسوبة، وهي كلها كذلك. */
    $mask = preg_replace_callback('/<\?(?:php|=)?.*?(?:\?>|$)/s',
        function ($m) { return str_repeat("\x01", strlen($m[0])); }, $src);

    /* السكربت والنمط والتعليق: نص فيها لا يقرؤه أحد. */
    foreach (array('/<(script|style)\b[^>]*>.*?<\/\1>/is', '/<!--.*?-->/s') as $re) {
        $mask = preg_replace_callback($re,
            function ($m) { return str_repeat("\x02", strlen($m[0])); }, $mask);
    }

    $segs = array();
    $len  = strlen($mask);

    /* ---- ٢) سمات تعرض للمستخدم ---- */
    if (preg_match_all(TQ_ATTR_RE, $mask, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[2] as $k => $x) {
            list($masked, $off) = $x;
            $val = substr($src, $off, strlen($masked));
            if (!tq_has_arabic($val)) continue;
            if (strpos($masked, "\x02") !== false) continue;
            if (!tq_visible_arabic($val, $masked)) continue;
            $segs[] = array(
                'kind'    => 'attr',
                'text'    => $val,
                'start'   => $off,
                'len'     => strlen($val),
                'dynamic' => strpos($masked, "\x01") !== false,
                'attr'    => $m[1][$k][0],
            );
        }
    }

    /* ---- ٣) نصوص بين الوسوم ---- */
    $pos = 0;
    while ($pos < $len) {
        $lt = strpos($mask, '<', $pos);
        $end = ($lt === false) ? $len : $lt;

        if ($end > $pos) {
            $masked = substr($mask, $pos, $end - $pos);
            $raw    = substr($src,  $pos, $end - $pos);

            if (tq_has_arabic($raw) && strpos($masked, "\x02") === false
                && tq_visible_arabic($raw, $masked)) {
                /* المسافات البادئة واللاحقة تبقى مكانها. */
                preg_match('/^(\s*)(.*?)(\s*)$/su', $raw, $mm);
                $inner = $mm[2];
                if ($inner !== '' && tq_has_arabic($inner)) {
                    $start   = $pos + strlen($mm[1]);
                    $innerMk = substr($mask, $start, strlen($inner));

                    if (strpos($innerMk, "\x01") === false) {
                        $segs[] = array('kind' => 'text', 'text' => $inner,
                                        'start' => $start, 'len' => strlen($inner), 'dynamic' => false);
                    } else {
                        foreach (tq_split_runs($src, $innerMk, $start) as $r) $segs[] = $r;
                    }
                }
            }
        }

        if ($lt === false) break;
        $gt = strpos($mask, '>', $lt);
        $pos = ($gt === false) ? $len : $gt + 1;
    }

    usort($segs, function ($a, $b) { return $a['start'] <=> $b['start']; });
    return $segs;
}

/**
 * السمات التي تعرض نصا للمستخدم.
 *
 * و`data-tq-confirm` منها: هي نص نافذة «هل أنت متأكد؟» يقرؤه السكربت —
 * وتركها يترك أخطر جملة في الشاشة عربية وحدها، وهي التي تسبق حذفا لا يرجع.
 */
const TQ_ATTR_RE = '/\b(title|placeholder|alt|aria-label|aria-description|aria-placeholder|aria-roledescription|data-tq-confirm|data-tq-confirm-note|data-tq-confirm-ok|data-tq-confirm-title|data-tqa-confirm|data-tqa-confirm-note|data-tqa-confirm-ok|data-tqa-confirm-title|data-tq-label|data-tq-empty|data-confirm)\s*=\s*"([^"]*)"/u';

/* ======================================================================
   ٣ — مجموعات الملفات
   ====================================================================== */

function tq_i18n_targets()
{
    return array(
        'portal' => array('wrap' => true, 'glob' => array(
            'application/views/frontend/taqdar/tq_*.php',
            'application/views/frontend/taqdar/portal_*.php',
            /* غلاف يعرض **داخل** كل صفحة بوابة وإن لم يبدأ اسمه بـ`tq_`:
               شريط الارتباط يطبع في كل شاشة (اثنتا عشرة عبارة)، و`seo` تكتب
               عنوان التبويب ووصفه. وقصر النطاق على الاصطلاح وحده ترك أظهر
               نص في اللوحة عربيا — وهو أول ما يقرؤه الزائر. */
            'application/views/frontend/taqdar/eu-cookie.php',
            'application/views/frontend/taqdar/seo.php',
            'application/views/frontend/taqdar/includes_top.php',
            'application/views/frontend/taqdar/includes_bottom.php',
            'application/views/frontend/taqdar/metas.php',
            /* والغلاف نفسه: فيه رابط «تخط إلى المحتوى» وهو أول ما يقرؤه
               من يتنقل بالمفاتيح. */
            'application/views/frontend/taqdar/index.php',
        )),
        'admin' => array('wrap' => true, 'glob' => array(
            'application/views/backend/*.php',
            'application/views/backend/admin/*.php',
        )),
        'components' => array('wrap' => true, 'glob' => array(
            'application/views/components/*.php',
            'application/views/components/*/*.php',
        )),
        'player' => array('wrap' => true, 'glob' => array(
            'application/views/lessons/*.php',
        )),
        /* الطبقة تحت القوالب: تجرد ولا تعدل — رسائلها تترجم عند العرض
           (نقاط الاختناق)، ومفتاح القاموس هو نصها كما كتب. */
        'models'      => array('wrap' => false, 'glob' => array('application/models/Taqdar_*.php')),
        'controllers' => array('wrap' => false, 'glob' => array(
            'application/controllers/Taqdar*.php',
            'application/controllers/Login.php',
            'application/controllers/Admin.php',
            'application/controllers/User.php',
        )),
        /* المساعدات **تعدل** لا تجرد وحسب: أكثرها يبني وسما ويرده جاهزا
           (`tq_spam_notice` · `tqs_bundles` · `tq_cur_fields`)، فنصه لا يمر
           بقالب ولا برسالة طائرة — ولا شيء يترجمه إن لم يلف في موضعه.
           وكانت في المجموعة غير المعدلة على أنها «طبقة تحت القوالب»، فبقي
           تنبيه «الرسائل غير المرغوبة» كله عربيا في لوحة إنجليزية. */
        'helpers'     => array('wrap' => true, 'glob' => array('application/helpers/taqdar_*.php')),
    );
}

function tq_i18n_files($group)
{
    $out = array();
    foreach ($group['glob'] as $g) {
        foreach (glob($g) as $f) {
            $f = strtr($f, '\\', '/');
            if (preg_match('/\.(orig|bak|pre)[-.]/', $f)) continue;
            if (basename($f) === 'index.html') continue;

            /* المترجم نفسه لا يترجم.
               `tq_languages()` تحمل أسماء اللغات، ولفها يجعل `t()` تنادي
               `tq_lang()` تنادي `tq_languages()` تنادي `t()` — تكرارا لا
               نهاية له تنفد معه الذاكرة قبل أن تخرج صفحة واحدة. وأسماء
               الشهور فيه مصفوفتان تختاران باللغة أصلا، فلفها يخرج
               «January» من فرع العربية. */
            if (basename($f) === 'taqdar_i18n_helper.php') continue;

            $out[$f] = true;
        }
    }
    ksort($out);
    return array_keys($out);
}
