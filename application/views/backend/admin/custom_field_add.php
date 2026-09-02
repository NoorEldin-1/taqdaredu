<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إضافة قسم مخصص إلى صفحة الكورس — يفتح في نافذة.
 *
 * أعيدت كتابته بهيكل `tqa-*`. وستة أعطال أصلحت معه:
 *
 * ١ — **بلا توكن CSRF.** والنموذج يكتب في القاعدة ويرفع ملفات.
 *     `csrf_protection = TRUE` في هذا التركيب، فالحفظ يرد صفحة خطأ.
 * ٢ — **أزرار «+» و«−» لا تفعل شيئا.** المستمعون معلقون على
 *     `.btn-success` و`.remove-image-field` و`.add-slider-field` — أصناف
 *     Bootstrap زالت حين أعيد بناء الأزرار بأصناف `tqa-*`. فمن أراد
 *     صورتين في قسم واحد لم يكن له سبيل: يضغط «+» فلا يحدث شيء.
 * ٣ — **نوع «slider» في الشيفرة ولا خيار له في المنتقي** — بينما
 *     `Admin::custom_field_add()` يعالجه. كتلة ميتة في الوجهين.
 * ٤ — **`initSummerNote` على `#summernote`** — معرف واحد في نموذج قد
 *     يحمل عدة حقول، والمحرر غير محمل في هذه النافذة أصلا.
 * ٥ — **بالإنجليزية** كاملا: «Select type» و«Section Title» و«Submit».
 *     و`get_phrase('Submit')` كانت ترد «يقدم» — ترجمة آلية لا معنى لها
 *     في زر حفظ.
 * ٦ — **الحقول الخمسة مطبوعة كلها** ثم تخفى بـ`d-none`: أي أن النموذج
 *     يرسل حقول الأنواع الأربعة الأخرى فارغة مع النوع المختار.
 */
$tq_types = array(
    'image'   => array(t('صور بعناوين'), t('صورة وعنوان ووصف لكل بند.'),      'image'),
    'text'    => array(t('نص مفصل'),     t('فقرات تعرض تحت وصف الكورس.'),      'file-text'),
    'video'   => array(t('فيديو'),       t('روابط يوتيوب تعرض في مشغل.'),      'play'),
    'faq'     => array(t('أسئلة شائعة'), t('سؤال وجواب خاصان بهذا الكورس.'),   'help'),
    'gallery' => array(t('معرض صور'),    t('صور بلا عناوين تعرض في شبكة.'),    'grid'),
);

/** عنوان القسم يثبت متى وجد قسم بالنوع نفسه: الحفظ يدمج فيه. */
$tq_titles = array();
foreach ($this->db->select('custom_type, custom_title')->where('course_id', (int) $param2)
                  ->get('custom_fields')->result_array() as $tq_r) {
    $tq_titles[(string) $tq_r['custom_type']] = (string) $tq_r['custom_title'];
}
?>

<form method="post" enctype="multipart/form-data"
      action="<?php echo site_url('admin/custom_field_add/' . (int) $param2); ?>">
    <?php echo tq_csrf(); ?>

    <fieldset class="tqa-field">
        <legend class="tqa-field__label">
            <?php echo t('نوع القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </legend>

        <div class="tqa-stack">
            <?php foreach ($tq_types as $tq_k => [$tq_label, $tq_hint, $tq_ic]): ?>
                <label class="tqa-check">
                    <input type="radio" name="custom_type" value="<?php echo $tq_k; ?>"
                           data-tqa-cf-type required>
                    <span>
                        <strong style="color:var(--tq-navy)"><?php echo $tq_label; ?></strong>
                        <span class="tqa-prefrow__hint"><?php echo $tq_hint; ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <?php foreach ($tq_types as $tq_k => [$tq_label, $tq_hint, $tq_ic]):
        $tq_fixed = isset($tq_titles[$tq_k]);
    ?>
    <div data-tqa-cf-pane="<?php echo $tq_k; ?>" hidden>

        <div class="tqa-field">
            <label class="tqa-field__label" for="title_<?php echo $tq_k; ?>">
                <?php echo t('عنوان القسم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
            </label>
            <input class="tqa-input" type="text" id="title_<?php echo $tq_k; ?>"
                   name="<?php echo $tq_k; ?>_custom_title" maxlength="190"
                   value="<?php echo html_escape($tq_titles[$tq_k] ?? ''); ?>"
                   <?php echo $tq_fixed ? 'readonly' : ''; ?>>
            <span class="tqa-field__hint">
                <?php echo $tq_fixed
                    ? t('يوجد قسم بهذا النوع، وما يضاف هنا ينضم إليه تحت عنوانه.')
                    : t('يظهر عنوانا فوق هذه الكتلة في صفحة الكورس.'); ?>
            </span>
        </div>

        <?php /* البنود: بند واحد ابتداء، والزر يستنسخه. */ ?>
        <div data-tqa-rep="<?php echo $tq_k; ?>">
            <div data-tqa-rep-item class="tqa-card"
                 style="box-shadow:none;border-style:dashed;margin-block-end:var(--tq-space-s)">

                <?php if ($tq_k === 'image'): ?>
                    <div class="tqa-field">
                        <label class="tqa-field__label"><?php echo t('العنوان'); ?></label>
                        <input class="tqa-input" type="text" name="image_title[]" maxlength="190">
                    </div>
                    <div class="tqa-field">
                        <label class="tqa-field__label"><?php echo t('الوصف'); ?></label>
                        <textarea class="tqa-textarea" name="image_description[]" rows="2"
                                  style="min-block-size:70px"></textarea>
                    </div>
                    <div class="tqa-field">
                        <span class="tqa-field__label"><?php echo t('الصورة'); ?></span>
                        <div class="tqa-file">
                            <input type="file" name="image_file[]" accept="image/*" data-tqa-file>
                            <label class="tqa-file__btn"><?php echo tq_icon('image', 16); ?> <?php echo t('اختر صورة'); ?></label>
                            <span class="tqa-file__name" data-tqa-file-name><?php echo t('لم تختر ملفا بعد'); ?></span>
                        </div>
                    </div>

                <?php elseif ($tq_k === 'text'): ?>
                    <div class="tqa-field">
                        <label class="tqa-field__label"><?php echo t('النص'); ?></label>
                        <textarea class="tqa-textarea" name="text_content[]" rows="5" data-tqa-rich></textarea>
                    </div>

                <?php elseif ($tq_k === 'video'): ?>
                    <div class="tqa-field">
                        <label class="tqa-field__label"><?php echo t('رابط يوتيوب'); ?></label>
                        <input class="tqa-input tqa-input--ltr" type="url" name="video_url[]" dir="ltr"
                               placeholder="https://www.youtube.com/watch?v=...">
                    </div>

                <?php elseif ($tq_k === 'faq'): ?>
                    <div class="tqa-field">
                        <label class="tqa-field__label"><?php echo t('السؤال'); ?></label>
                        <input class="tqa-input" type="text" name="faq_question[]" maxlength="255">
                    </div>
                    <div class="tqa-field">
                        <label class="tqa-field__label"><?php echo t('الإجابة'); ?></label>
                        <textarea class="tqa-textarea" name="faq_answer[]" rows="3"></textarea>
                    </div>

                <?php else: /* gallery */ ?>
                    <div class="tqa-field">
                        <span class="tqa-field__label"><?php echo t('الصور'); ?></span>
                        <div class="tqa-file">
                            <input type="file" name="gallery_images[]" accept="image/*" multiple data-tqa-file>
                            <label class="tqa-file__btn"><?php echo tq_icon('image', 16); ?> <?php echo t('اختر صورا'); ?></label>
                            <span class="tqa-file__name" data-tqa-file-name><?php echo t('يمكن اختيار عدة صور معا'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="tqa-actions" style="margin-block-start:var(--tq-space-s)">
                    <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm tqa-btn--quiet-danger" data-tqa-rep-remove>
                        <?php echo tq_icon('trash', 14); ?> <?php echo t('احذف هذا البند'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php if ($tq_k !== 'gallery'): ?>
            <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                    data-tqa-rep-add="<?php echo $tq_k; ?>">
                <?php echo tq_icon('plus', 14); ?> <?php echo t('أضف بندا'); ?>
            </button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="tqa-actions" data-tqa-cf-save hidden>
        <button class="tqa-btn tqa-btn--primary tqa-btn--block" type="submit">
            <?php echo tq_icon('check', 16); ?> <?php echo t('أضف القسم'); ?>
        </button>
    </div>
</form>

<script>
(function () {
    'use strict';

    /**
     * النوع المختار وحده يعرض **ويرسل**.
     *
     * التعطيل لا الإخفاء: الحقل المخفي يرسل قيمته الفارغة، وقد كان
     * النموذج يرسل حقول الأنواع الخمسة معا — فيقرأ `custom_field_add`
     * مصفوفات فارغة لأنواع لم تختر.
     */
    var panes = Array.prototype.slice.call(document.querySelectorAll('[data-tqa-cf-pane]'));
    var save  = document.querySelector('[data-tqa-cf-save]');
    if (!panes.length || !save) return;

    var apply = function (kind) {
        panes.forEach(function (p) {
            var on = (p.getAttribute('data-tqa-cf-pane') === kind);
            p.hidden = !on;
            Array.prototype.forEach.call(p.querySelectorAll('input, textarea, select'), function (f) {
                f.disabled = !on;
            });
        });
        save.hidden = !kind;
    };

    Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-cf-type]'), function (r) {
        r.addEventListener('change', function () { apply(r.value); });
    });
    apply('');
})();
</script>

<?php include 'tqa_file_js.php'; ?>
<?php include 'tqa_repeater_js.php'; ?>
