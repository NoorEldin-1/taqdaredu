<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="video-url">
<input type="hidden" name="lesson_provider" value="google_drive">

<div class="tqa-field">
    <label class="tqa-field__label" for="google_drive_video_url"><?php echo t('رابط الفيديو'); ?></label>
    <input class="tqa-input tqa-input--ltr" type="url" id="google_drive_video_url"
           name="google_drive_video_url" dir="ltr" placeholder="https://drive.google.com/file/d/...">
    <span class="tqa-field__hint"><?php echo t('يتطلب تفعيل خدمة درايف في مفتاح جوجل بإعدادات المنصة.'); ?></span>
</div>

<div class="tqa-field">
    <label class="tqa-field__label" for="google_drive_video_duration"><?php echo t('المدة'); ?></label>
    <input class="tqa-input tqa-input--ltr" type="text" id="google_drive_video_duration"
           name="google_drive_video_duration" dir="ltr" value="00:00:00" placeholder="00:00:00">
</div>
