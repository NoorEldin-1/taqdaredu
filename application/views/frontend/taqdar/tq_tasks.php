<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * مهامي — الواجبات والمهام المطلوبة من الطالب.
 *
 * المهام مجمعة بالحالة لا مرتبة بالتاريخ وحده: الطالب يسأل «ما المتأخر؟»
 * قبل أن يسأل «ما التالي؟»، فالمجموعة تجيب قبل الصف.
 *
 * مصدر البيانات: assessments.type = 'homework' — الواجب صف تقييم مرتبط بدرس
 * (assessments.lesson_id) في كورس مسجل (enrol)، وحالته من attempts للطالب:
 *   لا محاولة              → لم تبدأ
 *   محاولة بدأت ولم تسلم → قيد التنفيذ
 *   محاولة مسلمة         → مكتملة بدرجتها من attempts.score
 * وعدد بنود الواجب من question (question.quiz_id = معرف الدرس)،
 * ومدته من assessments.time_limit_sec، ودرجة نجاحه من assessments.pass_mark.
 *
 * بلا مصدر بعد: تاريخ استحقاق للواجب — لا عمود due في assessments، فلا يعرض
 * موعد مخترع ولا حالة «متأخر»، وتقتصر التواريخ على ما سجلته المحاولة فعلا.
 * وكذلك درجة الصعوبة: لا عمود لها، فلا شارة صعوبة تفترض.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'tasks';
$tq_role  = 'student';
$tq_title = t('مهامي');
$tq_sub   = t('تابع مهامك وحقق أفضل النتائج');
$tq_icon  = 'clipboard';

/**
 * شكل المهمة الواحدة الذي يقرأه العارض:
 *   id · title · subject · stage · at (طابع زمني مسجل أو صفر) · minutes
 *   points · pass · type · score · max (للمكتملة) · href
 *
 * والمجموعات ثلاث بحالات القاعدة نفسها — لا حالة «متأخر» لأن لا موعد
 * استحقاق يقاس عليه التأخر.
 */
$tq_groups = [
    'todo'     => ['label' => t('لم تبدأ'),     'dot' => 'idle', 'badge' => 'idle',     'items' => []],
    'progress' => ['label' => t('قيد التنفيذ'), 'dot' => 'due',  'badge' => 'progress', 'items' => []],
    'done'     => ['label' => t('مكتملة'),      'dot' => 'done', 'badge' => 'mastered', 'items' => []],
];

/* ---- الواجبات من القاعدة ---------------------------------------------
   get_instance() صراحة: $this في العرض ليس المتحكم. */
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

        /* آخر محاولة لكل تقييم — الترتيب تصاعدي فالأحدث يغلب.
           وأعمدة اعتماد المعلم تقرأ معها: الواجب عمل يقرؤه معلم، ودرجته
           لا تعرض قبل اعتماده. والحكم في `Taqdar_marking_model` لا هنا،
           فيقرأه كل عارض من موضع واحد. */
        $CI->load->model('taqdar_marking_model');
        $CI->taqdar_marking_model->ensure_schema();

        $tq_att = [];
        foreach ($CI->db->select('assessment_id, score, passed, started_at, submitted_at,'
                               . ' teacher_score, teacher_note, approved_at')
                        ->from('attempts')
                        ->where('student_id', $tq_uid)
                        ->where_in('assessment_id', $a_ids)
                        ->order_by('attempt_no', 'ASC')
                        ->get()->result_array() as $r) {
            $tq_att[(int) $r['assessment_id']] = $r;
        }

        // عدد بنود الواجب — الأسئلة معلقة بمعرف الدرس
        $tq_items_n = [];
        foreach ($CI->db->select('quiz_id, COUNT(*) AS n')
                        ->from('question')->where_in('quiz_id', $l_ids)
                        ->group_by('quiz_id')->get()->result_array() as $r) {
            $tq_items_n[(int) $r['quiz_id']] = (int) $r['n'];
        }

        // اسم المادة من التصنيف — والعنوان بديلا حين لا تصنيف
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
                /* ما يعرض من الدرجة يقرره النموذج: قبل اعتماد المعلم لا
                   تعرض. كان الطالب يقرأ رقما كتبه سكربت ويحسبه درجته
                   النهائية، ثم يأتي اعتماد المعلم فيغيره — أو لا يأتي
                   أصلا لأن الواجب لم يكن يصل معلما. */
                $view = $CI->taqdar_marking_model->homework_student_view($att);
                $item['graded']  = $view['visible'];
                $item['score']   = $view['score'];
                $item['max']     = 100;   // مقياس الواجب في المنصة نسبة مئوية
                $item['note']    = $view['note'];
                $item['pass_ok'] = $view['visible'] ? $view['passed'] : null;
            }
            $tq_groups[$key]['items'][] = $item;
        }
    }
}

$tq_total = 0;
foreach ($tq_groups as $g) $tq_total += count($g['items']);

$f_state = (string) $this->input->get('state', true);
if (!isset($tq_groups[$f_state])) $f_state = '';

$tq_marks = [];   // علامات التقويم — لا يعلم يوم بلا مهمة حقيقية فيه
foreach ($tq_groups as $key => $g) {
    foreach ($g['items'] as $t) {
        if (empty($t['at'])) continue;
        if (date('Y-n', $t['at']) !== date('Y-n')) continue;
        $tq_marks[(int) date('j', $t['at'])] = $key === 'done' ? 'done' : 'due';
    }
}

/* ما ينتظر الطالب: ما لم يسلم بعد — الجاري أولا لأنه بدأ فعلا */
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

        <nav class="tq-tabs tq-s-tabs" aria-label="<?php echo te('تصفية المهام بالحالة'); ?>">
            <?php
            $tabs = ['' => [t('الكل'), $tq_total]];
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
                    t('لا مهام عليك الآن'),
                    t('حين يسند إليك واجب في إحدى موادك يظهر هنا بعدد بنوده ومدته ودرجة نجاحه، مجمعا حسب حالته: لم تبدأ، أو قيد التنفيذ، أو مكتملة بدرجتها.'),
                    t('تصفح دروسك'),
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
                        /* التسمية تصف ما يحمله التاريخ فعلا: تاريخ تسليم أو تاريخ بدء.
                           ولا موعد استحقاق في القاعدة، فلا يكتب «موعد التسليم» فوق فراغ. */
                        $date_label = $key === 'done' ? t('تم التسليم') : ($key === 'progress' ? t('بدأته') : t('لم تبدأ بعد'));
                        ?>
                        <article class="tq-s-row">

                            <?php /* أيقونة النوع أول الصف لا آخره: كانت آخر أبنائه فتطبع
                                     في الطرف المقابل بعد الأزرار، فتبدو رمزا سائبا لا
                                     يعرف القارئ إلام يعود. وهي صفة المهمة، فمكانها قبلها. */ ?>
                            <span class="tq-icon-box tq-pastel tq-pastel--<?php echo $ico[1]; ?>" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon($ico[0]); ?></span>
                            </span>

                            <!-- كتلة الموعد: اليوم كبيرا، ثم الشهر، ثم منذ متى -->
                            <div class="tq-s-date">
                                <span class="tq-s-date__label"><?php echo html_escape($date_label); ?></span>
                                <?php if (!empty($t['at'])): ?>
                                    <span class="tq-s-date__day"><?php echo TQ_LRI . date('j', $t['at']) . TQ_PDI; ?></span>
                                    <span class="tq-s-date__month"><?php echo tq_s_month(date('n', $t['at'])); ?></span>
                                    <span class="tq-micro" style="color:var(--tq-text2)"><?php echo tq_since($t['at']); ?></span>
                                <?php else: ?>
                                    <span class="tq-micro" style="color:var(--tq-text2)"><?php echo t('لا موعد محدد'); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="tq-s-row__main">
                                <h3 class="tq-s-row__title"><?php echo html_escape($t['title']); ?></h3>
                                <p class="tq-micro" style="margin:0 0 var(--tq-space-s)">
                                    <?php echo html_escape(trim(($t['subject'] ?? '') . ' — ' . tq_s_level($t['stage'] ?? ''), ' —')); ?>
                                </p>
                                <div class="tq-s-meta">
                                    <?php if (!empty($t['minutes'])): ?>
                                        <span><?php echo tq_icon('clock', 16); ?><?php echo tq_s_minutes($t['minutes']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['points'])): ?>
                                        <span><?php echo tq_icon('award', 16); ?><?php echo tq_iso($t['points'] . t(' بندا')); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($t['pass'])): ?>
                                        <span><?php echo tq_icon('target', 16); ?><?php echo t('درجة النجاح'); ?> <?php echo tq_num($t['pass'] . '%', 'tq-num--sm'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($key === 'done' && !empty($t['graded']) && $t['score'] !== null): ?>
                                        <span><?php echo tq_icon('check', 16); ?><?php echo t('الدرجة'); ?> <?php echo tq_num(((float) $t['score'] == (int) $t['score'] ? (int) $t['score'] : $t['score']) . '%', 'tq-num--sm'); ?></span>
                                    <?php elseif ($key === 'done'): ?>
                                        <span><?php echo tq_icon('clock', 16); ?><?php echo t('ينتظر تصحيح معلمك'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php /* ملاحظة المعلم: هي أنفع ما في التصحيح، وكانت تكتب في
                                         شاشته ولا تصل صاحبها. */ ?>
                                <?php if ($key === 'done' && !empty($t['note'])): ?>
                                    <p class="tq-caption" style="margin:var(--tq-space-s) 0 0;padding:var(--tq-space-s) var(--tq-space-m);background:var(--tq-sand-fill);border-radius:var(--tq-radius-medium)">
                                        <span class="tq-strong"><?php echo t('ملاحظة معلمك:'); ?></span>
                                        <?php echo tq_iso(html_escape($t['note'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- شارة الحالة فوق زر الفعل — من القاعدة لا من افتراض -->
                            <div class="tq-s-row__end">
                                <?php if ($key === 'done' && isset($t['pass_ok']) && $t['pass_ok'] !== null): ?>
                                    <?php echo tq_badge($t['pass_ok'] ? 'mastered' : 'late', $t['pass_ok'] ? t('ناجح') : t('يحتاج إعادة')); ?>
                                <?php elseif ($key === 'done'): ?>
                                    <?php echo tq_badge('due', t('ينتظر التصحيح')); ?>
                                <?php else: ?>
                                    <?php echo tq_badge($g['badge'], $g['label']); ?>
                                <?php endif; ?>
                                <?php if ($key === 'done'): ?>
                                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo html_escape($t['href'] ?? '#'); ?>"><?php echo t('عرض التقييم'); ?></a>
                                <?php elseif ($key === 'progress'): ?>
                                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo html_escape($t['href'] ?? '#'); ?>"><?php echo t('متابعة'); ?></a>
                                <?php else: ?>
                                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo html_escape($t['href'] ?? '#'); ?>"><?php echo t('ابدأ الآن'); ?></a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <aside class="tq-aside">

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('ملخص المهام'); ?></h2>
                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('clipboard'); ?></span>
            </div>

            <?php if ($tq_total === 0): ?>
                <?php echo tq_s_empty(
                    'chart', 'sky',
                    t('لا أرقام بعد'),
                    t('عدد مهامك التي لم تبدأها وقيد التنفيذ والمكتملة يظهر هنا فور إسناد أول مهمة إليك.'),
                    '', '', true
                ); ?>
            <?php else: ?>
                <div class="tq-s-2x2">
                    <?php
                    echo tq_s_stat(tq_num(count($tq_groups['todo']['items'])),     t('لم تبدأ'),      '', 'peach');
                    echo tq_s_stat(tq_num(count($tq_groups['progress']['items'])), t('قيد التنفيذ'),  '', 'rose');
                    echo tq_s_stat(tq_num(count($tq_groups['done']['items'])),     t('مكتملة'),       '', 'mint');
                    echo tq_s_stat(tq_num($tq_total),                              t('إجمالي المهام'), '', 'sky');
                    ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- التقويم: الشهر الجاري واليوم الحقيقي، ولا تعلم أيام بلا مهام فيها. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('التقويم'); ?></h2></div>
            <?php echo tq_s_calendar(time(), $tq_marks); ?>
            <?php echo tq_s_key([
                'done' => t('مهام سلمتها'),
                'due'  => t('مهام بدأتها'),
            ]); ?>
        </section>

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('مهام في انتظارك'); ?></h2>
                <?php if ($tq_pending): ?>
                    <a class="tq-caption" href="<?php echo base_url('student/tasks'); ?>"><?php echo t('عرض الكل'); ?></a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_pending)): ?>
                <?php echo tq_s_empty(
                    'calendar', 'lilac',
                    t('لا مهام في انتظارك'),
                    t('كل واجب لم تسلمه بعد يظهر هنا بمادته وعدد بنوده، لتعرف ما ينتظرك دون فتح القائمة.'),
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
                                    <span class="tq-micro"><?php echo t(' بندا'); ?></span>
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
