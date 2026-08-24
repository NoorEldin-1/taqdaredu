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
 *
 * ═══ من يعلم بالنتيجة ═══
 *
 * الطالب يراها في الشاشة، وهذا كل ما كان يقع. ومن يقرر امر الباقة فعلا
 * هو من يدفع — وهو في الغالب ليس صاحب الشاشة. فالنتيجة تخرج من المتصفح
 * الى بريد من يعنيه امرها:
 *
 *   ١ — ولي امر مربوط بموافقة **نشطة** (`parent_links`)، بحسابه وبريده.
 *   ٢ — `users.guardian_email` — البريد الذي كتبه الطالب عند التسجيل،
 *       وهو مشروط على من هو دون الخامسة عشرة. لا حساب له ولا تفضيلات،
 *       فالبريد وحده سبيله.
 *   ٣ — وان لم يكن هذا ولا ذاك، فبريد الطالب نفسه: «اي بريد مرتبط
 *       بالحساب» احسن من نتيجة لا تخرج من الشاشة.
 *
 * والاول وحده يستقبل ايضا اشعارا داخل المنصة، لان له حسابا يقرأ فيه.
 *
 * وثلاثة قيود على هذا الباب:
 *
 * · **الرابط النشط لا غيره.** `pending` طلب لم يوافق عليه الطالب،
 *   و`revoked` موافقة سحبت — وكلاهما لا يفتح بيانات احد، فلا يستقبل
 *   عنها بريدا. ولا يخمن ولي الامر بتشابه اسم او جوال.
 * · **البريد لا يرسل مرتين.** `notified_at` على المحاولة، وهو ما يجعل
 *   نداء الكرون بعد نداء التسليم بلا اثر ثان.
 * · **وفشل البريد لا يفسد التسليم.** النتيجة محفوظة قبل ان يفتح اتصال،
 *   وما لم يرسل يبقى `notified_at` فارغا فيلتقطه `taqdar_cron_events
 *   placements` — فمن ضبط البريد بعد ان ادى طلابه يصلهم ما فات، ولا
 *   يعيد الارسال لمن وصله.
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
                    `notified_at`  datetime     DEFAULT NULL,
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

            /* العمود يضاف على القائم لا على المنشأ وحده: `CREATE TABLE IF
               NOT EXISTS` لا تمس جدولا موجودا، فمن نصب المنصة قبل هذا
               العمل يبقى جدوله بلا `notified_at` — واول تحديث عليه يرمي،
               فيسقط التسليم بعد ان تكون النتيجة قد حفظت.

               والخبيئة تفرغ قبل الفحص: CodeIgniter يحفظ اسماء اعمدة كل
               جدول في الطلب الواحد، فمن قرأ الجدول قبل هذا السطر — وشاشة
               الوحدة الموصوفة تقرؤه — يعطي قائمة بائتة، فيعاد `ADD COLUMN`
               على عمود قائم. وفي البيئة المحلية `db_debug` مفتوح، فذلك
               الخطأ صفحة بيضاء لا استثناء يمسك. */
            $this->db->data_cache = array();
            if (!$this->db->field_exists('notified_at', 'tq_diag_attempts')) {
                $this->db->query('ALTER TABLE `tq_diag_attempts`
                                  ADD COLUMN `notified_at` datetime DEFAULT NULL');
            }

            /* TQ-QIMG · صورة السؤال — بالقاعدة نفسها: العمود يضاف على
               الجدول القائم كما اضيف `notified_at` اعلاه. والمعادلة
               والرسم البياني لا يكتبان حروفا، وكان بنك الاسئلة نصا
               خالصا. انظر `tq_qimage_upload()` في `taqdar_helper`. */
            if (!$this->db->field_exists('image', 'tq_diag_questions')) {
                $this->db->query('ALTER TABLE `tq_diag_questions`
                                  ADD COLUMN `image` varchar(190) DEFAULT NULL');
            }
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

        $cols = 'id, exam_id, level, title, type, options, image, `order`'
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

        $this->load->model('taqdar_site_model');
        foreach (self::levels() as $key => $meta) {
            /* الباقة تلزم للمستوى الذي فيه اسئلة وحده: اختبار بمستويين
               لا يطالب بباقة مستوى لا يبلغه احد. */
            if ($tally[$key] < 1) continue;

            $pid = (int) $exam['plan_' . $key];
            if ($pid <= 0) {
                $why[] = 'مستوى «' . $meta['label'] . '» فيه اسئلة ولا باقة مربوطة به.';
                continue;
            }

            /* **وباقة مربوطة ليست باقة تفتح** (TQ-DIAG-404): قائمة
               الاختيار تعرض الموقوفة بعلامتها عمدا، ومن اوقف باقة بعد ان
               ربطها لا يمر على الاختبارات. فيسأل هنا عن البوابة نفسها
               التي يفتحها الطالب — والا خرجت الرسالة تسمي باقة وزرها 404
               ولا شيء يقوله الا شكواه. */
            if (!$this->taqdar_site_model->plan_row($pid)) {
                $why[] = 'باقة مستوى «' . $meta['label'] . '» موقوفة او محذوفة — '
                       . 'فالنتيجة ترشحها ورابطها لا يفتح. اخترها من جديد او اعد تفعيلها.';
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
       من يعلم بالنتيجة — البريد والاشعار
       ===================================================================== */

    /**
     * الى من ترسل نتيجة هذا الطالب؟
     *
     * ترد بنودا، لكل بند `email` و`kind` (`parent` · `guardian` · `student`)
     * و`user_id` (صفر لمن لا حساب له). والترتيب مقصود: من له حساب اولا.
     *
     * وبريد الطالب نفسه **بديل لا اضافة**: يدخل القائمة حين تخلو من ولي
     * امر ومن بريد ولي امر مكتوب، لا معهما. فمن له ولي امر يعلم وليه،
     * ومن لا احد له يعلم هو — ولا ترسل رسالتان عن حدث واحد.
     *
     * والتكرار يطوى بالبريد لا بالحساب: ولي امر مربوط كتب الطالب بريده
     * نفسه في `guardian_email` شخص واحد، ورسالتان اليه تقرآن عطلا.
     */
    public function result_audience($student_id)
    {
        $sid = (int) $student_id;
        if ($sid <= 0) return array();

        $u = $this->db->select('email, guardian_email')
                      ->where('id', $sid)->get('users')->row_array();
        if (!$u) return array();

        $out  = array();
        $seen = array();

        /* البريد يفك ترميزه قبل ان يفحص: `Login::register()` يحفظ
           `html_escape($email)`، فبريد فيه فاصلة عليا — وهي حرف مقبول —
           يخزن `&#39;` ويرفضه `FILTER_VALIDATE_EMAIL` فيسقط صاحبه من
           القائمة صامتا. */
        $push = function ($email, $kind, $uid) use (&$out, &$seen) {
            $email = trim(html_entity_decode((string) $email, ENT_QUOTES, 'UTF-8'));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;

            $key = strtolower($email);
            if (isset($seen[$key])) return;
            $seen[$key] = true;

            $out[] = array('email' => $email, 'kind' => $kind, 'user_id' => (int) $uid);
        };

        /* ١ — اولياء الامر بموافقة نشطة. والتفضيل يفحص هنا كما يفحص عند
               كتابة الاشعار: من اوقف هذا النوع من شاشة اعداداته لا يصله
               بريد به — والا صار الاعداد كلاما. */
        if ($this->db->table_exists('parent_links')) {
            $this->load->model('taqdar_events_model');

            try {
                $rows = $this->db->query(
                    'SELECT DISTINCT u.`id`, u.`email`
                       FROM `parent_links` pl
                       JOIN `users` u ON u.`id` = pl.`parent_user_id` AND u.`status` = 1
                      WHERE pl.`student_id` = ? AND pl.`status` = "active"',
                    array($sid)
                )->result_array();
            } catch (Throwable $e) {
                $rows = array();
            }

            foreach ($rows as $r) {
                if (!$this->taqdar_events_model->parent_wants((int) $r['id'], 'placement_result')) {
                    continue;
                }
                $push($r['email'], 'parent', (int) $r['id']);
            }
        }

        /* ٢ — البريد المكتوب عند التسجيل. لا حساب له ولا تفضيلات، وهو
               البريد الذي جمع لهذا الغرض بعينه: ان يعلم ولي الامر بما
               يخص ابنه. ولا يكتم لانه لا شاشة له يكتم منها. */
        $push(isset($u['guardian_email']) ? $u['guardian_email'] : '', 'guardian', 0);

        /* ٣ — البديل: الطالب نفسه، وحين لا احد سواه. */
        if (!$out) {
            $push($u['email'], 'student', $sid);
        }

        return $out;
    }

    /**
     * يبلغ بنتيجة محاولة: اشعارا في المنصة وبريدا الى من يعنيه.
     *
     * تنادى من مسار التسليم اولا — فالنتيجة تصل حين تعني شيئا لا بعد
     * ساعة — ومن الكرون بعده لما لم يرسل.
     *
     * وترتيبها مقصود:
     *   · الاشعار داخل المنصة يكتب اولا ولا يتوقف على بريد مضبوط.
     *   · ثم البريد. وبلا ضبط لا يدمغ `notified_at` — فيلتقطها الكرون
     *     متى ضبط، ولا تضيع نتيجة اديت قبل ان يكتب مسؤول كلمة مرور.
     *   · والدمغ يقع كذلك حين لا مستلم اصلا: قائمة فارغة ليست فشلا
     *     يعاد، فلا تسأل كل ليلة الى ان تخرج من نافذة المسح.
     *
     * @param  int  $attempt_id
     * @param  bool $force  يعيد الارسال لمحاولة دمغت — لزر «اعد الارسال».
     * @return array sent · to · skipped
     */
    public function notify_result($attempt_id, $force = false)
    {
        $this->ensure_schema();

        $attempt_id = (int) $attempt_id;
        $out = array('sent' => 0, 'to' => array(), 'skipped' => '');
        if ($attempt_id <= 0) {
            $out['skipped'] = 'bad_id';
            return $out;
        }

        try {
            $a = $this->db->where('id', $attempt_id)
                          ->where('submitted_at IS NOT NULL', null, false)
                          ->get('tq_diag_attempts')->row_array();
        } catch (Throwable $e) {
            $out['skipped'] = 'no_table';
            return $out;
        }

        /* محاولة لم تسلم لا نتيجة لها تبلغ: من فتح الاختبار واغلق المتصفح
           لا يرسل عنه شيء. */
        if (!$a) {
            $out['skipped'] = 'not_submitted';
            return $out;
        }
        /* `isset` لا القراءة المباشرة: العمود يضاف في `ensure_schema()`،
           وان فشلت الاضافة على قاعدة قديمة فالغياب يقرأ «لم يبلغ» ولا يرمي
           تنبيها في وسط رد الصفحة. */
        if (!$force && trim((string) (isset($a['notified_at']) ? $a['notified_at'] : '')) !== '') {
            $out['skipped'] = 'already';
            return $out;
        }

        $sid   = (int) $a['student_id'];
        $level = (string) $a['result_level'];
        $label = self::level_label($level);
        $score = (int) $a['score'];
        $total = (int) $a['total'];

        $levels = self::levels();
        $lead   = isset($levels[$level]) ? $levels[$level]['lead'] : '';

        /* الباقة تقرأ كاملة لا بمعرفها: رسالة تقول «باقة رقم ٣» لا تقول
           شيئا، والاسم والسعر والمدة هي ما يقرر عليه من يدفع.

           **وتقرأ من بوابة عنوانها العام لا من الجدول** (TQ-DIAG-404):
           `Taqdar_billing_model::plan()` ترد أي صف بمعرفه، وصفحة
           `‎/plan/<الرمز>‎` ترد `active = 1` وحدها. فباقة أوقفت بعد أن
           ربطت بمستوى — أو كتب صفها بلا رمز — كانت تخرج رسالة تسميها
           بسعرها وزرها يفتح 404. والاسم والزر من مصدر واحد الآن: إما
           باقة تفتح فتسمى، أو لا اسم ولا زر يخصها. */
        $this->load->model('taqdar_site_model');
        $plan     = $this->taqdar_site_model->plan_row((int) $a['plan_id']);
        $plan_url = $plan ? $this->taqdar_site_model->plan_url($plan) : null;
        if (!$plan_url) $plan = null;

        $this->load->model('taqdar_events_model');
        $name = $this->taqdar_events_model->student_name($sid);

        $numbers = 'اجاب ' . $score . ' من ' . $total . ' اجابة صحيحة.';
        $spread  = $this->breakdown_line($a['breakdown']);

        /* ── الاشعار داخل المنصة ─────────────────────────────────────
           نص محايد الضمير: الصف نفسه يصل الطالب ووليه، و«ابنك» في شاشة
           الطالب حديث عن غائب. و`email => false` لان البريد يرسل من هنا
           بجسم كامل — ونداء `maybe_email` كان يبعث سطرا مكررا معه. */
        $note = 'نتيجة اختبار تحديد المستوى: ' . $label . ' — '
              . $score . ' من ' . $total . ' اجابة صحيحة.'
              . ($plan ? ' والباقة المرشحة: ' . (string) $plan['name_ar'] . '.' : '');

        $this->taqdar_events_model->notify_student_and_parents($sid, 'placement_result', array(
            'key'         => 'diag_attempt:' . $attempt_id,
            'window_days' => 60,
            'text'        => $note,
            'email'       => false,
        ));

        /* ── البريد ─────────────────────────────────────────────────── */
        $audience = $this->result_audience($sid);
        if (!$audience) {
            $this->mark_notified($attempt_id);
            $out['skipped'] = 'no_recipient';
            return $out;
        }

        $this->load->model('taqdar_mail_model');
        if (!$this->taqdar_mail_model->configured()) {
            /* بلا دمغ: هذه نتيجة تنتظر بريدا يضبط، لا نتيجة ابلغ عنها. */
            $out['skipped'] = 'mail_off';
            return $out;
        }

        /* الرابط الى صفحة الباقة العامة لا الى بوابة الطالب: من يقرأ
           الرسالة قد لا يكون له حساب اصلا (`guardian_email`)، ورابط يطلب
           منه تسجيل دخول لا يفتح له شيئا. */
        $cta = $plan
            ? array('label' => 'اطلع على الباقة', 'href' => $plan_url)
            : array('label' => 'اطلع على الباقات', 'href' => site_url('plans'));

        $plan_lines = array();
        if ($plan) {
            $price = number_format(((int) $plan['price']) / 100, 0, '.', ',');
            $plan_lines[] = 'الباقة التي ترشحها المنصة على هذه النتيجة: '
                          . (string) $plan['name_ar'] . ' — ' . $price . ' ريال '
                          . tqs_period_label((int) $plan['duration_days']) . '.';
            $plan_lines[] = 'وهي توصية لا الزام: كل الباقات مفتوحة، والاختيار لكم.';
        } else {
            $plan_lines[] = 'وباقات الصف معروضة كلها في صفحة الباقات.';
        }

        /* مجموعتان لا واحدة: من يخاطب **عن** الطالب، والطالب نفسه. ورسالة
           تقول «ابنك» لمن هو الابن تقرأ عطلا، ورسالة تقول «اديت» لولي
           الامر تقرأ خطأ في المرسل اليه. */
        $groups = array('about' => array(), 'self' => array());
        foreach ($audience as $r) {
            $groups[$r['kind'] === 'student' ? 'self' : 'about'][] = $r['email'];
            $out['to'][] = $r['email'];
        }

        $sent = 0;

        if ($groups['about']) {
            $sent += (int) $this->taqdar_mail_model->send_lines(
                $groups['about'],
                'نتيجة اختبار تحديد المستوى — ' . $name,
                array_merge(array(
                    'السلام عليكم،',
                    'ادى ' . $name . ' اختبار تحديد المستوى في منصة تقدر، وهذه نتيجته.',
                    'موضعه الان: ' . $label . '. ' . $numbers,
                    $spread,
                    $lead,
                ), $plan_lines, array(
                    'والاختبار تشخيص لا امتحان: لا رسوب فيه، وانما يقول اين يبدأ '
                    . 'ليكون ما يدرسه على قدره.',
                )),
                $cta
            );
        }

        if ($groups['self']) {
            $sent += (int) $this->taqdar_mail_model->send_lines(
                $groups['self'],
                'نتيجة اختبار تحديد المستوى',
                array_merge(array(
                    'هذه نتيجة اختبار تحديد المستوى الذي اديته في منصة تقدر.',
                    'موضعك الان: ' . $label . '. اجبت ' . $score . ' من ' . $total
                    . ' اجابة صحيحة.',
                    $spread,
                    $lead,
                ), $plan_lines),
                $cta
            );
        }

        /* الدمغ على ارسال وقع فعلا: خادم رفض الرسالة يترك الصف كما هو
           فيعاد في مسح الكرون التالي. */
        if ($sent > 0) {
            $this->mark_notified($attempt_id);
        }

        $out['sent'] = $sent;
        if ($sent < 1) $out['skipped'] = 'send_failed';
        return $out;
    }

    /**
     * محاولات سلمت ولم يبلغ عنها — طابور الكرون.
     *
     * النافذة تحد الاعادة: ما مضى عليه اسبوعان لا يبلغ عنه بعد ذلك.
     * ورسالة عن نتيجة قديمة تصل ولي امر لا يذكرها اشبه بالخلل من الخدمة،
     * ومحاولة تعصى الارسال ابدا كانت ستسأل الخادم كل ليلة الى الابد.
     */
    public function pending_notifications($days = 14, $limit = 100)
    {
        $this->ensure_schema();

        try {
            $rows = $this->db->select('id')
                             ->where('submitted_at IS NOT NULL', null, false)
                             ->where('notified_at IS NULL', null, false)
                             ->where('submitted_at >=', date('Y-m-d H:i:s', time() - max(1, (int) $days) * 86400))
                             ->order_by('id', 'ASC')->limit(max(1, (int) $limit))
                             ->get('tq_diag_attempts')->result_array();
        } catch (Throwable $e) {
            return array();
        }

        $ids = array();
        foreach ($rows as $r) $ids[] = (int) $r['id'];
        return $ids;
    }

    /** توزيع الصحيح على المستويات، سطرا يقرأ. */
    private function breakdown_line($json)
    {
        $bd = json_decode((string) $json, true);
        if (!is_array($bd)) return '';

        $parts = array();
        foreach (self::levels() as $key => $meta) {
            $n = isset($bd[$key]['n']) ? (int) $bd[$key]['n'] : 0;
            if ($n < 1) continue;   // مستوى بلا اسئلة لا يذكر بصفر يقرأ رسوبا
            $ok = isset($bd[$key]['ok']) ? (int) $bd[$key]['ok'] : 0;
            $parts[] = $meta['label'] . ': ' . $ok . ' من ' . $n;
        }

        return $parts ? ('التوزيع على المستويات — ' . implode(' · ', $parts) . '.') : '';
    }

    private function mark_notified($attempt_id)
    {
        try {
            $this->db->where('id', (int) $attempt_id)
                     ->update('tq_diag_attempts', array('notified_at' => date('Y-m-d H:i:s')));
        } catch (Throwable $e) {
            /* الرسالة ارسلت فعلا؛ وفقدان الدمغة يعني اعادة محتملة في مسح
               الكرون، وهي اهون من ان يرد التسليم خطأ على نتيجة حفظت. */
            log_message('error', 'TQ-DIAG: تعذر دمغ notified_at للمحاولة '
                . (int) $attempt_id . ' — ' . $e->getMessage());
        }
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

        /* TQ-QIMG · الصورة.
           `$post['image']` ليست ملفا: المتحكم يرفع الملف قبل ان ينادي هنا
           ويمرر اسم المحفوظ، لان `$this->input->post()` لا يرى `$_FILES`.
           وثلاث حالات لا اثنتان: لم يرفع شيء ولم يطلب حذف — تترك كما هي؛
           رفع جديد — يكتب ويحذف القديم؛ طلب حذف — يفرغ العمود ويحذف الملف. */
        $old = ($id > 0)
            ? (string) $this->db->select('image')->where('id', $id)
                                ->where('exam_id', $exam_id)
                                ->get('tq_diag_questions')->row('image')
            : '';

        $new  = isset($post['image']) ? trim((string) $post['image']) : '';
        $drop = !empty($post['image_remove']);

        if ($new !== '') {
            $data['image'] = $new;
            if ($old !== '' && $old !== $new) tq_qimage_delete($old);
        } elseif ($drop) {
            $data['image'] = null;
            if ($old !== '') tq_qimage_delete($old);
        }

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

        /* الملف يقرأ قبل الحذف: بعده لا يبقى صف يدل عليه، فتبقى الصورة
           في القرص الى الابد بلا من يشير اليها. */
        $img = (string) $this->db->select('image')->where('id', (int) $id)
                                 ->where('exam_id', (int) $exam_id)
                                 ->get('tq_diag_questions')->row('image');

        $this->db->where('id', (int) $id)->where('exam_id', (int) $exam_id)
                 ->delete('tq_diag_questions');

        $ok = $this->db->affected_rows() > 0;
        if ($ok && $img !== '') tq_qimage_delete($img);
        return $ok;
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
