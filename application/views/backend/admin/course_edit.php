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
    tqa_head(t('كورس غير موجود'), '', 'book');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty(t('لا كورس بهذا المعرف'), t('قد يكون حذف من شاشة أخرى.'),
        t('العودة إلى الكورسات'), site_url('admin/courses'), 'book');
    echo '</div>';
    return;
}

/* الشاشات الداخلية تقرأ `$course_details` بهذا الاسم. */
$course_details = $tq_course;

$tq_tabs = array(
    'curriculum' => array(t('المقرر'),            'layers'),
    'basic'      => array(t('أساسيات الكورس'),    'book'),
    'info'       => array(t('ما يعرض في صفحته'),  'clipboard'),
    'pricing'    => array(t('التسعير'),           'money'),
    'media'      => array(t('الصور والفيديو'),    'video'),
    'seo'        => array(t('تحسين البحث'),       'search'),
    'fields'     => array(t('حقول مخصصة'),        'grid'),
    'enrolled'   => array(t('المسجلون'),          'users'),
    'progress'   => array(t('التقدم العلمي'),     'chart'),
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

/* الربط بالبرنامج — يقرأ مرة ويستعمله لوح الحالة وبطاقة الحقول.
   والنداء عبر `get_instance()` لا `$this`: القالب يضمن داخل
   `CI_Loader::_ci_load`، فـ`$this` فيه هو المحمل لا المتحكم. والمحمل
   ينسخ خصائص المتحكم إليه **قبل** التضمين، فما يحمل من داخل القالب
   يوضع على المتحكم ولا يرى هنا — و`CI_Loader` بلا `__get` يسده. */
$CI = get_instance();
$CI->load->model('taqdar_course_link_model', 'tq_link_m');
$tq_link  = $CI->tq_link_m->link_of($course_id);
$tq_gaps  = $CI->tq_link_m->diagnose($course_id);
?>

<?php tqa_head(t('تحرير الكورس'), $tq_course['title'], 'book',
    '<a class="tqa-btn tqa-btn--ghost" href="' . $tq_live . '" target="_blank" rel="noopener">'
  . tq_icon('external', 16) . t(' صفحته في الموقع</a>')
  . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/courses') . '">'
  . tq_icon('chev-prev', 16) . t(' كل الكورسات</a>')); ?>

<nav class="tqa-tabs tqa-tabs--scroll" aria-label="<?php echo te('أقسام تحرير الكورس'); ?>">
    <?php foreach ($tq_tabs as $tq_k => [$tq_label, $tq_ic]): ?>
        <a href="<?php echo $tq_url($tq_k); ?>" <?php echo $tq_tab === $tq_k ? 'aria-current="page"' : ''; ?>>
            <?php echo html_escape($tq_label); ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php
/**
 * لوح الوصول — ما يفصل هذا الكورس عن الطالب.
 *
 * كانت الشاشة تقول «منشور» ولا تقول أكثر، بينما «منشور» في `course` لا
 * تعني ظاهرا: الكتالوج ومحرك الاشتراكات يقرآن من `paths` وحده. فمن
 * أنشأ كورسا ونشره ثم لم يجده في الموقع لم يكن أمامه ما يفسر ذلك، ولا
 * موضع يصلحه منه. وهذه الأسطر تقولها بترتيب معالجتها.
 */
if ($tq_gaps): ?>
    <div class="tqa-stack tqa-section">
    <?php foreach ($tq_gaps as [$tq_tone, $tq_t, $tq_b, $tq_href]): ?>
        <p class="tqa-note<?php echo $tq_tone === 'warn' ? ' tqa-note--warn' : ''; ?>">
            <span aria-hidden="true"><?php echo tq_icon($tq_tone === 'warn' ? 'alert' : 'help', 18); ?></span>
            <span style="flex:1">
                <strong><?php echo html_escape($tq_t); ?></strong>
                <span style="display:block"><?php echo $tq_b; ?></span>
            </span>
            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="<?php echo html_escape($tq_href); ?>"><?php echo t('عالجها'); ?></a>
        </p>
    <?php endforeach; ?>
    </div>
<?php elseif ($tq_link['path_id'] > 0): ?>
    <p class="tqa-note tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        <span style="flex:1">
            <strong><?php echo t('هذا الكورس يصل إلى الطالب.'); ?></strong>
            <?php echo t('منشور، وله صف ومادة، وباقة تفتحه.'); ?>
        </span>
        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" target="_blank" rel="noopener"
           href="<?php echo site_url('path/' . rawurlencode($tq_link['slug'])); ?>"><?php echo t('صفحته في «المواد والبرامج»'); ?></a>
    </p>
<?php endif; ?>


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

    <?php
    /**
     * التبويب المعروض وحده يرسل حقوله؛ وما لا يعرض يحمل قيمه الحالية في
     * حقول مخفية.
     *
     * TQ-TAB-WIPE — و`Crud_model::update_course()` **تكتب كل عمود في كل
     * حفظ**، لا ما أرسل منها. فكل حقل لا يقابله مخفي يمحى: كان الحمل
     * أحد عشر حقلا من نيف وعشرين، فحفظ «تحسين البحث» — وهو تبويب بحقلين
     * — كان يمسح المتطلبات والمخرجات والأسئلة الشائعة، ويرد «كورس مميز»
     * و«الإتاحة التدريجية» إلى الإطفاء، ويفرغ الخصم ومدة الوصول ولغة
     * المحتوى ومصدر الفيديو. أي أن **تحرير أي تبويب كان يهدم البقية**.
     *
     * واثنان من الأحد عشر لم يكونا يعملان أصلا: `video_type` عمود لا
     * وجود له في `course` (اسمه `course_overview_provider`)، فيسقط
     * الشرط `array_key_exists` ولا يكتب المخفي — ثم تقرأ الدالة
     * `course_overview_url` غير الفارغة فتخزن مصدرا فارغا.
     *
     * فالخريطة هنا **كاملة**: مفتاحها اسم الحقل في النموذج، وقيمتها
     * اسم العمود. وما كان مصفوفة (المتطلبات · المخرجات · الأسئلة) يعاد
     * بنودا مخفية لا سطرا واحدا.
     */
    $tq_carry = array(
        'basic'   => array('title' => 'title', 'short_description' => 'short_description',
                           'description' => 'description', 'sub_category_id' => 'sub_category_id',
                           'level' => 'level', 'language_made_in' => 'language',
                           'status' => 'status', 'publish_date' => 'publish_date',
                           'is_top_course' => 'is_top_course',
                           'enable_drip_content' => 'enable_drip_content'),
        'pricing' => array('price' => 'price', 'discounted_price' => 'discounted_price',
                           'discount_flag' => 'discount_flag', 'is_free_course' => 'is_free_course'),
        'seo'     => array('meta_keywords' => 'meta_keywords', 'meta_description' => 'meta_description'),
        'media'   => array('course_overview_url' => 'video_url',
                           'course_overview_provider' => 'course_overview_provider'),
    );

    foreach ($tq_carry as $tq_group => $tq_names):
        if ($tq_group === $tq_tab) continue;
        foreach ($tq_names as $tq_field => $tq_col):
            if (!array_key_exists($tq_col, $tq_course)) continue;
            $tq_v = (string) $tq_course[$tq_col];
            /* الصفر في خانة تأشير يعني «غير مؤشرة»، والمؤشرة ترسل «1».
               وإرسال «0» يجعل `$this->input->post(...)` صادقة فيقلب
               المعنى — فالحقل يحذف بدل أن يرسل صفرا. */
            if (in_array($tq_field, array('is_top_course', 'enable_drip_content',
                                          'discount_flag', 'is_free_course'), true)
                && (int) $tq_v !== 1) continue;
    ?>
        <input type="hidden" name="<?php echo html_escape($tq_field); ?>"
               value="<?php echo html_escape($tq_v); ?>">
    <?php endforeach; endforeach; ?>

    <?php
    /* مدة الوصول: عمود واحد يمثل بحقلين في النموذج، فيعاد بناؤهما. */
    if ($tq_tab !== 'pricing'):
        $tq_months = (int) $tq_course['expiry_period'];
    ?>
        <input type="hidden" name="expiry_period"
               value="<?php echo $tq_months > 0 ? 'limited_time' : 'lifetime'; ?>">
        <?php if ($tq_months > 0): ?>
            <input type="hidden" name="number_of_month" value="<?php echo $tq_months; ?>">
        <?php endif; ?>
    <?php endif; ?>

    <?php
    /* المصفوفات الثلاث. `faqs` تقبل الشكلين: القائمة التي تكتب اليوم،
       والخريطة التي كتبت قبل إصلاح TQ-FAQ-SHAPE. */
    if ($tq_tab !== 'info'):
        $tq_c_reqs = json_decode((string) $tq_course['requirements'], true);
        $tq_c_outs = json_decode((string) $tq_course['outcomes'], true);
        foreach (array('requirements' => $tq_c_reqs, 'outcomes' => $tq_c_outs) as $tq_n => $tq_vals):
            foreach (is_array($tq_vals) ? $tq_vals : array() as $tq_v): ?>
                <input type="hidden" name="<?php echo $tq_n; ?>[]" value="<?php echo html_escape($tq_v); ?>">
            <?php endforeach;
        endforeach;
        foreach (tqa_course_faqs($tq_course['faqs']) as $tq_f): ?>
            <input type="hidden" name="faqs[]" value="<?php echo html_escape($tq_f['title']); ?>">
            <input type="hidden" name="faq_descriptions[]" value="<?php echo html_escape($tq_f['description']); ?>">
        <?php endforeach;
    endif; ?>

    <?php /* الربط لا يحمل في مخفي: `sync()` لا تنادى إلا حين يرسل
             `tq_link_sent`، وهو في تبويب «الأساسيات» وحده. */ ?>


    <?php if ($tq_tab === 'basic'): ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('book', 20); ?></span>
                <h2><?php echo t('أساسيات الكورس'); ?></h2>
            </div>

            <div class="tqa-fieldgrid">
                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="course_title">
                        <?php echo t('عنوان الكورس'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <input class="tqa-input" type="text" id="course_title" name="title" required maxlength="190"
                           value="<?php echo html_escape($tq_course['title']); ?>">
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="sub_category_id">
                        <?php echo t('المرحلة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <select class="tqa-select" id="sub_category_id" name="sub_category_id" required>
                        <option value=""><?php echo t('— اختر مرحلة'); ?></option>
                        <?php echo tqa_category_options((int) $tq_course['sub_category_id']); ?>
                    </select>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="level"><?php echo t('المستوى'); ?></label>
                    <select class="tqa-select" id="level" name="level">
                        <?php foreach (array('beginner' => t('مبتدئ'), 'intermediate' => t('متوسط'), 'advanced' => t('متقدم')) as $tq_k => $tq_l): ?>
                            <option value="<?php echo $tq_k; ?>"
                                <?php echo $tq_course['level'] === $tq_k ? 'selected' : ''; ?>><?php echo $tq_l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="language_made_in"><?php echo t('لغة المحتوى'); ?></label>
                    <select class="tqa-select" id="language_made_in" name="language_made_in">
                        <?php /* العمود اسمه `language` لا `language_made_in`: الثاني اسم
                                 الحقل في النموذج وحده. وقراءته من الصف كانت تطبع تحذير
                                 PHP **داخل وسم `<option>`** في كل فتح للتبويب — وهو ما
                                 يظهر للمسؤول سطر خطأ مكان اسم اللغة. */ ?>
                        <?php foreach ($languages as $tq_l): ?>
                            <option value="<?php echo html_escape($tq_l); ?>"
                                <?php echo (string) $tq_course['language'] === (string) $tq_l ? 'selected' : ''; ?>>
                                <?php echo html_escape(ucfirst($tq_l)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="short_description"><?php echo t('وصف مختصر'); ?></label>
                    <input class="tqa-input" type="text" id="short_description" name="short_description" maxlength="255"
                           value="<?php echo html_escape($tq_course['short_description']); ?>">
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="description"><?php echo t('الوصف الكامل'); ?></label>
                    <textarea class="tqa-textarea" id="description" name="description" rows="6" data-tqa-rich><?php
                        echo html_escape($tq_course['description']); ?></textarea>
                </div>
            </div>

            <div class="tqa-field" style="margin-block-start:var(--tq-space-l)">
                <span class="tqa-field__label"><?php echo t('حالة الكورس'); ?></span>
                <div class="tqa-stack">
                    <?php foreach (array(
                        'active'   => array(t('منشور'), t('يظهر في الموقع العام ويمكن الاشتراك فيه.')),
                        'private'  => array(t('خاص'), t('لا يظهر في القوائم — يفتح برابطه وحده.')),
                        'upcoming' => array(t('قادم'), t('يعرض بتاريخ نشر ولا يفتح قبله.')),
                        'pending'  => array(t('قيد المراجعة'), t('أرسله معلم وينتظر قرار الإدارة.')),
                        'draft'    => array(t('مسودة'), t('غير مكتمل ولا يعرض لأحد.')),
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
                <?php /* حقل «تاريخ النشر» كان غائبا عن هذه الشاشة وحاضرا في شاشة
                         الإضافة، و`update_course` تكتب `publish_date` من الطلب في كل
                         حفظ — فأول تعديل بعد الإنشاء يمحو التاريخ، ويصير «الكورس
                         القادم» بلا موعد يعرض. */ ?>
                <div class="tqa-field">
                    <label class="tqa-field__label" for="input_publish_date"><?php echo t('تاريخ النشر'); ?></label>
                    <input class="tqa-input tqa-input--ltr" type="datetime-local" id="input_publish_date"
                           name="publish_date" dir="ltr"
                           value="<?php echo html_escape($tq_course['publish_date']); ?>">
                </div>

                <div class="tqa-field">
                    <span class="tqa-field__label"><?php echo t('صورة الكورس القادم'); ?></span>
                    <div class="tqa-file">
                        <input type="file" id="upcoming_image_thumbnail" name="upcoming_image_thumbnail"
                               accept="image/*" data-tqa-file>
                        <label class="tqa-file__btn" for="upcoming_image_thumbnail">
                            <?php echo tq_icon('image', 16); ?> <?php echo t('اختر صورة'); ?>
                        </label>
                        <span class="tqa-file__name" data-tqa-file-name><?php echo t('المقاس المفضل ‎365 × 460‎'); ?></span>
                        <input type="hidden" name="old_upcoming_image_thumbnail"
                               value="<?php echo html_escape($tq_course['upcoming_image_thumbnail']); ?>">
                    </div>
                </div>
            </div>

            <div class="tqa-prefrow">
                <div class="tqa-prefrow__main">
                    <label class="tqa-prefrow__title" for="is_top_course"><?php echo t('كورس مميز'); ?></label>
                    <span class="tqa-prefrow__hint"><?php echo t('يعرض في شريط «الأبرز» في الصفحة الرئيسية.'); ?></span>
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
                    <label class="tqa-prefrow__title" for="enable_drip_content"><?php echo t('إتاحة الدروس تدريجيا'); ?></label>
                    <span class="tqa-prefrow__hint"><?php echo t('الدرس لا يفتح إلا بعد سابقه.'); ?></span>
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


        <?php /* صاحب الكورس: يرسل من هذا التبويب وحده (`tq_owner_sent`). */ ?>
        <?php include 'tqa_course_owner_field.php'; ?>
        <?php /* الربط: يرسل من هذا التبويب وحده، ولذلك `tq_link_sent`. */ ?>
        <input type="hidden" name="tq_link_sent" value="1">
        <?php include 'tqa_course_link_fields.php'; ?>


    <?php elseif ($tq_tab === 'info'):
        $tq_faqs  = tqa_course_faqs($tq_course['faqs']);
        $tq_reqs  = json_decode((string) $tq_course['requirements'], true);
        $tq_outs  = json_decode((string) $tq_course['outcomes'], true);
        if (!$tq_faqs) $tq_faqs = array(array('title' => '', 'description' => ''));
        if (!is_array($tq_reqs) || !$tq_reqs) $tq_reqs = array('');
        if (!is_array($tq_outs) || !$tq_outs) $tq_outs = array('');
    ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-sand" aria-hidden="true"><?php echo tq_icon('clipboard', 20); ?></span>
                <h2><?php echo t('ما يعرض في صفحة الكورس'); ?></h2>
            </div>

            <div class="tqa-field">
                <span class="tqa-field__label"><?php echo t('المتطلبات السابقة'); ?></span>
                <div data-tqa-rep="requirements">
                    <?php foreach ($tq_reqs as $tq_r): ?>
                        <div data-tqa-rep-item class="tqa-row" style="flex-wrap:nowrap;margin-block-end:var(--tq-space-s)">
                            <input class="tqa-input" type="text" name="requirements[]"
                                   value="<?php echo html_escape($tq_r); ?>">
                            <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--quiet-danger" data-tqa-rep-remove aria-label="<?php echo te('احذف هذا المتطلب'); ?>">
                                <?php echo tq_icon('trash', 15); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-add="requirements">
                    <?php echo tq_icon('plus', 14); ?> <?php echo t('أضف متطلبا'); ?>
                </button>
            </div>

            <div class="tqa-field">
                <span class="tqa-field__label"><?php echo t('مخرجات التعلم'); ?></span>
                <div data-tqa-rep="outcomes">
                    <?php foreach ($tq_outs as $tq_o): ?>
                        <div data-tqa-rep-item class="tqa-row" style="flex-wrap:nowrap;margin-block-end:var(--tq-space-s)">
                            <input class="tqa-input" type="text" name="outcomes[]"
                                   value="<?php echo html_escape($tq_o); ?>">
                            <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--quiet-danger" data-tqa-rep-remove aria-label="<?php echo te('احذف هذا المخرج'); ?>">
                                <?php echo tq_icon('trash', 15); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-add="outcomes">
                    <?php echo tq_icon('plus', 14); ?> <?php echo t('أضف مخرجا'); ?>
                </button>
            </div>

            <div class="tqa-field">
                <span class="tqa-field__label"><?php echo t('أسئلة شائعة عن الكورس'); ?></span>
                <div data-tqa-rep="faqs">
                    <?php foreach ($tq_faqs as $tq_f): ?>
                        <div data-tqa-rep-item class="tqa-card" style="box-shadow:none;border-style:dashed;
                             margin-block-end:var(--tq-space-s)">
                            <input class="tqa-input" type="text" name="faqs[]" placeholder="<?php echo te('السؤال'); ?>"
                                   style="margin-block-end:var(--tq-space-s)"
                                   value="<?php echo html_escape($tq_f['title'] ?? ''); ?>">
                            <textarea class="tqa-textarea" name="faq_descriptions[]" rows="2" placeholder="<?php echo te('الإجابة'); ?>"
                                      style="min-block-size:70px"><?php echo html_escape($tq_f['description'] ?? ''); ?></textarea>
                            <div class="tqa-actions" style="margin-block-start:var(--tq-space-s)">
                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm tqa-btn--quiet-danger" data-tqa-rep-remove>
                                    <?php echo tq_icon('trash', 14); ?> <?php echo t('احذف هذا السؤال'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-add="faqs">
                    <?php echo tq_icon('plus', 14); ?> <?php echo t('أضف سؤالا'); ?>
                </button>
            </div>
        </div>


    <?php elseif ($tq_tab === 'pricing'): ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('money', 20); ?></span>
                <h2><?php echo t('التسعير'); ?></h2>
            </div>

            <div class="tqa-prefrow">
                <div class="tqa-prefrow__main">
                    <label class="tqa-prefrow__title" for="is_free_course"><?php echo t('كورس مجاني'); ?></label>
                    <span class="tqa-prefrow__hint"><?php echo t('يفتح لكل مسجل بلا دفع.'); ?></span>
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
                            <?php echo t('السعر ('); ?><?php echo html_escape(currency_code_and_symbol()); ?>)
                        </label>
                        <input class="tqa-input tqa-input--ltr" type="number" id="price" name="price"
                               min="0" step="0.01" value="<?php echo html_escape($tq_course['price']); ?>">
                    </div>

                    <div class="tqa-field">
                        <label class="tqa-field__label" for="discounted_price">
                            <?php echo t('السعر بعد الخصم ('); ?><?php echo html_escape(currency_code_and_symbol()); ?>)
                        </label>
                        <input class="tqa-input tqa-input--ltr" type="number" id="discounted_price"
                               name="discounted_price" min="0" step="0.01" data-tqa-discount
                               value="<?php echo html_escape($tq_course['discounted_price']); ?>">
                        <span class="tqa-field__hint"><?php echo t('الخصم'); ?> <span class="tqa-num" data-tqa-discount-pct>—</span></span>
                    </div>
                </div>

                <div class="tqa-prefrow">
                    <div class="tqa-prefrow__main">
                        <label class="tqa-prefrow__title" for="discount_flag"><?php echo t('تفعيل الخصم'); ?></label>
                        <span class="tqa-prefrow__hint"><?php echo t('بدونه يباع بالسعر الأصلي ولو كتب سعر الخصم.'); ?></span>
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
                <span class="tqa-field__label"><?php echo t('مدة الوصول'); ?></span>
                <div class="tqa-checkrow">
                    <label class="tqa-check">
                        <input type="radio" name="expiry_period" value="lifetime" data-tqa-expiry
                               <?php echo (int) $tq_course['expiry_period'] === 0 ? 'checked' : ''; ?>>
                        <span><?php echo t('وصول دائم'); ?></span>
                    </label>
                    <label class="tqa-check">
                        <input type="radio" name="expiry_period" value="limited_time" data-tqa-expiry
                               <?php echo (int) $tq_course['expiry_period'] > 0 ? 'checked' : ''; ?>>
                        <span><?php echo t('مدة محدودة'); ?></span>
                    </label>
                </div>
            </div>

            <div class="tqa-field" data-tqa-months <?php echo (int) $tq_course['expiry_period'] > 0 ? '' : 'hidden'; ?>>
                <label class="tqa-field__label" for="number_of_month"><?php echo t('عدد الأشهر'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="number" id="number_of_month" name="number_of_month"
                       min="1" value="<?php echo (int) $tq_course['expiry_period']; ?>">
                <span class="tqa-field__hint"><?php echo t('وهي مدة وصول'); ?> <b><?php echo t('المشتري المفرد'); ?></b> <?php echo t('كذلك: من اشترى الكورس وحده يفتح له هذه المدة، و«وصول دائم» يعني بلا انتهاء.'); ?></span>
            </div>
        </div>

        <?php
        /* TQ-COURSE-SALE — البيع المفرد في بطاقته لا مبعثرا بين حقول السعر.
           والسبب أنه سؤال آخر: «بكم يباع» سؤال، و«أيباع أصلا خارج الباقة»
           سؤال ثان — وخانة تسبح بين حقول السعر تقرأ إعدادا فنيا لا قرار
           عمل.

           وكل ما فيها يقرأ من `offer()` نفسها التي تعرض في الموقع، فما
           تراه هنا هو ما يراه المشتري بالهللة. */
        $tq_cs_ci = get_instance();
        $tq_cs_ci->load->model('taqdar_course_sale_model', 'tq_cs');
        $tq_offer = $tq_cs_ci->tq_cs->offer($course_id);
        $tq_scfg  = $tq_cs_ci->tq_cs->config();
        $tq_sold  = $tq_cs_ci->tq_cs->sold_counts();
        $tq_sold  = isset($tq_sold[(int) $course_id]) ? $tq_sold[(int) $course_id] : null;
        $tq_pc    = $tq_course['tq_teacher_percent'] ?? null;
        ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('card', 20); ?></span>
                <div>
                    <h2><?php echo t('البيع المفرد'); ?></h2>
                    <p class="tqa-card__lead"><?php echo t('بيع هذا الكورس وحده خارج الباقات — لمن يريد مادة بعينها لا منهج مرحلة كاملا.'); ?></p>
                </div>
            </div>

            <?php /* العلامة تقول للمتحكم إن هذا التبويب عرض الخانة — بلاها
                     يقرأ غيابها «أطفئ» فيسحب كل كورس معروض من البيع عند
                     حفظ أي تبويب آخر. وهي علة TQ-TAB-WIPE نفسها. */ ?>
            <input type="hidden" name="tq_sale_sent" value="1">

            <div class="tqa-prefrow">
                <div class="tqa-prefrow__main">
                    <label class="tqa-prefrow__title" for="tq_sell"><?php echo t('يباع مفردا'); ?></label>
                    <span class="tqa-prefrow__hint"><?php echo t('يظهر بسعره في «المواد والبرامج» وفي صفحته، ويشترى بالبطاقة أو بتحويل بنكي كما تشترى الباقة.'); ?></span>
                </div>
                <div class="tqa-prefrow__end">
                    <span class="tqa-switch">
                        <input type="checkbox" id="tq_sell" name="tq_sell" value="1" data-tqa-sell
                               <?php echo !empty($tq_offer['marked']) ? 'checked' : ''; ?>>
                        <span class="tqa-switch__track" aria-hidden="true"></span>
                    </span>
                </div>
            </div>

            <div class="tqa-fieldgrid" style="margin-block-start:var(--tq-space-l)">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="tq_teacher_percent"><?php echo t('نصيب المعلم %'); ?></label>
                    <input class="tqa-input tqa-input--ltr" type="number" id="tq_teacher_percent"
                           name="tq_teacher_percent" min="0" max="100" step="0.01" data-tqa-share
                           placeholder="<?php echo te('____ — الافتراض العام', array(html_escape($tq_scfg['percent']))); ?>"
                           value="<?php echo $tq_pc === null ? '' : html_escape($tq_pc); ?>">
                    <?php /* **الفارغ غير الصفر.** وقوله صراحة يمنع أن يكتب
                             مسؤول صفرا ظانا أنه «يرجعه إلى الافتراضي»
                             فيحرم معلما من نصيبه بلا أن يقصد. */ ?>
                    <span class="tqa-field__hint"><?php echo t('اتركه فارغا ليأخذ الافتراض العام ('); ?><span class="tqa-num"><?php echo html_escape($tq_scfg['percent']); ?><?php echo t('٪'); ?></span><?php echo t('). والصفر يعني'); ?> <b><?php echo t('صفرا بقرار'); ?></b> <?php echo t('لا «الافتراضي». والباقي عمولة المنصة.'); ?></span>
                </div>

                <div class="tqa-field">
                    <span class="tqa-field__label"><?php echo t('القسمة على السعر المحفوظ'); ?></span>
                    <p class="tqa-note" style="margin:0">
                        <span aria-hidden="true"><?php echo tq_icon('money', 18); ?></span>
                        <span style="flex:1">
                            <?php if ((int) $tq_offer['price'] > 0): ?>
                                <?php echo t('من'); ?> <b class="tqa-num"><?php
                                    echo number_format($tq_offer['price'] / 100, 2); ?></b> <?php echo t('ر.س: للمعلم'); ?> <b class="tqa-num"><?php
                                    echo number_format($tq_offer['share'] / 100, 2); ?></b> <?php echo t('ر.س، وللمنصة'); ?> <b class="tqa-num"><?php
                                    echo number_format($tq_offer['platform'] / 100, 2); ?></b> <?php echo t('ر.س.'); ?>
                                <?php /* «المحفوظ» لا «المكتوب»: هذه الأرقام
                                         من الصف لا من الحقول أعلاه، وتتغير
                                         بعد الحفظ لا قبله. وقولها يمنع أن
                                         يظنها المسؤول لا تستجيب. */ ?>
                            <?php else: ?>
                                <?php echo t('لا سعر بعد — اكتب السعر أعلاه واحفظ لترى القسمة.'); ?>
                            <?php endif; ?>
                        </span>
                    </p>
                </div>
            </div>

            <?php /* **الحال تقال، لا تترك للمسؤول أن يستنتجها.** كورس علم
                     للبيع وهو غير منشور، أو بلا سعر، أو بلا درس — يجلس
                     معلما ولا يعرض، ولا شيء في الشاشة يفسر. و`why` تقول
                     أول ما يمنع بترتيب معالجته. */ ?>
            <p class="tqa-note<?php echo $tq_offer['sellable'] ? '' : ' tqa-note--warn'; ?>"
               style="margin-block-start:var(--tq-space-l)">
                <span aria-hidden="true"><?php echo tq_icon($tq_offer['sellable'] ? 'check-badge' : 'alert', 18); ?></span>
                <span style="flex:1">
                    <strong><?php echo $tq_offer['sellable']
                        ? t('معروض للبيع المفرد الآن.') : t('لا يباع مفردا الآن.'); ?></strong>
                    <span style="display:block"><?php echo html_escape($tq_offer['why']); ?></span>
                </span>
                <?php if ($tq_offer['reason'] === 'disabled'): ?>
                    <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                       href="<?php echo site_url('taqdar_admin/course_sales'); ?>"><?php echo t('افتح الباب'); ?></a>
                <?php elseif ($tq_offer['sellable']): ?>
                    <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                       href="<?php echo site_url('course-checkout/' . (int) $course_id); ?>"
                       target="_blank" rel="noopener"><?php echo t('شاشة الشراء'); ?></a>
                <?php endif; ?>
            </p>

            <?php if ($tq_sold): ?>
                <p class="tqa-note" style="margin-block-start:var(--tq-space-m)">
                    <span aria-hidden="true"><?php echo tq_icon('chart', 18); ?></span>
                    <span style="flex:1">
                        <strong><?php echo t('بيع مفردا'); ?> <span class="tqa-num"><?php echo (int) $tq_sold['n']; ?></span> <?php echo t('مرة'); ?></strong>
                        <span style="display:block"><?php echo t('بمحصل'); ?>
                            <span class="tqa-num"><?php echo number_format($tq_sold['gross'] / 100); ?></span> <?php echo t('ر.س.'); ?>
                            <?php /* وتعديل السعر لا يمس ما بيع: `subscriptions.price`
                                     ينسخ وقت الشراء. وقولها هنا يمنع سؤالا يتكرر. */ ?>
                            <?php echo t('وتعديل السعر لا يغير ما بيع — السعر ينسخ وقت الشراء.'); ?></span>
                    </span>
                </p>
            <?php endif; ?>
        </div>


    <?php elseif ($tq_tab === 'media'): ?>

        <div class="tqa-card tqa-section">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('video', 20); ?></span>
                <h2><?php echo t('الصور وفيديو النظرة العامة'); ?></h2>
            </div>

            <div class="tqa-fieldgrid">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="course_overview_provider"><?php echo t('مصدر الفيديو'); ?></label>
                    <select class="tqa-select" id="course_overview_provider" name="course_overview_provider">
                        <?php /* العمود `course_overview_provider`. و`video_type` عمود في
                                 `lesson` لا في `course`، فقراءته هنا كانت تحذيرا مطبوعا
                                 في كل خيار من الثلاثة. */ ?>
                        <?php foreach (array('youtube' => t('يوتيوب'), 'vimeo' => t('فيميو'), 'html5' => t('ملف مرفوع')) as $tq_k => $tq_l): ?>
                            <option value="<?php echo $tq_k; ?>"
                                <?php echo (string) $tq_course['course_overview_provider'] === $tq_k ? 'selected' : ''; ?>><?php echo $tq_l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="course_overview_url"><?php echo t('رابط الفيديو'); ?></label>
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
                <h2><?php echo t('تحسين محركات البحث'); ?></h2>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="meta_keywords_in"><?php echo t('الكلمات الدلالية'); ?></label>
                <div class="tqa-tags" data-tqa-tags>
                    <input type="hidden" name="meta_keywords" data-tqa-tags-value
                           value="<?php echo html_escape($tq_course['meta_keywords']); ?>">
                    <input class="tqa-tags__in" type="text" id="meta_keywords_in" autocomplete="off"
                           placeholder="<?php echo te('اكتب كلمة ثم اضغط Enter'); ?>" data-tqa-tags-input>
                </div>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="meta_description"><?php echo t('وصف محركات البحث'); ?></label>
                <textarea class="tqa-textarea" id="meta_description" name="meta_description" rows="3"
                          maxlength="320"><?php echo html_escape($tq_course['meta_description']); ?></textarea>
            </div>
        </div>

    <?php endif; ?>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ التعديل'); ?>
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/courses'); ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>

<?php endif; ?>

<?php if ($tq_editing): ?>
    <?php include 'tqa_file_js.php'; ?>
    <?php include 'tqa_tags_js.php'; ?>
    <?php include 'tqa_repeater_js.php'; ?>
    <?php include 'tqa_course_form_js.php'; ?>
<?php endif; ?>
