<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="other-img">

<div class="tqa-field">
    <span class="tqa-field__label"><?php echo t('الصورة'); ?></span>
    <div class="tqa-file">
        <input type="file" id="attachment" name="attachment" accept="image/*" data-tqa-file>
        <label class="tqa-file__btn" for="attachment"><?php echo tq_icon('image', 16); ?> <?php echo t('استبدل الصورة'); ?></label>
        <span class="tqa-file__name" data-tqa-file-name><?php echo t('اتركه فارغا لإبقاء الصورة الحالية'); ?></span>
    </div>
</div>
