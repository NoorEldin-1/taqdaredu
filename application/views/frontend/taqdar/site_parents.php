<!--
title: أولياء الأمور — منصة تقدر
desc: نوفر لكم كل ما تحتاجونه لمتابعة تقدم أبنائكم التعليمي، لدعمهم وتحفيزهم نحو مستقبل مشرق.
active: parents
header: solid
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="page-hero">
  <div class="shell">
    <div class="page-hero__grid">
      <span class="lantern lantern--l" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="page-hero__copy reveal">
        <h1><?php echo tq_text('site_parents', 'hero_title', 'أولياء الأمور'); ?>
          <span class="page-hero__sub"><?php
            echo tq_text('site_parents', 'hero_sub', 'شركاء في رحلة تعليم أبنائنا'); ?></span>
        </h1>
        <p class="page-hero__lede">
          <?php echo tq_text('site_parents', 'hero_lede',
              'نوفر لكم كل ما تحتاجونه لمتابعة تقدم أبنائكم التعليمي، لدعمهم وتحفيزهم نحو مستقبل مشرق.'); ?>
        </p>
        <div class="page-hero__cta">
          <a class="btn btn--primary" href="<?php echo base_url('sign_up'); ?>"><?php
            echo tq_text('site_parents', 'hero_cta_1', 'ابدأ الآن'); ?></a>
          <a class="btn btn--ghost" href="#features"><?php
            echo tq_text('site_parents', 'hero_cta_2', 'استكشف المنصة'); ?></a>
        </div>
        <div class="hero-mini">
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-chart"></use></svg></span>
            <div><b>تقارير تفصيلية</b><span>متابعة دقيقة لتقدم الأبناء</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-growth"></use></svg></span>
            <div><b>السلوك والمواظبة</b><span>نظرة شاملة على الأداء</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-chat"></use></svg></span>
            <div><b>تواصل مباشر</b><span>مع المعلمين والإدارة</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-shield"></use></svg></span>
            <div><b>بيئة آمنة وموثوقة</b><span>حماية وخصوصية البيانات</span></div>
          </div>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/parents-hero.webp'); ?>" width="960" height="1440"
                 alt="أب سعودي يتابع مع ابنه على جهاز لوحي">
          </div>
<?php include __DIR__ . '/site/site_arch.php'; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ كل ما تحتاجه لمتابعة أبنائك ══════════ -->
<section class="section" id="features">
  <div class="shell">
    <div class="panel">
      <span class="lantern lantern--corner" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="section-head">
        <h2><span>كل ما تحتاجه لمتابعة أبنائك</span></h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>
      <div class="grid-5">
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-monitor"></use></svg></span>
          <h3>تقارير شاملة</h3>
          <p>تقارير دورية تفصيلية عن الأداء الدراسي والمهارات المكتسبة.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-clipboard"></use></svg></span>
          <h3>متابعة الواجبات</h3>
          <p>اطلع على الواجبات والأنشطة ومواعيد التسليم بسهولة.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-chat"></use></svg></span>
          <h3>التواصل مع المعلمين</h3>
          <p>تواصل مباشر مع المعلمين لمتابعة تقدم أبنائك أولا بأول.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-user"></use></svg></span>
          <h3>السلوك والحضور</h3>
          <p>متابعة الحضور والانضباط والسلوك داخل المنصة.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-bell"></use></svg></span>
          <h3>تنبيهات لحظية</h3>
          <p>تنبيهات فورية بكل جديد يخص أداء ومهام أبنائك.</p>
        </article>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ كيف نضمن لك متابعة سهلة؟ ══════════ -->
<section class="section section--plain">
  <div class="shell">
    <div class="section-head">
      <h2><span>كيف نضمن لك متابعة سهلة وفعالة؟</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>
    <div class="steps">
      <div class="step reveal">
        <span class="step__n">1</span>
        <h3>تسجيل الدخول</h3><p>سجل دخولك إلى حساب ولي الأمر الخاص بك.</p>
      </div>
      <div class="step reveal">
        <span class="step__n">2</span>
        <h3>اختر أبناءك</h3><p>أضف أبناءك واربطهم بحسابك بسهولة.</p>
      </div>
      <div class="step reveal">
        <span class="step__n">3</span>
        <h3>تابع التقدم</h3><p>اطلع على التقارير والدرجات والواجبات والأنشطة.</p>
      </div>
      <div class="step reveal">
        <span class="step__n">4</span>
        <h3>كن جزءا من النجاح</h3><p>شارك أبناءك الرحلة وكن داعمهم الأول.</p>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ لوحة المتابعة والتطبيق ══════════ -->
<section class="section" id="dashboard">
  <div class="shell">
    <div class="showcase">
      <div class="showcase__panel reveal">
        <h2>لوحة متابعة ولي الأمر</h2>
        <p>واجهة سهلة تمنحك نظرة شاملة على أداء أبنائك في مكان واحد.</p>
        <ul class="showcase__list">
          <li><svg aria-hidden="true"><use href="#i-check"></use></svg>ملخص الأداء الأكاديمي</li>
          <li><svg aria-hidden="true"><use href="#i-check"></use></svg>تحليل نقاط القوة والضعف</li>
          <li><svg aria-hidden="true"><use href="#i-check"></use></svg>متابعة الأنشطة والواجبات</li>
          <li><svg aria-hidden="true"><use href="#i-check"></use></svg>التواصل مع المدرسة والمعلمين</li>
        </ul>
        <a class="btn btn--gold" href="<?php echo base_url('sign_up'); ?>">عرض نموذج اللوحة</a>
      </div>

      <!-- اللوحة مبنية بالـCSS لا كصورة: تبقى حادة وتتبع التوكنات -->
      <div class="dash reveal" role="img"
           aria-label="نموذج لوحة متابعة ولي الأمر: متوسط الأداء 85٪، المعدل العام 4.6، عدد المهام 12">
        <div class="dash__bar">
          <b>مرحبا، فاطمة</b>
          <span class="dash__dots" aria-hidden="true"><i></i><i></i><i></i></span>
        </div>
        <div class="dash__body">
          <div class="dash__kpis">
            <div class="dash__kpi"><b>85%</b><span>متوسط الأداء</span></div>
            <div class="dash__kpi"><b>4.6</b><span>المعدل العام</span></div>
            <div class="dash__kpi"><b>12</b><span>مهمة مكتملة</span></div>
          </div>
          <div class="dash__chart">
            <svg viewBox="0 0 200 84" aria-hidden="true">
              <g stroke="rgba(201,165,95,.28)" stroke-width="1">
                <path d="M8 70h184M8 50h184M8 30h184"/>
              </g>
              <path d="M16 62 60 54 104 40 148 30 188 18" fill="none" stroke="#023331"
                    stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M16 62 60 54 104 40 148 30 188 18V78H16Z" fill="rgba(2,51,49,.08)"/>
              <g fill="#C9A55F">
                <circle cx="16" cy="62" r="3"/><circle cx="60" cy="54" r="3"/>
                <circle cx="104" cy="40" r="3"/><circle cx="148" cy="30" r="3"/>
                <circle cx="188" cy="18" r="3"/>
              </g>
            </svg>
          </div>
          <div class="dash__kids">
            <div class="dash__kid">
              <span class="av"><svg aria-hidden="true"><use href="#i-user"></use></svg></span>
              <div><b>محمد أحمد</b><span>المرحلة المتوسطة</span></div>
            </div>
            <div class="dash__kid">
              <span class="av"><svg aria-hidden="true"><use href="#i-user"></use></svg></span>
              <div><b>سارة أحمد</b><span>المرحلة الابتدائية</span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="appcard reveal" id="app">
        <h3>تطبيق تقدر بين يديك</h3>
        <p>تابع أبناءك من أي مكان وفي أي وقت من خلال تطبيقنا المخصص لأولياء الأمور.</p>
        <div class="phone" aria-hidden="true">
          <img src="<?php echo tq_site_asset('img/logo-light.webp'); ?>" alt="" width="280" height="163">
        </div>
        <div class="stores"><?php include __DIR__ . '/site/site_stores.php'; ?></div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ ماذا يقول أولياء الأمور؟ ══════════ -->
<section class="section section--plain">
  <div class="shell">
    <div class="section-head">
      <h2><span>ماذا يقول أولياء الأمور؟</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>
    <div class="cards-3">
      <article class="tcard reveal">
        <svg class="tcard__mark" aria-hidden="true"><use href="#i-quote"></use></svg>
        <p>التواصل مع المعلمين أصبح أسهل والمنصة توفر وقت وجهد كبير.</p>
        <div class="tcard__who">
          <img src="<?php echo tq_site_asset('img/avatar-3.webp'); ?>" width="130" height="130" loading="lazy"
               decoding="async" alt="">
          <div><b>نوال المطيري</b><span>أم لطالبة في المرحلة الثانوية</span></div>
        </div>
        <div class="stars" aria-label="خمس نجوم من خمس">
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
        </div>
      </article>

      <article class="tcard reveal">
        <svg class="tcard__mark" aria-hidden="true"><use href="#i-quote"></use></svg>
        <p>التقارير مفصلة جدا وتساعدني على دعم ابنتي في نقاط ضعفها أولا بأول.</p>
        <div class="tcard__who">
          <img src="<?php echo tq_site_asset('img/avatar-2.webp'); ?>" width="130" height="130" loading="lazy"
               decoding="async" alt="">
          <div><b>أحمد الشهري</b><span>أب لطالبة في المرحلة الابتدائية</span></div>
        </div>
        <div class="stars" aria-label="خمس نجوم من خمس">
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
        </div>
      </article>

      <article class="tcard reveal">
        <svg class="tcard__mark" aria-hidden="true"><use href="#i-quote"></use></svg>
        <p>المنصة رائعة وسهلة. أتابع ابني أولا بأول وأشعر بالطمأنينة على مستواه الدراسي.</p>
        <div class="tcard__who">
          <img src="<?php echo tq_site_asset('img/avatar-1.webp'); ?>" width="130" height="130" loading="lazy"
               decoding="async" alt="">
          <div><b>سارة العنزي</b><span>أم لطالب في المرحلة المتوسطة</span></div>
        </div>
        <div class="stars" aria-label="خمس نجوم من خمس">
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
          <svg aria-hidden="true"><use href="#i-star"></use></svg>
        </div>
      </article>
    </div>
  </div>
</section>

<!-- ══════════ نداء الختام ══════════ -->
<section class="section" id="signup">
  <div class="shell">
    <div class="cta on-dark">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2>معا نصنع مستقبلا أفضل</h2>
        <p>انضم إلى آلاف أولياء الأمور الذين يثقون بمنصة تقدر التعليمية</p>
        <div class="cta__actions">
          <a class="btn btn--gold" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب مجاني</a>
          <a class="btn btn--ghost" href="<?php echo base_url('login'); ?>">تسجيل الدخول</a>
        </div>
      </div>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/parents-cta.webp'); ?>" width="880" height="587" loading="lazy"
             decoding="async" alt="أب سعودي وابنه يتابعان الدروس على حاسوب محمول"
            >
      </div>
    </div>
  </div>
</section>
