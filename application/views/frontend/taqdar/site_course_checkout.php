<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * TQ-COURSE-SALE — تأكيد شراء كورس مفرد.
 *
 * أخت `site_checkout.php` بمفرداتها نفسها (`co-*`)، ولا ورقة ثانية:
 * شاشتا دفع تختلفان في الشكل تجعلان المشتري يشك في إحداهما، وهو آخر ما
 * يحسن أن يشك فيه.
 *
 * وما يعرض هنا **هو ما يكتب في القاعدة**: السعر من `offer()` لا من حقل
 * مخفي في النموذج، و`subscribe_course()` تنادي `offer()` نفسها. أرسل
 * المتصفح ما أرسل، المحرك يقرأ السعر من عنده — فلا يشترى بستة ريالات
 * ما ثمنه ستمئة.
 *
 * **والفرق الوحيد الذي يستحق أن يقال**: الباقة تفتح منهج مرحلة، وهذه
 * تفتح **مادة واحدة**. فيقال ذلك صراحة في الملخص وتحت الزر معا — لا
 * ليقلل من الشراء، بل لأن من يظن أنه اشترى المنهج كله يكتشف ذلك بعد
 * الدفع لا قبله، وهو استرداد وشكوى ومنصة فقدت ثقة.
 */
$o = isset($tq_offer) ? $tq_offer : null;
if (!$o || empty($o['sellable'])) return;

$course  = isset($tq_course) ? $tq_course : array();
$cid     = (int) $o['course_id'];
$pending = isset($tq_pending) ? $tq_pending : null;

/* البطاقة تعرض إن كانت مضبوطة وحدها — والقرار جاء من المتحكم
   (`Taqdar_tap_model::ready()`) لا من القالب. وحين لا تكون مضبوطة تعرض
   هذه الشاشة التحويل البنكي وحده، بلا منتقي يفضي إلى خطأ. */
$tq_card = !empty($tq_card);
$tq_test = !empty($tq_card_test);
$tq_bank = (tqs_bank() !== null) || !$tq_card;
$tq_both = $tq_card && $tq_bank;

$CI = get_instance();

/* أرقام المحتوى — من الجدول لا من وعد: كورس بلا دروس لا يعرض هنا أصلا
   (`offer()` ترده `empty`)، وما يعرض يقول حجمه بالضبط. */
$n_lessons = 0; $n_quizzes = 0; $total_secs = 0;
foreach ($CI->db->select('lesson_type, duration')->where('course_id', $cid)
                ->get('lesson')->result_array() as $l) {
    if ((string) $l['lesson_type'] === 'quiz') { $n_quizzes++; }
    else                                       { $n_lessons++; }
    $p = explode(':', trim((string) $l['duration']));
    if (count($p) === 3)      $total_secs += ((int) $p[0]) * 3600 + ((int) $p[1]) * 60 + (int) $p[2];
    elseif (count($p) === 2)  $total_secs += ((int) $p[0]) * 60 + (int) $p[1];
}

$thumb = (string) $CI->crud_model->get_course_thumbnail_url($cid);
if (rtrim($thumb, '/') === rtrim(base_url(), '/')) $thumb = '';

$course_url = base_url('course/' . rawurlencode(slugify($o['title'])) . '/' . $cid);

/* أجل الوصول بعبارة يفهمها المشتري لا بعدد أيام. و«دائم» تقال صراحة:
   هي أقوى ما في هذا العرض، وتركها ضمنا يضيعها. */
$access = ((int) $o['days'] > 0)
        ? ('وصول ' . tq_count_units((int) round($o['days'] / 30), 'شهر', 'شهران', 'شهرين',
                                    'أشهر', 'شهرا', '', 'obl'))
        : 'وصول دائم — بلا انتهاء';
?>

<section class="page-hero page-hero--checkout">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url('catalog'); ?>">المواد والبرامج</a> ›
      <a href="<?php echo html_escape($course_url); ?>"><?php echo html_escape($o['title']); ?></a> ›
      <span aria-current="page">تأكيد الشراء</span>
    </nav>
    <h1>تأكيد الشراء</h1>
    <p class="page-hero__lead">راجع ما ستشتريه، ثم أكد — ولن يخصم شيء تلقائيا.</p>
  </div>
</section>

<section class="section section--tight">
  <?php
  /* النموذج يغلف العمودين لا العمود الأيسر وحده: طريقة الدفع تختار في
     بطاقة الملخص والزر في البطاقة اللاصقة، ولو بقي النموذج في إحداهما
     لصار المنتقي خارجه — أي أن الاختيار لا يرسل. */
  ?>
  <form class="shell co-cols" method="post" action="<?php echo base_url('student/buy-course'); ?>"
        id="tqCheckout">
    <?php echo tq_csrf(); ?>
    <?php /* `course_id` وحده يرسل، والسعر يقرأ في المحرك من `offer()`. */ ?>
    <input type="hidden" name="course_id" value="<?php echo $cid; ?>">

    <div class="co-main">

      <?php if ($m = $CI->session->flashdata('error_message')): ?>
        <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($m); ?></p>
      <?php endif; ?>

      <?php if ($pending): ?>
        <?php /* فاتورة قائمة لم تسدد: يقال ذلك بدل أن يعرض زر شراء ثان
                 لمن اشترى قبل دقيقة — فيخرج بصفين وفاتورتين وتبقى
                 إحداهما «غير مدفوعة» أبدا. و`subscribe_course()` تعيد
                 استعمال الصف نفسه، فالزر أدناه لا يكرر شيئا؛ وهذا
                 السطر ليقرأه من ظن أن تأكيده الأول لم يصل. */ ?>
        <p class="tq-flash tq-flash--ok" role="status">
          فاتورتك <b class="tq-ltr"><?php echo html_escape($pending['invoice']['invoice_no']); ?></b>
          صدرت ولم تسدد بعد. تأكيدك هنا يكملها ولا يصدر أخرى.
        </p>
      <?php endif; ?>

      <div class="icard co-summary">
        <h2>ملخص الطلب</h2>

        <div class="co-item">
          <?php if ($thumb !== ''): ?>
            <img class="co-item__img" src="<?php echo html_escape($thumb); ?>"
                 alt="" width="220" height="147" loading="lazy" decoding="async">
          <?php endif; ?>
          <div class="co-item__b">
            <h3><?php echo html_escape($o['title']); ?></h3>
            <?php if (!empty($course['short_description'])): ?>
              <p class="tq-caption"><?php echo html_escape($course['short_description']); ?></p>
            <?php endif; ?>
            <p class="co-item__dur">
              <svg aria-hidden="true"><use href="#i-lock"></use></svg>
              <?php echo html_escape($access); ?>
            </p>
          </div>
          <p class="co-item__price">
            <?php echo tqs_money((int) $o['price']); ?>
            <?php if ((int) $o['list_price'] > 0): ?>
              <small>بدل <?php echo number_format((int) $o['list_price'] / 100); ?> ر.س
                — خصم <?php echo (int) $o['off']; ?>٪</small>
            <?php endif; ?>
          </p>
        </div>

        <?php
        $facts = array();
        if ($n_lessons  > 0) $facts[] = tq_lessons_word($n_lessons, '', 'nom');
        if ($n_quizzes  > 0) $facts[] = tq_count_units($n_quizzes, 'اختبار', 'اختباران', 'اختبارين',
                                                       'اختبارات', 'اختبارا', '', 'nom');
        if ($total_secs > 0) $facts[] = tqs_dur(gmdate('H:i:s', $total_secs)) . ' من المشاهدة';
        if ($facts):
        ?>
          <p class="co-opens"><b>ما تفتحه:</b> <?php echo html_escape(implode(' · ', $facts)); ?>
            <a href="<?php echo html_escape($course_url); ?>#curriculum">شاهد المنهج كاملا</a>
          </p>
        <?php endif; ?>

        <dl class="co-total">
          <div><dt>سعر الكورس</dt><dd><?php echo tqs_money((int) $o['price']); ?></dd></div>
          <?php /* لا ضريبة تضاف هنا: `issue_invoice()` تكتب `tax = 0` ما
                   لم تضبط، ورقم في العرض لا يقابله صف في الفاتورة يوقع
                   في نزاع. */ ?>
          <div class="co-total__f"><dt>الإجمالي</dt><dd><?php echo tqs_money((int) $o['price']); ?></dd></div>
        </dl>

        <?php /* **الحد الذي يقال قبل الدفع لا بعده.** من يشتري مادة
                 واحدة ويظن أنه اشترى المنهج يكتشف ذلك في أول درس مقفل،
                 فيطلب استردادا ويقول إن المنصة أوهمته. وسطر واحد هنا
                 يمنع ذلك كله — وهو الوجه المقابل لسطر «الباقة تفتح
                 المرحلة كاملة» في شاشة الباقة. */ ?>
        <p class="co-cycle">
          هذا شراء <b>هذا الكورس وحده</b> — دروسه واختباراته وشهادته، لا
          بقية مواد صفك.
          <?php echo html_escape($access); ?>، بلا تجديد ولا خصم متكرر.
          <a href="<?php echo base_url('plans'); ?>">وإن أردت المنهج كاملا، شاهد الباقات</a>.
        </p>
      </div>

      <div class="icard">
        <h2>طريقة الدفع</h2>

        <?php if ($tq_both): ?>
          <div class="co-pick">
            <label class="co-opt">
              <input type="radio" name="pay_method" value="tap" checked data-tq-pay="tap">
              <span class="co-opt__box">
                <span class="co-opt__ico" aria-hidden="true"><svg><use href="#i-card"></use></svg></span>
                <span class="co-opt__t">
                  <b>بطاقة — مدى أو فيزا أو ماستركارد</b>
                  <span class="tq-caption">دفع فوري في صفحة تاب الآمنة، ويفتح الكورس في لحظته.</span>
                </span>
              </span>
            </label>

            <label class="co-opt">
              <input type="radio" name="pay_method" value="bank" data-tq-pay="bank">
              <span class="co-opt__box">
                <span class="co-opt__ico" aria-hidden="true"><svg><use href="#i-bank"></use></svg></span>
                <span class="co-opt__t">
                  <b>تحويل بنكي</b>
                  <span class="tq-caption">تصدر فاتورتك برقم مرجعي، ويفتح الكورس بعد التحقق من الحوالة.</span>
                </span>
              </span>
            </label>
          </div>

          <?php if ($tq_test): ?>
            <p class="co-pay__test" data-tq-for="tap">
              <svg aria-hidden="true"><use href="#i-shield"></use></svg>
              البوابة في <b>وضع الاختبار</b> الآن: الدفع لا يخصم مالا حقيقيا.
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
              <b>بطاقة — مدى أو فيزا أو ماستركارد</b>
            </span>
            <span class="tq-caption">دفع فوري في صفحة تاب الآمنة، ويفتح الكورس في لحظته.</span>
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
          <p class="co-pay">
            <span class="co-pay__pick">
              <svg aria-hidden="true"><use href="#i-bank"></use></svg>
              <b>تحويل بنكي</b>
            </span>
            <span class="tq-caption">
              تصدر فاتورتك فورا برقم مرجعي، ويفتح الكورس بعد التحقق من الحوالة.
            </span>
          </p>
          <?php echo tqs_bank_block(); ?>
        <?php endif; ?>
      </div>

      <?php echo tq_spam_notice(array('compact' => true, 'id' => 'tq-spam-cco')); ?>

    </div>

    <aside class="co-side">
      <div class="icard icard--sticky">
        <h3 class="co-side__h">الخطوة الأخيرة</h3>

        <?php if ($tq_card): ?>
          <ol class="co-steps" data-tq-for="tap">
            <li><b>١</b> تنتقل إلى صفحة تاب الآمنة</li>
            <li><b>٢</b> تدفع ببطاقتك</li>
            <li><b>٣</b> يفتح الكورس فورا</li>
          </ol>
        <?php endif; ?>

        <?php if ($tq_bank): ?>
          <ol class="co-steps" data-tq-for="bank">
            <li><b>١</b> تؤكد الشراء وتصدر فاتورتك</li>
            <li><b>٢</b> تحول المبلغ بمرجع الفاتورة</li>
            <li><b>٣</b> يفتح الكورس بعد التحقق</li>
          </ol>
        <?php endif; ?>

        <p class="co-side__total">
          <span>الإجمالي</span>
          <b><?php echo tqs_money((int) $o['price']); ?></b>
        </p>
        <?php /* البطاقة اللاصقة آخر ما تقرأه العين قبل الزر، ومن نزل
                 إليها مباشرة لا يمر على الملخص — فحد ما اشتراه يقال
                 هنا كذلك لا في الملخص وحده. */ ?>
        <p class="co-side__cycle">
          هذا الكورس وحده — <?php echo html_escape($access); ?>.
        </p>

        <button type="submit" class="btn btn--primary btn--block" data-tq-submit
                data-tq-label-tap="تابع إلى الدفع الآمن"
                data-tq-label-bank="تأكيد الشراء وإصدار الفاتورة">
          <?php echo $tq_card ? 'تابع إلى الدفع الآمن' : 'تأكيد الشراء وإصدار الفاتورة'; ?>
        </button>

        <p class="tq-caption co-side__note">
          لا تجديد تلقائي ولا خصم متكرر. وبتأكيدك توافق على
          <a href="<?php echo base_url('terms'); ?>">الشروط</a>
          و<a href="<?php echo base_url('refund'); ?>">سياسة الاسترداد</a>.
        </p>

        <a class="co-side__back" href="<?php echo html_escape($course_url); ?>">
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          عودة إلى صفحة الكورس
        </a>
      </div>
    </aside>

  </form>
</section>

<?php if ($tq_both): ?>
<?php /* تحسين تدريجي بحتا — النسخة نفسها في `site_checkout.php`: لا
         `hidden` في الوسم، فمن تعثر عنده هذا الملف يرى الطريقتين
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
