<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تعديل مقال. الشكل نفسه الذي في [blog_add.php]، وعليه معاينة الصور
 * المحفوظة — كانت تعرض نائبة السمة أبدا حتى للمقال الذي له صورة.
 */
if (!$blog) {
    tqa_head(t('مقال غير موجود'), '', 'file');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty(t('لا مقال بهذا المعرف'), t('قد يكون حذف من شاشة أخرى.'),
        t('كل المقالات'), site_url('admin/blog'), 'file');
    echo '</div>';
    return;
}

$tq_cats = $this->crud_model->get_blog_categories()->result_array();

/** الملف الموجود فعلا وحده يعرض. */
$tq_shot = function ($dir, $file) {
    $file = trim((string) $file);
    if ($file === '') return '';
    $rel = 'uploads/blog/' . $dir . '/' . $file;
    return is_file(FCPATH . $rel) ? base_url($rel) : '';
};
$tq_thumb  = $tq_shot('thumbnail', $blog['thumbnail'] ?? '');
$tq_banner = $tq_shot('banner', $blog['banner'] ?? '');

$tq_live = site_url('blog/details/' . rawurlencode(slugify($blog['title'])) . '/' . (int) $blog['blog_id']);
?>

<?php tqa_head(t('تعديل المقال'), $blog['title'], 'file',
    '<a class="tqa-btn tqa-btn--ghost" href="' . $tq_live . '" target="_blank" rel="noopener">'
  . tq_icon('external', 16) . t('اقرأه في الموقع</a>')
  . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/blog') . '">'
  . tq_icon('chev-prev', 16) . t('كل المقالات</a>')); ?>

<form action="<?php echo site_url('admin/blog/edit/' . (int) $blog['blog_id']); ?>" method="post"
      enctype="multipart/form-data" style="max-inline-size:860px">
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
                <input class="tqa-input" type="text" id="title" name="title" required maxlength="190"
                       value="<?php echo html_escape($blog['title']); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="blog_category_id">
                    <?php echo t('القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <select class="tqa-select" id="blog_category_id" name="blog_category_id" required>
                    <option value=""><?php echo t('— اختر قسما'); ?></option>
                    <?php foreach ($tq_cats as $tq_c): ?>
                        <option value="<?php echo (int) $tq_c['blog_category_id']; ?>"
                            <?php echo (int) $blog['blog_category_id'] === (int) $tq_c['blog_category_id'] ? 'selected' : ''; ?>>
                            <?php echo html_escape($tq_c['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="keywords_in"><?php echo t('الكلمات الدلالية'); ?></label>
                <div class="tqa-tags" data-tqa-tags>
                    <input type="hidden" name="keywords" data-tqa-tags-value
                           value="<?php echo html_escape($blog['keywords']); ?>">
                    <input class="tqa-tags__in" type="text" id="keywords_in" autocomplete="off"
                           placeholder="<?php echo te('اكتب كلمة ثم اضغط Enter'); ?>" data-tqa-tags-input>
                </div>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="description"><?php echo t('نص المقال'); ?></label>
                <textarea class="tqa-textarea" id="description" name="description" rows="12" data-tqa-rich><?php
                    echo html_escape(htmlspecialchars_decode_($blog['description'])); ?></textarea>
            </div>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('image', 20); ?></span>
            <h2><?php echo t('الصور'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <?php foreach (array(
                array('thumbnail', t('صورة المقال المصغرة'), '800 × 500', $tq_thumb),
                array('banner',    t('بانر المقال'),          '2000 × 500', $tq_banner),
            ) as [$tq_name, $tq_label, $tq_size, $tq_src]): ?>
                <div class="tqa-field">
                    <span class="tqa-field__label"><?php echo $tq_label; ?></span>

                    <?php if ($tq_src !== ''): ?>
                        <div class="tqa-checker" style="min-block-size:90px">
                            <img src="<?php echo html_escape($tq_src); ?>" alt="<?php echo $tq_label; ?> الحالية">
                        </div>
                    <?php endif; ?>

                    <div class="tqa-file">
                        <input type="file" id="<?php echo $tq_name; ?>" name="<?php echo $tq_name; ?>"
                               accept="image/*" data-tqa-file>
                        <label class="tqa-file__btn" for="<?php echo $tq_name; ?>">
                            <?php echo tq_icon('image', 16); ?>
                            <?php echo $tq_src !== '' ? t('استبدل الصورة') : t('اختر صورة'); ?>
                        </label>
                        <span class="tqa-file__name" data-tqa-file-name>
                            <?php echo t('المقاس المفضل'); ?> <span class="tq-ltr" dir="ltr"><?php echo $tq_size; ?></span>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <label class="tqa-prefrow__title" for="is_popular"><?php echo t('مقال مميز'); ?></label>
                <span class="tqa-prefrow__hint"><?php echo t('يعرض في شريط «الأبرز» في صفحة المدونة.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <span class="tqa-switch">
                    <input type="checkbox" id="is_popular" name="is_popular" value="1"
                           <?php echo (int) $blog['is_popular'] === 1 ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> احفظ التعديل
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/blog'); ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>

<?php include 'tqa_file_js.php'; ?>
<?php include 'tqa_tags_js.php'; ?>
