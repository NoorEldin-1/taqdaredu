<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * بيانات التحويل البنكيّ — وجهةُ المال.
 *
 * كانت `offline_bank_information` في Academy تحمل نصّها الإنجليزيّ
 * الافتراضيّ «Enter your bank information»، ولم تكن تُعرَض في أيّ صفحة.
 * فالطالب يُقال له «حوّل قيمة الفاتورة» ولا يُدلّ على حساب — وهذه هي
 * الفجوة الوحيدة التي كانت توقف كلّ عملية بيع.
 *
 * والحقول هنا **مفصولة** لا حقلٌ نصّيّ حرّ: الآيبان يُنسخ بزرّ، ولا
 * يُنسَخ من فقرةٍ مختلطة.
 */
$tq_fields = array(
    'tq_bank_beneficiary' => array('اسم المستفيد', 'كما هو مسجَّل في البنك حرفًا بحرف — الاسم المخالف يردّ الحوالة.', 'rtl'),
    'tq_bank_iban'        => array('الآيبان', 'يبدأ بـ SA ويتبعه اثنان وعشرون رقمًا.', 'ltr'),
    'tq_bank_name'        => array('اسم البنك', 'اختياريّ — يطمئن المُحوِّل قبل أن يلصق الرقم.', 'rtl'),
);
$tq_note = (string) get_settings('tq_bank_note');
?>
<div class="tqa-wrap">
  <?php echo tqa_flash(); ?>
  <div class="tqa-head">
    <h1>بيانات التحويل البنكيّ</h1>
    <p class="tqa-lead">
      تُعرض في <strong>شاشة تأكيد الاشتراك</strong> و<strong>صفحة اشتراك الطالب</strong>
      مع رقم الفاتورة مرجعًا للحوالة.
      <strong>وما دام أحد الحقلين الأوّلين فارغًا لا يُعرَض شيء</strong> —
      يظهر للطالب بدلًا منه دعوةٌ للتواصل، لا حسابٌ ناقص.
    </p>
  </div>

  <form method="post" action="<?php echo site_url('taqdar_admin/bank_save'); ?>" class="tqa-card">
    <div class="tqa-grid">
      <?php foreach ($tq_fields as $tq_k => $tq_m): ?>
        <label class="tqa-field">
          <span class="tqa-label"><?php echo $tq_m[0]; ?></span>
          <input type="text" name="<?php echo $tq_k; ?>" dir="<?php echo $tq_m[2]; ?>"
                 value="<?php echo html_escape(get_settings($tq_k)); ?>"
                 placeholder="<?php echo $tq_k === 'tq_bank_iban' ? 'SA0000000000000000000000' : ''; ?>">
          <span class="tqa-hint"><?php echo $tq_m[1]; ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <label class="tqa-field">
      <span class="tqa-label">ملاحظة تظهر تحت البيانات</span>
      <textarea name="tq_bank_note" rows="2"
                placeholder="مثال: تُراجَع الحوالات خلال يوم عمل واحد."><?php echo html_escape($tq_note); ?></textarea>
      <span class="tqa-hint">اختيارية — تُترك فارغةً فلا تظهر.</span>
    </label>

    <div class="tqa-actions">
      <button type="submit" class="tqa-btn tqa-btn--primary">حفظ</button>
    </div>
  </form>
</div>
