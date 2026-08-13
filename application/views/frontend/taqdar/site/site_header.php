<?php
/**
 * ترويسة الواجهة العامة — تصميم تقدر.
 *
 * يدرج من `index.php` **للصفحات العامة وحدها** (البوابات تفتح غلافها
 * بـ`portal_open.php`)، فهذا الملف موضع آمن لمكتبة الأشكال وطبقات الخلفية:
 * لا تحمل في شاشات البوابات ولا تزيد وزنها.
 *
 * ومكتبة الأشكال **مضمنة لا خارجية**: `<use href="#i-x">` يشترط الرمز في
 * المستند نفسه، والملف الخارجي يوفر كيلوبايتين مضغوطين مقابل مخاطرة عرض
 * حقيقية في متصفحات لا تتبع المرجع الخارجي.
 */
/* الخريطة في `tqs_nav_key()` — كانت مكررة هنا وهناك،
   فأي صفحة جديدة تضاف في موضع وتنسى في الآخر. */
$tq_active = tqs_nav_key($page_name ?? '');

/* «لوحتي» تقود إلى **بوابة صاحبها**. كانت مكتوبة `student` حرفيا في
   ترويسة يقرؤها الأدوار الأربعة: فالمعلم الذي يتصفح صفحة دورة ثم يضغطها
   يمر بـ`tq_guard` فيرد إلى لوحته برسالة «هذه الصفحة تخص بوابة الطالب»
   — وهو لم يطلب بوابة أحد، إنما ضغط زرا في ترويسته هو. */
$tq_dash = function_exists('tq_home_for') ? tq_home_for(tq_role()) : base_url('student');

/**
 * صنف الهيدر: الرئيسية شفافة فوق الهيرو، وما عداها صلبة.
 *
 * TQ-HEADER-ODDBALL — كان هنا فرع ثالث:
 *
 *     if ($tq_active === 'contact') $tq_hdr_class = 'dark';
 *
 * صفحة واحدة من إحدى عشرة تقلب الترويسة كاملة — أرضية بترولية داكنة،
 * وشعار آخر (`brand__light`)، وروابط بيضاء، وأزرار ذهبية. وتعليق الورقة
 * يسمي ذلك «أقوى تمييز بين الصفحات».
 *
 * وهو ليس تمييزا بل انقطاعا: من يتصفح «من نحن» ثم يضغط «تواصل معنا»
 * يرى الترويسة تنقلب تحت يده، فيظن أنه غادر الموقع. والتمييز بين
 * الصفحات وظيفة **الهيرو** — وهو مختلف فيها أصلا — لا وظيفة الترويسة:
 * الترويسة هي الثابت الذي يعرف به الزائر أنه ما زال في المكان نفسه.
 *
 * فصارت `solid` كأخواتها. وحذفت معها كتلتا `.site-header--dark` من
 * `taqdar.css` و`pages.css` — لا صفحة تطلبها بعد اليوم.
 *
 * TQ-HEADER-TRANSPARENT — والشرط كان يقرأ `$tq_active` (مفتاح بند
 * القائمة) لا اسم الصفحة. و`tqs_nav_key()` ترد `''` لكل صفحة لا بند
 * لها في القائمة: **سياسة الخصوصية، والشروط، والاسترجاع، والأسئلة
 * الشائعة، والبحث**. فكانت الخمس تحسب «الرئيسية» وتأخذ ترويسة شفافة
 * فوق محتوى يبدأ من أعلى الصفحة — روابط بيضاء على نص أسود حتى يمرر
 * الزائر فتصمت الترويسة. والشفافية للرئيسية وحدها لأنها وحدها تحتها
 * هيرو مصمم لها. فالشرط يقرأ الآن اسم الصفحة نفسه.
 */
$tq_page      = (string) ($page_name ?? '');
$tq_hdr_class = in_array($tq_page, array('home', 'home_elegant'), true) ? 'plain' : 'solid';
?>
<?php include __DIR__ . '/site_sprite.php'; ?>

<!-- طبقات الخلفية: ورق · حروفيات عربية · لوحة خطية · هالات -->
<div class="bg-layers" aria-hidden="true">
  <span class="bg-paper"></span>
  <span class="bg-script"></span>
  <span class="bg-column"></span>
  <span class="bg-glow bg-glow--a"></span>
  <span class="bg-glow bg-glow--b"></span>
  <span class="bg-glow bg-glow--c"></span>
</div>

<header class="site-header site-header--<?php echo $tq_hdr_class; ?>" id="header">
  <div class="header-main">
    <div class="shell">
      <a class="brand" href="<?php echo base_url(); ?>" aria-label="منصة تقدر — الصفحة الرئيسية">
        <img src="<?php echo tq_site_asset('img/logo.webp'); ?>" alt="منصة تقدر" width="280" height="157">
        <img src="<?php echo tq_site_asset('img/logo-light.webp'); ?>" alt="" width="280" height="163" class="brand__light" aria-hidden="true">
      </a>

      <?php /* البحث في الترويسة — على الجوال وحده (تخفيه الورقة فوق ٩٨٠px).
              الشاشة الضيقة تخفي القائمة خلف زر، فالبحث فيها أقصر طريق
              إلى مادة أو مقال. و`GET` لا `POST`: نتيجة تشارك برابطها
              وتحفظ في المفضلة، ولا شيء يتغير في الخادم. */ ?>
      <form class="sitesearch header-search" role="search" method="get"
            action="<?php echo base_url('search'); ?>">
        <label class="sr-only" for="hdrQ">ابحث في المنصة</label>
        <svg aria-hidden="true"><use href="#i-search"></use></svg>
        <input id="hdrQ" type="search" name="q" placeholder="ابحث…" autocomplete="off">
      </form>

      <nav class="nav" id="nav" aria-label="التنقل الرئيسي">
<?php echo tqs_nav($tq_active); ?>

        <?php /* الدخول والتسجيل داخل القائمة: شريط الترويسة الضيق يخفيهما
                 على الجوال، فلو لم يكونا هنا لما بقي للزائر باب يدخل منه. */ ?>
        <span class="nav__sep" aria-hidden="true"></span>
<?php if ((int) $this->session->userdata('user_id') > 0): ?>
        <a class="nav__auth nav__auth--primary" href="<?php echo $tq_dash; ?>">لوحتي</a>
<?php else: ?>
        <a class="nav__auth" href="<?php echo base_url('login'); ?>">تسجيل الدخول</a>
        <a class="nav__auth nav__auth--primary" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب</a>
<?php endif; ?>
      </nav>

      <div class="header-actions">
<?php if ((int) $this->session->userdata('user_id') > 0): ?>
        <a class="btn btn--primary btn--sm" href="<?php echo $tq_dash; ?>">لوحتي</a>
<?php else: ?>
        <a class="btn btn--ghost" href="<?php echo base_url('login'); ?>">تسجيل الدخول</a>
        <a class="btn btn--primary btn--sm" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب</a>
<?php endif; ?>
        <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="nav"
                aria-label="فتح القائمة">
          <svg aria-hidden="true"><use href="#i-menu"></use></svg>
        </button>
      </div>
    </div>
  </div>
</header>
