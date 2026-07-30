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

<!-- Critical Preloads -->
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/bootstrap.min.css'; ?>" as="style">
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/style.min.css'; ?>" as="style">
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/new-style.css'; ?>" as="style">
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/responsive.css'; ?>" as="style">
<link rel="preload" href="<?php echo base_url('assets/global/js/jquery-3.6.1.min.js'); ?>" as="script">

<!-- Preload Critical FontAwesome Fonts -->
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/webfonts/fa-solid-900.woff2'; ?>"
    as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/webfonts/fa-brands-400.woff2'; ?>"
    as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?php echo base_url() . 'assets/frontend/default-new/css/webfonts/fa-regular-400.woff2'; ?>"
    as="font" type="font/woff2" crossorigin>

<!-- Extracted Google Fonts (Deferred with font-display: swap) -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"
    media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
</noscript>

<link
    href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100;200;300;400;500;600;700;800;900&family=Manrope:wght@200;300;400;500;600;700;800&display=swap"
    rel="stylesheet" media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100;200;300;400;500;600;700;800;900&family=Manrope:wght@200;300;400;500;600;700;800&display=swap">
</noscript>

<link
    href="https://fonts.googleapis.com/css2?family=Mulish:wght@200;300;400;500;600;700;800;900;1000&family=Raleway:wght@100;200;300;400;500;600;700;800;900&family=Secular+One&display=swap"
    rel="stylesheet" media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Mulish:wght@200;300;400;500;600;700;800;900;1000&family=Raleway:wght@100;200;300;400;500;600;700;800;900&family=Secular+One&display=swap">
</noscript>

<!-- Custom Font Stylesheet (Deferred) -->
<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/fonts/custom/stylesheet.css'; ?>"
    media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet"
        href="<?php echo base_url() . 'assets/frontend/default-new/css/fonts/custom/stylesheet.css'; ?>">
</noscript>

<!-- Font Awesome (Deferred) -->
<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/all.min.css'; ?>" media="print"
    onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/all.min.css'; ?>">
</noscript>

<!-- Font Display Fix for Font Awesome - Performance Optimization -->
<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/font-display-fix.css'; ?>">


<?php if ($language_dir == 'rtl'): ?>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/bootstrap.rtl.min.css'; ?>">
<?php else: ?>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/bootstrap.min.css'; ?>">
<?php endif; ?>

<!-- Deferred Libraries -->
<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/jquery.webui-popover.min.css'; ?>"
    media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet"
        href="<?php echo base_url() . 'assets/frontend/default-new/css/jquery.webui-popover.min.css'; ?>">
</noscript>

<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/h-2-carousel.css'; ?>"
    media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/h-2-carousel.css'; ?>">
</noscript>

<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/nice-select.css'; ?>"
    media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/nice-select.css'; ?>">
</noscript>

<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/owl.carousel.min.css'; ?>"
    media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/owl.carousel.min.css'; ?>">
</noscript>

<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/owl.theme.default.min.css'; ?>"
    media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet"
        href="<?php echo base_url() . 'assets/frontend/default-new/css/owl.theme.default.min.css'; ?>">
</noscript>

<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/slick-theme.css'; ?>"
    media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/slick-theme.css'; ?>">
</noscript>

<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/slick.css'; ?>" media="print"
    onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/slick.css'; ?>">
</noscript>

<!-- Critical Theme Styles (Blocking but Preloaded) -->
<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/style.min.css'; ?>">
<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/new-style.css'; ?>">
<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/responsive.css'; ?>">

<!-- Performance Override - Inline for immediate CLS prevention -->
<style>
    /* Layout Shift Prevention */
    img {
        height: auto;
        max-width: 100%;
    }

    img:not([width]):not([height]) {
        aspect-ratio: 16 / 9;
    }

    .courses-card-image img {
        aspect-ratio: 3 / 2;
        object-fit: cover;
    }

    .cate-image img {
        aspect-ratio: 1 / 1;
        object-fit: cover;
    }

    .banner-card-1 img[src$=".svg"] {
        aspect-ratio: 1 / 1;
    }

    .app-store-image {
        aspect-ratio: 10 / 3;
    }

    .bannar-card.Ebaner-card {
        min-height: 80px;
    }

    /* Mobile Performance Optimizations - Hide heavy hero images */
    @media (max-width: 768px) {

        /* Hide 1920x769 hero banner image on mobile - saves 127 KiB */
        .banner-image img[width="1920"],
        .hero-banner img[fetchpriority="high"][width="1920"],
        img[src*="bd97d45d577a3870b66233f261c60ba"] {
            display: none !important;
        }

        /* Adjust hero section for mobile without image */
        .hero-banner,
        .banner-section,
        .banner-image {
            min-height: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Optimize testimonial profile images - saves 24 KiB */
        .ele-testimonial-profile-area .profile img,
        .elegant-testimonial-slide .profile img {
            max-width: 64px !important;
            height: auto;
            object-fit: cover;
        }
    }
</style>

<!-- Deferred Extras -->
<?php
// Conditional loading: Only load Summernote CSS on pages that need it
$summernote_pages = ['course_form', 'lesson_form', 'quiz_questions', 'blog_form', 'page_form'];
$load_summernote = false;

if (isset($page_name) && in_array($page_name, $summernote_pages)) {
    $load_summernote = true;
}

// Also load on admin pages
if (isset($page_name) && strpos($page_name, 'admin') !== false) {
    $load_summernote = true;
}

if ($load_summernote):
    ?>
    <link rel="stylesheet"
        href="<?php echo base_url() . 'assets/frontend/default-new/summernote-0.8.20-dist/summernote-lite.min.css'; ?>"
        media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet"
            href="<?php echo base_url() . 'assets/frontend/default-new/summernote-0.8.20-dist/summernote-lite.min.css'; ?>">
    </noscript>
<?php endif; ?>

<link rel="stylesheet" href="<?php echo base_url() . 'assets/global/tagify/tagify.css'; ?>" media="print"
    onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/global/tagify/tagify.css'; ?>">
</noscript>

<link rel="stylesheet" href="<?php echo base_url() . 'assets/global/toastr/toastr.css' ?>" media="print"
    onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/global/toastr/toastr.css' ?>">
</noscript>

<!-- REMOVED: animate.min.css (70KB) - Replaced with lightweight GPU-accelerated animations -->
<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/lightweight_animations.css'; ?>"
    media="print" onload="this.media='all'">
<noscript>
    <link rel="stylesheet"
        href="<?php echo base_url() . 'assets/frontend/default-new/css/lightweight_animations.css'; ?>">
</noscript>

<link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/custom.css'; ?>" media="print"
    onload="this.media='all'">
<noscript>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/custom.css'; ?>">
</noscript>

<?php if ($language_dir == 'rtl'): ?>
    <link rel="stylesheet" href="<?php echo base_url() . 'assets/frontend/default-new/css/rtl.css'; ?>">
<?php endif; ?>

<script src="<?php echo base_url('assets/global/js/jquery-3.6.1.min.js'); ?>"></script>

<!-- Google Site Verification -->
<meta name="google-site-verification" content="-ZGi7Jx8_Y8_V-uljnyxSIHP5FVlV88Vayqo-xPzaIQ" />