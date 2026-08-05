<?php
/* البيانات من نموذج واحد — لا استعلام في العرض.
   و`get_instance()` لا `$this->load`: تحميل نموذج داخل عرض CI3
   يُنتج بترًا صامتًا للصفحة، لا خطأً يُنبّه. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_site_model', 'tq_m');
$tq_m  = $tq_ci->tq_m;
$tq_cat = trim((string) $tq_ci->input->get('cat'));
$tq_cats  = $tq_m->categories();
$tq_mats  = $tq_m->materials($tq_cat);
?>
<!--
title: منصّة تقدّر — نبني العقول ونصنع المستقبل
desc: استكشف المواد والبرامج والمواد والكورسات في منصة تقدّر — مصنّفة حسب المرحلة، مع كتب المنهج السعودي جاهزة للتحميل.
active: home
header: plain
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="hero hero--video">
  <div class="shell">
    <?php /* الفيديو داخل إطاره: لا يمسّ الترويسة ولا يمتدّ إلى الحوافّ.
             و`src` يُحقن من `site.js` — الوسم الذي يحمل `src` يبدأ
             التفاوض على التنزيل قبل أن يُعرَف عرض الشاشة، فيُنزَّل
             الملفّ الكبير على جوّالٍ يكفيه الصغير. */ ?>
    <div class="hero__frame">
      <div class="hero__bg" aria-hidden="true">
        <img class="hero__poster" src="<?php echo tq_site_asset('video/hero-poster.webp'); ?>"
             alt="" width="1280" height="720" fetchpriority="high">
        <video class="hero__video" data-tq-hero-video preload="none" muted playsinline loop
               poster="<?php echo tq_site_asset('video/hero-poster.webp'); ?>"></video>
        <?php /* زرّ الإيقاف: الحركة الدائمة خلف نصٍّ تُتعب القراءة، ومن
                يريد إيقافها لم يكن له سبيل. ويُظهره السكربت متى بدأ
                التشغيل فعلًا — زرٌّ يوقف ما لا يتحرّك تشويش. */ ?>
        <button class="hero__vtoggle" type="button" data-tq-hero-toggle hidden
                aria-label="إيقاف الخلفية المتحركة">
          <svg aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
        <span class="hero__scrim"></span>
      </div>

      <div class="hero__copy reveal">
        <h1>نبني العقول<br>ونصنع <span class="gold">المستقبل</span></h1>
        <p class="hero__lede">
          منصّة تعليمية سعودية على المنهج الرسميّ: برامجُ يبنيها معلّمون،
          وتقييمٌ يقيس الإتقان لا الحضور، وتقاريرُ يراها وليّ الأمر أوّلًا بأوّل.
        </p>
        <div class="hero__cta">
          <a class="btn btn--primary" href="<?php echo base_url('sign_up'); ?>">ابدأ رحلة التعلّم</a>
          <a class="btn btn--ghost-light" href="<?php echo base_url('plans'); ?>">
            تصفَّح البرامج
            <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ شريط المميزات ══════════ -->
<section class="features">
  <div class="shell">
    <div class="features__bar reveal">
      <div class="feature">
        <span class="feature__icon"><svg aria-hidden="true"><use href="#i-badge"></use></svg></span>
        <div><b class="feature__t">مناهج معتمدة</b><p>وفق أحدث المعايير</p></div>
      </div>
      <div class="feature">
        <span class="feature__icon"><svg aria-hidden="true"><use href="#i-spark"></use></svg></span>
        <div><b class="feature__t">تعلم تفاعلي</b><p>تجربة ممتعة وفعّالة</p></div>
      </div>
      <div class="feature">
        <span class="feature__icon"><svg aria-hidden="true"><use href="#i-teacher"></use></svg></span>
        <div><b class="feature__t">معلمون متميزون</b><p>ذوو خبرة عالية</p></div>
      </div>
      <div class="feature">
        <span class="feature__icon"><svg aria-hidden="true"><use href="#i-report"></use></svg></span>
        <div><b class="feature__t">متابعة مستمرة</b><p>تقارير دورية للأهالي</p></div>
      </div>
    </div>
  </div>
</section>





<!-- ══════════ لماذا تختار تقدّر؟ ══════════ -->
<section class="section" id="teachers">
  <div class="shell">
    <div class="why">
      <h2>لماذا تختار منصة تقدّر؟</h2>
      <div class="why__grid">
        <div class="why__item">
          <span class="ico"><svg aria-hidden="true"><use href="#i-shield"></use></svg></span>
          <h3>تجربة تعليمية<span>آمنة ومحفّزة</span></h3>
        </div>
        <div class="why__item">
          <span class="ico"><svg aria-hidden="true"><use href="#i-price"></use></svg></span>
          <h3>أسعار مناسبة<span>وباقات مرنة</span></h3>
        </div>
        <div class="why__item">
          <span class="ico"><svg aria-hidden="true"><use href="#i-support"></use></svg></span>
          <h3>دعم فني<span>على مدار الساعة</span></h3>
        </div>
        <div class="why__item">
          <span class="ico"><svg aria-hidden="true"><use href="#i-curriculum"></use></svg></span>
          <h3>متوافق مع<span>المناهج السعودية</span></h3>
        </div>
        <div class="why__item">
          <span class="ico"><svg aria-hidden="true"><use href="#i-quality"></use></svg></span>
          <h3>محتوى عربي<span>بجودة عالية</span></h3>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ آراء أولياء الأمور ══════════ -->
<section class="section" id="parents">
  <div class="shell">
    <div class="panel">
      <div class="section-head">
        <h2>ماذا يقول أولياء الأمور؟</h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>

      <div class="cards-3">
        <article class="quote-card reveal">
          <div class="quote-card__head">
            <div><b>فاطمة القحطاني</b><span>الرياض</span></div>
            <img src="<?php echo tq_site_asset('img/avatar-1.webp'); ?>" width="130" height="130" alt="" loading="lazy" decoding="async">
          </div>
          <p>منصة رائعة! ابني أصبح أكثر حماسًا للتعلم وتحسّنت درجاته بشكل ملحوظ.</p>
          <div class="stars" aria-label="خمس نجوم من خمس">
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
          </div>
        </article>

        <article class="quote-card reveal">
          <div class="quote-card__head">
            <div><b>عبدالله السبيعي</b><span>جدة</span></div>
            <img src="<?php echo tq_site_asset('img/avatar-2.webp'); ?>" width="130" height="130" alt="" loading="lazy" decoding="async">
          </div>
          <p>أكثر ما أعجبني المتابعة المستمرة والتقارير المفصّلة عن مستوى ابني.</p>
          <div class="stars" aria-label="خمس نجوم من خمس">
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
          </div>
        </article>

        <article class="quote-card reveal">
          <div class="quote-card__head">
            <div><b>سارة العنزي</b><span>الدمام</span></div>
            <img src="<?php echo tq_site_asset('img/avatar-3.webp'); ?>" width="130" height="130" alt="" loading="lazy" decoding="async">
          </div>
          <p>المعلمون متعاونون والمحتوى مميّز جدًّا. أنصح كل أمّ بتجربة المنصة.</p>
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
  </div>
</section>



<?php /* ══════════ TQ-HOME-BUNDLES ══════════
        الباقة وحدة البيع، فهي قلب الصفحة. والمواد تحتها **محتوًى**
        يُطمئن لا سلعةٌ تُشترى — ولذلك بلا سعرٍ ولا زرّ شراء. */ ?>
<section class="section" id="bundles">
  <div class="shell">
    <div class="section-head reveal">
      <h2><span>اختر باقتك</span></h2>
      <p>المنهج كاملًا في باقةٍ واحدة — لا مادّةً مادّة. كلّ باقةٍ تحوي ما قبلها وتزيد.</p>
    </div>
    <?php echo tqs_stage_tabs(); ?>
    <?php echo tqs_bundle_cards(); ?>
  </div>
</section>


<!-- ══════════ الجامعات ══════════ -->
<?php $tq_unis = tqs_universities(); ?>
<?php if (!empty($tq_unis)): ?>
<section class="section unis" id="universities">
  <div class="shell">
    <div class="section-head reveal">
      <h2><span>نُعِدّ طلابنا لجامعات المملكة</span></h2>
      <p>وجهاتٌ يلتحق بها خرّيجو الثانوية في السعودية. ومنصّة تقدّر تُعدّ لاختباراتها،
         ولا ترتبط بأيٍّ منها ولا تمثّلها.</p>
    </div>
  </div>
  <?php /* الشريط يمرّ خارج الغلاف ليبلغ حافّتَي الشاشة. والحركة تتوقّف عند
           التحويم وعند `prefers-reduced-motion` — شريطٌ لا يقف لا يُقرأ. */ ?>
  <div class="shell">
    <?php echo tqs_carousel(tqs_universities_slides(), 'جامعات المملكة'); ?>
  </div>
</section>
<?php endif; ?>
<!-- ══════════ نداء الختام ══════════ -->
<section class="section" id="signup">
  <div class="shell">
    <div class="cta">
      <span class="lantern lantern--r" aria-hidden="true">
<?php include __DIR__ . '/site/site_lantern.php'; ?>
      </span>
      <span class="lantern lantern--l lantern--slow" aria-hidden="true">
<?php include __DIR__ . '/site/site_lantern.php'; ?>
      </span>

      <div class="cta__copy">
        <h2>ابدأ رحلة التعلم الآن</h2>
        <p>سجّل الآن وامنح طفلك مستقبلًا مشرقًا</p>
        <a class="btn btn--gold" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب مجاني</a>
        <a class="cta__login" href="<?php echo base_url('login'); ?>">أو تسجيل الدخول</a>
      </div>

      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/cta-kids-cut.webp'); ?>" width="660" height="990"
             alt="طفلان سعوديان يبتسمان ويحملان جهازًا لوحيًّا" loading="lazy" decoding="async">
      </div>
    </div>
  </div>
</section>


<?php /* بانر المسابقات: أُخفي بطلب المالك. الصفحة `/competitions` قائمة
        ورابطها في القائمة العلوية — فالإخفاء من الرئيسية لا يُلغي الميزة. */ ?>
