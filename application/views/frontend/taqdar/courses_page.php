<?php
/* البيانات من نموذج واحد — لا استعلام في العرض.
   و`get_instance()` لا `$this->load`: تحميل نموذج داخل عرض CI3
   ينتج بترا صامتا للصفحة، لا خطأ ينبه. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_site_model', 'tq_m');
$tq_m  = $tq_ci->tq_m;
$tq_cat = trim((string) $tq_ci->input->get('cat'));
$tq_cats  = $tq_m->categories();
$tq_mats  = $tq_m->materials($tq_cat);
?>
<!--
title: المواد والبرامج — منصة تقدر
desc: برامج تعليمية متدرجة مصممة بعناية وفق أحدث المناهج السعودية، تساعد طفلك على بناء أساس قوي لمستقبل مشرق.
active: paths
header: solid
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="page-hero">
  <div class="shell">
    <div class="page-hero__grid">
      <span class="lantern lantern--l" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="page-hero__copy reveal">
        <h1>المواد والبرامج
          <span class="page-hero__sub">رحلة تعلم متكاملة تناسب كل طفل</span>
        </h1>
        <p class="page-hero__lede">
          برامج تعليمية متدرجة ومصممة بعناية وفق أحدث المناهج السعودية،
          لتساعد طفلك على بناء أساس قوي لمستقبل مشرق.
        </p>
        <div class="hero-mini">
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-badge"></use></svg></span>
            <div><b>مناهج معتمدة</b><span>وفق أحدث المعايير</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-users"></use></svg></span>
            <div><b>مصممون تربويون</b><span>خبرة في التعليم</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-spark"></use></svg></span>
            <div><b>تعلم تفاعلي</b><span>تجربة ممتعة وفعالة</span></div>
          </div>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/paths-hero.webp'); ?>" width="960" height="1440"
                 alt="طفلان سعوديان يحملان جهازا لوحيا وكتبا دراسية">
          </div>
<?php include __DIR__ . '/site/site_arch.php'; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ اختيار المرحلة والصف والمواد ══════════ -->
<section class="section" id="stages">
  <div class="shell">
    <div class="panel">
      <span class="lantern lantern--corner" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="section-head">
        <h2><span>اختر المرحلة التعليمية</span></h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>

<?php echo tqs_categories($tq_cats); ?>

      <div class="section-head" style="margin-block:clamp(28px,3.4vw,48px) 22px">
        <h2><span id="catalogTitle">جميع المواد والبرامج</span></h2>
      </div>

<?php echo tqs_materials($tq_mats); ?>

      <div style="text-align:center;margin-block-start:clamp(22px,2.6vw,36px)">
        <a class="btn btn--ghost" href="<?php echo base_url('sign_up'); ?>">
          عرض جميع المواد
          <svg class="dir-icon" aria-hidden="true" style="width:16px;height:16px">
            <use href="#i-arrow"></use></svg>
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ لماذا برامج تقدر؟ ══════════ -->
<section class="section section--plain">
  <div class="shell">
    <div class="section-head">
      <h2><span>لماذا برامج تقدر التعليمية؟</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>
    <div class="grid-6">
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-report"></use></svg></span>
        <h3>تقارير دورية</h3><p>لمتابعة تقدم الطالب أولا بأول.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-bulb"></use></svg></span>
        <h3>ذكاء اصطناعي</h3><p>لتخصيص رحلة التعلم حسب مستوى كل طالب.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-clipboard"></use></svg></span>
        <h3>اختبارات تفاعلية</h3><p>تقيس الفهم لا الحفظ، وتعطي نتيجة فورية.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-target"></use></svg></span>
        <h3>أنشطة ومسابقات</h3><p>لتحفيز التعلم وجعله عادة محببة.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-video"></use></svg></span>
        <h3>فيديوهات مبسطة</h3><p>تشرح المفاهيم بسهولة وبلغة الطفل.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-eye"></use></svg></span>
        <h3>متابعة ولي الأمر</h3><p>لحظية ومستمرة من أي مكان.</p>
      </article>
    </div>
  </div>
</section>

<!-- ══════════ نداء الختام ══════════ -->
<section class="section" id="signup">
  <div class="shell">
    <div class="cta">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2>ابدأ رحلة التعلم الآن</h2>
        <p>سجل الآن وامنح طفلك مستقبلا مشرقا</p>
        <a class="btn btn--gold" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب مجاني</a>
        <a class="cta__login" href="<?php echo base_url('login'); ?>">أو تسجيل الدخول</a>
      </div>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/cta-kids-cut.webp'); ?>" width="660" height="990" loading="lazy"
             decoding="async" alt="طفلان سعوديان يبتسمان ويحملان جهازا لوحيا">
      </div>
    </div>
  </div>
</section>
