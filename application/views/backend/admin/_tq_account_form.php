<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * نموذج حساب — مشترك بين إضافة الطالب والمعلم وتحريرهما.
 *
 * كانت أربعة ملفات (`user_add` · `user_edit` · `instructor_add` ·
 * `instructor_edit`) منسوخة بعضها من بعض، مجموعها ٥٠٠ سطر لتسعة حقول.
 * وكلها معالج أربع خطوات من قالب Hyper — انظر TQ-WIZARD-BLANK في
 * [admin_add.php] لأعطاله.
 *
 * وثلاثة أعطال أخرى في نسخ التحرير:
 *
 * ١ — **`json_decode($user_data['payment_keys'], true)['paypal']`** —
 *     ثلاثة أسطر تقرأ مفاتيح دفع **لا حقل لها في النموذج كله**. وحساب
 *     عموده فارغ يرد `null`، فقراءة الفهرس منه ثلاثة تحذيرات PHP 8.2
 *     تطبع فوق النموذج. حذفت.
 * ٢ — **`$social_links['facebook']` بلا فحص** — العطل نفسه.
 * ٣ — **لا حقل كلمة مرور في التحرير إطلاقا.** فمن نسي كلمته لا سبيل
 *     إلى ضبطها له من اللوحة. أضيف، ويترك فارغا فلا يغير شيئا.
 *
 * ينتظر: `$tq_action` و`$tq_row` (فارغ في الإضافة) و`$tq_back`
 * و`$tq_cta` و`$tq_skills` (بولياني — حقلا المعلم).
 */
$tq_row    = $tq_row    ?? null;
$tq_skills = $tq_skills ?? false;

$tq_social = $tq_row ? json_decode((string) $tq_row['social_links'], true) : array();
if (!is_array($tq_social)) $tq_social = array();
$tq_social += array('facebook' => '', 'twitter' => '', 'linkedin' => '');

/** قيمة حقل: الصف المحفوظ إن وجد وإلا فراغ. */
$tq_v = function ($key) use ($tq_row) {
    return ($tq_row && isset($tq_row[$key])) ? $tq_row[$key] : '';
};
?>

<form action="<?php echo $tq_action; ?>" method="post" enctype="multipart/form-data"
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
                <input class="tqa-input" type="text" id="first_name" name="first_name" required maxlength="100"
                       value="<?php echo html_escape($tq_v('first_name')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="last_name">
                    <?php echo t('اسم العائلة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="last_name" name="last_name" required maxlength="100"
                       value="<?php echo html_escape($tq_v('last_name')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="phone"><?php echo t('الهاتف'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="tel" id="phone" name="phone" inputmode="tel"
                       value="<?php echo html_escape($tq_v('phone')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="address"><?php echo t('العنوان'); ?></label>
                <input class="tqa-input" type="text" id="address" name="address"
                       value="<?php echo html_escape($tq_v('address')); ?>">
            </div>

            <?php if ($tq_skills): ?>
                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="title"><?php echo t('سطر تعريفي'); ?></label>
                    <input class="tqa-input" type="text" id="title" name="title" maxlength="255"
                           value="<?php echo html_escape($tq_v('title')); ?>">
                    <span class="tqa-field__hint"><?php echo t('يظهر تحت اسم المعلم في صفحته العامة.'); ?></span>
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="skills_in"><?php echo t('المهارات'); ?></label>
                    <div class="tqa-tags" data-tqa-tags>
                        <input type="hidden" name="skills" data-tqa-tags-value
                               value="<?php echo html_escape($tq_v('skills')); ?>">
                        <input class="tqa-tags__in" type="text" id="skills_in" autocomplete="off"
                               placeholder="<?php echo te('اكتب مهارة ثم اضغط Enter'); ?>" data-tqa-tags-input>
                    </div>
                </div>
            <?php endif; ?>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="biography"><?php echo t('نبذة'); ?></label>
                <textarea class="tqa-textarea" id="biography" name="biography" rows="4"><?php
                    echo html_escape($tq_v('biography')); ?></textarea>
            </div>

            <div class="tqa-field tqa-field--full">
                <span class="tqa-field__label"><?php echo t('الصورة الشخصية'); ?></span>
                <div class="tqa-file">
                    <?php if ($tq_row): ?>
                        <img class="tqa-avatar" width="38" height="38" alt="<?php echo te('الصورة الحالية'); ?>"
                             src="<?php echo html_escape($this->user_model->get_user_image_url($tq_row['id'])); ?>">
                    <?php endif; ?>
                    <input type="file" id="user_image" name="user_image" accept="image/*" data-tqa-file>
                    <label class="tqa-file__btn" for="user_image">
                        <?php echo tq_icon('image', 16); ?>
                        <?php echo $tq_row ? t('استبدل الصورة') : t('اختر صورة'); ?>
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name>
                        <?php echo $tq_row ? t('اتركه فارغا لإبقاء الصورة الحالية') : t('صورة مربعة تخرج أفضل'); ?>
                    </span>
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
                <input class="tqa-input tqa-input--ltr" type="email" id="email" name="email" required dir="ltr"
                       value="<?php echo html_escape($tq_v('email')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="password">
                    كلمة المرور
                    <?php if (!$tq_row): ?><span class="tqa-field__req" aria-hidden="true">*</span><?php endif; ?>
                </label>
                <input class="tqa-input tqa-input--ltr" type="password" id="password" name="password" dir="ltr"
                       minlength="8" autocomplete="new-password" <?php echo $tq_row ? '' : 'required'; ?>>
                <span class="tqa-field__hint">
                    <?php echo $tq_row ? t('اتركها فارغة لإبقاء كلمة المرور الحالية.') : t('ثمانية محارف على الأقل.'); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('link', 20); ?></span>
            <h2><?php echo t('روابط التواصل'); ?></h2>
        </div>

        <div class="tqa-fieldgrid tqa-fieldgrid--3">
            <?php foreach (array(
                'facebook_link' => array(t('فيسبوك'), 'facebook'),
                'twitter_link'  => array(t('إكس (تويتر)'), 'twitter'),
                'linkedin_link' => array(t('لينكدإن'), 'linkedin'),
            ) as $tq_name => [$tq_label, $tq_key]): ?>
                <div class="tqa-field">
                    <label class="tqa-field__label" for="<?php echo $tq_name; ?>"><?php echo $tq_label; ?></label>
                    <input class="tqa-input tqa-input--ltr" type="url" id="<?php echo $tq_name; ?>"
                           name="<?php echo $tq_name; ?>" dir="ltr"
                           value="<?php echo html_escape($tq_social[$tq_key]); ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> <?php echo html_escape($tq_cta); ?>
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo $tq_back; ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>

<?php include 'tqa_file_js.php'; ?>
<?php if ($tq_skills) include 'tqa_tags_js.php'; ?>
