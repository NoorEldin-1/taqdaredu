<?php
/**
 * الترويسة العلوية للبوابة.
 * التحية يمينا · البحث وسطا يفتح بـ ⌘K · الإشعارات والرسائل · الحساب.
 *
 * **الوجهات تشتق من الدور.** كانت مكتوبة `student/...` حرفيا في ترويسة
 * تعرض للأدوار الثلاثة: فالمعلم الذي ينقر جرس الإشعارات يمر بـ`tq_guard`
 * فيرد إلى لوحته برسالة «هذه الصفحة تخص بوابة الطالب» — وهو لم يطلب بوابة
 * أحد، إنما نقر زرا في ترويسته هو. وكذلك صورة حسابه وصندوق بحثه.
 *
 * وما لا شاشة له في بوابة الدور **لا يعرض زره**: زر يعد بوجهة ثم يعتذر
 * أسوأ من زر غائب، والشارة فوقه تعد بعدد لا يفتحه شيء.
 */
$tq_uid   = $this->session->userdata('user_id');
$tq_user  = $tq_uid ? $this->user_model->get_all_user($tq_uid)->row_array() : null;
$tq_name  = $tq_user ? trim($tq_user['first_name'] . ' ' . $tq_user['last_name']) : '';
$tq_photo = tqs_person_img($tq_user ? $tq_user['image'] : '');

$tq_role_label = ['student' => 'طالب', 'teacher' => 'معلم', 'parent' => 'ولي أمر'][$tq_role] ?? 'طالب';

/**
 * وجهات الترويسة لكل دور. `null` تعني «لا شاشة لها في هذه البوابة بعد».
 * والمفاتيح تطابق مفاتيح `Taqdar::counts()` حتى لا تعلق شارة على زر خطأ.
 */
$tq_bar_map = [
    'student' => [
        'messages'      => 'student/messages',
        'notifications' => 'student/notifications',
        'account'       => 'student/settings',
        'search'        => 'student/search',
    ],
    'teacher' => [
        // كانت الثلاثة `null` لأن الشاشات لم تكن موجودة، فكان جرس المعلم
        // مخفيا وصورة حسابه تعيده إلى لوحته. وصارت له شاشاته فصارت له أزرارها.
        'messages'      => 'teacher/messages',
        'notifications' => 'teacher/notifications',
        'account'       => 'teacher/settings',
        'search'        => 'teacher/search',
    ],
    'parent' => [
        'messages'      => 'parent/messages',
        'notifications' => 'parent/alerts',
        'account'       => 'parent/settings',
        'search'        => 'parent/search',
    ],
];
$tq_bar = $tq_bar_map[$tq_role] ?? $tq_bar_map['student'];

/* ثلاث فترات لا اثنتان: الفرع الأوسط كان يعيد نص الفرع الأخير حرفا بحرف،
   فكان الشرط يحسب ولا يغير شيئا. */
$tq_hour = (int) date('G');
if     ($tq_hour < 12) $tq_greet = 'صباح الخير';
elseif ($tq_hour < 18) $tq_greet = 'طاب يومك';
else                   $tq_greet = 'مساء الخير';
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

    <form class="tq-topbar__search" role="search" action="<?php echo base_url($tq_bar['search']); ?>" method="get">
        <label class="tq-sr" for="tq-q">ابحث في الدروس والمواد والمعلمين</label>
        <input class="tq-input" id="tq-q" name="q" type="search"
               value="<?php echo html_escape((string) $this->input->get('q', true)); ?>"
               placeholder="ابحث عن درس أو مادة أو معلم…" data-tq-search>
        <?php /* الاختصار يكتب باسمه على المنصة التي يقرأ عليها: `taqdar.js`
                 يبدل النص إلى Ctrl K على غير أجهزة أبل. */ ?>
        <kbd class="tq-topbar__kbd" aria-hidden="true" data-tq-kbd><span class="tq-ltr">⌘K</span></kbd>
    </form>

    <div class="tq-topbar__actions">

        <?php if (!empty($tq_bar['messages'])): ?>
            <a class="tq-iconbtn" href="<?php echo base_url($tq_bar['messages']); ?>" aria-label="الرسائل" title="الرسائل">
                <?php echo tq_icon('chat'); ?>
                <?php if (!empty($tq_counts['messages'])): ?>
                    <span class="tq-iconbtn__dot"><?php echo TQ_LRI . (int) $tq_counts['messages'] . TQ_PDI; ?></span>
                    <span class="tq-sr">رسائل غير مقروءة</span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if (!empty($tq_bar['notifications'])): ?>
            <a class="tq-iconbtn" href="<?php echo base_url($tq_bar['notifications']); ?>" aria-label="الإشعارات" title="الإشعارات">
                <?php echo tq_icon('bell'); ?>
                <?php if (!empty($tq_counts['notifications'])): ?>
                    <span class="tq-iconbtn__dot"><?php echo TQ_LRI . (int) $tq_counts['notifications'] . TQ_PDI; ?></span>
                    <span class="tq-sr">إشعارات غير مقروءة</span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <a class="tq-topbar__me" href="<?php echo base_url($tq_bar['account']); ?>" title="<?php echo html_escape($tq_name ?: 'حسابي'); ?>">
            <img class="tq-avatar tq-avatar--sm" src="<?php echo $tq_photo; ?>" alt="">
            <span class="tq-topbar__me-name">
                <span class="tq-caption" style="color:var(--tq-navy);font-weight:700"><?php echo html_escape($tq_name ?: 'حسابي'); ?></span>
                <span class="tq-micro"><?php echo html_escape($tq_role_label); ?></span>
            </span>
        </a>
    </div>
</header>
