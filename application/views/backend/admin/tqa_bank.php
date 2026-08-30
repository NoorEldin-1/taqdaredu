<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * بيانات التحويل البنكي — وجهة المال.
 *
 * كانت `offline_bank_information` في Academy تحمل نصها الإنجليزي
 * الافتراضي «Enter your bank information»، ولم تكن تعرض في أي صفحة.
 * فالطالب يقال له «حول قيمة الفاتورة» ولا يدل على حساب — وهذه هي
 * الفجوة الوحيدة التي كانت توقف كل عملية بيع.
 *
 * والحقول هنا **مفصولة** لا حقل نصي حر: الآيبان ينسخ بزر، ولا ينسخ من
 * فقرة مختلطة.
 *
 * TQ-BARE-FIELD — كانت هذه الشاشة تكتب `<label class="tqa-field">` وفيها
 * `<input>` **عاريا بلا صنف**. و`.tqa-field` غلاف لا حقل: يضبط الهامش
 * وحده. فالحقول كانت تخرج بمظهر المتصفح الافتراضي — مربعات ضيقة بعرض
 * عشرين محرفا وحد رمادي وخط النظام — وسط شاشة مبنية بهوية تقدر. صارت
 * `.tqa-input` و`.tqa-textarea` كما في بقية اللوحة.
 */
$tq_fields = array(
    'tq_bank_beneficiary' => array(t('اسم المستفيد'), t('كما هو مسجل في البنك حرفا بحرف — الاسم المخالف يرد الحوالة.'), 'rtl'),
    'tq_bank_iban'        => array(t('الآيبان'), t('يبدأ بـ SA ويتبعه اثنان وعشرون رقما.'), 'ltr'),
    'tq_bank_name'        => array(t('اسم البنك'), t('اختياري — يطمئن المحول قبل أن يلصق الرقم.'), 'rtl'),
);
$tq_note  = (string) get_settings('tq_bank_note');
$tq_ready = trim((string) get_settings('tq_bank_beneficiary')) !== ''
         && trim((string) get_settings('tq_bank_iban')) !== '';
?>

<?php tqa_head(t('بيانات التحويل البنكي'),
    t('تعرض في شاشة تأكيد الاشتراك وصفحة اشتراك الطالب، مع رقم الفاتورة مرجعا للحوالة.'),
    'bank'); ?>

<?php /* الحالة تعلن قبل الحقول: من يفتح الشاشة يريد أن يعرف أولا
         هل الحساب معروض للطلاب الآن أم لا. */ ?>
<div class="tqa-note <?php echo $tq_ready ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($tq_ready ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <?php if ($tq_ready): ?>
            الحساب معروض للطلاب الآن. أي تعديل هنا يظهر فورا في شاشة الدفع.
        <?php else: ?>
            <strong><?php echo t('لا يعرض شيء للطالب حاليا.'); ?></strong>
            ما دام «اسم المستفيد» أو «الآيبان» فارغا تظهر للطالب دعوة للتواصل بدلا من الحساب —
            وهو أصدق من حساب ناقص يحول إليه فيضيع.
        <?php endif; ?>
    </span>
</div>

<div class="tqa-card" style="max-inline-size:720px">
    <form method="post" action="<?php echo site_url('taqdar_admin/bank_save'); ?>">
        <?php echo tq_csrf(); ?>

        <?php foreach ($tq_fields as $tq_k => $tq_m): ?>
            <div class="tqa-field">
                <label class="tqa-field__label" for="f_<?php echo $tq_k; ?>">
                    <?php echo html_escape($tq_m[0]); ?>
                </label>
                <input class="tqa-input<?php echo $tq_m[2] === 'ltr' ? ' tqa-input--ltr' : ''; ?>"
                       type="text" id="f_<?php echo $tq_k; ?>" name="<?php echo $tq_k; ?>"
                       dir="<?php echo $tq_m[2]; ?>"
                       value="<?php echo html_escape(get_settings($tq_k)); ?>"
                       <?php if ($tq_k === 'tq_bank_iban'): ?>
                           placeholder="SA0000000000000000000000"
                           maxlength="34" autocomplete="off" spellcheck="false"
                       <?php endif; ?>>
                <span class="tqa-field__hint"><?php echo html_escape($tq_m[1]); ?></span>
            </div>
        <?php endforeach; ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_note"><?php echo t('ملاحظة تظهر تحت البيانات'); ?></label>
            <textarea class="tqa-textarea" id="f_note" name="tq_bank_note" rows="2"
                      placeholder="<?php echo te('مثال: تراجع الحوالات خلال يوم عمل واحد.'); ?>"><?php echo html_escape($tq_note); ?></textarea>
            <span class="tqa-field__hint"><?php echo t('اختيارية — تترك فارغة فلا تظهر.'); ?></span>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> احفظ البيانات
            </button>
        </div>
    </form>
</div>
