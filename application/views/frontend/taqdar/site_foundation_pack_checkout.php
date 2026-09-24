<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * TQ-FND-PACK — تأكيد شراء باقة حصص تأسيس.
 *
 * أخت `site_book_checkout.php` و`site_course_checkout.php` بمفرداتها
 * نفسها (`co-*`)، ولا ورقة رابعة: أربع شاشات دفع تختلف في الشكل تجعل
 * المشتري يشك في إحداها، وهو آخر ما يحسن أن يشك فيه.
 *
 * وما يعرض هنا **هو ما يكتب في القاعدة**: الثمن والعدد والمدة كلها من
 * `pack_offer()`، و`subscribe_foundation_pack()` تنادي `pack_offer()`
 * نفسها. أرسل المتصفح ما أرسل، المحرك يقرأ من عنده — فلا تشترى بريالين
 * باقة ثمنها مئتان.
 *
 * ═══ والفرق الذي يستحق أن يقال ═══
 *
 * الكتاب والكورس **محتوى يفتح**: يدفع صاحبه فيجد ما اشتراه في مكتبته
 * في اللحظة. وهذه **رصيد ساعات**: لا تفتح درسا واحدا، ولا تحجز موعدا
 * واحدا — يشتريها الطالب فيصير له عدد حصص يحجز بها **بنفسه** متى فتح
 * معلمو المسار أوقاتا.
 *
 * ومن ظن أنه اشترى جدولا محجوزا ينتظر رسالة لا تجيء، ثم يطلب استردادا
 * ويقول إن المنصة أوهمته. فيقال ذلك ثلاث مرات في هذه الشاشة: في الملخص،
 * وتحت الزر، وفي خطوات ما بعد الدفع — لا تخويفا من الشراء، بل لأن هذا
 * بعينه هو ما يفترق فيه هذا المنتج عن الثلاثة قبله.
 */
$o = isset($tq_offer) ? $tq_offer : null;
if (!$o || empty($o['sellable'])) return;

$track    = isset($tq_track) ? $tq_track : (isset($o['track']) ? $o['track'] : array());
$pid      = (int) $o['id'];
$credits  = isset($tq_credits) ? $tq_credits : array();

/* TQ-COUPON — حال الكود من المتحكم، والإجمالي في كل موضع يطبع منها. */
$tq_cpn = isset($tq_cpn) ? $tq_cpn : array('show' => false, 'applied' => false);
$tq_net = tq_coupon_net($tq_cpn, (int) $o['price']);

$tq_card = !empty($tq_card);
$tq_test = !empty($tq_card_test);
$tq_bank = (tqs_bank() !== null) || !$tq_card;
$tq_both = $tq_card && $tq_bank;

/* TQ-QUICK-BUY — الزائر يرى الشاشة كاملة ومعها بطاقة «بياناتك». */
$tq_guest = !empty($tq_guest);

$CI = get_instance();

$slug      = trim((string) ($track['slug'] ?? '')) !== '' ? (string) $track['slug'] : (string) $pid;
$track_url = base_url('foundation/' . rawurlencode($slug));

/* مدة الرصيد بعبارة تقرأ لا بعدد أيام عار. و«لا ينتهي» تقال صراحة: هي
   أقوى ما في هذا العرض متى كانت، وتركها ضمنا يضيعها. */
$valid = ((int) $o['days'] > 0)
       ? (t('تحجز حصصك خلال ') . (int) $o['days'] . t(' يوما من الدفع'))
       : t('رصيد لا ينتهي — تحجز حصصك متى شئت');

/* ما بقي له من قبل — يعرض قبل أن يشتري. من له ثلاث حصص لم يحجزها قد لا
   يحتاج باقة ثانية اليوم، وشاشة تخفي ذلك تبيع له ما لا يستعمله. */
$have = 0;
foreach ($credits as $c) $have += (int) $c['left'];
?>

<section class="page-hero page-hero--checkout">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <nav class="crumbs" aria-label="<?php echo t('مسار التصفح'); ?>">
      <a href="<?php echo base_url('foundation'); ?>"><?php echo t('التأسيس'); ?></a> ›
      <a href="<?php echo html_escape($track_url); ?>"><?php echo html_escape($track['name'] ?? ''); ?></a> ›
      <span aria-current="page"><?php echo t('تأكيد الشراء'); ?></span>
    </nav>
    <h1><?php echo t('تأكيد الشراء'); ?></h1>
    <p class="page-hero__lead"><?php echo t('راجع ما ستشتريه، ثم أكد — ولن يخصم شيء تلقائيا.'); ?></p>
  </div>
</section>

<section class="section section--tight">
  <?php /* النموذج يغلف العمودين لا الأيسر وحده: طريقة الدفع تختار في
           بطاقة الملخص والزر في البطاقة اللاصقة. */ ?>
  <?php /* TQ-QUICK-BUY — الزائر يشتري من النموذج نفسه ووجهته `checkout-quick`. */ ?>
  <form class="shell co-cols" method="post" id="tqCheckout"
        action="<?php echo base_url($tq_guest ? 'checkout-quick' : 'student/buy-foundation'); ?>"
        data-tq-item="pack:<?php echo $pid; ?>">
    <?php echo tq_csrf(); ?>
    <?php /* `pack_id` وحده يرسل، والثمن يقرأ في المحرك من `pack_offer()`. */ ?>
    <input type="hidden" name="pack_id" value="<?php echo $pid; ?>">

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
            'kind'  => 'pack',
            'back'  => 'foundation-checkout/' . $pid,
            'title' => $o['name'],
            'total' => tq_coupon_total($tq_cpn, (int) $o['price']),
        )); ?>
      <?php endif; ?>

      <?php if ($have > 0): ?>
        <?php /* رصيد قائم: يقال ولا يمنع الشراء — من يريد باقة ثانية فوق
                 رصيده يشتريها، والرصيدان يقرآن معا ويخصم من أقربهما أجلا.
                 والمنع هنا كان يجعل من انتهت حصصه ينتظر انتهاء أجل صف
                 فارغ. */ ?>
        <p class="tq-flash tq-flash--ok" role="status">
          <?php echo t('لديك'); ?> <b><?php echo (int) $have; ?></b>
          <?php echo t('حصة لم تحجزها بعد في هذا المسار. وشراؤك اليوم يضاف إليها ولا يلغيها — ويخصم من أقربها انتهاء أولا.'); ?>
        </p>
      <?php endif; ?>

      <div class="icard co-summary">
        <h2><?php echo t('ملخص الطلب'); ?></h2>

        <div class="co-item">
          <span class="co-item__img fndco-badge" aria-hidden="true">
            <b><?php echo (int) $o['sessions']; ?></b>
            <small><?php echo t('حصة'); ?></small>
          </span>
          <div class="co-item__b">
            <h3><?php echo html_escape($o['name']); ?></h3>
            <p class="tq-caption"><?php echo html_escape($track['name'] ?? t('التأسيس')); ?></p>
            <p class="co-item__dur">
              <svg aria-hidden="true"><use href="#i-clock"></use></svg>
              <?php echo html_escape($valid); ?>
            </p>
          </div>
          <p class="co-item__price">
            <?php echo tqs_money((int) $o['price']); ?>
            <?php if ((int) $o['save'] > 0): ?>
              <small><?php echo t('بدل'); ?> <?php echo number_format((int) $o['list'] / 100); ?> <?php echo t('ر.س'); ?>
                — <?php echo t('توفير ____٪', array((string) (int) $o['save_pct'])); ?></small>
            <?php endif; ?>
          </p>
        </div>

        <?php /* «ما تفتحه» هنا ليس محتوى: هو **عدد ساعات** وثمن الساعة
                 فيها. والمقارنة بسعر الحصة المفردة هي ما يقنع — ورقمان
                 متجاوران بلا جسر يجعلان المشتري يوازن ولا يعرف بكم. */ ?>
        <p class="co-opens">
          <b><?php echo t('ما تفتحه:'); ?></b>
          <?php echo tqs_ar_count((int) $o['sessions'], array(t('حصة'), t('حصتين'), t('حصص'), t('حصة'))); ?>
          <?php echo t('فردية مباشرة في'); ?>
          <?php echo html_escape($track['name'] ?? t('التأسيس')); ?>
          — <?php echo t('الحصة فيها'); ?> <?php echo tqs_money((int) $o['unit']); ?>
          <?php if ((int) $o['single'] > 0): ?>
            <?php echo t('بدل'); ?> <?php echo tqs_money((int) $o['single']); ?> <?php echo t('مفردة'); ?>
          <?php endif; ?>
          <a href="<?php echo html_escape($track_url); ?>"><?php echo t('عد إلى صفحة المسار'); ?></a>
        </p>

        <dl class="co-total">
          <div><dt><?php echo t('ثمن الباقة'); ?></dt><dd><?php echo tqs_money((int) $o['price']); ?></dd></div>
          <?php if ((int) $o['save'] > 0): ?>
            <div><dt><?php echo t('توفيرك عن الحصص المفردة'); ?></dt>
                 <dd>−<?php echo tqs_money((int) $o['save']); ?></dd></div>
          <?php endif; ?>
          <?php /* لا ضريبة تضاف هنا: `issue_invoice()` تكتب `tax = 0` ما
                   لم تضبط، ورقم في العرض لا يقابله صف في الفاتورة يوقع
                   في نزاع. */ ?>
          <?php echo tq_coupon_row($tq_cpn); ?>
          <div class="co-total__f"><dt><?php echo t('الإجمالي'); ?></dt>
               <dd><?php echo tq_coupon_total($tq_cpn, (int) $o['price']); ?></dd></div>
        </dl>

        <?php /* **الحد يقال قبل الدفع لا بعده**: هذه ساعات تحجزها بنفسك،
                 لا مواعيد محجوزة ولا محتوى يفتح. وهو الوجه المقابل لسطر
                 «الباقة تفتح المرحلة كاملة» في شاشة الباقة. */ ?>
        <p class="co-cycle">
          <?php echo t('هذا شراء'); ?> <b><?php echo t('رصيد حصص'); ?></b> —
          <?php echo t('تحجز به بنفسك من شاشة «التأسيس» في بوابتك، مع أي معلم في هذا المسار ومتى فتح وقتا.'); ?>
          <?php echo t('ولا يحجز لك مواعيد سلفا، ولا يفتح دروسا مسجلة.'); ?>
          <?php echo html_escape($valid); ?><?php echo t('، بلا تجديد ولا خصم متكرر.'); ?>
          <a href="<?php echo base_url('plans'); ?>"><?php echo t('وإن أردت منهج صفك كاملا بدروسه واختباراته، شاهد الباقات'); ?></a>.
        </p>
      </div>

      <?php echo tq_coupon_box($tq_cpn, array('here' => site_url('foundation-checkout/' . $pid))); ?>

      <div class="icard">
        <h2><?php echo t('طريقة الدفع'); ?></h2>

        <?php /* TQ-EXPRESS-PAY — Apple Pay · Google Pay · البطاقة هنا، للداخل وحده. */ ?>
        <?php if ($tq_guest): ?>
          <?php echo tq_quick_express_note(); ?>
        <?php else: ?>
          <?php echo tq_express_pay(array(
              'amount' => (int) $o['price'],
              'coupon' => $tq_cpn,
              'form'   => 'tqCheckout',
              'label'  => $o['name'],
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
                  <span class="tq-caption"><?php echo t('دفع فوري في صفحة تاب الآمنة، ويفتح رصيدك في لحظته.'); ?></span>
                </span>
              </span>
            </label>

            <label class="co-opt">
              <input type="radio" name="pay_method" value="bank" data-tq-pay="bank">
              <span class="co-opt__box">
                <span class="co-opt__ico" aria-hidden="true"><svg><use href="#i-bank"></use></svg></span>
                <span class="co-opt__t">
                  <b><?php echo t('تحويل بنكي'); ?></b>
                  <span class="tq-caption"><?php echo t('تصدر فاتورتك برقم مرجعي، ويفتح رصيدك بعد التحقق من الحوالة.'); ?></span>
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

          <?php /* بلا `hidden` في الوسم: من تعثر عنده JS يرى بيانات
                   التحويل ظاهرة ويستطيع أن يحول. */ ?>
          <div class="co-bank" data-tq-for="bank">
            <?php echo tqs_bank_block(); ?>
          </div>

        <?php elseif ($tq_card): ?>
          <p class="co-pay">
            <span class="co-pay__pick">
              <svg aria-hidden="true"><use href="#i-card"></use></svg>
              <b><?php echo t('بطاقة — مدى أو فيزا أو ماستركارد'); ?></b>
            </span>
            <span class="tq-caption"><?php echo t('دفع فوري في صفحة تاب الآمنة، ويفتح رصيدك في لحظته.'); ?></span>
          </p>
          <input type="hidden" name="pay_method" value="tap">
          <?php if ($tq_test): ?>
            <p class="co-pay__test">
              <svg aria-hidden="true"><use href="#i-shield"></use></svg>
              <?php echo t('البوابة في'); ?> <b><?php echo t('وضع الاختبار'); ?></b>
              <?php echo t('الآن: الدفع لا يخصم مالا حقيقيا.'); ?>
            </p>
          <?php endif; ?>

        <?php else: ?>
          <p class="co-pay">
            <span class="co-pay__pick">
              <svg aria-hidden="true"><use href="#i-bank"></use></svg>
              <b><?php echo t('تحويل بنكي'); ?></b>
            </span>
            <span class="tq-caption">
              <?php echo t('تصدر فاتورتك فورا برقم مرجعي، ويفتح رصيدك بعد التحقق من الحوالة.'); ?>
            </span>
          </p>
          <?php echo tqs_bank_block(); ?>
        <?php endif; ?>
      </div>

      <?php echo tq_spam_notice(array('compact' => true, 'id' => 'tq-spam-fpco')); ?>

    </div>

    <aside class="co-side">
      <div class="icard icard--sticky">
        <h3 class="co-side__h"><?php echo t('الخطوة الأخيرة'); ?></h3>

        <?php /* وخطوات ما بعد الدفع تقول **أن الحجز يقع بعده**: من قرأ
                 «يفتح فورا» وحدها ينتظر جدولا لا يجيء. */ ?>
        <?php if ($tq_card): ?>
          <ol class="co-steps" data-tq-for="tap">
            <li><b>١</b> <?php echo t('تنتقل إلى صفحة تاب الآمنة'); ?></li>
            <li><b>٢</b> <?php echo t('تدفع ببطاقتك'); ?></li>
            <li><b>٣</b> <?php echo t('يفتح رصيدك فورا'); ?></li>
            <li><b>٤</b> <?php echo t('تحجز حصصك بنفسك من شاشة «التأسيس»'); ?></li>
          </ol>
        <?php endif; ?>

        <?php if ($tq_bank): ?>
          <ol class="co-steps" data-tq-for="bank">
            <li><b>١</b> <?php echo t('تؤكد الشراء وتصدر فاتورتك'); ?></li>
            <li><b>٢</b> <?php echo t('تحول المبلغ بمرجع الفاتورة'); ?></li>
            <li><b>٣</b> <?php echo t('يفتح رصيدك بعد التحقق'); ?></li>
            <li><b>٤</b> <?php echo t('تحجز حصصك بنفسك من شاشة «التأسيس»'); ?></li>
          </ol>
        <?php endif; ?>

        <p class="co-side__total">
          <span><?php echo t('الإجمالي'); ?></span>
          <b><?php echo tq_coupon_total($tq_cpn, (int) $o['price']); ?></b>
        </p>
        <?php /* البطاقة اللاصقة آخر ما تقرأه العين قبل الزر، ومن نزل
                 إليها مباشرة لا يمر على الملخص — فحد ما اشتراه يقال هنا
                 كذلك لا في الملخص وحده. */ ?>
        <p class="co-side__cycle">
          <?php echo tqs_ar_count((int) $o['sessions'], array(t('حصة'), t('حصتين'), t('حصص'), t('حصة'))); ?>
          <?php echo t('تحجزها بنفسك.'); ?>
          <?php echo html_escape($valid); ?>.
        </p>

        <?php echo tq_quick_side_button($tq_guest, $tq_card, t('تأكيد الشراء وإصدار الفاتورة')); ?>

        <p class="tq-caption co-side__note">
          <?php echo t('لا تجديد تلقائي ولا خصم متكرر. وبتأكيدك توافق على'); ?>
          <a href="<?php echo base_url('terms'); ?>"><?php echo t('الشروط'); ?></a>
          <?php echo t('و'); ?><a href="<?php echo base_url('refund'); ?>"><?php echo t('سياسة الاسترداد'); ?></a>.
        </p>

        <a class="co-side__back" href="<?php echo html_escape($track_url); ?>">
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          <?php echo t('عودة إلى صفحة المسار'); ?>
        </a>
      </div>
    </aside>

  </form>
  <?php if ($tq_guest) echo tq_quick_login_form('foundation-checkout/' . $pid); ?>
</section>
<?php if ($tq_guest) echo tq_quick_script(); ?>

<?php if ($tq_both): ?>
<?php /* تحسين تدريجي بحتا — النسخة نفسها في شاشتي الكتاب والكورس: لا
         `hidden` في الوسم، فمن تعثر عنده هذا الملف يرى الطريقتين وبيانات
         التحويل والزر كلها عاملة، و`radio` يرسل نفسه. */ ?>
<script>
(function () {
  var form = document.getElementById('tqCheckout');
  if (!form) return;

  var only   = form.querySelectorAll('[data-tq-for]');
  var submit = form.querySelector('[data-tq-submit]');

  function apply() {
    var picked = form.querySelector('input[name="pay_method"]:checked');
    var mode   = picked ? picked.value : 'tap';

    for (var i = 0; i < only.length; i++) {
      only[i].hidden = (only[i].getAttribute('data-tq-for') !== mode);
    }
    if (submit) {
      var label = submit.getAttribute('data-tq-label-' + mode);
      if (label) submit.textContent = label;
    }
  }

  form.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'pay_method') apply();
  });
  apply();
})();
</script>
<?php endif; ?>

<?php /* TQ-META-CAPI — «بلغ شاشة التاكيد»، بالرمز الذي يرسله الخادم. */ ?>
<?php echo tq_meta_checkout('fndpack-' . $pid, $o['name'], $tq_net); ?>
