<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * طلبات السحب.
 *
 * تحل محل `admin/instructor_payout` الموروثة — وهي تدفع بـPayPal أو
 * Stripe أو Razorpay ولا واحدة منها مفعلة، ولا تعرف دفتر المحفظة
 * أصلا: فاعتمادها كان يضع `payout.status = 1` ويترك المبلغ محجوزا في
 * دلو `locked` إلى الأبد — رصيد المعلم لا يعود ولا يخرج.
 *
 * وهذه الشاشة تمر بـ`Taqdar_wallet_model::mark_payout_paid()` و
 * `cancel_payout()`، فيغادر المال دلو الحجز عند التحويل ويعود إلى
 * المتاح عند الرفض. والوسيلة حوالة بنكية — وهي وسيلة الدفع الفعلية.
 */

$states = array(
    0 => array('warn',   'بانتظار التحويل'),
    1 => array('ok',     'حول'),
    2 => array('danger', 'رفض'),
);
?>

<?php tqa_head('طلبات السحب', 'ما طلبه المعلمون من أرصدتهم، وما حول منه.', 'send'); ?>

<div class="tqa-grid tqa-grid--3" style="margin-block-end:var(--tq-space-xl)">
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label">طلبات بلا رد</span>
            <span class="tqa-stat__icon <?php echo $totals['pending_n'] ? 'tqa-rose' : 'tqa-mint'; ?>" aria-hidden="true">
                <?php echo tq_icon($totals['pending_n'] ? 'alert' : 'check', 18); ?>
            </span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $totals['pending_n']; ?></span>
        <span class="tqa-stat__hint">تنتظر قرارك</span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label">المبلغ المحجوز</span>
            <span class="tqa-stat__icon tqa-peach" aria-hidden="true"><?php echo tq_icon('lock', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo tqa_money($totals['pending_sum']); ?></span>
        <span class="tqa-stat__hint">محجوز من أرصدة المعلمين حتى تقرر</span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label">حول إجمالا</span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('check', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo tqa_money($totals['paid_sum']); ?></span>
        <span class="tqa-stat__hint">منذ بداية التشغيل</span>
    </div>
</div>

<div class="tqa-tabs">
    <a href="<?php echo site_url('taqdar_admin/payouts'); ?>"
       <?php echo $status === '' ? 'aria-current="page"' : ''; ?>>الكل</a>
    <a href="<?php echo site_url('taqdar_admin/payouts?status=pending'); ?>"
       <?php echo $status === 'pending' ? 'aria-current="page"' : ''; ?>>بانتظار التحويل</a>
    <a href="<?php echo site_url('taqdar_admin/payouts?status=paid'); ?>"
       <?php echo $status === 'paid' ? 'aria-current="page"' : ''; ?>>حولت</a>
    <a href="<?php echo site_url('taqdar_admin/payouts?status=rejected'); ?>"
       <?php echo $status === 'rejected' ? 'aria-current="page"' : ''; ?>>رفضت</a>
</div>

<div class="tqa-card tqa-card--flush">
<?php if (!$rows): ?>

    <?php tqa_empty('لا طلبات سحب',
        'المعلم يطلب السحب من شاشة «المحفظة والأرباح» ببوابته. ولا يستطيع الطلب إلا إذا بلغ رصيده المتاح الحد الأدنى.',
        '', '', 'send'); ?>

<?php else: ?>
    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead>
            <tr>
                <th>#</th>
                <th>المعلم</th>
                <th>المبلغ</th>
                <th>الوجهة</th>
                <th>التاريخ</th>
                <th>الحالة</th>
                <th>القرار</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $p):
            $st = (int) $p['status'];
            [$tone, $label] = $states[$st] ?? array('muted', 'غير معروفة');
            $ch = (string) ($p['requested_channel'] ?: $p['payment_type']);
            $ch_label = $channels[$ch]['label'] ?? 'قناة تحدد مع الإدارة';
        ?>
            <tr>
                <td data-label="#"><span class="tqa-num"><?php echo (int) $p['id']; ?></span></td>

                <td data-label="المعلم">
                    <?php echo html_escape($p['teacher_name'] ?: '—'); ?><br>
                    <span class="tqa-num" style="color:var(--tq-text2);font-size:12px">
                        <?php echo html_escape($p['teacher_email'] ?: ''); ?></span>
                </td>

                <td data-label="المبلغ">
                    <strong><?php echo tqa_money($p['amount_halalas']); ?></strong>
                    <?php /* المتاح بعد الحجز: الإدارة تحتاج أن تعرف أن المعلم
                             لم يطلب أكثر مما يملك — والدفتر يمنع ذلك، لكن
                             عرضه يجعل الرقم مفهوما لا مسلما به. */ ?>
                    <br><span style="color:var(--tq-text2);font-size:12px">
                        رصيده المتاح: <?php echo tqa_money((int) $p['balance_available']); ?>
                    </span>
                </td>

                <td data-label="الوجهة">
                    <?php echo html_escape($ch_label); ?><br>
                    <?php if (!empty($p['destination'])): ?>
                        <span class="tqa-num" style="font-size:12px"><?php echo html_escape($p['destination']); ?></span>
                    <?php else: ?>
                        <span style="color:var(--tq-text3);font-size:12px">لم يحدد وجهة — اسأله قبل التحويل</span>
                    <?php endif; ?>
                </td>

                <td data-label="التاريخ">
                    <span class="tqa-num"><?php
                        echo !empty($p['date_added']) ? date('Y-m-d', (int) $p['date_added']) : '—';
                    ?></span>
                </td>

                <td data-label="الحالة">
                    <span class="tqa-badge tqa-badge--<?php echo $tone; ?>"><?php echo html_escape($label); ?></span>
                    <?php if ($st === 1 && !empty($p['payment_type'])): ?>
                        <br><span class="tqa-num" style="font-size:11px;color:var(--tq-text2)">
                            <?php echo html_escape($p['payment_type']); ?></span>
                    <?php endif; ?>
                </td>

                <td data-label="القرار">
                    <?php if ($st !== 0): ?>
                        <span style="color:var(--tq-text3)">قرر</span>
                    <?php else: ?>
                        <?php /* المرجع حقل واحد يخدم الفعلين: رقم العملية عند
                                 الاعتماد، وسبب الرفض عند الرفض. وحقلان
                                 منفصلان في خلية جدول يضيقان على القارئ بلا فائدة. */ ?>
                        <form action="<?php echo site_url('taqdar_admin/payout_decide'); ?>" method="post"
                              style="margin:0;display:grid;gap:6px;min-inline-size:220px">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="payout_id" value="<?php echo (int) $p['id']; ?>">
                            <input class="tqa-input tqa-input--ltr" type="text" name="reference"
                                   placeholder="رقم العملية أو سبب الرفض"
                                   style="min-block-size:34px;font-size:13px">
                            <div style="display:flex;gap:6px">
                                <?php /* التأكيد على الزر لا على النموذج: الزران يرسلان
                                         النموذج نفسه ويفترقان في `act` وحدها، فالسؤال
                                         سؤالان لا سؤال واحد. */ ?>
                                <button class="tqa-btn tqa-btn--mastery tqa-btn--sm" type="submit" name="act" value="pay"
                                        data-tqa-confirm-title="اعتماد التحويل"
                                        data-tqa-confirm="سيخصم المبلغ من المحجوز نهائيا ويخطر المعلم. تأكد من تنفيذ الحوالة أولا."
                                        data-tqa-confirm-ok="نعم، حولت">
                                    حولت
                                </button>
                                <button class="tqa-btn tqa-btn--ghost tqa-btn--sm" type="submit" name="act" value="reject"
                                        data-tqa-confirm-title="رفض طلب السحب"
                                        data-tqa-confirm="سيعاد المبلغ إلى رصيد المعلم المتاح، ويصله سبب الرفض كما كتبته."
                                        data-tqa-confirm-ok="ارفض الطلب"
                                        data-tqa-confirm-tone="danger">
                                    رفض
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</div>

<div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
    <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
    <span>
        الاعتماد يخصم من دلو «المحجوز» في دفتر المحفظة، والرفض يعيد المبلغ إلى «المتاح».
        وكلاهما يسجل في سجل التدقيق باسمك وبرقم العملية — فالتحويل يطابق بكشف البنك،
        ولا يبقى قرار مالي بلا أثر يراجع.
    </span>
</div>
