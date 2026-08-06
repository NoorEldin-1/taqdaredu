<?php
defined('BASEPATH') or exit('No direct script access allowed');
$M = &get_instance()->taqdar_admin_model;
$readonly = !empty($spec['readonly']);
?>

<div class="tqa-head">
    <div>
        <h1><?php echo html_escape($spec['title']); ?></h1>
        <p><?php echo html_escape($spec['lead']); ?></p>
    </div>
    <?php if (!$readonly): ?>
        <a class="btn btn-primary" href="<?php echo site_url('taqdar_admin/form/' . $mkey); ?>">
            إضافة <?php echo html_escape($spec['title']); ?>
        </a>
    <?php endif; ?>
</div>

<?php tqa_flash(); ?>

<?php if (!empty($spec['note'])): ?>
    <div class="tqa-note"><?php echo html_escape($spec['note']); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($rows)): ?>

            <div class="tqa-empty">
                <h3>لا يوجد شيء بعد</h3>
                <p>
                    <?php echo $readonly
                        ? 'ستمتلئ هذه الشاشة تلقائيا حين يبدأ النظام في التسجيل.'
                        : 'ابدأ بإضافة أول عنصر — بقية الوحدات تعتمد عليه.'; ?>
                </p>
                <?php if (!$readonly): ?>
                    <a class="btn btn-primary" href="<?php echo site_url('taqdar_admin/form/' . $mkey); ?>">إضافة الآن</a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="inline-size:64px">#</th>
                            <?php foreach ($spec['fields'] as $name => $f): ?>
                                <?php if (!empty($f['list'])): ?>
                                    <th><?php echo html_escape($f['label']); ?></th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (!$readonly): ?><th style="inline-size:140px">إجراءات</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><span class="tq-ltr" dir="ltr"><?php echo (int) $r['id']; ?></span></td>

                            <?php foreach ($spec['fields'] as $name => $f): ?>
                                <?php if (!empty($f['list'])): ?>
                                    <td><?php echo tqa_cell($f, isset($r[$name]) ? $r[$name] : null, $M); ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if (!$readonly): ?>
                            <td>
                                <a class="btn btn-sm btn-secondary"
                                   href="<?php echo site_url('taqdar_admin/form/' . $mkey . '/' . (int) $r['id']); ?>">تعديل</a>

                                <?php if (empty($spec['nodelete'])): ?>
                                    <?php /* الحذف نموذجا لا رابطا: رابط GET يحذف ينفذ بمجرد جلبه. */ ?>
                                    <form method="post" class="d-inline tqa-del"
                                          action="<?php echo site_url('taqdar_admin/delete/' . $mkey . '/' . (int) $r['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="tqa-count">
                المعروض <span class="tq-ltr" dir="ltr"><?php echo count($rows); ?></span> عنصرا.
            </p>

        <?php endif; ?>
    </div>
</div>

<script>
/* تأكيد الحذف. الحارس الحقيقي في الخادم — هذا لمنع الزلة لا الاعتداء. */
document.querySelectorAll('.tqa-del').forEach(function (f) {
    f.addEventListener('submit', function (e) {
        if (!window.confirm('حذف هذا العنصر نهائيا؟')) e.preventDefault();
    });
});
</script>
