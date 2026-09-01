<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إرسال إشعار.
 *
 * جدول `notifications` كان يكتب فيه النظام والمهام الدورية وحدها، ولا
 * باب إليه من اللوحة: فإخطار الطلاب بموعد امتحان أو بتوقف مؤقت للخدمة
 * لم يكن ممكنا إلا برسالة بريدية جماعية — تصل بعد ساعات وتقرأ نصفها.
 * والإشعار يظهر في جرس البوابة فورا.
 */
$audiences = array(
    'all'         => t('كل المستخدمين'),
    'students'    => t('الطلاب'),
    'teachers'    => t('المعلمون'),
    'parents'     => t('أولياء الأمور'),
    'subscribers' => t('المشتركون النشطون'),
);
?>

<?php tqa_head(t('إرسال إشعار'), t('رسالة تظهر في جرس البوابة فورا — لا بريد ينتظر.'), 'bell'); ?>

<div class="tqa-grid tqa-grid--2">

    <section class="tqa-card">
        <h2 style="margin-block-end:var(--tq-space-l)"><?php echo t('إشعار جديد'); ?></h2>

        <form action="<?php echo site_url('taqdar_admin/notify_send'); ?>" method="post">
            <?php echo tq_csrf(); ?>

            <label class="tqa-field">
                <span class="tqa-field__label"><?php echo t('إلى من'); ?> <span class="tqa-field__req">*</span></span>
                <select class="tqa-select" name="audience" required>
                    <?php foreach ($audiences as $k => $label): ?>
                        <option value="<?php echo $k; ?>">
                            <?php echo html_escape($label); ?>
                            (<?php echo (int) ($sizes[$k] ?? 0); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="tqa-field__hint">
                    <?php echo t('الحسابات المغلقة لا تشملها الفئات — إشعار لمن لا يستطيع الدخول لا يقرأ.'); ?>
                </span>
            </label>

            <label class="tqa-field">
                <span class="tqa-field__label"><?php echo t('العنوان'); ?> <span class="tqa-field__req">*</span></span>
                <input class="tqa-input" type="text" name="title" maxlength="120" required
                       placeholder="<?php echo te('مثال: صيانة المنصة مساء الخميس'); ?>">
                <span class="tqa-field__hint">
                    <?php echo t('هذا وحده ما يظهر في الجرس، فليكن كاملا بذاته — «تنبيه مهم» لا يخبر بشيء.'); ?>
                </span>
            </label>

            <label class="tqa-field">
                <span class="tqa-field__label"><?php echo t('النص'); ?> <span class="tqa-field__req">*</span></span>
                <textarea class="tqa-textarea" name="description" required rows="5"
                          placeholder="<?php echo te('التفاصيل التي يقرؤها من فتح الإشعار.'); ?>"></textarea>
            </label>

            <?php
            /* النسخة البريدية اختيارية لا افتراضية: الإشعار داخل المنصة
               مجاني وفوري، والبريد اتصال لكل خمسين مستلما — وإرساله إلى
               كل المستخدمين لخبر صغير يحرق سمعة النطاق عند مزودي البريد.
               والخانة تطفأ حين لا يكون البريد مضبوطا، ويقال لماذا. */
            $CI_mail = &get_instance();
            $CI_mail->load->model('taqdar_mail_model');
            $tq_mail_on = $CI_mail->taqdar_mail_model->configured();
            ?>
            <label class="tqa-switch" style="margin-block-end:var(--tq-space-l)">
                <input type="checkbox" name="by_mail" value="1" <?php echo $tq_mail_on ? '' : 'disabled'; ?>>
                <span class="tqa-switch__track" aria-hidden="true"></span>
                <span><?php echo t('أرسل نسخة بالبريد أيضا'); ?></span>
            </label>
            <?php if (!$tq_mail_on): ?>
                <p class="tqa-field__hint" style="margin-block-start:calc(-1 * var(--tq-space-m));margin-block-end:var(--tq-space-l)">
                    <?php echo t('البريد الصادر غير مضبوط، فالنسخة البريدية معطلة.'); ?>
                    <a href="<?php echo site_url('taqdar_admin/mail'); ?>"><?php echo t('اضبطه من هنا'); ?></a>.
                </p>
            <?php endif; ?>

            <?php
            /* ونسخة واتساب مثلها — أضيق منها وأثقل ثمنا.
               البريد يخرج بنسخة مخفية (ألفا مستلم أربعون رسالة)، وواتساب
               رسالة لكل رقم يدفع ثمنها. فهي تودع في الطابور وتخرج تباعا،
               ولا ترسل من هذه الصفحة. */
            $CI_mail->load->model('taqdar_wa_model');
            $tq_wa_on = $CI_mail->taqdar_wa_model->ready();
            ?>
            <label class="tqa-switch" style="margin-block-end:var(--tq-space-l)">
                <input type="checkbox" name="by_wa" value="1" <?php echo $tq_wa_on ? '' : 'disabled'; ?>>
                <span class="tqa-switch__track" aria-hidden="true"></span>
                <span><?php echo t('وأرسل نسخة بواتساب أيضا'); ?></span>
            </label>
            <p class="tqa-field__hint" style="margin-block-start:calc(-1 * var(--tq-space-m));margin-block-end:var(--tq-space-l)">
                <?php if ($tq_wa_on): ?>
                    <?php echo t('تخرج تباعا لا دفعة واحدة، ولمن له رقم جوال وحده. وواتساب يدفع ثمن كل رسالة، ومن يصله ما لم يطلبه يبلغ عن الرقم — فتضعف القناة عند ميتا وتضيع معها إشعارات المال. فهذه لما يعني كل مستلم.'); ?>
                <?php else: ?>
                    <?php echo t('واتساب غير مضبوط، فنسخته معطلة.'); ?>
                    <a href="<?php echo site_url('taqdar_admin/whatsapp'); ?>"><?php echo t('اضبطه من هنا'); ?></a>.
                <?php endif; ?>
            </p>

            <?php /* التأكيد يذكر العدد: «أرسل» بلا رقم يجعل من يقصد الطلاب
                     يرسل إلى الجميع ولا يعلم إلا بعد الإرسال — ولا سبيل للسحب. */ ?>
            <button class="tqa-btn tqa-btn--primary" type="submit"
                    data-tqa-confirm-title="<?php echo te('تأكيد الإرسال'); ?>"
                    data-tqa-confirm="<?php echo te('لا يمكن سحب الإشعار بعد إرساله. هل تريد المتابعة؟'); ?>"
                    data-tqa-confirm-ok="<?php echo te('أرسل الآن'); ?>">
                <?php echo tq_icon('send', 18); ?> <?php echo t('أرسل الإشعار'); ?>
            </button>
        </form>
    </section>

    <section class="tqa-card tqa-card--flush">
        <div class="tqa-card__head"><h2><?php echo t('ما أرسل قبل'); ?></h2></div>

        <?php if (!$recent): ?>
            <?php tqa_empty(t('لم يرسل إشعار إداري بعد'),
                t('الإشعارات التي يولدها النظام (التصحيح، الحصص، المحفظة) لا تظهر هنا — هذا كشف ما أرسلته الإدارة بيدها.'),
                '', '', 'bell'); ?>
        <?php else: ?>
            <div class="tqa-table__wrap">
            <table class="tqa-table">
                <thead>
                    <tr><th><?php echo t('العنوان'); ?></th><th><?php echo t('المستلمون'); ?></th><th><?php echo t('فتح'); ?></th><th><?php echo t('التاريخ'); ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $n):
                    $rc   = (int) $n['recipients'];
                    $open = (int) $n['opened'];
                    $pct  = $rc > 0 ? (int) round(($open * 100) / $rc) : 0;
                ?>
                    <tr>
                        <td data-label="العنوان"><?php echo html_escape($n['title']); ?></td>
                        <td data-label="المستلمون"><span class="tqa-num"><?php echo $rc; ?></span></td>
                        <td data-label="فتح">
                            <span class="tqa-badge tqa-badge--<?php echo $pct >= 50 ? 'ok' : ($pct > 0 ? 'warn' : 'muted'); ?>">
                                <span class="tqa-num"><?php echo $pct; ?>%</span>
                            </span>
                        </td>
                        <td data-label="التاريخ">
                            <?php /* الجدول فيه صيغتا وقت — انظر TQ-NOTIF-TIME. */ ?>
                            <?php echo tqa_when($n['created_at']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>
</div>
