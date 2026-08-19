<?php
/**
 * اختيار قناة رمز التأكيد — جزء يدرج في لوحي المعلم وولي الأمر.
 *
 * ملف واحد لا نسختان: اللوحان يطلبان الشيء نفسه، ونسختان تفترقان عند
 * أول تعديل — كما افترق حقلا الجوال حتى صار لكل بوابة اسم
 * (`teacher_phone` · `parent_phone`).
 *
 * **ولكل بوابة اسم حقلها** (`teacher_otp_channel` · `parent_otp_channel`)
 * كما لكل بوابة اسم حقل جوالها — انظر TQ-PHONE-DUP في
 * [Login.php](../../../controllers/Login.php). والسبب هنا أشد: أزرار
 * `radio` باسم واحد تكون **مجموعة واحدة عند المتصفح** ولو تفرقت في
 * لوحين. فلو تسمى اللوحان باسم واحد لصار الأربعة مجموعة من أربعة،
 * ولا يعلم فيها إلا واحد — فيسقط تعليم لوح المعلم لأن لوح ولي الأمر
 * علم بعده في ترتيب الصفحة، ويصل الطلب بلا قناة مختارة أصلا.
 *
 * ولا يعرض حين لا يكون واتساب مضبوطا: خيار واحد ليس خيارا، وخيار لا
 * يعمل أسوأ من خيار لا يعرض. وحينها لا يرسل الحقل أصلا، فيقع المتحكم
 * على القناة الافتراضية — وهي البريد.
 *
 * @var bool   $tq_wa_otp هل واتساب مضبوط ومسموح للرموز؟
 * @var string $tq_chan_v ما اختير قبل الرفض
 * @var string $tq_otp_for البوابة التي يدرج فيها (للمعرفات وحدها)
 */
if (empty($tq_wa_otp)) return;

$tq_for = isset($tq_otp_for) ? (string) $tq_otp_for : 'x';
$tq_sel = (isset($tq_chan_v) && $tq_chan_v === 'whatsapp') ? 'whatsapp' : 'email';
?>
<div class="form-cell otp-pick">
  <span class="otp-pick__t" id="otpT-<?php echo $tq_for; ?>">
    <svg aria-hidden="true"><use href="#i-shield"></use></svg>
    أين نرسل رمز تأكيد الحساب؟
  </span>

  <div class="otp-pick__row" role="radiogroup" aria-labelledby="otpT-<?php echo $tq_for; ?>">
    <?php foreach (array(
      'email'    => array('i-mail',     'البريد الإلكتروني', 'إلى البريد الذي كتبته أعلاه'),
      'whatsapp' => array('i-whatsapp', 'واتساب',            'إلى رقم الجوال الذي كتبته'),
    ) as $tq_k => $tq_o): ?>
      <label class="otp-pick__opt<?php echo $tq_sel === $tq_k ? ' is-on' : ''; ?>">
        <input type="radio" name="<?php echo $tq_for; ?>_otp_channel" value="<?php echo $tq_k; ?>"
               <?php echo $tq_sel === $tq_k ? 'checked' : ''; ?>>
        <svg aria-hidden="true"><use href="#<?php echo $tq_o[0]; ?>"></use></svg>
        <span><b><?php echo $tq_o[1]; ?></b><small><?php echo $tq_o[2]; ?></small></span>
      </label>
    <?php endforeach; ?>
  </div>

  <p class="form-hint">
    لا يفتح حسابك قبل أن تكتب الرمز. وإن أخطأت في الوجهة فيمكنك تبديلها من
    الشاشة التالية.
  </p>
</div>
