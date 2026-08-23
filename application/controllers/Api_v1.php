<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * واجهة برمجة تقدر — الإصدار الأول.
 *
 * مدخل واحد لتطبيق Flutter: الدخول، وثلاث شاشات من بوابة الطالب (الملف
 * والإعدادات والاشتراك). وما سواها يبقى في الويب حتى يطلب.
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
     * الأدوار الأربعة تدخل كلها من `/auth/login`، ونقاط `/student/*` وحدها
     * موجودة اليوم. والمعلم الذي يناديها يقرأ **أن بوابته لم تصدر بعد**
     * لا شاشة فارغة يظنها عطبا.
     */
    private function require_student()
    {
        $u    = $this->authenticate();
        $role = tq_role((int) $u['id']);

        if ($role !== 'student') {
            $this->fail('هذه النقطة لبوابة الطالب. واجهة «' . $role . '» لم تصدر بعد.',
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
        $u = $this->require_student();
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
        $u = $this->require_student();
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

        $out = array(
            'subscription'  => $sub,
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

    private function plan_out($p)
    {
        $features = array();
        if (!empty($p['features'])) {
            $d = json_decode($p['features'], true);
            if (is_array($d)) $features = $d;
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
            'features' => $features,
            'web_url'  => base_url('plan/' . rawurlencode((string) $p['code'])),
        );
    }

    private function invoice_out($i)
    {
        $labels = array('unpaid' => 'غير مدفوعة', 'paid' => 'مدفوعة', 'refunded' => 'مستردة');
        return array(
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
}
