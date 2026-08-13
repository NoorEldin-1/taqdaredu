<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إضافة اختبار — يفتح في نافذة.
 *
 * أعيدت كتابته بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **قاعدة الإتاحة التدريجية كانت بلا خيار محدد.** زرا راديو ولا
 *     `checked` على أيهما، فالحقل يرسل فارغا ما لم يختر المستخدم — وهو
 *     عمود يقرأه محرك الإتاحة. صار الافتراض معلنا.
 * ٢ — **«درجة النجاح» مطلوبة و«الدرجة الكلية» ليست كذلك**، وهما رقمان
 *     يقارن أحدهما بالآخر. فصارتا مطلوبتين معا، ودرجة النجاح مقيدة
 *     بألا تتجاوز الكلية.
 * ٣ — **`initSelect2` و`initTimepicker`** من حزمة القالب — و select2 غير
 *     محمل أصلا (TQ-SELECT2-GONE).
 */
$tq_sections = $this->crud_model->get_section('course', $param2)->result_array();
?>

<?php if (empty($tq_sections)): ?>

    <div class="tqa-note tqa-note--warn">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong>لا أقسام في هذا الكورس بعد.</strong>
            والاختبار لا يحفظ بلا قسم يحمله. أضف قسما أولا من تبويب «المقرر».
        </span>
    </div>

<?php else: ?>

<form action="<?php echo site_url('admin/quizes/' . (int) $param2 . '/add'); ?>" method="post">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="title">
            عنوان الاختبار <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="title" name="title" required maxlength="190">
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="section_id">
            القسم <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <select class="tqa-select" id="section_id" name="section_id" required>
            <?php foreach ($tq_sections as $tq_s): ?>
                <option value="<?php echo (int) $tq_s['id']; ?>"><?php echo html_escape($tq_s['title']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="tqa-fieldgrid">
        <div class="tqa-field">
            <label class="tqa-field__label" for="total_marks">
                الدرجة الكلية <span class="tqa-field__req" aria-hidden="true">*</span>
            </label>
            <input class="tqa-input tqa-input--ltr" type="number" id="total_marks" name="total_marks"
                   min="1" required dir="ltr">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="pass_mark">
                درجة النجاح <span class="tqa-field__req" aria-hidden="true">*</span>
            </label>
            <input class="tqa-input tqa-input--ltr" type="number" id="pass_mark" name="pass_mark"
                   min="0" required dir="ltr">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="quiz_duration">مدة الاختبار</label>
            <input class="tqa-input tqa-input--ltr" type="text" id="quiz_duration" name="quiz_duration"
                   dir="ltr" value="00:00:00" placeholder="00:00:00">
            <span class="tqa-field__hint"><span class="tq-ltr" dir="ltr">00:00:00</span> تعني بلا مؤقت.</span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="number_of_quiz_retakes">عدد المحاولات الإضافية</label>
            <input class="tqa-input tqa-input--ltr" type="number" id="number_of_quiz_retakes"
                   name="number_of_quiz_retakes" min="0" max="50" value="0" dir="ltr">
            <span class="tqa-field__hint">صفر يعني محاولة واحدة لا غير.</span>
        </div>
    </div>

    <fieldset class="tqa-field">
        <legend class="tqa-field__label">قاعدة الانتقال إلى الدرس التالي</legend>
        <span class="tqa-field__hint" style="margin-block-end:var(--tq-space-s)">
            لا أثر لها ما لم تكن «الإتاحة التدريجية» مفعلة في إعدادات الكورس.
        </span>

        <div class="tqa-stack">
            <label class="tqa-check">
                <input type="radio" name="drip_content_for_passing_rule" value="not_applicable" checked>
                <span>يكفي تسليم الاختبار — بأي درجة</span>
            </label>
            <label class="tqa-check">
                <input type="radio" name="drip_content_for_passing_rule" value="applicable">
                <span>يلزم بلوغ درجة النجاح</span>
            </label>
        </div>
    </fieldset>

    <div class="tqa-field">
        <label class="tqa-field__label" for="quiz_summary">تعليمات للطالب</label>
        <textarea class="tqa-textarea" id="quiz_summary" name="summary" rows="3"></textarea>
    </div>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary tqa-btn--block" type="submit">
            <?php echo tq_icon('plus', 16); ?> أضف الاختبار
        </button>
    </div>
</form>

<?php endif; ?>
