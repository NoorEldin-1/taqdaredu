<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * نصوص الصفحات — الفهرس.
 *
 * كل بطاقة صفحة عامة منشورة، وعليها كم حقلا حرر منها. والصفر ليس عيبا:
 * يعني أن الصفحة تعرض نص قالبها الأصلي، وهو نص مكتوب بعناية أصلا.
 */
?>

<?php tqa_head(t('نصوص الصفحات'),
    t('عناوين الصفحات العامة ونصوصها — تحرر من هنا وتظهر على الموقع فورا، بلا نشر.'),
    'edit'); ?>

<div class="tqa-note" style="margin-block-end:var(--tq-space-xl)">
    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
    <span>
        <?php echo t('الحقل الذي يترك فارغا يرجع إلى النص الأصلي المكتوب في القالب — لا يمسح العنوان. فإن أردت التراجع عن تعديل، امسح الحقل واحفظ.'); ?>
    </span>
</div>

<div class="tqa-grid tqa-grid--3">
<?php foreach ($pages as $key => $p):
    $n = (int) ($edited[$key] ?? 0);
    $total = count($p['fields']);
?>
    <article class="tqa-card">
        <div style="display:flex;align-items:center;gap:var(--tq-space-m);margin-block-end:var(--tq-space-m)">
            <span class="tqa-iconbox" aria-hidden="true"><?php echo tq_icon($p['icon'], 20); ?></span>
            <div style="min-inline-size:0">
                <h2 style="margin:0;font:var(--tq-type-h2)"><?php echo html_escape($p['title']); ?></h2>
                <span class="tqa-num" style="font-size:12px;color:var(--tq-text2);direction:ltr">
                    /<?php echo html_escape($p['route']); ?>
                </span>
            </div>
        </div>

        <p style="margin-block-end:var(--tq-space-l);font:var(--tq-type-caption);color:var(--tq-text2)">
            <?php echo html_escape($p['lead']); ?>
        </p>

        <div style="display:flex;align-items:center;gap:var(--tq-space-s);margin-block-end:var(--tq-space-l)">
            <?php if ($n > 0): ?>
                <span class="tqa-badge tqa-badge--ok">
                    <?php echo t('حرر'); ?> <span class="tqa-num"><?php echo $n; ?></span>
                    <?php echo t('من'); ?> <span class="tqa-num"><?php echo $total; ?></span>
                </span>
            <?php else: ?>
                <span class="tqa-badge tqa-badge--muted">
                    <span class="tqa-num"><?php echo $total; ?></span> <?php echo t('حقلا بنصها الأصلي'); ?>
                </span>
            <?php endif; ?>
        </div>

        <div style="display:flex;gap:var(--tq-space-s)">
            <a class="tqa-btn tqa-btn--primary tqa-btn--sm"
               href="<?php echo site_url('taqdar_admin/content/' . $key); ?>"><?php echo t('حرر النصوص'); ?></a>
            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
               href="<?php echo base_url($p['route']); ?>" target="_blank" rel="noopener">
                <?php echo tq_icon('eye', 15); ?> <?php echo t('عاين'); ?>
            </a>
        </div>
    </article>
<?php endforeach; ?>
</div>
