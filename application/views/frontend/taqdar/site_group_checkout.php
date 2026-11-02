<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * TQ-GROUP — تأكيد الانضمام الى مجموعة مباشرة.
 *
 * اخت `site_book_checkout.php` بمفرداتها (`co-*`) وبطاقاتها الثلاث:
 * `tq_quick_card()` للزائر، و`tq_coupon_box()`، و`tq_express_pay()`.
 * والسعر من `offer()` **بالهللات**، و`subscribe_group()` تنادي `offer()`
 * نفسها — فما يعد به الزر هو ما تقيده الفاتورة.
 *
 * ويقال قبل الدفع ما يشترى: **جدول بمواعيد معلنة** لا وصول مفتوح، ومقعد
 * يحجز ساعات حتى تدفع، وان الغيت المجموعة قبل اول حصة فالرد يدوي خارج
 * المنصة — لا وعد برد تلقائي لا مسار له.
 */
$o   = isset($tq_offer) ? $tq_offer : null;
$sum = isset($tq_sum) ? $tq_sum : null;
if (!$o || empty($o['sellable']) || !$sum) return;

$gid   = (int) $o['id'];
$price = (int) $o['price_halalas'];
$tq_cpn = isset($tq_cpn) ? $tq_cpn : array('show' => false, 'applied' => false);
$tq_net = tq_coupon_net($tq_cpn, $price);

$tq_card  = !empty($tq_card);
$tq_test  = !empty($tq_card_test);
$tq_bank  = (tqs_bank() !== null) || !$tq_card;
$tq_both  = $tq_card && $tq_bank;
$tq_guest = !empty($tq_guest);

$CI = get_instance();
$CI->load->model('taqdar_group_model', 'tq_grp_v');
$hold = (int) $CI->tq_grp_v->hold_hours();
$url  = base_url('group/' . rawurlencode($sum['slug']));
?>

<section class="page-hero page-hero--checkout">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <nav class="crumbs" aria-label="<?php echo t('مسار التصفح'); ?>">
      <a href="<?php echo base_url('groups'); ?>"><?php echo t('المجموعات المباشرة'); ?></a> ›
      <a href="<?php echo html_escape($url); ?>"><?php echo html_escape($o['title']); ?></a> ›
      <span aria-current="page"><?php echo t('تأكيد الانضمام'); ?></span>
    </nav>
    <h1><?php echo t('تأكيد الانضمام'); ?></h1>
    <p class="page-hero__lead"><?php echo t('راجع الجدول والمبلغ، ثم أكد — ولن يخصم شيء تلقائيا.'); ?></p>
  </div>
</section>

<section class="section section--tight">
  <form class="shell co-cols" method="post" id="tqCheckout"
        action="<?php echo base_url($tq_guest ? 'checkout-quick' : 'student/buy-group'); ?>"
        data-tq-item="group:<?php echo $gid; ?>">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="group_id" value="<?php echo $gid; ?>">

    <div class="co-main">
      <?php if ($m = $CI->session->flashdata('error_message')): ?>
        <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($m); ?></p>
      <?php endif; ?>
      <?php if ($m = $CI->session->flashdata('flash_message')): ?>
        <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($m); ?></p>
      <?php endif; ?>
      <?php echo tq_quick_welcome(); ?>

      <?php if ($tq_guest): ?>
        <?php echo tq_quick_card(array(
            'kind'  => 'group',
            'back'  => 'group-checkout/' . $gid,
            'title' => $o['title'],
            'total' => tq_coupon_total($tq_cpn, $price),
        )); ?>
      <?php endif; ?>

      <div class="icard co-summary">
        <h2><?php echo t('ملخص الطلب'); ?></h2>
        <div class="co-item">
          <div class="co-item__b">
            <h3><?php echo html_escape($o['title']); ?></h3>
            <p class="co-item__dur">
              <svg aria-hidden="true"><use href="#i-calendar"></use></svg>
              <?php echo html_escape(tq_count_units((int) $sum['sessions_total'], 'حصة', 'حصتان', 'حصتين', 'حصص', 'حصة', '', 'nom')); ?>
              <?php if ($sum['first_starts_at']): ?>
                — <?php echo t('أولها'); ?> <b class="tq-ltr"><?php echo html_escape(date('Y-m-d H:i', strtotime($sum['first_starts_at']) + 3 * 3600)); ?></b>
                <?php echo t('(توقيت مكة)'); ?>
              <?php endif; ?>
            </p>
            <p class="tq-caption"><?php echo html_escape($sum['seats_text']); ?></p>
          </div>
          <p class="co-item__price"><?php echo tqs_money($price); ?></p>
        </div>

        <dl class="co-total">
          <div><dt><?php echo t('ثمن العضوية'); ?></dt><dd><?php echo tqs_money($price); ?></dd></div>
          <?php echo tq_coupon_row($tq_cpn); ?>
          <div class="co-total__f"><dt><?php echo t('الإجمالي'); ?></dt><dd><?php echo tq_coupon_total($tq_cpn, $price); ?></dd></div>
        </dl>

        <p class="co-cycle">
          <?php echo t('هذا انضمام إلى'); ?> <b><?php echo t('جدول هذه المجموعة'); ?></b> —
          <?php echo t('حصص مباشرة بمواعيدها المعلنة، تدخلها من بوابتك، وتبقى تسجيلاتها لك.'); ?>
          <?php echo t('ومقعدك يحجز ____ ساعة بعد إصدار الفاتورة، ثم يعود لغيرك إن لم تدفع.', array($hold)); ?>
          <a href="<?php echo html_escape($url); ?>"><?php echo t('راجع الجدول كاملا'); ?></a>.
        </p>
        <p class="co-cycle">
          <?php echo t('وإن ألغيت المجموعة قبل أول حصة تلغى عضويتك ويرد إليك ما دفعت يدويا خارج المنصة بعد التواصل معك — لا ردا تلقائيا إلى بطاقتك.'); ?>
        </p>
      </div>

      <?php echo tq_coupon_box($tq_cpn, array('here' => site_url('group-checkout/' . $gid))); ?>

      <div class="icard">
        <h2><?php echo t('طريقة الدفع'); ?></h2>
        <?php if ($tq_guest): ?>
          <?php echo tq_quick_express_note(); ?>
        <?php else: ?>
          <?php echo tq_express_pay(array(
              'amount' => $price,
              'coupon' => $tq_cpn,
              'form'   => 'tqCheckout',
              'label'  => $o['title'],
          )); ?>
        <?php endif; ?>

        <?php if ($tq_both): ?>
          <div class="co-pick">
            <label class="co-opt">
              <input type="radio" name="pay_method" value="tap" checked data-tq-pay="tap">
              <span class="co-opt__box">
                <span class="co-opt__ico" aria-hidden="true"><svg><use href="#i-card"></use></svg></span>
                <span class="co-opt__t">
                  <b><?php echo t('بطاقة — مدى أو فيزا أو ماستركارد'); ?></b>
                  <span class="tq-caption"><?php echo t('دفع فوري في صفحة تاب الآمنة، وتفعل عضويتك في لحظتها.'); ?></span>
                </span>
              </span>
            </label>
            <label class="co-opt">
              <input type="radio" name="pay_method" value="bank" data-tq-pay="bank">
              <span class="co-opt__box">
                <span class="co-opt__ico" aria-hidden="true"><svg><use href="#i-bank"></use></svg></span>
                <span class="co-opt__t">
                  <b><?php echo t('تحويل بنكي'); ?></b>
                  <span class="tq-caption"><?php echo t('تصدر فاتورتك برقم مرجعي، وتفعل عضويتك بعد التحقق من الحوالة.'); ?></span>
                </span>
              </span>
            </label>
          </div>
          <?php if ($tq_test): ?>
            <p class="co-pay__test" data-tq-for="tap">
              <svg aria-hidden="true"><use href="#i-shield"></use></svg>
              <?php echo t('البوابة في'); ?> <b><?php echo t('وضع الاختبار'); ?></b>
              <?php echo t('الآن: الدفع لا يخصم مالا حقيقيا.'); ?>
            </p>
          <?php endif; ?>
          <div class="co-bank" data-tq-for="bank"><?php echo tqs_bank_block(); ?></div>
        <?php elseif ($tq_card): ?>
          <p class="co-pay">
            <span class="co-pay__pick">
              <svg aria-hidden="true"><use href="#i-card"></use></svg>
              <b><?php echo t('بطاقة — مدى أو فيزا أو ماستركارد'); ?></b>
            </span>
          </p>
          <input type="hidden" name="pay_method" value="tap">
        <?php else: ?>
          <p class="co-pay">
            <span class="co-pay__pick">
              <svg aria-hidden="true"><use href="#i-bank"></use></svg>
              <b><?php echo t('تحويل بنكي'); ?></b>
            </span>
            <span class="tq-caption"><?php echo t('تصدر فاتورتك فورا برقم مرجعي، وتفعل عضويتك بعد التحقق من الحوالة.'); ?></span>
          </p>
          <?php echo tqs_bank_block(); ?>
        <?php endif; ?>
      </div>

      <?php echo tq_spam_notice(array('compact' => true, 'id' => 'tq-spam-gco')); ?>
    </div>

    <aside class="co-side">
      <div class="icard icard--sticky">
        <h3 class="co-side__h"><?php echo t('الخطوة الأخيرة'); ?></h3>
        <p class="co-side__total">
          <span><?php echo t('الإجمالي'); ?></span>
          <b><?php echo tq_coupon_total($tq_cpn, $price); ?></b>
        </p>
        <p class="co-side__cycle"><?php echo t('جدول هذه المجموعة وحدها، بلا تجديد ولا خصم متكرر.'); ?></p>
        <?php echo tq_quick_side_button($tq_guest, $tq_card, t('تأكيد الانضمام وإصدار الفاتورة')); ?>
        <p class="tq-caption co-side__note">
          <?php echo t('وبتأكيدك توافق على'); ?>
          <a href="<?php echo base_url('terms'); ?>"><?php echo t('الشروط'); ?></a>
          <?php echo t('و'); ?><a href="<?php echo base_url('refund'); ?>"><?php echo t('سياسة الاسترداد'); ?></a>.
        </p>
        <a class="co-side__back" href="<?php echo html_escape($url); ?>">
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          <?php echo t('عودة إلى صفحة المجموعة'); ?>
        </a>
      </div>
    </aside>
  </form>
  <?php if ($tq_guest) echo tq_quick_login_form('group-checkout/' . $gid); ?>
</section>
<?php if ($tq_guest) echo tq_quick_script(); ?>

<?php if ($tq_both): ?>
<script>
(function () {
  var form = document.getElementById('tqCheckout');
  if (!form) return;
  var only = form.querySelectorAll('[data-tq-for]');
  function apply() {
    var picked = form.querySelector('input[name="pay_method"]:checked');
    var mode = picked ? picked.value : 'tap';
    for (var i = 0; i < only.length; i++) only[i].hidden = (only[i].getAttribute('data-tq-for') !== mode);
  }
  form.addEventListener('change', function (e) { if (e.target && e.target.name === 'pay_method') apply(); });
  apply();
})();
</script>
<?php endif; ?>

<?php echo tq_meta_checkout('group-' . $gid, $o['title'], $tq_net); ?>
