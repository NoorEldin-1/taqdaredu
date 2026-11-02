<?php
/**
 * TQ-GROUP gap 3.2 — تسجيل حصة جماعية.
 *
 * يفتح من مسار حارس (`student/group-sessions/<id>/recording`) لا من رابط عار:
 * الحكم في `Taqdar_group_model::recording_for()` — العضو الساري او ولي امره.
 * والعرض بـ`tqs_video_embed()` القائمة، فلا مشغل جديد.
 *
 * المدخلات: `$tq_rec` (صف الحصة + group + when_text) · `$tq_rec_role` · `$tq_back`.
 */
$tq_role  = $tq_rec_role === 'parent' ? 'parent' : 'student';
$tq_nav   = $tq_role === 'parent' ? 'children' : 'groups';
$tq_title = t('تسجيل الحصة');
$tq_sub   = (string) ($tq_rec['group']['title'] ?? '');
$tq_icon  = 'video';

include 'portal_open.php';
$tq_embed = tqs_video_embed('', (string) $tq_rec['recording_url'], $tq_title);
?>
<section class="tq-section" aria-labelledby="tq-rec-h">
    <div class="tq-sectionhead">
        <h2 id="tq-rec-h"><?php echo t('تسجيل الحصة'); ?></h2>
        <a class="tq-caption" href="<?php echo base_url($tq_back); ?>"><?php echo t('رجوع إلى الحصص'); ?></a>
    </div>
    <div class="tq-card">
        <p class="tq-micro" style="margin:0 0 var(--tq-space-s)"><?php echo tq_iso(html_escape((string) $tq_rec['when_text'])); ?></p>
        <?php if ($tq_embed !== ''): ?>
            <div class="tq-player__frame" style="position:relative;aspect-ratio:16/9;background:var(--tq-navy)">
                <?php echo str_replace('<iframe ', '<iframe style="position:absolute;inset:0;inline-size:100%;block-size:100%;border:0" ', $tq_embed); ?>
            </div>
        <?php else: ?>
            <p class="tq-micro" style="margin:0"><?php echo t('تعذر عرض هذا التسجيل هنا. راسل المعلم.'); ?></p>
        <?php endif; ?>
    </div>
</section>
<?php include 'portal_close.php'; ?>
