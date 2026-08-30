<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تحرير نصوص صفحة.
 *
 * كل حقل يعرض قيمته المحفوظة إن وجدت، **والنص الأصلي في المكان النائب**
 * إن لم توجد. فالمحرر يرى ما سيظهر لو ترك الحقل فارغا، ولا يحتاج أن
 * يفتح الصفحة ليعرف. وهذا أهم تفصيل في الشاشة: بلا المكان النائب يبدو
 * الحقل الفارغ كأن الصفحة بلا عنوان.
 */
$edited = 0;
foreach ($spec['fields'] as $k => $f) {
    if (isset($values[$k])) $edited++;
}
?>

<?php
$tools = '<a class="tqa-btn tqa-btn--ghost" href="' . base_url($spec['route']) . '" target="_blank" rel="noopener">'
       . tq_icon('eye', 16) . t(' عاين الصفحة</a>')
       . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/content') . t('">كل الصفحات</a>');
tqa_head($spec['title'], $spec['lead'], $spec['icon'], $tools);
?>

<form action="<?php echo site_url('taqdar_admin/content_save/' . $page); ?>" method="post">
    <?php echo tq_csrf(); ?>

    <div class="tqa-card" style="margin-block-end:var(--tq-space-l)">

        <?php if ($edited > 0): ?>
            <div class="tqa-note" style="margin-block-end:var(--tq-space-xl)">
                <span aria-hidden="true"><?php echo tq_icon('edit', 18); ?></span>
                <span>
                    <span class="tqa-num"><?php echo $edited; ?></span>
                    <?php echo t('من هذه الحقول محرر ويظهر على الموقع الآن. والباقي يعرض نصه الأصلي — وهو المكتوب بالرمادي في كل حقل فارغ.'); ?>
                </span>
            </div>
        <?php endif; ?>

        <?php foreach ($spec['fields'] as $ckey => $f):
            $val = isset($values[$ckey]) ? $values[$ckey] : '';
            $id  = 'f-' . $ckey;
        ?>
            <div class="tqa-field">
                <label class="tqa-field__label" for="<?php echo $id; ?>">
                    <?php echo html_escape($f['label']); ?>
                    <?php if ($val !== ''): ?>
                        <span class="tqa-badge tqa-badge--ok" style="margin-inline-start:6px"><?php echo t('محرر'); ?></span>
                    <?php endif; ?>
                </label>

                <?php if ($f['type'] === 'text'): ?>
                    <textarea class="tqa-textarea" id="<?php echo $id; ?>" name="<?php echo $ckey; ?>"
                              rows="3" placeholder="<?php echo html_escape($f['default']); ?>"><?php
                        echo html_escape($val); ?></textarea>
                <?php else: ?>
                    <input class="tqa-input" id="<?php echo $id; ?>" type="text" name="<?php echo $ckey; ?>"
                           value="<?php echo html_escape($val); ?>"
                           placeholder="<?php echo html_escape($f['default']); ?>"
                           maxlength="<?php echo $f['type'] === 'label' ? 60 : 200; ?>">
                <?php endif; ?>

                <span class="tqa-field__hint">
                    <?php if (!empty($f['hint'])): ?>
                        <?php echo html_escape($f['hint']); ?>
                        <br>
                    <?php endif; ?>
                    <?php echo t('الأصلي:'); ?> <span style="color:var(--tq-text3)">«<?php
                        echo html_escape(mb_strimwidth($f['default'], 0, 110, '…', 'UTF-8')); ?>»</span>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="display:flex;gap:var(--tq-space-s);flex-wrap:wrap">
        <button class="tqa-btn tqa-btn--primary" type="submit"><?php echo t('احفظ النصوص'); ?></button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/content'); ?>"><?php echo t('رجوع'); ?></a>
    </div>

    <div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
        <span>
            <?php echo t('الحقول تقبل نصا لا وسوم HTML — والوسوم تجرد عند الحفظ. وحقل يترك فارغا يرجع إلى نصه الأصلي، فامسحه واحفظ لتتراجع عن تعديل.'); ?>
        </span>
    </div>
</form>
