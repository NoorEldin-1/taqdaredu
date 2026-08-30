<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إضافة درس — يفتح في نافذة.
 *
 * أعيدت كتابته بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **حذفت أنواع لا قوالب لها.** `academy_cloud` و`amazon-s3` و
 *     `wasabi-storage` و`wasabi-s3` — أربعة `include` لملفات غير موجودة
 *     في هذا المستودع. و`include` لملف غائب **تحذير لا خطأ**، فالنموذج
 *     كان يفتح بلا حقل مصدر: عنوان وقسم وملخص، ودرس يحفظ بلا فيديو.
 * ٢ — **لا أقسام = لا رسالة.** كان المنتقي يخرج فارغا حين لا أقسام في
 *     الكورس، والحفظ يرد خطأ قاعدة بيانات. صار يقول ما ينقص.
 * ٣ — **`initSummerNote` و`initSelect2` و`initTimepicker`** — ثلاث دوال
 *     من حزمة القالب تنادى على معرفات أكثرها غير موجود في هذه النافذة.
 */
$tq_course   = $this->crud_model->get_course_by_id($param2)->row_array();
$tq_sections = $this->crud_model->get_section('course', $param2)->result_array();
$tq_kind     = isset($param3) ? $param3 : 'youtube';

/* القوالب الموجودة فعلا — والمفتاح الغائب يسقط إلى «رابط يوتيوب». */
$tq_partials = array(
    'youtube'            => 'youtube_type_lesson_add.php',
    'vimeo'              => 'vimeo_type_lesson_add.php',
    'html5'              => 'html5_type_lesson_add.php',
    'video'              => 'video_type_lesson_add.php',
    'audio'              => 'audio_type_lesson_add.php',
    'google_drive_video' => 'google_drive_video_lesson_add.php',
    'document'           => 'document_type_lesson_add.php',
    'text'               => 'text_type_lesson_add.php',
    'image'              => 'image_type_lesson_add.php',
    'iframe'             => 'iframe_type_lesson_add.php',
);

$tq_names = array(
    'youtube'            => t('فيديو يوتيوب'),
    'vimeo'              => t('فيديو فيميو'),
    'html5'              => t('رابط ملف مباشر'),
    'video'              => t('ملف فيديو'),
    'audio'              => t('ملف صوتي'),
    'google_drive_video' => t('فيديو جوجل درايف'),
    'document'           => t('مستند'),
    'text'               => t('نص'),
    'image'              => t('صورة'),
    'iframe'             => t('تضمين خارجي'),
);
?>

<div class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('play', 18); ?></span>
    <span style="flex:1">
        <?php echo t('نوع الدرس:'); ?> <strong><?php echo html_escape($tq_names[$tq_kind] ?? $tq_kind); ?></strong>
    </span>
    <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
            onclick="showAjaxModal('<?php echo site_url('modal/popup/lesson_types/' . (int) $param2 . '/' . html_escape($tq_kind)); ?>', 'إضافة درس')">
        <?php echo t('غيره'); ?>
    </button>
</div>

<?php if (empty($tq_sections)): ?>

    <div class="tqa-note tqa-note--warn">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo t('لا أقسام في هذا الكورس بعد.'); ?></strong>
            <?php echo t('والدرس لا يحفظ بلا قسم يحمله. أغلق هذه النافذة وأضف قسما أولا من تبويب «المقرر».'); ?>
        </span>
    </div>

<?php else: ?>

<form class="ajaxFormSubmission" method="post" enctype="multipart/form-data"
      action="<?php echo site_url('admin/lessons/' . (int) $param2 . '/add'); ?>">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="course_id" value="<?php echo (int) $param2; ?>">

    <div class="tqa-field">
        <label class="tqa-field__label" for="lesson_title">
            <?php echo t('عنوان الدرس'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="lesson_title" name="title" required maxlength="190">
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="section_id">
            <?php echo t('القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <select class="tqa-select" id="section_id" name="section_id" required>
            <?php foreach ($tq_sections as $tq_s): ?>
                <option value="<?php echo (int) $tq_s['id']; ?>"><?php echo html_escape($tq_s['title']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php
    /* قالب النوع — والمفتاح الغائب يسقط إلى يوتيوب بدل `include` صامت
       على ملف غير موجود. */
    $tq_file = $tq_partials[$tq_kind] ?? $tq_partials['youtube'];
    include $tq_file;
    ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="lesson_summary"><?php echo t('ملخص الدرس'); ?></label>
        <textarea class="tqa-textarea" id="lesson_summary" name="summary" rows="3"></textarea>
    </div>

    <div class="tqa-prefrow">
        <div class="tqa-prefrow__main">
            <label class="tqa-prefrow__title" for="free_lesson"><?php echo t('درس معاينة مجاني'); ?></label>
            <span class="tqa-prefrow__hint"><?php echo t('يفتح لغير المشتركين — واحد أو اثنان يكفيان.'); ?></span>
        </div>
        <div class="tqa-prefrow__end">
            <span class="tqa-switch">
                <input type="checkbox" id="free_lesson" name="free_lesson" value="1">
                <span class="tqa-switch__track" aria-hidden="true"></span>
            </span>
        </div>
    </div>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary tqa-btn--block formSubmissionBtn" type="submit">
            <?php echo tq_icon('plus', 16); ?> أضف الدرس
        </button>
    </div>
</form>

<?php endif; ?>

<?php include 'tqa_file_js.php'; ?>
<?php include 'tqa_lesson_form_js.php'; ?>
