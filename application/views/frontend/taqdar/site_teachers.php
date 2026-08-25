<?php
/* البيانات من نموذج واحد — لا استعلام في العرض.
   و`get_instance()` لا `$this->load`: تحميل نموذج داخل عرض CI3
   ينتج بترا صامتا للصفحة، لا خطأ ينبه. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_site_model', 'tq_m');
$tq_m  = $tq_ci->tq_m;
$tq_teachers = $tq_m->teachers();
?>
<!--
title: المعلمون — منصة تقدر
desc: نخبة من المعلمين المتخصصين المتميزين في تقديم تجارب تعليمية فريدة تلهم العقول وتنمي المهارات.
active: teachers
header: solid
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="page-hero">
  <div class="shell">
    <div class="page-hero__grid">
      <span class="lantern lantern--l" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="page-hero__copy reveal">
        <h1><?php echo tq_text('site_teachers', 'hero_title', 'المعلمون'); ?>
          <span class="page-hero__sub"><?php
            echo tq_text('site_teachers', 'hero_sub', 'خبرات ملهمة.. تعليم يرتقي'); ?></span>
        </h1>
        <p class="page-hero__lede">
          <?php echo tq_text('site_teachers', 'hero_lede',
              'نخبة من المعلمين المتخصصين المتميزين في تقديم تجارب تعليمية فريدة تلهم العقول وتنمي المهارات.'); ?>
        </p>
        <div class="hero-mini">
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-star"></use></svg></span>
            <div><b>تأثير حقيقي</b><span>نتائج ملموسة ومستدامة</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-book"></use></svg></span>
            <div><b>أساليب تعليم حديثة</b><span>تفاعلية ومبتكرة</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-badge"></use></svg></span>
            <div><b>معلمون معتمدون</b><span>ذوو خبرة وكفاءة عالية</span></div>
          </div>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/teachers-hero.webp'); ?>" width="960" height="1440"
                 alt="أربعة معلمين ومعلمات سعوديين يقفون معا">
          </div>
<?php include __DIR__ . '/site/site_arch.php'; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ شريط الأرقام ══════════ -->
<section class="section">
  <div class="shell">
    <div class="stat-strip reveal">
        <?php /* انظر TQ-STAT-ORPHAN في `about_us.php`. */ ?>
        <?php echo tqs_stat('teachers','i-teacher','معلم ومعلمة','stat-strip__item'); ?>
        <?php echo tqs_stat('students','i-users','طالبا وطالبة','stat-strip__item'); ?>
        <?php echo tqs_stat('subjects','i-book','مادة تعليمية','stat-strip__item'); ?>
        <?php echo tqs_stat('paths','i-target','برنامج تعليمي','stat-strip__item'); ?>
        <?php echo tqs_stat('rating','i-star','مستوى الرضا','stat-strip__item'); ?>
    </div>
  </div>
</section>

<!-- ══════════ دليل المعلمين ══════════ -->
<section class="section" id="directory">
  <div class="shell">
    <div class="panel">
      <span class="lantern lantern--corner" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="section-head">
        <h2><span>تعرف على معلمينا المتميزين</span></h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>

      <div class="directory-bar reveal" id="teacherBar">
        <label class="field field--grow">
          <svg aria-hidden="true"><use href="#i-search"></use></svg>
          <span class="sr-only">ابحث عن معلم أو مادة</span>
          <input type="search" id="teacherSearch" placeholder="ابحث عن معلم أو مادة…">
        </label>
        <label class="field field--select">
          <svg aria-hidden="true"><use href="#i-filter"></use></svg>
          <span class="sr-only">المرحلة</span>
          <select id="teacherStage">
            <option value="">جميع المراحل</option>
            <option value="primary">المرحلة الابتدائية</option>
            <option value="middle">المرحلة المتوسطة</option>
            <option value="secondary">المرحلة الثانوية</option>
          </select>
          <svg aria-hidden="true"><use href="#i-chevron"></use></svg>
        </label>
        <label class="field field--select">
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <span class="sr-only">الترتيب</span>
          <select id="teacherSort">
            <option value="rating">ترتيب: الأعلى تقييما</option>
            <option value="reviews">ترتيب: الأكثر مراجعات</option>
            <option value="courses">ترتيب: الأكثر دورات</option>
          </select>
          <svg aria-hidden="true"><use href="#i-chevron"></use></svg>
        </label>
      </div>

      <?php
      /* TQ-DIR-ALL · «عرض جميع المعلمين» كان يشير إلى `sign_up` — أي إلى
         لوحة من كان داخلا، وإلى نموذج تسجيل من لم يكن. وفي الحالين لا
         يعرض معلما واحدا، والزر يعد بذلك نصا.
         والدليل يعرض كل معلم عام أصلا (`teachers()` بلا حد)، فالزر لا
         وجهة له خارج الصفحة. فصار ما يقوله: أول عشرة تعرض، والبقية
         يكشفها الزر — ولا يطبع الزر أصلا إن لم يكن وراءه أحد. */
      /* الكاروسل لا يحتاج طيًّا: البطاقات كلها في المضمار والزائر
         يمرّرها. فـ`fold=0` والزر يسقط من تلقائه. */
      $tq_fold = 0;
      $tq_more = 0;
      ?>
<?php echo tqs_carousel(tqs_teachers($tq_teachers, $tq_fold),
                        'المعلمون', 'carousel2--teachers', 'teacherGrid'); ?>

      <p class="dir-empty" id="teacherEmpty" hidden>لا توجد نتائج مطابقة — جرب كلمة أخرى.</p>

      <?php if ($tq_more > 0): ?>
        <div style="text-align:center;margin-block-start:clamp(22px,2.6vw,36px)">
          <button class="btn btn--ghost" type="button" id="teacherMore"
                  data-label-more="عرض جميع المعلمين"
                  data-label-less="اعرض أقل">
            <span data-tq-morelbl>عرض جميع المعلمين</span>
            <span class="tq-ltr">(<?php echo (int) $tq_more; ?>+)</span>
            <svg class="dir-icon" aria-hidden="true" style="width:16px;height:16px">
              <use href="#i-arrow"></use></svg>
          </button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══════════ لماذا معلمونا مميزون؟ ══════════ -->
<section class="section section--plain" id="why">
  <div class="shell">
    <div class="section-head">
      <h2><span>لماذا معلمونا مميزون؟</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>
    <div class="grid-5">
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-star"></use></svg></span>
        <h3>تقييمات ممتازة</h3><p>تقييمات عالية من الطلاب وأولياء الأمور.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-chat"></use></svg></span>
        <h3>دعم ومتابعة</h3><p>متابعة مستمرة لضمان تحقيق أفضل النتائج.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-bulb"></use></svg></span>
        <h3>شغف بالتعليم</h3><p>رسالة واضحة في إلهام الطلاب وتمكينهم.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-growth"></use></svg></span>
        <h3>تطوير مستمر</h3><p>يواكبون أحدث أساليب التعليم والتقنيات.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-pen"></use></svg></span>
        <h3>خبرة عملية عالية</h3><p>سنوات من الخبرة في التدريس والتدريب.</p>
      </article>
    </div>
  </div>
</section>

<!-- ══════════ نداء الختام ══════════ -->
<section class="section" id="join">
  <div class="shell">
    <div class="cta on-dark">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2>ابدأ رحلتك التعليمية مع أفضل المعلمين</h2>
        <p>اختر برنامجك التعليمي وابدأ التعلم اليوم</p>
        <div class="cta__actions">
          <?php /* «تصفح البرامج» كان يقود إلى نموذج التسجيل — وهو عكس
                   ما يقوله: من يريد أن يتصفح قبل أن يسجل يجد نفسه مسجلا
                   أولا. والبرامج في الكتالوج، وهو مفتوح بلا حساب. */ ?>
          <a class="btn btn--gold" href="<?php echo base_url('catalog'); ?>">تصفح البرامج مجانا</a>
          <a class="btn btn--ghost" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب</a>
        </div>
      </div>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/teachers-cta.webp'); ?>" width="880" height="587" loading="lazy"
             decoding="async" alt="طفلان سعوديان يدرسان معا على جهاز لوحي"
            >
      </div>
    </div>
  </div>
</section>
