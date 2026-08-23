<?php
/**
 * بذرة عرض — تكملة بوابة الطالب.
 *
 * `seed_demo_student.php` يغطي التعلم والقياس: الكورسات والاختبارات والواجبات
 * والمراجعة والحجوزات والاشتراكات والرسائل. وهذا المرور يغطي ما بقي من شاشات
 * بلا بيانات — وكلها كانت تعرض حالة فارغة صادقة **دائمة**، لا تتغير مهما فعل
 * الطالب، فتقرأ على أنها عطل:
 *
 *   المواد التعليمية  · ملفات حقيقية على القرص وصفوف `resource_files` تشير
 *                       إليها. والحجم يقرأ من القرص لا من عمود، فالملف لا بد
 *                       أن يوجد فعلا وإلا عرض الجدول «—» في خانة الحجم وأعطى
 *                       زر تحميل يرد 404.
 *   المفضلة           · دروس وملفات في `tq_favourites` وكورسات في
 *                       `users.wishlist` — الأقسام الثلاثة تمتلئ معا.
 *   الشهادات          · لا شهادة في المنصة كلها لأن `assessments` ليس فيها
 *                       صف واحد من نوع `exam` — والشاشة تقرأ `type='exam'`
 *                       و`passed=1` وحدهما. فتبذر امتحانات محطات ومحاولات
 *                       ناجحة عليها.
 *   حصص بالطلب        · روابط لقاء على الحجوزات المؤكدة والجارية، وطلب معلق
 *                       لحساب المعلم التجريبي ليجرب مسار التأكيد كاملا.
 *
 * الاستعمال (من جذر الموقع):
 *     php scripts/seed_demo_student_extras.php                 # عرض الخطة
 *     php scripts/seed_demo_student_extras.php --apply
 *     php scripts/seed_demo_student_extras.php --apply --clear # حذف هذه البذرة
 *     php scripts/seed_demo_student_extras.php --apply --student=290
 *
 * مأمون التكرار: كل ما يكتب يوسم، والوسم هو ما يحذف. والحذف مقيد بهذا
 * الطالب وبما بذر هذا المرور وحده — لا يقترب من محتوى حقيقي ولا من طالب آخر.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("CLI only.\n");
}

/* ---------- جذر الموقع: يعرف بعلامته لا بموضع السكربت ---------- */
$root = __DIR__;
while ($root !== dirname($root)) {
    if (is_file($root . '/index.php') && is_dir($root . '/application')) break;
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

$apply = in_array('--apply', $argv, true);
$clear = in_array('--clear', $argv, true);
$want  = 0;
foreach ($argv as $a) {
    if (strpos($a, '--student=') === 0) $want = (int) substr($a, 10);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $conf['hostname'], $conf['database']),
    $conf['username'],
    $conf['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

/* ---------- أدوات ---------- */
$writes = 0;
$run = function ($sql, $args = []) use ($pdo, $apply, &$writes) {
    $writes++;
    if (!$apply) return 0;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->rowCount();
};
$all = function ($sql, $args = []) use ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
};
$one = function ($sql, $args = []) use ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $r = $st->fetch(PDO::FETCH_NUM);
    return $r ? $r[0] : null;
};
$dt  = static function ($ts) { return date('Y-m-d H:i:s', $ts); };
$say = static function ($s = '') { echo $s, "\n"; };

/* وسم البذرة: يكتب في `resource_files.file_name` وفي عنوان الامتحان،
   فيعرف ما يحذف بلا جدول وسوم منفصل. */
const TAG = 'tqseedx';

/** الرابط الخارجي الموسوم — الوسم في الشذرة ليعرف عند الحذف. */
const LINK_ATTACHMENT = 'https://ar.wikipedia.org/wiki/رياضيات#' . TAG;

/* ---------- الحساب المستهدف ---------- */
$SID = $want ?: (int) $one("SELECT id FROM users WHERE email = ? LIMIT 1", ['student.test@taqdaredu.com']);
if (!$SID) exit("لا حساب عرض. مرر --student=<معرف الطالب>\n");

$who = $all("SELECT id, email, first_name, last_name, role_id, is_instructor FROM users WHERE id = ?", [$SID]);
if (!$who) exit("لا مستخدم بالمعرف $SID\n");
$who = $who[0];
if ((int) $who['role_id'] === 1 || (int) $who['is_instructor'] === 1) {
    exit("المعرف $SID ليس حساب طالب (أدمن أو معلم). أوقف.\n");
}

$TQA = (int) $one("SELECT id FROM users WHERE email = ? LIMIT 1", ['teacher.test@taqdaredu.com']);
$now = time();
$DAY = 86400;

$say('');
$say('حساب العرض : ' . $who['first_name'] . ' ' . $who['last_name'] . '  (#' . $SID . ' · ' . $who['email'] . ')');
$say('قاعدة       : ' . $conf['database'] . ' @ ' . $conf['hostname']);
$say('الوضع       : ' . ($apply ? ($clear ? 'حذف البذرة' : 'تنفيذ') : 'عرض الخطة فقط (بلا كتابة)'));
$say(str_repeat('-', 62));

/* الكورسات المسجلة للطالب — كل ما يبذر هنا داخلها، فلا يظهر له ملف
   ولا شهادة في كورس لم يسجل فيه. */
$enrolled = $all(
    "SELECT c.id, c.title FROM enrol e JOIN course c ON c.id = e.course_id
      WHERE e.user_id = ? ORDER BY c.id", [$SID]);
if (!$enrolled) exit("الطالب غير مسجل في أي كورس. شغل seed_demo_student.php أولا.\n");

$course_ids = array_map(static function ($r) { return (int) $r['id']; }, $enrolled);
$in_courses = implode(',', $course_ids);

$files_dir = $root . '/uploads/resource_files';

/* ================================================================
   ٠ · المخطط الذي يعتمد عليه هذا المرور
   ================================================================
   `tq_favourites` و`tutoring_sessions.meet_url` ينشئهما التطبيق كسولا عند
   أول استعمال (`ensure_schema` في النموذجين). وهذا السكربت يتصل بـPDO
   مباشرة ولا يمر بالتطبيق، فلا يضمن أن أحدا فتح الشاشة قبله — وعلى قاعدة
   نشرت للتو لم يفتحها أحد، فيسقط عند أول `meet_url` بـ«Unknown column».
   فيملك المرور مخططه بدل أن يفترض أن غيره هيأه له. */
if ($apply) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `tq_favourites` (
            `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id`    INT(10) UNSIGNED NOT NULL,
            `kind`       VARCHAR(16)      NOT NULL,
            `item_id`    INT(10) UNSIGNED NOT NULL,
            `created_at` INT(11)          NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_user_kind_item` (`user_id`,`kind`,`item_id`),
            KEY `ix_user_kind` (`user_id`,`kind`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $has_meet = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'tutoring_sessions' AND COLUMN_NAME = 'meet_url'"
    )->fetchColumn();
    if (!$has_meet) {
        $pdo->exec("ALTER TABLE `tutoring_sessions` ADD COLUMN `meet_url` VARCHAR(512) NULL DEFAULT NULL");
        $say('٠ · أضيف العمود tutoring_sessions.meet_url');
    }
}

/* ================================================================
   ١ · حذف أثر هذا المرور وحده
   ================================================================ */
$say('١ · حذف أثر البذرة السابقة');

/* المفضلة: صفوف هذا الطالب في جدول لا يكتبه غير المستخدم — تمسح كلها له. */
$run("DELETE FROM tq_favourites WHERE user_id = ?", [$SID]);

/* الملفات: صفوف موسومة في دروس كورسات هذا الطالب وحدها. */
$old_files = $all(
    "SELECT rf.id, rf.file_name FROM resource_files rf
       JOIN lesson l ON l.id = rf.lesson_id
      WHERE l.course_id IN ($in_courses) AND rf.file_name LIKE ?", [TAG . '%']);
foreach ($old_files as $f) {
    $p = $files_dir . '/' . $f['file_name'];
    if ($apply && is_file($p)) @unlink($p);
}
$run("DELETE rf FROM resource_files rf JOIN lesson l ON l.id = rf.lesson_id
       WHERE l.course_id IN ($in_courses) AND rf.file_name LIKE ?", [TAG . '%']);

/* الشهادات: محاولات هذا الطالب على امتحانات موسومة، ثم الامتحانات نفسها
   — ولا تحذف إن كان عليها محاولة لطالب آخر. */
$run("DELETE t FROM attempts t JOIN assessments a ON a.id = t.assessment_id
       WHERE t.student_id = ? AND a.type = 'exam'
         AND a.milestone_id IN (SELECT id FROM milestones WHERE title LIKE ?)",
     [$SID, '%']);
$run("DELETE a FROM assessments a
       WHERE a.type = 'exam' AND a.path_id IN
             (SELECT id FROM paths WHERE course_id IN ($in_courses))
         AND NOT EXISTS (SELECT 1 FROM attempts t2 WHERE t2.assessment_id = a.id)");

/* روابط اللقاء التي وضعها هذا المرور. */
$run("UPDATE tutoring_sessions SET meet_url = NULL WHERE student_id = ?", [$SID]);

/* المرفق الخارجي الموسوم يعاد فارغا — وهو الأثر الوحيد لهذا المرور في
   محتوى يملكه معلم، فلا يترك وراءه. والشرط على الرابط نفسه لا على الدرس،
   فلا يمس مرفقا وضعه صاحبه. */
$run("UPDATE lesson SET attachment = '', attachment_type = ''
       WHERE course_id IN ($in_courses) AND attachment = ?", [LINK_ATTACHMENT]);

/* مواعيد حساب المعلم التجريبي وطلباته من هذا الطالب.
   بدون هذا الحذف يتراكم طلب معلق وموعدان مفتوحان في كل تشغيل، فتمتلئ
   شاشة المعلم بطلبات مكررة لا تنتهي — والمرور يوصف بأنه مأمون التكرار.

   والحذف مقيد بـ`duration_min = 45` وهي بصمة هذا المرور وحده: المرور
   الأساسي (`seed_demo_student.php`) يفتح مواعيده بـ240 و300 دقيقة.
   وبلا هذا القيد كان الحذف يمسح **كل** حجوزات المعلم التجريبي — وهو على
   الإنتاج المعلم الفاعل الوحيد، فيقع عليه كل ما يبذره المرور الأساسي من
   الحالات السبع. فيمحو مرور التكملة ما بذره الأساسي قبله بثوان، ولا يبقى
   حجز مؤكد ولا جار يعرض عليه زر «ادخل الحصة». */
if ($TQA) {
    $run("DELETE t FROM tutoring_sessions t
           JOIN availability_slots a ON a.id = t.slot_id
          WHERE t.student_id = ? AND t.teacher_id = ? AND a.duration_min = 45",
         [$SID, $TQA]);

    /* والمواعيد لا تحذف إلا إن خلت من أي حجز — لطالب آخر أو لهذا الطالب. */
    $run("DELETE FROM availability_slots
           WHERE teacher_id = ? AND duration_min = 45
             AND NOT EXISTS (SELECT 1 FROM tutoring_sessions t WHERE t.slot_id = availability_slots.id)",
         [$TQA]);
}

if ($clear) {
    $run("UPDATE users SET wishlist = '[]' WHERE id = ?", [$SID]);
    $say('');
    $say($apply ? "حذفت البذرة ($writes عملية)." : "الخطة: $writes عملية حذف. أضف --apply للتنفيذ.");
    exit(0);
}

/* ================================================================
   ١ · المواد التعليمية — ملفات حقيقية على القرص
   ================================================================
   الحجم يقرأ بـ`filesize()` من القرص، وزر التحميل يشير إلى المسار نفسه.
   فصف في الجدول بلا ملف يعطي «—» في خانة الحجم و404 عند الضغط — وهو
   أسوأ من لا شيء. ولذلك تكتب ملفات فعلية بمحتوى يفتح.
*/
$say('٢ · المواد التعليمية (ملفات على القرص + صفوف resource_files)');

if ($apply && !is_dir($files_dir)) @mkdir($files_dir, 0775, true);

/**
 * مقطع mp4 صحيح مصغر — H.264 بلا صوت، ١٦٠×٩٠، ثانية واحدة.
 *
 * محفوظ مرمزا لأنه بيانات ثنائية لا شيفرة: بناء MP4 صالح يحتاج
 * `moov` كاملا بجداول العينات و SPS/PPS للترميز، وكتابتها بيد في
 * سكربت بذر تعني خطأ صامتا آخر كالذي سبق. ولد بـffmpeg مرة:
 *   ffmpeg -f lavfi -i color=c=0x023331:s=160x90:d=1:r=5 \
 *          -c:v libx264 -profile:v baseline -pix_fmt yuv420p \
 *          -movflags +faststart out.mp4
 */
define('TQ_SEED_MP4_B64',
        'AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAM4bW9vdgAAAGxtdmhkAAAAAAAAAAAAAAAAAAAD6AAAA+gAAQAA'
      . 'AQAAAAAAAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
      . 'AAAAAgAAAmN0cmFrAAAAXHRraGQAAAADAAAAAAAAAAAAAAABAAAAAAAAA+gAAAAAAAAAAAAAAAAAAAAAAAEAAAAAAAAAAAAA'
      . 'AAAAAAABAAAAAAAAAAAAAAAAAABAAAAAAKAAAABaAAAAAAAkZWR0cwAAABxlbHN0AAAAAAAAAAEAAAPoAAAAAAABAAAAAAHb'
      . 'bWRpYQAAACBtZGhkAAAAAAAAAAAAAAAAAAAoAAAAKABVxAAAAAAALWhkbHIAAAAAAAAAAHZpZGUAAAAAAAAAAAAAAABWaWRl'
      . 'b0hhbmRsZXIAAAABhm1pbmYAAAAUdm1oZAAAAAEAAAAAAAAAAAAAACRkaW5mAAAAHGRyZWYAAAAAAAAAAQAAAAx1cmwgAAAA'
      . 'AQAAAUZzdGJsAAAAunN0c2QAAAAAAAAAAQAAAKphdmMxAAAAAAAAAAEAAAAAAAAAAAAAAAAAAAAAAKAAWgBIAAAASAAAAAAA'
      . 'AAABFUxhdmM2Mi4xMS4xMDAgbGlieDI2NAAAAAAAAAAAAAAAGP//AAAAMGF2Y0MBQsAe/+EAGGdCwB7ZAo35MBEAAAMAAQAA'
      . 'AwAKDxYuSAEABWjLg8sgAAAAEHBhc3AAAAABAAAAAQAAABRidHJ0AAAAAAAAFyAAAAAAAAAAGHN0dHMAAAAAAAAAAQAAAAUA'
      . 'AAgAAAAAFHN0c3MAAAAAAAAAAQAAAAEAAAAcc3RzYwAAAAAAAAABAAAAAQAAAAUAAAABAAAAKHN0c3oAAAAAAAAAAAAAAAUA'
      . 'AAK6AAAACwAAAAsAAAAKAAAACgAAABRzdGNvAAAAAAAAAAEAAANoAAAAYXVkdGEAAABZbWV0YQAAAAAAAAAhaGRscgAAAAAA'
      . 'AAAAbWRpcmFwcGwAAAAAAAAAAAAAAAAsaWxzdAAAACSpdG9vAAAAHGRhdGEAAAABAAAAAExhdmY2Mi4zLjEwMAAAAAhmcmVl'
      . 'AAAC7G1kYXQAAAJwBgX//2zcRem95tlIt5Ys2CDZI+7veDI2NCAtIGNvcmUgMTY1IHIzMjIyIGIzNTYwNWEgLSBILjI2NC9N'
      . 'UEVHLTQgQVZDIGNvZGVjIC0gQ29weWxlZnQgMjAwMy0yMDI1IC0gaHR0cDovL3d3dy52aWRlb2xhbi5vcmcveDI2NC5odG1s'
      . 'IC0gb3B0aW9uczogY2FiYWM9MCByZWY9MyBkZWJsb2NrPTE6MDowIGFuYWx5c2U9MHgxOjB4MTExIG1lPWhleCBzdWJtZT03'
      . 'IHBzeT0xIHBzeV9yZD0xLjAwOjAuMDAgbWl4ZWRfcmVmPTEgbWVfcmFuZ2U9MTYgY2hyb21hX21lPTEgdHJlbGxpcz0xIDh4'
      . 'OGRjdD0wIGNxbT0wIGRlYWR6b25lPTIxLDExIGZhc3RfcHNraXA9MSBjaHJvbWFfcXBfb2Zmc2V0PS0yIHRocmVhZHM9MyBs'
      . 'b29rYWhlYWRfdGhyZWFkcz0xIHNsaWNlZF90aHJlYWRzPTAgbnI9MCBkZWNpbWF0ZT0xIGludGVybGFjZWQ9MCBibHVyYXlf'
      . 'Y29tcGF0PTAgY29uc3RyYWluZWRfaW50cmE9MCBiZnJhbWVzPTAgd2VpZ2h0cD0wIGtleWludD0yNTAga2V5aW50X21pbj01'
      . 'IHNjZW5lY3V0PTQwIGludHJhX3JlZnJlc2g9MCByY19sb29rYWhlYWQ9NDAgcmM9Y3JmIG1idHJlZT0xIGNyZj0yMy4wIHFj'
      . 'b21wPTAuNjAgcXBtaW49MCBxcG1heD02OSBxcHN0ZXA9NCBpcF9yYXRpbz0xLjQwIGFxPTE6MS4wMACAAAAAQmWIhAR8RigA'
      . 'C/zHAAEDaOAAIlMnJycnJycnJyddddddddddddddddddddddddddddddddddddddddddddddddddeAAAAAdBmjgI+D2AAAAA'
      . 'B0GaVAI+D2AAAAAGQZpgEPB7AAAABkGagD/B7A=='
);

/** أصغر عرض تقديمي صحيح البنية (OOXML) — شريحة واحدة بعنوانها. */
function tq_seed_pptx($title, $subtitle = '')
{
    $esc = static function ($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };
    $P = 'http://schemas.openxmlformats.org/presentationml/2006/main';
    $A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    $R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $X = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $RELS = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

    $parts = array();

    $parts['[Content_Types].xml'] = $X
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'
        . '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>'
        . '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>'
        . '<Override PartName="/ppt/slides/slide1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>'
        . '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
        . '</Types>';

    $parts['_rels/.rels'] = $X . $RELS
        . '<Relationship Id="rId1" Type="' . $R . '/officeDocument" Target="ppt/presentation.xml"/>'
        . '</Relationships>';

    $parts['ppt/presentation.xml'] = $X
        . '<p:presentation xmlns:a="' . $A . '" xmlns:r="' . $R . '" xmlns:p="' . $P . '" rtl="1">'
        . '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>'
        . '<p:sldIdLst><p:sldId id="256" r:id="rId2"/></p:sldIdLst>'
        . '<p:sldSz cx="12192000" cy="6858000"/><p:notesSz cx="6858000" cy="9144000"/>'
        . '</p:presentation>';

    $parts['ppt/_rels/presentation.xml.rels'] = $X . $RELS
        . '<Relationship Id="rId1" Type="' . $R . '/slideMaster" Target="slideMasters/slideMaster1.xml"/>'
        . '<Relationship Id="rId2" Type="' . $R . '/slide" Target="slides/slide1.xml"/>'
        . '<Relationship Id="rId3" Type="' . $R . '/theme" Target="theme/theme1.xml"/>'
        . '</Relationships>';

    /* الشريحة: عنوان ونص تحته. والعربية تكتب كما هي —
       OOXML مستند XML بترميز UTF-8، فلا مسألة ترميز هنا أصلا. */
    $body = '<a:p><a:pPr algn="ctr" rtl="1"/><a:r><a:rPr lang="ar-SA" sz="4000" b="1" dirty="0"/>'
          . '<a:t>' . $esc($title) . '</a:t></a:r></a:p>';
    if ($subtitle !== '') {
        $body .= '<a:p><a:pPr algn="ctr" rtl="1"/><a:r><a:rPr lang="ar-SA" sz="2000" dirty="0"/>'
               . '<a:t>' . $esc($subtitle) . '</a:t></a:r></a:p>';
    }

    $shape = '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Title 1"/>'
        . '<p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr>'
        . '<p:nvPr><p:ph type="ctrTitle"/></p:nvPr></p:nvSpPr>'
        . '<p:spPr><a:xfrm><a:off x="1524000" y="2286000"/><a:ext cx="9144000" cy="2286000"/></a:xfrm></p:spPr>'
        . '<p:txBody><a:bodyPr/><a:lstStyle/>' . $body . '</p:txBody></p:sp>';

    $tree = '<p:spTree>'
        . '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr>'
        . '<p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/>'
        . '<a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>';

    $parts['ppt/slides/slide1.xml'] = $X
        . '<p:sld xmlns:a="' . $A . '" xmlns:r="' . $R . '" xmlns:p="' . $P . '">'
        . '<p:cSld>' . $tree . $shape . '</p:spTree></p:cSld>'
        . '<p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';

    $parts['ppt/slides/_rels/slide1.xml.rels'] = $X . $RELS
        . '<Relationship Id="rId1" Type="' . $R . '/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
        . '</Relationships>';

    $parts['ppt/slideLayouts/slideLayout1.xml'] = $X
        . '<p:sldLayout xmlns:a="' . $A . '" xmlns:r="' . $R . '" xmlns:p="' . $P . '" type="title" preserve="1">'
        . '<p:cSld name="Title Slide">' . $tree . '</p:spTree></p:cSld>'
        . '<p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sldLayout>';

    $parts['ppt/slideLayouts/_rels/slideLayout1.xml.rels'] = $X . $RELS
        . '<Relationship Id="rId1" Type="' . $R . '/slideMaster" Target="../slideMasters/slideMaster1.xml"/>'
        . '</Relationships>';

    $parts['ppt/slideMasters/slideMaster1.xml'] = $X
        . '<p:sldMaster xmlns:a="' . $A . '" xmlns:r="' . $R . '" xmlns:p="' . $P . '">'
        . '<p:cSld>' . $tree . '</p:spTree></p:cSld>'
        . '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2"'
        . ' accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6"'
        . ' hlink="hlink" folHlink="folHlink"/>'
        . '<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst>'
        . '</p:sldMaster>';

    $parts['ppt/slideMasters/_rels/slideMaster1.xml.rels'] = $X . $RELS
        . '<Relationship Id="rId1" Type="' . $R . '/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'
        . '<Relationship Id="rId2" Type="' . $R . '/theme" Target="../theme/theme1.xml"/>'
        . '</Relationships>';

    /* السمة: أصغر ما يقبله العارض — لوحة ألوان تقدر وخطان وتنسيق فارغ. */
    $pal = array('dk2' => '023331', 'lt2' => 'F4F7F6', 'accent1' => '0C786C', 'accent2' => '1B9E8A',
                 'accent3' => '8FBFB5', 'accent4' => 'D9E8E4', 'accent5' => '2F6F63',
                 'accent6' => '5AA79A', 'hlink' => '0C786C', 'folHlink' => '2F6F63');
    $scheme = '<a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1>'
            . '<a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1>';
    foreach ($pal as $k => $v) {
        $scheme .= '<a:' . $k . '><a:srgbClr val="' . $v . '"/></a:' . $k . '>';
    }
    $fill3 = str_repeat('<a:solidFill><a:schemeClr val="phClr"/></a:solidFill>', 3);
    $ln = '';
    foreach (array(6350, 12700, 19050) as $w) {
        $ln .= '<a:ln w="' . $w . '"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>';
    }
    $parts['ppt/theme/theme1.xml'] = $X
        . '<a:theme xmlns:a="' . $A . '" name="Taqdar">'
        . '<a:themeElements>'
        . '<a:clrScheme name="Taqdar">' . $scheme . '</a:clrScheme>'
        . '<a:fontScheme name="Taqdar">'
        . '<a:majorFont><a:latin typeface="Calibri Light"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont>'
        . '<a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont>'
        . '</a:fontScheme>'
        . '<a:fmtScheme name="Taqdar">'
        . '<a:fillStyleLst>' . $fill3 . '</a:fillStyleLst>'
        . '<a:lnStyleLst>' . $ln . '</a:lnStyleLst>'
        . '<a:effectStyleLst>' . str_repeat('<a:effectStyle><a:effectLst/></a:effectStyle>', 3) . '</a:effectStyleLst>'
        . '<a:bgFillStyleLst>' . $fill3 . '</a:bgFillStyleLst>'
        . '</a:fmtScheme></a:themeElements></a:theme>';

    if (!class_exists('ZipArchive')) {
        return '';
    }
    $tmp = tempnam(sys_get_temp_dir(), 'tqpptx');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        return '';
    }
    foreach ($parts as $name => $xml) {
        $zip->addFromString($name, $xml);
    }
    $zip->close();
    $bytes = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $bytes;
}

/**
 * محتوى ملف صغير **يفتحه برنامجه فعلا** لكل امتداد.
 *
 * TQ-SEED-FAKE — كانت الملفات هنا أشكالا لا ملفات، وثلاثة بلاغات جودة
 * خرجت منها وحدها («تصدير PDF» · «تصدير الفيديو» · «تصدير الصوت والعرض»)
 * وكلها سجلت على أنها عطل في التنزيل — والتنزيل سليم: القالب يضع
 * `<a href download>` على ملف ساكن، وأباتشي يخدمه كما هو. المعطوب ما
 * كتب على القرص:
 *
 *   • **mp4** — صندوق `ftyp` وحده يتبعه ٥١٢ صفرا. لا `moov` ولا `mdat`،
 *     أي لا مسار ولا بيانات: «moov atom not found» من ffprobe، و«Failed
 *     to Play» من كل مشغل.
 *   • **mp3** — ترويسة `ID3` بحجم صفر يتبعها ٥١٢ صفرا. لا إطار MPEG
 *     واحدا: «Failed to find two consecutive MPEG audio frames».
 *   • **pptx** — نص عربي عاد بامتداد `.pptx`. وملف OOXML أرشيف ZIP
 *     يبدأ بـ`PK`، فالبرنامج يرفضه قبل أن يقرأ حرفا.
 *   • **pdf** — بنيته صحيحة، ولكن العنوان العربي كتب بايتاته الخام في
 *     سلسلة `Tj` بخط Helvetica. والخطوط القياسية أحادية البايت لاتينية
 *     لا حرف عربي فيها، فيرسم كل بايت حرفا لاتينيا: «ÙˆØ§...».
 *
 * والأربعة تولد هنا صحيحة، وكل واحد محقق بأداته: ffprobe للصوت والمرئي،
 * وقارئ OOXML للعرض، وpdftotext للملف المقروء.
 *
 * والعربية تكتب حيث يمثلها الشكل: في العرض التقديمي نصا في الشريحة
 * (OOXML مستند UTF-8، فلا مسألة ترميز)، وفي PDF عنوانا للمستند
 * (`/Info /Title` بترميز UTF-16BE) — لا في صفحته، فعرضها هناك يوجب
 * تضمين ملف خط لا يحمله المستودع.
 */
$blob = static function ($ext, $title, $course_id = 0, $lesson_id = 0) {

    if ($ext === 'pdf') {
        /* هروب ما يخص PDF من رموز في نص الصفحة. */
        $pesc = static function ($s) {
            return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), (string) $s);
        };
        /* عنوان المستند: UTF-16BE بعلامة ترتيب — بها يقرأ العارض العربية. */
        $utf16 = static function ($s) {
            $b = @iconv('UTF-8', 'UTF-16BE', (string) $s);
            if ($b === false) { $b = ''; }
            return '<' . strtoupper(bin2hex("\xFE\xFF" . $b)) . '>';
        };

        $lines = array(
            'Taqdar - lesson material',
            'Seed sample for course ' . (int) $course_id . ', lesson ' . (int) $lesson_id . '.',
            'taqdaredu.com',
        );
        $stream = "BT\n/F1 20 Tf 60 760 Td (" . $pesc($lines[0]) . ") Tj\n"
                . "0 -34 Td /F1 12 Tf (" . $pesc($lines[1]) . ") Tj\n"
                . "0 -20 Td (" . $pesc($lines[2]) . ") Tj\nET\n"
                . "0.05 0.20 0.19 RG 2 w 60 735 m 535 735 l S\n";

        $objs = array(
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842]"
                . " /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica"
                . " /Encoding /WinAnsiEncoding >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream\nendobj\n",
            "6 0 obj\n<< /Title " . $utf16($title) . " /Producer (Taqdar seed) >>\nendobj\n",
        );

        $pdf = "%PDF-1.4\n";
        $off = array();
        foreach ($objs as $o) { $off[] = strlen($pdf); $pdf .= $o; }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) { $pdf .= sprintf("%010d 00000 n \n", $o); }
        $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R /Info 6 0 R >>\n"
              . "startxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }

    if ($ext === 'png') {
        // بكسل PNG صحيح — يكفي لعرض صورة ولقياس حجم
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    if ($ext === 'mp3') {
        /* ثانية صمت: إطارات MPEG-1 Layer III صحيحة الترويسة —
           ٤٤٫١ كيلوهرتز، ١٢٨ كيلوبت، أحادي. وطول الإطار
           144 × 128000 ÷ 44100 = 417 بايتا، حشوه أصفار: إطار صالح
           يفك إلى صمت. ولذلك تفتحه المشغلات وتقرأ مدته. */
        $hdr   = chr(0xFF) . chr(0xFB) . chr(0x90) . chr(0xC0);
        $frame = $hdr . str_repeat(chr(0), 417 - 4);
        return str_repeat($frame, 38);
    }

    if ($ext === 'mp4') {
        /* مقطع H.264 صحيح: ١٦٠×٩٠، ثانية واحدة، خمسة إطارات بلون تقدر،
           و`moov` قبل `mdat` (faststart) فيبدأ التشغيل قبل اكتمال التنزيل.
           ولد بـffmpeg مرة وحفظ هنا: بناؤه بايتا بايتا في هذا السكربت
           يعني كتابة SPS وPPS بيدين — ومصفوفة أصفار هي التي أوقعتنا هنا. */
        return base64_decode(TQ_SEED_MP4_B64);
    }

    if ($ext === 'pptx') {
        return tq_seed_pptx($title, 'منصة تقدر — مادة عرض تجريبية');
    }

    // ما لا يعرف له شكل: نص عادي — ولا يدعى أنه غير ذلك
    return "منصة تقدر — مادة عرض\n\n" . $title . "\n\n" . str_repeat("سطر تجريبي للعرض.\n", 40);
};


/* لكل كورس مسجل ملفان أو ثلاثة بأنواع مختلفة، لتمتلئ تصفية النوع كلها:
   pdf · video · slide · audio · image. */
$kinds = [
    ['pdf',  'pdf',  'ملخص الوحدة الأولى'],
    ['mp4',  'mp4',  'شرح مصور للدرس'],
    ['pptx', 'pptx', 'عرض تقديمي للوحدة'],
    ['mp3',  'mp3',  'تسميع صوتي'],
    ['png',  'png',  'خريطة ذهنية للوحدة'],
];

$made = 0;
$k = 0;
foreach ($course_ids as $cid) {
    $lessons = $all(
        "SELECT id, title FROM lesson WHERE course_id = ? AND lesson_type != 'quiz'
          ORDER BY id LIMIT 3", [$cid]);
    foreach ($lessons as $ls) {
        [$ext, , $label] = $kinds[$k % count($kinds)];
        $k++;

        $name  = TAG . '_' . $cid . '_' . (int) $ls['id'] . '.' . $ext;
        $title = $label . ' — ' . $ls['title'];

        /* العنوان الكامل لا التسمية وحدها: هو ما يكتب في `resource_files`،
           وهو ما يقرؤه صاحب الملف في شريحة العرض وفي خصائص PDF — فيتطابق
           ما في الشاشة وما في الملف. */
        if ($apply) {
            file_put_contents($files_dir . '/' . $name,
                              $blob($ext, $title, (int) $cid, (int) $ls['id']));
        }

        $run("INSERT INTO resource_files (lesson_id, title, file_name, created_at, updated_at)
              VALUES (?, ?, ?, ?, ?)",
             [(int) $ls['id'], $title, $name, (string) ($now - $k * 3600), (string) $now]);
        $made++;
    }
}
$say('   ' . $made . ' ملفا في ' . count($course_ids) . ' كورسات — بأنواع pdf و mp4 و pptx و mp3 و png');

/* مرفق درس من نوع «رابط خارجي»: التصفية فيها خانة `link` ولا مصدر يملؤها
   إلا `lesson.attachment` حين يكون عنوانا كاملا.

   وهذا **الكتابة الوحيدة هنا في محتوى حقيقي** — عمود في صف درس يملكه معلمه،
   لا صف يخص هذا الطالب. فيقيد بثلاثة شروط:
     • لا يكتب إلا على درس **مرفقه فارغ أصلا** — فلا يمحو مرفقا وضعه معلم.
     • الرابط موسوم (`#` + الوسم) فيعرف عند الحذف.
     • الحذف أعلاه يعيده فارغا كما كان.
   وبلا هذه القيود يترك المرور أثرا في المنهج لا يزول بـ`--clear`. */
$link_lesson = (int) $one(
    "SELECT id FROM lesson
      WHERE course_id IN ($in_courses) AND lesson_type != 'quiz'
        AND (attachment IS NULL OR attachment = '')
      ORDER BY id DESC LIMIT 1");
if ($link_lesson) {
    $run("UPDATE lesson SET attachment = ?, attachment_type = 'link' WHERE id = ?",
         [LINK_ATTACHMENT, $link_lesson]);
    $say('   ومرفق خارجي واحد ليمتلئ تبويب «روابط خارجية»');
} else {
    $say('   (لا درس بمرفق فارغ — تخطي الرابط الخارجي، ولا يمس مرفق معلم)');
}

/* ================================================================
   ٢ · المفضلة — الأقسام الثلاثة معا
   ================================================================ */
$say('٣ · المفضلة (دروس · ملفات · كورسات)');

$run("CREATE TABLE IF NOT EXISTS `tq_favourites` (
        `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id`    INT(10) UNSIGNED NOT NULL,
        `kind`       VARCHAR(16)      NOT NULL,
        `item_id`    INT(10) UNSIGNED NOT NULL,
        `created_at` INT(11)          NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_user_kind_item` (`user_id`,`kind`,`item_id`),
        KEY `ix_user_kind` (`user_id`,`kind`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$fav_lessons = $all(
    "SELECT id FROM lesson WHERE course_id IN ($in_courses) AND lesson_type != 'quiz'
      ORDER BY id LIMIT 6");
$i = 0;
foreach ($fav_lessons as $ls) {
    $run("INSERT IGNORE INTO tq_favourites (user_id, kind, item_id, created_at) VALUES (?, 'lesson', ?, ?)",
         [$SID, (int) $ls['id'], $now - (++$i) * 900]);
}
$say('   ' . count($fav_lessons) . ' دروس');

$fav_files = $all(
    "SELECT rf.id FROM resource_files rf JOIN lesson l ON l.id = rf.lesson_id
      WHERE l.course_id IN ($in_courses) AND rf.file_name LIKE ?
      ORDER BY rf.id LIMIT 4", [TAG . '%']);
foreach ($fav_files as $f) {
    $run("INSERT IGNORE INTO tq_favourites (user_id, kind, item_id, created_at) VALUES (?, 'material', ?, ?)",
         [$SID, (int) $f['id'], $now - (++$i) * 900]);
}
$say('   ' . count($fav_files) . ' ملفات');
if (!$apply && !$fav_files) $say('   (الملفات تعد بعد كتابتها — الرقم يظهر عند --apply)');

/* الكورسات في `users.wishlist` لا في الجدول — مصدرها Academy ولا ينازع.
   وتؤخذ من الكورسات المسجلة لأن الشاشة تعرض تقدم الطالب في كل مفضل. */
$wish = array_slice($course_ids, 0, 3);
$run("UPDATE users SET wishlist = ? WHERE id = ?", [json_encode(array_values($wish)), $SID]);
$say('   ' . count($wish) . ' كورسات في users.wishlist');

/* ================================================================
   ٣ · الشهادات — امتحان محطة ومحاولة ناجحة عليه
   ================================================================
   شاشة الشهادات تقرأ `assessments.type = 'exam'` و`attempts.passed = 1`
   وحدهما. وفي القاعدة عشرون `quiz` واثنا عشر `homework` ولا `exam` واحدا،
   فالشاشة فارغة أبدا مهما أتقن الطالب.
*/
$say('٤ · الشهادات (امتحانات محطات + محاولات ناجحة وراسبة)');

$miles = $all(
    "SELECT m.id, m.title, m.path_id FROM milestones m
       JOIN paths p ON p.id = m.path_id
      WHERE p.course_id IN ($in_courses)
      ORDER BY p.course_id, m.`order` LIMIT 6");

/* ثلاث ناجحة بدرجات مختلفة، وواحدة راسبة لا تصدر شهادة — فيرى المجرب
   أن الشرط `passed = 1` يعمل فعلا لا أن كل محاولة تعطي شهادة. */
$scores = [92, 85, 78, 64];
$n = 0;
foreach ($miles as $m) {
    if ($n >= count($scores)) break;
    $score  = $scores[$n];
    $passed = $score >= 70 ? 1 : 0;
    $when   = $now - (($n + 1) * 6 * $DAY);

    $run("INSERT INTO assessments (type, lesson_id, milestone_id, path_id, pass_mark, time_limit_sec)
          VALUES ('exam', NULL, ?, ?, 70, 2700)",
         [(int) $m['id'], (int) $m['path_id']]);
    $aid = (int) $pdo->lastInsertId();

    if ($apply && $aid) {
        $run("INSERT INTO attempts (assessment_id, student_id, score, passed, attempt_no, started_at, submitted_at)
              VALUES (?, ?, ?, ?, 1, ?, ?)",
             [$aid, $SID, $score, $passed, $dt($when - 2400), $dt($when)]);
        // المحطة تشير إلى امتحانها، فيقرأ النظام بوابتها من موضعها الصحيح
        $run("UPDATE milestones SET checkpoint_assessment_id = ? WHERE id = ?", [$aid, (int) $m['id']]);
    } else {
        $writes += 2;
    }
    $n++;
}
$say('   ' . $n . ' امتحان محطة — ' . count(array_filter($scores, static function ($s) { return $s >= 70; }))
     . ' ناجحة تصدر شهادات وواحدة راسبة لا تصدر');

/* ================================================================
   ٤ · حصص بالطلب — روابط اللقاء ومسار التأكيد
   ================================================================ */
$say('٥ · حصص بالطلب (روابط لقاء + طلب معلق لحساب المعلم التجريبي)');

/* المؤكدة والجارية لها روابط: الشاشة لا تعرض زر «ادخل الحصة» إلا بها.
   والنطاقات من القائمة البيضاء في `Taqdar_sessions_model::MEET_HOSTS` —
   رابط خارجها يرفضه النموذج، فبذره هنا يعطي بيانات لا تنتجها الواجهة. */
$books = $all(
    "SELECT id, status FROM tutoring_sessions
      WHERE student_id = ? AND status IN ('confirmed','live') ORDER BY id", [$SID]);
$urls = [
    'https://meet.google.com/tqd-demo-001',
    'https://zoom.us/j/9911223344',
    'https://teams.microsoft.com/l/meetup-join/tqd-demo',
];
$j = 0;
foreach ($books as $b) {
    $run("UPDATE tutoring_sessions SET meet_url = ? WHERE id = ? AND student_id = ?",
         [$urls[$j % count($urls)], (int) $b['id'], $SID]);
    $j++;
}
$say('   ' . count($books) . ' حجزا مؤكدا/جاريا بروابط Meet و Zoom و Teams');

/* طلب معلق يملكه حساب المعلم التجريبي: بدونه لا يمكن تجربة شاشة
   «طلبات الحجز» ولا مسار «تأكيد وإرسال الرابط» من حساب يعرف مروره. */
if ($TQA) {
    $slot_at = $dt($now + 2 * $DAY + 3600);
    $run("INSERT INTO availability_slots (teacher_id, starts_at, duration_min, status)
          VALUES (?, ?, 45, 'held')", [$TQA, $slot_at]);
    $slot = (int) $pdo->lastInsertId();

    if ($apply && $slot) {
        $run("INSERT INTO tutoring_sessions (slot_id, student_id, teacher_id, status)
              VALUES (?, ?, ?, 'requested')", [$slot, $SID, $TQA]);
    } else {
        $writes++;
    }

    /* وموعدان مفتوحان له أيضا، ليظهر في «معلمون متاحون الآن» فيطلب منه. */
    for ($d = 1; $d <= 2; $d++) {
        $run("INSERT INTO availability_slots (teacher_id, starts_at, duration_min, status)
              VALUES (?, ?, 45, 'open')", [$TQA, $dt($now + $d * $DAY + 5 * 3600)]);
    }
    $say('   طلب معلق + موعدان مفتوحان لحساب teacher.test');
} else {
    $say('   (لا حساب teacher.test — تخطي مسار التأكيد)');
}

/* ================================================================ */
$say('');
$say(str_repeat('-', 62));
if (!$apply) {
    $say("الخطة: $writes عملية كتابة. أضف --apply للتنفيذ.");
    $say('');
    exit(0);
}
$say("تمت البذرة ($writes عملية).");
$say('');
$say('للتجربة:');
$say('  /student/materials    — ملفات بأنواعها وأحجامها وأزرار تحميل تعمل');
$say('  /student/favourites   — الأقسام الثلاثة ممتلئة، والقلب يزيل فعلا');
$say('  /student/certificates — شهادات بدرجاتها ورموز تحققها');
$say('  /student/on-demand    — «ادخل الحصة» على المؤكدة والجارية');
$say('  /teacher/sessions     — طلب ينتظر تأكيدا برابط لقاء (teacher.test)');
$say('');
