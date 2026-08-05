<?php
/* البيانات من نموذج واحد — لا استعلام في العرض.
   و`get_instance()` لا `$this->load`: تحميل نموذج داخل عرض CI3
   يُنتج بترًا صامتًا للصفحة، لا خطأً يُنبّه. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_site_model', 'tq_m');
$tq_m  = $tq_ci->tq_m;
$tq_cat = trim((string) $tq_ci->input->get('cat'));
$tq_cats  = $tq_m->categories();
$tq_books = $tq_m->books($tq_cat);
?>
<!--
title: كتب المنهج الدراسي — منصّة تقدّر
desc: تصفَّح وحمِّل كتب المنهج الدراسي السعودي مجانًا، مرتّبة حسب المرحلة والمادة.
active: books
header: solid
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="page-hero">
  <div class="shell">
    <div class="page-hero__grid">
      <span class="lantern lantern--l" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="page-hero__copy reveal">
        <h1>كتب المنهج الدراسي
          <span class="page-hero__sub">تصفَّح وحمِّل مجانًا</span>
        </h1>
        <p class="page-hero__lede">
          كتب المنهج السعودي المعتمد، مرتّبة حسب المرحلة والمادة،
          جاهزة للتصفّح والتحميل في أي وقت.
        </p>
        <div class="hero-mini">
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-badge"></use></svg></span>
            <div><b>مناهج معتمدة</b><span>وفق وزارة التعليم</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-download"></use></svg></span>
            <div><b>تحميل مجاني</b><span>بلا تسجيل</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-curriculum"></use></svg></span>
            <div><b>كل المراحل</b><span>ابتدائي ومتوسط وثانوي</span></div>
          </div>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/blog-hero.webp'); ?>" width="960" height="1440"
                 alt="طفل سعودي يذاكر من كتابه على مكتب دراسي">
          </div>
<?php include __DIR__ . '/site/site_arch.php'; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ الكتب ══════════ -->
<section class="section" id="library">
  <div class="shell">
    <div class="panel">
      <span class="lantern lantern--corner" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="section-head">
        <h2><span>اختر المرحلة</span></h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>

<?php echo tqs_categories($tq_cats); ?>

      <div class="section-head" style="margin-block:clamp(30px,3.6vw,52px) 22px">
        <h2><span id="catalogTitle">جميع الكتب</span></h2>
      </div>

<?php echo tqs_books($tq_books); ?>
    </div>
  </div>
</section>

<!-- ══════════ نداء الختام ══════════ -->
<section class="section">
  <div class="shell">
    <div class="cta on-dark">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2>الكتاب بداية.. والبرنامج هو الرحلة</h2>
        <p>حمِّل الكتاب وابدأ البرنامج التعليمي المرافق له</p>
        <a class="btn btn--gold" href="<?php echo base_url('plans'); ?>">استكشف المواد والبرامج</a>
      </div>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/cta-kids-cut.webp'); ?>" width="660" height="990" loading="lazy"
             decoding="async" alt="طفلان سعوديان يبتسمان ويحملان جهازًا لوحيًّا">
      </div>
    </div>
  </div>
</section>
