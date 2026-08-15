<?php
/**
 * صفحة الكتاب.
 *
 * الكتاب كان بطاقة وزر تحميل لا أكثر: بلا وصف ولا مؤلف ولا رابط يشارك.
 * وهو **المحتوى المجاني الوحيد** في المنصة — أي أنه أول ما يصل إليه
 * الزائر من محرك البحث، فصفحة له تعني بابا يدخل منه إلى بقية المنصة
 * بدل أن ينزل ملفا وينصرف.
 *
 * `$tq_book` صف الكتاب بفئته · `$tq_more` كتب أخرى قريبة منه.
 */
$tq_b     = $tq_book;
$tq_tones = array('math', 'arabic', 'science', 'islamic', 'english');
$tq_tone  = in_array((string) $tq_b['tone'], $tq_tones, true) ? (string) $tq_b['tone'] : 'math';
$tq_file  = tqs_book_file($tq_b['file']);
?>
<section class="page-hero page-hero--path">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url(); ?>">الرئيسية</a>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('catalog'); ?>">المواد والبرامج</a>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('catalog'); ?>?type=book">كتب المنهج</a>
<?php if (!empty($tq_b['cat_name'])): ?>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('catalog'); ?>?type=book&amp;cat=<?php echo html_escape($tq_b['cat_slug']); ?>"><?php
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
        <?php echo tqs_rich_text($tq_b['description'],
            'كتاب من المنهج السعودي المعتمد، متاح للتصفح والتحميل مجانا بلا تسجيل.'); ?>

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

<?php /* «تحميل مجاني بلا تسجيل» فوق زر يقول «الملف قيد الرفع» وعد
         ينقضه ما تحته مباشرة — فلا يكتب إلا حيث يصدق. */ ?>
<?php if ($tq_file !== ''): ?>
        <p class="bookface__free">
          <svg aria-hidden="true"><use href="#i-check"></use></svg>
          تحميل مجاني بلا تسجيل
        </p>
        <a class="btn btn--primary btn--block" href="<?php echo html_escape($tq_file); ?>" download>
          <svg aria-hidden="true"><use href="#i-download"></use></svg>حمل الكتاب
        </a>
        <a class="path-back" href="<?php echo html_escape($tq_file); ?>" target="_blank" rel="noopener">
          افتحه في المتصفح
        </a>
<?php else: ?>
        <?php /* زر معطل أصدق من رابط يقود إلى لا شيء */ ?>
        <button class="btn btn--primary btn--block" type="button" disabled>
          <svg aria-hidden="true"><use href="#i-clock"></use></svg>الملف قيد الرفع
        </button>
        <p class="tq-caption">سجل الكتاب منشور ولم يرفع ملفه بعد.</p>
<?php endif; ?>

        <a class="path-back" href="<?php echo base_url('catalog'); ?>?type=book">عودة إلى كل الكتب</a>
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
