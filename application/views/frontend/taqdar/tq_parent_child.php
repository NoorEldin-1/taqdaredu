<?php
/**
 * بوابة ولي الأمر — تفاصيل الابن.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية — كل شيء واضح ومفهوم من
 * نظرة واحدة وبلا مصطلحات.
 *
 * ------------------------------------------------------------------
 * حاجز الرؤية — ما يراه ولي الأمر وما لا يراه:
 *   يرى: الدروس المكتملة · الإتقان لكل مادة · أيام النشاط ·
 *        الحصص القادمة · المدفوعات والفواتير · ملاحظات المعلمين.
 *   لا يرى: محادثات المساعد الذكي · منشورات المجتمع ·
 *        كل إجابة خاطئة على حدة.
 * والسبب: «الرقابة الكاملة تنتج طالبا يخفي، لا طالبا يتعلم.»
 * ولذلك لا يوجد في هذه الصفحة استعلام واحد على المحادثات ولا على
 * المنشورات ولا على `quiz_results.user_answers` — الحاجز مطبق في
 * طبقة الاستعلام لا في إخفاء عنصر من الواجهة.
 * ------------------------------------------------------------------
 *
 * المقياس الثلاثي المبسط: الالتزام · الفهم · الاتجاه.
 * والاتجاه مقارنة بأسبوعه هو — لا ترتيب بين الأبناء ولا بين الطلاب.
 *
 * الربط في `parent_links`، والملكية تفحص في الخادم قبل أي استعلام —
 * ومن فتح رابط ابن ليس ابنه لم يجد إلا شاشة «لا يفتح حساب قبل ربطه».
 *
 * وخطة الأسبوع صارت من بيانات ولي الأمر (`parent_links.scope`) بعد أن
 * كانت `5` مزروعة في الشيفرة تعرض كأنها خطته؛ وحين لا يحددها يقال له.
 *
 * ثلاث بطاقات كانت حالات فارغة دائمة وبياناتها في القاعدة منذ زمن:
 *   · «الفهم»            — `objectives` (101 صفا) و`skill_state`
 *   · «الحصص القادمة»    — `tutoring_sessions` و`availability_slots`
 *   · «ملاحظات المعلمين» — `quiz_results.teacher_note` مع `approved_at`
 * الحالة الفارغة الصادقة تصير كذبا يوم تمتلئ الجداول ولا تقرؤها الشاشة:
 * يقرأ ولي الأمر «لا ملاحظات بعد» وللمعلم خمس ملاحظات معتمدة على ابنه.
 *
 * والملاحظة تعرض بشرط `approved_at` وحده: الدرجة قبل اعتمادها لا يراها
 * الطالب (`tq_grade_visible`)، فرؤية وليه لها تسبقه بخبر عن نفسه.
 *
 * ما ينتظر جدولا:
 *   `activity_days` — يوم نشاط لكل طالب (المتاح اليوم طوابع زمنية متفرقة)
 */

$tq_nav   = 'children';
$tq_role  = 'parent';
$tq_icon  = 'users';

$tq_uid = (int) $this->session->userdata('user_id');
$tq_cid = (int) $this->input->get('id');

/* لا يفتح حساب ابن إلا إن كان مربوطا بهذا الولي برابط نشط — والفحص في
   الخادم عبر `Taqdar_parent_model::child()`، مصدر الحقيقة الواحد للملكية،
   لا نسخة استعلام في كل شاشة تتباعد عن أختها. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_parent_model');
$tq_pm = $tq_ci->taqdar_parent_model;

$tq_child = $tq_cid ? $tq_pm->child($tq_uid, $tq_cid) : null;

$tq_name  = $tq_child ? trim($tq_child['first_name'] . ' ' . $tq_child['last_name']) : '';
$tq_title = $tq_child ? $tq_name : t('تفاصيل الابن');
$tq_sub   = $tq_child ? t('صورة أسبوعه في ثلاثة أرقام') : t('يفتح بعد ربط حساب ابنك');

/* TQ-CHILD-ONE — كل رقم في هذه الشاشة من `Taqdar_parent_model::child_detail()`.
   كان القالب نسخة ثانية منه باستعلاماته: «ما أنهاه» من `watch_histories`
   المنحرف (TQ-PROGRESS-ONE)، وأيام النشاط من المشاهدة وحدها (TQ-ACTIVITY-ALL)
   — والتطبيق يسأل النموذج فيرى غيرها. والنموذج هو الحكم، والقالب يعرض. */
$tq_plan_days = 5; $tq_plan_is_default = true;
$tq_days_this = 0; $tq_days_prev = 0;
$tq_subjects  = []; $tq_completed = 0; $tq_payments = [];
$tq_day_flags = array_fill(0, 7, false);
$tq_sessions  = []; $tq_notes = [];
$tq_skill     = ['open' => 0, 'mastered' => 0, 'percent' => 0];
$tq_commitment = 0;

$tq_detail = $tq_child ? $tq_pm->child_detail($tq_uid, $tq_cid) : null;
if ($tq_detail) {
    $tq_plan_days       = (int) $tq_detail['plan_days'];
    $tq_plan_is_default = !empty($tq_detail['plan_is_default']);
    $tq_days_this       = (int) $tq_detail['days_this'];
    $tq_days_prev       = (int) $tq_detail['days_prev'];
    $tq_day_flags       = $tq_detail['day_flags'];
    $tq_commitment      = (int) $tq_detail['commitment'];
    $tq_subjects        = $tq_detail['subjects'];
    $tq_completed       = (int) $tq_detail['completed'];
    $tq_skill           = $tq_detail['skill'];
    $tq_sessions        = $tq_detail['sessions'];
    $tq_notes           = $tq_detail['notes'];
    $tq_payments        = $tq_detail['payments'];
}

$tq_day_names  = [t('الأحد'), t('الاثنين'), t('الثلاثاء'), t('الأربعاء'), t('الخميس'), t('الجمعة'), t('السبت')];

include 'portal_open.php';
?>

<?php if (!$tq_child): ?>

    <div class="tq-card tq-empty">
        <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('lock', 24); ?></span>
        <h2 class="tq-empty__title"><?php echo t('لا يفتح حساب قبل ربطه بحسابك'); ?></h2>
        <p class="tq-empty__text">
            <?php echo t('بيانات أي طالب لا تظهر لولي أمر إلا بعد ربط موثق بين الحسابين. اربط حساب ابنك، وستجد هنا تقدمه ومواده وحصصه وفواتيره.'); ?>
        </p>
        <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>"><?php echo t('عودة إلى أبنائي'); ?></a>
    </div>

<?php else: ?>

<div class="tq-cols">
    <div>

        <!-- المقياس الثلاثي المبسط: ثلاثة أرقام لا لوحة أرقام -->
        <div class="tq-grid tq-grid--3 tq-section">
            <div class="tq-card" style="text-align:center">
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)"><?php echo t('الالتزام'); ?></p>
                <?php echo tq_ring($tq_commitment, 120, 10, t('من خطة أسبوعه')); ?>
                <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                    <?php echo tq_iso(t('حضر ') . $tq_days_this . t(' أيام من ') . $tq_plan_days); ?>
                </p>
                <p class="tq-micro" style="margin:0">
                    <?php if ($tq_plan_is_default): ?>
                        <?php echo t('خطة أسبوعه غير محددة، فالحساب على ____ أيام افتراضيا —', TQ_LRI . $tq_plan_days . TQ_PDI); ?>
                        <a href="<?php echo base_url('parent/settings'); ?>"><?php echo t('حددها'); ?></a>.
                    <?php else: ?>
                        <?php echo t('حسب خطة ____ أيام التي حددتها له.', TQ_LRI . $tq_plan_days . TQ_PDI); ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="tq-card" style="text-align:center">
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)"><?php echo t('الفهم'); ?></p>
                <?php if ($tq_skill['open'] > 0): ?>
                    <?php echo tq_ring($tq_skill['percent'], 120, 10, t('من أهدافه المفتوحة')); ?>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                        <?php echo tq_iso(t('أتقن ') . $tq_skill['mastered'] . t(' هدفا من ') . $tq_skill['open']); ?>
                    </p>
                    <p class="tq-micro" style="margin:0">
                        <?php echo t('الهدف يفتح بفتح درسه، ويعد متقنا حين يجيب عنه إجابة ثابتة لا إجابة واحدة.'); ?>
                    </p>
                <?php else: ?>
                    <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                        <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('target', 24); ?></span>
                        <p class="tq-empty__text tq-caption">
                            <?php echo t('لم يفتح بعد درسا له أهداف مقاسة. يظهر هنا كم هدفا أتقن من المفتوح له.'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tq-card" style="text-align:center">
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)"><?php echo t('الاتجاه'); ?></p>
                <?php
                $tq_diff = $tq_days_this - $tq_days_prev;
                $tq_trend_text = $tq_diff > 0 ? t('أفضل من أسبوعه الماضي')
                    : ($tq_diff < 0 ? t('أقل من أسبوعه الماضي') : t('كأسبوعه الماضي'));
                $tq_trend_kind = $tq_diff > 0 ? 'mastered' : ($tq_diff < 0 ? 'due' : 'progress');
                ?>
                <p style="margin:0;font:var(--tq-type-numeralXl);color:var(--tq-navy)">
                    <?php echo tq_num(($tq_diff > 0 ? '+' : '') . $tq_diff); ?>
                </p>
                <p class="tq-caption" style="margin:0"><?php echo t('فرق أيام النشاط'); ?></p>
                <p style="margin-block-start:var(--tq-space-m)"><?php echo tq_badge($tq_trend_kind, $tq_trend_text); ?></p>
                <p class="tq-micro" style="margin-block-start:var(--tq-space-s)">
                    <?php echo t('نقارنه بأسبوعه هو — بالأيام نفسها منه لا بالأسبوع كاملا، ولا نرتبه بين أبنائك ولا بين زملائه.'); ?>
                </p>
            </div>
        </div>

        <!-- الإتقان لكل مادة -->
        <section class="tq-section" aria-labelledby="tq-subj-h">
            <div class="tq-sectionhead"><h2 id="tq-subj-h"><?php echo t('كل مادة على حدة'); ?></h2></div>

            <?php if ($tq_subjects): ?>
                <div class="tq-card">
                    <ul class="tq-stack">
                        <?php foreach ($tq_subjects as $tq_i => $tq_s): ?>
                            <li style="padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <div class="tq-row tq-row--between" style="margin-block-end:var(--tq-space-s)">
                                    <span class="tq-row" style="gap:var(--tq-space-m)">
                                        <span class="tq-icon-box tq-pastel--<?php echo tq_pastel($tq_i); ?>" aria-hidden="true"><?php echo tq_icon('book'); ?></span>
                                        <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_s['title']); ?></span>
                                    </span>
                                    <span class="tq-caption">
                                        <?php echo (int) $tq_s['last_seen'] > 0
                                            ? html_escape(tq_since((int) $tq_s['last_seen']))
                                            : t('لم يبدأ بعد'); ?>
                                    </span>
                                </div>
                                <?php echo tq_progress((int) $tq_s['progress'], t('ما أنهاه في ') . $tq_s['title']); ?>
                                <?php /* الرقم تحت الشريط: النسبة وحدها لا تقول من كم. */ ?>
                                <?php if ((int) $tq_s['lessons_n'] > 0): ?>
                                    <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                                        <?php echo tq_iso(t('أنهى ') . (int) $tq_s['done_n'] . t(' من ')
                                            . tq_lessons_word((int) $tq_s['lessons_n'])); ?>
                                    </p>
                                <?php else: ?>
                                    <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                                        <?php echo t('لم ينشر في هذه المادة درس بعد.'); ?>
                                    </p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('book', 24); ?></span>
                    <h3 class="tq-empty__title"><?php echo t('لا مواد مسجلة بعد'); ?></h3>
                    <p class="tq-empty__text"><?php echo t('حين يسجل ابنك في مادة، تظهر هنا مع ما أنهاه منها.'); ?></p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('parent/payments'); ?>"><?php echo t('المدفوعات'); ?></a>
                </div>
            <?php endif; ?>
        </section>

        <!-- المدفوعات والفواتير -->
        <section aria-labelledby="tq-pay-h">
            <div class="tq-sectionhead"><h2 id="tq-pay-h"><?php echo t('المدفوعات والفواتير'); ?></h2></div>

            <?php if ($tq_payments): ?>
                <div class="tq-card">
                    <div class="tq-table-wrap">
                        <table class="tq-table">
                            <caption class="tq-sr"><?php echo t('فواتير ما اشتري لهذا الابن'); ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo t('التاريخ'); ?></th>
                                    <th scope="col"><?php echo t('ما اشتري'); ?></th>
                                    <th scope="col"><?php echo t('المبلغ'); ?></th>
                                    <th scope="col"><?php echo t('الحالة'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tq_payments as $tq_p): ?>
                                    <tr>
                                        <td data-label="<?php echo te('التاريخ'); ?>">
                                            <?php echo (int) $tq_p['ts'] > 0
                                                ? tq_num(date('Y-m-d', (int) $tq_p['ts']), 'tq-num--sm')
                                                : '<span class="tq-caption">—</span>'; ?>
                                        </td>
                                        <td data-label="<?php echo te('ما اشتري'); ?>"><?php echo html_escape($tq_p['title']); ?></td>
                                        <td data-label="<?php echo te('المبلغ'); ?>"><?php echo tq_sar($tq_p['amount'], 2); ?></td>
                                        <td data-label="<?php echo te('الحالة'); ?>">
                                            <?php echo tq_badge(
                                                $tq_p['status'] === 'paid' ? 'mastered' : ($tq_p['status'] === 'unpaid' ? 'due' : 'idle'),
                                                $tq_p['label']
                                            ); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('wallet', 24); ?></span>
                    <h3 class="tq-empty__title"><?php echo t('لا فواتير لهذا الابن'); ?></h3>
                    <p class="tq-empty__text"><?php echo t('كل عملية دفع تخصه ستظهر هنا بتاريخها ومبلغها.'); ?></p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('parent/payments'); ?>"><?php echo t('كل المدفوعات'); ?></a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="tq-aside">

        <!-- أيام النشاط: الأسبوع يبدأ الأحد -->
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('أيام هذا الأسبوع'); ?></h2></div>
            <ul class="tq-row" style="gap:var(--tq-space-xs);flex-wrap:wrap">
                <?php foreach ($tq_day_names as $tq_i => $tq_d): ?>
                    <li style="text-align:center;flex:1;min-inline-size:40px">
                        <span class="tq-icon-box <?php echo $tq_day_flags[$tq_i] ? 'tq-pastel--mint' : ''; ?>"
                              style="<?php echo $tq_day_flags[$tq_i] ? 'color:var(--tq-mint-ink)' : 'background:var(--tq-ground);color:var(--tq-text3)'; ?>;inline-size:36px;block-size:36px;margin-inline:auto"
                              aria-hidden="true">
                            <?php echo $tq_day_flags[$tq_i] ? tq_icon('check', 16) : ''; ?>
                        </span>
                        <span class="tq-micro" style="display:block;margin-block-start:var(--tq-space-xs)"><?php echo html_escape(mb_substr($tq_d, 0, 3)); ?></span>
                        <span class="tq-sr"><?php echo html_escape($tq_d . ($tq_day_flags[$tq_i] ? t(': نشط') : t(': بلا نشاط'))); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
                <?php echo tq_iso(t('أنهى ') . $tq_completed . t(' درسا حتى الآن.')); ?>
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('الحصص القادمة'); ?></h2></div>

            <?php if ($tq_sessions): ?>
                <ul class="tq-stack">
                    <?php foreach ($tq_sessions as $tq_ss): ?>
                        <?php
                        $tq_ts   = !empty($tq_ss['starts_at']) ? strtotime($tq_ss['starts_at']) : 0;
                        $tq_st   = (string) $tq_ss['status'];
                        $tq_skind = $tq_st === 'confirmed' ? 'mastered' : ($tq_st === 'live' ? 'progress' : 'due');
                        $tq_slab = ['requested' => t('بانتظار المعلم'), 'confirmed' => t('مؤكدة'), 'live' => t('جارية الآن')][$tq_st] ?? $tq_st;
                        ?>
                        <li class="tq-row" style="gap:var(--tq-space-m);align-items:flex-start">
                            <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('video'); ?></span>
                            <span style="flex:1;min-inline-size:0">
                                <span class="tq-strong" style="display:block;color:var(--tq-navy)">
                                    <?php echo html_escape($tq_ss['teacher'] ?: t('معلم')); ?>
                                </span>
                                <span class="tq-micro" style="display:block">
                                    <?php echo $tq_ts > 0
                                        ? tq_iso(html_escape(date('Y-m-d', $tq_ts) . ' — ' . date('H:i', $tq_ts)
                                            . ' · ' . (int) $tq_ss['duration_min'] . t(' دقيقة')))
                                        : t('الموعد لم يثبت بعد'); ?>
                                </span>
                                <?php /* TQ-SESSION-GRID — والصف يقال: المعلم
                                         يفتح لكل صف وقته، وولي الأمر الذي
                                         يقرأ موعدا بلا صف لا يعرف أهي حصة
                                         ابنه أم أخته. و«كل الصفوف» لا تكتب. */ ?>
                                <?php $tq_stag = array_filter(array(
                                    (string) ($tq_ss['subject_name'] ?? ''),
                                    (int) ($tq_ss['grade_id'] ?? 0) > 0 ? (string) $tq_ss['grade_name'] : '')); ?>
                                <?php if ($tq_stag): ?>
                                    <span class="tq-micro" style="display:block">
                                        <?php echo html_escape(implode(' · ', $tq_stag)); ?>
                                    </span>
                                <?php endif; ?>
                                <span style="display:inline-block;margin-block-start:var(--tq-space-xs)">
                                    <?php echo tq_badge($tq_skind, $tq_slab); ?>
                                </span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="tq-micro" style="margin-block-start:var(--tq-space-l)">
                    <?php echo t('رابط الدخول يصل ابنك في حسابه — الحصة له لا لك، وحضورك عليه يقرره هو ومعلمه.'); ?>
                </p>
            <?php else: ?>
                <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                    <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('calendar', 24); ?></span>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)"><?php echo t('لا حصص محجوزة'); ?></h3>
                    <p class="tq-empty__text tq-caption"><?php echo t('حين يحجز لابنك موعد مع معلم، يظهر هنا بيومه وساعته.'); ?></p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('parent/messages'); ?>"><?php echo t('مراسلة المعلم'); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('ملاحظات المعلمين'); ?></h2></div>

            <?php if ($tq_notes): ?>
                <ul class="tq-stack">
                    <?php foreach ($tq_notes as $tq_i => $tq_n): ?>
                        <li class="tq-pastel tq-pastel--<?php echo tq_pastel($tq_i); ?>">
                            <span class="tq-pastel__label tq-micro">
                                <?php echo html_escape($tq_n['teacher'] ?: t('معلم المادة')); ?>
                                · <?php echo html_escape((string) ($tq_n['course_title'] ?: $tq_n['lesson_title'])); ?>
                            </span>
                            <p class="tq-pastel__body" style="margin:var(--tq-space-xs) 0 0">
                                <?php echo tq_iso(html_escape($tq_n['teacher_note'])); ?>
                            </p>
                            <p class="tq-micro" style="margin:var(--tq-space-s) 0 0">
                                <?php echo html_escape(tq_since((int) $tq_n['approved_at'])); ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="tq-micro" style="margin-block-start:var(--tq-space-l)">
                    <?php echo t('تعرض كما كتبها المعلم، وبعد اعتماده الدرجة — فلا تسبق ابنك بخبر عن نفسه.'); ?>
                </p>
            <?php else: ?>
                <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                    <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('chat', 24); ?></span>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)"><?php echo t('لا ملاحظات بعد'); ?></h3>
                    <p class="tq-empty__text tq-caption">
                        <?php echo t('كل ملاحظة يعتمدها معلم مع درجة ابنك تصلك هنا كما كتبها.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('parent/reports'); ?>"><?php echo t('التقارير'); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro"><?php echo t('ما لا نعرضه لك'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo t('محادثات ابنك مع المساعد الذكي، ومنشوراته، وكل إجابة خاطئة على حدة.'); ?>
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                <?php echo t('الرقابة الكاملة تنتج طالبا يخفي، لا طالبا يتعلم. نعطيك الصورة التي تكفيك لتساعده.'); ?>
            </p>
        </div>
    </aside>
</div>

<?php endif; ?>


<?php /* ── نتائج اختبارات دروس الابن ──────────────────────────────── */ ?>
<?php if ($tq_child):
    $CI = get_instance();
    $CI->load->model('taqdar_quiz_model', 'tq_quiz');
    $r_rows  = $CI->tq_quiz->student_results((int) $tq_cid);
    $r_skin  = 'tq';
    $r_who   = 'parent';
    $r_title = t('نتائج اختبارات الدروس');
    $r_empty = t('لم يؤد ابنك اختبار درس بعد.');
?>
<div class="tq-section">
    <?php include APPPATH . 'views/components/tq_quiz_results.php'; ?>
</div>
<?php endif; ?>

<?php include 'portal_close.php'; ?>
