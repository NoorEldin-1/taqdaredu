<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * الإعدادات — ستة أقسام بتنقل جانبي.
 *
 * كانت هذه الصفحة تقرأ ولا تكتب: صفر نموذج وصفر حقل. وأخطر من ذلك أن
 * جدول التنبيهات فيها كان يعرض قيما حرفية مكتوبة في العرض («مفعل»،
 * «مطفأ») كأنها تفضيلات صاحب الحساب، وكذلك «اللغة: العربية» و«ساعات صمت
 * من 10 مساء إلى 7 صباحا». والنقص يرى فيطلب، أما القيمة المفبركة
 * فتصدق فلا تطلب — ولذلك أزيلت كلها.
 *
 * كل قيمة هنا الآن لها مصدر: `users` للهوية، و`tq_prefs_user` و
 * `tq_prefs_notify` للتفضيلات (يبنيهما ويقرؤهما Taqdar_settings_model)،
 * و`subscriptions` لآخر وسيلة دفع. وما لا مصدر له قيل نصا إنه غير موجود
 * ولم يعرض بلون الإعداد.
 *
 * النماذج ترسل POST إلى student/settings/save. البرنامج ليس من ملفات هذه
 * المهمة، والصفحة تعرض كاملة سواء وجد أو لم يوجد.
 *
 * وفيها حقان تنص الوثيقة على تنفيذهما **كشاشتين لا كسياسة مكتوبة**:
 * تصدير البيانات وحذف الحساب. وحذف الحساب **تجهيل لا محو**.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

/* داخل العرض `$this` ليس المتحكم، فتحميل نموذج به يبتر الصفحة صامتا. */
$CI = get_instance();
$CI->load->model('taqdar_settings_model');
$tq_set = $CI->taqdar_settings_model;

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'settings';
$tq_role  = $tq_role ?? 'student';
$tq_title = t('الإعدادات');
$tq_sub   = t('إدارة حسابك وتفضيلاتك');
$tq_icon  = 'cog';

$u = $CI->db->where('id', $tq_uid)->get('users')->row_array() ?: [];

$tq_prefs    = $tq_set->prefs($tq_uid);
$tq_matrix   = $tq_set->notify_matrix($tq_uid);
$tq_types    = $tq_set->notify_types();
$tq_channels = $tq_set->notify_channels();
/* لا `themes()` هنا: الوضع الداكن أزيل والوجه واحد فاتح
   (انظر `Taqdar_settings_model::save_prefs` — يثبت `auto` ولا يقرأ المدخل).
   وكان يحمل في متغير لا يقرؤه شيء في الصفحة. */
$tq_langs    = $tq_set->languages();

/* الصورة: الاسم في القاعدة رمز بلا امتداد، والملف <code>.jpg — وعرضه
   بلا امتداد كان يعطي صورة مكسورة لا صورة حساب. وهذا ما كانت تفعله عشر
   شاشات أخرى، فصار الحل في `tqs_person_img` مرة لا في كل موضع. */
$tq_avatar = tqs_person_img($u['image'] ?? '');

/* آخر وسيلة دفع استعملت فعلا — لا «وسيلة محفوظة»، فالمنصة لا تحفظ بطاقات. */
$tq_last_pay = $CI->db->table_exists('subscriptions')
    ? $CI->db->select('method, created_at')->where('user_id', $tq_uid)
             ->where('method IS NOT NULL', null, false)
             ->order_by('id', 'DESC')->limit(1)
             ->get('subscriptions')->row_array()
    : null;
$tq_pay_names = ['manual' => t('تحويل بنكي يدوي'), 'free' => t('باقة مجانية')];

$tq_save = base_url('student/settings/save');
$tq_ok   = tq_flash('flash_message');
$tq_err  = tq_flash('error_message');

$sections = [
    ['profile',  t('الملف الشخصي'),      'users'],
    ['security', t('الأمان والخصوصية'),  'lock'],
    ['alerts',   t('التنبيهات'),         'bell'],
    ['prefs',    t('التفضيلات العامة'),  'cog'],
    ['billing',  t('طريقة الدفع'),       'wallet'],
    ['offline',  t('تحميلاتك'),          'download'],
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
                        <?php
                        /* روابط أولياء الأمر — المعلقة والنشطة معا.
                           تعرض قبل كل شيء لأنها قرار يخص خصوصيته.

                           والقراءة من `Taqdar_parent_model` لا من استعلام
                           مكتوب في العرض: الملكية والحالة مصدرهما واحد،
                           ونسخة استعلام في كل شاشة تتباعد عن أختها.

                           والنشطة تعرض كذلك لأن نص الموافقة يعد صراحة:
                           «ولي أن أسحب هذه الموافقة متى شئت» — وكان وعدا
                           بلا باب، فالطالب يوافق ولا يجد بعدها سبيلا
                           للتراجع إلا أن يطلب من وليه أن يفك الربط بنفسه. */
                        $tq_ci_pl = &get_instance();
                        $tq_ci_pl->load->model('taqdar_parent_model');
                        $tq_sid_pl        = (int) $tq_ci_pl->session->userdata('user_id');
                        $tq_pending_links = $tq_ci_pl->taqdar_parent_model->links_of_student($tq_sid_pl, 'pending');
                        $tq_active_links  = $tq_ci_pl->taqdar_parent_model->links_of_student($tq_sid_pl, 'active');
                        ?>
                        <?php if ($tq_pending_links): ?>
                            <div class="tq-card tq-card--panel" style="margin-block-end:var(--tq-space-xl)">
                                <h2 class="tq-card__title"><?php echo t('طلب متابعة من ولي أمر'); ?></h2>
                                <p class="tq-caption">
                                    <?php echo t('الموافقة تمنحه الاطلاع على تقدمك ونتائجك. وهي قرارك أنت، ويمكنك سحبها متى شئت.'); ?>
                                </p>

                                <div class="tq-pastel tq-pastel--sand" style="margin-block:var(--tq-space-l)">
                                    <span class="tq-pastel__label tq-micro"><?php echo t('نص ما توقع عليه'); ?></span>
                                    <p class="tq-pastel__body" style="margin:var(--tq-space-xs) 0 0">
                                        <?php echo html_escape(Taqdar_parent_model::CONSENT_TEXT); ?>
                                    </p>
                                </div>

                                <?php foreach ($tq_pending_links as $tq_pl): ?>
                                    <div class="tq-row" style="gap:var(--tq-space-m);align-items:center;margin-block-start:var(--tq-space-m);flex-wrap:wrap">
                                        <span class="tq-strong" style="flex:1;min-inline-size:0">
                                            <?php echo html_escape($tq_pl['name'] ?: $tq_pl['email']); ?>
                                            <span class="tq-micro tq-ltr" style="display:block"><?php echo html_escape((string) $tq_pl['email']); ?></span>
                                        </span>

                                        <form method="post" action="<?php echo base_url('student/parent-link'); ?>" class="tq-form-inline">
                                            <?php echo tq_csrf(); ?>
                                            <input type="hidden" name="link_id" value="<?php echo (int) $tq_pl['id']; ?>">
                                            <input type="hidden" name="act" value="approve">
                                            <button type="submit" class="tq-btn tq-btn--mastery tq-btn--sm"><?php echo t('أوافق'); ?></button>
                                        </form>

                                        <form method="post" action="<?php echo base_url('student/parent-link'); ?>" class="tq-form-inline"
                                              data-tq-confirm-title="<?php echo te('رفض طلب ____؟', array(html_escape($tq_pl['name']))); ?>"
                                              data-tq-confirm="<?php echo te('لن يفتح شيء من بياناتك، ويصله أنك لم توافق.'); ?>"
                                              data-tq-confirm-note="<?php echo te('القرار قرارك وحدك، ولك أن تقبل طلبا جديدا منه لاحقا.'); ?>"
                                              data-tq-confirm-ok="<?php echo te('أرفض الطلب'); ?>">
                                            <?php echo tq_csrf(); ?>
                                            <input type="hidden" name="link_id" value="<?php echo (int) $tq_pl['id']; ?>">
                                            <input type="hidden" name="act" value="reject">
                                            <button type="submit" class="tq-btn tq-btn--secondary tq-btn--sm"><?php echo t('أرفض'); ?></button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($tq_active_links): ?>
                            <div class="tq-card tq-card--panel" style="margin-block-end:var(--tq-space-xl)">
                                <h2 class="tq-card__title"><?php echo t('من يتابع حسابك'); ?></h2>
                                <p class="tq-caption">
                                    <?php echo t('يرى تقدمك في المواد وأيام نشاطك ونتائج اختباراتك ومدفوعاتك وملاحظات معلميك. ولا يرى محادثاتك مع المساعد الذكي ولا منشوراتك ولا إجاباتك الخاطئة مفردة.'); ?>
                                </p>

                                <?php foreach ($tq_active_links as $tq_al): ?>
                                    <div class="tq-row" style="gap:var(--tq-space-m);align-items:center;margin-block-start:var(--tq-space-l);flex-wrap:wrap">
                                        <span style="flex:1;min-inline-size:0">
                                            <span class="tq-strong" style="display:block"><?php echo html_escape($tq_al['name'] ?: $tq_al['email']); ?></span>
                                            <span class="tq-micro" style="display:block">
                                                <?php echo t('وافقت بتاريخ'); ?> <?php echo tq_stamp($tq_al['consent_at']); ?>
                                            </span>
                                        </span>
                                        <?php echo tq_badge('mastered', t('يتابعك')); ?>
                                        <form method="post" action="<?php echo base_url('student/parent-link'); ?>" class="tq-form-inline"
                                              data-tq-confirm-title="<?php echo te('سحب موافقتك على متابعة ____؟', array(html_escape($tq_al['name']))); ?>"
                                              data-tq-confirm="<?php echo te('لن يرى شيئا من بياناتك بعدها، ويصله إشعار بأنك سحبت موافقتك.'); ?>"
                                              data-tq-confirm-note="<?php echo te('يبقى في السجل تاريخ موافقتك وتاريخ سحبها. ولك أن توافق من جديد متى شئت.'); ?>"
                                              data-tq-confirm-ok="<?php echo te('أسحب موافقتي'); ?>"
                                              data-tq-confirm-tone="danger">
                                            <?php echo tq_csrf(); ?>
                                            <input type="hidden" name="link_id" value="<?php echo (int) $tq_al['id']; ?>">
                                            <input type="hidden" name="act" value="withdraw">
                                            <button type="submit" class="tq-btn tq-btn--ghost tq-btn--sm"><?php echo t('أسحب موافقتي'); ?></button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

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
                                        <?php echo t('JPG أو PNG أو WebP، الحد الأقصى ____. اتركه فارغا لتبقى صورتك كما هي.', array(tq_iso(t('2 ميجابايت')))); ?>
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
                                    <a href="<?php echo base_url('student/settings?s=alerts#tq-spam'); ?>"><?php echo t('اقرأ ما تفعله في دقيقة'); ?></a>.
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

                        <?php /* التوقيت إعداد منصة لا إعداد حساب، فيقال كذلك ولا يوضع في نموذج. */ ?>
                        <p class="tq-micro tq-muted" style="margin-block-start:var(--tq-space-l)">
                            <?php echo t('توقيت المنصة كلها ____، وليس إعدادا لكل حساب على حدة.', array(tq_iso(html_escape(function_exists('get_settings') ? (get_settings('timezone') ?: 'Asia/Riyadh') : 'Asia/Riyadh')))); ?>
                        </p>
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
                    /* الجلسات المفتوحة.
                       كان هذا القسم يقول «لا سجل أجهزة في المنصة بعد» — وهو غير صحيح:
                       `users.sessions` يحمل معرفات جلسات الحساب، و`ci_sessions` يحمل
                       لكل معرف عنوانه الشبكي وآخر نشاط له، وعليهما يقوم حد
                       `allowed_device_number_of_loging` الذي يمنع الدخول فعلا. فالسجل
                       قائم ويعمل، والشاشة وحدها كانت تنكره — فيقرأ الطالب أن المنصة
                       لا تعرف أجهزته وهي تحصيها وتقفل عليه بها.

                       والصفوف الميتة تطرح: جامع القمامة يحذف من `ci_sessions` ولا يمس
                       المصفوفة، فيبقى فيها معرف لا جلسة له. */
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

                        <?php /* لا زر «إنهاء هذه الجلسة» بجوار كل صف: إنهاء جلسة عن بعد
                                 مسار كتابة لا وجود له في المتحكم، وزر يعد بما لا يقع أسوأ
                                 من غيابه. والخروج من هذا الجهاز بالزر أسفل الصفحة. */ ?>
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
                                <p class="tq-micro" style="margin:0"><?php echo t('ملف بكل ما يخص حسابك، يبنى ثم يصلك برابط مؤقت.'); ?></p>
                            </div>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('student/export_data'); ?>">
                                <?php echo tq_icon('download'); ?> <?php echo t('طلب نسخة'); ?>
                            </a>
                        </div>
                        <div class="tq-s-row">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0"><?php echo t('حذف الحساب'); ?></p>
                                <p class="tq-micro" style="margin:0">
                                    <?php echo t('تستبدل بياناتك الشخصية بقيم مجهولة. وتبقى الفواتير بمعرف مجهول لأن الالتزام الضريبي يوجب حفظها.'); ?>
                                </p>
                            </div>
                            <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('student/delete_account'); ?>"><?php echo t('حذف حسابي'); ?></a>
                        </div>
                    </section>

                <?php elseif ($active === 'alerts'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('التنبيهات'); ?></h2>
                        <p class="tq-caption">
                            <?php echo t('لكل نوع قناتان مستقلتان — إيقاف قناة لا يوقف الأخرى. وليست هناك قناة «إشعار على الجهاز» لأن المنصة لا ترسل إشعارات دفع بعد.'); ?>
                        </p>

                        <?php /* TQ-SPAM — قناة البريد مفعلة في الجدول أدناه ولا يصل
                                 منها شيء: مزود البريد صنفها غير مرغوبة. ومن لا يعرف
                                 ذلك يقلب المفاتيح هنا مرارا ولا يتغير شيء، ثم يوقفها
                                 كلها. فالتنبيه فوق الجدول لا تحته. */ ?>
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

                            <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                                <?php echo t('ساعات الصمت تؤجل التنبيه ولا تلغيه: يصلك بعد انتهائها، ويبقى في شاشة إشعاراتك طوالها. وتنبيهات المال والحصص لا تؤجل.'); ?>
                            </p>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('حفظ تفضيلات التنبيهات'); ?></button>
                            </div>
                        </form>

                        <?php /* ── التلعيب ────────────────────────────────────────
                                `F2.6` يشترط «زر إيقاف كامل للتلعيب من الإعدادات».
                                ونموذج مستقل لا خانة في النموذج أعلاه: هذا يحفظ في
                                `tq_student_setup` وذاك في `tq_prefs_notify`، وجمعهما
                                في زر واحد يعني مسار حفظ يكتب في جدولين ويفشل نصفه
                                بصمت. */ ?>
                        <?php
                        $CI_g = &get_instance();
                        $CI_g->load->model('taqdar_learn_model', 'tq_learn');
                        $tq_gam = (int) $CI_g->tq_learn->setup((int) $tq_uid)['gamify'] === 1;
                        ?>
                        <h3 class="tq-card__title"
                            style="font:var(--tq-type-h2);margin-block-start:var(--tq-space-h1)"><?php echo t('التحفيز'); ?></h3>

                        <form method="post" action="<?php echo base_url('student/gamify'); ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="gamify" value="<?php echo $tq_gam ? '0' : '1'; ?>">

                            <div class="tq-prefrow">
                                <span class="tq-prefrow__main">
                                    <span class="tq-prefrow__title"><?php echo t('السلسلة وحلقة الهدف'); ?></span>
                                    <span class="tq-prefrow__hint">
                                        <?php echo $tq_gam
                                            ? t('تظهر في لوحتك أيامك المتتالية وتقدمك نحو هدف اليوم.')
                                            : t('موقوفة الآن: لا سلسلة ولا حلقة هدف ولا أرقام تحفيز في أي شاشة.'); ?>
                                    </span>
                                </span>
                                <span class="tq-prefrow__end">
                                    <button class="tq-btn <?php echo $tq_gam ? 'tq-btn--secondary' : 'tq-btn--primary'; ?>"
                                            type="submit"><?php
                                        echo $tq_gam ? t('أوقف التلعيب') : t('أعد التلعيب'); ?></button>
                                </span>
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


                <?php elseif ($active === 'billing'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('طريقة الدفع'); ?></h2>
                        <p class="tq-caption">
                            <?php echo t('المنصة لا تحفظ بيانات بطاقتك ولا تخزن وسيلة دفع على حسابك — تدفع كل فاتورة عند إصدارها، ويبقى سجلها في صفحة المدفوعات.'); ?>
                        </p>

                        <?php if ($tq_last_pay): ?>
                            <div class="tq-s-row">
                                <div class="tq-s-row__main">
                                    <p class="tq-strong" style="margin:0"><?php echo t('آخر وسيلة استعملتها'); ?></p>
                                    <p class="tq-micro" style="margin:0">
                                        <?php
                                        $m = (string) $tq_last_pay['method'];
                                        echo html_escape($tq_pay_names[$m] ?? $m);
                                        if (!empty($tq_last_pay['created_at'])) {
                                            echo ' — ' . tq_iso(html_escape(date('Y/m/d', strtotime($tq_last_pay['created_at']))));
                                        }
                                        ?>
                                    </p>
                                </div>
                                <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('student/payments'); ?>"><?php echo t('المدفوعات والفواتير'); ?></a>
                            </div>
                        <?php else: ?>
                            <div class="tq-empty">
                                <p class="tq-empty__title"><?php echo t('لم تسجل لك دفعة بعد'); ?></p>
                                <p class="tq-empty__text">
                                    <?php echo t('عند أول اشتراك تظهر وسيلة الدفع التي استعملتها هنا، وتظهر فاتورتها في صفحة المدفوعات.'); ?>
                                </p>
                                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>"><?php echo t('تصفح الباقات'); ?></a>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php else: ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title"><?php echo t('تحميلاتك'); ?></h2>
                        <p class="tq-caption">
                            <?php echo t('التحميل للعمل دون اتصال غير متاح في نسخة الويب بعد، فلا سجل تحميلات ولا مساحة مستخدمة نعرضها لك. والمواد تشاهد داخل المنصة بصلاحية زمنية — تشاهد ولا تملك نسخة.'); ?>
                        </p>
                        <div style="margin-block-start:var(--tq-space-l)">
                            <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('student/materials'); ?>"><?php echo t('المواد التعليمية'); ?></a>
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
                                 كان الرابط هنا ينهي الجلسة بنقرة واحدة وبجواره أزرار
                                 حفظ الإعدادات، فالسهو نقرة تكلف إعادة الدخول وسط عمل.
                                 و`data-tq-confirm` يقرأه `taqdar.js` المحمل في كل شاشة
                                 بوابة، فلا سكربت جديدا هنا. */ ?>
                        <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('login/logout'); ?>"
                           data-tq-confirm-title="<?php echo te('تسجيل الخروج؟'); ?>"
                           data-tq-confirm="<?php echo te('تنتهي جلستك على هذا الجهاز، وتحتاج بريدك وكلمة مرورك للدخول من جديد.'); ?>"
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
                    <p class="tq-micro" style="margin:0"><?php echo $tq_role === 'teacher' ? t('معلم') : t('طالب'); ?></p>
                </div>
            </div>
        </div>

        <div class="tq-card">
            <h2 class="tq-card__title"><?php echo t('الدعم والمساعدة'); ?></h2>
            <div class="tq-stack" style="--tq-space-l:var(--tq-space-s)">
                <a href="<?php echo base_url('faq'); ?>"><?php echo t('الأسئلة الشائعة'); ?></a>
                <a href="<?php echo base_url('contact'); ?>"><?php echo t('تواصل معنا'); ?></a>
                <a href="<?php echo base_url('privacy'); ?>"><?php echo t('سياسة الخصوصية'); ?></a>
            </div>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
