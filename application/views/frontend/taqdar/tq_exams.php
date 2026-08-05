<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * اختباراتي — القادم والجاري والمنتهي.
 *
 * موصول بالقاعدة: الاختبار درسٌ من نوع quiz في كورس مسجَّل (lesson + enrol)،
 * وأسئلته من question، ونتيجته من quiz_results:
 *   بلا نتيجة        → قادم
 *   نتيجة غير مُسلَّمة → جارٍ (الطالب بدأ ولم يُسلّم)
 *   نتيجة مُسلَّمة     → منتهٍ بدرجته
 *
 * ومدّة الاختبار من assessments.time_limit_sec للدرس نفسه — لا من رقم مكتوب
 * في الشيفرة. الاختبار الذي لا صفّ تقييم له تبقى مدّته صفرًا فيُعرض بلا عدّاد.
 *
 * بلا مصدر بعد: موعد بدء مجدول للاختبار — لا عمود له في القاعدة، فلا يُعرض
 * تاريخ مخترَع ويُكتفى بما هو معلوم.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'exams';
$tq_role  = 'student';
$tq_title = 'اختباراتي';
$tq_sub   = 'تابع اختباراتك القادمة والسابقة';
$tq_icon  = 'check-badge';

$tq_quizzes = tq_s_quizzes($tq_uid);

/**
 * مدّة كل اختبار بالثواني — من assessments.time_limit_sec لدرس الاختبار.
 * الجدول يربط التقييم بالدرس (assessments.lesson_id = lesson.id) وقد يحمل
 * الدرس أكثر من نوع تقييم، فتُؤخذ أصغر مدّة معلَنة لا أوّل ما يصادف.
 *
 * get_instance() صراحةً: $this في العرض ليس المتحكّم، وقاعدة البيانات
 * المحمَّلة أثناء العرض لا تظهر فيه.
 */
$tq_limits = [];
if ($tq_quizzes) {
    $CI  = get_instance();
    $ids = array_map(static function ($q) { return (int) $q['id']; }, $tq_quizzes);
    foreach ($CI->db->select('lesson_id, MIN(time_limit_sec) AS secs', false)
                    ->from('assessments')
                    ->where_in('lesson_id', $ids)
                    ->where('time_limit_sec >', 0)
                    ->group_by('lesson_id')
                    ->get()->result_array() as $r) {
        $tq_limits[(int) $r['lesson_id']] = (int) $r['secs'];
    }
}

$tq_upcoming = [];
$tq_live     = [];
$tq_done     = [];
foreach ($tq_quizzes as $q) {
    if ($q['state'] === 'upcoming')   $tq_upcoming[] = $q;
    elseif ($q['state'] === 'live')   $tq_live[]     = $q;
    else                              $tq_done[]     = $q;
}

$f_state = (string) $this->input->get('state', true);
if (!in_array($f_state, ['upcoming', 'live', 'done'], true)) $f_state = '';
$show = function ($key) use ($f_state) { return $f_state === '' || $f_state === $key; };

/* متوسط الدرجات من الاختبارات المصحَّحة وحدها */
$tq_avg = 0;
if ($tq_done) {
    $sum = 0;
    // المتوسّط يُحسب من المعتمَد وحده: إدخالُ المحجوب فيه يُنتج رقمًا
    // لا يعرف الطالب من أين جاء، ويتغيّر تحت قدميه عند كل اعتماد
    $tq_counted = 0;
    foreach ($tq_done as $q) {
        if (!empty($q['visible']) && $q['percent'] !== null) { $sum += $q['percent']; $tq_counted++; }
    }
    $tq_avg = (int) round($sum / max(1, $tq_counted));
}

/* علامات تقويم الاختبارات: أيام فيها اختبار منتهٍ فعلًا في الشهر الجاري */
$tq_marks = [];
foreach ($tq_done as $q) {
    if ($q['ended_at'] <= 0 || date('Y-n', $q['ended_at']) !== date('Y-n')) continue;
    $tq_marks[(int) date('j', $q['ended_at'])] = 'done';
}
foreach ($tq_live as $q) {
    if ($q['started_at'] <= 0 || date('Y-n', $q['started_at']) !== date('Y-n')) continue;
    $tq_marks[(int) date('j', $q['started_at'])] = 'due';
}

/** تسمية الدرجة: النصّ يقول المستوى، واللون يؤكّده ولا يحمله وحده. */
$tq_grade = function ($pct) {
    if ($pct >= 90) return ['mastered', 'ممتاز'];
    if ($pct >= 80) return ['mastered', 'جيد جدًا'];
    if ($pct >= 70) return ['progress',  'جيد'];
    if ($pct >= 50) return ['due',       'مقبول'];
    return ['late', 'يحتاج مراجعة'];
};

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <nav class="tq-tabs tq-s-tabs" aria-label="تصفية الاختبارات بالحالة">
            <?php
            $tabs = [
                ''         => ['الكل',    count($tq_quizzes)],
                'upcoming' => ['القادمة', count($tq_upcoming)],
                'live'     => ['الجارية', count($tq_live)],
                'done'     => ['المنتهية', count($tq_done)],
            ];
            foreach ($tabs as $key => $t):
                $href = base_url('student/exams') . ($key !== '' ? '?state=' . $key : '');
                ?>
                <a class="tq-tab" href="<?php echo $href; ?>" <?php echo $f_state === $key ? 'aria-current="page"' : ''; ?>>
                    <?php echo html_escape($t[0]); ?>
                    <span class="tq-tab__n"><?php echo TQ_LRI . (int) $t[1] . TQ_PDI; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($tq_quizzes)): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'check-badge', 'sky',
                    'لا اختبارات بعد',
                    'اختبارات كورساتك تظهر هنا: القادم بموعده ودرجته، والجاري بعدّاد وقته، والمنتهي بنتيجته وتقييمه.',
                    'تصفّح دروسك',
                    base_url('student/lessons'),
                    false,
                    'primary'
                ); ?>
            </div>
        <?php endif; ?>

        <!-- الاختبارات القادمة -->
        <?php if ($show('upcoming') && $tq_quizzes): ?>
            <section class="tq-section">
                <div class="tq-sectionhead">
                    <h2><?php echo tq_icon('clock', 18); ?> الاختبارات القادمة</h2>
                    <?php if ($tq_upcoming): ?>
                        <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_upcoming) . TQ_PDI; ?></span>
                    <?php endif; ?>
                </div>

                <?php if (empty($tq_upcoming)): ?>
                    <div class="tq-card">
                        <?php echo tq_s_empty(
                            'calendar', 'lilac',
                            'لا اختبار قادم',
                            'الاختبار الذي لم تبدأه بعد يظهر هنا بمادّته وعدد درجاته وزرّ بدئه.',
                            '', '', true
                        ); ?>
                    </div>
                <?php else: ?>
                    <div class="tq-s-grid3 tq-stagger">
                        <?php foreach (array_slice($tq_upcoming, 0, 3) as $q): ?>
                            <article class="tq-card">
                                <div class="tq-row tq-row--between" style="align-items:flex-start">
                                    <span class="tq-icon-box tq-pastel tq-pastel--<?php echo tq_pastel($q['index']); ?>" aria-hidden="true">
                                        <span class="tq-pastel__icon"><?php echo tq_icon('check-badge'); ?></span>
                                    </span>
                                    <span class="tq-micro"><?php echo html_escape($q['subject']); ?></span>
                                </div>

                                <h3 class="tq-s-course__title" style="margin-block-start:var(--tq-space-m)">
                                    <?php echo html_escape($q['title']); ?>
                                </h3>
                                <p class="tq-micro" style="margin:0 0 var(--tq-space-m)">
                                    <?php echo html_escape($q['level'] !== '' ? $q['level'] : $q['course']); ?>
                                </p>

                                <div class="tq-s-meta" style="margin-block-end:var(--tq-space-m)">
                                    <span><?php echo tq_icon('award', 16); ?><?php echo tq_iso($q['marks'] . ' درجة'); ?></span>
                                    <span><?php echo tq_icon('help', 16); ?><?php echo tq_iso($q['marks'] . ' سؤالًا'); ?></span>
                                    <?php if (!empty($tq_limits[$q['id']])): ?>
                                        <span><?php echo tq_icon('clock', 16); ?><?php echo tq_s_minutes((int) round($tq_limits[$q['id']] / 60)); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php
                                /* لا موعد بدء في القاعدة، فلا يُعرض «بعد يومين» مخترَعًا.
                                   الاختبار متاح متى شاء الطالب حتى يُضاف جدول مواعيد. */
                                echo tq_badge('progress', 'متاح الآن');
                                ?>

                                <a class="tq-btn tq-btn--secondary tq-btn--block" style="margin-block-start:var(--tq-space-m)"
                                   href="<?php echo tq_s_lesson_url($q['course_id'], $q['id']); ?>">ابدأ الاختبار</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- الاختبارات الجارية -->
        <?php if ($show('live') && $tq_live): ?>
            <section class="tq-section">
                <div class="tq-sectionhead">
                    <h2><span class="tq-s-dot tq-s-dot--live" aria-hidden="true"></span> الاختبارات الجارية</h2>
                </div>

                <?php foreach ($tq_live as $q): ?>
                    <?php
                    /**
                     * العدّاد ليس عنصر هذه الشاشة وحدها: ما دام اختبارٌ مفتوحًا
                     * يجب أن يبقى «الوقت المتبقي» ظاهرًا في كل شاشة ينتقل إليها
                     * الطالب — شريطًا ثابتًا في الترويسة — حتى لا ينتهي وقته
                     * وهو يتصفّح صفحة أخرى ظانًّا أن الاختبار متوقّف.
                     *
                     * والمدّة من القاعدة: assessments.time_limit_sec لهذا الدرس.
                     * فإن لم يكن للاختبار صفّ تقييم بقيت صفرًا، ويُعرض وقت البدء
                     * الحقيقي وحده بدل عدّاد مخترَع.
                     */
                    $duration_min = (int) round(($tq_limits[$q['id']] ?? 0) / 60);
                    ?>
                    <article class="tq-card tq-card--float">
                        <div class="tq-row tq-row--between" style="flex-wrap:wrap;gap:var(--tq-space-l)">
                            <div class="tq-row" style="gap:var(--tq-space-m)">
                                <span class="tq-icon-box tq-pastel tq-pastel--peach" aria-hidden="true">
                                    <span class="tq-pastel__icon"><?php echo tq_icon('clock'); ?></span>
                                </span>
                                <div>
                                    <h3 class="tq-s-row__title"><?php echo html_escape($q['title']); ?></h3>
                                    <p class="tq-micro" style="margin:0"><?php echo html_escape($q['subject']); ?></p>
                                </div>
                            </div>

                            <div class="tq-s-meta">
                                <?php if ($q['started_at']): ?>
                                    <span><?php echo tq_icon('play', 16); ?>بدأ <?php echo tq_since($q['started_at']); ?></span>
                                <?php endif; ?>
                                <span><?php echo tq_icon('award', 16); ?><?php echo tq_iso($q['marks'] . ' درجة'); ?></span>
                            </div>

                            <a class="tq-btn tq-btn--primary" href="<?php echo tq_s_lesson_url($q['course_id'], $q['id']); ?>">
                                متابعة الاختبار
                            </a>
                        </div>

                        <div style="margin-block-start:var(--tq-space-l)">
                            <?php if ($duration_min > 0): ?>
                                <div class="tq-row tq-row--between" style="margin-block-end:var(--tq-space-s)">
                                    <span class="tq-caption">الوقت المتبقي</span>
                                    <?php
                                    $left = max(0, $q['started_at'] + $duration_min * 60 - time());
                                    echo tq_num(tq_s_clock($left));
                                    ?>
                                </div>
                                <div class="tq-s-timebar">
                                    <div class="tq-s-timebar__fill"
                                         style="inline-size:<?php echo (int) round($left * 100 / ($duration_min * 60)); ?>%"></div>
                                </div>
                            <?php else: ?>
                                <p class="tq-micro" style="margin:0">
                                    الوقت المتبقي يظهر هنا وفي كل شاشة بمجرّد تحديد مدّة الاختبار.
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <!-- الاختبارات المنتهية -->
        <?php if ($show('done') && $tq_quizzes): ?>
            <section class="tq-section">
                <div class="tq-sectionhead">
                    <h2><?php echo tq_icon('check', 18); ?> الاختبارات المنتهية</h2>
                    <?php if ($tq_done): ?>
                        <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_done) . TQ_PDI; ?></span>
                    <?php endif; ?>
                </div>

                <div class="tq-card">
                    <?php if (empty($tq_done)): ?>
                        <?php echo tq_s_empty(
                            'award', 'mint',
                            'لا نتائج بعد',
                            'كل اختبار تُسلّمه يُسجَّل هنا بتاريخه ودرجته وتقييمه، ويمكنك فتح إجاباتك ومراجعتها.',
                            '', '', true
                        ); ?>
                    <?php else: ?>
                        <table class="tq-table">
                            <caption class="tq-sr">نتائج اختباراتك المنتهية</caption>
                            <thead>
                                <tr>
                                    <th scope="col">الاختبار</th>
                                    <th scope="col">المادة</th>
                                    <th scope="col">التاريخ</th>
                                    <th scope="col">الدرجة</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col">الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tq_done as $q): $g = $tq_grade($q['percent'] === null ? -1 : $q['percent']); ?>
                                    <tr>
                                        <td data-label="الاختبار">
                                            <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($q['title']); ?></span>
                                        </td>
                                        <td data-label="المادة"><?php echo html_escape($q['subject']); ?></td>
                                        <td data-label="التاريخ"><?php echo $q['ended_at'] ? tq_s_date($q['ended_at']) : '<span class="tq-muted">—</span>'; ?></td>
                                        <td data-label="الدرجة">
                                            <?php if (!empty($q['visible'])): ?>
                                                <?php echo tq_num(((float) $q['obtained'] == (int) $q['obtained'] ? (int) $q['obtained'] : $q['obtained']) . ' / ' . $q['marks'], 'tq-num--sm'); ?>
                                            <?php elseif (($q['grade_state'] ?? '') === 'pending_approval'): ?>
                                                <span class="tq-caption">بانتظار اعتماد المعلّم</span>
                                            <?php else: ?>
                                                <span class="tq-caption">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="الحالة"><?php echo tq_badge($g[0], $g[1]); ?></td>
                                        <td data-label="الإجراء">
                                            <a class="tq-btn tq-btn--secondary tq-btn--sm"
                                               href="<?php echo tq_s_lesson_url($q['course_id'], $q['id']); ?>">عرض النتيجة</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

    </div>

    <aside class="tq-aside">

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title">نظرة عامة</h2></div>
            <?php if (empty($tq_quizzes)): ?>
                <?php echo tq_s_empty(
                    'chart', 'sky',
                    'لا أرقام بعد',
                    'عدد اختباراتك القادمة والجارية والمكتملة ومتوسط درجاتك يظهر هنا.',
                    '', '', true
                ); ?>
            <?php else: ?>
                <div class="tq-s-2x2">
                    <?php
                    echo tq_s_stat(tq_num(count($tq_upcoming)), 'اختبارات قادمة', 'calendar', 'sky');
                    echo tq_s_stat(tq_num(count($tq_live)),     'اختبار جارٍ',    'clock',    'peach');
                    echo tq_s_stat(tq_num(count($tq_done)),     'اختبارات مكتملة', 'check',   'mint');
                    echo tq_s_stat(tq_num($tq_avg . '%'),       'متوسط الدرجات',  'award',    'lilac');
                    ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title">الاختبارات القادمة</h2>
                <?php if ($tq_upcoming): ?>
                    <a class="tq-caption" href="<?php echo base_url('taqdar/exams?state=upcoming'); ?>">عرض الكل</a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_upcoming)): ?>
                <?php echo tq_s_empty(
                    'calendar', 'lilac',
                    'لا اختبار قادم',
                    'أقرب ثلاثة اختبارات تظهر هنا لتعرف ما ينتظرك.',
                    '', '', true
                ); ?>
            <?php else: ?>
                <ul class="tq-s-list">
                    <?php foreach (array_slice($tq_upcoming, 0, 3) as $q): ?>
                        <li class="tq-s-item">
                            <span class="tq-icon-box tq-pastel tq-pastel--sky" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon('check-badge'); ?></span>
                            </span>
                            <span class="tq-s-item__body">
                                <span class="tq-s-item__s tq-s-trunc"><?php echo html_escape($q['subject']); ?></span>
                                <span class="tq-s-item__t tq-s-trunc"><?php echo html_escape($q['title']); ?></span>
                            </span>
                            <?php echo tq_badge('progress', 'متاح'); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title">تقويم الاختبارات</h2></div>
            <?php echo tq_s_calendar(time(), $tq_marks); ?>
            <?php echo tq_s_key([
                'due'  => 'اختبار جارٍ',
                'done' => 'اختبار منتهٍ',
                'idle' => 'لا اختبار',
            ]); ?>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
