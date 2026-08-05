<?php
/**
 * تذييل الواجهة العامّة — تصميم تقدّر.
 *
 * يُدرَج للصفحات العامّة وحدها، فسكربت التصميم يُحمَّل من هنا: لا حاجة إلى
 * لمس `includes_bottom.php` المشترك مع البوّابات (وفيه لفّ CSRF وتحميل
 * سكربتَي الدرس والمراجعة المشروط).
 *
 * وبيانات التواصل **من الإعدادات**: ما لم يُضبط لا يُعرَض. الرقم الوهميّ
 * الذي كان في التصميم أسوأ من غياب الرقم — من يتصل به يجد لا أحد.
 */
$tq_mail  = trim((string) get_settings('system_email'));
$tq_phone = trim((string) get_settings('phone'));
$tq_year  = date('Y');
?>
<footer class="site-footer">
  <div class="shell">
    <div class="footer-grid">

      <div class="footer-brand">
        <img src="<?php echo tq_site_asset('img/logo-light.webp'); ?>" alt="منصّة تقدّر" width="280" height="163">
        <p><?php echo html_escape(get_settings('slogan') ?: 'منصة تعليمية تُلهم.. وتُمكِّن'); ?></p>
        <div class="stores"><?php include __DIR__ . '/site_stores.php'; ?></div>
      </div>

      <nav class="footer-col" aria-label="عن المنصة">
        <h3>عن المنصة</h3>
        <a href="<?php echo base_url('about'); ?>">من نحن</a>
        <a href="<?php echo base_url('about'); ?>#vision">رؤيتنا ورسالتنا</a>
        <a href="<?php echo base_url('about'); ?>#team">فريق العمل</a>
        <a href="<?php echo base_url('blog'); ?>">المدوّنة</a>
      </nav>

      <nav class="footer-col" aria-label="روابط سريعة">
        <h3>روابط سريعة</h3>
        <a href="<?php echo base_url('plans'); ?>">المواد والبرامج التعليمية</a>
        <a href="<?php echo base_url('books'); ?>">كتب المنهج</a>
        <a href="<?php echo base_url('teachers'); ?>">المعلمون</a>
        <a href="<?php echo base_url('students'); ?>">الطلاب</a>
      </nav>

      <nav class="footer-col" aria-label="الدعم والمساعدة">
        <h3>الدعم والمساعدة</h3>
        <a href="<?php echo base_url('faq'); ?>">الأسئلة الشائعة</a>
        <a href="<?php echo base_url('privacy'); ?>">سياسة الخصوصية</a>
        <a href="<?php echo base_url('terms'); ?>">الشروط والأحكام</a>
        <a href="<?php echo base_url('contact'); ?>">اتصل بنا</a>
      </nav>

      <div class="footer-col footer-contact">
        <h3>تواصل معنا</h3>
<?php if ($tq_mail !== ''): ?>
        <a href="mailto:<?php echo html_escape($tq_mail); ?>">
          <svg aria-hidden="true"><use href="#i-mail"></use></svg><?php echo html_escape($tq_mail); ?></a>
<?php endif; ?>
<?php if ($tq_phone !== ''): ?>
        <a href="tel:<?php echo html_escape(preg_replace('/[^0-9+]/', '', $tq_phone)); ?>">
          <svg aria-hidden="true"><use href="#i-phone"></use></svg>
          <span class="tq-ltr"><?php echo html_escape($tq_phone); ?></span></a>
<?php endif; ?>
<?php echo tqs_social(); ?>
      </div>

    </div>
    <p class="footer-bottom">جميع الحقوق محفوظة © <?php echo $tq_year; ?> <?php echo html_escape(get_settings('system_title') ?: 'منصة تقدّر التعليمية'); ?></p>
  </div>
</footer>

<?php /* الشريط السفليّ حُذف: خمسة روابط تكرّر ما في قائمة الهامبرغر
        نفسها، مقابل ٥٧px تُقتطع دائمًا من شاشةٍ ضيّقة. والملفّ باقٍ
        (`site/site_tabbar.php`) فإعادته سطرٌ واحد. */ ?>

<script src="<?php echo tq_site_asset('js/site.js'); ?>" defer></script>
