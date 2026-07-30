<?php
/**
 * SEO home page partial.
 * Expected variables: $top_courses (array), $categories (array)
 */
$top_courses = isset($top_courses) ? $top_courses : [];
$categories  = isset($categories)  ? $categories  : [];
?>

<h1>My-Communication Academy</h1>
<p>
    Master the skills that power modern telecommunications. From 5G networks to cloud
    infrastructure, learn from industry experts and advance your career with globally
    recognized certifications. We empower professionals worldwide with cutting-edge
    telecom, IT, and business education.
</p>

<h2>Why choose us</h2>
<ul>
    <li>Expert instructors with deep industry experience</li>
    <li>Practical, hands-on telecom and networking courses</li>
    <li>Globally recognized certifications</li>
    <li>Flexible online learning at your own pace</li>
</ul>

<?php if (!empty($categories)): ?>
<h2>Course categories</h2>
<ul>
    <?php foreach ($categories as $cat): ?>
        <?php
        $catId   = is_array($cat) ? ($cat['id']   ?? null) : ($cat->id   ?? null);
        $catName = is_array($cat) ? ($cat['name'] ?? '')   : ($cat->name ?? '');
        if (!$catId) continue;
        ?>
        <li>
            <a href="/courses?category=<?= (int)$catId ?>"><?= htmlspecialchars($catName) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (!empty($top_courses)): ?>
<h2>Featured courses</h2>
<div class="grid">
    <?php foreach ($top_courses as $course): ?>
        <?php
        $cId    = is_array($course) ? ($course['id']    ?? null) : ($course->id    ?? null);
        $cTitle = is_array($course) ? ($course['title'] ?? '')   : ($course->title ?? '');
        $cDesc  = is_array($course) ? ($course['short_description'] ?? '') : ($course->short_description ?? '');
        $cPrice = is_array($course) ? ($course['price'] ?? 0) : ($course->price ?? 0);
        if (!$cId) continue;
        ?>
        <article class="card">
            <h3><a href="/courses/<?= (int)$cId ?>-<?= slugify($cTitle) ?>"><?= htmlspecialchars($cTitle) ?></a></h3>
            <p><?= htmlspecialchars(strip_tags($cDesc)) ?></p>
            <p class="price"><?= $cPrice > 0 ? '£' . number_format((float)$cPrice, 2) : 'Free' ?></p>
            <p><a href="/courses/<?= (int)$cId ?>-<?= slugify($cTitle) ?>">View course details &rarr;</a></p>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h2>Get started</h2>
<p>
    Browse our <a href="/courses">complete course catalog</a>, learn more
    <a href="/about-us">about our academy</a>, or
    <a href="/contact-us">contact our team</a>.
</p>
