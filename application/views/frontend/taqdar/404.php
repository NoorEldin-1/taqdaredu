<?php
/**
 * الصفحة غير الموجودة.
 *
 * تعرض مخارج لا اعتذارًا: من ضلّ الطريق يحتاج بابًا لا أسفًا. وحالة
 * الاستجابة يضبطها `Home::page_not_found()` صراحةً — فالصفحة **٤٠٤ حقًّا**
 * لا صفحةَ خطأ بحالة ٢٠٠ تُخبر محرّكات البحث أن العنوان صحيح.
 */
$tq_h1   = 'لم نجد هذه الصفحة';
$tq_lead = 'قد يكون الرابط قديمًا أو فيه خطأ مطبعيّ.';
include __DIR__ . '/site/site_pagehero.php';
?>
<section class="section">
  <div class="shell shell--narrow">
    <div class="icard err-card" style="text-align:center">
      <p>جرّب أحد هذه:</p>
      <?php /* صندوق بحث: أكثر ما يفعله من وصل إلى ٤٠٤ هو البحث عمّا
              جاء من أجله، وثلاثة أزرارٍ ثابتة لا تُغني عنه. */ ?>
      <form class="sitesearch sitesearch--page" role="search" method="get"
            action="<?php echo base_url('search'); ?>">
        <label class="sr-only" for="e404q">ابحث في المنصّة</label>
        <svg aria-hidden="true"><use href="#i-search"></use></svg>
        <input id="e404q" type="search" name="q" placeholder="ابحث عمّا تريد…" autocomplete="off">
        <button class="btn btn--primary btn--sm" type="submit">بحث</button>
      </form>
      <div class="hero__cta" style="justify-content:center;flex-wrap:wrap">
        <a class="btn btn--primary" href="<?php echo base_url('plans'); ?>">المواد والبرامج</a>
        <a class="btn btn--ghost" href="<?php echo base_url('books'); ?>">كتب المنهج</a>
        <a class="btn btn--ghost" href="<?php echo base_url(); ?>">الصفحة الرئيسية</a>
      </div>
      <p class="tq-caption" style="margin-block-start:1.2rem">
        وإن كنت تظنّ أن الرابط صحيح فـ<a href="<?php echo base_url('contact'); ?>">أخبرنا</a>.
      </p>
    </div>
  </div>
</section>
