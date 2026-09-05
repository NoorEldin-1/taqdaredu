<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مشتركو النشرة البريدية — عرض من الخادم.
 * انظر TQ-DT-GONE في [Admin::subscribed_user()] لسبب سقوط DataTables.
 */
$tq_tools = '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/newsletters') . '">'
          . tq_icon('send', 16) . t(' النشرات المرسلة</a>');
?>

<?php tqa_head(t('مشتركو النشرة البريدية'),
    t('من سجل بريده من الموقع العام. المسجل في المنصة يعرف باسمه، وغيره ببريده وحده.'),
    'send', $tq_tools); ?>

<form class="tqa-toolbar" method="get" action="<?php echo site_url('admin/subscribed_user'); ?>">
    <label class="tqa-sr" for="q"><?php echo t('ابحث ببريد المشترك'); ?></label>
    <input class="tqa-input" type="search" id="q" name="q" placeholder="<?php echo te('ابحث ببريد المشترك…'); ?>"
           value="<?php echo html_escape($search); ?>" dir="ltr">
    <button type="submit" class="tqa-btn tqa-btn--primary"><?php echo tq_icon('search', 16); ?> <?php echo t('ابحث'); ?></button>
    <?php if ($search !== ''): ?>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/subscribed_user'); ?>"><?php echo t('مسح البحث'); ?></a>
    <?php endif; ?>
</form>

<div class="tqa-card tqa-card--flush">
<?php if (empty($rows)): ?>

    <?php tqa_empty(
        $search !== '' ? t('لا مشترك يطابق هذا البحث') : t('لا مشتركين بعد'),
        $search !== ''
            ? t('جرب جزءا من البريد بدل البريد كاملا.')
            : t('يمتلئ هذا الجدول وحده حين يسجل الزوار بريدهم من تذييل الموقع — ولا يضاف إليه بيد.'),
        $search !== '' ? t('مسح البحث') : '',
        $search !== '' ? site_url('admin/subscribed_user') : '',
        'send'
    ); ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <caption class="tqa-sr"><?php echo t('المشتركون في النشرة وحالة كل منهم في المنصة'); ?></caption>
            <thead>
                <tr>
                    <th style="inline-size:60px">#</th>
                    <th><?php echo t('البريد الإلكتروني'); ?></th>
                    <th><?php echo t('الحساب في المنصة'); ?></th>
                    <th style="inline-size:120px"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $tq_i => $tq_r):
                $tq_ord  = ($page_no - 1) * $per_page + $tq_i + 1;
                $tq_user = $known[strtolower($tq_r['email'])] ?? null;
            ?>
                <tr>
                    <td data-label="#"><span class="tqa-num"><?php echo $tq_ord; ?></span></td>

                    <td data-label="<?php echo te('البريد الإلكتروني'); ?>">
                        <span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_r['email']); ?></span>
                    </td>

                    <td data-label="<?php echo te('الحساب في المنصة'); ?>">
                        <?php if ($tq_user): ?>
                            <span class="tqa-media__title">
                                <?php echo html_escape(trim($tq_user['first_name'] . ' ' . $tq_user['last_name'])); ?>
                            </span>
                            <span class="tqa-badge tqa-badge--<?php echo (int) $tq_user['is_instructor'] === 1 ? 'ok' : 'info'; ?>">
                                <?php echo (int) $tq_user['is_instructor'] === 1 ? t('معلم') : t('طالب'); ?>
                            </span>
                        <?php else: ?>
                            <span class="tqa-badge tqa-badge--muted"><?php echo t('غير مسجل'); ?></span>
                        <?php endif; ?>
                    </td>

                    <td data-label="<?php echo te('إجراءات'); ?>">
                        <form method="post" action="<?php echo site_url('admin/subscribed_user/delete/' . (int) $tq_r['id']); ?>"
                              data-tqa-confirm-title="<?php echo te('إلغاء الاشتراك'); ?>"
                              data-tqa-confirm="<?php echo te('لن تصل النشرة إلى هذا البريد بعدها.'); ?>"
                              data-tqa-confirm-ok="<?php echo te('نعم، ألغ'); ?>"
                              data-tqa-confirm-tone="danger">
                            <?php echo tq_csrf(); ?>
                            <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm tqa-btn--quiet-danger">
                                <?php echo tq_icon('trash', 14); ?> <?php echo t('إلغاء'); ?>
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
    $tq_url  = function ($p) use ($search) {
        $qs = array_filter(array('q' => $search, 'page' => $p > 1 ? $p : null));
        return site_url('admin/subscribed_user') . ($qs ? '?' . http_build_query($qs) : '');
    };
    ?>
    <nav class="tqa-pager" aria-label="<?php echo te('صفحات المشتركين'); ?>">
        <span class="tqa-pager__info">
            <?php echo t('المعروض'); ?> <span class="tqa-num"><?php echo count($rows); ?></span>
            <?php echo t('من'); ?> <span class="tqa-num"><?php echo (int) $total; ?></span> <?php echo t('مشتركا'); ?>
        </span>

        <?php if ($page_no > 1): ?>
            <a href="<?php echo $tq_url($page_no - 1); ?>" rel="prev" aria-label="<?php echo te('الصفحة السابقة'); ?>">
                <?php echo tq_icon('chev-prev', 16); ?>
            </a>
        <?php endif; ?>

        <?php for ($tq_p = $tq_from; $tq_p <= $tq_last; $tq_p++): ?>
            <?php if ($tq_p === (int) $page_no): ?>
                <span aria-current="page"><?php echo $tq_p; ?></span>
            <?php else: ?>
                <a href="<?php echo $tq_url($tq_p); ?>"><?php echo $tq_p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page_no < $page_count): ?>
            <a href="<?php echo $tq_url($page_no + 1); ?>" rel="next" aria-label="<?php echo te('الصفحة التالية'); ?>">
                <?php echo tq_icon('chev-next', 16); ?>
            </a>
        <?php endif; ?>
    </nav>

<?php endif; ?>
</div>
