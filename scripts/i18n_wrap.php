<?php
/**
 * TQ-I18N — يلف النص العربي الظاهر في القوالب بدالة الترجمة.
 *
 * خمسة آلاف نص في مئتي قالب: اليد لا تبلغها، ومن بلغها نسي سطرا فبقيت
 * شاشة عربية وسط لوحة إنجليزية بلا خطأ يظهر. فالتعديل آلي، وقواعده هي
 * قواعد الجرد نفسها في [i18n_lib.php](i18n_lib.php).
 *
 * **وهو مأمون بالبناء**: `t()` مفتاحها النص العربي نفسه وترد ما لم تعرفه
 * كما جاء — فقالب لف نصه ولا ترجمة له في القاموس يعرض ما كان يعرضه حرفا
 * بحرف. فلا يشترط أن يسبق التعديلَ قاموسٌ كامل.
 *
 *     php scripts/i18n_wrap.php                 # عرض ما سيتغير، بلا كتابة
 *     php scripts/i18n_wrap.php --apply
 *     php scripts/i18n_wrap.php --apply --only=application/views/backend/admin/tqa_list.php
 *
 * ويترك وحده:
 *   - النص المتقطع بكتلة PHP (`dynamic`) — ترجمته نصفين تفسد ترتيب الجملة.
 *   - كل ما يرده `tq_php_reject()`: مفتاح مصفوفة، طرف مقارنة، استعلام، رمز.
 *   - ما لف من قبل — فالتشغيل مرتين لا يغير شيئا في الثانية.
 */

require __DIR__ . '/i18n_lib.php';
chdir(dirname(__DIR__));

$apply = in_array('--apply', $argv, true);
$only  = null;
foreach ($argv as $a) if (strpos($a, '--only=') === 0) $only = substr($a, 7);

$totals = array('files' => 0, 'text' => 0, 'attr' => 0, 'php' => 0, 'skip' => 0);

foreach (tq_i18n_targets() as $gname => $group) {
    if (empty($group['wrap'])) continue;

    foreach (tq_i18n_files($group) as $file) {
        if ($only !== null && $file !== strtr($only, '\\', '/')) continue;

        $src = file_get_contents($file);
        if (!tq_has_arabic($src)) continue;

        $edits = array();   // [offset, length, replacement]

        /* ---- ١) نصوص الوسم وسماته ---- */
        foreach (tq_segment_view($src) as $seg) {
            if ($seg['dynamic']) { $totals['skip']++; continue; }
            $k = tq_key($seg['text']);
            if ($k === '' || !tq_has_arabic($k)) continue;

            if ($seg['kind'] === 'attr') {
                /* السمة تهرب: قيمتها تدخل بين علامتي اقتباس في وسم. */
                $edits[] = array($seg['start'], $seg['len'], '<?php echo te(' . tq_php_quote($k) . '); ?>');
                $totals['attr']++;
            } else {
                /* نص المتن **لا يهرب**: ما كان معروضا خاما يبقى خاما.
                   والقالب يكتب `&laquo;` و`&mdash;` وكيانات أخرى داخل نصه،
                   وتهريبها يطبع `&amp;laquo;` حرفا على الشاشة. */
                $edits[] = array($seg['start'], $seg['len'], '<?php echo t(' . tq_php_quote($k) . '); ?>');
                $totals['text']++;
            }
        }

        /* ---- ٢) سلاسل PHP داخل القالب ---- */
        $toks = token_get_all($src);
        $off  = 0;
        $offsets = array();
        foreach ($toks as $i => $tok) {
            $len = strlen(is_array($tok) ? $tok[1] : $tok);
            $offsets[$i] = $off;
            $off += $len;
        }
        foreach ($toks as $i => $tok) {
            if (!is_array($tok) || $tok[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
            if (!tq_has_arabic($tok[1])) continue;
            if (tq_php_reject($toks, $i) !== null) { $totals['skip']++; continue; }

            $val = tq_tok_value($tok);
            $k   = tq_key($val);
            if ($k === '') continue;

            /* الأصل يبقى مكانه ويلف: `'احفظ'` تصير `t('احفظ')`. والمفتاح هو
               النص **مسوى** لا كما كتب — والمسافات البادئة تعاد في
               `tq_translate()`، فلا يتغير المطبوع. */
            $edits[] = array($offsets[$i], strlen($tok[1]), 't(' . tq_php_quote($k) . ')');
            $totals['php']++;
        }

        if (!$edits) continue;
        $totals['files']++;

        /* التطبيق من آخر الملف إلى أوله — وإلا أزاحت كل قصة ما بعدها. */
        usort($edits, function ($a, $b) { return $b[0] <=> $a[0]; });
        $out = $src;
        $prev = PHP_INT_MAX;
        foreach ($edits as $e) {
            if ($e[0] + $e[1] > $prev) continue;   // تداخل — يترك
            $out = substr_replace($out, $e[2], $e[0], $e[1]);
            $prev = $e[0];
        }

        /* لا يكتب ملفا لا يعبر الفاحص: قالب مكسور أسوأ من قالب عربي. */
        $err = tq_php_lint($out);
        if ($err !== null) {
            fwrite(STDERR, "!! SKIPPED (syntax) $file — $err\n");
            continue;
        }

        if ($apply) {
            file_put_contents($file, $out);
        } else {
            printf("%-62s %4d edits\n", $file, count($edits));
        }
    }
}

printf("\nfiles=%d  text=%d  attr=%d  php=%d  left-alone=%d  %s\n",
    $totals['files'], $totals['text'], $totals['attr'], $totals['php'], $totals['skip'],
    $apply ? 'APPLIED' : '(dry run — pass --apply)');

/* ---------------------------------------------------------------- */

/** يقتبس نصا لـPHP بعلامة مفردة. */
function tq_php_quote($s)
{
    return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $s) . "'";
}

/** يفحص الشيفرة الناتجة — يرد رسالة الخطأ أو null. */
function tq_php_lint($code)
{
    $tmp = tempnam(sys_get_temp_dir(), 'tqi18n');
    file_put_contents($tmp, $code);
    $out = array(); $rc = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
    unlink($tmp);
    return $rc === 0 ? null : trim(implode(' ', $out));
}
