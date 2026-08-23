<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * وصف واجهة البرمجة — **المصدر الوحيد**.
 *
 * منه تخرج ثلاثة مخرجات ولا يكتب أحدها بيد:
 *   · صفحة الوثائق   `/api/docs`
 *   · مواصفة OpenAPI `/api/docs/openapi.json`
 *   · مجموعة Postman `/api/docs/collection.json`
 *
 * **ولماذا ملف PHP لا JSON؟**
 * أولا لأن `base_url()` تختلف بين المحلي والإنتاج، ومواصفة تحمل عنوانا
 * ثابتا تجعل مطور Flutter ينسخ رابط الإنتاج وهو يجرب على جهازه. وثانيا
 * لأن JSON لا يحمل تعليقا، وهذا الملف يحتاج أن يقول **لماذا** كما تقول
 * بقية شيفرة تقدر. وثالثا لأن `php -l` يمسك خطأ الفاصلة، و JSON مكسور
 * لا يظهر إلا حين تفتح الصفحة فتجدها بيضاء.
 *
 * **ولماذا لا Scribe؟**
 * Scribe حزمة Laravel تحتاج Composer وحاوية خدمات، وهذا المستودع
 * CodeIgniter 3 بلا `vendor/` وبلا خطوة بناء (`deploy.sh` سحب
 * `git reset --hard` لا أكثر). فأخذ ما تعطيه Scribe — ثلاثة أعمدة،
 * وأمثلة جاهزة، ومجموعة Postman — وبني على مواصفة قياسية بدلها،
 * فالمواصفة تفتح في Swagger وInsomnia وStoplight كذلك.
 *
 * إضافة نقطة = مدخل هنا **و** قاعدة في `routes.php` **و** دالة في
 * `Api_v1.php`. وناقص واحد منها يعني نقطة موثقة لا تعمل أو تعمل ولا يعلم
 * بها أحد.
 */

$TQ_API_BASE = rtrim(base_url(), '/');

/* ---------------------------------------------------------------------
 * قطع تتكرر — تعرف مرة وتشار إليها.
 * ------------------------------------------------------------------- */

$money = array(
    'type' => 'object',
    'description' => 'Money is returned three ways so no client has to guess. `amount` is the authoritative value in halalas (integer); never do arithmetic on `decimal`.',
    'properties' => array(
        'amount'    => array('type' => 'integer', 'example' => 39900, 'description' => 'Minor units (halalas). 39900 = 399.00 SAR.'),
        'decimal'   => array('type' => 'string',  'example' => '399.00'),
        'currency'  => array('type' => 'string',  'example' => 'SAR'),
        'formatted' => array('type' => 'string',  'example' => '399.00 ر.س'),
    ),
);

$user = array(
    'type' => 'object',
    'properties' => array(
        'id'         => array('type' => 'integer', 'example' => 287),
        'first_name' => array('type' => 'string',  'example' => 'طالب'),
        'last_name'  => array('type' => 'string',  'example' => 'الاختبار'),
        'name'       => array('type' => 'string',  'example' => 'طالب الاختبار'),
        'email'      => array('type' => 'string',  'format' => 'email', 'example' => 'student.test@taqdaredu.com'),
        'phone'      => array('type' => 'string',  'example' => '0512345678'),
        'avatar_url' => array('type' => 'string',  'format' => 'uri'),
        'role'       => array('type' => 'string',  'enum' => array('student', 'teacher', 'parent'), 'example' => 'student'),
        'status'     => array('type' => 'string',  'enum' => array('active', 'suspended'), 'example' => 'active'),
        'grade_id'   => array('type' => array('integer', 'null'), 'example' => 20),
        'created_at' => array('type' => array('string', 'null'), 'format' => 'date-time'),
    ),
);

/** غلاف الخطأ — واحد في كل نقطة، حتى 404 و500. */
$error = array(
    'type' => 'object',
    'required' => array('message', 'code'),
    'properties' => array(
        'message' => array('type' => 'string', 'description' => 'Arabic, ready to show the user as-is.'),
        'code'    => array('type' => 'string', 'description' => 'Stable machine key. Branch on this, never on `message`.'),
        'errors'  => array(
            'type' => 'object',
            'description' => 'Present on 422 only. Maps a field name to its list of messages.',
            'additionalProperties' => array('type' => 'array', 'items' => array('type' => 'string')),
        ),
    ),
);

/**
 * رد خطأ.
 *
 * المخطط `$ref` لا نسخة: الشكل واحد في كل نقطة، وتكراره حرفيا مئة مرة
 * كان يضخم المواصفة إلى مئتين وثمانين كيلوبايت — تحمل في كل فتح للصفحة.
 * والوصف والمثال يبقيان موضعيين لأنهما يختلفان من نقطة إلى نقطة، وهما
 * ما يقرؤه القارئ فعلا.
 */
$err_ref = function ($desc, $example) {
    return array(
        'description' => $desc,
        'content' => array('application/json' => array(
            'schema'  => array('$ref' => '#/components/schemas/Error'),
            'example' => $example,
        )),
    );
};

$r_401 = $err_ref('Missing, malformed, expired or revoked access token. `token_expired` means refresh silently; anything else means send the user back to the login screen.',
    array('message' => 'انتهت صلاحية رمز الدخول. جدده وأعد المحاولة.', 'code' => 'token_expired'));

$r_403 = $err_ref('Authenticated, but not allowed — most often a non-student token on a `/student/*` endpoint.',
    array('message' => 'هذه النقطة لبوابة الطالب. واجهة «teacher» لم تصدر بعد.', 'code' => 'wrong_role'));

$r_404 = $err_ref('No such resource, or it does not belong to the caller. The two are deliberately not distinguished.',
    array('message' => 'لا فاتورة بهذا الرقم في حسابك.', 'code' => 'not_found'));

$r_422 = $err_ref('Validation failed. `errors` names the offending fields.',
    array(
        'message' => 'راجع البيانات المدخلة.',
        'code'    => 'validation_failed',
        'errors'  => array('email' => array('صيغة البريد غير صحيحة — مثال: name@example.com')),
    ));

$r_429 = $err_ref('Rate limit exhausted. Honour `Retry-After` (seconds); do not retry in a tight loop.',
    array('message' => 'تجاوزت عدد الطلبات المسموح به. أعد المحاولة بعد 42 ثانية.', 'code' => 'rate_limited'));

/** المصادقة على كل نقطة محمية. */
$auth = array(array('bearerAuth' => array()));

/** معاملا الترقيم. */
$page_params = array(
    array('name' => 'page', 'in' => 'query', 'required' => false,
          'schema' => array('type' => 'integer', 'minimum' => 1, 'default' => 1)),
    array('name' => 'per_page', 'in' => 'query', 'required' => false,
          'schema' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20),
          'description' => 'Capped server-side. Asking for more returns the cap, not an error.'),
);

/* =====================================================================
   المواصفة
   ===================================================================== */

return array(

'openapi' => '3.1.0',

'info' => array(
    'title'   => 'Taqdar Mobile API',
    'version' => '1.0.0',
    'summary' => 'Token-authenticated JSON API for the Taqdar Flutter client.',
    'description' => implode("\n", array(
        'Version 1 of the Taqdar mobile API. It covers **authentication** and three screens of the',
        '**student dashboard**: profile, settings, and subscription. Nothing else is exposed yet —',
        'an endpoint that is not listed here does not exist.',
        '',
        '### Response envelope',
        '',
        'Every response uses one of two shapes, with no exceptions — including `404` and `500`.',
        'Write your Dart models against these two and you will never need a third code path.',
        '',
        '```json',
        '// success',
        '{ "data": { }, "message": "", "meta": { } }',
        '',
        '// failure',
        '{ "message": "…", "code": "validation_failed", "errors": { "email": ["…"] } }',
        '```',
        '',
        '`message` is Arabic and is safe to show the user verbatim. `code` is a stable ASCII key —',
        '**always branch on `code`, never on `message`**, because message wording changes and codes do not.',
        '',
        '### Money',
        '',
        'All amounts are integers in **halalas**, the minor unit of the Saudi riyal (`39900` = `399.00 SAR`).',
        'Each money field also carries `decimal` and `formatted` for display. Do arithmetic on `amount` only —',
        '`decimal` is a string precisely so nobody accumulates float error into someone\'s invoice.',
        '',
        '### Dates',
        '',
        'Every timestamp is ISO-8601 with an explicit offset (`2026-08-22T14:30:00+03:00`), so',
        '`DateTime.parse()` is enough. Platform time is `Asia/Riyadh`. Fields that can be absent are',
        '`null`, never `""` or `0`.',
        '',
        '### Language',
        '',
        'Send `Accept-Language: ar` (default) or `en`. Content authored in Arabic — plan names, lesson',
        'titles, validation messages — stays Arabic regardless, because that is what it is.',
    )),
    'contact' => array(
        'name'  => 'Taqdar Platform',
        'url'   => 'https://taqdaredu.com',
        'email' => 'info@taqdaredu.com',
    ),
),

/* الإنتاج أولا: هو ما ينسخ. والمحلي مذكور لأنه موجود فعلا في SETUP.md،
   وحذفه يجعل كل مطور يخمن المنفذ. */
'servers' => array(
    array('url' => 'https://taqdaredu.com', 'description' => 'Production'),
    array('url' => 'http://localhost:8081', 'description' => 'Local development (see SETUP.md)'),
),

'tags' => array(
    array('name' => 'Authentication', 'description' => 'Obtain, refresh and revoke tokens.'),
    array('name' => 'Profile',        'description' => 'What the student has reached — mastery, streak, certificates.'),
    array('name' => 'Settings',       'description' => 'Account, notifications, preferences, guardian consent, data rights.'),
    array('name' => 'Subscription',   'description' => 'Plan status, invoices, cancellation and card payment.'),
    array('name' => 'Meta',           'description' => 'Service metadata.'),
),

'security' => $auth,

'components' => array(

    'securitySchemes' => array(
        'bearerAuth' => array(
            'type'   => 'http',
            'scheme' => 'bearer',
            'description' => implode("\n", array(
                'Send the access token from `POST /api/v1/auth/login` on every protected request:',
                '',
                '```',
                'Authorization: Bearer tqa_xxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                '```',
                '',
                'Access tokens live **15 minutes**; refresh tokens live **30 days**. Tokens are opaque —',
                'they carry no readable claims, so do not try to decode one to find the user id or expiry.',
                'Use `expires_in` from the login response instead.',
                '',
                'Tokens are stored hashed server-side and can be revoked instantly. Changing the password',
                'revokes every other device; a refresh token presented twice revokes the whole chain.',
            )),
        ),
    ),

    'schemas' => array(
        'Money'    => $money,
        'User'     => $user,
        'Error'    => $error,
        'TokenPair'=> array(
            'type' => 'object',
            'properties' => array(
                'access_token'       => array('type' => 'string', 'example' => 'tqa_9f3c...'),
                'refresh_token'      => array('type' => 'string', 'example' => 'tqr_1a8e...'),
                'token_type'         => array('type' => 'string', 'example' => 'Bearer'),
                'expires_in'         => array('type' => 'integer', 'example' => 900, 'description' => 'Access token lifetime, seconds.'),
                'refresh_expires_in' => array('type' => 'integer', 'example' => 2592000),
            ),
        ),
    ),

    'responses' => array(
        'Unauthorized' => $r_401,
        'Forbidden'    => $r_403,
        'NotFound'     => $r_404,
        'Validation'   => $r_422,
        'RateLimited'  => $r_429,
    ),
),

/* =====================================================================
   النقاط
   ===================================================================== */

'paths' => array(

/* ---- Meta -------------------------------------------------------- */

'/api/v1' => array('get' => array(
    'tags' => array('Meta'),
    'summary' => 'Service index',
    'description' => 'Liveness and discovery. The only unauthenticated read in the API — handy as a connectivity probe on app start.',
    'security' => array(),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'name' => 'Taqdar Mobile API', 'version' => 'v1', 'status' => 'ok',
                'server_time' => '2026-08-22T14:30:00+03:00',
                'docs_url'    => $TQ_API_BASE . '/api/docs',
                'openapi_url' => $TQ_API_BASE . '/api/docs/openapi.json',
                'postman_url' => $TQ_API_BASE . '/api/docs/collection.json',
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '429' => $r_429,
    ),
)),

/* ---- Authentication ---------------------------------------------- */

'/api/v1/auth/login' => array('post' => array(
    'tags' => array('Authentication'),
    'summary' => 'Log in',
    'description' => implode("\n", array(
        'Exchange e-mail and password for a token pair.',
        '',
        'Send `device_name` and `platform` — they are optional, but they are what makes',
        '`GET /auth/sessions` readable when a user asks "which devices am I signed in on?".',
        '',
        '**Store the refresh token in secure storage** (`flutter_secure_storage`), never in',
        '`SharedPreferences`. The access token can live in memory.',
        '',
        '#### Failures that are not "wrong password"',
        '',
        'A `403` here means the credentials were correct but the account cannot be used yet.',
        'Branch on `code` and route the user accordingly:',
        '',
        '| `code` | What happened | What the app should do |',
        '|---|---|---|',
        '| `invalid_credentials` | Wrong e-mail or password (401) | Show the message on the form |',
        '| `email_not_verified` | Signed up, never confirmed | Send them to the website to finish verification |',
        '| `teacher_pending_approval` | Teacher awaiting admin review | Show the message; there is nothing to retry |',
        '| `admin_not_allowed` | Admin account | Admins use the web panel only |',
        '| `too_many_attempts` | Throttled (429) | Honour `Retry-After` |',
        '',
        'Sign-up and OTP verification are **not** part of v1 — they stay on the website.',
    )),
    'security' => array(),
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array(
            'type' => 'object',
            'required' => array('email', 'password'),
            'properties' => array(
                'email'       => array('type' => 'string', 'format' => 'email', 'maxLength' => 190, 'example' => 'student.test@taqdaredu.com'),
                'password'    => array('type' => 'string', 'format' => 'password', 'example' => 'secret123'),
                'device_name' => array('type' => 'string', 'maxLength' => 120, 'example' => 'Galaxy S23'),
                'device_id'   => array('type' => 'string', 'maxLength' => 120, 'example' => 'a41f9c2e-77b0'),
                'platform'    => array('type' => 'string', 'maxLength' => 32, 'example' => 'android'),
                'app_version' => array('type' => 'string', 'maxLength' => 32, 'example' => '1.0.0'),
            ),
        ),
        'example' => array(
            'email' => 'student.test@taqdaredu.com', 'password' => 'secret123',
            'device_name' => 'Galaxy S23', 'platform' => 'android', 'app_version' => '1.0.0',
        ),
    ))),
    'responses' => array(
        '200' => array('description' => 'Signed in.', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'token' => array(
                    'access_token' => 'tqa_9f3cAb7dQ1sK...', 'refresh_token' => 'tqr_1a8eZz0pLm4X...',
                    'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_expires_in' => 2592000,
                ),
                'user' => array(
                    'id' => 287, 'first_name' => 'طالب', 'last_name' => 'الاختبار',
                    'name' => 'طالب الاختبار', 'email' => 'student.test@taqdaredu.com',
                    'phone' => '', 'avatar_url' => $TQ_API_BASE . '/uploads/user_image/ab12.jpg',
                    'role' => 'student', 'status' => 'active', 'grade_id' => 20,
                    'created_at' => '2026-08-05T01:18:00+03:00',
                ),
            ),
            'message' => 'أهلا بك، طالب الاختبار.', 'meta' => new stdClass(),
        )))),
        '401' => $err_ref('Wrong e-mail or password. The same message is returned for an unknown e-mail, so the API cannot be used to discover who has an account.',
            array('message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.', 'code' => 'invalid_credentials')),
        '403' => $err_ref('Credentials were right; the account is not usable yet. See the table above.',
            array('message' => 'بريدك لم يؤكد بعد. أكمل التحقق من الموقع ثم عد إلى التطبيق.', 'code' => 'email_not_verified')),
        '422' => $r_422,
        '429' => $r_429,
    ),
)),

'/api/v1/auth/refresh' => array('post' => array(
    'tags' => array('Authentication'),
    'summary' => 'Refresh the token pair',
    'description' => implode("\n", array(
        'Trade a refresh token for a fresh pair. **The old pair is invalidated immediately** —',
        'both tokens rotate, so persist the new refresh token before you use the new access token.',
        '',
        'Wire this into a Dio interceptor: on `401` with `code: "token_expired"`, refresh once,',
        'replay the original request, and only sign the user out if the refresh itself fails.',
        '',
        '#### Reuse detection',
        '',
        'Presenting a refresh token that was already spent returns `token_reused` and **revokes the',
        'entire chain** for that device. That is deliberate (OAuth 2.0 BCP): a token used twice means',
        'either a copy leaked or the client raced itself, and losing one session beats leaving a',
        'stolen one open. Practically this means: never fire two refreshes concurrently. Guard the',
        'call with a mutex/`Completer` so parallel 401s wait on one refresh instead of racing.',
    )),
    'security' => array(),
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array(
            'type' => 'object', 'required' => array('refresh_token'),
            'properties' => array('refresh_token' => array('type' => 'string', 'example' => 'tqr_1a8eZz0pLm4X...')),
        ),
        'example' => array('refresh_token' => 'tqr_1a8eZz0pLm4X...'),
    ))),
    'responses' => array(
        '200' => array('description' => 'New pair issued.', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'token' => array('access_token' => 'tqa_new...', 'refresh_token' => 'tqr_new...',
                                 'token_type' => 'Bearer', 'expires_in' => 900, 'refresh_expires_in' => 2592000),
                'user'  => array('id' => 287, 'name' => 'طالب الاختبار', 'role' => 'student'),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $err_ref('Refresh token invalid, expired, or replayed.',
            array('message' => 'استعمل رمز التجديد مرتين، فأبطلت الجلسة كلها احتياطا. سجل دخولك من جديد.', 'code' => 'token_reused')),
        '422' => $r_422,
        '429' => $r_429,
    ),
)),

'/api/v1/auth/logout' => array('post' => array(
    'tags' => array('Authentication'),
    'summary' => 'Log out this device',
    'description' => 'Revokes the current pair only. Other devices stay signed in. Idempotent enough to call on a best-effort basis while clearing local storage.',
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'Signed out.', 'content' => array('application/json' => array('example' => array(
            'data' => null, 'message' => 'سجل خروجك من هذا الجهاز.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401,
    ),
)),

'/api/v1/auth/logout-all' => array('post' => array(
    'tags' => array('Authentication'),
    'summary' => 'Log out every device',
    'description' => 'Revokes every live token for this account, including the one making the call. Offer this on a "lost my phone" action.',
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'All sessions revoked.', 'content' => array('application/json' => array('example' => array(
            'data' => null, 'message' => 'سجل خروجك من كل الأجهزة.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401,
    ),
)),

'/api/v1/auth/me' => array('get' => array(
    'tags' => array('Authentication'),
    'summary' => 'Current user',
    'description' => 'The account behind the token. Cheapest way to validate a stored session on app start — one query, and it tells you the role before you route to a dashboard.',
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'id' => 287, 'first_name' => 'طالب', 'last_name' => 'الاختبار', 'name' => 'طالب الاختبار',
                'email' => 'student.test@taqdaredu.com', 'phone' => '', 'avatar_url' => $TQ_API_BASE . '/uploads/user_image/ab12.jpg',
                'role' => 'student', 'status' => 'active', 'grade_id' => 20,
                'created_at' => '2026-08-05T01:18:00+03:00', 'email_verified_at' => null,
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401,
    ),
)),

'/api/v1/auth/sessions' => array('get' => array(
    'tags' => array('Authentication'),
    'summary' => 'Signed-in devices',
    'description' => 'One row per device, newest use first. `current` marks the caller. Pair this with `logout-all` so the button is not pressed blind.',
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'device_name' => 'Galaxy S23', 'platform' => 'android', 'app_version' => '1.0.0',
                'ip' => '5.42.10.9', 'created_at' => '2026-08-20T09:11:00+03:00',
                'last_used_at' => '2026-08-22T14:02:00+03:00', 'current' => true,
            )),
            'message' => '', 'meta' => array('count' => 1),
        )))),
        '401' => $r_401,
    ),
)),

/* ---- Profile ------------------------------------------------------ */

'/api/v1/student/profile' => array('get' => array(
    'tags' => array('Profile'),
    'summary' => 'Student profile',
    'description' => implode("\n", array(
        'Everything the profile screen shows above the fold: identity, grade, mastery summary,',
        'streak, today\'s goal, exam mode, the five weakest objectives, and recent certificates.',
        '',
        'The 90-day activity grid and the full mastery map are **not** here — they are large and',
        'rarely both needed, so they have their own endpoints. This response is sized to render a',
        'screen on a slow connection.',
        '',
        'By design there is **no ranking and no comparison to other students**, here or anywhere',
        'else in the API. That is a product rule, not an oversight: a source of household pressure',
        'does not become gentler because it arrived as JSON.',
        '',
        '`average_mastery` and every `level` are on a **0–100 scale**, not 0–1. Do not multiply.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'user' => array(
                    'id' => 287, 'name' => 'طالب الاختبار', 'email' => 'student.test@taqdaredu.com',
                    'avatar_url' => $TQ_API_BASE . '/uploads/user_image/ab12.jpg', 'role' => 'student',
                    'grade_id' => 20, 'grade_name' => 'الصف الرابع الابتدائي',
                    'created_at' => '2026-08-05T01:18:00+03:00',
                ),
                'stats' => array(
                    'certificates' => 3, 'average_mastery' => 4.0, 'objectives' => 31,
                    'mastered' => 0, 'active_days_90' => 0,
                ),
                'streak' => array('days' => 0, 'best' => 4, 'today' => false),
                'goal_today' => array(
                    'unit' => 'minutes', 'label' => 'دقيقة', 'plural' => 'دقائق',
                    'target' => 30, 'done' => 0, 'percent' => 0, 'met' => false, 'gamify' => true,
                ),
                'exam_mode' => array('active' => false, 'from' => null, 'to' => null, 'days_left' => 0),
                'weakest' => array(array(
                    'objective_id' => 412, 'text' => 'أن يختار الطالب العملية المناسبة لمسألة لفظية',
                    'level' => 0.0, 'forget_rate' => 0.3, 'last_seen_at' => null,
                    'lesson' => array('id' => 88, 'title' => 'المسائل اللفظية'),
                    'course' => array('id' => 12, 'title' => 'الرياضيات — الصف الرابع'),
                )),
                'certificates' => array(array(
                    'id' => 501, 'title' => 'الوحدة الأولى: الأنماط والجمع والطرح',
                    'path' => 'الرياضيات — الصف الرابع', 'score' => 92.0,
                    'issued_at' => '2026-08-03T10:22:00+03:00',
                    'download_url' => $TQ_API_BASE . '/student/certificate/501',
                )),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/profile/activity' => array('get' => array(
    'tags' => array('Profile'),
    'summary' => 'Activity grid',
    'description' => implode("\n", array(
        'One entry per calendar day, oldest first, with **no gaps** — quiet days come back with',
        '`active: false` and zeroes. Render the array straight into the heat-map; you never have to',
        'fill missing dates yourself.',
        '',
        'The date key is `day` (`YYYY-MM-DD`), matching the column it comes from.',
    )),
    'security' => $auth,
    'parameters' => array(array(
        'name' => 'days', 'in' => 'query', 'required' => false,
        'schema' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 366, 'default' => 91),
        'description' => 'How many days back. Out-of-range values fall back to 91 rather than erroring.',
    )),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                array('day' => '2026-08-21', 'active' => false, 'lessons' => 0, 'reviews' => 0, 'seconds' => 0),
                array('day' => '2026-08-22', 'active' => true,  'lessons' => 2, 'reviews' => 8, 'seconds' => 1740),
            ),
            'message' => '', 'meta' => array('days' => 91, 'count' => 91),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/profile/mastery' => array('get' => array(
    'tags' => array('Profile'),
    'summary' => 'Full mastery map',
    'description' => implode("\n", array(
        'Every tracked objective, **weakest first** — the order is the point, so paginate rather',
        'than re-sorting client-side.',
        '',
        '`level` is 0–100. `forget_rate` drives spaced repetition scheduling; treat it as opaque',
        'unless you are building a review UI.',
    )),
    'security' => $auth,
    'parameters' => array(
        array('name' => 'page', 'in' => 'query', 'schema' => array('type' => 'integer', 'minimum' => 1, 'default' => 1)),
        array('name' => 'per_page', 'in' => 'query', 'schema' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50)),
    ),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'objective_id' => 412, 'text' => 'أن يطرح الطالب عددين مع الاستلاف المتكرر',
                'level' => 0.0, 'forget_rate' => 0.3, 'last_seen_at' => '2026-08-19T18:04:00+03:00',
                'lesson' => array('id' => 88, 'title' => 'الطرح مع الاستلاف'),
                'course' => array('id' => 12, 'title' => 'الرياضيات — الصف الرابع'),
            )),
            'message' => '',
            'meta' => array(
                'pagination' => array('page' => 1, 'per_page' => 50, 'total' => 31, 'total_pages' => 1, 'has_more' => false),
                'average_level' => 4.0,
            ),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

/* ---- Settings ----------------------------------------------------- */

'/api/v1/student/settings' => array('get' => array(
    'tags' => array('Settings'),
    'summary' => 'All settings',
    'description' => implode("\n", array(
        'Every section in one response: profile, notifications, preferences, billing, downloads and',
        'guardian links. The settings screen opens once and switches tabs without touching the',
        'network again — six endpoints for six tabs would be six round-trips on mobile data.',
        '',
        '**Render the reference lists, do not hard-code them.** `notifications.types`,',
        '`notifications.channels` and `preferences.languages` ship with the payload precisely so that',
        'adding a channel server-side shows up in the app without a release.',
        '',
        'Two fields state a deliberate absence rather than hiding it: `billing.saves_card` is always',
        '`false` (the platform stores no cards — each invoice is paid when issued) and',
        '`downloads.available` is `false` (offline download does not exist yet). Show them as facts,',
        'not as toggles.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'profile' => array(
                    'first_name' => 'طالب', 'last_name' => 'الاختبار',
                    'email' => 'student.test@taqdaredu.com', 'phone' => '',
                    'avatar_url' => $TQ_API_BASE . '/uploads/user_image/ab12.jpg',
                    'avatar_max_bytes' => 2097152,
                ),
                'notifications' => array(
                    'types' => array(
                        array('key' => 'review_due', 'label' => 'تذكير المراجعة', 'hint' => 'حين يحين موعد مراجعة درس سبق'),
                        array('key' => 'quiz_result', 'label' => 'نتيجة اختبار', 'hint' => 'حين ترصد نتيجة اختبار أديته'),
                    ),
                    'channels' => array(
                        array('key' => 'inapp', 'label' => 'داخل المنصة'),
                        array('key' => 'email', 'label' => 'بريد إلكتروني'),
                    ),
                    'matrix' => array(
                        'review_due'  => array('inapp' => 1, 'email' => 0),
                        'quiz_result' => array('inapp' => 1, 'email' => 1),
                    ),
                    'quiet_hours' => array('enabled' => false, 'from' => 22, 'to' => 7),
                ),
                'preferences' => array(
                    'language' => 'arabic',
                    'languages' => array(array('key' => 'arabic', 'label' => 'العربية'), array('key' => 'english', 'label' => 'English')),
                    'theme' => 'light', 'theme_locked' => true,
                ),
                'billing' => array(
                    'saves_card' => false, 'last_method' => 'manual',
                    'last_method_label' => 'تحويل بنكي يدوي', 'last_used_at' => '2026-08-06T13:39:52+03:00',
                ),
                'downloads' => array('available' => false, 'note' => 'التحميل للعمل دون اتصال غير متاح بعد. المواد تشاهد داخل المنصة بصلاحية زمنية.'),
                'parent_links' => array(
                    'consent_text' => 'أوافق على أن يطلع ولي أمري على تقدمي ونتائجي، ولي أن أسحب هذه الموافقة متى شئت.',
                    'pending' => array(),
                    'active'  => array(array(
                        'id' => 14, 'parent' => array('name' => 'ولي الاختبار', 'email' => 'parent.test@taqdaredu.com'),
                        'status' => 'active', 'consent_at' => '2026-06-10T23:32:33+03:00',
                    )),
                ),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/settings/profile' => array('put' => array(
    'tags' => array('Settings'),
    'summary' => 'Update profile',
    'description' => implode("\n", array(
        'Name, e-mail and phone. **Send all four fields** — this replaces the record rather than',
        'patching it, so an omitted `phone` clears the phone.',
        '',
        'The e-mail is the login identifier: changing it changes what the user signs in with, and',
        'the API says so in the success message. A duplicate e-mail comes back as a `422` on the',
        '`profile` key.',
        '',
        'The avatar is **not** editable here — it needs `multipart`, so it has its own endpoint.',
        '`POST` is accepted as an alias for clients that cannot send `PUT`.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array(
            'type' => 'object', 'required' => array('first_name', 'email'),
            'properties' => array(
                'first_name' => array('type' => 'string', 'maxLength' => 120, 'example' => 'طالب'),
                'last_name'  => array('type' => 'string', 'maxLength' => 120, 'example' => 'الاختبار'),
                'email'      => array('type' => 'string', 'format' => 'email', 'maxLength' => 50, 'example' => 'student.test@taqdaredu.com'),
                'phone'      => array('type' => 'string', 'maxLength' => 25, 'example' => '0512345678',
                                      'description' => 'Digits, `+`, `-`, parentheses and spaces only.'),
            ),
        ),
        'example' => array('first_name' => 'طالب', 'last_name' => 'الاختبار',
                           'email' => 'student.test@taqdaredu.com', 'phone' => '0512345678'),
    ))),
    'responses' => array(
        '200' => array('description' => 'Saved. Returns the refreshed user.', 'content' => array('application/json' => array('example' => array(
            'data' => array('id' => 287, 'name' => 'طالب الاختبار', 'email' => 'student.test@taqdaredu.com',
                            'phone' => '0512345678', 'role' => 'student', 'status' => 'active'),
            'message' => 'حفظت بيانات ملفك.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403,
        '422' => $err_ref('Validation failed, or the e-mail belongs to another account.',
            array('message' => 'هذا البريد مسجل لحساب آخر — اختر بريدا غيره.', 'code' => 'validation_failed',
                  'errors' => array('profile' => array('هذا البريد مسجل لحساب آخر — اختر بريدا غيره.')))),
        '429' => $r_429,
    ),
)),

'/api/v1/student/settings/avatar' => array('post' => array(
    'tags' => array('Settings'),
    'summary' => 'Upload avatar',
    'description' => implode("\n", array(
        '`multipart/form-data` with a single file field named `user_image`.',
        '',
        '**`POST` only, and that is not an oversight**: PHP does not parse `multipart` bodies on',
        '`PUT`, so a `PUT` route would receive an empty file list and tell the user to pick an image',
        'they had already picked.',
        '',
        'JPG, PNG or WebP, up to 2 MB (`profile.avatar_max_bytes` in the settings payload). The file',
        'is validated as a real image and **re-encoded to JPEG** server-side, so a payload hiding',
        'code behind an image header does not survive. Replacing an avatar deletes the previous file.',
        '',
        'The response carries the new `avatar_url`; use it to refresh your cache rather than',
        're-fetching the whole settings payload.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('multipart/form-data' => array(
        'schema' => array(
            'type' => 'object', 'required' => array('user_image'),
            'properties' => array('user_image' => array('type' => 'string', 'format' => 'binary')),
        ),
    ))),
    'responses' => array(
        '200' => array('description' => 'Uploaded.', 'content' => array('application/json' => array('example' => array(
            'data' => array('avatar_url' => $TQ_API_BASE . '/uploads/user_image/9c1f.jpg'),
            'message' => 'حدثت صورتك.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403,
        '422' => $err_ref('No file, wrong type, or larger than 2 MB.',
            array('message' => 'الصورة أكبر من 2 ميجابايت — اختر صورة أصغر.', 'code' => 'upload_failed',
                  'errors' => array('user_image' => array('الصورة أكبر من 2 ميجابايت — اختر صورة أصغر.')))),
        '429' => $r_429,
    ),
)),

'/api/v1/student/settings/password' => array('put' => array(
    'tags' => array('Settings'),
    'summary' => 'Change password',
    'description' => implode("\n", array(
        'Requires the current password. Minimum eight characters, and the new one must differ from',
        'the old.',
        '',
        '**This revokes every other device.** The calling device keeps its tokens; everything else is',
        'signed out immediately. Someone who changes their password because it leaked has not',
        'achieved anything if the other session stays open. Tell the user this before they submit —',
        'the success message says it too.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array(
            'type' => 'object', 'required' => array('current_password', 'new_password', 'confirm_password'),
            'properties' => array(
                'current_password' => array('type' => 'string', 'format' => 'password'),
                'new_password'     => array('type' => 'string', 'format' => 'password', 'minLength' => 8),
                'confirm_password' => array('type' => 'string', 'format' => 'password'),
            ),
        ),
        'example' => array('current_password' => 'secret123', 'new_password' => 'a-longer-secret', 'confirm_password' => 'a-longer-secret'),
    ))),
    'responses' => array(
        '200' => array('description' => 'Changed.', 'content' => array('application/json' => array('example' => array(
            'data' => null, 'message' => 'غيرت كلمة مرورك، وأخرجت بقية الأجهزة.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403,
        '422' => $err_ref('Current password wrong, too short, or confirmation mismatch.',
            array('message' => 'كلمة المرور الحالية غير صحيحة.', 'code' => 'validation_failed',
                  'errors' => array('current_password' => array('كلمة المرور الحالية غير صحيحة.')))),
        '429' => $r_429,
    ),
)),

'/api/v1/student/settings/notifications' => array('put' => array(
    'tags' => array('Settings'),
    'summary' => 'Update notification preferences',
    'description' => implode("\n", array(
        'Send the **whole matrix**, not a delta. Anything you omit is stored as off — the semantics of',
        'the web form, where an unchecked box simply is not submitted. Read the current matrix from',
        '`GET /student/settings`, flip what the user flipped, send it all back.',
        '',
        'Keys come from `notifications.types` and `notifications.channels`; unknown keys are ignored.',
        'Only two channels exist — `inapp` and `email`. There is no push channel because the platform',
        'has no push infrastructure, and a switch for a channel that does not exist is a promise, not',
        'a setting.',
        '',
        '`quiet_hours.from`/`to` are hours 0–23 and may wrap past midnight (22 → 7 is one window, not two).',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array(
            'type' => 'object',
            'properties' => array(
                'notify' => array(
                    'type' => 'object',
                    'description' => 'type → channel → 0|1',
                    'additionalProperties' => array('type' => 'object', 'additionalProperties' => array('type' => 'integer', 'enum' => array(0, 1))),
                ),
                'quiet_hours' => array('type' => 'object', 'properties' => array(
                    'enabled' => array('type' => 'boolean'),
                    'from'    => array('type' => 'integer', 'minimum' => 0, 'maximum' => 23),
                    'to'      => array('type' => 'integer', 'minimum' => 0, 'maximum' => 23),
                )),
            ),
        ),
        'example' => array(
            'notify' => array(
                'review_due'         => array('inapp' => 1, 'email' => 0),
                'station_unlocked'   => array('inapp' => 1, 'email' => 0),
                'quiz_result'        => array('inapp' => 1, 'email' => 1),
                'purchase_confirmed' => array('inapp' => 1, 'email' => 1),
                'session_confirmed'  => array('inapp' => 1, 'email' => 1),
            ),
            'quiet_hours' => array('enabled' => true, 'from' => 22, 'to' => 7),
        ),
    ))),
    'responses' => array(
        '200' => array('description' => 'Saved. Returns the stored matrix so you can trust what landed.', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'matrix' => array('review_due' => array('inapp' => 1, 'email' => 0), 'quiz_result' => array('inapp' => 1, 'email' => 1)),
                'quiet_hours' => array('enabled' => true, 'from' => 22, 'to' => 7),
            ),
            'message' => 'حفظت تفضيلات تنبيهاتك.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '422' => $r_422, '429' => $r_429,
    ),
)),

'/api/v1/student/settings/preferences' => array('put' => array(
    'tags' => array('Settings'),
    'summary' => 'Update preferences',
    'description' => implode("\n", array(
        'Interface language. Pick a `key` from `preferences.languages` — the list is derived from the',
        'translation table at runtime, so it is authoritative.',
        '',
        'Text direction follows from the language and is not a separate setting: choosing `english`',
        'flips the app to LTR. Theme is reported as `light` with `theme_locked: true` — the platform',
        'has a single light face and sending a theme has no effect.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('language'),
                          'properties' => array('language' => array('type' => 'string', 'example' => 'arabic'))),
        'example' => array('language' => 'arabic'),
    ))),
    'responses' => array(
        '200' => array('description' => 'Saved.', 'content' => array('application/json' => array('example' => array(
            'data' => array('language' => 'arabic', 'theme' => 'light'),
            'message' => 'حفظت تفضيلاتك.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403,
        '422' => $err_ref('Unknown language key.',
            array('message' => 'لغة غير متاحة.', 'code' => 'validation_failed',
                  'errors' => array('language' => array('لغة غير متاحة.')))),
        '429' => $r_429,
    ),
)),

'/api/v1/student/settings/parent-links' => array('get' => array(
    'tags' => array('Settings'),
    'summary' => 'Guardian links',
    'description' => implode("\n", array(
        'Pending requests and active links, plus `consent_text` — the exact wording the student is',
        'agreeing to.',
        '',
        '**Show `consent_text` verbatim before the approve button.** It is a legal statement and the',
        'server is the only place it is authored; paraphrasing it in the app makes the two disagree.',
        'It also promises the student can withdraw at any time, which is why active links are listed',
        'here and not only pending ones.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'consent_text' => 'أوافق على أن يطلع ولي أمري على تقدمي ونتائجي، ولي أن أسحب هذه الموافقة متى شئت.',
                'pending' => array(array('id' => 21, 'parent' => array('name' => 'ولي الاختبار', 'email' => 'parent.test@taqdaredu.com'), 'status' => 'pending', 'consent_at' => null)),
                'active'  => array(),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/settings/parent-links/{id}' => array('post' => array(
    'tags' => array('Settings'),
    'summary' => 'Respond to a guardian link',
    'description' => implode("\n", array(
        'Approve or reject a pending request, or withdraw consent already given.',
        '',
        'Only the **student** can sign this — a guardian cannot approve their own request, on the web',
        'or here. Ownership is enforced against the link id and the caller together, so guessing an',
        'id changes nothing.',
        '',
        'Returns the refreshed link lists, so the screen can re-render from the response without a',
        'second call.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
        'schema' => array('type' => 'integer'), 'description' => 'Link id from `parent_links`.')),
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('action'),
            'properties' => array('action' => array('type' => 'string', 'enum' => array('approve', 'reject', 'withdraw')))),
        'example' => array('action' => 'approve'),
    ))),
    'responses' => array(
        '200' => array('description' => 'Done.', 'content' => array('application/json' => array('example' => array(
            'data' => array('consent_text' => '…', 'pending' => array(),
                            'active' => array(array('id' => 21, 'parent' => array('name' => 'ولي الاختبار', 'email' => 'parent.test@taqdaredu.com'),
                                                    'status' => 'active', 'consent_at' => '2026-08-22T14:31:00+03:00'))),
            'message' => 'وافقت على الربط، ويستطيع ولي أمرك متابعة تقدمك الآن.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403,
        '422' => $err_ref('Unknown action, or the link is not in a state that allows it.',
            array('message' => 'لا رابط معلق بهذا الرقم في حسابك.', 'code' => 'action_failed')),
        '429' => $r_429,
    ),
)),

'/api/v1/student/settings/export' => array('get' => array(
    'tags' => array('Settings'),
    'summary' => 'Export my data',
    'description' => implode("\n", array(
        'A copy of everything the platform holds on this account: the user record (with credentials',
        'and internal fields stripped), learning history, and payments.',
        '',
        'Limited to **five calls per hour** — it reads six tables whole, and an unmetered endpoint',
        'like that is a denial-of-service tool that ships with the app.',
        '',
        'The response can be large. Save it to a file and share it rather than holding it in memory.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'generated_at' => '2026-08-22T14:30:00+03:00',
                'account' => array('id' => 287, 'first_name' => 'طالب', 'email' => 'student.test@taqdaredu.com'),
                'learning' => array('enrol' => array(), 'attempts' => array(), 'skill_state' => array()),
                'payments' => array(),
            ),
            'message' => 'هذه نسخة من بياناتك.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/account' => array('delete' => array(
    'tags' => array('Settings'),
    'summary' => 'Delete my account',
    'description' => implode("\n", array(
        '**Anonymisation, not erasure.** Identity fields are overwritten with anonymous values and the',
        'account is suspended; financial records stay attached to an anonymous id because tax law',
        'requires invoices to be kept. Say this in the confirmation dialog — a user who expects total',
        'erasure and gets anonymisation was misled by the app, not by the API.',
        '',
        'Requires `{"confirm": "DELETE"}` in the body. Every token is revoked on success, so treat the',
        'response as a sign-out: clear secure storage and return to the login screen.',
        '',
        '**This cannot be undone from the app.** `POST` is accepted as an alias for clients that',
        'cannot send a `DELETE` body.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('confirm'),
            'properties' => array('confirm' => array('type' => 'string', 'enum' => array('DELETE')))),
        'example' => array('confirm' => 'DELETE'),
    ))),
    'responses' => array(
        '200' => array('description' => 'Account anonymised and all tokens revoked.', 'content' => array('application/json' => array('example' => array(
            'data' => null,
            'message' => 'جهل حسابك. تبقى فواتيرك في السجل بمعرف مجهول كما يوجب النظام.',
            'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403,
        '422' => $err_ref('Confirmation missing or wrong.',
            array('message' => 'أرسل confirm بقيمة DELETE لتأكيد الحذف.', 'code' => 'confirmation_required')),
        '429' => $r_429,
    ),
)),

/* ---- Subscription -------------------------------------------------- */

'/api/v1/student/subscription' => array('get' => array(
    'tags' => array('Subscription'),
    'summary' => 'Subscription status',
    'description' => implode("\n", array(
        'Current subscription, the invoice awaiting payment, recent invoices, and how payment can be',
        'made right now.',
        '',
        '#### `status` vs `status_stored`',
        '',
        'Read **`status`**. Expiry is swept by a nightly cron, but the student is reading now — so a',
        'subscription that lapsed this morning is still `active` in the database and correctly reports',
        '`expired` here. `status_stored` is the raw column, exposed only so support can explain a',
        'discrepancy. Never branch on it.',
        '',
        '#### There is always something to show',
        '',
        'If no subscription is active, the most recent one is returned whatever its state — pending',
        'means an invoice is waiting, expired means it lapsed. Both are things the student needs to',
        'be told. `subscription` is `null` only for an account that never subscribed at all.',
        '',
        '#### Paying',
        '',
        '`payment.card_enabled` reflects whether the card gateway is configured. When it is `false`,',
        'hide the pay-by-card button entirely and show `payment.bank_transfer` instead —',
        '`bank_transfer.reference` is the invoice number and **must** be quoted on the transfer, or the',
        'payment arrives with nothing to match it against. `card_is_test` being `true` means payments',
        'succeed without money moving; never ship a build pointed at that.',
    )),
    'security' => $auth,
    'parameters' => array(array(
        'name' => 'include', 'in' => 'query', 'required' => false,
        'schema' => array('type' => 'string', 'enum' => array('contents')),
        'description' => 'Pass `contents` to also resolve what the plan unlocks (grades, subjects, lesson counts). Six extra queries — request it only on a screen that shows it.',
    )),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'subscription' => array(
                    'id' => 59, 'status' => 'active', 'status_stored' => 'active', 'status_label' => 'نشط',
                    'price' => array('amount' => 39900, 'decimal' => '399.00', 'currency' => 'SAR', 'formatted' => '399.00 ر.س'),
                    'started_at' => '2026-07-28T00:00:00+03:00', 'ends_at' => '2026-10-25T00:00:00+03:00',
                    'days_left' => 65, 'auto_renew' => false, 'method' => 'manual',
                    'cancelled_at' => null, 'created_at' => '2026-08-04T13:39:52+03:00',
                    'plan' => array(
                        'id' => 9, 'code' => 'plus-primary', 'name' => 'الباقة المميزة — المرحلة الابتدائية',
                        'note' => 'كل مواد المرحلة ومعها المهارات الرقمية',
                        'price' => array('amount' => 59900, 'decimal' => '599.00', 'currency' => 'SAR', 'formatted' => '599.00 ر.س'),
                        'period' => 'annual', 'duration_days' => 365, 'scope' => 'grade', 'stage' => 'primary',
                        'is_trial' => false, 'features' => array('كل المواد لا الأساسية وحدها', 'المهارات الرقمية'),
                        'web_url' => $TQ_API_BASE . '/plan/plus-primary',
                    ),
                ),
                'due_invoice' => array(
                    'id' => 58, 'invoice_no' => 'TQ-2026-00006', 'status' => 'unpaid', 'status_label' => 'غير مدفوعة',
                    'amount' => array('amount' => 59900, 'decimal' => '599.00', 'currency' => 'SAR', 'formatted' => '599.00 ر.س'),
                    'tax' => array('amount' => 0, 'decimal' => '0.00', 'currency' => 'SAR', 'formatted' => '0.00 ر.س'),
                    'total' => array('amount' => 59900, 'decimal' => '599.00', 'currency' => 'SAR', 'formatted' => '599.00 ر.س'),
                    'method' => 'manual', 'issued_at' => '2026-08-06T13:39:52+03:00', 'paid_at' => null, 'payable' => true,
                ),
                'invoices' => array('…'),
                'payment' => array(
                    'card_enabled' => false, 'card_is_test' => false,
                    'bank_transfer' => array(
                        'enabled' => true, 'bank_name' => 'مصرف الراجحي', 'beneficiary' => 'شركة تقدر التعليمية',
                        'iban' => 'SA0000000000000000000000', 'instructions' => 'يفعل الاشتراك بعد التحقق من الحوالة.',
                        'reference' => 'TQ-2026-00006',
                    ),
                ),
                'placement_level' => array('level' => 'intermediate', 'score' => 14, 'total' => 20, 'taken_at' => '2026-08-05T02:10:00+03:00'),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/subscription/cancel' => array('post' => array(
    'tags' => array('Subscription'),
    'summary' => 'Stop auto-renewal',
    'description' => implode("\n", array(
        '**Stops renewal; it does not end the subscription.** Access continues until `ends_at` — what',
        'was paid for is not clawed back by a button press. Word the confirmation dialog that way, or',
        'users will press it expecting a refund.',
        '',
        'Returns `409 no_active_subscription` when there is nothing to cancel.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'Renewal stopped.', 'content' => array('application/json' => array('example' => array(
            'data' => array('id' => 59, 'status' => 'cancelled', 'ends_at' => '2026-10-25T00:00:00+03:00', 'auto_renew' => false),
            'message' => 'أوقف التجديد — ويبقى اشتراكك صالحا حتى تاريخ انتهائه.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403,
        '409' => $err_ref('Nothing active to cancel.',
            array('message' => 'لا اشتراك نشط في حسابك.', 'code' => 'no_active_subscription')),
        '429' => $r_429,
    ),
)),

'/api/v1/student/invoices' => array('get' => array(
    'tags' => array('Subscription'),
    'summary' => 'List invoices',
    'description' => 'Newest first, paginated. Filter with `status` to build an "unpaid" tab without fetching everything.',
    'security' => $auth,
    'parameters' => array_merge($page_params, array(array(
        'name' => 'status', 'in' => 'query', 'required' => false,
        'schema' => array('type' => 'string', 'enum' => array('unpaid', 'paid', 'refunded')),
    ))),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'id' => 58, 'invoice_no' => 'TQ-2026-00006', 'status' => 'unpaid', 'status_label' => 'غير مدفوعة',
                'amount' => array('amount' => 59900, 'decimal' => '599.00', 'currency' => 'SAR', 'formatted' => '599.00 ر.س'),
                'tax' => array('amount' => 0, 'decimal' => '0.00', 'currency' => 'SAR', 'formatted' => '0.00 ر.س'),
                'total' => array('amount' => 59900, 'decimal' => '599.00', 'currency' => 'SAR', 'formatted' => '599.00 ر.س'),
                'method' => 'manual', 'issued_at' => '2026-08-06T13:39:52+03:00', 'paid_at' => null, 'payable' => true,
            )),
            'message' => '',
            'meta' => array('pagination' => array('page' => 1, 'per_page' => 20, 'total' => 4, 'total_pages' => 1, 'has_more' => false)),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/invoices/{id}' => array('get' => array(
    'tags' => array('Subscription'),
    'summary' => 'Invoice detail',
    'description' => implode("\n", array(
        'One invoice with the subscription and plan it belongs to, plus the bank-transfer block',
        'pre-filled with this invoice number as the reference.',
        '',
        'An invoice belonging to someone else returns `404`, identical to one that does not exist.',
        'Distinguishing them would turn invoice numbers into a counter anyone could read.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array('type' => 'integer'))),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'id' => 58, 'invoice_no' => 'TQ-2026-00006', 'status' => 'unpaid', 'status_label' => 'غير مدفوعة',
                'total' => array('amount' => 59900, 'decimal' => '599.00', 'currency' => 'SAR', 'formatted' => '599.00 ر.س'),
                'issued_at' => '2026-08-06T13:39:52+03:00', 'paid_at' => null, 'payable' => true,
                'subscription' => array('id' => 59, 'status' => 'pending', 'plan' => array('code' => 'plus-primary', 'name' => 'الباقة المميزة — المرحلة الابتدائية')),
                'bank_transfer' => array('enabled' => true, 'iban' => 'SA0000000000000000000000', 'reference' => 'TQ-2026-00006'),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '404' => $r_404, '429' => $r_429,
    ),
)),

'/api/v1/student/invoices/{id}/pay' => array('post' => array(
    'tags' => array('Subscription'),
    'summary' => 'Start a card payment',
    'description' => implode("\n", array(
        'Creates a Tap charge for an unpaid invoice and returns a `payment_url`.',
        '',
        '**The API never touches card details.** Open `payment_url` in an in-app browser',
        '(`flutter_inappwebview` or Custom Tabs); the card is entered on the gateway\'s page.',
        '',
        '#### After the user comes back',
        '',
        'Call `GET /student/subscription` **once**. Do not poll. Three independent paths already close',
        'the loop server-side — the browser redirect, a gateway webhook for users who close the window,',
        'and a reconciliation job every fifteen minutes for when no webhook arrives — and all three',
        'settle through the same idempotent routine. By the time the user is back on your screen the',
        'state is either final or will be shortly; a polling loop only adds load.',
        '',
        '#### The amount is not yours to send',
        '',
        'There is no amount field in this request, deliberately. The server reads it from the invoice,',
        'records the expected figure in halalas, and reconciles it against what the gateway reports —',
        'a mismatch means no activation. A number sent by the client would be ignored.',
        '',
        '`503 card_payment_disabled` means the gateway is not configured: fall back to bank transfer.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array('type' => 'integer'))),
    'responses' => array(
        '200' => array('description' => 'Payment page ready.', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'payment_url' => 'https://checkout.payments.tap.company/…',
                'invoice' => array('id' => 58, 'invoice_no' => 'TQ-2026-00006', 'status' => 'unpaid'),
                'note' => 'افتح الرابط في متصفح داخلي. يفعل الاشتراك تلقائيا بعد نجاح الدفع.',
            ),
            'message' => 'جهزت صفحة الدفع.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '404' => $r_404,
        '409' => $err_ref('Invoice already paid or refunded.',
            array('message' => 'هذه الفاتورة ليست مستحقة السداد.', 'code' => 'invoice_not_payable')),
        '502' => $err_ref('The gateway refused to open a charge. The invoice still stands and can be paid by transfer.',
            array('message' => 'تعذر بدء الدفع. وفاتورتك صدرت، فيمكنك تحويل قيمتها بنكيا.', 'code' => 'payment_start_failed')),
        '503' => $err_ref('Card payments are not configured on this installation.',
            array('message' => 'الدفع بالبطاقة غير مفعل حاليا. حول قيمة الفاتورة بنكيا.', 'code' => 'card_payment_disabled')),
        '429' => $r_429,
    ),
)),

),  // paths
);
