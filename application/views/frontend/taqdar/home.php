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
title: منصة تقدر — نبني العقول ونصنع المستقبل
desc: استكشف المواد والبرامج والمواد والكورسات في منصة تقدر — مصنفة حسب المرحلة، مع كتب المنهج السعودي جاهزة للتحميل.
active: home
header: plain
css: pages
-->

<!-- ══════════ الهيرو ══════════ -->
<section class="hero hero--video">
  <div class="shell">
    <?php /* الفيديو داخل إطاره: لا يمس الترويسة ولا يمتد إلى الحواف.
             و`src` يحقن من `site.js` — الوسم الذي يحمل `src` يبدأ
             التفاوض على التنزيل قبل أن يعرف عرض الشاشة، فينزل
             الملف الكبير على جوال يكفيه الصغير. */ ?>
    <div class="hero__frame">
      <div class="hero__bg" aria-hidden="true">
        <img class="hero__poster" src="<?php echo tq_site_asset('video/hero-poster.webp'); ?>"
             alt="" width="1280" height="720" fetchpriority="high">
        <video class="hero__video" data-tq-hero-video preload="none" muted playsinline loop
               poster="<?php echo tq_site_asset('video/hero-poster.webp'); ?>"></video>
        <?php /* زر الإيقاف: الحركة الدائمة خلف نص تتعب القراءة، ومن
                يريد إيقافها لم يكن له سبيل. ويظهره السكربت متى بدأ
                التشغيل فعلا — زر يوقف ما لا يتحرك تشويش. */ ?>
        <button class="hero__vtoggle" type="button" data-tq-hero-toggle hidden
                aria-label="إيقاف الخلفية المتحركة">
          <svg aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
        <span class="hero__scrim"></span>
      </div>

      <?php /* النصوص تحرر من «المحتوى والموقع › نصوص الصفحات» في اللوحة.
               والمكتوب هنا هو الافتراضي الذي يعرض ما لم يحرر — فالصفحة
               تعمل بقاعدة فارغة كما تعمل بقاعدة ممتلئة. */ ?>
      <div class="hero__copy reveal">
        <p class="hero__eyebrow"><?php echo tq_text('home', 'hero_eyebrow', 'منصة تعليمية سعودية'); ?></p>
        <h1><?php echo tq_text('home', 'hero_title_1', 'نبني العقول'); ?><br><?php
            echo tq_text('home', 'hero_title_2', 'ونصنع'); ?>
            <span class="gold"><?php echo tq_text('home', 'hero_title_3', 'المستقبل'); ?></span></h1>
        <p class="hero__lede">
          <?php echo tq_text('home', 'hero_lede',
              'منصة تعليمية سعودية على المنهج الرسمي: برامج يبنيها معلمون، '
            . 'وتقييم يقيس الإتقان لا الحضور، وتقارير يراها ولي الأمر أولا بأول.'); ?>
        </p>
        <div class="hero__cta">
          <a class="btn btn--primary" href="<?php echo base_url('sign_up'); ?>">
            <?php echo tq_text('home', 'hero_cta_1', 'ابدأ رحلة التعلم'); ?></a>
          <a class="btn btn--ghost-light" href="<?php echo base_url('catalog'); ?>">
            <?php echo tq_text('home', 'hero_cta_2', 'تصفح البرامج'); ?>
            <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg>
          </a>
        </div>
      </div>

      <?php /* شريط المميزات: كان أربعة أعمدة تحت الهيرو فحذف لتكراره،
               ويعود هنا **داخل** الإطار ثلاثة لا أربعة — على الفيديو لا
               تحته. وحقوله الثلاثة هي نفسها في اللوحة، فما كان يحرر
               بلا أثر صار له أثر. */ ?>
      <div class="hero__feats reveal">
<?php foreach (array(
    array('i-spark',   'feat_1_t', 'تعلم تفاعلي',    'feat_1_d', 'تجربة ممتعة وفعالة'),
    array('i-teacher', 'feat_2_t', 'معلمون متميزون', 'feat_2_d', 'ذوو خبرة عالية'),
    array('i-report',  'feat_3_t', 'متابعة المنهاج', 'feat_3_d', 'تقرير يومي للأهل'),
) as $tq_f): ?>
        <div class="hero__feat">
          <span class="ico"><svg aria-hidden="true"><use href="#<?php echo $tq_f[0]; ?>"></use></svg></span>
          <div>
            <b><?php echo tq_text('home', $tq_f[1], $tq_f[2]); ?></b>
            <span><?php echo tq_text('home', $tq_f[3], $tq_f[4]); ?></span>
          </div>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ لماذا تختار تقدر؟ ══════════ -->
<?php /* شريط داكن بخمسة أعمدة على تصميم المالك. وكل نص هنا افتراضي
        يحرر من «المحتوى والموقع › نصوص الصفحات» — فما يوصف وعدا
        تجاريا يبقى بيد المالك لا بيد القالب. */ ?>
<section class="section" id="teachers">
  <div class="shell">
    <div class="whyd reveal">
      <div class="whyd__head">
        <h2><?php echo tq_text('home', 'why_title', 'لماذا تختار منصة تقدر؟'); ?></h2>
      </div>
      <div class="whyd__grid">
<?php
/* الأيقونة من سبرايت الموقع نفسه — لا نخلة ولا سيفين بقرار المالك،
   والمنهج يمثله كتاب. */
$tq_why = array(
    array('i-price',   'why_1_t', 'باقات مرنة',            'why_1_d', 'خيارات تناسب كل أسرة'),
    array('i-book',    'why_2_t', 'المنهج السعودي',        'why_2_d', 'مواكب للمنهج الرسمي'),
    array('i-teacher', 'why_3_t', 'معلمون متخصصون',        'why_3_d', 'خبرة وكفاءة في كل مادة'),
    array('i-chart',   'why_4_t', 'تقارير دورية للأولياء', 'why_4_d', 'متابعة شفافة وتقدم مستمر'),
    array('i-badge',   'why_5_t', 'نتائج حقيقية',          'why_5_d', 'تحسن ملحوظ في الأداء الدراسي'),
);
foreach ($tq_why as $w):
?>
        <div class="whyd__item">
          <span class="whyd__ico" aria-hidden="true"><svg><use href="#<?php echo $w[0]; ?>"></use></svg></span>
          <h3><?php echo tq_text('home', $w[1], $w[2]); ?></h3>
          <p><?php echo tq_text('home', $w[3], $w[4]); ?></p>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php /* ══════════════════════════════════════════════════════════════
        ترتيب الأقسام بقرار المالك (٢٠٢٦-٠٨-٢٦): الباقة وحدة البيع
        فهي تلي الهيرو مباشرة، ثم لماذا تختار، ثم الجامعات، ثم آراء
        أولياء الأمور آخر ما يقرأ قبل نداء الختام. وشريط المميزات
        الأربع حذف: مكرر لما اندمج في الهيرو نفسه.
        ══════════════════════════════════════════════════════════════ */ ?>
<?php /* ══════════════════════════════════════════════════════════════
        قسم الباقات — الكساء الداكن (`css/home-dark.css`) بقرار المالك
        ٢٠٢٦-٠٨-٢٦. الباقة وحدة البيع فهي تلي الهيرو مباشرة.
        والبيانات من `tqs_bundles()` نفسها التي تقرؤها صفحة `/plans`
        البيضاء — كساءان لمصدر واحد، صفر استعلام جديد.
        ══════════════════════════════════════════════════════════════ */ ?>
<section class="section" id="bundles">
  <div class="shell">
   <div class="p26d">
    <div class="p26d__head reveal">
      <h2><?php echo tq_text('home', 'plans_title', 'اختر الباقة المناسبة لرحلة التعلم'); ?></h2>
      <p><?php echo tq_text('home', 'plans_lede',
             'خطط مرنة ومصممة بعناية لتلبية احتياجات كل مرحلة دراسية'); ?></p>
      <div class="p26d__rule" aria-hidden="true"><i></i></div>
    </div>

<?php /* التبويب والمبدّل قرار واحد «أي مرحلة وبأي دورة» — فيقربان
        في غلاف واحد بدل أن يبدو كل منهما قرارا مستقلا. */ ?>
    <div class="p26d__switch">
<?php $tq_tabs = tqs_stage_tabs(); if ($tq_tabs !== ''): ?>
      <div class="p26d__tabs"><?php echo $tq_tabs; ?></div>
<?php endif; ?>
<?php /* مبدل الدورة — **عرض لا فوترة**: الباقة السنوية تعرض معادلها
        الشهري ومعه «تدفع سنويا». والزران مبدلان لا روابط، ويعملان بلا
        تنقل ولا استعلام. ولا يطبعان إن لم تكن في المعروض باقة لها
        دورتان (TQ-PLAN-CYCLE). */ ?>
      <?php echo tqs_plan_cycle_switch(); ?>
    </div>

<?php echo tqs_bundles_dark(); ?>

    <div class="p26d__more">
      <a href="<?php echo base_url('plans'); ?>">قارن الباقات كاملة
        <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg></a>
    </div>
   </div>
  </div>
</section>


<!-- ══════════ الجامعات ══════════ -->
<?php $tq_unis = tqs_universities(); ?>
<?php if (!empty($tq_unis)): ?>
<section class="section unis" id="universities">
  <div class="shell">
    <div class="section-head reveal">
      <h2><span>نعد طلابنا لجامعات المملكة</span></h2>
      <p>وجهات يلتحق بها خريجو الثانوية في السعودية. ومنصة تقدر تعد لاختباراتها،
         ولا ترتبط بأي منها ولا تمثلها.</p>
    </div>
  </div>
  <?php /* الشريط يمر خارج الغلاف ليبلغ حافتي الشاشة. والحركة تتوقف عند
           التحويم وعند `prefers-reduced-motion` — شريط لا يقف لا يقرأ. */ ?>
  <div class="shell">
    <?php echo tqs_carousel(tqs_universities_slides(), 'جامعات المملكة'); ?>
  </div>
</section>
<?php endif; ?>
<!-- ══════════ آراء أولياء الأمور ══════════ -->
<?php
/* المصدر واحد مع `/parents`: الآراء من اللوحة
   (`taqdar_admin/module/testimonials`) لا من هذا الملف. وكانت البطاقات
   الثلاث مكتوبة هنا باسم ومدينة ونص وصورة، فمن حرر رأيا في اللوحة رآه
   في صفحة أولياء الأمور ولم يره في الرئيسية — والرئيسية هي التي تفتح.

   والثلاث فقط: الرئيسية عرض لا سرد، وصفحة `/parents` تعرضها كلها في
   كاروسل. والفارغ يرجع إلى ما كان — قاعدة بلا صف منشور تعرض الآراء
   الثلاث الأصلية بصورها حرفا بحرف، كما يرد `tq_text()` نص القالب. */
$tq_quotes = array_slice(tq_testimonials(), 0, 3);
if (!$tq_quotes) {
    $tq_quotes = array(
        array('name' => 'فاطمة القحطاني', 'role' => 'الرياض', 'rating' => 5,
              'body' => 'منصة رائعة! ابني أصبح أكثر حماسا للتعلم وتحسنت درجاته بشكل ملحوظ.',
              'avatar' => 'img/avatar-1.webp'),
        array('name' => 'عبدالله السبيعي', 'role' => 'جدة', 'rating' => 5,
              'body' => 'أكثر ما أعجبني المتابعة المستمرة والتقارير المفصلة عن مستوى ابني.',
              'avatar' => 'img/avatar-2.webp'),
        array('name' => 'سارة العنزي', 'role' => 'الدمام', 'rating' => 5,
              'body' => 'المعلمون متعاونون والمحتوى مميز جدا. أنصح كل أم بتجربة المنصة.',
              'avatar' => 'img/avatar-3.webp'),
    );
}
?>
<section class="section" id="parents">
  <div class="shell">
    <div class="panel">
      <div class="section-head">
        <h2><?php echo tq_text('site_parents', 'quotes_title', 'ماذا يقول أولياء الأمور؟'); ?></h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>

      <div class="cards-3">
        <?php foreach ($tq_quotes as $q): ?>
        <article class="quote-card reveal">
          <div class="quote-card__head">
            <div><b><?php echo html_escape($q['name']); ?></b><?php
              if ((string) $q['role'] !== '') echo '<span>' . html_escape($q['role']) . '</span>';
            ?></div>
            <?php /* الصورة للبطاقات المكتوبة في القالب وحدها: رأي اللوحة
                     بلا صورة عمدا — وجه لا يعرفه القارئ لا يزيد الرأي صدقا. */ ?>
            <?php if (!empty($q['avatar'])): ?>
            <img src="<?php echo tq_site_asset($q['avatar']); ?>" width="130" height="130" alt="" loading="lazy" decoding="async">
            <?php endif; ?>
          </div>
          <p><?php echo html_escape($q['body']); ?></p>
          <?php $stars = (int) $q['rating']; if ($stars > 0): ?>
          <div class="stars" aria-label="<?php echo $stars; ?> من 5">
            <?php for ($i = 0; $i < $stars; $i++): ?>
            <svg aria-hidden="true"><use href="#i-star"></use></svg>
            <?php endfor; ?>
          </div>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>



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
        <p>سجل الآن وامنح طفلك مستقبلا مشرقا</p>
        <a class="btn btn--gold" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب مجاني</a>
        <a class="cta__login" href="<?php echo base_url('login'); ?>">أو تسجيل الدخول</a>
      </div>

      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/cta-kids-cut.webp'); ?>" width="660" height="990"
             alt="طفلان سعوديان يبتسمان ويحملان جهازا لوحيا" loading="lazy" decoding="async">
      </div>
    </div>
  </div>
</section>


<?php /* بانر المسابقات: أخفي بطلب المالك. الصفحة `/competitions` قائمة
        ورابطها في القائمة العلوية — فالإخفاء من الرئيسية لا يلغي الميزة. */ ?>
