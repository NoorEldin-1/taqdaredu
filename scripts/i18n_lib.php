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
function tq_segment_view($src)
{
    $segs = array();

    /* ١ — مواضع كتل PHP، لتترك كما هي ويعلم ما جاورها. */
    $php = array();
    if (preg_match_all('/<\?(?:php|=)?.*?(?:\?>|$)/s', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $x) $php[] = array($x[1], strlen($x[0]));
    }

    $inPhp = function ($pos) use ($php) {
        foreach ($php as $p) if ($pos >= $p[0] && $pos < $p[0] + $p[1]) return true;
        return false;
    };

    /* ٢ — الوسوم والتعليقات والسكربتات: مناطق محرمة على النص. */
    $blocked = array();
    foreach (array('/<(script|style)\b[^>]*>.*?<\/\1>/is', '/<!--.*?-->/s') as $re) {
        if (preg_match_all($re, $src, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $x) $blocked[] = array($x[1], strlen($x[0]));
        }
    }
    $isBlocked = function ($pos) use ($blocked) {
        foreach ($blocked as $b) if ($pos >= $b[0] && $pos < $b[0] + $b[1]) return true;
        return false;
    };

    /* ٣ — سمات تعرض للمستخدم داخل الوسوم. */
    $attrRe = '/\b(title|placeholder|alt|aria-label|aria-description|aria-placeholder|data-tq-label)\s*=\s*"([^"]*)"/u';
    if (preg_match_all($attrRe, $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[2] as $k => $x) {
            list($val, $off) = $x;
            if (!tq_has_arabic($val) || $inPhp($off) || $isBlocked($off)) continue;
            $segs[] = array(
                'kind'    => 'attr',
                'text'    => $val,
                'start'   => $off,
                'len'     => strlen($val),
                'dynamic' => strpos($val, '<?') !== false,
                'attr'    => $m[1][$k][0],
            );
        }
    }

    /* ٤ — نصوص بين الوسوم. */
    $pos = 0;
    $len = strlen($src);
    while ($pos < $len) {
        $lt = strpos($src, '<', $pos);
        if ($lt === false) { $chunk = substr($src, $pos); $chunkAt = $pos; $pos = $len; }
        else { $chunk = substr($src, $pos, $lt - $pos); $chunkAt = $pos; $pos = $lt + 1;
               $gt = strpos($src, '>', $lt); $pos = ($gt === false) ? $len : $gt + 1; }

        if ($chunk === '' || !tq_has_arabic($chunk)) continue;
        if ($inPhp($chunkAt) || $isBlocked($chunkAt)) continue;

        /* هل النص متقطع؟ ينظر إلى ما قبله وما بعده مباشرة. */
        $before = substr($src, max(0, $chunkAt - 2), 2);
        $afterAt = $chunkAt + strlen($chunk);
        $after  = substr($src, $afterAt, 2);
        $dynamic = ($before === '?>') || ($after === '<?');

        /* المسافات البادئة واللاحقة تبقى مكانها، ويقص ما بينهما. */
        preg_match('/^(\s*)(.*?)(\s*)$/su', $chunk, $mm);
        if ($mm[2] === '' || !tq_has_arabic($mm[2])) continue;

        $segs[] = array(
            'kind'    => 'text',
            'text'    => $mm[2],
            'start'   => $chunkAt + strlen($mm[1]),
            'len'     => strlen($mm[2]),
            'dynamic' => $dynamic,
        );
    }

    usort($segs, function ($a, $b) { return $a['start'] <=> $b['start']; });
    return $segs;
}

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
