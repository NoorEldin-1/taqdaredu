<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * بكسل ميتا — مفتاح واحد، وشاشة تجيب ثلاثة أسئلة يسألها من يفتحها:
 * هل يعمل الآن؟ وأين يعمل؟ وكيف أتحقق بنفسي بدل أن أصدق رسالة الحفظ؟
 *
 * ولماذا شاشة لا حقل في «إعدادات الموقع» الموروثة: هناك حقل
 * `custom_css` وحده يقبل نصا حرا، ولصق قصاصة ميتا فيه يضع سكربتا داخل
 * وسم `<style>` — فلا يعمل ولا يخطئ. وقد وصلت القصاصة من فريق التسويق
 * **مكررة مرتين**، ولصقها كما وصلت يسجل زيارتين لكل زائر. فالحقل رقم
 * لا قصاصة: المعرف يكتب، والوسم يبنيه الخادم مرة واحدة.
 *
 * TQ-META-CAPI — **وصارت الشاشة طبقتين لا واحدة.** البكسل يقيس ما يقع
 * في متصفحنا، وصفحة الدفع ليست فيه: تاب تأخذ المشتري إلى نطاقها. فحدث
 * الشراء — وهو الحدث الذي تبنى عليه كل حملة تحويل — كان لا يسجل أبدا:
 * من أغلق متصفحه بعد الدفع لا يعود، ومن معه مانع إعلانات لا يقاس،
 * والحوالة البنكية تفعل بعد أيام بلا متصفح أصلا. فالطبقة الثانية تسد
 * ذلك من الخادم، وترتيب الشاشة يقول ذلك: القياس، ثم الشراء، ثم التحقق.
 */
$tq_saved   = get_settings('tq_meta_pixel_id');   // NULL = لم يضبط قط
$tq_live    = tq_meta_pixel_id();                 // المعرف الفعال بعد الافتراضي
$tq_on      = $tq_live !== '';
$tq_default = ($tq_saved === null);

$tq_capi    = !empty($tq_capi_ready);
$tq_test    = (string) ($tq_test_code ?? '');
$tq_tot     = is_array($tq_totals ?? null) ? $tq_totals : array();
$tq_evs     = is_array($tq_events ?? null) ? $tq_events : array();
?>

<?php tqa_head(t('بكسل ميتا'), t('قياس زيارات الموقع ومبيعاته لحملات فيسبوك وإنستغرام.'), 'chart'); ?>

<?php /* الحال قبل الحقل: من يفتح الشاشة يريد أن يعرف أولا هل يقاس شيء الآن.
         وسطران لا سطر: الزيارة والشراء يقاسان بطريقتين، وقد تعمل واحدة
         وتسكت الأخرى — وهي الحال التي كانت قائمة قبل هذا الملف. */ ?>
<div class="tqa-note <?php echo $tq_on ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($tq_on ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <?php if ($tq_on): ?>
            <strong><?php echo t('الزيارة تقاس'); ?></strong> <?php echo t('بالمعرف ____ — على صفحات الموقع العام وبوابات الطالب والمعلم وولي الأمر جميعا، ولا يحمل في لوحة الإدارة.', tq_num($tq_live)); ?>
            <?php if ($tq_default): ?>
                <?php echo t('وهو المعرف الافتراضي المكتوب في الشيفرة؛ الحفظ من هنا يعلو عليه.'); ?>
            <?php endif; ?>
        <?php else: ?>
            <strong><?php echo t('مطفأ.'); ?></strong> <?php echo t('لا يحمل سكربت ميتا في أي صفحة، ولا تقاس أي زيارة.'); ?>
        <?php endif; ?>
    </span>
</div>

<div class="tqa-note <?php echo $tq_capi ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($tq_capi ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <?php if ($tq_capi): ?>
            <strong><?php echo t('والشراء يقاس من الخادم'); ?></strong>
            <?php echo t('— يرسل عند سداد كل فاتورة: بطاقة وحوالة وحصة. فمن دفع وأغلق متصفحه يعد كما يعد من عاد.'); ?>
            <?php if ($tq_test !== ''): ?>
                <br><strong><?php echo t('تنبيه:'); ?></strong>
                <?php echo t('رمز اختبار محفوظ (____) — والأحداث تذهب إلى تبويب «اختبار الأحداث» ولا تحسب في الحملة. امسحه عند التشغيل الحقيقي.', html_escape($tq_test)); ?>
            <?php endif; ?>
        <?php else: ?>
            <strong><?php echo t('والشراء لا يقاس.'); ?></strong>
            <?php echo t('صفحة الدفع عند تاب لا عندنا، فحدث الشراء لا يقع في متصفحنا: من أغلق متصفحه بعد الدفع لا يعد، ومن معه مانع إعلانات لا يعد، والحوالة البنكية تفعل بعد أيام بلا متصفح. اكتب رمز الوصول أدناه ليرسل الخادم الشراء بنفسه.'); ?>
        <?php endif; ?>
    </span>
</div>

<div class="tqa-card" style="max-inline-size:720px">
    <form method="post" action="<?php echo site_url('taqdar_admin/tracking_save'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_pixel"><?php echo t('معرف البكسل (Pixel ID)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_pixel"
                   name="tq_meta_pixel_id" dir="ltr" inputmode="numeric"
                   maxlength="24" autocomplete="off" spellcheck="false"
                   placeholder="1616041446836504"
                   value="<?php echo html_escape($tq_saved === null ? $tq_live : $tq_saved); ?>">
            <span class="tqa-field__hint">
                <?php echo t('رقم وحده — يؤخذ من «مدير الأحداث» في حساب ميتا التجاري. والصق القصاصة كاملة هنا لا يعمل: الرقم منها فقط. واتركه فارغا لإطفاء البكسل كله.'); ?>
            </span>
        </div>

        <?php /* الربط البرمجي في القسم نفسه لا في شاشة أخرى: من يفتح
                 «بكسل ميتا» يفتحها ليقيس، والقياس نصفان. */ ?>
        <h3 class="tqa-formsec" style="margin-block:var(--tq-space-xl) var(--tq-space-m)">
            <?php echo t('الربط البرمجي (Conversions API)'); ?>
        </h3>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_token"><?php echo t('رمز الوصول (Access Token)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="password" id="f_token"
                   name="tq_meta_capi_token" dir="ltr" autocomplete="off" spellcheck="false"
                   placeholder="<?php echo $tq_capi ? te('محفوظ — اتركه فارغا فلا يمس') : 'EAAG...'; ?>">
            <span class="tqa-field__hint">
                <?php echo t('من «مدير الأحداث» ← البكسل ← الإعدادات ← Conversions API ← إنشاء رمز وصول. وهو سر: يخزن في القاعدة ولا يظهر بعد حفظه.'); ?>
                <?php if ($tq_capi): ?>
                    <br><?php echo t('والفارغ لا يمسه — فتصحيح المعرف أعلاه لا يمحو الربط.'); ?>
                <?php endif; ?>
            </span>
        </div>

        <?php if ($tq_capi): ?>
        <div class="tqa-field">
            <label class="tqa-check">
                <input type="checkbox" name="tq_meta_capi_clear" value="1">
                <span><?php echo t('امسح رمز الوصول وأوقف قياس الشراء من الخادم.'); ?></span>
            </label>
        </div>
        <?php endif; ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_test"><?php echo t('رمز اختبار الأحداث (اختياري)'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_test"
                   name="tq_meta_test_code" dir="ltr" maxlength="32"
                   autocomplete="off" spellcheck="false" placeholder="TEST12345"
                   value="<?php echo html_escape($tq_test); ?>">
            <span class="tqa-field__hint">
                <?php echo t('يوجه الأحداث إلى تبويب «اختبار الأحداث» فتقرأ في ثوان بدل دقائق. واتركه فارغا في التشغيل الحقيقي — وإلا مضت مبيعات حقيقية إلى الاختبار ولم تحسب في أي حملة.'); ?>
            </span>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ'); ?>
            </button>
        </div>
    </form>
</div>

<?php /* التحقق يذكر لأن رسالة «حفظ» لا تثبت أن ميتا استقبلت شيئا:
         المعرف قد يكون صحيح الشكل ويخص حسابا آخر، والرقم يبدو معقولا. */ ?>
<div class="tqa-card tqa-section" style="max-inline-size:720px">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('shield', 20); ?></span>
        <h2><?php echo t('كيف تتأكد أنه يصل فعلا'); ?></h2>
    </div>
    <ol class="tqa-steps">
        <li><?php echo t('ثبت إضافة'); ?> <strong>Meta Pixel Helper</strong> <?php echo t('في متصفح كروم.'); ?></li>
        <li><?php echo t('افتح'); ?> <a href="<?php echo base_url(); ?>" target="_blank" rel="noopener"><?php echo t('الصفحة الرئيسية'); ?></a>
            <?php echo t('في نافذة خفية، وانقر أيقونة الإضافة: يجب أن تظهر'); ?>
            <strong>PageView</strong> <?php echo t('بالمعرف نفسه أعلاه.'); ?></li>
        <li><?php echo t('وفي «مدير الأحداث» عند ميتا يظهر النشاط خلال دقائق — لا فورا، فلا يستعجل الحكم بالفشل.'); ?></li>
        <li><?php echo t('وللربط البرمجي: اضغط «اسأل ميتا» أدناه. يسألها عن البكسل بالرمز المحفوظ، فيثبت أن الرمز صحيح وأنه يملك هذا البكسل بعينه — وهو الخطأ الأكثر وقوعا: رمز سليم لحساب آخر، تقبله ميتا ولا يظهر منه حدث.'); ?></li>
    </ol>

    <form method="post" action="<?php echo site_url('taqdar_admin/tracking_probe'); ?>"
          style="margin-block-start:var(--tq-space-l)">
        <?php echo tq_csrf(); ?>
        <button type="submit" class="tqa-btn tqa-btn--ghost">
            <?php echo tq_icon('shield', 16); ?> <?php echo t('اسأل ميتا'); ?>
        </button>
    </form>

    <p class="tqa-hint">
        <?php echo t('وزائر ضغط'); ?> <strong><?php echo t('«رفض غير الضروري»'); ?></strong> <?php echo t('في شريط ملفات الارتباط لا يحمل له البكسل أصلا، ولا يرسل الخادم شراءه كذلك — فالرفض له أثر في الجهتين، ولذلك لن يظهر في تصفحه ولا في شرائه.'); ?>
    </p>
</div>

<?php /* سجل ما أرسل: «هل وصل الشراء؟» يجاب من صف لا من ظن — وهو أول ما
         يسأل حين يقول فريق الإعلان «المبيعات أكثر مما يظهر عندنا». */ ?>
<div class="tqa-card tqa-card--flush tqa-section">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('chart', 20); ?></span>
        <h2><?php echo t('أحداث الشراء المرسلة من الخادم'); ?></h2>
        <span class="tqa-hint" style="margin-inline-start:auto">
            <?php echo t('وصل'); ?> <span class="tqa-num"><?php echo (int) ($tq_tot['sent'] ?? 0); ?></span> ·
            <?php echo t('فشل'); ?> <span class="tqa-num"><?php echo (int) ($tq_tot['failed'] ?? 0); ?></span> ·
            <?php echo t('تخطي'); ?> <span class="tqa-num"><?php echo (int) ($tq_tot['skipped'] ?? 0); ?></span>
        </span>
    </div>

    <?php if (!$tq_evs): ?>
        <div class="tqa-empty">
            <span class="tqa-empty__icon" aria-hidden="true"><?php echo tq_icon('chart', 28); ?></span>
            <h3><?php echo t('لا حدث بعد'); ?></h3>
            <p><?php echo $tq_capi
                ? t('يكتب صف هنا عند سداد أول فاتورة. والصف يقيد ولو رفضته ميتا، فما يفشل يعاد من الكرون كل ربع ساعة.')
                : t('لا يرسل الخادم شيئا حتى يحفظ رمز الوصول أعلاه.'); ?></p>
        </div>
    <?php else: ?>
        <?php
        $tq_tone = array('sent' => 'ok', 'failed' => 'danger', 'skipped' => 'muted', 'sending' => 'info');
        $tq_word = array('sent' => t('وصل'), 'failed' => t('فشل'),
                         'skipped' => t('تخطي'), 'sending' => t('يرسل'));
        ?>
        <div class="tqa-table__wrap">
            <table class="tqa-table">
                <thead>
                    <tr>
                        <th><?php echo t('الفاتورة'); ?></th>
                        <th><?php echo t('المشتري'); ?></th>
                        <th><?php echo t('القيمة'); ?></th>
                        <th><?php echo t('المصدر'); ?></th>
                        <th><?php echo t('الحال'); ?></th>
                        <th><?php echo t('متى'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tq_evs as $e):
                    $st = (string) $e['status']; ?>
                    <tr>
                        <td data-label="<?php echo te('الفاتورة'); ?>">
                            <span class="tqa-num" dir="ltr"><?php echo html_escape((string) ($e['invoice_no'] ?: '#' . (int) $e['invoice_id'])); ?></span>
                        </td>
                        <td data-label="<?php echo te('المشتري'); ?>">
                            <?php echo html_escape(trim((string) $e['user_name']) ?: '—'); ?>
                        </td>
                        <td data-label="<?php echo te('القيمة'); ?>">
                            <span class="tqa-num"><?php echo number_format(((int) $e['value']) / 100, 2); ?></span>
                            <?php echo html_escape((string) $e['currency']); ?>
                        </td>
                        <td data-label="<?php echo te('المصدر'); ?>">
                            <span class="tqa-num" dir="ltr"><?php echo html_escape((string) $e['source']); ?></span>
                        </td>
                        <td data-label="<?php echo te('الحال'); ?>">
                            <span class="tqa-badge tqa-badge--<?php echo $tq_tone[$st] ?? 'muted'; ?>">
                                <?php echo $tq_word[$st] ?? html_escape($st); ?>
                            </span>
                            <?php /* السبب يعرض دائما لا عند الفشل وحده: «تخطي»
                                     بلا سببه يقرأ عطلا، وهو قد يكون رفض ارتباط. */ ?>
                            <?php if (!empty($e['note'])): ?>
                                <span class="tqa-status__why"><?php echo html_escape((string) $e['note']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php echo te('متى'); ?>">
                            <span class="tqa-num" dir="ltr"><?php echo html_escape((string) ($e['sent_at'] ?: $e['created_at'])); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php /* والأحداث التي بقيت: البكسل يقيس الزيارة، والخادم يقيس الشراء،
         وما بينهما (بدء الدفع · التسجيل) يطبع في قوالبه. */ ?>
<div class="tqa-note tqa-section" style="max-inline-size:720px">
    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
    <span>
        <?php echo t('حدث'); ?> <strong>InitiateCheckout</strong>
        <?php echo t('يطلق في شاشات التأكيد الثلاث (باقة · كورس · كتاب)، و'); ?><strong>Purchase</strong>
        <?php echo t('يطلق من الخادم عند السداد ومن المتصفح عند العودة — وميتا تطرح المكرر بمعرف الحدث فيعد شراء واحدا. وما بقي (تسجيل حساب) يضاف بالدالة'); ?>
        <code>tq_meta_track()</code><?php echo t('.'); ?>
    </span>
</div>
