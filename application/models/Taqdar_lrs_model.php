<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مستودع سجلات التعلم الوطني (NELC LRS) — رحلة المتعلم بمعيار xAPI.
 *
 * ═══ لماذا وجد ═══
 *
 * اعتماد المركز الوطني للتعلم الإلكتروني يشترط تكاملا تقنيا: ترسل
 * المنصة ما يفعله المتعلم — تسجيلا وبدءا ومشاهدة واختبارا وإتماما
 * وشهادة — الى مستودع الجهة، فتقرأ عندها لا عندنا. والمنصة التي لا
 * ترسل لا تدخل المؤشر الوطني مهما كان محتواها.
 *
 * ═══ من اين جاء العقد ═══
 *
 * لا من التخمين: قرئت حزمتا NELC المفتوحتان (لارافيل ومودل) وحزمة
 * `msaaq`، وحيث اختلفن اتبعت NELC. وخمسة مزالق يقع فيها من يخمن، وكلها
 * مكتوبة هنا حيث تقرأ:
 *
 *   ١ — **رقم الهوية الوطنية يوضع في `actor.name`** لا في `account`.
 *       و`mbox` البريد. ولا `homePage` في العقد اصلا.
 *   ٢ — **`context.platform` معرف قصير**، منفصل تماما عن اسم المنصة
 *       ثنائي اللغة في `extensions`. ووضع الاسم فيه هو الخطأ الشائع.
 *   ٣ — **`contextActivities.parent` مصفوفة** فيها كائن واحد، لا كائنا
 *       مفردا (وهو ما يخالف فيه `msaaq` المعيار نفسه).
 *   ٤ — **`attempt-id` نص** `"1"` لا عددا.
 *   ٥ — **`object.id` رابط** لا معرفا رقميا.
 *
 * ولا يرسل: لا `id` للرسالة، ولا `context.registration`، ولا
 * `authority`، ولا `result.extensions`. و`verb.display` بالانجليزية
 * وحدها حتى في الرسائل العربية.
 *
 * ═══ ولماذا طابور، والحزم الثلاث بلا طابور ═══
 *
 * لان الثلاث ترسل من داخل الطلب وتنسى ما فشل. ودرس يشاهده طالب في
 * لحظة انقطاع شبكة يضيع من سجل الجهة الى الابد، ولا شيء يقول انه ضاع.
 * فالرسالة تكتب اولا في `tq_xapi_queue` — والكتابة في قاعدتنا لا تفشل
 * لان مستودعا بعيدا لا يرد — ثم يسحبها الكرون ويعيد المحاولة.
 *
 * وهذا ما يجعل الحجب الحالي (المستودع خلف Cloudflare، والخادم خارج
 * قائمته البيضاء) عائقا مؤجلا لا فقدانا: تتراكم الرسائل بحالتها، فيوم
 * يفتح تتدفق كلها بترتيبها بلا تدخل.
 *
 * ═══ وعدم التكرار مبني في الجدول لا في النية ═══
 *
 * `fingerprint` مفتاح فريد. فمن نادى الالتقاط مرتين — كرون يمر مرتين،
 * او مستخدم يحدث الصفحة — يكتب صفا واحدا. والرسالة عند NELC بلا معرف
 * يرسله المرسل، فلا سبيل لكشف التكرار عندهم؛ فالمنع يقع هنا.
 */
class Taqdar_lrs_model extends CI_Model
{
    /** نسخة المعيار — ترويسة `X-Experience-API-Version`. */
    const XAPI_VERSION = '1.0.3';

    /** خمس محاولات ثم تموت — كما في `Taqdar_events_model::drain()`. */
    const MAX_ATTEMPTS = 5;

    private $cfg = null;
    private $schema_checked = false;

    /* =====================================================================
       العقد — الافعال والانواع والامتدادات، حرفا بحرف
       ===================================================================== */

    /**
     * الافعال الثمانية.
     *
     * لاحظ اختلاف المخطط (`http` مقابل `https`) بين النطاقات: منقول كما
     * هو من شيفرة NELC، ولا يوحد — الفعل معرف لا رابط يزار، وحرف واحد
     * فيه يجعله فعلا اخر عند المستودع.
     */
    private static function verbs()
    {
        return array(
            'registered'  => 'http://adlnet.gov/expapi/verbs/registered',
            'initialized' => 'http://adlnet.gov/expapi/verbs/initialized',
            'watched'     => 'https://w3id.org/xapi/acrossx/verbs/watched',
            'attempted'   => 'http://adlnet.gov/expapi/verbs/attempted',
            'completed'   => 'http://adlnet.gov/expapi/verbs/completed',
            'progressed'  => 'http://adlnet.gov/expapi/verbs/progressed',
            'rated'       => 'http://id.tincanapi.com/verb/rated',
            'earned'      => 'http://id.tincanapi.com/verb/earned',
        );
    }

    const T_COURSE = 'https://w3id.org/xapi/cmi5/activitytype/course';
    const T_LESSON = 'http://adlnet.gov/expapi/activities/lesson';
    const T_MODULE = 'http://adlnet.gov/expapi/activities/module';
    const T_VIDEO  = 'https://w3id.org/xapi/video/activity-type/video';
    const T_TEST   = 'http://id.tincanapi.com/activitytype/unit-test';
    const T_CERT   = 'https://www.opigno.org/en/tincan_registry/activity_type/certificate';

    const X_PLATFORM    = 'https://nelc.gov.sa/extensions/platform';
    const X_DURATION    = 'https://nelc.gov.sa/extensions/duration';
    const X_LMS_URL     = 'https://nelc.gov.sa/extensions/lms_url';
    const X_PROGRAM_URL = 'https://nelc.gov.sa/extensions/program_url';
    const X_MOBILE      = 'https://nelc.gov.sa/extensions/learner_mobile_no';
    const X_FULL_NAME   = 'https://nelc.gov.sa/extensions/learner_full_name';
    const X_NATIONALITY = 'https://nelc.gov.sa/extensions/learner_nationality';
    const X_DOB         = 'https://nelc.gov.sa/extensions/date_of_birth';
    const X_BROWSER     = 'http://id.tincanapi.com/extension/browser-info';
    const X_ATTEMPT_ID  = 'http://id.tincanapi.com/extension/attempt-id';
    const X_CERT_URL    = 'http://id.tincanapi.com/extension/jws-certificate-location';

    /* =====================================================================
       الاعدادات
       ===================================================================== */

    private static function boot_keys()
    {
        return array('tq_lrs_enabled', 'tq_lrs_endpoint', 'tq_lrs_user', 'tq_lrs_pass',
                     'tq_lrs_platform', 'tq_lrs_name_ar', 'tq_lrs_name_en',
                     'tq_lrs_lang', 'tq_lrs_lms_url', 'tq_lrs_course_url');
    }

    public function config()
    {
        if ($this->cfg !== null) return $this->cfg;

        $v = array();
        try {
            $rows = $this->db->select('key, value')->where_in('key', self::boot_keys())
                             ->get('settings')->result_array();
            foreach ($rows as $r) $v[$r['key']] = (string) $r['value'];
        } catch (Throwable $e) {
            log_message('error', 'TQ-LRS: تعذر قراءة الإعدادات — ' . $e->getMessage());
        }

        $g = function ($k, $d = '') use ($v) {
            return isset($v[$k]) ? trim((string) $v[$k]) : $d;
        };

        $lang = $g('tq_lrs_lang', 'ar-SA');
        if ($lang !== 'ar-SA' && $lang !== 'en-US') $lang = 'ar-SA';

        $this->cfg = array(
            'enabled'  => $g('tq_lrs_enabled') === '1',
            'endpoint' => $g('tq_lrs_endpoint'),
            'user'     => $g('tq_lrs_user'),
            'pass'     => $g('tq_lrs_pass'),
            /* معرف المنصة — يذهب الى `context.platform` وحده. */
            'platform' => $g('tq_lrs_platform'),
            'name_ar'  => $g('tq_lrs_name_ar', 'منصة تقدر'),
            'name_en'  => $g('tq_lrs_name_en', 'Taqdar'),
            'lang'     => $lang,

            /* TQ-LRS-URL — عنوان المنصة **إعداد لا اشتقاق**.

               `base_url()` تبنى في `config.php` من `$_SERVER['SCRIPT_NAME']`،
               وفي سطر الأوامر يكون مسار ملف الكرون — فتخرج الروابط
               `https://taqdaredu.com/home/taqdaredu.com/path/…`. والكرون هو
               ما يرسل معظم الرسائل، فكان كل `object.id` يذهب الى الجهة
               بعنوان لا يفتح. والخطأ صامت: الرسالة تقبل، والرابط يكسر. */
            'lms_url'  => rtrim($g('tq_lrs_lms_url', 'https://taqdaredu.com'), '/') . '/',

            /* TQ-LRS-JOURNEY — شكل `object.id` للمقرر.

               نموذج التحقق عند الجهة يعبئ `{platform}/course/CR001`
               تلقائيا، ونص حقله يقول «استخدم المعرف نفسه (كامل الرابط)»
               — أي أن الشكل حر ما دام ثابتا. والافتراض عندنا `path`
               لان `‎/path/<السبيكة>‎` صفحة حقيقية تفتح وتعرض البرنامج،
               بينما `‎/course/<رقم>‎` يرد ٢٠٠ على أي شيء ويعرض قالبا
               فارغا — ورابط في سجل وطني يحسن ان يفتح على شيء.

               والمفتاح موجود لان الجهة قد تشترط شكلها، فيبدل بلا نشر. */
            'course_url' => ($g('tq_lrs_course_url', 'path') === 'course') ? 'course' : 'path',
        );
        return $this->cfg;
    }

    /**
     * رابط مطلق من مسار — أو يمرر المطلق كما هو.
     *
     * فنقاط الالتقاط تمرر مسارا (`path/alryadyat-21`) ولا تعرف اين تعمل:
     * الشيفرة نفسها تنادى من طلب ومن كرون، والنتيجة واحدة.
     */
    public function url($path)
    {
        $p = trim((string) $path);
        if ($p === '') return '';
        if (preg_match('~^https?://~i', $p)) return $p;
        return $this->config()['lms_url'] . ltrim($p, '/');
    }

    /** ينسى المحفوظ — تنادى بعد الحفظ في شاشة الإعدادات. */
    public function forget()
    {
        $this->cfg = null;
    }

    /**
     * أيمكن الإرسال فعلا؟
     *
     * أربعة لا واحد. و`enabled` وحده لا يعني شيئا — كما ان `smtp_host`
     * وحده لا يعني ان البريد يرسل.
     */
    public function configured()
    {
        $c = $this->config();
        return $c['enabled'] && $c['endpoint'] !== '' && $c['user'] !== '' && $c['pass'] !== '';
    }

    /** اسم ثان — يقرأ في مواضع القرار. */
    public function ready()
    {
        return $this->configured();
    }

    /** ما ينقص، بعبارة تقرأ في الشاشة لا في السجل. */
    public function missing()
    {
        $c   = $this->config();
        $out = array();
        if (!$c['enabled'])         $out[] = 'التفعيل';
        if ($c['endpoint'] === '')  $out[] = 'عنوان المستودع';
        if ($c['user'] === '')      $out[] = 'اسم المستخدم';
        if ($c['pass'] === '')      $out[] = 'كلمة المرور';
        if ($c['platform'] === '')  $out[] = 'معرف المنصة';
        return $out;
    }

    /* =====================================================================
       المخطط — ينشأ وقت التشغيل، كما `tq_wa_log` و`tq_notify_queue`
       ===================================================================== */

    public function ensure_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;
        $this->ensure_columns();

        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_xapi_queue` (
                    `id`          int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `user_id`     int(10) unsigned NOT NULL DEFAULT 0,
                    `verb`        varchar(24)  NOT NULL DEFAULT "",
                    `source`      varchar(64)  NOT NULL DEFAULT "",
                    `fingerprint` char(40)     NOT NULL,
                    `statement`   longtext     NOT NULL,
                    `state`       varchar(16)  NOT NULL DEFAULT "queued",
                    `attempts`    int(11)      NOT NULL DEFAULT 0,
                    `http_code`   int(11)      NOT NULL DEFAULT 0,
                    `last_error`  varchar(250) DEFAULT NULL,
                    `next_try_at` int(11)      NOT NULL DEFAULT 0,
                    `created_at`  int(11)      NOT NULL DEFAULT 0,
                    `sent_at`     int(11)      DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_fingerprint` (`fingerprint`),
                    KEY `ix_due` (`state`,`next_try_at`),
                    KEY `ix_user` (`user_id`,`verb`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-LRS: تعذر إنشاء tq_xapi_queue — ' . $e->getMessage());
        }
    }

    /**
     * الأعمدة التي يحتاجها الربط في جداول قائمة — تضاف دفاعيا.
     *
     * لا هجرات في هذا المستودع، والبديل ملف SQL يستورد بيد وينسى؛ فيسقط
     * الالتقاط عند من نشر ولم يستورد. وهو نمط `Taqdar_book_model` نفسه.
     *
     * · `users.national_id` — الهوية الوطنية، اختيارية.
     * · `xapi_sent_at` على مصادر الأحداث — **شبكة الأمان**: نظير
     *   `notified_at` في `Taqdar_diag_model` حرفا. فالالتقاط المتزامن قد
     *   يفوت (نشر وسط طلب، خطأ عابر)، والكانسة تلتقط ما فات بسؤال واحد:
     *   «أي صف تم ولم يرسل؟».
     */
    public function ensure_columns()
    {
        $add = array(
            'users'            => array('national_id',
                "ADD COLUMN `national_id` varchar(20) DEFAULT NULL COMMENT 'national id / iqama — TQ-LRS'"),
            'attempts'         => array('xapi_sent_at', "ADD COLUMN `xapi_sent_at` datetime DEFAULT NULL"),
            'tq_diag_attempts' => array('xapi_sent_at', "ADD COLUMN `xapi_sent_at` datetime DEFAULT NULL"),
            'lesson_progress'  => array('xapi_sent_at', "ADD COLUMN `xapi_sent_at` datetime DEFAULT NULL"),
            'subscriptions'    => array('xapi_sent_at', "ADD COLUMN `xapi_sent_at` datetime DEFAULT NULL"),
        );

        foreach ($add as $table => $spec) {
            try {
                if ($this->db->field_exists($spec[0], $table)) continue;
                $this->db->query('ALTER TABLE `' . $table . '` ' . $spec[1]);
            } catch (Throwable $e) {
                $this->db->reset_query();
                log_message('error', 'TQ-LRS column ' . $table . '.' . $spec[0] . ': ' . $e->getMessage());
            }
        }
        return true;
    }

    /* =====================================================================
       TQ-LRS-CTX — السياق: يقرأ مرة هنا لا في كل نقطة التقاط
       ===================================================================== */

    /**
     * المتعلم كما تحتاجه الرسالة.
     *
     * وتقرأ الهوية ان كان العمود موجودا: نقطة التقاط تنادى قبل ان يضاف
     * العمود ترمي «Unknown column» وسط درس طالب.
     */
    public function learner($user_id)
    {
        $uid = (int) $user_id;
        if ($uid <= 0) return null;
        try {
            $has_nid = $this->db->field_exists('national_id', 'users');
            $cols = 'id, email, first_name, last_name, phone' . ($has_nid ? ', national_id' : '');
            $u = $this->db->select($cols)->where('id', $uid)->limit(1)->get('users')->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
        if (!$u || trim((string) $u['email']) === '') return null;

        $u['full_name'] = trim(trim((string) $u['first_name']) . ' ' . trim((string) $u['last_name']));
        if (!isset($u['national_id'])) $u['national_id'] = '';
        return $u;
    }

    /**
     * المقرر كما تحتاجه الرسالة — من **المسار** لا من صف `course`.
     *
     * لان `object.id` رابط يفتح، والرابط العام للمادة هو `path/<slug>`؛
     * وصف `course` لا صفحة عامة له. والمعلم يقرأ من المسار كذلك، فهو
     * صاحب البرنامج لا صاحب الصف في LMS الموروث.
     */
    public function course_ctx($course_id)
    {
        $cid = (int) $course_id;
        if ($cid <= 0) return null;
        try {
            $p = $this->db->select('p.id, p.title, p.slug, p.short_description, p.expected_weeks,
                                    u.first_name, u.last_name, u.email', false)
                          ->from('paths p')
                          ->join('users u', 'u.id = p.teacher_id', 'left')
                          ->where('p.course_id', $cid)->limit(1)->get()->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
        if (!$p) return null;

        $t = trim(trim((string) $p['first_name']) . ' ' . trim((string) $p['last_name']));
        return array(
            'course' => array(
                'url'   => $this->course_path($p, $cid),
                'title' => (string) $p['title'],
                'desc'  => (string) $p['short_description'],
            ),
            'instructor' => $t !== '' ? array('name' => $t, 'email' => (string) $p['email']) : null,
            'weeks' => (int) $p['expected_weeks'],
        );
    }

    /** مسار المقرر بحسب الشكل المختار — والمعرف هو ما يقرأ في الحالين. */
    private function course_path($p, $course_id = 0)
    {
        /* ومعرّف **المقرّر** لا المسار: `‎/course/<رقم>‎` مسار LMS الموروث،
           ورقم المسار فيه يشير الى غير ما يظنّ قارئه. */
        return ($this->config()['course_url'] === 'course')
             ? 'course/' . (int) ($course_id ?: $p['id'])
             : 'path/' . (string) $p['slug'];
    }

    /**
     * وسم المتصفح من الطلب — ويرد فارغا في سطر الأوامر.
     *
     * و`code_name` هو النظام لا عائلة المتصفح: هكذا تكتبها حزمة NELC
     * (`Interactions/Browser.php`)، وحزمة `msaaq` تكتب العائلة. والاثنتان
     * تقبلان، فيتبع صاحب المستودع.
     */
    public function browser()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        if ($ua === '') return null;

        $os = 'Other';
        foreach (array('Windows' => 'Windows', 'Macintosh' => 'Mac', 'Android' => 'Android',
                       'iPhone' => 'iOS', 'iPad' => 'iOS', 'Linux' => 'Linux') as $needle => $name) {
            if (stripos($ua, $needle) !== false) { $os = $name; break; }
        }

        $name = 'Other'; $ver = '';
        foreach (array('Edg' => 'Microsoft Edge', 'OPR' => 'Opera', 'Chrome' => 'Google Chrome',
                       'Safari' => 'Safari', 'Firefox' => 'Mozilla Firefox') as $needle => $label) {
            if (stripos($ua, $needle) !== false) {
                $name = $label;
                if (preg_match('~' . preg_quote($needle, '~') . '/([0-9.]+)~i', $ua, $m)) $ver = $m[1];
                break;
            }
        }
        return array('os' => $os, 'name' => $name, 'version' => $ver);
    }

    /** مدة ISO-8601 من ثوان — `PT1H2M3S`، وصفر ثانية `PT0S`. */
    public static function iso_duration($seconds)
    {
        $s = max(0, (int) $seconds);
        if ($s === 0) return 'PT0S';
        $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60); $x = $s % 60;
        return 'PT' . ($h ? $h . 'H' : '') . ($m ? $m . 'M' : '') . ($x || (!$h && !$m) ? $x . 'S' : '');
    }

    /* =====================================================================
       اللبنات
       ===================================================================== */

    /**
     * الفاعل.
     *
     * `actor.name` رقم الهوية الوطنية لا الاسم — هكذا العقد، ووضع اسم
     * المتعلم فيه يجعل الجهة تقرأ اسما حيث تنتظر رقما.
     *
     * **ومن لا هوية له يحذف المفتاح** ولا يعوض بشيء: `mbox` وحده معرف
     * صالح في المعيار، ورقم مخترع او معرف داخلي في خانة الهوية الوطنية
     * يلوث بيانات الجهة — والتلويث اسوأ من النقص، لانه لا يقرأ نقصا.
     */
    private function actor($u)
    {
        $a = array('mbox' => 'mailto:' . trim((string) $u['email']));

        $nid = isset($u['national_id']) ? preg_replace('/\D/', '', (string) $u['national_id']) : '';
        if ($nid !== '') $a['name'] = $nid;

        $a['objectType'] = 'Agent';
        return $a;
    }

    /** نشاط: رابطه واسمه ووصفه ونوعه. والوصف يكتب دائما ولو فارغا (كما NELC). */
    private function activity($id, $name, $desc, $type, $lang)
    {
        return array(
            'id' => (string) $id,
            'definition' => array(
                'name'        => array($lang => (string) $name),
                'description' => array($lang => (string) $desc),
                'type'        => $type,
            ),
            'objectType' => 'Activity',
        );
    }

    /** امتداد اسم المنصة — في كل رسالة بلا استثناء، وباللغتين دائما. */
    private function platform_ext()
    {
        $c = $this->config();
        return array('name' => array('ar-SA' => $c['name_ar'], 'en-US' => $c['name_en']));
    }

    /**
     * الطابع الزمني.
     *
     * الخادم على UTC (تحقق منه)، فالحرف `Z` يصدق. وNELC تكتبها بوقت
     * الخادم المحلي وتلحق `Z` — وهو خطأ يظهر على خادم بازاحة، فلا ينسخ.
     */
    private function now()
    {
        return gmdate('Y-m-d\TH:i:s') . '.' . substr(sprintf('%03d', (int) (microtime(true) * 1000) % 1000), 0, 3) . 'Z';
    }

    /** المعلم — `name` اسمه و`mbox` بريده، وبلا `objectType` (كما العقد). */
    private function instructor($t)
    {
        if (!$t) return null;
        $n = trim((string) (isset($t['name']) ? $t['name'] : ''));
        $e = trim((string) (isset($t['email']) ? $t['email'] : ''));
        if ($n === '' && $e === '') return null;
        return array('name' => $n, 'mbox' => $e !== '' ? 'mailto:' . $e : '');
    }

    /** الاب في `contextActivities` — **مصفوفة** فيها واحد. */
    private function parent_of($course, $lang)
    {
        return array('parent' => array(
            $this->activity($this->url($course['url']), $course['title'], '', self::T_COURSE, $lang),
        ));
    }

    /* =====================================================================
       بناء الرسائل
       ===================================================================== */

    /**
     * الهيكل المشترك — الفاعل والفعل والموضوع والسياق والطابع.
     *
     * `$ctx` يزاد عليه لا يستبدل: `platform` و`language` وامتداد اسم
     * المنصة تكتب هنا مرة، فلا تنسى في رسالة ولا تكتب ثمان مرات.
     */
    private function base($verb, $u, $object, $ctx = array())
    {
        $c = $this->config();
        $lang = isset($ctx['__lang']) ? $ctx['__lang'] : $c['lang'];
        unset($ctx['__lang']);

        $ts_keep = isset($ctx['__ts']) ? $ctx['__ts'] : null;
        unset($ctx['__ts']);

        $verbs = self::verbs();
        $ext   = isset($ctx['extensions']) ? $ctx['extensions'] : array();
        $ext[self::X_PLATFORM] = $this->platform_ext();

        $context = array(
            'platform'   => $c['platform'],
            'language'   => $lang,
            'extensions' => $ext,
        );
        foreach ($ctx as $k => $v) {
            if ($k === 'extensions') continue;
            if ($v === null) continue;
            $context[$k] = $v;
        }

        /* الطابع طابع **الحدث** لا لحظة الإرسال: الكانسة تلتقط درسا أتم
           أمس، ورسالة تقول «الآن» تزيح رحلة المتعلم عند الجهة يوما. */
        $out = array(
            'actor' => $this->actor($u),
            'verb'  => array('id' => $verbs[$verb], 'display' => array('en-US' => $verb)),
            'object' => $object,
            'context' => $context,
            'timestamp' => $ts_keep !== null ? $ts_keep : $this->now(),
        );
        return $out;
    }

    /**
     * بناء رسالة من حدث.
     *
     * مدخل واحد لثمانية افعال: نقاط الالتقاط تنادي `emit()` باسم الحدث
     * ومصفوفة، ولا تعرف شكل xAPI ولا تحمله. فتغير العقد يوما يقع هنا
     * وحده، لا في ثماني نقاط في الشيفرة.
     */
    public function build($event, $a)
    {
        $c    = $this->config();
        $lang = isset($a['lang']) ? $a['lang'] : $c['lang'];
        $u    = $a['user'];
        $ins  = $this->instructor(isset($a['instructor']) ? $a['instructor'] : null);
        $crs  = isset($a['course']) ? $a['course'] : null;

        switch ($event) {

            /* التسجيل في مقرر — الرسالة الوحيدة التي تحمل بيانات المتعلم. */
            case 'registered':
                return $this->base('registered', $u,
                    $this->activity($this->url($crs['url']), $crs['title'], isset($crs['desc']) ? $crs['desc'] : '',
                                    self::T_COURSE, $lang),
                    array('__lang' => $lang, '__ts' => $this->ts($a), 'instructor' => $ins, 'extensions' => array(
                        self::X_DURATION    => (string) (isset($a['duration']) ? $a['duration'] : ''),
                        self::X_LMS_URL     => $c['lms_url'],
                        self::X_PROGRAM_URL => $this->url($crs['url']),
                        self::X_MOBILE      => (string) (isset($a['mobile']) ? $a['mobile'] : ''),
                        self::X_FULL_NAME   => (string) (isset($a['full_name']) ? $a['full_name'] : ''),
                        self::X_NATIONALITY => (string) (isset($a['nationality']) ? $a['nationality'] : ''),
                        self::X_DOB         => (string) (isset($a['dob']) ? $a['dob'] : ''),
                    )));

            /* بدء المقرر. */
            case 'initialized':
                return $this->base('initialized', $u,
                    $this->activity($this->url($crs['url']), $crs['title'], '', self::T_COURSE, $lang),
                    array('__lang' => $lang, '__ts' => $this->ts($a), 'instructor' => $ins));

            /* مشاهدة فيديو الدرس. */
            case 'watched':
                $s = $this->base('watched', $u,
                    $this->activity($this->url($a['lesson']['url']), $a['lesson']['title'],
                                    isset($a['lesson']['desc']) ? $a['lesson']['desc'] : '',
                                    self::T_VIDEO, $lang),
                    array('__lang' => $lang, '__ts' => $this->ts($a), 'instructor' => $ins,
                          'contextActivities' => $this->parent_of($crs, $lang),
                          'extensions' => $this->browser_ext($a)));
                $s['result'] = array(
                    'completion' => (bool) (isset($a['completed']) ? $a['completed'] : false),
                    'duration'   => (string) (isset($a['duration']) ? $a['duration'] : 'PT0S'),
                );
                return $s;

            /* محاولة اختبار. */
            case 'attempted':
                $ext = $this->browser_ext($a);
                /* نصا لا عددا — هكذا NELC، و`msaaq` تخالف. */
                $ext[self::X_ATTEMPT_ID] = (string) (int) (isset($a['attempt_no']) ? $a['attempt_no'] : 1);
                $s = $this->base('attempted', $u,
                    $this->activity($this->url($a['quiz']['url']), $a['quiz']['title'],
                                    isset($a['quiz']['desc']) ? $a['quiz']['desc'] : '',
                                    self::T_TEST, $lang),
                    array('__lang' => $lang, '__ts' => $this->ts($a), 'instructor' => $ins,
                          'contextActivities' => $this->parent_of($crs, $lang),
                          'extensions' => $ext));
                $min = (float) (isset($a['min']) ? $a['min'] : 0);
                $max = (float) (isset($a['max']) ? $a['max'] : 100);
                $raw = (float) (isset($a['raw']) ? $a['raw'] : 0);
                $s['result'] = array(
                    'score' => array(
                        'scaled' => $max > $min ? round(($raw - $min) / ($max - $min), 4) : 0,
                        'raw'    => $raw, 'min' => $min, 'max' => $max,
                    ),
                    'completion' => true,
                    'success'    => (bool) (isset($a['passed']) ? $a['passed'] : false),
                );
                return $s;

            /* اتمام درس. */
            case 'completed_lesson':
                $s = $this->base('completed', $u,
                    $this->activity($this->url($a['lesson']['url']), $a['lesson']['title'],
                                    isset($a['lesson']['desc']) ? $a['lesson']['desc'] : '',
                                    self::T_LESSON, $lang),
                    array('__lang' => $lang, '__ts' => $this->ts($a), 'instructor' => $ins,
                          'contextActivities' => $this->parent_of($crs, $lang),
                          'extensions' => $this->browser_ext($a)));
                $s['result'] = array('duration' => (string) (isset($a['duration']) ? $a['duration'] : 'PT15M0S'));
                return $s;

            /* اتمام وحدة — بلا `result`. */
            case 'completed_unit':
                return $this->base('completed', $u,
                    $this->activity($this->url($a['unit']['url']), $a['unit']['title'],
                                    isset($a['unit']['desc']) ? $a['unit']['desc'] : '',
                                    self::T_MODULE, $lang),
                    array('__lang' => $lang, '__ts' => $this->ts($a), 'instructor' => $ins,
                          'contextActivities' => $this->parent_of($crs, $lang),
                          'extensions' => $this->browser_ext($a)));

            /* اتمام مقرر — بلا متصفح ولا اب ولا نتيجة. */
            case 'completed_course':
                return $this->base('completed', $u,
                    $this->activity($this->url($crs['url']), $crs['title'], '', self::T_COURSE, $lang),
                    array('__lang' => $lang, '__ts' => $this->ts($a), 'instructor' => $ins));

            /* التقدم — `scaled` وحدها بلا `raw`. */
            case 'progressed':
                $s = $this->base('progressed', $u,
                    $this->activity($this->url($crs['url']), $crs['title'], '', self::T_COURSE, $lang),
                    array('__lang' => $lang, '__ts' => $this->ts($a), 'instructor' => $ins));
                $p = max(0, min(100, (int) $a['percent']));
                $s['result'] = array(
                    'score'      => array('scaled' => round($p / 100, 4)),
                    'completion' => $p >= 100,
                );
                return $s;

            /* التقييم — النطاق ٠–٥ مثبت عند NELC. */
            case 'rated':
                $s = $this->base('rated', $u,
                    $this->activity($this->url($crs['url']), $crs['title'], '', self::T_COURSE, $lang),
                    array('__lang' => $lang, '__ts' => $this->ts($a), 'instructor' => $ins));
                $stars = max(0, min(5, (float) $a['stars']));
                $s['result'] = array(
                    'score'    => array('scaled' => round($stars / 5, 4), 'raw' => $stars,
                                        'min' => 0, 'max' => 5),
                    'response' => (string) (isset($a['comment']) ? $a['comment'] : ''),
                );
                return $s;

            /* الشهادة — بلا وصف للنشاط وبلا معلم (كما العقد). */
            case 'earned':
                $obj = array(
                    'id' => $this->url($a['certificate']['url']),
                    'definition' => array(
                        'name' => array($lang => (string) $a['certificate']['title']),
                        'type' => self::T_CERT,
                    ),
                    'objectType' => 'Activity',
                );
                return $this->base('earned', $u, $obj, array(
                    '__lang' => $lang, '__ts' => $this->ts($a),
                    'contextActivities' => $this->parent_of($crs, $lang),
                    'extensions' => array(
                        self::X_CERT_URL => $this->url($a['certificate']['file_url']),
                    ),
                ));
        }
        return null;
    }

    /**
     * وسم المتصفح.
     *
     * ويقرأ من الطلب ان كان في طلب، ويسكت في الكرون: خانات فارغة اصدق
     * من نسبة زيارة الى متصفح لا وجود له.
     */
    private function browser_ext($a)
    {
        if (isset($a['browser']) && is_array($a['browser'])) {
            return array(self::X_BROWSER => array(
                'code_name' => (string) (isset($a['browser']['os']) ? $a['browser']['os'] : ''),
                'name'      => (string) (isset($a['browser']['name']) ? $a['browser']['name'] : ''),
                'version'   => (string) (isset($a['browser']['version']) ? $a['browser']['version'] : ''),
            ));
        }
        return array();
    }

    /* =====================================================================
       الطابور
       ===================================================================== */

    /**
     * يبني الرسالة ويضعها في الطابور. لا يرسل، ولا يرمي.
     *
     * `$source` يقول من اين جاءت (`attempts:41`)، و`$key` هو ما يميزها
     * في بصمتها. والبصمة فريدة في الجدول، فالنداء الثاني يسقط صامتا —
     * وهو المطلوب: عدم التكرار قيد لا نية.
     */
    public function emit($event, $args, $source = '', $key = '')
    {
        try {
            $c = $this->config();
            if (!$c['enabled']) return false;

            $st = $this->build($event, $args);
            if (!$st) return false;

            $this->ensure_schema();

            $uid  = (int) (isset($args['user']['id']) ? $args['user']['id'] : 0);
            $fp   = sha1($event . '|' . $source . '|' . $key . '|' . $uid);
            $verb = mb_substr($event, 0, 24);

            $this->db->query(
                'INSERT IGNORE INTO `tq_xapi_queue`
                 (`user_id`,`verb`,`source`,`fingerprint`,`statement`,`state`,`next_try_at`,`created_at`)
                 VALUES (?, ?, ?, ?, ?, "queued", ?, ?)',
                array($uid, $verb, mb_substr((string) $source, 0, 64), $fp,
                      json_encode($st, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                      time(), time()));

            /* **`INSERT IGNORE` لا يخطئ حين يسقط الصفّ المكرّر** — يمرّ
               صامتا. فالرد يقرأ من `affected_rows()` لا من عدم الرمي،
               والا قالت الشاشة «كتبت عشرا» وهي لم تكتب واحدة. */
            return ((int) $this->db->affected_rows() > 0);
        } catch (Throwable $e) {
            /* لا ترمي ابدا: رسالة لا تكتب تخسر سطرا في سجل الجهة، ورمي
               هنا يكسر درسا او اختبارا على طالب. */
            log_message('error', 'TQ-LRS emit(' . $event . '): ' . $e->getMessage());
            return false;
        }
    }

    /* =====================================================================
       الارسال
       ===================================================================== */

    /** يرسل رسالة واحدة. لا تجميع — العقد رسالة لكل طلب. */
    public function post($statement_json)
    {
        $c = $this->config();
        if ($c['endpoint'] === '' || $c['user'] === '') {
            return array('ok' => false, 'code' => 0, 'systemic' => true, 'error' => 'الربط غير مهيأ.');
        }
        if (!function_exists('curl_init')) {
            return array('ok' => false, 'code' => 0, 'systemic' => true,
                         'error' => 'إضافة cURL غير مثبتة على هذا الخادم.');
        }

        $ch = curl_init($c['endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $statement_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . base64_encode($c['user'] . ':' . $c['pass']),
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Experience-API-Version: ' . self::XAPI_VERSION,
        ));

        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return array('ok' => false, 'code' => 0, 'systemic' => true,
                         'error' => ($cerr ?: 'تعذر الاتصال بالمستودع.'), 'body' => '');
        }

        /* النجاح `200` وحده — هكذا تحكم حزم NELC الثلاث. و`204` لا يرد
           منه، فلا يقبل تخمينا. */
        if ($code !== 200) {
            /* «علة الباب» — لا تخص هذه الرسالة بعينها. */
            $systemic = ($code === 401 || $code === 403 || $code === 404
                      || $code === 408 || $code === 429 || $code >= 500);
            return array('ok' => false, 'code' => $code, 'systemic' => $systemic,
                         'error' => $this->post_error($code, (string) $raw),
                         'body' => (string) $raw);
        }
        return array('ok' => true, 'code' => $code, 'systemic' => false,
                     'error' => '', 'body' => (string) $raw);
    }

    /**
     * العلة بعبارة تقرأ.
     *
     * والمستودع يرد HTML عند الحجب لا JSON، فطباعة جسمه خاما في السجل
     * تملؤه بصفحة كاملة. فالحالات المعروفة تسمى، وما عداها يقص.
     */
    private function post_error($code, $raw)
    {
        if ($code === 401) return 'رفض الاعتماد (401) — راجع اسم المستخدم وكلمة المرور.';
        if ($code === 403) {
            if (stripos($raw, 'cloudflare') !== false) {
                return 'محجوب قبل بلوغ المستودع (403 من Cloudflare) — '
                     . 'عنوان الخادم ليس في القائمة البيضاء عند الجهة.';
            }
            return 'ممنوع (403) — الحساب لا يملك الكتابة في هذا المستودع.';
        }
        if ($code === 400) return 'رفضت الرسالة (400) — ' . mb_substr(strip_tags($raw), 0, 180);
        if ($code === 404) return 'العنوان غير موجود (404) — راجع مسار `/xapi/statements`.';
        if ($code >= 500)  return 'عطل في المستودع (' . $code . ') — يعاد لاحقا.';
        return 'رد غير متوقع (' . $code . ') — ' . mb_substr(strip_tags($raw), 0, 180);
    }

    /**
     * يسحب الطابور.
     *
     * والتراجع نفسه الذي في `Taqdar_events_model::drain()` حرفا: خمس
     * محاولات، `attempts² × 60` ثانية، ثم `dead`.
     *
     * **و`skipped` لا `dead` حين لا تهيئة**: قناة موقوفة تعيد المحاولة
     * خمسا ثم تموت تقرأ عطبا لا وجود له.
     */
    public function drain($limit = 60)
    {
        $out = array('sent' => 0, 'failed' => 0, 'dead' => 0, 'skipped' => 0, 'held' => 0);

        try {
            $this->ensure_schema();
            $rows = $this->db->where('state', 'queued')
                             ->where('next_try_at <=', time())
                             ->order_by('next_try_at', 'ASC')->order_by('id', 'ASC')
                             ->limit(max(1, min(500, (int) $limit)))
                             ->get('tq_xapi_queue')->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-LRS drain: ' . $e->getMessage());
            return $out;
        }

        if (!$rows) return $out;

        if (!$this->configured()) {
            foreach ($rows as $r) {
                $this->db->where('id', (int) $r['id'])->update('tq_xapi_queue', array(
                    'state'      => 'skipped',
                    'last_error' => mb_substr('الربط غير مهيأ: ' . implode(' · ', $this->missing()), 0, 250)));
                $out['skipped']++;
            }
            return $out;
        }

        foreach ($rows as $r) {
            $id  = (int) $r['id'];
            $res = $this->post((string) $r['statement']);
            $n   = (int) $r['attempts'] + 1;

            /* TQ-LRS-HELD — **علة الباب لا تحسب على الرسالة.**

               لو حسبت، لماتت الرسائل كلها في خمس جولات (٢٥ دقيقة) ما دام
               المستودع محجوبا — والحجب حال مؤقتة تنتظر قائمة بيضاء، لا
               رسالة فاسدة. فما كان من جنس «لا يبلغ التطبيق» (انقطاع ·
               ٤٠١ · ٤٠٣ · ٤٢٩ · ٥xx) يعلق: يكتب سببه ويؤجل خمس دقائق
               **بلا زيادة محاولة**، ويوقف الجولة — فلا معنى لطرق باب
               مغلق مئتي مرة.

               وما رفضته الجهة نفسها (٤٠٠ وأخواته) يحسب ويموت عند الخامسة:
               رسالة لا تقبل اليوم لا تقبل بعد ساعة. */
            if (!empty($res['systemic'])) {
                $this->db->where('id', $id)->update('tq_xapi_queue', array(
                    'http_code'   => (int) $res['code'],
                    'next_try_at' => time() + 300,
                    'last_error'  => mb_substr((string) $res['error'], 0, 250)));
                $out['held']++;
                break;
            }

            if (!empty($res['ok'])) {
                $this->db->where('id', $id)->update('tq_xapi_queue', array(
                    'state' => 'sent', 'attempts' => $n, 'http_code' => (int) $res['code'],
                    'sent_at' => time(), 'last_error' => null));
                $out['sent']++;
                continue;
            }

            $err = mb_substr((string) $res['error'], 0, 250);

            if ($n >= self::MAX_ATTEMPTS) {
                $this->db->where('id', $id)->update('tq_xapi_queue', array(
                    'state' => 'dead', 'attempts' => $n,
                    'http_code' => (int) $res['code'], 'last_error' => $err));
                $out['dead']++;
                continue;
            }

            $this->db->where('id', $id)->update('tq_xapi_queue', array(
                'attempts' => $n, 'http_code' => (int) $res['code'],
                'next_try_at' => time() + ($n * $n * 60), 'last_error' => $err));
            $out['failed']++;
        }

        return $out;
    }

    /** يعيد ما مات الى الطابور — بعد فتح الحجب مثلا. */
    public function revive($states = array('dead', 'skipped'))
    {
        try {
            $this->ensure_schema();
            $this->db->where_in('state', $states)->update('tq_xapi_queue', array(
                'state' => 'queued', 'attempts' => 0, 'next_try_at' => time(), 'last_error' => null));
            return (int) $this->db->affected_rows();
        } catch (Throwable $e) {
            log_message('error', 'TQ-LRS revive: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * طابع الحدث من مصفوفة الوسائط — `Y-m-d H:i:s` من القاعدة الى ISO.
     *
     * والقاعدة تخزن بلا منطقة، والخادم على UTC، فالقراءة مباشرة.
     */
    private function ts($a)
    {
        $t = isset($a['at']) ? trim((string) $a['at']) : '';
        if ($t === '') return null;
        $u = strtotime($t);
        if (!$u) return null;
        return gmdate('Y-m-d\TH:i:s', $u) . '.000Z';
    }

    /* =====================================================================
       TQ-LRS-SWEEP — الكانسة
       =====================================================================

       ولماذا كانسة لا نداء داخل الطلب؟

       لان مواضع الالتقاط الثمانية تقع في أحرّ مسارات المنصة: تسليم
       اختبار، وحفظ تقدم درس، وتفعيل اشتراك بعد دفع. وسطر يضاف الى
       `Taqdar_billing_model::activate()` يعني ان عطبا في الربط يقع على
       طالب دفع للتو. والاشتقاق من الجداول يعطي الشيء نفسه بعد دقائق
       خمس، بلا سطر واحد في مسار دفع.

       وكل مصدر يوسم بـ`xapi_sent_at` بعد كنسه — فالجولة الثانية لا تعيده،
       والبصمة الفريدة في الطابور تمسك ما فلت. حارسان لا واحد. */

    public function sweep($limit = 200)
    {
        $out = array('registered' => 0, 'attempted' => 0, 'earned' => 0,
                     'lesson' => 0, 'course' => 0, 'rated' => 0);
        if (!$this->config()['enabled']) return $out;

        try { $this->ensure_schema(); } catch (Throwable $e) { return $out; }

        $this->sweep_subscriptions($limit, $out);
        $this->sweep_attempts($limit, $out);
        $this->sweep_lessons($limit, $out);
        $this->sweep_ratings($limit, $out);
        return $out;
    }

    /**
     * التسجيل — رسالة لكل مقرر يفتحه الاشتراك.
     *
     * والمقررات تشتق من `subscription_items` لا من `plans.scope_ids`:
     * الاول نسخة جمدت وقت التفعيل، وهو ما يفتح فعلا لهذا المشترك؛
     * والثاني قد يكون تغير بعده.
     */
    private function sweep_subscriptions($limit, &$out)
    {
        try {
            $subs = $this->db->select('id, user_id, created_at, started_at')
                             ->where('status', 'active')
                             ->where('xapi_sent_at IS NULL', null, false)
                             ->order_by('id', 'ASC')->limit((int) $limit)
                             ->get('subscriptions')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); $this->sweep_err('subscriptions', $e); return; }

        foreach ($subs as $sb) {
            $u = $this->learner((int) $sb['user_id']);
            if (!$u) { $this->mark('subscriptions', (int) $sb['id']); continue; }

            $at = trim((string) (!empty($sb['started_at']) ? $sb['started_at'] : $sb['created_at']));

            foreach ($this->courses_of_subscription((int) $sb['id']) as $cid) {
                $ctx = $this->course_ctx($cid);
                if (!$ctx) continue;
                $args = array(
                    'user' => $u, 'course' => $ctx['course'], 'instructor' => $ctx['instructor'],
                    'at' => $at,
                    'duration'    => $ctx['weeks'] > 0 ? 'PT' . ($ctx['weeks'] * 5) . 'H' : '',
                    'mobile'      => (string) $u['phone'],
                    'full_name'   => (string) $u['full_name'],
                    'nationality' => '',
                    'dob'         => '',
                );
                if ($this->emit('registered', $args, 'subscriptions:' . (int) $sb['id'], 'course:' . $cid)) {
                    $out['registered']++;
                }
            }
            $this->mark('subscriptions', (int) $sb['id']);
        }
    }

    /** معرفات المقررات التي يفتحها اشتراك — من بنوده المجمدة. */
    private function courses_of_subscription($sid)
    {
        $ids = array();
        try {
            $items = $this->db->select('entity_type, entity_id')
                              ->where('subscription_id', (int) $sid)
                              ->get('subscription_items')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return $ids; }

        foreach ($items as $it) {
            $t = (string) $it['entity_type'];
            $v = (int) $it['entity_id'];
            try {
                if ($t === 'grade') {
                    $rows = $this->db->select('course_id')->from('paths')
                                     ->where('grade_id', $v)->where('status', 'published')
                                     ->where('course_id >', 0)->get()->result_array();
                    foreach ($rows as $r) $ids[] = (int) $r['course_id'];
                } elseif ($t === 'path') {
                    $c = (int) $this->db->select('course_id')->where('id', $v)
                                        ->get('paths')->row('course_id');
                    if ($c > 0) $ids[] = $c;
                } elseif ($t === 'course') {
                    if ($v > 0) $ids[] = $v;
                } elseif ($t === 'all') {
                    $rows = $this->db->select('course_id')->from('paths')
                                     ->where('status', 'published')->where('course_id >', 0)
                                     ->get()->result_array();
                    foreach ($rows as $r) $ids[] = (int) $r['course_id'];
                }
            } catch (Throwable $e) { $this->db->reset_query(); }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * المحاولات — `attempted` لكل مسلمة، و`earned` لامتحان نجح فيه.
     *
     * والشهادة مشتقة لا مخزنة في هذه المنصة: رمزها `TQ-000057` وصفحتها
     * `‎/certificate/57‎`. فالرسالة تشير الى ما يفتح فعلا.
     */
    private function sweep_attempts($limit, &$out)
    {
        try {
            $rows = $this->db->select('a.id, a.student_id, a.score, a.passed, a.attempt_no,
                                       a.submitted_at, s.type, s.lesson_id, s.path_id, s.pass_mark', false)
                             ->from('attempts a')
                             ->join('assessments s', 's.id = a.assessment_id', 'inner')
                             ->where('a.submitted_at IS NOT NULL', null, false)
                             ->where('a.xapi_sent_at IS NULL', null, false)
                             ->order_by('a.id', 'ASC')->limit((int) $limit)
                             ->get()->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); $this->sweep_err('attempts', $e); return; }

        foreach ($rows as $r) {
            $aid = (int) $r['id'];
            $u   = $this->learner((int) $r['student_id']);
            $cid = $this->course_of_assessment($r);
            $ctx = $cid > 0 ? $this->course_ctx($cid) : null;

            if (!$u || !$ctx) { $this->mark('attempts', $aid); continue; }

            $is_exam = ((string) $r['type'] === 'exam');
            $title   = $is_exam ? 'اختبار ' . $ctx['course']['title'] : 'تقويم درس';

            $args = array(
                'user' => $u, 'course' => $ctx['course'], 'instructor' => $ctx['instructor'],
                'at' => (string) $r['submitted_at'],
                'quiz' => array('url' => 'student/attempt/' . $aid, 'title' => $title, 'desc' => ''),
                'attempt_no' => (int) $r['attempt_no'],
                'raw' => (float) $r['score'], 'min' => 0, 'max' => 100,
                'passed' => ((int) $r['passed'] === 1),
            );
            if ($this->emit('attempted', $args, 'attempts:' . $aid, '')) $out['attempted']++;

            if ($is_exam && (int) $r['passed'] === 1) {
                $cert = array(
                    'url'      => 'certificate/' . $aid,
                    'title'    => 'شهادة إتمام ' . $ctx['course']['title'],
                    'file_url' => 'certificate/' . $aid,
                );
                $e2 = array('user' => $u, 'course' => $ctx['course'], 'certificate' => $cert,
                            'at' => (string) $r['submitted_at']);
                if ($this->emit('earned', $e2, 'attempts:' . $aid, 'cert')) $out['earned']++;
            }

            $this->mark('attempts', $aid);
        }
    }

    /** مقرر التقويم: من درسه ان كان درسا، ومن مساره ان كان امتحانا. */
    private function course_of_assessment($r)
    {
        try {
            if ((int) $r['lesson_id'] > 0) {
                return (int) $this->db->select('course_id')->where('id', (int) $r['lesson_id'])
                                      ->get('lesson')->row('course_id');
            }
            if ((int) $r['path_id'] > 0) {
                return (int) $this->db->select('course_id')->where('id', (int) $r['path_id'])
                                      ->get('paths')->row('course_id');
            }
        } catch (Throwable $e) { $this->db->reset_query(); }
        return 0;
    }

    /**
     * الدروس المتمة — أربع رسائل ممكنة لكل درس.
     *
     * `initialized` لأول درس في مقرره، ثم `watched` ان كان فيه فيديو،
     * ثم `completed` للدرس، ثم `progressed` عند عتبة عشر، و`completed`
     * للمقرر عند المئة. والبصمة تمنع تكرار الثلاثة الأخيرة.
     */
    private function sweep_lessons($limit, &$out)
    {
        try {
            $rows = $this->db->select('p.id, p.student_id, p.lesson_id, p.watch_seconds,
                                       p.completed_at, l.title, l.course_id, l.duration_sec,
                                       l.video_url, l.summary', false)
                             ->from('lesson_progress p')
                             ->join('lesson l', 'l.id = p.lesson_id', 'inner')
                             ->where('p.completed_at IS NOT NULL', null, false)
                             ->where('p.xapi_sent_at IS NULL', null, false)
                             ->order_by('p.completed_at', 'ASC')->limit((int) $limit)
                             ->get()->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); $this->sweep_err('lessons', $e); return; }

        foreach ($rows as $r) {
            $pid = (int) $r['id'];
            $sid = (int) $r['student_id'];
            $cid = (int) $r['course_id'];
            $u   = $this->learner($sid);
            $ctx = $cid > 0 ? $this->course_ctx($cid) : null;

            if (!$u || !$ctx) { $this->mark('lesson_progress', $pid); continue; }

            $at   = (string) $r['completed_at'];
            $base = array('user' => $u, 'course' => $ctx['course'],
                          'instructor' => $ctx['instructor'], 'at' => $at);
            $lesson = array(
                'url'   => 'student/lesson/' . $cid . '/' . (int) $r['lesson_id'],
                'title' => (string) $r['title'],
                'desc'  => mb_substr(trim(strip_tags((string) $r['summary'])), 0, 200),
            );

            /* بدء المقرر — البصمة تجعلها تكتب مرة واحدة مهما كثر الدروس. */
            $this->emit('initialized', $base, 'course:' . $cid, 'student:' . $sid);

            if (trim((string) $r['video_url']) !== '') {
                $w = $base;
                $w['lesson']    = $lesson;
                $w['completed'] = true;
                $w['duration']  = self::iso_duration((int) $r['watch_seconds']);
                if ($this->emit('watched', $w, 'lesson_progress:' . $pid, '')) $out['lesson']++;
            }

            $c = $base;
            $c['lesson']   = $lesson;
            $c['duration'] = self::iso_duration((int) ($r['duration_sec'] ?: $r['watch_seconds']));
            $this->emit('completed_lesson', $c, 'lesson_progress:' . $pid, '');

            $this->mark('lesson_progress', $pid);

            /* التقدم والاتمام يحسبان بعد الوسم، فالنسبة تشمل هذا الدرس. */
            $pct = $this->course_percent($sid, $cid);
            if ($pct > 0) {
                $bucket = intdiv($pct, 10) * 10;
                $g = $base;
                $g['percent'] = $bucket;
                $this->emit('progressed', $g, 'course:' . $cid,
                            'student:' . $sid . ':b' . $bucket);
            }
            if ($pct >= 100) {
                if ($this->emit('completed_course', $base, 'course:' . $cid, 'student:' . $sid)) {
                    $out['course']++;
                }
            }
        }
    }

    /** نسبة اتمام مقرر لطالب — دروس أتمها على دروسه المنشورة. */
    private function course_percent($student_id, $course_id)
    {
        try {
            $all = (int) $this->db->where('course_id', (int) $course_id)
                                  ->count_all_results('lesson');
            if ($all < 1) return 0;
            $done = (int) $this->db->from('lesson_progress p')
                                   ->join('lesson l', 'l.id = p.lesson_id', 'inner')
                                   ->where('p.student_id', (int) $student_id)
                                   ->where('l.course_id', (int) $course_id)
                                   ->where('p.completed_at IS NOT NULL', null, false)
                                   ->count_all_results();
            return (int) floor($done * 100 / $all);
        } catch (Throwable $e) {
            $this->db->reset_query();
            return 0;
        }
    }

    /**
     * التقييمات.
     *
     * ولا عمود وسم على `rating`: الجدول موروث ولا يمس. فالبصمة وحدها
     * تمنع التكرار، والمسح يقصر على شهر — فلا يفحص الجدول كله كل خمس
     * دقائق ولا يفوته جديد.
     */
    private function sweep_ratings($limit, &$out)
    {
        try {
            $rows = $this->db->select('id, user_id, rating, ratable_id, ratable_type, review, date_added')
                             ->where('date_added >=', date('Y-m-d H:i:s', time() - 30 * 86400))
                             ->order_by('id', 'DESC')->limit((int) $limit)
                             ->get('rating')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); $this->sweep_err('ratings', $e); return; }

        foreach ($rows as $r) {
            $u   = $this->learner((int) $r['user_id']);
            $ctx = $this->course_ctx((int) $r['ratable_id']);
            if (!$u || !$ctx) continue;

            $args = array('user' => $u, 'course' => $ctx['course'],
                          'instructor' => $ctx['instructor'], 'at' => (string) $r['date_added'],
                          'stars' => (float) $r['rating'],
                          'comment' => mb_substr(trim(strip_tags((string) $r['review'])), 0, 400));
            if ($this->emit('rated', $args, 'rating:' . (int) $r['id'], '')) $out['rated']++;
        }
    }

    /**
     * علة كنس تسجل ولا ترمى.
     *
     * وهذه الدالة وجدت من عطب وقع: استعلام الاشتراكات كان يسأل عن
     * `date_added` و`start_date` ولا وجود لهما (الاسمان `created_at`
     * و`started_at`)، فكان `catch` يبتلع الخطأ ويرد صامتا — فتمر الكنسة
     * تقول «تم» ولا رسالة تسجيل واحدة تكتب. الصمت هو ما اخفاه، لا الخطأ.
     */
    private function sweep_err($what, $e)
    {
        log_message('error', 'TQ-LRS sweep(' . $what . '): ' . $e->getMessage());
    }

    /* =====================================================================
       TQ-LRS-JOURNEY — رحلة التحقّق
       =====================================================================

       نموذج «تشغيل اختبار التحقق» عند الجهة يأخذ **رقم هوية ومعرف دورة**
       ثم يبحث في مستودعه عن رحلة ذلك المتعلم في تلك الدورة. وشاشة
       المساعدة عندهم تعرض الرحلة عشر رسائل بترتيبها:

         registered · initialized · watched · completed(درس) · attempted ·
         completed(وحدة) · progressed · completed(مقرر) · rated · earned

       والكانسة لا تكفي لهذا: هي تشتق ما وقع فعلا، وطالب لم يقيّم مقرره
       لا `rated` له، ومن لم يبلغ المئة لا `completed(مقرر)` له. فالرحلة
       تولّد العشرة عمدا على متعلم ودورة تختارهما — لتشغيل الاختبار لا
       لتزوير سجل: الأسماء والدروس والوحدات تقرأ من القاعدة كما هي، ولا
       يخترع إلا ما لا وجود له اصلا.

       والبصمة في مجالها (`journey:{متعلم}:{دورة}`) فإعادة التوليد لا
       تضاعف. و`$force` يمحو ويعيد — لمن بدّل شكل معرف الدورة مثلا. */

    public function journey($user_id, $course_id, $force = false)
    {
        $out = array('ok' => false, 'written' => 0, 'error' => '',
                     'actor' => '', 'object' => '', 'source' => '');

        $uid = (int) $user_id;
        $cid = (int) $course_id;
        $u   = $this->learner($uid);
        $ctx = $this->course_ctx($cid);

        if (!$u)   { $out['error'] = 'لا متعلم بهذا المعرف، أو لا بريد له.'; return $out; }
        if (!$ctx) { $out['error'] = 'لا مقرر بهذا المعرف — أو لا مسار منشور يقابله.'; return $out; }

        $src = 'journey:' . $uid . ':' . $cid;
        $out['source'] = $src;
        $out['actor']  = (string) $u['national_id'];
        $out['object'] = $this->url($ctx['course']['url']);

        try { $this->ensure_schema(); } catch (Throwable $e) {}

        if ($force) {
            try { $this->db->where('source', $src)->delete('tq_xapi_queue'); }
            catch (Throwable $e) { $this->db->reset_query(); }
        }

        /* الدرس والوحدة من المقرر نفسه — وما خلا منهما يوصف بالمقرر:
           رسالة تشير الى درس لا وجود له اسوأ من رسالة تشير الى مقرره. */
        $lesson = $this->first_lesson($cid);
        $unit   = $this->first_section($cid);

        $L = $lesson ? array(
                'url'   => ($this->config()['course_url'] === 'course')
                         ? 'course/' . $cid . '/' . (int) $lesson['id']
                         : 'student/lesson/' . $cid . '/' . (int) $lesson['id'],
                'title' => (string) $lesson['title'],
                'desc'  => mb_substr(trim(strip_tags((string) $lesson['summary'])), 0, 200),
            ) : array('url' => $ctx['course']['url'], 'title' => $ctx['course']['title'], 'desc' => '');

        $U = $unit ? array(
                'url'   => $ctx['course']['url'] . '#unit-' . (int) $unit['id'],
                'title' => (string) $unit['title'],
                'desc'  => '',
            ) : array('url' => $ctx['course']['url'] . '#unit-1',
                      'title' => $ctx['course']['title'], 'desc' => '');

        $base = array('user' => $u, 'course' => $ctx['course'], 'instructor' => $ctx['instructor']);

        /* الطوابع تتدرج في الساعة الماضية لا تتساوى: عشر رسائل بطابع
           واحد تقرأ عند الجهة لحظة واحدة لا رحلة. */
        $t0 = time() - 3600;
        $at = function ($i) use ($t0) { return date('Y-m-d H:i:s', $t0 + $i * 300); };

        $steps = array(
            array('registered', array_merge($base, array(
                'at' => $at(0),
                'duration'    => $ctx['weeks'] > 0 ? 'PT' . ($ctx['weeks'] * 5) . 'H' : 'PT10H',
                'mobile'      => (string) $u['phone'],
                'full_name'   => (string) $u['full_name'],
                'nationality' => '', 'dob' => ''))),

            array('initialized', array_merge($base, array('at' => $at(1)))),

            array('watched', array_merge($base, array(
                'at' => $at(2), 'lesson' => $L,
                'completed' => true, 'duration' => 'PT12M30S'))),

            array('completed_lesson', array_merge($base, array(
                'at' => $at(3), 'lesson' => $L, 'duration' => 'PT15M0S'))),

            array('attempted', array_merge($base, array(
                'at' => $at(4),
                'quiz' => array('url' => $L['url'] . '#quiz',
                                'title' => 'تقويم ' . $L['title'], 'desc' => ''),
                'attempt_no' => 1, 'raw' => 90, 'min' => 0, 'max' => 100, 'passed' => true))),

            array('completed_unit', array_merge($base, array('at' => $at(5), 'unit' => $U))),

            array('progressed', array_merge($base, array('at' => $at(6), 'percent' => 100))),

            array('completed_course', array_merge($base, array('at' => $at(7)))),

            array('rated', array_merge($base, array(
                'at' => $at(8), 'stars' => 5, 'comment' => 'محتوى واضح ومرتب.'))),

            array('earned', array(
                'user' => $u, 'course' => $ctx['course'], 'at' => $at(9),
                'certificate' => array(
                    'url'      => 'certificate/' . $uid . '-' . $cid,
                    'title'    => 'شهادة إتمام ' . $ctx['course']['title'],
                    'file_url' => 'certificate/' . $uid . '-' . $cid))),
        );

        $n = 0;
        foreach ($steps as $i => $st) {
            /* المفتاح رقم الخطوة لا اسم الفعل: ثلاث رسائل «completed»
               في الرحلة، واسم الفعل وحده يجعل الثانية تسقط بالبصمة. */
            if ($this->emit($st[0], $st[1], $src, 'step:' . ($i + 1))) $n++;
        }

        $out['ok'] = true;
        $out['written'] = $n;
        return $out;
    }

    /** أول درس في المقرر — بترتيبه لا بمعرفه. */
    private function first_lesson($course_id)
    {
        try {
            return $this->db->select('id, title, summary')
                            ->where('course_id', (int) $course_id)
                            ->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                            ->limit(1)->get('lesson')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return null; }
    }

    /** أول وحدة (قسم) في المقرر. */
    private function first_section($course_id)
    {
        try {
            return $this->db->select('id, title')
                            ->where('course_id', (int) $course_id)
                            ->order_by('`order`', 'ASC', false)->order_by('id', 'ASC')
                            ->limit(1)->get('section')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return null; }
    }

    /** المقرّرات المنشورة للاختيار — معرّف المقرّر لا المسار. */
    public function course_choices()
    {
        try {
            return $this->db->select('p.course_id, p.title, g.name_ar AS grade', false)
                            ->from('paths p')->join('grades g', 'g.id = p.grade_id', 'left')
                            ->where('p.status', 'published')->where('p.course_id >', 0)
                            ->order_by('g.`order`', 'ASC', false)->order_by('p.title', 'ASC')
                            ->get()->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    /** حال رحلة بعينها — تقرؤه الشاشة بترتيب الخطوات. */
    public function journey_state($user_id, $course_id)
    {
        $src = 'journey:' . (int) $user_id . ':' . (int) $course_id;
        try {
            $this->ensure_schema();
            return $this->db->select('id, verb, state, attempts, http_code, last_error, sent_at')
                            ->where('source', $src)->order_by('id', 'ASC')
                            ->get('tq_xapi_queue')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    /** يوسم صف المصدر بأنه كنس — الحارس الأول، والبصمة هي الثاني. */
    private function mark($table, $id)
    {
        try {
            $this->db->where('id', (int) $id)
                     ->update($table, array('xapi_sent_at' => date('Y-m-d H:i:s')));
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-LRS mark ' . $table . ':' . $id . ' — ' . $e->getMessage());
        }
    }

    /**
     * آخر ما في الطابور — لشاشة اللوحة.
     *
     * ولا يعرض جسم الرسالة: فيه بريد المتعلم وهويته، وشاشة تعرض مئتي
     * رسالة تعرض مئتي هوية بلا حاجة. والعمود المفيد هو ما وقع لها.
     */
    public function recent($limit = 20)
    {
        try {
            $this->ensure_schema();
            return $this->db->select('id, user_id, verb, source, state, attempts,
                                      http_code, last_error, created_at, sent_at')
                            ->order_by('id', 'DESC')->limit(max(1, min(100, (int) $limit)))
                            ->get('tq_xapi_queue')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
    }

    /** إحصاء الطابور — تقرؤه اللوحة فلا يبقى صندوقا مغلقا. */
    public function queue_stats()
    {
        $out = array('queued' => 0, 'sent' => 0, 'failed' => 0, 'dead' => 0, 'skipped' => 0, 'total' => 0);
        try {
            $this->ensure_schema();
            foreach ($this->db->select('state, COUNT(*) n', false)
                              ->group_by('state')->get('tq_xapi_queue')->result_array() as $r) {
                $out[(string) $r['state']] = (int) $r['n'];
                $out['total'] += (int) $r['n'];
            }
        } catch (Throwable $e) {
            log_message('error', 'TQ-LRS stats: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * فحص الاتصال — يقرأ ولا يكتب.
     *
     * يسأل `/about` وهو مدخل التعريف في المعيار، فلا يترك اثرا في سجل
     * الجهة. ورسالة تجريبية تكتب في بيانات اعتمادهم صفا لا يمحى.
     */
    public function diagnose()
    {
        $c = $this->config();
        $out = array('ok' => false, 'code' => 0, 'note' => '', 'endpoint' => $c['endpoint']);

        if (!$this->configured()) {
            $out['note'] = 'الربط غير مهيأ: ' . implode(' · ', $this->missing());
            return $out;
        }

        $about = preg_replace('~/statements/?$~', '/about', $c['endpoint']);
        $ch = curl_init($about);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ' . base64_encode($c['user'] . ':' . $c['pass']),
            'X-Experience-API-Version: ' . self::XAPI_VERSION,
            'Accept: application/json',
        ));
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        $out['code'] = $code;
        if ($raw === false) { $out['note'] = $cerr ?: 'تعذر الاتصال.'; return $out; }
        if ($code === 200)  { $out['ok'] = true; $out['note'] = 'المستودع يرد.'; return $out; }
        $out['note'] = $this->post_error($code, (string) $raw);
        return $out;
    }
}
