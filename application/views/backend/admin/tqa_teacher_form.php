<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-TEACHER-ADD — نموذج المعلم في اللوحة: إنشاء وتعديل بقالب واحد.
 *
 * وقالبان — واحد ينشئ وآخر يعدل — يفترقان عند أول حقل يضاف: يظهر في
 * الإنشاء ولا يظهر في التعديل، فيعدل المسؤول معلما فتمحى منه قيمة لم
 * تعرض له أصلا. فالفارق هنا `$row` وحده.
 *
 * القواعد كلها في `Taqdar_admin_model::create_teacher()` و
 * `update_teacher()`، والصورة في `Taqdar_settings_model::store_image()`،
 * والجوال في `taqdar_phone_helper` — والشاشة تعرض وترد ما كتب.
 *
 * والنموذج يحمل `enctype="multipart/form-data"` — وبلاها يصل اسم الملف
 * نصا في `$_POST` ويحفظ الحساب بلا صورة ولا خطأ يظهر (TQ-IMG-NORM).
 */
$row     = isset($row) && $row ? $row : null;
$tq_edit = (bool) $row;
$tq_id   = $tq_edit ? (int) $row['id'] : 0;

/** ما كتب أولا، ثم الصف المحفوظ، ثم الافتراض. */
$tq_o = function ($k, $d = '') use ($old, $row) {
    if (isset($old[$k]) && $old[$k] !== '') return (string) $old[$k];
    if ($row && isset($row[$k]) && $row[$k] !== null && $row[$k] !== '') return (string) $row[$k];
    return $d;
};

/* مربعا الاختيار لا يقرآن بـ`$tq_o`: القيمة `'0'` فراغ عنده، فمربع
   أطفأه المسؤول ثم رد النموذج لخطأ في حقل آخر كان يعود معلما. */
$tq_flag = function ($k, $row_key = null) use ($old, $row) {
    if (isset($old[$k]) && $old[$k] !== '') return $old[$k] === '1';
    if ($row) return (int) ($row[$row_key ?: $k] ?? 0) === 1;
    return false;
};

/* الرقم المخزن `+<رمز><وطني>` يعود إلى حقليه: المنتقي والرقم الوطني. */
$tq_codes = tq_dial_codes();
list($tq_row_cc, $tq_row_nat) = $tq_edit ? tq_phone_split((string) $row['phone'])
                                         : array(tq_phone_default_iso(), '');
$tq_cc  = isset($old['phone_cc']) && $old['phone_cc'] !== '' ? (string) $old['phone_cc'] : $tq_row_cc;
if (!isset($tq_codes[$tq_cc])) $tq_cc = tq_phone_default_iso();
$tq_nat = isset($old['phone']) && $old['phone'] !== '' ? (string) $old['phone'] : $tq_row_nat;

$tq_img = ($tq_edit && trim((string) $row['image']) !== '')
        ? $this->user_model->get_user_image_url($tq_id) : '';
?>

<?php tqa_head(
    $tq_edit ? t('تعديل المعلم') : t('إضافة معلم'),
    $tq_edit
        ? t('ما يعدل هنا يظهر في صفحته العامة وفي حسابه. وكلمة المرور تترك فارغة فلا تمس.')
        : t('حساب يفتح في الحال: لا رمز تأكيد ولا مراجعة طلب — يدخل صاحبه ببريده وكلمة مروره.'),
    'user-check'); ?>

<?php if (!$tq_edit): ?>
    <div class="tqa-note tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        <span>
            <?php echo t('هذا الباب'); ?> <strong><?php echo t('للمعلم الذي تعرفه الإدارة'); ?></strong> <?php echo t('— من تعاقدت معه وبيدها بياناته. فالحساب ينشأ مفتوحا معتمدا مؤكدا، ولا يمر على «طلبات المعلمين» ولا على رمز التحقق. ومن يطرق الباب من خارج يبقى مساره صفحة التسجيل بمحطاتها الثلاث.'); ?>
        </span>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="tqa-note tqa-note--warn tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo $tq_edit ? t('لم يحفظ التعديل.') : t('لم ينشأ الحساب.'); ?></strong>
            <ul style="margin:var(--tq-space-xs) 0 0;padding-inline-start:var(--tq-space-l)">
                <?php foreach ($errors as $tq_e): ?>
                    <li><?php echo html_escape($tq_e); ?></li>
                <?php endforeach; ?>
            </ul>
        </span>
    </div>
<?php endif; ?>

<form action="<?php echo site_url($tq_edit ? 'taqdar_admin/teacher_update/' . $tq_id
                                           : 'taqdar_admin/teacher_create'); ?>"
      method="post" enctype="multipart/form-data" style="max-inline-size:860px">
    <?php echo tq_csrf(); ?>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('user', 20); ?></span>
            <h2><?php echo t('هويته'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field">
                <label class="tqa-field__label" for="first_name">
                    <?php echo t('الاسم الأول'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="first_name" name="first_name"
                       required maxlength="40" value="<?php echo html_escape($tq_o('first_name')); ?>">
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="last_name">
                    <?php echo t('اسم العائلة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input" type="text" id="last_name" name="last_name"
                       required maxlength="40" value="<?php echo html_escape($tq_o('last_name')); ?>">
            </div>

            <?php /* TQ-PHONE-INTL — الرمز ينتقى والرقم وطني، ويخزن `+<رمز><وطني>`.
                     ومنتقي الدولة يبدل المثال مع اختياره، فمن بدل إلى «مصر» ورأى
                     مثالا سعوديا كتب رقما سعودي الشكل ثم رفض. */ ?>
            <div class="tqa-field">
                <label class="tqa-field__label" for="phone"><?php echo t('الجوال'); ?></label>
                <div style="display:flex;gap:var(--tq-space-s)">
                    <select class="tqa-select" id="phone_cc" name="phone_cc"
                            style="inline-size:clamp(140px, 38%, 200px);flex:none"
                            data-tqa-phone-cc>
                        <?php foreach ($tq_codes as $tq_iso => $tq_c): ?>
                            <option value="<?php echo $tq_iso; ?>"
                                    data-ex="<?php echo html_escape($tq_c['ex']); ?>"
                                    <?php echo $tq_iso === $tq_cc ? 'selected' : ''; ?>>
                                <?php echo $tq_c['flag'] . ' ' . html_escape($tq_c['name']) . ' +' . $tq_c['dial']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input class="tqa-input tqa-input--ltr" type="tel" id="phone" name="phone"
                           dir="ltr" inputmode="tel" autocomplete="off"
                           placeholder="<?php echo html_escape($tq_codes[$tq_cc]['ex']); ?>"
                           value="<?php echo html_escape($tq_nat); ?>" data-tqa-phone>
                </div>
                <span class="tqa-field__hint">
                    <?php echo t('اختياري. الرقم الوطني بلا مفتاح الدولة — وعليه يصل واتساب المال وإشعاراته.'); ?>
                </span>
            </div>

            <?php /* الوسم وسم `tqa-filefield` نفسه الذي تطبعه `tqa_form.php`،
                     فمعاينة ما اختير قبل الحفظ تعمل بسكربت اللوحة القائم —
                     ووسم ثان يعني معالجا ثانيا ينسى عند أول تعديل. */ ?>
            <div class="tqa-field">
                <label class="tqa-field__label" for="user_image"><?php echo t('الصورة الشخصية'); ?></label>
                <div class="tqa-filefield" data-tqa-file>
                    <?php if ($tq_img !== ''): ?>
                        <div class="tqa-filefield__now">
                            <img src="<?php echo html_escape($tq_img); ?>" alt="<?php echo te('الصورة الحالية'); ?>"
                                 loading="lazy" decoding="async" data-tqa-file-cur>
                        </div>
                    <?php endif; ?>
                    <div class="tqa-filefield__ctl">
                        <input class="tqa-input" type="file" id="user_image" name="user_image"
                               accept="image/jpeg,image/png,image/webp" data-tqa-file-input>
                    </div>
                    <div class="tqa-filefield__next" data-tqa-file-preview hidden>
                        <span class="tqa-dim"><?php echo t('المختارة الآن:'); ?></span>
                        <img alt="" data-tqa-file-img>
                    </div>
                </div>
                <span class="tqa-field__hint">
                    <?php echo $tq_edit
                        ? t('اتركه فارغا فتبقى الصورة الحالية كما هي.')
                        : t('اختيارية — JPG أو PNG أو WebP، حتى ميجابايتين.'); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('lock', 20); ?></span>
            <h2><?php echo t('بيانات الدخول'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="email">
                    <?php echo t('البريد الإلكتروني'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                </label>
                <input class="tqa-input tqa-input--ltr" type="email" id="email" name="email"
                       required dir="ltr" maxlength="50" autocomplete="off"
                       value="<?php echo html_escape($tq_o('email')); ?>">
                <span class="tqa-field__hint">
                    <?php echo t('به يدخل، وإليه تصل استعادة كلمة المرور. خمسون محرفا على الأكثر — وهو طول العمود.'); ?>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="password">
                    كلمة المرور
                    <?php if (!$tq_edit): ?><span class="tqa-field__req" aria-hidden="true">*</span><?php endif; ?>
                </label>
                <input class="tqa-input tqa-input--ltr" type="text" id="password" name="password"
                       dir="ltr" minlength="8" maxlength="72" autocomplete="new-password"
                       <?php echo $tq_edit ? '' : 'required'; ?> data-tqa-pass>
                <span class="tqa-field__hint">
                    <?php echo $tq_edit
                        ? t('اتركها فارغة فلا تمس. وما تكتبه هنا يبدلها في الحال.')
                        : t('ثمانية محارف فأكثر. تظهر كما تكتب — فأنت من يسلمها لصاحبها.'); ?>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="password_confirm">
                    تأكيد كلمة المرور
                    <?php if (!$tq_edit): ?><span class="tqa-field__req" aria-hidden="true">*</span><?php endif; ?>
                </label>
                <input class="tqa-input tqa-input--ltr" type="text" id="password_confirm"
                       name="password_confirm" dir="ltr" minlength="8" maxlength="72"
                       autocomplete="new-password" <?php echo $tq_edit ? '' : 'required'; ?> data-tqa-pass2>
                <button class="tqa-btn tqa-btn--ghost tqa-btn--sm" type="button"
                        style="margin-block-start:var(--tq-space-s)" data-tqa-passgen hidden>
                    <?php echo tq_icon('key', 16); ?> ولد كلمة مرور
                </button>
            </div>

            <?php if ($tq_edit): ?>
                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="status"><?php echo t('حال الحساب'); ?></label>
                    <select class="tqa-select" id="status" name="status">
                        <option value="1" <?php echo $tq_flag('status') ? 'selected' : ''; ?>><?php echo t('مفتوح — يدخل صاحبه'); ?></option>
                        <option value="0" <?php echo $tq_flag('status') ? '' : 'selected'; ?>><?php echo t('مغلق — يمنع الدخول'); ?></option>
                    </select>
                    <span class="tqa-field__hint">
                        <?php echo t('الإغلاق يمنع الدخول ولا يحذف شيئا: كورساته وطلابه ومحفظته تبقى كما هي.'); ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="tqa-field tqa-field--full">
                    <label class="tqa-check">
                        <input type="checkbox" name="notify" value="1"
                               <?php echo (!isset($old['notify']) || $old['notify'] === '1') ? 'checked' : ''; ?>>
                        <span>
                            <?php echo t('أرسل إليه بيانات الدخول بالبريد.'); ?>
                            <span class="tqa-field__hint" style="display:block">
                                <?php echo t('رسالة فيها بريده وكلمة مروره ودعوة إلى تغييرها بعد أول دخول. وكلمة المرور تسافر نصا في البريد — فاتركه إن كنت ستسلمها بيدك.'); ?>
                            </span>
                        </span>
                    </label>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tqa-card tqa-section">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('globe', 20); ?></span>
            <h2><?php echo t('صفحته العامة'); ?></h2>
        </div>

        <div class="tqa-fieldgrid">
            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="title"><?php echo t('الصفة'); ?></label>
                <input class="tqa-input" type="text" id="title" name="title" maxlength="160"
                       placeholder="<?php echo te('مثال: معلم رياضيات — ماجستير مناهج'); ?>"
                       value="<?php echo html_escape($tq_o('title')); ?>">
                <span class="tqa-field__hint"><?php echo t('سطر واحد يظهر تحت اسمه.'); ?></span>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="skills"><?php echo t('المواد التي يدرسها'); ?></label>
                <input class="tqa-input" type="text" id="skills" name="skills" maxlength="255"
                       placeholder="<?php echo te('الرياضيات · الفيزياء'); ?>"
                       value="<?php echo html_escape($tq_o('skills')); ?>">
                <span class="tqa-field__hint">
                    <?php echo t('تفصل بنقطة وسطى «·» — وهي التي تطبع رقاقات في بطاقته.'); ?>
                </span>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-field__label" for="biography"><?php echo t('نبذة'); ?></label>
                <textarea class="tqa-textarea" id="biography" name="biography" rows="4"
                          maxlength="1500"><?php echo html_escape($tq_o('biography')); ?></textarea>
            </div>

            <div class="tqa-field tqa-field--full">
                <label class="tqa-check">
                    <input type="checkbox" name="is_public" value="1"
                           <?php echo $tq_flag('is_public') ? 'checked' : ''; ?>>
                    <span>
                        <?php echo t('اعرضه في صفحة «المعلمون» العامة.'); ?>
                        <span class="tqa-field__hint" style="display:block">
                            <?php echo t('العرض العام اختيار صريح لا أثر جانبي لكونه معلما: من يدرس لا يلزم أن ينشر اسمه وصورته.'); ?>
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary" type="submit">
            <?php echo tq_icon('check', 16); ?>
            <?php echo $tq_edit ? t('احفظ التعديل') : t('أنشئ حساب المعلم'); ?>
        </button>
        <a class="tqa-btn tqa-btn--ghost"
           href="<?php echo site_url($tq_edit ? 'taqdar_admin/teacher/' . $tq_id
                                              : 'taqdar_admin/people?role=teacher'); ?>">
            <?php echo $tq_edit ? t('عد إلى صفحته') : t('عد إلى الحسابات'); ?>
        </a>
    </div>
</form>

<?php /* تيسيران لا قاعدتان: مثال الرقم يتبع الدولة، وزر يولد كلمة مرور
         ويكتبها في الحقلين. والنموذج يعمل بلا هذا الملف كله — ولذلك
         الزر يبدأ مخفيا ويظهره السكربت وحده. */ ?>
<script>
(function () {
    var cc = document.querySelector('[data-tqa-phone-cc]');
    var ph = document.querySelector('[data-tqa-phone]');
    if (cc && ph) {
        cc.addEventListener('change', function () {
            var o = cc.options[cc.selectedIndex];
            if (o && o.dataset.ex) ph.placeholder = o.dataset.ex;
        });
    }

    var gen = document.querySelector('[data-tqa-passgen]');
    var p1  = document.querySelector('[data-tqa-pass]');
    var p2  = document.querySelector('[data-tqa-pass2]');
    if (!gen || !p1 || !p2) return;

    gen.hidden = false;
    gen.addEventListener('click', function () {
        var abc = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var buf = new Uint32Array(12), out = '';
        (window.crypto || window.msCrypto).getRandomValues(buf);
        for (var i = 0; i < buf.length; i++) out += abc.charAt(buf[i] % abc.length);
        p1.value = out;
        p2.value = out;
        p1.focus();
        p1.select();
    });
})();
</script>
