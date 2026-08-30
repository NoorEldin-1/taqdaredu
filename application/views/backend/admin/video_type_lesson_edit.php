<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="system-video">
<input type="hidden" name="lesson_provider" value="system_video">

<div class="tqa-field">
    <span class="tqa-field__label"><?php echo t('ملف الفيديو'); ?></span>
    <div class="tqa-file">
        <input type="file" id="system_video_file" name="system_video_file" accept="video/*" data-tqa-file>
        <label class="tqa-file__btn" for="system_video_file"><?php echo tq_icon('upload', 16); ?> <?php echo t('استبدل الملف'); ?></label>
        <span class="tqa-file__name" data-tqa-file-name><?php echo t('اتركه فارغا لإبقاء الملف الحالي'); ?></span>
    </div>
    <span class="tqa-field__hint">
        <?php echo t('أقصى حجم للملف'); ?> <span class="tq-ltr" dir="ltr"><?php echo html_escape(ini_get('upload_max_filesize')); ?></span><?php echo t('، وأقصى حجم للطلب'); ?> <span class="tq-ltr" dir="ltr"><?php echo html_escape(ini_get('post_max_size')); ?></span>.
    </span>
</div>

<div class="tqa-field">
    <label class="tqa-field__label" for="system_video_file_duration">
        <?php echo t('المدة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
    </label>
    <input class="tqa-input tqa-input--ltr" type="text" id="system_video_file_duration"
           name="system_video_file_duration" dir="ltr" required placeholder="00:00:00"
           value="<?php echo html_escape($lesson_details['duration']); ?>">
</div>

<div class="tqa-field">
    <span class="tqa-field__label"><?php echo t('ملف الترجمة'); ?></span>
    <div class="tqa-file">
        <input type="file" id="caption" name="caption" accept=".vtt" data-tqa-file>
        <label class="tqa-file__btn" for="caption"><?php echo tq_icon('file-text', 16); ?> <?php echo t('استبدل الملف'); ?></label>
        <span class="tqa-file__name" data-tqa-file-name><?php echo t('اتركه فارغا لإبقاء الملف الحالي'); ?></span>
    </div>
</div>
