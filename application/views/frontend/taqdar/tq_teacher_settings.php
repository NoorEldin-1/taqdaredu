<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * بوابة المعلم — الإعدادات.
 *
 * ── ما كان قبل هذه الشاشة ────────────────────────────────────────────────
 * لم يكن للمعلم إعدادات أصلا. خريطة `Taqdar::teacher()` ثمانية أقسام ليس
 * فيها إعدادات، و`portal_topbar.php` يصوب وجهة الحساب إلى `teacher` —
 * فالنقر على صورة حسابه يعيده إلى لوحته. وأخطر من ذلك أنه **لم يكن في
 * بوابة المعلم كلها سبيل لتسجيل الخروج**: الزر موجود في `tq_settings.php`
 * لبوابة الطالب وحدها، و`login/logout` قائم يعمل ولا شيء يدل عليه.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * ولا نموذج جديد لهذه الشاشة: `Taqdar_settings_model` يملك الإعدادات في
 * المنصة كلها، وهو نفسه الذي تكتب به بوابة الطالب (`save_profile` و
 * `save_password` و`save_alerts` و`save_prefs`). وأضيف إليه `save_teacher_public`
 * وحده لما يخص المعلم دون غيره: صفته ونبذته في صفحته العامة.
 *
 * والأنماط من `tq_student_styles.php` — أصناف عرض مشتركة (`tq-fieldgrid`
 * و`tq-formbar` و`tq-s-row` و`tq-switch`) لا بيانات طالب، فلا يستورد معها
 * `tq_student_data.php`.
 */
include 'tq_student_styles.php';

$CI = get_instance();
$CI->load->model('taqdar_settings_model');
$tq_set = $CI->taqdar_settings_model;

$tq_uid = isset($user_id) ? (int) $user_id : (int) $CI->session->userdata('user_id');

$tq_nav   = 'settings';
$tq_role  = 'teacher';
$tq_title = t('الإعدادات');
$tq_sub   = t('حسابك وصفحتك العامة وتنبيهاتك');
$tq_icon  = 'cog';

$u = $CI->db->where('id', $tq_uid)->get('users')->row_array() ?: [];

$tq_prefs    = $tq_set->prefs($tq_uid);
$tq_matrix   = $tq_set->notify_matrix($tq_uid);
$tq_types    = $tq_set->notify_types();
$tq_channels = $tq_set->notify_channels();
$tq_langs    = $tq_set->languages();

$tq_avatar = tqs_person_img($u['image'] ?? '');

$tq_save = base_url('teacher/settings/save');
$tq_ok   = tq_flash('flash_message');
$tq_err  = tq_flash('error_message');

$sections = [
    ['profile', t('الملف الشخصي'),      'users'],
    ['teacher', t('صفحتي العامة'),      'award'],
    ['security', t('الأمان والخصوصية'), 'lock'],
    ['alerts',  t('التنبيهات'),         'bell'],
    ['prefs',   t('التفضيلات العامة'),  'cog'],
    ['payouts', t('التحويلات المالية'), 'wallet'],
];
$active = (string) $CI->input->get('s', true);
if (!in_array($active, array_column($sections, 0), true)) $active = 'profile';

/** ساعة من ساعات اليوم بصيغة معزولة. */
$tq_fmt_hour = function ($h) {
    return TQ_LRI . sprintf('%02d:00', (int) $h) . TQ_PDI;
};

include 'portal_open.php';
?>

<div class="tq-cols tq-cols--wide">
    <div>
        <?php if ($tq_ok): ?>
            <p class="tqp-flash tqp-flash--ok" role="status"><?php echo tq_iso(html_escape($tq_ok)); ?></p>
        <?php endif; ?>
        <?php if ($tq_err): ?>
            <p class="tqp-flash tqp-flash--no" role="alert"><?php echo tq_iso(html_escape($tq_err)); ?></p>
        <?php endif; ?>

        <div class="tq-setcols">

            <nav class="tq-card tq-setcols__nav" aria-label="<?php echo te('أقسام الإعدادات'); ?>" style="padding:var(--tq-space-s)">
                <?php foreach ($sections as [$key, $label, $icon]): ?>
                    <a class="tq-rail__item" href="?s=<?php echo $key; ?>"
                       <?php echo $key === $active ? ' aria-current="page"' : ''; ?>>
                        <span aria-hidden="true"><?php echo tq_icon($icon); ?></span>
                        <span><?php echo html_escape($label); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="tq-stack">
                <?php if ($active === 'profile'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('الملف الشخصي'); ?></h2>

                        <form method="post" action="<?php echo $tq_save; ?>" enctype="multipart/form-data">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="profile">
                            <input type="hidden" name="s" value="profile">

                            <div class="tq-row" style="gap:var(--tq-space-l);margin-block:var(--tq-space-l) var(--tq-space-xl)">
                                <img class="tq-avatar tq-avatar--lg" src="<?php echo html_escape($tq_avatar); ?>"
                                     alt="<?php echo te('صورتك الحالية'); ?>">
                                <div class="tq-field" style="flex:1;min-inline-size:0;margin-block-end:0">
                                    <label class="tq-field__label" for="tq-avatar"><?php echo t('الصورة الشخصية'); ?></label>
                                    <input class="tq-input" id="tq-avatar" name="user_image" type="file"
                                           accept="image/jpeg,image/png,image/webp"
                                           aria-describedby="tq-avatar-hint">
                                    <span class="tq-field__msg tq-field__hint" id="tq-avatar-hint">
                                        <?php echo t('JPG أو PNG أو WebP، الحد الأقصى ____. وهي الصورة التي يراها طلابك في رسائلك وصفحتك.', array(tq_iso(t('2 ميجابايت')))); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="tq-fieldgrid">
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-first"><?php echo t('الاسم الأول'); ?></label>
                                    <input class="tq-input" id="tq-first" name="first_name" type="text"
                                           required maxlength="120" autocomplete="given-name"
                                           value="<?php echo html_escape($u['first_name'] ?? ''); ?>">
                                </div>
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-last"><?php echo t('الاسم الأخير'); ?></label>
                                    <input class="tq-input" id="tq-last" name="last_name" type="text"
                                           maxlength="120" autocomplete="family-name"
                                           value="<?php echo html_escape($u['last_name'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-email"><?php echo t('البريد الإلكتروني'); ?></label>
                                <input class="tq-input" id="tq-email" name="email" type="email" dir="ltr"
                                       required maxlength="50" autocomplete="email"
                                       aria-describedby="tq-email-hint"
                                       value="<?php echo html_escape($u['email'] ?? ''); ?>">
                                <span class="tq-field__msg tq-field__hint" id="tq-email-hint">
                                    <?php echo t('بريدك هو اسم دخولك — تغييره يغير ما تسجل به الدخول. ولا تصلك رسائلنا عليه؟'); ?>
                                    <a href="<?php echo base_url('teacher/settings?s=alerts#tq-spam'); ?>"><?php echo t('اقرأ ما تفعله في دقيقة'); ?></a>.
                                </span>
                            </div>

                            <?php /* TQ-PHONE-INTL — الدولة تنتقى هنا كما تنتقى في
                                     التسجيل: الرقم يخزن `+<رمز><وطني>` وعليه يرسل
                                     واتساب. وشاشة تحفظ `05…` عارية بينما التسجيل
                                     يحفظ `+9665…` تجعل معلما يعدل عنوانه فيفقد
                                     إشعاراته. */ ?>
                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-phone"><?php echo t('رقم الجوال'); ?></label>
                                <?php echo tq_phone_field('phone', array(
                                    'skin'  => 'portal',
                                    'id'    => 'tq-phone',
                                    'value' => (string) ($u['phone'] ?? ''),
                                    'hint'  => t('عليه تصلك رسائل واتساب من المنصة.'),
                                )); ?>
                            </div>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('حفظ بيانات الملف'); ?></button>
                            </div>
                        </form>

                        <p class="tq-micro tq-muted" style="margin-block-start:var(--tq-space-l)">
                            <?php echo t('توقيت المنصة كلها ____، وليس إعدادا لكل حساب على حدة. ومواعيد حصصك تحسب به.', array(tq_iso(html_escape(function_exists('get_settings') ? (get_settings('timezone') ?: 'Asia/Riyadh') : 'Asia/Riyadh')))); ?>
                        </p>
                    </section>

                <?php elseif ($active === 'teacher'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('صفحتي العامة'); ?></h2>
                        <p class="tq-caption">
                            <?php echo t('ما يقرؤه الطالب وولي أمره عنك قبل أن يحجز حصة. اكتبه بما تدرسه ولمن، لا بما تتمنى أن يقال عنك.'); ?>
                        </p>

                        <form method="post" action="<?php echo $tq_save; ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="teacher">
                            <input type="hidden" name="s" value="teacher">

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-title"><?php echo t('صفتك'); ?></label>
                                <input class="tq-input" id="tq-title" name="title" type="text" maxlength="160"
                                       placeholder="<?php echo te('معلم رياضيات للمرحلة الابتدائية'); ?>"
                                       value="<?php echo html_escape(strip_tags((string) ($u['title'] ?? ''))); ?>">
                                <span class="tq-field__msg tq-field__hint"><?php echo t('سطر واحد يظهر تحت اسمك.'); ?></span>
                            </div>

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-bio"><?php echo t('نبذة عنك'); ?></label>
                                <textarea class="tq-textarea" id="tq-bio" name="biography" rows="6" maxlength="1500"
                                          placeholder="<?php echo te('خبرتك، والصفوف التي تدرسها، وطريقتك في الشرح.'); ?>"><?php
                                    echo html_escape(strip_tags((string) ($u['biography'] ?? '')));
                                ?></textarea>
                                <span class="tq-field__msg tq-field__hint">
                                    <?php echo tq_iso(t('حتى 1500 حرف. تظهر في صفحتك العامة وفي بطاقتك عند حجز الحصص.')); ?>
                                </span>
                            </div>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('حفظ صفحتي العامة'); ?></button>
                            </div>
                        </form>

                        <div class="tq-s-row" style="margin-block-start:var(--tq-space-xl)">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0"><?php echo t('ظهورك على الموقع'); ?></p>
                                <p class="tq-micro" style="margin:0">
                                    <?php if (!empty($u['is_public'])): ?>
                                        <?php echo t('صفحتك منشورة ويصل إليها الزوار.'); ?>
                                    <?php else: ?>
                                        <?php echo t('صفحتك غير منشورة بعد. النشر قرار إدارة لا مفتاح في إعداداتك، فما يعرض على الموقع تراجعه المنصة قبل نشره.'); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if (!empty($u['is_public'])): ?>
                                <a class="tq-btn tq-btn--secondary tq-btn--sm" target="_blank" rel="noopener"
                                   href="<?php echo base_url('instructor/' . (int) $tq_uid); ?>"><?php echo t('عرض صفحتي'); ?></a>
                            <?php else: ?>
                                <?php echo tq_badge('idle', t('غير منشورة')); ?>
                            <?php endif; ?>
                        </div>
                    </section>

                <?php elseif ($active === 'security'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('كلمة المرور'); ?></h2>
                        <form method="post" action="<?php echo $tq_save; ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="password">
                            <input type="hidden" name="s" value="security">

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-pw-cur"><?php echo t('كلمة المرور الحالية'); ?></label>
                                <input class="tq-input" id="tq-pw-cur" name="current_password" type="password"
                                       required autocomplete="current-password">
                            </div>
                            <div class="tq-fieldgrid">
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-pw-new"><?php echo t('كلمة المرور الجديدة'); ?></label>
                                    <input class="tq-input" id="tq-pw-new" name="new_password" type="password"
                                           required minlength="8" autocomplete="new-password"
                                           aria-describedby="tq-pw-hint">
                                    <span class="tq-field__msg tq-field__hint" id="tq-pw-hint">
                                        <?php echo tq_iso(t('ثمانية محارف فأكثر.')); ?>
                                    </span>
                                </div>
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-pw-again"><?php echo t('تأكيد كلمة المرور'); ?></label>
                                    <input class="tq-input" id="tq-pw-again" name="confirm_password" type="password"
                                           required minlength="8" autocomplete="new-password">
                                </div>
                            </div>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('تغيير كلمة المرور'); ?></button>
                            </div>
                        </form>
                    </section>

                    <?php
                    /* الجلسات المفتوحة — من `users.sessions` و`ci_sessions`، وهو
                       السجل نفسه الذي يقوم عليه حد الأجهزة الذي يمنع الدخول فعلا.
                       والصفوف الميتة تطرح: جامع القمامة يحذف من `ci_sessions` ولا
                       يمس المصفوفة. */
                    $tq_sess_ids = json_decode((string) ($u['sessions'] ?? ''), true);
                    $tq_sess_ids = is_array($tq_sess_ids) ? array_values(array_filter($tq_sess_ids, 'is_string')) : [];
                    $tq_sessions = [];
                    if ($tq_sess_ids) {
                        $tq_sessions = $CI->db->select('id, ip_address, timestamp')
                            ->where_in('id', $tq_sess_ids)
                            ->order_by('timestamp', 'DESC')
                            ->get('ci_sessions')->result_array();
                    }
                    $tq_here = session_id();
                    ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('الجلسات والأجهزة'); ?></h2>
                        <p class="tq-caption">
                            <?php echo t('كل جهاز سجلت منه دخولا ولم تخرج منه بعد. والحد ____ جلسة في الوقت نفسه — وبعده يطلب منك تأكيد الجهاز الجديد.', array(tq_iso(html_escape((string) (get_settings('allowed_device_number_of_loging') ?: '—'))))); ?>
                        </p>

                        <?php if (!$tq_sessions): ?>
                            <p class="tq-caption" style="margin-block-end:0"><?php echo t('لا جلسة مسجلة غير هذه.'); ?></p>
                        <?php else: ?>
                            <div class="tq-table-wrap">
                                <table class="tq-table">
                                    <caption class="tq-sr"><?php echo t('جلسات حسابك المفتوحة'); ?></caption>
                                    <thead>
                                        <tr>
                                            <th scope="col"><?php echo t('الجهاز'); ?></th>
                                            <th scope="col"><?php echo t('عنوان الاتصال'); ?></th>
                                            <th scope="col"><?php echo t('آخر نشاط'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tq_sessions as $tq_s): ?>
                                            <?php $tq_is_here = ((string) $tq_s['id'] === (string) $tq_here); ?>
                                            <tr>
                                                <td data-label="<?php echo te('الجهاز'); ?>">
                                                    <?php if ($tq_is_here): ?>
                                                        <span class="tq-badge tq-badge--mastered"><?php echo t('هذا الجهاز'); ?></span>
                                                    <?php else: ?>
                                                        <span class="tq-caption"><?php echo t('جهاز آخر'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="<?php echo te('عنوان الاتصال'); ?>">
                                                    <span class="tq-ltr" dir="ltr"><?php echo html_escape((string) $tq_s['ip_address']); ?></span>
                                                </td>
                                                <td data-label="<?php echo te('آخر نشاط'); ?>">
                                                    <?php echo tq_iso(html_escape(date('Y-m-d H:i', (int) $tq_s['timestamp']))); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <p class="tq-micro tq-muted" style="margin-block-end:0">
                            <?php echo t('إنهاء جلسة على جهاز آخر عن بعد غير متاح بعد. وتغيير كلمة المرور أعلاه يبقي الجلسات القائمة كما هي.'); ?>
                        </p>
                    </section>

                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('بياناتك'); ?></h2>
                        <p class="tq-caption">
                            <?php echo t('حقان ينفذان كإجراءين لا كنص في سياسة: أن تأخذ نسخة من بياناتك، وأن تنهي حسابك.'); ?>
                        </p>
                        <div class="tq-s-row">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0"><?php echo t('تصدير بياناتي'); ?></p>
                                <p class="tq-micro" style="margin:0"><?php echo t('ملف بكل ما يخص حسابك، ينزل مباشرة.'); ?></p>
                            </div>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('teacher/export-data'); ?>">
                                <?php echo tq_icon('download'); ?> <?php echo t('طلب نسخة'); ?>
                            </a>
                        </div>
                        <div class="tq-s-row">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0"><?php echo t('حذف الحساب'); ?></p>
                                <p class="tq-micro" style="margin:0">
                                    <?php echo t('تستبدل بياناتك الشخصية بقيم مجهولة. وتبقى قيود محفظتك وفواتيرها بمعرف مجهول لأن الالتزام الضريبي يوجب حفظها.'); ?>
                                </p>
                            </div>
                            <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('teacher/delete-account'); ?>"><?php echo t('حذف حسابي'); ?></a>
                        </div>
                    </section>

                <?php elseif ($active === 'alerts'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('التنبيهات'); ?></h2>
                        <p class="tq-caption">
                            <?php echo t('لكل نوع قناتان مستقلتان — إيقاف قناة لا يوقف الأخرى. وليست هناك قناة «إشعار على الجهاز» لأن المنصة لا ترسل إشعارات دفع بعد.'); ?>
                        </p>

                        <?php /* TQ-SPAM — كما في شاشة الطالب: قناة البريد مفعلة في
                                 الجدول أدناه ولا يصل منها شيء لأن مزود البريد صنفها
                                 غير مرغوبة. والمعلم يخسر بها طلب حصة وتسليما ينتظر
                                 تصحيحه وإشعار تحويل أرباح. */ ?>
                        <?php echo tq_spam_notice(array(
                            'email' => (string) ($u['email'] ?? ''),
                            'what'  => t('إشعاراتنا'),
                        )); ?>

                        <form method="post" action="<?php echo $tq_save; ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="alerts">
                            <input type="hidden" name="s" value="alerts">

                            <div class="tq-table-wrap">
                                <table class="tq-table">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?php echo t('النوع'); ?></th>
                                            <?php foreach ($tq_channels as $ck => $clabel): ?>
                                                <th scope="col"><?php echo html_escape($clabel); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tq_types as $tkey => [$tlabel, $thint]): ?>
                                            <tr>
                                                <td data-label="<?php echo te('النوع'); ?>">
                                                    <span class="tq-strong"><?php echo html_escape($tlabel); ?></span>
                                                    <span class="tq-micro tq-muted" style="display:block"><?php echo html_escape($thint); ?></span>
                                                </td>
                                                <?php foreach ($tq_channels as $ck => $clabel): ?>
                                                    <?php $id = 'tq-n-' . $tkey . '-' . $ck; ?>
                                                    <td data-label="<?php echo html_escape($clabel); ?>">
                                                        <span class="tq-switchcell">
                                                            <label class="tq-sr" for="<?php echo $id; ?>">
                                                                <?php echo html_escape($tlabel . ' — ' . $clabel); ?>
                                                            </label>
                                                            <span class="tq-switch">
                                                                <input id="<?php echo $id; ?>" type="checkbox" value="1"
                                                                       name="notify[<?php echo html_escape($tkey); ?>][<?php echo html_escape($ck); ?>]"
                                                                       <?php echo !empty($tq_matrix[$tkey][$ck]) ? ' checked' : ''; ?>>
                                                                <span class="tq-switch__track" aria-hidden="true"></span>
                                                                <span class="tq-switch__knob" aria-hidden="true"></span>
                                                            </span>
                                                        </span>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <h3 class="tq-card__title" style="font:var(--tq-type-h2);margin-block-start:var(--tq-space-xl)"><?php echo t('ساعات الصمت'); ?></h3>

                            <div class="tq-prefrow">
                                <span class="tq-prefrow__main">
                                    <label class="tq-prefrow__title" for="tq-quiet-on"><?php echo t('تفعيل ساعات الصمت'); ?></label>
                                    <span class="tq-prefrow__hint"><?php echo t('لا تنبيهات داخل النافذة التي تختارها.'); ?></span>
                                </span>
                                <span class="tq-prefrow__end">
                                    <span class="tq-switchcell">
                                        <span class="tq-switch">
                                            <input id="tq-quiet-on" name="quiet_on" type="checkbox" value="1"
                                                   <?php echo !empty($tq_prefs['quiet_on']) ? ' checked' : ''; ?>>
                                            <span class="tq-switch__track" aria-hidden="true"></span>
                                            <span class="tq-switch__knob" aria-hidden="true"></span>
                                        </span>
                                    </span>
                                </span>
                            </div>

                            <div class="tq-fieldgrid" style="margin-block-start:var(--tq-space-l)">
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-quiet-from"><?php echo t('تبدأ الساعة'); ?></label>
                                    <select class="tq-select" id="tq-quiet-from" name="quiet_from">
                                        <?php for ($h = 0; $h < 24; $h++): ?>
                                            <option value="<?php echo $h; ?>"
                                                <?php echo ((int) $tq_prefs['quiet_from'] === $h) ? ' selected' : ''; ?>>
                                                <?php echo $tq_fmt_hour($h); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-quiet-to"><?php echo t('تنتهي الساعة'); ?></label>
                                    <select class="tq-select" id="tq-quiet-to" name="quiet_to">
                                        <?php for ($h = 0; $h < 24; $h++): ?>
                                            <option value="<?php echo $h; ?>"
                                                <?php echo ((int) $tq_prefs['quiet_to'] === $h) ? ' selected' : ''; ?>>
                                                <?php echo $tq_fmt_hour($h); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('حفظ تفضيلات التنبيهات'); ?></button>
                            </div>
                        </form>
                    </section>

                <?php elseif ($active === 'prefs'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('التفضيلات العامة'); ?></h2>

                        <form method="post" action="<?php echo $tq_save; ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="prefs">
                            <input type="hidden" name="s" value="prefs">

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-lang"><?php echo t('لغة الواجهة'); ?></label>
                                <select class="tq-select" id="tq-lang" name="language" aria-describedby="tq-lang-hint">
                                    <?php foreach ($tq_langs as $lk => $ll): ?>
                                        <option value="<?php echo html_escape($lk); ?>"
                                            <?php echo ($tq_prefs['language'] === $lk) ? ' selected' : ''; ?>>
                                            <?php echo html_escape($ll); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="tq-field__msg tq-field__hint" id="tq-lang-hint">
                                    <?php echo t('اتجاه الصفحة نتيجة للغة لا إعداد مستقل — فاختيار الإنجليزية يقلب الاتجاه معها.'); ?>
                                </span>
                            </div>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('حفظ التفضيلات'); ?></button>
                            </div>
                        </form>
                    </section>

                <?php else: ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('التحويلات المالية'); ?></h2>
                        <p class="tq-caption">
                            <?php echo t('بيانات التحويل لا تحفظ على حسابك: تكتب مع كل طلب سحب على حدة، ولا تظهر بعدها إلا بأربع خاناتها الأخيرة. فلا وسيلة دفع محفوظة هنا تعدلها.'); ?>
                        </p>
                        <div class="tq-s-row">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0"><?php echo t('رصيدك وطلبات سحبك'); ?></p>
                                <p class="tq-micro" style="margin:0"><?php echo t('المتاح والمعلق وكشف الحساب وسجل التحويلات.'); ?></p>
                            </div>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('teacher/wallet'); ?>"><?php echo t('المحفظة والأرباح'); ?></a>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="tq-card">
                    <div class="tq-s-row">
                        <div class="tq-s-row__main">
                            <p class="tq-strong" style="margin:0"><?php echo t('تسجيل الخروج'); ?></p>
                            <p class="tq-micro" style="margin:0"><?php echo t('إنهاء جلستك على هذا الجهاز.'); ?></p>
                        </div>
                        <?php /* السؤال قبل الخروج — كما في بوابة ولي الأمر حرفا بحرف.
                                 والمعلم أولى به: قد يكون في يده درس نصف مرفوع أو تصحيح
                                 نصف مكتوب، وخروج بنقرة واحدة يضيعهما. */ ?>
                        <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('login/logout'); ?>"
                           data-tq-confirm-title="<?php echo te('تسجيل الخروج؟'); ?>"
                           data-tq-confirm="<?php echo te('تنتهي جلستك على هذا الجهاز، وتحتاج بريدك وكلمة مرورك للدخول من جديد.'); ?>"
                           data-tq-confirm-note="<?php echo te('ما لم يحفظ من نموذج مفتوح لا يحفظ بالخروج.'); ?>"
                           data-tq-confirm-ok="<?php echo te('تسجيل الخروج'); ?>"
                           data-tq-confirm-tone="danger"><?php echo t('تسجيل الخروج'); ?></a>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <h2 class="tq-card__title"><?php echo t('حسابي'); ?></h2>
            <div class="tq-row" style="gap:var(--tq-space-m)">
                <img class="tq-avatar" src="<?php echo html_escape($tq_avatar); ?>" alt="">
                <div>
                    <p class="tq-strong" style="margin:0"><?php echo html_escape(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))); ?></p>
                    <p class="tq-micro" style="margin:0"><?php echo t('معلم'); ?></p>
                </div>
            </div>
        </div>

        <div class="tq-card">
            <h2 class="tq-card__title"><?php echo t('الدعم والمساعدة'); ?></h2>
            <div class="tq-stack" style="--tq-space-l:var(--tq-space-s)">
                <a href="<?php echo base_url('teacher/messages'); ?>?filter=admin"><?php echo t('مراسلة الإدارة'); ?></a>
                <a href="<?php echo base_url('faq'); ?>"><?php echo t('الأسئلة الشائعة'); ?></a>
                <a href="<?php echo base_url('contact'); ?>"><?php echo t('تواصل معنا'); ?></a>
                <a href="<?php echo base_url('privacy'); ?>"><?php echo t('سياسة الخصوصية'); ?></a>
            </div>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
