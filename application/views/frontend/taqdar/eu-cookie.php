<?php
/**
 * شريط ملفات تعريف الارتباط.
 *
 * TQ-I18N — ونصه من `frontend_settings` لا من القالب، فيمر بـ`t()` عند
 * العرض: المفتاح هو النص كما كتبه المسؤول، فما له ترجمة في القاموس يخرج
 * بها، وما عدله المسؤول بعد ذلك يخرج كما كتبه حرفا بحرف. وهو مبدأ
 * `tq_text()` نفسه — القاعدة تعلو على القالب، والقالب لا يفقد.
 *
 * وهذا الشريط يطبع في **كل** صفحة بوابة (`index.php` يضمه قبل المحتوى)،
 * فبقاؤه عربيا كان يترك اثنتي عشرة كلمة عربية في أعلى كل شاشة إنجليزية —
 * وهي أول ما يقرؤه الزائر.
 */
$tq_note = get_frontend_settings('cookie_note');
?>
<div class="tq-cookie tq-card tq-card--float" data-tq-cookie hidden role="region" aria-label="<?php echo te('ملفات تعريف الارتباط'); ?>">
    <p class="tq-caption" style="margin:0 0 var(--tq-space-m)"><?php echo html_escape(t($tq_note)); ?></p>
    <div class="tq-row">
        <button class="tq-btn tq-btn--primary tq-btn--sm" type="button" data-tq-cookie-accept><?php echo t('موافق'); ?></button>
        <?php /* الرفض له أثر فعلي: يمحو تفضيل الوضع ويمنع كتابته. وبقاؤه
                 بلا أثر يجعل الشريط استئذانا صوريا. */ ?>
        <button class="tq-btn tq-btn--ghost tq-btn--sm" type="button" data-tq-cookie-deny><?php echo t('رفض غير الضروري'); ?></button>
        <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('privacy'); ?>"><?php echo t('سياسة الخصوصية'); ?></a>
    </div>
</div>
