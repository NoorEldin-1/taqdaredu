<?php
/**
 * بذرة عرض لبوابة الطالب — بيانات تمر بكل حالة تعرضها الشاشات.
 *
 * الشاشات الثمانية تعرض حالات فارغة صادقة حين لا بيانات، وهو الصواب —
 * لكنه يجعل المراجعة والعرض على العميل عمياء: لا يرى أحد شكل «اختبار جار
 * عداده يعمل» ولا «واجب راسب» ولا «حجز اعتذر عنه المعلم» حتى تقع فعلا.
 * هذا المرور يولد تلك الحالات كلها لحساب واحد.
 *
 * ما يغطى، حالة حالة:
 *   دروسي        · كورس مكتمل · اثنان قيد التقدم · اثنان لم يبدآ
 *                 · ثلاثة مستويات لتمتلئ تصفية المرحلة · خمس مواد
 *   الرئيسية     · نقطة استكمال · مواعيد اليوم وغدا وبعد أيام وبعيدة
 *                 · موعد مضى (يجب أن يختفي) · أرقام نشاط حقيقية
 *   اختباراتي    · قادم · جار عداده يعمل · جار مضى وقته
 *                 · منته معتمد بخمسة تقديرات · منته ينتظر اعتماد المعلم
 *   مهامي        · أربعة لم تبدأ · ثلاثة قيد التنفيذ · خمسة مكتملة (ناجح وراسب)
 *   المراجعة     · مستحق اليوم أكثر من دفعة اليوم · مؤجل غدا · بعيد · متعثر
 *   حصص بالطلب   · مواعيد مفتوحة لستة معلمين · حجوزات بالحالات السبع كلها
 *   محتوى باقتي  · اشتراك نشط وتاريخ اشتراكات (منته وملغى)
 *   التقارير     · ثمانية أسابيع من النشاط لترسم المنحنيات والفروق
 *   القائمة      · رسائل وإشعارات غير مقروءة لتظهر الشارات
 *
 * الاستعمال (من جذر الموقع):
 *     php scripts/seed_demo_student.php                  # عرض الخطة، بلا كتابة
 *     php scripts/seed_demo_student.php --apply          # التنفيذ
 *     php scripts/seed_demo_student.php --apply --student=290
 *     php scripts/seed_demo_student.php --apply --clear  # حذف البذرة وحدها
 *     php scripts/seed_demo_student.php --apply --demo-teachers   # يفعل حسابات المعلمين
 *
 * ── ما لا يمسه هذا المرور ────────────────────────────────────────────────
 * كل عملية حذف أو تعديل هنا **مقيدة بأثر هذا الطالب وحده**. والقيود
 * مقصودة لأن السكربت يشغل على قاعدة الإنتاج أيضا، وفيها طلاب حقيقيون:
 *
 *   • الواجبات: لا يحذف إلا ما لا محاولة عليه من طالب آخر.
 *   • مواعيد الوحدات: لا يفرغ إلا مواعيد وحدات الكورسات التي يبذرها،
 *     ولا يقترب من مواعيد بقية المنصة.
 *   • مستوى الكورس: لا يكتب إلا إن كان العمود فارغا أصلا — المستوى محتوى
 *     يكتبه صاحبه، لا حقل عرض.
 *   • مواعيد المعلمين: لا يحذف موعدا عليه حجز لطالب آخر.
 *   • تفعيل حسابات المعلمين: **لا يتم إلا بـ`--demo-teachers` صراحة** —
 *     تفعيل حساب على الموقع الحي يظهر صاحبه للناس، وهذا قرار إدارة لا
 *     أثر جانبي لمرور بيانات.
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
$ids = function ($rows, $key = 'id') {
    return array_map(static function ($r) use ($key) { return (int) $r[$key]; }, $rows);
};
$dt  = static function ($ts) { return date('Y-m-d H:i:s', $ts); };
$say = static function ($s = '') { echo $s, "\n"; };

/* ---------- الحساب المستهدف ---------- */
$SID = $want ?: (int) $one("SELECT id FROM users WHERE email = ? LIMIT 1", ['student.test@taqdaredu.com']);
if (!$SID) {
    exit("لا حساب عرض. مرر --student=<معرف الطالب>\n");
}
$who = $all("SELECT id, email, first_name, last_name, role_id, is_instructor FROM users WHERE id = ?", [$SID]);
if (!$who) exit("لا مستخدم بالمعرف $SID\n");
$who = $who[0];
if ((int) $who['role_id'] === 1 || (int) $who['is_instructor'] === 1) {
    exit("المعرف $SID ليس حساب طالب (أدمن أو معلم). أوقف.\n");
}

$PID = (int) $one("SELECT id FROM users WHERE email = ? LIMIT 1", ['parent.test@taqdaredu.com']);
$TQA = (int) $one("SELECT id FROM users WHERE email = ? LIMIT 1", ['teacher.test@taqdaredu.com']);
$ADM = (int) $one("SELECT id FROM users WHERE role_id = 1 ORDER BY id LIMIT 1");

$now = time();
$DAY = 86400;

$say('');
$say('حساب العرض : ' . $who['first_name'] . ' ' . $who['last_name'] . '  (#' . $SID . ' · ' . $who['email'] . ')');
$say('قاعدة       : ' . $conf['database'] . ' @ ' . $conf['hostname']);
$say('الوضع       : ' . ($apply ? ($clear ? 'حذف البذرة' : 'تنفيذ') : 'عرض الخطة فقط (بلا كتابة)'));
$say(str_repeat('-', 62));

/* ---------- الكورسات المستهدفة: تعرف قبل الحذف ليقيد الحذف بها ---------- */
$courses = $all(
    "SELECT c.id, c.title, p.subject_id, s.name_ar AS subject
       FROM course c
       JOIN paths p   ON p.course_id = c.id
       JOIN subjects s ON s.id = p.subject_id
      WHERE c.status = 'active'
        AND (SELECT COUNT(*) FROM lesson l WHERE l.course_id = c.id AND l.lesson_type != 'quiz') >= 5
      ORDER BY c.id
      LIMIT 5");
if (count($courses) < 3) exit("لا كورسات منشورة كافية للبذر.\n");
$seed_courses = $ids($courses);
$in_courses   = implode(',', $seed_courses);

/* ================================================================
   ٠ · حذف البذرة السابقة — وهي كل ما يخص هذا الطالب من أثر تعلم
   ================================================================ */
$say('١ · حذف أثر البذرة السابقة');
foreach ([
    'enrol'              => 'user_id',
    'watch_histories'    => 'student_id',
    'watched_duration'   => 'watched_student_id',
    'lesson_progress'    => 'student_id',
    'quiz_results'       => 'user_id',
    'attempts'           => 'student_id',
    'review_queue'       => 'student_id',
    'skill_state'        => 'student_id',
    'tutoring_sessions'  => 'student_id',
    'subscriptions'      => 'user_id',
    'notifications'      => 'to_user',
] as $table => $colname) {
    $run("DELETE FROM `$table` WHERE `$colname` = ?", [$SID]);
}
$run("DELETE FROM `message` WHERE `receiver` = ? OR `sender` = ?", [$SID, $SID]);
$run("DELETE FROM `message_thread` WHERE `message_thread_code` LIKE ?", ['tqseed' . $SID . '%']);

/* الواجبات: في دروس الكورسات المبذورة وحدها، وبشرط ألا يكون عليها محاولة
   لطالب آخر. `DELETE ... WHERE type='homework'` بلا قيد كان يمحو واجبات
   المنصة كلها — وعلى الإنتاج يعني محو عمل معلمين وطلاب حقيقيين. */
$run("DELETE a FROM `assessments` a
        JOIN `lesson` l ON l.id = a.lesson_id
       WHERE a.type = 'homework'
         AND l.course_id IN ($in_courses)
         AND NOT EXISTS (SELECT 1 FROM `attempts` t WHERE t.assessment_id = a.id AND t.student_id <> ?)",
    [$SID]);

/* مواعيد الوحدات: وحدات الكورسات المبذورة وحدها. */
$run("UPDATE `section` SET `end_date` = NULL
       WHERE `end_date` IS NOT NULL AND `course_id` IN ($in_courses)");

if ($PID) $run("DELETE FROM `parent_links` WHERE `parent_user_id` = ? AND `student_id` = ?", [$PID, $SID]);

if ($clear) {
    $say('');
    $say($apply ? "حذفت البذرة ($writes عملية)." : "الخطة: $writes عملية حذف. أضف --apply للتنفيذ.");
    exit(0);
}

/* ================================================================
   ١ · المعلمون: تفعيل حسابات العرض ليظهروا في «حصص بالطلب»
   ================================================================ */
$demo_teachers = in_array('--demo-teachers', $argv, true);
$say('٢ · معلمو المسارات');
$teachers = $ids($all(
    "SELECT DISTINCT u.id
       FROM paths p JOIN users u ON u.id = p.teacher_id
      WHERE p.teacher_id > 0 AND u.is_instructor = 1 AND u.role_id != 1"));

/* التفعيل قرار إدارة لا أثر جانبي: حساب يفعل على الموقع الحي يظهر صاحبه
   للناس وتفتح له بوابته. فلا يفعل إلا بطلب صريح. */
$off = $teachers ? $ids($all("SELECT id FROM users WHERE id IN (" . implode(',', $teachers) . ") AND status != 1")) : [];
if ($off && $demo_teachers) {
    $run("UPDATE `users` SET `status` = 1 WHERE `id` IN (" . implode(',', $off) . ")");
    $say('   فعلت ' . count($off) . ' حساب معلم (--demo-teachers)');
} elseif ($off) {
    $say('   ' . count($off) . ' حساب معلم معطل — لن يظهروا في «حصص بالطلب».');
    $say('   للتفعيل: أضف --demo-teachers، أو فعلهم من لوحة الإدارة.');
}
/* المعطلون يستبعدون: موعد لمعلم معطل لا يعرض، فبذره أثر ميت. */
$teachers = array_values(array_diff($teachers, $demo_teachers ? [] : $off));
if ($TQA) $teachers[] = $TQA;
$teachers = array_values(array_unique($teachers));
$say('   معلمون فاعلون: ' . count($teachers));

/* ================================================================
   ٢ · الكورسات: التسجيل والحالات الثلاث
   ================================================================ */
$say('٣ · التسجيل في ' . count($courses) . ' كورسات وحالاتها');

/* مستوى مختلف لكل كورس لتمتلئ تصفية المرحلة */
$levels = ['beginner', 'beginner', 'intermediate', 'intermediate', 'advanced'];
/* الحالة المقصودة: مكتمل · قيد التقدم (الأحدث نشاطا) · قيد التقدم · لم يبدأ ×2 */
$pcts   = [100, 55, 20, 0, 0];
$aged   = [0, 0, 1, 3, 4];   // بأسابيع

$c_state = [];
foreach ($courses as $i => $c) {
    $cid = (int) $c['id'];
    /* المستوى محتوى يكتبه صاحب الكورس، لا حقل عرض. فلا يكتب إلا على
       الفارغ — ولا يدهس ما كتبه معلم على الإنتاج. */
    $run("UPDATE `course` SET `level` = ? WHERE `id` = ? AND (`level` IS NULL OR `level` = '')",
        [$levels[$i % 5], $cid]);

    $en = $now - (60 - $i * 5) * $DAY;
    $run("INSERT INTO `enrol` (user_id, course_id, gifted_by, expiry_date, date_added, last_modified)
          VALUES (?, ?, 0, '', ?, ?)", [$SID, $cid, $en, $en]);

    $lessons = $ids($all("SELECT id FROM lesson WHERE course_id = ? AND lesson_type != 'quiz' ORDER BY `order`, id", [$cid]));
    $total   = count($lessons);
    if ($total === 0) continue;

    $pct     = $pcts[$i % 5];
    $ndone   = (int) round($total * $pct / 100);
    $done    = array_slice($lessons, 0, $ndone);
    $resume  = $ndone < $total ? $lessons[$ndone] : $lessons[$total - 1];
    $touched = $now - $aged[$i % 5] * 7 * $DAY - $i * 3 * 3600;

    $run("INSERT INTO `watch_histories`
            (course_id, student_id, completed_lesson, course_progress, watching_lesson_id, quiz_result, completed_date, date_added, date_updated)
          VALUES (?, ?, ?, ?, ?, '[]', ?, ?, ?)",
        [$cid, $SID, json_encode(array_values($done)), $pct, $resume,
         $pct >= 100 ? $dt($touched) : '', $en, $touched]);

    /* دقائق مشاهدة + طوابع إكمال موزعة على ثمانية أسابيع لترسم منحنيات التقارير */
    foreach ($done as $k => $lid) {
        $secs = 420 + ($k % 7) * 90;
        $run("INSERT INTO `watched_duration` (watched_student_id, watched_course_id, watched_lesson_id, current_duration, watched_counter)
              VALUES (?, ?, ?, ?, '[]')", [$SID, $cid, $lid, $secs]);

        $weeks_ago = max(0, 7 - (int) floor($k * 7 / max(1, $ndone)));
        $ts = $now - $weeks_ago * 7 * $DAY - ($k % 5) * $DAY - 3600;
        $run("INSERT INTO `lesson_progress` (student_id, lesson_id, position_sec, watch_seconds, completed_at, mastered_at)
              VALUES (?, ?, ?, ?, ?, ?)", [$SID, $lid, $secs, $secs, $dt($ts), $dt($ts)]);
    }
    $c_state[$i] = $cid;
}

$c_done = $c_state[0] ?? (int) $courses[0]['id'];
$c_main = $c_state[1] ?? $c_done;
$c_slow = $c_state[2] ?? $c_main;

/* ================================================================
   ٣ · مواعيد الوحدات: اليوم · غدا · بعد أيام · بعيد · ومنقض
   ================================================================ */
$say('٤ · مواعيد الوحدات (اليوم · غدا · بعيد · منقض)');
$secs = array_merge(
    $ids($all("SELECT id FROM section WHERE course_id = ? ORDER BY id", [$c_main])),
    $ids($all("SELECT id FROM section WHERE course_id = ? ORDER BY id", [$c_slow]))
);
foreach ([0, 1, 2, 6, 20] as $k => $days) {
    if (!isset($secs[$k])) break;
    $run("UPDATE `section` SET `end_date` = ? WHERE `id` = ?", [date('Y-m-d', $now + $days * $DAY), $secs[$k]]);
}
/* موعد مضى — يجب أن يختفي من «المواعيد القادمة» */
if (isset($secs[6])) {
    $run("UPDATE `section` SET `end_date` = ? WHERE `id` = ?", [date('Y-m-d', $now - 9 * $DAY), $secs[6]]);
}

/* ================================================================
   ٤ · الاختبارات
   ================================================================ */
$say('٥ · الاختبارات (قادم · جاريان · ستة منتهية)');
$quiz_ids = $ids($all(
    "SELECT id FROM lesson WHERE course_id IN (?, ?, ?) AND lesson_type = 'quiz' ORDER BY course_id, `order`, id",
    [$c_done, $c_main, $c_slow]));

$qn = function ($lid) use ($one) { return max(1, (int) $one("SELECT COUNT(*) FROM question WHERE quiz_id = ?", [$lid])); };

/* درجات تغطي التقديرات الخمسة، وواحد ينتظر اعتماد المعلم */
$finished = [
    [95, 0, true], [84, 1, true], [72, 2, true], [58, 4, true], [35, 6, true], [66, 0, false],
];
$qi = 0;
foreach ($finished as [$pct, $weeks, $approved]) {
    if (!isset($quiz_ids[$qi])) break;
    $lid   = $quiz_ids[$qi++];
    $n     = $qn($lid);
    $marks = round($n * $pct / 100, 2);
    $ans   = json_encode(array_fill(0, $n, 'a'));
    $ts    = $now - $weeks * 7 * $DAY - 5 * 3600;
    $note  = $approved ? ($pct >= 80 ? 'عمل ممتاز، واصل' : ($pct >= 50 ? 'جيد، راجع الوحدة الثانية' : 'يحتاج إعادة مذاكرة')) : '';
    $run("INSERT INTO `quiz_results`
            (quiz_id, user_id, user_answers, correct_answers, total_obtained_marks, date_added, date_updated,
             is_submitted, teacher_score, teacher_note, approved_at, approved_by)
          VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)",
        [$lid, $SID, $ans, $ans, $marks, $ts, $ts,
         $approved ? $marks : null, $note,
         $approved ? $ts + 3600 : null, $approved ? ($TQA ?: null) : null]);
}

/* جاريان: واحد عداده يعمل وواحد مضى وقته */
$live = [];
foreach ([$now - 20 * 60, $now - 30 * 3600] as $started) {
    if (!isset($quiz_ids[$qi])) break;
    $lid = $quiz_ids[$qi++];
    $live[] = $lid;
    $n   = $qn($lid);
    $ans = json_encode(array_fill(0, $n, 'a'));
    $run("INSERT INTO `quiz_results`
            (quiz_id, user_id, user_answers, correct_answers, total_obtained_marks, date_added, date_updated,
             is_submitted, teacher_score, teacher_note, approved_at, approved_by)
          VALUES (?, ?, ?, ?, 0, ?, ?, 0, NULL, '', NULL, NULL)",
        [$lid, $SID, $ans, $ans, $started, $started]);
}
/* والباقي بلا نتيجة = قادم */

/* مدد: بعضها معلنة وبعضها بلا مدة (فيعرض بلا عداد) */
foreach ($quiz_ids as $k => $lid) {
    if ($k % 3 === 2) continue;
    $run("UPDATE `assessments` SET `time_limit_sec` = ? WHERE `lesson_id` = ? AND `type` = 'quiz'",
        [600 + ($k % 4) * 300, $lid]);
}
/* الأول من الجاريين مدته طويلة عمدا فيبقى «عداده يعمل» ساعات بعد البذر
   لا دقائق، والثاني قصير فيظل مثالا لمحاولة مضى وقتها. */
if (isset($live[0])) $run("UPDATE `assessments` SET `time_limit_sec` = 43200 WHERE `lesson_id` = ? AND `type` = 'quiz'", [$live[0]]);
if (isset($live[1])) $run("UPDATE `assessments` SET `time_limit_sec` = 1800  WHERE `lesson_id` = ? AND `type` = 'quiz'", [$live[1]]);

/* ================================================================
   ٥ · الواجبات
   ================================================================ */
$say('٦ · الواجبات (٤ لم تبدأ · ٣ قيد التنفيذ · ٥ مكتملة)');
$hw = [];
foreach ([$c_main, $c_slow, $c_done] as $cid) {
    foreach ($ids($all("SELECT id FROM lesson WHERE course_id = ? AND lesson_type != 'quiz' ORDER BY `order`, id LIMIT 4", [$cid])) as $lid) {
        $hw[] = $lid;
    }
}
$hw = array_slice($hw, 0, 12);
foreach ($hw as $k => $lid) {
    $run("INSERT INTO `assessments` (type, lesson_id, milestone_id, path_id, pass_mark, time_limit_sec)
          VALUES ('homework', ?, 0, 0, ?, ?)", [$lid, [50, 60, 70][$k % 3], [15, 30, 45, 60][$k % 4] * 60]);
}
if ($apply) {
    $hw_ids = $ids($all("SELECT id FROM assessments WHERE type = 'homework' ORDER BY id"));
    foreach (array_slice($hw_ids, 4, 3) as $k => $aid) {          // قيد التنفيذ
        $run("INSERT INTO `attempts` (assessment_id, student_id, score, passed, attempt_no, started_at, submitted_at)
              VALUES (?, ?, NULL, NULL, 1, ?, NULL)", [$aid, $SID, $dt($now - ($k + 1) * 5 * 3600)]);
    }
    $marks = [[9, 1], [7, 1], [4, 0], [10, 1], [3, 0]];           // ناجح وراسب
    foreach (array_slice($hw_ids, 7, 5) as $k => $aid) {
        [$score, $passed] = $marks[$k % 5];
        $ts = $now - ($k * 2 + 1) * $DAY - 2 * 3600;
        $run("INSERT INTO `attempts` (assessment_id, student_id, score, passed, attempt_no, started_at, submitted_at)
              VALUES (?, ?, ?, ?, 1, ?, ?)", [$aid, $SID, $score, $passed, $dt($ts - 1800), $dt($ts)]);
    }
}

/* ================================================================
   ٦ · طابور المراجعة
   ================================================================ */
$say('٧ · طابور المراجعة (١٤ مستحقا اليوم — أكثر من دفعة اليوم)');
$qs = $ids($all(
    "SELECT q.id FROM question q JOIN lesson l ON l.id = q.quiz_id
      WHERE l.course_id IN (?, ?, ?) ORDER BY q.id LIMIT 30", [$c_done, $c_main, $c_slow]));
if (count($qs) < 10) $qs = $ids($all("SELECT id FROM question ORDER BY id LIMIT 30"));

foreach ($qs as $k => $qid) {
    if ($k < 14) {                                   // مستحق اليوم
        $due = $dt($now - (3600 + $k * 60));
        $int = [1, 3, 7][$k % 3];
        $lap = ($k % 5 === 0) ? 2 : 0;               // بعضها تعثر فيه من قبل
        $ease = $lap ? 1.90 : 2.50;
    } elseif ($k < 20) {                             // غدا وبعده
        $due = $dt($now + ($k - 13) * $DAY); $int = 3; $lap = 0; $ease = 2.60;
    } else {                                         // بعيد
        $due = $dt($now + (10 + $k) * $DAY); $int = 30; $lap = 0; $ease = 2.80;
    }
    $run("INSERT INTO `review_queue` (student_id, question_id, due_at, interval_days, ease, lapses)
          VALUES (?, ?, ?, ?, ?, ?)", [$SID, $qid, $due, $int, $ease, $lap]);
}

foreach ($ids($all("SELECT id FROM objectives ORDER BY id LIMIT 12")) as $k => $oid) {
    $run("INSERT INTO `skill_state` (student_id, objective_id, level, forget_rate, last_seen_at, avg_response_ms)
          VALUES (?, ?, ?, 0.0500, ?, ?)",
        [$SID, $oid, [0.35, 0.55, 0.72, 0.88, 0.95][$k % 5], $dt($now - ($k + 1) * $DAY), 4000 + $k * 350]);
}

/* ================================================================
   ٧ · حصص بالطلب — مواعيد + حجوزات بالحالات السبع
   ================================================================ */
$say('٨ · حصص بالطلب (مواعيد مفتوحة + سبع حالات حجز)');
/* لا يحذف موعدا عليه حجز لطالب آخر: الموعد المحذوف يترك طلب طالب حقيقي
   معلقا بلا موعد، وهو أسوأ من بذرة ناقصة. */
$run("DELETE s FROM `availability_slots` s
       WHERE s.teacher_id IN (" . implode(',', $teachers ?: [0]) . ")
         AND NOT EXISTS (SELECT 1 FROM `tutoring_sessions` t
                          WHERE t.slot_id = s.id AND t.student_id <> ?)", [$SID]);

$hours = [8, 12, 16];
if ($apply) {
    foreach ($teachers as $ti => $tid) {
        for ($d = 1; $d <= 4; $d++) {
            $h = $hours[($ti + $d) % 3];
            $run("INSERT IGNORE INTO `availability_slots` (teacher_id, starts_at, duration_min, status)
                  VALUES (?, ?, ?, 'open')",
                [$tid, date('Y-m-d', $now + $d * $DAY) . sprintf(' %02d:00:00', $h), $h === 16 ? 300 : 240]);
        }
    }
    foreach (array_slice($teachers, 0, 3) as $k => $tid) {   // مواعيد ماضية للحجوزات المنتهية
        $run("INSERT IGNORE INTO `availability_slots` (teacher_id, starts_at, duration_min, status)
              VALUES (?, ?, 300, 'booked')", [$tid, date('Y-m-d', $now - ($k + 2) * $DAY) . ' 16:00:00']);
    }

    $future = $all("SELECT id, teacher_id FROM availability_slots WHERE starts_at > NOW() ORDER BY id");
    $past   = $all("SELECT id, teacher_id FROM availability_slots WHERE starts_at <= NOW() ORDER BY id");

    $bookings = [
        ['requested', 'future', 0], ['confirmed', 'future', 1], ['live', 'future', 2],
        ['declined', 'future', 3], ['expired', 'future', 4],
        ['completed', 'past', 0],   ['refunded', 'past', 1],
    ];
    foreach ($bookings as [$status, $pool, $idx]) {
        $src = $pool === 'future' ? $future : $past;
        if (!isset($src[$idx])) continue;
        $s = $src[$idx];
        $run("INSERT INTO `tutoring_sessions` (slot_id, student_id, teacher_id, status, room_id, recording_key, context_objective_id)
              VALUES (?, ?, ?, ?, '', '', 0)", [$s['id'], $SID, $s['teacher_id'], $status]);
        if ($pool === 'future') {
            $slot_state = in_array($status, ['confirmed', 'live'], true) ? 'booked'
                        : (in_array($status, ['declined', 'expired'], true) ? 'open' : 'held');
            $run("UPDATE `availability_slots` SET `status` = ? WHERE `id` = ?", [$slot_state, $s['id']]);
        }
    }
}

/* ================================================================
   ٨ · الاشتراكات: نشط + تاريخ
   ================================================================ */
$say('٩ · الاشتراكات (نشط + منته + ملغى)');
$plan_paid = (int) $one("SELECT id FROM plans WHERE scope = 'grade' AND active = 1 ORDER BY price ASC LIMIT 1");
$plan_free = (int) $one("SELECT id FROM plans WHERE code = 'free' LIMIT 1");
if (!$plan_paid) $plan_paid = (int) $one("SELECT id FROM plans WHERE active = 1 ORDER BY id LIMIT 1");

foreach ([
    [$plan_free ?: $plan_paid, 'expired',   -400, -370, 0],
    [$plan_paid, 'cancelled', -360, -180, 39900],
    [$plan_paid, 'expired',   -175, -20,  39900],
    [$plan_paid, 'active',    -12,  78,   39900],
] as [$pid, $status, $from, $to, $price]) {
    $run("INSERT INTO `subscriptions`
            (user_id, plan_id, path_id, status, price, started_at, ends_at, auto_renew, method, transaction_id, cancelled_at, cancel_reason, created_at)
          VALUES (?, ?, 0, ?, ?, ?, ?, ?, 'manual', ?, ?, ?, ?)",
        [$SID, $pid, $status, $price, $dt($now + $from * $DAY), $dt($now + $to * $DAY),
         $status === 'active' ? 1 : 0,
         'TQ-' . strtoupper(substr(md5($SID . $from), 0, 10)),
         $status === 'cancelled' ? $dt($now + ($to - 30) * $DAY) : null,
         $status === 'cancelled' ? 'غير الطالب باقته' : null,
         $dt($now + $from * $DAY)]);
}

/* ================================================================
   ٩ · الرسائل والإشعارات — لتظهر شارات القائمة والترويسة
   ================================================================ */
$say('١٠ · رسائل وإشعارات غير مقروءة');
$sender = $TQA ?: $ADM;
foreach ([
    [$sender, 'أحسنت في اختبار الوحدة الثالثة. راجع تمارين الكسور قبل الوحدة القادمة.', 3600, 0],
    [$sender, 'وصلني واجبك، وسأصححه اليوم بإذن الله.', 26 * 3600, 0],
    [$ADM,    'أهلا بك في تقدر. لو احتجت أي مساعدة راسلنا في أي وقت.', 20 * $DAY, 1],
] as $k => [$from, $body, $ago, $read]) {
    if (!$from) continue;
    $code = 'tqseed' . $SID . '_' . $from . '_' . $k;
    $ts   = $now - $ago;
    $run("INSERT INTO `message_thread` (message_thread_code, sender, receiver, last_message_timestamp)
          VALUES (?, ?, ?, ?)", [$code, (string) $from, (string) $SID, (string) $ts]);
    $run("INSERT INTO `message` (message_thread_code, message, sender, receiver, timestamp, read_status)
          VALUES (?, ?, ?, ?, ?, ?)", [$code, $body, $from, $SID, (string) $ts, $read]);
}

foreach ([
    ['grade',   'اعتمدت درجتك',          'اعتمد معلمك درجة اختبار الوحدة الأولى.',            2 * 3600,  0],
    ['session', 'طلب حصتك قيد المراجعة', 'أرسلنا طلبك إلى المعلم، وسنخبرك فور تأكيده.',       5 * 3600,  0],
    ['review',  'مراجعة اليوم جاهزة',    'عندك أسئلة حل موعد مراجعتها اليوم. عشر دقائق تكفي.', 9 * 3600,  0],
    ['task',    'واجب جديد',             'أسند إليك واجب في إحدى موادك.',                      2 * $DAY,  1],
    ['billing', 'تجدد اشتراكك',          'فعلنا اشتراكك، وتظهر مدته في صفحة الاشتراك.',        12 * $DAY, 1],
] as [$type, $title, $desc, $ago, $read]) {
    $ts = $now - $ago;
    $run("INSERT INTO `notifications` (from_user, to_user, type, title, description, status, created_at, updated_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$ADM ?: 0, $SID, $type, $title, $desc, $read, (string) $ts, (string) $ts]);
}

/* ================================================================
   ١٠ · ربط ولي الأمر
   ================================================================ */
if ($PID) {
    $say('١١ · ربط ولي الأمر بالطالب');
    $run("INSERT INTO `parent_links` (parent_user_id, student_id, consent_at, status, scope)
          VALUES (?, ?, ?, 'active', ?)",
        [$PID, $SID, $dt($now - 30 * $DAY), '{"reports":true,"payments":true}']);
}

/* ================================================================
   الخلاصة
   ================================================================ */
$say('');
$say(str_repeat('-', 62));
if (!$apply) {
    $say("الخطة: $writes عملية كتابة. أضف --apply للتنفيذ.");
    exit(0);
}

foreach ([
    'كورسات مسجلة'       => "SELECT COUNT(*) FROM enrol WHERE user_id = $SID",
    'دروس مكتملة'        => "SELECT COUNT(*) FROM lesson_progress WHERE student_id = $SID AND completed_at IS NOT NULL",
    'نتائج اختبارات'     => "SELECT COUNT(*) FROM quiz_results WHERE user_id = $SID",
    'منها جارية'         => "SELECT COUNT(*) FROM quiz_results WHERE user_id = $SID AND is_submitted = 0",
    'واجبات'             => "SELECT COUNT(*) FROM assessments WHERE type = 'homework'",
    'محاولات واجبات'     => "SELECT COUNT(*) FROM attempts WHERE student_id = $SID",
    'طابور مراجعة'       => "SELECT COUNT(*) FROM review_queue WHERE student_id = $SID",
    'مستحق اليوم'        => "SELECT COUNT(*) FROM review_queue WHERE student_id = $SID AND due_at <= NOW()",
    'مواعيد معلمين'      => "SELECT COUNT(*) FROM availability_slots",
    'حجوزات'             => "SELECT COUNT(*) FROM tutoring_sessions WHERE student_id = $SID",
    'اشتراكات'           => "SELECT COUNT(*) FROM subscriptions WHERE user_id = $SID",
    'رسائل غير مقروءة'   => "SELECT COUNT(*) FROM message WHERE receiver = $SID AND read_status = 0",
    'إشعارات غير مقروءة' => "SELECT COUNT(*) FROM notifications WHERE to_user = $SID AND status = 0",
    'مواعيد وحدات'       => "SELECT COUNT(*) FROM section WHERE end_date IS NOT NULL",
] as $label => $sql) {
    printf("%-22s %s\n", $label, $one($sql));
}
$say('');
$say("تمت البذرة ($writes عملية).");
