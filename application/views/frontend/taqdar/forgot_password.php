<?php
/** استعادة كلمة المرور. المقطع `frontend` جزءٌ من البرنامج لا زيادة فيه. */
$tq_h1 = 'استعادة كلمة المرور';
$tq_lead = 'اكتب بريدك، ونرسل إليك رابط إعادة التعيين.';
include __DIR__ . '/site/site_pagehero.php';
?>
<section class="section">
  <div class="shell shell--auth">
    <div class="auth-wrap">
      <div class="form-card">
      <a class="auth-brand" href="<?php echo base_url(); ?>" aria-label="منصّة تقدّر">
        <img src="<?php echo tq_site_asset('img/logo.webp'); ?>" alt="منصّة تقدّر" width="280" height="157">
      </a>

      <?php if ($tq_e = $this->session->flashdata('error_message')): ?>
        <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($tq_e); ?></p>
      <?php endif; ?>
      <?php if ($tq_f = $this->session->flashdata('flash_message')): ?>
        <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_f); ?></p>
      <?php endif; ?>

      <form action="<?php echo site_url('login/forgot_password/frontend'); ?>" method="post" id="forgot-password">
        <label class="form-field">
          <svg aria-hidden="true"><use href="#i-mail"></use></svg>
          <span class="sr-only">البريد الإلكتروني</span>
          <input type="email" name="email" placeholder="البريد الإلكتروني" required autocomplete="email">
        </label>
        <button class="btn btn--primary btn--block" type="submit">أرسل الرابط</button>
      </form>

      <p class="form-alt"><a href="<?php echo base_url('login'); ?>">عودة إلى تسجيل الدخول</a></p>
      </div>
    </div>
  </div>
</section>
