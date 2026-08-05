<?php $tq_note = get_frontend_settings('cookie_note'); ?>
<div class="tq-cookie tq-card tq-card--float" data-tq-cookie hidden role="region" aria-label="ملفات تعريف الارتباط">
    <p class="tq-caption" style="margin:0 0 var(--tq-space-m)"><?php echo html_escape($tq_note); ?></p>
    <div class="tq-row">
        <button class="tq-btn tq-btn--primary tq-btn--sm" type="button" data-tq-cookie-accept>موافق</button>
        <?php /* الرفض له أثرٌ فعليّ: يمحو تفضيل الوضع ويمنع كتابته. وبقاؤه
                 بلا أثرٍ يجعل الشريط استئذانًا صوريًّا. */ ?>
        <button class="tq-btn tq-btn--ghost tq-btn--sm" type="button" data-tq-cookie-deny>رفض غير الضروريّ</button>
        <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('privacy'); ?>">سياسة الخصوصية</a>
    </div>
</div>
