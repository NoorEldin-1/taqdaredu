<?php
/**
 * بوّابة وليّ الأمر — التقرير الأسبوعي.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية.
 * والتقرير هنا بلغة بشرية لا جدول: أربعة أسطر تُقرأ في عشر ثوانٍ،
 * كل رقم فيها معزول، وكل سطر يجيب سؤالًا واحدًا:
 *   ماذا أنجز · ما الذي تحسّن · ما الذي يقلق · ماذا أفعل الآن.
 *
 * الأسبوع يبدأ الأحد (السوق سعودي)، والتقرير يُرسَل صباح الأحد.
 * والاتجاه مقارنةً بأسبوعه هو — لا ترتيب بين الأبناء ولا بين الطلاب.
 *
 * وخطة الأسبوع لم تعد `5` مزروعة في الشيفرة: يحدّدها وليّ الأمر لكل ابن في
 * الإعدادات وتُحفظ في `parent_links.scope`. وحين لا يحدّدها، يُحسب المقترح
 * على الافتراضي **ويُقال ذلك في السطر نفسه** — لا يُعرض افتراضٌ كأنه خطّته.
 * وسقط معه ادّعاء «20 دقيقة في اليوم تكفي»: لا مصدر له في بيانات أحد.
 *
 * ما ينتظر جدولًا:
 *   `progress_snapshots` — لقطة أسبوعية لكل مادة، ومنها يُكتب سطر
 *                          «الرياضيات ارتفعت من 62% إلى 78%». ولا يوجد
 *                          اليوم إلا التقدّم اللحظي، فالسطر الثاني يُبنى من
 *                          مقارنة أيام نشاطه بأسبوعه الماضي — وهي المقارنة
 *                          الوحيدة المتاحة بصدق.
 *   `objectives`         — «أتقن كذا هدفًا»
 * ولا يُعرض سطر لا مصدر له: التقرير الذي يخمّن أسوأ من تقرير أقصر.
 */

$tq_nav   = 'weekly';
$tq_role  = 'parent';
$tq_title = 'التقرير الأسبوعي';
$tq_sub   = 'أربعة أسطر تُقرأ في عشر ثوانٍ';
$tq_icon  = 'clipboard';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_parent_model');
$tq_pm = $tq_ci->taqdar_parent_model;

/* الأسبوع يبدأ الأحد. */
$tq_week_start = strtotime('today') - ((int) date('w')) * 86400;
$tq_prev_start = $tq_week_start - 7 * 86400;
$tq_days_left  = 7 - ((int) date('w') + 1);

$tq_children = $tq_pm->children($tq_uid);

foreach ($tq_children as &$tq_child) {
    $tq_cid = (int) $tq_child['student_id'];
    $tq_child['id'] = $tq_cid;

    /* خطة أسبوعه: ما حدّده وليّ الأمر لهذا الابن، وإلا الافتراضي مع إعلانه. */
    $tq_plan = $tq_pm->plan_days($tq_uid, $tq_cid);
    $tq_child['plan_days']  = (int) $tq_plan['days'];
    $tq_child['plan_is_default'] = !empty($tq_plan['is_default']);

    /* أيام النشاط هذا الأسبوع وأسبوعه الماضي. */
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

    $tq_days_this = 0;
    $tq_days_prev = 0;
    $tq_day_set   = [];
    foreach ($tq_stamps as $tq_ts) {
        if ($tq_ts > 0) {
            $tq_day_set[strtotime('today', $tq_ts)] = true;
        }
    }
    foreach (array_keys($tq_day_set) as $tq_day) {
        if ($tq_day >= $tq_week_start)      { $tq_days_this++; }
        elseif ($tq_day >= $tq_prev_start)  { $tq_days_prev++; }
    }

    /* ما أنهاه من دروس، واختبارات هذا الأسبوع. */
    $tq_done = 0;
    foreach ($this->db->query(
        "SELECT completed_lesson FROM watch_histories WHERE student_id = ?", [$tq_cid]
    )->result_array() as $tq_r) {
        $tq_list = json_decode((string) $tq_r['completed_lesson'], true);
        $tq_done += is_array($tq_list) ? count($tq_list) : 0;
    }

    $tq_quizzes = (int) $this->db->query(
        "SELECT COUNT(*) AS n FROM quiz_results
          WHERE user_id = ? AND is_submitted = 1 AND date_added >= ?",
        [$tq_cid, $tq_week_start]
    )->row('n');

    /* المادة المتوقّفة: أطول غياب بين مواده. */
    $tq_stalled = $this->db->query(
        "SELECT c.title, COALESCE(w.date_updated, 0) AS last_seen
           FROM enrol e
           JOIN course c ON c.id = e.course_id
           LEFT JOIN watch_histories w
                  ON w.student_id = e.user_id AND w.course_id = e.course_id
          WHERE e.user_id = ?
          ORDER BY last_seen ASC
          LIMIT 1",
        [$tq_cid]
    )->row_array();

    $tq_child['days_this'] = $tq_days_this;
    $tq_child['days_prev'] = $tq_days_prev;
    $tq_child['done']      = $tq_done;
    $tq_child['quizzes']   = $tq_quizzes;
    $tq_child['stalled']   = $tq_stalled;
}
unset($tq_child);

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>
        <?php if ($tq_children): ?>

            <?php foreach ($tq_children as $tq_c): ?>
                <?php
                $tq_first = explode(' ', trim($tq_c['first_name'] . ' ' . $tq_c['last_name']))[0];

                $tq_lines = [];

                $tq_lines[] = ['✅',
                    'أكمل ' . $tq_c['done'] . ' دروس · سلّم ' . $tq_c['quizzes'] . ' اختبارًا هذا الأسبوع'];

                $tq_lines[] = ['📈',
                    $tq_c['days_this'] > $tq_c['days_prev']
                        ? 'نشاطه ارتفع من ' . $tq_c['days_prev'] . ' أيام إلى ' . $tq_c['days_this'] . ' أيام مقارنةً بأسبوعه الماضي'
                        : ($tq_c['days_this'] < $tq_c['days_prev']
                            ? 'نشاطه نزل من ' . $tq_c['days_prev'] . ' أيام إلى ' . $tq_c['days_this'] . ' أيام مقارنةً بأسبوعه الماضي'
                            : 'نشاطه ثابت عند ' . $tq_c['days_this'] . ' أيام كأسبوعه الماضي')];

                if (!empty($tq_c['stalled'])) {
                    $tq_gap = (int) $tq_c['stalled']['last_seen'] > 0
                        ? (int) floor((time() - (int) $tq_c['stalled']['last_seen']) / 86400)
                        : null;
                    $tq_lines[] = ['⚠️', $tq_gap === null
                        ? $tq_c['stalled']['title'] . ': لم يبدأها بعد'
                        : $tq_c['stalled']['title'] . ': لم يفتح المنصّة منذ ' . $tq_gap . ' أيام'];
                }

                $tq_plan_days = (int) $tq_c['plan_days'];
                $tq_need = max(0, $tq_plan_days - (int) $tq_c['days_this']);
                $tq_plan_note = $tq_c['plan_is_default']
                    ? ' (خطة أسبوعه غير محدَّدة، فالحساب على ' . $tq_plan_days . ' أيام افتراضيًّا)'
                    : '';
                $tq_lines[] = ['🎯', $tq_need > 0
                    ? 'المقترح: ' . $tq_need . ' أيام باقية من خطة ' . $tq_plan_days . ' أيام' . $tq_plan_note
                    : 'المقترح: أتمّ خطة أسبوعه — يكفي أن يحافظ على هذا الإيقاع' . $tq_plan_note];
                ?>

                <article class="tq-card tq-card--panel tq-section">
                    <h2 class="tq-h2" style="margin-block-end:var(--tq-space-l)">
                        <?php echo html_escape($tq_first); ?> هذا الأسبوع:
                    </h2>
                    <ul class="tq-stack">
                        <?php foreach ($tq_lines as $tq_line): ?>
                            <li class="tq-row" style="gap:var(--tq-space-m);align-items:flex-start">
                                <span aria-hidden="true" style="font-size:18px;line-height:1.6"><?php echo $tq_line[0]; ?></span>
                                <span class="tq-body" style="color:var(--tq-text)"><?php echo tq_iso(html_escape($tq_line[1])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" style="margin-block-start:var(--tq-space-xl)"
                       href="<?php echo base_url('parent/child'); ?>?id=<?php echo (int) $tq_c['id']; ?>">
                        تفاصيل <?php echo html_escape($tq_first); ?>
                    </a>
                </article>
            <?php endforeach; ?>

        <?php else: ?>

            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('clipboard', 24); ?></span>
                <h2 class="tq-empty__title">لا تقرير قبل ربط حساب ابنك</h2>
                <p class="tq-empty__text">
                    بعد الربط يصلك كل أحد صباحًا تقرير من أربعة أسطر: ماذا أنجز هذا الأسبوع،
                    وما الذي تحسّن، وما الذي يقلق، وما الخطوة الصغيرة التي تكفي هذا الأسبوع.
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>">اربط حساب ابنك</a>
            </div>

        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--lilac">
            <span class="tq-pastel__label tq-micro">موعد التقرير</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                صباح كل أحد، مع بداية أسبوع ابنك الدراسي.
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                وما لا يستحقّ المقاطعة ينتظر هذا التقرير بدل أن يقطع يومك بإشعار.
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">لماذا أربعة أسطر</h2></div>
            <p class="tq-caption">
                التقرير الطويل لا يُقرأ، وما لا يُقرأ لا يغيّر شيئًا.
                أربعة أسطر تكفي لتعرف أين ابنك وماذا تفعل اليوم.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
