<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تعديل بند في قسم مخصص — يفتح في نافذة.
 *
 * أعيدت كتابته بهيكل `tqa-*`. وأعطاله:
 *
 * ١ — **بلا توكن CSRF**، والنموذج يكتب ويرفع.
 * ٢ — **`$field['custom_type']` و`$item['title']` بلا فحص** — بند حذف
 *     من نافذة أخرى، أو قسم من نوع `gallery` (وبنوده بلا `title`)،
 *     يقرأ فهرسا غائبا في كل حقل.
 * ٣ — **`get_phrase('custom_field_type : ')`** مفتاح بمسافتين ونقطتين،
 *     لا ترجمة له، فيطبع حرفا كما هو.
 * ٤ — **حلقة `foreach` على بند واحد** ثم `<hr>` بعدها: بقية من نسخة
 *     كانت تعرض البنود كلها.
 * ٥ — **`$('#summernote').summernote(...)`** والمحرر غير محمل هنا.
 * ٦ — **`gallery` يعرض حقل `image_file[] multiple`** بينما
 *     `custom_field_item_update()` يقرأ `$_FILES['image_file']['name'][0]`
 *     وحده — فاختيار خمس صور يحفظ واحدة بلا إخبار.
 */
$tq_f = $this->db->where('id', (int) $param2)->get('custom_fields')->row_array();

if (!$tq_f) {
    echo '<p class="tqa-note tqa-note--warn">لا قسم بهذا المعرف — قد يكون حذف من نافذة أخرى.</p>';
    return;
}

$tq_type  = (string) $tq_f['custom_type'];
$tq_items = json_decode((string) $tq_f['custom_field'], true)['data'] ?? array();
$tq_item  = null;
foreach (is_array($tq_items) ? $tq_items : array() as $tq_it) {
    if ((string) ($tq_it['id'] ?? '') === (string) $param3) { $tq_item = $tq_it; break; }
}

if (!$tq_item) {
    echo '<p class="tqa-note tqa-note--warn">لا بند بهذا المعرف في هذا القسم.</p>';
    return;
}

$tq_names = array('image' => 'صور بعناوين', 'text' => 'نص مفصل', 'video' => 'فيديو',
                  'faq' => 'أسئلة شائعة', 'gallery' => 'معرض صور');

$tq_file = (string) ($tq_item['file'] ?? '');
$tq_src  = ($tq_file !== '' && is_file(FCPATH . 'uploads/custom_fields/' . $tq_file))
         ? base_url('uploads/custom_fields/' . $tq_file) : '';
?>

<div class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('grid', 18); ?></span>
    <span>
        القسم: <strong><?php echo html_escape($tq_f['custom_title']); ?></strong>
        — نوعه <?php echo html_escape($tq_names[$tq_type] ?? $tq_type); ?>.
    </span>
</div>

<form method="post" enctype="multipart/form-data"
      action="<?php echo site_url('admin/custom_field_item_update/' . (int) $tq_f['id'] . '/' . rawurlencode((string) $tq_item['id'])); ?>">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="item_id" value="<?php echo html_escape($tq_item['id']); ?>">

    <?php if ($tq_type === 'image'): ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="cf_title">العنوان</label>
            <input class="tqa-input" type="text" id="cf_title" name="image_title[]" maxlength="190"
                   value="<?php echo html_escape($tq_item['title'] ?? ''); ?>">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="cf_desc">الوصف</label>
            <textarea class="tqa-textarea" id="cf_desc" name="image_description[]" rows="3"><?php
                echo html_escape($tq_item['description'] ?? ''); ?></textarea>
        </div>

        <div class="tqa-field">
            <span class="tqa-field__label">الصورة</span>
            <?php if ($tq_src !== ''): ?>
                <img src="<?php echo html_escape($tq_src); ?>" alt="" loading="lazy"
                     style="inline-size:200px;block-size:120px;object-fit:cover;
                            border-radius:var(--tqa-radius-sm);margin-block-end:var(--tq-space-s)">
            <?php endif; ?>
            <div class="tqa-file">
                <input type="file" id="cf_file" name="image_file[]" accept="image/*" data-tqa-file>
                <label class="tqa-file__btn" for="cf_file"><?php echo tq_icon('image', 16); ?> استبدل الصورة</label>
                <span class="tqa-file__name" data-tqa-file-name>اترك الحقل ليبقى ما هو محفوظ</span>
            </div>
        </div>

    <?php elseif ($tq_type === 'text'): ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="cf_text">النص</label>
            <textarea class="tqa-textarea" id="cf_text" name="text_content[]" rows="6" data-tqa-rich><?php
                echo html_escape($tq_item['description'] ?? ''); ?></textarea>
        </div>

    <?php elseif ($tq_type === 'video'): ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="cf_video">رابط يوتيوب</label>
            <input class="tqa-input tqa-input--ltr" type="url" id="cf_video" name="video_url[]" dir="ltr"
                   value="<?php echo html_escape($tq_file); ?>"
                   placeholder="https://www.youtube.com/watch?v=...">
        </div>

    <?php elseif ($tq_type === 'faq'): ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="cf_q">السؤال</label>
            <input class="tqa-input" type="text" id="cf_q" name="faq_question[]" maxlength="255"
                   value="<?php echo html_escape($tq_item['title'] ?? ''); ?>">
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="cf_a">الإجابة</label>
            <textarea class="tqa-textarea" id="cf_a" name="faq_answer[]" rows="4"><?php
                echo html_escape($tq_item['description'] ?? ''); ?></textarea>
        </div>

    <?php else: /* gallery */ ?>

        <div class="tqa-field">
            <span class="tqa-field__label">الصورة</span>
            <?php if ($tq_src !== ''): ?>
                <img src="<?php echo html_escape($tq_src); ?>" alt="" loading="lazy"
                     style="inline-size:200px;block-size:120px;object-fit:cover;
                            border-radius:var(--tqa-radius-sm);margin-block-end:var(--tq-space-s)">
            <?php endif; ?>
            <?php /* ملف واحد لا `multiple`: الحفظ يقرأ الأول وحده، وحقل
                     متعدد هنا يعد بما لا يفعل. الصور تضاف من «أضف قسما». */ ?>
            <div class="tqa-file">
                <input type="file" id="cf_gal" name="image_file[]" accept="image/*" data-tqa-file>
                <label class="tqa-file__btn" for="cf_gal"><?php echo tq_icon('image', 16); ?> استبدل الصورة</label>
                <span class="tqa-file__name" data-tqa-file-name>صورة واحدة — تستبدل المحفوظة</span>
            </div>
        </div>

    <?php endif; ?>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary tqa-btn--block" type="submit">
            <?php echo tq_icon('check', 16); ?> احفظ التعديل
        </button>
    </div>
</form>

<?php include 'tqa_file_js.php'; ?>
