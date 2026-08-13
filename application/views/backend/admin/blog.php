<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * المدونة.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **قائمة النقاط الثلاث `dropright` صارت أزرارا ظاهرة.** «right» في
 *     صفحة عربية جهة خاطئة، والقائمة تفتح داخل الجدول فتقص عند حافته.
 * ٢ — **التفعيل والحذف صارا نموذجي POST بتوكن** بدل رابطي GET.
 * ٣ — **الكاتب والقسم كانا استعلامين لكل صف** — `get_all_user()` و
 *     `get_blog_categories()` داخل الحلقة. جمعا في استعلامين اثنين.
 * ٤ — **الصورة كانت `height="50" width="50"` على صورة قد لا تكون
 *     مربعة**، فتخرج ممطوطة. صارت `object-fit: cover` من `.tqa-avatar`.
 */
$tq_blogs = $blogs->result_array();

/* الكتاب والأقسام مرة واحدة لا مرة لكل صف. */
$tq_uids = array_unique(array_map(function ($b) { return (int) $b['user_id']; }, $tq_blogs));
$tq_cids = array_unique(array_map(function ($b) { return (int) $b['blog_category_id']; }, $tq_blogs));

$tq_authors = array();
if ($tq_uids) {
    foreach ($this->db->select('id, first_name, last_name, email')
                      ->where_in('id', $tq_uids)->get('users')->result_array() as $tq_u) {
        $tq_authors[(int) $tq_u['id']] = $tq_u;
    }
}

$tq_cats = array();
if ($tq_cids) {
    foreach ($this->db->select('blog_category_id, title')
                      ->where_in('blog_category_id', $tq_cids)->get('blog_category')->result_array() as $tq_c) {
        $tq_cats[(int) $tq_c['blog_category_id']] = $tq_c['title'];
    }
}

$tq_tools = '<a class="tqa-btn tqa-btn--primary" href="' . site_url('admin/add_blog') . '">'
          . tq_icon('plus', 17) . ' مقال جديد</a>'
          . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/blog_category') . '">'
          . tq_icon('grid', 16) . ' أقسام المدونة</a>';
?>

<?php tqa_head('المدونة', 'المقال المعطل يبقى في القاعدة ولا يظهر في الموقع العام.', 'file', $tq_tools); ?>

<div class="tqa-card tqa-card--flush">
<?php if (empty($tq_blogs)): ?>

    <?php tqa_empty('لا مقالات بعد',
        'المدونة صفحة عامة في الموقع؛ وما دامت فارغة تعرض حالة فراغ للزائر.',
        'اكتب أول مقال', site_url('admin/add_blog'), 'file'); ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <caption class="tqa-sr">مقالات المدونة: الكاتب والقسم والحالة</caption>
            <thead>
                <tr>
                    <th style="inline-size:60px">#</th>
                    <th>المقال</th>
                    <th>الكاتب</th>
                    <th>القسم</th>
                    <th>الحالة</th>
                    <th style="inline-size:230px"><span class="tqa-sr">إجراءات</span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tq_blogs as $tq_k => $tq_b):
                $tq_id  = (int) $tq_b['blog_id'];
                $tq_on  = (int) $tq_b['status'] === 1;
                $tq_a   = $tq_authors[(int) $tq_b['user_id']] ?? null;
                $tq_an  = $tq_a ? trim($tq_a['first_name'] . ' ' . $tq_a['last_name']) : '';
                $tq_url = site_url('blog/details/' . rawurlencode(slugify($tq_b['title'])) . '/' . $tq_id);
            ?>
                <tr>
                    <td data-label="#"><span class="tqa-num"><?php echo $tq_k + 1; ?></span></td>

                    <td data-label="المقال">
                        <a class="tqa-media__title" href="<?php echo site_url('admin/edit_blog/' . $tq_id); ?>">
                            <?php echo html_escape($tq_b['title']); ?>
                        </a>
                        <span class="tqa-media__sub">
                            <span class="tq-ltr" dir="ltr"><?php echo date('Y-m-d', (int) $tq_b['added_date']); ?></span>
                        </span>
                    </td>

                    <td data-label="الكاتب">
                        <?php if ($tq_a): ?>
                            <span class="tqa-media">
                                <img class="tqa-avatar tqa-avatar--sm" alt="" width="30" height="30" loading="lazy"
                                     src="<?php echo html_escape($this->user_model->get_user_image_url($tq_a['id'])); ?>">
                                <span class="tqa-media__body">
                                    <span class="tqa-media__title"><?php echo html_escape($tq_an !== '' ? $tq_an : $tq_a['email']); ?></span>
                                    <span class="tqa-media__sub tq-ltr" dir="ltr"><?php echo html_escape($tq_a['email']); ?></span>
                                </span>
                            </span>
                        <?php else: ?>
                            <span class="tqa-dim">حساب محذوف</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="القسم">
                        <?php $tq_cn = $tq_cats[(int) $tq_b['blog_category_id']] ?? ''; ?>
                        <?php if ($tq_cn !== ''): ?>
                            <span class="tqa-badge tqa-badge--muted"><?php echo html_escape($tq_cn); ?></span>
                        <?php else: ?>
                            <span class="tqa-dim">بلا قسم</span>
                        <?php endif; ?>
                    </td>

                    <td data-label="الحالة">
                        <span class="tqa-badge tqa-badge--<?php echo $tq_on ? 'ok' : 'muted'; ?>">
                            <?php echo $tq_on ? 'منشور' : 'معطل'; ?>
                        </span>
                    </td>

                    <td data-label="إجراءات">
                        <div class="tqa-rowacts">
                            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                               href="<?php echo site_url('admin/edit_blog/' . $tq_id); ?>">
                                <?php echo tq_icon('edit', 14); ?> تحرير
                            </a>

                            <?php if ($tq_on): ?>
                                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="<?php echo $tq_url; ?>"
                                   target="_blank" rel="noopener" title="اقرأه في الموقع">
                                    <?php echo tq_icon('external', 14); ?>
                                    <span class="tqa-sr">اقرأه في الموقع</span>
                                </a>
                            <?php endif; ?>

                            <form method="post" action="<?php echo site_url('admin/blog/status/' . $tq_id); ?>">
                                <?php echo tq_csrf(); ?>
                                <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm">
                                    <?php echo tq_icon($tq_on ? 'eye' : 'check', 14); ?>
                                    <?php echo $tq_on ? 'عطل' : 'انشر'; ?>
                                </button>
                            </form>

                            <form method="post" action="<?php echo site_url('admin/blog/delete/' . $tq_id); ?>"
                                  data-tqa-confirm-title="حذف المقال"
                                  data-tqa-confirm="سيحذف «<?php echo html_escape($tq_b['title']); ?>» نهائيا."
                                  data-tqa-confirm-ok="نعم، احذف"
                                  data-tqa-confirm-tone="danger">
                                <?php echo tq_csrf(); ?>
                                <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">
                                    <?php echo tq_icon('trash', 14); ?>
                                    <span class="tqa-sr">حذف <?php echo html_escape($tq_b['title']); ?></span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p style="padding:var(--tq-space-m) var(--tq-space-xl);margin:0;border-block-start:1px solid var(--tq-line);
              font:var(--tq-type-caption);color:var(--tq-text2)">
        <span class="tqa-num"><?php echo count($tq_blogs); ?></span> مقالا.
    </p>

<?php endif; ?>
</div>
