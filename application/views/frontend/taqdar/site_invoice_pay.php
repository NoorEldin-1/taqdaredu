<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * TQ-EXPRESS-PAY — دفع فاتورة قائمة: `pay/<رقم>`.
 *
 * أخت شاشات التأكيد بمفرداتها نفسها (`co-*`): من يدفع فاتورة حصة أو
 * فاتورة تركها ثم عاد إليها يرى الشاشة التي رأى مثلها حين اشترى — وشاشة
 * دفع تختلف في شكلها عن أخواتها هي أول ما يشك فيه المشتري.
 *
 * والمبلغ من الفاتورة لا من شيء آخر: هو ما تقابله `settle()` بالهللة،
 * وما يعرض في نافذة Apple Pay، وما يكتب على زر البطاقة.
 */
$inv    = isset($tq_inv) ? $tq_inv : null;
if (!$inv) return;

$total  = (int) $inv['total'];
$closed = (string) $tq_closed;
$tq_card = !empty($tq_card);
$tq_test = !empty($tq_card_test);
$CI = get_instance();
?>

<section class="page-hero page-hero--checkout">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <nav class="crumbs" aria-label="<?php echo t('مسار التصفح'); ?>">
      <a href="<?php echo base_url($tq_back); ?>"><?php echo t('رجوع'); ?></a> ›
      <span aria-current="page"><?php echo t('دفع الفاتورة'); ?></span>
    </nav>
    <h1><?php echo t('دفع الفاتورة'); ?></h1>
    <p class="page-hero__lead"><?php echo t('اختر طريقة الدفع — ويفتح ما دفعت ثمنه في لحظته.'); ?></p>
  </div>
</section>

<section class="section section--tight">
  <form class="shell co-cols" method="post" action="<?php echo base_url('student/pay-invoice'); ?>" id="tqPayInv">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="invoice_id" value="<?php echo (int) $inv['id']; ?>">
    <?php /* يعود إلى هذه الشاشة إن تعثر البدء — قيمة مغلقة يقرؤها
             `Taqdar_pay::start()`. */ ?>
    <input type="hidden" name="back" value="invoice">

    <div class="co-main">

      <?php if ($m = $CI->session->flashdata('error_message')): ?>
        <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($m); ?></p>
      <?php endif; ?>
      <?php if ($m = $CI->session->flashdata('flash_message')): ?>
        <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($m); ?></p>
      <?php endif; ?>

      <div class="icard co-summary">
        <h2><?php echo t('ملخص الفاتورة'); ?></h2>
        <div class="co-item">
          <div class="co-item__b">
            <h3><?php echo html_escape($tq_what); ?></h3>
            <p class="tq-caption">
              <?php echo t('رقم الفاتورة'); ?>
              <b class="tq-ltr"><?php echo html_escape($inv['invoice_no']); ?></b>
            </p>
          </div>
          <p class="co-item__price"><?php echo tqs_money($total); ?></p>
        </div>
        <dl class="co-total">
          <div class="co-total__f"><dt><?php echo t('الإجمالي'); ?></dt><dd><?php echo tqs_money($total); ?></dd></div>
        </dl>
      </div>

      <div class="icard">
        <h2><?php echo t('طريقة الدفع'); ?></h2>

        <?php if ($closed !== ''): ?>
          <p class="co-pay"><span class="tq-caption"><?php echo html_escape($closed); ?></span></p>

        <?php else: ?>
          <?php echo tq_express_pay(array(
              'amount' => $total,
              'form'   => 'tqPayInv',
              'label'  => $tq_what,
          )); ?>

          <?php if ($tq_card): ?>
            <p class="co-pay">
              <span class="co-pay__pick">
                <svg aria-hidden="true"><use href="#i-card"></use></svg>
                <b><?php echo t('صفحة تاب الآمنة'); ?></b>
              </span>
              <span class="tq-caption"><?php echo t('كل طرق الدفع في صفحة تاب: مدى وفيزا وماستركارد وApple Pay. الزر في البطاقة الجانبية.'); ?></span>
            </p>
            <?php if ($tq_test): ?>
              <p class="co-pay__test">
                <svg aria-hidden="true"><use href="#i-shield"></use></svg>
                <?php echo t('البوابة في'); ?> <b><?php echo t('وضع الاختبار'); ?></b>
                <?php echo t('الآن: الدفع لا يخصم مالا حقيقيا.'); ?>
              </p>
            <?php endif; ?>
          <?php endif; ?>

          <?php /* التحويل يبقى متاحا لمن لا بطاقة معه: الفاتورة قائمة
                   ومرجعها رقمها، فلا شيء يضغط — يحول ويفعل بعد التحقق. */ ?>
          <?php if (tqs_bank() !== null || !$tq_card): ?>
            <div class="co-bank">
              <?php echo tqs_bank_block($inv['invoice_no'], $total); ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <?php echo tq_spam_notice(array('compact' => true, 'id' => 'tq-spam-ipay')); ?>
    </div>

    <aside class="co-side">
      <div class="icard icard--sticky">
        <h3 class="co-side__h"><?php echo t('الخطوة الأخيرة'); ?></h3>
        <p class="co-side__plan"><?php echo html_escape($tq_what); ?></p>
        <p class="co-side__total">
          <span><?php echo t('الإجمالي'); ?></span>
          <b><?php echo tqs_money($total); ?></b>
        </p>

        <?php if ($closed === '' && $tq_card): ?>
          <button type="submit" class="btn btn--primary btn--block"><?php echo t('ادفع في صفحة تاب الآمنة'); ?></button>
        <?php endif; ?>

        <a class="co-side__back" href="<?php echo base_url($tq_back); ?>">
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          <?php echo t('رجوع'); ?>
        </a>
      </div>
    </aside>
  </form>
</section>
