<?php
/**
 * SEO base layout — minimal, semantic HTML5 designed for crawlers.
 * Variables expected: $title, $description, $canonical, $og_image,
 *                     $partial (view name), $root (root URL).
 * Optional: $jsonld (raw JSON string).
 */
$title       = isset($title)       ? $title       : 'My-Communication Academy';
$description = isset($description) ? $description : 'Telecom, IT and Networking courses.';
$canonical   = isset($canonical)   ? $canonical   : ($root ?? '');
$og_image    = isset($og_image)    ? $og_image    : '';
// Social title/description: use the admin OG override if set, else fall back to the page title/description.
$og_title       = !empty($og_title)       ? $og_title       : $title;
$og_description = !empty($og_description) ? $og_description : $description;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <?php if (!empty($keywords)): ?><meta name="keywords" content="<?= htmlspecialchars($keywords) ?>">
    <?php endif; ?><link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($og_description) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:site_name" content="My-Communication Academy">
    <?php if ($og_image): ?><meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">
    <?php if (!empty($og_image_w)): ?><meta property="og:image:width" content="<?= (int) $og_image_w ?>">
    <meta property="og:image:height" content="<?= (int) $og_image_h ?>">
    <?php endif; ?><?php if (!empty($og_image_type)): ?><meta property="og:image:type" content="<?= htmlspecialchars($og_image_type) ?>">
    <?php endif; ?><?php endif; ?>

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($og_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($og_description) ?>">
    <?php if ($og_image): ?><meta name="twitter:image" content="<?= htmlspecialchars($og_image) ?>"><?php endif; ?>

    <?php if (!empty($jsonld)): ?>
    <script type="application/ld+json"><?= $jsonld ?></script>
    <?php endif; ?>

    <style>
        body{margin:0;font-family:-apple-system,system-ui,"Segoe UI",Roboto,Arial,sans-serif;color:#0f1f36;line-height:1.5;}
        header,footer{padding:20px 24px;background:#0a1628;color:#fff;}
        header a, footer a{color:#fff;text-decoration:none;margin-right:18px;}
        main{max-width:1100px;margin:0 auto;padding:32px 24px;}
        h1{font-size:32px;margin:0 0 16px;color:#0a1628;}
        h2{font-size:24px;margin:24px 0 12px;color:#0a1628;}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;}
        .card{border:1px solid #e4eaf2;border-radius:8px;padding:18px;background:#fff;}
        .card h3{margin:0 0 8px;font-size:18px;}
        .card a{color:#1976d2;text-decoration:none;}
        .price{color:#1976d2;font-weight:600;}
        .breadcrumbs{font-size:13px;color:#5b6b82;margin-bottom:16px;}
        .breadcrumbs a{color:#1976d2;text-decoration:none;}
    </style>
</head>
<body>

<header>
    <strong>My-Communication Academy</strong>
    <nav style="display:inline-block;margin-left:24px;">
        <a href="/">Home</a>
        <a href="/courses">Courses</a>
        <a href="/about-us">About</a>
        <a href="/become-instructor">Teach</a>
        <a href="/contact-us">Contact</a>
        <a href="/blog">Blog</a>
    </nav>
</header>

<main>
    <?php
    $partialFile = APPPATH . 'views/seo/' . preg_replace('/[^a-z_]/', '', $partial) . '.php';
    if (is_file($partialFile)) {
        $this->load->view('seo/' . $partial, get_defined_vars());
    } else {
        echo '<p>Page content unavailable.</p>';
    }
    ?>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> My-Communication Academy &middot; <a href="/contact-us">Contact</a> &middot; <a href="/about-us">About</a></p>
</footer>

</body>
</html>
