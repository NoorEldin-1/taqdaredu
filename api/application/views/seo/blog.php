<?php
$blogs = isset($blogs) ? $blogs : [];
?>
<nav class="breadcrumbs"><a href="/">Home</a> &rsaquo; Blog</nav>
<h1>Blog</h1>
<p>Articles, tutorials, and insights on telecom, networking, and IT engineering from our team.</p>

<?php if (empty($blogs)): ?>
    <p>No blog posts yet — check back soon.</p>
<?php else: ?>
<div class="grid">
    <?php foreach ($blogs as $b): ?>
        <?php
        $id     = is_array($b) ? ($b['id']    ?? null) : ($b->id    ?? null);
        $title  = is_array($b) ? ($b['title'] ?? '')   : ($b->title ?? '');
        $short  = is_array($b) ? ($b['excerpt'] ?? ($b['short_description'] ?? '')) : ($b->excerpt ?? '');
        $cat    = is_array($b) ? ($b['category'] ?? null) : ($b->category ?? null);
        $catName = is_array($cat) ? ($cat['name'] ?? '') : (is_string($cat) ? $cat : '');
        if (!$id) continue;
        ?>
        <article class="card">
            <h3><a href="/blogs/<?= (int)$id ?>-<?= slugify($title) ?>"><?= htmlspecialchars($title) ?></a></h3>
            <?php if ($catName): ?><p><small><?= htmlspecialchars($catName) ?></small></p><?php endif; ?>
            <p><?= htmlspecialchars(strip_tags((string)$short)) ?></p>
            <p><a href="/blogs/<?= (int)$id ?>-<?= slugify($title) ?>">Read more &rarr;</a></p>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
