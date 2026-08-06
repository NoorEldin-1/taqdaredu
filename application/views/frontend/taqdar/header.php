<?php
/** ترويسة الموقع العام — الشعار · القائمة · دخول · إنشاء حساب · اللغة · التطبيق */
$tq_logged = (bool) $this->session->userdata('user_id');
$tq_is_admin = $this->session->userdata('admin_login');
$tq_dash = $tq_is_admin ? base_url('admin/dashboard') : base_url('student');
?>
<header class="tq-site-header">
    <div class="tq-container tq-site-header__inner">

        <a class="tq-logo" href="<?php echo base_url(); ?>">
            <img src="<?php echo tq_asset('brand/wordmark.png'); ?>" alt="تقدر — منصة التعليم الذكي" height="40">
        </a>

        <nav class="tq-site-nav" aria-label="التنقل الرئيسي">
            <a href="<?php echo base_url('plans'); ?>">الدورات</a>
            <a href="<?php echo base_url('categories'); ?>">الأقسام</a>
            <a href="<?php echo base_url('plans'); ?>">الاشتراكات</a>
            <a href="<?php echo base_url('blog'); ?>">المدونة</a>
            <a href="<?php echo base_url('about'); ?>">من نحن</a>
            <a href="<?php echo base_url('contact'); ?>">تواصل معنا</a>
        </nav>

        <div class="tq-site-header__actions">

            <?php if ($tq_logged): ?>
                <a class="tq-btn tq-btn--primary tq-btn--sm" href="<?php echo $tq_dash; ?>">لوحتي</a>
            <?php else: ?>
                <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('login'); ?>">تسجيل الدخول</a>
                <a class="tq-btn tq-btn--primary tq-btn--sm" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب</a>
            <?php endif; ?>
        </div>
    </div>
</header>
