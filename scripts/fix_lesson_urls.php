<?php
/**
 * يفك تهريب HTML من روابط الفيديو المخزنة.
 *
 * `Crud_model` يكتب `html_escape($this->input->post('video_url'))` في
 * ستة مواضع — والرابط ليس نصا يعرض في صفحة، هو **قيمة تفكك**: يقرؤها
 * محلل الرابط في المتصفح، ويقرأ منها المشغل معرف الفيديو. فتهريبها
 * يحول `&` إلى `&amp;` في القاعدة، فيقرأ المشغل معاملا اسمه `amp;list`
 * لا `list`.
 *
 * وأسوأ منه أن الحفظ **يهرب المهرب**: من فتح درسا وحفظه بلا تعديل صار
 * رابطه `&amp;amp;`، ثم `&amp;amp;amp;`. عندنا في القاعدة درس بلغ
 * المستوى الثاني فعلا (id=390).
 *
 * والتهريب يبقى في العرض حيث موضعه: القالب يهرب حين يطبع. أما التخزين
 * فيحفظ ما كتبه صاحبه.
 *
 * الاستعمال (من جذر الموقع):
 *     php scripts/fix_lesson_urls.php            # عرض ما سيتغير، بلا كتابة
 *     php scripts/fix_lesson_urls.php --apply    # التنفيذ داخل معاملة
 *
 * مأمون التكرار: يفك حتى يستقر النص، فتشغيله مرتين لا يغير شيئا في
 * الثانية. ولا يفك ما ليس تهريبا: `&` عارية تبقى كما هي.
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

defined('BASEPATH') or define('BASEPATH', $root . '/system/');
defined('ENVIRONMENT') or define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'production');
require $cfg;
$conf = $db[isset($active_group) ? $active_group : 'default'];

/**
 * الأعمدة التي تحمل روابط لا نصوصا.
 *
 * قائمة معلنة لا مسح للمخطط: عمود `text` قد يحمل وصفا فيه `&amp;`
 * مقصودا (نص كتبه محرر HTML)، وفكه هناك يخرب الوصف. والرابط يعرف
 * بموضعه لا بشكله.
 */
$targets = [
    ['lesson',   'id', 'video_url'],
    ['lesson',   'id', 'video_url_for_mobile_application'],
    ['lesson',   'id', 'audio_url'],
    ['course',   'id', 'video_url'],
];

/**
 * يفك التهريب حتى يستقر.
 *
 * `html_entity_decode` مرة واحدة لا تكفي: `&amp;amp;` تصير `&amp;` لا
 * `&`. والحلقة تقف عند عدم التغير، وبحد أقصى ثمان دورات فلا تدور أبدا
 * على مدخل خبيث.
 */
function tq_unescape_url($s)
{
    for ($i = 0; $i < 8; $i++) {
        $next = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($next === $s) return $s;
        $s = $next;
    }
    return $s;
}

$apply = in_array('--apply', $argv, true);

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $conf['hostname'], $conf['database']),
    $conf['username'],
    $conf['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

/* الأعمدة الغائبة تتخطى بهدوء: المخطط ينمو، وسكربت يسقط على عمود لم
   ينشأ بعد يمنع إصلاح الأعمدة التي أمامه. */
$have = [];
foreach ($pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
", PDO::FETCH_NUM) as $r) {
    $have[$r[0] . '.' . $r[1]] = true;
}

$pdo->beginTransaction();

$total   = 0;
$report  = [];
$samples = [];

foreach ($targets as [$tbl, $pk, $col]) {
    if (!isset($have["$tbl.$col"])) continue;

    $sel = $pdo->query(sprintf('SELECT `%s`, `%s` FROM `%s`', $pk, $col, $tbl));
    $upd = $pdo->prepare(sprintf('UPDATE `%s` SET `%s` = ? WHERE `%s` = ?', $tbl, $col, $pk));

    $n = 0;
    foreach ($sel as $row) {
        $old = $row[$col];
        if (!is_string($old) || $old === '') continue;

        $new = tq_unescape_url($old);
        if ($new === $old) continue;

        $n++;
        if (count($samples) < 5) {
            $samples[] = sprintf("  %s#%s\n    قبل: %s\n    بعد: %s",
                $tbl, $row[$pk], $old, $new);
        }
        if ($apply) $upd->execute([$new, $row[$pk]]);
    }
    if ($n) {
        $report[] = [$n, "$tbl.$col"];
        $total += $n;
    }
}

if ($apply) $pdo->commit();
else        $pdo->rollBack();

usort($report, function ($a, $b) { return $b[0] - $a[0]; });
foreach ($report as [$n, $where]) {
    printf("%6d صف  %s\n", $n, $where);
}
if ($samples) {
    printf("\nعينة:\n%s\n", implode("\n", $samples));
}
printf("\nالمجموع: %d صف في %d عمود — %s\n",
    $total, count($report), $apply ? 'نفذ' : 'عرض فقط (أضف ‎--apply‎)');
