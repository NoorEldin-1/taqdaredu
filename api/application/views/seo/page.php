<?php
$page = isset($page) ? $page : null;
if (!$page) { echo '<p>Page not found.</p>'; return; }
$g = function ($k, $d='') use ($page) { return is_array($page) ? ($page[$k] ?? $d) : ($page->{$k} ?? $d); };
$title = $g('title', 'Page');
$content = (string) $g('content', '');
$safe = strip_tags($content, '<p><br><h2><h3><h4><strong><em><ul><ol><li><a><blockquote><table><tr><td><th><thead><tbody>');
?>
<nav class="breadcrumbs"><a href="/">Home</a> &rsaquo; <?= htmlspecialchars($title) ?></nav>
<h1><?= htmlspecialchars($title) ?></h1>
<?php if (trim($safe) !== ''): ?>
    <div><?= $safe ?></div>
<?php else: ?>
    <p>Please contact us for more information about <?= htmlspecialchars($title) ?>.</p>
<?php endif; ?>
