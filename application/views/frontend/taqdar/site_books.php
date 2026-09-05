<?php
/**
 * TQ-BOOK — صفحة الكتب.
 *
 * ═══ ولماذا صفحة والكتاب نوع في الكتالوج ═══
 *
 * حذف بند «الكتب» يوم كان الكتاب **صفا مجانيا** يحمل ملفه: بند يقود
 * إلى مرشح واحد من أربعة يقسم ما جمعته صفحة واحدة. وصار الكتاب اليوم
 * وحدة بيع لها سعر وصاحب وباقة تفتحها — أي أنه يجيب سؤالا لا يجيبه
 * الكتالوج: **«ما كتب المنصة، وبكم؟»**. ومن جاء يشتري كتابا لا يريد أن
 * يمر على البرامج والباقات ليجده.
 *
 * ═══ وهو المحرك نفسه لا كتالوج ثان ═══
 *
 * الشبكة والمرشحات والبحث والترقيم كلها من `Taqdar_catalog_model`
 * و`taqdar_catalog_helper`، والوسم وسم `site_catalog.php` نفسه —
 * والنوع مثبت على `book` في المتحكم لا هنا. وصفحة تبحث وترشح بقواعدها
 * هي تفترق عن أختها عند أول تعديل، ثم يقرأ الزائر عددين مختلفين
 * للشيء الواحد.
 *
 * `$tq_f` المرشحات · `$tq_res` النتيجة — بالعقد نفسه حرفا بحرف.
 */
$tq_h1   = tq_text_raw('site_books', 'hero_title', 'كتب المنصة');
$tq_lead = tq_text_raw('site_books', 'hero_lede',
    'كتب المنهج وكتب معلمي تقدر — تقرأ في مكتبتك صفحة صفحة. منها ما يحمل مجانا، ومنها ما يشترى وحده أو يفتح ضمن باقتك.');
include __DIR__ . '/site/site_pagehero.php';

$tq_sorts = array(
    'featured'   => 'المميز أولا',
    'newest'     => 'الأحدث',
    'title'      => 'أبجديا',
    'price_asc'  => 'الأقل سعرا',
    'price_desc' => 'الأعلى سعرا',
);
?>
<section class="section" id="catalog">
  <div class="shell">

<?php /* ── تصفح بالصف ────────────────────────────────────────────
         TQ-BOOK-GRADES — ومن دون هذا الشريط تصير صفحات الصفوف يتيمة:
         تعمل بروابطها ولا يصلها زائر ولا زاحف، و«صفحة لكل صف» تصير
         عنوانا لا بابا.

         والصفوف كلها تعرض لا التي فيها كتب وحدها — كما في شجرة المنهج:
         الطالب يقرأ سلم صفه كاملا فيعرف اين هو منه، والصف بلا كتب يقال
         بلا عدد ولا يفتح، فلا وعد يخلف. */ ?>
<?php if (!empty($tq_grades)): ?>
    <nav class="bgrades" aria-label="<?php echo te('تصفح الكتب بالصف'); ?>">
      <span class="bgrades__t"><?php echo t('تصفح بالصف'); ?></span>
<?php   foreach ($tq_grades as $tq_g): ?>
<?php     if ((int) $tq_g['n'] > 0): ?>
      <a class="bgrades__i" href="<?php echo html_escape(tqs_grade_books_url($tq_g)); ?>">
        <?php echo html_escape($tq_g['name_ar']); ?>
        <span class="tq-ltr"><?php echo tq_num((int) $tq_g['n']); ?></span>
      </a>
<?php     else: ?>
      <span class="bgrades__i is-off"><?php echo html_escape($tq_g['name_ar']); ?></span>
<?php     endif; ?>
<?php   endforeach; ?>
    </nav>
<?php endif; ?>

    <?php /* شريط الأدوات: البحث والفرز والعد. **خارج** الجزء المستبدل
             لأن استبدال حقل البحث مع كل حرف يفقد المؤشر موضعه.
             و`action` إلى `/books` لا `/catalog`: النموذج بلا جافاسكربت
             يرسل إلى ما في `action`، فبحث من هذه الصفحة كان يهبط في
             الكتالوج العام ويوسع النتيجة إلى الأنواع الأربعة. */ ?>
    <form class="cbar" role="search" method="get" action="<?php echo base_url('books'); ?>"
          data-tq-cat-form>
      <div class="cbar__search">
        <svg aria-hidden="true"><use href="#i-search"></use></svg>
        <label class="sr-only" for="catQ"><?php echo t('ابحث في الكتب'); ?></label>
        <input id="catQ" type="search" name="q" autocomplete="off"
               value="<?php echo html_escape($tq_f['q']); ?>"
               placeholder="<?php echo t('ابحث باسم كتاب أو مادة أو مؤلف…'); ?>" data-tq-cat-q>
        <button class="cbar__clear" type="button" data-tq-cat-clear
                aria-label="<?php echo t('امسح البحث'); ?>"<?php echo ($tq_f['q'] === '') ? ' hidden' : ''; ?>>
          <svg aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
      </div>

      <div class="cbar__sort">
        <label class="sr-only" for="catSort"><?php echo t('ترتيب النتائج'); ?></label>
        <select id="catSort" name="sort" data-tq-cat-sort>
<?php foreach ($tq_sorts as $tq_v => $tq_l): ?>
          <option value="<?php echo html_escape($tq_v); ?>"<?php
              echo ($tq_f['sort'] === $tq_v) ? ' selected' : ''; ?>><?php echo t($tq_l); ?></option>
<?php endforeach; ?>
        </select>
        <svg aria-hidden="true"><use href="#i-chevron"></use></svg>
      </div>

      <button class="btn btn--primary cbar__go" type="submit" data-tq-cat-go><?php echo t('ابحث'); ?></button>

      <?php /* النوع مثبت هنا مع بقية المرشحات: من أرسل النموذج بلا
               جافاسكربت كان يفقده، فيقرأ نتيجة فيها برامج وباقات في
               صفحة عنوانها «الكتب». */ ?>
      <div hidden data-tq-cat-hidden>
        <input type="hidden" name="type" value="book">
<?php foreach (array('cat', 'grade', 'subject', 'teacher') as $tq_k): ?>
<?php   if ($tq_f[$tq_k]): ?>
        <input type="hidden" name="<?php echo $tq_k; ?>" value="<?php echo html_escape(implode(',', $tq_f[$tq_k])); ?>">
<?php   endif; ?>
<?php endforeach; ?>
<?php if ($tq_f['price'] !== ''): ?>
        <input type="hidden" name="price" value="<?php echo html_escape($tq_f['price']); ?>">
<?php endif; ?>
      </div>
    </form>

    <div class="cwrap">

      <button class="cwrap__toggle" type="button" data-tq-cat-rail
              aria-expanded="false" aria-controls="catRail">
        <svg aria-hidden="true"><use href="#i-filter"></use></svg>
        <span><?php echo t('المرشحات'); ?></span>
<?php if ($tq_res['active']): ?>
        <b class="tq-ltr"><?php echo count($tq_res['active']); ?></b>
<?php endif; ?>
      </button>

      <aside class="crail" id="catRail" data-tq-cat-rail-box>
<?php echo $this->load->view('frontend/taqdar/site/site_catalog_filters',
        array('tq_f' => $tq_f, 'tq_res' => $tq_res), true); ?>
      </aside>

      <div class="cmain">
        <p class="ccount" id="catCount" role="status" aria-live="polite">
          <?php echo tqs_cat_count_line($tq_res); ?>
        </p>

        <?php /* جزء النتائج يجلب من `books/results` لا من `catalog/results`:
                 المصدر واحد والنوع مثبت فيه، فبحث حي من هذه الصفحة لا
                 يعود بنتيجة فيها برامج. */ ?>
        <div id="catGrid" data-tq-cat-grid="<?php echo html_escape(base_url('books/results')); ?>">
<?php echo $this->load->view('frontend/taqdar/site/site_catalog_grid',
        array('tq_f' => $tq_f, 'tq_res' => $tq_res), true); ?>
        </div>
      </div>

    </div>
  </div>
</section>

<?php /* نداء الختام: الكتاب يقرأ، والبرنامج يشرحه ويقيسه. ومن فتح صفحة
        الكتب جاء يبحث عن مادة يقرؤها — فالوصلة إلى ما بعدها في موضعها. */ ?>
<section class="section">
  <div class="shell">
    <div class="cta on-dark">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2><?php echo t('الكتاب بداية.. والباقة هي الرحلة'); ?></h2>
        <p><?php echo t('اشترك في باقة صفك: تفتح كتب المرحلة كلها، ومعها البرامج والدروس والاختبارات.'); ?></p>
      </div>
      <div class="cta__act">
        <a class="btn btn--gold" href="<?php echo base_url('plans'); ?>"><?php echo t('تصفح الباقات'); ?></a>
        <a class="btn btn--ghost-light" href="<?php echo base_url('catalog'); ?>"><?php echo t('كل المواد والبرامج'); ?></a>
      </div>
    </div>
  </div>
</section>
