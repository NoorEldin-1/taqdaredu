<?php
/**
 * بذّار كتب المنهج — TQ-BOOK-DRIVE.
 *
 * ═══ ما يفعله ═══
 *
 * يُدخل ١١٠ كتابًا من كتب وزارة التعليم في جدول `books`، ملفّاتها على
 * Google Drive وأغلفتها على الخادم. ولا يحسب شيئًا ولا يستنتج: كلّ قرار
 * تصنيف اتُّخذ في `gen_books_data.py` من قراءة الأغلفة، وهذا يُدخل ما
 * يُعطى — فمن أراد مراجعة التصنيف يقرأ الـJSON لا يقرأ SQL.
 *
 * ═══ الأوضاع ═══
 *
 *   (بلا وسيط)  معاينة: يقول ما سيفعل ولا يكتب حرفًا
 *   --apply     ينفّذ
 *   --verify    يقرأ من القاعدة ويقارن بالملفّ
 *   --clear     يحذف ما أدخله هذا البذّار وحده (`tq_drive_id` غير فارغ)
 *
 * والمفتاح `tq_drive_id`: إعادة التشغيل تُحدِّث ولا تُكرِّر.
 */

$root = __DIR__;
while ($root !== dirname($root)) {
    if (is_file($root . '/index.php') && is_dir($root . '/application')) break;
    $root = dirname($root);
}
$cfg = $root . '/application/config/database.php';
if (!is_file($cfg)) exit("تعذّر العثور على إعدادات القاعدة.\n");

defined('BASEPATH') or define('BASEPATH', $root . '/system/');
defined('ENVIRONMENT') or define('ENVIRONMENT', 'production');
require $cfg;
$conf = $db[isset($active_group) ? $active_group : 'default'];

$apply  = in_array('--apply',  $argv, true);
$clear  = in_array('--clear',  $argv, true);
$verify = in_array('--verify', $argv, true);

$src = __DIR__ . '/books/books-data.json';
if (!is_file($src)) exit("لا ملفّ بيانات: $src\n");
$rows = json_decode(file_get_contents($src), true);
if (!is_array($rows) || !$rows) exit("ملفّ البيانات فارغ أو تالف.\n");

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $conf['hostname'], $conf['database']),
    $conf['username'], $conf['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$all = function ($q, $a = []) use ($pdo) { $s = $pdo->prepare($q); $s->execute($a); return $s->fetchAll(); };
$one = function ($q, $a = []) use ($pdo) { $s = $pdo->prepare($q); $s->execute($a); return $s->fetch(); };

/* ── فحص ────────────────────────────────────────────────────── */
if ($verify) {
    $n = (int) $one('SELECT COUNT(*) c FROM books WHERE tq_drive_id IS NOT NULL AND tq_drive_id <> ""')['c'];
    printf("في القاعدة: %d · في الملفّ: %d %s\n", $n, count($rows), $n === count($rows) ? '✓' : '✗');

    $bad = $all('SELECT id, title FROM books WHERE tq_drive_id <> "" AND (grade_id = 0 OR cover = "" OR cover IS NULL)');
    printf("بلا صفّ أو بلا غلاف: %d %s\n", count($bad), $bad ? '✗' : '✓');
    foreach (array_slice($bad, 0, 5) as $b) echo '   - #' . $b['id'] . ' ' . $b['title'] . "\n";

    $ph = (int) $one('SELECT COUNT(*) c FROM books WHERE tq_seed = 1')['c'];
    printf("بطاقات البذر القديمة الباقية: %d %s\n", $ph, $ph === 0 ? '✓' : '✗');

    echo "\nالصفوف:\n";
    foreach ($all('SELECT g.name_ar, g.`order` o, COUNT(b.id) n, COUNT(DISTINCT b.subject) s
                     FROM books b JOIN grades g ON g.id = b.grade_id
                    WHERE b.tq_drive_id <> "" GROUP BY b.grade_id ORDER BY g.`order`') as $r) {
        printf("  %-24s %2d كتابًا · %2d مواد\n", $r['name_ar'], $r['n'], $r['s']);
    }
    exit(0);
}

/* ── تفريغ ──────────────────────────────────────────────────── */
if ($clear) {
    $n = (int) $one('SELECT COUNT(*) c FROM books WHERE tq_drive_id IS NOT NULL AND tq_drive_id <> ""')['c'];
    if (!$apply) { echo "معاينة: سيحذف $n كتابًا (بمعرّف Drive). أضف --apply.\n"; exit(0); }
    $pdo->exec('DELETE FROM books WHERE tq_drive_id IS NOT NULL AND tq_drive_id <> ""');
    echo "حُذف $n كتابًا.\n"; exit(0);
}

/* ── البطاقات الفارغة القديمة ───────────────────────────────────
   تُحذف لا تُملأ (قرار المالك). والحذف مشروط: صفّ اشتراه أحد لا يُحذف
   ولو كان بذرًا — الفاتورة تشير إليه بمعرّفه، وسجلّ يشير إلى لا شيء
   لا يُقرأ بعدها أبدًا. */
$ph = $all('SELECT id, title FROM books WHERE tq_seed = 1');
$blocked = [];
foreach ($ph as $p) {
    $c = (int) $one('SELECT COUNT(*) c FROM subscriptions WHERE book_id = ?', [$p['id']])['c'];
    if ($c > 0) $blocked[] = $p;
}

echo "═══ بطاقات البذر القديمة ═══\n";
printf("  للحذف: %d · محجوزة باشتراك: %d\n", count($ph) - count($blocked), count($blocked));
foreach ($blocked as $b) echo "   ⚠ #{$b['id']} {$b['title']} — اشتُري، يبقى.\n";

/* ── الإدخال ────────────────────────────────────────────────── */
$now = time();
$ins = 0; $upd = 0;

echo "\n═══ الكتب ═══\n";
foreach ($rows as $i => $r) {
    $ex = $one('SELECT id FROM books WHERE tq_drive_id = ?', [$r['drive_id']]);
    if ($ex) $upd++; else $ins++;
    if ($i < 3 || $i === count($rows) - 1) {
        printf("  %s %s\n", $ex ? 'تحديث' : 'إدخال ', $r['title']);
    } elseif ($i === 3) {
        echo "  … \n";
    }
}
printf("  جديد: %d · محدَّث: %d · المجموع: %d\n", $ins, $upd, count($rows));

if (!$apply) { echo "\nمعاينة فقط — أضف --apply للتنفيذ.\n"; exit(0); }

$pdo->beginTransaction();
try {
    foreach ($ph as $p) {
        $held = false;
        foreach ($blocked as $b) if ($b['id'] === $p['id']) $held = true;
        if (!$held) $pdo->prepare('DELETE FROM books WHERE id = ?')->execute([$p['id']]);
    }

    $cols = ['category_id','title','slug','subject','author','pages','tone','cover','file',
             'description','status','tq_order','tq_seed','date_added','grade_id','teacher_id',
             'price','discount_price','tq_sell','access_days','file_size','last_modified',
             'tq_drive_id','tq_book_kind'];

    foreach ($rows as $r) {
        $v = [
            'category_id'    => (int) $r['category_id'],
            'title'          => $r['title'],
            'slug'           => $r['slug'],
            'subject'        => $r['subject'],
            'author'         => $r['author'],
            'pages'          => 0,
            'tone'           => $r['tone'],
            'cover'          => $r['cover'],
            'file'           => null,
            'description'    => $r['description'],
            'status'         => $r['status'],
            'tq_order'       => (int) $r['tq_order'],
            'tq_seed'        => 0,
            'date_added'     => $now,
            'grade_id'       => (int) $r['grade_id'],
            'teacher_id'     => 0,
            'price'          => 0,
            'discount_price' => 0,
            'tq_sell'        => 0,
            'access_days'    => 0,
            'file_size'      => (int) $r['file_size'],
            'last_modified'  => $now,
            'tq_drive_id'    => $r['drive_id'],
            'tq_book_kind'   => $r['kind'],
        ];

        $ex = $one('SELECT id FROM books WHERE tq_drive_id = ?', [$r['drive_id']]);
        if ($ex) {
            $set = implode(', ', array_map(function ($c) { return "`$c` = ?"; }, $cols));
            $a   = array_values($v); $a[] = (int) $ex['id'];
            $pdo->prepare("UPDATE books SET $set WHERE id = ?")->execute($a);
        } else {
            $ph2 = implode(', ', array_fill(0, count($cols), '?'));
            $cl  = '`' . implode('`, `', $cols) . '`';
            $pdo->prepare("INSERT INTO books ($cl) VALUES ($ph2)")->execute(array_values($v));
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit("\n✗ فشل ورُدّ كلّ شيء: " . $e->getMessage() . "\n");
}

echo "\n✓ تمّ. شغّل --verify للفحص.\n";
