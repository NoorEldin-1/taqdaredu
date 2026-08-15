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
    <?php echo tqs_stage_tabs(); ?>
<?php
/* أول مرحلة ظاهرة والباقي مخفي — كما في الرئيسية، فلو فشل السكربت
   رأى الزائر مرحلة واحدة صحيحة لا ست بطاقات مختلطة. */
$tq_stg   = tqs_bundle_stages();
$tq_hide  = (count($tq_stg) > 1);
$tq_ks    = array_keys($tq_stg);
$tq_first = $tq_ks ? $tq_ks[0] : '';
?>
<?php
/* البطاقات تجمع في مخزن ثم تلف بالكاروسل: `tqs_carousel` تأخذ وسما
   جاهزا لا تطبع بنفسها — فهي غلاف واحد يعاد استعماله في الرئيسية
   وفي الجامعات وهنا، ولا تعرف ما تلفه. */
ob_start();
foreach ($tq_rows as $tq_b):
        $tq_feat = json_decode((string) $tq_b['features'], true);
        $tq_feat = is_array($tq_feat) ? $tq_feat : array();
        $tq_hot  = ((int) $tq_b['featured'] === 1);
?>
      <article class="bundle<?php echo $tq_hot ? ' bundle--featured' : ''; ?> reveal"
               data-stage="<?php echo html_escape($tq_b['stage']); ?>"
               <?php echo ($tq_hide && $tq_b['stage'] !== $tq_first) ? 'hidden' : ''; ?>
               id="<?php echo html_escape($tq_b['code']); ?>">
<?php if ($tq_hot): ?>        <span class="bundle__flag">الأكثر طلبا</span>
<?php endif; ?>
<?php if ((string) $tq_b['image'] !== ''): ?>
        <div class="bundle__media">
          <?php /* `eager` لا `lazy`: البطاقات داخل مضمار أفقي، وحساب
                   المتصفح لما «سيرى» فيه غير موثوق في الاتجاه من اليمين
                   — قيس فحملت صورة واحدة من ثلاث كلها في الشاشة. وهي
                   ست صور على أكثر تقدير، وهي محتوى الصفحة لا زينتها. */ ?>
          <img src="<?php echo tqs_asset_img($tq_b['image'], 'path-primary'); ?>"
               alt="" width="1100" height="733" loading="eager" decoding="async">
          <span class="bundle__over">
            <b><?php echo html_escape(tqs_bundle_tier($tq_b['name_ar'])); ?></b>
            <i><?php echo html_escape(tqs_stage_label($tq_b['stage'])); ?></i>
          </span>
        </div>
<?php endif; ?>
        <h2 class="bundle__name"><?php echo html_escape($tq_b['name_ar']); ?></h2>
<?php if ((string) $tq_b['note'] !== ''): ?>
        <p class="bundle__note"><?php echo html_escape($tq_b['note']); ?></p>
<?php endif; ?>
        <p class="bundle__price">
          <b class="tq-ltr"><?php echo number_format($tq_b['price'] / 100); ?></b> <span>ر.س</span>
          <small><?php echo ((int) $tq_b['duration_days'] >= 360)
              ? 'للعام الدراسي كاملا' : 'لكل ' . (int) $tq_b['duration_days'] . ' يوما'; ?></small>
        </p>
<?php if ($tq_feat): ?>
        <ul class="bundle__list">
<?php foreach ($tq_feat as $tq_x): ?>
          <li><svg aria-hidden="true"><use href="#i-check"></use></svg><?php echo html_escape($tq_x); ?></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
        <?php /* الزر كان POST مباشرا: ضغطة واحدة تصدر فاتورة بأربعمئة
             ريال بلا شاشة تأكيد ولا عرض لما اشتري. صار يقود إلى
             شاشة التأكيد — والعقد نفسه (plan_id) يرسل من هناك. */ ?>
        <a class="btn <?php echo $tq_hot ? 'btn--primary' : 'btn--ghost'; ?>"
           href="<?php echo base_url('checkout/' . $tq_b['code']); ?>">اشترك الآن</a>
        <a class="bundle__more" href="<?php echo base_url('plan/' . $tq_b['code']); ?>">
          ما في هذه الباقة؟
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
        </a>
      </article>
<?php
endforeach;
$tq_cards = ob_get_clean();
/* `data-tq-bundles` على الغلاف لا على المضمار: تبويب المرحلة يبحث عن
   `[data-tq-bundles] [data-stage]` — أي سلف يكفي، والمضمار يطبع من
   `tqs_carousel` التي لا تعرف الباقات ولا ينبغي أن تعرفها. */
?>
    <div data-tq-bundles>
<?php echo tqs_carousel($tq_cards, 'الباقات المتاحة', 'carousel2--bundles'); ?>
    </div>

    <p class="bundles__foot tq-caption">
      الأسعار بالريال السعودي وتشمل ما هو مذكور في الباقة. ويمكنك الترقية في أي وقت،
      فيحتسب لك ما دفعته. واطلع على <a href="<?php echo base_url('refund'); ?>">سياسة الاسترجاع</a>.
    </p>
<?php else: ?>
    <p class="dir-empty">لا توجد باقات متاحة الآن.</p>
<?php endif; ?>
  </div>
</section>

<?php /* المسابقات هنا لأن الزائر يقرر الشراء في هذه الصفحة، والمنافسة
        حجة تقنع — والدالة تخفي القسم كله إن لم تكن هناك مسابقة. */ ?>
<?php echo tqs_competitions_strip(); ?>
