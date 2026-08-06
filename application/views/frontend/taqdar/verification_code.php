<?php
/**
 * تأكيد البريد بعد التسجيل.
 *
 * هذا القالب لم يكن في ثيم تقدر أيضا. وهو لا يظهر ما دام إعداد
 * `student_email_verification` معطلا، فبقي غيابه غير مرئي — ولكن
 * تفعيل الإعداد من لوحة الإدارة كان يكفي ليصير التسجيل كله ينتهي
 * بصفحة ٤٠٤ فور إنشاء الحساب: `Login::register` يحول إلى
 * `sign_up/verification_code`، و`index.php` لا يجد قالبا فيرد ٤٠٤.
 *
 * والنقطتان اللتان تنقلان العقد حرفيا: `login/verify_email_address`
 * بحقلي `email` و`verification_code`، و`login/resend_verification_code`
 * بحقل `email` — كلاهما يرد `true`/`false` نصا لا JSON.
 *
 * ولا jQuery في هذا الثيم، فالطلب بـ`fetch`. ورمز CSRF يرسل يدويا:
 * الحقن التلقائي يمس `<form>` وحدها، وهذان طلبان لا يمران بها.
 */
$tq_h1   = 'أكد بريدك';
$tq_lead = 'أرسلنا رمزا إلى بريدك. اكتبه هنا ليفتح حسابك.';
include __DIR__ . '/site/site_pagehero.php';

$tq_reg_email = (string) $this->session->userdata('register_email');
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
      <?php if ($tq_i = $this->session->flashdata('info_message')): ?>
        <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_i); ?></p>
      <?php endif; ?>

      <?php if ($tq_reg_email !== ''): ?>
        <p class="form-hint form-hint--box">
          <svg aria-hidden="true"><use href="#i-mail"></use></svg>
          <span>الرمز أرسل إلى <b><?php echo html_escape($tq_reg_email); ?></b></span>
        </p>
      <?php endif; ?>

      <form id="verify-form" data-tq-auth novalidate>
        <div class="form-cell">
          <label class="form-field">
            <svg aria-hidden="true"><use href="#i-shield"></use></svg>
            <span class="sr-only">رمز التأكيد</span>
            <input type="text" name="verification_code" id="tqVcode" required
                   inputmode="numeric" autocomplete="one-time-code"
                   placeholder="رمز التأكيد" maxlength="6" minlength="6"
                   data-msg="الرمز ست خانات.">
          </label>
        </div>
        <p class="field-err" id="vcodeErr" role="alert" hidden></p>
        <button class="btn btn--primary btn--block" type="submit">تأكيد</button>
      </form>

      <p class="form-alt">
        لم يصلك الرمز؟
        <a href="#" id="tqResend">أعد الإرسال</a>
        <span id="tqResendState" role="status"></span>
      </p>
      <p class="form-alt"><a href="<?php echo base_url('login'); ?>">عودة إلى تسجيل الدخول</a></p>
      </div>

      <?php
      $tq_aside_h2 = 'خطوة واحدة تفصلك';
      $tq_aside_points = array(
        array('i-mail',   'راجع بريدك',    'وربما مجلد الرسائل غير المرغوبة'),
        array('i-shield', 'الرمز لك وحدك', 'لا تشاركه مع أحد'),
        array('i-cap',    'ثم تبدأ',       'برنامجك في انتظارك'),
      );
      include __DIR__ . '/site/site_authaside.php';
      ?>
    </div>
  </div>
</section>

<script>
(function () {
  var form = document.getElementById('verify-form');
  if (!form) return;
  var input = document.getElementById('tqVcode');
  var err   = document.getElementById('vcodeErr');
  var email = <?php echo json_encode($tq_reg_email); ?>;
  var CSRF  = <?php echo json_encode(array(
      $this->security->get_csrf_token_name() => $this->security->get_csrf_hash())); ?>;

  function say(msg) { err.textContent = msg || ''; err.hidden = !msg; }

  function post(url, data) {
    var body = new URLSearchParams(CSRF);
    Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
    return fetch(url, {
      method: 'POST', body: body, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (r) { return r.text(); });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();          /* الوجهة نقطة تجيب نصا لا صفحة */
    say('');
    var code = input.value.trim();
    if (!/^[0-9]{6}$/.test(code)) { say('الرمز ست خانات رقمية.'); input.focus(); return; }

    var btn = form.querySelector('[type=submit]');
    btn.disabled = true; btn.classList.add('is-busy');
    post('<?php echo site_url('login/verify_email_address'); ?>',
         { email: email, verification_code: code })
      .then(function (t) {
        /* النقطة ترد `1` عند القبول وفراغا عند الرفض. */
        if (t.trim() === '1') { location.href = '<?php echo site_url('login'); ?>'; return; }
        say('الرمز غير صحيح. راجع الرسالة أو أعد الإرسال.');
      })
      .catch(function () { say('تعذر الاتصال. حاول مرة أخرى.'); })
      .finally(function () { btn.disabled = false; btn.classList.remove('is-busy'); });
  });

  var resend = document.getElementById('tqResend');
  var state  = document.getElementById('tqResendState');
  resend.addEventListener('click', function (e) {
    e.preventDefault();
    /* الزر يقفل ستين ثانية: النقر المتكرر يصطدم بخنق الخادم فيبدو
       كأن شيئا لا يحدث. */
    resend.setAttribute('aria-disabled', 'true');
    resend.style.pointerEvents = 'none';
    state.textContent = ' — جار الإرسال…';
    post('<?php echo site_url('login/resend_verification_code'); ?>', { email: email })
      .then(function () { state.textContent = ' — أرسلنا رمزا جديدا.'; })
      .catch(function () { state.textContent = ' — تعذر الإرسال.'; })
      .finally(function () {
        setTimeout(function () {
          resend.removeAttribute('aria-disabled');
          resend.style.pointerEvents = '';
          state.textContent = '';
        }, 60000);
      });
  });
})();
</script>
