<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * دروسي — كل الكورسات المسجلة وتقدمها.
 *
 * التبويبات والتصفية تعملان في الخادم لا في المتصفح: الرابط يحمل الحالة،
 * فيمكن حفظه ومشاركته، ويعمل بلا جافاسكربت. وزر «مسح الفلاتر» لا يظهر
 * إلا حين يوجد فلتر فعلا — زر لا يفعل شيئا يعلم المستخدم تجاهل الأزرار.
 *
 * موصول بالقاعدة: enrol · course · lesson · watch_histories · category.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'lessons';
$tq_role  = 'student';
$tq_title = 'دروسي';
$tq_sub   = 'كل الدروس المسجلة لديك';
$tq_icon  = 'book';

$tq_all = tq_s_enrolled($tq_uid);

/* --- الفلاتر --- */
$f_state   = (string) $this->input->get('state', true);
$f_subject = (string) $this->input->get('subject', true);
$f_stage   = (string) $this->input->get('stage', true);
$f_all     = (string) $this->input->get('all', true);
if (!in_array($f_state, ['progress', 'done', 'idle'], true)) $f_state = '';

/* الحالة فلتر كغيرها: تضبط من التبويب ومن القائمة معا، وزر «مسح الفلاتر»
   الذي لا يظهر معها يترك الطالب أمام قائمة مصفاة بلا باب يعيده منها. */
$tq_has_filter = ($f_subject !== '' || $f_stage !== '' || $f_state !== '');

/* خيارات التصفية من كورسات الطالب نفسها لا من كل تصنيفات المنصة */
$opt_subjects = [];
$opt_stages   = [];
/* التصفية بالمادة **باسمها** لا بمعرف تصنيف: `course.category_id` صفر في كل
   كورس منشور، فالمفتاح الرقمي كان يجمع كل المواد في خيار واحد اسمه آخر ما
   مر. والاسم يأتي من `paths.subject_id` وهو ثابت ومقروء في الرابط. */
foreach ($tq_all as $c) {
    $s = tq_s_subject($c['category_id'], '', $c['id']);
    if ($s !== '') $opt_subjects[$s] = $s;
    if (trim((string) $c['level']) !== '') $opt_stages[$c['level']] = $c['level'];
}
ksort($opt_subjects);
ksort($opt_stages);

$tq_counts_by = ['all' => count($tq_all), 'progress' => 0, 'done' => 0, 'idle' => 0];
foreach ($tq_all as $c) $tq_counts_by[$c['status']]++;

$tq_list = array_values(array_filter($tq_all, function ($c) use ($f_state, $f_subject, $f_stage) {
    if ($f_state !== '' && $c['status'] !== $f_state) return false;
    if ($f_subject !== '' && tq_s_subject($c['category_id'], '', $c['id']) !== $f_subject) return false;
    if ($f_stage !== '' && (string) $c['level'] !== $f_stage) return false;
    return true;
}));

/* --- إجمالي التقدم: دروس مكتملة من إجمالي دروس الكورسات، لا متوسط نسب --- */
$tq_total_lessons = 0;
$tq_done_lessons  = 0;
foreach ($tq_all as $c) { $tq_total_lessons += $c['lessons']; $tq_done_lessons += $c['done']; }
$tq_overall = $tq_total_lessons > 0 ? (int) round($tq_done_lessons * 100 / $tq_total_lessons) : 0;

/* --- بناء روابط تحفظ بقية الفلاتر --- */
$tq_query = function ($over = []) use ($f_state, $f_subject, $f_stage) {
    $p = array_merge(['state' => $f_state, 'subject' => $f_subject, 'stage' => $f_stage], $over);
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return base_url('student/lessons') . ($p ? '?' . http_build_query($p) : '');
};

$tq_limit   = 9;
$tq_visible = ($f_all === '1') ? $tq_list : array_slice($tq_list, 0, $tq_limit);

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <nav class="tq-tabs tq-s-tabs" aria-label="تصفية الدروس بالحالة">
            <?php
            $tabs = [
                ''         => ['الكل',        $tq_counts_by['all']],
                'progress' => ['قيد التقدم', $tq_counts_by['progress']],
                'done'     => ['مكتملة',      $tq_counts_by['done']],
                'idle'     => ['لم تبدأ',     $tq_counts_by['idle']],
            ];
            foreach ($tabs as $key => $t):
                $is = ($f_state === $key);
                ?>
                <a class="tq-tab" href="<?php echo $tq_query(['state' => $key, 'all' => null]); ?>"
                   <?php echo $is ? 'aria-current="page"' : ''; ?>>
                    <?php echo html_escape($t[0]); ?>
                    <span class="tq-tab__n"><?php echo TQ_LRI . (int) $t[1] . TQ_PDI; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($tq_all)): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'book', 'mint',
                    'لا دروس بعد',
                    'كل كورس تسجل فيه يظهر هنا ببطاقة تحمل غلافه وحالته ومدته ونسبة تقدمك فيه.',
                    'تصفح الكورسات',
                    base_url('plans')
                ); ?>
            </div>

        <?php elseif (empty($tq_list)): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'search', 'sky',
                    'لا نتائج لهذه التصفية',
                    'جرب توسيع التصفية أو امسحها لترى كل كورساتك مرة أخرى.',
                    'مسح الفلاتر',
                    base_url('student/lessons')
                ); ?>
            </div>

        <?php else: ?>
            <div class="tq-s-grid3 tq-stagger">
                <?php foreach ($tq_visible as $c): ?>
                    <?php
                    $badge = $c['status'] === 'done'
                        ? tq_badge('mastered', 'مكتملة')
                        : ($c['status'] === 'progress' ? tq_badge('progress', 'قيد التقدم') : tq_badge('idle', 'لم تبدأ'));
                    $href = tq_s_lesson_url($c['id'], $c['resume_id']);
                    ?>
                    <article class="tq-card tq-s-course">
                        <a href="<?php echo $href; ?>">
                            <?php echo tq_s_thumb($c['title'], $c['thumbnail'], $c['index'], $badge,
                                $c['seconds'] ? tq_s_clock($c['seconds']) : ''); ?>
                        </a>
                        <h3 class="tq-s-course__title">
                            <a href="<?php echo $href; ?>" style="color:var(--tq-navy)"><?php echo html_escape($c['title']); ?></a>
                        </h3>
                        <p class="tq-micro" style="margin:0">
                            <?php echo html_escape($c['level'] !== '' ? tq_s_level($c['level']) : tq_s_subject($c['category_id'], 'كورس', $c['id'])); ?>
                        </p>
                        <?php echo tq_progress($c['progress'], 'تقدمك في ' . $c['title']); ?>
                        <p class="tq-caption" style="margin:0"><?php echo tq_s_lessons_word($c['done'], $c['lessons']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($f_all !== '1' && count($tq_list) > $tq_limit): ?>
                <p style="text-align:center;margin-block-start:var(--tq-space-xl)">
                    <a class="tq-btn tq-btn--secondary" href="<?php echo $tq_query(['all' => '1']); ?>">
                        عرض المزيد
                        <span class="tq-sr">من الكورسات</span>
                    </a>
                </p>
            <?php endif; ?>
        <?php endif; ?>

    </div>

    <aside class="tq-aside">

        <!-- التصفية: نموذج GET يعمل بلا جافاسكربت، وكل حقل له تسمية مرتبطة. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title">تصفية الدروس</h2>
                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('cog'); ?></span>
            </div>

            <form method="get" action="<?php echo base_url('student/lessons'); ?>">
                <p class="tq-field">
                    <label class="tq-field__label" for="tq-f-subject">المادة</label>
                    <select class="tq-select" id="tq-f-subject" name="subject">
                        <option value="">كل المواد</option>
                        <?php foreach ($opt_subjects as $id => $name): ?>
                            <option value="<?php echo html_escape($id); ?>"
                                <?php echo ((string) $id === $f_subject) ? 'selected' : ''; ?>>
                                <?php echo html_escape($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p class="tq-field">
                    <label class="tq-field__label" for="tq-f-stage">المرحلة</label>
                    <select class="tq-select" id="tq-f-stage" name="stage">
                        <option value="">كل المراحل</option>
                        <?php foreach ($opt_stages as $lvl): ?>
                            <option value="<?php echo html_escape($lvl); ?>"
                                <?php echo ((string) $lvl === $f_stage) ? 'selected' : ''; ?>>
                                <?php echo html_escape(tq_s_level($lvl)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p class="tq-field">
                    <label class="tq-field__label" for="tq-f-state">الحالة</label>
                    <select class="tq-select" id="tq-f-state" name="state">
                        <option value="">كل الحالات</option>
                        <option value="progress" <?php echo $f_state === 'progress' ? 'selected' : ''; ?>>قيد التقدم</option>
                        <option value="done"     <?php echo $f_state === 'done' ? 'selected' : ''; ?>>مكتملة</option>
                        <option value="idle"     <?php echo $f_state === 'idle' ? 'selected' : ''; ?>>لم تبدأ</option>
                    </select>
                </p>

                <button class="tq-btn tq-btn--primary tq-btn--block" type="submit">طبق التصفية</button>

                <?php if ($tq_has_filter): ?>
                    <a class="tq-btn tq-btn--secondary tq-btn--block" style="margin-block-start:var(--tq-space-s)"
                       href="<?php echo base_url('student/lessons'); ?>">مسح الفلاتر</a>
                <?php endif; ?>
            </form>
        </section>

        <!-- ملخص تقدمك -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title">ملخص تقدمك</h2></div>

            <?php if (empty($tq_all)): ?>
                <?php echo tq_s_empty(
                    'chart', 'mint',
                    'لا تقدم بعد',
                    'حلقة تقدمك وتوزيع حالات كورساتك يظهران هنا بعد أول درس تكمله.',
                    '', '', true
                ); ?>
            <?php else: ?>
                <div class="tq-row" style="gap:var(--tq-space-l)">
                    <?php echo tq_ring($tq_overall, 108, 10); ?>
                    <div>
                        <p class="tq-strong" style="margin:0;color:var(--tq-navy)">إجمالي التقدم</p>
                        <p class="tq-caption" style="margin:0">
                            <?php echo tq_s_lessons_word($tq_done_lessons, $tq_total_lessons); ?>
                        </p>
                    </div>
                </div>

                <ul class="tq-s-list" style="margin-block-start:var(--tq-space-l)">
                    <?php
                    $rows = [
                        ['done', 'كورسات مكتملة',    $tq_counts_by['done']],
                        ['due',  'كورسات قيد التقدم', $tq_counts_by['progress']],
                        ['idle', 'كورسات لم تبدأ',   $tq_counts_by['idle']],
                    ];
                    foreach ($rows as [$dot, $label, $n]):
                        ?>
                        <li class="tq-row tq-row--between">
                            <span class="tq-row" style="gap:var(--tq-space-s)">
                                <span class="tq-s-dot tq-s-dot--<?php echo $dot; ?>" aria-hidden="true"></span>
                                <span class="tq-caption"><?php echo html_escape($label); ?></span>
                            </span>
                            <?php echo tq_num($n, 'tq-num--sm'); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a class="tq-btn tq-btn--secondary tq-btn--block" style="margin-block-start:var(--tq-space-l)"
                   href="<?php echo base_url('student/reports'); ?>">عرض تقرير تفصيلي</a>
            <?php endif; ?>
        </section>

        <!-- حصص بالطلب -->
        <section class="tq-card tq-card--panel tq-pastel tq-pastel--sky">
            <div class="tq-card__head">
                <h2 class="tq-card__title tq-pastel__title">حصص بالطلب</h2>
                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('video', 24); ?></span>
            </div>
            <p class="tq-pastel__body">احجز حصة مباشرة مع معلم عند حاجتك لفهم أعمق.</p>
            <a class="tq-btn tq-btn--mastery tq-btn--block" href="<?php echo base_url('student/on-demand'); ?>">احجز الآن</a>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
