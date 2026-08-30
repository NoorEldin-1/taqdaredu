<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تعديل درس — يفتح في نافذة.
 *
 * أعيدت كتابته بهيكل `tqa-*`. وثلاثة أعطال منطقية أصلحت معه:
 *
 * TQ-LESSON-TYPE-OR — كانت شروط اختيار القالب مكتوبة هكذا:
 *
 *     if ($l['lesson_type'] == 'other' && $att == 'doc' || $att == 'pdf' || $att == 'txt')
 *
 * و`&&` تسبق `||` في الأسبقية، فالشرط يقرأ:
 *
 *     (lesson_type == 'other' && att == 'doc')  ||  att == 'pdf'  ||  att == 'txt'
 *
 * أي أن **أي** درس امتداده pdf أو txt يعرض نموذج المستند مهما كان نوعه.
 * وهي مكتوبة مرتين: في سطر العنوان وفي اختيار القالب. صار النوع يحسب
 * مرة واحدة في `$tq_kind` وتقارن قيمته.
 *
 * وحذفت أربعة `include` لملفات غير موجودة (`academy_cloud` · `amazon_s3`
 * · `wasabi_storage`) — انظر [lesson_add.php].
 */
$tq_lesson   = $this->crud_model->get_lessons('lesson', $param2)->row_array();
$tq_sections = $this->crud_model->get_section('course', $param3)->result_array();

if (!$tq_lesson) {
    echo t('<p class="tqa-note tqa-note--warn">لا درس بهذا المعرف — قد يكون حذف من نافذة أخرى.</p>');
    return;
}

/**
 * نوع الدرس يحسب مرة: [مفتاح القالب، الاسم المعروض].
 * الترتيب من الأخص إلى الأعم، وكل فرع يرد فورا.
 */
$tq_type = (string) $tq_lesson['lesson_type'];
$tq_vid  = strtolower((string) $tq_lesson['video_type']);
$tq_att  = strtolower((string) $tq_lesson['attachment_type']);

if ($tq_type === 'video') {
    switch ($tq_vid) {
        case 'youtube':      $tq_kind = array('youtube', t('فيديو يوتيوب')); break;
        case 'vimeo':        $tq_kind = array('vimeo', t('فيديو فيميو')); break;
        case 'html5':        $tq_kind = array('html5', t('رابط ملف مباشر')); break;
        case 'system':       $tq_kind = array('video', t('ملف فيديو')); break;
        case 'google_drive': $tq_kind = array('google_drive_video', t('فيديو جوجل درايف')); break;
        default:             $tq_kind = array('youtube', t('فيديو')); break;
    }
} elseif ($tq_type === 'audio') {
    $tq_kind = array('audio', t('ملف صوتي'));
} elseif ($tq_type === 'text' && $tq_att === 'description') {
    $tq_kind = array('text', t('نص'));
} elseif ($tq_type === 'other' && in_array($tq_att, array('doc', 'pdf', 'txt'), true)) {
    $tq_kind = array('document', t('مستند'));
} elseif ($tq_type === 'other' && $tq_att === 'img') {
    $tq_kind = array('image', t('صورة'));
} elseif ($tq_type === 'other' && $tq_att === 'iframe') {
    $tq_kind = array('iframe', t('تضمين خارجي'));
} else {
    $tq_kind = array('', t('غير معروف'));
}

/* القوالب الموجودة فعلا. */
$tq_partials = array(
    'youtube'            => 'youtube_type_lesson_edit.php',
    'vimeo'              => 'vimeo_type_lesson_edit.php',
    'html5'              => 'html5_type_lesson_edit.php',
    'video'              => 'video_type_lesson_edit.php',
    'audio'              => 'audio_type_lesson_edit.php',
    'google_drive_video' => 'google_drive_video_lesson_edit.php',
    'document'           => 'document_type_lesson_edit.php',
    'text'               => 'text_type_lesson_edit.php',
    'image'              => 'image_type_lesson_edit.php',
    'iframe'             => 'iframe_type_lesson_edit.php',
);

/* الشاشات الفرعية تقرأ `$lesson_details` بهذا الاسم. */
$lesson_details = $tq_lesson;
?>

<div class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('play', 18); ?></span>
    <span><?php echo t('نوع الدرس:'); ?> <strong><?php echo html_escape($tq_kind[1]); ?></strong> <?php echo t('— ولا يغير بعد الإنشاء.'); ?></span>
</div>

<form class="ajaxFormSubmission" method="post" enctype="multipart/form-data"
      action="<?php echo site_url('admin/lessons/' . (int) $param3 . '/edit/' . (int) $param2); ?>">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="course_id" value="<?php echo (int) $param3; ?>">

    <div class="tqa-field">
        <label class="tqa-field__label" for="lesson_title">
            <?php echo t('عنوان الدرس'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="lesson_title" name="title" required maxlength="190"
               value="<?php echo html_escape($tq_lesson['title']); ?>">
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="section_id">
            <?php echo t('القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <select class="tqa-select" id="section_id" name="section_id" required>
            <?php foreach ($tq_sections as $tq_s): ?>
                <option value="<?php echo (int) $tq_s['id']; ?>"
                    <?php echo (int) $tq_lesson['section_id'] === (int) $tq_s['id'] ? 'selected' : ''; ?>>
                    <?php echo html_escape($tq_s['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php
    if ($tq_kind[0] !== '' && isset($tq_partials[$tq_kind[0]])) {
        include $tq_partials[$tq_kind[0]];
    }
    ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="lesson_summary"><?php echo t('ملخص الدرس'); ?></label>
        <textarea class="tqa-textarea" id="lesson_summary" name="summary" rows="3"><?php
            echo html_escape(htmlspecialchars_decode_($tq_lesson['summary'])); ?></textarea>
    </div>

    <div class="tqa-prefrow">
        <div class="tqa-prefrow__main">
            <label class="tqa-prefrow__title" for="free_lesson"><?php echo t('درس معاينة مجاني'); ?></label>
            <span class="tqa-prefrow__hint"><?php echo t('يفتح لغير المشتركين.'); ?></span>
        </div>
        <div class="tqa-prefrow__end">
            <span class="tqa-switch">
                <input type="checkbox" id="free_lesson" name="free_lesson" value="1"
                       <?php echo (int) $tq_lesson['is_free'] === 1 ? 'checked' : ''; ?>>
                <span class="tqa-switch__track" aria-hidden="true"></span>
            </span>
        </div>
    </div>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary tqa-btn--block formSubmissionBtn" type="submit">
            <?php echo tq_icon('check', 16); ?> احفظ التعديل
        </button>
    </div>
</form>

<?php include 'tqa_file_js.php'; ?>
<?php include 'tqa_lesson_form_js.php'; ?>
