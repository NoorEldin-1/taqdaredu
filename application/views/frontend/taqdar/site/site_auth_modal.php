<?php
/**
 * TQ-PLAN-AUTH · نافذة الدخول أو التسجيل قبل الدفع — **مشتركة لا صفحية**.
 *
 * كانت مكتوبة في `site_plan.php` وحدها، فمن ضغط «اختر هذه الباقة» في
 * `/plans` — وهي الشاشة التي تعرض الباقات كلها وأكثر ما يضغط منها —
 * ينقل الى صفحة دخول كاملة يفقد فيها ما اختار. ونسخة ثانية منها هناك
 * تفترق عن اختها عند اول حقل يضاف. فصارت شريكا واحدا يطبع من ذيل
 * الموقع لكل زائر بلا جلسة: الصفحة التي تعرض رابط شراء غدا تجده
 * جاهزا بلا ان يتذكر كاتبها سطرا.
 *
 * وهي **عرض لا منطق**: النموذجان يرسلان الى `login/register` و
 * `login/validate_login` كما هما بحقولهما نفسها، وCSRF يحقن اليا في كل
 * `<form method=post>`. فلا مسار جديد ولا لمس لمنطق الدخول ولا للدفع.
 * وبلا سكربت يعمل الزر كما كان: الرابط يذهب الى `/checkout/<code>`
 * فيحول الى الدخول بـ`?next=` — والوجهة نفسها.
 *
 * TQ-AUTH-NEXT — والوجهة تسافر في حقل مخفي يكتبه السكربت من **الرابط
 * الذي ضغط** لا من ثابت في القالب: الدورة (`?cycle=`) والباقة كلتاهما
 * فيه، ومبدل الدورة يبدله. ويقرؤها `Login::validate_login()` ثم
 * `set_login_userdata()` عبر `url_history` — فالعودة قرار خادم، لا
 * `sessionStorage` يقرأ في صفحة لا تحمل `site.js` اصلا (لوحة الطالب).
 */
$tq_grades = array();
$tq_gq = $this->db->select('id, name_ar', false)->from('grades')
                  ->where('active', 1)->order_by('id', 'ASC')->get();
if ($tq_gq) $tq_grades = $tq_gq->result_array();
?>
<dialog class="tq-auth" data-tq-auth
        data-tq-base="<?php echo base_url(); ?>" aria-label="الدخول أو إنشاء حساب">
  <button class="tq-auth__x" type="button" data-tq-auth-close aria-label="إغلاق">
    <svg aria-hidden="true"><use href="#i-close"></use></svg>
  </button>

  <p class="tq-auth__lede">
    خطوة واحدة قبل الدفع<span data-tq-auth-name hidden>: <b></b></span>
  </p>

  <div class="tq-auth__tabs" role="group" aria-label="اختر طريقة المتابعة">
    <button type="button" data-tq-auth-tab="new"  aria-pressed="true">حساب جديد</button>
    <button type="button" data-tq-auth-tab="have" aria-pressed="false">لدي حساب</button>
  </div>

  <form class="tq-auth__form" data-tq-auth-pane="new"
        action="<?php echo site_url('login/register'); ?>" method="post">
    <input type="hidden" name="tq_gate" value="student">
    <input type="hidden" name="tq_next" value="" data-tq-auth-next>
    <div class="tq-auth__row">
      <label><span>الاسم الأول</span>
        <input type="text" name="first_name" required minlength="2" maxlength="40" autocomplete="given-name"></label>
      <label><span>اسم العائلة</span>
        <input type="text" name="last_name" required minlength="2" maxlength="40" autocomplete="family-name"></label>
    </div>
    <label><span>البريد الإلكتروني</span>
      <input type="email" name="email" required maxlength="50" autocomplete="email"></label>
    <div class="tq-auth__row">
      <label><span>كلمة المرور</span>
        <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
      <label><span>تأكيد كلمة المرور</span>
        <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password"></label>
    </div>
    <div class="tq-auth__row">
      <label><span>عمر الطالب</span>
        <input type="number" name="age" required min="5" max="99" inputmode="numeric"></label>
      <label><span>الصف</span>
        <select name="grade_id" required>
          <option value="">اختر الصف</option>
<?php foreach ($tq_grades as $tq_g): ?>
          <option value="<?php echo (int) $tq_g['id']; ?>"><?php echo html_escape($tq_g['name_ar']); ?></option>
<?php endforeach; ?>
        </select></label>
    </div>
    <label><span>بريد ولي الأمر <i>— يلزم إن كان عمر الطالب أقل من ١٥</i></span>
      <input type="email" name="guardian_email" maxlength="190" autocomplete="email"></label>
    <label class="tq-auth__terms">
      <input type="checkbox" name="accept_terms" value="1" required>
      <span>أوافق على <a href="<?php echo base_url('terms'); ?>" target="_blank" rel="noopener">الشروط والأحكام</a>
        و<a href="<?php echo base_url('privacy'); ?>" target="_blank" rel="noopener">سياسة الخصوصية</a></span>
    </label>
    <button class="btn btn--primary btn--block" type="submit">أنشئ الحساب وتابع</button>
    <p class="tq-caption tq-auth__note">
      سيصلك رمز تأكيد على بريدك. وبعد تأكيده تعود إلى هذه الباقة لإتمام الدفع.
    </p>
  </form>

  <form class="tq-auth__form" data-tq-auth-pane="have" hidden
        action="<?php echo site_url('login/validate_login'); ?>" method="post">
    <input type="hidden" name="tq_gate" value="student">
    <input type="hidden" name="tq_next" value="" data-tq-auth-next>
    <label><span>البريد الإلكتروني</span>
      <input type="email" name="email" required autocomplete="email"></label>
    <label><span>كلمة المرور</span>
      <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn--primary btn--block" type="submit">ادخل وتابع</button>
    <p class="tq-caption tq-auth__note">
      <a href="<?php echo base_url('forgot_password'); ?>">نسيت كلمة المرور؟</a>
    </p>
  </form>
</dialog>
