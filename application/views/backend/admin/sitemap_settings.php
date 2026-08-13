<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * خريطة الموقع.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **حذفت ثلاث كتل مشروطة بـ`addon_status(...)`** — `ebook` و
 *     `course_bundle` و`bootcamp`. مجلد الإضافات فارغ وجدول `addons` بلا
 *     صف، فالشرط كاذب أبدا؛ وداخل كل كتلة استعلام على جدول غير موجود.
 *     وكانت `$this->db->get('ebook', array('is_active', 1))` أصلا خطأ في
 *     التوقيع (الوسيط الثاني في `get()` هو `$limit` لا شرط) — أي أنها لو
 *     نفذت لجلبت الجدول كله بحد صفري.
 * ٢ — **الروابط كانت تطبع بـ`echo` من داخل PHP** في سلاسل مركبة، فلا
 *     يمكن تنسيقها ولا عدها ولا التمرير فيها. صارت وسوما.
 * ٣ — **العدد لم يكن معروضا.** خريطة فيها مئتا رابط تقرأ قائمة لا نهاية
 *     لها ولا يعرف من يقرؤها هل هي عشرة أم ألف.
 */

/** الروابط الثابتة: مخزنة JSON في `settings`. */
$tq_static = array();
if (!empty($sitemap['value'])) {
    $tq_decoded = json_decode($sitemap['value'], true);
    if (is_array($tq_decoded)) {
        foreach ($tq_decoded as $tq_u) $tq_static[] = base_url($tq_u);
    }
}

/** الروابط المتغيرة: تولد من المحتوى، فتتغير كلما أضيف كورس أو مقال. */
$tq_dynamic = array_merge(
    (array) $courses,
    (array) $categories,
    (array) $blogs,
    (array) $blog_categories
);

$tq_lists = array(
    array('روابط ثابتة',  'صفحات الموقع التي لا تتغير — تضبط في إعدادات النظام.', 'layers', $tq_static),
    array('روابط متغيرة', 'تولد من الكورسات والأقسام والمقالات، وتحدث وحدها.',     'refresh', $tq_dynamic),
);
?>

<?php tqa_head('خريطة الموقع',
    'ما يقرؤه محرك البحث ليعرف صفحات الموقع. تولد تلقائيا ولا تحرر من هنا.',
    'layers',
    '<a class="tqa-btn tqa-btn--ghost" href="' . base_url('sitemap.xml') . '" target="_blank" rel="noopener">'
  . tq_icon('external', 16) . ' افتح sitemap.xml</a>'); ?>

<div class="tqa-grid tqa-grid--2">
<?php foreach ($tq_lists as [$tq_title, $tq_sub, $tq_icon, $tq_urls]): ?>
    <section class="tqa-card tqa-card--flush">
        <div class="tqa-card__head">
            <span class="tqa-iconbox" aria-hidden="true"><?php echo tq_icon($tq_icon, 20); ?></span>
            <div>
                <h2><?php echo html_escape($tq_title); ?></h2>
                <span class="tqa-media__sub"><?php echo html_escape($tq_sub); ?></span>
            </div>
            <span class="tqa-badge tqa-badge--muted">
                <span class="tqa-num"><?php echo count($tq_urls); ?></span>
            </span>
        </div>

        <?php if (empty($tq_urls)): ?>
            <p style="padding:var(--tq-space-xl);margin:0;font:var(--tq-type-caption);color:var(--tq-text2)">
                لا روابط في هذه المجموعة بعد.
            </p>
        <?php else: ?>
            <?php /* قائمة طويلة تمرر داخل نفسها: مئتا رابط تحت بعضها تدفع
                     كل ما بعدها خارج الشاشة. */ ?>
            <ol style="list-style:none;margin:0;padding:var(--tq-space-s);
                       max-block-size:52vh;overflow-y:auto">
                <?php foreach ($tq_urls as $tq_i => $tq_u): ?>
                    <li style="display:flex;gap:var(--tq-space-s);align-items:baseline;
                               padding:var(--tq-space-xs) var(--tq-space-m);border-radius:var(--tqa-radius-sm)">
                        <span class="tqa-num" style="color:var(--tq-text3);flex:none;min-inline-size:2.5ch">
                            <?php echo $tq_i + 1; ?>
                        </span>
                        <a class="tq-ltr" dir="ltr" target="_blank" rel="noopener"
                           href="<?php echo html_escape($tq_u); ?>"
                           style="min-inline-size:0;overflow-wrap:anywhere;font:var(--tq-type-caption)">
                            <?php echo html_escape($tq_u); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>
