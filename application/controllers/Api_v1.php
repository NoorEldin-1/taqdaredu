<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * واجهة برمجة تقدر — الإصدار الأول.
 *
 * مدخل واحد لتطبيق Flutter: الدخول، وشاشات الحساب من بوابة الطالب (الملف
 * والإعدادات والاشتراك)، **وحلقة التعلم** — الرئيسية والكورسات والمنهج
 * والمشغل واختبار الدرس والمراجعة المتباعدة. وما سواها يبقى في الويب حتى
 * يطلب.
 *
 * والوحدات الخمس الأخيرة مرتبة بالاعتماد لا بالسهولة: الرئيسية تقرر ما
 * يفعله الطالب، والتعلم يعطي معرف الدرس، والدرس يشغله ويقيسه، والتقييم
 * يفتح ما بعده، والتمرين يعيد ما تعلم. وهي حلقة تعود إلى أولها.
 *
 * ------------------------------------------------------------------
 * قواعد لا تخرق في هذا الملف
 * ------------------------------------------------------------------
 *
 * ١ · **لا جلسة ولا كعكة.** الهوية من ترويسة `Authorization` وحدها
 *     (`Taqdar_api_model`). ونداء `$this->session` هنا يعني حالة على
 *     الخادم لطلب لا يحمل حالة — وهو أول ما ينكسر خلف موازن حمل.
 *
 * ٢ · **القرار في النموذج لا في المتحكم.** الحفظ يمر بـ
 *     `Taqdar_settings_model` و`Taqdar_billing_model` أنفسهما اللذين
 *     تمر بهما شاشات الويب. ونسخة ثانية من قواعد التحقق هنا تفترق عن
 *     أختها عند أول تعديل، فيقبل التطبيق ما يرفضه الموقع.
 *
 * ٣ · **الغلاف ثابت.** نجاح `{data, message, meta}` وخطأ
 *     `{message, code, errors}` — في كل نقطة بلا استثناء، حتى 404 و500.
 *
 * ٤ · **المال هللات.** `tq_api_money()` ترد العدد الصحيح والكسر والصيغة
 *     معا، فلا يحسب العميل بعائم.
 *
 * ٥ · **الملكية تفحص هنا.** `require_student()` ثم فحص صاحب الصف: رقم
 *     فاتورة مخمن لا يفتح فاتورة غيرك.
 *
 * والبادئة `api/` مقصودة: `csrf_exclude_uris` في
 * [config.php](../config/config.php) يستثني `api/.*` — والتطبيق لا يحمل
 * رمز حماية النماذج ولا كعكته.
 */
class Api_v1 extends CI_Controller
{
    /* حدود الطلبات — نافذة بالثواني وسقف داخلها. */
    const RL_LOGIN_MAX      = 10;      // محاولة دخول
    const RL_LOGIN_WINDOW   = 900;     // في ربع ساعة، لكل (بريد + عنوان)
    const RL_READ_MAX       = 120;     // قراءة
    const RL_READ_WINDOW    = 60;      // في الدقيقة، لكل رمز
    const RL_WRITE_MAX      = 30;      // كتابة
    const RL_WRITE_WINDOW   = 60;
    const RL_ANON_MAX       = 60;      // لزائر بلا رمز، لكل عنوان
    const RL_ANON_WINDOW    = 60;
    const RL_HEAVY_MAX      = 5;       // تصدير البيانات وما يشبهه
    const RL_HEAVY_WINDOW   = 3600;

    /** المستخدم الحالي وصف رمزه — يملآن مرة في `authenticate()`. */
    private $me    = null;
    private $token = null;

    /** معرف الطلب — يرد في كل استجابة وفي كل سطر سجل. */
    private $request_id = '';

    /** هل خرج رد بالفعل؟ يمنع حارس الأخطاء من الكتابة فوق رد سليم. */
    private $answered = false;

    /**
     * دور صاحب الرمز — يملأ في `require_portal()`.
     *
     * والصندوق الوارد وحده يفرع عليه: الإشعارات والمحادثات نقاط واحدة
     * للأدوار الثلاثة، والذي يختلف بينها **من يجوز مراسلته** لا أكثر.
     */
    private $role = '';

    public function __construct()
    {
        parent::__construct();

        $this->load->database();
        $this->load->helper('taqdar_api');
        $this->load->model('taqdar_api_model', 'api');

        /* المنطقة الزمنية كما تضبط في كل متحكم تقدر: بدونها يكتب هذا
           الملف بتوقيت الخادم وتكتب اللوحة بتوقيت المنصة، فيفترق
           الطابعان ثلاث ساعات في `audit_log` نفسه. */
        $tz = trim((string) get_settings('timezone'));
        if ($tz === '' || !in_array($tz, DateTimeZone::listIdentifiers(), true)) {
            $tz = 'Asia/Riyadh';
        }
        date_default_timezone_set($tz);

        $this->request_id = bin2hex(random_bytes(8));

        $this->guard_fatals();
        $this->cors();
    }

    /**
     * يجعل الخطأ غير المتوقع يخرج بالغلاف نفسه.
     *
     * الوثيقة تعد بأن **كل** رد على شكلين لا ثالث لهما — و`500` من ضمنها.
     * وبلا هذا الحارس يرد CI صفحة HTML كاملة على أي استثناء، فيقرأ عميل
     * Flutter `<!doctype html` ويرمي `FormatException` بدل أن يعرض
     * «تعذر إتمام الطلب». والفرق بين الاثنين هو الفرق بين خطأ يظهر للمستخدم
     * وانهيار في الشاشة.
     *
     * والتفصيل لا يخرج للعميل أبدا: يكتب في السجل ويعطى صاحبه `X-Request-Id`
     * ليقابل به. ورسالة خطأ فيها مسار ملف أو استعلام هي تسريب لا مساعدة.
     */
    private function guard_fatals()
    {
        set_exception_handler(function ($e) {
            log_message('error', 'API[' . $this->request_id . '] uncaught: '
                . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->emit_fatal();
        });

        register_shutdown_function(function () {
            if ($this->answered) return;               // رد سليم خرج بالفعل
            $e = error_get_last();
            if (!$e) return;
            if (!in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR,
                                            E_COMPILE_ERROR, E_USER_ERROR), true)) {
                return;                                 // تنبيه لا يوقف الرد
            }
            log_message('error', 'API[' . $this->request_id . '] fatal: '
                . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
            $this->emit_fatal();
        });
    }

    /** يرمي ما طبع ناقصا ثم يكتب الغلاف. لا يمر بـ`respond()`: قد يكون هو من سقط. */
    private function emit_fatal()
    {
        if ($this->answered) return;
        $this->answered = true;

        /* صفحة الخطأ التي بدأ CI بطباعتها تلقى: نصفها فوق JSON يجعل الرد
           غير قابل للتحليل أصلا. */
        while (ob_get_level() > 0) { @ob_end_clean(); }

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: application/json; charset=utf-8');
            header('X-Request-Id: ' . $this->request_id);
            header('Cache-Control: private, no-store, max-age=0');
        }

        echo json_encode(array(
            'message'    => 'تعذر إتمام الطلب. حاول مرة أخرى، فإن تكرر فأبلغنا برقم الطلب.',
            'code'       => 'server_error',
            'request_id' => $this->request_id,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /* ================================================================
       البنية التحتية
       ================================================================ */

    /**
     * ترويسات المصدر المشترك.
     *
     * تطبيق Flutter الأصيل لا يعرف CORS أصلا — لكن نسخة الويب منه تعرفه،
     * وكذلك كل أداة تجرب النقاط من متصفح. و`*` هنا **غير ضار**: الواجهة
     * لا تصدق كعكة، فرد مفتوح لا يعني جلسة مسروقة. ولو صدقت الكعكة يوما
     * لوجب حصر المصادر قبل ذلك بيوم.
     */
    private function cors()
    {
        $this->output
             ->set_header('Access-Control-Allow-Origin: *')
             ->set_header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS')
             ->set_header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, Accept-Language, X-Requested-With, If-None-Match')
             ->set_header('Access-Control-Expose-Headers: ETag, X-Request-Id, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After')
             ->set_header('Access-Control-Max-Age: 86400')
             ->set_header('Vary: Origin, Accept-Language, Authorization');

        if ($this->input->method(true) === 'OPTIONS') {
            $this->output->set_status_header(204)->_display();
            exit;
        }
    }

    /**
     * الإخراج الوحيد. كل رد في هذا الملف يمر من هنا، فالترويسات لا تنسى
     * في نقطة ولا تكتب مرتين بشكلين.
     */
    private function respond($payload, $status = 200, $headers = array())
    {
        /* ما تسرب قبل الرد يرمى هنا ويسجل.
           تنبيه PHP واحد — `Undefined array key` من نموذج غير معد — يطبع
           كتلة HTML **قبل** JSON، فيقرأ عميل Dart `<div style=` ويرمي
           `FormatException`. والوعد أن كل رد على شكلين لا ثالث لهما،
           و`guard_fatals()` كتبت لأجل نصفه الآخر (الاستثناء القاتل)؛
           وهذا نصفه الأول. والحارس نفسه في `Taqdar_gate::respond()`
           منذ TQ-GATE-CLEAN — وغيابه هنا يعني أن الواجهة التي تعد
           بالغلاف أضعف من البوابة التي لا تعد به.

           والتسرب يسجل ولا يبتلع صامتا: تنبيه يخفى يبقى إلى أن يصير
           عطلا. */
        $stray = '';
        while (ob_get_level() > 0) $stray .= (string) ob_get_clean();
        if (trim($stray) !== '') {
            log_message('error', 'API[' . $this->request_id . '] stray output before JSON: '
                . substr(trim($stray), 0, 400));
        }

        $this->output
             ->set_status_header($status)
             ->set_content_type('application/json', 'utf-8')
             ->set_header('X-Request-Id: ' . $this->request_id)
             ->set_header('X-Content-Type-Options: nosniff')
             /* ردود الواجهة كلها خاصة بصاحبها: وسيط يخزن ردا لطالب ثم
                يقدمه لطالب آخر تسريب لا بطء. */
             ->set_header('Cache-Control: private, no-store, max-age=0')
             ->set_header('Pragma: no-cache');

        foreach ($headers as $h) $this->output->set_header($h);

        $this->output->set_output(json_encode($payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));

        /* يرفع قبل الإخراج لا بعده: `exit` يشغل دالة الإغلاق فورا، فلو
           رفع بعدها لما وصل الرفع أبدا وكتب الحارس فوق رد سليم. */
        $this->answered = true;

        $this->output->_display();
        exit;
    }

    /** خطأ بالغلاف نفسه — ثم يتوقف الطلب. */
    private function fail($message, $code = 'error', $status = 400, $errors = array(), $headers = array())
    {
        $this->respond(tq_api_error($message, $code, $errors), $status, $headers);
    }

    /**
     * جسم الطلب مهما كانت صيغته.
     *
     * ثلاث صيغ تصل فعلا: JSON من Dio، و`form-urlencoded` من `http`
     * البسيطة، و`multipart` عند رفع الصورة. و`$this->input->post()` لا
     * يقرأ إلا الثانية ولا يقرأ PUT أصلا — فمن اعتمد عليها وحدها استقبل
     * طلبا فارغا من نصف العملاء بلا خطأ يظهر.
     */
    private function body()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $type = strtolower((string) $this->input->get_request_header('Content-Type', true));
        $raw  = file_get_contents('php://input');

        if (strpos($type, 'application/json') !== false) {
            $decoded = json_decode($raw, true);
            if ($raw !== '' && json_last_error() !== JSON_ERROR_NONE) {
                $this->fail('جسم الطلب ليس JSON صالحا.', 'malformed_json', 400);
            }
            $cache = is_array($decoded) ? $decoded : array();
            return $cache;
        }

        if (strpos($type, 'multipart/form-data') !== false || $this->input->method(true) === 'POST') {
            $cache = is_array($_POST) ? $_POST : array();
            if ($cache) return $cache;
        }

        $parsed = array();
        if ($raw !== '') parse_str($raw, $parsed);
        $cache = is_array($parsed) ? $parsed : array();
        return $cache;
    }

    /** قيمة واحدة من الجسم. */
    private function in($key, $default = null)
    {
        $b = $this->body();
        return array_key_exists($key, $b) ? $b[$key] : $default;
    }

    /** يشترط طريقة بعينها — الطريقة الخاطئة 405 لا 404. */
    private function method($allowed)
    {
        $allowed = (array) $allowed;
        $m = $this->input->method(true);
        if (!in_array($m, $allowed, true)) {
            $this->fail('طريقة الطلب غير مدعومة على هذه النقطة.', 'method_not_allowed', 405,
                        array(), array('Allow: ' . implode(', ', $allowed)));
        }
        return $m;
    }

    /**
     * الاستيثاق. يملأ `$this->me` أو يرد 401 برمز يقول ما العمل.
     *
     * والتفريق بين `token_expired` و`token_invalid` ليس تفصيلا: الأول
     * يعالجه التطبيق بتجديد صامت لا يراه المستخدم، والثاني بإخراجه إلى
     * شاشة الدخول. ورد واحد لهما يجعل كل ربع ساعة إخراجا.
     */
    private function authenticate()
    {
        if ($this->me !== null) return $this->me;

        $header = (string) $this->input->get_request_header('Authorization', true);
        if ($header === '' || stripos($header, 'Bearer ') !== 0) {
            $this->fail('هذا الطلب يحتاج تسجيل دخول.', 'unauthenticated', 401,
                        array(), array('WWW-Authenticate: Bearer'));
        }

        $r = $this->api->authenticate(trim(substr($header, 7)));

        if (empty($r['ok'])) {
            $messages = array(
                'token_expired'    => 'انتهت صلاحية رمز الدخول. جدده وأعد المحاولة.',
                'token_revoked'    => 'أبطل رمز الدخول. سجل دخولك من جديد.',
                'account_disabled' => 'هذا الحساب موقوف. تواصل مع الإدارة.',
                'token_invalid'    => 'رمز الدخول غير صالح.',
            );
            $code = $r['code'];
            $this->fail(isset($messages[$code]) ? $messages[$code] : $messages['token_invalid'],
                        $code, ($code === 'account_disabled') ? 403 : 401,
                        array(), array('WWW-Authenticate: Bearer error="' . $code . '"'));
        }

        $this->me    = $r['user'];
        $this->token = $r['token'];
        return $this->me;
    }

    /**
     * يشترط أن يكون صاحب الرمز طالبا.
     *
     * والبوابات الثلاث لها نقاطها اليوم، فالرسالة تدل على بوابة القارئ
     * بدل أن تقول «لم تصدر بعد» — وهي عبارة صارت كاذبة، ومن قرأها انتظر
     * إصدارا وقد كان بابه مفتوحا.
     */
    private function require_student()
    {
        $u    = $this->authenticate();
        $role = tq_role((int) $u['id']);

        if ($role !== 'student') {
            $gate = ($role === 'teacher') ? '/api/v1/teacher/*'
                  : (($role === 'parent') ? '/api/v1/parent/*' : '');
            $this->fail('هذه النقطة لبوابة الطالب.'
                        . ($gate !== '' ? ' وبوابتك هي ' . $gate . '.' : ''),
                        'wrong_role', 403);
        }
        return $u;
    }

    /**
     * حد الطلبات. يفرض دائما ويرد ترويساته دائما — حتى حين يسمح، فالعميل
     * الحسن يبطئ نفسه قبل أن يرد عليه 429.
     */
    private function limit($scope, $max, $window)
    {
        $who = $this->me
             ? 'u' . (int) $this->me['id']
             : 'ip' . $this->input->ip_address();

        $r = $this->api->throttle($scope . ':' . $who, $max, $window);

        $headers = array(
            'X-RateLimit-Limit: ' . $r['limit'],
            'X-RateLimit-Remaining: ' . $r['remaining'],
            'X-RateLimit-Reset: ' . $r['reset'],
        );

        if (!$r['allowed']) {
            $headers[] = 'Retry-After: ' . $r['retry_after'];
            $this->fail('تجاوزت عدد الطلبات المسموح به. أعد المحاولة بعد '
                        . $r['retry_after'] . ' ثانية.', 'rate_limited', 429, array(), $headers);
        }

        return $headers;
    }

    /**
     * رد قراءة مع ETag.
     *
     * شاشة الملف تقرأ خريطة إتقان وشهادات وتسعين يوم نشاط، وتفتح كلما
     * رجع الطالب إليها. و`304` هنا يوفر الحمولة كاملة حين لا يتغير شيء —
     * والخادم يحسبها على أي حال، فالوفر في الشبكة لا في المعالج. وهو
     * الوفر الذي يهم على جوال بشبكة ضعيفة.
     */
    private function read($data, $message = '', $meta = array(), $headers = array())
    {
        $payload = tq_api_ok($data, $message, $meta);
        $etag    = '"' . md5(json_encode($payload, JSON_UNESCAPED_UNICODE)) . '"';

        $sent = trim((string) $this->input->get_request_header('If-None-Match', true));
        if ($sent !== '' && $sent === $etag) {
            $this->respond(null, 304, array_merge($headers, array('ETag: ' . $etag)));
        }

        $this->respond($payload, 200, array_merge($headers, array('ETag: ' . $etag)));
    }

    /* ================================================================
       الفهرس
       ================================================================ */

    /** GET /api/v1 — بطاقة تعريف الواجهة. لا تحتاج رمزا. */
    public function index()
    {
        $this->method('GET');
        $h = $this->limit('anon', self::RL_ANON_MAX, self::RL_ANON_WINDOW);

        $this->read(array(
            'name'        => 'Taqdar Mobile API',
            'version'     => 'v1',
            'status'      => 'ok',
            'server_time' => date('c'),
            'docs_url'    => base_url('api/docs'),
            'openapi_url' => base_url('api/docs/openapi.json'),
            'postman_url' => base_url('api/docs/collection.json'),
        ), '', array(), $h);
    }

    /* ================================================================
       الدخول
       ================================================================ */

    /**
     * POST /api/v1/auth/login
     *
     * المنطق هو منطق [Login::validate_login](Login.php) نفسه — التلبيدة
     * القديمة تقبل وترقى، والحساب الموقوف يفحص **بعد** الاستيثاق لا قبله
     * فلا يكشف الرد شيئا لمن لا يملك كلمة المرور.
     *
     * وثلاثة أبواب مسدودة عمدا:
     *   · الأدمن لا يدخل من هنا — واجهة تطبيق لا تصنع جلسة إدارية
     *     (وهو القيد نفسه في `Api::web_redirect_to_buy_course_get`).
     *   · العداد الموروث (`tq_auth_*`) يعمل مع عداد الواجهة معا: الأول
     *     يعد الإخفاقات والثاني يعد الطلبات، ومن جرب ألف كلمة صحيحة
     *     على ألف حساب لا يوقفه عداد الإخفاقات وحده.
     *   · رسالة واحدة للبريد المجهول وللكلمة الخاطئة، فلا تعرف الواجهة
     *     من له حساب عندنا.
     */
    public function auth_login()
    {
        $this->method('POST');

        $b     = $this->body();
        $email = trim((string) ($b['email'] ?? ''));
        $pass  = (string) ($b['password'] ?? '');

        $errors = tq_api_validate($b, array(
            'email'    => 'required|email|max:190',
            'password' => 'required|max:255',
        ));
        if ($errors) {
            $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);
        }

        /* حد الطلبات على (البريد + العنوان) لا على العنوان وحده: بيت
           واحد خلف NAT لا يقفل على أهله لأن أحدهم أخطأ. */
        $this->limit('login:' . strtolower($email), self::RL_LOGIN_MAX, self::RL_LOGIN_WINDOW);

        if (tq_auth_is_throttled($email, 'login')) {
            $this->fail(tq_auth_throttle_message(), 'too_many_attempts', 429,
                        array(), array('Retry-After: ' . TQ_AUTH_WINDOW));
        }

        $row = $this->db->get_where('users', array('email' => $email))->row_array();

        if (!$row || !tq_password_authenticate($row, $pass)) {
            tq_auth_record_failure($email, 'login');
            $this->fail('البريد الإلكتروني أو كلمة المرور غير صحيحة.', 'invalid_credentials', 401);
        }

        tq_auth_clear_failures($email, 'login');

        $role = tq_role((int) $row['id']);

        if ($role === 'admin') {
            $this->api->audit('api.login.admin_refused', (int) $row['id']);
            $this->fail('حسابات الإدارة تدخل من لوحة الويب لا من التطبيق.',
                        'admin_not_allowed', 403);
        }

        /* الحساب الموقوف: يقال له **لماذا** ويدل على الباب الذي يفتحه.
           و«بريدك غير مؤكد» و«طلبك قيد المراجعة» حالان مختلفان تماما،
           ورد واحد لهما يترك المعلم ينتظر رمزا لا يأتي. */
        if ((int) $row['status'] !== 1) {
            if (!empty($row['is_instructor']) || (string) $row['tq_gate'] === 'teacher') {
                $this->fail('طلب انضمامك معلما ما زال قيد المراجعة. نتواصل معك عند الاعتماد.',
                            'teacher_pending_approval', 403);
            }
            $this->fail('بريدك لم يؤكد بعد. أكمل التحقق من الموقع ثم عد إلى التطبيق.',
                        'email_not_verified', 403);
        }

        $pair = $this->api->issue_pair((int) $row['id'], array(
            'device_name' => $b['device_name'] ?? null,
            'device_id'   => $b['device_id']   ?? null,
            'platform'    => $b['platform']    ?? null,
            'app_version' => $b['app_version'] ?? null,
        ));

        $this->api->audit('api.login', (int) $row['id'],
                          array('platform' => $b['platform'] ?? null));

        unset($pair['family']);   // شأن داخلي لا يعني العميل

        $this->respond(tq_api_ok(array(
            'token' => $pair,
            'user'  => tq_api_user($row),
        ), 'أهلا بك، ' . trim($row['first_name'] . ' ' . $row['last_name']) . '.'), 200);
    }

    /**
     * POST /api/v1/auth/refresh
     *
     * التدوير: الرمز المقدم يبطل ويصدر زوج جديد. ومن قدم رمزا مبطلا قطعت
     * سلسلته كلها — انظر `Taqdar_api_model::rotate()`.
     */
    public function auth_refresh()
    {
        $this->method('POST');
        $this->limit('refresh', 60, 3600);

        $b       = $this->body();
        $refresh = trim((string) ($b['refresh_token'] ?? ''));

        if ($refresh === '') {
            $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422,
                        array('refresh_token' => array('هذا الحقل مطلوب.')));
        }

        $r = $this->api->rotate($refresh, array(
            'device_name' => $b['device_name'] ?? null,
            'device_id'   => $b['device_id']   ?? null,
            'platform'    => $b['platform']    ?? null,
            'app_version' => $b['app_version'] ?? null,
        ));

        if (empty($r['ok'])) {
            $messages = array(
                'token_expired'    => 'انتهت صلاحية رمز التجديد. سجل دخولك من جديد.',
                'token_reused'     => 'استعمل رمز التجديد مرتين، فأبطلت الجلسة كلها احتياطا. سجل دخولك من جديد.',
                'account_disabled' => 'هذا الحساب موقوف. تواصل مع الإدارة.',
                'token_invalid'    => 'رمز التجديد غير صالح.',
            );
            $code = $r['code'];
            $this->fail(isset($messages[$code]) ? $messages[$code] : $messages['token_invalid'],
                        $code, ($code === 'account_disabled') ? 403 : 401);
        }

        $pair = $r['pair'];
        unset($pair['family']);

        $this->respond(tq_api_ok(array(
            'token' => $pair,
            'user'  => tq_api_user($r['user']),
        )), 200);
    }

    /** POST /api/v1/auth/logout — خروج من هذا الجهاز وحده. */
    public function auth_logout()
    {
        $this->method('POST');
        $this->authenticate();

        $this->api->revoke_token($this->token);
        $this->api->audit('api.logout', (int) $this->me['id']);

        $this->respond(tq_api_ok(null, 'سجل خروجك من هذا الجهاز.'), 200);
    }

    /** POST /api/v1/auth/logout-all — خروج من كل الأجهزة. */
    public function auth_logout_all()
    {
        $this->method('POST');
        $this->authenticate();

        $this->api->revoke_all((int) $this->me['id']);
        $this->api->audit('api.logout_all', (int) $this->me['id']);

        $this->respond(tq_api_ok(null, 'سجل خروجك من كل الأجهزة.'), 200);
    }

    /** GET /api/v1/auth/me — صاحب الرمز. */
    public function auth_me()
    {
        $this->method('GET');
        $u = $this->authenticate();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->read(tq_api_user($u, array(
            'email_verified_at' => tq_api_date($u['tq_verified_at'] ?? null),
        )), '', array(), $h);
    }

    /**
     * GET /api/v1/auth/sessions — الأجهزة الداخلة.
     *
     * جزء من طبقة الرموز لا شاشة جديدة: من يصدر رموزا يبطل لا بد أن يري
     * صاحبها ما هو قائم منها، وإلا صار «اخرج من كل الأجهزة» زرا يضغط على
     * العمياء.
     */
    public function auth_sessions()
    {
        $this->method('GET');
        $u = $this->authenticate();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $rows = array();
        foreach ($this->api->sessions_of((int) $u['id']) as $s) {
            $rows[] = array(
                'device_name'  => $s['device_name'],
                'platform'     => $s['platform'],
                'app_version'  => $s['app_version'],
                'ip'           => $s['ip'],
                'created_at'   => tq_api_date($s['created_at']),
                'last_used_at' => tq_api_date($s['last_used_at']),
                'current'      => ($s['family'] === ($this->token['family'] ?? '')),
            );
        }

        $this->read($rows, '', array('count' => count($rows)), $h);
    }

    /* ================================================================
       الطالب — ملفي
       ================================================================ */

    /**
     * GET /api/v1/student/profile
     *
     * مطابق لما تعرضه [tq_profile.php](../views/frontend/taqdar/tq_profile.php):
     * الهوية والصف والانتظام وخريطة الإتقان والشهادات.
     *
     * **ولا مقارنة بأحد** — لا ترتيب ولا نسبة مقابل زملاء. القاعدة نفسها
     * التي تسري في الويب تسري هنا: مصدر ضغط منزلي لا يصير أخف لأنه وصل
     * في JSON.
     *
     * والنشاط وخريطة الإتقان الكاملة **لا تخرجان هنا**: تسعون صفا وقائمة
     * أهداف بلا حد في رد يفتح عند كل رجوع إلى الشاشة. ولكل منهما نقطته.
     */
    public function student_profile()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];

        $this->load->model('taqdar_learn_model', 'tq_learn');
        $this->load->model('taqdar_repo_model', 'tq_repo');

        $grade = $this->db->query(
            'SELECT g.`name_ar` FROM `users` u JOIN `grades` g ON g.`id` = u.`grade_id`
              WHERE u.`id` = ? LIMIT 1', array($uid))->row('name_ar');

        $map = array('count' => 0, 'average_level' => 0, 'weakest' => array(), 'objectives' => array());
        try { $map = $this->tq_repo->get_skill_map($uid); } catch (Throwable $e) {}

        $mastered = 0;
        foreach ($map['objectives'] as $o) if ((float) $o['level'] >= 80) $mastered++;

        /* أيام النشاط تعد هنا ولا ترسل مفصلة: الرقم هو ما تعرضه البطاقة،
           والمربعات التسعون لها `/profile/activity`. */
        $days   = $this->tq_learn->activity_range($uid, 91);
        $active = 0;
        foreach ($days as $d) if (!empty($d['active'])) $active++;

        /* `streak()` ترد مصفوفة `{days, today, best}` لا عددا — وقصها إلى
           `int` يعطي `1` دائما، فيقرأ كل طالب أن انتظامه يوم واحد. */
        $streak = $this->tq_learn->streak($uid);

        $this->read(array(
            'user'  => tq_api_user($u, array('grade_name' => $grade ?: null)),
            'stats' => array(
                'certificates'    => count($this->certificates_of($uid)),
                'average_mastery' => (float) $map['average_level'],
                'objectives'      => (int) $map['count'],
                'mastered'        => $mastered,
                'active_days_90'  => $active,
            ),
            'streak' => array(
                'days'  => (int) $streak['days'],
                'best'  => (int) $streak['best'],
                'today' => (bool) $streak['today'],
            ),
            'goal_today'  => $this->tq_learn->goal_today($uid),
            'exam_mode'   => $this->tq_learn->exam_mode($uid),
            'weakest'     => $this->objectives_out(array_slice($map['weakest'], 0, 5)),
            'certificates'=> $this->certificates_of($uid),
        ), '', array(), $h);
    }

    /**
     * GET /api/v1/student/profile/activity?days=91
     * شبكة الانتظام — مربع لكل يوم كما في الويب.
     */
    public function student_activity()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $days = (int) $this->input->get('days');
        if ($days < 1 || $days > 366) $days = 91;

        $this->load->model('taqdar_learn_model', 'tq_learn');
        $rows = $this->tq_learn->activity_range((int) $u['id'], $days);

        /* المفتاح `day` لا `date` — كما ترده `activity_range()` حرفا.
           واسم مخترع هنا يعني حقلا فارغا في كل مربع بلا خطأ يظهر. */
        $out = array();
        foreach ($rows as $d) {
            $out[] = array(
                'day'     => $d['day'],
                'active'  => !empty($d['active']),
                'lessons' => (int) $d['lessons'],
                'reviews' => (int) $d['reviews'],
                'seconds' => (int) $d['seconds'],
            );
        }

        $this->read($out, '', array('days' => $days, 'count' => count($out)), $h);
    }

    /**
     * GET /api/v1/student/profile/mastery
     * خريطة الإتقان كاملة — مرقمة، فقائمة الأهداف تكبر بكبر المنهج.
     */
    public function student_mastery()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->load->model('taqdar_repo_model', 'tq_repo');

        $map = array('objectives' => array(), 'count' => 0, 'average_level' => 0);
        try { $map = $this->tq_repo->get_skill_map((int) $u['id']); } catch (Throwable $e) {}

        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 200, 50);

        $slice = array_slice($map['objectives'], $offset, $per);

        $this->read($this->objectives_out($slice),
            '',
            array_merge(
                tq_api_meta_page($page, $per, (int) $map['count']),
                array('average_level' => (float) $map['average_level'])
            ),
            $h);
    }

    /**
     * شكل الهدف الواحد.
     *
     * `skill_state.level` مدرج **0..100** لا كسرا عشريا — وهو أحد الأعمدة
     * التي تخطئ الظن فيها (CLAUDE.md). فيخرج كما هو ويقال مداه في الوثيقة،
     * فلا يضربه العميل في مئة مرة ثانية.
     */
    private function objectives_out($rows)
    {
        $out = array();
        foreach ((array) $rows as $r) {
            $out[] = array(
                'objective_id' => (int) $r['objective_id'],
                'text'         => (string) $r['objective_text'],
                'level'        => (float) $r['level'],        // 0..100
                'forget_rate'  => (float) $r['forget_rate'],
                'last_seen_at' => tq_api_date($r['last_seen_at'] ?? null),
                'lesson'       => array(
                    'id'    => isset($r['lesson_id']) ? (int) $r['lesson_id'] : null,
                    'title' => $r['lesson_title'] ?? null,
                ),
                'course'       => array(
                    'id'    => isset($r['course_id']) ? (int) $r['course_id'] : null,
                    'title' => $r['course_title'] ?? null,
                ),
            );
        }
        return $out;
    }

    /**
     * الشهادات — التعريف نفسه الذي في `tq_certificates.php` و
     * `Taqdar::certificate_row()`: اجتياز تقييم من نوع `exam`. وتعريف ثان
     * هنا يعني أن التطبيق يعد الشهادات عددا والموقع يعدها آخر.
     */
    private function certificates_of($uid)
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $cache = array();
        try {
            if (!$this->db->table_exists('attempts')) return $cache;

            $rows = $this->db->query(
                "SELECT a.id, a.score, a.submitted_at,
                        m.title AS milestone_title, p.title AS path_title
                   FROM attempts a
                   JOIN assessments s ON s.id = a.assessment_id AND s.type = 'exam'
                   LEFT JOIN milestones m ON m.id = s.milestone_id
                   LEFT JOIN paths p ON p.id = COALESCE(s.path_id, m.path_id)
                  WHERE a.student_id = ? AND a.passed = 1
                  ORDER BY a.submitted_at DESC LIMIT 12", array((int) $uid))->result_array();

            foreach ($rows as $r) {
                $cache[] = array(
                    'id'          => (int) $r['id'],
                    'title'       => $r['milestone_title'] ?: ($r['path_title'] ?: 'شهادة'),
                    'path'        => $r['path_title'],
                    'score'       => (float) $r['score'],
                    'issued_at'   => tq_api_date($r['submitted_at']),
                    'download_url'=> base_url('student/certificate/' . (int) $r['id']),
                );
            }
        } catch (Throwable $e) {
            $cache = array();
        }
        return $cache;
    }

    /* ================================================================
       الطالب — الإعدادات
       ================================================================ */

    /**
     * GET /api/v1/student/settings
     *
     * الأقسام الستة في رد واحد: شاشة الإعدادات في التطبيق تفتح مرة وتنقل
     * بين تبويباتها بلا شبكة، وستة نداءات لستة تبويبات تعني ست دورات
     * تحميل على شبكة جوال.
     *
     * والقوائم المرجعية تخرج معها (أنواع التنبيه وقنواته واللغات): من دونها
     * يكتب Flutter الأسماء عنده، فتضاف قناة في الخادم ولا يراها أحد.
     */
    public function student_settings()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $this->load->model('taqdar_settings_model', 'tq_set');

        $types = array();
        foreach ($this->tq_set->notify_types() as $key => $t) {
            $types[] = array('key' => $key, 'label' => $t[0], 'hint' => $t[1]);
        }

        $channels = array();
        foreach ($this->tq_set->notify_channels() as $key => $label) {
            $channels[] = array('key' => $key, 'label' => $label);
        }

        $langs = array();
        foreach ($this->tq_set->languages() as $key => $label) {
            $langs[] = array('key' => $key, 'label' => $label);
        }

        $prefs = $this->tq_set->prefs($uid);

        /* آخر وسيلة دفع استعملت فعلا — لا «وسيلة محفوظة»: المنصة لا تحفظ
           بطاقات، وحقل باسم `saved_card` يعد بما لا يوجد. */
        $last_pay = $this->db->table_exists('subscriptions')
            ? $this->db->select('method, created_at')->where('user_id', $uid)
                       ->where('method IS NOT NULL', null, false)
                       ->order_by('id', 'DESC')->limit(1)
                       ->get('subscriptions')->row_array()
            : null;

        $pay_names = array('manual' => 'تحويل بنكي يدوي', 'free' => 'باقة مجانية', 'tap' => 'بطاقة');

        $this->read(array(
            'profile' => array(
                'first_name' => (string) $u['first_name'],
                'last_name'  => (string) $u['last_name'],
                'email'      => (string) $u['email'],
                'phone'      => (string) $u['phone'],
                'avatar_url' => tq_api_avatar($u['image']),
                'avatar_max_bytes' => Taqdar_settings_model::IMAGE_MAX_BYTES,
            ),
            'notifications' => array(
                'types'    => $types,
                'channels' => $channels,
                'matrix'   => $this->tq_set->notify_matrix($uid),
                'quiet_hours' => array(
                    'enabled' => (bool) $prefs['quiet_on'],
                    'from'    => (int) $prefs['quiet_from'],
                    'to'      => (int) $prefs['quiet_to'],
                ),
            ),
            'preferences' => array(
                'language'  => $prefs['language'],
                'languages' => $langs,
                /* الوجه واحد فاتح — `save_prefs` تثبت `auto` ولا تقرأ
                   المدخل. ويقال ذلك صراحة بدل حقل يقبل ولا يؤثر. */
                'theme'     => 'light',
                'theme_locked' => true,
            ),
            'billing' => array(
                'saves_card'    => false,
                'last_method'   => $last_pay ? (string) $last_pay['method'] : null,
                'last_method_label' => $last_pay
                    ? ($pay_names[$last_pay['method']] ?? $last_pay['method']) : null,
                'last_used_at'  => $last_pay ? tq_api_date($last_pay['created_at']) : null,
            ),
            'downloads' => array(
                'available' => false,
                'note'      => 'التحميل للعمل دون اتصال غير متاح بعد. المواد تشاهد داخل المنصة بصلاحية زمنية.',
            ),
            'parent_links' => $this->parent_links_out($uid),
        ), '', array(), $h);
    }

    /** PUT /api/v1/student/settings/profile */
    public function settings_profile()
    {
        $this->method(array('PUT', 'PATCH', 'POST'));
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array(
            'first_name' => 'required|max:120',
            'last_name'  => 'max:120',
            'email'      => 'required|email|max:50',
            'phone'      => 'phone|max:25',
        ));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        /* القرار في النموذج نفسه الذي تمر به شاشة الويب، وهو يقرأ من
           `$this->input->post()` — فيحقن الجسم فيها. ونسخ قواعده هنا يعني
           قاعدتين تفترقان عند أول تعديل. */
        $this->as_post($b);

        $this->load->model('taqdar_settings_model', 'tq_set');
        $r = $this->tq_set->save_profile((int) $u['id']);

        $this->settings_result($r, (int) $u['id'], 'profile');
    }

    /**
     * POST /api/v1/student/settings/avatar — `multipart/form-data`، الحقل
     * `user_image`.
     *
     * POST لا PUT: PHP لا يفكك `multipart` إلا على POST، فنقطة PUT ترد
     * `$_FILES` فارغة ويقال للمستخدم «اختر صورة» وهو قد اختارها.
     */
    public function settings_avatar()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        if (empty($_FILES['user_image']['name'])) {
            $this->fail('أرفق صورة في الحقل user_image.', 'validation_failed', 422,
                        array('user_image' => array('هذا الحقل مطلوب.')));
        }

        /* `save_profile` هي من تعرف حدود الصورة وإعادة ترميزها، وهي تطلب
           بقية الحقول معها. فتملأ من صف المستخدم القائم: رفع صورة لا
           يعني تعديل بريد. */
        $this->as_post(array(
            'first_name' => (string) $u['first_name'],
            'last_name'  => (string) $u['last_name'],
            'email'      => (string) $u['email'],
            'phone'      => (string) $u['phone'],
        ));

        $this->load->model('taqdar_settings_model', 'tq_set');
        $r = $this->tq_set->save_profile((int) $u['id']);

        if (empty($r['ok'])) {
            $this->fail(implode(' ', $r['errors']), 'upload_failed', 422,
                        array('user_image' => $r['errors']));
        }

        $code = (string) $this->db->select('image')->where('id', (int) $u['id'])
                                  ->get('users')->row('image');

        $this->api->audit('api.settings.avatar', (int) $u['id']);
        $this->respond(tq_api_ok(array('avatar_url' => tq_api_avatar($code)),
                                 'حدثت صورتك.'), 200);
    }

    /**
     * PUT /api/v1/student/settings/password
     *
     * وتغيير الكلمة **يبطل رموز بقية الأجهزة**. من غير كلمته لأنها سربت
     * ثم بقي الجهاز الآخر داخلا لم يغير شيئا.
     */
    public function settings_password()
    {
        $this->method(array('PUT', 'PATCH', 'POST'));
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array(
            'current_password' => 'required',
            'new_password'     => 'required|min:8|max:255',
            'confirm_password' => 'required',
        ));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $this->as_post($b);

        $this->load->model('taqdar_settings_model', 'tq_set');
        $r = $this->tq_set->save_password((int) $u['id']);

        if (empty($r['ok'])) {
            $this->fail(implode(' ', $r['errors']), 'validation_failed', 422,
                        array('current_password' => $r['errors']));
        }

        $this->api->revoke_all((int) $u['id'], $this->token['family']);
        $this->api->audit('api.settings.password', (int) $u['id']);

        $this->respond(tq_api_ok(null,
            'غيرت كلمة مرورك، وأخرجت بقية الأجهزة.'), 200);
    }

    /** PUT /api/v1/student/settings/notifications */
    public function settings_notifications()
    {
        $this->method(array('PUT', 'PATCH', 'POST'));
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();

        $this->load->model('taqdar_settings_model', 'tq_set');

        /* `save_alerts` تقرأ `notify[<نوع>][<قناة>]` كما يرسلها نموذج
           الويب، والجسم هنا JSON متداخل — والشكل نفسه، فيمرر كما هو.
           وما لم يذكر يعتبر مطفأ: هذا ما يفعله النموذج (خانة غير معلمة
           لا ترسل)، فحذف نوع من الجسم يطفئه لا يبقيه. */
        $matrix = $b['notify'] ?? ($b['matrix'] ?? array());
        if (!is_array($matrix)) $matrix = array();

        $quiet = $b['quiet_hours'] ?? array();

        $this->as_post(array(
            'notify'     => $matrix,
            'quiet_on'   => tq_api_bool($quiet['enabled'] ?? false) ? 1 : 0,
            'quiet_from' => (int) ($quiet['from'] ?? 22),
            'quiet_to'   => (int) ($quiet['to']   ?? 7),
        ));

        $r = $this->tq_set->save_alerts((int) $u['id']);

        $this->settings_result($r, (int) $u['id'], 'notifications', array(
            'matrix'      => $this->tq_set->notify_matrix((int) $u['id']),
            'quiet_hours' => $this->quiet_out((int) $u['id']),
        ));
    }

    /** PUT /api/v1/student/settings/preferences */
    public function settings_preferences()
    {
        $this->method(array('PUT', 'PATCH', 'POST'));
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array('language' => 'required|max:32'));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $this->as_post($b);

        $this->load->model('taqdar_settings_model', 'tq_set');
        $r = $this->tq_set->save_prefs((int) $u['id']);

        if (empty($r['ok'])) {
            $this->fail(implode(' ', (array) $r['errors']), 'validation_failed', 422,
                        array('language' => (array) $r['errors']));
        }

        $prefs = $this->tq_set->prefs((int) $u['id']);
        $this->api->audit('api.settings.preferences', (int) $u['id']);

        $this->respond(tq_api_ok(array(
            'language' => $prefs['language'],
            'theme'    => 'light',
        ), $r['message']), 200);
    }

    private function quiet_out($uid)
    {
        $this->load->model('taqdar_settings_model', 'tq_set');
        $p = $this->tq_set->prefs((int) $uid);
        return array(
            'enabled' => (bool) $p['quiet_on'],
            'from'    => (int) $p['quiet_from'],
            'to'      => (int) $p['quiet_to'],
        );
    }

    /** GET /api/v1/student/settings/parent-links */
    public function settings_parent_links()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->read($this->parent_links_out((int) $u['id']), '', array(), $h);
    }

    /**
     * POST /api/v1/student/settings/parent-links/{id}
     * الجسم: `{"action": "approve"|"reject"|"withdraw"}`
     *
     * الموافقة بيان قانوني يوقعه صاحبه: النموذج يرفض أن يوقعها ولي الأمر،
     * وهذه النقطة تمنح الطالب أن يوقعها من تطبيقه. والملكية في النموذج
     * (`$link_id` مع `$student_id` معا) لا في المتحكم وحده.
     */
    public function settings_parent_link($link_id = 0)
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid     = (int) $u['id'];
        $link_id = (int) $link_id;
        $action  = (string) $this->in('action', '');

        if (!in_array($action, array('approve', 'reject', 'withdraw'), true)) {
            $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422,
                        array('action' => array('القيمة غير مقبولة. المتاح: approve · reject · withdraw')));
        }

        $this->load->model('taqdar_parent_model');

        /* لكل فعل دالته الموقعة باسمه — انظر `Taqdar::parent_link_respond()`:
           الرفض كان ينادي `revoke_link($link_id, $uid)` وتوقيعها
           `($parent_id, $student_id)`، فلا يطابق شيئا أبدا. */
        if ($action === 'reject') {
            $r = $this->taqdar_parent_model->reject_request($link_id, $uid);
            $msg = 'رفضت الطلب، ولم يفتح شيء من بياناتك.';
        } elseif ($action === 'withdraw') {
            $r = $this->taqdar_parent_model->withdraw_consent($uid, $link_id);
            $msg = 'سحبت موافقتك، ولم يعد ولي أمرك يرى شيئا من بياناتك.';
        } else {
            $r = $this->taqdar_parent_model->grant_consent($link_id, $uid);
            $msg = 'وافقت على الربط، ويستطيع ولي أمرك متابعة تقدمك الآن.';
        }

        if (empty($r['ok'])) {
            $this->fail(isset($r['errors']) ? implode(' ', (array) $r['errors']) : 'تعذر تنفيذ الطلب.',
                        'action_failed', 422);
        }

        $this->api->audit('api.parent_link.' . $action, $uid,
                          array('link_id' => $link_id));

        $this->respond(tq_api_ok($this->parent_links_out($uid), $msg), 200);
    }

    private function parent_links_out($uid)
    {
        $this->load->model('taqdar_parent_model');

        $shape = function ($rows) {
            $out = array();
            foreach ((array) $rows as $r) {
                $out[] = array(
                    'id'         => (int) $r['id'],
                    'parent'     => array(
                        'name'  => $r['name'] ?: $r['email'],
                        'email' => (string) $r['email'],
                    ),
                    'status'     => (string) $r['status'],
                    'consent_at' => tq_api_date($r['consent_at'] ?? null),
                );
            }
            return $out;
        };

        return array(
            'consent_text' => Taqdar_parent_model::CONSENT_TEXT,
            'pending'      => $shape($this->taqdar_parent_model->links_of_student((int) $uid, 'pending')),
            'active'       => $shape($this->taqdar_parent_model->links_of_student((int) $uid, 'active')),
        );
    }

    /**
     * GET /api/v1/student/settings/export — نسخة من بياناتك.
     *
     * محدودة بخمس مرات في الساعة: الرد يجمع ست جداول كاملة، ونقطة بلا حد
     * تصير أداة إنهاك للخادم بحساب واحد.
     */
    public function settings_export()
    {
        $this->method('GET');
        $u = $this->require_portal();
        $this->limit('export', self::RL_HEAVY_MAX, self::RL_HEAVY_WINDOW);

        $uid = (int) $u['id'];
        $out = array('generated_at' => date('c'), 'account' => null,
                     'learning' => array(), 'payments' => array());

        $acc = $this->db->where('id', $uid)->get('users')->row_array();
        if ($acc) {
            unset($acc['password'], $acc['verification_code'], $acc['payment_keys'],
                  $acc['sessions'], $acc['temp']);
            $out['account'] = $acc;
        }

        foreach (array('enrol', 'lesson_progress', 'attempts', 'answers',
                       'review_queue', 'skill_state') as $t) {
            if (!$this->db->table_exists($t)) continue;
            $col = ($t === 'enrol') ? 'user_id' : 'student_id';
            if (!in_array($col, $this->db->list_fields($t), true)) continue;
            $out['learning'][$t] = $this->db->where($col, $uid)->get($t)->result_array();
        }

        $pcols = $this->db->list_fields('payment');
        if (in_array('user_id', $pcols, true)) {
            $out['payments'] = $this->db->where('user_id', $uid)->get('payment')->result_array();
        }

        $this->api->audit('api.export_data', $uid);
        $this->respond(tq_api_ok($out, 'هذه نسخة من بياناتك.'), 200);
    }

    /**
     * DELETE /api/v1/student/account — **تجهيل لا محو**.
     *
     * حقول الهوية تستبدل بقيم مجهولة وتبقى القيود المالية بمعرف مجهول،
     * لأن الالتزام الضريبي يوجب حفظ الفواتير. وهو المنطق نفسه في
     * [Taqdar::delete_account()](Taqdar.php).
     *
     * والتأكيد صريح في الجسم: `{"confirm": "DELETE"}`. طلب بلا تأكيد
     * يجهل حسابا بضغطة عابرة أو بإعادة محاولة تلقائية من العميل.
     */
    public function account_delete()
    {
        $this->method(array('DELETE', 'POST'));
        $u = $this->require_portal();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        if ((string) $this->in('confirm', '') !== 'DELETE') {
            $this->fail('أرسل confirm بقيمة DELETE لتأكيد الحذف.', 'confirmation_required', 422,
                        array('confirm' => array('القيمة المطلوبة: DELETE')));
        }

        $uid  = (int) $u['id'];
        $anon = 'deleted_' . $uid . '_' . substr(md5($uid . microtime(true)), 0, 8);

        $this->db->where('id', $uid)->update('users', array(
            'first_name'   => 'حساب',
            'last_name'    => 'محذوف',
            'email'        => $anon . '@deleted.invalid',
            'phone'        => '',
            'address'      => '',
            'biography'    => '',
            'image'        => '',
            'social_links' => '{}',
            'status'       => 0,
        ));

        $this->api->revoke_all($uid);
        $this->api->audit('account.anonymised', $uid, array('by' => 'api', 'handle' => $anon));

        $this->respond(tq_api_ok(null,
            'جهل حسابك. تبقى فواتيرك في السجل بمعرف مجهول كما يوجب النظام.'), 200);
    }

    /* ================================================================
       الطالب — الاشتراك والفواتير
       ================================================================ */

    /**
     * GET /api/v1/student/subscription
     *
     * مطابق لـ[Taqdar::subscription()](Taqdar.php) وشاشتها.
     *
     * والاشتراك المعروض ليس النشط وحده: المعلق تنتظره فاتورة، والمنتهي
     * يحتاج صاحبه أن يعرف أنه انتهى — لا أن يقال له «لا اشتراك لك».
     *
     * و**الحال الفعلية لا المخزنة**: الكرون يمر ليلا والطالب يقرأ الآن،
     * فاشتراك انقضى أمس حاله `active` في الجدول ويقرأ هنا `expired`.
     */
    public function student_subscription()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $this->load->model('taqdar_billing_model', 'tq_bill');
        $this->load->model('taqdar_tap_model', 'tq_tap');

        $cur = $this->tq_bill->active_subscription($uid);
        if (!$cur) {
            $cur = $this->db->where('user_id', $uid)->order_by('id', 'DESC')->limit(1)
                            ->get('subscriptions')->row_array();
        }

        $sub  = null;
        $plan = null;

        if ($cur) {
            $plan = $this->tq_bill->plan($cur['plan_id']);

            $eff = (string) $cur['status'];
            if (in_array($eff, array('active', 'cancelled'), true)
                && !empty($cur['ends_at']) && strtotime($cur['ends_at']) < time()) {
                $eff = 'expired';
            }

            $labels = array('pending' => 'بانتظار السداد', 'active' => 'نشط',
                            'cancelled' => 'ملغى التجديد', 'expired' => 'منته');

            $days_left = null;
            if (!empty($cur['ends_at']) && $eff !== 'expired') {
                $days_left = max(0, (int) ceil((strtotime($cur['ends_at']) - time()) / 86400));
            }

            $sub = array(
                'id'            => (int) $cur['id'],
                'status'        => $eff,
                'status_stored' => (string) $cur['status'],
                'status_label'  => $labels[$eff] ?? $eff,
                'price'         => tq_api_money($cur['price']),
                'started_at'    => tq_api_date($cur['started_at']),
                'ends_at'       => tq_api_date($cur['ends_at']),
                'days_left'     => $days_left,
                'auto_renew'    => ((int) $cur['auto_renew'] === 1),
                'method'        => $cur['method'],
                'cancelled_at'  => tq_api_date($cur['cancelled_at']),
                'created_at'    => tq_api_date($cur['created_at']),
                'plan'          => $plan ? $this->plan_out($plan) : null,
            );
        }

        $invoices = $this->tq_bill->invoices_of($uid);

        /* أول فاتورة `unpaid` بالحرف — لا «ليست مدفوعة»: المستردة ليست
           مدفوعة أيضا، ولا يطلب من صاحبها أن يحول قيمتها من جديد. */
        $due = null;
        foreach ((array) $invoices as $i) {
            if ($i['status'] === 'unpaid') { $due = $i; break; }
        }

        $level = null;
        try {
            $this->load->model('taqdar_diag_model', 'tq_diag');
            $level = $this->tq_diag->latest_result($uid);
        } catch (Throwable $e) {}

        /* TQ-MULTI-SUB — **وما يملكه غير باقته.**
           `active_subscription()` بالمفرد ترد صفا واحدا و**تتخطى الشراء
           المفرد عمدا**: أحد عشر مستدعيا يسألونها «ما باقة هذا الطالب؟».
           فالحقل `subscription` يبقى جواب ذلك السؤال، و`purchases` جواب
           السؤال الآخر — «ماذا يملك؟». ومن له باقة صف واشترى فوقها مادة
           يملك صفين، وصف واحد يقرأ يعني أن أحد الشراءين لا يظهر لصاحبه
           في شاشة واحدة: بابا مقفلا في العرض على من دفع ثمنه. */
        $purchases = array();
        foreach ((array) $this->tq_bill->active_subscriptions($uid) as $row) {
            $purchases[] = $this->purchase_out($row);
        }

        $out = array(
            'subscription'  => $sub,
            'purchases'     => $purchases,
            'due_invoice'   => $due ? $this->invoice_out($due) : null,
            'invoices'      => array_map(array($this, 'invoice_out'), array_slice((array) $invoices, 0, 20)),
            'payment'       => array(
                'card_enabled'  => (bool) $this->tq_tap->ready(),
                'card_is_test'  => (bool) $this->tq_tap->is_test_ready(),
                'bank_transfer' => $this->bank_out($due ? (string) $due['invoice_no'] : null),
            ),
            /* العمود `result_level` لا `level` — انظر مخطط
               `tq_diag_attempts` في `Taqdar_diag_model::ensure_schema()`.
               واسم مخترع هنا يرد `null` صامتا على كل طالب أدى الاختبار. */
            'placement_level' => $level ? array(
                'level'    => $level['result_level'],
                'score'    => (int) $level['score'],
                'total'    => (int) $level['total'],
                'taken_at' => tq_api_date($level['submitted_at']),
            ) : null,
        );

        /* محتوى الباقة **مستنتج لا مسرود** (CLAUDE.md): السلسلة
           `plans.scope_ids → grades → paths → course → section → lesson`.
           وهي ست استعلامات، فلا تحسب إلا لمن طلبها بـ`?include=contents`. */
        if (strpos((string) $this->input->get('include'), 'contents') !== false && $plan) {
            $this->load->model('taqdar_site_model', 'tq_site');
            $bundle = $this->tq_site->bundle_by_code($plan['code']);
            $out['contents'] = $bundle ? array(
                'totals'   => $bundle['totals'],
                'features' => $bundle['features'],
                'grades'   => array_values($bundle['grades']),
            ) : null;
        }

        $this->read($out, '', array(), $h);
    }

    /**
     * شكل الباقة — **وبدوراتها** (TQ-CYCLE-BUY).
     *
     * `price` سعر صف الباقة، و`cycles` ما يشترى فعلا: الشهري والسنوي
     * سعران لصف واحد لا صفان (صفان يحملان رقمين لحقيقة واحدة يفترقان
     * أول ما يعدل السنوي، ولا شيء يقول أيهما الصحيح). ومفتاح الدورة هو
     * ما يرسله التطبيق في `POST /student/subscribe` — وواجهة تعرض سعر
     * الشهر ثم تشتري بلا مفتاح تجعل من ضغط «شهري» يدفع سعر السنة.
     */
    private function plan_out($p)
    {
        $features = array();
        if (!empty($p['features'])) {
            $d = json_decode($p['features'], true);
            if (is_array($d)) $features = $d;
        }

        $this->load->model('taqdar_billing_model', 'tq_bill');
        $cycles = array();
        foreach ((array) $this->tq_bill->plan_cycles($p) as $k => $c) {
            $cycles[] = array(
                'key'     => (string) $c['key'],
                'label'   => (string) $c['label'],
                'unit'    => (string) $c['unit'],
                'price'   => tq_api_money($c['price']),
                'days'    => (int) $c['days'],
                'default' => (bool) $c['default'],
            );
        }

        return array(
            'id'       => (int) $p['id'],
            'code'     => (string) $p['code'],
            'name'     => (string) $p['name_ar'],
            'note'     => (string) $p['note'],
            'price'    => tq_api_money($p['price']),
            'period'   => (string) $p['period'],
            'duration_days' => (int) $p['duration_days'],
            'scope'    => (string) $p['scope'],
            'stage'    => $p['stage'],
            'is_trial' => ((string) $p['scope'] === 'trial'),
            'cycles'   => $cycles,
            'features' => $features,
            'cover_url'=> function_exists('tqs_plan_cover') ? tqs_plan_cover($p) : null,
            'web_url'  => base_url('plan/' . rawurlencode((string) $p['code'])),
        );
    }

    /**
     * شكل الفاتورة — **ومعها اسم ما بيع**.
     *
     * ومن يقرأ اسم ما بيع يقرأ الثلاثة: الباقة والمسار والكورس المفرد.
     * والضم على `plans` وحده يطبع «—» على شراء مسار أو كورس — وقد كان
     * يفعل في شاشة الاشتراكات وفي إشعار إصدار الفاتورة وفي إشعار نجاح
     * الدفع وفي التفعيل اليدوي. وفاتورة بلا اسم لما اشتري تجعل صاحبها
     * يحول مبلغا لا يعرف مقابله.
     *
     * و`subscription_id = 0` ليست عيبا: **فاتورة الحصة يتيمة** بحكم
     * `Taqdar_sessions_model` (وبها يفترق مسار التسوية)، فتخرج `item`
     * نوعها `session` لا `null` يقرؤه التطبيق فراغا.
     */
    private function invoice_out($i)
    {
        $labels = array('unpaid' => 'غير مدفوعة', 'paid' => 'مدفوعة', 'refunded' => 'مستردة');
        return array(
            'item'         => $this->invoice_item($i),
            'id'           => (int) $i['id'],
            'invoice_no'   => (string) $i['invoice_no'],
            'status'       => (string) $i['status'],
            'status_label' => $labels[$i['status']] ?? $i['status'],
            'amount'       => tq_api_money($i['amount']),
            'tax'          => tq_api_money($i['tax']),
            'total'        => tq_api_money($i['total']),
            'method'       => $i['method'],
            'issued_at'    => tq_api_date($i['issued_at']),
            'paid_at'      => tq_api_date($i['paid_at']),
            'payable'      => ($i['status'] === 'unpaid'),
        );
    }

    /** ما اشترته هذه الفاتورة: باقة أو مسار أو كورس مفرد أو حصة. */
    private function invoice_item($i)
    {
        $sid = (int) ($i['subscription_id'] ?? 0);

        if ($sid <= 0) {
            /* الفاتورة اليتيمة حصة خاصة — و`by_invoice()` هي التي تعرف،
               وهي الفرع نفسه الذي تفرعه `Taqdar_tap_model::settle()`. */
            $this->load->model('taqdar_sessions_model', 'tq_sess');
            $row = $this->tq_sess->by_invoice((int) $i['id']);
            return $row
                ? array('kind' => 'session', 'ref_id' => (int) $row['id'], 'title' => t('حصة خاصة'))
                : null;
        }

        $s = $this->db->select('plan_id, path_id, course_id')->where('id', $sid)
                      ->get('subscriptions')->row_array();
        if (!$s) return null;

        if ((int) ($s['course_id'] ?? 0) > 0) {
            return array('kind' => 'course', 'ref_id' => (int) $s['course_id'],
                'title' => (string) $this->db->select('title')->where('id', (int) $s['course_id'])
                                             ->get('course')->row('title'));
        }
        if ((int) ($s['path_id'] ?? 0) > 0) {
            return array('kind' => 'path', 'ref_id' => (int) $s['path_id'],
                'title' => (string) $this->db->select('title')->where('id', (int) $s['path_id'])
                                             ->get('paths')->row('title'));
        }

        $this->load->model('taqdar_billing_model', 'tq_bill');
        $plan = $this->tq_bill->plan((int) $s['plan_id']);
        return array('kind' => 'plan', 'ref_id' => (int) $s['plan_id'],
                     'title' => $plan ? (string) $plan['name_ar'] : '');
    }

    /**
     * تعليمات الحوالة البنكية.
     *
     * من `tqs_bank()` لا من `get_settings` مباشرة: تلك الدالة هي التي
     * تعرف أن الحساب لا يعرض إلا باكتمال الآيبان **والمستفيد** معا —
     * فآيبان بلا اسم مستفيد حوالة ترد. وقراءة المفاتيح هنا من جديد تعني
     * أن التطبيق يعرض ما يخفيه الموقع.
     *
     * و`reference` رقم الفاتورة: بدونه تصل حوالة بلا اسم يطابق، فيفتح
     * الاشتراك بالتخمين أو لا يفتح.
     */
    private function bank_out($invoice_no = null)
    {
        $b = function_exists('tqs_bank') ? tqs_bank() : null;

        if (!$b) return array('enabled' => false);

        return array(
            'enabled'      => true,
            'bank_name'    => $b['bank'],
            'beneficiary'  => $b['beneficiary'],
            'iban'         => $b['iban'],
            'instructions' => $b['note'],
            'reference'    => $invoice_no,
        );
    }

    /**
     * GET /api/v1/student/invoices — مرقمة.
     *
     * `invoices_of()` ترد الكل، والترقيم هنا فوقها: قائمة بلا حد على
     * حساب قديم ترد مئة صف في شاشة تعرض عشرة.
     */
    public function student_invoices()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->load->model('taqdar_billing_model', 'tq_bill');
        $all = (array) $this->tq_bill->invoices_of((int) $u['id']);

        $status = (string) $this->input->get('status');
        if (in_array($status, array('unpaid', 'paid', 'refunded'), true)) {
            $all = array_values(array_filter($all, function ($i) use ($status) {
                return $i['status'] === $status;
            }));
        }

        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 100, 20);

        $this->read(
            array_map(array($this, 'invoice_out'), array_slice($all, $offset, $per)),
            '',
            tq_api_meta_page($page, $per, count($all)),
            $h
        );
    }

    /** GET /api/v1/student/invoices/{id} */
    public function student_invoice($id = 0)
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $inv = $this->owned_invoice((int) $id, (int) $u['id']);

        $out = $this->invoice_out($inv);

        if ((int) $inv['subscription_id'] > 0) {
            $this->load->model('taqdar_billing_model', 'tq_bill');
            $sub = $this->tq_bill->subscription((int) $inv['subscription_id']);
            if ($sub) {
                $plan = $this->tq_bill->plan($sub['plan_id']);
                $out['subscription'] = array(
                    'id'     => (int) $sub['id'],
                    'status' => (string) $sub['status'],
                    'plan'   => $plan ? $this->plan_out($plan) : null,
                );
            }
        }

        $out['bank_transfer'] = $this->bank_out((string) $inv['invoice_no']);

        $this->read($out, '', array(), $h);
    }

    /**
     * الفاتورة بمعرفها **وبصاحبها معا**.
     *
     * ولا يفرق الرد بين «غير موجودة» و«ليست لك»: التفريق يجعل ترقيم
     * الفواتير عدادا يقرؤه أي مستخدم — يعرف كم فاتورة أصدرت المنصة.
     */
    private function owned_invoice($id, $uid)
    {
        $inv = $this->db->where('id', (int) $id)->where('user_id', (int) $uid)
                        ->get('invoices')->row_array();
        if (!$inv) $this->fail('لا فاتورة بهذا الرقم في حسابك.', 'not_found', 404);
        return $inv;
    }

    /**
     * POST /api/v1/student/subscription/cancel — إيقاف التجديد.
     *
     * لا إلغاء فوريا: الاشتراك يبقى صالحا إلى تاريخ انتهائه — وهو ما
     * تعد به الشاشة نصا، وما دفع لا يسحب بضغطة.
     */
    public function subscription_cancel()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $this->load->model('taqdar_billing_model', 'tq_bill');
        $sub = $this->tq_bill->active_subscription((int) $u['id']);

        if (!$sub) {
            $this->fail('لا اشتراك نشط في حسابك.', 'no_active_subscription', 409);
        }

        $this->tq_bill->cancel($sub['id'], 'ألغاه الطالب من التطبيق');
        $this->api->audit('api.subscription.cancel', (int) $u['id'],
                          array('subscription_id' => (int) $sub['id']));

        $fresh = $this->db->where('id', (int) $sub['id'])->get('subscriptions')->row_array();

        $this->respond(tq_api_ok(array(
            'id'         => (int) $fresh['id'],
            'status'     => (string) $fresh['status'],
            'ends_at'    => tq_api_date($fresh['ends_at']),
            'auto_renew' => false,
        ), 'أوقف التجديد — ويبقى اشتراكك صالحا حتى تاريخ انتهائه.'), 200);
    }

    /**
     * POST /api/v1/student/invoices/{id}/pay — يبدأ دفعة بالبطاقة.
     *
     * **يرد رابطا لا يقبض مالا.** صفحة تاب هي التي تأخذ البطاقة، ويفتحها
     * التطبيق في متصفح داخلي. وثلاثة أبواب تغلق الحلقة بعدها كما في
     * الويب حرفا: عودة المستخدم، والويبهوك لمن أغلق النافذة، و
     * `taqdar_cron reconcile` لمن لم يصله ويبهوك. فلا حاجة إلى أن يستفتي
     * التطبيق الخادم في حلقة — يسأل `/student/subscription` مرة عند
     * الرجوع ويجد الحال مستقرة.
     *
     * والمبلغ لا يقبل من العميل: `Taqdar_tap_model::start()` يقرأه من
     * الفاتورة ويكتب صف محاولة بقيمتها، وما ترده تاب يقابله — وإلا فلا
     * تفعيل. رقم يرسله التطبيق هنا لا يعني شيئا، وهذا مقصود.
     */
    public function invoice_pay($id = 0)
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid = (int) $u['id'];
        $inv = $this->owned_invoice((int) $id, $uid);

        if ($inv['status'] !== 'unpaid') {
            $this->fail('هذه الفاتورة ليست مستحقة السداد.', 'invoice_not_payable', 409);
        }

        $this->load->model('taqdar_tap_model', 'tq_tap');

        if (!$this->tq_tap->ready()) {
            $this->fail('الدفع بالبطاقة غير مفعل حاليا. حول قيمة الفاتورة بنكيا.',
                        'card_payment_disabled', 503);
        }

        $pay = $this->tq_tap->start((int) $inv['id'], $uid);

        if (empty($pay['ok'])) {
            $this->fail(implode(' ', (array) $pay['errors'])
                        . ' وفاتورتك صدرت، فيمكنك تحويل قيمتها بنكيا.',
                        'payment_start_failed', 502);
        }

        $this->api->audit('api.invoice.pay_start', $uid,
                          array('invoice_id' => (int) $inv['id']));

        $this->respond(tq_api_ok(array(
            'payment_url' => $pay['url'],
            'invoice'     => $this->invoice_out($inv),
            'note'        => 'افتح الرابط في متصفح داخلي. يفعل الاشتراك تلقائيا بعد نجاح الدفع.',
        ), 'جهزت صفحة الدفع.'), 200);
    }

    /* ================================================================
       أدوات داخلية
       ================================================================ */

    /**
     * يحقن جسم JSON في `$_POST`.
     *
     * `Taqdar_settings_model` يقرأ بـ`$this->input->post()` لأنه كتب
     * لشاشات الويب، وهو **القرار الصحيح**: قواعد التحقق موضع واحد لا
     * موضعان. فبدل نسخها هنا يجعل الجسم في متناوله.
     *
     * و`$this->input` يخبئ نسخته من `$_POST` عند أول قراءة، فيصفر خبيئته
     * كذلك — وإلا كتب النموذج قيم طلب سابق أو لا شيء.
     */
    private function as_post(array $data)
    {
        $_POST = $data;
        $_REQUEST = array_merge($_REQUEST, $data);

        /* CI3 لا يوفر تصفيرا معلنا لخبيئة المدخلات، والخاصية محمية —
           فتفتح بمرآة. والبديل نسخ قواعد التحقق كلها، وهو أسوأ بكثير. */
        try {
            $ref = new ReflectionClass($this->input);
            foreach (array('_input_stream', '_raw_input_stream') as $p) {
                if ($ref->hasProperty($p)) {
                    $prop = $ref->getProperty($p);
                    $prop->setAccessible(true);
                    $prop->setValue($this->input, null);
                }
            }
        } catch (Throwable $e) {
            // لا شيء: `$_POST` وحدها كافية في CI3 لأن `post()` تقرأ منها.
        }
    }

    /** رد موحد لنتيجة `Taqdar_settings_model`. */
    private function settings_result($r, $uid, $section, $data = null)
    {
        if (empty($r['ok'])) {
            $this->fail(implode(' ', (array) $r['errors']), 'validation_failed', 422,
                        array($section => (array) $r['errors']));
        }

        $this->api->audit('api.settings.' . $section, (int) $uid);

        if ($data === null) {
            $fresh = $this->db->where('id', (int) $uid)->get('users')->row_array();
            $data  = tq_api_user($fresh);
        }

        $this->respond(tq_api_ok($data, $r['message']), 200);
    }


    /* ================================================================
       الجسر إلى بوابة الإتقان
       ================================================================

       الوحدات الخمس التالية (الرئيسية · التعلم · الدرس · التقييم ·
       التمرين) لا تحمل قاعدة عمل واحدة: كلها تنادي `Taqdar_repo_model`
       و`Taqdar_learn_model` — الطبقة نفسها التي تناديها `Taqdar_gate`
       من الويب. فالقفل والتغطية والتباعد والاستحقاق تحسب مرة واحدة،
       ولا يقع أن يفتح التطبيق درسا يقفله الموقع.

       وما يضاف هنا شيئان لا ثالث لهما: ترجمة **الغلاف** (غلاف البوابة
       `{error:{code,…}}` إلى غلاف الواجهة `{message,code,errors}`)،
       وتشكيل **الأسماء** بما يقرؤه Dart. */

    /** النموذجان اللذان تقوم عليهما الوحدات الخمس. */
    private function repo()
    {
        $this->load->model('taqdar_repo_model', 'tq_repo');
        return $this->tq_repo;
    }

    private function learn()
    {
        $this->load->model('taqdar_learn_model', 'tq_learn');
        return $this->tq_learn;
    }

    /**
     * يترجم خطأ البوابة إلى غلاف الواجهة — أو يمرر النتيجة سليمة.
     *
     * رموز البوابة (`MASTERY_LOCKED` · `NOT_ENTITLED` · …) صارخة الحروف
     * وغلافها `{error:{code,message,message_ar,details}}`، وغلاف هذه
     * الواجهة `{message,code,errors}` ورموزه صغيرة. وتركهما يخرجان كما
     * هما يعني **غلافا ثالثا** في واجهة تعد بأن لا ثالث لها — وهو الوعد
     * الذي كتب `guard_fatals()` لأجله وحده.
     *
     * و`details` تخرج في `errors` ولا تلقى: `blocking_lesson_id` هو ما
     * يجعل التطبيق يقول «أكمل درس الكسور أولا» بدل «هذا الدرس مقفل» —
     * وهو الفرق بين رسالة تدل ورسالة تسد.
     */
    private function gate($result)
    {
        $repo = $this->repo();
        if (!$repo->is_error($result)) return $result;

        $e      = $result['error'];
        $code   = (string) $e['code'];
        $status = (int) $repo->http_status($code);

        $details = (array) $e['details'];
        $errors  = $details ? array('details' => $details) : array();

        $this->fail((string) $e['message_ar'], strtolower($code), $status, $errors);
    }

    /* ================================================================
       ١ · الرئيسية
       ================================================================ */

    /**
     * GET /api/v1/student/home
     *
     * شاشة الفتح — نداء واحد لا سبعة.
     *
     * التطبيق يفتح عليها في كل تشغيل، وهي في الويب تجمع سبعة مصادر:
     * الخطوة التالية والسلسلة وهدف اليوم وموضع التوقف والكورسات
     * والمواعيد والشارات. وسبعة نداءات لرسم شاشة واحدة تعني سبعة أشواط
     * على شبكة جوال ووميضا سبعة أضعاف — والمستخدم يقرأ ذلك بطئا لا
     * معمارا.
     *
     * و**الخطوة التالية من `next_step()` نفسها** التي تقرأ منها الويب:
     * قاعدة «ما الذي يفعله الطالب الآن؟» فيها سبعة فروع مرتبة (تهيئة ·
     * مراجعة مستحقة · وضع امتحان · واجب · استكمال · درس تال · تصفح)،
     * ونسخة ثانية منها هنا تفترق عن أختها عند أول تعديل — فيقرأ الطالب
     * في التطبيق «ابدأ درسا جديدا» وفي الموقع «راجع أسئلة اليوم» في
     * اللحظة نفسها.
     *
     * و`web_url` يبقى كما ترده الطبقة: رابط ويب مطلق ينفع التطبيق متى
     * لم يعرف الشاشة. ومعه `kind` و`meta` وهما ما يوجه بهما التطبيق
     * نفسه إلى شاشته الأصيلة.
     */
    public function student_home()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid   = (int) $u['id'];
        $learn = $this->learn();
        $repo  = $this->repo();

        $step   = $learn->next_step($uid);
        $streak = $learn->streak($uid);

        /* الكورسات مختصرة لا كاملة: أربع بطاقات هي ما يعرض، والقائمة
           كلها لها `/student/courses`. */
        $courses = $this->courses_of($uid);

        /* موضع التوقف: أول كورس بدئ ولم يكتمل. و`next_lesson_id` يأتي من
           `path_progress()` — أي من `lesson_progress` نفسه الذي يقرؤه
           القفل، لا من `watch_histories`. انظر `courses_of()`. */
        $resume = null;
        foreach ($courses as $c) {
            if ($c['progress']['next_lesson_id'] && $c['progress']['percent'] > 0) {
                $resume = array(
                    'course_id' => $c['id'],
                    'course'    => $c['title'],
                    'lesson_id' => $c['progress']['next_lesson_id'],
                    'percent'   => $c['progress']['percent'],
                );
                break;
            }
        }

        $this->read(array(
            'user' => array(
                'id'         => $uid,
                'name'       => trim($u['first_name'] . ' ' . $u['last_name']),
                'avatar_url' => tq_api_avatar($u['image'] ?? ''),
            ),
            'next_step' => array(
                'kind'     => (string) $step['kind'],
                'title'    => (string) $step['title'],
                'subtitle' => (string) $step['subtitle'],
                'cta'      => (string) $step['cta'],
                'icon'     => (string) $step['icon'],
                'web_url'  => (string) $step['href'],
                'meta'     => (object) $step['meta'],
            ),
            'streak' => array(
                'days'  => (int) $streak['days'],
                'best'  => (int) $streak['best'],
                'today' => (bool) $streak['today'],
            ),
            'goal_today' => $learn->goal_today($uid),
            'exam_mode'  => $learn->exam_mode($uid),
            'resume'     => $resume,
            'courses'    => array_slice($courses, 0, 4),
            'deadlines'  => $this->deadlines_of($uid, 5),
            'badges'     => array(
                'reviews'       => (int) $repo->count_due_reviews($uid),
                'tasks'         => $this->pending_tasks_count($uid),
                'messages'      => (int) $this->db->where('receiver', $uid)
                                        ->where('read_status', 0)->count_all_results('message'),
                'notifications' => (int) $this->db->where('to_user', $uid)
                                        ->where('status', 0)->count_all_results('notifications'),
            ),
        ), '', array('courses_total' => count($courses)), $h);
    }

    /**
     * المواعيد القريبة — الواجبات غير المسلمة، الأقرب أولا.
     *
     * لا جدول مواعيد في القاعدة: الواجب **هو** الموعد، وتاريخه `due_at`
     * إن كتب. وما لا تاريخ له يذهب إلى الذيل لا يسقط — واجب بلا موعد
     * واجب قائم.
     */
    private function deadlines_of($uid, $limit = 5)
    {
        try {
            $rows = $this->db->query(
                'SELECT a.`id`, a.`type`, a.`due_at`,
                        l.`id` AS lesson_id, l.`title` AS lesson_title,
                        c.`id` AS course_id, c.`title` AS course_title
                   FROM `assessments` a
                   JOIN `lesson` l ON l.`id` = a.`lesson_id`
                   JOIN `course` c ON c.`id` = l.`course_id`
                   JOIN `enrol`  e ON e.`course_id` = c.`id` AND e.`user_id` = ?
                  WHERE a.`type` = "homework"
                    AND NOT EXISTS (SELECT 1 FROM `attempts` t
                                     WHERE t.`assessment_id` = a.`id` AND t.`student_id` = ?
                                       AND t.`submitted_at` IS NOT NULL)
                  ORDER BY (a.`due_at` IS NULL) ASC, a.`due_at` ASC, a.`id` ASC
                  LIMIT ' . (int) $limit,
                array((int) $uid, (int) $uid))->result_array();
        } catch (Throwable $e) {
            return array();     // قائمة ناقصة أهون من شاشة لا تفتح
        }

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'assessment_id' => (int) $r['id'],
                'kind'          => (string) $r['type'],
                'title'         => (string) $r['lesson_title'],
                'lesson_id'     => (int) $r['lesson_id'],
                'course'        => array('id' => (int) $r['course_id'], 'title' => $r['course_title']),
                'due_at'        => tq_api_date($r['due_at'] ?? null),
            );
        }
        return $out;
    }

    /** عدد الواجبات المعلقة — الاستعلام نفسه الذي تعد به `Taqdar::counts()`. */
    private function pending_tasks_count($uid)
    {
        try {
            return (int) $this->db->query(
                'SELECT COUNT(*) AS n
                   FROM `assessments` a
                   JOIN `lesson` l ON l.`id` = a.`lesson_id`
                   JOIN `enrol`  e ON e.`course_id` = l.`course_id` AND e.`user_id` = ?
                  WHERE a.`type` = "homework"
                    AND NOT EXISTS (SELECT 1 FROM `attempts` t
                                     WHERE t.`assessment_id` = a.`id` AND t.`student_id` = ?
                                       AND t.`submitted_at` IS NOT NULL)',
                array((int) $uid, (int) $uid))->row('n');
        } catch (Throwable $e) {
            return 0;
        }
    }

    /* ================================================================
       ٢ · التعلم — الكورسات والمنهج والدروس
       ================================================================ */

    /**
     * GET /api/v1/student/courses
     *
     * كورسات الطالب وتقدمه فيها.
     *
     * **والتقدم يقرأ من `lesson_progress` لا من `watch_histories`.**
     * وهذا فرق يهم: شاشة «كورساتي» في الويب تقرأ `course_progress`
     * و`completed_lesson` من `watch_histories` — وهو عمود Academy
     * الموروث يكتبه المشغل القديم — بينما القفل ونسبة الدرس وبوابة
     * الإتقان كلها تقرأ `lesson_progress`. ورقمان يفترقان يجعلان
     * «٪١٠٠» تقف أمام درس مقفل (CLAUDE.md، المشغل والتقدم). فالواجهة
     * تقرأ ما يقرؤه القفل، ولا تنقل الانقسام إلى التطبيق.
     *
     * والمرشحات في الخادم كما في الكتالوج: `state` و`q` معاملات `GET`،
     * فالرابط يحمل الحال ويعمل الترقيم فوقه بلا أن ينسى البحث.
     */
    public function student_courses()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $all = $this->courses_of((int) $u['id']);

        $state = (string) $this->input->get('state');
        if (!in_array($state, array('progress', 'done', 'idle'), true)) $state = '';

        $q = trim((string) $this->input->get('q'));

        $list = array();
        foreach ($all as $c) {
            if ($state !== '' && $c['status'] !== $state) continue;
            if ($q !== '' && mb_stripos($c['title'], $q) === false
                          && mb_stripos((string) $c['subject'], $q) === false) continue;
            $list[] = $c;
        }

        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 50, 20);

        $by_state = array('all' => count($all), 'progress' => 0, 'done' => 0, 'idle' => 0);
        foreach ($all as $c) $by_state[$c['status']]++;

        $this->read(array_slice($list, $offset, $per), '',
            array_merge(
                tq_api_meta_page($page, $per, count($list)),
                array('counts' => $by_state, 'filters' => array('state' => $state, 'q' => $q))
            ), $h);
    }

    /**
     * كورسات الطالب — مصدر واحد لثلاث نقاط (الرئيسية والقائمة والمنهج).
     *
     * والاستحقاق من `enrol` هنا لا من `subscription_grants()`: هذه
     * **قائمة** لا **وصول**، والقائمة هي ما يجسده `sync_enrolments()`
     * (TQ-ENROL-STALE). ومن كان اشتراكه أحدث من آخر تجسيد يقرأ كورسا
     * ناقصا هنا ويفتحه هناك — وذلك عيب التجسيد لا عيب هذه النقطة،
     * و`taqdar_cron enrolments` تصلحه في نصف ساعة.
     */
    private function courses_of($uid)
    {
        static $cache = array();
        $uid = (int) $uid;
        if (isset($cache[$uid])) return $cache[$uid];

        $repo = $this->repo();

        $rows = $this->db->select('c.id, c.title, c.thumbnail, c.level, c.category_id,'
                        . ' c.short_description, e.date_added AS enrolled_at,'
                        . ' p.id AS path_id, s.name_ar AS subject_ar,'
                        . ' TRIM(CONCAT(COALESCE(t.first_name,""), " ", COALESCE(t.last_name,""))) AS teacher_name')
                ->from('enrol e')
                ->join('course c', 'c.id = e.course_id', 'inner')
                ->join('paths p', 'p.course_id = c.id', 'left')
                ->join('subjects s', 's.id = p.subject_id', 'left')
                ->join('users t', 't.id = c.user_id', 'left')
                ->where('e.user_id', $uid)
                ->group_by('c.id')
                ->order_by('e.date_added', 'DESC')
                ->get()->result_array();

        $out = array();
        foreach ($rows as $r) {
            $cid = (int) $r['id'];
            $pr  = $repo->path_progress($uid, $cid);

            $out[] = array(
                'id'          => $cid,
                'title'       => (string) $r['title'],
                'subject'     => $r['subject_ar'] ?: null,
                'level'       => (string) $r['level'],
                'summary'     => (string) $r['short_description'],
                'teacher'     => trim((string) $r['teacher_name']) ?: null,
                'path_id'     => $r['path_id'] ? (int) $r['path_id'] : null,
                'thumbnail'   => $this->thumb_url($r['thumbnail']),
                'enrolled_at' => tq_api_date($r['enrolled_at']),
                'progress'    => array(
                    'total_lessons'  => (int) $pr['total_lessons'],
                    'completed'      => (int) $pr['completed'],
                    'mastered'       => (int) $pr['mastered'],
                    'percent'        => (int) $pr['percent'],
                    'next_lesson_id' => $pr['next_lesson_id'] ? (int) $pr['next_lesson_id'] : null,
                ),
                'status' => $pr['percent'] >= 100 ? 'done'
                          : ($pr['percent'] > 0 ? 'progress' : 'idle'),
            );
        }

        return $cache[$uid] = $out;
    }

    /**
     * رابط الغلاف كاملا لا رمزا.
     *
     * `course.thumbnail` يخزن **اسم ملف** لا مسارا، وموضعه
     * `uploads/thumbnails/course_thumbnails/` — والقاعدة نفسها في
     * `tq_s_cover()` بالويب. والتطبيق لا يملك أن يعرفها، وهو الدرس نفسه
     * الذي علمته `tq_api_avatar()`: رمز بلا امتداد كسر عشر شاشات قبل
     * `tqs_person_img`.
     *
     * و`is_file()` قبل الرد: صف يحمل اسم ملف حذف يعطي رابطا يرد 404،
     * وصورة مكسورة في البطاقة أسوأ من غلاف بديل. فالمعدوم يرد **بديل
     * المنصة** لا `null` — فلا يحتاج التطبيق فرعا لغلاف غائب.
     */
    private function thumb_url($raw)
    {
        $fallback = base_url('assets/taqdar/brand/course-placeholder.png');

        $t = trim((string) $raw);
        if ($t === '') return $fallback;
        if (filter_var($t, FILTER_VALIDATE_URL)) return $t;

        $rel = (strpos($t, 'uploads/') === 0)
             ? $t
             : 'uploads/thumbnails/course_thumbnails/' . $t;

        return is_file(FCPATH . $rel) ? base_url($rel) : $fallback;
    }

    /**
     * GET /api/v1/student/courses/{id}
     *
     * منهج الكورس: وحدات فدروسا، ومع كل درس **حال قفله**.
     *
     * والقفل يحسب هنا لا في التطبيق: `lesson_lock_state()` تقرأ ترتيب
     * الدروس وإتقان ما قبلها وأي درس يحجب — وقاعدة ثانية في Dart تعني
     * شاشة تعرض قفلا يفتحه الخادم أو تعرض فتحا يقفله. والقفل قفل: من
     * فتح درسا مقفلا برقمه يرد عليه `get_lesson()` بـ`mastery_locked`
     * على كل حال، فما هنا **عرض** لا حراسة.
     */
    public function student_course($id = 0)
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $cid = (int) $id;

        $course = $this->db->select('c.id, c.title, c.thumbnail, c.level, c.short_description,'
                          . ' c.description, c.video_url,'
                          . ' TRIM(CONCAT(COALESCE(t.first_name,""), " ", COALESCE(t.last_name,""))) AS teacher_name,'
                          . ' t.id AS teacher_id')
                  ->from('course c')
                  ->join('users t', 't.id = c.user_id', 'left')
                  ->where('c.id', $cid)->get()->row_array();

        if (!$course) $this->fail('لا كورس بهذا الرقم.', 'not_found', 404);

        $repo = $this->repo();
        if (!$repo->is_entitled($uid, $cid)) {
            /* TQ-COURSE-SALE — **والرفض يقول كيف يفتح.**
               كان الرد بابا مقفلا وحده: «غير متاح ضمن اشتراكك» ولا شيء
               بعده. والكورس قد يكون **معروضا للبيع مفردا** — فمن أراد
               مادة واحدة لا منهج مرحلة يقرأ رفضا ولا يجد ما يشتريه،
               فيدفع ثمن الباقة أو ينصرف؛ والثاني هو ما يقع. فيخرج العرض
               مع الرفض، ويفتح التطبيق شاشة الشراء من الرد نفسه.

               وخروجه في `errors.details` لا في `data`: الغلاف ثابت بلا
               استثناء، ورد خطأ يحمل `data` هو الغلاف الثالث الذي تعد
               هذه الواجهة بألا ثالث لها. */
            $details = array('course_id' => $cid);
            try {
                $this->load->model('taqdar_course_sale_model', 'tq_cs');
                $offer = $this->tq_cs->offer($cid);
                if (!empty($offer['sellable'])) $details['offer'] = $this->offer_out($offer, $uid);
            } catch (Throwable $e) {
                /* بيع الكورسات مطفأ أو جدوله لم ينشأ بعد: الرفض يبقى
                   رفضا كما كان، ولا يسقط على استثناء عرض. */
                $this->db->reset_query();
            }

            $this->fail('هذا المحتوى غير متاح ضمن اشتراكك.', 'not_entitled', 403,
                        array('details' => $details));
        }

        /* `ordered_lessons()` هي ترتيب القفل نفسه — الوحدة فالترتيب فالمعرف.
           وترتيب ثان هنا يجعل «الدرس التالي» في الشاشة غير «الدرس التالي»
           في البوابة. */
        $lessons = $repo->ordered_lessons($cid);

        $section_ids = array();
        foreach ($lessons as $l) $section_ids[(int) $l['section_id']] = true;

        $sections = array();
        if ($section_ids) {
            foreach ($this->db->select('id, title, order')
                        ->where_in('id', array_keys($section_ids))
                        ->order_by('order', 'ASC')->order_by('id', 'ASC')
                        ->get('section')->result_array() as $s) {
                $sections[(int) $s['id']] = array(
                    'id' => (int) $s['id'], 'title' => (string) $s['title'], 'lessons' => array(),
                );
            }
        }
        /* درس بلا وحدة لا يسقط: الصف قد يحمل `section_id` صفرا أو معرفا
           لوحدة حذفت، ودرس لا يظهر في المنهج أسوأ من وحدة بلا اسم. */
        $sections[0] = array('id' => 0, 'title' => 'دروس عامة', 'lessons' => array());

        $prog = array();
        foreach ($this->db->where('student_id', $uid)
                    ->where('course_id', $cid)
                    ->select('lp.*', false)
                    ->from('lesson_progress lp')
                    ->join('lesson l', 'l.id = lp.lesson_id', 'inner')
                    ->get()->result_array() as $p) {
            $prog[(int) $p['lesson_id']] = $p;
        }

        foreach ($lessons as $l) {
            $lid = (int) $l['id'];
            $sid = isset($sections[(int) $l['section_id']]) ? (int) $l['section_id'] : 0;
            $st  = $repo->lesson_lock_state($uid, $lid);
            $p   = isset($prog[$lid]) ? $prog[$lid] : null;

            $sections[$sid]['lessons'][] = array(
                'id'           => $lid,
                'title'        => (string) $l['title'],
                'lesson_type'  => (string) $l['lesson_type'],
                'duration_sec' => (int) $repo->lesson_duration($l),
                'is_free'      => ((int) $l['is_free'] === 1),
                'trackable'    => (bool) $repo->trackable($l),
                'has_quiz'     => (bool) $repo->review_assessment($lid, false),
                'unlocked'     => !empty($st['unlocked']),
                'lock_reason'  => $st['reason'],
                'blocking_lesson_id' => isset($st['blocking_lesson_id']) && $st['blocking_lesson_id']
                                        ? (int) $st['blocking_lesson_id'] : null,
                'completed_at' => $p ? tq_api_date($p['completed_at']) : null,
                'mastered_at'  => $p ? tq_api_date($p['mastered_at'])  : null,
                'position_sec' => $p ? (int) $p['position_sec'] : 0,
            );
        }

        $out = array();
        foreach ($sections as $s) {
            if (!$s['lessons']) continue;      // وحدة فارغة لا تعرض
            $out[] = $s;
        }

        $pr = $repo->path_progress($uid, $cid);

        $this->read(array(
            'course' => array(
                'id'        => (int) $course['id'],
                'title'     => (string) $course['title'],
                'level'     => (string) $course['level'],
                'summary'   => (string) $course['short_description'],
                'about'     => (string) $course['description'],
                'preview'   => trim((string) $course['video_url']) ?: null,
                'thumbnail' => $this->thumb_url($course['thumbnail']),
                'teacher'   => array(
                    'id'   => $course['teacher_id'] ? (int) $course['teacher_id'] : null,
                    'name' => trim((string) $course['teacher_name']) ?: null,
                ),
            ),
            'progress' => array(
                'total_lessons'  => (int) $pr['total_lessons'],
                'completed'      => (int) $pr['completed'],
                'mastered'       => (int) $pr['mastered'],
                'percent'        => (int) $pr['percent'],
                'next_lesson_id' => $pr['next_lesson_id'] ? (int) $pr['next_lesson_id'] : null,
            ),
            'sections' => $out,
        ), '', array('sections' => count($out), 'lessons' => count($lessons)), $h);
    }

    /**
     * GET /api/v1/student/lessons
     *
     * الدروس أنفسها لا الكورسات — والفرق ليس تسمية.
     *
     * كانت شاشة «دروسي» في الويب تعرض بطاقات كورسات، فلم يكن في البوابة
     * كلها مدخل إلى **درس** بعينه: من أراد «درس الكسور» فتح الكورس ومسح
     * منهجه بعينه. وهذه النقطة تقرأ الدرس وحدة صف، وترشح بالكورس
     * وبالحالة وبالنص.
     *
     * والاختبارات (`lesson_type = 'quiz'`) تستثنى: لها `/student/exams`،
     * وخلطها بالدروس يجعل «٣٥ من ١١٢ درسا» يخالف عداد الكورسات.
     */
    public function student_lessons()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid    = (int) $u['id'];
        $course = (int) $this->input->get('course_id');
        $state  = (string) $this->input->get('state');
        if (!in_array($state, array('done', 'current', 'todo'), true)) $state = '';
        $q = trim((string) $this->input->get('q'));

        $this->db->select('l.id, l.title, l.duration, l.duration_sec, l.lesson_type,'
                        . ' l.video_type, l.is_free, l.section_id, l.course_id,'
                        . ' sec.title AS unit, c.title AS course_title, c.level, c.thumbnail,'
                        . ' lp.completed_at, lp.mastered_at, lp.position_sec', false)
                 ->from('lesson l')
                 ->join('enrol e', 'e.course_id = l.course_id AND e.user_id = ' . $uid, 'inner')
                 ->join('course c', 'c.id = l.course_id', 'inner')
                 ->join('section sec', 'sec.id = l.section_id', 'left')
                 ->join('lesson_progress lp', 'lp.lesson_id = l.id AND lp.student_id = ' . $uid, 'left')
                 ->where('l.lesson_type !=', 'quiz');

        if ($course > 0) $this->db->where('l.course_id', $course);
        if ($q !== '')   $this->db->group_start()
                                  ->like('l.title', $q)
                                  ->or_like('c.title', $q)
                                  ->group_end();

        /* الحالة ترشح في الخادم: `completed_at` عمود، فالشرط عليه لا على
           صفوف تجلب ثم ترمى — وإلا كان ترقيم الصفحة يعد ما لا يعرض. */
        if ($state === 'done')    $this->db->where('lp.completed_at IS NOT NULL', null, false);
        if ($state === 'todo')    $this->db->where('lp.completed_at IS NULL', null, false);
        if ($state === 'current') $this->db->where('lp.completed_at IS NULL', null, false)
                                           ->where('lp.position_sec >', 0);

        $total = $this->db->count_all_results('', false);

        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 100, 20);

        $rows = $this->db->order_by('l.course_id', 'ASC')
                         ->order_by('l.section_id', 'ASC')
                         ->order_by('l.order', 'ASC')
                         ->order_by('l.id', 'ASC')
                         ->limit($per, $offset)->get()->result_array();

        $repo = $this->repo();
        $out  = array();
        foreach ($rows as $r) {
            $done = !empty($r['completed_at']);
            $out[] = array(
                'id'           => (int) $r['id'],
                'title'        => (string) $r['title'],
                'unit'         => $r['unit'] ?: null,
                'lesson_type'  => (string) $r['lesson_type'],
                'duration_sec' => (int) $repo->lesson_duration($r),
                'is_free'      => ((int) $r['is_free'] === 1),
                'course'       => array(
                    'id'    => (int) $r['course_id'],
                    'title' => (string) $r['course_title'],
                    'level' => (string) $r['level'],
                ),
                'thumbnail'    => $this->thumb_url($r['thumbnail']),
                'position_sec' => (int) $r['position_sec'],
                'completed_at' => tq_api_date($r['completed_at']),
                'mastered_at'  => tq_api_date($r['mastered_at']),
                'state'        => $done ? 'done'
                                : (((int) $r['position_sec'] > 0) ? 'current' : 'todo'),
            );
        }

        $this->read($out, '',
            array_merge(tq_api_meta_page($page, $per, $total),
                        array('filters' => array('course_id' => $course ?: null,
                                                 'state' => $state, 'q' => $q))), $h);
    }

    /* ================================================================
       ٣ · الدرس — المشغل والتقدم والملاحظات
       ================================================================ */

    /**
     * GET /api/v1/student/lessons/{id}
     *
     * الدرس الواحد: مصدر التشغيل والأهداف والتقدم وبطاقة الاختبار.
     *
     * والقرار كله من `Taqdar_repo_model::get_lesson()` — هي التي تفحص
     * الاستحقاق ثم القفل ثم ترد، **ولا ترد رابط تشغيل لمن لم يفتح له
     * الدرس** (ولا حتى ملخصه: القفل قفل). فالمتحكم هنا يترجم الغلاف
     * ويعيد التسمية، ولا يحكم في شيء.
     *
     * و`trackable` صفة الخادم لا صفة المشغل: «هل يعد هذا المصدر مقيسا؟»
     * وعليها يقرر التطبيق أيعرض شريط تقدم أم زر إقرار (TQ-BLIND).
     */
    public function student_lesson($id = 0)
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $r = $this->gate($this->repo()->get_lesson((int) $id, (int) $u['id']));

        $l = $r['lesson'];
        $p = $r['playback'];

        $this->read(array(
            'lesson' => array(
                'id'           => (int) $l['id'],
                'title'        => (string) $l['title'],
                'course_id'    => (int) $l['course_id'],
                'section_id'   => (int) $l['section_id'],
                'lesson_type'  => (string) $l['lesson_type'],
                'duration'     => (string) $l['duration'],
                'duration_sec' => (int) $l['duration_sec'],
                'summary'      => (string) $l['summary'],
                'is_free'      => ((int) $l['is_free'] === 1),
                'trackable'    => ((int) $l['trackable'] === 1),
            ),
            'playback' => $this->playback_out($p),
            'objectives' => array_map(function ($o) {
                return array(
                    'id'         => (int) $o['id'],
                    'text'       => (string) $o['text'],
                    'at_second'  => (int) $o['at_second'],
                );
            }, (array) $r['objectives']),
            'progress' => array(
                'position_sec'  => (int) $r['progress']['position_sec'],
                'watch_seconds' => (int) $r['progress']['watch_seconds'],
                'covered_sec'   => (int) $r['progress']['covered_sec'],
                'percent'       => (int) $r['progress']['percent'],
                'completed_at'  => tq_api_date($r['progress']['completed_at']),
                'mastered_at'   => tq_api_date($r['progress']['mastered_at']),
            ),
            'quiz' => $r['review'] ? array(
                'assessment_id'  => (int) $r['review']['assessment_id'],
                'question_count' => (int) $r['review']['question_count'],
                'pass_mark'      => (int) $r['review']['pass_mark'],
                'attempts'       => (int) $r['review']['attempts'],
            ) : null,
            'prev_lesson_id' => $r['prev_lesson_id'] ? (int) $r['prev_lesson_id'] : null,
            'next_lesson_id' => $r['next_lesson_id'] ? (int) $r['next_lesson_id'] : null,
        ), '', array(), $h);
    }

    /**
     * مصدر التشغيل كما يفهمه التطبيق.
     *
     * **والرابط الموقع يحول إلى نقطة هذه الواجهة لا إلى البوابة.**
     * `playback_for()` يبني `taqdar_gate/media/<token>` وهي تستوثق
     * بكعكة الجلسة (`$this->user_id()`) — والتطبيق لا يحمل كعكة، فكل
     * درس مرفوع (`file` · `system` · درايف) كان **يرد 401 على التطبيق
     * وحده** بينما يعمل في المتصفح. ويوتيوب وفيميو لا يوقعان
     * (`protection_for()` تعدهما `unprotected`) — فلو أن أول تجربة
     * وقعت على درس يوتيوب لما ظهر العطل إلا بعد النشر.
     *
     * والرمز نفسه لا يعاد توليده: هو موقع بـHMAC على
     * `(lesson_id، student_id، exp)`، فينقل كما هو ويفحص هناك.
     */
    private function playback_out($p)
    {
        $url = (string) $p['video_url'];

        if (($p['protection'] ?? '') === 'signed'
            && preg_match('~/taqdar_gate/media/([^/?#]+)~', $url, $m)) {
            $url = base_url('api/v1/student/media/' . $m[1]);
        }

        return array(
            'video_type' => (string) $p['video_type'],
            'video_url'  => $url,
            'audio_url'  => trim((string) $p['audio_url']) ?: null,
            'attachment' => trim((string) $p['attachment']) ?: null,
            'resume_at'  => (int) $p['resume_at'],
            'protection' => (string) $p['protection'],
            'expires_in' => isset($p['expires_in']) ? (int) $p['expires_in'] : null,
        );
    }

    /**
     * GET /api/v1/student/media/{token}
     *
     * يمرر المقطع المحمي لصاحبه — بترويسة `Authorization` لا بكعكة.
     *
     * وثلاثة فحوص لا اثنان: الرمز موقع (HMAC ويحمل صاحبه)، **وصاحبه هو
     * حامل رمز الدخول** (وإلا كفى أن يشارك طالب رمز مقطعه في محادثة)،
     * والدرس مفتوح له ومستحق الآن (الرمز يعيش خمس دقائق، وقد يفقد
     * الاستحقاق فيها).
     *
     * ولا يمر بـ`respond()`: هذه ترد **ملفا** بترويسات `Range`، وغلاف
     * JSON فوقه يفسد المقطع. والفشل وحده يرد بالغلاف.
     *
     * و`video_player` في Flutter يقبل `httpHeaders`، فالترويسة تصل.
     */
    public function student_media($token = '')
    {
        $this->method('GET');
        $u = $this->require_student();
        $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];

        $this->load->model('taqdar_studio_model', 'tq_studio');
        $lesson_id = (int) $this->tq_studio->verify($token, $uid);

        if (!$lesson_id) {
            $this->fail('انتهت صلاحية رابط التشغيل. أعد فتح الدرس.', 'media_token_expired', 403);
        }

        $repo = $this->repo();
        if (!$repo->is_lesson_unlocked($uid, $lesson_id)) {
            $this->fail('أكمل مراجعة الدرس السابق أولا.', 'mastery_locked', 403);
        }

        $lesson = $this->db->select('video_url, course_id, is_free')
                           ->where('id', $lesson_id)->get('lesson')->row_array();
        if (!$lesson) $this->fail('لا درس بهذا الرقم.', 'not_found', 404);

        if ((int) $lesson['is_free'] !== 1 && !$repo->is_entitled($uid, (int) $lesson['course_id'])) {
            $this->fail('هذا المحتوى غير متاح ضمن اشتراكك.', 'not_entitled', 403);
        }

        /* الملف داخل `uploads/` وحدها. و`realpath` قبل المقارنة لا بعدها:
           `../` في العمود يخرج من المجلد بلا هذا الفحص. */
        $rel  = ltrim(str_replace('\\', '/', (string) $lesson['video_url']), '/');
        $base = realpath(FCPATH . 'uploads');
        $path = realpath(FCPATH . $rel);

        if (!$base || !$path || strpos($path, $base) !== 0 || !is_file($path)) {
            $this->fail('ملف الدرس غير موجود.', 'not_found', 404);
        }

        $this->answered = true;                 // لا يكتب حارس الأخطاء فوق الملف
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $this->stream_file($path);
    }

    /**
     * GET /api/v1/student/books/{id}/file — ملف الكتاب للتطبيق.
     *
     * ═══ TQ-BOOK-GATE بوجهه الثاني ═══
     *
     * `book-file/<id>` في الويب يستوثق **بكعكة الجلسة**، والتطبيق بلا
     * كعكة. فكانت `student/library` تسلمه ذلك الرابط وتعده بأنه يفتح —
     * ويرد الحارس 403 على **كل كتاب مدفوع**: من اشترى كتابا بمئة وخمسين
     * يقرؤه في المتصفح ولا يفتحه في التطبيق، ولا رسالة تقول لماذا.
     *
     * ولم يظهر في التجربة الأولى لأن **المجاني يمر بلا تسجيل**
     * (`has_book()` ترده `true` قبل أن تسأل عن المستخدم)، وكل كتب
     * القاعدة كانت مجانية.
     *
     * وهو عين ما عولج في `playback_out()`: الرمز الموقع يحول إلى
     * `api/v1/student/media/<token>` لأن أصله يستوثق بالكعكة كذلك.
     *
     * ═══ والحكم من `has_book()` وحدها ═══
     *
     * هي التي يسألها حارس الويب، وهي التي تسأل هنا — فلا يفتح باب ما
     * يرده الآخر. ونسخة ثانية من قواعدها تفترق عند أول تعديل.
     */
    public function student_book_file($book_id = 0)
    {
        $this->method('GET');
        $u = $this->require_student();
        $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $book_id = (int) $book_id;
        $this->load->model('taqdar_book_model', 'tq_bk');
        $book = $this->tq_bk->book($book_id);

        if (!$book || (string) $book['status'] !== 'published') {
            $this->fail('لا كتاب بهذا الرقم.', 'not_found', 404);
        }

        $this->load->model('taqdar_billing_model', 'tq_bill');
        if (!$this->tq_bill->has_book((int) $u['id'], $book_id)) {
            /* و«غير مستحق» لا «غير موجود»: الكتاب معروض في المكتبة
               بسعره، والكذب عليه بـ404 يجعل التطبيق يخفيه بدل أن يعرض
               زر شرائه. */
            $this->fail('هذا الكتاب يفتح بشرائه أو بالاشتراك في باقة صفه.',
                        'not_entitled', 403);
        }

        /* الملف داخل `uploads/` وحدها، و`realpath` قبل المقارنة لا
           بعدها: `../` في العمود يخرج من المجلد بلا هذا الفحص. */
        $rel  = ltrim(str_replace(chr(92), '/', (string) $book['file']), '/');
        $base = realpath(FCPATH . 'uploads');
        $path = $rel !== '' ? realpath(FCPATH . $rel) : false;

        if (!$base || !$path || strpos($path, $base) !== 0 || !is_file($path)) {
            $this->fail('ملف هذا الكتاب غير موجود.', 'not_found', 404);
        }

        $this->answered = true;                 // لا يكتب حارس الأخطاء فوق الملف
        while (ob_get_level() > 0) { @ob_end_clean(); }
        $this->stream_file($path, 'application/pdf');
    }

    /**
     * يمرر ملفا مع دعم `Range` — ويخرج بعده. نسخة `Taqdar_gate::stream()` نفسها.
     *
     * و`$mime` يمرر صريحا لما ليس وسائط: الخريطة تحت ترتد إلى `video/mp4`
     * لكل امتداد لا تعرفه، فملف PDF يخرج معلنا أنه فيديو — و`nosniff`
     * أدناه يمنع المتصفح من تصحيح ذلك، فلا يفتح القارئ شيئا.
     */
    private function stream_file($path, $mime = null)
    {
        $size = filesize($path);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map  = array('webm' => 'video/webm', 'ogg' => 'video/ogg', 'ogv' => 'video/ogg',
                      'm4v' => 'video/mp4', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4');
        if ($mime === null) $mime = isset($map[$ext]) ? $map[$ext] : 'video/mp4';

        $start = 0;
        $end   = $size - 1;
        $partial = false;
        $range = (string) $this->input->server('HTTP_RANGE');

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
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('X-Request-Id: ' . $this->request_id);
        header('Content-Disposition: inline');

        $fp = fopen($path, 'rb');
        if (!$fp) { header('HTTP/1.1 500 Internal Server Error'); exit; }

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

    /**
     * POST /api/v1/student/lessons/{id}/progress
     *
     * نبضة المشاهدة — الموضع والزمن **ودلاء التغطية**.
     *
     * `covered` قائمة أرقام دلاء عشر ثوان مر عليها التشغيل منذ آخر نبضة
     * (`floor(sec/10)`)، وبها يقاس الإتمام لا بعداد يزيد: السحب إلى
     * النهاية لا يكمل درسا (TQ-COVERAGE). والحد مئة في النبضة كما في
     * البوابة — سد أمام حمولة تدعي تغطية درس كامل في نداء واحد.
     *
     * و`media_sec` طول المقطع كما أعلنه مشغل هذا الطالب. **وهو شهادة
     * لا حكم**: `save_progress()` تكتبه في `lesson_progress.media_sec`
     * لهذا الطالب وحده، ولا يصحح مدة الدرس إلا باتفاق شاهدين مستقلين
     * (TQ-DURATION). فطالب يعدل عميله يفسد رقمه هو ولا يفسده على زملائه.
     *
     * ورقم الدرس **من المسار لا من الجسم**: هو مورد النقطة، ونسخة ثانية
     * منه في الجسم تعني نداء يحفظ في درس غير الذي في رابطه.
     */
    public function lesson_progress($id = 0)
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();

        $covered = isset($b['covered']) && is_array($b['covered']) ? $b['covered'] : array();
        $covered = array_slice($covered, 0, 100);

        /* `duration_sec` يقبل باسمه القديم توافقا مع عميل الويب: معناهما
           واحد — ما أعلنه المشغل. */
        $media = (int) ($b['media_sec'] ?? 0);
        if ($media <= 0) $media = (int) ($b['duration_sec'] ?? 0);

        $r = $this->gate($this->repo()->save_progress(
            (int) $u['id'], (int) $id,
            (int) ($b['position_sec'] ?? 0),
            (int) ($b['watched_delta'] ?? 0),
            $covered, $media));

        $this->respond(tq_api_ok($this->progress_out($r)), 200);
    }

    /**
     * POST /api/v1/student/lessons/{id}/complete
     *
     * إقرار الطالب بإتمام درس **لا يقاس**.
     *
     * درايف والإطار الخارجي لا يعلنان موضعا، فلا شيء يقاس والبديل أن
     * يبقى التالي مقفلا إلى الأبد. و`confirm_complete()` ترفض الإقرار
     * على مصدر **يقاس** فلا يصير مخرجا من كل درس — إلا بشهادة عجز
     * يختمها الخادم (`blind_at`) ومهلة تمضي (TQ-BLIND).
     *
     * فالرفض هنا **جواب لا عطل**: التطبيق يقرؤه ويقول «حدث الصفحة» بدل
     * أن يعرض زرا يرد بالخطأ في كل ضغطة.
     */
    public function lesson_complete($id = 0)
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $r = $this->gate($this->repo()->confirm_complete((int) $u['id'], (int) $id));

        $this->respond(tq_api_ok($this->progress_out($r), 'سجل إتمامك للدرس.'), 200);
    }

    /** شكل واحد لرد النبضة والإقرار — فلا يفرع العميل على النقطة. */
    private function progress_out($r)
    {
        $r = (array) $r;
        return array(
            'lesson_id'    => isset($r['lesson_id']) ? (int) $r['lesson_id'] : null,
            'position_sec' => isset($r['position_sec']) ? (int) $r['position_sec'] : 0,
            'covered_sec'  => isset($r['covered_sec'])  ? (int) $r['covered_sec']  : 0,
            'duration_sec' => isset($r['duration_sec']) ? (int) $r['duration_sec'] : 0,
            'percent'      => isset($r['percent'])      ? (int) $r['percent']      : 0,
            'completed_at' => tq_api_date($r['completed_at'] ?? null),
            'mastered_at'  => tq_api_date($r['mastered_at'] ?? null),
            'declared'     => !empty($r['declared']),
            'blind'        => !empty($r['blind']),
            'can_declare'  => !empty($r['can_declare']),
        );
    }

    /**
     * GET · POST /api/v1/student/lessons/{id}/notes
     *
     * ملاحظات الطالب على الدرس، ولكل ملاحظة **ثانيتها**: «راجع الدقيقة
     * ٤:١٢» لا تعني شيئا بلا موضع، والملاحظة بلا موضع مفكرة لا أداة درس.
     */
    public function lesson_notes($id = 0)
    {
        $m = $this->method(array('GET', 'POST'));
        $u = $this->require_student();

        $uid = (int) $u['id'];
        $lid = (int) $id;

        /* الملكية أولا: الملاحظة تكتب على درس، ودرس لا يملكه صاحبها
           يجعل كتابة الملاحظة بابا يعرف به وجود الدرس ورقمه. */
        $this->gate($this->repo()->get_lesson($lid, $uid));

        if ($m === 'GET') {
            $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);
            $this->read($this->notes_out($this->learn()->notes($uid, $lid), $lid), '',
                        array('lesson_id' => $lid), $h);
        }

        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array('body' => 'required|max:2000'));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $this->learn()->add_note($uid, $lid, (int) ($b['at_second'] ?? 0), (string) $b['body']);

        $this->respond(tq_api_ok($this->notes_out($this->learn()->notes($uid, $lid), $lid),
                                 'حفظت ملاحظتك.'), 201);
    }

    /** DELETE /api/v1/student/notes/{id} */
    public function note_delete($id = 0)
    {
        $this->method(array('DELETE', 'POST'));
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        /* `delete_note()` تشترط الطالب في `WHERE` نفسه، فرقم مخمن لا يحذف
           ملاحظة غيره. والرد واحد للمحذوف وللمعدوم عمدا: التفريق يقول
           لمن خمن أن الرقم موجود. */
        $this->learn()->delete_note((int) $u['id'], (int) $id);

        $this->respond(tq_api_ok(null, 'حذفت الملاحظة.'), 200);
    }

    /**
     * شكل الملاحظة.
     *
     * `notes()` ترد `id` و`at_second` و`body` و`created_at` و`at_label`
     * — **ولا ترد `lesson_id`**: الاستعلام مرشح به أصلا فلا معنى لتكراره
     * في كل صف. فيؤخذ من الوسيط، ولا يقرأ من مفتاح غير موجود.
     *
     * و`at_label` (`04:12`) يعاد كما هي: التنسيق نفسه في التطبيق والويب،
     * ونسخة ثانية منه في Dart تكتب `4:12` وأخرى `04:12`.
     */
    private function notes_out($rows, $lesson_id)
    {
        $out = array();
        foreach ((array) $rows as $r) {
            $out[] = array(
                'id'         => (int) $r['id'],
                'lesson_id'  => (int) $lesson_id,
                'at_second'  => (int) $r['at_second'],
                'at_label'   => (string) ($r['at_label'] ?? ''),
                'body'       => (string) $r['body'],
                'created_at' => tq_api_date($r['created_at'] ?? null),
            );
        }
        return $out;
    }

    /* ================================================================
       ٤ · التقييم — اختبار الدرس والمحاولات
       ================================================================ */

    /**
     * POST /api/v1/student/lessons/{id}/quiz/start
     *
     * يفتح محاولة — أو **يستأنف المفتوحة**.
     *
     * `start_attempt()` تعيد المحاولة غير المسلمة إن وجدت بدل أن تفتح
     * ثانية: من أغلق التطبيق في منتصف الاختبار يعود إلى محاولته لا إلى
     * محاولة جديدة تعد عليه. وعلى الجوال هذا الحال هي الشائعة لا النادرة
     * — مكالمة واردة تكفي.
     *
     * والأسئلة تخرج **بلا إجاباتها الصحيحة**: `review_questions()` تحذفها،
     * والتصحيح في الخادم. وقائمة خيارات ومعها الصواب في الحمولة تجعل
     * الاختبار عرضا لا قياسا — ومن يفتح أدوات المطور يقرأها.
     */
    public function quiz_start($id = 0)
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $r = $this->gate($this->repo()->start_attempt((int) $u['id'], (int) $id));

        $this->respond(tq_api_ok(array(
            'attempt_id'     => (int) $r['attempt_id'],
            'attempt_no'     => (int) $r['attempt_no'],
            'assessment_id'  => (int) $r['assessment_id'],
            'lesson_id'      => (int) $r['lesson_id'],
            'pass_mark'      => (int) $r['pass_mark'],
            'time_limit_sec' => $r['time_limit_sec'] !== null ? (int) $r['time_limit_sec'] : null,
            'questions'      => $this->questions_out($r['questions']),
        )), 200);
    }

    /**
     * POST /api/v1/student/quiz/attempts/{id}/submit
     *
     * يسلم المحاولة ويرد **قرار البوابة** لا الدرجة وحدها.
     *
     * والقرار هو ما يرسم الشاشة التالية، وله ثلاثة وجوه بحسب رقم
     * المحاولة (`submit_attempt()`):
     *   · أتقن            ⇐ `mastered`، وفتح الدرس التالي
     *   · أخفق والأولى    ⇐ `retry` ومعه `seek_to` — ارجع إلى الدقيقة
     *   · أخفق والثانية   ⇐ `retry` ومعه شرح بديل
     *   · أخفق والثالثة   ⇐ `suggest_session` — حصة بالطلب، والقفل باق
     *
     * **ولا إجابات صحيحة في هذا الرد**: التلميح بالحل بعد التسليم
     * مباشرة يفسد المحاولة التالية. ومن أراد المراجعة فلها نقطتها،
     * ولا تفتح إلا على محاولة **مسلمة**.
     */
    public function quiz_submit($id = 0)
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $raw = $this->in('answers', array());
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($raw)) $raw = array();

        $answers = $this->answers_in($raw);

        /* شكل لم يفهم يقال، ولا يمرر صفرا.
           `submit_attempt()` تتخطى بصمت كل بند لا تفهمه — فحمولة بشكل
           آخر تصحح على أنها **صفر من ثلاثة**: الطالب أجاب إجاباته كلها
           صحيحة ويقرأ أنه رسب، ولا خطأ في أي موضع. وهو الصمت نفسه الذي
           تحذر منه CLAUDE.md في أول قواعد التوجيه، وقد وقع هنا فعلا في
           أول نداء جرب. */
        if ($raw && !$answers) {
            $this->fail('صيغة الإجابات غير مفهومة.', 'validation_failed', 422,
                array('answers' => array(
                    'أرسلها قائمة: [{"question_id":7781,"given":["٢٥"]}] — أو خريطة {"7781":["٢٥"]}.')));
        }

        $r = $this->gate($this->repo()->submit_attempt((int) $u['id'], (int) $id, $answers));

        $this->respond(tq_api_ok($r), 200);
    }

    /**
     * يقبل الشكلين ويرد الشكل الذي يفهمه النموذج.
     *
     * القياسي قائمة `[{question_id, given, took_ms}]` — وهو ما يرسله
     * عميل الويب وما تقرؤه `submit_attempt()`. والخريطة
     * `{"7781": ["٢٥"]}` أطبع في JSON وأول ما يكتبه من يقرأ الوثيقة،
     * فتقبل وتحول هنا. والتحويل في موضع واحد لا في النموذج: ذاك تناديه
     * الويب كذلك، وتوسيع مدخله يوسع ما يجب أن تفهمه شاشتان.
     *
     * و`given` يقبل نصا مفردا كما يقبل قائمة: سؤال باختيار واحد يرسل
     * `"٢٥"` طبعا، ورفضه لأنه ليس قائمة تحكم بلا فائدة.
     */
    private function answers_in($raw)
    {
        $out = array();

        foreach ($raw as $key => $item) {
            if (is_array($item) && isset($item['question_id'])) {
                $qid   = (int) $item['question_id'];
                $given = isset($item['given']) ? $item['given'] : null;
                $ms    = isset($item['took_ms']) ? (int) $item['took_ms'] : null;
            } elseif (is_numeric($key)  && !is_array($item)) {
                /* قائمة قيم عارية بلا معرفات: لا سبيل إلى معرفة أي سؤال
                   تخص — ورقم الفهرس ليس رقم السؤال. تهمل فيرد 422 أعلاه
                   بدل أن تصحح على أنها إجابة عن السؤال الأول. */
                continue;
            } else {
                $qid   = (int) $key;
                $given = $item;
                $ms    = null;
            }

            if ($qid <= 0) continue;

            if (is_string($given)) {
                $decoded = json_decode($given, true);
                $given   = is_array($decoded) ? $decoded : array($given);
            } elseif (!is_array($given)) {
                $given = ($given === null) ? array() : array($given);
            }

            $one = array('question_id' => $qid, 'given' => array_values($given));
            if ($ms !== null) $one['took_ms'] = max(0, $ms);
            $out[] = $one;
        }

        return $out;
    }

    /**
     * GET /api/v1/student/quiz/attempts/{id}
     *
     * مراجعة محاولة **مسلمة** — وهنا وحدها تخرج الإجابات الصحيحة.
     *
     * منفصلة عن التسليم عمدا لا تكاسلا: رد التسليم يبقى بلا حل، وهذه
     * طلب ثان يختاره الطالب بعد أن ينتهي. و`attempt_review()` تفحص
     * صاحب المحاولة وتفحص أنها سلمت — فرقم مخمن لا يقرأ إجابات غيره،
     * ومحاولة مفتوحة لا تقرأ حلها.
     */
    public function quiz_attempt($id = 0)
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $r = $this->gate($this->repo()->attempt_review((int) $u['id'], (int) $id));

        unset($r['ok']);
        $this->read($r, '', array(), $h);
    }

    /**
     * GET /api/v1/student/exams
     *
     * اختبارات الطالب ونتائجها — **آخر محاولة لكل اختبار** لا كلها.
     * السؤال «أين هو الآن؟» لا «ماذا فعل عبر الشهر».
     *
     * TQ-EXAM-SOURCE: هذه تقرأ نظام التقييمات (`assessments` +
     * `attempts`) وهو ما يؤلف به اليوم — لا `quiz_results` الموروث الذي
     * لم يعد يكتب فيه شيء، وكانت شاشة الويب تعد منه وحده فتقول «لا
     * اختبارات بعد» لطالب سلم أربع محاولات الأسبوع الماضي.
     *
     * والاختبار بلا سؤال واحد لا يعرض: هو صف تقييم أنشئ عند فتح المحرر
     * ولم يؤلف.
     */
    public function student_exams()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];

        $rows = $this->db->query(
            'SELECT s.`id` AS assessment_id, s.`type`, s.`pass_mark`, s.`time_limit_sec`,
                    l.`id` AS lesson_id, l.`title` AS lesson_title,
                    c.`id` AS course_id, c.`title` AS course_title,
                    (SELECT COUNT(*) FROM `question` q WHERE q.`assessment_id` = s.`id`) AS questions,
                    t.`id` AS attempt_id, t.`attempt_no`, t.`score`, t.`passed`, t.`submitted_at`,
                    (SELECT MAX(t2.`score`) FROM `attempts` t2
                      WHERE t2.`assessment_id` = s.`id` AND t2.`student_id` = ?) AS best_score,
                    (SELECT COUNT(*) FROM `attempts` t3
                      WHERE t3.`assessment_id` = s.`id` AND t3.`student_id` = ?
                        AND t3.`submitted_at` IS NOT NULL) AS tries
               FROM `assessments` s
               JOIN `lesson` l ON l.`id` = s.`lesson_id`
               JOIN `course` c ON c.`id` = l.`course_id`
               JOIN `enrol`  e ON e.`course_id` = c.`id` AND e.`user_id` = ?
               LEFT JOIN `attempts` t
                      ON t.`id` = (SELECT t4.`id` FROM `attempts` t4
                                    WHERE t4.`assessment_id` = s.`id` AND t4.`student_id` = ?
                                      AND t4.`submitted_at` IS NOT NULL
                                    ORDER BY t4.`submitted_at` DESC, t4.`id` DESC LIMIT 1)
              WHERE s.`lesson_id` IS NOT NULL
              HAVING questions > 0
              ORDER BY (t.`submitted_at` IS NULL) ASC, t.`submitted_at` DESC, s.`id` DESC',
            array($uid, $uid, $uid, $uid))->result_array();

        $out = array();
        $done = 0; $passed = 0;
        foreach ($rows as $r) {
            $has = !empty($r['attempt_id']);
            if ($has) {
                $done++;
                if ((int) $r['passed'] === 1) $passed++;
            }
            $out[] = array(
                'assessment_id'  => (int) $r['assessment_id'],
                'kind'           => (string) $r['type'],
                'lesson'         => array('id' => (int) $r['lesson_id'], 'title' => $r['lesson_title']),
                'course'         => array('id' => (int) $r['course_id'], 'title' => $r['course_title']),
                'question_count' => (int) $r['questions'],
                'pass_mark'      => (int) $r['pass_mark'],
                'time_limit_sec' => $r['time_limit_sec'] !== null ? (int) $r['time_limit_sec'] : null,
                'tries'          => (int) $r['tries'],
                'best_score'     => $r['best_score'] !== null ? (int) $r['best_score'] : null,
                'last_attempt'   => $has ? array(
                    'attempt_id'   => (int) $r['attempt_id'],
                    'attempt_no'   => (int) $r['attempt_no'],
                    'score'        => (int) $r['score'],
                    'passed'       => ((int) $r['passed'] === 1),
                    'submitted_at' => tq_api_date($r['submitted_at']),
                ) : null,
                'state' => !$has ? 'not_started'
                         : (((int) $r['passed'] === 1) ? 'passed' : 'failed'),
            );
        }

        $this->read($out, '', array(
            'total'  => count($out),
            'taken'  => $done,
            'passed' => $passed,
        ), $h);
    }

    /**
     * شكل السؤال المعروض — **بلا `correct_answers` بحال**.
     *
     * `review_questions()` تحذفها قبل أن ترد، والتعداد هنا يعدد ما يخرج
     * لا ما يحجب: عمود جديد في `question` غدا لا يتسرب لأن أحدا نسي أن
     * يضيفه إلى قائمة الحجب. وهو مبدأ `tq_api_user()` نفسه.
     */
    private function questions_out($rows)
    {
        $out = array();
        foreach ((array) $rows as $r) {
            $opts = $r['options'] ?? array();
            if (is_string($opts)) $opts = json_decode($opts, true);

            $out[] = array(
                'id'           => (int) $r['id'],
                'title'        => (string) $r['title'],
                'type'         => (string) $r['type'],
                'options'      => is_array($opts) ? array_values($opts) : array(),
                'objective_id' => !empty($r['objective_id']) ? (int) $r['objective_id'] : null,
            );
        }
        return $out;
    }

    /* ================================================================
       ٥ · التمرين — المراجعة المتباعدة ودفتر الأخطاء
       ================================================================ */

    /**
     * GET /api/v1/student/reviews
     *
     * أسئلة اليوم المستحقة — دفعة واحدة لا القائمة كلها.
     *
     * `review_daily_batch` في `settings` هو حجم الدفعة، و`get_due_reviews()`
     * تسقفه بخمسين. ولا يفتح للطالب أن يجر الطابور كله: التباعد يعمل
     * بالدفعة اليومية، ومن راجع مئتي سؤال في جلسة لم يثبت شيئا — والحد
     * قاعدة تعليمية لا حماية خادم.
     *
     * والترتيب من النموذج: الأقدم استحقاقا، فالأكثر تعثرا، فالأصعب
     * (`ease` الأدنى). وترتيب ثان في العميل يهدم التباعد.
     */
    public function student_reviews()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $repo  = $this->repo();
        $limit = (int) $this->input->get('limit');
        $rows  = $repo->get_due_reviews((int) $u['id'], $limit);

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'question_id'   => (int) $r['question_id'],
                'title'         => (string) $r['title'],
                'type'          => (string) $r['type'],
                'options'       => is_array($r['options']) ? array_values($r['options']) : array(),
                'objective'     => array(
                    'id'        => $r['objective_id'] ? (int) $r['objective_id'] : null,
                    'text'      => $r['objective_text'] ?: null,
                    'at_second' => (int) $r['at_second'],
                ),
                'lesson'        => array(
                    'id'    => $r['lesson_id'] ? (int) $r['lesson_id'] : null,
                    'title' => $r['lesson_title'] ?: null,
                ),
                'course'        => array(
                    'id'    => $r['course_id'] ? (int) $r['course_id'] : null,
                    'title' => $r['course_title'] ?: null,
                ),
                'due_at'        => tq_api_date($r['due_at']),
                'interval_days' => (int) $r['interval_days'],
                'lapses'        => (int) $r['lapses'],
            );
        }

        $this->read($out, '', array(
            'count'       => count($out),
            'total_due'   => (int) $repo->count_due_reviews((int) $u['id']),
            'daily_batch' => (int) $repo->setting('review_daily_batch', 10),
        ), $h);
    }

    /**
     * POST /api/v1/student/reviews/answer
     *
     * إجابة سؤال مراجعة — **والصواب يقرره الخادم**.
     *
     * `correct` لا يقبل من الجسم بحال: العميل يرسل ما اختاره
     * (`given`)، و`is_answer_correct()` تقابله بـ`correct_answers`. ولو
     * قبل من العميل لصار الجدول الزمني لعبة — يعلن الطالب صوابا فيتباعد
     * السؤال ستين يوما وهو لم يجب.
     *
     * وشرط ثان قبل ذلك: السؤال **في طابور هذا الطالب**. وبلاه يجيب من
     * يخمن الأرقام على أسئلة لم تسند إليه فيحرك حالة مهارات لم يدرسها.
     */
    public function review_answer()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid = (int) $u['id'];
        $qid = (int) $this->in('question_id', 0);
        if (!$qid) {
            $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422,
                        array('question_id' => array('هذا الحقل مطلوب.')));
        }

        $in_queue = $this->db->where('student_id', $uid)->where('question_id', $qid)
                             ->count_all_results('review_queue');
        if (!$in_queue) {
            $this->fail('هذا السؤال ليس في مراجعتك اليوم.', 'not_entitled', 403);
        }

        $q = $this->db->where('id', $qid)->get('question')->row_array();
        if (!$q) $this->fail('لا سؤال بهذا الرقم.', 'not_found', 404);

        $given = $this->in('given', array());
        if (is_string($given)) {
            $decoded = json_decode($given, true);
            $given   = is_array($decoded) ? $decoded : array($given);
        }
        if (!is_array($given)) $given = ($given === null) ? array() : array($given);

        $repo    = $this->repo();
        $correct = $repo->is_answer_correct($q, $given);
        $r       = $repo->answer_review($uid, $qid, $correct);

        $repo->audit($uid, 'review.answer', 'question:' . $qid, null, array(
            'correct'       => (bool) $correct,
            'interval_days' => $r['interval_days'],
            'via'           => 'api',
        ));

        $this->respond(tq_api_ok(array(
            'question_id'   => (int) $r['question_id'],
            'correct'       => (bool) $r['correct'],
            'interval_days' => (int) $r['interval_days'],
            'lapses'        => (int) $r['lapses'],
            'due_at'        => tq_api_date($r['due_at']),
            'remaining_due' => (int) $r['remaining_due'],
        )), 200);
    }

    /**
     * GET /api/v1/student/mistakes
     *
     * دفتر الأخطاء — يشتق من `answers` حيث `is_correct = 0`، لا جدول
     * مستقل، حتى لا يفترق الدفتر عن الحقيقة.
     *
     * والصف سؤال لا محاولة: `wrong_count` كم مرة أخطئ فيه، و`due_at`
     * متى يعود في المراجعة. وسؤال أخطئ فيه أربع مرات ليس أربعة أسطر —
     * هو خطأ واحد متكرر، وذاك ما يقرؤه الطالب.
     */
    public function student_mistakes()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $rows = $this->repo()->get_mistakes((int) $u['id']);

        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 100, 20);

        $out = array();
        foreach (array_slice($rows, $offset, $per) as $r) {
            $out[] = array(
                'question_id' => (int) $r['question_id'],
                'title'       => (string) $r['title'],
                'type'        => (string) $r['type'],
                'wrong_count' => (int) $r['wrong_count'],
                'last_wrong_at' => tq_api_date($r['last_wrong_at']),
                'objective'   => array(
                    'id'        => $r['objective_id'] ? (int) $r['objective_id'] : null,
                    'text'      => $r['objective_text'] ?: null,
                    'at_second' => (int) $r['at_second'],
                ),
                'lesson'      => array(
                    'id'    => $r['lesson_id'] ? (int) $r['lesson_id'] : null,
                    'title' => $r['lesson_title'] ?: null,
                ),
                'course'      => array(
                    'id'    => $r['course_id'] ? (int) $r['course_id'] : null,
                    'title' => $r['course_title'] ?: null,
                ),
                'due_at'        => tq_api_date($r['due_at'] ?? null),
                'interval_days' => (int) $r['interval_days'],
                'lapses'        => (int) $r['lapses'],
            );
        }

        $this->read($out, '', tq_api_meta_page($page, $per, count($rows)), $h);
    }

    /* ================================================================
       ٦ · بقية بوابة الطالب — الشاشات التي كانت في الويب وحدها
       ================================================================

       الوحدات الخمس أعلاه هي **حلقة التعلم**، وهي التي بنيت أولا لأن
       التطبيق بلا درس يشغل ليس تطبيقا. وما هنا بقية البوابة: ما يحيط
       بالحلقة ويجعلها قابلة للاستعمال يوما بعد يوم — الإشعار الذي يخبر،
       والرسالة التي تسأل المعلم، والمهمة التي تنتظر، والتقويم الذي يرتب،
       والتقرير الذي يقيس، والشراء الذي يفتح.

       والقاعدة واحدة لم تتغير: **لا قاعدة عمل في هذه الطبقة.** ما كان
       في نموذج ينادى من نموذجه (`Taqdar_favourites_model` ·
       `Taqdar_sessions_model` · `Taqdar_billing_model` ·
       `Taqdar_learn_model` · `Taqdar_diag_model`)، وما كان في قالب نقل
       إلى `Taqdar_student_model` وصارت الشاشة والواجهة تقرآن منه معا.
       ونسخة ثانية هنا تعني أن التطبيق يعرض ما يخفيه الموقع. */

    /** نموذج بقية شاشات الطالب. */
    private function stu()
    {
        $this->load->model('taqdar_student_model', 'tq_stu');
        return $this->tq_stu;
    }

    /* ---- الإشعارات ------------------------------------------------- */

    /**
     * GET /api/v1/student/notifications
     *
     * والعدادات تحسب على **الكل** لا على المعروض: تبويب «غير مقروءة»
     * يعد ما بداخله وحده يقول صفرا حين تفتحه وقد قرأت آخر إشعار، فيبدو
     * التبويب معطلا.
     */
    public function student_notifications()
    {
        $this->method('GET');
        $u = $this->require_portal();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $state = (string) $this->input->get('state');
        if (!in_array($state, array('unread', 'read'), true)) $state = 'all';

        $feed = $this->stu()->notifications((int) $u['id'], $state);
        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 50, 20);

        $items = array();
        foreach (array_slice($feed['items'], $offset, $per) as $n) {
            $items[] = $this->notification_out($n);
        }

        $this->read($items, '', array_merge(
            tq_api_meta_page($page, $per, count($feed['items'])),
            array('counts' => $feed['counts'], 'by_kind' => (object) $feed['by_kind'],
                  'filters' => array('state' => $state))
        ), $h);
    }

    /**
     * شكل الإشعار.
     *
     * `notifications.created_at` طابع يونكس **نصا** في مخطط Academy،
     * و`tq_api_date()` تعرف الاثنين. و`kind_label` يخرج مع `type` لا بدلا
     * منه: الأول تسمية تعرض والثاني مفتاح يفرع عليه التطبيق.
     *
     * **والنص في `description` لا في `message`**، ولا عمود `url` في
     * الجدول أصلا (`id · from_user · to_user · type · title · description ·
     * status · created_at · updated_at`). وقراءة اسم مفترض ترد سلسلة
     * فارغة بلا خطأ — فيخرج كل إشعار بعنوانه وبلا نصه، وهو أسوأ من خطأ
     * لأنه يبدو عاملا.
     */
    private function notification_out($n)
    {
        list($label, $icon, $tone) = $this->stu()->notification_kind($n['type']);

        return array(
            'id'         => (int) $n['id'],
            'type'       => (string) $n['type'],
            'kind_label' => $label,
            'icon'       => $icon,
            'tone'       => $tone,
            'title'      => (string) ($n['title'] ?? ''),
            'body'       => (string) ($n['description'] ?? ''),
            'is_read'    => ((int) $n['status'] === 1),
            'created_at' => tq_api_date($n['created_at'] ?? null),
        );
    }

    /**
     * POST /api/v1/student/notifications/read — إشعار بعينه أو الكل.
     *
     * `{"id": 12}` يقرأ واحدا، و`{"all": true}` يقرأ الكل. والرد يحمل
     * العدادات بعد التغيير لا «تم»: الشارة في التطبيق تحدث من الرد نفسه
     * بلا نداء ثان.
     */
    public function notifications_read()
    {
        $this->method('POST');
        $u = $this->require_portal();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid = (int) $u['id'];
        $all = tq_api_bool($this->in('all', false));
        $id  = (int) $this->in('id', 0);

        if (!$all && $id <= 0) {
            $this->fail('حدد إشعارا برقمه أو اطلب قراءة الكل.', 'validation_failed', 422,
                        array('id' => array(t('هذا الحقل مطلوب.'))));
        }

        $n = $all ? $this->stu()->mark_all_notifications_read($uid)
                  : $this->stu()->mark_notification_read($uid, $id);

        $feed = $this->stu()->notifications($uid, 'all');

        $this->respond(tq_api_ok(
            array('changed' => (int) $n, 'counts' => $feed['counts']),
            $all ? 'قرئت كل الإشعارات.' : 'قرئ الإشعار.'
        ), 200);
    }

    /* ---- الرسائل --------------------------------------------------- */

    /**
     * GET /api/v1/student/messages — المحادثات.
     *
     * والتصفية في الخادم لا في التطبيق: نسختان من قاعدة «المعلمون»
     * تفترقان عند أول تعديل، وهي القاعدة نفسها التي تحكم `/catalog`.
     */
    public function student_messages()
    {
        /* بابان على مسار واحد: القراءة والإرسال. والقاعدة في `routes.php`
           تربط **المسار** لا الطريقة، فنقطتان بدالتين على المسار نفسه
           تعنيان أن الثانية لا تنادى أبدا — يصل `POST` إلى دالة القراءة
           فيرد 405 على طلب صحيح. وهو التوزيع نفسه في `lesson_notes()`
           و`student_setup()`. */
        if ($this->method(array('GET', 'POST')) === 'POST') {
            $this->message_send();
            return;
        }

        $u = $this->require_portal();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $all = $this->stu()->threads($uid);

        $filter = (string) $this->input->get('filter');
        if (!in_array($filter, array('unread', 'teachers', 'support'), true)) $filter = 'all';
        $q = trim((string) $this->input->get('q'));

        $list = array();
        $unread_total = 0;
        foreach ($all as $t) {
            $unread_total += (int) $t['unread'];

            if ($filter === 'unread'   && $t['unread'] < 1) continue;
            if ($filter === 'teachers' && empty($t['person']['is_instructor'])) continue;
            if ($filter === 'support'  && (int) ($t['person']['role_id'] ?? 0) !== 1) continue;
            if ($q !== '') {
                $hay = ($t['person']['first_name'] ?? '') . ' ' . ($t['person']['last_name'] ?? '')
                     . ' ' . ($t['last']['message'] ?? '');
                if (mb_stripos($hay, $q) === false) continue;
            }
            $list[] = $this->thread_out($t);
        }

        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 50, 20);

        $this->read(array_slice($list, $offset, $per), '', array_merge(
            tq_api_meta_page($page, $per, count($list)),
            array('unread_total' => $unread_total,
                  'filters' => array('filter' => $filter, 'q' => $q))
        ), $h);
    }

    /** شكل المحادثة في القائمة. */
    private function thread_out($t)
    {
        $p = (array) $t['person'];
        $is_support = ((int) ($p['role_id'] ?? 0) === 1) && empty($p['is_instructor']);

        return array(
            'code'   => (string) $t['code'],
            'unread' => (int) $t['unread'],
            'person' => array(
                'id'         => (int) $t['other'],
                'name'       => $is_support ? t('الدعم الفني')
                              : trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
                'role'       => !empty($p['is_instructor']) ? 'teacher' : ($is_support ? 'support' : 'user'),
                'avatar_url' => tq_api_avatar($p['image'] ?? ''),
            ),
            'last' => empty($t['last']) ? null : array(
                'body' => (string) $t['last']['message'],
                'mine' => ((int) $t['last']['sender'] === (int) $this->me['id']),
                'at'   => tq_api_date($t['last']['timestamp']),
            ),
            'updated_at' => tq_api_date($t['ts']),
        );
    }

    /**
     * GET /api/v1/student/messages/recipients — من يجوز مراسلته.
     *
     * ومن القائمة نفسها التي يفحص بها الإرسال: منتق يعرض حسابا يرده
     * الحارس يجعل الطالب يقرأ «لا ترسل الرسائل إلا إلى معلمي موادك» عن
     * اسم اختاره من قائمة عرضناها نحن.
     */
    public function message_recipients()
    {
        $this->method('GET');
        $u = $this->require_portal();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->read($this->recipients_of((int) $u['id']), '',
                    array('note' => $this->recipients_note()), $h);
    }

    /**
     * POST /api/v1/student/messages — رسالة جديدة إلى حساب مسموح.
     *
     * والنطاق يفحص في الخادم: `crud_model::send_new_private_message()`
     * تقرأ `receiver` من الطلب ولا تفحصه، فبدون هذا الشرط يبدل من شاء
     * رقما فيراسل أي حساب في المنصة.
     */
    private function message_send()
    {
        $u = $this->require_portal();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid = (int) $u['id'];
        $b   = $this->body();

        $errors = tq_api_validate($b, array(
            'receiver' => 'required|int',
            'body'     => 'required|max:5000',
        ));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $to = (int) $b['receiver'];
        if (!$this->may_message_to($uid, $to)) {
            $this->fail($this->recipients_note(), 'recipient_not_allowed', 403);
        }

        /* والحفظ في `Taqdar_student_model` لا في `crud_model`: تلك تقرأ
           المرسل من **الجلسة**، وهذه الطبقة بلا جلسة بحكم أول قاعدة
           فيها — فنداؤها من هنا يكتب صفا مرسله صفر. والمرسل هنا معامل. */
        $code = (string) $this->stu()->send_message($uid, $to, (string) $b['body']);

        $this->api->audit('api.message.send', $uid, array('to' => $to));

        $this->respond(tq_api_ok(array('thread_code' => $code), 'وصلت رسالتك.'), 201);
    }

    /**
     * GET · POST · DELETE /api/v1/student/messages/{code}
     *
     * والملكية تفحص في النموذج في `WHERE` نفسه: رمز مخمن لا يفتح محادثة
     * غيرك ولا يحقن فيها ردا ولا يحذفها.
     */
    public function message_thread($code = '')
    {
        $m = $this->method(array('GET', 'POST', 'DELETE'));
        $u = $this->require_portal();

        $uid  = (int) $u['id'];
        $code = (string) rawurldecode((string) $code);

        if (!$this->stu()->owns_thread($uid, $code)) {
            /* رد واحد للمعدوم ولمحادثة غيره عمدا: التفريق يقول لمن خمن
               أن الرمز موجود. */
            $this->fail('لا محادثة بهذا الرمز.', 'not_found', 404);
        }

        if ($m === 'DELETE') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);
            $this->stu()->delete_thread($uid, $code);
            $this->api->audit('api.message.delete_thread', $uid, array('thread' => $code));
            $this->respond(tq_api_ok(null, 'حذفت المحادثة.'), 200);
        }

        if ($m === 'POST') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

            $b = $this->body();
            $errors = tq_api_validate($b, array('body' => 'required|max:5000'));
            if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

            $this->stu()->reply_message($uid, $code, (string) $b['body']);

            $this->respond(tq_api_ok(
                $this->messages_out($this->stu()->messages($uid, $code), $uid),
                'أرسل ردك.'), 201);
        }

        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        /* فتح المحادثة يجعلها مقروءة — كما يتوقع من فتحها فعلا. */
        $this->stu()->read_thread($uid, $code);

        $this->read($this->messages_out($this->stu()->messages($uid, $code), $uid), '',
                    array('thread_code' => $code), $h);
    }

    /**
     * شكل الرسالة الواحدة — و`mine` تغني التطبيق عن مقارنة المعرفات.
     *
     * والمفتاح `message_id` لا `id`: جدول `message` من مخطط Academy
     * ويسمي مفتاحه باسم الجدول. وقراءة `id` ترد صفرا لكل صف بلا خطأ —
     * فيقرأ التطبيق قائمة رسائل مفاتيحها كلها صفر، ولا يفرق بينها.
     */
    private function messages_out($rows, $uid)
    {
        $out = array();
        foreach ((array) $rows as $r) {
            $out[] = array(
                'id'      => (int) $r['message_id'],
                'body'    => (string) $r['message'],
                'mine'    => ((int) $r['sender'] === (int) $uid),
                'is_read' => ((int) $r['read_status'] === 1),
                'sent_at' => tq_api_date($r['timestamp']),
            );
        }
        return $out;
    }

    /* ---- الشهادات --------------------------------------------------- */

    /**
     * GET /api/v1/student/certificates
     *
     * والشهادة على **إتقان مقاس** لا على مشاهدة: من لم يجتز امتحان محطة
     * بعد يقرأ قائمة فارغة، لا شهادة مبنية على وقت تشغيل.
     */
    public function student_certificates()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $stu = $this->stu();
        $out = array();
        foreach ($stu->certificates((int) $u['id']) as $c) {
            $out[] = array(
                'id'          => (int) $c['id'],
                'code'        => $stu->certificate_code($c['id']),
                'title'       => (string) ($c['milestone_title'] ?: $c['path_title'] ?: t('محطة')),
                'score'       => (int) $c['score'],
                'issued_at'   => tq_api_date($c['submitted_at']),
                /* الشهادة صفحة تطبع وتوقع، فلا نموذج JSON لها: التطبيق
                   يفتح رابطها في متصفح داخلي كما يفتح صفحة الدفع. */
                'web_url'     => base_url('student/certificate/' . (int) $c['id']),
                'verify_url'  => base_url('student/verify/' . (int) $c['id']),
            );
        }

        $this->read($out, '', array('count' => count($out)), $h);
    }

    /* ---- المهام ------------------------------------------------------ */

    /**
     * GET /api/v1/student/tasks — الواجبات في ثلاث مجموعات.
     *
     * ولا حالة «متأخر»: لا موعد استحقاق في المخطط يقاس عليه التأخر.
     * ودرجة لم يعتمدها المعلم لا تخرج — `Taqdar_marking_model` هو من
     * يقرر، فلا يقرأ الطالب رقما يحسبه نهائيا ثم يأتي الاعتماد فيغيره.
     */
    public function student_tasks()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $groups = $this->stu()->tasks((int) $u['id']);

        $out = array(); $counts = array(); $total = 0;
        foreach ($groups as $key => $g) {
            $items = array();
            foreach ($g['items'] as $i) {
                $items[] = array(
                    'lesson_id' => (int) $i['id'],
                    'course_id' => (int) $i['course_id'],
                    'title'     => (string) $i['title'],
                    'subject'   => (string) $i['subject'],
                    'stage'     => (string) $i['stage'],
                    'minutes'   => (int) $i['minutes'],
                    'questions' => (int) $i['points'],
                    'pass_mark' => (int) $i['pass'],
                    'at'        => tq_api_date($i['at'] ?: null),
                    /* المكتملة وحدها تحمل الدرجة، و`graded=false` تعني
                       «سلمت وتنتظر معلمك» — وهي حال مختلفة عن صفر. */
                    'graded'    => isset($i['graded']) ? (bool) $i['graded'] : null,
                    'score'     => isset($i['score'])  ? $i['score'] : null,
                    'max'       => isset($i['max'])    ? (int) $i['max'] : null,
                    'passed'    => isset($i['pass_ok']) ? $i['pass_ok'] : null,
                    'note'      => isset($i['note'])   ? (string) $i['note'] : null,
                    'web_url'   => (string) $i['href'],
                );
            }
            $counts[$key] = count($items);
            $total += count($items);
            $out[] = array('key' => $key, 'label' => $g['label'], 'items' => $items);
        }

        $this->read($out, '', array('counts' => $counts, 'total' => $total), $h);
    }

    /* ---- التقويم ------------------------------------------------------ */

    /**
     * GET /api/v1/student/calendar
     *
     * قائمة مسطحة مرتبة زمنيا — لا شبكة شهر. الشبكة رسم، ورسمها في Dart
     * أسهل وأصح من نقل مصفوفة صفوف وأعمدة عبر الشبكة؛ وما يحتاجه الرسم
     * هو الأحداث بتواريخها، وهي ما يخرج هنا.
     *
     * و`from`/`to` نافذة اختيارية (`YYYY-MM-DD`): بلاها يخرج كل ما يعرفه
     * التقويم، وهو مئات الصفوف على حساب قديم.
     */
    public function student_calendar()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $day = function ($v) {
            $v = trim((string) $v);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? strtotime($v) : 0;
        };
        $from = $day($this->input->get('from'));
        $to   = $day($this->input->get('to'));
        if ($to > 0) $to += 86400;          // شامل ليومه كله لا حتى منتصف ليله

        $cat = (string) $this->input->get('cat');
        $cats = $this->stu()->calendar_categories();
        if (!isset($cats[$cat])) $cat = '';

        $events = array(); $by_cat = array();
        foreach ($this->stu()->calendar_events((int) $u['id']) as $e) {
            $by_cat[$e['cat']] = (isset($by_cat[$e['cat']]) ? $by_cat[$e['cat']] : 0) + 1;

            if ($from > 0 && $e['ts'] <  $from) continue;
            if ($to   > 0 && $e['ts'] >= $to)   continue;
            if ($cat !== '' && $e['cat'] !== $cat) continue;

            $events[] = array(
                'at'         => tq_api_date($e['ts']),
                'date'       => date('Y-m-d', (int) $e['ts']),
                'category'   => (string) $e['cat'],
                'category_label' => $cats[$e['cat']][0],
                'icon'       => $cats[$e['cat']][2],
                'title'      => (string) $e['title'],
                'subtitle'   => (string) $e['sub'],
                'web_url'    => (string) $e['href'],
            );
        }

        $legend = array();
        foreach ($cats as $k => $c) {
            $legend[] = array('key' => $k, 'label' => $c[0], 'icon' => $c[2],
                              'action' => $c[3], 'count' => isset($by_cat[$k]) ? $by_cat[$k] : 0);
        }

        $this->read($events, '', array(
            'categories' => $legend,
            'filters'    => array('from' => $this->input->get('from'),
                                  'to'   => $this->input->get('to'),
                                  'cat'  => $cat),
            'total'      => count($events),
        ), $h);
    }

    /* ---- المكتبة ------------------------------------------------------ */

    /**
     * GET /api/v1/student/library — كتب الطالب.
     *
     * **والملف يرد رابطه ولا يخفى**: القارئ داخل الصفحة في الويب
     * (`pdf.js`) يمنع النسخ العرضي، والتطبيق له قارئه هو. وحجب الرابط
     * هنا يعني كتابا لا يفتح في التطبيق أصلا — وهو حجب لا حماية:
     * المحتوى نفسه يصل إلى القارئ في الحالين.
     *
     * TQ-BOOK — **والرابط يمر بالحارس** (`book-file/<id>`) لا بـ
     * `uploads/` عاريا: صار الكتاب يباع، ورابط عار في مجلد الرفع يعني
     * أن أول مشتر يوزعه على من شاء — والشراء يصير اقتراحا. والحارس يخدم
     * المجاني بلا تسجيل كما كان، فلا مساران.
     *
     * **ومجموعتان لا واحدة**: `books` ما يفتحه الآن، و`locked` كتب
     * تشترى بسعرها ورابط شرائها. وقائمة واحدة تخلطهما تجعل التطبيق يفتح
     * قارئه على كتاب لا يملكه فيرد الحارس 403، ولا شيء يقول لماذا.
     */
    public function student_library()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $lib = $this->stu()->library((int) $u['id']);

        $asset = function ($rel, $dir) {
            $v = trim((string) $rel);
            if ($v === '') return null;
            if (filter_var($v, FILTER_VALIDATE_URL)) return $v;
            $path = (strpos($v, 'uploads/') === 0) ? $v : $dir . $v;
            return is_file(FCPATH . $path) ? base_url($path) : null;
        };

        $shape = function ($b, $open) use ($asset) {
            $slug = trim((string) $b['slug']) !== '' ? (string) $b['slug'] : (string) $b['id'];
            $row  = array(
                'id'          => (int) $b['id'],
                'title'       => (string) $b['title'],
                'slug'        => $slug,
                'subject'     => (string) $b['subject'],
                'author'      => (string) $b['author'],
                'pages'       => (int) $b['pages'],
                'description' => (string) $b['description'],
                'cover_url'   => $asset($b['cover'], 'uploads/books/'),
                'web_url'     => base_url('book/' . rawurlencode($slug)),
            );

            if ($open) {
                /* الحارس لا `uploads/`: انظر رأس الدالة. وبلا ملف لا
                   رابط — ورابط يقود إلى 404 أسوأ من غيابه.
                   **وحارس الواجهة لا حارس الويب**: `book-file/<id>`
                   يستوثق بكعكة الجلسة والتطبيق بلا كعكة، فكل كتاب مدفوع
                   كان يرد 403 على التطبيق وحده. وهو `playback_out()`
                   نفسها. */
                $row['file_url'] = trim((string) $b['file']) !== ''
                                 ? base_url('api/v1/student/books/' . (int) $b['id'] . '/file')
                                 : null;
                $row['price']    = null;
            } else {
                $row['file_url']     = null;
                $row['price']        = tq_api_money((int) $b['price']);
                $row['list_price']   = ((int) $b['list_price'] > 0)
                                     ? tq_api_money((int) $b['list_price']) : null;
                $row['discount_pct'] = (int) $b['off'];
                $row['checkout_url'] = base_url('book-checkout/' . (int) $b['id']);
            }
            return $row;
        };

        $out    = array();
        $locked = array();
        foreach ($lib['books'] as $b)  $out[]    = $shape($b, true);
        foreach ($lib['locked'] as $b) $locked[] = $shape($b, false);

        $this->read(array('books' => $out, 'locked' => $locked), '', array(
            'count'        => count($out),
            'locked_count' => count($locked),
            /* `scoped` تقول أيهما وقع: كتب مرحلته، أم الكل لأن مرحلته
               بلا كتاب. وشاشة تعد بواحدة وتعرض الأخرى تربك صاحبها. */
            'scoped' => (bool) $lib['scoped'],
        ), $h);
    }

    /* ---- المواد التعليمية والمفضلة ----------------------------------- */

    /**
     * GET /api/v1/student/materials — ملفات كورساته المسجلة.
     *
     * مصدران في واحد: `resource_files` المعلقة بالدرس، ومرفق الدرس
     * نفسه. و`favourite` تخرج مع كل صف: شاشة تعرض قلبا فارغا على ملف
     * محفوظ تجعل الطالب يحفظه مرتين ثم يجده مرة.
     */
    public function student_materials()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];

        $this->load->model('taqdar_favourites_model', 'tq_fav');
        $fav = array_flip(array_map('intval', (array) $this->tq_fav->ids($uid, 'material')));

        $type = (string) $this->input->get('type');
        if (!in_array($type, array('pdf', 'video', 'slide', 'audio', 'image', 'link', 'doc'), true)) $type = '';
        $q = trim((string) $this->input->get('q'));

        $all = tq_s_materials($uid);

        $by_type = array();
        $list = array();
        foreach ($all as $m) {
            $k = $m['kind']['key'];
            $by_type[$k] = (isset($by_type[$k]) ? $by_type[$k] : 0) + 1;

            if ($type !== '' && $k !== $type) continue;
            if ($q !== '' && mb_stripos($m['title'], $q) === false
                          && mb_stripos((string) $m['course'], $q) === false) continue;

            $list[] = array(
                'id'         => (int) ($m['fav_id'] ?? 0),
                'title'      => (string) $m['title'],
                'lesson'     => (string) $m['lesson'],
                'course'     => (string) $m['course'],
                'subject'    => (string) $m['subject'],
                'kind'       => $k,
                'kind_label' => $m['kind']['label'],
                'url'        => (string) $m['url'],
                'bytes'      => (int) $m['bytes'],
                'added_at'   => tq_api_date($m['at'] ?: null),
                /* `fav_id` صفر يعني مرفق درس: لا صف له في جدول فلا معرف
                   ثابت يفضل به — وقلب لا يعرف ما يحفظ لا يعرض. */
                'favourable' => ((int) ($m['fav_id'] ?? 0) > 0),
                'favourite'  => isset($fav[(int) ($m['fav_id'] ?? 0)]) && (int) ($m['fav_id'] ?? 0) > 0,
            );
        }

        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 60, 20);

        $this->read(array_slice($list, $offset, $per), '', array_merge(
            tq_api_meta_page($page, $per, count($list)),
            array('by_type' => (object) $by_type, 'filters' => array('type' => $type, 'q' => $q))
        ), $h);
    }

    /**
     * GET /api/v1/student/favourites — الكورسات والدروس والملفات المحفوظة.
     *
     * ثلاثة أنواع في نقطة واحدة: الشاشة تبويبات فوق قائمة واحدة، وثلاث
     * نقاط لرسمها تعني ثلاثة أشواط على شبكة جوال لعرض تبويب واحد.
     */
    public function student_favourites()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $this->load->model('taqdar_favourites_model', 'tq_fav');

        $type = (string) $this->input->get('type');
        if (!in_array($type, array('courses', 'lessons', 'materials'), true)) $type = '';

        $courses = array();
        $ids = array_map('intval', (array) $this->tq_fav->course_ids($uid));
        if ($ids) {
            $owned = array();
            foreach ($this->courses_of($uid) as $c) $owned[(int) $c['id']] = $c;
            foreach ($this->db->select('id, title, thumbnail, level, short_description')
                              ->where_in('id', $ids)->get('course')->result_array() as $c) {
                $cid = (int) $c['id'];
                $courses[] = array(
                    'id'         => $cid,
                    'title'      => (string) $c['title'],
                    'level'      => (string) $c['level'],
                    'summary'    => (string) $c['short_description'],
                    'thumbnail'  => $this->thumb_url($c['thumbnail']),
                    /* «مسجل» غير «محفوظ»: يحفظ الطالب كورسا لا يملكه بعد
                       ليشتريه، فبطاقة تعده بزر «تابع» على محتوى مقفل
                       تعطل عند أول ضغطة. */
                    'enrolled'   => isset($owned[$cid]),
                    'progress'   => isset($owned[$cid]) ? $owned[$cid]['progress'] : null,
                );
            }
        }

        /* والنموذج يرد **صفوف القاعدة خاما** لا شكلا معدا: `duration`
           نص ساعة و`course_title` اسم العمود. والتشكيل هنا كما تشكله
           الشاشة — `tq_s_secs()` هي نفسها التي تقرأ المدة في المشغل. */
        $lessons = array();
        foreach ((array) $this->tq_fav->lessons($uid) as $l) {
            $lessons[] = array(
                'id'        => (int) $l['id'],
                'course_id' => (int) $l['course_id'],
                'title'     => (string) $l['title'],
                'course'    => (string) $l['course_title'],
                'subject'   => tq_s_subject($l['category_id'], (string) $l['course_title'], (int) $l['course_id']),
                'type'      => (string) $l['lesson_type'],
                'seconds'   => tq_s_secs($l['duration']),
            );
        }

        $materials = array();
        foreach ((array) $this->tq_fav->materials($uid) as $m) {
            $rel  = 'uploads/resource_files/' . $m['file_name'];
            $kind = tq_file_kind($m['file_name']);
            $materials[] = array(
                'id'         => (int) $m['id'],
                'title'      => (string) ($m['title'] !== '' ? $m['title'] : $m['file_name']),
                'lesson'     => (string) $m['lesson_title'],
                'course'     => (string) $m['course_title'],
                'subject'    => tq_s_subject($m['category_id'], (string) $m['course_title'], (int) $m['course_id']),
                'kind'       => $kind['key'],
                'kind_label' => $kind['label'],
                'url'        => base_url($rel),
                'bytes'      => is_file(FCPATH . $rel) ? (int) filesize(FCPATH . $rel) : 0,
            );
        }

        $data = array('courses' => $courses, 'lessons' => $lessons, 'materials' => $materials);
        if ($type !== '') $data = array($type => $data[$type]);

        $this->read($data, '', array(
            'counts' => array('courses' => count($courses), 'lessons' => count($lessons),
                              'materials' => count($materials)),
            'filters' => array('type' => $type),
        ), $h);
    }

    /**
     * POST /api/v1/student/favourites — يقلب القلب.
     *
     * والرد يحمل `on` — الحال بعد القلب لا «تم»: التطبيق يرسم القلب من
     * الرد بلا نداء ثان، ولا يخمن حاله فيخالف الخادم عند أول تعثر شبكة.
     */
    public function favourite_toggle()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array(
            'kind'    => 'required|in:course,lesson,material',
            'item_id' => 'required|int',
        ));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $uid  = (int) $u['id'];
        $kind = (string) $b['kind'];
        $id   = (int) $b['item_id'];

        $this->load->model('taqdar_favourites_model', 'tq_fav');
        $r = ($kind === 'course')
           ? $this->tq_fav->toggle_course($uid, $id)
           : $this->tq_fav->toggle($uid, $kind, $id);

        if (empty($r['ok'])) {
            $this->fail(isset($r['msg']) ? $r['msg'] : 'تعذر حفظ المفضلة.', 'favourite_failed', 422);
        }

        $this->respond(tq_api_ok(
            array('kind' => $kind, 'item_id' => $id, 'on' => !empty($r['on'])),
            isset($r['msg']) ? $r['msg'] : ''), 200);
    }

    /* ---- التقارير ----------------------------------------------------- */

    /**
     * GET /api/v1/student/reports — «المتابعة والتقارير».
     *
     * والسلاسل ترد **بفراغاتها**: أسبوع بلا قياس يخرج `null` لا صفرا.
     * وصفر مخترع مكان الفراغ يجعل الرسم يهبط إلى القاع فيقرأ الطالب
     * «تراجعت» عن أسبوع لم يقس له شيء أصلا. وهو سبب `has_delta` نفسه.
     */
    public function student_reports()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $r = $this->stu()->reports((int) $u['id']);

        $weeks = array();
        foreach ($r['weeks'] as $i => $w) {
            $weeks[] = array(
                'from'       => date('Y-m-d', (int) $w['from']),
                'to'         => date('Y-m-d', (int) $w['to'] - 86400),
                'grade'      => $r['grade_series'][$i],
                'completion' => $r['completion_series'][$i],
                'lessons'    => $r['lessons_series'][$i],
            );
        }

        $subjects = array();
        foreach ($r['subjects'] as $s) {
            $subjects[] = array(
                'name'     => (string) $s['name'],
                'courses'  => (int) $s['courses'],
                'lessons'  => (int) $s['lessons'],
                'percent'  => $s['courses'] > 0 ? (int) round($s['sum'] / $s['courses']) : 0,
            );
        }

        $this->read(array(
            'has_data' => (bool) $r['has_data'],
            'totals'   => array(
                'study_seconds' => (int) $r['seconds'],
                'study_hours'   => (int) $r['hours'],
                'study_minutes' => (int) $r['minutes'],
                'completion'    => (int) $r['completion'],
                'average_score' => (int) $r['average'],
                'lessons_done'  => (int) $r['done_lessons'],
                'lessons_total' => (int) $r['total_lessons'],
                'courses'       => count($r['enrolled']),
            ),
            'deltas'  => array(
                'grade'      => $r['grade_delta'],
                'completion' => $r['completion_delta'],
            ),
            'weeks'    => $weeks,
            'subjects' => $subjects,
        ), '', array(), $h);
    }

    /* ---- البحث داخل البوابة ------------------------------------------- */

    /**
     * GET /api/v1/student/search — في **ما يملكه** لا في الكتالوج.
     *
     * الكتالوج العام سؤال آخر وله بابه؛ وهذا السؤال «أين ذلك الدرس الذي
     * شاهدته؟». والمصادر الثلاثة من `tq_s_*` نفسها التي تبني شاشاتها،
     * فلا يفترق ما يجده البحث عما يفتحه بعده.
     */
    public function student_search()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $q = trim((string) $this->input->get('q'));
        if (mb_strlen($q) > 120) $q = mb_substr($q, 0, 120);

        $r = $this->stu()->search((int) $u['id'], $q);

        $courses = array();
        foreach ($r['courses'] as $c) {
            $courses[] = array(
                'id'        => (int) $c['id'],
                'title'     => (string) $c['title'],
                'subject'   => (string) $c['subject'],
                'progress'  => (int) $c['progress'],
                'thumbnail' => $this->thumb_url($c['thumbnail']),
            );
        }

        $lessons = array();
        foreach ($r['lessons'] as $l) {
            $lessons[] = array(
                'id'        => (int) $l['id'],
                'course_id' => (int) $l['course_id'],
                'title'     => (string) $l['title'],
                'course'    => (string) $l['course'],
                'subject'   => (string) $l['subject'],
                'seconds'   => (int) $l['seconds'],
                'state'     => (string) $l['state'],
            );
        }

        $materials = array();
        foreach ($r['materials'] as $m) {
            $materials[] = array(
                'title'  => (string) $m['title'],
                'course' => (string) $m['course'],
                'kind'   => $m['kind']['key'],
                'url'    => (string) $m['url'],
            );
        }

        $this->read(array('courses' => $courses, 'lessons' => $lessons, 'materials' => $materials),
            '', array('query' => $q, 'total' => (int) $r['total']), $h);
    }

    /* ---- التهيئة ووضع الامتحان والتلعيب -------------------------------- */

    /**
     * GET · POST /api/v1/student/setup — خطة الطالب.
     *
     * والقرار في `Taqdar_learn_model` نفسه الذي تمر به شاشة الويب:
     * الصف والمواد وهدف اليوم بوحدته. ونسخة ثانية من قواعده هنا تجعل
     * التطبيق يقبل هدفا يرفضه الموقع.
     */
    public function student_setup()
    {
        $m = $this->method(array('GET', 'POST'));
        $u = $this->require_student();
        $uid = (int) $u['id'];

        if ($m === 'POST') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);
            $b = $this->body();

            $r = $this->learn()->save_setup($uid, array(
                'grade_id'    => isset($b['grade_id']) ? $b['grade_id'] : null,
                'subject_ids' => isset($b['subject_ids']) ? $b['subject_ids'] : array(),
                'goal_unit'   => isset($b['goal_unit']) ? $b['goal_unit'] : null,
                'goal_value'  => isset($b['goal_value']) ? $b['goal_value'] : null,
            ));

            if (empty($r['ok'])) {
                $this->fail(isset($r['message']) ? $r['message'] : 'راجع البيانات المدخلة.',
                            'validation_failed', 422);
            }

            /* الوجهة بعد الحفظ ليست الرئيسية دائما: من بقي عليه تشخيص
               يذهب إليه — وهي القاعدة نفسها في `setup_save()` بالويب. */
            $this->load->model('taqdar_diag_model', 'tq_diag');
            $this->respond(tq_api_ok(array(
                'setup' => $this->learn()->setup($uid),
                'next'  => $this->tq_diag->gate($uid) ? 'placement' : 'home',
            ), $r['message']), 200);
        }

        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        /* النموذج يحمل **قبل** النداء الساكن: `Taqdar_learn_model::units()`
           تحتاج الصنف معرفا، و`$this->learn()` هي التي تحمله. وترتيبهما
           بالعكس يرمي «Class not found» على شاشة التهيئة كلها. */
        $learn = $this->learn();

        $grades = array();
        foreach ($this->db->select('id, name_ar')->from('grades')->where('active', 1)
                          ->order_by('`order`', 'ASC', false)->get()->result_array() as $g) {
            $grades[] = array('id' => (int) $g['id'], 'name' => (string) $g['name_ar']);
        }

        $units = array();
        foreach (Taqdar_learn_model::units() as $k => $v) {
            $units[] = array(
                'key'     => (string) $k,
                'label'   => t((string) $v['label']),
                'plural'  => t((string) $v['plural']),
                'default' => (int) $v['default'],
            );
        }

        $this->read(array(
            'setup'      => $learn->setup($uid),
            'needs'      => (bool) $learn->needs_setup($uid),
            'subjects'   => $learn->subjects_for($uid),
            'grades'     => $grades,
            'goal_units' => $units,
            'grade_id'   => (int) $this->db->select('grade_id')->where('id', $uid)
                                           ->get('users')->row('grade_id'),
        ), '', array(), $h);
    }

    /**
     * POST /api/v1/student/exam-mode — يفتح وضع الامتحان أو يطفئه.
     *
     * `{"off": true}` يطفئه، و`{"from","to"}` يفتحه. والتواريخ تفحص في
     * النموذج لا هنا: مدى مقلوب أو ماض يرد برسالته من الموضع الذي يرد
     * منه في الويب.
     */
    public function exam_mode()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid = (int) $u['id'];
        $b   = $this->body();

        $r = tq_api_bool($this->in('off', false))
           ? $this->learn()->set_exam_mode($uid, null, null)
           : $this->learn()->set_exam_mode($uid, (string) ($b['from'] ?? ''), (string) ($b['to'] ?? ''));

        if (empty($r['ok'])) {
            $this->fail(isset($r['message']) ? $r['message'] : 'راجع التواريخ.', 'validation_failed', 422);
        }

        $this->respond(tq_api_ok($this->learn()->exam_mode($uid), $r['message']), 200);
    }

    /**
     * POST /api/v1/student/gamify — السلسلة وحلقة الهدف.
     *
     * وهو تفضيل لا إعداد نظام: من أطفأه لا يرى رقما تحفيزيا واحدا، لا
     * في الويب ولا في التطبيق — والقراءة من `Taqdar_learn_model` نفسه.
     */
    public function gamify()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $on = tq_api_bool($this->in('on', $this->in('gamify', false)));
        $this->learn()->set_gamify((int) $u['id'], $on);

        $this->respond(tq_api_ok(array('gamify' => $on), $on
            ? 'أعاد التلعيب: تظهر لك السلسلة وحلقة الهدف.'
            : 'أوقف التلعيب: لا سلسلة ولا حلقة هدف ولا أرقام تحفيز.'), 200);
    }

    /* ---- اختبار تحديد المستوى ------------------------------------------ */

    /**
     * GET /api/v1/student/placement — حال الاختبار التشخيصي.
     *
     * ثلاث حالات كما في الويب: `intro` قبل البدء، و`exam` أثناءه بأسئلته
     * **بلا إجاباتها**، و`result` بعده بمستواه والباقة الموصى بها.
     *
     * **والمحاولة المفتوحة تقرأ قبل المسلمة** لا بعدها: من فتح محاولة
     * ثانية بإذن المسؤول له مفتوحة ومسلمة معا، فقراءة المسلمة أولا تعيده
     * إلى نتيجته القديمة أبدا ويصير «يسمح بالإعادة» إعدادا بلا باب.
     */
    public function student_placement()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $this->load->model('taqdar_diag_model', 'tq_diag');

        $exam = $this->placement_exam($uid);
        if (!$exam) {
            /* لا اختبار لصفه — أو لا صف له. وهي حال طبيعية لا خطأ:
               الشاشة تدل على الباقات كما تفعل في الويب. */
            $this->read(array('state' => 'unavailable', 'exam' => null,
                              'reason' => t('لا اختبار تحديد مستوى لصفك بعد.')),
                        '', array(), $h);
        }

        $open = $this->db->where('student_id', $uid)->where('exam_id', (int) $exam['id'])
                         ->where('submitted_at IS NULL', null, false)
                         ->order_by('id', 'DESC')->limit(1)
                         ->get('tq_diag_attempts')->row_array();

        $done  = $this->tq_diag->latest_attempt($uid, (int) $exam['id']);
        $state = $open ? 'exam' : ($done ? 'result' : 'intro');

        $questions = array();
        if ($state === 'exam') {
            /* TQ-DIAG-FORM — عينة هذا الطالب لا البنك كله. */
            foreach ($this->tq_diag->exam_form((int) $exam['id'], $uid) as $q) {
                /* بلا `with_answers`: قائمة خيارات ومعها الصواب في الحمولة
                   تجعل الاختبار عرضا لا قياسا. و`options` تصل **مصفوفة
                   مفكوكة** من النموذج لا نص JSON، ففكها هنا من جديد يرد
                   `null` على كل سؤال. */
                $questions[] = array(
                    'id'      => (int) $q['id'],
                    'title'   => (string) $q['title'],
                    'type'    => (string) ($q['type'] ?? 'single'),
                    'level'   => (string) ($q['level'] ?? ''),
                    'image'   => trim((string) ($q['image'] ?? '')) ?: null,
                    'options' => array_values((array) $q['options']),
                );
            }
        }

        $plan = null;
        if ($done && (int) $done['plan_id'] > 0) {
            $this->load->model('taqdar_site_model', 'tq_site');
            $row = $this->tq_site->plan_row((int) $done['plan_id']);
            /* **ومن بوابة عنوانها العام** (TQ-DIAG-404): الرابط يفتح
               `active = 1` وحدها، فباقة أوقفت بعد أن ربطت بمستوى كانت
               تعرض بسعرها وزرها يرد 404. */
            if ($row && $this->tq_site->plan_url($row)) $plan = $this->plan_out($row);
        }

        $this->read(array(
            'state' => $state,
            'exam'  => array(
                'id'    => (int) $exam['id'],
                'title' => (string) ($exam['title'] ?? ''),
                /* وقبل البدء يعلن حجم العينة لا حجم البنك. */
                'count' => count($questions) ?: $this->tq_diag->form_size((int) $exam['id'], $uid),
            ),
            'questions' => $questions,
            'result'    => $done ? array(
                'attempt_id' => (int) $done['id'],
                'level'      => (string) $done['result_level'],
                'level_label'=> Taqdar_diag_model::level_label($done['result_level']),
                'score'      => (int) $done['score'],
                'total'      => (int) $done['total'],
                'taken_at'   => tq_api_date($done['submitted_at']),
            ) : null,
            'recommended_plan' => $plan,
            'levels'           => Taqdar_diag_model::levels(),
        ), '', array(), $h);
    }

    /** اختبار صف هذا الطالب — الاشتقاق نفسه في الشاشات الثلاث. */
    private function placement_exam($uid)
    {
        $this->load->model('taqdar_diag_model', 'tq_diag');
        $grade = (int) $this->db->select('grade_id')->where('id', (int) $uid)
                                ->get('users')->row('grade_id');
        $exam = $this->tq_diag->exam_for_grade($grade);

        /* اختبار بلا سؤال واحد ليس اختبارا: صف أنشئ ولم يؤلف. والفحص
           نفسه في `placement()` بالويب. */
        if (!$exam || array_sum($this->tq_diag->level_tally((int) $exam['id'])) < 1) return null;
        return $exam;
    }

    /** POST /api/v1/student/placement/start — يبدأ المحاولة ويضبط لحظة بدئها. */
    public function placement_start()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $exam = $this->placement_exam((int) $u['id']);
        if (!$exam) $this->fail('لا اختبار تحديد مستوى لصفك بعد.', 'placement_unavailable', 409);

        $this->tq_diag->start((int) $u['id'], (int) $exam['id']);

        $this->respond(tq_api_ok(array('exam_id' => (int) $exam['id']), 'بدأ الاختبار.'), 201);
    }

    /**
     * POST /api/v1/student/placement/submit
     *
     * `{"answers": {"<رقم السؤال>": "<نص الخيار>"}}` — والصحة تقرر في
     * `submit()` من `correct_answers` المقروءة من القاعدة، فمن أضاف
     * حقلا في حمولته لم يضف إلى نتيجته شيئا.
     */
    public function placement_submit()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid  = (int) $u['id'];
        $exam = $this->placement_exam($uid);
        if (!$exam) $this->fail('لا اختبار تحديد مستوى لصفك بعد.', 'placement_unavailable', 409);

        $given = $this->in('answers', array());
        if (!is_array($given)) {
            $this->fail('حقل الإجابات يجب أن يكون خريطة «رقم السؤال ← الخيار».',
                        'validation_failed', 422, array('answers' => array(t('القيمة غير مقبولة.'))));
        }

        $r = $this->tq_diag->submit($uid, (int) $exam['id'], $given);
        if (empty($r['ok'])) {
            $this->fail(implode(' ', (array) $r['errors']), 'placement_failed', 422);
        }

        /* الإبلاغ **بعد** حفظ النتيجة: من يقرر أمر الباقة هو من يدفع،
           وهو في الغالب ليس صاحب هذه الشاشة. وما لم يرسل يبقى بلا دمغة
           فيلتقطه `taqdar_cron_events placements`. */
        try { $this->tq_diag->notify_result((int) $r['attempt_id']); } catch (Throwable $e) {}

        $this->api->audit('api.placement.submit', $uid,
                          array('level' => $r['level'], 'score' => $r['score']));

        $this->respond(tq_api_ok(array(
            'attempt_id'  => (int) $r['attempt_id'],
            'level'       => (string) $r['level'],
            'level_label' => Taqdar_diag_model::level_label($r['level']),
            'score'       => (int) $r['score'],
            'total'       => (int) $r['total'],
        ), 'سجلت نتيجتك.'), 201);
    }


    /* ================================================================
       ٧ · الحصص بالطلب — وقت يباع لا محتوى
       ================================================================

       ودورة الحياة كلها في `Taqdar_sessions_model` (TQ-SESSION-PAY):
       يطلب الطالب، فيؤكد المعلم، فتصير `awaiting_payment` بمهلة، فيدفع
       فتصير `confirmed`، فيفتح الرابط قبل الموعد بمهلة، فينهيها المعلم
       فيقيد نصيبه. والواجهة تعرض وتنقل الطلب ولا تحكم في شيء منها. */

    /** نموذج الحصص. */
    private function sess()
    {
        $this->load->model('taqdar_sessions_model', 'tq_sess');
        return $this->tq_sess;
    }

    /**
     * GET /api/v1/student/sessions — حجوزاته والتسعيرة والمعلمون المتاحون.
     *
     * نداء واحد لا ثلاثة: الشاشة تعرض الثلاثة معا، وثلاثة أشواط على شبكة
     * جوال لرسم شاشة واحدة تقرأ بطئا لا معمارا. وهي قاعدة `student/home`
     * نفسها.
     */
    public function student_sessions()
    {
        /* بابان على مسار واحد كما في `student_messages()`: القراءة والطلب.
           والقاعدة في `routes.php` تربط المسار لا الطريقة. */
        if ($this->method(array('GET', 'POST')) === 'POST') {
            $this->session_request();
            return;
        }

        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $m   = $this->sess();
        $cfg = $m->config();

        $subject = (int) $this->input->get('subject');

        $bookings = array();
        foreach ((array) $m->bookings_for_student((int) $u['id']) as $b) {
            $bookings[] = $this->booking_out($b);
        }

        $this->load->model('taqdar_tap_model', 'tq_tap');

        /* TQ-SESSION-GRID — والترشيح بالصف يقع في الطبقة نفسها التي يقع
           فيها في الويب: المعلم يفتح لكل صف وقته، وتطبيق يعرض المواعيد
           كلها يجعل الطالب يطلب موعدا ليس لصفه فيرده الخادم — وهو رفض
           صحيح يقرأ عطلا في التطبيق. */
        $grade = $m->student_grade((int) $u['id']);

        $this->read(array(
            'bookings' => $bookings,
            'teachers' => $this->tutors_out($m->available_teachers(12, 6, $subject, $grade)),
            /* والصف يرد صريحا: التطبيق يقول «مواعيد صفك» أو يدعو إلى
               تحديد الصف، ولا يخمن سبب قصر القائمة. */
            'grade'    => array(
                'id'   => (int) $grade,
                'name' => $grade > 0 ? (string) $m->grade_name($grade) : null,
            ),
            'pricing'  => array(
                /* صفر يعني **مجانية بقرار** لا «لم تسعر»: حينها يؤكد
                   المعلم فتصير الحصة `confirmed` في الحال بلا فاتورة
                   ولا مهلة، وهو ما كان قبل TQ-SESSION-PAY حرفا بحرف. */
                'price'        => tq_api_money($cfg['price']),
                'free'         => ((int) $cfg['price'] <= 0),
                'minutes'      => (int) $cfg['minutes'],
                'pay_hours'    => (int) $cfg['pay_hours'],
                'join_lead_min'=> (int) $cfg['lead_min'],
                'grace_hours'  => (int) $cfg['grace_hours'],
            ),
            'card_enabled' => (bool) $this->tq_tap->ready(),
            /* `teacher_subjects()` ترد خريطة **معلم ← مادة** لا قائمة
               مواد، فتشتق القائمة من قيمها مفردة — وقراءة مفتاح مخترع
               هنا ترد فارغا صامتا فيقرأ التطبيق منتقيا بلا خيار. */
            'subjects'     => array_values(array_unique(array_filter(
                                  (array) ($m->teacher_subjects()['name'] ?? array())))),
        ), '', array('filters' => array('subject' => $subject)), $h);
    }

    /**
     * شكل الحجز.
     *
     * و`can_*` تخرج من `join_state()` نفسها التي تحرس الفتح في الويب:
     * زر «ادخل» يعرضه التطبيق بقاعدته هو يفتح غرفة قبل يومين أو بعد أن
     * انتهت — والحكم للخادم لا للساعة على الجهاز.
     */
    private function booking_out($b)
    {
        return array(
            'id'            => (int) $b['id'],
            'status'        => (string) $b['status'],
            /* `status_badge()` ترد `[نغمة, تسمية]` بمفاتيح رقمية لا
               `['label']`: مفتاح مخترع يطبع اسم الحالة الخام للطالب. */
            'status_label'  => (string) $this->sess()->status_badge($b['status'])[1],
            'teacher'       => array(
                'id'         => (int) $b['tutor_id'],
                'name'       => (string) $b['tutor'],
                'avatar_url' => tq_api_avatar($b['image']),
            ),
            'subject'    => (string) $b['subject'],
            'grade'      => ((int) ($b['grade_id'] ?? 0) > 0) ? array(
                'id'   => (int) $b['grade_id'],
                'name' => (string) $b['grade_name'],
            ) : null,
            'starts_at'  => tq_api_date($b['starts_at']),
            'when_text'  => (string) $b['when_text'],
            'minutes'    => (int) $b['minutes'],
            'price'      => tq_api_money($b['price']),
            'invoice'    => ((int) $b['invoice_id'] > 0) ? array(
                'id'     => (int) $b['invoice_id'],
                'no'     => (string) $b['invoice_no'],
                'total'  => tq_api_money($b['invoice_total']),
            ) : null,
            'pay_deadline'  => tq_api_date($b['pay_deadline']),
            'meet_url'      => $b['can_join'] ? (string) $b['meet_url'] : null,
            'cancel_reason' => (string) $b['cancel_reason'],
            'note'          => (string) $b['note'],
            'can_pay'       => (bool) $b['needs_pay'],
            'can_cancel'    => (bool) $b['can_cancel'],
            'can_join'      => (bool) $b['can_join'],
            'is_over'       => (bool) $b['is_over'],
        );
    }

    /** شكل المعلم المتاح ومواعيده. */
    private function tutors_out($rows)
    {
        $out = array();
        foreach ((array) $rows as $t) {
            $slots = array();
            foreach ((array) $t['slots'] as $s) {
                $slots[] = array(
                    'id'        => (int) $s['id'],
                    'starts_at' => tq_api_date($s['starts_at']),
                    'when_text' => (string) $s['when_text'],
                    'minutes'   => (int) $s['minutes'],
                    /* صفر يعني «كل الصفوف» لا «مجهول»، فيرد `null` لا صفرا:
                       عميل يطبع الرقم يكتب «صف #0» بجوار موعد صحيح. */
                    'grade'     => ((int) ($s['grade_id'] ?? 0) > 0) ? array(
                        'id'   => (int) $s['grade_id'],
                        'name' => (string) $s['grade_name'],
                    ) : null,
                    /* والمادة مع الصف: المعلم يفتح لأكثر من مادة، وشاشة
                       تعرض مواعيده بلا مادة تجعل الطالب يحجز ليسأل في غير
                       ما فتح له. وصفر يعني «فتح قبل TQ-SESSION-GRID». */
                    'subject'   => ((int) ($s['subject_id'] ?? 0) > 0) ? array(
                        'id'   => (int) $s['subject_id'],
                        'name' => (string) $s['subject_name'],
                    ) : null,
                );
            }
            /* **والتسعيرة تسعيرة هذا المعلم** لا العامة: العمود الفارغ
               يعني «خذ العام» والصفر يعني «مجانا بقرار»، و`pricing_for()`
               هي التي تفرق — ورقم عام يعرض على معلم له استثناء يجعل
               الطالب يقرأ ثمنا ويدفع آخر. */
            $out[] = array(
                'id'         => (int) $t['id'],
                'name'       => (string) $t['name'],
                'title'      => (string) $t['title'],
                'subject'    => (string) $t['subject'],
                'avatar_url' => tq_api_avatar($t['image']),
                'price'      => tq_api_money($t['pricing']['price']),
                'free'       => ((int) $t['pricing']['price'] <= 0),
                'slots'      => $slots,
            );
        }
        return $out;
    }

    /** POST /api/v1/student/sessions — يطلب موعدا من جدول معلم. */
    private function session_request()
    {
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array('slot_id' => 'required|int'));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $uid = (int) $u['id'];
        $r   = $this->sess()->request_session($uid, (int) $b['slot_id']);

        if (empty($r['ok'])) {
            $this->fail(isset($r['msg']) ? $r['msg'] : 'تعذر إرسال الطلب.', 'session_request_failed', 409);
        }

        /* يخبر المعلم أن طلبا ينتظر رده — وإلا لم يعرف إلا إن فتح شاشته.
           وفشل الإشعار لا يبطل الطلب: الصف كتب فعلا. */
        try {
            $tid = (int) ($r['teacher_id'] ?? 0);
            if ($tid > 0) {
                $this->load->model('taqdar_admin_model');
                $this->taqdar_admin_model->push_notification($tid, 'طلب حصة خاصة جديد',
                    'طلب أحد طلابك حصة خاصة على أحد مواعيدك. أكدها أو اعتذر عنها من شاشة «الحصص»'
                    . ' — والطلب بلا رد يلغى تلقائيا ويعاد للطالب.', 'session');
            }
        } catch (Throwable $e) {}

        $this->api->audit('api.session.request', $uid, array('slot_id' => (int) $b['slot_id']));

        $this->respond(tq_api_ok(array('session_id' => (int) ($r['id'] ?? 0)),
                                 isset($r['msg']) ? $r['msg'] : 'أرسل طلبك.'), 201);
    }

    /**
     * POST /api/v1/student/sessions/{id}/pay
     *
     * ولا يستقبل رقم فاتورة: يستقبل رقم **الحصة** ويشتق فاتورتها. ورقم
     * فاتورة يرسل من عميل يجعل من خمن رقما يفتح صفحة دفع لفاتورة غيره.
     */
    public function session_pay($id = 0)
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid = (int) $u['id'];
        $row = $this->db->where('id', (int) $id)->where('student_id', $uid)
                        ->get('tutoring_sessions')->row_array();

        if (!$row) $this->fail('لا حصة بهذا الرقم في حسابك.', 'not_found', 404);

        if ($row['status'] !== 'awaiting_payment' || (int) $row['invoice_id'] <= 0) {
            $this->fail('هذه الحصة ليست في انتظار الدفع. حدث الشاشة واقرأ حالها.',
                        'session_not_payable', 409);
        }

        $this->load->model('taqdar_tap_model', 'tq_tap');
        if (!$this->tq_tap->ready()) {
            $no = (string) $this->db->select('invoice_no')->where('id', (int) $row['invoice_id'])
                                    ->get('invoices')->row('invoice_no');
            $this->fail('الدفع بالبطاقة غير مفعل حاليا. حول قيمة الفاتورة ' . $no
                        . ' بنكيا وأبلغ الإدارة.', 'card_payment_disabled', 503);
        }

        $r = $this->tq_tap->start((int) $row['invoice_id'], $uid);
        if (empty($r['ok'])) {
            $this->fail(implode(' ', (array) $r['errors']), 'payment_start_failed', 502);
        }

        $this->respond(tq_api_ok(array(
            'payment_url' => $r['url'],
            'session_id'  => (int) $row['id'],
            'note'        => 'افتح الرابط في متصفح داخلي. تؤكد حصتك تلقائيا بعد نجاح الدفع.',
        ), 'جهزت صفحة الدفع.'), 200);
    }

    /**
     * POST /api/v1/student/sessions/{id}/cancel — قبل الدفع وحده.
     *
     * وبعد الدفع لا يلغي بنفسه: لا مسار استرداد آلي في هذا التركيب، وزر
     * يلغي بلا رد يترك الطالب بلا حصة وبلا مال. والنموذج يرفض ويقول لماذا.
     */
    public function session_cancel($id = 0)
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid = (int) $u['id'];
        $r = $this->sess()->student_cancel((int) $id, $uid, (string) $this->in('reason', ''));

        if (empty($r['ok'])) {
            $this->fail(isset($r['msg']) ? $r['msg'] : 'تعذر إلغاء الحصة.', 'session_cancel_failed', 409);
        }

        try {
            if (!empty($r['teacher_id'])) {
                $this->load->model('taqdar_admin_model');
                $this->taqdar_admin_model->push_notification((int) $r['teacher_id'],
                    'ألغى الطالب طلب الحصة',
                    'ألغى الطالب حجزه، وعاد الموعد متاحا في جدولك.', 'session');
            }
        } catch (Throwable $e) {}

        $this->api->audit('api.session.cancel', $uid, array('session_id' => (int) $id));

        $this->respond(tq_api_ok(null, isset($r['msg']) ? $r['msg'] : 'ألغي حجزك.'), 200);
    }

    /* ================================================================
       ٨ · المتجر — الباقة والمسار والكورس المفرد
       ================================================================

       ثلاث وحدات بيع على **مرساة واحدة**: الفاتورة. `subscribe()`
       للباقة و`subscribe_path()` للمسار و`subscribe_course()` للكورس
       المفرد — وثلاثتها تكتب صفا في `subscriptions` يفرق بـ`plan_id`/
       `path_id`/`course_id`، فكل ما بعد الفاتورة يتبعها بلا تعديل: تاب
       والتحويل البنكي والتفعيل والتجسيد وقسمة الإيراد.

       **والترتيب في كل شراء: الفاتورة أولا في الحالين.** لو أنشئت
       الدفعة عند تاب قبل أن تكتب الفاتورة لصار من دفع ثم سقط اتصاله قد
       دفع بلا صف عندنا يقابل دفعته. */

    /**
     * GET /api/v1/student/plans — الباقات المعروضة بدوراتها.
     *
     * **والدورة تشترى** (TQ-CYCLE-BUY): الشهري والسنوي سعران لصف باقة
     * واحد، لا صفان. فتخرج `cycles` مع كل باقة، ومفتاحها هو ما يرسله
     * التطبيق في الشراء — وواجهة تعرض سعر الشهر ثم تشتري بلا مفتاح
     * تجعل من ضغط «شهري» يدفع سعر السنة.
     *
     * والمعروضة هي `scope = 'grade'` وحدها كما في `/plans`: باقة بنطاق
     * آخر تشترى برمزها ولا تظهر في قائمة عامة.
     */
    public function student_plans()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->load->model('taqdar_billing_model', 'tq_bill');

        $out = array();
        foreach ((array) $this->tq_bill->plans(true) as $p) {
            if ((string) $p['scope'] !== 'grade') continue;
            $out[] = $this->plan_out($p);
        }

        $this->load->model('taqdar_tap_model', 'tq_tap');

        $this->read($out, '', array(
            'count'        => count($out),
            'card_enabled' => (bool) $this->tq_tap->ready(),
            'bank'         => $this->bank_out(null),
            'current'      => $this->tq_bill->active_subscription((int) $u['id']) ? true : false,
        ), $h);
    }

    /**
     * GET /api/v1/student/plans/{code} — باقة بعينها ومحتواها.
     *
     * ومحتوى الباقة **مستنتج لا مسرود**: السلسلة
     * `plans.scope_ids → grades → paths → course → section → lesson`.
     * ولا حقل يربط درسا بباقة، ولو وجد لصار كل درس جديد يحتاج مرورا على
     * كل باقة — ولنسي.
     */
    public function student_plan($code = '')
    {
        $this->method('GET');
        $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->load->model('taqdar_billing_model', 'tq_bill');
        $plan = $this->tq_bill->plan_by_code(rawurldecode((string) $code));

        if (!$plan || (int) $plan['active'] !== 1) {
            $this->fail('لا باقة بهذا الرمز.', 'not_found', 404);
        }

        $out = $this->plan_out($plan);

        $this->load->model('taqdar_site_model', 'tq_site');
        $bundle = $this->tq_site->bundle_by_code($plan['code']);
        $out['contents'] = $bundle ? array(
            'totals'   => $bundle['totals'],
            'features' => $bundle['features'],
            'grades'   => array_values($bundle['grades']),
        ) : null;

        $this->read($out, '', array(), $h);
    }

    /**
     * POST /api/v1/student/subscribe — يشترك في باقة بدورة.
     *
     * `cycle` يمرر ولا يفسر هنا: `cycle_of()` تحرسه، فمفتاح لا تعرفه
     * الباقة يقع على **دورتها هي** لا على الأرخص — والارتداد إلى الأرخص
     * يجعل تعديل حرف في الحمولة يشتري السنة بسعر الشهر.
     */
    public function student_subscribe()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array('plan_id' => 'required|int'));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $this->buy(function ($uid, $method) use ($b) {
            $this->load->model('taqdar_billing_model', 'tq_bill');
            return $this->tq_bill->subscribe($uid, (int) $b['plan_id'], $method,
                                             (string) ($b['cycle'] ?? ''));
        }, 'api.subscribe.plan', 'صدرت فاتورتك. حول قيمتها ويفعل اشتراكك بعد التحقق من الحوالة.');
    }

    /** POST /api/v1/student/subscribe-path — يشتري مسارا مفردا. */
    public function subscribe_path()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array('path_id' => 'required|int'));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $this->buy(function ($uid, $method) use ($b) {
            $this->load->model('taqdar_billing_model', 'tq_bill');
            return $this->tq_bill->subscribe_path($uid, (int) $b['path_id'], $method);
        }, 'api.subscribe.path', 'صدرت فاتورتك. حول قيمتها ويفتح المسار بعد التحقق من الحوالة.');
    }

    /**
     * POST /api/v1/student/buy-course — TQ-COURSE-SALE.
     *
     * **ولا يمنع الباقة ولا تمنعه**: الباقة تمنع الباقة لأنهما شيء واحد
     * يشترى مرتين؛ والكورس المفرد شيء آخر — ومن له باقة صفه واشترى فوقها
     * مادة إثرائية اشترى شيئين لا شيئا مكررا. ويمنع **تكرار نفسه** وحده،
     * والفحص في `subscribe_course()` على `has_course()` نفسها التي تحرس
     * المشغل: فلا يباع لمن يملك، ولا تعد الشاشة بما يمنعه الحارس.
     */
    public function buy_course()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array('course_id' => 'required|int'));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $this->buy(function ($uid, $method) use ($b) {
            $this->load->model('taqdar_billing_model', 'tq_bill');
            return $this->tq_bill->subscribe_course($uid, (int) $b['course_id'], $method);
        }, 'api.buy.course', 'صدرت فاتورتك. حول قيمتها ويفتح الكورس بعد التحقق من الحوالة.');
    }

    /**
     * POST /api/v1/student/buy-book — شراء كتاب مفردا (TQ-BOOK).
     *
     * وعلى مسار الشراء الواحد نفسه: الفاتورة أولا ثم الدفع، ورمز الرفض
     * يخرج كما هو (`ALREADY_OWNED` · `NOT_SELLABLE`) فيفرع عليه التطبيق.
     */
    public function buy_book()
    {
        $this->method('POST');
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array('book_id' => 'required|int'));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $this->buy(function ($uid, $method) use ($b) {
            $this->load->model('taqdar_billing_model', 'tq_bill');
            return $this->tq_bill->subscribe_book($uid, (int) $b['book_id'], $method);
        }, 'api.buy.book', 'صدرت فاتورتك. حول قيمتها ويفتح الكتاب في مكتبتك بعد التحقق من الحوالة.');
    }

    /**
     * مسار الشراء الواحد للوحدات الثلاث.
     *
     * الفاتورة أولا ثم الدفع — في الباقة والمسار والكورس سواء. وثلاث
     * نسخ من هذا الترتيب تعني أن واحدة منها ستكتب يوما بالعكس: دفعة قبل
     * صف، فمن دفع ثم سقط اتصاله دفع بلا شيء عندنا يقابله.
     *
     * و**الرد لا يحول**: الويب يعيد التوجيه إلى تاب، والتطبيق يفتح
     * `payment_url` في متصفح داخلي. وتحويل 302 على واجهة JSON يجعل عميل
     * Dart يتبعه فيقرأ HTML صفحة الدفع ويرمي `FormatException`.
     */
    private function buy(callable $run, $audit, $bank_message)
    {
        $uid = (int) $this->me['id'];

        $this->load->model('taqdar_tap_model', 'tq_tap');
        $by_card = ((string) $this->in('pay_method', 'manual') === 'tap')
                && $this->tq_tap->ready();

        $r = $run($uid, $by_card ? 'tap' : 'manual');

        if (empty($r['ok'])) {
            /* رمز النموذج يخرج كما هو حين يوجد (`PLACEMENT_REQUIRED` ·
               `ALREADY_OWNED` · `NOT_SELLABLE`): عليه يفرع التطبيق فيفتح
               شاشة التشخيص أو يقول «تملكه بالفعل» — ورمز واحد لكل رفض
               يجعله يعرض الرسالة ولا يعرف ماذا يفعل بعدها. */
            $code = isset($r['code']) ? strtolower((string) $r['code']) : 'purchase_failed';
            $this->fail(implode(' ', (array) $r['errors']), $code, 409);
        }

        $this->api->audit($audit, $uid, array(
            'subscription_id' => (int) ($r['subscription_id'] ?? 0),
            'invoice_id'      => (int) ($r['invoice_id'] ?? 0),
        ));

        /* الباقة المجانية تفعل في الحال: لا فاتورة تدفع ولا رابط يفتح. */
        if (!empty($r['free'])) {
            $this->respond(tq_api_ok(array(
                'subscription_id' => (int) $r['subscription_id'],
                'free'            => true,
                'invoice'         => null,
                'payment_url'     => null,
            ), 'فعلت باقتك المجانية.'), 201);
        }

        $inv = $this->db->where('id', (int) $r['invoice_id'])->get('invoices')->row_array();

        if ($by_card) {
            $pay = $this->tq_tap->start((int) $r['invoice_id'], $uid);
            if (!empty($pay['ok'])) {
                $this->respond(tq_api_ok(array(
                    'subscription_id' => (int) $r['subscription_id'],
                    'free'            => false,
                    'invoice'         => $inv ? $this->invoice_out($inv) : null,
                    'payment_url'     => $pay['url'],
                ), 'جهزت صفحة الدفع.'), 201);
            }

            /* تعذر بدء الدفع: **الفاتورة صدرت ولم تضع**، فيقال ما وقع
               ويدل على البديل القائم — ولا يرد بخطأ عار يجعل صاحبه يعيد
               الشراء فيصير له اشتراكان معلقان. */
            $this->respond(tq_api_ok(array(
                'subscription_id' => (int) $r['subscription_id'],
                'free'            => false,
                'invoice'         => $inv ? $this->invoice_out($inv) : null,
                'payment_url'     => null,
                'bank'            => $this->bank_out($inv ? (string) $inv['invoice_no'] : null),
            ), implode(' ', (array) $pay['errors'])
               . ' وفاتورتك صدرت، فيمكنك تحويل قيمتها بنكيا أو إعادة المحاولة.'), 201);
        }

        $this->respond(tq_api_ok(array(
            'subscription_id' => (int) $r['subscription_id'],
            'free'            => false,
            'invoice'         => $inv ? $this->invoice_out($inv) : null,
            'payment_url'     => null,
            'bank'            => $this->bank_out($inv ? (string) $inv['invoice_no'] : null),
        ), $bank_message), 201);
    }

    /**
     * GET /api/v1/student/store/courses — الكورسات المعروضة مفردة.
     *
     * **وبلا مفتاح لا شيء يظهر**: `tq_course_sales_enabled` مطفأ افتراضا،
     * وحينها ترد `offer()` «لا يباع» لكل كورس فتخرج القائمة فارغة —
     * كما تعرض الصفحات في الويب ما كانت تعرضه حرفا بحرف.
     */
    public function store_courses()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $this->load->model('taqdar_course_sale_model', 'tq_cs');
        $this->load->model('taqdar_billing_model', 'tq_bill');

        $q = trim((string) $this->input->get('q'));

        $list = array();
        foreach ((array) $this->tq_cs->offers(true) as $o) {
            if (!$o['sellable']) continue;
            if ($q !== '' && mb_stripos($o['title'], $q) === false) continue;
            $list[] = $this->offer_out($o, $uid);
        }

        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 50, 20);

        $this->load->model('taqdar_tap_model', 'tq_tap');

        $this->read(array_slice($list, $offset, $per), '', array_merge(
            tq_api_meta_page($page, $per, count($list)),
            array('enabled' => (bool) $this->tq_cs->enabled(),
                  'card_enabled' => (bool) $this->tq_tap->ready(),
                  'filters' => array('q' => $q))
        ), $h);
    }

    /**
     * GET /api/v1/student/store/courses/{id} — عرض كورس واحد.
     *
     * ويعرض **العرضين مرتبين**: الشراء المفرد أولا لأن من فتح مادة بعينها
     * جاء يسأل عنها، والباقة تحته **بفارق السعر لا بسعرها** — «وبكذا
     * زيادة تفتح المرحلة كلها» يقارن ما يقارن؛ ورقمان متجاوران بلا جسر
     * يجعلان المشتري يوازن بين خيارين ولا يعرف ما يشتريه أحدهما زيادة.
     */
    public function store_course($id = 0)
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $this->load->model('taqdar_course_sale_model', 'tq_cs');

        $offer = $this->tq_cs->offer((int) $id);
        if ((int) $offer['course_id'] <= 0) $this->fail('لا كورس بهذا الرقم.', 'not_found', 404);

        $out = $this->offer_out($offer, $uid);

        /* الباقات التي تفتح هذا الكورس — بفارق السعر لا بسعرها. */
        $this->load->model('taqdar_site_model', 'tq_site');
        $plans = array();
        foreach ((array) $this->tq_site->plans_for_course((int) $offer['course_id']) as $p) {
            $row = isset($p['id']) ? $p : null;
            if (!$row) continue;
            $card = $this->plan_out($row);
            $card['extra_over_course'] = ($offer['sellable'] && (int) $row['price'] > (int) $offer['price'])
                ? tq_api_money((int) $row['price'] - (int) $offer['price'])
                : null;
            $plans[] = $card;
        }
        $out['plans'] = $plans;

        $this->read($out, '', array(), $h);
    }

    /**
     * شكل العرض المفرد.
     *
     * و`sellable` و`reason` يخرجان معا: الأول يفرع عليه التطبيق، والثاني
     * يقول **لماذا** — «مجاني» غير «لم يعلن للبيع» غير «تملكه». ورد واحد
     * لكل امتناع يجعل الزر يختفي بلا سبب يقرأ.
     */
    private function offer_out($o, $uid)
    {
        $this->load->model('taqdar_billing_model', 'tq_bill');
        $owned = $this->tq_bill->has_course((int) $uid, (int) $o['course_id']);

        /* الأعمدة **مؤهلة بالاسم المستعار**: `users` فيه `id` كذلك،
           فـ`select('id, …')` مع ضم عليه يرد «Column 'id' is ambiguous»
           — خطأ لا يظهر إلا حين يوجد الضم، فيمر في الاختبار على جدول
           بلا ضم ويسقط في الشاشة التي تعرض اسم المعلم. */
        $c = $this->db->select('c.id, c.title, c.thumbnail, c.level, c.short_description,'
                    . ' TRIM(CONCAT(COALESCE(t.first_name,""), " ", COALESCE(t.last_name,""))) AS teacher_name')
             ->from('course c')->join('users t', 't.id = c.creator', 'left')
             ->where('c.id', (int) $o['course_id'])->get()->row_array();

        return array(
            'course_id'  => (int) $o['course_id'],
            'title'      => (string) $o['title'],
            'summary'    => (string) ($c['short_description'] ?? ''),
            'level'      => (string) ($c['level'] ?? ''),
            'teacher'    => trim((string) ($c['teacher_name'] ?? '')) ?: null,
            'thumbnail'  => $this->thumb_url($c['thumbnail'] ?? ''),
            'sellable'   => (bool) $o['sellable'],
            'reason'     => (string) $o['reason'],
            'why'        => (string) $o['why'],
            'owned'      => (bool) $owned,
            'price'      => tq_api_money($o['price']),
            /* `list_price` صفر يعني «لا خصم» — و`null` أوضح للعميل من صفر
               يرسمه شطبا فوق مجانية لا وجود لها. */
            'list_price' => ((int) $o['list_price'] > 0) ? tq_api_money($o['list_price']) : null,
            'discount'   => (int) $o['off'],
            /* صفر يوما يعني وصولا **دائما**: `ends_at` تبقى `NULL` و
               `expire_due()` تشترط `IS NOT NULL` فلا تلمسه. وتاريخ بعيد
               مخترع ينتهي يوما ويقفل ما بيع على أنه دائم. */
            'access_days'=> (int) $o['days'],
            'lifetime'   => ((int) $o['days'] <= 0),
            'web_url'    => base_url('course-checkout/' . (int) $o['course_id']),
        );
    }

    /**
     * GET /api/v1/student/purchases — كل ما يسري لا أوله.
     *
     * TQ-MULTI-SUB — `active_subscription()` بالمفرد ترد **صفا واحدا**
     * وتتخطى الشراء المفرد عمدا، لأن أحد عشر مستدعيا يسألونها سؤالا
     * واحدا: «ما **باقة** هذا الطالب؟». وهذه النقطة تسأل غيره: «ماذا
     * يملك؟» — ومن له باقة صف واشترى فوقها مادة يملك صفين، وصف واحد
     * يقرأ يعني أن أحد الشراءين لا يظهر لصاحبه في شاشة واحدة.
     */
    public function student_purchases()
    {
        $this->method('GET');
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $this->load->model('taqdar_billing_model', 'tq_bill');

        $out = array();
        foreach ((array) $this->tq_bill->active_subscriptions($uid) as $s) {
            $out[] = $this->purchase_out($s);
        }

        $this->read($out, '', array('count' => count($out)), $h);
    }

    /**
     * شكل الشراء — والاسم يقرأ من الثلاثة لا من `plans` وحده.
     *
     * الضم على `plans` وحده يطبع «—» على شراء مسار أو كورس، وقد كان
     * يفعل في شاشة الاشتراكات وفي إشعار إصدار الفاتورة وفي إشعار نجاح
     * الدفع وفي التفعيل اليدوي.
     */
    private function purchase_out($s)
    {
        $this->load->model('taqdar_billing_model', 'tq_bill');

        /* TQ-SOLD-NAME — النوع والاسم من `sold()` وحدها.
           كانت هذه سلسلة `if` رابعة تكتب القاعدة نفسها بيدها، و**بلا فرع
           للكتاب**: صف شراء كتاب يحمل `plan_id = 0`، فيسقط إلى الفرع
           الأخير ويخرج إلى التطبيق `kind: 'plan'` باسم «باقة #0».
           فمن اشترى كتابا يقرأ في «مشترياتي» باقة لا وجود لها.
           TQ-PLAN-DELETE — وما حذف يقال بالرقم، وهو في `sold()` كذلك. */
        $sold  = $this->tq_bill->sold($s);
        $kind  = (string) $sold['kind'];
        $ref   = (int) $sold['id'];
        $title = (string) $sold['title'];
        $code  = $sold['code'];

        $days_left = null;
        if (!empty($s['ends_at'])) {
            $days_left = max(0, (int) ceil((strtotime($s['ends_at']) - time()) / 86400));
        }

        return array(
            'id'         => (int) $s['id'],
            'kind'       => $kind,
            'ref_id'     => $ref,
            'code'       => $code,
            'title'      => $title !== '' ? $title : t('شراء'),
            'status'     => (string) $s['status'],
            'price'      => tq_api_money($s['price']),
            /* الفارغ يخرج `null` لا `""`: عمود `cycle` أضيف بعد أن بيعت
               اشتراكات، فصفوفها القديمة تحمله فارغا — وسلسلة فارغة ترسم
               رقاقة دورة بلا نص. */
            'cycle'      => trim((string) ($s['cycle'] ?? '')) ?: null,
            'started_at' => tq_api_date($s['started_at']),
            'ends_at'    => tq_api_date($s['ends_at']),
            /* `null` يعني **دائما** لا «مجهولا»: الكورس المفرد بأجل صفر
               لا `ends_at` له، وعدد أيام مخترع يقفل ما بيع على أنه دائم. */
            'days_left'  => $days_left,
            'lifetime'   => empty($s['ends_at']),
        );
    }

    /**
     * كل مسار تحت `api/v1/` لا قاعدة له.
     *
     * ولا يترك لـ`show_404()`: تلك ترد صفحة HTML كاملة بترويسة وتذييل،
     * فيقرأ عميل Flutter `<!doctype html` ويرمي `FormatException` بدل أن
     * يقول للمستخدم «المسار غير موجود».
     */
    public function not_found()
    {
        $this->fail('لا توجد نقطة بهذا المسار. راجع ' . base_url('api/docs'),
                    'not_found', 404);
    }

    /* =====================================================================
       بوابتا المعلم وولي الأمر
       ---------------------------------------------------------------------
       ولا قاعدة عمل واحدة تكتب هنا، كما لا تكتب في نقاط الطالب: هذه
       الطبقة تنادي `Taqdar_teacher_model` و`Taqdar_parent_model` و
       `Taqdar_marking_model` و`Taqdar_wallet_model` و`Taqdar_sessions_model`
       و`Taqdar_curriculum_model` — الطبقة نفسها التي تناديها شاشات الويب.
       فما يعتمده المعلم في التطبيق يعتمده في الموقع بالحكم نفسه، وما يراه
       ولي الأمر هنا هو ما يراه هناك بحاجز الرؤية نفسه.

       **والصندوق الوارد واحد للأدوار الثلاثة** — الإشعارات والمحادثات
       جدولاهما `notifications` و`message` موصولان بالمستخدم لا بدوره،
       فالنقاط نفسها تخدم `/student/*` و`/teacher/*` و`/parent/*`
       (`require_portal()`). والذي يفرق بالدور شيء واحد: **من يجوز
       مراسلته** — وهو `recipients_of()` وحدها. ونسخة ثانية من قائمة
       المحادثات لكل دور تعني ثلاث نسخ تفترق عند أول تعديل، وهي علة
       TQ-SOLD-NAME نفسها.
       ===================================================================== */

    /**
     * حارس البوابات: يستوثق ويرد المستخدم، ويسجل دوره في `$this->role`.
     *
     * و`require_student()` تبقى كما هي لنقاط الطالب: هي تقول لمن ناداها
     * بدور آخر **إن بوابته لم تصدر بعد** — وهي رسالة صارت كاذبة اليوم في
     * الرسائل والإشعارات، فتلك تنادي هذه.
     *
     * @param array|null $allowed الأدوار المقبولة، أو `null` لأي دور بوابة
     */
    private function require_portal($allowed = null)
    {
        $u    = $this->authenticate();
        $role = tq_role((int) $u['id']);

        if ($role === 'admin') {
            /* الأدمن لا يدخل من التطبيق — واللوحة على الويب بحراسها. */
            $this->fail('لوحة الإدارة لا تفتح من التطبيق.', 'wrong_role', 403);
        }

        if ($allowed !== null && !in_array($role, (array) $allowed, true)) {
            $this->fail('هذه النقطة ليست لبوابتك.', 'wrong_role', 403);
        }

        $this->role = $role;
        return $u;
    }

    /** حارس المعلم. */
    private function require_teacher() { return $this->require_portal(array('teacher')); }

    /** حارس ولي الأمر. */
    private function require_parent()  { return $this->require_portal(array('parent')); }

    /**
     * رسالة نتيجة من نموذج — والاصطلاح **ثلاثة** في هذه الشجرة لا واحد.
     *
     * `Taqdar_billing_model` يرد `errors` مصفوفة، و`Taqdar_sessions_model`
     * يرد `msg` نصا، و`Taqdar_marking_model` و`Taqdar_parent_model` يردان
     * `message`. وقراءة مفتاح واحد منها تجعل رفضا صحيحا يخرج إلى التطبيق
     * برسالة فارغة: الحصة لا تؤكد ولا يقال لماذا. والتوحيد هنا لأن
     * النماذج مشتركة مع الويب فلا تمس اصطلاحاتها.
     */
    private function model_msg($r, $fallback)
    {
        if (!empty($r['errors'])) return implode(' ', (array) $r['errors']);
        if (!empty($r['msg']))     return (string) $r['msg'];
        if (!empty($r['message'])) return (string) $r['message'];
        return $fallback;
    }

    /* ---- النماذج، محملة عند أول نداء لا في المنشئ ------------------- */

    private function tm()
    {
        $this->load->model('taqdar_teacher_model', 'tq_tm');
        return $this->tq_tm;
    }

    private function pm()
    {
        $this->load->model('taqdar_parent_model', 'tq_pm');
        return $this->tq_pm;
    }

    private function mk()
    {
        $this->load->model('taqdar_marking_model', 'tq_mk');
        $this->tq_mk->ensure_schema();
        return $this->tq_mk;
    }

    private function wal()
    {
        $this->load->model('taqdar_wallet_model', 'tq_wal');
        return $this->tq_wal;
    }

    private function cur()
    {
        $this->load->model('taqdar_curriculum_model', 'tq_cur');
        return $this->tq_cur;
    }

    /* =================================================================
       الصندوق الوارد — الفارق الوحيد بين الأدوار
       ================================================================= */

    /**
     * من يجوز لهذا المستخدم مراسلته — بحسب دوره، ومن المصدر نفسه الذي
     * يفحص به الإرسال.
     *
     * منتق يعرض حسابا يرده الحارس يجعل صاحبه يقرأ رفضا عن اسم عرضناه
     * نحن — ولذلك القائمة والفحص من دالة واحدة في كل دور.
     */
    private function recipients_of($uid)
    {
        $out = array();

        if ($this->role === 'parent') {
            /* شكل ولي الأمر غير الشكلين: صفوفه معلمون بأسمائهم ومواد
               أبنائهم، والدعم بينهم بعلامته. */
            foreach ($this->pm()->recipients_for($uid) as $p) {
                $out[] = array(
                    'id'         => (int) $p['id'],
                    'name'       => (string) $p['name'],
                    'role'       => !empty($p['support']) ? 'support' : 'teacher',
                    'avatar_url' => tq_api_avatar(''),
                    'context'    => array_values((array) ($p['courses'] ?? array())),
                    'children'   => array_values((array) ($p['children'] ?? array())),
                );
            }
            return $out;
        }

        $rows = ($this->role === 'teacher')
              ? $this->tm()->messageable($uid)
              : $this->stu()->messageable($uid);

        foreach ($rows as $p) {
            /* و`kind` من النموذج يسبق الاشتقاق حين يوجد: حساب الإدارة قد
               يحمل `is_instructor = 1` (كل حسابات الاختبار كذلك)، فاشتقاق
               الدور من العمودين وحدهما يسمي «إدارة المنصة» معلما — ويقرأ
               المعلم في قائمته اسم زميل لا قناة دعم. */
            $is_support = (isset($p['kind']) && $p['kind'] === 'admin')
                       || (((int) ($p['role_id'] ?? 0) === 1) && empty($p['is_instructor']));
            $out[] = array(
                'id'         => (int) $p['id'],
                'name'       => $is_support ? t('إدارة المنصة')
                              : trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
                'role'       => $is_support ? 'support'
                              : (!empty($p['is_instructor']) ? 'teacher' : 'student'),
                'avatar_url' => tq_api_avatar($p['image'] ?? ''),
            );
        }
        return $out;
    }

    /** الفحص من القائمة نفسها — لا نسخة ثانية من قاعدة النطاق. */
    private function may_message_to($uid, $to)
    {
        $to = (int) $to;
        if ($to <= 0 || $to === (int) $uid) return false;

        foreach ($this->recipients_of($uid) as $p) {
            if ((int) $p['id'] === $to) return true;
        }
        return false;
    }

    /** جملة النطاق — تعرض في القائمة وتقال عند الرفض، فلا تفترقان. */
    private function recipients_note()
    {
        if ($this->role === 'teacher') {
            return t('المراسلة متاحة مع طلاب كورساتك وإدارة المنصة.');
        }
        if ($this->role === 'parent') {
            return t('المراسلة متاحة مع معلمي مواد أبنائك وإدارة المنصة.');
        }
        return t('المراسلة متاحة مع معلميك والدعم فقط، ولا رسائل خاصة بين الطلاب.');
    }

    /* =================================================================
       بوابة المعلم
       ================================================================= */

    /**
     * GET /api/v1/teacher/home — لوحة المعلم.
     *
     * أربعة أرقام و«يحتاج انتباهك» — والترتيب ترتيب السؤال: ما الذي
     * يحتاج فعلك اليوم قبل أي رقم آخر. والحساب كله في
     * `Taqdar_teacher_model::dashboard()`، وهو المصدر الذي تقرأ منه
     * الشاشة في الويب كذلك.
     */
    public function teacher_home()
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $d   = $this->tm()->dashboard($uid);

        $this->load->model('taqdar_sessions_model', 'tq_sess');
        $sessions = $this->tq_sess->teacher_summary($uid);

        $attention = array();
        foreach ($d['attention'] as $a) {
            $attention[] = array(
                'student_id'     => (int) $a['student_id'],
                'name'           => (string) $a['name'],
                'avatar_url'     => tq_api_avatar($a['image']),
                'course_id'      => (int) $a['course_id'],
                'course_title'   => (string) $a['course_title'],
                'progress'       => (int) $a['progress'],
                'days_away'      => (int) $a['days_away'],
                'failed_quizzes' => (int) $a['failed_quizzes'],
                /* `reason` مفتاح يفرع عليه التطبيق، و`reason_label` نص
                   يعرض — ورد واحد لهما يجعله يعرض ولا يعرف ماذا يفعل. */
                'reason'         => (string) $a['reason'],
                'reason_label'   => $this->attention_label($a),
            );
        }

        $hard = array();
        foreach ($d['hard_lessons'] as $l) {
            $started  = max(1, (int) $l['started']);
            $hard[] = array(
                'lesson_id'    => (int) $l['id'],
                'title'        => (string) $l['title'],
                'course_title' => (string) $l['course_title'],
                'started'      => (int) $l['started'],
                'finished'     => (int) $l['finished'],
                'finish_rate'  => (int) round(100 * (int) $l['finished'] / $started),
            );
        }

        $this->read(array(
            'stats' => array(
                'students'         => (int) $d['students'],
                'courses'          => count($d['courses']),
                'pending_marking'  => (int) $d['pending_marking'],
                'pending_quizzes'  => (int) $d['pending_quizzes'],
                'pending_homework' => (int) $d['pending_homework'],
                'month_earnings'   => tq_api_money((int) $d['month_earnings']),
            ),
            'attention'       => $attention,
            'attention_total' => (int) $d['attention_total'],
            'hard_lessons'    => $hard,
            'sessions'        => array(
                'pending'  => (int) $sessions['pending'],
                'unpaid'   => (int) $sessions['unpaid'],
                'booked'   => (int) $sessions['booked'],
                'upcoming' => tq_api_money((int) $sessions['upcoming']),
            ),
            'inbox' => array(
                'messages'      => $this->unread_messages($uid),
                'notifications' => $this->unread_notifications($uid),
            ),
        ), '', array('pass_percent' => (int) $d['pass_percent']), $h);
    }

    /** نص سبب التعثر — والأسباب الثلاثة تعالج بغير ما يعالج به الآخر. */
    private function attention_label($a)
    {
        if ($a['reason'] === 'failing') {
            return t('رسب في ') . (int) $a['failed_quizzes'] . t(' اختبار');
        }
        if ($a['reason'] === 'at_risk') {
            return t('يوشك على الانقطاع — غاب ') . (int) $a['days_away'] . t(' يوما');
        }
        return t('توقف عند ') . (int) $a['progress'] . t('٪');
    }

    private function unread_messages($uid)
    {
        return (int) $this->db->where('receiver', $uid)->where('read_status', 0)
                              ->count_all_results('message');
    }

    private function unread_notifications($uid)
    {
        return (int) $this->db->where('to_user', $uid)->where('status', 0)
                              ->count_all_results('notifications');
    }

    /**
     * GET /api/v1/teacher/courses — كورسات المعلم.
     *
     * والنطاق صورتان لا واحدة (`creator` و`user_id`)، فالمعلم المشارك
     * يرى ما شارك فيه.
     */
    public function teacher_courses()
    {
        /* والباب واحد للقراءة والإنشاء: مسار ثان لـ«كورس جديد» يعني
           قاعدتين في `routes.php` تفترقان عند أول تعديل. */
        if ($this->method(array('GET', 'POST')) === 'POST') $this->teacher_course_create();

        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid  = (int) $u['id'];
        $rows = $this->tm()->scope_courses($uid);
        $ids  = array_map('intval', array_column($rows, 'id'));

        /* عدد الأقسام والدروس والمسجلين — استعلام واحد لكل المعروض لا
           ثلاثة لكل صف: قائمة من عشرين كورسا كانت ستفتح ستين استعلاما. */
        $sec = $les = $enr = array();
        if ($ids) {
            $in = implode(',', $ids);
            foreach ($this->db->query("SELECT `course_id` c, COUNT(*) n FROM `section`
                                        WHERE `course_id` IN ($in) GROUP BY `course_id`")
                     ->result_array() as $r) $sec[(int) $r['c']] = (int) $r['n'];
            foreach ($this->db->query("SELECT `course_id` c, COUNT(*) n FROM `lesson`
                                        WHERE `course_id` IN ($in) GROUP BY `course_id`")
                     ->result_array() as $r) $les[(int) $r['c']] = (int) $r['n'];
            foreach ($this->db->query("SELECT `course_id` c, COUNT(*) n FROM `enrol`
                                        WHERE `course_id` IN ($in) GROUP BY `course_id`")
                     ->result_array() as $r) $enr[(int) $r['c']] = (int) $r['n'];
        }

        $out = array();
        foreach ($rows as $c) {
            $id = (int) $c['id'];
            $out[] = array(
                'id'            => $id,
                'title'         => (string) $c['title'],
                'status'        => (string) $c['status'],
                'thumbnail_url' => $this->thumb_url($c['thumbnail'] ?? ''),
                'sections'      => isset($sec[$id]) ? $sec[$id] : 0,
                'lessons'       => isset($les[$id]) ? $les[$id] : 0,
                'students'      => isset($enr[$id]) ? $enr[$id] : 0,
                'created_at'    => tq_api_date($c['date_added']),
            );
        }

        $this->read($out, '', array('total' => count($out)), $h);
    }

    /**
     * GET /api/v1/teacher/courses/{id} — منهج الكورس.
     *
     * والملكية تفحص في `Taqdar_curriculum_model::may_edit_course()` —
     * الحكم الواحد الذي تفحص به شاشة الويب كذلك، فلا يفتح باب ما يرده
     * الآخر.
     */
    public function teacher_course($id = 0)
    {
        if ($this->method(array('GET', 'PATCH', 'POST')) !== 'GET') $this->teacher_course_update($id);

        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $id    = (int) $id;
        $actor = $this->cur()->actor_as('teacher', (int) $u['id']);

        if (!$this->cur()->may_edit_course($actor, $id)) {
            /* رد واحد للمعدوم ولكورس غيره عمدا: التفريق يقول لمن خمن
               رقما إن الكورس موجود. */
            $this->fail('لا كورس بهذا الرقم في نطاقك.', 'not_found', 404);
        }

        $course   = $this->cur()->course($id);
        $sections = array();

        foreach ($this->cur()->sections_of($id) as $s) {
            $lessons = array();
            foreach ($this->cur()->lessons_of($id, (int) $s['id']) as $l) {
                $lessons[] = array(
                    'id'         => (int) $l['id'],
                    'title'      => (string) $l['title'],
                    'kind'       => Taqdar_curriculum_model::kind_of($l),
                    'duration'   => (string) ($l['duration'] ?? ''),
                    'is_free'    => !empty($l['is_free']),
                    'status'     => (string) ($l['tq_status'] ?? 'published'),
                    'order'      => (int) ($l['order'] ?? 0),
                );
            }
            $sections[] = array(
                'id'      => (int) $s['id'],
                'title'   => (string) $s['title'],
                'order'   => (int) ($s['order'] ?? 0),
                'lessons' => $lessons,
            );
        }

        $this->read(array(
            'id'            => $id,
            'title'         => (string) ($course['title'] ?? ''),
            'status'        => (string) ($course['status'] ?? ''),
            'thumbnail_url' => $this->thumb_url($course['thumbnail'] ?? ''),
            'sections'      => $sections,
            /* TQ-DURATION — والمدة المكتوبة ادعاء، والقياس يخالفها أحيانا.
               فيقال للمعلم في التطبيق ما يقال له في اللوحة: بالرقمين. */
            'duration_conflicts' => $this->cur()->duration_conflicts($id),
        ), '', array(), $h);
    }

    /**
     * GET /api/v1/teacher/lessons — دروس المعلم بمرشحاتها.
     *
     * والترشيح في الخادم لا في التطبيق: نسختان من قواعده تفترقان عند أول
     * تعديل، وهي قاعدة `/catalog` نفسها.
     */
    public function teacher_lessons()
    {
        if ($this->method(array('GET', 'POST')) === 'POST') $this->teacher_lesson_create();

        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $f = array(
            'course' => (int) $this->input->get('course'),
            'status' => (string) $this->input->get('status'),
            'type'   => (string) $this->input->get('type'),
            'q'      => trim((string) $this->input->get('q')),
        );

        $rows = $this->tm()->lessons_of((int) $u['id'], $f);
        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 100, 20);

        $out = array();
        foreach (array_slice($rows, $offset, $per) as $l) {
            $out[] = array(
                'id'             => (int) $l['id'],
                'title'          => (string) $l['title'],
                'course_id'      => (int) $l['course_id'],
                'course_title'   => (string) $l['course_title'],
                'section_id'     => (int) $l['section_id'],
                'section_title'  => (string) $l['section_title'],
                'lesson_type'    => (string) $l['lesson_type'],
                'duration'       => (string) $l['duration'],
                'is_free'        => !empty($l['is_free']),
                'status'         => (string) $l['tq_status'],
                'quiz_questions' => (int) $l['quiz_questions'],
                'created_at'     => tq_api_date($l['date_added']),
            );
        }

        $this->read($out, '', array_merge(
            tq_api_meta_page($page, $per, count($rows)),
            array('filters' => $f)
        ), $h);
    }

    /**
     * GET /api/v1/teacher/students — طلاب كورساته وحدهم.
     *
     * المعلم لا يرى سجل الطلاب، يرى طلابه — والنطاق يفرض في طبقة
     * الاستعلام لا في إخفاء عنصر من الواجهة.
     */
    public function teacher_students()
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $d = $this->tm()->students((int) $u['id'], (int) $this->input->get('course'));

        $shape = function ($s) {
            return array(
                'student_id'   => (int) $s['student_id'],
                'name'         => (string) $s['name'],
                'avatar_url'   => tq_api_avatar($s['image']),
                'course_id'    => (int) $s['course_id'],
                'course_title' => (string) $s['course_title'],
                'progress'     => (int) $s['progress'],
                'days_away'    => (int) $s['days_away'],
                'last_seen'    => tq_api_date($s['last_seen']),
                'enrolled_at'  => tq_api_date($s['enrolled_at']),
                'attempts'     => (int) $s['attempts'],
                /* «ينتظر اعتمادك» خبر لا فراغ: محاولة لم تعتمد بعد لا
                   تحسب في المتوسط وتعد على حدة. */
                'held'         => (int) $s['held'],
                'avg_percent'  => $s['avg_percent'] === null ? null : (int) $s['avg_percent'],
                'at_risk'      => !empty($s['at_risk']),
            );
        };

        $all = array_map($shape, $d['students']);
        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 100, 20);

        $courses = array();
        foreach ($d['courses'] as $c) {
            $courses[] = array('id' => (int) $c['id'], 'title' => (string) $c['title']);
        }

        $this->read(array_slice($all, $offset, $per), '', array_merge(
            tq_api_meta_page($page, $per, count($all)),
            array(
                'at_risk' => array_map($shape, $d['at_risk']),
                'courses' => $courses,
                'filters' => array('course' => (int) $d['course']),
            )
        ), $h);
    }

    /**
     * GET /api/v1/teacher/analytics — الخريطة الحرارية وما تحتها.
     *
     * ومعيار القبول ليس رسما: **كل نمط انخفاض له اقتراح إجراء**. فيخرج
     * مع كل صف `severity` يفرع عليه التطبيق، لا لون يرسمه هو بقاعدة
     * ثانية تفترق عن قاعدتنا.
     */
    public function teacher_analytics()
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->load->model('taqdar_analytics_model', 'tq_an');
        $uid = (int) $u['id'];
        $cid = (int) $this->input->get('course');

        $heat = $this->tq_an->heatmap($uid, $cid, 120);

        /* الترتيب بالأشد لا بالترتيب الدراسي: من يفتح هذه الشاشة عنده وقت
           لدرسين لا لعشرين، فالأولان يجب أن يكونا الأسوأ. */
        $rank = array('high' => 0, 'mid' => 1, 'ok' => 2, 'none' => 3);
        usort($heat, function ($a, $b) use ($rank) {
            $ra = $rank[$a['severity']]; $rb = $rank[$b['severity']];
            if ($ra !== $rb) return $ra - $rb;
            return ((int) $a['finish_rate']) - ((int) $b['finish_rate']);
        });

        $courses = array();
        foreach ($this->tq_an->courses_of($uid) as $c) {
            $courses[] = array('id' => (int) $c['id'], 'title' => (string) $c['title']);
        }

        $this->read(array(
            'summary'         => $this->tq_an->summary($uid, $cid),
            'heatmap'         => $heat,
            'weak_objectives' => $this->tq_an->weak_objectives($uid, $cid, 8),
            'hard_questions'  => $this->tq_an->hard_questions($uid, $cid, 8),
        ), '', array('courses' => $courses, 'filters' => array('course' => $cid)), $h);
    }

    /**
     * GET /api/v1/teacher/marking — صفا التصحيح: الاختبارات والواجبات.
     *
     * وبطاقة تعد أحدهما تخفي الآخر — فيخرجان معا بعدديهما الكاملين لا
     * بعدد المعروض: السقف يخفي الزيادة، والعد هو الحقيقة.
     */
    public function teacher_marking()
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid = (int) $u['id'];
        $mk  = $this->mk();

        $kind = (string) $this->input->get('kind');
        if (!in_array($kind, array('quiz', 'homework'), true)) $kind = 'quiz';

        $rows = ($kind === 'homework') ? $mk->homework_queue($uid, 50) : $mk->queue($uid, 50);
        $done = ($kind === 'homework') ? $mk->homework_recent($uid, 8)  : $mk->approved_recent($uid, 8);

        $this->read(array(
            'queue'    => array_map(array($this, 'marking_row'), $rows),
            'approved' => array_map(array($this, 'marking_row'), $done),
        ), '', array(
            'counts' => array(
                'quizzes'  => (int) $mk->queue_count($uid),
                'homework' => (int) $mk->homework_queue_count($uid),
            ),
            'pass_percent' => (int) $mk->pass_percent(),
            'filters'      => array('kind' => $kind),
        ), $h);
    }

    /** صف الصف الواحد — الأسماء نفسها للنوعين، فيفرع التطبيق مرة. */
    private function marking_row($r)
    {
        $total = (int) ($r['q_count'] ?? $r['total'] ?? 0);
        $score = isset($r['teacher_score']) && $r['teacher_score'] !== null
               ? (int) $r['teacher_score']
               : (int) ($r['total_obtained_marks'] ?? $r['score'] ?? 0);

        return array(
            'id'           => (int) ($r['quiz_result_id'] ?? $r['id'] ?? 0),
            'student_id'   => (int) ($r['user_id'] ?? $r['student_id'] ?? 0),
            'student_name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
            'avatar_url'   => tq_api_avatar($r['image'] ?? ''),
            'lesson_id'    => (int) ($r['quiz_id'] ?? $r['lesson_id'] ?? 0),
            'lesson_title' => (string) ($r['lesson_title'] ?? ''),
            'course_title' => (string) ($r['course_title'] ?? ''),
            'score'        => $score,
            'total'        => $total,
            'percent'      => $total > 0 ? (int) round(100 * $score / $total) : null,
            'submitted_at' => tq_api_date($r['submitted_at'] ?? $r['date_added'] ?? null),
            'approved_at'  => tq_api_date($r['approved_at'] ?? null),
            'teacher_note' => (string) ($r['teacher_note'] ?? ''),
        );
    }

    /**
     * GET · POST /api/v1/teacher/marking/{kind}/{id}
     *
     * القراءة تفتح المحاولة بإجاباتها، والكتابة تعتمدها بدرجة وملاحظة.
     * والملكية في النموذج: محاولة خارج كورسات المعلم ترد `null` ولا فرق
     * عندها بين «غير موجودة» و«ليست لك».
     */
    public function teacher_marking_item($kind = 'quiz', $id = 0)
    {
        $m = $this->method(array('GET', 'POST'));
        $u = $this->require_teacher();

        $uid  = (int) $u['id'];
        $id   = (int) $id;
        $kind = ($kind === 'homework') ? 'homework' : 'quiz';
        $mk   = $this->mk();

        $row = ($kind === 'homework') ? $mk->homework_attempt($id, $uid)
                                      : $mk->attempt($id, $uid);
        if (!$row) $this->fail('لا محاولة بهذا الرقم في نطاقك.', 'not_found', 404);

        if ($m === 'POST') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

            $b = $this->body();
            $errors = tq_api_validate($b, array('score' => 'required|int'));
            if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

            $note = (string) ($b['note'] ?? '');

            /* والقرار في النموذج لا هنا: هو من يفحص المدى ويكتب الأثر
               ويخبر الطالب — ونسخة ثانية من قواعده تقبل درجة يرفضها
               الويب. */
            $r = ($kind === 'homework')
               ? $mk->approve_homework($uid, array('attempt_id' => $id,
                     'score' => (int) $b['score'], 'note' => $note))
               : $mk->approve($id, $uid, (int) $b['score'], $note);

            if (empty($r['ok'])) {
                $this->fail($this->model_msg($r, t('تعذر الاعتماد.')), 'approve_failed', 409);
            }

            $this->api->audit('api.marking.approve', $uid,
                              array('kind' => $kind, 'id' => $id, 'score' => (int) $b['score']));

            $this->respond(tq_api_ok(array('id' => $id, 'kind' => $kind),
                                     'اعتمدت الدرجة، وأبلغ صاحبها.'), 200);
        }

        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->read(array_merge($this->marking_row($row), array(
            'kind'    => $kind,
            'answers' => ($kind === 'homework') ? array() : $mk->answers_of($id, $uid),
        )), '', array(), $h);
    }

    /**
     * GET /api/v1/teacher/wallet — المحفظة والأرباح.
     *
     * وكل مبلغ بالهللات عبر `tq_api_money()`: الدفتر يخزن هللات، وعائم
     * في المنتصف يجعل ريالا يضيع في القسمة.
     */
    public function teacher_wallet()
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $w = $this->wal()->screen((int) $u['id']);

        /* الكشف **مجمع بالمستند** لا صفا لكل قيد: البيع والعمولة والمحتجط
           ثلاثة قيود لبيعة واحدة، وعرضها مفرقة يجعل المعلم يعد بيعته
           ثلاث مرات. و`share` مجموعها وهو حصته بحكم البناء. */
        $statement = array();
        foreach ((array) $w['statement'] as $e) {
            $statement[] = array(
                'origin'      => (string) ($e['origin'] ?? ''),
                'subject'     => (string) ($e['subject'] ?? ''),
                'gross'       => tq_api_money((int) ($e['gross'] ?? 0)),
                'commission'  => tq_api_money((int) ($e['commission'] ?? 0)),
                'retained'    => tq_api_money((int) ($e['retained'] ?? 0)),
                'share'       => tq_api_money((int) ($e['share'] ?? 0)),
                'state'       => (string) ($e['state'] ?? ''),
                'days_left'   => (int) ($e['days_left'] ?? 0),
                'occurred_at' => tq_api_date($e['occurred_at'] ?? null),
                'released_at' => tq_api_date($e['released_at'] ?? null),
            );
        }

        $payouts = array();
        foreach ((array) $w['payouts'] as $p) {
            $payouts[] = array(
                'id'          => (int) ($p['id'] ?? 0),
                'amount'      => tq_api_money((int) ($p['amount_halalas'] ?? 0)),
                'status'      => (string) ($p['status'] ?? ''),
                'channel'     => (string) ($p['channel'] ?? ''),
                /* الوجهة **مقنعة** هنا كما تقنع في شاشة المعلم: أربع خانات
                   تكفيه ليعرف أي حساب قصد، وسجل يحمل الرقم كاملا في كل
                   رد يجعل كل من قرأ سجلات التطبيق يقرأ حسابات الناس. */
                'destination' => (string) ($p['destination_masked'] ?? ''),
                'requested_at'=> tq_api_date($p['date_added'] ?? null),
                'decided_at'  => tq_api_date($p['decided_at'] ?? null),
                'reference'   => (string) ($p['reference'] ?? ''),
            );
        }

        $this->read(array(
            'balances' => array(
                'available'   => tq_api_money((int) $w['available']),
                'pending'     => tq_api_money((int) $w['pending']),
                'locked'      => tq_api_money((int) $w['locked']),
                'transferred' => tq_api_money((int) $w['transferred']),
            ),
            /* «متى ينضج رصيدي؟» و«ما أقل ما أسحبه؟» سؤالان يسألهما كل
               معلم قبل أن يضغط زر السحب، فيخرجان معه لا في وثيقة. */
            'hold_days'  => (int) $w['refund_days'],
            'min_payout' => tq_api_money((int) $w['min_payout']),
            'channels'   => $w['channels'],
            'statement'  => $statement,
            'payouts'    => $payouts,
        ), '', array(), $h);
    }

    /**
     * POST /api/v1/teacher/wallet/withdraw — طلب سحب.
     *
     * وفحص الوجهة في `Taqdar_wallet_model::$CHANNELS` — الجدول نفسه الذي
     * يفحص به الويب: قناة تضاف هناك وحدها فتقبل في السطحين معا.
     */
    public function teacher_wallet_withdraw()
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array(
            'amount'      => 'required',
            'channel'     => 'required',
            'destination' => 'required',
        ));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $r = $this->wal()->request_withdrawal((int) $u['id'], array(
            'amount'      => $b['amount'],
            'channel'     => (string) $b['channel'],
            'destination' => (string) $b['destination'],
        ));

        if (empty($r['ok'])) {
            $this->fail($this->model_msg($r, t('تعذر طلب السحب.')), 'withdraw_failed', 409);
        }

        $this->api->audit('api.wallet.withdraw', (int) $u['id'],
                          array('payout_id' => (int) ($r['payout_id'] ?? 0)));

        $this->respond(tq_api_ok(array('payout_id' => (int) ($r['payout_id'] ?? 0)),
                                 t('سجل طلب السحب، وتراجعه الإدارة.')), 201);
    }

    /**
     * GET /api/v1/teacher/sessions — الحصص الخاصة عند المعلم.
     *
     * والحالات الثمان تخرج كما هي مع شارتها: `status` مفتاح يفرع عليه
     * التطبيق، و`status_label` نص يعرض.
     */
    public function teacher_sessions()
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->load->model('taqdar_sessions_model', 'tq_sess');
        $uid = (int) $u['id'];

        $states = array('requested', 'awaiting_payment', 'confirmed', 'live', 'completed');
        $filter = (string) $this->input->get('status');
        if ($filter !== '' && in_array($filter, $states, true)) $states = array($filter);

        $rows = $this->tq_sess->requests_for_teacher($uid, $states, 50);

        /* والصفوف تخرج **مشكلة من النموذج أصلا**: الاسم والصف والمادة
           ونص الموعد وحال الانضمام كلها فيه، فلا يعاد بناؤها هنا بقاعدة
           ثانية تفترق عن شاشة الويب. */
        $out = array();
        foreach ($rows as $r) {
            list($tone, $label) = $this->tq_sess->status_badge((string) $r['status']);

            $out[] = array(
                'id'            => (int) $r['id'],
                'status'        => (string) $r['status'],
                'status_label'  => $label,
                'status_tone'   => $tone,
                'student_id'    => (int) $r['student_id'],
                'student_name'  => (string) $r['student_name'],
                'avatar_url'    => tq_api_avatar($r['image']),
                'starts_at'     => tq_api_date($r['starts_at']),
                'when_text'     => (string) $r['when_text'],
                'minutes'       => (int) $r['minutes'],
                'grade'         => (string) $r['grade_name'],
                'subject'       => (string) $r['subject_name'],
                'price'         => tq_api_money((int) $r['price']),
                'my_share'      => tq_api_money((int) $r['share']),
                /* الرابط يخرج حين يفتح وحده: رابط يسلم قبل موعده بيومين
                   يجعل من يفتحه يجد غرفة فارغة ويظن أن الطرف الآخر تخلف. */
                'meet_url'      => !empty($r['can_join']) ? (string) $r['meet_url'] : null,
                'can_join'      => (bool) $r['can_join'],
                'can_complete'  => (bool) $r['can_complete'],
                'note'          => (string) $r['note'],
                'paid_at'       => tq_api_date($r['paid_at']),
                'pay_deadline'  => tq_api_date($r['pay_deadline']),
            );
        }

        $this->read($out, '', array(
            'summary' => $this->tq_sess->teacher_summary($uid),
            'filters' => array('status' => (string) $this->input->get('status')),
        ), $h);
    }

    /**
     * POST /api/v1/teacher/sessions/{id}/decide — تأكيد أو اعتذار.
     *
     * والحكم كله في `Taqdar_sessions_model::decide()`: هي التي تفحص
     * الملكية والحالة، وتصدر الفاتورة إن كانت الحصة مسعرة (فيصير الطلب
     * `awaiting_payment` لا `confirmed`)، وتخبر الطالب. ونسخة ثانية هنا
     * تجعل الحصة تؤكد في التطبيق بلا فاتورة.
     */
    public function teacher_session_decide($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $decision = (string) ($b['decision'] ?? '');
        if (!in_array($decision, array('confirm', 'decline'), true)) {
            $this->fail('حدد القرار: تأكيد أو اعتذار.', 'validation_failed', 422,
                        array('decision' => array(t('القيم المقبولة: confirm · decline'))));
        }

        /* ورابط اللقاء شرط في التأكيد لا حقل اختياري — والنموذج هو من
           يفرضه: «مؤكدة» بلا رابط تقول للطالب إن الحصة قائمة ولا تقول
           أين، فيقف في موعده أمام شاشة بلا باب. */
        $r = $this->tq_sessions()->decide((int) $id, (int) $u['id'], $decision,
                                          (string) ($b['meet_url'] ?? ''),
                                          (string) ($b['reason'] ?? ''));

        if (empty($r['ok'])) {
            $this->fail($this->model_msg($r, t('تعذر تنفيذ القرار.')), 'decision_failed', 409);
        }

        $this->api->audit('api.session.decide', (int) $u['id'],
                          array('session_id' => (int) $id, 'decision' => $decision));

        $this->respond(tq_api_ok(array('id' => (int) $id, 'status' => (string) ($r['state'] ?? '')),
                                 $this->model_msg($r, t('سجل قرارك.'))), 200);
    }

    /**
     * POST /api/v1/teacher/sessions/{id}/complete — إعلان انتهاء الحصة.
     *
     * **وهنا يقيد نصيب المعلم** لا عند الدفع: الحصة وقت لم يمض بعد، ولو
     * قيدت عند الدفع لصار معلم غاب عن حصته يملك مالها في دفتره.
     */
    public function teacher_session_complete($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $r = $this->tq_sessions()->complete((int) $id, (int) $u['id'], 'teacher');

        if (empty($r['ok'])) {
            $this->fail($this->model_msg($r, t('تعذر إنهاء الحصة.')), 'complete_failed', 409);
        }

        $this->api->audit('api.session.complete', (int) $u['id'], array('session_id' => (int) $id));

        $this->respond(tq_api_ok(array(
            'id'       => (int) $id,
            'status'   => 'completed',
            /* **وهنا يقيد النصيب**، فيقال إن قيد: «أنهيتها» و«ووصل مالها»
               خبران، والثاني هو ما يسأل عنه المعلم. */
            'credited' => !empty($r['credited']),
        ), $this->model_msg($r, t('أنهيت الحصة، وقيد نصيبك.'))), 200);
    }

    private function tq_sessions()
    {
        $this->load->model('taqdar_sessions_model', 'tq_sess');
        return $this->tq_sess;
    }

    /**
     * GET /api/v1/teacher/books — كتب المعلم.
     *
     * و`offer()` هي المصدر الواحد لسؤال «أيباع؟ بكم؟» — الصفحة والكتالوج
     * وشاشة التأكيد والمكتبة يقرأون منها، فما يقرؤه المعلم هنا هو ما
     * يقيده محرك الشراء بالهللة.
     */
    public function teacher_books()
    {
        if ($this->method(array('GET', 'POST')) === 'POST') $this->teacher_book_create();

        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->load->model('taqdar_book_model', 'tq_book');
        $this->tq_book->ensure_schema();

        $out = array();
        foreach ($this->tq_book->books_of((int) $u['id'], 200) as $bk) {
            $offer = $this->tq_book->offer($bk);
            $out[] = array(
                'id'        => (int) $bk['id'],
                'title'     => (string) $bk['title'],
                'slug'      => (string) ($bk['slug'] ?? ''),
                'status'    => (string) ($bk['status'] ?? ''),
                'grade_id'  => isset($bk['grade_id']) ? (int) $bk['grade_id'] : null,
                'pages'     => (int) ($bk['pages'] ?? 0),
                'sellable'  => !empty($offer['sellable']),
                /* `reason` مفتاح و`why` نص عربي يعرض — والمعلم يحتاج
                   الثاني ليعرف لماذا لا يباع كتابه. */
                'reason'    => (string) ($offer['reason'] ?? ''),
                'why'       => (string) ($offer['why'] ?? ''),
                'price'     => tq_api_money((int) ($offer['price_halalas'] ?? 0)),
                'my_share'  => tq_api_money((int) ($offer['teacher_share_halalas'] ?? 0)),
            );
        }

        $this->read($out, '', array('total' => count($out),
                                    'sales_enabled' => (bool) $this->tq_book->enabled()), $h);
    }

    /* =================================================================
       بوابة ولي الأمر
       ================================================================= */

    /**
     * GET /api/v1/parent/children — أبنائي.
     *
     * والروابط كلها لا النشطة وحدها: طلب معلق ينتظر موافقة ابنه خبر يجب
     * أن يقرأه — وقائمة تعرض النشط وحده تجعله يظن أن طلبه ضاع فيعيده.
     */
    public function parent_children()
    {
        $this->method('GET');
        $u = $this->require_parent();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $out = array();
        foreach ($this->pm()->links((int) $u['id']) as $l) {
            $out[] = array(
                'link_id'    => (int) $l['id'],
                'student_id' => (int) $l['student_id'],
                'name'       => (string) $l['name'],
                'email'      => (string) $l['email'],
                'avatar_url' => tq_api_avatar($l['image']),
                'status'     => (string) $l['status'],
                'consent_at' => tq_api_date($l['consent_at']),
                'plan_days'  => (int) $this->pm()->plan_days((int) $u['id'],
                                        (int) $l['student_id'])['days'],
            );
        }

        $this->read($out, '', array('total' => count($out)), $h);
    }

    /**
     * GET /api/v1/parent/children/{id} — تفاصيل الابن.
     *
     * **وحاجز الرؤية مطبق في طبقة الاستعلام**: لا محادثات المساعد الذكي،
     * ولا منشورات، ولا كل إجابة خاطئة على حدة. «الرقابة الكاملة تنتج
     * طالبا يخفي، لا طالبا يتعلم» — والحاجز في النموذج لا في إخفاء حقل
     * من هذا الرد.
     */
    public function parent_child($id = 0)
    {
        $this->method('GET');
        $u = $this->require_parent();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $d = $this->pm()->child_detail((int) $u['id'], (int) $id);
        if (!$d) {
            $this->fail('لا يفتح حساب ابن قبل ربطه بحسابك برابط نشط.', 'not_found', 404);
        }

        $subjects = array();
        foreach ($d['subjects'] as $s) {
            $subjects[] = array(
                'course_id'  => (int) $s['id'],
                'title'      => (string) $s['title'],
                'progress'   => (int) $s['progress'],
                'done'       => (int) $s['done_n'],
                'lessons'    => (int) $s['lessons_n'],
                'last_seen'  => tq_api_date($s['last_seen']),
            );
        }

        $sessions = array();
        foreach ($d['sessions'] as $s) {
            $sessions[] = array(
                'id'        => (int) $s['id'],
                'status'    => (string) $s['status'],
                'starts_at' => tq_api_date($s['starts_at']),
                'minutes'   => (int) $s['duration_min'],
                'teacher'   => (string) $s['teacher'],
                'subject'   => (string) ($s['subject_name'] ?? ''),
                'grade'     => (string) ($s['grade_name'] ?? ''),
            );
        }

        $notes = array();
        foreach ($d['notes'] as $n) {
            $notes[] = array(
                'id'           => (int) $n['id'],
                'kind'         => (string) $n['kind'],
                'note'         => (string) $n['teacher_note'],
                'lesson_title' => (string) $n['lesson_title'],
                'course_title' => (string) $n['course_title'],
                'teacher'      => (string) $n['teacher'],
                'approved_at'  => tq_api_date($n['approved_at']),
            );
        }

        $this->read(array(
            'student_id' => (int) $d['child']['id'],
            'name'       => trim($d['child']['first_name'] . ' ' . $d['child']['last_name']),
            'avatar_url' => tq_api_avatar($d['child']['image']),
            /* المقياس الثلاثي المبسط: الالتزام · الفهم · الاتجاه.
               والاتجاه مقارنة بأسبوعه هو — لا ترتيب بين الأبناء. */
            'commitment' => array(
                'percent'    => (int) $d['commitment'],
                'days'       => (int) $d['days_this'],
                'plan_days'  => (int) $d['plan_days'],
                'is_default' => (bool) $d['plan_is_default'],
                'week_flags' => array_map('boolval', $d['day_flags']),
            ),
            'understanding' => array(
                'open'     => (int) $d['skill']['open'],
                'mastered' => (int) $d['skill']['mastered'],
                'percent'  => (int) $d['skill']['percent'],
            ),
            'trend' => array(
                'days_this' => (int) $d['days_this'],
                'days_prev' => (int) $d['days_prev'],
                'direction' => $d['days_this'] > $d['days_prev'] ? 'up'
                             : ($d['days_this'] < $d['days_prev'] ? 'down' : 'flat'),
            ),
            'lessons_completed' => (int) $d['completed'],
            'subjects'          => $subjects,
            'sessions'          => $sessions,
            'teacher_notes'     => $notes,
            'payments'          => array_map(array($this, 'parent_payment_out'), $d['payments']),
        ), '', array(), $h);
    }

    /**
     * GET /api/v1/parent/weekly — التقرير الأسبوعي.
     *
     * والمقارنة على مدى واحد: ما مضى من هذا الأسبوع مقابل **الأيام نفسها**
     * من الأسبوع الماضي — وإلا قرأ كل ولي أمر صباح الأحد أن نشاط ابنه
     * «نزل»، لأن أسبوعا لم يبدأ بعد يقارن بأسبوع تم.
     */
    public function parent_weekly()
    {
        $this->method('GET');
        $u = $this->require_parent();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $d = $this->pm()->weekly((int) $u['id'], (int) $this->input->get('child'));

        $kids = array();
        foreach ($d['children'] as $c) {
            $kids[] = array(
                'student_id'      => (int) $c['student_id'],
                'name'            => (string) $c['name'],
                'avatar_url'      => tq_api_avatar($c['image']),
                'lessons_done'    => (int) $c['lessons_done'],
                'lessons_total'   => (int) $c['lessons_total'],
                'quizzes'         => (int) $c['quizzes'],
                'days_this'       => (int) $c['days_this'],
                'days_prev'       => (int) $c['days_prev'],
                'trend'           => (string) $c['trend'],
                'plan_days'       => (int) $c['plan_days'],
                'plan_is_default' => (bool) $c['plan_is_default'],
                'days_needed'     => (int) $c['needed'],
                'stalled'         => $c['stalled'],
            );
        }

        $this->read($kids, '', array(
            'week' => array(
                'start'     => tq_api_date($d['week']['start']),
                'elapsed'   => (int) $d['week']['elapsed'],
                'days_left' => (int) $d['week']['days_left'],
            ),
        ), $h);
    }

    /**
     * GET /api/v1/parent/reports — كل مادة في سطر واحد لكل ابن.
     *
     * **والدرجة المعروضة هي التي يراها ابنك نفسه** لا الدرجة الخام: ما
     * لم يعتمده معلمه لا يعرض لأحد، ويعد على حدة. وأسرع طريق إلى شجار
     * بين مراهق وأهله أن تعطيهما المنصة رقمين.
     */
    public function parent_reports()
    {
        $this->method('GET');
        $u = $this->require_parent();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $out = array();
        foreach ($this->pm()->reports((int) $u['id'], (int) $this->input->get('child')) as $c) {
            $subjects = array();
            foreach ($c['subjects'] as $s) {
                $subjects[] = array(
                    'course_id'   => (int) $s['id'],
                    'title'       => (string) $s['title'],
                    'progress'    => (int) $s['progress'],
                    'lessons'     => (int) $s['lessons'],
                    'attempts'    => (int) $s['attempts'],
                    'held'        => (int) $s['held'],
                    'avg_percent' => $s['avg_percent'] === null ? null : (int) $s['avg_percent'],
                    'last_seen'   => tq_api_date($s['last_seen']),
                );
            }
            $out[] = array(
                'student_id' => (int) $c['student_id'],
                'name'       => (string) $c['name'],
                'avatar_url' => tq_api_avatar($c['image']),
                'subjects'   => $subjects,
            );
        }

        $this->read($out, '', array('total' => count($out)), $h);
    }

    /**
     * GET /api/v1/parent/payments — ما دفع عن أبنائه.
     *
     * ومن مصدري المال معا (فواتير تقدر ومدفوعات Academy) عبر الدفتر
     * الموحد في النموذج — لا استعلامين يفترق شكلاهما.
     */
    public function parent_payments()
    {
        $this->method('GET');
        $u = $this->require_parent();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $uid   = (int) $u['id'];
        $child = (int) $this->input->get('child');

        $rows = array();
        foreach ($this->pm()->children($uid) as $c) {
            $cid = (int) $c['student_id'];
            if ($child && $cid !== $child) continue;

            foreach ($this->pm()->payments_of($cid, 50) as $p) {
                $p['student_id']   = $cid;
                $p['student_name'] = trim($c['first_name'] . ' ' . $c['last_name']);
                $rows[] = $p;
            }
        }

        usort($rows, function ($a, $b) {
            return (int) ($b['ts'] ?? 0) <=> (int) ($a['ts'] ?? 0);
        });

        list($page, $per, $offset) = tq_api_page(
            $this->input->get('page'), $this->input->get('per_page'), 100, 20);

        $this->read(array_map(array($this, 'parent_payment_out'),
                              array_slice($rows, $offset, $per)),
                    '', array_merge(
                        tq_api_meta_page($page, $per, count($rows)),
                        array('totals'  => $this->pm()->payment_totals($rows),
                              'bank'    => $this->bank_out(),
                              'filters' => array('child' => $child))
                    ), $h);
    }

    /** شكل الدفعة — والمبلغ بالهللات كما يخزن، فلا يحسب العميل بعائم. */
    private function parent_payment_out($p)
    {
        /* **والمبلغ يعود إلى الهللات هنا**: الدفتر الموحد يخرجه بالريال
           (`total / 100`) لأن القالب يطبعه، و`tq_api_money()` عقدها
           الهللة. وتمرير الريال كما هو يجعل فاتورة ٣٩٩ تقرأ في التطبيق
           «٣٫٩٩ ر.س» — رقم معقول لا يشك فيه أحد. */
        return array(
            'source'       => (string) ($p['source'] ?? ''),
            'title'        => (string) ($p['title'] ?? ''),
            'reference'    => (string) ($p['ref'] ?? ''),
            'amount'       => tq_api_money((int) round(((float) ($p['amount'] ?? 0)) * 100)),
            'status'       => (string) ($p['status'] ?? ''),
            'status_label' => (string) ($p['label'] ?? ''),
            'method'       => (string) ($p['method'] ?? ''),
            'student_id'   => (int) ($p['student_id'] ?? 0),
            'student_name' => (string) ($p['student_name'] ?? ''),
            'at'           => tq_api_date($p['ts'] ?? null),
        );
    }

    /**
     * POST /api/v1/parent/children — طلب ربط ابن.
     *
     * والربط لا يتم بطلب ولي الأمر وحده: يكتب صفا `pending` ويصل ابنه
     * طلب موافقة — «خطأ واحد هنا يفتح بيانات طفل لغير أهله».
     */
    public function parent_child_link()
    {
        $this->method('POST');
        $u = $this->require_parent();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $errors = tq_api_validate($b, array('identifier' => 'required'));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $r = $this->pm()->request_link((int) $u['id'], (string) $b['identifier']);

        if (empty($r['ok'])) {
            $this->fail($this->model_msg($r, t('تعذر إرسال الطلب.')), 'link_failed', 409);
        }

        $this->api->audit('api.parent.link_request', (int) $u['id'],
                          array('link_id' => (int) ($r['link_id'] ?? 0)));

        $this->respond(tq_api_ok(array('link_id' => (int) ($r['link_id'] ?? 0), 'status' => 'pending'),
                                 $this->model_msg($r, t('أرسل الطلب، وينتظر موافقة ابنك.'))), 201);
    }

    /**
     * DELETE /api/v1/parent/children/{id} — إلغاء ربط أو سحب طلب.
     *
     * والإلغاء يغلق بيانات الابن **في الحال**، ويبقى في السجل تاريخ
     * موافقته وتاريخ الإلغاء — فما كان لا يمحى.
     */
    public function parent_child_unlink($student_id = 0)
    {
        $this->method('DELETE');
        $u = $this->require_parent();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $r = $this->pm()->revoke_link((int) $u['id'], (int) $student_id);

        if (empty($r['ok'])) {
            $this->fail($this->model_msg($r, t('تعذر إلغاء الربط.')), 'unlink_failed', 409);
        }

        $this->api->audit('api.parent.unlink', (int) $u['id'],
                          array('student_id' => (int) $student_id));

        $this->respond(tq_api_ok(null, $this->model_msg($r, t('ألغي الربط.'))), 200);
    }

    /**
     * POST /api/v1/parent/pay — الدفع عن الابن.
     *
     * **والاشتراك والفاتورة يكتبان باسم الابن لا باسم الأب**: هو صاحب
     * المحتوى، وعليه يقاس التقدم، وله تجسد `sync_enrolments()` صفوف
     * `enrol`. وولي الأمر يدفع ولا يملك.
     *
     * والمحرك واحد للأنواع الثلاثة (`subscribe` · `subscribe_course` ·
     * `subscribe_book`) — ثلاثة أبواب على مرساة الفاتورة نفسها، فما
     * بعدها يتبعها بلا تعديل.
     */
    public function parent_pay()
    {
        /* والقراءة على المسار نفسه: باب يكتب ولا يقرأ يجعل التطبيق يعرف
           كيف يدفع ولا يعرف ماذا يعرض. */
        if ($this->method(array('GET', 'POST')) === 'GET') $this->parent_pay_options();

        $u = $this->require_parent();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $pid = (int) $u['id'];
        $b   = $this->body();

        $child = (int) ($b['child_id'] ?? 0);
        if (!$this->pm()->owns($pid, $child)) {
            $this->fail('هذا الطالب غير مرتبط بحسابك برابط نشط.', 'not_your_child', 403);
        }

        $kind = (string) ($b['kind'] ?? 'plan');
        if (!in_array($kind, array('plan', 'course', 'book'), true)) {
            $this->fail('نوع الشراء غير معروف.', 'validation_failed', 422,
                        array('kind' => array(t('القيم المقبولة: plan · course · book'))));
        }

        $this->load->model('taqdar_billing_model', 'tq_bill');
        $this->load->model('taqdar_tap_model', 'tq_tap');

        $by_card = ((string) ($b['pay_method'] ?? 'manual') === 'tap') && $this->tq_tap->ready();
        $method  = $by_card ? 'tap' : 'manual';

        if ($kind === 'course') {
            $r = $this->tq_bill->subscribe_course($child, (int) ($b['course_id'] ?? 0), $method);
        } elseif ($kind === 'book') {
            $r = $this->tq_bill->subscribe_book($child, (int) ($b['book_id'] ?? 0), $method);
        } else {
            /* TQ-CYCLE-BUY — والدورة معامل: باب بلا دورة يعني أن ولي
               الأمر لا يشتري الشهري أبدا مهما عرضته عليه صفحة الباقات. */
            $r = $this->tq_bill->subscribe($child, (int) ($b['plan_id'] ?? 0), $method,
                                           (string) ($b['cycle'] ?? ''));
        }

        if (empty($r['ok'])) {
            $code = isset($r['code']) ? strtolower((string) $r['code']) : 'purchase_failed';
            $this->fail($this->model_msg($r, t('تعذر إنشاء الشراء.')), $code, 409);
        }

        $this->api->audit('api.parent.pay', $pid, array(
            'child_id'        => $child,
            'kind'            => $kind,
            'subscription_id' => (int) ($r['subscription_id'] ?? 0),
            'invoice_id'      => (int) ($r['invoice_id'] ?? 0),
        ));

        if (!empty($r['free'])) {
            $this->respond(tq_api_ok(array(
                'subscription_id' => (int) $r['subscription_id'],
                'free'            => true, 'invoice' => null, 'payment_url' => null,
            ), t('فعلت الباقة المجانية باسم ابنك.')), 201);
        }

        $inv = $this->db->where('id', (int) $r['invoice_id'])->get('invoices')->row_array();

        if ($by_card) {
            $pay = $this->tq_tap->start((int) $r['invoice_id'], $child);
            if (!empty($pay['ok'])) {
                $this->respond(tq_api_ok(array(
                    'subscription_id' => (int) $r['subscription_id'],
                    'free'            => false,
                    'invoice'         => $inv ? $this->invoice_out($inv) : null,
                    'payment_url'     => $pay['url'],
                ), t('جهزت صفحة الدفع.')), 201);
            }
        }

        /* **الفاتورة صدرت ولم تدفع**، فيقال ما وقع ويدل على البديل
           القائم — ورد خطأ عار يجعل صاحبه يعيد الشراء فيصير لابنه
           اشتراكان معلقان. */
        $this->respond(tq_api_ok(array(
            'subscription_id' => (int) $r['subscription_id'],
            'free'            => false,
            'invoice'         => $inv ? $this->invoice_out($inv) : null,
            'payment_url'     => null,
            'bank'            => $this->bank_out($inv ? (string) $inv['invoice_no'] : null),
        ), t('صدرت الفاتورة باسم ابنك. حول قيمتها ثم ترسل الإدارة التفعيل.')), 201);
    }

    /* =================================================================
       الحساب — للمعلم ولولي الأمر بالمسار نفسه
       ================================================================= */

    /**
     * GET · PATCH /api/v1/{gate}/settings
     *
     * والحفظ يمر بـ`Taqdar_settings_model` نفسه الذي تمر به شاشات الويب:
     * نسخة ثانية من قواعد التحقق هنا تفترق عند أول تشديد، فيقبل التطبيق
     * ما يرفضه الموقع.
     */
    public function portal_settings()
    {
        $m = $this->method(array('GET', 'PATCH', 'POST'));
        $u = $this->require_portal(array('teacher', 'parent'));

        $uid = (int) $u['id'];
        $this->load->model('taqdar_settings_model', 'tq_set');

        if ($m !== 'GET') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

            $section = (string) $this->in('section', 'profile');
            $allowed = array('profile', 'password', 'notifications', 'preferences');
            if (!in_array($section, $allowed, true)) {
                $this->fail('قسم غير معروف.', 'validation_failed', 422,
                            array('section' => array(implode(' · ', $allowed))));
            }

            /* `as_post()` تحقن جسم JSON في `$_POST` لأن النموذج يقرأ منه:
               هو المكتوب لشاشة الويب، ونداؤه بحمولة JSON بلا ذلك يقرأ
               حقولا فارغة فيحفظ صفا ممحوا. */
            $this->as_post($this->body());

            switch ($section) {
                case 'password':      $r = $this->tq_set->save_password($uid); break;
                case 'notifications': $r = $this->tq_set->save_alerts($uid);   break;
                case 'preferences':   $r = $this->tq_set->save_prefs($uid);    break;
                default:              $r = $this->tq_set->save_profile($uid);
            }

            $this->settings_result($r, $uid, $section);
        }

        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $this->read(array(
            'account'  => tq_api_user($this->db->where('id', $uid)->get('users')->row_array()),
            'prefs'    => $this->tq_set->prefs($uid),
            'notify'   => $this->tq_set->notify_matrix($uid),
            'channels' => $this->tq_set->notify_channels(),
            'themes'   => $this->tq_set->themes(),
            'languages'=> $this->tq_set->languages(),
        ), '', array('role' => $this->role), $h);
    }

    /* =====================================================================
       بوابة المعلم — التأليف
       ---------------------------------------------------------------------
       ما سبق يقرأ، وهذا يكتب. والبوابة على الويب **تؤلف**: كورسا وأقساما
       ودروسا واختبارا وكتابا وأوقات حصص. وواجهة تقرأ ولا تكتب تجعل
       التطبيق شاشة عرض لا بوابة عمل: يرى المعلم درسه ولا يصحح عنوانه،
       ويقرأ «لا أسئلة بعد» ولا يؤلف سؤالا، ويرى «لا مواعيد» ولا يفتح
       ساعة واحدة.

       **ولا قاعدة عمل واحدة تكتب هنا كذلك.** الملكية والتحقق والنشر
       والمراجعة كلها في `Taqdar_curriculum_model` و`Taqdar_quiz_model` و
       `Taqdar_book_model` و`Taqdar_sessions_model` — الطبقة نفسها التي
       تناديها شاشات الويب. فما يرفضه الموقع يرفضه التطبيق بالحرف، وما
       ينزل إلى «قيد المراجعة» هناك ينزل هنا: `may_publish()` واحدة.
       ===================================================================== */

    /** فاعل المنهج — `actor_as()` تبني الشكل الذي تفحص به كل دوال الطبقة. */
    private function tactor($uid)
    {
        return $this->cur()->actor_as('teacher', (int) $uid);
    }

    private function qz()
    {
        $this->load->model('taqdar_quiz_model', 'tq_qz');
        return $this->tq_qz;
    }

    /**
     * جسم الكتابة كما تنتظره النماذج.
     *
     * والنماذج مكتوبة لشاشة ويب ترسل `multipart`، فتقرأ `$_FILES` من
     * المتغير العام. وحمولة JSON تصل بلا ملف، وهو الشائع في التطبيق:
     * الحقول نصية والصورة ترفع `multipart` حين ترفع.
     */
    private function wbody()
    {
        return array($this->body(), isset($_FILES) && is_array($_FILES) ? $_FILES : array());
    }

    /**
     * جسم الدرس — و`kind` مرادف لـ`tq_kind`.
     *
     * القراءة ترد `kind` (من `kind_of()`)، والكتابة تنتظر `tq_kind`
     * لأن ذلك اسم الحقل في نموذج الويب. واسمان لشيء واحد بين قراءة
     * وكتابة يجعل من قرأ ثم كتب يرسل `kind` **فيسقط على الافتراضي
     * `youtube`** — فيرد «رابط الفيديو مطلوب» على درس نصي، ولا شيء
     * يقول إن الاسم هو الخطأ. فالمرادفة هنا، والنموذج لا يمس لأنه
     * مشترك مع الويب.
     */
    private function lesson_post()
    {
        list($post, $files) = $this->wbody();
        if (!isset($post['tq_kind']) && isset($post['kind'])) {
            $post['tq_kind'] = $post['kind'];
        }
        return array($post, $files);
    }

    /** رد موحد لنتيجة نموذج كتابة — والرسالة من `model_msg()` لا من مفتاح واحد. */
    private function wrote($r, $fallback, $code, $data = null, $status = 200)
    {
        if (empty($r['ok'])) {
            $this->fail($this->model_msg($r, $fallback), $code, 409);
        }

        if ($data === null) $data = array('id' => (int) (isset($r['id']) ? $r['id'] : 0));

        /* و«حفظ» ليست الخبر كله: `save_lesson()` و`save_course()` تنزلان
           ما يعلنه المعلم منشورا إلى «قيد المراجعة» بحكم `may_publish()`،
           والرسالة هي التي تقول ذلك. ورد يقول «حفظ» وحده يجعل المعلم
           يظن أنه نشر، فيفتح رابط درسه ولا يجده. */
        if (array_key_exists('staged', $r)) $data['staged'] = !empty($r['staged']);
        if (isset($r['status']))            $data['status'] = (string) $r['status'];

        $this->respond(tq_api_ok($data, $this->model_msg($r, '')), $status);
    }

    /**
     * GET /api/v1/teacher/lesson-types — وصف أنواع الدروس العشرة.
     *
     * ومنه يبني التطبيق نموذج الدرس كما تبنيه شاشتا الويب من الوصف
     * نفسه: حقول كل نوع، وأيها مطلوب، وأيها تقرأ مدته من مصدره
     * (`probe`). ونسخة ثانية من القائمة في Dart تعني نوعا يضاف هنا ولا
     * يظهر هناك — فيرفع المعلم درسا من الموقع ولا يفتحه من تطبيقه.
     */
    public function teacher_lesson_types()
    {
        $this->method('GET');
        $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        /* والتحميل قبل النداء الساكن: `lesson_types()` ساكنة، والصنف لا
           يحمل نفسه — فنداؤها بلا `load->model()` يرمي «Class not found»
           ويبتلعه حارس الأخطاء فيقرأ العميل 500 بلا سبب. */
        $this->cur();

        $this->read(Taqdar_curriculum_model::lesson_types(), '', array(
            'course_statuses' => Taqdar_curriculum_model::course_statuses(),
        ), $h);
    }

    /**
     * GET /api/v1/teacher/course-form — وصف حقول الكورس، وقيم كورس قائم.
     *
     * والوصف من `course_fields($actor)` — وهي التي تحذف حقول `admin`
     * (السعر و«مميز» وتحسين البحث وتاريخ النشر) عن غير المسؤول. فما لا
     * يملكه المعلم لا يصل إليه أصلا، ولا يكتفى بإخفاء حقل في شاشة.
     */
    public function teacher_course_form()
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $id    = (int) $this->input->get('id');
        $actor = $this->tactor((int) $u['id']);
        $spec  = $this->cur()->course_fields($actor);

        $values = array();
        if ($id > 0) {
            if (!$this->cur()->may_edit_course($actor, $id)) {
                $this->fail('لا كورس بهذا الرقم في نطاقك.', 'not_found', 404);
            }
            $row = $this->cur()->course($id);
            if (!is_array($row)) $row = array();
            foreach ($spec as $name => $f) {
                $col = isset($f['col']) ? $f['col'] : null;
                $key = ($col !== null && $col !== '') ? $col : $name;
                $values[$name] = array_key_exists($key, $row) ? $row[$key]
                               : (array_key_exists($name, $row) ? $row[$name] : null);
            }
        }

        $this->read(array(
            'course_id' => $id,
            'fields'    => $spec,
            'values'    => $values,
            'statuses'  => Taqdar_curriculum_model::course_statuses(),
            /* `may_publish` خبر يعرض قبل الحفظ لا بعده: من يعرف أن نشره
               يمر بمراجعة لا يفاجأ بها في الرد. */
            'may_publish' => (bool) $this->cur()->may_publish($actor),
        ), '', array(), $h);
    }

    /**
     * POST /api/v1/teacher/courses — كورس جديد.
     *
     * TQ-COURSE-SPLIT — والصف والمادة في الحقول لا خارجها: كورس بلا
     * صف **يولد محجوبا** — لا يظهر في «المواد والبرامج»، ولا تفتحه باقة،
     * ولا يصل إليه طالب، ولا شيء في شاشته يقول لماذا.
     */
    private function teacher_course_create()
    {
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        list($post, $files) = $this->wbody();
        $r = $this->cur()->save_course($this->tactor((int) $u['id']), 0, $post, $files);

        if (!empty($r['ok'])) {
            $this->api->audit('api.course.create', (int) $u['id'],
                              array('course_id' => (int) (isset($r['id']) ? $r['id'] : 0)));
        }

        $this->wrote($r, t('تعذر حفظ الكورس.'), 'save_failed',
                     array('id' => (int) (isset($r['id']) ? $r['id'] : 0)), 201);
    }

    /** PATCH /api/v1/teacher/courses/{id} — تعديل كورس قائم. */
    private function teacher_course_update($id)
    {
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $id    = (int) $id;
        $actor = $this->tactor((int) $u['id']);

        if (!$this->cur()->may_edit_course($actor, $id)) {
            $this->fail('لا كورس بهذا الرقم في نطاقك.', 'not_found', 404);
        }

        list($post, $files) = $this->wbody();
        $r = $this->cur()->save_course($actor, $id, $post, $files);

        if (!empty($r['ok'])) {
            $this->api->audit('api.course.save', (int) $u['id'], array('course_id' => $id));
        }

        $this->wrote($r, t('تعذر حفظ الكورس.'), 'save_failed', array('id' => $id));
    }

    /* ---- الأقسام ---------------------------------------------------- */

    /** POST /api/v1/teacher/sections — قسم جديد في كورس. */
    public function teacher_sections()
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b   = $this->body();
        $cid = (int) (isset($b['course_id']) ? $b['course_id'] : 0);
        $r   = $this->cur()->save_section($this->tactor((int) $u['id']), $cid, 0, $b);

        $this->wrote($r, t('تعذر حفظ القسم.'), 'save_failed',
                     array('id' => (int) (isset($r['id']) ? $r['id'] : 0), 'course_id' => $cid), 201);
    }

    /** PATCH · DELETE /api/v1/teacher/sections/{id} */
    public function teacher_section($id = 0)
    {
        $m = $this->method(array('PATCH', 'POST', 'DELETE'));
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $id    = (int) $id;
        $actor = $this->tactor((int) $u['id']);

        /* الملكية تفحص هنا كذلك لا في النموذج وحده: النموذج يفحصها،
           ولكن «لا قسم بهذا الرقم» أوضح حين يكون الرقم مخترعا أصلا. */
        if (!$this->cur()->may_edit_section($actor, $id)) {
            $this->fail('لا قسم بهذا الرقم في نطاقك.', 'not_found', 404);
        }

        $sec = $this->cur()->section($id);
        $cid = (int) (isset($sec['course_id']) ? $sec['course_id'] : 0);

        if ($m === 'DELETE') {
            $r = $this->cur()->delete_section($actor, $id);
            $this->api->audit('api.section.delete', (int) $u['id'], array('section_id' => $id));
            $this->wrote($r, t('تعذر حذف القسم.'), 'delete_failed',
                         array('id' => $id, 'course_id' => $cid));
        }

        $r = $this->cur()->save_section($actor, $cid, $id, $this->body());
        $this->wrote($r, t('تعذر حفظ القسم.'), 'save_failed',
                     array('id' => $id, 'course_id' => $cid));
    }

    /**
     * POST /api/v1/teacher/sections/sort — ترتيب أقسام كورس.
     *
     * والقائمة كاملة لا فرقا: `sort_sections()` تكتب الترتيب من موضع كل
     * معرف فيها، فإرسال المنقول وحده يترك البقية على أرقامها القديمة.
     */
    public function teacher_sections_sort()
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b   = $this->body();
        $cid = (int) (isset($b['course_id']) ? $b['course_id'] : 0);
        $r   = $this->cur()->sort_sections($this->tactor((int) $u['id']), $cid,
                                           (array) (isset($b['ids']) ? $b['ids'] : array()));

        $this->wrote($r, t('تعذر ترتيب الأقسام.'), 'sort_failed', array('course_id' => $cid));
    }

    /* ---- الدروس ----------------------------------------------------- */

    /**
     * POST /api/v1/teacher/lessons — درس جديد.
     *
     * وحقوله بحسب نوعه من `lesson_types()` — والتحقق في النموذج: نوع
     * بحقل ناقص يرد برسالته، ونسخة ثانية من القواعد هنا تقبل ما يرفضه
     * الموقع.
     *
     * **و«أول قسم في الكورس» تيسير الشاشة لا قاعدة**: من أرسل `course_id`
     * بلا `section_id` يقع درسه في أول قسم، كما يقع من شاشة «رفع الدروس»
     * (TQ-UPLOAD-FOLD). وبلا ذلك يرد النموذج «حدد القسم» على من لا يعرف
     * أن للكورس أقساما أصلا.
     */
    private function teacher_lesson_create()
    {
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        list($post, $files) = $this->lesson_post();

        $cid = (int) (isset($post['course_id']) ? $post['course_id'] : 0);
        if ($cid > 0 && (int) (isset($post['section_id']) ? $post['section_id'] : 0) <= 0) {
            $first = $this->first_section_of($cid);
            if ($first > 0) $post['section_id'] = $first;
        }

        $r = $this->cur()->save_lesson($this->tactor((int) $u['id']), 0, $post, $files);

        if (!empty($r['ok'])) {
            $this->api->audit('api.lesson.create', (int) $u['id'],
                              array('lesson_id' => (int) (isset($r['id']) ? $r['id'] : 0),
                                    'course_id' => $cid));
        }

        $this->wrote($r, t('تعذر حفظ الدرس.'), 'save_failed',
                     array('id' => (int) (isset($r['id']) ? $r['id'] : 0), 'course_id' => $cid), 201);
    }

    /** أول قسم في كورس — تيسير الباب، لا قاعدة في الطبقة. */
    private function first_section_of($course_id)
    {
        $r = $this->db->select('id')->where('course_id', (int) $course_id)
                      ->order_by('`order`', 'ASC')->order_by('id', 'ASC')
                      ->limit(1)->get('section')->row_array();
        return (int) (isset($r['id']) ? $r['id'] : 0);
    }

    /**
     * GET · PATCH · DELETE /api/v1/teacher/lessons/{id}
     *
     * والقراءة ترد الصف بحقوله كما يخزن **ونوعه مستنتجا**: الأعمدة
     * الثلاثة (`lesson_type` · `attachment_type` · `video_type`) تفرق
     * بين الأنواع ولا يحمل الصف مفتاح النوع، فـ`kind_of()` تستنتجه —
     * في النموذج لا في التطبيق.
     */
    public function teacher_lesson($id = 0)
    {
        $m = $this->method(array('GET', 'PATCH', 'POST', 'DELETE'));
        $u = $this->require_teacher();

        $id    = (int) $id;
        $actor = $this->tactor((int) $u['id']);

        if (!$this->cur()->may_edit_lesson($actor, $id)) {
            $this->fail('لا درس بهذا الرقم في نطاقك.', 'not_found', 404);
        }

        if ($m === 'DELETE') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);
            $les = $this->cur()->lesson($id);
            $r   = $this->cur()->delete_lesson($actor, $id);
            $this->api->audit('api.lesson.delete', (int) $u['id'], array('lesson_id' => $id));
            $this->wrote($r, t('تعذر حذف الدرس.'), 'delete_failed',
                         array('id' => $id,
                               'course_id' => (int) (isset($les['course_id']) ? $les['course_id'] : 0)));
        }

        if ($m !== 'GET') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);
            list($post, $files) = $this->lesson_post();
            $r = $this->cur()->save_lesson($actor, $id, $post, $files);
            $this->api->audit('api.lesson.save', (int) $u['id'], array('lesson_id' => $id));
            $this->wrote($r, t('تعذر حفظ الدرس.'), 'save_failed', array('id' => $id));
        }

        $h   = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);
        $row = $this->cur()->lesson($id);
        if (!$row) $this->fail('لا درس بهذا الرقم في نطاقك.', 'not_found', 404);

        $kind = Taqdar_curriculum_model::kind_of($row);

        $this->read(array(
            'id'           => $id,
            'title'        => (string) (isset($row['title']) ? $row['title'] : ''),
            'kind'         => $kind,
            'course_id'    => (int) (isset($row['course_id']) ? $row['course_id'] : 0),
            'section_id'   => (int) (isset($row['section_id']) ? $row['section_id'] : 0),
            'duration'     => (string) (isset($row['duration']) ? $row['duration'] : ''),
            'duration_sec' => (int) (isset($row['duration_sec']) ? $row['duration_sec'] : 0),
            'is_free'      => !empty($row['is_free']),
            'status'       => (string) (isset($row['tq_status']) ? $row['tq_status'] : 'published'),
            'order'        => (int) (isset($row['order']) ? $row['order'] : 0),
            'summary'      => (string) (isset($row['summary']) ? $row['summary'] : ''),
            'video_url'    => (string) (isset($row['video_url']) ? $row['video_url'] : ''),
            'video_type'   => (string) (isset($row['video_type']) ? $row['video_type'] : ''),
            'attachment'   => (string) (isset($row['attachment']) ? $row['attachment'] : ''),
            'attachment_type' => (string) (isset($row['attachment_type']) ? $row['attachment_type'] : ''),
            /* الوصف يخرج مع الصف: نموذج التعديل في التطبيق يبنى منه،
               ونسخة ثانية منه في Dart تفترق عند أول نوع يضاف. */
            'type_spec'    => Taqdar_curriculum_model::lesson_type($kind),
            'objectives'   => $this->cur()->objectives_of($id),
            'quiz'         => $this->qz()->readiness($id),
            'review_note'  => (string) (isset($row['tq_review_note']) ? $row['tq_review_note'] : ''),
            'pending_revision' => (bool) $this->cur()->pending_revision('lesson', $id),
        ), '', array(), $h);
    }

    /** POST /api/v1/teacher/lessons/sort — ترتيب دروس قسم. */
    public function teacher_lessons_sort()
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b   = $this->body();
        $sid = (int) (isset($b['section_id']) ? $b['section_id'] : 0);
        $r   = $this->cur()->sort_lessons($this->tactor((int) $u['id']), $sid,
                                          (array) (isset($b['ids']) ? $b['ids'] : array()));

        $this->wrote($r, t('تعذر ترتيب الدروس.'), 'sort_failed', array('section_id' => $sid));
    }

    /**
     * POST /api/v1/teacher/lessons/{id}/move — نقل درس إلى قسم آخر.
     *
     * والقسم الوجهة **من الكورس نفسه** — والفحص في النموذج: نقل درس إلى
     * قسم كورس آخر يترك الدرس معلقا بين اثنين، ويقرؤه المنهجان.
     */
    public function teacher_lesson_move($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b   = $this->body();
        $sid = (int) (isset($b['section_id']) ? $b['section_id'] : 0);
        $r   = $this->cur()->move_lesson($this->tactor((int) $u['id']), (int) $id, $sid);

        $this->wrote($r, t('تعذر نقل الدرس.'), 'move_failed',
                     array('id' => (int) $id, 'section_id' => $sid));
    }

    /* ---- اختبار الدرس ----------------------------------------------- */

    /**
     * GET · PATCH /api/v1/teacher/lessons/{id}/quiz
     *
     * وهو **بوابة الإتقان نفسها لا نظام رابع**: أسئلة الدرس تنسب إلى
     * تقييم `type='review'` الذي يحكم فتح الدرس التالي، فالقفل
     * والمحاولات الثلاث وتصعيدها ودفتر الأخطاء وخريطة الإتقان تعمل لما
     * يؤلف هنا بلا سطر يضاف.
     *
     * **ولوح الجاهزية يخرج مع الأسئلة** (`readiness()`): «حد النجاح أكبر
     * من عدد الأسئلة» و«كذا سؤالا بلا هدف» خبران لا يظهران في صف الجدول،
     * وبلاهما يحفظ المعلم اختبارا لا يجتازه أحد ولا يقول شيء لماذا.
     */
    public function teacher_lesson_quiz($id = 0)
    {
        $m = $this->method(array('GET', 'PATCH', 'POST'));
        $u = $this->require_teacher();

        $id    = (int) $id;
        $actor = $this->tactor((int) $u['id']);

        if (!$this->cur()->may_edit_lesson($actor, $id)) {
            $this->fail('لا درس بهذا الرقم في نطاقك.', 'not_found', 404);
        }

        if ($m !== 'GET') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);
            $r = $this->qz()->save_settings($actor, $id, $this->body());
            $this->wrote($r, t('تعذر حفظ إعدادات الاختبار.'), 'save_failed',
                         array('lesson_id' => $id));
        }

        $h    = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);
        $quiz = $this->qz()->quiz_of($id);

        /* `with_answers = true` هنا وحدها: هذه شاشة المؤلف، ومفاتيح الحل
           في رد الطالب غش بضغطة — ولذلك لا تنادى من نقاط `/student/*`. */
        $this->read(array(
            'lesson_id' => $id,
            'settings'  => array(
                'assessment_id'    => (int) (isset($quiz['id']) ? $quiz['id'] : 0),
                'pass_mark'        => (int) (isset($quiz['pass_mark']) ? $quiz['pass_mark'] : 0),
                'time_limit_sec'   => isset($quiz['time_limit_sec']) && $quiz['time_limit_sec'] !== null
                                    ? (int) $quiz['time_limit_sec'] : null,
                'attempts_allowed' => (int) (isset($quiz['attempts_allowed']) ? $quiz['attempts_allowed'] : 0),
            ),
            'questions'  => $this->qz()->questions($id, true),
            /* والهدف اختياري ولكن **افتراضه أول هدف** (TQ-QOBJ): سؤال بلا
               هدف يصحح ولا يكتب صف `skill_state` واحد، فتخرج القائمة مع
               الأسئلة ليقع الاختيار لا السكوت. */
            'objectives' => $this->cur()->objectives_of($id),
            'readiness'  => $this->qz()->readiness($id),
            'stats'      => $this->qz()->question_stats($id),
        ), '', array(), $h);
    }

    /**
     * POST /api/v1/teacher/lessons/{id}/quiz/questions — سؤال جديد.
     *
     * و«الصحيح» يرسل **بموضعه في الخيارات** لا نصا ولا رقما حرا:
     * `save_question()` تترجمه إلى نصه بعد التنقية، فيبقى صحيحا ولو سقط
     * خيار فارغ من المنتصف.
     */
    public function teacher_quiz_questions($id = 0)
    {
        $this->method('POST');
        $this->quiz_question_write((int) $id, 0);
    }

    /** PATCH · DELETE /api/v1/teacher/lessons/{id}/quiz/questions/{qid} */
    public function teacher_quiz_question($id = 0, $qid = 0)
    {
        $m = $this->method(array('PATCH', 'POST', 'DELETE'));

        if ($m === 'DELETE') {
            $u     = $this->require_teacher();
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);
            $actor = $this->tactor((int) $u['id']);
            $r     = $this->qz()->delete_question($actor, (int) $id, (int) $qid);
            $this->wrote($r, t('تعذر حذف السؤال.'), 'delete_failed',
                         array('id' => (int) $qid, 'lesson_id' => (int) $id));
        }

        $this->quiz_question_write((int) $id, (int) $qid);
    }

    /** الكتابة الواحدة للإنشاء والتحرير — والفارق المعرف وحده. */
    private function quiz_question_write($lesson_id, $qid)
    {
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $actor = $this->tactor((int) $u['id']);
        $post  = $this->body();

        /* الرفع هنا لا في النموذج: النموذج يستقبل مصفوفة `post` وحدها
           ولا يرى `$_FILES`. و`tq_qimage_upload()` ترد `false` على ملف
           مرفوض و`''` على «لم يرفع شيء» — والفرق بينهما هو الفرق بين
           خطأ يقال وحقل لا يمس. */
        $img = tq_qimage_upload('image');
        if ($img === false) {
            $this->fail('الصورة مرفوضة — صيغة مقبولة (jpg · png · gif · webp) وحجم دون 4 ميجابايت.',
                        'validation_failed', 422,
                        array('image' => array(t('صيغة أو حجم غير مقبول.'))));
        }
        if ($img !== '') $post['image'] = $img;

        $r = $this->qz()->save_question($actor, (int) $lesson_id, (int) $qid, $post);

        $this->wrote($r, t('تعذر حفظ السؤال.'), 'save_failed',
                     array('id'        => (int) (isset($r['id']) ? $r['id'] : $qid),
                           'lesson_id' => (int) $lesson_id),
                     $qid > 0 ? 200 : 201);
    }

    /** POST /api/v1/teacher/lessons/{id}/quiz/questions/sort */
    public function teacher_quiz_questions_sort($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $b = $this->body();
        $r = $this->qz()->sort_questions($this->tactor((int) $u['id']), (int) $id,
                                         (array) (isset($b['ids']) ? $b['ids'] : array()));

        $this->wrote($r, t('تعذر ترتيب الأسئلة.'), 'sort_failed', array('lesson_id' => (int) $id));
    }

    /**
     * GET /api/v1/teacher/lessons/{id}/quiz/attempts — محاولات طلابه.
     *
     * **وآخر محاولة لكل طالب** لا كلها: السؤال «أين هو الآن؟» — وقائمة
     * تعرض كل محاولة تجعل من رسب ثم نجح يقرأ راسبا في أول صف.
     */
    public function teacher_quiz_attempts($id = 0)
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $actor = $this->tactor((int) $u['id']);
        if (!$this->cur()->may_edit_lesson($actor, (int) $id)) {
            $this->fail('لا درس بهذا الرقم في نطاقك.', 'not_found', 404);
        }

        $rows = $this->qz()->attempts_of_lesson((int) $id, 300);

        $this->read($rows, '', array('total' => count($rows),
                                     'readiness' => $this->qz()->readiness((int) $id)), $h);
    }

    /* ---- الكتب ------------------------------------------------------- */

    /**
     * GET /api/v1/teacher/books/form — وصف حقول الكتاب، وقيم كتاب قائم.
     *
     * TQ-BOOK-GRADE — و`grade_id` حقل `admin` فلا يصل المعلم: به وحده
     * تفتح الباقة الكتاب ويدخل صاحبه في قسمة وعائها، وهو قرار عمل لا
     * قرار محتوى.
     */
    public function teacher_book_form()
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $actor = array('id' => (int) $u['id'], 'role' => 'teacher');
        $spec  = $this->bk()->book_fields($actor);

        $id     = (int) $this->input->get('id');
        $values = array();
        if ($id > 0) {
            $row = $this->bk()->book($id);
            if (!$row || (int) (isset($row['teacher_id']) ? $row['teacher_id'] : 0) !== (int) $u['id']) {
                $this->fail('لا كتاب بهذا الرقم في نطاقك.', 'not_found', 404);
            }
            foreach ($spec as $name => $f) {
                $col = isset($f['col']) ? $f['col'] : $name;
                $values[$name] = array_key_exists($col, $row) ? $row[$col] : null;
            }
        }

        $this->read(array(
            'book_id'     => $id,
            'fields'      => $spec,
            'values'      => $values,
            'may_publish' => (bool) $this->bk()->may_publish($actor),
        ), '', array('sales_enabled' => (bool) $this->bk()->enabled()), $h);
    }

    private function bk()
    {
        $this->load->model('taqdar_book_model', 'tq_bk_m');
        $this->tq_bk_m->ensure_schema();
        return $this->tq_bk_m;
    }

    /**
     * POST /api/v1/teacher/books — كتاب جديد.
     *
     * TQ-BOOK-REVIEW — والطابور واحد لا رابع: ما يعلنه المعلم «منشورا»
     * يحفظ `review` بحكم `may_publish()`، ويقرأ في `taqdar_admin/review`
     * مع الدرس والاقتراح والكورس.
     */
    private function teacher_book_create()
    {
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        list($post, $files) = $this->wbody();
        $r = $this->bk()->save_book(array('id' => (int) $u['id'], 'role' => 'teacher'),
                                    0, $post, $files);

        if (!empty($r['ok'])) {
            $this->api->audit('api.book.create', (int) $u['id'],
                              array('book_id' => (int) (isset($r['id']) ? $r['id'] : 0)));
        }

        $this->wrote($r, t('تعذر حفظ الكتاب.'), 'save_failed',
                     array('id' => (int) (isset($r['id']) ? $r['id'] : 0)), 201);
    }

    /**
     * GET · PATCH · DELETE /api/v1/teacher/books/{id}
     *
     * TQ-BOOK-DELETE — وكتاب بيع لا يحذف: بند الاستحقاق يشير إلى
     * `books.id`، فالحذف **يقطع وصولا اشتري**. و`delete_blockers()` ترد
     * بالرقم لا بـ«غير مسموح» — من قرأ «لا يحذف» بلا سبب يظن الشاشة
     * معطلة.
     */
    public function teacher_book($id = 0)
    {
        $m = $this->method(array('GET', 'PATCH', 'POST', 'DELETE'));
        $u = $this->require_teacher();

        $id    = (int) $id;
        $actor = array('id' => (int) $u['id'], 'role' => 'teacher');
        $row   = $this->bk()->book($id);

        if (!$row || (int) (isset($row['teacher_id']) ? $row['teacher_id'] : 0) !== (int) $u['id']) {
            $this->fail('لا كتاب بهذا الرقم في نطاقك.', 'not_found', 404);
        }

        if ($m === 'DELETE') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);
            $r = $this->bk()->delete_book($actor, $id);
            $this->api->audit('api.book.delete', (int) $u['id'], array('book_id' => $id));
            $this->wrote($r, t('تعذر حذف الكتاب.'), 'delete_failed', array('id' => $id));
        }

        if ($m !== 'GET') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);
            list($post, $files) = $this->wbody();
            $r = $this->bk()->save_book($actor, $id, $post, $files);
            $this->api->audit('api.book.save', (int) $u['id'], array('book_id' => $id));
            $this->wrote($r, t('تعذر حفظ الكتاب.'), 'save_failed', array('id' => $id));
        }

        $h     = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);
        $offer = $this->bk()->offer($row);

        $this->read(array(
            'id'          => $id,
            'title'       => (string) (isset($row['title']) ? $row['title'] : ''),
            'slug'        => (string) (isset($row['slug']) ? $row['slug'] : ''),
            'subject'     => (string) (isset($row['subject']) ? $row['subject'] : ''),
            'author'      => (string) (isset($row['author']) ? $row['author'] : ''),
            'description' => (string) (isset($row['description']) ? $row['description'] : ''),
            'status'      => (string) (isset($row['status']) ? $row['status'] : ''),
            'grade_id'    => isset($row['grade_id']) ? (int) $row['grade_id'] : null,
            'category_id' => (int) (isset($row['category_id']) ? $row['category_id'] : 0),
            'pages'       => (int) (isset($row['pages']) ? $row['pages'] : 0),
            'sellable'    => !empty($offer['sellable']),
            'reason'      => (string) (isset($offer['reason']) ? $offer['reason'] : ''),
            'why'         => (string) (isset($offer['why']) ? $offer['why'] : ''),
            'price'       => tq_api_money((int) (isset($offer['price_halalas']) ? $offer['price_halalas'] : 0)),
            'my_share'    => tq_api_money((int) (isset($offer['teacher_share_halalas']) ? $offer['teacher_share_halalas'] : 0)),
            /* وما يمنع الحذف يقال **قبل** أن يضغط لا بعده: زر يرد كل مرة
               يقرأ عطلا. */
            'delete_blockers' => $this->bk()->delete_blockers($id),
        ), '', array(), $h);
    }

    /* ---- أوقات الحصص (TQ-SESSION-GRID) ------------------------------ */

    /**
     * GET · PUT /api/v1/teacher/availability — القاعدة الأسبوعية الدائمة.
     *
     * وهي **سطور** لا فترات ثابتة: يوم، ومن، وإلى، وصف، ومادة. و
     * `availability_slots` حاصلها لا مصدرها — تفرش من هنا وتتجدد كل
     * ساعة من `lifecycle_tick()`، فلا تنفد الشبكة بعد آخر حفظ.
     *
     * **والحفظ استبدال كامل**: ما يرسل هو ما يبقى، فحذف سطر من القائمة
     * حذف من القاعدة. وإضافة صف بصف تجعل الحذف يحتاج مسار كتابة ثانيا
     * ومعرفا يرسل من متصفح.
     *
     * **ونطاق المعلم صفوف كورساته ومواده وحدها** — ولا يرتد إلى «كل
     * الصفوف»: من يفتح وقتا لما لا يدرسه يجلس ساعة مع طالب لا يفيده فيها
     * وقد قبض ثمنها. فمن لا نطاق له يقرأ ذلك في `scope` ولا يعرض له
     * جدول يرد كل حفظ.
     */
    public function teacher_availability()
    {
        $m = $this->method(array('GET', 'PUT', 'POST'));
        $u = $this->require_teacher();

        $uid  = (int) $u['id'];
        $sess = $this->tq_sessions();
        $sess->install_schema();

        if ($m !== 'GET') {
            $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

            $b    = $this->body();
            $rows = array();
            foreach ((array) (isset($b['windows']) ? $b['windows'] : array()) as $w) {
                if (!is_array($w)) continue;
                $rows[] = array(
                    'dow'        => isset($w['dow'])  ? trim((string) $w['dow'])  : '',
                    'from'       => isset($w['from']) ? trim((string) $w['from']) : '',
                    'to'         => isset($w['to'])   ? trim((string) $w['to'])   : '',
                    'grade_id'   => (int) (isset($w['grade_id'])   ? $w['grade_id']   : 0),
                    'subject_id' => (int) (isset($w['subject_id']) ? $w['subject_id'] : 0),
                );
            }

            $r = $sess->save_windows($uid, $rows);
            if (empty($r['ok'])) {
                /* والرفض يسمي يومه وساعته وصفه — `save_windows()` ترد
                   `msg` بذلك، ومن قرأ «تعذر تنفيذ الطلب» يعيد المحاولة
                   بلا ما يصحح. */
                $this->fail($this->model_msg($r, t('تعذر حفظ أوقاتك.')), 'save_failed', 409);
            }

            $this->api->audit('api.sessions.windows', $uid,
                              array('windows' => (int) (isset($r['count']) ? $r['count'] : 0),
                                    'slots'   => (int) (isset($r['slots']) ? $r['slots'] : 0)));

            /* **والخبر هو ما يراه الطالب الآن** لا «حفظ»: معلم كتب وقتا
               أقصر من مدة الحصة يحفظ صفا ولا يفرش موعدا واحدا، فيقرأ
               «حفظت» ويبقى غائبا عن شاشة الطالب ولا شيء يقول لماذا. */
            $this->respond(tq_api_ok(array(
                'windows' => (int) (isset($r['count']) ? $r['count'] : 0),
                'slots'   => (int) (isset($r['slots']) ? $r['slots'] : 0),
            ), $this->model_msg($r, t('حفظت أوقاتك.'))), 200);
        }

        $h    = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);
        $cfg  = $sess->config();
        $pric = $sess->pricing_for($uid);

        $this->read(array(
            'windows' => $sess->windows_for($uid),
            'days'    => $sess->days(),
            'minutes' => (int) $cfg['minutes'],
            'scope'   => array(
                'grades'   => $sess->teacher_grades($uid),
                'subjects' => $sess->teacher_subject_options($uid),
            ),
            /* وثمن حصته ونصيبه منها يخرجان هنا كذلك: من يفتح وقتا يسأل
               «بكم تباع ساعتي؟» قبل أن يكتب ساعة. */
            'pricing' => array(
                'price'   => tq_api_money((int) (isset($pric['price']) ? $pric['price'] : 0)),
                'percent' => (int) (isset($pric['percent']) ? $pric['percent'] : 0),
                'share'   => tq_api_money((int) (isset($pric['share']) ? $pric['share'] : 0)),
            ),
        ), '', array(), $h);
    }

    /* ---- المحفظة: إلغاء طلب سحب -------------------------------------- */

    /**
     * POST /api/v1/teacher/wallet/payouts/{id}/cancel
     *
     * والملكية والحالة تفحصان هنا لأن `cancel_payout()` نداء إداري يقبل
     * أي معرف: طلب ليس له، أو حول بالفعل، أو ملغى من قبل — ثلاثة ردود
     * لا رد واحد، لأن كل واحد منها يعالج بغير ما يعالج به الآخر.
     */
    public function teacher_payout_cancel($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid = (int) $u['id'];
        $id  = (int) $id;

        $row = $id > 0
             ? $this->db->select('id, user_id, status')->where('id', $id)
                        ->get('payout')->row_array()
             : null;

        if (!$row || (int) $row['user_id'] !== $uid) {
            $this->fail('هذا الطلب ليس من طلباتك.', 'not_found', 404);
        }
        if ((int) $row['status'] === 1) {
            $this->fail('هذا الطلب حول بالفعل، فلا يلغى.', 'already_paid', 409);
        }
        if ((int) $row['status'] === 2) {
            $this->fail('هذا الطلب ملغى من قبل.', 'already_cancelled', 409);
        }

        $ok = (bool) $this->wal()->cancel_payout($id);
        if (!$ok) $this->fail('تعذر إلغاء الطلب — أعد المحاولة.', 'cancel_failed', 409);

        $this->api->audit('api.wallet.cancel', $uid, array('payout_id' => $id));

        $this->respond(tq_api_ok(array('id' => $id, 'status' => 'cancelled'),
                                 t('ألغي الطلب، وعاد مبلغه إلى رصيدك المتاح.')), 200);
    }

    /* ---- استوديو المحتوى --------------------------------------------- */

    /**
     * GET /api/v1/teacher/lessons/{id}/studio — مخرجات الدرس وحالتها.
     *
     * وخطوتان من دورة الإنتاج تعيشان هنا: التوليد الآلي، **واعتماد
     * المعلم لكل مخرج قبل النشر**. و«لا نشر تلقائي» ليس تحفظا: مخرج
     * يولد ويصل الطالب بلا أن تقرأه عين أحد يعلمه ما لم يقصده أحد.
     */
    public function teacher_studio($id = 0)
    {
        $this->method('GET');
        $u = $this->require_teacher();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $id = (int) $id;
        $this->studio_guard((int) $u['id'], $id);

        $st = $this->studio();

        $this->read(array(
            'lesson_id' => $id,
            'asset'     => $st->asset($id),
            'outputs'   => $st->outputs($id),
            'playable'  => (bool) $st->is_playable($id),
        ), '', array(), $h);
    }

    private function studio()
    {
        $this->load->model('taqdar_studio_model', 'tq_studio_m');
        $this->tq_studio_m->ensure_schema();
        return $this->tq_studio_m;
    }

    /** ملكية الدرس — الحكم الواحد الذي تفحص به شاشة الويب كذلك. */
    private function studio_guard($uid, $lesson_id)
    {
        if (!$this->cur()->may_edit_lesson($this->tactor((int) $uid), (int) $lesson_id)) {
            $this->fail('لا درس بهذا الرقم في نطاقك.', 'not_found', 404);
        }
    }

    /**
     * POST /api/v1/teacher/lessons/{id}/studio/generate — توليد مسودات.
     *
     * **ولا ينشر شيئا**: يكتب مسودات، والاعتماد مسار مستقل تحته.
     */
    public function teacher_studio_generate($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('export', self::RL_HEAVY_MAX, self::RL_HEAVY_WINDOW);

        $id = (int) $id;
        $this->studio_guard((int) $u['id'], $id);

        $b    = $this->body();
        $only = isset($b['only']) ? $b['only'] : null;
        $r    = $this->studio()->generate($id, (int) $u['id'], $only);

        $this->api->audit('api.studio.generate', (int) $u['id'], array('lesson_id' => $id));
        $this->wrote($r, t('تعذر توليد المخرجات.'), 'generate_failed', array('lesson_id' => $id));
    }

    /**
     * POST /api/v1/teacher/lessons/{id}/studio/output — حفظ تعديل مخرج.
     *
     * **ويعيده مسودة إن كان معتمدا**: نص يعدل بعد اعتماده لم يقرأه أحد
     * بصورته الجديدة، فاعتماد الأمس لا يسري عليه.
     */
    public function teacher_studio_output($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $id = (int) $id;
        $this->studio_guard((int) $u['id'], $id);

        $b    = $this->body();
        $kind = (string) (isset($b['kind']) ? $b['kind'] : '');
        $data = isset($b['data']) ? $b['data'] : null;

        /* والحمولة **مصفوفة لا نص**: شاشة الويب ترسل JSON في حقل نصي
           لأن النموذج نموذج، وهنا الجسم JSON أصلا. والنص يقبل كذلك لمن
           نقل حمولته كما هي. */
        if (is_string($data)) $data = json_decode($data, true);
        if (!is_array($data)) {
            $this->fail('حمولة المخرج غير مفهومة.', 'validation_failed', 422,
                        array('data' => array(t('أرسل كائنا أو نص JSON صالحا.'))));
        }

        $r = $this->studio()->save_output($id, $kind, $data, (int) $u['id']);
        $this->wrote($r, t('تعذر حفظ المخرج.'), 'save_failed',
                     array('lesson_id' => $id, 'kind' => $kind));
    }

    /**
     * POST /api/v1/teacher/lessons/{id}/studio/approve — اعتماد أو رفض.
     *
     * **والرفض يعيده مسودة ولا يحذفه**: عمل المعلم لا يمحى بضغطة.
     */
    public function teacher_studio_approve($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $id = (int) $id;
        $this->studio_guard((int) $u['id'], $id);

        $b    = $this->body();
        $kind = (string) (isset($b['kind']) ? $b['kind'] : '');
        $act  = (string) (isset($b['act'])  ? $b['act']  : 'approve');

        $r = ($act === 'reject')
           ? $this->studio()->reject_output($id, $kind, (int) $u['id'],
                                            (string) (isset($b['reason']) ? $b['reason'] : ''))
           : $this->studio()->approve($id, $kind, (int) $u['id']);

        $this->api->audit('api.studio.' . ($act === 'reject' ? 'reject' : 'approve'),
                          (int) $u['id'], array('lesson_id' => $id, 'kind' => $kind));

        $this->wrote($r, t('تعذر تنفيذ القرار.'), 'decision_failed',
                     array('lesson_id' => $id, 'kind' => $kind));
    }

    /** POST /api/v1/teacher/lessons/{id}/studio/transcript — نص الدرس. */
    public function teacher_studio_transcript($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $id = (int) $id;
        $this->studio_guard((int) $u['id'], $id);

        $this->load->model('taqdar_learn_model', 'tq_learn_m');
        $r = $this->tq_learn_m->save_transcript($id, (string) $this->in('transcript', ''));

        $this->respond(tq_api_ok(array('lesson_id' => $id),
                                 $this->model_msg($r, t('حفظ نص الدرس.'))), 200);
    }

    /**
     * POST /api/v1/teacher/lessons/{id}/studio/state — نقل حالة الأصل.
     *
     * والمعلم ينقل إلى `processed` و`in_review` وحدهما: النشر والرفض
     * للإدارة بعد المراجعة، ومعلم ينشر لنفسه يلغي المراجعة كلها.
     */
    public function teacher_studio_state($id = 0)
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $id = (int) $id;
        $this->studio_guard((int) $u['id'], $id);

        $to = (string) $this->in('to', '');
        if (!in_array($to, array('processed', 'in_review'), true)) {
            $this->fail('النشر والرفض من الإدارة بعد المراجعة العلمية والفنية.',
                        'validation_failed', 422,
                        array('to' => array(t('القيم المقبولة: processed · in_review'))));
        }

        $r = $this->studio()->transition($id, $to, (int) $u['id']);
        $this->wrote($r, t('تعذر نقل الحالة.'), 'transition_failed',
                     array('lesson_id' => $id, 'state' => $to));
    }

    /* ---- بنك الأسئلة: الاستيراد ووصفة التوليد ------------------------ */

    /**
     * POST /api/v1/teacher/questions/import — استيراد أسئلة من CSV.
     *
     * والملف يرفع `multipart` في الحقل `csv`، وحدوده حدود الويب نفسها:
     * صيغة، وحجم، وملكية الدرس والكورس. ونسخة ثانية من الحدود هنا تقبل
     * ما يرفضه الموقع.
     */
    public function teacher_questions_import()
    {
        $this->method('POST');
        $u = $this->require_teacher();
        $this->limit('export', self::RL_HEAVY_MAX, self::RL_HEAVY_WINDOW);

        $uid   = (int) $u['id'];
        $actor = $this->tactor($uid);

        $lesson_id = (int) $this->in('lesson_id', 0);
        $course_id = (int) $this->in('course_id', 0);

        if ($lesson_id > 0 && !$this->cur()->may_edit_lesson($actor, $lesson_id)) {
            $this->fail('هذا الدرس في كورس ليس لك.', 'not_found', 404);
        }
        if ($course_id > 0 && !$this->cur()->may_edit_course($actor, $course_id)) {
            $this->fail('هذا الكورس ليس لك.', 'not_found', 404);
        }

        if (empty($_FILES['csv']['name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $this->fail('أرفق ملف CSV في الحقل csv.', 'validation_failed', 422,
                        array('csv' => array(t('الملف مطلوب.'))));
        }
        if ((int) $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
            $this->fail('تعذر رفع الملف — أعد المحاولة.', 'upload_failed', 422);
        }
        if ((int) $_FILES['csv']['size'] > 2 * 1024 * 1024) {
            $this->fail('حجم الملف يتجاوز ٢ ميغابايت.', 'file_too_large', 422);
        }
        $ext = strtolower((string) pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv', 'txt'), true)) {
            $this->fail('الملف لا بد أن يكون بصيغة CSV.', 'unsupported_type', 422);
        }

        $r = $this->tm()->import_questions($uid, array(
            'lesson_id' => $lesson_id,
            'course_id' => $course_id,
        ), $_FILES['csv']);

        $this->api->audit('api.questions.import', $uid,
                          array('lesson_id' => $lesson_id, 'course_id' => $course_id));

        $this->wrote($r, t('تعذر استيراد الأسئلة.'), 'import_failed', array(
            'imported' => (int) (isset($r['imported']) ? $r['imported'] : 0),
            'skipped'  => (int) (isset($r['skipped'])  ? $r['skipped']  : 0),
        ));
    }

    /* =================================================================
       بوابة ولي الأمر — شاشة الشراء
       ================================================================= */

    /**
     * GET /api/v1/parent/pay — ماذا يشترى، ولمن، وبكم.
     *
     * وهي شاشة **مستقلة عن «المدفوعات»**: تلك سجل قراءة وهذه فعل. وكان
     * في الواجهة بابها الكاتب (`POST /parent/pay`) بلا ما يقرأ: يعرف
     * التطبيق كيف يدفع ولا يعرف **ماذا يعرض** — فلا باقة ولا سعر ولا
     * دورة، وباب شراء لا يقول ما يباع ليس بابا.
     *
     * **والباقة المعروضة هي `scope = 'grade'` وحدها** — القاعدة نفسها
     * التي ترشح بها `/plans` و`tqs_bundles()`. وباقة بنطاق آخر تمنح من
     * اللوحة ولا تظهر في شاشة شراء.
     */
    public function parent_pay_options()
    {
        $this->method('GET');
        $u = $this->require_parent();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $pid = (int) $u['id'];

        $this->load->model('taqdar_billing_model', 'tq_bill');
        $this->load->model('taqdar_tap_model', 'tq_tap');

        $kids = array();
        foreach ($this->pm()->children($pid) as $c) {
            $kids[] = array(
                'student_id' => (int) $c['student_id'],
                'name'       => trim($c['first_name'] . ' ' . $c['last_name']),
                'avatar_url' => tq_api_avatar(isset($c['image']) ? $c['image'] : ''),
            );
        }

        /* **والشكل شكل `/student/plans` نفسه** (`plan_out()`): TQ-CYCLE-BUY
           فيه بدوراتها، والغلاف والمزايا معه. وشكل ثان لشيء واحد يجعل
           شاشة الشراء عند ولي الأمر تطبع بطاقة بلا اسم وبلا مزايا ولا
           تخطئ — وهي علة `/plans` القديمة حرفا. */
        $plans = array();
        try {
            foreach ((array) $this->tq_bill->plans(true) as $p) {
                if ((string) $p['scope'] !== 'grade') continue;
                $plans[] = $this->plan_out($p);
            }
        } catch (Throwable $e) {
            /* استعلام يفشل يترك حالة بناء الاستعلام كما هي (TQ-BUILDER-DIRTY)،
               فيرث كل استعلام تال في الطلب نفسه ضمومه ويرد «ambiguous». */
            $this->db->reset_query();
            $plans = array();
        }

        $card = false;
        try { $card = (bool) $this->tq_tap->ready(); } catch (Throwable $e) {}

        /* **والفاتورة التي تنتظر تعرض قبل ما يشترى**: من عاد إلى هذه
           الشاشة بعد الإنشاء يريد أن يكمل دفعه لا أن يبدأ شراء ثانيا —
           وشاشة تخفيها تجعل لابنه اشتراكين معلقين. */
        $due = array();
        try {
            $rows = $this->db->query(
                'SELECT i.`id`, i.`invoice_no`, i.`total`, i.`status`, i.`user_id`, i.`issued_at`,
                        TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) AS holder
                   FROM `invoices` i
                   JOIN `parent_links` pl ON pl.`student_id` = i.`user_id`
                                         AND pl.`parent_user_id` = ? AND pl.`status` = "active"
              LEFT JOIN `users` u ON u.`id` = i.`user_id`
                  WHERE i.`status` <> "paid"
               ORDER BY i.`id` DESC LIMIT 20', array($pid))->result_array();

            foreach ($rows as $i) {
                $due[] = array(
                    'invoice_id'   => (int) $i['id'],
                    'invoice_no'   => (string) $i['invoice_no'],
                    'student_id'   => (int) $i['user_id'],
                    'student_name' => (string) $i['holder'],
                    'amount'       => tq_api_money((int) $i['total']),
                    'status'       => (string) $i['status'],
                    'issued_at'    => tq_api_date($i['issued_at']),
                );
            }
        } catch (Throwable $e) { $this->db->reset_query(); }

        $this->read(array(
            'children'      => $kids,
            'plans'         => $plans,
            'due_invoices'  => $due,
            'card_ready'    => $card,
            'pay_methods'   => $card ? array('tap', 'manual') : array('manual'),
            'bank'          => $this->bank_out(),
        ), '', array(
            'note' => t('الاشتراك يفتح في حساب ابنك هو، والفاتورة تصدر باسمه.'),
        ), $h);
    }
}
