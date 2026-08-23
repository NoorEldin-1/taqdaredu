<?php
/**
 * بوابة المعلم — المحفظة والأرباح.
 *
 * القاعدة الحاكمة لبوابة المعلم كلها:
 * المعلم مسند إلى مادة وصف بعينهما، وما لم يسند إليه لا يظهر في لوحته
 * أصلا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زر في الواجهة ليس صلاحية. لذلك كشف الحساب أدناه
 * مقيد بمحفظة هذا المعلم وحدها، وسجل السحب مقيد بمعرفه هو.
 *
 * ما تغير هنا: **الشاشة لم تعد تحسب.**
 * كانت تفتح `payment` عند كل عرض وتجمع `instructor_revenue` وتقسمها بتاريخ
 * البيع — فكان الرقم صحيحا ما دام لا شيء يحدث، وينهار عند أول استرداد أو
 * تسوية أو سحب جزئي، ولا يعطي كشف حساب بل قائمة مبيعات. الآن كل حركة
 * مقيدة في `wallet_entries`، والرصيد مجموع قيوده حرفيا، والشاشة تعرض
 * ما في الدفتر ولا تشتق منه شيئا: لا جمع ولا طرح ولا قسمة في هذا الملف.
 * انظر `Taqdar_wallet_model` — هو صاحب الحساب كله.
 *
 * عمولة المنصة تظهر على كل عملية بيع منفردة لا كمبلغ إجمالي آخر الشهر:
 * المعلم يجب أن يستطيع تتبع أي ريال إلى مصدره — أي عملية، أي كورس، أي يوم.
 * ولذلك عمود «عمولة المنصة» في كشف الحساب صف بصف، لا سطر واحد في الأسفل.
 *
 * البيع يظل معلقا مدة نافذة الاسترداد ثم يتحرر، ويعرض ذلك بوضوح
 * لا يكتشف: لكل عملية معلقة تاريخ تحررها وكم بقي عليه. والنافذة نفسها
 * إعداد (`taqdar_refund_window_days`) لا رقم مكتوب في شيفرة الشاشة، وكذلك
 * الحد الأدنى للسحب (`taqdar_payout_min_sar`).
 *
 * والمال هللات صحيحة في الدفتر كله؛ القسمة على مئة هنا حد عرض أخير
 * لا حساب — ولا يخفى الكسر: مبلغ فيه هللات يعرض بخانتيه.
 */

$tq_nav   = 'wallet';
$tq_role  = 'teacher';
$tq_title = 'المحفظة والأرباح';
$tq_sub   = 'المتاح والمعلق منفصلان، وكل ريال يتتبع إلى مصدره';
$tq_icon  = 'wallet';

$tq_uid = (int) $this->session->userdata('user_id');

/* الحمال ينسخ خصائص المتحكم إلى نفسه قبل تضمين العرض، فما يحمل داخل
   العرض يسند إلى المتحكم لا إليه — ولذلك النسخة الحية صراحة هنا. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_wallet_model');
$tq_w  = $tq_ci->taqdar_wallet_model->screen($tq_uid);

/* قسمات الباقات — الدفتر يقول «حصتك ١٠.٥٩»، وهذا يقول **لماذا**.
   وبيع الباقة يختلف عن بيع الكورس في أن المبلغ لم يدفع فيك وحدك: الطالب
   اشترى باقة تفتح محتوى سبعة معلمين، ونصيبك منها بعدد دروسك. ورقم بلا
   سبب في شاشة مال يقرأ خصما. */
$tq_ci->load->model('taqdar_revenue_model');
$tq_shares = $tq_ci->taqdar_revenue_model->shares_for_teacher($tq_uid);
$tq_pool_default = $tq_ci->taqdar_revenue_model->default_pool_percent();

/** هللات ⇐ نص ريالات. الخانتان تظهران حين يكون فيه هللات، فلا يدور مال. */
$tq_money = function ($halalas) {
    $h = (int) $halalas;
    return tq_sar($h / 100, ($h % 100 === 0) ? 0 : 2);
};

$tq_states = array(
    'pending'   => array('due',      'معلق'),
    'available' => array('progress', 'متاح للسحب'),
    'paid'      => array('mastered', 'حول إليك'),
    'refunded'  => array('late',     'مسترد'),
);

$tq_commission_total = 0;
$tq_has_retained     = false;
foreach ($tq_w['statement'] as $tq_s) {
    $tq_commission_total += (int) $tq_s['commission'];
    if ((int) $tq_s['retained'] !== 0) $tq_has_retained = true;
}

$tq_ok  = $this->session->flashdata('wallet_ok')    ?: $this->session->flashdata('flash_message');
$tq_err = $this->session->flashdata('wallet_error') ?: $this->session->flashdata('error_message');

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <?php if ($tq_ok): ?>
            <div class="tq-pastel tq-pastel--mint tq-section" role="status">
                <p class="tq-pastel__body" style="margin:0"><?php echo tq_iso(html_escape($tq_ok)); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($tq_err): ?>
            <div class="tq-pastel tq-pastel--rose tq-section" role="alert">
                <p class="tq-pastel__body" style="margin:0"><?php echo tq_iso(html_escape($tq_err)); ?></p>
            </div>
        <?php endif; ?>

        <!-- المتاح والمعلق منفصلان بوضوح لا يكتشف -->
        <div class="tq-grid tq-grid--3 tq-section">
            <div class="tq-pastel tq-pastel--mint">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro">متاح للسحب</span>
                    <span class="tq-pastel__icon" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('wallet'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo $tq_money($tq_w['available']); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0">تجاوز نافذة الاسترداد</p>
            </div>

            <div class="tq-pastel tq-pastel--peach">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro">معلق مؤقتا</span>
                    <span class="tq-pastel__icon" style="color:var(--tq-peach-ink)" aria-hidden="true"><?php echo tq_icon('clock'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo $tq_money($tq_w['pending']); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0">
                    <?php echo tq_iso('يتحرر بعد ' . (int) $tq_w['refund_days'] . ' يوما من البيع'); ?>
                </p>
            </div>

            <div class="tq-pastel tq-pastel--sky">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro">حول إليك</span>
                    <span class="tq-pastel__icon" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('check-badge'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo $tq_money($tq_w['transferred']); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0">مجموع ما استلمته سابقا</p>
            </div>
        </div>

        <!-- كشف الحساب: العمولة صفا بصف لا مبلغا إجماليا آخر الشهر -->
        <section class="tq-section" aria-labelledby="tq-stmt-h">
            <div class="tq-sectionhead">
                <h2 id="tq-stmt-h">كشف الحساب</h2>
                <?php if ($tq_w['statement']): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_w['statement']) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_w['statement']): ?>
                <div class="tq-card">
                    <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                        كل سطر عملية بيع واحدة كما قيدت في دفترك: مبلغها، وعمولة المنصة عليها،
                        وحصتك منها، ومتى تتحرر. والأرصدة أعلاه حاصل جمع هذه القيود لا حسابا مستقلا عنها.
                        وسطر الباقة يحمل تحته سبب رقمه: سعرها، ووعاء معلميها، وكم درسا لك فيها.
                    </p>
                    <table class="tq-table">
                        <caption class="tq-sr">مبيعاتك: المبلغ وعمولة المنصة وحصتك وحالة كل عملية</caption>
                        <thead>
                            <tr>
                                <th scope="col">التاريخ</th>
                                <th scope="col">ما بيع</th>
                                <th scope="col">مبلغ البيع</th>
                                <th scope="col">عمولة المنصة</th>
                                <?php if ($tq_has_retained): ?>
                                    <th scope="col">ضريبة ومحتجز</th>
                                <?php endif; ?>
                                <th scope="col">حصتك</th>
                                <th scope="col">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_w['statement'] as $tq_s): ?>
                                <?php
                                $tq_kind  = isset($tq_states[$tq_s['state']]) ? $tq_states[$tq_s['state']] : $tq_states['pending'];
                                $tq_label = $tq_kind[1];
                                if ($tq_s['state'] === 'pending') {
                                    /* «يتحرر بعد 4 يوما» خطأ في تمييز العدد، و«بعد 0 يوما»
                                       أسوأ منه: بيع بلغ موعد تحرره يقال عنه إنه ينتظر صفرا. */
                                    $tq_label .= (int) $tq_s['days_left'] > 0
                                        ? ' · يتحرر بعد ' . tq_days((int) $tq_s['days_left'])
                                        : ' · يتحرر مع أول تحديث';
                                }
                                ?>
                                <?php
                                /* بيع باقة: المبلغ لم يدفع فيك وحدك، فيقال بأي
                                   نسبة صار نصيبك ما صار. والسطر تحت الاسم لا
                                   عمود ثامن — الجدول بسبعة أعمدة أصلا، وثامن
                                   يجعله يمرر أفقيا على الجوال. */
                                $tq_sh = isset($tq_shares[$tq_s['origin']]) ? $tq_shares[$tq_s['origin']] : null;
                                ?>
                                <tr>
                                    <td data-label="التاريخ"><?php echo tq_num($tq_s['date'], 'tq-num--sm'); ?></td>
                                    <td data-label="الكورس">
                                        <?php echo html_escape($tq_s['subject']); ?>
                                        <?php if ($tq_sh):
                                            $tq_wt = (int) $tq_sh['weight_total'] > 0
                                                   ? round((int) $tq_sh['weight'] * 100 / (int) $tq_sh['weight_total'], 1) : 0;
                                        ?>
                                            <span class="tq-micro tq-stmt__why">
                                                <?php echo tq_iso(
                                                    'باقة بـ' . number_format(((int) $tq_sh['gross_halalas']) / 100, 2) . ' ر.س · '
                                                    . 'وعاء المعلمين ' . rtrim(rtrim(number_format((float) $tq_sh['pool_percent'], 2), '0'), '.')
                                                    . '% = ' . number_format(((int) $tq_sh['pool_halalas']) / 100, 2) . ' ر.س · '
                                                    . 'دروسك ' . (int) $tq_sh['lessons'] . ' من ' . (int) $tq_sh['lessons_total']
                                                    . ' (' . $tq_wt . '% من الوعاء)'
                                                ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="مبلغ البيع"><?php echo $tq_money($tq_s['gross']); ?></td>
                                    <td data-label="عمولة المنصة"><?php echo $tq_money($tq_s['commission']); ?></td>
                                    <?php if ($tq_has_retained): ?>
                                        <td data-label="ضريبة ومحتجز"><?php echo $tq_money($tq_s['retained']); ?></td>
                                    <?php endif; ?>
                                    <td data-label="حصتك"><span class="tq-strong"><?php echo $tq_money($tq_s['share']); ?></span></td>
                                    <td data-label="الحالة"><?php echo tq_badge($tq_kind[0], $tq_label); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
                        مجموع عمولة المنصة في هذا الكشف: <?php echo $tq_money($tq_commission_total); ?> —
                        وهو حاصل جمع العمولات أعلاه لا رقم مستقل عنها.
                    </p>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('file', 24); ?></span>
                    <h3 class="tq-empty__title">لا مبيعات بعد</h3>
                    <p class="tq-empty__text">
                        أول عملية بيع في كورساتك ستظهر هنا بمبلغها وعمولة المنصة عليها وحصتك منها،
                        وبتاريخ تحررها من نافذة الاسترداد.
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/courses'); ?>">كورساتي</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- سجل السحب -->
        <section aria-labelledby="tq-payouts-h">
            <div class="tq-sectionhead"><h2 id="tq-payouts-h">طلبات السحب السابقة</h2></div>

            <?php if ($tq_w['payouts']): ?>
                <div class="tq-card">
                    <table class="tq-table">
                        <caption class="tq-sr">طلبات السحب التي قدمتها وقنواتها وحالتها</caption>
                        <thead>
                            <tr>
                                <th scope="col">التاريخ</th>
                                <th scope="col">المبلغ</th>
                                <th scope="col">القناة</th>
                                <th scope="col">الوجهة</th>
                                <th scope="col">الحالة</th>
                                <th scope="col"><span class="tq-sr">إجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_w['payouts'] as $tq_p): ?>
                                <?php
                                $tq_ch = isset($tq_w['channels'][$tq_p['channel']])
                                       ? $tq_w['channels'][$tq_p['channel']]['label']
                                       : 'تحدد مع الإدارة';
                                if ((int) $tq_p['status'] === 1) {
                                    $tq_pstate = tq_badge('mastered', 'حول');
                                } elseif ((int) $tq_p['status'] === 2) {
                                    $tq_pstate = tq_badge('idle', 'ألغي — عاد إلى رصيدك');
                                } else {
                                    $tq_pstate = tq_badge('due', 'قيد المعالجة · محجوز من رصيدك');
                                }
                                ?>
                                <tr>
                                    <td data-label="التاريخ"><?php echo tq_num($tq_p['date'], 'tq-num--sm'); ?></td>
                                    <td data-label="المبلغ"><?php echo $tq_money($tq_p['amount_halalas']); ?></td>
                                    <td data-label="القناة"><?php echo html_escape($tq_ch); ?></td>
                                    <td data-label="الوجهة"><?php echo tq_num($tq_p['destination_masked'], 'tq-num--sm'); ?></td>
                                    <td data-label="الحالة"><?php echo $tq_pstate; ?></td>
                                    <?php /* الإلغاء — كان النص أعلاه يعد بأن «إلغاء الطلب يعيده
                                             إلى المتاح» ولا زر في الشاشة يفعله، و`cancel_payout()`
                                             في النموذج مكتوبة تنتظر بابا. ويعرض للمعلق وحده:
                                             المحول لا يلغى، والملغى لا يلغى مرتين. */ ?>
                                    <td data-label="إجراءات">
                                        <?php if ((int) $tq_p['status'] === 0): ?>
                                            <form method="post" action="<?php echo base_url('teacher/wallet/cancel'); ?>"
                                                  data-tq-confirm-title="إلغاء طلب سحب <?php echo html_escape(trim(strip_tags($tq_money($tq_p['amount_halalas'])))); ?>؟"
                                                  data-tq-confirm="يعود المبلغ إلى رصيدك المتاح فورا، ويقيد ذلك في دفترك."
                                                  data-tq-confirm-note="يبقى الطلب في السجل بحالة «ألغي» — الدفتر لا يمحو سطرا."
                                                  data-tq-confirm-ok="ألغي الطلب"
                                                  data-tq-confirm-tone="danger">
                                                <?php echo tq_csrf(); ?>
                                                <input type="hidden" name="payout_id" value="<?php echo (int) $tq_p['id']; ?>">
                                                <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit">إلغاء</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="tq-micro">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('download', 24); ?></span>
                    <h3 class="tq-empty__title">لم تطلب سحبا بعد</h3>
                    <p class="tq-empty__text">حين تطلب سحب رصيدك المتاح، يظهر الطلب هنا بتاريخه وقناته ووجهته وحالته.</p>
                    <a class="tq-btn tq-btn--secondary" href="#tq-withdraw">طلب سحب</a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="tq-aside">

        <?php if ((int) $tq_w['locked'] > 0): ?>
            <div class="tq-pastel tq-pastel--lilac">
                <span class="tq-pastel__label tq-micro">محجوز الآن</span>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo $tq_money($tq_w['locked']); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0">
                    خرج من المتاح مقابل طلبات سحب قائمة، ولم يحول بعد. إلغاء الطلب يعيده إلى المتاح.
                </p>
            </div>
        <?php endif; ?>

        <!--
            طلب سحب. الحقول بأسمائها القديمة (`withdrawal_amount` بالريالات،
            `payment_type` للقناة، `destination` للوجهة) فلا يكسر النموذج
            ما يقرؤه المتحكم. والقيد الحقيقي — الحد الأدنى والرصيد الكافي
            وصحة القناة — في `Taqdar_wallet_model::request_payout` في الخادم؛
            وما هنا (min/max) تيسير للمستخدم لا حراسة، فالحارس لا يكون في المتصفح.
        -->
        <?php
        /* المدى المعروض على الحقل.
           كان `min` الحد الأدنى و`max` الرصيد المتاح دائما — فحين يكون
           المتاح صفرا (وهو حال كل معلم جديد) يصير الحقل `min=100 max=0`:
           مدى مستحيل يرفض المتصفح كل رقم يكتب فيه ولا يقول لماذا. والزر
           معطل عندها، لكن الحقل يبقى مفتوحا يستقبل ما لا يقبله.
           فحين لا يبلغ المتاح الحد الأدنى يعطل الحقل نفسه، ويقال السبب. */
        $tq_can_withdraw = ((int) $tq_w['available'] >= (int) $tq_w['min_payout'])
                        && (int) $tq_w['min_payout'] > 0;
        ?>
        <form class="tq-card" id="tq-withdraw" method="post"
              action="<?php echo base_url('teacher/wallet/withdraw'); ?>">
            <?php echo tq_csrf(); ?>
            <div class="tq-card__head"><h2 class="tq-card__title">طلب سحب</h2></div>

            <div class="tq-field">
                <label class="tq-field__label" for="tq-amount">المبلغ بالريال</label>
                <input class="tq-input" id="tq-amount" type="number" name="withdrawal_amount"
                       min="<?php echo number_format((int) $tq_w['min_payout'] / 100, 2, '.', ''); ?>"
                       max="<?php echo number_format((int) $tq_w['available'] / 100, 2, '.', ''); ?>"
                       step="0.01" inputmode="decimal" required
                       <?php echo $tq_can_withdraw ? '' : 'disabled'; ?>>
                <span class="tq-field__msg tq-field__hint">
                    المتاح الآن <?php echo $tq_money($tq_w['available']); ?> —
                    <?php echo tq_iso('والحد الأدنى للسحب ' . number_format($tq_w['min_payout'] / 100, 2) . ' ريال.'); ?>
                    والمعلق لا يسحب قبل أن يتحرر.
                </span>
            </div>

            <fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-l)">
                <legend class="tq-field__label" style="padding:0">قناة التحويل</legend>
                <?php
                /* القنوات تقرأ من `Taqdar_wallet_model::$CHANNELS` — فإضافة
                   قناة هناك تظهر هنا بلا لمس هذا الملف. ومعها **مثالها**:
                   المثال يصحح الشكل قبل الإرسال أسرع من قاعدة تقرأ. */
                $tq_first = true;
                $tq_ch_js = array();
                foreach ($tq_w['channels'] as $tq_key => $tq_c) {
                    $tq_ch_js[$tq_key] = array(
                        'hint'    => isset($tq_c['hint']) ? $tq_c['hint'] : '',
                        'example' => isset($tq_c['example']) ? $tq_c['example'] : '',
                        'error'   => isset($tq_c['error']) ? $tq_c['error'] : '',
                    );
                }
                ?>
                <?php foreach ($tq_w['channels'] as $tq_key => $tq_c): ?>
                    <span class="tq-row" style="gap:var(--tq-space-s);margin-block-end:var(--tq-space-s)">
                        <input type="radio" id="tq-ch-<?php echo $tq_key; ?>" name="payment_type"
                               value="<?php echo $tq_key; ?>" <?php echo $tq_first ? 'checked' : ''; ?> required
                               data-tq-channel
                               <?php echo $tq_can_withdraw ? '' : 'disabled'; ?>>
                        <label for="tq-ch-<?php echo $tq_key; ?>">
                            <?php echo html_escape($tq_c['label']); ?>
                            <span class="tq-caption"> — <?php echo html_escape($tq_c['hint']); ?></span>
                        </label>
                    </span>
                <?php $tq_first = false; endforeach; ?>
            </fieldset>

            <div class="tq-field">
                <label class="tq-field__label" for="tq-dest">بيانات التحويل</label>
                <?php /* الحقل يتبع القناة المختارة. وكان يقول «الآيبان السعودي
                         يبدأ بـ SA» أيا كانت القناة — فمن اختار فودافون كاش
                         يقرأ تعليمات آيبان فوق حقل ينتظر رقم جوال مصريا. */ ?>
                <input class="tq-input" id="tq-dest" type="text" name="destination" required
                       dir="ltr" data-tq-dest
                       placeholder="<?php echo html_escape(reset($tq_ch_js)['example']); ?>"
                       <?php echo $tq_can_withdraw ? '' : 'disabled'; ?>>
                <span class="tq-field__msg tq-field__hint" data-tq-dest-hint>
                    <?php echo tq_iso(reset($tq_ch_js)['error']); ?>
                    وتحفظ الوجهة مع الطلب، ولا تظهر بعدها إلا بأربع خاناتها الأخيرة.
                </span>
            </div>

            <script>
            /* الخادم هو الحارس (`destination_error()` في النموذج). وهذه
               الأسطر تيسير: من لم يصله الملف يرى تعليمات القناة الأولى
               ويرد عليه الخادم بالرسالة الصحيحة — لا نموذج يقبل ثم يرفض
               بلا سبب. */
            (function () {
                var meta = <?php echo json_encode($tq_ch_js, JSON_UNESCAPED_UNICODE); ?>;
                var dest = document.querySelector('[data-tq-dest]');
                var hint = document.querySelector('[data-tq-dest-hint]');
                if (!dest || !hint) return;
                var tail = ' وتحفظ الوجهة مع الطلب، ولا تظهر بعدها إلا بأربع خاناتها الأخيرة.';
                Array.prototype.forEach.call(document.querySelectorAll('[data-tq-channel]'), function (r) {
                    r.addEventListener('change', function () {
                        var m = meta[r.value];
                        if (!m) return;
                        dest.placeholder = m.example;
                        dest.value = '';
                        hint.textContent = m.error + tail;
                    });
                });
            })();
            </script>

            <button class="tq-btn tq-btn--primary tq-btn--block" type="submit"
                    aria-describedby="tq-withdraw-note"
                    <?php echo $tq_can_withdraw ? '' : 'disabled'; ?>>
                إرسال الطلب
            </button>
            <p class="tq-field__msg tq-field__hint" id="tq-withdraw-note" style="margin-block-start:var(--tq-space-m)">
                <?php if (!$tq_can_withdraw): ?>
                    <?php echo tq_iso('رصيدك المتاح ' . number_format((int) $tq_w['available'] / 100, 2)
                        . ' ريال، ولم يبلغ الحد الأدنى للسحب (' . number_format((int) $tq_w['min_payout'] / 100, 2)
                        . ' ريال) بعد. والمعلق يتحرر بعد نافذة الاسترداد فينضم إلى المتاح.'); ?>
                <?php else: ?>
                    عند إرسال الطلب يحجز المبلغ من رصيدك المتاح فورا ويقيد في دفترك،
                    فلا يمكن طلبه مرتين.
                <?php endif; ?>
            </p>
        </form>

        <?php /* من أين يأتي المال — قبل «كيف يتحرر».
                 المعلم يفتح هذه الشاشة بسؤالين: «كم لي؟» و«لم هذا الرقم؟».
                 والثاني لا يجيبه كشف الحساب وحده حين يكون البيع باقة: الطالب
                 دفع خمسمئة، وقيد في دفترك عشرة — والفرق ليس عمولة كلها بل
                 حصص زملائك. ورقم بلا سبب في شاشة مال يقرأ خصما. */ ?>
        <div class="tq-pastel tq-pastel--sky tq-section">
            <span class="tq-pastel__label tq-micro">من أين تأتي أرباحك</span>
            <div class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <p style="margin:0 0 var(--tq-space-s)">
                    <b>بيع مباشر</b> — طالب يشتري كورسك أو مسارك وحده. المبلغ كله عن محتواك،
                    وحصتك منه نسبتك المضبوطة على ذلك المسار.
                </p>
                <p style="margin:0 0 var(--tq-space-s)">
                    <b>بيع باقة</b> — طالب يشتري باقة تفتح صفوفا كاملة، فيها محتواك ومحتوى
                    زملائك. والباقة تقسم قسمتين:
                </p>
                <ol style="margin:0 0 var(--tq-space-s);padding-inline-start:var(--tq-space-l);list-style:decimal">
                    <li>
                        يخرج من سعرها <b>وعاء المعلمين</b> —
                        <?php echo tq_iso('نسبته '
                            . rtrim(rtrim(number_format((float) $tq_pool_default, 2), '0'), '.')
                            . '% افتراضا، وقد تخص الباقة بنسبتها. والباقي عمولة المنصة.'); ?>
                    </li>
                    <li>
                        يقسم الوعاء على معلمي الباقة <b>بعدد دروسهم</b> لا بعدد كورساتهم —
                        فمن له خمسون درسا من مئتين يأخذ ربع الوعاء.
                    </li>
                </ol>
                <p style="margin:0">
                    ومجموع حصص المعلمين يساوي الوعاء بالضبط، فدخول معلم جديد لا يزيد ما تدفعه
                    المنصة ولا ينقص وعاء الباقة — يقسمه على عدد أكبر. وكل سطر باقة في كشفك
                    أعلاه يحمل تحته سعرها ووعاءها وعدد دروسك فيها.
                </p>
            </div>
        </div>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro">كيف يتحرر المال</span>
            <ol class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0;padding-inline-start:var(--tq-space-l);list-style:decimal">
                <li>يشتري الطالب كورسك أو باقة فيها محتواك، فتقيد العملية في دفترك بحصتك وعمولة المنصة عليها.</li>
                <li><?php echo tq_iso('تظل الحصة معلقة ' . (int) $tq_w['refund_days'] . ' يوما — نافذة استرداد الطالب.'); ?></li>
                <li>بعدها تنتقل إلى «متاح للسحب» بقيد في دفترك، لا بإعادة حساب.</li>
                <li>لو استرد بيع بعد تحرره، يقيد عكسه ويظهر في كشفك — ولا يمحى سطره.</li>
            </ol>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
