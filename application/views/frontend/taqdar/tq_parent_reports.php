<?php
/**
 * بوّابة وليّ الأمر — التقارير.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية — كل شيء واضح ومفهوم من
 * نظرة واحدة وبلا مصطلحات. ولذلك التقرير هنا صفوف قليلة بعناوين بشرية:
 * «ما أنهاه» و«نتائجه» و«آخر نشاط» — لا «معدّل تراكمي» ولا «مؤشّر أداء».
 *
 * حاجز الرؤية نفسه مطبَّق: لا استعلام على محادثات المساعد الذكي، ولا على
 * منشورات المجتمع، ولا على إجابات الطالب المفردة — نعرض المتقن والمتبقّي،
 * لا كل خطأ على حدة. «الرقابة الكاملة تُنتج طالبًا يُخفي، لا طالبًا يتعلّم.»
 *
 * ما ينتظر جدولًا:
 *   `parent_links` — ربط الوليّ بابنه (وبدونه لا تُفتح بيانات أحد)
 *   `objectives`   — «الإتقان» الحقيقي: هدف متقن من هدف مفتوح.
 *                    والمعروض اليوم بديله المتاح: ما أنهاه من دروس المادة.
 */

$tq_nav   = 'reports';
$tq_role  = 'parent';
$tq_title = 'التقارير';
$tq_sub   = 'كل مادة في سطر واحد';
$tq_icon  = 'chart';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_children = [];
if ($this->db->table_exists('parent_links')) {
    $tq_children = $this->db->query(
        "SELECT u.id, u.first_name, u.last_name, u.image
           FROM parent_links pl
           JOIN users u ON u.id = pl.student_id
          WHERE pl.parent_user_id = ? AND pl.status = 'active'
          ORDER BY u.first_name ASC",
        [$tq_uid]
    )->result_array();
}

/* لكل ابن: صفّ لكل مادة، من `enrol` و`watch_histories` و`quiz_results`. */
foreach ($tq_children as &$tq_child) {
    $tq_child['subjects'] = $this->db->query(
        "SELECT c.id, c.title,
                COALESCE(w.course_progress, 0) AS progress,
                COALESCE(w.date_updated, 0)    AS last_seen,
                (SELECT COUNT(*) FROM lesson l WHERE l.course_id = c.id) AS lessons
           FROM enrol e
           JOIN course c ON c.id = e.course_id
           LEFT JOIN watch_histories w
                  ON w.student_id = e.user_id AND w.course_id = e.course_id
          WHERE e.user_id = ?
          ORDER BY c.title ASC",
        [(int) $tq_child['id']]
    )->result_array();

    $tq_scores = [];
    foreach ($this->db->query(
        "SELECT l.course_id,
                COUNT(*) AS attempts,
                COALESCE(AVG(
                  CASE WHEN (SELECT COUNT(*) FROM question q WHERE q.quiz_id = r.quiz_id) > 0
                       THEN 100 * r.total_obtained_marks /
                            (SELECT COUNT(*) FROM question q WHERE q.quiz_id = r.quiz_id)
                       ELSE NULL END), 0) AS avg_pct
           FROM quiz_results r
           JOIN lesson l ON l.id = r.quiz_id
          WHERE r.user_id = ? AND r.is_submitted = 1
          GROUP BY l.course_id",
        [(int) $tq_child['id']]
    )->result_array() as $tq_s) {
        $tq_scores[(int) $tq_s['course_id']] = $tq_s;
    }

    foreach ($tq_child['subjects'] as &$tq_sub_row) {
        $tq_key = (int) $tq_sub_row['id'];
        $tq_sub_row['attempts'] = (int) ($tq_scores[$tq_key]['attempts'] ?? 0);
        $tq_sub_row['avg_pct']  = (int) round((float) ($tq_scores[$tq_key]['avg_pct'] ?? 0));
    }
    unset($tq_sub_row);
}
unset($tq_child);

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>
        <?php if ($tq_children): ?>

            <?php foreach ($tq_children as $tq_child): ?>
                <?php $tq_name = trim($tq_child['first_name'] . ' ' . $tq_child['last_name']); ?>
                <section class="tq-section" aria-labelledby="tq-child-<?php echo (int) $tq_child['id']; ?>">
                    <div class="tq-sectionhead">
                        <h2 id="tq-child-<?php echo (int) $tq_child['id']; ?>"><?php echo html_escape($tq_name); ?></h2>
                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                           href="<?php echo base_url('parent/child'); ?>?id=<?php echo (int) $tq_child['id']; ?>">التفاصيل</a>
                    </div>

                    <?php if ($tq_child['subjects']): ?>
                        <div class="tq-card">
                            <table class="tq-table">
                                <caption class="tq-sr">مواد <?php echo html_escape($tq_name); ?>: ما أنهاه ونتائجه وآخر نشاط</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">المادة</th>
                                        <th scope="col">ما أنهاه</th>
                                        <th scope="col">نتائج الاختبارات</th>
                                        <th scope="col">آخر نشاط</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tq_child['subjects'] as $tq_s): ?>
                                        <tr>
                                            <td data-label="المادة">
                                                <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_s['title']); ?></span>
                                                <span class="tq-micro" style="display:block"><?php echo tq_iso($tq_s['lessons'] . ' دروس'); ?></span>
                                            </td>
                                            <td data-label="ما أنهاه" style="min-inline-size:180px">
                                                <?php echo tq_progress((int) $tq_s['progress'], 'ما أنهاه في ' . $tq_s['title']); ?>
                                            </td>
                                            <td data-label="نتائج الاختبارات">
                                                <?php if ($tq_s['attempts'] > 0): ?>
                                                    <?php echo tq_num($tq_s['avg_pct'] . '%', 'tq-num--sm'); ?>
                                                    <span class="tq-micro" style="display:block"><?php echo tq_iso('من ' . $tq_s['attempts'] . ' اختبار'); ?></span>
                                                <?php else: ?>
                                                    <span class="tq-caption">لم يبدأ اختبارًا</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="آخر نشاط">
                                                <?php echo (int) $tq_s['last_seen'] > 0
                                                    ? html_escape(tq_since((int) $tq_s['last_seen']))
                                                    : '<span class="tq-caption">لم يبدأ بعد</span>'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="tq-card tq-empty">
                            <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('book', 24); ?></span>
                            <h3 class="tq-empty__title">لا مواد مسجّلة لـ<?php echo html_escape(explode(' ', $tq_name)[0]); ?></h3>
                            <p class="tq-empty__text">حين يُسجَّل في مادة يظهر تقريرها هنا في سطر واحد.</p>
                            <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('parent/payments'); ?>">المدفوعات</a>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

        <?php else: ?>

            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('chart', 24); ?></span>
                <h2 class="tq-empty__title">لا تقارير قبل ربط حساب ابنك</h2>
                <p class="tq-empty__text">
                    بعد الربط تجد هنا كل مادة في سطر واحد: ما أنهاه منها، ونتائج اختباراته فيها،
                    ومتى كان آخر نشاط له. بلا مصطلحات ولا جداول طويلة.
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>">اربط حساب ابنك</a>
            </div>

        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--sky">
            <span class="tq-pastel__label tq-micro">كيف تقرأ التقرير</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                انظر إلى «آخر نشاط» أوّلًا: الانقطاع يسبق تراجع النتائج دائمًا، ومعالجته أسهل.
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">التقرير الأسبوعي</h2></div>
            <p class="tq-caption">
                إن أردت الخلاصة وحدها، يصلك كل أحد تقرير من أربعة أسطر تُقرأ في عشر ثوانٍ.
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--block tq-btn--sm"
               href="<?php echo base_url('parent/weekly'); ?>">عرض التقرير الأسبوعي</a>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
