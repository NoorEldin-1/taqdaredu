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
    ['id' => $tq_uid, 'name' => t('مدفوعاتي'), 'self' => true],
];

foreach ($tq_pm->children($tq_uid) as $tq_c) {
    $tq_people[] = [
        'id'   => (int) $tq_c['student_id'],
        'name' => t('مدفوعات ') . $tq_c['name'],
        'self' => false,
    ];
}

$tq_month_start = strtotime(date('Y-m-01 00:00:00'));
$tq_month_total = 0;
$tq_all_total   = 0;
$tq_due_total   = 0;
$tq_due_count   = 0;

foreach ($tq_people as &$tq_p) {
    $tq_p['rows'] = $tq_pm->payments_of($tq_p['id']);
    $tq_t         = $tq_pm->payment_totals($tq_p['rows'], $tq_month_start);

    $tq_month_total += $tq_t['month'];
    $tq_all_total   += $tq_t['all'];
    $tq_due_total   += $tq_t['pending'];
    $tq_due_count   += $tq_t['pending_count'];
}
unset($tq_p);

/* أسماء قنوات الدفع بالعربية.
   الشاشة كانت تطبع مفتاح القناة كما هو (`bank_transfer`, `stripe`)، وهي
   بوابة عربية بالكامل — وسطر إنجليزي واحد وسط جدول عربي يقرأ خطأ لا
   بيانات. وما لا اسم له يعرض كما هو بدل أن يخفى: قناة مجهولة خبر. */
$tq_methods = [
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
                        <span class="tq-pastel__label tq-micro"><?php echo t('بانتظار التحويل'); ?></span>
                        <span class="tq-pastel__icon" style="color:var(--tq-peach-ink)" aria-hidden="true"><?php echo tq_icon('clock'); ?></span>
                    </div>
                    <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo tq_sar($tq_due_total); ?></p>
                    <p class="tq-pastel__body tq-caption" style="margin:0">
                        <?php echo tq_count_units($tq_due_count, t('فاتورة'), t('فاتورتان'), t('فاتورتين'), t('فواتير'), t('فاتورة'), null, 'nom'); ?>
                        <?php echo t('لم يفعل اشتراكها بعد'); ?>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>

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
            <ul class="tq-stack tq-caption">
                <li class="tq-row" style="gap:var(--tq-space-s)">
                    <span aria-hidden="true" style="color:var(--tq-teal)"><?php echo tq_icon('check', 16); ?></span>
                    <?php echo t('بطاقة مدى'); ?>
                </li>
                <li class="tq-row" style="gap:var(--tq-space-s)">
                    <span aria-hidden="true" style="color:var(--tq-teal)"><?php echo tq_icon('check', 16); ?></span>
                    <?php echo t('محفظة STC Pay'); ?>
                </li>
                <li class="tq-row" style="gap:var(--tq-space-s)">
                    <span aria-hidden="true" style="color:var(--tq-teal)"><?php echo tq_icon('check', 16); ?></span>
                    <?php echo t('محفظة urpay'); ?>
                </li>
                <li class="tq-row" style="gap:var(--tq-space-s)">
                    <span aria-hidden="true" style="color:var(--tq-teal)"><?php echo tq_icon('check', 16); ?></span>
                    <?php echo t('تحويل بنكي'); ?>
                </li>
            </ul>
        </div>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro"><?php echo t('استرداد'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo tq_iso(t('لك 14 يوما من تاريخ الشراء لطلب الاسترداد. راسلنا من صفحة الرسائل ونعالج طلبك.')); ?>
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
