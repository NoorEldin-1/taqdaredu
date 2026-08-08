<?php
/**
 * المتابعة والتقارير — بوابة الطالب.
 *
 * ── قاعدة ملزمة لهذه الشاشة ──────────────────────────────────────────────
 * كل مقارنة هنا مقارنة الطالب بنفسه في الأسبوع الماضي. ولا يعرض ترتيب بين
 * الطلاب ولا متوسط الفصل ولا موقع الطالب منه — المقارنة بالأقران تصنع قلقا
 * ولا تصنع تعلما، وأغلب مستخدمي المنصة قاصرون. المرجع الوحيد للطالب هو
 * الطالب نفسه قبل أسبوع.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * «وقت الدراسة» = الساعات الفعلية داخل المحتوى، مصدرها watched_duration
 * (ثوان مشاهدة مسجلة لكل درس) لا زمن فتح الصفحة ولا مدة الجلسة.
 *
 * ما لا مصدر له في قاعدة taqd_lms بعد (أهداف الشهر · ملاحظات المعلم) يعرض
 * حالة فارغة صحيحة لا رقما مخترعا.
 */

$tq_nav   = 'reports';
$tq_role  = 'student';
$tq_title = 'المتابعة والتقارير';
$tq_sub   = 'نظرة شاملة على تقدمك الدراسي وأدائك';
$tq_icon  = 'chart';

$uid = (int) $this->session->userdata('user_id');

/* ---- الكورسات المسجلة ------------------------------------------------- */
$tq_enrolled = $this->db->select('c.id, c.title, c.category_id')
    ->from('enrol e')
    ->join('course c', 'c.id = e.course_id', 'inner')
    ->where('e.user_id', $uid)
    ->get()->result_array();

$tq_course_ids = array_map(static function ($r) { return (int) $r['id']; }, $tq_enrolled);
$tq_has_data   = count($tq_course_ids) > 0;

/* ---- وقت الدراسة الفعلي داخل المحتوى ---------------------------------- */
$tq_seconds = (int) $this->db->select_sum('current_duration', 'total')
    ->where('watched_student_id', $uid)
    ->get('watched_duration')->row('total');
$tq_hours   = intdiv($tq_seconds, 3600);
$tq_minutes = intdiv($tq_seconds % 3600, 60);

/* ---- تقدم الكورسات ---------------------------------------------------- */
$tq_history = $this->db->where('student_id', $uid)->get('watch_histories')->result_array();

$tq_progress_sum = 0;
$tq_done_lessons = 0;
$tq_progress_by_course = [];
foreach ($tq_history as $row) {
    $tq_progress_sum += (int) $row['course_progress'];
    $tq_progress_by_course[(int) $row['course_id']] = (int) $row['course_progress'];
    /* `completed_lesson` قائمة معرفات يضاف إليها عند كل إكمال، وقد يكرر
       المعرف نفسه فيها. فعدها خاما كان يعطي «١٤ من ١٢ درسا» — رقما
       يفضح نفسه. والتفريد هنا هو نفسه المعمول به في `tq_s_enrolled()`،
       فلا يفترق رقم الشاشتين. */
    $done = json_decode($row['completed_lesson'], true);
    if (is_array($done)) {
        $tq_done_lessons += count(array_unique($done));
    }
}
$tq_completion = $tq_history ? (int) round($tq_progress_sum / count($tq_history)) : 0;

/* عدد الدروس — والاختبار ليس درسا، فلا يحسب في مقام «الدروس المكتملة» */
$tq_total_lessons = 0;
$tq_lessons_by_course = [];
if ($tq_course_ids) {
    foreach ($this->db->select('course_id, COUNT(*) AS n')
                      ->from('lesson')
                      ->where_in('course_id', $tq_course_ids)
                      ->where('lesson_type !=', 'quiz')
                      ->group_by('course_id')->get()->result_array() as $r) {
        $tq_lessons_by_course[(int) $r['course_id']] = (int) $r['n'];
        $tq_total_lessons += (int) $r['n'];
    }
}

/* ---- الاختبارات: العلامة نسبة إلى عدد الأسئلة ------------------------- */
$tq_quizzes = $this->db->where('user_id', $uid)->where('is_submitted', 1)
    ->order_by('date_added', 'ASC')
    ->get('quiz_results')->result_array();

$tq_quiz_points = [];   // [الطابع الزمني => النسبة]
foreach ($tq_quizzes as $q) {
    $answers = json_decode($q['correct_answers'], true);
    $count   = is_array($answers) ? count($answers) : 0;
    if ($count < 1) {
        continue;
    }
    // درجة لم يعتمدها المعلم بعد لا تدخل متوسطا يقرؤه الطالب على أنه أداؤه
    if (!tq_grade_visible($q)) { continue; }
    $tq_quiz_points[(int) $q['date_added']] = max(0, min(100, (int) round(((float) $q['total_obtained_marks'] / $count) * 100)));
}
$tq_average = $tq_quiz_points ? (int) round(array_sum($tq_quiz_points) / count($tq_quiz_points)) : 0;

/* ---- الأسابيع الثمانية الأخيرة — والأسبوع يبدأ الأحد ------------------- */
$tq_today_start = strtotime('today');
$tq_week_start  = $tq_today_start - ((int) date('w', $tq_today_start)) * 86400;

$tq_weeks = [];
for ($i = 7; $i >= 0; $i--) {
    $from = $tq_week_start - $i * 7 * 86400;
    $tq_weeks[] = ['from' => $from, 'to' => $from + 7 * 86400];
}

/* متوسط الدرجات لكل أسبوع — بيانات فعلية من تواريخ تسليم الاختبارات */
$tq_grade_series = [];
foreach ($tq_weeks as $w) {
    $vals = [];
    foreach ($tq_quiz_points as $ts => $pct) {
        if ($ts >= $w['from'] && $ts < $w['to']) {
            $vals[] = $pct;
        }
    }
    $tq_grade_series[] = $vals ? (int) round(array_sum($vals) / count($vals)) : null;
}

/* نسبة الإنجاز لكل أسبوع — التقدم المسجل للكورسات التي جرى تحديثها في ذلك
   الأسبوع. قيمة مسجلة لا مستنتجة، ولذلك تترك فارغة في الأسابيع بلا تحديث. */
$tq_completion_series = [];
foreach ($tq_weeks as $w) {
    $vals = [];
    foreach ($tq_history as $row) {
        $ts = (int) $row['date_updated'];
        if ($ts >= $w['from'] && $ts < $w['to']) {
            $vals[] = (int) $row['course_progress'];
        }
    }
    $tq_completion_series[] = $vals ? (int) round(array_sum($vals) / count($vals)) : null;
}

/* دلتا «من الأسبوع الماضي» — تحسب فقط حين يوجد قياس فعلي للأسبوعين. */
$tq_delta = static function ($series) {
    $n = count($series);
    if ($n < 2 || $series[$n - 1] === null || $series[$n - 2] === null) {
        return null;
    }
    return $series[$n - 1] - $series[$n - 2];
};
$tq_grade_delta      = $tq_delta($tq_grade_series);
$tq_completion_delta = $tq_delta($tq_completion_series);

/* ---- الدروس المكتملة أسبوعا بعد أسبوع ---------------------------------
   مصدرها lesson_progress.completed_at — الطابع الزمني الوحيد في القاعدة
   لإكمال درس بعينه. (watch_histories.completed_lesson مصفوفة معرفات بلا
   تاريخ لكل عنصر، فلا تصلح سلسلة زمنية.) والخط تراكمي لأن «المكتملة»
   عدد لا يتناقص. */
$CI = get_instance();
$tq_lesson_done_ts = [];
foreach ($CI->db->select('completed_at')->from('lesson_progress')
                ->where('student_id', $uid)
                ->where('completed_at IS NOT NULL', null, false)
                ->get()->result_array() as $r) {
    $ts = strtotime((string) $r['completed_at']);
    if ($ts) $tq_lesson_done_ts[] = $ts;
}

$tq_lessons_series = [];
foreach ($tq_weeks as $w) {
    $n = 0;
    foreach ($tq_lesson_done_ts as $ts) {
        if ($ts < $w['to']) $n++;
    }
    $tq_lessons_series[] = $n > 0 ? $n : null;
}

/* ---- أداؤك في المواد: تجميع الكورسات بالتصنيف ------------------------- */
$tq_subjects = [];
if ($tq_enrolled) {
    $cat_ids = array_values(array_unique(array_filter(array_map(static function ($r) {
        return (int) $r['category_id'];
    }, $tq_enrolled))));
    $cat_names = [];
    if ($cat_ids) {
        foreach ($this->db->where_in('id', $cat_ids)->get('category')->result_array() as $c) {
            $cat_names[(int) $c['id']] = $c['name'];
        }
    }
    foreach ($tq_enrolled as $row) {
        $key = (int) $row['category_id'];
        $name = $cat_names[$key] ?? $row['title'];
        if (!isset($tq_subjects[$key])) {
            $tq_subjects[$key] = ['name' => $name, 'sum' => 0, 'courses' => 0, 'lessons' => 0];
        }
        $tq_subjects[$key]['sum']     += $tq_progress_by_course[(int) $row['id']] ?? 0;
        $tq_subjects[$key]['lessons'] += $tq_lessons_by_course[(int) $row['id']] ?? 0;
        $tq_subjects[$key]['courses']++;
    }
    $tq_subjects = array_slice($tq_subjects, 0, 5, true);
}

/* ---- سجل النشاط: أحدث ما سجلته القاعدة فعلا ------------------------- */
$tq_activity = [];
foreach ($tq_history as $row) {
    if (!empty($row['date_updated'])) {
        $title = $this->db->select('title')->where('id', (int) $row['course_id'])->get('course')->row('title');
        $tq_activity[] = [
            'ts'   => (int) $row['date_updated'],
            'icon' => 'play',
            'text' => 'تقدمك في «' . ($title ?: 'كورس') . '»',
        ];
    }
}
foreach ($tq_quizzes as $q) {
    $answers = json_decode($q['correct_answers'], true);
    $count   = is_array($answers) ? count($answers) : 0;
    if ($count < 1) {
        continue;
    }
    if (!tq_grade_visible($q)) {
        $tq_activity[] = array('ts' => (int) $q['date_added'], 'icon' => 'clipboard',
                               'text' => 'سلمت اختبارا، وينتظر اعتماد معلمك');
        continue;
    }
    $pct = (int) round(((float) $q['total_obtained_marks'] / $count) * 100);
    $tq_activity[] = [
        'ts'   => (int) $q['date_added'],
        'icon' => 'check-badge',
        'text' => 'سلمت اختبارا ونتيجتك ' . $pct . '%',
    ];
}
usort($tq_activity, static function ($a, $b) { return $b['ts'] <=> $a['ts']; });
$tq_activity = array_slice($tq_activity, 0, 4);

/* ---- رسم الشرارة: خط من قيم حقيقية، ويحذف حين لا سلسلة ------------- */
$tq_spark = static function (array $values, $tone) {
    $pts = array_values(array_filter($values, static function ($v) { return $v !== null; }));
    if (count($pts) < 2) {
        return '';
    }
    $w = 200;
    $h = 40;
    $max = max($pts);
    $min = min($pts);
    $span = ($max - $min) ?: 1;
    $out = [];
    $n = count($pts);
    for ($i = 0; $i < $n; $i++) {
        $x = round($i * ($w - 6) / ($n - 1) + 3, 1);
        $y = round($h - 4 - (($pts[$i] - $min) / $span) * ($h - 8), 1);
        $out[] = $x . ',' . $y;
    }
    return '<svg class="tq-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none"'
        . ' aria-hidden="true" style="color:var(--tq-' . $tone . ')">'
        . '<polyline points="' . implode(' ', $out) . '" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
};

include 'portal_open.php';
?>

<style>
/* التقارير — تخطيط خاص بالشاشة، بالتوكنات وحدها وبمنطق start/end. */
/* مربع الأيقونة يرث حبر عائلته الباستيلية — والحبر للأيقونة وحدها لا للنص. */
.tq-icon-box[class*='tq-pastel--'] { color: var(--tq-pastel-ink); }
.tq-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--tq-space-l); }
.tq-kpi { display: flex; flex-direction: column; gap: var(--tq-space-m); }
.tq-kpi__top { display: flex; align-items: center; gap: var(--tq-space-m); }
.tq-kpi__label { font: var(--tq-type-caption); color: var(--tq-text2); }
.tq-kpi__value { margin: 0; color: var(--tq-navy); display: flex; align-items: baseline; gap: var(--tq-space-xs); flex-wrap: wrap; }
.tq-kpi__unit { font: var(--tq-type-caption); color: var(--tq-text2); }
.tq-kpi__delta { display: inline-flex; align-items: center; gap: var(--tq-space-xs); font: var(--tq-type-micro); }
.tq-kpi__delta--up { color: var(--tq-teal); }
.tq-kpi__delta--down { color: var(--tq-danger); }
.tq-kpi__delta--flat { color: var(--tq-text2); }
.tq-spark { inline-size: 100%; block-size: 40px; }
html[dir='rtl'] .tq-spark { transform: scaleX(-1); }

.tq-chart { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: var(--tq-space-m); align-items: stretch; }
.tq-chart__yaxis { display: flex; flex-direction: column; justify-content: space-between; padding-block: 2px; }
.tq-chart__plot { position: relative; min-block-size: 220px; }
.tq-chart__svg { inline-size: 100%; block-size: 220px; }
html[dir='rtl'] .tq-chart__svg { transform: scaleX(-1); }
.tq-chart__xaxis { display: flex; justify-content: space-between; margin-block-start: var(--tq-space-s); }
.tq-legend { display: flex; gap: var(--tq-space-l); flex-wrap: wrap; }
.tq-legend__key { display: inline-flex; align-items: center; gap: var(--tq-space-xs); font: var(--tq-type-caption); color: var(--tq-text2); }
.tq-legend__dot { inline-size: 10px; block-size: 10px; border-radius: var(--tq-radius-pill); flex: none; }

.tq-subjects { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: var(--tq-space-l); }
.tq-subject { display: grid; justify-items: center; gap: var(--tq-space-m); text-align: center; }

.tq-noteline { display: flex; gap: var(--tq-space-m); align-items: flex-start; }
.tq-noteline + .tq-noteline { margin-block-start: var(--tq-space-l); padding-block-start: var(--tq-space-l); border-block-start: 1px solid var(--tq-line); }

@media (max-width: 1023.98px) {
  .tq-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .tq-subjects { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 639.98px) {
  .tq-kpis { grid-template-columns: minmax(0, 1fr); }
  .tq-subjects { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

<div class="tq-tabs" role="tablist" data-tq-tabs aria-label="أقسام التقارير">
    <button class="tq-tab" role="tab" id="tq-rt-overview" aria-controls="tq-rp-overview" aria-selected="true" tabindex="0" type="button">نظرة عامة</button>
    <button class="tq-tab" role="tab" id="tq-rt-subjects" aria-controls="tq-rp-subjects" aria-selected="false" tabindex="-1" type="button">المواد</button>
    <button class="tq-tab" role="tab" id="tq-rt-quizzes" aria-controls="tq-rp-quizzes" aria-selected="false" tabindex="-1" type="button">الاختبارات</button>
    <button class="tq-tab" role="tab" id="tq-rt-activity" aria-controls="tq-rp-activity" aria-selected="false" tabindex="-1" type="button">سجل النشاط</button>
</div>

<div class="tq-cols">
    <div>

        <div id="tq-rp-overview" role="tabpanel" aria-labelledby="tq-rt-overview">

            <?php if (!$tq_has_data): ?>
                <div class="tq-card tq-card--panel tq-section">
                    <div class="tq-empty">
                        <div class="tq-empty__art tq-pastel tq-pastel--sky" style="display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                            <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('chart', 44); ?></span>
                        </div>
                        <h2 class="tq-empty__title">تقريرك يبدأ مع أول درس</h2>
                        <p class="tq-empty__text">
                            هنا سيظهر وقت دراستك الفعلي داخل الدروس، ونسبة إنجازك، ومتوسط درجاتك،
                            ومنحنى تقدمك أسبوعا بعد أسبوع. سجل في مادة واحدة ليبدأ القياس.
                        </p>
                        <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>">تصفح المواد</a>
                    </div>
                </div>
            <?php else: ?>

                <!-- أربعة مؤشرات: كلها مقارنة بالأسبوع الماضي للطالب نفسه -->
                <div class="tq-kpis tq-section tq-stagger">

                    <div class="tq-card tq-kpi">
                        <div class="tq-kpi__top">
                            <span class="tq-icon-box tq-pastel--sky" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon('clock'); ?></span>
                            </span>
                            <span class="tq-kpi__label">وقت الدراسة داخل المحتوى</span>
                        </div>
                        <p class="tq-kpi__value">
                            <?php echo tq_num($tq_hours, 'tq-num--xl'); ?><span class="tq-kpi__unit">ساعة</span>
                            <?php echo tq_num($tq_minutes); ?><span class="tq-kpi__unit">دقيقة</span>
                        </p>
                        <span class="tq-kpi__delta tq-kpi__delta--flat">
                            زمن تشغيل فعلي مسجل، لا زمن فتح الصفحة
                        </span>
                        <?php /* لا خط أسبوعي هنا: watched_duration يخزن مجموع الثواني
                                 لكل درس بلا طابع زمني، فلا يمكن تقطيعه أسابيع.
                                 وخط من قيم مخترعة أسوأ من غياب الخط. */ ?>
                    </div>

                    <div class="tq-card tq-kpi">
                        <div class="tq-kpi__top">
                            <span class="tq-icon-box tq-pastel--mint" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon('check'); ?></span>
                            </span>
                            <span class="tq-kpi__label">نسبة الإنجاز</span>
                        </div>
                        <p class="tq-kpi__value"><?php echo tq_num($tq_completion . '%', 'tq-num--xl'); ?></p>
                        <?php if ($tq_completion_delta === null): ?>
                            <span class="tq-kpi__delta tq-kpi__delta--flat">تقارن بنفسك بعد أول أسبوع كامل</span>
                        <?php else: ?>
                            <span class="tq-kpi__delta tq-kpi__delta--<?php echo $tq_completion_delta > 0 ? 'up' : ($tq_completion_delta < 0 ? 'down' : 'flat'); ?>">
                                <?php echo tq_num(($tq_completion_delta > 0 ? '+' : '') . $tq_completion_delta . '%', 'tq-num--sm'); ?>
                                من الأسبوع الماضي
                            </span>
                        <?php endif; ?>
                        <?php echo $tq_spark($tq_completion_series, 'teal'); ?>
                    </div>

                    <div class="tq-card tq-kpi">
                        <div class="tq-kpi__top">
                            <span class="tq-icon-box tq-pastel--lilac" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon('target'); ?></span>
                            </span>
                            <span class="tq-kpi__label">الدروس المكتملة</span>
                        </div>
                        <p class="tq-kpi__value">
                            <?php echo tq_num($tq_done_lessons . ' / ' . $tq_total_lessons, 'tq-num--xl'); ?>
                        </p>
                        <span class="tq-kpi__delta tq-kpi__delta--flat">من دروس موادك المسجلة</span>
                        <?php echo $tq_spark($tq_lessons_series, 'teal'); ?>
                    </div>

                    <div class="tq-card tq-kpi">
                        <div class="tq-kpi__top">
                            <span class="tq-icon-box tq-pastel--peach" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon('star'); ?></span>
                            </span>
                            <span class="tq-kpi__label">المتوسط العام</span>
                        </div>
                        <p class="tq-kpi__value"><?php echo tq_num($tq_average . '%', 'tq-num--xl'); ?></p>
                        <?php if ($tq_grade_delta === null): ?>
                            <span class="tq-kpi__delta tq-kpi__delta--flat">تقارن بنفسك بعد أول أسبوع كامل</span>
                        <?php else: ?>
                            <span class="tq-kpi__delta tq-kpi__delta--<?php echo $tq_grade_delta > 0 ? 'up' : ($tq_grade_delta < 0 ? 'down' : 'flat'); ?>">
                                <?php echo tq_num(($tq_grade_delta > 0 ? '+' : '') . $tq_grade_delta . '%', 'tq-num--sm'); ?>
                                من الأسبوع الماضي
                            </span>
                        <?php endif; ?>
                        <?php echo $tq_spark($tq_grade_series, 'teal'); ?>
                    </div>

                </div>

                <!-- منحنى التقدم بمفتاح خطين -->
                <?php
                $tq_has_curve = false;
                foreach (array_merge($tq_grade_series, $tq_completion_series) as $v) {
                    if ($v !== null) { $tq_has_curve = true; break; }
                }
                ?>
                <section class="tq-card tq-card--panel tq-section" aria-labelledby="tq-curve-h">
                    <div class="tq-card__head">
                        <h2 class="tq-card__title" id="tq-curve-h">تقدمك خلال الأسابيع</h2>
                        <span class="tq-pill" aria-hidden="true">آخر <?php echo tq_num(8, 'tq-num--sm'); ?> أسابيع</span>
                    </div>

                    <div class="tq-legend" style="margin-block-end:var(--tq-space-l)">
                        <span class="tq-legend__key">
                            <span class="tq-legend__dot" style="background:var(--tq-navy)" aria-hidden="true"></span>
                            نسبة الإنجاز
                        </span>
                        <span class="tq-legend__key">
                            <span class="tq-legend__dot" style="background:var(--tq-teal)" aria-hidden="true"></span>
                            متوسط الدرجات
                        </span>
                    </div>

                    <?php if (!$tq_has_curve): ?>
                        <div class="tq-empty">
                            <h3 class="tq-empty__title">لا قياس أسبوعي بعد</h3>
                            <p class="tq-empty__text">
                                يرسم المنحنى خطين: نسبة إنجازك ومتوسط درجاتك، أسبوعا بعد أسبوع.
                                يبدأ الرسم بمجرد إكمال أول درس أو تسليم أول اختبار.
                            </p>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('student/lessons'); ?>">ابدأ درسا الآن</a>
                        </div>
                    <?php else: ?>
                        <?php
                        $cw = 640; $ch = 220; $pad = 8;
                        $line = static function ($series, $color) use ($cw, $ch, $pad) {
                            $pts = [];
                            $n = count($series);
                            for ($i = 0; $i < $n; $i++) {
                                if ($series[$i] === null) { continue; }
                                $x = round($i * ($cw - 2 * $pad) / max(1, $n - 1) + $pad, 1);
                                $y = round($ch - $pad - ($series[$i] / 100) * ($ch - 2 * $pad), 1);
                                $pts[] = [$x, $y];
                            }
                            if (!$pts) { return ''; }
                            $poly = implode(' ', array_map(static function ($p) { return $p[0] . ',' . $p[1]; }, $pts));
                            $dots = '';
                            foreach ($pts as $p) {
                                $dots .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="4" fill="' . $color . '"/>';
                            }
                            return '<polyline points="' . $poly . '" fill="none" stroke="' . $color . '" stroke-width="2.5"'
                                . ' stroke-linecap="round" stroke-linejoin="round"/>' . $dots;
                        };
                        ?>
                        <div class="tq-chart">
                            <div class="tq-chart__yaxis" aria-hidden="true">
                                <?php foreach ([100, 75, 50, 25, 0] as $tick): ?>
                                    <span class="tq-micro"><?php echo tq_num($tick . '%', 'tq-num--sm'); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div>
                                <div class="tq-chart__plot">
                                    <svg class="tq-chart__svg" viewBox="0 0 <?php echo $cw . ' ' . $ch; ?>" preserveAspectRatio="none"
                                         role="img" aria-label="منحنى نسبة الإنجاز ومتوسط الدرجات خلال الأسابيع الثمانية الأخيرة">
                                        <?php foreach ([0, 25, 50, 75, 100] as $g): ?>
                                            <line x1="<?php echo $pad; ?>" x2="<?php echo $cw - $pad; ?>"
                                                  y1="<?php echo round($ch - $pad - ($g / 100) * ($ch - 2 * $pad), 1); ?>"
                                                  y2="<?php echo round($ch - $pad - ($g / 100) * ($ch - 2 * $pad), 1); ?>"
                                                  stroke="var(--tq-line)" stroke-width="1"/>
                                        <?php endforeach; ?>
                                        <?php echo $line($tq_completion_series, 'var(--tq-navy)'); ?>
                                        <?php echo $line($tq_grade_series, 'var(--tq-teal)'); ?>
                                    </svg>
                                </div>
                                <?php /* الرقم وحده على المحور. ثمانية عناوين بنص «الأسبوع ن»
                                         مجموع عرضها يفوق عرض الرسم، و`space-between` لا تضغط
                                         النص فيفيض آخرها خارج البطاقة ويقطع نصفه. والسياق
                                         مكتوب فوق الرسم («آخر 8 أسابيع»)، والاسم الكامل يبقى
                                         لقارئ الشاشة. */ ?>
                                <div class="tq-chart__xaxis">
                                    <?php foreach ($tq_weeks as $i => $w): ?>
                                        <span class="tq-micro"><span class="tq-sr">الأسبوع </span><?php echo tq_num($i + 1, 'tq-num--sm'); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

            <?php endif; ?>

            <!-- أداؤك في المواد — جزء من النظرة العامة كما في الشاشة المرجعية.
                 ولا يعرض حين لا مواد: الشاشة كانت تكدس ثلاث حالات فارغة فوق
                 بعضها («تقريرك يبدأ مع أول درس» ثم «لا مواد مسجلة بعد» مرتين)
                 وثلاثة أزرار أساسية إلى الوجهة نفسها. النداء الواحد يسمع. -->
            <?php if ($tq_has_data): ?>
            <section class="tq-card tq-card--panel tq-section" aria-labelledby="tq-subj-h">
            <div class="tq-card__head">
                <h2 class="tq-card__title" id="tq-subj-h">أداؤك في المواد</h2>
            </div>
            <?php if (!$tq_subjects): ?>
                <div class="tq-empty">
                    <h3 class="tq-empty__title">لا مواد مسجلة بعد</h3>
                    <p class="tq-empty__text">لكل مادة حلقة تظهر نسبة إتقانك فيها وعدد دروسها المكتملة، بمجرد تسجيلك في مادة.</p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>">تصفح المواد</a>
                </div>
            <?php else: ?>
                <div class="tq-subjects">
                    <?php foreach (array_values($tq_subjects) as $i => $s): ?>
                        <?php $pct = $s['courses'] ? (int) round($s['sum'] / $s['courses']) : 0; ?>
                        <div class="tq-subject">
                            <?php echo tq_ring($pct, 96, 9); ?>
                            <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($s['name']); ?></span>
                            <span class="tq-micro">
                                <?php echo tq_iso($s['courses'] . ' كورس مسجل'); ?>
                                <?php if ($s['lessons'] > 0): ?>
                                    · <?php echo tq_iso($s['lessons'] . ($s['lessons'] > 10 ? ' درسا' : ' دروس')); ?>
                                <?php endif; ?>
                            </span>
                            <a class="tq-btn tq-btn--ghost tq-btn--sm tq-btn--block" href="<?php echo base_url('student/lessons'); ?>">عرض التفاصيل</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </section>
            <?php endif; ?>

        </div>

        <!-- المواد: تفصيل كل كورس مسجل بتقدمه المسجل -->
        <section class="tq-card tq-card--panel tq-section" id="tq-rp-subjects" role="tabpanel" aria-labelledby="tq-rt-subjects" hidden>
            <div class="tq-card__head">
                <h2 class="tq-card__title">تقدمك في كل مادة</h2>
            </div>
            <?php if (!$tq_enrolled): ?>
                <div class="tq-empty">
                    <h3 class="tq-empty__title">لا مواد مسجلة بعد</h3>
                    <p class="tq-empty__text">بعد تسجيلك في مادة يظهر لكل كورس صف بتقدمك المسجل فيه ونسبته.</p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>">تصفح المواد</a>
                </div>
            <?php else: ?>
                <table class="tq-table">
                    <caption class="tq-sr">تقدمك في كل كورس مسجل</caption>
                    <thead>
                        <tr><th scope="col">الكورس</th><th scope="col">التقدم</th><th scope="col">الحالة</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tq_enrolled as $row): ?>
                            <?php $p = $tq_progress_by_course[(int) $row['id']] ?? 0; ?>
                            <tr>
                                <td data-label="الكورس"><?php echo html_escape($row['title']); ?></td>
                                <td data-label="التقدم"><?php echo tq_progress($p, 'تقدمك في الكورس'); ?></td>
                                <td data-label="الحالة"><?php echo tq_badge($p >= 100 ? 'mastered' : ($p > 0 ? 'progress' : 'idle'), $p >= 100 ? 'مكتمل' : ($p > 0 ? 'قيد التقدم' : 'لم يبدأ')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- الاختبارات -->
        <section class="tq-card tq-card--panel tq-section" id="tq-rp-quizzes" role="tabpanel" aria-labelledby="tq-rt-quizzes" hidden>
            <div class="tq-card__head">
                <h2 class="tq-card__title">نتائج اختباراتك</h2>
            </div>
            <?php if (!$tq_quiz_points): ?>
                <div class="tq-empty">
                    <h3 class="tq-empty__title">لم تسلم اختبارا بعد</h3>
                    <p class="tq-empty__text">هنا تظهر كل نتيجة سلمتها بتاريخها ونسبتها، وتقارن بنتيجتك أنت في الأسبوع الماضي.</p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/exams'); ?>">اذهب إلى اختباراتي</a>
                </div>
            <?php else: ?>
                <table class="tq-table">
                    <caption class="tq-sr">نتائج الاختبارات المسلمة مرتبة من الأحدث</caption>
                    <thead>
                        <tr><th scope="col">التاريخ</th><th scope="col">النسبة</th><th scope="col">الحالة</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($tq_quiz_points, true) as $ts => $pct): ?>
                            <tr>
                                <td data-label="التاريخ"><?php echo tq_num(date('Y-m-d', $ts), 'tq-num--sm'); ?></td>
                                <td data-label="النسبة"><?php echo tq_progress($pct, 'نسبة الاختبار'); ?></td>
                                <td data-label="الحالة"><?php echo tq_badge($pct >= 80 ? 'mastered' : ($pct >= 50 ? 'progress' : 'late'), $pct >= 80 ? 'متقن' : ($pct >= 50 ? 'قيد التقدم' : 'يحتاج مراجعة')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- سجل النشاط -->
        <section class="tq-card tq-card--panel tq-section" id="tq-rp-activity" role="tabpanel" aria-labelledby="tq-rt-activity" hidden>
            <div class="tq-card__head">
                <h2 class="tq-card__title">سجل النشاط الكامل</h2>
            </div>
            <?php if (!$tq_activity): ?>
                <div class="tq-empty">
                    <h3 class="tq-empty__title">السجل فارغ</h3>
                    <p class="tq-empty__text">كل درس تكمله وكل اختبار تسلمه يسجل هنا بوقته، فترى بنفسك أين ذهب وقتك.</p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/lessons'); ?>">ابدأ درسا الآن</a>
                </div>
            <?php else: ?>
                <ul>
                    <?php foreach ($tq_activity as $a): ?>
                        <li class="tq-noteline">
                            <span class="tq-icon-box tq-pastel--mint" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon($a['icon']); ?></span>
                            </span>
                            <span>
                                <span class="tq-body" style="display:block"><?php echo tq_iso(html_escape($a['text'])); ?></span>
                                <span class="tq-micro"><?php echo html_escape(tq_since($a['ts'])); ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>

    <aside class="tq-aside">

        <!-- ملخص الأداء -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-sum-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-sum-h">ملخص الأداء</h2></div>
            <div style="display:grid;justify-items:center;gap:var(--tq-space-l)">
                <?php echo tq_ring($tq_completion, 150, 13, 'أداؤك العام'); ?>
            </div>
            <div class="tq-stack" style="margin-block-start:var(--tq-space-xl)">
                <div>
                    <span class="tq-caption">نسبة الإنجاز</span>
                    <?php echo tq_progress($tq_completion, 'نسبة الإنجاز'); ?>
                </div>
                <div>
                    <span class="tq-caption">متوسط الدرجات</span>
                    <?php echo tq_progress($tq_average, 'متوسط الدرجات'); ?>
                </div>
                <div>
                    <span class="tq-caption">الدروس المكتملة</span>
                    <?php echo tq_progress($tq_total_lessons ? (int) round($tq_done_lessons * 100 / $tq_total_lessons) : 0, 'الدروس المكتملة'); ?>
                </div>
            </div>
            <p class="tq-micro" style="margin-block-start:var(--tq-space-l);margin-block-end:0">
                كل رقم هنا يقارنك بنفسك أنت — لا ترتيب بين الطلاب ولا متوسط فصل.
            </p>
        </section>

        <!-- أهداف هذا الشهر: لا جدول أهداف في القاعدة بعد -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-goals-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-goals-h">أهداف هذا الشهر</h2></div>
            <div class="tq-empty" style="padding-block:var(--tq-space-xl)">
                <div class="tq-empty__art tq-pastel tq-pastel--peach" style="inline-size:72px;block-size:72px;display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                    <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('target', 32); ?></span>
                </div>
                <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)">تحديد الأهداف لم يفتح بعد</h3>
                <?php /* كان الزر «حدد هدفك الأول» يقود إلى الإعدادات، وليس فيها ولا في
                         القاعدة كلها جدول أهداف. فيضغطه الطالب ويقلب الأقسام الستة
                         يبحث عما ليس فيها، ثم يظن أنه أخطأ هو. والنقص يقال ولا يخبأ
                         خلف زر — وهو الأسلوب المتبع في بقية بطاقات هذه الشاشة. */ ?>
                <p class="tq-empty__text tq-caption">
                    حين تفتح الأهداف الشهرية ستحدد هدفا واحدا — ساعات دراسة أو دروسا تكملها —
                    ويظهر تقدمك نحوه هنا بشريط ورقم. وحتى ذلك الحين تجد قياسك الفعلي في
                    «نظرة عامة» أعلاه.
                </p>
            </div>
        </section>

        <!-- ملاحظات المعلم: لا جدول ملاحظات في القاعدة بعد -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-notes-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-notes-h">ملاحظات المعلم</h2></div>
            <div class="tq-empty" style="padding-block:var(--tq-space-xl)">
                <div class="tq-empty__art tq-pastel tq-pastel--lilac" style="inline-size:72px;block-size:72px;display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                    <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('chat', 32); ?></span>
                </div>
                <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)">لا ملاحظات بعد</h3>
                <p class="tq-empty__text tq-caption">
                    حين يكتب معلمك ملاحظة على أدائك تظهر هنا باسمه وتاريخها. يمكنك أن تسأله مباشرة الآن.
                </p>
                <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('student/messages'); ?>">راسل معلمك</a>
            </div>
        </section>

        <!-- سجل النشاط الأخير -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-act-h">
            <div class="tq-card__head">
                <h2 class="tq-card__title" id="tq-act-h">سجل النشاط الأخير</h2>
            </div>
            <?php if (!$tq_activity): ?>
                <p class="tq-caption" style="margin:0">
                    لا نشاط مسجل بعد. أول درس تفتحه وأول اختبار تسلمه يظهران هنا بوقتهما.
                </p>
            <?php else: ?>
                <ul>
                    <?php foreach ($tq_activity as $a): ?>
                        <li class="tq-noteline">
                            <span class="tq-icon-box tq-pastel--sky" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon($a['icon']); ?></span>
                            </span>
                            <span>
                                <span class="tq-caption" style="display:block;color:var(--tq-navy)"><?php echo tq_iso(html_escape($a['text'])); ?></span>
                                <span class="tq-micro"><?php echo html_escape(tq_since($a['ts'])); ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
