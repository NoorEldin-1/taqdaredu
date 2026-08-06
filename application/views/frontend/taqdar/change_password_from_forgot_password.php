<?php
/**
 * تعيين كلمة مرور جديدة — نهاية رحلة الاستعادة.
 *
 * هذا الملف لم يكن موجودا في ثيم تقدر. و`index.php` يستدعي `show_404()`
 * لما لا يجد قالبه، فرابط إعادة التعيين الذي يصل في البريد كان يفتح
 * صفحة «غير موجودة»: الرحلة كلها — «نسيت كلمة المرور؟» ثم البريد ثم
 * الرابط — تنتهي عند حائط، والحساب لا يستعاد.
 *
 * والعقد كما في `Login::change_password`: `new_password` و`confirm_password`
 * إلى `login/change_password/<الرمز>`. والرمز يمضي في المسار لا في حقل
 * مخفي، فلا يقع في سجل النماذج ولا في إكمال المتصفح.
 */
$tq_h1   = 'كلمة مرور جديدة';
$tq_lead = 'اكتبها مرتين، وندخلك بها من جديد.';
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

      <form action="<?php echo site_url('login/change_password/' . $verification_code); ?>"
            method="post" id="reset-form" data-tq-auth novalidate>
        <div class="form-stack">
          <div class="form-cell">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-lock"></use></svg>
              <span class="sr-only">كلمة المرور الجديدة</span>
              <input type="password" name="new_password" id="newPw" required minlength="8"
                     placeholder="كلمة المرور الجديدة" autocomplete="new-password"
                     aria-describedby="newPwHint"
                     data-msg="كلمة المرور ثمانية محارف على الأقل.">
              <button class="pw-eye" type="button" data-tq-pw="newPw"
                      aria-label="إظهار كلمة المرور" aria-pressed="false">
                <svg aria-hidden="true"><use href="#i-eye"></use></svg>
              </button>
            </label>
            <p class="form-hint" id="newPwHint">ثمانية محارف على الأقل.</p>
          </div>
          <div class="form-cell">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-lock"></use></svg>
              <span class="sr-only">تأكيد كلمة المرور</span>
              <input type="password" name="confirm_password" id="newPw2" required minlength="8"
                     placeholder="تأكيد كلمة المرور" autocomplete="new-password"
                     data-match="newPw" data-msg="كلمتا المرور غير متطابقتين.">
              <button class="pw-eye" type="button" data-tq-pw="newPw2"
                      aria-label="إظهار كلمة المرور" aria-pressed="false">
                <svg aria-hidden="true"><use href="#i-eye"></use></svg>
              </button>
            </label>
          </div>
        </div>
        <button class="btn btn--primary btn--block" type="submit">احفظ كلمة المرور</button>
      </form>

      <p class="form-hint form-hint--box">
        <svg aria-hidden="true"><use href="#i-shield"></use></svg>
        <span>بعد الحفظ يسقط هذا الرابط ولا يعمل مرة أخرى، وتدخل بكلمتك الجديدة.</span>
      </p>

      <p class="form-alt"><a href="<?php echo base_url('login'); ?>">عودة إلى تسجيل الدخول</a></p>
      </div>

      <?php
      $tq_aside_h2 = 'خطوة أخيرة';
      $tq_aside_points = array(
        array('i-lock',   'اختر كلمة تذكرها', 'ثمانية محارف على الأقل'),
        array('i-shield', 'لا تشاركها',       'لن نطلبها منك في رسالة ولا مكالمة'),
        array('i-cap',    'ثم تكمل',          'برنامجك ودروسك كما تركتها'),
      );
      include __DIR__ . '/site/site_authaside.php';
      ?>
    </div>
  </div>
</section>
