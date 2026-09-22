<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-COUPON — الكود الواحد: أيعمل؟ ولمن؟ ومن استعمله، وفي أي شراء؟
 *
 * وهو سؤال يسأل عند كل اتصال: «كتبت الكود ولم يخصم» (الحال وسببه في
 * أول الشاشة)، و«من أين جاء هذا الطالب؟» (الاستعمالات بأسمائها)، و«كم
 * جلبت حملة فلان؟» (الأرقام). وكل استعمال يدل على شاشة بيعته
 * (`taqdar_admin/subscription/<id>`) — القصة كاملة هناك لا نسخة منها هنا.
 */

$row   = isset($row) ? $row : array();
$use   = isset($use) ? $use : array('paid' => 0, 'held' => 0, 'discount' => 0, 'revenue' => 0);
$state = isset($state) ? $state : array('tone' => 'muted', 'label' => '', 'why' => '');
$kinds = isset($kinds) ? $kinds : array();
$reds  = isset($reds) ? $reds : array();

$sar  = function ($h) { return '<span class="tqa-num">' . number_format(((int) $h) / 100, 0) . '</span> ' . t('ر.س'); };
$tone = array('ok' => 'ok', 'warn' => 'warn', 'no' => 'danger');
$rs   = array('held' => array(t('محجوز — فاتورة لم تسدد'), 'warn'), 'paid' => array(t('استعمل'), 'ok'),
              'void' => array(t('ألغي'), 'muted'));

$on = array();
foreach ($kinds as $k => $d) if ((int) $row[$d['col']] === 1) $on[] = $d['label'];

$share = site_url('plans') . '?coupon=' . rawurlencode($row['code']);

$tools = '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/coupons') . '">' . t('كل الأكواد') . '</a>'
       . '<a class="tqa-btn tqa-btn--primary" href="' . site_url('taqdar_admin/form/coupons/' . (int) $row['id']) . '">'
       . tq_icon('edit', 16) . ' ' . t('تعديل') . '</a>';
?>

<?php tqa_head(t('كود الخصم') . ' ' . $row['code'], $row['label'] !== '' ? $row['label'] : t('بلا اسم داخلي'), 'tag', $tools); ?>

<?php if ($m = tq_flash('flash_message')): ?>
    <p class="tqa-note tqa-section"><span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        <span style="flex:1"><?php echo html_escape($m); ?></span></p>
<?php endif; ?>

<div class="tqa-note<?php echo $state['tone'] === 'ok' ? '' : ' tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($state['tone'] === 'ok' ? 'check-badge' : 'alert', 18); ?></span>
    <span style="flex:1">
        <span class="tqa-badge tqa-badge--<?php echo isset($tone[$state['tone']]) ? $tone[$state['tone']] : 'muted'; ?>"><?php echo html_escape($state['label']); ?></span>
        <?php echo html_escape($state['why']); ?>
    </span>
    <form method="post" action="<?php echo site_url('taqdar_admin/coupon_toggle'); ?>" style="margin:0">
        <?php echo tq_csrf(); ?>
        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
        <input type="hidden" name="on" value="<?php echo (int) $row['active'] === 1 ? 0 : 1; ?>">
        <input type="hidden" name="back" value="one">
        <button class="tqa-btn tqa-btn--sm <?php echo (int) $row['active'] === 1 ? 'tqa-btn--quiet-danger' : 'tqa-btn--primary'; ?>" type="submit">
            <?php echo (int) $row['active'] === 1 ? t('أوقف الكود') : t('فعل الكود'); ?></button>
    </form>
</div>

<div class="tqa-grid tqa-grid--4" style="margin-block-end:var(--tq-space-xl)">
    <?php foreach (array(
        array(t('الخصم'), '<span class="tqa-num">' . (int) $row['percent'] . t('٪') . '</span>',
              $row['max_discount'] !== null ? t('بحد ____', array(strip_tags($sar($row['max_discount'])))) : t('بلا حد أقصى'), 'tag', 'tqa-mint'),
        array(t('استعمل'), '<span class="tqa-num">' . (int) $use['paid'] . '</span>'
              . ($row['max_uses'] !== null ? ' / <span class="tqa-num">' . (int) $row['max_uses'] . '</span>' : ''),
              (int) $use['held'] . t(' محجوز بفواتير لم تسدد'), 'chart', 'tqa-sky'),
        array(t('ما وفره المشترون'), $sar($use['discount']), t('في الشراء المسدد'), 'wallet', 'tqa-peach'),
        array(t('بيع به'), $sar($use['revenue']), t('ما دفع بعد الخصم'), 'money', 'tqa-lilac'),
    ) as $t): ?>
        <div class="tqa-stat">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label"><?php echo html_escape($t[0]); ?></span>
                <span class="tqa-stat__icon <?php echo $t[4]; ?>"><?php echo tq_icon($t[3], 17); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo $t[1]; ?></span>
            <span class="tqa-stat__hint"><?php echo html_escape($t[2]); ?></span>
        </div>
    <?php endforeach; ?>
</div>

<section class="tqa-card tqa-section">
    <div class="tqa-card__head"><h2><?php echo tq_icon('shield', 18); ?> <?php echo t('شروط الكود'); ?></h2></div>
    <dl class="tqa-grid tqa-grid--2" style="margin:0">
        <div><dt class="tqa-field__label"><?php echo t('يعمل على'); ?></dt>
             <dd style="margin:0"><?php echo html_escape($on ? implode(t('، '), $on) : '—'); ?>
                <?php if (trim($row['plan_ids'] . $row['course_ids'] . $row['book_ids']) !== ''): ?>
                    <span class="tqa-badge tqa-badge--info"><?php echo t('عناصر محددة'); ?></span>
                <?php endif; ?></dd></div>
        <div><dt class="tqa-field__label"><?php echo t('أقل مبلغ'); ?></dt>
             <dd style="margin:0"><?php echo $row['min_amount'] !== null ? $sar($row['min_amount']) : t('أي مبلغ'); ?></dd></div>
        <div><dt class="tqa-field__label"><?php echo t('لكل حساب'); ?></dt>
             <dd style="margin:0"><?php echo (int) $row['per_user'] > 0 ? (int) $row['per_user'] : t('بلا حد'); ?>
                <?php echo (int) $row['first_purchase'] === 1 ? ' · ' . t('لأول شراء فقط') : ''; ?></dd></div>
        <div><dt class="tqa-field__label"><?php echo t('المدة'); ?></dt>
             <dd style="margin:0" dir="auto"><?php
                /* `tqa_when()` ترد وسما مهربا (`<span class="tq-ltr">`)، فلا يهرب مرة ثانية. */
                echo ($row['starts_at'] ? tqa_when($row['starts_at']) : html_escape(t('من إنشائه')))
                    . ' ← ' . ($row['ends_at'] ? tqa_when($row['ends_at']) : html_escape(t('بلا نهاية'))); ?></dd></div>
        <div style="grid-column:1/-1"><dt class="tqa-field__label"><?php echo t('رابط يطبق الكود وحده'); ?></dt>
             <dd style="margin:0"><span class="tqa-mono" dir="ltr"><?php echo html_escape($share); ?></span>
                <span class="tqa-field__hint" style="display:block"><?php echo t('من فتحه يجد الكود في شاشة الدفع بعد أن يختار ويسجل — ويصلح لأي صفحة في الموقع.'); ?></span></dd></div>
        <?php if (trim((string) $row['note']) !== ''): ?>
            <div style="grid-column:1/-1"><dt class="tqa-field__label"><?php echo t('ملاحظة'); ?></dt>
                 <dd style="margin:0"><?php echo nl2br(html_escape($row['note'])); ?></dd></div>
        <?php endif; ?>
    </dl>
</section>

<section class="tqa-card tqa-card--flush tqa-section">
    <div class="tqa-card__head"><h2><?php echo tq_icon('users', 18); ?> <?php echo t('من استعمله'); ?></h2></div>
    <?php if (!$reds): ?>
        <?php tqa_empty(t('لم يستعمل بعد'), t('حين يكتبه مشتر في شاشة الدفع ويصدر فاتورته يظهر هنا — محجوزا حتى يسدد، ثم مستعملا.'), '', '', 'users'); ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead><tr>
                <th><?php echo t('المشتري'); ?></th>
                <th><?php echo t('ما اشترى'); ?></th>
                <th><?php echo t('السعر ← الخصم ← المدفوع'); ?></th>
                <th><?php echo t('الحال'); ?></th>
                <th><?php echo t('متى'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($reds as $r):
                $st = isset($rs[$r['status']]) ? $rs[$r['status']] : array($r['status'], 'muted');
                if (!empty($r['stale'])) $st = array(t('حجز انتهت مهلته'), 'muted');
            ?>
                <tr>
                    <td data-label="<?php echo te('المشتري'); ?>">
                        <strong><?php echo html_escape(trim($r['first_name'] . ' ' . $r['last_name']) ?: '#' . (int) $r['user_id']); ?></strong>
                        <br><span class="tqa-dim" style="font-size:12px" dir="ltr"><?php echo html_escape((string) $r['email']); ?></span>
                    </td>
                    <td data-label="<?php echo te('ما اشترى'); ?>">
                        <span class="tqa-badge tqa-badge--muted"><?php echo html_escape($r['sold']['label']); ?></span>
                        <?php echo html_escape($r['sold']['title']); ?>
                        <?php if ((int) $r['subscription_id'] > 0): ?>
                            <br><a style="font-size:12px" href="<?php echo site_url('taqdar_admin/subscription/' . (int) $r['subscription_id']); ?>">
                                <?php echo t('تفاصيل البيعة'); ?><?php echo $r['invoice_no'] ? ' · ' . html_escape($r['invoice_no']) : ''; ?></a>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php echo te('المبالغ'); ?>" style="white-space:nowrap">
                        <?php echo $sar($r['gross']); ?> ← <span style="color:var(--tq-teal)">−<?php echo $sar($r['discount']); ?></span>
                        ← <strong><?php echo $sar($r['net']); ?></strong>
                    </td>
                    <td data-label="<?php echo te('الحال'); ?>"><span class="tqa-badge tqa-badge--<?php echo $st[1]; ?>"><?php echo html_escape($st[0]); ?></span></td>
                    <td data-label="<?php echo te('متى'); ?>" style="font-size:12px"><?php echo tqa_when($r['paid_at'] ?: $r['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($blocked)): ?>
    <p class="tqa-field__hint"><?php echo t('لا يحذف هذا الكود: ') . html_escape(implode(' ', $blocked)) . ' ' . t('أوقفه بدل ذلك.'); ?></p>
<?php endif; ?>
