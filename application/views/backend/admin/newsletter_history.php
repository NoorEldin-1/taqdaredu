<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * سجل إرسال النشرة — عرض من الخادم.
 * انظر TQ-DT-GONE في [Admin::newsletter_history()].
 */
$tq_tabs = array(
    'pending' => array(t('بانتظار الإرسال'), 'warn'),
    'sent'    => array(t('أرسلت'),           'ok'),
    'faild'   => array(t('تعثرت'),           'danger'),
    'unable'  => array(t('تعذر إرسالها'),    'muted'),
);

$tq_url = function ($t, $p = 1) use ($search) {
    $qs = array_filter(array('q' => $search, 'page' => $p > 1 ? $p : null));
    return site_url('admin/newsletter_history/' . $t) . ($qs ? '?' . http_build_query($qs) : '');
};
?>

<?php tqa_head(t('سجل إرسال النشرة'),
    t('كل رسالة وحالتها. والمتعثرة تعاد يدويا من هنا.'),
    'receipt',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/newsletters') . '">'
  . tq_icon('chev-prev', 16) . t('قوالب النشرة</a>')); ?>

<nav class="tqa-tabs" aria-label="<?php echo te('تصفية السجل بالحالة'); ?>">
    <?php foreach ($tq_tabs as $tq_k => [$tq_label, $tq_tone]): ?>
        <a href="<?php echo site_url('admin/newsletter_history/' . $tq_k); ?>"
           <?php echo $type === $tq_k ? 'aria-current="page"' : ''; ?>>
            <?php echo html_escape($tq_label); ?>
        </a>
    <?php endforeach; ?>
</nav>

<form class="tqa-toolbar" method="get" action="<?php echo site_url('admin/newsletter_history/' . $type); ?>">
    <label class="tqa-sr" for="q"><?php echo t('ابحث في السجل'); ?></label>
    <input class="tqa-input" type="search" id="q" name="q" placeholder="<?php echo te('ابحث ببريد أو عنوان…'); ?>"
           value="<?php echo html_escape($search); ?>">
    <button type="submit" class="tqa-btn tqa-btn--primary"><?php echo tq_icon('search', 16); ?> ابحث</button>
    <?php if ($search !== ''): ?>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/newsletter_history/' . $type); ?>">
            <?php echo t('مسح البحث'); ?>
        </a>
    <?php endif; ?>
</form>

<div class="tqa-card tqa-card--flush">
<?php if (empty($rows)): ?>

    <?php tqa_empty(
        $search !== '' ? t('لا نتيجة لهذا البحث') : t('لا رسائل بهذه الحالة'),
        $search !== ''
            ? t('جرب جزءا من البريد أو العنوان.')
            : t('يمتلئ هذا الجدول حين ترسل نشرة — ولا يضاف إليه بيد.'),
        '', '', 'receipt'
    ); ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <caption class="tqa-sr"><?php echo t('سجل رسائل النشرة وحالة كل رسالة'); ?></caption>
            <thead>
                <tr>
                    <th style="inline-size:60px">#</th>
                    <th><?php echo t('العنوان'); ?></th>
                    <th><?php echo t('المستقبل'); ?></th>
                    <th style="inline-size:130px"><?php echo t('الحالة'); ?></th>
                    <th style="inline-size:150px"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $tq_i => $tq_r):
                $tq_ord  = ($page_no - 1) * $per_page + $tq_i + 1;
                $tq_tone = $tq_tabs[$tq_r['status']][1] ?? 'muted';
                $tq_lbl  = $tq_tabs[$tq_r['status']][0] ?? $tq_r['status'];
            ?>
                <tr>
                    <td data-label="#"><span class="tqa-num"><?php echo $tq_ord; ?></span></td>

                    <td data-label="العنوان"><?php echo html_escape($tq_r['subject']); ?></td>

                    <td data-label="المستقبل">
                        <span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_r['email']); ?></span>
                    </td>

                    <td data-label="الحالة">
                        <span class="tqa-badge tqa-badge--<?php echo $tq_tone; ?>"><?php echo html_escape($tq_lbl); ?></span>
                    </td>

                    <td data-label="إجراءات">
                        <?php /* الإرسال نموذج POST بتوكن — كان رابطا ينفذ
                                 بجلبه، ويرسل بريدا فعليا بمجرد الجلب. */ ?>
                        <form method="post"
                              action="<?php echo site_url('admin/newsletter_history/send/' . (int) $tq_r['id']); ?>"
                              data-tqa-confirm-title="<?php echo $tq_r['status'] === 'sent' ? t('إعادة الإرسال') : t('إرسال الآن'); ?>"
                              data-tqa-confirm="سترسل الرسالة إلى <?php echo html_escape($tq_r['email']); ?> الآن."
                              data-tqa-confirm-ok="أرسل">
                            <?php echo tq_csrf(); ?>
                            <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm">
                                <?php echo tq_icon('send', 14); ?>
                                <?php echo $tq_r['status'] === 'sent' ? t('أرسل ثانية') : t('أرسل الآن'); ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $tq_from = max(1, $page_no - 2);
    $tq_last = min($page_count, $tq_from + 4);
    $tq_from = max(1, $tq_last - 4);
    ?>
    <nav class="tqa-pager" aria-label="<?php echo te('صفحات السجل'); ?>">
        <span class="tqa-pager__info">
            <?php echo t('المعروض'); ?> <span class="tqa-num"><?php echo count($rows); ?></span>
            <?php echo t('من'); ?> <span class="tqa-num"><?php echo (int) $total; ?></span> <?php echo t('رسالة'); ?>
        </span>

        <?php if ($page_no > 1): ?>
            <a href="<?php echo $tq_url($type, $page_no - 1); ?>" rel="prev" aria-label="<?php echo te('الصفحة السابقة'); ?>">
                <?php echo tq_icon('chev-prev', 16); ?>
            </a>
        <?php endif; ?>

        <?php for ($tq_p = $tq_from; $tq_p <= $tq_last; $tq_p++): ?>
            <?php if ($tq_p === (int) $page_no): ?>
                <span aria-current="page"><?php echo $tq_p; ?></span>
            <?php else: ?>
                <a href="<?php echo $tq_url($type, $tq_p); ?>"><?php echo $tq_p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page_no < $page_count): ?>
            <a href="<?php echo $tq_url($type, $page_no + 1); ?>" rel="next" aria-label="<?php echo te('الصفحة التالية'); ?>">
                <?php echo tq_icon('chev-next', 16); ?>
            </a>
        <?php endif; ?>
    </nav>

<?php endif; ?>
</div>
