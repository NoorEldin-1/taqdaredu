<?php
/**
 * TQ-I18N — يبني هيكل القاموس: كل مفتاح مستعمل، وترجمته فارغة تنتظر.
 *
 * الفارغ **لا يخزن** (انظر `tq_catalog()`): مدخل بقيمة فارغة يعني «لم
 * يترجم بعد» فيرد `t()` العربية كما هي — وهو أهون من مفتاح يعرض عاريا.
 * فالهيكل هنا وثيقة عمل تملأ، لا حالة تشحن ناقصة.
 *
 * ويحافظ على ما ترجم: الملف يعاد بناؤه بعد كل جولة تعديل آلي، ولو محا
 * الترجمات لضاع العمل كله عند أول مفتاح يضاف.
 *
 *     php scripts/i18n_skeleton.php            # عرض ما سيكتب
 *     php scripts/i18n_skeleton.php --apply
 */

require __DIR__ . '/i18n_lib.php';
chdir(dirname(__DIR__));

$apply = in_array('--apply', $argv, true);
$lang  = 'english';
foreach ($argv as $a) if (strpos($a, '--lang=') === 0) $lang = substr($a, 7);

$dir = 'application/language/tq/' . $lang;
if (!is_dir($dir)) mkdir($dir, 0775, true);

/* ---- ما ترجم فعلا: يحفظ ---- */
$have = array();
$owned = array();          // مفاتيح ملف يدوي — لا تكتب في المولد
foreach (glob($dir . '/*.php') as $f) {
    $part = include $f;
    if (!is_array($part)) continue;
    $hand = (basename($f)[0] === '_');
    foreach ($part as $k => $v) {
        $kk = tq_i18n_key_local($k);
        if ($v !== '' && $v !== null) $have[$kk] = $v;
        /* الملف الذي يبدأ اسمه بـ`_` يكتب بيد ولا يولد: مفاتيحه ليست في
           الشيفرة (نص من `frontend_settings`، اسم المنصة)، فالمولد لا يجدها
           ولا يعيد كتابتها — ولو ملكها لمحاها في أول تشغيل. */
        if ($hand) $owned[$kk] = true;
    }
}

function tq_i18n_key_local($s) { return tq_key($s); }

/* ---- المفاتيح المستعملة، مصنفة بمجموعتها ---- */
$byGroup = array();

foreach (tq_i18n_targets() as $gname => $group) {
    foreach (tq_i18n_files($group) as $file) {
        $src = file_get_contents($file);
        if (!tq_has_arabic($src)) continue;

        /* نداءات `t()`/`te()` الصريحة. */
        if (preg_match_all("/\\bte?\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/u", $src, $m)) {
            foreach ($m[1] as $raw) {
                $k = tq_key(str_replace(array("\\'", '\\\\'), array("'", '\\'), $raw));
                if ($k !== '' && tq_has_arabic($k)) $byGroup[$gname][$k] = true;
            }
        }

        /* الطبقة تحت القوالب: نصها يترجم عند العرض، ومفتاحه نصه. */
        if (!empty($group['wrap'])) continue;
        $toks = token_get_all($src);
        foreach ($toks as $i => $tok) {
            if (!is_array($tok) || $tok[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
            if (!tq_has_arabic($tok[1])) continue;
            if (tq_php_reject($toks, $i) !== null) continue;
            $k = tq_key(tq_tok_value($tok));
            if ($k !== '') $byGroup[$gname][$k] = true;
        }
    }
}

/* ---- الجافاسكربت ---- */
foreach (glob('assets/taqdar/js/*.js') as $f) {
    $src = file_get_contents($f);
    if (preg_match_all("/\\bTQ A?\\.t\\(/", $src, $x)) { /* لا شيء — الصيغة التالية أدق */ }
    if (preg_match_all("/\\b(?:TQ|TQA)\\.t\\(\\s*(['\"])((?:(?!\\1)[^\\\\]|\\\\.)*)\\1/u", $src, $m)) {
        foreach ($m[2] as $raw) {
            $k = tq_key(stripslashes($raw));
            if ($k !== '' && tq_has_arabic($k)) $byGroup['js'][$k] = true;
        }
    }
}

/* ---- مفتاح واحد في ملف واحد ----
   النص نفسه يستعمل في بوابة الطالب وفي اللوحة وفي نموذج تحته، ولو كتب في
   كل ملف لترجم ثلاث مرات — وثلاث ترجمات لنص واحد تفترق عند أول تصحيح،
   ويعرض الملف الأخير ترجمته على البقية بلا أن يعرف أحد لماذا اختلفت
   الشاشتان. فالترتيب ثابت، وأول مجموعة تأخذ المفتاح. */
$order = array('models', 'controllers', 'helpers', 'components', 'portal', 'admin', 'js');
$seen  = array();
$dedup = array();
foreach ($order as $g) {
    if (empty($byGroup[$g])) continue;
    foreach (array_keys($byGroup[$g]) as $k) {
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $dedup[$g][$k] = true;
    }
}
foreach ($byGroup as $g => $keys) {   // مجموعة لم تذكر في الترتيب
    if (in_array($g, $order, true)) continue;
    foreach (array_keys($keys) as $k) {
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $dedup[$g][$k] = true;
    }
}
$byGroup = $dedup;

/* ---- الكتابة ---- */
$totalKeys = 0; $totalDone = 0;
foreach ($byGroup as $g => $keys) {
    ksort($keys);
    $lines = array();
    $done = 0;
    foreach (array_keys($keys) as $k) {
        /* مفتاح يملكه ملف يدوي لا يكرر هنا: نسختان لنص واحد تفترقان عند أول
           تصحيح، وأيهما يعرض يقرره ترتيب `glob` — وهو ليس قرارا. */
        if (isset($owned[$k])) continue;
        $v = isset($have[$k]) ? $have[$k] : '';
        if ($v !== '') $done++;
        $lines[] = "    '" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $k) . "' => '"
                 . str_replace(array('\\', "'"), array('\\\\', "\\'"), $v) . "',";
    }
    $totalKeys += count($lines); $totalDone += $done;

    $head = "<?php\n"
          . "/**\n"
          . " * TQ-I18N — قاموس " . $lang . " · " . $g . "\n"
          . " *\n"
          . " * المفتاح هو النص العربي كما كتب في الشيفرة، والقيمة ترجمته.\n"
          . " * وقيمة فارغة تعني «لم يترجم بعد»: `tq_catalog()` تسقطها، فيرد\n"
          . " * `t()` العربية كما هي — شاشة بلا ترجمة تعرض ما كانت تعرضه، ولا\n"
          . " * تعرض مفتاحا عاريا ولا فراغا.\n"
          . " *\n"
          . " * يولد بـ`php scripts/i18n_skeleton.php --apply`، وهو يحفظ ما ترجم\n"
          . " * ويضيف ما جد. فلا تحرر ترتيبه بيد — حرر القيم وحدها.\n"
          . " */\n\n"
          . "return array(\n";

    $out = $head . implode("\n", $lines) . "\n);\n";
    $path = $dir . '/' . $g . '.php';

    printf("%-46s %5d keys  %5d translated\n", $path, count($keys), $done);
    if ($apply) file_put_contents($path, $out);
}

printf("\ntotal %d keys, %d translated, %d remaining  %s\n",
    $totalKeys, $totalDone, $totalKeys - $totalDone, $apply ? 'WRITTEN' : '(dry run)');
