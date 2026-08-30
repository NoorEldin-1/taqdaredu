<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إشعارات الموقع.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **التبويبات صارت روابط حقيقية.** كانت `data-toggle="tab"` من
 *     Bootstrap تبدل بلا تغيير الرابط، والمتحكم يقرأ `?tab=` أصلا —
 *     فالنسختان تتعايشان: التبويب يبدل بجافاسكربت ولا يحفظ في الرابط،
 *     ثم يعود إلى «SMTP» عند أي حفظ أو تحديث. صار التبويب في الرابط،
 *     فيحفظ ويرسل ويعود إليه زر الرجوع.
 * ٢ — **زر حفظ SMTP كان `type="button"`** ينادي `checkRequiredFields()`
 *     — فالنموذج بلا زر إرسال حقيقي، ولا يحفظ إن تعثر ملف جافاسكربت.
 * ٣ — **كلمة مرور SMTP كانت تكشف بـ`onfocus`** وتعود بـ`onblur`: أي أن
 *     مجرد وضع المؤشر في الحقل يعرضها لمن خلف الكتف. صار زرا صريحا
 *     يضغط لإظهارها.
 * ٤ — **`initSummerNote(['#about_us', …])`** — نداء لمحرر على خمسة حقول
 *     **لا وجود لأي منها في هذه الشاشة**. منسوخ من شاشة الإعدادات.
 * ٥ — **`notification_enable_disable` كانت تعكس القيمة يدويا** ثم ترسل،
 *     وتعرض «تم» في كل حال — بما فيها حال الفشل، إذ لا معالج خطأ. وهنا
 *     يرجع المفتاح إن لم يرد الخادم بنجاح.
 * ٦ — **`$system_notification[$user_type]` بلا فحص** — صف ينقص فيه نوع
 *     مستخدم يطبع تحذير PHP فوق الجدول.
 */
$tq_tab  = isset($tab) ? $tab : 'smtp-settings';
$tq_rows = $this->db->get('notification_settings')->result_array();

$tq_tabs = array(
    'smtp-settings'  => array(t('إعدادات البريد الصادر'), 'send'),
    'email-template' => array(t('قوالب الرسائل'),          'mail'),
    'notification'   => array(t('ما يرسل ولمن'),           'bell'),
);
if (!isset($tq_tabs[$tq_tab])) $tq_tab = 'smtp-settings';

/** أسماء الأدوار — كانت تعرض `get_phrase('To student')` فتخرج إنجليزية. */
$tq_roles = array(
    'student'    => t('الطالب'),
    'instructor' => t('المعلم'),
    'teacher'    => t('المعلم'),
    'parent'     => t('ولي الأمر'),
    'admin'      => t('الإدارة'),
);
$tq_role = function ($k) use ($tq_roles) { return $tq_roles[$k] ?? $k; };

$tq_smtp = array(
    'protocol'        => array(t('البروتوكول'), t('smtp أو ssmtp أو mail'), true, 'text'),
    'smtp_crypto'     => array(t('التشفير'), t('ssl أو tls'), false, 'text'),
    'smtp_host'       => array(t('الخادم'), t('مثال: smtp.gmail.com'), true, 'text'),
    'smtp_port'       => array(t('المنفذ'), t('587 مع tls، و465 مع ssl'), true, 'text'),
    'smtp_from_email' => array(t('يرسل من'), t('العنوان الذي يظهر للمستقبل'), true, 'email'),
    'smtp_user'       => array(t('اسم المستخدم'), t('غالبا هو العنوان نفسه'), true, 'text'),
);
?>

<?php tqa_head(t('إشعارات الموقع'), t('من أين يرسل البريد، وبأي نص، وإلى من.'), 'bell'); ?>

<nav class="tqa-tabs" aria-label="<?php echo te('أقسام الإشعارات'); ?>">
    <?php foreach ($tq_tabs as $tq_k => [$tq_label, $tq_icon]): ?>
        <a href="<?php echo site_url('admin/notification_settings') . '?tab=' . $tq_k; ?>"
           <?php echo $tq_tab === $tq_k ? 'aria-current="page"' : ''; ?>>
            <?php echo html_escape($tq_label); ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tq_tab === 'smtp-settings'): ?>

    <div class="tqa-cols">
        <form class="tqa-card" method="post"
              action="<?php echo site_url('admin/notification_settings/smtp_settings'); ?>">
            <?php echo tq_csrf(); ?>

            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('send', 20); ?></span>
                <h2><?php echo t('البريد الصادر'); ?></h2>
            </div>

            <div class="tqa-fieldgrid">
                <?php foreach ($tq_smtp as $tq_k => [$tq_l, $tq_h, $tq_req, $tq_type]): ?>
                    <div class="tqa-field">
                        <label class="tqa-field__label" for="<?php echo $tq_k; ?>">
                            <?php echo html_escape($tq_l); ?>
                            <?php if ($tq_req): ?><span class="tqa-field__req" aria-hidden="true">*</span><?php endif; ?>
                        </label>
                        <input class="tqa-input tqa-input--ltr" type="<?php echo $tq_type; ?>"
                               id="<?php echo $tq_k; ?>" name="<?php echo $tq_k; ?>" dir="ltr"
                               autocomplete="off" spellcheck="false"
                               value="<?php echo html_escape(get_settings($tq_k)); ?>"
                               <?php echo $tq_req ? 'required' : ''; ?>>
                        <span class="tqa-field__hint"><?php echo html_escape($tq_h); ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="smtp_pass">
                        <?php echo t('كلمة المرور'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <div class="tqa-row" style="flex-wrap:nowrap">
                        <input class="tqa-input tqa-input--ltr" type="password" id="smtp_pass" name="smtp_pass"
                               dir="ltr" autocomplete="off" required
                               value="<?php echo html_escape(get_settings('smtp_pass')); ?>">
                        <?php /* الكشف بزر صريح لا بـ`onfocus`: كانت كلمة
                                 المرور تظهر بمجرد وضع المؤشر في الحقل. */ ?>
                        <button type="button" class="tqa-btn tqa-btn--ghost" data-tqa-reveal="smtp_pass"
                                aria-pressed="false">
                            <?php echo tq_icon('eye', 16); ?> <span><?php echo t('أظهر'); ?></span>
                        </button>
                    </div>
                    <span class="tqa-field__hint">
                        <?php echo t('مع Gmail تستعمل «كلمة مرور التطبيقات» لا كلمة مرور الحساب.'); ?>
                    </span>
                </div>
            </div>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--primary">
                    <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ إعدادات البريد'); ?>
                </button>
            </div>
        </form>

        <aside>
            <div class="tqa-note tqa-note--warn">
                <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
                <span>
                    <?php echo t('كل رسالة تخرج من المنصة تمر من هنا: تأكيد الحساب، واستعادة كلمة المرور، وتنبيهات ولي الأمر. فإعداد خاطئ هنا لا يظهر في شاشة — يظهر في رسائل لا تصل.'); ?>
                </span>
            </div>
        </aside>
    </div>

<?php elseif ($tq_tab === 'email-template'): ?>

    <div class="tqa-card tqa-card--flush">
        <?php if (empty($tq_rows)): ?>
            <?php tqa_empty(t('لا قوالب بريد'), t('تنشأ مع تركيب النظام.'), '', '', 'mail'); ?>
        <?php else: ?>
            <div class="tqa-table__wrap">
                <table class="tqa-table">
                    <caption class="tqa-sr"><?php echo t('قوالب رسائل البريد وعناوينها لكل دور'); ?></caption>
                    <thead>
                        <tr>
                            <th style="inline-size:60px">#</th>
                            <th><?php echo t('نوع الرسالة'); ?></th>
                            <th><?php echo t('العنوان المرسل'); ?></th>
                            <th style="inline-size:120px"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tq_rows as $tq_k => $tq_r):
                        $tq_subjects = json_decode((string) $tq_r['subject'], true);
                        if (!is_array($tq_subjects)) $tq_subjects = array();
                    ?>
                        <tr>
                            <td data-label="#"><span class="tqa-num"><?php echo $tq_k + 1; ?></span></td>

                            <td data-label="نوع الرسالة">
                                <span class="tqa-media__title"><?php echo html_escape($tq_r['setting_title']); ?></span>
                                <span class="tqa-media__sub"><?php echo html_escape($tq_r['setting_sub_title']); ?></span>
                            </td>

                            <td data-label="العنوان المرسل">
                                <?php if ($tq_subjects): ?>
                                    <?php foreach ($tq_subjects as $tq_who => $tq_subj): ?>
                                        <div style="margin-block-end:var(--tq-space-xs)">
                                            <span class="tqa-badge tqa-badge--muted"><?php echo html_escape($tq_role($tq_who)); ?></span>
                                            <?php echo html_escape($tq_subj); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="tqa-dim"><?php echo t('بلا عنوان'); ?></span>
                                <?php endif; ?>
                            </td>

                            <td data-label="إجراءات">
                                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                   href="<?php echo site_url('admin/edit_email_template/' . (int) $tq_r['id']); ?>">
                                    <?php echo tq_icon('edit', 14); ?> <?php echo t('تحرير'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="tqa-card">
        <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
            <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('bell', 20); ?></span>
            <h2><?php echo t('ما يرسل ولمن'); ?></h2>
        </div>

        <p style="margin:0 0 var(--tq-space-l);font:var(--tq-type-caption);color:var(--tq-text2)">
            <?php echo t('«إشعار المنصة» يظهر في جرس الحساب. و«إشعار البريد» يرسل رسالة. والمفتاح يحفظ فور تبديله.'); ?>
        </p>

        <?php foreach ($tq_rows as $tq_r):
            $tq_sys   = json_decode((string) $tq_r['system_notification'], true);
            $tq_mail  = json_decode((string) $tq_r['email_notification'], true);
            $tq_types = json_decode((string) $tq_r['user_types'], true);
            if (!is_array($tq_sys))   $tq_sys   = array();
            if (!is_array($tq_mail))  $tq_mail  = array();
            if (!is_array($tq_types)) $tq_types = array();

            $tq_locked = (int) $tq_r['is_editable'] !== 1;
        ?>
            <div class="tqa-prefrow" style="align-items:flex-start">
                <div class="tqa-prefrow__main">
                    <span class="tqa-prefrow__title">
                        <?php echo html_escape($tq_r['setting_title']); ?>
                        <?php if ($tq_locked): ?>
                            <span class="tqa-badge tqa-badge--warn"><?php echo t('لا يعدل'); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="tqa-prefrow__hint"><?php echo html_escape($tq_r['setting_sub_title']); ?></span>
                </div>

                <div class="tqa-prefrow__end" style="flex-wrap:wrap;align-items:flex-start;gap:var(--tq-space-l)">
                    <?php foreach ($tq_types as $tq_t): ?>
                        <div>
                            <span class="tqa-field__hint" style="margin:0 0 var(--tq-space-xs)">
                                <?php echo html_escape($tq_role($tq_t)); ?>
                            </span>

                            <?php foreach (array(
                                'system' => array(t('إشعار المنصة'), !empty($tq_sys[$tq_t])),
                                'email'  => array(t('إشعار البريد'), !empty($tq_mail[$tq_t])),
                            ) as $tq_kind => [$tq_lbl, $tq_val]):
                                $tq_dom = $tq_r['id'] . '-' . $tq_t . '-' . $tq_kind;
                            ?>
                                <label class="tqa-row" for="n-<?php echo $tq_dom; ?>"
                                       style="gap:var(--tq-space-s);margin-block-end:var(--tq-space-xs);cursor:pointer">
                                    <span class="tqa-switch">
                                        <input type="checkbox" id="n-<?php echo $tq_dom; ?>"
                                               data-tqa-notif="<?php echo (int) $tq_r['id']; ?>"
                                               data-tqa-notif-role="<?php echo html_escape($tq_t); ?>"
                                               data-tqa-notif-kind="<?php echo $tq_kind; ?>"
                                               <?php echo $tq_val ? 'checked' : ''; ?>
                                               <?php echo $tq_locked ? 'disabled' : ''; ?>>
                                        <span class="tqa-switch__track" aria-hidden="true"></span>
                                    </span>
                                    <span style="font:var(--tq-type-micro);color:var(--tq-text2)">
                                        <?php echo $tq_lbl; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<script>
(function () {
    'use strict';

    var CSRF = window.TQ_CSRF || null;

    /* كشف كلمة المرور: زر يعلن حالته، لا `onfocus` يكشفها بلا قصد. */
    Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-reveal]'), function (btn) {
        var field = document.getElementById(btn.getAttribute('data-tqa-reveal'));
        if (!field) return;

        btn.addEventListener('click', function () {
            var show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            var label = btn.querySelector('span');
            if (label) label.textContent = show ? 'أخف' : 'أظهر';
        });
    });

    /**
     * تبديل إشعار.
     *
     * كان السابق يقرأ حالة المربع **بعد** أن يبدلها المتصفح ثم يعكسها
     * يدويا (`if(!input_val) input_val = 1`) — منطق يصح صدفة، ويصير
     * معكوسا متى تغير ترتيب الحدث. وهنا ترسل الحالة كما هي.
     */
    var URL = <?php echo json_encode(site_url('admin/notification_settings/notification_enable_diable')); ?>;

    Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-notif]'), function (box) {
        box.addEventListener('change', function () {
            var was  = !box.checked;
            var body = new URLSearchParams();
            body.set('id', box.getAttribute('data-tqa-notif'));
            body.set('user_type', box.getAttribute('data-tqa-notif-role'));
            body.set('notification_type', box.getAttribute('data-tqa-notif-kind'));
            body.set('input_val', box.checked ? '1' : '0');
            if (CSRF && CSRF.name) body.set(CSRF.name, CSRF.hash);

            box.disabled = true;

            fetch(URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function (r) {
                if (!r.ok) throw new Error(r.status);
                if (window.TQA) TQA.ok(box.checked ? 'فعل الإشعار' : 'أوقف الإشعار');
            }).catch(function () {
                box.checked = was;
                if (window.TQA) TQA.error('لم يحفظ التغيير. حدث الصفحة وأعد المحاولة.');
            }).then(function () {
                box.disabled = false;
            });
        });
    });
})();
</script>
