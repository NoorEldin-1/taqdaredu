<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-BOOK — ما يبلغه هذا الكتاب: من يقرؤه، ومن يأخذ ثمنه.
 *
 * الكتاب ثلاثة أشياء في صف واحد: محتوى يقرأ، وسلعة تباع، ووزن في وعاء
 * باقة. وثلاثتها تقررها حقول متفرقة في النموذج — المرحلة والصف والسعر
 * والمعلم والوزن — **ولا شيء في الشاشة يجمعها في جواب**. فمسؤول يختار
 * صفا ولا يعرف أي باقة يفتحها به، ويكتب وزنا ولا يعرف ما يعنيه.
 *
 * وهذه اللوحة تجيب، كما تجيب `tqa_plan_reach` عن الباقة. والجلد جلدها
 * نفسه (`tqa-reach__*`): لوحة تجيب السؤال نفسه تلبس اللباس نفسه، وصنف
 * جديد لشيء قائم يعني ورقتين تفترقان عند أول تعديل.
 *
 * `$row` صف الكتاب — و`null` عند الإضافة، فتقرأ اللوحة صفرا وتقول
 * ما سيقع لا ما وقع.
 */
$M = &get_instance()->taqdar_admin_model;

$r     = is_array($row) ? $row : array();
$reach = $M->book_reach($r);

$gid   = (int) (isset($r['grade_id']) ? $r['grade_id'] : 0);
$sell  = ((int) (isset($r['tq_sell']) ? $r['tq_sell'] : 0) === 1);
$tid   = (int) (isset($r['teacher_id']) ? $r['teacher_id'] : 0);
$price = (int) (isset($r['price']) ? $r['price'] : 0);
$disc  = (int) (isset($r['discount_price']) ? $r['discount_price'] : 0);
$net   = ($disc > 0 && $disc < $price) ? $disc : $price;
$share = $tid > 0 ? (int) $reach['share'] : 0;

/* الريال في سطر واحد: `number_format` على الهللات هنا لا في ثلاثة
   مواضع تفترق في عدد الكسور. */
$sar = function ($h) { return number_format(((int) $h) / 100, 2); };
?>

<div class="tqa-card tqa-reach" style="margin-block-end:var(--tq-space-l)">

    <div class="tqa-card__head">
        <div>
            <h2 class="tqa-reach__title"><?php echo t('ما يبلغه هذا الكتاب'); ?></h2>
            <p class="tqa-reach__lead">
                <?php echo t('محسوب من الحقول أعلاه — ويتحدث بعد الحفظ.'); ?>
            </p>
        </div>
    </div>

    <div class="tqa-card__body">

        <?php /* ── الأرقام الأربعة ────────────────────────────────── */ ?>
        <div class="tqa-reach__nums">
            <div class="tqa-reach__num">
                <b><?php echo (int) count($reach['plans']); ?></b>
                <span><?php echo t('باقة تفتحه'); ?></span>
            </div>
            <div class="tqa-reach__num">
                <b><?php echo (int) $reach['subs']; ?></b>
                <span><?php echo t('مشتركا يقرؤه'); ?></span>
            </div>
            <div class="tqa-reach__num">
                <b><?php echo (int) $reach['sold']; ?></b>
                <span><?php echo t('بيع مفردا'); ?></span>
            </div>
            <div class="tqa-reach__num">
                <b><?php echo (int) $reach['weight']; ?></b>
                <span><?php echo t('وزنه بالدروس'); ?></span>
            </div>
        </div>

        <?php /* ── ١ · الباقات ───────────────────────────────────────
                 TQ-BOOK-GRADE: الصف هو الجسر الوحيد. والصمت هنا هو ما
                 يجعل مسؤولا يرفع كتابا بمرحلة بلا صف ويظنه في باقة
                 المرحلة — وهو ليس في باقة واحدة. */ ?>
        <div class="tqa-reach__rows">
            <?php if ($gid <= 0): ?>
                <div class="tqa-reach__row">
                    <b><?php echo t('في الباقات'); ?></b>
                    <span class="tqa-warn">
                        <?php echo t('بلا صف — لا تفتحه باقة واحدة. المرحلة ترشحه في الكتالوج، والصف وحده يدخله في الباقة.'); ?>
                    </span>
                </div>
            <?php elseif (!$reach['plans']): ?>
                <div class="tqa-reach__row">
                    <b><?php echo html_escape($reach['grade']); ?></b>
                    <span class="tqa-warn">
                        <?php echo t('لا باقة تشمل هذا الصف بعد — فالكتاب ينشر ولا يفتحه اشتراك.'); ?>
                    </span>
                </div>
            <?php else: ?>
                <?php foreach ($reach['plans'] as $p): ?>
                    <div class="tqa-reach__row">
                        <b><?php echo html_escape($p['name_ar']); ?></b>
                        <span>
                            <?php if ((int) $p['active'] === 1): ?>
                                <?php echo t('تفتحه لمشتركيها'); ?>
                            <?php else: ?>
                                <?php echo t('موقوفة — لا تفتح شيئا حتى تفعل'); ?>
                            <?php endif; ?>
                            ·
                            <a href="<?php echo site_url('taqdar_admin/form/plans/' . (int) $p['id']); ?>"><?php
                                echo t('افتح الباقة'); ?></a>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php /* ── ٢ · الوزن في الوعاء ─────────────────────────
                     الباقة تباع مرة وتفتح محتوى معلمين كثر، فالدفعة
                     واحدة والمستحقون كثر — والوعاء يقسم بالوزن. */ ?>
            <div class="tqa-reach__row">
                <b><?php echo t('الوزن في وعاء الباقة'); ?></b>
                <?php if ($tid <= 0): ?>
                    <span><?php echo t('كتاب منصة بلا معلم: لا وزن ولا نصيب — والوعاء يقسم على المعلمين الآخرين وحدهم.'); ?></span>
                <?php elseif ($gid <= 0): ?>
                    <span><?php echo t('يحسب كـ'); ?> <?php echo tq_num((int) $reach['weight']); ?>
                          <?php echo t('دروس لصاحبه — ولا يدخل قسمة واحدة ما دام بلا صف.'); ?></span>
                <?php else: ?>
                    <span><?php echo t('يحسب كـ'); ?> <?php echo tq_num((int) $reach['weight']); ?>
                          <?php echo t('دروس لصاحبه في وعاء كل باقة تشمل صفه. ولوحة «قسمة الإيراد» في شاشة الباقة تريك نصيبه بالريال.'); ?></span>
                <?php endif; ?>
            </div>

            <?php /* ── ٣ · البيع المفرد ─────────────────────────────
                     والنسبة هنا **غير** نسبة الباقة: هذه لصاحب واحد من
                     سعر واحد، وتلك وزن في وعاء يقسم على كثر. وخلطهما هو
                     ما يجعل معلما يقرأ رقمين ولا يعرف أيهما نصيبه. */ ?>
            <div class="tqa-reach__row">
                <b><?php echo t('البيع المفرد'); ?></b>
                <?php if (!$sell): ?>
                    <span><?php echo t('لا يباع — يحمل مجانا من صفحة الكتاب بلا تسجيل، كما كانت كتب المنصة كلها.'); ?></span>
                <?php elseif ($net <= 0): ?>
                    <span class="tqa-warn"><?php echo t('أعلن للبيع ولم يسعر — والشراء يرد بخطأ.'); ?></span>
                <?php else: ?>
                    <span>
                        <?php echo t('المحصل'); ?> <span class="tq-ltr"><?php echo $sar($net); ?></span> <?php echo t('ر.س'); ?>
                        ·
                        <?php if ($tid > 0): ?>
                            <?php echo t('للمعلم'); ?> <span class="tq-ltr"><?php echo $sar($share); ?></span> <?php echo t('ر.س'); ?>
                            (<span class="tq-ltr"><?php echo rtrim(rtrim(number_format((float) $reach['percent'], 2), '0'), '.'); ?>%</span>)
                            ·
                            <?php echo t('للمنصة'); ?> <span class="tq-ltr"><?php echo $sar($net - $share); ?></span> <?php echo t('ر.س'); ?>
                        <?php else: ?>
                            <?php echo t('بلا معلم، فثمنه كله للمنصة ولا قيد في دفتر أحد.'); ?>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ── الحال كما يقرؤه عمود «الظهور» في القائمة ────────
                 مصدر واحد للجملة: `book_visibility()` نفسها التي تطبع
                 هناك. ونسختان تفترقان فيقرأ المسؤول حكمين على صف واحد. */ ?>
        <?php if ($rid > 0 && $row): ?>
            <?php $vis = $M->book_visibility($row); ?>
            <div class="tqa-reach__vis tqa-reach__vis--<?php echo html_escape($vis['tone']); ?>">
                <b><?php echo html_escape($vis['label']); ?></b>
                <span><?php echo html_escape($vis['why']); ?></span>
            </div>
        <?php endif; ?>

    </div>
</div>
