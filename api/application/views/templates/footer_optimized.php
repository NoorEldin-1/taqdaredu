<!-- Footer content here -->

<?php
// Bundle JavaScript files
$this->load->library('asset_minifier');

$js_files = [
    'assets/js/jquery-3.6.1.min.js',
    'assets/js/bootstrap.bundle.min.js',
    'assets/js/jquery.meanmenu.min.js',
    'assets/js/jquery.nice-select.min.js',
    'assets/js/owl.carousel.min.js',
    'assets/js/slick.min.js',
    'assets/js/venobox.min.js',
    'assets/js/jquery.webui-popover.min.js',
    'assets/js/course.js',
    'assets/js/berli.js',
    'assets/js/script.js'
];

$bundled_js = $this->asset_minifier->bundle_js($js_files, 'main');
?>

<!-- Bundled JavaScript - Deferred -->
<script src="<?= $bundled_js ?>" defer></script>

<!-- Third-party scripts - Lazy loaded after user interaction -->
<script>
    /**
     * Lazy Load Third-Party Scripts
     * Only loads Facebook Pixel and Chat Widget after user interaction or delay
     */
    (function () {
        let scriptsLoaded = false;

        function loadThirdPartyScripts() {
            if (scriptsLoaded) return;
            scriptsLoaded = true;

            // Load Facebook Pixel
            !function (f, b, e, v, n, t, s) {
                if (f.fbq) return; n = f.fbq = function () {
                    n.callMethod ?
                    n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                };
                if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0';
                n.queue = []; t = b.createElement(e); t.async = !0;
                t.src = v; s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s)
            }(window, document, 'script',
                'https://connect.facebook.net/en_US/fbevents.js');

            // Initialize FB Pixel with your ID
            fbq('init', 'YOUR_PIXEL_ID'); // Replace with actual pixel ID
            fbq('track', 'PageView');

            // Load Chat Widget
            var chatScript = document.createElement('script');
            chatScript.src = 'https://app.chaticmedia.com/webchat/plugin.js?v=6';
            chatScript.async = true;
            document.body.appendChild(chatScript);
        }

        // Load on scroll
        window.addEventListener('scroll', loadThirdPartyScripts, { once: true, passive: true });

        // Load on mouse move
        window.addEventListener('mousemove', loadThirdPartyScripts, { once: true, passive: true });

        // Load on touch
        window.addEventListener('touchstart', loadThirdPartyScripts, { once: true, passive: true });

        // Fallback: Load after 5 seconds if no interaction
        setTimeout(loadThirdPartyScripts, 5000);
    })();
</script>

<!-- Replace WOW.js with lightweight IntersectionObserver -->
<script>
    /**
     * Lightweight animation trigger using IntersectionObserver
     * Replaces wow.js and animate.css for better performance
     */
    document.addEventListener('DOMContentLoaded', function () {
        // Only run if IntersectionObserver is supported
        if ('IntersectionObserver' in window) {
            const animatedElements = document.querySelectorAll('.animate-on-scroll, .wow');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animated', 'fadeIn');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });

            animatedElements.forEach(el => observer.observe(el));
        }
    });
</script>

<!-- Fix jQuery reflows by using requestAnimationFrame -->
<script>
    /**
     * Optimize jQuery DOM manipulation to prevent forced reflows
     */
    (function ($) {
        // Cache jQuery collections for better performance
        const $window = $(window);
        const $document = $(document);

        // Debounce scroll/resize handlers
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Use requestAnimationFrame for smooth animations
        window.requestAnimFrame = (function () {
            return window.requestAnimationFrame ||
                window.webkitRequestAnimationFrame ||
                window.mozRequestAnimationFrame ||
                function (callback) {
                    window.setTimeout(callback, 1000 / 60);
                };
        })();

        // Example: Optimize scroll handlers
        $window.on('scroll', debounce(function () {
            requestAnimFrame(function () {
                // Your scroll logic here
                // This prevents layout thrashing
            });
        }, 16)); // ~60fps

    })(jQuery);
</script>

</body>

</html>