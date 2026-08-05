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
        <h1>استيراد المنهج</h1>
        <p>المواد والصفوف والمسارات دفعةً واحدة من ملفّ، بدل إدخالها صفًّا صفًّا.</p>
    </div>
    <a class="btn btn-secondary" href="<?php echo site_url('taqdar_admin/import_template'); ?>">
        نزّل ملفًّا نموذجيًّا
    </a>
</div>

<?php tqa_flash(); ?>

<?php if (!$preview): ?>

    <div class="tqa-note">
        <strong>صفٌّ واحد = مسار واحد.</strong>
        المادة والصفّ يُنشآن تلقائيًّا إن لم يكونا موجودين، فلا حاجة إلى ملفّات ثلاثة ولا إلى ربط معرّفات بيدك.
        <br>
        و<strong>لا يُكتب شيء قبل أن تراه</strong>: يُقرأ الملفّ ويُفحص وتُعرض النتيجة، ثمّ تؤكّد.
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h4 class="header-title">اختر الملفّ</h4></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data"
                          action="<?php echo site_url('taqdar_admin/import_preview'); ?>">
                        <div class="tqa-field">
                            <label for="f">ملفّ CSV أو JSON</label>
                            <input class="form-control" type="file" id="f" name="curriculum"
                                   accept=".csv,.json" required>
                            <small class="tqa-hint">
                                يُقبل الفاصل «,» أو «؛»، والترميز UTF-8. الحدّ الأقصى <span class="tq-ltr" dir="ltr">2</span> ميغابايت.
                            </small>
                        </div>
                        <div class="tqa-actions">
                            <button type="submit" class="btn btn-primary">اقرأ واعرض</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h4 class="header-title">الأعمدة</h4></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>العمود</th><th>لازم؟</th></tr></thead>
                        <tbody>
                            <tr><td>المادة</td><td>نعم</td></tr>
                            <tr><td>الصف</td><td>نعم</td></tr>
                            <tr><td>المسار</td><td>نعم</td></tr>
                            <tr><td>السعر</td><td>بالريال</td></tr>
                            <tr><td>المعلم</td><td>بريده</td></tr>
                            <tr><td>النسبة</td><td><span class="tq-ltr" dir="ltr">0–100</span></td></tr>
                            <tr><td>الاسابيع</td><td>عدد</td></tr>
                            <tr><td>الدورة</td><td>عنوانها</td></tr>
                            <tr><td>الحالة</td><td>مسودة/منشور</td></tr>
                        </tbody>
                    </table>
                    <small class="tqa-hint" style="margin-block-start:var(--tq-space-m)">
                        الأسماء الإنجليزية مقبولة أيضًا (<span class="tq-ltr" dir="ltr">subject, grade, title…</span>).
                    </small>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <div class="row">
        <?php foreach (array(
            'صالح للكتابة' => array($ok_n, 'success'),
            'به تنبيه'     => array($warn_n, 'warning'),
            'مرفوض'        => array($err_n, 'danger'),
        ) as $label => $pair): ?>
            <div class="col-md-4">
                <div class="tqa-stat">
                    <span class="tqa-stat-label"><?php echo $label; ?></span>
                    <span class="tqa-stat-num tq-ltr" dir="ltr"><?php echo (int) $pair[0]; ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($err_n): ?>
        <div class="tqa-note">
            الصفوف المرفوضة <strong>تُترك ولا تُوقف غيرها</strong> — تُكتب السليمة وحدها،
            فتُصحّح المرفوض وتُعيد الاستيراد. وإعادة الاستيراد <strong>تُحدّث ولا تُكرّر</strong>.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h4 class="header-title">المعاينة قبل الكتابة</h4></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>السطر</th><th>المسار</th><th>المادة</th><th>الصف</th>
                            <th>السعر</th><th>النسبة</th><th>المعلّم</th><th>الحالة</th><th>الأثر</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($preview as $r): ?>
                        <tr>
                            <td><span class="tq-ltr" dir="ltr"><?php echo (int) $r['line']; ?></span></td>
                            <td>
                                <?php echo html_escape($r['title']); ?>
                                <?php foreach ($r['errors'] as $e): ?>
                                    <span class="badge badge-danger"><?php echo html_escape($e); ?></span>
                                <?php endforeach; ?>
                                <?php foreach ($r['warnings'] as $w): ?>
                                    <span class="badge badge-warning"><?php echo html_escape($w); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?php echo html_escape($r['subject']); ?></td>
                            <td><?php echo html_escape($r['grade']); ?></td>
                            <td><?php echo $r['price'] ? tqa_money($r['price']) : '<span class="tqa-dim">—</span>'; ?></td>
                            <td><?php echo $r['share'] !== null
                                    ? '<span class="tq-ltr" dir="ltr">' . $r['share'] . '%</span>'
                                    : '<span class="tqa-dim">افتراضي</span>'; ?></td>
                            <td><?php echo $r['teacher'] ? '<span class="tq-ltr" dir="ltr">' . html_escape($r['teacher']) . '</span>' : '<span class="tqa-dim">—</span>'; ?></td>
                            <td><span class="badge badge-<?php echo $r['status'] === 'published' ? 'success' : 'warning'; ?>">
                                <?php echo $r['status'] === 'published' ? 'منشور' : 'مسودّة'; ?></span></td>
                            <td>
                                <?php if (!empty($r['errors'])): ?>
                                    <span class="tqa-dim">يُتجاوَز</span>
                                <?php else: ?>
                                    <?php echo $r['action'] === 'update' ? 'تحديث' : 'إنشاء'; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="tqa-actions">
                <form method="post" action="<?php echo site_url('taqdar_admin/import_commit'); ?>" class="d-inline">
                    <button type="submit" class="btn btn-primary" <?php echo $ok_n ? '' : 'disabled'; ?>>
                        اكتب <span class="tq-ltr" dir="ltr"><?php echo (int) $ok_n; ?></span> صفًّا
                    </button>
                </form>
                <a class="btn btn-secondary" href="<?php echo site_url('taqdar_admin/import'); ?>">إلغاء</a>
            </div>
        </div>
    </div>

<?php endif; ?>
