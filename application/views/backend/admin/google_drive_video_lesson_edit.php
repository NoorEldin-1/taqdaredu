<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<input type="hidden" name="lesson_type" value="video-url">
<input type="hidden" name="lesson_provider" value="google_drive">

<div class="tqa-field">
    <label class="tqa-field__label" for="google_drive_video_url">رابط الفيديو</label>
    <input class="tqa-input tqa-input--ltr" type="url" id="google_drive_video_url"
           name="google_drive_video_url" dir="ltr"
           value="<?php echo html_escape($lesson_details['video_url']); ?>"
           placeholder="https://drive.google.com/file/d/...">
</div>

<?php /* كان الحقل يحمل سمتي `value` اثنتين: المحفوظة ثم `00:00:00`.
         والمتصفح يأخذ الأولى ويتجاهل الثانية — أي أن المدة كانت تعرض
         صحيحة بالصدفة، وأي إعادة ترتيب تمحوها. */ ?>
<div class="tqa-field">
    <label class="tqa-field__label" for="google_drive_video_duration">المدة</label>
    <input class="tqa-input tqa-input--ltr" type="text" id="google_drive_video_duration"
           name="google_drive_video_duration" dir="ltr" placeholder="00:00:00"
           value="<?php echo html_escape($lesson_details['duration']); ?>">
</div>
