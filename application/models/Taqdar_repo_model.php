<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مستودع بيانات تقدر — الطبقة الانتقالية.
 *
 * كل دالة هنا تطابق عقد /api/v1 المستقبلي اسما ومخرجا، فلا تتغير
 * الشاشات حين تهاجر الطبقة إلى Laravel/PostgreSQL: يتبدل ما تحت الدالة
 * لا ما فوقها.
 *
 * قواعد ملزمة داخل هذا الملف:
 *   • المبالغ أعداد صحيحة بالهللات — لا حساب بأرقام عشرية عائمة.
 *   • الوقت يولد في PHP بتوقيت التطبيق (Asia/Riyadh) لا بـ NOW() لأن
 *     ساعة الخادم UTC، فاختلاطهما يفسد مواعيد الاستحقاق.
 *   • لا تعاد الإجابة الصحيحة إلى العميل أبدا — لا في المراجعة ولا في
 *     نتيجة المحاولة. التصحيح في الخادم والنتيجة رقم لا مفتاح حل.
 *   • القفل يحسب هنا، والعميل يعرضه فقط.
 */
class Taqdar_repo_model extends CI_Model
{
    /** كتالوج الأخطاء الموحد: code => [http, message, message_ar] */
    public static $ERRORS = array(
        'MASTERY_LOCKED'    => array(403, 'Finish the previous lesson review first.',   'أكمل مراجعة الدرس السابق أولا'),
        'NOT_ENTITLED'      => array(403, 'This content is not part of your enrolment.', 'هذا المحتوى غير متاح ضمن اشتراكك'),
        'OUT_OF_ASSIGNMENT' => array(403, 'Outside your teaching assignment.',           'هذه المادة أو هذا الصف خارج نطاق إسنادك'),
        'DUPLICATE_ATTEMPT' => array(409, 'This attempt was already submitted.',         'هذه المحاولة مسلمة من قبل'),
        'RATE_LIMITED'      => array(429, 'Too many requests, slow down.',               'محاولات كثيرة في وقت قصير — انتظر قليلا ثم أعد المحاولة'),
        'UNAUTHENTICATED'   => array(401, 'Sign in required.',                           'يلزم تسجيل الدخول'),
        'NOT_FOUND'         => array(404, 'Resource not found.',                         'العنصر المطلوب غير موجود'),
        'NO_REVIEW'         => array(404, 'No review is attached to this lesson.',        'لا توجد مراجعة مرتبطة بهذا الدرس'),
        'VALIDATION'        => array(422, 'Invalid input.',                              'بيانات غير صالحة'),
        /* TQ-GATE-CSRF · رمز مضاد للتزوير غائب أو بائت. والرسالة تقول
           «حدث الصفحة» لا «ممنوع»: هذا ما يقع فعلا حين تترك الصفحة
           مفتوحة حتى تنتهي الجلسة، وهو ما يصلحه المستخدم بنفسه. */
        'CSRF'              => array(403, 'Security token missing or stale.',            'انتهت صلاحية الصفحة — حدثها ثم أعد المحاولة'),
        'INTERNAL'          => array(500, 'Unexpected error.',                           'حدث خطأ غير متوقع'),
    );

    private $_lesson_order_cache = array();
    private $_settings_cache     = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /* ================================================================
     *  أدوات داخلية
     * ================================================================ */

    /** الوقت الآن بتوقيت التطبيق، بصيغة MySQL. */
    private function now()
    {
        return date('Y-m-d H:i:s');
    }

    /** إعداد من جدول settings — لا ثوابت مدفونة في الكود. */
    public function setting($key, $default = null)
    {
        if (array_key_exists($key, $this->_settings_cache)) {
            return $this->_settings_cache[$key];
        }
        $row = $this->db->select('value')->where('key', $key)->get('settings')->row_array();
        $val = ($row && $row['value'] !== null && $row['value'] !== '') ? $row['value'] : $default;
        $this->_settings_cache[$key] = $val;
        return $val;
    }

    public function pass_mark($assessment = null)
    {
        if (is_array($assessment) && isset($assessment['pass_mark']) && (int) $assessment['pass_mark'] > 0) {
            return (int) $assessment['pass_mark'];
        }
        return (int) $this->setting('mastery_pass_mark', 3);
    }

    /** مغلف الخطأ الموحد — العميل يعرض message_ar. */
    public function error($code, $details = array())
    {
        $e = isset(self::$ERRORS[$code]) ? self::$ERRORS[$code] : self::$ERRORS['INTERNAL'];
        return array('error' => array(
            'code'       => $code,
            'message'    => $e[1],
            'message_ar' => $e[2],
            'details'    => (object) $details,
        ));
    }

    public function http_status($code)
    {
        return isset(self::$ERRORS[$code]) ? self::$ERRORS[$code][0] : 500;
    }

    public function is_error($result)
    {
        return is_array($result) && isset($result['error']);
    }

    /** سجل التدقيق — من فعل ماذا بأي كيان ومتى. */
    public function audit($actor_id, $action, $entity, $before = null, $after = null)
    {
        $this->db->insert('audit_log', array(
            'actor_id' => $actor_id ? (int) $actor_id : null,
            'action'   => $action,
            'entity'   => $entity,
            'before'   => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after'    => $after  === null ? null : json_encode($after,  JSON_UNESCAPED_UNICODE),
            'ip'       => $this->input->ip_address(),
            'at'       => $this->now(),
        ));
        return $this->db->insert_id();
    }

    /**
     * يسجل يوم نشاط في دفتر `Taqdar_learn_model`.
     *
     * موضعه هنا لا في المتحكم لأن النشاط يقع في النموذج: حفظ المشاهدة
     * وإتقان الدرس وإجابة المراجعة ثلاثتها تمر من هذا الملف، ومنها يأتي
     * التطبيق والويب والكرون معا. ووضعه في متحكم واحد يعني أن السلسلة
     * تنقطع لمن دخل من الباب الآخر.
     *
     * ولا يرمي أبدا: دفتر نشاط ناقص أهون من درس لا يحفظ موضعه.
     */
    private function touch_day($student_id, $kind, $amount)
    {
        try {
            $this->load->model('taqdar_learn_model', 'tq_learn');
            $this->tq_learn->touch_activity($student_id, $kind, $amount);
        } catch (Throwable $e) {
            log_message('error', 'TQ-ACTIVITY: ' . $e->getMessage());
        }
    }

    /**
     * MySQL يعيد الأعداد نصوصا عبر mysqli؛ وعقد /api/v1 يعد بأعداد.
     * فالتحويل هنا مرة واحدة لا في كل شاشة.
     */
    private function cast_ints(&$row, $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row)) {
                $row[$k] = ($row[$k] === null) ? null : (int) $row[$k];
            }
        }
    }

    /** "01:12:30" أو "12:30" أو "750" ⇐ ثوان. */
    public function duration_seconds($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') return 0;
        if (ctype_digit($raw)) return (int) $raw;

        $parts = array_reverse(explode(':', $raw));
        $sec = 0;
        $mul = 1;
        foreach ($parts as $p) {
            $sec += ((int) $p) * $mul;
            $mul *= 60;
        }
        return $sec;
    }

    /* ================================================================
     *  الاستحقاق والترتيب والقفل
     * ================================================================ */

    /** دروس المقرر مرتبة كما يراها الطالب: القسم ثم ترتيب الدرس. */
    public function ordered_lessons($course_id)
    {
        $course_id = (int) $course_id;
        if (isset($this->_lesson_order_cache[$course_id])) {
            return $this->_lesson_order_cache[$course_id];
        }
        $sql = 'SELECT l.`id`, l.`title`, l.`section_id`, l.`course_id`, l.`duration`,
                       l.`is_free`, l.`lesson_type`, l.`order` AS lesson_order,
                       COALESCE(s.`order`, 0) AS section_order, s.`title` AS section_title
                FROM `lesson` l
                LEFT JOIN `section` s ON s.`id` = l.`section_id`
                WHERE l.`course_id` = ?
                  AND COALESCE(l.`tq_status`, "published") = "published"
                ORDER BY section_order ASC, l.`section_id` ASC, lesson_order ASC, l.`id` ASC';
        $rows = $this->db->query($sql, array($course_id))->result_array();
        foreach ($rows as &$r) {
            $this->cast_ints($r, array('id', 'section_id', 'course_id', 'is_free',
                                       'lesson_order', 'section_order'));
        }
        unset($r);
        $this->_lesson_order_cache[$course_id] = $rows;
        return $rows;
    }

    /** هل الطالب مستحق لمحتوى هذا المقرر؟ */
    public function is_entitled($student_id, $course_id)
    {
        $student_id = (int) $student_id;
        $course_id  = (int) $course_id;
        if (!$student_id || !$course_id) return false;

        $user = $this->db->select('id, role_id, is_instructor')
                         ->where('id', $student_id)->get('users')->row_array();
        if (!$user) return false;
        if ((int) $user['role_id'] === 1) return true; // الإدارة ترى كل شيء

        $course = $this->db->select('id, user_id, creator')
                           ->where('id', $course_id)->get('course')->row_array();
        if ($course) {
            $owners = array_map('trim', explode(',', (string) $course['user_id']));
            if (in_array((string) $student_id, $owners, true)) return true;
            if ((int) $course['creator'] === $student_id) return true;
        }

        // (1) الاشتراك النشط — الطريق الجديد
        $this->load->model('taqdar_billing_model');
        if ($this->taqdar_billing_model->subscription_grants($student_id, $course_id)) {
            return true;
        }

        // (2) التسجيل المفرد — ارتداد لا ينزع: من اشترى دورة قبل وجود
        //     الاشتراكات يبقى مالكا لها، ولا يطالب بالدفع مرتين.
        $enrol = $this->db->where('user_id', $student_id)->where('course_id', $course_id)
                          ->get('enrol')->row_array();
        if (!$enrol) return false;

        $expiry = (int) $enrol['expiry_date'];
        if ($expiry > 0 && $expiry < time()) return false;

        return true;
    }

    /**
     * حالة القفل لدرس بعينه — الحقيقة الوحيدة، والعميل تجميل لها.
     *
     * الدرس مفتوح إذا كان أول دروس المقرر، أو كان مجانيا للمعاينة،
     * أو أتقن الدرس السابق (mastered_at) حين تكون له مراجعة، أو أكمل
     * (completed_at) حين لا مراجعة له.
     */
    public function lesson_lock_state($student_id, $lesson_id)
    {
        $student_id = (int) $student_id;
        $lesson     = $this->db->where('id', (int) $lesson_id)->get('lesson')->row_array();
        if (!$lesson) return array('found' => false);

        $ordered = $this->ordered_lessons($lesson['course_id']);
        $pos     = -1;
        foreach ($ordered as $i => $l) {
            if ((int) $l['id'] === (int) $lesson['id']) { $pos = $i; break; }
        }

        $prev_id = ($pos > 0)                        ? (int) $ordered[$pos - 1]['id'] : null;
        $next_id = ($pos >= 0 && isset($ordered[$pos + 1])) ? (int) $ordered[$pos + 1]['id'] : null;

        $state = array(
            'found'          => true,
            'lesson'         => $lesson,
            'position'       => $pos,
            'prev_lesson_id' => $prev_id,
            'next_lesson_id' => $next_id,
            'unlocked'       => false,
            'reason'         => '',
        );

        if ($pos <= 0) { // أول درس مفتوح دائما
            $state['unlocked'] = true;
            $state['reason']   = 'first_lesson';
            return $state;
        }
        if ((int) $lesson['is_free'] === 1) {
            $state['unlocked'] = true;
            $state['reason']   = 'free_preview';
            return $state;
        }

        $prev_progress = $this->db->where('student_id', $student_id)->where('lesson_id', $prev_id)
                                  ->get('lesson_progress')->row_array();
        $prev_review   = $this->review_assessment($prev_id, false);

        if ($prev_review) {
            $state['unlocked'] = !empty($prev_progress['mastered_at']);
            $state['reason']   = $state['unlocked'] ? 'previous_mastered' : 'previous_not_mastered';
        } else {
            $state['unlocked'] = !empty($prev_progress['completed_at']);
            $state['reason']   = $state['unlocked'] ? 'previous_completed' : 'previous_not_completed';
        }
        $state['blocking_lesson_id'] = $state['unlocked'] ? null : $prev_id;

        return $state;
    }

    public function is_lesson_unlocked($student_id, $lesson_id)
    {
        $s = $this->lesson_lock_state($student_id, $lesson_id);
        return !empty($s['found']) && !empty($s['unlocked']);
    }

    /**
     * حمولة التشغيل — والرابط الدائم لا يخرج منها.
     *
     * `B3.3` معيار قبوله «لا رابط تشغيل دائم أبدا». وكان `video_url` يخرج
     * عاريا من هنا إلى المتصفح: ينسخ من أدوات المطور ويوزع، ويعمل بعد
     * سنة لمن لا حساب له. فصار الملف المستضاف عندنا يخرج **رمزا موقعا**
     * صالحا خمس دقائق مقيدا بالدرس والطالب معا، ويجلب من
     * `Taqdar_gate::media()`.
     *
     * ويوتيوب وفيميو يخرجان كما هما ومعهما `protection = unprotected`
     * صراحة: مستضافان عند غيرنا برابط عام دائم بحكم تعريفه، وإخفاؤهما
     * خلف رمز يوهم بحماية غير قائمة — والواجهة تقول ذلك للمعلم في شاشة
     * الرفع بدل أن يكتشفه بعد أن يرفع.
     */
    private function playback_for($lesson, $student_id, $progress)
    {
        $out = array(
            'video_type' => $lesson['video_type'],
            'video_url'  => $lesson['video_url'],
            'audio_url'  => $lesson['audio_url'],
            'attachment' => $lesson['attachment'],
            'resume_at'  => $progress ? (int) $progress['position_sec'] : 0,
            'protection' => 'unprotected',
        );

        try {
            $this->load->model('taqdar_studio_model', 'tq_studio');
            $mode = $this->tq_studio->protection_for($lesson['video_type']);

            if ($mode === 'signed' && trim((string) $lesson['video_url']) !== '') {
                $t = $this->tq_studio->sign((int) $lesson['id'], (int) $student_id);
                $out['protection'] = 'signed';
                $out['video_url']  = site_url('taqdar_gate/media/' . $t['token']);
                $out['expires_in'] = (int) $t['ttl'];
            }
        } catch (Throwable $e) {
            /* تعذرت طبقة الحماية: يعرض الدرس كما كان يعرض قبلها. وحجب
               المحتوى عن طالب دفع ثمنه لأجل عطل في التوقيع أسوأ من رابط
               غير موقع — والعطل يسجل ليصلح. */
            log_message('error', 'TQ-MEDIA sign: ' . $e->getMessage());
        }

        return $out;
    }

    /* ================================================================
     *  التقييمات
     * ================================================================ */

    /** تقييم المراجعة الخاص بدرس. ينشئه عند الطلب إن كان للدرس أهداف وأسئلة. */
    public function review_assessment($lesson_id, $create = false)
    {
        $lesson_id = (int) $lesson_id;
        $row = $this->db->where('type', 'review')->where('lesson_id', $lesson_id)
                        ->get('assessments')->row_array();
        if ($row || !$create) return $row ? $row : null;

        if (!$this->count_review_questions($lesson_id)) return null;

        $this->db->insert('assessments', array(
            'type'           => 'review',
            'lesson_id'      => $lesson_id,
            'milestone_id'   => null,
            'path_id'        => null,
            'pass_mark'      => (int) $this->setting('mastery_pass_mark', 3),
            'time_limit_sec' => null,
        ));
        $id = $this->db->insert_id();
        $this->audit(null, 'assessment.autocreate', 'assessments:' . $id, null,
                     array('type' => 'review', 'lesson_id' => $lesson_id));

        return $this->db->where('id', $id)->get('assessments')->row_array();
    }

    /**
     * معرف تقييم مراجعة الدرس — بلا إنشاء.
     *
     * يقرأ مرة ويخبأ: `review_questions()` و`count_review_questions()`
     * تنادى كل منهما مرات في الطلب الواحد، وكلتاهما تحتاجه.
     */
    private function review_assessment_id($lesson_id)
    {
        $lesson_id = (int) $lesson_id;
        if (!isset($this->_review_as_cache[$lesson_id])) {
            $this->_review_as_cache[$lesson_id] = (int) $this->db
                ->select('id')->where('type', 'review')->where('lesson_id', $lesson_id)
                ->get('assessments')->row('id');
        }
        return $this->_review_as_cache[$lesson_id];
    }
    private $_review_as_cache = array();

    /**
     * كم سؤالا لبوابة هذا الدرس — من أي المصدرين.
     *
     * TQ-QSOURCE — **مصدران لا واحد، والمؤلف يعلو.**
     *
     * كانت أسئلة البوابة تشتق من الأهداف وحدها: `question.objective_id`
     * يشير إلى هدف، والهدف إلى درس. وهذا يكفي مسارا واحدا — أن يؤلف
     * السؤال في «بنك الأسئلة» ثم يربط بهدف من شاشة الربط — وهو مساران
     * وشاشتان لشيء واحد.
     *
     * فصار للمعلم أن يؤلف أسئلة الدرس في موضعه ودفعة واحدة
     * (`Taqdar_quiz_model`)، وهي تنسب إلى التقييم مباشرة
     * (`question.assessment_id`). وحين توجد فهي الاختبار، وحين لا توجد
     * تبقى أسئلة الأهداف تعمل كما كانت حرفا بحرف.
     *
     * ولم يصر هذا نظام اختبارات رابعا: التقييم هو `type='review'` نفسه،
     * فالقفل والمحاولات الثلاث ودفتر الأخطاء وخريطة الإتقان تعمل بلا
     * تغيير — انظر رأس [Taqdar_quiz_model.php].
     */
    public function count_review_questions($lesson_id)
    {
        $lesson_id = (int) $lesson_id;

        $as = $this->review_assessment_id($lesson_id);
        if ($as > 0) {
            $n = (int) $this->db->query(
                'SELECT COUNT(*) AS c FROM `question` WHERE `assessment_id` = ?',
                array($as))->row('c');
            if ($n > 0) return $n;
        }

        $r = $this->db->query(
            'SELECT COUNT(*) AS c
               FROM `question` q
               JOIN `objectives` o ON o.`id` = q.`objective_id`
              WHERE o.`lesson_id` = ?', array($lesson_id))->row_array();
        return (int) $r['c'];
    }

    /**
     * أسئلة بوابة الدرس — بلا `correct_answers` أبدا.
     *
     * المؤلفة تخرج **كلها** بترتيب مؤلفها: الاختبار مصمم، وقص خمسة منه
     * يجعل ما يقيسه غير ما كتب. والمشتقة من الأهداف تبقى على حدها
     * (`mastery_review_questions`، افتراضه خمسة) كما كانت.
     */
    public function review_questions($lesson_id, $limit = null)
    {
        $lesson_id = (int) $lesson_id;

        /* TQ-QIMG · العمود يضمن قبل ان يقرأ: `question` جدول موروث بلا
           هجرة، وقراءة عمود لا وجود له تسقط الشاشة كلها لا السؤال. */
        tq_qimage_ensure('question');

        $as = $this->review_assessment_id($lesson_id);
        $rows = array();

        if ($as > 0) {
            $rows = $this->db->query(
                'SELECT q.`id`, q.`title`, q.`type`, q.`number_of_options`, q.`options`,
                        q.`image`, q.`objective_id`, o.`text` AS objective_text, o.`at_second`
                   FROM `question` q
                   LEFT JOIN `objectives` o ON o.`id` = q.`objective_id`
                  WHERE q.`assessment_id` = ?
                  ORDER BY q.`order` ASC, q.`id` ASC',
                array($as))->result_array();
        }

        if (!$rows) {
            $limit = $limit ? (int) $limit : (int) $this->setting('mastery_review_questions', 5);
            $rows  = $this->db->query(
                'SELECT q.`id`, q.`title`, q.`type`, q.`number_of_options`, q.`options`,
                        q.`image`, q.`objective_id`, o.`text` AS objective_text, o.`at_second`
                   FROM `question` q
                   JOIN `objectives` o ON o.`id` = q.`objective_id`
                  WHERE o.`lesson_id` = ?
                  ORDER BY o.`at_second` ASC, q.`order` ASC, q.`id` ASC
                  LIMIT ' . $limit,
                array($lesson_id))->result_array();
        }

        foreach ($rows as &$r) {
            $r['options'] = $r['options'] ? json_decode($r['options'], true) : array();
            if (!is_array($r['options'])) $r['options'] = array();
            /* الرابط لا اسم الملف: الواجهة لا تعرف اين يعيش المجلد، وبناؤه
               فيها يعني معرفة مسار الخادم في جافاسكربت. */
            $r['image'] = tq_qimage_url($r['image']);
            $this->cast_ints($r, array('id', 'number_of_options', 'objective_id', 'at_second'));
        }
        unset($r);
        return $rows;
    }

    /** المقارنة بمنطق Academy نفسه، حتى لا تختلف نتيجة عن أخرى. */
    public function is_answer_correct($question, $given)
    {
        $correct = json_decode((string) $question['correct_answers'], true);
        if (!is_array($correct)) $correct = array();
        if (!is_array($given))   $given   = ($given === null || $given === '') ? array() : array($given);

        $given = array_values(array_map(function ($v) { return trim((string) $v); }, $given));
        if (!count($given) || !count($correct)) return 0;

        $type = (string) $question['type'];

        if ($type === 'fill_in_the_blank') {
            $correct = array_map(function ($v) { return mb_strtolower(trim((string) $v)); }, $correct);
            $given   = array_map(function ($v) { return mb_strtolower($v); }, $given);
            if (count($given) !== count($correct)) return 0;
            foreach ($given as $k => $g) {
                if (!isset($correct[$k]) || $g !== $correct[$k]) return 0;
            }
            return 1;
        }

        $correct = array_map(function ($v) { return trim((string) $v); }, $correct);

        if ($type === 'single_choice') {
            return in_array($given[0], $correct, true) ? 1 : 0;
        }

        // multiple_choice والافتراضي: تطابق المجموعتين تماما
        if (count($given) !== count($correct)) return 0;
        foreach ($given as $g) {
            if (!in_array($g, $correct, true)) return 0;
        }
        return 1;
    }

    /* ================================================================
     *  عقد /api/v1 — المسارات والدروس
     * ================================================================ */

    /**
     * مسارات الطالب المنشورة التي يستحقها، مع تقدمه في كل منها.
     *
     * الضم إلى `enrol` خارجي لا داخلي: التسجيل المفرد طريق إلى المحتوى
     * لا شرط له. فالمشترك اشتراكا نشطا يستحق المسار وإن لم يكن له صف في
     * `enrol` قط — ولو بقي الضم داخليا لفتح له الدرس ولم ير مسارا واحدا.
     *
     * والحكم النهائي واحد لا اثنان: `is_entitled()` هي مصدر القرار هنا كما هي
     * مصدره في `get_lesson()`، فلا تفترق قائمة عن قفل.
     */
    public function get_paths($student_id)
    {
        $student_id = (int) $student_id;
        if (!$student_id) return array();

        $sql = 'SELECT p.`id`, p.`title`, p.`price`, p.`status`, p.`expected_weeks`,
                       p.`subject_id`, p.`grade_id`, p.`teacher_id`, p.`course_id`,
                       s.`name_ar` AS subject_ar, s.`name_en` AS subject_en,
                       g.`name_ar` AS grade_ar,   g.`name_en` AS grade_en,
                       TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) AS teacher_name,
                       c.`thumbnail`,
                       MAX(CASE WHEN e.`id` IS NULL THEN 0 ELSE 1 END) AS is_enrolled
                FROM `paths` p
                LEFT JOIN `subjects` s ON s.`id` = p.`subject_id`
                LEFT JOIN `grades`   g ON g.`id` = p.`grade_id`
                LEFT JOIN `users`    u ON u.`id` = p.`teacher_id`
                LEFT JOIN `course`   c ON c.`id` = p.`course_id`
                LEFT JOIN `enrol`    e ON e.`course_id` = p.`course_id` AND e.`user_id` = ?
                WHERE p.`status` = "published"
                GROUP BY p.`id`
                ORDER BY p.`id` ASC';
        $rows = $this->db->query($sql, array($student_id))->result_array();

        $out = array();
        foreach ($rows as $r) {
            $this->cast_ints($r, array('id', 'price', 'expected_weeks', 'subject_id',
                                       'grade_id', 'teacher_id', 'course_id',
                                       'is_enrolled')); // price بالهللات

            // «مسجل أو مستحق بالاشتراك» — و`is_entitled` تحتويهما معا،
            // ومعهما انتهاء صلاحية التسجيل الذي كان الضم الداخلي يتجاهله.
            if (!$this->is_entitled($student_id, $r['course_id'])) continue;

            $r['enrolled'] = ((int) $r['is_enrolled'] === 1);
            unset($r['is_enrolled']);
            $r['progress'] = $this->path_progress($student_id, $r['course_id']);
            $out[] = $r;
        }
        return $out;
    }

    /** تقدم الطالب داخل مقرر: العدد والنسبة والدرس التالي المفتوح. */
    public function path_progress($student_id, $course_id)
    {
        $lessons = $this->ordered_lessons($course_id);
        $total   = count($lessons);
        if (!$total) {
            return array('total_lessons' => 0, 'mastered' => 0, 'completed' => 0,
                         'percent' => 0, 'next_lesson_id' => null);
        }

        $ids = array();
        foreach ($lessons as $l) $ids[] = (int) $l['id'];

        $this->db->select('lesson_id, completed_at, mastered_at')
                 ->where('student_id', (int) $student_id)
                 ->where_in('lesson_id', $ids);
        $prog = $this->db->get('lesson_progress')->result_array();

        $map = array();
        foreach ($prog as $p) $map[(int) $p['lesson_id']] = $p;

        $mastered = 0; $completed = 0; $next = null;
        foreach ($ids as $id) {
            $has = isset($map[$id]) ? $map[$id] : null;
            if ($has && !empty($has['mastered_at']))  $mastered++;
            if ($has && !empty($has['completed_at'])) $completed++;
            if ($next === null && (!$has || empty($has['mastered_at']))) $next = $id;
        }

        return array(
            'total_lessons'  => $total,
            'mastered'       => $mastered,
            'completed'      => $completed,
            'percent'        => (int) floor(($mastered * 100) / $total),
            'next_lesson_id' => $next,
        );
    }

    /** تفاصيل مسار: محطاته ودروسه وحالة قفل كل درس للطالب. */
    public function get_path($id, $student_id = null)
    {
        $id  = (int) $id;
        $sql = 'SELECT p.*, s.`name_ar` AS subject_ar, g.`name_ar` AS grade_ar,
                       TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) AS teacher_name,
                       c.`thumbnail`, c.`short_description`
                FROM `paths` p
                LEFT JOIN `subjects` s ON s.`id` = p.`subject_id`
                LEFT JOIN `grades`   g ON g.`id` = p.`grade_id`
                LEFT JOIN `users`    u ON u.`id` = p.`teacher_id`
                LEFT JOIN `course`   c ON c.`id` = p.`course_id`
                WHERE p.`id` = ?';
        $path = $this->db->query($sql, array($id))->row_array();
        if (!$path) return $this->error('NOT_FOUND', array('entity' => 'paths:' . $id));

        $this->cast_ints($path, array('id', 'price', 'expected_weeks', 'subject_id',
                                      'grade_id', 'teacher_id', 'course_id'));

        $path['milestones'] = $this->db->query(
            'SELECT `id`, `path_id`, `order`, `title`, `checkpoint_assessment_id`, `section_id`
             FROM `milestones` WHERE `path_id` = ? ORDER BY `order` ASC, `id` ASC',
            array($id))->result_array();
        foreach ($path['milestones'] as &$m) {
            $this->cast_ints($m, array('id', 'path_id', 'order', 'checkpoint_assessment_id', 'section_id'));
        }
        unset($m);

        $lessons = $this->ordered_lessons($path['course_id']);
        foreach ($lessons as &$l) {
            /* من `lesson_duration()` لا من النص وحده: العمود الرقمي هو
               الذي يكتبه المشغل حين يكتشف المدة، وقراءة النص هنا تعطي
               صفرا على درس صارت مدته معروفة. */
            $l['duration_sec'] = $this->lesson_duration($l);
            $l['has_review']   = (bool) $this->review_assessment($l['id'], false);
            if ($student_id) {
                $st = $this->lesson_lock_state($student_id, $l['id']);
                $pr = $this->db->where('student_id', (int) $student_id)->where('lesson_id', (int) $l['id'])
                               ->get('lesson_progress')->row_array();
                $l['unlocked']     = !empty($st['unlocked']);
                $l['lock_reason']  = $st['reason'];
                $l['mastered_at']  = $pr ? $pr['mastered_at'] : null;
                $l['completed_at'] = $pr ? $pr['completed_at'] : null;
                $l['position_sec'] = $pr ? (int) $pr['position_sec'] : 0;
            }
        }
        unset($l);
        $path['lessons'] = $lessons;

        if ($student_id) {
            $path['entitled'] = $this->is_entitled($student_id, $path['course_id']);
            $path['progress'] = $this->path_progress($student_id, $path['course_id']);
        }
        return $path;
    }

    /**
     * درس واحد للطالب.
     *
     * لا يعاد رابط تشغيل إلا لمن استحق وفتح له الدرس؛ وأي غير ذلك
     * مغلف خطأ يحمل MASTERY_LOCKED أو NOT_ENTITLED.
     */
    public function get_lesson($id, $student_id)
    {
        $id         = (int) $id;
        $student_id = (int) $student_id;

        $state = $this->lesson_lock_state($student_id, $id);
        if (empty($state['found'])) {
            return $this->error('NOT_FOUND', array('entity' => 'lesson:' . $id));
        }
        $lesson = $state['lesson'];

        $free = ((int) $lesson['is_free'] === 1);
        if (!$free && !$this->is_entitled($student_id, $lesson['course_id'])) {
            return $this->error('NOT_ENTITLED', array(
                'lesson_id' => $id,
                'course_id' => (int) $lesson['course_id'],
            ));
        }

        if (empty($state['unlocked'])) {
            // لا رابط تشغيل، ولا حتى ملخص الدرس — القفل قفل.
            return $this->error('MASTERY_LOCKED', array(
                'lesson_id'          => $id,
                'blocking_lesson_id' => isset($state['blocking_lesson_id']) ? $state['blocking_lesson_id'] : null,
                'reason'             => $state['reason'],
            ));
        }

        $progress = $this->db->where('student_id', $student_id)->where('lesson_id', $id)
                             ->get('lesson_progress')->row_array();

        $objectives = $this->db->query(
            'SELECT `id`, `text`, `at_second` FROM `objectives`
             WHERE `lesson_id` = ? ORDER BY `at_second` ASC, `id` ASC',
            array($id))->result_array();
        foreach ($objectives as &$o) $this->cast_ints($o, array('id', 'at_second'));
        unset($o);

        $review   = $this->review_assessment($id, true);
        $attempts = 0;
        if ($review) {
            $attempts = (int) $this->db->where('assessment_id', (int) $review['id'])
                                       ->where('student_id', $student_id)
                                       ->count_all_results('attempts');
        }

        /* النسبة تحسب هنا كما تحسب في مسار الكتابة، بالدالة نفسها.
           وكانت لا ترسل أصلا: `progress` يخرج بأعمدته الخام، فتقرأ
           الشاشة `p.percent` غير موجودة و`p.covered_sec` غير موجودة
           و`duration_sec` صفرا — فتطبع **٪٠ على درس أتمه صاحبه**، ولا
           يعود إلى الصواب إلا بعد أول نبضة تشغيل. ومن فتح درسا أتمه
           ولم يشغله يقرأ صفرا إلى أن يغادر. */
        $view = $this->progress_view($lesson, $student_id, $progress);

        return array(
            'lesson' => array(
                'id'           => (int) $lesson['id'],
                'title'        => $lesson['title'],
                'course_id'    => (int) $lesson['course_id'],
                'section_id'   => (int) $lesson['section_id'],
                'lesson_type'  => $lesson['lesson_type'],
                /* المعتمدة لا المكتوبة: هي التي يقسم عليها القفل دلاءه،
                   فتكون هي التي تقسم عليها الشاشة دلاءها. */
                'duration'     => $view['duration_sec'] > 0
                                    ? $this->hms_of($view['duration_sec'])
                                    : $lesson['duration'],
                'duration_sec' => $view['duration_sec'],
                'summary'      => $lesson['summary'],
                'is_free'      => (int) $lesson['is_free'],
                /* هل يقاس هذا المصدر أصلا؟ الشاشة تحتاجه لتقرر أتعد
                   بقياس أم تعرض إقرارا — ولا تستنتجه من الرابط. */
                'trackable'    => $this->trackable($lesson) ? 1 : 0,
            ),
            'playback' => $this->playback_for($lesson, $student_id, $progress),
            'objectives' => $objectives,
            'progress'   => array(
                'position_sec'  => $progress ? (int) $progress['position_sec']  : 0,
                'watch_seconds' => $progress ? (int) $progress['watch_seconds'] : 0,
                'covered_sec'   => $view['covered_sec'],
                'percent'       => $view['percent'],
                'completed_at'  => $progress ? $progress['completed_at'] : null,
                'mastered_at'   => $progress ? $progress['mastered_at']  : null,
            ),
            /* العدد يحسب لا يقرأ من إعداد.
               كان `mastery_review_questions` — رقم عام افتراضه خمسة —
               فتقول الشاشة «خمسة أسئلة قصيرة» على اختبار فيه ثلاثة، أو
               فيه عشرة. والعدد المعروض قبل البدء وعد، وإخلافه يقرأ عطلا. */
            'review' => $review ? array(
                'assessment_id'  => (int) $review['id'],
                'question_count' => count($this->review_questions($id)),
                'pass_mark'      => $this->pass_mark($review),
                'attempts'       => $attempts,
            ) : null,
            'prev_lesson_id' => $state['prev_lesson_id'],
            'next_lesson_id' => $state['next_lesson_id'],
            'unlocked'       => true,
        );
    }

    /** حفظ موضع المشاهدة وزمنها. الحفظ نفسه يمر بالقفل. */
    /**
     * @param array $covered   دلاء العشر ثوان التي مر عليها التشغيل فعلا
     *                         منذ آخر نبضة — انظر TQ-COVERAGE أدناه.
     * @param int   $media_sec طول المقطع كما أعلنه مشغل هذا الطالب، أو
     *                         صفر إن لم يعلن — انظر TQ-DURATION أدناه.
     */
    public function save_progress($student_id, $lesson_id, $position_sec, $watched_delta, $covered = array(), $media_sec = 0)
    {
        $student_id   = (int) $student_id;
        $lesson_id    = (int) $lesson_id;
        $position_sec = max(0, (int) $position_sec);
        // حد أعلى للزيادة الواحدة يمنع تضخيم زمن المشاهدة من العميل
        $watched_delta = max(0, min(300, (int) $watched_delta));

        $state = $this->lesson_lock_state($student_id, $lesson_id);
        if (empty($state['found'])) {
            return $this->error('NOT_FOUND', array('entity' => 'lesson:' . $lesson_id));
        }
        $lesson = $state['lesson'];

        if ((int) $lesson['is_free'] !== 1 && !$this->is_entitled($student_id, $lesson['course_id'])) {
            return $this->error('NOT_ENTITLED', array('lesson_id' => $lesson_id));
        }
        if (empty($state['unlocked'])) {
            return $this->error('MASTERY_LOCKED', array(
                'lesson_id'          => $lesson_id,
                'blocking_lesson_id' => isset($state['blocking_lesson_id']) ? $state['blocking_lesson_id'] : null,
            ));
        }

        $this->ensure_progress_schema();

        $row = $this->db->where('student_id', $student_id)->where('lesson_id', $lesson_id)
                        ->get('lesson_progress')->row_array();

        /* TQ-DURATION — المدة المعتمدة لا المكتوبة.
           المكتوبة **ادعاء** والمقيسة **شهادة**، وحين تختلفان يخسر
           الطالب: معلم كتب `00:12:00` على مقطع طوله دقيقتان وثمان
           وأربعون ثانية يجعل تسعين بالمئة رقما لا يبلغ أبدا، فيبقى
           الدرس التالي مقفلا على كل من اشترك ولا شيء يقول لماذا.
           و`effective_duration()` هي من يفصل — بشهادة طالبين لا بواحد. */
        $media = max(0, (int) $media_sec);
        $total = $this->effective_duration($lesson, $student_id, $media);
        $ratio = (float) $this->setting('lesson_complete_ratio', 0.9);

        /* ---- الزيادة تقاس بزمن الجدار ----
           `watched_delta` يأتي من المتصفح، وكان يقبل حتى ٣٠٠ ثانية للنبضة
           بلا سؤال: نداء واحد كل ثانية يدعي خمس دقائق يكمل درسا كاملا في
           دقيقتين. فالسقف الآن هو ما مضى فعلا منذ آخر نبضة (ومعه هامش
           للسرعة المضاعفة وتأخر الشبكة)، لا رقم ثابت. */
        $last = $row && !empty($row['last_ping_at']) ? strtotime($row['last_ping_at']) : 0;

        /* النبضة الأولى بلا مرجع: لا صف سابق فلا زمن يقاس عليه. فتأخذ
           ميزانية ثابتة صغيرة بدل أن يصدق ما يدعيه العميل — وإلا كان
           **أول نداء** هو الثغرة: يرسل الدرس كاملا فيكمله في لحظة. */
        $wall = $last > 0 ? max(0, time() - $last) : self::FIRST_PING;
        $watched_delta = min($watched_delta, (int) ($wall * 2.5) + 5);

        $watch = ($row ? (int) $row['watch_seconds'] : 0) + $watched_delta;

        /* ---- التغطية: أي أجزاء الدرس شوهدت فعلا ----
           TQ-COVERAGE — العداد وحده يكذب. الطالب يفتح الدرس ويقفز إلى
           آخره ويتركه دقيقة، فيجمع العداد ثواني لم يشاهد فيها شيئا؛
           والأسوأ أن السحب إلى النهاية كان يكفي وحده. فالإتمام يقاس
           بخريطة دلاء عشر ثوان: كل دلو يعلم حين يمر عليه التشغيل فعلا،
           والإتمام أن تعلم منها نسبة `lesson_complete_ratio`.

           والخريطة تخزن ست عشرية: بت لكل دلو، فدرس ساعتين ١٨٠ بايتا. */
        $segments = $row ? (string) $row['segments'] : '';
        $buckets  = $total > 0 ? (int) ceil($total / self::BUCKET) : 0;

        if ($buckets > 0 && is_array($covered) && $covered) {
            /* الدلاء تحد بزمن الجدار كما يحد العداد.
               بدونها يرسل عميل معدل خريطة الدرس كاملة في نداء واحد
               فيكمله في لحظة — وهو الباب نفسه الذي سد على `watched_delta`.
               والميزانية: ما مضى مضروبا في السرعة القصوى، وفوقه دلوان
               هامشا للشبكة. والفجوة تحسب بخمس دقائق على الأكثر: العميل
               ينبض كل خمس عشرة ثانية ما دام يعمل، وفجوة أطول تعني أن
               التشغيل كان متوقفا. */
            $span  = min($wall, 300);
            $allow = (int) ceil(($span * 2.5 + 20) / self::BUCKET);
            if (count($covered) > $allow) $covered = array_slice($covered, 0, $allow);

            $segments = $this->seg_add($segments, $covered, $buckets);
        }
        $seen = $buckets > 0 ? $this->seg_count($segments, $buckets) : 0;

        $completed_at = $row ? $row['completed_at'] : null;
        if (!$completed_at && $buckets > 0) {
            if ($seen >= (int) ceil($buckets * $ratio)) $completed_at = $this->now();
        } elseif (!$completed_at && $total > 0) {
            /* لا خريطة (عميل قديم لا يرسل التغطية): العداد كما كان. */
            if ($watch >= (int) ceil($total * $ratio)) $completed_at = $this->now();
        }

        $data = array(
            'position_sec'  => $position_sec,
            'watch_seconds' => $watch,
            'completed_at'  => $completed_at,
            'segments'      => $segments !== '' ? $segments : null,
            'last_ping_at'  => $this->now(),
        );

        /* شهادة هذا الطالب على طول المقطع — تكتب ولا يكتب فوقها صفر:
           نبضة تأتي قبل أن يعلن المشغل مدته لا تمحو ما أعلنه قبلها. */
        if ($media > 0) $data['media_sec'] = $media;

        /* TQ-BLIND — ختم العجز.
           «لا قياس» تعني: لا مدة أعلنها مشغله، ولا دلو تعلم، ولا موضع
           تحرك. ومتى وصل أي منها محي الختم — فالعجز حال قائمة لا حكم
           سابق، ومن حجب عنه السكربت دقيقة ثم وصل لا يعامل معاملة من لم
           يصله شيء. وبهذا الختم وحده يقبل إقرار الإتمام على مصدر
           يفترض أنه يقاس (`confirm_complete`). */
        $measured = ($media > 0) || ($seen > 0) || ($position_sec > 0);
        if ($measured) {
            $data['blind_at'] = null;
        } elseif (!$row || empty($row['blind_at'])) {
            $data['blind_at'] = $this->now();
        }

        if ($row) {
            $this->db->where('id', (int) $row['id'])->update('lesson_progress', $data);
        } else {
            $data['student_id'] = $student_id;
            $data['lesson_id']  = $lesson_id;
            $data['mastered_at'] = null;
            $this->db->insert('lesson_progress', $data);
        }

        // الجديد يملي على القديم فلا يتناقض رقمان لطالب واحد
        $this->sync_watch_history($student_id, (int) $lesson['course_id'], $lesson_id, $position_sec, (bool) $completed_at);

        /* سجل اليوم في دفتر النشاط — وهو مصدر السلسلة وحلقة الهدف.
           يكتب ولو كانت الزيادة صفرا: من فتح درسا اليوم حضر، والسلسلة عن
           الحضور لا عن بلوغ عتبة. */
        $this->touch_day($student_id, 'seconds', $watched_delta);

        /* النسبة من التغطية لا من العداد: هي ما يقرؤه الطالب، ويجب أن
           تكون هي نفسها ما يفتح به الدرس التالي — رقمان يفترقان يجعلان
           «٪١٠٠» تقف أمام درس مقفل. */
        $percent = $completed_at
            ? 100
            : ($buckets > 0
                ? min(100, (int) floor($seen * 100 / $buckets))
                : ($total > 0 ? min(100, (int) floor($watch * 100 / $total)) : 0));

        return array(
            'lesson_id'     => $lesson_id,
            'position_sec'  => $position_sec,
            'watch_seconds' => $watch,
            /* المعتمدة لا المكتوبة: هي التي حسبت عليها النسبة، فتعود
               إلى الشاشة لتحسب عليها هي أيضا. رقمان يفترقان يجعلان
               «٪١٠٠» تقف أمام درس مقفل. */
            'duration_sec'  => $total,
            'buckets'       => $buckets,
            'covered_sec'   => $seen * self::BUCKET,
            'percent'       => $percent,
            'completed_at'  => $completed_at,
            'mastered_at'   => $row ? $row['mastered_at'] : null,
        );
    }

    /**
     * إتمام يعلنه الطالب بنفسه — للمصادر التي لا تعلن موضع تشغيلها.
     *
     * درايف والإطار الخارجي لا يعطيان موضعا ولا مدة، فلا شيء يقاس. وأمام
     * ذلك بابان: أن يبقى الدرس التالي مقفلا إلى الأبد، أو أن يقر الطالب
     * بإتمامه. والثاني إقرار لا قياس — ويقال له ذلك في الشاشة، ويكتب في
     * السجل ليعرف المعلم أي إتمام قيس وأي إتمام أقر.
     */
    public function confirm_complete($student_id, $lesson_id)
    {
        $this->ensure_progress_schema();

        $student_id = (int) $student_id;
        $lesson_id  = (int) $lesson_id;

        $state = $this->lesson_lock_state($student_id, $lesson_id);
        if (empty($state['found'])) {
            return $this->error('NOT_FOUND', array('entity' => 'lesson:' . $lesson_id));
        }
        $lesson = $state['lesson'];

        if ((int) $lesson['is_free'] !== 1 && !$this->is_entitled($student_id, $lesson['course_id'])) {
            return $this->error('NOT_ENTITLED', array('lesson_id' => $lesson_id));
        }
        if (empty($state['unlocked'])) {
            return $this->error('MASTERY_LOCKED', array('lesson_id' => $lesson_id));
        }

        $row = $this->db->where('student_id', $student_id)->where('lesson_id', $lesson_id)
                        ->get('lesson_progress')->row_array();
        $now = $this->now();

        /* الإقرار لا يقبل على مصدر يقاس: من يستطيع القياس يقاس. وبلا هذا
           الشرط يصير الزر مخرجا من كل درس فيديو على المنصة.

           TQ-BLIND — **إلا أن يكون القياس تعذر فعلا**.
           فالمصدر «يقاس» وصف للنوع لا للواقع: سكربت يوتيوب يحجب في شبكة
           مدرسة، والفيديو يحذف من مصدره، والتضمين يرفض — فلا موضع ولا
           مدة ولا رسالة خطأ. وكان هذا الشرط وحده يجعل من وقع في ذلك
           محبوسا إلى الأبد: لا شريط يتحرك ولا زر يضغط ولا درس تال.

           والعجز لا يؤخذ من دعوى العميل: `blind_at` يختمه **الخادم**
           عند أول نبضة لا يصحبها قياس، ويمحوه عند أول قياس يصل. فالشرط
           أن يكون العجز قائما، وأن يكون قد مضى عليه وقت حقيقي — ومن
           عطل سكربته ليأخذ المخرج ينتظره كما ينتظره من حجب عنه. */
        if ($this->trackable($lesson) && !$this->blind_enough($row)) {
            return $this->error('VALIDATION', array(
                'reason'    => 'measurable_source',
                'lesson_id' => $lesson_id,
            ));
        }

        if ($row) {
            if (!empty($row['completed_at'])) {
                return array('lesson_id' => $lesson_id, 'completed_at' => $row['completed_at'],
                             'declared' => true);
            }
            $this->db->where('id', (int) $row['id'])->update('lesson_progress', array(
                'completed_at' => $now, 'declared_at' => $now, 'last_ping_at' => $now,
            ));
        } else {
            $this->db->insert('lesson_progress', array(
                'student_id' => $student_id, 'lesson_id' => $lesson_id,
                'position_sec' => 0, 'watch_seconds' => 0,
                'completed_at' => $now, 'declared_at' => $now, 'last_ping_at' => $now,
                'mastered_at' => null,
            ));
        }

        $this->sync_watch_history($student_id, (int) $lesson['course_id'], $lesson_id, 0, true);
        $this->audit($student_id, 'lesson.declare_complete', 'lesson:' . $lesson_id, null,
                     array('source'   => $lesson['video_type'],
                           /* ولماذا قبل: نوع لا يقاس أصلا، أم نوع يقاس
                              عجز عنه؟ الثاني عطل يصلح، والأول تصميم. */
                           'reason'   => $this->trackable($lesson) ? 'no_signal' : 'unmeasurable',
                           'blind_at' => $row && !empty($row['blind_at']) ? $row['blind_at'] : null));

        return array('lesson_id' => $lesson_id, 'completed_at' => $now, 'declared' => true);
    }

    /**
     * أرقام التقدم كما تعرض — من المسارين معا.
     *
     * مسار الكتابة (`save_progress`) ومسار القراءة (`get_lesson`) كانا
     * يحسبان شيئين مختلفين: الأول تغطية ونسبة، والثاني لا شيء. فيقرأ
     * الطالب صفرا على درس أتمه حتى تعود أول نبضة. والقاعدة التي تحكم
     * هذا الملف كله واحدة: **الرقم الذي يقرؤه الطالب هو الرقم الذي
     * يقرؤه القفل** — ورقمان يحسبان في موضعين يفترقان عند أول تعديل.
     */
    private function progress_view($lesson, $student_id, $row)
    {
        $media = $row && isset($row['media_sec']) ? (int) $row['media_sec'] : 0;
        $total = $this->effective_duration($lesson, $student_id, $media);

        $buckets = $total > 0 ? (int) ceil($total / self::BUCKET) : 0;
        $seg     = ($row && isset($row['segments'])) ? (string) $row['segments'] : '';
        $seen    = $buckets > 0 ? $this->seg_count($seg, $buckets) : 0;
        $watch   = $row ? (int) $row['watch_seconds'] : 0;

        /* درس أتم لا ينزل عن المئة مهما قالت خريطته: قد يكون أتم إقرارا
           (`declared_at`) فلا دلو فيه أصلا، أو أتم قبل أن تعرف مدته. */
        if ($row && !empty($row['completed_at'])) {
            $percent = 100;
        } elseif ($buckets > 0) {
            $percent = min(100, (int) floor($seen * 100 / $buckets));
        } else {
            $percent = $total > 0 ? min(100, (int) floor($watch * 100 / $total)) : 0;
        }

        return array(
            'duration_sec' => $total,
            'buckets'      => $buckets,
            'covered_sec'  => $seen * self::BUCKET,
            'percent'      => $percent,
        );
    }

    /** ثوان إلى `HH:MM:SS` — للعرض. */
    private function hms_of($sec)
    {
        $sec = max(0, (int) $sec);
        return sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60);
    }

    /**
     * مهلة العجز — كم يمضي على ختم `blind_at` قبل أن يقبل الإقرار.
     *
     * دقيقتان: تكفي أن يحدث الطالب صفحته وتتعافى شبكته، ولا تكفي أن
     * يمر بها على درس بعد درس ليفتح المقرر كله.
     */
    const BLIND_GRACE = 120;

    /** هل عجز القياس عن هذا الطالب في هذا الدرس عجزا قائما ومعمرا؟ */
    private function blind_enough($row)
    {
        if (!$row || empty($row['blind_at'])) return false;
        /* والعجز يبطل بأول قياس: `save_progress` يمحو الختم حينها،
           وهذان الشرطان احتياط لصف كتب قبل ذلك المحو. */
        if ((int) $row['position_sec'] > 0) return false;
        if (isset($row['media_sec']) && (int) $row['media_sec'] > 0) return false;
        if (trim((string) (isset($row['segments']) ? $row['segments'] : '')) !== '') return false;

        return (time() - strtotime($row['blind_at'])) >= self::BLIND_GRACE;
    }

    /**
     * يسجل المدة التي اكتشفها المشغل في المتصفح.
     *
     * يوتيوب وفيميو يعلنان المدة لمشغلهما، ولا يعلنانها للخادم إلا بمفتاح
     * واجهة برمجة لا يملكه هذا التركيب — وكل درس في القاعدة `00:00:00`،
     * أي أن `completed_at` لم يكن ليكتب أبدا مهما شوهد.
     *
     * والكتابة مشروطة بأن المخزن صفر: مدة كتبها صاحبها بيده لا يدهسها
     * رقم من متصفح زائر.
     */
    public function record_duration($lesson_id, $seconds)
    {
        try {
            $this->load->model('taqdar_curriculum_model', 'tq_curric');
            return $this->tq_curric->record_duration($lesson_id, $seconds);
        } catch (Throwable $e) {
            log_message('error', 'TQ-DUR: ' . $e->getMessage());
            return false;
        }
    }

    /* ================================================================
     *  التغطية — خريطة دلاء عشر ثوان
     * ================================================================ */

    /** ثواني الدلو الواحد. عشر: أدق من دقيقة، وأرخص من ثانية. */
    const BUCKET = 10;

    /**
     * ميزانية النبضة الأولى بالثواني.
     *
     * لا صف سابق فلا `last_ping_at` يقاس عليه، فتأخذ رقما ثابتا بدل أن
     * يصدق ما يدعيه العميل. والعميل ينبض بعد خمس عشرة ثانية من بدء
     * التشغيل، فثلاثون تسع أبطأ شبكة ولا تكفي لدرس.
     */
    const FIRST_PING = 30;

    /**
     * كم شاهدا يلزم قبل أن يصحح قياس ما كتبته يد؟
     *
     * اثنان افتراضا. وواحد يكفي منصة صغيرة لا يمر على الدرس فيها إلا
     * طالب واحد، ويضبط من `settings` — ولكن الافتراض ليس واحدا: طالب
     * واحد يعدل جافاسكربت متصفحه يستطيع أن يعلن أن مقطع اثنتي عشرة
     * دقيقة طوله عشر ثوان، فيكمله في لحظة **ويفسد رقمه على كل زملائه**.
     * واشتراط شاهدين مستقلين يجعل ذلك تواطؤا لا عبثا.
     */
    private function witnesses_needed()
    {
        $n = (int) $this->setting('tq_duration_witnesses', 2);
        return $n > 0 ? $n : 2;
    }

    /** هامش الاتفاق بين قياسين — عشرة بالمئة، وخمس ثوان حدا أدنى. */
    private function slack($sec)
    {
        return max(5, (int) round($sec * 0.10));
    }

    /**
     * TQ-DURATION — المدة التي يقاس عليها القفل.
     *
     * ═══ المشكلة ═══
     *
     * `lesson.duration` حقل يكتب بيد، و`duration_sec` مرآته الرقمية.
     * وكلاهما **ادعاء عن المقطع لا قياس له**. والمقطع نفسه يعرف طوله
     * ويعلنه لمشغله، ولا يعلنه للخادم: يوتيوب لا يرد المدة إلا بمفتاح
     * واجهة برمجة لا يملكه هذا التركيب.
     *
     * فحين يختلف الادعاء عن الحقيقة يقع أحد عطلين، وكلاهما صامت:
     *
     *   مكتوب أطول من الحقيقة  →  النسبة لا تبلغ الحد أبدا، فيبقى
     *                             الدرس التالي مقفلا على من شاهد كل شيء.
     *   مكتوب أصفار (وهو حال كل درس في القاعدة)  →  لا دلاء تعد أصلا،
     *                             فلا تغطية ولا إتمام ولا اختبار.
     *
     * ═══ القاعدة ═══
     *
     *   لا قياس عندي            →  المكتوب، فهو كل ما نملك.
     *   قياسي يوافق المكتوب     →  المكتوب، ولا استعلام يجرى أصلا.
     *   قياسي يخالفه            →  أسأل بقية الشهود:
     *        اتفق منهم النصاب   →  قولهم، وأصحح صف الدرس به.
     *        لم يتفق            →  المكتوب إن كان، وإلا فقياسي أنا.
     *
     * والأخيرة هي البداية: درس جديد بلا مدة مكتوبة يمشي بقياس أول
     * طالب — وحده، ولا يكتب في الصف حتى يصدقه ثان. فمن عبث لم يفسد
     * إلا رقم نفسه، وحد زمن الجدار (`$wall`) يمنعه أن ينتفع به.
     */
    private function effective_duration($lesson, $student_id, $media)
    {
        $authored = $this->lesson_duration($lesson);
        $media    = max(0, (int) $media);

        /* لا شهادة عندي: المكتوب وحده. */
        if ($media <= 0) return $authored;

        /* شهادتي توافق المكتوب: لا خلاف يفصل فيه، ولا استعلام يجرى.
           والشرط هنا لا هناك عمدا — هذه الدالة تنادى في كل نبضة من كل
           طالب، واستعلام يجري بلا سبب في مسار الكتابة يثقل بلا فائدة. */
        if ($authored > 0 && abs($authored - $media) <= $this->slack($media)) {
            return $authored;
        }

        $agreed = $this->agreed_media((int) $lesson['id'], (int) $student_id, $media);
        if ($agreed > 0) {
            if ($authored <= 0 || abs($authored - $agreed) > $this->slack($agreed)) {
                $this->adopt_duration((int) $lesson['id'], $agreed, $authored);
            }
            return $agreed;
        }

        return $authored > 0 ? $authored : $media;
    }

    /**
     * أطول قياس اتفق عليه النصاب من الشهود — أو صفر.
     *
     * والعنقود لا المتوسط: قيمة شاذة واحدة تجر المتوسط وتفسده، ولا
     * تحرك عنقودا. وكل قياس يعد من يوافقه بهامش، وأكبر عنقود يفوز،
     * ووسيطه هو الرقم — لا طرفه.
     *
     * والشهود متمايزون بحكم المخطط: `lesson_progress` صف لكل (طالب،
     * درس)، فلا يشهد أحد مرتين ولو نبض ألفا.
     */
    private function agreed_media($lesson_id, $student_id, $mine)
    {
        $need = $this->witnesses_needed();

        /* العمود يركب عند أول نبضة تقدم (`ensure_progress_schema`)، وهذه
           الدالة تنادى من مسار القراءة أيضا. فتركيب لم يفتح فيه درس بعد
           يقرأ عمودا غير موجود — واستثناء يبيض شاشة الدرس أسوأ من مدة
           غير مصدقة. */
        try {
            $rows = $this->db->select('student_id, media_sec')
                             ->where('lesson_id', (int) $lesson_id)
                             ->where('media_sec >', 0)
                             ->limit(60)
                             ->get('lesson_progress')->result_array();
        } catch (Throwable $e) {
            return 0;
        }

        $vals = array();
        foreach ($rows as $r) $vals[(int) $r['student_id']] = (int) $r['media_sec'];
        /* شهادتي قد لا تكون كتبت بعد — هذه النبضة هي التي تكتبها. */
        if ($mine > 0) $vals[(int) $student_id] = (int) $mine;

        if (count($vals) < $need) return 0;

        $best = 0; $best_n = 0;
        foreach ($vals as $v) {
            $grp = array();
            foreach ($vals as $w) {
                if (abs($w - $v) <= $this->slack($v)) $grp[] = $w;
            }
            if (count($grp) > $best_n) {
                sort($grp);
                $best_n = count($grp);
                $best   = $grp[intdiv(count($grp), 2)];
            }
        }
        return $best_n >= $need ? (int) $best : 0;
    }

    /**
     * يصحح مدة الدرس بما شهد به الشهود، ويترك أثرا.
     *
     * والأثر ليس زينة: هذا تعديل على صف يملكه معلم، يقع بلا أن يطلبه
     * أحد. فمن فتح الدرس غدا ووجد `00:02:48` مكان `00:12:00` الذي
     * كتبه بيده يجب أن يجد في السجل من غيره ولماذا — وإلا ظنه ضياعا
     * وأعاد كتابة الخطأ.
     */
    private function adopt_duration($lesson_id, $seconds, $was)
    {
        $lesson_id = (int) $lesson_id;
        $seconds   = (int) $seconds;
        if ($lesson_id <= 0 || $seconds <= 0 || $seconds > 28800) return;

        try {
            $this->db->where('id', $lesson_id)->update('lesson', array(
                'duration_sec' => $seconds,
                'duration'     => sprintf('%02d:%02d:%02d',
                                          intdiv($seconds, 3600),
                                          intdiv($seconds % 3600, 60),
                                          $seconds % 60),
            ));
            $this->audit(0, 'lesson.duration.measured', 'lesson:' . $lesson_id,
                array('duration_sec' => (int) $was),
                array('duration_sec' => $seconds, 'by' => 'players'));
        } catch (Throwable $e) {
            log_message('error', 'TQ-DURATION: ' . $e->getMessage());
        }
    }

    /** مدة الدرس بالثواني — العمود الرقمي أولا، والنصي مرآة قديمة. */
    public function lesson_duration($lesson)
    {
        if (isset($lesson['duration_sec']) && (int) $lesson['duration_sec'] > 0) {
            return (int) $lesson['duration_sec'];
        }
        return $this->duration_seconds(isset($lesson['duration']) ? $lesson['duration'] : '');
    }

    /** هل يقاس تشغيل هذا الدرس أصلا؟ */
    public function trackable($lesson)
    {
        $t = strtolower((string) (isset($lesson['video_type']) ? $lesson['video_type'] : ''));
        if (in_array($t, array('youtube', 'vimeo', 'html5', 'system'), true)) return true;
        return strtolower((string) $lesson['lesson_type']) === 'audio';
    }

    /** يعلم دلاء في الخريطة، ويرد الخريطة الجديدة ست عشرية. */
    private function seg_add($hex, $buckets, $max)
    {
        $bytes = (int) ceil($max / 8);
        $raw   = $hex !== '' ? @hex2bin(str_pad((string) $hex, $bytes * 2, '0')) : false;
        if ($raw === false || strlen($raw) < $bytes) {
            $raw = str_pad((string) $raw, $bytes, "\0");
        }

        foreach ((array) $buckets as $b) {
            $b = (int) $b;
            if ($b < 0 || $b >= $max) continue;
            $i = intdiv($b, 8);
            $raw[$i] = chr(ord($raw[$i]) | (1 << ($b % 8)));
        }
        return bin2hex(substr($raw, 0, $bytes));
    }

    /** كم دلوا معلما. */
    private function seg_count($hex, $max)
    {
        if ($hex === '' || $hex === null) return 0;
        $raw = @hex2bin(strlen($hex) % 2 ? '0' . $hex : $hex);
        if ($raw === false) return 0;

        $n = 0;
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $v = ord($raw[$i]);
            /* عد البتات — `substr_count(decbin())` أبطأ وأوضح، وهذه تنفذ
               على كل نبضة تقدم من كل طالب. */
            $v = $v - (($v >> 1) & 0x55);
            $v = ($v & 0x33) + (($v >> 2) & 0x33);
            $n += ($v + ($v >> 4)) & 0x0F;
        }
        return min($n, $max);
    }

    /**
     * أعمدة التقدم التي تحتاجها التغطية.
     *
     * تنشأ وقت التشغيل كما ينشئ `Taqdar_content_model` جدوله: المستودع
     * بلا هجرات، ومن نصب المنصة قبل هذا العمل يبقى جدوله بلا الأعمدة —
     * وأول كتابة عليها ترمي، فيسقط تسجيل التقدم كله.
     */
    private $_prog_schema = false;
    private function ensure_progress_schema()
    {
        if ($this->_prog_schema) return;
        $this->_prog_schema = true;

        try {
            $this->db->data_cache = array();
            $cols = array(
                'segments'     => 'text DEFAULT NULL COMMENT "خريطة دلاء عشر ثوان، ست عشرية"',
                'last_ping_at' => 'datetime DEFAULT NULL',
                'declared_at'  => 'datetime DEFAULT NULL COMMENT "إتمام أقره الطالب لا قيس"',
                /* TQ-DURATION — ما قاسه مشغل **هذا الطالب** هو.
                   عمود واحد لا جدول: `lesson_progress` صف لكل (طالب،
                   درس) بحكم تعريفه، فالقياسات فيه متمايزة بأصحابها
                   مجانا — ومن أراد التصديق عد الصفوف. */
                'media_sec'    => 'int(11) NOT NULL DEFAULT 0 COMMENT "طول المقطع كما أعلنه مشغل هذا الطالب"',
                /* TQ-BLIND — متى علمنا أننا لا نعلم.
                   يختم عند أول نبضة لا يصحبها قياس، ويمحى عند أول
                   قياس يصل. فهو «الوقت الذي مضى ونحن عاجزون»، وبه
                   وحده يقبل إقرار الإتمام على مصدر يفترض أنه يقاس. */
                'blind_at'     => 'datetime DEFAULT NULL COMMENT "أول لحظة عجز عن القياس"',
            );
            foreach ($cols as $c => $ddl) {
                if (!$this->db->field_exists($c, 'lesson_progress')) {
                    $this->db->query('ALTER TABLE `lesson_progress` ADD COLUMN `' . $c . '` ' . $ddl);
                }
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-COVERAGE: تعذر تركيب أعمدة التقدم — ' . $e->getMessage());
        }
    }

    /* ================================================================
     *  بوابة الإتقان
     * ================================================================ */

    /** يبدأ محاولة مراجعة ويعيد أسئلتها بلا مفاتيح حل. */
    public function start_attempt($student_id, $lesson_id)
    {
        $student_id = (int) $student_id;
        $lesson_id  = (int) $lesson_id;

        $state = $this->lesson_lock_state($student_id, $lesson_id);
        if (empty($state['found'])) {
            return $this->error('NOT_FOUND', array('entity' => 'lesson:' . $lesson_id));
        }
        $lesson = $state['lesson'];

        if ((int) $lesson['is_free'] !== 1 && !$this->is_entitled($student_id, $lesson['course_id'])) {
            return $this->error('NOT_ENTITLED', array('lesson_id' => $lesson_id));
        }
        if (empty($state['unlocked'])) {
            return $this->error('MASTERY_LOCKED', array(
                'lesson_id'          => $lesson_id,
                'blocking_lesson_id' => isset($state['blocking_lesson_id']) ? $state['blocking_lesson_id'] : null,
            ));
        }

        $assessment = $this->review_assessment($lesson_id, true);
        if (!$assessment) {
            return $this->error('NO_REVIEW', array('lesson_id' => $lesson_id));
        }

        // محاولة مفتوحة غير مسلمة؟ تستأنف بدل فتح واحدة جديدة.
        $open = $this->db->where('assessment_id', (int) $assessment['id'])
                         ->where('student_id', $student_id)
                         ->where('submitted_at', null)
                         ->order_by('attempt_no', 'DESC')
                         ->get('attempts')->row_array();

        if ($open) {
            $attempt = $open;
        } else {
            $last = $this->db->select_max('attempt_no', 'n')
                             ->where('assessment_id', (int) $assessment['id'])
                             ->where('student_id', $student_id)
                             ->get('attempts')->row_array();
            $no = ((int) $last['n']) + 1; // لا حد أقصى للمحاولات — العقاب بقاء القفل
            $this->db->insert('attempts', array(
                'assessment_id' => (int) $assessment['id'],
                'student_id'    => $student_id,
                'attempt_no'    => $no,
                'started_at'    => $this->now(),
            ));
            $attempt = $this->db->where('id', $this->db->insert_id())->get('attempts')->row_array();
            $this->audit($student_id, 'attempt.start', 'attempts:' . $attempt['id'], null,
                         array('assessment_id' => (int) $assessment['id'], 'attempt_no' => $no));
        }

        return array(
            'attempt_id'     => (int) $attempt['id'],
            'attempt_no'     => (int) $attempt['attempt_no'],
            'assessment_id'  => (int) $assessment['id'],
            'lesson_id'      => $lesson_id,
            'pass_mark'      => $this->pass_mark($assessment),
            'time_limit_sec' => $assessment['time_limit_sec'] !== null ? (int) $assessment['time_limit_sec'] : null,
            'questions'      => $this->review_questions($lesson_id),
        );
    }

    /**
     * تسليم محاولة مراجعة — الخوارزمية حرفيا:
     *
     *   score >= pass_mark          → إتقان: mastered_at، وفتح التالي،
     *                                 وجدولة الأهداف في review_queue بفاصل يوم
     *   score <  pass_mark & no = 1 → { retry, seek_to: أضعف هدف } بلا إعطاء الإجابة
     *   score <  pass_mark & no = 2 → { retry, alternate_explanation_id }
     *   score <  pass_mark & no >=3 → { suggest_session, context_objective_id } والقفل باق
     */
    public function submit_attempt($student_id, $attempt_id, $given_answers)
    {
        $student_id = (int) $student_id;
        $attempt_id = (int) $attempt_id;

        $attempt = $this->db->where('id', $attempt_id)->get('attempts')->row_array();
        if (!$attempt) return $this->error('NOT_FOUND', array('entity' => 'attempts:' . $attempt_id));
        if ((int) $attempt['student_id'] !== $student_id) {
            return $this->error('NOT_ENTITLED', array('entity' => 'attempts:' . $attempt_id));
        }
        if (!empty($attempt['submitted_at'])) {
            return $this->error('DUPLICATE_ATTEMPT', array(
                'attempt_id'   => $attempt_id,
                'submitted_at' => $attempt['submitted_at'],
            ));
        }

        $assessment = $this->db->where('id', (int) $attempt['assessment_id'])->get('assessments')->row_array();
        if (!$assessment) return $this->error('NOT_FOUND', array('entity' => 'assessments'));

        $lesson_id = (int) $assessment['lesson_id'];
        $pool      = $this->review_questions($lesson_id);
        $allowed   = array();
        foreach ($pool as $q) $allowed[(int) $q['id']] = true;

        if (!is_array($given_answers)) $given_answers = array();

        $score   = 0;
        $touched = array(); // objective_id => ['ok'=>n,'total'=>n,'ms'=>n]

        foreach ($given_answers as $item) {
            if (!is_array($item) || !isset($item['question_id'])) continue;
            $qid = (int) $item['question_id'];
            if (!isset($allowed[$qid])) continue; // سؤال خارج مجموعة المراجعة يهمل

            $q = $this->db->where('id', $qid)->get('question')->row_array();
            if (!$q) continue;

            $given = isset($item['given']) ? $item['given'] : null;
            if (is_string($given)) {
                $decoded = json_decode($given, true);
                $given   = is_array($decoded) ? $decoded : array($given);
            } elseif (!is_array($given)) {
                $given = $given === null ? array() : array($given);
            }

            $ok  = $this->is_answer_correct($q, $given);
            $ms  = isset($item['took_ms']) ? max(0, (int) $item['took_ms']) : null;
            $oid = $q['objective_id'] !== null ? (int) $q['objective_id'] : 0;

            $this->db->query(
                'INSERT INTO `answers` (`attempt_id`,`question_id`,`given`,`is_correct`,`took_ms`)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE `given`=VALUES(`given`), `is_correct`=VALUES(`is_correct`),
                                         `took_ms`=VALUES(`took_ms`)',
                array($attempt_id, $qid, json_encode($given, JSON_UNESCAPED_UNICODE), $ok, $ms));

            if ($ok) $score++;

            if (!isset($touched[$oid])) $touched[$oid] = array('ok' => 0, 'total' => 0, 'ms' => 0, 'n_ms' => 0);
            $touched[$oid]['total']++;
            if ($ok) $touched[$oid]['ok']++;
            if ($ms !== null) { $touched[$oid]['ms'] += $ms; $touched[$oid]['n_ms']++; }
        }

        $pass_mark = $this->pass_mark($assessment);
        $passed    = ($score >= $pass_mark) ? 1 : 0;
        $now       = $this->now();

        $this->db->where('id', $attempt_id)->update('attempts', array(
            'score'        => $score,
            'passed'       => $passed,
            'submitted_at' => $now,
        ));

        foreach ($touched as $oid => $t) {
            if (!$oid) continue;
            $avg = $t['n_ms'] ? (int) round($t['ms'] / $t['n_ms']) : null;
            $this->touch_skill_state($student_id, $oid, $t['ok'], $t['total'], $avg);
        }

        $this->audit($student_id, 'attempt.submit', 'attempts:' . $attempt_id, null, array(
            'assessment_id' => (int) $assessment['id'],
            'attempt_no'    => (int) $attempt['attempt_no'],
            'score'         => $score,
            'pass_mark'     => $pass_mark,
            'passed'        => $passed,
        ));

        /* إشعارات لحظة الحدث. تلف كلها لأن فشل الإشعار لا يبطل التسليم:
           من سلم امتحانه سلمه، وإسقاط العملية لأجل سطر إشعار عقاب بلا ذنب. */
        try {
            $this->load->model('taqdar_events_model', 'tq_events');

            $is_exam    = (isset($assessment['type']) && $assessment['type'] === 'exam');
            $is_station = !empty($assessment['milestone_id'])
                || $this->db->where('checkpoint_assessment_id', (int) $assessment['id'])
                            ->count_all_results('milestones') > 0;

            if ($is_exam) {
                $this->tq_events->notify_student_and_parents($student_id, 'exam_result', array(
                    'key'         => 'attempt:' . $attempt_id,
                    'window_days' => 14,
                    'text'        => 'نتيجة الامتحان: ' . $score . ' من ' . $pass_mark
                                   . ($passed ? ' — اجتياز.' : ' — دون حد النجاح.'),
                ));
            }
            if ($is_station && !$passed) {
                $this->tq_events->notify_student_and_parents($student_id, 'station_failed', array(
                    'key'         => 'attempt:' . $attempt_id,
                    'window_days' => 14,
                    'text'        => 'اختبار المحطة: المحاولة رقم ' . (int) $attempt['attempt_no']
                                   . ' لم تبلغ حد النجاح. والإعادة متاحة بلا حد.',
                ));
            }
            if ($is_exam && $passed) {
                $this->tq_events->notify_student_and_parents($student_id, 'certificate', array(
                    'key'         => 'attempt:' . $attempt_id,
                    'window_days' => 14,
                    'text'        => 'شهادة إتقان صارت متاحة.',
                ));
            }
        } catch (Throwable $e) {
            log_message('error', 'submit_attempt notify: ' . $e->getMessage());
        }

        $result = array(
            'attempt_id' => $attempt_id,
            'attempt_no' => (int) $attempt['attempt_no'],
            'score'      => $score,
            'out_of'     => count($pool),
            'pass_mark'  => $pass_mark,
            'passed'     => (bool) $passed,
        );

        if ($assessment['type'] !== 'review') {
            return $result; // البوابة تحكم المراجعة وحدها
        }

        return array_merge($result, $this->gate_decision(
            $student_id, $lesson_id, $attempt_id, (int) $attempt['attempt_no'], (bool) $passed));
    }

    /** قرار البوابة — هنا وحده يفتح القفل أو يبقى. */
    private function gate_decision($student_id, $lesson_id, $attempt_id, $attempt_no, $passed)
    {
        $state = $this->lesson_lock_state($student_id, $lesson_id);
        $next  = isset($state['next_lesson_id']) ? $state['next_lesson_id'] : null;

        if ($passed) {
            $now = $this->now();
            $this->db->query(
                'INSERT INTO `lesson_progress`
                    (`student_id`,`lesson_id`,`position_sec`,`watch_seconds`,`completed_at`,`mastered_at`)
                 VALUES (?,?,0,0,?,?)
                 ON DUPLICATE KEY UPDATE
                    `mastered_at` = COALESCE(`mastered_at`, VALUES(`mastered_at`)),
                    `completed_at`= COALESCE(`completed_at`, VALUES(`completed_at`))',
                array($student_id, $lesson_id, $now, $now));

            /*
             * الإتقان طريق ثان إلى الاكتمال، فوجب أن يمر بالمرآة كما يمر
             * حفظ المشاهدة. ومن أتقن درسا دون بلوغ عتبة المشاهدة كان يبقى
             * «غير مكتمل» في كل شاشة يراها هو ووليه ومعلمه، لأن الشاشات
             * تقرأ `watch_histories` لا `lesson_progress`. الآن رقم واحد.
             *
             * والموضع يقرأ من الصف بعد الكتابة لا يفترض صفرا، حتى لا
             * يمحو الإتقان موضع استئناف سجله الطالب قبله.
             */
            $saved = $this->db->select('position_sec')
                              ->where('student_id', $student_id)
                              ->where('lesson_id', $lesson_id)
                              ->get('lesson_progress')->row_array();

            $course_id = isset($state['lesson']['course_id']) ? (int) $state['lesson']['course_id'] : 0;
            $this->sync_watch_history(
                $student_id, $course_id, $lesson_id,
                $saved ? (int) $saved['position_sec'] : 0, true);

            $scheduled = $this->schedule_objectives($student_id, $lesson_id, 1);

            // درس أتقن اليوم — يعد في هدف اليوم إن كانت وحدته الدروس.
            $this->touch_day($student_id, 'lessons', 1);

            $this->audit($student_id, 'mastery.unlock', 'lesson:' . $lesson_id, null,
                         array('next_lesson_id' => $next, 'scheduled_reviews' => $scheduled));

            return array(
                'mastered'            => true,
                'mastered_at'         => $now,
                'unlocked_lesson_id'  => $next,
                'next_lesson_id'      => $next,
                'next_lesson_locked'  => false,
                'scheduled_reviews'   => $scheduled,
            );
        }

        $weak = $this->weakest_objective($attempt_id);

        if ($attempt_no <= 1) {
            // إعادة المحاولة مع إرجاع الطالب إلى موضع الشرح — بلا إعطاء الإجابة
            return array(
                'mastered'           => false,
                'retry'              => true,
                'seek_to'            => $weak ? (int) $weak['at_second'] : 0,
                'weakest_objective'  => $weak ? array(
                    'id'   => (int) $weak['objective_id'],
                    'text' => $weak['text'],
                ) : null,
                'next_lesson_id'     => $next,
                'next_lesson_locked' => true,
            );
        }

        if ($attempt_no == 2) {
            $alt = $weak ? $this->alternate_explanation((int) $weak['objective_id']) : null;
            return array(
                'mastered'                 => false,
                'retry'                    => true,
                'alternate_explanation_id' => $alt ? (int) $alt['lesson_id'] : null,
                'alternate'                => $alt ? array(
                    'lesson_id'    => (int) $alt['lesson_id'],
                    'objective_id' => (int) $alt['objective_id'],
                    'at_second'    => (int) $alt['at_second'],
                    'lesson_title' => $alt['lesson_title'],
                ) : null,
                'seek_to'                  => $weak ? (int) $weak['at_second'] : 0,
                'next_lesson_id'           => $next,
                'next_lesson_locked'       => true,
                'details'                  => $alt ? null : 'no_alternate_available',
            );
        }

        // ٣ فأكثر: تقترح حصة بالطلب، والقفل باق — لا حد أقصى للمحاولات
        $this->audit($student_id, 'mastery.suggest_session', 'lesson:' . $lesson_id, null,
                     array('attempt_no' => $attempt_no,
                           'context_objective_id' => $weak ? (int) $weak['objective_id'] : null));

        return array(
            'mastered'             => false,
            'retry'                => true,
            'suggest_session'      => true,
            'context_objective_id' => $weak ? (int) $weak['objective_id'] : null,
            'seek_to'              => $weak ? (int) $weak['at_second'] : 0,
            'next_lesson_id'       => $next,
            'next_lesson_locked'   => true,
        );
    }

    /** أضعف هدف في المحاولة: أقل نسبة صواب، وعند التساوي الأسبق زمنا. */
    public function weakest_objective($attempt_id)
    {
        $sql = 'SELECT q.`objective_id`, o.`text`, o.`at_second`,
                       SUM(a.`is_correct`) AS ok, COUNT(*) AS total
                FROM `answers` a
                JOIN `question` q ON q.`id` = a.`question_id`
                JOIN `objectives` o ON o.`id` = q.`objective_id`
                WHERE a.`attempt_id` = ?
                GROUP BY q.`objective_id`, o.`text`, o.`at_second`
                ORDER BY (SUM(a.`is_correct`) / COUNT(*)) ASC, o.`at_second` ASC
                LIMIT 1';
        $row = $this->db->query($sql, array((int) $attempt_id))->row_array();
        return $row ? $row : null;
    }

    /** شرح بديل للهدف نفسه من درس آخر — إن وجد. */
    public function alternate_explanation($objective_id)
    {
        $sql = 'SELECT o2.`id` AS objective_id, o2.`lesson_id`, o2.`at_second`, l.`title` AS lesson_title
                FROM `objectives` o1
                JOIN `objectives` o2 ON o2.`text` = o1.`text` AND o2.`lesson_id` <> o1.`lesson_id`
                JOIN `lesson` l ON l.`id` = o2.`lesson_id`
                WHERE o1.`id` = ?
                ORDER BY o2.`id` ASC LIMIT 1';
        $row = $this->db->query($sql, array((int) $objective_id))->row_array();
        return $row ? $row : null;
    }

    /** جدولة أسئلة أهداف الدرس في طابور المراجعة بفاصل يوم. */
    public function schedule_objectives($student_id, $lesson_id, $interval_days = 1)
    {
        $ease = (float) $this->setting('review_initial_ease', 2.5);
        $due  = date('Y-m-d H:i:s', time() + ((int) $interval_days * 86400));

        $rows = $this->db->query(
            'SELECT q.`id` FROM `question` q
             JOIN `objectives` o ON o.`id` = q.`objective_id`
             WHERE o.`lesson_id` = ?', array((int) $lesson_id))->result_array();

        $n = 0;
        foreach ($rows as $r) {
            $this->db->query(
                'INSERT INTO `review_queue`
                    (`student_id`,`question_id`,`due_at`,`interval_days`,`ease`,`lapses`)
                 VALUES (?,?,?,?,?,0)
                 ON DUPLICATE KEY UPDATE `due_at` = LEAST(`due_at`, VALUES(`due_at`))',
                array((int) $student_id, (int) $r['id'], $due, (int) $interval_days, $ease));
            $n++;
        }
        return $n;
    }

    /* ================================================================
     *  المراجعة المتباعدة ودفتر الأخطاء
     * ================================================================ */

    /** دفعة اليوم: ١٠ أسئلة مستحقة، بالأسبق موعدا ثم بالأصعب. */
    public function get_due_reviews($student_id, $limit = 10)
    {
        $student_id = (int) $student_id;
        $limit      = (int) $limit;
        if ($limit <= 0) $limit = (int) $this->setting('review_daily_batch', 10);
        $limit = min(50, $limit);

        $sql = 'SELECT rq.`id` AS queue_id, rq.`question_id`, rq.`due_at`, rq.`interval_days`,
                       rq.`ease`, rq.`lapses`,
                       q.`title`, q.`type`, q.`number_of_options`, q.`options`, q.`objective_id`,
                       o.`text` AS objective_text, o.`at_second`, o.`lesson_id`,
                       l.`title` AS lesson_title, l.`course_id`, c.`title` AS course_title
                FROM `review_queue` rq
                JOIN `question` q ON q.`id` = rq.`question_id`
                LEFT JOIN `objectives` o ON o.`id` = q.`objective_id`
                LEFT JOIN `lesson` l ON l.`id` = o.`lesson_id`
                LEFT JOIN `course` c ON c.`id` = l.`course_id`
                WHERE rq.`student_id` = ? AND rq.`due_at` <= ?
                ORDER BY rq.`due_at` ASC, rq.`lapses` DESC, rq.`ease` ASC, rq.`id` ASC
                LIMIT ' . $limit;

        $rows = $this->db->query($sql, array($student_id, $this->now()))->result_array();
        foreach ($rows as &$r) {
            $r['options'] = $r['options'] ? json_decode($r['options'], true) : array();
            $r['ease']    = (float) $r['ease'];
            $this->cast_ints($r, array('queue_id', 'question_id', 'interval_days', 'lapses',
                                       'number_of_options', 'objective_id', 'at_second',
                                       'lesson_id', 'course_id'));
        }
        unset($r);
        return $rows; // بلا correct_answers — التصحيح في الخادم
    }

    /** عدد المستحق اليوم (للشارات في القائمة). */
    public function count_due_reviews($student_id)
    {
        $r = $this->db->query(
            'SELECT COUNT(*) AS c FROM `review_queue` WHERE `student_id` = ? AND `due_at` <= ?',
            array((int) $student_id, $this->now()))->row_array();
        return (int) $r['c'];
    }

    /**
     * تحديث الفاصل بعد إجابة مراجعة:
     *   صحيحة → ease += 0.1 ؛ interval = round(interval * ease)
     *   خاطئة  → ease = max(1.3, ease - 0.2) ؛ interval = 1 ؛ lapses += 1
     *   due_at = now + interval days ؛ والسقف ٦٠ يوما
     */
    public function answer_review($student_id, $question_id, $correct)
    {
        $student_id = (int) $student_id;
        $question_id = (int) $question_id;
        $correct     = (bool) $correct;

        $max  = (int) $this->setting('review_max_interval_days', 60);
        $init = (float) $this->setting('review_initial_ease', 2.5);

        $row = $this->db->where('student_id', $student_id)->where('question_id', $question_id)
                        ->get('review_queue')->row_array();

        $ease     = $row ? (float) $row['ease']          : $init;
        $interval = $row ? (int) $row['interval_days']   : 1;
        $lapses   = $row ? (int) $row['lapses']          : 0;

        if ($correct) {
            $ease     = round($ease + 0.1, 2);
            $interval = (int) round($interval * $ease);
        } else {
            $ease     = max(1.3, round($ease - 0.2, 2));
            $interval = 1;
            $lapses++;
        }

        if ($interval < 1)    $interval = 1;
        if ($interval > $max) $interval = $max;

        $due = date('Y-m-d H:i:s', time() + ($interval * 86400));

        $this->db->query(
            'INSERT INTO `review_queue`
                (`student_id`,`question_id`,`due_at`,`interval_days`,`ease`,`lapses`)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE `due_at`=VALUES(`due_at`), `interval_days`=VALUES(`interval_days`),
                                     `ease`=VALUES(`ease`), `lapses`=VALUES(`lapses`)',
            array($student_id, $question_id, $due, $interval, $ease, $lapses));

        // الهدف هو المفصل: كل إجابة تحرك حالة المهارة أيضا
        $q = $this->db->select('objective_id')->where('id', $question_id)->get('question')->row_array();
        if ($q && $q['objective_id']) {
            $this->touch_skill_state($student_id, (int) $q['objective_id'], $correct ? 1 : 0, 1, null);
        }

        // ومراجعة واحدة في دفتر اليوم — صوابا كانت أو خطأ، فكلاهما عمل.
        $this->touch_day($student_id, 'reviews', 1);

        return array(
            'question_id'   => $question_id,
            'correct'       => $correct,
            'ease'          => $ease,
            'interval_days' => $interval,
            'lapses'        => $lapses,
            'due_at'        => $due,
            'remaining_due' => $this->count_due_reviews($student_id),
        );
    }

    /**
     * دفتر الأخطاء — يشتق من answers حيث is_correct = 0، لا جدول مستقل
     * حتى لا يفترق الدفتر عن الحقيقة.
     */
    public function get_mistakes($student_id)
    {
        $sql = 'SELECT a.`question_id`, q.`title`, q.`type`, q.`objective_id`,
                       o.`text` AS objective_text, o.`at_second`, o.`lesson_id`,
                       l.`title` AS lesson_title, l.`course_id`, c.`title` AS course_title,
                       COUNT(*) AS wrong_count,
                       MAX(at.`submitted_at`) AS last_wrong_at,
                       rq.`due_at`, rq.`lapses`, rq.`interval_days`
                FROM `answers` a
                JOIN `attempts` at ON at.`id` = a.`attempt_id`
                JOIN `question` q ON q.`id` = a.`question_id`
                LEFT JOIN `objectives` o ON o.`id` = q.`objective_id`
                LEFT JOIN `lesson` l ON l.`id` = o.`lesson_id`
                LEFT JOIN `course` c ON c.`id` = l.`course_id`
                LEFT JOIN `review_queue` rq ON rq.`question_id` = a.`question_id`
                                           AND rq.`student_id` = at.`student_id`
                WHERE at.`student_id` = ? AND a.`is_correct` = 0
                GROUP BY a.`question_id`, q.`title`, q.`type`, q.`objective_id`,
                         o.`text`, o.`at_second`, o.`lesson_id`, l.`title`, l.`course_id`,
                         c.`title`, rq.`due_at`, rq.`lapses`, rq.`interval_days`
                ORDER BY wrong_count DESC, last_wrong_at DESC';
        $rows = $this->db->query($sql, array((int) $student_id))->result_array();
        foreach ($rows as &$r) {
            $this->cast_ints($r, array('question_id', 'objective_id', 'at_second', 'lesson_id',
                                       'course_id', 'wrong_count', 'lapses', 'interval_days'));
        }
        unset($r);
        return $rows;
    }

    /* ================================================================
     *  التوأم الرقمي
     * ================================================================ */

    /** يحدث مستوى الهدف بعد إجابات — عشري ثابت لا عائم في التخزين. */
    public function touch_skill_state($student_id, $objective_id, $ok, $total, $avg_ms = null)
    {
        $student_id   = (int) $student_id;
        $objective_id = (int) $objective_id;
        if (!$student_id || !$objective_id || $total <= 0) return;

        $row = $this->db->where('student_id', $student_id)->where('objective_id', $objective_id)
                        ->get('skill_state')->row_array();

        $observed = ($ok / $total) * 100.0;
        $level    = $row ? (float) $row['level'] : 0.0;
        // متوسط مرجح: الملاحظة الجديدة تزن ٤٠٪ فلا يقفز المستوى بضربة واحدة
        $level    = $row ? ($level * 0.6 + $observed * 0.4) : $observed;
        $level    = max(0, min(100, round($level, 2)));

        $forget = $row ? (float) $row['forget_rate'] : 0.1;
        $forget = ($ok === $total) ? max(0.02, round($forget - 0.01, 4))
                                   : min(0.9,  round($forget + 0.02, 4));

        $avg = $row ? (int) $row['avg_response_ms'] : 0;
        if ($avg_ms !== null) {
            $avg = $avg > 0 ? (int) round(($avg * 0.7) + ($avg_ms * 0.3)) : (int) $avg_ms;
        }

        $data = array(
            'level'           => $level,
            'forget_rate'     => $forget,
            'last_seen_at'    => $this->now(),
            'avg_response_ms' => $avg,
        );

        if ($row) {
            $this->db->where('id', (int) $row['id'])->update('skill_state', $data);
        } else {
            $data['student_id']   = $student_id;
            $data['objective_id'] = $objective_id;
            $this->db->insert('skill_state', $data);
        }
    }

    /** خريطة المهارات: هدف هدف، ومعها أضعف خمسة للعرض السريع. */
    public function get_skill_map($student_id)
    {
        $sql = 'SELECT ss.`objective_id`, ss.`level`, ss.`forget_rate`, ss.`last_seen_at`,
                       ss.`avg_response_ms`, o.`text` AS objective_text, o.`at_second`,
                       o.`lesson_id`, l.`title` AS lesson_title, l.`course_id`,
                       c.`title` AS course_title
                FROM `skill_state` ss
                JOIN `objectives` o ON o.`id` = ss.`objective_id`
                LEFT JOIN `lesson` l ON l.`id` = o.`lesson_id`
                LEFT JOIN `course` c ON c.`id` = l.`course_id`
                WHERE ss.`student_id` = ?
                ORDER BY ss.`level` ASC, ss.`last_seen_at` ASC';
        $rows = $this->db->query($sql, array((int) $student_id))->result_array();

        $sum = 0;
        foreach ($rows as &$r) {
            $r['level']       = (float) $r['level'];
            $r['forget_rate'] = (float) $r['forget_rate'];
            $this->cast_ints($r, array('objective_id', 'at_second', 'avg_response_ms',
                                       'lesson_id', 'course_id'));
            $sum += $r['level'];
        }
        unset($r);

        return array(
            'objectives'    => $rows,
            'count'         => count($rows),
            'average_level' => count($rows) ? round($sum / count($rows), 2) : 0,
            'weakest'       => array_slice($rows, 0, 5),
        );
    }

    /* ================================================================
     *  المحفظة وأولياء الأمور والمعلمون
     * ================================================================ */

    /** محفظة المستخدم — كل المبالغ بالهللات كأعداد صحيحة. */
    public function get_wallet($user_id)
    {
        $user_id = (int) $user_id;
        $wallet  = $this->db->where('owner_user_id', $user_id)->get('wallets')->row_array();

        if (!$wallet) {
            $this->db->insert('wallets', array(
                'owner_user_id'     => $user_id,
                'balance_available' => 0,
                'balance_pending'   => 0,
                'balance_locked'    => 0,
            ));
            $wallet = $this->db->where('owner_user_id', $user_id)->get('wallets')->row_array();
        }

        $entries = $this->db->where('wallet_id', (int) $wallet['id'])
                            ->order_by('id', 'DESC')->limit(50)
                            ->get('wallet_entries')->result_array();
        foreach ($entries as &$e) $this->cast_ints($e, array('id', 'wallet_id', 'amount'));
        unset($e);

        return array(
            'wallet_id'         => (int) $wallet['id'],
            'owner_user_id'     => $user_id,
            'balance_available' => (int) $wallet['balance_available'],
            'balance_pending'   => (int) $wallet['balance_pending'],
            'balance_locked'    => (int) $wallet['balance_locked'],
            'currency'          => 'SAR',
            'unit'              => 'halala',
            'entries'           => $entries,
        );
    }

    /** أبناء ولي الأمر المرتبطون بموافقة نشطة فقط. */
    public function get_children($parent_user_id)
    {
        $sql = 'SELECT pl.`id` AS link_id, pl.`status`, pl.`consent_at`, pl.`scope`,
                       u.`id` AS student_id, u.`first_name`, u.`last_name`, u.`email`, u.`image`
                FROM `parent_links` pl
                JOIN `users` u ON u.`id` = pl.`student_id`
                WHERE pl.`parent_user_id` = ? AND pl.`status` = "active"
                ORDER BY u.`first_name` ASC';
        $rows = $this->db->query($sql, array((int) $parent_user_id))->result_array();

        foreach ($rows as &$r) {
            $this->cast_ints($r, array('link_id', 'student_id'));
            $r['scope'] = $r['scope'] ? json_decode($r['scope'], true) : null;
            $r['name']  = trim($r['first_name'] . ' ' . $r['last_name']);

            $m = $this->db->query(
                'SELECT COUNT(*) AS c FROM `lesson_progress`
                 WHERE `student_id` = ? AND `mastered_at` IS NOT NULL',
                array($r['student_id']))->row_array();

            $r['mastered_lessons'] = (int) $m['c'];
            $r['due_reviews']      = $this->count_due_reviews($r['student_id']);
        }
        return $rows;
    }

    /** نطاق إسناد المعلم: مادة × صف. خارجه OUT_OF_ASSIGNMENT. */
    public function get_teacher_scope($teacher_id)
    {
        $sql = 'SELECT ta.`id`, ta.`subject_id`, ta.`grade_id`, ta.`can_publish`, ta.`can_take_sessions`,
                       s.`name_ar` AS subject_ar, s.`name_en` AS subject_en,
                       g.`name_ar` AS grade_ar,   g.`name_en` AS grade_en
                FROM `teacher_assignments` ta
                LEFT JOIN `subjects` s ON s.`id` = ta.`subject_id`
                LEFT JOIN `grades`   g ON g.`id` = ta.`grade_id`
                WHERE ta.`teacher_id` = ?
                ORDER BY COALESCE(s.`order`,0) ASC, COALESCE(g.`order`,0) ASC';
        $rows = $this->db->query($sql, array((int) $teacher_id))->result_array();

        $subjects = array(); $grades = array();
        foreach ($rows as &$r) {
            $this->cast_ints($r, array('id', 'subject_id', 'grade_id',
                                       'can_publish', 'can_take_sessions'));
            $subjects[$r['subject_id']] = true;
            $grades[$r['grade_id']]     = true;
        }
        unset($r);

        return array(
            'teacher_id'  => (int) $teacher_id,
            'assignments' => $rows,
            'subject_ids' => array_keys($subjects),
            'grade_ids'   => array_keys($grades),
        );
    }

    /** هل هذا الإسناد داخل نطاق المعلم؟ */
    public function teacher_can($teacher_id, $subject_id, $grade_id, $capability = null)
    {
        $this->db->where('teacher_id', (int) $teacher_id)
                 ->where('subject_id', (int) $subject_id)
                 ->where('grade_id', (int) $grade_id);
        $row = $this->db->get('teacher_assignments')->row_array();
        if (!$row) return false;
        if ($capability && empty($row[$capability])) return false;
        return true;
    }

    /**
     * يعكس تقدم المحرك على جدول Academy القديم.
     *
     * `watch_histories.completed_lesson` مصفوفة JSON من معرفات الدروس،
     * و`course_progress` نسبة مئوية — هكذا يقرأها المشغل القديم وتقارير
     * الأدمن وشهادات Academy. لا نغير شكلها، بل نكتبه كما يتوقعه القديم.
     *
     * أخطاؤها لا توقف حفظ التقدم: المصدر الحقيقي كتب بالفعل، وهذه مرآة.
     */
    private function sync_watch_history($student_id, $course_id, $lesson_id, $position_sec, $completed)
    {
        $student_id   = (int) $student_id;
        $course_id    = (int) $course_id;
        $lesson_id    = (int) $lesson_id;
        $position_sec = max(0, (int) $position_sec);

        if ($course_id <= 0 || $lesson_id <= 0) return;

        $row = $this->db->where('student_id', $student_id)
                        ->where('course_id', $course_id)
                        ->get('watch_histories')->row_array();

        $done = array();
        if ($row && !empty($row['completed_lesson'])) {
            $decoded = json_decode($row['completed_lesson'], true);
            if (is_array($decoded)) $done = $decoded;
        }

        // للمرآة الآن مدخلان — حفظ المشاهدة والإتقان — فالتوحيد شرط:
        // أعداد صحيحة بلا تكرار، وإلا عد الدرس الواحد مرتين وتضخمت النسبة.
        $done = array_values(array_unique(array_map('intval', $done)));

        if ($completed && !in_array($lesson_id, $done, true)) {
            $done[] = $lesson_id;
        }

        $total = (int) $this->db->where('course_id', $course_id)->count_all_results('lesson');
        $pct   = $total > 0 ? min(100, (int) floor((count($done) * 100) / $total)) : 0;

        $data = array(
            'completed_lesson'   => json_encode(array_values($done)),
            'course_progress'    => $pct,
            'watching_lesson_id' => $lesson_id,
            'date_updated'       => time(),
        );

        if ($row) {
            $this->db->where('watch_history_id', (int) $row['watch_history_id'])
                     ->update('watch_histories', $data);
        } else {
            $data['student_id'] = $student_id;
            $data['course_id']  = $course_id;
            $data['date_added'] = time();
            $this->db->insert('watch_histories', $data);
        }

        // الموضع داخل الدرس يعيش في جدول منفصل عند Academy
        $w = $this->db->where('watched_student_id', $student_id)
                      ->where('watched_lesson_id', $lesson_id)
                      ->get('watched_duration')->row_array();
        if ($w) {
            $this->db->where('watched_id', (int) $w['watched_id'])
                     ->update('watched_duration', array('current_duration' => $position_sec));
        } else {
            $this->db->insert('watched_duration', array(
                'watched_student_id' => $student_id,
                'watched_course_id'  => $course_id,
                'watched_lesson_id'  => $lesson_id,
                'current_duration'   => $position_sec,
            ));
        }
    }


    /**
     * مراجعة محاولة بتفاصيل كل سؤال.
     *
     * الإجابات محفوظة في `answers` منذ التسليم، فهذه قراءة لا حساب —
     * ولا تعيد التصحيح: إعادة حساب الصواب هنا تنتج مصدر حقيقة
     * ثانيا يفترق عن الأول عند أول تعديل في قاعدة التصحيح.
     *
     * والحارس بالطالب لا بالمحاولة: رقم محاولة في الرابط لا يكفي،
     * وإلا قرأ كل طالب إجابات غيره بتغيير رقم.
     */
    public function attempt_review($student_id, $attempt_id)
    {
        $student_id = (int) $student_id;
        $attempt_id = (int) $attempt_id;

        $attempt = $this->db->where('id', $attempt_id)->get('attempts')->row_array();
        if (!$attempt) return $this->error('NOT_FOUND', array('entity' => 'attempts:' . $attempt_id));
        if ((int) $attempt['student_id'] !== $student_id) {
            return $this->error('NOT_ENTITLED', array('entity' => 'attempts:' . $attempt_id));
        }
        if (empty($attempt['submitted_at'])) {
            return $this->error('NOT_FOUND', array('entity' => 'attempt-not-submitted'));
        }

        $assessment = $this->db->where('id', (int) $attempt['assessment_id'])
                               ->get('assessments')->row_array();
        $lesson_id  = $assessment ? (int) $assessment['lesson_id'] : 0;

        $rows = $this->db->select('a.question_id, a.given, a.is_correct,
                                   q.title, q.type, q.options, q.correct_answers', false)
                         ->from('answers a')
                         ->join('question q', 'q.id = a.question_id', 'inner')
                         ->where('a.attempt_id', $attempt_id)
                         ->order_by('a.id', 'ASC')
                         ->get()->result_array();

        $items = array();
        foreach ($rows as $r) {
            $given   = json_decode((string) $r['given'], true);
            $correct = json_decode((string) $r['correct_answers'], true);
            $options = json_decode((string) $r['options'], true);
            $items[] = array(
                'question' => (string) $r['title'],
                'type'     => (string) $r['type'],
                'options'  => is_array($options) ? $options : array(),
                'given'    => is_array($given) ? $given : array(),
                'correct'  => is_array($correct) ? $correct : array(),
                'is_right' => ((int) $r['is_correct'] === 1),
            );
        }

        /* أعلى درجة لا آخرها: من أعاد فأتقن يستحق ما أتقنه. */
        $best = (int) $this->db->select_max('score', 'best')
                               ->where('student_id', $student_id)
                               ->where('assessment_id', (int) $attempt['assessment_id'])
                               ->get('attempts')->row('best');
        $tries = (int) $this->db->where('student_id', $student_id)
                                ->where('assessment_id', (int) $attempt['assessment_id'])
                                ->where('submitted_at IS NOT NULL', null, false)
                                ->count_all_results('attempts');

        return array(
            'ok'         => true,
            'attempt_id' => $attempt_id,
            'lesson_id'  => $lesson_id,
            'score'      => (int) $attempt['score'],
            'best'       => $best,
            'tries'      => $tries,
            'pass_mark'  => $assessment ? $this->pass_mark($assessment) : 0,
            'passed'     => ((int) $attempt['passed'] === 1),
            'total'      => count($items),
            'items'      => $items,
        );
    }

}
