<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * اختبار الدرس — الأسئلة التي تفتح الدرس التالي.
 *
 * ----------------------------------------------------------------------
 * لماذا هذا ليس نظام اختبارات رابعا
 *
 * كان في القاعدة ثلاثة أنظمة لا يعرف أحدها الآخر:
 *
 *   ١ — الموروث من Academy: الاختبار **درس** نوعه `quiz`، وأسئلته
 *       `question.quiz_id`، ونتائجه `quiz_results`. تقرؤه شاشة
 *       `student/exams` وتقارير ولي الأمر وتصحيح المعلم.
 *   ٢ — بوابة إتقان تقدر: `assessments(type='review')` وأسئلتها تشتق من
 *       `objectives` عبر `question.objective_id`، ونتائجها
 *       `attempts`/`answers`. تقرؤها صفحة الدرس ودفتر الأخطاء وخريطة
 *       الإتقان.
 *   ٣ — التشخيصي: `tq_diag_*` مستقل تماما.
 *
 * فنتيجة الطالب في مكانين، وشاشتان تقرأ كل منهما نصف الحقيقة.
 *
 * وهذا **لا يضيف رابعا**: اختبار الدرس هو بوابة الإتقان نفسها
 * (`type='review'`)، والذي يتغير **مصدر أسئلتها وحده**:
 *
 *     أسئلة مؤلفة لهذا التقييم  →  هي الاختبار
 *     ولا شيء                    →  أسئلة الأهداف كما كان يجري
 *
 * وأثر ذلك أن كل ما بني على البوابة يعمل للاختبار الجديد بلا سطر:
 * القفل والفتح · المحاولات الثلاث وتصعيدها إلى المعلم · دفتر الأخطاء ·
 * خريطة الإتقان · التكرار المتباعد · تصحيح المعلم. ونظام رابع كان
 * سيحتاج نسخة ثانية من كل واحد منها.
 * ----------------------------------------------------------------------
 *
 * والتأليف بالطريقة نفسها التي يؤلف بها الاختبار التشخيصي: نص السؤال،
 * وصورة اختيارية، وحتى ستة خيارات، ودائرة أمام الصحيح. والمحرر **قالب
 * واحد** يركب في ثلاث شاشات — انظر [_tq_question_editor.php].
 */
class Taqdar_quiz_model extends CI_Model
{
    const SCHEMA_VERSION = 1;

    /** الحد الأعلى للخيارات — كما في التشخيصي حرفا بحرف. */
    const MAX_OPTIONS = 6;

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
     * عمود واحد يربط السؤال بتقييمه.
     *
     * `question` جدول موروث بلا هجرة، وفيه `quiz_id` (يشير إلى **درس**
     * نوعه اختبار في النظام الموروث) و`objective_id` (يشير إلى هدف).
     * وليس فيهما ما يشير إلى `assessments` — فسؤال يؤلف لاختبار درس لا
     * موضع له.
     *
     * ولا يعاد استعمال `quiz_id`: قيمته في ١١٤ صفا اليوم تعني معرف درس،
     * وحقنها بمعرف تقييم يجعل العمود يعني شيئين حسب من كتبه — وهو تماما
     * ما جعل `scope_id` في `plans` يحتاج `refswitch`.
     */
    public function install_schema()
    {
        if ($this->schema_done) return;
        $this->schema_done = true;

        try {
            $this->db->data_cache = array();

            if (!$this->db->field_exists('assessment_id', 'question')) {
                $this->db->query(
                    'ALTER TABLE `question`
                     ADD COLUMN `assessment_id` int(10) unsigned DEFAULT NULL COMMENT "assessments.id"'
                );
                $this->db->query(
                    'ALTER TABLE `question` ADD KEY `idx_question_assessment` (`assessment_id`, `order`)'
                );
            }

            /* صورة السؤال — العمود نفسه الذي يضيفه التشخيصي، وبالمساعد
               نفسه: محرر واحد لا يحتمل عمودا في جدول وغيابه في آخر. */
            tq_qimage_ensure('question');

            /* عدد المحاولات المسموح: صفر = بلا حد، وهو الافتراض الذي
               تعمل به البوابة اليوم («العقاب بقاء القفل لا منع المحاولة»). */
            if (!$this->db->field_exists('attempts_allowed', 'assessments')) {
                $this->db->query(
                    'ALTER TABLE `assessments`
                     ADD COLUMN `attempts_allowed` int(11) NOT NULL DEFAULT 0'
                );
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-QUIZ: تعذر تركيب المخطط — ' . $e->getMessage());
        }
    }

    /* =====================================================================
       القراءة
       ===================================================================== */

    /**
     * تقييم بوابة هذا الدرس.
     *
     * `type='review'` لا `'quiz'`: المفتاح `uq_assessment_type_lesson`
     * يضمن صفا واحدا لكل (نوع، درس)، والبوابة تقرأ `review` — فتقييم
     * بنوع آخر لا يحكم شيئا مهما امتلأ بالأسئلة.
     *
     * @param bool $create ينشئه إن لم يوجد — عند تأليف أول سؤال
     */
    public function quiz_of($lesson_id, $create = false)
    {
        $this->install_schema();

        $lesson_id = (int) $lesson_id;
        if ($lesson_id <= 0) return null;

        $row = $this->db->where('type', 'review')->where('lesson_id', $lesson_id)
                        ->get('assessments')->row_array();
        if ($row || !$create) return $row ?: null;

        $this->db->insert('assessments', array(
            'type'           => 'review',
            'lesson_id'      => $lesson_id,
            'milestone_id'   => null,
            'path_id'        => null,
            'pass_mark'      => (int) $this->setting('mastery_pass_mark', 3),
            'time_limit_sec' => null,
        ));
        $id = (int) $this->db->insert_id();

        return $this->db->where('id', $id)->get('assessments')->row_array();
    }

    /**
     * أسئلة اختبار الدرس المؤلفة — لا المشتقة من الأهداف.
     *
     * @param bool $with_answers للتأليف. ولا ترسل إلى الطالب أبدا:
     *                          مفاتيح الحل في مصدر الصفحة غش بضغطة.
     */
    public function questions($lesson_id, $with_answers = false)
    {
        $this->install_schema();

        $quiz = $this->quiz_of($lesson_id);
        if (!$quiz) return array();

        $cols = 'q.`id`, q.`title`, q.`type`, q.`options`, q.`image`, q.`order`,
                 q.`objective_id`, o.`text` AS objective_text, o.`at_second`';
        if ($with_answers) $cols .= ', q.`correct_answers`';

        $rows = $this->db->query(
            'SELECT ' . $cols . '
               FROM `question` q
               LEFT JOIN `objectives` o ON o.`id` = q.`objective_id`
              WHERE q.`assessment_id` = ?
              ORDER BY q.`order` ASC, q.`id` ASC',
            array((int) $quiz['id'])
        )->result_array();

        foreach ($rows as &$r) {
            $r['options']      = $r['options'] ? json_decode($r['options'], true) : array();
            if (!is_array($r['options'])) $r['options'] = array();
            /* الرابط لا اسم الملف: الواجهة لا تعرف أين يعيش المجلد. */
            $r['image']        = tq_qimage_url($r['image']);
            $r['id']           = (int) $r['id'];
            $r['order']        = (int) $r['order'];
            $r['objective_id'] = $r['objective_id'] !== null ? (int) $r['objective_id'] : null;
            $r['at_second']    = (int) $r['at_second'];
        }
        unset($r);
        return $rows;
    }

    /** كم سؤالا مؤلفا لهذا الدرس — الرقم الذي يقرر أي مصدر يحكم. */
    public function count_questions($lesson_id)
    {
        $this->install_schema();

        $quiz = $this->quiz_of($lesson_id);
        if (!$quiz) return 0;

        return (int) $this->db->where('assessment_id', (int) $quiz['id'])
                              ->count_all_results('question');
    }

    /** هل لهذا الدرس اختبار مؤلف يحكم؟ */
    public function has_quiz($lesson_id)
    {
        return $this->count_questions($lesson_id) > 0;
    }

    /**
     * عدد أسئلة اختبار كل درس في مقرر — استعلام واحد لا واحد لكل درس.
     *
     * شاشة المقرر تعرض العدد بجوار كل درس، ونداء `count_questions()` في
     * حلقة يعني استعلامين لكل صف في مقرر قد يحمل مئة درس.
     *
     * @return array معرف الدرس => عدد الأسئلة (والغائب صفر)
     */
    public function counts_for_course($course_id)
    {
        $this->install_schema();

        $course_id = (int) $course_id;
        if ($course_id <= 0) return array();

        try {
            $rows = $this->db->query(
                'SELECT s.`lesson_id`, COUNT(q.`id`) AS n
                   FROM `assessments` s
                   JOIN `lesson` l ON l.`id` = s.`lesson_id`
                   LEFT JOIN `question` q ON q.`assessment_id` = s.`id`
                  WHERE s.`type` = "review" AND l.`course_id` = ?
                  GROUP BY s.`lesson_id`',
                array($course_id)
            )->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-QUIZ counts_for_course: ' . $e->getMessage());
            return array();
        }

        $out = array();
        foreach ($rows as $r) $out[(int) $r['lesson_id']] = (int) $r['n'];
        return $out;
    }

    /**
     * ما ينقص هذا الاختبار قبل أن يحكم — صراحة، لا «غير جاهز».
     *
     * والجهوزية تقاس بحد النجاح: اختبار بثلاثة أسئلة وحد نجاح خمسة
     * **لا يجتاز أبدا** — فيبقى الدرس التالي مقفلا على كل طالب، ولا شيء
     * في الشاشة يقول لماذا.
     */
    public function readiness($lesson_id)
    {
        $this->install_schema();

        $n    = $this->count_questions($lesson_id);
        $quiz = $this->quiz_of($lesson_id);
        $pass = $quiz ? (int) $this->pass_mark($quiz) : 3;
        $why  = array();

        if ($n === 0) {
            $why[] = 'لا أسئلة بعد.';
        } elseif ($n < 3) {
            $why[] = 'سؤالان أو أقل يقيسان الحظ لا الفهم — والموصى به خمسة.';
        }
        if ($n > 0 && $pass > $n) {
            $why[] = 'حد النجاح (' . $pass . ') أكبر من عدد الأسئلة (' . $n
                   . ') — فلا يجتازه أحد أبدا، ويبقى الدرس التالي مقفلا على الجميع.';
        }

        $no_obj = 0;
        foreach ($this->questions($lesson_id, false) as $q) {
            if (empty($q['objective_id'])) $no_obj++;
        }
        if ($no_obj > 0) {
            $why[] = $no_obj . ' من الأسئلة بلا هدف مرتبط — فلا تدخل خريطة الإتقان'
                   . ' ولا دفتر الأخطاء، ولا يعرف النظام إلى أي دقيقة يعيد من أخطأ فيها.';
        }

        return array(
            'ok'        => ($n > 0 && $pass <= $n),
            'questions' => $n,
            'pass_mark' => $pass,
            'why'       => $why,
        );
    }

    /* =====================================================================
       التأليف
       ===================================================================== */

    /**
     * يحفظ سؤالا — إنشاء أو تحرير.
     *
     * والقواعد هي قواعد `Taqdar_diag_model::save_question()` نفسها، لأن
     * المحرر واحد: خياران على الأقل وستة على الأكثر، بلا تكرار، والصحيح
     * **من الخيارات** — يأتي بموضعه ويترجم إلى نصه بعد التنقية، فالفهرس
     * يقرأ على القائمة المنقاة لا على ما أرسل.
     *
     * @param array $actor من `Taqdar_curriculum_model::actor()` — الملكية تفحص هنا
     */
    public function save_question($actor, $lesson_id, $id, $post)
    {
        $this->install_schema();

        $lesson_id = (int) $lesson_id;
        $id        = (int) $id;

        if (!$this->may_edit($actor, $lesson_id)) {
            return array('ok' => false, 'errors' => array('هذا الدرس ليس لك.'));
        }

        $title = trim((string) $this->v($post, 'title'));
        $title = preg_replace('/\s+/u', ' ', $title);

        /* الخيارات: الفارغ يسقط، والتكرار يمنع — خياران بنص واحد يجعلان
           الصحيح ملتبسا، لأن المقارنة بالنص لا بالموضع. */
        $raw  = is_array($this->v($post, 'options')) ? $this->v($post, 'options') : array();
        $opts = array();
        foreach ($raw as $o) {
            if (!is_scalar($o)) continue;
            $o = trim((string) $o);
            if ($o !== '') $opts[] = $o;
        }
        $opts = array_values(array_unique($opts));

        $errors = array();
        if ($title === '')                  $errors[] = 'نص السؤال مطلوب.';
        if (count($opts) < 2)               $errors[] = 'السؤال يحتاج خيارين مختلفين على الأقل.';
        if (count($opts) > self::MAX_OPTIONS) $errors[] = 'الخيارات ' . self::MAX_OPTIONS . ' على الأكثر.';

        $correct_idx = (int) $this->v($post, 'correct', -1);
        $correct     = '';
        if ($correct_idx >= 0) {
            $clean = array();
            foreach ($raw as $i => $o) {
                if (!is_scalar($o)) continue;
                $o = trim((string) $o);
                if ($o !== '') $clean[$i] = $o;
            }
            $correct = isset($clean[$correct_idx]) ? $clean[$correct_idx] : '';
        }
        if ($correct === '' || !in_array($correct, $opts, true)) {
            $errors[] = 'حدد الإجابة الصحيحة من الخيارات.';
        }

        /* الهدف: اختياري، ولكن لا بد أن يكون من هذا الدرس. هدف من درس
           آخر يكتب إتقانا في مفهوم لم يدرس هنا. */
        $objective_id = (int) $this->v($post, 'objective_id', 0);
        if ($objective_id > 0) {
            $owner = (int) $this->db->select('lesson_id')->where('id', $objective_id)
                                    ->get('objectives')->row('lesson_id');
            if ($owner !== $lesson_id) {
                $errors[] = 'الهدف المحدد ليس من هذا الدرس.';
                $objective_id = 0;
            }
        }

        if ($errors) return array('ok' => false, 'errors' => $errors);

        $quiz = $this->quiz_of($lesson_id, true);
        if (!$quiz) {
            return array('ok' => false, 'errors' => array('تعذر إنشاء تقييم هذا الدرس.'));
        }

        $data = array(
            'assessment_id'   => (int) $quiz['id'],
            'objective_id'    => $objective_id > 0 ? $objective_id : null,
            'title'           => $title,
            'type'            => 'single_choice',
            'number_of_options' => count($opts),
            'options'         => json_encode($opts, JSON_UNESCAPED_UNICODE),
            'correct_answers' => json_encode(array($correct), JSON_UNESCAPED_UNICODE),
            'order'           => (int) $this->v($post, 'order', 0),
        );

        /* الصورة: ثلاث حالات لا اثنتان — لم يرفع ولم يطلب حذفا (تترك)،
           رفع جديدا (يكتب ويحذف القديم)، طلب حذفا (يفرغ ويحذف الملف).
           والمتحكم يرفع قبل النداء ويمرر الاسم: `$this->input->post()`
           لا ترى `$_FILES` أصلا. */
        $old = ($id > 0)
            ? (string) $this->db->select('image')->where('id', $id)
                                ->where('assessment_id', (int) $quiz['id'])
                                ->get('question')->row('image')
            : '';
        $new  = trim((string) $this->v($post, 'image'));
        $drop = !empty($this->v($post, 'image_remove'));

        if ($new !== '') {
            $data['image'] = $new;
            if ($old !== '' && $old !== $new) tq_qimage_delete($old);
        } elseif ($drop) {
            $data['image'] = null;
            if ($old !== '') tq_qimage_delete($old);
        }

        if ($id > 0) {
            /* الشرط على التقييم لا على المعرف وحده: معرف من نموذج معدل
               كان يحرر سؤال اختبار درس آخر بلا أن يظهر ذلك في شاشة. */
            $this->db->where('id', $id)->where('assessment_id', (int) $quiz['id'])
                     ->update('question', $data);
            if ($this->db->affected_rows() < 1) {
                /* لا صف تغير: إما أن السؤال ليس من هذا الاختبار، وإما
                   أن القيم لم تتغير. والثاني ليس خطأ. */
                $exists = (int) $this->db->where('id', $id)
                                         ->where('assessment_id', (int) $quiz['id'])
                                         ->count_all_results('question');
                if (!$exists) return array('ok' => false, 'errors' => array('السؤال ليس من اختبار هذا الدرس.'));
            }
        } else {
            if ((int) $data['order'] <= 0) {
                $max = (int) $this->db->select_max('`order`', 'mx')
                                      ->where('assessment_id', (int) $quiz['id'])
                                      ->get('question')->row('mx');
                $data['order'] = $max + 1;
            }
            $this->db->insert('question', $data);
            $id = (int) $this->db->insert_id();
        }

        $this->log($actor, $id ? 'quiz.question.save' : 'quiz.question.create',
                   'question:' . $id, array('lesson_id' => $lesson_id));

        return array('ok' => true, 'id' => $id, 'message' => 'حفظ السؤال.');
    }

    /** يحذف سؤالا من اختبار درسه — والشرط على الاثنين لا على المعرف. */
    public function delete_question($actor, $lesson_id, $id)
    {
        $this->install_schema();

        $lesson_id = (int) $lesson_id;
        $id        = (int) $id;

        if (!$this->may_edit($actor, $lesson_id)) {
            return array('ok' => false, 'errors' => array('هذا الدرس ليس لك.'));
        }

        $quiz = $this->quiz_of($lesson_id);
        if (!$quiz) return array('ok' => false, 'errors' => array('لا اختبار لهذا الدرس.'));

        /* الملف يقرأ قبل الحذف: بعده لا يبقى صف يدل عليه فتبقى الصورة
           في القرص إلى الأبد. */
        $img = (string) $this->db->select('image')->where('id', $id)
                                 ->where('assessment_id', (int) $quiz['id'])
                                 ->get('question')->row('image');

        $this->db->where('id', $id)->where('assessment_id', (int) $quiz['id'])
                 ->delete('question');
        $ok = $this->db->affected_rows() > 0;

        if ($ok) {
            if ($img !== '') tq_qimage_delete($img);
            /* إجاباته تحذف معه: صف في `answers` يشير إلى سؤال محذوف
               يجعل مراجعة المحاولة تعرض سطرا بلا نص. */
            $this->db->where('question_id', $id)->delete('answers');
            $this->db->where('question_id', $id)->delete('review_queue');
            $this->log($actor, 'quiz.question.delete', 'question:' . $id,
                       array('lesson_id' => $lesson_id));
        }

        return array('ok' => $ok,
                     'message' => $ok ? 'حذف السؤال.' : 'تعذر الحذف — السؤال ليس من هذا الاختبار.',
                     'errors'  => $ok ? array() : array('السؤال ليس من اختبار هذا الدرس.'));
    }

    /** إعدادات الاختبار: حد النجاح والمدة وعدد المحاولات. */
    public function save_settings($actor, $lesson_id, $post)
    {
        $this->install_schema();

        $lesson_id = (int) $lesson_id;
        if (!$this->may_edit($actor, $lesson_id)) {
            return array('ok' => false, 'errors' => array('هذا الدرس ليس لك.'));
        }

        $quiz = $this->quiz_of($lesson_id, true);
        if (!$quiz) return array('ok' => false, 'errors' => array('تعذر إنشاء تقييم هذا الدرس.'));

        $n    = $this->count_questions($lesson_id);
        $pass = max(1, (int) $this->v($post, 'pass_mark', 3));

        /* حد نجاح أكبر من عدد الأسئلة لا يجتاز أبدا. يرفض هنا لا في
           الشاشة: قاعدة تكتب في قالب تنسى في القالب الثاني. */
        if ($n > 0 && $pass > $n) {
            return array('ok' => false, 'errors' => array(
                'حد النجاح ' . $pass . ' وأسئلة الاختبار ' . $n
                . ' — فلا يجتازه أحد، ويبقى الدرس التالي مقفلا على كل طالب.'));
        }

        $secs = max(0, (int) $this->v($post, 'time_limit_sec', 0));
        $tries = max(0, (int) $this->v($post, 'attempts_allowed', 0));

        $this->db->where('id', (int) $quiz['id'])->update('assessments', array(
            'pass_mark'        => $pass,
            'time_limit_sec'   => $secs > 0 ? $secs : null,
            'attempts_allowed' => $tries,
        ));

        $this->log($actor, 'quiz.settings', 'assessments:' . (int) $quiz['id'],
                   array('pass_mark' => $pass, 'time_limit_sec' => $secs));

        return array('ok' => true, 'message' => 'حفظت إعدادات الاختبار.');
    }

    /** يعيد ترتيب الأسئلة. */
    public function sort_questions($actor, $lesson_id, $ids)
    {
        if (!$this->may_edit($actor, $lesson_id)) {
            return array('ok' => false, 'errors' => array('هذا الدرس ليس لك.'));
        }
        $quiz = $this->quiz_of($lesson_id);
        if (!$quiz) return array('ok' => false, 'errors' => array('لا اختبار لهذا الدرس.'));

        $n = 0;
        foreach ((array) $ids as $i => $qid) {
            $qid = (int) $qid;
            if ($qid <= 0) continue;
            $this->db->where('id', $qid)->where('assessment_id', (int) $quiz['id'])
                     ->update('question', array('order' => $i + 1));
            $n += $this->db->affected_rows();
        }
        return array('ok' => true, 'message' => 'رتب ' . $n . ' سؤالا.');
    }

    /* =====================================================================
       النتائج — يقرؤها الطالب وولي أمره والمعلم والإدارة
       ===================================================================== */

    /**
     * محاولات اختبار درس، ومعها أسماء أصحابها.
     *
     * تقرأ ولا تحرر: النتيجة فعل الطالب، وتحريرها من اللوحة يجعل الكشف
     * شيئا آخر غير ما جرى.
     */
    public function attempts_of_lesson($lesson_id, $limit = 300)
    {
        $this->install_schema();

        $quiz = $this->quiz_of($lesson_id);
        if (!$quiz) return array();

        try {
            return $this->db->query(
                'SELECT a.`id`, a.`student_id`, a.`score`, a.`passed`, a.`attempt_no`,
                        a.`started_at`, a.`submitted_at`,
                        u.`first_name`, u.`last_name`, u.`email`
                   FROM `attempts` a
                   LEFT JOIN `users` u ON u.`id` = a.`student_id`
                  WHERE a.`assessment_id` = ? AND a.`submitted_at` IS NOT NULL
                  ORDER BY a.`submitted_at` DESC
                  LIMIT ' . (int) $limit,
                array((int) $quiz['id'])
            )->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-QUIZ attempts_of_lesson: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * نتائج طالب في اختبارات دروسه — لشاشته وشاشة ولي أمره.
     *
     * آخر محاولة لكل اختبار لا كل المحاولات: السؤال «أين هو الآن؟» لا
     * «كم مرة حاول؟». وعدد المحاولات يخرج معها في العمود نفسه.
     */
    public function student_results($student_id, $course_id = 0, $limit = 200)
    {
        $this->install_schema();

        $student_id = (int) $student_id;
        if ($student_id <= 0) return array();

        $args  = array($student_id);
        $where = '';
        if ((int) $course_id > 0) { $where = ' AND l.`course_id` = ?'; $args[] = (int) $course_id; }

        try {
            return $this->db->query(
                'SELECT a.`id`, a.`score`, a.`passed`, a.`attempt_no`, a.`submitted_at`,
                        s.`id` AS assessment_id, s.`pass_mark`,
                        l.`id` AS lesson_id, l.`title` AS lesson_title, l.`course_id`,
                        c.`title` AS course_title,
                        (SELECT COUNT(*) FROM `question` q WHERE q.`assessment_id` = s.`id`) AS total,
                        (SELECT COUNT(*) FROM `attempts` t
                          WHERE t.`assessment_id` = s.`id` AND t.`student_id` = a.`student_id`
                            AND t.`submitted_at` IS NOT NULL) AS tries
                   FROM `attempts` a
                   JOIN `assessments` s ON s.`id` = a.`assessment_id`
                   JOIN `lesson`      l ON l.`id` = s.`lesson_id`
                   LEFT JOIN `course` c ON c.`id` = l.`course_id`
                  WHERE a.`student_id` = ? AND a.`submitted_at` IS NOT NULL
                    AND s.`type` = "review"' . $where . '
                    AND a.`id` = (SELECT MAX(t2.`id`) FROM `attempts` t2
                                   WHERE t2.`assessment_id` = a.`assessment_id`
                                     AND t2.`student_id` = a.`student_id`
                                     AND t2.`submitted_at` IS NOT NULL)
                  ORDER BY a.`submitted_at` DESC
                  LIMIT ' . (int) $limit,
                $args
            )->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-QUIZ student_results: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * ملخص اختبار درس عبر طلابه — للمعلم وللإدارة.
     *
     * والسؤال الذي تجيبه: **أي سؤال يسقط فيه أكثر الطلاب؟** لأن ذلك يقرأ
     * عن الشرح لا عن الطلاب: سؤال يخطئه ثمانون بالمئة إما أن صياغته
     * ملتبسة وإما أن مفهومه لم يشرح.
     */
    public function question_stats($lesson_id)
    {
        $this->install_schema();

        $quiz = $this->quiz_of($lesson_id);
        if (!$quiz) return array();

        try {
            return $this->db->query(
                'SELECT q.`id`, q.`title`, q.`order`,
                        COUNT(n.`id`)                              AS answered,
                        SUM(CASE WHEN n.`is_correct` = 1 THEN 1 ELSE 0 END) AS correct
                   FROM `question` q
                   LEFT JOIN `answers` n ON n.`question_id` = q.`id`
                  WHERE q.`assessment_id` = ?
                  GROUP BY q.`id`, q.`title`, q.`order`
                  ORDER BY q.`order` ASC, q.`id` ASC',
                array((int) $quiz['id'])
            )->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-QUIZ question_stats: ' . $e->getMessage());
            return array();
        }
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    /** الملكية — ترد إلى طبقة المنهج، فلا يكتب هنا شرط ثان يفترق عنها. */
    private function may_edit($actor, $lesson_id)
    {
        $this->load->model('taqdar_curriculum_model', 'tq_curric');
        return $this->tq_curric->may_edit_lesson($actor, $lesson_id);
    }

    private function v($arr, $key, $default = '')
    {
        return (is_array($arr) && array_key_exists($key, $arr) && $arr[$key] !== null)
            ? $arr[$key] : $default;
    }

    private function setting($key, $default = null)
    {
        $v = get_settings($key);
        return ($v === null || $v === '') ? $default : $v;
    }

    /** حد النجاح: من التقييم، وإلا من الإعداد العام. */
    public function pass_mark($assessment)
    {
        if (is_array($assessment) && $assessment['pass_mark'] !== null
            && (int) $assessment['pass_mark'] > 0) {
            return (int) $assessment['pass_mark'];
        }
        return (int) $this->setting('mastery_pass_mark', 3);
    }

    private function log($actor, $action, $entity, $after = null)
    {
        try {
            $this->db->insert('audit_log', array(
                'actor_id' => (int) (isset($actor['id']) ? $actor['id'] : 0),
                'action'   => $action,
                'entity'   => $entity,
                'after'    => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
                'ip'       => $this->input->ip_address(),
                'at'       => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            log_message('error', 'TQ-QUIZ audit: ' . $e->getMessage());
        }
    }
}
