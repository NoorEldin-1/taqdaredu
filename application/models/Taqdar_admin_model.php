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
