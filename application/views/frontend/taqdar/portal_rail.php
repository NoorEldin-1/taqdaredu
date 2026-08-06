<?php
/**
 * القائمة الجانبية.
 * الترتيب ثابت في كل الشاشات ولا يعاد ترتيبه حسب الصفحة — قائمة تتحرك
 * تجبر المستخدم على القراءة في كل صفحة بدل أن يحفظ موضع ما يريد.
 * العرض 240px مفتوحة و72px مطوية، وارتفاع العنصر 44px وهو حد اللمس نفسه.
 */
$tq_nav_items = [
    'student' => [
        ['home',         'الرئيسية',              'student',         'home'],
        ['lessons',      'دروسي',                 'student/lessons',      'book'],
        ['bundle',       'محتوى باقتي',           'student/bundle',       'grid'],
        ['reviews',      'المراجعة',              'student/reviews',      'flame'],
        ['tasks',        'مهامي',                 'student/tasks',        'clipboard'],
        ['exams',        'اختباراتي',             'student/exams',        'check-badge'],
        ['on_demand',    'حصص بالطلب',            'student/on-demand',    'video'],
        ['reports',      'المتابعة والتقارير',    'student/reports',      'chart'],
        ['materials',    'المواد التعليمية',      'student/materials',    'folder'],
        ['favourites',   'المفضلة',               'student/favourites',   'heart'],
        ['messages',     'رسائلي',                'student/messages',     'chat'],
        ['notifications','الإشعارات',             'student/notifications','bell'],
        ['calendar',     'التقويم',               'student/calendar',     'calendar'],
        ['certificates', 'الشهادات',              'student/certificates', 'award'],
        ['subscription', 'اشتراكي',               'student/subscription', 'wallet'],
        ['settings',     'الإعدادات',             'student/settings',     'cog'],
    ],
    'teacher' => [
        ['dashboard', 'اللوحة',              'teacher',           'home'],
        ['courses',   'كورساتي',             'teacher/courses',   'book'],
        ['upload',    'رفع الدروس',          'teacher/upload',    'upload'],
        ['questions', 'بنك الأسئلة',         'teacher/questions', 'help'],
        ['marking',   'الواجبات والتصحيح',   'teacher/marking',   'clipboard'],
        ['students',  'طلابي',               'teacher/students',  'users'],
        ['sessions',  'الحصص',               'teacher/sessions',  'video'],
        ['wallet',    'المحفظة والأرباح',    'teacher/wallet',    'wallet'],
    ],
    'parent' => [
        ['children', 'أبنائي',        'parent',          'users'],
        ['reports',  'التقارير',      'parent/reports',  'chart'],
        ['weekly',   'التقرير الأسبوعي', 'parent/weekly', 'clipboard'],
        ['payments', 'المدفوعات',     'parent/payments', 'wallet'],
        ['messages', 'الرسائل',       'parent/messages', 'chat'],
        ['alerts',   'الإشعارات',     'parent/alerts',   'bell'],
        ['settings', 'الإعدادات',     'parent/settings', 'cog'],
    ],
];
$tq_items  = $tq_nav_items[$tq_role] ?? $tq_nav_items['student'];
$tq_counts = $tq_counts ?? [];

/**
 * شارة المراجعة.
 *
 * `Taqdar.php::counts()` لا يرجع مفتاح `reviews` بعد، وشارة بلا رقم تخبر
 * الطالب أن هناك شيئا ولا تخبره كم — فيفتحها ليجدها فارغة، وهذا أسوأ من
 * غيابها. فالعدد يقرأ هنا من `taqdar_repo_model` نفسه الذي تقرأ منه نقطة
 * البوابة — مصدر حقيقة واحد، لا استعلام مكتوب في العرض. ومتى أضيف المفتاح
 * إلى `counts()` سبق ما يأتي من المتحكم وسقط هذا الاحتياط من تلقاء نفسه.
 *
 * و`$this` هنا نسخة من خصائص المتحكم أخذت قبل التضمين، فنموذج يحمل الآن
 * لا يظهر فيها — ولذلك `get_instance()` صراحة، كما في كتلة الاشتراك أدناه.
 *
 * وهذه القائمة تعرض في كل صفحة بوابة: خطأ واحد هنا يبتر كل الشاشات. فأي
 * تعثر في قراءة العدد يبتلع، وتعرض القائمة بلا شارة.
 */
if ($tq_role === 'student' && !isset($tq_counts['reviews'])) {
    $tq_counts['reviews'] = 0;
    try {
        $tq_rv_ci  = &get_instance();
        $tq_rv_uid = (int) $tq_rv_ci->session->userdata('user_id');
        if ($tq_rv_uid) {
            $tq_rv_ci->load->model('taqdar_repo_model', 'tq_rail_repo');
            $tq_counts['reviews'] = (int) $tq_rv_ci->tq_rail_repo->count_due_reviews($tq_rv_uid);
        }
    } catch (Throwable $tq_rv_e) {
        $tq_counts['reviews'] = 0;
    }
}

/** نص قارئ الشاشة لكل شارة — «غير مقروء» لا يصف سؤالا حل موعده. */
$tq_count_sr = [
    'reviews'       => 'سؤال مستحق اليوم',
    'tasks'         => 'مهمة مستحقة',
    'messages'      => 'رسالة غير مقروءة',
    'notifications' => 'إشعار غير مقروء',
];
?>
<div class="tq-rail-scrim" data-tq-scrim hidden></div>

<nav class="tq-rail" data-tq-rail aria-label="التنقل الرئيسي">
    <a class="tq-logo" href="<?php echo base_url(); ?>" style="margin-block-end:var(--tq-space-xl);padding-inline:var(--tq-space-m)">
        <img src="<?php echo tq_asset('brand/icon.png'); ?>" alt="" width="36" height="36" aria-hidden="true">
        <span class="tq-rail__text">
            <span class="tq-strong" style="color:var(--tq-navy);display:block;line-height:1.2">تقدر</span>
            <span class="tq-micro">منصة التعليم الذكي</span>
        </span>
        <span class="tq-sr">تقدر — الصفحة الرئيسية</span>
    </a>

    <?php foreach ($tq_items as [$key, $label, $href, $icon]): ?>
        <a class="tq-rail__item" href="<?php echo base_url($href); ?>"<?php echo tq_active($key, $tq_nav); ?>>
            <span class="tq-rail__icon" aria-hidden="true"><?php echo tq_icon($icon); ?></span>
            <span class="tq-rail__text"><?php echo html_escape($label); ?></span>
            <?php if (!empty($tq_counts[$key])): ?>
                <span class="tq-rail__count<?php echo in_array($key, ['tasks', 'messages'], true) ? ' tq-rail__count--urgent' : ''; ?>">
                    <?php echo TQ_LRI . (int) $tq_counts[$key] . TQ_PDI; ?>
                </span>
                <span class="tq-sr"><?php echo html_escape($tq_count_sr[$key] ?? 'عنصر غير مقروء'); ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>

    <?php if ($tq_role === 'student'):
        /* من يدفع لا يطالب بالدفع: المشترك يرى مدته الباقية لا إعلانا. */
        $tq_ci = &get_instance();
        $tq_ci->load->model('taqdar_billing_model');
        $tq_rail_sub = $tq_ci->taqdar_billing_model->active_subscription($tq_ci->session->userdata('user_id'));
        $tq_rail_days = $tq_rail_sub && !empty($tq_rail_sub['ends_at'])
            ? (int) ceil((strtotime($tq_rail_sub['ends_at']) - time()) / 86400) : 0;
    ?>
        <div class="tq-rail__promo tq-pastel tq-pastel--mint" style="margin-block-start:auto">
            <?php if ($tq_rail_sub): ?>
                <span class="tq-pastel__label tq-micro">اشتراكك نشط</span>
                <p class="tq-pastel__body tq-strong" style="margin:var(--tq-space-xs) 0 var(--tq-space-m)">
                    <?php if ($tq_rail_days > 0): ?>
                        يتبقى <?php echo TQ_LRI . $tq_rail_days . TQ_PDI; ?> يوما
                    <?php else: ?>
                        ينتهي اليوم
                    <?php endif; ?>
                </p>
                <a class="tq-btn tq-btn--secondary tq-btn--sm tq-btn--block" href="<?php echo base_url('student/subscription'); ?>">
                    تفاصيل الاشتراك
                </a>
            <?php else: ?>
                <span class="tq-pastel__label tq-micro">اشتراكك</span>
                <p class="tq-pastel__body tq-strong" style="margin:var(--tq-space-xs) 0 var(--tq-space-m)">
                    افتح كل برامج صفك
                </p>
                <a class="tq-btn tq-btn--mastery tq-btn--sm tq-btn--block" href="<?php echo base_url('plans'); ?>">
                    عرض الباقات
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</nav>
