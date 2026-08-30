<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إضافة مسؤول.
 *
 * TQ-WIZARD-BLANK — كانت هذه الشاشة **معالج أربع خطوات** من قالب Hyper:
 * `#progressbarwizard` وأربع `.tab-pane` وشريط تقدم متحرك وزرا «السابق»
 * و«التالي». وثلاثة أعطال فيه:
 *
 * ١ — **الشاشة تخرج فارغة إن لم يشتغل المعالج.** `.tab-pane` مخفية في
 *     Bootstrap حتى تنشط، والذي ينشط أولها هو سكربت المعالج. فإن تعثر
 *     ملف واحد من حزمة القالب لا يرى المسؤول نموذجا أصلا — لا حقلا ولا
 *     زرا، بطاقة بيضاء وحدها.
 * ٢ — **زر الإرسال `type="button"`.** لا يرسل النموذج، بل ينادي
 *     `checkRequiredFields()` لترسله بيدها. فالنموذج بلا زر إرسال حقيقي،
 *     ولا يحفظ بلوحة المفاتيح (Enter في حقل لا يفعل شيئا).
 * ٣ — **الحقل المطلوب يخفى خلف خطوة.** «البريد» و«كلمة المرور» في
 *     الخطوة الثانية، فالمتصفح يرفض الإرسال لحقل مطلوب لا يستطيع أن
 *     يعرضه — وهي حالة يصمت فيها Chrome تماما.
 *
 * والبديل نموذج مسطح في ثلاث بطاقات معنونة. أربع خطوات لتسعة حقول ليست
 * تنظيما، وهي تخفي عن المسؤول ما بقي عليه.
 */
?>

<?php tqa_head(t('إضافة مسؤول'), t('الحساب الجديد يرى كل شيء حتى تضبط له صلاحيات من شاشة المسؤولين.'), 'shield',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/admins') . '">'
  . tq_icon('chev-prev', 16) . t(' كل المسؤولين</a>')); ?>

<form action="<?php echo site_url('admin/admins/add'); ?>" enctype="multipart/form-data" method="post"
      style="max-inline-size:860px">
    <?php echo tq_csrf(); ?>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('users', 20); ?></span>
            <h2><?php echo t('معلومات أساسية'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field">
                <label class="tqa-field__label" for="first_name">
                    <?php echo t('الاسم الأول'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="first_name" name="first_name" required maxlength="100">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="last_name">
                    <?php echo t('اسم العائلة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="last_name" name="last_name" required maxlength="100">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="phone"><?php echo t('الهاتف'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="tel" id="phone" name="phone"
                       inputmode="tel" autocomplete="tel">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="address"><?php echo t('العنوان'); ?></label>
                <input class="tqa-input" type="text" id="address" name="address">
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="biography"><?php echo t('نبذة'); ?></label>
                <textarea class="tqa-textarea" id="biography" name="biography" rows="4"></textarea>
                <span class="tqa-field__hint"><?php echo t('اختيارية — لا تعرض في الموقع العام.'); ?></span>
            </div>

            <div class="tqa-field tqa-field--full">
                <span class="tqa-field__label"><?php echo t('الصورة الشخصية'); ?></span>
                <div class="tqa-file">
                    <input type="file" id="user_image" name="user_image" accept="image/*" data-tqa-file>
                    <label class="tqa-file__btn" for="user_image">
                        <?php echo tq_icon('image', 16); ?> <?php echo t('اختر صورة'); ?>
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name><?php echo t('لم تختر ملفا بعد'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('lock', 20); ?></span>
            <h2><?php echo t('بيانات الدخول'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field">
                <label class="tqa-field__label" for="email">
                    <?php echo t('البريد الإلكتروني'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="email" id="email" name="email" required
                       autocomplete="off" dir="ltr">
                <span class="tqa-field__hint"><?php echo t('هو اسم الدخول، ولا يتكرر بين حسابين.'); ?></span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="password">
                    <?php echo t('كلمة المرور'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="password" id="password" name="password" required
                       minlength="8" autocomplete="new-password" dir="ltr">
                <span class="tqa-field__hint"><?php echo t('ثمانية محارف على الأقل.'); ?></span>
            </div>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('link', 20); ?></span>
            <h2><?php echo t('روابط التواصل'); ?></h2>
        </div>

        <div class="tqa-fieldgrid tqa-fieldgrid--3">
            <div class="tqa-field">
                <label class="tqa-field__label" for="facebook_link"><?php echo t('فيسبوك'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="url" id="facebook_link" name="facebook_link" dir="ltr">
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="twitter_link"><?php echo t('إكس (تويتر)'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="url" id="twitter_link" name="twitter_link" dir="ltr">
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="linkedin_link"><?php echo t('لينكدإن'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="url" id="linkedin_link" name="linkedin_link" dir="ltr">
            </div>
        </div>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> <?php echo t('أنشئ الحساب'); ?>
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/admins'); ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>

<?php include 'tqa_file_js.php'; ?>
