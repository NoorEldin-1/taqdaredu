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

    <?php /* ── TQ-LIVE المرحلة الثانية والثالثة ───────────────────────────
             التاجيل والالغاء بسبب وخطا المعلم والحضور والتسجيل. الازرار
             تظهر بحكم الخادم (`live_extra()` و`display_state()`)، والحارس
             الحقيقي في النموذج عند الارسال. */
    $tq_back_in = ((string) ($tq_lv['kind'] ?? '') === 'foundation')
        ? '<input type="hidden" name="back" value="foundation"><input type="hidden" name="track_id" value="' . (int) ($tq_lv['track_id'] ?? 0) . '">'
        : '';
    $tq_btn  = (string) ($tq_st['button'] ?? '');
    $tq_opts = null;
    $tq_need_opts = !empty($tq_lv['can_reschedule']) || in_array($tq_btn, array('teacher_fault', 'teacher_no_show'), true);
    if ($tq_need_opts) {
        $tq_opts = $this->taqdar_sessions_model->reschedule_options($tq_sid, $tq_lr, (int) $this->session->userdata('user_id'));
    }
    $tq_slot_select = function ($id) use ($tq_opts) {
        $h = '<label class="tq-sr" for="' . $id . '">' . t('الموعد الجديد') . '</label>'
           . '<select class="tq-input" id="' . $id . '" name="slot_id" required>';
        foreach ((array) $tq_opts as $o) {
            $h .= '<option value="' . (int) $o['id'] . '">' . html_escape($o['when_text']) . '</option>';
        }
        return $h . '</select>';
    };
    ?>

    <?php if ($tq_lr === 'student' && in_array($tq_btn, array('teacher_fault', 'teacher_no_show'), true)): ?>
        <div class="tq-note" style="margin-block-start:var(--tq-space-s)">
            <p class="tq-micro" style="margin:0 0 var(--tq-space-xs)">
                <?php echo $tq_btn === 'teacher_no_show'
                    ? t('لم يدخل المعلم بعد عشرين دقيقة من الموعد. اختر موعدا بديلا معه بلا رسوم، أو أبلغ الإدارة لتراجع طلب الاسترداد.')
                    : t('اعتذر المعلم عن الحصة. اختر موعدا بديلا معه بلا رسوم، أو الاسترداد.'); ?>
            </p>
            <form method="post" action="<?php echo base_url('student/sessions/teacher-fault'); ?>" class="tq-form-inline">
                <?php echo tq_csrf(); ?><?php echo $tq_back_in; ?>
                <input type="hidden" name="session_id" value="<?php echo $tq_sid; ?>">
                <?php if ($tq_opts): ?>
                    <?php echo $tq_slot_select('tq-tf-' . $tq_sid); ?>
                    <button class="tq-btn tq-btn--primary tq-btn--sm" type="submit" name="choice" value="reschedule"><?php echo t('ثبت الموعد البديل'); ?></button>
                <?php else: ?>
                    <p class="tq-micro" style="margin:0"><?php echo t('لا مواعيد مفتوحة لهذا المعلم الآن.'); ?></p>
                <?php endif; ?>
            </form>
            <form method="post" action="<?php echo base_url('student/sessions/teacher-fault'); ?>" class="tq-form-inline"
                  style="margin-block-start:var(--tq-space-xs)"
                  data-tq-confirm-title="<?php echo te('طلب الاسترداد؟'); ?>"
                  data-tq-confirm="<?php echo $tq_btn === 'teacher_no_show'
                      ? te('يصل بلاغك إلى الإدارة فتراجعه وتخبرك بقرارها.')
                      : te('تغلق الحصة، ويرد مبلغها خلال ٥ أيام عمل خارج المنصة.'); ?>"
                  data-tq-confirm-ok="<?php echo te('اطلب الاسترداد'); ?>">
                <?php echo tq_csrf(); ?><?php echo $tq_back_in; ?>
                <input type="hidden" name="session_id" value="<?php echo $tq_sid; ?>">
                <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit" name="choice" value="refund"><?php echo $tq_btn === 'teacher_no_show'
                    ? t('أبلغ الإدارة واطلب الاسترداد') : t('أريد الاسترداد'); ?></button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (!empty($tq_lv['can_reschedule']) && $tq_opts): ?>
        <details style="margin-block-start:var(--tq-space-s)">
            <summary class="tq-micro"><?php echo $tq_lr === 'student'
                ? t('تأجيل الحصة (مرة واحدة)') : t('تأجيل الحصة'); ?></summary>
            <form method="post" action="<?php echo base_url($tq_lr . '/sessions/reschedule'); ?>" class="tq-form-inline"
                  style="margin-block-start:var(--tq-space-xs)">
                <?php echo tq_csrf(); ?><?php echo $tq_back_in; ?>
                <input type="hidden" name="session_id" value="<?php echo $tq_sid; ?>">
                <?php echo $tq_slot_select('tq-rs-' . $tq_sid); ?>
                <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit"><?php echo t('أجل إلى هذا الموعد'); ?></button>
            </form>
            <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0"><?php echo t('السعر والفاتورة لا يتغيران.'); ?></p>
        </details>
    <?php endif; ?>

    <?php if ($tq_lr === 'student' && ($tq_lv['cancel_refund'] ?? '') === 'refund'): ?>
        <form method="post" action="<?php echo base_url('student/sessions/cancel'); ?>" class="tq-form-inline"
              style="margin-block-start:var(--tq-space-xs)"
              data-tq-confirm-title="<?php echo te('إلغاء الحصة واسترداد مبلغها؟'); ?>"
              data-tq-confirm="<?php echo te('يعود الموعد متاحا لغيرك، ويرد مبلغها خلال ٥ أيام عمل خارج المنصة.'); ?>"
              data-tq-confirm-ok="<?php echo te('ألغ واسترد'); ?>" data-tq-confirm-tone="danger">
            <?php echo tq_csrf(); ?><?php echo $tq_back_in; ?>
            <input type="hidden" name="session_id" value="<?php echo $tq_sid; ?>">
            <button class="tq-btn tq-btn--ghost tq-btn--sm tq-btn--block" type="submit"><?php echo t('إلغاء واسترداد'); ?></button>
        </form>
    <?php elseif ($tq_lr === 'student' && ($tq_lv['cancel_refund'] ?? '') === 'no_refund'): ?>
        <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
            <?php echo t('قرب الموعد فلا استرداد عند الإلغاء — وقت المعلم محجوز لك.'); ?>
            <?php if (!empty($tq_lv['can_reschedule'])) echo t('ويبقى لك التأجيل مرة واحدة.'); ?>
        </p>
    <?php endif; ?>

    <?php if ($tq_lr === 'teacher' && in_array($tq_lv['status'], array('confirmed', 'awaiting_payment'), true)
              && (int) ($tq_lv['starts_ts'] ?? 0) > time()): ?>
        <details style="margin-block-start:var(--tq-space-s)">
            <summary class="tq-micro"><?php echo t('إلغاء الحصة'); ?></summary>
            <form method="post" action="<?php echo base_url('teacher/sessions/cancel'); ?>"
                  style="margin-block-start:var(--tq-space-xs)"
                  data-tq-confirm-title="<?php echo te('إلغاء هذه الحصة؟'); ?>"
                  data-tq-confirm="<?php echo te('يصل الطالب السبب، ويختار موعدا بديلا معك أو الاسترداد.'); ?>"
                  data-tq-confirm-ok="<?php echo te('ألغ الحصة'); ?>" data-tq-confirm-tone="danger">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="session_id" value="<?php echo $tq_sid; ?>">
                <label class="tq-micro" for="tq-cr-<?php echo $tq_sid; ?>"><?php echo t('سبب الإلغاء (مطلوب، يقرؤه الطالب)'); ?></label>
                <textarea class="tq-input" id="tq-cr-<?php echo $tq_sid; ?>" name="reason" rows="2" maxlength="255" required></textarea>
                <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit"><?php echo t('ألغ الحصة'); ?></button>
            </form>
        </details>
    <?php endif; ?>

    <?php if ($tq_lv['status'] === 'completed'): ?>
        <?php if (($tq_lv['attendance'] ?? null) !== null): ?>
            <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                <?php echo $tq_lv['attendance'] === 'present' ? t('الحضور: حضر') : t('الحضور: لم يحضر'); ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($tq_lv['has_recording']) && $tq_lr === 'student'): ?>
            <a class="tq-btn tq-btn--secondary tq-btn--sm tq-btn--block" style="margin-block-start:var(--tq-space-xs)"
               href="<?php echo base_url('student/sessions/' . $tq_sid . '/recording'); ?>">
                <?php echo tq_icon('video', 16); ?> <?php echo t('شاهد التسجيل'); ?></a>
        <?php elseif (!empty($tq_lv['recording_pending'])): ?>
            <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0"><?php echo t('جاري تجهيز التسجيل'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
