<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ترتيب أقسام الكورس — يفتح في نافذة.
 *
 * أعيدت كتابته بهيكل `tqa-*`. وما كان قبله:
 *
 * ١ — **بالإنجليزية وسط لوحة عربية.** «List of sections» و«Update
 *     sorting» — `get_phrase` لا تجد لهما ترجمة فترد المفتاح كما هو.
 * ٢ — **لا شيء يقول إن الصفوف تسحب.** بطاقات Bootstrap بلا مقبض ولا
 *     مؤشر ولا سطر شرح: من يفتح النافذة يرى قائمة ساكنة وزرا اسمه
 *     «Update sorting» ولا يعرف ما يحدثه.
 * ٣ — **الزر مخفي حتى ينادى `onDomChange`.** `$('#section-sort-btn').show()`
 *     داخل `onDomChange(...)`، وهي دالة من حزمة القالب. فمتى تعثر ملفها
 *     بقي الزر مخفيا والنافذة بلا مخرج.
 * ٤ — **ثلاثون سطرا من مصنع Dragula مضغوطا** منسوخة في هذا الملف وفي
 *     [sort_lesson.php] وفي [custom_field_section_sorting.php] — وهي
 *     نفسها في `component.dragula.js` المحمل في الذيل أصلا.
 * ٥ — **الفرز يرسل بلا توكن CSRF**، فيسقط صامتا متى شددت الحماية.
 * ٦ — **`success_notify` ثم `location.reload()` بعد ثانية** بلا فحص أن
 *     الخادم قبل: أي رد — ولو كان صفحة خطأ — يعد نجاحا.
 */
$tq_sections = $this->crud_model->get_section('course', $param2)->result_array();
?>

<?php if (count($tq_sections) < 2): ?>

    <p class="tqa-note">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span>الترتيب يحتاج قسمين فأكثر.</span>
    </p>

<?php else: ?>

<p class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('layers', 18); ?></span>
    <span>اسحب القسم إلى موضعه. الترتيب هنا هو ترتيب ظهوره للطالب في صفحة الكورس.</span>
</p>

<div class="tqa-stack" id="tqa-sort-list" data-tqa-sortable>
    <?php foreach ($tq_sections as $tq_i => $tq_s): ?>
        <div class="tqa-card tqa-sortitem" data-id="<?php echo (int) $tq_s['id']; ?>"
             style="display:flex;align-items:center;gap:var(--tq-space-m);cursor:grab">
            <span class="tqa-iconbox tqa-mint" aria-hidden="true" style="inline-size:34px;block-size:34px">
                <?php echo tq_icon('menu', 16); ?>
            </span>
            <span style="flex:1;min-inline-size:0">
                <span class="tqa-media__title"><?php echo html_escape($tq_s['title']); ?></span>
                <span class="tqa-media__sub">
                    <span class="tqa-num" data-tqa-pos><?php echo $tq_i + 1; ?></span> في الترتيب
                </span>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<div class="tqa-actions">
    <button type="button" class="tqa-btn tqa-btn--primary tqa-btn--block" data-tqa-sort-save
            data-url="<?php echo site_url('admin/ajax_sort_section'); ?>" disabled>
        <?php echo tq_icon('check', 16); ?> احفظ الترتيب
    </button>
</div>

<?php include 'tqa_sortable_js.php'; ?>

<?php endif; ?>
