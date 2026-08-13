<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="video-url">
<input type="hidden" name="lesson_provider" value="html5">

<div class="tqa-field">
    <label class="tqa-field__label" for="html5_video_url">رابط الملف</label>
    <input class="tqa-input tqa-input--ltr" type="url" id="html5_video_url" name="html5_video_url" dir="ltr"
           value="<?php echo html_escape($lesson_details['video_url']); ?>"
           placeholder="https://example.com/lesson.mp4">
</div>

<div class="tqa-field">
    <label class="tqa-field__label" for="html5_duration">المدة</label>
    <input class="tqa-input tqa-input--ltr" type="text" id="html5_duration" name="html5_duration" dir="ltr"
           value="<?php echo html_escape($lesson_details['duration']); ?>" placeholder="00:00:00">
</div>

<div class="tqa-field">
    <span class="tqa-field__label">صورة الدرس</span>
    <div class="tqa-file">
        <input type="file" id="thumbnail" name="thumbnail" accept="image/*" data-tqa-file>
        <label class="tqa-file__btn" for="thumbnail"><?php echo tq_icon('image', 16); ?> استبدل الصورة</label>
        <span class="tqa-file__name" data-tqa-file-name>اتركه فارغا لإبقاء الصورة الحالية</span>
    </div>
</div>

<div class="tqa-field">
    <span class="tqa-field__label">ملف الترجمة</span>
    <div class="tqa-file">
        <input type="file" id="caption" name="caption" accept=".vtt" data-tqa-file>
        <label class="tqa-file__btn" for="caption"><?php echo tq_icon('file-text', 16); ?> استبدل الملف</label>
        <span class="tqa-file__name" data-tqa-file-name>اتركه فارغا لإبقاء الملف الحالي</span>
    </div>
</div>

<?php include '_tq_mobile_carry.php'; ?>
