<!-- Font Optimization -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- PERFORMANCE: Preconnect to Third-Party Origins (Est. 220ms+ savings) -->
<link rel="preconnect" href="https://app.chaticmedia.com" crossorigin>
<link rel="dns-prefetch" href="https://app.chaticmedia.com">
<link rel="preconnect" href="https://www.googletagmanager.com">
<link rel="dns-prefetch" href="https://www.googletagmanager.com">
<link rel="preconnect" href="https://connect.facebook.net" crossorigin>
<link rel="dns-prefetch" href="https://connect.facebook.net">

<!-- Critical CSS (Inlined) -->
<style>
    <?php
    $critical_css_path = FCPATH . 'assets/frontend/default-new/css/new-style.min.css';
    if (file_exists($critical_css_path)) {
        echo file_get_contents($critical_css_path);
    }
    ?>
    /* Additional Critical Styles */
    body {
        opacity: 1 !important;
    }
</style>

<!-- Preload Bootstrap (Non-blocking) -->
<?php if ($language_dir == 'rtl'): ?>
    <link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/bootstrap.rtl.min.css'; ?>"
        as="style" onload="this.onload=null;this.rel='stylesheet'">
<?php else: ?>
    <link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/bootstrap.min.css'; ?>" as="style"
        onload="this.onload=null;this.rel='stylesheet'">
<?php endif; ?>
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/bootstrap.min.css'; ?>">
</noscript>

<!-- Deferred Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"
    media="print" onload="this.media='all'">

<!-- LCP Hero Image Preload -->
<?php if (isset($page_name) && $page_name == 'home'): ?>
    <?php
    $banner_image = get_frontend_settings('banner_image');
    $optimized_banner = pathinfo($banner_image, PATHINFO_FILENAME) . '_optimized.webp';
    $banner_url = file_exists(FCPATH . 'uploads/system/' . $optimized_banner)
        ? base_url("uploads/system/" . $optimized_banner)
        : base_url("uploads/system/" . $banner_image);
    ?>
    <link rel="preload" as="image" href="<?php echo $banner_url; ?>" fetchpriority="high">
<?php endif; ?>

<!-- Async Load Main Styles -->
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/style.min.css'; ?>" as="style"
    onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/responsive.min.css'; ?>" as="style"
    onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/all.min.css'; ?>" as="style"
    onload="this.onload=null;this.rel='stylesheet'">

<!-- PERFORMANCE: Optimized CSS animations (replaces jQuery slideToggle) -->
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/optimized_animations.css'; ?>"
    as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript>
    <link rel="stylesheet"
        href="<?php echo base_url() . 'assets/frontend/default-new/css/optimized_animations.css'; ?>">
</noscript>

<!-- ACCESSIBILITY: Contrast and focus improvements -->
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/accessibility-contrast.css'; ?>"
    as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript>
    <link rel="stylesheet"
        href="<?php echo base_url() . 'assets/frontend/default-new/css/accessibility-contrast.css'; ?>">
</noscript>

<!-- SEO & Meta -->
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5.0, minimum-scale=0.86">
<?php include 'seo.php'; ?>
<link rel="icon" href="<?php echo base_url('uploads/system/' . get_frontend_settings('favicon')); ?>"
    type="image/x-icon">

<!-- Google Tag Manager (Deferred via JS in Footer, preventing render block here) -->

<!-- Custom CSS (Deferred) -->
<style media="print" onload="this.media='all'">
    <?php echo get_frontend_settings('custom_css'); ?>
</style>