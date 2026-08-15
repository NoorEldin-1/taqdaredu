<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الاختبارات التشخيصية — ما يحدد موضع الطالب قبل أن يدفع.
 *
 * ═══ لماذا وجد ═══
 *
 * الطالب ينشئ حسابه ثم يقف أمام صفحة باقات: ثلاثة أسماء وثلاثة أسعار،
 * ولا شيء يقول له أيها يناسبه. فيختار بالسعر — وهو أسوأ مرشد، لان الاقل
 * ثمنا قد يكون فوق مستواه بسنة والاعلى دونه بسنة. والنتيجة تظهر بعد
 * الدفع لا قبله.
 *
 * فالاختبار التشخيصي يقلب الترتيب: يقاس أولا، ثم يعرض عليه ما يقابل
 * قياسه. والباقة تصير جوابا عن سؤال لا عرضا يختار منه.
 *
 * ═══ لماذا جداول مستقلة لا توسيع لـ`assessments` ═══
 *
 * ثلاثة اسباب، وكلها عن عطل يقع لا عن ذوق:
 *
 * ١ — `assessments.type` قائمة مغلقة ومفتاحها الفريد `(type, lesson_id)`،
 *     والتشخيصي بلا درس. فتوسيعها يعني `ALTER` على جدول مشترك تقرؤه
 *     مسارات LMS الاصلية.
 * ٢ — `attempts` تقرؤها شاشة «اختباراتي» وقائمة التصحيح وعداد «مهامي».
 *     فمحاولة تشخيصية فيها تظهر للطالب اختبارا لم يسنده معلم، وللمعلم
 *     صفا في طابور تصحيح لا يخصه.
 * ٣ — مستوى السؤال (مبتدئ · متوسط · متقدم) وربط كل نتيجة بباقة لا عمود
 *     لهما في المخطط الحالي اصلا.
 *
 * والاعادة تبقى حيث تنفع: شكل السؤال هنا مطابق لـ`question` حرفا
 * (`type` · `options` · `correct_answers` بـJSON)، فـ
 * `Taqdar_repo_model::is_answer_correct()` تحكم عليه بلا سطر ثان —
 * ومنطق تصحيح واحد في المنصة كلها لا اثنان يفترقان.
 *
 * ═══ كيف تحسب النتيجة ═══
 *
 * تدرجا بالمستويات لا بنسبة اجمالية: يبلغ الطالب «متقدم» ان اتقن اسئلة
 * المتقدم، والا «متوسط» ان اتقن اسئلة المتوسط، والا «مبتدئ». وهذا هو ما
 * يجعل وسم كل سؤال بمستواه ذا معنى — والنسبة الاجمالية تسوي بين من اجاب
 * ثماني اسئلة سهلة ومن اجاب ثماني اسئلة صعبة، وهما ليسا في موضع واحد.
 *
 * ومستوى بلا سؤال واحد يتخطى في التدرج: اختبار كتبت له اسئلة مبتدئ
 * ومتقدم وحدها لا يحبس كل طالب في «مبتدئ» لان المتوسط خال.
 *
 * ═══ ما يمنعه هذا الاختبار وما لا يمنعه ═══
 *
 * يمنع الشراء قبل القياس — ولا يمنع شيئا بعده. فمن اداه صار حرا في اي
 * باقة شاء: التوصية ترشد ولا تلزم، ولو الزمت لصارت الاداة التي تقيس هي
 * الاداة التي تبيع.
 *
 * ولا يمنع شيئا حيث لا اختبار: صف بلا اختبار منشور — او طالب بلا صف —
 * يمر كما كان يمر قبل هذا الملف حرفا بحرف.
 */
class Taqdar_diag_model extends CI_Model
{
    /** يفحص مرة واحدة لكل طلب — لا في كل قراءة. */
    private $schema_checked = false;

    /* =====================================================================
       المستويات
       ===================================================================== */

    /**
     * المستويات الثلاثة بترتيبها الصاعد.
     *
     * الترتيب هنا **هو** ترتيب التدرج في `grade_attempt()`: يقرأ من اعلى
     * الى ادنى فياخذ اول ما اتقن. فتغيير الترتيب هنا يغير الحساب، ولذلك
     * لا يكتب في موضع ثان.
     */
    public static function levels()
    {
        return array(
            'beginner'     => array('label' => 'مبتدئ',  'order' => 1, 'tone' => 'warn',
                                    'lead'  => 'الاساسيات تحتاج بناء من جديد — وهذا موضع البداية الصحيح.'),
            'intermediate' => array('label' => 'متوسط',  'order' => 2, 'tone' => 'info',
                                    'lead'  => 'الاساس قائم، والبناء عليه هو الخطوة التالية.'),
            'advanced'     => array('label' => 'متقدم',  'order' => 3, 'tone' => 'ok',
                                    'lead'  => 'اتقان واضح — والمناسب توسع وتحد لا اعادة شرح.'),
        );
    }

    /** تسمية مستوى، وما لا يعرف يرد كما جاء لا فارغا. */
    public static function level_label($key)
    {
        $l = self::levels();
        return isset($l[$key]) ? $l[$key]['label'] : (string) $key;
    }

    /** المفاتيح بترتيبها الصاعد — للحلقات ولقوائم النماذج. */
    public static function level_keys()
    {
        return array_keys(self::levels());
    }

    /* =====================================================================
       المخطط
       ===================================================================== */

    /**
     * ينشئ الجداول عند اول استعمال.
     *
     * لا هجرات في هذا المستودع، والبديل ملف SQL يستورد بيد على الخادم —
     * وينسى، فتسقط الشاشة عند من نشر ولم يستورد. والانشاء متكرر الامان
     * (`IF NOT EXISTS`) ويفحص مرة واحدة لكل طلب.
     */
    public function ensure_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        try {
            /* الفرادة على `grade_id` قيد عمل لا تحسين اداء: صفان لصف
               واحد يعنيان اختبارين يتنازعان طالبا، ولا شيء يقرر ايهما
               يعرض. والقيد هنا يمنع ذلك في القاعدة نفسها — لا في شاشة
               قد تلتف عليها كتابة اخرى. */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_diag_exams` (
                    `id`                int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `grade_id`          int(10) unsigned NOT NULL,
                    `title`             varchar(190) NOT NULL,
                    `intro`             text         DEFAULT NULL,
                    `status`            enum("draft","published") NOT NULL DEFAULT "draft",
                    `time_limit_sec`    int(11)      NOT NULL DEFAULT 0,
                    `level_threshold`   int(11)      NOT NULL DEFAULT 60,
                    `allow_retake`      tinyint(1)   NOT NULL DEFAULT 0,
                    `plan_beginner`     int(10) unsigned NOT NULL DEFAULT 0,
                    `plan_intermediate` int(10) unsigned NOT NULL DEFAULT 0,
                    `plan_advanced`     int(10) unsigned NOT NULL DEFAULT 0,
                    `created_at`        datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_diag_grade` (`grade_id`),
                    KEY `ix_diag_status` (`status`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            /* اعمدة السؤال بأسماء `question` نفسها — وهو شرط اعادة
               استعمال `is_answer_correct()` بلا ترجمة بينهما. */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_diag_questions` (
                    `id`              int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `exam_id`         int(10) unsigned NOT NULL,
                    `level`           enum("beginner","intermediate","advanced") NOT NULL DEFAULT "beginner",
                    `title`           text         NOT NULL,
                    `type`            varchar(32)  NOT NULL DEFAULT "radio",
                    `options`         longtext     DEFAULT NULL,
                    `correct_answers` longtext     DEFAULT NULL,
                    `order`           int(11)      NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `ix_diag_q_exam` (`exam_id`, `level`, `order`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            /* `grade_id` ينسخ على المحاولة ولا يقرأ من `users` وقت العرض:
               الطالب قد يبدل صفه بعد ادائه، والنتيجة سجل لما جرى فعلا لا
               لما يجري الان. */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_diag_attempts` (
                    `id`           int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `exam_id`      int(10) unsigned NOT NULL,
                    `student_id`   int(10) unsigned NOT NULL,
                    `grade_id`     int(10) unsigned NOT NULL DEFAULT 0,
                    `score`        int(11)      NOT NULL DEFAULT 0,
                    `total`        int(11)      NOT NULL DEFAULT 0,
                    `breakdown`    varchar(255) DEFAULT NULL,
                    `result_level` enum("beginner","intermediate","advanced") DEFAULT NULL,
                    `plan_id`      int(10) unsigned NOT NULL DEFAULT 0,
                    `started_at`   datetime     DEFAULT NULL,
                    `submitted_at` datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `ix_diag_at_student` (`student_id`, `exam_id`),
                    KEY `ix_diag_at_exam` (`exam_id`, `submitted_at`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_diag_answers` (
                    `id`          int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `attempt_id`  int(10) unsigned NOT NULL,
                    `question_id` int(10) unsigned NOT NULL,
                    `given`       longtext     DEFAULT NULL,
                    `is_correct`  tinyint(1)   NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_diag_answer` (`attempt_id`, `question_id`),
                    KEY `ix_diag_an_q` (`question_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            /* بلا جداول لا يوجد اختبار منشور، و`gate()` ترد «لا مانع» —
               اي ان المنصة تعمل كما كانت تعمل قبل هذه الميزة. الفشل هنا
               يعطل الاختبار ولا يعطل الشراء. */
            log_message('error', 'TQ-DIAG: تعذر انشاء جداول الاختبار التشخيصي — ' . $e->getMessage());
        }
    }

    /* =====================================================================
       القراءة
       ===================================================================== */

    /** اختبار بمعرفه، بلا شرط حالة — للوحة. */
    public function exam($id)
    {
        $this->ensure_schema();
        $id = (int) $id;
        if ($id <= 0) return null;
        return $this->db->where('id', $id)->get('tq_diag_exams')->row_array();
    }

    /**
     * الاختبار المنشور لصف بعينه — وهو مصدر القرار كله.
     *
     * «المنشور» بالحرف: مسودة لا تحبس احدا ولا تعرض لاحد. فالمسؤول يبني
     * اختباره على مهل، ولا يقع على طالب نصف مكتوب.
     */
    public function exam_for_grade($grade_id)
    {
        $this->ensure_schema();
        $grade_id = (int) $grade_id;
        if ($grade_id <= 0) return null;

        try {
            return $this->db->where('grade_id', $grade_id)
                            ->where('status', 'published')
                            ->limit(1)->get('tq_diag_exams')->row_array();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * اسئلة اختبار.
     *
     * `$with_answers` تفتح `correct_answers` — وهي **لا تفتح للطالب ابدا**:
     * الصفحة التي تحمل الاجابات تحملها الى متصفحه، ومن فتح ادوات المطور
     * قرأها. فالتصحيح في الخادم وحده، والقراءة هنا بلا اجابات افتراضا.
     */
    public function questions($exam_id, $with_answers = false)
    {
        $this->ensure_schema();
        $exam_id = (int) $exam_id;
        if ($exam_id <= 0) return array();

        $cols = 'id, exam_id, level, title, type, options, `order`'
              . ($with_answers ? ', correct_answers' : '');

        try {
            $rows = $this->db->select($cols, false)
                             ->where('exam_id', $exam_id)
                             ->order_by('`order`', 'ASC', false)
                             ->order_by('id', 'ASC')
                             ->get('tq_diag_questions')->result_array();
        } catch (Throwable $e) {
            return array();
        }

        foreach ($rows as &$r) {
            $opts = json_decode((string) $r['options'], true);
            $r['options'] = is_array($opts) ? $opts : array();
            $r['id']      = (int) $r['id'];
            $r['order']   = (int) $r['order'];
        }
        unset($r);
        return $rows;
    }

    /**
     * الاسئلة مرتبة بالمستوى — للعرض على الطالب وللوحة.
     *
     * الترتيب بالمستوى لا بالادخال: من يبدأ باسئلة متقدمة ثم يهبط يقرأ
     * الاختبار على انه صعب فينصرف. والتدرج الصاعد هو ما يبني الثقة قبل
     * ان يقيس الحد.
     */
    public function questions_by_level($exam_id, $with_answers = false)
    {
        $out = array();
        foreach (self::level_keys() as $k) $out[$k] = array();

        foreach ($this->questions($exam_id, $with_answers) as $q) {
            $lv = isset($out[$q['level']]) ? $q['level'] : 'beginner';
            $out[$lv][] = $q;
        }
        return $out;
    }

    /** الاسئلة بترتيب العرض: مبتدئا فمتوسطا فمتقدما. */
    public function ordered_questions($exam_id, $with_answers = false)
    {
        $out = array();
        foreach ($this->questions_by_level($exam_id, $with_answers) as $rows) {
            foreach ($rows as $q) $out[] = $q;
        }
        return $out;
    }

    /** عدد الاسئلة في كل مستوى — لشاشة اللوحة ولفحص الجهوزية. */
    public function level_tally($exam_id)
    {
        $out = array();
        foreach ($this->questions_by_level($exam_id) as $k => $rows) $out[$k] = count($rows);
        return $out;
    }

    /**
     * هل هذا الاختبار صالح للنشر؟
     *
     * سؤال واحد على الاقل، وباقة لكل مستوى فيه سؤال. والعلة تقال صريحة:
     * «غير جاهز» بلا سبب يجعل المسؤول ينشر ثم يكتشف بالشكوى.
     */
    public function readiness($exam)
    {
        if (!$exam) return array('ok' => false, 'why' => array('لا اختبار.'));

        $why   = array();
        $tally = $this->level_tally((int) $exam['id']);
        $total = array_sum($tally);

        if ($total < 1) {
            $why[] = 'لا سؤال واحد في هذا الاختبار.';
        }

        foreach (self::levels() as $key => $meta) {
            /* الباقة تلزم للمستوى الذي فيه اسئلة وحده: اختبار بمستويين
               لا يطالب بباقة مستوى لا يبلغه احد. */
            if ($tally[$key] < 1) continue;
            if ((int) $exam['plan_' . $key] <= 0) {
                $why[] = 'مستوى «' . $meta['label'] . '» فيه اسئلة ولا باقة مربوطة به.';
            }
        }

        /* التدرج يبدأ من الاعلى، فمستوى بلا اسئلة يتخطى — ولكن اختبارا
           كله في مستوى واحد يعطي نتيجة واحدة لكل طالب، وهو قياس لا يقيس. */
        $filled = 0;
        foreach ($tally as $n) if ($n > 0) $filled++;
        if ($total > 0 && $filled < 2) {
            $why[] = 'اسئلة الاختبار كلها في مستوى واحد — فكل طالب يخرج بالنتيجة نفسها.';
        }

        return array('ok' => empty($why), 'why' => $why, 'tally' => $tally, 'total' => $total);
    }

    /* =====================================================================
       محاولات الطالب
       ===================================================================== */

    /** اخر محاولة مسلمة لهذا الطالب في هذا الاختبار — او لا شيء. */
    public function latest_attempt($student_id, $exam_id)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;
        $exam_id    = (int) $exam_id;
        if ($student_id <= 0 || $exam_id <= 0) return null;

        try {
            return $this->db->where('student_id', $student_id)
                            ->where('exam_id', $exam_id)
                            ->where('submitted_at IS NOT NULL', null, false)
                            ->order_by('id', 'DESC')->limit(1)
                            ->get('tq_diag_attempts')->row_array();
        } catch (Throwable $e) {
            return null;
        }
    }

    /** اخر نتيجة للطالب ايا كان اختبارها — لبطاقة اشتراكه ولصفحة الباقات. */
    public function latest_result($student_id)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;
        if ($student_id <= 0) return null;

        try {
            return $this->db->where('student_id', $student_id)
                            ->where('submitted_at IS NOT NULL', null, false)
                            ->order_by('id', 'DESC')->limit(1)
                            ->get('tq_diag_attempts')->row_array();
        } catch (Throwable $e) {
            return null;
        }
    }

    /* =====================================================================
       الحارس
       ===================================================================== */

    /**
     * هل يحبس هذا المستخدم عن الشراء حتى يؤدي اختباره؟
     *
     * **مصدر القرار الوحيد.** تناديه بوابة الطالب وشاشة التاكيد ونموذج
     * الفوترة جميعا — فلو كتب الشرط في كل موضع بيده لاختلفت المواضع عند
     * اول تعديل، وصار زر يخفى في شاشة ويعمل في اخرى.
     *
     * ويرد `null` — اي «لا مانع» — في كل الحالات التالية:
     *   · غير مسجل دخول، او ليس طالبا (المعلم وولي الامر لا يشتريان اصلا).
     *   · بلا صف دراسي على حسابه.
     *   · لا اختبار **منشور** لصفه.
     *   · ادى الاختبار وسلمه.
     *
     * فالميزة لا تمس الا من له صف فيه اختبار منشور ولم يؤده. وهذا هو
     * الشرط الذي طلب: «بس ده في حالة ان فيه اختبار اصلا للصف اللي هو فيه».
     */
    public function gate($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return null;

        /* الدور يشتق من الحارس الموحد لا بفحص `is_instructor` هنا: نسخة
           ثانية من الاشتقاق تفترق عن الاولى عند اول تعديل. */
        if (function_exists('tq_role') && tq_role() !== 'student') return null;

        try {
            $grade_id = (int) $this->db->select('grade_id')->where('id', $user_id)
                                       ->get('users')->row('grade_id');
        } catch (Throwable $e) {
            return null;
        }
        if ($grade_id <= 0) return null;

        $exam = $this->exam_for_grade($grade_id);
        if (!$exam) return null;

        /* اختبار منشور بلا سؤال واحد يحبس كل طلاب صفه امام شاشة فارغة.
           فالنشر وحده لا يكفي — الاسئلة هي الاختبار. */
        if (array_sum($this->level_tally((int) $exam['id'])) < 1) return null;

        if ($this->latest_attempt($user_id, (int) $exam['id'])) return null;

        return $exam;
    }

    /** هل ادى الطالب اختبار صفه؟ — الوجه الموجب من `gate()`. */
    public function is_cleared($user_id)
    {
        return $this->gate($user_id) === null;
    }

    /* =====================================================================
       الاداء والتصحيح
       ===================================================================== */

    /**
     * يفتح محاولة — او يعيد المفتوحة.
     *
     * محاولة معلقة لم تسلم تعاد لا تصنع ثانية: من فتح الاختبار ثم اغلق
     * المتصفح وعاد يجد محاولته لا صفا جديدا في كل مرة يفتح فيها الصفحة.
     */
    public function start($student_id, $exam_id)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;
        $exam_id    = (int) $exam_id;
        if ($student_id <= 0 || $exam_id <= 0) return 0;

        $open = $this->db->where('student_id', $student_id)
                         ->where('exam_id', $exam_id)
                         ->where('submitted_at IS NULL', null, false)
                         ->order_by('id', 'DESC')->limit(1)
                         ->get('tq_diag_attempts')->row_array();
        if ($open) return (int) $open['id'];

        $grade_id = (int) $this->db->select('grade_id')->where('id', $student_id)
                                   ->get('users')->row('grade_id');

        $this->db->insert('tq_diag_attempts', array(
            'exam_id'    => $exam_id,
            'student_id' => $student_id,
            'grade_id'   => $grade_id,
            'started_at' => date('Y-m-d H:i:s'),
        ));
        return (int) $this->db->insert_id();
    }

    /**
     * يصحح ويحسب المستوى ويحفظ.
     *
     * `$given` مصفوفة: معرف السؤال ⇒ نص الخيار المختار.
     *
     * والتصحيح في الخادم من `correct_answers` المقروءة هنا — لا مما ارسل
     * المتصفح. فالطالب يرسل اختياره وحده، ولا يرسل شيئا يقرر به صحته.
     */
    public function submit($student_id, $exam_id, $given)
    {
        $this->ensure_schema();
        $student_id = (int) $student_id;
        $exam = $this->exam($exam_id);

        if (!$exam || (string) $exam['status'] !== 'published') {
            return array('ok' => false, 'errors' => array('هذا الاختبار غير متاح الان.'));
        }

        /* الاعادة مغلقة افتراضا: نتيجة تعاد حتى تعجب صاحبها لا تقيس شيئا.
           والمسؤول يفتحها متى شاء بمفتاح في شاشة الاختبار. */
        $prev = $this->latest_attempt($student_id, (int) $exam['id']);
        if ($prev && (int) $exam['allow_retake'] !== 1) {
            return array('ok' => false, 'errors' => array('اديت هذا الاختبار من قبل.'),
                         'attempt_id' => (int) $prev['id']);
        }

        $questions = $this->ordered_questions((int) $exam['id'], true);
        if (!$questions) {
            return array('ok' => false, 'errors' => array('لا اسئلة في هذا الاختبار.'));
        }

        $attempt_id = $this->start($student_id, (int) $exam['id']);
        if ($attempt_id <= 0) {
            return array('ok' => false, 'errors' => array('تعذر تسجيل محاولتك. حاول مرة اخرى.'));
        }

        $this->load->model('taqdar_repo_model');

        $tally = array();
        foreach (self::level_keys() as $k) $tally[$k] = array('n' => 0, 'ok' => 0);

        $rows  = array();
        $score = 0;

        foreach ($questions as $q) {
            $lv  = isset($tally[$q['level']]) ? $q['level'] : 'beginner';
            $raw = isset($given[$q['id']]) ? $given[$q['id']] : null;

            /* الاجابة مصفوفة دائما عند الحكم: `is_answer_correct()` تقبل
               المفرد وتلفه، والتوحيد هنا يجعل ما يخزن مطابقا لما يحكم عليه. */
            $ans = ($raw === null || $raw === '') ? array() : (is_array($raw) ? $raw : array($raw));
            $ok  = (int) $this->taqdar_repo_model->is_answer_correct($q, $ans);

            $tally[$lv]['n']++;
            if ($ok) { $tally[$lv]['ok']++; $score++; }

            $rows[] = array(
                'attempt_id'  => $attempt_id,
                'question_id' => (int) $q['id'],
                'given'       => json_encode(array_values($ans), JSON_UNESCAPED_UNICODE),
                'is_correct'  => $ok,
            );
        }

        /* الاجابات تكتب قبل النتيجة: محاولة بنتيجة بلا اجابات لا تراجع
           ولا تفسر، ومحاولة باجابات بلا نتيجة تحسب من جديد متى شئنا. */
        if ($rows) {
            $this->db->where('attempt_id', $attempt_id)->delete('tq_diag_answers');
            $this->db->insert_batch('tq_diag_answers', $rows);
        }

        $level = $this->grade_attempt($tally, (int) $exam['level_threshold']);
        $plan  = (int) $exam['plan_' . $level];

        $this->db->where('id', $attempt_id)->update('tq_diag_attempts', array(
            'score'        => $score,
            'total'        => count($questions),
            'breakdown'    => json_encode($tally, JSON_UNESCAPED_UNICODE),
            'result_level' => $level,
            'plan_id'      => $plan,
            'submitted_at' => date('Y-m-d H:i:s'),
        ));

        return array(
            'ok'         => true,
            'attempt_id' => $attempt_id,
            'level'      => $level,
            'plan_id'    => $plan,
            'score'      => $score,
            'total'      => count($questions),
            'breakdown'  => $tally,
        );
    }

    /**
     * المستوى من التدرج الصاعد.
     *
     * يقرأ من الاعلى الى الادنى وياخذ اول مستوى بلغت فيه العتبة. ومستوى
     * بلا سؤال يتخطى بلا ان يقطع السلسلة — والا لحبس اختبار كتبت له اسئلة
     * مبتدئ ومتقدم وحدها كل طالب في «مبتدئ»، لان المتوسط الخالي يفشل عند
     * كل احد.
     *
     * والادنى هو المرتد: من لم يبلغ عتبة اي مستوى فموضعه البداية، وهي
     * نتيجة صالحة لها باقتها لا رسوب بلا مخرج.
     */
    public function grade_attempt($tally, $threshold = 60)
    {
        $threshold = max(1, min(100, (int) $threshold));
        $levels    = self::levels();

        /* الترتيب النازل مبني من `order` لا من `array_reverse` على المفاتيح:
           الترتيب معلن في `levels()` مرة واحدة، وقراءته من مصدره تعني ان
           اضافة مستوى رابع يوما لا تحتاج تعديل هذا السطر. */
        uasort($levels, function ($a, $b) { return $b['order'] - $a['order']; });

        foreach ($levels as $key => $meta) {
            $n = isset($tally[$key]['n']) ? (int) $tally[$key]['n'] : 0;
            if ($n < 1) continue;                       // مستوى بلا اسئلة لا يحكم ولا يقطع

            $ok  = isset($tally[$key]['ok']) ? (int) $tally[$key]['ok'] : 0;
            $pct = (int) floor(($ok * 100) / $n);
            if ($pct >= $threshold) return $key;
        }

        return 'beginner';
    }

    /* =====================================================================
       الكتابة من اللوحة — الاسئلة
       ===================================================================== */

    /**
     * يحفظ سؤالا (ينشئ او يحدث).
     *
     * الخيارات تنقى هنا لا في الشاشة: خيار فارغ بين خيارين يعرض زر اختيار
     * بلا نص، والاجابة الصحيحة تفحص انها **من** الخيارات فعلا — والا خزن
     * سؤال لا اجابة صحيحة له، فيخطئ فيه كل طالب ولا يظهر ذلك الا في
     * النتائج.
     */
    public function save_question($exam_id, $id, $post)
    {
        $this->ensure_schema();

        $exam_id = (int) $exam_id;
        $id      = (int) $id;
        $exam    = $this->exam($exam_id);
        if (!$exam) return array('ok' => false, 'errors' => array('الاختبار غير موجود.'));

        $title = trim((string) (isset($post['title']) ? $post['title'] : ''));
        $level = (string) (isset($post['level']) ? $post['level'] : 'beginner');
        if (!in_array($level, self::level_keys(), true)) $level = 'beginner';

        $raw = isset($post['options']) && is_array($post['options']) ? $post['options'] : array();
        $opts = array();
        foreach ($raw as $o) {
            $o = trim((string) $o);
            if ($o !== '') $opts[] = $o;
        }
        /* التكرار يمنع: خياران بنص واحد يجعلان الصحيح ملتبسا — والمقارنة
           بالنص لا بالموضع، فيصير احدهما صحيحا والاخر صحيحا معه. */
        $opts = array_values(array_unique($opts));

        $errors = array();
        if ($title === '')      $errors[] = 'نص السؤال مطلوب.';
        if (count($opts) < 2)   $errors[] = 'السؤال يحتاج خيارين مختلفين على الاقل.';
        if (count($opts) > 6)   $errors[] = 'الخيارات ستة على الاكثر.';

        /* الصحيح يأتي بموضعه في القائمة المرسلة، ويترجم الى نصه بعد
           التنقية — فالفهرس يقرأ على القائمة المنقاة نفسها لا على ما ارسل. */
        $correct_idx = (int) (isset($post['correct']) ? $post['correct'] : -1);
        $correct = '';
        if ($correct_idx >= 0) {
            $clean = array();
            foreach ($raw as $i => $o) {
                $o = trim((string) $o);
                if ($o !== '') $clean[$i] = $o;
            }
            $correct = isset($clean[$correct_idx]) ? $clean[$correct_idx] : '';
        }
        if ($correct === '' || !in_array($correct, $opts, true)) {
            $errors[] = 'حدد الاجابة الصحيحة من الخيارات.';
        }

        if ($errors) return array('ok' => false, 'errors' => $errors);

        $data = array(
            'exam_id'         => $exam_id,
            'level'           => $level,
            'title'           => $title,
            'type'            => 'radio',
            'options'         => json_encode($opts, JSON_UNESCAPED_UNICODE),
            'correct_answers' => json_encode(array($correct), JSON_UNESCAPED_UNICODE),
            'order'           => (int) (isset($post['order']) ? $post['order'] : 0),
        );

        if ($id > 0) {
            /* الشرط على `exam_id` لا على `id` وحده: معرف من نموذج معدل
               كان يحرر سؤال اختبار اخر بلا ان يظهر ذلك في شاشة. */
            $this->db->where('id', $id)->where('exam_id', $exam_id)
                     ->update('tq_diag_questions', $data);
        } else {
            if ((int) $data['order'] <= 0) {
                $max = (int) $this->db->select_max('`order`', 'mx')
                                      ->where('exam_id', $exam_id)
                                      ->get('tq_diag_questions')->row('mx');
                $data['order'] = $max + 1;
            }
            $this->db->insert('tq_diag_questions', $data);
            $id = (int) $this->db->insert_id();
        }

        return array('ok' => true, 'id' => $id);
    }

    /** يحذف سؤالا من اختباره — والشرط على الاثنين لا على المعرف وحده. */
    public function delete_question($exam_id, $id)
    {
        $this->ensure_schema();
        $this->db->where('id', (int) $id)->where('exam_id', (int) $exam_id)
                 ->delete('tq_diag_questions');
        return $this->db->affected_rows() > 0;
    }

    /* =====================================================================
       اللوحة — القراءة
       ===================================================================== */

    /**
     * محاولات اختبار بعينه، ومعها اسماء اصحابها.
     *
     * تقرأ ولا تحرر: النتيجة فعل الطالب، وتحريرها من اللوحة يجعل الكشف
     * شيئا اخر غير ما جرى.
     */
    public function attempts($exam_id = 0, $limit = 200)
    {
        $this->ensure_schema();

        try {
            $this->db->select('a.*, TRIM(CONCAT(COALESCE(u.first_name,""), " ", COALESCE(u.last_name,""))) AS student_name,
                               u.email AS student_email, g.name_ar AS grade_name, p.name_ar AS plan_name', false)
                     ->from('tq_diag_attempts a')
                     ->join('users  u', 'u.id = a.student_id', 'left')
                     ->join('grades g', 'g.id = a.grade_id',   'left')
                     ->join('plans  p', 'p.id = a.plan_id',    'left')
                     ->where('a.submitted_at IS NOT NULL', null, false)
                     ->order_by('a.id', 'DESC')->limit((int) $limit);

            if ((int) $exam_id > 0) $this->db->where('a.exam_id', (int) $exam_id);

            return $this->db->get()->result_array();
        } catch (Throwable $e) {
            return array();
        }
    }

    /**
     * توزيع نتائج اختبار على المستويات الثلاثة.
     *
     * وهو ما يقرأ به المسؤول اختباره: توزيع كله في «مبتدئ» يعني اسئلة
     * اصعب من صفها او عتبة اعلى مما ينبغي — لا يعني ان الصف كله ضعيف.
     */
    public function distribution($exam_id)
    {
        $this->ensure_schema();

        $out = array();
        foreach (self::level_keys() as $k) $out[$k] = 0;

        try {
            $rows = $this->db->select('result_level, COUNT(*) AS n', false)
                             ->where('exam_id', (int) $exam_id)
                             ->where('submitted_at IS NOT NULL', null, false)
                             ->group_by('result_level')
                             ->get('tq_diag_attempts')->result_array();
            foreach ($rows as $r) {
                if (isset($out[$r['result_level']])) $out[$r['result_level']] = (int) $r['n'];
            }
        } catch (Throwable $e) {
            // جدول لم يستعمل بعد: توزيع اصفار اهون من شاشة بيضاء
        }
        return $out;
    }

    /** عدد الاختبارات المنشورة — لشاشة الجهوزية في لوحة القيادة. */
    public function published_count()
    {
        $this->ensure_schema();
        try {
            return (int) $this->db->where('status', 'published')->count_all_results('tq_diag_exams');
        } catch (Throwable $e) {
            return 0;
        }
    }
}
