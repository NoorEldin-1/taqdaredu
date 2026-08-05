<?php
/**
 * بوّابة وليّ الأمر — المدفوعات.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية — كل شيء واضح ومفهوم من
 * نظرة واحدة وبلا مصطلحات: تاريخ، وما اشتُري، ولمن، وكم. لا أكثر.
 *
 * ما يظهر هنا حقيقي بالكامل من جدول `payment`:
 *   · مدفوعاتك أنت           — `payment.user_id = <وليّ الأمر>`
 *   · مدفوعات كل ابن مربوط   — عبر `parent_links` حين يوجد
 * والعملة الريال السعودي عبر tq_sar().
 *
 * ما ينتظر جدولًا:
 *   `parent_links` — ربط الوليّ بابنه؛ وبدونه تظهر مدفوعاته هو وحدها،
 *                    ولا تُخمَّن قرابة ولا تُفتح فاتورة حساب آخر.
 *   الفاتورة المطبوعة — تنتظر برنامج فاتورة رسميًّا؛ ويُعرض حتى ذلك
 *                    رقم العملية كما هو، فهو ما يُراجَع به الدفع.
 */

$tq_nav   = 'payments';
$tq_role  = 'parent';
$tq_title = 'المدفوعات';
$tq_sub   = 'كل ما دفعته، ولمن، ومتى';
$tq_icon  = 'wallet';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_people = [
    ['id' => $tq_uid, 'name' => 'مدفوعاتي', 'self' => true],
];

if ($this->db->table_exists('parent_links')) {
    foreach ($this->db->query(
        "SELECT u.id, u.first_name, u.last_name
           FROM parent_links pl
           JOIN users u ON u.id = pl.student_id
          WHERE pl.parent_user_id = ? AND pl.status = 'active'
          ORDER BY u.first_name ASC",
        [$tq_uid]
    )->result_array() as $tq_c) {
        $tq_people[] = [
            'id'   => (int) $tq_c['id'],
            'name' => trim($tq_c['first_name'] . ' ' . $tq_c['last_name']),
            'self' => false,
        ];
    }
}

$tq_month_start = strtotime(date('Y-m-01 00:00:00'));
$tq_month_total = 0;
$tq_all_total   = 0;

foreach ($tq_people as &$tq_p) {
    $tq_p['rows'] = $this->db->query(
        "SELECT p.id, p.amount, p.date_added, p.payment_type, p.transaction_id,
                c.title AS course_title
           FROM payment p
           LEFT JOIN course c ON c.id = p.course_id
          WHERE p.user_id = ?
          ORDER BY p.date_added DESC
          LIMIT 50",
        [(int) $tq_p['id']]
    )->result_array();

    foreach ($tq_p['rows'] as $tq_r) {
        $tq_all_total += (float) $tq_r['amount'];
        if ((int) $tq_r['date_added'] >= $tq_month_start) {
            $tq_month_total += (float) $tq_r['amount'];
        }
    }
}
unset($tq_p);

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

        <div class="tq-grid tq-grid--2 tq-section">
            <div class="tq-pastel tq-pastel--mint">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro">هذا الشهر</span>
                    <span class="tq-pastel__icon" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('wallet'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo tq_sar($tq_month_total); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0">مجموع ما دفعته منذ أول الشهر</p>
            </div>

            <div class="tq-pastel tq-pastel--sky">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro">الإجمالي</span>
                    <span class="tq-pastel__icon" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('file'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo tq_sar($tq_all_total); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0">منذ أول اشتراك</p>
            </div>
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
                        <table class="tq-table">
                            <caption class="tq-sr">فواتير <?php echo html_escape($tq_p['name']); ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col">التاريخ</th>
                                    <th scope="col">ما اشتُري</th>
                                    <th scope="col">المبلغ</th>
                                    <th scope="col">رقم العملية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tq_p['rows'] as $tq_r): ?>
                                    <tr>
                                        <td data-label="التاريخ"><?php echo tq_num(date('Y-m-d', (int) $tq_r['date_added']), 'tq-num--sm'); ?></td>
                                        <td data-label="ما اشتُري">
                                            <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_r['course_title'] ?: 'اشتراك'); ?></span>
                                        </td>
                                        <td data-label="المبلغ"><?php echo tq_sar($tq_r['amount']); ?></td>
                                        <td data-label="رقم العملية">
                                            <span class="tq-num tq-num--sm"><?php echo html_escape($tq_r['transaction_id'] ?: '—'); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>

        <?php else: ?>

            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('wallet', 24); ?></span>
                <h2 class="tq-empty__title">لا مدفوعات بعد</h2>
                <p class="tq-empty__text">
                    كل عملية دفع تخصّك أو تخصّ أبناءك المربوطين بحسابك ستظهر هنا بتاريخها ومبلغها
                    ورقم عمليتها — بلا مصطلحات ولا رسوم خفيّة.
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>">عودة إلى أبنائي</a>
            </div>

        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">طرق الدفع</h2></div>
            <ul class="tq-stack tq-caption">
                <li class="tq-row" style="gap:var(--tq-space-s)">
                    <span aria-hidden="true" style="color:var(--tq-teal)"><?php echo tq_icon('check', 16); ?></span>
                    بطاقة مدى
                </li>
                <li class="tq-row" style="gap:var(--tq-space-s)">
                    <span aria-hidden="true" style="color:var(--tq-teal)"><?php echo tq_icon('check', 16); ?></span>
                    محفظة STC Pay
                </li>
                <li class="tq-row" style="gap:var(--tq-space-s)">
                    <span aria-hidden="true" style="color:var(--tq-teal)"><?php echo tq_icon('check', 16); ?></span>
                    محفظة urpay
                </li>
                <li class="tq-row" style="gap:var(--tq-space-s)">
                    <span aria-hidden="true" style="color:var(--tq-teal)"><?php echo tq_icon('check', 16); ?></span>
                    تحويل بنكي
                </li>
            </ul>
        </div>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro">استرداد</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo tq_iso('لك 14 يومًا من تاريخ الشراء لطلب الاسترداد. راسِلنا من صفحة الرسائل ونعالج طلبك.'); ?>
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
