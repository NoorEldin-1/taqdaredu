<?php
/**
 * تذييل الواجهة العامة — تصميم تقدر.
 *
 * يدرج للصفحات العامة وحدها، فسكربت التصميم يحمل من هنا: لا حاجة إلى
 * لمس `includes_bottom.php` المشترك مع البوابات (وفيه لف CSRF وتحميل
 * سكربتي الدرس والمراجعة المشروط).
 *
 * وبيانات التواصل **من الإعدادات**: ما لم يضبط لا يعرض. الرقم الوهمي
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
        <img src="<?php echo tq_site_asset('img/logo-light.webp'); ?>" alt="منصة تقدر" width="280" height="163">
        <p><?php echo html_escape(get_settings('slogan') ?: 'منصة تعليمية تلهم.. وتمكن'); ?></p>
        <div class="stores"><?php include __DIR__ . '/site_stores.php'; ?></div>
      </div>

      <nav class="footer-col" aria-label="عن المنصة">
        <h3>عن المنصة</h3>
        <a href="<?php echo base_url('about'); ?>">من نحن</a>
        <a href="<?php echo base_url('about'); ?>#vision">رؤيتنا ورسالتنا</a>
        <a href="<?php echo base_url('blog'); ?>">المدونة</a>
      </nav>

      <nav class="footer-col" aria-label="روابط سريعة">
        <h3>روابط سريعة</h3>
        <a href="<?php echo base_url('catalog'); ?>">المواد والبرامج التعليمية</a>
        <a href="<?php echo base_url('plans'); ?>">الباقات</a>
        <a href="<?php echo base_url('catalog'); ?>?type=book">كتب المنهج</a>
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
<?php /* وسائل الدفع — قرار المالك. والبطاقة (مدى/فيزا/ماستركارد)
        والتحويل البنكي هما ما تنفذه شاشة الدفع فعلا اليوم. */ ?>
    <div class="paybar">
      <p class="paybar__t">نستقبل وسائل دفع متعددة وآمنة</p>
      <ul class="paybar__list">
<?php
/* العرض والارتفاع الحقيقيان لكل شعار: نسبها مختلفة، و`width="auto"`
   ليس قيمة صالحة — فبدونهما يقفز التذييل عند التحميل (CLS). */
$tq_pays = array(
    'pay-mada'       => array('مدى',          151, 90),
    'pay-visa'       => array('فيزا',          151, 90),
    'pay-mastercard' => array('ماستركارد',    150, 90),
    'pay-applepay'   => array('Apple Pay',    252, 91),
    'pay-tamara'     => array('تمارا',         280, 91),
    'pay-bank'       => array('تحويل بنكي',   153, 92),
);
foreach ($tq_pays as $tq_f => $tq_p):
?>
        <li><img src="<?php echo tq_site_asset('img/pay/' . $tq_f . '.webp'); ?>"
                 alt="<?php echo html_escape($tq_p[0]); ?>"
                 width="<?php echo $tq_p[1]; ?>" height="<?php echo $tq_p[2]; ?>"
                 class="<?php echo $tq_f === 'pay-tamara' ? 'is-bleed' : ''; ?>"
                 loading="lazy" decoding="async"></li>
<?php endforeach; ?>
      </ul>
    </div>
    <p class="footer-bottom">جميع الحقوق محفوظة © <?php echo $tq_year; ?> <?php echo html_escape(get_settings('system_title') ?: 'منصة تقدر التعليمية'); ?></p>
  </div>
</footer>

<?php /* الشريط السفلي حذف: خمسة روابط تكرر ما في قائمة الهامبرغر
        نفسها، مقابل ٥٧px تقتطع دائما من شاشة ضيقة. والملف باق
        (`site/site_tabbar.php`) فإعادته سطر واحد. */ ?>

<?php /* TQ-PLAN-AUTH — نافذة «خطوة واحدة قبل الدفع» لكل صفحة موقع
        يفتحها زائر بلا جلسة: الباقات والباقة الواحدة والكتالوج وما
        يضاف غدا. وهي تطبع مرة واحدة في المستند — و`site.js` يمسك
        **كل** رابط شراء بتفويض على المستند، فبطاقة تحقن بعد التحميل
        (نتائج الكتالوج) تفتحها كما تفتحها بطاقة طبعت مع الصفحة.
        ولمن له جلسة لا تطبع اصلا: هو يذهب الى شاشة التاكيد مباشرة. */ ?>
<?php if ((int) $this->session->userdata('user_id') <= 0): ?>
<?php include __DIR__ . '/site_auth_modal.php'; ?>
<?php endif; ?>

<?php /* حقل الجوال يعيش في الجهتين — انظر `tq-phone.js`. */ ?>
<script src="<?php echo tq_asset('js/tq-phone.js'); ?>" defer></script>
<script src="<?php echo tq_site_asset('js/site.js'); ?>" defer></script>
