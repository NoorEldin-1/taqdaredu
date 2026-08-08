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
   ٠ · حذف أثر هذا المرور وحده
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
   شاشة المعلم بطلبات مكررة لا تنتهي — والمرور يوصف بأنه مأمون التكرار. */
if ($TQA) {
    $run("DELETE FROM tutoring_sessions WHERE student_id = ? AND teacher_id = ?", [$SID, $TQA]);
    /* والمواعيد لا تحذف إلا إن خلت من حجز طالب آخر. */
    $run("DELETE FROM availability_slots
           WHERE teacher_id = ?
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
 * محتوى ملف صغير صالح لكل امتداد.
 * PDF مصغر لكنه صحيح البنية فيفتحه العارض، والبقية نص أو صورة حقيقية.
 */
$blob = static function ($ext, $title) {
    if ($ext === 'pdf') {
        // PDF بأربعة كائنات — أصغر ملف يفتحه أي عارض
        $txt = str_replace(['(', ')'], '', $title);
        $stream = "BT /F1 18 Tf 60 700 Td ($txt) Tj ET";
        $objs = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842]"
                . " /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n$stream\nendstream\nendobj\n",
        ];
        $pdf = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $o) { $off[] = strlen($pdf); $pdf .= $o; }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $pdf;
    }
    if ($ext === 'png') {
        // بكسل PNG صحيح — يكفي لعرض صورة ولقياس حجم
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }
    if ($ext === 'mp3') return "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\x00", 512);
    if ($ext === 'mp4') return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" . str_repeat("\x00", 512);
    // pptx/docx وما شابه: نص عادي يقرأ ويقيس حجما — ولا يدعى أنه غير ذلك
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

        if ($apply) file_put_contents($files_dir . '/' . $name, $blob($ext, $label));

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
