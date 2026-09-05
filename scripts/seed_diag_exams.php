<?php
/**
 * بنك الاختبار التشخيصي — اختبار تحديد المستوى لكل صف.
 *
 * النظام مبني ومربوط بالتسجيل: `Taqdar_diag_model::gate()` تعترض كل شاشة
 * في بوابة الطالب وتحوله الى `student/placement`. لكنها ترد `null` بصمت
 * لمن لا اختبار منشور لصفه — فثمانية صفوف من تسعة كان طلابها لا يرون
 * شيئا، ولا خطأ يظهر في اي مكان يشي بذلك.
 *
 * وهذا المرور يملأ ما بني. لا يغير مخططا ولا منطقا ولا شاشة.
 *
 * ── ما تفرضه البنية، وبني عليه هذا الملف ────────────────────────────────
 *
 *   • `tq_diag_exams` عليه `UNIQUE KEY uq_diag_grade` ⇒ **اختبار واحد لكل
 *     صف**، لا اختبار لكل مادة. فالمواد الخمس تلتقي في اختبار واحد،
 *     و`subject` هنا للتنظيم والتقرير فقط — لا عمود له في القاعدة.
 *
 *   • `ordered_questions()` ترد **كل** اسئلة الاختبار: لا سحب عينة في
 *     النظام. فطول الاختبار هو حجم البنك، ولذلك الاطوال متدرجة بالعمر
 *     (١٥ · ٢٥ · ٣٥) لا موحدة.
 *
 *   • `grade_attempt()` يقرأ المستويات من الاعلى نزولا وياخذ اول مستوى
 *     بلغت نسبته `level_threshold`. فمستوى بثلاثة اسئلة يعطي قفزات خشنة
 *     (٢ من ٣ = ٦٦٪)، ولا مستوى هنا دون اربعة.
 *
 *   • التصحيح **بمطابقة نص الخيار حرفيا** (`in_array($given[0], $correct,
 *     true)` في `Taqdar_repo_model::is_answer_correct()`). فمسافة طرفية
 *     واحدة تفشل السؤال صامتا — وهو ما اصاب السؤال القائم «٢+٥» الذي
 *     سجلت اجابته «١» لا «٧». والفحص ١ ادناه يمنع تكراره.
 *
 * الاستعمال (من جذر الموقع):
 *     php scripts/seed_diag_exams.php                  # الخطة والفحص، بلا كتابة
 *     php scripts/seed_diag_exams.php --check          # الفحص وحده
 *     php scripts/seed_diag_exams.php --apply          # التنفيذ
 *     php scripts/seed_diag_exams.php --apply --grade=7
 *     php scripts/seed_diag_exams.php --apply --clear  # حذف اسئلة الصفوف المدارة
 *
 * ── ما لا يمسه هذا المرور ───────────────────────────────────────────────
 *   • لا ينشئ جدولا ولا عمودا ولا يعدل مخططا.
 *   • لا يمس `tq_diag_attempts` ولا `tq_diag_answers` — محاولات الطلاب
 *     تبقى كما هي. (و`tq_diag_answers` تكتب ولا تقرأ في اي موضع، فحذف
 *     سؤال قديم لا ييتم شيئا معروضا.)
 *   • لا يمس الصفوف الثانوية (٢٠ · ٢١ · ٢٢) — خارج النطاق.
 *   • الاختبار لا ينشر الا بعد اجتياز فحص الجهوزية نفسه الذي تشترطه
 *     `Taqdar_diag_model::readiness()`.
 *
 * مأمون التكرار: يمسح اسئلة الصف ثم يكتبها من جديد.
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
$only  = 0;
foreach ($argv as $a) {
    if (strpos($a, '--grade=') === 0) $only = (int) substr($a, 8);
}
/* `--check` لا يكتب مهما قيل معه: الفحص وضع قراءة. */
if (in_array('--check', $argv, true)) { $apply = false; $clear = false; }

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $conf['hostname'], $conf['database']),
    $conf['username'],
    $conf['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$writes = 0;
$run = function ($sql, $args = []) use ($pdo, $apply, &$writes) {
    $writes++;
    if (!$apply) return 0;
    $st = $pdo->prepare($sql); $st->execute($args); return $st->rowCount();
};
$insert = function ($sql, $args = []) use ($pdo, $apply, &$writes) {
    $writes++;
    if (!$apply) return 0;
    $st = $pdo->prepare($sql); $st->execute($args); return (int) $pdo->lastInsertId();
};
$all = function ($sql, $args = []) use ($pdo) {
    $st = $pdo->prepare($sql); $st->execute($args); return $st->fetchAll();
};
$one = function ($sql, $args = []) use ($pdo) {
    $st = $pdo->prepare($sql); $st->execute($args);
    $r = $st->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null;
};
$say = static function ($s = '') { echo $s, "\n"; };

/* =====================================================================
   الصفوف المدارة وباقاتها
   ---------------------------------------------------------------------
   الربط بالنمط القائم في اختبار الصف الاول: المبتدئ والمتوسط الى
   الاساسية، والمتقدم الى المميزة. وقرار المالك.
   ===================================================================== */
$GRADES = [
    7  => ['stage' => 'primary', 'len' => 15],
    8  => ['stage' => 'primary', 'len' => 15],
    9  => ['stage' => 'primary', 'len' => 25],
    10 => ['stage' => 'primary', 'len' => 25],
    15 => ['stage' => 'primary', 'len' => 25],
    16 => ['stage' => 'primary', 'len' => 25],
    17 => ['stage' => 'middle',  'len' => 35],
    18 => ['stage' => 'middle',  'len' => 35],
    19 => ['stage' => 'middle',  'len' => 35],
];
$PLANS = [
    'primary' => ['beginner' => 8,  'intermediate' => 8,  'advanced' => 9],
    'middle'  => ['beginner' => 11, 'intermediate' => 11, 'advanced' => 12],
];
$LEVELS = ['beginner', 'intermediate', 'advanced'];

/* عتبة الاختبارات الجديدة = افتراض النظام. واختبار الصف الاول القائم
   يبقى على ٥٠ — لم يؤذن بتغيير اعداداته، والتباين مرصود لا مصلح. */
$NEW_THRESHOLD = 60;

/* =====================================================================
   التحقق من القاعدة نفسها (--verify)
   ---------------------------------------------------------------------
   الفحص السابق يقرأ ملفات البيانات. وهذا يقرأ ما **كتب فعلا**، لان بين
   الاثنين ترميز JSON — وهو موضع العطب الذي اصاب السؤال «٢+٥». فما يفحص
   هنا هو بالضبط ما ستراه `is_answer_correct()` عند التصحيح.
   ===================================================================== */
if (in_array('--verify', $argv, true)) {
    $bad = 0; $ok = 0;
    $rows = $all('SELECT e.id AS eid, e.grade_id, e.status, e.level_threshold,
                         e.plan_beginner, e.plan_intermediate, e.plan_advanced,
                         g.name_ar AS gname
                  FROM tq_diag_exams e LEFT JOIN grades g ON g.id = e.grade_id
                  ORDER BY g.`order`');
    foreach ($rows as $e) {
        $qs = $all('SELECT id, level, title, type, options, correct_answers
                    FROM tq_diag_questions WHERE exam_id = ? ORDER BY `order`', [$e['eid']]);
        $tally = array_fill_keys($LEVELS, 0);
        $errs  = [];
        foreach ($qs as $q) {
            $o = json_decode((string) $q['options'], true);
            $c = json_decode((string) $q['correct_answers'], true);
            if (!is_array($o) || count($o) !== 4)  $errs[] = "سؤال #{$q['id']}: الخيارات ليست أربعة.";
            if (!is_array($c) || count($c) !== 1)  $errs[] = "سؤال #{$q['id']}: الإجابة ليست واحدة.";
            /* المقارنة نفسها التي يجريها التصحيح: `in_array(..., true)` */
            elseif (!in_array($c[0], is_array($o) ? $o : [], true)) {
                $errs[] = "سؤال #{$q['id']}: الإجابة «{$c[0]}» ليست ضمن الخيارات.";
            }
            if ($q['type'] !== 'radio')            $errs[] = "سؤال #{$q['id']}: النوع «{$q['type']}».";
            if (isset($tally[$q['level']])) $tally[$q['level']]++;
        }

        /* شرط `readiness()` نفسه */
        $filled = 0;
        foreach ($tally as $lv => $n) {
            if ($n < 1) continue;
            $filled++;
            $pid = (int) $e['plan_' . $lv];
            if ($pid <= 0) { $errs[] = "المستوى «$lv» بلا باقة."; continue; }
            $p = $all('SELECT active FROM plans WHERE id = ?', [$pid]);
            if (!$p || !(int) $p[0]['active']) $errs[] = "باقة المستوى «$lv» ($pid) موقوفة أو محذوفة.";
        }
        if (!count($qs)) $errs[] = 'لا سؤال واحد.';
        if ($filled < 2) $errs[] = 'الأسئلة كلها في مستوى واحد.';

        $mark = $errs ? '✗' : '✓';
        $say(sprintf('  %s %-24s #%-3d %-10s %3d سؤالًا  (%d/%d/%d)  عتبة %d',
             $mark, $e['gname'], $e['eid'], $e['status'], count($qs),
             $tally['beginner'], $tally['intermediate'], $tally['advanced'],
             $e['level_threshold']));
        foreach ($errs as $x) $say('        · ' . $x);
        $errs ? $bad++ : $ok++;
    }
    $say();
    $say($bad ? "  ✗ $bad اختبارًا فيه خلل، و$ok سليم." : "  ✓ $ok اختبارًا — كلها جاهزة للنشر.");
    $say();
    exit($bad ? 1 : 0);
}

/* =====================================================================
   قراءة ملفات الصفوف
   ===================================================================== */
$banks = [];
foreach (array_keys($GRADES) as $gid) {
    $f = __DIR__ . '/diag/grade-' . sprintf('%02d', $gid) . '.php';
    if (!is_file($f)) continue;
    $banks[$gid] = require $f;
}
if (!$banks) exit("لا ملف بيانات واحد في scripts/diag/\n");

/* =====================================================================
   الفحص — ثمانية شروط، ولا كتابة قبل اجتيازها
   ===================================================================== */
$problems = [];
$seen_titles = [];   // كشف التكرار عبر الصفوف كلها

foreach ($banks as $gid => $bank) {
    if ($only && $gid !== $only) continue;
    $tag = 'الصف ' . $gid;

    $rows = isset($bank['questions']) ? $bank['questions'] : [];
    $tally = array_fill_keys($LEVELS, 0);
    $pos   = [];   // مواضع الاجابة الصحيحة

    foreach ($rows as $i => $q) {
        $where = $tag . ' / سؤال ' . ($i + 1);

        foreach (['level', 'title', 'options', 'answer', 'subject'] as $k) {
            if (!isset($q[$k])) { $problems[] = "$where: الحقل «$k» مفقود."; continue 2; }
        }
        if (!in_array($q['level'], $LEVELS, true)) {
            $problems[] = "$where: مستوى غير معروف «{$q['level']}».";
        }

        $opts = $q['options'];
        /* ٢ · لا خيار فارغ ولا مكرر */
        if (count($opts) !== 4) $problems[] = "$where: الخيارات " . count($opts) . " لا ٤.";
        foreach ($opts as $o) {
            if (trim($o) === '')            $problems[] = "$where: خيار فارغ.";
            if ($o !== trim($o))            $problems[] = "$where: خيار بمسافة طرفية «$o».";
        }
        if (count(array_unique($opts)) !== count($opts)) $problems[] = "$where: خيار مكرر.";

        /* ١ · الاجابة **بفهرس الخيار** لا بنصه.
              التصحيح في `is_answer_correct()` مطابقة نصية صارمة، وكتابة
              النص مرتين — مرة خيارا ومرة اجابة — هي بالضبط ما افسد
              السؤال القائم «٢+٥» الذي سجلت اجابته «١». فالفهرس يكتب مرة
              واحدة والسكربت يترجمه، فيستحيل الفرق. وهو صنيع لوحة الادارة
              نفسها: `tqa_diag_questions.php` ترسل `value="<?php echo $i; ?>"`.  */
        if (!is_int($q['answer']) || $q['answer'] < 0 || $q['answer'] >= count($opts)) {
            $problems[] = "$where: فهرس الاجابة «{$q['answer']}» خارج المدى.";
        } else {
            $pos[] = $q['answer'];
        }

        /* ٦ · لا مسافات طرفية ولا محارف خارج BMP */
        if ($q['title'] !== trim($q['title'])) $problems[] = "$where: مسافة طرفية في النص.";
        foreach (array_merge([$q['title']], $opts) as $t) {
            if (preg_match('/[\x{10000}-\x{10FFFF}]/u', $t)) {
                $problems[] = "$where: محرف خارج BMP.";
            }
        }

        /* ٤ · لا تكرار نص سؤال في البنك كله */
        $key = preg_replace('/\s+/u', ' ', trim($q['title']));
        if (isset($seen_titles[$key])) {
            $problems[] = "$where: نص مكرر مع " . $seen_titles[$key] . '.';
        } else {
            $seen_titles[$key] = $where;
        }

        $tally[$q['level']]++;
    }

    /* ٥ · الاعداد والمستويات */
    $len = $GRADES[$gid]['len'];
    if (count($rows) !== $len) {
        $problems[] = "$tag: عدد الاسئلة " . count($rows) . " والمطلوب $len.";
    }
    foreach ($tally as $lv => $n) {
        if ($n < 4) $problems[] = "$tag: المستوى «$lv» فيه $n اسئلة — والحد الادنى ٤.";
    }

    /* ٣ · توزيع موضع الاجابة الصحيحة */
    if ($pos) {
        $freq = array_count_values($pos);
        foreach ($freq as $p => $n) {
            if ($n / count($pos) > 0.40) {
                $problems[] = sprintf('%s: الاجابة في الموضع %d بنسبة %d%% — والحد ٤٠%%.',
                                      $tag, $p + 1, round(100 * $n / count($pos)));
            }
        }
    }

    /* ٨ · الصف موجود ونشط، والباقات حية */
    $g = $all('SELECT id, name_ar, active FROM grades WHERE id = ?', [$gid]);
    if (!$g)                       $problems[] = "$tag: لا وجود له في `grades`.";
    elseif (!(int) $g[0]['active']) $problems[] = "$tag: غير نشط في `grades`.";

    foreach ($PLANS[$GRADES[$gid]['stage']] as $lv => $pid) {
        $p = $all('SELECT id, active FROM plans WHERE id = ?', [$pid]);
        if (!$p)                        $problems[] = "$tag: الباقة $pid ($lv) غير موجودة.";
        elseif (!(int) $p[0]['active']) $problems[] = "$tag: الباقة $pid ($lv) موقوفة.";
    }
}

$say('══════════════════════════════════════════════════════════════');
$say('  بنك الاختبار التشخيصي — ' . ($apply ? 'تنفيذ' : 'فحص بلا كتابة'));
$say('══════════════════════════════════════════════════════════════');
$say();
$say(sprintf('  %-22s %6s %9s %9s %9s', 'الصف', 'أسئلة', 'مبتدئ', 'متوسط', 'متقدم'));
$say('  ' . str_repeat('─', 60));
$total = 0;
foreach ($banks as $gid => $bank) {
    if ($only && $gid !== $only) continue;
    $t = array_fill_keys($LEVELS, 0);
    foreach ($bank['questions'] as $q) if (isset($t[$q['level']])) $t[$q['level']]++;
    $n = count($bank['questions']); $total += $n;
    $nm = $one('SELECT name_ar FROM grades WHERE id = ?', [$gid]);
    $say(sprintf('  %-22s %6d %9d %9d %9d', $nm ?: ('#' . $gid), $n,
                 $t['beginner'], $t['intermediate'], $t['advanced']));
}
$say('  ' . str_repeat('─', 60));
$say(sprintf('  %-22s %6d', 'الإجمالي', $total));
$say();

if ($problems) {
    $say('  ✗ الفحص لم يمر — ' . count($problems) . ' مشكلة:');
    foreach ($problems as $p) $say('      · ' . $p);
    $say();
    exit(1);
}
$say('  ✓ الفحص مر: الإجابة ضمن الخيارات · لا تكرار · التوزيع سليم · الباقات حية.');
$say();

if (in_array('--check', $argv, true)) exit(0);

/* =====================================================================
   الكتابة
   ===================================================================== */
foreach ($banks as $gid => $bank) {
    if ($only && $gid !== $only) continue;
    $stage = $GRADES[$gid]['stage'];
    $nm    = $one('SELECT name_ar FROM grades WHERE id = ?', [$gid]);

    $exam = $all('SELECT * FROM tq_diag_exams WHERE grade_id = ?', [$gid]);
    $exam = $exam ? $exam[0] : null;

    if ($clear) {
        if ($exam) {
            $n = $run('DELETE FROM tq_diag_questions WHERE exam_id = ?', [$exam['id']]);
            $say("  ↩︎ $nm: حُذفت أسئلة الاختبار #{$exam['id']}" . ($apply ? " ($n)" : ''));
        }
        continue;
    }

    if ($exam) {
        /* اختبار قائم: العنوان والعتبة لا تمس (قد يكون المالك ضبطها)،
           والباقات تضبط فقط ان كانت صفرا — والا فقراره اولى. */
        $eid = (int) $exam['id'];
        $set = []; $arg = [];
        foreach ($PLANS[$stage] as $lv => $pid) {
            if ((int) $exam['plan_' . $lv] <= 0) { $set[] = "`plan_$lv` = ?"; $arg[] = $pid; }
        }
        if ($set) { $arg[] = $eid; $run('UPDATE tq_diag_exams SET ' . implode(', ', $set) . ' WHERE id = ?', $arg); }
        $run('DELETE FROM tq_diag_questions WHERE exam_id = ?', [$eid]);
        $say("  ◆ $nm: اختبار قائم #$eid — استُبدلت أسئلته.");
    } else {
        $eid = $insert(
            'INSERT INTO tq_diag_exams
             (grade_id, title, intro, status, time_limit_sec, level_threshold, allow_retake,
              plan_beginner, plan_intermediate, plan_advanced, created_at)
             VALUES (?,?,?,?,0,?,0,?,?,?,NOW())',
            [$gid, $bank['title'], $bank['intro'], 'draft', $NEW_THRESHOLD,
             $PLANS[$stage]['beginner'], $PLANS[$stage]['intermediate'], $PLANS[$stage]['advanced']]
        );
        $say("  ✚ $nm: اختبار جديد" . ($apply ? " #$eid" : ''));
    }

    if (!$apply) { $say("      (جافّ — لا كتابة)"); continue; }

    /* الترتيب تصاعدي داخل كل مستوى، والعرض يمر بالمستويات صاعدا:
       من يبدأ باسئلة متقدمة ثم يهبط يقرأ الاختبار على انه صعب فينصرف. */
    $ord = 0;
    foreach ($LEVELS as $lv) {
        foreach ($bank['questions'] as $q) {
            if ($q['level'] !== $lv) continue;
            $ord++;
            $insert(
                'INSERT INTO tq_diag_questions
                 (exam_id, level, title, type, options, correct_answers, `order`)
                 VALUES (?,?,?,?,?,?,?)',
                [$eid, $lv, $q['title'], 'radio',
                 json_encode(array_values($q['options']), JSON_UNESCAPED_UNICODE),
                 json_encode([$q['options'][$q['answer']]], JSON_UNESCAPED_UNICODE),
                 $ord]
            );
        }
    }
    $say("      ↳ أُدخل $ord سؤالًا.");
}

$say();
$say($apply ? "  ✓ تمّ. عمليات الكتابة: $writes" : "  (جافّ — $writes عملية كتابة كانت ستُنفَّذ)");
$say();
