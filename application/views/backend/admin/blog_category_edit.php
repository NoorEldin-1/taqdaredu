<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!$blog_category) {
    tqa_head(t('قسم غير موجود'), '', 'grid');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty(t('لا قسم بهذا المعرف'), t('قد يكون حذف من شاشة أخرى.'),
        t('كل الأقسام'), site_url('admin/blog_category'), 'grid');
    echo '</div>';
    return;
}
?>

<?php tqa_head(t('تعديل قسم المدونة'), $blog_category['title'], 'grid',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/blog_category') . '">'
  . tq_icon('chev-prev', 16) . t('كل الأقسام</a>')); ?>

<form class="tqa-card" method="post" style="max-inline-size:640px"
      action="<?php echo site_url('admin/blog_category/update/' . (int) $blog_category['blog_category_id']); ?>">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="category_title">
            <?php echo t('اسم القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="category_title" name="title" required maxlength="190"
               value="<?php echo html_escape($blog_category['title']); ?>">
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="category_subtitle"><?php echo t('سطر تعريفي'); ?></label>
        <textarea class="tqa-textarea" id="category_subtitle" name="subtitle" rows="2" maxlength="80"
                  style="min-block-size:70px"><?php echo html_escape($blog_category['subtitle']); ?></textarea>
        <span class="tqa-field__hint"><?php echo t('ثمانون محرفا فأقل.'); ?></span>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> احفظ التعديل
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/blog_category'); ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>
