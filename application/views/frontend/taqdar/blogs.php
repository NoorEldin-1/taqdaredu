<?php
/* البيانات من نموذج واحد — لا استعلام في العرض.
   و`get_instance()` لا `$this->load`: تحميل نموذج داخل عرض CI3
   ينتج بترا صامتا للصفحة، لا خطأ ينبه. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_site_model', 'tq_m');
$tq_m  = $tq_ci->tq_m;
$tq_posts = $tq_m->posts();
$tq_pcats = $tq_m->post_categories();
?>
<!--
title: المدونة — منصة تقدر
desc: مقالات ونصائح وأفكار تربوية وتعليمية ملهمة لمساعدتك على دعم رحلة التعلم لطفلك.
active: blog
header: solid
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="page-hero">
  <div class="shell">
    <div class="page-hero__grid">
      <span class="lantern lantern--l" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="page-hero__copy reveal">
        <h1>المدونة
          <span class="page-hero__sub">معرفة تلهم .. وتجربة تثري</span>
        </h1>
        <p class="page-hero__lede">
          مقالات ونصائح وأفكار تربوية وتعليمية ملهمة لمساعدتك على دعم
          رحلة التعلم لطفلك.
        </p>
        <div class="blog-filter">
          <label class="field field--grow">
            <svg aria-hidden="true"><use href="#i-search"></use></svg>
            <span class="sr-only">ابحث في المقالات</span>
            <input type="search" id="postSearch" placeholder="ابحث في المقالات…">
          </label>
          <nav class="cat-tabs" id="catNav" aria-label="تصنيفات المدونة">
<?php echo tqs_post_tabs($tq_pcats); ?>
          </nav>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/blog-hero.webp'); ?>" width="960" height="1440"
                 alt="طفل سعودي يكتب في دفتره على مكتب دراسي">
          </div>
<?php include __DIR__ . '/site/site_arch.php'; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ المقالات + الشريط الجانبي ══════════ -->
<section class="section section--plain" id="posts">
  <div class="shell">
    <div class="blog-layout">

      <div>
        <div class="section-head" style="text-align:start;margin-block-end:18px">
          <h2 style="font-size:clamp(17px,1.6vw,26px)"><span>مقالات مميزة</span></h2>
        </div>

        <?php echo tqs_feat_post($tq_posts); ?>

        <div class="cards-3" id="postGrid">
<?php echo tqs_post_cards($tq_posts); ?>
        </div>

        <div class="section-head" style="text-align:start;
             margin-block:clamp(26px,3vw,42px) 16px">
          <h2 style="font-size:clamp(15px,1.4vw,22px)"><span>مقالات أخرى قد تهمك</span></h2>
        </div>

        <div class="grid-4" id="postRows">
          <?php echo tqs_post_rows($tq_posts); ?>
        </div>

        <p class="dir-empty" id="postEmpty" hidden>لا توجد مقالات في هذا التصنيف حاليا.</p>

        <div style="text-align:center;margin-block-start:clamp(22px,2.6vw,36px)">
          <a class="btn btn--ghost" href="#posts">
            <svg aria-hidden="true" style="width:16px;height:16px"><use href="#i-grid"></use></svg>
            عرض المزيد من المقالات
          </a>
        </div>
      </div>

      <aside>
        <div class="side-card reveal">
          <h3>أقسام المدونة</h3>
          <nav class="side-list" aria-label="أقسام المدونة">
            <a href="#posts" data-cat="تعليم">
              <svg aria-hidden="true"><use href="#i-cap"></use></svg>تعليم</a>
            <a href="#posts" data-cat="تربية">
              <svg aria-hidden="true"><use href="#i-users"></use></svg>تربية</a>
            <a href="#posts" data-cat="تقنيات تعليمية">
              <svg aria-hidden="true"><use href="#i-monitor"></use></svg>تقنيات تعليمية</a>
            <a href="#posts" data-cat="تطوير ذات">
              <svg aria-hidden="true"><use href="#i-star"></use></svg>تطوير ذات</a>
            <a href="#posts" data-cat="أخبار المنصة">
              <svg aria-hidden="true"><use href="#i-bell"></use></svg>أخبار المنصة</a>
          </nav>
        </div>

        <div class="side-card reveal">
          <h3>مقالات ذات صلة</h3>
<?php /* من القاعدة لا من الوسم: كانت أربعة عناوين مختلقة بروابط `#`. */ ?>
<?php echo tqs_side_reads($tq_posts); ?>
        </div>

        <div class="newsletter reveal">
          <h3>اشترك في نشرتنا البريدية</h3>
          <p>احصل على أحدث المقالات والنصائح مباشرة إلى بريدك الإلكتروني.</p>
          <form data-validate novalidate>
            <label class="sr-only" for="nlEmail">البريد الإلكتروني</label>
            <input id="nlEmail" type="email" name="email" required
                   placeholder="أدخل بريدك الإلكتروني">
            <button type="submit" aria-label="اشتراك">
              <svg aria-hidden="true"><use href="#i-send"></use></svg>
            </button>
          </form>
          <p class="form-ok" data-ok>تم تسجيل بريدك — شكرا لاشتراكك.</p>
        </div>
      </aside>

    </div>
  </div>
</section>

<!-- ══════════ نداء الختام ══════════ -->
<section class="section">
  <div class="shell">
    <div class="cta">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2>رحلة التعلم لا تتوقف</h2>
        <p>ابدأ اليوم في تطوير مهاراتك ومهارات أطفالك</p>
        <a class="btn btn--gold" href="<?php echo base_url('plans'); ?>">استكشف المواد والبرامج</a>
      </div>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/cta-kids-cut.webp'); ?>" width="660" height="990" loading="lazy"
             decoding="async" alt="طفلان سعوديان يبتسمان ويحملان جهازا لوحيا">
      </div>
    </div>
  </div>
</section>
