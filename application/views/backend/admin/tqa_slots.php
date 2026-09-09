<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أوقات المعلمين.
 *
 * الشاشة تجيب سؤالا واحدا: **لماذا يرى الطالب «لا معلم متاح الآن»؟**
 * وجوابه غالبا أن أحدا لم يفتح وقتا — لا أن النظام معطل. فالجدول
 * الأول يعد المعلمين ومن لم يفتح منهم شيئا، والثاني يعرض الفسحات
 * القادمة نفسها.
 *
 * وصار الجدول الأول يجيب سؤالا ثانيا: **بكم يبيع كل معلم؟** والتسعيرة
 * تحرر فيه صفا بصف — الحقل الفارغ يعني التسعيرة العامة، والمكتوب استثناء.
 *
 * ── TQ-SLOTS-CRAM — ولماذا أعيد بناء الشاشة ───────────────────────
 *
 * السؤالان كانا في **عمودين متساويين**: نصف الشاشة لجدول من أربعة
 * أعمدة آخرها نموذج فيه حقلان وزر وسطر حساب. فوقع ما يقع دائما حين
 * يوضع نموذج في خلية جدول ضيقة:
 *
 *   ١ — النموذج ينكسر أربعة أسطر (حقل · حقل · زر · سطر الحساب)، فيرتفع
 *       الصف إلى مئتين وثلاثين بكسلا ويقرأ الجدول عمودا من قصاصات.
 *   ٢ — الحقلان بلا عنوان: نائباهما «عام» و«٪» — ومن فتح الشاشة أول
 *       مرة لا يعرف أن الأول ريال والثاني نسبة إلا بالتجربة.
 *   ٣ — الجدول يمرر أفقيا داخل نصف شاشة، فيختفي عمود «محجوزة» كله.
 *
 * فالعلاج ليس تضييق الحقول: هو أن تأخذ التسعيرة عرضها. فالعمودان صارا
 * غير متساويين (`.tqa-slots` في `admin.css`)، وعمودا «مفتوحة»
 * و«محجوزة» صارا عمودا واحدا بشارتين — سؤالهما واحد: كم وقتا عنده؟ —
 * وللحقلين عنوانان يقرآن، وسطر الحساب ثلاث شارات لا جملة متصلة.
 * ── TQ-SESSION-GRID — والصف عمود ثالث في السؤال ────────────────────
 *
 * صار المعلم يفتح ساعاته بنفسه ولكل ساعة صفها، فسؤال الشاشة صار سؤالين:
 * «من فتح وقتا؟» و**«لأي صف؟»**. والثاني هو الذي يفسر أكثر ما يشكى منه
 * بعد اليوم: طالب في صف لم يفتح له أحد وقتا يقرأ «لا معلم متاح» وفي
 * المنصة أحد عشر معلما فتحوا أوقاتهم كلهم — لصفوف أخرى.
 *
 * فصف المعلم في الجدول الأول يقول قواعده الأسبوعية كما كتبها، وعمود
 * «الصف» في «الفسحات القادمة» يقول لمن فتحت كل فسحة.
 */
$tq_cfg = isset($cfg) ? $cfg : array('price' => 0, 'percent' => 0);
$tq_wins = isset($windows) ? $windows : array();
$tq_ses  = isset($ses) ? $ses : null;

$no_slots = array_values(array_filter($teachers, function ($t) {
    return (int) $t['open_slots'] === 0 && (int) $t['booked_slots'] === 0;
}));
$open_total = 0;
$booked_total = 0;
foreach ($teachers as $t) {
    $open_total   += (int) $t['open_slots'];
    $booked_total += (int) $t['booked_slots'];
}
?>

<?php tqa_head(t('أوقات المعلمين'), t('من فتح وقتا لحصص الطلب، ومن لم يفتح.'), 'clock'); ?>

<div class="tqa-grid tqa-grid--3" style="margin-block-end:var(--tq-space-xl)">
    <?php echo tqa_stat(t('فسحات مفتوحة الآن'), (int) $open_total, array(
        'icon' => 'clock', 'tone' => $open_total > 0 ? 'ok' : 'warn',
        'hint' => t('قابلة للحجز في الأيام القادمة'),
    )); ?>

    <?php echo tqa_stat(t('معلمون بلا أي وقت'), count($no_slots), array(
        'icon' => $no_slots ? 'alert' : 'check',
        'tone' => $no_slots ? 'danger' : 'ok',
        'hint' => t('من ____ معلما معتمدا', array(count($teachers))),
    )); ?>

    <?php echo tqa_stat(t('فسحات محجوزة'), (int) $booked_total, array(
        'icon' => 'video', 'tone' => 'info',
        'hint' => t('حجزها طلاب فعلا'),
    )); ?>
</div>

<?php if ($no_slots): ?>
    <div class="tqa-note tqa-note--warn" style="margin-block-end:var(--tq-space-xl)">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><span class="tqa-num"><?php echo count($no_slots); ?></span> <?php echo t('معلما لم يفتح وقتا واحدا.'); ?></strong>
            <?php echo t('وما دام كذلك فبطاقة «حصص بالطلب» في بوابة الطالب تقول «لا معلم متاح الآن» — وهي رسالة صحيحة تقرأ كعطل. الأوقات تفتح من شاشة «الحصص» في بوابة المعلم.'); ?>
            <?php /* والصف نصف الجواب: معلم فتح وقته لصف واحد غائب عن كل
                     طالب في الصفوف الأخرى، ولا يعده هذا العداد. */ ?>
            <?php echo t('ولكل وقت صف يفتح له، فالطالب لا يرى إلا مواعيد صفه — راجع أوقات كل معلم أدناه.'); ?>
        </span>
    </div>
<?php endif; ?>

<div class="tqa-slots">

    <section class="tqa-card tqa-card--flush">
        <div class="tqa-card__head">
            <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('money', 20); ?></span>
            <div style="min-inline-size:0">
                <h2><?php echo t('المعلمون وتسعيرتهم'); ?></h2>
                <span class="tqa-media__sub"><?php echo t('من يبيع وقتا، وبكم.'); ?></span>
            </div>
            <span class="tqa-badge tqa-badge--muted">
                <span class="tqa-num"><?php echo count($teachers); ?></span>&nbsp;<?php echo t('معلما'); ?>
            </span>
        </div>

        <?php /* التسعيرة تحرر هنا لا في شاشة الحسابات: هذه هي الشاشة التي
                 يقرأ فيها المسؤول من **يبيع** حصصا فعلا، والسعر سؤال عن
                 معلم يعرض وقتا لا عن كل من في جدول المستخدمين. */ ?>
        <div class="tqa-note" style="margin:var(--tq-space-l) var(--tq-space-xl) 0">
            <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
            <span>
                <?php /* جملة واحدة بمفتاح واحد لا خمس قصاصات: الجملة المفككة
                         تترجم قطعا لا تركب في الإنجليزية، والفواصل بينها
                         تبقى عربية في لوحة إنجليزية — وهي ما أمسكه فاحص
                         `i18n_lint` على هذا السطر بعينه. */ ?>
                <?php echo t(
                    'الحقلان الفارغان يعنيان «التسعيرة العامة»: ____ ر.س للحصة، ونصيب المعلم ____٪.',
                    array(
                        '<strong class="tqa-num">' . number_format($tq_cfg['price'] / 100, 2) . '</strong>',
                        '<strong class="tqa-num">' . rtrim(rtrim(number_format($tq_cfg['percent'], 2), '0'), '.') . '</strong>',
                    )
                ); ?>
                <?php echo t(
                    'واكتب فيهما لتستثني معلما بعينه — ____: من كتب له صفر يدرس مجانا بقرار، ومن ترك فارغا يبيع بسعر المنصة.',
                    array('<strong>' . te('وصفر ليس فراغا') . '</strong>')
                ); ?>
                <a href="<?php echo site_url('taqdar_admin/sessions#tqa-pricing'); ?>"><?php echo t('عدل التسعيرة العامة'); ?></a>
            </span>
        </div>

        <?php if (!$teachers): ?>
            <?php tqa_empty(t('لا معلم معتمد بعد'), t('الحصص تحتاج معلما معتمدا أولا.'),
                            t('طلبات المعلمين'), site_url('taqdar_admin/teachers'), 'users'); ?>
        <?php else: ?>
            <div class="tqa-table__wrap">
            <table class="tqa-table tqa-table--zebra">
                <thead>
                    <tr>
                        <th><?php echo t('المعلم'); ?></th>
                        <?php /* عمود واحد لا عمودان: «كم وقتا عنده؟» سؤال واحد،
                                 وعمودان برقمين عاريين يقرآن رقمين لا جوابا. */ ?>
                        <th class="tqa-col--tight"><?php echo t('فسحاته'); ?></th>
                        <th><?php echo t('التسعيرة ونصيبه'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($teachers as $t):
                    $own_price = ($t['tq_session_price']   ?? null);
                    $own_pct   = ($t['tq_session_percent'] ?? null);
                    $has_own   = !($own_price === null || $own_price === '')
                              || !($own_pct   === null || $own_pct   === '');
                    $eff_price = ($own_price === null || $own_price === '')
                               ? $tq_cfg['price'] : (int) $own_price;
                    $eff_pct   = ($own_pct === null || $own_pct === '')
                               ? $tq_cfg['percent'] : (float) $own_pct;
                    $eff_share = (int) round($eff_price * $eff_pct / 100);
                    $tq_tid    = (int) $t['id'];
                ?>
                    <tr>
                        <td data-label="<?php echo te('المعلم'); ?>">
                            <div class="tqa-media">
                                <?php echo tqa_avatar('', $t['name'] ?: $t['email']); ?>
                                <div class="tqa-cell">
                                    <span class="tqa-cell__main"><?php echo html_escape($t['name'] ?: $t['email']); ?></span>
                                    <span class="tqa-cell__sub tqa-mono tqa-mono--dim"><?php echo html_escape($t['email']); ?></span>
                                </div>
                            </div>
                        </td>

                        <td class="tqa-col--tight" data-label="<?php echo te('فسحاته'); ?>">
                            <span class="tqa-cell__row">
                                <?php if ((int) $t['open_slots'] > 0): ?>
                                    <span class="tqa-badge tqa-badge--dot tqa-badge--ok">
                                        <span class="tqa-num"><?php echo (int) $t['open_slots']; ?></span>&nbsp;<?php echo t('مفتوحة'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="tqa-badge tqa-badge--dot tqa-badge--danger"><?php echo t('لا وقت مفتوح'); ?></span>
                                <?php endif; ?>
                                <?php if ((int) $t['booked_slots'] > 0): ?>
                                    <span class="tqa-badge tqa-badge--info">
                                        <span class="tqa-num"><?php echo (int) $t['booked_slots']; ?></span>&nbsp;<?php echo t('محجوزة'); ?>
                                    </span>
                                <?php endif; ?>
                            </span>

                            <?php /* قواعده الأسبوعية كما كتبها — لا عدد فسحات
                                     يتجدد وحده كل ساعة: «الأحد ١٠:٠٠–١٤:٠٠
                                     للثالث الابتدائي» هو ما يفسر من يرى ماذا،
                                     و«٣٦ فسحة» رقم لا يفسر شيئا. */ ?>
                            <?php $tq_my = isset($tq_wins[$tq_tid]) ? $tq_wins[$tq_tid] : array(); ?>
                            <?php if ($tq_my): ?>
                                <ul class="tqa-winlist">
                                    <?php foreach ($tq_my as $tq_w):
                                        $tq_dn = $tq_ses ? ($tq_ses->days()[(int) $tq_w['dow']] ?? '') : '';
                                        $tq_sp = $tq_ses ? $tq_ses->span_text($tq_w['start_min'], $tq_w['end_min'])
                                                         : ((int) $tq_w['start_min'] . '–' . (int) $tq_w['end_min']);
                                    ?>
                                        <li>
                                            <span class="tqa-cell__main"><?php echo html_escape($tq_dn); ?></span>
                                            <span class="tqa-num tqa-mono tqa-mono--dim"><?php echo html_escape($tq_sp); ?></span>
                                            <?php /* TQ-FOUNDATION — ووقت التأسيس بمساره:
                                                     «كل الصفوف» على وقت تأسيس تقرأ
                                                     خطأ في الإسناد، وهي حاله الصحيح. */ ?>
                                            <?php if ((string) ($tq_w['kind'] ?? '') === 'foundation'): ?>
                                                <span class="tqa-badge tqa-badge--ok"><?php echo t('تأسيس'); ?></span>
                                                <span class="tqa-badge tqa-badge--info">
                                                    <?php echo html_escape($tq_w['track_name'] !== null && $tq_w['track_name'] !== ''
                                                        ? $tq_w['track_name']
                                                        : t('مسار محذوف') . ' #' . (int) ($tq_w['track_id'] ?? 0)); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="tqa-badge tqa-badge--muted">
                                                    <?php echo html_escape($tq_w['grade_name'] ?: t('كل الصفوف')); ?>
                                                </span>
                                                <?php if (!empty($tq_w['subject_name'])): ?>
                                                    <span class="tqa-badge tqa-badge--info">
                                                        <?php echo html_escape($tq_w['subject_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="tqa-cell__sub"><?php echo t('لم يكتب أي وقت أسبوعي.'); ?></span>
                            <?php endif; ?>
                        </td>

                        <td data-label="<?php echo te('التسعيرة ونصيبه'); ?>">
                            <?php /* العنوانان فوق الحقلين لا نائبان داخلهما: النائب
                                     يختفي عند أول حرف يكتب، فيبقى الرقم بلا ما
                                     يقول أهو ريال أم نسبة. */ ?>
                            <form class="tqa-price<?php echo $has_own ? ' is-own' : ''; ?>"
                                  method="post" action="<?php echo site_url('taqdar_admin/slot_pricing_save'); ?>">
                                <?php echo tq_csrf(); ?>
                                <input type="hidden" name="teacher_id" value="<?php echo $tq_tid; ?>">

                                <div class="tqa-price__row">
                                    <label class="tqa-price__f" for="pr-<?php echo $tq_tid; ?>">
                                        <span><?php echo t('سعر الحصة (ر.س)'); ?></span>
                                        <input class="tqa-input tqa-input--ltr" id="pr-<?php echo $tq_tid; ?>"
                                               type="number" name="price_sar" min="0" step="0.01" dir="ltr"
                                               placeholder="<?php echo te('عام'); ?>"
                                               value="<?php echo ($own_price === null || $own_price === '')
                                                           ? '' : html_escape(number_format(((int) $own_price) / 100, 2, '.', '')); ?>">
                                    </label>

                                    <label class="tqa-price__f tqa-price__f--pct" for="pc-<?php echo $tq_tid; ?>">
                                        <span><?php echo t('نصيبه ٪'); ?></span>
                                        <input class="tqa-input tqa-input--ltr" id="pc-<?php echo $tq_tid; ?>"
                                               type="number" name="percent" min="0" max="100" step="0.01" dir="ltr"
                                               placeholder="<?php echo te('عام'); ?>"
                                               value="<?php echo ($own_pct === null || $own_pct === '')
                                                           ? '' : html_escape(rtrim(rtrim(number_format((float) $own_pct, 2, '.', ''), '0'), '.')); ?>">
                                    </label>

                                    <button class="tqa-btn tqa-btn--mastery tqa-btn--sm" type="submit">
                                        <?php echo tq_icon('check', 14); ?> <?php echo t('حفظ'); ?>
                                    </button>
                                </div>

                                <?php /* الساري ثلاثة أرقام تقرأ معا أو لا تقرأ:
                                         بكم يباع، وكم يأخذ، وكم يبقى. وكانت جملة
                                         متصلة بفواصل وسطى تنكسر سطرين. */ ?>
                                <div class="tqa-price__now">
                                    <span class="tqa-badge tqa-badge--muted">
                                        <?php echo t('الساري'); ?>&nbsp;<span class="tqa-num"><?php echo number_format($eff_price / 100, 2); ?></span>&nbsp;<?php echo t('ر.س'); ?>
                                    </span>
                                    <span class="tqa-badge tqa-badge--ok">
                                        <?php echo t('له'); ?>&nbsp;<span class="tqa-num"><?php echo number_format($eff_share / 100, 2); ?></span>
                                    </span>
                                    <span class="tqa-badge tqa-badge--info">
                                        <?php echo t('للمنصة'); ?>&nbsp;<span class="tqa-num"><?php echo number_format(($eff_price - $eff_share) / 100, 2); ?></span>
                                    </span>
                                    <?php /* الصف المستثنى يعلن نفسه: بلا ذلك يقرأ
                                             جدول من عشرين معلما متطابقا، ولا يعرف
                                             المسؤول من استثنى قبل أن يقرأ كل حقل. */ ?>
                                    <span class="tqa-badge tqa-badge--warn tqa-price__own"><?php echo t('تسعيرة خاصة'); ?></span>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="tqa-card tqa-card--flush tqa-slots__side">
        <div class="tqa-card__head">
            <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('clock', 20); ?></span>
            <div style="min-inline-size:0">
                <h2><?php echo t('الفسحات القادمة'); ?></h2>
                <span class="tqa-media__sub"><?php echo t('ما فتح فعلا، وحال كل فسحة.'); ?></span>
            </div>
        </div>
        <?php if (!$rows): ?>
            <?php tqa_empty(t('لا فسحة قادمة'), t('لا وقت مفتوحا في الأيام القادمة ولا في الأسبوع الماضي.'), '', '', 'clock'); ?>
        <?php else: ?>
            <div class="tqa-table__wrap">
            <table class="tqa-table">
                <thead>
                    <tr>
                        <th><?php echo t('الموعد'); ?></th>
                        <th><?php echo t('المعلم'); ?></th>
                        <th><?php echo t('الصف والمادة'); ?></th>
                        <th class="tqa-col--tight"><?php echo t('الحالة'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $s):
                    $when = strtotime($s['starts_at']);
                    $st   = (string) $s['status'];
                    $map  = array('open' => array('ok', t('مفتوحة')), 'held' => array('warn', t('محجوزة مؤقتا')),
                                  'booked' => array('info', t('محجوزة')));
                    [$tone, $label] = $map[$st] ?? array('muted', $st);
                ?>
                    <tr>
                        <?php /* المدة تحت الموعد لا في عمود ثالث: «٦٠ د» وحدها
                                 لا تعني شيئا بلا موعدها، وعمود لها يأكل عرضا
                                 في بطاقة جانبية ضيقة. */ ?>
                        <td data-label="<?php echo te('الموعد'); ?>">
                            <div class="tqa-cell">
                                <span class="tqa-cell__main tqa-num"><?php echo date('Y-m-d H:i', $when); ?></span>
                                <span class="tqa-cell__sub">
                                    <span class="tqa-num"><?php echo (int) $s['duration_min']; ?></span> <?php echo t('دقيقة'); ?>
                                    <?php if ($when < time()): ?> · <?php echo t('مضت'); ?><?php endif; ?>
                                </span>
                            </div>
                        </td>
                        <td data-label="<?php echo te('المعلم'); ?>"><?php echo html_escape($s['teacher_name'] ?: '—'); ?></td>
                        <?php /* «كل الصفوف» ليس فراغا: هي فسحة يقبل فيها
                                 معلمها أي طالب، وشرطة مكانها تقرأ «لم يحدد». */ ?>
                        <td data-label="<?php echo te('الصف والمادة'); ?>">
                            <?php /* TQ-FOUNDATION — وفسحة التأسيس تقول مسارها.
                                     وهي بصفر صف وصفر مادة بحكم الحفظ، فقراءتها
                                     بقاعدة المنهج تطبع «كل الصفوف» — وهي تقرأ
                                     «فسحة يقبل فيها معلمها أي طالب في أي مادة»
                                     وليست كذلك: هي وقت مسار بعينه. */ ?>
                            <?php if ((string) ($s['kind'] ?? '') === 'foundation'): ?>
                                <span class="tqa-badge tqa-badge--ok"><?php echo t('تأسيس'); ?></span>
                                <span class="tqa-badge tqa-badge--info"><?php
                                    echo html_escape($s['track_name'] !== null && $s['track_name'] !== ''
                                        ? $s['track_name']
                                        : t('مسار محذوف') . ' #' . (int) ($s['track_id'] ?? 0)); ?></span>
                            <?php else: ?>
                                <?php if ((int) ($s['grade_id'] ?? 0) > 0): ?>
                                    <span class="tqa-badge tqa-badge--muted"><?php echo html_escape($s['grade_name'] ?: t('صف') . ' #' . (int) $s['grade_id']); ?></span>
                                <?php else: ?>
                                    <span class="tqa-cell__sub"><?php echo t('كل الصفوف'); ?></span>
                                <?php endif; ?>
                                <?php /* المادة تحت الصف: هما جواب سؤال واحد —
                                         «لمن فتحت هذه الفسحة، وفي أي مادة؟» */ ?>
                                <?php if (!empty($s['subject_name'])): ?>
                                    <span class="tqa-badge tqa-badge--info"><?php echo html_escape($s['subject_name']); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="tqa-col--tight" data-label="<?php echo te('الحالة'); ?>">
                            <span class="tqa-badge tqa-badge--dot tqa-badge--<?php echo $tone; ?>"><?php echo html_escape($label); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>
</div>
