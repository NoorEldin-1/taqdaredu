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
                'table'     => 'paths',
                'title'     => 'المسارات التعليمية',
                'lead'      => 'المسار = مادة + صف، وهو ما يشترك فيه الطالب فعليا.',
                'icon'      => 'route',
                'order_by'  => array('id' => 'DESC'),
                /* عمود «الظهور» — المسار يسقط من الكتالوج أو يعرض فارغا
                   بأربعة أسباب مختلفة، وكان يعبرها كلها بلا إشارة. وأخطرها
                   الوعاء المحذوف: البرنامج يظل معروضا **ويباع** ويفتح على
                   «قيد التجهيز» أبدا (TQ-ORPHAN-PURGE). */
                'status_fn' => 'path_visibility',
                'status_label' => 'الظهور',
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

            /* الاختبار التشخيصي: راسه هنا، واسئلته في شاشتها.
               والسبب ان السؤال ليس حقلا في صف — هو صف في جدول ثان له
               مستواه وخياراته واجابته، فوصفه هنا يعني حقلا نصيا يكتب فيه
               المسؤول JSON بيده. و`row_action` يفتح شاشة الاسئلة من
               الجدول مباشرة، فلا يبحث عن رابطها في القائمة الجانبية. */
            'diag_exams' => array(
                'table'        => 'tq_diag_exams',
                'title'        => 'الاختبارات التشخيصية',
                'lead'         => 'اختبار واحد لكل صف، يقاس به الطالب قبل ان يشترك فيدل على باقته.',
                'icon'         => 'crosshair',
                'ensure'       => 'taqdar_diag_model',
                'order_by'     => array('id' => 'DESC'),
                'note'         => 'الطالب في صف له اختبار منشور لا يشترك حتى يؤديه. والمسودة لا تحبس احدا — فابن اختبارك على مهل، وانشره حين تكتمل اسئلته وباقاته.',
                'status_fn'    => 'diag_status',
                'status_label' => 'الجهوزية',
                'row_action'   => array('label' => 'الاسئلة', 'icon' => 'help',
                                        'href'  => 'taqdar_admin/diag_questions/'),
                'fields'   => array(
                    'grade_id'    => array('label' => 'الصف الدراسي', 'type' => 'ref', 'ref' => 'grades',
                                           'required' => true, 'list' => true, 'unique' => true,
                                           'section' => 'التعريف',
                                           'hint' => 'صف واحد لا يحمل اكثر من اختبار: اختباران لصف واحد يعنيان اختبارين يتنازعان الطالب ولا شيء يقرر ايهما يعرض.'),
                    'title'       => array('label' => 'عنوان الاختبار', 'type' => 'text', 'required' => true, 'list' => true,
                                           'hint' => 'يقرؤه الطالب قبل ان يبدا، مثل: اين موضعك في رياضيات الصف السادس؟'),
                    'intro'       => array('label' => 'نص التمهيد', 'type' => 'textarea',
                                           'hint' => 'يشرح للطالب ما هو الاختبار ولماذا. والتوضيح ان لا رسوب فيه يخفض القلق ويرفع صدق الاجابة.'),
                    'status'      => array('label' => 'الحالة', 'type' => 'enum', 'default' => 'draft', 'list' => true,
                                           'options' => array('draft' => 'مسودة', 'published' => 'منشور'),
                                           'hint' => 'المنشور وحده يعرض ويحبس. ولا تنشر قبل ان تكتمل الاسئلة وباقات المستويات.'),

                    'level_threshold' => array('label' => 'عتبة اتقان المستوى %', 'type' => 'number', 'default' => 60, 'list' => true,
                                           'section' => 'الحساب',
                                           'hint' => 'النسبة اللازمة داخل اسئلة المستوى ليعد متقنا. والحساب تدرجي: يبلغ الطالب اعلى مستوى بلغ عتبته، والا فما دونه، والا فمبتدئ.'),
                    'time_limit_sec'  => array('label' => 'المدة (ثوان)', 'type' => 'number', 'default' => 0,
                                           'hint' => 'صفر = بلا حد زمني. والاختبار تشخيص لا امتحان، فالضغط الزمني يخفض دقة القياس.'),
                    'allow_retake'    => array('label' => 'يسمح بالاعادة', 'type' => 'bool', 'default' => 0,
                                           'hint' => 'مغلق افتراضا: نتيجة تعاد حتى تعجب صاحبها لا تقيس شيئا.'),

                    'plan_beginner'     => array('label' => 'باقة المبتدئ', 'type' => 'ref', 'ref' => 'plans', 'list' => true,
                                           'section' => 'الباقة لكل نتيجة',
                                           'hint' => 'اليها يوجه من كانت نتيجته «مبتدئ». وهي توصية لا الزام — يبقى له ان يختار غيرها.'),
                    'plan_intermediate' => array('label' => 'باقة المتوسط', 'type' => 'ref', 'ref' => 'plans', 'list' => true),
                    'plan_advanced'     => array('label' => 'باقة المتقدم', 'type' => 'ref', 'ref' => 'plans', 'list' => true),
                ),
            ),

            /* النتائج تقرأ ولا تحرر: النتيجة فعل الطالب، وتحريرها من
               اللوحة يجعل الكشف شيئا اخر غير ما جرى — كما في
               `competition_entries` و`audit_log`. */
            'diag_attempts' => array(
                'table'    => 'tq_diag_attempts',
                'title'    => 'نتائج الاختبارات التشخيصية',
                'lead'     => 'من ادى اي اختبار، وبأي نتيجة، والى اي باقة وجه.',
                'icon'     => 'chart',
                'ensure'   => 'taqdar_diag_model',
                'order_by' => array('id' => 'DESC'),
                'readonly' => true,
                'nodelete' => true,
                'note'     => 'توزيع كله في «مبتدئ» يقرأ عن الاختبار لا عن الصف: اسئلة اصعب من صفها، او عتبة اعلى مما ينبغي.',
                'status_fn'    => 'diag_notify_status',
                'status_label' => 'ابلاغ ولي الامر',
                'fields'   => array(
                    'student_id'   => array('label' => 'الطالب', 'type' => 'ref', 'ref' => 'users', 'list' => true),
                    'grade_id'     => array('label' => 'الصف', 'type' => 'ref', 'ref' => 'grades', 'list' => true),
                    'result_level' => array('label' => 'النتيجة', 'type' => 'enum', 'list' => true,
                                            'options' => array('beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم')),
                    'score'        => array('label' => 'الصحيح', 'type' => 'number', 'list' => true),
                    'total'        => array('label' => 'من', 'type' => 'number', 'list' => true),
                    'plan_id'      => array('label' => 'الباقة الموصى بها', 'type' => 'ref', 'ref' => 'plans', 'list' => true),
                    'submitted_at' => array('label' => 'تاريخ الاداء', 'type' => 'datetime', 'list' => true),
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

            /* الباقة وحدة البيع، ومحتواها **مستنتج لا مسرود**: الباقة تختار
               صفوفا، والصف يجمع مسارات المنهج المنشورة، والمسار يحمل دورته
               ودروسها. فلا يربط المسؤول درسا بباقة ولا ينبغي أن يربط — ولو
               ربط لصار كل درس جديد يحتاج مرورا على كل باقة، ولنسي.
               ولوحة «ما تفتحه هذه الباقة» تحت النموذج تعرض حاصل هذا
               الاستنتاج لحظة بلحظة، فالمسؤول يرى ما يبيع قبل أن يحفظ. */
            'plans' => array(
                'table'        => 'plans',
                'title'        => 'الباقات',
                'lead'         => 'الباقة تحدد السعر والمدة والصفوف التي يفتحها الاشتراك.',
                'icon'         => 'package',
                'order_by'     => array('order' => 'ASC'),
                'note'         => 'تعديل باقة لا يمس اشتراكا قائما: السعر والنطاق ينسخان وقت الشراء، فمن اشترك أمس يبقى على ما دفع.',
                /* `teacher_pool_percent` عمود يضاف وقت التشغيل، وهذه
                   الوحدة تعرضه في القائمة وتحفظه من النموذج — فيركب قبل
                   أن يقرأ، لا عند أول بيعة. */
                'ensure'       => 'taqdar_revenue_model',
                'form_extra'   => array('tqa_plan_reach', 'tqa_plan_split'),
                'form_js'      => 'tqa_plan_js',
                'status_fn'    => 'plan_visibility',
                'status_label' => 'الظهور',
                'fields'   => array(
                    'name_ar'       => array('label' => 'الاسم بالعربية', 'type' => 'text', 'required' => true, 'list' => true,
                                             'section' => 'التعريف',
                                             'hint' => 'الاسم كما يقرؤه المشتري. وما بعد الشرطة «—» يقرأ مرحلة في بطاقة الباقة، مثل: الباقة المميزة — المرحلة المتوسطة.'),
                    'name_en'       => array('label' => 'الاسم بالإنجليزية', 'type' => 'text', 'ltr' => true),
                    'code'          => array('label' => 'الرمز', 'type' => 'text', 'required' => true, 'list' => true, 'ltr' => true,
                                             'unique' => true,
                                             'hint' => 'رمز لاتيني فريد لا يتغير — منه يبنى رابط الباقة `‎/plan/<الرمز>‎` ورابط الشراء. وتغييره يكسر كل رابط قديم.'),
                    'note'          => array('label' => 'سطر الوصف', 'type' => 'text',
                                             'hint' => 'سطر قصير تحت اسم الباقة في صفحة الباقات.'),

                    'price'         => array('label' => 'السعر', 'type' => 'money', 'default' => 0, 'list' => true,
                                             'section' => 'التسعير والمدة',
                                             'hint' => 'يدخل بالريال ويخزن بالهللات. المجانية تحدد بالدورة «مجانية» لا بخلو السعر؛ وباقة مدفوعة بلا سعر ترفض عند الشراء.'),
                    'period'        => array('label' => 'الدورة', 'type' => 'enum', 'default' => 'annual', 'list' => true,
                                             'options' => array('free' => 'مجانية', 'monthly' => 'شهرية', 'quarterly' => 'ربع سنوية', 'annual' => 'سنوية'),
                                             'hint' => 'اسم تجاري للدورة. والمدة تحتها هي ما يحسب عليه تاريخ الانتهاء فعلا.'),
                    'duration_days' => array('label' => 'المدة (أيام)', 'type' => 'number', 'default' => 365, 'list' => true,
                                             'hint' => 'من 360 يوما فصاعدا تكتب البطاقة «للعام الدراسي كاملا».'),

                    /* رقم واحد يخزن ونسبتان تعرضان. ورقمان في عمودين
                       يفترقان عند أول تعديل — فتحفظ باقة عمولتها ٨٠
                       ووعاؤها ٣٠ ولا شيء يمنعها. */
                    'teacher_pool_percent' => array(
                        'label'  => 'وعاء المعلمين %', 'type' => 'percent', 'list' => true,
                        'mirror' => 'عمولة المنصة', 'placeholder' => 'الافتراض العام',
                        'hint'   => 'ما يوزع على معلمي هذه الباقة من سعرها — يقسم بينهم بعدد دروسهم '
                                  . 'لا بعدد كورساتهم. والباقي عمولة المنصة. اتركه فارغا ليأخذ '
                                  . 'النسبة الافتراضية العامة. ولوحة «قسمة الإيراد» أسفل الصفحة '
                                  . 'تريك نصيب كل معلم بالريال قبل أن تحفظ.'),

                    'stage'         => array('label' => 'المرحلة', 'type' => 'pick', 'ref' => 'stages', 'list' => true,
                                             'section' => 'ما تفتحه الباقة',
                                             'hint' => 'صفحة الباقات تبوب بالمرحلة، وباقة بلا مرحلة تسقط من التبويب. والمرحلة قسم من «أقسام الكورسات» — فبها توضع الباقة في مرشح الكتالوج أيضا.'),
                    'scope'         => array('label' => 'النطاق', 'type' => 'enum', 'default' => 'grade', 'list' => true,
                                             'options' => array('grade' => 'صفوف دراسية — وهي باقة المنصة',
                                                                'all' => 'كل المحتوى المنشور',
                                                                'subject' => 'مادة واحدة',
                                                                'path' => 'مسار واحد',
                                                                'trial' => 'دروس تجريبية فقط'),
                                             'hint' => 'صفحة الباقات العامة تعرض «صفوف دراسية» وحدها — وهي وحدة البيع. وبقية النطاقات تشترى برابطها المباشر أو تمنح من اللوحة، ولا تظهر في الصفحة العامة.'),
                    'scope_ids'     => array('label' => 'الصفوف التي تفتحها', 'type' => 'multiref', 'ref' => 'grades', 'list' => true,
                                             'show_when' => array('scope' => array('grade')),
                                             'hint' => 'الاشتراك يفتح كل مسار منشور في هذه الصفوف، ومعه دورته ودروسها واختباراتها. ولا يربط الدرس بيد.'),
                    'scope_id'      => array('label' => 'ما تفتحه', 'type' => 'refswitch', 'default' => 0,
                                             'on' => 'scope',
                                             'refs' => array('subject' => 'subjects', 'path' => 'paths'),
                                             'show_when' => array('scope' => array('subject', 'path'))),

                    /* TQ-PLAN-IMG — رفع لا انتقاء من مجلد السمة.
                       كان `pick` من `assets/taqdar/site/img`، وهو مجلد
                       يملؤه مبرمج بـFTP — فمسؤول يريد صورة لباقته
                       الجديدة لا يملك سبيلا إليها إلا أن يطلب نشرا.
                       والمرفوع يقص ويقاس عند الرفع (`tq_img_store`)
                       فيخرج ١٢٠٠×٨٠٠ بنسبة صندوق البطاقة نفسها. */
                    'image'         => array('label' => 'صورة البطاقة', 'type' => 'file',
                                             'section' => 'العرض والترتيب',
                                             'bucket' => 'plans', 'img_w' => 1200, 'img_h' => 800,
                                             'accept' => '.jpg,.jpeg,.png,.webp',
                                             'hint' => 'ترفع من جهازك وتقص تلقائيا إلى 1200×800 — وهي نسبة البطاقة نفسها، '
                                                     . 'فما تراه هنا هو ما يراه الزائر. وبلا صورة تعرض البطاقة غلاف مرحلتها.'),
                    'features'      => array('label' => 'المزايا', 'type' => 'lines',
                                             'hint' => 'ميزة في كل سطر — تعرض في بطاقة الباقة كما تكتب هنا. وهي وعد يقرؤه المشتري، فلا تكتب فيها ما ليس في الصفوف أعلاه.'),
                    'featured'      => array('label' => 'الأكثر ملاءمة', 'type' => 'bool', 'default' => 0, 'list' => true,
                                             'hint' => 'باقة واحدة تبرز في كل مرحلة — وتعليمها هنا يرفع الإبراز عن أختها في المرحلة نفسها تلقائيا.'),
                    'active'        => array('label' => 'متاحة', 'type' => 'bool', 'default' => 1, 'list' => true),
                    'order'         => array('label' => 'الترتيب', 'type' => 'number', 'default' => 0,
                                             'hint' => 'الأصغر أولا داخل المرحلة — رتبها بالسعر صاعدا.'),
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

            /* آراء أولياء الأمور في صفحة `/parents`.
               كانت ثلاث بطاقات مكتوبة في القالب باسم ووصف ونص — فتغيير
               رأي واحد يحتاج تحرير ملف ونشرا على الخادم، وهي بحسب
               تعريفها محتوى ينشر: موضعه اللوحة.
               وبلا صف واحد هنا تعرض الصفحة آراءها الأصلية كما كانت،
               بالمبدأ نفسه الذي يرد به `tq_text()` نص القالب. */
            'testimonials' => array(
                'table'    => 'tq_testimonials',
                'title'    => 'آراء أولياء الأمور',
                'lead'     => 'ما يقوله أولياء الأمور في صفحة «أولياء الأمور». بلا صف منشور تعرض الصفحة الآراء الأصلية.',
                'icon'     => 'chat',
                'ensure'   => 'taqdar_content_model',
                'order_by' => array('tq_order' => 'ASC', 'id' => 'ASC'),
                'fields'   => array(
                    'name'     => array('label' => 'الاسم', 'type' => 'text', 'required' => true, 'list' => true),
                    'role'     => array('label' => 'الصفة', 'type' => 'text', 'list' => true,
                                        'hint' => 'مثل: أم لطالبة في المرحلة الثانوية.'),
                    'body'     => array('label' => 'نص الرأي', 'type' => 'textarea', 'required' => true),
                    'rating'   => array('label' => 'عدد النجوم', 'type' => 'number', 'default' => 5, 'list' => true,
                                        'hint' => 'من صفر إلى خمسة.'),
                    'status'   => array('label' => 'الحالة', 'type' => 'enum', 'default' => 'published', 'list' => true,
                                        'options' => array('draft' => 'مسودة', 'published' => 'منشور')),
                    'tq_order' => array('label' => 'الترتيب', 'type' => 'number', 'default' => 0, 'list' => true),
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

        /* TQ-I18N — الوصف يترجم عند الخروج، مرة واحدة للوحدة كلها.
           والوصف فيه عناوين وتسميات وتلميحات وأقسام واختيارات مبعثرة على
           سبعة مستويات، ولف كل واحدة بـ`t()` هنا يعني خمسمئة نداء مكتوبة
           بيد — تنسى منها واحدة فيظهر حقل عربي وسط نموذج إنجليزي.

           و`tq_t_deep()` مأمونة على ما ليس نصا: القاموس مفاتيحه عربية،
           فـ`'text'` و`'bool'` و`'name_ar'` لا تجد مدخلا وترد كما هي.
           والمفاتيح لا تمس أصلا — وهي التي تصل إلى أعمدة القاعدة. */
        if ($key === null) return tq_t_deep($modules);
        return isset($modules[$key]) ? tq_t_deep($modules[$key]) : null;
    }

    /* =====================================================================
       القراءة
       ===================================================================== */

    /**
     * جداول تنشأ وقت التشغيل لا بهجرة.
     *
     * بعض جداول تقدر ينشئها نموذجها عند اول استعمال (`site_content` ·
     * `payment_attempts` · `tq_diag_*`) — لان المستودع بلا هجرات. والوحدة
     * الموصوفة تستعلم عن جدولها مباشرة، فجدول لم ينشأ بعد يرمي استثناء
     * يبيض الشاشة عند اول فتح للوحة بعد النشر.
     *
     * فتعلن الوحدة نموذجها في `ensure`، وينادى هنا قبل اي استعلام. وهو
     * متكرر الامان ويفحص مرة واحدة لكل طلب داخل النموذج نفسه.
     */
    private function ensure_table($spec)
    {
        if (empty($spec['ensure'])) return;
        try {
            $m = (string) $spec['ensure'];
            $this->load->model($m);
            $this->{$m}->ensure_schema();
        } catch (Throwable $e) {
            // الفشل هنا يعطل شاشة واحدة لا اللوحة كلها
            log_message('error', 'TQ-ADMIN: تعذر تجهيز جدول الوحدة — ' . $e->getMessage());
        }
    }

    public function listing($key, $limit = 200, $offset = 0)
    {
        $spec = $this->spec($key);
        if (!$spec) return array();
        $this->ensure_table($spec);

        foreach ($spec['order_by'] as $col => $dir) {
            $this->db->order_by($col, $dir);
        }
        return $this->db->limit($limit, $offset)->get($spec['table'])->result_array();
    }

    /**
     * التصفح: بحث وفرز وترقيم فوق `listing()` نفسها.
     *
     * كانت الشاشة تعرض مئتي صف ثم تعتذر بسطر: «المزيد موجود ولا يظهر
     * هنا». وهو محتمل على «المواد الدراسية» وفيها عشرة صفوف، وعطل تام
     * على «سجل التدقيق» و«الفواتير» و«قيود المحافظ» — تكبر بلا حد، فيصير
     * كل ما قبل المئتين الأخيرة غير قابل للوصول من اللوحة أصلا. ولا
     * مخرج: لا بحث ولا صفحة ثانية ولا فرز يقلب الترتيب.
     *
     * والقرار هنا لا في القالب: ترشيح في المتصفح يرشح **المعروض وحده**،
     * فبحث عن صف قديم لا يرد شيئا ويبدو أنه غير موجود.
     *
     * والعمودان — المبحوث فيه والمفروز به — **يشتقان من الوصف** لا من
     * الطلب: اسم عمود يصل من `$_GET` يدخل في `ORDER BY` كما هو.
     */
    public function browse($key, $args = array())
    {
        $spec = $this->spec($key);
        if (!$spec) return array('rows' => array(), 'total' => 0, 'page' => 1,
                                 'pages' => 1, 'per' => 50, 'q' => '', 'sort' => '', 'dir' => 'DESC');
        $this->ensure_table($spec);

        $per  = 50;
        $page = max(1, (int) (isset($args['page']) ? $args['page'] : 1));
        $q    = trim((string) (isset($args['q']) ? $args['q'] : ''));

        /* البحث على الأعمدة النصية المعروضة وحدها: عمود لا يعرض يرد صفا
           لا يفسر ظهوره، و`ref` يقارن رقما بنص فلا يطابق أبدا. */
        if ($q !== '') {
            $text = array();
            foreach ($spec['fields'] as $name => $f) {
                if (empty($f['list'])) continue;
                if (in_array($f['type'], array('text', 'textarea', 'lines'), true)) $text[] = $name;
            }
            if ($text) {
                $this->db->group_start();
                foreach ($text as $i => $col) {
                    $i ? $this->db->or_like($col, $q) : $this->db->like($col, $q);
                }
                if (ctype_digit($q)) $this->db->or_where('id', (int) $q);
                $this->db->group_end();
            } elseif (ctype_digit($q)) {
                $this->db->where('id', (int) $q);
            } else {
                $this->db->where('1 = 0', null, false);
            }
        }

        /* العد يقرأ الشروط نفسها، والمعامل `FALSE` يبقيها للاستعلام
           التالي — وبدونه يعد الجدول كله فيطبع ترقيما لصفحات فارغة.
           والجدول يعلن **مرة واحدة** بـ`from()` ثم يعد ويجلب بلا اسم:
           `count_all_results($t, false)` تبقي `qb_from` كما تبقي الشروط،
           فتمرير الاسم إلى `get()` بعدها يضمه ثانية فيرد
           «Not unique table/alias» على كل وحدة موصوفة. */
        $this->db->from($spec['table']);
        $total = (int) $this->db->count_all_results('', false);
        $pages = max(1, (int) ceil($total / $per));
        if ($page > $pages) $page = $pages;

        $sort = (string) (isset($args['sort']) ? $args['sort'] : '');
        $dir  = strtoupper((string) (isset($args['dir']) ? $args['dir'] : '')) === 'ASC' ? 'ASC' : 'DESC';
        $sortable = $this->sortable_cols($key);
        if ($sort !== '' && isset($sortable[$sort])) {
            $this->db->order_by($sort, $dir);
        } else {
            $sort = '';
            foreach ($spec['order_by'] as $col => $d) $this->db->order_by($col, $d);
        }

        $rows = $this->db->limit($per, ($page - 1) * $per)->get()->result_array();

        return array('rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages,
                     'per' => $per, 'q' => $q, 'sort' => $sort, 'dir' => $dir);
    }

    /** الأعمدة التي يجوز الفرز بها — قائمة بيضاء من الوصف لا من الطلب. */
    public function sortable_cols($key)
    {
        $spec = $this->spec($key);
        if (!$spec) return array();
        $out = array('id' => '#');
        foreach ($spec['fields'] as $name => $f) {
            if (empty($f['list'])) continue;
            if (in_array($f['type'], array('file', 'multiref'), true)) continue;
            $out[$name] = $f['label'];
        }
        return $out;
    }

    public function count_rows($key)
    {
        $spec = $this->spec($key);
        if (!$spec) return 0;
        $this->ensure_table($spec);
        return (int) $this->db->count_all_results($spec['table']);
    }

    public function row($key, $id)
    {
        $spec = $this->spec($key);
        if (!$spec) return null;
        $this->ensure_table($spec);
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

            /* الباقات — لربط نتيجة الاختبار التشخيصي بما يوجه اليه.
               والمعطلة تعرض بعلامتها لا تحذف: باقة ربطت ثم عطلت تختفي من
               القائمة فيبدو الحقل فارغا وهو ليس فارغا، فيحفظ المسؤول
               تعديلا في حقل اخر ويمسح الربط بلا ان يقصد. */
            case 'plans':
                foreach ($this->db->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                                  ->get('plans')->result_array() as $r) {
                    $out[$r['id']] = $r['name_ar']
                        . ((int) $r['active'] === 1 ? '' : ' (' . t('معطلة') . ')');
                }
                break;

            /* مفاتيح لا أرقام — لنوع `pick`.
               ================================================== */

            /* المرحلة مسمى قسم لا رقمه: `plans.stage` نص، ويبحث به
               `Taqdar_catalog_model::cat_by_slug()` وتترجمه
               `tqs_stage_label()`. فقسم بلا مسمى لاتيني لا يصلح مرحلة —
               ولا يعرض هنا كي لا تحفظ باقة على مفتاح فارغ. */
            case 'stages':
                foreach ($this->db->where('parent', 0)->order_by('id', 'ASC')
                                  ->get('category')->result_array() as $r) {
                    $slug = trim((string) $r['slug']);
                    if ($slug === '') continue;
                    $out[$slug] = $r['name'];
                }
                break;

            /* صور السمة: القيمة اسم الملف بلا امتداد — هكذا تقرؤها
               `tqs_asset_img()`. والقائمة من المجلد لا من جدول: من رفع
               صورة إلى السمة يجدها هنا بلا تسجيل ثان. */
            case 'site_images':
                $dir = FCPATH . 'assets/taqdar/site/img/';
                if (!is_dir($dir)) break;
                $files = glob($dir . '*.webp');
                if (!$files) break;
                sort($files);
                foreach ($files as $f) {
                    $name = basename($f, '.webp');
                    $out[$name] = $name;
                }
                break;
        }

        /* TQ-I18N — الخيارات أكثرها **بيانات** يكتبها المسؤول (عناوين
           الكورسات والمسارات وأسماء المعلمين)، وهي لا تترجم بقرار: من
           سمى كورسه سماه. والقاموس مفاتيحه عربية بعينها، فما لم يكتبه
           مبرمج لا يجد مدخلا ويمر كما هو — والذي يجده هو المكتوب في هذا
           الملف وحده، مثل «(معطلة)» بجوار اسم باقة موقوفة. */
        $cache[$ref] = tq_t_deep($out);
        return $cache[$ref];
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
    public function save($key, $id, $post, $files = null)
    {
        $spec = $this->spec($key);
        if (!$spec || !empty($spec['readonly'])) {
            return array('ok' => false, 'errors' => array('هذه الوحدة للقراءة فقط.'));
        }
        $this->ensure_table($spec);

        $data   = array();
        $errors = array();
        /* الملفات تقرأ من `$_FILES` مباشرة حين لا تمرر: `$this->input->post()`
           لا تحملها، والمتحكم لم يكن يمررها — فحقل ملف بلا هذا لا يصل
           إليه شيء أبدا. */
        $files  = ($files === null) ? $_FILES : $files;
        /* الصف كما هو الآن — يحتاجه حقل الملف ليعرف ما يبقيه وما يحذف. */
        $row_now = ((int) $id > 0) ? $this->row($key, (int) $id) : null;
        $drop    = array();   /* ملفات تحذف بعد نجاح الحفظ لا قبله */

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

                /* نسبة مئوية. والفارغ يخزن `NULL` لا صفرا: الصفر نسبة
                   صريحة تعني «لا شيء»، والفارغ يعني «خذ الافتراض العام»
                   — ومعنيان في عمود واحد يحتاجان قيمتين لا قيمة تفسر
                   مرتين. ولذلك لا يقبل هذا النوع `required`. */
                case 'percent':
                    $s = trim((string) $raw);
                    $data[$name] = ($s === '' || !is_numeric($s))
                                 ? null
                                 : max(0, min(100, round((float) $s, 2)));
                    break;

                case 'ref':
                case 'refswitch':
                    $data[$name] = (int) $raw;
                    break;

                /* قائمة معرفات مفصولة بفواصل في عمود نصي. المرور بـ`intval`
                   شرط لا زينة: القيمة تحقن في `WHERE IN` وفي `explode`
                   بلا تهريب لاحق، وعنصر غير رقمي يخرج من كليهما بلا خطأ
                   يرى. والفرادة والترتيب يثبتان الصف: قائمتان بالمعرفات
                   نفسها بترتيبين تقرآن مختلفتين في سجل التدقيق. */
                case 'multiref':
                    $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
                    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
                    sort($ids);
                    $data[$name] = implode(',', $ids);
                    break;

                /* مفتاح نصي من قائمة مبنية وقت التشغيل. يفحص مقابلها —
                   وإلا كتب في العمود ما لا يقابله صف، فيقرأ اسما لاتينيا
                   في صفحة عربية أو يكسر رابط صورة. */
                case 'pick':
                    $key_val = trim((string) $raw);
                    $opts    = $this->options($f['ref']);
                    $data[$name] = ($key_val !== '' && isset($opts[$key_val])) ? $key_val : '';
                    break;

                /* ملف صورة — TQ-PLAN-IMG.
                   ثلاث حالات لا واحدة، وخلطها يمحو صور الناس:
                     · رفع جديد        ⇐ يخزن ويحل محل القديم ويحذفه
                     · «احذف الصورة»   ⇐ يفرغ العمود ويحذف الملف
                     · لا هذا ولا ذاك ⇐ **لا يمس العمود**
                   والثالثة هي المهمة: `$_FILES` تصل فارغة في كل حفظ لا
                   يختار فيه المسؤول ملفا — وهو أكثر الحفظ. فكتابة ''
                   حينها تمحو الصورة عند أول تعديل في السعر. */
                case 'file':
                    $keep = ($row_now !== null && array_key_exists($name, $row_now))
                          ? (string) $row_now[$name] : '';
                    $sent = (isset($files[$name]) && is_array($files[$name])
                             && (int) $files[$name]['error'] !== UPLOAD_ERR_NO_FILE
                             && (string) $files[$name]['name'] !== '');

                    if ($sent) {
                        $up = tq_img_store($files[$name], array(
                            'bucket' => isset($f['bucket']) ? $f['bucket'] : $key,
                            'w'      => isset($f['img_w']) ? (int) $f['img_w'] : 1200,
                            'h'      => isset($f['img_h']) ? (int) $f['img_h'] : 800,
                            'prefix' => $key . ($id ? '-' . (int) $id : ''),
                        ));
                        if (!$up['ok']) { $errors[] = $up['error']; break; }
                        $data[$name] = $up['path'];
                        /* الاسم بصمة المحتوى، فرفع الصورة نفسها ثانية
                           يعطي المسار نفسه — وحذف «القديم» حينها يحذف
                           الجديد. فالشرط لا زينة. */
                        if ($keep !== '' && $keep !== $up['path']) $drop[] = $keep;
                    } elseif (!empty($post[$name . '__clear'])) {
                        $data[$name] = '';
                        if ($keep !== '') $drop[] = $keep;
                    }
                    /* وإلا لا يكتب المفتاح أصلا فيبقى العمود كما هو. */
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
                /* حقل لم يكتب مفتاحه لم يرسل أصلا (حقل ملف بلا اختيار)،
                   فقياس فراغه على `$data` يرفض حفظا لم يخطئ فيه أحد. */
                if (!array_key_exists($name, $data)) {
                    $cur = ($row_now && array_key_exists($name, $row_now)) ? (string) $row_now[$name] : '';
                    if ($cur === '') $errors[] = 'الحقل «' . $f['label'] . '» مطلوب.';
                } else {
                    $empty = ($f['type'] === 'ref') ? ($data[$name] <= 0) : ($data[$name] === '' || $data[$name] === null);
                    if ($empty) $errors[] = 'الحقل «' . $f['label'] . '» مطلوب.';
                }
            }

            /* الفرادة تفحص هنا لا تترك للقاعدة: `UNIQUE` يرمي استثناء
               يبيض الشاشة ويضيع ما كتب في النموذج، والرسالة التي تظهر
               عنه إنجليزية عن فهرس لا يعرفه المسؤول. */
            if (!empty($f['unique']) && array_key_exists($name, $data) && (string) $data[$name] !== '') {
                $this->db->where($name, $data[$name]);
                if ((int) $id > 0) $this->db->where('id !=', (int) $id);
                if ($this->db->count_all_results($spec['table']) > 0) {
                    $errors[] = 'قيمة «' . $f['label'] . '» مستعملة في صف آخر — واختيرت فريدة.';
                }
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

        /* النجوم خمس لا أكثر. الرقم يطبع نجمة نجمة في القالب، فسبع
           تكسر سطر البطاقة وسالب يطبع صفرا بلا أن يقول أحد لماذا. */
        if ($key === 'testimonials' && isset($data['rating'])) {
            $r = (int) $data['rating'];
            if ($r < 0 || $r > 5) $errors[] = 'عدد النجوم من صفر إلى خمسة.';
        }

        // موافقة ولي الأمر: لا تفعل بلا تاريخ موثق
        if ($key === 'parent_links' && $data['status'] === 'active' && empty($data['consent_at'])) {
            $errors[] = 'لا يمكن تفعيل الرابط بلا تاريخ موافقة موثق.';
        }
        if ($key === 'parent_links' && $data['parent_user_id'] === $data['student_id'] && $data['parent_user_id'] > 0) {
            $errors[] = 'لا يكون المستخدم ولي أمر نفسه.';
        }

        /* الباقة: النطاق يقرر أي حقل يعني، وحقل من نطاق آخر يبقى في الصف
           فيكذب على قارئه. فيصفر ما لا يعنيه النطاق هنا، لا في العرض —
           `subscribe()` و`sync_enrolments()` يقرآن العمود لا الشاشة. */
        if ($key === 'plans') {
            $scope = isset($data['scope']) ? $data['scope'] : 'grade';
            /* `image` قد لا يكتب في هذا الحفظ — انظر `case 'file'`. */

            if ($scope !== 'grade')                      $data['scope_ids'] = '';
            if (!in_array($scope, array('subject', 'path'), true)) $data['scope_id'] = 0;

            if ($scope === 'grade' && $data['scope_ids'] === '') {
                $errors[] = 'باقة الصفوف بلا صف واحد لا تفتح شيئا — اختر صفوفها.';
            }
            if ($scope === 'grade' && (string) $data['stage'] === '') {
                $errors[] = 'باقة الصفوف بلا مرحلة تسقط من تبويب صفحة الباقات — اختر مرحلتها.';
            }
            if (in_array($scope, array('subject', 'path'), true) && (int) $data['scope_id'] <= 0) {
                $errors[] = 'نطاق «مادة واحدة» أو «مسار واحد» يلزمه تحديد ما تفتحه الباقة.';
            }
            if ($data['period'] !== 'free' && (int) $data['price'] <= 0) {
                $errors[] = 'باقة مدفوعة بلا سعر ترفض عند الشراء — ضع سعرها، أو اجعل دورتها «مجانية».';
            }
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

        /* الإبراز واحد في كل مرحلة. والبطاقة المبرزة هي التي تحمل شارة
           «الأكثر طلبا» وزرا ممتلئا بينما أختاها بزر شبحي — فإبراز
           الثلاث يلغي الفرق ولا يعلن عنه شيء. ورفع الإبراز عن الأخريات
           هنا لا في الشاشة: من يعلم باقة اليوم لا يذكر أيهن كانت معلمة
           من شهر. */
        if ($key === 'plans' && !empty($data['featured'])) {
            $this->db->where('id !=', $new_id)
                     ->where('stage', (string) $data['stage'])
                     ->where('featured', 1)
                     ->update('plans', array('featured' => 0));
        }

        /* برنامج صار منشورا يصل إلى مشتركي نطاقه الآن — TQ-ENROL-STALE.
           صفوف `enrol` تكتب مرة واحدة عند التفعيل، فمسار ينشر بعد
           البيع كان لا يبلغ مشتركا قائما أبدا. وفشله لا يبطل الحفظ:
           `taqdar_cron enrolments` تلحق ما فات. */
        $after = $this->row($key, $new_id);

        if ($key === 'paths' && is_array($after)
            && (string) (isset($after['status']) ? $after['status'] : '') === 'published') {
            try {
                $this->load->model('taqdar_billing_model', 'tq_bill');
                if (method_exists($this->tq_bill, 'resync_scope')) {
                    $this->tq_bill->resync_scope(
                        (int) (isset($after['grade_id'])   ? $after['grade_id']   : 0),
                        (int) (isset($after['subject_id']) ? $after['subject_id'] : 0),
                        (int) (isset($after['course_id'])  ? $after['course_id']  : 0)
                    );
                }
            } catch (Throwable $e) {
                log_message('error', 'TQ-ENROL paths save: ' . $e->getMessage());
            }
        }

        /* الملف القديم يحذف **بعد** أن يكتب الصف الجديد: حذفه قبله
           يترك بطاقة بلا صورة لو فشلت الكتابة. والحذف مقصور على ما
           تحت `uploads/` — انظر `tq_img_drop()`.
           وصف آخر قد يشير إلى الملف نفسه (نسخ باقة بصورتها)، فيسأل
           الجدول قبل أن يمحى ملف تحته صف حي. */
        foreach (array_unique($drop) as $old_file) {
            if ((string) $old_file === '') continue;
            $still = 0;
            foreach ($spec['fields'] as $fn => $ff) {
                if ($ff['type'] !== 'file') continue;
                $still += (int) $this->db->where($fn, $old_file)->count_all_results($spec['table']);
            }
            if ($still === 0) tq_img_drop($old_file);
        }

        $this->audit($id ? 'update' : 'create', $spec['table'] . '#' . $new_id, $before, $after);
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

    /** سبب آخر رفض حذف — يقرؤه المتحكم ليقول للمسؤول ما المانع. */
    public $delete_error = '';

    public function remove($key, $id)
    {
        $this->delete_error = '';

        $spec = $this->spec($key);
        if (!$spec || !empty($spec['nodelete']) || !empty($spec['readonly'])) {
            $this->delete_error = 'هذه الوحدة لا تسمح بالحذف.';
            return false;
        }

        $before = $this->row($key, $id);
        if (!$before) {
            $this->delete_error = 'الصف غير موجود — قد يكون حذف من قبل.';
            return false;
        }

        /* TQ-PLAN-DELETE — **باقة بيعت لا تحذف، توقف.**
           `subscriptions.plan_id` و`invoices` و`revenue_shares` تشير
           إليها بمعرفها، وحذف الصف يترك كل تلك السجلات تشير إلى لا
           شيء: شاشة الاشتراكات تعرض اسم باقة فارغا، وكشف حساب المعلم
           يقول «باقة #31» ولا يعرف أحد أيها كانت، وسجل مالي لا يقرأ.
           والوصول لا ينقطع (`subscription_items` نسخت النطاق وقت
           التفعيل) — فالضرر كله في القراءة، وهو ضرر لا يرجع.
           و«متاحة = لا» تخفيها من كل صفحة عامة وتمنع شراءها، وهو ما
           يريده من ضغط «حذف». */
        if ($key === 'plans') {
            $sold = (int) $this->db->where('plan_id', (int) $id)->count_all_results('subscriptions');
            if ($sold > 0) {
                $this->delete_error = 'لا تحذف باقة اشترك بها أحد (' . $sold . ' اشتراكا): '
                                    . 'فواتيرها وقسمة إيرادها تشير إليها بمعرفها، وحذفها يترك سجلا ماليا لا يقرأ. '
                                    . 'اجعل «متاحة» = لا — فتختفي من صفحة الباقات ومن الكتالوج ولا تشترى، '
                                    . 'ويبقى من دفع على ما دفع.';
                return false;
            }
        }

        /* حذف الاختبار التشخيصي يجر اسئلته: سؤال بلا اختباره لا يعرض ولا
           يصحح ولا يحرر — صفوف ميتة تكبر في الجدول ولا يراها احد.
           والمحاولات تبقى: هي سجل ما فعله الطالب، لا جزء من الاختبار.
           وحذفها يمحو من كشف النتائج طلابا ادوا فعلا. */
        if ($key === 'diag_exams') {
            $this->db->where('exam_id', (int) $id)->delete('tq_diag_questions');
        }

        $this->db->where('id', (int) $id)->delete($spec['table']);

        /* صور هذا الصف تذهب معه: ملف لا صف يشير إليه يبقى في
           `uploads/` إلى الأبد ولا يعرف أحد لمن كان. والفحص قبله لأن
           صفا آخر قد يشير إلى الملف نفسه. */
        foreach ($spec['fields'] as $fn => $ff) {
            if ($ff['type'] !== 'file') continue;
            $old_file = isset($before[$fn]) ? (string) $before[$fn] : '';
            if ($old_file === '') continue;
            if ((int) $this->db->where($fn, $old_file)->count_all_results($spec['table']) === 0) {
                tq_img_drop($old_file);
            }
        }

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
       الباقات: ما تفتحه، وهل تظهر

       سؤال المسؤول عن الباقة سؤالان لا واحد: **ماذا فيها؟** و**أين تظهر؟**
       وكلاهما كان يجاب بالتخمين — يحفظ باقة ثم يفتح الصفحة العامة فلا
       يجدها، بلا سطر واحد يقول لماذا.

       والجواب الأول مستنتج لا مخزن: `plans.scope_ids` صفوف، والصف يجمع
       مسارات `paths` المنشورة، والمسار يحمل `course_id` ودروسه. فلا حقل
       يربط درسا بباقة، ولا ينبغي — ولو وجد لصار كل درس جديد يحتاج مرورا
       على كل باقة.
       ===================================================================== */

    /**
     * ما يفتحه صف واحد — خريطة لكل صف، تقرأ باستعلامين لا باستعلام لكل صف.
     *
     * وتنقل كما هي إلى المتصفح فيجمع النموذج أرقامها وأنت تعلم الصفوف،
     * قبل أن تحفظ. ولهذا ترد المواد والمعلمين **قوائم معرفات** لا أعداد:
     * مادة واحدة تدرس في ستة صفوف، وجمع الأعداد يعدها ستا.
     */
    public function grade_reach_map()
    {
        /* تحفظ للطلب كله: شاشة القائمة تسأل عن حال كل باقة، وكل سؤال
           يمر من هنا — فبلا حفظ صارت الشاشة استعلامين في كل صف. */
        static $cache = null;
        if ($cache !== null) return $cache;

        $map = array();

        $paths = $this->safe_rows(
            'SELECT grade_id, subject_id, teacher_id, course_id
               FROM paths WHERE status = "published" AND grade_id > 0'
        );
        if (!$paths) return $cache = $map;

        $cids = array();
        foreach ($paths as $p) if ((int) $p['course_id'] > 0) $cids[] = (int) $p['course_id'];
        $cids = array_values(array_unique($cids));

        /* عد الدروس مرة واحدة لكل دورة ثم يوزع — لا استعلام لكل مسار. */
        $per_course = array();
        if ($cids) {
            $rows = $this->safe_rows(
                'SELECT course_id,
                        COUNT(*)                                        AS n,
                        SUM(CASE WHEN lesson_type = "quiz" THEN 1 ELSE 0 END) AS q,
                        SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END)          AS f
                   FROM lesson
                  WHERE course_id IN (' . implode(',', array_map('intval', $cids)) . ')
                    AND COALESCE(tq_status, "published") = "published"
                  GROUP BY course_id'
            );
            foreach ($rows as $r) {
                $per_course[(int) $r['course_id']] = array(
                    'lessons' => (int) $r['n'], 'quizzes' => (int) $r['q'], 'free' => (int) $r['f'],
                );
            }
        }

        foreach ($paths as $p) {
            $g = (int) $p['grade_id'];
            if (!isset($map[$g])) {
                $map[$g] = array('paths' => 0, 'lessons' => 0, 'quizzes' => 0, 'free' => 0,
                                 'subjects' => array(), 'teachers' => array());
            }
            $map[$g]['paths']++;
            if ((int) $p['subject_id'] > 0) $map[$g]['subjects'][(int) $p['subject_id']] = 1;
            if ((int) $p['teacher_id'] > 0) $map[$g]['teachers'][(int) $p['teacher_id']] = 1;

            $cid = (int) $p['course_id'];
            if ($cid > 0 && isset($per_course[$cid])) {
                $map[$g]['lessons'] += $per_course[$cid]['lessons'];
                $map[$g]['quizzes'] += $per_course[$cid]['quizzes'];
                $map[$g]['free']    += $per_course[$cid]['free'];
            }
        }

        foreach ($map as $g => $v) {
            $map[$g]['subjects'] = array_map('intval', array_keys($v['subjects']));
            $map[$g]['teachers'] = array_map('intval', array_keys($v['teachers']));
        }
        return $cache = $map;
    }

    /** معرفات الصفوف التي تفتحها باقة — `scope_ids` أولا و`scope_id` احتياطا. */
    public function plan_grade_ids($row)
    {
        if (!$row) return array();
        $ids = array_filter(array_map('intval', explode(',', (string)
                   (isset($row['scope_ids']) ? $row['scope_ids'] : ''))));
        if (!$ids && (int) (isset($row['scope_id']) ? $row['scope_id'] : 0) > 0) {
            $ids = array((int) $row['scope_id']);
        }
        return array_values(array_unique($ids));
    }

    /**
     * ما تفتحه الباقة بالأرقام. والصفر يقال صفرا: باقة لم تستورد مسارات
     * صفوفها بعد تعرض «لا محتوى» بدل أن تعد بما ليس في القاعدة.
     */
    public function plan_reach($row)
    {
        $out = array('grades' => 0, 'subjects' => 0, 'paths' => 0,
                     'lessons' => 0, 'quizzes' => 0, 'free' => 0, 'teachers' => 0);
        if (!$row) return $out;

        $scope = (string) $row['scope'];
        if (!in_array($scope, array('grade', 'all'), true)) return $out;

        $map  = $this->grade_reach_map();
        $gids = ($scope === 'all') ? array_keys($map) : $this->plan_grade_ids($row);

        $subjects = array();
        $teachers = array();
        foreach ($gids as $g) {
            $out['grades']++;
            if (!isset($map[$g])) continue;
            $out['paths']   += $map[$g]['paths'];
            $out['lessons'] += $map[$g]['lessons'];
            $out['quizzes'] += $map[$g]['quizzes'];
            $out['free']    += $map[$g]['free'];
            foreach ($map[$g]['subjects'] as $s) $subjects[$s] = 1;
            foreach ($map[$g]['teachers'] as $t) $teachers[$t] = 1;
        }
        $out['subjects'] = count($subjects);
        $out['teachers'] = count($teachers);
        return $out;
    }

    /**
     * هل تظهر هذه الباقة في صفحة الباقات العامة، ولم لا؟
     *
     * الشرط ليس واحدا: `plans.php` و`tqs_bundles()` يرشحان
     * `active = 1 AND scope = "grade"`، ثم `tqs_bundle_stages()` يبني
     * التبويب من `stage` فتسقط منه باقة بلا مرحلة، ثم `subscribe()` يرد
     * `PLAN_NOT_PRICED` عند الشراء. فأربعة أبواب يسقط عندها الظهور أو
     * البيع — والمسؤول كان يعبرها كلها بلا إشارة واحدة.
     *
     * @return array tone: ok|warn|no · label · why
     */
    public function plan_visibility($row)
    {
        if (empty($row['active'])) {
            return tq_t_deep(array('tone' => 'no', 'label' => 'موقوفة',
                         'why'  => 'غير متاحة — لا تظهر ولا تشترى حتى تفعل.'));
        }

        if ((string) $row['scope'] !== 'grade') {
            return tq_t_deep(array('tone' => 'warn', 'label' => 'خارج الصفحة العامة',
                         'why'  => 'صفحة الباقات تعرض باقات الصفوف وحدها. وهذه تشترى برابطها '
                                 . base_url('checkout/' . (string) $row['code']) . ' أو تمنح من اللوحة.'));
        }

        if (!$this->plan_grade_ids($row)) {
            return tq_t_deep(array('tone' => 'no', 'label' => 'لا تظهر',
                         'why'  => 'بلا صفوف — لا محتوى تفتحه.'));
        }

        if (trim((string) $row['stage']) === '') {
            return tq_t_deep(array('tone' => 'no', 'label' => 'لا تظهر',
                         'why'  => 'بلا مرحلة — وصفحة الباقات تبوب بالمرحلة فتسقط من التبويب.'));
        }

        if ((string) $row['period'] !== 'free' && (int) $row['price'] <= 0) {
            return tq_t_deep(array('tone' => 'warn', 'label' => 'تظهر ولا تشترى',
                         'why'  => 'لم تسعر — والشراء يرد بخطأ. ضع سعرها أو اجعل دورتها «مجانية».'));
        }

        /* TQ-PLAN-CYCLE — **الدورة والمدة يقرأهما اثنان مختلفان.**
           `period` اسم تجاري يقرؤه المشتري على البطاقة («كل ثلاثة أشهر»)،
           و`duration_days` هو ما يحسب عليه `ends_at` فعلا. وباقة دورتها
           «ربع سنوية» ومدتها ٣٦٥ يوما تعد المشتري بثلاثة أشهر وتعطيه سنة
           — أو العكس، وهو أسوأ: يدفع سنة ويغلق عليه بعد شهر.
           ولا يرفض عند الحفظ: باقة سنوية بـ٣٩٠ يوما هدية مقصودة. فيقال
           هنا حيث تقرأ حال الباقة، والمسؤول يقرر. */
        $tq_days = (int) $row['duration_days'];
        $tq_rng  = array('monthly' => array(21, 45), 'quarterly' => array(75, 120),
                         'annual'  => array(300, 400));
        $tq_per  = (string) $row['period'];
        if (isset($tq_rng[$tq_per]) && ($tq_days < $tq_rng[$tq_per][0] || $tq_days > $tq_rng[$tq_per][1])) {
            $tq_lbl = array('monthly' => 'شهرية', 'quarterly' => 'ربع سنوية', 'annual' => 'سنوية');
            return tq_t_deep(array('tone' => 'warn', 'label' => 'الدورة والمدة لا تتفقان',
                         'why'  => 'الدورة «' . $tq_lbl[$tq_per] . '» وهي ما يقرؤه المشتري على البطاقة، '
                                 . 'والمدة ' . $tq_days . ' يوما وهي ما يحسب عليه انتهاء الاشتراك فعلا. '
                                 . 'المتوقع من ' . $tq_rng[$tq_per][0] . ' إلى ' . $tq_rng[$tq_per][1] . ' يوما — '
                                 . 'صحح إحداهما، أو اتركهما إن كان الفرق مقصودا.'));
        }

        $reach = $this->plan_reach($row);
        if ($reach['paths'] === 0) {
            return tq_t_deep(array('tone' => 'warn', 'label' => 'تظهر فارغة',
                         'why'  => 'لا مسار منشور في صفوفها — تباع ولا يفتح المشتري شيئا.'));
        }

        return tq_t_deep(array('tone' => 'ok', 'label' => 'تظهر',
                     'why'  => 'في صفحة الباقات تحت ' . tqs_stage_label((string) $row['stage']) . '.'));
    }

    /**
     * ظهور المسار في الكتالوج — TQ-ORPHAN-PURGE.
     *
     * والحال الأولى أخطرها: كورس حذف من اللوحة يمحو `course` و`lesson`
     * ولا يمس `paths`. فيبقى البرنامج **منشورا في «المواد والبرامج»
     * وتفتحه الباقات**، ويقرأ الزائر تحته «دروس هذا البرنامج قيد
     * التجهيز» — إلى الأبد. وخمسة منها في القاعدة. ولا استعلام يخطئ،
     * فلا شيء يقول ذلك إلا هذا العمود.
     */
    public function path_visibility($row)
    {
        $cid = (int) (isset($row['course_id']) ? $row['course_id'] : 0);

        if ($cid > 0) {
            $c = $this->db->select('id, status')->where('id', $cid)->get('course')->row_array();
            if (!$c) {
                return tq_t_deep(array('tone' => 'no', 'label' => 'وعاؤه محذوف',
                             'why'  => 'الدورة المرتبطة (#' . $cid . ') لم تعد موجودة، فالبرنامج '
                                     . 'يعرض ويباع ويفتح فارغا. اربطه بدورة أخرى أو أنزله إلى مسودة.'));
            }
            if ((string) $c['status'] !== 'active' && (string) $row['status'] === 'published') {
                return tq_t_deep(array('tone' => 'warn', 'label' => 'دورته غير منشورة',
                             'why'  => 'البرنامج منشور ودورته حالتها «' . html_escape((string) $c['status'])
                                     . '»، فيعرض عنوانه ولا يفتح محتواه.'));
            }
        }

        if ((string) $row['status'] !== 'published') {
            return tq_t_deep(array('tone' => 'no', 'label' => 'مسودة',
                         'why'  => 'لا يعرض في الكتالوج ولا تفتحه باقة حتى ينشر.'));
        }

        if ($cid <= 0) {
            /* الجملة مفتاح واحد لا شطرين: شطر مترجم وشطر عربي يقرأ ركيكا،
               ونصفان يترجمان منفصلين لا يركبان في لغة أخرى. */
            return tq_t_deep(array('tone' => 'warn', 'label' => 'يظهر فارغا',
                         'why'  => 'بلا دورة مرتبطة — يعرض في الكتالوج بعنوانه ووصفه، ولا درس واحد تحته.'));
        }

        $n = (int) $this->db->where('course_id', $cid)
                            ->where('COALESCE(`tq_status`, "published") =', 'published')
                            ->count_all_results('lesson');
        if ($n === 0) {
            return tq_t_deep(array('tone' => 'warn', 'label' => 'يظهر بلا دروس',
                         'why'  => 'دورته المرتبطة بلا درس منشور واحد.'));
        }

        return tq_t_deep(array('tone' => 'ok', 'label' => 'يظهر',
                     'why'  => 'في «المواد والبرامج» بـ' . $n . ' درسا منشورا.'));
    }

    /**
     * حال الاختبار التشخيصي في الجدول.
     *
     * السؤال الذي يفتح المسؤول الشاشة من اجله: «انشات الاختبار، فهل يعمل؟».
     * واختبار منشور ناقص اسوأ من مسودة: يحبس طلاب صفه امام شاشة لا تكتمل،
     * ولا شيء يقول له ذلك الا شكواهم. فيقال هنا قبل ان يقع.
     */
    public function diag_status($row)
    {
        $this->load->model('taqdar_diag_model');
        $r = $this->taqdar_diag_model->readiness($row);

        $n = isset($r['total']) ? (int) $r['total'] : 0;

        if ((string) $row['status'] !== 'published') {
            return tq_t_deep(array('tone' => 'muted', 'label' => 'مسودة',
                         'why'  => $r['ok']
                             ? 'مكتمل ولم ينشر — لا يعرض ولا يحبس احدا.'
                             : 'قيد الاعداد: ' . implode(' ', $r['why'])));
        }

        if (!$r['ok']) {
            return tq_t_deep(array('tone' => 'no', 'label' => 'منشور وناقص',
                         'why'  => implode(' ', $r['why'])));
        }

        return tq_t_deep(array('tone' => 'ok', 'label' => 'يعمل',
                     'why'  => 'يعرض على طلاب هذا الصف قبل الاشتراك — ' . $n . ' سؤالا.'));
    }

    /**
     * هل خرجت هذه النتيجة الى بريد من يعنيه امرها؟
     *
     * السؤال ليس ترفا: التوصية تخرج لمن يدفع، فان لم تخرج بقيت في شاشة
     * الطالب وحدها ولا يعرف احد. وشاشة تقول «ادى» ولا تقول «ابلغ» تخفي
     * نصف الحال — وهو النصف الذي يشتكى منه.
     *
     * والفحص الغالي (من هم المستلمون؟) لا يجري الا حين يعني شيئا: صف دمغ
     * لا يسأل، وبريد غير مضبوط جوابه واحد لكل الصفوف.
     */
    public function diag_notify_status($row)
    {
        $when = trim((string) (isset($row['notified_at']) ? $row['notified_at'] : ''));
        if ($when !== '') {
            return tq_t_deep(array('tone' => 'ok', 'label' => 'ابلغ',
                         'why'  => 'ارسلت النتيجة الى بريد ولي الامر في ' . $when . '.'));
        }

        $this->load->model('taqdar_mail_model');
        if (!$this->taqdar_mail_model->configured()) {
            return tq_t_deep(array('tone' => 'warn', 'label' => 'ينتظر البريد',
                         'why'  => 'البريد الصادر غير مضبوط — الناقص: '
                                 . implode('، ', $this->taqdar_mail_model->missing())
                                 . '. تضبطه من «البريد الصادر» فترسل ما ادي في الاسبوعين الاخيرين.'));
        }

        $this->load->model('taqdar_diag_model');
        $to = $this->taqdar_diag_model->result_audience((int) $row['student_id']);
        if (!$to) {
            return tq_t_deep(array('tone' => 'muted', 'label' => 'لا بريد',
                         'why'  => 'لا ولي امر مربوط بموافقة نشطة، ولا بريد ولي امر على الحساب، '
                                 . 'ولا بريد صالح للطالب نفسه.'));
        }

        return tq_t_deep(array('tone' => 'warn', 'label' => 'في الطابور',
                     'why'  => 'تعذر الارسال حين سلم الاختبار. يعيده المسح الدوري '
                             . '(taqdar_cron_events placements) الى ' . count($to) . ' مستلما.'));
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
            /* الشاشة الأولى في اللوحة تمر على **كل** وحدة، فهي أول ما يصطدم
               بجدول ينشأ وقت التشغيل ولم ينشأ بعد: عد بلا `ensure_table` رمى
               استثناء أسقط لوحة القيادة كلها — و`admin/dashboard` محولة إليها،
               أي أن اللوحة كلها تسقط لوحدة واحدة لم تفتح مرة.
               فالتجهيز أولا، والعد بعده محروس: وحدة تعطلت تعرض صفرا، ولا
               تحجب الوحدات السبع عشرة الأخرى. */
            $this->ensure_table($spec);
            $out[$key] = array(
                'title' => $spec['title'],
                'icon'  => isset($spec['icon']) ? $spec['icon'] : 'circle',
                'count' => $this->safe_scalar('SELECT COUNT(*) n FROM `' . $spec['table'] . '`'),
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

    /** الحالات الثمان — مصدر واحد يقرأ منه المرشح والعداد. */
    public static $SESSION_STATES = array('requested', 'awaiting_payment', 'confirmed',
                                          'declined', 'expired', 'live', 'completed', 'refunded');

    public function sessions($status = '')
    {
        $where = '';
        $args  = array();
        if ($status !== '' && in_array($status, self::$SESSION_STATES, true)) {
            $where = ' WHERE s.`status` = ?';
            $args[] = $status;
        }

        /* السعر والنصيب والفاتورة تقرأ مع الصف: «كم دفع في هذه الحصة ومن
           أخذ ماذا» أول ما يسأل عنه من يفتح هذه الشاشة، واستعلام ثان لكل
           صف يجعل ثلاثمئة صف ثلاثمئة استعلام. */
        return $this->safe_rows(
            'SELECT s.*, sl.`starts_at`, sl.`duration_min`,
                    TRIM(CONCAT(COALESCE(st.`first_name`,""), " ", COALESCE(st.`last_name`,""))) student_name,
                    st.`email` student_email,
                    TRIM(CONCAT(COALESCE(te.`first_name`,""), " ", COALESCE(te.`last_name`,""))) teacher_name,
                    o.`text` objective_text,
                    i.`invoice_no`, i.`status` invoice_status, i.`total` invoice_total
               FROM `tutoring_sessions` s
               LEFT JOIN `availability_slots` sl ON sl.`id` = s.`slot_id`
               LEFT JOIN `users` st ON st.`id` = s.`student_id`
               LEFT JOIN `users` te ON te.`id` = s.`teacher_id`
               LEFT JOIN `objectives` o ON o.`id` = s.`context_objective_id`
               LEFT JOIN `invoices` i ON i.`id` = s.`invoice_id`'
            . $where . ' ORDER BY sl.`starts_at` IS NULL, sl.`starts_at` DESC, s.`id` DESC LIMIT 300',
            $args
        );
    }

    /** عد كل حالة — الشاشة تعرضها مرشحات فوق الجدول. */
    public function session_tally()
    {
        $out = array_fill_keys(self::$SESSION_STATES, 0);
        foreach ($this->safe_rows('SELECT `status`, COUNT(*) c FROM `tutoring_sessions` GROUP BY `status`') as $r) {
            $out[$r['status']] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * أرقام المال في الحصص — سطر واحد فوق الجدول.
     *
     * «كم أدخلت الحصص هذا الشهر، وكم منها للمعلمين، وكم ينتظر دفعا» ثلاثة
     * أسئلة تطرح معا ولا تجيبها قائمة صفوف: من يقرأ ثلاثمئة صف لا يجمعها
     * بعينه.
     */
    public function session_money()
    {
        $r = $this->safe_rows(
            'SELECT
                COALESCE(SUM(CASE WHEN `paid_at` IS NOT NULL THEN `price_halalas` ELSE 0 END), 0)         gross,
                COALESCE(SUM(CASE WHEN `status` = "completed" AND `paid_at` IS NOT NULL
                             THEN `teacher_share_halalas` ELSE 0 END), 0)                                 teachers,
                COALESCE(SUM(CASE WHEN `status` = "completed" AND `paid_at` IS NOT NULL
                             THEN `price_halalas` - `teacher_share_halalas` ELSE 0 END), 0)               platform,
                COALESCE(SUM(CASE WHEN `status` = "awaiting_payment" THEN `price_halalas` ELSE 0 END), 0) awaiting,
                COALESCE(SUM(CASE WHEN `status` = "refunded" THEN `price_halalas` ELSE 0 END), 0)         refunded,
                SUM(CASE WHEN `status` = "completed" AND `paid_at` IS NOT NULL AND `credited_at` IS NULL
                         THEN 1 ELSE 0 END)                                                              uncredited
               FROM `tutoring_sessions`'
        );
        $r = $r ? $r[0] : array();

        return array(
            'gross'      => (int) ($r['gross'] ?? 0),
            'teachers'   => (int) ($r['teachers'] ?? 0),
            'platform'   => (int) ($r['platform'] ?? 0),
            'awaiting'   => (int) ($r['awaiting'] ?? 0),
            'refunded'   => (int) ($r['refunded'] ?? 0),
            'uncredited' => (int) ($r['uncredited'] ?? 0),
        );
    }

    /**
     * إلغاء حصة من الإدارة — والقرار في `Taqdar_sessions_model` لا هنا.
     *
     * كانت هذه الدالة تقلب الحالة وتفتح الموعد بيدها. وبعد أن صار للحصة
     * ثمن لم يعد ذلك كافيا: فاتورة تبقى «غير مدفوعة» على حصة ألغيت، وقيد
     * في محفظة معلم عن حصة استردت، وموعد يفتح وعليه طلب آخر حي. فالإلغاء
     * صار قرار مال، وقرار المال في طبقته.
     */
    public function cancel_session($session_id, $reason = '')
    {
        $this->load->model('taqdar_sessions_model');
        $r = $this->taqdar_sessions_model->admin_cancel(
            (int) $session_id, (string) $reason, $this->tq_actor());

        if (empty($r['ok'])) return $r;

        $note = trim((string) $reason) !== '' ? ' — ' . trim((string) $reason) : '';
        $body = !empty($r['refunded'])
              ? 'ألغت الإدارة الحصة المتفق عليها' . $note . '، ويرد مبلغها إليك.'
              : 'ألغت الإدارة الحصة المتفق عليها' . $note . '.';

        foreach (array((int) ($r['student_id'] ?? 0), (int) ($r['teacher_id'] ?? 0)) as $uid) {
            if ($uid > 0) $this->push_notification($uid, 'ألغيت الحصة', $body, 'session');
        }
        return $r;
    }

    /**
     * تفعيل حصة دفع ثمنها **بالتحويل البنكي**.
     *
     * وبلاها يبقى التحويل البنكي بابا مسدودا في الحصص وحدها: الاشتراك
     * يفعل بـ`activate_manually()` منذ كتب، والحصة لا يفعلها شيء إلا
     * بوابة البطاقة — فمن حول بنكيا ينتظر مهلة الدفع حتى تمضي.
     */
    public function mark_session_paid($session_id, $reference = '')
    {
        $this->load->model('taqdar_sessions_model');
        $row = $this->db->where('id', (int) $session_id)->get('tutoring_sessions')->row_array();
        if (!$row) return array('ok' => false, 'msg' => 'الحصة غير موجودة.');
        if ((int) $row['invoice_id'] <= 0) {
            return array('ok' => false, 'msg' => 'هذه الحصة بلا فاتورة، فلا شيء يسدد.');
        }

        $r = $this->taqdar_sessions_model->settle_invoice(
            (int) $row['invoice_id'], trim((string) $reference) ?: 'تحويل بنكي', 'manual');

        if (!empty($r['ok']) && empty($r['already'])) {
            foreach (array((int) $row['student_id'], (int) $row['teacher_id']) as $uid) {
                if ($uid > 0) {
                    $this->push_notification($uid, 'ثبتت الحصة',
                        'سجلت الإدارة دفع ثمن الحصة، وصار الموعد مثبتا.', 'session');
                }
            }
        }
        return $r;
    }

    /** معرف المسؤول الحالي — صفر في سطر الأوامر. */
    private function tq_actor()
    {
        return isset($this->session) ? (int) $this->session->userdata('user_id') : 0;
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
                    u.`email`, u.`tq_session_price`, u.`tq_session_percent`,
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
        /* البنية أولا: أعمدة القرار (`decided_by` · `reference` · `reject_reason`)
           أضيفت في الإصدار الثاني من دفتر المحفظة، وقراءتها قبل إنشائها
           ترمي استثناء يبيض الشاشة. */
        $this->load->model('taqdar_wallet_model');
        $this->taqdar_wallet_model->install_schema();

        $where = '';
        if ($status === 'pending')       { $where = ' WHERE p.`status` = 0'; }
        elseif ($status === 'paid')      { $where = ' WHERE p.`status` = 1'; }
        elseif ($status === 'rejected')  { $where = ' WHERE p.`status` = 2'; }

        $rows = $this->safe_rows(
            'SELECT p.*,
                    TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) teacher_name,
                    u.`email` teacher_email, u.`phone` teacher_phone,
                    TRIM(CONCAT(COALESCE(d.`first_name`,""), " ", COALESCE(d.`last_name`,""))) decided_name,
                    w.`balance_available`, w.`balance_locked`, w.`balance_pending`
               FROM `payout` p
               LEFT JOIN `users` u   ON u.`id` = p.`user_id`
               LEFT JOIN `users` d   ON d.`id` = p.`decided_by`
               LEFT JOIN `wallets` w ON w.`owner_user_id` = p.`user_id`'
            . $where . ' ORDER BY p.`status` ASC, p.`id` DESC LIMIT 300'
        );

        /* السياق الذي يقرر به: كم طلبا لهذا المعلم قبل هذا، وهل تغيرت
           وجهته. واستعلام لكل صف يجعل الشاشة ثلاثمئة استعلام — فالسياق
           يجلب مرة واحدة لكل المعلمين الظاهرين. */
        $ids = array();
        foreach ($rows as $r) if ((int) $r['user_id'] > 0) $ids[(int) $r['user_id']] = 1;
        $hist = $ids ? $this->payout_history(array_keys($ids)) : array();

        foreach ($rows as &$r) {
            $uid = (int) $r['user_id'];
            $h   = isset($hist[$uid]) ? $hist[$uid] : array('paid' => 0, 'rejected' => 0, 'total' => 0,
                                                            'paid_sum' => 0, 'last_dest' => null, 'first_at' => 0);
            $r['hist'] = $h;

            /* الوجهة الجديدة تعلم لا تمنع. حساب اخترق تغير وجهته أول ما
               يفعل، ومسؤول يرى «حول إلى هذا الآيبان ست مرات» يقرر غير من
               يرى «آيبان لم يظهر قط». والقرار يبقى قراره. */
            $r['dest_changed'] = ($h['last_dest'] !== null
                                  && (string) $h['last_dest'] !== ''
                                  && (string) $h['last_dest'] !== (string) $r['destination']);
            $r['first_request'] = ((int) $h['total'] <= 1);
        }
        unset($r);

        return $rows;
    }

    /**
     * سجل السحب لكل معلم في نداء واحد — كم طلب، كم حول، وآخر وجهة **حول
     * إليها فعلا** (لا آخر وجهة طلبت: طلب معلق لم يقرر بعد ليس سابقة).
     */
    private function payout_history($user_ids)
    {
        $in = implode(',', array_map('intval', (array) $user_ids));
        if ($in === '') return array();

        $out = array();
        foreach ($this->safe_rows(
            'SELECT `user_id`, COUNT(*) total,
                    SUM(CASE WHEN `status` = 1 THEN 1 ELSE 0 END) paid,
                    SUM(CASE WHEN `status` = 2 THEN 1 ELSE 0 END) rejected,
                    COALESCE(SUM(CASE WHEN `status` = 1 THEN `amount_halalas` ELSE 0 END),0) paid_sum,
                    MIN(`date_added`) first_at
               FROM `payout` WHERE `user_id` IN (' . $in . ') GROUP BY `user_id`'
        ) as $r) {
            $out[(int) $r['user_id']] = array(
                'total'     => (int) $r['total'],
                'paid'      => (int) $r['paid'],
                'rejected'  => (int) $r['rejected'],
                'paid_sum'  => (int) $r['paid_sum'],
                'first_at'  => (int) $r['first_at'],
                'last_dest' => null,
            );
        }

        foreach ($this->safe_rows(
            'SELECT p.`user_id`, p.`destination`
               FROM `payout` p
               JOIN (SELECT `user_id`, MAX(`id`) mid FROM `payout`
                      WHERE `status` = 1 AND `user_id` IN (' . $in . ') GROUP BY `user_id`) m
                 ON m.`mid` = p.`id`'
        ) as $r) {
            $u = (int) $r['user_id'];
            if (isset($out[$u])) $out[$u]['last_dest'] = (string) $r['destination'];
        }

        return $out;
    }

    /**
     * ملف طلب سحب واحد — كل ما يحتاجه المسؤول ليقرر ثم ليجيب من يسأل.
     *
     * والقائمة لا تكفي: القرار المالي يحتاج أن يعرف صاحبه **من أين جاء
     * هذا الرصيد** (أي مبيعات كونته)، و**ما سابقة هذا المعلم**، و**ماذا
     * يبقى له بعد التحويل**. وهذه ثلاثة استعلامات لا تحتمل في صف جدول،
     * فلها شاشتها.
     *
     * @return array|null
     */
    public function payout_detail($id)
    {
        $this->load->model('taqdar_wallet_model');
        $this->taqdar_wallet_model->install_schema();

        $rows = $this->safe_rows(
            'SELECT p.*,
                    TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) teacher_name,
                    u.`email` teacher_email, u.`phone` teacher_phone,
                    u.`date_added` teacher_since, u.`status` teacher_status,
                    TRIM(CONCAT(COALESCE(d.`first_name`,""), " ", COALESCE(d.`last_name`,""))) decided_name,
                    d.`email` decided_email
               FROM `payout` p
               LEFT JOIN `users` u ON u.`id` = p.`user_id`
               LEFT JOIN `users` d ON d.`id` = p.`decided_by`
              WHERE p.`id` = ? LIMIT 1',
            array((int) $id)
        );
        if (!$rows) return null;
        $p   = $rows[0];
        $uid = (int) $p['user_id'];

        /* الرصيد يقرأ **بعد المصالحة**: الدفتر يبنى عند أول قراءة، ورقم
           قبلها صفر كاذب — وصفر كاذب في شاشة قرار مالي أسوأ من بطء. */
        $wallet = $this->taqdar_wallet_model->wallet_of($uid);
        $this->taqdar_wallet_model->sync($uid);
        $wallet = $this->taqdar_wallet_model->wallet_of($uid);

        $hist = $this->payout_history(array($uid));
        $h    = isset($hist[$uid]) ? $hist[$uid] : array('total' => 0, 'paid' => 0, 'rejected' => 0,
                                                         'paid_sum' => 0, 'first_at' => 0, 'last_dest' => null);

        return array(
            'payout'   => $p,
            'wallet'   => $wallet,
            'hist'     => $h,
            'dest_changed'  => ($h['last_dest'] !== null && (string) $h['last_dest'] !== ''
                                && (string) $h['last_dest'] !== (string) $p['destination']),
            'first_request' => ((int) $h['total'] <= 1),
            'sources'  => $this->payout_sources($wallet['id']),
            'others'   => $this->safe_rows(
                'SELECT `id`,`amount_halalas`,`status`,`date_added`,`destination`,
                        `requested_channel`,`payment_type`,`reference`,`reject_reason`
                   FROM `payout` WHERE `user_id` = ? AND `id` <> ?
                  ORDER BY `id` DESC LIMIT 10',
                array($uid, (int) $id)
            ),
            'entries'  => $this->safe_rows(
                'SELECT `type`,`bucket`,`amount`,`subject`,`occurred_at`,`origin`
                   FROM `wallet_entries` WHERE `wallet_id` = ?
                  ORDER BY `id` DESC LIMIT 25',
                array($wallet['id'])
            ),
            'teacher_paths' => (int) $this->safe_scalar(
                'SELECT COUNT(*) n FROM `paths` WHERE `teacher_id` = ?', array($uid)
            ),
        );
    }

    /**
     * من أين جاء رصيد هذا المعلم — آخر المبيعات التي كونته، ولكل منها
     * حصته الصافية بعد العمولة.
     *
     * وهذا هو الجواب على «المعلم يقول إن له أكثر من هذا»: تفتح الشاشة
     * فترى البيعات نفسها التي رآها في كشفه، بالأرقام نفسها.
     */
    private function payout_sources($wallet_id, $limit = 12)
    {
        $sales = $this->safe_rows(
            'SELECT `origin`, MAX(`subject`) subject, MAX(`occurred_at`) occurred_at,
                    SUM(`amount`) net
               FROM `wallet_entries`
              WHERE `wallet_id` = ?
                AND `type` IN ("sale","commission","retained","refund")
                AND `origin` IS NOT NULL
              GROUP BY `origin`
              ORDER BY MAX(`occurred_at`) DESC, MAX(`id`) DESC
              LIMIT ' . (int) $limit,
            array((int) $wallet_id)
        );
        foreach ($sales as &$s) {
            $s['net']  = (int) $s['net'];
            $s['kind'] = (strpos((string) $s['origin'], 'plansub:') === 0) ? 'plan' : 'course';
        }
        unset($s);
        return $sales;
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
     *
     * ═══════════════════════════════════════════════════════════════════
     * TQ-WA-PAY — وواتساب بعد البريد، لإشعارات المال وحدها
     * ═══════════════════════════════════════════════════════════════════
     *
     * القناة الثالثة لا تفتح لكل ما يمر من هنا، بل لما يقرره `$type`:
     * `Taqdar_wa_model::$PAY_TYPES` تسمي أنواع المال (`subscription` ·
     * `wallet` · `invoice` · `payment` · `payout`)، وما سواها يبقى في
     * المنصة والبريد كما كان.
     *
     * والتفريق ليس تحفظا زائدا: واتساب قناة يدفع ثمن كل رسالة فيها،
     * وقناة **تسد** — من يصله من منصة تعليمية خبر كل حصة ألغيت وكل
     * نتيجة امتحان يبلغ عن الرقم، وبلاغات المستلمين تخفض جودة الرقم عند
     * ميتا ثم تخفض حد إرساله اليومي. فالمال وحده يستحق المقاطعة، وهو
     * أيضا ما ينتظره صاحبه فعلا.
     *
     * و`$wa` يمرر صريحا لمن أراد أن يخالف الاشتقاق في الاتجاهين.
     */
    public function push_notification($to_user, $title, $description, $type = 'system',
                                      $mail = true, $wa = null)
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

        /* وواتساب بعده — تابعا كالبريد، ولإشعارات المال وحدها.
           و`Taqdar_wa_model` يرد `false` بهدوء حين لا يكون مضبوطا أو
           حين لا رقم لصاحب الحساب، فلا يسقط تفعيل اشتراك لأن رسالة
           لم تخرج. وهذا النداء قد يقع داخل ويبهوك دفع، فمهلته اثنتا
           عشرة ثانية ولا يرمي شيئا أبدا. */
        if ($wa !== false) {
            $this->wa_user((int) $to_user, $title, $description, (string) $type, $wa === true);
        }

        return true;
    }

    /**
     * يرسل نص إشعار بواتساب إلى صاحبه إن كان له رقم وواتساب مضبوط.
     *
     * ولا يرمي شيئا: النداء يقع في ويبهوك دفع وفي شاشة إدارة، واستثناء
     * هنا يعني دفعة بلا تفعيل أو شاشة بيضاء للمسؤول.
     */
    /**
     * إشعار واتساب وحده — لمن كتب صف `notifications` بيده.
     *
     * وليس بابا ثانيا: هو `wa_user()` نفسها بحارسها كله (سياسة العائلة،
     * وتفضيل صاحب الحساب، وساعات الصمت). ومن يكتب صفه بيده — لأن
     * `from_user` عنده طرف بعينه لا الفاعل في الجلسة، كما في روابط ولي
     * الأمر — يحتاج القناة بلا الصف، ونسخة ثانية من الحارس عنده تفترق
     * عن هذه عند أول تشديد.
     */
    public function notify_wa($user_id, $title, $body, $type = 'system')
    {
        return $this->wa_user((int) $user_id, $title, $body, (string) $type, false);
    }

    private function wa_user($user_id, $title, $body, $type = 'system', $force = false)
    {
        try {
            /* النموذج يحمل هنا لا في `push_notification`: ثوابت الصنف
               تقرأ منه، وقراءتها قبل تحميله خطأ قاتل في CI3 — لا محمل
               أصناف يوجد. */
            $this->load->model('taqdar_wa_model');

            $user_id = (int) $user_id;
            $type    = (string) $type;
            $family  = $this->taqdar_wa_model->family_of($type);

            /* ١) سياسة المنصة: أتخرج هذه العائلة أصلا؟ */
            if (!$force && !$this->taqdar_wa_model->family_on($family)) return false;
            if ($force && !$this->taqdar_wa_model->ready())            return false;

            /* ٢) وتفضيل صاحب الحساب فوقها.
                  القناة الثالثة **تطرق الباب**، والبريد ينتظر في صندوق
                  حتى يفتح. فمن أطفأ نوعا في شاشة إعداداته أطفأه هنا قبل
                  غيره — وإرسال ما رفضه صراحة إلى جواله هو ما يجعله يسد
                  الرقم، فتضيع معه إشعارات المال التي لم يرفضها. */
            if (!$force && !$this->tq_wa_wanted($user_id, $type)) return false;

            /* ٣) وساعات الصمت — للعائلات التي تحتملها وحدها.
                  المال والحساب والحصص تمر: من دفع الآن ينتظر جوابا الآن.
                  وما سواها **يؤجل ولا يسقط**: الطابور يخرجه أول لحظة بعد
                  الصمت. وإسقاطه هنا يجعل من نام يفقد خبره لا يؤجله. */
            if (!$force && $this->taqdar_wa_model->family_quiets($family)
                && $this->tq_wa_quiet_now($user_id)) {
                return $this->tq_wa_defer($user_id, $type, $title, $body, $family);
            }

            $to = $this->taqdar_wa_model->phone_of($user_id);
            if ($to === '') return false;   /* لا رقم: لا محاولة ولا سطر سجل */

            return (bool) $this->taqdar_wa_model->send_notice($to, $title, $body,
                array('purpose' => mb_substr($type, 0, 24), 'user_id' => $user_id));
        } catch (Throwable $e) {
            log_message('error', 'push_notification wa: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * أيريد صاحب الحساب هذا النوع على واتساب؟
     *
     * المصدر `Taqdar_settings_model::allows()` وحدها — وهي التي تقرؤها
     * شاشة إعداداته. ونسخة ثانية من القاعدة هنا تجعل المربع الذي يطفئه
     * الطالب لا يطفئ شيئا، وهو أسوأ من غياب الإعداد: الغياب يرى فيطلب،
     * والمربع الكاذب يصدق فلا يطلب.
     *
     * وما لا رأي لصاحبه فيه مسموح: `allows()` ترد `true` لنوع خارج
     * قائمتها — فنوع جديد يخرج ويرى، ولا يبتلع بلا أن يعرف أحد لماذا.
     */
    private function tq_wa_wanted($user_id, $type)
    {
        try {
            $this->load->model('taqdar_settings_model');
            return (bool) $this->taqdar_settings_model->allows(
                (int) $user_id, (string) $type, 'whatsapp');
        } catch (Throwable $e) {
            return true;   /* تعذر القراءة لا يصادر قرارا لم يتخذ */
        }
    }

    /** أهو داخل ساعات صمته الآن؟ الحكم من `Taqdar_events_model` وحده. */
    private function tq_wa_quiet_now($user_id)
    {
        try {
            $this->load->model('taqdar_events_model');
            return (bool) $this->taqdar_events_model->is_quiet_now((int) $user_id);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * يؤجل رسالة واتساب إلى ما بعد الصمت — في الطابور نفسه لا في ثان.
     *
     * `tq_notify_queue` يحمل عمود `channel` منذ كتب، ونصه في الشيفرة
     * صريح: «يوم يضاف الواتساب يصير صفا بقناة أخرى في الطابور نفسه، بلا
     * طابور ثان يعاد فيه التأجيل والإعادة». وهذا ذلك اليوم.
     */
    private function tq_wa_defer($user_id, $type, $title, $body, $family)
    {
        try {
            $this->load->model('taqdar_events_model');
            return (bool) $this->taqdar_events_model->enqueue(
                (int) $user_id, (string) $type, (string) $title, strip_tags((string) $body),
                $family === 'marketing' ? 'marketing' : 'learning',
                null, 'whatsapp');
        } catch (Throwable $e) {
            log_message('error', 'push_notification wa defer: ' . $e->getMessage());
            return false;
        }
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
    public function broadcast($audience, $title, $description, $by_mail = false, $by_wa = false)
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
        $waed   = $by_wa   ? $this->broadcast_wa($ids, $title, $description)   : 0;

        $this->audit('broadcast', 'notifications', null,
                     array('audience' => $audience, 'title' => $title,
                           'count' => $sent, 'mailed' => $mailed, 'wa' => $waed));

        $this->last_broadcast_mailed = $mailed;
        $this->last_broadcast_wa     = $waed;
        return $sent;
    }

    /** كم مستلما وصلته النسخة البريدية من آخر إشعار جماعي. */
    public $last_broadcast_mailed = 0;

    /** وكم أودع منه في طابور واتساب. */
    public $last_broadcast_wa = 0;

    /**
     * النسخة الواتسابية من الإشعار الجماعي — **تودع في الطابور ولا ترسل
     * هنا**.
     *
     * والفارق ليس أناقة: البريد يخرج بنسخة مخفية، فألفا مستلم أربعون
     * رسالة. وواتساب **رسالة لكل رقم** — لا نسخة مخفية فيه ولا مجموعات:
     * ألفا مستلم ألفا نداء إلى `graph.facebook.com`، كل واحد منها رحلة
     * شبكة كاملة. وذلك يعلق الصفحة حتى تنتهي مهلة PHP، فيقرأ المسؤول
     * خطأ ويعيد الإرسال، فيصل من وصله مرتين.
     *
     * فالإيداع هنا (استعلام واحد لكل خمسمئة)، والصرف على الكرون بمهلته
     * وإعادة محاولته وساعات صمت كل مستلم — وهو الطابور القائم أصلا.
     *
     * ويعود **عدد ما أودع** لا عدد ما وصل: الثاني لا يعرف بعد، وقوله
     * الآن كذب على المسؤول.
     */
    private function broadcast_wa($ids, $title, $description)
    {
        try {
            $this->load->model('taqdar_wa_model');
            if (!$this->taqdar_wa_model->ready()) return 0;

            $this->load->model('taqdar_events_model');
            $this->taqdar_events_model->ensure_queue();
        } catch (Throwable $e) {
            log_message('error', 'broadcast wa: ' . $e->getMessage());
            return 0;
        }

        /* ومن لا رقم له لا يودع له صف: الطابور يصرفه ثم يفشل ثم يعيد
           خمس مرات ثم يموت — خمس محاولات على من لا سبيل إليه أصلا. */
        try {
            $rows = $this->db->select('id')->where_in('id', $ids)
                             ->where('phone IS NOT NULL', null, false)
                             ->where('phone !=', '')
                             ->get('users')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return 0;
        }

        $now  = time();
        $done = 0;
        $body = strip_tags((string) $description);

        foreach (array_chunk($rows, 500) as $chunk) {
            $batch = array();
            foreach ($chunk as $r) {
                $batch[] = array(
                    'user_id'     => (int) $r['id'],
                    'type'        => 'admin',
                    'category'    => 'marketing',
                    'channel'     => 'whatsapp',
                    'title'       => mb_substr((string) $title, 0, 250),
                    'body'        => $body,
                    'state'       => 'queued',
                    'attempts'    => 0,
                    'next_try_at' => $now,
                    'created_at'  => $now,
                );
            }
            try {
                $this->db->insert_batch('tq_notify_queue', $batch);
                $done += count($batch);
            } catch (Throwable $e) {
                // دفعة تعثرت لا تسقط الباقي
            }
        }

        return $done;
    }

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
    /**
     * كل الحسابات، مرشحة بالدور ومبحوثا فيها ومرقمة.
     *
     * TQ-PEOPLE-CAP — كانت `LIMIT 400` وحدها بلا صفحة ثانية، وذيل
     * الشاشة يعتذر: «تعرض أول ٤٠٠ حساب. استعمل البحث للوصول إلى ما
     * بعدها». وهو محتمل ما دام في القاعدة أربعمئة حساب؛ ويصير عطلا
     * تاما عند الألف: **كل حساب سجل قبل آخر أربعمئة لا يبلغ من اللوحة
     * أصلا** إلا أن يعرف المسؤول اسمه أو بريده مسبقا — وهو لا يعرفه،
     * لأن الشاشة هي التي يفتحها ليعرف. ولا خطأ يظهر: الجدول يعمل،
     * والأرقام في التبويبات تقول ألفا، والقائمة تحته تعرض أربعمئة.
     *
     * والعد يستعلم مرة بالشروط نفسها: عدد يقدر أو يقرأ من `role_tally`
     * يفترق عن المعروض متى بحث أحد.
     *
     * و`image` تنضم إلى الأعمدة: بناء رابط الصورة كان يستعلم
     * (`User_model::get_user_image_url`) **صفا صفا** — خمسون استعلاما
     * لشاشة واحدة، وهي في جدول يقرأ منه أصلا.
     */
    public function people($role = '', $q = '', $page = 1, $per = 50)
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

        $sql_where = implode(' AND ', $where);
        $per   = max(1, (int) $per);
        $total = (int) $this->safe_scalar('SELECT COUNT(*) n FROM `users` u WHERE ' . $sql_where, $args);
        $pages = max(1, (int) ceil($total / $per));
        $page  = min(max(1, (int) $page), $pages);

        $rows = $this->safe_rows(
            'SELECT u.`id`, u.`first_name`, u.`last_name`, u.`email`, u.`phone`,
                    u.`status`, u.`is_instructor`, u.`role_id`, u.`date_added`, u.`image`,
                    COALESCE(u.`tq_gate`, "student") tq_gate
               FROM `users` u
              WHERE ' . $sql_where . '
              ORDER BY u.`id` DESC
              LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
            $args
        );

        return array('rows' => $rows, 'total' => $total, 'page' => $page,
                     'pages' => $pages, 'per' => $per);
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

    /* =====================================================================
       TQ-TEACHER-ADD — حساب معلم تنشئه الإدارة، يفتح بلا مراجعة ولا رمز

       باب الانضمام الوحيد كان صفحة التسجيل، ومساره ثلاث محطات: رمز تحقق
       يخرج إلى بريد المتقدم، ثم توثيق هوية، ثم اعتماد إدارة — والحساب
       مغلق (`status = 0`) حتى تمر الثلاث. وهو الصواب لمن يطرق الباب من
       خارج، **ولا معنى له لمن تعرفه الإدارة أصلا**: معلم تعاقدت معه
       وبيدها بياناته. فكانت تسجل له حسابا من صفحة التسجيل ثم تعود إلى
       «طلبات المعلمين» توثقه وتعتمده — بشرط أن يكون قد استقبل الرمز
       أولا وكتبه، وهو رمز يذهب إلى بريده هو لا إليها. فباب لا يعبر إلا
       بيد صاحبه، والإدارة تنتظره ليدخل حسابا هي التي أنشأته.

       وهذه الشاشة تنشئ الحساب **مفتوحا معتمدا مؤكدا** في خطوة واحدة:
       - `status = 1` — الدخول مفتوح في الحال، ولا شاشة اعتماد بعده.
       - `is_instructor = 1` مع `tq_gate = "teacher"` — الاثنان معا:
         الأول تشتق منه `tq_role()` وعليه تقوم لوحة المعلم، والثاني
         تقرؤه شاشة الدخول ورسائلها. وحساب بأحدهما دون الآخر يهبط في
         لوحة الطالب أو يقرأ «طلبك قيد المراجعة» بلا طلب.
       - `tq_verified_at` مختوم — لا رمز يطلب منه ولا شاشة تأكيد.

       **ولا صف في `applications`**: ذاك سجل مراجعة، وما لا يراجع لا
       يكتب فيه صف «معتمد» يوهم من يفتح الشاشة أن لجنة نظرت في أوراق.
       والأثر يترك في `audit_log` — وهو موضعه.
       ===================================================================== */

    /**
     * ينشئ حساب معلم من اللوحة.
     *
     * القواعد كلها هنا: الشاشة تعرض الحقول وترد الأخطاء ولا تحكم. وشروط
     * الاسم والبريد وكلمة المرور هي شروط `Login::register` نفسها —
     * وشرطان مختلفان لحقل واحد يجعلان اللوحة تقبل بريدا يرفضه التسجيل،
     * فينشأ حساب لا يستطيع صاحبه استعادة كلمة مروره.
     *
     * ويرد مصفوفة: `ok` و`errors` و`user_id` و`message`.
     */
    public function create_teacher()
    {
        $in    = $this->input;
        $first = trim((string) $in->post('first_name'));
        $last  = trim((string) $in->post('last_name'));
        $email = trim((string) $in->post('email'));
        $pass  = (string) $in->post('password');
        $conf  = (string) $in->post('password_confirm');
        $phone = trim((string) $in->post('phone'));
        $title = trim((string) $in->post('title'));
        $skill = trim((string) $in->post('skills'));
        $bio   = trim((string) $in->post('biography'));

        $errors = array();

        if (mb_strlen($first) < 2 || mb_strlen($first) > 40) {
            $errors[] = 'اكتب الاسم الأول (حرفان على الأقل، وأربعون على الأكثر).';
        }
        if (mb_strlen($last) < 2 || mb_strlen($last) > 40) {
            $errors[] = 'اكتب اسم العائلة (حرفان على الأقل، وأربعون على الأكثر).';
        }
        /* خمسون: طول `users.email`. وما زاد يقص عند الحفظ فينشأ حساب
           ببريد لا يصل إليه شيء — ولا استعادة له. */
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 50) {
            $errors[] = 'البريد الإلكتروني غير صحيح، أو أطول من خمسين محرفا.';
        } elseif ($this->db->where('email', $email)->count_all_results('users') > 0) {
            $errors[] = 'هذا البريد مسجل لحساب آخر. ابحث عنه في «كل الحسابات».';
        }
        if (mb_strlen($pass) < 8) {
            $errors[] = 'كلمة المرور ثمانية محارف على الأقل.';
        } elseif (strlen($pass) > 72) {
            /* bcrypt يقص عند اثنتين وسبعين بايت بلا إشعار: كلمة أطول
               تحفظ مقصوصة، فيدخل صاحبها بأولها ويظن الباقي محسوبا. */
            $errors[] = 'كلمة المرور أطول من اللازم. اجعلها دون اثنتين وسبعين خانة.';
        } elseif ($pass !== $conf) {
            $errors[] = 'كلمتا المرور غير متطابقتين.';
        }

        /* TQ-PHONE-INTL — الرقم يفحص في دولته ويخزن `+<رمز><وطني>`.
           وهو اختياري هنا: الدخول لا يتوقف عليه، وواتساب وحده يحتاجه. */
        $e164 = '';
        if ($phone !== '') {
            $ph = tq_phone_check($phone, (string) $in->post('phone_cc'));
            if (!$ph['ok']) $errors[] = $ph['error'];
            else            $e164 = $ph['e164'];
        }

        if (mb_strlen($title) > 160) $errors[] = 'الصفة أطول من المسموح (160 حرفا).';
        if (mb_strlen($skill) > 255) $errors[] = 'قائمة المواد أطول من المسموح (255 حرفا).';
        if (mb_strlen($bio) > 1500)  $errors[] = 'النبذة أطول من المسموح (1500 حرف).';

        if ($errors) {
            return array('ok' => false, 'errors' => $errors, 'user_id' => 0, 'message' => '');
        }

        /* أربعة أعمدة `NOT NULL` بلا افتراض في مخطط Academy
           (`skills` · `payment_keys` · `sessions` · `social_links`):
           تركها للقاعدة يرد الإدراج كله في وضع صارم. */
        $now  = time();
        $data = array(
            'first_name'        => $first,
            'last_name'         => $last,
            'email'             => $email,
            'password'          => tq_password_hash($pass),
            'phone'             => $e164,
            /* تجرد الوسوم عند الحفظ لا عند العرض: النبذة والصفة تعرضان
               في صفحة المعلم العامة، وتجريد عند العرض ينسى في شاشة. */
            'title'             => strip_tags($title),
            'skills'            => strip_tags($skill),
            'biography'         => strip_tags($bio),
            'role_id'           => 2,
            'is_instructor'     => 1,
            'tq_gate'           => 'teacher',
            'status'            => 1,
            'is_public'         => ((string) $in->post('is_public') === '1') ? 1 : 0,
            'date_added'        => $now,
            'last_modified'     => $now,
            'wishlist'          => json_encode(array()),
            'payment_keys'      => json_encode(array()),
            'social_links'      => json_encode(array('facebook' => '', 'twitter' => '', 'linkedin' => '')),
            'sessions'          => '',
            'verification_code' => '',
            'terms_accepted_at' => date('Y-m-d H:i:s'),
        );

        $this->db->insert('users', $data);
        $user_id = (int) $this->db->insert_id();
        if ($user_id < 1) {
            return array('ok' => false, 'user_id' => 0, 'message' => '',
                         'errors' => array('تعذر إنشاء الحساب. أعد المحاولة.'));
        }

        /* التأكيد يختم بعد الإدراج لا فيه: العمود ينشأ وقت التشغيل
           (`Taqdar_otp_model::ensure_schema()`)، وقاعدة لم يمر عليها
           تسجيل بعد لا تحمله — فكتابته في صف الإدراج ترد الإدراج كله
           بـ«Unknown column» فلا ينشأ حساب ولا يظهر سبب. */
        $this->stamp_verified($user_id);

        $img = $this->store_avatar($user_id);
        if ($img !== '') {
            $this->db->where('id', $user_id)->update('users', array('image' => $img));
        }

        $this->audit('teacher_created', 'users#' . $user_id, null, array(
            'email'     => $email,
            'name'      => trim($first . ' ' . $last),
            'is_public' => (int) $data['is_public'],
            'source'    => 'admin',
        ));

        return array('ok' => true, 'errors' => array(), 'user_id' => $user_id,
                     'message' => 'أنشئ حساب المعلم مفتوحا. يستطيع الدخول الآن ببريده وكلمة مروره.');
    }

    /** صف المعلم، أو `null` — والدور يفحص هنا لا في الشاشة. */
    public function teacher_row($user_id)
    {
        $u = $this->db->where('id', (int) $user_id)->get('users')->row_array();
        if (!$u || (int) $u['is_instructor'] !== 1) return null;
        return $u;
    }

    /**
     * ما يقوله الرقم عن هذا المعلم قبل أن يعدل أو يحذف.
     *
     * وكل استعلام ملفوف (`safe_scalar`): جدول لم يستعمل بعد يرمي استثناء
     * يبيض الشاشة كلها، ورقم ناقص أهون من شاشة لا تفتح.
     *
     * والنطاق نطاق `Taqdar_teacher_model` نفسه — منشئ الكورس أو أحد
     * معلميه (`FIND_IN_SET`)، ونطاق ثان يقول للمسؤول «بلا كورسات» عن
     * معلم يدرس ثلاثة.
     */
    public function teacher_stats($user_id)
    {
        $id = (int) $user_id;
        $own = '(c.`creator` = ? OR FIND_IN_SET(?, c.`user_id`) > 0)';

        return array(
            'courses'   => $this->safe_scalar("SELECT COUNT(*) n FROM `course` c WHERE $own", array($id, $id)),
            'published' => $this->safe_scalar("SELECT COUNT(*) n FROM `course` c WHERE $own AND c.`status` = 'active'", array($id, $id)),
            'lessons'   => $this->safe_scalar(
                "SELECT COUNT(*) n FROM `lesson` l JOIN `course` c ON c.`id` = l.`course_id` WHERE $own",
                array($id, $id)),
            'students'  => $this->safe_scalar(
                "SELECT COUNT(DISTINCT e.`user_id`) n FROM `enrol` e JOIN `course` c ON c.`id` = e.`course_id` WHERE $own",
                array($id, $id)),
            'paths'     => $this->safe_scalar(
                "SELECT COUNT(*) n FROM `paths` p JOIN `course` c ON c.`id` = p.`course_id` WHERE $own",
                array($id, $id)),
            /* `paths.teacher_id` عمود مستقل عن ملكية الكورس: برنامج قد
               يسند إلى معلم لا يملك كورسه، وحذفه يترك المسار يشير إلى
               معرف لا يقابل أحدا. */
            'own_paths' => $this->safe_scalar('SELECT COUNT(*) n FROM `paths` WHERE `teacher_id` = ?', array($id)),
            'sessions'  => $this->safe_scalar('SELECT COUNT(*) n FROM `tutoring_sessions` WHERE `teacher_id` = ?', array($id)),
            'shares'    => $this->safe_scalar('SELECT COUNT(*) n FROM `revenue_shares` WHERE `teacher_id` = ?', array($id)),
            'entries'   => $this->safe_scalar('SELECT COUNT(*) n FROM `wallet_entries` WHERE `wallet_id` IN
                                               (SELECT `id` FROM `wallets` WHERE `owner_user_id` = ?)', array($id)),
            'payouts'   => $this->safe_scalar('SELECT COUNT(*) n FROM `payout` WHERE `user_id` = ?', array($id)),
            'apps'      => $this->safe_scalar('SELECT COUNT(*) n FROM `applications` WHERE `user_id` = ?', array($id)),
        );
    }

    /**
     * تعديل حساب المعلم.
     *
     * قواعده قواعد `create_teacher()` نفسها — الدالة الوحيدة التي تفحص
     * اسما وبريدا وجوالا، فلا يقبل التعديل ما يرده الإنشاء. والفارقان:
     *
     * ١ — **كلمة المرور تترك فارغة فلا تمس.** حقل يفرض كلمة في كل حفظ
     *     يجعل تصحيح حرف في النبذة يبدل كلمة مرور صاحبها بلا أن يطلب
     *     أحد ذلك، ويخرجه من كل أجهزته.
     * ٢ — **البريد يفحص فرادته إلا على نفسه.** وبلا استثناء الصف نفسه
     *     يرد كل حفظ لا يغير البريد بـ«هذا البريد مسجل لحساب آخر».
     *
     * و`is_instructor` و`tq_gate` لا يمسان: تحويل معلم إلى طالب بحفظ
     * نموذج تعديل يقطع كورساته عن أصحابها بلا سؤال.
     */
    public function update_teacher($user_id)
    {
        $user_id = (int) $user_id;
        $row     = $this->teacher_row($user_id);
        if (!$row) {
            return array('ok' => false, 'user_id' => 0, 'message' => '',
                         'errors' => array('هذا الحساب ليس حساب معلم، أو لم يعد موجودا.'));
        }

        $in    = $this->input;
        $first = trim((string) $in->post('first_name'));
        $last  = trim((string) $in->post('last_name'));
        $email = trim((string) $in->post('email'));
        $pass  = (string) $in->post('password');
        $conf  = (string) $in->post('password_confirm');
        $phone = trim((string) $in->post('phone'));
        $title = trim((string) $in->post('title'));
        $skill = trim((string) $in->post('skills'));
        $bio   = trim((string) $in->post('biography'));

        $errors = array();

        if (mb_strlen($first) < 2 || mb_strlen($first) > 40) {
            $errors[] = 'اكتب الاسم الأول (حرفان على الأقل، وأربعون على الأكثر).';
        }
        if (mb_strlen($last) < 2 || mb_strlen($last) > 40) {
            $errors[] = 'اكتب اسم العائلة (حرفان على الأقل، وأربعون على الأكثر).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 50) {
            $errors[] = 'البريد الإلكتروني غير صحيح، أو أطول من خمسين محرفا.';
        } elseif ($this->db->where('email', $email)->where('id !=', $user_id)
                           ->count_all_results('users') > 0) {
            $errors[] = 'هذا البريد مسجل لحساب آخر. ابحث عنه في «كل الحسابات».';
        }

        /* الفراغ يعني «لا تمس»، وما كتب يفحص بشروط الإنشاء نفسها. */
        if ($pass !== '') {
            if (mb_strlen($pass) < 8)        $errors[] = 'كلمة المرور ثمانية محارف على الأقل.';
            elseif (strlen($pass) > 72)      $errors[] = 'كلمة المرور أطول من اللازم. اجعلها دون اثنتين وسبعين خانة.';
            elseif ($pass !== $conf)         $errors[] = 'كلمتا المرور غير متطابقتين.';
        }

        $e164 = '';
        if ($phone !== '') {
            $ph = tq_phone_check($phone, (string) $in->post('phone_cc'));
            if (!$ph['ok']) $errors[] = $ph['error'];
            else            $e164 = $ph['e164'];
        }

        if (mb_strlen($title) > 160) $errors[] = 'الصفة أطول من المسموح (160 حرفا).';
        if (mb_strlen($skill) > 255) $errors[] = 'قائمة المواد أطول من المسموح (255 حرفا).';
        if (mb_strlen($bio) > 1500)  $errors[] = 'النبذة أطول من المسموح (1500 حرف).';

        if ($errors) {
            return array('ok' => false, 'errors' => $errors, 'user_id' => $user_id, 'message' => '');
        }

        $data = array(
            'first_name'    => $first,
            'last_name'     => $last,
            'email'         => $email,
            'phone'         => $e164,
            'title'         => strip_tags($title),
            'skills'        => strip_tags($skill),
            'biography'     => strip_tags($bio),
            'is_public'     => ((string) $in->post('is_public') === '1') ? 1 : 0,
            'status'        => ((string) $in->post('status') === '0') ? 0 : 1,
            'last_modified' => time(),
        );
        if ($pass !== '') $data['password'] = tq_password_hash($pass);

        $this->db->where('id', $user_id)->update('users', $data);

        $img = $this->store_avatar($user_id);
        if ($img !== '') {
            $this->db->where('id', $user_id)->update('users', array('image' => $img));
        }

        /* الأثر يحمل ما تغير لا الصف كله — وكلمة المرور لا تكتب في السجل
           لا قبلها ولا بعدها، إنما يقال إنها بدلت. */
        $this->audit('teacher_updated', 'users#' . $user_id, array(
            'email'     => (string) $row['email'],
            'name'      => trim($row['first_name'] . ' ' . $row['last_name']),
            'status'    => (int) $row['status'],
            'is_public' => (int) $row['is_public'],
        ), array(
            'email'     => $email,
            'name'      => trim($first . ' ' . $last),
            'status'    => (int) $data['status'],
            'is_public' => (int) $data['is_public'],
            'password'  => ($pass !== '' ? 'changed' : 'kept'),
        ));

        return array('ok' => true, 'errors' => array(), 'user_id' => $user_id,
                     'message' => 'حفظت بيانات المعلم.'
                                . ($pass !== '' ? ' وبدلت كلمة مروره — سلمها إليه.' : ''));
    }

    /* =====================================================================
       TQ-TEACHER-DELETE — معلم درس لا يحذف، يغلق

       المبدأ مبدأ TQ-PLAN-DELETE نفسه: `course.creator` و`revenue_shares`
       و`wallet_entries` و`tutoring_sessions` تشير إلى المعلم **بمعرفه**،
       وحذف الصف يترك سجلا لا يقرأ — كشف حساب يقول «معلم #407» ولا يعرف
       أحد من كان، وكورس بلا صاحب في «المواد والبرامج»، وطالب يفتح درسا
       معلمه محذوف. والضرر كله في القراءة وهو لا يرجع.

       فالحذف مقصور على **حساب لم يبدأ**: بلا كورس ولا حصة ولا قيد مال
       ولا طلب انضمام. وما سواه يرد برسالته — و**الإغلاق هو ما يريده** من
       ضغط «احذف»: يمنع الدخول ويخفي الحساب، ولا يفقد شيئا.
       ===================================================================== */

    /** ما يمنع حذف هذا المعلم — قائمة أسباب، فارغة إن كان الحساب نظيفا. */
    public function teacher_delete_blockers($user_id)
    {
        $s = $this->teacher_stats($user_id);
        $out = array();

        if ($s['courses'] > 0)   $out[] = 'له ' . $s['courses'] . ' كورس' . ($s['courses'] > 1 ? 'ا' : '');
        if ($s['own_paths'] > 0) $out[] = 'أسند إليه ' . $s['own_paths'] . ' برنامج';
        if ($s['sessions'] > 0) $out[] = 'له ' . $s['sessions'] . ' حصة خاصة';
        if ($s['shares'] > 0)   $out[] = 'له ' . $s['shares'] . ' قيد قسمة إيراد';
        if ($s['entries'] > 0)  $out[] = 'في محفظته ' . $s['entries'] . ' حركة';
        if ($s['payouts'] > 0)  $out[] = 'له ' . $s['payouts'] . ' طلب سحب';

        return $out;
    }

    /**
     * يحذف حساب معلم لم يبدأ — وما سواه يرد.
     *
     * والتوابع تنظف قبل الصف: `wallets` الصفرية و`applications`
     * و`notifications` و`permissions`. وهي التي لا معنى لها بلا صاحبها،
     * ولا يشير إليها مال ولا محتوى.
     */
    public function delete_teacher($user_id)
    {
        $user_id = (int) $user_id;
        $row     = $this->teacher_row($user_id);
        if (!$row) {
            return array('ok' => false, 'message' => 'هذا الحساب ليس حساب معلم، أو لم يعد موجودا.');
        }
        if ($user_id === $this->tq_actor_id()) {
            return array('ok' => false, 'message' => 'لا تحذف حسابك أنت.');
        }

        $blockers = $this->teacher_delete_blockers($user_id);
        if ($blockers) {
            return array('ok' => false, 'message' =>
                'لا يحذف هذا الحساب: ' . implode(' · ', $blockers) . '. '
              . 'وحذفه يترك سجلا ماليا ومحتوى بلا صاحب يقرأ. أغلق الحساب بدلا من ذلك — '
              . 'يمنع الدخول ولا يفقد شيئا.');
        }

        $name  = trim($row['first_name'] . ' ' . $row['last_name']);
        $email = (string) $row['email'];

        try {
            $this->db->where('owner_user_id', $user_id)->delete('wallets');
            $this->db->where('user_id', $user_id)->delete('applications');
            $this->db->where('to_user', $user_id)->or_where('from_user', $user_id)->delete('notifications');
            $this->db->where('admin_id', $user_id)->delete('permissions');
            $this->db->where('id', $user_id)->delete('users');
        } catch (Throwable $e) {
            /* TQ-BUILDER-DIRTY — الاستثناء يترك بناء الاستعلام نظيفا خلفه. */
            $this->db->reset_query();
            log_message('error', 'TQ-TEACHER-DELETE: ' . $e->getMessage());
            return array('ok' => false, 'message' => 'تعذر حذف الحساب. أغلقه بدلا من ذلك، وراجع السجل.');
        }

        /* الصورة تحذف بنسختيها بعد الصف: ملف يبقى لصاحب ذهب لا يقرؤه
           أحد، ومقصور على `uploads/user_image/` فلا يمس أصلا من السمة. */
        $code = trim((string) $row['image']);
        if ($code !== '' && strpos($code, '/') === false) {
            $dir = rtrim(FCPATH, '/') . '/uploads/user_image/';
            @unlink($dir . $code . '.jpg');
            @unlink($dir . 'optimized/' . $code . '.jpg');
        }

        $this->audit('teacher_deleted', 'users#' . $user_id,
                     array('email' => $email, 'name' => $name), null);

        return array('ok' => true, 'message' => 'حذف حساب «' . $name . '» وما لا معنى له بلا صاحبه.');
    }

    /** يختم الحساب مؤكدا، ويصمت إن كان العمود لم ينشأ بعد. */
    private function stamp_verified($user_id)
    {
        try {
            $CI = get_instance();
            $CI->load->model('taqdar_otp_model');
            $CI->taqdar_otp_model->ensure_schema();
            $this->db->where('id', (int) $user_id)
                     ->update('users', array('tq_verified_at' => date('Y-m-d H:i:s')));
        } catch (Throwable $e) {
            /* TQ-BUILDER-DIRTY — الاستثناء يترك بناء الاستعلام نظيفا خلفه. */
            $this->db->reset_query();
            log_message('error', 'TQ-TEACHER-ADD: تعذر ختم التأكيد — ' . $e->getMessage());
        }
    }

    /**
     * صورة الحساب — بمعالج بوابة المعلم نفسه لا بنسخة ثانية منه.
     *
     * `Taqdar_settings_model::store_image()` هو الذي يفحص المحتوى ويعيد
     * الترميز ويكتب في `uploads/user_image/`؛ وشاشة اللوحة ترفع إلى
     * المجلد نفسه، فالفحص واحد ولا يفترق عند أول تشديد.
     *
     * وفشل الصورة لا يبطل الحساب: الصف كتب، ورد الشاشة كلها بخطأ صورة
     * بعده يترك المسؤول يظن أنه لم ينشأ فيعيد الإنشاء ببريد مكرر.
     */
    private function store_avatar($user_id)
    {
        if (empty($_FILES['user_image']['name'])) return '';

        $CI = get_instance();
        $CI->load->model('taqdar_settings_model');
        $r = $CI->taqdar_settings_model->store_image((int) $user_id);

        if (empty($r['ok'])) {
            log_message('error', 'TQ-TEACHER-ADD: صورة لم ترفع — ' . (string) $r['error']);
            return '';
        }
        return (string) $r['code'];
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
