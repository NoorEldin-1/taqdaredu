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

/* TQ-CYCLE-BUY — **الدورة المختارة هي مصدر كل رقم في هذه الشاشة.**
   كانت تطبع `plans.price` عارية مهما اختار المشتري، فمن ضغط «شهري»
   ورأى 42 وجد 399 وظن أن اختياره ضاع. وصارت الدورة تشترى فعلا، فالرقم
   المعروض هنا هو المخصوم حرفا بحرف: `$tq_cycle['price']` هي نفسها التي
   ستكتبها `subscribe()` في `subscriptions.price` ثم `invoices.total`.

   **والاختيار في الرابط لا في المتصفح** — `?cycle=` — كما في الكتالوج:
   لا حالة ثانية تفترق عما يفهمه الخادم، ولا رقم يبقى قديما لأن سكربتا
   تعثر. والخادم هو من يقرر (`cycle_of()`)، فمفتاح مخترع في الرابط يرد
   إلى دورة الباقة لا إلى الأرخص.

   والارتداد للأمان: من فتح الشاشة برابط قديم بلا `$tq_cycle` (كاش
   صفحة، أو استدعاء من موضع لم يمرره) يقرأ دورة الباقة لا صفرا. */
$tq_p     = tqs_plan_price($b);
$tq_cycs  = isset($tq_cycles) && is_array($tq_cycles) ? $tq_cycles : array();
$tq_cyc   = isset($tq_cycle) && is_array($tq_cycle) ? $tq_cycle : null;
if ($tq_cyc === null) {
    $tq_cyc = array('key'   => (string) $b['period'], 'price' => (int) $b['price'],
                    'days'  => (int) $b['days'],      'unit'  => $tq_p['unit'],
                    'label' => '');
}
$tq_amt  = (int) $tq_cyc['price'];
$tq_days = (int) $tq_cyc['days'];
$tq_mon  = ((string) $tq_cyc['key'] === 'monthly' && !empty($tq_p['has_alt']));

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
    <?php /* `plan_id` والدورة يرسلان، والسعر يقرأ في المحرك منهما —
             لا من حقل مخفي بمبلغ. أرسل المتصفح ما أرسل، `cycle_of()`
             تحرس المفتاح و`plan_cycles()` تحدد ثمنه. */ ?>
    <input type="hidden" name="plan_id" value="<?php echo (int) $b['plan_id']; ?>">
    <input type="hidden" name="cycle" value="<?php echo html_escape((string) $tq_cyc['key']); ?>">

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
                <?php echo html_escape(tqs_period_label($tq_days)); ?>
              </p>
            </div>
            <p class="co-item__price">
              <?php echo tqs_money($tq_amt); ?>
              <small><?php echo $tq_mon ? 'لكل شهر' : html_escape('/ ' . $tq_cyc['unit']); ?></small>
            </p>
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
            <div><dt>سعر الباقة<?php echo $tq_cyc['label'] !== '' ? ' — ' . html_escape($tq_cyc['label']) : ''; ?></dt>
                 <dd><?php echo tqs_money($tq_amt); ?></dd></div>
            <?php /* لا ضريبة تضاف هنا: `issue_invoice()` تكتب `tax = 0`،
                     ورقم في العرض لا يقابله صف في الفاتورة يوقع في نزاع. */ ?>
            <div class="co-total__f"><dt>الإجمالي</dt><dd><?php echo tqs_money($tq_amt); ?></dd></div>
          </dl>

          <?php /* ما يشتريه هذا المبلغ بالضبط: كم يوما، وماذا بعدها.
                   ولا تجديد تلقائي في هذا المحرك، فمن لا يجدد ينقطع
                   وصوله — وقوله قبل الدفع خير من اكتشافه بعد شهر. */ ?>
          <p class="co-cycle">
            يخصم <b class="tq-ltr"><?php echo number_format($tq_amt / 100); ?></b> ر.س
            <b>مرة واحدة</b>، ويفتح المحتوى
            <b class="tq-ltr"><?php echo $tq_days; ?></b> يوما
            (<?php echo html_escape(tqs_period_label($tq_days)); ?>).
            ولا تجديد تلقائي ولا خصم متكرر — تجدده بالشراء متى شئت.
          </p>
        </div>

        <?php if (count($tq_cycs) > 1): ?>
        <?php /* TQ-CYCLE-BUY — **مدة الاشتراك خيار هنا، لا في صفحة سابقة.**
                 المبدل في `/plans` يعرض ويمرر الدورة في الرابط، وهذا هو
                 الموضع الذي يقرر فيه المشتري وهو ينظر إلى المبلغ الذي
                 سيخصم. وكان المبدل هناك وحده، فمن وصل إلى هنا من رابط
                 مباشر أو من نتيجة بحث لم ير خيارا أصلا.

                 **وروابط لا أزرار راديو**: الاختيار يعيد بناء الصفحة من
                 الخادم، فالمبلغ المعروض في الملخص وفي البطاقة اللاصقة
                 وفي حقل `cycle` المرسل ثلاثتها من مصدر واحد ولا تفترق
                 لأن سكربتا تعثر. وهي قاعدة الكتالوج نفسها: الحال في
                 الرابط وحده. */ ?>
        <div class="icard co-cycles">
          <h2>مدة الاشتراك</h2>
          <div class="co-cyc" role="group" aria-label="مدة الاشتراك">
            <?php foreach ($tq_cycs as $tq_k => $tq_c):
              $tq_on  = ((string) $tq_k === (string) $tq_cyc['key']);
              $tq_sar = (int) round($tq_c['price'] / 100);
              /* التوفير يقارن بالشهري مضروبا في عدد أشهر الدورة — وهي
                 مقارنة حقيقية الآن لأن الشهر يباع فعلا. */
              $tq_sv = 0;
              if (!empty($tq_p['has_alt']) && $tq_k !== 'monthly') {
                  $tq_sv = max(0, (int) $tq_p['month'] * (int) $tq_p['months'] - $tq_sar);
              }
            ?>
              <a class="co-cyc__opt<?php echo $tq_on ? ' is-on' : ''; ?>"
                 href="<?php echo base_url('checkout/' . $b['code']) . '?cycle=' . rawurlencode((string) $tq_k); ?>"
                 <?php echo $tq_on ? 'aria-current="true"' : ''; ?>>
                <span class="co-cyc__name"><?php echo html_escape((string) $tq_c['label']); ?></span>
                <span class="co-cyc__price"><b class="tq-ltr"><?php echo number_format($tq_sar); ?></b> ر.س</span>
                <span class="co-cyc__note">
                  يفتح <?php echo (int) $tq_c['days']; ?> يوما<?php
                    echo $tq_sv > 0 ? ' — توفر ' . number_format($tq_sv) . ' ر.س' : ''; ?>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
          <p class="tq-caption co-cyc__foot">
            كل مدة تدفع مرة واحدة وتفتح المحتوى طوالها. لا تجديد تلقائي،
            فلا يخصم منك شيء بعدها حتى تجدد بنفسك.
          </p>
        </div>
        <?php endif; ?>

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
          <b><?php echo tqs_money($tq_amt); ?></b>
        </p>
        <?php /* البطاقة اللاصقة آخر ما تقرأه العين قبل الزر: من نزل إليه
                 مباشرة لا يمر على الملخص، فالمدة تقال هنا أيضا. */ ?>
        <p class="co-side__cycle">
          <?php echo html_escape(tqs_period_label($tq_days)); ?> —
          <b class="tq-ltr"><?php echo $tq_days; ?></b> يوما، بلا تجديد تلقائي.
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
