<?php
/**
 * بوابة ولي الأمر — التقرير الأسبوعي.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية.
 * والتقرير هنا بلغة بشرية لا جدول: أربعة أسطر تقرأ في عشر ثوان،
 * كل رقم فيها معزول، وكل سطر يجيب سؤالا واحدا:
 *   ماذا أنجز · ما الذي تحسن · ما الذي يقلق · ماذا أفعل الآن.
 *
 * الأسبوع يبدأ الأحد (السوق سعودي)، والتقرير يرسل صباح الأحد.
 * والاتجاه مقارنة بأسبوعه هو — لا ترتيب بين الأبناء ولا بين الطلاب.
 *
 * وخطة الأسبوع لم تعد `5` مزروعة في الشيفرة: يحددها ولي الأمر لكل ابن في
 * الإعدادات وتحفظ في `parent_links.scope`. وحين لا يحددها، يحسب المقترح
 * على الافتراضي **ويقال ذلك في السطر نفسه** — لا يعرض افتراض كأنه خطته.
 * وسقط معه ادعاء «20 دقيقة في اليوم تكفي»: لا مصدر له في بيانات أحد.
 *
 * ما ينتظر جدولا:
 *   `progress_snapshots` — لقطة أسبوعية لكل مادة، ومنها يكتب سطر
 *                          «الرياضيات ارتفعت من 62% إلى 78%». ولا يوجد
 *                          اليوم إلا التقدم اللحظي، فالسطر الثاني يبنى من
 *                          مقارنة أيام نشاطه بأسبوعه الماضي — وهي المقارنة
 *                          الوحيدة المتاحة بصدق.
 *   `objectives`         — «أتقن كذا هدفا»
 * ولا يعرض سطر لا مصدر له: التقرير الذي يخمن أسوأ من تقرير أقصر.
 */

$tq_nav   = 'weekly';
$tq_role  = 'parent';
$tq_title = t('التقرير الأسبوعي');
$tq_sub   = t('أربعة أسطر تقرأ في عشر ثوان');
$tq_icon  = 'clipboard';

/* المدى المشمول يكتب صراحة تحت العنوان.
   «هذا الأسبوع» وحدها لا تقول أين ينتهي: من يفتح الشاشة الثلاثاء يقرأ
   أرقام ثلاثة أيام ويحسبها أرقام سبعة، فيظن ابنه أسوأ مما هو. */
$tq_day_ar = [t('الأحد'), t('الاثنين'), t('الثلاثاء'), t('الأربعاء'), t('الخميس'), t('الجمعة'), t('السبت')];

$tq_uid = (int) $this->session->userdata('user_id');

$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_parent_model');
$tq_pm = $tq_ci->taqdar_parent_model;

/* الأسبوع يبدأ الأحد. و`date('w')` يعطي 0 للأحد، فما بقي منه سبعة
   ناقص ما مضى — واليوم الجاري محسوب مما مضى لأنه لم ينته بعد. */
$tq_week_start = strtotime('today') - ((int) date('w')) * 86400;
$tq_prev_start = $tq_week_start - 7 * 86400;
$tq_days_left  = 6 - (int) date('w');
$tq_elapsed    = (int) date('w') + 1;   // ما مضى من الأسبوع بما فيه اليوم

$tq_sub = $tq_elapsed === 1
    ? t('من صباح الأحد إلى الآن — مقارنا بأحد الأسبوع الماضي')
    : t('من الأحد إلى ') . $tq_day_ar[(int) date('w')] . t(' — مقارنا بالأيام نفسها من الأسبوع الماضي');

/* TQ-WEEKLY-ONE — الأرقام من `Taqdar_parent_model::weekly()` لا من
   استعلامات في القالب. كانت الصفحة تحسب بنفسها والتطبيق يسأل النموذج
   والبريد يحسب نسخة ثالثة: «سلم اختبارا» تعد الموروث في الصفحة والنظامين
   في البريد، و«أيام النشاط» تعد المشاهدة ولا تعد المراجعة — فيقرأ ولي
   الأمر ثلاثة أرقام لأسبوع واحد. والنموذج الآن يعد النشاط كله
   (TQ-ACTIVITY-ALL) والاختبارات من النظامين. */
$tq_children = [];
foreach ($tq_pm->weekly($tq_uid)['children'] as $tq_k) {
    $tq_children[] = [
        'id'              => (int) $tq_k['student_id'],
        'first_name'      => (string) $tq_k['name'],
        'last_name'       => '',
        'plan_days'       => (int) $tq_k['plan_days'],
        'plan_is_default' => !empty($tq_k['plan_is_default']),
        'days_this'       => (int) $tq_k['days_this'],
        'days_prev'       => (int) $tq_k['days_prev'],
        'done'            => (int) $tq_k['lessons_done'],
        'done_all'        => (int) $tq_k['lessons_total'],
        'quizzes'         => (int) $tq_k['quizzes'],
        'stalled'         => $tq_k['stalled'],
    ];
}

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>
        <?php if ($tq_children): ?>

            <?php foreach ($tq_children as $tq_c): ?>
                <?php
                $tq_first = explode(' ', trim($tq_c['first_name'] . ' ' . $tq_c['last_name']))[0];

                /* الرموز من مكتبة أشكال الثيم لا من الإيموجي.
                   الإيموجي يرسمه كل نظام بشكل ولون غير الآخر، فينكسر
                   الطابع الهادئ للبوابة كلها بأربع صور صفراء لا تخضع
                   لتوكنات الألوان ولا تعرف الوضع الداكن.
                   [أيقونة، عائلة اللون، النص] */
                $tq_lines = [];

                /* الجملة تبنى نفيا لا بحشو «لا شيء» في موضع المفعول:
                   «أنهى لا دروس» ليست عربية. والفعل نفسه ينفى. */
                $tq_l1 = (int) $tq_c['done'] > 0
                    ? t('أنهى ') . tq_lessons_word((int) $tq_c['done'])
                    : t('لم ينه درسا');
                $tq_l1 .= (int) $tq_c['quizzes'] > 0
                    ? t(' وسلم ') . tq_exams_word((int) $tq_c['quizzes'])
                    : t(' ولم يسلم اختبارا');

                $tq_lines[] = [
                    ((int) $tq_c['done'] > 0 || (int) $tq_c['quizzes'] > 0) ? 'check-badge' : 'clock',
                    ((int) $tq_c['done'] > 0 || (int) $tq_c['quizzes'] > 0) ? 'mint' : 'sand',
                    $tq_l1 . t(' هذا الأسبوع'),
                ];

                $tq_lines[] = ['chart',
                    $tq_c['days_this'] > $tq_c['days_prev'] ? 'mint'
                        : ($tq_c['days_this'] < $tq_c['days_prev'] ? 'peach' : 'sky'),
                    $tq_c['days_this'] > $tq_c['days_prev']
                        ? t('نشاطه ارتفع من ') . tq_days($tq_c['days_prev'], t('صفر')) . t(' إلى ') . tq_days($tq_c['days_this']) . t(' مقارنة بأسبوعه الماضي')
                        : ($tq_c['days_this'] < $tq_c['days_prev']
                            ? t('نشاطه نزل من ') . tq_days($tq_c['days_prev']) . t(' إلى ') . tq_days($tq_c['days_this'], t('صفر')) . t(' مقارنة بأسبوعه الماضي')
                            : ($tq_c['days_this'] > 0
                                ? t('نشاطه ثابت عند ') . tq_days($tq_c['days_this']) . t(' كأسبوعه الماضي')
                                : t('لم يدرس في هذه الأيام ولا في مثلها من أسبوعه الماضي')))];

                if (!empty($tq_c['stalled'])) {
                    $tq_gap = (int) $tq_c['stalled']['last_seen'] > 0
                        ? (int) floor((time() - (int) $tq_c['stalled']['last_seen']) / 86400)
                        : null;
                    /* «لم يفتح المنصة» كانت خطأ في المرجع: المتوقف مادة
                       بعينها لا المنصة كلها، وقد يكون نشطا في غيرها. */
                    $tq_lines[] = ['clock', 'peach', $tq_gap === null
                        ? $tq_c['stalled']['title'] . t(': لم يبدأها بعد')
                        : ($tq_gap < 1
                            ? $tq_c['stalled']['title'] . t(': أقل مواده نشاطا، وآخر عهده بها اليوم')
                            : $tq_c['stalled']['title'] . t(': لم يفتحها منذ ') . tq_days($tq_gap))];
                }

                $tq_plan_days = (int) $tq_c['plan_days'];
                $tq_need = max(0, $tq_plan_days - (int) $tq_c['days_this']);
                $tq_plan_note = $tq_c['plan_is_default']
                    ? t(' (خطة أسبوعه غير محددة، فالحساب على ') . tq_days($tq_plan_days) . t(' افتراضيا)')
                    : '';
                /* «يومان باقيان» مرفوعان لأنهما مبتدأ الجملة، و«من خطة
                   يومين» مجروران بحرف الجر — والمثنى وحده يفرق بينهما. */
                $tq_lines[] = ['target', $tq_need > 0 ? 'lilac' : 'mint', $tq_need > 0
                    ? t('المقترح: ') . tq_days($tq_need, t('لا يوم'), 'nom')
                        . ($tq_need === 2 ? t(' باقيان') : ($tq_need === 1 ? t(' باق') : t(' باقية')))
                        . t(' من خطة ') . tq_days($tq_plan_days) . $tq_plan_note
                    : t('المقترح: أتم خطة أسبوعه — يكفي أن يحافظ على هذا الإيقاع') . $tq_plan_note];
                ?>

                <article class="tq-card tq-card--panel tq-section">
                    <h2 class="tq-h2" style="margin-block-end:var(--tq-space-l)">
                        <?php echo html_escape($tq_first); ?> <?php echo t('هذا الأسبوع:'); ?>
                    </h2>
                    <ul class="tq-stack">
                        <?php foreach ($tq_lines as [$tq_ic, $tq_fam, $tq_txt]): ?>
                            <li class="tq-row" style="gap:var(--tq-space-m);align-items:flex-start">
                                <span class="tq-icon-box tq-pastel--<?php echo $tq_fam; ?>"
                                      style="color:var(--tq-<?php echo $tq_fam; ?>-ink);flex:none" aria-hidden="true">
                                    <?php echo tq_icon($tq_ic); ?>
                                </span>
                                <span class="tq-body" style="color:var(--tq-text);align-self:center"><?php echo tq_iso(html_escape($tq_txt)); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="tq-micro" style="margin-block-start:var(--tq-space-l)">
                        <?php /* «حصيلته درسان» و«بقي يومان» مرفوعان — خبر وفاعل. */ ?>
                        <?php echo tq_iso(t('حصيلته منذ البداية ') . tq_lessons_word((int) $tq_c['done_all'], t('لا دروس بعد'), 'nom')
                            . t('. وبقي من هذا الأسبوع ') . tq_days($tq_days_left, t('يومه الأخير'), 'nom') . '.'); ?>
                    </p>

                    <a class="tq-btn tq-btn--secondary tq-btn--sm" style="margin-block-start:var(--tq-space-l)"
                       href="<?php echo base_url('parent/child'); ?>?id=<?php echo (int) $tq_c['id']; ?>">
                        <?php echo t('تفاصيل'); ?> <?php echo html_escape($tq_first); ?>
                    </a>
                </article>
            <?php endforeach; ?>

        <?php else: ?>

            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('clipboard', 24); ?></span>
                <h2 class="tq-empty__title"><?php echo t('لا تقرير قبل ربط حساب ابنك'); ?></h2>
                <p class="tq-empty__text">
                    <?php echo t('بعد الربط يصلك كل أحد صباحا تقرير من أربعة أسطر: ماذا أنجز هذا الأسبوع، وما الذي تحسن، وما الذي يقلق، وما الخطوة الصغيرة التي تكفي هذا الأسبوع.'); ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>"><?php echo t('اربط حساب ابنك'); ?></a>
            </div>

        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--lilac">
            <span class="tq-pastel__label tq-micro"><?php echo t('موعد التقرير'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo t('صباح كل أحد، مع بداية أسبوع ابنك الدراسي.'); ?>
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                <?php echo t('وما لا يستحق المقاطعة ينتظر هذا التقرير بدل أن يقطع يومك بإشعار.'); ?>
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('لماذا أربعة أسطر'); ?></h2></div>
            <p class="tq-caption">
                <?php echo t('التقرير الطويل لا يقرأ، وما لا يقرأ لا يغير شيئا. أربعة أسطر تكفي لتعرف أين ابنك وماذا تفعل اليوم.'); ?>
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
