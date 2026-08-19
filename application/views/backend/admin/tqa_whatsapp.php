<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إشعارات واتساب.
 *
 * الشاشة أخت «البريد الصادر» في بنيتها: حال أعلى، ثم بيانات الاتصال،
 * ثم رسالة فحص، ثم ما يعتمد على القناة. والزائد فيها شيئان يفرضهما
 * واتساب نفسه ولا يفرضهما البريد:
 *
 * ١ — **القوالب.** لا يخرج من واتساب نص تكتبه إلا لمن راسلك في آخر
 *     أربع وعشرين ساعة. وكل ما ترسله المنصة يبدأ منها، فلا يخرج إلا
 *     بقالب اعتمدته ميتا. ومن يجهل هذا يضبط الرمز والرقم ثم يرى كل
 *     رسالة ترد `131047` ولا يفهم لماذا.
 *
 * ٢ — **الدليل.** بيانات واتساب لا تؤخذ من مكان واحد: الرمز من مستخدم
 *     نظام في مدير الأعمال، ومعرف الرقم من شاشة إعداد التطبيق، ومعرف
 *     حساب الأعمال من شاشة أخرى. فالدليل هنا خطوة خطوة، وفيه نص
 *     القالبين جاهزا للنسخ — لأن «اعتمد قالبا» أمر لا يقال لمن لم يفعله
 *     من قبل.
 *
 * والرمز **لا يعاد إلى الصفحة أبدا**: يعرض أنه محفوظ، ويترك الحقل
 * فارغا للإبقاء عليه — كما في كلمة مرور البريد.
 */
$W  = $wa;
$C  = $cfg;
$has_token = ($C['token'] !== '');

$tq_meta = array(
    'ok'      => array('ok',     'check', 'سليم'),
    'warn'    => array('warn',   'alert', 'يضعف الوصول'),
    'fail'    => array('danger', 'x',     'يمنع الوصول'),
    'unknown' => array('muted',  'help',  'تعذر الفحص'),
);

$tq_fail = 0;
$tq_warn = 0;
foreach ((array) $health as $tq_h) {
    if ($tq_h['state'] === 'fail')     $tq_fail++;
    elseif ($tq_h['state'] === 'warn') $tq_warn++;
}
?>

<?php tqa_head(
    'إشعارات واتساب',
    'منه تخرج إشعارات الدفع ورموز التحقق — إن ضبط. وبلاه لا يتعطل شيء.',
    'whatsapp',
    $configured
        ? '<span class="tqa-badge tqa-badge--ok">مفعل</span>'
        : '<span class="tqa-badge tqa-badge--muted">غير مضبوط</span>'
); ?>

<?php if (!$configured): ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span>
            <strong>واتساب غير مضبوط، والمنصة تعمل بدونه تماما.</strong>
            لا خطأ يقع ولا شاشة تتعطل ولا دفعة تتوقف: الرسائل ببساطة لا ترسل،
            والإشعار داخل المنصة يكتب كما هو، والبريد يخرج كما كان. وكل ما يضيفه
            الضبط هو <strong>قناة ثانية</strong> لإشعارات المال ورموز التحقق —
            والدليل أسفل الشاشة يمشي بك خطوة خطوة.
        </span>
    </div>
<?php else: ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('check', 18); ?></span>
        <span><strong>البيانات محفوظة والقناة مفعلة.</strong>
            ويبقى ذلك ادعاء حتى تنجح رسالة فحص فعلية — الزر في البطاقة المجاورة،
            والسجل أسفل الشاشة يقول ما جرى بعده.</span>
    </div>
<?php endif; ?>

<?php
/* ---------------------------------------------------------------------------
   حال الاتصال — لا حال الحفظ.

   «حفظت الرمز» و«تقبل ميتا رسائلي» جوابان عن سؤالين. وهذه البطاقة تسأل
   ميتا الآن: هل الرمز صالح؟ وهل الرقم مسجل؟ وهل القالبان معتمدان بلغتهما
   وبعدد بدائلهما؟ فيعرف الجواب قبل أن يعرفه طالب دفع وينتظر تأكيدا.
   --------------------------------------------------------------------------- */
?>
<div class="tqa-card" style="margin-block-end:var(--tq-space-l)">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox <?php echo $tq_fail ? 'tqa-peach' : 'tqa-mint'; ?>" aria-hidden="true">
            <?php echo tq_icon('shield', 20); ?>
        </span>
        <h2>حال الاتصال بواتساب</h2>
        <span class="tqa-badge tqa-badge--<?php
            echo $tq_fail ? 'danger' : ($tq_warn ? 'warn' : 'ok'); ?>" style="margin-inline-start:auto">
            <?php
            if ($tq_fail)     echo 'يمنع الوصول: ' . $tq_fail;
            elseif ($tq_warn) echo 'يستحسن إصلاحه: ' . $tq_warn;
            else              echo 'كل الفحوص سليمة';
            ?>
        </span>
    </div>
    <div>
        <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
            هذه الفحوص تسأل خوادم ميتا <strong>الآن</strong> عن الرمز والرقم والقوالب،
            فتعرف ما ستقوله عند أول رسالة حقيقية قبل أن تقوله. وبعضها يحتاج
            «معرف حساب الأعمال» ليعمل — وهو الحقل الذي يظنه الناس اختياريا.
        </p>

        <ul class="tqa-checks">
            <?php foreach ((array) $health as $tq_h):
                list($tq_badge, $tq_ic, $tq_word) = $tq_meta[$tq_h['state']]; ?>
                <li class="tqa-check tqa-check--<?php echo $tq_h['state']; ?>">
                    <div class="tqa-check__top">
                        <span class="tqa-check__mark" aria-hidden="true"><?php echo tq_icon($tq_ic, 15); ?></span>
                        <strong><?php echo html_escape($tq_h['label']); ?></strong>
                        <span class="tqa-badge tqa-badge--<?php echo $tq_badge; ?>"><?php echo $tq_word; ?></span>
                    </div>
                    <p class="tqa-check__says"><?php echo html_escape($tq_h['says']); ?></p>
                    <?php if ($tq_h['fix'] !== ''): ?>
                        <p class="tqa-check__fix"><strong>ما العمل:</strong> <?php echo html_escape($tq_h['fix']); ?></p>
                    <?php endif; ?>
                    <?php if ($tq_h['record'] !== ''): ?>
                        <pre class="tqa-debug" dir="ltr"><?php echo html_escape($tq_h['record']); ?></pre>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="tqa-cols">
    <div class="tqa-stack">

        <?php /* ── بيانات الاتصال ─────────────────────────────────── */ ?>
        <div class="tqa-card">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('whatsapp', 20); ?></span>
                <h2>بيانات الاتصال</h2>
            </div>
            <div>
                <form method="post" action="<?php echo site_url('taqdar_admin/whatsapp_save'); ?>">
                    <?php echo tq_csrf(); ?>

                    <div class="tqa-field">
                        <label class="tqa-check" for="w_on" style="display:flex;gap:10px;align-items:flex-start">
                            <input type="checkbox" id="w_on" name="tq_wa_enabled" value="1"
                                   <?php echo $C['enabled'] ? 'checked' : ''; ?>>
                            <span>
                                <strong>فعل قناة واتساب</strong>
                                <small class="tqa-field__hint" style="display:block">
                                    وهو مفتاح القدرة. وبدونه لا تخرج رسالة ولا يظهر خيار «الرمز
                                    بواتساب» لمن يسجل — ويبقى كل شيء آخر كما هو.
                                </small>
                            </span>
                        </label>
                    </div>

                    <div class="tqa-fieldgrid">
                        <div class="tqa-field" style="grid-column:1/-1">
                            <label class="tqa-field__label" for="w_token">
                                رمز الوصول
                                <?php echo $has_token ? '' : '<span class="tqa-field__req" aria-hidden="true">*</span>'; ?>
                            </label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="password" id="w_token"
                                   name="tq_wa_token" autocomplete="new-password"
                                   placeholder="<?php echo $has_token
                                       ? 'محفوظ — اتركه فارغا للإبقاء عليه'
                                       : 'EAAG… رمز مستخدم النظام'; ?>">
                            <small class="tqa-field__hint">
                                <?php echo $has_token
                                    ? 'محفوظ ولا يعرض هنا أبدا. اكتب قيمة جديدة لتبديله فقط.'
                                    : 'من مدير الأعمال ← مستخدمو النظام ← «إنشاء رمز» — انظر الخطوة ٤ في الدليل.'; ?>
                                <strong>ولا تستعمل الرمز المؤقت</strong> الظاهر في شاشة الإعداد:
                                عمره أربع وعشرون ساعة، ثم تتوقف كل الرسائل بلا سبب ظاهر.
                            </small>
                            <?php if ($has_token): ?>
                                <label class="tqa-check" style="margin-block-start:8px;display:flex;gap:8px;align-items:center">
                                    <input type="checkbox" name="tq_wa_token_clear" value="1">
                                    <span class="tqa-hint">امسح الرمز المحفوظ</span>
                                </label>
                            <?php endif; ?>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_pid">
                                معرف رقم المرسل <span class="tqa-field__req" aria-hidden="true">*</span>
                            </label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_pid"
                                   name="tq_wa_phone_id" inputmode="numeric" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_phone_id']); ?>"
                                   placeholder="123456789012345">
                            <small class="tqa-field__hint">
                                <span class="tq-ltr" dir="ltr">Phone number ID</span> —
                                رقم طويل لا رقم الجوال نفسه. الخطوة ٥.
                            </small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_waba">معرف حساب الأعمال</label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_waba"
                                   name="tq_wa_waba_id" inputmode="numeric" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_waba_id']); ?>"
                                   placeholder="123456789012345">
                            <small class="tqa-field__hint">
                                <span class="tq-ltr" dir="ltr">WABA ID</span> — لا يرسل به شيء،
                                <strong>وبه وحده تفحص القوالب</strong> في البطاقة أعلاه. الخطوة ٥.
                            </small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_ver">نسخة الواجهة</label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_ver"
                                   name="tq_wa_api_ver" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_api_ver']); ?>"
                                   placeholder="<?php echo Taqdar_wa_model::DEFAULT_VER; ?>">
                            <small class="tqa-field__hint">اتركها كما هي ما لم تطلب ميتا غيرها.</small>
                        </div>
                    </div>

                    <?php /* ── القوالب ────────────────────────────────
                            القسم الذي يخطئ فيه الجميع. الاسم واللغة وعدد
                            البدائل ثلاثتها تدخل في جسم الطلب، واختلاف
                            واحد منها يرد الرسالة برمز لا يفهم. */ ?>
                    <h3 class="tqa-formsec" style="margin-block:var(--tq-space-xl) var(--tq-space-m)">
                        القوالب المعتمدة
                    </h3>
                    <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
                        <strong>واتساب لا يوصل نصا تكتبه.</strong> النص الحر لا يخرج إلا لمن
                        راسل رقمك في آخر أربع وعشرين ساعة، وكل ما ترسله المنصة يبدأ منها —
                        رمز تحقق، وخبر دفعة. فلا يخرج شيء منه إلا بقالب صادقت عليه ميتا.
                        والاسم واللغة وعدد البدائل تدخل في كل طلب: اختلاف واحد منها يرد
                        الرسالة ولا يصل شيء. <strong>الخطوة ٦ في الدليل تعطيك نص القالبين
                        جاهزا للنسخ.</strong>
                    </p>

                    <div class="tqa-fieldgrid">
                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_tn">اسم قالب الإشعارات</label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_tn"
                                   name="tq_wa_tpl_notice" spellcheck="false" autocapitalize="off"
                                   value="<?php echo html_escape($W['tq_wa_tpl_notice']); ?>"
                                   placeholder="taqdar_notice">
                            <small class="tqa-field__hint">إشعارات الدفع كلها تخرج به.</small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_tnl">لغته</label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_tnl"
                                   name="tq_wa_tpl_notice_lang" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_tpl_notice_lang']); ?>"
                                   placeholder="ar">
                            <small class="tqa-field__hint">كما اعتمد بالحرف: <span class="tq-ltr" dir="ltr">ar</span>
                                غير <span class="tq-ltr" dir="ltr">ar_SA</span>.</small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_tnp">عدد بدائله</label>
                            <select class="tqa-select" id="w_tnp" name="tq_wa_tpl_notice_params">
                                <option value="2" <?php echo ((int) ($W['tq_wa_tpl_notice_params'] ?: 2)) === 2 ? 'selected' : ''; ?>>
                                    اثنان — العنوان ثم الجسم (المفضل)
                                </option>
                                <option value="1" <?php echo ((int) $W['tq_wa_tpl_notice_params']) === 1 ? 'selected' : ''; ?>>
                                    واحد — العنوان والجسم في بديل واحد
                                </option>
                            </select>
                            <small class="tqa-field__hint">
                                عد <span class="tq-ltr" dir="ltr">{{1}}</span> في نص قالبك، ولا تخمن:
                                الاختلاف يرد <span class="tq-ltr" dir="ltr">132000</span>.
                            </small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_to">اسم قالب رمز التحقق</label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_to"
                                   name="tq_wa_tpl_otp" spellcheck="false" autocapitalize="off"
                                   value="<?php echo html_escape($W['tq_wa_tpl_otp']); ?>"
                                   placeholder="taqdar_otp">
                            <small class="tqa-field__hint">من صنف
                                <span class="tq-ltr" dir="ltr">Authentication</span> لا
                                <span class="tq-ltr" dir="ltr">Utility</span>.</small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_tol">لغته</label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_tol"
                                   name="tq_wa_tpl_otp_lang" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_tpl_otp_lang']); ?>"
                                   placeholder="ar">
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label">زر «نسخ الرمز»</label>
                            <label class="tqa-check" style="display:flex;gap:8px;align-items:flex-start;padding-block-start:6px">
                                <input type="checkbox" name="tq_wa_tpl_otp_button" value="1"
                                       <?php echo $C['tpl_otp_button'] ? 'checked' : ''; ?>>
                                <span class="tqa-hint">
                                    في القالب زر نسخ الرمز — وهو الحال الافتراضية لقوالب
                                    <span class="tq-ltr" dir="ltr">Authentication</span>.
                                    وجوده يضيف بديلا ثانيا في الطلب، وخطؤه يرد
                                    <span class="tq-ltr" dir="ltr">132000</span>.
                                </span>
                            </label>
                        </div>
                    </div>

                    <?php /* ── ماذا يخرج من هذه القناة ───────────────── */ ?>
                    <h3 class="tqa-formsec" style="margin-block:var(--tq-space-xl) var(--tq-space-m)">
                        ماذا يخرج من هذه القناة
                    </h3>

                    <div class="tqa-field">
                        <label class="tqa-check" style="display:flex;gap:10px;align-items:flex-start">
                            <input type="checkbox" name="tq_wa_notify_payments" value="1"
                                   <?php echo $C['notify_payments'] ? 'checked' : ''; ?>>
                            <span>
                                <strong>إشعارات الدفع</strong>
                                <small class="tqa-field__hint" style="display:block">
                                    صدور الفاتورة · نجاح الدفع بالبطاقة · تفعيل الاشتراك بعد
                                    الحوالة · تحويل مبلغ السحب · رفضه. وهذه وحدها — نتيجة
                                    امتحان أو حصة ألغيت أو تنبيه انقطاع لا يخرج شيء منها
                                    بواتساب، لأن قناة تزعج يسدها المستلم فتضيع معها إشعارات
                                    المال نفسها.
                                </small>
                            </span>
                        </label>
                    </div>

                    <div class="tqa-field">
                        <label class="tqa-check" style="display:flex;gap:10px;align-items:flex-start">
                            <input type="checkbox" name="tq_wa_otp_allowed" value="1"
                                   <?php echo $C['otp_allowed'] ? 'checked' : ''; ?>>
                            <span>
                                <strong>رمز تأكيد الحساب</strong>
                                <small class="tqa-field__hint" style="display:block">
                                    يظهر للمعلم ولولي الأمر خيار «أرسل الرمز بواتساب» عند
                                    التسجيل — فهما وحدهما من يكتب جواله. والطالب لا يسأل عن
                                    جواله أصلا، فرمزه بالبريد دائما.
                                </small>
                            </span>
                        </label>
                    </div>

                    <div class="tqa-actions">
                        <button type="submit" class="tqa-btn tqa-btn--primary">
                            <?php echo tq_icon('check', 16); ?> احفظ الإعدادات
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php /* ── الدليل ─────────────────────────────────────────────
                مطوي: يفتحه من يضبط أول مرة، ولا يزاحم من ضبط وانتهى.
                ومفتوح تلقائيا ما دام غير مضبوط — فمن يفتح الشاشة أول
                مرة يجد الطريق أمامه لا خلف زر. */ ?>
        <details class="tqa-card" <?php echo $configured ? '' : 'open'; ?>>
            <summary class="tqa-row">
                <span class="tqa-iconbox tqa-sand" aria-hidden="true" style="inline-size:34px;block-size:34px">
                    <?php echo tq_icon('help', 17); ?>
                </span>
                <strong style="font:var(--tq-type-bodyStrong);color:var(--tq-navy)">
                    الدليل: من أين تجيء هذه البيانات؟ — سبع خطوات
                </strong>
            </summary>
            <div style="margin-block-start:var(--tq-space-l);border-block-start:1px solid var(--tq-line);padding-block-start:var(--tq-space-l)">

                <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
                    كل ما يلي يجري في حسابك عند ميتا مرة واحدة، ولا يكلف اشتراكا شهريا:
                    الواجهة السحابية مجانية، والمحاسبة على المحادثات (وأول ألف محادثة
                    خدمية في الشهر بلا مقابل عند كتابة هذا). ولا تحتاج وسيطا ولا مزودا
                    ثالثا. <strong>خذها بالترتيب</strong> — الخطوة الرابعة لا تظهر قبل
                    الثالثة.
                </p>

                <ol class="tqa-steps">
                    <li>
                        <b>حساب أعمال في ميتا</b>
                        <p>افتح <a href="https://business.facebook.com" target="_blank" rel="noopener">
                            business.facebook.com</a> وأنشئ حساب أعمال باسم المنصة إن لم يكن
                            لديك واحد. وهذا هو الوعاء الذي يعيش فيه كل ما بعده.</p>
                        <p class="tqa-hint">توثيق الأعمال (Business Verification) ليس شرطا
                            للبدء، ويصير شرطا حين تتجاوز حد الرسائل التجريبي. ابدأ بدونه.</p>
                    </li>

                    <li>
                        <b>تطبيق من نوع Business</b>
                        <p>افتح <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">
                            developers.facebook.com/apps</a> ← <span class="tq-ltr" dir="ltr">Create App</span>
                            ← اختر النوع <span class="tq-ltr" dir="ltr">Business</span>، واربطه بحساب
                            الأعمال من الخطوة الأولى.</p>
                        <p class="tqa-hint">النوع مهم: تطبيق من نوع آخر لا يظهر فيه منتج واتساب أصلا.</p>
                    </li>

                    <li>
                        <b>أضف منتج WhatsApp</b>
                        <p>في صفحة التطبيق ← <span class="tq-ltr" dir="ltr">Add product</span>
                            ← <span class="tq-ltr" dir="ltr">WhatsApp</span> ←
                            <span class="tq-ltr" dir="ltr">Set up</span>. تفتح شاشة اسمها
                            <span class="tq-ltr" dir="ltr">API Setup</span> — وهي التي تعود إليها كثيرا.</p>
                        <p>وفيها تربط <b>رقم المرسل</b>. ولك طريقان:</p>
                        <ul>
                            <li><b>الرقم التجريبي</b> الذي تعطيك إياه ميتا مجانا — يرسل إلى خمسة
                                أرقام تحددها بيدك، ويصلح للتجربة وحدها.</li>
                            <li><b>رقمك الحقيقي</b> —
                                <span class="tq-ltr" dir="ltr">Add phone number</span>. وشرطه أن يكون
                                <strong>رقما لا يعمل عليه تطبيق واتساب عادي ولا واتساب للأعمال</strong>؛
                                فإن كان يعمل عليه فاحذف الحساب منه أولا، وإلا رفض التسجيل.</li>
                        </ul>
                        <p class="tqa-hint">ورقم الدعم الظاهر في الموقع لا يصلح لهذا إن كان
                            يستقبل رسائل الناس على تطبيق واتساب — اختر رقما مخصصا للإرسال الآلي.</p>
                    </li>

                    <li>
                        <b>رمز الوصول الدائم</b> <span class="tqa-badge tqa-badge--warn">أهم خطوة</span>
                        <p>الشاشة السابقة تعرض <span class="tq-ltr" dir="ltr">Temporary access token</span>
                            وعمره <strong>أربع وعشرون ساعة</strong>. ومن نسخه إلى هنا رأى كل شيء
                            يعمل يوما ثم يتوقف بلا سبب ظاهر. فالمطلوب رمز دائم:</p>
                        <ol>
                            <li>افتح <a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener">
                                إعدادات الأعمال ← مستخدمو النظام</a>.</li>
                            <li><span class="tq-ltr" dir="ltr">Add</span> ← أنشئ مستخدم نظام باسم
                                «تقدر» ودوره <span class="tq-ltr" dir="ltr">Admin</span>.</li>
                            <li><span class="tq-ltr" dir="ltr">Add assets</span> ← اختر
                                <span class="tq-ltr" dir="ltr">WhatsApp accounts</span> ← حساب واتساب
                                الخاص بك ← امنحه صلاحية كاملة
                                (<span class="tq-ltr" dir="ltr">Full control</span>).</li>
                            <li><span class="tq-ltr" dir="ltr">Generate new token</span> ← اختر تطبيقك
                                ← ومدة <span class="tq-ltr" dir="ltr">Never</span> ← وفعل الصلاحيتين:
                                <span class="tq-ltr" dir="ltr">whatsapp_business_messaging</span> و
                                <span class="tq-ltr" dir="ltr">whatsapp_business_management</span>.</li>
                            <li>انسخ الرمز <strong>الآن</strong> — لا يعرض مرة أخرى — وضعه في حقل
                                «رمز الوصول» أعلاه.</li>
                        </ol>
                        <p class="tqa-hint">الصلاحية الثانية
                            (<span class="tq-ltr" dir="ltr">management</span>) هي التي تجعل فحص
                            القوالب في هذه الشاشة يعمل. وبدونها ترسل الرسائل ولا يفحص القالب.</p>
                    </li>

                    <li>
                        <b>المعرفان الرقميان</b>
                        <p>ارجع إلى <span class="tq-ltr" dir="ltr">WhatsApp ← API Setup</span>.
                            تحت اختيار الرقم تجد سطرين:</p>
                        <ul>
                            <li><span class="tq-ltr" dir="ltr">Phone number ID</span> —
                                انسخه إلى «معرف رقم المرسل». <strong>وهو ليس رقم الجوال</strong>:
                                رقم طويل من خمس عشرة خانة تقريبا.</li>
                            <li><span class="tq-ltr" dir="ltr">WhatsApp Business Account ID</span> —
                                انسخه إلى «معرف حساب الأعمال».</li>
                        </ul>
                    </li>

                    <li>
                        <b>القالبان</b> <span class="tqa-badge tqa-badge--warn">بدونهما لا يصل شيء</span>
                        <p>افتح <a href="https://business.facebook.com/wa/manage/message-templates/" target="_blank" rel="noopener">
                            مدير القوالب</a> ← <span class="tq-ltr" dir="ltr">Create template</span>،
                            وأنشئ اثنين بالضبط كما هنا:</p>

                        <p><b>١ — قالب الإشعارات</b></p>
                        <pre class="tqa-debug" dir="ltr">Name:      taqdar_notice
Category:  Utility
Language:  Arabic (ar)
Variables: Positional  ({{1}})  ← وليس Named ({{name}})

Body:
منصة تقدر التعليمية

{{1}}

{{2}}

يمكنك متابعة التفاصيل كاملة من حسابك على المنصة. هذه رسالة آلية ولا حاجة للرد عليها.

Sample {{1}}: نجح الدفع وفعل اشتراكك
Sample {{2}}: استلمنا دفعتك وفعلت باقة الصف الاول الثانوي حتى 2026-09-01</pre>
                        <p class="tqa-hint">
                            <strong>البدائل لا تكون وحدها، والنص الثابت حولها يقاس.</strong>
                            ميتا تحسب نسبة البدائل إلى طول الجسم، فترد
                            <span class="tq-ltr" dir="ltr">Parameters words ratio exceeds limit</span>
                            على قالب قصير فيه بديلان — وكلمة واحدة قبلهما لا تكفي. ولهذا
                            الترويسة والخاتمة أعلاه: هما ما يجعل القالب يمر.
                            ولا تكتب سطرين فارغين متتاليين داخل بديل: المنصة تسطح كل بديل
                            قبل إرساله، والقالب لا يقبلهما.
                        </p>
                        <p class="tqa-hint">
                            <strong>وإن سألتك شاشة ميتا عن صيغة البدائل فاختر</strong>
                            <span class="tq-ltr" dir="ltr">Positional</span> —
                            أي <span class="tq-ltr" dir="ltr">{{1}}</span> لا
                            <span class="tq-ltr" dir="ltr">{{name}}</span>. والقالب المسمى
                            يعتمد كما يعتمد المرقم، فلا يظهر الخطأ عند الإنشاء — بل عند أول
                            رسالة، برمز <span class="tq-ltr" dir="ltr">132012</span>: المنصة
                            ترسل بدائلها بالترتيب لا بالاسم.
                        </p>

                        <p style="margin-block-start:var(--tq-space-l)"><b>٢ — قالب رمز التحقق</b></p>
                        <pre class="tqa-debug" dir="ltr">Name:      taqdar_otp
Category:  Authentication     ← وليس Utility
Language:  Arabic (ar)

Code delivery:  Copy code       (اترك زر نسخ الرمز مفعلا)
Validity:       10 minutes
Security recommendation:  مفعلة</pre>
                        <p class="tqa-hint">
                            قوالب <span class="tq-ltr" dir="ltr">Authentication</span> <strong>لا
                            تكتب نصها بيدك</strong>: ميتا تكتبه وتترجمه، وأنت تختار الخيارات
                            وحدها. وهي تعتمد في دقائق غالبا لأن نصها معروف عندها. وإن ألغيت
                            زر النسخ فأزل علامة «زر نسخ الرمز» في البطاقة أعلاه — وإلا ردت
                            كل رسالة برمز <span class="tq-ltr" dir="ltr">132000</span>.
                        </p>

                        <p class="tqa-hint">ثم اكتب الاسمين واللغتين في حقول «القوالب المعتمدة»
                            أعلاه، واحفظ، وأعد تحميل هذه الشاشة: بطاقة «حال الاتصال» تقول لك
                            هل اعتمدا وهل تطابق بدائلهما ما ترسله المنصة.</p>
                    </li>

                    <li>
                        <b>افحص، ثم انقل التطبيق إلى الوضع الحي</b>
                        <p>أرسل رسالة فحص من البطاقة المجاورة إلى رقمك. وإن رد
                            <span class="tq-ltr" dir="ltr">131030</span> — «الرقم ليس في قائمة
                            المستقبلين» — فالتطبيق ما زال في وضع التطوير: إما أن تضيف الرقم في
                            قائمة <span class="tq-ltr" dir="ltr">To</span> بشاشة
                            <span class="tq-ltr" dir="ltr">API Setup</span> (وهي تقبل خمسة أرقام)،
                            وإما أن تنقل التطبيق إلى
                            <span class="tq-ltr" dir="ltr">Live</span> من أعلى صفحة التطبيق عند ميتا.</p>
                        <p class="tqa-hint">وحتى ينقل التطبيق إلى الوضع الحي لا يصل شيء إلى
                            طلابك ولا معلميك — وهذا أكثر ما يوقف الناس بعد ضبط كل شيء بشكل صحيح.</p>
                    </li>
                </ol>

                <div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
                    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
                    <span>
                        <strong>أربعة أخطاء تقع دائما:</strong>
                        رمز مؤقت بدل الدائم (يتوقف بعد يوم) ·
                        نسيان القوالب (كل رسالة ترد <span class="tq-ltr" dir="ltr">131047</span>) ·
                        بقاء التطبيق في وضع التطوير (<span class="tq-ltr" dir="ltr">131030</span>) ·
                        اختلاف عدد البدائل عن القالب (<span class="tq-ltr" dir="ltr">132000</span>).
                        وبطاقة «حال الاتصال» أعلى الشاشة تكشف الأربعة قبل أن تكتشفها بشكوى مستخدم.
                    </span>
                </div>
            </div>
        </details>
    </div>

    <aside class="tqa-stack">

        <?php /* ── رسالة فحص ──────────────────────────────────────── */ ?>
        <div class="tqa-card">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('check-badge', 20); ?></span>
                <h2>رسالة فحص</h2>
            </div>
            <div>
                <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
                    ترسل رسالة حقيقية بالإعدادات المحفوظة وبالمسار الذي تستعمله المنصة نفسها.
                    وإن فشلت يعرض <strong>سبب الفشل مترجما</strong> لا رمزا عاريا — فالسبب هو
                    ما يصلح، لا الفشل.
                </p>

                <form method="post" action="<?php echo site_url('taqdar_admin/whatsapp_test'); ?>">
                    <?php echo tq_csrf(); ?>

                    <div class="tqa-field">
                        <label class="tqa-field__label" for="w_to_test">أرسل إلى</label>
                        <input class="tqa-input tqa-input--ltr" dir="ltr" type="tel" id="w_to_test"
                               name="to" required inputmode="tel" placeholder="0501234567">
                        <small class="tqa-field__hint">
                            سعودي بأي صورة، أو رقم آخر برمز دولته
                            (<span class="tq-ltr" dir="ltr">+20…</span> أو
                            <span class="tq-ltr" dir="ltr">0020…</span>).
                            وبلا رمز دولة يقرأ الرقم سعوديا فيرد.
                        </small>
                    </div>

                    <div class="tqa-field">
                        <label class="tqa-field__label" for="w_kind">بأي طريق؟</label>
                        <select class="tqa-select" id="w_kind" name="kind">
                            <option value="text">نص حر — يفحص الرمز والرقم</option>
                            <option value="notice">قالب الإشعارات — يفحص ما يخرج فعلا</option>
                            <option value="otp">قالب رمز التحقق</option>
                        </select>
                        <small class="tqa-field__hint">
                            <strong>الثلاثة تفشل لأسباب مختلفة، فافحصها كلها.</strong>
                            النص الحر لا يصل إلا لمن راسل رقمك في آخر أربع وعشرين ساعة —
                            ففشله لا يعني عطبا. والقالبان هما ما يخرج إلى المستخدمين فعلا،
                            ونجاحهما هو الدليل.
                        </small>
                    </div>

                    <button type="submit" class="tqa-btn tqa-btn--mastery" <?php echo $configured ? '' : 'disabled'; ?>>
                        أرسل رسالة فحص
                    </button>
                    <?php if (!$configured): ?>
                        <small class="tqa-field__hint" style="color:var(--tq-amber)">
                            احفظ بيانات الاتصال وفعل القناة أولا.
                        </small>
                    <?php endif; ?>
                </form>

                <?php if (!empty($debug)): ?>
                    <div style="margin-block-start:var(--tq-space-l)">
                        <strong class="tqa-hint">ما قالته ميتا:</strong>
                        <pre class="tqa-debug"><?php echo html_escape($debug); ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ── ما يعتمد على واتساب ────────────────────────────── */ ?>
        <div class="tqa-card">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('bell', 20); ?></span>
                <h2>ما يمر من هذه القناة</h2>
            </div>
            <div>
                <?php
                /* الجدول يذكر ما يقع فعلا حين لا يضبط واتساب — لا «معطلة»
                   وحدها. ولا شيء هنا «متوقف»: لكل بند طريق ثان قائم. */
                $tq_pay = $C['notify_payments'] && $configured;
                $tq_otp = $C['otp_allowed'] && $configured;

                $tq_rows = array(
                    array('صدور الفاتورة',       'invoice',      $tq_pay),
                    array('نجاح الدفع بالبطاقة',  'subscription', $tq_pay),
                    array('تفعيل الاشتراك بعد الحوالة', 'subscription', $tq_pay),
                    array('تحويل مبلغ السحب',     'wallet',       $tq_pay),
                    array('رفض طلب السحب',        'wallet',       $tq_pay),
                    array('رمز تأكيد الحساب',     'otp',          $tq_otp),
                );
                ?>
                <table class="tqa-table" style="margin-block-end:var(--tq-space-l)">
                    <tbody>
                    <?php foreach ($tq_rows as list($tq_what, $tq_type, $tq_on)): ?>
                        <tr>
                            <td style="padding-inline:0">
                                <strong><?php echo $tq_what; ?></strong><br>
                                <span class="tqa-hint">
                                    <?php echo $tq_on
                                        ? 'يخرج بواتساب وبالبريد وفي المنصة.'
                                        : ($tq_type === 'otp'
                                            ? 'الرمز بالبريد وحده.'
                                            : 'في المنصة وبالبريد، بلا واتساب.'); ?>
                                </span>
                            </td>
                            <td style="padding-inline:0;text-align:end">
                                <span class="tqa-badge tqa-badge--<?php echo $tq_on ? 'ok' : 'muted'; ?>">
                                    <?php echo $tq_on ? 'يخرج' : 'لا يخرج'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="tqa-hint" style="margin-block-end:var(--tq-space-m)">
                    <strong>ولا شيء يتعطل بغيابها.</strong> الإشعار داخل المنصة يكتب دائما،
                    والبريد يخرج كما كان، والدفع يفعل الاشتراك سواء وصلت الرسالة أو لم تصل.
                </p>

                <?php /* تأكيد الحساب بالرمز: مفتاح يمس التسجيل كله لا واتساب
                         وحده — فيوضع هنا بصريح ما يفعله. */ ?>
                <form method="post" action="<?php echo site_url('taqdar_admin/whatsapp_toggle_otp'); ?>"
                      data-tqa-confirm-title="<?php echo $otp_on ? 'إيقاف تأكيد الحساب' : 'تفعيل تأكيد الحساب'; ?>"
                      data-tqa-confirm="<?php echo $otp_on
                          ? 'الحسابات الجديدة تفتح فور إنشائها بلا تأكيد بريد ولا جوال.'
                          : 'لا يفتح حساب جديد قبل أن يكتب صاحبه رمزا يصله. وإن تعذر إرسال الرمز (لا بريد ولا واتساب) فتح الحساب كما كان — فلا يتعطل التسجيل.'; ?>"
                      data-tqa-confirm-ok="<?php echo $otp_on ? 'أوقف' : 'فعل'; ?>">
                    <?php echo tq_csrf(); ?>
                    <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm">
                        <?php echo $otp_on ? 'أوقف تأكيد الحساب بالرمز' : 'فعل تأكيد الحساب بالرمز'; ?>
                    </button>
                    <span class="tqa-badge tqa-badge--<?php echo $otp_on ? 'ok' : 'muted'; ?>">
                        <?php echo $otp_on ? 'مفعل الآن' : 'مطفأ الآن'; ?>
                    </span>
                </form>
            </div>
        </div>

    </aside>
</div>

<?php /* ── السجل ──────────────────────────────────────────── */ ?>
<div class="tqa-card" style="margin-block-start:var(--tq-space-l)">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-sand" aria-hidden="true"><?php echo tq_icon('receipt', 20); ?></span>
        <h2>آخر المحاولات</h2>
        <span class="tqa-badge tqa-badge--<?php echo empty($totals['failed']) ? 'muted' : 'danger'; ?>"
              style="margin-inline-start:auto">
            <?php echo (int) $totals['today']; ?> اليوم
        </span>
    </div>
    <div>
        <p class="tqa-hint" style="margin-block-end:var(--tq-space-m)">
            البريد يفشل بصوت، وواتساب يفشل بصمت. فكل محاولة تكتب هنا — إلى من ولأي
            غرض وبأي قالب وما ردت ميتا. <strong>ونص الرسالة لا يكتب</strong>: الرمز
            سر، وسجل يحفظه يجعل كل من يفتح اللوحة يقرأ رموز الناس.
        </p>

        <?php if (empty($log)): ?>
            <?php tqa_empty('لم ترسل رسالة بعد',
                'أرسل رسالة فحص من البطاقة أعلاه ليظهر أول صف هنا.', '', '', 'whatsapp'); ?>
        <?php else: ?>
            <?php /* الجدول يمرر داخل صندوقه لا يدفع الصفحة: رسالة خطأ من
                     ميتا سطر طويل، وأربعة أعمدة على شاشة هاتف تخرج عن
                     الحافة فتلتف الصفحة كلها أفقيا. */ ?>
            <div style="overflow-x:auto">
            <table class="tqa-table">
                <thead>
                    <tr><th>إلى</th><th>الغرض</th><th>الحال</th><th>متى</th></tr>
                </thead>
                <tbody>
                <?php
                $tq_purposes = array(
                    'otp'          => 'رمز تحقق',
                    'test'         => 'فحص',
                    'notice'       => 'إشعار',
                    'invoice'      => 'فاتورة',
                    'subscription' => 'اشتراك',
                    'wallet'       => 'محفظة',
                );
                $tq_states = array(
                    'sent'    => array('ok',     'أرسلت'),
                    'failed'  => array('danger', 'فشلت'),
                    'skipped' => array('muted',  'لم تحاول'),
                );
                foreach ($log as $tq_r):
                    $tq_st = isset($tq_states[$tq_r['status']])
                           ? $tq_states[$tq_r['status']] : array('muted', $tq_r['status']);
                ?>
                    <tr>
                        <td>
                            <span class="tq-ltr" dir="ltr"><?php
                                echo html_escape('+' . $tq_r['to_phone']); ?></span>
                            <?php if (!empty($tq_r['user_name'])): ?>
                                <br><span class="tqa-hint"><?php echo html_escape($tq_r['user_name']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php
                            $tq_p = (string) $tq_r['purpose'];
                            echo html_escape(isset($tq_purposes[$tq_p]) ? $tq_purposes[$tq_p] : $tq_p);
                        ?></td>
                        <td>
                            <span class="tqa-badge tqa-badge--<?php echo $tq_st[0]; ?>"><?php
                                echo $tq_st[1]; ?></span>
                            <?php if (!empty($tq_r['error'])): ?>
                                <span class="tqa-status__why"><?php echo html_escape($tq_r['error']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="tqa-hint tq-ltr" dir="ltr"><?php
                            echo html_escape((string) $tq_r['at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</div>
