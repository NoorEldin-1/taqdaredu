<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إعدادات الموقع العام.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وأكبر ما تغير فيها **التبويبات**:
 *
 * TQ-TAB-SPIN — كانت ثمانية `tab-pane` من Bootstrap تبدل بجافاسكربت،
 * وفوقها هذه الكتلة لفتح تبويب من الرابط:
 *
 *     const tabClickInterval = setInterval(function(){
 *         if(!$("a[href$=…]").hasClass('active')){ $("a[href$=…]").click(); }
 *         else { clearInterval(tabClickInterval); }
 *     }, 1000);
 *
 * أي **مؤقت يضغط الرابط كل ثانية حتى ينشط**، ومعه طبقة تحميل تغطي
 * الشاشة (`.start_ajax_loading`) طوال ذلك. فمن يفتح `?tab=logo_and_images`
 * يرى الصفحة محجوبة ثانية أو أكثر ثم تقفز. وإن لم يكن التبويب موجودا
 * دار المؤقت **إلى الأبد**.
 *
 * والسبب في وجوده أصلا أن التبديل بجافاسكربت لا يفهم الرابط. والعلاج
 * أن يكون التبويب في الرابط ابتداء: كل تبويب صفحة يخدمها الخادم،
 * فتحفظ وترسل ويعود إليها زر الرجوع — وبلا مؤقت ولا حجب.
 *
 * وثلاثة أعطال أخرى أصلحت:
 *
 * ١ — **حقول الصور كانت `visibility:hidden`** مع `<label class="btn">`.
 *     و`visibility:hidden` تبقي العنصر يحجز مساحته ويخرجه من ترتيب
 *     التنقل — فلا يبلغه لوح المفاتيح، وتحته فراغ أبيض بارتفاع حقل.
 * ٢ — **«الأسئلة الشائعة» كان فيها حقل فارغ إضافي دائما** (`#blank_faq_field`)
 *     يرسل سؤالا فارغا مع كل حفظ. وزر «+» ينسخ من `#blank_faq_field`
 *     نفسه — فينسخ حقلا بمعرف مكرر في كل ضغطة.
 * ٣ — **`count(json_decode(...))` بلا فحص:** الإعداد الفارغ يرد `null`،
 *     و`count(null)` **خطأ قاتل في PHP 8** يبيض الصفحة كلها.
 */

$tq_tabs = array(
    'general'  => array(t('العام والسياسات'),      'globe'),
    'contact'  => array(t('بيانات التواصل'),       'mail'),
    'faq'      => array(t('الأسئلة الشائعة'),      'help'),
    'branding' => array(t('الشعار والصور'),        'image'),
    'reviews'  => array(t('آراء العملاء'),         'star'),
    'recaptcha' => array(t('حماية النماذج'),       'shield'),
    'code'     => array(t('أكواد مخصصة'),          'file-text'),
    'watermark' => array(t('العلامة المائية'),     'video'),
);

/* أسماء التبويبات القديمة تحول إلى الجديدة: روابط محفوظة ومراسلات
   داخلية تشير إليها، وتبويب مجهول كان يدير المؤقت بلا نهاية. */
$tq_alias = array(
    'frontendsettings'    => 'general',
    'websitefaqs'         => 'faq',
    'contact_information' => 'contact',
    'logo_and_images'     => 'branding',
    'custom_codes'        => 'code',
    'water_mark'          => 'watermark',
    'review'              => 'reviews',
);

$tq_tab = (string) $this->input->get('tab', true);
$tq_tab = $tq_alias[$tq_tab] ?? $tq_tab;
if (!isset($tq_tabs[$tq_tab])) $tq_tab = 'general';

$tq_url = function ($t) { return site_url('admin/frontend_settings') . '?tab=' . $t; };
?>

<?php tqa_head(t('إعدادات الموقع العام'), t('ما يراه الزائر: البانر والشعار والسياسات وبيانات التواصل.'), 'globe',
    '<a class="tqa-btn tqa-btn--ghost" href="' . base_url() . '" target="_blank" rel="noopener">'
  . tq_icon('external', 16) . t(' افتح الموقع</a>')); ?>

<nav class="tqa-tabs tqa-tabs--scroll" aria-label="<?php echo te('أقسام إعدادات الموقع'); ?>">
    <?php foreach ($tq_tabs as $tq_k => [$tq_label, $tq_ic]): ?>
        <a href="<?php echo $tq_url($tq_k); ?>" <?php echo $tq_tab === $tq_k ? 'aria-current="page"' : ''; ?>>
            <?php echo html_escape($tq_label); ?>
        </a>
    <?php endforeach; ?>
</nav>


<?php /* ================= العام والسياسات ================= */ ?>
<?php if ($tq_tab === 'general'): ?>

    <form class="tqa-card" method="post" enctype="multipart/form-data"
          action="<?php echo site_url('admin/frontend_settings/frontend_update'); ?>" style="max-inline-size:900px">
        <?php echo tq_csrf(); ?>

        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('home', 20); ?></span>
            <h2><?php echo t('بانر الصفحة الرئيسية'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="banner_title">
                    <?php echo t('عنوان البانر'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="banner_title" name="banner_title" required
                       value="<?php echo html_escape(get_frontend_settings('banner_title')); ?>">
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="banner_sub_title">
                    <?php echo t('العنوان الفرعي'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="banner_sub_title" name="banner_sub_title" required
                       value="<?php echo html_escape(get_frontend_settings('banner_sub_title')); ?>">
            </div>
        </div>

        <div class="tqa-card__head" style="padding:var(--tq-space-l) 0;margin-block:var(--tq-space-l);
             border-block:1px solid var(--tq-line)">
            <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('link', 20); ?></span>
            <h2><?php echo t('روابط التواصل الاجتماعي'); ?></h2>
        </div>

        <div class="tqa-fieldgrid tqa-fieldgrid--3">
            <?php foreach (array('facebook' => t('فيسبوك'), 'twitter' => t('إكس (تويتر)'), 'linkedin' => t('لينكدإن')) as $tq_k => $tq_l): ?>
                <div class="tqa-field">
                    <label class="tqa-field__label" for="<?php echo $tq_k; ?>"><?php echo $tq_l; ?></label>
                    <input class="tqa-input tqa-input--ltr" type="url" id="<?php echo $tq_k; ?>"
                           name="<?php echo $tq_k; ?>" dir="ltr"
                           value="<?php echo html_escape(get_frontend_settings($tq_k)); ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tqa-card__head" style="padding:var(--tq-space-l) 0;margin-block:var(--tq-space-l);
             border-block:1px solid var(--tq-line)">
            <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('shield', 20); ?></span>
            <h2><?php echo t('شريط ملفات تعريف الارتباط'); ?></h2>
        </div>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <span class="tqa-prefrow__title"><?php echo t('عرض شريط الموافقة'); ?></span>
                <span class="tqa-prefrow__hint"><?php echo t('يظهر للزائر في أول زيارة حتى يوافق.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <input type="hidden" name="cookie_status" value="inactive">
                <span class="tqa-switch">
                    <input type="checkbox" name="cookie_status" value="active"
                           <?php echo get_frontend_settings('cookie_status') === 'active' ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <div class="tqa-field" style="margin-block-start:var(--tq-space-l)">
            <label class="tqa-field__label" for="cookie_note"><?php echo t('نص الشريط'); ?></label>
            <textarea class="tqa-textarea" id="cookie_note" name="cookie_note" rows="3"><?php
                echo html_escape(get_frontend_settings('cookie_note')); ?></textarea>
        </div>

        <div class="tqa-card__head" style="padding:var(--tq-space-l) 0;margin-block:var(--tq-space-l);
             border-block:1px solid var(--tq-line)">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('file-text', 20); ?></span>
            <h2><?php echo t('صفحات السياسات'); ?></h2>
        </div>

        <p style="margin:0 0 var(--tq-space-l);font:var(--tq-type-caption);color:var(--tq-text2)">
            <?php echo t('كل واحدة صفحة عامة في الموقع. وما يترك فارغا تعرض صفحته سطر «لم يكتب نص هذه الصفحة بعد» — لا تختفي من التذييل.'); ?>
        </p>

        <?php /**
         * TQ-DEAD-FIELD — كان في هذه القائمة حقلان لا يعرضهما شيء:
         *
         * ١ — **«من نحن»**: صفحة `/about` في سمة تقدر صفحة مصممة كاملة
         *     (هيرو وأرقام وقصة وفريق)، نصوصها تحرر من
         *     `taqdar_admin/content`. والحقل هنا يقرؤه قالب
         *     `frontend/default-new/about_us.php` وحده — سمة Academy
         *     الأصلية، وهي غير مفعلة. فمن كتب فيه لا يرى أثرا.
         * ٢ — **«سياسة ملفات الارتباط»**: لا صفحة لها في الموقع أصلا،
         *     ولا يقرأ المفتاح أي قالب. وما يظهر للزائر هو «نص الشريط»
         *     في قسم ملفات الارتباط أعلاه.
         *
         * حذف الحقلان. والقيم المحفوظة تبقى في القاعدة كما هي: النموذج
         * لا يرسل المفتاح فلا يكتب فوقه.
         */ ?>
        <div class="tqa-note tqa-section">
            <span aria-hidden="true"><?php echo tq_icon('edit', 18); ?></span>
            <span>
                <?php echo t('نصوص صفحة'); ?> <strong><?php echo t('«من نحن»'); ?></strong> <?php echo t('تحرر من'); ?>
                <a href="<?php echo site_url('taqdar_admin/content'); ?>"><?php echo t('نصوص الصفحات'); ?></a> <?php echo t('— فهي صفحة مصممة لا نصا واحدا.'); ?>
            </span>
        </div>

        <?php foreach (array(
            'terms_and_condition' => array(t('الشروط والأحكام'), 'file-text'),
            'privacy_policy'      => array(t('سياسة الخصوصية'), 'lock'),
            'refund_policy'       => array(t('سياسة الاسترجاع'), 'receipt'),
        ) as $tq_k => [$tq_l, $tq_ic]): ?>
            <details class="tqa-card" style="margin-block-end:var(--tq-space-m);box-shadow:none">
                <summary class="tqa-row">
                    <span class="tqa-iconbox tqa-sand" aria-hidden="true" style="inline-size:34px;block-size:34px">
                        <?php echo tq_icon($tq_ic, 17); ?>
                    </span>
                    <strong style="color:var(--tq-navy);font:var(--tq-type-bodyStrong)"><?php echo $tq_l; ?></strong>
                </summary>
                <div style="margin-block-start:var(--tq-space-l)">
                    <textarea class="tqa-textarea" id="<?php echo $tq_k; ?>" name="<?php echo $tq_k; ?>"
                              rows="8" data-tqa-rich><?php echo html_escape(get_frontend_settings($tq_k)); ?></textarea>
                </div>
            </details>
        <?php endforeach; ?>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ التعديل'); ?>
            </button>
        </div>
    </form>


<?php /* ================= بيانات التواصل ================= */ ?>
<?php elseif ($tq_tab === 'contact'): ?>

    <form class="tqa-card" method="post" enctype="multipart/form-data"
          action="<?php echo site_url('admin/frontend_settings/contact_info'); ?>" style="max-inline-size:760px">
        <?php echo tq_csrf(); ?>

        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('mail', 20); ?></span>
            <h2><?php echo t('ما يعرض في صفحة «تواصل معنا»'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <?php foreach (array(
                'email'        => array(t('البريد الإلكتروني'), t('سطر لكل عنوان.')),
                'phone'        => array(t('الهاتف'), t('سطر لكل رقم.')),
                'address'      => array(t('العنوان'), ''),
                'office_hours' => array(t('ساعات العمل'), t('مثال: الأحد إلى الخميس، ٩ص — ٥م')),
            ) as $tq_k => [$tq_l, $tq_h]): ?>
                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="ci_<?php echo $tq_k; ?>"><?php echo $tq_l; ?></label>
                    <textarea class="tqa-textarea" id="ci_<?php echo $tq_k; ?>" name="<?php echo $tq_k; ?>"
                              rows="2" style="min-block-size:70px"><?php
                        echo html_escape($contact_info[$tq_k] ?? ''); ?></textarea>
                    <?php if ($tq_h !== ''): ?>
                        <span class="tqa-field__hint"><?php echo $tq_h; ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ البيانات'); ?>
            </button>
        </div>
    </form>


<?php /* ================= الأسئلة الشائعة ================= */ ?>
<?php elseif ($tq_tab === 'faq'):

    /* `count(null)` خطأ قاتل في PHP 8 — والإعداد فارغ في قاعدة جديدة. */
    $tq_faqs = json_decode((string) get_frontend_settings('website_faqs'), true);
    if (!is_array($tq_faqs) || !$tq_faqs) $tq_faqs = array(array('question' => '', 'answer' => ''));
?>

    <form class="tqa-card" method="post" enctype="multipart/form-data"
          action="<?php echo site_url('admin/frontend_settings/website_faq'); ?>" style="max-inline-size:820px">
        <?php echo tq_csrf(); ?>

        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('help', 20); ?></span>
            <h2><?php echo t('الأسئلة الشائعة'); ?></h2>
        </div>

        <p style="margin:0 0 var(--tq-space-l);font:var(--tq-type-caption);color:var(--tq-text2)">
            <?php echo t('تعرض في صفحة «الأسئلة الشائعة» بالترتيب نفسه. والسؤال الفارغ يهمل عند الحفظ.'); ?>
        </p>

        <div data-tqa-faq-list>
            <?php foreach ($tq_faqs as $tq_i => $tq_f): ?>
                <fieldset class="tqa-card" data-tqa-faq-item
                          style="box-shadow:none;margin-block-end:var(--tq-space-m);border-style:dashed">
                    <legend class="tqa-sr"><?php echo t('سؤال رقم'); ?> <?php echo $tq_i + 1; ?></legend>

                    <div class="tqa-field">
                        <label class="tqa-field__label"><?php echo t('السؤال'); ?></label>
                        <input class="tqa-input" type="text" name="questions[]"
                               value="<?php echo html_escape($tq_f['question'] ?? ''); ?>"
                               placeholder="<?php echo te('كيف أشترك في المنصة؟'); ?>">
                    </div>

                    <div class="tqa-field" style="margin-block-end:0">
                        <label class="tqa-field__label"><?php echo t('الإجابة'); ?></label>
                        <textarea class="tqa-textarea" name="answers[]" rows="3"
                                  style="min-block-size:80px"><?php echo html_escape($tq_f['answer'] ?? ''); ?></textarea>
                    </div>

                    <div class="tqa-actions" style="margin-block-start:var(--tq-space-m)">
                        <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm tqa-btn--quiet-danger" data-tqa-faq-remove>
                            <?php echo tq_icon('trash', 14); ?> <?php echo t('احذف هذا السؤال'); ?>
                        </button>
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <div class="tqa-actions">
            <button type="button" class="tqa-btn tqa-btn--ghost" data-tqa-faq-add>
                <?php echo tq_icon('plus', 16); ?> <?php echo t('أضف سؤالا'); ?>
            </button>
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ الأسئلة'); ?>
            </button>
        </div>
    </form>

    <script>
    /**
     * إضافة سؤال وحذفه.
     *
     * كانت النسخة السابقة تحتفظ في الصفحة بـ`#blank_faq_field` — حقلين
     * فارغين **ظاهرين دائما**، يرسلان سؤالا فارغا مع كل حفظ. وزر «+»
     * ينسخ تلك الكتلة نفسها، فينسخ معها معرفا مكررا (`id="questions"`)
     * في كل ضغطة.
     *
     * وهنا تنسخ آخر بطاقة وتفرغ حقولها، ولا حقل زائد في الصفحة.
     */
    (function () {
        'use strict';

        var list = document.querySelector('[data-tqa-faq-list]');
        var add  = document.querySelector('[data-tqa-faq-add]');
        if (!list || !add) return;

        var wire = function (item) {
            var btn = item.querySelector('[data-tqa-faq-remove]');
            if (!btn) return;
            btn.addEventListener('click', function () {
                /* آخر بطاقة لا تحذف — تفرغ. وإلا بقي النموذج بلا حقل
                   ولا سبيل إلى إضافة واحد إلا بتحديث الصفحة. */
                if (list.querySelectorAll('[data-tqa-faq-item]').length > 1) {
                    item.parentNode.removeChild(item);
                } else {
                    item.querySelector('input').value = '';
                    item.querySelector('textarea').value = '';
                }
            });
        };

        Array.prototype.forEach.call(list.querySelectorAll('[data-tqa-faq-item]'), wire);

        add.addEventListener('click', function () {
            var items = list.querySelectorAll('[data-tqa-faq-item]');
            var copy  = items[items.length - 1].cloneNode(true);

            copy.querySelector('input').value = '';
            copy.querySelector('textarea').value = '';

            list.appendChild(copy);
            wire(copy);
            copy.querySelector('input').focus();
        });
    })();
    </script>


<?php /* ================= الشعار والصور ================= */ ?>
<?php elseif ($tq_tab === 'branding'):

    $tq_images = array();

    if (!empty($homepage_banner) && !empty($homepage_banner['homepage_banner_image'])) {
        $tq_images['banner_image_update'] = array(
            'banner_image', t('صورة البانر'),
            $homepage_banner['homepage_banner_image_size'],
            get_current_banner('banner_image'),
        );
    }

    $tq_images += array(
        'light_logo' => array('light_logo', t('الشعار الفاتح'), '330 × 70', get_frontend_settings('light_logo')),
        'dark_logo'  => array('dark_logo',  t('الشعار الداكن'), '330 × 70', get_frontend_settings('dark_logo')),
        'small_logo' => array('small_logo', t('الشعار المصغر'), '49 × 58',  get_frontend_settings('small_logo')),
        'favicon'    => array('favicon',    t('أيقونة التبويب'), '90 × 90', get_frontend_settings('favicon')),
    );
?>

    <div class="tqa-grid tqa-grid--3">
        <?php foreach ($tq_images as $tq_route => [$tq_field, $tq_label, $tq_size, $tq_file]):
            $tq_rel = 'uploads/system/' . $tq_file;
            $tq_src = (trim((string) $tq_file) !== '' && is_file(FCPATH . $tq_rel)) ? base_url($tq_rel) : '';
        ?>
            <form class="tqa-card" method="post" enctype="multipart/form-data"
                  action="<?php echo site_url('admin/frontend_settings/' . $tq_route); ?>">
                <?php echo tq_csrf(); ?>

                <div class="tqa-card__head" style="padding:0 0 var(--tq-space-m);margin-block-end:var(--tq-space-m)">
                    <span class="tqa-iconbox tqa-mint" aria-hidden="true" style="inline-size:36px;block-size:36px">
                        <?php echo tq_icon('image', 18); ?>
                    </span>
                    <div>
                        <h2 style="font:var(--tq-type-bodyStrong);font-family:var(--tq-font-title)">
                            <?php echo html_escape($tq_label); ?>
                        </h2>
                        <span class="tqa-media__sub tq-ltr" dir="ltr"><?php echo html_escape($tq_size); ?></span>
                    </div>
                </div>

                <?php /* المعاينة على أرضية شطرنجية (`.tqa-checker`): الشعار
                         غالبا شفاف، وعلى أرضية بيضاء لا يعرف الأبيض منه
                         من الفراغ — فيرفع شعار بحواف بيضاء ولا يلاحظ. */ ?>
                <div class="tqa-checker">
                    <?php if ($tq_src !== ''): ?>
                        <img src="<?php echo html_escape($tq_src); ?>" alt="<?php echo te('____ الحالي', array(html_escape($tq_label))); ?>">
                    <?php else: ?>
                        <span style="font:var(--tq-type-caption);color:var(--tq-text2)"><?php echo t('لا صورة مرفوعة'); ?></span>
                    <?php endif; ?>
                </div>

                <div class="tqa-file">
                    <?php /* كان `style="visibility:hidden"` — يحجز مساحته
                             ويبقى خارج ترتيب التنقل، فيترك فراغا أبيض
                             ولا يبلغه لوح المفاتيح. */ ?>
                    <input type="file" id="<?php echo $tq_field; ?>" name="<?php echo $tq_field; ?>"
                           accept="image/*" data-tqa-file required>
                    <label class="tqa-file__btn" for="<?php echo $tq_field; ?>">
                        <?php echo tq_icon('upload', 16); ?> <?php echo t('اختر صورة'); ?>
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name><?php echo t('لم تختر ملفا بعد'); ?></span>
                </div>

                <div class="tqa-actions">
                    <button type="submit" class="tqa-btn tqa-btn--primary tqa-btn--sm">
                        <?php echo tq_icon('check', 15); ?> <?php echo t('ارفع'); ?>
                    </button>
                </div>
            </form>
        <?php endforeach; ?>
    </div>

    <?php include 'tqa_file_js.php'; ?>


<?php /* ================= آراء العملاء ================= */ ?>
<?php elseif ($tq_tab === 'reviews'):
    $tq_reviews = $this->db->where('ratable_type', null)->get('rating')->result_array();

    $tq_names = array();
    $tq_uids  = array_filter(array_map(function ($r) { return (int) $r['user_id']; }, $tq_reviews));
    if ($tq_uids) {
        foreach ($this->db->select('id, first_name, last_name, email')
                          ->where_in('id', $tq_uids)->get('users')->result_array() as $tq_u) {
            $tq_names[(int) $tq_u['id']] = $tq_u;
        }
    }
?>

    <div class="tqa-card tqa-card--flush">
        <div class="tqa-card__head">
            <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('star', 20); ?></span>
            <h2><?php echo t('آراء تعرض في الموقع'); ?></h2>
            <a class="tqa-btn tqa-btn--primary tqa-btn--sm" href="<?php echo site_url('admin/review_add'); ?>">
                <?php echo tq_icon('plus', 15); ?> <?php echo t('أضف رأيا'); ?>
            </a>
        </div>

        <?php if (empty($tq_reviews)): ?>
            <?php tqa_empty(t('لا آراء بعد'),
                t('الرأي هنا يعرض في الصفحة الرئيسية باسم صاحبه وصورته.'),
                t('أضف أول رأي'), site_url('admin/review_add'), 'star'); ?>
        <?php else: ?>
            <div class="tqa-table__wrap">
                <table class="tqa-table">
                    <caption class="tqa-sr"><?php echo t('الآراء المعروضة في الموقع العام'); ?></caption>
                    <thead>
                        <tr>
                            <th style="inline-size:60px">#</th>
                            <th><?php echo t('صاحب الرأي'); ?></th>
                            <th style="inline-size:110px"><?php echo t('التقييم'); ?></th>
                            <th><?php echo t('النص'); ?></th>
                            <th style="inline-size:170px"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tq_reviews as $tq_i => $tq_r):
                        $tq_u = $tq_names[(int) $tq_r['user_id']] ?? null;
                        $tq_n = $tq_u ? trim($tq_u['first_name'] . ' ' . $tq_u['last_name']) : '';
                    ?>
                        <tr>
                            <td data-label="#"><span class="tqa-num"><?php echo $tq_i + 1; ?></span></td>

                            <td data-label="صاحب الرأي">
                                <?php if ($tq_u): ?>
                                    <span class="tqa-media__title"><?php echo html_escape($tq_n !== '' ? $tq_n : $tq_u['email']); ?></span>
                                    <span class="tqa-media__sub tq-ltr" dir="ltr"><?php echo html_escape($tq_u['email']); ?></span>
                                <?php else: ?>
                                    <span class="tqa-dim"><?php echo t('حساب محذوف'); ?></span>
                                <?php endif; ?>
                            </td>

                            <td data-label="التقييم">
                                <span class="tqa-badge tqa-badge--warn">
                                    <?php echo tq_icon('star', 12); ?>
                                    <span class="tqa-num"><?php echo html_escape($tq_r['rating']); ?></span>
                                </span>
                            </td>

                            <td data-label="النص"><?php echo html_escape($tq_r['review']); ?></td>

                            <td data-label="إجراءات">
                                <div class="tqa-rowacts">
                                    <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                       href="<?php echo site_url('admin/review_edit/' . (int) $tq_r['id']); ?>">
                                        <?php echo tq_icon('edit', 14); ?> <?php echo t('تعديل'); ?>
                                    </a>

                                    <form method="post" action="<?php echo site_url('admin/review/delete/' . (int) $tq_r['id']); ?>"
                                          data-tqa-confirm-title="<?php echo te('حذف الرأي'); ?>"
                                          data-tqa-confirm="<?php echo te('لن يظهر في الموقع بعدها.'); ?>"
                                          data-tqa-confirm-ok="<?php echo te('نعم، احذف'); ?>"
                                          data-tqa-confirm-tone="danger">
                                        <?php echo tq_csrf(); ?>
                                        <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm tqa-btn--quiet-danger">
                                            <?php echo tq_icon('trash', 14); ?>
                                            <span class="tqa-sr"><?php echo t('حذف'); ?></span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>


<?php /* ================= حماية النماذج ================= */ ?>
<?php elseif ($tq_tab === 'recaptcha'):
    $tq_v2 = (int) get_frontend_settings('recaptcha_status') === 1;
    $tq_v3 = (int) get_frontend_settings('recaptcha_status_v3') === 1;
    $tq_mode = $tq_v3 ? 'v3' : ($tq_v2 ? 'v2' : 'off');
?>

    <form class="tqa-card" method="post" enctype="multipart/form-data"
          action="<?php echo site_url('admin/frontend_settings/recaptcha_update'); ?>" style="max-inline-size:760px">
        <?php echo tq_csrf(); ?>

        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('shield', 20); ?></span>
            <h2><?php echo t('حماية النماذج من الإرسال الآلي'); ?></h2>
        </div>

        <div class="tqa-field">
            <span class="tqa-field__label"><?php echo t('الوضع'); ?></span>
            <div class="tqa-checkrow">
                <?php foreach (array(
                    'off' => t('معطلة'),
                    'v2'  => t('الإصدار الثاني (مربع «لست روبوتا»)'),
                    'v3'  => t('الإصدار الثالث (بلا تفاعل)'),
                ) as $tq_k => $tq_l): ?>
                    <label class="tqa-check">
                        <input type="radio" name="recaptcha_status" value="<?php echo $tq_k; ?>"
                               <?php echo $tq_mode === $tq_k ? 'checked' : ''; ?>>
                        <span><?php echo $tq_l; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tqa-fieldgrid">
            <?php foreach (array(
                'recaptcha_sitekey'      => t('مفتاح الموقع (v2)'),
                'recaptcha_secretkey'    => t('المفتاح السري (v2)'),
                'recaptcha_sitekey_v3'   => t('مفتاح الموقع (v3)'),
                'recaptcha_secretkey_v3' => t('المفتاح السري (v3)'),
            ) as $tq_k => $tq_l): ?>
                <div class="tqa-field">
                    <label class="tqa-field__label" for="<?php echo $tq_k; ?>"><?php echo $tq_l; ?></label>
                    <input class="tqa-input tqa-input--ltr" type="text" id="<?php echo $tq_k; ?>"
                           name="<?php echo $tq_k; ?>" dir="ltr" autocomplete="off" spellcheck="false"
                           value="<?php echo html_escape(get_frontend_settings($tq_k)); ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tqa-note">
            <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
            <span>
                <?php echo t('املأ مفاتيح الإصدار الذي تفعله وحده. وكانت الأربعة مطلوبة معا، فمن يريد الإصدار الثاني لم يكن يستطيع الحفظ حتى يخترع مفاتيح للثالث.'); ?>
            </span>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ الإعداد'); ?>
            </button>
        </div>
    </form>


<?php /* ================= أكواد مخصصة ================= */ ?>
<?php elseif ($tq_tab === 'code'): ?>

    <form class="tqa-card" method="post"
          action="<?php echo site_url('admin/frontend_settings/custom_codes'); ?>" style="max-inline-size:860px">
        <?php echo tq_csrf(); ?>

        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('file-text', 20); ?></span>
            <h2><?php echo t('أكواد تحقن في كل صفحة'); ?></h2>
        </div>

        <div class="tqa-note tqa-note--warn tqa-section">
            <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
            <span>
                <?php echo t('ما يكتب هنا ينفذ في متصفح كل زائر. خطأ واحد في القوس يكسر الموقع كله، ولا يظهر في هذه الشاشة — يظهر في الصفحة العامة.'); ?>
            </span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="custom_css">
                <span class="tq-ltr" dir="ltr">CSS</span> <?php echo t('مخصص'); ?>
            </label>
            <textarea class="tqa-textarea tqa-input--ltr" id="custom_css" name="custom_css" rows="8"
                      dir="ltr" spellcheck="false" placeholder="h3 { color: black; }"><?php
                echo html_escape(get_frontend_settings('custom_css')); ?></textarea>
            <span class="tqa-field__hint"><?php echo t('قواعد CSS وحدها — بلا وسم'); ?> <span class="tq-ltr" dir="ltr">&lt;style&gt;</span>.</span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="embed_code"><?php echo t('كود تضمين أو أداة'); ?></label>
            <textarea class="tqa-textarea tqa-input--ltr" id="embed_code" name="embed_code" rows="8"
                      dir="ltr" spellcheck="false"><?php
                echo html_escape(get_frontend_settings('embed_code')); ?></textarea>
            <span class="tqa-field__hint"><?php echo t('محادثة حية أو تتبع أو ما شابه.'); ?></span>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ الأكواد'); ?>
            </button>
        </div>
    </form>


<?php /* ================= العلامة المائية ================= */ ?>
<?php else:
    $tq_wm   = (string) get_frontend_settings('water_mark');
    $tq_kind = preg_match('/\.(png|jpe?g|gif|webp)$/i', $tq_wm) ? 'image' : 'text';
    $tq_rel  = 'uploads/system/' . $tq_wm;
    $tq_src  = ($tq_kind === 'image' && is_file(FCPATH . $tq_rel)) ? base_url($tq_rel) : '';
?>

    <form class="tqa-card" method="post" enctype="multipart/form-data"
          action="<?php echo site_url('admin/frontend_settings/water_mark'); ?>" style="max-inline-size:760px">
        <?php echo tq_csrf(); ?>

        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('video', 20); ?></span>
            <h2><?php echo t('العلامة المائية على الفيديو'); ?></h2>
        </div>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <span class="tqa-prefrow__title"><?php echo t('تفعيل العلامة'); ?></span>
                <span class="tqa-prefrow__hint"><?php echo t('تتحرك فوق مشغل الدرس فتصعب تسجيل الشاشة.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <input type="hidden" name="water_mark_status" value="inactive">
                <span class="tqa-switch">
                    <input type="checkbox" name="water_mark_status" value="active"
                           <?php echo get_frontend_settings('water_mark_status') === 'active' ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <div class="tqa-fieldgrid" style="margin-block-start:var(--tq-space-l)">
            <div class="tqa-field">
                <label class="tqa-field__label" for="water_mark_speed">
                    <?php echo t('سرعة الحركة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="number" min="200" step="100" required
                       id="water_mark_speed" name="water_mark_speed"
                       value="<?php echo html_escape(get_frontend_settings('water_mark_speed')); ?>">
                <span class="tqa-field__hint"><?php echo t('بالمللي ثانية — ‎1000‎ تعني ثانية واحدة.'); ?></span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="water_mark_opacity">
                    <?php echo t('الشفافية'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="number" min="0" max="1" step="0.1" required
                       id="water_mark_opacity" name="water_mark_opacity"
                       value="<?php echo html_escape(get_frontend_settings('water_mark_opacity')); ?>">
                <span class="tqa-field__hint"><?php echo t('من ‎0‎ (شفافة تماما) إلى ‎1‎ (ظاهرة تماما).'); ?></span>
            </div>
        </div>

        <div class="tqa-field">
            <span class="tqa-field__label"><?php echo t('نوع العلامة'); ?></span>
            <div class="tqa-checkrow">
                <label class="tqa-check">
                    <input type="radio" name="water_mark_type" value="text" data-tqa-wm
                           <?php echo $tq_kind === 'text' ? 'checked' : ''; ?>>
                    <span><?php echo t('نص'); ?></span>
                </label>
                <label class="tqa-check">
                    <input type="radio" name="water_mark_type" value="image" data-tqa-wm
                           <?php echo $tq_kind === 'image' ? 'checked' : ''; ?>>
                    <span><?php echo t('صورة'); ?></span>
                </label>
            </div>
        </div>

        <div data-tqa-wm-pane="text" <?php echo $tq_kind === 'text' ? '' : 'hidden'; ?>>
            <div class="tqa-fieldgrid">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="wm_text"><?php echo t('النص'); ?></label>
                    <input class="tqa-input" type="text" id="wm_text" name="water_mark"
                           value="<?php echo html_escape($tq_kind === 'text' ? $tq_wm : ''); ?>"
                           placeholder="<?php echo te('اسم الطالب أو اسم المنصة'); ?>">
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="water_mark_color"><?php echo t('لون النص'); ?></label>
                    <input class="tqa-input" type="color" id="water_mark_color" name="water_mark_color"
                           style="padding:var(--tq-space-xs);block-size:var(--tqa-field-h)"
                           value="<?php echo html_escape(get_frontend_settings('water_mark_color') ?: '#ffffff'); ?>">
                </div>
            </div>
        </div>

        <div data-tqa-wm-pane="image" <?php echo $tq_kind === 'image' ? '' : 'hidden'; ?>>
            <div class="tqa-field">
                <span class="tqa-field__label"><?php echo t('صورة العلامة'); ?></span>
                <div class="tqa-file">
                    <?php if ($tq_src !== ''): ?>
                        <img class="tqa-file__preview" src="<?php echo html_escape($tq_src); ?>" alt="<?php echo te('العلامة الحالية'); ?>">
                    <?php endif; ?>
                    <input type="file" id="water_mark_image" name="water_mark_image" accept="image/*" data-tqa-file>
                    <label class="tqa-file__btn" for="water_mark_image">
                        <?php echo tq_icon('upload', 16); ?> <?php echo t('اختر صورة'); ?>
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name><?php echo t('المقاس المفضل ‎330 × 70‎ بكسل'); ?></span>
                </div>
            </div>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ العلامة'); ?>
            </button>
        </div>
    </form>

    <script>
    (function () {
        'use strict';
        var panes = {
            text:  document.querySelector('[data-tqa-wm-pane="text"]'),
            image: document.querySelector('[data-tqa-wm-pane="image"]')
        };
        Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-wm]'), function (radio) {
            radio.addEventListener('change', function () {
                if (panes.text)  panes.text.hidden  = radio.value !== 'text';
                if (panes.image) panes.image.hidden = radio.value !== 'image';
            });
        });
    })();
    </script>

    <?php include 'tqa_file_js.php'; ?>

<?php endif; ?>
