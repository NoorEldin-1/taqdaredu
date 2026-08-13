<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تحسين محركات البحث.
 *
 * TQ-BS5-ACCORDION — كانت الأقسام مطوية بـ`data-bs-toggle="collapse"` و
 * `data-bs-target` و`data-bs-parent`: **صيغة Bootstrap 5**. واللوحة تحمل
 * **Bootstrap 4**، وهو يقرأ `data-toggle` و`data-target` بلا `bs`. فلم
 * يكن أي قسم يفتح إطلاقا: الوحيد المفتوح هو الذي يخرج من الخادم بصنف
 * `show` (`$active_tab`)، وبقية الصفحات — الكورسات والمدونة والباقات
 * وتواصل معنا … — **لم يكن ممكنا تحريرها من هذه الشاشة أصلا**. تضغط
 * عنوانها فلا يحدث شيء.
 *
 * والبديل `<details>`: يفتح ويغلق بلا سطر جافاسكربت واحد، ولا يعتمد على
 * إصدار مكتبة.
 *
 * وحذفت الكتل الثلاث المشروطة بـ`addon_status(...)` — `course_bundles` و
 * `bootcamps` و`ebook`. مجلد الإضافات فارغ وجدول `addons` بلا صف، فالشرط
 * كاذب أبدا (انظر CLAUDE.md).
 *
 * وحقل «الكلمات الدلالية» كان `bootstrap-tagsinput` من CDN — انظر
 * TQ-TAGSINPUT-CDN في [tqa_tags_js.php].
 */

/** أسماء المسارات بالعربية — كانت تعرض `ucfirst('contact_us')`. */
$tq_names = array(
    'home'               => 'الصفحة الرئيسية',
    'courses'            => 'الكورسات',
    'course_details'     => 'صفحة الكورس',
    'blog'               => 'المدونة',
    'blog_details'       => 'صفحة المقال',
    'about_us'           => 'من نحن',
    'contact_us'         => 'تواصل معنا',
    'privacy_policy'     => 'سياسة الخصوصية',
    'terms_and_condition' => 'الشروط والأحكام',
    'refund_policy'      => 'سياسة الاسترجاع',
    'faq'                => 'الأسئلة الشائعة',
    'instructor_page'    => 'صفحة المعلم',
    'login'              => 'تسجيل الدخول',
    'sign_up'            => 'إنشاء حساب',
);
?>

<?php tqa_head('تحسين محركات البحث',
    'عنوان كل صفحة ووصفها كما يقرؤهما محرك البحث ومنصات التواصل. اضغط اسم الصفحة لفتح حقولها.',
    'search',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/sitemap_settings') . '">'
  . tq_icon('layers', 16) . ' خريطة الموقع</a>'); ?>

<div class="tqa-stack" style="max-inline-size:900px">
<?php foreach ($seo_meta_tags as $tq_t):

    /* الإضافات غير مثبتة في هذا التركيب، فمسارات صفحاتها لا تعرض. */
    if ((int) $tq_t['is_addon'] === 1) continue;

    $tq_route = $tq_t['route'];
    $tq_slug  = slugify($tq_route);
    $tq_open  = $active_tab === $tq_slug;
    $tq_label = $tq_names[$tq_route] ?? ucfirst(str_replace('_', ' ', $tq_route));

    $tq_og  = 'uploads/seo-og-images/' . $tq_t['og_image'];
    $tq_og  = (trim((string) $tq_t['og_image']) !== '' && is_file(FCPATH . $tq_og))
            ? base_url($tq_og)
            : base_url('uploads/seo-og-images/placeholder.png');

    /* «مضبوطة» تعني أن العنوان والوصف مكتوبان — وهما وحدهما ما يظهر في
       نتيجة البحث. وبقية الحقول تحسين فوقهما. */
    $tq_set = trim((string) $tq_t['meta_title']) !== '' && trim((string) $tq_t['meta_description']) !== '';
?>
    <details class="tqa-card" <?php echo $tq_open ? 'open' : ''; ?>>
        <summary class="tqa-row tqa-row--between" style="cursor:pointer;list-style:none">
            <span class="tqa-row">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true" style="inline-size:36px;block-size:36px">
                    <?php echo tq_icon('globe', 18); ?>
                </span>
                <span>
                    <strong style="color:var(--tq-navy);font:var(--tq-type-bodyStrong)">
                        <?php echo html_escape($tq_label); ?>
                    </strong>
                    <span class="tqa-media__sub tq-ltr" dir="ltr"><?php echo html_escape($tq_route); ?></span>
                </span>
            </span>
            <span class="tqa-badge tqa-badge--<?php echo $tq_set ? 'ok' : 'warn'; ?>">
                <?php echo $tq_set ? 'مضبوطة' : 'ناقصة'; ?>
            </span>
        </summary>

        <form action="<?php echo site_url('admin/seo_settings/update/' . $tq_route); ?>" method="post"
              enctype="multipart/form-data" style="margin-block-start:var(--tq-space-l);
              border-block-start:1px solid var(--tq-line);padding-block-start:var(--tq-space-l)">
            <?php echo tq_csrf(); ?>

            <div class="tqa-fieldgrid">
                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="mt-<?php echo $tq_slug; ?>">عنوان الصفحة في نتائج البحث</label>
                    <input class="tqa-input" type="text" id="mt-<?php echo $tq_slug; ?>" name="meta_title"
                           maxlength="70" value="<?php echo html_escape($tq_t['meta_title']); ?>">
                    <span class="tqa-field__hint">سبعون محرفا فأقل — ما زاد يقص في نتيجة البحث.</span>
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="md-<?php echo $tq_slug; ?>">الوصف في نتائج البحث</label>
                    <textarea class="tqa-textarea" id="md-<?php echo $tq_slug; ?>" name="meta_description"
                              rows="3" maxlength="320"><?php echo html_escape($tq_t['meta_description']); ?></textarea>
                    <span class="tqa-field__hint">مئة وستون محرفا تقرأ كاملة في أغلب المحركات.</span>
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="mk-<?php echo $tq_slug; ?>">الكلمات الدلالية</label>
                    <div class="tqa-tags" data-tqa-tags>
                        <input type="hidden" name="meta_keywords"
                               value="<?php echo html_escape($tq_t['meta_keywords']); ?>" data-tqa-tags-value>
                        <input class="tqa-tags__in" type="text" id="mk-<?php echo $tq_slug; ?>" autocomplete="off"
                               placeholder="اكتب كلمة ثم اضغط Enter" data-tqa-tags-input>
                    </div>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="mr-<?php echo $tq_slug; ?>">تعليمات الزواحف</label>
                    <input class="tqa-input tqa-input--ltr" type="text" id="mr-<?php echo $tq_slug; ?>"
                           name="meta_robot" dir="ltr" placeholder="index, follow"
                           value="<?php echo html_escape($tq_t['meta_robot']); ?>">
                    <span class="tqa-field__hint"><span class="tq-ltr" dir="ltr">noindex</span> يمنع ظهورها في البحث.</span>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="cu-<?php echo $tq_slug; ?>">الرابط المعياري</label>
                    <input class="tqa-input tqa-input--ltr" type="url" id="cu-<?php echo $tq_slug; ?>"
                           name="canonical_url" dir="ltr" placeholder="https://taqdaredu.com/courses"
                           value="<?php echo html_escape($tq_t['canonical_url']); ?>">
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="cx-<?php echo $tq_slug; ?>">رابط مخصص</label>
                    <input class="tqa-input tqa-input--ltr" type="url" id="cx-<?php echo $tq_slug; ?>"
                           name="custom_url" dir="ltr"
                           value="<?php echo html_escape($tq_t['custom_url']); ?>">
                </div>
            </div>

            <div class="tqa-card__head" style="padding:var(--tq-space-l) 0;margin-block:var(--tq-space-m);
                 border-block-start:1px solid var(--tq-line);border-block-end:1px solid var(--tq-line)">
                <span class="tqa-iconbox tqa-sky" aria-hidden="true" style="inline-size:36px;block-size:36px">
                    <?php echo tq_icon('image', 18); ?>
                </span>
                <h2 style="font:var(--tq-type-bodyStrong);font-family:var(--tq-font-title)">
                    ما يظهر عند مشاركة الرابط
                </h2>
            </div>

            <div class="tqa-fieldgrid">
                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="ot-<?php echo $tq_slug; ?>">العنوان عند المشاركة</label>
                    <input class="tqa-input" type="text" id="ot-<?php echo $tq_slug; ?>" name="og_title"
                           value="<?php echo html_escape($tq_t['og_title']); ?>">
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="od-<?php echo $tq_slug; ?>">الوصف عند المشاركة</label>
                    <textarea class="tqa-textarea" id="od-<?php echo $tq_slug; ?>" name="og_description"
                              rows="2"><?php echo html_escape($tq_t['og_description']); ?></textarea>
                </div>

                <div class="tqa-field tqa-field--full">
                    <span class="tqa-field__label">صورة المشاركة</span>
                    <div class="tqa-file">
                        <img class="tqa-file__preview" src="<?php echo html_escape($tq_og); ?>" alt="الصورة الحالية"
                             style="inline-size:120px;block-size:64px">
                        <input type="file" id="oi-<?php echo $tq_slug; ?>" name="og_image" accept="image/*" data-tqa-file>
                        <label class="tqa-file__btn" for="oi-<?php echo $tq_slug; ?>">
                            <?php echo tq_icon('image', 16); ?> استبدل الصورة
                        </label>
                        <span class="tqa-file__name" data-tqa-file-name>المقاس المفضل ‎1200 × 630‎ بكسل</span>
                        <input type="hidden" name="old_og_image" value="<?php echo html_escape($tq_t['og_image']); ?>">
                    </div>
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="jl-<?php echo $tq_slug; ?>">
                        بيانات منظمة (<span class="tq-ltr" dir="ltr">JSON-LD</span>)
                    </label>
                    <textarea class="tqa-textarea tqa-input--ltr" id="jl-<?php echo $tq_slug; ?>" name="json_ld"
                              rows="5" dir="ltr" spellcheck="false"><?php echo html_escape($tq_t['json_ld']); ?></textarea>
                    <span class="tqa-field__hint">اختيارية — تترك فارغة إن لم تكن تعرف ما تكتب فيها.</span>
                </div>
            </div>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--primary">
                    <?php echo tq_icon('check', 16); ?> احفظ <?php echo html_escape($tq_label); ?>
                </button>
            </div>
        </form>
    </details>
<?php endforeach; ?>
</div>

<?php include 'tqa_file_js.php'; ?>
<?php include 'tqa_tags_js.php'; ?>
