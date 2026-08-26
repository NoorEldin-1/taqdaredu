<!--
title: عن المنصة — منصة تقدر
desc: منصة تعليمية سعودية رائدة تهدف إلى تقديم تجربة تعلم متكاملة تجمع بين الجودة والتقنية والقيم.
active: about
header: solid
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="page-hero" id="intro">
  <div class="shell">
    <div class="page-hero__grid">
      <span class="lantern lantern--l" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="page-hero__copy reveal">
        <h1><?php echo tq_text('about_us', 'hero_title', 'عن منصة تقدر'); ?>
          <span class="page-hero__sub"><?php
            echo tq_text('about_us', 'hero_sub', 'تعليم يلهم، وتمكين يبني المستقبل'); ?></span>
        </h1>
        <p class="page-hero__lede">
          <?php echo tq_text('about_us', 'hero_lede',
              'تقدر منصة تعليمية سعودية رائدة تهدف إلى تقديم تجربة تعلم متكاملة تجمع بين الجودة والتقنية والقيم، لنسهم في بناء جيل واع ومبدع وقادر على صناعة المستقبل.'); ?>
        </p>
        <div class="hero-mini">
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-badge"></use></svg></span>
            <div><b>مناهج معتمدة</b><span>وفق المنهج الرسمي</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-teacher"></use></svg></span>
            <div><b>معلمون متخصصون</b><span>لكل مادة وصف</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-report"></use></svg></span>
            <div><b>تقارير لولي الأمر</b><span>أولا بأول</span></div>
          </div>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/about-hero.webp'); ?>" width="960" height="1440"
                 alt="طفلان سعوديان يتشاركان جهازا لوحيا فوق كتاب مفتوح">
          </div>
<?php include __DIR__ . '/site/site_arch.php'; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ رؤيتنا ورسالتنا ══════════ -->
<section class="section" id="vision">
  <div class="shell">
    <div class="panel">
      <span class="lantern lantern--corner" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="section-head">
        <h2><span>رؤيتنا ورسالتنا</span></h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>
      <div class="cards-3">
        <article class="icard reveal">
          <?php /* كان مغلفا بريديا: خلط بين «الرسالة» بمعنى الغاية
                  و«الرسالة» بمعنى البريد — والأيقونة تقرأ قبل النص. */ ?>
          <span class="ico"><svg aria-hidden="true"><use href="#i-target"></use></svg></span>
          <h3>رسالتنا</h3>
          <p>تقديم محتوى تعليمي عالي الجودة بأساليب حديثة وتفاعلية، ينمي المهارات
             ويعزز القيم، ويمكن المتعلم من تحقيق طموحاته.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-eye"></use></svg></span>
          <h3>رؤيتنا</h3>
          <p>أن نكون الخيار الأول للتعليم الرقمي في العالم العربي، وأن نصنع أثرا
             حقيقيا في حياة المتعلمين وأسرهم.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-star"></use></svg></span>
          <h3>قيمنا</h3>
          <div class="chips">
            <span class="chip">الجودة</span><span class="chip">الإتقان</span>
            <span class="chip">الإبداع</span><span class="chip">المصداقية</span>
            <span class="chip">الابتكار</span><span class="chip">المسؤولية</span>
          </div>
        </article>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ تقدر بالأرقام ══════════ -->
<section class="section">
  <div class="shell">
    <div class="stats-band reveal">
      <h2>تقدر بالأرقام</h2>
      <?php /* TQ-STAT-ORPHAN — «الطلاب» و«الدروس» و«ساعات التعلم» و«مستوى
               الرضا» كانت حقولا في `taqdar_admin/stats` **لا تعرض في أي
               صفحة**: يملؤها المسؤول ويحفظ ولا يتغير شيء في الموقع، وشرح
               الحقل يعده بأنها تظهر هنا. فوصلت بمواضعها، وضبط الشرح على
               ما يجري فعلا. والحقل الفارغ لا يعرض بندا (انظر `tqs_stat`). */ ?>
      <div class="stats-band__grid">
        <?php echo tqs_stat('students','i-users','طالبا وطالبة','stats-band__item'); ?>
        <?php echo tqs_stat('teachers','i-teacher','معلم ومعلمة','stats-band__item'); ?>
        <?php echo tqs_stat('paths','i-target','برنامج تعليمي','stats-band__item'); ?>
        <?php echo tqs_stat('subjects','i-book','مادة تعليمية','stats-band__item'); ?>
        <?php echo tqs_stat('lessons','i-play','درسا','stats-band__item'); ?>
        <?php echo tqs_stat('books','i-curriculum','كتاب منهجي','stats-band__item'); ?>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ قصتنا ══════════ -->
<section class="section section--plain" id="story">
  <div class="shell">
    <div class="section-head">
      <h2><span>قصتنا</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>
    <div class="timeline">
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-bulb"></use></svg></span>
        <h3>بداية الفكرة</h3>
        <p>بدأت فكرة تقدر من إيماننا بأن التعليم هو أساس التغيير وبناء المستقبل.</p>
        <span class="year tq-ltr">2021</span>
      </div>
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-users"></use></svg></span>
        <h3>التأسيس</h3>
        <p>تأسست تقدر بهدف تصميم منصة تعليمية تجمع بين الجودة والتقنية والقيم.</p>
        <span class="year tq-ltr">2022</span>
      </div>
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-rocket"></use></svg></span>
        <h3>إطلاق المنصة</h3>
        <p>أطلقت المنصة لتقدم تجربة تعليم رقمية متكاملة للطلاب وأولياء الأمور.</p>
        <span class="year tq-ltr">2023</span>
      </div>
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-growth"></use></svg></span>
        <h3>النمو والتطوير</h3>
        <p>توسعنا في البرامج والمحتوى، وانضم إلينا آلاف المتعلمين والمعلمين.</p>
        <span class="year tq-ltr">2024</span>
      </div>
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-star"></use></svg></span>
        <h3>المستقبل</h3>
        <p>نواصل رحلتنا لنكون المنصة التعليمية العربية الأهم والأكثر تأثيرا.</p>
        <span class="year">مستمرون</span>
      </div>
    </div>
  </div>
</section>

<?php /* قسم «لماذا تقدر؟» حذف: نسخة ثانية من قسم في الصفحة
        الرئيسية بالمحتوى نفسه تقريبا — تكرار يطيل الصفحة ولا
        يضيف. والقسم باق في الرئيسية حيث يقرأ أولا. */ ?>


<!-- ══════════ نداء الختام ══════════ -->
<section class="section">
  <div class="shell">
    <div class="cta on-dark">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2>ابدأ رحلتك التعليمية مع تقدر</h2>
        <p>ابدأ رحلتك التعليمية الآن وطور مهاراتك لتحقيق طموحاتك</p>
        <div class="cta__actions">
          <a class="btn btn--gold" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب مجاني</a>
          <a class="btn btn--ghost" href="<?php echo base_url('plans'); ?>">استكشاف البرامج</a>
        </div>
      </div>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/about-cta.webp'); ?>" width="880" height="587" loading="lazy"
             decoding="async" alt="طفلان سعوديان يتشاركان حاسوبا محمولا"
            >
      </div>
    </div>
  </div>
</section>
