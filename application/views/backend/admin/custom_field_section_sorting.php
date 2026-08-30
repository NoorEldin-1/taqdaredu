<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ترتيب الأقسام المخصصة — يفتح في نافذة.
 * الشكل نفسه الذي في [sort_section.php]. وما كان هنا زيادة على أعطاله:
 * زر «Save Changes» بالإنجليزية، ونداء `dragula` مباشر بلا فحص أن
 * المكتبة محملة — فتعثر ملفها يوقف السكربت كله ويترك الزر معطلا أبدا.
 */
$tq_rows = $this->db->where('course_id', (int) $param2)
                    ->order_by('sorting', 'ASC')->order_by('id', 'ASC')
                    ->get('custom_fields')->result_array();
?>

<?php if (count($tq_rows) < 2): ?>

    <p class="tqa-note">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span><?php echo t('الترتيب يحتاج قسمين مخصصين فأكثر.'); ?></span>
    </p>

<?php else: ?>

<p class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('grid', 18); ?></span>
    <span><?php echo t('اسحب القسم إلى موضعه. الترتيب هنا هو ترتيب ظهوره في صفحة الكورس العامة.'); ?></span>
</p>

<div class="tqa-stack" id="tqa-sort-list" data-tqa-sortable>
    <?php foreach ($tq_rows as $tq_i => $tq_r): ?>
        <div class="tqa-card tqa-sortitem" data-id="<?php echo (int) $tq_r['id']; ?>"
             style="display:flex;align-items:center;gap:var(--tq-space-m);cursor:grab">
            <span class="tqa-iconbox tqa-lilac" aria-hidden="true" style="inline-size:34px;block-size:34px">
                <?php echo tq_icon('menu', 16); ?>
            </span>
            <span style="flex:1;min-inline-size:0">
                <span class="tqa-media__title"><?php echo html_escape($tq_r['custom_title']); ?></span>
                <span class="tqa-media__sub">
                    <span class="tqa-num" data-tqa-pos><?php echo $tq_i + 1; ?></span> <?php echo t('في الترتيب'); ?>
                </span>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<div class="tqa-actions">
    <button type="button" class="tqa-btn tqa-btn--primary tqa-btn--block" data-tqa-sort-save
            data-url="<?php echo site_url('admin/custom_field_section_sort_update'); ?>" disabled>
        <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ الترتيب'); ?>
    </button>
</div>

<?php include 'tqa_sortable_js.php'; ?>

<?php endif; ?>
