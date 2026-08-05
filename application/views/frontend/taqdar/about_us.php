<!--
title: عن المنصة — منصّة تقدّر
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
        <h1>عن منصة تقدّر
          <span class="page-hero__sub">تعليم يُلهم، وتمكين يبني المستقبل</span>
        </h1>
        <p class="page-hero__lede">
          تقدّر منصة تعليمية سعودية رائدة تهدف إلى تقديم تجربة تعلم متكاملة
          تجمع بين الجودة والتقنية والقيم، لنسهم في بناء جيل واعٍ ومبدع
          وقادر على صناعة المستقبل.
        </p>
        <div class="page-hero__cta">
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/about-hero.webp'); ?>" width="960" height="1440"
                 alt="طفلان سعوديان يتشاركان جهازًا لوحيًّا فوق كتاب مفتوح">
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
          <?php /* كان مغلّفًا بريديًّا: خلطٌ بين «الرسالة» بمعنى الغاية
                  و«الرسالة» بمعنى البريد — والأيقونة تُقرأ قبل النصّ. */ ?>
          <span class="ico"><svg aria-hidden="true"><use href="#i-target"></use></svg></span>
          <h3>رسالتنا</h3>
          <p>تقديم محتوى تعليمي عالي الجودة بأساليب حديثة وتفاعلية، ينمّي المهارات
             ويعزّز القيم، ويمكّن المتعلّم من تحقيق طموحاته.</p>
        </article>
        <article class="icard reveal">
          <span class="ico"><svg aria-hidden="true"><use href="#i-eye"></use></svg></span>
          <h3>رؤيتنا</h3>
          <p>أن نكون الخيار الأول للتعليم الرقمي في العالم العربي، وأن نصنع أثرًا
             حقيقيًّا في حياة المتعلّمين وأسرهم.</p>
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

<!-- ══════════ تقدّر بالأرقام ══════════ -->
<section class="section">
  <div class="shell">
    <div class="stats-band reveal">
      <h2>تقدّر بالأرقام</h2>
      <div class="stats-band__grid" style="--n:4">
        <?php echo tqs_stat('paths','i-target','برنامج تعليمي','stats-band__item'); ?>
        <?php echo tqs_stat('subjects','i-book','مادة تعليمية','stats-band__item'); ?>
        <?php echo tqs_stat('books','i-curriculum','كتاب منهجي','stats-band__item'); ?>
        <?php echo tqs_stat('teachers','i-teacher','معلم ومعلمة','stats-band__item'); ?>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ قصّتنا ══════════ -->
<section class="section section--plain" id="story">
  <div class="shell">
    <div class="section-head">
      <h2><span>قصّتنا</span></h2>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>
    <div class="timeline">
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-bulb"></use></svg></span>
        <h3>بداية الفكرة</h3>
        <p>بدأت فكرة تقدّر من إيماننا بأن التعليم هو أساس التغيير وبناء المستقبل.</p>
        <span class="year tq-ltr">2021</span>
      </div>
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-users"></use></svg></span>
        <h3>التأسيس</h3>
        <p>تأسّست تقدّر بهدف تصميم منصة تعليمية تجمع بين الجودة والتقنية والقيم.</p>
        <span class="year tq-ltr">2022</span>
      </div>
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-rocket"></use></svg></span>
        <h3>إطلاق المنصة</h3>
        <p>أُطلقت المنصة لتقدّم تجربة تعليم رقمية متكاملة للطلاب وأولياء الأمور.</p>
        <span class="year tq-ltr">2023</span>
      </div>
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-growth"></use></svg></span>
        <h3>النمو والتطوير</h3>
        <p>توسّعنا في البرامج والمحتوى، وانضمّ إلينا آلاف المتعلّمين والمعلمين.</p>
        <span class="year tq-ltr">2024</span>
      </div>
      <div class="tl-item reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-star"></use></svg></span>
        <h3>المستقبل</h3>
        <p>نواصل رحلتنا لنكون المنصة التعليمية العربية الأهمّ والأكثر تأثيرًا.</p>
        <span class="year">مستمرّون</span>
      </div>
    </div>
  </div>
</section>

<?php /* قسم «لماذا تقدّر؟» حُذف: نسخةٌ ثانية من قسمٍ في الصفحة
        الرئيسية بالمحتوى نفسه تقريبًا — تكرارٌ يُطيل الصفحة ولا
        يضيف. والقسم باقٍ في الرئيسية حيث يُقرأ أوّلًا. */ ?>

<!-- ══════════ فريقنا ══════════ -->
<section class="section" id="team">
  <div class="shell">
    <div class="panel">
      <div class="section-head">
        <h2><span>فريقنا</span></h2>
        <p>نحن فريق من خبراء التعليم والتقنية والمحتوى، نعمل بشغف لنقدّم أفضل
           تجربة تعليمية لأبنائنا.</p>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>
      <div class="grid-6">
        <?php /* الرئيس التنفيذيّ أوّلًا في ترتيب القراءة: كان آخر
        بطاقةٍ — أي أقصى اليسار في العربية. */ ?>
        <article class="team-card reveal">
          <img src="<?php echo tq_site_asset('img/team-6.webp'); ?>" width="130" height="130" loading="lazy"
               decoding="async" alt="">
          <div class="team-card__body"><h3>عبدالله الشمري</h3><p>الرئيس التنفيذي</p></div>
        </article>
        <article class="team-card reveal">
          <img src="<?php echo tq_site_asset('img/team-1.webp'); ?>" width="130" height="130" loading="lazy"
               decoding="async" alt="">
          <div class="team-card__body"><h3>محمد الحربي</h3><p>مدير التسويق</p></div>
        </article>
        <article class="team-card reveal">
          <img src="<?php echo tq_site_asset('img/team-2.webp'); ?>" width="130" height="130" loading="lazy"
               decoding="async" alt="">
          <div class="team-card__body"><h3>د. نورة العتيبي</h3><p>مديرة المحتوى التعليمي</p></div>
        </article>
        <article class="team-card reveal">
          <img src="<?php echo tq_site_asset('img/team-3.webp'); ?>" width="130" height="130" loading="lazy"
               decoding="async" alt="">
          <div class="team-card__body"><h3>خالد اليامي</h3><p>مدير العمليات</p></div>
        </article>
        <article class="team-card reveal">
          <img src="<?php echo tq_site_asset('img/team-4.webp'); ?>" width="130" height="130" loading="lazy"
               decoding="async" alt="">
          <div class="team-card__body"><h3>سارة المطيري</h3><p>مديرة تجربة المستخدم</p></div>
        </article>
        <article class="team-card reveal">
          <img src="<?php echo tq_site_asset('img/team-5.webp'); ?>" width="130" height="130" loading="lazy"
               decoding="async" alt="">
          <div class="team-card__body"><h3>إبراهيم القحطاني</h3><p>مدير التقنية</p></div>
        </article>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ نداء الختام ══════════ -->
<section class="section">
  <div class="shell">
    <div class="cta on-dark">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2>ابدأ رحلتك التعليمية مع تقدّر</h2>
        <p>ابدأ رحلتك التعليمية الآن وطوّر مهاراتك لتحقيق طموحاتك</p>
        <div class="cta__actions">
          <a class="btn btn--gold" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب مجاني</a>
          <a class="btn btn--ghost" href="<?php echo base_url('plans'); ?>">استكشاف البرامج</a>
        </div>
      </div>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/about-cta.webp'); ?>" width="880" height="587" loading="lazy"
             decoding="async" alt="طفلان سعوديان يتشاركان حاسوبًا محمولًا"
            >
      </div>
    </div>
  </div>
</section>
