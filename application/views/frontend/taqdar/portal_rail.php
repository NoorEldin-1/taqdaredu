<?php
/**
 * القائمة الجانبية.
 *
 * الترتيب ثابت في كل الشاشات ولا يعاد ترتيبه حسب الصفحة — قائمة تتحرك
 * تجبر المستخدم على القراءة في كل صفحة بدل أن يحفظ موضع ما يريد.
 * العرض 240px مفتوحة و72px مطوية، وارتفاع العنصر 44px وهو حد اللمس نفسه.
 *
 * ثلاثة أشياء تغيرت هنا، وكلها عن عطل ظاهر لا عن ذوق:
 *
 * ١ — **المجموعات.** ست عشرة وجهة في عمود واحد جدار يقرأ سطرا سطرا في كل
 *     مرة. وهي في ذهن الطالب أربعة أسئلة لا ستة عشر: أين أتعلم؟ أين
 *     أتمرن؟ كيف أقيس؟ من يساعدني؟ فالعناوين تجيب السؤال قبل القراءة،
 *     والترتيب داخل كل مجموعة يبقى ثابتا كما كان.
 *
 * ٢ — **الطي والتمرير.** الشريط كان يطول بطول قائمته: على شاشة محمول
 *     ارتفاعها 768 تسقط آخر ثلاثة بنود والبطاقة السفلية تحت حافة النافذة،
 *     ولا سبيل إليها إلا بتمرير الصفحة كلها — فتذهب القائمة مع المحتوى.
 *     صار الشريط ملتصقا بارتفاع النافذة، وقائمته وحدها هي التي تمرر،
 *     والبطاقة السفلية مثبتة أسفله لا تتحرك.
 *
 * ٣ — **الاسم في الوضع المطوي.** كان `.tq-rail__text` يخفى بـ`display:none`
 *     والأيقونة `aria-hidden` — فيبقى الرابط **بلا اسم** لقارئ الشاشة بين
 *     640 و1024 بكسل: ستة عشر رابطا يقرؤها «رابط» وحسب. النص الآن يخفى
 *     بصريا ويبقى في شجرة الوصول، ومعه `title` لمن يرى.
 */
$tq_nav  = $tq_nav  ?? '';
$tq_role = $tq_role ?? 'student';

/**
 * البنود مجمعة: [عنوان المجموعة، [مفتاح، تسمية، مسار، أيقونة]…].
 * وعنوان فارغ يعني مجموعة بلا رأس (البند الأول وحده).
 */
$tq_rail_map = [
    'student' => [
        ['', [
            ['home', 'الرئيسية', 'student', 'home'],
        ]],
        ['التعلم', [
            /* الكورس والدرس بندان لا بند: «دروسي» كانت تفتح شبكة كورسات،
               فلا مدخل في القائمة كلها إلى درس بعينه. والترتيب مقصود —
               الكورس وعاء والدرس ما فيه، فيقرأ من الأعم إلى الأخص. */
            ['courses',    'كورساتي',          'student/courses',    'book'],
            ['lessons',    'دروسي',            'student/lessons',    'play'],
            ['bundle',     'محتوى باقتي',      'student/bundle',     'grid'],
            /* الكتب كانت في الكتالوج العام وفي اللوحة، وبلا قارئ في بوابة
               الطالب: يشتري باقة فيها كتب ولا موضع يفتحها منه. */
            ['library',    'مكتبتي',           'student/library',    'book'],
            ['materials',  'المواد التعليمية', 'student/materials',  'folder'],
            ['favourites', 'المفضلة',          'student/favourites', 'heart'],
        ]],
        ['التمرين والقياس', [
            ['reviews',  'المراجعة',      'student/reviews',  'flame'],
            /* دفتر الأخطاء: محركه (`get_mistakes`) و`endpoint`ه
               (`taqdar_gate/mistakes`) مبنيان منذ أن كتبت بوابة الإتقان،
               ولم يكن في الواجهة كلها سطر يناديهما — ميزة كاملة بلا باب.
               وموضعه هنا لا في «المتابعة»: الدفتر يمرن ولا يخبر. */
            ['mistakes', 'دفتر الأخطاء', 'student/mistakes', 'help'],
            ['tasks',    'مهامي',        'student/tasks',    'clipboard'],
            ['exams',    'اختباراتي',    'student/exams',    'check-badge'],
        ]],
        ['المتابعة', [
            ['reports',      'المتابعة والتقارير', 'student/reports',      'chart'],
            /* خريطة الإتقان: يقرؤها المسؤول في `taqdar_admin/mastery`
               ويقاس عليها الطالب وتصدر بها شهادته — ولا يراها هو. */
            ['mastery',      'خريطة إتقاني',       'student/mastery',      'target'],
            ['calendar',     'التقويم',            'student/calendar',     'calendar'],
            ['certificates', 'الشهادات',           'student/certificates', 'award'],
        ]],
        ['الدعم والتواصل', [
            ['on_demand',     'حصص بالطلب', 'student/on-demand',     'video'],
            ['messages',      'رسائلي',     'student/messages',      'chat'],
            ['notifications', 'الإشعارات',  'student/notifications', 'bell'],
        ]],
        ['حسابي', [
            /* الملف الشخصي غير الإعدادات: هذا ما بلغه الطالب — إتقانه
               وشهاداته وسلسلته — وتلك ما يضبطه. وكانا شاشة واحدة. */
            ['profile',      'ملفي',      'student/profile',      'user'],
            ['subscription', 'اشتراكي',   'student/subscription', 'wallet'],
            ['settings',     'الإعدادات', 'student/settings',     'cog'],
        ]],
    ],
    'teacher' => [
        ['', [
            ['dashboard', 'اللوحة', 'teacher', 'home'],
        ]],
        ['التدريس', [
            /* كما في بوابة الطالب: الكورس وعاء والدرس ما فيه، ولكل منهما
               شاشة. وكان الدرس بلا شاشة هنا أصلا — رقم في جدول وخمسة في زاوية. */
            ['courses',   'كورساتي',     'teacher/courses',   'book'],
            ['lessons',   'دروسي',       'teacher/lessons',   'play'],
            ['upload',    'رفع الدروس',      'teacher/upload',    'upload'],
            /* الاستوديو بعد الرفع مباشرة: هو الخطوة التالية في دورة
               الإنتاج — يرفع، ثم يولد ويعتمد، ثم يرسل للمراجعة. */
            ['studio',    'استوديو المحتوى', 'teacher/studio',    'pen'],
            ['questions', 'بنك الأسئلة',     'teacher/questions', 'help'],
        ]],
        ['الطلاب والتصحيح', [
            ['marking',   'الواجبات والتصحيح', 'teacher/marking',   'clipboard'],
            ['students',  'طلابي',             'teacher/students',  'users'],
            ['sessions',  'الحصص',             'teacher/sessions',  'video'],
            /* التحليلات مع الطلاب لا مع التدريس: الخريطة الحرارية تقرأ
               سلوك الطلاب لا حال المحتوى، وكل صف فيها ينتهي بإجراء. */
            ['analytics', 'التحليلات',         'teacher/analytics', 'chart'],
        ]],
        /* التواصل: كان المعلم يرسل إلى طلابه من شاشة «طلابي» ولا يملك
           صندوقا يقرأ فيه ردهم، وكانت إشعاراته تعد في `Taqdar::counts()`
           ولا تعرض في شاشة ولا في جرس. */
        ['التواصل', [
            ['messages',      'الرسائل',   'teacher/messages',      'chat'],
            ['notifications', 'الإشعارات', 'teacher/notifications', 'bell'],
        ]],
        ['حسابي', [
            ['wallet',   'المحفظة والأرباح', 'teacher/wallet',   'wallet'],
            ['settings', 'الإعدادات',        'teacher/settings', 'cog'],
        ]],
    ],
    'parent' => [
        ['', [
            ['children', 'أبنائي', 'parent', 'users'],
        ]],
        ['المتابعة', [
            ['reports', 'التقارير',         'parent/reports', 'chart'],
            ['weekly',  'التقرير الأسبوعي', 'parent/weekly',  'clipboard'],
        ]],
        ['التواصل', [
            ['messages', 'الرسائل',   'parent/messages', 'chat'],
            ['alerts',   'الإشعارات', 'parent/alerts',   'bell'],
        ]],
        ['حسابي', [
            /* الدفع فعل والمدفوعات سجل، وهما بندان لا بند: كان ولي
               الأمر لا يستطيع الشراء لابنه أصلا — `checkout()` يرده
               برسالة «الاشتراك لحسابات الطلاب». */
            ['pay',      'ادفع عن ابنك', 'parent/pay',      'card'],
            ['payments', 'المدفوعات',    'parent/payments', 'wallet'],
            ['settings', 'الإعدادات',    'parent/settings', 'cog'],
        ]],
    ],
];
$tq_rail_groups = $tq_rail_map[$tq_role] ?? $tq_rail_map['student'];
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
    'alerts'        => 'إشعار غير مقروء',
];
?>
<div class="tq-rail-scrim" data-tq-scrim hidden></div>

<aside class="tq-rail" data-tq-rail>

    <div class="tq-rail__brand">
        <a class="tq-logo tq-rail__logo" href="<?php echo base_url(); ?>">
            <img src="<?php echo tq_asset('brand/icon.png'); ?>" alt="" width="36" height="36" aria-hidden="true">
            <span class="tq-rail__text">
                <span class="tq-strong" style="color:var(--tq-navy);display:block;line-height:1.2">تقدر</span>
                <span class="tq-micro">منصة التعليم الذكي</span>
            </span>
            <span class="tq-sr">تقدر — الصفحة الرئيسية</span>
        </a>

        <?php /* الطي كان مكتوبا في الورقة (`[data-collapsed]`) وبلا زر يضبطه —
                 نصف ميزة لا تعمل. وهذا زرها، ويحفظ اختياره على الجهاز. */ ?>
        <button class="tq-iconbtn tq-rail__collapse" type="button"
                data-tq-rail-collapse aria-expanded="true"
                aria-label="طي القائمة الجانبية" title="طي القائمة الجانبية">
            <?php echo tq_icon('chev-next', 18); ?>
        </button>

        <?php /* على الجوال يغلق الدرج من داخله: زر الفتح في الترويسة يختفي
                 خلف الطبقة السوداء، فمن فتح لا يجد بابا يخرج منه إلا بالضغط
                 على الطبقة — وهو تصرف لا يعلنه شيء. */ ?>
        <button class="tq-iconbtn tq-rail__close" type="button"
                data-tq-rail-toggle aria-label="إغلاق القائمة" title="إغلاق القائمة">
            <?php echo tq_icon('x', 18); ?>
        </button>
    </div>

    <nav class="tq-rail__nav" aria-label="التنقل الرئيسي">
        <?php foreach ($tq_rail_groups as [$tq_rail_gtitle, $tq_rail_gitems]): ?>
            <?php if ($tq_rail_gtitle !== ''): ?>
                <p class="tq-rail__group" aria-hidden="true"><?php echo html_escape($tq_rail_gtitle); ?></p>
            <?php endif; ?>

            <?php foreach ($tq_rail_gitems as [$key, $label, $href, $icon]): ?>
                <a class="tq-rail__item" href="<?php echo base_url($href); ?>"
                   title="<?php echo html_escape($label); ?>"<?php echo tq_active($key, $tq_nav); ?>>
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
        <?php endforeach; ?>
    </nav>

    <?php if ($tq_role === 'student'):
        /* من يدفع لا يطالب بالدفع: المشترك يرى مدته الباقية لا إعلانا. */
        $tq_ci = &get_instance();
        $tq_ci->load->model('taqdar_billing_model');
        $tq_rail_sub = $tq_ci->taqdar_billing_model->active_subscription($tq_ci->session->userdata('user_id'));
        $tq_rail_days = $tq_rail_sub && !empty($tq_rail_sub['ends_at'])
            ? (int) ceil((strtotime($tq_rail_sub['ends_at']) - time()) / 86400) : 0;
    ?>
        <div class="tq-rail__foot">
            <div class="tq-rail__promo tq-pastel tq-pastel--mint">
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

            <?php /* البديل في الوضع المطوي: البطاقة تختفي ويبقى بابها —
                     أيقونة واحدة تقود إلى الوجهة نفسها. */ ?>
            <a class="tq-rail__item tq-rail__foot-mini"
               href="<?php echo base_url($tq_rail_sub ? 'student/subscription' : 'plans'); ?>"
               title="<?php echo $tq_rail_sub ? 'تفاصيل الاشتراك' : 'عرض الباقات'; ?>">
                <span class="tq-rail__icon" aria-hidden="true"><?php echo tq_icon('wallet'); ?></span>
                <span class="tq-rail__text"><?php echo $tq_rail_sub ? 'تفاصيل الاشتراك' : 'عرض الباقات'; ?></span>
            </a>
        </div>
    <?php endif; ?>
</aside>
