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
/* (استعلام الصفوف أزيل مع لوحة التسجيل — لا حقول هنا بعد اليوم.) */
?>
<dialog class="tq-auth" data-tq-auth
        data-tq-base="<?php echo base_url(); ?>" aria-label="الدخول أو إنشاء حساب">
  <button class="tq-auth__x" type="button" data-tq-auth-close aria-label="إغلاق">
    <svg aria-hidden="true"><use href="#i-close"></use></svg>
  </button>

  <p class="tq-auth__lede">
    حسابك ثم تأكيد الاشتراك<span data-tq-auth-name hidden>: <b></b></span>
  </p>

  <?php /* TQ-SOCIAL — وأسرع باب يعرض أولا. من فتح هذه النافذة وهو
           يريد أن يدفع الآن لا يريد أن يملأ نموذجا، والزران يفتحان له
           حسابا ويعودان به إلى الباقة نفسها بدورتها (`?next=`).
           والشريك هو شريك `/login` و`/sign_up` نفسه — ونسخة ثالثة منه
           هنا تفترق عن أختيها عند أول مزود يضاف. */ ?>
  <?php
  $tq_soc_gate = 'student';
  $tq_soc_next = '';
  $tq_soc_lead = 'الأسرع';
  include __DIR__ . '/site_social_buttons.php';
  ?>

  <div class="tq-auth__tabs" role="group" aria-label="اختر طريقة المتابعة">
    <button type="button" data-tq-auth-tab="new"  aria-pressed="true">حساب جديد</button>
    <button type="button" data-tq-auth-tab="have" aria-pressed="false">لدي حساب</button>
  </div>

  <?php /* TQ-INSTANT — لوحة «حساب جديد» صارت رابطا إلى نموذج التسجيل
           المبسط الواحد: نسخة ثالثة من حقول التسجيل هنا كانت تفترق عن
           أختيها عند أول حقل يعدل (وقد افترقت فعلا: بقيت فيها تأكيد
           المرور وبريد ولي الأمر بعد حذفهما). والوجهة تركب الرابط
           (`?next=`) فيعود بعد التسجيل إلى ما كان يشتريه. */ ?>
  <div class="tq-auth__form" data-tq-auth-pane="new">
    <p class="tq-caption">
      حساب تقدر مجاني. قد يطلب تحديد مستوى من 5 إلى 10 أسئلة قبل الدفع، ثم تستكمل الباقة المختارة.
    </p>
    <a class="btn btn--primary btn--block" data-tq-auth-signup
       href="<?php echo base_url('sign_up'); ?>">أنشئ حسابا وتابع</a>
  </div>

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
