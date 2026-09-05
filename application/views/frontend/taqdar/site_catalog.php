<?php
/**
 * المواد والبرامج التعليمية — الكتالوج الموحد.
 *
 * ═══ لماذا صفحة واحدة ═══
 *
 * كان المحتوى موزعا على بابين: البرامج تحت `/plans`، والكتب تحت
 * `/books`. ومن يبحث عن «رياضيات الصف الرابع» لا يعرف أي باب يطرق،
 * ولا مرشح في باب يشبه مرشح الآخر: هنا أزرار فئات، وهناك تبويب مراحل.
 *
 * وهذه الصفحة تسأل سؤالا واحدا — **ماذا تبحث عنه؟** — وتجيب من مصدر
 * واحد (`Taqdar_catalog_model`) بأربعة أنواع في شبكة واحدة.
 *
 * ═══ أين يعيش الحال ═══
 *
 * في الرابط وحده. المرشحات والبحث ورقم الصفحة معاملات `GET`، فالنتيجة
 * تشارك وتحفظ وترجع بزر «رجوع». والسكربت لا يحتفظ بحال ثانية: يقرأ من
 * الرابط، ويكتب فيه، ويستبدل ما يرده الخادم. فلا يقع أن يقول المرشح
 * شيئا وتقول الشبكة غيره.
 */
$tq_h1   = tq_text_raw('site_catalog', 'hero_title', 'المواد والبرامج التعليمية');
$tq_lead = tq_text_raw('site_catalog', 'hero_lede',
    'كل ما تقدمه تقدر في مكان واحد: باقات المراحل وبرامج المواد وكتب المنهج — ابحث ورشح حتى تصل إلى ما يخص صفك.');
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

    <?php /* شريط الأدوات: البحث والفرز والعد. **خارج** الجزء المستبدل
             لأن استبدال حقل البحث مع كل حرف يفقد المؤشر موضعه.
             و`GET` لا `POST`: نتيجة تشارك برابطها ولا تغير شيئا. */ ?>
    <form class="cbar" role="search" method="get" action="<?php echo base_url('catalog'); ?>"
          data-tq-cat-form>
      <div class="cbar__search">
        <svg aria-hidden="true"><use href="#i-search"></use></svg>
        <label class="sr-only" for="catQ">ابحث في المواد والبرامج</label>
        <input id="catQ" type="search" name="q" autocomplete="off"
               value="<?php echo html_escape($tq_f['q']); ?>"
               placeholder="ابحث باسم مادة أو برنامج أو كتاب…" data-tq-cat-q>
        <button class="cbar__clear" type="button" data-tq-cat-clear
                aria-label="امسح البحث"<?php echo ($tq_f['q'] === '') ? ' hidden' : ''; ?>>
          <svg aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
      </div>

      <div class="cbar__sort">
        <label class="sr-only" for="catSort">ترتيب النتائج</label>
        <select id="catSort" name="sort" data-tq-cat-sort>
<?php foreach ($tq_sorts as $tq_v => $tq_l): ?>
          <option value="<?php echo html_escape($tq_v); ?>"<?php
              echo ($tq_f['sort'] === $tq_v) ? ' selected' : ''; ?>><?php echo html_escape($tq_l); ?></option>
<?php endforeach; ?>
        </select>
        <svg aria-hidden="true"><use href="#i-chevron"></use></svg>
      </div>

      <?php /* بلا سكربت يبقى زر يرسل النموذج؛ ومعه يخفى فالبحث يجري
               وقت الكتابة. `hidden` يضاف من `site.js` لا هنا. */ ?>
      <button class="btn btn--primary cbar__go" type="submit" data-tq-cat-go>ابحث</button>

      <?php /* المرشحات القائمة تسافر مع البحث بلا سكربت: بدونها يمسح
               الإرسال كل ما اختير قبله. */ ?>
      <div hidden data-tq-cat-hidden>
<?php foreach (array('type', 'cat', 'grade', 'subject', 'teacher') as $tq_k): ?>
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

      <?php /* على الجوال تطوى اللوحة خلف زر: ثلاثة مرشحات مفتوحة فوق
               الشبكة تعني تمرير شاشتين قبل أول بطاقة. */ ?>
      <button class="cwrap__toggle" type="button" data-tq-cat-rail
              aria-expanded="false" aria-controls="catRail">
        <?php /* «المرشحات» لا «تصفية النتائج»: عنوان اللوحة التي يفتحها
                 هو «تصفية النتائج»، وتكرار العبارة زرا وعنوانا تحته
                 يقرأ صدى لا مستويين. */ ?>
        <svg aria-hidden="true"><use href="#i-filter"></use></svg>
        <span>المرشحات</span>
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

        <?php /* عنوان جزء النتائج يكتب هنا لا في السكربت: `base_url()`
                 يقرأ من الإعدادات، واشتقاقه في الجافاسكربت من
                 `location.pathname` يخطئ متى ركب الموقع في مجلد. */ ?>
        <div id="catGrid" data-tq-cat-grid="<?php echo html_escape(base_url('catalog/results')); ?>">
<?php echo $this->load->view('frontend/taqdar/site/site_catalog_grid',
        array('tq_f' => $tq_f, 'tq_res' => $tq_res), true); ?>
        </div>
      </div>

    </div>
  </div>
</section>

<?php /* نداء الختام: من تصفح ولم يجد ما يفتح له المحتوى يقاد إلى
        الباقات — وهي الباب الوحيد الذي يفتح البرامج. */ ?>
<section class="section">
  <div class="shell">
    <div class="cta on-dark">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="cta__copy">
        <h2>الكتاب بداية.. والباقة هي الرحلة</h2>
        <p>اشترك في باقة صفك وافتح مواد المرحلة كاملة بدروسها واختباراتها</p>
        <a class="btn btn--gold" href="<?php echo base_url('plans'); ?>">شاهد الباقات</a>
      </div>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/cta-kids-cut.webp'); ?>" width="660" height="990" loading="lazy"
             decoding="async" alt="طفلان سعوديان يبتسمان ويحملان جهازا لوحيا">
      </div>
    </div>
  </div>
</section>
