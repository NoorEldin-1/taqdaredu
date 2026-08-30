<?php
/**
 * TQ-I18N — يدمج ترجمات من ملف نصي في ملفات القاموس.
 *
 * الترجمة تكتب سطرا سطرا: `النص العربي<tab>الترجمة`. والسطر أخف من صيغة
 * PHP في المراجعة وفي أداة الترجمة، والدمج هنا يضعه في ملف مجموعته —
 * فلا يحتاج من يترجم أن يعرف أي ملف يخص أي شاشة.
 *
 * ويرفض ما لا مفتاح له: ترجمة لنص لا وجود له في الشيفرة تعني أن المفتاح
 * كتب بخطأ إملائي أو مسافة زائدة، وقبولها صامتة تترك النص عربيا على
 * الشاشة والقاموس يقول إنه ترجم.
 *
 *     php scripts/i18n_fill.php batch.tsv
 *     php scripts/i18n_fill.php batch.tsv --lang=english
 */

require __DIR__ . '/i18n_lib.php';
chdir(dirname(__DIR__));

$file = null; $lang = 'english';
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--lang=') === 0) { $lang = substr($a, 7); continue; }
    if (strpos($a, '--') === 0) continue;
    $file = $a;
}
if (!$file || !is_file($file)) { fwrite(STDERR, "usage: i18n_fill.php <batch.tsv>\n"); exit(1); }

$dir = 'application/language/tq/' . $lang;

/* المفتاح -> الملف الذي يسكنه. */
$home = array();
$cat  = array();
foreach (glob($dir . '/*.php') as $f) {
    $part = include $f;
    if (!is_array($part)) continue;
    $cat[$f] = $part;
    foreach ($part as $k => $v) $home[tq_key($k)] = $f;
}

$applied = 0; $unknown = array(); $blank = 0;
foreach (file($file, FILE_IGNORE_NEW_LINES) as $ln => $line) {
    if (trim($line) === '' || $line[0] === '#') continue;
    $parts = explode("\t", $line, 2);
    if (count($parts) < 2) { $blank++; continue; }

    $k = tq_key($parts[0]);
    $v = trim($parts[1]);
    if ($k === '' || $v === '') { $blank++; continue; }

    if (!isset($home[$k])) { $unknown[] = mb_substr($k, 0, 70); continue; }
    $cat[$home[$k]][$k] = $v;
    $applied++;
}

foreach ($cat as $f => $rows) {
    $head = file_get_contents($f);
    $head = substr($head, 0, strpos($head, 'return array('));

    $lines = array();
    foreach ($rows as $k => $v) {
        $lines[] = "    '" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $k) . "' => '"
                 . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $v) . "',";
    }
    file_put_contents($f, $head . "return array(\n" . implode("\n", $lines) . "\n);\n");
}

echo "applied: $applied   skipped-blank: $blank   unknown: " . count($unknown) . "\n";
foreach (array_slice($unknown, 0, 25) as $u) echo "  ? $u\n";

/* عداد ما بقي. */
$done = 0; $all = 0;
foreach (glob($dir . '/*.php') as $f) {
    $part = include $f;
    foreach ($part as $v) { $all++; if ($v !== '' && $v !== null) $done++; }
}
echo "catalog: $done / $all translated\n";
