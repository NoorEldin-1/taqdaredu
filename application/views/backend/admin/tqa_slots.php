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
 */
$tq_cfg = isset($cfg) ? $cfg : array('price' => 0, 'percent' => 0);

$no_slots = array_values(array_filter($teachers, function ($t) {
    return (int) $t['open_slots'] === 0 && (int) $t['booked_slots'] === 0;
}));
$open_total = 0;
foreach ($teachers as $t) $open_total += (int) $t['open_slots'];
?>

<?php tqa_head('أوقات المعلمين', 'من فتح وقتا لحصص الطلب، ومن لم يفتح.', 'clock'); ?>

<div class="tqa-grid tqa-grid--3" style="margin-block-end:var(--tq-space-xl)">
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label">فسحات مفتوحة الآن</span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('clock', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $open_total; ?></span>
        <span class="tqa-stat__hint">قابلة للحجز في الأيام القادمة</span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label">معلمون بلا أي وقت</span>
            <span class="tqa-stat__icon <?php echo $no_slots ? 'tqa-rose' : 'tqa-mint'; ?>" aria-hidden="true">
                <?php echo tq_icon($no_slots ? 'alert' : 'check', 18); ?>
            </span>
        </div>
        <span class="tqa-stat__value"><?php echo count($no_slots); ?></span>
        <span class="tqa-stat__hint">من <span class="tqa-num"><?php echo count($teachers); ?></span> معلما معتمدا</span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label">فسحات محجوزة</span>
            <span class="tqa-stat__icon tqa-sky" aria-hidden="true"><?php echo tq_icon('video', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php
            $b = 0; foreach ($teachers as $t) $b += (int) $t['booked_slots']; echo $b;
        ?></span>
        <span class="tqa-stat__hint">حجزها طلاب فعلا</span>
    </div>
</div>

<?php if ($no_slots): ?>
    <div class="tqa-note tqa-note--warn" style="margin-block-end:var(--tq-space-xl)">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><span class="tqa-num"><?php echo count($no_slots); ?></span> معلما لم يفتح وقتا واحدا.</strong>
            وما دام كذلك فبطاقة «حصص بالطلب» في بوابة الطالب تقول «لا معلم متاح الآن»
            — وهي رسالة صحيحة تقرأ كعطل. الأوقات تفتح من شاشة «الحصص» في بوابة المعلم.
        </span>
    </div>
<?php endif; ?>

<div class="tqa-grid tqa-grid--2">

    <section class="tqa-card tqa-card--flush">
        <div class="tqa-card__head"><h2>المعلمون وتسعيرتهم</h2></div>

        <?php /* التسعيرة تحرر هنا لا في شاشة الحسابات: هذه هي الشاشة التي
                 يقرأ فيها المسؤول من **يبيع** حصصا فعلا، والسعر سؤال عن
                 معلم يعرض وقتا لا عن كل من في جدول المستخدمين. */ ?>
        <p class="tqa-card__lead" style="padding-inline:var(--tq-space-l)">
            الحقلان فارغان يعنيان «التسعيرة العامة»
            (<strong><?php echo number_format($tq_cfg['price'] / 100, 2); ?></strong> ر.س للحصة،
            نصيب المعلم <strong><?php echo rtrim(rtrim(number_format($tq_cfg['percent'], 2), '0'), '.'); ?>٪</strong>).
            واكتب فيهما لتستثني معلما بعينه — <strong>وصفر ليس فراغا</strong>: من كتب له صفر يدرس مجانا بقرار،
            ومن ترك فارغا يبيع بسعر المنصة.
            <a href="<?php echo site_url('taqdar_admin/sessions#tqa-pricing'); ?>">عدل التسعيرة العامة</a>.
        </p>

        <?php if (!$teachers): ?>
            <?php tqa_empty('لا معلم معتمد بعد', 'الحصص تحتاج معلما معتمدا أولا.',
                            'طلبات المعلمين', site_url('taqdar_admin/teachers'), 'users'); ?>
        <?php else: ?>
            <div class="tqa-table__wrap">
            <table class="tqa-table">
                <thead>
                    <tr><th>المعلم</th><th>مفتوحة</th><th>محجوزة</th><th>السعر ونصيبه</th></tr>
                </thead>
                <tbody>
                <?php foreach ($teachers as $t):
                    $own_price = ($t['tq_session_price']   ?? null);
                    $own_pct   = ($t['tq_session_percent'] ?? null);
                    $eff_price = ($own_price === null || $own_price === '')
                               ? $tq_cfg['price'] : (int) $own_price;
                    $eff_pct   = ($own_pct === null || $own_pct === '')
                               ? $tq_cfg['percent'] : (float) $own_pct;
                    $eff_share = (int) round($eff_price * $eff_pct / 100);
                ?>
                    <tr>
                        <td data-label="المعلم">
                            <?php echo html_escape($t['name'] ?: $t['email']); ?><br>
                            <span class="tqa-num" style="color:var(--tq-text2);font-size:12px">
                                <?php echo html_escape($t['email']); ?></span>
                        </td>
                        <td data-label="مفتوحة">
                            <?php if ((int) $t['open_slots'] > 0): ?>
                                <span class="tqa-badge tqa-badge--ok"><span class="tqa-num"><?php echo (int) $t['open_slots']; ?></span></span>
                            <?php else: ?>
                                <span class="tqa-badge tqa-badge--danger">لا شيء</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="محجوزة"><span class="tqa-num"><?php echo (int) $t['booked_slots']; ?></span></td>
                        <td data-label="السعر ونصيبه">
                            <form method="post" action="<?php echo site_url('taqdar_admin/slot_pricing_save'); ?>"
                                  style="margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                <?php echo tq_csrf(); ?>
                                <input type="hidden" name="teacher_id" value="<?php echo (int) $t['id']; ?>">
                                <label class="tqa-sr" for="pr-<?php echo (int) $t['id']; ?>">سعر حصة هذا المعلم بالريال</label>
                                <input class="tqa-input tqa-input--ltr" id="pr-<?php echo (int) $t['id']; ?>"
                                       type="number" name="price_sar" min="0" step="0.01" dir="ltr"
                                       placeholder="عام" title="السعر بالريال — فارغ يعني التسعيرة العامة"
                                       style="min-block-size:34px;inline-size:88px"
                                       value="<?php echo ($own_price === null || $own_price === '')
                                                   ? '' : html_escape(number_format(((int) $own_price) / 100, 2, '.', '')); ?>">
                                <label class="tqa-sr" for="pc-<?php echo (int) $t['id']; ?>">نصيب هذا المعلم بالمئة</label>
                                <input class="tqa-input tqa-input--ltr" id="pc-<?php echo (int) $t['id']; ?>"
                                       type="number" name="percent" min="0" max="100" step="0.01" dir="ltr"
                                       placeholder="٪" title="نصيبه بالمئة — فارغ يعني النسبة العامة"
                                       style="min-block-size:34px;inline-size:70px"
                                       value="<?php echo ($own_pct === null || $own_pct === '')
                                                   ? '' : html_escape(rtrim(rtrim(number_format((float) $own_pct, 2, '.', ''), '0'), '.')); ?>">
                                <button class="tqa-btn tqa-btn--sm" type="submit">حفظ</button>
                                <span style="flex-basis:100%;color:var(--tq-text2);font-size:12px">
                                    الساري: <span class="tqa-num"><?php echo number_format($eff_price / 100, 2); ?></span> ر.س
                                    · له <span class="tqa-num"><?php echo number_format($eff_share / 100, 2); ?></span> ر.س
                                    · للمنصة <span class="tqa-num"><?php echo number_format(($eff_price - $eff_share) / 100, 2); ?></span> ر.س
                                </span>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="tqa-card tqa-card--flush">
        <div class="tqa-card__head"><h2>الفسحات القادمة</h2></div>
        <?php if (!$rows): ?>
            <?php tqa_empty('لا فسحة قادمة', 'لا وقت مفتوحا في الأيام القادمة ولا في الأسبوع الماضي.', '', '', 'clock'); ?>
        <?php else: ?>
            <div class="tqa-table__wrap">
            <table class="tqa-table">
                <thead>
                    <tr><th>الموعد</th><th>المعلم</th><th>المدة</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $s):
                    $when = strtotime($s['starts_at']);
                    $st   = (string) $s['status'];
                    $map  = array('open' => array('ok', 'مفتوحة'), 'held' => array('warn', 'محجوزة مؤقتا'),
                                  'booked' => array('info', 'محجوزة'));
                    [$tone, $label] = $map[$st] ?? array('muted', $st);
                ?>
                    <tr>
                        <td data-label="الموعد">
                            <span class="tqa-num"><?php echo date('Y-m-d H:i', $when); ?></span>
                            <?php if ($when < time()): ?>
                                <br><span style="color:var(--tq-text3);font-size:12px">مضت</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="المعلم"><?php echo html_escape($s['teacher_name'] ?: '—'); ?></td>
                        <td data-label="المدة"><span class="tqa-num"><?php echo (int) $s['duration_min']; ?></span> د</td>
                        <td data-label="الحالة">
                            <span class="tqa-badge tqa-badge--<?php echo $tone; ?>"><?php echo html_escape($label); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>
</div>
