<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php tqa_head('قسم مدونة جديد', 'القسم يظهر مرشحا في صفحة المدونة، وكل مقال يسند إلى واحد.', 'grid',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/blog_category') . '">'
  . tq_icon('chev-prev', 16) . ' كل الأقسام</a>'); ?>

<form class="tqa-card" action="<?php echo site_url('admin/blog_category/add'); ?>" method="post"
      style="max-inline-size:640px">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="category_title">
            اسم القسم <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="category_title" name="title" required maxlength="190">
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="category_subtitle">سطر تعريفي</label>
        <textarea class="tqa-textarea" id="category_subtitle" name="subtitle" rows="2" maxlength="80"
                  style="min-block-size:70px"></textarea>
        <span class="tqa-field__hint">ثمانون محرفا فأقل — يعرض تحت اسم القسم.</span>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> احفظ القسم
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/blog_category'); ?>">إلغاء</a>
    </div>
</form>
