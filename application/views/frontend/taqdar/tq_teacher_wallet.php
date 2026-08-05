<?php
/**
 * بوّابة المعلم — المحفظة والأرباح.
 *
 * القاعدة الحاكمة لبوّابة المعلم كلها:
 * المعلم مُسنَد إلى مادة وصفّ بعينهما، وما لم يُسنَد إليه لا يظهر في لوحته
 * أصلًا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يُفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زرّ في الواجهة ليس صلاحية. لذلك كشف الحساب أدناه
 * مقيَّد بمحفظة هذا المعلّم وحدها، وسجلّ السحب مقيَّد بمعرّفه هو.
 *
 * ما تغيّر هنا: **الشاشة لم تعد تحسب.**
 * كانت تفتح `payment` عند كل عرض وتجمع `instructor_revenue` وتقسمها بتاريخ
 * البيع — فكان الرقم صحيحًا ما دام لا شيء يحدث، وينهار عند أوّل استرداد أو
 * تسوية أو سحب جزئيّ، ولا يعطي كشف حساب بل قائمة مبيعات. الآن كل حركة
 * مقيَّدة في `wallet_entries`، والرصيد مجموع قيوده حرفيًّا، والشاشة تعرض
 * ما في الدفتر ولا تشتقّ منه شيئًا: لا جمع ولا طرح ولا قسمة في هذا الملفّ.
 * انظر `Taqdar_wallet_model` — هو صاحب الحساب كلّه.
 *
 * عمولة المنصة تظهر على كل عملية بيع منفردة لا كمبلغ إجمالي آخر الشهر:
 * المعلم يجب أن يستطيع تتبّع أي ريال إلى مصدره — أي عملية، أي كورس، أي يوم.
 * ولذلك عمود «عمولة المنصّة» في كشف الحساب صفّ بصفّ، لا سطر واحد في الأسفل.
 *
 * البيع يظلّ معلّقًا مدّة نافذة الاسترداد ثمّ يتحرّر، ويُعرض ذلك بوضوح
 * لا يُكتشف: لكل عملية معلّقة تاريخ تحرّرها وكم بقي عليه. والنافذة نفسها
 * إعدادٌ (`taqdar_refund_window_days`) لا رقم مكتوب في شيفرة الشاشة، وكذلك
 * الحدّ الأدنى للسحب (`taqdar_payout_min_sar`).
 *
 * والمال هللات صحيحة في الدفتر كلّه؛ القسمة على مئة هنا حدّ عرضٍ أخير
 * لا حساب — ولا يُخفى الكسر: مبلغٌ فيه هللات يُعرض بخانتيه.
 */

$tq_nav   = 'wallet';
$tq_role  = 'teacher';
$tq_title = 'المحفظة والأرباح';
$tq_sub   = 'المتاح والمعلّق منفصلان، وكل ريال يُتتبَّع إلى مصدره';
$tq_icon  = 'wallet';

$tq_uid = (int) $this->session->userdata('user_id');

/* الحمّال ينسخ خصائص المتحكّم إلى نفسه قبل تضمين العرض، فما يُحمَّل داخل
   العرض يُسنَد إلى المتحكّم لا إليه — ولذلك النسخة الحيّة صراحةً هنا. */
$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_wallet_model');
$tq_w  = $tq_ci->taqdar_wallet_model->screen($tq_uid);

/** هللات ⇐ نصّ ريالات. الخانتان تظهران حين يكون فيه هللات، فلا يُدوَّر مال. */
$tq_money = function ($halalas) {
    $h = (int) $halalas;
    return tq_sar($h / 100, ($h % 100 === 0) ? 0 : 2);
};

$tq_states = array(
    'pending'   => array('due',      'معلّق'),
    'available' => array('progress', 'متاح للسحب'),
    'paid'      => array('mastered', 'حُوِّل إليك'),
    'refunded'  => array('late',     'مُسترَدّ'),
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

        <!-- المتاح والمعلّق منفصلان بوضوح لا يُكتشف -->
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
                    <span class="tq-pastel__label tq-micro">معلّق مؤقّتًا</span>
                    <span class="tq-pastel__icon" style="color:var(--tq-peach-ink)" aria-hidden="true"><?php echo tq_icon('clock'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo $tq_money($tq_w['pending']); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0">
                    <?php echo tq_iso('يتحرّر بعد ' . (int) $tq_w['refund_days'] . ' يومًا من البيع'); ?>
                </p>
            </div>

            <div class="tq-pastel tq-pastel--sky">
                <div class="tq-row tq-row--between">
                    <span class="tq-pastel__label tq-micro">حُوِّل إليك</span>
                    <span class="tq-pastel__icon" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('check-badge'); ?></span>
                </div>
                <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)"><?php echo $tq_money($tq_w['transferred']); ?></p>
                <p class="tq-pastel__body tq-caption" style="margin:0">مجموع ما استلمته سابقًا</p>
            </div>
        </div>

        <!-- كشف الحساب: العمولة صفًّا بصفّ لا مبلغًا إجماليًّا آخر الشهر -->
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
                        كل سطر عملية بيع واحدة كما قُيِّدت في دفترك: مبلغها، وعمولة المنصّة عليها،
                        وحصّتك منها، ومتى تتحرّر. والأرصدة أعلاه حاصل جمع هذه القيود لا حسابًا مستقلًّا عنها.
                    </p>
                    <table class="tq-table">
                        <caption class="tq-sr">مبيعات كورساتك: المبلغ وعمولة المنصّة وحصّتك وحالة كل عملية</caption>
                        <thead>
                            <tr>
                                <th scope="col">التاريخ</th>
                                <th scope="col">الكورس</th>
                                <th scope="col">مبلغ البيع</th>
                                <th scope="col">عمولة المنصّة</th>
                                <?php if ($tq_has_retained): ?>
                                    <th scope="col">ضريبة ومحتجَز</th>
                                <?php endif; ?>
                                <th scope="col">حصّتك</th>
                                <th scope="col">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_w['statement'] as $tq_s): ?>
                                <?php
                                $tq_kind  = isset($tq_states[$tq_s['state']]) ? $tq_states[$tq_s['state']] : $tq_states['pending'];
                                $tq_label = $tq_kind[1];
                                if ($tq_s['state'] === 'pending') {
                                    $tq_label .= ' · يتحرّر بعد ' . TQ_LRI . (int) $tq_s['days_left'] . TQ_PDI . ' يومًا';
                                }
                                ?>
                                <tr>
                                    <td data-label="التاريخ"><?php echo tq_num($tq_s['date'], 'tq-num--sm'); ?></td>
                                    <td data-label="الكورس"><?php echo html_escape($tq_s['subject']); ?></td>
                                    <td data-label="مبلغ البيع"><?php echo $tq_money($tq_s['gross']); ?></td>
                                    <td data-label="عمولة المنصّة"><?php echo $tq_money($tq_s['commission']); ?></td>
                                    <?php if ($tq_has_retained): ?>
                                        <td data-label="ضريبة ومحتجَز"><?php echo $tq_money($tq_s['retained']); ?></td>
                                    <?php endif; ?>
                                    <td data-label="حصّتك"><span class="tq-strong"><?php echo $tq_money($tq_s['share']); ?></span></td>
                                    <td data-label="الحالة"><?php echo tq_badge($tq_kind[0], $tq_label); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
                        مجموع عمولة المنصّة في هذا الكشف: <?php echo $tq_money($tq_commission_total); ?> —
                        وهو حاصل جمع العمولات أعلاه لا رقم مستقلّ عنها.
                    </p>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('file', 24); ?></span>
                    <h3 class="tq-empty__title">لا مبيعات بعد</h3>
                    <p class="tq-empty__text">
                        أول عملية بيع في كورساتك ستظهر هنا بمبلغها وعمولة المنصّة عليها وحصّتك منها،
                        وبتاريخ تحرّرها من نافذة الاسترداد.
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/courses'); ?>">كورساتي</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- سجلّ السحب -->
        <section aria-labelledby="tq-payouts-h">
            <div class="tq-sectionhead"><h2 id="tq-payouts-h">طلبات السحب السابقة</h2></div>

            <?php if ($tq_w['payouts']): ?>
                <div class="tq-card">
                    <table class="tq-table">
                        <caption class="tq-sr">طلبات السحب التي قدّمتها وقنواتها وحالتها</caption>
                        <thead>
                            <tr>
                                <th scope="col">التاريخ</th>
                                <th scope="col">المبلغ</th>
                                <th scope="col">القناة</th>
                                <th scope="col">الوجهة</th>
                                <th scope="col">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_w['payouts'] as $tq_p): ?>
                                <?php
                                $tq_ch = isset($tq_w['channels'][$tq_p['channel']])
                                       ? $tq_w['channels'][$tq_p['channel']]['label']
                                       : 'تُحدَّد مع الإدارة';
                                if ((int) $tq_p['status'] === 1) {
                                    $tq_pstate = tq_badge('mastered', 'حُوِّل');
                                } elseif ((int) $tq_p['status'] === 2) {
                                    $tq_pstate = tq_badge('idle', 'أُلغي — عاد إلى رصيدك');
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('download', 24); ?></span>
                    <h3 class="tq-empty__title">لم تطلب سحبًا بعد</h3>
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
                    خرج من المتاح مقابل طلبات سحب قائمة، ولم يُحوَّل بعد. إلغاء الطلب يعيده إلى المتاح.
                </p>
            </div>
        <?php endif; ?>

        <!--
            طلب سحب. الحقول بأسمائها القديمة (`withdrawal_amount` بالريالات،
            `payment_type` للقناة، `destination` للوجهة) فلا يكسر النموذجُ
            ما يقرؤه المتحكّم. والقيد الحقيقي — الحدّ الأدنى والرصيد الكافي
            وصحّة القناة — في `Taqdar_wallet_model::request_payout` في الخادم؛
            وما هنا (min/max) تيسيرٌ للمستخدم لا حراسة، فالحارس لا يكون في المتصفّح.
        -->
        <form class="tq-card" id="tq-withdraw" method="post"
              action="<?php echo base_url('teacher/wallet/withdraw'); ?>">
            <div class="tq-card__head"><h2 class="tq-card__title">طلب سحب</h2></div>

            <div class="tq-field">
                <label class="tq-field__label" for="tq-amount">المبلغ بالريال</label>
                <input class="tq-input" id="tq-amount" type="number" name="withdrawal_amount"
                       min="<?php echo (int) $tq_w['min_payout'] / 100; ?>" step="0.01" inputmode="decimal"
                       max="<?php echo (int) $tq_w['available'] / 100; ?>" required>
                <span class="tq-field__msg tq-field__hint">
                    المتاح الآن <?php echo $tq_money($tq_w['available']); ?> —
                    <?php echo tq_iso('والحدّ الأدنى للسحب ' . number_format($tq_w['min_payout'] / 100, 2) . ' ريال.'); ?>
                    والمعلّق لا يُسحب قبل أن يتحرّر.
                </span>
            </div>

            <fieldset style="border:0;padding:0;margin:0 0 var(--tq-space-l)">
                <legend class="tq-field__label" style="padding:0">قناة التحويل</legend>
                <?php $tq_first = true; foreach ($tq_w['channels'] as $tq_key => $tq_c): ?>
                    <span class="tq-row" style="gap:var(--tq-space-s);margin-block-end:var(--tq-space-s)">
                        <input type="radio" id="tq-ch-<?php echo $tq_key; ?>" name="payment_type"
                               value="<?php echo $tq_key; ?>" <?php echo $tq_first ? 'checked' : ''; ?> required>
                        <label for="tq-ch-<?php echo $tq_key; ?>">
                            <?php echo html_escape($tq_c['label']); ?>
                            <span class="tq-caption"> — <?php echo html_escape($tq_c['hint']); ?></span>
                        </label>
                    </span>
                <?php $tq_first = false; endforeach; ?>
            </fieldset>

            <div class="tq-field">
                <label class="tq-field__label" for="tq-dest">بيانات التحويل</label>
                <input class="tq-input" id="tq-dest" type="text" name="destination" required
                       placeholder="رقم الآيبان أو رقم الجوّال المرتبط بالمحفظة">
                <span class="tq-field__msg tq-field__hint">
                    <?php echo tq_iso('الآيبان السعودي يبدأ بـ SA ويتكوّن من 24 خانة.'); ?>
                    وتُحفَظ الوجهة مع الطلب، ولا تظهر بعدها إلّا بأربع خاناتها الأخيرة.
                </span>
            </div>

            <button class="tq-btn tq-btn--primary tq-btn--block" type="submit"
                    aria-describedby="tq-withdraw-note"
                    <?php echo ((int) $tq_w['available'] < (int) $tq_w['min_payout']) ? 'disabled' : ''; ?>>
                إرسال الطلب
            </button>
            <p class="tq-field__msg tq-field__hint" id="tq-withdraw-note" style="margin-block-start:var(--tq-space-m)">
                <?php if ((int) $tq_w['available'] < (int) $tq_w['min_payout']): ?>
                    رصيدك المتاح لم يبلغ الحدّ الأدنى للسحب بعد.
                <?php else: ?>
                    عند إرسال الطلب يُحجَز المبلغ من رصيدك المتاح فورًا ويُقيَّد في دفترك،
                    فلا يمكن طلبه مرّتين.
                <?php endif; ?>
            </p>
        </form>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro">كيف يتحرّر المال</span>
            <ol class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0;padding-inline-start:var(--tq-space-l);list-style:decimal">
                <li>يشتري الطالب الكورس، فتُقيَّد العملية في دفترك بحصّتك وعمولة المنصّة عليها.</li>
                <li><?php echo tq_iso('تظلّ الحصّة معلّقة ' . (int) $tq_w['refund_days'] . ' يومًا — نافذة استرداد الطالب.'); ?></li>
                <li>بعدها تنتقل إلى «متاح للسحب» بقيدٍ في دفترك، لا بإعادة حساب.</li>
                <li>لو استُرِدّ بيعٌ بعد تحرّره، يُقيَّد عكسه ويظهر في كشفك — ولا يُمحى سطره.</li>
            </ol>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
