<?php
/**
 * بوّابة وليّ الأمر — تفاصيل الابن.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية — كل شيء واضح ومفهوم من
 * نظرة واحدة وبلا مصطلحات.
 *
 * ------------------------------------------------------------------
 * حاجز الرؤية — ما يراه وليّ الأمر وما لا يراه:
 *   يرى: الدروس المكتملة · الإتقان لكل مادة · أيام النشاط ·
 *        الحصص القادمة · المدفوعات والفواتير · ملاحظات المعلمين.
 *   لا يرى: محادثات المساعد الذكي · منشورات المجتمع ·
 *        كل إجابة خاطئة على حدة.
 * والسبب: «الرقابة الكاملة تُنتج طالبًا يُخفي، لا طالبًا يتعلّم.»
 * ولذلك لا يوجد في هذه الصفحة استعلام واحد على المحادثات ولا على
 * المنشورات ولا على `quiz_results.user_answers` — الحاجز مطبَّق في
 * طبقة الاستعلام لا في إخفاء عنصر من الواجهة.
 * ------------------------------------------------------------------
 *
 * المقياس الثلاثي المبسَّط: الالتزام · الفهم · الاتجاه.
 * والاتجاه مقارنةً بأسبوعه هو — لا ترتيب بين الأبناء ولا بين الطلاب.
 *
 * الربط في `parent_links`، والملكية تُفحص في الخادم قبل أي استعلام —
 * ومن فتح رابط ابن ليس ابنه لم يجد إلا شاشة «لا يُفتح حساب قبل ربطه».
 *
 * وخطة الأسبوع صارت من بيانات وليّ الأمر (`parent_links.scope`) بعد أن
 * كانت `5` مزروعة في الشيفرة تُعرض كأنها خطّته؛ وحين لا يحدّدها يُقال له.
 *
 * ما ينتظر جدولًا:
 *   `objectives`    — الأهداف، ومنها يُحسب «الفهم» (متقن من مفتوح)
 *   `activity_days` — يوم نشاط لكل طالب (المتاح اليوم طوابع زمنية متفرّقة)
 *   `session_requests` — الحصص القادمة
 *   ملاحظات المعلمين — تنتظر عمود ملاحظة معتمَدة في `quiz_results`
 */

$tq_nav   = 'children';
$tq_role  = 'parent';
$tq_icon  = 'users';

$tq_uid = (int) $this->session->userdata('user_id');
$tq_cid = (int) $this->input->get('id');

/* لا يُفتح حساب ابن إلا إن كان مربوطًا بهذا الوليّ برابط نشط — والفحص في
   الخادم عبر `Taqdar_parent_model::child()`، مصدر الحقيقة الواحد للملكية،
   لا نسخة استعلام في كل شاشة تتباعد عن أختها. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_parent_model');
$tq_pm = $tq_ci->taqdar_parent_model;

$tq_child = $tq_cid ? $tq_pm->child($tq_uid, $tq_cid) : null;

$tq_name  = $tq_child ? trim($tq_child['first_name'] . ' ' . $tq_child['last_name']) : '';
$tq_title = $tq_child ? $tq_name : 'تفاصيل الابن';
$tq_sub   = $tq_child ? 'صورة أسبوعه في ثلاثة أرقام' : 'يُفتح بعد ربط حساب ابنك';

/* --- الأسبوع يبدأ الأحد (السوق سعودي) --- */
$tq_week_start = strtotime('today') - ((int) date('w')) * 86400;
$tq_prev_start = $tq_week_start - 7 * 86400;

/* خطة الأسبوع: أيامٌ يحدّدها وليّ الأمر لكل ابن في الإعدادات وتُحفظ في
   `parent_links.scope`. وما لم يحدّدها، تُحسب على الافتراضي ويُقال ذلك
   صراحةً تحت الرقم — لا يُعرض افتراضٌ كأنه خطّة الأسرة. */
$tq_plan       = $tq_pm->plan_days($tq_uid, $tq_cid);
$tq_plan_days  = (int) $tq_plan['days'];
$tq_plan_is_default = !empty($tq_plan['is_default']);

$tq_days_this = 0;
$tq_days_prev = 0;
$tq_subjects  = [];
$tq_completed = 0;
$tq_payments  = [];
$tq_day_flags = array_fill(0, 7, false);

if ($tq_child) {

    /* أيام النشاط: تُجمَع من الطوابع الزمنية المتاحة فعلًا (مشاهدة واختبار).
       وهي إشارة جزئية إلى أن يوجد جدول أيام النشاط الصريح. */
    $tq_stamps = [];
    foreach ($this->db->query(
        "SELECT date_updated AS ts FROM watch_histories WHERE student_id = ?", [$tq_cid]
    )->result_array() as $tq_r) {
        $tq_stamps[] = (int) $tq_r['ts'];
    }
    foreach ($this->db->query(
        "SELECT date_added AS ts FROM quiz_results WHERE user_id = ? AND is_submitted = 1", [$tq_cid]
    )->result_array() as $tq_r) {
        $tq_stamps[] = (int) $tq_r['ts'];
    }

    $tq_day_set = [];
    foreach ($tq_stamps as $tq_ts) {
        if ($tq_ts <= 0) {
            continue;
        }
        $tq_day_set[strtotime('today', $tq_ts)] = true;
    }

    foreach (array_keys($tq_day_set) as $tq_day) {
        if ($tq_day >= $tq_week_start) {
            $tq_days_this++;
            $tq_idx = (int) floor(($tq_day - $tq_week_start) / 86400);
            if ($tq_idx >= 0 && $tq_idx < 7) {
                $tq_day_flags[$tq_idx] = true;
            }
        } elseif ($tq_day >= $tq_prev_start) {
            $tq_days_prev++;
        }
    }

    /* الإتقان لكل مادة + الدروس المكتملة. */
    $tq_subjects = $this->db->query(
        "SELECT c.id, c.title,
                COALESCE(w.course_progress, 0) AS progress,
                w.completed_lesson,
                COALESCE(w.date_updated, 0)    AS last_seen
           FROM enrol e
           JOIN course c ON c.id = e.course_id
           LEFT JOIN watch_histories w
                  ON w.student_id = e.user_id AND w.course_id = e.course_id
          WHERE e.user_id = ?
          ORDER BY c.title ASC",
        [$tq_cid]
    )->result_array();

    foreach ($tq_subjects as $tq_s) {
        $tq_list = json_decode((string) $tq_s['completed_lesson'], true);
        $tq_completed += is_array($tq_list) ? count($tq_list) : 0;
    }

    /* المدفوعات والفواتير — ما اشتُري لهذا الابن. */
    $tq_payments = $this->db->query(
        "SELECT p.id, p.amount, p.date_added, p.transaction_id, c.title AS course_title
           FROM payment p
           LEFT JOIN course c ON c.id = p.course_id
          WHERE p.user_id = ?
          ORDER BY p.date_added DESC
          LIMIT 10",
        [$tq_cid]
    )->result_array();
}

$tq_commitment = (int) round(100 * min($tq_days_this, $tq_plan_days) / max(1, $tq_plan_days));
$tq_day_names  = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

include 'portal_open.php';
?>

<?php if (!$tq_child): ?>

    <div class="tq-card tq-empty">
        <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('lock', 24); ?></span>
        <h2 class="tq-empty__title">لا يُفتح حساب قبل ربطه بحسابك</h2>
        <p class="tq-empty__text">
            بيانات أي طالب لا تظهر لوليّ أمر إلا بعد ربط موثّق بين الحسابين.
            اربط حساب ابنك، وستجد هنا تقدّمه ومواده وحصصه وفواتيره.
        </p>
        <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>">عودة إلى أبنائي</a>
    </div>

<?php else: ?>

<div class="tq-cols">
    <div>

        <!-- المقياس الثلاثي المبسَّط: ثلاثة أرقام لا لوحة أرقام -->
        <div class="tq-grid tq-grid--3 tq-section">
            <div class="tq-card" style="text-align:center">
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">الالتزام</p>
                <?php echo tq_ring($tq_commitment, 120, 10, 'من خطة أسبوعه'); ?>
                <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                    <?php echo tq_iso('حضر ' . $tq_days_this . ' أيام من ' . $tq_plan_days); ?>
                </p>
                <p class="tq-micro" style="margin:0">
                    <?php if ($tq_plan_is_default): ?>
                        خطة أسبوعه غير محدَّدة، فالحساب على
                        <?php echo TQ_LRI . $tq_plan_days . TQ_PDI; ?> أيام افتراضيًّا —
                        <a href="<?php echo base_url('parent/settings'); ?>">حدّدها</a>.
                    <?php else: ?>
                        حسب خطة <?php echo TQ_LRI . $tq_plan_days . TQ_PDI; ?> أيام التي حدّدتها له.
                    <?php endif; ?>
                </p>
            </div>

            <div class="tq-card" style="text-align:center">
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">الفهم</p>
                <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                    <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('target', 24); ?></span>
                    <p class="tq-empty__text tq-caption">
                        يظهر هنا كم هدفًا أتقنه من الأهداف المفتوحة له، فور تفعيل أهداف الدروس.
                    </p>
                </div>
            </div>

            <div class="tq-card" style="text-align:center">
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">الاتجاه</p>
                <?php
                $tq_diff = $tq_days_this - $tq_days_prev;
                $tq_trend_text = $tq_diff > 0 ? 'أفضل من أسبوعه الماضي'
                    : ($tq_diff < 0 ? 'أقلّ من أسبوعه الماضي' : 'كأسبوعه الماضي');
                $tq_trend_kind = $tq_diff > 0 ? 'mastered' : ($tq_diff < 0 ? 'due' : 'progress');
                ?>
                <p style="margin:0;font:var(--tq-type-numeralXl);color:var(--tq-navy)">
                    <?php echo tq_num(($tq_diff > 0 ? '+' : '') . $tq_diff); ?>
                </p>
                <p class="tq-caption" style="margin:0">فرق أيام النشاط</p>
                <p style="margin-block-start:var(--tq-space-m)"><?php echo tq_badge($tq_trend_kind, $tq_trend_text); ?></p>
                <p class="tq-micro" style="margin-block-start:var(--tq-space-s)">
                    نقارنه بأسبوعه هو، ولا نرتّبه بين أبنائك ولا بين زملائه.
                </p>
            </div>
        </div>

        <!-- الإتقان لكل مادة -->
        <section class="tq-section" aria-labelledby="tq-subj-h">
            <div class="tq-sectionhead"><h2 id="tq-subj-h">كل مادة على حدة</h2></div>

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
                                            : 'لم يبدأ بعد'; ?>
                                    </span>
                                </div>
                                <?php echo tq_progress((int) $tq_s['progress'], 'ما أنهاه في ' . $tq_s['title']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('book', 24); ?></span>
                    <h3 class="tq-empty__title">لا مواد مسجّلة بعد</h3>
                    <p class="tq-empty__text">حين يُسجَّل ابنك في مادة، تظهر هنا مع ما أنهاه منها.</p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('parent/payments'); ?>">المدفوعات</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- المدفوعات والفواتير -->
        <section aria-labelledby="tq-pay-h">
            <div class="tq-sectionhead"><h2 id="tq-pay-h">المدفوعات والفواتير</h2></div>

            <?php if ($tq_payments): ?>
                <div class="tq-card">
                    <table class="tq-table">
                        <caption class="tq-sr">فواتير ما اشتُري لهذا الابن</caption>
                        <thead>
                            <tr>
                                <th scope="col">التاريخ</th>
                                <th scope="col">ما اشتُري</th>
                                <th scope="col">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_payments as $tq_p): ?>
                                <tr>
                                    <td data-label="التاريخ"><?php echo tq_num(date('Y-m-d', (int) $tq_p['date_added']), 'tq-num--sm'); ?></td>
                                    <td data-label="ما اشتُري"><?php echo html_escape($tq_p['course_title'] ?: 'اشتراك'); ?></td>
                                    <td data-label="المبلغ"><?php echo tq_sar($tq_p['amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('wallet', 24); ?></span>
                    <h3 class="tq-empty__title">لا فواتير لهذا الابن</h3>
                    <p class="tq-empty__text">كل عملية دفع تخصّه ستظهر هنا بتاريخها ومبلغها.</p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('parent/payments'); ?>">كل المدفوعات</a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="tq-aside">

        <!-- أيام النشاط: الأسبوع يبدأ الأحد -->
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">أيام هذا الأسبوع</h2></div>
            <ul class="tq-row" style="gap:var(--tq-space-xs);flex-wrap:wrap">
                <?php foreach ($tq_day_names as $tq_i => $tq_d): ?>
                    <li style="text-align:center;flex:1;min-inline-size:40px">
                        <span class="tq-icon-box <?php echo $tq_day_flags[$tq_i] ? 'tq-pastel--mint' : ''; ?>"
                              style="<?php echo $tq_day_flags[$tq_i] ? 'color:var(--tq-mint-ink)' : 'background:var(--tq-ground);color:var(--tq-text3)'; ?>;inline-size:36px;block-size:36px;margin-inline:auto"
                              aria-hidden="true">
                            <?php echo $tq_day_flags[$tq_i] ? tq_icon('check', 16) : ''; ?>
                        </span>
                        <span class="tq-micro" style="display:block;margin-block-start:var(--tq-space-xs)"><?php echo html_escape(mb_substr($tq_d, 0, 3)); ?></span>
                        <span class="tq-sr"><?php echo html_escape($tq_d . ($tq_day_flags[$tq_i] ? ': نشِط' : ': بلا نشاط')); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
                <?php echo tq_iso('أنهى ' . $tq_completed . ' درسًا حتى الآن.'); ?>
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">الحصص القادمة</h2></div>
            <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('calendar', 24); ?></span>
                <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)">لا حصص محجوزة</h3>
                <p class="tq-empty__text tq-caption">حين يُحجَز لابنك موعد مع معلّم، يظهر هنا بيومه وساعته.</p>
                <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('parent/messages'); ?>">مراسلة المعلّم</a>
            </div>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">ملاحظات المعلمين</h2></div>
            <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('chat', 24); ?></span>
                <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)">لا ملاحظات بعد</h3>
                <p class="tq-empty__text tq-caption">
                    كل ملاحظة يعتمدها معلّم مع درجة ابنك تصلك هنا كما كتبها.
                </p>
                <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('parent/reports'); ?>">التقارير</a>
            </div>
        </div>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro">ما لا نعرضه لك</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                محادثات ابنك مع المساعد الذكي، ومنشوراته، وكل إجابة خاطئة على حدة.
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                الرقابة الكاملة تُنتج طالبًا يُخفي، لا طالبًا يتعلّم. نعطيك الصورة التي تكفيك لتساعده.
            </p>
        </div>
    </aside>
</div>

<?php endif; ?>

<?php include 'portal_close.php'; ?>
