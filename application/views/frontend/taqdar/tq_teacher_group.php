<?php
/**
 * بوابة المعلم — مجموعة واحدة (TQ-GROUP): بياناتها، وجدولها، والاعضاء.
 *
 * الشاشة تعرض ولا تحكم: الحفظ من `save_group()` و`save_session()`،
 * والتأجيل بسببه من `reschedule_session()` (والالغاء بلا بديل للادارة
 * وحدها)، والانتهاء بالحضور من `complete_session()`. والسعر يكتب بالريال
 * ويخزن بالهللات في النموذج — فيعرض هنا بالريال من الهللات.
 */
include 'tq_student_styles.php';

$tq_g    = isset($tq_group) ? $tq_group : null;
$tq_rows = isset($tq_sessions) ? (array) $tq_sessions : array();
$tq_mem  = isset($tq_members) ? (array) $tq_members : array();
$tq_sm   = isset($tq_sum) ? $tq_sum : null;

$tq_nav   = 'groups';
$tq_role  = 'teacher';
$tq_title = $tq_g ? $tq_g['title'] : t('مجموعة جديدة');
$tq_sub   = $tq_sm ? $tq_sm['seats_text'] : t('اكتب بيانات المجموعة، ثم أضف حصصها بعد الحفظ.');
$tq_icon  = 'users';

$tq_uid = (int) $this->session->userdata('user_id');
$tq_ci  = &get_instance();
$tq_ci->load->model('taqdar_group_model', 'tq_grp');

$tq_v = function ($k, $d = '') use ($tq_g) { return $tq_g && isset($tq_g[$k]) && $tq_g[$k] !== null ? $tq_g[$k] : $d; };
$tq_states = array('draft' => t('مسودة'), 'published' => t('منشورة (تمر بمراجعة الإدارة)'), 'closed' => t('مغلقة'));

include 'portal_open.php';
?>

<?php if ($m = tq_flash('tq_ok')): ?>
    <div class="tq-alert tq-alert--ok tq-section" role="status"><?php echo html_escape($m); ?></div>
<?php endif; ?>
<?php if ($m = tq_flash('tq_error')): ?>
    <div class="tq-alert tq-alert--no tq-section" role="alert"><?php echo html_escape($m); ?></div>
<?php endif; ?>

<div class="tq-card tq-section">
    <h2 class="tq-card__title"><?php echo t('بيانات المجموعة'); ?></h2>
    <form method="post" action="<?php echo base_url('teacher/groups/save'); ?>" class="tq-form">
        <?php echo tq_csrf(); ?>
        <?php if ($tq_g): ?><input type="hidden" name="id" value="<?php echo (int) $tq_g['id']; ?>"><?php endif; ?>
        <label class="tq-field"><span><?php echo t('العنوان'); ?></span>
            <input type="text" name="title" required maxlength="190" value="<?php echo html_escape($tq_v('title')); ?>"></label>
        <label class="tq-field"><span><?php echo t('الوصف'); ?></span>
            <textarea name="description" rows="3"><?php echo html_escape($tq_v('description')); ?></textarea></label>
        <label class="tq-field"><span><?php echo t('السعر بالريال'); ?></span>
            <input type="number" name="price" min="0" step="0.01" dir="ltr"
                   value="<?php echo $tq_g ? html_escape(number_format(((int) $tq_g['price']) / 100, 2, '.', '')) : ''; ?>"></label>
        <label class="tq-field"><span><?php echo t('عدد المقاعد (صفر = بلا حد)'); ?></span>
            <input type="number" name="capacity" min="0" dir="ltr" value="<?php echo (int) $tq_v('capacity', 0); ?>"></label>
        <label class="tq-field"><span><?php echo t('الانضمام حتى'); ?></span>
            <input type="datetime-local" name="enroll_until" dir="ltr"
                   value="<?php echo $tq_v('enroll_until') ? html_escape(date('Y-m-d\TH:i', strtotime($tq_v('enroll_until')))) : ''; ?>"></label>
        <label class="tq-field"><span><?php echo t('الحالة'); ?></span>
            <select name="status">
                <?php foreach ($tq_states as $tq_k => $tq_l): ?>
                    <option value="<?php echo $tq_k; ?>" <?php echo $tq_v('status', 'draft') === $tq_k || ($tq_k === 'published' && $tq_v('status') === 'review') ? 'selected' : ''; ?>><?php echo html_escape($tq_l); ?></option>
                <?php endforeach; ?>
            </select></label>
        <?php if ($tq_v('status') === 'review'): ?>
            <p class="tq-caption"><?php echo t('المجموعة بانتظار مراجعة الإدارة، ولا تباع قبل نشرها.'); ?></p>
        <?php endif; ?>
        <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('احفظ'); ?></button>
    </form>
</div>

<?php if ($tq_g): ?>
<div class="tq-card tq-section">
    <h2 class="tq-card__title"><?php echo t('الجدول'); ?></h2>
    <?php if ($tq_rows): ?>
        <div class="tq-table-wrap">
            <table class="tq-table">
                <caption class="tq-sr"><?php echo t('حصص المجموعة'); ?></caption>
                <thead><tr>
                    <th scope="col"><?php echo t('الموعد (توقيت مكة)'); ?></th>
                    <th scope="col"><?php echo t('الحالة'); ?></th>
                    <th scope="col"><?php echo t('إجراءات'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($tq_rows as $tq_s):
                    $tq_d = $tq_ci->tq_grp->display_state($tq_s, $tq_uid, 'teacher');
                ?>
                    <tr>
                        <td data-label="<?php echo te('الموعد (توقيت مكة)'); ?>">
                            <span class="tq-ltr"><?php echo html_escape(date('Y-m-d H:i', strtotime($tq_s['starts_at']))); ?></span>
                            <span class="tq-micro" style="display:block"><?php echo html_escape(trim((string) $tq_s['title'])); ?> · <?php echo (int) $tq_s['duration_min']; ?> <?php echo t('دقيقة'); ?></span>
                            <?php if (trim((string) $tq_s['meet_url']) === '' && !$tq_d['is_over']): ?>
                                <span class="tq-micro" style="display:block;color:var(--tq-red-ink)"><?php echo t('لا رابط لقاء بعد'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php echo te('الحالة'); ?>"><?php echo html_escape($tq_d['label']); ?>
                            <?php if ($tq_d['note'] !== ''): ?><span class="tq-micro" style="display:block"><?php echo html_escape($tq_d['note']); ?></span><?php endif; ?></td>
                        <td data-label="<?php echo te('إجراءات'); ?>">
                            <?php if ($tq_d['can_join']): ?>
                                <a class="tq-btn tq-btn--primary tq-btn--sm" href="<?php echo base_url('teacher/group-sessions/' . (int) $tq_s['id'] . '/join'); ?>"><?php echo t('ابدأ الحصة'); ?></a>
                            <?php endif; ?>

                            <?php if ($tq_s['status'] === 'scheduled'): ?>
                                <details><summary class="tq-micro"><?php echo t('الرابط والعنوان'); ?></summary>
                                    <form method="post" action="<?php echo base_url('teacher/groups/session-save'); ?>">
                                        <?php echo tq_csrf(); ?>
                                        <input type="hidden" name="group_id" value="<?php echo (int) $tq_g['id']; ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) $tq_s['id']; ?>">
                                        <input type="text" name="title" placeholder="<?php echo te('عنوان الحصة'); ?>" value="<?php echo html_escape($tq_s['title']); ?>">
                                        <input type="url" name="meet_url" dir="ltr" placeholder="https://" value="<?php echo html_escape($tq_s['meet_url']); ?>">
                                        <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit"><?php echo t('احفظ'); ?></button>
                                    </form>
                                </details>
                                <details><summary class="tq-micro"><?php echo t('أجل الحصة'); ?></summary>
                                    <form method="post" action="<?php echo base_url('teacher/groups/session-reschedule'); ?>">
                                        <?php echo tq_csrf(); ?>
                                        <input type="hidden" name="session_id" value="<?php echo (int) $tq_s['id']; ?>">
                                        <input type="datetime-local" name="starts_at" required dir="ltr">
                                        <input type="text" name="reason" required placeholder="<?php echo te('سبب التأجيل — يقرؤه كل الأعضاء'); ?>">
                                        <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit"><?php echo t('أجل وبلغ الأعضاء'); ?></button>
                                    </form>
                                    <p class="tq-micro"><?php echo t('الحصة لا تلغى بلا بديل — الإلغاء بلا بديل قرار للإدارة وحدها.'); ?></p>
                                </details>
                            <?php endif; ?>

                            <?php if ($tq_d['can_complete']): ?>
                                <details><summary class="tq-micro"><?php echo t('أعلن انتهاءها والحضور'); ?></summary>
                                    <form method="post" action="<?php echo base_url('teacher/groups/session-complete'); ?>">
                                        <?php echo tq_csrf(); ?>
                                        <input type="hidden" name="session_id" value="<?php echo (int) $tq_s['id']; ?>">
                                        <?php foreach ($tq_mem as $tq_m): ?>
                                            <label class="tq-micro" style="display:block"><?php echo html_escape($tq_m['name']); ?>
                                                <select name="att[<?php echo (int) $tq_m['user_id']; ?>]">
                                                    <option value=""><?php echo t('بحسب دخوله'); ?></option>
                                                    <option value="present"><?php echo t('حاضر'); ?></option>
                                                    <option value="absent"><?php echo t('غائب'); ?></option>
                                                </select></label>
                                        <?php endforeach; ?>
                                        <input type="url" name="recording_url" dir="ltr" placeholder="<?php echo te('رابط التسجيل https://'); ?>">
                                        <label class="tq-micro"><input type="checkbox" name="recording_pending" value="1"> <?php echo t('سأرفع التسجيل لاحقا'); ?></label>
                                        <button class="tq-btn tq-btn--primary tq-btn--sm" type="submit"><?php echo t('أعلن الانتهاء'); ?></button>
                                    </form>
                                </details>
                            <?php endif; ?>

                            <?php if ($tq_s['status'] === 'completed' && trim((string) $tq_s['recording_url']) === ''): ?>
                                <form method="post" action="<?php echo base_url('teacher/groups/session-recording'); ?>">
                                    <?php echo tq_csrf(); ?>
                                    <input type="hidden" name="session_id" value="<?php echo (int) $tq_s['id']; ?>">
                                    <input type="url" name="recording_url" required dir="ltr" placeholder="<?php echo te('رابط التسجيل https://'); ?>">
                                    <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit"><?php echo t('ارفع التسجيل'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="tq-caption"><?php echo t('لا حصص بعد. أضف أول حصة — العضوية تباع بجدولها.'); ?></p>
    <?php endif; ?>

    <h3 class="tq-card__title"><?php echo t('أضف حصة'); ?></h3>
    <form method="post" action="<?php echo base_url('teacher/groups/session-save'); ?>">
        <?php echo tq_csrf(); ?>
        <input type="hidden" name="group_id" value="<?php echo (int) $tq_g['id']; ?>">
        <label class="tq-field"><span><?php echo t('الموعد (توقيت مكة)'); ?></span>
            <input type="datetime-local" name="starts_at" required dir="ltr"></label>
        <label class="tq-field"><span><?php echo t('المدة بالدقائق'); ?></span>
            <input type="number" name="duration_min" min="15" max="300" value="60" dir="ltr"></label>
        <label class="tq-field"><span><?php echo t('العنوان'); ?></span>
            <input type="text" name="title" maxlength="190"></label>
        <label class="tq-field"><span><?php echo t('رابط اللقاء'); ?></span>
            <input type="url" name="meet_url" dir="ltr" placeholder="https://"></label>
        <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('أضف الحصة'); ?></button>
    </form>
</div>

<div class="tq-card">
    <h2 class="tq-card__title"><?php echo t('الأعضاء'); ?> (<?php echo count($tq_mem); ?>)</h2>
    <?php if ($tq_mem): ?>
        <ul class="tq-caption">
            <?php foreach ($tq_mem as $tq_m): ?><li><?php echo html_escape($tq_m['name']); ?></li><?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="tq-caption"><?php echo t('لا أعضاء بعد.'); ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include 'portal_close.php'; ?>
