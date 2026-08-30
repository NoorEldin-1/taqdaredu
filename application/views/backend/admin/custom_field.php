<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الحقول المخصصة للكورس — أقسام إضافية تعرض في صفحته العامة.
 *
 * TQ-BS5-ACCORDION — كانت الأقسام مطوية بـ`data-bs-toggle="collapse"`،
 * وهي **صيغة Bootstrap 5**؛ واللوحة تحمل Bootstrap 4 الذي يقرأ
 * `data-toggle` بلا `bs`. فلم يكن أي قسم يفتح إطلاقا: تضغط عنوانه فلا
 * يحدث شيء، ولا سبيل إلى رؤية ما فيه ولا تحريره. (العطل نفسه كان في
 * [seo_settings.php].)
 *
 * وثلاثة أعطال أخرى:
 *
 * ١ — **مئتا سطر `<style>` مضمنة** في القالب، تعيد تعريف `.card` و
 *     `.accordion-button` و`.list-group-item` بألوان مكتوبة مباشرة
 *     (`#1982FE` · `#ff4625`) خارج هوية المنصة كلها.
 * ٢ — **أزرار التحرير والحذف تظهر بالتمرير وحده** (`.eControll` بـ
 *     `opacity:0` و`:hover`) — لا تصل من جوال ولا بلوحة مفاتيح.
 * ٣ — **`$item['description']` و`$item['title']` تطبعان خامتين** — نص
 *     يكتبه المسؤول، وهو مقصود في الوصف؛ لكن العنوان يهرب الآن.
 */
$tq_fields = $this->db->where('course_id', (int) $course_id)
                      ->order_by('sorting', 'ASC')
                      ->get('custom_fields')->result_array();

$tq_kinds = array(
    'image'   => array(t('صور بعناوين'), 'image'),
    'text'    => array(t('نص مفصل'),     'file-text'),
    'video'   => array(t('فيديو'),       'play'),
    'faq'     => array(t('أسئلة شائعة'), 'help'),
    'gallery' => array(t('معرض صور'),    'grid'),
);

/** معرف فيديو يوتيوب من أي صيغة رابط. */
$tq_ytid = function ($url) {
    foreach (array('~youtu\.be/([^\?&]+)~', '~v=([^\?&]+)~', '~/embed/([^\?&]+)~', '~/shorts/([^\?&]+)~') as $tq_re) {
        if (preg_match($tq_re, (string) $url, $tq_m)) return $tq_m[1];
    }
    return '';
};
?>

<div class="tqa-toolbar">
    <button type="button" class="tqa-btn tqa-btn--primary"
            onclick="showLargeModal('<?php echo site_url('modal/popup/custom_field_add/' . (int) $course_id); ?>', 'إضافة قسم مخصص')">
        <?php echo tq_icon('plus', 16); ?> <?php echo t('أضف قسما'); ?>
    </button>

    <?php if (count($tq_fields) > 1): ?>
        <button type="button" class="tqa-btn tqa-btn--ghost"
                onclick="showLargeModal('<?php echo site_url('modal/popup/custom_field_section_sorting/' . (int) $course_id); ?>', 'ترتيب الأقسام')">
            <?php echo tq_icon('layers', 16); ?> <?php echo t('رتب الأقسام'); ?>
        </button>
    <?php endif; ?>
</div>

<?php if (empty($tq_fields)): ?>

    <div class="tqa-card tqa-card--flush">
        <?php tqa_empty(
            t('لا أقسام مخصصة في هذا الكورس'),
            t('القسم المخصص كتلة إضافية تعرض في صفحة الكورس العامة — صور أو نص أو فيديو أو أسئلة شائعة أو معرض.'),
            '', '', 'grid'
        ); ?>
    </div>

<?php else: ?>

<div class="tqa-stack" style="max-inline-size:900px">
<?php foreach ($tq_fields as $tq_f):
    $tq_id    = (int) $tq_f['id'];
    $tq_type  = (string) $tq_f['custom_type'];
    $tq_items = json_decode((string) $tq_f['custom_field'], true)['data'] ?? array();
    if (!is_array($tq_items)) $tq_items = array();
    [$tq_kindname, $tq_ic] = $tq_kinds[$tq_type] ?? array($tq_type, 'grid');
?>
    <details class="tqa-card">
        <summary class="tqa-row tqa-row--between">
            <span class="tqa-row">
                <span class="tqa-iconbox tqa-lilac" aria-hidden="true" style="inline-size:36px;block-size:36px">
                    <?php echo tq_icon($tq_ic, 18); ?>
                </span>
                <span>
                    <strong style="color:var(--tq-navy);font:var(--tq-type-bodyStrong)">
                        <?php echo html_escape($tq_f['custom_title']); ?>
                    </strong>
                    <span class="tqa-media__sub">
                        <?php echo html_escape($tq_kindname); ?> ·
                        <span class="tqa-num"><?php echo count($tq_items); ?></span> <?php echo t('عنصرا'); ?>
                    </span>
                </span>
            </span>
        </summary>

        <div style="margin-block-start:var(--tq-space-l);border-block-start:1px solid var(--tq-line);
                    padding-block-start:var(--tq-space-l)">

            <?php /* أدوات القسم — ظاهرة دائما لا بالتمرير. */ ?>
            <div class="tqa-rowacts tqa-section">
                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                        onclick="showLargeModal('<?php echo site_url('modal/popup/custom_field_section_edit/' . $tq_id); ?>', 'تعديل القسم')">
                    <?php echo tq_icon('edit', 14); ?> <?php echo t('تعديل القسم'); ?>
                </button>

                <form method="post" action="<?php echo site_url('admin/custom_field_section_delete/' . $tq_id); ?>"
                      data-tqa-confirm-title="<?php echo te('حذف القسم المخصص'); ?>"
                      data-tqa-confirm="<?php echo te('سيحذف «____» وكل عناصره.', array(html_escape($tq_f['custom_title']))); ?>"
                      data-tqa-confirm-ok="<?php echo te('نعم، احذف'); ?>"
                      data-tqa-confirm-tone="danger">
                    <?php echo tq_csrf(); ?>
                    <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">
                        <?php echo tq_icon('trash', 14); ?> <?php echo t('حذف القسم'); ?>
                    </button>
                </form>
            </div>

            <?php if (empty($tq_items)): ?>
                <p style="margin:0;font:var(--tq-type-caption);color:var(--tq-text2)">
                    <?php echo t('لا عناصر في هذا القسم بعد.'); ?>
                </p>
            <?php else: ?>

                <div class="<?php echo in_array($tq_type, array('image', 'gallery'), true) ? 'tqa-grid tqa-grid--2' : 'tqa-stack'; ?>">
                <?php foreach ($tq_items as $tq_it):
                    $tq_file = 'uploads/custom_fields/' . ($tq_it['file'] ?? '');
                    $tq_src  = (!empty($tq_it['file']) && is_file(FCPATH . $tq_file)) ? base_url($tq_file) : '';

                    /* شريط الأدوات لكل عنصر — يكتب مرة ويستعمل في الأنواع الخمسة. */
                    ob_start(); ?>
                    <div class="tqa-rowacts" style="margin-block-start:var(--tq-space-s)">
                        <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                onclick="showLargeModal('<?php echo site_url('modal/popup/custom_field_edit/' . $tq_id . '/' . html_escape($tq_it['id'])); ?>', 'تعديل العنصر')">
                            <?php echo tq_icon('edit', 14); ?> <?php echo t('تعديل'); ?>
                        </button>

                        <form method="post"
                              action="<?php echo site_url('admin/custom_field_item_delete/' . $tq_id . '/' . html_escape($tq_it['id'])); ?>"
                              data-tqa-confirm-title="<?php echo te('حذف العنصر'); ?>"
                              data-tqa-confirm="<?php echo te('لا رجعة في هذا الحذف.'); ?>"
                              data-tqa-confirm-ok="<?php echo te('نعم، احذف'); ?>"
                              data-tqa-confirm-tone="danger">
                            <?php echo tq_csrf(); ?>
                            <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">
                                <?php echo tq_icon('trash', 14); ?>
                                <span class="tqa-sr"><?php echo t('حذف'); ?></span>
                            </button>
                        </form>
                    </div>
                    <?php $tq_acts = ob_get_clean(); ?>

                    <?php if ($tq_type === 'video'): $tq_vid = $tq_ytid($tq_it['file'] ?? ''); ?>
                        <div class="tqa-card" style="box-shadow:none;border-style:dashed">
                            <?php if ($tq_vid !== ''): ?>
                                <div style="position:relative;padding-block-end:56.25%;border-radius:var(--tqa-radius-sm);overflow:hidden">
                                    <iframe style="position:absolute;inset:0;inline-size:100%;block-size:100%;border:0"
                                            src="https://www.youtube.com/embed/<?php echo html_escape($tq_vid); ?>"
                                            title="<?php echo html_escape($tq_it['title'] ?? t('فيديو')); ?>"
                                            allowfullscreen loading="lazy"></iframe>
                                </div>
                            <?php else: ?>
                                <p class="tqa-note tqa-note--warn" style="margin:0">
                                    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
                                    <span><?php echo t('رابط يوتيوب غير صالح — لا يعرض شيء للطالب.'); ?></span>
                                </p>
                            <?php endif; ?>
                            <?php echo $tq_acts; ?>
                        </div>

                    <?php elseif ($tq_type === 'gallery'): ?>
                        <div class="tqa-card" style="box-shadow:none;border-style:dashed">
                            <?php if ($tq_src !== ''): ?>
                                <img src="<?php echo html_escape($tq_src); ?>" alt="" loading="lazy"
                                     style="inline-size:100%;block-size:200px;object-fit:cover;border-radius:var(--tqa-radius-sm)">
                            <?php else: ?>
                                <span class="tqa-dim"><?php echo t('لا صورة'); ?></span>
                            <?php endif; ?>
                            <?php echo $tq_acts; ?>
                        </div>

                    <?php elseif ($tq_type === 'image'): ?>
                        <div class="tqa-card" style="box-shadow:none;border-style:dashed">
                            <?php if ($tq_src !== ''): ?>
                                <img src="<?php echo html_escape($tq_src); ?>" alt="" loading="lazy"
                                     style="inline-size:100%;block-size:140px;object-fit:cover;
                                            border-radius:var(--tqa-radius-sm);margin-block-end:var(--tq-space-m)">
                            <?php endif; ?>
                            <strong style="color:var(--tq-navy);display:block">
                                <?php echo html_escape($tq_it['title'] ?? ''); ?>
                            </strong>
                            <p style="margin:var(--tq-space-xs) 0 0;font:var(--tq-type-caption);color:var(--tq-text2)">
                                <?php echo html_escape($tq_it['description'] ?? ''); ?>
                            </p>
                            <?php echo $tq_acts; ?>
                        </div>

                    <?php else: /* text · faq */ ?>
                        <div class="tqa-card" style="box-shadow:none;border-style:dashed">
                            <?php if (!empty($tq_it['title'])): ?>
                                <strong style="color:var(--tq-navy);display:block;margin-block-end:var(--tq-space-xs)">
                                    <?php echo html_escape($tq_it['title']); ?>
                                </strong>
                            <?php endif; ?>
                            <div style="font:var(--tq-type-caption);color:var(--tq-text);overflow-wrap:anywhere">
                                <?php echo $tq_it['description'] ?? ''; ?>
                            </div>
                            <?php echo $tq_acts; ?>
                        </div>
                    <?php endif; ?>

                <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>
    </details>
<?php endforeach; ?>
</div>

<?php endif; ?>
