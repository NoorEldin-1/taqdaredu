<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * خريطة الإتقان.
 *
 * `attempts` و`answers` و`skill_state` و`review_queue` قلب المنتج: كل
 * إجابة طالب تكتب فيها، وعليها يبنى جدول المراجعة والتقرير الأسبوعي.
 * ولم تكن في اللوحة نافذة واحدة عليها.
 *
 * وأنفع ما فيها جدول «أصعب الأهداف»: الهدف الذي يسقط فيه أكثر من مر به
 * ليس مشكلة طالب — إنما شرح ناقص أو سؤال ملتبس. وهو رقم يقرؤه محرر
 * المنهج فيعرف أي درس يعاد تسجيله، وكان محجوبا تماما.
 */
$rate = (int) $summary['answers'] > 0
    ? (int) round(((int) $summary['correct'] * 100) / (int) $summary['answers'])
    : 0;
$mastered_pct = (int) $summary['skills'] > 0
    ? (int) round(((int) $summary['mastered'] * 100) / (int) $summary['skills'])
    : 0;
?>

<?php tqa_head(t('خريطة الإتقان'), t('ما يتقنه الطلاب وما يتعثرون فيه — وأي هدف يحتاج إعادة شرح.'), 'crosshair'); ?>

<div class="tqa-grid tqa-grid--4" style="margin-block-end:var(--tq-space-xl)">

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('نسبة الإجابات الصحيحة'); ?></span>
            <span class="tqa-stat__icon tqa-mint" aria-hidden="true"><?php echo tq_icon('check', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><span class="tqa-num"><?php echo $rate; ?></span>%</span>
        <span class="tqa-stat__hint">
            <?php echo t('من'); ?> <span class="tqa-num"><?php echo (int) $summary['answers']; ?></span> <?php echo t('إجابة'); ?>
        </span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('مهارات أتقنت'); ?></span>
            <span class="tqa-stat__icon tqa-sky" aria-hidden="true"><?php echo tq_icon('target', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><span class="tqa-num"><?php echo $mastered_pct; ?></span>%</span>
        <span class="tqa-stat__hint">
            <span class="tqa-num"><?php echo (int) $summary['mastered']; ?></span>
            <?php echo t('من'); ?> <span class="tqa-num"><?php echo (int) $summary['skills']; ?></span> <?php echo t('حالة مهارة بلغت ٨٠ فأكثر'); ?>
        </span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('مراجعات مستحقة الآن'); ?></span>
            <span class="tqa-stat__icon tqa-peach" aria-hidden="true"><?php echo tq_icon('flame', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $summary['due_reviews']; ?></span>
        <span class="tqa-stat__hint"><?php echo t('سؤال حل موعد مراجعته'); ?></span>
    </div>

    <div class="tqa-stat">
        <div class="tqa-stat__top">
            <span class="tqa-stat__label"><?php echo t('طلاب يتدربون'); ?></span>
            <span class="tqa-stat__icon tqa-lilac" aria-hidden="true"><?php echo tq_icon('users', 18); ?></span>
        </div>
        <span class="tqa-stat__value"><?php echo (int) $summary['learners']; ?></span>
        <span class="tqa-stat__hint">
            <?php echo t('سلموا'); ?> <span class="tqa-num"><?php echo (int) $summary['attempts']; ?></span> <?php echo t('محاولة تقييم'); ?>
        </span>
    </div>
</div>

<section class="tqa-card tqa-card--flush" style="margin-block-end:var(--tq-space-xl)">
    <div class="tqa-card__head">
        <h2><?php echo t('أصعب الأهداف'); ?></h2>
        <span class="tqa-badge tqa-badge--muted"><?php echo t('خمس إجابات فأكثر'); ?></span>
    </div>

    <?php if (!$hardest): ?>
        <?php tqa_empty(t('لا بيانات كافية بعد'),
            t('الجدول يظهر الأهداف التي أجيب عليها خمس مرات فأكثر — أقل من ذلك عينة لا يقال عنها شيء. ويحتاج أن تكون الأسئلة مربوطة بأهدافها أولا.'),
            t('اربط الأسئلة بالأهداف'), site_url('taqdar_admin/bindings'), 'crosshair'); ?>
    <?php else: ?>
        <p style="padding:var(--tq-space-l) var(--tq-space-xl) 0;margin:0;font:var(--tq-type-caption);color:var(--tq-text2)">
            <?php echo t('الهدف الذي يسقط فيه أكثر من مر به ليس مشكلة طالب — إنما شرح ناقص أو سؤال ملتبس. ابدأ من أعلى القائمة.'); ?>
        </p>
        <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr>
                    <th><?php echo t('الهدف'); ?></th>
                    <th><?php echo t('الدرس'); ?></th>
                    <th><?php echo t('نسبة الصواب'); ?></th>
                    <th><?php echo t('الإجابات'); ?></th>
                    <th><?php echo t('الطلاب'); ?></th>
                    <th><span class="tqa-sr"><?php echo t('تحرير'); ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($hardest as $o):
                $tries = (int) $o['tries'];
                $hits  = (int) $o['hits'];
                $p     = $tries > 0 ? (int) round(($hits * 100) / $tries) : 0;
                $tone  = $p < 40 ? 'danger' : ($p < 65 ? 'warn' : 'ok');
            ?>
                <tr>
                    <td data-label="<?php echo te('الهدف'); ?>">
                        <?php echo html_escape(mb_strimwidth((string) $o['text'], 0, 70, '…', 'UTF-8')); ?>
                    </td>
                    <td data-label="<?php echo te('الدرس'); ?>">
                        <?php echo html_escape($o['lesson_title'] ?: '—'); ?>
                        <?php if (!empty($o['course_title'])): ?>
                            <br><span style="color:var(--tq-text2);font-size:12px">
                                <?php echo html_escape($o['course_title']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php echo te('نسبة الصواب'); ?>">
                        <span class="tqa-badge tqa-badge--<?php echo $tone; ?>">
                            <span class="tqa-num"><?php echo $p; ?>%</span>
                        </span>
                        <div class="tqa-bar" style="margin-block-start:6px;inline-size:90px">
                            <div class="tqa-bar__fill" style="inline-size:<?php echo $p; ?>%;
                                 background:var(--tq-<?php echo $tone === 'danger' ? 'actionDanger' : ($tone === 'warn' ? 'amber' : 'actionMastery'); ?>)"></div>
                        </div>
                    </td>
                    <td data-label="<?php echo te('الإجابات'); ?>"><span class="tqa-num"><?php echo $tries; ?></span></td>
                    <td data-label="<?php echo te('الطلاب'); ?>"><span class="tqa-num"><?php echo (int) $o['learners']; ?></span></td>
                    <td data-label="<?php echo te('تحرير'); ?>">
                        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                           href="<?php echo site_url('taqdar_admin/form/objectives/' . (int) $o['id']); ?>"><?php echo t('حرر'); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<section class="tqa-card tqa-card--flush">
    <div class="tqa-card__head"><h2><?php echo t('متوسط الإتقان في كل مسار'); ?></h2></div>

    <?php if (!$by_path): ?>
        <?php tqa_empty(t('لا مسار فيه حالات مهارة بعد'),
            t('يظهر المسار هنا حين يبدأ طلابه في الإجابة على أسئلة مربوطة بأهداف دروسه.'),
            t('المسارات'), site_url('taqdar_admin/module/paths'), 'target'); ?>
    <?php else: ?>
        <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr><th><?php echo t('المسار'); ?></th><th><?php echo t('الطلاب'); ?></th><th><?php echo t('متوسط الإتقان'); ?></th></tr>
            </thead>
            <tbody>
            <?php foreach ($by_path as $p):
                $lv   = (int) $p['avg_level'];
                $tone = $lv < 40 ? 'danger' : ($lv < 70 ? 'warn' : 'ok');
            ?>
                <tr>
                    <td data-label="<?php echo te('المسار'); ?>">
                        <a href="<?php echo site_url('taqdar_admin/form/paths/' . (int) $p['id']); ?>">
                            <?php echo html_escape($p['title']); ?>
                        </a>
                    </td>
                    <td data-label="<?php echo te('الطلاب'); ?>"><span class="tqa-num"><?php echo (int) $p['learners']; ?></span></td>
                    <td data-label="<?php echo te('متوسط الإتقان'); ?>">
                        <span class="tqa-badge tqa-badge--<?php echo $tone; ?>">
                            <span class="tqa-num"><?php echo $lv; ?></span> / 100
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
