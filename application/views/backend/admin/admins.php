<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * المسؤولون.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما تغير في السلوك:
 *
 * ١ — **قائمة النقاط الثلاث صارت أزرارا.** كانت `dropright` — و«right»
 *     هنا حرفية لا منطقية، فتفتح القائمة في صفحة عربية إلى الجهة
 *     الخاطئة وتقص عند حافة الجدول.
 * ٢ — **الحذف صار نموذج POST بتوكن.** كان رابط GET ينفذ بمجرد جلبه.
 * ٣ — **تصدير CSV صار رابطا لا نداء AJAX.** كان يجمع المعرفات من الـDOM
 *     ويرسلها POST ثم يبني Blob في المتصفح: أي أنه **لا يصدر إلا الصفحة
 *     المعروضة**، ويسقط صامتا إن تعثر السكربت (المعالج يكتب الخطأ في
 *     الطرفية وحدها). والخادم يعرف قائمته كاملة فيصدرها هو.
 *
 * وشارة «مشرف الجذر» تبقى كما هي: هو الحساب الذي لا يعدل ولا يحذف، وإلا
 * أمكن لمسؤول أن يقفل اللوحة على الجميع.
 */
$tq_tools = '<a class="tqa-btn tqa-btn--primary" href="' . site_url('admin/admin_form/add_admin_form') . '">'
          . tq_icon('plus', 17) . ' إضافة مسؤول</a>'
          . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/export_admins_csv') . '">'
          . tq_icon('download', 16) . ' تصدير CSV</a>';
?>

<?php tqa_head('المسؤولون والصلاحيات',
    'جدول `permissions` فارغ اليوم — ومن لا صف له يرى كل شيء. اضبط صلاحيات كل مسؤول من زر «الصلاحيات».',
    'shield', $tq_tools); ?>

<div class="tqa-card tqa-card--flush">
<?php if (empty($admins)): ?>

    <?php tqa_empty('لا مسؤولين بعد', 'أضف أول حساب إدارة.', 'إضافة مسؤول',
        site_url('admin/admin_form/add_admin_form'), 'shield'); ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <caption class="tqa-sr">حسابات الإدارة وبيانات التواصل بها</caption>
            <thead>
                <tr>
                    <th style="inline-size:60px">#</th>
                    <th>المسؤول</th>
                    <th>البريد الإلكتروني</th>
                    <th>الهاتف</th>
                    <th style="inline-size:250px"><span class="tqa-sr">إجراءات</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $tq_k => $tq_u):
                $tq_id   = (int) $tq_u['id'];
                $tq_name = trim($tq_u['first_name'] . ' ' . $tq_u['last_name']);
                if ($tq_name === '') $tq_name = $tq_u['email'];
                $tq_root = is_root_admin($tq_id);
            ?>
                <tr>
                    <td data-label="#"><span class="tqa-num"><?php echo $tq_k + 1; ?></span></td>

                    <td data-label="المسؤول">
                        <span class="tqa-media">
                            <img class="tqa-avatar" alt="" width="38" height="38" loading="lazy"
                                 src="<?php echo html_escape($this->user_model->get_user_image_url($tq_id)); ?>">
                            <span class="tqa-media__body">
                                <span class="tqa-media__title"><?php echo html_escape($tq_name); ?></span>
                                <?php if ($tq_root): ?>
                                    <span class="tqa-media__sub">مشرف الجذر — لا يعدل ولا يحذف</span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </td>

                    <td data-label="البريد الإلكتروني">
                        <span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_u['email']); ?></span>
                    </td>

                    <td data-label="الهاتف">
                        <?php if (trim((string) $tq_u['phone']) !== ''): ?>
                            <span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_u['phone']); ?></span>
                        <?php else: ?>
                            <span class="tqa-dim">—</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="إجراءات">
                        <?php if ($tq_root): ?>
                            <span class="tqa-badge tqa-badge--ok">
                                <?php echo tq_icon('shield', 13); ?> مشرف الجذر
                            </span>
                        <?php else: ?>
                            <div class="tqa-rowacts">
                                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                   href="<?php echo site_url('admin/permissions?permission_assing_to=' . $tq_id); ?>">
                                    <?php echo tq_icon('key', 14); ?> الصلاحيات
                                </a>

                                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                   href="<?php echo site_url('admin/admin_form/edit_admin_form/' . $tq_id); ?>">
                                    <?php echo tq_icon('edit', 14); ?> تعديل
                                </a>

                                <form method="post" action="<?php echo site_url('admin/admins/delete/' . $tq_id); ?>"
                                      data-tqa-confirm-title="حذف المسؤول"
                                      data-tqa-confirm="سيحذف حساب «<?php echo html_escape($tq_name); ?>» ولن يستطيع الدخول بعدها."
                                      data-tqa-confirm-ok="نعم، احذف"
                                      data-tqa-confirm-tone="danger">
                                    <?php echo tq_csrf(); ?>
                                    <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                            style="color:var(--tq-danger)">
                                        <?php echo tq_icon('trash', 14); ?>
                                        <span class="tqa-sr">حذف <?php echo html_escape($tq_name); ?></span>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p style="padding:var(--tq-space-m) var(--tq-space-xl);margin:0;border-block-start:1px solid var(--tq-line);
              font:var(--tq-type-caption);color:var(--tq-text2)">
        <span class="tqa-num"><?php echo count($admins); ?></span> حساب إدارة.
    </p>

<?php endif; ?>
</div>
