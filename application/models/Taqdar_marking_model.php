<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * تصحيح المعلم واعتماد الدرجة.
 *
 * القاعدة الحاكمة: المعرّف القادم من النموذج بيانٌ لا صلاحية. كل دالّة هنا
 * تشتقّ نطاق المعلم من القاعدة نفسها — `course.creator` أو `course.user_id` —
 * ثمّ تربط المحاولة به عبر `lesson` و`course`، فما لم يُسنَد إليه لا يُقرأ
 * ولا يُكتب مهما كان في الرابط أو في الحقل المخفي. والحفظ يمرّ بالتحقّق
 * نفسه مرّتين: مرّة عند فتح الشاشة، ومرّة — وهي التي تحمي — عند الاعتماد.
 *
 * والتصحيح الآلي مساعِد لا بديل: `total_obtained_marks` اقتراح يحسبه السكربت
 * من الأسئلة الموضوعية وحدها، و`teacher_score` هي الدرجة التي اعتمدها إنسان.
 * ولذلك درجة الاختبار الذي فيه سؤال مقالي لا تُعرض على الطالب قبل الاعتماد —
 * وهذا ما تقرّره `student_view()` أدناه في موضع واحد يستعمله كل عارض.
 *
 * أعمدة الاعتماد في `quiz_results`:
 *   teacher_score · teacher_note · approved_at · approved_by
 */
class Taqdar_marking_model extends CI_Model
{
    /** أنواع الأسئلة التي يصحّحها السكربت آليًّا (User::quiz_answer_submit). */
    private $auto_types = array('multiple_choice', 'single_choice', 'fill_in_the_blank');

    /** عتبة النجاح حين لا يضبطها الإعداد — نسبة مئوية. */
    const PASS_PERCENT_DEFAULT = 60;

    /** إعداد العتبة في جدول `settings`. */
    const PASS_PERCENT_KEY = 'marking_pass_percent';

    /** أطول ملاحظة تُحفظ — الحقل نصّ، والحدّ يمنع الإغراق لا التعبير. */
    const NOTE_MAX = 2000;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /* ================================================================
     *  الإعدادات
     * ================================================================ */

    /**
     * عتبة النجاح نسبةً مئوية. تُقرأ من `settings` لا من الشيفرة، وقيمتها
     * الافتراضية 60 حتى يُضبط المفتاح — فلا يتغيّر سلوك اليوم بلا قرار.
     */
    public function pass_percent()
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $val = null;
        if ($this->db->table_exists('settings')) {
            $row = $this->db->select('value')
                            ->where('key', self::PASS_PERCENT_KEY)
                            ->get('settings')->row_array();
            if ($row && $row['value'] !== null && trim((string) $row['value']) !== '') {
                $val = (int) $row['value'];
            }
        }
        if ($val === null || $val < 1 || $val > 100) {
            $val = self::PASS_PERCENT_DEFAULT;
        }
        return $cached = $val;
    }

    /**
     * حالة الإتقان مشتقّة من الدرجة والعتبة — لا تُخزَّن ولا تُسأل عن المعلم
     * مرّتين. موضعها النهائي جدول `objectives` (لكل هدف عتبته)، وحتى يوجد
     * فالعتبة إعداد واحد لا رقم مبعثر في الشاشات.
     */
    public function mastery($score, $total)
    {
        $total = max(1, (int) $total);
        $pct   = (float) $score * 100 / $total;
        $pass  = $this->pass_percent();

        if ($pct >= $pass)            return array('key' => 'mastered', 'label' => 'أتقن الهدف',            'percent' => (int) round($pct));
        if ($pct >= $pass * 0.75)     return array('key' => 'progress', 'label' => 'يحتاج مراجعة',          'percent' => (int) round($pct));
        return                               array('key' => 'late',     'label' => 'لم يُتقَن — يُعاد الدرس', 'percent' => (int) round($pct));
    }

    /* ================================================================
     *  النطاق: ما يملكه المعلم
     * ================================================================ */

    /** معرّفات كورسات المعلم — منشئًا أو مُسنَدًا إليها. */
    public function course_ids($teacher_id)
    {
        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return array();

        $rows = $this->db->query(
            "SELECT id FROM course WHERE creator = ? OR FIND_IN_SET(?, user_id) > 0",
            array($teacher_id, $teacher_id)
        )->result_array();

        return array_map('intval', array_column($rows, 'id'));
    }

    /** جذع الاستعلام — الأعمدة نفسها لكل قارئ فلا تتباعد الشاشات. */
    private function select_sql()
    {
        return "SELECT r.quiz_result_id, r.user_id, r.quiz_id, r.total_obtained_marks,
                       r.date_added, r.date_updated, r.is_submitted,
                       r.teacher_score, r.teacher_note, r.approved_at, r.approved_by,
                       u.first_name, u.last_name, u.image,
                       l.title AS quiz_title, l.course_id,
                       c.title AS course_title,
                       (SELECT COUNT(*) FROM question q WHERE q.quiz_id = r.quiz_id) AS total_marks
                  FROM quiz_results r
                  JOIN lesson l ON l.id = r.quiz_id
                  JOIN course c ON c.id = l.course_id
                  JOIN users  u ON u.id = r.user_id";
    }

    /**
     * صفّ التصحيح: المُسلَّم الذي لم يُعتمد بعد، الأقدم أوّلًا —
     * الطالب الذي انتظر أطول يُصحَّح أوّلًا.
     */
    public function queue($teacher_id, $limit = 50)
    {
        $ids = $this->course_ids($teacher_id);
        if (!$ids) return array();

        $in    = implode(',', $ids);
        $limit = max(1, (int) $limit);

        return $this->db->query(
            $this->select_sql() .
            " WHERE r.is_submitted = 1 AND r.approved_at IS NULL AND c.id IN ($in)
               ORDER BY r.date_added ASC
               LIMIT $limit"
        )->result_array();
    }

    /** آخر ما اعتمده المعلم — ليرى أثر عمله لا صفًّا يفرغ بلا خبر. */
    public function approved_recent($teacher_id, $limit = 8)
    {
        $ids = $this->course_ids($teacher_id);
        if (!$ids) return array();

        $in    = implode(',', $ids);
        $limit = max(1, (int) $limit);

        return $this->db->query(
            $this->select_sql() .
            " WHERE r.is_submitted = 1 AND r.approved_at IS NOT NULL AND c.id IN ($in)
               ORDER BY r.approved_at DESC
               LIMIT $limit"
        )->result_array();
    }

    /**
     * محاولة بعينها — مقيَّدة بنطاق المعلم. تعود null إن لم تكن في كورساته،
     * ولا فرق عندها بين «غير موجودة» و«ليست لك»: كلاهما لا يُعرض.
     */
    public function attempt($result_id, $teacher_id)
    {
        $result_id = (int) $result_id;
        if ($result_id <= 0) return null;

        $ids = $this->course_ids($teacher_id);
        if (!$ids) return null;

        $in = implode(',', $ids);

        return $this->db->query(
            $this->select_sql() .
            " WHERE r.quiz_result_id = ? AND c.id IN ($in) LIMIT 1",
            array($result_id)
        )->row_array();
    }

    /* ================================================================
     *  الأسئلة المقالية
     * ================================================================ */

    /**
     * عدد أسئلة الاختبار التي لا يصحّحها السكربت — المقالي وما شابهه.
     * وجود واحد منها يعني أن الاقتراح الآلي ناقص بالضرورة.
     */
    public function manual_questions($quiz_id)
    {
        $quiz_id = (int) $quiz_id;
        if ($quiz_id <= 0) return 0;

        $escaped = array();
        foreach ($this->auto_types as $t) {
            $escaped[] = $this->db->escape($t);
        }
        $list = implode(',', $escaped);

        $row = $this->db->query(
            "SELECT COUNT(*) AS n FROM question
              WHERE quiz_id = ? AND (type IS NULL OR type = '' OR type NOT IN ($list))",
            array($quiz_id)
        )->row_array();

        return (int) ($row ? $row['n'] : 0);
    }

    /** هل في الاختبار سؤال مقالي؟ */
    public function has_manual_questions($quiz_id)
    {
        return $this->manual_questions($quiz_id) > 0;
    }

    /* ================================================================
     *  ما يراه الطالب
     * ================================================================ */

    /**
     * الحاجز المعلن في واجهة المعلم، منفَّذًا في موضع واحد:
     * درجة الاختبار الذي فيه سؤال مقالي **لا تُعرض للطالب قبل الاعتماد**.
     *
     * @param  array $row صفّ من `quiz_results` (يكفي منه quiz_id و
     *                    total_obtained_marks و teacher_score و approved_at)
     * @return array visible: هل تُعرض درجة؟ · score: الدرجة المعروضة أو null
     *               state: approved | pending_approval | auto | unsubmitted
     *               note: ملاحظة المعلم بعد الاعتماد وحده
     */
    public function student_view($row)
    {
        if (!is_array($row)) {
            return array('visible' => false, 'score' => null, 'state' => 'unsubmitted', 'note' => '');
        }

        if (isset($row['is_submitted']) && (int) $row['is_submitted'] !== 1) {
            return array('visible' => false, 'score' => null, 'state' => 'unsubmitted', 'note' => '');
        }

        if (!empty($row['approved_at'])) {
            $score = ($row['teacher_score'] === null || $row['teacher_score'] === '')
                ? (float) $row['total_obtained_marks']
                : (float) $row['teacher_score'];
            return array(
                'visible' => true,
                'score'   => $score,
                'state'   => 'approved',
                'note'    => (string) (isset($row['teacher_note']) ? $row['teacher_note'] : ''),
            );
        }

        if ($this->has_manual_questions(isset($row['quiz_id']) ? $row['quiz_id'] : 0)) {
            return array('visible' => false, 'score' => null, 'state' => 'pending_approval', 'note' => '');
        }

        /* اختبار موضوعي بالكامل: الآلة تكفيه، فلا يُحجب عن صاحبه بلا سبب. */
        return array(
            'visible' => true,
            'score'   => (float) $row['total_obtained_marks'],
            'state'   => 'auto',
            'note'    => '',
        );
    }

    /**
     * مثلها بمعرّف المحاولة ومعرّف الطالب — والملكية تُتحقّق: لا يقرأ أحد
     * نتيجة غيره بتغيير رقم في الرابط.
     */
    public function student_view_by_id($result_id, $student_id)
    {
        $result_id  = (int) $result_id;
        $student_id = (int) $student_id;
        if ($result_id <= 0 || $student_id <= 0) {
            return $this->student_view(null);
        }
        $row = $this->db->query(
            "SELECT quiz_id, total_obtained_marks, is_submitted,
                    teacher_score, teacher_note, approved_at
               FROM quiz_results WHERE quiz_result_id = ? AND user_id = ? LIMIT 1",
            array($result_id, $student_id)
        )->row_array();

        return $this->student_view($row);
    }

    /* ================================================================
     *  الاعتماد
     * ================================================================ */

    /**
     * العقد الموحّد للفشل: `message` للعرض و`errors` لأن متحكّم الكتابة
     * يقرأ منها — ولو غابت لعرض «تعذّر تنفيذ الطلب» بدل السبب الحقيقي.
     */
    private function fail($message)
    {
        return array(
            'ok'      => false,
            'message' => $message,
            'errors'  => array($message),
            'attempt' => null,
        );
    }

    /**
     * مِطواة المتحكّم: `Taqdar::marking_approve()` يفوّض بنداء واحد
     * `(معرّف المعلم، حمولة)` — فيُستقبل هنا ويُفكّ إلى وسائط `approve()`.
     * الحمولة بيان لا صلاحية: لا يُؤخذ منها معرّف طالب ولا اختبار، لأن
     * الملكية تُعاد قراءتها من القاعدة بمعرّف المحاولة وحده.
     */
    public function approve_marking($teacher_id, $payload = array())
    {
        $payload = (array) $payload;

        return $this->approve(
            isset($payload['result_id']) ? $payload['result_id'] : 0,
            $teacher_id,
            isset($payload['score']) ? $payload['score'] : null,
            isset($payload['note'])  ? $payload['note']  : ''
        );
    }

    /** الاسم الآخر الذي قد يناديه المتحكّم — سلوك واحد لا سلوكان. */
    public function marking_approve($teacher_id, $payload = array())
    {
        return $this->approve_marking($teacher_id, $payload);
    }

    /**
     * اعتماد الدرجة. لا يُصدَّق من النموذج إلا معرّف المحاولة والدرجة
     * والملاحظة — والملكية تُقرأ من القاعدة قبل أي كتابة.
     *
     * @return array ok · message · attempt (الصفّ بعد الحفظ)
     */
    public function approve($result_id, $teacher_id, $score, $note = '')
    {
        $result_id  = (int) $result_id;
        $teacher_id = (int) $teacher_id;

        if ($result_id <= 0) {
            return $this->fail('لم تُحدَّد المحاولة المطلوب اعتمادها.');
        }
        if ($teacher_id <= 0) {
            return $this->fail('الجلسة انتهت — سجّل دخولك ثمّ أعد الاعتماد.');
        }

        /* التحقّق الحاسم: المحاولة تُقرأ مقيَّدة بكورسات هذا المعلم وحده. */
        $row = $this->attempt($result_id, $teacher_id);
        if (!$row) {
            return $this->fail('هذه المحاولة ليست في كورساتك، فلا تُعتمد منك.');
        }
        if ((int) $row['is_submitted'] !== 1) {
            return $this->fail('المحاولة لم تُسلَّم بعد، فلا درجة نهائية لها.');
        }

        $total = max(1, (int) $row['total_marks']);

        if ($score === null || $score === '' || !is_numeric($score)) {
            return $this->fail('الدرجة مطلوبة رقمًا بين 0 و' . $total . '.');
        }
        $score = round((float) $score, 2);
        if ($score < 0 || $score > $total) {
            return $this->fail('الدرجة خارج المدى: من 0 إلى ' . $total . ' درجة.');
        }

        $note = trim(strip_tags((string) $note));
        if ($note !== '' && function_exists('mb_substr')) {
            $note = mb_substr($note, 0, self::NOTE_MAX, 'UTF-8');
        } elseif ($note !== '') {
            $note = substr($note, 0, self::NOTE_MAX);
        }

        $this->db->where('quiz_result_id', $result_id)->update('quiz_results', array(
            'teacher_score' => $score,
            'teacher_note'  => $note,
            'approved_at'   => time(),
            'approved_by'   => $teacher_id,
        ));

        /* الحفظ يُقرأ لا يُفترض. */
        $saved = $this->attempt($result_id, $teacher_id);
        if (!$saved || (int) $saved['approved_by'] !== $teacher_id || empty($saved['approved_at'])) {
            return $this->fail('تعذّر حفظ الاعتماد — أعد المحاولة.');
        }

        $mastery = $this->mastery($saved['teacher_score'], $saved['total_marks']);

        return array(
            'ok'      => true,
            'message' => 'اعتُمدت الدرجة ' . (float) $saved['teacher_score'] . ' من ' . (int) $saved['total_marks']
                       . ' — ' . $mastery['label'] . '. وصارت تظهر للطالب.',
            'attempt' => $saved,
        );
    }

    /**
     * سحب الاعتماد — يُعيد الدرجة إلى الحجب. تحتاجه شاشة الاعتماد حين
     * يعتمد المعلم بالخطأ، ولا معنى لحاجز لا يُعاد.
     */
    public function unapprove($result_id, $teacher_id)
    {
        $result_id  = (int) $result_id;
        $teacher_id = (int) $teacher_id;

        $row = $this->attempt($result_id, $teacher_id);
        if (!$row) {
            return $this->fail('هذه المحاولة ليست في كورساتك.');
        }

        $this->db->where('quiz_result_id', $result_id)->update('quiz_results', array(
            'approved_at' => null,
            'approved_by' => null,
        ));

        $saved = $this->attempt($result_id, $teacher_id);
        if (!$saved || !empty($saved['approved_at'])) {
            return $this->fail('تعذّر سحب الاعتماد — أعد المحاولة.');
        }

        return array('ok' => true, 'message' => 'سُحب الاعتماد، والدرجة محجوبة عن الطالب من جديد.', 'attempt' => $saved);
    }
}
