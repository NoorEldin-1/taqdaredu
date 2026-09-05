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
    'ok'      => array('ok',     'check', t('سليم')),
    'warn'    => array('warn',   'alert', t('يضعف الوصول')),
    'fail'    => array('danger', 'x',     t('يمنع الوصول')),
    'unknown' => array('muted',  'help',  t('تعذر الفحص')),
);

$tq_fail = 0;
$tq_warn = 0;
foreach ((array) $health as $tq_h) {
    if ($tq_h['state'] === 'fail')     $tq_fail++;
    elseif ($tq_h['state'] === 'warn') $tq_warn++;
}
?>

<?php tqa_head(
    t('إشعارات واتساب'),
    t('منه تخرج إشعارات الدفع ورموز التحقق — إن ضبط. وبلاه لا يتعطل شيء.'),
    'whatsapp',
    $configured
        ? t('<span class="tqa-badge tqa-badge--ok">مفعل</span>')
        : t('<span class="tqa-badge tqa-badge--muted">غير مضبوط</span>')
); ?>

<?php if (!$configured): ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span>
            <strong><?php echo t('واتساب غير مضبوط، والمنصة تعمل بدونه تماما.'); ?></strong>
            <?php echo t('لا خطأ يقع ولا شاشة تتعطل ولا دفعة تتوقف: الرسائل ببساطة لا ترسل، والإشعار داخل المنصة يكتب كما هو، والبريد يخرج كما كان. وكل ما يضيفه الضبط هو'); ?> <strong><?php echo t('قناة ثانية'); ?></strong> <?php echo t('لإشعارات المال ورموز التحقق — والدليل أسفل الشاشة يمشي بك خطوة خطوة.'); ?>
        </span>
    </div>
<?php else: ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('check', 18); ?></span>
        <span><strong><?php echo t('البيانات محفوظة والقناة مفعلة.'); ?></strong>
            <?php echo t('ويبقى ذلك ادعاء حتى تنجح رسالة فحص فعلية — الزر في البطاقة المجاورة، والسجل أسفل الشاشة يقول ما جرى بعده.'); ?></span>
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
        <h2><?php echo t('حال الاتصال بواتساب'); ?></h2>
        <span class="tqa-badge tqa-badge--<?php
            echo $tq_fail ? 'danger' : ($tq_warn ? 'warn' : 'ok'); ?>" style="margin-inline-start:auto">
            <?php
            if ($tq_fail)     echo t('يمنع الوصول: ') . $tq_fail;
            elseif ($tq_warn) echo t('يستحسن إصلاحه: ') . $tq_warn;
            else              echo t('كل الفحوص سليمة');
            ?>
        </span>
    </div>
    <div>
        <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
            <?php echo t('هذه الفحوص تسأل خوادم ميتا'); ?> <strong><?php echo t('الآن'); ?></strong> <?php echo t('عن الرمز والرقم والقوالب، فتعرف ما ستقوله عند أول رسالة حقيقية قبل أن تقوله. وبعضها يحتاج «معرف حساب الأعمال» ليعمل — وهو الحقل الذي يظنه الناس اختياريا.'); ?>
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
                        <p class="tqa-check__fix"><strong><?php echo t('ما العمل:'); ?></strong> <?php echo html_escape($tq_h['fix']); ?></p>
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
                <h2><?php echo t('بيانات الاتصال'); ?></h2>
            </div>
            <div>
                <form method="post" action="<?php echo site_url('taqdar_admin/whatsapp_save'); ?>">
                    <?php echo tq_csrf(); ?>

                    <div class="tqa-field">
                        <label class="tqa-check" for="w_on" style="display:flex;gap:10px;align-items:flex-start">
                            <input type="checkbox" id="w_on" name="tq_wa_enabled" value="1"
                                   <?php echo $C['enabled'] ? 'checked' : ''; ?>>
                            <span>
                                <strong><?php echo t('فعل قناة واتساب'); ?></strong>
                                <small class="tqa-field__hint" style="display:block">
                                    <?php echo t('وهو مفتاح القدرة. وبدونه لا تخرج رسالة ولا يظهر خيار «الرمز بواتساب» لمن يسجل — ويبقى كل شيء آخر كما هو.'); ?>
                                </small>
                            </span>
                        </label>
                    </div>

                    <div class="tqa-fieldgrid">
                        <div class="tqa-field" style="grid-column:1/-1">
                            <label class="tqa-field__label" for="w_token">
                                <?php echo t('رمز الوصول'); ?>
                                <?php echo $has_token ? '' : '<span class="tqa-field__req" aria-hidden="true">*</span>'; ?>
                            </label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="password" id="w_token"
                                   name="tq_wa_token" autocomplete="new-password"
                                   placeholder="<?php echo $has_token
                                       ? t('محفوظ — اتركه فارغا للإبقاء عليه')
                                       : t('EAAG… رمز مستخدم النظام'); ?>">
                            <small class="tqa-field__hint">
                                <?php echo $has_token
                                    ? t('محفوظ ولا يعرض هنا أبدا. اكتب قيمة جديدة لتبديله فقط.')
                                    : t('من مدير الأعمال ← مستخدمو النظام ← «إنشاء رمز» — انظر الخطوة ٤ في الدليل.'); ?>
                                <strong><?php echo t('ولا تستعمل الرمز المؤقت'); ?></strong> <?php echo t('الظاهر في شاشة الإعداد: عمره أربع وعشرون ساعة، ثم تتوقف كل الرسائل بلا سبب ظاهر.'); ?>
                            </small>
                            <?php if ($has_token): ?>
                                <label class="tqa-check" style="margin-block-start:8px;display:flex;gap:8px;align-items:center">
                                    <input type="checkbox" name="tq_wa_token_clear" value="1">
                                    <span class="tqa-hint"><?php echo t('امسح الرمز المحفوظ'); ?></span>
                                </label>
                            <?php endif; ?>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_pid">
                                <?php echo t('معرف رقم المرسل'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                            </label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_pid"
                                   name="tq_wa_phone_id" inputmode="numeric" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_phone_id']); ?>"
                                   placeholder="123456789012345">
                            <small class="tqa-field__hint">
                                <span class="tq-ltr" dir="ltr">Phone number ID</span> <?php echo t('— رقم طويل لا رقم الجوال نفسه. الخطوة ٥.'); ?>
                            </small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_waba"><?php echo t('معرف حساب الأعمال'); ?></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_waba"
                                   name="tq_wa_waba_id" inputmode="numeric" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_waba_id']); ?>"
                                   placeholder="123456789012345">
                            <small class="tqa-field__hint">
                                <span class="tq-ltr" dir="ltr">WABA ID</span> <?php echo t('— لا يرسل به شيء،'); ?>
                                <strong><?php echo t('وبه وحده تفحص القوالب'); ?></strong> <?php echo t('في البطاقة أعلاه. الخطوة ٥.'); ?>
                            </small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_ver"><?php echo t('نسخة الواجهة'); ?></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_ver"
                                   name="tq_wa_api_ver" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_api_ver']); ?>"
                                   placeholder="<?php echo Taqdar_wa_model::DEFAULT_VER; ?>">
                            <small class="tqa-field__hint"><?php echo t('اتركها كما هي ما لم تطلب ميتا غيرها.'); ?></small>
                        </div>
                    </div>

                    <?php /* ── القوالب ────────────────────────────────
                            القسم الذي يخطئ فيه الجميع. الاسم واللغة وعدد
                            البدائل ثلاثتها تدخل في جسم الطلب، واختلاف
                            واحد منها يرد الرسالة برمز لا يفهم. */ ?>
                    <h3 class="tqa-formsec" style="margin-block:var(--tq-space-xl) var(--tq-space-m)">
                        <?php echo t('القوالب المعتمدة'); ?>
                    </h3>
                    <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
                        <strong><?php echo t('واتساب لا يوصل نصا تكتبه.'); ?></strong> <?php echo t('النص الحر لا يخرج إلا لمن راسل رقمك في آخر أربع وعشرين ساعة، وكل ما ترسله المنصة يبدأ منها — رمز تحقق، وخبر دفعة. فلا يخرج شيء منه إلا بقالب صادقت عليه ميتا. والاسم واللغة وعدد البدائل تدخل في كل طلب: اختلاف واحد منها يرد الرسالة ولا يصل شيء.'); ?> <strong><?php echo t('الخطوة ٦ في الدليل تعطيك نص القالبين جاهزا للنسخ.'); ?></strong>
                    </p>

                    <div class="tqa-fieldgrid">
                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_tn"><?php echo t('اسم قالب الإشعارات'); ?></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_tn"
                                   name="tq_wa_tpl_notice" spellcheck="false" autocapitalize="off"
                                   value="<?php echo html_escape($W['tq_wa_tpl_notice']); ?>"
                                   placeholder="taqdar_notice">
                            <small class="tqa-field__hint"><?php echo t('إشعارات الدفع كلها تخرج به.'); ?></small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_tnl"><?php echo t('لغته'); ?></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_tnl"
                                   name="tq_wa_tpl_notice_lang" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_tpl_notice_lang']); ?>"
                                   placeholder="ar">
                            <small class="tqa-field__hint"><?php echo t('كما اعتمد بالحرف:'); ?> <span class="tq-ltr" dir="ltr">ar</span>
                                <?php echo t('غير'); ?> <span class="tq-ltr" dir="ltr">ar_SA</span>.</small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_tnp"><?php echo t('عدد بدائله'); ?></label>
                            <select class="tqa-select" id="w_tnp" name="tq_wa_tpl_notice_params">
                                <option value="2" <?php echo ((int) ($W['tq_wa_tpl_notice_params'] ?: 2)) === 2 ? 'selected' : ''; ?>>
                                    <?php echo t('اثنان — العنوان ثم الجسم (المفضل)'); ?>
                                </option>
                                <option value="1" <?php echo ((int) $W['tq_wa_tpl_notice_params']) === 1 ? 'selected' : ''; ?>>
                                    <?php echo t('واحد — العنوان والجسم في بديل واحد'); ?>
                                </option>
                            </select>
                            <small class="tqa-field__hint">
                                <?php echo t('عد'); ?> <span class="tq-ltr" dir="ltr">{{1}}</span> <?php echo t('في نص قالبك، ولا تخمن: الاختلاف يرد'); ?> <span class="tq-ltr" dir="ltr">132000</span>.
                            </small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_to"><?php echo t('اسم قالب رمز التحقق'); ?></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_to"
                                   name="tq_wa_tpl_otp" spellcheck="false" autocapitalize="off"
                                   value="<?php echo html_escape($W['tq_wa_tpl_otp']); ?>"
                                   placeholder="taqdar_otp">
                            <small class="tqa-field__hint"><?php echo t('من صنف'); ?>
                                <span class="tq-ltr" dir="ltr">Authentication</span> <?php echo t('لا'); ?>
                                <span class="tq-ltr" dir="ltr">Utility</span>.</small>
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label" for="w_tol"><?php echo t('لغته'); ?></label>
                            <input class="tqa-input tqa-input--ltr" dir="ltr" type="text" id="w_tol"
                                   name="tq_wa_tpl_otp_lang" spellcheck="false"
                                   value="<?php echo html_escape($W['tq_wa_tpl_otp_lang']); ?>"
                                   placeholder="ar">
                        </div>

                        <div class="tqa-field">
                            <label class="tqa-field__label"><?php echo t('زر «نسخ الرمز»'); ?></label>
                            <label class="tqa-check" style="display:flex;gap:8px;align-items:flex-start;padding-block-start:6px">
                                <input type="checkbox" name="tq_wa_tpl_otp_button" value="1"
                                       <?php echo $C['tpl_otp_button'] ? 'checked' : ''; ?>>
                                <span class="tqa-hint">
                                    <?php echo t('في القالب زر نسخ الرمز — وهو الحال الافتراضية لقوالب'); ?>
                                    <span class="tq-ltr" dir="ltr">Authentication</span><?php echo t('. وجوده يضيف بديلا ثانيا في الطلب، وخطؤه يرد'); ?>
                                    <span class="tq-ltr" dir="ltr">132000</span>.
                                </span>
                            </label>
                        </div>
                    </div>

                    <?php /* ── ماذا يخرج من هذه القناة ───────────────── */ ?>
                    <h3 class="tqa-formsec" style="margin-block:var(--tq-space-xl) var(--tq-space-m)">
                        <?php echo t('ماذا يخرج من هذه القناة'); ?>
                    </h3>

                    <p class="tqa-field__hint" style="margin-block-end:var(--tq-space-m)">
                        <?php echo t('كل ما تكتبه المنصة من إشعارات يخرج في هذه القناة كما يخرج في البريد، والمربعات تحته تقول أي عائلة منها تخرج. وثلاثة حراس بعد هذا الاختيار: تفضيل صاحب الحساب في شاشة إعداداته، وساعات صمته (تؤجل ما يحتمل التأجيل ولا تسقطه)، ووجود رقم جوال عنده أصلا.'); ?>
                    </p>

                    <?php foreach ($C['families'] as $tq_fk => $tq_fam): ?>
                    <div class="tqa-field">
                        <label class="tqa-check" style="display:flex;gap:10px;align-items:flex-start">
                            <input type="checkbox" name="tq_wa_fam_<?php echo html_escape($tq_fk); ?>" value="1"
                                   <?php echo $tq_fam['on'] ? 'checked' : ''; ?>>
                            <span>
                                <strong><?php echo html_escape($tq_fam['label']); ?></strong>
                                <small class="tqa-field__hint" style="display:block">
                                    <?php echo html_escape($tq_fam['hint']); ?>
                                    <?php if ($tq_fam['quiet']): ?>
                                        — <?php echo t('ويؤجل إلى ما بعد ساعات صمت صاحبه.'); ?>
                                    <?php endif; ?>
                                </small>
                            </span>
                        </label>
                    </div>
                    <?php endforeach; ?>

                    <p class="tqa-field__hint" style="margin-block-end:var(--tq-space-l)">
                        <?php echo t('و«التسويق» مطفأ افتراضا عن قصد: البريد يبتلع الإعلان وواتساب يسده المستلم — وبلاغاته تخفض جودة الرقم عند ميتا ثم حد إرساله اليومي، فتضيع معها إشعارات المال نفسها. فالثمن ليس رسالة تفقد، بل القناة كلها.'); ?>
                    </p>

                    <div class="tqa-field">
                        <label class="tqa-check" style="display:flex;gap:10px;align-items:flex-start">
                            <input type="checkbox" name="tq_wa_otp_allowed" value="1"
                                   <?php echo $C['otp_allowed'] ? 'checked' : ''; ?>>
                            <span>
                                <strong><?php echo t('رمز تأكيد الحساب'); ?></strong>
                                <small class="tqa-field__hint" style="display:block">
                                    <?php echo t('يظهر للمعلم ولولي الأمر خيار «أرسل الرمز بواتساب» عند التسجيل — فهما وحدهما من يكتب جواله. والطالب لا يسأل عن جواله أصلا، فرمزه بالبريد دائما.'); ?>
                                </small>
                            </span>
                        </label>
                    </div>

                    <div class="tqa-actions">
                        <button type="submit" class="tqa-btn tqa-btn--primary">
                            <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ الإعدادات'); ?>
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
                    <?php echo t('الدليل: من أين تجيء هذه البيانات؟ — سبع خطوات'); ?>
                </strong>
            </summary>
            <div style="margin-block-start:var(--tq-space-l);border-block-start:1px solid var(--tq-line);padding-block-start:var(--tq-space-l)">

                <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
                    <?php echo t('كل ما يلي يجري في حسابك عند ميتا مرة واحدة، ولا يكلف اشتراكا شهريا: الواجهة السحابية مجانية، والمحاسبة على المحادثات (وأول ألف محادثة خدمية في الشهر بلا مقابل عند كتابة هذا). ولا تحتاج وسيطا ولا مزودا ثالثا.'); ?> <strong><?php echo t('خذها بالترتيب'); ?></strong> <?php echo t('— الخطوة الرابعة لا تظهر قبل الثالثة.'); ?>
                </p>

                <ol class="tqa-steps">
                    <li>
                        <b><?php echo t('حساب أعمال في ميتا'); ?></b>
                        <p><?php echo t('افتح'); ?> <a href="https://business.facebook.com" target="_blank" rel="noopener">
                            business.facebook.com</a> <?php echo t('وأنشئ حساب أعمال باسم المنصة إن لم يكن لديك واحد. وهذا هو الوعاء الذي يعيش فيه كل ما بعده.'); ?></p>
                        <p class="tqa-hint"><?php echo t('توثيق الأعمال (Business Verification) ليس شرطا للبدء، ويصير شرطا حين تتجاوز حد الرسائل التجريبي. ابدأ بدونه.'); ?></p>
                    </li>

                    <li>
                        <b><?php echo t('تطبيق من نوع Business'); ?></b>
                        <p><?php echo t('افتح'); ?> <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">
                            developers.facebook.com/apps</a> ← <span class="tq-ltr" dir="ltr">Create App</span>
                            <?php echo t('← اختر النوع'); ?> <span class="tq-ltr" dir="ltr">Business</span><?php echo t('، واربطه بحساب الأعمال من الخطوة الأولى.'); ?></p>
                        <p class="tqa-hint"><?php echo t('النوع مهم: تطبيق من نوع آخر لا يظهر فيه منتج واتساب أصلا.'); ?></p>
                    </li>

                    <li>
                        <b><?php echo t('أضف منتج WhatsApp'); ?></b>
                        <p><?php echo t('في صفحة التطبيق ←'); ?> <span class="tq-ltr" dir="ltr">Add product</span>
                            ← <span class="tq-ltr" dir="ltr">WhatsApp</span> ←
                            <span class="tq-ltr" dir="ltr">Set up</span><?php echo t('. تفتح شاشة اسمها'); ?>
                            <span class="tq-ltr" dir="ltr">API Setup</span> <?php echo t('— وهي التي تعود إليها كثيرا.'); ?></p>
                        <p><?php echo t('وفيها تربط'); ?> <b><?php echo t('رقم المرسل'); ?></b><?php echo t('. ولك طريقان:'); ?></p>
                        <ul>
                            <li><b><?php echo t('الرقم التجريبي'); ?></b> <?php echo t('الذي تعطيك إياه ميتا مجانا — يرسل إلى خمسة أرقام تحددها بيدك، ويصلح للتجربة وحدها.'); ?></li>
                            <li><b><?php echo t('رقمك الحقيقي'); ?></b> —
                                <span class="tq-ltr" dir="ltr">Add phone number</span><?php echo t('. وشرطه أن يكون'); ?>
                                <strong><?php echo t('رقما لا يعمل عليه تطبيق واتساب عادي ولا واتساب للأعمال'); ?></strong><?php echo t('؛ فإن كان يعمل عليه فاحذف الحساب منه أولا، وإلا رفض التسجيل.'); ?></li>
                        </ul>
                        <p class="tqa-hint"><?php echo t('ورقم الدعم الظاهر في الموقع لا يصلح لهذا إن كان يستقبل رسائل الناس على تطبيق واتساب — اختر رقما مخصصا للإرسال الآلي.'); ?></p>
                    </li>

                    <li>
                        <b><?php echo t('رمز الوصول الدائم'); ?></b> <span class="tqa-badge tqa-badge--warn"><?php echo t('أهم خطوة'); ?></span>
                        <p><?php echo t('الشاشة السابقة تعرض'); ?> <span class="tq-ltr" dir="ltr">Temporary access token</span>
                            <?php echo t('وعمره'); ?> <strong><?php echo t('أربع وعشرون ساعة'); ?></strong><?php echo t('. ومن نسخه إلى هنا رأى كل شيء يعمل يوما ثم يتوقف بلا سبب ظاهر. فالمطلوب رمز دائم:'); ?></p>
                        <ol>
                            <li><?php echo t('افتح'); ?> <a href="https://business.facebook.com/settings/system-users" target="_blank" rel="noopener">
                                <?php echo t('إعدادات الأعمال ← مستخدمو النظام'); ?></a>.</li>
                            <li><span class="tq-ltr" dir="ltr">Add</span> <?php echo t('← أنشئ مستخدم نظام باسم «تقدر» ودوره'); ?> <span class="tq-ltr" dir="ltr">Admin</span>.</li>
                            <li><span class="tq-ltr" dir="ltr">Add assets</span> <?php echo t('← اختر'); ?>
                                <span class="tq-ltr" dir="ltr">WhatsApp accounts</span> <?php echo t('← حساب واتساب الخاص بك ← امنحه صلاحية كاملة ('); ?><span class="tq-ltr" dir="ltr">Full control</span>).</li>
                            <li><span class="tq-ltr" dir="ltr">Generate new token</span> <?php echo t('← اختر تطبيقك ← ومدة'); ?> <span class="tq-ltr" dir="ltr">Never</span> <?php echo t('← وفعل الصلاحيتين:'); ?>
                                <span class="tq-ltr" dir="ltr">whatsapp_business_messaging</span> <?php echo t('و'); ?>
                                <span class="tq-ltr" dir="ltr">whatsapp_business_management</span>.</li>
                            <li><?php echo t('انسخ الرمز'); ?> <strong><?php echo t('الآن'); ?></strong> <?php echo t('— لا يعرض مرة أخرى — وضعه في حقل «رمز الوصول» أعلاه.'); ?></li>
                        </ol>
                        <p class="tqa-hint"><?php echo t('الصلاحية الثانية ('); ?><span class="tq-ltr" dir="ltr">management</span><?php echo t(') هي التي تجعل فحص القوالب في هذه الشاشة يعمل. وبدونها ترسل الرسائل ولا يفحص القالب.'); ?></p>
                    </li>

                    <li>
                        <b><?php echo t('المعرفان الرقميان'); ?></b>
                        <p><?php echo t('ارجع إلى'); ?> <span class="tq-ltr" dir="ltr">WhatsApp ← API Setup</span><?php echo t('. تحت اختيار الرقم تجد سطرين:'); ?></p>
                        <ul>
                            <li><span class="tq-ltr" dir="ltr">Phone number ID</span> <?php echo t('— انسخه إلى «معرف رقم المرسل».'); ?> <strong><?php echo t('وهو ليس رقم الجوال'); ?></strong><?php echo t(': رقم طويل من خمس عشرة خانة تقريبا.'); ?></li>
                            <li><span class="tq-ltr" dir="ltr">WhatsApp Business Account ID</span> <?php echo t('— انسخه إلى «معرف حساب الأعمال».'); ?></li>
                        </ul>
                    </li>

                    <li>
                        <b><?php echo t('القالبان'); ?></b> <span class="tqa-badge tqa-badge--warn"><?php echo t('بدونهما لا يصل شيء'); ?></span>
                        <p><?php echo t('افتح'); ?> <a href="https://business.facebook.com/wa/manage/message-templates/" target="_blank" rel="noopener">
                            <?php echo t('مدير القوالب'); ?></a> ← <span class="tq-ltr" dir="ltr">Create template</span><?php echo t('، وأنشئ اثنين بالضبط كما هنا:'); ?></p>

                        <p><b><?php echo t('١ — قالب الإشعارات'); ?></b></p>
                        <pre class="tqa-debug" dir="ltr"><?php echo t('Name: taqdar_notice Category: Utility Language: Arabic (ar) Variables: Positional ({{1}}) ← وليس Named ({{name}}) Body: منصة تقدر التعليمية {{1}} {{2}} يمكنك متابعة التفاصيل كاملة من حسابك على المنصة. هذه رسالة آلية ولا حاجة للرد عليها. Sample {{1}}: نجح الدفع وفعل اشتراكك Sample {{2}}: استلمنا دفعتك وفعلت باقة الصف الاول الثانوي حتى 2026-09-01'); ?></pre>
                        <p class="tqa-hint">
                            <strong><?php echo t('البدائل لا تكون وحدها، والنص الثابت حولها يقاس.'); ?></strong>
                            <?php echo t('ميتا تحسب نسبة البدائل إلى طول الجسم، فترد'); ?>
                            <span class="tq-ltr" dir="ltr">Parameters words ratio exceeds limit</span>
                            <?php echo t('على قالب قصير فيه بديلان — وكلمة واحدة قبلهما لا تكفي. ولهذا الترويسة والخاتمة أعلاه: هما ما يجعل القالب يمر. ولا تكتب سطرين فارغين متتاليين داخل بديل: المنصة تسطح كل بديل قبل إرساله، والقالب لا يقبلهما.'); ?>
                        </p>
                        <p class="tqa-hint">
                            <strong><?php echo t('وإن سألتك شاشة ميتا عن صيغة البدائل فاختر'); ?></strong>
                            <span class="tq-ltr" dir="ltr">Positional</span> <?php echo t('— أي'); ?> <span class="tq-ltr" dir="ltr">{{1}}</span> <?php echo t('لا'); ?>
                            <span class="tq-ltr" dir="ltr">{{name}}</span><?php echo t('. والقالب المسمى يعتمد كما يعتمد المرقم، فلا يظهر الخطأ عند الإنشاء — بل عند أول رسالة، برمز'); ?> <span class="tq-ltr" dir="ltr">132012</span><?php echo t(': المنصة ترسل بدائلها بالترتيب لا بالاسم.'); ?>
                        </p>

                        <p style="margin-block-start:var(--tq-space-l)"><b><?php echo t('٢ — قالب رمز التحقق'); ?></b></p>
                        <pre class="tqa-debug" dir="ltr"><?php echo t('Name: taqdar_otp Category: Authentication ← وليس Utility Language: Arabic (ar) Code delivery: Copy code (اترك زر نسخ الرمز مفعلا) Validity: 10 minutes Security recommendation: مفعلة'); ?></pre>
                        <p class="tqa-hint">
                            <?php echo t('قوالب'); ?> <span class="tq-ltr" dir="ltr">Authentication</span> <strong><?php echo t('لا تكتب نصها بيدك'); ?></strong><?php echo t(': ميتا تكتبه وتترجمه، وأنت تختار الخيارات وحدها. وهي تعتمد في دقائق غالبا لأن نصها معروف عندها. وإن ألغيت زر النسخ فأزل علامة «زر نسخ الرمز» في البطاقة أعلاه — وإلا ردت كل رسالة برمز'); ?> <span class="tq-ltr" dir="ltr">132000</span>.
                        </p>

                        <p class="tqa-hint"><?php echo t('ثم اكتب الاسمين واللغتين في حقول «القوالب المعتمدة» أعلاه، واحفظ، وأعد تحميل هذه الشاشة: بطاقة «حال الاتصال» تقول لك هل اعتمدا وهل تطابق بدائلهما ما ترسله المنصة.'); ?></p>
                    </li>

                    <li>
                        <b><?php echo t('افحص، ثم انقل التطبيق إلى الوضع الحي'); ?></b>
                        <p><?php echo t('أرسل رسالة فحص من البطاقة المجاورة إلى رقمك. وإن رد'); ?>
                            <span class="tq-ltr" dir="ltr">131030</span> <?php echo t('— «الرقم ليس في قائمة المستقبلين» — فالتطبيق ما زال في وضع التطوير: إما أن تضيف الرقم في قائمة'); ?> <span class="tq-ltr" dir="ltr">To</span> <?php echo t('بشاشة'); ?>
                            <span class="tq-ltr" dir="ltr">API Setup</span> <?php echo t('(وهي تقبل خمسة أرقام)، وإما أن تنقل التطبيق إلى'); ?>
                            <span class="tq-ltr" dir="ltr">Live</span> <?php echo t('من أعلى صفحة التطبيق عند ميتا.'); ?></p>
                        <p class="tqa-hint"><?php echo t('وحتى ينقل التطبيق إلى الوضع الحي لا يصل شيء إلى طلابك ولا معلميك — وهذا أكثر ما يوقف الناس بعد ضبط كل شيء بشكل صحيح.'); ?></p>
                    </li>
                </ol>

                <div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
                    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
                    <span>
                        <strong><?php echo t('أربعة أخطاء تقع دائما:'); ?></strong>
                        <?php echo t('رمز مؤقت بدل الدائم (يتوقف بعد يوم) · نسيان القوالب (كل رسالة ترد'); ?> <span class="tq-ltr" dir="ltr">131047</span><?php echo t(') · بقاء التطبيق في وضع التطوير ('); ?><span class="tq-ltr" dir="ltr">131030</span><?php echo t(') · اختلاف عدد البدائل عن القالب ('); ?><span class="tq-ltr" dir="ltr">132000</span><?php echo t('). وبطاقة «حال الاتصال» أعلى الشاشة تكشف الأربعة قبل أن تكتشفها بشكوى مستخدم.'); ?>
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
                <h2><?php echo t('رسالة فحص'); ?></h2>
            </div>
            <div>
                <p class="tqa-hint" style="margin-block-end:var(--tq-space-l)">
                    <?php echo t('ترسل رسالة حقيقية بالإعدادات المحفوظة وبالمسار الذي تستعمله المنصة نفسها. وإن فشلت يعرض'); ?> <strong><?php echo t('سبب الفشل مترجما'); ?></strong> <?php echo t('لا رمزا عاريا — فالسبب هو ما يصلح، لا الفشل.'); ?>
                </p>

                <form method="post" action="<?php echo site_url('taqdar_admin/whatsapp_test'); ?>">
                    <?php echo tq_csrf(); ?>

                    <div class="tqa-field">
                        <label class="tqa-field__label" for="w_to_test"><?php echo t('أرسل إلى'); ?></label>
                        <input class="tqa-input tqa-input--ltr" dir="ltr" type="tel" id="w_to_test"
                               name="to" required inputmode="tel" placeholder="0501234567">
                        <small class="tqa-field__hint">
                            <?php echo t('سعودي بأي صورة، أو رقم آخر برمز دولته ('); ?><span class="tq-ltr" dir="ltr">+20…</span> <?php echo t('أو'); ?>
                            <span class="tq-ltr" dir="ltr">0020…</span><?php echo t('). وبلا رمز دولة يقرأ الرقم سعوديا فيرد.'); ?>
                        </small>
                    </div>

                    <div class="tqa-field">
                        <label class="tqa-field__label" for="w_kind"><?php echo t('بأي طريق؟'); ?></label>
                        <select class="tqa-select" id="w_kind" name="kind">
                            <option value="text"><?php echo t('نص حر — يفحص الرمز والرقم'); ?></option>
                            <option value="notice"><?php echo t('قالب الإشعارات — يفحص ما يخرج فعلا'); ?></option>
                            <option value="otp"><?php echo t('قالب رمز التحقق'); ?></option>
                        </select>
                        <small class="tqa-field__hint">
                            <strong><?php echo t('الثلاثة تفشل لأسباب مختلفة، فافحصها كلها.'); ?></strong>
                            <?php echo t('النص الحر لا يصل إلا لمن راسل رقمك في آخر أربع وعشرين ساعة — ففشله لا يعني عطبا. والقالبان هما ما يخرج إلى المستخدمين فعلا، ونجاحهما هو الدليل.'); ?>
                        </small>
                    </div>

                    <button type="submit" class="tqa-btn tqa-btn--mastery" <?php echo $configured ? '' : 'disabled'; ?>>
                        <?php echo t('أرسل رسالة فحص'); ?>
                    </button>
                    <?php if (!$configured): ?>
                        <small class="tqa-field__hint" style="color:var(--tq-amber)">
                            <?php echo t('احفظ بيانات الاتصال وفعل القناة أولا.'); ?>
                        </small>
                    <?php endif; ?>
                </form>

                <?php if (!empty($debug)): ?>
                    <div style="margin-block-start:var(--tq-space-l)">
                        <strong class="tqa-hint"><?php echo t('ما قالته ميتا:'); ?></strong>
                        <pre class="tqa-debug"><?php echo html_escape($debug); ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ── ما يعتمد على واتساب ────────────────────────────── */ ?>
        <div class="tqa-card">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('bell', 20); ?></span>
                <h2><?php echo t('ما يمر من هذه القناة'); ?></h2>
            </div>
            <div>
                <?php
                /* الجدول يذكر ما يقع فعلا حين لا يضبط واتساب — لا «معطلة»
                   وحدها. ولا شيء هنا «متوقف»: لكل بند طريق ثان قائم. */
                /* والصفوف تشتق من العائلات لا تكتب بيد: عائلة تضاف في
                   `Taqdar_wa_model::$FAMILIES` تظهر هنا بلا أن يتذكر
                   كاتبها هذا القالب — وسطر يكتب بيد بجوار قائمة تشتق
                   يفترقان عند أول إضافة، فتقول الشاشة «لا يخرج» عن
                   عائلة تخرج فعلا. */
                $tq_rows = array();
                foreach ($C['families'] as $tq_fam) {
                    $tq_rows[] = array($tq_fam['label'], $tq_fam['hint'],
                                       $tq_fam['on'] && $configured, $tq_fam['quiet']);
                }
                $tq_rows[] = array(t('رمز تأكيد الحساب'),
                                   t('يخرج للمعلم ولولي الأمر إن اختاره عند التسجيل.'),
                                   $C['otp_allowed'] && $configured, false);
                ?>
                <div class="tqa-table__wrap">
                    <table class="tqa-table" style="margin-block-end:var(--tq-space-l)">
                        <tbody>
                        <?php foreach ($tq_rows as list($tq_what, $tq_hint, $tq_on, $tq_quiet)): ?>
                            <tr>
                                <td style="padding-inline:0">
                                    <strong><?php echo html_escape($tq_what); ?></strong><br>
                                    <span class="tqa-hint"><?php echo html_escape($tq_hint); ?></span><br>
                                    <span class="tqa-hint">
                                        <?php echo $tq_on
                                            ? ($tq_quiet
                                                ? t('يخرج بواتساب وبالبريد وفي المنصة — ويؤجل في ساعات الصمت.')
                                                : t('يخرج بواتساب وبالبريد وفي المنصة.'))
                                            : t('في المنصة وبالبريد، بلا واتساب.'); ?>
                                    </span>
                                </td>
                                <td style="padding-inline:0;text-align:end">
                                    <span class="tqa-badge tqa-badge--<?php echo $tq_on ? 'ok' : 'muted'; ?>">
                                        <?php echo $tq_on ? t('يخرج') : t('لا يخرج'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="tqa-hint" style="margin-block-end:var(--tq-space-m)">
                    <strong><?php echo t('ولا شيء يتعطل بغيابها.'); ?></strong> <?php echo t('الإشعار داخل المنصة يكتب دائما، والبريد يخرج كما كان، والدفع يفعل الاشتراك سواء وصلت الرسالة أو لم تصل.'); ?>
                </p>

                <?php /* تأكيد الحساب بالرمز: مفتاح يمس التسجيل كله لا واتساب
                         وحده — فيوضع هنا بصريح ما يفعله. */ ?>
                <form method="post" action="<?php echo site_url('taqdar_admin/whatsapp_toggle_otp'); ?>"
                      data-tqa-confirm-title="<?php echo $otp_on ? t('إيقاف تأكيد الحساب') : t('تفعيل تأكيد الحساب'); ?>"
                      data-tqa-confirm="<?php echo $otp_on
                          ? t('الحسابات الجديدة تفتح فور إنشائها بلا تأكيد بريد ولا جوال.')
                          : t('لا يفتح حساب جديد قبل أن يكتب صاحبه رمزا يصله. وإن تعذر إرسال الرمز (لا بريد ولا واتساب) فتح الحساب كما كان — فلا يتعطل التسجيل.'); ?>"
                      data-tqa-confirm-ok="<?php echo $otp_on ? t('أوقف') : t('فعل'); ?>">
                    <?php echo tq_csrf(); ?>
                    <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm">
                        <?php echo $otp_on ? t('أوقف تأكيد الحساب بالرمز') : t('فعل تأكيد الحساب بالرمز'); ?>
                    </button>
                    <span class="tqa-badge tqa-badge--<?php echo $otp_on ? 'ok' : 'muted'; ?>">
                        <?php echo $otp_on ? t('مفعل الآن') : t('مطفأ الآن'); ?>
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
        <h2><?php echo t('آخر المحاولات'); ?></h2>
        <span class="tqa-badge tqa-badge--<?php echo empty($totals['failed']) ? 'muted' : 'danger'; ?>"
              style="margin-inline-start:auto">
            <?php echo (int) $totals['today']; ?> <?php echo t('اليوم'); ?>
        </span>
    </div>
    <div>
        <p class="tqa-hint" style="margin-block-end:var(--tq-space-m)">
            <?php echo t('البريد يفشل بصوت، وواتساب يفشل بصمت. فكل محاولة تكتب هنا — إلى من ولأي غرض وبأي قالب وما ردت ميتا.'); ?> <strong><?php echo t('ونص الرسالة لا يكتب'); ?></strong><?php echo t(': الرمز سر، وسجل يحفظه يجعل كل من يفتح اللوحة يقرأ رموز الناس.'); ?>
        </p>

        <?php if (empty($log)): ?>
            <?php tqa_empty(t('لم ترسل رسالة بعد'),
                t('أرسل رسالة فحص من البطاقة أعلاه ليظهر أول صف هنا.'), '', '', 'whatsapp'); ?>
        <?php else: ?>
            <?php /* الجدول يمرر داخل صندوقه لا يدفع الصفحة: رسالة خطأ من
                     ميتا سطر طويل، وأربعة أعمدة على شاشة هاتف تخرج عن
                     الحافة فتلتف الصفحة كلها أفقيا. */ ?>
            <div style="overflow-x:auto">
            <div class="tqa-table__wrap">
                <table class="tqa-table">
                    <thead>
                        <tr><th><?php echo t('إلى'); ?></th><th><?php echo t('الغرض'); ?></th><th><?php echo t('الحال'); ?></th><th><?php echo t('متى'); ?></th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $tq_purposes = array(
                        'otp'          => t('رمز تحقق'),
                        'test'         => t('فحص'),
                        'notice'       => t('إشعار'),
                        'invoice'      => t('فاتورة'),
                        'subscription' => t('اشتراك'),
                        'wallet'       => t('محفظة'),
                    );
                    $tq_states = array(
                        'sent'    => array('ok',     t('أرسلت')),
                        'failed'  => array('danger', t('فشلت')),
                        'skipped' => array('muted',  t('لم تحاول')),
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
                                    <?php /* TQ-I18N — سبب مخزن في `tq_wa_log`: يكتب مرة وقت المحاولة
                                         ويقرأ بعدها بلغة من يفتح الشاشة. */ ?>
                                    <span class="tqa-status__why"><?php echo html_escape(t($tq_r['error'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="tqa-hint tq-ltr" dir="ltr"><?php
                                echo html_escape((string) $tq_r['at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            </div>
        <?php endif; ?>
    </div>
</div>
