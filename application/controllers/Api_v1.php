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

    /** يمرر ملفا مع دعم `Range` — ويخرج بعده. نسخة `Taqdar_gate::stream()` نفسها. */
    private function stream_file($path)
    {
        $size = filesize($path);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map  = array('webm' => 'video/webm', 'ogg' => 'video/ogg', 'ogv' => 'video/ogg',
                      'm4v' => 'video/mp4', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4');
        $mime = isset($map[$ext]) ? $map[$ext] : 'video/mp4';

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
        $u = $this->require_student();
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
        $u = $this->require_student();
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

        $u = $this->require_student();
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
        $u = $this->require_student();
        $h = $this->limit('read', self::RL_READ_MAX, self::RL_READ_WINDOW);

        $out = array();
        foreach ($this->stu()->messageable((int) $u['id']) as $p) {
            $is_support = ((int) ($p['role_id'] ?? 0) === 1) && empty($p['is_instructor']);
            $out[] = array(
                'id'         => (int) $p['id'],
                'name'       => $is_support ? t('الدعم الفني')
                              : trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
                'role'       => !empty($p['is_instructor']) ? 'teacher' : ($is_support ? 'support' : 'user'),
                'avatar_url' => tq_api_avatar($p['image'] ?? ''),
            );
        }

        $this->read($out, '', array('note' => t('المراسلة متاحة مع معلميك والدعم فقط، ولا رسائل خاصة بين الطلاب.')), $h);
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
        $u = $this->require_student();
        $this->limit('write', self::RL_WRITE_MAX, self::RL_WRITE_WINDOW);

        $uid = (int) $u['id'];
        $b   = $this->body();

        $errors = tq_api_validate($b, array(
            'receiver' => 'required|int',
            'body'     => 'required|max:5000',
        ));
        if ($errors) $this->fail('راجع البيانات المدخلة.', 'validation_failed', 422, $errors);

        $to = (int) $b['receiver'];
        if (!$this->stu()->may_message($uid, $to)) {
            $this->fail('لا ترسل الرسائل إلا إلى معلمي موادك أو الدعم الفني.',
                        'recipient_not_allowed', 403);
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
        $u = $this->require_student();

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
     * GET /api/v1/student/library — كتب مرحلة الطالب.
     *
     * **والملف يرد رابطه ولا يخفى**: القارئ داخل الصفحة في الويب
     * (`pdf.js`) يمنع النسخ العرضي، والتطبيق له قارئه هو. وحجب الرابط
     * هنا يعني كتابا لا يفتح في التطبيق أصلا — وهو حجب لا حماية:
     * المحتوى نفسه يصل إلى القارئ في الحالين.
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

        $out = array();
        foreach ($lib['books'] as $b) {
            $out[] = array(
                'id'          => (int) $b['id'],
                'title'       => (string) $b['title'],
                'slug'        => (string) $b['slug'],
                'subject'     => (string) $b['subject'],
                'author'      => (string) $b['author'],
                'pages'       => (int) $b['pages'],
                'description' => (string) $b['description'],
                'cover_url'   => $asset($b['cover'], 'uploads/books/'),
                'file_url'    => $asset($b['file'],  'uploads/books/'),
                'web_url'     => base_url('book/' . rawurlencode((string) $b['slug'])),
            );
        }

        $this->read($out, '', array(
            'count'  => count($out),
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

        $this->read(array(
            'bookings' => $bookings,
            'teachers' => $this->tutors_out($m->available_teachers(12, 6, $subject)),
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

        $kind = 'plan'; $title = ''; $ref = 0; $code = null;

        if ((int) ($s['course_id'] ?? 0) > 0) {
            $kind  = 'course';
            $ref   = (int) $s['course_id'];
            $title = (string) $this->db->select('title')->where('id', $ref)
                                       ->get('course')->row('title');
        } elseif ((int) ($s['path_id'] ?? 0) > 0) {
            $kind  = 'path';
            $ref   = (int) $s['path_id'];
            $title = (string) $this->db->select('title')->where('id', $ref)
                                       ->get('paths')->row('title');
        } else {
            $ref  = (int) $s['plan_id'];
            $plan = $this->tq_bill->plan($ref);
            /* TQ-PLAN-DELETE — صف باقة حذفت يترك اشتراكا يشير إلى معرف لا
               يقابله شيء. فيقال «باقة #8» بالرقم لا «شراء»: الرقم يقابل
               به السجل المالي، والكلمة العامة لا تقابل شيئا. */
            $title = $plan ? (string) $plan['name_ar'] : (t('باقة') . ' #' . $ref);
            $code  = $plan ? (string) $plan['code'] : null;
        }

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
}
