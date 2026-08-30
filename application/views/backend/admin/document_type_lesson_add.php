<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="tqa-field">
    <label class="tqa-field__label" for="lesson_type">
        <?php echo t('نوع المستند'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
    </label>
    <select class="tqa-select" id="lesson_type" name="lesson_type" required>
        <option value="other-pdf" selected><?php echo t('ملف PDF'); ?></option>
        <option value="other-doc"><?php echo t('مستند Word'); ?></option>
        <option value="other-txt"><?php echo t('ملف نصي'); ?></option>
    </select>
</div>

<div class="tqa-field">
    <span class="tqa-field__label"><?php echo t('الملف'); ?> <span class="tqa-field__req" aria-hidden="true">*</span></span>
    <div class="tqa-file">
        <input type="file" id="attachment" name="attachment" required data-tqa-file
               accept=".pdf,.doc,.docx,.txt">
        <label class="tqa-file__btn" for="attachment"><?php echo tq_icon('upload', 16); ?> <?php echo t('اختر ملفا'); ?></label>
        <span class="tqa-file__name" data-tqa-file-name><?php echo t('لم تختر ملفا بعد'); ?></span>
    </div>
</div>
