<?php
defined('BASEPATH') or exit('No direct script access allowed');
$M = &get_instance()->taqdar_admin_model;

$labels = array('pending' => 'معلّق', 'active' => 'نشط', 'cancelled' => 'ملغى', 'expired' => 'منتهٍ');
$tones  = array('pending' => 'warning', 'active' => 'success', 'cancelled' => 'danger', 'expired' => 'danger');
?>

<div class="tqa-head">
    <div>
        <h1>الاشتراكات</h1>
        <p>حالة كل مشترك، وتفعيل التحويلات البنكية بعد التحقّق منها.</p>
    </div>
</div>

<?php
/* اشتراكٌ نشط بلا بنود = طالبٌ دفع وبوّابته فارغة، بلا خطأ في أيّ سجلّ.
   يقع حين يُفعَّل الاشتراك بـUPDATE مباشر بدل المرور بـ`activate()`.
   والعدد يُقاس هنا لا يُفترض: تنبيهٌ دائم يُتجاهَل بعد أسبوع. */
$tq_broken = (int) get_instance()->db->query(
    'SELECT COUNT(*) AS n FROM `subscriptions` s
      WHERE s.`status` IN ("active","cancelled")
        AND NOT EXISTS (SELECT 1 FROM `subscription_items` i
                         WHERE i.`subscription_id` = s.`id`)'
)->row()->n;
?>
<?php if ($tq_broken > 0): ?>
    <div class="tqa-note tqa-note--warn">
        <strong><?php echo $tq_broken; ?> اشتراكًا نشطًا بلا بنود.</strong>
        هذه الاشتراكات مدفوعة وحالتها نشطة، لكنّ محتواها <strong>لا يُفتح للطالب</strong>
        لأنّ نطاقها لم يُنسَخ بنودًا. يقع هذا حين يُفعَّل الاشتراك من خارج زرّ التفعيل.
        <form method="post" action="<?php echo site_url('taqdar_admin/subscriptions_repair'); ?>"
              style="margin-block-start:10px">
            <button type="submit" class="tqa-btn tqa-btn--primary">أعِد بناء البنود من نطاق الباقات</button>
        </form>
    </div>
<?php endif; ?>

<?php tqa_flash(); ?>

<?php if (!$gateway_active): ?>
    <div class="tqa-note">
        <strong>لا بوّابة دفع مفعَّلة.</strong>
        مفاتيح PayPal وStripe فارغة في الإعدادات، فلا يمكن للطالب أن يدفع أونلاين اليوم.
        المسار العامل هو التحويل البنكي: يشترك الطالب فيُنشأ اشتراك «معلّق» وفاتورة،
        ثم تُفعّله من هنا بعد أن تتحقّق من الحوالة.
    </div>
<?php endif; ?>

<div class="row">
    <?php foreach (array(
        'الباقات' => $stats['plans'], 'اشتراكات نشطة' => $stats['active'],
        'بانتظار التفعيل' => $stats['pending'], 'فواتير غير مدفوعة' => $stats['unpaid'],
    ) as $label => $num): ?>
        <div class="col-md-3 col-sm-6">
            <div class="tqa-stat">
                <span class="tqa-stat-label"><?php echo $label; ?></span>
                <span class="tqa-stat-num tq-ltr" dir="ltr"><?php echo (int) $num; ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h4 class="header-title">
            إجمالي المحصَّل: <?php echo tqa_money($stats['revenue']); ?>
        </h4>
    </div>
    <div class="card-body">
        <?php if (empty($rows)): ?>

            <div class="tqa-empty">
                <h3>لا اشتراكات بعد</h3>
                <p>أضِف الباقات أوّلًا من <a href="<?php echo site_url('taqdar_admin/module/plans'); ?>">شاشة الباقات</a>،
                   ثم ستظهر هنا اشتراكات الطلاب.</p>
            </div>

        <?php else: ?>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المشترك</th>
                            <th>الباقة</th>
                            <th>المدفوع</th>
                            <th>الحالة</th>
                            <th>يبدأ</th>
                            <th>ينتهي</th>
                            <th>الوسيلة</th>
                            <th style="inline-size:180px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $st = $r['status'];
                        // منتهٍ فعليًّا وإن لم يمرّ الكرون بعد
                        if ($st === 'active' && !empty($r['ends_at']) && strtotime($r['ends_at']) < time()) {
                            $st = 'expired';
                        }
                    ?>
                        <tr>
                            <td><span class="tq-ltr" dir="ltr"><?php echo (int) $r['id']; ?></span></td>
                            <td><?php echo html_escape($r['user_name'] ?: ('#' . $r['user_id'])); ?></td>
                            <td><?php echo html_escape($r['plan_name'] ?: '—'); ?></td>
                            <td><?php echo tqa_money($r['price']); ?></td>
                            <td><span class="badge badge-<?php echo $tones[$st]; ?>"><?php echo $labels[$st]; ?></span></td>
                            <td><?php echo $r['started_at'] ? tqa_ltr(date('Y-m-d', strtotime($r['started_at']))) : '<span class="tqa-dim">—</span>'; ?></td>
                            <td><?php echo $r['ends_at'] ? tqa_ltr(date('Y-m-d', strtotime($r['ends_at']))) : '<span class="tqa-dim">—</span>'; ?></td>
                            <td><?php echo $r['method'] ? tqa_ltr($r['method']) : '<span class="tqa-dim">—</span>'; ?></td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <form method="post" class="tqa-activate"
                                          action="<?php echo site_url('taqdar_admin/subscription_activate/' . (int) $r['id']); ?>">
                                        <input type="text" name="reference" class="form-control tq-ltr" dir="ltr"
                                               placeholder="مرجع الحوالة" required>
                                        <button type="submit" class="btn btn-sm btn-success">فعّل</button>
                                    </form>
                                <?php elseif (in_array($r['status'], array('active'), true)): ?>
                                    <form method="post" class="tqa-cancel"
                                          action="<?php echo site_url('taqdar_admin/subscription_cancel/' . (int) $r['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">إلغاء</button>
                                    </form>
                                <?php else: ?>
                                    <span class="tqa-dim">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
/* الإلغاء لا يصادر المدفوع — المدّة تُكمَل. نقول ذلك قبل التأكيد لا بعده. */
document.querySelectorAll('.tqa-cancel').forEach(function (f) {
    f.addEventListener('submit', function (e) {
        if (!window.confirm('إلغاء التجديد؟ يبقى الاشتراك صالحًا حتى تاريخ انتهائه.')) e.preventDefault();
    });
});
</script>
