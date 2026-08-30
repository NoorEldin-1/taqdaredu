<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ترتيب دروس قسم — يفتح في نافذة.
 * الشكل نفسه الذي في [sort_section.php]، وأعطاله نفسها كانت هنا.
 */
$tq_section = $this->crud_model->get_section('section', $param2)->row_array();

if (!$tq_section) {
    echo t('<p class="tqa-note tqa-note--warn">لا قسم بهذا المعرف — قد يكون حذف من نافذة أخرى.</p>');
    return;
}

$tq_lessons = $this->crud_model->get_lessons('section', $tq_section['id'])->result_array();
?>

<?php if (count($tq_lessons) < 2): ?>

    <p class="tqa-note">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span><?php echo t('الترتيب يحتاج درسين فأكثر في القسم.'); ?></span>
    </p>

<?php else: ?>

<p class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('layers', 18); ?></span>
    <span>
        <?php echo t('دروس قسم'); ?> <strong><?php echo html_escape($tq_section['title']); ?></strong><?php echo t('. اسحب الدرس إلى موضعه — والترتيب هنا هو ترتيب فتحه للطالب متى فعلت «الإتاحة التدريجية».'); ?>
    </span>
</p>

<div class="tqa-stack" id="tqa-sort-list" data-tqa-sortable>
    <?php foreach ($tq_lessons as $tq_i => $tq_l):
        $tq_is_quiz = ((string) $tq_l['lesson_type'] === 'quiz');
    ?>
        <div class="tqa-card tqa-sortitem" data-id="<?php echo (int) $tq_l['id']; ?>"
             style="display:flex;align-items:center;gap:var(--tq-space-m);cursor:grab">
            <span class="tqa-iconbox <?php echo $tq_is_quiz ? 'tqa-peach' : 'tqa-sky'; ?>"
                  aria-hidden="true" style="inline-size:34px;block-size:34px">
                <?php echo tq_icon($tq_is_quiz ? 'check-badge' : 'play', 16); ?>
            </span>
            <span style="flex:1;min-inline-size:0">
                <span class="tqa-media__title"><?php echo html_escape($tq_l['title']); ?></span>
                <span class="tqa-media__sub">
                    <?php echo $tq_is_quiz ? t('اختبار') : t('درس'); ?> ·
                    <span class="tqa-num" data-tqa-pos><?php echo $tq_i + 1; ?></span> <?php echo t('في الترتيب'); ?>
                </span>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<div class="tqa-actions">
    <button type="button" class="tqa-btn tqa-btn--primary tqa-btn--block" data-tqa-sort-save
            data-url="<?php echo site_url('admin/ajax_sort_lesson'); ?>" disabled>
        <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ الترتيب'); ?>
    </button>
</div>

<?php include 'tqa_sortable_js.php'; ?>

<?php endif; ?>
