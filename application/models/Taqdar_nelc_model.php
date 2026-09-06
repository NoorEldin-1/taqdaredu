<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * التكامل مع المركز الوطني للتعليم الإلكتروني (NELC) — xAPI.
 *
 * **الموضع الوحيد الذي يبني جملة xAPI أو يرسلها.**
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ما هذا ولماذا
 * ═══════════════════════════════════════════════════════════════════════
 *
 * NELC هو الجهة التي ترخص منصات التعليم الإلكتروني في السعودية، وشرط
 * الترخيص أن تبلغه المنصة نشاط متعلميها بمعيار **xAPI**: كل تسجيل في
 * كورس، وكل درس يفتح، وكل مشاهدة تكتمل، وكل محاولة تسلم — «جملة»
 * (statement) بصيغة JSON ترسل إلى مخزن سجلات التعلم (LRS) عندهم.
 *
 * والبيئة التي تسلم أولا هي بيئة **الترخيص التجريبية**
 * (`lrs-license-stg`): يربط عليها، ويراجع المركز ما يصله، ثم يعطي بيانات
 * الإنتاج. فالنطاق كله في `settings` لا في الشيفرة — لأن الانتقال من
 * التجريبي إلى الحي يقع بتبديل سطر في شاشة، لا بنشر.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ثلاث قواعد تحكم كل ما يكتب هنا
 * ═══════════════════════════════════════════════════════════════════════
 *
 * ١ — **بلا مفاتيح لا شيء يتغير.** `ready()` كاذبة حتى تضبط الشاشة،
 *     وحينها لا يكتب صف ولا يخرج طلب، وتعمل المنصة كما كانت حرفا بحرف.
 *     وهي قاعدة تاب نفسها وقاعدة واتساب نفسها.
 *
 * ٢ — **لا يرسل من مسار الطلب.** الحدث يكتب صفا في `tq_nelc_queue`،
 *     والكرون يفرغه. ونبضة تقدم الطالب لا تنتظر خادما حكوميا يرد: عشرون
 *     ثانية مهلة اتصال في مسار يرد كل خمس عشرة ثانية تعني شاشة تتجمد.
 *     وأهم منه أن الإرسال **يفشل ويعاد**: طلب يخرج من مسار الطلب ويسقط
 *     لا أحد يعيده، فيضيع الحدث ولا يعرف أحد.
 *
 * ٣ — **فشل الإبلاغ لا يبطل ما أبلغ عنه.** الطالب أتم درسه سواء وصلت
 *     الجملة أو لم تصل. فكل نداء من مسار حي ملفوف، والاستثناء يسجل ولا
 *     يصعد. وهي قاعدة `push_notification()` نفسها.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * رقم الهوية — الشرط الذي لا بديل عنه
 * ═══════════════════════════════════════════════════════════════════════
 *
 * NELC يعرف المتعلم برقم هويته الوطنية، لا ببريده ولا بمعرفه عندنا:
 *
 *     "actor": {"mbox": "mailto:…", "name": "<رقم الهوية>", "objectType": "Agent"}
 *
 * ولم يكن في `users` عمود له، فأضيف `tq_national_id` **اختياريا**: من
 * كتبه ترسل جمله، ومن لم يكتبه **لا يرسل عنه شيء أصلا**. والبديل — أن
 * يرسل معرف الحساب مكان الهوية — يملأ مخزن المركز بمعرفات ليست هويات،
 * وهو أسوأ من الفراغ: بيانات صحيحة قليلة تراجع، وبيانات مخترعة كثيرة
 * ترد المراجعة كلها. وعمود «التغطية» في الشاشة يقول كم بقي.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * التكرار — حارسان لا واحد
 * ═══════════════════════════════════════════════════════════════════════
 *
 * الحدث يقع مرة ويجب أن يعد مرة، وبين المنصة والمركز شبكة تسقط وكرون
 * يعيد المحاولة. فحارسان:
 *
 *   ــ **عندنا**: `event_key` فريد في الجدول. الحدث نفسه لا يدخل الطابور
 *      مرتين مهما نودي مرتين — و`get_lesson()` تنادى في كل فتح للدرس.
 *   ــ **عندهم**: `id` الجملة مشتق من `event_key` اشتقاقا ثابتا (UUID).
 *      فإعادة إرسال ما وصل ترد `409` وهي عندنا **نجاح**: الجملة هناك.
 *
 * وبلا الثاني كان انقطاع الشبكة بعد أن يستقبل المركز الجملة وقبل أن يصلنا
 * رده يجعل الكرون يعيدها، فيعد الدرس الواحد مرتين في تقرير الترخيص.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * المواصفة
 * ═══════════════════════════════════════════════════════════════════════
 *
 * الأفعال الثمانية ومعرفاتها وامتدادات `nelc.gov.sa` مأخوذة من الحزمتين
 * المعلنتين للتكامل (حزمة NELC لـLaravel، وSDK مساق) — لا تخمن ولا تشتق:
 * معرف فعل بحرف زائد يقبل في الشبكة ويسقط من تقرير المركز بلا خطأ يظهر.
 */
class Taqdar_nelc_model extends CI_Model
{
    /** نسخة المعيار — ترويسة إلزامية في كل طلب. */
    const XAPI_VERSION = '1.0.3';

    /** إصدار المخطط. رفعه يعيد `install_schema()` على قاعدة قائمة. */
    const SCHEMA_V = 1;

    /** النطاق التجريبي للترخيص — وهو ما يسلم أولا. */
    const DEFAULT_ENDPOINT = 'https://lrs.nelc.gov.sa/lrs-license-stg/xapi/statements';

    /** نطاق الإنتاج — يكتب في الشاشة حين يعطيه المركز. */
    const LIVE_ENDPOINT = 'https://lrs.nelc.gov.sa/lrs-nelc/xapi/statements';

    /* ---------------------------------------------------------------- */
    /* الأفعال                                                           */
    /* ---------------------------------------------------------------- */

    const V_REGISTERED  = 'http://adlnet.gov/expapi/verbs/registered';
    const V_INITIALIZED = 'http://adlnet.gov/expapi/verbs/initialized';
    const V_WATCHED     = 'https://w3id.org/xapi/acrossx/verbs/watched';
    const V_COMPLETED   = 'http://adlnet.gov/expapi/verbs/completed';
    const V_PROGRESSED  = 'http://adlnet.gov/expapi/verbs/progressed';
    const V_ATTEMPTED   = 'http://adlnet.gov/expapi/verbs/attempted';
    const V_EARNED      = 'http://id.tincanapi.com/verb/earned';
    const V_RATED       = 'http://id.tincanapi.com/verb/rated';

    /* ---------------------------------------------------------------- */
    /* أنواع النشاط                                                      */
    /* ---------------------------------------------------------------- */

    const A_COURSE      = 'https://w3id.org/xapi/cmi5/activitytype/course';
    const A_LESSON      = 'http://adlnet.gov/expapi/activities/lesson';
    const A_MODULE      = 'http://adlnet.gov/expapi/activities/module';
    const A_VIDEO       = 'https://w3id.org/xapi/video/activity-type/video';
    const A_UNIT_TEST   = 'http://id.tincanapi.com/activitytype/unit-test';
    const A_CERTIFICATE = 'https://www.opigno.org/en/tincan_registry/activity_type/certificate';

    /* ---------------------------------------------------------------- */
    /* الامتدادات                                                        */
    /* ---------------------------------------------------------------- */

    const X_PLATFORM    = 'https://nelc.gov.sa/extensions/platform';
    const X_DURATION    = 'https://nelc.gov.sa/extensions/duration';
    const X_MOBILE      = 'https://nelc.gov.sa/extensions/learner_mobile_no';
    const X_FULL_NAME   = 'https://nelc.gov.sa/extensions/learner_full_name';
    const X_NATIONALITY = 'https://nelc.gov.sa/extensions/learner_nationality';
    const X_DOB         = 'https://nelc.gov.sa/extensions/date_of_birth';
    const X_LMS_URL     = 'https://nelc.gov.sa/extensions/lms_url';
    const X_PROGRAM_URL = 'https://nelc.gov.sa/extensions/program_url';
    const X_ATTEMPT_ID  = 'http://id.tincanapi.com/extension/attempt-id';
    const X_BROWSER     = 'http://id.tincanapi.com/extension/browser-info';
    const X_CERT_URL    = 'http://id.tincanapi.com/extension/jws-certificate-location';

    /** لغة المحتوى — المنصة عربية سعودية. */
    const LANG = 'ar-SA';

    /**
     * الأحداث الثمانية — وحدة القرار في هذه القناة.
     *
     * ولكل حدث مفتاح يشغله وحده (`tq_nelc_ev_<المفتاح>`): المركز قد يطلب
     * في مرحلة المراجعة أن يوقف فعل بعينه أو يقتصر على أربعة، وقلب مربع
     * أهون من نشر. وكلها مشعلة افتراضا — من فتح التكامل أراد ما يفتحه.
     *
     * `label` ما يقرؤه المسؤول، و`hint` متى يخرج بالضبط فلا يقلب مفتاحا
     * لا يعرف أثره.
     */
    public static $EVENTS = array(
        'registered' => array(
            'label' => 'التسجيل في كورس',
            'hint'  => 'يخرج عند تفعيل اشتراك يفتح الكورس للطالب — مرة لكل (طالب، كورس).',
        ),
        'initialized' => array(
            'label' => 'بدء الكورس',
            'hint'  => 'يخرج عند أول درس يفتحه الطالب في الكورس.',
        ),
        'watched' => array(
            'label' => 'مشاهدة درس',
            'hint'  => 'يخرج عند اكتمال مشاهدة درس فيديو، ومعه الزمن المقاس فعلا.',
        ),
        'completed' => array(
            'label' => 'إتمام درس أو كورس',
            'hint'  => 'يخرج عند إتمام كل درس، ومرة عند إتمام دروس الكورس كلها.',
        ),
        'progressed' => array(
            'label' => 'نسبة التقدم',
            'hint'  => 'يخرج مع كل إتمام درس بنسبة ما أنجز من الكورس.',
        ),
        'attempted' => array(
            'label' => 'محاولة اختبار',
            'hint'  => 'يخرج عند تسليم محاولة، ومعها الدرجة وحد النجاح.',
        ),
        'earned' => array(
            'label' => 'شهادة',
            'hint'  => 'يخرج عند اجتياز امتحان محطة — وهو ما تبنى عليه الشهادة هنا.',
        ),
        'rated' => array(
            'label' => 'تقييم كورس',
            'hint'  => 'يخرج عند تقييم الطالب للكورس، ومعه نص المراجعة.',
        ),
    );

    /** مفاتيح الإعدادات — كلها في `settings` بالبادئة `tq_nelc_`. */
    public static $KEYS = array(
        'tq_nelc_enabled',
        'tq_nelc_endpoint',
        'tq_nelc_username',
        'tq_nelc_password',
        'tq_nelc_platform_id',
        'tq_nelc_platform_ar',
        'tq_nelc_platform_en',
        'tq_nelc_lms_url',
        'tq_nelc_fallback_email',
        'tq_nelc_schema_v',
    );

    /** ضبط محفوظ للطلب — `get_settings()` استعلام لكل مفتاح. */
    private $cfg = null;

    /** المخطط يفحص مرة واحدة لكل طلب. */
    private $schema_checked = false;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /* =====================================================================
       الضبط
       ===================================================================== */

    /**
     * كل المفاتيح باستعلام واحد.
     *
     * والافتراضات ليست زينة: من فتح الشاشة أول مرة يجد النطاق التجريبي
     * واسم المنصة ورابطها مكتوبة، فلا يبقى عليه إلا اسم المستخدم وكلمة
     * السر — وهما وحدهما ما يعطيه المركز.
     */
    public function config($fresh = false)
    {
        if ($this->cfg !== null && !$fresh) return $this->cfg;

        $v = array();
        try {
            $rows = $this->db->select('key, value')
                             ->where_in('key', array_merge(self::$KEYS, $this->event_keys()))
                             ->get('settings')->result_array();
            foreach ($rows as $r) $v[$r['key']] = (string) $r['value'];
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-NELC: تعذر قراءة الإعدادات — ' . $e->getMessage());
        }

        $events = array();
        foreach (array_keys(self::$EVENTS) as $k) {
            /* الغياب «مشعل»: من ضبط المفاتيح قبل أن تكتب مفاتيح الأحداث
               لا يفقد سبعة أفعال من ثمانية بلا أن يطلب. */
            $events[$k] = !isset($v['tq_nelc_ev_' . $k]) || $v['tq_nelc_ev_' . $k] === '1';
        }

        $this->cfg = array(
            'enabled'     => isset($v['tq_nelc_enabled']) && $v['tq_nelc_enabled'] === '1',
            'endpoint'    => trim((string) ($v['tq_nelc_endpoint'] ?? '')) ?: self::DEFAULT_ENDPOINT,
            'username'    => trim((string) ($v['tq_nelc_username'] ?? '')),
            'password'    => (string) ($v['tq_nelc_password'] ?? ''),
            'platform_id' => trim((string) ($v['tq_nelc_platform_id'] ?? '')) ?: $this->host(),
            'platform_ar' => trim((string) ($v['tq_nelc_platform_ar'] ?? '')) ?: 'تقدر',
            'platform_en' => trim((string) ($v['tq_nelc_platform_en'] ?? '')) ?: 'Taqdar',
            'lms_url'     => trim((string) ($v['tq_nelc_lms_url'] ?? '')) ?: $this->site_root(),
            'fallback'    => trim((string) ($v['tq_nelc_fallback_email'] ?? '')),
            'events'      => $events,
        );
        return $this->cfg;
    }

    /** مفاتيح الأحداث مشتقة لا مكتوبة — قائمتان تفترقان عند أول فعل يضاف. */
    private function event_keys()
    {
        $out = array();
        foreach (array_keys(self::$EVENTS) as $k) $out[] = 'tq_nelc_ev_' . $k;
        return $out;
    }

    /**
     * أمضبوط؟
     *
     * ثلاثة لا اثنان: مشعل، وله نطاق، وله بيانات اعتماد. ومفتاح مشعل بلا
     * كلمة سر يجعل كل جملة تخرج لترد `401`، فيمتلئ الطابور بفشل ليس فيه
     * خبر — وأصله أن أحدا لم يكمل الشاشة.
     */
    public function ready()
    {
        $c = $this->config();
        return $c['enabled'] && $c['endpoint'] !== '' && $c['username'] !== '' && $c['password'] !== '';
    }

    /** أهذا الفعل مشعل؟ */
    public function event_on($key)
    {
        $c = $this->config();
        return !empty($c['events'][$key]);
    }

    /** اسم المضيف — الافتراض المعقول لمعرف المنصة. */
    private function host()
    {
        $h = parse_url(base_url(), PHP_URL_HOST);
        return $h ? (string) $h : 'taqdaredu.com';
    }

    /**
     * جذر الموقع — ولا يبنى من `base_url()` مباشرة.
     *
     * `config.php` يركب `base_url` من `$_SERVER['HTTP_HOST']`، وهو **فارغ في
     * سطر الأوامر**: فتخرج `http://` وحدها، و`rtrim(…, '/')` تتركها `http:`
     * — فيصير رابط الكورس `http:/course/…` في كل جملة تبنى من الكرون. وهو
     * ما يكتب في `id` النشاط، أي في المعرف الذي يعرف به المركز الكورس:
     * فيقرأ كورسا اسمه شيء ورابطه لا شيء، ولا خطأ يظهر — الجملة صحيحة
     * البناء، والذي يكذب هو الرابط وحده.
     *
     * والكرون هو من ينادي `sweep_ratings()`، فهذا ليس فرضا نظريا.
     */
    private function site_root()
    {
        $u = (string) base_url();
        if (parse_url($u, PHP_URL_HOST)) return rtrim($u, '/');
        return 'https://' . $this->host();
    }

    private function put_setting($key, $value)
    {
        try {
            if ($this->db->where('key', $key)->count_all_results('settings') > 0) {
                $this->db->where('key', $key)->update('settings', array('value' => (string) $value));
            } else {
                $this->db->insert('settings', array('key' => $key, 'value' => (string) $value));
            }
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    /** يكتب المفاتيح كلها — تنادى من شاشة الحفظ وحدها. */
    public function save_config($vals)
    {
        foreach ((array) $vals as $k => $v) $this->put_setting($k, $v);
        $this->cfg = null;
    }

    /* =====================================================================
       المخطط
       ===================================================================== */

    /**
     * الطابور وعمود الهوية.
     *
     * وينادى من **مسار العرض** كما ينادى من مسار الكتابة: شاشة اللوحة
     * تقرأ الطابور قبل أن يكتب فيه صف واحد، وقراءة جدول قبل إنشائه ترمي
     * استثناء يبيض الشاشة. وهي قاعدة `Taqdar_course_sale_model` نفسها.
     */
    public function install_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        $have = (int) get_settings('tq_nelc_schema_v');
        if ($have >= self::SCHEMA_V) return;

        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_nelc_queue` (
                    `id`          int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `uuid`        char(36)     NOT NULL,
                    `event_key`   varchar(190) NOT NULL,
                    `verb`        varchar(32)  NOT NULL,
                    `user_id`     int(11)      NOT NULL DEFAULT 0,
                    `statement`   longtext     NOT NULL,
                    `state`       varchar(16)  NOT NULL DEFAULT "queued",
                    `attempts`    int(11)      NOT NULL DEFAULT 0,
                    `http_code`   int(11)      NOT NULL DEFAULT 0,
                    `last_error`  varchar(250) DEFAULT NULL,
                    `next_try_at` int(11)      NOT NULL DEFAULT 0,
                    `created_at`  int(11)      NOT NULL DEFAULT 0,
                    `sent_at`     int(11)      DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_event` (`event_key`),
                    KEY `ix_due` (`state`,`next_try_at`),
                    KEY `ix_user` (`user_id`,`verb`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            /* رقم الهوية — والعمود على `users` لا في جدول جانبي: صف واحد
               لكل مستخدم بحكم المخطط، وجدول ثان لحقل واحد يعني ضما في كل
               بناء جملة. */
            $this->db->query(
                'ALTER TABLE `users`
                    ADD COLUMN IF NOT EXISTS `tq_national_id` varchar(20) DEFAULT NULL,
                    ADD COLUMN IF NOT EXISTS `tq_nationality` varchar(64) DEFAULT NULL,
                    ADD COLUMN IF NOT EXISTS `tq_birth_date`  date        DEFAULT NULL'
            );

            $this->put_setting('tq_nelc_schema_v', (string) self::SCHEMA_V);
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-NELC install_schema: ' . $e->getMessage());
        }
    }

    /* =====================================================================
       الهوية
       ===================================================================== */

    /**
     * رقم الهوية السعودية — عشر خانات تبدأ بـ١ (مواطن) أو ٢ (مقيم).
     *
     * والفحص هنا وحده: شاشة الطالب وشاشة اللوحة والتسجيل ثلاثتها تنادي
     * هذه — وثلاثة شروط لحقل واحد تجعل اللوحة تقبل ما ترده الشاشة.
     */
    public static function valid_national_id($v)
    {
        $v = preg_replace('/\D+/', '', (string) $v);
        return (bool) preg_match('/^[12]\d{9}$/', $v);
    }

    /** يسوي المدخل: أرقام لاتينية مجردة، والفارغ يبقى فارغا. */
    public static function clean_national_id($v)
    {
        $v = trim((string) $v);
        if ($v === '') return '';

        /* الأرقام العربية الهندية تلصق من الجوال وتصل هنا — وحقل يرد
           رقما صحيحا كتب بلوحة عربية يقرأ عطلا. */
        $ar = array('٠','١','٢','٣','٤','٥','٦','٧','٨','٩');
        $v  = str_replace($ar, array('0','1','2','3','4','5','6','7','8','9'), $v);

        return preg_replace('/\D+/', '', $v);
    }

    /**
     * المتعلم كما يعرفه المركز — أو `null`.
     *
     * و`null` هي الجواب الصحيح لمن لا هوية له: تقرأ عند كل مستدعي فيمتنع
     * عن الكتابة، فلا يدخل الطابور صف لا يمكن إرساله ولا يبقى فيه إلى
     * الأبد. والبريد من الحساب — والقاصر يكتب بريد ولي أمره وهو شرط
     * تسجيله، فلا حساب بلا بريد.
     */
    public function actor($user_id)
    {
        $u = $this->user($user_id);
        if (!$u) return null;

        $nid = self::clean_national_id($u['tq_national_id'] ?? '');
        if (!self::valid_national_id($nid)) return null;

        $email = trim((string) $u['email']);
        if ($email === '') return null;

        return array(
            'mbox'       => 'mailto:' . $email,
            'name'       => $nid,
            'objectType' => 'Agent',
        );
    }

    /** صف المستخدم — محفوظ للطلب، فالجملة الواحدة تسأل عنه مرتين. */
    private $users = array();

    private function user($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return null;
        if (array_key_exists($user_id, $this->users)) return $this->users[$user_id];

        try {
            $row = $this->db->select('id, first_name, last_name, email, phone, age,
                                      tq_national_id, tq_nationality, tq_birth_date')
                            ->where('id', $user_id)->get('users')->row_array();
        } catch (Throwable $e) {
            /* العمود قد لا يكون أنشئ بعد على قاعدة لم يفتح فيها التكامل. */
            $this->db->reset_query();
            $row = null;
        }
        return $this->users[$user_id] = ($row ?: null);
    }

    /** الاسم الكامل كما يكتب في امتداد `learner_full_name`. */
    private function full_name($u)
    {
        return trim(trim((string) ($u['first_name'] ?? '')) . ' ' . trim((string) ($u['last_name'] ?? '')));
    }

    /**
     * المعلم — إلزامي في سياق كل جملة.
     *
     * وكورس بلا صاحب ليس حالا نادرة: الاستيراد يكتب كورسات بلا `creator`،
     * ومعلم يغلق حسابه يترك كورساته. فبدل أن تسقط الجملة كلها لغياب اسم
     * ترتد إلى المنصة نفسها — والبريد من الشاشة أو من بريد النظام.
     */
    public function instructor($course)
    {
        $c   = $this->config();
        $uid = (int) ($course['creator'] ?? $course['user_id'] ?? 0);

        if ($uid > 0) {
            $u = $this->user($uid);
            if ($u && trim((string) $u['email']) !== '') {
                $name = $this->full_name($u);
                return array(
                    'name' => $name !== '' ? $name : $c['platform_ar'],
                    'mbox' => 'mailto:' . trim((string) $u['email']),
                );
            }
        }

        $mail = $c['fallback'] !== '' ? $c['fallback'] : trim((string) get_settings('system_email'));
        if ($mail === '') $mail = 'info@' . $this->host();

        return array('name' => $c['platform_ar'], 'mbox' => 'mailto:' . $mail);
    }

    /* =====================================================================
       بناء الجملة
       ===================================================================== */

    /** امتداد المنصة — يلحق بكل جملة بلا استثناء. */
    private function platform_ext()
    {
        $c = $this->config();
        return array(self::X_PLATFORM => array('name' => array(
            'ar-SA' => $c['platform_ar'],
            'en-US' => $c['platform_en'],
        )));
    }

    /** الكائن (النشاط): رابطه ونوعه واسمه. */
    private function activity($url, $title, $type, $description = '')
    {
        $def = array(
            'name' => array(self::LANG => $this->plain($title)),
            'type' => $type,
        );
        $description = $this->plain($description);
        if ($description !== '') $def['description'] = array(self::LANG => $description);

        return array('id' => $url, 'definition' => $def, 'objectType' => 'Activity');
    }

    /**
     * نص نظيف لجملة تخرج إلى جهة أخرى.
     *
     * وصف الكورس في القاعدة HTML كتبه محرر غني، ودفعه كما هو يملأ تقرير
     * المركز بوسوم. والقص عند حد معقول: حقل وصف بعشرة آلاف حرف يضخم
     * الحمولة بلا أن يقرأه أحد.
     */
    private function plain($s, $max = 500)
    {
        $s = (string) $s;
        if ($s === '') return '';
        $s = strip_tags(str_replace(array('<br>', '<br/>', '<br />', '</p>'), ' ', $s));
        $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        if ($s !== '' && function_exists('mb_substr') && mb_strlen($s, 'UTF-8') > $max) {
            $s = mb_substr($s, 0, $max, 'UTF-8');
        }
        return $s;
    }

    /** السياق المشترك — المعلم والمنصة واللغة، ومعها ما يخص الفعل. */
    private function context($course, $extensions = array(), $parent = null)
    {
        $c   = $this->config();
        $ctx = array(
            'instructor' => $this->instructor($course),
            'platform'   => $c['platform_id'],
            'language'   => self::LANG,
            'extensions' => array_merge($this->platform_ext(), $extensions),
        );
        if ($parent) $ctx['contextActivities'] = array('parent' => $parent);
        return $ctx;
    }

    /** المدة بصيغة ISO-8601 كما يقرؤها المعيار. */
    public static function iso_duration($seconds)
    {
        $s = max(0, (int) round($seconds));
        return 'PT' . intdiv($s, 3600) . 'H' . (intdiv($s, 60) % 60) . 'M' . ($s % 60) . 'S';
    }

    /**
     * وقت الحدث المعلن — يستعمله الترحيل وحده.
     *
     * وبلاه يخرج تاريخ اليوم على درس أتم في رمضان، فيقرأ المركز منصة بلا
     * تاريخ: كل نشاطها وقع في اليوم الذي شغل فيه الترحيل. والتقرير الذي
     * يبنى على ذلك لا يقول شيئا.
     */
    private $at = null;

    /** يثبت وقت ما يبنى بعده، و`null` يعيده إلى «الآن». */
    public function at($ts = null)
    {
        $this->at = $ts ? (int) $ts : null;
        return $this;
    }

    /** الوقت بصيغة ISO-8601 بمنطقة صريحة — «الآن» بلا منطقة يقرأ UTC. */
    private function iso_now($ts = null)
    {
        if (!$ts) $ts = $this->at;
        $ts = $ts ? (int) $ts : time();
        return date('c', $ts);
    }

    /* =====================================================================
       الروابط
       ===================================================================== */

    /** رابط الكورس العام — الصيغة نفسها التي يبنيها الكتالوج. */
    public function course_url($course)
    {
        $id = (int) ($course['id'] ?? 0);
        return $this->site_root() . '/course/'
             . rawurlencode(slugify((string) ($course['title'] ?? 'course'))) . '/' . $id;
    }

    /** رابط الدرس — معرف ثابت للنشاط، لا صفحة تفتح للعموم. */
    public function lesson_url($lesson)
    {
        return $this->site_root() . '/student/lesson/' . (int) ($lesson['id'] ?? 0);
    }

    /* =====================================================================
       الأحداث — ما تناديه المنصة
       ===================================================================== */

    /**
     * تسجيل الطالب في كورس.
     *
     * ومعه امتدادات المتعلم الاختيارية: المركز يطلبها في جملة التسجيل
     * وحدها لأنها تعرف بالمتعلم مرة، وتكرارها في كل نبضة تقدم حشو.
     */
    public function registered($user_id, $course_id, $path = null)
    {
        if (!$this->guard('registered')) return false;

        $actor  = $this->actor($user_id);
        $course = $this->course($course_id);
        if (!$actor || !$course) return false;

        $u   = $this->user($user_id);
        $c   = $this->config();
        $ext = array(self::X_LMS_URL => $c['lms_url']);

        $name = $this->full_name($u);
        if ($name !== '')                        $ext[self::X_FULL_NAME]   = $name;
        if (!empty($u['phone']))                 $ext[self::X_MOBILE]      = (string) $u['phone'];
        if (!empty($u['tq_nationality']))        $ext[self::X_NATIONALITY] = (string) $u['tq_nationality'];
        if (!empty($u['tq_birth_date']))         $ext[self::X_DOB]         = (string) $u['tq_birth_date'];
        if ($path && !empty($path['url']))       $ext[self::X_PROGRAM_URL] = (string) $path['url'];

        /* مدة الكورس بالساعات — `expiry_period` مدة الوصول لا مدة الدرس،
           فالمصدر مجموع أطوال دروسه المقاسة. وصفر لا يكتب: امتداد بقيمة
           صفر يقرأ «كورس بلا محتوى». */
        $secs = $this->course_seconds($course_id);
        if ($secs > 0) $ext[self::X_DURATION] = self::iso_duration($secs);

        return $this->push('registered', 'reg:' . (int) $user_id . ':' . (int) $course_id, array(
            'actor'     => $actor,
            'verb'      => array('id' => self::V_REGISTERED, 'display' => array('en-US' => 'registered')),
            'object'    => $this->activity($this->course_url($course), $course['title'],
                                           self::A_COURSE, $course['short_description'] ?? ''),
            'context'   => $this->context($course, $ext),
            'timestamp' => $this->iso_now(),
        ), $user_id);
    }

    /** بدء الكورس — أول درس يفتحه الطالب فيه. */
    public function initialized($user_id, $course_id)
    {
        if (!$this->guard('initialized')) return false;

        $actor  = $this->actor($user_id);
        $course = $this->course($course_id);
        if (!$actor || !$course) return false;

        return $this->push('initialized', 'init:' . (int) $user_id . ':' . (int) $course_id, array(
            'actor'     => $actor,
            'verb'      => array('id' => self::V_INITIALIZED, 'display' => array('en-US' => 'initialized')),
            'object'    => $this->activity($this->course_url($course), $course['title'],
                                           self::A_COURSE, $course['short_description'] ?? ''),
            'context'   => $this->context($course),
            'timestamp' => $this->iso_now(),
        ), $user_id);
    }

    /**
     * مشاهدة درس فيديو اكتملت.
     *
     * والمدة **المقاسة** لا المكتوبة في صف الدرس: TQ-DURATION يقول إن
     * `lesson.duration` ادعاء يكتب بيد، وإرسال ادعاء إلى جهة ترخيص أسوأ
     * من إرساله إلى شاشة.
     */
    public function watched($user_id, $lesson_id, $watched_seconds, $completed = true)
    {
        if (!$this->guard('watched')) return false;

        $actor  = $this->actor($user_id);
        $lesson = $this->lesson($lesson_id);
        if (!$actor || !$lesson) return false;

        $course = $this->course((int) $lesson['course_id']);
        if (!$course) return false;

        return $this->push('watched', 'watch:' . (int) $user_id . ':' . (int) $lesson_id, array(
            'actor'     => $actor,
            'verb'      => array('id' => self::V_WATCHED, 'display' => array('en-US' => 'watched')),
            'object'    => $this->activity($this->lesson_url($lesson), $lesson['title'], self::A_VIDEO),
            'context'   => $this->context($course, array(),
                              $this->activity($this->course_url($course), $course['title'], self::A_COURSE)),
            'result'    => array(
                'completion' => (bool) $completed,
                'duration'   => self::iso_duration($watched_seconds),
            ),
            'timestamp' => $this->iso_now(),
        ), $user_id);
    }

    /** إتمام درس. */
    public function completed_lesson($user_id, $lesson_id)
    {
        if (!$this->guard('completed')) return false;

        $actor  = $this->actor($user_id);
        $lesson = $this->lesson($lesson_id);
        if (!$actor || !$lesson) return false;

        $course = $this->course((int) $lesson['course_id']);
        if (!$course) return false;

        return $this->push('completed', 'donel:' . (int) $user_id . ':' . (int) $lesson_id, array(
            'actor'     => $actor,
            'verb'      => array('id' => self::V_COMPLETED, 'display' => array('en-US' => 'completed')),
            'object'    => $this->activity($this->lesson_url($lesson), $lesson['title'], self::A_LESSON),
            'context'   => $this->context($course, array(),
                              $this->activity($this->course_url($course), $course['title'], self::A_COURSE)),
            'timestamp' => $this->iso_now(),
        ), $user_id);
    }

    /** إتمام الكورس كله. */
    public function completed_course($user_id, $course_id)
    {
        if (!$this->guard('completed')) return false;

        $actor  = $this->actor($user_id);
        $course = $this->course($course_id);
        if (!$actor || !$course) return false;

        return $this->push('completed', 'donec:' . (int) $user_id . ':' . (int) $course_id, array(
            'actor'     => $actor,
            'verb'      => array('id' => self::V_COMPLETED, 'display' => array('en-US' => 'completed')),
            'object'    => $this->activity($this->course_url($course), $course['title'],
                                           self::A_COURSE, $course['short_description'] ?? ''),
            'context'   => $this->context($course),
            'timestamp' => $this->iso_now(),
        ), $user_id);
    }

    /**
     * نسبة التقدم في الكورس.
     *
     * و`event_key` يحمل النسبة: التقدم حدث متكرر بطبعه — عشرة بالمئة ثم
     * عشرون — ومفتاح بلا نسبة يجعل أول إرسال يمنع كل ما بعده، فيقرأ
     * المركز طالبا وقف عند ١٠٪ إلى الأبد. والدرجة **قياسية** (`scaled`)
     * كما يطلب المعيار: كسر بين صفر وواحد.
     */
    public function progressed($user_id, $course_id, $percent, $completed = false)
    {
        if (!$this->guard('progressed')) return false;

        $actor  = $this->actor($user_id);
        $course = $this->course($course_id);
        if (!$actor || !$course) return false;

        $percent = max(0, min(100, (int) $percent));

        return $this->push('progressed',
            'prog:' . (int) $user_id . ':' . (int) $course_id . ':' . $percent, array(
            'actor'     => $actor,
            'verb'      => array('id' => self::V_PROGRESSED, 'display' => array('en-US' => 'progressed')),
            'object'    => $this->activity($this->course_url($course), $course['title'], self::A_COURSE),
            'context'   => $this->context($course),
            'result'    => array(
                'completion' => (bool) $completed,
                'score'      => array('scaled' => round($percent / 100, 2)),
            ),
            'timestamp' => $this->iso_now(),
        ), $user_id);
    }

    /**
     * محاولة اختبار سلمت.
     *
     * والدرجة أربعة أرقام لا واحد (`raw`/`min`/`max`/`scaled`): المركز
     * يقرأ `scaled` ويعرض `raw`، و`max` هو عدد الأسئلة لا مئة — «سبعة من
     * عشرة» تقرأ، و«سبعون من مئة» تخترع مقياسا لم يكن.
     */
    public function attempted($user_id, $lesson_id, $attempt_id, $attempt_no, $score, $out_of, $passed)
    {
        if (!$this->guard('attempted')) return false;

        $actor  = $this->actor($user_id);
        $lesson = $this->lesson($lesson_id);
        if (!$actor || !$lesson) return false;

        $course = $this->course((int) $lesson['course_id']);
        if (!$course) return false;

        $out_of = max(1, (int) $out_of);
        $score  = max(0, min($out_of, (int) $score));

        return $this->push('attempted', 'try:' . (int) $attempt_id, array(
            'actor'     => $actor,
            'verb'      => array('id' => self::V_ATTEMPTED, 'display' => array('en-US' => 'attempted')),
            'object'    => $this->activity($this->lesson_url($lesson), $lesson['title'], self::A_UNIT_TEST),
            'context'   => $this->context($course,
                              array(self::X_ATTEMPT_ID => (int) $attempt_no),
                              $this->activity($this->course_url($course), $course['title'], self::A_COURSE)),
            'result'    => array(
                'completion' => true,
                'success'    => (bool) $passed,
                'score'      => array(
                    'scaled' => round($score / $out_of, 4),
                    'raw'    => $score,
                    'min'    => 0,
                    'max'    => $out_of,
                ),
            ),
            'timestamp' => $this->iso_now(),
        ), $user_id);
    }

    /**
     * شهادة.
     *
     * والشهادة هنا **اجتياز امتحان محطة** لا نسبة مشاهدة — كما تقرؤها
     * `Taqdar_student_model::certificates()`. ومصدران للشهادة يعنيان أن
     * المركز يعد شهادات لا يعدها الطالب في شاشته.
     */
    public function earned($user_id, $attempt_id, $title, $course_id = 0, $certificate_url = '')
    {
        if (!$this->guard('earned')) return false;

        $actor = $this->actor($user_id);
        if (!$actor) return false;

        $course = $course_id ? $this->course($course_id) : null;
        $cert   = $this->site_root() . '/taqdar/verify/'
                . 'TQ-' . str_pad((string) (int) $attempt_id, 6, '0', STR_PAD_LEFT);

        $ext = array();
        if ($certificate_url !== '') $ext[self::X_CERT_URL] = $certificate_url;

        $st = array(
            'actor'     => $actor,
            'verb'      => array('id' => self::V_EARNED, 'display' => array('en-US' => 'earned')),
            'object'    => $this->activity($cert, $title, self::A_CERTIFICATE),
            'context'   => $this->context($course ?: array(), $ext,
                              $course ? $this->activity($this->course_url($course),
                                                        $course['title'], self::A_COURSE) : null),
            'timestamp' => $this->iso_now(),
        );

        return $this->push('earned', 'cert:' . (int) $attempt_id, $st, $user_id);
    }

    /** تقييم كورس. */
    public function rated($user_id, $course_id, $stars, $review = '')
    {
        if (!$this->guard('rated')) return false;

        $actor  = $this->actor($user_id);
        $course = $this->course($course_id);
        if (!$actor || !$course) return false;

        $stars = max(0, min(5, (float) $stars));

        return $this->push('rated', 'rate:' . (int) $user_id . ':' . (int) $course_id, array(
            'actor'     => $actor,
            'verb'      => array('id' => self::V_RATED, 'display' => array('en-US' => 'rated')),
            'object'    => $this->activity($this->course_url($course), $course['title'], self::A_COURSE),
            'context'   => $this->context($course),
            'result'    => array(
                'score'    => array('scaled' => round($stars / 5, 2), 'raw' => $stars, 'min' => 0, 'max' => 5),
                'response' => $this->plain($review, 1000),
            ),
            'timestamp' => $this->iso_now(),
        ), $user_id);
    }

    /* =====================================================================
       قراءات مساعدة
       ===================================================================== */

    /**
     * نسبة إتمام الكورس لهذا الطالب.
     *
     * ومن `lesson_progress` نفسه الذي يقرؤه القفل، لا من `watch_histories`
     * الموروث: رقمان يفترقان يجعلان المركز يقرأ «٪١٠٠» على طالب يقف أمام
     * درس مقفل. وهي قاعدة الواجهة نفسها.
     */
    public function course_percent($user_id, $course_id)
    {
        try {
            $total = (int) $this->db->where('course_id', (int) $course_id)
                                    ->count_all_results('lesson');
            if ($total <= 0) return 0;

            $r = $this->db->select('COUNT(*) AS n', false)
                          ->from('lesson_progress p')
                          ->join('lesson l', 'l.id = p.lesson_id', 'inner')
                          ->where('p.student_id', (int) $user_id)
                          ->where('l.course_id', (int) $course_id)
                          ->where('p.completed_at IS NOT NULL')
                          ->get()->row_array();

            $done = (int) ($r['n'] ?? 0);
            return min(100, (int) floor($done * 100 / $total));
        } catch (Throwable $e) {
            $this->db->reset_query();
            return 0;
        }
    }

    /**
     * حزمة إتمام الدرس — نداء واحد من مسار التقدم.
     *
     * أربعة أفعال تخرج من حدث واحد، وجمعها هنا يترك في `Taqdar_repo_model`
     * سطرا واحدا: مسار التقدم لا يعرف من قواعد المركز شيئا، ومعرفته بها
     * تعني أن الفعل التاسع يضاف هناك وينسى هنا.
     *
     * و`completed_course` يخرج عند بلوغ المئة وحده — و`event_key` يمنع
     * تكراره، فمن أعاد درسا بعد إتمام الكورس لا يعد كورسا ثانيا.
     */
    public function lesson_done($user_id, $lesson_id, $watch_seconds = 0)
    {
        if (!$this->ready()) return;

        try {
            $lesson = $this->lesson($lesson_id);
            if (!$lesson) return;
            $cid = (int) $lesson['course_id'];

            if ($watch_seconds > 0 && $this->trackable_kind($lesson)) {
                $this->watched($user_id, $lesson_id, $watch_seconds, true);
            }
            $this->completed_lesson($user_id, $lesson_id);

            $pc = $this->course_percent($user_id, $cid);
            $this->progressed($user_id, $cid, $pc, $pc >= 100);
            if ($pc >= 100) $this->completed_course($user_id, $cid);
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-NELC lesson_done: ' . $e->getMessage());
        }
    }

    /**
     * أهذا درس فيديو؟
     *
     * `watched` فعل يخص المرئي وحده — وإرساله على درس نصي أو ملف مرفق
     * يصف المتعلم بأنه «شاهد» مستندا، وهو خبر كاذب في تقرير جهة ترخيص.
     */
    private function trackable_kind($lesson)
    {
        $t = (string) ($lesson['lesson_type'] ?? '');
        return in_array($t, array('video', 'system-video', 'youtube', 'vimeo', 'html5'), true)
            || trim((string) ($lesson['video_type'] ?? '')) !== '';
    }

    private $courses = array();
    private $lessons = array();

    private function course($id)
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        if (array_key_exists($id, $this->courses)) return $this->courses[$id];

        try {
            $row = $this->db->select('id, title, short_description, creator, user_id, status')
                            ->where('id', $id)->get('course')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); $row = null; }

        return $this->courses[$id] = ($row ?: null);
    }

    private function lesson($id)
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        if (array_key_exists($id, $this->lessons)) return $this->lessons[$id];

        try {
            $row = $this->db->select('id, title, course_id, duration_sec, lesson_type, video_type')
                            ->where('id', $id)->get('lesson')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); $row = null; }

        return $this->lessons[$id] = ($row ?: null);
    }

    /** كورس هذا الدرس — يناديه مسار المحاولة، وأسئلة المحطة قد تكون بلا درس. */
    public function course_of_lesson($lesson_id)
    {
        $l = $this->lesson($lesson_id);
        return $l ? (int) $l['course_id'] : 0;
    }

    /** مجموع أطوال دروس الكورس المقاسة — بالثواني. */
    private function course_seconds($course_id)
    {
        try {
            $r = $this->db->select('COALESCE(SUM(duration_sec),0) AS s', false)
                          ->where('course_id', (int) $course_id)
                          ->get('lesson')->row_array();
            return (int) ($r['s'] ?? 0);
        } catch (Throwable $e) { $this->db->reset_query(); return 0; }
    }

    /* =====================================================================
       الطابور
       ===================================================================== */

    /**
     * الحارس الذي يسبق كل حدث.
     *
     * يجمع الشرطين — مضبوط، وهذا الفعل مشعل — في موضع واحد، فلا يكتب
     * ثمان مرات ولا ينسى في التاسع.
     */
    private function guard($event)
    {
        if (!$this->ready()) return false;
        if (!$this->event_on($event)) return false;
        $this->install_schema();
        return true;
    }

    /**
     * معرف الجملة — مشتق من مفتاح الحدث اشتقاقا ثابتا.
     *
     * فإعادة إرسال ما وصل ترد `409` وهي عندنا نجاح. و UUID عشوائي يجعل
     * كل إعادة محاولة جملة جديدة عند المركز، فيعد الحدث مرتين.
     */
    public static function uuid_of($event_key)
    {
        $h = sha1('taqdar-nelc:' . $event_key);
        return sprintf('%s-%s-5%s-%x%s-%s',
            substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3),
            (hexdec(substr($h, 16, 1)) & 0x3) | 0x8, substr($h, 17, 3), substr($h, 20, 12));
    }

    /**
     * يكتب الجملة في الطابور.
     *
     * ولا ترسل من هنا: مسار الطلب لا ينتظر شبكة. والإدراج ملفوف لأنه
     * تابع لا شرط — من أتم درسه أتمه سواء كتب الصف أو لم يكتب.
     */
    private function push($verb, $event_key, $statement, $user_id = 0)
    {
        $statement['id'] = self::uuid_of($event_key);

        /* وضع التقدير: الجملة بنيت — وهو ما يقاس — ولا تكتب. والرقم الذي
           يخرج **حد أعلى**: لا يطرح منه ما أبلغ عنه من قبل، لأن معرفة ذلك
           تحتاج الاستعلام نفسه الذي يجتنبه التقدير. */
        if ($this->dry) return true;

        try {
            /* `INSERT IGNORE` على `uq_event`: الحدث نفسه لا يدخل مرتين،
               و`count` قبل `insert` سباق يمر منه نداءان متزامنان. */
            $this->db->query(
                'INSERT IGNORE INTO `tq_nelc_queue`
                 (`uuid`,`event_key`,`verb`,`user_id`,`statement`,`state`,`next_try_at`,`created_at`)
                 VALUES (?,?,?,?,?,"queued",?,?)',
                array($statement['id'], $event_key, $verb, (int) $user_id,
                      json_encode($statement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                      time(), time()));
            return $this->db->affected_rows() > 0;
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-NELC push (' . $verb . '): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * يفرغ الطابور — ينادى من الكرون ومن زر الشاشة.
     *
     * والمهلة بين الصف والصف ليست حذرا زائدا: خادم يستقبل مئة طلب في
     * ثانية من مصدر واحد يخنقه، فيرتد كل ما بعده `429` ويعاد كله في
     * الدورة التالية — دورة تطعم نفسها.
     */
    public function drain($limit = 100)
    {
        $out = array('sent' => 0, 'failed' => 0, 'retry' => 0, 'skipped' => 0);
        if (!$this->ready()) { $out['skipped'] = 1; return $out; }

        $this->install_schema();

        try {
            $rows = $this->db->where('state', 'queued')
                             ->where('next_try_at <=', time())
                             ->order_by('id', 'ASC')
                             ->limit(max(1, (int) $limit))
                             ->get('tq_nelc_queue')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-NELC drain: ' . $e->getMessage());
            return $out;
        }

        foreach ($rows as $row) {
            $st = json_decode((string) $row['statement'], true);
            if (!is_array($st)) {
                $this->mark($row, 'failed', 0, 'الجملة المخزنة غير صالحة.');
                $out['failed']++;
                continue;
            }

            $r = $this->send($st);
            if ($r['ok']) {
                $this->mark($row, 'sent', $r['code'], null);
                $out['sent']++;
                continue;
            }

            if ($r['permanent']) {
                $this->mark($row, 'failed', $r['code'], $r['error']);
                $out['failed']++;
            } else {
                $this->mark($row, 'queued', $r['code'], $r['error']);
                $out['retry']++;
            }

            usleep(150000);
        }

        return $out;
    }

    /** أقصى عدد محاولات قبل أن يعد الصف فاشلا. */
    const MAX_ATTEMPTS = 12;

    /** تصاعد الانتظار — دقيقة، فخمس، فربع ساعة… وسقفه ست ساعات. */
    private static $BACKOFF = array(60, 300, 900, 1800, 3600, 7200, 21600);

    private function mark($row, $state, $code, $error)
    {
        $n    = (int) $row['attempts'] + 1;
        $data = array(
            'attempts'   => $n,
            'http_code'  => (int) $code,
            'last_error' => $error !== null ? mb_substr((string) $error, 0, 250, 'UTF-8') : null,
        );

        if ($state === 'sent') {
            $data['state']   = 'sent';
            $data['sent_at'] = time();
            /* وخطأ المحاولة السابقة يمحى: صف حاله «وصلت» وبجواره «تعذر
               الاتصال» يجعل من يقرأ الشاشة يظن أن شيئا لم يصل. والخبر هو
               الحال، والخطأ إنما يقرأ ما دام قائما. */
            $data['last_error'] = null;
        } elseif ($state === 'failed' || $n >= self::MAX_ATTEMPTS) {
            $data['state'] = 'failed';
        } else {
            $data['state']       = 'queued';
            $i                   = min($n - 1, count(self::$BACKOFF) - 1);
            $data['next_try_at'] = time() + self::$BACKOFF[$i];
        }

        try {
            $this->db->where('id', (int) $row['id'])->update('tq_nelc_queue', $data);
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    /* =====================================================================
       الإرسال
       ===================================================================== */

    /**
     * يرسل جملة واحدة.
     *
     * **و`409` نجاح.** المعيار يرد به على جملة بمعرف موجود، وهو بالضبط ما
     * يقع حين ينقطع الاتصال بعد أن يستقبل المركز ويقبل قبل أن يصلنا رده:
     * إعادة المحاولة تصل إلى جملة قائمة. وعده فشلا يجعل الصف يعاد اثنتي
     * عشرة مرة ثم يعلن فاشلا وهو **واصل**، فيقرأ المسؤول في شاشته فشلا
     * لا وجود له ويظن التكامل معطلا.
     *
     * ويفرق بين ما يعاد وما لا يعاد: `400`/`422` جملة نبنيها نحن خطأ،
     * وإعادتها ألف مرة لا تصححها — تعلن فاشلة ويقرأ سببها. و`401`/`403`
     * بيانات اعتماد، وهي تصحح من الشاشة فيبقى الصف ينتظر: إعلانه فاشلا
     * يعني أن تصحيح كلمة السر لا يعيد ما ضاع في أثناء خطئها.
     */
    public function send($statement)
    {
        $c = $this->config();

        $ch = curl_init($c['endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERPWD, $c['username'] . ':' . $c['password']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Experience-API-Version: ' . self::XAPI_VERSION,
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            json_encode($statement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return array('ok' => false, 'permanent' => false, 'code' => 0,
                         'error' => $cerr ?: 'تعذر الاتصال بخادم المركز.', 'body' => '');
        }

        $body = (string) $raw;

        if ($code === 200 || $code === 204 || $code === 409) {
            return array('ok' => true, 'permanent' => false, 'code' => $code,
                         'error' => null, 'body' => $body);
        }

        return array(
            'ok'        => false,
            'permanent' => ($code === 400 || $code === 404 || $code === 413 || $code === 422),
            'code'      => $code,
            'error'     => $this->api_error($code, $body),
            'body'      => $body,
        );
    }

    /**
     * رمز الرد إلى عربية تقول ما العمل.
     *
     * «تعذر الإرسال» جواب صحيح لا ينفع أحدا: من يقرأ `401` لا يعرف أن
     * كلمة السر هي المشكلة، ومن يقرأ `400` يظن الخادم معطلا وهو يرد على
     * جملة بنيناها خطأ.
     */
    public function api_error($code, $body = '')
    {
        $tail = '';
        $j    = json_decode((string) $body, true);
        if (is_array($j)) {
            foreach (array('message', 'error', 'detail', 'warnings') as $k) {
                if (!empty($j[$k])) { $tail = ' — ' . $this->plain(is_array($j[$k])
                    ? implode(' ', array_map('strval', $j[$k])) : (string) $j[$k], 120); break; }
            }
        } elseif (trim((string) $body) !== '') {
            $tail = ' — ' . $this->plain($body, 120);
        }

        switch ((int) $code) {
            case 0:   return 'تعذر الاتصال بخادم المركز.' . $tail;
            case 400: return 'المركز رفض شكل الجملة (400) — عطب في البناء لا في الشبكة.' . $tail;
            case 401: return 'اسم المستخدم أو كلمة السر مرفوضة (401) — راجع بيانات الاعتماد.' . $tail;
            case 403: return 'الحساب لا يملك إذن الكتابة في هذا المخزن (403).' . $tail;
            case 404: return 'النطاق غير موجود (404) — راجع رابط النقطة.' . $tail;
            case 409: return 'الجملة موجودة عندهم مسبقا (409).' . $tail;
            case 413: return 'الجملة أكبر مما يقبله المخزن (413).' . $tail;
            case 422: return 'الجملة مرفوضة لمخالفة المواصفة (422).' . $tail;
            case 429: return 'تجاوز حد الطلبات (429) — سيعاد الإرسال بعد مهلة.' . $tail;
        }
        if ($code >= 500) return 'خادم المركز يرد بخطأ (' . (int) $code . ') — سيعاد الإرسال.' . $tail;
        return 'رد غير متوقع (' . (int) $code . ').' . $tail;
    }

    /* =====================================================================
       الفحص والقراءة — لشاشة اللوحة
       ===================================================================== */

    /**
     * جملة فحص لا تلمس الطابور.
     *
     * وهي جملة `initialized` على نشاط فحص باسم صريح: بعض المخازن ترفض
     * الفعل المخترع، والمعرف عشوائي فلا يصادم حدثا حقيقيا. والفحص يرسل
     * **الآن** لأن سؤاله «أيصل؟» لا «أيكتب؟».
     */
    public function test($actor_email = '', $actor_nid = '')
    {
        if (!$this->ready()) {
            return array('ok' => false, 'code' => 0,
                         'error' => 'التكامل غير مضبوط — أكمل النطاق واسم المستخدم وكلمة السر.');
        }

        $c     = $this->config();
        $email = trim($actor_email) !== '' ? trim($actor_email)
               : (trim((string) get_settings('system_email')) ?: 'test@' . $this->host());
        $nid   = self::clean_national_id($actor_nid);
        if (!self::valid_national_id($nid)) $nid = '1000000000';

        $st = array(
            'id'     => self::uuid_of('test:' . microtime(true) . ':' . mt_rand()),
            'actor'  => array('mbox' => 'mailto:' . $email, 'name' => $nid, 'objectType' => 'Agent'),
            'verb'   => array('id' => self::V_INITIALIZED, 'display' => array('en-US' => 'initialized')),
            'object' => $this->activity($c['lms_url'] . '/nelc-connection-test',
                                        'فحص اتصال منصة تقدر', self::A_COURSE),
            'context' => array(
                'instructor' => array('name' => $c['platform_ar'],
                                      'mbox' => 'mailto:' . ($c['fallback'] ?: 'info@' . $this->host())),
                'platform'   => $c['platform_id'],
                'language'   => self::LANG,
                'extensions' => array_merge($this->platform_ext(),
                                            array(self::X_LMS_URL => $c['lms_url'])),
            ),
            'timestamp' => $this->iso_now(),
        );

        $r = $this->send($st);
        return array(
            'ok'        => $r['ok'],
            'code'      => $r['code'],
            'error'     => $r['error'],
            'body'      => $r['body'],
            'statement' => $st,
        );
    }

    /**
     * مسح التقييمات — الفعل الوحيد الذي لا يخرج من خطاف.
     *
     * وسببه أن كتابة التقييم في `Crud_model::insert_rating()` وهي **مشتركة
     * مع مسارات LMS الأصلية**: خطاف فيها يمس شيفرة يناديها ما لا نعرف،
     * والقاعدة في هذا المستودع ألا تمس. فالكرون يمر على ما جد.
     *
     * والمؤشر في `settings` لا في الذاكرة: مهمة تبدأ من الصفر كل دورة تمر
     * على آلاف الصفوف لتجد صفا، و`INSERT IGNORE` يجعل ذلك غير ضار وإنما
     * ثقيلا بلا سبب.
     */
    public function sweep_ratings($limit = 200)
    {
        if (!$this->ready() || !$this->event_on('rated')) return 0;
        $this->install_schema();

        $from = (int) get_settings('tq_nelc_rating_cursor');
        $n    = 0;
        $last = $from;

        try {
            $rows = $this->db->select('id, user_id, ratable_id, rating, review')
                             ->where('ratable_type', 'course')
                             ->where('id >', $from)
                             ->order_by('id', 'ASC')
                             ->limit(max(1, (int) $limit))
                             ->get('rating')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-NELC sweep_ratings: ' . $e->getMessage());
            return 0;
        }

        foreach ($rows as $r) {
            $last = (int) $r['id'];
            if ($this->rated((int) $r['user_id'], (int) $r['ratable_id'],
                             (float) $r['rating'], (string) $r['review'])) {
                $n++;
            }
        }

        /* والمؤشر يتقدم ولو لم تكتب جملة واحدة: الصف الذي لا يبلغ عنه —
           صاحبه بلا هوية، أو كورسه حذف — لا يبلغ عنه في الدورة القادمة
           أيضا، فالوقوف عنده يجعل المسح لا يتعدى أبدا ذلك الصف. */
        if ($last > $from) $this->put_setting('tq_nelc_rating_cursor', (string) $last);

        return $n;
    }

    /* =====================================================================
       الترحيل — ما وقع قبل أن يفتح التكامل
       ===================================================================== */

    /**
     * وضع التقدير: يبني ولا يكتب.
     *
     * فمن يريد أن يعرف «كم جملة سيخرج؟» قبل أن يفتح الباب على مخزن جهة
     * أخرى يعرفه بلا أن يكتب صفا. وهو `--dry-run` في `deploy.sh` نفسه.
     */
    private $dry = false;

    /**
     * يملأ الطابور بما وقع قبل أن يفتح التكامل.
     *
     * **ولا ينادى من مسار تلقائي واحد.** تشغيله قرار: قد يخرج آلاف الجمل
     * إلى مخزن جهة أخرى دفعة واحدة، وهذا ليس شيئا يقع لأن كرونا دار.
     *
     * والوقت المعلن هو **وقت الحدث الأصلي** لا وقت التشغيل، وإلا قرأ المركز
     * منصة كل نشاطها وقع في يوم واحد.
     *
     * ولا يكرر: `event_key` هو نفسه الذي يكتبه المسار الحي، فحدث أبلغ عنه
     * أمس لا يدخل الطابور اليوم مهما شغل الترحيل مرات.
     *
     * والمرحل من الأنواع الثلاثة التي تحمل تاريخها في القاعدة:
     *   `enrolments` ← `enrol` · `lessons` ← `lesson_progress` · `attempts` ← `attempts`
     *
     * وما لا يرحل: «بدء الكورس» و«نسبة التقدم» — الأول لا تاريخ له في
     * المخطط، والثاني حالة اللحظة لا حدث مؤرخ، فترحيله يكتب تاريخ اليوم
     * على نسبة الأمس.
     */
    public function backfill($kind = 'all', $limit = 500, $apply = false)
    {
        $out = array('enrolments' => 0, 'lessons' => 0, 'attempts' => 0, 'skipped' => 0);
        if (!$this->ready()) { $out['skipped'] = 1; return $out; }

        $this->install_schema();
        $this->dry = !$apply;
        $limit     = max(1, (int) $limit);

        try {
            if ($kind === 'all' || $kind === 'enrolments') {
                foreach ($this->db->select('user_id, course_id, date_added')
                                  ->order_by('id', 'ASC')->limit($limit)
                                  ->get('enrol')->result_array() as $r) {
                    $this->at($r['date_added']);
                    if ($this->registered((int) $r['user_id'], (int) $r['course_id'])) {
                        $out['enrolments']++;
                    }
                }
            }

            if ($kind === 'all' || $kind === 'lessons') {
                foreach ($this->db->select('student_id, lesson_id, watch_seconds, completed_at')
                                  ->where('completed_at IS NOT NULL')
                                  ->order_by('id', 'ASC')->limit($limit)
                                  ->get('lesson_progress')->result_array() as $r) {
                    $this->at(strtotime((string) $r['completed_at']));
                    if ($this->completed_lesson((int) $r['student_id'], (int) $r['lesson_id'])) {
                        $out['lessons']++;
                    }
                }
            }

            if ($kind === 'all' || $kind === 'attempts') {
                foreach ($this->db->select('a.id, a.student_id, a.attempt_no, a.score, a.passed,
                                            a.submitted_at, s.lesson_id, s.type')
                                  ->from('attempts a')
                                  ->join('assessments s', 's.id = a.assessment_id', 'inner')
                                  ->where('a.submitted_at IS NOT NULL')
                                  ->order_by('a.id', 'ASC')->limit($limit)
                                  ->get()->result_array() as $r) {
                    $this->at(strtotime((string) $r['submitted_at']));
                    $sid = (int) $r['student_id'];

                    /* `out_of` غير محفوظ في الصف: العدد الحي هو أقرب ما
                       يقاس، وأصدق من مئة مخترعة تجعل «سبعة» تقرأ ٧٪. */
                    $out_of = (int) $this->db->where('attempt_id', (int) $r['id'])
                                             ->count_all_results('answers');

                    if ($this->attempted($sid, (int) $r['lesson_id'], (int) $r['id'],
                                         (int) $r['attempt_no'], (int) $r['score'],
                                         max(1, $out_of), !empty($r['passed']))) {
                        $out['attempts']++;
                    }
                    if ($r['type'] === 'exam' && !empty($r['passed'])) {
                        $this->earned($sid, (int) $r['id'], 'شهادة إتقان',
                                      $this->course_of_lesson((int) $r['lesson_id']));
                    }
                }
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-NELC backfill: ' . $e->getMessage());
        }

        $this->dry = false;
        $this->at(null);
        return $out;
    }

    /** أعداد الطابور بحالاته — استعلام واحد. */
    public function stats()
    {
        $this->install_schema();
        $out = array('queued' => 0, 'sent' => 0, 'failed' => 0, 'due' => 0, 'total' => 0);

        try {
            foreach ($this->db->select('state, COUNT(*) AS n', false)
                              ->group_by('state')->get('tq_nelc_queue')->result_array() as $r) {
                $k = (string) $r['state'];
                if (isset($out[$k])) $out[$k] = (int) $r['n'];
                $out['total'] += (int) $r['n'];
            }
            $out['due'] = (int) $this->db->where('state', 'queued')
                                         ->where('next_try_at <=', time())
                                         ->count_all_results('tq_nelc_queue');
        } catch (Throwable $e) { $this->db->reset_query(); }

        return $out;
    }

    /** آخر ما دخل الطابور — للشاشة. */
    public function recent($limit = 50, $state = '')
    {
        $this->install_schema();
        try {
            $this->db->select('q.id, q.uuid, q.event_key, q.verb, q.user_id, q.state, q.attempts,
                               q.http_code, q.last_error, q.next_try_at, q.created_at, q.sent_at,
                               u.first_name, u.last_name, u.email')
                     ->from('tq_nelc_queue q')
                     ->join('users u', 'u.id = q.user_id', 'left');
            if ($state !== '') $this->db->where('q.state', $state);
            return $this->db->order_by('q.id', 'DESC')->limit(max(1, (int) $limit))
                            ->get()->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    /**
     * تغطية الهوية — «كم طالبا يمكن الإبلاغ عنه أصلا؟»
     *
     * وهو أول رقم يسأل عنه من يفتح الشاشة: تكامل مضبوط وطابور فارغ قد
     * يعني أن لا أحد كتب هويته، لا أن لا أحد درس. ورقم لا يقال يجعل
     * المسؤول يفتش في الشيفرة عن عطل ليس فيها.
     */
    public function coverage()
    {
        $this->install_schema();
        $out = array('students' => 0, 'with_id' => 0, 'percent' => 0);

        try {
            $out['students'] = (int) $this->db->where('is_instructor', 0)
                                              ->where('status', 1)
                                              ->count_all_results('users');
            $out['with_id']  = (int) $this->db->where('is_instructor', 0)
                                              ->where('status', 1)
                                              ->where('tq_national_id IS NOT NULL')
                                              ->where("TRIM(tq_national_id) <> ''")
                                              ->count_all_results('users');
        } catch (Throwable $e) { $this->db->reset_query(); }

        if ($out['students'] > 0) {
            $out['percent'] = (int) floor($out['with_id'] * 100 / $out['students']);
        }
        return $out;
    }

    /** يعيد الفاشل إلى الطابور — بيد المسؤول لا تلقائيا. */
    public function requeue($id = 0)
    {
        $this->install_schema();
        try {
            $this->db->where('state', 'failed');
            if ((int) $id > 0) $this->db->where('id', (int) $id);
            $this->db->update('tq_nelc_queue', array(
                'state' => 'queued', 'attempts' => 0, 'next_try_at' => time(), 'last_error' => null,
            ));
            return $this->db->affected_rows();
        } catch (Throwable $e) { $this->db->reset_query(); return 0; }
    }

    /** ينظف ما أرسل ومضى عليه شهران — الطابور سجل عمل لا أرشيف. */
    public function purge($days = 60)
    {
        $this->install_schema();
        try {
            $this->db->where('state', 'sent')
                     ->where('sent_at <', time() - max(1, (int) $days) * 86400)
                     ->delete('tq_nelc_queue');
            return $this->db->affected_rows();
        } catch (Throwable $e) { $this->db->reset_query(); return 0; }
    }
}
