<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * TQ-SOCIAL — إعداد الدخول بجوجل وأبل.
 *
 * والشاشة ترتب بترتيب السؤال لا بترتيب الأعمدة: **أيعمل الآن؟** ثم
 * **ما وجهة العودة التي تكتب هناك؟** ثم المفاتيح. ومن يضبط أول مرة
 * يقف عند الثانية أكثر مما يقف عند الثالثة: فارق مقطع واحد في وجهة
 * العودة يرد `redirect_uri_mismatch` — رسالة إنجليزية في شاشة المزود
 * لا تقول أي المسارين هو الصحيح.
 *
 * **ولا سر يعرض.** حقول الأسرار تترك فارغة وتعني «لا تمسه»، ومسحها
 * يطلب بمربع — وهو مبدأ رمز CAPI في `tqa_tracking.php` نفسه.
 */
$tq_p   = isset($tq_providers) ? $tq_providers : array();
$tq_c   = isset($tq_cfg) ? $tq_cfg : array();
$tq_rd  = isset($tq_redirect) ? $tq_redirect : array();
$tq_st  = isset($tq_stats) ? $tq_stats : array('google' => 0, 'apple' => 0, 'last' => null);

$tq_on  = array();
foreach ($tq_p as $k => $x) { if (!empty($x['on'])) $tq_on[] = $x['label']; }

$tq_has_gsec = trim((string) get_settings('tq_google_client_secret')) !== '';
$tq_has_akey = trim((string) get_settings('tq_apple_private_key')) !== '';
?>

<?php tqa_head(t('الدخول بجوجل وأبل'),
    t('زران في صفحتي الدخول والتسجيل ونافذة الشراء. وبلا مفاتيح لا يعرض شيء، وتبقى الصفحات كما هي.'),
    'users'); ?>

<?php /* الحال تعلن قبل الحقول: من يفتح الشاشة يريد أن يعرف أولا هل
         الزران معروضان للزائر الآن أم لا. */ ?>
<div class="tqa-note <?php echo $tq_on ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($tq_on ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <?php if ($tq_on): ?>
            <?php echo t('يعرض للزائر الآن:'); ?>
            <strong><?php echo html_escape(implode(' · ', $tq_on)); ?></strong>.
            <?php echo t('أي تعديل هنا يظهر فورا في صفحتي الدخول والتسجيل.'); ?>
        <?php else: ?>
            <strong><?php echo t('لا يعرض أي زر للزائر حاليا.'); ?></strong>
            <?php echo t('ما دامت مفاتيح المزود ناقصة تبقى صفحتا الدخول والتسجيل كما كانتا حرفا بحرف — بلا زر معطل يضغطه أحد.'); ?>
        <?php endif; ?>
    </span>
</div>

<?php /* ── ما دخل فعلا ──────────────────────────────────────────────
         إعداد يبدو صحيحا ولا يدخل منه أحد إعداد معطل — والرقم وحده
         يفرق بين «ضبط ولم يجربه أحد» و«ضبط ويعمل». */ ?>
<div class="tqa-grid tqa-grid--4 tqa-section">
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('حسابات مرتبطة بجوجل'); ?></span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('user-check', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $tq_st['google']; ?></span>
    </div>
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('حسابات مرتبطة بأبل'); ?></span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('user-check', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $tq_st['apple']; ?></span>
    </div>
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('آخر دخول اجتماعي'); ?></span>
            <span class="tqa-stat__icon" aria-hidden="true"><?php echo tq_icon('clock', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo $tq_st['last']
            ? html_escape(date('Y-m-d H:i', strtotime((string) $tq_st['last'])))
            : '—'; ?></span>
        <span class="tqa-stat__hint"><?php echo t('صفر مع مفاتيح مضبوطة يعني أن أحدا لم يجرب الباب بعد.'); ?></span>
    </div>
</div>

<?php /* ── وجهة العودة ───────────────────────────────────────────────
         تكتب حرفا بحرف عند المزود. وهي معروضة هنا بدل أن تركب بيد:
         `https` مكان `http`، أو `/` زائدة في آخرها، يرد كل محاولة
         دخول بـ`redirect_uri_mismatch` — وهي رسالة تظهر في شاشة جوجل
         لا في سجلنا، فلا يجدها من يبحث عندنا. */ ?>
<div class="tqa-card tqa-section">
    <h2 class="tqa-card__t"><?php echo t('وجهة العودة — تكتب عند المزود حرفا بحرف'); ?></h2>
    <p class="tqa-field__hint" style="margin-block-end:10px">
        <?php echo t('انسخها كما هي. أي فارق — حتى شرطة مائلة في آخرها — يرد كل محاولة دخول برسالة عند المزود لا تصل إلينا.'); ?>
    </p>
    <?php foreach (array('google' => t('جوجل'), 'apple' => t('أبل')) as $tq_k => $tq_lbl): ?>
        <div class="tqa-field">
            <label class="tqa-field__label" for="rd_<?php echo $tq_k; ?>"><?php echo html_escape($tq_lbl); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="rd_<?php echo $tq_k; ?>"
                   dir="ltr" readonly onfocus="this.select()"
                   value="<?php echo html_escape(isset($tq_rd[$tq_k]) ? $tq_rd[$tq_k] : ''); ?>">
        </div>
    <?php endforeach; ?>

    <?php /* أبل لا تقبل `http` ولا `localhost` في وجهة العودة بحال —
             فمن جرب محليا ونجح بجوجل يظن الإعداد واحدا ثم يقف. */ ?>
    <div class="tqa-note tqa-note--warn">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span><?php echo t('أبل لا تقبل وجهة عودة على `http` ولا على `localhost` بحال — تجربتها تكون على النطاق الحقيقي وحده. وجوجل تقبل `localhost` للتجربة.'); ?></span>
    </div>
</div>

<form method="post" action="<?php echo site_url('taqdar_admin/social_save'); ?>">
    <?php echo tq_csrf(); ?>

    <?php /* ── جوجل ─────────────────────────────────────────────────── */ ?>
    <div class="tqa-card tqa-section">
        <h2 class="tqa-card__t">
            <?php echo t('جوجل'); ?>
            <?php if (!empty($tq_p['google']['on'])): ?>
                <span class="tqa-badge tqa-badge--ok"><?php echo t('يعمل'); ?></span>
            <?php else: ?>
                <span class="tqa-badge tqa-badge--warn"><?php echo html_escape((string) $tq_p['google']['why']); ?></span>
            <?php endif; ?>
        </h2>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_gid"><?php echo t('معرف العميل (Client ID)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" id="f_gid"
                   name="tq_google_client_id" autocomplete="off" spellcheck="false"
                   placeholder="000000000000-xxxxxxxx.apps.googleusercontent.com"
                   value="<?php echo html_escape((string) $tq_c['google_id']); ?>">
            <span class="tqa-field__hint"><?php echo t('من Google Cloud Console ← APIs & Services ← Credentials ← OAuth client ID من نوع Web application.'); ?></span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_gsec"><?php echo t('سر العميل (Client Secret)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="password" dir="ltr" id="f_gsec"
                   name="tq_google_client_secret" autocomplete="new-password" spellcheck="false"
                   placeholder="<?php echo $tq_has_gsec ? te('محفوظ — اتركه فارغا فلا يمس') : te('الصق السر هنا'); ?>">
            <span class="tqa-field__hint">
                <?php echo t('يترك فارغا فلا يمس. وحقل يفرض كتابته في كل حفظ يجعل تصحيح حرف في المعرف يمحو السر كله فيتوقف الدخول بلا سبب ظاهر.'); ?>
            </span>
            <?php if ($tq_has_gsec): ?>
                <label class="tqa-check">
                    <input type="checkbox" name="tq_google_client_secret_clear" value="1">
                    <span><?php echo t('امسح السر المحفوظ'); ?></span>
                </label>
            <?php endif; ?>
        </div>

        <?php /* TQ-SOCIAL-API — جمهور التطبيق. جوجل تصدر `id_token`
                 بمعرف المنصة التي وقع عليها الدخول لا بمعرف الويب،
                 فبلا هذين يرد **كل** دخول من التطبيق «هذه الهوية صادرة
                 لتطبيق آخر» والمفتاح صحيح تماما. */ ?>
        <div class="tqa-field">
            <label class="tqa-field__label" for="f_gios"><?php echo t('معرف عميل iOS (للتطبيق)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" id="f_gios"
                   name="tq_google_ios_client_id" autocomplete="off" spellcheck="false"
                   value="<?php echo html_escape((string) $tq_c['google_ios']); ?>">
            <span class="tqa-field__hint"><?php echo t('اختياري — يلزم إن كان تطبيق آيفون يسجل الدخول بجوجل.'); ?></span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_gand"><?php echo t('معرف عميل أندرويد (للتطبيق)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" id="f_gand"
                   name="tq_google_android_client_id" autocomplete="off" spellcheck="false"
                   value="<?php echo html_escape((string) $tq_c['google_and']); ?>">
            <span class="tqa-field__hint"><?php echo t('اختياري — يلزم إن كان تطبيق أندرويد يسجل الدخول بجوجل.'); ?></span>
        </div>
    </div>

    <?php /* ── أبل ──────────────────────────────────────────────────── */ ?>
    <div class="tqa-card tqa-section">
        <h2 class="tqa-card__t">
            <?php echo t('أبل'); ?>
            <?php if (!empty($tq_p['apple']['on'])): ?>
                <span class="tqa-badge tqa-badge--ok"><?php echo t('يعمل'); ?></span>
            <?php else: ?>
                <span class="tqa-badge tqa-badge--warn"><?php echo html_escape((string) $tq_p['apple']['why']); ?></span>
            <?php endif; ?>
        </h2>

        <?php /* TQ-APPLE-SECRET — أبل لا تعطي سرا يلصق: تعطي مفتاحا
                 يبنى منه سر موقع في كل مرة، وأجله ستة أشهر. فسر يكتب
                 بيد يتوقف بعد نصف عام ولا يذكر أحد أنه كتبه. */ ?>
        <div class="tqa-note">
            <span aria-hidden="true"><?php echo tq_icon('key', 18); ?></span>
            <span><?php echo t('أبل لا تعطي «سر عميل» جاهزا. تعطي ملف مفتاح (.p8) يولد منه النظام سرا موقعا ويجدده وحده — فلا تحتاج أن تعود إليه كل ستة أشهر.'); ?></span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_aid"><?php echo t('معرف الخدمة (Services ID) — للويب'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" id="f_aid"
                   name="tq_apple_client_id" autocomplete="off" spellcheck="false"
                   placeholder="com.taqdaredu.web"
                   value="<?php echo html_escape((string) $tq_c['apple_id']); ?>">
            <span class="tqa-field__hint"><?php echo t('من Apple Developer ← Identifiers ← Services IDs. وهو غير معرف التطبيق.'); ?></span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_abun"><?php echo t('معرف التطبيق (Bundle ID) — للتطبيق'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" id="f_abun"
                   name="tq_apple_bundle_id" autocomplete="off" spellcheck="false"
                   placeholder="com.taqdaredu.app"
                   value="<?php echo html_escape((string) $tq_c['apple_bundle']); ?>">
            <span class="tqa-field__hint"><?php echo t('اختياري — يلزم إن كان تطبيق آيفون يسجل الدخول بأبل.'); ?></span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_ateam"><?php echo t('معرف الفريق (Team ID)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" id="f_ateam"
                   name="tq_apple_team_id" autocomplete="off" spellcheck="false"
                   placeholder="ABCDE12345" maxlength="16"
                   value="<?php echo html_escape((string) $tq_c['apple_team']); ?>">
            <span class="tqa-field__hint"><?php echo t('عشرة محارف، أعلى يمين حساب المطور عند أبل.'); ?></span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_akid"><?php echo t('معرف المفتاح (Key ID)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" id="f_akid"
                   name="tq_apple_key_id" autocomplete="off" spellcheck="false"
                   placeholder="ABC123DEFG" maxlength="16"
                   value="<?php echo html_escape((string) $tq_c['apple_key_id']); ?>">
            <span class="tqa-field__hint"><?php echo t('يظهر مع المفتاح عند إنشائه في Keys، وهو جزء من اسم ملف .p8 نفسه.'); ?></span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_akey"><?php echo t('المفتاح الخاص — محتوى ملف .p8'); ?></label>
            <textarea class="tqa-textarea tqa-input--ltr" id="f_akey" name="tq_apple_private_key"
                      dir="ltr" rows="5" spellcheck="false" autocomplete="off"
                      placeholder="<?php echo $tq_has_akey
                          ? te('محفوظ — اتركه فارغا فلا يمس')
                          : te('-----BEGIN PRIVATE KEY-----'); ?>"></textarea>
            <span class="tqa-field__hint">
                <?php echo t('افتح ملف AuthKey_XXXXXX.p8 بمحرر نصوص والصق محتواه كاملا بسطوره. وأبل تعطيه مرة واحدة لا تتكرر، فاحفظ نسخة منه في مكان آمن.'); ?>
            </span>
            <?php if ($tq_has_akey): ?>
                <label class="tqa-check">
                    <input type="checkbox" name="tq_apple_private_key_clear" value="1">
                    <span><?php echo t('امسح المفتاح المحفوظ'); ?></span>
                </label>
            <?php endif; ?>
        </div>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ الإعداد'); ?>
        </button>
    </div>
</form>
