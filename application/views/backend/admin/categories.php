<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أقسام الكورسات.
 *
 * أعيدت كتابتها بهيكل `tqa-*` — كانت آخر شاشة في اللوحة تعرض عطلها
 * للعين مباشرة، وأعطالها ثلاثة لا واحد:
 *
 * ١ — **صورة مكسورة في كل بطاقة.** كانت تكتب
 *     `<img src=".../category_thumbnails/{thumbnail}">` بلا فحص، وعمود
 *     `thumbnail` في هذه القاعدة فارغ أو يحمل اسم ملف غير موجود — والموجود
 *     في المجلد ملف واحد اسمه `category-thumbnail.png`. فكانت كل بطاقة
 *     تعرض أيقونة الصورة المكسورة ونص `alt` الإنجليزي «Card image cap»
 *     في أعلاها. صار الغلاف يفحص الملف، ويسقط إلى مربع بحرف القسم الأول
 *     حين لا صورة — وهو ما يفعله بقية النظام في الصور الغائبة.
 *
 * ٢ — **الإجراءات تظهر بالتمرير وحده.** `style="display:none"` وjQuery
 *     `mouseenter`: لا تعديل ولا حذف من جوال أو لوح إطلاقا (لا `mouseenter`
 *     على شاشة لمس)، ولا وصول إليها بلوحة المفاتيح، ولا شيء يدل على
 *     وجودها. الأزرار الآن ظاهرة دائما.
 *
 * ٣ — **الحذف برابط GET.** `<a href="admin/categories/delete/5">` ينفذ
 *     بمجرد جلبه — من زاحف، أو من استباق التحميل في المتصفح. صار نموذج
 *     POST بتوكن، وبنافذة تأكيد قبله.
 */

$tq_cats = $categories->result_array();

/** غلاف القسم: ملف موجود فعلا، وإلا مربع بحرفه الأول. */
$tq_cover = function ($file) {
    $file = trim((string) $file);
    if ($file === '') return '';
    $rel = 'uploads/thumbnails/category_thumbnails/' . $file;
    return is_file(FCPATH . $rel) ? base_url($rel) : '';
};

$tq_tools = '<a class="tqa-btn tqa-btn--primary" href="' . site_url('admin/category_form/add_category') . '">'
          . tq_icon('plus', 17) . ' ' . t('إضافة قسم') . '</a>';
?>

<?php tqa_head(t('أقسام الكورسات'), t('القسم وعاء والقسم الفرعي ما فيه — والكورس يسند إلى الفرعي لا إلى الأب.'), 'grid', $tq_tools); ?>

<?php if (empty($tq_cats)): ?>

    <div class="tqa-card tqa-card--flush">
        <?php tqa_empty(
            t('لا أقسام بعد'),
            t('القسم هو ما يصنف به الكورس في الموقع العام. ابدأ بقسم أب، ثم أضف تحته أقساما فرعية.'),
            t('إضافة أول قسم'),
            site_url('admin/category_form/add_category'),
            'grid'
        ); ?>
    </div>

<?php else: ?>

    <div class="tqa-grid tqa-grid--3">
        <?php foreach ($tq_cats as $tq_c):
            if ((int) $tq_c['parent'] > 0) continue;
            $tq_subs  = $this->crud_model->get_sub_categories($tq_c['id']);
            $tq_img   = $tq_cover($tq_c['thumbnail']);
            $tq_first = mb_substr(trim((string) $tq_c['name']), 0, 1, 'UTF-8');
        ?>
            <article class="tqa-item">

                <div class="tqa-item__head">
                    <?php if ($tq_img !== ''): ?>
                        <img class="tqa-thumb" src="<?php echo html_escape($tq_img); ?>" alt=""
                             width="56" height="40" loading="lazy">
                    <?php else: ?>
                        <span class="tqa-thumb tqa-thumb--none" aria-hidden="true"><?php echo html_escape($tq_first); ?></span>
                    <?php endif; ?>

                    <div style="min-inline-size:0">
                        <h2 class="tqa-item__title"><?php echo html_escape($tq_c['name']); ?></h2>
                        <span class="tqa-item__sub">
                            <span class="tqa-num"><?php echo count($tq_subs); ?></span> <?php echo t('قسما فرعيا'); ?>
                        </span>
                    </div>
                </div>

                <?php if ($tq_subs): ?>
                    <ul class="tqa-item__list">
                        <?php foreach ($tq_subs as $tq_s): ?>
                            <li>
                                <span style="flex:1;min-inline-size:0"><?php echo html_escape($tq_s['name']); ?></span>

                                <span class="tqa-rowacts">
                                    <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                       href="<?php echo site_url('admin/category_form/edit_category/' . (int) $tq_s['id']); ?>"
                                       title="<?php echo te('تعديل ____', array(html_escape($tq_s['name']))); ?>">
                                        <?php echo tq_icon('edit', 14); ?>
                                        <span class="tqa-sr"><?php echo t('تعديل'); ?> <?php echo html_escape($tq_s['name']); ?></span>
                                    </a>

                                    <form method="post" action="<?php echo site_url('admin/categories/delete/' . (int) $tq_s['id']); ?>"
                                          data-tqa-confirm-title="<?php echo te('حذف القسم الفرعي'); ?>"
                                          data-tqa-confirm="<?php echo te('سيحذف «____». والكورسات المصنفة تحته تبقى بلا تصنيف.', array(html_escape($tq_s['name']))); ?>"
                                          data-tqa-confirm-ok="<?php echo te('نعم، احذف'); ?>"
                                          data-tqa-confirm-tone="danger">
                                        <?php echo tq_csrf(); ?>
                                        <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">
                                            <?php echo tq_icon('trash', 14); ?>
                                            <span class="tqa-sr"><?php echo t('حذف'); ?> <?php echo html_escape($tq_s['name']); ?></span>
                                        </button>
                                    </form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <ul class="tqa-item__list">
                        <li style="color:var(--tq-text2)"><?php echo t('لا أقسام فرعية تحته بعد'); ?></li>
                    </ul>
                <?php endif; ?>

                <div class="tqa-item__foot">
                    <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                       href="<?php echo site_url('admin/category_form/edit_category/' . (int) $tq_c['id']); ?>">
                        <?php echo tq_icon('edit', 15); ?> <?php echo t('تعديل'); ?>
                    </a>

                    <form method="post" style="margin-inline-start:auto"
                          action="<?php echo site_url('admin/categories/delete/' . (int) $tq_c['id']); ?>"
                          data-tqa-confirm-title="<?php echo te('حذف القسم'); ?>"
                          data-tqa-confirm="<?php echo te('سيحذف «____» وكل أقسامه الفرعية. لا رجعة في هذا.', array(html_escape($tq_c['name']))); ?>"
                          data-tqa-confirm-ok="<?php echo te('نعم، احذف'); ?>"
                          data-tqa-confirm-tone="danger">
                        <?php echo tq_csrf(); ?>
                        <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">
                            <?php echo tq_icon('trash', 15); ?> <?php echo t('حذف'); ?>
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
