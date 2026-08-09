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
$tq_title = 'التقرير الأسبوعي';
$tq_sub   = 'أربعة أسطر تقرأ في عشر ثوان';
$tq_icon  = 'clipboard';

/* المدى المشمول يكتب صراحة تحت العنوان.
   «هذا الأسبوع» وحدها لا تقول أين ينتهي: من يفتح الشاشة الثلاثاء يقرأ
   أرقام ثلاثة أيام ويحسبها أرقام سبعة، فيظن ابنه أسوأ مما هو. */
$tq_day_ar = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

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
    ? 'من صباح الأحد إلى الآن — مقارنا بأحد الأسبوع الماضي'
    : 'من الأحد إلى ' . $tq_day_ar[(int) date('w')] . ' — مقارنا بالأيام نفسها من الأسبوع الماضي';

$tq_children = $tq_pm->children($tq_uid);

foreach ($tq_children as &$tq_child) {
    $tq_cid = (int) $tq_child['student_id'];
    $tq_child['id'] = $tq_cid;

    /* خطة أسبوعه: ما حدده ولي الأمر لهذا الابن، وإلا الافتراضي مع إعلانه. */
    $tq_plan = $tq_pm->plan_days($tq_uid, $tq_cid);
    $tq_child['plan_days']  = (int) $tq_plan['days'];
    $tq_child['plan_is_default'] = !empty($tq_plan['is_default']);

    /* أيام النشاط هذا الأسبوع وأسبوعه الماضي.
       ثلاثة مصادر لا اثنان، و`lesson_progress` أولها وأصدقها: فيه صف
       **لكل درس** بتاريخ إنهائه. أما `watch_histories` فصف واحد لكل مادة
       يحمل آخر تحديث لها وحده — فمن درس خمسة أيام متتابعة في مادة واحدة
       كان يحسب له يوم نشاط واحد، ومن سجل في خمس مواد ولمسها مرة واحدة
       تحسب له خمسة. المقياس كان يكافئ تعدد المواد لا المواظبة. */
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

    $tq_days_this = 0;
    $tq_days_prev = 0;
    $tq_day_set   = [];
    foreach ($tq_stamps as $tq_ts) {
        if ($tq_ts > 0) {
            $tq_day_set[strtotime('today', $tq_ts)] = true;
        }
    }
    /* المقارنة على مدى واحد: أيام هذا الأسبوع حتى اليوم مقابل **الأيام
       نفسها** من الأسبوع الماضي.

       كانت تقارن ما مضى من هذا الأسبوع بالأسبوع الماضي كاملا: فصباح
       الأحد — وهو موعد إرسال التقرير نفسه — يقرأ كل ولي أمر أن نشاط
       ابنه «نزل»، لأن أسبوعا لم يبدأ بعد يقارن بأسبوع تم. رسالة تصل
       أسبوعيا وتقول لكل أب إن ابنه تراجع لا تقرأ مرتين. */
    foreach (array_keys($tq_day_set) as $tq_day) {
        if ($tq_day >= $tq_week_start) {
            $tq_days_this++;
        } elseif ($tq_day >= $tq_prev_start && $tq_day < $tq_prev_start + ($tq_elapsed * 86400)) {
            $tq_days_prev++;
        }
    }

    /* دروس **هذا الأسبوع** واختباراته.
       كان العدد يجمع `watch_histories.completed_lesson` كله ثم يكتب في
       السطر «هذا الأسبوع»: يقرأ ولي أمر ابنه لم يفتح المنصة منذ شهر
       «أكمل 35 دروس هذا الأسبوع» فيطمئن — وهو أخطر ما يفعله تقرير.
       و`lesson_progress.completed_at` تاريخ صريح لكل درس، فالسؤال يجاب
       من عموده لا يقدر من مجموع بلا تاريخ. */
    $tq_done = (int) $this->db->query(
        "SELECT COUNT(*) AS n FROM lesson_progress
          WHERE student_id = ? AND completed_at IS NOT NULL
            AND completed_at >= FROM_UNIXTIME(?)",
        [$tq_cid, $tq_week_start]
    )->row('n');

    /* والحصيلة الكلية تعرض إلى جانبه لا بدلا منه: الأسبوع يقاس، والعمر
       يذكر — والخلط بينهما هو العطل نفسه. */
    $tq_done_all = (int) $this->db->query(
        "SELECT COUNT(*) AS n FROM lesson_progress
          WHERE student_id = ? AND completed_at IS NOT NULL",
        [$tq_cid]
    )->row('n');

    $tq_quizzes = (int) $this->db->query(
        "SELECT COUNT(*) AS n FROM quiz_results
          WHERE user_id = ? AND is_submitted = 1 AND date_added >= ?",
        [$tq_cid, $tq_week_start]
    )->row('n');

    /* المادة المتوقفة: أطول غياب بين مواده.
       ومادة لم تبدأ (`last_seen = 0`) تسبق كل متوقفة في الترتيب فتحجبها
       دائما — والانقطاع عن مادة بدأها خبر، وعدم البدء حال معلومة. فتقدم
       المتوقفة، وتذكر غير المبدوءة حين لا متوقف. */
    $tq_stalled = $this->db->query(
        "SELECT c.title, COALESCE(w.date_updated, 0) AS last_seen
           FROM enrol e
           JOIN course c ON c.id = e.course_id
           LEFT JOIN watch_histories w
                  ON w.student_id = e.user_id AND w.course_id = e.course_id
          WHERE e.user_id = ?
          ORDER BY (COALESCE(w.date_updated, 0) = 0) ASC, last_seen ASC
          LIMIT 1",
        [$tq_cid]
    )->row_array();

    $tq_child['days_this'] = $tq_days_this;
    $tq_child['days_prev'] = $tq_days_prev;
    $tq_child['done']      = $tq_done;
    $tq_child['done_all']  = $tq_done_all;
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

                /* الرموز من مكتبة أشكال الثيم لا من الإيموجي.
                   الإيموجي يرسمه كل نظام بشكل ولون غير الآخر، فينكسر
                   الطابع الهادئ للبوابة كلها بأربع صور صفراء لا تخضع
                   لتوكنات الألوان ولا تعرف الوضع الداكن.
                   [أيقونة، عائلة اللون، النص] */
                $tq_lines = [];

                /* الجملة تبنى نفيا لا بحشو «لا شيء» في موضع المفعول:
                   «أنهى لا دروس» ليست عربية. والفعل نفسه ينفى. */
                $tq_l1 = (int) $tq_c['done'] > 0
                    ? 'أنهى ' . tq_lessons_word((int) $tq_c['done'])
                    : 'لم ينه درسا';
                $tq_l1 .= (int) $tq_c['quizzes'] > 0
                    ? ' وسلم ' . tq_exams_word((int) $tq_c['quizzes'])
                    : ' ولم يسلم اختبارا';

                $tq_lines[] = [
                    ((int) $tq_c['done'] > 0 || (int) $tq_c['quizzes'] > 0) ? 'check-badge' : 'clock',
                    ((int) $tq_c['done'] > 0 || (int) $tq_c['quizzes'] > 0) ? 'mint' : 'sand',
                    $tq_l1 . ' هذا الأسبوع',
                ];

                $tq_lines[] = ['chart',
                    $tq_c['days_this'] > $tq_c['days_prev'] ? 'mint'
                        : ($tq_c['days_this'] < $tq_c['days_prev'] ? 'peach' : 'sky'),
                    $tq_c['days_this'] > $tq_c['days_prev']
                        ? 'نشاطه ارتفع من ' . tq_days($tq_c['days_prev'], 'صفر') . ' إلى ' . tq_days($tq_c['days_this']) . ' مقارنة بأسبوعه الماضي'
                        : ($tq_c['days_this'] < $tq_c['days_prev']
                            ? 'نشاطه نزل من ' . tq_days($tq_c['days_prev']) . ' إلى ' . tq_days($tq_c['days_this'], 'صفر') . ' مقارنة بأسبوعه الماضي'
                            : ($tq_c['days_this'] > 0
                                ? 'نشاطه ثابت عند ' . tq_days($tq_c['days_this']) . ' كأسبوعه الماضي'
                                : 'لم يدرس في هذه الأيام ولا في مثلها من أسبوعه الماضي'))];

                if (!empty($tq_c['stalled'])) {
                    $tq_gap = (int) $tq_c['stalled']['last_seen'] > 0
                        ? (int) floor((time() - (int) $tq_c['stalled']['last_seen']) / 86400)
                        : null;
                    /* «لم يفتح المنصة» كانت خطأ في المرجع: المتوقف مادة
                       بعينها لا المنصة كلها، وقد يكون نشطا في غيرها. */
                    $tq_lines[] = ['clock', 'peach', $tq_gap === null
                        ? $tq_c['stalled']['title'] . ': لم يبدأها بعد'
                        : ($tq_gap < 1
                            ? $tq_c['stalled']['title'] . ': أقل مواده نشاطا، وآخر عهده بها اليوم'
                            : $tq_c['stalled']['title'] . ': لم يفتحها منذ ' . tq_days($tq_gap))];
                }

                $tq_plan_days = (int) $tq_c['plan_days'];
                $tq_need = max(0, $tq_plan_days - (int) $tq_c['days_this']);
                $tq_plan_note = $tq_c['plan_is_default']
                    ? ' (خطة أسبوعه غير محددة، فالحساب على ' . tq_days($tq_plan_days) . ' افتراضيا)'
                    : '';
                /* «يومان باقيان» مرفوعان لأنهما مبتدأ الجملة، و«من خطة
                   يومين» مجروران بحرف الجر — والمثنى وحده يفرق بينهما. */
                $tq_lines[] = ['target', $tq_need > 0 ? 'lilac' : 'mint', $tq_need > 0
                    ? 'المقترح: ' . tq_days($tq_need, 'لا يوم', 'nom')
                        . ($tq_need === 2 ? ' باقيان' : ($tq_need === 1 ? ' باق' : ' باقية'))
                        . ' من خطة ' . tq_days($tq_plan_days) . $tq_plan_note
                    : 'المقترح: أتم خطة أسبوعه — يكفي أن يحافظ على هذا الإيقاع' . $tq_plan_note];
                ?>

                <article class="tq-card tq-card--panel tq-section">
                    <h2 class="tq-h2" style="margin-block-end:var(--tq-space-l)">
                        <?php echo html_escape($tq_first); ?> هذا الأسبوع:
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
                        <?php echo tq_iso('حصيلته منذ البداية ' . tq_lessons_word((int) $tq_c['done_all'], 'لا دروس بعد', 'nom')
                            . '. وبقي من هذا الأسبوع ' . tq_days($tq_days_left, 'يومه الأخير', 'nom') . '.'); ?>
                    </p>

                    <a class="tq-btn tq-btn--secondary tq-btn--sm" style="margin-block-start:var(--tq-space-l)"
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
                    بعد الربط يصلك كل أحد صباحا تقرير من أربعة أسطر: ماذا أنجز هذا الأسبوع،
                    وما الذي تحسن، وما الذي يقلق، وما الخطوة الصغيرة التي تكفي هذا الأسبوع.
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
                وما لا يستحق المقاطعة ينتظر هذا التقرير بدل أن يقطع يومك بإشعار.
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">لماذا أربعة أسطر</h2></div>
            <p class="tq-caption">
                التقرير الطويل لا يقرأ، وما لا يقرأ لا يغير شيئا.
                أربعة أسطر تكفي لتعرف أين ابنك وماذا تفعل اليوم.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
