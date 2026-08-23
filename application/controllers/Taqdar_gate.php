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
 *   GET  /taqdar_gate/next_step            الخطوة الواحدة + السلسلة + الهدف
 *   GET  /taqdar_gate/activity             خريطة أيام النشاط
 *   GET  /taqdar_gate/notes/{lesson_id}
 *   POST /taqdar_gate/note_add             lesson_id, at_second, body
 *   POST /taqdar_gate/note_delete          note_id, lesson_id
 *   GET  /taqdar_gate/transcript/{lesson_id}
 *   GET  /taqdar_gate/practice_questions   أسئلة دفتر الأخطاء
 *   POST /taqdar_gate/practice_answer      question_id, given
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

    /**
     * كتابة: POST، ومعها رمز مضاد للتزوير يقرأ من الترويسة.
     *
     * TQ-GATE-CSRF — **كل POST إلى هذه البوابة كان يرد 403.**
     *
     * `CI_Security::csrf_verify()` يبحث عن الرمز في `$_POST`. وجسم هذه
     * النقاط `application/json`، فـ`$_POST` فارغ دائما — فيسقط الفحص
     * **قبل أن يبلغ المتحكم**، ويرد CodeIgniter صفحة HTML بـ«The action
     * you have requested is not allowed». وأثر ذلك أن بوابة الإتقان كلها
     * — تسجيل المشاهدة، وبدء المراجعة، وتسليمها، والملاحظات، وأجوبة
     * التمرين — لم تعمل في متصفح قط منذ فتح `csrf_protection`. وهو تفسير
     * «الطالب يشاهد الفيديو ولا يسجل له تقدم» من جهته الثانية: يوتيوب لا
     * يرسل حدثا، وحين يرسل غيره يرد الخادم 403.
     *
     * والعلاج ليس إسقاط الحماية: هذه البوابة تستوثق **بكعكة الجلسة**،
     * فهي أحوج ما يكون إليها. وإنما فحص يقرأ JSON:
     * `csrf_exclude_uris` يستثنيها من فحص CodeIgniter الذي لا يقرأ
     * الجسم، وتفحص هي الرمز نفسه من ترويسة `X-CSRF-Token` مقابل هاش هذه
     * الجلسة — نمط الإرسال المزدوج. فصفحة من نطاق آخر تستطيع أن تجعل
     * المتصفح يرسل الكعكة، ولا تستطيع أن تقرأ الرمز لتضعه في الترويسة.
     */
    private function require_post()
    {
        if (strtoupper($this->input->server('REQUEST_METHOD')) !== 'POST') {
            $this->fail('VALIDATION', array('reason' => 'method_not_allowed', 'expected' => 'POST'));
            return false;
        }
        if (!$this->csrf_ok()) {
            $this->fail('CSRF', array('reason' => 'bad_or_missing_token'));
            return false;
        }
        return true;
    }

    /**
     * هل تحمل الترويسة رمز هذه الجلسة؟
     *
     * `hash_equals` لا `===`: المقارنة الحرفية تسرب طول المطابقة بزمنها.
     * والرمز يقبل من الترويسة أو من الجسم — عميل يرسل نموذجا عاديا
     * (لا JSON) يضعه حقلا، ومنع ذلك يجعل النقطة تخدم عميلا واحدا.
     */
    private function csrf_ok()
    {
        $name = (string) $this->config->item('csrf_token_name');
        $hash = (string) $this->security->get_csrf_hash();
        if ($hash === '') return true;   // الحماية مطفأة في هذا التركيب

        $sent = (string) $this->input->get_request_header('X-CSRF-Token', true);
        if ($sent === '') $sent = (string) $this->input->post($name);
        if ($sent === '') {
            $body = $this->body($name);
            if (is_scalar($body)) $sent = (string) $body;
        }

        return $sent !== '' && hash_equals($hash, $sent);
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

        /* TQ-COVERAGE · الدلاء التي مر عليها التشغيل منذ آخر نبضة.
           والحد مئة في النبضة: النبضة كل خمس عشرة ثانية، أي دلوان في
           التشغيل العادي وعشرة في السرعة القصوى — ومئة سعة كافية لأبطأ
           شبكة، وسد أمام حمولة تدعي تغطية درس كامل في نداء. */
        $covered = $this->body('covered', array());
        if (!is_array($covered)) $covered = array();
        $covered = array_slice($covered, 0, 100);

        /* المدة يعلنها المشغل: يوتيوب وفيميو يعرفانها ولا يعرفها الخادم
           بلا مفتاح واجهة برمجة، وكل درس في القاعدة `00:00:00`. */
        $dur = (int) $this->body('duration_sec', 0);
        if ($dur > 0) $this->repo->record_duration($lesson_id, $dur);

        $result = $this->repo->save_progress(
            $uid,
            $lesson_id,
            (int) $this->body('position_sec', 0),
            (int) $this->body('watched_delta', 0),
            $covered
        );
        if ($this->repo->is_error($result)) return $this->passthru_error($result);

        return $this->ok($result);
    }

    /**
     * إتمام يعلنه الطالب — للمصادر التي لا تعلن موضع تشغيلها.
     *
     * درايف والإطار الخارجي لا يعطيان موضعا، فلا شيء يقاس. والبديل أن
     * يبقى الدرس التالي مقفلا إلى الأبد. والنموذج يرفض هذا الإقرار على
     * مصدر يقاس، فلا يصير مخرجا من كل درس على المنصة.
     */
    public function complete()
    {
        if (!$this->require_post()) return;
        $uid = $this->guard('progress');
        if (!$uid) return;

        $lesson_id = (int) $this->body('lesson_id', 0);
        if (!$lesson_id) return $this->fail('VALIDATION', array('field' => 'lesson_id'));

        $result = $this->repo->confirm_complete($uid, $lesson_id);
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

    /* ================================================================
     *  الوسائط — الرابط الموقع (B3.3)
     * ================================================================ */

    /**
     * يخدم مقطع درس برمز موقع.
     *
     * الرمز يحمل الدرس والطالب والانتهاء موقعة بـ HMAC، ويفحص هنا
     * ثلاثا: التوقيع، ثم صلاحية الوقت، ثم **القفل والاشتراك من جديد**.
     * والثالثة ليست تكرارا: بين إصدار الرمز واستعماله قد ينتهي اشتراك،
     * أو يسحب درس من النشر — ورمز مصدر قبل دقائق لا يفتح ما أغلق بعده.
     *
     * والملف يمرر مجزأ (`Range`) لا دفعة واحدة: المشغل يطلب أول مئة
     * كيلوبايت ليعرف مدة المقطع، فقراءة ملف من ثلاثمئة ميغابايت في
     * الذاكرة لأجل ذلك تسقط الطلب.
     *
     * ولا يمر من هذا الباب إلا ما تحت `uploads/` — والمسار يفحص بعد
     * `realpath` لا قبله: `../` في عمود قاعدة بيانات يخرج من الجذر،
     * والفحص النصي وحده يمر عليه.
     */
    public function media($token = '')
    {
        /* هذه ترد ملفا لا JSON: المخزن الذي فتحه الباني لا يصلح هنا،
           وترويسة `application/json` تفسد الرد. */
        while (ob_get_level() > $this->ob_base) ob_end_clean();

        $uid = $this->user_id();
        if (!$uid) { $this->output->set_status_header(401); return; }

        $this->load->model('taqdar_studio_model', 'studio');
        $lesson_id = (int) $this->studio->verify($token, $uid);

        if (!$lesson_id) {
            /* 403 لا 404: الرمز إما زور أو انتهى، وكلاهما «ممنوع» لا
               «غير موجود». والرسالة عربية لأن المشغل قد يعرضها. */
            $this->output->set_status_header(403);
            $this->output->set_content_type('text/plain', 'utf-8');
            $this->output->set_output('انتهت صلاحية رابط التشغيل. أعد تحميل الصفحة.');
            return;
        }

        if (!$this->repo->is_lesson_unlocked($uid, $lesson_id)) {
            $this->output->set_status_header(403);
            return;
        }

        $lesson = $this->db->select('video_url, course_id, is_free')
                           ->where('id', $lesson_id)->get('lesson')->row_array();
        if (!$lesson) { $this->output->set_status_header(404); return; }

        if ((int) $lesson['is_free'] !== 1
            && !$this->repo->is_entitled($uid, (int) $lesson['course_id'])) {
            $this->output->set_status_header(403);
            return;
        }

        $rel  = ltrim(str_replace('\\', '/', (string) $lesson['video_url']), '/');
        $base = realpath(FCPATH . 'uploads');
        $path = realpath(FCPATH . $rel);

        if (!$base || !$path || strpos($path, $base) !== 0 || !is_file($path)) {
            $this->output->set_status_header(404);
            return;
        }

        $this->stream($path);
    }

    /** يمرر ملفا مع دعم `Range` — ويخرج بعده مباشرة. */
    private function stream($path)
    {
        $size = filesize($path);
        $mime = 'video/mp4';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map  = array('webm' => 'video/webm', 'ogg' => 'video/ogg', 'ogv' => 'video/ogg',
                      'm4v' => 'video/mp4', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4');
        if (isset($map[$ext])) $mime = $map[$ext];

        $start = 0;
        $end   = $size - 1;
        $range = (string) $this->input->server('HTTP_RANGE');
        $partial = false;

        if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            if ($m[1] !== '') $start = (int) $m[1];
            if ($m[2] !== '') $end   = (int) $m[2];
            if ($start > $end || $start >= $size) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header('Content-Range: bytes */' . $size);
                exit;
            }
            $end = min($end, $size - 1);
            $partial = true;
        }

        $length = $end - $start + 1;

        header($partial ? 'HTTP/1.1 206 Partial Content' : 'HTTP/1.1 200 OK');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $length);
        header('Accept-Ranges: bytes');
        if ($partial) header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        /* لا تخزين: الرابط موقع لخمس دقائق، ووسيط يخزنه يعيده لغير
           صاحبه بعد انتهائه. */
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline');

        $fp = fopen($path, 'rb');
        if (!$fp) { $this->output->set_status_header(500); return; }

        fseek($fp, $start);
        $left = $length;
        while ($left > 0 && !feof($fp)) {
            $chunk = fread($fp, min(262144, $left));
            if ($chunk === false) break;
            echo $chunk;
            $left -= strlen($chunk);
            flush();
        }
        fclose($fp);
        exit;
    }

    /* ================================================================
     *  رحلة اليوم — الخطوة والسلسلة والهدف
     * ================================================================ */

    private function learn()
    {
        $this->load->model('taqdar_learn_model', 'learn');
        return $this->learn;
    }

    /**
     * الخطوة الواحدة المقترحة، ومعها ما ترسمه اللوحة حولها.
     *
     * نداء واحد لا ثلاثة: اللوحة تريد الخطوة والسلسلة والهدف معا، وثلاثة
     * أشواط شبكية لرسم بطاقة واحدة تجعلها تومض ثلاث مرات.
     */
    public function next_step()
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $learn = $this->learn();

        return $this->ok(array(
            'step'   => $learn->next_step($uid),
            'streak' => $learn->streak($uid),
            'goal'   => $learn->goal_today($uid),
            'exam'   => $learn->exam_mode($uid),
            'setup'  => array('done' => !$learn->needs_setup($uid)),
        ));
    }

    /** خريطة نشاط الأسابيع الأخيرة — يوما يوما. */
    public function activity()
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $days = (int) $this->body('days', 28);
        return $this->ok(array(
            'days'   => $this->learn()->activity_range($uid, $days),
            'streak' => $this->learn()->streak($uid),
        ));
    }

    /* ================================================================
     *  الملاحظات الموقوتة
     * ================================================================ */

    public function notes($lesson_id = 0)
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $lesson_id = (int) ($lesson_id ?: $this->body('lesson_id', 0));
        if (!$lesson_id) return $this->fail('VALIDATION', array('field' => 'lesson_id'));

        return $this->ok(array(
            'lesson_id' => $lesson_id,
            'notes'     => $this->learn()->notes($uid, $lesson_id),
        ));
    }

    /**
     * ملاحظة جديدة عند ثانية بعينها.
     *
     * والقفل يفحص قبل الكتابة: من لا يفتح له الدرس لا يكتب فيه ملاحظة —
     * وإلا صار هذا المسار بابا يثبت به وجود درس مقفل ومدته وموضعه.
     */
    public function note_add()
    {
        if (!$this->require_post()) return;
        $uid = $this->guard('review_answer');
        if (!$uid) return;

        $lesson_id = (int) $this->body('lesson_id', 0);
        if (!$lesson_id) return $this->fail('VALIDATION', array('field' => 'lesson_id'));

        if (!$this->repo->is_lesson_unlocked($uid, $lesson_id)) {
            return $this->fail('MASTERY_LOCKED', array('lesson_id' => $lesson_id));
        }

        $r = $this->learn()->add_note($uid, $lesson_id,
                                      (int) $this->body('at_second', 0),
                                      (string) $this->body('body', ''));

        if (empty($r['ok'])) return $this->fail('VALIDATION', array('reason' => $r['message']));

        return $this->ok(array(
            'saved'   => true,
            'message' => $r['message'],
            'notes'   => $this->learn()->notes($uid, $lesson_id),
        ));
    }

    public function note_delete()
    {
        if (!$this->require_post()) return;
        $uid = $this->guard('review_answer');
        if (!$uid) return;

        $r = $this->learn()->delete_note($uid, (int) $this->body('note_id', 0));
        $lesson_id = (int) $this->body('lesson_id', 0);

        return $this->ok(array(
            'deleted' => !empty($r['ok']),
            'message' => $r['message'],
            'notes'   => $lesson_id ? $this->learn()->notes($uid, $lesson_id) : array(),
        ));
    }

    /* ================================================================
     *  نص الدرس
     * ================================================================ */

    /** النص مقطعا مقطعا بثوانيه. القفل يفحص — النص محتوى الدرس لا فهرسه. */
    public function transcript($lesson_id = 0)
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $lesson_id = (int) ($lesson_id ?: $this->body('lesson_id', 0));
        if (!$lesson_id) return $this->fail('VALIDATION', array('field' => 'lesson_id'));

        if (!$this->repo->is_lesson_unlocked($uid, $lesson_id)) {
            return $this->fail('MASTERY_LOCKED', array('lesson_id' => $lesson_id));
        }

        $rows = $this->learn()->transcript($lesson_id);
        return $this->ok(array(
            'lesson_id' => $lesson_id,
            'cues'      => $rows,
            'count'     => count($rows),
        ));
    }

    /* ================================================================
     *  دفتر الأخطاء — وضع المراجعة المركزة
     * ================================================================ */

    /**
     * إجابة سؤال من دفتر الأخطاء.
     *
     * منفصلة عن `review_answer` لأن شرط الملكية مختلف: تلك تشترط أن يكون
     * السؤال في طابور المراجعة، وهذه تشترط أن يكون الطالب **أخطأه من قبل**
     * — والسؤالان يفترقان: كل خطأ ليس في الطابور بالضرورة، ومن أخطأ في
     * تقييم درس لم يتقنه لا يجدول له شيء أصلا.
     *
     * والصواب هنا يحرك حالة المهارة، ويباعد الموعد إن كان السؤال مجدولا —
     * فالتدريب المركز يحسب كما تحسب المراجعة، ولا يكون عملا بلا أثر.
     */
    public function practice_answer()
    {
        if (!$this->require_post()) return;
        $uid = $this->guard('review_answer');
        if (!$uid) return;

        $question_id = (int) $this->body('question_id', 0);
        if (!$question_id) return $this->fail('VALIDATION', array('field' => 'question_id'));

        $owns = (int) $this->db->query(
            'SELECT COUNT(*) n FROM `answers` a
               JOIN `attempts` t ON t.`id` = a.`attempt_id`
              WHERE t.`student_id` = ? AND a.`question_id` = ? AND a.`is_correct` = 0',
            array($uid, $question_id))->row('n');

        if (!$owns) {
            return $this->fail('NOT_ENTITLED', array('reason' => 'question_not_in_your_mistakes'));
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

        /* السؤال المجدول يمر بمحرك المراجعة كاملا (فاصل وسهولة وتعثر)،
           وغير المجدول يحرك حالة المهارة وحدها — فلا يخترع لنفسه جدولا
           لم تفتحه بوابة الإتقان. */
        $scheduled = (int) $this->db->where('student_id', $uid)
                                    ->where('question_id', $question_id)
                                    ->count_all_results('review_queue');

        if ($scheduled) {
            $result = $this->repo->answer_review($uid, $question_id, $correct);
        } else {
            if (!empty($q['objective_id'])) {
                $this->repo->touch_skill_state($uid, (int) $q['objective_id'], $correct ? 1 : 0, 1, null);
            }
            $result = array(
                'question_id'  => $question_id,
                'correct'      => (bool) $correct,
                'scheduled'    => false,
                'remaining_due'=> $this->repo->count_due_reviews($uid),
            );
        }

        $result['practice'] = true;
        $result['still_wrong_count'] = (int) $this->db->query(
            'SELECT COUNT(*) n FROM `answers` a
               JOIN `attempts` t ON t.`id` = a.`attempt_id`
              WHERE t.`student_id` = ? AND a.`question_id` = ? AND a.`is_correct` = 0',
            array($uid, $question_id))->row('n');

        $this->repo->audit($uid, 'mistake.practice', 'question:' . $question_id, null,
                           array('correct' => (bool) $correct, 'scheduled' => (bool) $scheduled));

        return $this->ok($result);
    }

    /**
     * أسئلة دفتر الأخطاء بخياراتها — للتدريب.
     * بلا `correct_answers` أبدا، كما في كل مسار أسئلة في هذا المتحكم.
     */
    public function practice_questions()
    {
        $uid = $this->guard('read');
        if (!$uid) return;

        $limit = max(1, min(50, (int) $this->body('limit', 10)));
        $subject = (int) $this->body('subject_id', 0);

        $sql = 'SELECT DISTINCT q.`id`, q.`title`, q.`type`, q.`number_of_options`, q.`options`,
                       q.`objective_id`, o.`text` AS objective_text, o.`at_second`, o.`lesson_id`,
                       l.`title` AS lesson_title, l.`course_id`, c.`title` AS course_title,
                       COUNT(*) AS wrong_count
                  FROM `answers` a
                  JOIN `attempts` t   ON t.`id` = a.`attempt_id`
                  JOIN `question` q   ON q.`id` = a.`question_id`
             LEFT JOIN `objectives` o ON o.`id` = q.`objective_id`
             LEFT JOIN `lesson` l     ON l.`id` = o.`lesson_id`
             LEFT JOIN `course` c     ON c.`id` = l.`course_id`
                 WHERE t.`student_id` = ? AND a.`is_correct` = 0';
        $bind = array($uid);

        if ($subject > 0) {
            $sql .= ' AND c.`id` IN (SELECT `course_id` FROM `paths` WHERE `subject_id` = ?)';
            $bind[] = $subject;
        }

        $sql .= ' GROUP BY q.`id`, q.`title`, q.`type`, q.`number_of_options`, q.`options`,
                           q.`objective_id`, o.`text`, o.`at_second`, o.`lesson_id`,
                           l.`title`, l.`course_id`, c.`title`
                  ORDER BY wrong_count DESC, q.`id` ASC
                  LIMIT ' . $limit;

        $rows = $this->db->query($sql, $bind)->result_array();

        foreach ($rows as &$r) {
            $r['options'] = $r['options'] ? json_decode($r['options'], true) : array();
            foreach (array('id','number_of_options','objective_id','at_second',
                           'lesson_id','course_id','wrong_count') as $k) {
                if (isset($r[$k])) $r[$k] = (int) $r[$k];
            }
        }
        unset($r);

        return $this->ok(array('questions' => $rows, 'count' => count($rows)));
    }
}
