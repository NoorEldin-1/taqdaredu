<?php
$course = isset($course) ? $course : null;
if (!$course) { echo '<p>Course not found.</p>'; return; }

$g = function ($key, $default = '') use ($course) {
    return is_array($course) ? ($course[$key] ?? $default) : ($course->{$key} ?? $default);
};

$id           = (int) $g('id', 0);
$title        = $g('title', 'Course');
$short        = strip_tags((string) $g('short_description', ''));
$description  = strip_tags((string) $g('description', ''), '<p><br><h2><h3><h4><strong><em><ul><ol><li>');
$price        = (float) $g('price', 0);
$isFree       = (int) $g('is_free_course', 0);
$status       = $g('status', 'active');
$duration     = $g('duration', '');
$totalEnrol   = (int) ($g('enrollment_count', 0) ?: $g('total_students', 0));
$rating       = $g('rating', null);
$cat          = $g('category', null);
$catName      = is_array($cat) ? ($cat['name'] ?? '') : (is_string($cat) ? $cat : '');
$instructor   = $g('instructor', null);
$instName     = is_array($instructor) ? ($instructor['name'] ?? '') : '';
$instId       = is_array($instructor) ? ($instructor['id'] ?? null) : null;
$instBio      = is_array($instructor) ? strip_tags((string)($instructor['biography'] ?? '')) : '';
$sections     = $g('sections', []);
$outcomes     = $g('outcomes', []);
$requirements = $g('requirements', []);
?>

<nav class="breadcrumbs">
    <a href="/">Home</a> &rsaquo; <a href="/courses">Courses</a> &rsaquo; <?= htmlspecialchars($title) ?>
</nav>

<h1><?= htmlspecialchars($title) ?></h1>
<p><small>
    <?php if ($catName): ?><?= htmlspecialchars($catName) ?> &middot; <?php endif; ?>
    <?php if ($duration): ?><?= htmlspecialchars($duration) ?> &middot; <?php endif; ?>
    <?php if ($status === 'upcoming'): ?>Coming Soon<?php endif; ?>
</small></p>

<?php if ($short): ?>
    <p><strong><?= htmlspecialchars($short) ?></strong></p>
<?php endif; ?>

<?php if ($description): ?>
    <h2>About this course</h2>
    <div><?= $description ?></div>
<?php endif; ?>

<?php if (!empty($outcomes) && is_array($outcomes)): ?>
    <h2>What you will learn</h2>
    <ul><?php foreach ($outcomes as $o): ?><li><?= htmlspecialchars(strip_tags((string)$o)) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<?php if (!empty($requirements) && is_array($requirements)): ?>
    <h2>Requirements</h2>
    <ul><?php foreach ($requirements as $r): ?><li><?= htmlspecialchars(strip_tags((string)$r)) ?></li><?php endforeach; ?></ul>
<?php endif; ?>

<?php if (!empty($sections) && is_array($sections)): ?>
    <h2>Course curriculum</h2>
    <?php foreach ($sections as $s):
        $sTitle = is_array($s) ? ($s['title'] ?? '') : ($s->title ?? '');
        $lessons = is_array($s) ? ($s['lessons'] ?? []) : ($s->lessons ?? []);
        if (!$sTitle) continue; ?>
        <h3><?= htmlspecialchars($sTitle) ?></h3>
        <?php if (!empty($lessons) && is_array($lessons)): ?>
        <ul>
            <?php foreach ($lessons as $l): $lt = is_array($l) ? ($l['title'] ?? '') : ($l->title ?? ''); if(!$lt) continue; ?>
                <li><?= htmlspecialchars($lt) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Pricing</h2>
<p class="price">
    <?php if ($status === 'upcoming'): ?>Coming Soon
    <?php elseif ($isFree || $price <= 0): ?>Free
    <?php else: ?>£<?= number_format($price, 2) ?><?php endif; ?>
</p>

<?php if ($rating !== null && $rating !== '' && (float)$rating > 0): ?>
    <h2>Student rating</h2>
    <p><?= htmlspecialchars((string) $rating) ?> &middot; <?= $totalEnrol ?> students enrolled</p>
<?php endif; ?>

<?php if ($instName): ?>
    <h2>Instructor</h2>
    <p>
        <?php if ($instId): ?><a href="/instructors/<?= (int)$instId ?>"><?= htmlspecialchars($instName) ?></a><?php else: ?><?= htmlspecialchars($instName) ?><?php endif; ?>
    </p>
    <?php if ($instBio): ?><p><?= htmlspecialchars(character_limiter($instBio, 400)) ?></p><?php endif; ?>
<?php endif; ?>

<h2>Related</h2>
<p>Explore <a href="/courses">our full catalog of courses</a>, or learn more <a href="/about-us">about our academy</a>.</p>
