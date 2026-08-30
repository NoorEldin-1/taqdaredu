<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الصف والمادة — بطاقة مشتركة بين إضافة الكورس وتحريره.
 *
 * TQ-COURSE-ORPHAN — الكورس في هذا التركيب وعاء دروس لا أكثر: جدول
 * `course` لا يحمل صفا ولا مادة، والكتالوج ومحرك الاشتراكات كلاهما
 * يقرأ من `paths` وحده (انظر [Taqdar_course_link_model.php]). فالكورس
 * الذي ينشأ من اللوحة وينشر **لا يظهر في «المواد والبرامج» ولا تفتحه
 * باقة ولا يصل إليه طالب**، ولا شيء في الشاشة كان يقول ذلك.
 *
 * وهذان الحقلان هما الربط: بهما يكتب صف `paths` مقابل، فيصير الكورس
 * مادة تعرض وتباع. وبغيرهما يبقى مسودة محتوى — وهو استعمال مشروع
 * (كورس يجهز على مهل)، فلا يمنعان الحفظ ولكن يقال ثمنهما صراحة.
 *
 * المتغيرات: `$tq_link` (من `link_of()`) — وتسقط إلى صفرين في الإضافة.
 */
$tq_link = isset($tq_link) && is_array($tq_link) ? $tq_link : array('grade_id' => 0, 'subject_id' => 0);
?>

<div class="tqa-card tqa-section">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('target', 20); ?></span>
        <div>
            <h2><?php echo t('الصف والمادة'); ?></h2>
            <span class="tqa-media__sub"><?php echo t('بهما يظهر الكورس في «المواد والبرامج» وتفتحه باقة صفه.'); ?></span>
        </div>
    </div>

    <div class="tqa-fieldgrid">
        <div class="tqa-field">
            <label class="tqa-field__label" for="tq_grade_id"><?php echo t('الصف الدراسي'); ?></label>
            <select class="tqa-select" id="tq_grade_id" name="tq_grade_id">
                <option value="0"><?php echo t('— بلا صف'); ?></option>
                <?php echo tqa_ref_options('grades', (int) $tq_link['grade_id']); ?>
            </select>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="tq_subject_id"><?php echo t('المادة'); ?></label>
            <select class="tqa-select" id="tq_subject_id" name="tq_subject_id">
                <option value="0"><?php echo t('— بلا مادة'); ?></option>
                <?php echo tqa_ref_options('subjects', (int) $tq_link['subject_id']); ?>
            </select>
        </div>
    </div>

    <p class="tqa-note" style="margin-block-start:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span>
            <?php echo t('الباقة تمنح الطالب'); ?> <strong><?php echo t('صفا ومادة'); ?></strong> <?php echo t('لا كورسا بعينه. فما لم يحمل الكورس صفا ومادة يبقى محتوى داخليا: يفتحه من سجل فيه صراحة، ولا يعرض في الموقع العام.'); ?>
        </span>
    </p>
</div>
