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
            ['home', t('الرئيسية'), 'student', 'home'],
        ]],
        [t('التعلم'), [
            /* الكورس والدرس بندان لا بند: «دروسي» كانت تفتح شبكة كورسات،
               فلا مدخل في القائمة كلها إلى درس بعينه. والترتيب مقصود —
               الكورس وعاء والدرس ما فيه، فيقرأ من الأعم إلى الأخص. */
            ['courses',    t('كورساتي'),          'student/courses',    'book'],
            ['lessons',    t('دروسي'),            'student/lessons',    'play'],
            ['bundle',     t('محتوى باقتي'),      'student/bundle',     'grid'],
            /* الكتب كانت في الكتالوج العام وفي اللوحة، وبلا قارئ في بوابة
               الطالب: يشتري باقة فيها كتب ولا موضع يفتحها منه. */
            ['library',    t('مكتبتي'),           'student/library',    'book'],
            ['materials',  t('المواد التعليمية'), 'student/materials',  'folder'],
            ['favourites', t('المفضلة'),          'student/favourites', 'heart'],
        ]],
        [t('التمرين والقياس'), [
            ['reviews',  t('المراجعة'),      'student/reviews',  'flame'],
            /* دفتر الأخطاء: محركه (`get_mistakes`) و`endpoint`ه
               (`taqdar_gate/mistakes`) مبنيان منذ أن كتبت بوابة الإتقان،
               ولم يكن في الواجهة كلها سطر يناديهما — ميزة كاملة بلا باب.
               وموضعه هنا لا في «المتابعة»: الدفتر يمرن ولا يخبر. */
            ['mistakes', t('دفتر الأخطاء'), 'student/mistakes', 'help'],
            ['tasks',    t('مهامي'),        'student/tasks',    'clipboard'],
            ['exams',    t('اختباراتي'),    'student/exams',    'check-badge'],
        ]],
        [t('المتابعة'), [
            ['reports',      t('المتابعة والتقارير'), 'student/reports',      'chart'],
            /* خريطة الإتقان: يقرؤها المسؤول في `taqdar_admin/mastery`
               ويقاس عليها الطالب وتصدر بها شهادته — ولا يراها هو. */
            ['mastery',      t('خريطة إتقاني'),       'student/mastery',      'target'],
            ['calendar',     t('التقويم'),            'student/calendar',     'calendar'],
            ['certificates', t('الشهادات'),           'student/certificates', 'award'],
        ]],
        [t('الدعم والتواصل'), [
            ['on_demand',     t('حصص بالطلب'), 'student/on-demand',     'video'],
            ['messages',      t('رسائلي'),     'student/messages',      'chat'],
            ['notifications', t('الإشعارات'),  'student/notifications', 'bell'],
        ]],
        [t('حسابي'), [
            /* الملف الشخصي غير الإعدادات: هذا ما بلغه الطالب — إتقانه
               وشهاداته وسلسلته — وتلك ما يضبطه. وكانا شاشة واحدة. */
            ['profile',      t('ملفي'),      'student/profile',      'user'],
            ['subscription', t('اشتراكي'),   'student/subscription', 'wallet'],
            ['settings',     t('الإعدادات'), 'student/settings',     'cog'],
        ]],
    ],
    'teacher' => [
        ['', [
            ['dashboard', t('اللوحة'), 'teacher', 'home'],
        ]],
        [t('التدريس'), [
            /* كما في بوابة الطالب: الكورس وعاء والدرس ما فيه، ولكل منهما
               شاشة. وكان الدرس بلا شاشة هنا أصلا — رقم في جدول وخمسة في زاوية. */
            ['courses',   t('كورساتي'),     'teacher/courses',   'book'],
            ['lessons',   t('دروسي'),       'teacher/lessons',   'play'],
            ['upload',    t('رفع الدروس'),      'teacher/upload',    'upload'],
            /* الاستوديو بعد الرفع مباشرة: هو الخطوة التالية في دورة
               الإنتاج — يرفع، ثم يولد ويعتمد، ثم يرسل للمراجعة. */
            ['studio',    t('استوديو المحتوى'), 'teacher/studio',    'pen'],
            ['questions', t('بنك الأسئلة'),     'teacher/questions', 'help'],
        ]],
        [t('الطلاب والتصحيح'), [
            ['marking',   t('الواجبات والتصحيح'), 'teacher/marking',   'clipboard'],
            ['students',  t('طلابي'),             'teacher/students',  'users'],
            ['sessions',  t('الحصص'),             'teacher/sessions',  'video'],
            /* التحليلات مع الطلاب لا مع التدريس: الخريطة الحرارية تقرأ
               سلوك الطلاب لا حال المحتوى، وكل صف فيها ينتهي بإجراء. */
            ['analytics', t('التحليلات'),         'teacher/analytics', 'chart'],
        ]],
        /* التواصل: كان المعلم يرسل إلى طلابه من شاشة «طلابي» ولا يملك
           صندوقا يقرأ فيه ردهم، وكانت إشعاراته تعد في `Taqdar::counts()`
           ولا تعرض في شاشة ولا في جرس. */
        [t('التواصل'), [
            ['messages',      t('الرسائل'),   'teacher/messages',      'chat'],
            ['notifications', t('الإشعارات'), 'teacher/notifications', 'bell'],
        ]],
        [t('حسابي'), [
            ['wallet',   t('المحفظة والأرباح'), 'teacher/wallet',   'wallet'],
            ['settings', t('الإعدادات'),        'teacher/settings', 'cog'],
        ]],
    ],
    'parent' => [
        ['', [
            ['children', t('أبنائي'), 'parent', 'users'],
        ]],
        [t('المتابعة'), [
            ['reports', t('التقارير'),         'parent/reports', 'chart'],
            ['weekly',  t('التقرير الأسبوعي'), 'parent/weekly',  'clipboard'],
        ]],
        [t('التواصل'), [
            ['messages', t('الرسائل'),   'parent/messages', 'chat'],
            ['alerts',   t('الإشعارات'), 'parent/alerts',   'bell'],
        ]],
        [t('حسابي'), [
            /* الدفع فعل والمدفوعات سجل، وهما بندان لا بند: كان ولي
               الأمر لا يستطيع الشراء لابنه أصلا — `checkout()` يرده
               برسالة «الاشتراك لحسابات الطلاب». */
            ['pay',      t('ادفع عن ابنك'), 'parent/pay',      'card'],
            ['payments', t('المدفوعات'),    'parent/payments', 'wallet'],
            ['settings', t('الإعدادات'),    'parent/settings', 'cog'],
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
    'reviews'       => t('سؤال مستحق اليوم'),
    'tasks'         => t('مهمة مستحقة'),
    'messages'      => t('رسالة غير مقروءة'),
    'notifications' => t('إشعار غير مقروء'),
    'alerts'        => t('إشعار غير مقروء'),
];
?>
<div class="tq-rail-scrim" data-tq-scrim hidden></div>

<aside class="tq-rail" data-tq-rail>

    <div class="tq-rail__brand">
        <a class="tq-logo tq-rail__logo" href="<?php echo base_url(); ?>">
            <img src="<?php echo tq_asset('brand/icon.png'); ?>" alt="" width="36" height="36" aria-hidden="true">
            <span class="tq-rail__text">
                <span class="tq-strong" style="color:var(--tq-navy);display:block;line-height:1.2"><?php echo t('تقدر'); ?></span>
                <span class="tq-micro"><?php echo t('منصة التعليم الذكي'); ?></span>
            </span>
            <span class="tq-sr"><?php echo t('تقدر — الصفحة الرئيسية'); ?></span>
        </a>

        <?php /* الطي كان مكتوبا في الورقة (`[data-collapsed]`) وبلا زر يضبطه —
                 نصف ميزة لا تعمل. وهذا زرها، ويحفظ اختياره على الجهاز. */ ?>
        <button class="tq-iconbtn tq-rail__collapse" type="button"
                data-tq-rail-collapse aria-expanded="true"
                aria-label="<?php echo te('طي القائمة الجانبية'); ?>" title="<?php echo te('طي القائمة الجانبية'); ?>">
            <?php echo tq_icon('chev-next', 18); ?>
        </button>

        <?php /* على الجوال يغلق الدرج من داخله: زر الفتح في الترويسة يختفي
                 خلف الطبقة السوداء، فمن فتح لا يجد بابا يخرج منه إلا بالضغط
                 على الطبقة — وهو تصرف لا يعلنه شيء. */ ?>
        <button class="tq-iconbtn tq-rail__close" type="button"
                data-tq-rail-toggle aria-label="<?php echo te('إغلاق القائمة'); ?>" title="<?php echo te('إغلاق القائمة'); ?>">
            <?php echo tq_icon('x', 18); ?>
        </button>
    </div>

    <nav class="tq-rail__nav" aria-label="<?php echo te('التنقل الرئيسي'); ?>">
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
                        <span class="tq-sr"><?php echo html_escape($tq_count_sr[$key] ?? t('عنصر غير مقروء')); ?></span>
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
        /* الأيام تعد بالتقويم لا بالطابع الزمني. `activate()` تكتب في
           `ends_at` **ساعة التفعيل** لا منتصف الليل، وبطاقة الاشتراك تعرض
           تاريخها وحده (`date('Y-m-d', …)`). فقسمة الفارق على 86400 ثم
           `ceil` ترفع الرقم يوما كاملا ما دامت ساعة اليوم أقل من ساعة
           التفعيل: الطالب يقرأ «يتبقى 359 يوما» صباحا و«358» مساء، وكلاهما
           لا يوافق «ينتهي في 2027-08-23» المكتوب أمامه. فالطرح بين
           **تاريخين** — ثابت مهما فتحت الشاشة، وموافق للتاريخ المعروض. */
        $tq_rail_days = 0;
        if ($tq_rail_sub && !empty($tq_rail_sub['ends_at'])) {
            $tq_rail_end = strtotime(substr((string) $tq_rail_sub['ends_at'], 0, 10));
            if ($tq_rail_end) {
                $tq_rail_days = (int) round(($tq_rail_end - strtotime(date('Y-m-d'))) / 86400);
                if ($tq_rail_days < 0) $tq_rail_days = 0;
            }
        }
    ?>
        <div class="tq-rail__foot">
            <div class="tq-rail__promo tq-pastel tq-pastel--mint">
                <?php if ($tq_rail_sub): ?>
                    <span class="tq-pastel__label tq-micro"><?php echo t('اشتراكك نشط'); ?></span>
                    <p class="tq-pastel__body tq-strong" style="margin:var(--tq-space-xs) 0 var(--tq-space-m)">
                        <?php if ($tq_rail_days > 0): ?>
                            <?php echo t('يتبقى ____ يوما', TQ_LRI . $tq_rail_days . TQ_PDI); ?>
                        <?php else: ?>
                            <?php echo t('ينتهي اليوم'); ?>
                        <?php endif; ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm tq-btn--block" href="<?php echo base_url('student/subscription'); ?>">
                        <?php echo t('تفاصيل الاشتراك'); ?>
                    </a>
                <?php else: ?>
                    <span class="tq-pastel__label tq-micro"><?php echo t('اشتراكك'); ?></span>
                    <p class="tq-pastel__body tq-strong" style="margin:var(--tq-space-xs) 0 var(--tq-space-m)">
                        <?php echo t('افتح كل برامج صفك'); ?>
                    </p>
                    <a class="tq-btn tq-btn--mastery tq-btn--sm tq-btn--block" href="<?php echo base_url('plans'); ?>">
                        <?php echo t('عرض الباقات'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php /* البديل في الوضع المطوي: البطاقة تختفي ويبقى بابها —
                     أيقونة واحدة تقود إلى الوجهة نفسها. */ ?>
            <a class="tq-rail__item tq-rail__foot-mini"
               href="<?php echo base_url($tq_rail_sub ? 'student/subscription' : 'plans'); ?>"
               title="<?php echo $tq_rail_sub ? t('تفاصيل الاشتراك') : t('عرض الباقات'); ?>">
                <span class="tq-rail__icon" aria-hidden="true"><?php echo tq_icon('wallet'); ?></span>
                <span class="tq-rail__text"><?php echo $tq_rail_sub ? t('تفاصيل الاشتراك') : t('عرض الباقات'); ?></span>
            </a>
        </div>
    <?php endif; ?>
</aside>
