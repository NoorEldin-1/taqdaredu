<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * تطبيق الجوال — TQ-APP-CONFIG.
 *
 * ما يقرؤه التطبيق من `api/v1/app/config` عند كل تشغيل، فيبدل من هنا
 * بلا إصدار جديد. والقرار في `Taqdar_mobile_model`: الشاشة تعرض وترد
 * ما كتب.
 */
$c         = $tq_cfg;
$tq_review = $c['tq_mobile_ios_review'];
$tq_cash   = $c['tq_mobile_review_pay'] === 'cash';
$tq_closed = $c['tq_mobile_force_close'] === '1';
$tq_no_sales = $c['tq_mobile_ios_no_sales'] === '1';

$tq_platforms = array(
    'android' => t('أندرويد'),
    'ios'     => 'iOS',
);
?>

<?php tqa_head(t('تطبيق الجوال'),
    t('يقرؤها التطبيق من الخادم عند كل تشغيل، فتسري بلا إصدار جديد.'),
    'package'); ?>

<?php /* الحال قبل الحقول: إغلاق قائم أو إصدار تحت المراجعة يغيران ما يراه
         الناس الآن، ومن يفتح الشاشة يريد أن يعرف ذلك أولا. */ ?>
<?php if ($tq_closed): ?>
    <div class="tqa-note tqa-note--warn tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo t('التطبيق مغلق الآن على كل المستخدمين.'); ?></strong>
            <?php echo t('لا يفتح على أي منصة حتى يطفأ «إغلاق إجباري».'); ?>
        </span>
    </div>
<?php endif; ?>

<div class="tqa-note <?php echo $tq_review !== '' ? 'tqa-note--warn' : ''; ?> tqa-section">
    <span aria-hidden="true"><?php echo tq_icon($tq_review !== '' ? 'alert' : 'check-badge', 18); ?></span>
    <span>
        <?php if ($tq_review !== ''): ?>
            <strong>
                <?php echo $tq_cash
                    ? t('إصدار iOS ____ يدفع الآن كاش وحده.', html_escape($tq_review))
                    : t('إصدار iOS ____ يعمل الآن بلا دفع.', html_escape($tq_review)); ?>
            </strong>
            <?php echo t('امسح الحقل فور قبول أبل: بعد النشر يحمل الناس الإصدار نفسه، فيبقون بلا بطاقة ولا تحويل ما دام مكتوبا.'); ?>
        <?php elseif ($tq_no_sales): ?>
            <?php echo t('الشراء موقوف على كل إصدارات iOS، والحصص الخاصة تدفع بالبطاقة. أندرويد يدفع كما في الموقع.'); ?>
        <?php else: ?>
            <?php echo t('لا إصدار تحت المراجعة: كل نسخ التطبيق تدفع بالبطاقة والتحويل البنكي كما في الموقع.'); ?>
        <?php endif; ?>
    </span>
</div>

<form class="tqa-card" style="max-inline-size:880px" method="post"
      action="<?php echo site_url('taqdar_admin/mobile_save'); ?>">
    <?php echo tq_csrf(); ?>

    <div class="tqa-fieldgrid">
        <p class="tqa-formsec" style="grid-column:1/-1"><?php echo t('الإصدارات والتحديث'); ?></p>

        <?php foreach ($tq_platforms as $tq_p => $tq_label): ?>
            <div class="tqa-field">
                <label class="tqa-field__label" for="f_<?php echo $tq_p; ?>_version">
                    <?php echo t('آخر إصدار (____)', $tq_label); ?>
                </label>
                <input class="tqa-input tqa-input--ltr" type="text" dir="ltr"
                       id="f_<?php echo $tq_p; ?>_version" name="tq_mobile_<?php echo $tq_p; ?>_version"
                       value="<?php echo html_escape($c['tq_mobile_' . $tq_p . '_version']); ?>"
                       placeholder="1.2.3" autocomplete="off" spellcheck="false">
                <span class="tqa-field__hint"><?php echo t('المنشور في المتجر الآن. ما هو أقدم منه يقترح عليه التحديث.'); ?></span>
            </div>
        <?php endforeach; ?>

        <?php foreach ($tq_platforms as $tq_p => $tq_label): ?>
            <div class="tqa-field">
                <label class="tqa-field__label" for="f_<?php echo $tq_p; ?>_store">
                    <?php echo t('رابط المتجر (____)', $tq_label); ?>
                </label>
                <input class="tqa-input tqa-input--ltr" type="url" dir="ltr"
                       id="f_<?php echo $tq_p; ?>_store" name="tq_mobile_<?php echo $tq_p; ?>_store"
                       value="<?php echo html_escape($c['tq_mobile_' . $tq_p . '_store']); ?>"
                       placeholder="https://" autocomplete="off" spellcheck="false">
                <span class="tqa-field__hint"><?php echo t('يفتحه زر «حدث الآن» في التطبيق. والتحديث الإجباري يحتاجه.'); ?></span>
            </div>
        <?php endforeach; ?>

        <?php foreach ($tq_platforms as $tq_p => $tq_label): ?>
            <div class="tqa-field">
                <span class="tqa-field__label"><?php echo t('تحديث إجباري (____)', $tq_label); ?></span>
                <label class="tqa-switch">
                    <input type="checkbox" name="tq_mobile_<?php echo $tq_p; ?>_force" value="1"
                           <?php echo $c['tq_mobile_' . $tq_p . '_force'] === '1' ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                    <span><?php echo t('نعم'); ?></span>
                </label>
                <span class="tqa-field__hint"><?php echo t('ما هو أقدم من آخر إصدار لا يفتح حتى يحدث.'); ?></span>
            </div>
        <?php endforeach; ?>

        <div class="tqa-field tqa-field--full">
            <span class="tqa-field__label"><?php echo t('إغلاق إجباري'); ?></span>
            <label class="tqa-switch">
                <input type="checkbox" name="tq_mobile_force_close" value="1" <?php echo $tq_closed ? 'checked' : ''; ?>>
                <span class="tqa-switch__track" aria-hidden="true"></span>
                <span><?php echo t('نعم'); ?></span>
            </label>
            <span class="tqa-field__hint"><?php echo t('يغلق التطبيق على كل المنصات وكل الإصدارات. الموقع لا يتأثر.'); ?></span>
        </div>

        <p class="tqa-formsec" style="grid-column:1/-1"><?php echo t('مراجعة أبل'); ?></p>

        <div class="tqa-field tqa-field--full">
            <span class="tqa-field__label"><?php echo t('إيقاف الشراء على iOS لكل الإصدارات'); ?></span>
            <label class="tqa-switch">
                <input type="checkbox" name="tq_mobile_ios_no_sales" value="1" <?php echo $tq_no_sales ? 'checked' : ''; ?>>
                <span class="tqa-switch__track" aria-hidden="true"></span>
                <span><?php echo t('نعم'); ?></span>
            </label>
            <span class="tqa-field__hint">
                <?php echo t('لا باقة ولا كورس ولا كتاب يشترى من تطبيق iOS، والطالب يجد فيه ما اشتراه من الموقع. والحصص الخاصة مع المعلم تبقى تدفع بالبطاقة. وهو الآمن عند أبل: إظهار الدفع بعد القبول يعد إخفاء عن المراجع (2.3.1).'); ?>
            </span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_ios_review"><?php echo t('إصدار iOS تحت مراجعة أبل'); ?></label>
            <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" id="f_ios_review"
                   name="tq_mobile_ios_review" value="<?php echo html_escape($tq_review); ?>"
                   placeholder="1.2.4" autocomplete="off" spellcheck="false">
            <span class="tqa-field__hint">
                <?php echo t('كما في App Store Connect. اتركه فارغا إن لم يكن إصدار تحت المراجعة. و«1.2.4 (9)» يقصر الحكم على ذلك البناء.'); ?>
            </span>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="f_review_pay"><?php echo t('وسيلة الدفع أثناء مراجعة أبل'); ?></label>
            <select class="tqa-select" id="f_review_pay" name="tq_mobile_review_pay">
                <option value="cash" <?php echo $tq_cash ? 'selected' : ''; ?>><?php echo t('كاش'); ?></option>
                <option value="none" <?php echo $tq_cash ? '' : 'selected'; ?>><?php echo t('بلا دفع'); ?></option>
            </select>
            <span class="tqa-field__hint">
                <?php echo t('لهذا الإصدار وحده، ولا بطاقة ولا تحويل معها. والكاش يصدر فاتورته ويفعله المسؤول من «الاشتراكات» بعد الاستلام.'); ?>
            </span>
        </div>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> <?php echo t('احفظ'); ?>
        </button>
    </div>
</form>
