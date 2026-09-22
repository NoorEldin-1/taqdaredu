<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-COUPON — أكواد الخصم: ما يعمل منها، وما استعمل، وتوليد الجديد.
 *
 * ثلاثة أسئلة بترتيب من يفتح الشاشة: **كم أعطينا وكم جلب؟** (الأرقام)،
 * ثم **أين هذا الكود؟** (القائمة بمرشحاتها)، ثم **أعطني أكوادا جديدة**
 * (التوليد في ذيلها — وهو فعل مرة، والقراءة كل يوم).
 *
 * والتحرير الكامل للكود الواحد في الوحدة الموصوفة (`form/coupons`)، فلا
 * نموذج ثان هنا يفترق عنها عند أول حقل يضاف — إلا التوليد: دفعة من مئة
 * كود ليست مئة نموذج.
 */

$f       = isset($f) ? $f : array('q' => '', 'batch' => '', 'state' => '');
$rows    = isset($rows) ? $rows : array();
$batches = isset($batches) ? $batches : array();
$totals  = isset($totals) ? $totals : array('codes' => 0, 'live' => 0, 'paid' => 0, 'discount' => 0, 'revenue' => 0, 'held' => 0);
$kinds   = isset($kinds) ? $kinds : array();
$plans   = isset($plans) ? $plans : array();

$sar = function ($halalas) {
    return '<span class="tqa-num">' . number_format(((int) $halalas) / 100, 0) . '</span> ' . t('ر.س');
};
$tone = array('ok' => 'ok', 'warn' => 'warn', 'no' => 'danger');

$states = array('' => t('كل الحالات'), 'live' => t('يعمل'), 'scheduled' => t('مجدول'),
                'exhausted' => t('استنفد'), 'expired' => t('منته'), 'off' => t('موقوف'));

$tools = '<a class="tqa-btn tqa-btn--ghost" href="#tqa-gen">' . tq_icon('plus', 16) . ' ' . t('توليد دفعة') . '</a>'
       . '<a class="tqa-btn tqa-btn--primary" href="' . site_url('taqdar_admin/form/coupons') . '">'
       . tq_icon('tag', 16) . ' ' . t('كود جديد') . '</a>';
?>

<?php tqa_head(t('أكواد الخصم'),
    t('كود بنسبة خصم يكتبه المشتري في شاشة الدفع — ويحسب على ما يدفع فعلا، ويقسم نصيب المعلم مما دفع.'),
    'tag', $tools); ?>

<?php if ($m = tq_flash('flash_message')): ?>
    <p class="tqa-note tqa-section"><span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        <span style="flex:1"><?php echo html_escape($m); ?></span></p>
<?php endif; ?>
<?php if ($m = tq_flash('error_message')): ?>
    <p class="tqa-note tqa-note--warn tqa-section"><span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span style="flex:1"><?php echo html_escape($m); ?></span></p>
<?php endif; ?>

<?php /* الدفعة المولدة للتو — تعرض مرة للنسخ. من ولد مئة كود يريدها في
         ملف أو في رسالة الآن، لا أن يمر على مئة صف ينسخها صفا صفا. */ ?>
<?php if (!empty($fresh['codes'])): ?>
    <section class="tqa-card tqa-section">
        <div class="tqa-card__head">
            <h2><?php echo tq_icon('copy', 18); ?> <?php echo t('الأكواد الجديدة'); ?>
                <span class="tqa-badge tqa-badge--ok"><?php echo count($fresh['codes']); ?></span></h2>
            <div class="tqa-btngroup">
                <button type="button" class="tqa-btn tqa-btn--sm" data-tqa-copy="#tqaFresh"><?php echo t('نسخ الكل'); ?></button>
                <?php if (!empty($fresh['batch'])): ?>
                    <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                       href="<?php echo site_url('taqdar_admin/coupons_export?batch=' . rawurlencode($fresh['batch'])); ?>"><?php echo t('تنزيل CSV'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <textarea id="tqaFresh" class="tqa-input tqa-input--ltr" rows="<?php echo min(8, max(2, count($fresh['codes']))); ?>"
                  readonly dir="ltr" style="font-family:monospace;inline-size:100%"><?php
            echo html_escape(implode("\n", $fresh['codes'])); ?></textarea>
        <p class="tqa-field__hint" style="margin-block-start:8px">
            <?php echo t('رابط جاهز للمشاركة يطبق الكود وحده عند الدفع:'); ?>
            <span class="tqa-mono" dir="ltr"><?php echo html_escape(site_url('plans') . '?coupon=' . $fresh['codes'][0]); ?></span>
        </p>
    </section>
<?php endif; ?>

<div class="tqa-grid tqa-grid--4" style="margin-block-end:var(--tq-space-xl)">
    <?php
    $tiles = array(
        array(t('أكواد تعمل الآن'), '<span class="tqa-num">' . (int) $totals['live'] . '</span>',
              t('من ') . (int) $totals['codes'] . t(' كودا'), 'tag', 'tqa-mint'),
        array(t('مرات الاستعمال'), '<span class="tqa-num">' . (int) $totals['paid'] . '</span>',
              (int) $totals['held'] . t(' محجوزة بفواتير لم تسدد'), 'chart', 'tqa-sky'),
        array(t('ما وفره المشترون'), $sar($totals['discount']),
              t('مجموع الخصم في الشراء المسدد'), 'wallet', 'tqa-peach'),
        array(t('بيع بالأكواد'), $sar($totals['revenue']),
              t('ما دفعه المشترون بعد الخصم'), 'money', 'tqa-lilac'),
    );
    foreach ($tiles as $t): ?>
        <div class="tqa-stat">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label"><?php echo html_escape($t[0]); ?></span>
                <span class="tqa-stat__icon <?php echo $t[4]; ?>"><?php echo tq_icon($t[3], 17); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo $t[1]; ?></span>
            <span class="tqa-stat__hint"><?php echo html_escape($t[2]); ?></span>
        </div>
    <?php endforeach; ?>
</div>

<?php /* المرشحات في الرابط كالكتالوج — فالتصدير يأخذ المعروض نفسه. */ ?>
<div class="tqa-toolbar tqa-toolbar--wrap" style="margin-block-end:var(--tq-space-l)">
    <form method="get" action="<?php echo site_url('taqdar_admin/coupons'); ?>" role="search"
          style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input class="tqa-input" type="search" name="q" value="<?php echo html_escape($f['q']); ?>"
               placeholder="<?php echo te('ابحث بالكود أو الاسم أو الدفعة…'); ?>" aria-label="<?php echo te('بحث'); ?>">
        <select class="tqa-select" name="state" aria-label="<?php echo te('الحال'); ?>">
            <?php foreach ($states as $k => $lbl): ?>
                <option value="<?php echo $k; ?>" <?php echo $f['state'] === $k ? 'selected' : ''; ?>><?php echo html_escape($lbl); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($batches): ?>
            <select class="tqa-select" name="batch" aria-label="<?php echo te('الدفعة'); ?>">
                <option value=""><?php echo t('كل الدفعات'); ?></option>
                <?php foreach ($batches as $b => $n): ?>
                    <option value="<?php echo html_escape($b); ?>" <?php echo $f['batch'] === (string) $b ? 'selected' : ''; ?>>
                        <?php echo html_escape($b); ?> (<?php echo (int) $n; ?>)</option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button class="tqa-btn tqa-btn--ghost" type="submit"><?php echo tq_icon('filter', 16); ?> <?php echo t('عرض'); ?></button>
        <?php if ($f['q'] !== '' || $f['state'] !== '' || $f['batch'] !== ''): ?>
            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="<?php echo site_url('taqdar_admin/coupons'); ?>"><?php echo t('مسح'); ?></a>
        <?php endif; ?>
    </form>
    <div class="tqa-toolbar__end" style="display:flex;gap:8px">
        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
           href="<?php echo html_escape(site_url('taqdar_admin/coupons_export') . '?' . http_build_query(array_filter($f, 'strlen'))); ?>">
            <?php echo tq_icon('download', 16); ?> <?php echo t('تصدير المعروض'); ?></a>
        <?php if ($f['batch'] !== ''): ?>
            <?php /* الدفعة توقف كلها بضغطة: كود تسرب إلى مجموعة واتساب لا
                     يعرف أيها من مئة، والدفعة كلها تعرف. */ ?>
            <form method="post" action="<?php echo site_url('taqdar_admin/coupon_batch'); ?>" style="margin:0"
                  data-tqa-confirm="<?php echo te('لا يقبل أي كود منها في شراء جديد. وما صدرت فواتيره قبل الإيقاف يسوى بخصمه.'); ?>"
                  data-tqa-confirm-title="<?php echo te('إيقاف كل أكواد هذه الدفعة؟'); ?>"
                  data-tqa-confirm-ok="<?php echo te('نعم، أوقفها'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="batch" value="<?php echo html_escape($f['batch']); ?>">
                <input type="hidden" name="on" value="0">
                <button class="tqa-btn tqa-btn--quiet-danger tqa-btn--sm" type="submit"><?php echo t('أوقف الدفعة كلها'); ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<section class="tqa-card tqa-card--flush tqa-section">
<?php if (!$rows): ?>
    <?php tqa_empty(
        ($f['q'] !== '' || $f['state'] !== '' || $f['batch'] !== '') ? t('لا كود يطابق المرشح') : t('لا أكواد خصم بعد'),
        ($f['q'] !== '' || $f['state'] !== '' || $f['batch'] !== '')
            ? t('غير المرشح أو امسحه.')
            : t('أنشئ كودا واحدا باسم تختاره للإعلان العام، أو ولد دفعة أكواد فردية توزعها. ولا يظهر حقل الكود في شاشة الدفع حتى يعمل كود واحد.'),
        t('كود جديد'), site_url('taqdar_admin/form/coupons'), 'tag'); ?>
<?php else: ?>
    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead><tr>
            <th><?php echo t('الكود'); ?></th>
            <th><?php echo t('الخصم'); ?></th>
            <th><?php echo t('يعمل على'); ?></th>
            <th><?php echo t('الاستعمال'); ?></th>
            <th><?php echo t('وفر / جلب'); ?></th>
            <th><?php echo t('الحال'); ?></th>
            <th class="tqa-col--acts"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $on = array();
            foreach ($kinds as $k => $d) if ((int) $r[$d['col']] === 1) $on[] = $d['label'];
            $all_kinds = count($on) === count($kinds);
            $narrow = trim((string) $r['plan_ids'] . $r['course_ids'] . $r['book_ids']) !== '';
        ?>
            <tr>
                <td data-label="<?php echo te('الكود'); ?>">
                    <a href="<?php echo site_url('taqdar_admin/coupon/' . (int) $r['id']); ?>"
                       class="tqa-mono" dir="ltr" style="font-weight:700"><?php echo html_escape($r['code']); ?></a>
                    <?php if ($r['label'] !== '' || $r['batch'] !== ''): ?>
                        <br><span class="tqa-dim" style="font-size:12px"><?php
                            echo html_escape(trim($r['label'] . ($r['batch'] !== '' ? ' · ' . $r['batch'] : ''), ' ·')); ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="<?php echo te('الخصم'); ?>">
                    <strong class="tqa-num"><?php echo (int) $r['percent'] . t('٪'); ?></strong>
                    <?php if ($r['max_discount'] !== null): ?>
                        <br><span class="tqa-dim" style="font-size:12px"><?php echo t('بحد ____', array($sar($r['max_discount']))); ?></span>
                    <?php endif; ?>
                    <?php if ($r['min_amount'] !== null): ?>
                        <br><span class="tqa-dim" style="font-size:12px"><?php echo t('من ____ فأكثر', array($sar($r['min_amount']))); ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="<?php echo te('يعمل على'); ?>" style="font-size:13px">
                    <?php echo $all_kinds ? t('كل المشتريات') : html_escape(implode(t('، '), $on)); ?>
                    <?php if ($narrow): ?><br><span class="tqa-badge tqa-badge--info"><?php echo t('عناصر محددة'); ?></span><?php endif; ?>
                    <?php if ((int) $r['first_purchase'] === 1): ?><br><span class="tqa-badge tqa-badge--muted"><?php echo t('أول شراء'); ?></span><?php endif; ?>
                </td>
                <td data-label="<?php echo te('الاستعمال'); ?>">
                    <span class="tqa-num"><?php echo (int) $r['use']['paid']; ?></span>
                    <?php echo $r['max_uses'] !== null ? ' / <span class="tqa-num">' . (int) $r['max_uses'] . '</span>' : ''; ?>
                    <?php if ($r['use']['held'] > 0): ?>
                        <br><span class="tqa-dim" style="font-size:12px"><?php echo t('+ ____ محجوز', array((int) $r['use']['held'])); ?></span>
                    <?php endif; ?>
                    <br><span class="tqa-dim" style="font-size:12px"><?php echo (int) $r['per_user'] > 0
                        ? t('____ لكل حساب', array((int) $r['per_user'])) : t('بلا حد للحساب'); ?></span>
                </td>
                <td data-label="<?php echo te('وفر / جلب'); ?>" style="font-size:13px">
                    <?php echo $sar($r['use']['discount']); ?>
                    <br><span class="tqa-dim"><?php echo $sar($r['use']['revenue']); ?></span>
                </td>
                <td data-label="<?php echo te('الحال'); ?>">
                    <span class="tqa-badge tqa-badge--<?php echo isset($tone[$r['state']['tone']]) ? $tone[$r['state']['tone']] : 'muted'; ?>">
                        <?php echo html_escape($r['state']['label']); ?></span>
                    <span class="tqa-status__why"><?php echo html_escape($r['state']['why']); ?></span>
                </td>
                <td class="tqa-col--acts" data-label="<?php echo te('إجراءات'); ?>">
                    <?php echo tqa_rowmenu(array(
                        array('label' => t('من استعمله'), 'href' => site_url('taqdar_admin/coupon/' . (int) $r['id']),
                              'icon' => 'users', 'tone' => 'go'),
                        array('label' => t('تعديل'), 'href' => site_url('taqdar_admin/form/coupons/' . (int) $r['id']), 'icon' => 'edit'),
                        array('sep' => true),
                        array('label'  => (int) $r['active'] === 1 ? t('إيقاف') : t('تفعيل'),
                              'action' => 'taqdar_admin/coupon_toggle', 'icon' => (int) $r['active'] === 1 ? 'x' : 'check',
                              'hidden' => array('id' => (int) $r['id'], 'on' => (int) $r['active'] === 1 ? 0 : 1),
                              'tone'   => (int) $r['active'] === 1 ? 'danger' : ''),
                    ), array('title' => $r['code'], 'sub' => '#' . (int) $r['id'])); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</section>

<?php /* ====================================================================
         التوليد
         ==================================================================== */ ?>
<section class="tqa-card tqa-section" id="tqa-gen">
    <div class="tqa-card__head"><h2><?php echo tq_icon('plus', 18); ?> <?php echo t('أنشئ أكوادا'); ?></h2></div>

    <form method="post" action="<?php echo site_url('taqdar_admin/coupon_generate'); ?>">
        <?php echo tq_csrf(); ?>

        <p class="tqa-formsec"><?php echo t('الكود'); ?></p>
        <div class="tqa-grid tqa-grid--2">
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_custom"><?php echo t('كود واحد باسم تختاره'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="g_custom" name="custom_code" dir="ltr" maxlength="40"
                       placeholder="RAMADAN25" style="text-transform:uppercase">
                <span class="tqa-field__hint"><?php echo t('للإعلان العام: كود واحد يستعمله كثيرون. اتركه فارغا لتولد دفعة أكواد عشوائية.'); ?></span>
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_count"><?php echo t('أو عدد أكواد عشوائية'); ?></label>
                <div style="display:flex;gap:8px">
                    <input class="tqa-input tqa-input--ltr" id="g_count" name="count" type="number" min="1" max="500" value="20" dir="ltr" style="inline-size:90px">
                    <input class="tqa-input tqa-input--ltr" name="prefix" maxlength="16" dir="ltr" placeholder="<?php echo te('بادئة: SCHOOL'); ?>"
                           aria-label="<?php echo te('بادئة الأكواد'); ?>" style="text-transform:uppercase">
                    <input class="tqa-input tqa-input--ltr" name="length" type="number" min="5" max="12" value="6" dir="ltr"
                           aria-label="<?php echo te('طول الجزء العشوائي'); ?>" title="<?php echo te('طول الجزء العشوائي'); ?>" style="inline-size:70px">
                </div>
                <span class="tqa-field__hint"><?php echo t('كود لكل شخص (مثل SCHOOL-7KQ4MX) — تعرف من استعمل ماذا، وتوقف ما تسرب وحده.'); ?></span>
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_label"><?php echo t('الاسم الداخلي'); ?></label>
                <input class="tqa-input" id="g_label" name="label" maxlength="190" placeholder="<?php echo te('حملة العودة إلى المدارس'); ?>">
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_batch"><?php echo t('اسم الدفعة'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="g_batch" name="batch" maxlength="60" dir="ltr" placeholder="<?php echo te('يولد تلقائيا إن ترك'); ?>">
                <span class="tqa-field__hint"><?php echo t('به ترشح الأكواد وتصدر وتوقف معا.'); ?></span>
            </div>
        </div>

        <p class="tqa-formsec"><?php echo t('الخصم'); ?></p>
        <div class="tqa-grid tqa-grid--2">
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_pct"><?php echo t('نسبة الخصم %'); ?> <span class="tqa-field__req">*</span></label>
                <input class="tqa-input tqa-input--ltr" id="g_pct" name="percent" type="number" min="1" max="100" value="10" required dir="ltr">
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_cap"><?php echo t('حد أقصى للخصم (ريال)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="g_cap" name="max_discount" type="number" min="0" step="1" dir="ltr" placeholder="<?php echo te('بلا حد'); ?>">
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_min"><?php echo t('أقل مبلغ للشراء (ريال)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="g_min" name="min_amount" type="number" min="0" step="1" dir="ltr" placeholder="<?php echo te('بلا حد'); ?>">
            </div>
        </div>

        <p class="tqa-formsec"><?php echo t('على ماذا يعمل'); ?></p>
        <div style="display:flex;gap:18px;flex-wrap:wrap;margin-block-end:12px">
            <?php foreach ($kinds as $k => $d): ?>
                <label class="tqa-check"><input type="checkbox" name="<?php echo $d['col']; ?>" value="1" checked>
                    <span><?php echo html_escape($d['label']); ?></span></label>
            <?php endforeach; ?>
        </div>
        <?php if ($plans): ?>
            <details style="margin-block-end:12px">
                <summary class="tqa-field__label" style="cursor:pointer"><?php echo t('حصره في باقات بعينها (اختياري)'); ?></summary>
                <div class="tqa-picks__grid" style="margin-block-start:8px">
                    <?php foreach ($plans as $pid => $plbl): ?>
                        <label class="tqa-pick"><input type="checkbox" name="plan_ids[]" value="<?php echo (int) $pid; ?>">
                            <span><?php echo html_escape($plbl); ?></span></label>
                    <?php endforeach; ?>
                </div>
                <span class="tqa-field__hint"><?php echo t('بلا تحديد يعمل على كل الباقات. والكورسات والكتب تحصر من شاشة تعديل الكود.'); ?></span>
            </details>
        <?php endif; ?>

        <p class="tqa-formsec"><?php echo t('الحدود والمدة'); ?></p>
        <div class="tqa-grid tqa-grid--2">
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_uses"><?php echo t('عدد مرات الاستعمال لكل كود'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="g_uses" name="max_uses" type="number" min="0" dir="ltr" placeholder="<?php echo te('بلا حد'); ?>">
                <span class="tqa-field__hint"><?php echo t('في الدفعة العشوائية اجعله ١: كود لشخص واحد.'); ?></span>
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_per"><?php echo t('لكل حساب'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="g_per" name="per_user" type="number" min="0" value="1" dir="ltr">
                <span class="tqa-field__hint"><?php echo t('٠ = بلا حد (يصلح للتجديد الشهري).'); ?></span>
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_from"><?php echo t('يبدأ في'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="g_from" name="starts_at" type="datetime-local" dir="ltr">
            </div>
            <div class="tqa-field">
                <label class="tqa-field__label" for="g_to"><?php echo t('ينتهي في'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="g_to" name="ends_at" type="datetime-local" dir="ltr">
            </div>
            <div class="tqa-field">
                <label class="tqa-check"><input type="checkbox" name="first_purchase" value="1">
                    <span><?php echo t('لأول شراء على المنصة فقط'); ?></span></label>
            </div>
        </div>

        <?php /* صف عادي لا `tqa-formbar`: ذاك لاصق بأسفل الشاشة لنموذج
                 الوحدة الموصوفة، وهنا يطفو فوق أول حقول التوليد. */ ?>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-block-start:var(--tq-space-l)">
            <button class="tqa-btn tqa-btn--primary" type="submit"><?php echo t('أنشئ'); ?></button>
            <span class="tqa-field__hint"><?php echo t('الخصم يطبق عند الدفع على المبلغ المختار، ويقرب إلى ريال كامل لصالح المنصة.'); ?></span>
        </div>
    </form>
</section>

<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest && e.target.closest('[data-tqa-copy]');
  if (!b) return;
  var el = document.querySelector(b.getAttribute('data-tqa-copy'));
  if (!el) return;
  el.select();
  var done = function () { b.textContent = '<?php echo te('نسخت'); ?>'; };
  if (navigator.clipboard) navigator.clipboard.writeText(el.value).then(done, function () { document.execCommand('copy'); done(); });
  else { document.execCommand('copy'); done(); }
});
</script>
