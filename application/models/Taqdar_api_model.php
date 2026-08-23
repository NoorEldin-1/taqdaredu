<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * بنية واجهة البرمجة — الرموز وحد الطلبات.
 *
 * التطبيق لا يحمل كعكة ولا جلسة: البوابة تعرف صاحبها من ترويسة
 * `Authorization` وحدها. وهذا الملف هو من يصدر تلك الرموز ويتحقق منها
 * ويبطلها، وهو من يعد الطلبات فيرد الزائد منها.
 *
 * **لماذا رمز عشوائي لا JWT؟**
 * `libraries/JWT.php` طرف ثالث قديم لا يعدل (انظر CLAUDE.md)، وأخطر منه
 * أن JWT لا يبطل: من سرق رمزا يبقى به إلى انتهاء صلاحيته مهما فعل صاحبه.
 * والرمز هنا صف في جدول — «اخرج من كل الأجهزة» شرط `UPDATE` واحد، وتغيير
 * كلمة المرور يبطل ما سواه في اللحظة نفسها. والثمن استعلام واحد لكل طلب،
 * وهو لا شيء في تطبيق يقرأ عشرات الاستعلامات لكل شاشة.
 *
 * **ولا يخزن الرمز نفسه.** يخزن `sha256` منه، فمن قرأ الجدول لا ينتحل
 * أحدا — كما لا تخزن كلمة المرور نصا. والبحث بالتلبيدة فهي المفهرسة.
 *
 * **زوجان لا رمز واحد:** رمز وصول قصير (خمس عشرة دقيقة) يحمل في كل طلب،
 * ورمز تجديد طويل (ثلاثون يوما) لا يخرج إلا مرة عند التجديد. فسرقة
 * الأول تنتهي بنفسها، والثاني لا يمر على الشبكة إلا نادرا.
 */
class Taqdar_api_model extends CI_Model
{
    /** عمر رمز الوصول — قصير عمدا: هو الذي يسافر في كل طلب. */
    const ACCESS_TTL  = 900;          // خمس عشرة دقيقة

    /** عمر رمز التجديد — طويل: التطبيق لا يسأل كلمة المرور كل يوم. */
    const REFRESH_TTL = 2592000;      // ثلاثون يوما

    /** بادئتان تميزان النوع في السجلات وفي ماسحات الأسرار. */
    const ACCESS_PREFIX  = 'tqa_';
    const REFRESH_PREFIX = 'tqr_';

    /* ================================================================
       المخطط — ينشأ وقت التشغيل كسائر جداول تقدر
       ================================================================ */

    /**
     * ينشئ الجدولين إن غابا. يستدعى من كل مدخل، ويحرسه علم ساكن فلا
     * يتكرر في الطلب الواحد.
     */
    public function ensure_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        if (!$this->db->table_exists('tq_api_tokens')) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `tq_api_tokens` (
                    `id`           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id`      INT(10) UNSIGNED NOT NULL,
                    `kind`         ENUM('access','refresh') NOT NULL DEFAULT 'access',
                    `token_hash`   CHAR(64) NOT NULL,
                    `family`       CHAR(32) NOT NULL DEFAULT '',
                    `device_name`  VARCHAR(120) DEFAULT NULL,
                    `device_id`    VARCHAR(120) DEFAULT NULL,
                    `platform`     VARCHAR(32)  DEFAULT NULL,
                    `app_version`  VARCHAR(32)  DEFAULT NULL,
                    `ip`           VARCHAR(45)  DEFAULT NULL,
                    `user_agent`   VARCHAR(255) DEFAULT NULL,
                    `created_at`   INT(11) NOT NULL DEFAULT 0,
                    `expires_at`   INT(11) NOT NULL DEFAULT 0,
                    `last_used_at` INT(11) NOT NULL DEFAULT 0,
                    `revoked_at`   INT(11) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_token_hash` (`token_hash`),
                    KEY `ix_user_kind` (`user_id`,`kind`,`revoked_at`),
                    KEY `ix_family` (`family`),
                    KEY `ix_expiry` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$this->db->table_exists('tq_api_rate')) {
            /* دلو لكل (هوية، نافذة). المفتاح الأساسي هو الدلو نفسه، فالعد
               `INSERT ... ON DUPLICATE KEY UPDATE` واحد لا قراءة ثم كتابة —
               وطلبان متزامنان لا يقرآن العدد نفسه فيمرا معا. */
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `tq_api_rate` (
                    `bucket`       VARCHAR(160) NOT NULL,
                    `hits`         INT(11) NOT NULL DEFAULT 0,
                    `window_start` INT(11) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`bucket`),
                    KEY `ix_window` (`window_start`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    /* ================================================================
       إصدار الرموز
       ================================================================ */

    /** رمز خام لا يخزن — يعود لصاحبه مرة واحدة ولا سبيل إلى قراءته بعدها. */
    private function mint($prefix)
    {
        return $prefix . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** التلبيدة هي ما يخزن ويبحث به. */
    public function fingerprint($token)
    {
        return hash('sha256', (string) $token);
    }

    /**
     * زوج جديد لجهاز. `family` يربط سلسلة التجديد الواحدة: كل تجديد يبقى
     * في عائلته، فإن استعمل رمز تجديد مبطل عرف أن السلسلة سربت وقطعت كلها.
     */
    public function issue_pair($user_id, $device = array(), $family = null)
    {
        $this->ensure_schema();

        $user_id = (int) $user_id;
        $now     = time();
        $family  = $family ?: bin2hex(random_bytes(16));

        $access  = $this->mint(self::ACCESS_PREFIX);
        $refresh = $this->mint(self::REFRESH_PREFIX);

        $base = array(
            'user_id'      => $user_id,
            'family'       => $family,
            'device_name'  => $this->clip($device, 'device_name', 120),
            'device_id'    => $this->clip($device, 'device_id', 120),
            'platform'     => $this->clip($device, 'platform', 32),
            'app_version'  => $this->clip($device, 'app_version', 32),
            'ip'           => substr((string) $this->input->ip_address(), 0, 45),
            'user_agent'   => substr((string) $this->input->user_agent(), 0, 255),
            'created_at'   => $now,
            'last_used_at' => $now,
            'revoked_at'   => 0,
        );

        $this->db->insert('tq_api_tokens', array_merge($base, array(
            'kind'       => 'access',
            'token_hash' => $this->fingerprint($access),
            'expires_at' => $now + self::ACCESS_TTL,
        )));
        $this->db->insert('tq_api_tokens', array_merge($base, array(
            'kind'       => 'refresh',
            'token_hash' => $this->fingerprint($refresh),
            'expires_at' => $now + self::REFRESH_TTL,
        )));

        $this->gc();

        return array(
            'access_token'       => $access,
            'refresh_token'      => $refresh,
            'token_type'         => 'Bearer',
            'expires_in'         => self::ACCESS_TTL,
            'refresh_expires_in' => self::REFRESH_TTL,
            'family'             => $family,
        );
    }

    private function clip($arr, $key, $len)
    {
        $v = isset($arr[$key]) ? trim((string) $arr[$key]) : '';
        return $v === '' ? null : mb_substr($v, 0, $len);
    }

    /* ================================================================
       التحقق
       ================================================================ */

    /**
     * يقرأ رمز الوصول ويرد صاحبه، أو رمز خطأ يقول **لماذا** رفض.
     *
     * والتفريق مقصود: `token_expired` يعالجه التطبيق بتجديد صامت، و
     * `token_invalid` يعالجه بإخراج المستخدم إلى شاشة الدخول. ورد واحد
     * لهما يجعل كل انتهاء صلاحية إخراجا — وهو كل خمس عشرة دقيقة.
     */
    public function authenticate($token)
    {
        $this->ensure_schema();

        $token = (string) $token;
        if ($token === '' || strpos($token, self::ACCESS_PREFIX) !== 0) {
            return array('ok' => false, 'code' => 'token_invalid');
        }

        $row = $this->db->where('token_hash', $this->fingerprint($token))
                        ->where('kind', 'access')
                        ->get('tq_api_tokens')->row_array();

        if (!$row)                             return array('ok' => false, 'code' => 'token_invalid');
        if ((int) $row['revoked_at'] > 0)      return array('ok' => false, 'code' => 'token_revoked');
        if ((int) $row['expires_at'] < time()) return array('ok' => false, 'code' => 'token_expired');

        $user = $this->db->where('id', (int) $row['user_id'])->get('users')->row_array();
        if (!$user) return array('ok' => false, 'code' => 'token_invalid');

        /* حساب أوقف بعد إصدار رمزه لا يبقى داخلا برمز قديم. */
        if ((int) $user['status'] !== 1) {
            return array('ok' => false, 'code' => 'account_disabled');
        }

        /* `last_used_at` يكتب كل دقيقة لا كل طلب: عمود يفيد في «أجهزتك»
           ولا يستحق كتابة على كل نداء يقرأ شاشة. */
        if (time() - (int) $row['last_used_at'] > 60) {
            $this->db->where('id', (int) $row['id'])
                     ->update('tq_api_tokens', array('last_used_at' => time()));
        }

        return array('ok' => true, 'user' => $user, 'token' => $row);
    }

    /**
     * تجديد بالتدوير مع كشف إعادة الاستعمال.
     *
     * الرمز المستعمل يبطل في اللحظة، ويصدر زوج جديد في العائلة نفسها.
     * ومن قدم رمز تجديد **مبطلا** فإما نسخة مسروقة وإما نسخة قديمة عند
     * صاحبها — والاحتمالان يعالجان بقطع العائلة كلها: خسارة جلسة أهون من
     * جلسة مفتوحة لسارق. وهذه توصية OAuth 2.0 BCP نفسها.
     */
    public function rotate($refresh, $device = array())
    {
        $this->ensure_schema();

        $refresh = (string) $refresh;
        if ($refresh === '' || strpos($refresh, self::REFRESH_PREFIX) !== 0) {
            return array('ok' => false, 'code' => 'token_invalid');
        }

        $row = $this->db->where('token_hash', $this->fingerprint($refresh))
                        ->where('kind', 'refresh')
                        ->get('tq_api_tokens')->row_array();

        if (!$row) return array('ok' => false, 'code' => 'token_invalid');

        if ((int) $row['revoked_at'] > 0) {
            $this->revoke_family($row['family']);
            $this->audit('api.refresh.reuse_detected', (int) $row['user_id'],
                         array('family' => $row['family']));
            return array('ok' => false, 'code' => 'token_reused');
        }

        if ((int) $row['expires_at'] < time()) {
            return array('ok' => false, 'code' => 'token_expired');
        }

        $user = $this->db->where('id', (int) $row['user_id'])->get('users')->row_array();
        if (!$user || (int) $user['status'] !== 1) {
            return array('ok' => false, 'code' => 'account_disabled');
        }

        /* الزوج القديم يبطل كاملا — الوصول مع التجديد. وإبقاء رمز الوصول
           القديم صالحا يعني رمزين حيين لجهاز واحد بلا سبب. */
        $now = time();
        $this->db->where('family', $row['family'])->where('revoked_at', 0)
                 ->update('tq_api_tokens', array('revoked_at' => $now));

        $pair = $this->issue_pair((int) $row['user_id'], $device ?: array(
            'device_name' => $row['device_name'], 'device_id'   => $row['device_id'],
            'platform'    => $row['platform'],    'app_version' => $row['app_version'],
        ), $row['family']);

        return array('ok' => true, 'pair' => $pair, 'user' => $user);
    }

    /* ================================================================
       الإبطال
       ================================================================ */

    /** يبطل الزوج الذي ينتمي إليه هذا الرمز — خروج من هذا الجهاز وحده. */
    public function revoke_token($token_row)
    {
        if (empty($token_row['family'])) return;
        $this->revoke_family($token_row['family']);
    }

    public function revoke_family($family)
    {
        $this->ensure_schema();
        $this->db->where('family', (string) $family)->where('revoked_at', 0)
                 ->update('tq_api_tokens', array('revoked_at' => time()));
    }

    /**
     * خروج من كل الأجهزة. تنادى عند تغيير كلمة المرور وعند حذف الحساب.
     * و`$except_family` يبقي الجهاز الحالي داخلا حين يكون هو من طلب.
     */
    public function revoke_all($user_id, $except_family = null)
    {
        $this->ensure_schema();
        $this->db->where('user_id', (int) $user_id)->where('revoked_at', 0);
        if ($except_family) $this->db->where('family !=', (string) $except_family);
        $this->db->update('tq_api_tokens', array('revoked_at' => time()));
    }

    /** الأجهزة الداخلة — عائلة لكل جهاز، لا صفا لكل رمز. */
    public function sessions_of($user_id)
    {
        $this->ensure_schema();
        return $this->db->query(
            "SELECT `family`, MAX(`device_name`) AS device_name, MAX(`platform`) AS platform,
                    MAX(`app_version`) AS app_version, MAX(`ip`) AS ip,
                    MIN(`created_at`) AS created_at, MAX(`last_used_at`) AS last_used_at
               FROM `tq_api_tokens`
              WHERE `user_id` = ? AND `revoked_at` = 0 AND `expires_at` > ?
              GROUP BY `family` ORDER BY last_used_at DESC LIMIT 20",
            array((int) $user_id, time())
        )->result_array();
    }

    /* ================================================================
       حد الطلبات
       ================================================================ */

    /**
     * نافذة ثابتة بدلو واحد. ترد الحال دائما، ويقرر النادي.
     *
     * والعد `INSERT ... ON DUPLICATE KEY UPDATE` واحد: قراءة ثم كتابة
     * تجعل طلبين متزامنين يقرآن العدد نفسه فيمران معا، وهو بالضبط ما
     * يفعله من يجرب كلمات المرور بالتوازي.
     */
    public function throttle($bucket, $limit, $window)
    {
        $this->ensure_schema();

        $limit  = (int) $limit;
        $window = (int) $window;
        $now    = time();
        $start  = $now - ($now % $window);              // بداية النافذة الحالية
        $key    = substr($bucket . ':' . $start, 0, 160);

        $this->db->query(
            "INSERT INTO `tq_api_rate` (`bucket`,`hits`,`window_start`)
             VALUES (?, 1, ?)
             ON DUPLICATE KEY UPDATE `hits` = `hits` + 1",
            array($key, $start)
        );

        $hits = (int) $this->db->select('hits')->where('bucket', $key)
                               ->get('tq_api_rate')->row('hits');

        return array(
            'allowed'     => ($hits <= $limit),
            'limit'       => $limit,
            'remaining'   => max(0, $limit - $hits),
            'reset'       => $start + $window,
            'retry_after' => max(1, ($start + $window) - $now),
        );
    }

    /* ================================================================
       الكنس والسجل
       ================================================================ */

    /** كنس احتمالي: صف منته لا يضر، وجدول ينمو بلا حد يضر. */
    private function gc()
    {
        if (mt_rand(1, 100) !== 1) return;
        $this->db->where('expires_at <', time() - 86400)->delete('tq_api_tokens');
        $this->db->where('window_start <', time() - 3600)->delete('tq_api_rate');
    }

    /** السجل في `audit_log` القائم لا في جدول ثالث. */
    public function audit($action, $actor_id, $payload = array())
    {
        if (!$this->db->table_exists('audit_log')) return;
        $this->db->insert('audit_log', array(
            'actor_id' => (int) $actor_id ?: null,
            'action'   => substr((string) $action, 0, 80),
            'entity'   => 'users:' . (int) $actor_id,
            'after'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ip'       => $this->input->ip_address(),
            'at'       => date('Y-m-d H:i:s'),
        ));
    }
}
