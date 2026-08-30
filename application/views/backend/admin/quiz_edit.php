<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تعديل اختبار — يفتح في نافذة.
 *
 * الشكل نفسه الذي في [quiz_add.php]. وما أصلح هنا:
 *
 * **`json_decode(...)['total_marks']` كانت تنادى ثلاث مرات على السلسلة
 * نفسها**، وبلا فحص على الأولى منها. فاختبار حقله `attachment` فارغ أو
 * غير صالح — وهو وارد في صفوف قديمة — يقرأ فهرسا من `null`: تحذير
 * PHP 8.2 يطبع فوق النافذة، وقيمة فارغة في الحقل. صار الفك مرة واحدة
 * وبقيم افتراضية.
 */
$tq_quiz     = $this->crud_model->get_lessons('lesson', $param2)->row_array();
$tq_sections = $this->crud_model->get_section('course', $param3)->result_array();

if (!$tq_quiz) {
    echo t('<p class="tqa-note tqa-note--warn">لا اختبار بهذا المعرف — قد يكون حذف من نافذة أخرى.</p>');
    return;
}

$tq_meta = json_decode((string) $tq_quiz['attachment'], true);
if (!is_array($tq_meta)) $tq_meta = array();
$tq_meta += array('total_marks' => 0, 'pass_mark' => 0, 'drip_content_for_passing_rule' => 'not_applicable');
?>

<form action="<?php echo site_url('admin/quizes/' . (int) $param3 . '/edit/' . (int) $param2); ?>" method="post">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="title">
            <?php echo t('عنوان الاختبار'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="title" name="title" required maxlength="190"
               value="<?php echo html_escape($tq_quiz['title']); ?>">
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="section_id">
            <?php echo t('القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <select class="tqa-select" id="section_id" name="section_id" required>
            <?php foreach ($tq_sections as $tq_s): ?>
                <option value="<?php echo (int) $tq_s['id']; ?>"
                    <?php echo (int) $tq_quiz['section_id'] === (int) $tq_s['id'] ? 'selected' : ''; ?>>
                    <?php echo html_escape($tq_s['title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="tqa-fieldgrid">
        <div class="tqa-field">
            <label class="tqa-field__label" for="total_marks">
                <?php echo t('الدرجة الكلية'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
            </label>
            <input class="tqa-input tqa-input--ltr" type="number" id="total_marks" name="total_marks"
                   min="1" required dir="ltr" value="<?php echo html_escape($tq_meta['total_marks']); ?>">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="pass_mark">
                <?php echo t('درجة النجاح'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
            </label>
            <input class="tqa-input tqa-input--ltr" type="number" id="pass_mark" name="pass_mark"
                   min="0" required dir="ltr" value="<?php echo html_escape($tq_meta['pass_mark']); ?>">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="quiz_duration"><?php echo t('مدة الاختبار'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="quiz_duration" name="quiz_duration"
                   dir="ltr" placeholder="00:00:00"
                   value="<?php echo html_escape($tq_quiz['duration']); ?>">
            <span class="tqa-field__hint"><span class="tq-ltr" dir="ltr">00:00:00</span> <?php echo t('تعني بلا مؤقت.'); ?></span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="number_of_quiz_retakes"><?php echo t('عدد المحاولات الإضافية'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="number" id="number_of_quiz_retakes"
                   name="number_of_quiz_retakes" min="0" max="50" dir="ltr"
                   value="<?php echo (int) $tq_quiz['quiz_attempt']; ?>">
            <span class="tqa-field__hint"><?php echo t('صفر يعني محاولة واحدة لا غير.'); ?></span>
        </div>
    </div>

    <fieldset class="tqa-field">
        <legend class="tqa-field__label"><?php echo t('قاعدة الانتقال إلى الدرس التالي'); ?></legend>
        <span class="tqa-field__hint" style="margin-block-end:var(--tq-space-s)">
            <?php echo t('لا أثر لها ما لم تكن «الإتاحة التدريجية» مفعلة في إعدادات الكورس.'); ?>
        </span>

        <div class="tqa-stack">
            <label class="tqa-check">
                <input type="radio" name="drip_content_for_passing_rule" value="not_applicable"
                       <?php echo $tq_meta['drip_content_for_passing_rule'] !== 'applicable' ? 'checked' : ''; ?>>
                <span><?php echo t('يكفي تسليم الاختبار — بأي درجة'); ?></span>
            </label>
            <label class="tqa-check">
                <input type="radio" name="drip_content_for_passing_rule" value="applicable"
                       <?php echo $tq_meta['drip_content_for_passing_rule'] === 'applicable' ? 'checked' : ''; ?>>
                <span><?php echo t('يلزم بلوغ درجة النجاح'); ?></span>
            </label>
        </div>
    </fieldset>

    <div class="tqa-field">
        <label class="tqa-field__label" for="quiz_summary"><?php echo t('تعليمات للطالب'); ?></label>
        <textarea class="tqa-textarea" id="quiz_summary" name="summary" rows="3"><?php
            echo html_escape($tq_quiz['summary']); ?></textarea>
    </div>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary tqa-btn--block" type="submit">
            <?php echo tq_icon('check', 16); ?> احفظ التعديل
        </button>
    </div>
</form>
