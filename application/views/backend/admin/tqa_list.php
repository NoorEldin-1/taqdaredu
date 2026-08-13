<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الشاشة العامة لكل وحدة موصوفة.
 *
 * لا تعرف شيئا عن الجدول الذي تعرضه: `spec()` تصف الحقول وهي تصوغها.
 * والبديل — شاشة منسوخة لكل وحدة — يتعفن عند أول تعديل في حقل مشترك.
 */
$M        = &get_instance()->taqdar_admin_model;
$readonly = !empty($spec['readonly']);

/* الأعمدة تحسب مرة: `count($rows)` صف في جدول تعني إعادة المرور على
   كل الحقول لكل صف لمعرفة أيها يعرض. */
$cols = array();
foreach ($spec['fields'] as $name => $f) {
    if (!empty($f['list'])) $cols[$name] = $f;
}

$tools = $readonly ? '' :
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('taqdar_admin/form/' . $mkey) . '">'
  . tq_icon('plus', 17) . ' إضافة</a>';
?>

<?php tqa_head($spec['title'], $spec['lead'], isset($spec['icon']) ? $spec['icon'] : 'circle', $tools); ?>

<?php if (!empty($spec['note'])): ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
        <span><?php echo html_escape($spec['note']); ?></span>
    </div>
<?php endif; ?>

<div class="tqa-card tqa-card--flush">
<?php if (empty($rows)): ?>

    <?php tqa_empty(
        'لا ' . $spec['title'] . ' بعد',
        $readonly
            ? 'تمتلئ هذه الشاشة وحدها حين يبدأ النظام في التسجيل — ولا يضاف إليها بيد.'
            : 'ابدأ بإضافة أول عنصر؛ وحدات أخرى تعتمد عليه.',
        $readonly ? '' : 'إضافة الآن',
        $readonly ? '' : site_url('taqdar_admin/form/' . $mkey),
        isset($spec['icon']) ? $spec['icon'] : 'folder'
    ); ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr>
                    <th style="inline-size:64px">#</th>
                    <?php foreach ($cols as $f): ?>
                        <th><?php echo html_escape($f['label']); ?></th>
                    <?php endforeach; ?>
                    <?php if (!$readonly): ?>
                        <th style="inline-size:150px"><span class="tqa-sr">إجراءات</span></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td data-label="#"><span class="tqa-num"><?php echo (int) $r['id']; ?></span></td>

                    <?php foreach ($cols as $name => $f): ?>
                        <td data-label="<?php echo html_escape($f['label']); ?>">
                            <?php echo tqa_cell($f, isset($r[$name]) ? $r[$name] : null, $M); ?>
                        </td>
                    <?php endforeach; ?>

                    <?php if (!$readonly): ?>
                    <td data-label="إجراءات">
                        <div style="display:flex;gap:6px;align-items:center">
                            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                               href="<?php echo site_url('taqdar_admin/form/' . $mkey . '/' . (int) $r['id']); ?>">
                                <?php echo tq_icon('edit', 15); ?> تعديل
                            </a>

                            <?php if (empty($spec['nodelete'])): ?>
                                <?php /* الحذف نموذج لا رابط: رابط GET يحذف ينفذ
                                         بمجرد جلبه — من زاحف أو من استباق تحميل. */ ?>
                                <form method="post" style="margin:0"
                                      action="<?php echo site_url('taqdar_admin/delete/' . $mkey . '/' . (int) $r['id']); ?>"
                                      data-tqa-confirm-title="حذف نهائي"
                                      data-tqa-confirm="لا رجعة في هذا الحذف. وقد تعتمد عليه وحدات أخرى."
                                      data-tqa-confirm-ok="نعم، احذف"
                                      data-tqa-confirm-tone="danger">
                                    <?php echo tq_csrf(); ?>
                                    <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                            style="color:var(--tq-danger)">
                                        <?php echo tq_icon('trash', 15); ?>
                                        <span class="tqa-sr">حذف</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p style="padding:var(--tq-space-m) var(--tq-space-xl);margin:0;border-block-start:1px solid var(--tq-line);
              font:var(--tq-type-caption);color:var(--tq-text2)">
        المعروض <span class="tqa-num"><?php echo count($rows); ?></span> عنصرا
        <?php if (count($rows) >= 200): ?>
            — وهو حد العرض. المزيد موجود ولا يظهر هنا.
        <?php endif; ?>
    </p>

<?php endif; ?>
</div>
