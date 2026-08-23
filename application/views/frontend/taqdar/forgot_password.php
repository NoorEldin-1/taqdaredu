<?php
/** استعادة كلمة المرور. المقطع `frontend` جزء من البرنامج لا زيادة فيه. */
$tq_h1 = 'استعادة كلمة المرور';
$tq_lead = 'اكتب بريدك، ونرسل إليك رابط إعادة التعيين.';
include __DIR__ . '/site/site_pagehero.php';
?>
<section class="section">
  <div class="shell shell--auth">
    <div class="auth-wrap">
      <div class="form-card">
      <a class="auth-brand" href="<?php echo base_url(); ?>" aria-label="منصة تقدر">
        <img src="<?php echo tq_site_asset('img/logo.webp'); ?>" alt="منصة تقدر" width="280" height="157">
      </a>

      <?php if ($tq_e = $this->session->flashdata('error_message')): ?>
        <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($tq_e); ?></p>
      <?php endif; ?>
      <?php if ($tq_f = $this->session->flashdata('flash_message')): ?>
        <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_f); ?></p>
      <?php endif; ?>

      <form action="<?php echo site_url('login/forgot_password/frontend'); ?>" method="post"
            id="forgot-password" data-tq-auth novalidate>
        <div class="form-cell">
          <label class="form-field">
            <svg aria-hidden="true"><use href="#i-mail"></use></svg>
            <span class="sr-only">البريد الإلكتروني</span>
            <input type="email" name="email" placeholder="البريد الإلكتروني" required
                   autocomplete="email" maxlength="50" autocapitalize="off" spellcheck="false"
                   value="<?php echo html_escape((string) $this->session->flashdata('tq_old_email')); ?>">
          </label>
        </div>
        <button class="btn btn--primary btn--block" type="submit">أرسل الرابط</button>
      </form>

      <p class="form-hint form-hint--box">
        <svg aria-hidden="true"><use href="#i-clock"></use></svg>
        <span>الرابط صالح لمدة محدودة ويستعمل مرة واحدة. وإن لم يصلك خلال دقائق فاقرأ ما تحته.</span>
      </p>

      <?php /* TQ-SPAM — الرابط الذي لا يصل يترك صاحبه خارج حسابه.
               وكان هنا نصف سطر يقول «فراجع مجلد الرسائل غير المرغوبة» —
               وهو صحيح ولا يكفي: لا يقول أين المجلد، ولا ما يفعل حين
               يجد الرسالة، فتذهب الرسالة التالية إلى المجلد نفسه.
               والوجهة تعرف بعد الإرسال (`tq_sent_to`)، فيفتح له مزوده هو. */ ?>
      <?php echo tq_spam_notice(array(
          'email' => (string) ($this->session->flashdata('tq_sent_to')
                     ?: $this->session->flashdata('tq_old_email')),
          'what'  => 'رابط إعادة التعيين',
      )); ?>

      <p class="form-alt"><a href="<?php echo base_url('login'); ?>">عودة إلى تسجيل الدخول</a></p>
      </div>

      <?php /* اللوحة هنا كما في أختيها: الصفحة كانت عمودا واحدا في غلاف
              عرضه غلاف عمودين، فيقف النموذج وحده في وسط فراغ. */
      $tq_aside_h2 = 'حسابك يبقى لك';
      $tq_aside_points = array(
        array('i-lock',    'رابط لمرة واحدة', 'يسقط بعد استعماله أو انتهاء مدته'),
        array('i-shield',  'لا يطلع عليه أحد', 'يرسل إلى بريدك وحده'),
        array('i-support', 'تعثرت؟',           'تواصل معنا ونساعدك'),
      );
      include __DIR__ . '/site/site_authaside.php';
      ?>
    </div>
  </div>
</section>
