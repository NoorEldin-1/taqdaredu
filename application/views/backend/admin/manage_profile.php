<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * حسابي.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وثلاثة أعطال أصلحت معها:
 *
 * ١ — **وسم `</form>` زائد وثالث معلق.** كان في الملف ثلاثة أوسمة إغلاق
 *     لنموذجين: واحد داخل `foreach` وواحد بعده خارج الحلقة. ووسم إغلاق
 *     بلا فتح يغلق أقرب حاوية في شجرة المتصفح، فتخرج البطاقة مبتورة.
 * ٢ — **`$social_links['facebook']` بلا فحص.** `social_links` عمود نصي
 *     قد يكون `NULL` أو `''` في حساب لم يحفظ روابطه، و`json_decode('')`
 *     ترد `null` — فتقرأ ثلاثة فهارس من `null`: ثلاثة تحذيرات PHP 8.2
 *     تطبع فوق النموذج.
 * ٣ — **`initSummerNote(['#biography'])`** — محرر يحمل من حزمة القالب،
 *     ونبذة المسؤول لا تعرض في صفحة عامة أصلا فلا حاجة إلى HTML فيها.
 *     صار حقل نص عاديا.
 *
 * وحلقة `foreach($edit_data …)` بقيت: المتحكم يمرر مجموعة صف واحد، وهي
 * تدور مرة. لكنها تقرأ الآن أول صف صراحة — والحلقة التي تدور مرة واحدة
 * أبدا تخفي أن الشاشة لحساب واحد لا لقائمة.
 */
$tq_row = is_array($edit_data) ? reset($edit_data) : $edit_data->row_array();

if (!$tq_row) {
    tqa_head(t('حسابي'), '', 'cog');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty(t('تعذر قراءة بيانات الحساب'), t('سجل الخروج ثم الدخول مرة أخرى.'), '', '', 'cog');
    echo '</div>';
    return;
}

$tq_social = json_decode((string) $tq_row['social_links'], true);
if (!is_array($tq_social)) $tq_social = array();
$tq_social += array('facebook' => '', 'twitter' => '', 'linkedin' => '');

$tq_id = (int) $tq_row['id'];
?>

<?php tqa_head(t('حسابي'), t('بياناتك أنت وكلمة مرورك — لا حسابات المسؤولين الآخرين.'), 'cog'); ?>

<div class="tqa-cols">

    <div>
        <form action="<?php echo site_url('admin/manage_profile/update_profile_info/' . $tq_id); ?>"
              method="post" enctype="multipart/form-data" class="tqa-card">
            <?php echo tq_csrf(); ?>

            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('users', 20); ?></span>
                <h2><?php echo t('معلومات أساسية'); ?></h2>
            </div>

            <div class="tqa-fieldgrid">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="first_name">
                        <?php echo t('الاسم الأول'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <input class="tqa-input" type="text" id="first_name" name="first_name" required
                           value="<?php echo html_escape($tq_row['first_name']); ?>">
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="last_name">
                        <?php echo t('اسم العائلة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <input class="tqa-input" type="text" id="last_name" name="last_name" required
                           value="<?php echo html_escape($tq_row['last_name']); ?>">
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="email">
                        <?php echo t('البريد الإلكتروني'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <input class="tqa-input tqa-input--ltr" type="email" id="email" name="email" required dir="ltr"
                           value="<?php echo html_escape($tq_row['email']); ?>">
                    <span class="tqa-field__hint"><?php echo t('هو اسم دخولك — تغييره يغير ما تسجل به.'); ?></span>
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="title"><?php echo t('سطر تعريفي'); ?></label>
                    <input class="tqa-input" type="text" id="title" name="title" maxlength="255"
                           value="<?php echo html_escape($tq_row['title']); ?>">
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="skills_in"><?php echo t('المهارات'); ?></label>
                    <div class="tqa-tags" data-tqa-tags>
                        <input type="hidden" name="skills" value="<?php echo html_escape($tq_row['skills']); ?>"
                               data-tqa-tags-value>
                        <input class="tqa-tags__in" type="text" id="skills_in" autocomplete="off"
                               placeholder="<?php echo te('اكتب مهارة ثم اضغط Enter'); ?>" data-tqa-tags-input>
                    </div>
                </div>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="biography"><?php echo t('نبذة'); ?></label>
                    <textarea class="tqa-textarea" id="biography" name="biography" rows="4"><?php
                        echo html_escape($tq_row['biography']); ?></textarea>
                </div>

                <div class="tqa-field tqa-field--full">
                    <span class="tqa-field__label"><?php echo t('الصورة الشخصية'); ?></span>
                    <div class="tqa-file">
                        <img class="tqa-avatar" width="38" height="38" alt="<?php echo te('صورتك الحالية'); ?>"
                             src="<?php echo html_escape($this->user_model->get_user_image_url($tq_id)); ?>">
                        <input type="file" id="user_image" name="user_image" accept="image/*" data-tqa-file>
                        <label class="tqa-file__btn" for="user_image">
                            <?php echo tq_icon('image', 16); ?> <?php echo t('استبدل الصورة'); ?>
                        </label>
                        <span class="tqa-file__name" data-tqa-file-name><?php echo t('صورة مربعة تخرج أفضل'); ?></span>
                    </div>
                </div>
            </div>

            <div class="tqa-card__head" style="padding:var(--tq-space-l) 0 var(--tq-space-l);
                 margin-block:var(--tq-space-l);border-block-end:1px solid var(--tq-line);border-block-start:1px solid var(--tq-line)">
                <span class="tqa-iconbox tqa-lilac" aria-hidden="true"><?php echo tq_icon('link', 20); ?></span>
                <h2><?php echo t('روابط التواصل'); ?></h2>
            </div>

            <div class="tqa-fieldgrid tqa-fieldgrid--3">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="facebook_link"><?php echo t('فيسبوك'); ?></label>
                    <input class="tqa-input tqa-input--ltr" type="url" id="facebook_link" name="facebook_link" dir="ltr"
                           value="<?php echo html_escape($tq_social['facebook']); ?>">
                </div>
                <div class="tqa-field">
                    <label class="tqa-field__label" for="twitter_link"><?php echo t('إكس (تويتر)'); ?></label>
                    <input class="tqa-input tqa-input--ltr" type="url" id="twitter_link" name="twitter_link" dir="ltr"
                           value="<?php echo html_escape($tq_social['twitter']); ?>">
                </div>
                <div class="tqa-field">
                    <label class="tqa-field__label" for="linkedin_link"><?php echo t('لينكدإن'); ?></label>
                    <input class="tqa-input tqa-input--ltr" type="url" id="linkedin_link" name="linkedin_link" dir="ltr"
                           value="<?php echo html_escape($tq_social['linkedin']); ?>">
                </div>
            </div>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--primary">
                    <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ التعديل'); ?>
                </button>
            </div>
        </form>
    </div>

    <aside>
        <form action="<?php echo site_url('admin/manage_profile/change_password/' . $tq_id); ?>"
              method="post" class="tqa-card">
            <?php echo tq_csrf(); ?>

            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('lock', 20); ?></span>
                <h2><?php echo t('كلمة المرور'); ?></h2>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="current_password">
                    <?php echo t('كلمة المرور الحالية'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="password" id="current_password"
                       name="current_password" required autocomplete="current-password" dir="ltr">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="new_password">
                    <?php echo t('كلمة المرور الجديدة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="password" id="new_password"
                       name="new_password" required minlength="8" autocomplete="new-password" dir="ltr">
                <span class="tqa-field__hint"><?php echo t('ثمانية محارف على الأقل.'); ?></span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="confirm_password">
                    <?php echo t('تأكيد الجديدة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="password" id="confirm_password"
                       name="confirm_password" required minlength="8" autocomplete="new-password" dir="ltr">
            </div>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--mastery tqa-btn--block">
                    <?php echo tq_icon('lock', 16); ?> <?php echo t('غير كلمة المرور'); ?>
                </button>
            </div>
        </form>

        <div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
            <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
            <span><?php echo t('تغيير كلمة المرور لا يخرجك من الجلسة الحالية، ويخرج أي جلسة أخرى لك عند الدخول التالي.'); ?></span>
        </div>
    </aside>
</div>

<?php include 'tqa_file_js.php'; ?>
<?php include 'tqa_tags_js.php'; ?>
