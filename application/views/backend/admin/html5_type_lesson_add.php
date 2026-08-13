<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="video-url">
<input type="hidden" name="lesson_provider" value="html5">

<div class="tqa-field">
    <label class="tqa-field__label" for="html5_video_url">رابط الملف</label>
    <input class="tqa-input tqa-input--ltr" type="url" id="html5_video_url" name="html5_video_url" dir="ltr"
           placeholder="https://example.com/lesson.mp4">
    <span class="tqa-field__hint">رابط مباشر ينتهي بـ <span class="tq-ltr" dir="ltr">.mp4</span>.</span>
</div>

<div class="tqa-field">
    <label class="tqa-field__label" for="html5_duration">المدة</label>
    <input class="tqa-input tqa-input--ltr" type="text" id="html5_duration" name="html5_duration"
           dir="ltr" value="00:00:00" placeholder="00:00:00">
</div>

<div class="tqa-field">
    <span class="tqa-field__label">صورة الدرس</span>
    <div class="tqa-file">
        <input type="file" id="thumbnail" name="thumbnail" accept="image/*" data-tqa-file>
        <label class="tqa-file__btn" for="thumbnail"><?php echo tq_icon('image', 16); ?> اختر صورة</label>
        <span class="tqa-file__name" data-tqa-file-name>المقاس المفضل ‎979 × 551‎</span>
    </div>
</div>

<div class="tqa-field">
    <span class="tqa-field__label">ملف الترجمة</span>
    <div class="tqa-file">
        <input type="file" id="caption" name="caption" accept=".vtt" data-tqa-file>
        <label class="tqa-file__btn" for="caption"><?php echo tq_icon('file-text', 16); ?> اختر ملفا</label>
        <span class="tqa-file__name" data-tqa-file-name>صيغة <span class="tq-ltr" dir="ltr">.vtt</span> وحدها</span>
    </div>
</div>
