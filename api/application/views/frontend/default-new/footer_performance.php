<!-- Footer Performance Scripts -->

<!-- Load jQuery & Bootstrap (Deferred) -->
<script src="<?php echo base_url('assets/global/js/jquery-3.6.1.min.js'); ?>" defer></script>
<script src="<?php echo base_url('assets/frontend/default-new/js/bootstrap.bundle.min.js'); ?>" defer></script>

<!-- Core App Scripts (Defer) -->
<script src="<?php echo base_url('assets/frontend/default-new/js/script.min.js'); ?>" defer></script>
<script src="<?php echo base_url('assets/frontend/default-new/js/jquery.meanmenu.min.js'); ?>" defer></script>
<script src="<?php echo base_url('assets/frontend/default-new/js/jquery.nice-select.min.js'); ?>" defer></script>
<script src="<?php echo base_url('assets/frontend/default-new/js/slick.min.js'); ?>" defer></script>
<script src="<?php echo base_url('assets/frontend/default-new/js/owl.carousel.min.js'); ?>" defer></script>

<!-- Interaction-Based Loading (The "Magic" Script) -->
<script>
    // Wait for user interaction to load heavy third-party scripts
    function loadHeavyScripts() {
        if (window.heavyScriptsLoaded) return;
        window.heavyScriptsLoaded = true;

        console.log('User interaction detected. Loading heavy scripts...');

        // 1. Facebook Pixel
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
        fbq('init', 'YOUR_PIXEL_ID'); // Replace with dynamic ID if needed
        fbq('track', 'PageView');

        // 2. Chat Widget - Handled by deferred_chat.js (interaction-based loading)
        // REMOVED: Duplicate chat widget loading eliminated to prevent redundant requests

        // 3. Google Tag Manager (if needed here instead of head)
        (function (w, d, s, l, i) {
            w[l] = w[l] || []; w[l].push({
                'gtm.start':
                    new Date().getTime(), event: 'gtm.js'
            }); var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; j.async = true; j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl; f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-M4M87L2R'); // ID from index.php
    }

    // Trigger on mousemove, scroll, touchstart, keydown
    const events = ['mousemove', 'scroll', 'touchstart', 'keydown'];
    events.forEach(event => {
        window.addEventListener(event, loadHeavyScripts, { once: true, passive: true });
    });

    // Fallback: Load after 10 seconds anyway if no interaction
    setTimeout(loadHeavyScripts, 10000);
</script>

<!-- Toastr & Form (Defer) -->
<script src="<?php echo base_url('assets/global/toastr/toastr.min.js'); ?>" defer></script>
<script src="<?php echo base_url('assets/global/jquery-form/jquery.form.min.js'); ?>" defer></script>

<!-- Flash Messages -->
<?php if ($this->session->flashdata('flash_message') != ""): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (typeof toastr !== 'undefined') toastr.success('<?php echo $this->session->flashdata("flash_message"); ?>');
        });
    </script>
<?php endif; ?>