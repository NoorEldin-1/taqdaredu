<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * المدفوعات والفواتير.
 *
 * كل عملية تنتج فاتورة قابلة للتحميل، لأن ولي الأمر يسأل عنها.
 * والسعر يعرض شاملا ما دفع فعلا — لا رسوم تظهر بعد الشراء.
 * موصول: payment (جدول Academy) · العملة الريال.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'settings';
$tq_role  = 'student';
$tq_title = t('المدفوعات');
$tq_sub   = t('طلباتك وفواتيرك');
$tq_icon  = 'wallet';

$cols = $this->db->list_fields('payment');
$idc  = in_array('user_id', $cols, true) ? 'user_id' : (in_array('payer_id', $cols, true) ? 'payer_id' : null);

$tq_orders = [];
if ($idc) {
    $tq_orders = $this->db->where($idc, $tq_uid)->order_by('id', 'DESC')->limit(50)->get('payment')->result_array();
}

$paid = 0;
foreach ($tq_orders as $o) { $paid += (float) ($o['amount'] ?? 0); }

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>
        <?php if (empty($tq_orders)): ?>
            <div class="tq-card">
                <div class="tq-empty">
                    <span class="tq-icon-box tq-pastel--sky" style="inline-size:72px;block-size:72px" aria-hidden="true">
                        <?php echo tq_icon('wallet', 36); ?>
                    </span>
                    <p class="tq-empty__title"><?php echo t('لا عمليات بعد'); ?></p>
                    <p class="tq-empty__text"><?php echo t('أول اشتراك أو شراء يظهر هنا بفاتورته القابلة للتحميل.'); ?></p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>"><?php echo t('عرض الباقات'); ?></a>
                </div>
            </div>
        <?php else: ?>
            <div class="tq-card">
                <div class="tq-card__head">
                    <h2 class="tq-card__title"><?php echo t('سجل العمليات'); ?></h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_orders) . TQ_PDI; ?></span>
                </div>
                <table class="tq-table">
                    <thead>
                        <tr><th><?php echo t('رقم العملية'); ?></th><th><?php echo t('الوصف'); ?></th><th><?php echo t('التاريخ'); ?></th><th><?php echo t('المبلغ'); ?></th><th><?php echo t('الحالة'); ?></th><th><?php echo t('الفاتورة'); ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tq_orders as $o):
                            $st = strtolower((string) ($o['payment_status'] ?? $o['status'] ?? ''));
                            $kind = in_array($st, ['success', 'paid', 'completed', '1'], true) ? 'mastered'
                                  : (in_array($st, ['refunded', 'failed', 'cancelled'], true) ? 'late' : 'progress');
                            $word = ['mastered' => t('مدفوعة'), 'late' => t('مستردة أو متعثرة'), 'progress' => t('قيد المعالجة')][$kind];
                        ?>
                            <tr>
                                <td data-label="رقم العملية"><?php echo tq_num('#' . (int) $o['id'], 'tq-num--sm'); ?></td>
                                <td data-label="الوصف"><?php echo html_escape((string) ($o['course_title'] ?? $o['tags'] ?? t('اشتراك'))); ?></td>
                                <td data-label="التاريخ"><?php echo tq_iso(html_escape((string) ($o['date_added'] ?? ''))); ?></td>
                                <td data-label="المبلغ"><?php echo tq_sar($o['amount'] ?? 0, 2); ?></td>
                                <td data-label="الحالة"><?php echo tq_badge($kind, $word); ?></td>
                                <td data-label="الفاتورة">
                                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('home/invoice/' . (int) $o['id']); ?>">
                                        <?php echo tq_icon('download'); ?> <?php echo t('تحميل'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php /* TQ-SPAM — كل عملية هنا خرجت بفاتورتها بالبريد، والفاتورة
                 التي لا تصل تجعل صاحبها يظن أن الدفع لم يتم. مطوي: هذه
                 شاشة سجل، والحقيقة معروضة أمامه في الجدول. */ ?>
        <?php echo tq_spam_notice(array('compact' => true, 'id' => 'tq-spam-pay')); ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <h2 class="tq-card__title"><?php echo t('الملخص'); ?></h2>
            <div class="tq-s-2x2">
                <div class="tq-pastel tq-pastel--mint">
                    <span class="tq-pastel__label tq-micro"><?php echo t('إجمالي المدفوع'); ?></span>
                    <p class="tq-pastel__title" style="margin:var(--tq-space-xs) 0 0"><?php echo tq_sar($paid, 2); ?></p>
                </div>
                <div class="tq-pastel tq-pastel--sky">
                    <span class="tq-pastel__label tq-micro"><?php echo t('عدد العمليات'); ?></span>
                    <p class="tq-pastel__title" style="margin:var(--tq-space-xs) 0 0"><?php echo tq_num(count($tq_orders)); ?></p>
                </div>
            </div>
        </div>

        <div class="tq-card">
            <h2 class="tq-card__title"><?php echo t('اشتراكك'); ?></h2>
            <p class="tq-caption">
                <?php echo t('باقتك الحالية والتجديد يظهران هنا بعد أول اشتراك. وحصص بالطلب خارج الاشتراك — تشترى بالحصة.'); ?>
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--sm tq-btn--block" href="<?php echo base_url('plans'); ?>"><?php echo t('عرض الباقات'); ?></a>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
