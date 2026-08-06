<!--
title: الطلاب — منصة تقدر
desc: منصة تقدر تمنحك كل ما تحتاجه لتتعلم بذكاء، وتطور مهاراتك، وتحقق طموحاتك بثقة.
active: students
header: solid
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="page-hero">
  <div class="shell">
    <div class="page-hero__grid">
      <span class="lantern lantern--l" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="page-hero__copy reveal">
        <h1>الطلاب
          <span class="page-hero__sub">رحلتك التعليمية تبدأ من هنا</span>
        </h1>
        <p class="page-hero__lede">
          منصة تقدر تمنحك كل ما تحتاجه لتتعلم بذكاء، وتطور مهاراتك،
          وتحقق طموحاتك بثقة.
        </p>
        <div class="page-hero__cta">
          <a class="btn btn--primary" href="<?php echo base_url('sign_up'); ?>">ابدأ التعلم الآن</a>
          <a class="btn btn--ghost" href="#paths">استكشاف البرامج</a>
        </div>
        <div class="hero-mini">
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-certificate"></use></svg></span>
            <div><b>شهادات معتمدة</b><span>تعزز مستقبلك</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-growth"></use></svg></span>
            <div><b>تقدم مستمر</b><span>وخطوات دقيقة</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-play"></use></svg></span>
            <div><b>محتوى تفاعلي</b><span>مصمم خصيصا لك</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-clock"></use></svg></span>
            <div><b>تعلم في أي وقت</b><span>ومن أي مكان</span></div>
          </div>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/students-hero.webp'); ?>" width="960" height="1440"
                 alt="ثلاثة طلاب سعوديين يدرسون معا حول حاسوب محمول">
          </div>
<?php include __DIR__ . '/site/site_arch.php'; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ كل ما تحتاجه لتنجح ══════════ -->
<section class="section" id="need">
  <div class="shell">
    <div class="panel">
      <span class="lantern lantern--corner" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="section-head">
        <h2><span>كل ما تحتاجه لتنجح</span></h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>
      <div class="grid-5">
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-star"></use></svg></span>
          <h3>برامج متنوعة</h3><p>اختر ما يناسب ميولك وأهدافك.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-clipboard"></use></svg></span>
          <h3>دروس تفاعلية</h3><p>فيديوهات، اختبارات، أنشطة وتطبيقات عملية.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-book"></use></svg></span>
          <h3>متابعة التقدم</h3><p>لوحات تحكم وتقارير تساعدك على التطور.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-users"></use></svg></span>
          <h3>مجتمع طلابي ملهم</h3><p>تواصل وتعاون مع طلاب يشاركونك الطموح.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-support"></use></svg></span>
          <h3>دعم مستمر</h3><p>فريق دعم وإرشاد لمساعدتك في كل خطوة.</p>
        </article>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ برامج تناسب كل طالب ══════════ -->
<section class="section section--plain" id="paths">
  <div class="shell">
    <div class="section-head">
      <h2><span>برامج تعليمية تناسب كل طالب</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>
    <div class="grid-5">
      <article class="path-card reveal">
        <div class="path-card__media">
          <img src="<?php echo tq_site_asset('img/path-primary.webp'); ?>" width="620" height="620" loading="lazy"
               decoding="async" alt="طفل سعودي يكتب في دفتره"></div>
        <div class="path-card__body">
          <span class="path-card__icon"><svg aria-hidden="true"><use href="#i-book"></use></svg></span>
          <h3>المرحلة الابتدائية</h3><p>من الصف 1 إلى 6</p>
          <a class="btn btn--text" href="<?php echo base_url('plans'); ?>" style="padding:10px 0;font-size:.85em">
            استكشف البرنامج
            <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
        </div>
      </article>
      <article class="path-card reveal">
        <div class="path-card__media">
          <img src="<?php echo tq_site_asset('img/path-middle.webp'); ?>" width="620" height="620" loading="lazy"
               decoding="async" alt="طفلة سعودية تستخدم جهازا لوحيا"></div>
        <div class="path-card__body">
          <span class="path-card__icon"><svg aria-hidden="true"><use href="#i-headphones"></use></svg></span>
          <h3>المرحلة المتوسطة</h3><p>من الصف 1 إلى 3</p>
          <a class="btn btn--text" href="<?php echo base_url('plans'); ?>" style="padding:10px 0;font-size:.85em">
            استكشف البرنامج
            <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
        </div>
      </article>
      <article class="path-card reveal">
        <div class="path-card__media">
          <img src="<?php echo tq_site_asset('img/path-secondary.webp'); ?>" width="620" height="620" loading="lazy"
               decoding="async" alt="طالب سعودي يحمل جهازا لوحيا"></div>
        <div class="path-card__body">
          <span class="path-card__icon"><svg aria-hidden="true"><use href="#i-cap"></use></svg></span>
          <h3>المرحلة الثانوية</h3><p>من الصف 1 إلى 3</p>
          <a class="btn btn--text" href="<?php echo base_url('plans'); ?>" style="padding:10px 0;font-size:.85em">
            استكشف البرنامج
            <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
        </div>
      </article>
      <article class="path-card reveal">
        <div class="path-card__media">
          <img src="<?php echo tq_site_asset('img/path-qudurat.webp'); ?>" width="620" height="620" loading="lazy"
               decoding="async" alt="طالب سعودي يستخدم حاسوبا محمولا"></div>
        <div class="path-card__body">
          <span class="path-card__icon"><svg aria-hidden="true"><use href="#i-target"></use></svg></span>
          <h3>اختبارات القدرات</h3><p>تجهيز شامل للاختبار</p>
          <a class="btn btn--text" href="<?php echo base_url('plans'); ?>" style="padding:10px 0;font-size:.85em">
            استكشف البرنامج
            <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
        </div>
      </article>
      <article class="path-card reveal">
        <div class="path-card__media">
          <img src="<?php echo tq_site_asset('img/path-digital.webp'); ?>" width="620" height="620" loading="lazy"
               decoding="async" alt="طالب سعودي يعمل على حاسوبه"></div>
        <div class="path-card__body">
          <span class="path-card__icon"><svg aria-hidden="true"><use href="#i-monitor"></use></svg></span>
          <h3>المهارات الرقمية</h3><p>مهارات المستقبل الأول</p>
          <a class="btn btn--text" href="<?php echo base_url('plans'); ?>" style="padding:10px 0;font-size:.85em">
            استكشف البرنامج
            <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
        </div>
      </article>
    </div>
    <div style="text-align:center;margin-block-start:clamp(22px,2.6vw,36px)">
      <a class="btn btn--primary" href="<?php echo base_url('plans'); ?>">عرض جميع البرامج</a>
    </div>
  </div>
</section>

<!-- ══════════ تجربة تعليمية تصنع الفرق ══════════ -->
<section class="section">
  <div class="shell">
    <div class="stats-band stats-band--art reveal">
      <div class="stats-band__body">
        <h2>تجربة تعليمية تصنع الفرق</h2>
        <div class="stats-band__grid" style="--n:4">
        <?php echo tqs_stat('paths','i-target','برنامج تعليمي','stats-band__item'); ?>
        <?php echo tqs_stat('subjects','i-book','مادة تعليمية','stats-band__item'); ?>
        <?php echo tqs_stat('books','i-curriculum','كتاب منهجي','stats-band__item'); ?>
        <?php echo tqs_stat('teachers','i-teacher','معلم ومعلمة','stats-band__item'); ?>
        </div>
      </div>
      <img src="<?php echo tq_site_asset('img/student-stats-cut.webp'); ?>" width="660" height="990" loading="lazy"
           decoding="async" alt="طالب سعودي يرفع إبهامه علامة الرضا">
    </div>
  </div>
</section>

<!-- ══════════ نحن معك في كل خطوة ══════════ -->
<section class="section section--plain">
  <div class="shell">
    <div class="section-head">
      <h2><span>نحن معك في كل خطوة</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>
    <div class="split">
      <div class="grid-2 reveal">
        <article class="icard">
          <span class="ico"><svg aria-hidden="true"><use href="#i-shield"></use></svg></span>
          <h3>بيئة آمنة</h3><p>منصة آمنة تحمي خصوصيتك وتوفر تجربة مريحة.</p>
        </article>
        <article class="icard">
          <span class="ico"><svg aria-hidden="true"><use href="#i-curriculum"></use></svg></span>
          <h3>محتوى موثوق</h3><p>جميع الدروس من خبراء ومعلمين معتمدين.</p>
        </article>
        <article class="icard">
          <span class="ico"><svg aria-hidden="true"><use href="#i-eye"></use></svg></span>
          <h3>متابعة شخصية</h3><p>نراقب تقدمك ونقدم لك توصيات مخصصة.</p>
        </article>
        <article class="icard">
          <span class="ico"><svg aria-hidden="true"><use href="#i-clipboard"></use></svg></span>
          <h3>إرشاد أكاديمي</h3><p>نساعدك على اختيار البرنامج المناسب لك.</p>
        </article>
      </div>
      <div class="split__art reveal">
        <img src="<?php echo tq_site_asset('img/students-support.webp'); ?>" width="880" height="587" loading="lazy"
             decoding="async" alt="طالب سعودي يذاكر على مكتبه تحت ضوء دافئ">
      </div>
    </div>
  </div>
</section>

<!-- ══════════ نداء الختام ══════════ -->
<section class="section" id="signup">
  <div class="shell">
    <div class="cta on-dark">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2>ابدأ رحلتك نحو مستقبل أفضل</h2>
        <p>تعلم بثقة.. طور بذكاء.. وحقق طموحاتك</p>
        <div class="cta__actions">
          <a class="btn btn--gold" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب مجاني</a>
          <a class="btn btn--ghost" href="<?php echo base_url('login'); ?>">تسجيل الدخول</a>
        </div>
      </div>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/cta-kids-cut.webp'); ?>" width="660" height="990" loading="lazy"
             decoding="async" alt="طفلان سعوديان يبتسمان ويحملان جهازا لوحيا">
      </div>
    </div>
  </div>
</section>
