<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * اختباراتي — القادم والجاري والمنتهي.
 *
 * موصول بالقاعدة: الاختبار درس من نوع quiz في كورس مسجل (lesson + enrol)،
 * وأسئلته من question، ونتيجته من quiz_results:
 *   بلا نتيجة        → قادم
 *   نتيجة غير مسلمة → جار (الطالب بدأ ولم يسلم)
 *   نتيجة مسلمة     → منته بدرجته
 *
 * ومدة الاختبار من assessments.time_limit_sec للدرس نفسه — لا من رقم مكتوب
 * في الشيفرة. الاختبار الذي لا صف تقييم له تبقى مدته صفرا فيعرض بلا عداد.
 *
 * بلا مصدر بعد: موعد بدء مجدول للاختبار — لا عمود له في القاعدة، فلا يعرض
 * تاريخ مخترع ويكتفى بما هو معلوم.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'exams';
$tq_role  = 'student';
$tq_title = t('اختباراتي');
$tq_sub   = t('تابع اختباراتك القادمة والسابقة');
$tq_icon  = 'check-badge';

$tq_quizzes = tq_s_quizzes($tq_uid);

/**
 * مدة كل اختبار بالثواني — من assessments.time_limit_sec لدرس الاختبار.
 * الجدول يربط التقييم بالدرس (assessments.lesson_id = lesson.id) وقد يحمل
 * الدرس أكثر من نوع تقييم، فتؤخذ أصغر مدة معلنة لا أول ما يصادف.
 *
 * get_instance() صراحة: $this في العرض ليس المتحكم، وقاعدة البيانات
 * المحملة أثناء العرض لا تظهر فيه.
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

/* متوسط الدرجات من الاختبارات المصححة وحدها.
   وحين لا يوجد مصحح بعد فلا متوسط: الصفر رقم يقرؤه الطالب أداء له،
   وهو لم يصحح له شيء أصلا — فالشرطة أصدق منه. */
$tq_counted = 0;
$tq_avg     = null;
if ($tq_done) {
    $sum = 0;
    foreach ($tq_done as $q) {
        if (!empty($q['visible']) && $q['percent'] !== null) { $sum += $q['percent']; $tq_counted++; }
    }
    if ($tq_counted > 0) $tq_avg = (int) round($sum / $tq_counted);
}

/* علامات تقويم الاختبارات: أيام فيها اختبار منته فعلا في الشهر الجاري */
$tq_marks = [];
foreach ($tq_done as $q) {
    if ($q['ended_at'] <= 0 || date('Y-n', $q['ended_at']) !== date('Y-n')) continue;
    $tq_marks[(int) date('j', $q['ended_at'])] = 'done';
}
foreach ($tq_live as $q) {
    if ($q['started_at'] <= 0 || date('Y-n', $q['started_at']) !== date('Y-n')) continue;
    $tq_marks[(int) date('j', $q['started_at'])] = 'due';
}

/** تسمية الدرجة: النص يقول المستوى، واللون يؤكده ولا يحمله وحده. */
$tq_grade = function ($pct) {
    if ($pct >= 90) return ['mastered', t('ممتاز')];
    if ($pct >= 80) return ['mastered', t('جيد جدا')];
    if ($pct >= 70) return ['progress',  t('جيد')];
    if ($pct >= 50) return ['due',       t('مقبول')];
    return ['late', t('يحتاج مراجعة')];
};

/* وضع الامتحان — `F2.5`.
   موضعه هذه الشاشة لا الإعدادات: من يفتح «اختباراتي» قبل امتحانه بأسابيع
   هو من يريده، ودفنه في إعدادات لا يفتحها أحد يجعله ميزة لا توجد. */
$CI_ex = &get_instance();
$CI_ex->load->model('taqdar_learn_model', 'tq_learn');
$tq_exam = $CI_ex->tq_learn->exam_mode($tq_uid);

include 'portal_open.php';
?>

<?php /* ── وضع الامتحان ─────────────────────────────────────────────
        حالتان في بطاقة واحدة: سار فيقال ما بقي ويعرض الإيقاف، أو مطفأ
        فيعرض نموذج التفعيل. ولا شاشة ثالثة: الوضع مفتاح لا رحلة. */ ?>
<section class="tq-card tq-exam-mode<?php echo !empty($tq_exam['active']) ? ' is-on' : ''; ?>"
         style="margin-block-end:var(--tq-space-l)">
  <div class="tq-card__head">
    <h2 class="tq-card__title"><?php echo t('وضع الامتحان'); ?></h2>
    <?php if (!empty($tq_exam['active'])): ?>
      <span class="tq-badge tq-badge--due"><?php echo t('سار — بقي ____ يوما', array(tq_num((int) $tq_exam['days_left']))); ?></span>
    <?php endif; ?>
  </div>

  <?php if (!empty($tq_exam['active'])): ?>
    <p class="tq-body" style="margin-block-end:var(--tq-space-l)">
      <?php echo t('شاشاتك الآن خطة مراجعة: خطوتك اليومية مراجعة لا درس جديد، والإشعارات التسويقية موقوفة حتى ____. وإشعارات النتائج والحصص تصلك كما هي.', array(tq_num(html_escape((string) $tq_exam['to'])))); ?>
    </p>
    <form method="post" action="<?php echo base_url('student/exam-mode'); ?>">
      <?php echo tq_csrf(); ?>
      <input type="hidden" name="off" value="1">
      <button class="tq-btn tq-btn--secondary" type="submit"><?php echo t('أوقف وضع الامتحان'); ?></button>
    </form>

  <?php else: ?>
    <p class="tq-body" style="margin-block-end:var(--tq-space-l)">
      <?php echo t('حدد مدى امتحاناتك، وستتحول شاشاتك إلى خطة مراجعة: المراجعة قبل الدرس الجديد، وبلا إشعارات تسويقية تقطع عليك.'); ?>
    </p>
    <form method="post" action="<?php echo base_url('student/exam-mode'); ?>" class="tq-exam-mode__form">
      <?php echo tq_csrf(); ?>
      <label class="tq-field">
        <span class="tq-field__label"><?php echo t('من'); ?></span>
        <input class="tq-input" type="date" name="exam_from" required
               min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
      </label>
      <label class="tq-field">
        <span class="tq-field__label"><?php echo t('إلى'); ?></span>
        <input class="tq-input" type="date" name="exam_to" required
               min="<?php echo date('Y-m-d'); ?>"
               value="<?php echo date('Y-m-d', strtotime('+14 day')); ?>">
      </label>
      <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('فعل الوضع'); ?></button>
    </form>
  <?php endif; ?>
</section>

<style>
.tq-exam-mode.is-on { border-color: color-mix(in srgb, var(--tq-amber) 40%, transparent); }
.tq-exam-mode__form {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m); align-items: flex-end;
}
/* `align-items:flex-end` يحاذي **صندوق الهامش** لا الحد السفلي للحقل، و
   `.tq-field` يحمل `margin-block-end: var(--tq-space-l)` من `components.css`
   — فيقف الزر أخفض من الحقلين بمقدار ذلك الهامش، بلا أن يخطئ شيء. وصفره
   هنا كما يصفر في `.tq-prefrow__end .tq-field`. */
.tq-exam-mode__form .tq-field { min-inline-size: 160px; margin-block-end: 0; }
.tq-exam-mode__form .tq-btn   { flex: 0 0 auto; }
</style>

<div class="tq-cols">
    <div>

        <?php /* TQ-FILTERBAR — المكون الواحد. انظر `tq_filterbar()`. */ ?>
        <?php
        $tabs = [
            ''         => [t('الكل'),    count($tq_quizzes)],
            'upcoming' => [t('القادمة'), count($tq_upcoming)],
            'live'     => [t('الجارية'), count($tq_live)],
            'done'     => [t('المنتهية'), count($tq_done)],
        ];
        $tq_bar = [];
        foreach ($tabs as $key => $t) {
            $tq_bar[] = [
                'url'    => base_url('student/exams') . ($key !== '' ? '?state=' . $key : ''),
                'label'  => $t[0],
                'count'  => (int) $t[1],
                'active' => $f_state === $key,
            ];
        }
        echo tq_filterbar($tq_bar, t('تصفية الاختبارات بالحالة'));
        ?>

        <?php if (empty($tq_quizzes)): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'check-badge', 'sky',
                    t('لا اختبارات بعد'),
                    t('اختبارات كورساتك تظهر هنا: القادم بموعده ودرجته، والجاري بعداد وقته، والمنتهي بنتيجته وتقييمه.'),
                    t('تصفح دروسك'),
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
                    <h2><?php echo tq_icon('clock', 18); ?> <?php echo t('الاختبارات القادمة'); ?></h2>
                    <?php if ($tq_upcoming): ?>
                        <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_upcoming) . TQ_PDI; ?></span>
                    <?php endif; ?>
                </div>

                <?php if (empty($tq_upcoming)): ?>
                    <div class="tq-card">
                        <?php echo tq_s_empty(
                            'calendar', 'lilac',
                            t('لا اختبار قادم'),
                            t('الاختبار الذي لم تبدأه بعد يظهر هنا بمادته وعدد درجاته وزر بدئه.'),
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
                                    <?php echo html_escape($q['level'] !== '' ? tq_s_level($q['level']) : $q['course']); ?>
                                </p>

                                <div class="tq-s-meta" style="margin-block-end:var(--tq-space-m)">
                                    <?php /* «٥ درجة» و«٥ سؤالا» رقم واحد بتسميتين — الدرجة في هذا
                                             النموذج هي عدد الأسئلة نفسه. فيقال مرة واحدة. */ ?>
                                    <span><?php echo tq_icon('help', 16); ?><?php echo tq_iso($q['marks'] . t(' سؤالا، والدرجة من ') . $q['marks']); ?></span>
                                    <?php if (!empty($tq_limits[$q['id']])): ?>
                                        <span><?php echo tq_icon('clock', 16); ?><?php echo tq_s_minutes((int) round($tq_limits[$q['id']] / 60)); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php
                                /* لا موعد بدء في القاعدة، فلا يعرض «بعد يومين» مخترعا.
                                   الاختبار متاح متى شاء الطالب حتى يضاف جدول مواعيد. */
                                echo tq_badge('progress', t('متاح الآن'));
                                ?>

                                <a class="tq-btn tq-btn--secondary tq-btn--block" style="margin-block-start:var(--tq-space-m)"
                                   href="<?php echo tq_s_lesson_url($q['course_id'], $q['id']); ?>"><?php echo t('ابدأ الاختبار'); ?></a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- الاختبارات الجارية.
             القسم يطوى في «الكل» حين لا اختبار جاريا — عنوان فوق فراغ ضجيج.
             أما تبويب «الجارية» نفسه فلا يطوى: الطالب طلبه صراحة، وصفحة
             لا تحمل إلا شريط تبويبات تقول له إن الصفحة معطوبة. -->
        <?php if ($tq_quizzes && ($tq_live || $f_state === 'live')): ?>
            <section class="tq-section">
                <div class="tq-sectionhead">
                    <h2><span class="tq-s-dot tq-s-dot--live" aria-hidden="true"></span> <?php echo t('الاختبارات الجارية'); ?></h2>
                </div>

                <?php if (empty($tq_live)): ?>
                    <div class="tq-card">
                        <?php echo tq_s_empty(
                            'clock', 'peach',
                            t('لا اختبار جار الآن'),
                            t('الاختبار الذي تبدأه ولا تسلمه يظهر هنا بعداد وقته وزر يعيدك إليه من حيث توقفت.'),
                            '', '', true
                        ); ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($tq_live as $q): ?>
                    <?php
                    /**
                     * العداد ليس عنصر هذه الشاشة وحدها: ما دام اختبار مفتوحا
                     * يجب أن يبقى «الوقت المتبقي» ظاهرا في كل شاشة ينتقل إليها
                     * الطالب — شريطا ثابتا في الترويسة — حتى لا ينتهي وقته
                     * وهو يتصفح صفحة أخرى ظانا أن الاختبار متوقف.
                     *
                     * والمدة من القاعدة: assessments.time_limit_sec لهذا الدرس.
                     * فإن لم يكن للاختبار صف تقييم بقيت صفرا، ويعرض وقت البدء
                     * الحقيقي وحده بدل عداد مخترع.
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
                                    <span><?php echo tq_icon('play', 16); ?><?php echo t('بدأ'); ?> <?php echo tq_since($q['started_at']); ?></span>
                                <?php endif; ?>
                                <span><?php echo tq_icon('award', 16); ?><?php echo tq_iso($q['marks'] . t(' درجة')); ?></span>
                            </div>

                            <a class="tq-btn tq-btn--primary" href="<?php echo tq_s_lesson_url($q['course_id'], $q['id']); ?>">
                                <?php echo t('متابعة الاختبار'); ?>
                            </a>
                        </div>

                        <div style="margin-block-start:var(--tq-space-l)">
                            <?php $left = $duration_min > 0 ? max(0, $q['started_at'] + $duration_min * 60 - time()) : 0; ?>
                            <?php if ($duration_min > 0 && $left > 0): ?>
                                <div class="tq-row tq-row--between" style="margin-block-end:var(--tq-space-s)">
                                    <span class="tq-caption"><?php echo t('الوقت المتبقي'); ?></span>
                                    <?php echo tq_num(tq_s_clock($left)); ?>
                                </div>
                                <div class="tq-s-timebar">
                                    <div class="tq-s-timebar__fill"
                                         style="inline-size:<?php echo (int) round($left * 100 / ($duration_min * 60)); ?>%"></div>
                                </div>
                            <?php elseif ($duration_min > 0): ?>
                                <?php /* عداد على «00:00» وشريط فارغ لا يقولان شيئا: المحاولة
                                         مضى وقتها. يقال ذلك بلفظه، ويبقى الرابط ليحسم المشغل
                                         أمرها — فالحسم شأنه لا شأن هذه الشاشة. */ ?>
                                <div class="tq-row" style="gap:var(--tq-space-s)">
                                    <?php echo tq_badge('late', t('انتهى وقت هذه المحاولة')); ?>
                                    <span class="tq-micro">
                                        <?php echo t('مدتها ____، وقد مضت.', array(tq_s_minutes($duration_min))); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <p class="tq-micro" style="margin:0">
                                    <?php echo t('الوقت المتبقي يظهر هنا وفي كل شاشة بمجرد تحديد مدة الاختبار.'); ?>
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
                    <h2><?php echo tq_icon('check', 18); ?> <?php echo t('الاختبارات المنتهية'); ?></h2>
                    <?php if ($tq_done): ?>
                        <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_done) . TQ_PDI; ?></span>
                    <?php endif; ?>
                </div>

                <div class="tq-card">
                    <?php if (empty($tq_done)): ?>
                        <?php echo tq_s_empty(
                            'award', 'mint',
                            t('لا نتائج بعد'),
                            t('كل اختبار تسلمه يسجل هنا بتاريخه ودرجته وتقييمه، ويمكنك فتح إجاباتك ومراجعتها.'),
                            '', '', true
                        ); ?>
                    <?php else: ?>
                        <table class="tq-table">
                            <caption class="tq-sr"><?php echo t('نتائج اختباراتك المنتهية'); ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo t('الاختبار'); ?></th>
                                    <th scope="col"><?php echo t('المادة'); ?></th>
                                    <th scope="col"><?php echo t('التاريخ'); ?></th>
                                    <th scope="col"><?php echo t('الدرجة'); ?></th>
                                    <th scope="col"><?php echo t('الحالة'); ?></th>
                                    <th scope="col"><?php echo t('الإجراء'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tq_done as $q): ?>
                                    <?php
                                    /* درجة لم يعتمدها المعلم بعد ليست درجة ضعيفة.
                                       وتمرير `null` على أنها `-1` كان يصبغ كل تسليم
                                       ينتظر التصحيح بالأحمر ويقول لصاحبه «يحتاج
                                       مراجعة» — حكم على عمل لم يقرأه أحد بعد. */
                                    if (empty($q['visible'])) {
                                        $g = (($q['grade_state'] ?? '') === 'pending_approval')
                                            ? array('due', t('بانتظار التصحيح'))
                                            : array('idle', t('بلا درجة بعد'));
                                    } elseif ($q['percent'] === null) {
                                        $g = array('idle', t('بلا درجة بعد'));
                                    } else {
                                        $g = $tq_grade($q['percent']);
                                    }
                                    ?>
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
                                                <span class="tq-caption"><?php echo t('بانتظار اعتماد المعلم'); ?></span>
                                            <?php else: ?>
                                                <span class="tq-caption">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="الحالة"><?php echo tq_badge($g[0], $g[1]); ?></td>
                                        <td data-label="الإجراء">
                                            <a class="tq-btn tq-btn--secondary tq-btn--sm"
                                               href="<?php echo tq_s_lesson_url($q['course_id'], $q['id']); ?>"><?php echo t('عرض النتيجة'); ?></a>
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
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('نظرة عامة'); ?></h2></div>
            <?php if (empty($tq_quizzes)): ?>
                <?php echo tq_s_empty(
                    'chart', 'sky',
                    t('لا أرقام بعد'),
                    t('عدد اختباراتك القادمة والجارية والمكتملة ومتوسط درجاتك يظهر هنا.'),
                    '', '', true
                ); ?>
            <?php else: ?>
                <div class="tq-s-2x2">
                    <?php
                    /* TQ-PASTEL-NOISE — والنبرة تتبع الحال لا البطاقة.
                       `peach` في هذه اللوحة تعني «انتبه»، و«صفر اختبارات
                       جارية» لا شيء فيه ينتبه له — بل هو الحال الهادئة.
                       ولون تنبيه على صفر يستهلك النبرة، فلا تعود تعني شيئا
                       في اليوم الذي يجري فيه اختبار فعلا. */
                    echo tq_s_stat(tq_num(count($tq_upcoming)), t('اختبارات قادمة'), 'calendar', 'sky');
                    echo tq_s_stat(tq_num(count($tq_live)),     t('اختبار جار'),    'clock',
                                   count($tq_live) > 0 ? 'peach' : 'sand');
                    echo tq_s_stat(tq_num(count($tq_done)),     t('اختبارات مكتملة'), 'check',
                                   count($tq_done) > 0 ? 'mint' : 'sand');
                    echo tq_s_stat(
                        $tq_avg === null ? '<span class="tq-muted">—</span>' : tq_num($tq_avg . '%'),
                        t('متوسط الدرجات'), 'award', 'lilac',
                        $tq_avg === null ? t('يظهر بعد اعتماد أول درجة') : ''
                    );
                    ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('الاختبارات القادمة'); ?></h2>
                <?php if ($tq_upcoming): ?>
                    <a class="tq-caption" href="<?php echo base_url('student/exams?state=upcoming'); ?>"><?php echo t('عرض الكل'); ?></a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_upcoming)): ?>
                <?php echo tq_s_empty(
                    'calendar', 'lilac',
                    t('لا اختبار قادم'),
                    t('أقرب ثلاثة اختبارات تظهر هنا لتعرف ما ينتظرك.'),
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
                            <?php echo tq_badge('progress', t('متاح')); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('تقويم الاختبارات'); ?></h2></div>
            <?php echo tq_s_calendar(time(), $tq_marks); ?>
            <?php echo tq_s_key([
                'due'  => t('اختبار جار'),
                'done' => t('اختبار منته'),
                'idle' => t('لا اختبار'),
            ]); ?>
        </section>

    </aside>
</div>


<?php /* ── نتائج اختبارات الدروس ─────────────────────────────────────
         شاشة الاختبارات كانت تقرأ `quiz_results` الموروث وحده — وهو
         نصف الحقيقة: اختبار الدرس، وهو ما يفتح الدرس التالي، يكتب في
         `attempts`. فيقرأ الطالب شاشة «اختباراتي» ولا يجد فيها الاختبار
         الذي وقف عنده فعلا. */ ?>
<?php
$CI = get_instance();
$CI->load->model('taqdar_quiz_model', 'tq_quiz');
$r_rows  = $CI->tq_quiz->student_results((int) $this->session->userdata('user_id'));
$r_skin  = 'tq';
$r_who   = 'student';
$r_title = t('نتائج اختبارات الدروس');
$r_empty = t('لم تؤد اختبار درس بعد. اختبار كل درس يفتح بعد إتمام مشاهدته.');
?>
<div class="tq-section">
    <?php include APPPATH . 'views/components/tq_quiz_results.php'; ?>
</div>

<?php include 'portal_close.php'; ?>
