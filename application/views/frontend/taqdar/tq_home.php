<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * الرئيسية — بوّابة الطالب.
 *
 * ترتيب الشاشة يخدم سؤالًا واحدًا: «ما الذي أفعله الآن؟».
 * فالترحيب وزرّ الاستكمال أولًا، ثم ما بدأه ولم يُكمله، ثم كورساته،
 * ثم المقترح له. والعمود الجانبي للأشياء المؤقّتة: حصّة، موعد، رقم.
 *
 * موصول بالقاعدة: enrol · course · lesson · watch_histories ·
 * watched_duration · quiz_results · section.
 * بلا مصدر بعد: البرامج (فالمقترح لك حالة فارغة) وحصص بالطلب.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'home';
$tq_role  = 'student';
$tq_title = 'الرئيسية';
$tq_sub   = 'واصل من حيث توقّفت، وتابع مواعيدك وتقدّمك';
$tq_icon  = 'home';

$tq_courses   = tq_s_enrolled($tq_uid);
$tq_resume    = tq_s_resume($tq_uid);
$tq_active    = array_values(array_filter($tq_courses, function ($c) { return $c['status'] === 'progress'; }));
$tq_deadlines = tq_s_deadlines($tq_uid, 4);
$tq_act       = tq_s_activity($tq_uid);

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <!-- الترحيب: زرّ واحد أساسي في الشاشة، ويعود إلى آخر موضع بالضبط
             (الكورس الأحدث نشاطًا والدرس الذي توقّف عنده) لا إلى أوّل الكورس. -->
        <section class="tq-s-banner tq-section tq-enter">
            <div class="tq-s-banner__body">
                <p class="tq-eyebrow">استمرّ</p>
                <?php if ($tq_resume !== null): ?>
                    <h2 class="tq-display" style="margin-block-end:var(--tq-space-s)">واصل تعلّمك، وواصل تقدّمك</h2>
                    <p class="tq-body">
                        توقّفت عند
                        <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_resume['title']); ?></span>
                        —
                        <?php echo tq_s_lessons_word($tq_resume['done'], $tq_resume['lessons']); ?>.
                    </p>
                    <a class="tq-btn tq-btn--primary"
                       href="<?php echo tq_s_lesson_url($tq_resume['id'], $tq_resume['resume_id']); ?>">
                        <?php echo tq_icon('play'); ?> استكمل التعلّم
                    </a>
                <?php else: ?>
                    <h2 class="tq-display" style="margin-block-end:var(--tq-space-s)">ابدأ رحلتك مع تقدّر</h2>
                    <p class="tq-body">
                        اختر كورسك الأول، وسيظهر هنا زرّ يعيدك إلى آخر درس توقّفت عنده بالضبط.
                    </p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>">
                        تصفّح الكورسات
                    </a>
                <?php endif; ?>
            </div>
            <span class="tq-s-banner__art" aria-hidden="true"><?php echo tq_icon('play', 56); ?></span>
        </section>

        <!-- استكمال التعلّم -->
        <section class="tq-section">
            <div class="tq-sectionhead">
                <h2>استكمال التعلّم</h2>
                <?php if ($tq_active): ?>
                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('taqdar/lessons?state=progress'); ?>">عرض الكل</a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_active)): ?>
                <div class="tq-card">
                    <?php echo tq_s_empty(
                        'play', 'sky',
                        'لا يوجد درس قيد المتابعة',
                        'أوّل درس تبدأه سيظهر هنا ببطاقة تحمل نسبة تقدّمك ورقم الدرس من إجمالي دروس الكورس.',
                        'ابدأ درسًا الآن',
                        base_url('student/lessons')
                    ); ?>
                </div>
            <?php else: ?>
                <div class="tq-s-grid4 tq-stagger">
                    <?php foreach (array_slice($tq_active, 0, 4) as $c): ?>
                        <article class="tq-card tq-s-course">
                            <a href="<?php echo tq_s_lesson_url($c['id'], $c['resume_id']); ?>">
                                <?php echo tq_s_thumb(
                                    $c['title'], $c['thumbnail'], $c['index'],
                                    tq_badge('progress', 'قيد التقدّم'),
                                    $c['seconds'] ? tq_s_clock($c['seconds']) : ''
                                ); ?>
                            </a>
                            <h3 class="tq-s-course__title">
                                <a href="<?php echo tq_s_lesson_url($c['id'], $c['resume_id']); ?>"
                                   style="color:var(--tq-navy)"><?php echo html_escape($c['title']); ?></a>
                            </h3>
                            <?php if ($c['level']): ?>
                                <p class="tq-micro" style="margin:0"><?php echo html_escape($c['level']); ?></p>
                            <?php endif; ?>
                            <?php echo tq_progress($c['progress'], 'تقدّمك في ' . $c['title']); ?>
                            <p class="tq-caption" style="margin:0"><?php echo tq_s_lessons_word($c['done'], $c['lessons']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- كورساتي -->
        <section class="tq-section">
            <div class="tq-sectionhead">
                <h2>كورساتي</h2>
                <?php if ($tq_courses): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_courses) . TQ_PDI; ?></span>
                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/lessons'); ?>"
                       style="margin-inline-start:auto">عرض الكل</a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_courses)): ?>
                <div class="tq-card">
                    <?php echo tq_s_empty(
                        'book', 'mint',
                        'لم تسجَّل في كورس بعد',
                        'الكورسات التي تسجَّل فيها تظهر هنا مع عدد دروسها ومدّتها ونسبة إتمامك لها.',
                        'تصفّح الكورسات',
                        base_url('plans')
                    ); ?>
                </div>
            <?php else: ?>
                <div class="tq-s-grid4 tq-stagger">
                    <?php foreach (array_slice($tq_courses, 0, 8) as $c): ?>
                        <?php
                        $badge = $c['status'] === 'done'
                            ? tq_badge('mastered', 'مكتمل')
                            : ($c['status'] === 'progress' ? tq_badge('progress', 'قيد التقدّم') : tq_badge('idle', 'لم يبدأ'));
                        ?>
                        <article class="tq-card tq-s-course">
                            <a href="<?php echo tq_s_lesson_url($c['id'], $c['resume_id']); ?>">
                                <?php echo tq_s_thumb($c['title'], $c['thumbnail'], $c['index'], $badge,
                                    $c['seconds'] ? tq_s_clock($c['seconds']) : ''); ?>
                            </a>
                            <h3 class="tq-s-course__title">
                                <a href="<?php echo tq_s_lesson_url($c['id'], $c['resume_id']); ?>"
                                   style="color:var(--tq-navy)"><?php echo html_escape($c['title']); ?></a>
                            </h3>
                            <div class="tq-s-meta">
                                <span><?php echo tq_icon('book', 16); ?><?php echo tq_iso($c['lessons'] . ' درسًا'); ?></span>
                                <?php if ($c['seconds']): ?>
                                    <span><?php echo tq_icon('clock', 16); ?><?php echo tq_iso(tq_s_hours($c['seconds'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php echo tq_progress($c['progress'], 'تقدّمك في ' . $c['title']); ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- المقترح لك — يُبنى على برنامج الطالب وهدفه لا على الأكثر مبيعًا.
             ولا يوجد في القاعدة اليوم جدول برامج ولا أهداف، فلا نعرض «الأكثر
             مبيعًا» متنكّرًا في هيئة اقتراح شخصي. -->
        <section class="tq-section">
            <div class="tq-sectionhead"><h2>المقترح لك</h2></div>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'target', 'lilac',
                    'اقتراحاتك تُبنى على برنامجك',
                    'حدّد صفّك وهدفك الدراسي، فنقترح عليك الدروس التي تكمل برنامجك — لا الأكثر مبيعًا.',
                    'حدّد برنامجك',
                    base_url('student/settings')
                ); ?>
            </div>
        </section>

    </div>

    <aside class="tq-aside">

        <!-- حصص بالطلب: طبقة مستقلّة بجوار المنهج لا داخله. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title">حصص بالطلب</h2>
                <a class="tq-caption" href="<?php echo base_url('student/on-demand'); ?>">عرض الكل</a>
            </div>
            <?php echo tq_s_empty(
                'video', 'sky',
                'لا معلّم متاح الآن',
                'حين يفتح المعلّمون أوقاتهم يظهر هنا ثلاثة منهم بسعر الساعة وزرّ حجز مباشر.',
                'تصفّح حصص بالطلب',
                base_url('student/on-demand'),
                true
            ); ?>
        </section>

        <!-- المواعيد القادمة: الأحمر لما اقترب، والنصّ يقول القرب فلا يحمله اللون وحده. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title">المواعيد القادمة</h2>
                <?php if ($tq_deadlines): ?>
                    <a class="tq-caption" href="<?php echo base_url('student/calendar'); ?>">التقويم</a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_deadlines)): ?>
                <?php echo tq_s_empty(
                    'calendar', 'peach',
                    'لا مواعيد قريبة',
                    'مواعيد تسليم وحدات كورساتك تظهر هنا، ويتحوّل لونها إلى الأحمر حين يقترب الموعد.',
                    '', '', true
                ); ?>
            <?php else: ?>
                <ul class="tq-s-list">
                    <?php foreach ($tq_deadlines as $d): $w = tq_s_when($d['at']); ?>
                        <li class="tq-s-item">
                            <span class="tq-icon-box tq-pastel tq-pastel--peach" aria-hidden="true">
                                <span class="tq-pastel__icon"><?php echo tq_icon('clipboard'); ?></span>
                            </span>
                            <span class="tq-s-item__body">
                                <span class="tq-s-item__t tq-s-trunc"><?php echo html_escape($d['title']); ?></span>
                                <span class="tq-s-item__s tq-s-trunc"><?php echo html_escape($d['subject']); ?></span>
                            </span>
                            <?php echo tq_badge($w['kind'], $w['text']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- تقدّمك: كل رقم من جدول حقيقي. والمقارنة بالأسبوع الماضي تُعرض حيث
             يوجد طابع زمني للحدث فقط (نتائج الاختبارات)، ولا تُخمَّن حيث لا يوجد. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title">تقدّمك</h2>
                <a class="tq-caption" href="<?php echo base_url('student/reports'); ?>">التقارير</a>
            </div>

            <?php if (empty($tq_courses)): ?>
                <?php echo tq_s_empty(
                    'chart', 'mint',
                    'أرقامك تبدأ بأول درس',
                    'ساعات دراستك ودروسك المكتملة ومتوسط درجاتك وسلسلة أيامك تظهر هنا فور بدئك.',
                    '', '', true
                ); ?>
            <?php else: ?>
                <div class="tq-s-2x2">
                    <?php
                    echo tq_s_stat(
                        tq_iso(tq_s_hours($tq_act['seconds'])),
                        'ساعات الدراسة', 'clock', 'sky',
                        'منذ بدء اشتراكك'
                    );

                    echo tq_s_stat(
                        tq_num($tq_act['lessons']),
                        'الدروس المكتملة', 'check', 'mint',
                        'في كل كورساتك'
                    );

                    $score_note = $tq_act['score_delta'] === null
                        ? 'من اختباراتك المصحَّحة'
                        : tq_iso(($tq_act['score_delta'] >= 0 ? '+' : '') . $tq_act['score_delta'] . ' نقطة عن الأسبوع الماضي');
                    echo tq_s_stat(
                        tq_num($tq_act['score'] . '%'),
                        'متوسط الدرجات', 'award', 'lilac',
                        $score_note
                    );

                    // السلسلة تحتاج سجلّ نشاط يومي ولا جدول له بعد — والشرطة أصدق من رقم مخترَع.
                    echo tq_s_stat(
                        $tq_act['has_streak_source'] ? tq_num($tq_act['streak']) : '<span class="tq-muted">—</span>',
                        'سلسلة الأيام', 'flame', 'peach',
                        $tq_act['has_streak_source'] ? 'يومًا متتاليًا' : 'تظهر عند تسجيل نشاطك اليومي'
                    );
                    ?>
                </div>
            <?php endif; ?>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
