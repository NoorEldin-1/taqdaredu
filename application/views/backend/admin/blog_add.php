<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مقال جديد.
 *
 * أعيدت كتابته بهيكل `tqa-*`. وما تغير:
 * حقلا الصورة كانا `visibility:hidden` (يحجزان مساحتهما ويخرجان من
 * ترتيب التنقل)، وحقل الكلمات `bootstrap-tagsinput` من CDN — انظر
 * TQ-TAGSINPUT-CDN في [tqa_tags_js.php].
 */
$tq_cats = $this->crud_model->get_blog_categories()->result_array();
?>

<?php tqa_head(t('مقال جديد'), t('المقال ينشر في مدونة الموقع العام.'), 'file',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/blog') . '">'
  . tq_icon('chev-prev', 16) . t('كل المقالات</a>')); ?>

<?php if (empty($tq_cats)): ?>
    <div class="tqa-note tqa-note--warn tqa-section" style="max-inline-size:860px">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo t('لا أقسام في المدونة بعد.'); ?></strong>
            <?php echo t('والمقال لا يحفظ بلا قسم.'); ?>
            <a href="<?php echo site_url('admin/blog_category'); ?>"><?php echo t('أضف قسما أولا'); ?></a>.
        </span>
    </div>
<?php endif; ?>

<form action="<?php echo site_url('admin/blog/add'); ?>" method="post" enctype="multipart/form-data"
      style="max-inline-size:860px">
    <?php echo tq_csrf(); ?>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('edit', 20); ?></span>
            <h2><?php echo t('المحتوى'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="title">
                    <?php echo t('عنوان المقال'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="title" name="title" required maxlength="190">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="blog_category_id">
                    <?php echo t('القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <select class="tqa-select" id="blog_category_id" name="blog_category_id" required>
                    <option value=""><?php echo t('— اختر قسما'); ?></option>
                    <?php foreach ($tq_cats as $tq_c): ?>
                        <option value="<?php echo (int) $tq_c['blog_category_id']; ?>">
                            <?php echo html_escape($tq_c['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="keywords_in"><?php echo t('الكلمات الدلالية'); ?></label>
                <div class="tqa-tags" data-tqa-tags>
                    <input type="hidden" name="keywords" value="" data-tqa-tags-value>
                    <input class="tqa-tags__in" type="text" id="keywords_in" autocomplete="off"
                           placeholder="<?php echo te('اكتب كلمة ثم اضغط Enter'); ?>" data-tqa-tags-input>
                </div>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="description"><?php echo t('نص المقال'); ?></label>
                <textarea class="tqa-textarea" id="description" name="description" rows="12"
                          data-tqa-rich></textarea>
            </div>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('image', 20); ?></span>
            <h2><?php echo t('الصور'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field">
                <span class="tqa-field__label"><?php echo t('صورة المقال المصغرة'); ?></span>
                <div class="tqa-file">
                    <input type="file" id="thumbnail" name="thumbnail" accept="image/*" data-tqa-file>
                    <label class="tqa-file__btn" for="thumbnail">
                        <?php echo tq_icon('image', 16); ?> اختر صورة
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name><?php echo t('المقاس المفضل ‎800 × 500‎'); ?></span>
                </div>
                <span class="tqa-field__hint"><?php echo t('تظهر في قائمة المدونة.'); ?></span>
            </div>

            <div class="tqa-field">
                <span class="tqa-field__label"><?php echo t('بانر المقال'); ?></span>
                <div class="tqa-file">
                    <input type="file" id="banner" name="banner" accept="image/*" data-tqa-file>
                    <label class="tqa-file__btn" for="banner">
                        <?php echo tq_icon('image', 16); ?> اختر صورة
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name><?php echo t('المقاس المفضل ‎2000 × 500‎'); ?></span>
                </div>
                <span class="tqa-field__hint"><?php echo t('تظهر أعلى صفحة المقال.'); ?></span>
            </div>
        </div>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <label class="tqa-prefrow__title" for="is_popular"><?php echo t('مقال مميز'); ?></label>
                <span class="tqa-prefrow__hint"><?php echo t('يعرض في شريط «الأبرز» في صفحة المدونة.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <span class="tqa-switch">
                    <input type="checkbox" id="is_popular" name="is_popular" value="1">
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> انشر المقال
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/blog'); ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>

<?php include 'tqa_file_js.php'; ?>
<?php include 'tqa_tags_js.php'; ?>
