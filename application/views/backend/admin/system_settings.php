<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إعدادات المنصة.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **زر الحفظ كان `type="button"`** ينادي `checkRequiredFields()` من
 *     `main.js`. أي أن حفظ إعدادات المنصة كلها معلق على ملف جافاسكربت
 *     واحد، وأن Enter في أي حقل لا يفعل شيئا.
 * ٢ — **حقول لا تخص هذا التركيب كانت `required`:** «رمز الشراء» و
 *     «مفتاح يوتيوب» و«مفتاح فيميو». والمنصة لا تستضيف على يوتيوب ولا
 *     فيميو، ورمز الشراء من متجر القالب الأصلي. فمن أراد تغيير اسم
 *     الموقع لم يكن يستطيع الحفظ حتى يملأ ثلاثة حقول لا معنى لها عنده.
 *     صارت اختيارية ومجموعة في قسم «تكاملات».
 * ٣ — **نموذج «تحديث المنتج»** يرفع ملفا إلى `updater/update` — وهو
 *     محدث القالب التجاري، يفك أرشيفا فوق شجرة الموقع. والنشر هنا
 *     يجري بـ`deploy.sh` من git (انظر CLAUDE.md)، فرفع أرشيف فوقه يعني
 *     شجرة لا تطابق أي التزام. حذف النموذج.
 * ٤ — **«هل يستطيع الطلاب تعطيل حساباتهم؟» كان زري راديو** بلا مجموعة
 *     معنونة — يقرؤهما قارئ الشاشة خيارين معلقين. صار مفتاحا.
 * ٥ — الحقول جمعت في أربع بطاقات معنونة: كانت ثمانية عشر حقلا في عمود
 *     واحد بلا فاصل، فيقرأ «رمز الشراء» بجوار «رقم هاتف المنصة».
 */
$tq_yes = function ($key, $on = 'enable') { return get_settings($key) === $on; };
?>

<?php tqa_head(t('إعدادات المنصة'), t('هوية الموقع وسلوكه العام — وما يظهر منها في كل صفحة.'), 'cog'); ?>

<form action="<?php echo site_url('admin/system_settings/system_update'); ?>" method="post"
      enctype="multipart/form-data" style="max-inline-size:900px">
    <?php echo tq_csrf(); ?>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('globe', 20); ?></span>
            <h2><?php echo t('هوية الموقع'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field">
                <label class="tqa-field__label" for="system_name">
                    <?php echo t('اسم الموقع'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="system_name" name="system_name" required
                       value="<?php echo html_escape(get_settings('system_name')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="system_title">
                    <?php echo t('عنوان التبويب'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="system_title" name="system_title" required
                       value="<?php echo html_escape(get_settings('system_title')); ?>">
                <span class="tqa-field__hint"><?php echo t('يظهر في شريط تبويب المتصفح وفي المفضلة.'); ?></span>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="slogan">
                    <?php echo t('الشعار النصي'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="slogan" name="slogan" required
                       value="<?php echo html_escape(get_settings('slogan')); ?>">
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="website_keywords_in"><?php echo t('كلمات الموقع'); ?></label>
                <div class="tqa-tags" data-tqa-tags>
                    <input type="hidden" name="website_keywords" data-tqa-tags-value
                           value="<?php echo html_escape(get_settings('website_keywords')); ?>">
                    <input class="tqa-tags__in" type="text" id="website_keywords_in" autocomplete="off"
                           placeholder="<?php echo te('اكتب كلمة ثم اضغط Enter'); ?>" data-tqa-tags-input>
                </div>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="website_description"><?php echo t('وصف الموقع'); ?></label>
                <textarea class="tqa-textarea" id="website_description" name="website_description" rows="3"><?php
                    echo html_escape(get_settings('website_description')); ?></textarea>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="author"><?php echo t('المؤلف'); ?></label>
                <input class="tqa-input" type="text" id="author" name="author"
                       value="<?php echo html_escape(get_settings('author')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="timezone"><?php echo t('المنطقة الزمنية'); ?></label>
                <select class="tqa-select" id="timezone" name="timezone">
                    <?php foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $tq_tz): ?>
                        <option value="<?php echo html_escape($tq_tz); ?>"
                            <?php echo get_settings('timezone') === $tq_tz ? 'selected' : ''; ?>>
                            <?php echo html_escape($tq_tz); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="language"><?php echo t('لغة النظام'); ?></label>
                <select class="tqa-select" id="language" name="language">
                    <?php foreach ($languages as $tq_l): ?>
                        <option value="<?php echo html_escape($tq_l); ?>"
                            <?php echo get_settings('language') === $tq_l ? 'selected' : ''; ?>>
                            <?php echo html_escape(ucfirst($tq_l)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('mail', 20); ?></span>
            <h2><?php echo t('بيانات التواصل'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field">
                <label class="tqa-field__label" for="system_email">
                    <?php echo t('بريد المنصة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="email" id="system_email" name="system_email"
                       required dir="ltr" value="<?php echo html_escape(get_settings('system_email')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="phone"><?php echo t('الهاتف'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="tel" id="phone" name="phone" dir="ltr"
                       value="<?php echo html_escape(get_settings('phone')); ?>">
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="address"><?php echo t('العنوان'); ?></label>
                <textarea class="tqa-textarea" id="address" name="address" rows="2"><?php
                    echo html_escape(get_settings('address')); ?></textarea>
            </div>

            <?php /* شارتا التذييل تقرآن هذين المفتاحين: الحقل الفارغ يعرض
                     «التطبيق قريبا» صامتا، والرابط يجعلها حية. فالتفريغ هو
                     التعطيل، لا حذف الشارة. */ ?>
            <div class="tqa-field">
                <label class="tqa-field__label" for="app_store_url"><?php echo t('رابط App Store'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="url" id="app_store_url" name="app_store_url"
                       dir="ltr" placeholder="https://apps.apple.com/..."
                       value="<?php echo html_escape(get_settings('app_store_url')); ?>">
                <span class="tqa-field__hint"><?php echo t('اتركه فارغا لتظهر الشارة بحالة «قريبا».'); ?></span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="google_play_url"><?php echo t('رابط Google Play'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="url" id="google_play_url" name="google_play_url"
                       dir="ltr" placeholder="https://play.google.com/store/apps/details?id=..."
                       value="<?php echo html_escape(get_settings('google_play_url')); ?>">
                <span class="tqa-field__hint"><?php echo t('اتركه فارغا لتظهر الشارة بحالة «قريبا».'); ?></span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="footer_text"><?php echo t('نص التذييل'); ?></label>
                <input class="tqa-input" type="text" id="footer_text" name="footer_text"
                       value="<?php echo html_escape(get_settings('footer_text')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="footer_link"><?php echo t('رابط التذييل'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="url" id="footer_link" name="footer_link" dir="ltr"
                       value="<?php echo html_escape(get_settings('footer_link')); ?>">
            </div>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('shield', 20); ?></span>
            <h2><?php echo t('الحسابات والوصول'); ?></h2>
        </div>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <span class="tqa-prefrow__title"><?php echo t('التسجيل مفتوح للجميع'); ?></span>
                <span class="tqa-prefrow__hint"><?php echo t('إغلاقه يمنع إنشاء حسابات جديدة من الموقع العام.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <input type="hidden" name="public_signup" value="disable">
                <span class="tqa-switch">
                    <input type="checkbox" name="public_signup" value="enable"
                           <?php echo $tq_yes('public_signup') ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <span class="tqa-prefrow__title"><?php echo t('تأكيد البريد قبل الدخول'); ?></span>
                <span class="tqa-prefrow__hint">
                    <?php echo t('يتطلب رسالة تأكيد لكل حساب جديد — ويحتاج إعدادات بريد صادر تعمل.'); ?>
                </span>
            </div>
            <div class="tqa-prefrow__end">
                <input type="hidden" name="student_email_verification" value="disable">
                <span class="tqa-switch">
                    <input type="checkbox" name="student_email_verification" value="enable"
                           <?php echo $tq_yes('student_email_verification') ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <span class="tqa-prefrow__title"><?php echo t('الطالب يستطيع تعطيل حسابه'); ?></span>
                <span class="tqa-prefrow__hint"><?php echo t('يظهر زر التعطيل في إعدادات بوابة الطالب.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <input type="hidden" name="account_disable" value="0">
                <span class="tqa-switch">
                    <input type="checkbox" name="account_disable" value="1"
                           <?php echo (int) get_settings('account_disable') === 1 ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <span class="tqa-prefrow__title"><?php echo t('صفحات الكورسات للزوار'); ?></span>
                <span class="tqa-prefrow__hint"><?php echo t('إغلاقها يجبر الزائر على الدخول قبل أن يرى أي كورس.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <input type="hidden" name="course_accessibility" value="only_logged_in_users">
                <span class="tqa-switch">
                    <input type="checkbox" name="course_accessibility" value="publicly"
                           <?php echo get_settings('course_accessibility') === 'publicly' ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <div class="tqa-fieldgrid" style="margin-block-start:var(--tq-space-l)">
            <div class="tqa-field">
                <label class="tqa-field__label" for="allowed_device_number_of_loging">
                    <?php echo t('عدد الأجهزة المسموحة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="number" min="1" required
                       id="allowed_device_number_of_loging" name="allowed_device_number_of_loging"
                       value="<?php echo html_escape(get_settings('allowed_device_number_of_loging')); ?>">
                <span class="tqa-field__hint"><?php echo t('كم جهازا يدخل بالحساب الواحد في وقت واحد.'); ?></span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="course_selling_tax">
                    <?php echo t('ضريبة البيع (٪)'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="number" min="0" max="100" required
                       id="course_selling_tax" name="course_selling_tax"
                       value="<?php echo html_escape(get_settings('course_selling_tax')); ?>">
                <span class="tqa-field__hint"><?php echo t('صفر يعطل الضريبة تماما.'); ?></span>
            </div>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('link', 20); ?></span>
            <h2><?php echo t('تكاملات خارجية'); ?></h2>
        </div>

        <p style="margin:0 0 var(--tq-space-l);font:var(--tq-type-caption);color:var(--tq-text2)">
            <?php echo t('كلها اختيارية. الحقل الفارغ يعطل تكامله ولا يمنع الحفظ.'); ?>
        </p>

        <div class="tqa-fieldgrid">
            <div class="tqa-field">
                <label class="tqa-field__label" for="google_analytics_id"><?php echo t('معرف Google Analytics'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="google_analytics_id"
                       name="google_analytics_id" dir="ltr" placeholder="G-XXXXXXXXXX"
                       value="<?php echo html_escape(get_settings('google_analytics_id')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="meta_pixel_id"><?php echo t('معرف Meta Pixel'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="meta_pixel_id" name="meta_pixel_id"
                       dir="ltr" value="<?php echo html_escape(get_settings('meta_pixel_id')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="youtube_api_key"><?php echo t('مفتاح YouTube'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="youtube_api_key" name="youtube_api_key"
                       dir="ltr" autocomplete="off"
                       value="<?php echo html_escape(get_settings('youtube_api_key')); ?>">
                <span class="tqa-field__hint">
                    <?php echo t('يلزم لدروس يوتيوب وجوجل درايف وحدها.'); ?>
                    <a href="https://developers.google.com/youtube/v3/getting-started" target="_blank" rel="noopener">
                        <?php echo t('كيف تحصل عليه'); ?></a>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="vimeo_api_key"><?php echo t('مفتاح Vimeo'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="vimeo_api_key" name="vimeo_api_key"
                       dir="ltr" autocomplete="off"
                       value="<?php echo html_escape(get_settings('vimeo_api_key')); ?>">
                <span class="tqa-field__hint"><?php echo t('يلزم لدروس فيميو وحدها.'); ?></span>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="purchase_code"><?php echo t('رمز شراء القالب'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="purchase_code" name="purchase_code"
                       dir="ltr" autocomplete="off"
                       value="<?php echo html_escape(get_settings('purchase_code')); ?>">
                <span class="tqa-field__hint"><?php echo t('من متجر القالب الأصلي — لا أثر له في تشغيل المنصة.'); ?></span>
            </div>
        </div>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> احفظ الإعدادات
        </button>
    </div>
</form>

<?php include 'tqa_tags_js.php'; ?>
