<?php
/**
 * بوابة المعلم — اللوحة.
 *
 * القاعدة الحاكمة لبوابة المعلم كلها:
 * المعلم مسند إلى مادة وصف بعينهما، وما لم يسند إليه لا يظهر في لوحته
 * أصلا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زر في الواجهة ليس صلاحية.
 *
 * الإسناد الصريح (معلم × مادة × صف) ينتظر جدول `teacher_assignments`.
 * وإلى أن يوجد، النطاق الحقيقي المتاح هو ملكية الكورس نفسها:
 * `course.creator` أو ورود معرف المعلم في `course.user_id`.
 * كل استعلام في هذه الصفحة يبدأ من هذه القائمة ولا يتجاوزها.
 */

$tq_nav   = 'dashboard';
$tq_role  = 'teacher';
$tq_title = t('لوحة المعلم');
$tq_sub   = t('ما يحتاج انتباهك اليوم، قبل أي رقم آخر');
$tq_icon  = 'home';

$tq_uid = (int) $this->session->userdata('user_id');

/* النماذج تحمل عبر get_instance(): العارض في CI3 ينسخ خصائص المتحكم إلى
   المحمل مرة واحدة قبل التصيير، فما حمل بعد بدء التصيير لا يظهر في `$this`. */
$tq_CI = get_instance();
$tq_CI->load->model('taqdar_marking_model');
$tq_mark = $tq_CI->taqdar_marking_model;

/* ---- عتبة النجاح: من نموذج التصحيح لا من استعلام في العرض -----------
   كانت تقرأ هنا من `settings.tq_pass_threshold`، ويقرأها
   `Taqdar_marking_model` من `marking_pass_percent` — مفتاحان لعتبة واحدة.
   فمن ضبط أحدهما جعل اللوحة تحسب الرسوب بعتبة وشاشة التصحيح بأخرى.
   والقراءة الآن من موضع واحد يقبل المفتاحين. */
$tq_pass_pct   = $tq_mark->pass_percent();
$tq_pass_ratio = $tq_pass_pct / 100;

/* ---- النطاق: كورسات هذا المعلم وحدها ------------------------------- */
$tq_my_courses = $this->db
    ->select('id, title, status, date_added')
    ->group_start()
        ->where('creator', $tq_uid)
        ->or_where('FIND_IN_SET(' . $tq_uid . ', user_id) >', 0, false)
    ->group_end()
    ->order_by('date_added', 'DESC')
    ->get('course')->result_array();

$tq_course_ids = array_map('intval', array_column($tq_my_courses, 'id'));
$tq_in         = implode(',', $tq_course_ids);

$tq_students         = 0;
$tq_pending_marking  = 0;
$tq_pending_quizzes  = 0;
$tq_pending_homework = 0;
$tq_month_revenue   = 0;
$tq_attention       = [];
$tq_attention_total = 0;
$tq_hard_lessons    = [];

if ($tq_in !== '') {

    $tq_students = (int) $this->db->query(
        "SELECT COUNT(DISTINCT user_id) AS n FROM enrol WHERE course_id IN ($tq_in)"
    )->row('n');

    /* صف التصحيح — من `Taqdar_marking_model` لا من استعلام هنا.
       كان العد هنا يشمل المعتمد أيضا (بلا شرط `approved_at IS NULL`)،
       فتقول البطاقة رقما وتفتح شاشة التصحيح على رقم أصغر منه بكثير:
       المعلم يرسل إلى صف قصره هو بنفسه. والمصدر الآن واحد. */
    /* الصفان معا: اختبارات وواجبات. الأول من `quiz_results` والثاني من
       `attempts`، وكلاهما ينتظر المعلم — فبطاقة تعد أحدهما تخفي الآخر. */
    $tq_pending_quizzes  = $tq_mark->queue_count($tq_uid);
    $tq_pending_homework = $tq_mark->homework_queue_count($tq_uid);
    $tq_pending_marking  = $tq_pending_quizzes + $tq_pending_homework;

    /* أرباح الشهر من دفتر المحفظة لا من `payment`.
       كانت تجمع `payment.instructor_revenue` — وهي الطريقة التي هجرتها
       شاشة المحفظة لأنها لا ترى استردادا ولا تسوية، فيفترق الرقمان عن
       الشهر نفسه في شاشتين. والدفتر مصدر واحد لهما الآن.
       والمبلغ بالهللات، والقسمة على مئة حد عرض أخير لا حساب. */
    $tq_CI->load->model('taqdar_wallet_model');
    $tq_month_revenue = $tq_CI->taqdar_wallet_model->month_earnings($tq_uid) / 100;

    /* ---- «يحتاج انتباهك»: الطلاب المتعثرون مرتبين بالأولوية -------
       صف لكل (طالب × كورس): الطالب المسجل في ثلاثة من كورساتي يظهر ثلاث
       مرات، فتمتلئ قائمة الستة بطالبين. والاختيار الآن أعلى صف لكل طالب،
       والباقي يذكر عددا لا يكرر. */
    $tq_rows = $this->db->query(
        "SELECT u.id, u.first_name, u.last_name, u.image,
                c.id AS course_id, c.title AS course_title,
                COALESCE(w.course_progress, 0) AS progress,
                COALESCE(w.date_updated, e.date_added) AS last_seen
           FROM enrol e
           JOIN users u  ON u.id = e.user_id
           JOIN course c ON c.id = e.course_id
           LEFT JOIN watch_histories w
                  ON w.student_id = e.user_id AND w.course_id = e.course_id
          WHERE e.course_id IN ($tq_in)"
    )->result_array();

    /* الرسوب يقاس بعتبة `$tq_pass_pct` من عدد أسئلة الاختبار.
       والتجميع بـ(طالب × كورس) لا بالطالب وحده: كان مجموع رسوبه في
       كورساتي كلها يعلق على صف كل كورس منها، فيقرأ المعلم عن طالب
       «رسب في 7 اختبار» في مادة اختباراتها ثلاثة — رقم صحيح في غير موضعه.
       وعتبة لكل هدف على حدة تنتظر `objectives`. */
    $tq_fail_map = [];
    foreach ($this->db->query(
        "SELECT r.user_id, l.course_id,
                COUNT(*) AS attempts,
                SUM(CASE WHEN r.total_obtained_marks <
                     (SELECT COUNT(*) FROM question q WHERE q.quiz_id = r.quiz_id) * ?
                    THEN 1 ELSE 0 END) AS fails
           FROM quiz_results r
           JOIN lesson l ON l.id = r.quiz_id
          WHERE r.is_submitted = 1 AND l.course_id IN ($tq_in)
          GROUP BY r.user_id, l.course_id",
        [$tq_pass_ratio]
    )->result_array() as $tq_f) {
        $tq_fail_map[(int) $tq_f['user_id'] . ':' . (int) $tq_f['course_id']] = $tq_f;
    }

    foreach ($tq_rows as $tq_r) {
        $tq_days  = (int) floor((time() - (int) $tq_r['last_seen']) / 86400);
        $tq_days  = max(0, $tq_days);
        $tq_prog  = (int) $tq_r['progress'];
        $tq_key   = (int) $tq_r['id'] . ':' . (int) $tq_r['course_id'];
        $tq_fails = (int) ($tq_fail_map[$tq_key]['fails'] ?? 0);

        /* الأولوية: الانقطاع أثقل من بطء التقدم، والرسوب المتكرر أثقل منهما. */
        $tq_score = min($tq_days, 21) * 4 + (100 - $tq_prog) * 0.3 + $tq_fails * 12;

        if ($tq_days < 3 && $tq_fails === 0 && $tq_prog >= 40) {
            continue; // لا شيء يستدعي المقاطعة
        }

        $tq_reason = $tq_fails > 0
            ? t('رسب في ') . tq_exams_word($tq_fails)
            : ($tq_days >= 5
                ? t('انقطع ') . tq_days($tq_days)
                : t('تقدمه ') . TQ_LRI . $tq_prog . '%' . TQ_PDI . t(' فقط'));

        $tq_r['days']   = $tq_days;
        $tq_r['fails']  = $tq_fails;
        $tq_r['reason'] = $tq_reason;
        $tq_r['score']  = $tq_score;
        $tq_attention[] = $tq_r;
    }

    usort($tq_attention, static function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    /* طالب واحد مرة واحدة، بأثقل كورساته حالا — والاسم المكرر ست مرات
       يخفي خمسة طلاب متعثرين خلف واحد. */
    $tq_seen_student = [];
    $tq_unique       = [];
    foreach ($tq_attention as $tq_a) {
        $tq_sid = (int) $tq_a['id'];
        if (isset($tq_seen_student[$tq_sid])) continue;
        $tq_seen_student[$tq_sid] = true;
        $tq_unique[] = $tq_a;
    }
    $tq_attention_total = count($tq_unique);
    $tq_attention       = array_slice($tq_unique, 0, 6);

    /* ---- الدروس عالية الفشل ----------------------------------------
       التجميع في استعلام داخلي والترتيب على نتيجته: ماريا دي بي ترفض
       `ORDER BY (fails / attempts)` على أسماء دوال تجميع
       (Reference 'fails' not supported)، وكان ذلك يرمي استثناء يقطع
       اللوحة عند أول معلم له نتائج اختبارات. */
    $tq_hard_lessons = $this->db->query(
        "SELECT t.id, t.title, t.course_title, t.attempts, t.fails
           FROM (
                SELECT l.id, l.title, c.title AS course_title,
                       COUNT(r.quiz_result_id) AS attempts,
                       SUM(CASE WHEN r.total_obtained_marks <
                            (SELECT COUNT(*) FROM question q WHERE q.quiz_id = l.id) * ?
                           THEN 1 ELSE 0 END) AS fails
                  FROM quiz_results r
                  JOIN lesson l  ON l.id = r.quiz_id
                  JOIN course c  ON c.id = l.course_id
                 WHERE r.is_submitted = 1 AND l.course_id IN ($tq_in)
                 GROUP BY l.id, l.title, c.title
           ) t
          WHERE t.attempts > 0 AND t.fails > 0
          ORDER BY (t.fails / t.attempts) DESC, t.attempts DESC
          LIMIT 5",
        [$tq_pass_ratio]
    )->result_array();
}

/* ---- الحصص القادمة: قراءة من `tutoring_sessions` لا كتابة فيه ------
   جدول الحصص تملكه شاشة الحصص، وهذه اللوحة تعرض ما فيه ولا تمسه.
   الموعد نفسه ليس في الحصة بل في شريحتها `availability_slots.starts_at`،
   فحصة بلا شريحة حصة بلا موعد ولا تعد «قادمة». والنافذة أسبوع واحد:
   ما بعده مستقبل بعيد لا شيء يفعل حياله اليوم.
   و`table_exists` لأن الجدول تحت يد وكيل آخر: غيابه يفرغ البطاقة
   ولا يسقط الصفحة. */
$tq_sessions       = [];
$tq_sessions_count = 0;

if ($this->db->table_exists('tutoring_sessions') && $this->db->table_exists('availability_slots')) {

    $tq_sessions = $this->db->query(
        "SELECT s.id, s.status, s.room_id,
                a.starts_at, a.duration_min,
                u.first_name, u.last_name
           FROM tutoring_sessions s
           JOIN availability_slots a ON a.id = s.slot_id
           LEFT JOIN users u         ON u.id = s.student_id
          WHERE s.teacher_id = ?
            AND s.status IN ('confirmed', 'live')
            AND a.starts_at >= NOW()
            AND a.starts_at <  DATE_ADD(NOW(), INTERVAL 7 DAY)
          ORDER BY a.starts_at ASC
          LIMIT 5",
        [$tq_uid]
    )->result_array();

    /* العداد لا يشتق من القائمة: القائمة محدودة بخمس والعد هو الحقيقة. */
    $tq_sessions_count = (int) $this->db->query(
        "SELECT COUNT(*) AS n
           FROM tutoring_sessions s
           JOIN availability_slots a ON a.id = s.slot_id
          WHERE s.teacher_id = ?
            AND s.status IN ('confirmed', 'live')
            AND a.starts_at >= NOW()
            AND a.starts_at <  DATE_ADD(NOW(), INTERVAL 7 DAY)",
        [$tq_uid]
    )->row('n');
}

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <!-- الأرقام الأربعة: كلها داخل نطاق كورساته وحدها -->
        <div class="tq-grid tq-grid--4 tq-stagger tq-section">
            <div class="tq-pastel tq-pastel--sky">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro"><?php echo t('طلابي'); ?></span>
                    <span class="tq-pastel__icon" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('users'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0"><?php echo tq_num($tq_students, 'tq-num--xl'); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0"><?php echo t('مسجلون في كورساتك'); ?></p>
            </div>

            <div class="tq-pastel tq-pastel--peach">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro"><?php echo t('ينتظر تصحيحا'); ?></span>
                    <span class="tq-pastel__icon" style="color:var(--tq-peach-ink)" aria-hidden="true"><?php echo tq_icon('clipboard'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0"><?php echo tq_num($tq_pending_marking, 'tq-num--xl'); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0">
                    <?php echo tq_iso($tq_pending_marking > 0
                        ? tq_exams_word($tq_pending_quizzes) . t(' و') . tq_homework_word($tq_pending_homework) . t(' تنتظر اعتمادك')
                        : t('لا شيء ينتظر اعتمادك')); ?>
                </p>
            </div>

            <div class="tq-pastel tq-pastel--mint">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro"><?php echo t('أرباح هذا الشهر'); ?></span>
                    <span class="tq-pastel__icon" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('wallet'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo tq_sar($tq_month_revenue); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0"><?php echo t('حصتك من مبيعات كورساتك'); ?></p>
            </div>

            <div class="tq-pastel tq-pastel--lilac">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro"><?php echo t('الحصص القادمة'); ?></span>
                    <span class="tq-pastel__icon" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('video'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0"><?php echo tq_num($tq_sessions_count, 'tq-num--xl'); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0"><?php echo t('حصة مؤكدة خلال سبعة أيام'); ?></p>
            </div>
        </div>

        <!-- أهم قسم في اللوحة: الطلاب المتعثرون مرتبين بالأولوية -->
        <section class="tq-section" aria-labelledby="tq-attention-h">
            <div class="tq-sectionhead">
                <h2 id="tq-attention-h"><?php echo t('يحتاج انتباهك'); ?></h2>
                <?php if ($tq_attention): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . $tq_attention_total . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_attention): ?>
                <div class="tq-card tq-card--float">
                    <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                        <?php echo t('مرتبون بالأولوية: الانقطاع أولا، ثم الرسوب المتكرر، ثم بطء التقدم. وكل طالب مرة واحدة بأثقل كورساته حالا.'); ?>
                    </p>
                    <ul class="tq-stack">
                        <?php foreach ($tq_attention as $tq_i => $tq_s): ?>
                            <?php
                            $tq_name  = trim($tq_s['first_name'] . ' ' . $tq_s['last_name']);
                            $tq_photo = tqs_person_img($tq_s['image']);
                            ?>
                            <li class="tq-row" style="gap:var(--tq-space-l);padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <img class="tq-avatar" src="<?php echo $tq_photo; ?>"
                                     alt="<?php echo te('صورة ____', array(html_escape($tq_name))); ?>">
                                <div style="flex:1;min-inline-size:0">
                                    <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_name); ?></p>
                                    <p class="tq-micro" style="margin:0"><?php echo html_escape($tq_s['course_title']); ?></p>
                                </div>
                                <div style="inline-size:180px">
                                    <?php echo tq_progress((int) $tq_s['progress'], t('تقدم ') . $tq_name); ?>
                                </div>
                                <?php echo tq_badge($tq_s['fails'] > 0 ? 'late' : ($tq_s['days'] >= 5 ? 'due' : 'idle'), $tq_s['reason']); ?>
                                <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                   href="<?php echo base_url('teacher/students'); ?>#student-<?php echo (int) $tq_s['id']; ?>">
                                    <?php echo t('تابعه'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php /* القائمة مقطوعة عند ستة: من لم يظهر يقال عدده ويفتح
                             له باب، لا يبتلع صامتا. */ ?>
                    <?php if ($tq_attention_total > count($tq_attention)): ?>
                        <p class="tq-micro" style="margin-block-start:var(--tq-space-l)">
                            <?php echo tq_iso(t(' و') . tq_students_word($tq_attention_total - count($tq_attention)) . t(' غيرهم يحتاجون انتباهك.')); ?>
                        </p>
                    <?php endif; ?>
                    <a class="tq-btn tq-btn--ghost tq-btn--block tq-btn--sm" style="margin-block-start:var(--tq-space-m)"
                       href="<?php echo base_url('teacher/students'); ?>"><?php echo t('قائمة طلابي كاملة'); ?></a>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('check', 24); ?></span>
                    <h3 class="tq-empty__title"><?php echo t('لا أحد متعثر الآن'); ?></h3>
                    <p class="tq-empty__text">
                        <?php echo t('حين ينقطع طالب أو يرسب في اختبار أو يتوقف تقدمه، سيظهر هنا مرتبا بالأولوية. القائمة تبنى من نشاط طلاب كورساتك وحدهم.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/students'); ?>"><?php echo t('افتح قائمة طلابي'); ?></a>
                </div>
            <?php endif; ?>
        </section>

        <!-- الدروس عالية الفشل: مشكلة الدرس لا مشكلة الطالب -->
        <section class="tq-section" aria-labelledby="tq-hard-h">
            <div class="tq-sectionhead">
                <h2 id="tq-hard-h"><?php echo t('الدروس عالية الفشل'); ?></h2>
            </div>

            <?php if ($tq_hard_lessons): ?>
                <div class="tq-card">
                    <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                        <?php echo t('درس يرسب فيه كثيرون مشكلته في الشرح غالبا لا في الطلاب — راجعه قبل أن تراجعهم. الرسوب هنا محسوب بعتبة النجاح المعتمدة في المنصة:'); ?> <?php echo TQ_LRI . $tq_pass_pct . '%' . TQ_PDI; ?>.
                    </p>
                    <div class="tq-table-wrap">
                        <table class="tq-table">
                            <caption class="tq-sr"><?php echo t('الدروس الأعلى نسبة رسوب في كورساتك'); ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo t('الدرس'); ?></th>
                                    <th scope="col"><?php echo t('الكورس'); ?></th>
                                    <th scope="col"><?php echo t('المحاولات'); ?></th>
                                    <th scope="col"><?php echo t('نسبة الرسوب'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tq_hard_lessons as $tq_l): ?>
                                    <?php $tq_rate = (int) round(100 * $tq_l['fails'] / max(1, (int) $tq_l['attempts'])); ?>
                                    <tr>
                                        <td data-label="<?php echo te('الدرس'); ?>"><span class="tq-strong"><?php echo html_escape($tq_l['title']); ?></span></td>
                                        <td data-label="<?php echo te('الكورس'); ?>"><?php echo html_escape($tq_l['course_title']); ?></td>
                                        <td data-label="<?php echo te('المحاولات'); ?>"><?php echo tq_num($tq_l['attempts'], 'tq-num--sm'); ?></td>
                                        <td data-label="<?php echo te('نسبة الرسوب'); ?>"><?php echo tq_badge('due', t('رسب ') . TQ_LRI . $tq_rate . '%' . TQ_PDI); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('chart', 24); ?></span>
                    <h3 class="tq-empty__title"><?php echo t('لا توجد نتائج اختبارات بعد'); ?></h3>
                    <p class="tq-empty__text">
                        <?php echo t('يظهر هنا الدرس الذي يرسب في اختباره أكثر طلابك، بعد أن يسلم طلابك أول اختباراتهم.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/questions'); ?>"><?php echo t('افتح بنك الأسئلة'); ?></a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('الحصص القادمة'); ?></h2></div>
            <?php if ($tq_sessions): ?>
                <ul class="tq-stack">
                    <?php foreach ($tq_sessions as $tq_v): ?>
                        <?php
                        $tq_at   = strtotime($tq_v['starts_at']);
                        $tq_who  = trim($tq_v['first_name'] . ' ' . $tq_v['last_name']);
                        if ($tq_who === '') {
                            $tq_who = t('طالب لم يعد في المنصة');
                        }
                        $tq_day  = date('Y-m-d', $tq_at);
                        $tq_when = $tq_day === date('Y-m-d')
                            ? t('اليوم')
                            : ($tq_day === date('Y-m-d', strtotime('+1 day'))
                                ? t('غدا')
                                : TQ_LRI . $tq_day . TQ_PDI);
                        ?>
                        <li class="tq-row" style="gap:var(--tq-space-m);padding-block:var(--tq-space-s)">
                            <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('video'); ?></span>
                            <span style="flex:1;min-inline-size:0">
                                <span class="tq-strong" style="display:block;color:var(--tq-navy)"><?php echo html_escape($tq_who); ?></span>
                                <span class="tq-micro">
                                    <?php echo $tq_when; ?> ·
                                    <?php echo TQ_LRI . date('H:i', $tq_at) . TQ_PDI; ?> ·
                                    <?php echo tq_iso(tq_minutes_word((int) $tq_v['duration_min'])); ?>
                                </span>
                            </span>
                            <?php echo $tq_v['status'] === 'live'
                                ? tq_badge('late', t('جارية الآن'))
                                : tq_badge('progress', t('مؤكدة')); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($tq_sessions_count > count($tq_sessions)): ?>
                    <p class="tq-micro" style="margin-block-start:var(--tq-space-m)">
                        <?php echo tq_iso(t(' و') . tq_sessions_word($tq_sessions_count - count($tq_sessions)) . t(' أخرى خلال الأسبوع.')); ?>
                    </p>
                <?php endif; ?>
                <a class="tq-btn tq-btn--ghost tq-btn--block tq-btn--sm" style="margin-block-start:var(--tq-space-l)"
                   href="<?php echo base_url('teacher/sessions'); ?>"><?php echo t('كل حصصي'); ?></a>
            <?php else: ?>
                <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                    <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('calendar', 24); ?></span>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)"><?php echo t('لا حصص مجدولة'); ?></h3>
                    <p class="tq-empty__text tq-caption">
                        <?php echo t('حين يحجز طالب حصة خاصة معك يظهر موعدها هنا، وتؤكدها أو تعتذر من صفحة الحصص.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('teacher/sessions'); ?>"><?php echo t('أوقاتي المتاحة'); ?></a>
                </div>
            <?php endif; ?>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('كورساتك'); ?></h2></div>
            <?php if ($tq_my_courses): ?>
                <ul class="tq-stack">
                    <?php foreach (array_slice($tq_my_courses, 0, 5) as $tq_i => $tq_c): ?>
                        <li class="tq-row" style="gap:var(--tq-space-m)">
                            <span class="tq-icon-box tq-pastel--<?php echo tq_pastel($tq_i); ?>" aria-hidden="true"><?php echo tq_icon('book'); ?></span>
                            <span style="flex:1;min-inline-size:0">
                                <span class="tq-strong" style="display:block;color:var(--tq-navy)"><?php echo html_escape($tq_c['title']); ?></span>
                                <span class="tq-micro"><?php echo html_escape(tq_since((int) $tq_c['date_added'])); ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="tq-btn tq-btn--ghost tq-btn--block tq-btn--sm" style="margin-block-start:var(--tq-space-l)"
                   href="<?php echo base_url('teacher/courses'); ?>"><?php echo t('كل كورساتي'); ?></a>
            <?php else: ?>
                <div class="tq-empty" style="padding:var(--tq-space-l) 0">
                    <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('book', 24); ?></span>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)"><?php echo t('لم يسند إليك كورس بعد'); ?></h3>
                    <p class="tq-empty__text tq-caption">
                        <?php echo t('لوحتك تعرض ما أسند إليك وحده. تواصل مع إدارة المنصة لإسناد مادتك وصفك.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('teacher/upload'); ?>"><?php echo t('ارفع درسا'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
