<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إعدادات البريد الصادر.
 *
 * منه ترسل المنصة كل ما لا يقرأ داخلها: استعادة كلمة المرور، وقرار طلب
 * المعلم، وطلب ربط ولي الأمر، وقرار طلب السحب، والتقرير الأسبوعي.
 *
 * وكلمة المرور **لا تعاد إلى الصفحة أبدا**: تعرض نائبة، ويترك الحقل
 * فارغا للإبقاء عليها. فحقل يحمل السر يتسرب في مصدر الصفحة وفي أي لقطة
 * شاشة، والفائدة منه صفر — لا أحد يقرأ كلمة مروره من نموذج.
 *
 * وأضيف إلى الشاشة ثلاثة كانت ناقصة:
 *
 * ١ — **توكن CSRF في النماذج الثلاثة.** الحماية مفعلة، والحقن العام
 *     يعمل بجافاسكربت وحده — فتعثر ملف يجعل كل حفظ يرد 403.
 * ٢ — **قوالب المزودين.** ضبط SMTP ثلاثة أرقام يخطئ فيها من لا يعرفها،
 *     وأشهر خطأ هو منفذ ٤٦٥ مع TLS (أو العكس) فيفشل الاتصال بلا سبب
 *     مفهوم. فتختار المزود وتملأ الثلاثة وحدها.
 * ٣ — **شرح كلمة مرور التطبيق.** Gmail **لا يقبل كلمة مرور الحساب**
 *     في SMTP منذ ٢٠٢٢. ومن يكتبها يرى «Username and Password not
 *     accepted» ويظن الإعداد معطوبا. والمطلوب «كلمة مرور تطبيق» من
 *     ست عشرة خانة، ولها شرطها: تفعيل التحقق بخطوتين أولا.
 */
$M   = $mail;
$has = !empty($M['smtp_pass']);
?>

<?php tqa_head(
    t('البريد الصادر'),
    t('منه ترسل استعادة كلمة المرور وقرارات الطلبات وتنبيهات أولياء الأمور.'),
    'mail',
    $configured
        ? t('<span class="tqa-badge tqa-badge--ok">مضبوط</span>')
        : t('<span class="tqa-badge tqa-badge--danger">غير مضبوط</span>')
); ?>

<?php if (!$configured): ?>
    <div class="tqa-note tqa-note--warn" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo t('البريد غير مضبوط، والمنصة تعمل بدونه.'); ?></strong>
            <?php echo t('لا يقع خطأ ولا تتعطل شاشة — الرسائل ببساطة لا ترسل، والإشعار داخل المنصة يكتب كما هو. ولكن ما يعتمد على البريد وحده يتوقف: من ينسى كلمة مروره لا يستطيع الرجوع إلى حسابه، ومن يتقدم معلما لا يعلم بقرار طلبه، وولي الأمر لا يصله تقرير ولا رد على طلب ربط.'); ?>
        </span>
    </div>
<?php else: ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('check', 18); ?></span>
        <span><strong><?php echo t('البيانات كاملة.'); ?></strong>
            <?php echo t('ويبقى الضبط ادعاء حتى تنجح رسالة فحص فعلية — الزر في البطاقة المجاورة.'); ?></span>
    </div>
<?php endif; ?>

<?php
/* ---------------------------------------------------------------------------
   وصول الرسائل — لا إرسالها.

   «أرسلت الرسالة» و«وصلت الرسالة إلى الوارد» جوابان عن سؤالين، وزر الفحص
   يجيب عن الأول وحده: خادم قبلها، وهذا منتهى علمه. أما الثاني فيقرره
   المستلم بعد قراءة ثلاثة سجلات في نطاقك، ولا يخبرك بقراره — يضعها في
   المهملات بصمت، فتظن الإعداد سليما لأن الفحص قال «نجح».

   فهذه البطاقة تقرأ تلك السجلات وتقارنها بالضبط المحفوظ، وتقول ما سيقرره
   المستلم قبل أن يقرره.
   --------------------------------------------------------------------------- */
$tq_meta = array(
    'ok'      => array('ok',     'check', t('سليم')),
    'warn'    => array('warn',   'alert', t('يضعف الوصول')),
    'fail'    => array('danger', 'x',     t('يمنع الوصول')),
    'unknown' => array('muted',  'help',  t('تعذر الفحص')),
);

$tq_fail = 0;
$tq_warn = 0;
foreach ((array) $health as $tq_h) {
    if ($tq_h['state'] === 'fail')      $tq_fail++;
    elseif ($tq_h['state'] === 'warn')  $tq_warn++;
}
?>

<div class="tqa-card" style="margin-block-end:var(--tq-space-l)">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox <?php echo $tq_fail ? 'tqa-peach' : 'tqa-mint'; ?>" aria-hidden="true">
            <?php echo tq_icon('shield', 20); ?>
        </span>
        <h2><?php echo t('وصول الرسائل إلى الوارد'); ?></h2>
        <span class="tqa-badge tqa-badge--<?php
            echo $tq_fail ? 'danger' : ($tq_warn ? 'warn' : 'ok'); ?>" style="margin-inline-start:auto">
            <?php
            if ($tq_fail)      echo t('يمنع الوصول: ') . $tq_fail;
            elseif ($tq_warn)  echo t('يستحسن إصلاحه: ') . $tq_warn;
            else               echo t('كل الفحوص سليمة');
            ?>
        </span>
    </div>
    <div>
        <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
            <?php echo t('رسالة'); ?> <strong><?php echo t('ترسل'); ?></strong> <?php echo t('ورسالة'); ?> <strong><?php echo t('تصل إلى الوارد'); ?></strong> <?php echo t('ليستا شيئا واحدا. زر الفحص يثبت الأولى وحدها — أن خادما قبل الرسالة. أما الثانية فيقررها بريد المستلم بعد قراءة سجلات نطاقك، ولا يخبرك بقراره: يضع الرسالة في المهملات بصمت. وهذه الفحوص تقرأ تلك السجلات من DNS الآن، فتعرف القرار قبل أن يقع.'); ?>
        </p>

        <ul class="tqa-checks">
            <?php foreach ((array) $health as $tq_h):
                list($tq_badge, $tq_icon, $tq_word) = $tq_meta[$tq_h['state']]; ?>
                <li class="tqa-check tqa-check--<?php echo $tq_h['state']; ?>">
                    <div class="tqa-check__top">
                        <span class="tqa-check__mark" aria-hidden="true"><?php echo tq_icon($tq_icon, 15); ?></span>
                        <strong><?php echo html_escape($tq_h['label']); ?></strong>
                        <span class="tqa-badge tqa-badge--<?php echo $tq_badge; ?>"><?php echo $tq_word; ?></span>
                    </div>
                    <p class="tqa-check__says"><?php echo html_escape($tq_h['says']); ?></p>
                    <?php if ($tq_h['fix'] !== ''): ?>
                        <p class="tqa-check__fix"><strong><?php echo t('ما العمل:'); ?></strong> <?php echo html_escape($tq_h['fix']); ?></p>
                    <?php endif; ?>
                    <?php if ($tq_h['record'] !== ''): ?>
                        <pre class="tqa-debug" dir="ltr"><?php echo html_escape($tq_h['record']); ?></pre>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="tqa-hint" style="margin-block-start:var(--tq-space-l)">
            <?php echo t('سجلات DNS تنشر عند من يدير نطاق'); ?> <span class="tq-ltr" dir="ltr">taqdaredu.com</span><?php echo t('، وتأخذ من دقائق إلى ساعات حتى تنتشر. أعد تحميل هذه الشاشة بعدها لترى الفحص وقد تغير.'); ?>
        </p>
    </div>
</div>

<div class="tqa-cols">
    <div class="tqa-stack">
        <div class="tqa-card">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('send', 20); ?></span>
                <h2><?php echo t('بيانات الخادم'); ?></h2>
            </div>
            <div>

                <?php /* القوالب تملأ الحقول الثلاثة ولا ترسل شيئا: الاختيار
                         تسهيل لا حفظ، والحفظ زر مستقل. */ ?>
                <div class="tqa-field">
                    <label class="tqa-field__label" for="m_preset"><?php echo t('المزود'); ?></label>
                    <select class="tqa-select" id="m_preset">
                        <option value=""><?php echo t('اختر لتملأ الخادم والمنفذ والتعمية…'); ?></option>
                        <option value="smtp.gmail.com|587|tls"><?php echo t('Gmail — بكلمة مرور تطبيق'); ?></option>
                        <option value="smtp.hostinger.com|465|ssl"><?php echo t('Hostinger — بريد النطاق'); ?></option>
                        <option value="smtp.office365.com|587|tls">Outlook · Microsoft 365</option>
                        <option value="email-smtp.eu-central-1.amazonaws.com|587|tls"><?php echo t('Amazon SES (فرانكفورت)'); ?></option>
                        <option value="smtp.mailgun.org|587|tls">Mailgun</option>
                    </select>
                    <small class="tqa-field__hint"><?php echo t('يملأ ثلاثة حقول فقط — والمستخدم وكلمة المرور تكتبان بيدك.'); ?></small>
                </div>

                <form method="post" action="<?php echo site_url('taqdar_admin/mail_save'); ?>">
                    <?php echo tq_csrf(); ?>
                    <div class="tqa-fieldgrid">
                        <div class="tqa-field">
                            <label class="tqa-field__label" for="m_host"><?php echo t('خادم البريد'); ?> <span class="tqa-field__req" aria-hidden="true">*</span></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="m_host" name="smtp_host"
                                   value="<?php echo html_escape($M['smtp_host']); ?>" required
                                   autocapitalize="off" spellcheck="false">
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="m_port"><?php echo t('المنفذ'); ?> <span class="tqa-field__req" aria-hidden="true">*</span></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="number" id="m_port" name="smtp_port"
                                   value="<?php echo html_escape($M['smtp_port']); ?>" required>
                            <small class="tqa-field__hint"><?php echo t('465 مع SSL · 587 مع TLS'); ?></small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="m_crypto"><?php echo t('التعمية'); ?></label>
                            <select class="tqa-select" id="m_crypto" name="smtp_crypto">
                                <?php foreach (array('tls' => 'TLS', 'ssl' => 'SSL', '' => t('بلا تعمية')) as $k => $lbl): ?>
                                    <option value="<?php echo $k; ?>" <?php echo ((string) $M['smtp_crypto'] === (string) $k) ? 'selected' : ''; ?>>
                                        <?php echo $lbl; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="m_user"><?php echo t('اسم المستخدم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="m_user" name="smtp_user"
                                   value="<?php echo html_escape($M['smtp_user']); ?>" required
                                   autocapitalize="off" spellcheck="false"
                                   placeholder="info@taqdaredu.com">
                            <small class="tqa-field__hint"><?php echo t('عنوان الصندوق كاملا، لا الجزء الذي قبل @.'); ?></small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="m_pass"><?php echo t('كلمة المرور'); ?> <?php echo $has ? '' : '<span class="tqa-field__req" aria-hidden="true">*</span>'; ?></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="password" id="m_pass" name="smtp_pass"
                                   autocomplete="new-password"
                                   placeholder="<?php echo $has ? t('محفوظة — اتركه فارغا للإبقاء عليها') : t('كلمة مرور الصندوق أو كلمة مرور التطبيق'); ?>">
                            <small class="tqa-field__hint">
                                <?php echo $has
                                    ? t('محفوظة ولا تعرض هنا أبدا. اكتب قيمة جديدة لتبديلها فقط.')
                                    : t('لا تعرض بعد الحفظ أبدا.'); ?>
                            </small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="m_from"><?php echo t('المرسل الظاهر'); ?> <span class="tqa-field__req" aria-hidden="true">*</span></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="email" id="m_from" name="smtp_from_email"
                                   value="<?php echo html_escape($M['smtp_from_email']); ?>" required>
                            <small class="tqa-field__hint">
                                <strong><?php echo t('يجب أن يكون من نطاق اسم المستخدم نفسه.'); ?></strong>
                                <?php echo t('نطاق مختلف يفشل فحص DMARC عند المستلم فتذهب الرسائل إلى المهملات — انظر «وصول الرسائل» أعلى الشاشة.'); ?>
                            </small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="m_sys"><?php echo t('بريد النظام'); ?></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="email" id="m_sys" name="system_email"
                                   value="<?php echo html_escape($M['system_email']); ?>">
                            <small class="tqa-field__hint"><?php echo t('إليه تصل رسائل التواصل وتنبيهات النظام.'); ?></small>
                        </div>
                    </div>

                    <div class="tqa-actions"><button type="submit" class="tqa-btn tqa-btn--primary"><?php echo tq_icon('check', 16); ?> <?php echo t('احفظ الإعدادات'); ?></button></div>
                </form>
            </div>
        </div>

        <?php /* شرح Gmail مطوي: يفتحه من يحتاجه ولا يزاحم من ضبط بريد نطاقه. */ ?>
        <details class="tqa-card">
            <summary class="tqa-row">
                <span class="tqa-iconbox tqa-sand" aria-hidden="true" style="inline-size:34px;block-size:34px"><?php echo tq_icon('help', 17); ?></span>
                <strong style="font:var(--tq-type-bodyStrong);color:var(--tq-navy)">
                <?php echo t('كيف تحصل على كلمة مرور تطبيق من Gmail؟'); ?></strong>
            </summary>
            <div style="margin-block-start:var(--tq-space-l);border-block-start:1px solid var(--tq-line);padding-block-start:var(--tq-space-l)">
                <p>
                    Gmail <strong><?php echo t('لا يقبل كلمة مرور حسابك'); ?></strong> <?php echo t('في SMTP منذ عام ٢٠٢٢، ومن يكتبها هنا يرى الخادم يرد'); ?>
                    <span class="tq-ltr" dir="ltr">Username and Password not accepted</span>
                    <?php echo t('فيظن أن الإعداد معطوب وهو صحيح. والمطلوب «كلمة مرور تطبيق»:'); ?>
                </p>
                <ol style="padding-inline-start:20px;line-height:2">
                    <li><?php echo t('فعل'); ?> <strong><?php echo t('التحقق بخطوتين'); ?></strong> <?php echo t('على الحساب — بدونه لا يظهر الخيار أصلا.'); ?></li>
                    <li><?php echo t('افتح'); ?>
                        <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">
                            <?php echo t('صفحة كلمات مرور التطبيقات'); ?></a>.</li>
                    <li><?php echo t('أنشئ كلمة باسم «تقدر»، وانسخ الخانات الست عشرة كما هي.'); ?></li>
                    <li><?php echo t('ضعها في حقل كلمة المرور أعلاه، واسم المستخدم هو عنوان Gmail كاملا.'); ?></li>
                </ol>
                <p class="tqa-hint">
                    <?php echo t('وحد Gmail المجاني نحو ٥٠٠ رسالة في اليوم. فإن تجاوزت المنصة ذلك فالوجهة خدمة إرسال (SES أو Mailgun) لا صندوق بريد.'); ?>
                </p>
                <p class="tqa-hint">
                    <?php echo t('والمرسل الظاهر لا بد أن يكون عنوان Gmail نفسه أو عنوانا موثقا فيه، وإلا بدلته Google بعنوان الحساب أو رفضت الرسالة.'); ?>
                </p>
            </div>
        </details>
    </div>

    <aside class="tqa-stack">
        <div class="tqa-card">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('check-badge', 20); ?></span>
                <h2><?php echo t('رسالة فحص'); ?></h2>
            </div>
            <div>
                <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
                    <?php echo t('ترسل رسالة حقيقية بالإعدادات المحفوظة وبالمسار الذي تستعمله المنصة نفسها. وإن فشلت يعرض'); ?> <strong><?php echo t('سبب الفشل كما قاله الخادم'); ?></strong> <?php echo t('لا رسالة عامة — فالسبب هو ما يصلح، لا الفشل.'); ?>
                </p>

                <form method="post" action="<?php echo site_url('taqdar_admin/mail_test'); ?>">
                    <?php echo tq_csrf(); ?>
                    <div class="tqa-field">
                        <label class="tqa-field__label" for="m_to"><?php echo t('أرسل إلى'); ?></label>
                        <input class="tqa-input tqa-input--ltr" dir="ltr" type="email" id="m_to" name="to"
                               value="<?php echo html_escape($M['smtp_from_email']); ?>" required>
                    </div>
                    <button type="submit" class="tqa-btn tqa-btn--mastery" <?php echo $configured ? '' : 'disabled'; ?>>
                        <?php echo t('أرسل رسالة فحص'); ?>
                    </button>
                    <?php if (!$configured): ?>
                        <small class="tqa-field__hint" style="color:var(--tq-amber)"><?php echo t('احفظ البيانات كاملة أولا.'); ?></small>
                    <?php endif; ?>
                </form>

                <?php if (!empty($debug)): ?>
                    <div style="margin-block-start:var(--tq-space-l)">
                        <strong class="tqa-hint"><?php echo t('ما قاله الخادم:'); ?></strong>
                        <pre class="tqa-debug" dir="ltr"><?php echo html_escape($debug); ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tqa-card">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('alert', 20); ?></span>
                <h2><?php echo t('ما يعتمد على البريد'); ?></h2>
            </div>
            <div>
                <?php
                /* الجدول يذكر ما يقع فعلا حين لا يضبط البريد — لا «معطلة»
                   وحدها: من يقرأ «معطلة» لا يعرف أيتوقف التسجيل أم لا. */
                $tq_deps = array(
                    array(t('استعادة كلمة المرور'),     t('من ينسى كلمته لا يستطيع الرجوع إلى حسابه'), true),
                    array(t('تأكيد البريد عند التسجيل'), t('الرمز لا يصل، فلا يفتح الحساب'),            true),
                    array(t('قرار طلب المعلم'),         t('يعتمد أو يرفض ولا يعلم صاحبه'),              true),
                    array(t('طلب ربط ولي الأمر'),       t('يكتب في الجرس ولا يصل بريد'),                false),
                    array(t('نتيجة تحديد المستوى'),     t('لا تخرج من شاشة الطالب، ولا يعلم بها وليه'),  false),
                    array(t('قرار طلب السحب'),          t('يكتب في الجرس ولا يصل بريد'),                false),
                    array(t('التقرير الأسبوعي'),        t('يكتب في الجرس ولا يصل بريد'),                false),
                    array(t('النشرة البريدية'),         t('لا ترسل إطلاقا'),                            true),
                );
                ?>
                <div class="tqa-table__wrap">
                    <table class="tqa-table" style="margin-block-end:var(--tq-space-l)">
                        <tbody>
                        <?php foreach ($tq_deps as list($tq_what, $tq_else, $tq_hard)): ?>
                            <tr>
                                <td style="padding-inline:0">
                                    <strong><?php echo $tq_what; ?></strong><br>
                                    <?php if (!$configured): ?>
                                        <span class="tqa-hint"><?php echo $tq_else; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding-inline:0;text-align:end">
                                    <span class="tqa-badge tqa-badge--<?php
                                        echo $configured ? 'ok' : ($tq_hard ? 'danger' : 'warn'); ?>">
                                        <?php echo $configured ? t('تعمل') : ($tq_hard ? t('متوقفة') : t('داخل المنصة فقط')); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="tqa-hint">
                    <?php echo t('التنبيهات البريدية للأحداث (نتيجة امتحان · رسوب في محطة · انقطاع) مفتاح مستقل: تكتب في الجرس دائما، وترسل بريدا إن فعل هذا المفتاح. وخارجه'); ?> <strong><?php echo t('نتيجة تحديد المستوى'); ?></strong><?php echo t(': ترسل ما دام البريد مضبوطا — لأن على هذه النتيجة يقرر ولي الأمر أي باقة يشتري، ورسالة تصله بعد أن يدفع لا تفيده.'); ?>
                </p>

                <form method="post" action="<?php echo site_url('taqdar_admin/mail_toggle_events'); ?>"
                      data-tqa-confirm-title="<?php echo $events_email ? t('إيقاف التنبيهات البريدية') : t('تفعيل التنبيهات البريدية'); ?>"
                      data-tqa-confirm="<?php echo $events_email
                          ? t('تبقى الإشعارات داخل المنصة كما هي، ويتوقف إرسالها بالبريد.')
                          : t('سيرسل بريد عند كل نتيجة امتحان ورسوب محطة وانقطاع. راجع حد الإرسال عند مزودك.'); ?>"
                      data-tqa-confirm-ok="<?php echo $events_email ? t('أوقف') : t('فعل'); ?>">
                    <?php echo tq_csrf(); ?>
                    <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" <?php echo $configured ? '' : 'disabled'; ?>>
                        <?php echo $events_email ? t('أوقف التنبيهات البريدية') : t('فعل التنبيهات البريدية'); ?>
                    </button>
                    <span class="tqa-badge tqa-badge--<?php echo $events_email ? 'ok' : 'muted'; ?>">
                        <?php echo $events_email ? t('مفعلة الآن') : t('مطفأة الآن'); ?>
                    </span>
                </form>
            </div>
        </div>
    </aside>
</div>

<script>
/* قالب المزود يملأ الحقول الثلاثة ولا يحفظ: الاختيار تسهيل، والحفظ قرار. */
(function () {
    var preset = document.getElementById('m_preset');
    if (!preset) return;

    preset.addEventListener('change', function () {
        if (!preset.value) return;
        var p = preset.value.split('|');
        document.getElementById('m_host').value   = p[0];
        document.getElementById('m_port').value   = p[1];
        document.getElementById('m_crypto').value = p[2];
        if (window.TQA) TQA.info('ملئت بيانات الخادم. اكتب اسم المستخدم وكلمة المرور ثم احفظ.');
    });
})();
</script>
