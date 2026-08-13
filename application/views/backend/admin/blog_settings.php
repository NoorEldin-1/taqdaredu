<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * إعدادات صفحة المدونة.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وزوجا الراديو صارا مفتاحين: قيمتان
 * ثنائيتان («نعم/لا» و«ظاهرة/مخفية») يقرؤهما المفتاح بنظرة.
 *
 * وحقل البانر كان `class="form-control"` على `type="file"` — أي حقل ملف
 * بارتفاع حقل نص وحد حوله، وزر النظام بداخله بخطه ونصه الإنجليزي.
 */
$tq_banner = trim((string) get_frontend_settings('blog_page_banner'));
$tq_rel    = 'uploads/blog/' . $tq_banner;
$tq_src    = ($tq_banner !== '' && is_file(FCPATH . $tq_rel)) ? base_url($tq_rel) : '';
?>

<?php tqa_head('إعدادات المدونة', 'ما يظهر أعلى صفحة المدونة في الموقع العام.', 'file',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/blog') . '">'
  . tq_icon('chev-prev', 16) . ' المقالات</a>'); ?>

<form class="tqa-card" action="<?php echo site_url('admin/blog_settings/update'); ?>" method="post"
      enctype="multipart/form-data" style="max-inline-size:760px">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="blog_page_title">
            عنوان الصفحة <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="blog_page_title" name="blog_page_title" required
               value="<?php echo html_escape(get_frontend_settings('blog_page_title')); ?>">
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="blog_page_subtitle">
            العنوان الفرعي <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <textarea class="tqa-textarea" id="blog_page_subtitle" name="blog_page_subtitle" rows="2"
                  required style="min-block-size:70px"><?php
            echo html_escape(get_frontend_settings('blog_page_subtitle')); ?></textarea>
    </div>

    <div class="tqa-field">
        <span class="tqa-field__label">بانر الصفحة</span>
        <?php if ($tq_src !== ''): ?>
            <div class="tqa-checker" style="min-block-size:90px">
                <img src="<?php echo html_escape($tq_src); ?>" alt="البانر الحالي">
            </div>
        <?php endif; ?>
        <div class="tqa-file">
            <input type="file" id="blog_page_banner" name="blog_page_banner" accept="image/*" data-tqa-file>
            <label class="tqa-file__btn" for="blog_page_banner">
                <?php echo tq_icon('image', 16); ?>
                <?php echo $tq_src !== '' ? 'استبدل الصورة' : 'اختر صورة'; ?>
            </label>
            <span class="tqa-file__name" data-tqa-file-name>المقاس المفضل ‎2000 × 500‎</span>
        </div>
    </div>

    <div class="tqa-prefrow">
        <div class="tqa-prefrow__main">
            <span class="tqa-prefrow__title">المدونة تظهر في الصفحة الرئيسية</span>
            <span class="tqa-prefrow__hint">إغلاقها يبقي الصفحة قائمة ويخفي قسمها من الرئيسية.</span>
        </div>
        <div class="tqa-prefrow__end">
            <input type="hidden" name="blog_visibility_on_the_home_page" value="0">
            <span class="tqa-switch">
                <input type="checkbox" name="blog_visibility_on_the_home_page" value="1"
                       <?php echo (int) get_frontend_settings('blog_visibility_on_the_home_page') === 1 ? 'checked' : ''; ?>>
                <span class="tqa-switch__track" aria-hidden="true"></span>
            </span>
        </div>
    </div>

    <div class="tqa-prefrow">
        <div class="tqa-prefrow__main">
            <span class="tqa-prefrow__title">المعلمون يكتبون في المدونة</span>
            <span class="tqa-prefrow__hint">ما يكتبونه ينتظر اعتماد الإدارة قبل النشر.</span>
        </div>
        <div class="tqa-prefrow__end">
            <input type="hidden" name="instructors_blog_permission" value="0">
            <span class="tqa-switch">
                <input type="checkbox" name="instructors_blog_permission" value="1"
                       <?php echo (int) get_frontend_settings('instructors_blog_permission') === 1 ? 'checked' : ''; ?>>
                <span class="tqa-switch__track" aria-hidden="true"></span>
            </span>
        </div>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> احفظ الإعدادات
        </button>
    </div>
</form>

<?php include 'tqa_file_js.php'; ?>
