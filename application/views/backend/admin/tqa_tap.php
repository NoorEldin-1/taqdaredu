<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * بوابة الدفع — تاب.
 *
 * شاشة واحدة تجيب ثلاثة أسئلة تسأل في هذا الترتيب:
 *
 * ١ — **هل الدفع شغال الآن، وبأي وضع؟** يعلن في أعلى الشاشة قبل أي حقل:
 *     بوابة مفعلة في وضع الاختبار تقبل الدفع ولا تحصل مالا، وهي أخطر
 *     حالة في هذه الصفحة كلها — فتقال بشارة لا تخفى.
 * ٢ — **ما المفاتيح؟** تلصق هنا وتخزن في `settings` لا في الشيفرة.
 *     والحقول تعرض ملثمة: من فتح الشاشة ليبدل الوضع لا يقصد أن يمسح
 *     مفاتيحه، فالفارغ يبقي المحفوظ والمسح بمربع صريح.
 * ٣ — **هل وصلت دفعة فلان؟** جدول المحاولات أسفل الشاشة يجيب بصف، وفيه
 *     زر «اسأل تاب» لمن يقول «دفعت ولم يفتح».
 *
 * ولماذا لا تكتفى بـ`admin/payment_settings`: تلك الشاشة تفرض `required`
 * على كل مفتاح متى فعلت البوابة، فمن يبدأ بالاختبار وحده لا يستطيع أن
 * يحفظ. ولا تعرض الوضع الجاري أصلا.
 */
$mode    = (string) $cfg['mode'];
$on      = !empty($cfg['enabled']);
$live    = $on && $ready && $mode === 'live';
$testing = $on && $ready && $mode === 'test';

/** آخر أربعة محارف — يكفي ليعرف صاحبه أنه هو، ولا يكفي لاستعماله. */
$tq_mask = function ($v) {
    $v = (string) $v;
    if ($v === '') return '';
    return '•••••••• ' . substr($v, -4);
};

$tq_keys = array(
    'test_secret' => array(t('المفتاح السري — اختبار'), 'sk_test_…', t('وهو المفتاح الذي يوقع الطلبات. لا يعرض للمتصفح أبدا.')),
    'test_public' => array(t('المفتاح العام — اختبار'), 'pk_test_…', t('يعرض للمتصفح، ولا ضرر في ظهوره.')),
    'live_secret' => array(t('المفتاح السري — إنتاج'), 'sk_live_…', t('به تخصم أموال حقيقية. اقصر من يصل إلى هذه الشاشة.')),
    'live_public' => array(t('المفتاح العام — إنتاج'), 'pk_live_…', t('يعرض للمتصفح، ولا ضرر في ظهوره.')),
);

$tq_states = array(
    'initiated' => array('warn',   t('لم تكتمل')),
    'paid'      => array('ok',     t('دفعت')),
    'failed'    => array('danger', t('لم تنجح')),
    'mismatch'  => array('danger', t('مبلغ لا يطابق')),
);

/* عنوان الويبهوك يعرض ولو لم يكن صالحا: من يطور على `localhost` يحتاج أن
   يعرف **لماذا** لا يصل نداء إليه، لا أن يبحث عنه في شيفرة. */
$tq_hook   = site_url('payment/tap/webhook');
$tq_public = (strpos($tq_hook, 'https://') === 0)
          && strpos(parse_url($tq_hook, PHP_URL_HOST), '.') !== false
          && !in_array(parse_url($tq_hook, PHP_URL_HOST), array('localhost', '127.0.0.1'), true);
?>

<?php tqa_head(t('بوابة الدفع — تاب'),
    t('الدفع بالبطاقة: مدى وفيزا وماستركارد. المفاتيح تحفظ هنا، والدفعات تسوى من رد تاب لا من رد المتصفح.'),
    'card',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/bank') . '">'
  . tq_icon('bank', 16) . t('بيانات التحويل البنكي</a>')); ?>

<?php /* الحال قبل الحقول: من يفتح الشاشة يريد أولا أن يعرف ماذا يرى
         الطالب الآن — لا أن يقرأ نموذجا ليستنتجه. */ ?>
<?php if ($live): ?>
    <div class="tqa-note tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        <span>
            <strong><?php echo t('الدفع بالبطاقة شغال في وضع الإنتاج.'); ?></strong>
            <?php echo t('كل دفعة تخصم مالا حقيقيا، ومن يدفع يفعل اشتراكه بنفسه بلا تفعيل يدوي.'); ?>
        </span>
    </div>
<?php elseif ($testing): ?>
    <div class="tqa-note tqa-note--warn tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo t('وضع الاختبار — لا يحصل مالا.'); ?></strong>
            <?php echo t('الطالب يرى خيار البطاقة ويدفع وينجح الدفع ظاهريا، ولا يصل ريال واحد. حول الوضع إلى «إنتاج» قبل الإطلاق، وتأكد أن مفتاح الإنتاج محفوظ.'); ?>
        </span>
    </div>
<?php elseif ($on): ?>
    <div class="tqa-note tqa-note--warn tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo t('مفعلة بلا مفتاح سري.'); ?></strong>
            لا مفتاح سري محفوظ لوضع <?php echo $mode === 'live' ? t('الإنتاج') : t('الاختبار'); ?>،
            فلا يعرض للطالب خيار البطاقة — ويبقى التحويل البنكي وحده. الصق المفتاح أدناه.
        </span>
    </div>
<?php else: ?>
    <div class="tqa-note tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('lock', 18); ?></span>
        <span>
            <strong><?php echo t('البوابة معطلة.'); ?></strong>
            <?php echo t('الطالب يرى التحويل البنكي وحده في شاشة الاشتراك — لا خيار بطاقة ولا زر ينتهي إلى خطأ. وهذه حال سليمة تماما لمنصة لم تفتح حساب تاب بعد.'); ?>
        </span>
    </div>
<?php endif; ?>

<div class="tqa-grid tqa-grid--3" style="margin-block-end:var(--tq-space-xl)">
    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('دفعات نجحت'); ?></span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('check', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $totals['paid']; ?></span>
        <span class="tqa-stat__hint">بمجموع <?php echo tqa_money((int) $totals['sum_paid']); ?></span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('لم تكتمل'); ?></span>
            <span class="tqa-stat__icon <?php echo $totals['initiated'] ? 'tqa-peach' : 'tqa-mint'; ?>" aria-hidden="true">
                <?php echo tq_icon('clock', 18); ?>
            </span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $totals['initiated']; ?></span>
        <span class="tqa-stat__hint"><?php echo t('بدأت ولم تنته — يسألها الكرون كل ربع ساعة'); ?></span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('أخفقت أو لم تطابق'); ?></span>
            <span class="tqa-stat__icon <?php echo ($totals['failed'] + $totals['mismatch']) ? 'tqa-rose' : 'tqa-mint'; ?>" aria-hidden="true">
                <?php echo tq_icon('alert', 18); ?>
            </span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $totals['failed'] + (int) $totals['mismatch']; ?></span>
        <span class="tqa-stat__hint">
            <?php echo (int) $totals['mismatch']; ?> منها مبلغها لا يطابق الفاتورة
        </span>
    </div>
</div>

<div class="tqa-cols">
    <div class="tqa-stack">

        <form class="tqa-card" method="post" action="<?php echo site_url('taqdar_admin/tap_save'); ?>">
            <?php echo tq_csrf(); ?>

            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox <?php echo $ready ? 'tqa-mint' : ''; ?>" aria-hidden="true">
                    <?php echo tq_icon('card', 20); ?>
                </span>
                <h2><?php echo t('إعدادات البوابة'); ?></h2>
                <span class="tqa-row" style="margin-inline-start:auto;gap:var(--tq-space-xs)">
                    <span class="tqa-badge tqa-badge--<?php echo $ready ? 'ok' : 'muted'; ?>">
                        <?php echo $ready ? t('شغالة') : t('لا تعرض للطالب'); ?>
                    </span>
                    <?php if ($testing): ?>
                        <span class="tqa-badge tqa-badge--danger"><?php echo t('وضع اختبار'); ?></span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="tqa-prefrow">
                <div class="tqa-prefrow__main">
                    <label class="tqa-prefrow__title" for="tap_on"><?php echo t('تفعيل الدفع بالبطاقة'); ?></label>
                    <span class="tqa-prefrow__hint">
                        <?php echo t('المعطلة لا تظهر للطالب أصلا، ويبقى التحويل البنكي وحده.'); ?>
                    </span>
                </div>
                <div class="tqa-prefrow__end">
                    <?php /* الحقل المخفي يسبق المفتاح: مربع غير محدد لا يرسل
                             شيئا، فبدونه يبقى ما كان. */ ?>
                    <input type="hidden" name="tq_tap_enabled" value="0">
                    <span class="tqa-switch">
                        <input type="checkbox" id="tap_on" name="tq_tap_enabled" value="1"
                               <?php echo $on ? 'checked' : ''; ?>>
                        <span class="tqa-switch__track" aria-hidden="true"></span>
                    </span>
                </div>
            </div>

            <div class="tqa-fieldgrid" style="margin-block-start:var(--tq-space-l)">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="tap_mode">
                        <?php echo t('الوضع'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
                    </label>
                    <select class="tqa-select" id="tap_mode" name="tq_tap_mode" required>
                        <option value="test" <?php echo $mode === 'test' ? 'selected' : ''; ?>>
                            <?php echo t('اختبار — لا يحصل مالا'); ?>
                        </option>
                        <option value="live" <?php echo $mode === 'live' ? 'selected' : ''; ?>>
                            <?php echo t('إنتاج — يخصم مالا حقيقيا'); ?>
                        </option>
                    </select>
                    <span class="tqa-field__hint">
                        <?php echo t('الوضع يختار المفتاح المستعمل. ومفتاح اختبار في وضع إنتاج يعني دفعا ينجح بلا مال — فافحص المفتاح بالزر أدناه بعد الحفظ.'); ?>
                    </span>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="tap_merchant"><?php echo t('معرف التاجر'); ?></label>
                    <input class="tqa-input tqa-input--ltr" type="text" id="tap_merchant"
                           name="tq_tap_merchant" dir="ltr" autocomplete="off" spellcheck="false"
                           value="<?php echo html_escape($cfg['merchant']); ?>"
                           placeholder="68070328">
                    <span class="tqa-field__hint">
                        <?php echo t('اختياري — من لوحة تاب (Merchant ID). يرسل مع كل دفعة إن ملئ.'); ?>
                    </span>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label"><?php echo t('عملة الدفع'); ?></label>
                    <input class="tqa-input tqa-input--ltr" type="text" dir="ltr" readonly
                           value="<?php echo html_escape($cfg['currency']); ?>">
                    <span class="tqa-field__hint">
                        <?php echo t('تقرأ من «عملة النظام» في'); ?>
                        <a href="<?php echo site_url('admin/payment_settings'); ?>"><?php echo t('إعدادات الدفع'); ?></a> <?php echo t('— فلا تختلف عما عرض على الطالب.'); ?>
                    </span>
                </div>
            </div>

            <?php foreach (array('test' => t('مفاتيح الاختبار'), 'live' => t('مفاتيح الإنتاج')) as $tq_m => $tq_h): ?>
                <h3 class="tqa-field__label" style="margin-block:var(--tq-space-l) var(--tq-space-s)">
                    <?php echo html_escape($tq_h); ?>
                    <?php if ($mode === $tq_m): ?>
                        <span class="tqa-badge tqa-badge--ok"><?php echo t('الوضع الجاري'); ?></span>
                    <?php endif; ?>
                </h3>

                <div class="tqa-fieldgrid">
                    <?php foreach ($tq_keys as $tq_k => $tq_meta):
                        if (strpos($tq_k, $tq_m . '_') !== 0) continue;
                        $tq_has = $cfg['keys'][$tq_k] !== '';
                    ?>
                        <div class="tqa-field">
                            <label class="tqa-field__label" for="k_<?php echo $tq_k; ?>">
                                <?php echo html_escape($tq_meta[0]); ?>
                                <?php if ($tq_has): ?>
                                    <span class="tqa-badge tqa-badge--ok"><?php echo t('محفوظ'); ?></span>
                                <?php endif; ?>
                            </label>
                            <input class="tqa-input tqa-input--ltr" type="text" id="k_<?php echo $tq_k; ?>"
                                   name="tq_tap_<?php echo $tq_k; ?>" dir="ltr"
                                   autocomplete="off" spellcheck="false" value=""
                                   placeholder="<?php echo $tq_has ? html_escape($tq_mask($cfg['keys'][$tq_k])) : $tq_meta[1]; ?>">
                            <span class="tqa-field__hint">
                                <?php echo html_escape($tq_meta[2]); ?>
                                <?php if ($tq_has): ?>
                                    اتركه فارغا ليبقى المحفوظ كما هو.
                                    <label style="display:inline-flex;align-items:center;gap:6px;margin-inline-start:8px">
                                        <input type="checkbox" name="clear[]" value="<?php echo $tq_k; ?>">
                                        <?php echo t('امسح هذا المفتاح'); ?>
                                    </label>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--primary"
                        data-tqa-confirm-title="<?php echo te('حفظ إعدادات بوابة الدفع'); ?>"
                        data-tqa-confirm="ما يحفظ هنا يظهر فورا في شاشة الدفع أمام الطلاب."
                        data-tqa-confirm-ok="احفظ">
                    <?php echo tq_icon('check', 16); ?> احفظ الإعدادات
                </button>
            </div>
        </form>

        <?php /* الفحص نموذجان مستقلان: كل واحد يفحص وضعا بعينه، ورقم واحد
                 يفحص «الجاري» وحده يترك مفتاح الإنتاج بلا اختبار حتى أول
                 دفعة حقيقية — وهو أسوأ موضع يكتشف فيه خطأ لصق. */ ?>
        <div class="tqa-card">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox" aria-hidden="true"><?php echo tq_icon('shield', 20); ?></span>
                <h2><?php echo t('افحص المفاتيح'); ?></h2>
            </div>
            <p class="tqa-field__hint" style="margin-block-end:var(--tq-space-m)">
                <?php echo t('يسأل تاب عن دفعة غير موجودة بالمفتاح المحفوظ: المفتاح المرفوض يرد «غير مصرح»، والمقبول يرد «غير موجودة». فيعرف أن المفتاح يعمل قبل أن يجربه مشتر.'); ?>
            </p>
            <div class="tqa-row" style="gap:var(--tq-space-s);flex-wrap:wrap">
                <?php foreach (array('test' => t('افحص مفتاح الاختبار'), 'live' => t('افحص مفتاح الإنتاج')) as $tq_m => $tq_l): ?>
                    <form method="post" action="<?php echo site_url('taqdar_admin/tap_probe'); ?>" style="margin:0">
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="mode" value="<?php echo $tq_m; ?>">
                        <button type="submit" class="tqa-btn tqa-btn--ghost">
                            <?php echo tq_icon('link', 16); ?> <?php echo html_escape($tq_l); ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <aside>
        <div class="tqa-note tqa-note--warn">
            <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
            <span>
                <?php echo t('المفاتيح تحفظ في قاعدة البيانات لا في الشيفرة — والمستودع عام، والنشر يمسح ما فيه من تعديل. ومن يصل إلى هذه الشاشة يصل إليها، فاقصر صلاحية «تقدر» على من يحتاجها من'); ?>
                <a href="<?php echo site_url('admin/admins'); ?>"><?php echo t('المسؤولين'); ?></a>.
            </span>
        </div>

        <div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
            <span aria-hidden="true"><?php echo tq_icon('link', 18); ?></span>
            <span>
                <strong><?php echo t('عنوان الويبهوك'); ?></strong> <?php echo t('— يرسل مع كل دفعة تلقائيا، ولا يحتاج تسجيلا في لوحة تاب:'); ?>
                <br><code class="tq-ltr" dir="ltr" style="font-size:12px"><?php echo html_escape($tq_hook); ?></code>
                <?php if (!$tq_public): ?>
                    <br><strong><?php echo t('ولا يرسل من هذا الخادم:'); ?></strong> العنوان محلي أو بلا
                    HTTPS، فلا تصل إليه تاب. والتسوية تقع عند عودة الطالب — وهي تحقق
                    كامل لأنها تجلب الدفعة من تاب لا تقرأ الرابط.
                <?php endif; ?>
            </span>
        </div>

        <div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
            <span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
            <span>
                <strong><?php echo t('ما يقع عند الدفع:'); ?></strong> <?php echo t('يصدر الاشتراك والفاتورة أولا، ثم تفتح صفحة تاب. وحين يرجع الطالب تسأل تاب عن الدفعة بمفتاحك، ويقابل مبلغها مبلغ الفاتورة، ثم يفعل الاشتراك ويخطر صاحبه. فلا يفعل شيء برابط عودة مصنوع، ولا بمبلغ يخالف الفاتورة.'); ?>
            </span>
        </div>

        <div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
            <span aria-hidden="true"><?php echo tq_icon('clock', 18); ?></span>
            <span>
                <?php echo t('من دفع وأغلق المتصفح قبل أن يعود يفعل اشتراكه بالويبهوك. ولو لم يصل الويبهوك يقرأ الكرون ('); ?><code class="tq-ltr" dir="ltr" style="font-size:11px">taqdar_cron reconcile</code><?php echo t(') ما بقي «لم يكتمل» ويسأل تاب عنه — فلا تبقى دفعة حصلت بلا اشتراك.'); ?>
            </span>
        </div>
    </aside>
</div>

<h2 class="tqa-field__label" style="margin-block:var(--tq-space-xl) var(--tq-space-m)">
    <?php echo t('آخر المحاولات'); ?>
</h2>

<div class="tqa-card tqa-card--flush">
<?php if (!$attempts): ?>

    <?php tqa_empty(t('لا محاولة دفع بعد'),
        t('يظهر هنا صف لكل دفعة يبدؤها طالب، بحالها عند تاب ومبلغها ورقم فاتورتها.'),
        '', '', 'card'); ?>

<?php else: ?>
    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead>
            <tr>
                <th>#</th>
                <th><?php echo t('الطالب'); ?></th>
                <th><?php echo t('الفاتورة'); ?></th>
                <th><?php echo t('المبلغ'); ?></th>
                <th><?php echo t('الوضع'); ?></th>
                <th><?php echo t('الحالة'); ?></th>
                <th><?php echo t('معرف الدفعة'); ?></th>
                <th><?php echo t('التاريخ'); ?></th>
                <th><?php echo t('مراجعة'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($attempts as $a):
            [$tq_tone, $tq_label] = $tq_states[$a['status']] ?? array('muted', $a['status']);
        ?>
            <tr>
                <td data-label="#"><span class="tqa-num"><?php echo (int) $a['id']; ?></span></td>

                <td data-label="الطالب">
                    <?php echo html_escape($a['user_name'] ?: ('#' . (int) $a['user_id'])); ?>
                </td>

                <td data-label="الفاتورة">
                    <span class="tqa-num" style="font-size:12px">
                        <?php echo html_escape($a['invoice_no'] ?: ('#' . (int) $a['invoice_id'])); ?>
                    </span>
                </td>

                <td data-label="المبلغ">
                    <strong><?php echo tqa_money((int) $a['amount']); ?></strong>
                </td>

                <td data-label="الوضع">
                    <span class="tqa-badge tqa-badge--<?php echo $a['mode'] === 'live' ? 'ok' : 'muted'; ?>">
                        <?php echo $a['mode'] === 'live' ? t('إنتاج') : t('اختبار'); ?>
                    </span>
                </td>

                <td data-label="الحالة">
                    <span class="tqa-badge tqa-badge--<?php echo $tq_tone; ?>">
                        <?php echo html_escape($tq_label); ?>
                    </span>
                    <?php if (!empty($a['gateway_status'])): ?>
                        <br><span class="tqa-num" style="font-size:11px;color:var(--tq-text2)">
                            <?php echo html_escape($a['gateway_status']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($a['message'])): ?>
                        <br><span style="font-size:11px;color:var(--tq-text2)">
                            <?php echo html_escape($a['message']); ?></span>
                    <?php endif; ?>
                </td>

                <td data-label="معرف الدفعة">
                    <span class="tqa-num" style="font-size:11px">
                        <?php echo html_escape($a['charge_id'] ?: '—'); ?>
                    </span>
                </td>

                <td data-label="التاريخ">
                    <span class="tqa-num" style="font-size:12px">
                        <?php echo html_escape($a['created_at'] ?: '—'); ?>
                    </span>
                </td>

                <td data-label="مراجعة">
                    <?php if ($a['status'] === 'paid'): ?>
                        <span style="color:var(--tq-text3)"><?php echo t('سويت'); ?></span>
                    <?php elseif (empty($a['charge_id'])): ?>
                        <span style="color:var(--tq-text3)"><?php echo t('لم تبلغ البوابة'); ?></span>
                    <?php else: ?>
                        <?php /* السؤال يعاد على المحاولة نفسها: هذا هو الباب
                                 الذي يجاب منه «دفعت ولم يفتح» — بقراءة من
                                 البوابة لا بتفعيل يدوي على قول الطالب. */ ?>
                        <form method="post" action="<?php echo site_url('taqdar_admin/tap_recheck'); ?>" style="margin:0">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="charge_id" value="<?php echo html_escape($a['charge_id']); ?>">
                            <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm">
                                <?php echo tq_icon('refresh', 15); ?> اسأل تاب
                            </button>
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
    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
    <span>
        <?php echo t('«مبلغ لا يطابق» صف يستحق أن يقرأ فورا: وصلت دفعة قيمتها تخالف قيمة الفاتورة، فلم يفعل الاشتراك عمدا. راجعها في لوحة تاب قبل أن تفعل شيئا — والاسترداد يجري من هناك لا من هنا.'); ?>
    </span>
</div>
