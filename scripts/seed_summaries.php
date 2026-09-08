<?php
/**
 * بذّار الملخّصات — TQ-SUM-DRIVE.
 *
 * ═══ ما يفعله ═══
 *
 * يُدخل ملخّصات وبوربوينت من منصّة أفدني في جدول `books`، ملفّاتها على
 * Google Drive وأغلفتها على الخادم — بالمعمار نفسه الذي أدخل به
 * `seed_books.php` كتب الوزارة، ولا ينزّل ملفًّا واحدًا.
 *
 * ═══ لماذا سكربت ثانٍ لا تعديل في الأوّل ═══
 *
 * لأنّ `--clear` هناك يحذف **كلّ** صفّ له `tq_drive_id`. ومجموعتان في
 * جدول واحد تعني أنّ تنظيف الملخّصات يمحو كتب الوزارة معها. فكلّ
 * استعلام هنا مقيَّد بـ`tq_book_kind IN ('summary','slides')` — والحدّ
 * في السكربت لا في نيّة مشغّله.
 *
 * وحارس لا يملكه الأوّل: معرّف درايف موجود بنوع آخر **يُتخطّى ويُعلن** —
 * فلا يعيد هذا البذّار كتابة كتاب وزارة بحال.
 *
 * ═══ الأوضاع ═══
 *
 *   (بلا وسيط)  معاينة: يقول ما سيفعل ولا يكتب حرفًا
 *   --apply     ينفّذ
 *   --verify    يقرأ من القاعدة ويقارن بالملفّ
 *   --prune     يحذف ملخّصًا في القاعدة لا أثر له في الملفّ
 *   --publish   ينشر ما أُدخل مسوّدةً (بعد المراجعة)
 *   --clear     يحذف ملخّصات هذا البذّار وحدها
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

$apply   = in_array('--apply',   $argv, true);
$clear   = in_array('--clear',   $argv, true);
$verify  = in_array('--verify',  $argv, true);
$publish = in_array('--publish', $argv, true);

/* النوعان اللذان يملكهما هذا البذّار — وحدّ كلّ استعلام فيه. */
$KINDS   = ['summary', 'slides'];
$KIN     = "'" . implode("','", $KINDS) . "'";

$src = __DIR__ . '/books/summaries-data.json';
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
    $n = (int) $one("SELECT COUNT(*) c FROM books WHERE tq_book_kind IN ($KIN)")['c'];
    printf("في القاعدة: %d · في الملفّ: %d %s\n", $n, count($rows), $n === count($rows) ? '✓' : '✗');

    foreach ($all("SELECT status, COUNT(*) n FROM books WHERE tq_book_kind IN ($KIN) GROUP BY status") as $r) {
        printf("  %-10s %d\n", $r['status'], $r['n']);
    }

    $bad = $all("SELECT id, title FROM books
                  WHERE tq_book_kind IN ($KIN)
                    AND (grade_id = 0 OR tq_drive_id = '' OR tq_drive_id IS NULL)");
    printf("بلا صفّ أو بلا معرّف درايف: %d %s\n", count($bad), $bad ? '✗' : '✓');
    foreach (array_slice($bad, 0, 5) as $b) echo '   - #' . $b['id'] . ' ' . $b['title'] . "\n";

    $nocov = (int) $one("SELECT COUNT(*) c FROM books
                          WHERE tq_book_kind IN ($KIN) AND (cover = '' OR cover IS NULL)")['c'];
    printf("بلا غلاف: %d %s\n", $nocov, $nocov ? '⚠' : '✓');

    /* كتب الوزارة تُعدّ هنا أيضًا: أيّ نقص فيها بعد بذر الملخّصات
       يعني أنّ حدّ النوع تسرّب — وهو ما يجب أن يُرى فورًا. */
    $books = (int) $one("SELECT COUNT(*) c FROM books
                          WHERE tq_drive_id <> '' AND tq_book_kind NOT IN ($KIN)")['c'];
    printf("كتب الوزارة (يجب أن تبقى 110): %d %s\n", $books, $books === 110 ? '✓' : '✗');

    echo "\nالصفوف:\n";
    foreach ($all("SELECT g.name_ar, COUNT(b.id) n, COUNT(DISTINCT b.subject) s
                     FROM books b JOIN grades g ON g.id = b.grade_id
                    WHERE b.tq_book_kind IN ($KIN)
                    GROUP BY b.grade_id ORDER BY g.`order`") as $r) {
        printf("  %-24s %3d ملخّصًا · %2d مواد\n", $r['name_ar'], $r['n'], $r['s']);
    }
    exit(0);
}

/* ── التنقية ────────────────────────────────────────────────────
   ملفّ حُذف من درايف يُعرض إطارًا أبيض بلا خطأ — والزائر يحسب العطب
   في المنصّة. فما سقط من الملفّ يسقط من القاعدة، ولا يُترك منشورًا
   ولا مسوّدةً تُنشر سهوًا. وحدّ النوع قائم هنا كما في كلّ استعلام. */
if (in_array('--prune', $argv, true)) {
    $keep = array_map(function ($r) { return $r['drive_id']; }, $rows);
    $in   = implode(',', array_fill(0, count($keep), '?'));
    $gone = $all("SELECT id, title FROM books
                   WHERE tq_book_kind IN ($KIN) AND tq_drive_id NOT IN ($in)", $keep);
    if (!$apply) {
        printf("معاينة: سيحذف %d ملخّصًا لا أثر له في الملفّ. أضف --apply.\n", count($gone));
        foreach (array_slice($gone, 0, 5) as $g) echo "   - #{$g['id']} {$g['title']}\n";
        exit(0);
    }
    $st = $pdo->prepare('DELETE FROM books WHERE id = ?');
    foreach ($gone as $g) $st->execute([$g['id']]);
    printf("حُذف %d ملخّصًا.\n", count($gone));
    exit(0);
}

/* ── النشر ──────────────────────────────────────────────────── */
if ($publish) {
    $n = (int) $one("SELECT COUNT(*) c FROM books
                      WHERE tq_book_kind IN ($KIN) AND status <> 'published'")['c'];
    if (!$apply) { echo "معاينة: سينشر $n ملخّصًا. أضف --apply.\n"; exit(0); }
    $pdo->exec("UPDATE books SET status = 'published' WHERE tq_book_kind IN ($KIN)");
    echo "نُشر $n ملخّصًا.\n"; exit(0);
}

/* ── تفريغ ──────────────────────────────────────────────────── */
if ($clear) {
    $n = (int) $one("SELECT COUNT(*) c FROM books WHERE tq_book_kind IN ($KIN)")['c'];
    if (!$apply) { echo "معاينة: سيحذف $n ملخّصًا (ولا يمسّ كتب الوزارة). أضف --apply.\n"; exit(0); }
    $pdo->exec("DELETE FROM books WHERE tq_book_kind IN ($KIN)");
    echo "حُذف $n ملخّصًا.\n"; exit(0);
}

/* ── الإدخال ────────────────────────────────────────────────── */
$now = time();
$ins = 0; $upd = 0; $skip = [];

foreach ($rows as $r) {
    $ex = $one('SELECT id, tq_book_kind, title FROM books WHERE tq_drive_id = ?', [$r['drive_id']]);
    if ($ex && !in_array($ex['tq_book_kind'], $KINDS, true)) {
        /* معرّف يملكه بذّار آخر (كتاب وزارة): لا يُلمس. */
        $skip[] = $r;
        continue;
    }
    if ($ex) $upd++; else $ins++;
}

echo "═══ الملخّصات ═══\n";
printf("  جديد: %d · محدَّث: %d · متخطّى (معرّفه لكتاب قائم): %d · المجموع: %d\n",
       $ins, $upd, count($skip), count($rows));
foreach (array_slice($skip, 0, 5) as $s) echo "   ⚠ {$s['title']} — معرّفه لكتاب، يبقى.\n";

$cov = 0;
foreach ($rows as $r) if (!empty($r['cover'])) $cov++;
printf("  بأغلفة: %d من %d\n", $cov, count($rows));

if (!$apply) { echo "\nمعاينة فقط — أضف --apply للتنفيذ.\n"; exit(0); }

$pdo->beginTransaction();
try {
    $cols = ['category_id','title','slug','subject','author','pages','tone','cover','file',
             'description','status','tq_order','tq_seed','date_added','grade_id','teacher_id',
             'price','discount_price','tq_sell','access_days','file_size','last_modified',
             'tq_drive_id','tq_book_kind'];

    foreach ($rows as $r) {
        $ex = $one('SELECT id, tq_book_kind FROM books WHERE tq_drive_id = ?', [$r['drive_id']]);
        if ($ex && !in_array($ex['tq_book_kind'], $KINDS, true)) continue;   /* كتاب — لا يُمسّ */

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
            /* مجّانيّ قطعًا: `tq_drive_id` للمجّانيّ وحده — القاعدة في
               `install_schema()`، وملفّ لا نملكه لا يُباع بحال. */
            'price'          => 0,
            'discount_price' => 0,
            'tq_sell'        => 0,
            'access_days'    => 0,
            'file_size'      => (int) $r['file_size'],
            'last_modified'  => $now,
            'tq_drive_id'    => $r['drive_id'],
            'tq_book_kind'   => $r['kind'],
        ];

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

echo "\n✓ تمّ (مسوّدات). شغّل --verify للفحص، ثمّ --publish --apply للنشر.\n";
