<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أقسام المدونة.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **`<ul class="list-group">` بعنصر واحد لكل قسم.** قائمة من بند
 *     واحد، مكررة في شبكة — أي أن كل بطاقة قائمة مستقلة. صارت بطاقات.
 * ٢ — **قائمة منسدلة لإجرائين.** ضغطتان لما يقرأ بضغطة.
 * ٣ — **الحذف برابط GET** ينفذ بمجرد جلبه.
 * ٤ — **`get_blogs_by_category_id()` داخل الحلقة** — استعلام لكل قسم
 *     ليعد مقالاته. صار استعلاما واحدا مجمعا.
 */
$tq_cats = $categories->result_array();

/* عدد المقالات لكل قسم — استعلام واحد لا واحد لكل صف. */
$tq_counts = array();
try {
    foreach ($this->db->select('blog_category_id AS k, COUNT(*) AS n')
                      ->group_by('blog_category_id')
                      ->get('blogs')->result_array() as $tq_r) {
        $tq_counts[(int) $tq_r['k']] = (int) $tq_r['n'];
    }
} catch (Throwable $tq_e) {
    /* عمود أرقام فارغ أهون من شاشة بيضاء. */
}
?>

<?php tqa_head(t('أقسام المدونة'), t('كل مقال يسند إلى قسم واحد، والقسم يظهر مرشحا في صفحة المدونة.'), 'grid',
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('admin/add_blog_category') . '">'
  . tq_icon('plus', 17) . t(' قسم جديد</a>')
  . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/blog') . '">'
  . tq_icon('file', 16) . t(' المقالات</a>')); ?>

<?php if (empty($tq_cats)): ?>

    <div class="tqa-card tqa-card--flush">
        <?php tqa_empty(t('لا أقسام بعد'),
            t('المقال لا يحفظ بلا قسم — فهذه أول خطوة قبل الكتابة.'),
            t('أضف أول قسم'), site_url('admin/add_blog_category'), 'grid'); ?>
    </div>

<?php else: ?>

    <div class="tqa-grid tqa-grid--3">
        <?php foreach ($tq_cats as $tq_c): $tq_id = (int) $tq_c['blog_category_id']; ?>
            <article class="tqa-item">
                <div class="tqa-item__head">
                    <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('grid', 20); ?></span>

                    <div style="flex:1;min-inline-size:0">
                        <h2 class="tqa-item__title"><?php echo html_escape($tq_c['title']); ?></h2>
                        <?php if (trim((string) $tq_c['subtitle']) !== ''): ?>
                            <span class="tqa-item__sub"><?php echo html_escape($tq_c['subtitle']); ?></span>
                        <?php endif; ?>
                    </div>

                    <span class="tqa-badge tqa-badge--muted">
                        <span class="tqa-num"><?php echo (int) ($tq_counts[$tq_id] ?? 0); ?></span> <?php echo t('مقالا'); ?>
                    </span>
                </div>

                <div class="tqa-item__foot">
                    <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                       href="<?php echo site_url('admin/edit_blog_category/' . $tq_id); ?>">
                        <?php echo tq_icon('edit', 15); ?> <?php echo t('تعديل'); ?>
                    </a>

                    <form method="post" style="margin-inline-start:auto"
                          action="<?php echo site_url('admin/blog_category/delete/' . $tq_id); ?>"
                          data-tqa-confirm-title="<?php echo te('حذف القسم'); ?>"
                          data-tqa-confirm="<?php echo te('سيحذف «____». والمقالات المصنفة تحته تبقى بلا قسم.', array(html_escape($tq_c['title']))); ?>"
                          data-tqa-confirm-ok="<?php echo te('نعم، احذف'); ?>"
                          data-tqa-confirm-tone="danger">
                        <?php echo tq_csrf(); ?>
                        <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm tqa-btn--quiet-danger">
                            <?php echo tq_icon('trash', 15); ?> <?php echo t('حذف'); ?>
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
