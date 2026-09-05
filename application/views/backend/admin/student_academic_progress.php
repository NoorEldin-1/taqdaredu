<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تقدم الطلاب في الكورس.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وأعطالها:
 *
 * ١ — **بالإنجليزية** كاملة: «Student» و«Progress» و«Not started yet»
 *     و«Completed lesson … out of» — وهي جمل تركب من مفتاحين ونص، فلا
 *     تترجم بترجمة المفاتيح وحدها.
 * ٢ — **أربعة استعلامات لكل صف**: المستخدم، وسجل المشاهدة، ومدد
 *     المشاهدة، ودروس الكورس (وهذه الأخيرة كانت خارج الحلقة). صار
 *     الجلب مجمعا بثلاثة استعلامات للجدول كله.
 * ٣ — **`$student['first_name']` بلا فحص** — تسجيل لمستخدم حذف يقرأ
 *     فهرسا من `null` فيبيض الخانة بتحذير.
 * ٤ — **زر الشهادة مشروط بـ`addon_status('certificate')`** وهي كاذبة
 *     أبدا في هذا التركيب (لا إضافات مثبتة) — شيفرة ميتة.
 * ٥ — **`$('[data-toggle=tooltip]').tooltip()`** ونصوص التلميح
 *     بالإنجليزية، و`btn-group` و`progress` من Bootstrap وسط شاشة
 *     `tqa-*`.
 * ٦ — **«أجراءات»** بألف قطع في ترويسة العمود، وصوابها «إجراءات».
 */
$tq_cid  = (int) $course_details['id'];
$tq_rows = $this->db->where('course_id', $tq_cid)
                    ->order_by('date_added', 'DESC')->get('enrol')->result_array();

$tq_total = (int) $this->db->where('course_id', $tq_cid)->count_all_results('lesson');

$tq_users = array();
$tq_watch = array();
$tq_secs  = array();

if ($tq_rows) {
    $tq_uids = array();
    foreach ($tq_rows as $tq_r) $tq_uids[] = (int) $tq_r['user_id'];
    $tq_uids = array_values(array_unique($tq_uids));

    foreach ($this->db->select('id, first_name, last_name, email')
                      ->where_in('id', $tq_uids)->get('users')->result_array() as $tq_u) {
        $tq_users[(int) $tq_u['id']] = $tq_u;
    }

    foreach ($this->db->where('course_id', $tq_cid)->where_in('student_id', $tq_uids)
                      ->get('watch_histories')->result_array() as $tq_w) {
        $tq_watch[(int) $tq_w['student_id']] = $tq_w;
    }

    /* مدة المشاهدة: خمس ثوان لكل عداد. تجمع هنا بصف لكل طالب بدل
       استعلام داخل الحلقة. */
    foreach ($this->db->where('watched_course_id', $tq_cid)
                      ->where_in('watched_student_id', $tq_uids)
                      ->get('watched_duration')->result_array() as $tq_d) {
        $tq_c = json_decode((string) $tq_d['watched_counter'], true);
        $tq_secs[(int) $tq_d['watched_student_id']] =
            ($tq_secs[(int) $tq_d['watched_student_id']] ?? 0) + (is_array($tq_c) ? count($tq_c) * 5 : 0);
    }
}
?>

<div class="tqa-card tqa-card--flush">
<?php if (!$tq_rows): ?>

    <?php tqa_empty(
        t('لا طالب في هذا الكورس بعد'),
        t('يظهر هنا تقدم كل مسجل: ما أكمله من دروس، ومتى فتحه آخر مرة، وكم شاهد.'),
        '', '', 'chart'
    ); ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <caption class="tqa-sr"><?php echo t('تقدم الطلاب: الاسم والتواريخ ونسبة الإكمال'); ?></caption>
            <thead>
                <tr>
                    <th><?php echo t('الطالب'); ?></th>
                    <th><?php echo t('التواريخ'); ?></th>
                    <th><?php echo t('التقدم'); ?></th>
                    <th style="inline-size:140px"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tq_rows as $tq_e):
                $tq_uid  = (int) $tq_e['user_id'];
                $tq_u    = $tq_users[$tq_uid] ?? null;
                $tq_w    = $tq_watch[$tq_uid] ?? null;

                $tq_done = json_decode((string) ($tq_w['completed_lesson'] ?? ''), true);
                $tq_done = is_array($tq_done) ? count($tq_done) : 0;
                $tq_pct  = (int) ($tq_w['course_progress'] ?? 0);
            ?>
                <tr>
                    <td data-label="<?php echo te('الطالب'); ?>">
                        <?php if ($tq_u): ?>
                            <span class="tqa-media__title">
                                <?php echo html_escape(trim($tq_u['first_name'] . ' ' . $tq_u['last_name']) ?: $tq_u['email']); ?>
                            </span>
                            <span class="tqa-media__sub"><?php echo html_escape($tq_u['email']); ?></span>
                        <?php else: ?>
                            <span class="tqa-dim"><?php echo t('حساب محذوف ('); ?><span class="tqa-num"><?php echo $tq_uid; ?></span>)</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="<?php echo te('التواريخ'); ?>">
                        <span class="tqa-media__sub">
                            <?php echo t('سجل:'); ?> <?php echo tqa_when($tq_e['date_added'], 'Y-m-d'); ?>
                        </span>
                        <span class="tqa-media__sub">
                            <?php echo t('آخر فتح:'); ?>
                            <?php echo !empty($tq_w['date_updated'])
                                ? tqa_when($tq_w['date_updated'], 'Y-m-d')
                                : t('<span class="tqa-dim">لم يبدأ بعد</span>'); ?>
                        </span>
                        <span class="tqa-media__sub">
                            <?php echo t('أكمله:'); ?>
                            <?php echo !empty($tq_w['completed_date'])
                                ? tqa_when($tq_w['completed_date'], 'Y-m-d')
                                : t('<span class="tqa-dim">لم يكمل بعد</span>'); ?>
                        </span>
                    </td>

                    <td data-label="<?php echo te('التقدم'); ?>">
                        <?php /* `tqa-bar` مكون قائم في [admin.css] — لا أنماط في السطر
                                 بألوان مكتوبة خارج التوكنات. */ ?>
                        <div class="tqa-bar" style="inline-size:160px;max-inline-size:100%">
                            <div class="tqa-bar__fill"
                                 style="inline-size:<?php echo max(0, min(100, $tq_pct)); ?>%"></div>
                        </div>
                        <span class="tqa-media__sub">
                            <span class="tqa-num"><?php echo $tq_pct; ?>%</span> ·
                            <span class="tqa-num"><?php echo $tq_done; ?></span>
                            <?php echo t('من'); ?> <span class="tqa-num"><?php echo $tq_total; ?></span> <?php echo t('درسا'); ?>
                        </span>
                        <span class="tqa-media__sub">
                            <?php echo t('شاهد'); ?> <?php echo html_escape(seconds_to_time_format($tq_secs[$tq_uid] ?? 0)); ?>
                        </span>
                    </td>

                    <td data-label="<?php echo te('إجراءات'); ?>">
                        <div class="tqa-rowacts">
                            <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                    onclick="showLargeModal('<?php echo site_url('admin/student_academic_quiz_result/' . $tq_cid . '/' . $tq_uid); ?>', 'نتائج الاختبارات')">
                                <?php echo tq_icon('clipboard', 14); ?> <?php echo t('الاختبارات'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>
</div>
