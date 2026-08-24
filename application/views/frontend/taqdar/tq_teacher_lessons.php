<?php
/**
 * بوابة المعلم — دروسي.
 *
 * **ما كان قبل:** «كورساتي» تعطي المعلم رقما مجملا في عمود («٢٤ درسا»)
 * لا يفتح على شيء، و«رفع الدروس» تعرض له في زاويتها آخر خمسة رفعها.
 * فمعلم عنده أربعون درسا لا يملك في بوابته قائمة يجد فيها درسا بعينه،
 * ولا يعرف أيها بقي مسودة، ولا أي وحدة نقصت درسا، ولا أي اختبار نشر بلا
 * سؤال واحد. الدرس — وهو وحدة عمله اليومي — كان مذابا في شاشتين لا شاشة
 * له فيهما.
 *
 * فهذه شاشته: كل درس في كل كورس أسند إليه، مرتبا كما يدرس (كورسا فوحدة
 * فدرسا)، وبحالته ونوعه ومدته. والكورس يبقى في شاشته يعرض ما يعرض الكورس:
 * الحالة والمسجلين ونسبة الإكمال.
 *
 * القاعدة الحاكمة لبوابة المعلم كلها تسري هنا كما تسري هناك:
 * النطاق يفرض في طبقة الاستعلام لا في الواجهة — `lessons_of()` تقيد بملكية
 * الكورس، فمعرف يخمن في الرابط لا يرد صفا واحدا من كورس غيره.
 */

$tq_nav   = 'lessons';
$tq_role  = 'teacher';
$tq_title = 'دروسي';
$tq_sub   = 'كل ما رفعت، كورسا فوحدة فدرسا';
$tq_icon  = 'play';

/* `$this` في العرض هو المحمل لا المتحكم، فالنماذج تحمل عبر get_instance(). */
$CI = get_instance();
$tq_uid = (int) $CI->session->userdata('user_id');
if (!$tq_uid && isset($user_id)) $tq_uid = (int) $user_id;

$CI->load->model('taqdar_teacher_model');
$tq_model = $CI->taqdar_teacher_model;

$tq_my_courses = $tq_model->my_courses($tq_uid);
$tq_course_ids = array_map('intval', array_column($tq_my_courses, 'id'));

/* --- التصفية: قوائم بيضاء، وما لا يملكه المعلم يسقط --- */
$f_course = (int) $CI->input->get('course');
$f_status = (string) $CI->input->get('status', true);
$f_type   = (string) $CI->input->get('type', true);
$f_q      = trim((string) $CI->input->get('q', true));

if ($f_course && !in_array($f_course, $tq_course_ids, true)) $f_course = 0;
if (!in_array($f_status, array('published', 'review', 'draft'), true)) $f_status = '';
if (!in_array($f_type, array('lesson', 'quiz'), true)) $f_type = '';
if ($f_q !== '') $f_q = mb_substr($f_q, 0, 80);

$tq_has_filter = ($f_course > 0 || $f_status !== '' || $f_type !== '' || $f_q !== '');

/* مدد Academy نص «hh:mm:ss»، ولا تجمع في SQL. و`tq_s_*` تعيش في
   `tq_student_styles.php` — وهي ورقة شاشات الطالب، وتضمينها هنا يجر
   أنماط بوابة أخرى إلى هذه الشاشة. فالحسابان الصغيران محليان. */
$tq_secs_of = static function ($hms) {
    $p = array_map('intval', explode(':', trim((string) $hms)));
    $n = count($p);
    if ($n === 3) return $p[0] * 3600 + $p[1] * 60 + $p[2];
    if ($n === 2) return $p[0] * 60 + $p[1];
    return $n === 1 ? $p[0] : 0;
};
$tq_hours_of = static function ($seconds) {
    $s = max(0, (int) $seconds);
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    return $h > 0 ? $h . ' س ' . $m . ' د' : $m . ' د';
};

/* الكل مرة (للأعداد التي لا تتبدل بالتصفية) والمصفى مرة. */
$tq_all  = $tq_model->lessons_of($tq_uid);
$tq_rows = $tq_has_filter
    ? $tq_model->lessons_of($tq_uid, array('course' => $f_course, 'status' => $f_status,
                                           'type' => $f_type, 'q' => $f_q))
    : $tq_all;

/* --- الأعداد: من القائمة الكاملة لا من المعروض --- */
$tq_n = array('all' => count($tq_all), 'published' => 0, 'review' => 0, 'draft' => 0,
              'quiz' => 0, 'lesson' => 0);
$tq_seconds  = 0;
$tq_empty_quizzes = array();     // اختبار نشر بلا سؤال — عطل يقرأه الطالب لا المعلم
foreach ($tq_all as $l) {
    $st = (string) $l['tq_status'];
    if (isset($tq_n[$st])) $tq_n[$st]++;
    if ($l['lesson_type'] === 'quiz') {
        /* درس اختبار موروث */
        $tq_n['quiz']++;
        if ((int) $l['questions'] === 0) $tq_empty_quizzes[] = $l;
    } else {
        $tq_n['lesson']++;
        $tq_seconds += $tq_secs_of($l['duration']);
        /* TQ-EXAM-SOURCE — واختبار الدرس ليس درسا مستقلا اليوم: هو
           تقييم `review` معلق بالدرس نفسه. فكان العداد يقول «اختباراتك
           ⁦0⁩» لمعلم ألف اختبارين، لأنه يعد النوع الموروث وحده. */
        if ((int) (isset($l['quiz_questions']) ? $l['quiz_questions'] : 0) > 0) {
            $tq_n['quiz']++;
        }
    }
}

/* --- روابط تحفظ بقية الفلاتر --- */
$tq_query = static function ($over = array()) use ($f_course, $f_status, $f_type, $f_q) {
    $p = array_merge(array('course' => $f_course ?: '', 'status' => $f_status,
                           'type' => $f_type, 'q' => $f_q), $over);
    $p = array_filter($p, static function ($v) { return $v !== '' && $v !== null && $v !== 0; });
    return base_url('teacher/lessons') . ($p ? '?' . http_build_query($p) : '');
};

/* --- التجميع: كورس ← وحدة ← دروس --- */
$tq_groups = array();
foreach ($tq_rows as $l) {
    $cid = (int) $l['course_id'];
    if (!isset($tq_groups[$cid])) {
        $tq_groups[$cid] = array(
            'id' => $cid, 'title' => $l['course_title'], 'status' => $l['course_status'],
            'total' => 0, 'units' => array(),
        );
    }
    $unit = trim((string) $l['section_title']) !== '' ? $l['section_title'] : 'دروس بلا وحدة';
    $tq_groups[$cid]['units'][$unit][] = $l;
    $tq_groups[$cid]['total']++;
}

/* كورس بلا درس واحد لا يظهر في التجميع أعلاه — وهو أولى ما يعرض على
   معلم يسأل «أين بقية دروسي؟». فيجمع صراحة ويعرض بابا إلى الرفع. */
$tq_courses_with = array_keys($tq_groups);
$tq_courses_empty = array();
if (!$tq_has_filter) {
    foreach ($tq_my_courses as $c) {
        if (!in_array((int) $c['id'], $tq_courses_with, true)) $tq_courses_empty[] = $c;
    }
}

$tq_status_face = array(
    'published' => array('mastered', 'منشور'),
    'review'    => array('progress', 'قيد المراجعة'),
    'draft'     => array('idle',     'مسودة'),
);
$tq_course_status_face = array(
    'active'   => array('mastered', 'منشور'),
    'pending'  => array('due',      'قيد المراجعة'),
    'draft'    => array('idle',     'مسودة'),
    'private'  => array('progress', 'خاص'),
    'upcoming' => array('progress', 'قادم'),
);

include 'portal_open.php';
?>

<style>
/* صف الدرس: علامة النوع · النص · المدة · الحالة · الأفعال.
   والمدة عمود مستقل لتصطف الأرقام تحت بعضها فتقرأ عمودا واحدا. */
.tq-lgroup {
  background: var(--tq-surface); border: 1px solid var(--tq-line);
  border-radius: var(--tq-radius-medium); margin-block-end: var(--tq-space-m); overflow: hidden;
}
.tq-lgroup > summary {
  display: flex; align-items: center; gap: var(--tq-space-m);
  padding: var(--tq-space-l); cursor: pointer; list-style: none;
}
.tq-lgroup > summary::-webkit-details-marker { display: none; }
.tq-lgroup__mark { flex: none; color: var(--tq-text2); display: inline-flex;
  transition: transform var(--tq-motion-hover) ease; }
.tq-lgroup[open] .tq-lgroup__mark { transform: rotate(90deg); }
html[dir='rtl'] .tq-lgroup[open] .tq-lgroup__mark { transform: rotate(-90deg); }
.tq-lgroup__name { font: var(--tq-type-bodyStrong); color: var(--tq-navy); flex: 1; min-inline-size: 0; }
.tq-lgroup__n { flex: none; color: var(--tq-text2); font: var(--tq-type-caption); }

.tq-lunit {
  padding: var(--tq-space-m) var(--tq-space-l);
  border-block-start: 1px solid var(--tq-line); background: var(--tq-navyWash);
  font: var(--tq-type-micro); color: var(--tq-text2); margin: 0;
}

.tq-lrow {
  display: grid; align-items: center; gap: var(--tq-space-m);
  grid-template-columns: 40px minmax(0, 1fr) auto auto auto;
  padding: var(--tq-space-m) var(--tq-space-l);
  border-block-start: 1px solid var(--tq-line);
}
.tq-lrow:hover { background: var(--tq-navyWash); }
.tq-lrow__mark { inline-size: 40px; block-size: 40px; border-radius: var(--tq-radius-pill);
  display: grid; place-items: center; color: var(--tq-pastel-ink); }
.tq-lrow__main { min-inline-size: 0; }
.tq-lrow__title { font: var(--tq-type-body); color: var(--tq-navy); margin: 0; }
.tq-lrow__meta { display: flex; align-items: center; flex-wrap: wrap; gap: var(--tq-space-s);
  font: var(--tq-type-micro); color: var(--tq-text2); margin-block-start: 2px; }
.tq-lrow__time { font: var(--tq-type-numeralSm); color: var(--tq-text2);
  direction: ltr; unicode-bidi: isolate; }
.tq-lrow__acts { display: flex; align-items: center; gap: var(--tq-space-xs); }

@media (max-width: 767.98px) {
  .tq-lrow { grid-template-columns: 36px minmax(0, 1fr) auto; }
  .tq-lrow__mark { inline-size: 36px; block-size: 36px; }
  .tq-lrow__time, .tq-lrow__acts { grid-column: 2 / -1; }
}

.tq-chips { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); }
.tq-chip { display: inline-flex; align-items: center; gap: var(--tq-space-xs);
  min-block-size: var(--tq-touch-min); padding: 0 var(--tq-space-m);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-pill);
  background: var(--tq-surface); color: var(--tq-text2); font: var(--tq-type-caption); }
.tq-chip:hover { border-color: var(--tq-navy); color: var(--tq-navy); text-decoration: none; }
.tq-chip[aria-current='true'] { background: var(--tq-navy); border-color: var(--tq-navy);
  color: var(--tq-onAction); font-weight: 700; }
.tq-chip__n { font: var(--tq-type-numeralSm); direction: ltr; unicode-bidi: isolate; opacity: .8; }

.tq-lsearch { display: flex; gap: var(--tq-space-s); margin-block-start: var(--tq-space-l); }
.tq-lsearch .tq-input { flex: 1; min-inline-size: 0; }
</style>

<div class="tq-cols">
    <div>

        <?php if ($m = $CI->session->flashdata('tq_ok')): ?>
            <div class="tq-alert tq-alert--ok tq-section" role="status"><?php echo html_escape($m); ?></div>
        <?php endif; ?>

        <?php if ($tq_empty_quizzes && !$tq_has_filter): ?>
            <?php /* عطل يقرأه الطالب قبل المعلم: اختبار في كورس منشور بلا سؤال
                     يفتح على شاشة فارغة. فيقال هنا صراحة بعدده وبابه. */ ?>
            <section class="tq-card tq-card--panel tq-pastel tq-pastel--rose tq-section" role="status">
                <span class="tq-pastel__label tq-micro">يحتاج انتباهك</span>
                <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                    <?php echo tq_iso(tq_count_units(count($tq_empty_quizzes), 'اختبار', 'اختباران', 'اختبارين',
                        'اختبارات', 'اختبارا', '', 'nom')); ?>
                    عندك بلا سؤال واحد — ومن يفتحه من طلابك يجد شاشة فارغة.
                </p>
                <ul class="tq-stack" style="--tq-space-l:var(--tq-space-xs);margin-block-start:var(--tq-space-m)">
                    <?php foreach (array_slice($tq_empty_quizzes, 0, 4) as $q): ?>
                        <li class="tq-micro">
                            <?php echo html_escape($q['title']); ?>
                            — <?php echo html_escape($q['course_title']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="tq-btn tq-btn--secondary tq-btn--sm" style="margin-block-start:var(--tq-space-m)"
                   href="<?php echo base_url('teacher/questions'); ?>">افتح بنك الأسئلة</a>
            </section>
        <?php endif; ?>

        <?php if ($tq_all): ?>
            <section class="tq-card tq-card--panel tq-section" aria-labelledby="tq-tl-h">
                <div class="tq-card__head">
                    <h2 class="tq-card__title" id="tq-tl-h">اعثر على درس</h2>
                    <?php if ($tq_has_filter): ?>
                        <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('teacher/lessons'); ?>">مسح التصفية</a>
                    <?php endif; ?>
                </div>

                <p class="tq-field__label" id="tq-tl-state">الحالة</p>
                <div class="tq-chips" role="group" aria-labelledby="tq-tl-state" style="margin-block-end:var(--tq-space-l)">
                    <?php foreach (array(
                        array('',          'الكل',         $tq_n['all']),
                        array('published', 'منشور',        $tq_n['published']),
                        array('review',    'قيد المراجعة', $tq_n['review']),
                        array('draft',     'مسودة',        $tq_n['draft']),
                    ) as $chip): ?>
                        <?php [$key, $label, $n] = $chip; $on = ($f_status === $key); ?>
                        <a class="tq-chip" href="<?php echo $tq_query(array('status' => $key)); ?>"
                           <?php echo $on ? 'aria-current="true"' : ''; ?>>
                            <?php echo html_escape($label); ?>
                            <span class="tq-chip__n"><?php echo TQ_LRI . (int) $n . TQ_PDI; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <p class="tq-field__label" id="tq-tl-type">النوع</p>
                <div class="tq-chips" role="group" aria-labelledby="tq-tl-type" style="margin-block-end:var(--tq-space-l)">
                    <?php foreach (array(
                        array('',       'الكل',       $tq_n['all']),
                        array('lesson', 'دروس',       $tq_n['lesson']),
                        array('quiz',   'اختبارات',   $tq_n['quiz']),
                    ) as $chip): ?>
                        <?php [$key, $label, $n] = $chip; $on = ($f_type === $key); ?>
                        <a class="tq-chip" href="<?php echo $tq_query(array('type' => $key)); ?>"
                           <?php echo $on ? 'aria-current="true"' : ''; ?>>
                            <?php echo html_escape($label); ?>
                            <span class="tq-chip__n"><?php echo TQ_LRI . (int) $n . TQ_PDI; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if (count($tq_my_courses) > 1): ?>
                    <p class="tq-field__label" id="tq-tl-course">الكورس</p>
                    <div class="tq-chips" role="group" aria-labelledby="tq-tl-course">
                        <a class="tq-chip" href="<?php echo $tq_query(array('course' => '')); ?>"
                           <?php echo $f_course === 0 ? 'aria-current="true"' : ''; ?>>كل كورساتي</a>
                        <?php foreach ($tq_my_courses as $c): ?>
                            <?php $on = ((int) $c['id'] === $f_course); ?>
                            <a class="tq-chip" href="<?php echo $tq_query(array('course' => $on ? '' : (int) $c['id'])); ?>"
                               <?php echo $on ? 'aria-current="true"' : ''; ?>>
                                <?php echo html_escape($c['title']); ?>
                                <?php if ($on): ?>
                                    <span aria-hidden="true"><?php echo tq_icon('x', 14); ?></span>
                                    <span class="tq-sr">— اضغط لإلغاء تصفية هذا الكورس</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="tq-lsearch" method="get" action="<?php echo base_url('teacher/lessons'); ?>" role="search">
                    <?php if ($f_course > 0): ?><input type="hidden" name="course" value="<?php echo (int) $f_course; ?>"><?php endif; ?>
                    <?php if ($f_status !== ''): ?><input type="hidden" name="status" value="<?php echo html_escape($f_status); ?>"><?php endif; ?>
                    <?php if ($f_type !== ''): ?><input type="hidden" name="type" value="<?php echo html_escape($f_type); ?>"><?php endif; ?>
                    <label class="tq-sr" for="tq-tlq">ابحث في دروسك بالاسم أو الوحدة</label>
                    <input class="tq-input" id="tq-tlq" name="q" type="search" maxlength="80"
                           value="<?php echo html_escape($f_q); ?>" placeholder="اسم الدرس أو الوحدة…">
                    <button class="tq-btn tq-btn--secondary" type="submit">ابحث</button>
                </form>
            </section>
        <?php endif; ?>

        <?php if (empty($tq_all)): ?>
            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('play', 24); ?></span>
                <h2 class="tq-empty__title">لم ترفع درسا بعد</h2>
                <p class="tq-empty__text">
                    كل درس ترفعه يظهر هنا تحت كورسه ووحدته، بمدته وحالته — فتعرف في نظرة
                    أين وصلت من منهجك وما بقي منه.
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/upload'); ?>">ارفع درسك الأول</a>
            </div>

        <?php elseif (empty($tq_rows)): ?>
            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('search', 24); ?></span>
                <h2 class="tq-empty__title">لا درس بهذه التصفية</h2>
                <p class="tq-empty__text">غير الحالة أو النوع أو الكورس، أو امسح التصفية لترى كل دروسك.</p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/lessons'); ?>">مسح التصفية</a>
            </div>

        <?php else: ?>
            <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">
                <?php echo tq_iso(tq_count_units(count($tq_rows), 'درس', 'درسان', 'درسين', 'دروس', 'درسا', 'لا دروس', 'nom')); ?>
                <?php echo count($tq_groups) > 1
                    ? tq_iso('في ' . tq_count_units(count($tq_groups), 'كورس', 'كورسان', 'كورسين', 'كورسات', 'كورسا', '', 'obl'))
                    : ''; ?>
            </p>

            <?php $tq_gi = 0; ?>
            <?php foreach ($tq_groups as $g): ?>
                <?php
                /* تفتح المجموعة إن كانت وحدها، أو صفيت على كورسها، أو كان
                   المعلم يبحث — والبحث بلا فتح يخبئ نتائجه خلف مثلثات مغلقة.
                   والأولى مفتوحة دائما: صفحة كل مجموعاتها مطوية تفتح على
                   ستة عناوين وحدها، فلا يرى المعلم شكل ما تحتها قبل أن يضغط. */
                $tq_gi++;
                $open = ($f_course > 0) || (count($tq_groups) === 1) || ($f_q !== '') || ($tq_gi === 1);
                [$cs_kind, $cs_text] = $tq_course_status_face[$g['status']] ?? array('idle', $g['status']);
                ?>
                <details class="tq-lgroup" <?php echo $open ? 'open' : ''; ?>>
                    <summary>
                        <span class="tq-lgroup__mark" aria-hidden="true"><?php echo tq_icon('chev-next', 18); ?></span>
                        <span class="tq-lgroup__name"><?php echo html_escape($g['title']); ?></span>
                        <?php echo tq_badge($cs_kind, $cs_text); ?>
                        <span class="tq-lgroup__n">
                            <?php echo tq_iso(tq_lessons_word($g['total'], 'لا دروس', 'nom')); ?>
                        </span>
                    </summary>

                    <?php foreach ($g['units'] as $unit_title => $unit_lessons): ?>
                        <p class="tq-lunit">
                            <?php echo html_escape($unit_title); ?>
                            · <?php echo tq_iso(tq_lessons_word(count($unit_lessons), 'لا دروس', 'nom')); ?>
                        </p>

                        <?php foreach ($unit_lessons as $l): ?>
                            <?php
                            $is_quiz = ($l['lesson_type'] === 'quiz');
                            [$st_kind, $st_text] = $tq_status_face[(string) $l['tq_status']] ?? array('idle', 'مسودة');
                            ?>
                            <div class="tq-lrow">
                                <span class="tq-lrow__mark tq-pastel tq-pastel--<?php echo $is_quiz ? 'lilac' : 'sky'; ?>" aria-hidden="true">
                                    <?php echo tq_icon($is_quiz ? 'clipboard' : 'play', 20); ?>
                                </span>

                                <div class="tq-lrow__main">
                                    <p class="tq-lrow__title"><?php echo html_escape($l['title']); ?></p>
                                    <p class="tq-lrow__meta">
                                        <span><?php echo $is_quiz ? 'اختبار' : 'درس'; ?></span>
                                        <?php if ($is_quiz): ?>
                                            <span>·</span>
                                            <span><?php echo tq_iso(tq_count_units((int) $l['questions'], 'سؤال',
                                                'سؤالان', 'سؤالين', 'أسئلة', 'سؤالا', 'بلا أسئلة', 'nom')); ?></span>
                                            <?php if ((int) $l['questions'] === 0): ?>
                                                <?php echo tq_badge('due', 'يحتاج أسئلة'); ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($l['is_free'])): ?>
                                            <?php echo tq_badge('progress', 'درس تجريبي'); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <span class="tq-lrow__time">
                                    <?php if (!$is_quiz && !empty($l['duration']) && $l['duration'] !== '00:00:00'): ?>
                                        <span class="tq-sr">مدة الدرس</span>
                                        <?php echo TQ_LRI . html_escape($l['duration']) . TQ_PDI; ?>
                                    <?php endif; ?>
                                </span>

                                <span><?php echo tq_badge($st_kind, $st_text); ?></span>

                                <span class="tq-lrow__acts">
                                    <?php if ($is_quiz): ?>
                                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                           href="<?php echo base_url('teacher/questions') . '?course=' . (int) $l['course_id']; ?>">
                                            أسئلته
                                            <span class="tq-sr">— <?php echo html_escape($l['title']); ?></span>
                                        </a>
                                    <?php else: ?>
                                        <?php /* الأبواب الثلاثة من صف الدرس: تحريره،
                                                 واختباره، وإضافة درس بجواره. وكانت هنا
                                                 «أضف هنا» وحدها — فمن أراد أن يعدل درسا
                                                 رفعه لم يجد إليه سبيلا من شاشة دروسه. */ ?>
                                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                           href="<?php echo base_url('teacher/course/' . (int) $l['course_id'])
                                               . '?lesson=' . (int) $l['id']; ?>">
                                            تعديل
                                            <span class="tq-sr">— <?php echo html_escape($l['title']); ?></span>
                                        </a>
                                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                           href="<?php echo base_url('teacher/quiz/' . (int) $l['id']); ?>">
                                            اختباره
                                            <span class="tq-sr">— <?php echo html_escape($l['title']); ?></span>
                                        </a>
                                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                           href="<?php echo base_url('teacher/upload') . '?course=' . (int) $l['course_id']
                                               . (!empty($l['section_id']) ? '&section=' . (int) $l['section_id'] : ''); ?>">
                                            أضف هنا
                                            <span class="tq-sr">— درسا جديدا في <?php echo html_escape($unit_title); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>

            <?php if ($tq_courses_empty): ?>
                <section class="tq-card tq-card--panel" style="margin-block-start:var(--tq-space-xl)">
                    <div class="tq-card__head"><h2 class="tq-card__title">كورسات بلا دروس بعد</h2></div>
                    <p class="tq-caption">
                        هذه الكورسات مسندة إليك ولم يرفع فيها درس واحد — والطالب الذي يفتح
                        أحدها يجد منهجا فارغا.
                    </p>
                    <ul class="tq-stack" style="margin-block-start:var(--tq-space-l)">
                        <?php foreach ($tq_courses_empty as $c): ?>
                            <li class="tq-row tq-row--between" style="gap:var(--tq-space-m)">
                                <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($c['title']); ?></span>
                                <a class="tq-btn tq-btn--secondary tq-btn--sm"
                                   href="<?php echo base_url('teacher/upload') . '?course=' . (int) $c['id']; ?>">
                                    ارفع أول درس
                                    <span class="tq-sr">— <?php echo html_escape($c['title']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">ملخص</h2></div>
            <ul class="tq-stack">
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">دروسك</span>
                    <?php echo tq_num($tq_n['lesson']); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">اختباراتك</span>
                    <?php echo tq_num($tq_n['quiz']); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">مدة دروسك</span>
                    <?php /* `tq_iso` لا `tq_num`: «١٢ س ٤٥ د» نص عربي فيه أرقام،
                             وعزله كوحدة يسارية يقلب ترتيبه. */ ?>
                    <span class="tq-caption"><?php echo $tq_seconds > 0 ? tq_iso($tq_hours_of($tq_seconds)) : '—'; ?></span>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">كورساتك</span>
                    <?php echo tq_num(count($tq_my_courses)); ?>
                </li>
            </ul>
            <a class="tq-btn tq-btn--primary tq-btn--block" style="margin-block-start:var(--tq-space-l)"
               href="<?php echo base_url('teacher/upload'); ?>">ارفع درسا جديدا</a>
        </div>

        <?php /* الشاشة الأخت: الكورس رتبة فوق الدرس، وما يعرض عنه غير ما يعرض
                 عن دروسه — المسجلون ونسبة الإكمال وحالة النشر. */ ?>
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">كورساتي</h2></div>
            <p class="tq-caption" style="margin:0">
                حالة كل كورس وعدد المسجلين فيه ومتوسط إكمالهم — وهي أرقام الكورس
                لا أرقام دروسه.
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--block" style="margin-block-start:var(--tq-space-l)"
               href="<?php echo base_url('teacher/courses'); ?>">افتح كورساتي</a>
        </div>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro">قاعدة الدرس الواحد</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo tq_iso('من 8 إلى 15 دقيقة · من 1 إلى 3 أهداف · 5 أسئلة على الأقل لكل هدف.'); ?>
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                الدرس الأطول يقسم، والهدف الرابع يعني درسا ثانيا.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
