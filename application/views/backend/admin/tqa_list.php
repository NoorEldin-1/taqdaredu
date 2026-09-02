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

/* عمود حال محسوب — تعلنه الوحدة باسم دالة في النموذج.
   وهو جواب السؤال الذي يدفع المسؤول إلى فتح القائمة أصلا: «صنعت الصف،
   فأين هو؟». والباقة تسقط من الصفحة العامة عند أربعة شروط مختلفة، وكان
   يعبرها كلها بلا إشارة واحدة — ثم يفتح `/plans` فلا يجد شيئا. */
$status_fn = (!empty($spec['status_fn']) && method_exists($M, $spec['status_fn']))
    ? $spec['status_fn'] : '';

/* الأعمدة تحسب مرة: `count($rows)` صف في جدول تعني إعادة المرور على
   كل الحقول لكل صف لمعرفة أيها يعرض. */
$cols = array();
foreach ($spec['fields'] as $name => $f) {
    if (!empty($f['list'])) $cols[$name] = $f;
}

/* اسم الصف كما يقرؤه المسؤول — يستعمله رأس قائمة الإجراءات. وأول
   عمود نصي معروض هو ما يسمي الصف في كل وحدة تقريبا (`name_ar` ·
   `title`)؛ وما لا نص فيه يعرف بمعرفه، وهو أصدق من اسم مخترع. */
$namecol = '';
foreach ($cols as $cn => $cf) {
    if ($cf['type'] === 'text') { $namecol = $cn; break; }
}
$tq_name = function ($row) use ($namecol) {
    $v = ($namecol !== '' && isset($row[$namecol])) ? trim((string) $row[$namecol]) : '';
    return $v !== '' ? $v : ('#' . (int) $row['id']);
};

$tools = $readonly ? '' :
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('taqdar_admin/form/' . $mkey) . '">'
  . tq_icon('plus', 17) . t(' إضافة</a>');
?>

<?php tqa_head($spec['title'], $spec['lead'], isset($spec['icon']) ? $spec['icon'] : 'circle', $tools); ?>

<?php if (!empty($spec['note'])): ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
        <span><?php echo html_escape($spec['note']); ?></span>
    </div>
<?php endif; ?>

<?php
/* شريط البحث — يطبع ولو لم يرد الاستعلام شيئا: من بحث فلم يجد يحتاج
   الحقل ليصحح كلمته، وإخفاؤه مع النتيجة يتركه بلا مخرج غير الرجوع.
   ولا يطبع على وحدة صغيرة لم تتجاوز صفحة واحدة قط: أداة لا تلزم
   تزحم الشاشة وتوحي بأن تحتها ما لا يرى. */
$has_text = false;
foreach ($cols as $f) {
    if (in_array($f['type'], array('text', 'textarea', 'lines'), true)) { $has_text = true; break; }
}
$show_search = $has_text && ($view['q'] !== '' || $view['total'] > $view['per']);
?>

<?php if ($show_search): ?>
    <div class="tqa-toolbar" style="margin-block-end:var(--tq-space-l)">
        <form method="get" action="<?php echo site_url('taqdar_admin/module/' . $mkey); ?>" role="search">
            <?php /* الفرز يسافر مع البحث: بحث يمحو ترتيبا اختير قبله يعيد
                     المسؤول إلى أول القائمة بلا أن يطلب. */ ?>
            <?php if ($view['sort'] !== ''): ?>
                <input type="hidden" name="sort" value="<?php echo html_escape($view['sort']); ?>">
                <input type="hidden" name="dir" value="<?php echo html_escape($view['dir']); ?>">
            <?php endif; ?>
            <input class="tqa-input" type="search" name="q" value="<?php echo html_escape($view['q']); ?>"
                   placeholder="<?php echo te('ابحث في المعروض…'); ?>"
                   aria-label="<?php echo te('بحث'); ?>">
            <button class="tqa-btn tqa-btn--ghost" type="submit">
                <?php echo tq_icon('search', 16); ?> <?php echo t('بحث'); ?>
            </button>
            <?php if ($view['q'] !== ''): ?>
                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                   href="<?php echo html_escape(tqa_url_with('taqdar_admin/module/' . $mkey, array('q' => null, 'p' => null))); ?>">
                    <?php echo t('مسح'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>

<div class="tqa-card tqa-card--flush">
<?php if (empty($rows)): ?>

    <?php if ($view['q'] !== ''): ?>
        <?php /* «لا نتائج لبحثك» غير «لا صفوف بعد»: الأولى تدعو إلى تعديل
                 الكلمة، والثانية تدعو إلى الإضافة — ودعوة إلى إضافة صف
                 موجود أصلا تجعل المسؤول ينشئ ثانيا مكررا. */ ?>
        <?php tqa_empty(
            t('لا نتيجة لـ') . ' «' . $view['q'] . '»',
            t('لا صف في هذه الوحدة يطابق ما كتبت. والبحث على الأعمدة النصية المعروضة وحدها.'),
            t('اعرض الكل'),
            tqa_url_with('taqdar_admin/module/' . $mkey, array('q' => null, 'p' => null)),
            'search'
        ); ?>
    <?php else: ?>
        <?php tqa_empty(
            t('لا ') . $spec['title'] . t(' بعد'),
            $readonly
                ? t('تمتلئ هذه الشاشة وحدها حين يبدأ النظام في التسجيل — ولا يضاف إليها بيد.')
                : t('ابدأ بإضافة أول عنصر؛ وحدات أخرى تعتمد عليه.'),
            $readonly ? '' : t('إضافة الآن'),
            $readonly ? '' : site_url('taqdar_admin/form/' . $mkey),
            isset($spec['icon']) ? $spec['icon'] : 'folder'
        ); ?>
    <?php endif; ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <thead>
                <tr>
                    <?php echo tqa_sort_th('taqdar_admin/module/' . $mkey, 'id', '#',
                        $view['sort'], $view['dir'], $sortable); ?>
                    <?php foreach ($cols as $cname => $f): ?>
                        <?php echo tqa_sort_th('taqdar_admin/module/' . $mkey, $cname, $f['label'],
                            $view['sort'], $view['dir'], $sortable); ?>
                    <?php endforeach; ?>
                    <?php if ($status_fn !== ''): ?>
                        <th><?php echo html_escape(isset($spec['status_label']) ? $spec['status_label'] : t('الحال')); ?></th>
                    <?php endif; ?>
                    <?php if (!$readonly): ?>
                        <th class="tqa-col--acts"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
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

                    <?php if ($status_fn !== ''):
                        $st = $M->{$status_fn}($r);
                        $tone = array('ok' => 'ok', 'warn' => 'warn', 'no' => 'danger');
                    ?>
                        <td data-label="<?php echo html_escape(isset($spec['status_label']) ? $spec['status_label'] : t('الحال')); ?>">
                            <span class="tqa-badge tqa-badge--<?php echo isset($tone[$st['tone']]) ? $tone[$st['tone']] : 'muted'; ?>">
                                <?php echo html_escape($st['label']); ?>
                            </span>
                            <span class="tqa-status__why"><?php echo html_escape($st['why']); ?></span>
                        </td>
                    <?php endif; ?>

                    <?php if (!$readonly): ?>
                    <?php
                    /* TQ-ROW-CLUTTER — ما يفعل بالصف قائمة واحدة لا صف
                       أزرار: كان العمود ثلاثة عناصر متساوية الوزن (إجراء
                       الوحدة · تعديل · حذف) بعرض مئة وخمسين بكسلا في كل
                       وحدة من ثلاث وخمسين، والحذف بينها بحجم التعديل
                       ولونه إلا حبره.

                       ورأس القائمة يحمل اسم الصف: اللوح يفتح فوق الجدول
                       فيحجب صفه، ومن فتح قائمة الصف الحادي عشر ثم تردد
                       لا يعرف أهي قائمته أم قائمة جاره. والاسم من أول
                       عمود نصي معروض — وهو الذي يسمي الصف في كل وحدة. */
                    $tq_acts = array();

                    if (!empty($spec['row_action'])):
                        $ra = $spec['row_action'];
                        $tq_acts[] = array(
                            'label' => $ra['label'],
                            'href'  => site_url($ra['href'] . (int) $r['id']),
                            'icon'  => isset($ra['icon']) ? $ra['icon'] : 'link',
                            'tone'  => 'go',
                        );
                    endif;

                    $tq_acts[] = array(
                        'label' => t('تعديل'),
                        'sub'   => t('افتح النموذج بكل حقول هذا الصف'),
                        'href'  => site_url('taqdar_admin/form/' . $mkey . '/' . (int) $r['id']),
                        'icon'  => 'edit',
                    );

                    if (empty($spec['nodelete'])):
                        $tq_acts[] = array('sep' => true);
                        $tq_acts[] = array(
                            'label'   => t('حذف'),
                            'action'  => 'taqdar_admin/delete/' . $mkey . '/' . (int) $r['id'],
                            'icon'    => 'trash',
                            'tone'    => 'danger',
                            'confirm' => array(
                                'title' => t('حذف نهائي'),
                                'body'  => t('لا رجعة في هذا الحذف. وقد تعتمد عليه وحدات أخرى.'),
                                'ok'    => t('نعم، احذف'),
                                'tone'  => 'danger',
                            ),
                        );
                    endif;
                    ?>
                    <td class="tqa-col--acts" data-label="<?php echo te('إجراءات'); ?>">
                        <?php echo tqa_rowmenu($tq_acts, array(
                            'title' => $tq_name($r),
                            'sub'   => '#' . (int) $r['id'],
                        )); ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php /* الذيل يقول أين المسؤول من الكل، لا كم صفا طبع: «المعروض ٢٠٠»
             وحدها لا تقول إن تحتها ألفا. وعلى صفحة واحدة يكفي العدد. */ ?>
    <div class="tqa-tablefoot">
        <?php if ($view['pages'] > 1): ?>
            <?php tqa_pager('taqdar_admin/module/' . $mkey, $view['page'], $view['pages'], $view['total']); ?>
        <?php else: ?>
            <span class="tqa-pager__info">
                <?php echo t('المعروض'); ?>
                <span class="tqa-num"><?php echo count($rows); ?></span> <?php echo t('عنصرا'); ?>
                <?php if ($view['q'] !== ''): ?>
                    <?php echo t('— نتيجة بحث، والوحدة فيها'); ?>
                    <span class="tqa-num"><?php echo (int) $M->count_rows($mkey); ?></span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </div>

<?php endif; ?>
</div>
