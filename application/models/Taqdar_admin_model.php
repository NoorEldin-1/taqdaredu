<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * وحدات تقدر في لوحة الإدارة: السجل والقراءة والكتابة.
 *
 * الوحدات موصوفة لا مكتوبة: جدول واحد يصف الحقول، وشاشتان عامتان تعرضانه.
 * البديل — ثماني شاشات متطابقة منسوخة — يتعفن عند أول تعديل في حقل مشترك،
 * وقد رأينا ما يفعله النسخ في هذا السكربت نفسه.
 *
 * والأموال هنا **هللات كأعداد صحيحة**؛ العرض وحده يقسم على مئة. أي تخزين
 * عشري للنقود يفقد هللات عند الجمع، ولا يظهر الخطأ إلا في كشف حساب معلم.
 */
class Taqdar_admin_model extends CI_Model
{
    /* =====================================================================
       السجل
       ===================================================================== */

    public function spec($key = null)
    {
        $modules = array(

            'subjects' => array(
                'table'    => 'subjects',
                'title'    => 'المواد الدراسية',
                'lead'     => 'المادة أعلى تصنيف في المنهج، ويبنى فوقها المسار والصف.',
                'icon'     => 'book',
                'order_by' => array('order' => 'ASC'),
                'fields'   => array(
                    'name_ar' => array('label' => 'الاسم بالعربية', 'type' => 'text', 'required' => true, 'list' => true),
                    'name_en' => array('label' => 'الاسم بالإنجليزية', 'type' => 'text', 'list' => true, 'ltr' => true),
                    'order'   => array('label' => 'الترتيب', 'type' => 'number', 'default' => 0, 'list' => true),
                    'active'  => array('label' => 'مفعلة', 'type' => 'bool', 'default' => 1, 'list' => true),
                ),
            ),

            'grades' => array(
                'table'    => 'grades',
                'title'    => 'الصفوف الدراسية',
                'lead'     => 'الصف يحدد مستوى الطالب، وعليه تبنى المسارات وإسناد المعلمين.',
                'icon'     => 'layers',
                'order_by' => array('order' => 'ASC'),
                'fields'   => array(
                    'name_ar' => array('label' => 'الاسم بالعربية', 'type' => 'text', 'required' => true, 'list' => true),
                    'name_en' => array('label' => 'الاسم بالإنجليزية', 'type' => 'text', 'list' => true, 'ltr' => true),
                    'order'   => array('label' => 'الترتيب', 'type' => 'number', 'default' => 0, 'list' => true),
                    'active'  => array('label' => 'مفعل', 'type' => 'bool', 'default' => 1, 'list' => true),
                ),
            ),

            'paths' => array(
                'table'    => 'paths',
                'title'    => 'المسارات التعليمية',
                'lead'     => 'المسار = مادة + صف، وهو ما يشترك فيه الطالب فعليا.',
                'icon'     => 'route',
                'order_by' => array('id' => 'DESC'),
                'fields'   => array(
                    'title'          => array('label' => 'عنوان المسار', 'type' => 'text', 'required' => true, 'list' => true),
                    'subject_id'     => array('label' => 'المادة', 'type' => 'ref', 'ref' => 'subjects', 'required' => true, 'list' => true),
                    'grade_id'       => array('label' => 'الصف', 'type' => 'ref', 'ref' => 'grades', 'required' => true, 'list' => true),
                    'teacher_id'     => array('label' => 'المعلم المسؤول', 'type' => 'ref', 'ref' => 'teachers', 'list' => true),
                    'price'          => array('label' => 'السعر', 'type' => 'money', 'default' => 0, 'list' => true,
                                              'hint' => 'يدخل بالريال ويخزن بالهللات. اتركه صفرا للمسار المجاني.'),
                    'status'         => array('label' => 'الحالة', 'type' => 'enum', 'options' => array('draft' => 'مسودة', 'published' => 'منشور'), 'default' => 'draft', 'list' => true),
                    'teacher_share_percent' => array('label' => 'نسبة المعلم %', 'type' => 'number', 'list' => true,
                                              'hint' => 'حصة معلم هذا المسار من سعره. اتركه فارغا ليأخذ النسبة الافتراضية العامة.'),
                    'expected_weeks' => array('label' => 'المدة المتوقعة (أسابيع)', 'type' => 'number', 'default' => 0),
                    'course_id'      => array('label' => 'الدورة المرتبطة', 'type' => 'ref', 'ref' => 'courses',
                                              'hint' => 'تربط المسار بمحتوى دورة قائمة في السكربت، فتستعمل دروسها.'),
                ),
            ),

            'milestones' => array(
                'table'    => 'milestones',
                'title'    => 'المحطات',
                'lead'     => 'المحطة تجمع دروسا داخل مسار وتختم بتقييم.',
                'icon'     => 'flag',
                'order_by' => array('path_id' => 'ASC', 'order' => 'ASC'),
                'fields'   => array(
                    'path_id'                  => array('label' => 'المسار', 'type' => 'ref', 'ref' => 'paths', 'required' => true, 'list' => true),
                    'order'                    => array('label' => 'الترتيب', 'type' => 'number', 'default' => 0, 'list' => true),
                    'title'                    => array('label' => 'عنوان المحطة', 'type' => 'text', 'required' => true, 'list' => true),
                    'section_id'               => array('label' => 'القسم المرتبط', 'type' => 'ref', 'ref' => 'sections'),
                    'checkpoint_assessment_id' => array('label' => 'تقييم المحطة', 'type' => 'ref', 'ref' => 'assessments'),
                ),
            ),

            'objectives' => array(
                'table'    => 'objectives',
                'title'    => 'الأهداف التعليمية',
                'lead'     => 'الهدف يصف ما يتقنه الطالب في الدرس، وإليه تنسب أسئلة المراجعة.',
                'icon'     => 'target',
                'order_by' => array('lesson_id' => 'ASC', 'at_second' => 'ASC'),
                'note'     => 'بغير أهداف مربوطة بالأسئلة لا تستطيع بوابة الإتقان أن تحكم، فيبقى كل درس بعد الأول مقفلا. هذه الشاشة هي ما يفك ذلك.',
                'fields'   => array(
                    'lesson_id' => array('label' => 'الدرس', 'type' => 'ref', 'ref' => 'lessons', 'required' => true, 'list' => true),
                    'text'      => array('label' => 'نص الهدف', 'type' => 'text', 'required' => true, 'list' => true,
                                         'hint' => 'اكتبه فعلا قابلا للقياس: «يحسب مساحة المثلث» لا «يفهم المثلثات».'),
                    'at_second' => array('label' => 'لحظة الشرح (ثانية)', 'type' => 'seconds', 'default' => 0, 'list' => true,
                                         'hint' => 'إليها يعيد النظام الطالب حين يخطئ في سؤال هذا الهدف.'),
                ),
            ),

            'assessments' => array(
                'table'    => 'assessments',
                'title'    => 'التقييمات',
                'lead'     => 'الاختبار والامتحان والواجب. وبالامتحان وحده تصدر الشهادة.',
                'icon'     => 'check-badge',
                'order_by' => array('id' => 'DESC'),
                'note'     => 'نوع «المراجعة» يولده المحرك تلقائيا بشروطه ولا ينشأ يدويا — تقييم مراجعة مصنوع باليد لا يعرفه المحرك ولا يربطه بأهدافه.',
                'fields'   => array(
                    'type'           => array('label' => 'النوع', 'type' => 'enum', 'default' => 'quiz', 'list' => true,
                                              'options' => array('quiz' => 'اختبار', 'exam' => 'امتحان (تصدر به شهادة)', 'homework' => 'واجب')),
                    'lesson_id'      => array('label' => 'الدرس', 'type' => 'ref', 'ref' => 'lessons', 'list' => true,
                                              'hint' => 'الدرس الذي يجرى عليه التقييم.'),
                    'path_id'        => array('label' => 'المسار', 'type' => 'ref', 'ref' => 'paths', 'list' => true,
                                              'hint' => 'يظهر في الشهادة بوصفه ما أتقن.'),
                    'milestone_id'   => array('label' => 'المحطة', 'type' => 'ref', 'ref' => 'milestones'),
                    'pass_mark'      => array('label' => 'درجة النجاح', 'type' => 'number', 'default' => 3, 'list' => true,
                                              'hint' => 'عدد الإجابات الصحيحة اللازمة للاجتياز.'),
                    'time_limit_sec' => array('label' => 'المدة (ثوان)', 'type' => 'number', 'default' => 0,
                                              'hint' => 'صفر = بلا حد زمني.'),
                ),
            ),

            'competitions' => array(
                'table'    => 'competitions',
                'title'    => 'المسابقات',
                'lead'     => 'تحديات دورية على نمط أسئلة نافس. المفتوحة وحدها تظهر للطلاب.',
                'icon'     => 'target',
                'order_by' => array('tq_order' => 'ASC', 'id' => 'DESC'),
                'note'     => 'مسابقات المنصة غير مرتبطة بهيئة تقويم التعليم، والإيضاح مكتوب في الصفحة العامة — فلا تصغ عنوانا يوهم ارتباطا رسميا.',
                'fields'   => array(
                    'title'       => array('label' => 'العنوان', 'type' => 'text', 'required' => true, 'list' => true),
                    'slug'        => array('label' => 'المسمى', 'type' => 'text',
                                           'hint' => 'بحروف لاتينية — الروابط العربية ترد قبل أن تصل.'),
                    'tagline'     => array('label' => 'سطر التعريف', 'type' => 'text', 'list' => true),
                    'description' => array('label' => 'الوصف', 'type' => 'textarea'),
                    'category_id' => array('label' => 'المرحلة', 'type' => 'ref', 'ref' => 'categories', 'list' => true),
                    'starts_at'   => array('label' => 'تبدأ', 'type' => 'text', 'hint' => 'YYYY-MM-DD'),
                    'ends_at'     => array('label' => 'تنتهي', 'type' => 'text', 'hint' => 'YYYY-MM-DD'),
                    'seats'       => array('label' => 'المقاعد', 'type' => 'number', 'default' => 0,
                                           'hint' => 'صفر = بلا حد.'),
                    'prize'       => array('label' => 'الجائزة', 'type' => 'text'),
                    'status'      => array('label' => 'الحالة', 'type' => 'enum', 'default' => 'draft', 'list' => true,
                                           'options' => array('draft' => 'مسودة', 'open' => 'مفتوحة',
                                                              'closed' => 'أغلق التسجيل', 'done' => 'انتهت')),
                    'tq_order'    => array('label' => 'الترتيب', 'type' => 'number', 'default' => 0),
                ),
            ),

            'teacher_assignments' => array(
                'table'    => 'teacher_assignments',
                'title'    => 'إسناد المعلمين',
                'lead'     => 'الإسناد يحدد ما يملك المعلم حق العمل فيه: مادة وصفا وصلاحية.',
                'icon'     => 'user-check',
                'order_by' => array('id' => 'DESC'),
                'fields'   => array(
                    'teacher_id'        => array('label' => 'المعلم', 'type' => 'ref', 'ref' => 'teachers', 'required' => true, 'list' => true),
                    'subject_id'        => array('label' => 'المادة', 'type' => 'ref', 'ref' => 'subjects', 'required' => true, 'list' => true),
                    'grade_id'          => array('label' => 'الصف', 'type' => 'ref', 'ref' => 'grades', 'list' => true),
                    'can_publish'       => array('label' => 'ينشر المحتوى', 'type' => 'bool', 'default' => 0, 'list' => true),
                    'can_take_sessions' => array('label' => 'يستقبل حصصا', 'type' => 'bool', 'default' => 0, 'list' => true),
                ),
            ),

            'parent_links' => array(
                'table'    => 'parent_links',
                'title'    => 'روابط أولياء الأمور',
                'lead'     => 'الرابط يمنح ولي الأمر اطلاعا على تقدم ابنه — ولا يفعل إلا بموافقة موثقة.',
                'icon'     => 'users',
                'order_by' => array('id' => 'DESC'),
                'note'     => 'الموافقة بيان قانوني لا خانة شكلية: تاريخها هو ما يحتج به إن اعترض على اطلاع ولي الأمر. اترك الحالة «معلقة» حتى توثق.',
                'fields'   => array(
                    'parent_user_id' => array('label' => 'ولي الأمر', 'type' => 'ref', 'ref' => 'users', 'required' => true, 'list' => true),
                    'student_id'     => array('label' => 'الطالب', 'type' => 'ref', 'ref' => 'users', 'required' => true, 'list' => true),
                    'status'         => array('label' => 'الحالة', 'type' => 'enum', 'default' => 'pending', 'list' => true,
                                              'options' => array('pending' => 'معلقة', 'active' => 'نشطة', 'revoked' => 'ملغاة')),
                    'consent_at'     => array('label' => 'تاريخ الموافقة', 'type' => 'datetime', 'list' => true),
                    'scope'          => array('label' => 'نطاق الاطلاع', 'type' => 'textarea',
                                              'hint' => 'اتركه فارغا ليطلع على التقدم كاملا.'),
                ),
            ),

            'plans' => array(
                'table'    => 'plans',
                'title'    => 'الباقات',
                'lead'     => 'الباقة تحدد السعر والمدة وما يفتحه الاشتراك.',
                'icon'     => 'package',
                'order_by' => array('order' => 'ASC'),
                'note'     => 'تعديل باقة لا يمس اشتراكا قائما: السعر والنطاق ينسخان وقت الشراء، فمن اشترك أمس يبقى على ما دفع.',
                'fields'   => array(
                    'name_ar'       => array('label' => 'الاسم بالعربية', 'type' => 'text', 'required' => true, 'list' => true),
                    'name_en'       => array('label' => 'الاسم بالإنجليزية', 'type' => 'text', 'ltr' => true),
                    'code'          => array('label' => 'الرمز', 'type' => 'text', 'required' => true, 'list' => true, 'ltr' => true,
                                             'hint' => 'رمز لاتيني فريد لا يتغير: free · monthly · quarterly · annual.'),
                    'price'         => array('label' => 'السعر', 'type' => 'money', 'default' => 0, 'list' => true,
                                             'hint' => 'يدخل بالريال ويخزن بالهللات. المجانية تحدد بالدورة «مجانية» لا بخلو السعر؛ وباقة مدفوعة بلا سعر ترفض.'),
                    'period'        => array('label' => 'الدورة', 'type' => 'enum', 'default' => 'monthly', 'list' => true,
                                             'options' => array('free' => 'مجانية', 'monthly' => 'شهرية', 'quarterly' => 'ربع سنوية', 'annual' => 'سنوية')),
                    'duration_days' => array('label' => 'المدة (أيام)', 'type' => 'number', 'default' => 30, 'list' => true,
                                             'hint' => 'المدة الفعلية للاشتراك — هي ما يحسب عليه تاريخ الانتهاء لا اسم الدورة.'),
                    'scope'         => array('label' => 'النطاق', 'type' => 'enum', 'default' => 'all', 'list' => true,
                                             'options' => array('all' => 'كل المحتوى', 'trial' => 'دروس تجريبية فقط',
                                                 'subject' => 'مادة واحدة', 'path' => 'مسار واحد')),
                    'scope_id'      => array('label' => 'رقم المادة أو المسار', 'type' => 'number', 'default' => 0,
                                             'hint' => 'يترك صفرا حين يكون النطاق «كل المحتوى».'),
                    'note'          => array('label' => 'سطر الوصف', 'type' => 'text',
                                             'hint' => 'سطر قصير تحت اسم الباقة في صفحة الأسعار.'),
                    'featured'      => array('label' => 'الأكثر ملاءمة', 'type' => 'bool', 'default' => 0, 'list' => true,
                                             'hint' => 'باقة واحدة فقط تبرز — إبراز الكل إبراز لا شيء.'),
                    'features'      => array('label' => 'المزايا', 'type' => 'lines',
                                             'hint' => 'ميزة في كل سطر — تعرض في بطاقة الباقة كما تكتب هنا.'),
                    'active'        => array('label' => 'متاحة', 'type' => 'bool', 'default' => 1, 'list' => true),
                    'order'         => array('label' => 'الترتيب', 'type' => 'number', 'default' => 0),
                ),
            ),

            'invoices' => array(
                'table'    => 'invoices',
                'title'    => 'الفواتير',
                'lead'     => 'كل فاتورة صدرت، ومن دفعها ومتى.',
                'icon'     => 'file-text',
                'order_by' => array('id' => 'DESC'),
                'readonly' => true,
                'nodelete' => true,
                'note'     => 'الفاتورة لا تحذف ولا تحرر — سجل مالي قابل للتعديل لا يصلح دليلا.',
                'fields'   => array(
                    'invoice_no'      => array('label' => 'رقم الفاتورة', 'type' => 'text', 'list' => true, 'ltr' => true),
                    'user_id'         => array('label' => 'المشترك', 'type' => 'ref', 'ref' => 'users', 'list' => true),
                    'subscription_id' => array('label' => 'الاشتراك', 'type' => 'number', 'list' => true),
                    'total'           => array('label' => 'الإجمالي', 'type' => 'money', 'list' => true),
                    'status'          => array('label' => 'الحالة', 'type' => 'enum', 'list' => true,
                                               'options' => array('unpaid' => 'غير مدفوعة', 'paid' => 'مدفوعة', 'refunded' => 'مستردة')),
                    'method'          => array('label' => 'الوسيلة', 'type' => 'text', 'list' => true, 'ltr' => true),
                    'issued_at'       => array('label' => 'تاريخ الإصدار', 'type' => 'datetime', 'list' => true),
                    'paid_at'         => array('label' => 'تاريخ السداد', 'type' => 'datetime', 'list' => true),
                ),
            ),

            'wallets' => array(
                'table'    => 'wallets',
                'title'    => 'المحافظ',
                'lead'     => 'رصيد كل معلم: المتاح والمعلق والمحجوز.',
                'icon'     => 'wallet',
                'order_by' => array('id' => 'DESC'),
                'readonly' => true,
                'note'     => 'الأرصدة تقرأ ولا تحرر يدويا — تتغير بالقيود وحدها ليبقى الرصيد مساويا لمجموعها.',
                'fields'   => array(
                    'owner_user_id'     => array('label' => 'صاحب المحفظة', 'type' => 'ref', 'ref' => 'users', 'list' => true),
                    'balance_available' => array('label' => 'المتاح', 'type' => 'money', 'list' => true),
                    'balance_pending'   => array('label' => 'المعلق', 'type' => 'money', 'list' => true),
                    'balance_locked'    => array('label' => 'المحجوز', 'type' => 'money', 'list' => true),
                ),
            ),

            'wallet_entries' => array(
                'table'    => 'wallet_entries',
                'title'    => 'قيود المحافظ',
                'lead'     => 'كل حركة على أي محفظة، بترتيب زمني.',
                'icon'     => 'receipt',
                'order_by' => array('id' => 'DESC'),
                'readonly' => true,
                'fields'   => array(
                    'wallet_id'   => array('label' => 'المحفظة', 'type' => 'number', 'list' => true),
                    'type'        => array('label' => 'النوع', 'type' => 'text', 'list' => true),
                    'amount'      => array('label' => 'المبلغ', 'type' => 'money', 'list' => true),
                    'ref'         => array('label' => 'المرجع', 'type' => 'text', 'list' => true, 'ltr' => true),
                    'released_at' => array('label' => 'تاريخ الإفراج', 'type' => 'datetime', 'list' => true),
                ),
            ),

            /* الكتب: صفحة `/books` منشورة للعامة وتقرأ من هذا الجدول
               (`Taqdar_site_model::books()`)، ولم تكن له شاشة واحدة في
               اللوحة — فكان كتاب المنهج يضاف بيد في phpMyAdmin، أو لا
               يضاف. وأي محتوى عام بلا شاشة إدارة يعني نشرا لكل تعديل. */
            'books' => array(
                'table'    => 'books',
                'title'    => 'الكتب',
                'lead'     => 'كتب المنهج التي تعرض في صفحة الكتب العامة.',
                'icon'     => 'book',
                'order_by' => array('tq_order' => 'ASC', 'id' => 'DESC'),
                'fields'   => array(
                    'title'       => array('label' => 'العنوان', 'type' => 'text', 'required' => true, 'list' => true),
                    'slug'        => array('label' => 'المسمى في الرابط', 'type' => 'text', 'ltr' => true,
                                           'hint' => 'حروف لاتينية وشرطات. يترك فارغا ليولد من العنوان.'),
                    'subject'     => array('label' => 'المادة', 'type' => 'text', 'list' => true),
                    'category_id' => array('label' => 'المرحلة', 'type' => 'ref', 'ref' => 'categories', 'list' => true),
                    'author'      => array('label' => 'المؤلف أو الجهة', 'type' => 'text', 'list' => true),
                    'pages'       => array('label' => 'عدد الصفحات', 'type' => 'number', 'default' => 0),
                    'tone'        => array('label' => 'لون الغلاف', 'type' => 'enum', 'default' => 'math',
                                           'options' => array('math' => 'رياضيات', 'arabic' => 'لغة عربية',
                                                              'science' => 'علوم', 'islamic' => 'دراسات إسلامية',
                                                              'english' => 'لغة إنجليزية')),
                    'cover'       => array('label' => 'صورة الغلاف', 'type' => 'text', 'ltr' => true,
                                           'hint' => 'مسار الملف داخل uploads/'),
                    'file'        => array('label' => 'ملف الكتاب', 'type' => 'text', 'ltr' => true,
                                           'hint' => 'مسار PDF داخل uploads/. بلا ملف لا يظهر زر التحميل.'),
                    'description' => array('label' => 'الوصف', 'type' => 'textarea'),
                    'status'      => array('label' => 'الحالة', 'type' => 'enum', 'default' => 'draft', 'list' => true,
                                           'options' => array('draft' => 'مسودة', 'published' => 'منشور')),
                    'tq_order'    => array('label' => 'الترتيب', 'type' => 'number', 'default' => 0),
                ),
            ),

            /* المشاركون: كانت المسابقة تنشأ من اللوحة وتفتح للتسجيل
               (`Taqdar::competition_join`) ولا شاشة تقول من سجل فيها.
               تقرأ ولا تحرر: الاشتراك فعل صاحبه، وتحريره من اللوحة
               يجعل كشف المشاركين شيئا آخر غير ما جرى. */
            'competition_entries' => array(
                'table'    => 'competition_entries',
                'title'    => 'المشاركون في المسابقات',
                'lead'     => 'من سجل في كل مسابقة، ومتى، وبأي نتيجة.',
                'icon'     => 'trophy',
                'order_by' => array('id' => 'DESC'),
                'readonly' => true,
                'note'     => 'الاشتراك فعل صاحبه فلا يحرر ولا يحذف من هنا — كشف المشاركين يقرأ ما جرى لا ما أريد له أن يجري.',
                'fields'   => array(
                    'competition_id' => array('label' => 'المسابقة', 'type' => 'ref', 'ref' => 'competitions', 'list' => true),
                    'user_id'        => array('label' => 'المشترك', 'type' => 'ref', 'ref' => 'users', 'list' => true),
                    'score'          => array('label' => 'النتيجة', 'type' => 'number', 'list' => true),
                    'created_at'     => array('label' => 'تاريخ التسجيل', 'type' => 'datetime', 'list' => true),
                ),
            ),

            'audit_log' => array(
                'table'    => 'audit_log',
                'title'    => 'سجل التدقيق',
                'lead'     => 'من غير ماذا ومتى ومن أي عنوان.',
                'icon'     => 'shield',
                'order_by' => array('id' => 'DESC'),
                'readonly' => true,
                'nodelete' => true,
                'note'     => 'السجل لا يحذف ولا يحرر؛ سجل قابل للتعديل لا يصلح دليلا.',
                'fields'   => array(
                    'at'       => array('label' => 'التاريخ', 'type' => 'datetime', 'list' => true),
                    'actor_id' => array('label' => 'المنفذ', 'type' => 'ref', 'ref' => 'users', 'list' => true),
                    'action'   => array('label' => 'الإجراء', 'type' => 'text', 'list' => true),
                    'entity'   => array('label' => 'الكيان', 'type' => 'text', 'list' => true, 'ltr' => true),
                    'ip'       => array('label' => 'العنوان', 'type' => 'text', 'list' => true, 'ltr' => true),
                ),
            ),
        );

        if ($key === null) return $modules;
        return isset($modules[$key]) ? $modules[$key] : null;
    }

    /* =====================================================================
       القراءة
       ===================================================================== */

    public function listing($key, $limit = 200, $offset = 0)
    {
        $spec = $this->spec($key);
        if (!$spec) return array();

        foreach ($spec['order_by'] as $col => $dir) {
            $this->db->order_by($col, $dir);
        }
        return $this->db->limit($limit, $offset)->get($spec['table'])->result_array();
    }

    public function count_rows($key)
    {
        $spec = $this->spec($key);
        return $spec ? (int) $this->db->count_all_results($spec['table']) : 0;
    }

    public function row($key, $id)
    {
        $spec = $this->spec($key);
        if (!$spec) return null;
        return $this->db->where('id', (int) $id)->get($spec['table'])->row_array();
    }

    /**
     * قوائم الاختيار. تحمل مرة واحدة لكل طلب — الشاشة الواحدة قد تعرض
     * الحقل نفسه في مئتي صف، واستعلام لكل صف يقتل الصفحة.
     */
    public function options($ref)
    {
        static $cache = array();
        if (isset($cache[$ref])) return $cache[$ref];

        $out = array();
        switch ($ref) {

            case 'subjects':
            case 'grades':
                $rows = $this->db->order_by('order', 'ASC')->get($ref)->result_array();
                foreach ($rows as $r) $out[$r['id']] = $r['name_ar'] ?: $r['name_en'];
                break;

            case 'paths':
                foreach ($this->db->order_by('id', 'DESC')->get('paths')->result_array() as $r)
                    $out[$r['id']] = $r['title'];
                break;

            case 'teachers':
                $rows = $this->db->where('is_instructor', 1)->order_by('first_name', 'ASC')->get('users')->result_array();
                foreach ($rows as $r) $out[$r['id']] = trim($r['first_name'] . ' ' . $r['last_name']) ?: $r['email'];
                break;

            case 'users':
                $rows = $this->db->order_by('id', 'DESC')->limit(500)->get('users')->result_array();
                foreach ($rows as $r) $out[$r['id']] = trim($r['first_name'] . ' ' . $r['last_name']) . ' — ' . $r['email'];
                break;

            case 'courses':
                foreach ($this->db->order_by('id', 'DESC')->limit(500)->get('course')->result_array() as $r)
                    $out[$r['id']] = $r['title'];
                break;

            case 'lessons':
                // اسم الدرس وحده ملتبس — «مقدمة» تتكرر في كل دورة، فنسبقه بدورته
                $rows = $this->db->select('lesson.id, lesson.title, course.title AS course_title', false)
                                 ->join('course', 'course.id = lesson.course_id', 'left')
                                 ->order_by('lesson.course_id', 'ASC')->limit(1000)
                                 ->get('lesson')->result_array();
                foreach ($rows as $r)
                    $out[$r['id']] = ($r['course_title'] ? $r['course_title'] . ' › ' : '') . $r['title'];
                break;

            case 'sections':
                foreach ($this->db->order_by('id', 'DESC')->limit(500)->get('section')->result_array() as $r)
                    $out[$r['id']] = $r['title'];
                break;

            case 'assessments':
                foreach ($this->db->order_by('id', 'DESC')->limit(500)->get('assessments')->result_array() as $r)
                    $out[$r['id']] = '#' . $r['id'] . ' — ' . $r['type'];
                break;

            /* `categories` مرجع للمرحلة في الكتب والمسابقات وحدها.
               وليست مرجع المنهج — المنهج `subjects` + `grades`، وخلط
               المرجعين هو ما يجعل مادة تظهر تحت مرحلتين مختلفتين. */
            case 'categories':
                foreach ($this->db->where('parent', 0)->order_by('id', 'ASC')->get('category')->result_array() as $r)
                    $out[$r['id']] = $r['name'];
                break;

            case 'competitions':
                foreach ($this->db->order_by('id', 'DESC')->limit(300)->get('competitions')->result_array() as $r)
                    $out[$r['id']] = $r['title'];
                break;
        }

        $cache[$ref] = $out;
        return $out;
    }

    /* =====================================================================
       الكتابة
       ===================================================================== */

    /**
     * يحفظ صفا بعد تنقية كل حقل حسب نوعه المعلن.
     * لا يقرأ من `$_POST` إلا ما ورد في الوصف — فحقل يضاف إلى النموذج
     * من متصفح المستخدم لا يجد له عمودا يكتب فيه.
     *
     * @return array ok|errors
     */
    public function save($key, $id, $post)
    {
        $spec = $this->spec($key);
        if (!$spec || !empty($spec['readonly'])) {
            return array('ok' => false, 'errors' => array('هذه الوحدة للقراءة فقط.'));
        }

        $data   = array();
        $errors = array();

        foreach ($spec['fields'] as $name => $f) {
            $raw = isset($post[$name]) ? $post[$name] : null;

            switch ($f['type']) {

                case 'bool':
                    $data[$name] = !empty($raw) ? 1 : 0;
                    break;

                case 'number':
                case 'seconds':
                    $data[$name] = (int) $raw;
                    break;

                case 'ref':
                    $data[$name] = (int) $raw;
                    break;

                case 'money':
                    // يدخل بالريال ويخزن بالهللات — التقريب مرة واحدة هنا
                    $data[$name] = (int) round(((float) str_replace(',', '', (string) $raw)) * 100);
                    break;

                case 'enum':
                    $data[$name] = isset($f['options'][$raw]) ? $raw : $f['default'];
                    break;

                case 'datetime':
                    $data[$name] = trim((string) $raw) !== '' ? date('Y-m-d H:i:s', strtotime($raw)) : null;
                    break;

                case 'lines':
                    // سطر لكل ميزة في الشاشة، ومصفوفة JSON في القاعدة —
                    // فالعرض لا يحتاج تحليل نص حر ولا يخطئ في سطر فارغ
                    $lines = array_values(array_filter(array_map(
                        'trim', preg_split('/\r\n|\r|\n/', (string) $raw)
                    ), 'strlen'));
                    $data[$name] = json_encode($lines, JSON_UNESCAPED_UNICODE);
                    break;

                default:
                    $data[$name] = trim((string) $raw);
            }

            if (!empty($f['required'])) {
                $empty = ($f['type'] === 'ref') ? ($data[$name] <= 0) : ($data[$name] === '' || $data[$name] === null);
                if ($empty) $errors[] = 'الحقل «' . $f['label'] . '» مطلوب.';
            }
        }

        /* المسمى في الرابط يولد من العنوان متى ترك فارغا.
           كتاب بلا `slug` لا يفتح في صفحته العامة: الرابط يبنى منه،
           فيصير `/books/` بلا معرف — صفحة تعرض الكتب كلها بدل الكتاب. */
        if (in_array($key, array('books', 'competitions'), true)
            && array_key_exists('slug', $data) && trim((string) $data['slug']) === ''
            && !empty($data['title'])) {
            $data['slug'] = $this->slugify($data['title'], $spec['table'], (int) $id);
        }

        // موافقة ولي الأمر: لا تفعل بلا تاريخ موثق
        if ($key === 'parent_links' && $data['status'] === 'active' && empty($data['consent_at'])) {
            $errors[] = 'لا يمكن تفعيل الرابط بلا تاريخ موافقة موثق.';
        }
        if ($key === 'parent_links' && $data['parent_user_id'] === $data['student_id'] && $data['parent_user_id'] > 0) {
            $errors[] = 'لا يكون المستخدم ولي أمر نفسه.';
        }

        if ($errors) return array('ok' => false, 'errors' => $errors);

        $before = $id ? $this->row($key, $id) : null;

        if ($id) {
            $this->db->where('id', (int) $id)->update($spec['table'], $data);
            $new_id = (int) $id;
        } else {
            $this->db->insert($spec['table'], $data);
            $new_id = (int) $this->db->insert_id();
        }

        $this->audit($id ? 'update' : 'create', $spec['table'] . '#' . $new_id, $before, $this->row($key, $new_id));
        return array('ok' => true, 'id' => $new_id);
    }

    /**
     * مسمى فريد للرابط.
     *
     * العنوان عربي والرابط لاتيني، فالحروف العربية تحذف ولا تنقحر —
     * والنقحرة الآلية تنتج `alrhyadyat` وهو ليس أوضح من `book-12` ولا
     * أقصر. فإن لم يبق حرف لاتيني واحد يستعمل اسم الجدول مع الرقم.
     *
     * والفرادة تفحص فعلا: `slug` مكرر يجعل صفحتين تتنازعان رابطا واحدا،
     * فتفتح إحداهما أبدا والأخرى لا تفتح — بلا خطأ ولا رسالة.
     */
    private function slugify($title, $table, $exclude_id = 0)
    {
        $base = strtolower(trim((string) $title));
        $base = preg_replace('/[^a-z0-9]+/u', '-', $base);
        $base = trim((string) $base, '-');
        if ($base === '') $base = $table;

        $slug = $base;
        for ($i = 2; $i < 200; $i++) {
            $this->db->where('slug', $slug);
            if ($exclude_id > 0) $this->db->where('id !=', $exclude_id);
            if ($this->db->count_all_results($table) === 0) return $slug;
            $slug = $base . '-' . $i;
        }
        return $base . '-' . time();
    }

    public function remove($key, $id)
    {
        $spec = $this->spec($key);
        if (!$spec || !empty($spec['nodelete']) || !empty($spec['readonly'])) return false;

        $before = $this->row($key, $id);
        if (!$before) return false;

        $this->db->where('id', (int) $id)->delete($spec['table']);
        $this->audit('delete', $spec['table'] . '#' . (int) $id, $before, null);
        return true;
    }

    /**
     * يقيد الفعل في سجل التدقيق.
     * لا يوقف العملية إن فشل: فقدان سطر سجل أهون من رد حفظ نجح بالفعل.
     */
    public function audit($action, $entity, $before, $after)
    {
        $this->db->insert('audit_log', array(
            'actor_id' => $this->tq_actor_id(),
            'action'   => $action,
            'entity'   => $entity,
            'before'   => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after'    => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip'       => $this->input->is_cli_request() ? 'cli' : $this->input->ip_address(),
            'at'       => date('Y-m-d H:i:s'),
        ));
    }

    /* =====================================================================
       ربط الأسئلة بالأهداف — الشاشة التي تفك السلسلة المسدودة

       الطرفان لا يقعان على درس واحد، وهذا أصل العطب الذي كانت عليه الشاشة:
       السؤال معلق على **درس الاختبار** (`question.quiz_id`)، والهدف معلق
       على **درس الفيديو** (`objectives.lesson_id`) — درسان مختلفان دائما في
       أي دورة حقيقية. فشاشة تسرد أسئلة درس وأهداف الدرس نفسه لا تجد ما تربطه
       إلا حين توضع الأسئلة والأهداف على درس واحد اصطناعا.

       فالنطاق هنا **الدورة**: أسئلة اختبارات الدورة كلها تربط بأهداف دروس
       الدورة كلها. ويبقى القيد قائما — لا يعبر الربط حدود الدورة — لأن
       سؤالا يشير إلى هدف في دورة أخرى يعيد الطالب إلى فيديو لم يشتره.

       ومحرك الإتقان يقرأ العلاقة معكوسة: سؤال ← هدف ← درس الفيديو
       (`JOIN objectives o ON o.id = q.objective_id WHERE o.lesson_id = ?`)،
       فما يحفظ هنا هو ما يجده هناك، بلا وسيط ولا حقل جديد.
       ===================================================================== */

    /**
     * الدورات التي فيها أسئلة أو أهداف، ولكل ما لها وما عليها.
     *
     * «المربوط» يحسب بشرط المحرك لا بمجرد امتلاء `objective_id`:
     * سؤال يشير إلى هدف محذوف أو إلى هدف في دورة أخرى ليس مربوطا في شيء،
     * وعده مربوطا يعطي الإدارة اطمئنانا كاذبا بينما البوابة لا تجد سؤالا.
     */
    public function question_binding_overview()
    {
        // الجدول المشتق لا زينة: `HAVING` على اسم مستعار بلا `GROUP BY`
        // امتداد من امتدادات MySQL يسقط تحت `ONLY_FULL_GROUP_BY`
        $sql = "SELECT * FROM (
                SELECT c.id AS course_id, c.title AS course_title,

                       (SELECT COUNT(*)
                          FROM question q
                          JOIN lesson ql ON ql.id = q.quiz_id
                         WHERE ql.course_id = c.id) AS total_q,

                       (SELECT COUNT(*)
                          FROM question q
                          JOIN lesson ql ON ql.id = q.quiz_id
                          JOIN objectives o ON o.id = q.objective_id
                          JOIN lesson vl ON vl.id = o.lesson_id
                         WHERE ql.course_id = c.id
                           AND vl.course_id = c.id) AS bound_q,

                       (SELECT COUNT(*)
                          FROM objectives o
                          JOIN lesson vl ON vl.id = o.lesson_id
                         WHERE vl.course_id = c.id) AS objectives,

                       (SELECT COUNT(DISTINCT o.lesson_id)
                          FROM objectives o
                          JOIN lesson vl ON vl.id = o.lesson_id
                         WHERE vl.course_id = c.id) AS objective_lessons,

                       (SELECT COUNT(DISTINCT q.quiz_id)
                          FROM question q
                          JOIN lesson ql ON ql.id = q.quiz_id
                         WHERE ql.course_id = c.id) AS quiz_lessons

                FROM course c
                ) t
                WHERE t.total_q > 0 OR t.objectives > 0
                ORDER BY (t.total_q - t.bound_q) DESC, t.total_q DESC, t.course_id DESC";
        return $this->db->query($sql)->result_array();
    }

    /**
     * أسئلة اختبارات الدورة كلها، ومعها درس الاختبار الذي جاءت منه
     * وحالة ارتباطها الحالية — ليعرف العارض أي ربط قائم وأي ربط مكسور.
     */
    public function questions_of_course($course_id)
    {
        $sql = 'SELECT q.`id`, q.`title`, q.`type`, q.`order`, q.`quiz_id`,
                       q.`objective_id`,
                       ql.`title` AS quiz_title, ql.`order` AS quiz_order,
                       o.`text` AS objective_text, o.`lesson_id` AS objective_lesson_id,
                       vl.`course_id` AS objective_course_id
                FROM `question` q
                JOIN `lesson` ql ON ql.`id` = q.`quiz_id`
                LEFT JOIN `objectives` o ON o.`id` = q.`objective_id`
                LEFT JOIN `lesson` vl ON vl.`id` = o.`lesson_id`
                WHERE ql.`course_id` = ?
                ORDER BY ql.`order` ASC, ql.`id` ASC, q.`order` ASC, q.`id` ASC';
        return $this->db->query($sql, array((int) $course_id))->result_array();
    }

    /**
     * أهداف دروس الدورة كلها، مرتبة بدرسها ثم بلحظة الشرح —
     * فالعارض يجمعها بالدرس بلا استعلام ثان لكل درس.
     */
    public function objectives_of_course($course_id)
    {
        $sql = 'SELECT o.`id`, o.`text`, o.`at_second`, o.`lesson_id`,
                       l.`title` AS lesson_title, l.`lesson_type`, l.`order` AS lesson_order
                FROM `objectives` o
                JOIN `lesson` l ON l.`id` = o.`lesson_id`
                WHERE l.`course_id` = ?
                ORDER BY l.`order` ASC, l.`id` ASC, o.`at_second` ASC, o.`id` ASC';
        return $this->db->query($sql, array((int) $course_id))->result_array();
    }

    /**
     * يحفظ الربط بعد التحقق في الخادم من طرفيه معا.
     *
     * لا يوثق بما يصل من النموذج: قائمة `<select>` تحرر في المتصفح،
     * فالمسموح يقرأ من قاعدة البيانات — أسئلة اختبارات هذه الدورة، وأهداف
     * دروس هذه الدورة — وما خرج عن القائمتين يرفض ويحصى ليقال للإدارة.
     *
     * @return array bound|cleared|rejected
     */
    public function bind_questions($course_id, $map)
    {
        $course_id = (int) $course_id;

        $allowed_obj = array();
        foreach ($this->objectives_of_course($course_id) as $o) $allowed_obj[(int) $o['id']] = true;

        $allowed_q = array();
        foreach ($this->questions_of_course($course_id) as $q) $allowed_q[(int) $q['id']] = (int) $q['objective_id'];

        $bound = $cleared = $rejected = 0;

        foreach ($map as $question_id => $objective_id) {
            $question_id  = (int) $question_id;
            $objective_id = (int) $objective_id;

            // سؤال من خارج هذه الدورة: لا يلمس ولو ورد في الطلب
            if (!isset($allowed_q[$question_id])) { $rejected++; continue; }

            // هدف من دورة أخرى — أو هدف لا وجود له — يرفض،
            // وإلا صار السؤال يعيد الطالب إلى فيديو ليس من دورته
            if ($objective_id !== 0 && !isset($allowed_obj[$objective_id])) { $rejected++; continue; }

            if ($allowed_q[$question_id] !== $objective_id) {
                $this->db->where('id', $question_id)
                         ->update('question', array('objective_id' => $objective_id ?: null));
            }

            if ($objective_id === 0) $cleared++; else $bound++;
        }

        $result = array('bound' => $bound, 'cleared' => $cleared, 'rejected' => $rejected);
        $this->audit('bind_questions', 'course#' . $course_id, null, $result);
        return $result;
    }

    /* =====================================================================
       لوحة الحالة
       ===================================================================== */

    public function readiness()
    {
        $out = array();
        foreach ($this->spec() as $key => $spec) {
            $out[$key] = array(
                'title' => $spec['title'],
                'icon'  => isset($spec['icon']) ? $spec['icon'] : 'circle',
                'count' => (int) $this->db->count_all_results($spec['table']),
            );
        }

        // شريط «ربط الأسئلة بالأهداف» في لوحة الجاهزية يقيس ما تقيسه شاشة
        // الربط نفسها، لا `objective_id > 0`: رقم يقول «مكتمل» بينما الشاشة
        // تقول «ناقص» يجعل الإدارة تصدق أيهما أراحها. فالمربوط هنا وهناك
        // واحد: هدف قائم، في درس من الدورة التي جاء منها سؤاله.
        $q = $this->db->query(
            "SELECT COUNT(*) AS t,
                    SUM(CASE WHEN o.id IS NOT NULL AND vl.course_id = ql.course_id
                             THEN 1 ELSE 0 END) AS b
             FROM question q
             LEFT JOIN lesson ql     ON ql.id = q.quiz_id
             LEFT JOIN objectives o  ON o.id  = q.objective_id
             LEFT JOIN lesson vl     ON vl.id = o.lesson_id")->row_array();
        $out['_questions'] = array('total' => (int) $q['t'], 'bound' => (int) $q['b']);
        return $out;
    }


    /* =====================================================================
       نبض المنصة — لوحة القيادة

       الأرقام تقرأ من حيث يجري العمل فعلا: `subscriptions` و`invoices`
       لا `payment`، و`paths` لا `course` وحدها. والمقارنة بالشهر السابق
       لأن رقما بلا مرجع لا يقال عنه جيد ولا سيئ.
       ===================================================================== */

    public function pulse()
    {
        $month = date('Y-m-01 00:00:00');
        $prev  = date('Y-m-01 00:00:00', strtotime('-1 month'));

        return array(
            'students'      => $this->safe_scalar('SELECT COUNT(*) n FROM `users` WHERE `is_instructor` = 0 AND COALESCE(`tq_gate`, "student") = "student"'),
            'teachers'      => $this->safe_scalar('SELECT COUNT(*) n FROM `users` WHERE `is_instructor` = 1'),
            'parents'       => $this->safe_scalar('SELECT COUNT(*) n FROM `users` WHERE `tq_gate` = "parent"'),
            'paths_live'    => $this->safe_scalar('SELECT COUNT(*) n FROM `paths` WHERE `status` = "published"'),
            'paths_draft'   => $this->safe_scalar('SELECT COUNT(*) n FROM `paths` WHERE `status` != "published"'),
            'subs_active'   => $this->safe_scalar('SELECT COUNT(*) n FROM `subscriptions` WHERE `status` = "active"'),
            'revenue_month' => $this->safe_scalar(
                'SELECT COALESCE(SUM(`total`),0) n FROM `invoices` WHERE `status` = "paid" AND `paid_at` >= ?', array($month)),
            'revenue_prev'  => $this->safe_scalar(
                'SELECT COALESCE(SUM(`total`),0) n FROM `invoices` WHERE `status` = "paid" AND `paid_at` >= ? AND `paid_at` < ?',
                array($prev, $month)),
            'lessons'       => $this->safe_scalar('SELECT COUNT(*) n FROM `lesson`'),
            'objectives'    => $this->safe_scalar('SELECT COUNT(*) n FROM `objectives`'),
            'attempts_week' => $this->safe_scalar(
                'SELECT COUNT(*) n FROM `attempts` WHERE `submitted_at` >= ?', array(date('Y-m-d H:i:s', strtotime('-7 days')))),
        );
    }

    /**
     * عدد واحد من استعلام واحد، بلا أن يسقط الصفحة.
     *
     * جداول تقدر لا تنشأ كلها بهجرة: `tutoring_sessions` ينشئه
     * `Taqdar_sessions_model::ensure_schema()` عند أول استعمال، و
     * `wallet_entries` ينشئه `install_schema()`. فاستعلام على جدول لم
     * يستعمل بعد يرمي استثناء يبيض لوحة القيادة كلها — ورقم ناقص أهون.
     */
    private function safe_scalar($sql, $args = array())
    {
        try {
            $q = $this->db->query($sql, $args);
            if (!$q) return 0;
            $r = $q->row_array();
            return (int) ($r['n'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** صفوف من استعلام، بالحماية نفسها. */
    private function safe_rows($sql, $args = array())
    {
        try {
            $q = $this->db->query($sql, $args);
            return $q ? $q->result_array() : array();
        } catch (Throwable $e) {
            return array();
        }
    }

    /* =====================================================================
       الحصص بالطلب
       ===================================================================== */

    public function sessions($status = '')
    {
        $where = '';
        $args  = array();
        if ($status !== '' && in_array($status, array('requested', 'confirmed', 'declined', 'expired', 'live', 'completed', 'refunded'), true)) {
            $where = ' WHERE s.`status` = ?';
            $args[] = $status;
        }

        return $this->safe_rows(
            'SELECT s.*, sl.`starts_at`, sl.`duration_min`,
                    TRIM(CONCAT(COALESCE(st.`first_name`,""), " ", COALESCE(st.`last_name`,""))) student_name,
                    st.`email` student_email,
                    TRIM(CONCAT(COALESCE(te.`first_name`,""), " ", COALESCE(te.`last_name`,""))) teacher_name,
                    o.`text` objective_text
               FROM `tutoring_sessions` s
               LEFT JOIN `availability_slots` sl ON sl.`id` = s.`slot_id`
               LEFT JOIN `users` st ON st.`id` = s.`student_id`
               LEFT JOIN `users` te ON te.`id` = s.`teacher_id`
               LEFT JOIN `objectives` o ON o.`id` = s.`context_objective_id`'
            . $where . ' ORDER BY sl.`starts_at` IS NULL, sl.`starts_at` DESC, s.`id` DESC LIMIT 300',
            $args
        );
    }

    /** عد كل حالة — الشاشة تعرضها مرشحات فوق الجدول. */
    public function session_tally()
    {
        $out = array('requested' => 0, 'confirmed' => 0, 'declined' => 0,
                     'expired' => 0, 'live' => 0, 'completed' => 0, 'refunded' => 0);
        foreach ($this->safe_rows('SELECT `status`, COUNT(*) c FROM `tutoring_sessions` GROUP BY `status`') as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * إلغاء حصة: الحصة تلغى **ووقتها يحرر**.
     *
     * إلغاء يترك الفسحة `booked` يفقد المعلم ساعة من أسبوعه بلا سبب،
     * ولا يظهر ذلك في شاشة — يظهر في أنه لا أحد يستطيع حجزها.
     */
    public function cancel_session($session_id, $reason = '')
    {
        $session_id = (int) $session_id;
        $row = $this->db->where('id', $session_id)->get('tutoring_sessions')->row_array();
        if (!$row) return false;
        if (in_array($row['status'], array('completed', 'declined'), true)) return false;

        $this->db->where('id', $session_id)->update('tutoring_sessions', array('status' => 'declined'));

        if (!empty($row['slot_id'])) {
            $this->db->where('id', (int) $row['slot_id'])->update('availability_slots', array('status' => 'open'));
        }

        $note = trim((string) $reason) !== '' ? ' — ' . trim((string) $reason) : '';
        foreach (array((int) $row['student_id'], (int) $row['teacher_id']) as $uid) {
            if ($uid > 0) $this->push_notification($uid, 'ألغيت الحصة', 'ألغت الإدارة الحصة المتفق عليها' . $note . '.', 'session');
        }

        $this->audit('session_cancel', 'tutoring_sessions#' . $session_id, $row, array('reason' => $reason));
        return true;
    }

    public function slots()
    {
        return $this->safe_rows(
            'SELECT sl.*, TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) teacher_name
               FROM `availability_slots` sl
               LEFT JOIN `users` u ON u.`id` = sl.`teacher_id`
              WHERE sl.`starts_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              ORDER BY sl.`starts_at` ASC LIMIT 300'
        );
    }

    /**
     * من فتح وقتا **ومن لم يفتح**.
     *
     * الصف الفارغ هو المعلومة: شاشة تعرض الفسحات المفتوحة وحدها تخفي
     * أن نصف المعلمين لم يفتحوا شيئا، وهو سبب أن الطالب يرى «لا معلم
     * متاح الآن» في بوابته.
     */
    public function teacher_slot_summary()
    {
        return $this->safe_rows(
            'SELECT u.`id`, TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) name,
                    u.`email`,
                    COALESCE(SUM(CASE WHEN sl.`status` = "open"   AND sl.`starts_at` >= NOW() THEN 1 ELSE 0 END), 0) open_slots,
                    COALESCE(SUM(CASE WHEN sl.`status` = "booked" AND sl.`starts_at` >= NOW() THEN 1 ELSE 0 END), 0) booked_slots
               FROM `users` u
               LEFT JOIN `availability_slots` sl ON sl.`teacher_id` = u.`id`
              WHERE u.`is_instructor` = 1
              GROUP BY u.`id`
              ORDER BY open_slots ASC, name ASC'
        );
    }

    /* =====================================================================
       طلبات السحب
       ===================================================================== */

    public function payouts($status = '')
    {
        $where = '';
        $args  = array();
        if ($status === 'pending') { $where = ' WHERE p.`status` = 0'; }
        elseif ($status === 'paid') { $where = ' WHERE p.`status` = 1'; }
        elseif ($status === 'rejected') { $where = ' WHERE p.`status` = 2'; }

        return $this->safe_rows(
            'SELECT p.*,
                    TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) teacher_name,
                    u.`email` teacher_email,
                    w.`balance_available`, w.`balance_locked`
               FROM `payout` p
               LEFT JOIN `users` u   ON u.`id` = p.`user_id`
               LEFT JOIN `wallets` w ON w.`owner_user_id` = p.`user_id`'
            . $where . ' ORDER BY p.`status` ASC, p.`id` DESC LIMIT 300',
            $args
        );
    }

    public function payout_totals()
    {
        return array(
            'pending_n'   => $this->safe_scalar('SELECT COUNT(*) n FROM `payout` WHERE `status` = 0'),
            'pending_sum' => $this->safe_scalar('SELECT COALESCE(SUM(`amount_halalas`),0) n FROM `payout` WHERE `status` = 0'),
            'paid_sum'    => $this->safe_scalar('SELECT COALESCE(SUM(`amount_halalas`),0) n FROM `payout` WHERE `status` = 1'),
        );
    }

    /** يخطر المعلم بقرار طلبه — قرار بلا إخطار يقرأ صمتا. */
    public function notify_payout($payout_id, $paid, $note = '')
    {
        $p = $this->db->where('id', (int) $payout_id)->get('payout')->row_array();
        if (!$p) return;

        $sar = number_format(((int) $p['amount_halalas']) / 100, 2);
        if ($paid) {
            $this->push_notification((int) $p['user_id'], 'حول مبلغ السحب',
                'حول إليك ' . $sar . ' ر.س. رقم العملية: ' . $note . '.', 'wallet');
        } else {
            $this->push_notification((int) $p['user_id'], 'رفض طلب السحب',
                'رفض طلب سحب ' . $sar . ' ر.س، وأعيد المبلغ إلى رصيدك المتاح.'
                . (trim((string) $note) !== '' ? ' السبب: ' . trim((string) $note) . '.' : ''), 'wallet');
        }
    }

    /* =====================================================================
       الإشعارات
       ===================================================================== */

    /**
     * إشعار واحد إلى مستخدم واحد — الباب الذي تمر منه كل الإخطارات.
     *
     * TQ-NOTIF-TIME — `created_at` في هذا الجدول **طابع يونكس مخزن نصا**
     * (العمود `varchar(255)`)، هكذا كتبته Academy وهكذا تقرؤه الشاشات
     * الثلاث: `tq_notifications` و`tq_teacher_notifications` و
     * `tq_parent_alerts` كلها تكتب `tq_since((int) $n['created_at'])`.
     *
     * وكانت هذه الدالة تكتب `date('Y-m-d H:i:s')`. و`(int) "2026-08-11 …"`
     * تساوي **2026** — أي طابعا في يناير ١٩٧٠. فكل إشعار كتبته الإدارة
     * (اعتماد سحب · رفضه · إشعار جماعي) يظهر لصاحبه «منذ ٥٦ سنة»،
     * ويهبط إلى آخر القائمة تحت كل إشعار قديم، ويسقط من فرز «هذا الأسبوع».
     *
     * أي أن الإخطارات الإدارية كانت تكتب ولا تقرأ.
     */
    public function push_notification($to_user, $title, $description, $type = 'system', $mail = true)
    {
        try {
            $now = time();
            $this->db->insert('notifications', array(
                'from_user'   => $this->tq_actor_id(),
                'to_user'     => (int) $to_user,
                'type'        => $type,
                'title'       => $title,
                'description' => $description,
                'status'      => 0,
                'created_at'  => (string) $now,
                'updated_at'  => (string) $now,
            ));
        } catch (Throwable $e) {
            log_message('error', 'push_notification: ' . $e->getMessage());
            return false;
        }

        /* والبريد بعده — تابعا لا شرطا.
           الإشعارات التي تمر من هنا كلها قرارات إدارية على أموال وحسابات
           (اعتماد سحب · رفضه · تفعيل اشتراك)، وصاحبها ينتظرها ولا يفتح
           المنصة كل يوم ليجدها. و`Taqdar_mail_model` يرد `false` بهدوء
           حين لا يكون البريد مضبوطا — فلا يسقط القرار لأن الرسالة لم تصل. */
        if ($mail) {
            $this->mail_user((int) $to_user, $title, $description);
        }
        return true;
    }

    /** يرسل نص إشعار بالبريد إلى صاحبه إن كان له بريد والبريد مضبوط. */
    private function mail_user($user_id, $title, $body, $cta = null)
    {
        try {
            $u = $this->db->select('email')->where('id', (int) $user_id)
                          ->get('users')->row_array();
            if (!$u || empty($u['email'])) return false;

            $this->load->model('taqdar_mail_model');
            return $this->taqdar_mail_model->send_lines(
                $u['email'], $title, array(strip_tags((string) $body)),
                $cta ?: array('label' => 'افتح المنصة', 'href' => site_url('login'))
            );
        } catch (Throwable $e) {
            log_message('error', 'push_notification mail: ' . $e->getMessage());
            return false;
        }
    }

    /** فئات الإرسال ومن فيها. المعرفات تقرأ مرة ثم تستعمل مرتين. */
    private function audience_ids($audience)
    {
        switch ($audience) {
            case 'students':
                $sql = 'SELECT `id` FROM `users` WHERE `status` = 1 AND `is_instructor` = 0
                          AND COALESCE(`tq_gate`, "student") = "student"';
                break;
            case 'teachers':
                $sql = 'SELECT `id` FROM `users` WHERE `status` = 1 AND `is_instructor` = 1';
                break;
            case 'parents':
                $sql = 'SELECT `id` FROM `users` WHERE `status` = 1 AND `tq_gate` = "parent"';
                break;
            case 'subscribers':
                /* المشتركون فعلا: الرسالة التي تخص الاشتراك لا ترسل لمن
                   لا اشتراك له، وإلا قرئت إعلانا. */
                $sql = 'SELECT DISTINCT u.`id` FROM `users` u
                          JOIN `subscriptions` s ON s.`user_id` = u.`id` AND s.`status` = "active"
                         WHERE u.`status` = 1';
                break;
            case 'all':
            default:
                $sql = 'SELECT `id` FROM `users` WHERE `status` = 1';
        }

        $out = array();
        foreach ($this->safe_rows($sql) as $r) $out[] = (int) $r['id'];
        return $out;
    }

    public function audience_sizes()
    {
        $out = array();
        foreach (array('all', 'students', 'teachers', 'parents', 'subscribers') as $a) {
            $out[$a] = count($this->audience_ids($a));
        }
        return $out;
    }

    /**
     * البث.
     *
     * إدراج واحد مجمع لا إدراج لكل مستخدم: ألفا مستخدم يعني ألفي رحلة
     * إلى القاعدة، وتنتهي المهلة قبل نصفهم — فيرسل الإشعار إلى بعضهم
     * ويظهر للإدارة أنه فشل، فترسله ثانية إلى من وصله أصلا.
     */
    /**
     * إشعار جماعي.
     *
     * @param bool $by_mail نسخة بريدية أيضا. **اختيارية عمدا**: الإشعار
     *        داخل المنصة يكتب دفعة واحدة في القاعدة (ألف صف في استعلامين)،
     *        أما البريد فاتصال SMTP لكل دفعة — وإرساله إلى ألفي مستلم في
     *        مسار الطلب نفسه يعلق الصفحة حتى تنتهي مهلة PHP. فيرسل
     *        بنسخة مخفية على دفعات من خمسين، ويقال للمسؤول ما وصل.
     */
    public function broadcast($audience, $title, $description, $by_mail = false)
    {
        $ids = $this->audience_ids($audience);
        if (!$ids) return 0;

        /* طابع يونكس نصا — انظر TQ-NOTIF-TIME في `push_notification`.
           و`date('Y-m-d H:i:s')` هنا كانت تجعل كل إشعار جماعي يظهر
           لمستقبليه «منذ ٥٦ سنة» في آخر قائمتهم. */
        $now   = (string) time();
        $actor = $this->tq_actor_id();
        $sent  = 0;

        foreach (array_chunk($ids, 500) as $chunk) {
            $batch = array();
            foreach ($chunk as $uid) {
                $batch[] = array(
                    'from_user'   => $actor,
                    'to_user'     => $uid,
                    'type'        => 'admin',
                    'title'       => $title,
                    'description' => $description,
                    'status'      => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                );
            }
            try {
                $this->db->insert_batch('notifications', $batch);
                $sent += count($batch);
            } catch (Throwable $e) {
                // دفعة تعثرت لا تسقط الباقي — والعدد المرجع يقول ما وصل فعلا
            }
        }

        $mailed = $by_mail ? $this->broadcast_mail($ids, $title, $description) : 0;

        $this->audit('broadcast', 'notifications', null,
                     array('audience' => $audience, 'title' => $title,
                           'count' => $sent, 'mailed' => $mailed));

        $this->last_broadcast_mailed = $mailed;
        return $sent;
    }

    /** كم مستلما وصلته النسخة البريدية من آخر إشعار جماعي. */
    public $last_broadcast_mailed = 0;

    /**
     * النسخة البريدية من الإشعار الجماعي — بنسخة مخفية وعلى دفعات.
     *
     * خمسون في الدفعة: أكثر من ذلك ترفضه كثير من الخوادم (Gmail يحد
     * المستلمين لكل رسالة)، وأقل منه يفتح اتصالات أكثر بلا فائدة.
     * وعنوان كل مستلم في `Bcc` لا في `To`: قائمة ظاهرة تسرب بريد كل
     * مستخدم إلى الباقين.
     */
    private function broadcast_mail($ids, $title, $description)
    {
        $this->load->model('taqdar_mail_model');
        if (!$this->taqdar_mail_model->configured()) {
            return 0;
        }

        $rows = $this->db->select('email')->where_in('id', $ids)
                         ->where('email IS NOT NULL', null, false)
                         ->where('email !=', '')
                         ->get('users')->result_array();

        $emails = array();
        foreach ($rows as $r) {
            if (filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                $emails[] = $r['email'];
            }
        }
        if (!$emails) return 0;

        $done = 0;
        foreach (array_chunk($emails, 50) as $chunk) {
            /* `bulk` تضيف `List-Unsubscribe`: رسالة جماعية بلا زر إلغاء
               اشتراك تجمع بلاغات «مزعجة»، والبلاغ يضر النطاق كله لا هذه
               الرسالة. */
            if ($this->taqdar_mail_model->send_lines($chunk, $title, array($description),
                    null, array('bulk' => true))) {
                $done += count($chunk);
            }
        }
        return $done;
    }

    public function recent_notifications($limit = 30)
    {
        return $this->safe_rows(
            'SELECT n.`title`, n.`type`, n.`created_at`, COUNT(*) recipients,
                    SUM(CASE WHEN n.`status` = 1 THEN 1 ELSE 0 END) opened
               FROM `notifications` n
              WHERE n.`type` = "admin"
              GROUP BY n.`title`, n.`type`, n.`created_at`
              ORDER BY n.`created_at` DESC LIMIT ' . (int) $limit
        );
    }

    /* =====================================================================
       خريطة الإتقان
       ===================================================================== */

    /**
     * الأعمدة هنا ليست ما يظن.
     *
     * `attempts` صف لكل **محاولة تقييم** لا لكل سؤال: فيه `student_id`
     * و`passed` و`submitted_at`، وليس فيه `is_correct` ولا `question_id`.
     * الصواب والخطأ لكل سؤال في `answers` (`attempt_id` + `question_id`
     * + `is_correct`). وقوة المهارة `skill_state.level` وهي **0..100**
     * لا كسر عشري، ومفتاح صاحبها `student_id` لا `user_id`.
     *
     * كتبت هذه الاستعلامات أول مرة على أسماء مفترضة، وكلها كانت تخطئ
     * صامتة عبر `safe_rows` — أي ترجع جدولا فارغا يقرأ «لا بيانات».
     */
    public function mastery_summary()
    {
        return array(
            'attempts'    => $this->safe_scalar('SELECT COUNT(*) n FROM `attempts` WHERE `submitted_at` IS NOT NULL'),
            'answers'     => $this->safe_scalar('SELECT COUNT(*) n FROM `answers`'),
            'correct'     => $this->safe_scalar('SELECT COUNT(*) n FROM `answers` WHERE `is_correct` = 1'),
            'learners'    => $this->safe_scalar('SELECT COUNT(DISTINCT `student_id`) n FROM `attempts`'),
            'due_reviews' => $this->safe_scalar('SELECT COUNT(*) n FROM `review_queue` WHERE `due_at` <= NOW()'),
            'skills'      => $this->safe_scalar('SELECT COUNT(*) n FROM `skill_state`'),
            'mastered'    => $this->safe_scalar('SELECT COUNT(*) n FROM `skill_state` WHERE `level` >= 80'),
        );
    }

    /**
     * أصعب الأهداف: الهدف الذي يسقط فيه أكثر من مر به.
     *
     * أنفع رقم في المنصة لمن يحرر المنهج — وكان محجوبا تماما. والحد
     * الأدنى خمس إجابات: هدف جربه اثنان وأخطآ ليس «أصعب هدف»، إنما
     * عينة لا يقال عنها شيء.
     */
    public function hardest_objectives($limit = 20)
    {
        return $this->safe_rows(
            'SELECT o.`id`, o.`text`, o.`lesson_id`,
                    l.`title` lesson_title, c.`title` course_title,
                    COUNT(an.`id`) tries,
                    SUM(CASE WHEN an.`is_correct` = 1 THEN 1 ELSE 0 END) hits,
                    COUNT(DISTINCT at.`student_id`) learners
               FROM `objectives` o
               JOIN `question` q  ON q.`objective_id` = o.`id`
               JOIN `answers` an  ON an.`question_id` = q.`id`
               JOIN `attempts` at ON at.`id` = an.`attempt_id`
               LEFT JOIN `lesson` l ON l.`id` = o.`lesson_id`
               LEFT JOIN `course` c ON c.`id` = l.`course_id`
              GROUP BY o.`id`
             HAVING tries >= 5
              ORDER BY (hits / tries) ASC, tries DESC
              LIMIT ' . (int) $limit
        );
    }

    public function mastery_by_path($limit = 20)
    {
        return $this->safe_rows(
            'SELECT p.`id`, p.`title`,
                    COUNT(DISTINCT ss.`student_id`) learners,
                    ROUND(AVG(ss.`level`)) avg_level
               FROM `paths` p
               JOIN `lesson` l      ON l.`course_id` = p.`course_id`
               JOIN `objectives` o  ON o.`lesson_id` = l.`id`
               JOIN `skill_state` ss ON ss.`objective_id` = o.`id`
              GROUP BY p.`id`
              ORDER BY avg_level ASC
              LIMIT ' . (int) $limit
        );
    }

    /* =====================================================================
       الأشخاص
       ===================================================================== */

    /**
     * الحسابات الأربعة في شاشة واحدة.
     *
     * الدور يشتق كما يشتقه `tq_role()`: `is_instructor` ثم `tq_gate` ثم
     * الطالب افتراضا. واشتقاقه هنا بالقواعد نفسها لا باستدعائها لكل صف
     * — خمسمئة صف تعني خمسمئة استعلام.
     */
    public function people($role = '', $q = '')
    {
        $where = array('1 = 1');
        $args  = array();

        if ($role === 'teacher')      $where[] = 'u.`is_instructor` = 1';
        elseif ($role === 'parent')   $where[] = 'u.`is_instructor` = 0 AND u.`tq_gate` = "parent"';
        elseif ($role === 'student')  $where[] = 'u.`is_instructor` = 0 AND COALESCE(u.`tq_gate`, "student") = "student"';
        elseif ($role === 'admin')    $where[] = 'u.`role_id` = 1';
        elseif ($role === 'disabled') $where[] = 'u.`status` = 0';

        if (trim($q) !== '') {
            $like    = '%' . trim($q) . '%';
            $where[] = '(u.`email` LIKE ? OR u.`first_name` LIKE ? OR u.`last_name` LIKE ? OR u.`phone` LIKE ?)';
            array_push($args, $like, $like, $like, $like);
        }

        return $this->safe_rows(
            'SELECT u.`id`, u.`first_name`, u.`last_name`, u.`email`, u.`phone`,
                    u.`status`, u.`is_instructor`, u.`role_id`, u.`date_added`,
                    COALESCE(u.`tq_gate`, "student") tq_gate
               FROM `users` u
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY u.`id` DESC LIMIT 400',
            $args
        );
    }

    public function role_tally()
    {
        return array(
            'all'      => $this->safe_scalar('SELECT COUNT(*) n FROM `users`'),
            'student'  => $this->safe_scalar('SELECT COUNT(*) n FROM `users` WHERE `is_instructor` = 0 AND COALESCE(`tq_gate`, "student") = "student"'),
            'teacher'  => $this->safe_scalar('SELECT COUNT(*) n FROM `users` WHERE `is_instructor` = 1'),
            'parent'   => $this->safe_scalar('SELECT COUNT(*) n FROM `users` WHERE `is_instructor` = 0 AND `tq_gate` = "parent"'),
            'admin'    => $this->safe_scalar('SELECT COUNT(*) n FROM `users` WHERE `role_id` = 1'),
            'disabled' => $this->safe_scalar('SELECT COUNT(*) n FROM `users` WHERE `status` = 0'),
        );
    }

    public function toggle_user($user_id)
    {
        $user_id = (int) $user_id;
        $u = $this->db->where('id', $user_id)->get('users')->row_array();
        if (!$u) return array('ok' => false, 'message' => 'الحساب غير موجود.');

        $new = ((int) $u['status'] === 1) ? 0 : 1;
        $this->db->where('id', $user_id)->update('users', array('status' => $new));
        $this->audit($new ? 'user_enable' : 'user_disable', 'users#' . $user_id,
                     array('status' => $u['status']), array('status' => $new));

        return array('ok' => true, 'message' => $new
            ? 'فتح الحساب، وصار بإمكان صاحبه الدخول.'
            : 'أغلق الحساب. ولا يحذف شيء من بياناته — الإغلاق يمنع الدخول وحده.');
    }

    /**
     * الفاعل: المستخدم في الطلب الوبي، و0 (النظام) في المهام الدورية.
     * مكتبة الجلسة غير محملة في سطر الأوامر، وقراءتها هناك تسقط العملية.
     *
     * والفحص يقع على كائن CI لا على `$this`: خصائص النموذج في CI وهمية
     * يخدمها `__get` وحده، و`CI_Model` لا يعرف `__isset` — فـ
     * `isset($this->session)` كاذبة أبدا ولو كانت الجلسة قائمة، وكانت
     * تنسب كل فعل إداري إلى «النظام» فيفقد سجل التدقيق فائدته الوحيدة.
     */
    private function tq_actor_id()
    {
        $CI = get_instance();
        if (!$CI || !isset($CI->session) || !is_object($CI->session)) return 0;
        return (int) $CI->session->userdata('user_id');
    }

}
