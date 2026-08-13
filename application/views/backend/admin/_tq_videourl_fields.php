<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * حقول رابط فيديو يقرأ مدته — مشتركة بين يوتيوب وفيميو، إضافة وتحريرا.
 *
 * كانت الكتلة منسوخة حرفا بحرف في أربعة ملفات. ومعها ثلاثة حقول
 * `d-none` معنونة «لتطبيق الجوال»: مخفية بصنف، لا يكتبها أحد، ولا يقرأ
 * الخادم منها إلا `html5_duration_for_mobile_application` وقيمتها
 * `00:00:00` أبدا. حذفت.
 */
$tq_url = isset($tq_url) ? $tq_url : '';
$tq_dur = isset($tq_dur) ? $tq_dur : '';
?>
<div class="tqa-field">
    <label class="tqa-field__label" for="video_url">رابط الفيديو</label>
    <input class="tqa-input tqa-input--ltr" type="url" id="video_url" name="video_url" dir="ltr"
           value="<?php echo html_escape($tq_url); ?>"
           placeholder="https://www.youtube.com/watch?v=...">
    <span class="tqa-field__hint" id="perloader" hidden>يقرأ مدة الفيديو…</span>
    <span class="tqa-field__hint" id="invalid_url" style="color:var(--tq-danger)" hidden>
        رابط غير صالح — المصدر يوتيوب أو فيميو.
    </span>
</div>

<div class="tqa-field">
    <label class="tqa-field__label" for="duration">المدة</label>
    <input class="tqa-input tqa-input--ltr" type="text" id="duration" name="duration" dir="ltr"
           value="<?php echo html_escape($tq_dur); ?>" placeholder="00:00:00">
    <span class="tqa-field__hint">تقرأ تلقائيا من الرابط، وتكتب بيد إن تعذر.</span>
</div>
