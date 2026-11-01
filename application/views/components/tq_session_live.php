<?php
/**
 * TQ-LIVE — باب الحصة في بطاقتها: الدخول والعد التنازلي والتقويم واعادة الدفع.
 *
 * مشترك بين «حصص بالطلب» و«التأسيس» وشاشة المعلم، فلا تفترق ثلاث نسخ.
 * والحالة من `display_state()` وحدها (`$tq_live['state']`).
 *
 * المدخلات: `$tq_live` (صف من `bookings_for_student()` او
 * `requests_for_teacher()`) و`$tq_live_role` (student|teacher).
 *
 * والزر يقود الى `/<الدور>/sessions/<id>/join` لا الى رابط اللقاء: الخادم
 * يفحص الوقت ويختم الحضور ثم يحول. والعد يحسب من ساعة الخادم
 * (`data-tq-now`)، فساعة جهاز خاطئة لا تفتح زرا مبكرا — والحارس الحقيقي
 * `join_state()` عند النقر.
 */
$tq_lv    = $tq_live;
$tq_lr    = ($tq_live_role ?? 'student') === 'teacher' ? 'teacher' : 'student';
$tq_st    = $tq_lv['state'] ?? array('key' => '', 'button' => '', 'deadline_ts' => 0);
$tq_sid   = (int) $tq_lv['id'];
$tq_join  = base_url($tq_lr . '/sessions/' . $tq_sid . '/join');
$tq_opens = (int) ($tq_lv['opens_at'] ?? 0);
$tq_live_keys = array('scheduled', 'ready', 'live', 'rescheduled');
$tq_confirmed = in_array($tq_lv['status'], array('confirmed', 'live'), true);
?>
<div class="tq-live" data-tq-now="<?php echo time(); ?>" style="margin-block-start:var(--tq-space-s)">
    <?php if ($tq_st['key'] === 'pending_payment' && (int) $tq_st['deadline_ts'] > 0): ?>
        <p class="tq-micro" style="margin:0 0 var(--tq-space-xs)">
            <?php echo t('مهلة الدفع:'); ?>
            <strong data-tq-deadline="<?php echo (int) $tq_st['deadline_ts']; ?>"></strong>
        </p>
    <?php endif; ?>

    <?php if ($tq_st['key'] === 'payment_failed' && (int) ($tq_lv['invoice_id'] ?? 0) > 0): ?>
        <p class="tq-micro" style="margin:0 0 var(--tq-space-xs)">
            <?php echo t('لم يتم الدفع. الموعد ما زال محجوزا لك حتى تنتهي المهلة.'); ?>
        </p>
        <a class="tq-btn tq-btn--primary tq-btn--sm tq-btn--block"
           href="<?php echo base_url('pay/' . (int) $tq_lv['invoice_id']); ?>">
            <?php echo t('أعد محاولة الدفع'); ?>
        </a>
    <?php endif; ?>

    <?php if ($tq_confirmed && in_array($tq_st['key'], $tq_live_keys, true)): ?>
        <?php if ($tq_lv['can_join']): ?>
            <a class="tq-btn tq-btn--mastery tq-btn--sm tq-btn--block" href="<?php echo $tq_join; ?>"
               target="_blank" rel="noopener">
                <?php echo tq_icon('video', 16); ?>
                <?php echo $tq_st['key'] === 'live' ? t('ادخل الحصة الجارية') : t('ادخل الحصة'); ?>
                <span class="tq-sr"><?php echo t('— يفتح في نافذة جديدة'); ?></span>
            </a>
        <?php elseif ($tq_opens > time()): ?>
            <p class="tq-micro" style="margin:0" data-tq-opens="<?php echo $tq_opens; ?>"
               data-tq-join="<?php echo $tq_join; ?>"
               data-tq-join-label="<?php echo te('ادخل الحصة'); ?>">
                <?php echo t('يفتح بعد'); ?> <strong data-tq-starts="<?php echo $tq_opens; ?>"></strong>
                · <time data-tq-local="<?php echo (int) ($tq_lv['starts_ts'] ?? 0); ?>"></time>
            </p>
        <?php endif; ?>

        <?php if ((int) ($tq_lv['starts_ts'] ?? 0) > time()): ?>
            <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                <?php echo t('أضف للتقويم:'); ?>
                <a href="<?php echo base_url($tq_lr . '/sessions/' . $tq_sid . '/ics'); ?>"><?php echo t('ملف تقويم'); ?></a>
                <?php if (!empty($tq_lv['gcal_url'])): ?>
                    · <a href="<?php echo html_escape($tq_lv['gcal_url']); ?>" target="_blank" rel="noopener noreferrer">Google</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
