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
            <?php
            $hist = isset($p['hist']) ? $p['hist'] : array('paid' => 0, 'rejected' => 0, 'total' => 0, 'paid_sum' => 0);
            $country = isset($channels[$ch]['country']) ? $channels[$ch]['country'] : '';
            $amount  = (int) $p['amount_halalas'];
            if ($amount <= 0) $amount = (int) round(((float) $p['amount']) * 100);
            ?>
            <tr>
                <td data-label="#">
                    <a class="tqa-num" href="<?php echo site_url('taqdar_admin/payout/' . (int) $p['id']); ?>"
                       title="افتح ملف الطلب">#<?php echo (int) $p['id']; ?></a>
                </td>

                <td data-label="المعلم">
                    <?php echo html_escape($p['teacher_name'] ?: '—'); ?><br>
                    <span class="tqa-num" style="color:var(--tq-text2);font-size:12px">
                        <?php echo html_escape($p['teacher_email'] ?: ''); ?></span>
                    <?php /* السابقة في سطر: من حول إليه ست مرات ليس كمن يطلب
                             لأول مرة، والفرق يقرر قبل أن يفتح الملف. */ ?>
                    <br><span style="color:var(--tq-text2);font-size:11px">
                        <?php if ((int) $hist['paid'] > 0): ?>
                            حول إليه <?php echo (int) $hist['paid']; ?> مرة
                            (<?php echo tqa_money((int) $hist['paid_sum']); ?>)
                        <?php else: ?>
                            لم يحول إليه من قبل
                        <?php endif; ?>
                        <?php if ((int) $hist['rejected'] > 0): ?>
                            · رفض له <?php echo (int) $hist['rejected']; ?>
                        <?php endif; ?>
                    </span>
                </td>

                <td data-label="المبلغ">
                    <strong><?php echo tqa_money($amount); ?></strong>
                    <?php /* الدلاء الثلاثة: الإدارة تحتاج أن تعرف أن المعلم
                             لم يطلب أكثر مما يملك — والدفتر يمنع ذلك، لكن
                             عرضه يجعل الرقم مفهوما لا مسلما به. */ ?>
                    <br><span style="color:var(--tq-text2);font-size:12px">
                        متاح <?php echo tqa_money((int) $p['balance_available']); ?>
                        · معلق <?php echo tqa_money((int) $p['balance_pending']); ?>
                        · محجوز <?php echo tqa_money((int) $p['balance_locked']); ?>
                    </span>
                </td>

                <td data-label="الوجهة">
                    <?php echo html_escape($ch_label); ?>
                    <?php if ($country === 'eg'): ?>
                        <span class="tqa-badge tqa-badge--warn" style="font-size:10px">مصر</span>
                    <?php endif; ?>
                    <br>
                    <?php if (!empty($p['destination'])): ?>
                        <span class="tqa-num" style="font-size:12px"><?php echo html_escape($p['destination']); ?></span>
                        <?php if ($st === 0 && !empty($p['dest_changed'])): ?>
                            <br><span class="tqa-badge tqa-badge--danger" style="font-size:10px">وجهة جديدة</span>
                        <?php elseif ($st === 0 && !empty($p['first_request'])): ?>
                            <br><span class="tqa-badge tqa-badge--warn" style="font-size:10px">أول طلب</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:var(--tq-text3);font-size:12px">لم يحدد وجهة — اسأله قبل التحويل</span>
                    <?php endif; ?>
                </td>

                <td data-label="التاريخ">
                    <span class="tqa-num"><?php
                        echo !empty($p['date_added']) ? date('Y-m-d', (int) $p['date_added']) : '—';
                    ?></span>
                    <?php if ($st === 0 && !empty($p['date_added'])):
                        $days = (int) floor((time() - (int) $p['date_added']) / 86400); ?>
                        <br><span style="font-size:11px;color:var(--tq-<?php echo $days >= 3 ? 'amber' : 'text2'; ?>)">
                            <?php echo $days <= 0 ? 'اليوم' : 'منتظر ' . $days . ' يوما'; ?>
                        </span>
                    <?php endif; ?>
                </td>

                <td data-label="الحالة">
                    <span class="tqa-badge tqa-badge--<?php echo $tone; ?>"><?php echo html_escape($label); ?></span>
                    <?php /* أثر القرار في مكانه: من قرر ومتى وبأي مرجع — وكان
                             العمود يعرض `payment_type` وحده وهو نص فني
                             (`bank:12345`) لا يقول من قرر ولا متى. */ ?>
                    <?php if ($st !== 0): ?>
                        <br><span style="font-size:11px;color:var(--tq-text2)">
                            <?php if (!empty($p['decided_name'])): ?>
                                <?php echo html_escape(trim((string) $p['decided_name'])); ?>
                            <?php endif; ?>
                            <?php if (!empty($p['decided_at'])): ?>
                                · <span class="tqa-num"><?php echo date('Y-m-d', (int) $p['decided_at']); ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($p['reference'])): ?>
                            <br><span class="tqa-num" style="font-size:11px;color:var(--tq-text2)">
                                مرجع: <?php echo html_escape((string) $p['reference']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($p['reject_reason'])): ?>
                            <br><span style="font-size:11px;color:var(--tq-text2)">
                                <?php echo html_escape((string) $p['reject_reason']); ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>

                <td data-label="القرار">
                    <?php if ($st !== 0): ?>
                        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                           href="<?php echo site_url('taqdar_admin/payout/' . (int) $p['id']); ?>">التفاصيل</a>
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
                            <div style="display:flex;gap:6px;align-items:center">
                                <?php /* الملف قبل القرار لمن أراد التحقق. والقرار
                                         من هنا لمن لا يحتاج — القائمة تخدم من يمر
                                         على عشرة طلبات، والملف من يقف عند واحد. */ ?>
                                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                   href="<?php echo site_url('taqdar_admin/payout/' . (int) $p['id']); ?>">التفاصيل</a>
                            </div>
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
