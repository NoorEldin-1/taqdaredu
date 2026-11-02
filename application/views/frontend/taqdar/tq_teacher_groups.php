<?php
/**
 * بوابة المعلم — مجموعاتي (TQ-GROUP).
 *
 * قائمة مجموعاته بحالها ومقاعدها واقرب حصة. والتحرير في
 * `tq_teacher_group.php`. والسعر والنسبة قرار ادارة يقرؤه صاحبه هنا.
 */
include 'tq_student_styles.php';

$tq_nav   = 'groups';
$tq_role  = 'teacher';
$tq_title = t('مجموعاتي');
$tq_sub   = t('مجموعاتك المباشرة — جدول ومقاعد وحضور جماعي.');
$tq_icon  = 'users';

$tq_uid = (int) $this->session->userdata('user_id');
$tq_ci  = &get_instance();
$tq_ci->load->model('taqdar_group_model', 'tq_grp');
$tq_rows = array();
foreach ($tq_ci->tq_grp->for_teacher($tq_uid) as $tq_g) $tq_rows[] = $tq_ci->tq_grp->summary_of($tq_g);

$tq_badge = array(
    'published' => array('mastered', t('منشورة')),
    'review'    => array('due',      t('بانتظار المراجعة')),
    'draft'     => array('idle',     t('مسودة')),
    'closed'    => array('late',     t('مغلقة')),
);

include 'portal_open.php';
?>

<?php if ($m = tq_flash('tq_ok')): ?>
    <div class="tq-alert tq-alert--ok tq-section" role="status"><?php echo html_escape($m); ?></div>
<?php endif; ?>
<?php if ($m = tq_flash('tq_error')): ?>
    <div class="tq-alert tq-alert--no tq-section" role="alert"><?php echo html_escape($m); ?></div>
<?php endif; ?>

<div class="tq-card tq-card--panel tq-section">
    <div class="tq-row tq-row--between" style="flex-wrap:wrap;gap:var(--tq-space-m)">
        <p class="tq-caption" style="margin:0"><?php echo t('أنشئ مجموعة واكتب جدولها، ثم أعلنها «منشورة» فتصل إلى مراجعة الإدارة.'); ?></p>
        <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/groups/new'); ?>">
            <?php echo tq_icon('plus', 16); ?> <?php echo t('مجموعة جديدة'); ?>
        </a>
    </div>
</div>

<?php if (!$tq_rows): ?>
    <div class="tq-card">
        <?php echo tq_s_empty('users', 'sand', t('لا مجموعات بعد'),
              t('المجموعة جدول حصص مباشرة لعدة طلاب معا، يدفعون مرة ويحضرون الجدول كله.'),
              t('مجموعة جديدة'), base_url('teacher/groups/new'), false, 'primary'); ?>
    </div>
<?php else: ?>
    <div class="tq-card">
        <div class="tq-table-wrap">
            <table class="tq-table">
                <caption class="tq-sr"><?php echo t('مجموعاتك'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php echo t('المجموعة'); ?></th>
                        <th scope="col"><?php echo t('الحالة'); ?></th>
                        <th scope="col"><?php echo t('الحصص'); ?></th>
                        <th scope="col"><?php echo t('المقاعد'); ?></th>
                        <th scope="col"><?php echo t('السعر'); ?></th>
                        <th scope="col"><span class="tq-sr"><?php echo t('إجراءات'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tq_rows as $tq_s):
                    list($tq_k, $tq_l) = isset($tq_badge[$tq_s['status']]) ? $tq_badge[$tq_s['status']] : array('idle', $tq_s['status']);
                ?>
                    <tr>
                        <td data-label="<?php echo te('المجموعة'); ?>"><span class="tq-strong"><?php echo html_escape($tq_s['title']); ?></span></td>
                        <td data-label="<?php echo te('الحالة'); ?>"><?php echo tq_badge($tq_k, $tq_l); ?></td>
                        <td data-label="<?php echo te('الحصص'); ?>">
                            <?php echo tq_num((int) $tq_s['sessions_total'], 'tq-num--sm'); ?>
                            <?php if ($tq_s['next_starts_at']): ?>
                                <span class="tq-micro tq-ltr" style="display:block"><?php echo html_escape(date('Y-m-d H:i', strtotime($tq_s['next_starts_at']))); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php echo te('المقاعد'); ?>"><?php echo html_escape($tq_s['seats_text']); ?></td>
                        <td data-label="<?php echo te('السعر'); ?>">
                            <span class="tq-ltr"><?php echo number_format(((int) $tq_s['price_halalas']) / 100, 2); ?></span>
                            <span class="tq-micro"><?php echo t('ر.س'); ?></span>
                        </td>
                        <td data-label="<?php echo te('إجراءات'); ?>">
                            <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('teacher/groups/' . (int) $tq_s['id']); ?>"><?php echo t('افتح'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include 'portal_close.php'; ?>
