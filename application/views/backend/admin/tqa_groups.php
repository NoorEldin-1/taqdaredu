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
            <?php echo t('لا صفحة عامة ولا بند في البوابات ولا شراء حتى يفعل مفتاح «tq_group_sales_enabled».'); ?>
            <a href="#tq-group-sales"><?php echo t('فعله من ذيل الشاشة'); ?></a></span>
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
                <th><?php echo t('الحضور'); ?></th>
                <th><?php echo t('المحصل'); ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php if (!$tq_rows): ?>
                <tr><td colspan="9"><?php echo t('لا مجموعات بعد.'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($tq_rows as $r): ?>
                <tr>
                    <td><strong><?php echo html_escape($r['title']); ?></strong></td>
                    <td><?php echo html_escape(isset($tq_label[$r['status']]) ? $tq_label[$r['status']] : $r['status']); ?>
                        <?php if ((int) $r['refund_due'] > 0): ?>
                            <span class="tqa-badge tqa-badge--warn"><?php echo (int) $r['refund_due']; ?> <?php echo t('رد مستحق لم يسجل بعد'); ?></span>
                        <?php endif; ?></td>
                    <td><?php echo $tq_sar($r['price_halalas']); ?></td>
                    <td><span class="tqa-num"><?php echo (int) $r['members']; ?></span>
                        <?php if ((int) $r['pending'] > 0): ?><small> + <?php echo (int) $r['pending']; ?> <?php echo t('معلق'); ?></small><?php endif; ?></td>
                    <td><?php echo html_escape($r['seats_text']); ?></td>
                    <td><span class="tqa-num"><?php echo (int) $r['sessions_total']; ?></span>
                        <small>(<?php echo (int) $r['sessions_left']; ?> <?php echo t('قادمة'); ?>)</small></td>
                    <td><?php /* TQ-GROUP gap 4.4 — الحضور المعلن في الحصص المنتهية */
                        $tq_at = isset($r['attendance']) ? $r['attendance'] : array('total' => 0, 'present_pct' => null);
                        if ($tq_at['present_pct'] === null): ?>—<?php else: ?>
                        <span class="tqa-num"><?php echo (int) $tq_at['present_pct']; ?>%</span> <?php echo t('حضور'); ?>
                        <small>(<?php echo (int) $tq_at['absent_pct']; ?>% <?php echo t('غياب'); ?> · <?php echo (int) $tq_at['total']; ?>)</small>
                        <?php endif; ?></td>
                    <td><?php echo $tq_sar($r['gross']); ?></td>
                    <td><a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="<?php echo site_url('taqdar_admin/form/groups/' . (int) $r['id']); ?>"><?php echo t('حرر'); ?></a></td>
                </tr>
                <tr>
                    <td colspan="9">
                        <details>
                            <summary><?php echo t('إجراءات الإدارة'); ?></summary>
                            <?php
                            $tq_live_s = array_filter(isset($r['sessions']) ? $r['sessions'] : array(), function ($s) {
                                return in_array($s['status'], array('scheduled', 'live'), true);
                            });
                            ?>
                            <?php if ($tq_live_s): ?>
                                <h4><?php echo t('إلغاء حصة بلا بديل'); ?></h4>
                                <p class="tqa-hint"><?php echo t('قرار للإدارة وحدها: يبلغ كل الأعضاء بالسبب. وأي رد للمال يدوي خارج المنصة — لا يرد شيء تلقائيا.'); ?></p>
                                <?php foreach ($tq_live_s as $s): ?>
                                    <form method="post" action="<?php echo site_url('taqdar_admin/group_session_cancel'); ?>"
                                          onsubmit="return confirm('<?php echo te('تلغى هذه الحصة بلا بديل ويبلغ كل الأعضاء. ورد المال يدوي خارج المنصة. أتتابع؟'); ?>');"
                                          style="display:flex;gap:var(--tq-space-sm);align-items:center;flex-wrap:wrap;margin-block-end:var(--tq-space-sm)">
                                        <?php echo tq_csrf(); ?>
                                        <input type="hidden" name="session_id" value="<?php echo (int) $s['id']; ?>">
                                        <span><?php echo html_escape(($s['title'] !== '' ? $s['title'] . ' · ' : '') . $s['starts_at']); ?></span>
                                        <input class="form-control" type="text" name="reason" required maxlength="255"
                                               placeholder="<?php echo te('سبب الإلغاء — يقرؤه الأعضاء'); ?>" style="max-width:320px">
                                        <button class="tqa-btn tqa-btn--danger tqa-btn--sm" type="submit"><?php echo t('ألغ بلا بديل'); ?></button>
                                    </form>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php
                            $tq_sched_s = array_filter($tq_live_s, function ($s) { return $s['status'] === 'scheduled'; });
                            if ($tq_sched_s): ?>
                                <h4><?php echo t('تأجيل حصة'); ?></h4>
                                <p class="tqa-hint"><?php echo t('الموعد يتغير من هنا وحده: يبلغ كل الأعضاء بالموعد الجديد وسببه.'); ?></p>
                                <?php foreach ($tq_sched_s as $s): ?>
                                    <form method="post" action="<?php echo site_url('taqdar_admin/group_session_reschedule'); ?>"
                                          onsubmit="return confirm('<?php echo te('تؤجل هذه الحصة ويبلغ كل الأعضاء بالموعد الجديد. أتتابع؟'); ?>');"
                                          style="display:flex;gap:var(--tq-space-sm);align-items:center;flex-wrap:wrap;margin-block-end:var(--tq-space-sm)">
                                        <?php echo tq_csrf(); ?>
                                        <input type="hidden" name="session_id" value="<?php echo (int) $s['id']; ?>">
                                        <span><?php echo html_escape(($s['title'] !== '' ? $s['title'] . ' · ' : '') . $s['starts_at']); ?></span>
                                        <input class="form-control" type="datetime-local" name="starts_at" required style="max-width:220px"
                                               aria-label="<?php echo te('الموعد الجديد'); ?>">
                                        <input class="form-control" type="text" name="reason" required maxlength="255"
                                               placeholder="<?php echo te('سبب التأجيل — يقرؤه الأعضاء'); ?>" style="max-width:320px">
                                        <button class="tqa-btn tqa-btn--sm" type="submit"><?php echo t('أجل الحصة'); ?></button>
                                    </form>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if ($r['status'] !== 'closed'): ?>
                                <h4><?php echo t('إغلاق المجموعة'); ?></h4>
                                <p class="tqa-hint"><?php echo t('لا تباع بعده. وقبل أول حصة تلغى العضويات ويعكس قيد المعلم ويسجل رد مستحق لكل عضو — ورد المال نفسه يدوي خارج المنصة.'); ?></p>
                                <form method="post" action="<?php echo site_url('taqdar_admin/group_close'); ?>"
                                      onsubmit="return confirm('<?php echo te('تغلق المجموعة ولا تفتح للبيع. ورد مال الأعضاء يدوي خارج المنصة. أتتابع؟'); ?>');"
                                      style="display:flex;gap:var(--tq-space-sm);align-items:center;flex-wrap:wrap">
                                    <?php echo tq_csrf(); ?>
                                    <input type="hidden" name="group_id" value="<?php echo (int) $r['id']; ?>">
                                    <input class="form-control" type="text" name="reason" required maxlength="255"
                                           placeholder="<?php echo te('سبب الإغلاق'); ?>" style="max-width:320px">
                                    <button class="tqa-btn tqa-btn--danger tqa-btn--sm" type="submit"><?php echo t('أغلق المجموعة'); ?></button>
                                </form>
                            <?php endif; ?>

                            <?php if (!empty($r['refund_items'])): ?>
                                <h4><?php echo t('ردود مستحقة'); ?></h4>
                                <p class="tqa-hint"><?php echo t('رد المبلغ خارج المنصة ثم سجله هنا، فيخرج من العداد.'); ?></p>
                                <?php foreach ($r['refund_items'] as $tq_sid => $tq_amt): ?>
                                    <form method="post" action="<?php echo site_url('taqdar_admin/group_refund_resolved'); ?>"
                                          onsubmit="return confirm('<?php echo te('تسجل أن المبلغ رد لصاحبه خارج المنصة. أتتابع؟'); ?>');"
                                          style="display:flex;gap:var(--tq-space-sm);align-items:center;flex-wrap:wrap;margin-block-end:var(--tq-space-sm)">
                                        <?php echo tq_csrf(); ?>
                                        <input type="hidden" name="subscription_id" value="<?php echo (int) $tq_sid; ?>">
                                        <a href="<?php echo site_url('taqdar_admin/subscription/' . (int) $tq_sid); ?>">#<?php echo (int) $tq_sid; ?></a>
                                        <?php echo $tq_sar($tq_amt); ?>
                                        <input class="form-control" type="text" name="note" maxlength="255"
                                               placeholder="<?php echo te('مرجع التحويل (اختياري)'); ?>" style="max-width:240px">
                                        <button class="tqa-btn tqa-btn--ghost tqa-btn--sm" type="submit"><?php echo t('سجل أنه رد'); ?></button>
                                    </form>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tqa-card" id="tq-group-sales" style="margin-block-start:var(--tq-space-xl)">
    <h3><?php echo t('بيع المجموعات'); ?></h3>
    <p><?php echo t('الحال الآن:'); ?>
        <span class="tqa-badge <?php echo $tq_on ? 'tqa-badge--ok' : 'tqa-badge--warn'; ?>"><?php echo $tq_on ? t('مفعل') : t('مطفأ'); ?></span></p>
    <p class="tqa-hint"><?php echo t('متى فعل ظهرت صفحة المجموعات العامة ونوعها في الكتالوج وشاشة الشراء وعروض التطبيق. ومتى أطفئ اختفت كلها ولا يباع مقعد جديد، ومن اشترى يبقى على ما دفع. وإصدارات iOS لا تبيع في الحالين.'); ?></p>
    <form method="post" action="<?php echo site_url('taqdar_admin/groups_settings'); ?>"
          style="display:flex;gap:var(--tq-space-sm);align-items:center;flex-wrap:wrap">
        <?php echo tq_csrf(); ?>
        <label style="display:flex;gap:var(--tq-space-xs);align-items:center">
            <input type="checkbox" name="tq_group_sales_enabled" value="1"<?php echo $tq_on ? ' checked' : ''; ?>>
            <?php echo t('افتح بيع المجموعات'); ?>
        </label>
        <button class="tqa-btn tqa-btn--primary tqa-btn--sm" type="submit"><?php echo t('احفظ'); ?></button>
    </form>
</section>
