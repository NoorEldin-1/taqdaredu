<?php
/**
 * أصول الرأس.
 * الخطوط مُضمَّنة لا مجلوبة وقت التشغيل، وثلاثة ملفّات فقط تُحمَّل مبكرًا —
 * وهي ما تحتاجه الصفحة الأولى: Cairo للمتن، Tajawal للعناوين، SpaceGrotesk للأرقام.
 */
$tq_fav = get_frontend_settings('favicon') ?: 'favicon.png';
?>
<link rel="icon" href="<?php echo base_url('uploads/system/' . $tq_fav); ?>" type="image/png">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo base_url('uploads/system/' . $tq_fav); ?>">
<link rel="manifest" href="<?php echo base_url('assets/taqdar/manifest.webmanifest'); ?>">


<?php if (!empty($tq_is_site)): /* الصفحة منقولة: أوراق التصميم وحدها.
        وأوراق البوّابات لا تُحمَّل معها — لا تكديسًا للحرص بل لأن
        `h1 { font: ... }` فيها يستولي على حجم كل عنوان. */ ?>
<?php /* المسبَّق يطابق ما تطلبه الورقة **حرفًا بحرف**.
        `tq_site_asset()` تُلحق `?v=` بينما `@font-face` في `taqdar.css`
        تشير إلى الملفّ بلا معامل — وعنوانان مختلفان يعنيان طلبين، فكان
        كلّ خطّ يُنزَّل مرّتين. فيُكتب المسار خامًا هنا. */ ?>
    <link rel="preload" href="<?php echo base_url('assets/taqdar/site/fonts/Cairo-700-arabic.woff2'); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo base_url('assets/taqdar/site/fonts/Plex-400-arabic.woff2'); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?php echo tq_site_asset('css/taqdar.css'); ?>">
    <link rel="stylesheet" href="<?php echo tq_site_asset('css/pages.css'); ?>">
    <link rel="stylesheet" href="<?php echo tq_site_asset('css/shell.css'); ?>">
<?php else: ?>
<?php /* TQ-FONTS-MOVED — خطوط البوّابة تُسبَّق للبوّابة وحدها.
   كانت خارج الشرط، فتُجلب بأولوية عالية على كل صفحة تصميم — وهي
   مُعلَنة حصريًّا في `css/fonts.css` التي لا تُحمَّل هناك. أي ١٥٧ ك.ب
   تُنزَّل وتزاحم الأصول الحرجة ولا يُرسم بها حرف واحد. */ ?>
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
<?php endif; ?>

<?php
/* الوضع الليلي يُثبَّت قبل الرسم حتى لا تومض الصفحة بيضاء ثم تُظلم.
   واللون لا يعيش في البيانات — العنصر يحمل دورًا والوجه يقرّر قيمته. */
?>

<?php echo get_frontend_settings('custom_css') ? '<style>' . get_frontend_settings('custom_css') . '</style>' : ''; ?>
