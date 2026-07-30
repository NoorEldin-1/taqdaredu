<!-- 
    EXAMPLE: Home Page Implementation
    This shows how to use the optimized components
-->

<?php
// Load helpers
$this->load->helper('image_optimizer');

// Set page title
$data['page_title'] = 'Home - My Company UK';
?>

<?php $this->load->view('templates/header_optimized', $data); ?>

<!-- Hero Section - LCP Image -->
<section class="hero-section">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1>Welcome to My Company UK</h1>

                <!-- 
                    CRITICAL: Use lcp_image() for above-the-fold hero images
                    This sets fetchpriority="high" and loading="eager"
                -->
                <?= lcp_image('assets/image/image_1.png', 'Hero Banner', [
                    'class' => 'hero-image img-fluid',
                    'id' => 'main-hero'
                ]) ?>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="row">

            <!-- Feature 1 -->
            <div class="col-md-4">
                <div class="feature-box animate-on-scroll">
                    <!-- 
                        Use optimized_image() with responsive srcset
                        These will be lazy-loaded automatically
                    -->
                    <?= optimized_image('assets/system/bd97d45_optimized.webp', 'Feature 1', [
                        400 => '400w',
                        800 => '800w'
                    ], [
                        'class' => 'feature-icon',
                        'sizes' => '(max-width: 768px) 100vw, 400px'
                    ]) ?>

                    <h3>Feature One</h3>
                    <p>Description of feature one</p>
                </div>
            </div>

            <!-- Feature 2 -->
            <div class="col-md-4">
                <div class="feature-box animate-on-scroll">
                    <?= optimized_image('assets/optimized/450042c.webp', 'Feature 2', [
                        220 => '220w',
                        440 => '440w'
                    ], [
                        'class' => 'feature-icon'
                    ]) ?>

                    <h3>Feature Two</h3>
                    <p>Description of feature two</p>
                </div>
            </div>

            <!-- Feature 3 -->
            <div class="col-md-4">
                <div class="feature-box animate-on-scroll">
                    <?= optimized_image('assets/optimized/733701a.webp', 'Feature 3', [
                        164 => '164w',
                        328 => '328w'
                    ], [
                        'class' => 'feature-icon'
                    ]) ?>

                    <h3>Feature Three</h3>
                    <p>Description of feature three</p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Large Image Section - The problematic 922KB image -->
<section class="showcase-section">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h2>Our Showcase</h2>

                <!-- 
                    CRITICAL FIX: image_2.png is 922KB!
                    Using responsive srcset will dramatically reduce payload
                -->
                <?= optimized_image('assets/image/image_2.png', 'Showcase Image', [
                    400 => '400w',
                    800 => '800w',
                    1200 => '1200w',
                    1920 => '1920w'
                ], [
                    'class' => 'showcase-image img-fluid',
                    'sizes' => '(max-width: 768px) 100vw, (max-width: 1200px) 80vw, 1200px'
                ]) ?>
            </div>
        </div>
    </div>
</section>

<!-- App Download Section -->
<section class="download-section">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h2>Download Our App</h2>
                <p>Available on iOS and Android</p>

                <div class="app-buttons">
                    <?= optimized_image('assets/image/app-store_optimized.webp', 'App Store', [], [
                        'class' => 'app-store-image'
                    ]) ?>

                    <?= optimized_image('assets/image/google-play_optimized.webp', 'Google Play', [], [
                        'class' => 'app-store-image'
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php $this->load->view('templates/footer_optimized'); ?>

<!-- 
    PERFORMANCE NOTES:
    
    1. LCP Image (image_1.png):
       - Used lcp_image() with fetchpriority="high"
       - No lazy loading (loading="eager")
       - Will be preloaded in header
    
    2. Below-the-fold images:
       - Used optimized_image() with responsive srcset
       - Automatically lazy-loaded
       - WebP conversion happens on first load, cached thereafter
    
    3. Animations:
       - Added "animate-on-scroll" class
       - Footer's IntersectionObserver handles it (no wow.js needed)
    
    4. Third-party scripts:
       - Facebook Pixel and Chat Widget are lazy-loaded
       - Won't block main thread on initial page load
    
    5. Expected improvements:
       - LCP: 18.0s → ~3-4s
       - FCP: 8.6s → ~2-3s
       - Page Size: 4.2MB → ~1.5MB
       - Performance Score: 27 → 70-80+
-->