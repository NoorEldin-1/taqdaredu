<?php
/**
 * التقويم — بوابة الطالب.
 *
 * ── قاعدة ملزمة لهذه الشاشة ──────────────────────────────────────────────
 * كل حدث في هذا التقويم ينقر فيصل إلى صفحته: الدرس يفتح الدرس، والاختبار
 * يبدأ الاختبار، والحصة تدخل الحصة. تقويم لا ينقر منه تقويم للعرض لا
 * للعمل — يري الطالب موعده ثم يتركه يبحث عنه في مكان آخر، فيصير عبئا
 * إضافيا لا أداة. ولذلك كل حدث هنا وسم حوله رابط، لا نص ساكن.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * الأسبوع يبدأ الأحد (السوق سعودي)، واليوم الحالي بحبة كحلية مصمتة.
 *
 * لا جدول «مواعيد» واحد في قاعدة taqd_lms، لكن لكل فئة مصدرها الحقيقي:
 *   الدروس     ← section.start_date / section.end_date في الكورسات المسجلة
 *   الاختبارات ← quiz_results (بدء أو تسليم) لدروس نوعها quiz
 *   المهام     ← attempts على تقييمات نوعها homework
 *   حصص بالطلب ← tutoring_sessions × availability_slots.starts_at
 *   المراجعات  ← review_queue.due_at
 * فما يعرض هنا مسجل في القاعدة، ولا موعد مخترع. وbbb_meetings متروك
 * عمدا: يحمل غرفة الحصة بلا وقت مجدول، فلا يصلح حدثا في تقويم.
 */

$tq_nav   = 'calendar';
$tq_role  = 'student';
$tq_title = t('التقويم');
$tq_sub   = t('عرض وتنظيم جميع مواعيدك ودروسك وأحداثك');
$tq_icon  = 'calendar';

$uid = (int) $this->session->userdata('user_id');

/* ---- العرض والتاريخ المرجعي ------------------------------------------ */
$tq_view = $this->input->get('view', true);
$tq_view = in_array($tq_view, ['month', 'week', 'day', 'agenda'], true) ? $tq_view : 'month';

$tq_ref = $this->input->get('d', true);
$tq_ref = ($tq_ref && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tq_ref)) ? strtotime($tq_ref) : strtotime('today');

$tq_today   = strtotime('today');
$tq_year    = (int) date('Y', $tq_ref);
$tq_month   = (int) date('n', $tq_ref);
$tq_first   = mktime(0, 0, 0, $tq_month, 1, $tq_year);
$tq_days_in = (int) date('t', $tq_first);

$tq_month_names = [t('يناير'), t('فبراير'), t('مارس'), t('أبريل'), t('مايو'), t('يونيو'), t('يوليو'), t('أغسطس'), t('سبتمبر'), t('أكتوبر'), t('نوفمبر'), t('ديسمبر')];
/* أسماء الأيام مرتبة من الأحد — الأسبوع يبدأ الأحد في السوق السعودي */
$tq_day_names   = [t('الأحد'), t('الاثنين'), t('الثلاثاء'), t('الأربعاء'), t('الخميس'), t('الجمعة'), t('السبت')];
$tq_day_short   = [t('أحد'), t('اثنين'), t('ثلاثاء'), t('أربعاء'), t('خميس'), t('جمعة'), t('سبت')];

/* ---- خمس فئات ثابتة، ولون كل فئة ثابت لا يتغير بين الشاشات ---------- */
$tq_cats = [
    'lessons'   => [t('الدروس'),        'var(--tq-teal)',  'play',        t('انضم إلى الدرس'),  'student/lessons'],
    'exams'     => [t('الاختبارات'),    'var(--tq-sky-ink)', 'check-badge', t('ابدأ الاختبار'),   'student/exams'],
    'tasks'     => [t('المهام'),        'var(--tq-amber)', 'clipboard',   t('رفع الواجب'),      'student/tasks'],
    'on_demand' => [t('حصص بالطلب'),    'var(--tq-navy)',  'video',       t('دخول الحصة'),      'student/on-demand'],
    'revisions' => [t('المراجعات'),     'var(--tq-lilac-ink)', 'book',    t('بدء المراجعة'),    'student/materials'],
];

/* ---- الأحداث ---------------------------------------------------------
   شكل الحدث الواحد:
   ['ts' => طابع زمني, 'cat' => مفتاح فئة, 'title' => نص, 'sub' => نص, 'href' => رابط]
   وكل حدث يحمل رابطه، فالتقويم ينقر منه كما تنص قاعدة الشاشة أعلاه.

   get_instance() صراحة: $this في العرض ليس المتحكم، ونموذج أو قاعدة
   تحمل أثناء العرض لا تظهر فيه. */
$tq_events = [];

if ($uid > 0) {
    $CI = get_instance();

    /** Academy يخزن الوقت نصا: طابعا زمنيا أحيانا وتاريخا أحيانا. */
    $tq_ts = static function ($value) {
        $v = trim((string) $value);
        if ($v === '' || $v === '0') return 0;
        if (ctype_digit($v)) return (int) $v;
        $t = strtotime($v);
        return $t ?: 0;
    };

    $tq_my_courses = [];
    foreach ($CI->db->select('c.id, c.title')->from('enrol e')
                    ->join('course c', 'c.id = e.course_id', 'inner')
                    ->where('e.user_id', $uid)->get()->result_array() as $c) {
        $tq_my_courses[(int) $c['id']] = (string) $c['title'];
    }
    $tq_cids = array_keys($tq_my_courses);

    /* 1) الدروس — بداية الوحدة ونهايتها، وهما التاريخان الوحيدان المخزنان للمنهج */
    if ($tq_cids) {
        foreach ($CI->db->select('id, title, course_id, start_date, end_date')
                        ->from('section')->where_in('course_id', $tq_cids)
                        ->get()->result_array() as $s) {
            $cid = (int) $s['course_id'];
            foreach ([['start_date', t('بداية وحدة')], ['end_date', t('نهاية وحدة')]] as [$col, $word]) {
                $ts = $tq_ts($s[$col]);
                if ($ts <= 0) continue;
                $tq_events[] = [
                    'ts'    => $ts,
                    'cat'   => 'lessons',
                    'title' => $word . ': ' . $s['title'],
                    'sub'   => $tq_my_courses[$cid] ?? '',
                    'href'  => base_url('student/lessons'),
                ];
            }
        }
    }

    /* 2) الاختبارات — من نتائج الطالب نفسه: تسليم أو بدء بلا تسليم */
    if ($tq_cids) {
        foreach ($CI->db->select('qr.quiz_id, qr.is_submitted, qr.date_added, qr.date_updated,'
                               . ' l.title, l.course_id')
                        ->from('quiz_results qr')
                        ->join('lesson l', 'l.id = qr.quiz_id', 'inner')
                        ->where('qr.user_id', $uid)
                        ->where_in('l.course_id', $tq_cids)
                        ->get()->result_array() as $r) {
            $done = ((int) $r['is_submitted'] === 1);
            $ts   = $tq_ts($done ? $r['date_updated'] : $r['date_added']);
            if ($ts <= 0) continue;
            $cid  = (int) $r['course_id'];
            $tq_events[] = [
                'ts'    => $ts,
                'cat'   => 'exams',
                'title' => ($done ? t('سلمت: ') : t('بدأت: ')) . $r['title'],
                'sub'   => $tq_my_courses[$cid] ?? '',
                'href'  => base_url('student/lesson/' . $cid . '/' . (int) $r['quiz_id']),
            ];
        }
    }

    /* 3) المهام — محاولات الطالب على تقييمات نوعها homework */
    foreach ($CI->db->select('ap.started_at, ap.submitted_at, l.id AS lesson_id, l.title, l.course_id')
                    ->from('attempts ap')
                    ->join('assessments a', 'a.id = ap.assessment_id', 'inner')
                    ->join('lesson l', 'l.id = a.lesson_id', 'inner')
                    ->where('ap.student_id', $uid)
                    ->where('a.type', 'homework')
                    ->get()->result_array() as $r) {
        $done = !empty($r['submitted_at']);
        $ts   = $tq_ts($done ? $r['submitted_at'] : $r['started_at']);
        if ($ts <= 0) continue;
        $cid  = (int) $r['course_id'];
        $tq_events[] = [
            'ts'    => $ts,
            'cat'   => 'tasks',
            'title' => ($done ? t('سلمت واجب: ') : t('بدأت واجب: ')) . $r['title'],
            'sub'   => $tq_my_courses[$cid] ?? '',
            'href'  => base_url('student/lesson/' . $cid . '/' . (int) $r['lesson_id']),
        ];
    }

    /* 4) حصص بالطلب — وقتها من الفترة المحجوزة لا من الحصة نفسها */
    foreach ($CI->db->select('sl.starts_at, sl.duration_min, ts.status,'
                           . ' u.first_name, u.last_name')
                    ->from('tutoring_sessions ts')
                    ->join('availability_slots sl', 'sl.id = ts.slot_id', 'inner')
                    ->join('users u', 'u.id = ts.teacher_id', 'left')
                    ->where('ts.student_id', $uid)
                    ->where_in('ts.status', ['requested', 'confirmed', 'live', 'completed'])
                    ->get()->result_array() as $r) {
        $ts = $tq_ts($r['starts_at']);
        if ($ts <= 0) continue;
        $who = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $tq_events[] = [
            'ts'    => $ts,
            'cat'   => 'on_demand',
            'title' => t('حصة') . ($who !== '' ? t(' مع ') . $who : ''),
            /* نص خام: العزل يقع عند العرض مرة واحدة، فلا يعزل الرقم مرتين */
            'sub'   => ((int) $r['duration_min']) . t(' دقيقة'),
            'href'  => base_url('student/on-demand'),
        ];
    }

    /* 5) المراجعات — استحقاقات طابور التكرار المتباعد */
    foreach ($CI->db->select('rq.due_at, l.id AS lesson_id, l.title AS lesson_title, l.course_id')
                    ->from('review_queue rq')
                    ->join('question q', 'q.id = rq.question_id', 'inner')
                    ->join('lesson l', 'l.id = q.quiz_id', 'left')
                    ->where('rq.student_id', $uid)
                    ->order_by('rq.due_at', 'ASC')
                    ->limit(200)->get()->result_array() as $r) {
        $ts = $tq_ts($r['due_at']);
        if ($ts <= 0) continue;
        $cid   = (int) $r['course_id'];
        $title = trim((string) $r['lesson_title']);
        $tq_events[] = [
            'ts'    => $ts,
            'cat'   => 'revisions',
            'title' => t('مراجعة') . ($title !== '' ? ': ' . $title : ''),
            'sub'   => $tq_my_courses[$cid] ?? '',
            'href'  => $cid > 0
                ? base_url('student/lesson/' . $cid . '/' . (int) $r['lesson_id'])
                : base_url('student/materials'),
        ];
    }

    usort($tq_events, static function ($a, $b) { return $a['ts'] <=> $b['ts']; });
}

$tq_by_day = [];
foreach ($tq_events as $e) {
    $tq_by_day[date('Y-m-d', (int) $e['ts'])][] = $e;
}

$tq_today_events = $tq_by_day[date('Y-m-d', $tq_today)] ?? [];

/* «الثلاثون يوما القادمة» نافذة حقيقية لا عنوانا فوق قائمة بلا حد */
$tq_horizon  = $tq_today + 30 * 86400;
$tq_upcoming = array_values(array_filter($tq_events, static function ($e) use ($tq_today, $tq_horizon) {
    return (int) $e['ts'] >= $tq_today && (int) $e['ts'] < $tq_horizon;
}));
usort($tq_upcoming, static function ($a, $b) { return $a['ts'] <=> $b['ts']; });

/* ---- روابط التنقل ---------------------------------------------------- */
$tq_link = static function ($view, $date) {
    return base_url('student/calendar?view=' . $view . '&d=' . date('Y-m-d', $date));
};
$tq_prev_month = mktime(0, 0, 0, $tq_month - 1, 1, $tq_year);
$tq_next_month = mktime(0, 0, 0, $tq_month + 1, 1, $tq_year);

/* بداية أسبوع التاريخ المرجعي — الأحد */
$tq_week_from = $tq_ref - ((int) date('w', $tq_ref)) * 86400;

include 'portal_open.php';
?>

<style>
/* التقويم — الشبكة منطقية، والأسبوع يبدأ الأحد، واليوم حبة كحلية مصمتة. */
.tq-icon-box[class*='tq-pastel--'] { color: var(--tq-pastel-ink); }

.tq-calbar { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m);
  flex-wrap: wrap; margin-block-end: var(--tq-space-l); }
.tq-calnav { display: flex; align-items: center; gap: var(--tq-space-s); }
.tq-calnav__label { font: var(--tq-type-h2); color: var(--tq-navy); min-inline-size: 140px; text-align: center; }
.tq-views { display: flex; gap: var(--tq-space-xs); }
.tq-views .tq-pill[aria-current='page'] { background: var(--tq-actionPrimary); border-color: var(--tq-actionPrimary); color: var(--tq-onAction); }
.tq-views .tq-pill:hover { text-decoration: none; }

.tq-cal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); border-block-start: 1px solid var(--tq-line); border-inline-start: 1px solid var(--tq-line); }
.tq-cal__dow { padding: var(--tq-space-m); text-align: center; font: var(--tq-type-caption); color: var(--tq-text2);
  border-block-end: 1px solid var(--tq-line); border-inline-end: 1px solid var(--tq-line); }
.tq-cal__cell { min-block-size: 116px; padding: var(--tq-space-s); border-block-end: 1px solid var(--tq-line);
  border-inline-end: 1px solid var(--tq-line); display: flex; flex-direction: column; gap: var(--tq-space-xs); }
.tq-cal__cell--out { background: var(--tq-ground); }
.tq-cal__n { inline-size: 30px; block-size: 30px; border-radius: var(--tq-radius-pill); display: grid; place-items: center;
  font: var(--tq-type-numeralSm); color: var(--tq-text); unicode-bidi: isolate; direction: ltr; }
.tq-cal__cell--out .tq-cal__n { color: var(--tq-text3); }
/* اليوم الحالي: حبة كحلية مصمتة */
.tq-cal__n--today { background: var(--tq-actionPrimary); color: var(--tq-onAction); font-weight: 700; }

.tq-ev { display: flex; align-items: center; gap: var(--tq-space-xs); font: var(--tq-type-micro); color: var(--tq-navy); }
.tq-ev:hover { text-decoration: none; background: var(--tq-navyWash); border-radius: var(--tq-radius-small); }
.tq-ev__dot { inline-size: 7px; block-size: 7px; border-radius: var(--tq-radius-pill); flex: none; }
.tq-ev__t { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.tq-legend { display: flex; gap: var(--tq-space-l); flex-wrap: wrap; justify-content: center; margin-block-start: var(--tq-space-l); }
.tq-legend__key { display: inline-flex; align-items: center; gap: var(--tq-space-xs); font: var(--tq-type-caption); color: var(--tq-text2); }
.tq-legend__dot { inline-size: 10px; block-size: 10px; border-radius: var(--tq-radius-pill); flex: none; }

/* الخط الزمني الرأسي لجدول اليوم */
.tq-timeline { position: relative; padding-inline-start: var(--tq-space-h1); }
.tq-timeline::before { content: ''; position: absolute; inset-block: 0; inset-inline-start: 78px; inline-size: 2px; background: var(--tq-line); }
.tq-tl { display: grid; grid-template-columns: 70px 20px auto minmax(0, 1fr) auto; gap: var(--tq-space-m);
  align-items: center; padding-block: var(--tq-space-m); }
.tq-tl__time { font: var(--tq-type-numeralSm); color: var(--tq-text2); unicode-bidi: isolate; direction: ltr; text-align: end; }
.tq-tl__dot { inline-size: 11px; block-size: 11px; border-radius: var(--tq-radius-pill); }

.tq-weekgrid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: var(--tq-space-s); }
.tq-weekcol { background: var(--tq-surface); border: 1px solid var(--tq-line); border-radius: var(--tq-radius-medium);
  padding: var(--tq-space-m); min-block-size: 220px; display: flex; flex-direction: column; gap: var(--tq-space-xs); }
.tq-weekcol__h { text-align: center; margin-block-end: var(--tq-space-s); }

.tq-hourrow { display: grid; grid-template-columns: 70px minmax(0, 1fr); gap: var(--tq-space-m); align-items: start;
  padding-block: var(--tq-space-s); border-block-end: 1px solid var(--tq-line); }

.tq-mini { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 2px; }
.tq-mini__c { aspect-ratio: 1; display: grid; place-items: center; border-radius: var(--tq-radius-pill);
  font: var(--tq-type-numeralSm); color: var(--tq-text2); unicode-bidi: isolate; direction: ltr; }
.tq-mini__c--out { color: var(--tq-text3); }
.tq-mini__c--today { background: var(--tq-actionPrimary); color: var(--tq-onAction); }
.tq-mini__dow { text-align: center; font: var(--tq-type-micro); color: var(--tq-text2); }

.tq-calrow { display: flex; align-items: center; gap: var(--tq-space-m); padding-block: var(--tq-space-s); }
.tq-calrow input { inline-size: 18px; block-size: 18px; accent-color: var(--tq-navy); flex: none; }

@media (max-width: 1023.98px) {
  .tq-cal__cell { min-block-size: 84px; }
  .tq-weekgrid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 639.98px) {
  .tq-cal__cell { min-block-size: 64px; padding: var(--tq-space-xs); }
  .tq-weekgrid { grid-template-columns: minmax(0, 1fr); }
  .tq-timeline { padding-inline-start: 0; }
  .tq-timeline::before { display: none; }
  .tq-tl { grid-template-columns: minmax(0, 1fr); }
  .tq-tl__time { text-align: start; }
}
</style>

<div class="tq-cols">
    <div>

        <div class="tq-calbar">
            <div class="tq-calnav">
                <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo $tq_link($tq_view, $tq_today); ?>"><?php echo t('اليوم'); ?></a>
                <a class="tq-iconbtn tq-calnav__prev" href="<?php echo $tq_link($tq_view, $tq_prev_month); ?>" aria-label="<?php echo te('الشهر السابق'); ?>"><?php echo tq_icon('chev-prev', 16); ?></a>
                <span class="tq-calnav__label">
                    <?php echo html_escape($tq_month_names[$tq_month - 1]); ?> <?php echo tq_num($tq_year); ?>
                </span>
                <a class="tq-iconbtn tq-calnav__next" href="<?php echo $tq_link($tq_view, $tq_next_month); ?>" aria-label="<?php echo te('الشهر التالي'); ?>"><?php echo tq_icon('chev-next', 16); ?></a>
            </div>

            <nav class="tq-views" aria-label="<?php echo te('طريقة عرض التقويم'); ?>">
                <?php foreach (['month' => t('شهر'), 'week' => t('أسبوع'), 'day' => t('يوم'), 'agenda' => t('جدول')] as $v => $vl): ?>
                    <a class="tq-pill" href="<?php echo $tq_link($v, $tq_ref); ?>" <?php echo tq_active($v, $tq_view); ?>>
                        <?php echo html_escape($vl); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <section class="tq-card tq-card--panel tq-section" aria-label="<?php echo te('تقويم ____', array(html_escape($tq_month_names[$tq_month - 1]))); ?>">

            <?php if ($tq_view === 'month'): ?>
                <div class="tq-cal">
                    <?php foreach ($tq_day_names as $dn): ?>
                        <div class="tq-cal__dow"><?php echo html_escape($dn); ?></div>
                    <?php endforeach; ?>

                    <?php
                    $lead  = (int) date('w', $tq_first);           // الأحد = 0
                    $cells = (int) (ceil(($lead + $tq_days_in) / 7) * 7);
                    for ($i = 0; $i < $cells; $i++):
                        $stamp = $tq_first + ($i - $lead) * 86400;
                        $out   = ((int) date('n', $stamp) !== $tq_month);
                        $key   = date('Y-m-d', $stamp);
                        $evs   = $tq_by_day[$key] ?? [];
                    ?>
                        <div class="tq-cal__cell<?php echo $out ? ' tq-cal__cell--out' : ''; ?>">
                            <span class="tq-cal__n<?php echo $stamp === $tq_today ? ' tq-cal__n--today' : ''; ?>">
                                <?php echo TQ_LRI . date('j', $stamp) . TQ_PDI; ?>
                                <?php if ($stamp === $tq_today): ?><span class="tq-sr"><?php echo t('اليوم'); ?></span><?php endif; ?>
                            </span>
                            <?php foreach ($evs as $e): ?>
                                <?php $c = $tq_cats[$e['cat']] ?? $tq_cats['lessons']; ?>
                                <a class="tq-ev" data-tq-evcat="<?php echo html_escape($e['cat']); ?>" href="<?php echo html_escape($e['href']); ?>">
                                    <span class="tq-ev__dot" style="background:<?php echo $c[1]; ?>" aria-hidden="true"></span>
                                    <span class="tq-ev__t"><?php echo html_escape($e['title']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>

            <?php elseif ($tq_view === 'week'): ?>
                <div class="tq-weekgrid">
                    <?php for ($i = 0; $i < 7; $i++): ?>
                        <?php $stamp = $tq_week_from + $i * 86400; $evs = $tq_by_day[date('Y-m-d', $stamp)] ?? []; ?>
                        <div class="tq-weekcol">
                            <div class="tq-weekcol__h">
                                <span class="tq-micro" style="display:block"><?php echo html_escape($tq_day_short[$i]); ?></span>
                                <span class="tq-cal__n<?php echo $stamp === $tq_today ? ' tq-cal__n--today' : ''; ?>" style="margin-inline:auto">
                                    <?php echo TQ_LRI . date('j', $stamp) . TQ_PDI; ?>
                                </span>
                            </div>
                            <?php if (!$evs): ?>
                                <span class="tq-micro" style="text-align:center"><?php echo t('لا مواعيد'); ?></span>
                            <?php else: ?>
                                <?php foreach ($evs as $e): ?>
                                    <?php $c = $tq_cats[$e['cat']] ?? $tq_cats['lessons']; ?>
                                    <a class="tq-ev" data-tq-evcat="<?php echo html_escape($e['cat']); ?>" href="<?php echo html_escape($e['href']); ?>">
                                        <span class="tq-ev__dot" style="background:<?php echo $c[1]; ?>" aria-hidden="true"></span>
                                        <span class="tq-ev__t"><?php echo html_escape($e['title']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>

            <?php elseif ($tq_view === 'day'): ?>
                <h2 class="tq-h2" style="margin-block-end:var(--tq-space-l)">
                    <?php echo html_escape($tq_day_names[(int) date('w', $tq_ref)]); ?>
                    <?php echo tq_num(date('Y-m-d', $tq_ref), 'tq-num--sm'); ?>
                </h2>
                <?php for ($h = 7; $h <= 22; $h++): ?>
                    <?php
                    $slot = [];
                    foreach (($tq_by_day[date('Y-m-d', $tq_ref)] ?? []) as $e) {
                        if ((int) date('G', (int) $e['ts']) === $h) { $slot[] = $e; }
                    }
                    ?>
                    <div class="tq-hourrow">
                        <span class="tq-tl__time">
                            <?php echo tq_num(($h % 12 === 0 ? 12 : $h % 12) . ':00', 'tq-num--sm'); ?> <?php echo $h < 12 ? t('ص') : t('م'); ?>
                        </span>
                        <div>
                            <?php foreach ($slot as $e): ?>
                                <?php $c = $tq_cats[$e['cat']] ?? $tq_cats['lessons']; ?>
                                <a class="tq-ev" data-tq-evcat="<?php echo html_escape($e['cat']); ?>" href="<?php echo html_escape($e['href']); ?>">
                                    <span class="tq-ev__dot" style="background:<?php echo $c[1]; ?>" aria-hidden="true"></span>
                                    <span class="tq-ev__t"><?php echo html_escape($e['title']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endfor; ?>

            <?php else: ?>
                <h2 class="tq-h2" style="margin-block-end:var(--tq-space-l)"><?php echo t('جدول الثلاثين يوما القادمة'); ?></h2>
                <?php if (!$tq_upcoming): ?>
                    <div class="tq-empty">
                        <div class="tq-empty__art tq-pastel tq-pastel--sky" style="display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                            <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('calendar', 44); ?></span>
                        </div>
                        <h3 class="tq-empty__title"><?php echo t('لا مواعيد في الجدول'); ?></h3>
                        <p class="tq-empty__text">
                            <?php echo t('كل درس تحجزه وكل اختبار يفتح موعده وكل واجب له تاريخ تسليم يظهر هنا مرتبا بيومه، وبزر يأخذك إليه مباشرة.'); ?>
                        </p>
                        <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/on-demand'); ?>"><?php echo t('احجز حصة بالطلب'); ?></a>
                    </div>
                <?php else: ?>
                    <ul class="tq-stack">
                        <?php foreach ($tq_upcoming as $e): ?>
                            <?php $c = $tq_cats[$e['cat']] ?? $tq_cats['lessons']; ?>
                            <li class="tq-row tq-row--between">
                                <a class="tq-row" href="<?php echo html_escape($e['href']); ?>">
                                    <span class="tq-ev__dot" style="background:<?php echo $c[1]; ?>" aria-hidden="true"></span>
                                    <span>
                                        <span class="tq-strong" style="display:block;color:var(--tq-navy)"><?php echo html_escape($e['title']); ?></span>
                                        <span class="tq-micro"><?php echo tq_iso(html_escape($e['sub'])); ?></span>
                                    </span>
                                </a>
                                <span class="tq-micro"><?php echo tq_num(date('Y-m-d', (int) $e['ts']), 'tq-num--sm'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

            <!-- مفتاح الألوان: خمس فئات ثابتة، ولونها هو نفسه في كل الشاشات -->
            <div class="tq-legend">
                <?php foreach ($tq_cats as $c): ?>
                    <span class="tq-legend__key">
                        <span class="tq-legend__dot" style="background:<?php echo $c[1]; ?>" aria-hidden="true"></span>
                        <?php echo html_escape($c[0]); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- جدول اليوم على خط زمني رأسي، ولكل نوع زر فعله الخاص -->
        <section class="tq-card tq-card--panel tq-section" aria-labelledby="tq-day-h">
            <div class="tq-card__head">
                <h2 class="tq-card__title" id="tq-day-h">
                    <?php echo t('جدول اليوم —'); ?> <?php echo html_escape($tq_day_names[(int) date('w', $tq_today)]); ?>
                    <?php echo tq_num(date('Y-m-d', $tq_today), 'tq-num--sm'); ?>
                </h2>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo $tq_link('week', $tq_today); ?>"><?php echo t('عرض الجدول الأسبوعي'); ?></a>
            </div>

            <?php if (!$tq_today_events): ?>
                <div class="tq-empty">
                    <div class="tq-empty__art tq-pastel tq-pastel--mint" style="display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                        <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('clock', 44); ?></span>
                    </div>
                    <h3 class="tq-empty__title"><?php echo t('يومك خال من المواعيد'); ?></h3>
                    <p class="tq-empty__text">
                        <?php echo t('حين يكون لديك درس أو اختبار أو حصة بالطلب يظهر هنا على خط اليوم بوقته، وبجواره زر واحد يأخذك إليه: انضم إلى الدرس، أو ابدأ الاختبار، أو ادخل الحصة.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/on-demand'); ?>"><?php echo t('احجز حصة بالطلب'); ?></a>
                </div>
            <?php else: ?>
                <div class="tq-timeline">
                    <?php foreach ($tq_today_events as $e): ?>
                        <?php $c = $tq_cats[$e['cat']] ?? $tq_cats['lessons']; $ts = (int) $e['ts']; ?>
                        <?php /* الوسم هنا كما في شبكات الشهر والأسبوع واليوم: بدونه كان
                                 إخفاء فئة يفرغها من الشبكة ويبقيها على خط اليوم — فيرى
                                 الطالب أنه أخفى «الاختبارات» وهي أمامه، فيحسب المربع معطلا. */ ?>
                        <div class="tq-tl" data-tq-evcat="<?php echo html_escape($e['cat']); ?>">
                            <span class="tq-tl__time">
                                <?php echo tq_num(date('g:i', $ts), 'tq-num--sm'); ?> <?php echo (int) date('G', $ts) < 12 ? t('ص') : t('م'); ?>
                            </span>
                            <span class="tq-tl__dot" style="background:<?php echo $c[1]; ?>" aria-hidden="true"></span>
                            <span class="tq-icon-box tq-pastel--sky" aria-hidden="true"><?php echo tq_icon($c[2]); ?></span>
                            <span>
                                <a class="tq-strong" style="display:block;color:var(--tq-navy)" href="<?php echo html_escape($e['href']); ?>">
                                    <?php echo html_escape($e['title']); ?>
                                </a>
                                <span class="tq-micro"><?php echo tq_iso(html_escape($e['sub'])); ?></span>
                            </span>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo html_escape($e['href']); ?>">
                                <?php echo html_escape($c[3]); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <aside class="tq-aside">

        <!-- التقويمات: مربعات اختيار ملونة قابلة للإخفاء -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-cals-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-cals-h"><?php echo t('التقويمات'); ?></h2></div>
            <?php foreach ($tq_cats as $key => $c): ?>
                <div class="tq-calrow">
                    <input type="checkbox" id="tq-cal-<?php echo html_escape($key); ?>" name="cal[]"
                           value="<?php echo html_escape($key); ?>" checked
                           data-tq-cal="<?php echo html_escape($key); ?>"
                           style="accent-color:<?php echo $c[1]; ?>">
                    <label for="tq-cal-<?php echo html_escape($key); ?>" class="tq-caption" style="color:var(--tq-navy)">
                        <?php echo html_escape($c[0]); ?>
                    </label>
                    <span class="tq-legend__dot" style="background:<?php echo $c[1]; ?>;margin-inline-start:auto" aria-hidden="true"></span>
                </div>
            <?php endforeach; ?>
            <?php /* لا زر «إدارة التقويمات»: كان يقود إلى الإعدادات، وليس فيها قسم
                     تقويمات — الفئات الخمس مشتقة من الجداول لا مضافة بيد الطالب، فلا
                     شيء يدار. والمربعات أعلاه هي الضابط الحقيقي، وهو محلي لهذه
                     الزيارة. وقوله أصدق من زر يقود إلى شاشة لا يجد فيها ما وعد به. */ ?>
            <p class="tq-micro tq-muted" style="margin-block-start:var(--tq-space-m);margin-block-end:0">
                <?php echo t('الفئات الخمس ثابتة ومشتقة من مواعيدك المسجلة. وإخفاء فئة يخص هذه الزيارة وحدها.'); ?>
            </p>
        </section>

        <!-- المواعيد القادمة -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-up-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-up-h"><?php echo t('المواعيد القادمة'); ?></h2></div>
            <?php if (!$tq_upcoming): ?>
                <p class="tq-caption" style="margin:0">
                    <?php echo t('أقرب ثلاثة مواعيد ستظهر هنا بوقتها ومادتها، وكل منها رابط يأخذك إليه مباشرة.'); ?>
                </p>
            <?php else: ?>
                <ul class="tq-stack">
                    <?php foreach (array_slice($tq_upcoming, 0, 3) as $e): ?>
                        <?php $c = $tq_cats[$e['cat']] ?? $tq_cats['lessons']; ?>
                        <li data-tq-evcat="<?php echo html_escape($e['cat']); ?>">
                            <a class="tq-row" href="<?php echo html_escape($e['href']); ?>">
                                <span class="tq-icon-box tq-pastel--mint" aria-hidden="true"><?php echo tq_icon($c[2], 18); ?></span>
                                <span>
                                    <span class="tq-caption" style="display:block;color:var(--tq-navy)"><?php echo html_escape($e['title']); ?></span>
                                    <span class="tq-micro"><?php echo tq_iso(html_escape($e['sub'])); ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- تذكير اليوم -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-rem-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-rem-h"><?php echo t('تذكير اليوم'); ?></h2></div>
            <?php if (!$tq_today_events): ?>
                <p class="tq-caption" style="margin:0">
                    <?php echo t('لا شيء يستحق التذكير اليوم. حين يكون لديك مواعيد سيظهر عددها هنا وما مضى منها.'); ?>
                </p>
            <?php else: ?>
                <?php
                /* الشريط يقيس ما مضى وقته من مواعيد اليوم — قياس من الساعة
                   والطوابع الزمنية، لا نسبة إنجاز لا مصدر لها. */
                $tq_passed = 0;
                foreach ($tq_today_events as $e) {
                    if ((int) $e['ts'] <= time()) $tq_passed++;
                }
                ?>
                <p class="tq-caption">
                    <?php echo tq_iso(t('لديك ') . count($tq_today_events) . t(' موعدا اليوم')); ?>
                </p>
                <?php echo tq_progress((int) round($tq_passed * 100 / count($tq_today_events)), t('ما مضى من مواعيد اليوم')); ?>
                <a class="tq-btn tq-btn--secondary tq-btn--block" href="<?php echo $tq_link('day', $tq_today); ?>" style="margin-block-start:var(--tq-space-m)">
                    <?php echo t('عرض التفاصيل'); ?>
                </a>
            <?php endif; ?>
        </section>

        <!-- تقويم الشهر التالي -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-mini-h">
            <div class="tq-card__head">
                <h2 class="tq-card__title" id="tq-mini-h">
                    <?php echo html_escape($tq_month_names[(int) date('n', $tq_next_month) - 1]); ?>
                    <?php echo tq_num(date('Y', $tq_next_month), 'tq-num--sm'); ?>
                </h2>
            </div>
            <div class="tq-mini" aria-hidden="true">
                <?php foreach ($tq_day_short as $dn): ?>
                    <span class="tq-mini__dow"><?php echo html_escape(mb_substr($dn, 0, 3)); ?></span>
                <?php endforeach; ?>
                <?php
                $nlead  = (int) date('w', $tq_next_month);
                $ndays  = (int) date('t', $tq_next_month);
                $ncells = (int) (ceil(($nlead + $ndays) / 7) * 7);
                for ($i = 0; $i < $ncells; $i++):
                    $st  = $tq_next_month + ($i - $nlead) * 86400;
                    $out = ((int) date('n', $st) !== (int) date('n', $tq_next_month));
                ?>
                    <span class="tq-mini__c<?php echo $out ? ' tq-mini__c--out' : ''; ?><?php echo $st === $tq_today ? ' tq-mini__c--today' : ''; ?>">
                        <?php echo TQ_LRI . date('j', $st) . TQ_PDI; ?>
                    </span>
                <?php endfor; ?>
            </div>
            <a class="tq-btn tq-btn--ghost tq-btn--sm tq-btn--block" href="<?php echo $tq_link('month', $tq_next_month); ?>" style="margin-block-start:var(--tq-space-m)">
                <?php echo t('الذهاب إلى الشهر التالي'); ?>
            </a>
        </section>

    </aside>
</div>

<script>
/* إخفاء فئة من التقويم يخفي أحداثها فعلا — عرض محلي لا يمس البيانات. */
(function () {
  var boxes = document.querySelectorAll('[data-tq-cal]');
  Array.prototype.forEach.call(boxes, function (box) {
    box.addEventListener('change', function () {
      var sel = document.querySelectorAll('[data-tq-evcat="' + box.getAttribute('data-tq-cal') + '"]');
      Array.prototype.forEach.call(sel, function (el) { el.hidden = !box.checked; });
    });
  });
})();
</script>

<?php include 'portal_close.php'; ?>
