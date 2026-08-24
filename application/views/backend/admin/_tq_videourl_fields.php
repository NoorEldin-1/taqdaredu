<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * حقول رابط فيديو يقرأ مدته — مشتركة بين يوتيوب وفيميو، إضافة وتحريرا.
 *
 * كانت الكتلة منسوخة حرفا بحرف في أربعة ملفات. ومعها ثلاثة حقول
 * `d-none` معنونة «لتطبيق الجوال»: مخفية بصنف، لا يكتبها أحد، ولا يقرأ
 * الخادم منها إلا `html5_duration_for_mobile_application` وقيمتها
 * `00:00:00` أبدا. حذفت.
 *
 * ═══ TQ-PROBE — ومن أين تقرأ المدة ═══
 *
 * كانت تقرأ من الخادم: `admin/ajax_get_video_details` ينادي
 * `Video_model::getVideoDetails()` وهو يطلب `googleapis.com/youtube/v3`
 * بمفتاح `youtube_api_key` من `settings`. **والمفتاح فارغ** — وكذلك
 * `vimeo_api_key`. فجوجل ترد 400، و`file_get_contents` ترد `false`،
 * فيرد `getVideoDetails()` `null`، فيطبع المتحكم لا شيء، فيكتب
 * الجافاسكربت `dur.value = ''` — أي أن قارئ المدة كان **يمحو ما كتبه
 * المسؤول بيده** ويسمي ذلك قراءة.
 *
 * والقراءة الآن في المتصفح، من [tq-duration-probe.js]، بالمشغل نفسه
 * الذي يشغل الدرس عند الطالب: بلا مفتاح واجهة برمجة يضبط ويسرب، وبلا
 * نداء شبكة خارج من الخادم في أثناء طلب المسؤول. وهو المحرك نفسه الذي
 * يخدم شاشة المنهج عند المعلم، فالشاشتان تقرآن رقما واحدا.
 */
$tq_url  = isset($tq_url) ? $tq_url : '';
$tq_dur  = isset($tq_dur) ? $tq_dur : '';

/* النوع يأتي من الملف الذي ضمن هذا: `lesson_provider` مطبوع فوقه. */
$tq_kind = isset($tq_kind) ? $tq_kind : 'youtube';
?>
<div class="tqa-field">
    <label class="tqa-field__label" for="video_url">رابط الفيديو</label>
    <input class="tqa-input tqa-input--ltr" type="url" id="video_url" name="video_url" dir="ltr"
           value="<?php echo html_escape($tq_url); ?>"
           data-tq-cur="url" data-tq-probe="<?php echo html_escape($tq_kind); ?>"
           placeholder="<?php echo $tq_kind === 'vimeo'
               ? 'https://vimeo.com/...' : 'https://www.youtube.com/watch?v=...'; ?>">
    <span class="tqa-field__hint" data-tq-probe-out hidden></span>
</div>

<div class="tqa-field">
    <label class="tqa-field__label" for="duration">المدة</label>
    <input class="tqa-input tqa-input--ltr" type="text" id="duration" name="duration" dir="ltr"
           data-tq-cur="duration"
           value="<?php echo html_escape($tq_dur); ?>" placeholder="00:00:00">
    <span class="tqa-field__hint">تقرأ تلقائيا من الرابط، وتكتب بيد إن تعذر.</span>
</div>

<?php
/* القارئ يحمل هنا لا في قالب الشاشة: هذه الكتلة هي التي تعلن الحاجة
   إليه، ونافذة الدرس تحقن بـAJAX فلا يصلها سكربت الصفحة الأم. */
tq_cur_probe_scripts();
