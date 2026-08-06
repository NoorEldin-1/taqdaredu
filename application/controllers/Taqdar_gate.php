<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * بوابة الإتقان — في الخادم حصرا.
 *
 * ما يفعله هذا المتحكم لا يعاد فعله في العميل: القفل هنا، والتصحيح هنا،
 * وقرار «أعد المحاولة» أو «اطلب حصة» هنا. القفل البصري في الواجهة تجميل،
 * فطلب درس غير مفتوح يرجع 403 بلا رابط تشغيل مهما جاء الطلب من أين.
 *
 * كل استجابة خطأ بمغلف موحد:
 *   { error: { code, message, message_ar, details } }
 * والعميل يعرض message_ar.
 *
 * المسارات (توجيه CodeIgniter الافتراضي — لا حاجة لتعديل routes.php):
 *   GET  /taqdar_gate/paths
 *   GET  /taqdar_gate/path/{id}
 *   GET  /taqdar_gate/lesson/{id}          ← 403 MASTERY_LOCKED إن كان مقفلا
 *   POST /taqdar_gate/progress             lesson_id, position_sec, watched_delta
 *   POST /taqdar_gate/review_start         lesson_id
 *   POST /taqdar_gate/review_submit        attempt_id, answers[]
 *   GET  /taqdar_gate/reviews
 *   POST /taqdar_gate/review_answer        question_id, given
 *   GET  /taqdar_gate/mistakes
 *   GET  /taqdar_gate/skill_map
 *   GET  /taqdar_gate/wallet
 *   GET  /taqdar_gate/children
 *   GET  /taqdar_gate/child/{student_id}
 *   GET  /taqdar_gate/teacher_scope
 */
class Taqdar_gate extends CI_Controller
{
    /** حدود المعدل: نقطة => [أقصى عدد, نافذة بالثواني] */
    private $limits = array(
        'progress'       => array(120, 60),
        'review_start'   => array(20,  60),
        'review_submit'  => array(30,  60),
        'review_answer'  => array(60,  60),
        'read'           => array(120, 60),
    );

    private $cache_ready = false;

    /** مستوى مخازن الإخراج قبل أن نفتح مخزننا — إليه نعود في respond(). */
    private $ob_base = 0;

    public function __construct()
    {
        parent::__construct();

        /* TQ-GATE-CLEAN — لا بايت واحد قبل الـ JSON.
           هذه واجهة بيانات: أي `echo` عارض — تنبيه PHP، أو ملف مساعد فيه
           سطر فارغ بعد `?>` — يجعل أول بايت في الرد `<` أو مسافة، فيسقط
           `JSON.parse` في العميل برسالة «Unexpected token» لا علاقة لها
           بالخطأ الحقيقي. والمخزن يبتلع ذلك ويرميه، ويسجل ما ابتلعه في
           سجل الأخطاء — فلا يضيع الأثر ولا يخرج مع الرد. */
        $this->ob_base = ob_get_level();
        ob_start();

        $this->load->database();
        $this->load->library('session');
        $this->load->model('taqdar_repo_model', 'repo');

        $tz = get_settings('timezone');
        if ($tz) date_default_timezone_set($tz);

        /* مخزن مؤقت لعداد المعدل — إن تعذر، يظل الباقي عاملا.
           و`@` هنا لتقادم CI3 على PHP 8 داخل `Driver.php`: شيفرة طرف ثالث
           لا ترقع، وتنبيهها لا يخص من ينادي هذه النقطة. */
        try {
            @$this->load->driver('cache', array('adapter' => 'file', 'backup' => 'dummy'));
            $this->cache_ready = true;
        } catch (Exception $e) {
            $this->cache_ready = false;
        }

        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->output->set_header('Pragma: no-cache');
        $this->output->set_content_type('application/json', 'utf-8');
    }

    /* ================================================================
     *  أدوات الاستجابة
     * ================================================================ */

    private function respond($payload, $status = 200)
    {
        /* ما تسرب قبل الرد يرمى هنا ويسجل — انظر TQ-GATE-CLEAN أعلاه. */
        $stray = '';
        while (ob_get_level() > $this->ob_base) $stray .= (string) ob_get_clean();
        if (trim($stray) !== '') {
            log_message('error', 'TQ-GATE: إخراج عارض قبل JSON — ' . substr(trim($stray), 0, 400));
        }

        $this->output
             ->set_status_header($status)
             ->set_content_type('application/json', 'utf-8')
             ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return;
    }

    private function ok($data, $status = 200)
    {
        return $this->respond($data, $status);
    }

    /** مغلف الخطأ الموحد — مصدره الوحيد كتالوج النموذج. */
    private function fail($code, $details = array())
    {
        $body = $this->repo->error($code, $details);
        return $this->respond($body, $this->repo->http_status($code));
    }

    /** يمرر مغلف خطأ جاء من النموذج كما هو بحالته الصحيحة. */
    private function passthru_error($result)
    {
        $code = $result['error']['code'];
        return $this->respond($result, $this->repo->http_status($code));
    }

    /** جسم الطلب: JSON أو نموذج — أيهما جاء. */
    private function body($key = null, $default = null)
    {
        static $data = null;
        if ($data === null) {
            $data = array();
            $raw  = file_get_contents('php://input');
            if ($raw !== '' && $raw !== false) {
                $json = json_decode($raw, true);
                if (is_array($json)) $data = $json;
            }
            if (is_array($_POST)) $data = array_merge($data, $_POST);
            if (is_array($_GET))  $data = array_merge($_GET, $data);
        }
        if ($key === null) return $data;
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    private function user_id()
    {
        return (int) $this->session->userdata('user_id');
    }

    /** يشترط جلسة — وإلا 401 بمغلف لا بإعادة توجيه (هذه واجهة بيانات). */
    private function require_user()
    {
        $uid = $this->user_id();
        if (!$uid) {
            $this->fail('UNAUTHENTICATED');
            return 0;
        }
        return $uid;
    }

    /** عداد نافذة منزلقة بسيط — يرجع false حين يتجاوز الحد. */
    private function within_limit($bucket)
    {
        if (!$this->cache_ready) return true;
        $conf   = isset($this->limits[$bucket]) ? $this->limits[$bucket] : $this->limits['read'];
        $max    = $conf[0];
        $window = $conf[1];

        $key = 'tqgate_' . $bucket . '_' . $this->user_id() . '_' . (int) floor(time() / $window);
        $n   = (int) $this->cache->get($key);
        $n++;
        $this->cache->save($key, $n, $window * 2);

        return $n <= $max;
    }

    private function guard($bucket)
    {
        $uid = $this->require_user();
        if (!$uid) return 0;
        if (!$this->within_limit($bucket)) {
            $conf = isset($this->limits[$bucket]) ? $this->limits[$bucket] : $this->limits['read'];
            $this->fail('RATE_LIMITED', array('limit' => $conf[0], 'window_sec' => $conf[1]));
            return 0;
        }
        return $uid;
    }

    private function require_post()
    {
        if (strtoupper($this->input->server('REQUEST_METHOD')) !== 'POST') {
            $this->fail('VALIDATION', array('reason' => 'method_not_allowed', 'expected' => 'POST'));
            return false;
        }
        return true;
    }

    /* ================================================================
     *  المسارات والدروس
     * ================================================================ */

    public function index()
    {
        return $this->ok(array(
            'service' => 'taqdar-gate',
            'version' => 'v1-transitional',
            'ok'      => true,
        ));
    }

    public function paths()
    {
        $uid = $this->guard('read');
        if (!$uid) return;
        return $this->ok(array('paths' => $this->repo->get_paths($uid)));
    }

    public function path($id = 0)
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $result = $this->repo->get_path((int) $id, $uid);
        if ($this->repo->is_error($result)) return $this->passthru_error($result);

        // المسار غير المنشور لا يعرض إلا لصاحبه أو للإدارة
        if ($result['status'] !== 'published' && !$this->may_see_draft($uid, $result)) {
            return $this->fail('NOT_ENTITLED', array('entity' => 'paths:' . (int) $id));
        }
        return $this->ok($result);
    }

    private function may_see_draft($uid, $path)
    {
        if ((int) $path['teacher_id'] === (int) $uid) return true;
        $u = $this->db->select('role_id')->where('id', $uid)->get('users')->row_array();
        return $u && (int) $u['role_id'] === 1;
    }

    /**
     * الدرس. هنا يظهر أثر البوابة بأوضح صوره:
     * الدرس المقفل يرجع 403 ومعه MASTERY_LOCKED، ولا يحمل رده أي رابط تشغيل.
     */
    public function lesson($id = 0)
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $result = $this->repo->get_lesson((int) $id, $uid);
        if ($this->repo->is_error($result)) return $this->passthru_error($result);

        return $this->ok($result);
    }

    public function progress()
    {
        if (!$this->require_post()) return;
        $uid = $this->guard('progress');
        if (!$uid) return;

        $lesson_id = (int) $this->body('lesson_id', 0);
        if (!$lesson_id) return $this->fail('VALIDATION', array('field' => 'lesson_id'));

        $result = $this->repo->save_progress(
            $uid,
            $lesson_id,
            (int) $this->body('position_sec', 0),
            (int) $this->body('watched_delta', 0)
        );
        if ($this->repo->is_error($result)) return $this->passthru_error($result);

        return $this->ok($result);
    }

    /* ================================================================
     *  بوابة الإتقان — بدء المحاولة وتسليمها
     * ================================================================ */

    public function review_start()
    {
        if (!$this->require_post()) return;
        $uid = $this->guard('review_start');
        if (!$uid) return;

        $lesson_id = (int) $this->body('lesson_id', 0);
        if (!$lesson_id) return $this->fail('VALIDATION', array('field' => 'lesson_id'));

        $result = $this->repo->start_attempt($uid, $lesson_id);
        if ($this->repo->is_error($result)) return $this->passthru_error($result);

        return $this->ok($result);
    }

    /**
     * تسليم المراجعة. الرد يحمل النتيجة وقرار البوابة فقط — ولا يحمل
     * الإجابات الصحيحة، فالتلميح بالحل يفسد قياس الإتقان.
     */
    public function review_submit()
    {
        if (!$this->require_post()) return;
        $uid = $this->guard('review_submit');
        if (!$uid) return;

        $attempt_id = (int) $this->body('attempt_id', 0);
        if (!$attempt_id) return $this->fail('VALIDATION', array('field' => 'attempt_id'));

        $answers = $this->body('answers', array());
        if (is_string($answers)) {
            $decoded = json_decode($answers, true);
            $answers = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($answers)) $answers = array();

        $result = $this->repo->submit_attempt($uid, $attempt_id, $answers);
        if ($this->repo->is_error($result)) return $this->passthru_error($result);

        return $this->ok($result);
    }

    /**
     * مراجعة إجابات محاولة **مسلمة**.
     *
     * منفصلة عن `review_submit` عمدا: رد التسليم يبقى بلا إجابات
     * صحيحة (التلميح أثناء الاختبار يفسد قياسه)، والمراجعة طلب ثان
     * لا يقبل إلا بعد التسليم. والحارس بالطالب: رقم محاولة في الجسم
     * لا يكفي، وإلا قرأ كل طالب إجابات غيره.
     */
    public function review_answers()
    {
        $uid = $this->guard('review_answers');
        if (!$uid) return;

        $attempt_id = (int) $this->body('attempt_id', 0);
        if (!$attempt_id) $attempt_id = (int) $this->input->get('attempt_id');
        if (!$attempt_id) return $this->fail('VALIDATION', array('field' => 'attempt_id'));

        $result = $this->repo->attempt_review($uid, $attempt_id);
        if ($this->repo->is_error($result)) return $this->passthru_error($result);

        return $this->ok($result);
    }

    /* ================================================================
     *  المراجعة المتباعدة ودفتر الأخطاء
     * ================================================================ */

    public function reviews()
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $limit = (int) $this->body('limit', 0);
        $rows  = $this->repo->get_due_reviews($uid, $limit);

        return $this->ok(array(
            'due'         => $rows,
            'count'       => count($rows),
            'total_due'   => $this->repo->count_due_reviews($uid),
            'daily_batch' => (int) $this->repo->setting('review_daily_batch', 10),
        ));
    }

    /**
     * إجابة سؤال مراجعة. الصواب يقرره الخادم من correct_answers، ولا
     * يقبل من العميل بحال — وإلا صار الجدول الزمني لعبة.
     */
    public function review_answer()
    {
        if (!$this->require_post()) return;
        $uid = $this->guard('review_answer');
        if (!$uid) return;

        $question_id = (int) $this->body('question_id', 0);
        if (!$question_id) return $this->fail('VALIDATION', array('field' => 'question_id'));

        $in_queue = $this->db->where('student_id', $uid)->where('question_id', $question_id)
                             ->count_all_results('review_queue');
        if (!$in_queue) {
            return $this->fail('NOT_ENTITLED', array('reason' => 'question_not_in_your_queue'));
        }

        $q = $this->db->where('id', $question_id)->get('question')->row_array();
        if (!$q) return $this->fail('NOT_FOUND', array('entity' => 'question:' . $question_id));

        $given = $this->body('given', array());
        if (is_string($given)) {
            $decoded = json_decode($given, true);
            $given   = is_array($decoded) ? $decoded : array($given);
        }
        if (!is_array($given)) $given = ($given === null) ? array() : array($given);

        $correct = $this->repo->is_answer_correct($q, $given);
        $result  = $this->repo->answer_review($uid, $question_id, $correct);

        $this->repo->audit($uid, 'review.answer', 'question:' . $question_id, null, array(
            'correct'       => (bool) $correct,
            'interval_days' => $result['interval_days'],
        ));

        return $this->ok($result);
    }

    public function mistakes()
    {
        $uid = $this->guard('read');
        if (!$uid) return;
        $rows = $this->repo->get_mistakes($uid);
        return $this->ok(array('mistakes' => $rows, 'count' => count($rows)));
    }

    public function skill_map()
    {
        $uid = $this->guard('read');
        if (!$uid) return;
        return $this->ok($this->repo->get_skill_map($uid));
    }

    /* ================================================================
     *  المحفظة وولي الأمر والمعلم
     * ================================================================ */

    public function wallet()
    {
        $uid = $this->guard('read');
        if (!$uid) return;
        return $this->ok($this->repo->get_wallet($uid));
    }

    public function children()
    {
        $uid = $this->guard('read');
        if (!$uid) return;
        $rows = $this->repo->get_children($uid);
        return $this->ok(array('children' => $rows, 'count' => count($rows)));
    }

    /** تقرير ابن — لا يفتح إلا لولي أمر مرتبط بموافقة نشطة. */
    public function child($student_id = 0)
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $student_id = (int) $student_id;
        $linked = $this->db->where('parent_user_id', $uid)
                           ->where('student_id', $student_id)
                           ->where('status', 'active')
                           ->count_all_results('parent_links');
        if (!$linked) {
            return $this->fail('NOT_ENTITLED', array('reason' => 'not_your_child'));
        }

        return $this->ok(array(
            'student_id' => $student_id,
            'paths'      => $this->repo->get_paths($student_id),
            'skill_map'  => $this->repo->get_skill_map($student_id),
            'mistakes'   => $this->repo->get_mistakes($student_id),
            'due'        => $this->repo->count_due_reviews($student_id),
        ));
    }

    public function teacher_scope()
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $u = $this->db->select('is_instructor, role_id')->where('id', $uid)->get('users')->row_array();
        if (!$u || (empty($u['is_instructor']) && (int) $u['role_id'] !== 1)) {
            return $this->fail('OUT_OF_ASSIGNMENT', array('reason' => 'not_a_teacher'));
        }

        return $this->ok($this->repo->get_teacher_scope($uid));
    }
}
