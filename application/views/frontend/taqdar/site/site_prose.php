<?php
/**
 * الصفحات النصية — أحكام وخصوصية واسترجاع.
 *
 * قالب واحد لثلاث صفحات لأن الفرق بينها **مفتاح إعداد واحد** لا تخطيط.
 * والنص HTML كتبه المدير في اللوحة، فلا يمر بـ`html_escape` — وإلا
 * ظهرت وسومه حروفا. وهذا مقبول لأن كاتبه هو الإدارة نفسها.
 */
$tq_body  = (string) get_frontend_settings($tq_key);
$tq_shell = 'shell--read';
include __DIR__ . '/site_pagehero.php';
?>
<section class="section">
  <div class="shell shell--read">
    <div class="icard prose">
      <?php if (trim(strip_tags($tq_body)) !== ''): ?>
        <?php echo $tq_body; ?>
      <?php else: ?>
        <p class="dir-empty">لم يكتب نص هذه الصفحة بعد.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
