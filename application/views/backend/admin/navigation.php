<?php
/**
 * الشريط الجانبي للوحة.
 *
 * أعيد بناؤه من الصفر لثلاثة أسباب، وكلها عن عطل ظاهر لا عن ذوق:
 *
 * ١ — **قائمتان في شريط واحد.** كان أعلاه «منصة تقدر» وأسفله «ملاحة»
 *     Academy: فالمسارات في الأولى والكورسات في الثانية، والاشتراكات
 *     هنا والمدفوعات هناك. المسؤول الذي يريد «كل ما يخص المال» يقرأ
 *     الشريط كله مرتين. صارت البنية واحدة تجيب سؤال العمل لا سؤال
 *     من كتب الشيفرة.
 *
 * ٢ — **كتل لا ترسم شيئا أبدا.** ثلاث عشرة كتلة كانت مشروطة بـ
 *     `addon_status(...)`: bootcamp · team_training · tutor_booking ·
 *     ebook · affiliate_course · offline_payment · customer_support …
 *     و`application/controllers/addons/` مجلد فارغ وجدول `addons` بلا
 *     صف. أي: نصف الملف شرط كاذب أبدا، ومعه استعلامات تنفذ في كل صفحة
 *     لتعد صفوفا في جداول إضافات غير مثبتة. حذفت.
 *
 * ٣ — **الشارات كانت تستعلم في العرض.** ستة استعلامات مبعثرة بين
 *     الوسوم، تنفذ في كل صفحة من صفحات اللوحة التسعين. جمعت في
 *     `tqa_nav_counts()` — استعلام واحد مجمع، ومصدر واحد للرقم.
 *
 * والبند يكتب مرة واحدة في `$tqa_nav`: أي صفحة تضاف تسجل هنا وحدها،
 * ولا يبقى موضع ثان ينسى فيصير البند بلا تظليل.
 */

/**
 * التظليل يقرأ `nav_key` لا `page_name`.
 *
 * TQ-RAIL-NOACTIVE — الوحدات الموصوفة كلها تعرض بقالبي `tqa_list`
 * و`tqa_form`، فست عشرة شاشة كانت ترسل الاسم نفسه ولا بند هنا يحمله:
 * لا تظليل، ولا جلب البند إلى الرؤية، في قائمة من ثمانية وثلاثين بندا.
 * فـ`Taqdar_admin::render()` صار يرسل `nav_key` يسمي البند، وافتراضه
 * `page_name` فلا شيء مما كان يطابق ينكسر. وشاشات Academy تبقى على
 * `page_name` وحده — أسماء بندها مسجلة أدناه كما كانت.
 */
$page_name  = isset($page_name) ? $page_name : '';
$tqa_active_key = isset($nav_key) ? $nav_key : $page_name;
$tqa_counts = tqa_nav_counts();

/**
 * البنية: [عنوان المجموعة، الأيقونة، [[مفتاح الصفحة، التسمية، المسار،
 * الأيقونة، مفتاح الشارة]…]].
 *
 * `مفتاح الصفحة` قد يكون نصا أو مصفوفة أسماء — الشاشة الواحدة في هذا
 * القالب تحمل `page_name` مختلفا في العرض والتحرير والإضافة، والبند
 * يظلل في الثلاثة أو لا يظلل في شيء.
 */
$tqa_nav = [

    ['', '', [
        ['tqa_overview', 'لوحة القيادة', 'taqdar_admin/overview', 'meter', null],
    ]],

    ['المنهج', 'graduation', [
        ['tqa_subjects',    'المواد الدراسية', 'taqdar_admin/module/subjects',   'layers',      null],
        ['tqa_grades',      'الصفوف الدراسية', 'taqdar_admin/module/grades',     'award',       null],
        ['tqa_paths',       'المسارات',        'taqdar_admin/module/paths',      'target',      null],
        ['tqa_milestones',  'المحطات',         'taqdar_admin/module/milestones', 'flag',        null],
        [['course_add', 'course_edit', 'courses-server-side', 'curriculum',
          'lessons', 'video_player', 'course_enrol_list', 'change_course_author'],
                            'الكورسات',        'admin/courses',                  'book',        'pending_courses'],
        /* مراجعة المحتوى — تحت الكورسات لأنها بابها: ما يعتمد هنا يظهر
           هناك. وكانت غائبة كلها، فيكتب المعلم `tq_status='review'` ولا
           شيء في اللوحة يقرؤه. */
        ['tqa_review',      'مراجعة المحتوى',  'taqdar_admin/review',            'shield',      'content_review'],
        [['categories', 'category_add', 'category_edit'],
                            'أقسام الكورسات',  'admin/categories',               'grid',        null],
        ['tqa_import',      'استيراد المنهج',  'taqdar_admin/import',            'import',      null],
    ]],

    ['الإتقان والتقييم', 'crosshair', [
        ['tqa_objectives',  'الأهداف التعليمية',    'taqdar_admin/module/objectives',  'target',      null],
        [['tqa_bindings', 'tqa_bind'],
                            'ربط الأسئلة بالأهداف', 'taqdar_admin/bindings',           'link',        null],
        ['tqa_assessments', 'التقييمات',            'taqdar_admin/module/assessments', 'check-badge', null],
        /* الاختبار التشخيصي: بندان لا واحد — الاختبار يبنى، والنتائج تقرأ.
           وشاشة الأسئلة تفتح من جدول الاختبارات (`row_action`) فلا بند
           ثالث لها: بند في القائمة لشيء يحتاج معرفا يقود إلى لا شيء. */
        ['tqa_diag_exams',    'الاختبارات التشخيصية', 'taqdar_admin/module/diag_exams',    'crosshair', null],
        ['tqa_diag_attempts', 'نتائج التشخيص',        'taqdar_admin/module/diag_attempts', 'chart',     null],
        ['tqa_mastery',     'خريطة الإتقان',        'taqdar_admin/mastery',            'chart',       null],
    ]],

    ['الأشخاص', 'users', [
        [['tqa_people', 'users', 'user_add', 'user_edit', 'instructors', 'instructor_add',
          'instructor_edit', 'instructor_settings', 'enrol_student', 'enrol_history'],
                            'كل الحسابات',          'taqdar_admin/people',                     'users',   null],
        ['tqa_teachers',    'طلبات المعلمين',       'taqdar_admin/teachers',                   'file',    'teacher_apps'],
        ['tqa_teacher_assignments', 'إسناد المعلمين', 'taqdar_admin/module/teacher_assignments', 'link',  null],
        ['tqa_parent_links','روابط أولياء الأمور',  'taqdar_admin/module/parent_links',        'heart',   'parent_links'],
        [['admins', 'admin_add', 'admin_edit', 'admin_permission'],
                            'المسؤولون والصلاحيات', 'admin/admins',                            'shield',  null],
    ]],

    ['التعليم المباشر', 'video', [
        ['tqa_sessions',    'الحصص',          'taqdar_admin/sessions', 'video', 'sessions'],
        ['tqa_slots',       'أوقات المعلمين', 'taqdar_admin/slots',    'clock', null],
    ]],

    ['المالية', 'wallet', [
        ['tqa_plans',          'الباقات',              'taqdar_admin/module/plans',          'card',    null],
        ['tqa_subscriptions',  'الاشتراكات',           'taqdar_admin/subscriptions',         'refresh', 'subs_pending'],
        ['tqa_invoices',       'الفواتير',             'taqdar_admin/module/invoices',       'file-text', null],
        ['tqa_payouts',        'طلبات السحب',          'taqdar_admin/payouts',               'send',    'payouts'],
        ['tqa_wallets',        'المحافظ',              'taqdar_admin/module/wallets',        'wallet',  null],
        ['tqa_wallet_entries', 'قيود المحافظ',         'taqdar_admin/module/wallet_entries', 'receipt', null],
        ['tqa_tap',            'الدفع بالبطاقة',        'taqdar_admin/tap',                   'card',    null],
        ['tqa_bank',           'بيانات التحويل البنكي', 'taqdar_admin/bank',                  'bank',    null],
        /* «بوابات الدفع» الموروثة تبقى في القائمة ولا تصير الأولى: هي شاشة
           Academy لست عشرة بوابة لا واحدة منها تمس اشتراكات تقدر — وبند
           الدفع الفعلي هو «الدفع بالبطاقة» أعلاه. */
        [['payment_settings'], 'إعدادات الدفع الموروثة', 'admin/payment_settings',            'cog',     null],
    ]],

    ['المحتوى والموقع', 'globe', [
        [['tqa_content', 'tqa_content_edit'],
                             'نصوص الصفحات',     'taqdar_admin/content',                     'edit',   null],
        ['tqa_stats',        'أرقام الموقع',      'taqdar_admin/stats',                       'chart',  null],
        ['tqa_testimonials', 'آراء أولياء الأمور', 'taqdar_admin/module/testimonials',  'chat',   null],
        ['tqa_books',        'الكتب',            'taqdar_admin/module/books',                'book',   null],
        ['tqa_competitions', 'المسابقات',        'taqdar_admin/module/competitions',         'trophy', null],
        ['tqa_competition_entries', 'المشاركون في المسابقات', 'taqdar_admin/module/competition_entries', 'users', null],
        [['blog', 'blog_add', 'blog_edit', 'blog_category', 'blog_category_add', 'blog_category_edit',
          'blog_settings', 'instructors_pending_blog'],
                             'المدونة',          'admin/blog',                               'file',   'pending_blogs'],
    ]],

    ['التواصل', 'chat', [
        [['message', 'message_new', 'message_read', 'message_home'],
                             'الرسائل',            'admin/message',            'chat',  'messages'],
        ['tqa_notify',       'إرسال إشعار',        'taqdar_admin/notify',      'bell',  null],
        [['contact', 'contact_reply_form'],
                             'رسائل التواصل',      'admin/contact',            'mail',  'contact'],
        [['subscribed_user', 'newsletters', 'newsletter_history',
          'add_newsletter', 'edit_newsletter', 'send_newsletter'],
                             'النشرة البريدية',    'admin/subscribed_user',    'send',  null],
        ['tqa_mail',         'البريد الصادر',      'taqdar_admin/mail',        'mail',  null],
        /* القناة الثانية، وتحت البريد لا فوقه: البريد يحمل كل شيء
           وواتساب يحمل المال ورموز التحقق وحدها. */
        ['tqa_whatsapp',     'إشعارات واتساب',     'taqdar_admin/whatsapp',    'whatsapp', null],
    ]],

    ['النظام', 'cog', [
        [['system_settings'],   'إعدادات المنصة',       'admin/system_settings',       'cog',    null],
        [['frontend_settings', 'review_add', 'review_edit'],
                                'إعدادات الموقع',       'admin/frontend_settings',     'globe',  null],
        [['seo_settings'],      'تحسين محركات البحث',   'admin/seo_settings',          'search', null],
        [['sitemap_settings'],  'خريطة الموقع',         'admin/sitemap_settings',      'layers', null],
        [['manage_language'],   'اللغات والترجمة',      'admin/manage_language',       'file',   null],
        [['notification_settings'], 'قوالب الإشعارات',  'admin/notification_settings', 'bell',   null],
        ['tqa_audit_log',       'سجل التدقيق',          'taqdar_admin/module/audit_log', 'shield', null],
        [['manage_profile'],    'حسابي',                'admin/manage_profile',        'cog',    null],
    ]],
];

/** هل هذا البند هو الصفحة المعروضة؟ */
$tqa_is_active = function ($keys) use ($tqa_active_key) {
    return in_array($tqa_active_key, is_array($keys) ? $keys : [$keys], true);
};
?>
<div class="tqa-rail-scrim" data-tqa-scrim></div>

<aside class="tqa-rail" id="tqa-rail">

    <div class="tqa-rail__brand">
        <a class="tqa-rail__logo" href="<?php echo site_url('taqdar_admin/overview'); ?>">
            <img src="<?php echo tq_asset('brand/icon.png'); ?>" alt="" width="36" height="36">
            <span>
                <span class="tqa-rail__wordmark">تقدر</span>
                <span class="tqa-rail__tagline">لوحة الإدارة</span>
            </span>
        </a>

        <?php /* الطي يحفظ اختياره على الجهاز: من طوى الشريط لا يريد
                 أن يعود مفتوحا في كل صفحة.

                 والسهم يشير إلى الحافة التي ينطوي الشريط نحوها — ينعكس
                 في الوضع المطوي فيصير بابا يفتح. انظر `.tqa-rail__collapse`
                 في [admin.css]: انعكاسه مكتوب هناك صراحة لأن `base.css`
                 التي تقلب `tq-dir-icon` لا تحمل في اللوحة. */ ?>
        <button class="tqa-iconbtn tqa-rail__collapse" type="button"
                data-tqa-collapse aria-expanded="true"
                aria-label="طي القائمة الجانبية" title="طي القائمة الجانبية">
            <?php echo tq_icon('chev-next', 18); ?>
        </button>

        <button class="tqa-iconbtn tqa-rail__close" type="button"
                data-tqa-toggle aria-label="إغلاق القائمة" title="إغلاق القائمة">
            <?php echo tq_icon('x', 18); ?>
        </button>
    </div>

    <nav class="tqa-rail__nav" aria-label="التنقل الرئيسي">
        <?php foreach ($tqa_nav as [$tqa_gtitle, $tqa_gicon, $tqa_gitems]): ?>

            <?php if ($tqa_gtitle !== ''): ?>
                <p class="tqa-rail__group" aria-hidden="true"><?php echo html_escape($tqa_gtitle); ?></p>
            <?php endif; ?>

            <?php foreach ($tqa_gitems as [$tqa_key, $tqa_label, $tqa_href, $tqa_icon, $tqa_badge]): ?>
                <?php $tqa_n = $tqa_badge ? (int) ($tqa_counts[$tqa_badge] ?? 0) : 0; ?>
                <a class="tqa-rail__item" href="<?php echo site_url($tqa_href); ?>"
                   title="<?php echo html_escape($tqa_label); ?>"
                   <?php echo $tqa_is_active($tqa_key) ? 'aria-current="page"' : ''; ?>>
                    <span class="tqa-rail__icon" aria-hidden="true"><?php echo tq_icon($tqa_icon, 19); ?></span>
                    <span class="tqa-rail__text"><?php echo html_escape($tqa_label); ?></span>
                    <?php if ($tqa_n > 0): ?>
                        <?php /* الرقم في `<bdi>` لا الشارة نفسها.
                                 TQ-RAIL-BADGE-DIR — كانت `direction: ltr`
                                 مكتوبة على الشارة، وخصائص الهامش المنطقية
                                 تقرأ اتجاه **العنصر نفسه**: فـ
                                 `margin-inline-start: auto` صارت
                                 `margin-left` في صف مقلوب، أي هامشا على
                                 طرف النهاية لا البداية — فالشارة تلتصق
                                 بالنص بدل أن تدفع إلى طرف البند. والعزل
                                 يلزم مع ذلك حتى تخرج «99+» بعلامتها بعد
                                 الرقم؛ فانتقل إلى `<bdi>` حول الرقم وحده. */ ?>
                        <span class="tqa-rail__count<?php echo in_array($tqa_badge, ['payouts', 'teacher_apps', 'sessions'], true) ? ' tqa-rail__count--urgent' : ''; ?>"><bdi><?php
                            echo $tqa_n > 99 ? '99+' : $tqa_n;
                        ?></bdi></span>
                        <span class="tqa-sr">بند ينتظر إجراء</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

        <?php endforeach; ?>
    </nav>

    <?php /* الذيل يبقى في الوضع المطوي بأيقونته وحدها: كان يخفى كله،
             فينطوي الشريط ويذهب معه الباب الوحيد إلى الموقع العام.
             والاسم في `tqa-rail__text` فتخفيه قاعدة الطي نفسها. */ ?>
    <div class="tqa-rail__foot">
        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm tqa-btn--block" href="<?php echo base_url(); ?>"
           target="_blank" rel="noopener" title="زيارة الموقع">
            <?php echo tq_icon('eye', 16); ?> <span class="tqa-rail__text">زيارة الموقع</span>
        </a>
    </div>
</aside>
