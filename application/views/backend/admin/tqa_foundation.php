<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-FOUNDATION — قسم التأسيس في اللوحة.
 *
 * الشاشة تجيب سؤالا واحدا: **أيعمل القسم فعلا؟** — وهو غير سؤال «ما
 * المسارات؟» الذي تجيبه الوحدة الموصوفة (`module/foundation_tracks`).
 *
 * ومسار لا يراه طالب واحد لا يراه لثلاثة اسباب تقود الى ثلاثة افعال:
 *
 *   معطل                     ⇐ يفعل من شاشة المسار
 *   لا معلم اسند اليه        ⇐ يسند من حقل «معلمو المسار»
 *   اسند ولم يفتح وقتا       ⇐ يذكر المعلم، والوقت يفتح من بوابته وحدها
 *
 * والثلاثة تقرأ «صفر مواعيد» في شاشة لا تفرق بينها، فيقلب المسؤول في
 * القاعدة او يظن النظام معطلا. فالعمود الاخير يسمي السبب بعينه.
 *
 * ولا يفتح المسؤول وقتا نيابة عن المعلم: الوقت وقته، ومن يفتحه عنه يحجز
 * ساعة قد لا تكون فارغة — والشكوى تصل بعد ان ينعقد الموعد لا قبله.
 */
$tq_tracks  = isset($tracks) ? $tracks : array();
$tq_fnd     = isset($fnd) ? $fnd : null;
$tq_cfg     = isset($cfg) ? $cfg : array('price' => 0, 'minutes' => 60);
$tq_wins    = isset($windows) ? $windows : array();
$tq_ses     = isset($ses) ? $ses : null;

$tq_live    = 0;   // مسارات يراها الطالب فعلا
$tq_noteach = 0;   // منشورة بلا معلم مسند
$tq_notime  = 0;   // لها معلم ولا وقت مفتوح
$tq_slots   = 0;
$tq_gross   = 0;

foreach ($tq_tracks as $t) {
    $tq_slots += (int) $t['open_slots'];
    $tq_gross += (int) $t['gross'];
    if (!$t['active']) continue;
    if ((int) $t['teachers'] === 0)      { $tq_noteach++; continue; }
    if ((int) $t['open_slots'] === 0)    { $tq_notime++;  continue; }
    $tq_live++;
}

/** حال المسار في كلمة — والسبب لا الرقم. */
$tq_state = function ($t) {
    if (!$t['active'])                return array('muted',  t('معطل — لا يظهر لأحد'));
    if ((int) $t['teachers'] === 0)   return array('danger', t('لا معلم مسند إليه'));
    if ((int) $t['open_slots'] === 0) return array('warn',   t('لا وقت مفتوح'));
    return array('ok', t('يظهر للطلاب'));
};

$tq_sar = function ($h) {
    return '<span class="tqa-num">' . number_format(((int) $h) / 100, 2) . '</span> ' . t('ر.س');
};
?>

<?php tqa_head(
    t('قسم التأسيس'),
    t('حصص مباشرة بلا صف ولا منهج — قسم مستقل عن الباقات والمسارات.'),
    'graduation',
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('taqdar_admin/module/foundation_tracks') . '">'
        . tq_icon('plus', 16) . ' ' . te('حرر المسارات') . '</a>'
); ?>

<div class="tqa-grid tqa-grid--4" style="margin-block-end:var(--tq-space-xl)">
    <?php echo tqa_stat(t('مسارات يراها الطالب'), $tq_live, array(
        'icon' => $tq_live > 0 ? 'check' : 'alert',
        'tone' => $tq_live > 0 ? 'ok' : 'danger',
        'hint' => t('متاحة ولها معلم فتح وقتا'),
    )); ?>

    <?php echo tqa_stat(t('بلا معلم مسند'), $tq_noteach, array(
        'icon' => 'user-check', 'tone' => $tq_noteach ? 'danger' : 'ok',
        'hint' => t('تحرر من «معلمو المسار»'),
    )); ?>

    <?php echo tqa_stat(t('لها معلم ولا وقت'), $tq_notime, array(
        'icon' => 'clock', 'tone' => $tq_notime ? 'warn' : 'ok',
        'hint' => t('الوقت يفتحه المعلم من بوابته'),
    )); ?>

    <?php echo tqa_stat(t('مواعيد مفتوحة الآن'), $tq_slots, array(
        'icon' => 'calendar', 'tone' => $tq_slots > 0 ? 'info' : 'warn',
        'hint' => t('قابلة للحجز في الأيام القادمة'),
    )); ?>
</div>

<?php if (!$tq_tracks): ?>
    <div class="tqa-note tqa-note--warn" style="margin-block-end:var(--tq-space-xl)">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong><?php echo t('لا مسار تأسيس بعد.'); ?></strong>
            <?php echo t('وبلا مسار منشور لا يظهر القسم أصلا: لا بند في قائمة الطالب، ولا صفحة عامة، ولا خيار «تأسيس» في شاشة أوقات المعلم. أنشئ مسارا أولا.'); ?>
        </span>
    </div>
<?php elseif ($tq_noteach > 0 || $tq_notime > 0): ?>
    <div class="tqa-note tqa-note--warn" style="margin-block-end:var(--tq-space-xl)">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <?php if ($tq_noteach > 0): ?>
                <strong><span class="tqa-num"><?php echo $tq_noteach; ?></span> <?php echo t('مسارا منشورا بلا معلم مسند.'); ?></strong>
                <?php echo t('والاسناد شرط: من لا يسند إليه المسار لا يجد خيار «تأسيس» في شاشة أوقاته أصلا، فلا يفتح فيه وقتا مهما أراد.'); ?>
            <?php endif; ?>
            <?php if ($tq_notime > 0): ?>
                <strong><span class="tqa-num"><?php echo $tq_notime; ?></span> <?php echo t('مسارا له معلم ولا وقت مفتوح فيه.'); ?></strong>
                <?php echo t('والوقت يفتحه المعلم بنفسه من «الحصص» في بوابته — ولا تفتحه الإدارة نيابة عنه: ساعة تحجز على معلم قد لا تكون فارغة عنده.'); ?>
            <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<section class="tqa-card tqa-card--flush" style="margin-block-end:var(--tq-space-xl)">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('graduation', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('المسارات وحالها'); ?></h2>
            <span class="tqa-media__sub"><?php echo t('ما يعرض، وبكم، ومن يدرسه — ولماذا لا يعرض.'); ?></span>
        </div>
        <span class="tqa-badge tqa-badge--muted">
            <span class="tqa-num"><?php echo count($tq_tracks); ?></span>&nbsp;<?php echo t('مسارا'); ?>
        </span>
    </div>

    <div class="tqa-table__wrap">
        <table class="tqa-table tqa-table--zebra">
            <thead>
                <tr>
                    <th><?php echo t('المسار'); ?></th>
                    <th><?php echo t('سعر الحصة'); ?></th>
                    <th><?php echo t('معلموه'); ?></th>
                    <th><?php echo t('مواعيد مفتوحة'); ?></th>
                    <th><?php echo t('حصص'); ?></th>
                    <th><?php echo t('محصل'); ?></th>
                    <th><?php echo t('الحال'); ?></th>
                    <th><span class="tqa-sr"><?php echo t('تحرير'); ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tq_tracks as $tid => $t): ?>
                <?php list($tone, $why) = $tq_state($t); ?>
                <tr>
                    <td>
                        <strong><?php echo html_escape($t['name']); ?></strong>
                        <?php if ($t['tagline'] !== ''): ?>
                            <div class="tqa-cell__sub"><?php echo html_escape($t['tagline']); ?></div>
                        <?php endif; ?>
                        <div class="tqa-cell__sub" dir="ltr">/foundation/<?php echo html_escape($t['slug']); ?></div>
                    </td>
                    <td>
                        <?php echo $tq_sar($t['pricing']['price']); ?>
                        <?php /* من اين جاء الرقم: سعر المسار او استثناء
                                 المعلم او العام — والمسؤول يعدل في الموضع
                                 الصحيح متى عرف اين كتب. */ ?>
                        <div class="tqa-cell__sub">
                            <?php echo $t['price'] === null ? t('التسعيرة العامة') : t('سعر المسار'); ?>
                            ·
                            <?php echo t('نصيب المعلم'); ?>
                            <span class="tqa-num"><?php echo (float) $t['pricing']['percent']; ?></span>%
                        </div>
                    </td>
                    <td>
                        <span class="tqa-num"><?php echo (int) $t['teachers']; ?></span>
                        <?php if ((int) $t['teachers'] > 0): ?>
                            <div class="tqa-cell__sub">
                                <?php echo t('فتح وقتا منهم'); ?>
                                <span class="tqa-num"><?php echo (int) $t['teachers_open']; ?></span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><span class="tqa-num"><?php echo (int) $t['open_slots']; ?></span></td>
                    <td>
                        <span class="tqa-num"><?php echo (int) $t['total']; ?></span>
                        <div class="tqa-cell__sub">
                            <?php echo t('قادمة'); ?> <span class="tqa-num"><?php echo (int) $t['upcoming']; ?></span>
                            · <?php echo t('انتهت'); ?> <span class="tqa-num"><?php echo (int) $t['completed']; ?></span>
                        </div>
                    </td>
                    <td><?php echo $tq_sar($t['gross']); ?></td>
                    <td><span class="tqa-badge tqa-badge--<?php echo $tone; ?>"><?php echo html_escape($why); ?></span></td>
                    <td>
                        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                           href="<?php echo site_url('taqdar_admin/form/foundation_tracks/' . (int) $tid); ?>">
                            <?php echo t('تحرير'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="tqa-card tqa-card--flush">
    <div class="tqa-card__head">
        <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('clock', 20); ?></span>
        <div style="min-inline-size:0">
            <h2><?php echo t('أوقات التأسيس المفتوحة'); ?></h2>
            <span class="tqa-media__sub"><?php echo t('ما كتبه المعلمون بأيديهم — القاعدة الأسبوعية لا المواعيد.'); ?></span>
        </div>
    </div>

    <?php $tq_any = false; foreach ($tq_wins as $rows) if ($rows) $tq_any = true; ?>

    <?php if (!$tq_any): ?>
        <div class="tqa-empty">
            <p><?php echo t('لم يفتح أحد وقت تأسيس بعد.'); ?></p>
            <p class="tqa-cell__sub">
                <?php echo t('الوقت يفتحه المعلم من «الحصص» في بوابته: يضيف سطرا، ويختار «تأسيس» في عمود النوع، ثم مساره. ولا يظهر له خيار التأسيس إلا إن أسند إليه مسار من هنا.'); ?>
            </p>
        </div>
    <?php else: ?>
        <div class="tqa-table__wrap">
            <table class="tqa-table tqa-table--zebra">
                <thead>
                    <tr>
                        <th><?php echo t('المسار'); ?></th>
                        <th><?php echo t('المعلم'); ?></th>
                        <th><?php echo t('اليوم'); ?></th>
                        <th><?php echo t('الوقت'); ?></th>
                        <th><?php echo t('مواعيد مفتوحة'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tq_wins as $trk => $rows): ?>
                    <?php foreach ($rows as $w): ?>
                        <tr>
                            <td><?php echo html_escape($w['track_name'] !== null && $w['track_name'] !== ''
                                    ? $w['track_name'] : t('مسار محذوف') . ' #' . (int) $trk); ?></td>
                            <td><?php echo html_escape($w['teacher_name']); ?></td>
                            <td><?php echo html_escape($tq_ses ? ($tq_ses->days()[(int) $w['dow']] ?? '') : ''); ?></td>
                            <td dir="ltr"><?php echo $tq_ses
                                    ? html_escape($tq_ses->span_text((int) $w['start_min'], (int) $w['end_min'])) : ''; ?></td>
                            <td><span class="tqa-num"><?php echo (int) $w['open_slots']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="tqa-card__foot">
        <p class="tqa-cell__sub" style="margin:0">
            <?php echo t('ومدة الحصة وتسعيرتها العامة تحرران في'); ?>
            <a href="<?php echo site_url('taqdar_admin/sessions#tqa-pricing'); ?>"><?php echo t('شاشة الحصص'); ?></a>
            — <?php echo t('وهي تسعيرة الحصص كلها، منهجا وتأسيسا. وسعر المسار يعلوها متى كتب.'); ?>
        </p>
    </div>
</section>
