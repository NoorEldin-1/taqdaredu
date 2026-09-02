<?php
/**
 * أصول الرأس.
 * الخطوط مضمنة لا مجلوبة وقت التشغيل، وثلاثة ملفات فقط تحمل مبكرا —
 * وهي ما تحتاجه الصفحة الأولى: Cairo للمتن، Tajawal للعناوين، SpaceGrotesk للأرقام.
 */
$tq_fav = get_frontend_settings('favicon') ?: 'favicon.png';
?>
<link rel="icon" href="<?php echo base_url('uploads/system/' . $tq_fav); ?>" type="image/png">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo base_url('uploads/system/' . $tq_fav); ?>">
<link rel="manifest" href="<?php echo base_url('assets/taqdar/manifest.webmanifest'); ?>">


<?php if (!empty($tq_is_site)): /* الصفحة منقولة: أوراق التصميم وحدها.
        وأوراق البوابات لا تحمل معها — لا تكديسا للحرص بل لأن
        `h1 { font: ... }` فيها يستولي على حجم كل عنوان. */ ?>
<?php /* المسبق يطابق ما تطلبه الورقة **حرفا بحرف**.
        `tq_site_asset()` تلحق `?v=` بينما `@font-face` في `taqdar.css`
        تشير إلى الملف بلا معامل — وعنوانان مختلفان يعنيان طلبين، فكان
        كل خط ينزل مرتين. فيكتب المسار خاما هنا. */ ?>
    <link rel="preload" href="<?php echo base_url('assets/taqdar/site/fonts/Cairo-700-arabic.woff2'); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo base_url('assets/taqdar/site/fonts/Plex-400-arabic.woff2'); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?php echo tq_site_asset('css/taqdar.css'); ?>">
    <link rel="stylesheet" href="<?php echo tq_site_asset('css/pages.css'); ?>">
    <link rel="stylesheet" href="<?php echo tq_site_asset('css/shared.css'); ?>">
    <link rel="stylesheet" href="<?php echo tq_site_asset('css/shell.css'); ?>">
<?php /* TQ-P26 · ورقة قسم الباقات — الرئيسية وحدها، وكل قاعدة فيها
        مقيَّدة بـ`body.tq-p26`. حذف هذه الكتلة يعيد القسم إلى شكله السابق. */ ?>
<?php /* TQ-HOME-DARK · كتلة الرئيسية الداكنة (الباقات + لماذا تختار).
        كل قاعدة فيها مقيَّدة بـ`.p26d` أو `.whyd` — اسمان لا وجود لهما
        في ورقة أخرى. وحذف هذا السطر وحده يعيد القسمين إلى شكلهما. */ ?>
<?php /* TQ-HOME-DARK · لوح «لماذا تختار» الداكن — الرئيسية وحدها. */ ?>
<?php if (isset($page_name) && in_array($page_name, array('home', 'home_elegant', 'plans'), true)): ?>
    <link rel="stylesheet" href="<?php echo tq_site_asset('css/home-dark.css'); ?>">
<?php endif; ?>
<?php else: ?>
<?php /* TQ-FONTS-MOVED — خطوط البوابة تسبق للبوابة وحدها.
   كانت خارج الشرط، فتجلب بأولوية عالية على كل صفحة تصميم — وهي
   معلنة حصريا في `css/fonts.css` التي لا تحمل هناك. أي ١٥٧ ك.ب
   تنزل وتزاحم الأصول الحرجة ولا يرسم بها حرف واحد. */ ?>
<link rel="preload" as="font" type="font/woff2" crossorigin
      href="<?php echo tq_asset('site/fonts/Cairo-700-arabic.woff2'); ?>">
<link rel="preload" as="font" type="font/woff2" crossorigin
      href="<?php echo tq_asset('site/fonts/Plex-400-arabic.woff2'); ?>">
<link rel="preload" as="font" type="font/woff2" crossorigin
      href="<?php echo tq_asset('fonts/SpaceGrotesk.woff2'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/fonts.css'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/tokens.css'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/base.css'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/components.css'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/layout.css'); ?>">
<?php /* TQ-DS — طبقة الـDesign System لبوابات الطالب والمعلم وولي الأمر.
         تحمل بعد الرموز والمكونات فتتجاوزها، وكل قواعدها مقيدة بـ
         `body.tq-body--portal` — والصنف لا يوضع إلا على صفحة بوابة
         (`index.php:52`)، فالموقع العام لا يمسه شيء منها.
         وحذف هذا السطر وحده يلغي التصميم عن البوابات الثلاث. */ ?>
<link rel="stylesheet" href="<?php echo tq_asset('css/ds.css'); ?>">
<?php /* المكونات المشتركة — المنهج وبيانات التحويل وشرائط الأرقام. وسمها
        يخرج من مساعد واحد للجهتين، وورقتها تجسر توكناتها إلى توكنات
        البوابة. وهي **بعد** أوراق البوابة عمدا: الجسر يعرف على
        `.tq-body--portal` فلا بد أن يقرأ بعد أن تعرف `:root`. */ ?>
<link rel="stylesheet" href="<?php echo tq_site_asset('css/shared.css'); ?>">

<?php if (!empty($tq_is_portal)): ?>
<?php /* TQ-PORTAL-UI — طبقة ترقية البوابات: نظير `admin.css §٩` و`§١٠`
         للوحة. تحمل **آخر السلسلة** فتتجاوز `shared.css` كذلك، وكل
         قاعدة فيها مقيدة بـ`body.tq-body--portal` — والصنف لا يوضع إلا
         على صفحة بوابة. وحذف هذا السطر وحده يعيد البوابات الثلاث إلى
         ما كانت عليه، بلا قالب يعدل. */ ?>
<?php /* TQA-CONTROLS — المنتق وحقل الملف: الورقة نفسها التي تقرؤها
         اللوحة، لا نسخة منها. `<select>` يرسمه نظام التشغيل و
         `<input type=file>` يقول «No file chosen» بالإنجليزية — وهما
         كذلك في شاشة الطالب كما في شاشة الإدارة، فلوح فرز المفضلة كان
         يعرض منتقيا عاريا بسهم النظام إلى جوار رقاقات مصممة. */ ?>
<link rel="stylesheet" href="<?php echo tq_asset('css/tqa-controls.css'); ?>">
<link rel="stylesheet" href="<?php echo tq_asset('css/portal.css'); ?>">
<?php /* حالة طي الشريط الجانبي تثبت قبل الرسم لا بعد تحميل السكربت.
        لو كتبت في `taqdar.js` (المؤجل بـ`defer`) لرسم الشريط مفتوحا في كل
        صفحة ثم انطوى أمام العين — وميض في كل تنقل يقرأ عطلا لا ذاكرة. */ ?>
<script>
(function () {
    try {
        if (localStorage.getItem('tq-rail') === 'collapsed') {
            document.documentElement.setAttribute('data-tq-rail', 'collapsed');
        }
    } catch (e) {}
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php
/* الوضع الليلي يثبت قبل الرسم حتى لا تومض الصفحة بيضاء ثم تظلم.
   واللون لا يعيش في البيانات — العنصر يحمل دورا والوجه يقرر قيمته. */
?>

<?php echo get_frontend_settings('custom_css') ? '<style>' . get_frontend_settings('custom_css') . '</style>' : ''; ?>
