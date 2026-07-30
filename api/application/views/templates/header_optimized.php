<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= isset($page_title) ? $page_title : 'My Company UK' ?></title>

    <!-- Preconnect to critical origins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://connect.facebook.net">

    <!-- Critical CSS (inline for render-blocking prevention) -->
    <style>
        /* Critical Above-the-Fold Styles */
        /* Add your most critical CSS here - layout, fonts, header, hero section */
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        /* Prevent CLS for web fonts */
        @font-face {
            font-family: 'FontAwesome';
            font-display: swap;
        }

        /* Basic layout to prevent shifts */
        img,
        picture {
            max-width: 100%;
            height: auto;
        }
    </style>

    <?php
    // Load Asset Minifier
    $this->load->library('asset_minifier');

    // Bundle critical CSS (render-blocking, but necessary)
    $critical_css = [
        'assets/css/bootstrap.min.css',
        'assets/css/style.css',
        'assets/css/new-style.css',
        'assets/css/responsive.css',
        'assets/css/custom.css'
    ];

    $bundled_css = $this->asset_minifier->bundle_css($critical_css, 'critical');
    ?>

    <!-- Bundled Critical CSS -->
    <link rel="stylesheet" href="<?= $bundled_css ?>">

    <!-- Non-critical CSS - Load asynchronously -->
    <link rel="preload" href="<?= base_url('assets/css/all.min.css') ?>" as="style"
        onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="<?= base_url('assets/css/animate.min.css') ?>" as="style"
        onload="this.onload=null;this.rel='stylesheet'">

    <!-- Fallback for browsers that don't support preload -->
    <noscript>
        <link rel="stylesheet" href="<?= base_url('assets/css/all.min.css') ?>">
        <link rel="stylesheet" href="<?= base_url('assets/css/animate.min.css') ?>">
    </noscript>

    <!-- Small polyfill for CSS preload -->
    <script>
        !function (e) { "use strict"; var t = function (t, n, r) { var o, i = e.document, l = i.createElement("link"); if (n) o = n; else { var a = (i.body || i.getElementsByTagName("head")[0]).childNodes; o = a[a.length - 1] } var d = i.styleSheets; l.rel = "stylesheet", l.href = t, l.media = "only x", function e(t) { if (i.body) return t(); setTimeout(function () { e(t) }) }(function () { o.parentNode.insertBefore(l, n ? o : o.nextSibling) }); var f = function (e) { for (var t = l.href, n = d.length; n--;)if (d[n].href === t) return e(); setTimeout(function () { f(e) }) }; return l.addEventListener && l.addEventListener("load", r), l.onloadcssdefined = f, f(r), l }; "undefined" != typeof exports ? exports.loadCSS = t : e.loadCSS = t }("undefined" != typeof global ? global : this);
    </script>

    <!-- Preload critical fonts -->
    <link rel="preload" href="<?= base_url('assets/webfonts/fa-solid-900.woff2') ?>" as="font" type="font/woff2"
        crossorigin>

</head>

<body>