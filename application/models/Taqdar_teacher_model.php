<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * محرك بوابة المعلم — كتابة الدروس.
 *
 * القاعدة الحاكمة: **النطاق يفرض هنا، في طبقة الاستعلام، لا في الواجهة.**
 * المعلم يملك الكورس إما لأنه منشئه (`course.creator`) وإما لأنه ضمن
 * قائمة معلميه (`FIND_IN_SET(uid, course.user_id)`) — وهذا هو نموذج
 * النطاق الفعلي في هذا السكربت. فمن غير قيمة `course_id` في المتصفح
 * لا يكتب في كورس ليس له، لأن الملكية تتحقق قبل أي كتابة وقبل نقل
 * أي ملف مرفوع، لا بعده.
 *
 * والملف المرفوع يمر من `tq_safe_upload_extension()` وحدها: قائمة بيضاء
 * واحدة في المنصة كلها، فلا تتفرق القوائم وتنسى صيغة في إحداها.
 * والاسم المخزن يولد هنا ولا يشتق من اسم المستخدم أصلا، فلا مسار
 * يخترق ولا امتداد مزدوج يمر.
 *
 * عقد الاستدعاء من المتحكم (POST teacher/upload/save):
 *
 *     save_upload($teacher_id, $payload, $_FILES)  ← ما ينادي عليه Taqdar::upload_save
 *     save_lesson($teacher_id, $post, $files)      ← التنفيذ، ويصلح للنداء المباشر
 *
 * وكلاهما يعيد العقد الموحد نفسه: `ok` ومعه `message`، أو `errors` ومعها
 * `old` لإعادة ملء النموذج. ولا يطبع شيئا ولا يحول — العرض والتحويل قرار
 * المتحكم.
 *
 * و`save_upload` تقرأ POST الخام وتستعمل حمولة المتحكم لسد ما نقص منها
 * فقط. لولا ذلك لسقط ما لا تعرفه الحمولة — نوع الدرس ومصدر المقطع ورابطه —
 * فيحفظ درس مرئي بلا مقطع، وهو أسوأ من رفض صريح.
 *
 * والعرض `tq_teacher_upload.php` يقرأ `tq_upload_errors` و`tq_upload_old`
 * من flashdata إن ضبطهما المتحكم، وإلا اكتفى بـ `error_message`.
 */
class Taqdar_teacher_model extends CI_Model
{
    /**
     * حد المدة الصلب. القاعدة التربوية للمنصة «من 8 إلى 15 دقيقة» تبقى
     * إرشادا ظاهرا في النموذج، لكن الخادم لا يرفض درسا مشروعا لأنه بلغ
     * السادسة عشرة: منع المحتوى أسوأ من درس أطول بدقيقة.
     */
    const MIN_MINUTES = 1;
    const MAX_MINUTES = 180;

    /** من هدف إلى ثلاثة لكل درس — والرابع يعني درسا ثانيا. */
    const MAX_OBJECTIVES = 3;

    /**
     * مجلد ملفات الدروس.
     *
     * لا `uploads/lesson_files/` رغم أنه مجلد Academy التاريخي: ملف
     * `.htaccess` هناك يمنع الوصول منعا كاملا (`Require all denied`)،
     * فالمقطع المرفوع إليه لا يشغل في المتصفح أصلا. و`resource_files`
     * يمنع تنفيذ السكربتات ويخدم الساكن — وهو ما يحتاجه مشغل الدرس.
     */
    const UPLOAD_DIR = 'uploads/resource_files/tq_lessons/';

    /** حالات الدرس المعروفة. */
    private $statuses = array('draft', 'review', 'published');

    /* =====================================================================
       القراءة — كلها مقيدة بملكية المعلم
       ===================================================================== */

    /** كورسات المعلم: ما أنشأه أو ما أسند إليه. */
    /* =====================================================================
       مسار الانضمام — العينة ولجنة التحكيم
       =====================================================================

       فلو المعلم في وثيقة المنتج ثلاث خطوات قبل أن يفتح له شيء:

         ١ — تقديم طلب انضمام (بيانات **+ عينة شرح 10 دقائق**)
         ٢ — **مراجعة لجنة تحكيم داخلية** ← توثيق الهوية والمؤهل
         ٣ — الإدارة تسند مادة + صف ← يقفل نطاق لوحته

       والمنفذ منها كان الثالثة وحدها (`teacher_assignments` قائم ويقيد
       الاستعلام)، ونصف الأولى (مستند مؤهل ونبذة، بلا عينة). أما اللجنة
       فلم توجد: قرار مسؤول واحد اعتماد أو رفض.

       واللجنة هنا **ليست شاشة ثانية** بل جدول أصوات: كل محكم يسجل رأيه
       ومعه ملاحظته، والاعتماد يشترط نصابا (`tq_teacher_quorum`، افتراضه
       اثنان). فالقرار يبقى في الشاشة نفسها التي يعرفها المسؤول، ويصير
       له سند مكتوب يراجع — وهذا هو الفرق العملي بين «لجنة» و«زر». */

    public function ensure_apply_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        if (!$this->db->table_exists('applications')) return;

        $have = $this->db->list_fields('applications');
        $add  = array(
            'sample_url'   => 'ADD COLUMN `sample_url` VARCHAR(500) NULL DEFAULT NULL',
            'sample_note'  => 'ADD COLUMN `sample_note` VARCHAR(500) NULL DEFAULT NULL',
            'identity_ok'  => 'ADD COLUMN `identity_ok` TINYINT(1) NOT NULL DEFAULT 0',
            'subject_hint' => 'ADD COLUMN `subject_hint` VARCHAR(190) NULL DEFAULT NULL',
        );

        $sql = array();
        foreach ($add as $col => $clause) {
            if (!in_array($col, $have, true)) $sql[] = $clause;
        }
        if ($sql) {
            $this->db->query('ALTER TABLE `applications` ' . implode(', ', $sql));
            // CI يخبئ أسماء الأعمدة في الطلب الواحد، فقراءتها بعد التعديل بائتة
            $this->db->data_cache = array();
        }

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_app_review` (
               `id`          INT(11)      NOT NULL AUTO_INCREMENT,
               `app_id`      INT(11)      NOT NULL,
               `reviewer_id` INT(11)      NOT NULL,
               `verdict`     VARCHAR(12)  NOT NULL,
               `note`        VARCHAR(500) NULL,
               `created_at`  DATETIME     NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_vote` (`app_id`,`reviewer_id`),
               KEY `ix_app` (`app_id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /** نصاب اللجنة — من `settings` لا من الشيفرة. */
    public function quorum()
    {
        $row = $this->db->select('value')->where('key', 'tq_teacher_quorum')
                        ->get('settings')->row_array();
        $n = $row ? (int) $row['value'] : 0;
        return ($n >= 1 && $n <= 9) ? $n : 2;
    }

    /** أصوات اللجنة على طلب. */
    public function reviews_of($app_id)
    {
        $this->ensure_apply_schema();
        try {
            return $this->db->select('r.*, TRIM(CONCAT(COALESCE(u.first_name,""), " ",
                                       COALESCE(u.last_name,""))) AS reviewer', false)
                            ->from('tq_app_review r')
                            ->join('users u', 'u.id = r.reviewer_id', 'left')
                            ->where('r.app_id', (int) $app_id)
                            ->order_by('r.id', 'ASC')->get()->result_array();
        } catch (Throwable $e) { return array(); }
    }

    /**
     * تصويت محكم. صوت واحد لكل محكم على كل طلب — و`uq_vote` يفرضه في
     * القاعدة لا في شرط هنا، فالتصويت مرتين لا يقع بسباق نقرتين.
     * وتغيير الرأي مسموح: يستبدل الصوت ولا يضاف ثان.
     */
    public function cast_vote($app_id, $reviewer_id, $verdict, $note = '')
    {
        $this->ensure_apply_schema();
        $verdict = in_array($verdict, array('approve', 'reject'), true) ? $verdict : '';
        if ($verdict === '') return array('ok' => false, 'message' => 'رأي غير معروف.');

        if ($verdict === 'reject' && trim((string) $note) === '') {
            /* الرفض بسبب مكتوب — كما في رفض المحتوى. ورفض بلا سبب لا
               يعلم صاحبه ماذا يصلح، ولا يعلم بقية اللجنة لماذا خالف. */
            return array('ok' => false, 'message' => 'اكتب سبب الرفض قبل تسجيله.');
        }

        $this->db->query(
            'INSERT INTO `tq_app_review` (`app_id`,`reviewer_id`,`verdict`,`note`,`created_at`)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE `verdict` = VALUES(`verdict`), `note` = VALUES(`note`),
                                     `created_at` = VALUES(`created_at`)',
            array((int) $app_id, (int) $reviewer_id, $verdict,
                  mb_substr(trim((string) $note), 0, 500) ?: null, date('Y-m-d H:i:s')));

        $t = $this->tally($app_id);
        return array('ok' => true, 'tally' => $t,
            'message' => 'سجل رأيك. الموافقات ' . $t['approve'] . ' والاعتراضات ' . $t['reject']
                       . ' والنصاب ' . $t['quorum'] . '.');
    }

    /** حصيلة الأصوات، ومعها هل بلغ النصاب. */
    public function tally($app_id)
    {
        $rows = $this->reviews_of($app_id);
        $a = 0; $r = 0;
        foreach ($rows as $v) {
            if ($v['verdict'] === 'approve') $a++;
            elseif ($v['verdict'] === 'reject') $r++;
        }
        $q = $this->quorum();

        return array(
            'approve'      => $a,
            'reject'       => $r,
            'quorum'       => $q,
            'votes'        => $rows,
            'may_approve'  => $a >= $q,
            'may_reject'   => $r >= 1,   // اعتراض واحد يكفي لعرض زر الرفض
            'blocked'      => $a < $q,
        );
    }

    public function my_courses($teacher_id)
    {
        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return array();

        return $this->db->query(
            'SELECT `id`, `title`, `status` FROM `course`
              WHERE `creator` = ? OR FIND_IN_SET(?, `user_id`) > 0
              ORDER BY `title` ASC',
            array($teacher_id, $teacher_id)
        )->result_array();
    }

    /**
     * هل يملك المعلم هذا الكورس؟ سؤال واحد بجواب واحد، ويسأل قبل كل كتابة.
     * وجوده دالة مستقلة مقصود: تكرار الشرط في كل موضع هو كيف ينسى في موضع.
     */
    public function owns_course($teacher_id, $course_id)
    {
        $teacher_id = (int) $teacher_id;
        $course_id  = (int) $course_id;
        if ($teacher_id <= 0 || $course_id <= 0) return false;

        $row = $this->db->query(
            'SELECT `id` FROM `course`
              WHERE `id` = ? AND (`creator` = ? OR FIND_IN_SET(?, `user_id`) > 0)
              LIMIT 1',
            array($course_id, $teacher_id, $teacher_id)
        )->row_array();

        return (bool) $row;
    }

    /** أقسام مجموعة كورسات، مجمعة بالكورس. */
    public function sections_of_courses($course_ids)
    {
        $ids = array();
        foreach ((array) $course_ids as $c) {
            $c = (int) $c;
            if ($c > 0) $ids[] = $c;
        }
        if (!$ids) return array();

        $rows = $this->db->query(
            'SELECT `id`, `title`, `course_id` FROM `section`
              WHERE `course_id` IN (' . implode(',', $ids) . ')
              ORDER BY `order` ASC, `id` ASC'
        )->result_array();

        $out = array();
        foreach ($rows as $r) $out[(int) $r['course_id']][] = $r;
        return $out;
    }

    /** آخر ما رفعه المعلم — من جدول الدروس الحقيقي، مقيدا بكورساته. */
    public function recent_lessons($teacher_id, $limit = 5)
    {
        $teacher_id = (int) $teacher_id;
        $limit      = (int) $limit;
        if ($teacher_id <= 0 || $limit <= 0) return array();

        $status_col = $this->has_status_column() ? 'l.`tq_status`' : "'published'";

        return $this->db->query(
            'SELECT l.`id`, l.`title`, l.`duration`, l.`date_added`, l.`lesson_type`,
                    ' . $status_col . ' AS tq_status,
                    c.`title` AS course_title, c.`id` AS course_id
               FROM `lesson` l
               JOIN `course` c ON c.`id` = l.`course_id`
              WHERE c.`creator` = ? OR FIND_IN_SET(?, c.`user_id`) > 0
              ORDER BY l.`date_added` DESC, l.`id` DESC
              LIMIT ' . $limit,
            array($teacher_id, $teacher_id)
        )->result_array();
    }

    /**
     * كل دروس المعلم — لا آخر خمسة.
     *
     * كانت `recent_lessons()` كل ما يراه المعلم من دروسه: خمسة أسطر في
     * زاوية شاشة الرفع. فمعلم رفع أربعين درسا لا يملك في بوابته قائمة
     * يجدها فيها، ولا يعرف أي درس بقي مسودة، ولا أي وحدة نقصت درسا.
     * وشاشة «كورساتي» تعطيه رقما مجملا («٢٤ درسا») لا يفتح على شيء.
     *
     * النطاق هنا هو نطاق `my_courses()` نفسه: ما أنشأه المعلم أو أسند
     * إليه. والتصفية تضاف إلى شرط الملكية لا تحل محله، فمعرف كورس يخمن
     * في الرابط لا يرد صفا واحدا من كورس غيره.
     *
     * @param int   $teacher_id
     * @param array $f قد يحمل: course (int) · status (string) · type (string) · q (string)
     */
    public function lessons_of($teacher_id, $f = array())
    {
        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return array();

        $status_col = $this->has_status_column() ? 'l.`tq_status`' : "'published'";

        $sql = 'SELECT l.`id`, l.`title`, l.`duration`, l.`date_added`, l.`lesson_type`,
                       l.`is_free`, l.`order` AS lesson_order, l.`attachment`, l.`video_url`,
                       ' . $status_col . ' AS tq_status,
                       c.`id` AS course_id, c.`title` AS course_title, c.`status` AS course_status,
                       s.`id` AS section_id, s.`title` AS section_title,
                       (SELECT COUNT(*) FROM `question` q WHERE q.`quiz_id` = l.`id`) AS questions,
                       -- TQ-EXAM-SOURCE: `question.quiz_id` أعلاه يشير إلى
                       -- درس نوعه اختبار في النظام الموروث، وقد هجر —
                       -- واختبار الدرس اليوم تقييم `review` وأسئلته في
                       -- `question.assessment_id`. فكان العد يقرأ صفرا على
                       -- درس له اختبار بخمسة أسئلة، وتقول الشاشة
                       -- «اختباراتك ٠» لمعلم ألف اثنين.
                       (SELECT COUNT(*) FROM `question` q2
                          JOIN `assessments` a2 ON a2.`id` = q2.`assessment_id`
                         WHERE a2.`lesson_id` = l.`id` AND a2.`type` = "review") AS quiz_questions
                  FROM `lesson` l
                  JOIN `course` c ON c.`id` = l.`course_id`
             LEFT JOIN `section` s ON s.`id` = l.`section_id`
                 WHERE (c.`creator` = ? OR FIND_IN_SET(?, c.`user_id`) > 0)';
        $args = array($teacher_id, $teacher_id);

        $course = isset($f['course']) ? (int) $f['course'] : 0;
        if ($course > 0) { $sql .= ' AND c.`id` = ?'; $args[] = $course; }

        $status = isset($f['status']) ? (string) $f['status'] : '';
        if ($status !== '' && $this->has_status_column()) {
            $sql .= ' AND l.`tq_status` = ?'; $args[] = $status;
        }

        $type = isset($f['type']) ? (string) $f['type'] : '';
        if ($type === 'quiz')          { $sql .= ' AND l.`lesson_type` = ?';  $args[] = 'quiz'; }
        elseif ($type === 'lesson')    { $sql .= ' AND l.`lesson_type` != ?'; $args[] = 'quiz'; }

        /* البحث يمر بـ`escape_like_str` ثم يربط قيمة: النجمة والشرطة
           السفلية في مدخل المعلم حروف بحث لا رموز نمط. */
        $q = isset($f['q']) ? trim((string) $f['q']) : '';
        if ($q !== '') {
            $like = '%' . $this->db->escape_like_str(mb_substr($q, 0, 80)) . '%';
            $sql .= ' AND (l.`title` LIKE ? ESCAPE \'!\' OR s.`title` LIKE ? ESCAPE \'!\')';
            $args[] = $like; $args[] = $like;
        }

        $sql .= ' ORDER BY c.`date_added` DESC, c.`id` DESC, s.`order` ASC, s.`id` ASC,
                           l.`order` ASC, l.`id` ASC';

        return $this->db->query($sql, $args)->result_array();
    }

    /* =====================================================================
       الكتابة
       ===================================================================== */

    /**
     * مدخل المتحكم: `Taqdar::upload_save` ينادي هذه بالحمولة و`$_FILES`.
     *
     * الحمولة تسد الثغرات ولا تطمس POST: هي مشتقة منه أصلا، وما ليس فيها
     * (نوع الدرس، مصدر المقطع، الرابط، المجانية) موجود في POST. والتحقق
     * كله يعاد في `save_lesson` على أي حال، فلا يعتمد على تنظيف سابق.
     */
    public function save_upload($teacher_id, $payload = null, $files = null)
    {
        $post = $this->input->post();
        if (!is_array($post)) $post = array();

        if (is_array($payload)) {
            foreach ($payload as $k => $v) {
                $missing = !array_key_exists($k, $post)
                    || $post[$k] === '' || $post[$k] === null || $post[$k] === array();
                if ($missing) $post[$k] = $v;
            }
        }

        return $this->save_lesson($teacher_id, $post, $files);
    }

    /** اسم بديل للمدخل نفسه — المتحكم يجرب الاسمين. */
    public function upload_save($teacher_id, $payload = null, $files = null)
    {
        return $this->save_upload($teacher_id, $payload, $files);
    }

    /**
     * يحفظ درسا للمعلم.
     *
     * يعيد دائما مصفوفة: `ok` وإما `lesson_id` و`message`، وإما `errors`
     * ومعها `old` لإعادة ملء النموذج. لا يرمي استثناء ولا يطبع شيئا،
     * فالعرض والتحويل قرار المتحكم لا قرار النموذج.
     *
     * @param int        $teacher_id
     * @param array|null $post   افتراضه $this->input->post()
     * @param array|null $files  افتراضه $_FILES
     */
    /**
     * @deprecated TQ-UPLOAD-FOLD — استعمل `Taqdar_curriculum_model::save_lesson()`.
     *
     * هذه هي النسخة الثانية من كاتب الدروس، بجوار `Taqdar_curriculum_model`.
     * وافترقت عنه في كل ما يهم:
     *
     *   • نوعان (`video` · `text`) مقابل **عشرة** في الوصف الموحد
     *   • المدة عددا صحيحا بالدقائق، فمقطع `00:02:49` لا يعبر عنه أصلا
     *   • **لا تكتب `duration_sec`** وهو مقام نسبة التقدم وأساس القفل
     *   • قاعدة نشر ثالثة (`course.status === 'active'`) بدل `may_publish()`
     *   • `html_escape()` قبل الإدراج، فتخزن الكيانات في القاعدة
     *   • إدراج فقط: لا تحرير، فلا `tq_content_revisions`
     *
     * و`Taqdar::upload_save()` صارت تفوض إلى الطبقة. وتبقى هذه لئلا يرد
     * 404 على نموذج محفوظ أو نداء قديم — كما بقيت أختها `save_course()`.
     */
    public function save_lesson($teacher_id, $post = null, $files = null)
    {
        if ($post === null)  $post  = $this->input->post();
        if ($files === null) $files = isset($_FILES) ? $_FILES : array();
        if (!is_array($post))  $post  = array();
        if (!is_array($files)) $files = array();

        $teacher_id = (int) $teacher_id;

        /* الطلب تجاوز post_max_size: PHP يفرغ POST و FILES معا، فلولا هذا
           الفحص لظهرت للمعلم رسالة «العنوان مطلوب» وهو قد كتبه فعلا. */
        if (!$post && !$files && $this->post_overflowed()) {
            return $this->fail(array(
                'الملف أكبر من حد الرفع على الخادم (' . ini_get('post_max_size') . ') فلم يصل الطلب أصلا.'
            ), array());
        }

        $old = $this->old_input($post);

        if ($teacher_id <= 0) {
            return $this->fail(array('لا يمكن تحديد المعلم. سجل الدخول من جديد.'), $old);
        }

        $errors = array();

        /* ---- الملكية أولا: قبل أي تحقق آخر وقبل لمس أي ملف ---- */
        $course_id = (int) $this->val($post, 'course_id');
        if ($course_id <= 0) {
            $errors[] = 'اختر الكورس.';
        } elseif (!$this->owns_course($teacher_id, $course_id)) {
            $errors[] = 'هذا الكورس ليس ضمن كورساتك، فلا يمكن الرفع إليه.';
            $course_id = 0;   // لا يستعمل بعدها في أي استعلام
        }

        /* ---- القسم: إن حدد فليكن من هذا الكورس بعينه ---- */
        $section_id = (int) $this->val($post, 'section_id');
        if ($course_id > 0 && $section_id > 0) {
            $sec = $this->db->query(
                'SELECT `id`, `course_id` FROM `section` WHERE `id` = ? LIMIT 1',
                array($section_id)
            )->row_array();
            if (!$sec || (int) $sec['course_id'] !== $course_id) {
                $errors[] = 'القسم المحدد ليس من هذا الكورس.';
                $section_id = 0;
            }
        }

        /* ---- العنوان ---- */
        $title = trim((string) $this->val($post, 'title'));
        $title = preg_replace('/\s+/u', ' ', $title);
        $len   = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        if ($title === '')  $errors[] = 'اكتب عنوان الدرس.';
        elseif ($len < 3)   $errors[] = 'عنوان الدرس أقصر من أن يدل عليه.';
        elseif ($len > 140) $errors[] = 'عنوان الدرس أطول من 140 حرفا.';

        /* ---- النوع ---- */
        $lesson_type = strtolower(trim((string) $this->val($post, 'lesson_type', 'video')));
        if (!in_array($lesson_type, array('video', 'text'), true)) {
            $errors[] = 'نوع الدرس غير معروف.';
            $lesson_type = 'video';
        }

        /* ---- المدة ---- */
        $minutes_raw = trim((string) $this->val($post, 'duration_minutes'));
        $minutes     = (int) $minutes_raw;
        if ($minutes_raw === '' || !ctype_digit(ltrim($minutes_raw, '+'))) {
            $errors[] = 'اكتب مدة الدرس بالدقائق.';
        } elseif ($minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES) {
            $errors[] = 'مدة الدرس تكون بين ' . self::MIN_MINUTES . ' و' . self::MAX_MINUTES . ' دقيقة.';
        }

        /**
         * ---- الحالة ----
         *
         * الأزرار الثلاثة في الشاشة: «حفظ ونشر» و«إرسال للمراجعة» و«حفظ
         * كمسودة». وكان `Taqdar::upload_save()` يترجم كل ما ليس `draft`
         * إلى `review` — فيضغط المعلم «حفظ ونشر» فيحفظ درسه «قيد
         * المراجعة» ويقال له «حفظ الدرس وأرسل للمراجعة»، وهو لم يطلب
         * مراجعة. زر يعد بفعل ويقع غيره، بلا رسالة تقول لماذا.
         *
         * والقاعدة الآن: **النشر ينفذ داخل كورس منشور.** المعلم صاحب درسه
         * في كورس اعتمدته المنصة ونشرته، فلا معنى لمراجعة كل درس فيه.
         * أما الكورس الذي لم ينشر بعد (`pending`/`draft`/`private`) فدرس
         * منشور فيه لا يراه أحد أصلا — ولا يوعد المعلم بنشر لا يقع، بل
         * يهبط الدرس إلى المراجعة **ويقال له ذلك صراحة** في رسالة النتيجة.
         *
         * والقرار هنا لا في المتحكم: هذه هي الطبقة التي تعرف الكورس وحالته
         * وتفرض الملكية، وقاعدة تكتب في العرض أو في الباب تنسى في الثاني.
         */
        $status = strtolower(trim((string) $this->val($post, 'action', 'draft')));
        if (!in_array($status, $this->statuses, true)) $status = 'draft';

        $status_note = '';
        if ($status === 'published' && $course_id > 0) {
            $c_status = (string) $this->db->query(
                'SELECT `status` FROM `course` WHERE `id` = ? LIMIT 1',
                array($course_id)
            )->row('status');

            if ($c_status !== 'active') {
                $status      = 'review';
                $status_note = ' والنشر المباشر يكون في كورس منشور، وهذا الكورس '
                             . $this->course_status_phrase($c_status)
                             . ' — فأرسل الدرس للمراجعة بدلا من نشره.';
            }
        }

        /* ---- الملخص ---- */
        $summary = trim((string) $this->val($post, 'summary'));
        if ($lesson_type === 'text') {
            $slen = function_exists('mb_strlen') ? mb_strlen($summary, 'UTF-8') : strlen($summary);
            if ($slen < 10) $errors[] = 'الدرس النصي محتواه ملخصه — اكتب نص الدرس.';
        }

        /* ---- الأهداف: من واحد إلى ثلاثة ---- */
        $objectives = array();
        foreach ((array) $this->val($post, 'objectives', array()) as $o) {
            $o = trim(preg_replace('/\s+/u', ' ', (string) $o));
            if ($o === '') continue;
            if (function_exists('mb_substr')) $o = mb_substr($o, 0, 160, 'UTF-8');
            else                              $o = substr($o, 0, 160);
            $objectives[] = $o;
            if (count($objectives) >= self::MAX_OBJECTIVES) break;
        }
        if (!$objectives) $errors[] = 'اكتب هدفا واحدا على الأقل للدرس.';

        /* ---- مصدر الفيديو: رابط أو ملف ---- */
        $video_url  = '';
        $video_type = '';
        $has_video_file = $this->file_present($files, 'video');
        $url_raw = trim((string) $this->val($post, 'video_url'));

        if ($lesson_type === 'video') {
            $source = strtolower(trim((string) $this->val($post, 'video_source', '')));
            if ($source !== 'url' && $source !== 'file') {
                $source = $has_video_file ? 'file' : 'url';
            }

            if ($source === 'url') {
                if ($url_raw === '') {
                    $errors[] = 'ضع رابط المقطع أو اختر رفع ملف.';
                } elseif (!$this->valid_http_url($url_raw)) {
                    $errors[] = 'رابط المقطع غير صالح — يبدأ الرابط بـ http أو https.';
                } else {
                    $video_url  = $url_raw;
                    $video_type = $this->detect_video_type($url_raw);
                }
            } else {
                if (!$has_video_file) {
                    $errors[] = 'لم يصل ملف المقطع. اختر ملفا أو استعمل الرابط.';
                } else {
                    $chk = $this->check_upload($files['video'], 'مقطع الدرس');
                    if (!$chk['ok']) $errors[] = $chk['error'];
                }
            }
        }

        /* ---- المرفق (اختياري) ---- */
        $has_attachment = $this->file_present($files, 'attachment');
        if ($has_attachment) {
            $chk = $this->check_upload($files['attachment'], 'المرفق');
            if (!$chk['ok']) $errors[] = $chk['error'];
        }

        if ($errors) return $this->fail($errors, $old);

        /* ================================================================
           من هنا فصاعدا: صار مسموحا أن نكتب.
           ================================================================ */

        /* قسم افتراضي إن لم يكن للكورس أقسام — الدرس لا يعلق بلا موضع. */
        if ($section_id <= 0) {
            $section_id = $this->first_or_new_section($course_id);
        }

        $moved = array();   // للتنظيف إن فشل الإدراج

        if ($lesson_type === 'video' && $has_video_file && $video_url === '') {
            $mv = $this->store_upload($files['video'], $teacher_id);
            if (!$mv['ok']) return $this->fail(array($mv['error']), $old);
            $moved[]    = $mv['path'];
            $video_url  = $mv['url'];
            $video_type = 'html5';
        }

        $attachment = null;
        $attachment_type = null;
        if ($has_attachment) {
            $mv = $this->store_upload($files['attachment'], $teacher_id);
            if (!$mv['ok']) {
                $this->cleanup($moved);
                return $this->fail(array($mv['error']), $old);
            }
            $moved[]         = $mv['path'];
            $attachment      = $mv['url'];
            $attachment_type = $mv['ext'];
        }

        $now = time();

        $data = array(
            'title'           => html_escape($title),
            'course_id'       => $course_id,
            'section_id'      => $section_id,
            'lesson_type'     => $lesson_type,
            'duration'        => $this->hms($minutes * 60),
            'video_type'      => $video_type !== '' ? $video_type : null,
            'video_url'       => $video_url !== '' ? $video_url : null,
            'attachment'      => $attachment,
            'attachment_type' => $attachment_type,
            'summary'         => $summary !== '' ? html_escape($summary) : null,
            'is_free'         => $this->val($post, 'is_free') ? 1 : 0,
            'order'           => $this->next_order($course_id, $section_id),
            'date_added'      => $now,
            'last_modified'   => $now,
        );

        if ($this->has_status_column()) $data['tq_status'] = $status;

        $this->db->insert('lesson', $data);
        $lesson_id = (int) $this->db->insert_id();

        if ($lesson_id <= 0) {
            $this->cleanup($moved);
            return $this->fail(array('تعذر حفظ الدرس. حاول مرة أخرى.'), $old);
        }

        /* الأهداف — جدولها موجود، وعليه تقوم مراجعة الإتقان لاحقا. */
        foreach ($objectives as $text) {
            $this->db->insert('objectives', array(
                'lesson_id'  => $lesson_id,
                'text'       => html_escape($text),
                'at_second'  => 0,
            ));
        }

        $this->log('lesson.create', 'lesson:' . $lesson_id, $teacher_id, array(
            'course_id'  => $course_id,
            'section_id' => $section_id,
            'status'     => $status,
            'objectives' => count($objectives),
        ));

        return array(
            'ok'        => true,
            'lesson_id' => $lesson_id,
            'course_id' => $course_id,
            'status'    => $status,
            'message'   => 'حفظ الدرس «' . $title . '» ' . $this->status_phrase($status) . '.' . $status_note,
        );
    }

    /** حالة الكورس بعبارة تقرأ داخل جملة. */
    private function course_status_phrase($status)
    {
        $map = array(
            'pending'  => 'ينتظر مراجعة الإدارة',
            'draft'    => 'ما زال مسودة',
            'private'  => 'خاص غير منشور',
            'upcoming' => 'لم يبدأ بعد',
        );
        return isset($map[$status]) ? $map[$status] : 'غير منشور';
    }

    /* =====================================================================
       أدوات داخلية
       ===================================================================== */

    private function fail($errors, $old)
    {
        return array('ok' => false, 'errors' => array_values($errors), 'old' => $old);
    }

    private function val($arr, $key, $default = '')
    {
        return isset($arr[$key]) ? $arr[$key] : $default;
    }

    /** ما يعاد إلى النموذج بعد الخطأ — بلا الملفات، فهي لا تعاد. */
    private function old_input($post)
    {
        $keys = array('course_id', 'section_id', 'title', 'lesson_type', 'duration_minutes',
                      'video_source', 'video_url', 'summary', 'is_free');
        $old = array();
        foreach ($keys as $k) $old[$k] = isset($post[$k]) ? $post[$k] : '';
        $old['objectives'] = isset($post['objectives']) && is_array($post['objectives'])
            ? array_values($post['objectives']) : array();
        return $old;
    }

    /** هل تجاوز الطلب post_max_size؟ */
    private function post_overflowed()
    {
        if (empty($_SERVER['CONTENT_LENGTH'])) return false;
        $max = $this->bytes(ini_get('post_max_size'));
        return $max > 0 && (int) $_SERVER['CONTENT_LENGTH'] > $max;
    }

    private function bytes($val)
    {
        $val  = trim((string) $val);
        if ($val === '') return 0;
        $unit = strtolower(substr($val, -1));
        $num  = (int) $val;
        if ($unit === 'g') return $num * 1024 * 1024 * 1024;
        if ($unit === 'm') return $num * 1024 * 1024;
        if ($unit === 'k') return $num * 1024;
        return $num;
    }

    private function file_present($files, $key)
    {
        return isset($files[$key])
            && is_array($files[$key])
            && isset($files[$key]['error'])
            && (int) $files[$key]['error'] !== UPLOAD_ERR_NO_FILE
            && (string) $this->val($files[$key], 'name') !== '';
    }

    /**
     * يتحقق من ملف مرفوع قبل نقله.
     * الامتداد يقرر بـ tq_safe_upload_extension() وحدها — قائمة بيضاء
     * واحدة في المنصة، فلا تتفرق ولا تنسى فيها صيغة.
     */
    private function check_upload($file, $label)
    {
        $err = (int) $this->val($file, 'error', UPLOAD_ERR_NO_FILE);

        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return array('ok' => false, 'error' => $label . ' أكبر من حد الرفع (' . ini_get('upload_max_filesize') . ').');
        }
        if ($err === UPLOAD_ERR_PARTIAL) {
            return array('ok' => false, 'error' => 'انقطع رفع ' . $label . ' قبل اكتماله. أعد المحاولة.');
        }
        if ($err !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'error' => 'تعذر رفع ' . $label . ' (رمز ' . $err . ').');
        }

        $tmp = (string) $this->val($file, 'tmp_name');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return array('ok' => false, 'error' => 'ملف ' . $label . ' غير صالح.');
        }

        $ext = tq_safe_upload_extension($this->val($file, 'name'));
        if ($ext === false) {
            return array('ok' => false, 'error' => 'صيغة ' . $label . ' غير مسموح بها.');
        }

        return array('ok' => true, 'ext' => $ext);
    }

    /**
     * ينقل الملف باسم يولد هنا بالكامل.
     * لا شيء من اسم المستخدم يدخل المسار — لا امتداد مزدوج ولا `../`.
     */
    private function store_upload($file, $teacher_id)
    {
        $chk = $this->check_upload($file, 'الملف');
        if (!$chk['ok']) return array('ok' => false, 'error' => $chk['error']);

        $dir = rtrim(FCPATH, '/') . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return array('ok' => false, 'error' => 'تعذر تجهيز مجلد الرفع على الخادم.');
        }

        $name = 'tq_l' . (int) $teacher_id . '_' . time() . '_'
              . substr(bin2hex(random_bytes(6)), 0, 12) . '.' . $chk['ext'];
        $path = $dir . $name;

        if (!@move_uploaded_file($this->val($file, 'tmp_name'), $path)) {
            return array('ok' => false, 'error' => 'تعذر حفظ الملف المرفوع على الخادم.');
        }
        @chmod($path, 0644);

        return array(
            'ok'   => true,
            'path' => $path,
            'ext'  => $chk['ext'],
            'url'  => base_url(self::UPLOAD_DIR . $name),
        );
    }

    private function cleanup($paths)
    {
        foreach ((array) $paths as $p) if ($p && is_file($p)) @unlink($p);
    }

    /** رابط http/https فقط — لا `javascript:` ولا `data:`. */
    private function valid_http_url($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }

    /** نوع المقطع كما يفهمه مشغل تقدر. */
    private function detect_video_type($url)
    {
        if (preg_match('~youtu\.?be~i', $url))  return 'youtube';
        if (preg_match('~vimeo\.com~i', $url))  return 'vimeo';
        return 'html5';
    }

    /** Academy يخزن المدة نصا `HH:MM:SS` وقارئوها يعتمدون ذلك. */
    private function hms($seconds)
    {
        $seconds = max(0, (int) $seconds);
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /** ترتيب الدرس داخل قسمه — يلحق بآخر الدروس لا يقحم بينها. */
    private function next_order($course_id, $section_id)
    {
        $r = $this->db->query(
            'SELECT COALESCE(MAX(`order`), 0) AS mx FROM `lesson`
              WHERE `course_id` = ? AND `section_id` = ?',
            array((int) $course_id, (int) $section_id)
        )->row_array();
        return ((int) $r['mx']) + 1;
    }

    /**
     * أول قسم للكورس، وإن لم يكن له قسم أنشأ واحدا.
     * و`course.section` يحدث معه لأن لوحة Academy تقرأ ترتيب الأقسام منه،
     * فقسم لا يسجل هناك يظهر في البوابة ويغيب عن اللوحة.
     */
    private function first_or_new_section($course_id)
    {
        $course_id = (int) $course_id;

        $row = $this->db->query(
            'SELECT `id` FROM `section` WHERE `course_id` = ? ORDER BY `order` ASC, `id` ASC LIMIT 1',
            array($course_id)
        )->row_array();
        if ($row) return (int) $row['id'];

        $this->db->insert('section', array(
            'title'     => 'الوحدة الأولى',
            'course_id' => $course_id,
            'order'     => 1,
        ));
        $section_id = (int) $this->db->insert_id();

        $course = $this->db->query('SELECT `section` FROM `course` WHERE `id` = ?', array($course_id))->row_array();
        $list   = $course ? json_decode((string) $course['section'], true) : array();
        if (!is_array($list)) $list = array();
        $list[] = $section_id;
        $this->db->where('id', $course_id)->update('course', array('section' => json_encode(array_values($list))));

        return $section_id;
    }

    /**
     * جدول `lesson` في Academy بلا عمود حالة، وحالة الدرس مطلوبة.
     * يضاف `tq_status` مرة بقيمة افتراضية `published` حتى لا يتغير معنى
     * أي صف كتب قبله، ثم يحفظ فيه ما يختاره المعلم.
     */
    private function has_status_column()
    {
        static $has = null;
        if ($has !== null) return $has;

        $has = $this->db->field_exists('tq_status', 'lesson');
        if (!$has) {
            @$this->db->query(
                "ALTER TABLE `lesson`
                   ADD COLUMN `tq_status` VARCHAR(20) NOT NULL DEFAULT 'published'"
            );
            $this->db->data_cache = array();          // ذاكرة أسماء الأعمدة في CI
            $has = $this->db->field_exists('tq_status', 'lesson');
        }
        return $has;
    }

    private function status_phrase($status)
    {
        if ($status === 'published') return 'ونشر';
        if ($status === 'review')    return 'وأرسل للمراجعة';
        return 'كمسودة';
    }

    /** أثر الكتابة في سجل التدقيق — من كتب وماذا ومتى. */
    private function log($action, $entity, $actor_id, $after)
    {
        @$this->db->insert('audit_log', array(
            'actor_id' => (int) $actor_id ?: null,
            'action'   => $action,
            'entity'   => $entity,
            'before'   => null,
            'after'    => json_encode($after, JSON_UNESCAPED_UNICODE),
            'ip'       => $this->input->ip_address(),
            'at'       => date('Y-m-d H:i:s'),
        ));
    }


    /* =====================================================================
       إنشاء الكورس وتعديله
       ===================================================================== */

    /**
     * ينشئ كورسا للمعلم أو يعدل كورسا يملكه.
     *
     * الجديد يبدأ **`pending`**: النشر قرار إدارة لا قرار من يرفع المحتوى.
     * و`creator` و`user_id` يملآن معا لأن السكربت يفحص أحدهما تارة
     * والآخر تارة — وترك أحدهما ينتج كورسا لا يراه صاحبه في شاشته.
     */
    /**
     * @deprecated TQ-COURSE-SPLIT — نسخة ثانية من قواعد حفظ الكورس.
     *
     * تعرف أربعة حقول (عنوان ومستوى ووصفين) من نيف وعشرين تحررها اللوحة،
     * ولا تعرف **الصف والمادة** — وبهما وحدهما يصل الكورس إلى طالب: الكتالوج
     * ومحرك الاشتراكات يقرآن `paths` لا `course` (انظر
     * [Taqdar_course_link_model.php]). فكل كورس مر بها ولد محجوبا: لا يظهر
     * في «المواد والبرامج»، ولا تفتحه باقة، ولا شيء في شاشته يقول لماذا.
     *
     * والقواعد صارت في `Taqdar_curriculum_model::save_course()` وحدها —
     * وهي التي تنادى من اللوحة ومن بوابة المعلم معا، كما صارت قواعد
     * الأقسام والدروس. ولا تنادى هذه من مسار حي، وتبقى لئلا ينكسر ما
     * يستدعيها من خارج المستودع.
     */
    public function save_course($teacher_id, $post = null, $files = null)
    {
        $teacher_id = (int) $teacher_id;
        $post = $post ?: $_POST;
        $CI = get_instance();

        $id     = isset($post['course_id']) ? (int) $post['course_id'] : 0;
        $title  = trim((string) (isset($post['title']) ? $post['title'] : ''));
        $short  = trim((string) (isset($post['short_description']) ? $post['short_description'] : ''));
        $desc   = trim((string) (isset($post['description']) ? $post['description'] : ''));
        $level  = (string) (isset($post['level']) ? $post['level'] : 'beginner');
        $cat    = (int) (isset($post['category_id']) ? $post['category_id'] : 0);

        $errors = array();
        if ($title === '')            $errors[] = 'عنوان الكورس مطلوب.';
        if (mb_strlen($title) > 190)  $errors[] = 'العنوان أطول من المسموح.';
        if (!in_array($level, array('beginner', 'intermediate', 'advanced'), true)) {
            $level = 'beginner';
        }

        // التعديل: الملكية تعاد قراءتها من القاعدة، لا تؤخذ من النموذج
        if ($id > 0 && !$this->owns_course($teacher_id, $id)) {
            $errors[] = 'هذا الكورس ليس ضمن كورساتك.';
        }

        if ($errors) return array('ok' => false, 'errors' => $errors);

        $data = array(
            'title'             => html_escape($title),
            'short_description' => html_escape($short),
            'description'       => $desc,
            'level'             => $level,
            'category_id'       => $cat,
            'language'          => get_settings('language') ?: 'arabic',
            'last_modified'     => time(),
        );

        if ($id > 0) {
            $CI->db->where('id', $id)->update('course', $data);
            $msg = 'حفظت تعديلات الكورس.';
        } else {
            $data['creator']    = $teacher_id;
            $data['user_id']    = (string) $teacher_id;
            $data['status']     = 'pending';
            $data['date_added'] = time();
            $data['price']      = 0;
            $data['is_free_course'] = 0;
            $CI->db->insert('course', $data);
            $id  = (int) $CI->db->insert_id();
            $msg = 'أنشئ الكورس، وهو بانتظار مراجعة الإدارة قبل النشر.';
        }

        $CI->db->insert('audit_log', array(
            'actor_id' => $teacher_id,
            'action'   => 'teacher.course.save',
            'entity'   => 'course#' . $id,
            'after'    => json_encode(array('title' => $title), JSON_UNESCAPED_UNICODE),
            'ip'       => $CI->input->is_cli_request() ? 'cli' : $CI->input->ip_address(),
            'at'       => date('Y-m-d H:i:s'),
        ));

        return array('ok' => true, 'message' => $msg, 'course_id' => $id);
    }

    /** مطواة لتطابق سلسلة التفويض في المتحكم. */
    public function courses_save($teacher_id, $payload = null, $files = null)
    {
        return $this->save_course($teacher_id, $payload, $files);
    }

    /* =====================================================================
       استيراد الأسئلة
       ===================================================================== */

    /**
     * يستورد أسئلة اختيار من ملف CSV إلى درس اختبار يملكه المعلم.
     *
     * الأعمدة: السؤال · خيار١..خيار٤ · الصحيح (رقم الخيار أو نصه).
     * والصف المعطوب يتجاوز ولا يوقف غيره — ملف فيه خطأ واحد يجب أن
     * يدخل بقيته.
     */
    public function import_questions($teacher_id, $payload = null, $files = null)
    {
        $teacher_id = (int) $teacher_id;
        $payload    = $payload ?: array();
        $files      = $files ?: $_FILES;
        $CI = get_instance();

        $lesson_id = (int) (isset($payload['lesson_id']) ? $payload['lesson_id'] : 0);
        if ($lesson_id < 1) {
            return array('ok' => false, 'errors' => array('اختر الاختبار الذي تستورد إليه الأسئلة.'));
        }

        // الملكية تعاد قراءتها هنا ولا يكتفى بحارس المتحكم
        $own = $CI->db->query(
            'SELECT COUNT(*) n FROM `lesson` l JOIN `course` c ON c.id = l.course_id
              WHERE l.id = ? AND (c.creator = ? OR FIND_IN_SET(?, c.user_id) > 0)',
            array($lesson_id, $teacher_id, $teacher_id)
        )->row('n');
        if ((int) $own < 1) {
            return array('ok' => false, 'errors' => array('هذا الدرس في كورس ليس لك.'));
        }

        // شكلان محتملان: `$_FILES` كاملا، أو الملف وحده كما يمرره المتحكم
        $f = null;
        if (isset($files['tmp_name']) && is_string($files['tmp_name'])) {
            $f = $files;
        } else {
            foreach (array('csv', 'file', 'curriculum') as $k) {
                if (isset($files[$k]['tmp_name'])) { $f = $files[$k]; break; }
            }
            if ($f === null && is_array($files)) {
                $first = reset($files);
                if (is_array($first) && isset($first['tmp_name'])) $f = $first;
            }
        }

        if (!$f || empty($f['tmp_name']) || !is_readable($f['tmp_name'])) {
            return array('ok' => false, 'errors' => array('لم يصل ملف صالح.'));
        }

        $raw = @file_get_contents($f['tmp_name']);
        if ($raw === false || trim($raw) === '') {
            return array('ok' => false, 'errors' => array('الملف فارغ أو تعذرت قراءته.'));
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $delim = (substr_count($raw, ';') > substr_count($raw, ',')) ? ';' : ',';
        $lines = preg_split('/\r\n|\r|\n/', $raw);

        $head = null; $added = 0; $skipped = 0; $notes = array();
        $order = (int) $CI->db->where('quiz_id', $lesson_id)->count_all_results('question');

        /* كورس الاختبار — يبحث فيه عن الهدف بنصه قبل أن ينشأ جديد. */
        $course_of_lesson = (int) $CI->db->select('course_id')->where('id', $lesson_id)
                                         ->get('lesson')->row('course_id');

        foreach ($lines as $ln => $line) {
            if (trim($line) === '') continue;
            $cells = str_getcsv($line, $delim);

            if ($head === null) {
                $head = array_map(function ($h) { return trim(mb_strtolower($h)); }, $cells);
                continue;
            }

            $row = array();
            foreach ($head as $i => $h) $row[$h] = isset($cells[$i]) ? trim($cells[$i]) : '';

            $q = $this->pick($row, array('question', 'السؤال', 'نص_السؤال', 'title'));

            /**
             * أسماء أعمدة الخيارات.
             *
             * `option_1 … option_4` أولا لأنها **الأسماء المكتوبة في
             * الشاشة نفسها** (`tq_teacher_questions.php`) — وكان المحلل
             * يعرف `option1` بلا شرطة وحدها. فمن اتبع المواصفة المعروضة
             * حرفا بحرف رد عليه «لم يقبل أي سؤال. تحقق من الأعمدة»،
             * والأعمدة صحيحة وهو الذي كتبها كما قيل له.
             */
            $opts = array();
            foreach (array(
                array('option_1', 'option1', 'خيار1', 'الخيار1', 'خيار_1', 'a'),
                array('option_2', 'option2', 'خيار2', 'الخيار2', 'خيار_2', 'b'),
                array('option_3', 'option3', 'خيار3', 'الخيار3', 'خيار_3', 'c'),
                array('option_4', 'option4', 'خيار4', 'الخيار4', 'خيار_4', 'd'),
            ) as $names) {
                $v = $this->pick($row, $names);
                if ($v !== '') $opts[] = $v;
            }
            $correct = $this->pick($row, array('correct', 'الصحيح', 'الاجابة', 'الإجابة', 'answer'));

            /* الهدف: عمود إلزامي تعلنه الشاشة («الاستيراد يرفض أي صف بلا
               هدف»)، وكان المحلل لا يقرؤه أصلا — فيستورد السؤال بلا هدف
               ثم تعرضه الشاشة نفسها موسوما «بلا هدف». وعد يخالف نفسه. */
            $objective = $this->pick($row, array('objective', 'الهدف', 'هدف', 'objective_id'));

            if ($q === '' || count($opts) < 2 || $correct === '' || $objective === '') {
                $skipped++;
                if (count($notes) < 5) {
                    $notes[] = 'السطر ' . ($ln + 1) . ($objective === '' ? ' بلا هدف' : '');
                }
                continue;
            }

            /* النوع: `radio` أو `checkbox` باصطلاح تقدر، ويقبل اصطلاح
               Academy أيضا. وما لم يعرف يقرأ اختيارا واحدا. */
            $type = mb_strtolower($this->pick($row, array('type', 'النوع', 'نوع')));
            $type = in_array($type, array('checkbox', 'multiple_choice', 'multi', 'متعدد'), true)
                ? 'checkbox' : 'radio';

            /* الصحيح: رقم خيار أو نصه، وواحد أو أكثر للاختيار المتعدد.
               والفاصل بين الأرقام `;` أو `|` أو مسافة — لا فاصلة، فهي
               فاصل الأعمدة في الملف نفسه. */
            $wanted = preg_split('/[;|\s]+/u', $correct, -1, PREG_SPLIT_NO_EMPTY);
            $answers = array();
            foreach ($wanted as $one) {
                $one = trim($one);
                if ($one === '') continue;
                if (ctype_digit($one)) {
                    $idx = (int) $one - 1;
                    if (isset($opts[$idx])) $answers[] = $opts[$idx];
                } elseif (in_array($one, $opts, true)) {
                    $answers[] = $one;
                }
            }
            $answers = array_values(array_unique($answers));
            if (!$answers) { $skipped++; continue; }
            if ($type === 'radio') $answers = array($answers[0]);

            $order++;
            $CI->db->insert('question', array(
                'quiz_id'           => $lesson_id,
                'objective_id'      => $this->objective_id_for($objective, $lesson_id, $course_of_lesson),
                'title'             => html_escape($q),
                'type'              => $type,
                'number_of_options' => count($opts),
                'options'           => json_encode(array_map('html_escape', $opts), JSON_UNESCAPED_UNICODE),
                'correct_answers'   => json_encode(array_map('html_escape', $answers), JSON_UNESCAPED_UNICODE),
                'order'             => $order,
            ));
            $added++;
        }

        if ($head === null) return array('ok' => false, 'errors' => array('تعذر فهم ترويسة الملف.'));
        if ($added === 0)   return array('ok' => false, 'errors' => array('لم يقبل أي سؤال. تحقق من الأعمدة.'));

        /* «أضيف 2 سؤالا» خطأ في تمييز العدد، و`tq_count_units` في المساعدات
           تحسمه — والمساعدات محملة تلقائيا فلا تحتاج استيرادا. */
        $msg = 'أضيف ' . (function_exists('tq_count_units')
            ? tq_count_units($added, 'سؤال', 'سؤالان', 'سؤالين', 'أسئلة', 'سؤالا')
            : $added . ' سؤالا') . '.';
        if ($skipped) {
            $msg .= ' وتجاوز ' . (function_exists('tq_count_units')
                ? tq_count_units($skipped, 'سطر', 'سطران', 'سطرين', 'أسطر', 'سطرا')
                : $skipped . ' سطرا') . ($notes ? ' (' . implode('، ', $notes) . ')' : '') . '.';
        }
        return array('ok' => true, 'message' => $msg, 'added' => $added, 'skipped' => $skipped);
    }

    /** مطواة: سلسلة التفويض في المتحكم تنادي بهذا الاسم. */
    public function questions_import($teacher_id, $payload = null, $files = null)
    {
        return $this->import_questions($teacher_id, $payload, $files);
    }

    /**
     * معرف الهدف من خلية «objective» في ملف الاستيراد.
     *
     * ثلاث محاولات بترتيبها:
     *   ١ — رقم: يقبل إن كان هدفا في هذا الكورس (لا في كورس غيره).
     *   ٢ — نص: يطابق نص هدف قائم في دروس هذا الكورس، مجردا من فروق
     *       المسافات — فمن كتب الهدف بمسافة زائدة لا يخلق له ثانيا.
     *   ٣ — لا مطابق: ينشأ الهدف على درس الاختبار نفسه. وهو ما تفعله شاشة
     *       رفع الدرس بالضبط حين يكتب المعلم أهدافه نصا حرا — فالمعلم
     *       يؤلف بنكه هو، ونص هدفه محتواه لا مرجع خارجي يبحث عنه.
     *
     * @return int|null معرف الهدف، أو null إن تعذر (فيبقى السؤال بلا هدف)
     */
    private function objective_id_for($objective, $lesson_id, $course_id)
    {
        $objective = trim((string) $objective);
        if ($objective === '') return null;

        $CI = get_instance();
        if (!$CI->db->table_exists('objectives') || !$CI->db->field_exists('objective_id', 'question')) {
            return null;
        }

        if (ctype_digit($objective)) {
            $row = $CI->db->query(
                'SELECT o.`id` FROM `objectives` o
                   JOIN `lesson` l ON l.`id` = o.`lesson_id`
                  WHERE o.`id` = ? AND l.`course_id` = ? LIMIT 1',
                array((int) $objective, (int) $course_id)
            )->row_array();
            if ($row) return (int) $row['id'];
        }

        $norm = preg_replace('/\s+/u', ' ', $objective);
        $row  = $CI->db->query(
            "SELECT o.`id` FROM `objectives` o
               JOIN `lesson` l ON l.`id` = o.`lesson_id`
              WHERE l.`course_id` = ?
                AND TRIM(REGEXP_REPLACE(o.`text`, '[[:space:]]+', ' ')) = ?
              LIMIT 1",
            array((int) $course_id, $norm)
        )->row_array();
        if ($row) return (int) $row['id'];

        $CI->db->insert('objectives', array(
            'lesson_id' => (int) $lesson_id,
            'text'      => html_escape(mb_substr($norm, 0, 500)),
            'at_second' => 0,
        ));
        $id = (int) $CI->db->insert_id();

        return $id > 0 ? $id : null;
    }

    /** أول قيمة غير فارغة بين أسماء أعمدة مترادفة. */
    private function pick($row, $names)
    {
        foreach ($names as $n) {
            $k = mb_strtolower($n);
            if (isset($row[$k]) && trim($row[$k]) !== '') return trim($row[$k]);
        }
        return '';
    }

}
