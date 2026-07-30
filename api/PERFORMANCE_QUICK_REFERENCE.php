/**
* Quick Reference: CodeIgniter Performance Optimization
* ======================================================
*/

// ============================================
// 1. IMAGE OPTIMIZATION
// ============================================

// Load the helper (in controller or autoload)
$this->load->helper('image_optimizer');

// For hero/LCP images (above-the-fold, high priority)
echo lcp_image('assets/image/hero.jpg', 'Hero Image', [
'class' => 'hero-img',
'id' => 'main-hero'
]);

// For regular images with responsive srcset
echo optimized_image('assets/image/product.jpg', 'Product', [
400 => '400w',
800 => '800w',
1200 => '1200w'
], [
'class' => 'product-image',
'sizes' => '(max-width: 768px) 100vw, 400px'
]);

// For simple images (auto WebP, lazy load, dimensions)
echo optimized_image('assets/image/logo.png', 'Logo', [], [
'class' => 'logo'
]);


// ============================================
// 2. ASSET BUNDLING
// ============================================

// In your controller
$this->load->library('asset_minifier');

// Bundle CSS files
$css_files = [
'assets/css/bootstrap.min.css',
'assets/css/style.css',
'assets/css/custom.css'
];
$data['bundled_css'] = $this->asset_minifier->bundle_css($css_files, 'main');

// Bundle JS files
$js_files = [
'assets/js/jquery.min.js',
'assets/js/bootstrap.min.js',
'assets/js/app.js'
];
$data['bundled_js'] = $this->asset_minifier->bundle_js($js_files, 'main');

// In your view
<link rel="stylesheet" href="<?= $bundled_css ?>">
<script src="<?= $bundled_js ?>" defer></script>


// ============================================
// 3. OUTPUT CACHING (Static Pages)
// ============================================

// In controller (for static pages only)
class Home extends CI_Controller {
public function index() {
// Cache for 60 minutes
$this->output->cache(60);

$this->load->view('home');
}

public function about() {
// Cache for 2 hours
$this->output->cache(120);

$this->load->view('about');
}
}


// ============================================
// 4. DATABASE QUERY CACHING
// ============================================

// In model
class Product_model extends CI_Model {
public function get_featured() {
$this->db->cache_on();
$query = $this->db->get('products');
$this->db->cache_off();

return $query->result();
}
}


// ============================================
// 5. NON-BLOCKING CSS
// ============================================

// In header template
<link rel="preload" href="<?= base_url('assets/css/non-critical.css') ?>" as="style"
    onload="this.onload=null;this.rel='stylesheet'">
<noscript>
    <link rel="stylesheet" href="<?= base_url('assets/css/non-critical.css') ?>">
</noscript>


// ============================================
// 6. DEFER THIRD-PARTY SCRIPTS
// ============================================

// Use the facade pattern in footer_optimized.php
// Scripts load only after user interaction or 5s delay


// ============================================
// 7. PRELOAD CRITICAL RESOURCES
// ============================================

// In header
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preload" href="<?= base_url('assets/fonts/main.woff2') ?>" as="font" crossorigin>
<link rel="preload" href="<?= base_url('assets/image/hero.webp') ?>" as="image">


// ============================================
// 8. ANIMATIONS (Replace WOW.js)
// ============================================

// Add class to element
<div class="animate-on-scroll">Content</div>

// IntersectionObserver in footer handles it automatically


// ============================================
// 9. CLEAR CACHES
// ============================================

// Visit: http://localhost/myco_uk/clear_cache
// Or in code:
$this->output->delete_cache();
$this->asset_minifier->clear_cache();
$this->db->cache_delete_all();


// ============================================
// 10. CONFIGURATION
// ============================================

// Enable in config/autoload.php
$autoload['helper'] = ['url', 'image_optimizer'];
$autoload['libraries'] = ['asset_minifier'];

// Enable in config/database.php
$db['default']['cache_on'] = TRUE;
$db['default']['cachedir'] = APPPATH . 'cache/db/';

// Create writable directories
mkdir('application/cache/db', 0755, true);
mkdir('application/cache/output', 0755, true);
mkdir('assets/cache/css', 0755, true);
mkdir('assets/cache/js', 0755, true);


// ============================================
// PERFORMANCE CHECKLIST
// ============================================

/*
✓ Replace header.php with header_optimized.php
✓ Replace footer.php with footer_optimized.php
✓ Replace all <img> tags with optimized_image()
✓ Use lcp_image() for hero/first visible image
✓ Enable output caching for static pages
✓ Bundle CSS/JS files
✓ Enable Gzip in .htaccess
✓ Set browser caching headers
✓ Lazy-load third-party scripts
✓ Remove wow.js and animate.css
✓ Fix jQuery reflow issues
✓ Preload critical fonts and images
✓ Test with Lighthouse in incognito mode
*/