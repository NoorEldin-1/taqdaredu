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
        if (in_array($c, array('t','te','tq_translate','get_phrase','site_phrase','api_phrase'), true)) return 'already';
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
                    $segs[] = array(
                        'kind'    => 'text',
                        'text'    => $inner,
                        'start'   => $start,
                        'len'     => strlen($inner),
                        /* متقطع = كتلة PHP **داخل** النص نفسه، لا قبله ولا بعده. */
                        'dynamic' => strpos($innerMk, "\x01") !== false,
                    );
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
const TQ_ATTR_RE = '/\b(title|placeholder|alt|aria-label|aria-description|aria-placeholder|aria-roledescription|data-tq-confirm|data-tq-label|data-tq-empty|data-confirm)\s*=\s*"([^"]*)"/u';

/* ======================================================================
   ٣ — مجموعات الملفات
   ====================================================================== */

function tq_i18n_targets()
{
    return array(
        'portal' => array('wrap' => true, 'glob' => array(
            'application/views/frontend/taqdar/tq_*.php',
            'application/views/frontend/taqdar/portal_*.php',
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
        'helpers'     => array('wrap' => false, 'glob' => array('application/helpers/taqdar_*.php')),
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
            $out[$f] = true;
        }
    }
    ksort($out);
    return array_keys($out);
}
