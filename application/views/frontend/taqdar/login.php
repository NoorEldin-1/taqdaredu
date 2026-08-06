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

<?php
/* الدور هنا **عرض لا قرار**: الوجهة بعد الدخول يحددها ما في الحساب،
   ومعلم اختار «طالب» يصل إلى بوابة المعلم كما كان. وفائدته أن رابط
   «أنشئ حسابا» يفتح البوابة التي يريدها بلا خطوة ثانية. */
$tq_as = ((string) $this->input->get('as') === 'teacher') ? 'teacher' : 'student';
?>
      <div class="gate-picker gate-picker--login" role="tablist" aria-label="نوع الحساب">
        <?php foreach (array(
          'student' => array('طالب',  'i-cap',     'أتعلم على المنصة'),
          'teacher' => array('معلم', 'i-teacher', 'أدرس على المنصة'),
        ) as $tq_k => $tq_g): ?>
          <a class="gate-card<?php echo $tq_as === $tq_k ? ' is-on' : ''; ?>"
             role="tab" aria-selected="<?php echo $tq_as === $tq_k ? 'true' : 'false'; ?>"
             href="<?php echo base_url('login'); ?>?as=<?php echo $tq_k; ?>">
            <svg aria-hidden="true"><use href="#<?php echo $tq_g[1]; ?>"></use></svg>
            <b><?php echo $tq_g[0]; ?></b><span><?php echo $tq_g[2]; ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <form action="<?php echo site_url('login/validate_login'); ?>" method="post" id="login-form">
        <label class="form-field">
          <svg aria-hidden="true"><use href="#i-mail"></use></svg>
          <span class="sr-only">البريد الإلكتروني</span>
          <input type="email" name="email" placeholder="البريد الإلكتروني" required autocomplete="email">
        </label>
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
        <label class="form-remember">
          <input type="checkbox" name="remember_me" value="1">
          <span>تذكرني على هذا الجهاز</span>
        </label>
        <button class="btn btn--primary btn--block" type="submit">دخول</button>
      </form>

      <p class="form-alt">
        <a href="<?php echo site_url('login/forgot_password_request'); ?>">نسيت كلمة المرور؟</a>
      </p>
      <p class="form-alt">ليس لديك حساب؟
        <a href="<?php echo base_url('sign_up'); ?>?as=<?php echo $tq_as; ?>">أنشئ حسابا مجانا</a></p>

      </div>
      <aside class="auth-aside" aria-hidden="true">
        <img src="<?php echo tq_site_asset('img/auth-panel.webp'); ?>" alt="" width="700" height="1050" loading="lazy" decoding="async">
        <div class="auth-aside__body">
          <h2>منصة تعليمية سعودية</h2>
          <ul class="auth-points">
            <li><svg aria-hidden="true"><use href="#i-cap"></use></svg>
                <span><b>برامج متدرجة</b>مصممة وفق المناهج السعودية</span></li>
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
