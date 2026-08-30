<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إضافة قسم — يفتح في نافذة.
 *
 * حذفت منه كتلتان معلقتان بـ`<!-- -->` منذ القالب الأصلي: «خطة الدراسة»
 * و«قيود الخطة»، ومعهما `daterangepicker` وورقة أنماط لأربعة أسطر.
 * شيفرة معلقة تقرأ ميزة موجودة وليست موجودة.
 */
?>
<form action="<?php echo site_url('admin/sections/' . (int) $param2 . '/add'); ?>" method="post">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="section_title">
            <?php echo t('عنوان القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" name="title" id="section_title" required maxlength="190"
               placeholder="<?php echo te('مثال: الوحدة الأولى — الأعداد'); ?>">
        <span class="tqa-field__hint"><?php echo t('القسم وعاء الدروس، ويظهر عنوانه للطالب في صفحة الكورس.'); ?></span>
    </div>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary tqa-btn--block" type="submit">
            <?php echo tq_icon('plus', 16); ?> <?php echo t('أضف القسم'); ?>
        </button>
    </div>
</form>
