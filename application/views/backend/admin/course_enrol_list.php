<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * المسجلون في الكورس.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وأعطالها:
 *
 * ١ — **بالإنجليزية كاملة** وسط لوحة عربية: «Enrol student list» و
 *     «Export CSV» و«Lifetime access» و«no_data_found» — `get_phrase`
 *     لا تجد لها ترجمة فترد المفتاح حرفا.
 * ٢ — **استعلام لكل صف.** `get_where('users', ...)` داخل الحلقة،
 *     و`get_user_image_url()` معها. صار الجلب مجمعا بصفين.
 * ٣ — **`$enrol_history->result_array()` تنادى ثلاث مرات** — مرة للعد،
 *     ومرة للحلقة، ومرة للعد ثانية.
 * ٤ — **تصدير CSV بجافاسكربت يجمع معرفات الصفوف المعروضة** ويرسلها في
 *     `POST`، ثم يبني `Blob` وينقر رابطا. وهو طريق طويل إلى ملف: الرابط
 *     المباشر يصدر الكل بلا جافاسكربت، ولا يسقط صامتا إن تعثر النداء
 *     (وكان `error` منه يكتب في `console` وحدها فلا يرى المستخدم شيئا).
 * ٥ — **`$user_data['first_name']` بلا فحص** — تسجيل لمستخدم حذف يقرأ
 *     فهرسا من `null`.
 */
$tq_rows = $enrol_history->result_array();

$tq_users = array();
if ($tq_rows) {
    $tq_uids = array();
    foreach ($tq_rows as $tq_r) $tq_uids[] = (int) $tq_r['user_id'];
    foreach ($this->db->select('id, first_name, last_name, email')
                      ->where_in('id', array_unique($tq_uids))
                      ->get('users')->result_array() as $tq_u) {
        $tq_users[(int) $tq_u['id']] = $tq_u;
    }
}
?>

<div class="tqa-toolbar">
    <a class="tqa-btn tqa-btn--ghost"
       href="<?php echo site_url('admin/export_enrol_history_csv/' . (int) $course_id); ?>">
        <?php echo tq_icon('download', 16); ?> صدر جدولا
    </a>
</div>

<div class="tqa-card tqa-card--flush">
<?php if (!$tq_rows): ?>

    <?php tqa_empty(
        t('لا مسجل في هذا الكورس بعد'),
        t('يظهر هنا كل من سجل فيه: باشتراك في باقة تفتحه، أو بتسجيل مباشر من الإدارة.'),
        '', '', 'users'
    ); ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <caption class="tqa-sr"><?php echo t('المسجلون في الكورس: الاسم وتاريخ التسجيل ونهاية الوصول'); ?></caption>
            <thead>
                <tr>
                    <th><?php echo t('الطالب'); ?></th>
                    <th><?php echo t('سجل في'); ?></th>
                    <th><?php echo t('ينتهي وصوله'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tq_rows as $tq_e):
                $tq_u    = $tq_users[(int) $tq_e['user_id']] ?? null;
                $tq_name = $tq_u ? trim($tq_u['first_name'] . ' ' . $tq_u['last_name']) : '';
            ?>
                <tr>
                    <td data-label="الطالب">
                        <?php if ($tq_u): ?>
                            <span class="tqa-media__title"><?php echo html_escape($tq_name ?: $tq_u['email']); ?></span>
                            <span class="tqa-media__sub"><?php echo html_escape($tq_u['email']); ?></span>
                        <?php else: ?>
                            <span class="tqa-dim"><?php echo t('حساب محذوف ('); ?><span class="tqa-num"><?php echo (int) $tq_e['user_id']; ?></span>)</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="سجل في">
                        <?php echo tqa_when($tq_e['date_added'], 'Y-m-d'); ?>
                    </td>

                    <td data-label="ينتهي وصوله">
                        <?php if (!empty($tq_e['expiry_date'])): ?>
                            <?php echo tqa_when($tq_e['expiry_date'], 'Y-m-d'); ?>
                        <?php else: ?>
                            <span class="tqa-badge tqa-badge--ok"><?php echo t('وصول دائم'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="tqa-pager__info" style="padding:var(--tq-space-l) var(--tq-space-xl)">
        <span class="tqa-num"><?php echo count($tq_rows); ?></span> <?php echo t('مسجلا'); ?>
    </p>

<?php endif; ?>
</div>
