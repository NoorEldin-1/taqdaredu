<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * اختبار تحديد المستوى — أول ما يواجه الطالب الجديد.
 *
 * ═══ لماذا وجدت هذه الشاشة ═══
 *
 * كان الطالب ينشئ حسابه فيقف أمام ثلاثة أسماء وثلاثة أسعار، ولا شيء
 * يقول له أيها يناسبه. فيختار بالسعر — وهو أسوأ مرشد: الاقل ثمنا قد يكون
 * فوق مستواه بسنة، والاعلى دونه بسنة. والنتيجة تظهر بعد الدفع لا قبله.
 *
 * ═══ ثلاث حالات في صفحة واحدة ═══
 *
 * تمهيد · اسئلة · نتيجة. والحالة تشتق في المتحكم من القاعدة لا من معامل
 * في الرابط: ولو كانت في الرابط لفتحت شاشة النتيجة بلا نتيجة، وشاشة
 * الاسئلة بعد التسليم.
 *
 * ═══ ما لا يوجد هنا ═══
 *
 * لا `correct_answers` ولا شيء يشتق منها. التصحيح في الخادم وحده
 * (`Taqdar_diag_model::submit()`)، وصفحة تحمل الاجابات تحملها الى متصفح
 * الطالب — ومن فتح ادوات المطور قرأها.
 *
 * ولا عبارة «رسوب» ولا «فشل» في اي حالة: النتيجة موضع لا حكم، وثلاثتها
 * صالحة ولكل منها باقتها. والطالب الذي يقرأ «اختبار» يكتب بيد مرتجفة،
 * والقياس تحت الخوف قياس لشيء اخر.
 */

$state = isset($tq_state) ? $tq_state : 'intro';
$exam  = $tq_exam;
$LV    = $tq_levels;

$tq_nav   = 'placement';
$tq_role  = 'student';
$tq_title = 'اين موضعك؟';
$tq_sub   = 'اختبار قصير يحدد ما يناسبك، ثم نرشح لك الباقة التي تبدا منها.';
$tq_icon  = 'crosshair';

include 'portal_open.php';
?>

<style>
/* موضعي لهذه الشاشة، وكل قيمة من التوكنات — لا لون ولا مسافة مباشرة. */
.tqp-q       { padding: var(--tq-space-l); border: 1px solid var(--tq-line);
               border-radius: var(--tq-radius-medium); background: var(--tq-surface);
               margin-block-end: var(--tq-space-l); }
.tqp-q__n    { font: var(--tq-type-micro); color: var(--tq-text3); }
.tqp-q__t    { margin: var(--tq-space-xs) 0 var(--tq-space-m); font: var(--tq-type-bodyStrong); color: var(--tq-text); }
.tqp-opt     { display: block; padding: var(--tq-space-s) var(--tq-space-m); margin-block-end: var(--tq-space-s);
               border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
               cursor: pointer; transition: border-color .15s ease, background .15s ease; }
.tqp-opt:hover        { border-color: var(--tq-teal); }
.tqp-opt input        { margin-inline-end: var(--tq-space-s); accent-color: var(--tq-teal); }
.tqp-opt:has(input:checked) { border-color: var(--tq-teal); background: var(--tq-mint-fill); }
.tqp-steps   { display: grid; gap: var(--tq-space-m); margin: var(--tq-space-l) 0; padding: 0; list-style: none; }
.tqp-steps li{ display: flex; gap: var(--tq-space-m); align-items: flex-start; }
.tqp-steps b { display: grid; place-items: center; inline-size: 28px; block-size: 28px; flex: 0 0 auto;
               border-radius: 50%; background: var(--tq-mint-fill); color: var(--tq-mint-ink);
               font: var(--tq-type-micro); }
.tqp-scale   { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--tq-space-s);
               margin: var(--tq-space-l) 0; }
.tqp-scale__i{ padding: var(--tq-space-m); border-radius: var(--tq-radius-small);
               border: 1px solid var(--tq-line); text-align: center; opacity: .5; }
.tqp-scale__i.is-mine { opacity: 1; border-color: var(--tq-teal); background: var(--tq-mint-fill); }
.tqp-scale__i b { display: block; font: var(--tq-type-bodyStrong); color: var(--tq-text); }
.tqp-bar     { margin-block-start: var(--tq-space-s); }
@media (max-width: 560px) { .tqp-scale { grid-template-columns: minmax(0, 1fr); } }
</style>

<div class="tq-stack">

<?php if ($state === 'intro'): ?>

    <?php /* ── تمهيد: ما هو، وكم يستغرق، وماذا يحدث بعده ────────────── */ ?>
    <div class="tq-card tq-card--panel">
        <span class="tq-eyebrow">قبل ان تختار باقتك</span>
        <h2 class="tq-card__title"><?php echo html_escape($exam['title']); ?></h2>

        <p><?php echo nl2br(html_escape(
            trim((string) $exam['intro']) !== ''
                ? $exam['intro']
                : 'اسئلة اختيار من متعدد تقيس اين انت الان في منهج صفك. ولا رسوب فيه: '
                . 'كل نتيجة لها باقتها، والغرض ان تبدا من حيث انت لا من حيث يفترض غيرك.'
        )); ?></p>

        <?php
        $tq_n = 0;
        $CI_p = &get_instance();
        $CI_p->load->model('taqdar_diag_model');
        foreach ($CI_p->taqdar_diag_model->level_tally((int) $exam['id']) as $tq_c) $tq_n += $tq_c;
        ?>
        <ol class="tqp-steps">
            <li><b>1</b><span>
                <?php echo tq_count_units($tq_n, 'سؤال', 'سؤالان', 'سؤالين', 'اسئلة', 'سؤالا'); ?>
                من اختيار من متعدد
                <?php if ((int) $exam['time_limit_sec'] > 0): ?>
                    — والوقت <?php echo (int) ceil($exam['time_limit_sec'] / 60); ?> دقيقة.
                <?php else: ?>
                    — بلا حد زمني، فخذ وقتك.
                <?php endif; ?>
            </span></li>
            <li><b>2</b><span>تظهر نتيجتك فورا: مبتدئ او متوسط او متقدم.</span></li>
            <li><b>3</b><span>نرشح لك الباقة التي تقابل نتيجتك — ويبقى لك ان تختار غيرها.</span></li>
        </ol>

        <?php if ((int) $exam['allow_retake'] !== 1): ?>
            <p class="tq-caption">
                <?php echo tq_icon('alert', 15); ?>
                الاختبار مرة واحدة، فاجب بما تعرفه فعلا — النتيجة ترشدك انت.
            </p>
        <?php endif; ?>

        <form method="post" action="<?php echo base_url('student/placement/start'); ?>" style="margin-block-start:var(--tq-space-l)">
            <?php echo tq_csrf(); ?>
            <button type="submit" class="tq-btn tq-btn--primary">ابدا الاختبار</button>
        </form>
    </div>

    <?php /* الطالب الذي وضع في صف غير صفه يحتاج بابا يخرج منه: حبسه في
             شاشة بلا مخرج يجعل الخطا الاداري سجنا. */ ?>
    <p class="tq-caption">
        صفك المسجل غير صحيح؟
        <a href="<?php echo base_url('contact'); ?>">تواصل معنا</a> لتصحيحه قبل ان تبدا.
    </p>

<?php elseif ($state === 'exam'): ?>

    <?php /* ── الاسئلة ─────────────────────────────────────────────── */ ?>
    <form method="post" action="<?php echo base_url('student/placement/submit'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tq-card tq-card--panel">
            <h2 class="tq-card__title"><?php echo html_escape($exam['title']); ?></h2>
            <p class="tq-caption">
                اجب عما تعرفه، وما لا تعرفه اتركه — ترك السؤال اصدق من التخمين،
                والنتيجة ترشدك انت لا تحاسبك.
            </p>
        </div>

        <?php $i = 0; foreach ($tq_questions as $q): $i++; ?>
            <div class="tqp-q">
                <span class="tqp-q__n">السؤال <span class="tq-ltr"><?php echo $i; ?></span>
                    من <span class="tq-ltr"><?php echo count($tq_questions); ?></span></span>
                <p class="tqp-q__t"><?php echo html_escape($q['title']); ?></p>
                <?php /* TQ-QIMG · المعادلة والرسم البياني لا يكتبان حروفا،
                         فما رفع مع السؤال يعرض تحت نصه لا بدلا منه. */ ?>
                <?php echo tq_qimage_tag($q['image'] ?? '', 'tq-qimg'); ?>

                <?php /* المستوى لا يعرض للطالب عمدا: من قرا «متقدم» فوق سؤال
                         حكم على نفسه قبل ان يقرا السؤال، ومن قرا «مبتدئ»
                         استهان به. والوسم اداة قياس لا اعلان. */ ?>
                <?php foreach ($q['options'] as $oi => $o): ?>
                    <label class="tqp-opt">
                        <input type="radio"
                               name="answers[<?php echo (int) $q['id']; ?>]"
                               value="<?php echo html_escape($o); ?>">
                        <?php echo html_escape($o); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="tq-card tq-card--panel">
            <button type="submit" class="tq-btn tq-btn--primary">سلم الاختبار واعرض نتيجتي</button>
            <p class="tq-caption" style="margin-block-start:var(--tq-space-s)">
                تصحح اجاباتك عندنا وتظهر نتيجتك في الشاشة التالية.
            </p>
        </div>
    </form>

<?php else: ?>

    <?php
    /* ── النتيجة ─────────────────────────────────────────────────── */
    $lv    = (string) $tq_attempt['result_level'];
    $meta  = isset($LV[$lv]) ? $LV[$lv] : array('label' => $lv, 'lead' => '');
    $score = (int) $tq_attempt['score'];
    $total = max(1, (int) $tq_attempt['total']);
    $pct   = (int) round(($score * 100) / $total);
    ?>

    <div class="tq-card tq-card--panel">
        <span class="tq-eyebrow">نتيجتك</span>
        <h2 class="tq-card__title">موضعك الان: <?php echo html_escape($meta['label']); ?></h2>
        <p><?php echo html_escape($meta['lead']); ?></p>

        <?php /* الترتيب الصاعد يقرا مسارا لا ثلاث خانات: الطالب يرى اين هو
                 واين يمكن ان يصل — وهو ما يجعل النتيجة بداية لا حكما. */ ?>
        <div class="tqp-scale">
            <?php foreach ($LV as $k => $m): ?>
                <div class="tqp-scale__i<?php echo ($k === $lv) ? ' is-mine' : ''; ?>">
                    <b><?php echo html_escape($m['label']); ?></b>
                    <?php if ($k === $lv): ?><span class="tq-caption">انت هنا</span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="tq-caption">
            اجبت <span class="tq-ltr"><?php echo $score; ?></span>
            من <span class="tq-ltr"><?php echo (int) $tq_attempt['total']; ?></span> اجابة صحيحة.
        </p>
        <div class="tq-progress tqp-bar">
            <div class="tq-progress__track">
                <div class="tq-progress__fill" style="inline-size:<?php echo $pct; ?>%"></div>
            </div>
        </div>
    </div>

    <?php if ($tq_plan): ?>
        <?php
        $tq_code  = (string) $tq_plan['code'];
        $tq_feats = json_decode((string) $tq_plan['features'], true);
        $tq_feats = is_array($tq_feats) ? array_slice($tq_feats, 0, 4) : array();
        ?>
        <div class="tq-card tq-card--panel">
            <span class="tq-eyebrow">الباقة التي نرشحها لك</span>
            <h2 class="tq-card__title"><?php echo html_escape($tq_plan['name_ar']); ?></h2>

            <?php if (trim((string) $tq_plan['note']) !== ''): ?>
                <p><?php echo html_escape($tq_plan['note']); ?></p>
            <?php endif; ?>

            <p class="tq-num tq-num--xl"><?php echo tqs_money((int) $tq_plan['price']); ?></p>
            <p class="tq-caption"><?php echo html_escape(tqs_period_label((int) $tq_plan['duration_days'])); ?></p>

            <?php if ($tq_feats): ?>
                <ul class="tqp-steps" style="margin-block-start:var(--tq-space-l)">
                    <?php foreach ($tq_feats as $f): ?>
                        <li><b><?php echo tq_icon('check', 14); ?></b><span><?php echo html_escape($f); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="tq-row" style="gap:var(--tq-space-m);margin-block-start:var(--tq-space-l)">
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plan/' . $tq_code); ?>">
                    اطلع على تفاصيلها
                </a>
                <a class="tq-btn tq-btn--ghost" href="<?php echo base_url('plans'); ?>">قارن بالباقات الاخرى</a>
            </div>

            <?php /* التوصية ترشد ولا تلزم — يقال صراحة لا بالسكوت. والاداة
                     التي تقيس لا ينبغي ان تكون هي الاداة التي تبيع. */ ?>
            <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                هذه توصية لا الزام: كل الباقات مفتوحة لك، واختر ما تراه انسب.
            </p>

            <?php /* زر الاعادة يظهر بشرط الاختبار وحده.
                     ومفتاح «يسمح بالاعادة» في اللوحة كان اعدادا بلا باب:
                     يقبله النموذج ولا موضع في الشاشة يفتحه — فيعلمه
                     المسؤول ولا يقع شيء. */ ?>
            <?php if ((int) $exam['allow_retake'] === 1): ?>
                <form method="post" action="<?php echo base_url('student/placement/start'); ?>"
                      style="margin-block-start:var(--tq-space-m)">
                    <?php echo tq_csrf(); ?>
                    <button type="submit" class="tq-btn tq-btn--ghost tq-btn--sm">اعد الاختبار</button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php /* نتيجة بلا باقة مربوطة: عطل اداري لا يقال للطالب بلغته.
                 يدل على الباقات كلها بدل شاشة تنتهي عند لا شيء. */ ?>
        <div class="tq-card tq-card--panel">
            <div class="tq-empty">
                <span class="tq-icon-box tq-pastel--sky" aria-hidden="true"><?php echo tq_icon('card', 26); ?></span>
                <p class="tq-empty__title">اطلع على الباقات</p>
                <p class="tq-empty__text">اخترنا لك موضعك، وباقات صفك معروضة كلها — تصفحها واختر ما يناسبك.</p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>">عرض الباقات</a>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

</div>

<?php include 'portal_close.php'; ?>
