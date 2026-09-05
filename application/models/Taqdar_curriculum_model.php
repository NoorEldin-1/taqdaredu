<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * طبقة المنهج — الأقسام والدروس، تكتب من موضع واحد.
 *
 * السبب في وجودها أن **الإدارة والمعلم يفعلان الشيء نفسه بالضبط**، وكل
 * محاولة لكتابة ذلك مرتين تنتهي إلى شاشتين تفترقان: المعلم يقبل ما ترفضه
 * الإدارة، أو ينشأ نوع درس في اللوحة ولا يوجد في البوابة. فالقواعد هنا،
 * والشاشتان تعرضان ولا تحكمان.
 *
 * وموضعها بين شيئين قائمين:
 *
 *   · `Crud_model::add_lesson()` الموروث — ٤٠٠ سطر من `if/elseif` تكتب
 *     `flashdata` وترد `json_encode(['reload'=>true])`، أي أنها تعرف
 *     شاشتها. وهي تبقى تخدم مسارات Academy كما هي.
 *   · `Taqdar_teacher_model::save_lesson()` — تعرف المعلم وحده وتقبل
 *     نوعين من عشرة.
 *
 * وهذه تعرف الاثنين ولا تعرف شاشة: تأخذ فاعلا ومصفوفة، وترد
 * `['ok'=>bool, 'errors'=>[], ...]` — ومن ناداها يقرر ماذا يعرض.
 *
 * ----------------------------------------------------------------------
 * الوحدة الموصوفة: `lesson_types()`
 *
 * كل نوع درس يصف نفسه — تسميته وحقوله وأين يكتب كل حقل من الصف، وكيف
 * يتتبع تقدمه. فالنوع الجديد يضاف **هنا وحده** فيظهر في شاشتي الإدارة
 * والمعلم معا، ويحفظ ويتحقق ويعرض للطالب بلا قالب يكتب.
 *
 * وهو المبدأ نفسه الذي يبني به `Taqdar_admin_model::spec()` ست عشرة
 * شاشة إدارية.
 * ----------------------------------------------------------------------
 */
class Taqdar_curriculum_model extends CI_Model
{
    /**
     * إصدار المخطط.
     *
     * يرفع مع كل عمود يضاف. ومن يقرأ عمودا قبل ترقية الإصدار يقرأ عمودا
     * غير موجود — انظر `Taqdar_wallet_model::install_schema()`.
     */
    const SCHEMA_VERSION = 1;

    /** أطول عنوان درس أو قسم يقبل. */
    const MAX_TITLE = 190;

    /** حالات الدرس. `rejected` جديدة: المرفوض يعود إلى صاحبه بسببه. */
    private $statuses = array('draft', 'review', 'published', 'rejected');

    private $schema_done = false;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /* =====================================================================
       المخطط
       ===================================================================== */

    /**
     * ينشئ ما تحتاجه هذه الطبقة، ولا يمس ما كان.
     *
     * ثلاثة أشياء:
     *
     * ١ — `lesson.duration_sec` — **المدة رقما**. والعمود القائم
     *     `duration` نص `HH:MM:SS` يقرأ بـ`duration_seconds()` في كل
     *     مرة، وقيمته في كل درس عندنا اليوم `00:00:00` — أي أن
     *     `completed_at` لا يكتب أبدا مهما شاهد الطالب. والرقم يكتبه
     *     المشغل حين يكتشف المدة الحقيقية من مصدرها.
     *     و`duration` النصي يبقى ويكتب معه: شاشات Academy تقرؤه.
     *
     * ٢ — `lesson.tq_review_note` و`tq_reviewed_at` و`tq_reviewed_by` —
     *     سبب الرفض ومن رفض ومتى. وبلا السبب يعود الدرس إلى صاحبه
     *     بكلمة «مرفوض» وحدها، فيعيد إرساله كما هو.
     *
     * ٣ — `tq_content_revisions` — التعديل المقترح على محتوى **منشور**.
     *     والسبب أن إخفاء درس منشور لأن معلمه صحح خطأ إملائيا يعاقب
     *     الطالب الذي دفع. فالصف الحي لا يمس، والتعديل ينتظر بجواره.
     */
    public function install_schema()
    {
        if ($this->schema_done) return;
        $this->schema_done = true;

        try {
            /* الخبيئة تفرغ قبل الفحص: CodeIgniter يحفظ أسماء أعمدة كل
               جدول في الطلب الواحد، فمن قرأ الجدول قبل هذا السطر يعطي
               قائمة بائتة، فيعاد `ADD COLUMN` على عمود قائم — وفي
               البيئة المحلية `db_debug` مفتوح، فذلك صفحة بيضاء. */
            $this->db->data_cache = array();

            $cols = array(
                'duration_sec'   => "int(11) NOT NULL DEFAULT 0",
                'tq_review_note' => "varchar(500) DEFAULT NULL",
                'tq_reviewed_at' => "datetime DEFAULT NULL",
                'tq_reviewed_by' => "int(11) DEFAULT NULL",
            );
            foreach ($cols as $col => $ddl) {
                if (!$this->db->field_exists($col, 'lesson')) {
                    $this->db->query('ALTER TABLE `lesson` ADD COLUMN `' . $col . '` ' . $ddl);
                }
            }

            /* المدة الرقمية تملأ مرة من النصية القائمة: بلا ذلك يبدأ كل
               درس قديم بصفر، فيقرأ الطالب «٪٠» على درس أتمه. */
            $this->db->query(
                'UPDATE `lesson`
                    SET `duration_sec` = COALESCE(TIME_TO_SEC(NULLIF(`duration`, "")), 0)
                  WHERE `duration_sec` = 0 AND `duration` IS NOT NULL AND `duration` <> ""'
            );

            /**
             * المراجعة — صف لكل تغيير ينتظر قرارا.
             *
             * `payload` يحمل الصف المقترح كاملا JSON، لا فرقا: الفرق
             * يحتاج الأصل ليطبق، والأصل قد يتغير بين الاقتراح والقرار.
             * والحمولة كاملة تطبق كما هي مهما تأخر القرار.
             *
             * و`uq_pending` يمنع صفين معلقين لعنصر واحد: المعلم يعدل
             * مرتين قبل أن يرد أحد، فيبقى الاقتراح واحدا هو الأحدث —
             * وإلا اعتمد المسؤول القديم وهو يظنه الجديد.
             */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_content_revisions` (
                    `id`           int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `entity`       varchar(20)  NOT NULL COMMENT "lesson | section",
                    `entity_id`    int(10) unsigned NOT NULL,
                    `course_id`    int(10) unsigned NOT NULL DEFAULT 0,
                    `kind`         varchar(20)  NOT NULL DEFAULT "update" COMMENT "create | update | delete",
                    `payload`      longtext     DEFAULT NULL COMMENT "JSON: الصف المقترح",
                    `note`         varchar(500) DEFAULT NULL COMMENT "ما يقوله المعلم للمسؤول",
                    `status`       varchar(20)  NOT NULL DEFAULT "pending",
                    `decided_note` varchar(500) DEFAULT NULL COMMENT "سبب الرفض",
                    `requested_by` int(10) unsigned NOT NULL DEFAULT 0,
                    `requested_at` datetime     DEFAULT NULL,
                    `decided_by`   int(10) unsigned NOT NULL DEFAULT 0,
                    `decided_at`   datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_pending` (`entity`, `entity_id`, `status`),
                    KEY `ix_rev_status` (`status`, `requested_at`),
                    KEY `ix_rev_course` (`course_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            /* الفشل يسجل ولا يوقف: بلا هذه الأعمدة تعمل الشاشات كما كانت
               تعمل قبلها، وتعطيل تحرير المنهج كله لأجل عمود أسوأ. */
            log_message('error', 'TQ-CURRIC: تعذر تركيب المخطط — ' . $e->getMessage());
        }
    }

    /* =====================================================================
       الوحدة الموصوفة — أنواع الدروس
       ===================================================================== */

    /**
     * أنواع الدروس العشرة، كل نوع بحقوله وبموضع كل حقل من الصف.
     *
     * مفاتيح النوع:
     *   label · hint · icon   — ما يقرؤه من يختار
     *   row                   — ما يكتب ثابتا في الصف (الأعمدة الثلاثة
     *                           التي يفرق بها Academy بين الأنواع)
     *   track                 — كيف يقاس تقدم الطالب فيه:
     *                             api    مشغل من طرف آخر له واجهة برمجة
     *                             native عنصر وسائط في صفحتنا
     *                             none   لا موضع تشغيل يقرأ
     *   timed                 — هل له مدة أصلا (الصورة والنص لا مدة لهما)
     *   fields                — الحقول، ولكل حقل:
     *                             kind     url|duration|file|richtext|textarea
     *                             col      عمود الصف الذي يكتب فيه
     *                             required · accept · placeholder · hint
     *
     * والترتيب هو ترتيب العرض: الأشيع أولا.
     */
    public static function lesson_types()
    {
        return tq_t_deep(array(

            'youtube' => array(
                'label' => 'فيديو يوتيوب',
                'hint'  => 'الصق رابط الفيديو — تقرأ مدته تلقائيا.',
                'icon'  => 'play',
                'row'   => array('lesson_type' => 'video', 'attachment_type' => 'url', 'video_type' => 'youtube'),
                'track' => 'api',
                'timed' => true,
                'fields' => array(
                    'video_url' => array('kind' => 'url', 'col' => 'video_url', 'label' => 'رابط الفيديو',
                                         'required' => true, 'probe' => 'youtube',
                                         'placeholder' => 'https://www.youtube.com/watch?v=...'),
                    'duration'  => array('kind' => 'duration', 'col' => 'duration', 'label' => 'المدة',
                                         'hint' => 'تقرأ تلقائيا من الرابط، وتكتب بيد إن تعذر.'),
                    'caption'   => array('kind' => 'file', 'col' => 'caption', 'label' => 'ملف الترجمة',
                                         'accept' => '.vtt', 'dir' => 'uploads/captions',
                                         'hint' => 'صيغة WebVTT. ويوتيوب يعرض ترجمته هو أيضا.'),
                ),
            ),

            'vimeo' => array(
                'label' => 'فيديو فيميو',
                'hint'  => 'الصق رابط الفيديو — تقرأ مدته تلقائيا.',
                'icon'  => 'play',
                'row'   => array('lesson_type' => 'video', 'attachment_type' => 'url', 'video_type' => 'vimeo'),
                'track' => 'api',
                'timed' => true,
                'fields' => array(
                    'video_url' => array('kind' => 'url', 'col' => 'video_url', 'label' => 'رابط الفيديو',
                                         'required' => true, 'probe' => 'vimeo',
                                         'placeholder' => 'https://vimeo.com/...'),
                    'duration'  => array('kind' => 'duration', 'col' => 'duration', 'label' => 'المدة',
                                         'hint' => 'تقرأ تلقائيا من الرابط، وتكتب بيد إن تعذر.'),
                    'caption'   => array('kind' => 'file', 'col' => 'caption', 'label' => 'ملف الترجمة',
                                         'accept' => '.vtt', 'dir' => 'uploads/captions'),
                ),
            ),

            'upload_video' => array(
                'label' => 'ملف فيديو',
                'hint'  => 'يرفع إلى الخادم. الأثقل تخزينا والأضمن بقاء — ورابطه يوقع فلا ينسخ.',
                'icon'  => 'upload',
                'row'   => array('lesson_type' => 'video', 'attachment_type' => 'file', 'video_type' => 'system'),
                'track' => 'native',
                'timed' => true,
                'fields' => array(
                    'video_file' => array('kind' => 'file', 'col' => 'video_url', 'label' => 'ملف الفيديو',
                                          'required' => true, 'accept' => 'video/*', 'probe' => 'file',
                                          'dir' => 'uploads/lesson_files/videos', 'as_url' => true,
                                          'ext' => array('mp4', 'webm', 'ogg', 'ogv', 'm4v', 'mov')),
                    'duration'   => array('kind' => 'duration', 'col' => 'duration', 'label' => 'المدة',
                                          'hint' => 'تقرأ من الملف عند اختياره.'),
                    'caption'    => array('kind' => 'file', 'col' => 'caption', 'label' => 'ملف الترجمة',
                                          'accept' => '.vtt', 'dir' => 'uploads/captions'),
                ),
            ),

            'html5' => array(
                'label' => 'رابط ملف مباشر',
                'hint'  => 'رابط ينتهي بـ mp4. على خادم آخر.',
                'icon'  => 'link',
                'row'   => array('lesson_type' => 'video', 'attachment_type' => 'url', 'video_type' => 'html5'),
                'track' => 'native',
                'timed' => true,
                'fields' => array(
                    'video_url' => array('kind' => 'url', 'col' => 'video_url', 'label' => 'رابط الملف',
                                         'required' => true, 'probe' => 'html5',
                                         'placeholder' => 'https://…/video.mp4'),
                    'duration'  => array('kind' => 'duration', 'col' => 'duration', 'label' => 'المدة',
                                         'hint' => 'تقرأ تلقائيا من الملف، وتكتب بيد إن تعذر.'),
                ),
            ),

            'google_drive' => array(
                'label' => 'فيديو جوجل درايف',
                'hint'  => 'يعرض في إطار درايف — والمنصة لا تقرأ موضع تشغيله.',
                'icon'  => 'folder',
                'row'   => array('lesson_type' => 'video', 'attachment_type' => 'url', 'video_type' => 'google_drive'),
                'track' => 'none',
                'timed' => true,
                'fields' => array(
                    'video_url' => array('kind' => 'url', 'col' => 'video_url', 'label' => 'رابط الملف على درايف',
                                         'required' => true,
                                         'placeholder' => 'https://drive.google.com/file/d/...'),
                    'duration'  => array('kind' => 'duration', 'col' => 'duration', 'label' => 'المدة',
                                         'required' => true,
                                         'hint' => 'اكتبها بيدك: درايف لا يعلن مدة الفيديو، وبلا مدة لا يقاس تقدم الطالب.'),
                ),
            ),

            'upload_audio' => array(
                'label' => 'ملف صوتي',
                'hint'  => 'درس بلا صورة — للتلاوة والاستماع.',
                'icon'  => 'video',
                'row'   => array('lesson_type' => 'audio', 'attachment_type' => 'audio', 'video_type' => ''),
                'track' => 'native',
                'timed' => true,
                'fields' => array(
                    'audio_file' => array('kind' => 'file', 'col' => 'audio_url', 'label' => 'الملف الصوتي',
                                          'required' => true, 'accept' => 'audio/*', 'probe' => 'file',
                                          'dir' => 'uploads/lesson_files/audios', 'as_url' => true,
                                          'ext' => array('mp3', 'm4a', 'wav', 'ogg', 'oga', 'aac')),
                    'duration'   => array('kind' => 'duration', 'col' => 'duration', 'label' => 'المدة',
                                          'hint' => 'تقرأ من الملف عند اختياره.'),
                ),
            ),

            'document' => array(
                'label' => 'مستند',
                'hint'  => 'PDF أو Word يفتح داخل المشغل.',
                'icon'  => 'file',
                'row'   => array('lesson_type' => 'other', 'attachment_type' => 'pdf', 'video_type' => ''),
                'track' => 'none',
                'timed' => false,
                'fields' => array(
                    'attachment' => array('kind' => 'file', 'col' => 'attachment', 'label' => 'الملف',
                                          'required' => true, 'accept' => '.pdf,.doc,.docx,.txt',
                                          'dir' => 'uploads/lesson_files',
                                          'ext' => array('pdf', 'doc', 'docx', 'txt'),
                                          'sets' => 'attachment_type'),
                ),
            ),

            'image' => array(
                'label' => 'صورة',
                'hint'  => 'لوحة أو مخطط يعرض كاملا.',
                'icon'  => 'image',
                'row'   => array('lesson_type' => 'other', 'attachment_type' => 'img', 'video_type' => ''),
                'track' => 'none',
                'timed' => false,
                'fields' => array(
                    'attachment' => array('kind' => 'file', 'col' => 'attachment', 'label' => 'الصورة',
                                          'required' => true, 'accept' => 'image/*',
                                          'dir' => 'uploads/lesson_files',
                                          'ext' => array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg')),
                ),
            ),

            'text' => array(
                'label' => 'نص',
                'hint'  => 'درس مكتوب بلا وسائط.',
                'icon'  => 'file-text',
                'row'   => array('lesson_type' => 'text', 'attachment_type' => 'description', 'video_type' => ''),
                'track' => 'none',
                'timed' => false,
                'fields' => array(
                    'attachment' => array('kind' => 'richtext', 'col' => 'attachment', 'label' => 'نص الدرس',
                                          'required' => true, 'min' => 10),
                ),
            ),

            'iframe' => array(
                'label' => 'تضمين خارجي',
                'hint'  => 'أداة تفاعلية من موقع آخر — والمنصة لا تقرأ ما يجري داخلها.',
                'icon'  => 'globe',
                'row'   => array('lesson_type' => 'other', 'attachment_type' => 'iframe', 'video_type' => ''),
                'track' => 'none',
                'timed' => false,
                'fields' => array(
                    'attachment' => array('kind' => 'textarea', 'col' => 'attachment', 'label' => 'وسم التضمين',
                                          'required' => true, 'ltr' => true,
                                          'hint' => 'الصق وسم <iframe> كما يعطيه الموقع، أو رابطه وحده.'),
                ),
            ),
        ));
    }

    /** نوع بمفتاحه، أو null. */
    public static function lesson_type($key)
    {
        $all = self::lesson_types();
        return isset($all[$key]) ? $all[$key] : null;
    }

    /**
     * يستنتج مفتاح النوع من صف درس محفوظ.
     *
     * الصف يحمل ثلاثة أعمدة (`lesson_type` · `attachment_type` ·
     * `video_type`) ولا يحمل المفتاح. والاستنتاج هنا لا في القالب: قالب
     * يستنتجه بنفسه يفترق عن أخيه عند أول نوع يضاف.
     */
    public static function kind_of($lesson)
    {
        $lt = strtolower((string) (isset($lesson['lesson_type']) ? $lesson['lesson_type'] : ''));
        $at = strtolower((string) (isset($lesson['attachment_type']) ? $lesson['attachment_type'] : ''));
        $vt = strtolower((string) (isset($lesson['video_type']) ? $lesson['video_type'] : ''));

        if ($lt === 'video') {
            if ($at === 'file' || $vt === 'system') return 'upload_video';
            switch ($vt) {
                case 'youtube':      return 'youtube';
                case 'vimeo':        return 'vimeo';
                case 'google_drive': return 'google_drive';
                case 'html5':        return 'html5';
            }
            return 'html5';
        }
        if ($lt === 'audio')  return 'upload_audio';
        if ($lt === 'text')   return 'text';
        if ($lt === 'quiz')   return 'quiz';         // الموروث: اختبار كدرس
        if ($at === 'img')    return 'image';
        if ($at === 'iframe') return 'iframe';
        if (in_array($at, array('pdf', 'doc', 'docx', 'txt'), true)) return 'document';

        return 'html5';
    }

    /* =====================================================================
       الفاعل والملكية
       ===================================================================== */

    /**
     * من يكتب الآن — ودوره.
     *
     * الإدارة تعرف بجلستها (`admin_login`) لا بدورها في `users`: الأدمن
     * الذي يفتح بوابة المعلم لا يصير معلما.
     */
    public function actor()
    {
        $uid = (int) $this->session->userdata('user_id');
        if ($this->session->userdata('admin_login') === true) {
            return array('id' => $uid, 'role' => 'admin');
        }
        if ($this->session->userdata('teacher_login') === true
            || (string) $this->session->userdata('role') === 'teacher') {
            return array('id' => $uid, 'role' => 'teacher');
        }
        return array('id' => $uid, 'role' => 'guest');
    }

    /** فاعل بدور صريح — لمن ناداها من متحكم يعرف دوره. */
    public function actor_as($role, $user_id)
    {
        return array('id' => (int) $user_id, 'role' => (string) $role);
    }

    /**
     * هل يكتب هذا الفاعل في هذا الكورس؟
     *
     * الإدارة تمر بلا شرط. والمعلم يمر بشرط الملكية — و`owns_course()`
     * في `Taqdar_teacher_model` هي مصدر ذلك القرار، فلا يكتب هنا شرط
     * ثان يفترق عنه.
     */
    public function may_edit_course($actor, $course_id)
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) return false;

        $role = isset($actor['role']) ? $actor['role'] : 'guest';
        if ($role === 'admin') return true;
        if ($role !== 'teacher') return false;

        $this->load->model('taqdar_teacher_model');
        return (bool) $this->taqdar_teacher_model->owns_course((int) $actor['id'], $course_id);
    }

    /** هل يكتب في هذا الدرس؟ السؤال يرد إلى كورسه. */
    public function may_edit_lesson($actor, $lesson_id)
    {
        $cid = (int) $this->db->select('course_id')->where('id', (int) $lesson_id)
                              ->get('lesson')->row('course_id');
        return $cid > 0 && $this->may_edit_course($actor, $cid);
    }

    /** هل يكتب في هذا القسم؟ */
    public function may_edit_section($actor, $section_id)
    {
        $cid = (int) $this->db->select('course_id')->where('id', (int) $section_id)
                              ->get('section')->row('course_id');
        return $cid > 0 && $this->may_edit_course($actor, $cid);
    }

    /**
     * هل ينشر هذا الفاعل مباشرة؟
     *
     * الإدارة نعم. والمعلم لا — إلا أن يفتح المسؤول `tq_teacher_direct_publish`.
     * والافتراض مغلق: طلب المنتج أن يمر كل ما يرفعه المعلم بالمراجعة.
     */
    public function may_publish($actor)
    {
        if ((isset($actor['role']) ? $actor['role'] : '') === 'admin') return true;

        /* عمود المفتاح اسمه `key` وهو **كلمة محجوزة في MySQL**، فيلزمه
           التنصيص العكسي. و`get_settings()` هي التي تعرف ذلك، فتنادى
           بدل كتابة الاستعلام هنا — نسخة ثانية منه تنكسر متى تغير
           المخطط، وهذا ما وقع. */
        return (string) get_settings('tq_teacher_direct_publish') === '1';
    }

    /* =====================================================================
       القراءة
       ===================================================================== */

    /* =====================================================================
       الكورس نفسه — حقوله ومن يملك كلا منها
       =====================================================================

       TQ-COURSE-SPLIT — لوحتان تحرران كورسا واحدا.

       اللوحة تحرره من `admin/course_form/course_edit` بتسعة تبويبات،
       والمعلم كان يحرره من... لا شيء. نموذج إنشاء بأربعة حقول (عنوان
       ومستوى ووصفين) وحسب — بلا شاشة تعديل أصلا، وبلا **الصف والمادة**.

       والحقلان الأخيران ليسا زينة: الكتالوج ومحرك الاشتراكات لا يقرآن
       جدول `course` في سطر واحد، بل `paths` وحده (انظر
       [Taqdar_course_link_model.php]). فكل كورس أنشأه معلم منذ اليوم
       الأول **ولد محجوبا**: لا يظهر في «المواد والبرامج»، ولا تفتحه
       باقة، ولا يصل إليه طالب — ولا شيء في شاشته يقول لماذا.

       فالحقول توصف هنا مرة واحدة، بالمبدأ نفسه الذي وصفت به
       `lesson_types()` أنواع الدروس: **الوصف واحد والشاشات تعرض**.
       ولكل حقل صاحبه:

         any    — يملكه من يحرر الكورس، معلما كان أو مسؤولا
         admin  — قرار عمل لا قرار محتوى: السعر، و«كورس مميز»،
                  وتحسين البحث، وتاريخ النشر.

       والقسمة ليست حجرا على المعلم: من يضع سعر كورسه بنفسه يضع سعر
       المنصة، ومن يضع كورسه في شريط «الأبرز» بنفسه يضعه أمام كورسات
       زملائه. وما سوى ذلك محتوى، والمحتوى لصاحبه.
    */

    /** حالات الكورس ووصف كل منها — مصدر واحد للقائمتين. */
    public static function course_statuses()
    {
        return tq_t_deep(array(
            'active'   => array('منشور',        'يظهر في الموقع العام ويمكن الاشتراك فيه.'),
            'private'  => array('خاص',          'لا يظهر في القوائم — يفتح برابطه وحده.'),
            'upcoming' => array('قادم',         'يعرض بتاريخ نشر ولا يفتح قبله.'),
            'pending'  => array('قيد المراجعة', 'أرسله معلم وينتظر قرار الإدارة.'),
            'draft'    => array('مسودة',        'غير مكتمل ولا يعرض لأحد.'),
        ));
    }

    /**
     * حقول الكورس، وما يراه هذا الفاعل منها.
     *
     * `col` عمود القاعدة، و`kind` شكل الحقل، و`owner` من يملكه.
     * و`tq_grade_id`/`tq_subject_id` وحدهما ليسا عمودين في `course`:
     * يكتبان في `paths` عبر `Taqdar_course_link_model::sync()` —
     * ولذلك `col` فيهما `null`.
     */
    public function course_fields($actor = null)
    {
        $admin = (isset($actor['role']) ? $actor['role'] : '') === 'admin';

        $f = array(
            'title' => array('col' => 'title', 'kind' => 'text', 'owner' => 'any',
                'label' => 'عنوان الكورس', 'required' => true, 'max' => 190, 'full' => true,
                'section' => 'أساسيات الكورس'),

            'sub_category_id' => array('col' => 'sub_category_id', 'kind' => 'category', 'owner' => 'any',
                'label' => 'المرحلة',
                'hint' => 'بها يبوب الكورس في «المواد والبرامج».'),

            'level' => array('col' => 'level', 'kind' => 'enum', 'owner' => 'any',
                'label' => 'المستوى', 'default' => 'beginner',
                'options' => array('beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم')),

            'language_made_in' => array('col' => 'language', 'kind' => 'language', 'owner' => 'any',
                'label' => 'لغة المحتوى'),

            'short_description' => array('col' => 'short_description', 'kind' => 'text', 'owner' => 'any',
                'label' => 'وصف مختصر', 'max' => 255, 'full' => true,
                'hint' => 'سطر واحد يظهر تحت العنوان في بطاقة الكورس.'),

            'description' => array('col' => 'description', 'kind' => 'richtext', 'owner' => 'any',
                'label' => 'الوصف الكامل', 'full' => true),

            'status' => array('col' => 'status', 'kind' => 'status', 'owner' => 'any',
                'label' => 'حالة الكورس', 'default' => 'pending', 'full' => true,
                'section' => 'النشر والإتاحة'),

            'publish_date' => array('col' => 'publish_date', 'kind' => 'datetime', 'owner' => 'admin',
                'label' => 'تاريخ النشر'),

            'is_top_course' => array('col' => 'is_top_course', 'kind' => 'bool', 'owner' => 'admin',
                'label' => 'كورس مميز', 'full' => true,
                'hint' => 'يعرض في شريط «الأبرز» في الصفحة الرئيسية.'),

            'enable_drip_content' => array('col' => 'enable_drip_content', 'kind' => 'bool', 'owner' => 'any',
                'label' => 'إتاحة الدروس تدريجيا', 'full' => true,
                'hint' => 'الدرس لا يفتح إلا بعد سابقه.'),

            'tq_grade_id' => array('col' => null, 'kind' => 'ref', 'owner' => 'any', 'table' => 'grades',
                'label' => 'الصف الدراسي', 'empty' => '— بلا صف',
                'section' => 'الصف والمادة'),

            'tq_subject_id' => array('col' => null, 'kind' => 'ref', 'owner' => 'any', 'table' => 'subjects',
                'label' => 'المادة', 'empty' => '— بلا مادة'),

            'thumbnail' => array('col' => 'thumbnail', 'kind' => 'image', 'owner' => 'any',
                'label' => 'صورة الكورس', 'dir' => 'uploads/thumbnails/course_thumbnails',
                'accept' => 'image/*', 'section' => 'الصورة والفيديو',
                'hint' => 'المقاس المفضل 700 × 430.'),

            'course_overview_url' => array('col' => 'video_url', 'kind' => 'url', 'owner' => 'any',
                'label' => 'فيديو تعريفي', 'ltr' => true, 'full' => true,
                'hint' => 'مقطع قصير يعرض في صفحة الكورس قبل الاشتراك.'),

            'requirements' => array('col' => 'requirements', 'kind' => 'lines', 'owner' => 'any',
                'label' => 'المتطلبات السابقة', 'full' => true, 'section' => 'ما يعرض في صفحته',
                'hint' => 'بند في كل سطر.'),

            'outcomes' => array('col' => 'outcomes', 'kind' => 'lines', 'owner' => 'any',
                'label' => 'ماذا سيتعلم الطالب', 'full' => true, 'hint' => 'بند في كل سطر.'),

            'price' => array('col' => 'price', 'kind' => 'money', 'owner' => 'admin',
                'label' => 'السعر', 'section' => 'التسعير'),

            'meta_keywords' => array('col' => 'meta_keywords', 'kind' => 'text', 'owner' => 'admin',
                'label' => 'كلمات البحث', 'full' => true, 'section' => 'تحسين البحث'),

            'meta_description' => array('col' => 'meta_description', 'kind' => 'textarea', 'owner' => 'admin',
                'label' => 'وصف محرك البحث', 'full' => true),
        );

        /* TQ-I18N — الوصف يترجم عند الخروج (كما في `Taqdar_admin_model::spec()`).
           و`col` و`kind` و`owner` رموز لا نص، فلا تجد مدخلا في قاموس
           مفاتيحه عربية وتمر كما هي. */
        if ($admin) return tq_t_deep($f);

        foreach ($f as $k => $d) {
            if ($d['owner'] === 'admin') unset($f[$k]);
        }
        return tq_t_deep($f);
    }

    /** صف الكورس مع صفه ومادته — القراءة التي تغذي شاشة التحرير. */
    public function course($id)
    {
        $id  = (int) $id;
        $row = $this->db->where('id', $id)->get('course')->row_array();
        if (!$row) return null;

        $CI = get_instance();
        $CI->load->model('taqdar_course_link_model', 'tq_link_m');
        $link = $CI->tq_link_m->link_of($id);

        $row['tq_grade_id']   = (int) $link['grade_id'];
        $row['tq_subject_id'] = (int) $link['subject_id'];
        return $row;
    }

    /**
     * يحفظ حقول الكورس — من اللوحة أو من بوابة المعلم، بالقواعد نفسها.
     *
     * ويكتب **ما أرسل من الحقول المملوكة وحده**: `Crud_model::update_course()`
     * تكتب كل عمود في كل حفظ، فحفظ تبويب واحد يمحو ما سواه (TQ-TAB-WIPE).
     * وهنا لا يمس عمود لم يرسل حقله، فلا يحتاج النموذج أن يحمل حقولا
     * مخفية بقيمها القديمة كي لا تضيع.
     *
     * @param array $actor من `actor_as()`
     */
    public function save_course($actor, $id, $post, $files = array())
    {
        $this->install_schema();

        $id    = (int) $id;
        $post  = is_array($post) ? $post : array();
        $files = is_array($files) ? $files : array();
        $isnew = $id <= 0;

        if (!$isnew && !$this->may_edit_course($actor, $id)) {
            return $this->fail('هذا الكورس ليس ضمن كورساتك.');
        }

        $spec    = $this->course_fields($actor);
        $current = $isnew ? array() : ($this->course($id) ?: array());
        if (!$isnew && !$current) return $this->fail('لا كورس بهذا المعرف.');

        $errors = array();
        $data   = array();
        $link   = array('grade' => null, 'subject' => null);
        $paths  = array();
        $status_note = '';

        foreach ($spec as $name => $f) {
            $sent = array_key_exists($name, $post);

            /* الصف والمادة يذهبان إلى `paths` لا إلى `course`. */
            if ($name === 'tq_grade_id')   { if ($sent) $link['grade']   = (int) $post[$name]; continue; }
            if ($name === 'tq_subject_id') { if ($sent) $link['subject'] = (int) $post[$name]; continue; }

            switch ($f['kind']) {

                case 'image':
                    $up = $this->take_file($files, $name, $f);
                    if (is_array($up) && isset($up['error'])) { $errors[] = $up['error']; break; }
                    /* الاسم لا المسار: `course.thumbnail` يخزن اسم الملف
                       وحده كما يخزنه المسار الموروث، والقالب يبني
                       الرابط من مجلده. */
                    if ($up) { $data[$f['col']] = $up['name']; $paths[] = $up['path']; }
                    break;

                case 'bool':
                    /* الخانة غير المؤشرة لا ترسل أصلا، فلا يفرق «لم يرسل»
                       عن «أطفئ». وحقل مرافق `<name>_sent` هو ما يفصل —
                       يطبع مع كل خانة، فيعلم النموذج أن الشاشة عرضتها. */
                    if (!empty($post[$name . '_sent'])) {
                        $data[$f['col']] = ($sent && (string) $post[$name] !== '0') ? 1 : 0;
                    }
                    break;

                case 'lines':
                    if (!$sent) break;
                    $vals = preg_split('/\r\n|\r|\n/', (string) $post[$name]);
                    $vals = array_values(array_filter(array_map('trim', $vals), 'strlen'));
                    $data[$f['col']] = json_encode($vals, JSON_UNESCAPED_UNICODE);
                    break;

                case 'status':
                    if (!$sent) break;
                    $want = (string) $post[$name];
                    if (!array_key_exists($want, self::course_statuses())) $want = 'draft';
                    /* النشر ليس بيد المعلم — القاعدة نفسها التي تحكم
                       الدروس (`may_publish`). وما يعلنه منشورا أو خاصا
                       ينزل إلى «قيد المراجعة»، ولا يرد بخطأ: هو لم يخطئ،
                       وإنما القرار ليس له.
                       **ويقال له**: كان الخفض يقع صامتا والرسالة «حفظت
                       تعديلات الكورس» — فيظن المعلم أنه نشر، ويفتح رابط
                       كورسه فلا يجده، ولا شيء يفسر. وأخته `save_lesson()`
                       تلحق `$status_note` منذ كتبت. */
                    if (!$this->may_publish($actor)
                        && in_array($want, array('active', 'private', 'upcoming'), true)) {
                        $asked = $this->status_label($want);
                        $want  = 'pending';
                        $status_note = ' واخترت «' . $asked . '»، والنشر قرار إدارة —'
                                     . ' فحفظ الكورس «قيد المراجعة» وسيصل إلى طلابك متى اعتمد.';
                    }
                    $data['status'] = $want;
                    break;

                case 'money':
                    if (!$sent) break;
                    $data[$f['col']] = max(0, (float) $post[$name]);
                    break;

                case 'ref':
                case 'category':
                    if (!$sent) break;
                    $data[$f['col']] = (int) $post[$name];
                    break;

                case 'url':
                    if (!$sent) break;
                    /* الرابط قيمة تفكك لا نص يعرض — TQ-URLESC. */
                    $data[$f['col']] = $this->clean_url_val((string) $post[$name]);
                    break;

                case 'richtext':
                    if (!$sent) break;
                    $data[$f['col']] = $this->clean_html((string) $post[$name], true);
                    break;

                case 'enum':
                    if (!$sent) break;
                    $v = (string) $post[$name];
                    $data[$f['col']] = isset($f['options'][$v]) ? $v : $f['default'];
                    break;

                case 'language':
                case 'datetime':
                case 'textarea':
                default:
                    if (!$sent) break;
                    $v = trim((string) $post[$name]);
                    if (!empty($f['max']) && $this->len($v) > $f['max']) {
                        $errors[] = $f['label'] . ': أطول من المسموح (' . (int) $f['max'] . ' حرفا).';
                        break;
                    }
                    if (!empty($f['required']) && $v === '') {
                        $errors[] = $f['label'] . ' مطلوب.';
                        break;
                    }
                    $data[$f['col']] = ($f['kind'] === 'text') ? html_escape($v) : $v;
            }
        }

        if ($isnew && trim((string) $this->val($data, 'title')) === '') {
            $errors[] = 'عنوان الكورس مطلوب.';
        }
        if ($errors) { $this->cleanup($paths); return $this->fail($errors); }

        $data['last_modified'] = time();

        if ($isnew) {
            $data += array(
                'creator'        => (int) $actor['id'],
                'user_id'        => (string) (int) $actor['id'],
                'course_type'    => 'general',
                'date_added'     => time(),
                'price'          => 0,
                'is_free_course' => 0,
                'expiry_period'  => 0,
                'language'       => get_settings('language') ?: 'arabic',
            );
            if (empty($data['status'])) {
                $data['status'] = $this->may_publish($actor) ? 'draft' : 'pending';
            }
            $this->db->insert('course', $data);
            $id  = (int) $this->db->insert_id();
            $msg = $this->may_publish($actor)
                ? 'أنشئ الكورس.'
                : 'أنشئ الكورس، وهو بانتظار مراجعة الإدارة قبل النشر.';
        } else {
            $this->db->where('id', $id)->update('course', $data);
            $msg = 'حفظت تعديلات الكورس.';
        }

        /* الجسر إلى `paths` — وبه وحده يصل الكورس إلى طالب.
           ينادى متى أرسل أحد الحقلين، ويقرأ الآخر من المخزون إن لم
           يرسل: شاشة تعرض المادة وحدها لا يجوز أن تمحو الصف. */
        $sync = null;
        if ($link['grade'] !== null || $link['subject'] !== null) {
            $g = $link['grade']   !== null ? $link['grade']   : (int) $this->val($current, 'tq_grade_id', 0);
            $s = $link['subject'] !== null ? $link['subject'] : (int) $this->val($current, 'tq_subject_id', 0);

            $CI = get_instance();
            $CI->load->model('taqdar_course_link_model', 'tq_link_m');
            $sync = $CI->tq_link_m->sync($id, $g, $s);

            if ($g > 0 && $s > 0) {
                $msg .= ' وربط بصفه ومادته، فيظهر في «المواد والبرامج» متى نشر.';
            } else {
                $msg .= ' وبلا صف ومادة يبقى محتوى داخليا لا يعرض في الموقع العام.';
            }
        }

        $msg .= $status_note;

        $this->log($actor, $isnew ? 'course.create' : 'course.save', 'course:' . $id,
                   array('fields' => array_keys($data)));

        return array('ok' => true, 'id' => $id, 'message' => $msg, 'sync' => $sync);
    }

    /**
     * الرابط يخزن كما يكتب — TQ-URLESC.
     *
     * `html_escape()` يحول `&` إلى `&amp;` فيقرأ المشغل معاملا اسمه
     * `amp;list`. والتهريب موضعه العرض.
     */
    private function clean_url_val($raw)
    {
        $u = trim((string) $raw);
        if ($u === '') return '';
        $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
        return preg_match('~^(https?://|/)~i', $u) ? $u : '';
    }

    /** أقسام كورس مرتبة، ومع كل قسم عدد دروسه. */
    public function sections_of($course_id)
    {
        $this->install_schema();
        $rows = $this->db->query(
            'SELECT s.`id`, s.`title`, s.`course_id`, s.`order`,
                    s.`start_date`, s.`end_date`, s.`restricted_by`,
                    (SELECT COUNT(*) FROM `lesson` l WHERE l.`section_id` = s.`id`) AS lessons
               FROM `section` s
              WHERE s.`course_id` = ?
              ORDER BY s.`order` ASC, s.`id` ASC',
            array((int) $course_id)
        )->result_array();

        foreach ($rows as &$r) {
            $r['id']      = (int) $r['id'];
            $r['order']   = (int) $r['order'];
            $r['lessons'] = (int) $r['lessons'];
        }
        unset($r);
        return $rows;
    }

    /** قسم بمعرفه. */
    public function section($id)
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        return $this->db->where('id', $id)->get('section')->row_array();
    }

    /** درس بمعرفه. */
    public function lesson($id)
    {
        $this->install_schema();
        $id = (int) $id;
        if ($id <= 0) return null;
        $row = $this->db->where('id', $id)->get('lesson')->row_array();
        if ($row) $row['tq_kind'] = self::kind_of($row);
        return $row;
    }

    /**
     * دروس كورس أو قسم، مرتبة كما يراها الطالب.
     *
     * تحمل حالتها وعدد أسئلة اختبارها: الشاشة تحتاج الاثنين في كل صف،
     * واستعلام لكل صف يعني استعلاما في حلقة.
     */
    public function lessons_of($course_id, $section_id = null)
    {
        $this->install_schema();
        $args  = array((int) $course_id);
        $where = 'l.`course_id` = ?';
        if ($section_id !== null) {
            $where .= ' AND l.`section_id` = ?';
            $args[] = (int) $section_id;
        }

        $rows = $this->db->query(
            'SELECT l.`id`, l.`title`, l.`course_id`, l.`section_id`, l.`order`,
                    l.`lesson_type`, l.`attachment_type`, l.`video_type`,
                    l.`video_url`, l.`audio_url`, l.`attachment`, l.`caption`,
                    l.`duration`, l.`duration_sec`, l.`summary`, l.`is_free`,
                    l.`tq_status`, l.`tq_review_note`,
                    (SELECT COUNT(*) FROM `objectives` o WHERE o.`lesson_id` = l.`id`) AS objectives
               FROM `lesson` l
              WHERE ' . $where . '
              ORDER BY l.`order` ASC, l.`id` ASC',
            $args
        )->result_array();

        foreach ($rows as &$r) {
            $r['id']           = (int) $r['id'];
            $r['section_id']   = (int) $r['section_id'];
            $r['order']        = (int) $r['order'];
            $r['is_free']      = (int) $r['is_free'];
            $r['duration_sec'] = (int) $r['duration_sec'];
            $r['objectives']   = (int) $r['objectives'];
            $r['tq_kind']      = self::kind_of($r);
        }
        unset($r);
        return $rows;
    }

    /**
     * TQ-DURATION — الدروس التي تخالف مشغلاتها ما كتب فيها.
     *
     * المدة المكتوبة **أساس القفل**: تسعون بالمئة منها هي ما يفتح الدرس
     * التالي. فإن كتبت أطول من المقطع لم يبلغها أحد أبدا، وبقي المقرر
     * مقفلا على كل من اشترك — ولا شيء في أي شاشة يقول لماذا. وهذا
     * أخطر ما في هذا الحقل: يخطئ صامتا، ويظهر أثره عند الطالب لا عند
     * من كتبه.
     *
     * و`Taqdar_repo_model` يصحح وحده حين يتفق شاهدان مستقلان (انظر
     * `effective_duration()` هناك). وما دون النصاب لا يصحح — ولا يسكت
     * عنه أيضا: يعرض هنا لمن يملك إصلاحه، بشهادته وعددها.
     *
     * @return array  معرف الدرس => array('measured', 'witnesses', 'authored')
     */
    public function duration_conflicts($course_id)
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) return array();

        try {
            $rows = $this->db->query(
                'SELECT p.`lesson_id`, p.`media_sec`, l.`duration_sec`
                   FROM `lesson_progress` p
                   JOIN `lesson` l ON l.`id` = p.`lesson_id`
                  WHERE l.`course_id` = ? AND p.`media_sec` > 0',
                array($course_id)
            )->result_array();
        } catch (Throwable $e) {
            /* العمود يركب عند أول نبضة تقدم (`ensure_progress_schema`)،
               فمنصة لم يفتح فيها درس بعد تقرأ عمودا غير موجود. وشاشة
               بيضاء أسوأ من تنبيه ناقص. */
            return array();
        }

        $by = array();
        foreach ($rows as $r) {
            $by[(int) $r['lesson_id']]['authored'] = (int) $r['duration_sec'];
            $by[(int) $r['lesson_id']]['seen'][]   = (int) $r['media_sec'];
        }

        $out = array();
        foreach ($by as $lid => $d) {
            $seen = $d['seen'];
            sort($seen);
            $mid   = $seen[intdiv(count($seen), 2)];
            $slack = max(5, (int) round($mid * 0.10));
            /* المكتوب يوافق المقاس: لا خبر هنا. */
            if ($d['authored'] > 0 && abs($d['authored'] - $mid) <= $slack) continue;

            $agree = 0;
            foreach ($seen as $v) if (abs($v - $mid) <= $slack) $agree++;

            $out[$lid] = array(
                'measured'  => $mid,
                'witnesses' => $agree,
                'authored'  => $d['authored'],
            );
        }
        return $out;
    }

    /** المقرر كاملا: أقسام وفيها دروسها — استعلامان لا استعلام لكل قسم. */
    public function outline($course_id)
    {
        $course_id = (int) $course_id;
        $sections  = $this->sections_of($course_id);
        $lessons   = $this->lessons_of($course_id);

        $by_section = array();
        $orphans    = array();
        foreach ($lessons as $l) {
            if ($l['section_id'] > 0) $by_section[$l['section_id']][] = $l;
            else                      $orphans[] = $l;
        }

        foreach ($sections as &$s) {
            $s['items'] = isset($by_section[$s['id']]) ? $by_section[$s['id']] : array();
        }
        unset($s);

        /* الدرس بلا قسم يظهر ولا يخفى: صف يتيم كتب من مسار قديم يبقى
           غير مرئي إلى الأبد لو رسمنا الأقسام وحدها، ولا يعرف صاحبه
           لماذا يعد الكورس درسا لا يراه. */
        return array('sections' => $sections, 'orphans' => $orphans);
    }

    /* =====================================================================
       الأقسام — الكتابة
       ===================================================================== */

    /**
     * يحفظ قسما — إنشاء إن كان `$id` صفرا، وتحريرا إن لم يكن.
     *
     * ولا يمس `course.section` (عمود JSON موروث يسرد معرفات الأقسام):
     * الترتيب صار في `section.order`، والعمودان مصدران للحقيقة نفسها
     * يفترقان عند أول كتابة من مسار لا يعرف الثاني. وهو يحدث هنا للحفاظ
     * على شاشات Academy التي تقرؤه.
     */
    public function save_section($actor, $course_id, $id, $post)
    {
        $this->install_schema();

        $course_id = (int) $course_id;
        $id        = (int) $id;

        if (!$this->may_edit_course($actor, $course_id)) {
            return $this->fail('هذا الكورس ليس لك.');
        }
        if ($id > 0) {
            $cur = $this->section($id);
            if (!$cur || (int) $cur['course_id'] !== $course_id) {
                return $this->fail('القسم ليس من هذا الكورس.');
            }
        }

        $title = $this->clean_title($this->val($post, 'title'));
        if ($title === '')  return $this->fail('اكتب عنوان القسم.');
        if ($this->len($title) > self::MAX_TITLE) {
            return $this->fail('عنوان القسم أطول من ' . self::MAX_TITLE . ' حرفا.');
        }

        $data = array('title' => $title, 'course_id' => $course_id);

        /* خطة الدراسة: التاريخان معا أو لا شيء. وتاريخ بدء بلا نهاية
           يجعل القسم مقفلا إلى الأبد في محرك التقييد الموروث. */
        $range = trim((string) $this->val($post, 'date_range_of_study_plan'));
        if ($range !== '' && strpos($range, ' - ') !== false) {
            [$from, $to] = array_map('trim', explode(' - ', $range, 2));
            $f = strtotime($from);
            $t = strtotime($to);
            if ($f && $t && $t >= $f) {
                $data['start_date']    = $f;
                $data['end_date']      = $t;
                $data['restricted_by'] = (string) $this->val($post, 'restricted_by');
            } else {
                return $this->fail('مدى خطة الدراسة غير مفهوم — التاريخان بصيغة YYYY-MM-DD والنهاية بعد البداية.');
            }
        }

        if ($id > 0) {
            $this->db->where('id', $id)->update('section', $data);
        } else {
            $data['order'] = $this->next_order('section', 'course_id', $course_id);
            $this->db->insert('section', $data);
            $id = (int) $this->db->insert_id();
        }

        $this->sync_legacy_section_json($course_id);
        $this->log($actor, $id > 0 ? 'section.save' : 'section.create', 'section:' . $id, $data);

        return array('ok' => true, 'id' => $id, 'message' => 'حفظ القسم.');
    }

    /**
     * يحذف قسما ودروسه.
     *
     * والدروس تحذف بـ`delete_lesson()` لا بـ`DELETE` واحد: لكل درس
     * ملفات على القرص وتقدم طلاب وأهداف وأسئلة، وحذف الصف وحده يترك
     * الثلاثة معلقة. وهذا ما كان يفعله `Crud_model::delete_section()`.
     */
    public function delete_section($actor, $id)
    {
        $this->install_schema();

        $id  = (int) $id;
        $sec = $this->section($id);
        if (!$sec) return $this->fail('لا قسم بهذا المعرف.');
        if (!$this->may_edit_course($actor, (int) $sec['course_id'])) {
            return $this->fail('هذا القسم ليس لك.');
        }

        $lessons = $this->db->select('id')->where('section_id', $id)->get('lesson')->result_array();
        foreach ($lessons as $l) {
            $this->delete_lesson($actor, (int) $l['id'], true);
        }

        $this->db->where('id', $id)->delete('section');
        $this->sync_legacy_section_json((int) $sec['course_id']);
        $this->log($actor, 'section.delete', 'section:' . $id, $sec);

        return array('ok' => true, 'message' => 'حذف القسم و' . count($lessons) . ' من دروسه.');
    }

    /** يعيد ترتيب أقسام كورس بقائمة معرفات. */
    public function sort_sections($actor, $course_id, $ids)
    {
        $course_id = (int) $course_id;
        if (!$this->may_edit_course($actor, $course_id)) {
            return $this->fail('هذا الكورس ليس لك.');
        }

        $n = 0;
        foreach ((array) $ids as $i => $sid) {
            $sid = (int) $sid;
            if ($sid <= 0) continue;
            /* الشرط على الكورس لا على المعرف وحده: قائمة معدلة كانت
               ترتب أقسام كورس آخر بلا أن يظهر ذلك في شاشة. */
            $this->db->where('id', $sid)->where('course_id', $course_id)
                     ->update('section', array('order' => $i + 1));
            $n += $this->db->affected_rows();
        }

        $this->sync_legacy_section_json($course_id);
        $this->log($actor, 'section.sort', 'course:' . $course_id, array('n' => $n));

        return array('ok' => true, 'message' => 'رتب ' . $n . ' قسما.');
    }

    /* =====================================================================
       الدروس — الكتابة
       ===================================================================== */

    /**
     * يحفظ درسا — إنشاء إن كان `$id` صفرا، وتحريرا إن لم يكن.
     *
     * وهي الدالة التي تنادى من اللوحة ومن بوابة المعلم معا. والفرق
     * بينهما ثلاثة أشياء لا أكثر، وكلها تقرر هنا:
     *
     *   · الملكية — `may_edit_course()`
     *   · الحالة  — `may_publish()`؛ فما يطلبه المعلم نشرا يصير `review`
     *   · المراجعة — التعديل على درس **منشور** لا يكتب في الصف الحي
     *                 (انظر `stage_revision()`)
     *
     * وترد `['ok'=>bool, 'errors'=>[], 'id'=>int, 'staged'=>bool, 'message'=>string]`.
     *
     * @param array $actor ['id'=>int,'role'=>'admin'|'teacher']
     * @param int   $id    صفر للإنشاء
     * @param array $post  حقول النموذج (بلا تهريب)
     * @param array $files ‏$_FILES‎
     */
    public function save_lesson($actor, $id, $post, $files = array())
    {
        $this->install_schema();

        $id    = (int) $id;
        $post  = is_array($post)  ? $post  : array();
        $files = is_array($files) ? $files : array();

        /* الطلب تجاوز `post_max_size`: PHP يفرغ POST و FILES معا، فلولا
           هذا الفحص لقيل لمن رفع ملفا كبيرا «العنوان مطلوب» وهو كتبه. */
        if (!$post && !$files && $this->post_overflowed()) {
            return $this->fail('الملف أكبر من حد الرفع على الخادم ('
                . ini_get('post_max_size') . ') فلم يصل الطلب أصلا.');
        }

        $current = $id > 0 ? $this->lesson($id) : null;
        if ($id > 0 && !$current) return $this->fail('لا درس بهذا المعرف.');

        /* ---- الملكية أولا: قبل أي تحقق وقبل لمس أي ملف ---- */
        $course_id = $id > 0
            ? (int) $current['course_id']
            : (int) $this->val($post, 'course_id');

        if (!$this->may_edit_course($actor, $course_id)) {
            return $this->fail('هذا الكورس ليس ضمن كورساتك، فلا يكتب فيه درس.');
        }

        /* ---- النوع ---- */
        $kind = (string) $this->val($post, 'tq_kind',
                    $current ? self::kind_of($current) : 'youtube');
        $spec = self::lesson_type($kind);
        if (!$spec) return $this->fail('نوع الدرس غير معروف.');

        $errors = array();
        $data   = array();

        /* ---- القسم: لا بد أن يكون من هذا الكورس ---- */
        $section_id = (int) $this->val($post, 'section_id',
                          $current ? (int) $current['section_id'] : 0);
        if ($section_id <= 0) {
            $errors[] = 'اختر القسم الذي يحمل الدرس.';
        } else {
            $owner = (int) $this->db->select('course_id')->where('id', $section_id)
                                    ->get('section')->row('course_id');
            if ($owner !== $course_id) {
                $errors[] = 'القسم المحدد ليس من هذا الكورس.';
                $section_id = 0;
            }
        }

        /* ---- العنوان ---- */
        $title = $this->clean_title($this->val($post, 'title'));
        $len   = $this->len($title);
        if ($title === '')            $errors[] = 'اكتب عنوان الدرس.';
        elseif ($len < 3)             $errors[] = 'عنوان الدرس أقصر من أن يدل عليه.';
        elseif ($len > self::MAX_TITLE) $errors[] = 'عنوان الدرس أطول من ' . self::MAX_TITLE . ' حرفا.';

        /* ---- حقول النوع: المصدر والمدة والمرفق ---- */
        $uploaded = array();   // ما كتب على القرص، ليحذف إن فشل ما بعده
        $media    = $this->collect_fields($spec, $post, $files, $current, $uploaded);
        if ($media['errors']) {
            $this->cleanup($uploaded);
            return $this->fail(array_merge($errors, $media['errors']));
        }
        $data = array_merge($data, $media['data']);

        if ($errors) {
            $this->cleanup($uploaded);
            return $this->fail($errors);
        }

        /* ---- الأعمدة الثابتة للنوع ---- */
        foreach ($spec['row'] as $col => $v) {
            /* المستند يكتب امتداده في `attachment_type` (pdf · doc · txt)
               بدل الثابت — والحقل يعلن ذلك بـ`sets`. */
            if (!array_key_exists($col, $data)) $data[$col] = $v;
        }

        $data['title']      = $title;
        $data['section_id'] = $section_id;
        $data['course_id']  = $course_id;
        $data['summary']    = $this->clean_html($this->val($post, 'summary'));
        $data['is_free']    = (int) !empty($this->val($post, 'is_free',
                                   $this->val($post, 'free_lesson')));

        /* ---- الحالة ---- */
        $want = strtolower(trim((string) $this->val($post, 'action', 'draft')));
        if (!in_array($want, $this->statuses, true)) $want = 'draft';
        /* «مرفوض» ليست حالة تطلب — هي قرار مسؤول. */
        if ($want === 'rejected') $want = 'draft';

        $note = '';
        if ($want === 'published' && !$this->may_publish($actor)) {
            $want = 'review';
            $note = ' والنشر قرار إدارة، فأرسل الدرس للمراجعة.';
        }
        $data['tq_status'] = $want;

        /* الحالة تنتقل من مراجعة إلى مراجعة: من رفض درسه ثم عدله يعود
           إلى الطابور نظيفا، ولا يبقى سبب رفض قديم معلقا على صف مرسل
           من جديد فيقرؤه المسؤول قرارا اتخذ. */
        if ($want !== 'rejected') {
            $data['tq_review_note'] = null;
        }

        /* ---- الإنشاء ---- */
        if ($id <= 0) {
            $data['order']      = $this->next_order('lesson', 'section_id', $section_id);
            $data['date_added'] = time();
            $this->db->insert('lesson', $data);
            $id = (int) $this->db->insert_id();

            $this->save_objectives($id, $post);
            $this->log($actor, 'lesson.create', 'lesson:' . $id, $data);

            return array('ok' => true, 'id' => $id, 'staged' => false,
                         'status' => $want,
                         'message' => $this->status_message($want) . $note);
        }

        /* ---- التحرير ----
           درس منشور يعدله من لا ينشر: الصف الحي لا يمس، والتعديل ينتظر
           بجواره. فمن دفع لا يفقد درسه لأن معلمه صحح خطأ إملائيا. */
        $live = (string) $current['tq_status'] === 'published';
        if ($live && !$this->may_publish($actor)) {
            unset($data['tq_status'], $data['tq_review_note']);
            $r = $this->stage_revision($actor, 'lesson', $id, $course_id, $data,
                                       (string) $this->val($post, 'tq_note'));
            $this->cleanup_unused_on_stage($uploaded);   // لا شيء: الملفات تبقى للحمولة
            return $r;
        }

        $data['last_modified'] = time();
        $this->db->where('id', $id)->update('lesson', $data);
        $this->save_objectives($id, $post);
        $this->log($actor, 'lesson.update', 'lesson:' . $id, $data, $current);

        return array('ok' => true, 'id' => $id, 'staged' => false,
                     'status' => $want,
                     'message' => $this->status_message($want) . $note);
    }

    /**
     * يحذف درسا وكل ما تعلق به.
     *
     * `Crud_model::delete_lesson()` كانت تحذف الصف وتترك: الأهداف
     * وأسئلتها وتقدم الطلاب ومحاولاتهم وملفاته على القرص. فيبقى في
     * `lesson_progress` صف يشير إلى درس غير موجود، ويقرأ `ordered_lessons`
     * عددا لا يطابق ما يعرض.
     *
     * @param bool $cascade نداء داخلي من حذف القسم — فلا يفحص الملكية ثانية
     */
    public function delete_lesson($actor, $id, $cascade = false)
    {
        $this->install_schema();

        $id  = (int) $id;
        $row = $this->lesson($id);
        if (!$row) return $this->fail('لا درس بهذا المعرف.');

        if (!$cascade && !$this->may_edit_course($actor, (int) $row['course_id'])) {
            return $this->fail('هذا الدرس ليس لك.');
        }

        /* الملفات تقرأ قبل الحذف: بعده لا يبقى صف يدل عليها. */
        $this->drop_lesson_files($row);

        /* الأسئلة قبل الأهداف، والأهداف قبل الدرس: الترتيب يعكس
           الإشارة، فلا يبقى صف يشير إلى محذوف. */
        $obj_ids = array();
        foreach ($this->db->select('id')->where('lesson_id', $id)->get('objectives')->result_array() as $o) {
            $obj_ids[] = (int) $o['id'];
        }
        if ($obj_ids) {
            $this->db->where_in('objective_id', $obj_ids)->delete('question');
            $this->db->where_in('id', $obj_ids)->delete('objectives');
        }

        /* التقييمات ومحاولاتها — والاختبار يحذف بدرسه: اختبار بلا درس
           لا يفتح ولا يعرض، ويبقى في القوائم رقما بلا اسم. */
        $as_ids = array();
        foreach ($this->db->select('id')->where('lesson_id', $id)->get('assessments')->result_array() as $a) {
            $as_ids[] = (int) $a['id'];
        }
        if ($as_ids) {
            $at_ids = array();
            foreach ($this->db->select('id')->where_in('assessment_id', $as_ids)
                              ->get('attempts')->result_array() as $t) {
                $at_ids[] = (int) $t['id'];
            }
            if ($at_ids) $this->db->where_in('attempt_id', $at_ids)->delete('answers');
            $this->db->where_in('assessment_id', $as_ids)->delete('attempts');
            $this->db->where_in('assessment_id', $as_ids)->delete('question');
            $this->db->where_in('id', $as_ids)->delete('assessments');
        }

        $this->db->where('lesson_id', $id)->delete('lesson_progress');
        $this->safe_delete('tq_lesson_notes', 'lesson_id', $id);
        $this->safe_delete('tq_transcripts', 'lesson_id', $id);

        $this->db->where('entity', 'lesson')->where('entity_id', $id)
                 ->delete('tq_content_revisions');

        /* سجل المشاهدة: الدرس يزال من قائمة المكتمل ومن موضع التوقف. */
        $this->clear_watch_history($id, (int) $row['course_id']);

        $this->db->where('id', $id)->delete('lesson');
        $this->log($actor, 'lesson.delete', 'lesson:' . $id, null, $row);

        return array('ok' => true, 'message' => 'حذف الدرس.');
    }

    /** يعيد ترتيب دروس قسم بقائمة معرفات. */
    public function sort_lessons($actor, $section_id, $ids)
    {
        $section_id = (int) $section_id;
        if (!$this->may_edit_section($actor, $section_id)) {
            return $this->fail('هذا القسم ليس لك.');
        }

        $n = 0;
        foreach ((array) $ids as $i => $lid) {
            $lid = (int) $lid;
            if ($lid <= 0) continue;
            $this->db->where('id', $lid)->where('section_id', $section_id)
                     ->update('lesson', array('order' => $i + 1));
            $n += $this->db->affected_rows();
        }

        $this->log($actor, 'lesson.sort', 'section:' . $section_id, array('n' => $n));
        return array('ok' => true, 'message' => 'رتب ' . $n . ' درسا.');
    }

    /**
     * ينقل درسا إلى قسم آخر من الكورس نفسه.
     *
     * والقسمان من كورس واحد شرط: نقل درس إلى كورس آخر يعني أن طلاب
     * الأول يفقدون تقدمهم فيه بلا أن يخبروا، وأن قسمة الإيراد تحسب على
     * مسار لم يعد الدرس فيه.
     */
    public function move_lesson($actor, $id, $section_id)
    {
        $id  = (int) $id;
        $row = $this->lesson($id);
        if (!$row) return $this->fail('لا درس بهذا المعرف.');
        if (!$this->may_edit_course($actor, (int) $row['course_id'])) {
            return $this->fail('هذا الدرس ليس لك.');
        }

        $section_id = (int) $section_id;
        $owner = (int) $this->db->select('course_id')->where('id', $section_id)
                                ->get('section')->row('course_id');
        if ($owner !== (int) $row['course_id']) {
            return $this->fail('القسم المقصود ليس من كورس هذا الدرس.');
        }

        $this->db->where('id', $id)->update('lesson', array(
            'section_id' => $section_id,
            'order'      => $this->next_order('lesson', 'section_id', $section_id),
        ));
        $this->log($actor, 'lesson.move', 'lesson:' . $id, array('section_id' => $section_id));

        return array('ok' => true, 'message' => 'نقل الدرس.');
    }

    /* =====================================================================
       جمع حقول النوع
       ===================================================================== */

    /**
     * يقرأ حقول النوع من النموذج ويرد أعمدة الصف.
     *
     * وهي الدالة التي تجعل النوع الجديد يعمل بلا شيفرة: يصف حقوله في
     * `lesson_types()` فتقرأ هنا بنوعها — رابطا أو ملفا أو نصا.
     *
     * @param array $uploaded يملأ بمسارات ما كتب على القرص، ليحذف إن فشل ما بعده
     */
    private function collect_fields($spec, $post, $files, $current, &$uploaded)
    {
        $data   = array();
        $errors = array();
        $timed  = !empty($spec['timed']);

        foreach ($spec['fields'] as $name => $f) {
            $col      = $f['col'];
            $kind     = $f['kind'];
            $required = !empty($f['required']);
            $has_old  = $current && trim((string) $this->val($current, $col)) !== '';

            switch ($kind) {

                case 'url':
                    $raw = trim((string) $this->val($post, $name));
                    if ($raw === '') {
                        if ($required && !$has_old) $errors[] = $f['label'] . ' مطلوب.';
                        break;
                    }
                    $url = tq_clean_url($raw);
                    if ($url === '') {
                        $errors[] = $f['label'] . ' ليس رابطا صالحا — يبدأ بـ http أو https.';
                        break;
                    }
                    $data[$col] = $url;
                    break;

                case 'duration':
                    $sec = $this->parse_duration($this->val($post, $name));
                    if ($sec <= 0 && $required && !$has_old) {
                        $errors[] = $f['label'] . ' مطلوبة.';
                        break;
                    }
                    if ($sec > 0) {
                        $data['duration']     = $this->hms($sec);
                        $data['duration_sec'] = $sec;
                    }
                    break;

                case 'file':
                    $up = $this->take_file($files, $name, $f);
                    if ($up === null) {
                        if ($required && !$has_old) $errors[] = $f['label'] . ' مطلوب.';
                        break;
                    }
                    if (isset($up['error'])) { $errors[] = $up['error']; break; }

                    $uploaded[] = $up['path'];
                    $data[$col] = !empty($f['as_url']) ? site_url($up['path']) : $up['name'];

                    /* المستند يكتب امتداده نوعا للمرفق: `pdf` تفتح في
                       عارض والملف النصي لا. */
                    if (!empty($f['sets'])) {
                        $data[$f['sets']] = $up['ext'] === 'docx' ? 'doc' : $up['ext'];
                    }
                    /* الملف القديم يحذف بعد نجاح الجديد لا قبله. */
                    if ($has_old) $this->drop_stored($current[$col], $f);
                    break;

                case 'richtext':
                    $html = $this->clean_html($this->val($post, $name), true);
                    $plain = trim(strip_tags($html));
                    if ($plain === '') {
                        if ($required && !$has_old) $errors[] = $f['label'] . ' مطلوب.';
                        break;
                    }
                    if (isset($f['min']) && $this->len($plain) < (int) $f['min']) {
                        $errors[] = $f['label'] . ' أقصر من أن يكون درسا — اكتب ' . (int) $f['min'] . ' حرفا على الأقل.';
                        break;
                    }
                    $data[$col] = $html;
                    break;

                case 'textarea':
                    $v = trim((string) $this->val($post, $name));
                    if ($v === '') {
                        if ($required && !$has_old) $errors[] = $f['label'] . ' مطلوب.';
                        break;
                    }
                    $data[$col] = $this->clean_html($v, true);
                    break;
            }
        }

        /* النوع غير الموقوت يصفر مدته: صورة بمدة `00:12:00` تجعل
           `completed_at` ينتظر اثنتي عشرة دقيقة على شيء يقرأ في دقيقة. */
        if (!$timed) {
            $data['duration']     = '00:00:00';
            $data['duration_sec'] = 0;
        }

        return array('data' => $data, 'errors' => $errors);
    }

    /* =====================================================================
       المدة
       ===================================================================== */

    /**
     * يقرأ مدة مكتوبة بأي من صيغها إلى ثوان.
     *
     * `HH:MM:SS` و`MM:SS` و`12` (دقائق؟ ثوان؟) — والأخيرة ملتبسة، فتقرأ
     * **ثوان** كما يقرؤها `duration_seconds()` في `Taqdar_repo_model`.
     * ونموذج المعلم يرسل الدقائق بحقل اسمه `duration_minutes` صراحة، فلا
     * يلتبس رقمان.
     */
    public function parse_duration($raw)
    {
        $s = trim((string) $raw);
        if ($s === '') return 0;
        if (ctype_digit($s)) return (int) $s;

        if (!preg_match('/^\d{1,3}(:\d{1,2}){1,2}$/', $s)) return 0;

        $parts = array_reverse(explode(':', $s));
        $sec = 0; $mul = 1;
        foreach ($parts as $p) { $sec += ((int) $p) * $mul; $mul *= 60; }
        return $sec;
    }

    /** ثوان إلى `HH:MM:SS` — وهي الصيغة التي تقرأ منها شاشات Academy. */
    public function hms($sec)
    {
        $sec = max(0, (int) $sec);
        return sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
    }

    /**
     * يكتب المدة التي اكتشفها المشغل في المتصفح.
     *
     * يوتيوب وفيميو يعلنان مدة الفيديو، ولا يعلنها الخادم إلا بمفتاح
     * واجهة برمجة. والمشغل يعرفها بعد ثانية من التحميل، فيرسلها مرة
     * واحدة — والكتابة مشروطة بأن المخزن صفر: مدة كتبها صاحبها بيده لا
     * يدهسها رقم من متصفح زائر.
     */
    public function record_duration($lesson_id, $seconds)
    {
        $this->install_schema();

        $lesson_id = (int) $lesson_id;
        $seconds   = (int) $seconds;
        /* الحد الأعلى ثماني ساعات: رقم أكبر يأتي من بث مباشر أو من عبث،
           وكلاهما لا يصلح أساسا لنسبة إتمام. */
        if ($lesson_id <= 0 || $seconds <= 0 || $seconds > 28800) return false;

        $row = $this->db->select('duration_sec, duration')->where('id', $lesson_id)
                        ->get('lesson')->row_array();
        if (!$row) return false;
        if ((int) $row['duration_sec'] > 0) return false;

        $this->db->where('id', $lesson_id)->update('lesson', array(
            'duration_sec' => $seconds,
            'duration'     => $this->hms($seconds),
        ));
        return true;
    }

    /* =====================================================================
       الملفات
       ===================================================================== */

    /**
     * يأخذ ملفا مرفوعا ويكتبه، ويرد اسمه ومساره وامتداده.
     *
     * يرد `null` إن لم يرسل ملف أصلا — وهو ليس خطأ: تحرير درس بلا تبديل
     * ملفه يرسل حقلا فارغا.
     */
    private function take_file($files, $field, $f)
    {
        if (!isset($files[$field]) || !is_array($files[$field])) return null;
        $file = $files[$field];
        if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array('error' => $f['label'] . ': تعذر الرفع (' . (int) $file['error'] . ').');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return array('error' => $f['label'] . ': الملف لم يرفع من نموذج.');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (isset($f['ext']) && !in_array($ext, $f['ext'], true)) {
            return array('error' => $f['label'] . ': صيغة غير مقبولة — المقبول ' . implode(' · ', $f['ext']) . '.');
        }
        if ($ext === '' || preg_match('/^(php\d*|phtml|phar|htaccess|cgi|pl|py|sh|exe)$/i', $ext)) {
            return array('error' => $f['label'] . ': صيغة غير مسموحة.');
        }

        $dir = isset($f['dir']) ? $f['dir'] : 'uploads/lesson_files';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return array('error' => 'تعذر إنشاء مجلد الرفع: ' . $dir);
        }

        $name = md5(uniqid((string) mt_rand(), true)) . '.' . $ext;
        $path = rtrim($dir, '/') . '/' . $name;

        if (!@move_uploaded_file($file['tmp_name'], $path)) {
            return array('error' => $f['label'] . ': تعذر حفظ الملف على الخادم.');
        }

        return array('name' => $name, 'path' => $path, 'ext' => $ext);
    }

    /** يحذف ملفا مخزنا — بمساره أو باسمه داخل مجلد الحقل. */
    private function drop_stored($stored, $f)
    {
        $stored = trim((string) $stored);
        if ($stored === '') return;

        $dir  = isset($f['dir']) ? rtrim($f['dir'], '/') : 'uploads/lesson_files';
        $path = !empty($f['as_url'])
            ? ltrim(str_replace(base_url(), '', $stored), '/')
            : $dir . '/' . basename($stored);

        /* لا يخرج من `uploads/`: قيمة معطوبة في العمود لا تحذف ملف نظام. */
        if (strpos($path, 'uploads/') !== 0 || strpos($path, '..') !== false) return;
        if (is_file($path)) @unlink($path);
    }

    /** يحذف كل ملفات درس — عند حذفه. */
    private function drop_lesson_files($row)
    {
        $spec = self::lesson_type(self::kind_of($row));
        if ($spec) {
            foreach ($spec['fields'] as $f) {
                if ($f['kind'] !== 'file') continue;
                $this->drop_stored($this->val($row, $f['col']), $f);
            }
        }
        /* الترجمة والمصغرة خارج الوصف: تكتبان لأي نوع. */
        if (!empty($row['caption'])) {
            $p = 'uploads/captions/' . basename((string) $row['caption']);
            if (is_file($p)) @unlink($p);
        }
        $thumb = 'uploads/thumbnails/lesson_thumbnails/' . (int) $row['id'] . '.jpg';
        if (is_file($thumb)) @unlink($thumb);
    }

    /** يحذف ما كتب على القرص حين يفشل ما بعده. */
    private function cleanup($paths)
    {
        foreach ((array) $paths as $p) {
            if (is_string($p) && strpos($p, 'uploads/') === 0 && is_file($p)) @unlink($p);
        }
    }

    /** الملفات المرفوعة مع اقتراح تبقى: الحمولة تشير إليها حتى يبت فيها. */
    private function cleanup_unused_on_stage($paths) { /* عمدا لا شيء */ }

    /* =====================================================================
       الأهداف
       ===================================================================== */

    /**
     * يحفظ أهداف الدرس — من واحد إلى ثلاثة.
     *
     * والهدف ليس زينة: إليه تنسب أسئلة المراجعة، وبه تعرف بوابة الإتقان
     * أي مفهوم تعثر فيه الطالب فتعيده إلى ثانيته. ودرس بلا هدف يجعل
     * البوابة عاجزة عن الحكم.
     *
     * والمرسل غيابا لا يمحو: تحرير من تبويب لا يحمل الأهداف (التسعير
     * مثلا) كان يمحوها كلها لأن الحقل لم يرسل.
     */
    private function save_objectives($lesson_id, $post)
    {
        if (!array_key_exists('objectives', $post)) return;

        $lesson_id = (int) $lesson_id;
        $wanted    = array();
        foreach ((array) $post['objectives'] as $o) {
            if (!is_scalar($o)) continue;
            $t = trim(preg_replace('/\s+/u', ' ', (string) $o));
            if ($t === '') continue;
            $t = function_exists('mb_substr') ? mb_substr($t, 0, 500, 'UTF-8') : substr($t, 0, 500);
            $wanted[] = $t;
            if (count($wanted) >= 3) break;
        }

        $at = array();
        foreach ((array) $this->val($post, 'objective_at', array()) as $i => $v) {
            $at[$i] = max(0, (int) $v);
        }

        $existing = $this->db->where('lesson_id', $lesson_id)
                             ->order_by('id', 'ASC')->get('objectives')->result_array();

        /* التحديث في موضعه لا حذف وإنشاء: معرف الهدف مشار إليه من
           `question.objective_id` ومن `skill_state`، وإعادة إنشائه تقطع
           أسئلته عن الدرس وتصفر إتقان كل طالب فيه. */
        foreach ($wanted as $i => $text) {
            $row = array('lesson_id' => $lesson_id, 'text' => $text,
                         'at_second' => isset($at[$i]) ? $at[$i] : 0);
            if (isset($existing[$i])) {
                $this->db->where('id', (int) $existing[$i]['id'])->update('objectives', $row);
            } else {
                $this->db->insert('objectives', $row);
            }
        }

        /* الزائد يحذف بأسئلته: هدف بلا درس يبقى في شاشة الربط بلا معنى. */
        for ($i = count($wanted); $i < count($existing); $i++) {
            $oid = (int) $existing[$i]['id'];
            $this->db->where('objective_id', $oid)->delete('question');
            $this->db->where('id', $oid)->delete('objectives');
        }
    }

    /** أهداف درس — للشاشة. */
    public function objectives_of($lesson_id)
    {
        return $this->db->where('lesson_id', (int) $lesson_id)
                        ->order_by('at_second', 'ASC')->order_by('id', 'ASC')
                        ->get('objectives')->result_array();
    }

    /* =====================================================================
       المراجعة — الاقتراح والقرار
       ===================================================================== */

    /**
     * يودع تعديلا على محتوى **منشور** بدل أن يكتبه.
     *
     * والصف الحي لا يمس: الطالب الذي دفع يبقى درسه كما كان حتى يبت
     * المسؤول. وهذا هو الفرق بين هذا وبين إنزال الدرس إلى `review` —
     * وهو فرق يقع على من دفع لا على من يراجع.
     *
     * والاقتراح الواحد يستبدل: من عدل مرتين قبل أن يرد أحد يبقى له
     * اقتراح واحد هو الأحدث، وإلا اعتمد المسؤول القديم وهو يظنه الجديد.
     */
    private function stage_revision($actor, $entity, $entity_id, $course_id, $payload, $note = '')
    {
        $this->install_schema();

        $row = array(
            'entity'       => (string) $entity,
            'entity_id'    => (int) $entity_id,
            'course_id'    => (int) $course_id,
            'kind'         => 'update',
            'payload'      => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'note'         => $this->cut($note, 500),
            'status'       => 'pending',
            'decided_note' => null,
            'requested_by' => (int) $actor['id'],
            'requested_at' => $this->now(),
            'decided_by'   => 0,
            'decided_at'   => null,
        );

        try {
            $old = $this->db->where('entity', $entity)->where('entity_id', (int) $entity_id)
                            ->where('status', 'pending')->get('tq_content_revisions')->row_array();
            if ($old) {
                $this->db->where('id', (int) $old['id'])->update('tq_content_revisions', $row);
                $rev = (int) $old['id'];
            } else {
                $this->db->insert('tq_content_revisions', $row);
                $rev = (int) $this->db->insert_id();
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-CURRIC stage: ' . $e->getMessage());
            return $this->fail('تعذر إيداع التعديل للمراجعة. حاول ثانية.');
        }

        $this->log($actor, 'revision.stage', $entity . ':' . (int) $entity_id, $payload);
        $this->notify_reviewers($course_id, $entity, (int) $entity_id);

        return array(
            'ok' => true, 'id' => (int) $entity_id, 'staged' => true, 'revision_id' => $rev,
            'status'  => 'published',
            'message' => 'أرسل التعديل للمراجعة. والدرس المنشور يبقى كما هو أمام طلابك حتى تعتمده الإدارة.',
        );
    }

    /**
     * ما ينتظر قرار الإدارة — دروس جديدة واقتراحات على منشور، في قائمة واحدة.
     *
     * قائمتان في شاشتين تعنيان أن المسؤول يفتح الثانية إن تذكرها. وهما
     * سؤال واحد: **ما الذي ينتظرني؟**
     */
    public function pending($filters = array())
    {
        $this->install_schema();

        $course = (int) $this->val($filters, 'course_id', 0);
        $out    = array();

        /* ٠ — كورسات ينتظر نشرها — TQ-COURSE-REVIEW.
           `save_course()` تحول ما يعلنه المعلم `active` إلى `pending`
           بحكم `may_publish()`، كما تفعل بحالة الدرس تماما. وشاشة
           المعلم تقول له ذلك صراحة: «بانتظار مراجعة الإدارة».
           **ولم يكن في اللوحة ما يقرأ ذلك**: لا صف في هذا الطابور،
           ولا رقم في الشارة، ولا فرع في `approve()`. فالكورس يجلس في
           `pending` إلى الأبد، والمعلم ينتظر قرارا لا يعلم أحد أنه
           مطلوب — وهذا نصف TQ-COURSE-SPLIT الغائب.
           والمخرج الوحيد كان أن يعرف المسؤول أن يفتح القائمة الموروثة
           في `admin/courses` ويقلب الحالة بيده. */
        try {
            $args  = array();
            $where = 'c.`status` = "pending"';
            if ($course > 0) { $where .= ' AND c.`id` = ?'; $args[] = $course; }

            $rows = $this->db->query(
                'SELECT c.`id`, c.`title`, c.`date_added`, c.`last_modified`,
                        u.`first_name`, u.`last_name`,
                        (SELECT COUNT(*) FROM `lesson` l WHERE l.`course_id` = c.`id`)  AS lessons,
                        (SELECT COUNT(*) FROM `section` s WHERE s.`course_id` = c.`id`) AS sections,
                        p.`grade_id`, p.`subject_id`,
                        g.`name_ar` AS grade_name, sj.`name_ar` AS subject_name
                   FROM `course` c
              LEFT JOIN `users`  u  ON u.`id` = c.`creator`
              LEFT JOIN `paths`  p  ON p.`course_id` = c.`id`
              LEFT JOIN `grades` g  ON g.`id` = p.`grade_id`
              LEFT JOIN `subjects` sj ON sj.`id` = p.`subject_id`
                  WHERE ' . $where . '
               GROUP BY c.`id`
                  ORDER BY COALESCE(c.`last_modified`, c.`date_added`) ASC
                  LIMIT 200', $args
            )->result_array();

            foreach ($rows as $r) {
                $out[] = array(
                    'kind'        => 'course',
                    'entity'      => 'course',
                    'entity_id'   => (int) $r['id'],
                    'revision_id' => 0,
                    'title'       => (string) $r['title'],
                    'course_id'   => (int) $r['id'],
                    'course'      => (string) $r['title'],
                    'section'     => '',
                    'author'      => trim((string) $r['first_name'] . ' ' . (string) $r['last_name']),
                    'tq_kind'     => '',
                    'objectives'  => 0,
                    'duration'    => '',
                    'lessons'     => (int) $r['lessons'],
                    'sections'    => (int) $r['sections'],
                    'grade'       => (string) $r['grade_name'],
                    'subject'     => (string) $r['subject_name'],
                    'linked'      => ((int) $r['grade_id'] > 0 && (int) $r['subject_id'] > 0),
                    'at'          => (int) ($r['last_modified'] ?: $r['date_added']),
                    'note'        => '',
                );
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-CURRIC pending(course): ' . $e->getMessage());
        }

        /* ١ — دروس جديدة في `review` */
        try {
            $args  = array();
            $where = 'l.`tq_status` = "review"';
            if ($course > 0) { $where .= ' AND l.`course_id` = ?'; $args[] = $course; }

            $rows = $this->db->query(
                'SELECT l.`id`, l.`title`, l.`course_id`, l.`section_id`, l.`tq_status`,
                        l.`lesson_type`, l.`attachment_type`, l.`video_type`,
                        l.`duration`, l.`duration_sec`, l.`date_added`, l.`last_modified`,
                        c.`title` AS course_title, c.`status` AS course_status,
                        s.`title` AS section_title,
                        u.`first_name`, u.`last_name`, u.`email`,
                        (SELECT COUNT(*) FROM `objectives` o WHERE o.`lesson_id` = l.`id`) AS objectives
                   FROM `lesson` l
                   LEFT JOIN `course`  c ON c.`id` = l.`course_id`
                   LEFT JOIN `section` s ON s.`id` = l.`section_id`
                   LEFT JOIN `users`   u ON u.`id` = c.`creator`
                  WHERE ' . $where . '
                  ORDER BY COALESCE(l.`last_modified`, l.`date_added`) ASC, l.`id` ASC
                  LIMIT 300', $args
            )->result_array();

            foreach ($rows as $r) {
                $out[] = array(
                    'kind'        => 'new',
                    'entity'      => 'lesson',
                    'entity_id'   => (int) $r['id'],
                    'revision_id' => 0,
                    'title'       => (string) $r['title'],
                    'course_id'   => (int) $r['course_id'],
                    'course'      => (string) $r['course_title'],
                    'section'     => (string) $r['section_title'],
                    'author'      => trim((string) $r['first_name'] . ' ' . (string) $r['last_name']),
                    'tq_kind'     => self::kind_of($r),
                    'objectives'  => (int) $r['objectives'],
                    'duration'    => (string) $r['duration'],
                    'at'          => (int) ($r['last_modified'] ?: $r['date_added']),
                    'note'        => '',
                );
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-CURRIC pending(new): ' . $e->getMessage());
        }

        /* ٢ — اقتراحات على منشور */
        try {
            $args  = array();
            $where = 'r.`status` = "pending"';
            if ($course > 0) { $where .= ' AND r.`course_id` = ?'; $args[] = $course; }

            $rows = $this->db->query(
                'SELECT r.*, l.`title` AS live_title, l.`course_id` AS live_course,
                        c.`title` AS course_title, s.`title` AS section_title,
                        u.`first_name`, u.`last_name`
                   FROM `tq_content_revisions` r
                   LEFT JOIN `lesson`  l ON l.`id` = r.`entity_id` AND r.`entity` = "lesson"
                   LEFT JOIN `course`  c ON c.`id` = r.`course_id`
                   LEFT JOIN `section` s ON s.`id` = l.`section_id`
                   LEFT JOIN `users`   u ON u.`id` = r.`requested_by`
                  WHERE ' . $where . '
                  ORDER BY r.`requested_at` ASC
                  LIMIT 300', $args
            )->result_array();

            foreach ($rows as $r) {
                $payload = json_decode((string) $r['payload'], true);
                if (!is_array($payload)) $payload = array();
                $out[] = array(
                    'kind'        => 'edit',
                    'entity'      => (string) $r['entity'],
                    'entity_id'   => (int) $r['entity_id'],
                    'revision_id' => (int) $r['id'],
                    'title'       => (string) ($payload['title'] ?? $r['live_title']),
                    'live_title'  => (string) $r['live_title'],
                    'course_id'   => (int) $r['course_id'],
                    'course'      => (string) $r['course_title'],
                    'section'     => (string) $r['section_title'],
                    'author'      => trim((string) $r['first_name'] . ' ' . (string) $r['last_name']),
                    'tq_kind'     => self::kind_of($payload),
                    'payload'     => $payload,
                    'at'          => strtotime((string) $r['requested_at']),
                    'note'        => (string) $r['note'],
                );
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-CURRIC pending(edit): ' . $e->getMessage());
        }

        /* ٣ — كتب ينتظر نشرها — TQ-BOOK-REVIEW.
           والطابور واحد لا ثلاثة: المسؤول يفتح شاشة واحدة ويقرأ فيها كل
           ما ينتظر قراره — درسا واقتراحا وكورسا وكتابا. وطابور رابع في
           شاشة رابعة يعني كتابا يجلس في `review` إلى الأبد لأن أحدا لم
           يعرف أن له شاشة.
           والقرار في `Taqdar_book_model` لا هنا: الكتاب شأنه. */
        if ($course <= 0) {
            try {
                $CI = get_instance();
                $CI->load->model('taqdar_book_model', 'tq_bk_q');
                foreach ($CI->tq_bk_q->pending_books() as $r) $out[] = $r;
            } catch (Throwable $e) {
                log_message('error', 'TQ-CURRIC pending(book): ' . $e->getMessage());
            }
        }

        usort($out, function ($a, $b) { return $a['at'] <=> $b['at']; });
        return $out;
    }

    /** كم ينتظر — للشارة في الشريط. استعلامات تجمع، وترد صفرا عند العطل. */
    public function pending_count()
    {
        $n = 0;
        try {
            /* والكورس ينتظر كما ينتظر الدرس — TQ-COURSE-REVIEW.
               بلا هذا السطر تقول الشارة «لا شيء ينتظر» وكورس معلم
               حبيس `pending` منذ أسبوع. */
            $n += (int) $this->db->query(
                'SELECT COUNT(*) n FROM `course` WHERE `status` = "pending"')->row('n');
        } catch (Throwable $e) { /* الجدول لم يقرأ */ }
        try {
            $n += (int) $this->db->query(
                'SELECT COUNT(*) n FROM `lesson` WHERE `tq_status` = "review"')->row('n');
        } catch (Throwable $e) { /* العمود لم ينشأ */ }
        try {
            $n += (int) $this->db->query(
                'SELECT COUNT(*) n FROM `tq_content_revisions` WHERE `status` = "pending"')->row('n');
        } catch (Throwable $e) { /* الجدول لم ينشأ */ }
        try {
            /* والكتاب ينتظر كما ينتظر الكورس — TQ-BOOK-REVIEW. */
            $n += (int) $this->db->query(
                'SELECT COUNT(*) n FROM `books` WHERE `status` = "review"')->row('n');
        } catch (Throwable $e) { /* العمود لم يوسع بعد */ }
        return $n;
    }

    /**
     * يعتمد ما ينتظر — درسا جديدا أو اقتراحا على منشور.
     *
     * والإدارة وحدها: الاعتماد ليس تحريرا، هو الفصل بين من يكتب ومن
     * يقرر. ومعلم يعتمد لنفسه يجعل المراجعة اسما بلا معنى.
     */
    public function approve($actor, $entity, $entity_id, $revision_id = 0)
    {
        $this->install_schema();

        if ((isset($actor['role']) ? $actor['role'] : '') !== 'admin') {
            return $this->fail('الاعتماد قرار إدارة.');
        }

        $entity_id   = (int) $entity_id;
        $revision_id = (int) $revision_id;

        if ((string) $entity === 'course') return $this->approve_course($actor, $entity_id);

        /* TQ-BOOK-REVIEW — والكتاب يفوض إلى نموذجه: القاعدة هناك،
           والطابور هنا. ونسخة ثانية من قواعد الاعتماد هنا تفترق عن
           أختها عند أول تعديل. */
        if ((string) $entity === 'book') {
            $CI = get_instance();
            $CI->load->model('taqdar_book_model', 'tq_bk_ap');
            return $CI->tq_bk_ap->approve_book($actor, $entity_id);
        }

        /* اقتراح على منشور: الحمولة تطبق ثم يقفل الصف. */
        if ($revision_id > 0) {
            $rev = $this->db->where('id', $revision_id)->where('status', 'pending')
                            ->get('tq_content_revisions')->row_array();
            if (!$rev) return $this->fail('لا اقتراح معلق بهذا المعرف — لعله بت فيه.');

            $payload = json_decode((string) $rev['payload'], true);
            if (!is_array($payload) || !$payload) {
                return $this->fail('حمولة الاقتراح غير مقروءة.');
            }
            /* أعمدة الحالة لا تأتي من الحمولة أبدا: اقتراح يحمل
               `tq_status` ينشر نفسه بلا قرار. */
            unset($payload['tq_status'], $payload['tq_review_note'],
                  $payload['tq_reviewed_at'], $payload['tq_reviewed_by'],
                  $payload['id'], $payload['course_id']);

            $before = $this->lesson((int) $rev['entity_id']);
            $payload['last_modified'] = time();
            $this->db->where('id', (int) $rev['entity_id'])->update('lesson', $payload);

            $this->db->where('id', $revision_id)->update('tq_content_revisions', array(
                'status'     => 'approved',
                'decided_by' => (int) $actor['id'],
                'decided_at' => $this->now(),
            ));

            $this->log($actor, 'revision.approve', 'lesson:' . (int) $rev['entity_id'], $payload, $before);
            $this->notify_author((int) $rev['requested_by'], (int) $rev['entity_id'], true, '');

            return array('ok' => true, 'message' => 'اعتمد التعديل وطبق على الدرس المنشور.');
        }

        /* درس جديد: ينشر. */
        $row = $this->lesson($entity_id);
        if (!$row) return $this->fail('لا درس بهذا المعرف.');
        if ((string) $row['tq_status'] !== 'review') {
            return $this->fail('هذا الدرس ليس في انتظار المراجعة.');
        }

        $this->db->where('id', $entity_id)->update('lesson', array(
            'tq_status'      => 'published',
            'tq_review_note' => null,
            'tq_reviewed_at' => $this->now(),
            'tq_reviewed_by' => (int) $actor['id'],
            'last_modified'  => time(),
        ));

        $this->log($actor, 'lesson.approve', 'lesson:' . $entity_id, array('tq_status' => 'published'));
        $this->notify_author($this->author_of($entity_id), $entity_id, true, '');

        return array('ok' => true, 'message' => 'نشر الدرس. صار مرئيا لطلاب الكورس.');
    }

    /**
     * يرفض ما ينتظر، **بسببه**.
     *
     * والسبب مطلوب لا اختياري: «مرفوض» وحدها تعيد الدرس إلى صاحبه بلا
     * ما يفعله، فيعيد إرساله كما هو ويدور الاثنان.
     */
    public function reject($actor, $entity, $entity_id, $revision_id, $reason)
    {
        $this->install_schema();

        if ((isset($actor['role']) ? $actor['role'] : '') !== 'admin') {
            return $this->fail('الرفض قرار إدارة.');
        }

        $reason = $this->clean_title($reason);
        if ($this->len($reason) < 5) {
            return $this->fail('اكتب سبب الرفض — بلا سبب يعيد المعلم إرسال الدرس كما هو.');
        }
        $reason = $this->cut($reason, 500);

        $entity_id   = (int) $entity_id;
        $revision_id = (int) $revision_id;

        if ((string) $entity === 'course') return $this->reject_course($actor, $entity_id, $reason);

        if ((string) $entity === 'book') {
            $CI = get_instance();
            $CI->load->model('taqdar_book_model', 'tq_bk_rj');
            return $CI->tq_bk_rj->reject_book($actor, $entity_id, $reason);
        }

        if ($revision_id > 0) {
            $rev = $this->db->where('id', $revision_id)->where('status', 'pending')
                            ->get('tq_content_revisions')->row_array();
            if (!$rev) return $this->fail('لا اقتراح معلق بهذا المعرف.');

            $this->db->where('id', $revision_id)->update('tq_content_revisions', array(
                'status'       => 'rejected',
                'decided_note' => $reason,
                'decided_by'   => (int) $actor['id'],
                'decided_at'   => $this->now(),
            ));
            $this->log($actor, 'revision.reject', 'lesson:' . (int) $rev['entity_id'],
                       array('reason' => $reason));
            $this->notify_author((int) $rev['requested_by'], (int) $rev['entity_id'], false, $reason);

            return array('ok' => true, 'message' => 'رد التعديل إلى صاحبه. والدرس المنشور لم يمس.');
        }

        $row = $this->lesson($entity_id);
        if (!$row) return $this->fail('لا درس بهذا المعرف.');

        $this->db->where('id', $entity_id)->update('lesson', array(
            'tq_status'      => 'rejected',
            'tq_review_note' => $reason,
            'tq_reviewed_at' => $this->now(),
            'tq_reviewed_by' => (int) $actor['id'],
        ));

        $this->log($actor, 'lesson.reject', 'lesson:' . $entity_id, array('reason' => $reason));
        $this->notify_author($this->author_of($entity_id), $entity_id, false, $reason);

        return array('ok' => true, 'message' => 'رد الدرس إلى صاحبه مع السبب.');
    }

    /**
     * اعتماد كورس ينتظر — TQ-COURSE-REVIEW.
     *
     * وثلاثة أشياء تقع معا، وترك أيها يجعل الاعتماد نصفه:
     *
     * ١ — `course.status = 'active'`.
     * ٢ — **البرنامج يتبع**: `Taqdar_course_link_model::sync()` هو
     *     الجسر الوحيد إلى الكتالوج ومحرك الاشتراكات، وبلا استدعائه
     *     يصير الكورس منشورا وبرنامجه مسودة — فلا يظهر ولا تفتحه باقة،
     *     وهو عين العطل الذي جاء TQ-COURSE-SPLIT ليغلقه.
     * ٣ — **يبلغ من يملك نطاقه الآن**: `sync()` تنادي `resync_scope()`
     *     فيجسد للمشتركين القائمين (TQ-ENROL-STALE).
     *
     * ودروس الكورس لا تنشر معه: لكل درس قراره، وكورس فيه عشرون درسا
     * ينشر بضغطة واحدة يعني عشرين درسا لم يقرأها أحد.
     */
    private function approve_course($actor, $course_id)
    {
        $row = $this->db->select('id, title, status, creator')
                        ->where('id', (int) $course_id)->get('course')->row_array();
        if (!$row) return $this->fail('لا كورس بهذا المعرف.');
        if ((string) $row['status'] !== 'pending') {
            return $this->fail('هذا الكورس ليس في انتظار المراجعة — حالته «'
                             . $this->status_label((string) $row['status']) . '».');
        }

        $this->db->where('id', (int) $course_id)
                 ->update('course', array('status' => 'active', 'last_modified' => time()));

        $sync = null;
        try {
            $CI = get_instance();
            $CI->load->model('taqdar_course_link_model', 'tq_link_m');
            $link = $CI->tq_link_m->link_of((int) $course_id);
            $sync = $CI->tq_link_m->sync((int) $course_id,
                                         (int) $link['grade_id'], (int) $link['subject_id']);
        } catch (Throwable $e) {
            log_message('error', 'TQ-COURSE-REVIEW sync: ' . $e->getMessage());
        }

        $this->log($actor, 'course.approve', 'course:' . (int) $course_id,
                   array('status' => 'active', 'sync' => $sync));
        $this->notify_course_author((int) $row['creator'], (int) $course_id,
                                    (string) $row['title'], true, '');

        $msg = 'نشر الكورس «' . $row['title'] . '».';
        if (is_array($sync) && !empty($sync['reached'])) {
            $msg .= ' وفتح لـ' . (int) $sync['reached'] . ' اشتراكا قائما في نطاقه.';
        }
        if (empty($link['grade_id']) || empty($link['subject_id'])) {
            $msg .= ' وهو بلا صف أو مادة، فلا يظهر في «المواد والبرامج» ولا تفتحه باقة'
                  . ' — أكملهما من تبويب «الأساسيات».';
        }
        return array('ok' => true, 'message' => $msg);
    }

    /**
     * رد كورس إلى صاحبه بسببه.
     *
     * وينزل إلى `draft` لا إلى `rejected`: `course.status` ليس فيه هذه
     * الحالة (انظر `course_statuses()`)، والمسودة هي ما يستطيع صاحبها
     * أن يعدله ثم يعيد إرساله. والسبب يبلغه إشعارا لأن الكورس — خلاف
     * الدرس — بلا عمود يحمل ملاحظة المراجعة.
     */
    private function reject_course($actor, $course_id, $reason)
    {
        $row = $this->db->select('id, title, status, creator')
                        ->where('id', (int) $course_id)->get('course')->row_array();
        if (!$row) return $this->fail('لا كورس بهذا المعرف.');

        $this->db->where('id', (int) $course_id)
                 ->update('course', array('status' => 'draft', 'last_modified' => time()));

        /* والبرنامج يتبع الحالة: كورس رد إلى صاحبه لا يبقى برنامجه
           معروضا في الكتالوج. */
        try {
            $CI = get_instance();
            $CI->load->model('taqdar_course_link_model', 'tq_link_m');
            $link = $CI->tq_link_m->link_of((int) $course_id);
            $CI->tq_link_m->sync((int) $course_id,
                                 (int) $link['grade_id'], (int) $link['subject_id']);
        } catch (Throwable $e) {
            log_message('error', 'TQ-COURSE-REVIEW sync(reject): ' . $e->getMessage());
        }

        $this->log($actor, 'course.reject', 'course:' . (int) $course_id,
                   array('reason' => $reason));
        $this->notify_course_author((int) $row['creator'], (int) $course_id,
                                    (string) $row['title'], false, $reason);

        return array('ok' => true, 'message' => 'رد الكورس إلى صاحبه مع السبب، وصار مسودة عنده.');
    }

    /** اسم الحالة كما يقرؤها إنسان. */
    private function status_label($key)
    {
        $m = self::course_statuses();
        return isset($m[$key]) ? $m[$key][0] : $key;
    }

    /**
     * يخبر صاحب الكورس بالقرار.
     *
     * والباب واحد (`Taqdar_admin_model::push_notification`) كما لإشعار
     * الدرس: يكتب الصف أولا ثم يرسل، وفشل القناة لا يبطل القرار.
     */
    private function notify_course_author($teacher_id, $course_id, $title, $ok, $reason)
    {
        if ((int) $teacher_id <= 0) return;
        try {
            $CI = get_instance();
            $CI->load->model('taqdar_admin_model', 'tq_admin_m');
            if (!method_exists($CI->tq_admin_m, 'push_notification')) return;

            /* النوع `content` لا `payment`: `Taqdar_wa_model::$PAY_TYPES`
               تسمي أنواع المال وحدها، وما سواها لا يخرج بواتساب — وقرار
               مراجعة ليس مالا. */
            $CI->tq_admin_m->push_notification(
                (int) $teacher_id,
                $ok ? 'اعتمد كورسك ونشر' : 'كورسك يحتاج تعديلا',
                $ok
                    ? 'اعتمدت الإدارة كورس «' . $title . '» وصار منشورا،'
                      . ' ودروسه المعتمدة تفتح لطلابه.'
                    : 'رد كورس «' . $title . '» إليك للتعديل، وصار مسودة عندك.'
                      . ' السبب: ' . $reason,
                'content'
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-COURSE-REVIEW notify: ' . $e->getMessage());
        }
    }

    /** الاقتراح المعلق على درس — تعرضه شاشة المعلم فيعرف أنه ينتظر. */
    public function pending_revision($entity, $entity_id)
    {
        try {
            $r = $this->db->where('entity', (string) $entity)
                          ->where('entity_id', (int) $entity_id)
                          ->where('status', 'pending')
                          ->get('tq_content_revisions')->row_array();
            if (!$r) return null;
            $r['payload'] = json_decode((string) $r['payload'], true) ?: array();
            return $r;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** آخر قرار رفض على درس — ليقرأه صاحبه في شاشته. */
    public function last_rejection($lesson_id)
    {
        $lesson_id = (int) $lesson_id;
        $row = $this->lesson($lesson_id);
        if ($row && (string) $row['tq_status'] === 'rejected' && $row['tq_review_note']) {
            return array('reason' => (string) $row['tq_review_note'],
                         'at'     => (string) $row['tq_reviewed_at']);
        }
        try {
            $r = $this->db->where('entity', 'lesson')->where('entity_id', $lesson_id)
                          ->where('status', 'rejected')
                          ->order_by('decided_at', 'DESC')->limit(1)
                          ->get('tq_content_revisions')->row_array();
            return $r ? array('reason' => (string) $r['decided_note'],
                              'at'     => (string) $r['decided_at']) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** صاحب الدرس — منشئ كورسه. */
    private function author_of($lesson_id)
    {
        return (int) $this->db->query(
            'SELECT c.`creator` FROM `lesson` l JOIN `course` c ON c.`id` = l.`course_id`
              WHERE l.`id` = ? LIMIT 1', array((int) $lesson_id))->row('creator');
    }

    /**
     * يخبر صاحب الدرس بالقرار.
     *
     * والباب واحد: `Taqdar_admin_model::push_notification()` — يكتب في
     * `notifications` ثم يرسل البريد. والقناة تابعة لا شرط: قرار لا
     * يسجل لأن رسالة لم تخرج قرار ضائع.
     */
    private function notify_author($user_id, $lesson_id, $approved, $reason)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return;

        try {
            $title = (string) $this->db->select('title')->where('id', (int) $lesson_id)
                                       ->get('lesson')->row('title');

            $subject = $approved ? 'اعتمد درسك ونشر' : 'درسك يحتاج تعديلا';
            $body    = $approved
                ? 'اعتمدت الإدارة درس «' . $title . '» وصار مرئيا لطلاب الكورس.'
                : 'رد درس «' . $title . '» إليك للتعديل. السبب: ' . $reason;

            $this->load->model('taqdar_admin_model');
            /* النوع `content` لا `payment`: `Taqdar_wa_model::$PAY_TYPES`
               تسمي أنواع المال وحدها، وما سواها لا يخرج بواتساب — وقرار
               مراجعة ليس مالا. */
            $this->taqdar_admin_model->push_notification(
                $user_id, $subject, $body, 'content'
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-CURRIC notify_author: ' . $e->getMessage());
        }
    }

    /** يخبر الإدارة أن شيئا ينتظر — بلا إغراق: إشعار واحد لكل كورس. */
    private function notify_reviewers($course_id, $entity, $entity_id)
    {
        /* لا إشعار لكل حرف يعدل: الشارة في الشريط تكفي، وشاشة المراجعة
           تقرأ من القاعدة مباشرة. وإشعار لكل حفظ يجعل المسؤول يطفئ
           الإشعارات كلها. */
        return;
    }

    private function cut($s, $n)
    {
        $s = trim((string) $s);
        if ($s === '') return null;
        return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n);
    }

    /* =====================================================================
       أدوات داخلية
       ===================================================================== */

    private function status_message($status)
    {
        switch ($status) {
            case 'published': return 'حفظ الدرس ونشر.';
            case 'review':    return 'حفظ الدرس وأرسل للمراجعة.';
            case 'rejected':  return 'حفظ الدرس.';
            default:          return 'حفظ الدرس مسودة.';
        }
    }

    /** ينظف HTML وارد: يزيل السكربت وما يشبهه ويبقي الوسم. */
    private function clean_html($raw, $rich = false)
    {
        $s = (string) $raw;
        if ($s === '') return '';
        if (function_exists('remove_js')) $s = remove_js($s);
        /* `on…=` تبقى بعد `remove_js` في بعض الصيغ. */
        $s = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $s);
        $s = preg_replace('#\sjavascript\s*:#i', ' ', $s);
        return $rich ? $s : trim(strip_tags($s));
    }

    /** هل تجاوز الطلب `post_max_size` فأفرغ PHP كل شيء؟ */
    private function post_overflowed()
    {
        return strtoupper((string) $this->input->server('REQUEST_METHOD')) === 'POST'
            && (int) $this->input->server('CONTENT_LENGTH') > 0;
    }

    /** حذف من جدول قد لا يكون موجودا — يبتلع الاستثناء. */
    private function safe_delete($table, $col, $val)
    {
        try {
            if ($this->db->table_exists($table)) {
                $this->db->where($col, $val)->delete($table);
            }
        } catch (Throwable $e) { /* الجدول لم ينشأ بعد */ }
    }

    /** ينزع درسا محذوفا من سجلات المشاهدة — لكل الطلاب لا للفاعل وحده. */
    private function clear_watch_history($lesson_id, $course_id)
    {
        try {
            $this->db->where('watching_lesson_id', (int) $lesson_id)
                     ->update('watch_histories', array('watching_lesson_id' => null));

            $rows = $this->db->select('id, completed_lesson')
                             ->where('course_id', (int) $course_id)
                             ->get('watch_histories')->result_array();
            foreach ($rows as $r) {
                $list = json_decode((string) $r['completed_lesson'], true);
                if (!is_array($list)) continue;
                $next = array_values(array_filter($list, function ($v) use ($lesson_id) {
                    return (int) $v !== (int) $lesson_id;
                }));
                if (count($next) === count($list)) continue;
                $this->db->where('id', (int) $r['id'])
                         ->update('watch_histories', array('completed_lesson' => json_encode($next)));
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-CURRIC watch: ' . $e->getMessage());
        }
    }

    private function fail($errors, $extra = array())
    {
        if (!is_array($errors)) $errors = array($errors);
        return array_merge(array('ok' => false, 'errors' => $errors,
                                 'message' => implode(' ', $errors)), $extra);
    }

    private function val($arr, $key, $default = '')
    {
        return (is_array($arr) && array_key_exists($key, $arr) && $arr[$key] !== null)
            ? $arr[$key] : $default;
    }

    private function len($s)
    {
        return function_exists('mb_strlen') ? mb_strlen((string) $s, 'UTF-8') : strlen((string) $s);
    }

    private function clean_title($raw)
    {
        $t = trim(preg_replace('/\s+/u', ' ', (string) $raw));
        return $t;
    }

    private function now() { return date('Y-m-d H:i:s'); }

    /** الترتيب التالي في مجموعة — والصف الجديد يذهب إلى آخرها. */
    private function next_order($table, $scope_col, $scope_id)
    {
        $max = (int) $this->db->select_max('`order`', 'mx')
                              ->where($scope_col, (int) $scope_id)
                              ->get($table)->row('mx');
        return $max + 1;
    }

    /**
     * يحدث `course.section` — عمود JSON موروث يسرد معرفات الأقسام.
     *
     * لا يقرأ منه شيء في طبقة تقدر، لكن شاشات Academy تقرؤه؛ وتركه
     * بائتا يعني أن حذف قسم من الشاشة الجديدة يبقيه في القديمة.
     * ويكتب من `section` نفسها لا من العمود القديم: قراءته وتعديله
     * تنقل عطبه إن كان معطوبا.
     */
    private function sync_legacy_section_json($course_id)
    {
        try {
            $ids = array();
            foreach ($this->sections_of($course_id) as $s) $ids[] = (int) $s['id'];
            $this->db->where('id', (int) $course_id)
                     ->update('course', array('section' => json_encode($ids)));
        } catch (Throwable $e) {
            log_message('error', 'TQ-CURRIC: تعذر تحديث course.section — ' . $e->getMessage());
        }
    }

    /**
     * سجل التدقيق — ويبتلع عطله: تحرير المنهج لا يوقف لأن سطرا لم يكتب.
     *
     * والعمودان `before` و`after` عليهما `CHECK (json_valid(...))` في
     * المخطط، فالنص العادي يرفض والفارغ يجب أن يكون `NULL` لا `''`.
     */
    private function log($actor, $action, $entity, $after = null, $before = null)
    {
        try {
            $this->db->insert('audit_log', array(
                'actor_id' => (int) (isset($actor['id']) ? $actor['id'] : 0),
                'action'   => $action,
                'entity'   => $entity,
                'before'   => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
                'after'    => $after  === null ? null : json_encode($after,  JSON_UNESCAPED_UNICODE),
                'ip'       => $this->input->ip_address(),
                'at'       => $this->now(),
            ));
        } catch (Throwable $e) {
            log_message('error', 'TQ-CURRIC audit: ' . $e->getMessage());
        }
    }
}
