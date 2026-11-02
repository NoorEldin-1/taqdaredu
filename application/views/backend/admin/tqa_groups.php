<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-GROUP — المجموعات المباشرة في اللوحة.
 *
 * تجيب سؤال المال والمقاعد: كم عضوا، وكم معلقا يحجز مقعدا، وكم حصل
 * (**بالهللات** في القاعدة، ويطبع بالريال هنا)، وكم حصة بقيت. والتحرير
 * في الوحدة الموصوفة `module/groups`، والمراجعة في `taqdar_admin/review`.
 *
 * **ولا رد تلقائي**: اغلاق مجموعة قبل اول حصة يلغي العضويات ويعكس قيد
 * المعلم ويكتب `group.refund_due` في السجل — ورد المال نفسه يدوي خارج
 * المنصة، والشاشة تقول ذلك.
 */
$tq_rows = isset($stats['rows']) ? $stats['rows'] : array();
$tq_on   = !empty($enabled);

$tq_sar = function ($h) {
    return '<span class="tqa-num">' . number_format(((int) $h) / 100, 2) . '</span> ' . t('ر.س');
};
$tq_members = 0; $tq_gross = 0; $tq_pending = 0; $tq_review = 0;
foreach ($tq_rows as $r) {
    $tq_members += (int) $r['members'];
    $tq_gross   += (int) $r['gross'];
    $tq_pending += (int) $r['pending'];
    if ($r['status'] === 'review') $tq_review++;
}
$tq_label = array('draft' => t('مسودة'), 'review' => t('بانتظار المراجعة'), 'published' => t('منشورة'), 'closed' => t('مغلقة'));
?>

<?php tqa_head(
    t('المجموعات المباشرة'),
    t('جدول حصص مباشرة يباع مقعدا — الأعضاء والمقاعد والمحصل.'),
    'users',
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('taqdar_admin/module/groups') . '">'
        . tq_icon('plus', 16) . ' ' . te('حرر المجموعات') . '</a>'
); ?>

<?php if (!$tq_on): ?>
    <div class="tqa-note tqa-note--warn" style="margin-block-end:var(--tq-space-xl)">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span><strong><?php echo t('البيع مطفأ.'); ?></strong>
            <?php echo t('لا صفحة عامة ولا بند في البوابات ولا شراء حتى يفعل مفتاح «tq_group_sales_enabled».'); ?></span>
    </div>
<?php endif; ?>

<div class="tqa-grid tqa-grid--4" style="margin-block-end:var(--tq-space-xl)">
    <?php echo tqa_stat(t('أعضاء ساريون'), $tq_members, array('icon' => 'users', 'tone' => 'info')); ?>
    <?php echo tqa_stat(t('فواتير معلقة'), $tq_pending, array('icon' => 'clock', 'tone' => $tq_pending ? 'warn' : 'ok',
        'hint' => t('تحجز المقعد مهلتها ثم تسقط'))); ?>
    <?php echo tqa_stat(t('المحصل'), number_format($tq_gross / 100, 2), array('icon' => 'wallet', 'tone' => 'ok', 'hint' => t('ر.س'))); ?>
    <?php echo tqa_stat(t('بانتظار المراجعة'), $tq_review, array('icon' => 'help', 'tone' => $tq_review ? 'warn' : 'ok',
        'hint' => t('تعتمد من شاشة المراجعة'))); ?>
</div>

<div class="tqa-note" style="margin-block-end:var(--tq-space-xl)">
    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
    <span><?php echo t('إغلاق مجموعة قبل أول حصة يلغي عضويات أعضائها ويعكس قيد معلمها، ويسجل «رد مستحق» في سجل التدقيق. ورد المال نفسه يدوي خارج المنصة — لا مسار استرداد تلقائي.'); ?></span>
</div>

<section class="tqa-card tqa-card--flush">
    <div class="tqa-table__wrap">
        <table class="tqa-table tqa-table--zebra">
            <thead><tr>
                <th><?php echo t('المجموعة'); ?></th>
                <th><?php echo t('الحالة'); ?></th>
                <th><?php echo t('السعر'); ?></th>
                <th><?php echo t('الأعضاء'); ?></th>
                <th><?php echo t('المقاعد'); ?></th>
                <th><?php echo t('الحصص'); ?></th>
                <th><?php echo t('المحصل'); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php if (!$tq_rows): ?>
                <tr><td colspan="8"><?php echo t('لا مجموعات بعد.'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($tq_rows as $r): ?>
                <tr>
                    <td><strong><?php echo html_escape($r['title']); ?></strong></td>
                    <td><?php echo html_escape(isset($tq_label[$r['status']]) ? $tq_label[$r['status']] : $r['status']); ?>
                        <?php if ((int) $r['refund_due'] > 0): ?>
                            <span class="tqa-badge tqa-badge--warn"><?php echo (int) $r['refund_due']; ?> <?php echo t('عضوية ألغيت — راجع الرد اليدوي'); ?></span>
                        <?php endif; ?></td>
                    <td><?php echo $tq_sar($r['price_halalas']); ?></td>
                    <td><span class="tqa-num"><?php echo (int) $r['members']; ?></span>
                        <?php if ((int) $r['pending'] > 0): ?><small> + <?php echo (int) $r['pending']; ?> <?php echo t('معلق'); ?></small><?php endif; ?></td>
                    <td><?php echo html_escape($r['seats_text']); ?></td>
                    <td><span class="tqa-num"><?php echo (int) $r['sessions_total']; ?></span>
                        <small>(<?php echo (int) $r['sessions_left']; ?> <?php echo t('قادمة'); ?>)</small></td>
                    <td><?php echo $tq_sar($r['gross']); ?></td>
                    <td><a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="<?php echo site_url('taqdar_admin/form/groups/' . (int) $r['id']); ?>"><?php echo t('حرر'); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
