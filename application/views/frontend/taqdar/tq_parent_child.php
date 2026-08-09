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
$tq_title = $tq_child ? $tq_name : 'تفاصيل الابن';
$tq_sub   = $tq_child ? 'صورة أسبوعه في ثلاثة أرقام' : 'يفتح بعد ربط حساب ابنك';

/* --- الأسبوع يبدأ الأحد (السوق سعودي) --- */
$tq_week_start = strtotime('today') - ((int) date('w')) * 86400;
$tq_prev_start = $tq_week_start - 7 * 86400;
/* ما مضى من الأسبوع بما فيه اليوم — وعليه تقاس المقارنة، فلا يقارن
   أسبوع لم يتم بأسبوع تم. */
$tq_elapsed    = (int) date('w') + 1;

/* خطة الأسبوع: أيام يحددها ولي الأمر لكل ابن في الإعدادات وتحفظ في
   `parent_links.scope`. وما لم يحددها، تحسب على الافتراضي ويقال ذلك
   صراحة تحت الرقم — لا يعرض افتراض كأنه خطة الأسرة. */
$tq_plan       = $tq_pm->plan_days($tq_uid, $tq_cid);
$tq_plan_days  = (int) $tq_plan['days'];
$tq_plan_is_default = !empty($tq_plan['is_default']);

$tq_days_this = 0;
$tq_days_prev = 0;
$tq_subjects  = [];
$tq_completed = 0;
$tq_payments  = [];
$tq_day_flags = array_fill(0, 7, false);
$tq_sessions  = [];
$tq_notes     = [];
$tq_skill     = ['open' => 0, 'mastered' => 0, 'percent' => 0];

if ($tq_child) {

    /* أيام النشاط: تجمع من الطوابع الزمنية المتاحة فعلا.
       و`lesson_progress` أصدقها لأن فيه صفا **لكل درس** بتاريخ إنهائه —
       بينما `watch_histories` صف واحد لكل مادة بآخر تحديث لها وحده، فمن
       واظب خمسة أيام على مادة واحدة كان يحسب له يوم. والمصدر نفسه في
       التقرير الأسبوعي، فلا يفترق عدد هنا عن عدد هناك. */
    $tq_stamps = [];
    foreach ($this->db->query(
        "SELECT UNIX_TIMESTAMP(completed_at) AS ts FROM lesson_progress
          WHERE student_id = ? AND completed_at IS NOT NULL", [$tq_cid]
    )->result_array() as $tq_r) {
        $tq_stamps[] = (int) $tq_r['ts'];
    }
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
        } elseif ($tq_day >= $tq_prev_start && $tq_day < $tq_prev_start + $tq_elapsed * 86400) {
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

    /* المدفوعات والفواتير — ما اشتري لهذا الابن، من مصدري المال معا
       (فواتير تقدر ومدفوعات Academy) عبر الدفتر الموحد في النموذج. */
    $tq_payments = array_slice($tq_pm->payments_of($tq_cid, 10), 0, 10);

    /* الفهم: هدف متقن من هدف فتح له.
       الهدف يفتح بفتح درسه، ويتقن حين يبلغ مستواه في `skill_state` ثمانين.
       والمقياس مئوي لا كسري: `Taqdar_repo_model::touch_skill_state()` يكتب
       `($ok/$total)*100` ويقص على [0,100] — فعتبة `0.80` هنا كانت ستعد كل
       شيء متقنا وعتبة `80` على صفوف كسرية تعد كل شيء غير متقن. */
    $tq_sk = $this->db->query(
        "SELECT COUNT(*) AS open_n,
                SUM(CASE WHEN ss.level >= 80 THEN 1 ELSE 0 END) AS mastered_n
           FROM objectives o
           JOIN lesson l ON l.id = o.lesson_id
           JOIN enrol e  ON e.course_id = l.course_id AND e.user_id = ?
           LEFT JOIN skill_state ss ON ss.objective_id = o.id AND ss.student_id = ?
          WHERE EXISTS (SELECT 1 FROM lesson_progress lp
                         WHERE lp.student_id = ? AND lp.lesson_id = l.id)",
        [$tq_cid, $tq_cid, $tq_cid]
    )->row_array();

    $tq_skill['open']     = (int) ($tq_sk['open_n'] ?? 0);
    $tq_skill['mastered'] = (int) ($tq_sk['mastered_n'] ?? 0);
    $tq_skill['percent']  = $tq_skill['open'] > 0
        ? (int) round(100 * $tq_skill['mastered'] / $tq_skill['open'])
        : 0;

    /* الحصص القادمة — موعدها في `availability_slots.starts_at`.
       المطلوبة والمؤكدة وحدهما تعرضان: المعتذر عنها والمنتهية ليست
       «قادمة»، وعرضها يجعل ولي الأمر يترقب موعدا لن يقع. */
    if ($this->db->table_exists('tutoring_sessions')) {
        $tq_sessions = $this->db->query(
            "SELECT ts.id, ts.status, ts.meet_url, s.starts_at, s.duration_min,
                    TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS teacher
               FROM tutoring_sessions ts
               LEFT JOIN availability_slots s ON s.id = ts.slot_id
               LEFT JOIN users u ON u.id = ts.teacher_id
              WHERE ts.student_id = ?
                AND ts.status IN ('requested','confirmed','live')
                AND (s.starts_at IS NULL OR s.starts_at >= NOW() - INTERVAL 2 HOUR)
              ORDER BY s.starts_at ASC
              LIMIT 5",
            [$tq_cid]
        )->result_array();
    }

    /* ملاحظات المعلمين — المعتمدة وحدها.
       الدرجة قبل اعتمادها لا يراها الطالب، ورؤية وليه لها تسبقه بخبر
       عن نفسه — وهو أسوأ ما يقع بين مراهق وأهله. */
    $tq_notes = $this->db->query(
        "SELECT r.quiz_result_id, r.teacher_note, r.approved_at,
                l.title AS lesson_title, c.title AS course_title,
                TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS teacher
           FROM quiz_results r
           JOIN lesson l ON l.id = r.quiz_id
           LEFT JOIN course c ON c.id = l.course_id
           LEFT JOIN users u ON u.id = r.approved_by
          WHERE r.user_id = ?
            AND r.approved_at IS NOT NULL
            AND r.teacher_note IS NOT NULL AND TRIM(r.teacher_note) <> ''
          ORDER BY r.approved_at DESC
          LIMIT 5",
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
        <h2 class="tq-empty__title">لا يفتح حساب قبل ربطه بحسابك</h2>
        <p class="tq-empty__text">
            بيانات أي طالب لا تظهر لولي أمر إلا بعد ربط موثق بين الحسابين.
            اربط حساب ابنك، وستجد هنا تقدمه ومواده وحصصه وفواتيره.
        </p>
        <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>">عودة إلى أبنائي</a>
    </div>

<?php else: ?>

<div class="tq-cols">
    <div>

        <!-- المقياس الثلاثي المبسط: ثلاثة أرقام لا لوحة أرقام -->
        <div class="tq-grid tq-grid--3 tq-section">
            <div class="tq-card" style="text-align:center">
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">الالتزام</p>
                <?php echo tq_ring($tq_commitment, 120, 10, 'من خطة أسبوعه'); ?>
                <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                    <?php echo tq_iso('حضر ' . $tq_days_this . ' أيام من ' . $tq_plan_days); ?>
                </p>
                <p class="tq-micro" style="margin:0">
                    <?php if ($tq_plan_is_default): ?>
                        خطة أسبوعه غير محددة، فالحساب على
                        <?php echo TQ_LRI . $tq_plan_days . TQ_PDI; ?> أيام افتراضيا —
                        <a href="<?php echo base_url('parent/settings'); ?>">حددها</a>.
                    <?php else: ?>
                        حسب خطة <?php echo TQ_LRI . $tq_plan_days . TQ_PDI; ?> أيام التي حددتها له.
                    <?php endif; ?>
                </p>
            </div>

            <div class="tq-card" style="text-align:center">
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">الفهم</p>
                <?php if ($tq_skill['open'] > 0): ?>
                    <?php echo tq_ring($tq_skill['percent'], 120, 10, 'من أهدافه المفتوحة'); ?>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                        <?php echo tq_iso('أتقن ' . $tq_skill['mastered'] . ' هدفا من ' . $tq_skill['open']); ?>
                    </p>
                    <p class="tq-micro" style="margin:0">
                        الهدف يفتح بفتح درسه، ويعد متقنا حين يجيب عنه إجابة ثابتة لا إجابة واحدة.
                    </p>
                <?php else: ?>
                    <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                        <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('target', 24); ?></span>
                        <p class="tq-empty__text tq-caption">
                            لم يفتح بعد درسا له أهداف مقاسة. يظهر هنا كم هدفا أتقن من المفتوح له.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tq-card" style="text-align:center">
                <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">الاتجاه</p>
                <?php
                $tq_diff = $tq_days_this - $tq_days_prev;
                $tq_trend_text = $tq_diff > 0 ? 'أفضل من أسبوعه الماضي'
                    : ($tq_diff < 0 ? 'أقل من أسبوعه الماضي' : 'كأسبوعه الماضي');
                $tq_trend_kind = $tq_diff > 0 ? 'mastered' : ($tq_diff < 0 ? 'due' : 'progress');
                ?>
                <p style="margin:0;font:var(--tq-type-numeralXl);color:var(--tq-navy)">
                    <?php echo tq_num(($tq_diff > 0 ? '+' : '') . $tq_diff); ?>
                </p>
                <p class="tq-caption" style="margin:0">فرق أيام النشاط</p>
                <p style="margin-block-start:var(--tq-space-m)"><?php echo tq_badge($tq_trend_kind, $tq_trend_text); ?></p>
                <p class="tq-micro" style="margin-block-start:var(--tq-space-s)">
                    نقارنه بأسبوعه هو — بالأيام نفسها منه لا بالأسبوع كاملا،
                    ولا نرتبه بين أبنائك ولا بين زملائه.
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
                    <h3 class="tq-empty__title">لا مواد مسجلة بعد</h3>
                    <p class="tq-empty__text">حين يسجل ابنك في مادة، تظهر هنا مع ما أنهاه منها.</p>
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
                        <caption class="tq-sr">فواتير ما اشتري لهذا الابن</caption>
                        <thead>
                            <tr>
                                <th scope="col">التاريخ</th>
                                <th scope="col">ما اشتري</th>
                                <th scope="col">المبلغ</th>
                                <th scope="col">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_payments as $tq_p): ?>
                                <tr>
                                    <td data-label="التاريخ">
                                        <?php echo (int) $tq_p['ts'] > 0
                                            ? tq_num(date('Y-m-d', (int) $tq_p['ts']), 'tq-num--sm')
                                            : '<span class="tq-caption">—</span>'; ?>
                                    </td>
                                    <td data-label="ما اشتري"><?php echo html_escape($tq_p['title']); ?></td>
                                    <td data-label="المبلغ"><?php echo tq_sar($tq_p['amount'], 2); ?></td>
                                    <td data-label="الحالة">
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
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('wallet', 24); ?></span>
                    <h3 class="tq-empty__title">لا فواتير لهذا الابن</h3>
                    <p class="tq-empty__text">كل عملية دفع تخصه ستظهر هنا بتاريخها ومبلغها.</p>
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
                        <span class="tq-sr"><?php echo html_escape($tq_d . ($tq_day_flags[$tq_i] ? ': نشط' : ': بلا نشاط')); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
                <?php echo tq_iso('أنهى ' . $tq_completed . ' درسا حتى الآن.'); ?>
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">الحصص القادمة</h2></div>

            <?php if ($tq_sessions): ?>
                <ul class="tq-stack">
                    <?php foreach ($tq_sessions as $tq_ss): ?>
                        <?php
                        $tq_ts   = !empty($tq_ss['starts_at']) ? strtotime($tq_ss['starts_at']) : 0;
                        $tq_st   = (string) $tq_ss['status'];
                        $tq_skind = $tq_st === 'confirmed' ? 'mastered' : ($tq_st === 'live' ? 'progress' : 'due');
                        $tq_slab = ['requested' => 'بانتظار المعلم', 'confirmed' => 'مؤكدة', 'live' => 'جارية الآن'][$tq_st] ?? $tq_st;
                        ?>
                        <li class="tq-row" style="gap:var(--tq-space-m);align-items:flex-start">
                            <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('video'); ?></span>
                            <span style="flex:1;min-inline-size:0">
                                <span class="tq-strong" style="display:block;color:var(--tq-navy)">
                                    <?php echo html_escape($tq_ss['teacher'] ?: 'معلم'); ?>
                                </span>
                                <span class="tq-micro" style="display:block">
                                    <?php echo $tq_ts > 0
                                        ? tq_iso(html_escape(date('Y-m-d', $tq_ts) . ' — ' . date('H:i', $tq_ts)
                                            . ' · ' . (int) $tq_ss['duration_min'] . ' دقيقة'))
                                        : 'الموعد لم يثبت بعد'; ?>
                                </span>
                                <span style="display:inline-block;margin-block-start:var(--tq-space-xs)">
                                    <?php echo tq_badge($tq_skind, $tq_slab); ?>
                                </span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="tq-micro" style="margin-block-start:var(--tq-space-l)">
                    رابط الدخول يصل ابنك في حسابه — الحصة له لا لك، وحضورك عليه يقرره هو ومعلمه.
                </p>
            <?php else: ?>
                <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                    <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('calendar', 24); ?></span>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)">لا حصص محجوزة</h3>
                    <p class="tq-empty__text tq-caption">حين يحجز لابنك موعد مع معلم، يظهر هنا بيومه وساعته.</p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('parent/messages'); ?>">مراسلة المعلم</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">ملاحظات المعلمين</h2></div>

            <?php if ($tq_notes): ?>
                <ul class="tq-stack">
                    <?php foreach ($tq_notes as $tq_i => $tq_n): ?>
                        <li class="tq-pastel tq-pastel--<?php echo tq_pastel($tq_i); ?>">
                            <span class="tq-pastel__label tq-micro">
                                <?php echo html_escape($tq_n['teacher'] ?: 'معلم المادة'); ?>
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
                    تعرض كما كتبها المعلم، وبعد اعتماده الدرجة — فلا تسبق ابنك بخبر عن نفسه.
                </p>
            <?php else: ?>
                <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                    <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('chat', 24); ?></span>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)">لا ملاحظات بعد</h3>
                    <p class="tq-empty__text tq-caption">
                        كل ملاحظة يعتمدها معلم مع درجة ابنك تصلك هنا كما كتبها.
                    </p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('parent/reports'); ?>">التقارير</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro">ما لا نعرضه لك</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                محادثات ابنك مع المساعد الذكي، ومنشوراته، وكل إجابة خاطئة على حدة.
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                الرقابة الكاملة تنتج طالبا يخفي، لا طالبا يتعلم. نعطيك الصورة التي تكفيك لتساعده.
            </p>
        </div>
    </aside>
</div>

<?php endif; ?>

<?php include 'portal_close.php'; ?>
