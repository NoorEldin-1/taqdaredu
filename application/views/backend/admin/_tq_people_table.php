<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * جدول حسابات — مشترك بين شاشتي الطلاب والمعلمين.
 * انظر TQ-DT-GONE في [Admin::tq_people_page()].
 *
 * ينتظر: `$tq_kind` ('user' أو 'instructor')، و`$tq_base` مسار الشاشة،
 * و`$tq_form` مسار نموذج الإضافة والتحرير.
 */
$tq_url = function ($p = 1) use ($tq_base, $search) {
    $qs = array_filter(array('q' => $search, 'page' => $p > 1 ? $p : null));
    return site_url($tq_base) . ($qs ? '?' . http_build_query($qs) : '');
};
?>

<form class="tqa-toolbar" method="get" action="<?php echo site_url($tq_base); ?>">
    <label class="tqa-sr" for="q"><?php echo t('ابحث في الحسابات'); ?></label>
    <input class="tqa-input" type="search" id="q" name="q" placeholder="<?php echo te('ابحث بالاسم أو البريد أو الهاتف…'); ?>"
           value="<?php echo html_escape($search); ?>" style="min-inline-size:280px">
    <button type="submit" class="tqa-btn tqa-btn--primary"><?php echo tq_icon('search', 16); ?> ابحث</button>
    <?php if ($search !== ''): ?>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url($tq_base); ?>"><?php echo t('مسح البحث'); ?></a>
    <?php endif; ?>
</form>

<div class="tqa-card tqa-card--flush">
<?php if (empty($rows)): ?>

    <?php tqa_empty(
        $search !== '' ? t('لا حساب يطابق هذا البحث') : t('لا حسابات بعد'),
        $search !== '' ? t('جرب جزءا من الاسم أو البريد.') : t('أضف أول حساب من الزر أعلى الشاشة.'),
        $search !== '' ? t('مسح البحث') : t('إضافة حساب'),
        $search !== '' ? site_url($tq_base) : site_url($tq_form),
        'users'
    ); ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <caption class="tqa-sr"><?php echo t('الحسابات وبيانات التواصل بها وعدد تسجيلاتها'); ?></caption>
            <thead>
                <tr>
                    <th style="inline-size:60px">#</th>
                    <th><?php echo t('الحساب'); ?></th>
                    <th><?php echo t('الهاتف'); ?></th>
                    <th style="inline-size:120px"><?php echo t('الكورسات'); ?></th>
                    <th style="inline-size:110px"><?php echo t('الحالة'); ?></th>
                    <th style="inline-size:190px"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $tq_i => $tq_u):
                $tq_id  = (int) $tq_u['id'];
                $tq_ord = ($page_no - 1) * $per_page + $tq_i + 1;
                $tq_n   = trim($tq_u['first_name'] . ' ' . $tq_u['last_name']);
                if ($tq_n === '') $tq_n = $tq_u['email'];
                $tq_on  = (int) $tq_u['status'] === 1;
            ?>
                <tr>
                    <td data-label="#"><span class="tqa-num"><?php echo $tq_ord; ?></span></td>

                    <td data-label="الحساب">
                        <span class="tqa-media">
                            <img class="tqa-avatar" alt="" width="38" height="38" loading="lazy"
                                 src="<?php echo html_escape($this->user_model->get_user_image_url($tq_id)); ?>">
                            <span class="tqa-media__body">
                                <span class="tqa-media__title"><?php echo html_escape($tq_n); ?></span>
                                <span class="tqa-media__sub tq-ltr" dir="ltr"><?php echo html_escape($tq_u['email']); ?></span>
                            </span>
                        </span>
                    </td>

                    <td data-label="الهاتف">
                        <?php if (trim((string) $tq_u['phone']) !== ''): ?>
                            <span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_u['phone']); ?></span>
                        <?php else: ?>
                            <span class="tqa-dim">—</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="الكورسات">
                        <span class="tqa-num"><?php echo (int) ($enrols[$tq_id] ?? 0); ?></span>
                    </td>

                    <td data-label="الحالة">
                        <span class="tqa-badge tqa-badge--<?php echo $tq_on ? 'ok' : 'muted'; ?>">
                            <?php echo $tq_on ? t('نشط') : t('موقوف'); ?>
                        </span>
                    </td>

                    <td data-label="إجراءات">
                        <div class="tqa-rowacts">
                            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                               href="<?php echo site_url($tq_form . '/' . $tq_id); ?>">
                                <?php echo tq_icon('edit', 14); ?> تعديل
                            </a>

                            <?php if ($tq_kind === 'instructor'): ?>
                                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" target="_blank" rel="noopener"
                                   href="<?php echo site_url('home/instructor_page/' . $tq_id); ?>"
                                   title="<?php echo te('صفحته في الموقع'); ?>">
                                    <?php echo tq_icon('external', 14); ?>
                                    <span class="tqa-sr"><?php echo t('صفحته في الموقع'); ?></span>
                                </a>
                            <?php endif; ?>

                            <form method="post" action="<?php echo site_url($tq_base . '/delete/' . $tq_id); ?>"
                                  data-tqa-confirm-title="<?php echo te('حذف الحساب'); ?>"
                                  data-tqa-confirm="سيحذف حساب «<?php echo html_escape($tq_n); ?>» وتسجيلاته. لا رجعة في هذا."
                                  data-tqa-confirm-ok="نعم، احذف"
                                  data-tqa-confirm-tone="danger">
                                <?php echo tq_csrf(); ?>
                                <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        style="color:var(--tq-danger)">
                                    <?php echo tq_icon('trash', 14); ?>
                                    <span class="tqa-sr">حذف <?php echo html_escape($tq_n); ?></span>
                                </button>
                            </form>
                        </div>
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
    <nav class="tqa-pager" aria-label="<?php echo te('صفحات الحسابات'); ?>">
        <span class="tqa-pager__info">
            <?php echo t('المعروض'); ?> <span class="tqa-num"><?php echo count($rows); ?></span>
            <?php echo t('من'); ?> <span class="tqa-num"><?php echo (int) $total; ?></span> <?php echo t('حسابا'); ?>
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
