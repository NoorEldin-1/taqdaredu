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
$tq_title = 'الإعدادات';
$tq_sub   = 'إدارة حسابك وتفضيلاتك';
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
$tq_pay_names = ['manual' => 'تحويل بنكي يدوي', 'free' => 'باقة مجانية'];

$tq_save = base_url('student/settings/save');
$tq_ok   = $CI->session->flashdata('flash_message');
$tq_err  = $CI->session->flashdata('error_message');

$sections = [
    ['profile',  'الملف الشخصي',      'users'],
    ['security', 'الأمان والخصوصية',  'lock'],
    ['alerts',   'التنبيهات',         'bell'],
    ['prefs',    'التفضيلات العامة',  'cog'],
    ['billing',  'طريقة الدفع',       'wallet'],
    ['offline',  'تحميلاتك',          'download'],
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
                                <h2 class="tq-card__title">طلب متابعة من ولي أمر</h2>
                                <p class="tq-caption">
                                    الموافقة تمنحه الاطلاع على تقدمك ونتائجك. وهي قرارك أنت،
                                    ويمكنك سحبها متى شئت.
                                </p>

                                <div class="tq-pastel tq-pastel--sand" style="margin-block:var(--tq-space-l)">
                                    <span class="tq-pastel__label tq-micro">نص ما توقع عليه</span>
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
                                            <button type="submit" class="tq-btn tq-btn--mastery tq-btn--sm">أوافق</button>
                                        </form>

                                        <form method="post" action="<?php echo base_url('student/parent-link'); ?>" class="tq-form-inline"
                                              data-tq-confirm-title="رفض طلب <?php echo html_escape($tq_pl['name']); ?>؟"
                                              data-tq-confirm="لن يفتح شيء من بياناتك، ويصله أنك لم توافق."
                                              data-tq-confirm-note="القرار قرارك وحدك، ولك أن تقبل طلبا جديدا منه لاحقا."
                                              data-tq-confirm-ok="أرفض الطلب">
                                            <?php echo tq_csrf(); ?>
                                            <input type="hidden" name="link_id" value="<?php echo (int) $tq_pl['id']; ?>">
                                            <input type="hidden" name="act" value="reject">
                                            <button type="submit" class="tq-btn tq-btn--secondary tq-btn--sm">أرفض</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($tq_active_links): ?>
                            <div class="tq-card tq-card--panel" style="margin-block-end:var(--tq-space-xl)">
                                <h2 class="tq-card__title">من يتابع حسابك</h2>
                                <p class="tq-caption">
                                    يرى تقدمك في المواد وأيام نشاطك ونتائج اختباراتك ومدفوعاتك وملاحظات معلميك.
                                    ولا يرى محادثاتك مع المساعد الذكي ولا منشوراتك ولا إجاباتك الخاطئة مفردة.
                                </p>

                                <?php foreach ($tq_active_links as $tq_al): ?>
                                    <div class="tq-row" style="gap:var(--tq-space-m);align-items:center;margin-block-start:var(--tq-space-l);flex-wrap:wrap">
                                        <span style="flex:1;min-inline-size:0">
                                            <span class="tq-strong" style="display:block"><?php echo html_escape($tq_al['name'] ?: $tq_al['email']); ?></span>
                                            <span class="tq-micro" style="display:block">
                                                وافقت بتاريخ <?php echo TQ_LRI . html_escape((string) $tq_al['consent_at']) . TQ_PDI; ?>
                                            </span>
                                        </span>
                                        <?php echo tq_badge('mastered', 'يتابعك'); ?>
                                        <form method="post" action="<?php echo base_url('student/parent-link'); ?>" class="tq-form-inline"
                                              data-tq-confirm-title="سحب موافقتك على متابعة <?php echo html_escape($tq_al['name']); ?>؟"
                                              data-tq-confirm="لن يرى شيئا من بياناتك بعدها، ويصله إشعار بأنك سحبت موافقتك."
                                              data-tq-confirm-note="يبقى في السجل تاريخ موافقتك وتاريخ سحبها. ولك أن توافق من جديد متى شئت."
                                              data-tq-confirm-ok="أسحب موافقتي"
                                              data-tq-confirm-tone="danger">
                                            <?php echo tq_csrf(); ?>
                                            <input type="hidden" name="link_id" value="<?php echo (int) $tq_al['id']; ?>">
                                            <input type="hidden" name="act" value="withdraw">
                                            <button type="submit" class="tq-btn tq-btn--ghost tq-btn--sm">أسحب موافقتي</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

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
                                        اتركه فارغا لتبقى صورتك كما هي.
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
                                    ولا تصلك رسائلنا عليه؟
                                    <a href="<?php echo base_url('student/settings?s=alerts#tq-spam'); ?>">اقرأ ما تفعله في دقيقة</a>.
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

                        <?php /* التوقيت إعداد منصة لا إعداد حساب، فيقال كذلك ولا يوضع في نموذج. */ ?>
                        <p class="tq-micro tq-muted" style="margin-block-start:var(--tq-space-l)">
                            توقيت المنصة كلها
                            <?php echo tq_iso(html_escape(function_exists('get_settings') ? (get_settings('timezone') ?: 'Asia/Riyadh') : 'Asia/Riyadh')); ?>،
                            وليس إعدادا لكل حساب على حدة.
                        </p>
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

                        <?php /* لا زر «إنهاء هذه الجلسة» بجوار كل صف: إنهاء جلسة عن بعد
                                 مسار كتابة لا وجود له في المتحكم، وزر يعد بما لا يقع أسوأ
                                 من غيابه. والخروج من هذا الجهاز بالزر أسفل الصفحة. */ ?>
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
                                <p class="tq-micro" style="margin:0">ملف بكل ما يخص حسابك، يبنى ثم يصلك برابط مؤقت.</p>
                            </div>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('student/export_data'); ?>">
                                <?php echo tq_icon('download'); ?> طلب نسخة
                            </a>
                        </div>
                        <div class="tq-s-row">
                            <div class="tq-s-row__main">
                                <p class="tq-strong" style="margin:0">حذف الحساب</p>
                                <p class="tq-micro" style="margin:0">
                                    تستبدل بياناتك الشخصية بقيم مجهولة. وتبقى الفواتير بمعرف مجهول
                                    لأن الالتزام الضريبي يوجب حفظها.
                                </p>
                            </div>
                            <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('student/delete_account'); ?>">حذف حسابي</a>
                        </div>
                    </section>

                <?php elseif ($active === 'alerts'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title">التنبيهات</h2>
                        <p class="tq-caption">
                            لكل نوع قناتان مستقلتان — إيقاف قناة لا يوقف الأخرى.
                            وليست هناك قناة «إشعار على الجهاز» لأن المنصة لا ترسل إشعارات دفع بعد.
                        </p>

                        <?php /* TQ-SPAM — قناة البريد مفعلة في الجدول أدناه ولا يصل
                                 منها شيء: مزود البريد صنفها غير مرغوبة. ومن لا يعرف
                                 ذلك يقلب المفاتيح هنا مرارا ولا يتغير شيء، ثم يوقفها
                                 كلها. فالتنبيه فوق الجدول لا تحته. */ ?>
                        <?php echo tq_spam_notice(array(
                            'email' => (string) ($u['email'] ?? ''),
                            'what'  => 'إشعاراتنا',
                        )); ?>

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

                            <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                                ساعات الصمت تؤجل التنبيه ولا تلغيه: يصلك بعد انتهائها، ويبقى
                                في شاشة إشعاراتك طوالها. وتنبيهات المال والحصص لا تؤجل.
                            </p>

                            <div class="tq-formbar">
                                <button class="tq-btn tq-btn--primary" type="submit">حفظ تفضيلات التنبيهات</button>
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
                            style="font:var(--tq-type-h2);margin-block-start:var(--tq-space-h1)">التحفيز</h3>

                        <form method="post" action="<?php echo base_url('student/gamify'); ?>">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="gamify" value="<?php echo $tq_gam ? '0' : '1'; ?>">

                            <div class="tq-prefrow">
                                <span class="tq-prefrow__main">
                                    <span class="tq-prefrow__title">السلسلة وحلقة الهدف</span>
                                    <span class="tq-prefrow__hint">
                                        <?php echo $tq_gam
                                            ? 'تظهر في لوحتك أيامك المتتالية وتقدمك نحو هدف اليوم.'
                                            : 'موقوفة الآن: لا سلسلة ولا حلقة هدف ولا أرقام تحفيز في أي شاشة.'; ?>
                                    </span>
                                </span>
                                <span class="tq-prefrow__end">
                                    <button class="tq-btn <?php echo $tq_gam ? 'tq-btn--secondary' : 'tq-btn--primary'; ?>"
                                            type="submit"><?php
                                        echo $tq_gam ? 'أوقف التلعيب' : 'أعد التلعيب'; ?></button>
                                </span>
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


                <?php elseif ($active === 'billing'): ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title">طريقة الدفع</h2>
                        <p class="tq-caption">
                            المنصة لا تحفظ بيانات بطاقتك ولا تخزن وسيلة دفع على حسابك — تدفع كل فاتورة
                            عند إصدارها، ويبقى سجلها في صفحة المدفوعات.
                        </p>

                        <?php if ($tq_last_pay): ?>
                            <div class="tq-s-row">
                                <div class="tq-s-row__main">
                                    <p class="tq-strong" style="margin:0">آخر وسيلة استعملتها</p>
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
                                <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('student/payments'); ?>">المدفوعات والفواتير</a>
                            </div>
                        <?php else: ?>
                            <div class="tq-empty">
                                <p class="tq-empty__title">لم تسجل لك دفعة بعد</p>
                                <p class="tq-empty__text">
                                    عند أول اشتراك تظهر وسيلة الدفع التي استعملتها هنا، وتظهر فاتورتها في صفحة المدفوعات.
                                </p>
                                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>">تصفح الباقات</a>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php else: ?>
                    <section class="tq-card">
                        <h2 class="tq-card__title">تحميلاتك</h2>
                        <p class="tq-caption">
                            التحميل للعمل دون اتصال غير متاح في نسخة الويب بعد، فلا سجل تحميلات ولا مساحة
                            مستخدمة نعرضها لك. والمواد تشاهد داخل المنصة بصلاحية زمنية — تشاهد ولا تملك نسخة.
                        </p>
                        <div style="margin-block-start:var(--tq-space-l)">
                            <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('student/materials'); ?>">المواد التعليمية</a>
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
                                 كان الرابط هنا ينهي الجلسة بنقرة واحدة وبجواره أزرار
                                 حفظ الإعدادات، فالسهو نقرة تكلف إعادة الدخول وسط عمل.
                                 و`data-tq-confirm` يقرأه `taqdar.js` المحمل في كل شاشة
                                 بوابة، فلا سكربت جديدا هنا. */ ?>
                        <a class="tq-btn tq-btn--danger tq-btn--sm" href="<?php echo base_url('login/logout'); ?>"
                           data-tq-confirm-title="تسجيل الخروج؟"
                           data-tq-confirm="تنتهي جلستك على هذا الجهاز، وتحتاج بريدك وكلمة مرورك للدخول من جديد."
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
                    <p class="tq-micro" style="margin:0"><?php echo $tq_role === 'teacher' ? 'معلم' : 'طالب'; ?></p>
                </div>
            </div>
        </div>

        <div class="tq-card">
            <h2 class="tq-card__title">الدعم والمساعدة</h2>
            <div class="tq-stack" style="--tq-space-l:var(--tq-space-s)">
                <a href="<?php echo base_url('faq'); ?>">الأسئلة الشائعة</a>
                <a href="<?php echo base_url('contact'); ?>">تواصل معنا</a>
                <a href="<?php echo base_url('privacy'); ?>">سياسة الخصوصية</a>
            </div>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
