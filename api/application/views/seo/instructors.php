<?php
$instructors = isset($instructors) ? $instructors : [];
?>
<nav class="breadcrumbs"><a href="/">Home</a> &rsaquo; Instructors</nav>
<h1>Our Instructors</h1>
<p>Meet the industry-expert instructors behind My-Communication Academy.</p>

<?php if (empty($instructors)): ?>
    <p>No instructors listed yet.</p>
<?php else: ?>
<div class="grid">
    <?php foreach ($instructors as $i): ?>
        <?php
        $id    = is_array($i) ? ($i['id']    ?? null) : ($i->id    ?? null);
        $name  = is_array($i) ? ($i['name']  ?? '')   : ($i->name  ?? '');
        $title = is_array($i) ? ($i['title'] ?? '')   : ($i->title ?? '');
        $n     = is_array($i) ? ($i['total_courses'] ?? 0) : ($i->total_courses ?? 0);
        if (!$id) continue;
        ?>
        <article class="card">
            <h3><a href="/instructors/<?= (int)$id ?>"><?= htmlspecialchars($name) ?></a></h3>
            <?php if ($title): ?><p><?= htmlspecialchars(strip_tags($title)) ?></p><?php endif; ?>
            <p><small><?= (int)$n ?> course(s)</small></p>
            <p><a href="/instructors/<?= (int)$id ?>">View profile &rarr;</a></p>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
