<?php
/**
 * بذرة عرض لبوابة المعلم — بيانات تمر بكل حالة تعرضها الشاشات الثمان.
 *
 * الشاشات تعرض حالات فارغة صادقة حين لا بيانات، وهو الصواب — لكن حساب
 * المعلم التجريبي لا كورس مسند إليه أصلا، فكل شاشاته فارغة، ولا سبيل إلى
 * فحص زر واحد فيها. هذا المرور يولد الحالات كلها لحساب واحد.
 *
 * ما يغطى، شاشة شاشة:
 *   اللوحة       · طلاب متعثرون بالأسباب الثلاثة (انقطاع · رسوب · بطء تقدم)
 *                 · دروس عالية الفشل · حصص قادمة · أرباح شهر
 *   كورساتي      · الحالات الأربع (منشور · قيد المراجعة · مسودة · خاص)
 *                 · كورس بلا دروس · كورس بلا مسجلين
 *   رفع الدروس   · دروس مرئية ونصية · منشور وقيد مراجعة ومسودة · مجاني ومدفوع
 *   بنك الأسئلة  · اختبار موضوعي بالكامل · اختبار فيه مقالي وإجابة قصيرة
 *                 · أسئلة مربوطة بأهداف وأخرى بلا هدف
 *   التصحيح      · صف ينتظر (أقدمه أسبوعان) · معتمدات سابقة · محاولة لم تسلم
 *   طلابي        · متقدم · متوسط · لم يبدأ · منقطع 6 أيام · منقطع 21 يوما
 *   الحصص        · فترات مفتوحة ومعلقة ومحجوزة · طلبات بالحالات كلها
 *   المحفظة      · معلق يتحرر بعد أيام · متاح · محول · مسترد · محتجز ضريبة
 *                 · طلبات سحب معلقة ومحولة وملغاة
 *
 * ونقطة الالتقاء مقصودة: المعلم يضم إلى الكورسين اللذين يدرسهما الطالب
 * التجريبي ويتابعهما ولي أمره، فتلتقي البوابات الثلاث على بيانات واحدة
 * ويمكن تتبع المسار من طرفه إلى طرفه: تسليم الطالب ← صف تصحيح المعلم ←
 * اعتماده ← ظهور الدرجة عند الطالب وولي أمره.
 *
 * الاستعمال (من جذر الموقع):
 *     php scripts/seed_demo_teacher.php                 # عرض الخطة، بلا كتابة
 *     php scripts/seed_demo_teacher.php --apply         # التنفيذ
 *     php scripts/seed_demo_teacher.php --apply --clear # حذف البذرة وحدها
 *     php scripts/seed_demo_teacher.php --apply --teacher=289
 *
 * ── ما لا يمسه هذا المرور ────────────────────────────────────────────────
 * كل حذف أو تعديل هنا مقيد بأثر هذا المعلم وحده، والسكربت يرفض العمل على
 * حساب ليس حساب عرض إلا بـ`--force` صراحة — لأنه يشغل على قاعدة الإنتاج
 * أيضا، وفيها معلمون حقيقيون بمال حقيقي في محافظهم.
 *
 *   • الكورسات المبذورة موسومة في `meta_keywords` بعلامة هذا السكربت،
 *     ولا يحذف إلا الموسوم.
 *   • ضم المعلم إلى كورسات قائمة يضيف معرفه إلى `course.user_id` ولا
 *     يزيح أحدا، والحذف يزيل معرفه وحده.
 *   • المدفوعات المبذورة موسومة في `transaction_id`.
 *   • دفتر المحفظة لا يكتب يدويا إلا قيود بيع مسترد (لا صف دفع له)،
 *     وبقيته يبنيها `Taqdar_wallet_model` من `payment` عند أول عرض —
 *     فالدفتر يبقى نتاج منطق واحد لا منطقين.
 *   • لا ينشئ مستخدمين: يستعمل حسابات العرض القائمة وحدها.
 *
 * مأمون التكرار: يمسح ما بذره سابقا ثم يكتبه من جديد.
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
$force = in_array('--force', $argv, true);
$want  = 0;
foreach ($argv as $a) {
    if (strpos($a, '--teacher=') === 0) $want = (int) substr($a, 10);
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
$insert = function ($sql, $args = []) use ($pdo, $apply, &$writes) {
    $writes++;
    if (!$apply) return 0;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return (int) $pdo->lastInsertId();
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
$say = static function ($s = '') { echo $s, "\n"; };
$hms = static function ($minutes) { return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60); };
$dt  = static function ($ts) { return date('Y-m-d H:i:s', $ts); };

$now  = time();
$DAY  = 86400;

/** علامة هذا السكربت — بها وحدها يعرف ما بذره فيحذفه. */
const TQ_MARK = 'tq-seed-teacher';

/* ---------- المعلم المستهدف ---------- */
$TID = $want ?: (int) $one("SELECT id FROM users WHERE email = ? LIMIT 1", ['teacher.test@taqdaredu.com']);
if (!$TID) exit("لا حساب معلم عرض. مرر --teacher=<معرف الحساب>\n");

$who = $all("SELECT id, email, first_name, last_name, role_id, is_instructor, status FROM users WHERE id = ?", [$TID]);
if (!$who) exit("لا مستخدم بالمعرف $TID\n");
$who = $who[0];

if ((int) $who['role_id'] === 1) {
    exit("المعرف $TID حساب إدارة لا معلم. أوقف.\n");
}

/* حارس الإنتاج: لا يبذر على معلم حقيقي إلا بطلب صريح. حسابات العرض
   وحدها تعرف بنطاقها، وما عداها قد يكون معلما له طلاب ومال. */
$is_demo = (strpos($who['email'], '@taqdaredu.local') !== false)
        || ($who['email'] === 'teacher.test@taqdaredu.com');
if (!$is_demo && !$force) {
    exit("الحساب {$who['email']} ليس حساب عرض. أعد الأمر مع --force إن كنت تقصده فعلا.\n");
}

$name = trim($who['first_name'] . ' ' . $who['last_name']);
$say("المعلم: #$TID — $name <{$who['email']}>");
$say($apply ? 'الوضع: تنفيذ' : 'الوضع: عرض الخطة فقط (أضف --apply للتنفيذ)');
$say(str_repeat('-', 62));

/* حساب المعلم لا بد أن يكون معلما مفعلا، وإلا لم تفتح بوابته أصلا. */
if ((int) $who['is_instructor'] !== 1 || (int) $who['status'] !== 1) {
    $run("UPDATE users SET is_instructor = 1, status = 1 WHERE id = ?", [$TID]);
    $say('· فعل حساب المعلم (is_instructor / status)');
}

/* ---------- طلاب العرض: تستعمل القائمة ولا ينشأ مستخدم ---------- */
$student_emails = [
    'student.test@taqdaredu.com',
    'seed-child1@taqdaredu.local',
    'seed-child2@taqdaredu.local',
    'seed-child3@taqdaredu.local',
    'seed-child4@taqdaredu.local',
    'seed-child5@taqdaredu.local',
];
$students = [];
foreach ($student_emails as $em) {
    $row = $all("SELECT id, first_name, last_name FROM users WHERE email = ? LIMIT 1", [$em]);
    if ($row) $students[] = ['id' => (int) $row[0]['id'], 'name' => trim($row[0]['first_name'] . ' ' . $row[0]['last_name'])];
}
if (count($students) < 3) {
    exit("لا يكفي من طلاب العرض (وجد " . count($students) . "). شغل seed_demo_student.php وseed_demo_parent.php أولا.\n");
}
$say('طلاب العرض المستعملون: ' . count($students));

/* =====================================================================
   الحذف — ما بذره هذا السكربت وحده
   ===================================================================== */

$seeded_courses = array_map(
    static function ($r) { return (int) $r['id']; },
    $all("SELECT id FROM course WHERE creator = ? AND meta_keywords = ?", [$TID, TQ_MARK])
);

if ($seeded_courses) {
    $in = implode(',', $seeded_courses);
    $lessons = array_map(
        static function ($r) { return (int) $r['id']; },
        $all("SELECT id FROM lesson WHERE course_id IN ($in)")
    );

    if ($lessons) {
        $lin = implode(',', $lessons);
        $run("DELETE FROM attempts WHERE assessment_id IN (SELECT id FROM assessments WHERE lesson_id IN ($lin))");
        $run("DELETE FROM assessments WHERE lesson_id IN ($lin)");
        $run("DELETE FROM quiz_results WHERE quiz_id IN ($lin)");
        $run("DELETE FROM question WHERE quiz_id IN ($lin)");
        $run("DELETE FROM objectives WHERE lesson_id IN ($lin)");
        $run("DELETE FROM lesson WHERE course_id IN ($in)");
    }
    $run("DELETE FROM section WHERE course_id IN ($in)");
    $run("DELETE FROM watch_histories WHERE course_id IN ($in)");
    $run("DELETE FROM enrol WHERE course_id IN ($in)");
    $run("DELETE FROM payment WHERE course_id IN ($in)");
    $run("DELETE FROM course WHERE id IN ($in)");
    $say('· حذف ' . count($seeded_courses) . ' كورسا مبذورا وكل ما تعلق به');
}

/* الضم إلى كورسات قائمة يزال بإخراج معرف المعلم من القائمة وحده. */
foreach ($all("SELECT id, user_id FROM course WHERE FIND_IN_SET(?, user_id) > 0 AND creator <> ?", [$TID, $TID]) as $c) {
    $ids = array_values(array_filter(array_map('trim', explode(',', (string) $c['user_id'])),
        static function ($v) use ($TID) { return $v !== '' && (int) $v !== $TID; }));
    $run("UPDATE course SET user_id = ?, multi_instructor = ? WHERE id = ?",
        [implode(',', $ids), count($ids) > 1 ? 1 : 0, (int) $c['id']]);
    $say('· أخرج المعلم من الكورس #' . (int) $c['id']);
}

$run("DELETE FROM payment WHERE transaction_id = ?", [TQ_MARK]);
$run("DELETE FROM tutoring_sessions WHERE teacher_id = ?", [$TID]);
$run("DELETE FROM availability_slots WHERE teacher_id = ?", [$TID]);
$run("DELETE FROM payout WHERE user_id = ?", [$TID]);
$run("DELETE FROM notifications WHERE to_user = ? AND description LIKE ?", [$TID, '%' . TQ_MARK . '%']);

/* الرسائل تعرف بعلامتها لا بخيطها: الخيط قد يكون قائما من قبل وفيه رسائل
   حقيقية بين المعلم وطالبه، وحذفه كله يمحو محادثة لم يكتبها هذا السكربت. */
$run("DELETE FROM message WHERE message LIKE ?", ['%' . TQ_MARK . '%']);
/* والخيط الذي أنشأه هذا السكربت لا يحذف إلا إن لم تبق فيه رسالة. */
$run("DELETE FROM message_thread
       WHERE message_thread_code LIKE ?
         AND NOT EXISTS (SELECT 1 FROM message m
                          WHERE m.message_thread_code = message_thread.message_thread_code)",
    ['tqseedt%']);

$wid_old = (int) $one("SELECT id FROM wallets WHERE owner_user_id = ?", [$TID]);
if ($wid_old) {
    $run("DELETE FROM wallet_entries WHERE wallet_id = ?", [$wid_old]);
    $run("UPDATE wallets SET balance_available = 0, balance_pending = 0, balance_locked = 0 WHERE id = ?", [$wid_old]);
    $say('· أفرغ دفتر المحفظة ليعاد بناؤه من المدفوعات');
}

if ($clear) {
    $say(str_repeat('-', 62));
    $say($apply ? "حذفت البذرة. عدد عمليات الكتابة: $writes" : "خطة الحذف: $writes عملية. أضف --apply للتنفيذ.");
    exit(0);
}

/* =====================================================================
   الكورسات
   ===================================================================== */

$cat = (int) $one("SELECT id FROM category WHERE parent <> 0 ORDER BY id LIMIT 1");
if (!$cat) $cat = (int) $one("SELECT id FROM category ORDER BY id LIMIT 1");

/**
 * الكورسات الخمسة: الحالات الأربع كلها، ومنها كورس بلا دروس وكورس بلا
 * مسجلين — فتصفية الحالة في الشاشة تجرب على بيانات لا على فراغ.
 */
$course_plan = [
    ['key' => 'math5',  'title' => 'الرياضيات — الصف الخامس الابتدائي', 'status' => 'active',
     'level' => 'intermediate', 'price' => 120,
     'short' => 'الكسور والأعداد العشرية والهندسة للصف الخامس، درسا درسا.'],
    ['key' => 'digi5',  'title' => 'المهارات الرقمية — الصف الخامس الابتدائي', 'status' => 'active',
     'level' => 'beginner', 'price' => 90,
     'short' => 'من التعامل مع الملفات إلى أول برنامج بالسحب والإفلات.'],
    ['key' => 'eng5',   'title' => 'اللغة الإنجليزية — الصف الخامس الابتدائي', 'status' => 'pending',
     'level' => 'beginner', 'price' => 100,
     'short' => 'كورس ينتظر مراجعة الإدارة قبل نشره.'],
    ['key' => 'art5',   'title' => 'التربية الفنية — مسودة أولى', 'status' => 'draft',
     'level' => 'beginner', 'price' => 0,
     'short' => 'مسودة لم يرفع إليها درس بعد.'],
    ['key' => 'club',   'title' => 'نادي البرمجة — مجموعة مغلقة', 'status' => 'private',
     'level' => 'advanced', 'price' => 200,
     'short' => 'مجموعة مغلقة لطلاب مختارين.'],
];

$C = [];
foreach ($course_plan as $i => $p) {
    $C[$p['key']] = $insert(
        "INSERT INTO course
            (title, short_description, description, outcomes, faqs, language, category_id, sub_category_id,
             section, requirements, price, discount_flag, discounted_price, level, user_id, thumbnail,
             date_added, last_modified, course_type, is_top_course, is_admin, status,
             meta_keywords, meta_description, is_free_course, multi_instructor, enable_drip_content,
             creator, expiry_period)
         VALUES (?,?,?,?,'','arabic',?,?,'[]','[]',?,0,0,?,?,'',?,?,'general',0,0,?,?,?,0,0,0,?,0)",
        [
            $p['title'], $p['short'], $p['short'], '[]',
            $cat, $cat,
            $p['price'], $p['level'], (string) $TID,
            $now - (20 - $i * 3) * $DAY, $now - $i * $DAY,
            $p['status'], TQ_MARK, $p['short'],
            $TID,
        ]
    );
    $say("· كورس [{$p['status']}] {$p['title']}");
}

/**
 * الضم إلى كورسات الطالب وولي الأمر التجريبيين.
 *
 * هذه هي نقطة الالتقاء: ما لم يكن المعلم معلم الكورس الذي يدرسه الطالب،
 * بقيت البوابات الثلاث تتحدث عن بيانات ثلاث ولم يفحص مسار واحد كاملا.
 */
$demo_student_id = $students[0]['id'];
$join_courses = $all(
    "SELECT DISTINCT c.id, c.title, c.user_id
       FROM course c JOIN enrol e ON e.course_id = c.id
      WHERE e.user_id = ? AND c.creator <> ? AND FIND_IN_SET(?, c.user_id) = 0
      ORDER BY c.id LIMIT 2",
    [$demo_student_id, $TID, $TID]
);
foreach ($join_courses as $jc) {
    $list = trim((string) $jc['user_id']);
    $list = $list === '' ? (string) $TID : $list . ',' . $TID;
    $run("UPDATE course SET user_id = ?, multi_instructor = 1 WHERE id = ?", [$list, (int) $jc['id']]);
    $say('· ضم المعلم إلى الكورس القائم: ' . $jc['title']);
}

if (!$apply) {
    /* في وضع العرض لا معرفات حقيقية، فما بعده يوصف ولا يحسب صفا صفا. */
    $say(str_repeat('-', 62));
    $say("خطة البذر: $writes عملية كتابة حتى الآن، وما بعدها يتوقف على معرفات تنشأ عند التنفيذ.");
    $say('أضف --apply لتنفيذ المرور كاملا.');
    exit(0);
}

/* =====================================================================
   الأقسام والدروس
   ===================================================================== */

$S = [];
$S['math5_a'] = $insert("INSERT INTO section (title, course_id, `order`) VALUES (?,?,?)", ['الوحدة الأولى: الكسور', $C['math5'], 1]);
$S['math5_b'] = $insert("INSERT INTO section (title, course_id, `order`) VALUES (?,?,?)", ['الوحدة الثانية: الأعداد العشرية', $C['math5'], 2]);
$S['digi5_a'] = $insert("INSERT INTO section (title, course_id, `order`) VALUES (?,?,?)", ['الوحدة الأولى: الملفات والمجلدات', $C['digi5'], 1]);
$S['eng5_a']  = $insert("INSERT INTO section (title, course_id, `order`) VALUES (?,?,?)", ['Unit 1: Greetings', $C['eng5'], 1]);
$S['club_a']  = $insert("INSERT INTO section (title, course_id, `order`) VALUES (?,?,?)", ['لقاءات النادي', $C['club'], 1]);

/**
 * كل درس معه هدفه: السؤال غير المربوط بهدف عديم القيمة تشخيصيا، وشاشة
 * بنك الأسئلة تعرض «بلا هدف» بصدق لمن لا هدف له — فيبذر الوجهان معا.
 */
$lesson_plan = [
    // [مفتاح, كورس, قسم, عنوان, دقائق, نوع, حالة, مجاني, ترتيب, هدف]
    ['m1', 'math5', 'math5_a', 'مفهوم الكسر وأجزاء الوحدة',            10, 'video', 'published', 1, 1, 'أن يمثل الطالب الكسر بالرسم ويقرأه'],
    ['m2', 'math5', 'math5_a', 'الكسور المتكافئة',                      12, 'video', 'published', 0, 2, 'أن يولد الطالب كسورا مكافئة لكسر معطى'],
    ['m3', 'math5', 'math5_a', 'مقارنة الكسور وترتيبها',                11, 'video', 'published', 0, 3, 'أن يقارن الطالب بين كسرين مختلفي المقام'],
    ['m4', 'math5', 'math5_b', 'قراءة العدد العشري وكتابته',            9,  'video', 'published', 0, 5, 'أن يقرأ الطالب العدد العشري إلى المنزلة الثالثة'],
    ['m5', 'math5', 'math5_b', 'جمع الأعداد العشرية وطرحها',            14, 'video', 'published', 0, 6, 'أن يجمع الطالب عددين عشريين بمحاذاة الفاصلة'],
    ['m6', 'math5', 'math5_b', 'ملخص الوحدة الثانية',                   8,  'text',  'published', 0, 7, 'أن يلخص الطالب قواعد الوحدة في صفحة واحدة'],
    ['m7', 'math5', 'math5_b', 'الضرب في الأعداد العشرية',              13, 'video', 'review',    0, 8, 'أن يضرب الطالب عددا عشريا في عدد صحيح'],
    ['m8', 'math5', 'math5_b', 'القسمة على عدد عشري — مسودة',           15, 'video', 'draft',     0, 9, 'أن يقسم الطالب على عدد عشري بتحويل المقسوم عليه'],
    ['d1', 'digi5', 'digi5_a', 'ما الملف وما المجلد',                   9,  'video', 'published', 1, 1, 'أن يميز الطالب بين الملف والمجلد'],
    ['d2', 'digi5', 'digi5_a', 'حفظ الملف وتسميته',                     10, 'video', 'published', 0, 2, 'أن يحفظ الطالب ملفا باسم دال وامتداد صحيح'],
    ['d3', 'digi5', 'digi5_a', 'البحث عن ملف ضائع',                     8,  'text',  'published', 0, 3, 'أن يستعمل الطالب البحث للوصول إلى ملف'],
    ['e1', 'eng5',  'eng5_a',  'Hello and Goodbye',                     10, 'video', 'review',    1, 1, 'أن يحيي الطالب ويودع بالإنجليزية'],
    ['e2', 'eng5',  'eng5_a',  'How are you?',                          9,  'video', 'draft',     0, 2, 'أن يسأل الطالب عن الحال ويجيب'],
    ['k1', 'club',  'club_a',  'أول برنامج بالسحب والإفلات',            12, 'video', 'published', 0, 1, 'أن يبني الطالب برنامجا بسيطا بالكتل'],
];

$L = [];
foreach ($lesson_plan as $p) {
    [$k, $ck, $sk, $title, $mins, $type, $status, $free, $order, $objective] = $p;
    $L[$k] = $insert(
        "INSERT INTO lesson
            (title, duration, course_id, section_id, video_type, video_url, date_added, last_modified,
             lesson_type, attachment, attachment_type, summary, is_free, `order`, tq_status)
         VALUES (?,?,?,?,?,?,?,?,?,'','',?,?,?,?)",
        [
            $title, $hms($mins), $C[$ck], $S[$sk],
            $type === 'video' ? 'youtube' : '',
            $type === 'video' ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : '',
            $now - (18 - $order) * $DAY, $now - $order * $DAY,
            $type, $objective, $free, $order, $status,
        ]
    );
    $insert("INSERT INTO objectives (lesson_id, text, at_second) VALUES (?,?,0)", [$L[$k], $objective]);
}
$say('· ' . count($L) . ' درسا بأهدافها');

/* ---------- الاختبارات ---------- */
$quiz_plan = [
    ['qa', 'math5', 'math5_a', 'اختبار الوحدة الأولى: الكسور',       4],
    ['qb', 'math5', 'math5_b', 'اختبار الوحدة الثانية: الأعداد العشرية', 10],
    ['qc', 'digi5', 'digi5_a', 'اختبار الملفات والمجلدات',           4],
];
foreach ($quiz_plan as $p) {
    [$k, $ck, $sk, $title, $order] = $p;
    $L[$k] = $insert(
        "INSERT INTO lesson
            (title, duration, course_id, section_id, video_type, video_url, date_added, last_modified,
             lesson_type, attachment, attachment_type, summary, is_free, `order`, tq_status, quiz_attempt)
         VALUES (?,?,?,?,'','',?,?,'quiz','','',?,0,?,'published',3)",
        [$title, $hms(15), $C[$ck], $S[$sk], $now - 15 * $DAY, $now - $order * $DAY,
         'اختبار قصير يقيس أهداف الوحدة.', $order]
    );
}

/**
 * الأسئلة.
 *
 * `qa` و`qc` موضوعيان بالكامل — درجتهما تظهر لصاحبها بلا انتظار.
 * و`qb` فيه مقالي وإجابة قصيرة، فدرجته محجوبة حتى يعتمدها المعلم:
 * وهذا هو الفرق الذي لا يرى إلا ببيانات فيه الحالتان معا.
 */
$q_math_a = [
    ['ما الكسر الذي يمثل نصف الوحدة؟', 'radio', ['1/2', '1/3', '2/3', '3/4'], ['1/2']],
    ['أي الكسور يكافئ 2/4؟', 'radio', ['1/2', '2/3', '3/4', '1/4'], ['1/2']],
    ['أيهما أكبر: 3/5 أم 2/5؟', 'radio', ['3/5', '2/5', 'متساويان', 'لا يمكن'], ['3/5']],
    ['اختر كل ما يكافئ 1/3', 'checkbox', ['2/6', '3/9', '1/4', '4/12'], ['2/6', '3/9', '4/12']],
    ['كم سدسا في الوحدة الكاملة؟', 'radio', ['6', '3', '12', '1'], ['6']],
];
$q_math_b = [
    ['اقرأ العدد 3.05', 'radio', ['ثلاثة وخمسة من مئة', 'ثلاثة وخمسة من عشرة', 'ثلاثمئة وخمسة', 'ثلاثة وخمسون'], ['ثلاثة وخمسة من مئة']],
    ['ناتج 1.2 + 3.45', 'radio', ['4.65', '4.57', '3.65', '4.15'], ['4.65']],
    ['اختر كل عدد أكبر من 2.5', 'checkbox', ['2.51', '2.05', '3.10', '2.49'], ['2.51', '3.10']],
    ['اكتب بكلماتك: لماذا نحاذي الفاصلة عند الجمع؟', 'essay', [], []],
    ['ما منزلة الرقم 7 في العدد 4.078؟', 'text', [], ['المئات من الجزء العشري']],
];
$q_digi = [
    ['ما الذي يحوي ملفات أخرى؟', 'radio', ['المجلد', 'الملف', 'الامتداد', 'الاختصار'], ['المجلد']],
    ['امتداد ملف الصورة من بين التالي', 'radio', ['png', 'docx', 'mp3', 'exe'], ['png']],
    ['اختر ما يعد اسما دالا للملف', 'checkbox', ['بحث-العلوم-الوحدة1', 'ملف جديد 2', 'واجب-الرياضيات-الاثنين', 'aaa'], ['بحث-العلوم-الوحدة1', 'واجب-الرياضيات-الاثنين']],
    ['أين تبحث عن ملف نسيت مكانه؟', 'radio', ['شريط البحث', 'سلة المحذوفات', 'لوحة التحكم', 'المتصفح'], ['شريط البحث']],
];

$put_questions = function ($quiz_lesson, $rows, $objective_lesson = 0) use ($insert, $all) {
    $obj = 0;
    if ($objective_lesson) {
        $obj = (int) ($all("SELECT id FROM objectives WHERE lesson_id = ? LIMIT 1", [$objective_lesson])[0]['id'] ?? 0);
    }
    $i = 0;
    foreach ($rows as $r) {
        $i++;
        [$title, $type, $options, $correct] = $r;
        $insert(
            "INSERT INTO question (quiz_id, objective_id, title, type, number_of_options, options, correct_answers, `order`)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                $quiz_lesson,
                /* السؤالان الأخيران في كل اختبار بلا هدف عمدا: الشاشة تعد
                   بأن تقول «بلا هدف» بصدق، ولا يفحص وعد بلا مثال. */
                ($obj && $i <= count($rows) - 2) ? $obj : null,
                $title, $type, count($options),
                json_encode(array_values($options), JSON_UNESCAPED_UNICODE),
                json_encode(array_values($correct), JSON_UNESCAPED_UNICODE),
                $i,
            ]
        );
    }
};
$put_questions($L['qa'], $q_math_a, $L['m1']);
$put_questions($L['qb'], $q_math_b, $L['m4']);
$put_questions($L['qc'], $q_digi,   $L['d1']);
$say('· 3 اختبارات و' . (count($q_math_a) + count($q_math_b) + count($q_digi)) . ' سؤالا');

/* =====================================================================
   التسجيل والتقدم
   ===================================================================== */

/**
 * مصفوفة الحالات. `days` غياب بالأيام، و`progress` نسبة الإكمال:
 * منها من يمر بشرط «يوشك على الانقطاع» (غياب 5 فأكثر وتقدم بين 1 و99)،
 * ومنها من لم يبدأ أصلا فلا يعد منقطعا وإن غاب شهرا — وهو فرق تقيسه
 * الشاشة ولا يظهر إلا ببيانات فيه الحالتان.
 */
$enrol_plan = [
    // [فهرس الطالب, كورس, تقدم, أيام منذ آخر نشاط]
    [0, 'math5', 72,  0],
    [1, 'math5', 95,  1],
    [2, 'math5', 40,  6],
    [3, 'math5', 12,  21],
    [4, 'math5', 0,   30],
    [5, 'math5', 55,  2],
    [0, 'digi5', 60,  1],
    [1, 'digi5', 100, 3],
    [2, 'digi5', 30,  6],
    [1, 'club',  20,  4],
];

foreach ($enrol_plan as $p) {
    [$si, $ck, $prog, $days] = $p;
    if (!isset($students[$si])) continue;
    $sid  = $students[$si]['id'];
    $seen = $now - $days * $DAY;

    $insert("INSERT INTO enrol (user_id, course_id, gifted_by, date_added, last_modified) VALUES (?,?,0,?,?)",
        [$sid, $C[$ck], $now - 35 * $DAY, $seen]);

    $insert(
        "INSERT INTO watch_histories
            (course_id, student_id, completed_lesson, course_progress, watching_lesson_id, quiz_result, date_added, date_updated)
         VALUES (?,?,?,?,?,'[]',?,?)",
        [$C[$ck], $sid, '[]', $prog, 0, (string) ($now - 35 * $DAY), (string) $seen]
    );
}
$say('· ' . count($enrol_plan) . ' تسجيلا بتقدمها وآخر نشاط لها');

/* =====================================================================
   المحاولات — صف التصحيح ومعتمداته
   ===================================================================== */

$mk_result = function ($quiz, $si, $marks, $submitted, $days_ago, $approved = null, $note = '')
    use ($insert, $students, $TID, $now, $DAY) {
    if (!isset($students[$si])) return;
    $ts = $now - (int) ($days_ago * $DAY);
    $insert(
        "INSERT INTO quiz_results
            (quiz_id, user_id, user_answers, correct_answers, total_obtained_marks,
             date_added, date_updated, is_submitted, teacher_score, teacher_note, approved_at, approved_by)
         VALUES (?,?,'[]','[]',?,?,?,?,?,?,?,?)",
        [
            $quiz, $students[$si]['id'], $marks,
            (string) $ts, (string) $ts, $submitted ? 1 : 0,
            $approved === null ? null : $approved,
            $note,
            $approved === null ? null : ($now - 1 * $DAY),
            $approved === null ? null : $TID,
        ]
    );
};

/* اختبار الكسور — خمسة أسئلة، عتبة النجاح ثلاث درجات. */
$mk_result($L['qa'], 0, 4.0, true, 5, 4.5, 'إجابة نظيفة. في المرة القادمة اشرح خطوة المقارنة كتابة قبل أن تختار.');
$mk_result($L['qa'], 1, 3.0, true, 14);   // أقدم انتظار في الصف
$mk_result($L['qa'], 2, 2.0, true, 3);    // راسب ينتظر
$mk_result($L['qa'], 3, 1.0, true, 1);    // راسب ينتظر

/* اختبار الأعداد العشرية — فيه مقالي وإجابة قصيرة، فدرجته محجوبة. */
$mk_result($L['qb'], 5, 2.0, true, 2);
$mk_result($L['qb'], 5, 2.5, true, 1);
$mk_result($L['qb'], 0, 3.5, true, 4);
$mk_result($L['qb'], 4, 0.0, false, 6);   // بدأ ولم يسلم — لا يدخل الصف

/* اختبار المهارات الرقمية — أربعة أسئلة، معتمدان. */
$mk_result($L['qc'], 1, 4.0, true, 7, 4.0, 'ممتاز. جرب أن تسمي ملفاتك بالتاريخ أيضا.');
$mk_result($L['qc'], 2, 3.0, true, 8, 3.0, 'اقتربت. راجع درس البحث عن ملف ضائع ثم أعد المحاولة.');
$say('· 10 محاولات: ينتظر منها ستة، ومعتمد ثلاثة، وواحدة لم تسلم');

/* =====================================================================
   الواجبات — assessments/attempts
   ===================================================================== */

/* `assessments.pass_mark` عتبة **مئوية** و`attempts.score` نسبة مئوية —
   هو اصطلاح المنصة القائم: شاشة «مهامي» تطبعها «درجة النجاح ٧٠٪»،
   وصفوف القاعدة مكتوبة كذلك (٥٠ و٦٠ و٧٠). */
$hw = [];
$hw['m2'] = $insert("INSERT INTO assessments (type, lesson_id, pass_mark, time_limit_sec) VALUES ('homework',?,?,?)",
    [$L['m2'], 60, 1800]);
$hw['d2'] = $insert("INSERT INTO assessments (type, lesson_id, pass_mark, time_limit_sec) VALUES ('homework',?,?,?)",
    [$L['d2'], 70, 1200]);

$mk_attempt = function ($assessment, $si, $score, $passed, $started_days, $submitted_days)
    use ($insert, $students, $now, $DAY, $dt) {
    if (!isset($students[$si])) return;
    $insert(
        "INSERT INTO attempts (assessment_id, student_id, score, passed, attempt_no, started_at, submitted_at)
         VALUES (?,?,?,?,1,?,?)",
        [
            $assessment, $students[$si]['id'], $score, $passed,
            $dt($now - $started_days * $DAY),
            $submitted_days === null ? null : $dt($now - $submitted_days * $DAY),
        ]
    );
};
$mk_attempt($hw['m2'], 0, 85,   1, 4, 4);       // سلم وينتظر اعتماد المعلم
$mk_attempt($hw['m2'], 1, 45,   0, 9, 9);       // سلم بدرجة دون العتبة
$mk_attempt($hw['m2'], 3, null, null, 2, null); // بدأ ولم يسلم
$mk_attempt($hw['d2'], 0, 90,   1, 6, 6);
$say('· واجبان بأربع محاولات');

/* =====================================================================
   الحصص — الفترات والطلبات
   ===================================================================== */

/** ساعة بداية كل فترة، كما في Taqdar_sessions_model::periods(). */
$period_hour = ['morning' => 8, 'noon' => 12, 'evening' => 16];
$period_dur  = ['morning' => 240, 'noon' => 240, 'evening' => 300];

/**
 * الفترة تحول إلى أقرب وقوع قادم داخل الأيام السبعة — القاعدة نفسها التي
 * يطبقها النموذج، فلا يبذر للمعلم موعد في الماضي لا تعرضه شاشته.
 */
$slot_at = function ($dow, $pk) use ($now, $period_hour) {
    $delta = ($dow - (int) date('w', $now) + 7) % 7;
    $ts = strtotime(date('Y-m-d', strtotime("+$delta day", $now)) . ' ' . sprintf('%02d:00:00', $period_hour[$pk]));
    if ($ts <= $now) $ts = strtotime('+7 day', $ts);
    return $ts;
};

$slots = [];
$slot_plan = [
    [0, 'evening', 'open'],
    [1, 'morning', 'open'],
    [2, 'noon',    'held'],     // عليه طلب معلق
    [3, 'evening', 'booked'],   // عليه حصة مؤكدة
    [4, 'morning', 'open'],
    [5, 'evening', 'booked'],   // جارية الآن
    [6, 'noon',    'open'],
];
foreach ($slot_plan as $p) {
    [$dow, $pk, $status] = $p;
    $ts = $slot_at($dow, $pk);
    $slots["$dow:$pk"] = $insert(
        "INSERT INTO availability_slots (teacher_id, starts_at, duration_min, status) VALUES (?,?,?,?)",
        [$TID, $dt($ts), $period_dur[$pk], $status]
    );
}

/* موعدان مضيا — لحصة منتهية وأخرى اعتذر عنها، فيرى سجل الحالات كله. */
$past_a = $insert("INSERT INTO availability_slots (teacher_id, starts_at, duration_min, status) VALUES (?,?,?,'booked')",
    [$TID, $dt($now - 6 * $DAY), 240]);
$past_b = $insert("INSERT INTO availability_slots (teacher_id, starts_at, duration_min, status) VALUES (?,?,?,'open')",
    [$TID, $dt($now - 9 * $DAY), 240]);

$mk_session = function ($slot, $si, $status, $meet = null) use ($insert, $students, $TID) {
    if (!isset($students[$si]) || !$slot) return;
    $insert("INSERT INTO tutoring_sessions (slot_id, student_id, teacher_id, status, meet_url) VALUES (?,?,?,?,?)",
        [$slot, $students[$si]['id'], $TID, $status, $meet]);
};
$mk_session($slots['2:noon'],    0, 'requested');
$mk_session($slots['3:evening'], 1, 'confirmed', 'https://meet.google.com/tqd-demo-abc');
$mk_session($slots['5:evening'], 2, 'live',      'https://meet.google.com/tqd-demo-live');
$mk_session($past_a,             3, 'completed', 'https://meet.google.com/tqd-demo-done');
$mk_session($past_b,             4, 'declined');
$mk_session($past_b,             5, 'expired');
$say('· 9 فترات و6 حصص بالحالات كلها');

/* =====================================================================
   المال — المدفوعات وطلبات السحب
   ===================================================================== */

/**
 * لا تكتب قيود الدفتر هنا إلا للبيع المسترد.
 *
 * البيع العادي يقيده `Taqdar_wallet_model::post_sales()` من `payment` عند
 * أول عرض للشاشة — فيبقى الدفتر نتاج منطق واحد لا منطقين، وأي تغيير في
 * حساب العمولة أو نافذة الاسترداد يسري على البذرة كما يسري على الإنتاج.
 */
$pay_plan = [
    // [فهرس الطالب, كورس, المبلغ, عمولة المنصة, حصة المعلم, أيام مضت, سوي سابقا؟]
    [0, 'math5', 120, 18, 102, 3,  0],   // معلق — يتحرر بعد أيام
    [1, 'math5', 120, 18, 102, 10, 0],   // معلق — قارب التحرر
    [2, 'math5', 120, 18, 102, 40, 0],   // متاح
    [3, 'math5', 120, 18, 96,  55, 0],   // متاح، وفيه محتجز (ضريبة) 6
    [1, 'digi5', 90,  14, 76,  70, 1],   // حول إليك سابقا
    [2, 'digi5', 90,  14, 76,  45, 0],   // متاح
];
foreach ($pay_plan as $p) {
    [$si, $ck, $amount, $admin, $inst, $days, $settled] = $p;
    if (!isset($students[$si])) continue;
    $insert(
        "INSERT INTO payment
            (user_id, payment_type, course_id, amount, date_added, last_modified,
             admin_revenue, instructor_revenue, tax, instructor_payment_status, transaction_id)
         VALUES (?,'manual',?,?,?,?,?,?,0,?,?)",
        [$students[$si]['id'], $C[$ck], $amount, $now - $days * $DAY, $now - $days * $DAY,
         (string) $admin, (string) $inst, $settled, TQ_MARK]
    );
}

/* المحفظة تفتح هنا لأن قيود الاسترداد تحتاج معرفها. وبقية الدفتر تبنى وحدها. */
$wid = (int) $one("SELECT id FROM wallets WHERE owner_user_id = ?", [$TID]);
if (!$wid) {
    $wid = $insert("INSERT INTO wallets (owner_user_id, balance_available, balance_pending, balance_locked) VALUES (?,0,0,0)", [$TID]);
}

/**
 * بيع استرد.
 *
 * الاسترداد في هذا الدفتر ليس عمودا بل غياب: قيود بيع لا صف دفع لها،
 * و`reconcile_refunds()` تكتب عكسها عند أول عرض. فتبذر القيود وحدها
 * بمعرف مدفوعة لا وجود لها، ويتولى النموذج الباقي كما يتولاه في الإنتاج.
 */
$ghost  = 'payment:900000001';
$occur  = $dt($now - 25 * $DAY);
$release = $dt($now - 11 * $DAY);
foreach ([
    ['sale',       'pending', 12000,  'sale'],
    ['commission', 'pending', -1800,  'commission'],
] as $e) {
    [$type, $bucket, $amount, $kind] = $e;
    $insert(
        "INSERT IGNORE INTO wallet_entries (wallet_id, type, bucket, amount, ref, origin, subject, released_at, occurred_at)
         VALUES (?,?,?,?,?,?,?,?,?)",
        [$wid, $type, $bucket, $amount, "w{$wid}:{$ghost}:{$kind}", $ghost,
         'الرياضيات — الصف الخامس الابتدائي', $release, $occur]
    );
}

/**
 * طلبات السحب: معلق ومحول وملغى.
 *
 * والمبالغ دون الرصيد المتاح عمدا. `reconcile_payouts()` تقيد الحجز من
 * صف الطلب كما هو ولا تسأل عن كفاية الرصيد — وهو الصواب في الإنتاج لأن
 * `request_payout()` هي التي تفحص الكفاية قبل أن يوجد الصف. لكن بذرة
 * تكتب الصف مباشرة تستطيع أن تخلق رصيدا سالبا لا يقع في الواقع، فتقرأ
 * الشاشة «متاح للسحب −٧٦ ريال» وهي صادقة في نقل دفتر كاذب.
 * فالمبالغ هنا محسوبة على ما يتحرر فعلا.
 */
$po_pending = $insert(
    "INSERT INTO payout (user_id, payment_type, destination, requested_channel, amount, amount_halalas, date_added, last_modified, status)
     VALUES (?,?,?,?,?,?,?,?,0)",
    [$TID, 'bank', 'SA4420000001234567891234', 'bank', 60.00, 6000, $now - 2 * $DAY, $now - 2 * $DAY]
);
$po_paid = $insert(
    "INSERT INTO payout (user_id, payment_type, destination, requested_channel, amount, amount_halalas, date_added, last_modified, status)
     VALUES (?,?,?,?,?,?,?,?,1)",
    [$TID, 'stcpay', '0551234567', 'stcpay', 100.00, 10000, $now - 30 * $DAY, $now - 28 * $DAY]
);
$po_cancel = $insert(
    "INSERT INTO payout (user_id, payment_type, destination, requested_channel, amount, amount_halalas, date_added, last_modified, status)
     VALUES (?,?,?,?,?,?,?,?,2)",
    [$TID, 'mada', '4111111111111111', 'mada', 120.00, 12000, $now - 18 * $DAY, $now - 17 * $DAY]
);
foreach ([
    ['payout_cancel_out', 'locked',    -12000, 'cancel:out'],
    ['payout_cancel_in',  'available',  12000, 'cancel:in'],
] as $e) {
    [$type, $bucket, $amount, $kind] = $e;
    $origin = 'payout:' . $po_cancel;
    $insert(
        "INSERT IGNORE INTO wallet_entries (wallet_id, type, bucket, amount, ref, origin, subject, occurred_at)
         VALUES (?,?,?,?,?,?,?,?)",
        [$wid, $type, $bucket, $amount, "w{$wid}:{$origin}:{$kind}", $origin,
         'إلغاء طلب سحب', $dt($now - 17 * $DAY)]
    );
}
$say('· 6 مبيعات وبيع مسترد و3 طلبات سحب');

/* =====================================================================
   الرسائل والإشعارات
   ===================================================================== */

/**
 * خيط المحادثة مع طالب.
 *
 * يعاد استعمال الخيط القائم إن وجد ولا ينشأ ثان: `crud_model` يبحث عن خيط
 * الطرفين قبل أن ينشئ، فخيطان بين الاثنين يعنيان أن رسالة الطالب تذهب إلى
 * أحدهما ورد المعلم إلى الآخر — محادثة تنقسم نصفين ولا يظهر أثر ذلك إلا
 * في الشاشة.
 */
$thread = function ($other_id, $suffix) use ($insert, $one, $TID, $now, $run) {
    $found = $one(
        "SELECT message_thread_code FROM message_thread
          WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?) LIMIT 1",
        [(string) $TID, (string) $other_id, (string) $other_id, (string) $TID]
    );
    if ($found) {
        $run("UPDATE message_thread SET last_message_timestamp = ? WHERE message_thread_code = ?",
            [(string) $now, $found]);
        return $found;
    }
    $code = 'tqseedt' . $suffix . str_pad((string) $other_id, 6, '0', STR_PAD_LEFT);
    $insert("INSERT INTO message_thread (message_thread_code, sender, receiver, last_message_timestamp) VALUES (?,?,?,?)",
        [$code, (string) $TID, (string) $other_id, (string) $now]);
    return $code;
};
/**
 * رسالة مبذورة.
 *
 * العلامة تكتب تعليق HTML في ذيل النص: `strip_tags()` تمحوه في الشاشتين
 * فلا يراه أحد، ويبقى في القاعدة فيعرف الحذف ما يحذف. وهي حيلة
 * `Taqdar_events_model` نفسها في بصمة الإشعار — لا عمود جديد في جدول
 * تشترك فيه المنصة كلها.
 */
$msg = function ($code, $from, $to, $body, $days, $read) use ($insert, $now, $DAY) {
    $insert("INSERT INTO message (message_thread_code, message, sender, receiver, timestamp, read_status) VALUES (?,?,?,?,?,?)",
        [$code, $body . '<!--' . TQ_MARK . '-->', $from, $to, (string) ($now - $days * $DAY), $read]);
};

/* خيط فيه رد من الطالب لم يقرأه المعلم — فتظهر شارة الرسائل في ترويسته. */
$t1 = $thread($students[0]['id'], 'a');
$msg($t1, $TID, $students[0]['id'], 'لاحظت أنك توقفت عند درس الكسور المتكافئة. أعد الدقيقة الرابعة وحدها ثم جرب السؤال الثالث.', 3, 1);
$msg($t1, $students[0]['id'], $TID, 'شكرا يا أستاذ. أعدت الدرس وفهمت، لكن السؤال الرابع ما زال صعبا علي.', 2, 0);
$msg($t1, $students[0]['id'], $TID, 'هل أستطيع حجز حصة قصيرة هذا الأسبوع؟', 1, 0);

/* خيط مقروء بالكامل — ليرى الفرق بين المقروء وغيره. */
$t2 = $thread($students[2]['id'], 'b');
$msg($t2, $TID, $students[2]['id'], 'غبت ستة أيام. ابدأ بدرس واحد اليوم ولو عشر دقائق، والباقي يسهل.', 5, 1);
$msg($t2, $students[2]['id'], $TID, 'إن شاء الله أبدأ اليوم.', 4, 1);
$say('· خيطا رسائل، فيهما رسالتان غير مقروءتين عند المعلم');

$notify = function ($type, $title, $text, $days, $read) use ($insert, $TID, $now, $DAY) {
    $insert(
        "INSERT INTO notifications (from_user, to_user, type, title, description, status, created_at, updated_at)
         VALUES (NULL,?,?,?,?,?,?,NULL)",
        [$TID, $type, $title, $text . '<!--' . TQ_MARK . '-->', $read, (string) ($now - $days * $DAY)]
    );
};
$notify('session_request', 'طلب حصة خاصة', 'طلب ' . $students[0]['name'] . ' حصة خاصة معك. أكدها أو اعتذر خلال 24 ساعة.', 0, 0);
$notify('exam_result',     'تسليم جديد ينتظر تصحيحك', 'سلم ' . $students[3]['name'] . ' اختبار الوحدة الأولى.', 1, 0);
$notify('inactivity_3days','طالب انقطع', 'انقطع ' . $students[3]['name'] . ' عن الدراسة واحدا وعشرين يوما.', 2, 0);
$notify('weekly_report',   'ملخص أسبوعك', 'ستة تسليمات ينتظر تصحيحك، وحصة مؤكدة واحدة هذا الأسبوع.', 6, 1);
$notify('certificate',     'شهادة لطالبك', 'أنهى ' . $students[1]['name'] . ' كورس المهارات الرقمية وحصل على شهادته.', 8, 1);
$say('· 5 إشعارات، ثلاثة منها غير مقروءة');

/* =====================================================================
   الخلاصة
   ===================================================================== */

$say(str_repeat('-', 62));
$say("تم. عدد عمليات الكتابة: $writes");
$say('');
$say('افتح البوابة وتحقق:');
$say('  /teacher            اللوحة: أرقام أربعة، متعثرون، دروس عالية الفشل، حصص');
$say('  /teacher/courses    خمسة كورسات بالحالات الأربع');
$say('  /teacher/upload     أربعة عشر درسا في القائمة الجانبية');
$say('  /teacher/questions  ثلاثة اختبارات وأربعة عشر سؤالا');
$say('  /teacher/marking    ستة في الصف، وثلاثة معتمدة');
$say('  /teacher/students   عشرة تسجيلات، اثنان يوشكان على الانقطاع');
$say('  /teacher/sessions   طلب معلق، وحصتان مؤكدتان، وسبع فترات');
$say('  /teacher/wallet     معلق ومتاح ومحول ومسترد، وثلاثة طلبات سحب');
$say('');
$say('ولمسح البذرة: php scripts/seed_demo_teacher.php --apply --clear');
