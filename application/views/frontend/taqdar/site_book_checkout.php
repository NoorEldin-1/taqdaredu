<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * TQ-BOOK — تأكيد شراء كتاب مفرد.
 *
 * أخت `site_course_checkout.php` بمفرداتها نفسها (`co-*`)، ولا ورقة
 * ثالثة: ثلاث شاشات دفع تختلف في الشكل تجعل المشتري يشك في إحداها،
 * وهو آخر ما يحسن أن يشك فيه.
 *
 * وما يعرض هنا **هو ما يكتب في القاعدة**: السعر من `offer()` لا من حقل
 * مخفي في النموذج، و`subscribe_book()` تنادي `offer()` نفسها. أرسل
 * المتصفح ما أرسل، المحرك يقرأ السعر من عنده — فلا يشترى بريالين ما
 * ثمنه مئتان.
 *
 * **والفرق الذي يستحق أن يقال**: الباقة تفتح منهج مرحلة، وهذه تفتح
 * **كتابا واحدا يقرأ**. فيقال ذلك صراحة في الملخص وتحت الزر معا — لا
 * ليقلل من الشراء، بل لأن من يظن أنه اشترى دروسا يكتشف ذلك بعد الدفع.
 * ويقال معه **أنه يقرأ ولا ينزل**: القارئ في البوابة يعرض صفحة صفحة،
 * ومن توقع ملفا في جهازه يطلب استردادا.
 */
$o = isset($tq_offer) ? $tq_offer : null;
if (!$o || empty($o['sellable'])) return;

$book    = isset($tq_book) ? $tq_book : array();
$bid     = (int) $o['book_id'];
$pending = isset($tq_pending) ? $tq_pending : null;

/* البطاقة تعرض إن كانت مضبوطة وحدها — والقرار جاء من المتحكم
   (`Taqdar_tap_model::ready()`) لا من القالب. */
$tq_card = !empty($tq_card);
$tq_test = !empty($tq_card_test);
$tq_bank = (tqs_bank() !== null) || !$tq_card;
$tq_both = $tq_card && $tq_bank;

$CI = get_instance();

$slug     = trim((string) $book['slug']) !== '' ? (string) $book['slug'] : (string) $bid;
$book_url = base_url('book/' . rawurlencode($slug));

$tones   = array('math', 'arabic', 'science', 'islamic', 'english');
$tone    = in_array((string) $book['tone'], $tones, true) ? (string) $book['tone'] : 'math';
$cover   = trim((string) $book['cover']);
$pages   = (int) $book['pages'];

/* أجل الوصول بعبارة يفهمها المشتري لا بعدد أيام. و«دائم» تقال صراحة:
   هي أقوى ما في هذا العرض، وتركها ضمنا يضيعها. */
$access = ((int) $o['days'] > 0)
        ? (t('وصول ') . tq_count_units((int) round($o['days'] / 30), 'شهر', 'شهران', 'شهرين',
                                       'أشهر', 'شهرا', '', 'obl'))
        : t('وصول دائم — يبقى في مكتبتك');
?>

<section class="page-hero page-hero--checkout">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <nav class="crumbs" aria-label="<?php echo t('مسار التصفح'); ?>">
      <a href="<?php echo base_url('books'); ?>"><?php echo t('الكتب'); ?></a> ›
      <a href="<?php echo html_escape($book_url); ?>"><?php echo html_escape($o['title']); ?></a> ›
      <span aria-current="page"><?php echo t('تأكيد الشراء'); ?></span>
    </nav>
    <h1><?php echo t('تأكيد الشراء'); ?></h1>
    <p class="page-hero__lead"><?php echo t('راجع ما ستشتريه، ثم أكد — ولن يخصم شيء تلقائيا.'); ?></p>
  </div>
</section>

<section class="section section--tight">
  <?php
  /* النموذج يغلف العمودين لا العمود الأيسر وحده: طريقة الدفع تختار في
     بطاقة الملخص والزر في البطاقة اللاصقة، ولو بقي النموذج في إحداهما
     لصار المنتقي خارجه — أي أن الاختيار لا يرسل. */
  ?>
  <form class="shell co-cols" method="post" action="<?php echo base_url('student/buy-book'); ?>"
        id="tqCheckout">
    <?php echo tq_csrf(); ?>
    <?php /* `book_id` وحده يرسل، والسعر يقرأ في المحرك من `offer()`. */ ?>
    <input type="hidden" name="book_id" value="<?php echo $bid; ?>">

    <div class="co-main">

      <?php if ($m = $CI->session->flashdata('error_message')): ?>
        <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($m); ?></p>
      <?php endif; ?>

      <?php if ($pending): ?>
        <?php /* فاتورة قائمة لم تسدد: يقال ذلك بدل أن يعرض زر شراء ثان
                 لمن اشترى قبل دقيقة — فيخرج بصفين وفاتورتين وتبقى
                 إحداهما «غير مدفوعة» أبدا. و`subscribe_book()` تعيد
                 استعمال الصف نفسه، فالزر أدناه لا يكرر شيئا. */ ?>
        <p class="tq-flash tq-flash--ok" role="status">
          <?php echo t('فاتورتك'); ?>
          <b class="tq-ltr"><?php echo html_escape($pending['invoice']['invoice_no']); ?></b>
          <?php echo t('صدرت ولم تسدد بعد. تأكيدك هنا يكملها ولا يصدر أخرى.'); ?>
        </p>
      <?php endif; ?>

      <div class="icard co-summary">
        <h2><?php echo t('ملخص الطلب'); ?></h2>

        <div class="co-item">
          <?php /* الغلاف يعرض دائما ولو لم ترفع صورة: الوجه المرسوم
                   بالمادة ولونها يفرق بين كتاب وكتاب، ومربع فارغ لا. */ ?>
          <span class="bookface bookface--sm co-item__img" data-tone="<?php echo $tone; ?>">
            <?php if ($cover !== ''): ?>
              <img src="<?php echo html_escape(tqs_img($cover, 'subj-math')); ?>"
                   alt="" width="220" height="308" loading="lazy" decoding="async">
            <?php else: ?>
              <span class="book-card__spine" aria-hidden="true"></span>
              <span class="book-card__label"><?php echo html_escape((string) $book['subject'] !== ''
                  ? $book['subject'] : $book['title']); ?></span>
            <?php endif; ?>
          </span>
          <div class="co-item__b">
            <h3><?php echo html_escape($o['title']); ?></h3>
            <?php if (trim((string) $book['author']) !== ''): ?>
              <p class="tq-caption"><?php echo html_escape($book['author']); ?></p>
            <?php endif; ?>
            <p class="co-item__dur">
              <svg aria-hidden="true"><use href="#i-lock"></use></svg>
              <?php echo html_escape($access); ?>
            </p>
          </div>
          <p class="co-item__price">
            <?php echo tqs_money((int) $o['price']); ?>
            <?php if ((int) $o['list_price'] > 0): ?>
              <small><?php echo t('بدل'); ?> <?php echo number_format((int) $o['list_price'] / 100); ?> <?php echo t('ر.س'); ?>
                — <?php echo t('خصم'); ?> <?php echo (int) $o['off']; ?>٪</small>
            <?php endif; ?>
          </p>
        </div>

        <?php
        $facts = array();
        if ($pages > 0)   $facts[] = tq_count_units($pages, 'صفحة', 'صفحتان', 'صفحتين',
                                                    'صفحات', 'صفحة', '', 'nom');
        if (trim((string) $book['subject']) !== '') $facts[] = (string) $book['subject'];
        if ($facts):
        ?>
          <p class="co-opens"><b><?php echo t('ما تفتحه:'); ?></b> <?php echo html_escape(implode(' · ', $facts)); ?>
            <a href="<?php echo html_escape($book_url); ?>"><?php echo t('عد إلى صفحة الكتاب'); ?></a>
          </p>
        <?php endif; ?>

        <dl class="co-total">
          <div><dt><?php echo t('سعر الكتاب'); ?></dt><dd><?php echo tqs_money((int) $o['price']); ?></dd></div>
          <?php /* لا ضريبة تضاف هنا: `issue_invoice()` تكتب `tax = 0` ما
                   لم تضبط، ورقم في العرض لا يقابله صف في الفاتورة يوقع
                   في نزاع. */ ?>
          <div class="co-total__f"><dt><?php echo t('الإجمالي'); ?></dt><dd><?php echo tqs_money((int) $o['price']); ?></dd></div>
        </dl>

        <?php /* **الحدان يقالان قبل الدفع لا بعده.** الأول: كتاب واحد
                 لا منهج. والثاني: **يقرأ ولا ينزل** — القارئ في بوابتك
                 يعرض صفحة صفحة، ومن توقع ملفا في جهازه يطلب استردادا
                 ويقول إن المنصة أوهمته. وهو الوجه المقابل لسطر «الباقة
                 تفتح المرحلة كاملة» في شاشة الباقة. */ ?>
        <p class="co-cycle">
          <?php echo t('هذا شراء'); ?> <b><?php echo t('هذا الكتاب وحده'); ?></b> —
          <?php echo t('يقرأ في مكتبتك صفحة صفحة داخل المنصة، ولا ينزل ملفا على جهازك.'); ?>
          <?php echo html_escape($access); ?><?php echo t('، بلا تجديد ولا خصم متكرر.'); ?>
          <a href="<?php echo base_url('plans'); ?>"><?php echo t('وإن أردت كتب المرحلة وبرامجها، شاهد الباقات'); ?></a>.
        </p>
      </div>

      <div class="icard">
        <h2><?php echo t('طريقة الدفع'); ?></h2>

        <?php if ($tq_both): ?>
          <div class="co-pick">
            <label class="co-opt">
              <input type="radio" name="pay_method" value="tap" checked data-tq-pay="tap">
              <span class="co-opt__box">
                <span class="co-opt__ico" aria-hidden="true"><svg><use href="#i-card"></use></svg></span>
                <span class="co-opt__t">
                  <b><?php echo t('بطاقة — مدى أو فيزا أو ماستركارد'); ?></b>
                  <span class="tq-caption"><?php echo t('دفع فوري في صفحة تاب الآمنة، ويفتح الكتاب في لحظته.'); ?></span>
                </span>
              </span>
            </label>

            <label class="co-opt">
              <input type="radio" name="pay_method" value="bank" data-tq-pay="bank">
              <span class="co-opt__box">
                <span class="co-opt__ico" aria-hidden="true"><svg><use href="#i-bank"></use></svg></span>
                <span class="co-opt__t">
                  <b><?php echo t('تحويل بنكي'); ?></b>
                  <span class="tq-caption"><?php echo t('تصدر فاتورتك برقم مرجعي، ويفتح الكتاب بعد التحقق من الحوالة.'); ?></span>
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
            <span class="tq-caption"><?php echo t('دفع فوري في صفحة تاب الآمنة، ويفتح الكتاب في لحظته.'); ?></span>
          </p>
          <?php /* والحقل المخفي يقوم مقام المنتقي: بلا `pay_method` يقرأ
                   المحرك «تحويل بنكي» فتصدر فاتورة لا حساب يحول إليه. */ ?>
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
              <?php echo t('تصدر فاتورتك فورا برقم مرجعي، ويفتح الكتاب بعد التحقق من الحوالة.'); ?>
            </span>
          </p>
          <?php echo tqs_bank_block(); ?>
        <?php endif; ?>
      </div>

      <?php echo tq_spam_notice(array('compact' => true, 'id' => 'tq-spam-bco')); ?>

    </div>

    <aside class="co-side">
      <div class="icard icard--sticky">
        <h3 class="co-side__h"><?php echo t('الخطوة الأخيرة'); ?></h3>

        <?php if ($tq_card): ?>
          <ol class="co-steps" data-tq-for="tap">
            <li><b>١</b> <?php echo t('تنتقل إلى صفحة تاب الآمنة'); ?></li>
            <li><b>٢</b> <?php echo t('تدفع ببطاقتك'); ?></li>
            <li><b>٣</b> <?php echo t('يفتح الكتاب في مكتبتك فورا'); ?></li>
          </ol>
        <?php endif; ?>

        <?php if ($tq_bank): ?>
          <ol class="co-steps" data-tq-for="bank">
            <li><b>١</b> <?php echo t('تؤكد الشراء وتصدر فاتورتك'); ?></li>
            <li><b>٢</b> <?php echo t('تحول المبلغ بمرجع الفاتورة'); ?></li>
            <li><b>٣</b> <?php echo t('يفتح الكتاب بعد التحقق'); ?></li>
          </ol>
        <?php endif; ?>

        <p class="co-side__total">
          <span><?php echo t('الإجمالي'); ?></span>
          <b><?php echo tqs_money((int) $o['price']); ?></b>
        </p>
        <?php /* البطاقة اللاصقة آخر ما تقرأه العين قبل الزر، ومن نزل
                 إليها مباشرة لا يمر على الملخص — فحد ما اشتراه يقال
                 هنا كذلك لا في الملخص وحده. */ ?>
        <p class="co-side__cycle">
          <?php echo t('هذا الكتاب وحده — يقرأ في مكتبتك.'); ?> <?php echo html_escape($access); ?>.
        </p>

        <button type="submit" class="btn btn--primary btn--block" data-tq-submit
                data-tq-label-tap="<?php echo t('تابع إلى الدفع الآمن'); ?>"
                data-tq-label-bank="<?php echo t('تأكيد الشراء وإصدار الفاتورة'); ?>">
          <?php echo $tq_card ? t('تابع إلى الدفع الآمن') : t('تأكيد الشراء وإصدار الفاتورة'); ?>
        </button>

        <p class="tq-caption co-side__note">
          <?php echo t('لا تجديد تلقائي ولا خصم متكرر. وبتأكيدك توافق على'); ?>
          <a href="<?php echo base_url('terms'); ?>"><?php echo t('الشروط'); ?></a>
          <?php echo t('و'); ?><a href="<?php echo base_url('refund'); ?>"><?php echo t('سياسة الاسترداد'); ?></a>.
        </p>

        <a class="co-side__back" href="<?php echo html_escape($book_url); ?>">
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          <?php echo t('عودة إلى صفحة الكتاب'); ?>
        </a>
      </div>
    </aside>

  </form>
</section>

<?php if ($tq_both): ?>
<?php /* تحسين تدريجي بحتا — النسخة نفسها في `site_course_checkout.php`:
         لا `hidden` في الوسم، فمن تعثر عنده هذا الملف يرى الطريقتين
         وبيانات التحويل والزر كلها عاملة، و`radio` يرسل نفسه. */ ?>
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
