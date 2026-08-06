<?php
/**
 * هيرو الصفحات الداخلية — قوس وفانوس وعنوان.
 *
 * جزء واحد بدل تكراره في اثنتي عشرة صفحة: تكراره يعني أن تصحيح إزاحة
 * فيه يحتاج اثني عشر تعديلا، وينسى في واحد منها فينفرد بمظهره.
 * المتغيرات `$tq_h1` و`$tq_lead` تضبط قبل الإدراج.
 *
 * و`$tq_shell` غلاف اختياري: الصفحات النصية تمرر `shell--read` ليقف
 * العنوان على عمود النص نفسه. وبدونه يمتد العنوان على الغلاف كله بينما
 * النص تحته في عمود أضيق، فتنشأ فجوة تقرأ خطأ في التصميم لا اختيارا.
 *
 * و`site_lantern.php` وسم `svg` عار لا غلاف له — الغلاف `span.lantern`
 * هو ما يحمل `position:absolute` والتأرجح. وكان يدرج هنا مجردا، فيسقط
 * في مجرى الصفحة عنصرا سطريا: مصباح ذهبي معلق وحده في أعلى يمين كل
 * صفحة داخلية، يدفع ما بعده ولا يمت لشيء. الغلاف يعيده زينة.
 */
?>
<section class="page-hero">
  <div class="<?php echo html_escape(trim('shell ' . (isset($tq_shell) ? $tq_shell : ''))); ?>">
    <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site_lantern.php'; ?></span>
    <span class="lantern lantern--l lantern--slow" aria-hidden="true"><?php include __DIR__ . '/site_lantern.php'; ?></span>
    <h1><?php echo html_escape($tq_h1); ?></h1>
    <?php if (!empty($tq_lead)): ?>
      <p class="page-hero__lead"><?php echo html_escape($tq_lead); ?></p>
    <?php endif; ?>
  </div>
</section>
