<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-PUSH — إشعارات التطبيق عبر Firebase Cloud Messaging (HTTP v1).
 *
 * القناة الرابعة بعد «داخل المنصة» والبريد وواتساب، **وهي مرآة الأولى**:
 * الإشعار داخل المنصة صف ينتظر أن يفتح صاحبه شاشته، والدفع هو ذلك الصف
 * نفسه يطرق جواله والتطبيق مغلق. فلا تفضيل خامس يخترع له — يتبع مربع
 * «داخل المنصة» في شاشة التنبيهات، ومن أطفأ ذاك لا يطرق جواله أحد.
 *
 * **والقاعدة الحاكمة: لا محرك ثان.** لا يكتب أحد إشعار دفع بيده؛
 * `Taqdar_admin_model::push_notification()` وطابور `Taqdar_events_model`
 * ينتهيان إلى `send_user()` هنا، فكل ما يكتب في `notifications` يصل.
 *
 * ثلاث قواعد لا تخرق — وهي قواعد تاب وأبل نفسها:
 *
 * 1. **المفتاح في `settings` لا في الشيفرة** (`tq_fcm_service_account`):
 *    المستودع عام والنشر `git reset --hard`. والملف يلصق كاملا في اللوحة
 *    ولا يعرض بعدها أبدا — تعرض بصمته (المشروع والبريد ومعرف المفتاح).
 * 2. **ولا مكتبة Google**: SDK الرسمي يحتاج Composer، وهذا مستودع بلا
 *    `vendor/` وبلا خطوة بناء. والذي يحتاجه الإرسال شيئان: JWT موقع
 *    بـRS256 من المفتاح الخاص (`openssl_sign`)، يبدل برمز وصول ساعة من
 *    `token_uri` — ثم `POST messages:send`. وهو ما يفعله SDK بعينه.
 * 3. **ولا يرمي شيئا أبدا**: النداء يقع داخل ويبهوك دفع وفي شاشة إدارة،
 *    واستثناء هنا يعني دفعة بلا تفعيل. كل فشل يرد `false` ويسجل سببه.
 *
 * **والجهاز الميت يحذف عند أول رد يقول ذلك** (`UNREGISTERED` · رمز غير
 * صالح · مشروع آخر): التطبيق يحذف ويعاد تثبيته فيولد رمزا جديدا، والقديم
 * يبقى في الجدول يرسل إليه في كل إشعار إلى الأبد إن لم يحذف.
 */
class Taqdar_push_model extends CI_Model
{
    const SCOPE     = 'https://www.googleapis.com/auth/firebase.messaging';
    const SCHEMA_V  = '1';
    /* مهلتان قصيرتان: النداء قد يقع في ويبهوك دفع، وخادم جوجل البطيء لا
       يجوز أن يؤخر تفعيل اشتراك. */
    const T_CONNECT = 4;
    const T_TOTAL   = 8;
    /* سقف الأجهزة لكل حساب: من يسجل من عشرين جهازا لا يحتاج عشرين طرقة،
       والسقف يحد ما يصرفه إشعار واحد. والأقدم يسقط. */
    const MAX_DEVICES = 10;

    public $last_error = '';

    /* =====================================================================
       البنية
       ===================================================================== */

    public function install_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        try {
            if ((string) get_settings('tq_push_schema_v') === self::SCHEMA_V
                && $this->db->table_exists('tq_push_devices')) {
                return;
            }

            /* الرمز يخزن كما هو — الإرسال يحتاجه — والفرادة على بصمته:
               رموز FCM تتجاوز مئة وخمسين حرفا، وفهرس فريد على نص طويل
               بـutf8mb4 يتجاوز حد مفتاح InnoDB. */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_push_devices` (
                   `id`          INT(11)      NOT NULL AUTO_INCREMENT,
                   `user_id`     INT(11)      NOT NULL,
                   `token`       VARCHAR(512) NOT NULL,
                   `token_hash`  CHAR(64)     NOT NULL,
                   `platform`    VARCHAR(16)  NOT NULL DEFAULT "android",
                   `app_version` VARCHAR(32)  NULL,
                   `created_at`  INT(11)      NOT NULL DEFAULT 0,
                   `last_seen_at` INT(11)     NOT NULL DEFAULT 0,
                   `last_ok_at`  INT(11)      NULL,
                   PRIMARY KEY (`id`),
                   UNIQUE KEY `uq_token` (`token_hash`),
                   KEY `ix_user` (`user_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            /* صف لكل محاولة إرسال — ونص الإشعار لا يكتب، كسجل واتساب:
               السجل يجيب «أوصل؟ ولماذا لا؟» لا «ماذا قيل؟». */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_push_log` (
                   `id`         INT(11)      NOT NULL AUTO_INCREMENT,
                   `user_id`    INT(11)      NOT NULL DEFAULT 0,
                   `device_id`  INT(11)      NOT NULL DEFAULT 0,
                   `platform`   VARCHAR(16)  NULL,
                   `purpose`    VARCHAR(32)  NOT NULL DEFAULT "notice",
                   `ok`         TINYINT(1)   NOT NULL DEFAULT 0,
                   `http`       INT(11)      NOT NULL DEFAULT 0,
                   `message_id` VARCHAR(190) NULL,
                   `error`      VARCHAR(250) NULL,
                   `created_at` INT(11)      NOT NULL DEFAULT 0,
                   PRIMARY KEY (`id`),
                   KEY `ix_created` (`created_at`),
                   KEY `ix_user` (`user_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $this->put_setting('tq_push_schema_v', self::SCHEMA_V);
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-PUSH schema: ' . $e->getMessage());
        }
    }

    /* =====================================================================
       المفتاح
       ===================================================================== */

    /**
     * يفحص ملف حساب الخدمة كما لصق — قبل أن يحفظ.
     *
     * ويرد `['ok'=>bool, 'sa'=>array, 'error'=>string]`. والفحص على
     * **الحقول التي يحتاجها التوقيع** لا على الشكل: ملف `google-services.json`
     * (ملف التطبيق لا الخادم) يلصق هنا خطأ كثيرا، وهو JSON صالح تماما —
     * فلو قبل لحفظ ثم فشل كل إرسال بلا سبب يفهمه أحد.
     */
    public function parse_service_account($json)
    {
        $json = trim((string) $json);
        if ($json === '') return array('ok' => false, 'error' => 'الصق محتوى الملف كاملا.');

        $sa = json_decode($json, true);
        if (!is_array($sa)) {
            return array('ok' => false, 'error' => 'المحتوى ليس JSON صالحا — الصق الملف كما هو من أوله إلى آخره.');
        }
        if (isset($sa['project_info']) || isset($sa['client'])) {
            return array('ok' => false, 'error' => 'هذا ملف google-services.json الخاص بالتطبيق، لا مفتاح الخادم. المطلوب ملف Service Account من Firebase ← Project settings ← Service accounts ← Generate new private key.');
        }
        if ((isset($sa['type']) ? $sa['type'] : '') !== 'service_account') {
            return array('ok' => false, 'error' => 'الملف ليس حساب خدمة (type ليس service_account).');
        }
        foreach (array('project_id', 'private_key', 'client_email') as $k) {
            if (empty($sa[$k]) || !is_string($sa[$k])) {
                return array('ok' => false, 'error' => 'الحقل ' . $k . ' غائب من الملف.');
            }
        }
        if (!preg_match('/^[a-z0-9-]{4,64}$/', $sa['project_id'])) {
            return array('ok' => false, 'error' => 'معرف المشروع في الملف غير صالح.');
        }
        if (!openssl_pkey_get_private($sa['private_key'])) {
            return array('ok' => false, 'error' => 'المفتاح الخاص في الملف لا يقرأ — لعل الملف قص أو عدل.');
        }
        if (empty($sa['token_uri'])) $sa['token_uri'] = 'https://oauth2.googleapis.com/token';

        /* `token_uri` يرسل إليه JWT موقع — فلا يقبل إلا عنوان جوجل. ملف
           معدل يوجهه إلى خادم آخر يجعلنا نسلم توقيعا صالحا لمن يشاء. */
        if (!preg_match('~^https://oauth2\.googleapis\.com/token$~', $sa['token_uri'])) {
            return array('ok' => false, 'error' => 'عنوان token_uri في الملف ليس عنوان جوجل.');
        }

        return array('ok' => true, 'sa' => $sa, 'error' => '');
    }

    /** الحساب المحفوظ مفحوصا، أو `null`. */
    public function service_account()
    {
        static $cache = null;
        if ($cache !== null) return $cache ?: null;

        $raw = (string) get_settings('tq_fcm_service_account');
        if (trim($raw) === '') { $cache = false; return null; }

        $r = $this->parse_service_account($raw);
        $cache = $r['ok'] ? $r['sa'] : false;
        return $cache ?: null;
    }

    /** أيرسل؟ — مفتاح محفوظ صالح. وبلاه لا شيء يتغير في المنصة. */
    public function ready()
    {
        return $this->service_account() !== null;
    }

    /**
     * بصمة المفتاح للعرض — ولا سر فيها.
     * @return array|null project_id · client_email · key_id
     */
    public function fingerprint()
    {
        $sa = $this->service_account();
        if (!$sa) return null;
        return array(
            'project_id'   => (string) $sa['project_id'],
            'client_email' => (string) $sa['client_email'],
            'key_id'       => substr((string) (isset($sa['private_key_id']) ? $sa['private_key_id'] : ''), 0, 8),
        );
    }

    /* =====================================================================
       رمز الوصول
       ===================================================================== */

    /**
     * رمز وصول ساعة من جوجل، مكاشا في `settings` حتى قبيل انتهائه.
     *
     * والكاش ليس أداء وحده: جوجل تحد إصدار الرموز، وإشعار لكل طالب في
     * دورة كرون واحدة يصدر مئة رمز بلا كاش. والكاش يحمل **معرف المفتاح**،
     * فمفتاح يبدل في اللوحة يبطل رمز القديم في الطلب نفسه.
     */
    public function access_token($force = false)
    {
        $sa = $this->service_account();
        if (!$sa) { $this->last_error = 'لا مفتاح محفوظ.'; return ''; }

        $kid = (string) (isset($sa['private_key_id']) ? $sa['private_key_id'] : '');
        $fp  = hash('sha256', $sa['client_email'] . '|' . $kid);

        if (!$force) {
            $c = json_decode((string) get_settings('tq_fcm_access_cache'), true);
            if (is_array($c) && isset($c['fp'], $c['token'], $c['exp'])
                && $c['fp'] === $fp && (int) $c['exp'] > time() + 120) {
                return (string) $c['token'];
            }
        }

        $now = time();
        $h = $this->b64url(json_encode(array('alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid)));
        $c = $this->b64url(json_encode(array(
            'iss'   => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $sa['token_uri'],
            'iat'   => $now,
            'exp'   => $now + 3600,
        )));

        $sig = '';
        if (!@openssl_sign($h . '.' . $c, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            $this->last_error = 'تعذر التوقيع بالمفتاح الخاص.';
            return '';
        }

        $r = $this->http($sa['token_uri'], array('Content-Type: application/x-www-form-urlencoded'),
            http_build_query(array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $h . '.' . $c . '.' . $this->b64url($sig),
            )));

        $j = json_decode((string) $r['body'], true);
        if ($r['code'] !== 200 || empty($j['access_token'])) {
            /* `invalid_grant` يعني مفتاحا حذف من Google Cloud أو ساعة خادم
               منحرفة — والرسالة تقول الاثنين، فلا يظن أحد الملف تالفا. */
            $err = is_array($j) && isset($j['error']) ? (string) $j['error'] : ('HTTP ' . $r['code']);
            $this->last_error = 'رفضت جوجل المفتاح (' . $err . ')'
                . ($err === 'invalid_grant' ? ' — لعل المفتاح حذف من Google Cloud، أو ساعة الخادم منحرفة.' : '')
                . ($r['error'] !== '' ? ' ' . $r['error'] : '');
            return '';
        }

        $this->put_setting('tq_fcm_access_cache', json_encode(array(
            'fp'    => $fp,
            'token' => (string) $j['access_token'],
            'exp'   => $now + (int) (isset($j['expires_in']) ? $j['expires_in'] : 3600),
        )));

        return (string) $j['access_token'];
    }

    /**
     * فحص الاتصال من اللوحة: أتقبل جوجل المفتاح؟ وأيقبل FCM المشروع؟
     *
     * والثاني بإرسال **`validate_only`** إلى رمز جهاز مخترع: الرد
     * `INVALID_ARGUMENT` على الرمز يعني أن كل ما قبله صحيح — المفتاح
     * والمشروع وتفعيل واجهة FCM وصلاحية الحساب. و`PERMISSION_DENIED` أو
     * `404` يعني واحدا من هذه، والرسالة تسميه. ولا يصل شيء إلى أحد.
     *
     * @return array ok · steps[] (label, ok, note)
     */
    public function probe()
    {
        $steps = array();
        $sa = $this->service_account();
        if (!$sa) {
            return array('ok' => false, 'steps' => array(
                array('label' => 'مفتاح محفوظ', 'ok' => false, 'note' => 'لا مفتاح صالح محفوظ.')));
        }
        $steps[] = array('label' => 'مفتاح محفوظ', 'ok' => true,
                         'note' => $sa['project_id'] . ' · ' . $sa['client_email']);

        $tok = $this->access_token(true);
        $steps[] = array('label' => 'جوجل تقبل المفتاح (OAuth)', 'ok' => $tok !== '',
                         'note' => $tok !== '' ? 'صدر رمز وصول صالح ساعة.' : $this->last_error);
        if ($tok === '') return array('ok' => false, 'steps' => $steps);

        $r = $this->http($this->send_url($sa),
            array('Authorization: Bearer ' . $tok, 'Content-Type: application/json'),
            json_encode(array('validate_only' => true, 'message' => array(
                'token' => 'tq-probe-not-a-real-device',
                'notification' => array('title' => 'probe', 'body' => 'probe'),
            ))));
        $st = $this->fcm_status($r['body']);

        $ok = ($r['code'] === 400 && $st === 'INVALID_ARGUMENT') || $r['code'] === 200;
        $steps[] = array('label' => 'Firebase Cloud Messaging يقبل المشروع', 'ok' => $ok,
            'note' => $ok ? 'واجهة FCM مفعلة والحساب مخول بالإرسال.'
                          : ('HTTP ' . $r['code'] . ' ' . $st . ' — ' . $this->fcm_message($r['body'])));

        return array('ok' => $ok, 'steps' => $steps);
    }

    /* =====================================================================
       الأجهزة
       ===================================================================== */

    /**
     * يسجل رمز جهاز لصاحب الحساب — ويعيد ربطه إن كان لغيره.
     *
     * الرمز للجهاز لا للحساب: من خرج من حساب ودخل بآخر على الجوال نفسه
     * يسلم الرمز نفسه، وبقاؤه على الأول يوصل إشعارات الأول إلى جوال
     * الثاني — أي درجات طالب إلى جوال أخيه.
     *
     * @return array|null الصف بعد الحفظ
     */
    public function register($user_id, $token, $platform = 'android', $app_version = '')
    {
        $this->install_schema();
        $user_id = (int) $user_id;
        $token   = trim((string) $token);
        if ($user_id <= 0 || !$this->token_ok($token)) return null;

        $platform = in_array($platform, array('android', 'ios', 'web'), true) ? $platform : 'android';
        $hash = hash('sha256', $token);
        $now  = time();

        try {
            $row = $this->db->where('token_hash', $hash)->get('tq_push_devices')->row_array();
            $set = array(
                'user_id'      => $user_id,
                'token'        => $token,
                'platform'     => $platform,
                'app_version'  => mb_substr((string) $app_version, 0, 32) ?: null,
                'last_seen_at' => $now,
            );
            if ($row) {
                /* تسجيل بلا إصدار لا يمحو الإصدار المعروف. */
                if ($set['app_version'] === null) unset($set['app_version']);
                $this->db->where('id', (int) $row['id'])->update('tq_push_devices', $set);
                $id = (int) $row['id'];
            } else {
                $set['token_hash'] = $hash;
                $set['created_at'] = $now;
                /* `INSERT IGNORE` لا `insert`: تسجيلان متزامنان للرمز نفسه
                   (التطبيق يسجل عند الإقلاع وعند تجدد الرمز معا) يمر
                   منهما فحص «أموجود؟» كلاهما — والفرادة هي الحكم. */
                $this->db->query('INSERT IGNORE INTO `tq_push_devices`
                    (`user_id`,`token`,`token_hash`,`platform`,`app_version`,`created_at`,`last_seen_at`)
                    VALUES (?,?,?,?,?,?,?)', array($user_id, $token, $hash, $platform,
                    $set['app_version'], $now, $now));
                $id = (int) $this->db->insert_id();
                if ($id <= 0) {
                    $r2 = $this->db->where('token_hash', $hash)->get('tq_push_devices')->row_array();
                    $id = $r2 ? (int) $r2['id'] : 0;
                }
            }
            $this->trim_devices($user_id);
            return $id ? $this->db->where('id', $id)->get('tq_push_devices')->row_array() : null;
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-PUSH register: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * يلغي رمزا — ولصاحبه وحده إن سمي.
     * والملكية في `WHERE` لا في فحص قبله: رمز مخمن لا يطفئ جوال غيره.
     */
    public function unregister($token, $user_id = null)
    {
        $this->install_schema();
        $token = trim((string) $token);
        if ($token === '') return 0;
        try {
            $this->db->where('token_hash', hash('sha256', $token));
            if ($user_id !== null) $this->db->where('user_id', (int) $user_id);
            $this->db->delete('tq_push_devices');
            return (int) $this->db->affected_rows();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return 0;
        }
    }

    /** يلغي كل أجهزة الحساب — «اخرج من كل الأجهزة». */
    public function unregister_all($user_id)
    {
        $this->install_schema();
        try {
            $this->db->where('user_id', (int) $user_id)->delete('tq_push_devices');
            return (int) $this->db->affected_rows();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return 0;
        }
    }

    public function devices_of($user_id)
    {
        $this->install_schema();
        try {
            return $this->db->where('user_id', (int) $user_id)
                            ->order_by('last_seen_at', 'DESC')
                            ->get('tq_push_devices')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
    }

    public function has_devices($user_id)
    {
        return count($this->devices_of($user_id)) > 0;
    }

    /* =====================================================================
       الإرسال
       ===================================================================== */

    /**
     * يرسل إشعارا إلى كل أجهزة صاحبه.
     *
     * @param array $data  مفاتيح تصل إلى التطبيق (`type` · `notification_id`
     *                     …) — وFCM يشترط قيمها نصوصا، فتحول هنا.
     * @return int عدد الأجهزة التي قبلت FCM الإرسال إليها
     */
    public function send_user($user_id, $title, $body, $data = array(), $purpose = 'notice')
    {
        $this->last_error = '';
        if (!$this->ready()) return 0;

        $devices = $this->devices_of($user_id);
        if (!$devices) return 0;

        $sent = 0;
        foreach ($devices as $d) {
            if ($this->send_device($d, $title, $body, $data, $purpose)) $sent++;
        }
        return $sent;
    }

    /** إرسال إلى جهاز واحد — وحذفه إن قالت FCM إنه ميت. */
    public function send_device(array $d, $title, $body, $data = array(), $purpose = 'notice')
    {
        $sa  = $this->service_account();
        $tok = $sa ? $this->access_token() : '';
        if ($tok === '') {
            $this->log($d, $purpose, false, 0, null, $this->last_error ?: 'لا رمز وصول');
            return false;
        }

        $payload = json_encode(array('message' => $this->message($d, $title, $body, $data)),
                               JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $r = $this->http($this->send_url($sa),
            array('Authorization: Bearer ' . $tok, 'Content-Type: application/json'), $payload);

        /* رمز الوصول قد يبطل قبل موعده (مفتاح دور في Google Cloud):
           يجدد مرة ويعاد الطلب مرة، لا حلقة. */
        if ($r['code'] === 401) {
            $tok = $this->access_token(true);
            if ($tok !== '') {
                $r = $this->http($this->send_url($sa),
                    array('Authorization: Bearer ' . $tok, 'Content-Type: application/json'), $payload);
            }
        }

        if ($r['code'] === 200) {
            $j = json_decode((string) $r['body'], true);
            $mid = is_array($j) && isset($j['name']) ? (string) $j['name'] : null;
            try {
                $this->db->where('id', (int) $d['id'])->update('tq_push_devices', array('last_ok_at' => time()));
            } catch (Throwable $e) { $this->db->reset_query(); }
            $this->log($d, $purpose, true, 200, $mid, null);
            return true;
        }

        $st  = $this->fcm_status($r['body']);
        $msg = $r['error'] !== '' ? $r['error'] : $this->fcm_message($r['body']);

        /* الجهاز الميت يحذف — وثلاث صور لا واحدة. `INVALID_ARGUMENT` وحده
           لا يكفي: يقع كذلك على حمولة خاطئة، وحذف أجهزة الناس كلها لأننا
           أخطأنا في الحمولة أسوأ من الإرسال إلى ميت. فالشرط أن يسمي الرد
           حقل الرمز. */
        $dead = ($st === 'UNREGISTERED' || $st === 'SENDER_ID_MISMATCH'
                 || ($r['code'] === 404)
                 || ($st === 'INVALID_ARGUMENT' && strpos((string) $r['body'], 'message.token') !== false));
        if ($dead) {
            try {
                $this->db->where('id', (int) $d['id'])->delete('tq_push_devices');
            } catch (Throwable $e) { $this->db->reset_query(); }
            $msg = 'جهاز لم يعد مسجلا (' . ($st ?: 'HTTP ' . $r['code']) . ') — حذف من القائمة.';
        }

        $this->last_error = $msg;
        $this->log($d, $purpose, false, (int) $r['code'], null, $msg);
        return false;
    }

    /**
     * الرسالة بشكل FCM v1.
     *
     * `notification` لا `data` وحده: رسالة بيانات لا تظهر والتطبيق مغلق
     * إلا إن بناها التطبيق بنفسه، وعلى iOS قد لا يوقظه النظام أصلا. و
     * `data` معها يحمل ما يفتح به التطبيق الشاشة الصحيحة عند النقر.
     */
    private function message(array $d, $title, $body, array $data)
    {
        $title = mb_substr(trim(strip_tags((string) $title)), 0, 120);
        $body  = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $body))), 0, 400);

        $flat = array('click_action' => 'FLUTTER_NOTIFICATION_CLICK');
        foreach ($data as $k => $v) {
            if (is_scalar($v) && $v !== '' && $v !== null) $flat[(string) $k] = (string) $v;
        }

        $badge = $this->unread_count((int) $d['user_id']);

        return array(
            'token'        => (string) $d['token'],
            'notification' => array('title' => $title, 'body' => $body),
            'data'         => $flat,
            'android'      => array(
                'priority'     => 'HIGH',
                'notification' => array('channel_id' => 'taqdar_default', 'sound' => 'default'),
            ),
            'apns'         => array(
                'headers' => array('apns-priority' => '10'),
                'payload' => array('aps' => array_filter(array(
                    'sound' => 'default',
                    'badge' => $badge >= 0 ? $badge : null,
                ), function ($v) { return $v !== null; })),
            ),
        );
    }

    /** عدد غير المقروء — شارة أيقونة التطبيق على iOS. و`-1` إن تعذر. */
    private function unread_count($user_id)
    {
        try {
            return (int) $this->db->where('to_user', (int) $user_id)->where('status', 0)
                                  ->count_all_results('notifications');
        } catch (Throwable $e) {
            $this->db->reset_query();
            return -1;
        }
    }

    /* =====================================================================
       السجل والأرقام — للوحة
       ===================================================================== */

    private function log(array $d, $purpose, $ok, $http, $mid, $error)
    {
        try {
            $this->db->insert('tq_push_log', array(
                'user_id'    => (int) (isset($d['user_id']) ? $d['user_id'] : 0),
                'device_id'  => (int) (isset($d['id']) ? $d['id'] : 0),
                'platform'   => isset($d['platform']) ? (string) $d['platform'] : null,
                'purpose'    => mb_substr((string) $purpose, 0, 32),
                'ok'         => $ok ? 1 : 0,
                'http'       => (int) $http,
                'message_id' => $mid !== null ? mb_substr($mid, 0, 190) : null,
                'error'      => $error !== null ? mb_substr((string) $error, 0, 250) : null,
                'created_at' => time(),
            ));
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
    }

    /** السجل الأخير بأسماء أصحابه. */
    public function recent_log($limit = 25)
    {
        $this->install_schema();
        try {
            return $this->db->select('l.*, u.first_name, u.last_name, u.email')
                            ->from('tq_push_log l')
                            ->join('users u', 'u.id = l.user_id', 'left')
                            ->order_by('l.id', 'DESC')->limit((int) $limit)
                            ->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
    }

    /** أرقام الشاشة: أجهزة بمنصاتها، وحسابات، وإرسال آخر يوم. */
    public function stats()
    {
        $this->install_schema();
        $out = array('devices' => 0, 'users' => 0, 'android' => 0, 'ios' => 0, 'web' => 0,
                     'ok_24h' => 0, 'fail_24h' => 0);
        try {
            foreach ($this->db->query('SELECT platform, COUNT(*) n FROM tq_push_devices GROUP BY platform')
                              ->result_array() as $r) {
                $out[(string) $r['platform']] = (int) $r['n'];
                $out['devices'] += (int) $r['n'];
            }
            $out['users'] = (int) $this->db->query('SELECT COUNT(DISTINCT user_id) n FROM tq_push_devices')
                                           ->row()->n;
            $since = time() - 86400;
            foreach ($this->db->query('SELECT ok, COUNT(*) n FROM tq_push_log WHERE created_at >= ? GROUP BY ok',
                                      array($since))->result_array() as $r) {
                $out[(int) $r['ok'] ? 'ok_24h' : 'fail_24h'] = (int) $r['n'];
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
        return $out;
    }

    /** أحدث الأجهزة المسجلة بأصحابها — منها يختار المسؤول جهاز الفحص. */
    public function recent_devices($limit = 30)
    {
        $this->install_schema();
        try {
            return $this->db->select('d.id, d.user_id, d.platform, d.app_version, d.last_seen_at, d.last_ok_at,
                                      u.first_name, u.last_name, u.email')
                            ->from('tq_push_devices d')
                            ->join('users u', 'u.id = d.user_id', 'left')
                            ->order_by('d.last_seen_at', 'DESC')->limit((int) $limit)
                            ->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
    }

    public function device($id)
    {
        $this->install_schema();
        try {
            return $this->db->where('id', (int) $id)->get('tq_push_devices')->row_array() ?: null;
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
    }

    /** تنظيف السجل — ينادى من `taqdar_cron purge`. */
    public function purge_log($days = 30)
    {
        try {
            $this->db->where('created_at <', time() - 86400 * (int) $days)->delete('tq_push_log');
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    /** رمز FCM: حروف وأرقام و`:_-` بطول معقول. وما سواه لا يخزن. */
    public function token_ok($token)
    {
        return is_string($token) && preg_match('/^[A-Za-z0-9:_\-\.]{20,500}$/', $token) === 1;
    }

    private function trim_devices($user_id)
    {
        try {
            $ids = array_column($this->db->select('id')->where('user_id', (int) $user_id)
                ->order_by('last_seen_at', 'DESC')->get('tq_push_devices')->result_array(), 'id');
            if (count($ids) > self::MAX_DEVICES) {
                $this->db->where_in('id', array_slice($ids, self::MAX_DEVICES))->delete('tq_push_devices');
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
    }

    private function send_url(array $sa)
    {
        return 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($sa['project_id']) . '/messages:send';
    }

    /** رمز خطأ FCM (`UNREGISTERED` …) من تفاصيل الرد، وإلا حاله العامة. */
    private function fcm_status($body)
    {
        $j = json_decode((string) $body, true);
        if (!is_array($j) || !isset($j['error'])) return '';
        if (!empty($j['error']['details'])) {
            foreach ((array) $j['error']['details'] as $det) {
                if (!empty($det['errorCode'])) return (string) $det['errorCode'];
            }
        }
        return isset($j['error']['status']) ? (string) $j['error']['status'] : '';
    }

    private function fcm_message($body)
    {
        $j = json_decode((string) $body, true);
        return is_array($j) && isset($j['error']['message'])
            ? mb_substr((string) $j['error']['message'], 0, 200) : '';
    }

    private function b64url($s)
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    /** @return array code · body · error */
    private function http($url, array $headers, $body)
    {
        if (!function_exists('curl_init')) return array('code' => 0, 'body' => '', 'error' => 'cURL غير متاح');
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_CONNECTTIMEOUT => self::T_CONNECT,
            CURLOPT_TIMEOUT        => self::T_TOTAL,
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        $out  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = $out === false ? (string) curl_error($ch) : '';
        curl_close($ch);
        return array('code' => $code, 'body' => (string) $out, 'error' => $err);
    }

    public function put_setting($key, $value)
    {
        try {
            $has = $this->db->where('key', $key)->count_all_results('settings') > 0;
            if ($has) $this->db->where('key', $key)->update('settings', array('value' => (string) $value));
            else      $this->db->insert('settings', array('key' => $key, 'value' => (string) $value));
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
    }
}
