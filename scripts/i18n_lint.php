<?php
/**
 * TQ-I18N — يقول ما بقي عربيا بلا ترجمة، وأين.
 *
 * الفحص الآلي هو ما يمنع الانحدار: شاشة تضاف غدا بنص مكتوب فيها لا يخطئ
 * ولا يظهر — تعرض عربية وسط لوحة إنجليزية، ولا يلاحظها إلا من يقرأ
 * بالإنجليزية وهو ليس من يكتبها. فيصير للنص الحي عداد يقرأ.
 *
 *     php scripts/i18n_lint.php               # ملخص
 *     php scripts/i18n_lint.php --list        # كل موضع
 *     php scripts/i18n_lint.php --max=0       # يفشل (رمز خروج 1) على أي نص غير ملفوف
 *
 * ويفحص ثلاثة:
 *   ١ نص عربي في قالب لم يلف بـ`t()`.
 *   ٢ مفتاح ملفوف لا مدخل له في القاموس الإنجليزي.
 *   ٣ مدخل في القاموس لا يقابله نص في الشيفرة (ترجمة ميتة).
 */

require __DIR__ . '/i18n_lib.php';
chdir(dirname(__DIR__));

$list = in_array('--list', $argv, true);
$max  = null;
foreach ($argv as $a) if (strpos($a, '--max=') === 0) $max = (int) substr($a, 6);

/* ---- القاموس ---- */
$cat = array();
foreach (glob('application/language/tq/english/*.php') as $f) {
    $part = include $f;
    if (is_array($part)) foreach ($part as $k => $v) $cat[tq_key($k)] = $v;
}

$unwrapped = array();   // نص ظاهر لم يلف
$used      = array();   // مفتاح مستعمل في الشيفرة
$dynamic   = array();

foreach (tq_i18n_targets() as $gname => $group) {
    foreach (tq_i18n_files($group) as $file) {
        $src = file_get_contents($file);
        if (!tq_has_arabic($src)) continue;

        $toks = token_get_all($src);
        foreach ($toks as $i => $tok) {
            if (!is_array($tok) || $tok[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
            if (!tq_has_arabic($tok[1])) continue;

            $k = tq_key(tq_tok_value($tok));
            $reason = tq_php_reject($toks, $i);

            if ($reason === 'already') { $used[$k] = $gname; continue; }
            if ($reason !== null) continue;                    // لا يترجم عمدا

            /* في القوالب: نص لم يلف. وفي الطبقة تحتها: مفتاح ينتظر ترجمة
               تقع عند العرض (نقاط الاختناق)، فهو مستعمل لا مهمل. */
            if (!empty($group['wrap'])) {
                $unwrapped[] = array($file, $tok[2], $k, 'php-string');
            } else {
                $used[$k] = $gname;
            }
        }

        if (empty($group['wrap'])) continue;

        foreach (tq_segment_view($src) as $seg) {
            $k = tq_key($seg['text']);
            if ($k === '' || !tq_has_arabic($k)) continue;
            if ($seg['dynamic']) { $dynamic[] = array($file, $k); continue; }
            $unwrapped[] = array($file, 0, $k, $seg['kind']);
        }
    }
}

/* المفاتيح الملفوفة فعلا — تقرأ من نداءات `t()`/`te()` في كل الشجرة. */
foreach (tq_i18n_targets() as $gname => $group) {
    foreach (tq_i18n_files($group) as $file) {
        $src = file_get_contents($file);
        if (preg_match_all("/\\bte?\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/u", $src, $m)) {
            foreach ($m[1] as $raw) {
                $k = tq_key(str_replace(array("\\'", '\\\\'), array("'", '\\'), $raw));
                if ($k !== '' && tq_has_arabic($k)) $used[$k] = $gname;
            }
        }
    }
}

$missing = array();
foreach ($used as $k => $g) if (!isset($cat[$k]) || $cat[$k] === '' || $cat[$k] === null) $missing[$k] = $g;

$dead = array();
foreach ($cat as $k => $v) if (!isset($used[$k])) $dead[$k] = true;

/* ---- التقرير ---- */
echo "keys used in code      : " . count($used) . "\n";
echo "translated             : " . (count($used) - count($missing)) . "\n";
echo "MISSING translation    : " . count($missing) . "\n";
echo "unwrapped display text : " . count($unwrapped) . "\n";
echo "dynamic (manual)       : " . count($dynamic) . "\n";
echo "dead catalog entries   : " . count($dead) . "\n";

if ($list) {
    echo "\n--- UNWRAPPED ---\n";
    foreach ($unwrapped as $u) printf("%s:%d  [%s]  %s\n", $u[0], $u[1], $u[3], mb_substr($u[2], 0, 90));
    echo "\n--- MISSING TRANSLATION ---\n";
    foreach ($missing as $k => $g) printf("[%s] %s\n", $g, mb_substr($k, 0, 110));
    echo "\n--- DYNAMIC ---\n";
    foreach ($dynamic as $d) printf("%s  %s\n", $d[0], mb_substr($d[1], 0, 90));
}

if ($max !== null && count($unwrapped) > $max) {
    fwrite(STDERR, "\nFAIL: " . count($unwrapped) . " unwrapped strings (max $max)\n");
    exit(1);
}
