<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-COURSE-SALE — بيع الكورسات المفردة.
 *
 * الكورس على هذه المنصة محتوى باقة: من أراد مادة واحدة يدفع ثمن المرحلة
 * كلها أو ينصرف. وهذه الشاشة تفتح الباب الثاني، وتجيب ثلاثة أسئلة لم يكن
 * في اللوحة موضع يجيب أيا منها:
 *
 *   · **أي الكورسات تباع مفردة؟** — والقائمة تعرض ما علم للبيع **ولو لم
 *     يعرض**: كورس علم وهو غير منشور أو بلا سعر يجلس ولا يظهر، وقائمة
 *     تسقطه تخفي عن المسؤول العطل الذي جاء يصلحه.
 *   · **بكم، ونصيب من؟** — والقسمة تعرض بالريال صفا بصف، من `split()`
 *     نفسها التي تنفذ لا من نسخة ثانية من قواعدها.
 *   · **ماذا بيع؟** — وهو أول ما يسأل عنه من فتح الباب.
 *
 * والمفتاح العام في ذيلها لا في «إعدادات المنصة»: هذه هي الشاشة التي
 * يظهر فيها أثره — بجوار الكورسات التي يفتحها. وهو مبدأ تسعيرة الحصص
 * نفسه: مفتاح يدفن في شاشة لا تفتح ميزة لا توجد.
 */

$cfg    = isset($cfg) ? $cfg : array('enabled' => false, 'percent' => 60, 'default_days' => 0);
$offers = isset($offers) ? $offers : array();
$sold   = isset($sold)   ? $sold   : array();
$sales  = isset($sales)  ? $sales  : array();
$rest   = isset($rest)   ? $rest   : array();
$totals = isset($totals) ? $totals : array('sold' => 0, 'gross' => 0, 'pending' => 0,
                                           'pending_amount' => 0, 'courses' => 0);

/** مبلغ بالهللات إلى ريال مقروء — وموضع القسمة على مئة واحد في الشاشة. */
$sar = function ($halalas) {
    return '<span class="tqa-num">' . number_format(((int) $halalas) / 100, 2) . t('</span> ر.س');
};

/** نسبة بلا أصفار تافهة: «٦٠٪» لا «60.00٪». */
$pct = function ($v) {
    return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . t('٪');
};

/** رقم بالريال في حقل نموذج — بنقطة عشرية لا بفاصلة آلاف. */
$field_sar = function ($halalas) {
    return number_format(((int) $halalas) / 100, 2, '.', '');
};

/* الحال ونبرتها. والترتيب هنا ترتيب `offer()` في معالجتها، فما يقرؤه
   المسؤول في العمود هو أول ما يمنع لا آخره. */
$state = array(
    'ok'          => array(t('يباع الآن'),  'ok'),
    'disabled'    => array(t('الباب مغلق'), 'muted'),
    'free'        => array(t('مجاني'),      'muted'),
    'not_marked'  => array(t('لم يعلن'),    'muted'),
    'unpublished' => array(t('غير منشور'),  'warn'),
    'unpriced'    => array(t('بلا سعر'),    'danger'),
    'no_teacher'  => array(t('بلا معلم'),   'danger'),
    'empty'       => array(t('بلا دروس'),   'warn'),
);

$sub_labels = array('pending' => t('بانتظار السداد'), 'active' => t('مفتوح'),
                    'cancelled' => t('ملغى'), 'expired' => t('منته'));
$sub_tones  = array('pending' => 'warn', 'active' => 'ok',
                    'cancelled' => 'muted', 'expired' => 'muted');

$open_n = 0;
foreach ($offers as $o) if (!empty($o['sellable'])) $open_n++;
?>

<?php tqa_head(t('بيع الكورسات'),
    t('الكورسات التي تباع مفردة خارج الباقات — بسعرها ونصيب معلمها وما بيع منها.'),
    'card'); ?>

<?php if ($m = tq_flash('flash_message')): ?>
    <p class="tqa-note tqa-section"><span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        <span style="flex:1"><?php echo html_escape($m); ?></span></p>
<?php endif; ?>
<?php if ($m = tq_flash('error_message')): ?>
    <p class="tqa-note tqa-note--warn tqa-section"><span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span style="flex:1"><?php echo html_escape($m); ?></span></p>
<?php endif; ?>

<?php /* **الباب المغلق يقال في أول الشاشة لا في ذيلها.** المسؤول الذي
         يسعر كورسا ويعلمه ثم لا يجده في الموقع يبحث عن العطل في الكورس،
         والعطل مفتاح في آخر الصفحة لم ينزل إليه. */ ?>
<?php if (!$cfg['enabled']): ?>
    <p class="tqa-note tqa-note--warn tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span style="flex:1">
            <strong><?php echo t('باب البيع المفرد مغلق.'); ?></strong>
            <span style="display:block"><?php echo t('لا يعرض كورس بسعره في الموقع مهما علم، وتبقى الباقة وحدها وحدة البيع كما كانت.'); ?></span>
        </span>
        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="#tqa-pricing"><?php echo t('افتح الباب'); ?></a>
    </p>
<?php endif; ?>

<?php /* سطر المال أولا: من يفتح هذه الشاشة يسأل «كم بيع» قبل أن يسأل عن
         كورس بعينه، وقائمة ثلاثين صفا لا تجمع نفسها. */ ?>
<div class="tqa-grid tqa-grid--4" style="margin-block-end:var(--tq-space-xl)">
    <?php
    $tiles = array(
        array(t('محصل البيع المفرد'), $sar($totals['gross']),
              t('من بيعات فعلت — والمعلق لا يعد إيرادا'), 'wallet', 'tqa-mint'),
        array(t('بيعة مفردة'), '<span class="tqa-num">' . (int) $totals['sold'] . '</span>',
              t('في ') . (int) $totals['courses'] . t(' كورسا'), 'chart', 'tqa-sky'),
        array(t('ينتظر الحوالة'), $sar($totals['pending_amount']),
              (int) $totals['pending'] . t(' فاتورة صدرت ولم تسدد'), 'clock', 'tqa-peach'),
        array(t('معروض للبيع'), '<span class="tqa-num">' . $open_n . '</span>',
              t('من ') . count($offers) . t(' كورسا علم'), 'card', 'tqa-lilac'),
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

<?php /* ====================================================================
         الكورسات المعلنة للبيع
         ==================================================================== */ ?>
<section class="tqa-card tqa-card--flush">
    <div class="tqa-card__head"><h2><?php echo tq_icon('book', 18); ?> <?php echo t('الكورسات المعلنة للبيع'); ?></h2></div>

    <?php if (!$offers): ?>
        <?php tqa_empty(t('لم يعلن كورس للبيع المفرد بعد'),
            t('أعلن كورسا من الجدول أدناه، أو من تبويب «التسعير» في شاشته. والإعلان وحده ')
          . t('لا يكفي: الكورس يحتاج سعرا ودرسا واحدا على الأقل وأن يكون منشورا.'),
            t('الكورسات'), site_url('admin/courses'), 'book'); ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr>
                    <th><?php echo t('الكورس'); ?></th>
                    <th><?php echo t('الحال'); ?></th>
                    <th><?php echo t('القسمة على السعر الحالي'); ?></th>
                    <th><?php echo t('الوصول'); ?></th>
                    <th><?php echo t('بيع'); ?></th>
                    <th><?php echo t('السعر والنسبة'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($offers as $cid => $o):
                $st = isset($state[$o['reason']]) ? $state[$o['reason']] : array($o['reason'], 'muted');
                $n  = isset($sold[$cid]) ? $sold[$cid] : null;
                /* السعر في الحقل هو **سعر القائمة** لا المخصوم: هو ما يحرر،
                   والمخصوم حقل ثان بجواره. وخلطهما يجعل حفظا بلا تعديل
                   ينزل السعر إلى سعر الخصم في كل مرة. */
                $listed = ((int) $o['list_price'] > 0) ? (int) $o['list_price'] : (int) $o['price'];
                $own_pc = ((float) $o['percent'] === (float) $cfg['percent']) ? '' : $o['percent'];
            ?>
                <tr>
                    <td data-label="<?php echo te('الكورس'); ?>">
                        <a href="<?php echo site_url('admin/course_form/course_edit/' . (int) $cid . '?tab=pricing'); ?>">
                            <strong><?php echo html_escape($o['title']); ?></strong></a>
                        <?php /* السبب تحت الاسم لا في تلميح: هو ما جاء المسؤول
                                 من أجله، وتلميح يظهر بالمرور لا يقرأ في مسح
                                 عين على جدول. */ ?>
                        <?php if (empty($o['sellable'])): ?>
                            <br><span class="tqa-dim" style="font-size:12px"><?php
                                echo html_escape($o['why']); ?></span>
                        <?php endif; ?>
                    </td>

                    <td data-label="<?php echo te('الحال'); ?>">
                        <span class="tqa-badge tqa-badge--<?php echo $st[1]; ?>"><?php
                            echo html_escape($st[0]); ?></span>
                    </td>

                    <td data-label="<?php echo te('القسمة'); ?>">
                        <?php if ((int) $o['price'] > 0): ?>
                            <?php echo t('من '); ?> <?php echo $sar($o['price']); ?>
                            <br><span style="color:var(--tq-text2);font-size:12px">
                                <?php echo t('للمعلم ____ (____) · للمنصة ____', array($sar($o['share']), html_escape($pct($o['percent'])), $sar($o['platform']))); ?></span>
                            <?php if ((int) $o['list_price'] > 0): ?>
                                <br><span class="tqa-dim" style="font-size:12px"><?php echo t('بدل ____ — خصم ____٪', array(number_format($o['list_price'] / 100, 2), (int) $o['off'])); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="tqa-dim"><?php echo t('لا سعر'); ?></span>
                        <?php endif; ?>
                    </td>

                    <td data-label="<?php echo te('الوصول'); ?>"><?php echo (int) $o['days'] > 0
                        ? '<span class="tqa-num">' . (int) round($o['days'] / 30) . t('</span> شهرا')
                        : t('دائم'); ?></td>

                    <td data-label="<?php echo te('بيع'); ?>">
                        <?php if ($n): ?>
                            <span class="tqa-num"><?php echo (int) $n['n']; ?></span>
                            <br><span class="tqa-dim" style="font-size:12px"><span class="tqa-num"><?php
                                echo number_format($n['gross'] / 100); ?></span> <?php echo t('ر.س'); ?></span>
                        <?php else: ?>
                            <span class="tqa-dim">—</span>
                        <?php endif; ?>
                    </td>

                    <?php /* التحرير في الصف لا في شاشة أخرى: من يفتح هذه
                             الشاشة يعدل أسعارا كثيرة في جلسة واحدة، والانتقال
                             إلى شاشة الكورس لكل رقم يعني عشر صفحات لعشرة
                             أرقام. وهو ما تفعله شاشة «أوقات المعلمين». */ ?>
                    <td data-label="<?php echo te('السعر والنسبة'); ?>">
                        <form method="post" action="<?php echo site_url('taqdar_admin/course_sale_save'); ?>"
                              style="margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="course_id" value="<?php echo (int) $cid; ?>">

                            <label class="tqa-check" title="<?php echo te('يظهر بسعره في الموقع ويشترى'); ?>">
                                <input type="checkbox" name="tq_sell" value="1"
                                       <?php echo !empty($o['marked']) ? 'checked' : ''; ?>>
                                <span><?php echo t('يباع'); ?></span>
                            </label>

                            <label class="tqa-sr" for="pr-<?php echo (int) $cid; ?>"><?php echo t('سعر هذا الكورس بالريال'); ?></label>
                            <input class="tqa-input tqa-input--ltr" id="pr-<?php echo (int) $cid; ?>"
                                   type="number" name="price_sar" min="0" step="0.01" dir="ltr"
                                   title="<?php echo te('السعر بالريال قبل الخصم'); ?>"
                                   style="min-block-size:34px;inline-size:88px"
                                   value="<?php echo html_escape($field_sar($listed)); ?>">

                            <label class="tqa-check" title="<?php echo te('بدونه يباع بالسعر الأصلي ولو كتب سعر الخصم'); ?>">
                                <input type="checkbox" name="discount_on" value="1"
                                       <?php echo (int) $o['list_price'] > 0 ? 'checked' : ''; ?>>
                                <span><?php echo t('خصم'); ?></span>
                            </label>

                            <label class="tqa-sr" for="pd-<?php echo (int) $cid; ?>"><?php echo t('السعر بعد الخصم بالريال'); ?></label>
                            <input class="tqa-input tqa-input--ltr" id="pd-<?php echo (int) $cid; ?>"
                                   type="number" name="discount_sar" min="0" step="0.01" dir="ltr"
                                   placeholder="<?php echo te('بعد الخصم'); ?>" title="<?php echo te('أقل من السعر أعلاه، وإلا أهمل'); ?>"
                                   style="min-block-size:34px;inline-size:88px"
                                   value="<?php echo (int) $o['list_price'] > 0
                                       ? html_escape($field_sar($o['price'])) : ''; ?>">

                            <label class="tqa-sr" for="pc-<?php echo (int) $cid; ?>"><?php echo t('نصيب المعلم بالمئة'); ?></label>
                            <?php /* الفارغ «خذ العام» والصفر «صفر بقرار» —
                                     و`placeholder` يقول ما يقع عند الفراغ. */ ?>
                            <input class="tqa-input tqa-input--ltr" id="pc-<?php echo (int) $cid; ?>"
                                   type="number" name="percent" min="0" max="100" step="0.01" dir="ltr"
                                   placeholder="<?php echo te('٪'); ?>" title="<?php echo te('نصيبه بالمئة — فارغ يعني النسبة العامة، وصفر يعني صفرا بقرار'); ?>"
                                   style="min-block-size:34px;inline-size:70px"
                                   value="<?php echo $own_pc === '' ? ''
                                       : html_escape(rtrim(rtrim(number_format((float) $own_pc, 2, '.', ''), '0'), '.')); ?>">

                            <button class="tqa-btn tqa-btn--sm" type="submit"><?php echo t('حفظ'); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php /* ====================================================================
         كورسات لم تعلن — يعلن منها الجديد بلا مغادرة الشاشة
         ==================================================================== */ ?>
<?php if ($rest): ?>
<section class="tqa-card tqa-card--flush" style="margin-block-start:var(--tq-space-xl)">
    <div class="tqa-card__head"><h2><?php echo tq_icon('plus', 18); ?> <?php echo t('منشورة ولم تعلن للبيع'); ?></h2></div>

    <p style="margin:0;padding:var(--tq-space-l) var(--tq-space-xl) 0;color:var(--tq-text2)">
        <?php echo t('هذه تفتح بالباقة وحدها اليوم. وإعلان واحد منها للبيع'); ?> <strong><?php echo t('لا يخرجه من الباقة'); ?></strong> <?php echo t('— يبقى فيها ويباع مفردا كذلك: فمن اشترك في باقة صفه يفتح له بها، ومن أراد هذه المادة وحدها يشتريها بثمنها.'); ?>
    </p>

    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead><tr><th><?php echo t('الكورس'); ?></th><th><?php echo t('السعر المحفوظ'); ?></th><th><?php echo t('أعلنه'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($rest as $r): ?>
            <tr>
                <td data-label="<?php echo te('الكورس'); ?>">
                    <a href="<?php echo site_url('admin/course_form/course_edit/'
                            . (int) $r['id'] . '?tab=pricing'); ?>"><?php
                        echo html_escape($r['title']); ?></a></td>
                <td data-label="<?php echo te('السعر'); ?>"><?php echo ((float) $r['price'] > 0)
                    ? '<span class="tqa-num">' . number_format((float) $r['price'], 2) . t('</span> ر.س')
                    : t('<span class="tqa-dim">لا سعر</span>'); ?></td>
                <td data-label="<?php echo te('أعلنه'); ?>">
                    <form method="post" action="<?php echo site_url('taqdar_admin/course_sale_save'); ?>"
                          style="margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="course_id" value="<?php echo (int) $r['id']; ?>">
                        <input type="hidden" name="tq_sell" value="1">
                        <label class="tqa-sr" for="np-<?php echo (int) $r['id']; ?>"><?php echo t('سعر هذا الكورس بالريال'); ?></label>
                        <input class="tqa-input tqa-input--ltr" id="np-<?php echo (int) $r['id']; ?>"
                               type="number" name="price_sar" min="0" step="0.01" dir="ltr"
                               placeholder="<?php echo te('السعر'); ?>" style="min-block-size:34px;inline-size:88px"
                               value="<?php echo html_escape(number_format((float) $r['price'], 2, '.', '')); ?>">
                        <button class="tqa-btn tqa-btn--sm" type="submit"><?php echo t('أعلنه للبيع'); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
<?php endif; ?>

<?php /* ====================================================================
         ما بيع
         ==================================================================== */ ?>
<section class="tqa-card tqa-card--flush" style="margin-block-start:var(--tq-space-xl)">
    <div class="tqa-card__head"><h2><?php echo tq_icon('receipt', 18); ?> <?php echo t('ما بيع مفردا'); ?></h2></div>

    <?php if (!$sales): ?>
        <?php tqa_empty(t('لا بيعة مفردة بعد'),
            t('كل شراء كورس منفرد يظهر هنا بمشتريه وفاتورته ونصيب معلمه. وتفعيل الحوالة ')
          . t('البنكية يقع من شاشة «الاشتراكات»: البيعة اشتراك صفه في الجدول نفسه.'),
            t('الاشتراكات'), site_url('taqdar_admin/subscriptions'), 'refresh'); ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr><th>#</th><th><?php echo t('المشتري'); ?></th><th><?php echo t('الكورس'); ?></th><th><?php echo t('المعلم'); ?></th>
                    <th><?php echo t('المبلغ'); ?></th><th><?php echo t('الحال'); ?></th><th><?php echo t('الفاتورة'); ?></th><th><?php echo t('الوصول حتى'); ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $s):
                $tone = isset($sub_tones[$s['status']]) ? $sub_tones[$s['status']] : 'muted';
            ?>
                <tr>
                    <td data-label="#"><span class="tqa-num"><?php echo (int) $s['id']; ?></span></td>
                    <td data-label="<?php echo te('المشتري'); ?>"><?php echo html_escape(trim((string) $s['buyer_name']) !== ''
                        ? $s['buyer_name'] : ('#' . (int) $s['user_id'])); ?></td>
                    <td data-label="<?php echo te('الكورس'); ?>">
                        <a href="<?php echo site_url('admin/course_form/course_edit/'
                                . (int) $s['course_id'] . '?tab=pricing'); ?>"><?php
                            echo html_escape($s['course_title']); ?></a></td>
                    <td data-label="<?php echo te('المعلم'); ?>"><?php echo html_escape(trim((string) $s['teacher_name']) !== ''
                        ? $s['teacher_name'] : '—'); ?></td>
                    <td data-label="<?php echo te('المبلغ'); ?>"><?php echo $sar($s['price']); ?></td>
                    <td data-label="<?php echo te('الحال'); ?>"><span class="tqa-badge tqa-badge--<?php echo $tone; ?>"><?php
                        echo html_escape(isset($sub_labels[$s['status']])
                            ? $sub_labels[$s['status']] : $s['status']); ?></span></td>
                    <td data-label="<?php echo te('الفاتورة'); ?>">
                        <?php if (!empty($s['invoice_no'])): ?>
                            <span class="tqa-num"><?php echo html_escape($s['invoice_no']); ?></span>
                            <br><span class="tqa-dim" style="font-size:12px"><?php
                                echo $s['invoice_status'] === 'paid' ? t('مدفوعة') : t('غير مدفوعة'); ?></span>
                        <?php else: ?>
                            <span class="tqa-dim">—</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php echo te('الوصول'); ?>"><?php echo !empty($s['ends_at'])
                        ? '<span class="tqa-num">' . html_escape(substr($s['ends_at'], 0, 10)) . '</span>'
                        : ($s['status'] === 'active' ? t('دائم') : '<span class="tqa-dim">—</span>'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php /* البيعة اشتراك، ففاتورتها تفعل من حيث تفعل فواتير
                 الاشتراكات — ولا زر ثان هنا يفعل الشيء نفسه بباب ثان
                 يفترق عنه عند أول تعديل. */ ?>
        <p style="margin:0;padding:var(--tq-space-l) var(--tq-space-xl);color:var(--tq-text2)">
            <?php echo t('تفعيل حوالة بنكية يقع من'); ?>
            <a href="<?php echo site_url('taqdar_admin/subscriptions'); ?>"><?php echo t('شاشة الاشتراكات'); ?></a><?php echo t(': البيعة المفردة اشتراك صفه في الجدول نفسه، ولا باب ثان يفعلها.'); ?>
        </p>
    <?php endif; ?>
</section>

<?php /* ====================================================================
         الإعدادات العامة
         ==================================================================== */ ?>
<section class="tqa-card" id="tqa-pricing" style="margin-block-start:var(--tq-space-xl)">
    <div class="tqa-card__head"><h2><?php echo tq_icon('cog', 18); ?> <?php echo t('الإعدادات العامة'); ?></h2></div>

    <p class="tqa-card__lead" style="color:var(--tq-text2)">
        <?php echo t('هذه تحكم الباب كله.'); ?> <strong><?php echo t('ولا يعرض كورس للبيع حتى يعلم «يباع مفردا»'); ?></strong> <?php echo t('في تبويب تسعيره أو في الجدول أعلاه — فتح الباب وحده لا يعرض شيئا.'); ?>
        <strong><?php echo t('والسعر والنسبة والأجل تجمد على البيعة وقت الشراء'); ?></strong><?php echo t('، فتعديلها اليوم لا يغير ما بيع أمس ولا ما قيد في دفتر معلم.'); ?>
    </p>

    <form method="post" action="<?php echo site_url('taqdar_admin/course_sales_config'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <label class="tqa-prefrow__title" for="cs-on"><?php echo t('بيع الكورسات المفردة'); ?></label>
                <span class="tqa-prefrow__hint"><?php echo t('مغلقا: الباقة وحدها وحدة البيع، ولا تتغير صفحة واحدة في الموقع عما هي عليه اليوم.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <span class="tqa-switch">
                    <input type="checkbox" id="cs-on" name="tq_course_sales_enabled" value="1"
                           <?php echo $cfg['enabled'] ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <div class="tqa-grid tqa-grid--2" style="margin-block-start:var(--tq-space-l)">
            <div class="tqa-field">
                <label class="tqa-field__label" for="cs-pct"><?php echo t('نصيب المعلم الافتراضي (٪)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="cs-pct" name="tq_course_teacher_percent"
                       type="number" min="0" max="100" step="0.01" dir="ltr"
                       value="<?php echo html_escape(rtrim(rtrim(number_format($cfg['percent'], 2, '.', ''), '0'), '.')); ?>">
                <span class="tqa-field__hint">
                    <?php echo t('يؤخذ لكل كورس لم يكتب له نصيب خاص — والباقي عمولة المنصة. ولا يخزن رقمان: نسبة المنصة مرآة محسوبة، ورقمان في عمودين يفترقان عند أول تعديل.'); ?>
                    <?php /* الفارق عن الباقة يقال هنا: القسمة في الباقة وعاء
                             على معلمين كثر، وهنا نسبة واحدة لمعلم واحد —
                             ومسؤول يظنهما شيئا واحدا يضبط أحدهما ويترك الآخر. */ ?>
                    <br><?php echo t('وهي غير «وعاء المعلمين» في الباقة: الباقة تفتح محتوى عدة معلمين فيقسم وعاؤها بينهم بعدد دروسهم، والكورس المفرد لمعلم واحد فتكفيه نسبة.'); ?>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="cs-days"><?php echo t('أجل الوصول الافتراضي (يوما)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="cs-days" name="tq_course_default_days"
                       type="number" min="0" max="3650" dir="ltr"
                       value="<?php echo (int) $cfg['default_days']; ?>">
                <span class="tqa-field__hint">
                    <?php echo t('يؤخذ للكورس الذي لم تحدد له «مدة وصول» في تبويب تسعيره. و'); ?><strong><?php echo t('صفر يعني وصولا دائما'); ?></strong> <?php echo t('— وهو ما يتوقعه من اشترى مادة بثمنها.'); ?>
                </span>
            </div>
        </div>

        <div class="tqa-actions">
            <button class="tqa-btn" type="submit"><?php echo tq_icon('check', 16); ?> <?php echo t('احفظ'); ?></button>
        </div>
    </form>
</section>
