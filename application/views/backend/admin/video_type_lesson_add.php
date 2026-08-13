<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="system-video">
<input type="hidden" name="lesson_provider" value="system_video">

<div class="tqa-field">
    <span class="tqa-field__label">ملف الفيديو <span class="tqa-field__req" aria-hidden="true">*</span></span>
    <div class="tqa-file">
        <input type="file" id="system_video_file" name="system_video_file" accept="video/*" required data-tqa-file>
        <label class="tqa-file__btn" for="system_video_file"><?php echo tq_icon('upload', 16); ?> اختر ملفا</label>
        <span class="tqa-file__name" data-tqa-file-name>لم تختر ملفا بعد</span>
    </div>
    <?php /* حدود الخادم تعلن قبل الرفع لا بعده: الملف الأكبر من الحد كان
             يرفع كاملا ثم يرد الخادم ردا فارغا بلا رسالة مفهومة. */ ?>
    <span class="tqa-field__hint">
        أقصى حجم للملف <span class="tq-ltr" dir="ltr"><?php echo html_escape(ini_get('upload_max_filesize')); ?></span>،
        وأقصى حجم للطلب <span class="tq-ltr" dir="ltr"><?php echo html_escape(ini_get('post_max_size')); ?></span>.
    </span>
</div>

<div class="tqa-field">
    <label class="tqa-field__label" for="system_video_file_duration">
        المدة <span class="tqa-field__req" aria-hidden="true">*</span>
    </label>
    <input class="tqa-input tqa-input--ltr" type="text" id="system_video_file_duration"
           name="system_video_file_duration" dir="ltr" value="00:00:00" placeholder="00:00:00" required>
</div>

<div class="tqa-field">
    <span class="tqa-field__label">ملف الترجمة</span>
    <div class="tqa-file">
        <input type="file" id="caption" name="caption" accept=".vtt" data-tqa-file>
        <label class="tqa-file__btn" for="caption"><?php echo tq_icon('file-text', 16); ?> اختر ملفا</label>
        <span class="tqa-file__name" data-tqa-file-name>صيغة <span class="tq-ltr" dir="ltr">.vtt</span> وحدها</span>
    </div>
</div>
