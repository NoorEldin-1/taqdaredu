<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-BOOK — بيع الكتب المفردة.
 *
 * الكتاب على هذه المنصة كان **ملحقا مجانيا لا سلعة**: صف بعنوان وملف
 * PDF يحمل بلا تسجيل، بلا معلم ولا سعر ولا نصيب لأحد فيه. وهذه الشاشة
 * تفتح الباب، وتجيب أربعة أسئلة لم يكن في اللوحة موضع يجيب أيا منها:
 *
 *   · **أي الكتب تباع مفردة؟** — والقائمة تعرض ما علم للبيع **ولو لم
 *     يعرض**: كتاب علم وهو غير منشور أو بلا سعر أو بلا ملف يجلس ولا
 *     يظهر، وقائمة تسقطه تخفي عن المسؤول العطل الذي جاء يصلحه.
 *   · **بكم، ونصيب من؟** — والقسمة تعرض بالريال صفا بصف، من `offer()`
 *     نفسها التي تعرض للمشتري وتقيد في المحفظة.
 *   · **ماذا بيع؟** — وهو أول ما يسأل عنه من فتح الباب.
 *   · **وأيها بلا معلم؟** — كتاب المنصة ثمنه كله لها، وهو أكثر ما في
 *     القاعدة اليوم (كتب المنهج الرسمي). وخلطه بكتاب المعلم في عمود
 *     واحد يجعل «نصيب المعلم: ٠» يقرأ ظلما وهو ليس كذلك.
 *
 * والمفتاح العام في ذيلها لا في «إعدادات المنصة»: هذه هي الشاشة التي
 * يظهر فيها أثره. وهو مبدأ `tqa_course_sales` نفسه.
 */

$cfg    = isset($cfg) ? $cfg : array('enabled' => false, 'percent' => 60,
                                     'default_days' => 0, 'weight' => 3, 'direct_publish' => false);
$offers = isset($offers) ? $offers : array();
$sales  = isset($sales)  ? $sales  : array();
$rest   = isset($rest)   ? $rest   : array();
$stats  = isset($stats)  ? $stats  : array('sold' => 0, 'active' => 0, 'pending' => 0,
                                           'gross' => 0, 'listed' => 0, 'teachers' => 0);

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
    'ok'          => array(t('يباع الآن'),   'ok'),
    'disabled'    => array(t('الباب مغلق'),  'muted'),
    'not_marked'  => array(t('لم يعلن'),     'muted'),
    'unpublished' => array(t('غير منشور'),   'warn'),
    'unpriced'    => array(t('بلا سعر'),     'danger'),
    'no_file'     => array(t('بلا ملف'),     'danger'),
    'no_book'     => array(t('غير موجود'),   'muted'),
);

$sub_labels = array('pending' => t('بانتظار السداد'), 'active' => t('مفتوح'),
                    'cancelled' => t('ملغى'), 'expired' => t('منته'));
$sub_tones  = array('pending' => 'warn', 'active' => 'ok',
                    'cancelled' => 'muted', 'expired' => 'muted');

$open_n = 0;
foreach ($offers as $o) if (!empty($o['sellable'])) $open_n++;
?>

<?php tqa_head(t('بيع الكتب'),
    t('الكتب التي تباع مفردة خارج الباقات — بسعرها ونصيب صاحبها وما بيع منها.'),
    'book'); ?>

<?php if ($m = tq_flash('flash_message')): ?>
    <p class="tqa-note tqa-section"><span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        <span style="flex:1"><?php echo html_escape($m); ?></span></p>
<?php endif; ?>
<?php if ($m = tq_flash('error_message')): ?>
    <p class="tqa-note tqa-note--warn tqa-section"><span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span style="flex:1"><?php echo html_escape($m); ?></span></p>
<?php endif; ?>

<?php /* **الباب المغلق يقال في أول الشاشة لا في ذيلها.** المسؤول الذي
         يسعر كتابا ويعلمه ثم لا يجده في الموقع يبحث عن العطل في الكتاب،
         والعطل مفتاح في آخر الصفحة لم ينزل إليه. */ ?>
<?php if (!$cfg['enabled']): ?>
    <p class="tqa-note tqa-note--warn tqa-section">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span style="flex:1">
            <strong><?php echo t('باب بيع الكتب مغلق.'); ?></strong>
            <span style="display:block"><?php echo t('تعرض الكتب مجانية للتحميل كما كانت، ولا يظهر سعر في صفحة واحدة مهما علم كتاب أو سعر.'); ?></span>
        </span>
        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="#tqa-pricing"><?php echo t('افتح الباب'); ?></a>
    </p>
<?php endif; ?>

<?php /* سطر المال أولا: من يفتح هذه الشاشة يسأل «كم بيع» قبل أن يسأل عن
         كتاب بعينه، وقائمة ثلاثين صفا لا تجمع نفسها. */ ?>
<div class="tqa-grid tqa-grid--4" style="margin-block-end:var(--tq-space-xl)">
    <?php
    $tiles = array(
        array(t('محصل بيع الكتب'), $sar($stats['gross']),
              t('من بيعات فعلت — والمعلق لا يعد إيرادا'), 'wallet', 'tqa-mint'),
        array(t('بيعة مفردة'), '<span class="tqa-num">' . (int) $stats['active'] . '</span>',
              t('من ') . (int) $stats['sold'] . t(' طلبا'), 'chart', 'tqa-sky'),
        array(t('ينتظر الحوالة'), '<span class="tqa-num">' . (int) $stats['pending'] . '</span>',
              t('فاتورة صدرت ولم تسدد'), 'clock', 'tqa-peach'),
        array(t('معروض للبيع'), '<span class="tqa-num">' . $open_n . '</span>',
              t('من ') . count($offers) . t(' كتابا علم'), 'card', 'tqa-lilac'),
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
         الكتب المعلنة للبيع
         ==================================================================== */ ?>
<section class="tqa-card tqa-card--flush">
    <div class="tqa-card__head"><h2><?php echo tq_icon('book', 18); ?> <?php echo t('الكتب المعلنة للبيع'); ?></h2></div>

    <?php if (!$offers): ?>
        <?php tqa_empty(t('لم يعلن كتاب للبيع المفرد بعد'),
            t('أعلن كتابا من الجدول أدناه أو من شاشته. والإعلان وحده لا يكفي: الكتاب ')
          . t('يحتاج سعرا وملف PDF مرفوعا وأن يكون منشورا.'),
            t('الكتب'), site_url('taqdar_admin/module/books'), 'book'); ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr>
                    <th><?php echo t('الكتاب'); ?></th>
                    <th><?php echo t('الحال'); ?></th>
                    <th><?php echo t('القسمة على السعر الحالي'); ?></th>
                    <th><?php echo t('الوصول'); ?></th>
                    <th><?php echo t('في الباقات'); ?></th>
                    <th><?php echo t('السعر والنسبة'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($offers as $bid => $o):
                $st = isset($state[$o['reason']]) ? $state[$o['reason']] : array($o['reason'], 'muted');
                /* السعر في الحقل هو **سعر القائمة** لا المخصوم: هو ما يحرر،
                   والمخصوم حقل ثان بجواره. وخلطهما يجعل حفظا بلا تعديل
                   ينزل السعر إلى سعر الخصم في كل مرة. */
                $listed = ((int) $o['list_price'] > 0) ? (int) $o['list_price'] : (int) $o['price'];
                $own_pc = ((float) $o['percent'] === (float) $cfg['percent']) ? '' : $o['percent'];
            ?>
                <tr>
                    <td data-label="<?php echo te('الكتاب'); ?>">
                        <a href="<?php echo site_url('taqdar_admin/form/books/' . (int) $bid); ?>">
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

                    <?php /* **وكتاب المنصة يقال كتاب منصة لا «نصيب ٠».**
                             خلطه بكتاب المعلم في عمود واحد يجعل الصفر يقرأ
                             ظلما وهو قرار — ولا معلم أصلا يظلم. */ ?>
                    <td data-label="<?php echo te('القسمة'); ?>">
                        <?php if ((int) $o['price'] <= 0): ?>
                            <span class="tqa-dim"><?php echo t('لا سعر'); ?></span>
                        <?php elseif ((int) $o['teacher_id'] <= 0): ?>
                            <?php echo t('من '); ?> <?php echo $sar($o['price']); ?>
                            <br><span style="color:var(--tq-text2);font-size:12px"><?php
                                echo t('كتاب منصة — ثمنه كله للمنصة، ولا قيد في دفتر أحد.'); ?></span>
                        <?php else: ?>
                            <?php echo t('من '); ?> <?php echo $sar($o['price']); ?>
                            <br><span style="color:var(--tq-text2);font-size:12px">
                                <?php echo t('للمعلم ____ (____) · للمنصة ____', array($sar($o['share']), html_escape($pct($o['percent'])), $sar($o['platform']))); ?></span>
                            <?php if ((int) $o['list_price'] > 0): ?>
                                <br><span class="tqa-dim" style="font-size:12px"><?php echo t('بدل ____ — خصم ____٪', array(number_format($o['list_price'] / 100, 2), (int) $o['off'])); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>

                    <td data-label="<?php echo te('الوصول'); ?>"><?php echo (int) $o['days'] > 0
                        ? '<span class="tqa-num">' . (int) $o['days'] . t('</span> يوما')
                        : t('دائم'); ?></td>

                    <?php /* TQ-BOOK-GRADE — الصف هو الجسر الوحيد إلى الباقة،
                             وغيابه أكثر ما يسأل عنه معلم: «كتابي منشور ولا
                             يقرؤه مشترك». فيقال هنا صفا بصف. */ ?>
                    <td data-label="<?php echo te('في الباقات'); ?>">
                        <?php if (!empty($o['in_plans'])): ?>
                            <span class="tqa-badge tqa-badge--ok"><?php echo t('نعم'); ?></span>
                            <br><span class="tqa-dim" style="font-size:12px"><?php
                                echo t('وزنه '); ?><span class="tqa-num"><?php echo (int) $o['weight']; ?></span></span>
                        <?php else: ?>
                            <span class="tqa-dim" style="font-size:12px"><?php echo t('بلا صف — لا تفتحه باقة'); ?></span>
                        <?php endif; ?>
                    </td>

                    <?php /* التحرير في الصف لا في شاشة أخرى: من يفتح هذه
                             الشاشة يعدل أسعارا كثيرة في جلسة واحدة، والانتقال
                             إلى شاشة الكتاب لكل رقم يعني عشر صفحات لعشرة
                             أرقام. */ ?>
                    <td data-label="<?php echo te('السعر والنسبة'); ?>">
                        <form method="post" action="<?php echo site_url('taqdar_admin/book_sale_save'); ?>"
                              style="margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                            <?php echo tq_csrf(); ?>
                            <input type="hidden" name="book_id" value="<?php echo (int) $bid; ?>">

                            <label class="tqa-check" title="<?php echo te('يظهر بسعره في الموقع ويشترى'); ?>">
                                <input type="checkbox" name="tq_sell" value="1"
                                       <?php echo !empty($o['marked']) ? 'checked' : ''; ?>>
                                <span><?php echo t('يباع'); ?></span>
                            </label>

                            <label class="tqa-sr" for="pr-<?php echo (int) $bid; ?>"><?php echo t('سعر هذا الكتاب بالريال'); ?></label>
                            <input class="tqa-input tqa-input--ltr" id="pr-<?php echo (int) $bid; ?>"
                                   type="number" name="price_sar" min="0" step="0.01" dir="ltr"
                                   title="<?php echo te('السعر بالريال قبل الخصم'); ?>"
                                   style="min-block-size:34px;inline-size:88px"
                                   value="<?php echo html_escape($field_sar($listed)); ?>">

                            <label class="tqa-sr" for="pd-<?php echo (int) $bid; ?>"><?php echo t('السعر بعد الخصم بالريال'); ?></label>
                            <input class="tqa-input tqa-input--ltr" id="pd-<?php echo (int) $bid; ?>"
                                   type="number" name="discount_sar" min="0" step="0.01" dir="ltr"
                                   placeholder="<?php echo te('بعد الخصم'); ?>" title="<?php echo te('أقل من السعر أعلاه، وإلا أهمل'); ?>"
                                   style="min-block-size:34px;inline-size:88px"
                                   value="<?php echo (int) $o['list_price'] > 0
                                       ? html_escape($field_sar($o['price'])) : ''; ?>">

                            <?php if ((int) $o['teacher_id'] > 0): ?>
                                <label class="tqa-sr" for="pc-<?php echo (int) $bid; ?>"><?php echo t('نصيب المعلم بالمئة'); ?></label>
                                <?php /* الفارغ «خذ العام» والصفر «صفر بقرار» —
                                         و`placeholder` يقول ما يقع عند الفراغ. */ ?>
                                <input class="tqa-input tqa-input--ltr" id="pc-<?php echo (int) $bid; ?>"
                                       type="number" name="percent" min="0" max="100" step="0.01" dir="ltr"
                                       placeholder="<?php echo te('٪'); ?>" title="<?php echo te('نصيبه بالمئة — فارغ يعني النسبة العامة، وصفر يعني صفرا بقرار'); ?>"
                                       style="min-block-size:34px;inline-size:70px"
                                       value="<?php echo $own_pc === '' ? ''
                                           : html_escape(rtrim(rtrim(number_format((float) $own_pc, 2, '.', ''), '0'), '.')); ?>">
                            <?php endif; ?>

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
         كتب لم تعلن — يعلن منها الجديد بلا مغادرة الشاشة
         ==================================================================== */ ?>
<?php if ($rest): ?>
<section class="tqa-card tqa-card--flush" style="margin-block-start:var(--tq-space-xl)">
    <div class="tqa-card__head"><h2><?php echo tq_icon('plus', 18); ?> <?php echo t('كتب لم تعلن للبيع'); ?></h2></div>

    <p style="margin:0;padding:var(--tq-space-l) var(--tq-space-xl) 0;color:var(--tq-text2)">
        <?php echo t('هذه تحمل مجانا اليوم. وإعلان واحد منها للبيع'); ?> <strong><?php echo t('لا يخرجه من الباقة'); ?></strong> <?php echo t('— يبقى فيها ويباع مفردا كذلك: فمن اشترك في باقة صفه يقرؤه بها، ومن أراد هذا الكتاب وحده يشتريه بثمنه.'); ?>
    </p>

    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead><tr><th><?php echo t('الكتاب'); ?></th><th><?php echo t('صاحبه'); ?></th><th><?php echo t('الملف'); ?></th><th><?php echo t('أعلنه'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($rest as $r): ?>
            <tr>
                <td data-label="<?php echo te('الكتاب'); ?>">
                    <a href="<?php echo site_url('taqdar_admin/form/books/' . (int) $r['id']); ?>"><?php
                        echo html_escape($r['title']); ?></a>
                    <?php if (trim((string) $r['grade_name']) !== ''): ?>
                        <br><span class="tqa-dim" style="font-size:12px"><?php echo html_escape($r['grade_name']); ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="<?php echo te('صاحبه'); ?>"><?php echo trim((string) $r['teacher_name']) !== ''
                    ? html_escape($r['teacher_name'])
                    : t('<span class="tqa-dim">المنصة</span>'); ?></td>
                <td data-label="<?php echo te('الملف'); ?>"><?php echo trim((string) $r['file']) !== ''
                    ? t('<span class="tqa-badge tqa-badge--ok">مرفوع</span>')
                    : t('<span class="tqa-badge tqa-badge--danger">لا ملف</span>'); ?></td>
                <td data-label="<?php echo te('أعلنه'); ?>">
                    <form method="post" action="<?php echo site_url('taqdar_admin/book_sale_save'); ?>"
                          style="margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="book_id" value="<?php echo (int) $r['id']; ?>">
                        <input type="hidden" name="tq_sell" value="1">
                        <label class="tqa-sr" for="np-<?php echo (int) $r['id']; ?>"><?php echo t('سعر هذا الكتاب بالريال'); ?></label>
                        <input class="tqa-input tqa-input--ltr" id="np-<?php echo (int) $r['id']; ?>"
                               type="number" name="price_sar" min="0" step="0.01" dir="ltr"
                               placeholder="<?php echo te('السعر'); ?>" style="min-block-size:34px;inline-size:88px">
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
        <?php tqa_empty(t('لا بيعة كتاب بعد'),
            t('كل شراء كتاب منفرد يظهر هنا بمشتريه وفاتورته ونصيب صاحبه. وتفعيل الحوالة ')
          . t('البنكية يقع من شاشة «الاشتراكات»: البيعة اشتراك صفه في الجدول نفسه.'),
            t('الاشتراكات'), site_url('taqdar_admin/subscriptions'), 'refresh'); ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr><th>#</th><th><?php echo t('المشتري'); ?></th><th><?php echo t('الكتاب'); ?></th><th><?php echo t('صاحبه'); ?></th>
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
                    <td data-label="<?php echo te('الكتاب'); ?>">
                        <a href="<?php echo site_url('taqdar_admin/form/books/' . (int) $s['book_id']); ?>"><?php
                            echo html_escape($s['book_title']); ?></a></td>
                    <td data-label="<?php echo te('صاحبه'); ?>"><?php echo trim((string) $s['teacher_name']) !== ''
                        ? html_escape($s['teacher_name']) : t('المنصة'); ?></td>
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
        <?php echo t('هذه تحكم الباب كله.'); ?> <strong><?php echo t('ولا يعرض كتاب للبيع حتى يعلم «يباع مفردا» ويسعر'); ?></strong> <?php echo t('— فتح الباب وحده لا يعرض شيئا.'); ?>
        <strong><?php echo t('والسعر والنسبة والأجل تجمد على البيعة وقت الشراء'); ?></strong><?php echo t('، فتعديلها اليوم لا يغير ما بيع أمس ولا ما قيد في دفتر معلم.'); ?>
    </p>

    <form method="post" action="<?php echo site_url('taqdar_admin/book_sales_config'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <label class="tqa-prefrow__title" for="bs-on"><?php echo t('بيع الكتب المفردة'); ?></label>
                <span class="tqa-prefrow__hint"><?php echo t('مغلقا: تحمل الكتب مجانا كما كانت، ولا تتغير صفحة واحدة في الموقع عما هي عليه اليوم.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <span class="tqa-switch">
                    <input type="checkbox" id="bs-on" name="tq_book_sales_enabled" value="1"
                           <?php echo $cfg['enabled'] ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <?php /* ونشر المعلم مفتاح ثان في الشاشة نفسها: هو الذي يحكم
                 وصول كتاب المعلم إلى الطالب، ودفنه في «إعدادات المنصة»
                 يجعله مفتاحا لا يعرف أحد أنه موجود. */ ?>
        <div class="tqa-prefrow">
            <div class="tqa-prefrow__main">
                <label class="tqa-prefrow__title" for="bs-direct"><?php echo t('نشر المعلم لكتبه مباشرة'); ?></label>
                <span class="tqa-prefrow__hint"><?php echo t('مغلقا: ما يعلنه المعلم «منشورا» يصل إلى «مراجعة المحتوى» وينتظر قرارك — وهو الأصل.'); ?></span>
            </div>
            <div class="tqa-prefrow__end">
                <span class="tqa-switch">
                    <input type="checkbox" id="bs-direct" name="tq_book_direct_publish" value="1"
                           <?php echo !empty($cfg['direct_publish']) ? 'checked' : ''; ?>>
                    <span class="tqa-switch__track" aria-hidden="true"></span>
                </span>
            </div>
        </div>

        <div class="tqa-grid tqa-grid--3" style="margin-block-start:var(--tq-space-l)">
            <div class="tqa-field">
                <label class="tqa-field__label" for="bs-pct"><?php echo t('نصيب المعلم الافتراضي (٪)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="bs-pct" name="tq_book_teacher_percent"
                       type="number" min="0" max="100" step="0.01" dir="ltr"
                       value="<?php echo html_escape(rtrim(rtrim(number_format($cfg['percent'], 2, '.', ''), '0'), '.')); ?>">
                <span class="tqa-field__hint">
                    <?php echo t('من سعر الشراء المفرد وحده، لكل كتاب لم يكتب له نصيب خاص — والباقي عمولة المنصة.'); ?>
                    <br><?php echo t('وكتاب بلا معلم لا نصيب فيه: ثمنه كله للمنصة.'); ?>
                </span>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="bs-days"><?php echo t('أجل الوصول الافتراضي (يوما)'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="bs-days" name="tq_book_default_days"
                       type="number" min="0" max="3650" dir="ltr"
                       value="<?php echo (int) $cfg['default_days']; ?>">
                <span class="tqa-field__hint">
                    <?php echo t('يؤخذ للكتاب الذي لم يحدد له أجل. و'); ?><strong><?php echo t('صفر يعني وصولا دائما'); ?></strong> <?php echo t('— وهو ما يتوقعه من اشترى كتابا بثمنه.'); ?>
                </span>
            </div>

            <?php /* والوزن يوضح هنا لأنه أقل ما يفهم: نسبة الباقة **غير**
                     نسبة البيع المفرد، ومسؤول يظنهما شيئا واحدا يضبط
                     أحدهما ويترك الآخر. */ ?>
            <div class="tqa-field">
                <label class="tqa-field__label" for="bs-w"><?php echo t('وزن الكتاب في وعاء الباقة'); ?></label>
                <input class="tqa-input tqa-input--ltr" id="bs-w" name="tq_book_weight_lessons"
                       type="number" min="0" max="500" dir="ltr"
                       value="<?php echo (int) $cfg['weight']; ?>">
                <span class="tqa-field__hint">
                    <?php echo t('بمعادل الدروس: كتاب وزنه ٣ يحسب في وعاء الباقة كثلاثة دروس لصاحبه، إلى جانب دروس زملائه.'); ?>
                    <br><?php echo t('وهذه غير النسبة أعلاه: تلك من سعر بيعة مفردة لصاحب واحد، وهذه وزن في وعاء يقسم على معلمين كثر.'); ?>
                </span>
            </div>
        </div>

        <div class="tqa-actions">
            <button class="tqa-btn" type="submit"><?php echo tq_icon('check', 16); ?> <?php echo t('احفظ'); ?></button>
        </div>
    </form>
</section>
