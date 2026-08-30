<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="system-audio">
<input type="hidden" name="lesson_provider" value="system_audio">

<div class="tqa-field">
    <span class="tqa-field__label"><?php echo t('الملف الصوتي'); ?> <span class="tqa-field__req" aria-hidden="true">*</span></span>
    <div class="tqa-file">
        <input type="file" id="system_audio_file" name="system_audio_file" accept="audio/*" required data-tqa-file>
        <label class="tqa-file__btn" for="system_audio_file"><?php echo tq_icon('upload', 16); ?> <?php echo t('اختر ملفا'); ?></label>
        <span class="tqa-file__name" data-tqa-file-name><?php echo t('لم تختر ملفا بعد'); ?></span>
    </div>
</div>

<div class="tqa-field">
    <label class="tqa-field__label" for="system_audio_file_duration">
        <?php echo t('المدة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
    </label>
    <input class="tqa-input tqa-input--ltr" type="text" id="system_audio_file_duration"
           name="system_audio_file_duration" dir="ltr" value="00:00:00" placeholder="00:00:00" required>
</div>
