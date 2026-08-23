<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * تأكيد الاشتراك — الشاشة الوحيدة بين الرغبة والدفع.
 *
 * لا سلة: السلة تجمع أصنافا، والطالب يشتري باقة واحدة تغطي سنته.
 * فلا شيء يجمع، وكل خطوة زائدة هنا تسقط مشترين بلا مقابل.
 *
 * وما يعرض هنا **هو ما يكتب في القاعدة**: السعر من `plans.price` لا من
 * حقل مخفي في النموذج. أرسل المتصفح ما أرسل، المحرك يقرأ سعر الباقة
 * من عنده — فلا يشترى بستة ريالات ما ثمنه ستمئة.
 */
$b = isset($tq_bundle) ? $tq_bundle : null;
if (!$b) return;

$cur   = isset($tq_current) ? $tq_current : null;
$t     = $b['totals'];
$blocked = ($cur && $cur['status'] === 'active');

/* البطاقة تعرض إن كانت مضبوطة وحدها — والقرار جاء من المتحكم
   (`Taqdar_tap_model::ready()`) لا من القالب. وحين لا تكون مضبوطة تعرض
   هذه الشاشة ما كانت تعرضه قبل تاب: التحويل البنكي وحده، بلا منتقي طريقة
   ولا خيار يفضي إلى خطأ. */
$tq_card = !empty($tq_card);
$tq_test = !empty($tq_card_test);

/* والتحويل البنكي يعرض إن كان له حساب منشور — أو إن لم تكن البطاقة مضبوطة.
   ولماذا الشرط الثاني: بلا طريقة واحدة صالحة لا يبقى إلا أن يقال الحق
   ويعطى باب يطرق، وهو ما يفعله `tqs_bank_block()` حين لا حساب. أما أن
   يعرض «تحويل بنكي» إلى حساب لم ينشر بينما البطاقة تعمل، فهو خيار ينتهي
   إلى لا شيء وسط شاشة فيها خيار يعمل. */
$tq_bank = (tqs_bank() !== null) || !$tq_card;
$tq_both = $tq_card && $tq_bank;
?>

<section class="page-hero page-hero--checkout">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url('plans'); ?>">الباقات</a> ›
      <a href="<?php echo base_url('plan/' . $b['code']); ?>"><?php echo html_escape(tqs_bundle_tier($b['name'])); ?></a> ›
      <span aria-current="page">تأكيد الاشتراك</span>
    </nav>
    <h1>تأكيد الاشتراك</h1>
    <p class="page-hero__lead">راجع ما ستشترك فيه، ثم أكد — ولن يخصم شيء تلقائيا.</p>
  </div>
</section>

<?php if ($blocked): ?>
<section class="section section--tight">
  <div class="shell co-cols">
    <div class="co-main">
      <?php /* اشتراك نشط قائم: `subscribe()` ترفض بحق، ورسالتها تصل
               بعد الضغط. وقولها هنا يمنع ضغطة تنتهي إلى خطأ. */ ?>
      <div class="icard co-blocked">
        <h2>لديك اشتراك نشط بالفعل</h2>
        <p>لا يمكن جمع اشتراكين في وقت واحد. أوقف تجديد اشتراكك الحالي
           أو انتظر انتهاء مدته، ثم عد إلى هنا.</p>
        <div class="co-blocked__acts">
          <a class="btn btn--primary" href="<?php echo base_url('student/subscription'); ?>">اشتراكي الحالي</a>
          <a class="btn btn--ghost" href="<?php echo base_url('student/bundle'); ?>">محتوى باقتي</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php else: ?>
<section class="section section--tight">
  <?php
  /* النموذج يغلف العمودين لا العمود الأيسر وحده.
     طريقة الدفع تختار في بطاقة الملخص والزر في البطاقة اللاصقة، ولو بقي
     النموذج في إحداهما لصار المنتقي خارجه — أي أن الاختيار لا يرسل، أو
     يرسل بحقل مخفي يحدثه JS فيسقط الاختيار كله متى تعثر ملف.
     و`<form>` حاوية شبكة صحيحة: الأصناف كما كانت على الحاوية نفسها. */
  ?>
  <form class="shell co-cols" method="post" action="<?php echo base_url('student/subscribe'); ?>"
        id="tqCheckout">
    <?php echo tq_csrf(); ?>
    <?php /* `plan_id` وحده يرسل، والسعر يقرأ في المحرك من الباقة. */ ?>
    <input type="hidden" name="plan_id" value="<?php echo (int) $b['plan_id']; ?>">

    <div class="co-main">

        <div class="icard co-summary">
          <h2>ملخص الطلب</h2>

          <div class="co-item">
            <?php if ((string) $b['image'] !== ''): ?>
              <img class="co-item__img" src="<?php echo tqs_asset_img($b['image'], 'path-primary'); ?>"
                   alt="" width="220" height="147" loading="lazy" decoding="async">
            <?php endif; ?>
            <div class="co-item__b">
              <h3><?php echo html_escape($b['name']); ?></h3>
              <?php if ($b['note'] !== ''): ?>
                <p class="tq-caption"><?php echo html_escape($b['note']); ?></p>
              <?php endif; ?>
              <p class="co-item__dur">
                <svg aria-hidden="true"><use href="#i-calendar"></use></svg>
                <?php echo html_escape(tqs_period_label($b['days'])); ?>
              </p>
            </div>
            <p class="co-item__price"><?php echo tqs_money($b['price']); ?></p>
          </div>

          <?php
          $facts = array();
          if ($t['grades']   > 0) $facts[] = $t['grades'] . ' صفوف دراسية';
          if ($t['subjects'] > 0) $facts[] = $t['subjects'] . ' مادة';
          if ($t['units']    > 0) $facts[] = $t['units'] . ' وحدة';
          if ($t['lessons']  > 0) $facts[] = $t['lessons'] . ' درسا';
          if ($t['quizzes']  > 0) $facts[] = $t['quizzes'] . ' اختبارا';
          if ($facts):
          ?>
            <p class="co-opens"><b>ما تفتحه:</b> <?php echo html_escape(implode(' · ', $facts)); ?>
              <a href="<?php echo base_url('plan/' . $b['code']); ?>#curriculum">شاهد المنهج كاملا</a>
            </p>
          <?php endif; ?>

          <dl class="co-total">
            <div><dt>سعر الباقة</dt><dd><?php echo tqs_money($b['price']); ?></dd></div>
            <?php /* لا ضريبة تضاف هنا: `issue_invoice()` تكتب `tax = 0`،
                     ورقم في العرض لا يقابله صف في الفاتورة يوقع في نزاع. */ ?>
            <div class="co-total__f"><dt>الإجمالي</dt><dd><?php echo tqs_money($b['price']); ?></dd></div>
          </dl>
        </div>

        <div class="icard">
          <h2>طريقة الدفع</h2>

          <?php if ($tq_both): ?>
            <?php /* منتقيان حقيقيان لا زران يحدثان حقلا مخفيا: `radio`
                     داخل النموذج يرسل نفسه، ويعمل بلا JS، ويقرأ بقارئ
                     الشاشة كما هو. و`checked` على البطاقة: هي الأسرع
                     والأكثر اختيارا، والافتراض الذي يوافق الأكثر يوفر
                     ضغطة على الأكثر. */ ?>
            <div class="co-pick">
              <label class="co-opt">
                <input type="radio" name="pay_method" value="tap" checked
                       data-tq-pay="tap">
                <span class="co-opt__box">
                  <span class="co-opt__ico" aria-hidden="true">
                    <svg><use href="#i-card"></use></svg>
                  </span>
                  <span class="co-opt__t">
                    <b>بطاقة — مدى أو فيزا أو ماستركارد</b>
                    <span class="tq-caption">دفع فوري في صفحة تاب الآمنة، ويفتح المحتوى في لحظته.</span>
                  </span>
                </span>
              </label>

              <label class="co-opt">
                <input type="radio" name="pay_method" value="bank" data-tq-pay="bank">
                <span class="co-opt__box">
                  <span class="co-opt__ico" aria-hidden="true">
                    <svg><use href="#i-bank"></use></svg>
                  </span>
                  <span class="co-opt__t">
                    <b>تحويل بنكي</b>
                    <span class="tq-caption">تصدر فاتورتك برقم مرجعي، وتفعل باقتك بعد التحقق من الحوالة.</span>
                  </span>
                </span>
              </label>
            </div>

            <?php if ($tq_test): ?>
              <?php /* وضع الاختبار يقال للطالب لا للمسؤول وحده: دفعة
                       تنجح ظاهريا ولا يخصم منها شيء، ومن لا يعلم ذلك
                       يظن أنه اشترى. */ ?>
              <p class="co-pay__test" data-tq-for="tap">
                <svg aria-hidden="true"><use href="#i-shield"></use></svg>
                البوابة في <b>وضع الاختبار</b> الآن: الدفع لا يخصم مالا حقيقيا.
              </p>
            <?php endif; ?>

            <?php /* بلا `hidden` في الوسم: من لا يشتغل عنده JS يرى بيانات
                     التحويل ظاهرة ويستطيع أن يحول — و`hidden` يضعه JS
                     حين يعرف أن الاختيار بطاقة. الإخفاء في الوسم يعني
                     شاشة ناقصة لمن تعثر عنده ملف. */ ?>
            <div class="co-bank" data-tq-for="bank">
              <?php echo tqs_bank_block(); ?>
            </div>

          <?php elseif ($tq_card): ?>
            <?php /* بطاقة وحدها — الحساب البنكي لم ينشر بعد. تعلن الطريقة
                     ولا يعرض منتقي: خيار واحد لا ينتقى. */ ?>
            <p class="co-pay">
              <span class="co-pay__pick">
                <svg aria-hidden="true"><use href="#i-card"></use></svg>
                <b>بطاقة — مدى أو فيزا أو ماستركارد</b>
              </span>
              <span class="tq-caption">
                دفع فوري في صفحة تاب الآمنة، ويفتح المحتوى في لحظته.
              </span>
            </p>
            <?php /* والحقل المخفي يقوم مقام المنتقي: بلا `pay_method` يقرأ
                     المحرك «تحويل بنكي» فتصدر فاتورة لا حساب يحول إليه. */ ?>
            <input type="hidden" name="pay_method" value="tap">
            <?php if ($tq_test): ?>
              <p class="co-pay__test">
                <svg aria-hidden="true"><use href="#i-shield"></use></svg>
                البوابة في <b>وضع الاختبار</b> الآن: الدفع لا يخصم مالا حقيقيا.
              </p>
            <?php endif; ?>

          <?php else: ?>
            <?php /* لا بطاقة مضبوطة: الشاشة كما كانت — طريقة واحدة تعلن
                     ولا منتقي يوهم بخيار. */ ?>
            <p class="co-pay">
              <span class="co-pay__pick">
                <svg aria-hidden="true"><use href="#i-bank"></use></svg>
                <b>تحويل بنكي</b>
              </span>
              <span class="tq-caption">
                تصدر فاتورتك فورا برقم مرجعي، وتفعل باقتك بعد التحقق من الحوالة.
              </span>
            </p>
            <?php echo tqs_bank_block(); ?>
          <?php endif; ?>
        </div>

        <?php /* TQ-SPAM — الفاتورة تصدر وتخرج نسخة منها بالبريد، ومزود
                 البريد قد يضعها في «غير المرغوبة». والمشتري الذي لا تصله
                 فاتورته يظن أن شراءه لم يتم فيعيده. ومطوي لا مفتوح: هذه
                 شاشة شراء لا شاشة انتظار رسالة. */ ?>
        <?php echo tq_spam_notice(array('compact' => true, 'id' => 'tq-spam-co')); ?>

    </div>

    <aside class="co-side">
      <div class="icard icard--sticky">
        <h3 class="co-side__h">الخطوة الأخيرة</h3>

        <?php if ($tq_card): ?>
          <ol class="co-steps" data-tq-for="tap">
            <li><b>١</b> تنتقل إلى صفحة تاب الآمنة</li>
            <li><b>٢</b> تدفع ببطاقتك</li>
            <li><b>٣</b> يفعل اشتراكك ويفتح المحتوى فورا</li>
          </ol>
        <?php endif; ?>

        <?php if ($tq_bank): ?>
          <ol class="co-steps" data-tq-for="bank">
            <li><b>١</b> تؤكد الاشتراك وتصدر فاتورتك</li>
            <li><b>٢</b> تحول المبلغ بمرجع الفاتورة</li>
            <li><b>٣</b> يفعل اشتراكك ويفتح المحتوى</li>
          </ol>
        <?php endif; ?>

        <p class="co-side__total">
          <span>الإجمالي</span>
          <b><?php echo tqs_money($b['price']); ?></b>
        </p>

        <?php /* نص الزر يوافق الطريقة المختارة: «إصدار الفاتورة» على زر
                 يفتح صفحة بطاقة يخالف ما يقع بعد الضغط. */ ?>
        <button type="submit" class="btn btn--primary btn--block" data-tq-submit
                data-tq-label-tap="تابع إلى الدفع الآمن"
                data-tq-label-bank="تأكيد الاشتراك وإصدار الفاتورة">
          <?php echo $tq_card ? 'تابع إلى الدفع الآمن' : 'تأكيد الاشتراك وإصدار الفاتورة'; ?>
        </button>

        <p class="tq-caption co-side__note">
          لا تجديد تلقائي ولا خصم متكرر. وبتأكيدك توافق على
          <a href="<?php echo base_url('terms'); ?>">الشروط</a> و<a href="<?php echo base_url('refund'); ?>">سياسة الاسترداد</a>.
        </p>

        <a class="co-side__back" href="<?php echo base_url('plan/' . $b['code']); ?>">
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          عودة إلى تفاصيل الباقة
        </a>
      </div>
    </aside>

  </form>
</section>

<?php if ($tq_both): ?>
<?php /* تحسين تدريجي بحتا: لا `hidden` في الوسم، فمن تعثر عنده هذا الملف
         يرى الطريقتين وبيانات التحويل والزر كلها عاملة — والنموذج يرسل
         اختياره لأن `radio` يرسل نفسه. وهذا النص يخفي ما لا يخص الطريقة
         المختارة ويصحح نص الزر، لا أكثر. */ ?>
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
<?php endif; ?>
