<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تحرير كورس.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وأربعة أعطال أصلحت معها:
 *
 * ١ — **تبويب «Bbb live class» يضمن ملفا محذوفا.** كان السطر
 *     `include "bbb_live_class.php"` **بلا شرط** — والملف حذف مع بقية
 *     شاشات الإضافات. فينتج تحذير PHP في كل فتح للشاشة، والتبويب يفتح
 *     على فراغ أبيض. حذف التبويب وأخواته المشروطة بـ`addon_status(...)`
 *     — وكلها كاذبة أبدا في هذا التركيب.
 * ٢ — **التبويبات لا تعيش في الرابط.** `data-toggle="tab"` يبدل
 *     بجافاسكربت، فالعودة إلى «المقرر» بعد حفظ أي تبويب غير ممكنة، ولا
 *     يمكن إرسال رابط إلى تبويب بعينه. صارت في `?tab=`.
 * ٣ — **«قائمة المسجلين» و«التقدم العلمي» كانا يجلبان بـAJAX** إلى
 *     `<div class="tab-pane"></div>` فارغة — أي أن التبويب يظهر فارغا
 *     ثم يمتلئ، ولا يظهر شيئا إن تعثر النداء. صارا يعرضان من الخادم.
 * ٤ — **زر الحفظ `type="button"`** ينادي `checkRequiredFields()`؛ وهو
 *     العطل نفسه الموصوف في TQ-WIZARD-BLANK.
 *
 * وتبويب «المقرر» يبقى خارج نموذج الحفظ: كان **داخله**، فأزراره
 * (إضافة قسم · إضافة درس · فرز) تقع في نموذج `<form>` الكورس. وزر بلا
 * `type` داخل نموذج نوعه `submit` ضمنا — أي أن الضغط على «فرز الأقسام»
 * كان يرسل نموذج تحرير الكورس.
 */
$tq_course = $this->crud_model->get_course_by_id($course_id)->row_array();

if (!$tq_course) {
    tqa_head('كورس غير موجود', '', 'book');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty('لا كورس بهذا المعرف', 'قد يكون حذف من شاشة أخرى.',
        'العودة إلى الكورسات', site_url('admin/courses'), 'book');
    echo '</div>';
    return;
}

/* الشاشات الداخلية تقرأ `$course_details` بهذا الاسم. */
$course_details = $tq_course;

$tq_tabs = array(
    'curriculum' => array('المقرر',            'layers'),
    'basic'      => array('أساسيات الكورس',    'book'),
    'info'       => array('ما يعرض في صفحته',  'clipboard'),
    'pricing'    => array('التسعير',           'money'),
    'media'      => array('الصور والفيديو',    'video'),
    'seo'        => array('تحسين البحث',       'search'),
    'fields'     => array('حقول مخصصة',        'grid'),
    'enrolled'   => array('المسجلون',          'users'),
    'progress'   => array('التقدم العلمي',     'chart'),
);

$tq_tab = (string) $this->input->get('tab', true);
/* أسماء التبويبات القديمة تحول: روابط محفوظة تشير إليها. */
$tq_tab = array('customField' => 'fields', 'enrol_list' => 'enrolled',
                'academic_progress' => 'progress')[$tq_tab] ?? $tq_tab;
if (!isset($tq_tabs[$tq_tab])) $tq_tab = 'curriculum';

$tq_url = function ($t) use ($course_id) {
    return site_url('admin/course_form/course_edit/' . (int) $course_id) . '?tab=' . $t;
};

/** التبويبات التي تحرر حقول الكورس — وهي وحدها التي تلف بنموذج الحفظ. */
$tq_editing = in_array($tq_tab, array('basic', 'info', 'pricing', 'media', 'seo'), true);

$tq_live = site_url('home/course/' . rawurlencode(slugify($tq_course['title'])) . '/' . (int) $course_id);
?>

<?php tqa_head('تحرير الكورس', $tq_course['title'], 'book',
    '<a class="tqa-btn tqa-btn--ghost" href="' . $tq_live . '" target="_blank" rel="noopener">'
  . tq_icon('external', 16) . ' صفحته في الموقع</a>'
  . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/courses') . '">'
  . tq_icon('chev-prev', 16) . ' كل الكورسات</a>'); ?>

<nav class="tqa-tabs tqa-tabs--scroll" aria-label="أقسام تحرير الكورس">
    <?php foreach ($tq_tabs as $tq_k => [$tq_label, $tq_ic]): ?>
        <a href="<?php echo $tq_url($tq_k); ?>" <?php echo $tq_tab === $tq_k ? 'aria-current="page"' : ''; ?>>
            <?php echo html_escape($tq_label); ?>
        </a>
    <?php endforeach; ?>
</nav>


<?php /* ============ المقرر — خارج نموذج الحفظ عمدا ============ */ ?>
<?php if ($tq_tab === 'curriculum'): ?>

    <?php include 'curriculum.php'; ?>


<?php /* ============ المسجلون ============ */ ?>
<?php elseif ($tq_tab === 'enrolled'):
    $enrol_history = $this->crud_model->enrol_history($course_id);
    include 'course_enrol_list.php';
?>


<?php /* ============ التقدم العلمي ============ */ ?>
<?php elseif ($tq_tab === 'progress'): ?>

    <?php include 'student_academic_progress.php'; ?>


<?php /* ============ حقول مخصصة ============ */ ?>
<?php elseif ($tq_tab === 'fields'): ?>

    <?php include 'custom_field.php'; ?>


<?php /* ============ تبويبات التحرير ============ */ ?>
<?php else: ?>

<form class="required-form" method="post" enctype="multipart/form-data" autocomplete="off"
      action="<?php echo site_url('admin/course_actions/edit/' . (int) $course_id); ?>"
      style="max-inline-size:900px">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="course_type" value="<?php echo html_escape($tq_course['course_type']); ?>">

    <?php /* التبويب المعروض وحده يرسل حقوله. والحقول التي لا تعرض تحمل
             قيمها الحالية في حقول مخفية، فحفظ «التسعير» لا يمحو «الوصف».
             وكان النموذج السابق يعرضها كلها في صفحة واحدة، فلم تكن
             المشكلة قائمة — وهي تقوم مع التبويبات الحقيقية. */ ?>
    <?php
    $tq_carry = array(
        'basic'   => array('title', 'short_description', 'description', 'sub_category_id',
                           'level', 'language_made_in', 'status'),
        'pricing' => array('price', 'discounted_price'),
        'seo'     => array('meta_keywords', 'meta_description'),
        'media'   => array('course_overview_url' => 'video_url', 'course_overview_provider' => 'video_type'),
    );
    foreach ($tq_carry as $tq_group => $tq_names):
        if ($tq_group === $tq_tab) continue;
        foreach ($tq_names as $tq_field => $tq_col):
            $tq_field = is_int($tq_field) ? $tq_col : $tq_field;
            if (!array_key_exists($tq_col, $tq_course)) continue;
    ?>
        <input type="hidden" name="<?php echo html_escape($tq_field); ?>"
               value="<?php echo html_escape($tq_course[$tq_col]); ?>">
    <?php endforeach; endforeach; ?>


    <?php if ($tq_tab === 'basic'): ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('book', 20); ?></span>
                <h2>أساسيات الكورس</h2>
            </div>

            <div class="tqa-fieldgrid">
                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="course_title">
                        عنوان الكورس <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <input class="tqa-input" type="text" id="course_title" name="title" required maxlength="190"
                           value="<?php echo html_escape($tq_course['title']); ?>">
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="sub_category_id">
                        التصنيف <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <select class="tqa-select" id="sub_category_id" name="sub_category_id" required>
                        <option value="">— اختر تصنيفا</option>
                        <?php foreach ($categories->result_array() as $tq_c): ?>
                            <?php if ((int) $tq_c['parent'] !== 0) continue; ?>
                            <optgroup label="<?php echo html_escape($tq_c['name']); ?>">
                                <?php foreach ($this->crud_model->get_sub_categories($tq_c['id']) as $tq_s): ?>
                                    <option value="<?php echo (int) $tq_s['id']; ?>"
                                        <?php echo (int) $tq_course['sub_category_id'] === (int) $tq_s['id'] ? 'selected' : ''; ?>>
                                        <?php echo html_escape($tq_s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="level">المستوى</label>
                    <select class="tqa-select" id="level" name="level">
                        <?php foreach (array('beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم') as $tq_k => $tq_l): ?>
                            <option value="<?php echo $tq_k; ?>"
                                <?php echo $tq_course['level'] === $tq_k ? 'selected' : ''; ?>><?php echo $tq_l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="language_made_in">لغة المحتوى</label>
                    <select class="tqa-select" id="language_made_in" name="language_made_in">
                        <?php foreach ($languages as $tq_l): ?>
                            <option value="<?php echo html_escape($tq_l); ?>"
                                <?php echo $tq_course['language_made_in'] === $tq_l ? 'selected' : ''; ?>>
                                <?php echo html_escape(ucfirst($tq_l)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="short_description">وصف مختصر</label>
                    <input class="tqa-input" type="text" id="short_description" name="short_description" maxlength="255"
                           value="<?php echo html_escape($tq_course['short_description']); ?>">
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="description">الوصف الكامل</label>
                    <textarea class="tqa-textarea" id="description" name="description" rows="6" data-tqa-rich><?php
                        echo html_escape($tq_course['description']); ?></textarea>
                </div>
            </div>

            <div class="tqa-field" style="margin-block-start:var(--tq-space-l)">
                <span class="tqa-field__label">حالة الكورس</span>
                <div class="tqa-stack">
                    <?php foreach (array(
                        'active'   => array('منشور', 'يظهر في الموقع العام ويمكن الاشتراك فيه.'),
                        'private'  => array('خاص', 'لا يظهر في القوائم — يفتح برابطه وحده.'),
                        'upcoming' => array('قادم', 'يعرض بتاريخ نشر ولا يفتح قبله.'),
                        'pending'  => array('قيد المراجعة', 'أرسله معلم وينتظر قرار الإدارة.'),
                        'draft'    => array('مسودة', 'غير مكتمل ولا يعرض لأحد.'),
                    ) as $tq_k => [$tq_l, $tq_h]): ?>
                        <label class="tqa-check">
                            <input type="radio" name="status" value="<?php echo $tq_k; ?>" data-tqa-status
                                   <?php echo $tq_course['status'] === $tq_k ? 'checked' : ''; ?>>
                            <span>
                                <strong style="color:var(--tq-navy)"><?php echo $tq_l; ?></strong>
                                <span class="tqa-prefrow__hint"><?php echo $tq_h; ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div data-tqa-upcoming <?php echo $tq_course['status'] === 'upcoming' ? '' : 'hidden'; ?>>
                <div class="tqa-field">
                    <span class="tqa-field__label">صورة الكورس القادم</span>
                    <div class="tqa-file">
                        <input type="file" id="upcoming_image_thumbnail" name="upcoming_image_thumbnail"
                               accept="image/*" data-tqa-file>
                        <label class="tqa-file__btn" for="upcoming_image_thumbnail">
                            <?php echo tq_icon('image', 16); ?> اختر صورة
                        </label>
                        <span class="tqa-file__name" data-tqa-file-name>المقاس المفضل ‎365 × 460‎</span>
                        <input type="hidden" name="old_upcoming_image_thumbnail"
                               value="<?php echo html_escape($tq_course['upcoming_image_thumbnail']); ?>">
                    </div>
                </div>
            </div>

            <div class="tqa-prefrow">
                <div class="tqa-prefrow__main">
                    <label class="tqa-prefrow__title" for="is_top_course">كورس مميز</label>
                    <span class="tqa-prefrow__hint">يعرض في شريط «الأبرز» في الصفحة الرئيسية.</span>
                </div>
                <div class="tqa-prefrow__end">
                    <span class="tqa-switch">
                        <input type="checkbox" id="is_top_course" name="is_top_course" value="1"
                               <?php echo (int) $tq_course['is_top_course'] === 1 ? 'checked' : ''; ?>>
                        <span class="tqa-switch__track" aria-hidden="true"></span>
                    </span>
                </div>
            </div>

            <div class="tqa-prefrow">
                <div class="tqa-prefrow__main">
                    <label class="tqa-prefrow__title" for="enable_drip_content">إتاحة الدروس تدريجيا</label>
                    <span class="tqa-prefrow__hint">الدرس لا يفتح إلا بعد سابقه.</span>
                </div>
                <div class="tqa-prefrow__end">
                    <span class="tqa-switch">
                        <input type="checkbox" id="enable_drip_content" name="enable_drip_content" value="1"
                               <?php echo (int) $tq_course['enable_drip_content'] === 1 ? 'checked' : ''; ?>>
                        <span class="tqa-switch__track" aria-hidden="true"></span>
                    </span>
                </div>
            </div>
        </div>


    <?php elseif ($tq_tab === 'info'):
        $tq_faqs  = json_decode((string) $tq_course['faqs'], true);
        $tq_reqs  = json_decode((string) $tq_course['requirements'], true);
        $tq_outs  = json_decode((string) $tq_course['outcomes'], true);
        if (!is_array($tq_faqs) || !$tq_faqs) $tq_faqs = array(array('title' => '', 'description' => ''));
        if (!is_array($tq_reqs) || !$tq_reqs) $tq_reqs = array('');
        if (!is_array($tq_outs) || !$tq_outs) $tq_outs = array('');
    ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-sand" aria-hidden="true"><?php echo tq_icon('clipboard', 20); ?></span>
                <h2>ما يعرض في صفحة الكورس</h2>
            </div>

            <div class="tqa-field">
                <span class="tqa-field__label">المتطلبات السابقة</span>
                <div data-tqa-rep="requirements">
                    <?php foreach ($tq_reqs as $tq_r): ?>
                        <div data-tqa-rep-item class="tqa-row" style="flex-wrap:nowrap;margin-block-end:var(--tq-space-s)">
                            <input class="tqa-input" type="text" name="requirements[]"
                                   value="<?php echo html_escape($tq_r); ?>">
                            <button type="button" class="tqa-btn tqa-btn--ghost" data-tqa-rep-remove
                                    style="color:var(--tq-danger)" aria-label="احذف هذا المتطلب">
                                <?php echo tq_icon('trash', 15); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-add="requirements">
                    <?php echo tq_icon('plus', 14); ?> أضف متطلبا
                </button>
            </div>

            <div class="tqa-field">
                <span class="tqa-field__label">مخرجات التعلم</span>
                <div data-tqa-rep="outcomes">
                    <?php foreach ($tq_outs as $tq_o): ?>
                        <div data-tqa-rep-item class="tqa-row" style="flex-wrap:nowrap;margin-block-end:var(--tq-space-s)">
                            <input class="tqa-input" type="text" name="outcomes[]"
                                   value="<?php echo html_escape($tq_o); ?>">
                            <button type="button" class="tqa-btn tqa-btn--ghost" data-tqa-rep-remove
                                    style="color:var(--tq-danger)" aria-label="احذف هذا المخرج">
                                <?php echo tq_icon('trash', 15); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-add="outcomes">
                    <?php echo tq_icon('plus', 14); ?> أضف مخرجا
                </button>
            </div>

            <div class="tqa-field">
                <span class="tqa-field__label">أسئلة شائعة عن الكورس</span>
                <div data-tqa-rep="faqs">
                    <?php foreach ($tq_faqs as $tq_f): ?>
                        <div data-tqa-rep-item class="tqa-card" style="box-shadow:none;border-style:dashed;
                             margin-block-end:var(--tq-space-s)">
                            <input class="tqa-input" type="text" name="faqs[]" placeholder="السؤال"
                                   style="margin-block-end:var(--tq-space-s)"
                                   value="<?php echo html_escape($tq_f['title'] ?? ''); ?>">
                            <textarea class="tqa-textarea" name="faq_descriptions[]" rows="2" placeholder="الإجابة"
                                      style="min-block-size:70px"><?php echo html_escape($tq_f['description'] ?? ''); ?></textarea>
                            <div class="tqa-actions" style="margin-block-start:var(--tq-space-s)">
                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-remove
                                        style="color:var(--tq-danger)">
                                    <?php echo tq_icon('trash', 14); ?> احذف هذا السؤال
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-add="faqs">
                    <?php echo tq_icon('plus', 14); ?> أضف سؤالا
                </button>
            </div>
        </div>


    <?php elseif ($tq_tab === 'pricing'): ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('money', 20); ?></span>
                <h2>التسعير</h2>
            </div>

            <div class="tqa-prefrow">
                <div class="tqa-prefrow__main">
                    <label class="tqa-prefrow__title" for="is_free_course">كورس مجاني</label>
                    <span class="tqa-prefrow__hint">يفتح لكل مسجل بلا دفع.</span>
                </div>
                <div class="tqa-prefrow__end">
                    <span class="tqa-switch">
                        <input type="checkbox" id="is_free_course" name="is_free_course" value="1" data-tqa-free
                               <?php echo (int) $tq_course['is_free_course'] === 1 ? 'checked' : ''; ?>>
                        <span class="tqa-switch__track" aria-hidden="true"></span>
                    </span>
                </div>
            </div>

            <div data-tqa-paid style="margin-block-start:var(--tq-space-l)">
                <div class="tqa-fieldgrid">
                    <div class="tqa-field">
                        <label class="tqa-field__label" for="price">
                            السعر (<?php echo html_escape(currency_code_and_symbol()); ?>)
                        </label>
                        <input class="tqa-input tqa-input--ltr" type="number" id="price" name="price"
                               min="0" step="0.01" value="<?php echo html_escape($tq_course['price']); ?>">
                    </div>

                    <div class="tqa-field">
                        <label class="tqa-field__label" for="discounted_price">
                            السعر بعد الخصم (<?php echo html_escape(currency_code_and_symbol()); ?>)
                        </label>
                        <input class="tqa-input tqa-input--ltr" type="number" id="discounted_price"
                               name="discounted_price" min="0" step="0.01" data-tqa-discount
                               value="<?php echo html_escape($tq_course['discounted_price']); ?>">
                        <span class="tqa-field__hint">الخصم <span class="tqa-num" data-tqa-discount-pct>—</span></span>
                    </div>
                </div>

                <div class="tqa-prefrow">
                    <div class="tqa-prefrow__main">
                        <label class="tqa-prefrow__title" for="discount_flag">تفعيل الخصم</label>
                        <span class="tqa-prefrow__hint">بدونه يباع بالسعر الأصلي ولو كتب سعر الخصم.</span>
                    </div>
                    <div class="tqa-prefrow__end">
                        <span class="tqa-switch">
                            <input type="checkbox" id="discount_flag" name="discount_flag" value="1"
                                   <?php echo (int) $tq_course['discount_flag'] === 1 ? 'checked' : ''; ?>>
                            <span class="tqa-switch__track" aria-hidden="true"></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="tqa-field" style="margin-block-start:var(--tq-space-l)">
                <span class="tqa-field__label">مدة الوصول</span>
                <div class="tqa-checkrow">
                    <label class="tqa-check">
                        <input type="radio" name="expiry_period" value="lifetime" data-tqa-expiry
                               <?php echo (int) $tq_course['expiry_period'] === 0 ? 'checked' : ''; ?>>
                        <span>وصول دائم</span>
                    </label>
                    <label class="tqa-check">
                        <input type="radio" name="expiry_period" value="limited_time" data-tqa-expiry
                               <?php echo (int) $tq_course['expiry_period'] > 0 ? 'checked' : ''; ?>>
                        <span>مدة محدودة</span>
                    </label>
                </div>
            </div>

            <div class="tqa-field" data-tqa-months <?php echo (int) $tq_course['expiry_period'] > 0 ? '' : 'hidden'; ?>>
                <label class="tqa-field__label" for="number_of_month">عدد الأشهر</label>
                <input class="tqa-input tqa-input--ltr" type="number" id="number_of_month" name="number_of_month"
                       min="1" value="<?php echo (int) $tq_course['expiry_period']; ?>">
            </div>
        </div>


    <?php elseif ($tq_tab === 'media'): ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('video', 20); ?></span>
                <h2>الصور وفيديو النظرة العامة</h2>
            </div>

            <div class="tqa-fieldgrid">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="course_overview_provider">مصدر الفيديو</label>
                    <select class="tqa-select" id="course_overview_provider" name="course_overview_provider">
                        <?php foreach (array('youtube' => 'يوتيوب', 'vimeo' => 'فيميو', 'html5' => 'ملف مرفوع') as $tq_k => $tq_l): ?>
                            <option value="<?php echo $tq_k; ?>"
                                <?php echo $tq_course['video_type'] === $tq_k ? 'selected' : ''; ?>><?php echo $tq_l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="course_overview_url">رابط الفيديو</label>
                    <input class="tqa-input tqa-input--ltr" type="url" id="course_overview_url"
                           name="course_overview_url" dir="ltr"
                           value="<?php echo html_escape($tq_course['video_url']); ?>">
                </div>

                <?php include 'course_media_add.php'; ?>
            </div>
        </div>


    <?php else: /* seo */ ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-rose" aria-hidden="true"><?php echo tq_icon('search', 20); ?></span>
                <h2>تحسين محركات البحث</h2>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="meta_keywords_in">الكلمات الدلالية</label>
                <div class="tqa-tags" data-tqa-tags>
                    <input type="hidden" name="meta_keywords" data-tqa-tags-value
                           value="<?php echo html_escape($tq_course['meta_keywords']); ?>">
                    <input class="tqa-tags__in" type="text" id="meta_keywords_in" autocomplete="off"
                           placeholder="اكتب كلمة ثم اضغط Enter" data-tqa-tags-input>
                </div>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="meta_description">وصف محركات البحث</label>
                <textarea class="tqa-textarea" id="meta_description" name="meta_description" rows="3"
                          maxlength="320"><?php echo html_escape($tq_course['meta_description']); ?></textarea>
            </div>
        </div>

    <?php endif; ?>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> احفظ التعديل
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/courses'); ?>">إلغاء</a>
    </div>
</form>

<?php endif; ?>

<?php if ($tq_editing): ?>
    <?php include 'tqa_file_js.php'; ?>
    <?php include 'tqa_tags_js.php'; ?>
    <?php include 'tqa_repeater_js.php'; ?>
    <?php include 'tqa_course_form_js.php'; ?>
<?php endif; ?>
