<?php
/**
 * الترويسة العلوية للبوابة.
 * التحية يمينا · البحث وسطا يفتح بـ ⌘K · الإشعارات والرسائل · الحساب.
 * شريحة ⌘K تظهر في العربية أيضا — الاختصار ليس ميزة إنجليزية.
 */
$tq_uid   = $this->session->userdata('user_id');
$tq_user  = $tq_uid ? $this->user_model->get_all_user($tq_uid)->row_array() : null;
$tq_name  = $tq_user ? trim($tq_user['first_name'] . ' ' . $tq_user['last_name']) : '';
$tq_photo = $tq_user && !empty($tq_user['image'])
    ? base_url('uploads/user_image/' . $tq_user['image'])
    : tq_asset('brand/icon.png');

$tq_role_label = ['student' => 'طالب', 'teacher' => 'معلم', 'parent' => 'ولي أمر'][$tq_role] ?? 'طالب';

$tq_hour  = (int) date('G');
$tq_greet = $tq_hour < 12 ? 'صباح الخير' : ($tq_hour < 17 ? 'مساء الخير' : 'مساء الخير');
?>
<header class="tq-topbar">
    <button class="tq-iconbtn tq-rail-toggle" type="button" data-tq-rail-toggle aria-label="فتح القائمة" aria-expanded="false">
        <?php echo tq_icon('menu'); ?>
    </button>

    <div class="tq-topbar__greet">
        <p class="tq-strong" style="margin:0;color:var(--tq-navy)">
            <?php echo html_escape($tq_greet . ($tq_name ? '، ' . explode(' ', $tq_name)[0] : '')); ?> 👋
        </p>
        <p class="tq-micro" style="margin:0">مستعد لتتعلم شيئا جديدا اليوم؟</p>
    </div>

    <form class="tq-topbar__search" role="search" action="<?php echo base_url('student/search'); ?>" method="get">
        <label class="tq-sr" for="tq-q">ابحث في الدروس والمواد والمعلمين</label>
        <input class="tq-input" id="tq-q" name="q" type="search"
               placeholder="ابحث عن درس أو مادة أو معلم…" data-tq-search>
        <kbd class="tq-topbar__kbd" aria-hidden="true"><span class="tq-ltr">⌘K</span></kbd>
    </form>

    <div class="tq-topbar__actions">

        <a class="tq-iconbtn" href="<?php echo base_url('student/messages'); ?>" aria-label="الرسائل">
            <?php echo tq_icon('chat'); ?>
            <?php if (!empty($tq_counts['messages'])): ?>
                <span class="tq-iconbtn__dot"><?php echo TQ_LRI . (int) $tq_counts['messages'] . TQ_PDI; ?></span>
                <span class="tq-sr">رسائل غير مقروءة</span>
            <?php endif; ?>
        </a>

        <a class="tq-iconbtn" href="<?php echo base_url('student/notifications'); ?>" aria-label="الإشعارات">
            <?php echo tq_icon('bell'); ?>
            <?php if (!empty($tq_counts['notifications'])): ?>
                <span class="tq-iconbtn__dot"><?php echo TQ_LRI . (int) $tq_counts['notifications'] . TQ_PDI; ?></span>
                <span class="tq-sr">إشعارات غير مقروءة</span>
            <?php endif; ?>
        </a>

        <a class="tq-row" href="<?php echo base_url('student/settings'); ?>" style="gap:var(--tq-space-s)">
            <img class="tq-avatar tq-avatar--sm" src="<?php echo $tq_photo; ?>" alt="">
            <span class="tq-rail__text">
                <span class="tq-caption" style="display:block;color:var(--tq-navy);font-weight:700"><?php echo html_escape($tq_name ?: 'حسابي'); ?></span>
                <span class="tq-micro"><?php echo html_escape($tq_role_label); ?></span>
            </span>
        </a>
    </div>
</header>
