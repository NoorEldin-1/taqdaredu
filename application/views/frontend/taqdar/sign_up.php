<?php
/**
 * إنشاء حساب — ثلاث بوابات.
 *
 * الطالب وولي الأمر والمعلم لا يريدون الشيء نفسه ولا يسألون عنه.
 * فبوابة لكل، وحقول تخصه، ووجهة هي لوحته.
 *
 * **وبرنامج المعلم لا يفتح حسابا**: ينشئ طلبا ينتظر اعتماد الإدارة.
 * منصة يدرس فيها الغرباء بلا تحقق ليست منصة تعليمية.
 */
$tq_h1   = 'حسابك في تقدر مجاني.';
$tq_lead = 'اختر بوابتك، وأنشئ حسابك في دقيقة.';
include __DIR__ . '/site/site_pagehero.php';

/* «ولي أمر» لم تعد بوابة عامة، فـ`?as=parent` لا يقبل — وإلا فتحت
   بطاقة محذوفة من الاختيار عبر الرابط وحده. */
$tq_gate = in_array((string) $this->input->get('as'), array('teacher'), true)
         ? (string) $this->input->get('as') : 'student';
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

      <div class="gate-picker" role="tablist" aria-label="نوع الحساب">
        <?php foreach (array(
          'student' => array('طالب',      'i-cap',      'أتعلم على المنصة'),
          'teacher' => array('معلم',     'i-teacher',  'أدرس على المنصة'),
        ) as $tq_k => $tq_g): ?>
          <button class="gate-card<?php echo $tq_gate === $tq_k ? ' is-on' : ''; ?>" type="button"
                  role="tab" aria-selected="<?php echo $tq_gate === $tq_k ? 'true' : 'false'; ?>"
                  data-gate="<?php echo $tq_k; ?>">
            <svg aria-hidden="true"><use href="#<?php echo $tq_g[1]; ?>"></use></svg>
            <b><?php echo $tq_g[0]; ?></b><span><?php echo $tq_g[2]; ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <form action="<?php echo site_url('login/register'); ?>" method="post"
            enctype="multipart/form-data" id="signup-form" novalidate>
        <input type="hidden" name="tq_gate" id="tqGate" value="<?php echo html_escape($tq_gate); ?>">

        <div class="form-grid">
          <label class="form-field">
            <svg aria-hidden="true"><use href="#i-user"></use></svg>
            <span class="sr-only">الاسم الأول</span>
            <input type="text" name="first_name" placeholder="الاسم الأول" required>
          </label>
          <label class="form-field">
            <svg aria-hidden="true"><use href="#i-user"></use></svg>
            <span class="sr-only">اسم العائلة</span>
            <input type="text" name="last_name" placeholder="اسم العائلة" required>
          </label>
        </div>

        <label class="form-field">
          <svg aria-hidden="true"><use href="#i-mail"></use></svg>
          <span class="sr-only">البريد الإلكتروني</span>
          <input type="email" name="email" placeholder="البريد الإلكتروني" required autocomplete="email">
        </label>

        <div class="form-grid">
          <label class="form-field">
            <svg aria-hidden="true"><use href="#i-lock"></use></svg>
            <span class="sr-only">كلمة المرور</span>
            <input type="password" name="password" id="signPw" aria-describedby="pwHint" placeholder="كلمة المرور" required
                   minlength="8" autocomplete="new-password">
              <button class="pw-eye" type="button" data-tq-pw="signPw" aria-label="إظهار كلمة المرور" aria-pressed="false"><svg aria-hidden="true"><use href="#i-eye"></use></svg></button>
          </label>
            <?php /* التلميح تحت الحقل الذي يصفه لا تحت تأكيده:
                    من يقرؤه بعد كتابة الكلمتين يقرؤه بعد فوات الأوان. */ ?>
            <p class="form-hint" id="pwHint">ثمانية محارف على الأقل.</p>
          <label class="form-field">
            <svg aria-hidden="true"><use href="#i-lock"></use></svg>
            <span class="sr-only">تأكيد كلمة المرور</span>
            <input type="password" name="password_confirm" placeholder="تأكيد كلمة المرور" required
                   minlength="8" autocomplete="new-password">
          </label>
        </div>


        <?php /* ── الطالب: العمر، وبريد ولي الأمر لمن هو دون الخامسة عشرة ── */ ?>
        <div class="gate-fields" data-for="student">
          <div class="form-grid">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-user"></use></svg>
              <span class="sr-only">العمر</span>
              <?php /* مطلوب: المرحلة تشتق منه، وحساب بلا عمر لا يصنف.
        وكان بلا `required` رغم أنه محور التصنيف. */ ?>
              <input type="number" name="age" id="tqAge" required
                     autocomplete="off" placeholder="العمر" min="5" max="99" inputmode="numeric">
            </label>
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-cap"></use></svg>
              <span class="sr-only">الصف الدراسي</span>
              <select name="grade_id">
                <option value="">الصف الدراسي (اختياري)</option>
                <?php
                $tq_ci_g = &get_instance();
                foreach ($tq_ci_g->db->select('id, name_ar')->from('grades')->where('active', 1)
                                     ->order_by('`order`', 'ASC')->get()->result_array() as $tq_gr): ?>
                  <option value="<?php echo (int) $tq_gr['id']; ?>"><?php echo html_escape($tq_gr['name_ar']); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div id="tqGuardian" hidden>
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-mail"></use></svg>
              <span class="sr-only">بريد ولي الأمر</span>
              <input type="email" name="guardian_email" placeholder="بريد ولي الأمر">
            </label>
            <p class="form-hint">
              دون الخامسة عشرة نطلب بريد ولي أمرك، ونرسل إليه طلب موافقة قبل تفعيل الحساب.
            </p>
          </div>
        </div>

        <?php /* ── المعلم: طلب يراجع، لا حساب يفتح ── */ ?>
        <div class="gate-fields" data-for="teacher">
          <label class="form-field">
            <svg aria-hidden="true"><use href="#i-phone"></use></svg>
            <span class="sr-only">رقم الجوال</span>
            <input type="tel" name="phone" placeholder="5XXXXXXXX" inputmode="numeric" pattern="5[0-9]{8}">
          </label>
          <label class="form-field">
            <svg aria-hidden="true"><use href="#i-file"></use></svg>
            <span class="sr-only">مستند التعريف</span>
            <input type="file" name="document" id="tqDoc" accept=".pdf,.jpg,.jpeg,.png" class="sr-only">
            <span class="file-btn" id="tqDocBtn">اختر ملفا…</span>
            <span class="file-name" id="tqDocName">لم يختر ملف</span>
          </label>
          <label class="form-field form-field--full">
            <span class="sr-only">نبذة عن خبرتك</span>
            <textarea name="message" rows="3" placeholder="نبذة عن خبرتك التدريسية وموادك"></textarea>
          </label>
          <p class="form-hint">
            طلبك يراجع من الإدارة، ونتواصل معك قبل تفعيل لوحتك.
          </p>
        </div>

        <label class="form-consent">
          <input type="checkbox" name="accept_terms" value="1" required>
          <span>أوافق على <a href="<?php echo base_url('terms'); ?>">الشروط والأحكام</a>
                و<a href="<?php echo base_url('privacy'); ?>">سياسة الخصوصية</a>.</span>
        </label>

        <button class="btn btn--primary btn--block" type="submit">إنشاء الحساب</button>
      </form>

      <p class="form-alt">لديك حساب؟ <a href="<?php echo base_url('login'); ?>">سجل الدخول</a></p>
      </div>

      <aside class="auth-aside" aria-hidden="true">
        <img src="<?php echo tq_site_asset('img/auth-panel.webp'); ?>" alt="" width="700" height="1050" loading="lazy" decoding="async">
        <div class="auth-aside__body">
          <h2>منصة تعليمية سعودية</h2>
          <ul class="auth-points">
            <li><svg aria-hidden="true"><use href="#i-cap"></use></svg>
                <span><b>وفق المنهج السعودي</b>برامج لكل صف ومادة</span></li>
            <li><svg aria-hidden="true"><use href="#i-chart"></use></svg>
                <span><b>متابعة تقدمك</b>تقارير دقيقة لك ولولي أمرك</span></li>
            <li><svg aria-hidden="true"><use href="#i-certificate"></use></svg>
                <span><b>شهادات إتقان</b>تصدر عند اجتياز المحطات</span></li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
</section>

<script>
/* البوابة تبدل الحقول والوجهة. وحقول كل بوابة `required` عند اختيارها
   وحدها — وإلا منع الإرسال بحقل مخفي لا يراه المستخدم. */
(function () {
  var gate = document.getElementById('tqGate');
  var picker = document.querySelector('.gate-picker');
  var age = document.getElementById('tqAge');
  var guard = document.getElementById('tqGuardian');
  if (!gate || !picker) return;

  function apply(v) {
    gate.value = v;
    picker.querySelectorAll('.gate-card').forEach(function (b) {
      var on = b.dataset.gate === v;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-selected', String(on));
    });
    document.querySelectorAll('.gate-fields').forEach(function (f) {
      var on = f.dataset.for === v;
      f.hidden = !on;
      f.querySelectorAll('input,select,textarea').forEach(function (el) {
        if (!on) { el.removeAttribute('required'); return; }
        if (el.name === 'phone' || el.name === 'document') el.setAttribute('required', '');
      });
    });
    if (v !== 'student' && guard) guard.hidden = true;
  }

  picker.addEventListener('click', function (e) {
    var b = e.target.closest('.gate-card');
    if (b) apply(b.dataset.gate);
  });

  if (age) age.addEventListener('input', function () {
    var n = parseInt(age.value, 10);
    var minor = !isNaN(n) && n > 0 && n < 15;
    if (guard) {
      guard.hidden = !minor;
      var g = guard.querySelector('input');
      if (g) { if (minor) g.setAttribute('required', ''); else g.removeAttribute('required'); }
    }
  });

  /* حقل الملف: زر عربي واسم الملف المختار بدل «Choose File» */
  var doc = document.getElementById('tqDoc'), btn = document.getElementById('tqDocBtn'),
      nm = document.getElementById('tqDocName');
  if (doc && btn) {
    btn.addEventListener('click', function () { doc.click(); });
    doc.addEventListener('change', function () {
      if (nm) nm.textContent = doc.files && doc.files[0] ? doc.files[0].name : 'لم يختر ملف';
    });
  }

  apply(gate.value);
})();
</script>
