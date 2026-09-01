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
        'Version 1 of the Taqdar mobile API. It covers **authentication**, the **account** half of the',
        'student dashboard (profile, settings, subscription), and the **learning loop**: the home',
        'screen, courses and curriculum, the lesson player, the lesson quiz, and spaced review.',
        'Nothing else is exposed yet — an endpoint that is not listed here does not exist.',
        '',
        'The five learning groups are ordered by dependency, and reading them in order is the shortest',
        'path to a working client: **Home** decides what the student does next · **Learning** lists the',
        'courses and lessons · **Lesson** plays one and measures it · **Assessment** is the quiz that',
        'unlocks the next one · **Practice** brings back what was learned. It closes: Home points at',
        'Practice, Practice points at a Lesson, the Lesson opens an Assessment, and the Assessment',
        'writes the next review.',
        '',
        '### The rules live on the server',
        '',
        'Mastery locking, coverage-based completion, the spacing schedule, entitlement — all of it is',
        'computed here and is the same code the website runs. Do not re-derive any of it in Dart. A',
        'second copy drifts on the first change, and then the app unlocks a lesson the website locks,',
        'for the same student, in the same minute.',
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
    /* الترتيب هو رحلة التطبيق نفسها لا ترتيب الكتابة: يدخل، فيهبط على
       الرئيسية، فيتعلم، ثم يلتفت إلى حسابه. ومن يقرأ الوثيقة من أولها
       يبني عميلا يعمل قبل أن يبلغ نصفها. */
    array('name' => 'Authentication', 'description' => 'Obtain, refresh and revoke tokens.'),
    array('name' => 'Home',           'description' => 'The landing screen — one call, and the one field that decides what the student does next.'),
    array('name' => 'Learning',       'description' => 'Enrolled courses, their curriculum, and every lesson in them.'),
    array('name' => 'Lesson',         'description' => 'Playing a lesson: source, heartbeats, completion, notes.'),
    array('name' => 'Assessment',     'description' => 'The lesson quiz — the gate that unlocks what comes next.'),
    array('name' => 'Practice',       'description' => 'Spaced repetition and the mistake notebook.'),
    array('name' => 'Profile',        'description' => 'What the student has reached — mastery, streak, certificates.'),
    array('name' => 'Settings',       'description' => 'Account, notifications, preferences, guardian consent, data rights.'),
    array('name' => 'Subscription',   'description' => 'Plan status, invoices, cancellation and card payment.'),
    array('name' => 'Inbox',        'description' => 'Notifications and conversations — what the platform told the student, and what they asked their teacher.'),
    array('name' => 'Progress',     'description' => 'Homework, calendar, reports and certificates — what has been done and what is due.'),
    array('name' => 'Library',      'description' => 'Books, course materials, saved items, and search across what the student owns.'),
    array('name' => 'Onboarding',   'description' => 'The study plan, exam mode, gamification, and the placement test.'),
    array('name' => 'Sessions',     'description' => 'Private sessions: request, pay after the teacher confirms, join, cancel.'),
    array('name' => 'Store',        'description' => 'The three units of sale on one anchor — plan, path, single course — and everything currently in force.'),
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

/* =====================================================================
   الوحدات الخمس — من شاشة الحساب إلى حلقة التعلم

   الإصدار الأول غطى **الحساب**: الدخول والملف والإعدادات والاشتراك.
   وبها يستطيع التطبيق أن يسجل دخول طالب ويعرض بياناته ويأخذ ماله — ولا
   يستطيع أن يعلمه شيئا. فهذه الخمس هي النصف الآخر، وترتيبها ترتيب
   الاعتماد لا ترتيب السهولة:

     ١ · Home        شاشة الفتح، ومنها ينطلق كل ما بعدها
     ٢ · Learning    الكورسات والمنهج والدروس — بلاها لا معرف درس أصلا
     ٣ · Lesson      المشغل والتقدم — يحتاج معرف الدرس من ٢
     ٤ · Assessment  اختبار الدرس — يفتح بعد إتمامه في ٣، وهو بوابة التالي
     ٥ · Practice    المراجعة والأخطاء — تتغذى من إجابات ٤، وتعود إلى ١

   وهي حلقة مغلقة: الرئيسية تشير إلى المراجعة، والمراجعة تشير إلى الدرس،
   والدرس يفتح الاختبار، والاختبار يكتب المراجعة.
   ===================================================================== */

/* ---------------------------------------------------------------------
   ١ · الرئيسية
   ------------------------------------------------------------------- */

'/api/v1/student/home' => array('get' => array(
    'tags' => array('Home'),
    'summary' => 'Home screen',
    'description' => implode("\n", array(
        'One call for the whole landing screen. It exists because the web version of this screen',
        'reads seven separate sources, and seven round trips on a phone connection is not a design —',
        'it is a stutter the user reads as slowness.',
        '',
        '### `next_step` is the important field',
        '',
        'It is the server\'s answer to "what should this student do right now?", and it comes from the',
        'same seven-branch rule the website uses, in this order: needs setup → reviews are due →',
        'exam mode is on → homework is unsubmitted → a lesson was left unfinished → the next unlocked',
        'lesson → browse the catalogue.',
        '',
        'Branch on `kind` (`setup` · `review` · `exam_plan` · `task` · `resume` · `lesson` · `browse`)',
        'and route to your own screen. `meta` carries what that screen needs — `lesson_id`,',
        '`position_sec`, `due`. `web_url` is the website equivalent and is a usable fallback for a',
        '`kind` your build does not handle yet, so a new branch added server-side never dead-ends.',
        '',
        '**Do not reimplement the rule.** A second copy in Dart drifts on the first change, and then',
        'the app says "start a new lesson" while the website says "review today\'s questions" — for the',
        'same student, in the same minute.',
        '',
        '### The rest',
        '',
        '`courses` is the first four cards only; `meta.courses_total` says how many exist and',
        '`GET /student/courses` has them all. `badges` are the sidebar counters, counted from the same',
        'tables the lists read, so a badge never says three while the screen shows two.',
        '',
        'There is no ranking and no comparison to other students, here or anywhere else in this API.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'user' => array('id' => 287, 'name' => 'طالب الاختبار',
                                'avatar_url' => $TQ_API_BASE . '/uploads/user_image/ab12.jpg'),
                'next_step' => array(
                    'kind' => 'review', 'title' => 'راجع 3 أسئلة اليوم',
                    'subtitle' => 'أسئلة أتقنتها من قبل عادت اليوم لتثبت. لا تتجاوز دفعة واحدة.',
                    'cta' => 'ابدأ المراجعة', 'icon' => 'flame',
                    'web_url' => $TQ_API_BASE . '/student/reviews',
                    'meta' => array('due' => 3, 'exam_mode' => false),
                ),
                'streak' => array('days' => 0, 'best' => 4, 'today' => false),
                'goal_today' => array('unit' => 'minutes', 'label' => 'دقيقة', 'plural' => 'دقائق',
                                      'target' => 30, 'done' => 0, 'percent' => 0,
                                      'met' => false, 'gamify' => true),
                'exam_mode' => array('active' => false, 'from' => null, 'to' => null, 'days_left' => 0),
                'resume' => array('course_id' => 12, 'course' => 'الرياضيات — الصف الرابع',
                                  'lesson_id' => 88, 'percent' => 34),
                'courses' => array(array(
                    'id' => 12, 'title' => 'الرياضيات — الصف الرابع', 'subject' => 'الرياضيات',
                    'level' => 'مبتدئ', 'summary' => 'الأنماط والجمع والطرح والكسور',
                    'teacher' => 'أ. سارة', 'path_id' => 4,
                    'thumbnail' => $TQ_API_BASE . '/uploads/thumbnails/12.jpg',
                    'enrolled_at' => '2026-08-06T13:40:10+03:00',
                    'progress' => array('total_lessons' => 24, 'completed' => 9, 'mastered' => 8,
                                        'percent' => 34, 'next_lesson_id' => 88),
                    'status' => 'progress',
                )),
                'deadlines' => array(array(
                    'assessment_id' => 71, 'kind' => 'homework', 'title' => 'الطرح مع الاستلاف',
                    'lesson_id' => 88,
                    'course' => array('id' => 12, 'title' => 'الرياضيات — الصف الرابع'),
                    'due_at' => '2026-08-29T23:59:00+03:00',
                )),
                'badges' => array('reviews' => 3, 'tasks' => 1, 'messages' => 0, 'notifications' => 1),
            ),
            'message' => '', 'meta' => array('courses_total' => 3),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

/* ---------------------------------------------------------------------
   ٢ · التعلم
   ------------------------------------------------------------------- */

'/api/v1/student/courses' => array('get' => array(
    'tags' => array('Learning'),
    'summary' => 'Enrolled courses',
    'description' => implode("\n", array(
        'Every course the student is enrolled in, newest enrolment first, with real progress.',
        '',
        '### Which progress number this is',
        '',
        '`percent` is computed from `lesson_progress` — the same table the **mastery lock** reads —',
        'and not from the legacy `watch_histories.course_progress` column the old web screen uses.',
        'That is deliberate: when the number a student reads differs from the number the lock uses,',
        'you get a card saying 100% sitting in front of a locked lesson, and nothing on screen',
        'explains it. `mastered` counts lessons whose review was passed; `completed` counts lessons',
        'watched to the end. `percent` follows `mastered`, because that is what unlocks the next one.',
        '',
        '`next_lesson_id` is the first lesson not yet mastered — the correct target for a "continue"',
        'button, and it is `null` only when the course is finished.',
        '',
        'Filters are query parameters and combine. `counts` always describes the **unfiltered** set,',
        'so a filter chip can show its own size without a second request.',
    )),
    'security' => $auth,
    'parameters' => array_merge(array(
        array('name' => 'state', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'string', 'enum' => array('progress', 'done', 'idle')),
              'description' => 'Started · finished · not opened yet. An unknown value is ignored rather than rejected.'),
        array('name' => 'q', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'string'),
              'description' => 'Matches course title or subject name.'),
    ), $page_params),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'id' => 12, 'title' => 'الرياضيات — الصف الرابع', 'subject' => 'الرياضيات',
                'level' => 'مبتدئ', 'summary' => 'الأنماط والجمع والطرح والكسور',
                'teacher' => 'أ. سارة', 'path_id' => 4,
                'thumbnail' => $TQ_API_BASE . '/uploads/thumbnails/12.jpg',
                'enrolled_at' => '2026-08-06T13:40:10+03:00',
                'progress' => array('total_lessons' => 24, 'completed' => 9, 'mastered' => 8,
                                    'percent' => 34, 'next_lesson_id' => 88),
                'status' => 'progress',
            )),
            'message' => '',
            'meta' => array(
                'pagination' => array('page' => 1, 'per_page' => 20, 'total' => 3,
                                      'total_pages' => 1, 'has_more' => false),
                'counts' => array('all' => 3, 'progress' => 1, 'done' => 0, 'idle' => 2),
                'filters' => array('state' => '', 'q' => ''),
            ),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/courses/{id}' => array('get' => array(
    'tags' => array('Learning'),
    'summary' => 'Course curriculum',
    'description' => implode("\n", array(
        'Units and their lessons, in the exact order the lock walks them, each lesson carrying its own',
        'lock state.',
        '',
        '### Read `unlocked` and `lock_reason`, and show the reason',
        '',
        '`blocking_lesson_id` names the lesson standing in the way. With it your screen can say',
        '"finish *Subtraction with borrowing* first" and offer a tap that goes there; without it the',
        'best you can render is a padlock, and a padlock with no cause reads as a bug.',
        '',
        'This endpoint **shows** the lock, it does not enforce it — `GET /student/lessons/{id}` re-checks',
        'and answers `mastery_locked` regardless of what the client believed. So you are free to render',
        'locked rows; you are not free to skip the check.',
        '',
        '`duration_sec` is the **measured** duration where one is known, not the string a teacher typed.',
        'A lesson whose typed duration is wrong locks the course for everyone who bought it — ninety',
        'per cent of a number that was never real is a bar that never fills — so the platform corrects',
        'it from two independent player measurements and this field carries the corrected value.',
        '',
        'Lessons with no unit, or whose unit was deleted, are grouped under `دروس عامة` (`id: 0`)',
        'rather than dropped: a lesson missing from the curriculum is worse than a unit without a name.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'))),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'course' => array(
                    'id' => 12, 'title' => 'الرياضيات — الصف الرابع', 'level' => 'مبتدئ',
                    'summary' => 'الأنماط والجمع والطرح والكسور', 'about' => '…',
                    'preview' => 'https://www.youtube.com/watch?v=…',
                    'thumbnail' => $TQ_API_BASE . '/uploads/thumbnails/12.jpg',
                    'teacher' => array('id' => 41, 'name' => 'أ. سارة'),
                ),
                'progress' => array('total_lessons' => 24, 'completed' => 9, 'mastered' => 8,
                                    'percent' => 34, 'next_lesson_id' => 88),
                'sections' => array(array(
                    'id' => 31, 'title' => 'الوحدة الأولى: الجمع والطرح',
                    'lessons' => array(
                        array('id' => 87, 'title' => 'الجمع مع الحمل', 'lesson_type' => 'video',
                              'duration_sec' => 169, 'is_free' => true, 'trackable' => true,
                              'has_quiz' => true, 'unlocked' => true, 'lock_reason' => 'ok',
                              'blocking_lesson_id' => null,
                              'completed_at' => '2026-08-20T18:04:00+03:00',
                              'mastered_at' => '2026-08-20T18:11:00+03:00', 'position_sec' => 169),
                        array('id' => 88, 'title' => 'الطرح مع الاستلاف', 'lesson_type' => 'video',
                              'duration_sec' => 212, 'is_free' => false, 'trackable' => true,
                              'has_quiz' => true, 'unlocked' => true, 'lock_reason' => 'ok',
                              'blocking_lesson_id' => null, 'completed_at' => null,
                              'mastered_at' => null, 'position_sec' => 72),
                        array('id' => 89, 'title' => 'المسائل اللفظية', 'lesson_type' => 'video',
                              'duration_sec' => 0, 'is_free' => false, 'trackable' => true,
                              'has_quiz' => false, 'unlocked' => false,
                              'lock_reason' => 'previous_not_mastered', 'blocking_lesson_id' => 88,
                              'completed_at' => null, 'mastered_at' => null, 'position_sec' => 0),
                    ),
                )),
            ),
            'message' => '', 'meta' => array('sections' => 4, 'lessons' => 24),
        )))),
        '401' => $r_401,
        '403' => $err_ref('Enrolled in nothing that grants this course.',
            array('message' => 'هذا المحتوى غير متاح ضمن اشتراكك', 'code' => 'not_entitled')),
        '404' => $r_404, '429' => $r_429,
    ),
)),

'/api/v1/student/lessons' => array('get' => array(
    'tags' => array('Learning'),
    'summary' => 'All lessons, flat',
    'description' => implode("\n", array(
        'The lesson as the unit of the row, not the course — a flat, filterable, paginated list across',
        'every enrolled course.',
        '',
        'It exists because a course-shaped list has no answer to "where is the lesson on fractions?"',
        'except *open the course and scan its curriculum*. Filter by `course_id`, by `state`, or by',
        'free text on the lesson and course title.',
        '',
        'Quizzes (`lesson_type: "quiz"`) are excluded — they are in `GET /student/exams`. Mixing them',
        'in would make "35 of 112 lessons" disagree with the course counters.',
        '',
        '`state` is derived server-side: `done` once `completed_at` is set, `current` while a position',
        'was saved but the lesson is unfinished, `todo` otherwise. Filtering happens in SQL, so the',
        'pagination total counts what you will actually be shown.',
        '',
        'This list is deliberately quiet about locks. It is a directory; `GET /student/lessons/{id}`',
        'is the door, and it is the one that answers `mastery_locked`.',
    )),
    'security' => $auth,
    'parameters' => array_merge(array(
        array('name' => 'course_id', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'integer')),
        array('name' => 'state', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'string', 'enum' => array('done', 'current', 'todo'))),
        array('name' => 'q', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'string'),
              'description' => 'Matches lesson title or course title.'),
    ), $page_params),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'id' => 88, 'title' => 'الطرح مع الاستلاف',
                'unit' => 'الوحدة الأولى: الجمع والطرح', 'lesson_type' => 'video',
                'duration_sec' => 212, 'is_free' => false,
                'course' => array('id' => 12, 'title' => 'الرياضيات — الصف الرابع', 'level' => 'مبتدئ'),
                'thumbnail' => $TQ_API_BASE . '/uploads/thumbnails/12.jpg',
                'position_sec' => 72, 'completed_at' => null, 'mastered_at' => null,
                'state' => 'current',
            )),
            'message' => '',
            'meta' => array(
                'pagination' => array('page' => 1, 'per_page' => 20, 'total' => 112,
                                      'total_pages' => 6, 'has_more' => true),
                'filters' => array('course_id' => null, 'state' => '', 'q' => ''),
            ),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

/* ---------------------------------------------------------------------
   ٣ · الدرس
   ------------------------------------------------------------------- */

'/api/v1/student/lessons/{id}' => array('get' => array(
    'tags' => array('Lesson'),
    'summary' => 'Open a lesson',
    'description' => implode("\n", array(
        'Playback source, objectives, saved position, and the quiz card — or a refusal.',
        '',
        '### Two refusals, and they mean different things',
        '',
        '`403 not_entitled` — the course is not covered by the subscription. Send the user to the plan',
        'page. `403 mastery_locked` — it is covered, but an earlier lesson has not been mastered;',
        '`errors.details.blocking_lesson_id` names it. Neither response carries a playback URL, and',
        '`mastery_locked` does not carry the summary either. The lock is a lock.',
        '',
        '### `trackable` decides what your player screen looks like',
        '',
        '`true` — the source reports its position, so show a progress bar and send heartbeats.',
        '`false` — it does not (Google Drive, a bare iframe), so no bar can be honest; show the',
        '"I finished this lesson" button instead. This flag is the **server\'s** judgement from the',
        'stored `video_type`, not what your player managed this minute; when they disagree, trust this',
        'one, or you will render a button the server refuses.',
        '',
        '### Playback',
        '',
        'YouTube and Vimeo come back as their own URLs (`protection: "unprotected"`). Uploaded files',
        'come back signed (`protection: "signed"`), pointing at `GET /student/media/{token}` with an',
        '`expires_in` in seconds. Re-open the lesson to get a fresh token; do not cache the URL.',
        '',
        '`quiz` is `null` when the lesson has no authored questions. When present, `question_count` is',
        'counted, not read from a setting — promising "five short questions" before an exam of three is',
        'read as a bug, and it is the sort of bug nobody reports.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'))),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'lesson' => array(
                    'id' => 88, 'title' => 'الطرح مع الاستلاف', 'course_id' => 12, 'section_id' => 31,
                    'lesson_type' => 'video', 'duration' => '00:03:32', 'duration_sec' => 212,
                    'summary' => 'كيف نستلف من المنزلة الأعلى', 'is_free' => false, 'trackable' => true,
                ),
                'playback' => array(
                    'video_type' => 'youtube',
                    'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'audio_url' => null, 'attachment' => null, 'resume_at' => 72,
                    'protection' => 'unprotected', 'expires_in' => null,
                ),
                'objectives' => array(
                    array('id' => 412, 'text' => 'أن يستلف الطالب من منزلة العشرات', 'at_second' => 45),
                ),
                'progress' => array('position_sec' => 72, 'watch_seconds' => 68, 'covered_sec' => 70,
                                    'percent' => 33, 'completed_at' => null, 'mastered_at' => null),
                'quiz' => array('assessment_id' => 903, 'question_count' => 4,
                                'pass_mark' => 3, 'attempts' => 0),
                'prev_lesson_id' => 87, 'next_lesson_id' => 89,
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401,
        '403' => $err_ref('Either the content is outside the subscription (`not_entitled`) or an earlier lesson is unmastered (`mastery_locked`).',
            array('message' => 'أكمل مراجعة الدرس السابق أولا', 'code' => 'mastery_locked',
                  'errors' => array('details' => array('lesson_id' => 89, 'blocking_lesson_id' => 88,
                                                       'reason' => 'previous_not_mastered')))),
        '404' => $r_404, '429' => $r_429,
    ),
)),

'/api/v1/student/lessons/{id}/progress' => array('post' => array(
    'tags' => array('Lesson'),
    'summary' => 'Watch heartbeat',
    'description' => implode("\n", array(
        'Send this every ~15 seconds while the lesson is playing. It is what makes progress real.',
        '',
        '### `covered` is the field that matters',
        '',
        'It is the list of **ten-second buckets** the playhead actually crossed since your last',
        'heartbeat — `floor(currentTimeInSeconds / 10)` for each tick. Completion is measured from',
        'coverage, not from a counter that goes up, which is why dragging the scrubber to the end does',
        'not finish a lesson. Send the buckets you crossed; up to 100 per beat are accepted and the',
        'rest ignored.',
        '',
        'Bucket zero is a real bucket. `floor(sec/10)` needs no duration to be computed, so send it',
        'from the very first tick — the opening seconds are the ones every single student watches.',
        '',
        '### `media_sec` is testimony, not instruction',
        '',
        'The clip knows its own length and the server does not; every lesson row starts at `00:00:00`',
        'and a teacher\'s typed guess can lock a course for everyone who paid. So report what your',
        'player measured. It is stored **against this student**, and it corrects the lesson row only',
        'when two independent students agree within 10%. One patched client moves its own number and',
        'nobody else\'s.',
        '',
        'Both counters are also clamped against wall-clock time between beats, so a heartbeat claiming',
        'ten minutes of watching four seconds after the last one is trimmed, not trusted.',
        '',
        'The response is the authoritative progress after the write — render from it, do not keep your',
        'own running total. `mastered_at` stays `null` until the quiz is passed; `completed_at` alone',
        'does not unlock the next lesson.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'))),
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'properties' => array(
            'position_sec'  => array('type' => 'integer', 'description' => 'Playhead now, in seconds.'),
            'watched_delta' => array('type' => 'integer', 'description' => 'Seconds watched since the last beat. Capped at 300.'),
            'covered'       => array('type' => 'array', 'items' => array('type' => 'integer'),
                                     'description' => 'Ten-second bucket indices crossed since the last beat. Max 100.'),
            'media_sec'     => array('type' => 'integer', 'description' => 'Clip length as your player reports it, or omit. `duration_sec` is accepted as an alias.'),
        )),
        'example' => array('position_sec' => 84, 'watched_delta' => 12,
                           'covered' => array(7, 8), 'media_sec' => 212),
    ))),
    'responses' => array(
        '200' => array('description' => 'Saved.', 'content' => array('application/json' => array('example' => array(
            'data' => array('lesson_id' => 88, 'position_sec' => 84, 'covered_sec' => 90,
                            'duration_sec' => 212, 'percent' => 42, 'completed_at' => null,
                            'mastered_at' => null, 'declared' => false, 'blind' => false,
                            'can_declare' => false),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401,
        '403' => $err_ref('`mastery_locked` or `not_entitled` — the same checks the read path runs. Progress cannot be written into a lesson that cannot be opened.',
            array('message' => 'أكمل مراجعة الدرس السابق أولا', 'code' => 'mastery_locked')),
        '404' => $r_404, '429' => $r_429,
    ),
)),

'/api/v1/student/lessons/{id}/complete' => array('post' => array(
    'tags' => array('Lesson'),
    'summary' => 'Declare completion',
    'description' => implode("\n", array(
        'The escape hatch for sources that cannot be measured — and it is deliberately narrow.',
        '',
        'Google Drive and bare iframes report no position, so nothing can be measured and the next',
        'lesson would stay locked forever. This endpoint lets the student say "I finished it".',
        '',
        '**It is refused on a source the server considers trackable**, otherwise it would be a way out',
        'of every lesson on the platform. The one exception is a witnessed failure: if heartbeats keep',
        'arriving with no measurement in them, the *server* stamps the blindness (never the client, or',
        'editing one number in your build would buy the exit instantly) and after a two-minute grace',
        'period the declaration is accepted and recorded as `no_signal`.',
        '',
        'So: send an arrival heartbeat as soon as the player mounts, and only offer this button when',
        '`trackable` is `false` — or when it is `true` and the grace period has passed with nothing',
        'measured. Word the two cases differently. "This kind of lesson is not measured" and "we could',
        'not measure your viewing" are different facts, and the second one deserves a "reload first".',
        '',
        'A `403` here is an answer, not a fault: it means the source *is* measurable, so keep playing.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'))),
    'responses' => array(
        '200' => array('description' => 'Recorded.', 'content' => array('application/json' => array('example' => array(
            'data' => array('lesson_id' => 91, 'position_sec' => 0, 'covered_sec' => 0,
                            'duration_sec' => 0, 'percent' => 100,
                            'completed_at' => '2026-08-27T14:02:11+03:00', 'mastered_at' => null,
                            'declared' => true, 'blind' => true, 'can_declare' => true),
            'message' => 'سجل إتمامك للدرس.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401,
        '403' => $err_ref('The lesson is locked or outside the subscription.',
            array('message' => 'أكمل مراجعة الدرس السابق أولا', 'code' => 'mastery_locked')),
        '404' => $r_404,
        '422' => $err_ref('The source *is* measurable, so a declaration is refused — keep playing and sending heartbeats. `errors.details.reason` is `measurable_source`.',
            array('message' => 'بيانات غير صالحة', 'code' => 'validation',
                  'errors' => array('details' => array('reason' => 'measurable_source', 'lesson_id' => 400)))),
        '429' => $r_429,
    ),
)),

'/api/v1/student/media/{token}' => array('get' => array(
    'tags' => array('Lesson'),
    'summary' => 'Stream a protected clip',
    'description' => implode("\n", array(
        'Streams an uploaded lesson file with `Range` support. **This is the only endpoint in the API',
        'that does not return JSON on success** — it returns bytes, `200` or `206`. Failures still use',
        'the standard error envelope.',
        '',
        'The `token` is the one inside `playback.video_url` when `protection` is `"signed"`. Take that',
        'URL as given; do not build it yourself. It is HMAC-signed over lesson, student and expiry, and',
        'lives about five minutes.',
        '',
        'Send the `Authorization` header — in Flutter, `VideoPlayerController.networkUrl(uri,',
        'httpHeaders: {...})`. Three things are checked: the signature, that the token\'s owner is the',
        'bearer of the access token (otherwise sharing the URL in a chat would be enough), and that the',
        'lesson is still unlocked and still paid for.',
        '',
        '> The website has its own copy of this route under `taqdar_gate/`, and that one authenticates',
        '> by session cookie. An app has no cookie, so every uploaded lesson answered `401` there while',
        '> playing fine in a browser — and because YouTube and Vimeo are never signed, the failure only',
        '> appears on the subset of lessons hosted on the platform itself.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'token', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'string'),
                                'description' => 'base64url, from `playback.video_url`.')),
    'responses' => array(
        '200' => array('description' => 'The whole file.',
                       'content' => array('video/mp4' => array('schema' => array('type' => 'string', 'format' => 'binary')))),
        '206' => array('description' => 'Partial content, in answer to a `Range` request.',
                       'content' => array('video/mp4' => array('schema' => array('type' => 'string', 'format' => 'binary')))),
        '401' => $r_401,
        '403' => $err_ref('Token expired or forged (`media_token_expired`), the lesson is locked (`mastery_locked`), or the subscription does not cover it (`not_entitled`). Re-open the lesson to get a fresh token.',
            array('message' => 'انتهت صلاحية رابط التشغيل. أعد فتح الدرس.', 'code' => 'media_token_expired')),
        '404' => $r_404,
        '416' => array('description' => 'The requested `Range` lies outside the file.'),
        '429' => $r_429,
    ),
)),

'/api/v1/student/lessons/{id}/notes' => array(
    'get' => array(
        'tags' => array('Lesson'),
        'summary' => 'Lesson notes',
        'description' => implode("\n", array(
            'The student\'s own notes on this lesson, each pinned to a second of the clip.',
            '',
            'The timestamp is the point: "check minute 4:12" means nothing without one, and a note',
            'without a position is a memo, not a study tool. `at_label` is that second already formatted',
            '(`4:12`) — the same formatter the website uses, so the app does not render `04:12` beside it.',
            'Ownership is checked through the lesson',
            'itself, so a locked lesson has no readable notes either.',
        )),
        'security' => $auth,
        'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                    'schema' => array('type' => 'integer'))),
        'responses' => array(
            '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
                'data' => array(array('id' => 17, 'lesson_id' => 88, 'at_second' => 252,
                                      'at_label' => '4:12',
                                      'body' => 'راجع خطوة الاستلاف من المئات',
                                      'created_at' => '2026-08-22T14:31:00+03:00')),
                'message' => '', 'meta' => array('lesson_id' => 88),
            )))),
            '401' => $r_401, '403' => $r_403, '404' => $r_404, '429' => $r_429,
        ),
    ),
    'post' => array(
        'tags' => array('Lesson'),
        'summary' => 'Add a note',
        'description' => 'Adds one note. `at_second` defaults to `0`; send the playhead so the note can be jumped to later. The response is the **whole** list after the insert, so the screen re-renders from one payload.',
        'security' => $auth,
        'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                    'schema' => array('type' => 'integer'))),
        'requestBody' => array('required' => true, 'content' => array('application/json' => array(
            'schema' => array('type' => 'object', 'required' => array('body'), 'properties' => array(
                'body'      => array('type' => 'string', 'maxLength' => 2000),
                'at_second' => array('type' => 'integer', 'default' => 0),
            )),
            'example' => array('body' => 'راجع خطوة الاستلاف من المئات', 'at_second' => 252),
        ))),
        'responses' => array(
            '201' => array('description' => 'Saved.', 'content' => array('application/json' => array('example' => array(
                'data' => array(array('id' => 17, 'lesson_id' => 88, 'at_second' => 252,
                                      'at_label' => '4:12',
                                      'body' => 'راجع خطوة الاستلاف من المئات',
                                      'created_at' => '2026-08-22T14:31:00+03:00')),
                'message' => 'حفظت ملاحظتك.', 'meta' => new stdClass(),
            )))),
            '401' => $r_401, '403' => $r_403, '404' => $r_404, '422' => $r_422, '429' => $r_429,
        ),
    ),
),

'/api/v1/student/notes/{id}' => array('delete' => array(
    'tags' => array('Lesson'),
    'summary' => 'Delete a note',
    'description' => implode("\n", array(
        'Deletes one of your own notes. The student id is part of the `WHERE`, so a guessed id cannot',
        'reach somebody else\'s note.',
        '',
        'A note that never existed and a note just deleted both answer `200`. That is on purpose:',
        'distinguishing them would confirm to whoever is guessing that the id was real.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'))),
    'responses' => array(
        '200' => array('description' => 'Gone.', 'content' => array('application/json' => array('example' => array(
            'data' => null, 'message' => 'حذفت الملاحظة.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

/* ---------------------------------------------------------------------
   ٤ · التقييم
   ------------------------------------------------------------------- */

'/api/v1/student/lessons/{id}/quiz/start' => array('post' => array(
    'tags' => array('Assessment'),
    'summary' => 'Start (or resume) the lesson quiz',
    'description' => implode("\n", array(
        'Opens an attempt and returns its questions.',
        '',
        '**It resumes an unsubmitted attempt rather than opening a second one.** On a phone that is the',
        'common case, not the rare one — an incoming call is enough. So calling this twice is safe: the',
        'same `attempt_id` comes back and the student is not charged an extra try.',
        '',
        'Questions arrive **without their correct answers**; marking happens on the server. A payload',
        'carrying the options and the answer together turns an exam into a display, and anyone who',
        'opens a proxy reads it.',
        '',
        '`pass_mark` is the number of correct answers needed, never more than `questions.length` —',
        'that is validated when the teacher saves, because a three-question quiz with a pass mark of',
        'five can never be passed, and the next lesson stays locked for every student with nothing on',
        'screen saying why.',
        '',
        'There is no cap on attempts. The consequence of failing is that the lock stays, not that a',
        'door closes.',
        '',
        '`404 no_review` means the lesson has no authored questions — check `quiz` on the lesson',
        'response before offering the button.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'),
                                'description' => 'Lesson id, not assessment id.')),
    'responses' => array(
        '200' => array('description' => 'Attempt open.', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'attempt_id' => 3312, 'attempt_no' => 1, 'assessment_id' => 903, 'lesson_id' => 88,
                'pass_mark' => 3, 'time_limit_sec' => null,
                'questions' => array(array(
                    'id' => 7781, 'title' => 'كم ناتج ٤٢ − ١٧؟', 'type' => 'radio',
                    'options' => array('٢٥', '٣٥', '٢٩', '١٥'), 'objective_id' => 412,
                )),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401,
        '403' => $err_ref('`mastery_locked` or `not_entitled`.',
            array('message' => 'أكمل مراجعة الدرس السابق أولا', 'code' => 'mastery_locked')),
        '404' => $err_ref('No questions are attached to this lesson.',
            array('message' => 'لا توجد مراجعة مرتبطة بهذا الدرس', 'code' => 'no_review')),
        '429' => $r_429,
    ),
)),

'/api/v1/student/quiz/attempts/{id}/submit' => array('post' => array(
    'tags' => array('Assessment'),
    'summary' => 'Submit an attempt',
    'description' => implode("\n", array(
        'Marks the attempt and returns **the gate\'s decision** — which draws the next screen far more',
        'than the score does.',
        '',
        'Every response carries `score`, `out_of`, `pass_mark`, `passed`, `mastered`, `next_lesson_id`',
        'and `next_lesson_locked`. On top of those, one of four branches applies, chosen by attempt',
        'number — they are four different screens, so branch on the fields, not on `score`:',
        '',
        '| Outcome | Extra fields | What to show |',
        '|---|---|---|',
        '| Passed | `mastered_at` · `unlocked_lesson_id` · `scheduled_reviews` | Mastered. The next lesson is open, and `scheduled_reviews` questions were queued for spaced repetition. |',
        '| Failed, 1st | `retry` · `seek_to` · `weakest_objective` | Send them back to that second of the clip. |',
        '| Failed, 2nd | `retry` · `alternate_explanation_id` · `alternate` · `seek_to` | Offer the other explanation of the same objective. |',
        '| Failed, 3rd+ | `retry` · `suggest_session` · `context_objective_id` | Offer a one-to-one session. The lock stays. |',
        '',
        '### The shape of `answers`',
        '',
        'Canonical is a **list of objects** — `[{"question_id": 7781, "given": ["٢٥"]}]` — optionally',
        'with `took_ms` per question. A **map** of question id to answer (`{"7781": ["٢٥"]}`) is also',
        'accepted and normalised. `given` may be a bare string for single-choice.',
        '',
        'A payload in some third shape is rejected with `422`, not scored. It would otherwise mark as',
        '**zero out of three** while every answer was right — the student reads that they failed, and',
        'nothing anywhere reports an error.',
        '',
        'Questions that are not part of this attempt are ignored, and unanswered ones simply count as',
        'wrong; there is no requirement to send an entry for each.',
        '',
        '**No correct answers come back here.** Handing over the solution right after submission spoils',
        'the next attempt. `GET /student/quiz/attempts/{id}` has them, afterwards, when the student asks.',
        '',
        'Submitting the same attempt twice answers `409 duplicate_attempt`; treat it as "already done"',
        'and fetch the review rather than showing an error.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'),
                                'description' => 'Attempt id from the start call.')),
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('answers'), 'properties' => array(
            'answers' => array(
                'description' => 'List of answers, or a map of question id to answer.',
                'oneOf' => array(
                    array('type' => 'array', 'items' => array(
                        'type' => 'object', 'required' => array('question_id', 'given'),
                        'properties' => array(
                            'question_id' => array('type' => 'integer'),
                            'given'   => array('type' => 'array', 'items' => array('type' => 'string')),
                            'took_ms' => array('type' => 'integer', 'description' => 'Time spent on this question. Optional; feeds response speed into the mastery model.'),
                        ))),
                    array('type' => 'object',
                          'additionalProperties' => array('type' => 'array', 'items' => array('type' => 'string'))),
                )),
        )),
        'example' => array('answers' => array(
            array('question_id' => 7781, 'given' => array('٢٥'), 'took_ms' => 4200),
            array('question_id' => 7782, 'given' => array('نعم')),
        )),
    ))),
    'responses' => array(
        '200' => array('description' => 'Marked. The example is the passing branch; see the table above for the other three.',
            'content' => array('application/json' => array('example' => array(
            'data' => array(
                'attempt_id' => 3312, 'attempt_no' => 2, 'score' => 4, 'out_of' => 4,
                'pass_mark' => 3, 'passed' => true, 'mastered' => true,
                'mastered_at' => '2026-08-27T14:44:00+03:00',
                'unlocked_lesson_id' => 89, 'next_lesson_id' => 89, 'next_lesson_locked' => false,
                'scheduled_reviews' => 4,
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '404' => $r_404,
        '409' => $err_ref('This attempt was already submitted.',
            array('message' => 'هذه المحاولة مسلمة من قبل', 'code' => 'duplicate_attempt')),
        '422' => $err_ref('`answers` is in an unrecognised shape. It is refused rather than scored as zero.',
            array('message' => 'صيغة الإجابات غير مفهومة.', 'code' => 'validation_failed',
                  'errors' => array('answers' => array('أرسلها قائمة: [{"question_id":7781,"given":["٢٥"]}] — أو خريطة {"7781":["٢٥"]}.')))),
        '429' => $r_429,
    ),
)),

'/api/v1/student/quiz/attempts/{id}' => array('get' => array(
    'tags' => array('Assessment'),
    'summary' => 'Review a submitted attempt',
    'description' => implode("\n", array(
        'Question by question: what was given, what was right, and whether it matched. **This is the',
        'only place correct answers are returned**, and only for an attempt that has already been',
        'submitted.',
        '',
        'It is a separate request on purpose, not laziness: the submit response stays clean so it',
        'cannot leak into the next try, and this one is something the student chooses to open when they',
        'are done.',
        '',
        '`best` is the highest score across attempts, not the latest — someone who retried until they',
        'mastered it has earned what they mastered.',
        '',
        'An unsubmitted attempt answers `404`, and someone else\'s answers `403`.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'))),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'attempt_id' => 3312, 'lesson_id' => 88, 'score' => 2, 'best' => 4, 'tries' => 2,
                'pass_mark' => 3, 'passed' => false, 'total' => 4,
                'items' => array(array(
                    'question' => 'كم ناتج ٤٢ − ١٧؟', 'type' => 'radio',
                    'options' => array('٢٥', '٣٥', '٢٩', '١٥'),
                    'given' => array('٣٥'), 'correct' => array('٢٥'), 'is_right' => false,
                )),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401,
        '403' => $err_ref('The attempt belongs to someone else.',
            array('message' => 'هذا المحتوى غير متاح ضمن اشتراكك', 'code' => 'not_entitled')),
        '404' => $err_ref('No such attempt, or it has not been submitted yet.',
            array('message' => 'العنصر المطلوب غير موجود', 'code' => 'not_found')),
        '429' => $r_429,
    ),
)),

'/api/v1/student/exams' => array('get' => array(
    'tags' => array('Assessment'),
    'summary' => 'Exams and results',
    'description' => implode("\n", array(
        'Every quiz across the student\'s enrolled courses, with the **latest** attempt on each.',
        'The question a student asks is "where am I now?", not "what did I do over the past month".',
        '`best_score` and `tries` are there for the fuller picture without a second request.',
        '',
        'Unattempted quizzes come first (`state: "not_started"`), then the most recently submitted.',
        '',
        'A quiz with no authored questions is not listed at all — that row exists only because someone',
        'opened the editor and never wrote anything, and listing it promises an exam that cannot be sat.',
        '',
        '> This reads the assessments system, which is where quizzes are authored today. The legacy',
        '> `quiz_results` path is no longer written to, and a screen counting from it alone will tell a',
        '> student who sat four attempts last week that they have no exams yet.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'assessment_id' => 903, 'kind' => 'review',
                'lesson' => array('id' => 88, 'title' => 'الطرح مع الاستلاف'),
                'course' => array('id' => 12, 'title' => 'الرياضيات — الصف الرابع'),
                'question_count' => 4, 'pass_mark' => 3, 'time_limit_sec' => null,
                'tries' => 2, 'best_score' => 4,
                'last_attempt' => array('attempt_id' => 3312, 'attempt_no' => 2, 'score' => 4,
                                        'passed' => true, 'submitted_at' => '2026-08-22T14:44:00+03:00'),
                'state' => 'passed',
            )),
            'message' => '', 'meta' => array('total' => 18, 'taken' => 7, 'passed' => 6),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

/* ---------------------------------------------------------------------
   ٥ · التمرين
   ------------------------------------------------------------------- */

'/api/v1/student/reviews' => array('get' => array(
    'tags' => array('Practice'),
    'summary' => "Today's due reviews",
    'description' => implode("\n", array(
        'Spaced repetition: questions already mastered, returning on the day they are due.',
        '',
        'This is **one batch, not the queue**. `meta.daily_batch` is the configured size and',
        '`meta.total_due` is how many are waiting behind it. There is no parameter to drain the whole',
        'queue, and that is a teaching rule rather than a server limit — two hundred reviews in one',
        'sitting fix nothing.',
        '',
        'The order is the server\'s: longest overdue first, then most-lapsed, then hardest (lowest',
        '`ease`). Re-sorting in the client dismantles the schedule.',
        '',
        'Correct answers are not included. `POST /student/reviews/answer` marks each one.',
        '',
        'Every question links back to its objective, lesson and course, and `objective.at_second` is',
        'the moment in the clip that taught it — enough to offer "rewatch that minute" beside a wrong',
        'answer.',
    )),
    'security' => $auth,
    'parameters' => array(array(
        'name' => 'limit', 'in' => 'query', 'required' => false,
        'schema' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 50),
        'description' => 'Smaller batch than the configured default. Capped at 50; `0` or absent uses the platform setting.',
    )),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'question_id' => 7781, 'title' => 'كم ناتج ٤٢ − ١٧؟', 'type' => 'radio',
                'options' => array('٢٥', '٣٥', '٢٩', '١٥'),
                'objective' => array('id' => 412, 'text' => 'أن يستلف الطالب من منزلة العشرات',
                                     'at_second' => 45),
                'lesson' => array('id' => 88, 'title' => 'الطرح مع الاستلاف'),
                'course' => array('id' => 12, 'title' => 'الرياضيات — الصف الرابع'),
                'due_at' => '2026-08-27T02:00:00+03:00', 'interval_days' => 3, 'lapses' => 1,
            )),
            'message' => '', 'meta' => array('count' => 3, 'total_due' => 3, 'daily_batch' => 10),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/reviews/answer' => array('post' => array(
    'tags' => array('Practice'),
    'summary' => 'Answer a review question',
    'description' => implode("\n", array(
        'Marks one review answer and reschedules the question.',
        '',
        '**Send what was chosen, never whether it was right.** There is no `correct` field in this',
        'request, deliberately — the server compares `given` against the stored answer. A client that',
        'could declare its own correctness would turn the schedule into a toy: announce a success and',
        'the question disappears for sixty days without ever being answered.',
        '',
        'The question must be in **your** queue; otherwise `403`. Without that check, guessing ids',
        'would let anyone move skill levels for material they never studied.',
        '',
        'Scheduling, for what it is worth on screen: right → `ease += 0.1` and the interval multiplies',
        'by it; wrong → `ease` drops by 0.2 (floor 1.3), the interval resets to one day, and `lapses`',
        'goes up. The ceiling is sixty days.',
        '',
        '`remaining_due` is what is left in today\'s batch — enough to drive the progress counter',
        'without another request.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('question_id', 'given'),
            'properties' => array(
                'question_id' => array('type' => 'integer'),
                'given' => array('type' => 'array', 'items' => array('type' => 'string'),
                                 'description' => 'Chosen option(s). Always a list, even for single-choice.'),
            )),
        'example' => array('question_id' => 7781, 'given' => array('٢٥')),
    ))),
    'responses' => array(
        '200' => array('description' => 'Marked and rescheduled.', 'content' => array('application/json' => array('example' => array(
            'data' => array('question_id' => 7781, 'correct' => true, 'interval_days' => 8,
                            'lapses' => 1, 'due_at' => '2026-09-04T14:31:00+03:00',
                            'remaining_due' => 2),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401,
        '403' => $err_ref('This question is not in your queue today.',
            array('message' => 'هذا السؤال ليس في مراجعتك اليوم.', 'code' => 'not_entitled')),
        '404' => $r_404, '422' => $r_422, '429' => $r_429,
    ),
)),

'/api/v1/student/mistakes' => array('get' => array(
    'tags' => array('Practice'),
    'summary' => 'Mistake notebook',
    'description' => implode("\n", array(
        'Questions this student has got wrong, most-repeated first.',
        '',
        'It is derived from the recorded answers rather than kept in a table of its own, so the',
        'notebook cannot drift from what actually happened.',
        '',
        'The row is a **question, not an attempt**: one question missed four times is one entry with',
        '`wrong_count: 4`, not four rows. That is what a student can act on — a repeated mistake, not',
        'a list of occasions.',
        '',
        '`due_at` is when it comes back in review, if it is scheduled. `objective.at_second` is the',
        'moment in the lesson that taught it.',
        '',
        'A question with no objective attached will still appear here, but it teaches nothing beyond',
        'the fact of the error — no mastery map, no scheduling. That is a gap in how the question was',
        'authored, not in this endpoint.',
    )),
    'security' => $auth,
    'parameters' => $page_params,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'question_id' => 7781, 'title' => 'كم ناتج ٤٢ − ١٧؟', 'type' => 'radio',
                'wrong_count' => 3, 'last_wrong_at' => '2026-08-22T14:41:00+03:00',
                'objective' => array('id' => 412, 'text' => 'أن يستلف الطالب من منزلة العشرات',
                                     'at_second' => 45),
                'lesson' => array('id' => 88, 'title' => 'الطرح مع الاستلاف'),
                'course' => array('id' => 12, 'title' => 'الرياضيات — الصف الرابع'),
                'due_at' => '2026-08-28T02:00:00+03:00', 'interval_days' => 1, 'lapses' => 3,
            )),
            'message' => '',
            'meta' => array('pagination' => array('page' => 1, 'per_page' => 20, 'total' => 9,
                                                  'total_pages' => 1, 'has_more' => false)),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),


/* ---- Inbox --------------------------------------------------------
 *
 * الإشعار والرسالة سطحان لسؤال واحد: «ما الجديد؟». وهما أول ما يفتحه
 * الطالب بعد الرئيسية، وآخر ما بني في الويب — فكانا آخر ما وصل الواجهة.
 */

'/api/v1/student/notifications' => array('get' => array(
    'tags' => array('Inbox'),
    'summary' => 'Notifications',
    'description' => implode("\n", array(
        'Everything the platform has told this student, newest first.',
        '',
        '`kind_label`, `icon` and `tone` come from one classification table shared with the website',
        'and the guardian portal — so a failed milestone reads as a failed milestone in all three,',
        'not as "other alerts" in one of them.',
        '',
        '**The counts in `meta` are over the whole feed, not over the page or the filter.** A tab',
        'that counts only what is inside it says zero the moment you open it having read the last',
        'unread item, and then looks broken.',
    )),
    'security' => $auth,
    'parameters' => array_merge($page_params, array(
        array('name' => 'state', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'string', 'enum' => array('all', 'unread', 'read')),
              'description' => 'Defaults to `all`.'),
    )),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'id' => 9912, 'type' => 'exam_result', 'kind_label' => 'نتيجة امتحان',
                'icon' => 'check-badge', 'tone' => 'mint',
                'title' => 'نتيجة اختبار الطرح مع الاستلاف',
                'body' => 'اجتزت الاختبار بنسبة ٨٥٪ — وفتح لك الدرس التالي.',
                'is_read' => false,
                'created_at' => '2026-08-22T14:41:00+03:00',
            )),
            'message' => '',
            'meta' => array(
                'pagination' => array('page' => 1, 'per_page' => 20, 'total' => 31,
                                      'total_pages' => 2, 'has_more' => true),
                'counts' => array('all' => 31, 'unread' => 4, 'read' => 27),
                'by_kind' => array('نتيجة امتحان' => array('count' => 12, 'icon' => 'check-badge', 'tone' => 'mint')),
                'filters' => array('state' => 'all'),
            ),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/notifications/read' => array('post' => array(
    'tags' => array('Inbox'),
    'summary' => 'Mark notifications read',
    'description' => implode("\n", array(
        'Send `{"id": 9912}` for one, or `{"all": true}` for the whole inbox.',
        '',
        'The reply carries the counts **after** the change, so the badge updates from this response',
        'and never needs a second call to agree with the server.',
        '',
        'Ownership is in the `WHERE` clause, not in a check before it: a guessed id cannot clear',
        'somebody else\'s unread dot.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'properties' => array(
            'id'  => array('type' => 'integer', 'example' => 9912),
            'all' => array('type' => 'boolean', 'example' => false),
        )),
    ))),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array('changed' => 1, 'counts' => array('all' => 31, 'unread' => 3, 'read' => 28)),
            'message' => 'قرئ الإشعار.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '422' => $r_422, '429' => $r_429,
    ),
)),

'/api/v1/student/messages' => array(
    'get' => array(
        'tags' => array('Inbox'),
        'summary' => 'Conversations',
        'description' => implode("\n", array(
            'Threads this student is a party to, most recently active first.',
            '',
            'Filtering runs on the server (`filter`, `q`). A second copy of "who counts as a teacher"',
            'in Dart drifts from this one on the first change — the same reason the catalogue filters',
            'live on the server too.',
            '',
            '`unread_total` in `meta` is over every thread, not over the filtered page.',
        )),
        'security' => $auth,
        'parameters' => array_merge($page_params, array(
            array('name' => 'filter', 'in' => 'query', 'required' => false,
                  'schema' => array('type' => 'string', 'enum' => array('all', 'unread', 'teachers', 'support'))),
            array('name' => 'q', 'in' => 'query', 'required' => false,
                  'schema' => array('type' => 'string'), 'description' => 'Matches the other party\'s name and the last message.'),
        )),
        'responses' => array(
            '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
                'data' => array(array(
                    'code' => 'a91c3f7e', 'unread' => 2,
                    'person' => array('id' => 148, 'name' => 'أ. سارة', 'role' => 'teacher',
                                      'avatar_url' => $TQ_API_BASE . '/uploads/user_image/148.jpg'),
                    'last' => array('body' => 'راجع تمرين ٣ قبل الحصة.', 'mine' => false,
                                    'at' => '2026-08-22T14:41:00+03:00'),
                    'updated_at' => '2026-08-22T14:41:00+03:00',
                )),
                'message' => '',
                'meta' => array(
                    'pagination' => array('page' => 1, 'per_page' => 20, 'total' => 3,
                                          'total_pages' => 1, 'has_more' => false),
                    'unread_total' => 2, 'filters' => array('filter' => 'all', 'q' => ''),
                ),
            )))),
            '401' => $r_401, '403' => $r_403, '429' => $r_429,
        ),
    ),
    'post' => array(
        'tags' => array('Inbox'),
        'summary' => 'Start a conversation',
        'description' => implode("\n", array(
            'Opens a new thread with one of the accounts listed by `GET /student/messages/recipients`.',
            '',
            '**The recipient scope is enforced here, not merely displayed.** The underlying Academy',
            'model reads `receiver` from the request and does not check it — so without this guard,',
            'changing a number in the payload messages any account on the platform. Students never',
            'message students.',
        )),
        'security' => $auth,
        'requestBody' => array('required' => true, 'content' => array('application/json' => array(
            'schema' => array('type' => 'object', 'required' => array('receiver', 'body'),
                'properties' => array(
                    'receiver' => array('type' => 'integer', 'example' => 148),
                    'body'     => array('type' => 'string', 'maxLength' => 5000,
                                        'example' => 'أستاذة، لم أفهم خطوة الاستلاف.'),
                )),
        ))),
        'responses' => array(
            '201' => array('description' => 'Created', 'content' => array('application/json' => array('example' => array(
                'data' => array('thread_code' => 'a91c3f7e'),
                'message' => 'وصلت رسالتك.', 'meta' => new stdClass(),
            )))),
            '401' => $r_401,
            '403' => $err_ref('The recipient is not one of this student\'s teachers or the support account.',
                              array('message' => 'لا ترسل الرسائل إلا إلى معلمي موادك أو الدعم الفني.',
                                    'code' => 'recipient_not_allowed')),
            '422' => $r_422, '429' => $r_429,
        ),
    ),
),

'/api/v1/student/messages/recipients' => array('get' => array(
    'tags' => array('Inbox'),
    'summary' => 'Who may be messaged',
    'description' => implode("\n", array(
        'The teachers of this student\'s enrolled courses, plus the support account.',
        '',
        'It is the same list the send endpoint validates against. Two lists built from two queries',
        'drift, and then the picker offers a name the guard rejects — the student reads "you may only',
        'message your teachers" about somebody we showed them.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                array('id' => 148, 'name' => 'أ. سارة', 'role' => 'teacher',
                      'avatar_url' => $TQ_API_BASE . '/uploads/user_image/148.jpg'),
                array('id' => 1, 'name' => 'الدعم الفني', 'role' => 'support',
                      'avatar_url' => $TQ_API_BASE . '/assets/taqdar/brand/avatar.svg'),
            ),
            'message' => '',
            'meta' => array('note' => 'المراسلة متاحة مع معلميك والدعم فقط، ولا رسائل خاصة بين الطلاب.'),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/messages/{code}' => array(
    'get' => array(
        'tags' => array('Inbox'),
        'summary' => 'Read a conversation',
        'description' => implode("\n", array(
            'Messages oldest-first, as they read. Opening the thread marks it read — which is what',
            'opening it means.',
            '',
            '`mine` saves the client comparing ids on every row.',
        )),
        'security' => $auth,
        'parameters' => array(array('name' => 'code', 'in' => 'path', 'required' => true,
                                    'schema' => array('type' => 'string'))),
        'responses' => array(
            '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
                'data' => array(array('id' => 5521, 'body' => 'راجع تمرين ٣ قبل الحصة.',
                                      'mine' => false, 'is_read' => true,
                                      'sent_at' => '2026-08-22T14:41:00+03:00')),
                'message' => '', 'meta' => array('thread_code' => 'a91c3f7e'),
            )))),
            '401' => $r_401, '403' => $r_403, '404' => $r_404, '429' => $r_429,
        ),
    ),
    'post' => array(
        'tags' => array('Inbox'),
        'summary' => 'Reply in a conversation',
        'description' => 'Ownership of the thread is checked before the write: a guessed code cannot inject a message into somebody else\'s conversation. Returns the thread as it now stands.',
        'security' => $auth,
        'parameters' => array(array('name' => 'code', 'in' => 'path', 'required' => true,
                                    'schema' => array('type' => 'string'))),
        'requestBody' => array('required' => true, 'content' => array('application/json' => array(
            'schema' => array('type' => 'object', 'required' => array('body'),
                'properties' => array('body' => array('type' => 'string', 'maxLength' => 5000))),
        ))),
        'responses' => array(
            '201' => array('description' => 'Created'),
            '401' => $r_401, '403' => $r_403, '404' => $r_404, '422' => $r_422, '429' => $r_429,
        ),
    ),
    'delete' => array(
        'tags' => array('Inbox'),
        'summary' => 'Delete a conversation',
        'description' => 'Removes the thread and its messages for both parties — that is what the Academy schema stores. Ownership is required.',
        'security' => $auth,
        'parameters' => array(array('name' => 'code', 'in' => 'path', 'required' => true,
                                    'schema' => array('type' => 'string'))),
        'responses' => array(
            '200' => array('description' => 'OK'),
            '401' => $r_401, '403' => $r_403, '404' => $r_404, '429' => $r_429,
        ),
    ),
),

/* ---- Progress ------------------------------------------------------
 *
 * ما أنجزه الطالب معروضا عليه: مهامه وتقويمه وتقاريره وشهاداته.
 */

'/api/v1/student/tasks' => array('get' => array(
    'tags' => array('Progress'),
    'summary' => 'Homework',
    'description' => implode("\n", array(
        'Assignments across enrolled courses, in three groups: `todo`, `progress`, `done`.',
        '',
        '**There is no "late" group.** The schema stores no due date for homework, so lateness cannot',
        'be measured — and a group that invents one tells the student they missed a deadline nobody',
        'set.',
        '',
        'On a finished item, `graded: false` means *submitted and waiting for the teacher*, which is a',
        'different state from a score of zero. `score` stays `null` until the teacher approves it —',
        'showing a provisional number that later changes is worse than showing none.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array('key' => 'done', 'label' => 'مكتملة', 'items' => array(array(
                'lesson_id' => 88, 'course_id' => 12, 'title' => 'واجب الطرح مع الاستلاف',
                'subject' => 'الرياضيات', 'stage' => 'المرحلة الابتدائية',
                'minutes' => 20, 'questions' => 8, 'pass_mark' => 6,
                'at' => '2026-08-22T14:41:00+03:00',
                'graded' => true, 'score' => 85, 'max' => 100, 'passed' => true,
                'note' => 'انتبه لخطوة الاستلاف في السؤال الخامس.',
                'web_url' => $TQ_API_BASE . '/student/lesson/12/88',
            )))),
            'message' => '',
            'meta' => array('counts' => array('todo' => 2, 'progress' => 0, 'done' => 5), 'total' => 7),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/calendar' => array('get' => array(
    'tags' => array('Progress'),
    'summary' => 'Calendar events',
    'description' => implode("\n", array(
        'A **flat, time-ordered list** — not a month grid. Drawing the grid is presentation, and Dart',
        'does it better from dates than from a serialised table of rows and columns.',
        '',
        'Five sources are merged: unit start/end dates, quiz starts and submissions, homework',
        'attempts, booked private sessions, and spaced-review due dates. Every event carries its own',
        '`web_url` — a calendar you cannot act from is a noticeboard.',
        '',
        '`from`/`to` bound the window (`YYYY-MM-DD`, `to` inclusive). Without them everything the',
        'calendar knows is returned, which is hundreds of rows on an old account.',
    )),
    'security' => $auth,
    'parameters' => array(
        array('name' => 'from', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'string', 'format' => 'date')),
        array('name' => 'to', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'string', 'format' => 'date')),
        array('name' => 'cat', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'string',
                                'enum' => array('lessons', 'exams', 'tasks', 'on_demand', 'revisions'))),
    ),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'at' => '2026-08-24T18:00:00+03:00', 'date' => '2026-08-24',
                'category' => 'on_demand', 'category_label' => 'حصص بالطلب', 'icon' => 'video',
                'title' => 'حصة مع أ. سارة', 'subtitle' => '60 دقيقة',
                'web_url' => $TQ_API_BASE . '/student/on-demand',
            )),
            'message' => '',
            'meta' => array(
                'categories' => array(array('key' => 'on_demand', 'label' => 'حصص بالطلب',
                                            'icon' => 'video', 'action' => 'دخول الحصة', 'count' => 1)),
                'filters' => array('from' => null, 'to' => null, 'cat' => ''),
                'total' => 1,
            ),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/reports' => array('get' => array(
    'tags' => array('Progress'),
    'summary' => 'Progress report',
    'description' => implode("\n", array(
        'Study time, completion, average score, and eight weekly buckets ending with the current week.',
        '',
        '**A week with no measurement returns `null`, not `0`.** Zero would drop the line to the floor',
        'and read as a collapse in a week where nothing was measured at all. The same reason `deltas`',
        'are `null` unless both of the last two weeks actually carry a number.',
        '',
        'Numerator and denominator come from one set — the enrolled courses. Reading progress from',
        'every course the student ever touched while counting lessons only from enrolments printed',
        '"6 of 0 lessons": a figure that gives itself away and explains nothing.',
        '',
        'A quiz score the teacher has not approved is excluded from the average, for the same reason',
        'it is hidden on the homework card.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'has_data' => true,
                'totals' => array('study_seconds' => 40920, 'study_hours' => 11, 'study_minutes' => 22,
                                  'completion' => 64, 'average_score' => 81,
                                  'lessons_done' => 27, 'lessons_total' => 44, 'courses' => 3),
                'deltas' => array('grade' => 4, 'completion' => null),
                'weeks' => array(array('from' => '2026-08-16', 'to' => '2026-08-22',
                                       'grade' => 81, 'completion' => null, 'lessons' => 27)),
                'subjects' => array(array('name' => 'الرياضيات', 'courses' => 2, 'lessons' => 30, 'percent' => 71)),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/certificates' => array('get' => array(
    'tags' => array('Progress'),
    'summary' => 'Certificates',
    'description' => implode("\n", array(
        'Certificates are issued on **measured mastery, not on watching**: a passed milestone exam,',
        'nothing else. A student who has not passed one reads an empty list — which is the correct',
        'empty state, not a gap.',
        '',
        'The certificate itself is a page that prints and carries a verification code, so there is no',
        'JSON body for it: open `web_url` in an in-app browser, exactly as you open the payment page.',
        '`verify_url` is public — any third party can confirm the certificate from it.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'id' => 331, 'code' => 'TQ-000331', 'title' => 'محطة الأعداد والعمليات',
                'score' => 92, 'issued_at' => '2026-08-20T09:12:00+03:00',
                'web_url' => $TQ_API_BASE . '/student/certificate/331',
                'verify_url' => $TQ_API_BASE . '/student/verify/331',
            )),
            'message' => '', 'meta' => array('count' => 1),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

/* ---- Library ------------------------------------------------------ */

'/api/v1/student/library' => array('get' => array(
    'tags' => array('Library'),
    'summary' => 'Books',
    'description' => implode("\n", array(
        'Published books for this student\'s **stage** (not their grade) — the same category the',
        'catalogue files books under, so there is no second taxonomy to drift from it.',
        '',
        'If the stage has no books, every published book is returned and `meta.scoped` is `false`. A',
        'filter that empties the screen is worse than no filter: an empty state is a dead end in a',
        'fixed navigation list. Read `scoped` to label the screen honestly.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'id' => 14, 'title' => 'الرياضيات — كتاب الطالب', 'slug' => 'math-student-book',
                'subject' => 'الرياضيات', 'author' => 'وزارة التعليم', 'pages' => 212,
                'description' => '', 'cover_url' => null,
                'file_url' => $TQ_API_BASE . '/uploads/books/math.pdf',
                'web_url' => $TQ_API_BASE . '/book/math-student-book',
            )),
            'message' => '', 'meta' => array('count' => 1, 'scoped' => true),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/materials' => array('get' => array(
    'tags' => array('Library'),
    'summary' => 'Course materials',
    'description' => implode("\n", array(
        'Files attached to the lessons of enrolled courses — both `resource_files` rows and the',
        'lesson\'s own attachment.',
        '',
        '`favourable: false` marks the second kind: a lesson attachment has no row of its own, so it',
        'has no stable id to favourite. A heart that does not know what it is saving is not shown.',
        '',
        '`meta.by_type` counts the whole set, not the filtered page — so the type chips keep their',
        'numbers after a filter is applied.',
    )),
    'security' => $auth,
    'parameters' => array_merge($page_params, array(
        array('name' => 'type', 'in' => 'query', 'required' => false,
              'schema' => array('type' => 'string',
                                'enum' => array('pdf', 'video', 'slide', 'audio', 'image', 'link', 'doc'))),
        array('name' => 'q', 'in' => 'query', 'required' => false, 'schema' => array('type' => 'string')),
    )),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'id' => 77, 'title' => 'ورقة عمل — الاستلاف', 'lesson' => 'الطرح مع الاستلاف',
                'course' => 'الرياضيات — الصف الرابع', 'subject' => 'الرياضيات',
                'kind' => 'pdf', 'kind_label' => 'PDF',
                'url' => $TQ_API_BASE . '/uploads/resource_files/w1.pdf',
                'bytes' => 184320, 'added_at' => '2026-08-10T10:00:00+03:00',
                'favourable' => true, 'favourite' => false,
            )),
            'message' => '',
            'meta' => array(
                'pagination' => array('page' => 1, 'per_page' => 20, 'total' => 12,
                                      'total_pages' => 1, 'has_more' => false),
                'by_type' => array('pdf' => 9, 'video' => 3),
                'filters' => array('type' => '', 'q' => ''),
            ),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/favourites' => array('get' => array(
    'tags' => array('Library'),
    'summary' => 'Saved items',
    'description' => implode("\n", array(
        'Courses, lessons and materials the student has hearted — all three in one call, because the',
        'screen is tabs over one list and three calls would be three round trips to draw one tab.',
        '',
        'On a saved course, `enrolled` distinguishes *saved* from *owned*: a student hearts a course',
        'they do not have yet in order to buy it, and a card promising "continue" on locked content',
        'breaks on the first tap.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'type', 'in' => 'query', 'required' => false,
        'schema' => array('type' => 'string', 'enum' => array('courses', 'lessons', 'materials')),
        'description' => 'Return one bucket only. Omit for all three.')),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'courses' => array(array('id' => 12, 'title' => 'الرياضيات — الصف الرابع',
                                         'level' => '', 'summary' => '', 'thumbnail' => null,
                                         'enrolled' => true,
                                         'progress' => array('total_lessons' => 20, 'completed' => 13,
                                                             'mastered' => 11, 'percent' => 65,
                                                             'next_lesson_id' => 91))),
                'lessons' => array(), 'materials' => array(),
            ),
            'message' => '',
            'meta' => array('counts' => array('courses' => 1, 'lessons' => 0, 'materials' => 0),
                            'filters' => array('type' => '')),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/favourites/toggle' => array('post' => array(
    'tags' => array('Library'),
    'summary' => 'Toggle a favourite',
    'description' => implode("\n", array(
        'Adds it if absent, removes it if present.',
        '',
        'The reply carries `on` — the state **after** the toggle. Guessing it client-side means the',
        'heart disagrees with the server the first time the network stutters.',
        '',
        'Ownership is checked in the model: a student cannot favourite a lesson of a course they are',
        'not enrolled in by guessing an id, which would turn the favourites list into an index of',
        'content they do not have.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('kind', 'item_id'),
            'properties' => array(
                'kind'    => array('type' => 'string', 'enum' => array('course', 'lesson', 'material')),
                'item_id' => array('type' => 'integer', 'example' => 77),
            )),
    ))),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array('kind' => 'material', 'item_id' => 77, 'on' => true),
            'message' => 'حفظ في المفضلة.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '422' => $r_422, '429' => $r_429,
    ),
)),

'/api/v1/student/search' => array('get' => array(
    'tags' => array('Library'),
    'summary' => 'Search what the student owns',
    'description' => implode("\n", array(
        'Searches the student\'s **own** courses, lessons and materials — not the public catalogue.',
        'The catalogue answers "what does the platform offer?"; this answers "where was that lesson I',
        'watched?".',
        '',
        'The three sources are the same functions that build those three screens, so a hit here opens',
        'to exactly what the screen would have shown.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'q', 'in' => 'query', 'required' => true,
                                'schema' => array('type' => 'string', 'maxLength' => 120))),
    'responses' => array(
        '200' => array('description' => 'OK'),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

/* ---- Onboarding --------------------------------------------------- */

'/api/v1/student/setup' => array(
    'get' => array(
        'tags' => array('Onboarding'),
        'summary' => 'Read the study plan',
        'description' => 'Grade, chosen subjects, and the daily goal with its unit. `needs` is `true` while the student has not been onboarded — the home screen\'s `next_step` reads the same flag.',
        'security' => $auth,
        'responses' => array('200' => array('description' => 'OK'),
                             '401' => $r_401, '403' => $r_403, '429' => $r_429),
    ),
    'post' => array(
        'tags' => array('Onboarding'),
        'summary' => 'Save the study plan',
        'description' => implode("\n", array(
            'Validation lives in the same model the website posts to, so the app cannot save a goal',
            'the site would reject.',
            '',
            '`next` says where to go: `placement` when a diagnostic is still owed, otherwise `home`.',
            'Reading "plan saved" and then being thrown to an unrequested screen is the thing this',
            'field avoids.',
        )),
        'security' => $auth,
        'requestBody' => array('required' => true, 'content' => array('application/json' => array(
            'schema' => array('type' => 'object', 'properties' => array(
                'grade_id'    => array('type' => 'integer', 'example' => 20),
                'subject_ids' => array('type' => 'array', 'items' => array('type' => 'integer')),
                'goal_unit'   => array('type' => 'string', 'enum' => array('minutes', 'lessons', 'reviews')),
                'goal_value'  => array('type' => 'integer', 'example' => 30),
            )),
        ))),
        'responses' => array('200' => array('description' => 'OK'),
                             '401' => $r_401, '403' => $r_403, '422' => $r_422, '429' => $r_429),
    ),
),

'/api/v1/student/exam-mode' => array('post' => array(
    'tags' => array('Onboarding'),
    'summary' => 'Exam mode',
    'description' => 'Send `{"from","to"}` to switch it on for a date range, or `{"off": true}` to switch it off. The range is validated in the model, so a reversed or past range fails here exactly as it does on the website.',
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'properties' => array(
            'from' => array('type' => 'string', 'format' => 'date'),
            'to'   => array('type' => 'string', 'format' => 'date'),
            'off'  => array('type' => 'boolean'),
        )),
    ))),
    'responses' => array('200' => array('description' => 'OK'),
                         '401' => $r_401, '403' => $r_403, '422' => $r_422, '429' => $r_429),
)),

'/api/v1/student/gamify' => array('post' => array(
    'tags' => array('Onboarding'),
    'summary' => 'Streaks and goal ring',
    'description' => 'A preference, not a system setting: a student who turns it off sees no motivational figure anywhere — website and app alike, because both read the same flag.',
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'properties' => array('on' => array('type' => 'boolean'))),
    ))),
    'responses' => array('200' => array('description' => 'OK'),
                         '401' => $r_401, '403' => $r_403, '429' => $r_429),
)),

'/api/v1/student/placement' => array('get' => array(
    'tags' => array('Onboarding'),
    'summary' => 'Placement test state',
    'description' => implode("\n", array(
        'Three states: `intro` before starting, `exam` while an attempt is open (with its questions),',
        '`result` after submission (with the level and the recommended plan). A fourth, `unavailable`,',
        'means this student\'s grade has no authored test — a normal condition, not an error.',
        '',
        '**The open attempt is read before the submitted one.** With them the other way round, a',
        'student granted a retake is returned to their old result forever and the "allow retake"',
        'switch in the admin panel has no door.',
        '',
        'Questions carry no correct answers. Marking happens on the server.',
        '',
        'The recommended plan is only included when its public page would actually open: a plan',
        'deactivated after being linked to a level used to be shown here with a button returning 404.',
    )),
    'security' => $auth,
    'responses' => array('200' => array('description' => 'OK'),
                         '401' => $r_401, '403' => $r_403, '429' => $r_429),
)),

'/api/v1/student/placement/start' => array('post' => array(
    'tags' => array('Onboarding'),
    'summary' => 'Start the placement test',
    'description' => 'Stamps the start time. Call `GET /student/placement` afterwards to read the questions.',
    'security' => $auth,
    'responses' => array('201' => array('description' => 'Created'),
                         '401' => $r_401, '403' => $r_403,
                         '409' => $err_ref('This student\'s grade has no authored placement test.',
                                           array('message' => 'لا اختبار تحديد مستوى لصفك بعد.',
                                                 'code' => 'placement_unavailable')),
                         '429' => $r_429),
)),

'/api/v1/student/placement/submit' => array('post' => array(
    'tags' => array('Onboarding'),
    'summary' => 'Submit the placement test',
    'description' => implode("\n", array(
        'Send `answers` as a map of question id to the chosen option text.',
        '',
        'Correctness is decided from the stored answers, so adding a field to the payload adds',
        'nothing to the result. The recommendation is emailed to the guardian after the result is',
        'saved — the person who decides about a plan is usually not the one holding this screen.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('answers'),
            'properties' => array('answers' => array('type' => 'object',
                'additionalProperties' => array('type' => 'string'),
                'example' => array('101' => 'خمسة وعشرون', '102' => 'ثمانية')))),
    ))),
    'responses' => array('201' => array('description' => 'Created'),
                         '401' => $r_401, '403' => $r_403, '422' => $r_422, '429' => $r_429),
)),

/* ---- Sessions ------------------------------------------------------ */

'/api/v1/student/sessions' => array(
    'get' => array(
        'tags' => array('Sessions'),
        'summary' => 'Private sessions',
        'description' => implode("\n", array(
            'Bookings, pricing and available teachers in one call — the screen shows all three, and',
            'three round trips to draw one screen read as slowness, not as architecture.',
            '',
            'The lifecycle is: requested → the teacher confirms → `awaiting_payment` with a deadline →',
            'paid → `confirmed` → `live` → `completed`. **Payment comes after confirmation, not before**',
            '— declining is common, and paying first would mean a card refund on every decline.',
            '',
            '`can_join` and `meet_url` come from the same guard the website uses. A link the client',
            'decides to show opens an empty room two days early, or one that has already ended.',
            '',
            '`pricing.free` (price zero) means sessions are free **by decision**: the teacher confirms',
            'and the session becomes `confirmed` immediately, with no invoice and no deadline.',
        )),
        'security' => $auth,
        'parameters' => array(array('name' => 'subject', 'in' => 'query', 'required' => false,
                                    'schema' => array('type' => 'integer'))),
        'responses' => array('200' => array('description' => 'OK'),
                             '401' => $r_401, '403' => $r_403, '429' => $r_429),
    ),
    'post' => array(
        'tags' => array('Sessions'),
        'summary' => 'Request a session',
        'description' => 'Books one of a teacher\'s open slots. The price and the teacher\'s share are **frozen onto the row at request time**: a price rise between the request and the teacher\'s reply does not change what the student agreed to. The slot stays `held` until payment — reserving it earlier would drop a slot from the teacher\'s diary for everyone who asked and did not pay.',
        'security' => $auth,
        'requestBody' => array('required' => true, 'content' => array('application/json' => array(
            'schema' => array('type' => 'object', 'required' => array('slot_id'),
                'properties' => array('slot_id' => array('type' => 'integer', 'example' => 4412))),
        ))),
        'responses' => array('201' => array('description' => 'Created'),
                             '401' => $r_401, '403' => $r_403, '409' => $err_ref(
                                 'The slot is gone, already booked, or past.',
                                 array('message' => 'هذا الموعد لم يعد متاحا.', 'code' => 'session_request_failed')),
                             '422' => $r_422, '429' => $r_429),
    ),
),

'/api/v1/student/sessions/{id}/pay' => array('post' => array(
    'tags' => array('Sessions'),
    'summary' => 'Pay for a confirmed session',
    'description' => implode("\n", array(
        'Takes the **session** id and derives its invoice. It deliberately does not accept an invoice',
        'number from the client: a guessed number would open a payment page for somebody else\'s',
        'invoice.',
        '',
        'Returns a URL; it does not take money. Open it in an in-app browser. The session confirms',
        'itself once Tap settles — through the return, the webhook, or the reconcile cron.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'))),
    'responses' => array('200' => array('description' => 'OK'),
                         '401' => $r_401, '403' => $r_403, '404' => $r_404,
                         '409' => $err_ref('The session is not awaiting payment.',
                                           array('message' => 'هذه الحصة ليست في انتظار الدفع.',
                                                 'code' => 'session_not_payable')),
                         '429' => $r_429),
)),

'/api/v1/student/sessions/{id}/cancel' => array('post' => array(
    'tags' => array('Sessions'),
    'summary' => 'Cancel before payment',
    'description' => 'A student may cancel while the session is `requested` or `awaiting_payment`. **After payment they may not** — there is no automatic refund path in this setup, and a button that cancels without refunding leaves the student with neither the session nor the money. Administration handles a paid cancellation as a refund.',
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'))),
    'requestBody' => array('required' => false, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'properties' => array('reason' => array('type' => 'string'))),
    ))),
    'responses' => array('200' => array('description' => 'OK'),
                         '401' => $r_401, '403' => $r_403,
                         '409' => $err_ref('Already paid, already over, or not cancellable.',
                                           array('message' => 'لا تلغى حصة مدفوعة من هنا.',
                                                 'code' => 'session_cancel_failed')),
                         '429' => $r_429),
)),

/* ---- Store ---------------------------------------------------------
 *
 * ثلاث وحدات بيع على مرساة واحدة: الفاتورة. والشراء يرد رابطا ولا يقبض
 * مالا، كما ترد `invoices/{id}/pay`.
 */

'/api/v1/student/plans' => array('get' => array(
    'tags' => array('Store'),
    'summary' => 'Plans on offer',
    'description' => implode("\n", array(
        'The plans a visitor can buy — `scope = grade` only. A plan with another scope is bought by',
        'its code and never listed publicly.',
        '',
        '**`cycles` is the thing you buy, not `price`.** Monthly and annual are two prices for one',
        'plan row, not two rows: two rows carrying two numbers for one fact diverge the first time',
        'the annual price is edited, and nothing says which is right. Send `cycles[].key` as `cycle`',
        'when subscribing. A screen that shows the monthly figure and then buys without the key makes',
        'whoever tapped "monthly" pay for the year.',
        '',
        'The monthly cycle exists **for annual plans only** — applying the discount to a quarterly',
        'plan would invent a saving that does not exist, and a monthly plan is already monthly.',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(array(
                'id' => 31, 'code' => 'g4-full', 'name' => 'باقة الصف الرابع',
                'note' => '', 'price' => array('amount' => 39900, 'decimal' => '399.00',
                                               'currency' => 'SAR', 'formatted' => '399.00 ر.س'),
                'period' => 'annual', 'duration_days' => 365, 'scope' => 'grade',
                'stage' => 'primary', 'is_trial' => false,
                'cycles' => array(
                    array('key' => 'annual', 'label' => 'سنوي', 'unit' => 'سنويا',
                          'price' => array('amount' => 39900, 'decimal' => '399.00',
                                           'currency' => 'SAR', 'formatted' => '399.00 ر.س'),
                          'days' => 365, 'default' => true),
                    array('key' => 'monthly', 'label' => 'شهري', 'unit' => 'شهريا',
                          'price' => array('amount' => 4200, 'decimal' => '42.00',
                                           'currency' => 'SAR', 'formatted' => '42.00 ر.س'),
                          'days' => 30, 'default' => false),
                ),
                'features' => array('كل مواد الصف', 'اختبارات وتقارير'),
                'cover_url' => $TQ_API_BASE . '/assets/taqdar/site/img/cov-plan-primary.webp',
                'web_url' => $TQ_API_BASE . '/plan/g4-full',
            )),
            'message' => '',
            'meta' => array('count' => 1, 'card_enabled' => true,
                            'bank' => array('enabled' => false), 'current' => false),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

'/api/v1/student/plans/{code}' => array('get' => array(
    'tags' => array('Store'),
    'summary' => 'One plan and what it opens',
    'description' => implode("\n", array(
        'The plan plus its contents.',
        '',
        'Contents are **derived, not listed**: `plans.scope_ids → grades → paths → course → section →',
        'lesson`. No column ties a lesson to a plan, and none should — with one, every new lesson',
        'would need a pass over every plan, and it would be forgotten.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'code', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'string', 'example' => 'g4-full'))),
    'responses' => array('200' => array('description' => 'OK'),
                         '401' => $r_401, '403' => $r_403, '404' => $r_404, '429' => $r_429),
)),

'/api/v1/student/subscribe' => array('post' => array(
    'tags' => array('Store'),
    'summary' => 'Subscribe to a plan',
    'description' => implode("\n", array(
        '**The invoice is issued first, in both payment paths.** Creating the Tap charge before',
        'writing the invoice would mean a student whose connection dropped after paying has paid with',
        'no row on our side to match it.',
        '',
        'Send `cycle` from `cycles[].key`. It is passed through, never interpreted here: a key the',
        'plan does not own falls back to **the plan\'s own cycle**, which is the dearer one. Falling',
        'back to the cheapest would let an edited payload buy a year at a month\'s price.',
        '',
        'With `pay_method: "tap"` the reply carries `payment_url`; open it in an in-app browser. With',
        '`manual` it carries `bank` — the transfer details with the invoice number as the reference.',
        'A free plan activates immediately: `free: true`, no invoice, no URL.',
        '',
        'If starting the card payment fails, the response is still **success**, because the invoice',
        'was issued: it carries `payment_url: null` and the bank details. Answering with a bare error',
        'makes the student buy again and end up with two pending subscriptions.',
        '',
        'The same plan may be bought over itself — that is renewal, and it starts from the end of the',
        'current term, not from now. A different plan over an active one is refused.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('plan_id'),
            'properties' => array(
                'plan_id'    => array('type' => 'integer', 'example' => 31),
                'cycle'      => array('type' => 'string', 'example' => 'monthly',
                                      'description' => 'A key from the plan\'s `cycles`. Omit for the plan\'s own cycle.'),
                'pay_method' => array('type' => 'string', 'enum' => array('tap', 'manual'), 'default' => 'manual'),
            )),
    ))),
    'responses' => array(
        '201' => array('description' => 'Created', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'subscription_id' => 918, 'free' => false,
                'invoice' => array('id' => 4471, 'invoice_no' => 'TQ-2026-004471',
                                   'status' => 'unpaid', 'status_label' => 'غير مدفوعة',
                                   'item' => array('kind' => 'plan', 'ref_id' => 31,
                                                   'title' => 'باقة الصف الرابع')),
                'payment_url' => 'https://checkout.tap.company/...',
            ),
            'message' => 'جهزت صفحة الدفع.', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403,
        '409' => $err_ref('Refused. `code` says why: `placement_required`, `plan_not_priced`, or an active subscription already in place.',
                          array('message' => 'قبل الاشتراك: اختبار قصير يحدد موضعك.',
                                'code' => 'placement_required')),
        '422' => $r_422, '429' => $r_429,
    ),
)),

'/api/v1/student/subscribe-path' => array('post' => array(
    'tags' => array('Store'),
    'summary' => 'Buy a single path',
    'description' => 'Same anchor, same order: invoice first, then payment. Writes a `subscriptions` row distinguished by `path_id`, so activation, entitlement, enrolment materialisation and revenue split all follow it unchanged.',
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('path_id'),
            'properties' => array(
                'path_id'    => array('type' => 'integer', 'example' => 204),
                'pay_method' => array('type' => 'string', 'enum' => array('tap', 'manual')),
            )),
    ))),
    'responses' => array('201' => array('description' => 'Created'),
                         '401' => $r_401, '403' => $r_403, '409' => $err_ref(
                             'Not purchasable, or already owned.',
                             array('message' => 'هذا المسار مفتوح لك بالفعل.', 'code' => 'purchase_failed')),
                         '422' => $r_422, '429' => $r_429),
)),

'/api/v1/student/store/courses' => array('get' => array(
    'tags' => array('Store'),
    'summary' => 'Courses sold individually',
    'description' => implode("\n", array(
        'Courses a student can buy on their own, without a plan.',
        '',
        '**With the feature switch off, this list is empty** — `tq_course_sales_enabled` is off by',
        'default, and then every course reports "not for sale". Nothing changes for anyone until an',
        'administrator turns it on, which is the same rule Tap and session pricing follow.',
        '',
        '"Sold individually" is a **declared attribute** (`tq_sell`), not a consequence of having a',
        'price. Inferring it from `price > 0` would turn every course carrying a price from an old',
        'import into a listed product the moment the door opened.',
    )),
    'security' => $auth,
    'parameters' => array_merge($page_params, array(
        array('name' => 'q', 'in' => 'query', 'required' => false, 'schema' => array('type' => 'string')),
    )),
    'responses' => array('200' => array('description' => 'OK'),
                         '401' => $r_401, '403' => $r_403, '429' => $r_429),
)),

'/api/v1/student/store/courses/{id}' => array('get' => array(
    'tags' => array('Store'),
    'summary' => 'One course offer',
    'description' => implode("\n", array(
        'The individual offer, and under it the plans that also open this course.',
        '',
        'Each plan carries `extra_over_course` — **the difference, not the plan\'s price.** "And for',
        'N riyals more you open the whole stage" compares what is comparable; two prices side by side',
        'with no bridge leave the buyer weighing two options without knowing what the extra buys.',
        '',
        '`access_days: 0` with `lifetime: true` means permanent access: `ends_at` stays null and the',
        'expiry job never touches it. An invented far-off date would one day arrive and close',
        'something sold as permanent.',
        '',
        '`sellable: false` always comes with a `reason` — `disabled`, `free`, `not_marked`,',
        '`unpublished`, `unpriced`, `no_teacher`, `empty`. One blanket refusal makes the button vanish',
        'with no readable cause.',
    )),
    'security' => $auth,
    'parameters' => array(array('name' => 'id', 'in' => 'path', 'required' => true,
                                'schema' => array('type' => 'integer'))),
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                'course_id' => 12, 'title' => 'الرياضيات — الصف الرابع', 'summary' => '',
                'level' => '', 'teacher' => 'أ. سارة', 'thumbnail' => null,
                'sellable' => true, 'reason' => 'ok', 'why' => 'معروض للبيع المفرد.',
                'owned' => false,
                'price' => array('amount' => 19900, 'decimal' => '199.00',
                                 'currency' => 'SAR', 'formatted' => '199.00 ر.س'),
                'list_price' => null, 'discount' => 0,
                'access_days' => 180, 'lifetime' => false,
                'web_url' => $TQ_API_BASE . '/course-checkout/12',
                'plans' => array(),
            ),
            'message' => '', 'meta' => new stdClass(),
        )))),
        '401' => $r_401, '403' => $r_403, '404' => $r_404, '429' => $r_429,
    ),
)),

'/api/v1/student/buy-course' => array('post' => array(
    'tags' => array('Store'),
    'summary' => 'Buy a single course',
    'description' => implode("\n", array(
        'Invoice first, then payment — the same order as every other purchase, and the same reply',
        'shape as `POST /student/subscribe`.',
        '',
        '**It neither blocks a plan nor is blocked by one.** A plan blocks a plan because they are the',
        'same thing bought twice; a single course is another thing — a student with their grade\'s plan',
        'who buys an enrichment subject on top has bought two things, not the same thing twice. Only',
        'buying the *same course* again is refused, and that check runs against the very function that',
        'guards the player: it is never sold to someone who already has it, and the screen never',
        'promises what the guard would refuse.',
        '',
        'No diagnostic gate here. The placement test guards plans because a plan opens a whole stage',
        'and the test says which stage fits; a single subject is chosen by name, so a test in its path',
        'would be a question with no answer to give.',
        '',
        'Price, access period and the teacher\'s percentage are all **frozen at purchase**: editing the',
        'price today changes neither what was sold yesterday nor what was credited to a teacher.',
    )),
    'security' => $auth,
    'requestBody' => array('required' => true, 'content' => array('application/json' => array(
        'schema' => array('type' => 'object', 'required' => array('course_id'),
            'properties' => array(
                'course_id'  => array('type' => 'integer', 'example' => 12),
                'pay_method' => array('type' => 'string', 'enum' => array('tap', 'manual')),
            )),
    ))),
    'responses' => array(
        '201' => array('description' => 'Created'),
        '401' => $r_401, '403' => $r_403,
        '409' => $err_ref('`not_sellable` (see `reason` on the offer) or `already_owned`.',
                          array('message' => 'هذا الكورس مفتوح لك بالفعل.', 'code' => 'already_owned')),
        '422' => $r_422, '429' => $r_429,
    ),
)),

'/api/v1/student/purchases' => array('get' => array(
    'tags' => array('Store'),
    'summary' => 'Everything currently in force',
    'description' => implode("\n", array(
        'Every live entitlement: plans, single paths and single courses alike.',
        '',
        'This is **not** the same question as `GET /student/subscription`. That one answers "what is',
        'this student\'s *plan*?" — it returns one row and deliberately skips single purchases,',
        'because eleven callers ask it that one question. This one answers "what do they own?".',
        '',
        'A student with their grade\'s plan who bought a subject on top holds two rows. Reading only',
        'one would mean one of the two purchases never appears to the person who paid for it.',
        '',
        '`lifetime: true` with `days_left: null` means permanent — a single course sold with a zero',
        'access period. Null is "forever", not "unknown".',
    )),
    'security' => $auth,
    'responses' => array(
        '200' => array('description' => 'OK', 'content' => array('application/json' => array('example' => array(
            'data' => array(
                array('id' => 918, 'kind' => 'plan', 'ref_id' => 31, 'code' => 'g4-full',
                      'title' => 'باقة الصف الرابع', 'status' => 'active',
                      'price' => array('amount' => 4200, 'decimal' => '42.00',
                                       'currency' => 'SAR', 'formatted' => '42.00 ر.س'),
                      'cycle' => 'monthly', 'started_at' => '2026-08-01T00:00:00+03:00',
                      'ends_at' => '2026-08-31T00:00:00+03:00', 'days_left' => 9, 'lifetime' => false),
                array('id' => 921, 'kind' => 'course', 'ref_id' => 12, 'code' => null,
                      'title' => 'الرياضيات — الصف الرابع', 'status' => 'active',
                      'price' => array('amount' => 19900, 'decimal' => '199.00',
                                       'currency' => 'SAR', 'formatted' => '199.00 ر.س'),
                      'cycle' => null, 'started_at' => '2026-08-12T00:00:00+03:00',
                      'ends_at' => null, 'days_left' => null, 'lifetime' => true),
            ),
            'message' => '', 'meta' => array('count' => 2),
        )))),
        '401' => $r_401, '403' => $r_403, '429' => $r_429,
    ),
)),

),  // paths
);
