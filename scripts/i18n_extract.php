<?php
/**
 * TQ-I18N — جرد كل نص عربي ظاهر في الشيفرة.
 *
 * القاموس مفتاحه النص العربي نفسه (انظر `taqdar_i18n_helper.php`)، فبناؤه
 * يبدأ من هنا. والقواعد كلها في [i18n_lib.php](i18n_lib.php) — يقرؤها هذا
 * والتعديل الآلي والفحص، فلا يجرد أحدها ما لا يعدله الآخر.
 *
 *     php scripts/i18n_extract.php
 *     php scripts/i18n_extract.php --dump=out.json
 *     php scripts/i18n_extract.php --missing        # ما لم يترجم بعد فقط
 */

require __DIR__ . '/i18n_lib.php';
chdir(dirname(__DIR__));

$dump    = null;
$missing = false;
foreach ($argv as $a) {
    if (strpos($a, '--dump=') === 0) $dump = substr($a, 7);
    if ($a === '--missing') $missing = true;
}

/* القاموس القائم — لمعرفة ما ترجم وما بقي. */
$have = array();
foreach (glob('application/language/tq/english/*.php') as $f) {
    $part = include $f;
    if (is_array($part)) foreach ($part as $k => $v) if ($v !== '' && $v !== null) $have[tq_key($k)] = true;
}

$report  = array();
$catalog = array();
$skipped = array();
$dynamic = array();

foreach (tq_i18n_targets() as $gname => $group) {
    $stat = array('files' => 0, 'php' => 0, 'html' => 0, 'attr' => 0, 'dyn' => 0, 'skip' => 0);

    foreach (tq_i18n_files($group) as $file) {
        $src = file_get_contents($file);
        if (!tq_has_arabic($src)) continue;
        $stat['files']++;

        /* ---- سلاسل PHP ---- */
        $toks = token_get_all($src);
        foreach ($toks as $i => $tok) {
            if (!is_array($tok)) continue;
            if ($tok[0] !== T_CONSTANT_ENCAPSED_STRING) {
                /* سلسلة مزدوجة فيها متغير: `"لديك $n درسا"` — تترك لليد. */
                if ($tok[0] === T_ENCAPSED_AND_WHITESPACE && tq_has_arabic($tok[1])) {
                    $dynamic[tq_key($tok[1])] = $file;
                    $stat['dyn']++;
                }
                continue;
            }
            if (!tq_has_arabic($tok[1])) continue;

            $reason = tq_php_reject($toks, $i);
            if ($reason !== null) { $stat['skip']++; $skipped[$reason][$file . ' :: ' . tq_key(tq_tok_value($tok))] = true; continue; }

            $k = tq_key(tq_tok_value($tok));
            if ($k === '') continue;
            $catalog[$k]['ctx'] = $catalog[$k]['ctx'] ?? $gname;
            $catalog[$k]['files'][$file] = true;
            $stat['php']++;

            /* موصول بمتغير: `'لديك ' . $n . ' درسا'` — يعلم. */
            $nx = tq_tok_near($toks, $i, +1);
            $pv = tq_tok_near($toks, $i, -1);
            if ($nx === '.' || $pv === '.') $catalog[$k]['concat'] = true;
        }

        /* ---- نصوص الوسم ---- */
        if (!empty($group['wrap']) || true) {
            foreach (tq_segment_view($src) as $seg) {
                $k = tq_key($seg['text']);
                if ($k === '' || !tq_has_arabic($k)) continue;
                if ($seg['dynamic']) { $dynamic[$k] = $file; $stat['dyn']++; continue; }
                $catalog[$k]['ctx'] = $catalog[$k]['ctx'] ?? $gname;
                $catalog[$k]['files'][$file] = true;
                $stat[$seg['kind'] === 'attr' ? 'attr' : 'html']++;
            }
        }
    }
    $report[$gname] = $stat;
}

/* ---------------- التقرير ---------------- */

printf("%-13s %6s %7s %7s %6s %6s %6s\n", 'GROUP', 'files', 'php', 'html', 'attr', 'dyn', 'skip');
foreach ($report as $g => $s) {
    printf("%-13s %6d %7d %7d %6d %6d %6d\n", $g, $s['files'], $s['php'], $s['html'], $s['attr'], $s['dyn'], $s['skip']);
}

$todo = array();
foreach ($catalog as $k => $v) if (!isset($have[$k])) $todo[$k] = $v;

echo "\nUNIQUE keys: " . count($catalog) . "   translated: " . (count($catalog) - count($todo))
   . "   remaining: " . count($todo) . "\n";

$byctx = array();
foreach ($todo as $k => $v) $byctx[$v['ctx']] = ($byctx[$v['ctx']] ?? 0) + 1;
arsort($byctx);
foreach ($byctx as $c => $n) printf("  %-13s %6d remaining\n", $c, $n);

echo "\nSKIPPED (not translatable):\n";
ksort($skipped);
foreach ($skipped as $r => $list) printf("  %-18s %6d\n", $r, count($list));

echo "\nDYNAMIC (text split by a PHP block — manual): " . count($dynamic) . "\n";

if ($dump) {
    $out = array();
    foreach (($missing ? $todo : $catalog) as $k => $v) {
        $out[] = array(
            'key'    => $k,
            'ctx'    => $v['ctx'],
            'concat' => !empty($v['concat']),
            'files'  => array_keys($v['files']),
        );
    }
    file_put_contents($dump, json_encode(array(
        'keys'    => $out,
        'skipped' => array_map('array_keys', $skipped),
        'dynamic' => $dynamic,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\nwrote $dump (" . count($out) . " keys)\n";
}
