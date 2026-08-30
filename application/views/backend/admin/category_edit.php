<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تعديل قسم. الشكل نفسه الذي في [category_add.php] حرفا بحرف، وعليه
 * زيادتان: الغلاف المحفوظ يعرض (لا اسم ملف مجردا كما كان)، وحذفه
 * نموذج POST لا رابط GET.
 */
$tq_cat = $this->crud_model->get_category_details_by_id($category_id)->row_array();

if (!$tq_cat) {
    tqa_head(t('قسم غير موجود'), '', 'grid');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty(t('لا قسم بهذا المعرف'), t('قد يكون حذف من شاشة أخرى.'), t('العودة إلى الأقسام'), site_url('admin/categories'), 'grid');
    echo '</div>';
    return;
}

$tq_is_sub = (int) $tq_cat['parent'] > 0;

/** الملف الموجود فعلا وحده يعرض — واسم في القاعدة لا يعني ملفا على القرص. */
$tq_shot = function ($file) {
    $file = trim((string) $file);
    if ($file === '') return '';
    $rel = 'uploads/thumbnails/category_thumbnails/' . $file;
    return is_file(FCPATH . $rel) ? base_url($rel) : '';
};
$tq_sub_img = $tq_shot(isset($tq_cat['sub_category_thumbnail']) ? $tq_cat['sub_category_thumbnail'] : '');
$tq_par_img = $tq_shot(isset($tq_cat['thumbnail']) ? $tq_cat['thumbnail'] : '');
?>

<?php tqa_head(t('تعديل القسم'), html_escape($tq_cat['name']), 'grid',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/categories') . '">'
  . tq_icon('chev-prev', 16) . t('كل الأقسام</a>')); ?>

<div class="tqa-card" style="max-inline-size:760px">

    <form action="<?php echo site_url('admin/categories/edit/' . (int) $category_id); ?>" method="post"
          enctype="multipart/form-data">
        <?php echo tq_csrf(); ?>

        <div class="tqa-fieldgrid">

            <div class="tqa-field">
                <label class="tqa-field__label" for="cat_name">
                    <?php echo t('اسم القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="cat_name" name="name" required maxlength="190"
                       value="<?php echo html_escape($tq_cat['name']); ?>" autocomplete="off">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="cat_parent"><?php echo t('القسم الأب'); ?></label>
                <select class="tqa-select" id="cat_parent" name="parent" data-tqa-parent>
                    <option value="0"><?php echo t('— بلا أب (قسم رئيسي)'); ?></option>
                    <?php foreach ($categories as $tq_c): ?>
                        <?php if ((int) $tq_c['id'] === (int) $tq_cat['id']) continue; ?>
                        <?php if ((int) $tq_c['parent'] !== 0) continue; ?>
                        <option value="<?php echo (int) $tq_c['id']; ?>"
                            <?php echo (int) $tq_cat['parent'] === (int) $tq_c['id'] ? 'selected' : ''; ?>>
                            <?php echo html_escape($tq_c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="cat_code"><?php echo t('رمز القسم'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="cat_code" name="code"
                       value="<?php echo html_escape($tq_cat['code']); ?>" readonly>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="cat_icon"><?php echo t('صنف الأيقونة'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="cat_icon" name="font_awesome_class"
                       value="<?php echo html_escape($tq_cat['font_awesome_class']); ?>"
                       autocomplete="off" placeholder="fas fa-book">
            </div>

            <div class="tqa-field tqa-field--full" data-tqa-cover="parent" <?php echo $tq_is_sub ? 'hidden' : ''; ?>>
                <span class="tqa-field__label"><?php echo t('غلاف القسم الرئيسي'); ?></span>
                <div class="tqa-file">
                    <?php if ($tq_par_img !== ''): ?>
                        <img class="tqa-file__preview" src="<?php echo html_escape($tq_par_img); ?>" alt="<?php echo te('الغلاف الحالي'); ?>">
                    <?php endif; ?>
                    <input type="file" id="cat_cover" name="category_thumbnail" accept="image/*" data-tqa-file>
                    <label class="tqa-file__btn" for="cat_cover">
                        <?php echo tq_icon('image', 16); ?>
                        <?php echo $tq_par_img !== '' ? t('استبدل الصورة') : t('اختر صورة'); ?>
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name>
                        <?php echo $tq_par_img !== '' ? t('اترك الحقل فارغا لإبقاء الصورة الحالية') : t('لم تختر ملفا بعد'); ?>
                    </span>
                </div>
                <span class="tqa-field__hint"><?php echo t('المقاس المفضل ‎400 × 255‎ بكسل.'); ?></span>
            </div>

            <div class="tqa-field tqa-field--full" data-tqa-cover="sub" <?php echo $tq_is_sub ? '' : 'hidden'; ?>>
                <span class="tqa-field__label"><?php echo t('أيقونة القسم الفرعي'); ?></span>
                <div class="tqa-file">
                    <?php if ($tq_sub_img !== ''): ?>
                        <img class="tqa-file__preview" src="<?php echo html_escape($tq_sub_img); ?>" alt="<?php echo te('الأيقونة الحالية'); ?>">
                    <?php endif; ?>
                    <input type="file" id="cat_cover_sub" name="sub_category_thumbnail" accept="image/*" data-tqa-file>
                    <label class="tqa-file__btn" for="cat_cover_sub">
                        <?php echo tq_icon('image', 16); ?>
                        <?php echo $tq_sub_img !== '' ? t('استبدل الصورة') : t('اختر صورة'); ?>
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name>
                        <?php echo $tq_sub_img !== '' ? t('اترك الحقل فارغا لإبقاء الصورة الحالية') : t('لم تختر ملفا بعد'); ?>
                    </span>
                </div>
                <span class="tqa-field__hint"><?php echo t('المقاس المفضل ‎100 × 100‎ بكسل.'); ?></span>
            </div>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> احفظ التعديل
            </button>
            <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/categories'); ?>"><?php echo t('إلغاء'); ?></a>
        </div>
    </form>
</div>

<?php if ($tq_sub_img !== ''): ?>
    <div class="tqa-card tqa-section" style="max-inline-size:760px;margin-block-start:var(--tq-space-l)">
        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <span class="tqa-prefrow__title"><?php echo t('حذف أيقونة القسم الفرعي'); ?></span>
                <span class="tqa-prefrow__hint"><?php echo t('يحذف الملف وحده — القسم يبقى كما هو.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <form method="post"
                      action="<?php echo site_url('admin/categories/sub_category_image/' . (int) $tq_cat['id']); ?>"
                      data-tqa-confirm-title="<?php echo te('حذف الأيقونة'); ?>"
                      data-tqa-confirm="سيحذف ملف الأيقونة من الخادم."
                      data-tqa-confirm-ok="نعم، احذف"
                      data-tqa-confirm-tone="danger">
                    <?php echo tq_csrf(); ?>
                    <button type="submit" class="tqa-btn tqa-btn--ghost" style="color:var(--tq-danger)">
                        <?php echo tq_icon('trash', 15); ?> حذف الأيقونة
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'category_form_js.php'; ?>
