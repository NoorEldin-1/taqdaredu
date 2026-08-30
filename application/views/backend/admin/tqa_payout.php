<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ملف طلب سحب واحد.
 *
 * القائمة تجيب «كم طلبا ينتظر؟». وهذه تجيب الأسئلة التي يقف عندها من
 * يهم بالتحويل فعلا:
 *
 *   · إلى أين أحول بالضبط؟ (الوجهة كاملة، لا مقنعة — المسؤول هو من يحول)
 *   · هل يملك المعلم هذا المبلغ؟ (الدلاء الثلاثة، وما يبقى بعد التحويل)
 *   · من أين جاء رصيده؟ (المبيعات التي كونته، وهي نفسها التي يراها هو)
 *   · هل حولت له قبل؟ (السابقة: كم حول وكم رفض ومتى)
 *   · هل تغير شيء يستوقف؟ (الوجهة الجديدة تعلن ولا تمنع)
 *
 * وكانت هذه الأسئلة كلها بلا جواب في الشاشة **لأن جوابها غير مخزن**:
 * جدول `payout` عشرة أعمدة لا أثر فيها لمن قرر ولا متى ولا برقم أي
 * عملية — فرقم التحويل كان يكتب في `ref` قيد المحفظة وحده، نصا داخل
 * مفتاح فني لا يبحث فيه ولا يعرض.
 *
 * والوجهة تعرض كاملة هنا وحدها: شاشة المعلم تقنعها بأربع خانات، وهو
 * الصواب هناك — أما من سيفتح تطبيق البنك فيحتاج الرقم كله.
 */

$p = $d['payout'];
$w = $d['wallet'];
$h = $d['hist'];

$st     = (int) $p['status'];
$states = array(
    0 => array('warn',   t('بانتظار التحويل')),
    1 => array('ok',     t('حول')),
    2 => array('danger', t('رفض')),
);
list($tone, $label) = isset($states[$st]) ? $states[$st] : array('muted', t('غير معروفة'));

$ch       = (string) ($p['requested_channel'] ?: $p['payment_type']);
$ch_meta  = isset($channels[$ch]) ? $channels[$ch] : null;
$ch_label = $ch_meta ? $ch_meta['label'] : t('قناة تحدد مع الإدارة');
$country  = $ch_meta && isset($ch_meta['country']) ? $ch_meta['country'] : '';

$amount = (int) $p['amount_halalas'];
if ($amount <= 0) $amount = (int) round(((float) $p['amount']) * 100);

/* ما يبقى بعد التحويل — الرقم الذي يسأل عنه من يوازن بين طلبات كثيرة.
   والمحجوز يشمل هذا الطلب، فخصمه منه لا من المتاح: المتاح خرج منه
   المبلغ وقت الطلب لا وقت التحويل. */
$after = (int) $w['balance_locked'] - $amount;

$when = function ($ts) {
    $ts = (int) $ts;
    return $ts > 0 ? date('Y-m-d H:i', $ts) : '—';
};
?>

<?php tqa_head(t('طلب سحب #') . (int) $p['id'],
               t('كل ما يلزم لاتخاذ القرار وللإجابة عنه بعده.'), 'send',
               '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/payouts') . t('">رجوع إلى القائمة</a>')); ?>

<?php /* التنبيهات أولا: ما يغير القرار يقرأ قبل ما يشرحه. */ ?>
<?php if ($st === 0 && !empty($d['dest_changed'])): ?>
    <div class="tqa-note tqa-note--warn" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <b><?php echo t('الوجهة تغيرت عن آخر تحويل.'); ?></b>
            <?php echo t('كانت'); ?> <span class="tqa-num"><?php echo html_escape((string) $h['last_dest']); ?></span>
            <?php echo t('وصارت'); ?> <span class="tqa-num"><?php echo html_escape((string) $p['destination']); ?></span><?php echo t('. وهذا يقع بلا سبب سيئ في الغالب — تغيير بنك أو محفظة. لكن حسابا اخترق يبدأ من هنا، فتأكد بالاتصال قبل التحويل ولا تكتف بالرسالة.'); ?>
        </span>
    </div>
<?php elseif ($st === 0 && !empty($d['first_request'])): ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <b><?php echo t('أول طلب لهذا المعلم.'); ?></b>
            <?php echo t('لا وجهة سابقة يقارن بها. تحقق من الاسم على الحساب قبل التحويل — الحوالة الأولى هي التي تصحح، وما بعدها يقارن بها.'); ?>
        </span>
    </div>
<?php endif; ?>

<div class="tqa-grid tqa-grid--3" style="margin-block-end:var(--tq-space-xl)">
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('المطلوب'); ?></span>
            <span class="tqa-stat__icon tqa-peach" aria-hidden="true"><?php echo tq_icon('send', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo tqa_money($amount); ?></span>
        <span class="tqa-stat__hint">
            <span class="tqa-badge tqa-badge--<?php echo $tone; ?>"><?php echo html_escape($label); ?></span>
        </span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('محجوز الآن'); ?></span>
            <span class="tqa-stat__icon tqa-lilac" aria-hidden="true"><?php echo tq_icon('lock', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo tqa_money((int) $w['balance_locked']); ?></span>
        <span class="tqa-stat__hint">
            <?php if ($st === 0): ?>
                يبقى <?php echo tqa_money(max(0, $after)); ?> بعد هذا التحويل
            <?php else: ?>
                مقابل طلبات قائمة أخرى
            <?php endif; ?>
        </span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('رصيده بعد الحجز'); ?></span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('wallet', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo tqa_money((int) $w['balance_available']); ?></span>
        <span class="tqa-stat__hint">
            ومعلق <?php echo tqa_money((int) $w['balance_pending']); ?> لم يتحرر بعد
        </span>
    </div>
</div>

<div class="tqa-cols">

    <div>
        <?php /* ---- الوجهة: أهم صندوق في الشاشة، فهو أعلاها وأوسعها ---- */ ?>
        <div class="tqa-card" style="margin-block-end:var(--tq-space-l)">
            <div class="tqa-card__head"><h2 class="tqa-card__title"><?php echo t('إلى أين تحول'); ?></h2></div>
            <div class="tqa-card__body">
                <dl class="tqa-dl">
                    <dt><?php echo t('القناة'); ?></dt>
                    <dd>
                        <?php echo html_escape($ch_label); ?>
                        <?php if ($country === 'eg'): ?>
                            <span class="tqa-badge tqa-badge--warn"><?php echo t('قناة مصرية'); ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt><?php echo t('الوجهة'); ?></dt>
                    <dd>
                        <?php if (!empty($p['destination'])): ?>
                            <span class="tqa-num tqa-dest"><?php echo html_escape((string) $p['destination']); ?></span>
                            <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                    data-tqa-copy="<?php echo html_escape((string) $p['destination']); ?>"><?php echo t('انسخ'); ?></button>
                        <?php else: ?>
                            <span class="tqa-dim"><?php echo t('لم يحدد وجهة — اسأله قبل التحويل'); ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt><?php echo t('المبلغ'); ?></dt>
                    <dd><b><?php echo tqa_money($amount); ?></b>
                        <span class="tqa-dim">(<?php echo tqa_ltr($amount); ?> هللة)</span></dd>

                    <dt><?php echo t('تاريخ الطلب'); ?></dt>
                    <dd><span class="tqa-num"><?php echo $when($p['date_added']); ?></span></dd>
                </dl>

                <?php if ($country === 'eg'): ?>
                    <p class="tqa-reach__note" style="margin-block-start:var(--tq-space-m)">
                        <?php echo t('الرصيد بالريال والوجهة مصرية — فالتحويل يمر بصرف. سجل رقم العملية وسعر الصرف في الملاحظة أدناه، فالمعلم يرى مبلغه بالريال ويستلم بالجنيه.'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ---- من أين جاء الرصيد ---- */ ?>
        <div class="tqa-card" style="margin-block-end:var(--tq-space-l)">
            <div class="tqa-card__head">
                <h2 class="tqa-card__title"><?php echo t('من أين جاء هذا الرصيد'); ?></h2>
            </div>
            <div class="tqa-card__body">
                <?php if (!$d['sources']): ?>
                    <p class="tqa-dim"><?php echo t('لا مبيعات مقيدة في دفتر هذا المعلم بعد.'); ?></p>
                <?php else: ?>
                    <p class="tqa-reach__lead" style="margin-block-end:var(--tq-space-m)">
                        <?php echo t('آخر المبيعات وحصته الصافية من كل منها بعد عمولة المنصة — وهي نفسها الأرقام التي يراها في كشف حسابه، فلا يفترق ما يقرؤه عما تقرؤه.'); ?>
                    </p>
                    <table class="tqa-split__table">
                        <thead>
                            <tr><th><?php echo t('التاريخ'); ?></th><th><?php echo t('ما بيع'); ?></th><th><?php echo t('النوع'); ?></th><th><?php echo t('حصته الصافية'); ?></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($d['sources'] as $s): ?>
                            <tr>
                                <td><span class="tqa-num"><?php echo html_escape(substr((string) $s['occurred_at'], 0, 10)); ?></span></td>
                                <td><?php echo html_escape((string) $s['subject']); ?></td>
                                <td>
                                    <?php echo $s['kind'] === 'plan'
                                        ? t('<span class="tqa-badge tqa-badge--ok">باقة</span>')
                                        : t('<span class="tqa-badge tqa-badge--muted">كورس</span>'); ?>
                                </td>
                                <td><b><?php echo tqa_money((int) $s['net']); ?></b></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ---- الدفتر خاما: آخر حركة، لمن يريد أن يتحقق بنفسه ---- */ ?>
        <?php if ($d['entries']): ?>
            <details class="tqa-card">
                <summary class="tqa-card__head" style="cursor:pointer">
                    <h2 class="tqa-card__title">آخر حركات الدفتر (<?php echo count($d['entries']); ?>)</h2>
                </summary>
                <div class="tqa-card__body">
                    <p class="tqa-reach__lead" style="margin-block-end:var(--tq-space-m)">
                        <?php echo t('القيود كما كتبت. القيد لا يعدل ولا يحذف — يقابل بعكسه، فالسالب هنا حقيقة محاسبية لا خطأ.'); ?>
                    </p>
                    <table class="tqa-split__table">
                        <thead><tr><th><?php echo t('الوقت'); ?></th><th><?php echo t('النوع'); ?></th><th><?php echo t('الدلو'); ?></th><th><?php echo t('المبلغ'); ?></th><th><?php echo t('عن'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($d['entries'] as $e): ?>
                            <tr>
                                <td><span class="tqa-num"><?php echo html_escape(substr((string) $e['occurred_at'], 0, 16)); ?></span></td>
                                <td class="tqa-num"><?php echo html_escape((string) $e['type']); ?></td>
                                <td class="tqa-num"><?php echo html_escape((string) $e['bucket']); ?></td>
                                <td><?php echo tqa_money((int) $e['amount']); ?></td>
                                <td><?php echo html_escape(mb_substr((string) $e['subject'], 0, 40)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </div>

    <aside>
        <?php /* ---- القرار ---- */ ?>
        <?php if ($st === 0): ?>
            <form class="tqa-card" method="post" action="<?php echo site_url('taqdar_admin/payout_decide'); ?>"
                  style="margin-block-end:var(--tq-space-l)">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="payout_id" value="<?php echo (int) $p['id']; ?>">
                <input type="hidden" name="back" value="detail">

                <div class="tqa-card__head"><h2 class="tqa-card__title"><?php echo t('القرار'); ?></h2></div>
                <div class="tqa-card__body">
                    <div class="tqa-field">
                        <label class="tqa-field__label" for="tqa-ref"><?php echo t('رقم العملية أو سبب الرفض'); ?></label>
                        <input class="tqa-input tqa-input--ltr" dir="ltr" id="tqa-ref" type="text" name="reference">
                        <span class="tqa-field__hint">
                            <?php echo t('عند الاعتماد: رقم الحوالة كما في كشف البنك — وهو شرط، فتحويل بلا مرجع لا يطابق. وعند الرفض: السبب كما سيصل المعلم في إشعاره.'); ?>
                        </span>
                    </div>

                    <div class="tqa-field">
                        <label class="tqa-field__label" for="tqa-note"><?php echo t('ملاحظة داخلية'); ?></label>
                        <input class="tqa-input" id="tqa-note" type="text" name="admin_note">
                        <span class="tqa-field__hint">
                            <?php echo t('لا تعرض للمعلم. لسعر الصرف، أو لمن اتصلت به، أو لما تريد أن تذكره حين يسأل أحد بعد شهر.'); ?>
                        </span>
                    </div>

                    <div style="display:flex;gap:var(--tq-space-s);flex-wrap:wrap">
                        <button class="tqa-btn tqa-btn--mastery" type="submit" name="act" value="pay"
                                data-tqa-confirm-title="<?php echo te('اعتماد التحويل'); ?>"
                                data-tqa-confirm="سيخصم المبلغ من المحجوز نهائيا ويخطر المعلم. تأكد من تنفيذ الحوالة أولا."
                                data-tqa-confirm-ok="نعم، حولت"><?php echo t('حولت'); ?></button>
                        <button class="tqa-btn tqa-btn--ghost" type="submit" name="act" value="reject"
                                data-tqa-confirm-title="<?php echo te('رفض طلب السحب'); ?>"
                                data-tqa-confirm="سيعاد المبلغ إلى رصيد المعلم المتاح، ويصله سبب الرفض كما كتبته."
                                data-tqa-confirm-ok="ارفض الطلب"
                                data-tqa-confirm-tone="danger"><?php echo t('رفض'); ?></button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="tqa-card" style="margin-block-end:var(--tq-space-l)">
                <div class="tqa-card__head"><h2 class="tqa-card__title"><?php echo t('أثر القرار'); ?></h2></div>
                <div class="tqa-card__body">
                    <dl class="tqa-dl">
                        <dt><?php echo t('الحال'); ?></dt>
                        <dd><span class="tqa-badge tqa-badge--<?php echo $tone; ?>"><?php echo html_escape($label); ?></span></dd>
                        <dt><?php echo t('من قرر'); ?></dt>
                        <dd><?php echo html_escape(trim((string) $p['decided_name']) ?: t('غير مسجل')); ?></dd>
                        <dt><?php echo t('متى'); ?></dt>
                        <dd><span class="tqa-num"><?php echo $when($p['decided_at'] ?: $p['last_modified']); ?></span></dd>
                        <?php if (!empty($p['reference'])): ?>
                            <dt><?php echo t('رقم العملية'); ?></dt>
                            <dd><span class="tqa-num"><?php echo html_escape((string) $p['reference']); ?></span></dd>
                        <?php endif; ?>
                        <?php if (!empty($p['reject_reason'])): ?>
                            <dt><?php echo t('سبب الرفض'); ?></dt>
                            <dd><?php echo html_escape((string) $p['reject_reason']); ?></dd>
                        <?php endif; ?>
                        <?php if (!empty($p['admin_note'])): ?>
                            <dt><?php echo t('ملاحظة داخلية'); ?></dt>
                            <dd><?php echo html_escape((string) $p['admin_note']); ?></dd>
                        <?php endif; ?>
                    </dl>
                    <?php if (empty($p['decided_name']) && empty($p['reference'])): ?>
                        <p class="tqa-reach__note">
                            <?php echo t('قرر هذا الطلب قبل أن يسجل أثر القرار — فلا اسم ولا مرجع. وما يقرر بعد اليوم يحفظ كلاهما.'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ---- المعلم وسابقته ---- */ ?>
        <div class="tqa-card" style="margin-block-end:var(--tq-space-l)">
            <div class="tqa-card__head"><h2 class="tqa-card__title"><?php echo t('المعلم'); ?></h2></div>
            <div class="tqa-card__body">
                <dl class="tqa-dl">
                    <dt><?php echo t('الاسم'); ?></dt>
                    <dd><b><?php echo html_escape(trim((string) $p['teacher_name']) ?: '—'); ?></b></dd>
                    <dt><?php echo t('البريد'); ?></dt>
                    <dd><span class="tqa-num"><?php echo html_escape((string) $p['teacher_email']); ?></span></dd>
                    <dt><?php echo t('الجوال'); ?></dt>
                    <dd><span class="tqa-num"><?php echo html_escape((string) $p['teacher_phone']) ?: '<span class="tqa-dim">—</span>'; ?></span></dd>
                    <dt><?php echo t('في المنصة منذ'); ?></dt>
                    <dd><span class="tqa-num"><?php echo $when($p['teacher_since']); ?></span></dd>
                    <dt><?php echo t('حساب'); ?></dt>
                    <dd>
                        <?php echo ((int) $p['teacher_status'] === 1)
                            ? t('<span class="tqa-badge tqa-badge--ok">مفعل</span>')
                            : t('<span class="tqa-badge tqa-badge--danger">موقوف</span>'); ?>
                    </dd>
                    <dt><?php echo t('مساراته'); ?></dt>
                    <dd><?php echo (int) $d['teacher_paths']; ?></dd>
                </dl>

                <hr style="border:0;border-block-start:1px solid var(--tq-line);margin:var(--tq-space-l) 0">

                <dl class="tqa-dl">
                    <dt><?php echo t('طلبات سابقة'); ?></dt>
                    <dd><?php echo max(0, (int) $h['total'] - 1); ?></dd>
                    <dt><?php echo t('حول إليه'); ?></dt>
                    <dd><?php echo (int) $h['paid']; ?> مرة · <b><?php echo tqa_money((int) $h['paid_sum']); ?></b></dd>
                    <dt><?php echo t('رفض له'); ?></dt>
                    <dd><?php echo (int) $h['rejected']; ?></dd>
                    <dt><?php echo t('أول طلب'); ?></dt>
                    <dd><span class="tqa-num"><?php echo $when($h['first_at']); ?></span></dd>
                </dl>

                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                   href="<?php echo site_url('taqdar_admin/form/users/' . (int) $p['user_id']); ?>"><?php echo t('افتح حسابه'); ?></a>
            </div>
        </div>

        <?php /* ---- طلباته الأخرى ---- */ ?>
        <?php if ($d['others']): ?>
            <div class="tqa-card">
                <div class="tqa-card__head"><h2 class="tqa-card__title"><?php echo t('طلباته الأخرى'); ?></h2></div>
                <div class="tqa-card__body" style="padding:0">
                    <table class="tqa-split__table" style="margin:0">
                        <thead><tr><th>#</th><th><?php echo t('المبلغ'); ?></th><th><?php echo t('الحال'); ?></th><th><?php echo t('التاريخ'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($d['others'] as $o):
                            $ost = (int) $o['status'];
                            list($ot, $ol) = isset($states[$ost]) ? $states[$ost] : array('muted', '—');
                        ?>
                            <tr>
                                <td><a href="<?php echo site_url('taqdar_admin/payout/' . (int) $o['id']); ?>"
                                       class="tqa-num">#<?php echo (int) $o['id']; ?></a></td>
                                <td><?php echo tqa_money((int) $o['amount_halalas']); ?></td>
                                <td><span class="tqa-badge tqa-badge--<?php echo $ot; ?>"><?php echo html_escape($ol); ?></span></td>
                                <td><span class="tqa-num"><?php
                                    echo !empty($o['date_added']) ? date('Y-m-d', (int) $o['date_added']) : '—';
                                ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </aside>
</div>

<script>
/* نسخ الوجهة بضغطة — الآيبان أربع وعشرون خانة، ونسخه بالتحديد اليدوي
   يسقط منه رقم أو يلتقط مسافة. والفشل يقال: من ضغط ولم ينسخ يجب أن
   يعرف قبل أن يلصق في تطبيق بنكه ما نسخه قبل ساعة. */
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-copy]'), function (b) {
        b.addEventListener('click', function () {
            var v = b.getAttribute('data-tqa-copy') || '';
            var done = function (ok) {
                b.textContent = ok ? 'نسخ' : 'حدده وانسخه يدويا';
                setTimeout(function () { b.textContent = 'انسخ'; }, 2500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(v).then(function () { done(true); },
                                                      function () { done(false); });
            } else {
                done(false);
            }
        });
    });
})();
</script>
