<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * مهامي — الواجبات والمهامّ المطلوبة من الطالب.
 *
 * المهامّ مجمَّعة بالحالة لا مرتَّبة بالتاريخ وحده: الطالب يسأل «ما المتأخّر؟»
 * قبل أن يسأل «ما التالي؟»، فالمجموعة تجيب قبل الصفّ.
 *
 * مصدر البيانات: assessments.type = 'homework' — الواجب صفّ تقييم مرتبط بدرس
 * (assessments.lesson_id) في كورس مسجَّل (enrol)، وحالته من attempts للطالب:
 *   لا محاولة              → لم تبدأ
 *   محاولة بدأت ولم تُسلَّم → قيد التنفيذ
 *   محاولة مُسلَّمة         → مكتملة بدرجتها من attempts.score
 * وعدد بنود الواجب من question (question.quiz_id = معرّف الدرس)،
 * ومدّته من assessments.time_limit_sec، ودرجة نجاحه من assessments.pass_mark.
 *
 * بلا مصدر بعد: تاريخ استحقاق للواجب — لا عمود due في assessments، فلا يُعرض
 * موعد مخترَع ولا حالة «متأخّر»، وتقتصر التواريخ على ما سجّلته المحاولة فعلًا.
 * وكذلك درجة الصعوبة: لا عمود لها، فلا شارة صعوبة تُفترض.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'tasks';
$tq_role  = 'student';
$tq_title = 'مهامي';
$tq_sub   = 'تابع مهامك وحقّق أفضل النتائج';
$tq_icon  = 'clipboard';

/**
 * شكل المهمّة الواحدة الذي يقرأه العارض:
 *   id · title · subject · stage · at (طابع زمني مسجَّل أو صفر) · minutes
 *   points · pass · type · score · max (للمكتملة) · href
 *
 * والمجموعات ثلاث بحالات القاعدة نفسها — لا حالة «متأخّر» لأن لا موعد
 * استحقاق يُقاس عليه التأخّر.
 */
$tq_groups = [
    'todo'     => ['label' => 'لم تبدأ',     'dot' => 'idle', 'badge' => 'idle',     'items' => []],
    'progress' => ['label' => 'قيد التنفيذ', 'dot' => 'due',  'badge' => 'progress', 'items' => []],
    'done'     => ['label' => 'مكتملة',      'dot' => 'done', 'badge' => 'mastered', 'items' => []],
];

/* ---- الواجبات من القاعدة ---------------------------------------------
   get_instance() صراحةً: $this في العرض ليس المتحكّم. */
if ($tq_uid > 0) {
    $CI = get_instance();

    $tq_hw = $CI->db
        ->select('a.id AS assessment_id, a.time_limit_sec, a.pass_mark,'
               . ' l.id AS lesson_id, l.title, l.course_id,'
               . ' c.title AS course_title, c.level, c.category_id')
        ->from('assessments a')
        ->join('lesson l', 'l.id = a.lesson_id', 'inner')
        ->join('course c', 'c.id = l.course_id', 'inner')
        ->join('enrol e', 'e.course_id = c.id', 'inner')
        ->where('e.user_id', $tq_uid)
        ->where('a.type', 'homework')
        ->order_by('c.id', 'ASC')
        ->order_by('l.order', 'ASC')
        ->get()->result_array();

    if ($tq_hw) {
        $a_ids = array_map(static function ($r) { return (int) $r['assessment_id']; }, $tq_hw);
        $l_ids = array_map(static function ($r) { return (int) $r['lesson_id']; }, $tq_hw);

        // آخر محاولة لكل تقييم — الترتيب تصاعدي فالأحدث يغلب
        $tq_att = [];
        foreach ($CI->db->select('assessment_id, score, passed, started_at, submitted_at')
                        ->from('attempts')
                        ->where('student_id', $tq_uid)
                        ->where_in('assessment_id', $a_ids)
                        ->order_by('attempt_no', 'ASC')
                        ->get()->result_array() as $r) {
            $tq_att[(int) $r['assessment_id']] = $r;
        }

        // عدد بنود الواجب — الأسئلة معلَّقة بمعرّف الدرس
        $tq_items_n = [];
        foreach ($CI->db->select('quiz_id, COUNT(*) AS n')
                        ->from('question')->where_in('quiz_id', $l_ids)
                        ->group_by('quiz_id')->get()->result_array() as $r) {
            $tq_items_n[(int) $r['quiz_id']] = (int) $r['n'];
        }

        // اسم المادة من التصنيف — والعنوان بديلًا حين لا تصنيف
        $tq_cat_names = [];
        $cat_ids = array_values(array_unique(array_filter(array_map(static function ($r) {
            return (int) $r['category_id'];
        }, $tq_hw))));
        if ($cat_ids) {
            foreach ($CI->db->select('id, name')->from('category')
                            ->where_in('id', $cat_ids)->get()->result_array() as $r) {
                $tq_cat_names[(int) $r['id']] = $r['name'];
            }
        }

        foreach ($tq_hw as $r) {
            $lid = (int) $r['lesson_id'];
            $att = $tq_att[(int) $r['assessment_id']] ?? null;
            $max = $tq_items_n[$lid] ?? 0;

            $submitted = ($att && !empty($att['submitted_at'])) ? (int) strtotime($att['submitted_at']) : 0;
            $started   = ($att && !empty($att['started_at']))   ? (int) strtotime($att['started_at'])   : 0;

            $key = $submitted ? 'done' : ($started ? 'progress' : 'todo');

            $item = [
                'id'      => $lid,
                'title'   => (string) $r['title'],
                'subject' => $tq_cat_names[(int) $r['category_id']] ?? (string) $r['course_title'],
                'stage'   => (string) $r['level'],
                'at'      => $submitted ?: $started,
                'minutes' => (int) round(((int) $r['time_limit_sec']) / 60),
                'points'  => $max,
                'pass'    => (int) $r['pass_mark'],
                'type'    => 'homework',
                'href'    => base_url('student/lesson/' . (int) $r['course_id'] . '/' . $lid),
            ];
            if ($key === 'done') {
                $item['score'] = $att['score'] === null ? null : (int) $att['score'];
                $item['max']   = $max;
                $item['pass_ok'] = $att['passed'] === null ? null : ((int) $att['passed'] === 1);
            }
            $tq_groups[$key]['items'][] = $item;
        }
    }
}

$tq_total = 0;
foreach ($tq_groups as $g) $tq_total += count($g['items']);

$f_state = (string) $this->input->get('state', true);
if (!isset($tq_groups[$f_state])) $f_state = '';

$tq_marks = [];   // علامات التقويم — لا يُعلَّم يوم بلا مهمّة حقيقية فيه
foreach ($tq_groups as $key => $g) {
    foreach ($g['items'] as $t) {
        if (empty($t['at'])) continue;
        if (date('Y-n', $t['at']) !== date('Y-n')) continue;
        $tq_marks[(int) date('j', $t['at'])] = $key === 'done' ? 'done' : 'due';
    }
}

/* ما ينتظر الطالب: ما لم يُسلَّم بعد — الجاري أوّلًا لأنه بدأ فعلًا */
$tq_pending = [];
foreach (['progress', 'todo'] as $key) {
    foreach ($tq_groups[$key]['items'] as $t) $tq_pending[] = $t;
}
$tq_pending = array_slice($tq_pending, 0, 3);

$tq_type_icon = [
    'homework' => ['clipboard', 'peach'],
    'report'   => ['file',      'rose'],
    'reading'  => ['book',      'mint'],
    'project'  => ['target',    'lilac'],
];

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <nav class="tq-tabs tq-s-tabs" aria-label="تصفية المهامّ بالحالة">
            <?php
            $tabs = ['' => ['الكل', $tq_total]];
            foreach ($tq_groups as $k => $g) $tabs[$k] = [$g['label'], count($g['items'])];
            foreach ($tabs as $key => $t):
                $href = base_url('student/tasks') . ($key !== '' ? '?state=' . $key : '');
                ?>
                <a class="tq-tab" href="<?php echo $href; ?>" <?php echo $f_state === $key ? 'aria-current="page"' : ''; ?>>
                    <?php echo html_escape($t[0]); ?>
                    <span class="tq-tab__n"><?php echo TQ_LRI . (int) $t[1] . TQ_PDI; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tq_total === 0): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'clipboard', 'peach',
                    'لا مهامّ عليك الآن',
                    'حين يُسند إليك واجب في إحدى موادّك يظهر هنا بعدد بنوده ومدّته ودرجة نجاحه، مجمَّعًا حسب حالته: لم تبدأ، أو قيد التنفيذ، أو مكتملة بدرجتها.',
                    'تصفّح دروسك',
                    base_url('student/lessons'),
                    false,
                    'primary'
                ); ?>
            </div>
        <?php else: ?>
            <?php foreach ($tq_groups as $key => $g): ?>
                <?php if ($f_state !== '' && $f_state !== $key) continue; ?>
                <?php if (empty($g['items'])) continue; ?>

                <section>
                    <div class="tq-s-group">
                        <span class="tq-s-dot tq-s-dot--<?php echo $g['dot']; ?>" aria-hidden="true"></span>
                        <h2><?php echo html_escape($g['label']); ?></h2>
                        <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($g['items']) . TQ_PDI; ?></span>
                    </div>

                    <?php foreach ($g['items'] as $t): ?>
                        <?php
                        $ico = $tq_type_icon[$t['type'] ?? 'homework'] ?? $tq_type_icon['homework'];
                        /* التسمية تصف ما يحمله التاريخ فعلًا: تاريخ تسليم أو تاريخ بدء.
                           ولا موعد استحقاق في القاعدة، فلا يُكتب «موعد التسليم» فوق فراغ. */
                        $date_label = $key === 'done' ? 'تمّ التسليم' : ($key === 'progress' ? 'بدأتَه' : 'لم تبدأ بعد');
                        ?>
                        <article class="tq-s-row">

                            <!-- كتلة الموعد: اليوم كبيرًا، ثم الشهر، ثم منذ متى -->
                            <div class="tq-s-date">
                                <span class="tq-s-date__label"><?php echo html_escape($date_label); ?></span>
                                <?php if (!empty($t['at'])): ?>
                                    <span class="tq-s-date__day"><?php echo TQ_LRI . date('j', $t['at']) . TQ_PDI; ?></span>
                                    <span class="tq-s-date__month"><?php echo tq_s_month(date('n', $t['at'])); ?></span>
                                    <span class="tq-micro" style="color:var(--tq-text2)"><?php echo tq_since($t['at']); ?></span>
                                <?php else: ?>
                                    <span class="tq-micro" style="color:var(--tq-text2)">لا موعد محدَّد</span>
                                <?php endif; ?>
                            </div>

                            <div class="tq-s-row__main">
                                <h3 class="tq-s-row__title"><?php echo html_escape($t['title']); ?></h3>
                                <p class="tq-micro" style="margin:0 0 var(--tq-space-s)">
                                    <?php echo html_escape(trim(($t['subject'] ?? '') . ' — ' . ($t['stage'] ?? ''), ' —')); ?>
                                </p>
                                <div class="tq-s-meta">
                                    <?php if (!empty($t['minutes'])): ?>
                                        <span><?php echo tq_icon('clock', 16); ?><?php echo tq_s_minutes($t['minutes']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['points'])): ?>
                                        <span><?php echo tq_icon('award', 16); ?><?php echo tq_iso($t['points'] . ' بندًا'); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['pass'])): ?>
                                        <span><?php echo tq_icon('target', 16); ?>درجة النجاح <?php echo tq_num($t['pass'] . '%', 'tq-num--sm'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($key === 'done' && isset($t['score'], $t['max']) && $t['score'] !== null && $t['max'] > 0): ?>
                                        <span><?php echo tq_icon('check', 16); ?>الدرجة <?php echo tq_num($t['score'] . '/' . $t['max'], 'tq-num--sm'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- شارة الحالة فوق زرّ الفعل — من القاعدة لا من افتراض -->
                            <div class="tq-s-row__end">
                                <?php if ($key === 'done' && isset($t['pass_ok']) && $t['pass_ok'] !== null): ?>
                                    <?php echo tq_badge($t['pass_ok'] ? 'mastered' : 'late', $t['pass_ok'] ? 'ناجح' : 'يحتاج إعادة'); ?>
                                <?php else: ?>
                                    <?php echo tq_badge($g['badge'], $g['label']); ?>
                                <?php endif; ?>
                                <?php if ($key === 'done'): ?>
                                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo html_escape($t['href'] ?? '#'); ?>">عرض التقييم</a>
                                <?php elseif ($key === 'progress'): ?>
                                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo html_escape($t['href'] ?? '#'); ?>">متابعة</a>
                                <?php else: ?>
                                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo html_escape($t['href'] ?? '#'); ?>">ابدأ الآن</a>
                                <?php endif; ?>
                            </div>

                            <span class="tq-icon-box tq-pastel tq-pastel--<?php echo $ico[1]; ?>" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon($ico[0]); ?></span>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <aside class="tq-aside">

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title">ملخّص المهام</h2>
                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('clipboard'); ?></span>
            </div>

            <?php if ($tq_total === 0): ?>
                <?php echo tq_s_empty(
                    'chart', 'sky',
                    'لا أرقام بعد',
                    'عدد مهامك التي لم تبدأها وقيد التنفيذ والمكتملة يظهر هنا فور إسناد أول مهمّة إليك.',
                    '', '', true
                ); ?>
            <?php else: ?>
                <div class="tq-s-2x2">
                    <?php
                    echo tq_s_stat(tq_num(count($tq_groups['todo']['items'])),     'لم تبدأ',      '', 'peach');
                    echo tq_s_stat(tq_num(count($tq_groups['progress']['items'])), 'قيد التنفيذ',  '', 'rose');
                    echo tq_s_stat(tq_num(count($tq_groups['done']['items'])),     'مكتملة',       '', 'mint');
                    echo tq_s_stat(tq_num($tq_total),                              'إجمالي المهام', '', 'sky');
                    ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- التقويم: الشهر الجاري واليوم الحقيقي، ولا تُعلَّم أيام بلا مهامّ فيها. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title">التقويم</h2></div>
            <?php echo tq_s_calendar(time(), $tq_marks); ?>
            <?php echo tq_s_key([
                'done' => 'مهامّ سلّمتها',
                'due'  => 'مهامّ بدأتَها',
            ]); ?>
        </section>

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title">مهامّ في انتظارك</h2>
                <?php if ($tq_pending): ?>
                    <a class="tq-caption" href="<?php echo base_url('student/tasks'); ?>">عرض الكل</a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_pending)): ?>
                <?php echo tq_s_empty(
                    'calendar', 'lilac',
                    'لا مهامّ في انتظارك',
                    'كل واجب لم تُسلّمه بعد يظهر هنا بمادّته وعدد بنوده، لتعرف ما ينتظرك دون فتح القائمة.',
                    '', '', true
                ); ?>
            <?php else: ?>
                <ul class="tq-s-list">
                    <?php foreach ($tq_pending as $t): ?>
                        <li class="tq-s-item">
                            <span class="tq-icon-box tq-pastel tq-pastel--sky" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon('clipboard'); ?></span>
                            </span>
                            <span class="tq-s-item__body">
                                <span class="tq-s-item__t tq-s-trunc"><?php echo html_escape($t['title']); ?></span>
                                <span class="tq-s-item__s tq-s-trunc"><?php echo html_escape($t['subject'] ?? ''); ?></span>
                            </span>
                            <?php if (!empty($t['points'])): ?>
                                <span class="tq-caption" style="text-align:center">
                                    <?php echo tq_num($t['points'], 'tq-num--sm'); ?><br>
                                    <span class="tq-micro">بندًا</span>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
