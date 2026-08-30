<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الحصص بالطلب — ودورتها المالية.
 *
 * دورة الحصة: المعلم يفتح وقتا (`availability_slots`) · الطالب يحجزه
 * فينشأ `tutoring_sessions` بحالة `requested` · المعلم يؤكد فتصدر فاتورة
 * وتنتظر الدفع · يدفع الطالب فتثبت · تنعقد · يعلن المعلم انتهاءها فيقيد
 * نصيبه. ولم يكن في اللوحة شاشة واحدة تراها — فالإدارة تعرف بالشكوى
 * وحدها، وطلب علق أسبوعا لا يظهر في مكان.
 *
 * والتسعيرة تحرر في ذيل هذه الشاشة لا في «إعدادات المنصة»: هذه هي الشاشة
 * التي يظهر فيها أثرها — بجوار الحصص التي بيعت بها. وهو مبدأ TQ-REVIEW-KNOBS
 * نفسه: مفتاح يدفن في شاشة لا تفتح ميزة لا توجد.
 *
 * وعمود «الرابط» كان يقرأ `room_id` — وهو معرف غرفة BigBlueButton لا
 * عنوان لقاء. فالرابط الذي يكتبه المعلم (`meet_url`) لم يكن يظهر في
 * اللوحة قط، وما كان يظهر مكانه رابط مكسور إن وجد معرف غرفة.
 */

$labels = array(
    'requested'        => t('بانتظار رد المعلم'),
    'awaiting_payment' => t('بانتظار الدفع'),
    'confirmed'        => t('مؤكدة ومدفوعة'),
    'live'             => t('جارية الآن'),
    'completed'        => t('انتهت'),
    'declined'         => t('ألغيت'),
    'expired'          => t('مضت مهلتها'),
    'refunded'         => t('مستردة'),
);
$tones = array(
    'requested' => 'warn',  'awaiting_payment' => 'warn', 'confirmed' => 'ok',
    'live'      => 'info',  'completed'        => 'muted','declined'  => 'danger',
    'expired'   => 'muted', 'refunded'         => 'danger',
);

/** مبلغ بالهللات إلى ريال مقروء — وموضع القسمة على مئة واحد في الشاشة. */
$sar = function ($halalas) {
    return '<span class="tqa-num">' . number_format(((int) $halalas) / 100, 2) . t('</span> ر.س');
};

$cfg   = isset($cfg) ? $cfg : array('price' => 0, 'percent' => 0, 'minutes' => 60,
                                    'pay_hours' => 12, 'lead_min' => 30, 'grace_hours' => 6);
$money = isset($money) ? $money : array();
$tap_ready = !empty($tap_ready);
?>

<?php tqa_head(t('الحصص'), t('كل حصة طلبت، ومن طلبها، ومن يدرسها، وبكم بيعت، وأين وقفت.'), 'video'); ?>

<?php /* سطر المال أولا: من يفتح هذه الشاشة يسأل «كم دخل وكم خرج» قبل أن
         يسأل عن صف بعينه، وقائمة ثلاثمئة صف لا تجمع نفسها. */ ?>
<?php if ($cfg['price'] > 0 || !empty($money['gross'])): ?>
<div class="tqa-grid tqa-grid--4" style="margin-block-end:var(--tq-space-xl)">
    <?php
    $tq_tiles = array(
        array(t('محصل من الحصص'), $money['gross']    ?? 0, t('كل حصة وصل ثمنها، بلا ضريبة'),        'wallet',  'tqa-mint'),
        array(t('نصيب المعلمين'),  $money['teachers'] ?? 0, t('عن حصص انعقدت — وهي وحدها التي تقيد'), 'users',   'tqa-sky'),
        array(t('نصيب المنصة'),    $money['platform'] ?? 0, t('الباقي من ثمن الحصص المنعقدة'),        'shield',  'tqa-lilac'),
        array(t('ينتظر الدفع'),    $money['awaiting'] ?? 0, t('حصص أكدها معلموها ولم تدفع بعد'),      'clock',   'tqa-peach'),
    );
    foreach ($tq_tiles as $t): ?>
        <div class="tqa-stat">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label"><?php echo html_escape($t[0]); ?></span>
                <span class="tqa-stat__icon <?php echo $t[4]; ?>"><?php echo tq_icon($t[3], 17); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo $sar($t[1]); ?></span>
            <span class="tqa-stat__hint"><?php echo html_escape($t[2]); ?></span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* حصة انعقدت ودفعت ولم يقيد نصيب معلمها: قيد تعثر ولم يكتب. وهو
         الخبر الذي لا يظهر إلا هنا — المعلم يرى محفظته ناقصة ولا يعرف
         لماذا، والدفتر لا يشتكي. */ ?>
<?php if (!empty($money['uncredited'])): ?>
    <div class="tqa-note tqa-note--warn tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><span class="tqa-num"><?php echo (int) $money['uncredited']; ?></span></strong>
            <?php echo t('حصة انعقدت ودفع ثمنها ولم يقيد نصيب معلمها في محفظته. وهو قيد تعثر لا حصة ناقصة — يعاد بتشغيل'); ?> <code class="tqa-code">taqdar_cron expire_sessions</code><?php echo t('، وإن بقي فالسبب في سجل الأخطاء بوسم'); ?> <code class="tqa-code">TQ-SESSION-CREDIT</code>.
        </span>
    </div>
<?php endif; ?>

<?php /* المرشحات: العدد جزء من التسمية — «بانتظار رد» بلا رقم لا تخبر
         إن كان الانتظار واحدا أو أربعين. */ ?>
<div class="tqa-tabs">
    <a href="<?php echo site_url('taqdar_admin/sessions'); ?>"
       <?php echo $status === '' ? 'aria-current="page"' : ''; ?>><?php echo t('الكل'); ?></a>
    <?php foreach ($labels as $k => $label): ?>
        <a href="<?php echo site_url('taqdar_admin/sessions?status=' . $k); ?>"
           <?php echo $status === $k ? 'aria-current="page"' : ''; ?>>
            <?php echo html_escape($label); ?>
            <?php if (!empty($tally[$k])): ?>
                <span class="tqa-num">(<?php echo (int) $tally[$k]; ?>)</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="tqa-card tqa-card--flush">
<?php if (!$rows): ?>

    <?php tqa_empty(
        $status === '' ? t('لا حصص بعد') : t('لا حصة في هذه الحالة'),
        $status === ''
            ? t('الحصة تبدأ حين يفتح معلم وقتا في شاشة «الحصص» ببوابته، ثم يحجزه طالب. فإن لم يفتح أحد وقتا فلا حصة تطلب — راجع أوقات المعلمين.')
            : t('جرب مرشحا آخر، أو اعرض الكل.'),
        t('أوقات المعلمين'), site_url('taqdar_admin/slots'), 'video'
    ); ?>

<?php else: ?>
    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead>
            <tr>
                <th><?php echo t('الموعد'); ?></th>
                <th><?php echo t('الطالب'); ?></th>
                <th><?php echo t('المعلم'); ?></th>
                <th><?php echo t('المال'); ?></th>
                <th><?php echo t('الحالة'); ?></th>
                <th><?php echo t('الرابط'); ?></th>
                <th><span class="tqa-sr"><?php echo t('إجراء'); ?></span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $st   = (string) $r['status'];
            $tone = $tones[$st] ?? 'muted';
            $when = !empty($r['starts_at']) ? strtotime($r['starts_at']) : 0;
            $price = (int) ($r['price_halalas'] ?? 0);
            $share = (int) ($r['teacher_share_halalas'] ?? 0);
            $paid  = !empty($r['paid_at']);
            /* الحصة المغلقة لا تلغى ثانية: زر يعتذر أسوأ من زر غائب.
               وحصة **انعقدت ودفعت** تسترد: هذا أكثر ما يقع — يتخلف المعلم
               أو يشتكي الطالب بعد الحصة لا قبلها، والقيد قائم في محفظة
               معلمها حتى يعكس. */
            $can_cancel = !in_array($st, array('declined', 'expired', 'refunded'), true)
                       && !($st === 'completed' && !$paid);
            $can_mark   = $st === 'awaiting_payment' && (int) ($r['invoice_id'] ?? 0) > 0;
        ?>
            <tr>
                <td data-label="الموعد">
                    <?php if ($when): ?>
                        <span class="tqa-num"><?php echo date('Y-m-d', $when); ?></span><br>
                        <span class="tqa-num" style="color:var(--tq-text2)"><?php echo date('H:i', $when); ?></span>
                        <?php if (!empty($r['duration_min'])): ?>
                            <span style="color:var(--tq-text2)">·
                                <span class="tqa-num"><?php echo (int) $r['duration_min']; ?></span> <?php echo t('دقيقة'); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--tq-text3)"><?php echo t('الفسحة حذفت'); ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="الطالب">
                    <?php echo html_escape($r['student_name'] ?: '—'); ?><br>
                    <span class="tqa-num" style="color:var(--tq-text2);font-size:12px">
                        <?php echo html_escape($r['student_email'] ?: ''); ?></span>
                </td>
                <td data-label="المعلم"><?php echo html_escape($r['teacher_name'] ?: '—'); ?></td>

                <?php /* ثلاثة أرقام في خانة: الثمن، ونصيب المعلم منه، ورقم
                         الفاتورة. و«باعوا حصتي ولم يصلني شيء» أول ما يسأل
                         عنه معلم، ولم يكن في اللوحة موضع واحد يجيب. */ ?>
                <td data-label="المال">
                    <?php if ($price <= 0): ?>
                        <span style="color:var(--tq-text3)"><?php echo t('بلا ثمن'); ?></span>
                    <?php else: ?>
                        <strong><?php echo $sar($price); ?></strong><br>
                        <span style="color:var(--tq-text2);font-size:12px">
                            <?php echo t('للمعلم'); ?> <?php echo $sar($share); ?>
                            <?php if (!empty($r['credited_at'])): ?>
                                · <span style="color:var(--tq-ok,green)"><?php echo t('قيد'); ?></span>
                            <?php elseif ($st === 'completed' && $paid): ?>
                                · <span style="color:var(--tq-warn,orange)"><?php echo t('لم يقيد'); ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($r['invoice_no'])): ?>
                            <br><span class="tqa-num" style="color:var(--tq-text3);font-size:11px">
                                <?php echo html_escape($r['invoice_no']); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>

                <td data-label="الحالة">
                    <span class="tqa-badge tqa-badge--<?php echo $tone; ?>">
                        <?php echo html_escape($labels[$st] ?? $st); ?>
                    </span>
                    <?php if ($st === 'awaiting_payment' && !empty($r['pay_deadline'])): ?>
                        <br><span class="tqa-num" style="color:var(--tq-text3);font-size:11px">
                            <?php echo t('حتى'); ?> <?php echo date('Y-m-d H:i', strtotime($r['pay_deadline'])); ?></span>
                    <?php elseif (!empty($r['cancel_reason'])): ?>
                        <br><span style="color:var(--tq-text3);font-size:11px">
                            <?php /* TQ-I18N — السبب مخزن في الصف: يكتب مرة بلغة وقت الحدث،
                                     ويقرؤه بعد ذلك من يشاء بلغته. فالترجمة عند
                                     العرض لا عند الكتابة. */ ?>
                            <?php echo html_escape(t($r['cancel_reason'])); ?></span>
                    <?php endif; ?>
                </td>

                <td data-label="الرابط">
                    <?php $link = trim((string) ($r['meet_url'] ?? '')); ?>
                    <?php if ($link !== ''): ?>
                        <a href="<?php echo html_escape($link); ?>" target="_blank" rel="noopener"><?php echo t('افتح'); ?></a>
                    <?php else: ?>
                        <span style="color:var(--tq-text3)">—</span>
                    <?php endif; ?>
                </td>

                <td data-label="إجراء">
                    <?php if ($can_mark): ?>
                        <?php /* التحويل البنكي: يسجله من رآه في الحساب. وبلا
                                 هذا الزر يبقى التحويل بابا مسدودا في الحصص
                                 وحدها — والاشتراك يفعل يدويا منذ كتب. */ ?>
                        <form action="<?php echo site_url('taqdar_admin/session_mark_paid'); ?>" method="post"
                              data-tqa-confirm-title="<?php echo te('تسجيل دفع الحصة'); ?>"
                              data-tqa-confirm="<?php echo te('تثبت الحصة ويفتح رابطها للطالب. سجل هذا بعد أن ترى الحوالة في الحساب لا قبله.'); ?>"
                              data-tqa-confirm-ok="<?php echo te('سجل الدفع'); ?>"
                              style="margin:0 0 6px;display:flex;gap:6px;align-items:center">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="session_id" value="<?php echo (int) $r['id']; ?>">
                            <input class="tqa-input tqa-btn--sm" type="text" name="reference"
                                   placeholder="<?php echo te('مرجع الحوالة'); ?>" style="min-block-size:34px;inline-size:130px">
                            <button class="tqa-btn tqa-btn--sm" type="submit"><?php echo t('سجل الدفع'); ?></button>
                        </form>
                    <?php endif; ?>

                    <?php if ($can_cancel): ?>
                        <?php /* الإلغاء POST لا رابط: يكتب في القاعدة ويحرر وقتا،
                                 ورابط يفعل ذلك بمجرد فتحه يستدعيه استباق المتصفح. */ ?>
                        <form action="<?php echo site_url('taqdar_admin/session_cancel'); ?>" method="post"
                              data-tqa-confirm-title="<?php echo te('إلغاء الحصة'); ?>"
                              data-tqa-confirm="<?php echo $paid
                                  ? t('هذه الحصة مدفوعة: توسم مستردة، ويعكس قيد معلمها. ورد المبلغ نفسه يجرى من لوحة تاب — المنصة لا تردها بنفسها.')
                                  : t('سيحرر وقتها، وتشطب فاتورتها، ويخطر الطالب والمعلم.'); ?>"
                              data-tqa-confirm-ok="<?php echo $paid ? t('وسمها مستردة') : t('ألغ الحصة'); ?>"
                              data-tqa-confirm-tone="danger"
                              style="margin:0;display:flex;gap:6px;align-items:center">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="session_id" value="<?php echo (int) $r['id']; ?>">
                            <input class="tqa-input tqa-btn--sm" type="text" name="reason"
                                   placeholder="<?php echo te('السبب (اختياري)'); ?>" style="min-block-size:34px;inline-size:130px">
                            <button class="tqa-btn tqa-btn--ghost tqa-btn--sm" type="submit">
                                <?php echo $paid ? t('استرداد') : t('إلغاء'); ?></button>
                        </form>
                    <?php else: ?>
                        <span style="color:var(--tq-text3)">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</div>

<?php /* ====================================================================
         تسعيرة الحصص
         ==================================================================== */ ?>
<div class="tqa-card" id="tqa-pricing" style="margin-block-start:var(--tq-space-xl)">
    <div class="tqa-card__head">
        <h2 class="tqa-card__title"><?php echo tq_icon('card', 18); ?> <?php echo t('تسعيرة الحصص'); ?></h2>
    </div>
    <p class="tqa-card__lead">
        <?php echo t('سعر الحصة الواحدة، ونصيب المعلم منه — والباقي للمنصة. والرقمان يقرآن في شاشة الطالب قبل أن يحجز، وفي شاشة المعلم قبل أن يؤكد.'); ?>
        <strong><?php echo t('والسعر يجمد على الحصة وقت طلبها'); ?></strong><?php echo t('، فتعديله اليوم لا يغير ثمن ما طلب أمس.'); ?>
    </p>

    <?php if (!$tap_ready && $cfg['price'] > 0): ?>
        <div class="tqa-note tqa-note--warn tqa-section">
            <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
            <span>
                <?php echo t('سعر الحصة موضوع وبوابة البطاقة غير مفعلة، فلا يعرض للطالب زر دفع. يبقى له التحويل البنكي، وتسجل دفعته من زر «سجل الدفع» في الجدول أعلاه.'); ?>
                <a href="<?php echo site_url('taqdar_admin/tap'); ?>"><?php echo t('اضبط بوابة تاب'); ?></a>.
            </span>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo site_url('taqdar_admin/session_pricing_save'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-grid tqa-grid--2">
            <div class="tqa-field">
                <label class="tqa-field__label" for="p-price"><?php echo t('سعر الحصة (ريال)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="p-price" name="tq_session_price_sar" type="number"
                       min="0" step="0.01" dir="ltr"
                       value="<?php echo html_escape(number_format($cfg['price'] / 100, 2, '.', '')); ?>">
                <span class="tqa-field__hint">
                    <?php echo t('بلا ضريبة — تضاف عند إصدار الفاتورة بنسبة «ضريبة القيمة المضافة» في إعدادات المنصة. و'); ?><strong><?php echo t('صفر يعني حصصا مجانية'); ?></strong><?php echo t(': لا فاتورة ولا شاشة دفع، وتؤكد الحصة في الحال كما كانت.'); ?>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="p-percent"><?php echo t('نصيب المعلم (٪ من السعر)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="p-percent" name="tq_session_teacher_percent" type="number"
                       min="0" max="100" step="0.01" dir="ltr"
                       value="<?php echo html_escape(rtrim(rtrim(number_format($cfg['percent'], 2, '.', ''), '0'), '.')); ?>">
                <span class="tqa-field__hint">
                    <?php echo t('والباقي للمنصة — ولا يخزن رقمان: نسبة المنصة مرآة محسوبة، ورقمان في عمودين يفترقان عند أول تعديل.'); ?>
                    <?php if ($cfg['price'] > 0): ?>
                        <br><?php echo t('بالسعر الحالي: للمعلم'); ?>
                        <strong><?php echo $sar((int) round($cfg['price'] * $cfg['percent'] / 100)); ?></strong><?php echo t('، وللمنصة'); ?>
                        <strong><?php echo $sar($cfg['price'] - (int) round($cfg['price'] * $cfg['percent'] / 100)); ?></strong>.
                    <?php endif; ?>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="p-minutes"><?php echo t('مدة الحصة (دقيقة)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="p-minutes" name="tq_session_minutes" type="number"
                       min="15" max="300" step="5" dir="ltr" value="<?php echo (int) $cfg['minutes']; ?>">
                <span class="tqa-field__hint">
                    <?php echo t('فترة المعلم في شبكته إتاحة لا حصة: «مساء» خمس ساعات، تفرش إلى مواعيد بهذا الطول فيحجزها أكثر من طالب. وتغييرها يعيد فرش'); ?> <strong><?php echo t('المواعيد المفتوحة'); ?></strong> <?php echo t('عند أول حفظ لشبكة كل معلم، ولا يمس موعدا حجز بمدته.'); ?>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="p-pay"><?php echo t('مهلة الدفع (ساعة)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="p-pay" name="tq_session_pay_hours" type="number"
                       min="1" max="168" dir="ltr" value="<?php echo (int) $cfg['pay_hours']; ?>">
                <span class="tqa-field__hint">
                    <?php echo t('بعد تأكيد المعلم. ومهلة الرد على الطلب هي نفسها. وتقصر تلقائيا إلى بداية الحصة: مهلة تمتد بعد موعدها تعني طالبا يدفع ثمن حصة فاتت.'); ?>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="p-lead"><?php echo t('يفتح الرابط قبل الموعد بـ (دقيقة)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="p-lead" name="tq_session_join_lead_min" type="number"
                       min="0" max="240" dir="ltr" value="<?php echo (int) $cfg['lead_min']; ?>">
                <span class="tqa-field__hint">
                    <?php echo t('رابط يفتح قبل يومين يجعل الطالب يدخل غرفة فارغة ويظن أن معلمه تخلف.'); ?>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="p-grace"><?php echo t('مهلة الإغلاق بعد الحصة (ساعة)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="p-grace" name="tq_session_grace_hours" type="number"
                       min="1" max="72" dir="ltr" value="<?php echo (int) $cfg['grace_hours']; ?>">
                <span class="tqa-field__hint">
                    <?php echo t('حصة مضت ولم يعلن معلمها انتهاءها تنهى بعد هذه المهلة'); ?> <strong><?php echo t('ويقيد نصيبه'); ?></strong> <?php echo t('— فمن نسي الزر لا يخسر ماله، ولا يبقى رابط حصة حيا لأن أحدا لم يضغط شيئا.'); ?>
                </span>
            </div>
        </div>

        <div class="tqa-actions">
            <button class="tqa-btn tqa-btn--primary" type="submit">
                <?php echo tq_icon('check', 16); ?> <?php echo t('حفظ التسعيرة'); ?>
            </button>
            <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/slots'); ?>">
                <?php echo t('استثناءات المعلمين'); ?>
            </a>
        </div>
    </form>
</div>
