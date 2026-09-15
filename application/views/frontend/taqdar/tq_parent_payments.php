<?php
/**
 * بوابة ولي الأمر — المدفوعات.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية — كل شيء واضح ومفهوم من
 * نظرة واحدة وبلا مصطلحات: تاريخ، وما اشتري، ولمن، وكم. لا أكثر.
 *
 * ما يظهر هنا حقيقي بالكامل، ومن **مصدري المال معا** لا من أحدهما:
 *   · `invoices` + `subscriptions` — مسار تقدر، وهو المسار العامل اليوم
 *   · `payment`                    — جدول Academy، شراء كورس مفرد
 * والدمج والوحدة في `Taqdar_parent_model::payments_of()`، فلا تقسم شاشة
 * على مئة وتنسى أختها.
 *
 * وكانت الشاشة تقرأ `payment` وحده — وهو فارغ بينما لابن ولي الأمر
 * اشتراك نشط بثلاثمئة وتسعين ريالا وأربع فواتير: فيقرأ من يدفع فعلا
 * «لا مدفوعات بعد · 0 ريال». صفحة تعمل وتكذب أسوأ من صفحة معطوبة.
 *
 * والمعلق يفصل عن المدفوع: فاتورة تنتظر تحويلا ليست مالا خرج من الجيب،
 * وجمعها في «ما دفعته» يكبر الرقم على صاحبه — وهي في الوقت نفسه أهم ما
 * في الصفحة، لأن عليها يتوقف اشتراك ابنه.
 *
 * ما ينتظر جدولا:
 *   الفاتورة المطبوعة — تنتظر برنامج فاتورة رسميا؛ ويعرض حتى ذلك
 *                    رقم العملية كما هو، فهو ما يراجع به الدفع.
 */

$tq_nav   = 'payments';
$tq_role  = 'parent';
$tq_title = t('المدفوعات');
$tq_sub   = t('كل ما دفعته، ولمن، ومتى');
$tq_icon  = 'wallet';

$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_parent_model');
$tq_pm = $tq_ci->taqdar_parent_model;

$tq_uid = (int) $this->session->userdata('user_id');

$tq_people = [
    ['id' => $tq_uid, 'name' => t('مدفوعاتي'), 'self' => true, 'until' => 0],
];

foreach ($tq_pm->children($tq_uid) as $tq_c) {
    $tq_people[] = [
        'id'   => (int) $tq_c['student_id'],
        'name' => t('مدفوعات ') . $tq_c['name'],
        'self' => false,
        'until' => 0,
    ];
}

/* TQ-PAY-UNLINKED — ما دفعه ولي الأمر لا يختفي بفك الربط. كان سجل مدفوعاته
   يقرأ الأبناء المرتبطين الآن وحدهم: فمن فك ربط ابنه يفقد من سجله كل ما دفعه
   عنه. فالرابط الملغى بعد موافقة يبقى — بما صدر قبل تاريخ إلغائه وحده، لا بما
   يصدر للابن بعد أن صار خارج حسابه. */
foreach ($tq_pm->links($tq_uid, 'revoked') as $tq_l) {
    if (empty($tq_l['consent_at']) && empty($tq_l['prefs']['consent'])) continue;
    $tq_until = strtotime((string) ($tq_l['prefs']['revoked']['at'] ?? '')) ?: 0;
    $tq_people[] = [
        'id'    => (int) $tq_l['student_id'],
        'name'  => t('مدفوعات ') . $tq_l['name'] . t(' (رابط ملغى)'),
        'self'  => false,
        'until' => $tq_until,
    ];
}

$tq_month_start = strtotime(date('Y-m-01 00:00:00'));
$tq_month_total = 0;
$tq_all_total   = 0;
$tq_due_total   = 0;
$tq_due_count   = 0;

/* TQ-PAY-TOTALS — آخر خمسين عملية تعرض، و«عرض كل العمليات» يفتح الباقي؛
   والمجاميع من القاعدة كلها لا من المعروض. */
$tq_show_all = (string) $this->input->get('all') === '1';
$tq_limit    = $tq_show_all ? 2000 : 50;
$tq_trimmed  = false;

foreach ($tq_people as &$tq_p) {
    $tq_p['rows'] = $tq_pm->payments_of($tq_p['id'], $tq_limit);
    if (count($tq_p['rows']) >= $tq_limit) $tq_trimmed = true;

    if ($tq_p['until'] > 0) {
        $tq_until = $tq_p['until'];
        $tq_p['rows'] = array_values(array_filter($tq_p['rows'], function ($r) use ($tq_until) {
            return (int) $r['ts'] <= $tq_until;
        }));
        $tq_t = $tq_pm->payment_totals($tq_p['rows'], $tq_month_start);
    } else {
        $tq_t = $tq_pm->payment_totals_all($tq_p['id'], $tq_month_start);
    }

    $tq_month_total += $tq_t['month'];
    $tq_all_total   += $tq_t['all'];
    /* المعلق لابن فك ربطه ليس على ولي الأمر: لا يدفعه ولا يراه. */
    if ($tq_p['until'] === 0) {
        $tq_due_total += $tq_t['pending'];
        $tq_due_count += $tq_t['pending_count'];
    }
}
unset($tq_p);

$tq_CIp = &get_instance();
$tq_CIp->load->model('taqdar_tap_model');
$tq_card_ready = false;
try { $tq_card_ready = (bool) $tq_CIp->taqdar_tap_model->ready(); } catch (Throwable $e) {}

/* أسماء قنوات الدفع بالعربية.
   الشاشة كانت تطبع مفتاح القناة كما هو (`bank_transfer`, `stripe`)، وهي
   بوابة عربية بالكامل — وسطر إنجليزي واحد وسط جدول عربي يقرأ خطأ لا
   بيانات. وما لا اسم له يعرض كما هو بدل أن يخفى: قناة مجهولة خبر. */
$tq_methods = [
    /* «tap» اسم بوابة الدفع لا اسم طريقة يعرفها ولي الأمر. */
    'tap'           => t('بطاقة'),
    'card'          => t('بطاقة'),
    'manual'        => t('تحويل بنكي'),
    'bank_transfer' => t('تحويل بنكي'),
    'bank'          => t('تحويل بنكي'),
    'mada'          => t('بطاقة مدى'),
    'stcpay'        => t('محفظة STC Pay'),
    'urpay'         => t('محفظة urpay'),
    'stripe'        => t('بطاقة'),
    'paypal'        => t('باي بال'),
    'wallet'        => t('رصيد المحفظة'),
    'free'          => t('مجانا'),
];

$tq_has_rows = false;
foreach ($tq_people as $tq_p) {
    if ($tq_p['rows']) {
        $tq_has_rows = true;
        break;
    }
}

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <div class="tq-grid tq-grid--<?php echo $tq_due_count > 0 ? '3' : '2'; ?> tq-section">
            <div class="tq-pastel tq-pastel--mint">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro"><?php echo t('هذا الشهر'); ?></span>
                    <span class="tq-pastel__icon" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('wallet'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo tq_sar($tq_month_total); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0"><?php echo t('ما دفع لك ولأبنائك منذ أول الشهر'); ?></p>
            </div>

            <div class="tq-pastel tq-pastel--sky">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro"><?php echo t('الإجمالي'); ?></span>
                    <span class="tq-pastel__icon" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('file'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo tq_sar($tq_all_total); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0"><?php echo t('منذ أول اشتراك — المدفوع وحده'); ?></p>
            </div>

            <?php if ($tq_due_count > 0): ?>
                <?php /* المعلق لا يجمع مع المدفوع: مال لم يخرج بعد. وهو
                         أهم ما في الصفحة لأن عليه يتوقف اشتراك الابن. */ ?>
                <div class="tq-pastel tq-pastel--peach">
                    <div class="tq-row tq-row--between">
                        <span class="tq-pastel__label tq-micro"><?php echo t('لم تدفع بعد'); ?></span>
                        <span class="tq-pastel__icon" style="color:var(--tq-peach-ink)" aria-hidden="true"><?php echo tq_icon('clock'); ?></span>
                    </div>
                    <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo tq_sar($tq_due_total); ?></p>
                    <p class="tq-pastel__body tq-caption" style="margin:0">
                        <?php echo tq_count_units($tq_due_count, t('فاتورة'), t('فاتورتان'), t('فاتورتين'), t('فواتير'), t('فاتورة'), null, 'nom'); ?>
                        <?php echo t('تنتظر الدفع — ادفعها أو ألغها من السجل تحت'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($tq_has_rows): ?>

            <?php foreach ($tq_people as $tq_p): ?>
                <?php if (!$tq_p['rows']) { continue; } ?>
                <section class="tq-section" aria-labelledby="tq-pay-<?php echo (int) $tq_p['id']; ?>">
                    <div class="tq-sectionhead">
                        <h2 id="tq-pay-<?php echo (int) $tq_p['id']; ?>"><?php echo html_escape($tq_p['name']); ?></h2>
                        <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_p['rows']) . TQ_PDI; ?></span>
                    </div>

                    <div class="tq-card">
                        <div class="tq-table-wrap">
                        <table class="tq-table">
                            <caption class="tq-sr"><?php echo t('فواتير'); ?> <?php echo html_escape($tq_p['name']); ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo t('التاريخ'); ?></th>
                                    <th scope="col"><?php echo t('ما اشتري'); ?></th>
                                    <th scope="col"><?php echo t('المبلغ'); ?></th>
                                    <th scope="col"><?php echo t('الحالة'); ?></th>
                                    <th scope="col"><?php echo t('رقم العملية'); ?></th>
                                    <th scope="col"><?php echo t('الإجراء'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tq_p['rows'] as $tq_r): ?>
                                    <?php
                                    $tq_kind = $tq_r['status'] === 'paid' ? 'mastered'
                                        : ($tq_r['status'] === 'unpaid' ? 'due' : 'idle');
                                    ?>
                                    <tr>
                                        <td data-label="<?php echo te('التاريخ'); ?>">
                                            <?php echo (int) $tq_r['ts'] > 0
                                                ? tq_num(date('Y-m-d', (int) $tq_r['ts']), 'tq-num--sm')
                                                : '<span class="tq-caption">—</span>'; ?>
                                        </td>
                                        <td data-label="<?php echo te('ما اشتري'); ?>">
                                            <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_r['title']); ?></span>
                                            <?php if ($tq_r['method'] !== ''): ?>
                                                <span class="tq-micro" style="display:block">
                                                    <?php echo html_escape($tq_methods[$tq_r['method']] ?? $tq_r['method']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="<?php echo te('المبلغ'); ?>"><?php echo tq_sar($tq_r['amount'], 2); ?></td>
                                        <td data-label="<?php echo te('الحالة'); ?>"><?php echo tq_badge($tq_kind, $tq_r['label']); ?></td>
                                        <td data-label="<?php echo te('رقم العملية'); ?>">
                                            <span class="tq-num tq-num--sm"><?php echo html_escape($tq_r['ref'] ?: '—'); ?></span>
                                        </td>
                                        <td data-label="<?php echo te('الإجراء'); ?>">
                                            <?php /* TQ-INVOICE-CANCEL — الفاتورة غير المدفوعة كانت بلا باب:
                                                     لا دفع ولا إلغاء، فتبقى معلقة إلى الأبد. */ ?>
                                            <?php if (!empty($tq_r['payable']) && !$tq_p['self'] && $tq_p['until'] === 0): ?>
                                                <span class="tq-row" style="gap:var(--tq-space-xs);flex-wrap:wrap">
                                                    <?php if ($tq_card_ready): ?>
                                                        <form method="post" action="<?php echo base_url('student/pay-invoice'); ?>" class="tq-form-inline">
                                                            <?php echo tq_csrf(); ?>
                                                            <input type="hidden" name="invoice_id" value="<?php echo (int) $tq_r['invoice_id']; ?>">
                                                            <button class="tq-btn tq-btn--primary tq-btn--sm" type="submit"><?php echo t('ادفع الآن'); ?></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if (!empty($tq_r['cancellable'])): ?>
                                                        <form method="post" action="<?php echo base_url('parent/pay/cancel'); ?>" class="tq-form-inline"
                                                              data-tq-confirm-title="<?php echo te('إلغاء هذه الفاتورة؟'); ?>"
                                                              data-tq-confirm="<?php echo te('تشطب الفاتورة ولا يفتح ما اشتري بها.'); ?>"
                                                              data-tq-confirm-ok="<?php echo te('ألغ الفاتورة'); ?>" data-tq-confirm-tone="danger">
                                                            <?php echo tq_csrf(); ?>
                                                            <input type="hidden" name="invoice_id" value="<?php echo (int) $tq_r['invoice_id']; ?>">
                                                            <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit"><?php echo t('إلغاء'); ?></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="tq-caption">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>

            <?php if ($tq_trimmed && !$tq_show_all): ?>
                <p style="text-align:center">
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('parent/payments?all=1'); ?>"><?php echo t('عرض كل العمليات'); ?></a>
                </p>
            <?php endif; ?>

        <?php else: ?>

            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('wallet', 24); ?></span>
                <h2 class="tq-empty__title"><?php echo t('لا مدفوعات بعد'); ?></h2>
                <p class="tq-empty__text">
                    <?php echo t('كل عملية دفع تخصك أو تخص أبناءك المربوطين بحسابك ستظهر هنا بتاريخها ومبلغها ورقم عمليتها — بلا مصطلحات ولا رسوم خفية.'); ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>"><?php echo t('عودة إلى أبنائي'); ?></a>
            </div>

        <?php endif; ?>

        <?php /* TQ-SPAM — فواتير ولي الأمر تخرج بالبريد كذلك. مطوي: هذه
                 شاشة سجل لا شاشة انتظار رسالة. */ ?>
        <?php echo tq_spam_notice(array('compact' => true, 'id' => 'tq-spam-ppay')); ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('طرق الدفع'); ?></h2></div>
            <?php /* TQ-PAY-METHODS — ما يقبله الدفع فعلا، كما في شاشة الدفع. كانت
                     القائمة تعد بـSTC Pay وurpay وهما غير متاحين عند الدفع، وتسكت عن
                     فيزا وماستركارد وApple Pay وهي المتاحة. */ ?>
            <ul class="tq-stack tq-caption">
                <?php $tq_ways = $tq_card_ready
                    ? array(t('بطاقات مدى وفيزا وماستركارد'), t('Apple Pay'), t('تحويل بنكي'))
                    : array(t('تحويل بنكي')); ?>
                <?php foreach ($tq_ways as $tq_w): ?>
                    <li class="tq-row" style="gap:var(--tq-space-s)">
                        <span aria-hidden="true" style="color:var(--tq-teal)"><?php echo tq_icon('check', 16); ?></span>
                        <?php echo html_escape($tq_w); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro"><?php echo t('استرداد'); ?></span>
            <?php /* TQ-REFUND-ONE — مصدر واحد للشروط: صفحة سياسة الاسترجاع التي
                     تحررها الإدارة. كان المربع يقول «١٤ يوما» والصفحة «٧ أيام بشرط
                     ألا يتجاوز ما شوهد ٢٠٪» — وعدان مختلفان بمال واحد. */ ?>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo t('مدة الاسترجاع وشروطه مكتوبة في سياسة الاسترجاع المعلنة، وهي التي تطبق على كل ما تدفعه. ولطلبه راسل إدارة المنصة من صفحة الرسائل.'); ?>
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--sm" style="margin-block-start:var(--tq-space-s)"
               href="<?php echo base_url('refund'); ?>"><?php echo t('اقرأ سياسة الاسترجاع'); ?></a>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
