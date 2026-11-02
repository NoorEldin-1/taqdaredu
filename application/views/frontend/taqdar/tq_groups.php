<?php
/**
 * بوابة الطالب — مجموعاتي (TQ-GROUP).
 *
 * لكل مجموعة جدولها، ولكل حصة حالتها من `display_state()` — المفردات
 * التسع نفسها التي يقرؤها التطبيق — وزر «ادخل» لا يظهر الا حين
 * `can_join`. والدخول يمر بالخادم (`student/group-sessions/<id>/join`)
 * فيسجل الحضور، ولا رابط لقاء عار يطبع في الصفحة.
 */
include 'tq_student_styles.php';

$tq_nav   = 'groups';
$tq_role  = 'student';
$tq_title = t('مجموعاتي');
$tq_sub   = t('حصصك المباشرة مع مجموعتك — الجدول والدخول والتسجيلات');
$tq_icon  = 'users';

$tq_uid = (int) $this->session->userdata('user_id');
$tq_ci  = &get_instance();
$tq_ci->load->model('taqdar_group_model', 'tq_grp');
$tq_groups = $tq_ci->tq_grp->groups_of_student($tq_uid);

$tq_tone = array('neutral' => 'idle', 'info' => 'due', 'success' => 'mastered', 'warning' => 'due', 'danger' => 'late');

include 'portal_open.php';
?>

<?php if ($m = tq_flash('tq_ok')): ?>
    <div class="tq-alert tq-alert--ok tq-section" role="status"><?php echo html_escape($m); ?></div>
<?php endif; ?>
<?php if ($m = tq_flash('tq_error')): ?>
    <div class="tq-alert tq-alert--no tq-section" role="alert"><?php echo html_escape($m); ?></div>
<?php endif; ?>

<?php if (!$tq_groups): ?>
    <div class="tq-card">
        <?php echo tq_s_empty('users', 'sand', t('لم تنضم إلى مجموعة بعد'),
              t('المجموعة جدول حصص مباشرة مع معلمك وزملائك، تدفع مرة وتحضر الجدول كله.'),
              t('تصفح المجموعات'), base_url('groups'), false, 'primary'); ?>
    </div>
<?php endif; ?>

<?php foreach ($tq_groups as $tq_g):
    $tq_rows = $tq_ci->tq_grp->sessions_of((int) $tq_g['id']);
?>
    <div class="tq-card tq-section">
        <div class="tq-row tq-row--between" style="flex-wrap:wrap;gap:var(--tq-space-m)">
            <h2 class="tq-card__title" style="margin:0"><?php echo html_escape($tq_g['title']); ?></h2>
            <?php if ($tq_rows): ?>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/groups/' . (int) $tq_g['id'] . '/ics'); ?>">
                    <?php echo tq_icon('calendar', 16); ?> <?php echo t('أضف الجدول كله إلى تقويمك'); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$tq_rows): ?>
            <p class="tq-caption"><?php echo t('لم تجدول حصص هذه المجموعة بعد. يصلك إشعار حين تجدول.'); ?></p>
        <?php else: ?>
            <div class="tq-table-wrap">
                <table class="tq-table">
                    <caption class="tq-sr"><?php echo t('جدول حصص المجموعة'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo t('الموعد (توقيت مكة)'); ?></th>
                            <th scope="col"><?php echo t('الحصة'); ?></th>
                            <th scope="col"><?php echo t('الحالة'); ?></th>
                            <th scope="col"><span class="tq-sr"><?php echo t('إجراءات'); ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tq_rows as $tq_s):
                        $tq_d = $tq_ci->tq_grp->display_state($tq_s, $tq_uid, 'student');
                    ?>
                        <tr data-tq-now="<?php echo time(); ?>">
                            <td data-label="<?php echo te('الموعد (توقيت مكة)'); ?>"><?php /* الخلية بطاقة دون ٦٤٠: محتواها وعاء واحد يلتف، لا قطع يتجاور فيخرج عن الشاشة */ ?><div class="tq-cellbox">
                                <span class="tq-ltr"><?php echo html_escape(date('Y-m-d H:i', strtotime($tq_s['starts_at']))); ?></span>
                                <?php /* TQ-GROUP gap D9 — توقيت جهازك والعد التنازلي (آلية الحصص الفردية: data-tq-now/starts/local) */ ?>
                                <time class="tq-micro" style="display:block" data-tq-local="<?php echo (int) $tq_d['starts_ts']; ?>"></time>
                                <span class="tq-micro" style="display:block"><?php echo (int) $tq_s['duration_min']; ?> <?php echo t('دقيقة'); ?></span>
                            </div></td>
                            <td data-label="<?php echo te('الحصة'); ?>"><?php echo html_escape(trim((string) $tq_s['title']) !== '' ? $tq_s['title'] : '—'); ?></td>
                            <td data-label="<?php echo te('الحالة'); ?>"><?php /* الخلية بطاقة دون ٦٤٠: محتواها وعاء واحد يلتف، لا قطع يتجاور فيخرج عن الشاشة */ ?><div class="tq-cellbox">
                                <?php echo tq_badge(isset($tq_tone[$tq_d['tone']]) ? $tq_tone[$tq_d['tone']] : 'idle', $tq_d['label']); ?>
                                <?php if ($tq_d['note'] !== ''): ?>
                                    <span class="tq-micro" style="display:block"><?php echo html_escape($tq_d['note']); ?></span>
                                <?php endif; ?>
                                <?php if (!$tq_d['is_over'] && (int) $tq_d['opens_ts'] > time()): ?>
                                    <span class="tq-micro" style="display:block" data-tq-opens="<?php echo (int) $tq_d['opens_ts']; ?>"
                                          data-tq-join="<?php echo base_url('student/group-sessions/' . (int) $tq_s['id'] . '/join'); ?>"
                                          data-tq-join-label="<?php echo te('ادخل الحصة'); ?>">
                                        <?php echo t('يفتح بعد'); ?> <strong data-tq-starts="<?php echo (int) $tq_d['opens_ts']; ?>"></strong>
                                    </span>
                                <?php endif; ?>
                            </div></td>
                            <td data-label="<?php echo te('إجراءات'); ?>"><?php /* الخلية بطاقة دون ٦٤٠: محتواها وعاء واحد يلتف، لا قطع يتجاور فيخرج عن الشاشة */ ?><div class="tq-cellbox tq-cellbox--acts">
                                <?php if ($tq_d['can_join']): ?>
                                    <a class="tq-btn tq-btn--primary tq-btn--sm" href="<?php echo base_url('student/group-sessions/' . (int) $tq_s['id'] . '/join'); ?>"><?php echo t('ادخل الحصة'); ?></a>
                                <?php elseif ($tq_d['state'] === 'recording_available'): ?>
                                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/group-sessions/' . (int) $tq_s['id'] . '/recording'); ?>"><?php echo t('شاهد التسجيل'); ?></a>
                                <?php elseif (!$tq_d['is_over']): ?>
                                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/groups/' . (int) $tq_g['id'] . '/ics?session=' . (int) $tq_s['id']); ?>"><?php echo t('أضف إلى التقويم'); ?></a>
                                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo html_escape(Taqdar_group_model::google_calendar_url($tq_g, $tq_s)); ?>" target="_blank" rel="noopener"><?php echo t('تقويم جوجل'); ?></a>
                                <?php endif; ?>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php include 'portal_close.php'; ?>
