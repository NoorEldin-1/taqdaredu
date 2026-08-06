<?php
/**
 * يحذف التشكيل العربي من كل نص مخزن في قاعدة البيانات.
 *
 * الشيفرة والقوالب جردت من التشكيل في المستودع، لكن نصف النص الظاهر
 * يعيش في الجداول لا في الملفات: `language` و`settings` و`plans` وأسماء
 * الدروس والأسئلة. فبلا هذا المرور يبقى نصف الموقع مشكلا.
 *
 * يمر على كل عمود نصي في المخطط وقت التشغيل — لا على قائمة محفوظة —
 * حتى يلتقط الجداول الممتلئة على الخادم والفارغة محليا (`answers` مثلا).
 *
 * الاستعمال (من جذر الموقع):
 *     php scripts/strip_tashkeel_db.php            # عرض ما سيتغير، بلا كتابة
 *     php scripts/strip_tashkeel_db.php --apply    # التنفيذ داخل معاملة
 *
 * وهو مأمون التكرار: تشغيله مرتين لا يغير شيئا في الثانية.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("CLI only.\n");
}

/* ---------- جذر الموقع: يعرف بعلامته لا بموضع السكربت ---------- */
$root = __DIR__;
while ($root !== dirname($root)) {
    if (is_file($root . '/index.php') && is_dir($root . '/application')) {
        break;
    }
    $root = dirname($root);
}
$cfg = $root . '/application/config/database.php';
if (!is_file($cfg)) {
    exit("تعذر العثور على application/config/database.php من " . __DIR__ . "\n");
}

/* الملف يحرس نفسه بـ BASEPATH، ويقرأ ENVIRONMENT في `db_debug` — وكلاهما
   يعرفه `index.php` عادة. هنا يعرفان يدويا لأن المرور لا يقلع CodeIgniter:
   تحميل النواة كاملة لأجل مصفوفة اتصال تحميل ما لا يلزم. */
defined('BASEPATH') or define('BASEPATH', $root . '/system/');
defined('ENVIRONMENT') or define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'production');
require $cfg;
$conf = $db[isset($active_group) ? $active_group : 'default'];

/* ---------- العلامات: التشكيل وحده. التطويل U+0640 ليس تشكيلا فيبقى ---------- */
$marks = ["\u{064B}", "\u{064C}", "\u{064D}", "\u{064E}",
          "\u{064F}", "\u{0650}", "\u{0651}", "\u{0652}"];

/* ---------- جداول لا تمس ---------- */
$skip_tables = [
    // سجل تدقيق: يحفظ القيم كما كانت وقت التغيير. إعادة كتابته تزوير سجل.
    'audit_log',
];

$apply = in_array('--apply', $argv, true);

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $conf['hostname'], $conf['database']),
    $conf['username'],
    $conf['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

/* ---------- الأعمدة النصية، ولكل جدول مفتاحه الأولي ---------- */
$cols = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND DATA_TYPE IN ('varchar','text','mediumtext','longtext','tinytext','char')
     ORDER BY TABLE_NAME, ORDINAL_POSITION
")->fetchAll(PDO::FETCH_NUM);

$pks = [];
foreach ($pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_KEY = 'PRI'
     ORDER BY TABLE_NAME, ORDINAL_POSITION
", PDO::FETCH_NUM) as $r) {
    $pks[$r[0]][] = $r[1];
}

$pdo->beginTransaction();

$total_rows = 0;
$report     = [];
$skipped_nopk = [];

foreach ($cols as [$tbl, $col]) {
    if (in_array($tbl, $skip_tables, true)) {
        continue;
    }
    /* مفتاح أولي مفرد شرط التحديث الآمن: بلا معرف لا يستهدف صف بعينه. */
    if (!isset($pks[$tbl]) || count($pks[$tbl]) !== 1) {
        $skipped_nopk[$tbl] = true;
        continue;
    }
    $pk = $pks[$tbl][0];

    $sel = $pdo->query(sprintf('SELECT `%s`, `%s` FROM `%s`', $pk, $col, $tbl));
    $upd = $pdo->prepare(sprintf('UPDATE `%s` SET `%s` = ? WHERE `%s` = ?', $tbl, $col, $pk));

    $n = 0;
    foreach ($sel as $row) {
        $id  = $row[$pk];
        $old = $row[$col];
        if (!is_string($old) || $old === '') {
            continue;
        }
        $new = str_replace($marks, '', $old);
        if ($new === $old) {
            continue;
        }
        $n++;
        if ($apply) {
            $upd->execute([$new, $id]);
        }
    }
    if ($n) {
        $report[] = [$n, "$tbl.$col"];
        $total_rows += $n;
    }
}

if ($apply) {
    $pdo->commit();
} else {
    $pdo->rollBack();
}

usort($report, function ($a, $b) { return $b[0] - $a[0]; });
foreach ($report as [$n, $where]) {
    printf("%6d صف  %s\n", $n, $where);
}
if ($skipped_nopk) {
    printf("\nتخطيت (بلا مفتاح أولي مفرد): %s\n", implode(', ', array_keys($skipped_nopk)));
}
printf("\nجداول متروكة عمدا: %s\n", implode(', ', $skip_tables));
printf("المجموع: %d صف في %d عمود — %s\n",
    $total_rows, count($report), $apply ? 'نفذ' : 'عرض فقط (أضف ‎--apply‎)');
