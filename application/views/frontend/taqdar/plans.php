<?php
/**
 * الباقات.
 *
 * وحدة البيع صارت **الباقة** لا البرنامج: الطالب يأخذ منهج صفه كاملا،
 * لا مادة مادة. وهذه الصفحة صارت صفحة الباقات وحدها بعد أن انتقل تصفح
 * المحتوى إلى الكتالوج الموحد (`/catalog`) — فما كان صفحة تسأل سؤالين
 * («ما المعروض؟» و«بكم؟») صار صفحتين، لكل واحدة سؤالها.
 *
 * والعرض كاروسل لا شبكة: الباقات ست (ثلاث درجات في مرحلتين)، وشبكة
 * ثلاثية تجعل كل مرحلة صفا فيطول العمود بلا حاجة — والكاروسل يعرض
 * المرحلة المختارة في صف واحد يوازن بين درجاتها، وهو الموازنة التي
 * جاء الزائر يجريها. وأساسه `scroll-snap` فيسحب بالإصبع وبلوحة
 * اللمس ويمضي بالسهمين، ويعمل بلا سكربت إن تعثر تحميله.
 *
 * والاشتراك يمر بـ`checkout/<code>` كما كان: محرك الفوترة لم يتبدل.
 */
/* العنوان والتعريف يحرران من «نصوص الصفحات» في اللوحة؛ والمكتوب هنا
   الافتراضي الذي يعرض ما لم يحرر. و`tq_text_raw` لا `tq_text` لأن
   `site_pagehero.php` يهرب ما يطبعه — والتهريب مرتين يظهر `&quot;`. */
$tq_h1   = tq_text_raw('plans', 'hero_title', 'الباقات');
$tq_lead = tq_text_raw('plans', 'hero_lede', 'منهج المرحلة كاملا في باقة واحدة: مواد الصف وبرامجها ودروسها واختباراتها — تختار مرة، ويبقى الباب مفتوحا العام الدراسي كله.');
include __DIR__ . '/site/site_pagehero.php';

$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_site_model', 'tq_m');
$tq_ci->load->model('taqdar_billing_model', 'tq_b');
$tq_uid   = (int) $tq_ci->session->userdata('user_id');

/* الباقات من `plans` بنطاق الصفوف — ومعها معرفاتها للشراء. */
$tq_rows = array();
foreach ((array) $tq_ci->tq_b->plans(true) as $tq_p) {
    if ((string) $tq_p['scope'] === 'grade') $tq_rows[] = $tq_p;
}
?>
<section class="section" id="bundles">
  <div class="shell">
    <?php if ($tq_f = $tq_ci->session->flashdata('flash_message')): ?>
      <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_f); ?></p>
    <?php endif; ?>
    <?php if ($tq_e = $tq_ci->session->flashdata('error_message')): ?>
      <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($tq_e); ?></p>
    <?php endif; ?>

<?php if ($tq_rows): ?>
   <div class="p26d">
<?php /* التبويب والمبدّل قرار واحد «أي مرحلة وبأي دورة» — فيقربان. */ ?>
    <div class="p26d__switch">
      <div class="p26d__tabs"><?php echo tqs_stage_tabs(); ?></div>
<?php /* مبدّل الدورة — **عرض لا فوترة**: كل الباقات سنوية في القاعدة،
        فالشهريّ يعرض المعادل ومعه «تدفع سنويا». */ ?>
      <div class="p26d__cycle">
        <div class="p26d__cycle-in" role="group" aria-label="دورة عرض السعر">
          <button type="button" data-tq-cycle="year" aria-pressed="false">سنوي<span class="p26d__cycle-save">وفر 20%</span></button>
          <button type="button" data-tq-cycle="month" aria-pressed="true">شهري</button>
        </div>
      </div>
    </div>
<?php /* البطاقات من مولّد واحد يخدم الرئيسية وهذه الصفحة — نسخةٌ ثانية
        من الوسم تشيخ وحدها. والزر هنا يقود إلى شاشة التأكيد لا إلى
        صفحة الباقة، وتحته رابط التفاصيل. */ ?>
<?php echo tqs_bundles_dark(null, array('cta' => 'checkout', 'more' => true)); ?>

    <p class="bundles__foot tq-caption">
      الأسعار بالريال السعودي وتشمل ما هو مذكور في الباقة. ويمكنك الترقية في أي وقت،
      فيحتسب لك ما دفعته. واطلع على <a href="<?php echo base_url('refund'); ?>">سياسة الاسترجاع</a>.
    </p>
   </div>
<?php else: ?>
    <p class="dir-empty">لا توجد باقات متاحة الآن.</p>
<?php endif; ?>
  </div>
</section>

<?php /* المسابقات هنا لأن الزائر يقرر الشراء في هذه الصفحة، والمنافسة
        حجة تقنع — والدالة تخفي القسم كله إن لم تكن هناك مسابقة. */ ?>
<?php echo tqs_competitions_strip(); ?>
