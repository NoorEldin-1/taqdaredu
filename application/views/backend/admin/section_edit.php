<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تعديل قسم — يفتح في نافذة.
 * الشكل نفسه الذي في [section_add.php]، وحذفت منه الكتل المعلقة نفسها.
 */
$tq_section = $this->crud_model->get_section('section', $param2)->row_array();

if (!$tq_section) {
    echo '<p class="tqa-note tqa-note--warn">لا قسم بهذا المعرف — قد يكون حذف من نافذة أخرى.</p>';
    return;
}
?>
<form action="<?php echo site_url('admin/sections/' . (int) $param3 . '/edit/' . (int) $param2); ?>" method="post">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="section_title">
            عنوان القسم <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" name="title" id="section_title" required maxlength="190"
               value="<?php echo html_escape($tq_section['title']); ?>">
    </div>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary tqa-btn--block" type="submit">
            <?php echo tq_icon('check', 16); ?> احفظ التعديل
        </button>
    </div>
</form>
