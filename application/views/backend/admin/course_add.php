<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إضافة كورس.
 *
 * TQ-WIZARD-BLANK — كانت معالج ست خطوات من قالب Hyper، وفيه فوق أعطال
 * المعالج المعتادة (انظر [admin_add.php]) عطلان خاصان به:
 *
 * ١ — **شريط تبويبات بزري تمرير** (`scrollable-tab-btn-left/right`)
 *     يحركان الشريط بجافاسكربت. وهما «يسار» و«يمين» حرفيتان في صفحة
 *     عربية، فالسهم الذي يشير إلى التالي يمرر إلى السابق.
 * ٢ — **الحقل المطلوب مخفي خلف خطوة.** «التصنيف» مطلوب وهو في الخطوة
 *     الأولى، و«تاريخ النشر» مطلوب متى اختير «كورس قادم». والمتصفح
 *     يرفض الإرسال لحقل مطلوب لا يستطيع عرضه — ويصمت. فمن ملأ كل شيء
 *     وضغط «حفظ» في الخطوة السادسة لا يحدث شيء ولا يقال له لماذا.
 *
 * والبديل نموذج مسطح في بطاقات معنونة. وهو الشكل نفسه الذي يبنى به
 * «رفع درس» في بوابة المعلم — فالمعلم والمسؤول يريان الشاشة نفسها.
 */
?>

<?php tqa_head('إضافة كورس', 'الكورس وعاء الدروس. تضاف أقسامه ودروسه بعد حفظه.', 'book',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/courses') . '">'
  . tq_icon('chev-prev', 16) . ' كل الكورسات</a>'); ?>

<form class="required-form" action="<?php echo site_url('admin/course_actions/add'); ?>" method="post"
      enctype="multipart/form-data" style="max-inline-size:900px">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="course_type" value="general">

    <?php /* ---------- أساسي ---------- */ ?>
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
                       placeholder="مثال: الرياضيات — الصف الرابع الابتدائي">
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
                                <option value="<?php echo (int) $tq_s['id']; ?>"><?php echo html_escape($tq_s['name']); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <span class="tqa-field__hint">التصنيف الفرعي — لا الأب.</span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="level">المستوى</label>
                <select class="tqa-select" id="level" name="level">
                    <option value="beginner">مبتدئ</option>
                    <option value="intermediate">متوسط</option>
                    <option value="advanced">متقدم</option>
                </select>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="language_made_in">لغة المحتوى</label>
                <select class="tqa-select" id="language_made_in" name="language_made_in">
                    <?php foreach ($languages as $tq_l): ?>
                        <option value="<?php echo html_escape($tq_l); ?>"><?php echo html_escape(ucfirst($tq_l)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="short_description">وصف مختصر</label>
                <input class="tqa-input" type="text" id="short_description" name="short_description" maxlength="255">
                <span class="tqa-field__hint">سطر واحد يظهر تحت العنوان في بطاقة الكورس.</span>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="description">الوصف الكامل</label>
                <textarea class="tqa-textarea" id="description" name="description" rows="6" data-tqa-rich></textarea>
            </div>
        </div>
    </div>

    <?php /* ---------- الصف والمادة ---------- */ ?>
    <?php include 'tqa_course_link_fields.php'; ?>

    <?php /* ---------- النشر ---------- */ ?>
    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('eye', 20); ?></span>
            <h2>النشر والظهور</h2>
        </div>

        <div class="tqa-field">
            <span class="tqa-field__label">حالة الكورس</span>
            <div class="tqa-stack">
                <?php foreach (array(
                    'active'   => array('منشور', 'يظهر في الموقع العام ويمكن الاشتراك فيه.'),
                    'private'  => array('خاص', 'لا يظهر في القوائم — يفتح برابطه وحده.'),
                    'upcoming' => array('قادم', 'يعرض بتاريخ نشر ولا يفتح قبله.'),
                ) as $tq_k => [$tq_l, $tq_h]): ?>
                    <label class="tqa-check">
                        <input type="radio" name="status" value="<?php echo $tq_k; ?>"
                               data-tqa-status <?php echo $tq_k === 'active' ? 'checked' : ''; ?>>
                        <span>
                            <strong style="color:var(--tq-navy)"><?php echo $tq_l; ?></strong>
                            <span class="tqa-prefrow__hint"><?php echo $tq_h; ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <?php /* حقول «القادم» تظهر مع اختياره وحده — وكانت ظاهرة دائما،
                 فيقرأ من ينشر كورسا عاديا حقل «تاريخ النشر» ويظن أنه مطلوب. */ ?>
        <div data-tqa-upcoming hidden>
            <div class="tqa-fieldgrid">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="input_publish_date">تاريخ النشر</label>
                    <input class="tqa-input tqa-input--ltr" type="datetime-local" id="input_publish_date"
                           name="publish_date">
                </div>

                <div class="tqa-field">
                    <span class="tqa-field__label">صورة الكورس القادم</span>
                    <div class="tqa-file">
                        <input type="file" id="upcoming_image_thumbnail" name="upcoming_image_thumbnail"
                               accept="image/*" data-tqa-file>
                        <label class="tqa-file__btn" for="upcoming_image_thumbnail">
                            <?php echo tq_icon('image', 16); ?> اختر صورة
                        </label>
                        <span class="tqa-file__name" data-tqa-file-name>المقاس المفضل ‎365 × 460‎</span>
                    </div>
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
                    <input type="checkbox" id="is_top_course" name="is_top_course" value="1">
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
                    <input type="checkbox" id="enable_drip_content" name="enable_drip_content" value="1">
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>
    </div>

    <?php /* ---------- التسعير ---------- */ ?>
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
                    <input type="checkbox" id="is_free_course" name="is_free_course" value="1" data-tqa-free>
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
                    <input class="tqa-input tqa-input--ltr" type="number" id="price" name="price" min="0" step="0.01">
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="discounted_price">
                        السعر بعد الخصم (<?php echo html_escape(currency_code_and_symbol()); ?>)
                    </label>
                    <input class="tqa-input tqa-input--ltr" type="number" id="discounted_price"
                           name="discounted_price" min="0" step="0.01" data-tqa-discount>
                    <span class="tqa-field__hint">
                        الخصم <span class="tqa-num" data-tqa-discount-pct>0%</span>
                    </span>
                </div>
            </div>

            <div class="tqa-prefrow">
                <div class="tqa-prefrow__main">
                    <label class="tqa-prefrow__title" for="discount_flag">تفعيل الخصم</label>
                    <span class="tqa-prefrow__hint">بدونه يباع بالسعر الأصلي ولو كتب سعر الخصم.</span>
                </div>
                <div class="tqa-prefrow__end">
                    <span class="tqa-switch">
                        <input type="checkbox" id="discount_flag" name="discount_flag" value="1">
                        <span class="tqa-switch__track" aria-hidden="true"></span>
                    </span>
                </div>
            </div>
        </div>

        <div class="tqa-field" style="margin-block-start:var(--tq-space-l)">
            <span class="tqa-field__label">مدة الوصول</span>
            <div class="tqa-checkrow">
                <label class="tqa-check">
                    <input type="radio" name="expiry_period" value="lifetime" data-tqa-expiry checked>
                    <span>وصول دائم</span>
                </label>
                <label class="tqa-check">
                    <input type="radio" name="expiry_period" value="limited_time" data-tqa-expiry>
                    <span>مدة محدودة</span>
                </label>
            </div>
        </div>

        <div class="tqa-field" data-tqa-months hidden>
            <label class="tqa-field__label" for="number_of_month">عدد الأشهر</label>
            <input class="tqa-input tqa-input--ltr" type="number" id="number_of_month" name="number_of_month" min="1">
            <span class="tqa-field__hint">بعد الشراء يبقى الكورس مفتوحا هذه المدة ثم يقفل.</span>
        </div>
    </div>

    <?php /* ---------- الوسائط ---------- */ ?>
    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('video', 20); ?></span>
            <h2>الصور وفيديو النظرة العامة</h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field">
                <label class="tqa-field__label" for="course_overview_provider">مصدر الفيديو</label>
                <select class="tqa-select" id="course_overview_provider" name="course_overview_provider">
                    <option value="youtube">يوتيوب</option>
                    <option value="vimeo">فيميو</option>
                    <option value="html5">ملف مرفوع</option>
                </select>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="course_overview_url">رابط الفيديو</label>
                <input class="tqa-input tqa-input--ltr" type="url" id="course_overview_url"
                       name="course_overview_url" dir="ltr"
                       placeholder="https://www.youtube.com/watch?v=...">
            </div>

            <?php include 'course_media_add.php'; ?>
        </div>
    </div>

    <?php /* ---------- المعلومات ---------- */ ?>
    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-sand" aria-hidden="true"><?php echo tq_icon('clipboard', 20); ?></span>
            <h2>ما يعرض في صفحة الكورس</h2>
        </div>

        <div class="tqa-field">
            <span class="tqa-field__label">المتطلبات السابقة</span>
            <div data-tqa-rep="requirements">
                <div data-tqa-rep-item class="tqa-row" style="flex-wrap:nowrap;margin-block-end:var(--tq-space-s)">
                    <input class="tqa-input" type="text" name="requirements[]"
                           placeholder="مثال: إتقان جدول الضرب">
                    <button type="button" class="tqa-btn tqa-btn--ghost" data-tqa-rep-remove
                            style="color:var(--tq-danger)" aria-label="احذف هذا المتطلب">
                        <?php echo tq_icon('trash', 15); ?>
                    </button>
                </div>
            </div>
            <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-add="requirements">
                <?php echo tq_icon('plus', 14); ?> أضف متطلبا
            </button>
        </div>

        <div class="tqa-field">
            <span class="tqa-field__label">مخرجات التعلم</span>
            <div data-tqa-rep="outcomes">
                <div data-tqa-rep-item class="tqa-row" style="flex-wrap:nowrap;margin-block-end:var(--tq-space-s)">
                    <input class="tqa-input" type="text" name="outcomes[]"
                           placeholder="مثال: أن يحل الطالب مسائل القسمة المطولة">
                    <button type="button" class="tqa-btn tqa-btn--ghost" data-tqa-rep-remove
                            style="color:var(--tq-danger)" aria-label="احذف هذا المخرج">
                        <?php echo tq_icon('trash', 15); ?>
                    </button>
                </div>
            </div>
            <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-add="outcomes">
                <?php echo tq_icon('plus', 14); ?> أضف مخرجا
            </button>
        </div>

        <div class="tqa-field">
            <span class="tqa-field__label">أسئلة شائعة عن الكورس</span>
            <div data-tqa-rep="faqs">
                <div data-tqa-rep-item class="tqa-card" style="box-shadow:none;border-style:dashed;
                     margin-block-end:var(--tq-space-s)">
                    <input class="tqa-input" type="text" name="faqs[]" placeholder="السؤال"
                           style="margin-block-end:var(--tq-space-s)">
                    <textarea class="tqa-textarea" name="faq_descriptions[]" rows="2"
                              placeholder="الإجابة" style="min-block-size:70px"></textarea>
                    <div class="tqa-actions" style="margin-block-start:var(--tq-space-s)">
                        <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-remove
                                style="color:var(--tq-danger)">
                            <?php echo tq_icon('trash', 14); ?> احذف هذا السؤال
                        </button>
                    </div>
                </div>
            </div>
            <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm" data-tqa-rep-add="faqs">
                <?php echo tq_icon('plus', 14); ?> أضف سؤالا
            </button>
        </div>
    </div>

    <?php /* ---------- تحسين البحث ---------- */ ?>
    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-rose" aria-hidden="true"><?php echo tq_icon('search', 20); ?></span>
            <h2>تحسين محركات البحث</h2>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="meta_keywords_in">الكلمات الدلالية</label>
            <div class="tqa-tags" data-tqa-tags>
                <input type="hidden" name="meta_keywords" value="" data-tqa-tags-value>
                <input class="tqa-tags__in" type="text" id="meta_keywords_in" autocomplete="off"
                       placeholder="اكتب كلمة ثم اضغط Enter" data-tqa-tags-input>
            </div>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="meta_description">وصف محركات البحث</label>
            <textarea class="tqa-textarea" id="meta_description" name="meta_description" rows="3"
                      maxlength="320"></textarea>
        </div>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> أنشئ الكورس
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/courses'); ?>">إلغاء</a>
    </div>
</form>

<?php include 'tqa_file_js.php'; ?>
<?php include 'tqa_tags_js.php'; ?>
<?php include 'tqa_repeater_js.php'; ?>
<?php include 'tqa_course_form_js.php'; ?>
