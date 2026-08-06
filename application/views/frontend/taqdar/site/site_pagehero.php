<?php
/**
 * هيرو الصفحات الداخلية — قوس وفانوس وعنوان.
 *
 * جزء واحد بدل تكراره في اثنتي عشرة صفحة: تكراره يعني أن تصحيح إزاحة
 * فيه يحتاج اثني عشر تعديلا، وينسى في واحد منها فينفرد بمظهره.
 * المتغيرات `$tq_h1` و`$tq_lead` تضبط قبل الإدراج.
 */
?>
<section class="page-hero">
  <?php include __DIR__ . '/site_lantern.php'; ?>
  <div class="shell">
    <h1><?php echo html_escape($tq_h1); ?></h1>
    <?php if (!empty($tq_lead)): ?>
      <p class="page-hero__lead"><?php echo html_escape($tq_lead); ?></p>
    <?php endif; ?>
  </div>
</section>
