<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * اختيار نوع الدرس — يفتح في نافذة.
 *
 * أعيدت كتابته بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **حذفت الأنواع المشروطة بـ`addon_status(...)`** — `amazon-s3` و
 *     `wasabi-s3`. ومعها `wasabi-storage` وهو **غير مشروط أصلا** ولا
 *     قالب له: `wasabi_storage_type_lesson_add.php` غير موجود في هذا
 *     المستودع. فاختياره كان يفتح نموذجا بلا حقل مصدر — درس يحفظ بلا
 *     فيديو.
 * ٢ — **`academy_cloud` كان معلقا** بـ`<!-- -->` وقالبه محذوف. حذف.
 * ٣ — **المنتقي `server-side-select3`** في فرع «اختصار الدرس» يعتمد على
 *     select2 وهو غير محمل في اللوحة (TQ-SELECT2-GONE) — فكان الحقل
 *     يعرض «اختر كورسا» وحدها ولا يمكن اختيار شيء. صار منتقيا عاديا
 *     مملوءا من الخادم.
 * ٤ — **`error_notify` عند عدم اختيار كورس** — دالة من حزمة القالب. صار
 *     الإلزام من المتصفح، والزر معطلا حتى يختار.
 */
$tq_picked = (isset($param3) && $param3 !== '') ? $param3 : 'youtube';

/** النوع: [التسمية، الشرح، الأيقونة]. الترتيب من الأكثر استعمالا. */
$tq_types = array(
    'youtube'            => array('فيديو يوتيوب', 'الصق رابط الفيديو — تقرأ مدته تلقائيا.', 'play'),
    'video'              => array('ملف فيديو', 'يرفع إلى الخادم. الأثقل تخزينا والأضمن بقاء.', 'upload'),
    'html5'              => array('رابط ملف مباشر', 'رابط ينتهي بـ .mp4 على خادم آخر.', 'link'),
    'vimeo'              => array('فيديو فيميو', 'يتطلب مفتاح فيميو في إعدادات المنصة.', 'play'),
    'google_drive_video' => array('فيديو جوجل درايف', 'يتطلب تفعيل خدمة درايف في مفتاح يوتيوب.', 'folder'),
    'audio'              => array('ملف صوتي', 'درس بلا صورة — للتلاوة والاستماع.', 'video'),
    'document'           => array('مستند', 'PDF أو Word يفتح داخل المشغل.', 'file'),
    'text'               => array('نص', 'درس مكتوب بلا وسائط.', 'file-text'),
    'image'              => array('صورة', 'لوحة أو مخطط يعرض كاملا.', 'image'),
    'iframe'             => array('تضمين خارجي', 'أداة تفاعلية من موقع آخر.', 'globe'),
);

$tq_shortcut = isset($param2) && $param2 === 'add_shortcut_lesson';
?>

<?php if ($tq_shortcut): ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="course_id_for_lesson">
            الكورس <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <?php /* مملوء من الخادم: الكورسات هنا عشرات لا آلاف، والمنتقي
                 الذي يجلب بـAJAX كان لا يجلب شيئا أصلا. */ ?>
        <select class="tqa-select" id="course_id_for_lesson" name="course_id_for_lesson" required>
            <option value="">— اختر كورسا</option>
            <?php foreach ($this->db->select('id, title')->where('course_type', 'general')
                                    ->order_by('title', 'ASC')->get('course')->result_array() as $tq_c): ?>
                <option value="<?php echo (int) $tq_c['id']; ?>"><?php echo html_escape($tq_c['title']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

<?php else: ?>

    <div class="tqa-note tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('book', 18); ?></span>
        <span>
            الكورس:
            <strong><?php echo html_escape($this->crud_model->get_course_by_id($param2)->row('title')); ?></strong>
        </span>
    </div>
    <input id="course_id_for_lesson" type="hidden" value="<?php echo (int) $param2; ?>" name="course_id_for_lesson">

<?php endif; ?>

<fieldset class="tqa-field">
    <legend class="tqa-field__label">نوع الدرس</legend>

    <div class="tqa-stack">
        <?php foreach ($tq_types as $tq_k => [$tq_label, $tq_hint, $tq_ic]): ?>
            <label class="tqa-check">
                <input type="radio" name="lesson_type" value="<?php echo $tq_k; ?>"
                       <?php echo $tq_picked === $tq_k ? 'checked' : ''; ?>>
                <span>
                    <strong style="color:var(--tq-navy)"><?php echo $tq_label; ?></strong>
                    <span class="tqa-prefrow__hint"><?php echo $tq_hint; ?></span>
                </span>
            </label>
        <?php endforeach; ?>
    </div>
</fieldset>

<div class="tqa-actions">
    <button type="button" class="tqa-btn tqa-btn--primary tqa-btn--block" data-tqa-lesson-next>
        التالي <?php echo tq_icon('chev-next', 16); ?>
    </button>
</div>

<script>
(function () {
    'use strict';

    var btn = document.querySelector('[data-tqa-lesson-next]');
    var sel = document.getElementById('course_id_for_lesson');
    if (!btn || !sel) return;

    var base = <?php echo json_encode(site_url('modal/popup/lesson_add')); ?>;

    /* الزر يعطل ما لم يختر كورس — كان يفتح ثم يعرض رسالة خطأ من مكتبة
       القالب، وهي غير محملة فلا تظهر رسالة أصلا. */
    var sync = function () { btn.disabled = !(parseInt(sel.value, 10) > 0); };
    sel.addEventListener('change', sync);
    sync();

    btn.addEventListener('click', function () {
        var kind = document.querySelector('input[name=lesson_type]:checked');
        if (!kind || btn.disabled) return;

        showAjaxModal(base + '/' + sel.value + '/' + kind.value, 'إضافة درس');
    });
})();
</script>
