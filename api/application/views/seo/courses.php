<?php
$courses = isset($courses) ? $courses : [];
?>
<nav class="breadcrumbs"><a href="/">Home</a> &rsaquo; Courses</nav>
<h1>All Courses</h1>
<p>
    Browse our catalog of <?= count($courses) ?> telecom, networking, and IT engineering courses.
    Filter by category, level, or price to find what fits your career goals.
</p>

<?php if (empty($courses)): ?>
    <p>No courses are currently available.</p>
<?php else: ?>
<div class="grid">
    <?php foreach ($courses as $c): ?>
        <?php
        $id    = is_array($c) ? ($c['id']    ?? null) : ($c->id    ?? null);
        $title = is_array($c) ? ($c['title'] ?? '')   : ($c->title ?? '');
        $desc  = is_array($c) ? ($c['short_description'] ?? '') : ($c->short_description ?? '');
        $price = is_array($c) ? ($c['price'] ?? 0) : ($c->price ?? 0);
        $isFree= is_array($c) ? ($c['is_free_course'] ?? 0) : ($c->is_free_course ?? 0);
        if (!$id) continue;
        ?>
        <article class="card">
            <h3><a href="/courses/<?= (int)$id ?>-<?= slugify($title) ?>"><?= htmlspecialchars($title) ?></a></h3>
            <p><?= htmlspecialchars(strip_tags($desc)) ?></p>
            <p class="price"><?= $isFree ? 'Free' : ($price > 0 ? '£' . number_format((float)$price, 2) : 'Free') ?></p>
            <p><a href="/courses/<?= (int)$id ?>-<?= slugify($title) ?>">Learn more &rarr;</a></p>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
