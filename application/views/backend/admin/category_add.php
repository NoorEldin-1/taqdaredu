<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إضافة قسم.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وأربعة أشياء تغيرت في السلوك لا في الشكل:
 *
 * ١ — **الزر كان `type="button"`** ينادي `checkRequiredFields()` من
 *     `main.js` ليرسل النموذج بيده. أي أن **إضافة قسم لا تعمل إن تعثر
 *     ملف جافاسكربت واحد** — والنموذج بلا زر إرسال حقيقي أصلا. صار
 *     `type="submit"`، والإلزام يفرضه المتصفح بـ`required`.
 *
 * ٢ — **حقلا الملف كانا يحملان `id="category_thumbnail"` كلاهما.** معرف
 *     مكرر في صفحة واحدة: `<label for>` يشير إلى الأول أبدا، فالنقر على
 *     ملصق «غلاف القسم الفرعي» يفتح منتقي ملفات الحقل الآخر.
 *
 * ٣ — **`select2` غير محمل في اللوحة** (انظر TQ-SELECT2-GONE)، فالصنف
 *     `select2` و`data-toggle="select2"` زينة لا أثر لها. حذفت.
 *
 * ٤ — الغلاف المطلوب يتبع الأب: قسم أب له غلاف عريض، والفرعي أيقونة
 *     صغيرة. وهو ما كان يفعله السكربت، وبقي — لكن بحقول تعمل بدونه أيضا
 *     (كلاهما ظاهر حين لا جافاسكربت، لا كلاهما مخفي).
 */
$tq_code = substr(md5((string) rand(0, 1000000)), 0, 10);
?>

<?php tqa_head(t('إضافة قسم'), t('اترك «القسم الأب» فارغا لإنشاء قسم رئيسي، أو اخترـه لإنشاء قسم فرعي تحته.'), 'grid'); ?>

<div class="tqa-card" style="max-inline-size:760px">

    <form action="<?php echo site_url('admin/categories/add'); ?>" method="post" enctype="multipart/form-data">
        <?php echo tq_csrf(); ?>

        <div class="tqa-fieldgrid">

            <div class="tqa-field">
                <label class="tqa-field__label" for="cat_name">
                    <?php echo t('اسم القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="cat_name" name="name" required maxlength="190"
                       autocomplete="off">
                <span class="tqa-field__hint"><?php echo t('هذا ما يقرؤه الزائر في صفحة الكورسات.'); ?></span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="cat_parent"><?php echo t('القسم الأب'); ?></label>
                <select class="tqa-select" id="cat_parent" name="parent" data-tqa-parent>
                    <option value="0"><?php echo t('— بلا أب (قسم رئيسي)'); ?></option>
                    <?php foreach ($categories as $tq_c): ?>
                        <?php if ((int) $tq_c['parent'] !== 0) continue; ?>
                        <option value="<?php echo (int) $tq_c['id']; ?>"><?php echo html_escape($tq_c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="tqa-field__hint"><?php echo t('اختياره يجعل هذا قسما فرعيا — والكورس يسند إلى الفرعي.'); ?></span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="cat_code"><?php echo t('رمز القسم'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="cat_code" name="code"
                       value="<?php echo html_escape($tq_code); ?>" readonly>
                <span class="tqa-field__hint"><?php echo t('يولد تلقائيا ولا يعدل.'); ?></span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="cat_icon"><?php echo t('صنف الأيقونة'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="cat_icon" name="font_awesome_class"
                       autocomplete="off" placeholder="fas fa-book">
                <span class="tqa-field__hint"><?php echo t('اختياري — صنف Font Awesome يعرض بجانب الاسم.'); ?></span>
            </div>

            <div class="tqa-field tqa-field--full" data-tqa-cover="parent">
                <span class="tqa-field__label"><?php echo t('غلاف القسم الرئيسي'); ?></span>
                <div class="tqa-file">
                    <input type="file" id="cat_cover" name="category_thumbnail" accept="image/*" data-tqa-file>
                    <label class="tqa-file__btn" for="cat_cover">
                        <?php echo tq_icon('image', 16); ?> <?php echo t('اختر صورة'); ?>
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name><?php echo t('لم تختر ملفا بعد'); ?></span>
                </div>
                <span class="tqa-field__hint"><?php echo t('المقاس المفضل ‎400 × 255‎ بكسل.'); ?></span>
            </div>

            <div class="tqa-field tqa-field--full" data-tqa-cover="sub" hidden>
                <span class="tqa-field__label"><?php echo t('أيقونة القسم الفرعي'); ?></span>
                <div class="tqa-file">
                    <input type="file" id="cat_cover_sub" name="sub_category_thumbnail" accept="image/*" data-tqa-file>
                    <label class="tqa-file__btn" for="cat_cover_sub">
                        <?php echo tq_icon('image', 16); ?> <?php echo t('اختر صورة'); ?>
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name><?php echo t('لم تختر ملفا بعد'); ?></span>
                </div>
                <span class="tqa-field__hint"><?php echo t('المقاس المفضل ‎100 × 100‎ بكسل.'); ?></span>
            </div>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ القسم'); ?>
            </button>
            <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/categories'); ?>"><?php echo t('إلغاء'); ?></a>
        </div>
    </form>
</div>

<?php include 'category_form_js.php'; ?>
