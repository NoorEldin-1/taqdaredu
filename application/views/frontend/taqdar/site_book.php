<?php
/**
 * صفحة الكتاب.
 *
 * الكتاب كان بطاقة وزر تحميل لا أكثر: بلا وصف ولا مؤلف ولا رابط يشارك.
 * فصفحة له تعني بابا يدخل منه الزائر إلى بقية المنصة بدل أن ينزل ملفا
 * وينصرف.
 *
 * TQ-BOOK — وصار الكتاب وحدة بيع، فصارت الصفحة تجيب سؤالين لا واحدا:
 * **ما هذا الكتاب؟** و**كيف أفتحه؟**. والثاني له أربعة جواب متبادلة
 * (أملكه · فاتورة معلقة · يشترى · مجاني)، وكانت الصفحة تعرف واحدا.
 *
 * `$tq_book` صف الكتاب بفئته · `$tq_offer` عرضه من `Taqdar_book_model`
 * · `$tq_own` أمفتوح لقارئها؟ · `$tq_pend` فاتورة معلقة إن كانت
 * · `$tq_plans` باقات صفه · `$tq_more` كتب أخرى قريبة منه.
 */
$tq_b     = $tq_book;
$tq_tones = array('math', 'arabic', 'science', 'islamic', 'english');
$tq_tone  = in_array((string) $tq_b['tone'], $tq_tones, true) ? (string) $tq_b['tone'] : 'math';
$tq_file  = tqs_book_file($tq_b['file']);
$tq_slug  = trim((string) $tq_b['slug']) !== '' ? (string) $tq_b['slug'] : (string) $tq_b['id'];

/* العرض من مصدره الواحد، ومحايده حين لا يمرر: القالب يستدعى من
   `books_like()` وغيرها، ونداء بلا `$tq_offer` لا يجوز أن يسقط الصفحة. */
$tq_offer   = isset($tq_offer) && is_array($tq_offer) ? $tq_offer : array(
    'sellable' => false, 'price' => 0, 'list_price' => 0, 'off' => 0, 'days' => 0);
$tq_sell    = !empty($tq_offer['sellable']);
$tq_price   = (int) $tq_offer['price'];
$tq_list    = (int) $tq_offer['list_price'];
$tq_off     = (int) $tq_offer['off'];

/* أرخص باقة تفتح صفه — وبها يحسب الفارق. والأرخص لا الأولى: المقارنة
   تكون بأقل ما يفتح المرحلة، ورقم أعلى يجعل الفارق يبدو أكبر مما هو. */
$tq_plan = null;
foreach ((array) (isset($tq_plans) ? $tq_plans : array()) as $tq_p) {
    if ((int) $tq_p['price'] <= 0) continue;
    if ($tq_plan === null || (int) $tq_p['price'] < (int) $tq_plan['price']) $tq_plan = $tq_p;
}
$tq_gap = ($tq_plan && $tq_sell) ? max(0, (int) $tq_plan['price'] - $tq_price) : 0;
?>
<section class="page-hero page-hero--path">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url(); ?>">الرئيسية</a>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('books'); ?>"><?php echo t('الكتب'); ?></a>
<?php if (!empty($tq_b['cat_name'])): ?>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('books'); ?>?cat=<?php echo html_escape($tq_b['cat_slug']); ?>"><?php
          echo html_escape($tq_b['cat_name']); ?></a>
<?php endif; ?>
    </nav>
    <h1><?php echo html_escape($tq_b['title']); ?></h1>
<?php if ((string) $tq_b['subject'] !== ''): ?>
    <p class="page-hero__lead"><?php echo html_escape($tq_b['subject']); ?><?php
        echo !empty($tq_b['cat_name']) ? ' — ' . html_escape($tq_b['cat_name']) : ''; ?></p>
<?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="shell tq-cols-2">

    <div class="path-main">
      <div class="icard">
        <h2>عن هذا الكتاب</h2>
        <?php /* `tqs_rich_text` لا `html_escape`: بعض الأوصاف تحرر بمحرر
                 غني فتخزن `<p>…</p>` — والتهريب يطبعها حرفا. */ ?>
        <?php /* والافتراض يتبع حال الكتاب: «متاح مجانا بلا تسجيل» فوق
                 زر شراء وعد ينقضه ما تحته، وهو ما جعل النص الافتراضي
                 لا يصلح لكل كتاب بعد أن صار الكتاب يباع. */ ?>
        <?php echo tqs_rich_text($tq_b['description'], $tq_sell
            ? t('كتاب من كتب تقدر — يقرأ في مكتبتك صفحة صفحة بعد شرائه أو ضمن باقة صفك.')
            : t('كتاب من المنهج السعودي المعتمد، متاح للتصفح والتحميل مجانا بلا تسجيل.')); ?>

        <div class="path-facts">
<?php if ((string) $tq_b['subject'] !== ''): ?>
          <span><svg aria-hidden="true"><use href="#i-book"></use></svg><?php echo html_escape($tq_b['subject']); ?></span>
<?php endif; ?>
<?php if ((int) $tq_b['pages'] > 0): ?>
          <span><svg aria-hidden="true"><use href="#i-file"></use></svg><span class="tq-ltr"><?php
              echo (int) $tq_b['pages']; ?></span> صفحة</span>
<?php endif; ?>
<?php if ((string) $tq_b['author'] !== ''): ?>
          <span><svg aria-hidden="true"><use href="#i-users"></use></svg><?php echo html_escape($tq_b['author']); ?></span>
<?php endif; ?>
<?php if (!empty($tq_b['cat_name'])): ?>
          <span><svg aria-hidden="true"><use href="#i-curriculum"></use></svg><?php echo html_escape($tq_b['cat_name']); ?></span>
<?php endif; ?>
        </div>
      </div>

      <?php /* الكتاب وحده لا يعلم: يقرأ ثم يقف الطالب عند أول سؤال لا
               يجد من يجيبه. والوصلة إلى البرنامج المرافق هي ما يجعل
               للتحميل المجاني ما بعده. */ ?>
      <div class="icard">
        <h2>وماذا بعد الكتاب؟</h2>
        <p>الكتاب يعرض المادة، والبرنامج يشرحها ويقيسها: دروس مصورة، واختبار
           بعد كل درس، وتقرير يقول أين تقف وأين تحتاج مراجعة.</p>
        <a class="btn btn--primary" href="<?php echo base_url('catalog'); ?>?type=path<?php
            echo !empty($tq_b['cat_slug']) ? '&amp;cat=' . html_escape($tq_b['cat_slug']) : ''; ?>">
          برامج <?php echo html_escape(!empty($tq_b['cat_name']) ? $tq_b['cat_name'] : 'المرحلة'); ?>
        </a>
      </div>
    </div>

    <aside class="path-buy">
      <div class="icard icard--sticky">
        <div class="bookface book-card__cover" data-tone="<?php echo $tq_tone; ?>">
<?php if ((string) $tq_b['cover'] !== ''): ?>
          <img src="<?php echo html_escape(tqs_img($tq_b['cover'], 'subj-math')); ?>"
               width="420" height="560" loading="lazy" decoding="async" alt="">
<?php else: ?>
          <span class="book-card__spine" aria-hidden="true"></span>
          <span class="book-card__label"><?php echo html_escape((string) $tq_b['subject'] !== ''
              ? $tq_b['subject'] : $tq_b['title']); ?></span>
<?php endif; ?>
        </div>

<?php /* TQ-BOOK — أربعة أبواب متبادلة، والصفحة كانت تعرف واحدا.
         والترتيب هو ترتيب من يقرأ: **ما أملكه** أولا، فمن دفع لا يعرض
         عليه أن يدفع ثانية؛ ثم **فاتورة معلقة**، فمن اشترى قبل دقيقة
         ولم يحول لا يشتري مرة ثانية فيصدر له صفان وفاتورتان؛ ثم
         **الشراء**؛ ثم **التحميل المجاني** كما كان منذ كتبت الصفحة. */ ?>

<?php if (!empty($tq_own)): ?>
        <?php /* مفتوح له: وعد بالقراءة لا بالتحميل — القارئ في البوابة
                 يعرض صفحة صفحة، والرابط المباشر ينسخ ويوزع. */ ?>
        <p class="bookface__free">
          <svg aria-hidden="true"><use href="#i-check"></use></svg>
          <?php echo t('هذا الكتاب مفتوح لك'); ?>
        </p>
        <a class="btn btn--primary btn--block" href="<?php echo base_url('student/library'); ?>">
          <svg aria-hidden="true"><use href="#i-book"></use></svg><?php echo t('اقرأه في مكتبتك'); ?>
        </a>

<?php elseif (!empty($tq_pend)): ?>
        <?php /* فاتورة صدرت ولم تسدد: يدل على سدادها لا يعرض شراء ثانيا. */ ?>
        <p class="bookface__free">
          <svg aria-hidden="true"><use href="#i-clock"></use></svg>
          <?php echo t('فاتورتك صدرت ولم تسدد بعد'); ?>
        </p>
        <a class="btn btn--primary btn--block" href="<?php echo base_url('student/subscription'); ?>">
          <?php echo t('أكمل الدفع'); ?>
        </a>
        <p class="tq-caption"><?php echo t('ويفتح الكتاب في مكتبتك بعد التحقق من الحوالة.'); ?></p>

<?php elseif ($tq_sell): ?>
        <?php /* السعر أولا وهو أكبر ما في البطاقة: من فتح صفحة كتاب
                 مدفوع جاء يسأل عن ثمنه. و`plan-card__price` لا صنف
                 جديد: بطاقة تعرض سعرا تلبس ما تلبسه أختها، وورقتان
                 لشيء واحد تفترقان عند أول تعديل. */ ?>
        <p class="plan-card__price">
          <?php echo tqs_money($tq_price); ?>
<?php if ($tq_off > 0): ?>
          <small><s class="tq-ltr"><?php echo number_format($tq_list / 100, 0); ?></s>
                 <?php echo t('خصم'); ?> <span class="tq-ltr"><?php echo (int) $tq_off; ?>%</span></small>
<?php endif; ?>
        </p>
        <p class="tq-caption">
<?php if ((int) $tq_offer['days'] > 0): ?>
          <?php echo t('وصول'); ?> <?php echo tq_num((int) $tq_offer['days']); ?> <?php echo t('يوما من الشراء.'); ?>
<?php else: ?>
          <?php echo t('وصول دائم — يبقى في مكتبتك.'); ?>
<?php endif; ?>
        </p>

        <a class="btn btn--primary btn--block"
           href="<?php echo base_url('book-checkout/' . (int) $tq_b['id']); ?>">
          <svg aria-hidden="true"><use href="#i-check"></use></svg><?php echo t('اشتر الكتاب'); ?>
        </a>

<?php else: ?>
<?php /* «تحميل مجاني بلا تسجيل» فوق زر يقول «الملف قيد الرفع» وعد
         ينقضه ما تحته مباشرة — فلا يكتب إلا حيث يصدق. */ ?>
<?php   if ($tq_file !== ''): ?>
        <p class="bookface__free">
          <svg aria-hidden="true"><use href="#i-check"></use></svg>
          <?php echo t('تحميل مجاني بلا تسجيل'); ?>
        </p>
        <a class="btn btn--primary btn--block" href="<?php echo html_escape($tq_file); ?>" download>
          <svg aria-hidden="true"><use href="#i-download"></use></svg><?php echo t('حمل الكتاب'); ?>
        </a>
        <a class="path-back" href="<?php echo html_escape($tq_file); ?>" target="_blank" rel="noopener">
          <?php echo t('افتحه في المتصفح'); ?>
        </a>
<?php   else: ?>
        <?php /* زر معطل أصدق من رابط يقود إلى لا شيء */ ?>
        <button class="btn btn--primary btn--block" type="button" disabled>
          <svg aria-hidden="true"><use href="#i-clock"></use></svg><?php echo t('الملف قيد الرفع'); ?>
        </button>
        <p class="tq-caption"><?php echo t('سجل الكتاب منشور ولم يرفع ملفه بعد.'); ?></p>
<?php   endif; ?>
<?php endif; ?>

<?php /* ── الباقة تحت الشراء المفرد، وبفارق السعر لا بسعرها ────────
         «وبـ٨٠١ ر.س زيادة تفتح المرحلة كلها» يقارن ما يقارن؛ ورقمان
         متجاوران بلا جسر يجعلان المشتري يوازن بين خيارين ولا يعرف ما
         يشتريه أحدهما زيادة. وهو حكم صفحة الكورس نفسه. */ ?>
<?php if (empty($tq_own) && $tq_plan): ?>
        <div class="book-alt">
          <p class="tq-caption"><?php echo t('أو افتح المرحلة كلها'); ?></p>
          <p>
            <strong><?php echo html_escape($tq_plan['name_ar']); ?></strong>
            —
<?php if ($tq_sell && $tq_gap > 0): ?>
            <?php echo t('وبـ'); ?><b class="tq-ltr"><?php echo number_format($tq_gap / 100, 0); ?></b>
            <?php echo t('ر.س زيادة تفتح كتب المرحلة كلها ومعها البرامج والدروس والاختبارات.'); ?>
<?php else: ?>
            <b class="tq-ltr"><?php echo number_format(((int) $tq_plan['price']) / 100, 0); ?></b>
            <?php echo t('ر.س — تفتح كتب المرحلة كلها ومعها البرامج والدروس والاختبارات.'); ?>
<?php endif; ?>
          </p>
          <a class="btn btn--ghost btn--block"
             href="<?php echo base_url('plan/' . rawurlencode((string) $tq_plan['code'])); ?>">
            <?php echo t('تفاصيل الباقة'); ?>
          </a>
        </div>
<?php elseif (empty($tq_own) && (int) $tq_b['grade_id'] > 0): ?>
        <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
          <?php echo t('ويفتح هذا الكتاب لكل مشترك في باقة صفه.'); ?>
        </p>
<?php endif; ?>

        <a class="path-back" href="<?php echo base_url('books'); ?>"><?php echo t('عودة إلى كل الكتب'); ?></a>
      </div>
    </aside>

  </div>
</section>

<?php if (!empty($tq_more)): ?>
<section class="section section--tint">
  <div class="shell">
    <div class="section-head">
      <h2><span>كتب أخرى قد تفيدك</span></h2>
    </div>
    <div class="cgrid">
<?php foreach ($tq_more as $tq_it) echo tqs_cat_card($tq_it); ?>
    </div>
  </div>
</section>
<?php endif; ?>
