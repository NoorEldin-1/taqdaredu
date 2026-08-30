<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تعديل عنوان قسم مخصص — يفتح في نافذة.
 * كان بالإنجليزية وبلا توكن CSRF وبلا فحص أن القسم موجود.
 */
$tq_f = $this->db->where('id', (int) $param2)->get('custom_fields')->row_array();

if (!$tq_f) {
    echo t('<p class="tqa-note tqa-note--warn">لا قسم بهذا المعرف — قد يكون حذف من نافذة أخرى.</p>');
    return;
}
?>
<form method="post" action="<?php echo site_url('admin/custom_field_section_update/' . (int) $tq_f['id']); ?>">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="custom_title">
            <?php echo t('عنوان القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="custom_title" name="custom_title" required maxlength="190"
               value="<?php echo html_escape($tq_f['custom_title']); ?>">
        <span class="tqa-field__hint"><?php echo t('يظهر عنوانا فوق هذه الكتلة في صفحة الكورس العامة.'); ?></span>
    </div>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary tqa-btn--block" type="submit">
            <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ التعديل'); ?>
        </button>
    </div>
</form>
