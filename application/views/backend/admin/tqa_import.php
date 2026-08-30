<?php
defined('BASEPATH') or exit('No direct script access allowed');

$preview = isset($preview) ? $preview : null;
$ok_n    = 0; $err_n = 0; $warn_n = 0;
if ($preview) {
    foreach ($preview as $r) {
        if (!empty($r['errors']))        $err_n++;
        else                             $ok_n++;
        if (!empty($r['warnings']))      $warn_n++;
    }
}
?>

<div class="tqa-head">
    <div>
        <h1><?php echo t('استيراد المنهج'); ?></h1>
        <p><?php echo t('المواد والصفوف والمسارات دفعة واحدة من ملف، بدل إدخالها صفا صفا.'); ?></p>
    </div>
    <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/import_template'); ?>">
        <?php echo t('نزل ملفا نموذجيا'); ?>
    </a>
</div>


<?php if (!$preview): ?>

    <div class="tqa-note">
        <strong><?php echo t('صف واحد = مسار واحد.'); ?></strong>
        <?php echo t('المادة والصف ينشآن تلقائيا إن لم يكونا موجودين، فلا حاجة إلى ملفات ثلاثة ولا إلى ربط معرفات بيدك.'); ?>
        <br>
        <?php echo t('و'); ?><strong><?php echo t('لا يكتب شيء قبل أن تراه'); ?></strong><?php echo t(': يقرأ الملف ويفحص وتعرض النتيجة، ثم تؤكد.'); ?>
    </div>

    <div class="tqa-stack">
        <div>
            <div class="tqa-card">
                <div class="tqa-card__head"><h2><?php echo t('اختر الملف'); ?></h2></div>
                <div class="tqa-card__body">
                    <form method="post" enctype="multipart/form-data"
                          action="<?php echo site_url('taqdar_admin/import_preview'); ?>">
                        <div class="tqa-field">
                            <label for="f"><?php echo t('ملف CSV أو JSON'); ?></label>
                            <input class="tqa-input" type="file" id="f" name="curriculum"
                                   accept=".csv,.json" required>
                            <small class="tqa-hint">
                                <?php echo t('يقبل الفاصل «,» أو «؛»، والترميز UTF-8. الحد الأقصى'); ?> <span class="tq-ltr" dir="ltr">2</span> <?php echo t('ميغابايت.'); ?>
                            </small>
                        </div>
                        <div class="tqa-actions">
                            <button type="submit" class="tqa-btn tqa-btn--primary"><?php echo t('اقرأ واعرض'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div>
            <div class="tqa-card">
                <div class="tqa-card__head"><h2><?php echo t('الأعمدة'); ?></h2></div>
                <div class="tqa-card__body">
                    <table class="table table-sm mb-0">
                        <thead><tr><th><?php echo t('العمود'); ?></th><th><?php echo t('لازم؟'); ?></th></tr></thead>
                        <tbody>
                            <tr><td><?php echo t('المادة'); ?></td><td><?php echo t('نعم'); ?></td></tr>
                            <tr><td><?php echo t('الصف'); ?></td><td><?php echo t('نعم'); ?></td></tr>
                            <tr><td><?php echo t('المسار'); ?></td><td><?php echo t('نعم'); ?></td></tr>
                            <tr><td><?php echo t('السعر'); ?></td><td><?php echo t('بالريال'); ?></td></tr>
                            <tr><td><?php echo t('المعلم'); ?></td><td><?php echo t('بريده'); ?></td></tr>
                            <tr><td><?php echo t('النسبة'); ?></td><td><span class="tq-ltr" dir="ltr">0–100</span></td></tr>
                            <tr><td><?php echo t('الاسابيع'); ?></td><td><?php echo t('عدد'); ?></td></tr>
                            <tr><td><?php echo t('الدورة'); ?></td><td><?php echo t('عنوانها'); ?></td></tr>
                            <tr><td><?php echo t('الحالة'); ?></td><td><?php echo t('مسودة/منشور'); ?></td></tr>
                        </tbody>
                    </table>
                    <small class="tqa-hint" style="margin-block-start:var(--tq-space-m)">
                        <?php echo t('الأسماء الإنجليزية مقبولة أيضا ('); ?><span class="tq-ltr" dir="ltr">subject, grade, title…</span>).
                    </small>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="tqa-stack">
        <?php foreach (array(
            'صالح للكتابة' => array($ok_n, 'success'),
            'به تنبيه'     => array($warn_n, 'warning'),
            'مرفوض'        => array($err_n, 'danger'),
        ) as $label => $pair): ?>
            <div>
                <div class="tqa-stat">
                    <span class="tqa-stat-label"><?php echo $label; ?></span>
                    <span class="tqa-stat-num tq-ltr" dir="ltr"><?php echo (int) $pair[0]; ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($err_n): ?>
        <div class="tqa-note">
            <?php echo t('الصفوف المرفوضة'); ?> <strong><?php echo t('تترك ولا توقف غيرها'); ?></strong> <?php echo t('— تكتب السليمة وحدها، فتصحح المرفوض وتعيد الاستيراد. وإعادة الاستيراد'); ?> <strong><?php echo t('تحدث ولا تكرر'); ?></strong>.
        </div>
    <?php endif; ?>

    <div class="tqa-card">
        <div class="tqa-card__head"><h2><?php echo t('المعاينة قبل الكتابة'); ?></h2></div>
        <div class="tqa-card__body">
            <div class="tqa-table__wrap">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?php echo t('السطر'); ?></th><th><?php echo t('المسار'); ?></th><th><?php echo t('المادة'); ?></th><th><?php echo t('الصف'); ?></th>
                            <th><?php echo t('السعر'); ?></th><th><?php echo t('النسبة'); ?></th><th><?php echo t('المعلم'); ?></th><th><?php echo t('الحالة'); ?></th><th><?php echo t('الأثر'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($preview as $r): ?>
                        <tr>
                            <td><span class="tq-ltr" dir="ltr"><?php echo (int) $r['line']; ?></span></td>
                            <td>
                                <?php echo html_escape($r['title']); ?>
                                <?php foreach ($r['errors'] as $e): ?>
                                    <span class="tqa-badge tqa-badge--danger"><?php echo html_escape($e); ?></span>
                                <?php endforeach; ?>
                                <?php foreach ($r['warnings'] as $w): ?>
                                    <span class="tqa-badge tqa-badge--warn"><?php echo html_escape($w); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo html_escape($r['subject']); ?></td>
                            <td><?php echo html_escape($r['grade']); ?></td>
                            <td><?php echo $r['price'] ? tqa_money($r['price']) : '<span class="tqa-dim">—</span>'; ?></td>
                            <td><?php echo $r['share'] !== null
                                    ? '<span class="tq-ltr" dir="ltr">' . $r['share'] . '%</span>'
                                    : t('<span class="tqa-dim">افتراضي</span>'); ?></td>
                            <td><?php echo $r['teacher'] ? '<span class="tq-ltr" dir="ltr">' . html_escape($r['teacher']) . '</span>' : '<span class="tqa-dim">—</span>'; ?></td>
                            <td><span class="badge badge-<?php echo $r['status'] === 'published' ? 'success' : 'warning'; ?>">
                                <?php echo $r['status'] === 'published' ? t('منشور') : t('مسودة'); ?></span></td>
                            <td>
                                <?php if (!empty($r['errors'])): ?>
                                    <span class="tqa-dim"><?php echo t('يتجاوز'); ?></span>
                                <?php else: ?>
                                    <?php echo $r['action'] === 'update' ? t('تحديث') : t('إنشاء'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="tqa-actions">
                <form method="post" action="<?php echo site_url('taqdar_admin/import_commit'); ?>" class="d-inline">
                    <button type="submit" class="tqa-btn tqa-btn--primary" <?php echo $ok_n ? '' : 'disabled'; ?>>
                        <?php echo t('اكتب'); ?> <span class="tq-ltr" dir="ltr"><?php echo (int) $ok_n; ?></span> <?php echo t('صفا'); ?>
                    </button>
                </form>
                <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/import'); ?>"><?php echo t('إلغاء'); ?></a>
            </div>
        </div>
    </div>

<?php endif; ?>
