<?php
/**
 * بذرة عرض لبوابة ولي الأمر — أسرة كاملة تمر بكل حالة تعرضها الشاشات.
 *
 * شاشات ولي الأمر السبع تعرض حالات فارغة صادقة حين لا بيانات، وهو الصواب —
 * لكن أسرة من ابن واحد نشط لا تظهر إلا زاوية واحدة من كل شاشة: لا يرى أحد
 * شكل «طلب ربط ينتظر موافقة الابن» ولا «ابن انقطع ثلاثة أسابيع» ولا
 * «فاتورة بانتظار التحويل» ولا شارة رسائل غير مقروءة، حتى تقع فعلا.
 * هذا المرور يولد تلك الحالات كلها لحساب ولي أمر واحد.
 *
 * ما يغطى، شاشة شاشة:
 *   أبنائي        · ثلاثة أبناء مربوطين بحالات نشاط مختلفة (نشط · متعثر ·
 *                   منقطع) · طلب معلق ينتظر موافقة · رابط مسحوب في السجل
 *   التقارير      · ابن بخمس مواد ونتائج معتمدة · ابن بمادتين بلا اختبار
 *                   · ابن بلا مواد أصلا (الحالة الفارغة داخل الصفحة)
 *   التقرير الأسبوعي · الأسطر الأربعة بكل تفريعاتها: ارتفاع · نزول · ثبات
 *                   · خطة محددة وخطة افتراضية · مادة متوقفة ومادة لم تبدأ
 *   الرسائل       · خيط مع معلم فيه غير مقروء (تظهر الشارة) · خيط مع الإدارة
 *                   · خيط قديم مقروء بالكامل
 *   الإشعارات     · الأحداث الخمسة كلها · مقروء وغير مقروء · نوع موقوف
 *                   · أحداث مؤجلة في «ينتظر التقرير الأسبوعي»
 *   المدفوعات     · فواتير مدفوعة هذا الشهر وقبله · فاتورة بانتظار التحويل
 *                   · فاتورة مستردة · عملية Academy قديمة (دمج المصدرين)
 *   الإعدادات     · روابط بالحالات الثلاث · تفضيلات محفوظة فعلا
 *                   · خطة أيام محددة لابن وغير محددة لآخر
 *   تفاصيل الابن  · الفهم (أهداف ومستويات) · الحصص القادمة · ملاحظات المعلمين
 *
 * الاستعمال (من جذر الموقع):
 *     php scripts/seed_demo_parent.php                  # عرض الخطة، بلا كتابة
 *     php scripts/seed_demo_parent.php --apply          # التنفيذ
 *     php scripts/seed_demo_parent.php --apply --parent=291
 *     php scripts/seed_demo_parent.php --apply --clear  # حذف البذرة وحدها
 *     php scripts/seed_demo_parent.php --apply --demo-teachers  # يفعل معلمي الكورسات
 *
 * ── ما لا يمسه هذا المرور ────────────────────────────────────────────────
 * كل حذف هنا مقيد بأثر **هذا الولي وأبنائه المبذورين وحدهم**، لأن السكربت
 * قد يشغل على قاعدة فيها أسر حقيقية:
 *
 *   • الأبناء المبذورون يعرفون ببريدهم (`seed-child*@taqdaredu.local`)،
 *     ولا يحذف السكربت حساب طالب لا يحمل هذه البصمة — ولو كان مربوطا
 *     بهذا الولي. فمن ربط ابنه الحقيقي بحساب الاختبار لا يفقده.
 *   • ابن المنصة القائم (`student.test`) يربط ولا يمس أثره التعليمي:
 *     ذاك شأن `seed_demo_student.php`، ولا يبذر الملف الواحد مرتين.
 *   • الفواتير والاشتراكات: تحذف لأبناء البذرة ولولي الأمر نفسه فقط.
 *   • الرسائل: الخيوط المبذورة تحمل بادئة `tqpar<معرف الولي>`، ولا يحذف
 *     خيطا لا يحملها — فمحادثة حقيقية مع معلم لا تختفي في مرور بيانات.
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
$want  = 0;
foreach ($argv as $a) {
    if (strpos($a, '--parent=') === 0) $want = (int) substr($a, 9);
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
$ids = function ($rows, $key = 'id') {
    return array_map(static function ($r) use ($key) { return (int) $r[$key]; }, $rows);
};
$dt   = static function ($ts) { return date('Y-m-d H:i:s', $ts); };
$say  = static function ($s = '') { echo $s, "\n"; };
$code = static function ($seed) {                 // رمز خيط بطول رموز المنصة
    return substr($seed . str_repeat('x', 30), 0, 30);
};

$now  = time();
$DAY  = 86400;
$WEEK = 7 * $DAY;
/* الأسبوع يبدأ الأحد — كما تحسبه الشاشات كلها. */
$week_start = strtotime('today') - ((int) date('w')) * $DAY;
$prev_start = $week_start - $WEEK;

/* ---------- ولي الأمر المستهدف ---------- */
$PID = $want ?: (int) $one("SELECT id FROM users WHERE email = ? LIMIT 1", ['parent.test@taqdaredu.com']);
if (!$PID) exit("لا حساب ولي أمر. مرر --parent=<معرف الحساب>\n");

$who = $all("SELECT id, email, first_name, last_name, role_id, is_instructor, tq_gate FROM users WHERE id = ?", [$PID]);
if (!$who) exit("لا مستخدم بالمعرف $PID\n");
$who = $who[0];
if ((int) $who['role_id'] === 1 || (int) $who['is_instructor'] === 1) {
    exit("المعرف $PID ليس حساب ولي أمر (أدمن أو معلم). أوقف.\n");
}

$ADM = (int) $one("SELECT id FROM users WHERE role_id = 1 AND status = 1 ORDER BY id LIMIT 1");
$SID = (int) $one("SELECT id FROM users WHERE email = ? LIMIT 1", ['student.test@taqdaredu.com']);

$say('');
$say('ولي الأمر : ' . trim($who['first_name'] . ' ' . $who['last_name']) . '  (#' . $PID . ' · ' . $who['email'] . ')');
$say('قاعدة      : ' . $conf['database'] . ' @ ' . $conf['hostname']);
$say('الوضع      : ' . ($apply ? ($clear ? 'حذف البذرة' : 'تنفيذ') : 'عرض الخطة فقط (بلا كتابة)'));
$say(str_repeat('-', 62));

/* ---------- الكورسات المستهدفة ---------- */
$courses = $all(
    "SELECT c.id, c.title
       FROM course c
      WHERE c.status = 'active'
        AND (SELECT COUNT(*) FROM lesson l WHERE l.course_id = c.id AND l.lesson_type != 'quiz') >= 5
      ORDER BY c.id
      LIMIT 5");
if (count($courses) < 3) exit("لا كورسات منشورة كافية للبذر.\n");
$seed_courses = $ids($courses);
$in_courses   = implode(',', $seed_courses);

/* ---------- المعلمون ----------
   يستعملون في ثلاثة مواضع: مرسل الملاحظة المعتمدة، ومعلم الحصة، وطرف
   المحادثة. والأصل معلمو الكورسات المبذورة.

   وقاعدة الإنتاج تختلف عن المحلية هنا اختلافا يوقف المرور: المحلية فيها
   خمسة معلمين مفعلين يملكون هذه الكورسات، والإنتاج فيه معلم مفعل واحد
   وهو حساب العرض، وكورسات البذرة ليست باسمه. فشرط «معلم مفعل يملك
   الكورس» يخرج فارغا هناك ويخرج المرور برسالة قبل أن يكتب شيئا.

   فالاختيار على درجتين: معلمو الكورسات المفعلون أولا، فإن لم يوجد منهم
   أحد فأي معلم مفعل في المنصة. ولا يوقف المرور إلا إن خلت المنصة من
   معلم مفعل أصلا — وعندها لا حصة ولا ملاحظة يصح بذرها باسم أحد. */
$teachers = [];
foreach ($all("SELECT id, creator, user_id FROM course WHERE id IN ($in_courses)") as $c) {
    foreach (array_merge([$c['creator']], explode(',', (string) $c['user_id'])) as $raw) {
        $tid = (int) trim((string) $raw);
        if ($tid > 0) $teachers[$tid] = true;
    }
}
$teachers = array_values(array_filter(array_keys($teachers), function ($tid) use ($one) {
    return (int) $one("SELECT COUNT(*) FROM users WHERE id = ? AND status = 1 AND is_instructor = 1", [$tid]) > 0;
}));

/* `--demo-teachers`: يفعل حسابات معلمي كورسات البذرة.
   التفعيل قرار إدارة لا أثر جانبي لمرور بيانات، فلا يقع إلا بطلب صريح —
   كما في `seed_demo_student.php`. وأثره هنا أن ولي الأمر يستطيع مراسلة
   معلمي مواد أبنائه: `Taqdar_parent_model::teachers_for()` يشترط
   `status = 1`، فبلا تفعيل لا يجد ولي الأمر في قائمة المراسلة إلا
   الإدارة — وهي نصف التجربة. */
$demo_teachers = in_array('--demo-teachers', $argv, true);

if (!$teachers && $demo_teachers) {
    $owners = [];
    foreach ($all("SELECT id, creator, user_id FROM course WHERE id IN ($in_courses)") as $c) {
        foreach (array_merge([$c['creator']], explode(',', (string) $c['user_id'])) as $raw) {
            $tid = (int) trim((string) $raw);
            if ($tid > 0) $owners[$tid] = true;
        }
    }
    $owners = array_values(array_filter(array_keys($owners), function ($tid) use ($one) {
        return (int) $one("SELECT COUNT(*) FROM users WHERE id = ? AND is_instructor = 1 AND role_id <> 1", [$tid]) > 0;
    }));
    if ($owners) {
        $run("UPDATE `users` SET status = 1 WHERE id IN (" . implode(',', $owners) . ")");
        $teachers = $owners;
        $say('   فعلت حسابات معلمي كورسات البذرة: ' . implode('، ', $owners));
    }
}

if (!$teachers) {
    $teachers = $ids($all(
        "SELECT id FROM users WHERE status = 1 AND is_instructor = 1 AND role_id <> 1 ORDER BY id LIMIT 5"));
    if ($teachers) {
        $say('   (لا معلم مفعل يملك كورسات البذرة — استعمل معلمي المنصة المفعلين: '
             . implode('، ', $teachers) . ')');
        $say('   ولمراسلة معلمي المواد من بوابة ولي الأمر: أضف --demo-teachers');
    }
}
if (!$teachers) exit("لا معلم مفعل في المنصة — لا يبذر باسم أحد.\n");

/* ================================================================
   الأبناء المبذورون — بصمتهم في بريدهم، وبها وحدها يعرفون ويحذفون
   ================================================================
   خمسة أبناء، كل واحد يمثل حالة لا تظهر في الآخر:

     child1  نشط يوميا، خمس مواد، نتائج معتمدة وملاحظات وحصص  → active
     child2  متعثر: يدرس يومين ونتائجه دون النصف                → active
     child3  منقطع منذ ثلاثة أسابيع، مادتان بلا اختبار          → active
     child4  لم يوافق بعد                                       → pending
     child5  رفض الطلب، ويبقى في السجل                          → revoked

   وحساب المنصة القائم (`student.test`) يربط سادسا إن وجد: أثره التعليمي
   يبذره سكربته هو، فيظهر هنا ابنا كامل البيانات بلا أن نكتب فوقه.
*/
$kids = [
    ['key' => 'child1', 'first' => 'سلمان', 'last' => 'الاختبار', 'link' => 'active',
     'days' => [0,1,2,3,4], 'prev_days' => 3, 'courses' => 5, 'progress' => [88, 74, 61, 45, 20],
     'exams' => 'good',  'plan' => 5, 'gap' => 0],

    ['key' => 'child2', 'first' => 'لينا',  'last' => 'الاختبار', 'link' => 'active',
     'days' => [1, 4],   'prev_days' => 5, 'courses' => 3, 'progress' => [42, 18, 5],
     'exams' => 'weak',  'plan' => 0, 'gap' => 2],

    ['key' => 'child3', 'first' => 'فيصل',  'last' => 'الاختبار', 'link' => 'active',
     'days' => [],       'prev_days' => 0, 'courses' => 2, 'progress' => [12, 0],
     'exams' => 'none',  'plan' => 3, 'gap' => 21],

    ['key' => 'child4', 'first' => 'نورة',  'last' => 'الاختبار', 'link' => 'pending',
     'days' => [],       'prev_days' => 0, 'courses' => 0, 'progress' => [],
     'exams' => 'none',  'plan' => 0, 'gap' => 0],

    ['key' => 'child5', 'first' => 'بدر',   'last' => 'الاختبار', 'link' => 'revoked',
     'days' => [],       'prev_days' => 0, 'courses' => 0, 'progress' => [],
     'exams' => 'none',  'plan' => 0, 'gap' => 0],
];
foreach ($kids as &$k) { $k['email'] = 'seed-' . $k['key'] . '@taqdaredu.local'; }
unset($k);

$kid_emails = array_column($kids, 'email');
$in_emails  = "'" . implode("','", $kid_emails) . "'";

/* معرفات الأبناء المبذورين القائمة (قبل الحذف) — يقيد بها كل حذف. */
$kid_ids = $ids($all("SELECT id FROM users WHERE email IN ($in_emails)"));
$in_kids = $kid_ids ? implode(',', $kid_ids) : '0';

/* ================================================================
   ٠ · حذف البذرة السابقة
   ================================================================ */
$say('١ · حذف أثر البذرة السابقة');

/* مواعيد المعلمين **قبل** حذف الحجوزات التي تدل عليها.
   الموعد يعرف بأن حجزه لأبناء البذرة، والدليل هو صف `tutoring_sessions`
   نفسه — فحذفه أولا يقطع الخيط الذي نعرف به الموعد، فتبقى المواعيد
   يتيمة وتصطدم بمفتاح (معلم، وقت البدء) الفريد في المرور التالي.
   وقيد «لا حجز عليه لطالب آخر» يبقي مواعيد الطلاب الحقيقيين. */
$run("DELETE s FROM `availability_slots` s
       WHERE EXISTS (SELECT 1 FROM `tutoring_sessions` t
                      WHERE t.slot_id = s.id AND t.student_id IN ($in_kids))
         AND NOT EXISTS (SELECT 1 FROM `tutoring_sessions` t2
                          WHERE t2.slot_id = s.id AND t2.student_id NOT IN ($in_kids))");

/* أثر الأبناء المبذورين وحدهم — بمعرفاتهم لا بصلتهم بولي الأمر. */
foreach ([
    'enrol'             => 'user_id',
    'watch_histories'   => 'student_id',
    'lesson_progress'   => 'student_id',
    'quiz_results'      => 'user_id',
    'attempts'          => 'student_id',
    'review_queue'      => 'student_id',
    'skill_state'       => 'student_id',
    'tutoring_sessions' => 'student_id',
    'subscriptions'     => 'user_id',
    'invoices'          => 'user_id',
    'payment'           => 'user_id',
    'notifications'     => 'to_user',
] as $table => $col) {
    $run("DELETE FROM `$table` WHERE `$col` IN ($in_kids)");
}

/* أثر ولي الأمر نفسه: إشعاراته وفواتيره ومدفوعاته وروابطه المبذورة. */
$run("DELETE FROM `notifications` WHERE `to_user` = ?", [$PID]);
$run("DELETE FROM `invoices` WHERE `user_id` = ?", [$PID]);
$run("DELETE FROM `payment`  WHERE `user_id` = ?", [$PID]);
$run("DELETE FROM `subscriptions` WHERE `user_id` = ?", [$PID]);
$run("DELETE FROM `tq_prefs_notify` WHERE `user_id` = ? AND `channel` = ?", [$PID, 'portal']);

/* روابط حساب العرض **كلها**.
   هذا الحساب حساب عرض بحكم وجوده، والغرض من المرور أن تظهر بوابته
   بالحالات المقصودة وحدها. ورابط واحد بقي من تجربة سابقة — إلى حساب
   لا يعرفه أحد — يظهر في «طلبات تنتظر موافقة أبنائك» أمام العميل
   ويفسد الصورة. فلا يترك شيء لم يبذره هذا الملف.

   وهذا هو السبب في أن `--parent=` لا يوجه إلى حساب ولي أمر حقيقي. */
$run("DELETE FROM `parent_links` WHERE `parent_user_id` = ?", [$PID]);

/* الرسائل المبذورة وحدها — ببادئتها، فلا تمس محادثة حقيقية. */
$prefix = 'tqpar' . $PID;
$run("DELETE FROM `message` WHERE `message_thread_code` LIKE ?", [$prefix . '%']);
$run("DELETE FROM `message_thread` WHERE `message_thread_code` LIKE ?", [$prefix . '%']);

/* حسابات الأبناء المبذورين — آخر ما يحذف، بعد أثرها. */
$run("DELETE FROM `users` WHERE `email` IN ($in_emails)");

if ($clear) {
    $say('');
    $say($apply ? "حذفت البذرة. عمليات: $writes" : "خطة الحذف: $writes عملية. أضف --apply للتنفيذ.");
    exit(0);
}

/* ================================================================
   ١ · حسابات الأبناء
   ================================================================ */
$say('٢ · بيانات حساب العرض وأبنائه الخمسة');
$hash = password_hash('Taqdar@Test2026', PASSWORD_DEFAULT);

/* بيانات ولي الأمر نفسه.
   الاسم يظهر في تحية الترويسة وفي شاشة الإعدادات وفي كل رسالة يرسلها،
   فحساب عرض باسم ناقص أو مشوه يفسد أول ما تقع عليه العين. ولا تمس
   كلمة مروره ولا بريده: بهما يدخل من يجرب. */
$run("UPDATE `users` SET first_name = ?, last_name = ?, phone = ?, address = ?, last_modified = ?
       WHERE id = ?",
    ['ولي', 'الاختبار', '0551234567', 'الرياض', $now, $PID]);

foreach ($kids as &$k) {
    $k['id'] = $insert(
        "INSERT INTO `users` (first_name, last_name, email, password, role_id, is_instructor,
                              status, tq_gate, date_added, last_modified)
         VALUES (?, ?, ?, ?, 2, 0, 1, 'student', ?, ?)",
        [$k['first'], $k['last'], $k['email'], $hash, $now, $now]
    );
    if (!$apply) $k['id'] = 0;
}
unset($k);

/* ================================================================
   ٢ · الروابط — بحالاتها الثلاث، والموافقة بتاريخها ونصها
   ================================================================
   `consent_at` شرط في القاعدة نفسها (trg_parent_links_consent_*): رابط
   نشط بلا تاريخ موافقة ترفضه القاعدة لا الشيفرة. فالنشط يكتب بتاريخه.
*/
$say('٣ · روابط ولي الأمر (نشط · معلق · مسحوب)');

$CONSENT = 'أوافق على ربط حسابي بحساب ولي أمري، وعلى أن يرى تقدمي في المواد '
         . 'وأيام نشاطي ونتائج اختباراتي ومدفوعاتي وملاحظات معلمي. ولا يرى '
         . 'محادثاتي مع المساعد الذكي ولا منشوراتي ولا إجاباتي الخاطئة مفردة. '
         . 'ولي أن أسحب هذه الموافقة متى شئت.';

$mk_scope = function ($k) use ($CONSENT, $dt, $now, $DAY) {
    $s = ['requested_at' => $dt($now - 40 * $DAY), 'requested_ip' => '127.0.0.1'];

    if ($k['link'] === 'active') {
        $s['consent'] = [
            'at' => $dt($now - 39 * $DAY), 'by' => (int) $k['id'],
            'by_role' => 'student', 'ip' => '127.0.0.1', 'text' => $CONSENT,
        ];
        // خطة الأسبوع: محددة لمن له `plan`، وغير محددة لغيره — والشاشة
        // تعلن الافتراضي صراحة في الحالة الثانية.
        if (!empty($k['plan'])) $s['plan_days'] = (int) $k['plan'];
    }
    if ($k['link'] === 'revoked') {
        $s['rejected'] = ['at' => $dt($now - 12 * $DAY), 'by' => (int) $k['id'], 'ip' => '127.0.0.1'];
    }
    return json_encode($s, JSON_UNESCAPED_UNICODE);
};

foreach ($kids as $k) {
    $run("INSERT INTO `parent_links` (parent_user_id, student_id, consent_at, status, scope)
          VALUES (?, ?, ?, ?, ?)",
        [$PID, $k['id'],
         $k['link'] === 'active' ? $dt($now - 39 * $DAY) : null,
         $k['link'], $mk_scope($k)]);
}

/* حساب المنصة القائم: ابن سادس مربوط، بلا أن يكتب فوق أثره التعليمي. */
if ($SID) {
    $run("INSERT INTO `parent_links` (parent_user_id, student_id, consent_at, status, scope)
          VALUES (?, ?, ?, 'active', ?)",
        [$PID, $SID, $dt($now - 60 * $DAY),
         json_encode([
             'requested_at' => $dt($now - 61 * $DAY),
             'consent' => ['at' => $dt($now - 60 * $DAY), 'by' => $SID,
                           'by_role' => 'student', 'ip' => '127.0.0.1', 'text' => $CONSENT],
         ], JSON_UNESCAPED_UNICODE)]);
}

/* ================================================================
   ٣ · أثر التعلم لكل ابن — بقدر ما تحتاجه شاشات ولي الأمر
   ================================================================
   الشاشات تقرأ من:
     `enrol`           المواد المسجلة
     `watch_histories` نسبة التقدم وآخر نشاط  (طابع يونكس)
     `lesson_progress` الدروس المنجزة بتواريخها (ومنها «هذا الأسبوع»)
     `quiz_results`    النتائج والملاحظات المعتمدة
     `skill_state`     الفهم — بمقياس مئوي لا كسري
*/
$say('٤ · أثر التعلم (مواد · دروس · نتائج · أهداف)');

foreach ($kids as $k) {
    if ($k['courses'] < 1) continue;

    $mine = array_slice($seed_courses, 0, (int) $k['courses']);

    /* أيام نشاطه هذا الأسبوع — طوابع فعلية يقرأ منها التقرير عدد الأيام.
       وعليها توزع دروسه المنجزة كذلك: «أنهى خمسة عشر درسا» في يوم نشاط
       واحد رقمان يكذب أحدهما الآخر في السطر نفسه، ولا يصدق ولي أمر
       تقريرا يناقض نفسه. فالمصدران واحد: هذه الأيام. */
    $active = [];
    foreach ((array) $k['days'] as $d) {
        $ts = $week_start + $d * $DAY + 18 * 3600;
        if ($ts <= $now) $active[] = $ts;
    }
    // الأسبوع الماضي — منه يحسب «ارتفع» و«نزل»
    $prev = [];
    for ($d = 0; $d < (int) $k['prev_days']; $d++) $prev[] = $prev_start + $d * $DAY + 18 * 3600;

    foreach ($mine as $i => $cid) {
        $pct  = $k['progress'][$i] ?? 0;
        /* آخر نشاط في المادة: من أيامه الفعلية إن كان نشطا، وإلا قبل
           فجوته المعلنة — فلا يقول «آخر نشاط اليوم» لمن انقطع ثلاثة أسابيع. */
        $seen = $k['gap'] > 0
            ? $now - ($k['gap'] + $i) * $DAY
            : ($active ? $active[$i % count($active)] : ($prev ? end($prev) : $now - $DAY));

        // مادة لم تبدأ: بلا صف مشاهدة أصلا، فتقرأ الشاشة «لم يبدأ بعد»
        $started = $pct > 0;

        $run("INSERT INTO `enrol` (user_id, course_id, gifted_by, date_added, last_modified)
              VALUES (?, ?, 0, ?, ?)", [$k['id'], $cid, $now - 45 * $DAY, $now - 45 * $DAY]);

        $lessons = $ids($all(
            "SELECT id FROM lesson WHERE course_id = ? AND lesson_type != 'quiz' ORDER BY id", [$cid]));
        $total   = max(1, count($lessons));
        $done_n  = (int) round($total * $pct / 100);
        $done    = array_slice($lessons, 0, $done_n);

        if ($started) {
            $run("INSERT INTO `watch_histories`
                    (student_id, course_id, completed_lesson, course_progress,
                     watching_lesson_id, quiz_result, date_added, date_updated)
                  VALUES (?, ?, ?, ?, ?, '[]', ?, ?)",
                [$k['id'], $cid, json_encode(array_values($done)), $pct,
                 $done ? end($done) : ($lessons[0] ?? 0),
                 (string) ($now - 45 * $DAY), (string) $seen]);
        }

        /* الدروس بتواريخها.
           درسان من كل مادة يقعان في أيام نشاطه **الفعلية** هذا الأسبوع،
           فيتفق سطر «أنهى كذا درسا» مع سطر «نشاطه كذا أيام». وبقيتها
           تسبق الأسبوع. والابن المنقطع لا يكمل درسا هذا الأسبوع بتة —
           وهو ما يجب أن يقرأه وليه. */
        foreach ($done as $n => $lid) {
            if ($k['gap'] > 0 || !$active) {
                /* منقطع: كل دروسه قبل فجوته — ولا يوم نشاط هذا الأسبوع. */
                $at = $now - (max(1, (int) $k['gap']) + $n) * $DAY;
            } elseif ($n < 2) {
                $at = $active[($i + $n) % count($active)] - 1800;
            } elseif ($prev && $n < 4) {
                /* أيام الأسبوع الماضي — منها يقرأ التقرير «ارتفع» أو «نزل».
                   وبدونها يقارن الأسبوع بصفر دائما، فيقول «ارتفع» لكل من
                   درس يوما واحدا. */
                $at = $prev[($i + $n) % count($prev)] - 1800;
            } else {
                $at = $prev_start - (2 + $n) * $DAY;
            }
            if ($at > $now) $at = $now - 3600;

            $run("INSERT INTO `lesson_progress`
                    (student_id, lesson_id, position_sec, watch_seconds, completed_at, mastered_at)
                  VALUES (?, ?, 0, ?, ?, ?)",
                [$k['id'], $lid, 300 + $n * 40, $dt($at), $n % 3 === 0 ? $dt($at) : null]);
        }
    }

    /* النتائج والملاحظات — المعتمدة وحدها يراها ولي الأمر. */
    if ($k['exams'] !== 'none') {
        $quizzes = $ids($all(
            "SELECT id FROM lesson WHERE course_id IN (" . implode(',', $mine) . ")
               AND lesson_type = 'quiz' ORDER BY id LIMIT 6"));

        $notes = $k['exams'] === 'good'
            ? ['عمل ممتاز، واصل على هذا الإيقاع', 'إجابات دقيقة — انتبه لخطوة التبسيط الأخيرة',
               'تحسن واضح عن الاختبار السابق']
            : ['يحتاج إعادة مذاكرة الوحدة الثانية', 'الفكرة صحيحة والتطبيق ناقص — راجع الأمثلة',
               'راجع معي هذا الدرس في الحصة القادمة'];

        foreach ($quizzes as $n => $qid) {
            $qn = (int) $one("SELECT COUNT(*) FROM question WHERE quiz_id = ?", [$qid]);
            if ($qn < 1) continue;

            $ratio = $k['exams'] === 'good' ? [0.95, 0.84, 0.90, 0.76][$n % 4]
                                            : [0.42, 0.35, 0.55, 0.28][$n % 4];
            $mark  = round($qn * $ratio, 2);
            $when  = $now - (3 + $n * 5) * $DAY;

            /* آخر واحد يترك بلا اعتماد: ولي الأمر يقرأ «ينتظر اعتماد
               معلمه» لا رقما لم يره ابنه بعد. */
            $approved = ($n < count($quizzes) - 1);

            $run("INSERT INTO `quiz_results`
                    (quiz_id, user_id, user_answers, correct_answers, total_obtained_marks,
                     date_added, date_updated, is_submitted, teacher_score, teacher_note,
                     approved_at, approved_by)
                  VALUES (?, ?, '[]', '[]', ?, ?, ?, 1, ?, ?, ?, ?)",
                [$qid, $k['id'], $mark, $when, $when,
                 $approved ? $mark : null,
                 $approved ? $notes[$n % count($notes)] : null,
                 $approved ? $when + 3600 : null,
                 $approved ? $teachers[$n % count($teachers)] : null]);
        }
    }

    /* الفهم: أهداف دروس مواده، بمستوى **مئوي** — عتبة الإتقان 80. */
    $objs = $ids($all(
        "SELECT o.id FROM objectives o
           JOIN lesson l ON l.id = o.lesson_id
          WHERE l.course_id IN (" . implode(',', $mine) . ")
          ORDER BY o.id LIMIT 20"));

    $levels = $k['exams'] === 'good' ? [92, 85, 78, 96, 88, 64]
            : ($k['exams'] === 'weak' ? [48, 62, 35, 71, 55, 40]
                                      : [22, 15, 30]);

    foreach ($objs as $n => $oid) {
        $run("INSERT INTO `skill_state`
                (student_id, objective_id, level, forget_rate, last_seen_at, avg_response_ms)
              VALUES (?, ?, ?, 0.0800, ?, ?)",
            [$k['id'], $oid, $levels[$n % count($levels)],
             $dt($now - ($k['gap'] > 0 ? $k['gap'] : 1) * $DAY - $n * 3600), 3800 + $n * 260]);
    }
}

/* ================================================================
   ٤ · الحصص القادمة — بطاقة كانت فارغة دائما
   ================================================================
   الموعد في `availability_slots.starts_at`، والحجز يشير إليه. وحالتان
   تعرضان لولي الأمر: مطلوبة (بانتظار المعلم) ومؤكدة.
*/
$say('٥ · الحصص القادمة (مطلوبة · مؤكدة · منتهية)');

/* `availability_slots` فريد بـ(معلم، وقت البدء): ثلاثة أبناء في الوقت
   نفسه مع المعلم نفسه اصطدام لا بيانات. فلكل ابن إزاحة دقائق خاصة به —
   وهي أصدق أيضا، إذ لا يجلس معلم واحد مع ثلاثة في اللحظة نفسها. */
$kid_slot_no = 0;
foreach ($kids as $k) {
    if ($k['link'] !== 'active' || $k['courses'] < 1) continue;
    $kid_slot_no++;

    $plan = [
        ['+2 days 17:00', 'confirmed'],
        ['+5 days 19:30', 'requested'],
        ['-6 days 18:00', 'completed'],   // منتهية: لا تعرض في «القادمة»
    ];
    foreach ($plan as $n => [$when, $status]) {
        $tid  = $teachers[($n + $kid_slot_no) % count($teachers)];
        $at   = strtotime($when) + $kid_slot_no * 50 * 60;

        /* `uq_slot_teacher_start` يمنع موعدين لمعلم في اللحظة نفسها —
           وهو قيد صحيح لا عقبة: معلم واحد لا يجلس مع اثنين معا.
           فالموعد يكتب أو يستعمل القائم (`LAST_INSERT_ID(id)` يعيد معرف
           الصف الموجود)، ولا يفشل المرور. وبدونه كان أي موعد بقي من
           مرور متعثر سابق يوقف كل مرور بعده. */
        $slot = $insert(
            "INSERT INTO `availability_slots` (teacher_id, starts_at, duration_min, status)
             VALUES (?, ?, 45, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), status = VALUES(status)",
            [$tid, $dt($at), $status === 'requested' ? 'held' : 'booked']);

        $run("INSERT INTO `tutoring_sessions`
                (slot_id, student_id, teacher_id, status, room_id, meet_url)
              VALUES (?, ?, ?, ?, ?, ?)",
            [$slot ?: null, $k['id'], $tid, $status,
             'tq-' . $k['id'] . '-' . $n, 'https://meet.taqdaredu.com/tq-' . $k['id'] . '-' . $n]);
    }
}

/* ================================================================
   ٥ · المال — من مصدريه معا
   ================================================================
   شاشة المدفوعات تدمج `invoices` (بالهللات) و`payment` (بالريالات).
   والحالات الثلاث تبذر: مدفوعة ومعلقة ومستردة — ولكل منها لون وشارة.
*/
$say('٦ · المدفوعات (مدفوعة · بانتظار التحويل · مستردة · عملية Academy)');

$plan_row = $all("SELECT id, name_ar, price FROM plans WHERE active = 1 ORDER BY price DESC LIMIT 1");
$plan_id  = $plan_row ? (int) $plan_row[0]['id'] : 0;
$price    = $plan_row ? (int) $plan_row[0]['price'] : 39900;

$inv_n = (int) $one("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_no,'-',-1) AS UNSIGNED)),0) FROM invoices");

$bill = function ($user_id, $status, $when, $amount) use (
    $insert, $run, $dt, $plan_id, &$inv_n, $now
) {
    $inv_n++;
    $sub = $insert(
        "INSERT INTO `subscriptions`
           (user_id, plan_id, path_id, status, price, started_at, ends_at,
            auto_renew, method, transaction_id, created_at)
         VALUES (?, ?, 0, ?, ?, ?, ?, ?, 'manual', ?, ?)",
        [$user_id, $plan_id,
         $status === 'paid' ? ($when + 90 * 86400 > $now ? 'active' : 'expired') : 'pending',
         $amount, $dt($when), $dt($when + 90 * 86400),
         $status === 'paid' ? 1 : 0,
         'TQ-' . strtoupper(substr(md5($user_id . $when . $status), 0, 10)),
         $dt($when)]);

    $run("INSERT INTO `invoices`
            (invoice_no, subscription_id, user_id, amount, tax, total, status, method,
             transaction_id, issued_at, paid_at)
          VALUES (?, ?, ?, ?, 0, ?, ?, 'manual', ?, ?, ?)",
        ['TQ-' . date('Y', $when) . '-' . str_pad((string) $inv_n, 5, '0', STR_PAD_LEFT),
         $sub ?: 0, $user_id, $amount, $amount, $status,
         $status === 'unpaid' ? '' : 'TRX' . substr(md5($when . $user_id), 0, 12),
         $dt($when), $status === 'unpaid' ? null : $dt($when + 7200)]);
};

$month_start = strtotime(date('Y-m-01 00:00:00'));

/* فاتورتان مدفوعتان لولي الأمر: واحدة هذا الشهر وأخرى قبل شهرين —
   فيمتلئ عدادا «هذا الشهر» و«الإجمالي» بقيمتين مختلفتين. */
$bill($PID, 'paid', $month_start + 3 * $DAY, $price);
$bill($PID, 'paid', $now - 70 * $DAY,        $price);

/* واحدة مستردة: تعرض ولا تجمع في المدفوع. */
$bill($PID, 'refunded', $now - 100 * $DAY, $price);

foreach ($kids as $k) {
    if ($k['link'] !== 'active') continue;
    if ($k['key'] === 'child1') $bill($k['id'], 'paid',   $month_start + $DAY, $price);
    if ($k['key'] === 'child2') $bill($k['id'], 'unpaid', $now - 2 * $DAY,     $price);
    if ($k['key'] === 'child3') $bill($k['id'], 'paid',   $now - 40 * $DAY,    $price);
}

/* عملية Academy قديمة — الدليل على أن الشاشة تدمج المصدرين لا أحدهما. */
$run("INSERT INTO `payment` (user_id, payment_type, course_id, amount, date_added,
                             last_modified, tax, instructor_payment_status, transaction_id)
      VALUES (?, 'bank_transfer', ?, 120, ?, ?, 0, 1, ?)",
    [$PID, $seed_courses[0], $now - 150 * $DAY, $now - 150 * $DAY, 'ACD-9F2C71']);

/* ================================================================
   ٦ · الإشعارات — الأحداث الخمسة وما ينتظر الأحد
   ================================================================
   `created_at` طابع يونكس **نصا** كما يكتبه `Taqdar_events_model`.
   وبعضها غير مقروء ليظهر الجرس في الترويسة وزر «تحديد الكل كمقروء».
*/
$say('٧ · الإشعارات (الخمسة العاجلة + المؤجلة، مقروء وغير مقروء)');

$kid_by = [];
foreach ($kids as $k) $kid_by[$k['key']] = $k;

$alerts = [
    ['exam_result',      'نتيجة اختبار ' . $kid_by['child1']['first'],
     'أنهى ' . $kid_by['child1']['first'] . ' اختبار الوحدة الثالثة بنتيجة 90% — واعتمدها معلمه.', 0, 4 * 3600],
    ['station_failed',   'لم يجتز ' . $kid_by['child2']['first'] . ' اختبار المحطة',
     'نتيجته 42% وعتبة الاجتياز 60%. مراجعة الوحدة الثانية معه تكفي غالبا.', 0, 26 * 3600],
    ['inactivity_3days', 'انقطع ' . $kid_by['child3']['first'] . ' عن الدراسة ثلاثة أيام',
     'آخر نشاط له كان قبل ثلاثة أسابيع. سؤال قصير منك يفتح ما لا تفتحه التقارير.', 0, 2 * $DAY],
    ['session_request',  'طلب حصة خاصة لـ' . $kid_by['child1']['first'],
     'طلب حصة مع معلم الرياضيات، وينتظر تأكيد المعلم.', 1, 3 * $DAY],
    ['certificate',      'شهادة جديدة لـ' . $kid_by['child1']['first'],
     'أنهى محطة «الكسور» واجتاز اختبارها، فصدرت له شهادة برمز تحقق.', 1, 6 * $DAY],

    // ما لا يستحق المقاطعة — يهبط في «ينتظر التقرير الأسبوعي»
    ['weekly_report',    'تقريرك الأسبوعي جاهز',
     'أربعة أسطر عن كل ابن، تقرأ في عشر ثوان.', 1, 5 * $DAY],
    ['course_purchase',  'فتحت مادة جديدة',
     'أضيفت «العلوم — الصف الرابع» إلى باقة أبنائك.', 1, 9 * $DAY],
    ['parent_link_granted', 'وافق ' . $kid_by['child1']['first'] . ' على الربط',
     'تجد متابعته الآن في «أبنائي».', 1, 39 * $DAY],
];

foreach ($alerts as [$type, $title, $body, $read, $ago]) {
    $run("INSERT INTO `notifications` (from_user, to_user, type, title, description, status, created_at, updated_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [null, $PID, $type, $title, $body, $read, (string) ($now - $ago), null]);
}

/* وإشعار للابن نفسه، ليرى الطالب الطرف الآخر من العلاقة. */
if (!empty($kid_by['child1']['id'])) {
    $run("INSERT INTO `notifications` (from_user, to_user, type, title, description, status, created_at, updated_at)
          VALUES (?, ?, 'exam_result', ?, ?, 0, ?, NULL)",
        [null, $kid_by['child1']['id'], 'اعتمدت درجتك',
         'اعتمد معلمك درجة اختبار الوحدة الثالثة.', (string) ($now - 4 * 3600)]);
}

/* ================================================================
   ٧ · تفضيلات ما يصله — محفوظة فعلا لا افتراضية
   ================================================================
   نوع واحد موقوف عمدا: شاشة الإشعارات تعرضه «أوقفته» في القائمة
   الجانبية، فيرى المجرب الفرق بين المفتوح والموقوف.
*/
$say('٨ · تفضيلات الإشعارات (نوع موقوف عمدا)');

foreach (['weekly' => 1, 'exam_result' => 1, 'station_failed' => 1,
          'inactivity_3days' => 1, 'session_request' => 0, 'certificate' => 1] as $type => $on) {
    $run("REPLACE INTO `tq_prefs_notify` (user_id, notify_type, channel, enabled, updated_at)
          VALUES (?, ?, 'portal', ?, ?)", [$PID, $type, $on, $now]);
}

/* ================================================================
   ٨ · الرسائل — خيوط بالحالات الثلاث
   ================================================================
   الشارة في القائمة والترويسة تقرأ `message.read_status = 0` للمستقبل،
   فخيط واحد على الأقل يترك بغير مقروء — وإلا لم ير أحد الشارة تعمل.
*/
$say('٩ · الرسائل (وارد غير مقروء · محادثة مع الإدارة · خيط مقروء)');

$threads = [
    // [الطرف الآخر، [ [من، النص، منذ كم ثانية، مقروء؟] ... ] ]
    [$teachers[0], [
        [$PID,         'السلام عليكم أستاذ. كيف حال سلمان في الرياضيات هذا الأسبوع؟', 3 * $DAY, 1],
        [$teachers[0], 'وعليكم السلام. سلمان متقدم في الوحدة الثالثة، ودرجته الأخيرة 90%.', 3 * $DAY - 5400, 1],
        [$teachers[0], 'أنصح بمراجعة قصيرة على الكسور قبل اختبار المحطة الأسبوع القادم.', 6 * 3600, 0],
    ]],
    [$teachers[1] ?? $teachers[0], [
        [$PID,                        'أستاذة، لينا تجد صعوبة في الوحدة الثانية. بم تنصحين؟', 2 * $DAY, 1],
        [$teachers[1] ?? $teachers[0], 'سأعيد شرح الدرس في حصة الأحد، وأرسل لها تمارين إضافية.', 2 * $DAY - 7200, 0],
    ]],
];
if ($ADM) {
    $threads[] = [$ADM, [
        [$PID, 'مرحبا، أريد الاستفسار عن فاتورة لم تفعل بعد.', 5 * $DAY, 1],
        [$ADM, 'أهلا بك. راجعنا الحوالة وسيفعل الاشتراك خلال ساعات.', 5 * $DAY - 3600, 1],
    ]];
}

foreach ($threads as $n => [$other, $msgs]) {
    $tcode = $code($prefix . 'm' . $n . str_repeat('0', 8));
    $last  = $now - $msgs[count($msgs) - 1][2];

    $run("INSERT INTO `message_thread` (message_thread_code, sender, receiver, last_message_timestamp)
          VALUES (?, ?, ?, ?)", [$tcode, $PID, $other, $last]);

    foreach ($msgs as [$from, $text, $ago, $read]) {
        $run("INSERT INTO `message` (message_thread_code, message, sender, receiver, timestamp, read_status)
              VALUES (?, ?, ?, ?, ?, ?)",
            [$tcode, $text, $from, $from === $PID ? $other : $PID, $now - $ago, $read]);
    }
}

/* ================================================================
   الخلاصة
   ================================================================ */
$say('');
$say(str_repeat('-', 62));
$say('ما يفتحه هذا المرور في بوابة ولي الأمر:');
$say('  /parent           ' . (count($kids) - 2) . ' أبناء مربوطين + طلب معلق + بطاقة إضافة');
$say('  /parent/reports   مواد ونتائج معتمدة، ومادة تنتظر اعتماد معلمها');
$say('  /parent/weekly    الأسطر الأربعة بكل تفريعاتها (ارتفاع · نزول · ثبات · انقطاع)');
$say('  /parent/messages  ' . count($threads) . ' محادثات، فيها غير مقروء تظهر معه الشارة');
$say('  /parent/alerts    الأحداث الخمسة + المؤجلة، ونوع موقوف عمدا');
$say('  /parent/payments  مدفوعة هذا الشهر وقبله · بانتظار التحويل · مستردة · عملية Academy');
$say('  /parent/settings  ثلاث حالات ربط · تفضيلات محفوظة · خطة محددة وغير محددة');
$say('  /parent/child?id= الفهم والحصص القادمة وملاحظات المعلمين — كلها بمحتوى');
$say('');
$say('حسابات الأبناء المبذورة (كلمة المرور Taqdar@Test2026):');
foreach ($kids as $k) {
    $say('  ' . str_pad($k['email'], 34) . ' ' . $k['first'] . ' — رابط ' . $k['link']);
}
$say('');
$say($apply ? "تم. عمليات الكتابة: $writes" : "خطة: $writes عملية. أضف --apply للتنفيذ.");
