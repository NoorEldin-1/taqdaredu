<?php
$instructor = isset($instructor) ? $instructor : null;
if (!$instructor) { echo '<p>Instructor not found.</p>'; return; }
$g = function ($k, $d='') use ($instructor) { return is_array($instructor) ? ($instructor[$k] ?? $d) : ($instructor->{$k} ?? $d); };
$name = $g('name', 'Instructor');
$bio  = strip_tags((string) $g('biography', ''));
$skills = $g('skills', []);
$courses = $g('courses', []);
$rating = $g('rating', null);
$students = (int) $g('total_students', 0);
?>
<nav class="breadcrumbs">
    <a href="/">Home</a> &rsaquo; <a href="/instructors">Instructors</a> &rsaquo; <?= htmlspecialchars($name) ?>
</nav>
<h1><?= htmlspecialchars($name) ?></h1>
<?php if ($rating): ?><p><small><?= htmlspecialchars((string)$rating) ?> rating &middot; <?= $students ?> students</small></p><?php endif; ?>
<?php if ($bio): ?><h2>About</h2><p><?= nl2br(htmlspecialchars($bio)) ?></p><?php endif; ?>
<?php if (!empty($skills) && is_array($skills)): ?>
    <h2>Expertise</h2><p><?= htmlspecialchars(implode(', ', array_map('strval', $skills))) ?></p>
<?php endif; ?>
<?php if (!empty($courses) && is_array($courses)): ?>
<h2>Courses by <?= htmlspecialchars($name) ?></h2>
<div class="grid">
    <?php foreach ($courses as $c): ?>
        <?php $cid = is_array($c)?($c['id']??null):($c->id??null); $ct = is_array($c)?($c['title']??''):($c->title??''); if(!$cid) continue; ?>
        <article class="card">
            <h3><a href="/courses/<?= (int)$cid ?>-<?= slugify($ct) ?>"><?= htmlspecialchars($ct) ?></a></h3>
            <p><a href="/courses/<?= (int)$cid ?>-<?= slugify($ct) ?>">View course &rarr;</a></p>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<h2>Explore</h2>
<p><a href="/instructors">All instructors</a> &middot; <a href="/courses">All courses</a></p>
