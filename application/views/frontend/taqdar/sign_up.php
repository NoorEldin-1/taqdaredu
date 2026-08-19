<?php
/**
 * إنشاء حساب — بوابتان.
 *
 * الطالب والمعلم لا يريدان الشيء نفسه ولا يسألان عنه. فبوابة لكل،
 * وحقول تخصه، ووجهة هي لوحته.
 *
 * **وبرنامج المعلم لا يفتح حسابا**: ينشئ طلبا ينتظر اعتماد الإدارة.
 * منصة يدرس فيها الغرباء بلا تحقق ليست منصة تعليمية.
 *
 * والتحقق طبقتان: هذه الصفحة تقول الخطأ تحت حقله قبل الإرسال،
 * و`Login::register` يعيد الفحص كله — فمن عطل السكربت لا يمر بشيء.
 */
$tq_h1   = 'حسابك في تقدر مجاني.';
$tq_lead = 'اختر بوابتك، وأنشئ حسابك في دقيقة.';
include __DIR__ . '/site/site_pagehero.php';

/* ما كتب قبل الرفض يعود: نموذج من أحد عشر حقلا يمسح كله لأن حرفا
   نقص في البريد يجعل المستخدم يبدأ من الصفر — والمرة الثانية يخطئ
   في غيره. المتحكم يودع القيم في `tq_old` عند كل رفض.
   وكلمتا المرور لا تعادان أبدا: قيمة سر في `value` تدخل ذاكرة
   المتصفح وسجل الصفحة. */
$tq_old = $this->session->flashdata('tq_old');
if (!is_array($tq_old)) { $tq_old = array(); }
$tq_v = function ($k) use ($tq_old) {
    return isset($tq_old[$k]) ? html_escape((string) $tq_old[$k]) : '';
};

/* ثلاث بوابات لا اثنتان: «ولي أمر» عادت. والمحرك يعرفها أصلا —
   `tq_role()` يرد `parent` لمن `tq_gate` عنده `parent`، ولوحة ولي
   الأمر وجداول الربط قائمة — وكان الناقص بابا يدخل منه.
   و«الإدارة» ليست منها: حساباتها تنشأ من داخل المنصة، وبوابة تسجيل
   عامة للإدارة باب لا يفتح لأحد.
   والبوابة المرفوضة تعود كما اختيرت لا كما في العنوان. */
$tq_gate = isset($tq_old['tq_gate']) ? (string) $tq_old['tq_gate'] : (string) $this->input->get('as');
$tq_gate = in_array($tq_gate, array('teacher', 'parent'), true) ? $tq_gate : 'student';

/* TQ-OTP — أين يصل رمز تأكيد الحساب؟
   السؤال يعرض للمعلم ولولي الأمر وحدهما، لأنهما وحدهما من يكتب بريدا
   وجوالا معا. والطالب لا يسأل عن جواله أصلا فقناته البريد — وإن كان
   دون الخامسة عشرة فالوجهة بريد ولي أمره، وذلك مكتوب تحت حقله.
   والقرار كله في `Taqdar_otp_model::signup_route()`؛ وهذا عرضه.
   وإن لم يكن واتساب مضبوطا لا يظهر السؤال أصلا: خيار لا يعمل أسوأ من
   خيار لا يعرض. */
$tq_ci_otp = &get_instance();
$tq_ci_otp->load->model('taqdar_wa_model');
$tq_wa_otp = $tq_ci_otp->taqdar_wa_model->otp_on();
$tq_chan_v = isset($tq_old['otp_channel']) ? (string) $tq_old['otp_channel'] : 'email';
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

      <div class="gate-picker" id="signupGate" role="radiogroup" aria-label="نوع الحساب">
        <?php foreach (array(
          'student' => array('طالب',    'i-cap',     'أتعلم على المنصة'),
          'teacher' => array('معلم',   'i-teacher', 'أدرس على المنصة'),
          'parent'  => array('ولي أمر', 'i-users',   'أتابع أبنائي'),
        ) as $tq_k => $tq_g): ?>
          <button class="gate-card<?php echo $tq_gate === $tq_k ? ' is-on' : ''; ?>" type="button"
                  role="radio" aria-checked="<?php echo $tq_gate === $tq_k ? 'true' : 'false'; ?>"
                  data-gate="<?php echo $tq_k; ?>">
            <svg aria-hidden="true"><use href="#<?php echo $tq_g[1]; ?>"></use></svg>
            <b><?php echo $tq_g[0]; ?></b><span><?php echo $tq_g[2]; ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <form action="<?php echo site_url('login/register'); ?>" method="post"
            enctype="multipart/form-data" id="signup-form" data-tq-auth novalidate>
        <input type="hidden" name="tq_gate" id="tqGate" value="<?php echo html_escape($tq_gate); ?>">

        <?php /* ── اسمك ──────────────────────────────────────────────── */ ?>
        <div class="form-sect">
          <h2 class="form-sect__t"><svg aria-hidden="true"><use href="#i-user"></use></svg>اسمك</h2>
          <div class="form-grid">
            <div class="form-cell">
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-user"></use></svg>
                <span class="sr-only">الاسم الأول</span>
                <input type="text" name="first_name" placeholder="الاسم الأول" required
                       autocomplete="given-name" minlength="2" maxlength="40"
                       value="<?php echo $tq_v('first_name'); ?>">
              </label>
            </div>
            <div class="form-cell">
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-user"></use></svg>
                <span class="sr-only">اسم العائلة</span>
                <input type="text" name="last_name" placeholder="اسم العائلة" required
                       autocomplete="family-name" minlength="2" maxlength="40"
                       value="<?php echo $tq_v('last_name'); ?>">
              </label>
            </div>
          </div>
        </div>

        <?php /* ── بيانات الدخول ─────────────────────────────────────── */ ?>
        <div class="form-sect">
          <h2 class="form-sect__t"><svg aria-hidden="true"><use href="#i-lock"></use></svg>بيانات الدخول</h2>

          <div class="form-cell">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-mail"></use></svg>
              <span class="sr-only">البريد الإلكتروني</span>
              <?php /* خمسون محرفا هو طول العمود في `users.email`؛ وما زاد
                      كان يقص عند الحفظ فيصير بريدا لا يصل إليه شيء. */ ?>
              <input type="email" name="email" placeholder="البريد الإلكتروني" required
                     autocomplete="email" maxlength="50" autocapitalize="off" spellcheck="false"
                     value="<?php echo $tq_v('email'); ?>">
            </label>
          </div>

          <div class="form-grid">
            <div class="form-cell">
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-lock"></use></svg>
                <span class="sr-only">كلمة المرور</span>
                <input type="password" name="password" id="signPw" placeholder="كلمة المرور" required
                       minlength="8" autocomplete="new-password" aria-describedby="pwHint"
                       data-msg="كلمة المرور ثمانية محارف على الأقل.">
                <button class="pw-eye" type="button" data-tq-pw="signPw"
                        aria-label="إظهار كلمة المرور" aria-pressed="false">
                  <svg aria-hidden="true"><use href="#i-eye"></use></svg>
                </button>
              </label>
              <?php /* التلميح تحت الحقل الذي يصفه لا تحت تأكيده: من يقرؤه
                      بعد كتابة الكلمتين يقرؤه بعد فوات الأوان.
                      وكان عنصرا مباشرا في `.form-grid` — أي خلية ثانية في
                      شبكة عمودين، فيقف بجانب كلمة المرور ويدفع التأكيد إلى
                      سطر وحده. وهو الآن داخل حقله فلا يزاحم أحدا. */ ?>
              <p class="form-hint" id="pwHint">ثمانية محارف على الأقل.</p>
            </div>
            <div class="form-cell">
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-lock"></use></svg>
                <span class="sr-only">تأكيد كلمة المرور</span>
                <input type="password" name="password_confirm" id="signPw2" required
                       placeholder="تأكيد كلمة المرور" minlength="8" autocomplete="new-password"
                       data-match="signPw" data-msg="كلمتا المرور غير متطابقتين.">
                <button class="pw-eye" type="button" data-tq-pw="signPw2"
                        aria-label="إظهار كلمة المرور" aria-pressed="false">
                  <svg aria-hidden="true"><use href="#i-eye"></use></svg>
                </button>
              </label>
            </div>
          </div>
        </div>

        <?php /* ── الطالب: العمر، وبريد ولي الأمر لمن هو دون الخامسة عشرة ── */ ?>
        <div class="form-sect gate-fields" data-for="student">
          <h2 class="form-sect__t"><svg aria-hidden="true"><use href="#i-cap"></use></svg>بيانات الطالب</h2>
          <div class="form-grid">
            <div class="form-cell">
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-user"></use></svg>
                <span class="sr-only">العمر</span>
                <?php /* مطلوب: المرحلة تشتق منه، وحساب بلا عمر لا يصنف. */ ?>
                <input type="number" name="age" id="tqAge" data-req="1"
                       autocomplete="off" placeholder="العمر" min="5" max="99" inputmode="numeric"
                       data-msg="اكتب عمرا بين 5 و99."
                       value="<?php echo $tq_v('age'); ?>">
              </label>
            </div>
            <div class="form-cell">
              <label class="form-field">
                <svg aria-hidden="true"><use href="#i-cap"></use></svg>
                <span class="sr-only">الصف الدراسي</span>
                <select name="grade_id">
                  <option value="">الصف الدراسي (اختياري)</option>
                  <?php
                  $tq_ci_g = &get_instance();
                  $tq_gr_old = (string) (isset($tq_old['grade_id']) ? $tq_old['grade_id'] : '');
                  foreach ($tq_ci_g->db->select('id, name_ar')->from('grades')->where('active', 1)
                                       ->order_by('`order`', 'ASC')->get()->result_array() as $tq_gr): ?>
                    <option value="<?php echo (int) $tq_gr['id']; ?>"
                      <?php echo $tq_gr_old === (string) $tq_gr['id'] ? ' selected' : ''; ?>><?php
                      echo html_escape($tq_gr['name_ar']); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
          </div>

          <div class="form-cell" id="tqGuardian" hidden>
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-mail"></use></svg>
              <span class="sr-only">بريد ولي الأمر</span>
              <input type="email" name="guardian_email" placeholder="بريد ولي الأمر"
                     autocomplete="email" maxlength="190" autocapitalize="off" spellcheck="false"
                     value="<?php echo $tq_v('guardian_email'); ?>">
            </label>
            <p class="form-hint">
              دون الخامسة عشرة نطلب بريد ولي أمرك، ونرسل إليه طلب موافقة قبل تفعيل الحساب.
            </p>
          </div>
        </div>

        <?php /* ── المعلم: طلب يراجع، لا حساب يفتح ── */ ?>
        <div class="form-sect gate-fields" data-for="teacher">
          <h2 class="form-sect__t"><svg aria-hidden="true"><use href="#i-teacher"></use></svg>بيانات المعلم</h2>

          <div class="form-cell">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-phone"></use></svg>
              <span class="sr-only">رقم الجوال</span>
              <?php /* بلا `pattern`: الخادم يطبع الرقم قبل فحصه فيقبل
                      `0501234567` و`+966 50 123 4567`، وكان النمط هنا
                      يرفض ما سيقبله — شرطان مختلفان لحقل واحد.

                      TQ-PHONE-DUP — والاسم `teacher_phone` لا `phone`:
                      لوحا المعلم وولي الأمر كلاهما في الصفحة دائما (يخفى
                      أحدهما بـ`hidden` لا يحذف)، وكلاهما كان يحمل
                      `name="phone"`. و`hidden` لا يمنع الإرسال — فالطلب
                      يحمل الحقلين، وPHP يبقي **الأخير**: حقل ولي الأمر
                      الفارغ. فكل تسجيل معلم كان يرد «رقم الجوال غير صحيح»
                      مهما كتب فيه، وهو أول ما يقابل من يريد أن يدرس هنا. */ ?>
              <input type="tel" name="teacher_phone" data-req="1" placeholder="05XXXXXXXX"
                     inputmode="tel" autocomplete="tel"
                     value="<?php echo $tq_v('phone'); ?>">
            </label>
          </div>

          <?php /* الحقل `div` لا `label`: `label` يلف `input[type=file]`
                  فيمرر النقرة إليه، والسكربت يناديه ثانية — فيفتح
                  المستكشف مرتين. والزر زر حقيقي يبلغه لوح المفاتيح. */ ?>
          <div class="form-cell">
            <div class="form-field form-field--file">
              <svg aria-hidden="true"><use href="#i-file"></use></svg>
              <span class="file-name" id="tqDocName">لم يختر ملف</span>
              <button type="button" class="file-btn" id="tqDocBtn">اختر ملفا…</button>
              <input type="file" name="document" id="tqDoc" data-req="1" tabindex="-1"
                     class="sr-only" accept=".pdf,.jpg,.jpeg,.png"
                     aria-label="مستند التعريف" data-msg="أرفق مستندا يثبت مؤهلك أو خبرتك.">
            </div>
            <p class="form-hint">مؤهل أو خبرة تدريسية — PDF أو صورة، حتى خمسة ميغابايت.</p>
          </div>

          <div class="form-cell">
            <label class="form-field form-field--full">
              <svg aria-hidden="true"><use href="#i-pen"></use></svg>
              <span class="sr-only">نبذة عن خبرتك</span>
              <textarea name="message" rows="3" maxlength="1000"
                        placeholder="نبذة عن خبرتك التدريسية وموادك"><?php
                echo $tq_v('message'); ?></textarea>
            </label>
          </div>

          <?php /* ── عينة الشرح ────────────────────────────────────────
                  فلو المعلم في وثيقة المنتج يشترطها مع البيانات: «تقديم
                  طلب انضمام (بيانات + عينة شرح 10 دقائق)». وكان النموذج
                  يقبل مستند مؤهل ونبذة وحدهما — فتراجع اللجنة ورقا ولا
                  ترى المتقدم يشرح، وهي تحكم على تدريس.

                  **ورابط لا ملف**: عشر دقائق فيديو مئات الميغابايتات،
                  ورفعها في نموذج تسجيل يسقط عند حد الخادم بلا رسالة
                  مفهومة — والمتقدم غالبا له تسجيل جاهز على يوتيوب أو
                  درايف. */ ?>
          <div class="form-cell">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-play"></use></svg>
              <span class="sr-only">رابط عينة الشرح</span>
              <input type="url" name="sample_url" data-req="1" dir="ltr"
                     placeholder="رابط عينة شرح — يوتيوب أو درايف"
                     inputmode="url" autocomplete="off" spellcheck="false" maxlength="500"
                     data-msg="ضع رابطا صحيحا لعينة شرحك."
                     value="<?php echo $tq_v('sample_url'); ?>">
            </label>
            <p class="form-hint">
              عشر دقائق تشرح فيها مفهوما واحدا من مادتك. تأكد أن الرابط يفتح لمن يملكه.
            </p>
          </div>

          <div class="form-cell">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-cap"></use></svg>
              <span class="sr-only">المادة والصف</span>
              <input type="text" name="subject_hint" maxlength="190"
                     placeholder="المادة والصف الذي تدرسه (اختياري)"
                     value="<?php echo $tq_v('subject_hint'); ?>">
            </label>
          </div>

          <?php $tq_otp_for = 'teacher'; include __DIR__ . '/_tq_otp_channel.php'; ?>

          <p class="form-hint form-hint--box">
            <svg aria-hidden="true"><use href="#i-shield"></use></svg>
            <span>طلبك تراجعه لجنة تحكيم داخلية: تشاهد عينتك، وتوثق هويتك ومؤهلك.
                  ونتواصل معك قبل تفعيل لوحتك. لن تستطيع الدخول إلى لوحة المعلم قبل الاعتماد.</span>
          </p>
        </div>

        <?php /* ── ولي الأمر: حساب يفتح فورا، والربط بعده ──────────────
                الربط لا يطلب هنا: `parent_links` يفتح بموافقة الطالب
                نفسه (ولها مشغلان في القاعدة)، فطلبه في شاشة التسجيل
                يعد بما لا يتم في هذه الخطوة. */ ?>
        <div class="form-sect gate-fields" data-for="parent">
          <h2 class="form-sect__t"><svg aria-hidden="true"><use href="#i-users"></use></svg>بيانات ولي الأمر</h2>

          <div class="form-cell">
            <label class="form-field">
              <svg aria-hidden="true"><use href="#i-phone"></use></svg>
              <span class="sr-only">رقم الجوال</span>
              <?php /* اسم مستقل عن حقل المعلم — انظر TQ-PHONE-DUP أعلاه. */ ?>
              <input type="tel" name="parent_phone" data-req="1" placeholder="05XXXXXXXX"
                     inputmode="tel" autocomplete="tel"
                     value="<?php echo $tq_v('phone'); ?>">
            </label>
            <p class="form-hint">نراسلك عليه في تنبيهات أبنائك المهمة.</p>
          </div>

          <?php $tq_otp_for = 'parent'; include __DIR__ . '/_tq_otp_channel.php'; ?>

          <p class="form-hint form-hint--box">
            <svg aria-hidden="true"><use href="#i-users"></use></svg>
            <span>بعد إنشاء حسابك تربط أبناءك من لوحتك. يصل الطالب طلب موافقة، ولا تفتح تقاريره قبل أن يوافق.</span>
          </p>
        </div>

        <div class="form-cell">
          <label class="form-consent">
            <input type="checkbox" name="accept_terms" value="1" required
                   data-msg="لا بد من الموافقة على الشروط وسياسة الخصوصية.">
            <span>أوافق على <a href="<?php echo base_url('terms'); ?>">الشروط والأحكام</a>
                  و<a href="<?php echo base_url('privacy'); ?>">سياسة الخصوصية</a>.</span>
          </label>
        </div>

        <button class="btn btn--primary btn--block" type="submit">إنشاء الحساب</button>
      </form>

      <p class="form-alt">لديك حساب؟
        <a href="<?php echo base_url('login'); ?>?as=<?php echo $tq_gate; ?>"
           id="signupLoginLink">سجل الدخول</a></p>
      </div>

      <?php
      $tq_aside_h2 = 'منصة تعليمية سعودية';
      $tq_aside_points = array(
        array('i-cap',         'وفق المنهج السعودي', 'برامج لكل صف ومادة'),
        array('i-chart',       'متابعة تقدمك',       'تقارير دقيقة لك ولولي أمرك'),
        array('i-certificate', 'شهادات إتقان',       'تصدر عند اجتياز المحطات'),
      );
      include __DIR__ . '/site/site_authaside.php';
      ?>
    </div>
  </div>
</section>

<script>
/* البوابة تبدل الحقول ووجهة الطلب.
   و`required` تركب وتفك مع البوابة: حقل مطلوب في لوح مطوي يرد الإرسال
   بلا أن يظهر سببه. والمصدر واحد — `data-req` على الحقل نفسه — بدل
   قائمة أسماء في السكربت كانت تنسى `age` فتفقده صفته كلما بدلت
   البوابة ذهابا وإيابا. */
(function () {
  var gate = document.getElementById('tqGate');
  var picker = document.getElementById('signupGate');
  var age = document.getElementById('tqAge');
  var guard = document.getElementById('tqGuardian');
  var loginLink = document.getElementById('signupLoginLink');
  if (!gate || !picker) return;

  function guardianCheck() {
    if (!guard) return;
    var n = age ? parseInt(String(age.value).replace(/[^0-9]/g, ''), 10) : NaN;
    var minor = gate.value === 'student' && !isNaN(n) && n > 0 && n < 15;
    guard.hidden = !minor;
    var g = guard.querySelector('input');
    if (!g) return;
    if (minor) {
      g.setAttribute('required', '');
    } else {
      g.removeAttribute('required');
      /* القيمة تمسح مع الحقل: بريد ولي أمر كتب ثم رفع العمر إلى ٢٠
         كان يبقى في الطلب ويحفظ في الحساب — بيانات شخص ثالث بلا سبب. */
      g.value = '';
      g.removeAttribute('aria-invalid');
      var box = g.closest('.form-field');
      if (box) box.classList.remove('form-field--invalid');
    }
  }

  function apply(v) {
    gate.value = v;
    picker.querySelectorAll('.gate-card').forEach(function (b) {
      var on = b.dataset.gate === v;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-checked', String(on));
    });
    document.querySelectorAll('.gate-fields').forEach(function (f) {
      var on = f.dataset.for === v;
      f.hidden = !on;
      f.querySelectorAll('[data-req]').forEach(function (el) {
        if (on) el.setAttribute('required', '');
        else el.removeAttribute('required');
      });
      /* حقول البوابة المطوية تعطل فلا ترسل أصلا: `hidden` يخفي ولا يمنع
         الإرسال، فكان طلب المعلم يحمل عمر الطالب وبريد ولي أمره ومستند
         رفعه ثم بدل بوابته — بيانات لا تخص الطلب، وملف يرفع بلا سبب.
         (والتصادم في اسم `phone` عولج بفصل الاسمين، فهذا احتياط ثان.) */
      f.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.disabled = !on;
      });
      /* رسائل بوابة انصرف عنها لا تبقى معلقة */
      if (!on) f.querySelectorAll('.field-err').forEach(function (m) { m.hidden = true; });
      if (!on) f.querySelectorAll('.form-field--invalid').forEach(function (m) {
        m.classList.remove('form-field--invalid');
      });
    });
    guardianCheck();
    if (loginLink) loginLink.href = loginLink.href.replace(/\?as=.*$/, '') + '?as=' + v;
  }

  picker.addEventListener('click', function (e) {
    var b = e.target.closest('.gate-card');
    if (b) apply(b.dataset.gate);
  });

  if (age) age.addEventListener('input', guardianCheck);

  /* حقل الملف: زر عربي واسم الملف المختار بدل «Choose File» */
  var doc = document.getElementById('tqDoc'), btn = document.getElementById('tqDocBtn'),
      nm = document.getElementById('tqDocName');
  if (doc && btn) {
    btn.addEventListener('click', function () { doc.click(); });
    doc.addEventListener('change', function () {
      var f = doc.files && doc.files[0];
      if (nm) {
        nm.textContent = f ? f.name : 'لم يختر ملف';
        nm.classList.toggle('is-set', !!f);
      }
      btn.textContent = f ? 'غير الملف' : 'اختر ملفا…';
    });
  }

  apply(gate.value);

  /* اختيار قناة الرمز: البطاقة تعلن اختيارها بالحد واللون معا — واللون
     وحده لا يراه كل أحد. و`:has()` تفعل هذا في CSS الحديث وحده، وهذه
     الصفحة تفتح على أجهزة قديمة كثيرة. */
  document.querySelectorAll('.otp-pick__row').forEach(function (row) {
    row.addEventListener('change', function () {
      row.querySelectorAll('.otp-pick__opt').forEach(function (opt) {
        var r = opt.querySelector('input[type=radio]');
        opt.classList.toggle('is-on', !!(r && r.checked));
      });
    });
  });
})();
</script>
