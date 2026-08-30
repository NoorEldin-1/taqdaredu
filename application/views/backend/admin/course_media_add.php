<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * صور الكورس — تولد من `theme-config.json` للسمة المفعلة.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وحقول الملفات كانت `visibility:hidden`:
 * تحجز مساحتها فتترك فراغا أبيض، وتبقى خارج ترتيب التنقل فلا يبلغها
 * لوح المفاتيح. صارت مخفية بـ`clip-path` من `.tqa-file` — خارج الرؤية
 * وداخل الشجرة.
 *
 * ويستعمل هذا الملف في شاشتي الإضافة والتحرير معا: `$course_details`
 * موجود في الثانية وحدها، فالصورة الحالية تعرض حين توجد وإلا فالنائبة.
 */
$tq_files        = themeConfiguration(get_frontend_settings('theme'), 'course_media_files');
$tq_placeholders = themeConfiguration(get_frontend_settings('theme'), 'course_media_placeholders');

if (!is_array($tq_files)) $tq_files = array();
if (!is_array($tq_placeholders)) $tq_placeholders = array();

$tq_labels = array(
    'course_thumbnail' => t('صورة الكورس'),
    'course_banner'    => t('بانر الكورس'),
    'course_overview_image' => t('صورة النظرة العامة'),
);

$tq_row = isset($course_details) ? $course_details : null;
?>

<?php foreach ($tq_files as $tq_key => $tq_size):
    $tq_label = $tq_labels[$tq_key] ?? str_replace('_', ' ', $tq_key);

    /* الصورة المحفوظة إن وجدت، وإلا نائبة السمة. */
    $tq_src = '';
    if ($tq_row && !empty($tq_row[$tq_key])) {
        $tq_rel = 'uploads/thumbnails/course_thumbnails/' . $tq_row[$tq_key];
        if (is_file(FCPATH . $tq_rel)) $tq_src = base_url($tq_rel);
    }
    if ($tq_src === '' && !empty($tq_placeholders[$tq_key . '_placeholder'])) {
        $tq_src = base_url($tq_placeholders[$tq_key . '_placeholder']);
    }
?>
    <div class="tqa-field">
        <span class="tqa-field__label"><?php echo html_escape($tq_label); ?></span>

        <div class="tqa-checker" style="min-block-size:130px">
            <?php if ($tq_src !== ''): ?>
                <img src="<?php echo html_escape($tq_src); ?>" alt="<?php echo te('____ الحالية', array(html_escape($tq_label))); ?>">
            <?php else: ?>
                <span style="font:var(--tq-type-caption);color:var(--tq-text2)"><?php echo t('لا صورة'); ?></span>
            <?php endif; ?>
        </div>

        <div class="tqa-file">
            <input type="file" id="m_<?php echo html_escape($tq_key); ?>" name="<?php echo html_escape($tq_key); ?>"
                   accept="image/*" data-tqa-file>
            <label class="tqa-file__btn" for="m_<?php echo html_escape($tq_key); ?>">
                <?php echo tq_icon('image', 16); ?> <?php echo t('اختر صورة'); ?>
            </label>
            <span class="tqa-file__name" data-tqa-file-name>
                <?php echo t('المقاس المفضل'); ?> <span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_size); ?></span>
            </span>
        </div>
    </div>
<?php endforeach; ?>
