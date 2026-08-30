<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * أرقام الموقع — ما يعرض على الصفحات العامة.
 *
 * **ما يترك فارغا لا يعرض** على الموقع: بنده يختفي كله. وهذا مقصود —
 * «٠ طالب» ادعاء معكوس، وغياب البند أصدق منه.
 *
 * وحقولها كانت عارية بلا صنف مثل [tqa_bank.php] — انظر TQ-BARE-FIELD
 * هناك.
 */
/**
 * الحقول وأين يظهر كل واحد **فعلا**.
 *
 * TQ-STAT-ORPHAN — كان الشرح هنا يعد بمواضع لا وجود لها: «الطلاب» و
 * «الدروس» و«ساعات التعلم» و«مستوى الرضا» أربعة حقول **لا يعرضها أي
 * قالب**، وشرحها يقول «يظهر في: الطلاب · من نحن». فيملؤها المسؤول
 * ويحفظ، ويفتح الصفحة فلا يجد شيئا، ولا يعرف أالخطأ في قيمته أم في
 * الحفظ. وصلت الأربعة بمواضعها في القوالب، وضبط الشرح على ما يجري.
 *
 * وأي حقل يضاف هنا يجب أن يقابله نداء `tqs_stat()` في قالب — وإلا عاد
 * الوعد كاذبا.
 */
$tq_fields = array(
    'students' => array(t('طلاب'),            t('من نحن · الطلاب · المعلمون'), 'users'),
    'teachers' => array(t('معلمون'),          t('من نحن · المعلمون'),          'graduation'),
    'paths'    => array(t('مسارات تعليمية'),  t('من نحن · الطلاب · المعلمون'), 'target'),
    'subjects' => array(t('مواد دراسية'),     t('من نحن · الطلاب · المعلمون'), 'layers'),
    'lessons'  => array(t('دروس'),            t('من نحن'),                     'play'),
    'books'    => array(t('كتب'),             t('من نحن · الطلاب'),            'book'),
    'hours'    => array(t('ساعات تعلم'),      t('الطلاب'),                     'clock'),
    'rating'   => array(t('مستوى الرضا'),     t('الطلاب · المعلمون'),          'star'),
);
?>

<?php tqa_head(t('أرقام الموقع'),
    t('تظهر في أشرطة الأرقام على الصفحات العامة كما تكتبها تماما — بعلاماتها ولواحقها.'),
    'chart'); ?>

<div class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
    <span>
        <?php echo t('اكتب الرقم بصيغته المعروضة:'); ?>
        <span class="tq-ltr" dir="ltr">+500</span> ·
        <span class="tq-ltr" dir="ltr">550K</span> ·
        <span class="tq-ltr" dir="ltr">98%</span>.
        <strong><?php echo t('وما تتركه فارغا لا يعرض إطلاقا'); ?></strong> <?php echo t('— بنده يختفي من الشريط ولا يظهر صفرا.'); ?>
    </span>
</div>

<div class="tqa-card" style="max-inline-size:820px">
    <form method="post" action="<?php echo site_url('taqdar_admin/stats_save'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-fieldgrid">
            <?php foreach ($tq_fields as $tq_k => $tq_m): ?>
                <div class="tqa-field">
                    <label class="tqa-field__label" for="s_<?php echo $tq_k; ?>">
                        <?php echo html_escape($tq_m[0]); ?>
                    </label>
                    <input class="tqa-input tqa-input--ltr" type="text" id="s_<?php echo $tq_k; ?>"
                           name="<?php echo $tq_k; ?>" dir="ltr" autocomplete="off"
                           value="<?php echo html_escape(get_settings('taqdar_stat_' . $tq_k)); ?>"
                           placeholder="<?php echo te('اتركه فارغا فلا يعرض'); ?>">
                    <span class="tqa-field__hint">
                        <?php if ($tq_m[1] !== ''): ?>
                            يظهر في: <?php echo html_escape($tq_m[1]); ?>
                        <?php else: ?>
                            لا صفحة تعرضه حاليا.
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('check', 16); ?> احفظ الأرقام
            </button>
        </div>
    </form>
</div>
