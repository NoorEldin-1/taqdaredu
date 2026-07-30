<?php $items = isset($items) ? $items : []; ?>
<nav class="breadcrumbs"><a href="/">Home</a> &rsaquo; FAQ</nav>
<h1>Frequently Asked Questions</h1>
<p>Common questions about My-Communication Academy — our Telecom, 5G, networking, and IT courses, enrolment, certificates, access, and support.</p>

<?php foreach ($items as $f): ?>
    <h2><?= htmlspecialchars($f['q']) ?></h2>
    <p><?= htmlspecialchars($f['a']) ?></p>
<?php endforeach; ?>

<p>Still have a question? <a href="/contact-us">Contact our team</a> or browse <a href="/courses">all courses</a>.</p>
