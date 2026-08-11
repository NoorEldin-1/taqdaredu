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
$tq_title = 'الإعدادات';
$tq_sub   = 'حسابك وصفحتك العامة وتنبيهاتك';
$tq_icon  = 'cog';

$u = $CI->db->where('id', $tq_uid)->get('users')->row_array() ?: [];

$tq_prefs    = $tq_set->prefs($tq_uid);
$tq_matrix   = $tq_set->notify_matrix($tq_uid);
$tq_types    = $tq_set->notify_types();
$tq_channels = $tq_set->notify_channels();
$tq_langs    = $tq_set->languages();

$tq_avatar = tqs_person_img($u['image'] ?? '');

$tq_save = base_url('teacher/settings/save');
$tq_ok   = $CI->session->flashdata('flash_message');
$tq_err  = $CI->session->flashdata('error_message');

$sections = [
    ['profile', 'الملف الشخصي',      'users'],
    ['teacher', 'صفحتي العامة',      'award'],
    ['security', 'الأمان والخصوصية', 'lock'],
    ['alerts',  'التنبيهات',         'bell'],
    ['prefs',   'التفضيلات العامة',  'cog'],
    ['payouts', 'التحويلات المالية', 'wallet'],
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

        <div class="tq-grid" style="grid-template-columns:220px minmax(0,1fr);gap:var(--tq-space-xxl)">

            <nav class="tq-card" aria-label="أقسام الإعدادات" style="padding:var(--tq-space-s)">
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
                        <h2 class="tq-card__title">الملف الشخصي</h2>

                        <form method="post" action="<?php echo $tq_save; ?>" enctype="multipart/form-data">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="profile">
                            <input type="hidden" name="s" value="profile">

                            <div class="tq-row" style="gap:var(--tq-space-l);margin-block:var(--tq-space-l) var(--tq-space-xl)">
                                <img class="tq-avatar tq-avatar--lg" src="<?php echo html_escape($tq_avatar); ?>"
                                     alt="صورتك الحالية">
                                <div class="tq-field" style="flex:1;min-inline-size:0;margin-block-end:0">
                                    <label class="tq-field__label" for="tq-avatar">الصورة الشخصية</label>
                                    <input class="tq-input" id="tq-avatar" name="user_image" type="file"
                                           accept="image/jpeg,image/png,image/webp"
                                           aria-describedby="tq-avatar-hint">
                                    <span class="tq-field__msg tq-field__hint" id="tq-avatar-hint">
                                        JPG أو PNG أو WebP، الحد الأقصى <?php echo tq_iso('2 ميجابايت'); ?>.
                                        وهي الصورة التي يراها طلابك في رسائلك وصفحتك.
                                    </span>
                                </div>
                            </div>

                            <div class="tq-fieldgrid">
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-first">الاسم الأول</label>
                                    <input class="tq-input" id="tq-first" name="first_name" type="text"
                                           required maxlength="120" autocomplete="given-name"
                                           value="<?php echo html_escape($u['first_name'] ?? ''); ?>">
                                </div>
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-last">الاسم الأخير</label>
                                    <input class="tq-input" id="tq-last" name="last_name" type="text"
                                           maxlength="120" autocomplete="family-name"
                                           value="<?php echo html_escape($u['last_name'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-email">البريد الإلكتروني</label>
                                <input class="tq-input" id="tq-email" name="email" type="email" dir="ltr"
                                       required maxlength="50" autocomplete="email"
                                       aria-describedby="tq-email-hint"
                                       value="<?php echo html_escape($u['email'] ?? ''); ?>">
                                <span class="tq-field__msg tq-field__hint" id="tq-email-hint">
                                    بريدك هو اسم دخولك — تغييره يغير ما تسجل به الدخول.
                                </span>
                            </div>

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-phone">رقم الجوال</label>
                                <input class="tq-input" id="tq-phone" name="phone" type="tel" dir="ltr"
                                       maxlength="25" autocomplete="tel" inputmode="tel"
                                       placeholder="05XXXXXXXX"
                                       value="<?php echo html_escape($u['phone'] ?? ''); ?>">
                            </div>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit">حفظ بيانات الملف</button>
                            </div>
                        </form>

                        <p class="tq-micro tq-muted" style="margin-block-start:var(--tq-space-l)">
                            توقيت المنصة كلها
                            <?php echo tq_iso(html_escape(function_exists('get_settings') ? (get_settings('timezone') ?: 'Asia/Riyadh') : 'Asia/Riyadh')); ?>،
                            وليس إعدادا لكل حساب على حدة. ومواعيد حصصك تحسب به.
                        </p>
                    </section>

                <?php elseif ($active === 'teacher'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title">صفحتي العامة</h2>
                        <p class="tq-caption">
                            ما يقرؤه الطالب وولي أمره عنك قبل أن يحجز حصة. اكتبه بما تدرسه ولمن،
                            لا بما تتمنى أن يقال عنك.
                        </p>

                        <form method="post" action="<?php echo $tq_save; ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="teacher">
                            <input type="hidden" name="s" value="teacher">

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-title">صفتك</label>
                                <input class="tq-input" id="tq-title" name="title" type="text" maxlength="160"
                                       placeholder="معلم رياضيات للمرحلة الابتدائية"
                                       value="<?php echo html_escape(strip_tags((string) ($u['title'] ?? ''))); ?>">
                                <span class="tq-field__msg tq-field__hint">سطر واحد يظهر تحت اسمك.</span>
                            </div>

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-bio">نبذة عنك</label>
                                <textarea class="tq-textarea" id="tq-bio" name="biography" rows="6" maxlength="1500"
                                          placeholder="خبرتك، والصفوف التي تدرسها، وطريقتك في الشرح."><?php
                                    echo html_escape(strip_tags((string) ($u['biography'] ?? '')));
                                ?></textarea>
                                <span class="tq-field__msg tq-field__hint">
                                    <?php echo tq_iso('حتى 1500 حرف. تظهر في صفحتك العامة وفي بطاقتك عند حجز الحصص.'); ?>
                                </span>
                            </div>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit">حفظ صفحتي العامة</button>
                            </div>
                        </form>

                        <div class="tq-s-row" style="margin-block-start:var(--tq-space-xl)">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0">ظهورك على الموقع</p>
                                <p class="tq-micro" style="margin:0">
                                    <?php if (!empty($u['is_public'])): ?>
                                        صفحتك منشورة ويصل إليها الزوار.
                                    <?php else: ?>
                                        صفحتك غير منشورة بعد. النشر قرار إدارة لا مفتاح في إعداداتك،
                                        فما يعرض على الموقع تراجعه المنصة قبل نشره.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if (!empty($u['is_public'])): ?>
                                <a class="tq-btn tq-btn--secondary tq-btn--sm" target="_blank" rel="noopener"
                                   href="<?php echo base_url('instructor/' . (int) $tq_uid); ?>">عرض صفحتي</a>
                            <?php else: ?>
                                <?php echo tq_badge('idle', 'غير منشورة'); ?>
                            <?php endif; ?>
                        </div>
                    </section>

                <?php elseif ($active === 'security'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title">كلمة المرور</h2>
                        <form method="post" action="<?php echo $tq_save; ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="password">
                            <input type="hidden" name="s" value="security">

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-pw-cur">كلمة المرور الحالية</label>
                                <input class="tq-input" id="tq-pw-cur" name="current_password" type="password"
                                       required autocomplete="current-password">
                            </div>
                            <div class="tq-fieldgrid">
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-pw-new">كلمة المرور الجديدة</label>
                                    <input class="tq-input" id="tq-pw-new" name="new_password" type="password"
                                           required minlength="8" autocomplete="new-password"
                                           aria-describedby="tq-pw-hint">
                                    <span class="tq-field__msg tq-field__hint" id="tq-pw-hint">
                                        <?php echo tq_iso('ثمانية محارف فأكثر.'); ?>
                                    </span>
                                </div>
                                <div class="tq-field">
                                    <label class="tq-field__label" for="tq-pw-again">تأكيد كلمة المرور</label>
                                    <input class="tq-input" id="tq-pw-again" name="confirm_password" type="password"
                                           required minlength="8" autocomplete="new-password">
                                </div>
                            </div>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit">تغيير كلمة المرور</button>
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
                        <h2 class="tq-card__title">الجلسات والأجهزة</h2>
                        <p class="tq-caption">
                            كل جهاز سجلت منه دخولا ولم تخرج منه بعد. والحد
                            <?php echo tq_iso(html_escape((string) (get_settings('allowed_device_number_of_loging') ?: '—'))); ?>
                            جلسة في الوقت نفسه — وبعده يطلب منك تأكيد الجهاز الجديد.
                        </p>

                        <?php if (!$tq_sessions): ?>
                            <p class="tq-caption" style="margin-block-end:0">لا جلسة مسجلة غير هذه.</p>
                        <?php else: ?>
                            <div class="tq-table-wrap">
                                <table class="tq-table">
                                    <caption class="tq-sr">جلسات حسابك المفتوحة</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">الجهاز</th>
                                            <th scope="col">عنوان الاتصال</th>
                                            <th scope="col">آخر نشاط</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tq_sessions as $tq_s): ?>
                                            <?php $tq_is_here = ((string) $tq_s['id'] === (string) $tq_here); ?>
                                            <tr>
                                                <td data-label="الجهاز">
                                                    <?php if ($tq_is_here): ?>
                                                        <span class="tq-badge tq-badge--mastered">هذا الجهاز</span>
                                                    <?php else: ?>
                                                        <span class="tq-caption">جهاز آخر</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="عنوان الاتصال">
                                                    <span class="tq-ltr" dir="ltr"><?php echo html_escape((string) $tq_s['ip_address']); ?></span>
                                                </td>
                                                <td data-label="آخر نشاط">
                                                    <?php echo tq_iso(html_escape(date('Y-m-d H:i', (int) $tq_s['timestamp']))); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <p class="tq-micro tq-muted" style="margin-block-end:0">
                            إنهاء جلسة على جهاز آخر عن بعد غير متاح بعد. وتغيير كلمة المرور أعلاه
                            يبقي الجلسات القائمة كما هي.
                        </p>
                    </section>

                    <section class="tq-card">
                        <h2 class="tq-card__title">بياناتك</h2>
                        <p class="tq-caption">
                            حقان ينفذان كإجراءين لا كنص في سياسة: أن تأخذ نسخة من بياناتك،
                            وأن تنهي حسابك.
                        </p>
                        <div class="tq-s-row">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0">تصدير بياناتي</p>
                                <p class="tq-micro" style="margin:0">ملف بكل ما يخص حسابك، ينزل مباشرة.</p>
                            </div>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('teacher/export-data'); ?>">
                                <?php echo tq_icon('download'); ?> طلب نسخة
                            </a>
                        </div>
                        <div class="tq-s-row">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0">حذف الحساب</p>
                                <p class="tq-micro" style="margin:0">
                                    تستبدل بياناتك الشخصية بقيم مجهولة. وتبقى قيود محفظتك وفواتيرها
                                    بمعرف مجهول لأن الالتزام الضريبي يوجب حفظها.
                                </p>
                            </div>
                            <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('teacher/delete-account'); ?>">حذف حسابي</a>
                        </div>
                    </section>

                <?php elseif ($active === 'alerts'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title">التنبيهات</h2>
                        <p class="tq-caption">
                            لكل نوع قناتان مستقلتان — إيقاف قناة لا يوقف الأخرى.
                            وليست هناك قناة «إشعار على الجهاز» لأن المنصة لا ترسل إشعارات دفع بعد.
                        </p>

                        <form method="post" action="<?php echo $tq_save; ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="alerts">
                            <input type="hidden" name="s" value="alerts">

                            <table class="tq-table">
                                <thead>
                                    <tr>
                                        <th scope="col">النوع</th>
                                        <?php foreach ($tq_channels as $ck => $clabel): ?>
                                            <th scope="col"><?php echo html_escape($clabel); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tq_types as $tkey => [$tlabel, $thint]): ?>
                                        <tr>
                                            <td data-label="النوع">
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

                            <h3 class="tq-card__title" style="font:var(--tq-type-h2);margin-block-start:var(--tq-space-xl)">ساعات الصمت</h3>

                            <div class="tq-prefrow">
                                <span class="tq-prefrow__main">
                                    <label class="tq-prefrow__title" for="tq-quiet-on">تفعيل ساعات الصمت</label>
                                    <span class="tq-prefrow__hint">لا تنبيهات داخل النافذة التي تختارها.</span>
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
                                    <label class="tq-field__label" for="tq-quiet-from">تبدأ الساعة</label>
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
                                    <label class="tq-field__label" for="tq-quiet-to">تنتهي الساعة</label>
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
                                <button class="tq-btn tq-btn--primary" type="submit">حفظ تفضيلات التنبيهات</button>
                            </div>
                        </form>
                    </section>

                <?php elseif ($active === 'prefs'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title">التفضيلات العامة</h2>

                        <form method="post" action="<?php echo $tq_save; ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="action" value="prefs">
                            <input type="hidden" name="s" value="prefs">

                            <div class="tq-field">
                                <label class="tq-field__label" for="tq-lang">لغة الواجهة</label>
                                <select class="tq-select" id="tq-lang" name="language" aria-describedby="tq-lang-hint">
                                    <?php foreach ($tq_langs as $lk => $ll): ?>
                                        <option value="<?php echo html_escape($lk); ?>"
                                            <?php echo ($tq_prefs['language'] === $lk) ? ' selected' : ''; ?>>
                                            <?php echo html_escape($ll); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="tq-field__msg tq-field__hint" id="tq-lang-hint">
                                    اتجاه الصفحة نتيجة للغة لا إعداد مستقل — فاختيار الإنجليزية يقلب الاتجاه معها.
                                </span>
                            </div>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit">حفظ التفضيلات</button>
                            </div>
                        </form>
                    </section>

                <?php else: ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title">التحويلات المالية</h2>
                        <p class="tq-caption">
                            بيانات التحويل لا تحفظ على حسابك: تكتب مع كل طلب سحب على حدة، ولا تظهر
                            بعدها إلا بأربع خاناتها الأخيرة. فلا وسيلة دفع محفوظة هنا تعدلها.
                        </p>
                        <div class="tq-s-row">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0">رصيدك وطلبات سحبك</p>
                                <p class="tq-micro" style="margin:0">المتاح والمعلق وكشف الحساب وسجل التحويلات.</p>
                            </div>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('teacher/wallet'); ?>">المحفظة والأرباح</a>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="tq-card">
                    <div class="tq-s-row">
                        <div class="tq-s-row__main">
                            <p class="tq-strong" style="margin:0">تسجيل الخروج</p>
                            <p class="tq-micro" style="margin:0">إنهاء جلستك على هذا الجهاز.</p>
                        </div>
                        <?php /* السؤال قبل الخروج — كما في بوابة ولي الأمر حرفا بحرف.
                                 والمعلم أولى به: قد يكون في يده درس نصف مرفوع أو تصحيح
                                 نصف مكتوب، وخروج بنقرة واحدة يضيعهما. */ ?>
                        <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('login/logout'); ?>"
                           data-tq-confirm-title="تسجيل الخروج؟"
                           data-tq-confirm="تنتهي جلستك على هذا الجهاز، وتحتاج بريدك وكلمة مرورك للدخول من جديد."
                           data-tq-confirm-note="ما لم يحفظ من نموذج مفتوح لا يحفظ بالخروج."
                           data-tq-confirm-ok="تسجيل الخروج"
                           data-tq-confirm-tone="danger">تسجيل الخروج</a>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <h2 class="tq-card__title">حسابي</h2>
            <div class="tq-row" style="gap:var(--tq-space-m)">
                <img class="tq-avatar" src="<?php echo html_escape($tq_avatar); ?>" alt="">
                <div>
                    <p class="tq-strong" style="margin:0"><?php echo html_escape(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))); ?></p>
                    <p class="tq-micro" style="margin:0">معلم</p>
                </div>
            </div>
        </div>

        <div class="tq-card">
            <h2 class="tq-card__title">الدعم والمساعدة</h2>
            <div class="tq-stack" style="--tq-space-l:var(--tq-space-s)">
                <a href="<?php echo base_url('teacher/messages'); ?>?filter=admin">مراسلة الإدارة</a>
                <a href="<?php echo base_url('faq'); ?>">الأسئلة الشائعة</a>
                <a href="<?php echo base_url('contact'); ?>">تواصل معنا</a>
                <a href="<?php echo base_url('privacy'); ?>">سياسة الخصوصية</a>
            </div>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
