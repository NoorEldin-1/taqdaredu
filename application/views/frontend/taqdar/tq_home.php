<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * الرئيسية — بوابة الطالب.
 *
 * ترتيب الشاشة يخدم سؤالا واحدا: «ما الذي أفعله الآن؟».
 * فالترحيب وزر الاستكمال أولا، ثم ما بدأه ولم يكمله، ثم كورساته،
 * ثم المقترح له. والعمود الجانبي للأشياء المؤقتة: حصة، موعد، رقم.
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
$tq_title = t('الرئيسية');
$tq_sub   = t('واصل من حيث توقفت، وتابع مواعيدك وتقدمك');
$tq_icon  = 'home';

$tq_courses   = tq_s_enrolled($tq_uid);
$tq_resume    = tq_s_resume($tq_uid);
$tq_active    = array_values(array_filter($tq_courses, function ($c) { return $c['status'] === 'progress'; }));
$tq_deadlines = tq_s_deadlines($tq_uid, 4);
$tq_act       = tq_s_activity($tq_uid);

/* الخطوة التالية — محسوبة لا مستأنفة.
   الفرق بينها وبين «واصل من حيث وقفت» أنها **توازن**: مراجعة مستحقة اليوم
   أهم من درس جديد لأن ما نسي لا يعوض بما يضاف، وواجب لم يسلم أقرب موعدا
   منهما. و`B1.7` يشترط «خطوة واحدة مقترحة لا قائمة»، و`F1.4` «زر واحد
   كبير» — وهما هذان.
   والحساب في الخادم (`Taqdar_learn_model::next_step`) لا هنا: ترتيب
   الأولويات قرار منتج، ونسخة ثانية منه في قالب تفترق عن الأولى عند أول
   تعديل. */
$CI = &get_instance();
$CI->load->model('taqdar_learn_model', 'tq_learn');
$tq_step   = $CI->tq_learn->next_step($tq_uid);
$tq_streak = $CI->tq_learn->streak($tq_uid);
$tq_goal   = $CI->tq_learn->goal_today($tq_uid);
$tq_exam   = $CI->tq_learn->exam_mode($tq_uid);

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <!-- الترحيب: زر واحد أساسي في الشاشة، ويعود إلى آخر موضع بالضبط
             (الكورس الأحدث نشاطا والدرس الذي توقف عنده) لا إلى أول الكورس. -->
        <?php /* `--plain`: بلا قرص التشغيل الزخرفي. كان القرص يعد بفيديو يعمل
                 عند الضغط وهو `aria-hidden` لا يستقبل ضغطا — فالوعد كاذب،
                 والزر الحقيقي «استكمل التعلم» تحته يقرأ ثانيا. وبحذفه يتسع
                 النص لعرض البطاقة بدل أن يترك فجوة في مكان القرص. */ ?>
        <section class="tq-s-banner tq-s-banner--plain tq-section tq-enter">
            <div class="tq-s-banner__body">
                <p class="tq-eyebrow"><?php
                  echo !empty($tq_exam['active']) ? t('وضع الامتحان') : t('خطوتك التالية'); ?></p>
                <h2 class="tq-display" style="margin-block-end:var(--tq-space-s)"><?php
                  echo html_escape($tq_step['title']); ?></h2>
                <p class="tq-body"><?php echo html_escape($tq_step['subtitle']); ?></p>
                <a class="tq-btn tq-btn--primary" href="<?php echo html_escape($tq_step['href']); ?>">
                    <?php echo tq_icon($tq_step['icon']); ?> <?php echo html_escape($tq_step['cta']); ?>
                </a>

                <?php /* الاستئناف يبقى — لكن ثانويا وبلا زر أساسي ثان.
                        فمن كانت خطوته اليوم مراجعة قد يريد مع ذلك أن يعود
                        إلى درسه، وحذف الباب كله ليس توجيها بل حجب.
                        ويخفى حين تكون الخطوة نفسها هي الاستئناف — رابطان
                        إلى موضع واحد يقرآن خيارين. */ ?>
                <?php if ($tq_resume !== null && $tq_step['kind'] !== 'resume'): ?>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                        <?php echo t('أو'); ?> <a href="<?php echo tq_s_lesson_url($tq_resume['id'], $tq_resume['resume_id']); ?>"><?php echo t('واصل'); ?>
                        <?php echo html_escape($tq_resume['title']); ?></a>
                        <?php echo t('حيث توقفت.'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php /* حلقة الهدف والسلسلة — `F2.6`.
                    وتخفى بالكامل لمن أوقف التلعيب من إعداداته: الوثيقة
                    تشترط «زر إيقاف كامل»، والإيقاف الذي يبقي الحلقة
                    ويخفي الرقم ليس إيقافا. */ ?>
            <?php if (!empty($tq_goal['gamify'])): ?>
              <?php
                $tq_pct  = (int) $tq_goal['percent'];
                $tq_dash = 100 - $tq_pct;
              ?>
              <div class="tq-s-goal" aria-hidden="false">
                <div class="tq-s-goal__ring" role="img"
                     aria-label="<?php echo te('أنجزت ____ من ____ ____ اليوم', array((int) $tq_goal['done'], (int) $tq_goal['target'], html_escape($tq_goal['plural']))); ?>">
                  <svg viewBox="0 0 42 42" width="96" height="96">
                    <circle class="tq-s-goal__track" cx="21" cy="21" r="15.9" fill="none" stroke-width="4"></circle>
                    <circle class="tq-s-goal__fill<?php echo $tq_goal['met'] ? ' is-met' : ''; ?>"
                            cx="21" cy="21" r="15.9" fill="none" stroke-width="4"
                            stroke-linecap="round"
                            stroke-dasharray="<?php echo $tq_pct; ?> <?php echo $tq_dash; ?>"
                            stroke-dashoffset="25"></circle>
                  </svg>
                  <span class="tq-s-goal__num"><?php echo tq_num($tq_goal['done'] . '/' . $tq_goal['target']); ?></span>
                </div>
                <p class="tq-s-goal__label"><?php
                  echo $tq_goal['met']
                     ? t('بلغت هدف اليوم')
                     : html_escape($tq_goal['plural']) . t(' اليوم'); ?></p>

                <?php if ((int) $tq_streak['days'] > 0): ?>
                  <p class="tq-s-goal__streak">
                    <?php echo tq_icon('flame', 14); ?>
                    <?php echo tq_num((int) $tq_streak['days']); ?> <?php echo t('يوما متتاليا'); ?>
                  </p>
                <?php else: ?>
                  <?php /* لا سلسلة: تدعى ولا تلام. «انقطعت» تقرأ عتابا. */ ?>
                  <p class="tq-s-goal__streak tq-s-goal__streak--off"><?php echo t('ابدأ سلسلتك اليوم'); ?></p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($tq_exam['active'])): ?>
          <?php /* شريط وضع الامتحان: يقول إن الشاشة تغيرت ولماذا، وكيف
                  يوقف. وضع يغير السلوك بلا أن يعلن نفسه يقرأ عطلا. */ ?>
          <div class="tq-s-examstrip tq-section">
            <span class="tq-s-examstrip__i" aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
            <p>
              <strong><?php echo t('وضع الامتحان سار.'); ?></strong>
              <?php echo t('بقي ____ يوما. خطوتك اليوم مراجعة لا درس جديد، والإشعارات التسويقية موقوفة.', array(tq_num((int) $tq_exam['days_left']))); ?>
            </p>
            <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/exams'); ?>"><?php echo t('اضبطه'); ?></a>
          </div>
        <?php endif; ?>

        <!-- استكمال التعلم -->
        <section class="tq-section">
            <div class="tq-sectionhead">
                <h2><?php echo t('استكمال التعلم'); ?></h2>
                <?php if ($tq_active): ?>
<?php /* المسار الحي `student/*`؛ و`taqdar/*` يحول إليه بـ301 من `.htaccess`،
                             فكتابته هنا تكلف الطالب رحلة ذهاب وإياب على كل نقرة. */ ?>
<?php /* والوجهة `courses` لا `lessons`: ما تحت هذا العنوان بطاقات كورسات،
                             و`state=progress` حالة كورس. وقد صارت «دروسي» شاشة دروس
                             مفردة لا تعرف هذه الحالة، فرابط إليها يصفي بلا شيء. */ ?>
                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/courses?state=progress'); ?>"><?php echo t('عرض الكل'); ?></a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_active)): ?>
                <div class="tq-card">
                    <?php echo tq_s_empty(
                        'play', 'sky',
                        t('لا يوجد درس قيد المتابعة'),
                        t('أول درس تبدأه سيظهر هنا ببطاقة تحمل نسبة تقدمك ورقم الدرس من إجمالي دروس الكورس.'),
                        t('ابدأ درسا الآن'),
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
                                    tq_badge('progress', t('قيد التقدم')),
                                    $c['seconds'] ? tq_s_clock($c['seconds']) : ''
                                ); ?>
                            </a>
                            <h3 class="tq-s-course__title">
                                <a href="<?php echo tq_s_lesson_url($c['id'], $c['resume_id']); ?>"
                                   style="color:var(--tq-navy)"><?php echo html_escape($c['title']); ?></a>
                            </h3>
                            <?php if ($c['level']): ?>
                                <p class="tq-micro" style="margin:0"><?php echo html_escape(tq_s_level($c['level'])); ?></p>
                            <?php endif; ?>
                            <?php echo tq_progress($c['progress'], t('تقدمك في ') . $c['title']); ?>
                            <p class="tq-caption" style="margin:0"><?php echo tq_s_lessons_word($c['done'], $c['lessons']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- كورساتي -->
        <section class="tq-section">
            <div class="tq-sectionhead">
                <h2><?php echo t('كورساتي'); ?></h2>
                <?php if ($tq_courses): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_courses) . TQ_PDI; ?></span>
                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/courses'); ?>"
                       style="margin-inline-start:auto"><?php echo t('عرض الكل'); ?></a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_courses)): ?>
                <div class="tq-card">
                    <?php echo tq_s_empty(
                        'book', 'mint',
                        t('لم تسجل في كورس بعد'),
                        t('الكورسات التي تسجل فيها تظهر هنا مع عدد دروسها ومدتها ونسبة إتمامك لها.'),
                        t('تصفح الكورسات'),
                        base_url('catalog')
                    ); ?>
                </div>
            <?php else: ?>
                <div class="tq-s-grid4 tq-stagger">
                    <?php foreach (array_slice($tq_courses, 0, 8) as $c): ?>
                        <?php
                        $badge = $c['status'] === 'done'
                            ? tq_badge('mastered', t('مكتمل'))
                            : ($c['status'] === 'progress' ? tq_badge('progress', t('قيد التقدم')) : tq_badge('idle', t('لم يبدأ')));
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
                                <span><?php echo tq_icon('book', 16); ?><?php echo tq_iso($c['lessons'] . t(' درسا')); ?></span>
                                <?php if ($c['seconds']): ?>
                                    <span><?php echo tq_icon('clock', 16); ?><?php echo tq_iso(tq_s_hours($c['seconds'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php echo tq_progress($c['progress'], t('تقدمك في ') . $c['title']); ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- المقترح لك — يبنى على برنامج الطالب وهدفه لا على الأكثر مبيعا.
             ولا يوجد في القاعدة اليوم جدول برامج ولا أهداف، فلا نعرض «الأكثر
             مبيعا» متنكرا في هيئة اقتراح شخصي. -->
        <section class="tq-section">
            <div class="tq-sectionhead"><h2><?php echo t('المقترح لك'); ?></h2></div>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'target', 'lilac',
                    t('اقتراحاتك تبنى على برنامجك'),
                    t('حدد صفك وهدفك الدراسي، فنقترح عليك الدروس التي تكمل برنامجك — لا الأكثر مبيعا.'),
                    t('حدد برنامجك'),
                    base_url('student/settings')
                ); ?>
            </div>
        </section>

    </div>

    <aside class="tq-aside">

        <!-- حصص بالطلب: طبقة مستقلة بجوار المنهج لا داخله. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('حصص بالطلب'); ?></h2>
                <a class="tq-caption" href="<?php echo base_url('student/on-demand'); ?>"><?php echo t('عرض الكل'); ?></a>
            </div>
            <?php echo tq_s_empty(
                'video', 'sky',
                t('لا معلم متاح الآن'),
                t('حين يفتح المعلمون أوقاتهم يظهر هنا ثلاثة منهم بسعر الساعة وزر حجز مباشر.'),
                t('تصفح حصص بالطلب'),
                base_url('student/on-demand'),
                true
            ); ?>
        </section>

        <!-- المواعيد القادمة: الأحمر لما اقترب، والنص يقول القرب فلا يحمله اللون وحده. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('المواعيد القادمة'); ?></h2>
                <?php if ($tq_deadlines): ?>
                    <a class="tq-caption" href="<?php echo base_url('student/calendar'); ?>"><?php echo t('التقويم'); ?></a>
                <?php endif; ?>
            </div>

            <?php if (empty($tq_deadlines)): ?>
                <?php echo tq_s_empty(
                    'calendar', 'peach',
                    t('لا مواعيد قريبة'),
                    t('مواعيد تسليم وحدات كورساتك تظهر هنا، ويتحول لونها إلى الأحمر حين يقترب الموعد.'),
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

        <!-- تقدمك: كل رقم من جدول حقيقي. والمقارنة بالأسبوع الماضي تعرض حيث
             يوجد طابع زمني للحدث فقط (نتائج الاختبارات)، ولا تخمن حيث لا يوجد. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('تقدمك'); ?></h2>
                <a class="tq-caption" href="<?php echo base_url('student/reports'); ?>"><?php echo t('التقارير'); ?></a>
            </div>

            <?php if (empty($tq_courses)): ?>
                <?php echo tq_s_empty(
                    'chart', 'mint',
                    t('أرقامك تبدأ بأول درس'),
                    t('ساعات دراستك ودروسك المكتملة ومتوسط درجاتك وسلسلة أيامك تظهر هنا فور بدئك.'),
                    '', '', true
                ); ?>
            <?php else: ?>
                <div class="tq-s-2x2">
                    <?php
                    echo tq_s_stat(
                        tq_iso(tq_s_hours($tq_act['seconds'])),
                        t('ساعات الدراسة'), 'clock', 'sky',
                        t('منذ بدء اشتراكك')
                    );

                    echo tq_s_stat(
                        tq_num($tq_act['lessons']),
                        t('الدروس المكتملة'), 'check', 'mint',
                        t('في كل كورساتك')
                    );

                    $score_note = $tq_act['score_delta'] === null
                        ? t('من اختباراتك المصححة')
                        : tq_iso(($tq_act['score_delta'] >= 0 ? '+' : '') . $tq_act['score_delta'] . t(' نقطة عن الأسبوع الماضي'));
                    echo tq_s_stat(
                        tq_num($tq_act['score'] . '%'),
                        t('متوسط الدرجات'), 'award', 'lilac',
                        $score_note
                    );

                    // السلسلة تحتاج سجل نشاط يومي ولا جدول له بعد — والشرطة أصدق من رقم مخترع.
                    echo tq_s_stat(
                        $tq_act['has_streak_source'] ? tq_num($tq_act['streak']) : '<span class="tq-muted">—</span>',
                        t('سلسلة الأيام'), 'flame', 'peach',
                        $tq_act['has_streak_source'] ? t('يوما متتاليا') : t('تظهر عند تسجيل نشاطك اليومي')
                    );
                    ?>
                </div>
            <?php endif; ?>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
