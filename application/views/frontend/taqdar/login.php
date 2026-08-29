<?php
/**
 * تسجيل الدخول.
 *
 * العقد محفوظ حرفيا: `login/validate_login` بحقلي `email` و`password`.
 * المتحكم يتحقق منهما بأسمائهما، فتغيير حرف في اسم حقل ينتج رفضا
 * صامتا يبدو للمستخدم «كلمة مرور خاطئة».
 *
 * ولا حقل CSRF يدوي: `REST_Output` يحقنه في كل `<form method=post>`.
 */
$tq_h1 = 'أكمل من حيث توقفت.';
$tq_lead = 'ادخل إلى حسابك لمتابعة برنامجك ودروسك.';
include __DIR__ . '/site/site_pagehero.php';

/* البريد يعود بعد الرفض: من أخطأ في كلمة المرور لا يعاقب بإعادة كتابة
   بريده أيضا. والمتحكم يودعه في `tq_old_email` عند كل رفض. */
$tq_old_email = (string) $this->session->flashdata('tq_old_email');
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

<?php
/* TQ-LOGIN-GATE — البوابة تسافر مع الطلب.
   كانت زينة معلنة: «عرض لا قرار» — أربع بطاقات تبدل شكلها ولا تبدل
   شيئا في الخادم، فمن اختار «معلم» ودخل بحساب طالب لا يقال له شيء.
   وهي الآن حقل يرسل، و`validate_login` يقابله بالدور الحقيقي: يطابق
   فيمضي، ويخالف فيدخل صاحبه إلى لوحته **ويقال له لماذا**.

   والدخول لا يمنع أبدا باختيار خاطئ: من كتب بريده وكلمته الصحيحين
   يدخل. البوابة توجه وتشرح، ولا تقف بابا خامسا يرد الناس. */
$tq_gates = array(
  'student' => array('طالب',    'i-cap',     'أتعلم على المنصة'),
  'teacher' => array('معلم',   'i-teacher', 'أدرس على المنصة'),
  'parent'  => array('ولي أمر', 'i-users',   'أتابع أبنائي'),
);
/* و«الإدارة» ليست بوابة تعرض: حسابها ينشأ من داخل المنصة ولا يسجل من
   الموقع العام، فبطاقتها تعد بابا رابعا لا يخص أحدا ممن يقرأ الصفحة —
   وأربع بطاقات تصير صفين يزاحمان النموذج. والمسؤول يدخل من النموذج
   نفسه ببريده وكلمته، و`tq_gate` يصله فارغا فلا يقابل بدور — ثم
   يوجهه `validate_login` إلى لوحته كما كان. */
/* الاختيار الصريح وحده يحاسب عليه: «طالب» هي حالة العرض الأولى لا
   قرارا اتخذه أحد. فلو أرسلت كما هي لقرأ كل معلم يفتح `/login` مجردا
   «دخلت من بوابة طالب» في كل مرة — تنبيه عن اختيار لم يختره.
   فالحقل يبقى فارغا حتى يأتي `?as=` أو تنقر بطاقة. */
$tq_as     = (string) $this->input->get('as');
/* TQ-AUTH-NEXT — الوجهة تعبر الصفحة كما تعبر البوابة.
   من ضغط «اشترك» بلا جلسة يصل الى هنا بـ`?next=checkout/<code>?cycle=…`،
   ولم يكن في الصفحة ما يحمله: النموذج يرسل بلا وجهة، وبطاقات البوابة
   روابط تعيد بناء الرابط فتسقطها. فيهبط من دخل في لوحته وقد ترك شراءه.
   والتصفية في `tqs_safe_next()` وحدها. */
$tq_next = tqs_safe_next($this->input->get('next'));
if ($tq_next === '') $tq_next = tqs_safe_next($this->session->userdata('tq_next'));
$tq_next_q = $tq_next !== '' ? '&next=' . rawurlencode($tq_next) : '';
$tq_chosen = isset($tq_gates[$tq_as]);
if (!$tq_chosen) { $tq_as = 'student'; }
?>
      <?php /* البطاقات روابط تعمل بلا سكربت؛ والسكربت يمنع الانتقال
                ويبدل محليا — فالنقر عليها بعد كتابة البريد وكلمة المرور
                كان يعيد تحميل الصفحة فيمسح ما كتب. */ ?>
      <div class="gate-picker gate-picker--login" id="loginGate"
           role="radiogroup" aria-label="نوع الحساب">
        <?php foreach ($tq_gates as $tq_k => $tq_g): ?>
          <a class="gate-card<?php echo $tq_as === $tq_k ? ' is-on' : ''; ?>"
             role="radio" aria-checked="<?php echo $tq_as === $tq_k ? 'true' : 'false'; ?>"
             data-gate="<?php echo $tq_k; ?>"
             href="<?php echo base_url('login'); ?>?as=<?php echo $tq_k . $tq_next_q; ?>">
            <svg aria-hidden="true"><use href="#<?php echo $tq_g[1]; ?>"></use></svg>
            <b><?php echo $tq_g[0]; ?></b><span><?php echo $tq_g[2]; ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <form action="<?php echo site_url('login/validate_login'); ?>" method="post"
            id="login-form" data-tq-auth novalidate>
        <input type="hidden" name="tq_gate" id="loginGateValue"
               value="<?php echo $tq_chosen ? html_escape($tq_as) : ''; ?>">
<?php if ($tq_next !== ''): ?>
        <input type="hidden" name="tq_next" value="<?php echo html_escape($tq_next); ?>">
<?php endif; ?>
        <div class="form-stack">
          <div class="form-cell">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-mail"></use></svg>
              <span class="sr-only">البريد الإلكتروني</span>
              <input type="email" name="email" placeholder="البريد الإلكتروني" required
                     autocomplete="email" maxlength="50" autocapitalize="off" spellcheck="false"
                     value="<?php echo html_escape($tq_old_email); ?>">
            </label>
          </div>
          <div class="form-cell">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-lock"></use></svg>
              <span class="sr-only">كلمة المرور</span>
              <input type="password" name="password" id="loginPw" placeholder="كلمة المرور"
                     required autocomplete="current-password">
              <?php /* المهرب من الخطأ الصامت: من يكتب كلمة في حقل لا يراه
                      لا يعرف أخطأ في حرف أم في لغة لوحة المفاتيح. */ ?>
              <button class="pw-eye" type="button" data-tq-pw="loginPw"
                      aria-label="إظهار كلمة المرور" aria-pressed="false">
                <svg aria-hidden="true"><use href="#i-eye"></use></svg>
              </button>
            </label>
          </div>
        </div>

        <label class="form-remember">
          <input type="checkbox" name="remember_me" value="1">
          <span>تذكرني على هذا الجهاز</span>
        </label>
        <button class="btn btn--primary btn--block" type="submit">دخول</button>
      </form>

      <p class="form-alt">
        <a href="<?php echo site_url('login/forgot_password_request'); ?>">نسيت كلمة المرور؟</a>
      </p>
      <p class="form-alt" id="loginSignupLine">ليس لديك حساب؟
        <a href="<?php echo base_url('sign_up'); ?>?as=<?php echo $tq_as; ?>"
           id="loginSignupLink">أنشئ حسابا مجانا</a></p>

      </div>
      <?php
      $tq_aside_h2 = 'منصة تعليمية سعودية';
      $tq_aside_points = array(
        array('i-cap',         'برامج متدرجة', 'مصممة وفق المناهج السعودية'),
        array('i-chart',       'متابعة تقدمك', 'تقارير دقيقة لك ولولي أمرك'),
        array('i-certificate', 'شهادات إتقان', 'تصدر عند اجتياز المحطات'),
      );
      include __DIR__ . '/site/site_authaside.php';
      ?>
    </div>
  </div>
</section>

<script>
/* بطاقات الدخول: تبديل في المكان لا انتقال.
   بلا هذا السكربت يبقى الرابط رابطا ويعمل — وهذا هو الأساس، والحقل
   المخفي يصل إلى الخادم بقيمة العنوان نفسها. */
(function () {
  var box = document.getElementById('loginGate');
  if (!box) return;
  var link  = document.getElementById('loginSignupLink');
  var field = document.getElementById('loginGateValue');

  box.addEventListener('click', function (e) {
    var card = e.target.closest('.gate-card');
    if (!card) return;
    e.preventDefault();
    var v = card.dataset.gate;
    box.querySelectorAll('.gate-card').forEach(function (c) {
      var on = c.dataset.gate === v;
      c.classList.toggle('is-on', on);
      c.setAttribute('aria-checked', String(on));
    });
    if (field) field.value = v;              /* ما يقرؤه الخادم */
    if (link) link.href = link.href.replace(/\?as=.*$/, '') + '?as=' + v;
    /* والعنوان يتبع الاختيار كي لا يعود التحديث ببوابة أخرى */
    try { history.replaceState(null, '', '?as=' + v); } catch (err) {}
  });

  /* المسافة والإدخال يفعلان ما تفعله النقرة على عنصر دوره `radio` */
  box.addEventListener('keydown', function (e) {
    if (e.key !== ' ' && e.key !== 'Enter') return;
    var card = e.target.closest('.gate-card');
    if (!card) return;
    e.preventDefault();
    card.click();
  });
})();
</script>
