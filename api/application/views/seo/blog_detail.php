<?php
$blog = isset($blog) ? $blog : null;
if (!$blog) { echo '<p>Article not found.</p>'; return; }

$g = function ($k, $d='') use ($blog) { return is_array($blog) ? ($blog[$k] ?? $d) : ($blog->{$k} ?? $d); };
$id    = (int) $g('id', 0);
$title = $g('title', 'Article');
$short = strip_tags((string) ($g('excerpt','') ?: $g('short_description', '')));
$body  = strip_tags((string) ($g('content','') ?: $g('description', '')), '<p><br><h2><h3><h4><strong><em><ul><ol><li><a><blockquote>');
$author = $g('author', []);
$authorName = is_array($author) ? ($author['name'] ?? '') : '';
$created = $g('created_at', '');
$readTime = $g('read_time', '');
$cat = $g('category', null);
$catName = is_array($cat) ? ($cat['name'] ?? '') : (is_string($cat) ? $cat : '');
?>

<nav class="breadcrumbs">
    <a href="/">Home</a> &rsaquo; <a href="/blogs">Blog</a> &rsaquo; <?= htmlspecialchars($title) ?>
</nav>

<article>
    <h1><?= htmlspecialchars($title) ?></h1>
    <p><small>
        <?php if ($catName): ?><?= htmlspecialchars($catName) ?> &middot; <?php endif; ?>
        <?php if ($authorName): ?>By <?= htmlspecialchars($authorName) ?> &middot; <?php endif; ?>
        <?php if ($created): ?><?= htmlspecialchars(date('F j, Y', strtotime($created))) ?> &middot; <?php endif; ?>
        <?php if ($readTime): ?><?= htmlspecialchars($readTime) ?><?php endif; ?>
    </small></p>
    <?php if ($short): ?><p><strong><?= htmlspecialchars($short) ?></strong></p><?php endif; ?>
    <?php if ($body): ?><div><?= $body ?></div><?php endif; ?>
</article>

<h2>More articles</h2>
<p><a href="/blogs">View the full blog</a> or <a href="/courses">browse our courses</a>.</p>
