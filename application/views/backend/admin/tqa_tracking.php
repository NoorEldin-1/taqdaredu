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
 */
$tq_saved   = get_settings('tq_meta_pixel_id');   // NULL = لم يضبط قط
$tq_live    = tq_meta_pixel_id();                 // المعرف الفعال بعد الافتراضي
$tq_on      = $tq_live !== '';
$tq_default = ($tq_saved === null);
?>

<?php tqa_head('بكسل ميتا', 'قياس زيارات الموقع لحملات فيسبوك وإنستغرام.', 'chart'); ?>

<?php /* الحال قبل الحقل: من يفتح الشاشة يريد أن يعرف أولا هل يقاس شيء الآن. */ ?>
<div class="tqa-note <?php echo $tq_on ? '' : 'tqa-note--warn'; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($tq_on ? 'check-badge' : 'alert', 18); ?></span>
    <span>
        <?php if ($tq_on): ?>
            <strong>يعمل الآن</strong> بالمعرف <?php echo tq_num($tq_live); ?> —
            على صفحات الموقع العام وبوابات الطالب والمعلم وولي الأمر جميعا،
            ولا يحمل في لوحة الإدارة.
            <?php if ($tq_default): ?>
                وهو المعرف الافتراضي المكتوب في الشيفرة؛ الحفظ من هنا يعلو عليه.
            <?php endif; ?>
        <?php else: ?>
            <strong>مطفأ.</strong> لا يحمل سكربت ميتا في أي صفحة، ولا تقاس أي زيارة.
        <?php endif; ?>
    </span>
</div>

<div class="tqa-card" style="max-inline-size:720px">
    <form method="post" action="<?php echo site_url('taqdar_admin/tracking_save'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_pixel">معرف البكسل (Pixel ID)</label>
            <input class="tqa-input tqa-input--ltr" type="text" id="f_pixel"
                   name="tq_meta_pixel_id" dir="ltr" inputmode="numeric"
                   maxlength="24" autocomplete="off" spellcheck="false"
                   placeholder="1616041446836504"
                   value="<?php echo html_escape($tq_saved === null ? $tq_live : $tq_saved); ?>">
            <span class="tqa-field__hint">
                رقم وحده — يؤخذ من «مدير الأحداث» في حساب ميتا التجاري.
                والصق القصاصة كاملة هنا لا يعمل: الرقم منها فقط.
                واتركه فارغا لإطفاء البكسل كله.
            </span>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> احفظ
            </button>
        </div>
    </form>
</div>

<?php /* التحقق يذكر لأن رسالة «حفظ» لا تثبت أن ميتا استقبلت شيئا:
         المعرف قد يكون صحيح الشكل ويخص حسابا آخر، والرقم يبدو معقولا. */ ?>
<div class="tqa-card tqa-section" style="max-inline-size:720px">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('shield', 20); ?></span>
        <h2>كيف تتأكد أنه يصل فعلا</h2>
    </div>
    <ol class="tqa-steps">
        <li>ثبت إضافة <strong>Meta Pixel Helper</strong> في متصفح كروم.</li>
        <li>افتح <a href="<?php echo base_url(); ?>" target="_blank" rel="noopener">الصفحة الرئيسية</a>
            في نافذة خفية، وانقر أيقونة الإضافة: يجب أن تظهر
            <strong>PageView</strong> بالمعرف نفسه أعلاه.</li>
        <li>وفي «مدير الأحداث» عند ميتا يظهر النشاط خلال دقائق —
            لا فورا، فلا يستعجل الحكم بالفشل.</li>
    </ol>
    <p class="tqa-hint">
        وزائر ضغط <strong>«رفض غير الضروري»</strong> في شريط ملفات الارتباط
        لا يحمل له البكسل أصلا — فالرفض له أثر، ولذلك لن يظهر في تصفحه.
    </p>
</div>

<?php /* الأحداث تذكر لأنها السؤال التالي دائما: البكسل وحده يقيس
         الزيارة لا الشراء، ومن يبني حملة تحويل يحتاج الثاني. */ ?>
<div class="tqa-note tqa-section" style="max-inline-size:720px">
    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
    <span>
        هذه الشاشة تضبط <strong>زيارة الصفحة</strong> وحدها.
        أحداث التحويل (شراء باقة · تسجيل حساب · بدء دفع) تضاف في قوالبها
        بالدالة <code>tq_meta_track()</code> — وهي جاهزة، ولم يفعل منها
        شيء بعد.
    </span>
</div>
