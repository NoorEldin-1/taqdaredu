<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ═══════════════════════════════════════════════════════════════════════
 * TQ-SOCIAL — الدخول بحساب جوجل أو أبل
 * ═══════════════════════════════════════════════════════════════════════
 *
 * الباب الوحيد إلى المنصة كان بريدا وكلمة مرور. وهو باب يفقد من يطرقه:
 * من يريد أن يجرب درسا واحدا يقرأ نموذجا من أحد عشر حقلا فينصرف، ومن
 * أنشأ حسابه قبل شهرين لا يذكر كلمته فيطلب استعادة تصل إلى «غير مرغوب»
 * — وهو العطل الذي لأجله كتب `tq_spam_notice()` أصلا.
 *
 * **والقاعدة الحاكمة هي قاعدة TQ-COURSE-SALE وTQ-BOOK نفسها: لا محرك
 * ثان.** الدخول بجوجل ينتهي حيث ينتهي الدخول بكلمة المرور حرفا بحرف —
 * `new_device_login_tracker()` ثم `set_login_userdata()` — والتسجيل به
 * يكتب صف `users` نفسه بـ`tq_gate` و`status` و`role_id` نفسها. فالحارس
 * الذي يمنع الطالب من لوحة المعلم يمنعه هنا، والاشتراك الذي يفتح محتواه
 * يفتحه، والتصدير وحذف الحساب يعملان له بلا سطر. ونظام ثان كان يحتاج
 * نسخة ثانية من كل واحد منها.
 *
 * **والذي يتغير بعد واحد: من يشهد على البريد.** كلمة المرور تقول «أنا
 * أعرف سر هذا الحساب»، والمزود يقول «أنا أملك هذا البريد وقد وثقته».
 * فالشهادة أقوى لا أضعف — ولذلك يفتح الحساب المنشأ بها فورا ويختم
 * `tq_verified_at`، ولا يرسل إليه رمز تأكيد بريد أصلا.
 *
 *  المزود   ما يعطيه                            ما لا يعطيه
 *  ───────  ──────────────────────────────────  ──────────────────────
 *  جوجل     معرف ثابت · بريد موثق · اسم          جوال · عمر · صف
 *  أبل      معرف ثابت · بريد (قد يكون مرحلا) ·   جوال · عمر · صف ·
 *           واسم **مرة واحدة في العمر**          والاسم بعد أول تفويض
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ثلاث قواعد لا تخرق
 * ═══════════════════════════════════════════════════════════════════════
 *
 * ١ — **المفاتيح في `settings` لا في الشيفرة.** المستودع عام والنشر
 *     `git reset --hard`. وهي قاعدة `Taqdar_tap_model` نفسها.
 *
 * ٢ — **لا يصدق طلب وارد.** ما يعود إلى `/auth/<مزود>/callback` ليس
 *     هوية بل **رمز تفويض**: القرار على تبادله في نقطة المزود بالمفتاح
 *     السري، ثم على التحقق من توقيع `id_token` بمفاتيح المزود المعلنة
 *     (JWKS) ومن `iss` و`aud` و`exp` و`nonce`. فمن اخترع `?code=` أو
 *     لصق `id_token` من تطبيق آخر لم يفعل شيئا.
 *
 * ٣ — **الربط بالبريد يشترط أن يكون موثقا** (`email_verified`). وبلا
 *     هذا الشرط يصير كل حساب في المنصة مفتوحا لمن يسجل عند مزود بريد
 *     غيره ولا يوثقه: يضغط «ادخل بجوجل» فيدخل حساب سواه بلا كلمة مرور.
 *     وهو أخطر ما في هذا الملف كله، **ولا يظهر في التجربة**: جوجل توثق
 *     بريد كل حساب عادي، فالتجربة الأولى تمر دائما.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * ومن ينشأ حسابه هنا، وبأي صفة؟
 * ═══════════════════════════════════════════════════════════════════════
 *
 * **طالب أو ولي أمر، ولا معلم.** طلب المعلم ثلاث محطات — جوال ومستند
 * مؤهل وعينة شرح — ثم لجنة تعتمده (TQ-TEACHER-GATE)، ونقرة واحدة لا
 * تنتج شيئا من ذلك. فمعلم **له حساب** يدخل بجوجل كما يدخل بكلمته (بريده
 * موثق فيربط)، ومعلم **لا حساب له** يرد إلى نموذج الانضمام بنصه.
 *
 * **والإدارة تربط ولا تنشأ**: حساب `role_id = 1` ينشأ من داخل المنصة
 * وحده كما يقول نموذج التسجيل نفسه.
 *
 * **وما ينقص يطلب بعد الدخول لا قبله** (TQ-SOCIAL-COMPLETE): الجوال
 * والعمر لا يعطيهما مزود، وحجب الحساب حتى يكتبا يعيد النموذج الذي جاء
 * هذا الباب ليتجاوزه. فيفتح الحساب ويدخل صاحبه، وشاشة واحدة تطلب ما
 * ينقص — وهو مبدأ TQ-INSTANT نفسه.
 */
class Taqdar_social_model extends CI_Model
{
    /* نقاط جوجل وأبل — ثابتة معلنة، ولا تقرأ من إعداد: مسار يكتبه
       مسؤول يعني بابا يستوثق عنده الناس بحساباتهم عند من يشاء. */
    const GOOGLE_AUTH  = 'https://accounts.google.com/o/oauth2/v2/auth';
    const GOOGLE_TOKEN = 'https://oauth2.googleapis.com/token';
    const GOOGLE_JWKS  = 'https://www.googleapis.com/oauth2/v3/certs';

    const APPLE_AUTH   = 'https://appleid.apple.com/auth/authorize';
    const APPLE_TOKEN  = 'https://appleid.apple.com/auth/token';
    const APPLE_JWKS   = 'https://appleid.apple.com/auth/keys';
    const APPLE_ISS    = 'https://appleid.apple.com';

    /** مهلة الرحلة من الزر إلى العودة. ربع ساعة تكفي من يستوثق بخطوتين. */
    const STATE_TTL = 900;

    /** كاش مفاتيح المزود المعلنة — يوم. ودورتها عندهم أطول من ذلك. */
    const JWKS_TTL = 86400;

    /** عمر سر أبل المولد. أبل تقبل حتى ستة أشهر، والشهر أهون في التدوير. */
    const APPLE_SECRET_TTL = 2592000;

    private $schema_checked = false;

    /* =====================================================================
       المخطط — جدولان ينشآن وقت التشغيل

       والتجهيز ينادى **قبل كل قراءة** لا من مسار الكتابة وحده: صفحة
       الدخول تسأل «أي مزود يعمل؟» في كل عرض، وقراءة جدول قبل إنشائه ترد
       «Table doesn't exist» فتبيض صفحة الدخول لكل زائر. وهي علة
       `Taqdar_course_sale_model::install_schema()` نفسها.
       ===================================================================== */

    public function install_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        try {
            /* الربط: صف لكل (مزود، حساب عنده). و`uq_provider_uid` فريد في
               المخطط لا في شرط `if`: نقرتان متتاليتان على الزر نداءان
               متوازيان كلاهما يقرأ «لا ربط» فيكتبان صفين — والثاني يرتد
               على المفتاح بلا ضرر. وهو مبدأ `meta_lead_id` نفسه.

               و`uq_user_provider` يمنع ربط حسابين من جوجل بمستخدم واحد:
               «افصل الربط» بعدها لا تعرف أيهما تفصل. */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_social_accounts` (
                    `id`            int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `user_id`       int(10) unsigned NOT NULL,
                    `provider`      varchar(16)  NOT NULL,
                    `provider_uid`  varchar(191) NOT NULL,
                    `email`         varchar(190) DEFAULT NULL,
                    `is_relay`      tinyint(1)   NOT NULL DEFAULT 0,
                    `linked_at`     datetime     DEFAULT NULL,
                    `last_login_at` datetime     DEFAULT NULL,
                    `logins`        int(10) unsigned NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_provider_uid` (`provider`, `provider_uid`),
                    UNIQUE KEY `uq_user_provider` (`user_id`, `provider`),
                    KEY `k_user` (`user_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            /* الحال بين الذهاب والعودة — **في الجدول لا في الجلسة**.

               TQ-APPLE-POST — وهذا ليس تفضيلا. أبل تعيد الزائر بـ`POST`
               من نطاقها إلى نطاقنا (`response_mode=form_post`، وهو
               مشترط متى طلبنا البريد والاسم)، و**كعكة الجلسة
               `SameSite=Lax` لا ترسل مع POST قادم من موقع آخر**. فحال
               محفوظة في الجلسة تقرأ فارغة عند العودة دائما: كل دخول
               بأبل يرد «انتهت الجلسة» ولا شيء في أي سجل يقول لماذا.
               والصف هنا يقرأ بالمفتاح الذي يعود في الطلب نفسه، فلا
               يتوقف على كعكة.

               و`state` يخزن **مهشوما**: الجدول يقرؤه من يفتح القاعدة،
               وقيمة حية فيه تعني أن من قرأها يستطيع أن يتم رحلة غيره. */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_social_states` (
                    `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `state_hash` char(64)     NOT NULL,
                    `provider`   varchar(16)  NOT NULL,
                    `nonce`      varchar(64)  NOT NULL DEFAULT "",
                    `gate`       varchar(16)  NOT NULL DEFAULT "student",
                    `mode`       varchar(16)  NOT NULL DEFAULT "login",
                    `next_url`   varchar(255) NOT NULL DEFAULT "",
                    `link_user`  int(10) unsigned NOT NULL DEFAULT 0,
                    `created_at` datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_state` (`state_hash`),
                    KEY `k_created` (`created_at`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            /* TQ-BUILDER-DIRTY — الاستثناء يترك بناء الاستعلام نظيفا خلفه. */
            $this->db->reset_query();
            log_message('error', 'TQ-SOCIAL: تعذر تجهيز المخطط — ' . $e->getMessage());
        }
    }

    /* =====================================================================
       الإعداد — أي مزود يعمل، ولماذا لا يعمل الآخر
       ===================================================================== */

    /**
     * المفاتيح كما هي في `settings`.
     *
     * ولا افتراض في الشيفرة لواحد منها: «بلا مفاتيح لا شيء يتغير» —
     * فبلا ضبط لا يعرض زر، وتبقى صفحتا الدخول والتسجيل كما كانتا حرفا
     * بحرف. وهي قاعدة تاب وواتساب وبيع الكورسات نفسها.
     */
    public function config()
    {
        return array(
            'google_id'     => trim((string) get_settings('tq_google_client_id')),
            'google_secret' => trim((string) get_settings('tq_google_client_secret')),
            /* جمهور إضافي يقبل من التطبيق: جوجل تصدر `id_token` بجمهور
               معرف المنصة التي وقع عليها الدخول — أندرويد أو iOS — لا
               بمعرف الويب. فقبول معرف الويب وحده يرد **كل** دخول من
               التطبيق بـ«هذه الهوية صادرة لتطبيق آخر»، والمفتاح صحيح
               تماما ولا شيء يقول أين الخلل. */
            'google_ios'    => trim((string) get_settings('tq_google_ios_client_id')),
            'google_and'    => trim((string) get_settings('tq_google_android_client_id')),

            /* أبل: معرف الخدمة (Services ID) للويب، ومعرف التطبيق
               (Bundle ID) للتطبيق — وهما **مختلفان**، وجمهور `id_token`
               يتبع من طلبه. */
            'apple_id'      => trim((string) get_settings('tq_apple_client_id')),
            'apple_bundle'  => trim((string) get_settings('tq_apple_bundle_id')),
            'apple_team'    => trim((string) get_settings('tq_apple_team_id')),
            'apple_key_id'  => trim((string) get_settings('tq_apple_key_id')),
            'apple_key'     => trim((string) get_settings('tq_apple_private_key')),
        );
    }

    /**
     * حال كل مزود بسببه — للشاشة العامة وللوحة معا.
     *
     * و«لماذا لا يعمل» يقال بالحقل الناقص لا بـ«غير مضبوط»: من قرأ «غير
     * مضبوط» يفتح الشاشة ولا يعرف أي حقل ينقصه، وأربعة حقول لأبل تجعل
     * ذلك تخمينا.
     */
    public function providers()
    {
        $c = $this->config();

        $g_miss = array();
        if ($c['google_id'] === '')     $g_miss[] = 'معرف العميل';
        if ($c['google_secret'] === '') $g_miss[] = 'سر العميل';

        $a_miss = array();
        if ($c['apple_id'] === '')     $a_miss[] = 'معرف الخدمة';
        if ($c['apple_team'] === '')   $a_miss[] = 'معرف الفريق';
        if ($c['apple_key_id'] === '') $a_miss[] = 'معرف المفتاح';
        if ($c['apple_key'] === '')    $a_miss[] = 'المفتاح الخاص';

        return array(
            'google' => array(
                'key'   => 'google',
                'label' => 'جوجل',
                'on'    => empty($g_miss),
                'why'   => empty($g_miss) ? '' : ('ينقصه: ' . implode(' · ', $g_miss)),
            ),
            'apple' => array(
                'key'   => 'apple',
                'label' => 'أبل',
                'on'    => empty($a_miss),
                'why'   => empty($a_miss) ? '' : ('ينقصه: ' . implode(' · ', $a_miss)),
            ),
        );
    }

    /** هل هذا المزود مضبوط؟ */
    public function enabled($provider)
    {
        $p = $this->providers();
        return isset($p[$provider]) && $p[$provider]['on'];
    }

    /** المزودون العاملون وحدهم — وهو ما تطبعه الشاشة العامة. */
    public function live()
    {
        $out = array();
        foreach ($this->providers() as $k => $p) {
            if ($p['on']) $out[$k] = $p;
        }
        return $out;
    }

    /**
     * وجهة العودة المسجلة عند المزود.
     *
     * وهي **تكتب حرفا بحرف** في لوحة جوجل وأبل: فارق مقطع أو `http`
     * مكان `https` يرد `redirect_uri_mismatch` — وهو أكثر ما يوقف من
     * يضبط أول مرة، ولذلك تعرض في شاشة اللوحة جاهزة للنسخ.
     */
    public function redirect_uri($provider)
    {
        return site_url('auth/' . $provider . '/callback');
    }

    /* =====================================================================
       الذهاب — بناء رابط التفويض
       ===================================================================== */

    /**
     * رابط يذهب إليه الزائر، وصف حال يقابله عند العودة.
     *
     * `$ctx` يحمل ما يجب أن ينجو من الرحلة: البوابة التي اختارها
     * (طالب / ولي أمر)، والوجهة التي جاء من أجلها (TQ-AUTH-NEXT)، وهل
     * هذه رحلة **ربط** حساب قائم لا دخول.
     */
    public function start_url($provider, array $ctx = array())
    {
        $this->install_schema();
        if (!$this->enabled($provider)) return '';

        $state = bin2hex(random_bytes(24));
        $nonce = bin2hex(random_bytes(16));

        $gate = isset($ctx['gate']) ? (string) $ctx['gate'] : 'student';
        if (!in_array($gate, array('student', 'parent'), true)) $gate = 'student';

        try {
            $this->db->insert('tq_social_states', array(
                'state_hash' => hash('sha256', $state),
                'provider'   => $provider,
                'nonce'      => $nonce,
                'gate'       => $gate,
                'mode'       => (isset($ctx['mode']) && $ctx['mode'] === 'link') ? 'link' : 'login',
                'next_url'   => (string) (isset($ctx['next']) ? $ctx['next'] : ''),
                'link_user'  => (int) (isset($ctx['link_user']) ? $ctx['link_user'] : 0),
                'created_at' => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-SOCIAL: تعذر كتابة حال الرحلة — ' . $e->getMessage());
            return '';
        }

        $this->sweep_states();

        $c = $this->config();

        if ($provider === 'google') {
            return self::GOOGLE_AUTH . '?' . http_build_query(array(
                'client_id'     => $c['google_id'],
                'redirect_uri'  => $this->redirect_uri('google'),
                'response_type' => 'code',
                'scope'         => 'openid email profile',
                'state'         => $state,
                'nonce'         => $nonce,
                /* `select_account` لا سواه: جهاز فيه أكثر من حساب جوجل —
                   وهو حال أكثر البيوت — كان يدخل بأولها بلا سؤال، فمن
                   أراد حسابه الثاني لا يجد كيف يبدله. */
                'prompt'        => 'select_account',
            ), '', '&', PHP_QUERY_RFC3986);
        }

        /* أبل: `form_post` مشترط متى طلبنا `name` أو `email` في النطاق —
           انظر TQ-APPLE-POST أعلاه. و`response_mode=query` معهما يرد
           `invalid_request` بلا شرح. */
        return self::APPLE_AUTH . '?' . http_build_query(array(
            'client_id'     => $c['apple_id'],
            'redirect_uri'  => $this->redirect_uri('apple'),
            'response_type' => 'code id_token',
            'response_mode' => 'form_post',
            'scope'         => 'name email',
            'state'         => $state,
            'nonce'         => $nonce,
        ), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * الحال تستهلك مرة واحدة.
     *
     * والحذف **قبل** أي شيء آخر: صف يبقى يعني أن من أعاد إرسال نموذج
     * العودة (وهو نموذج POST عند أبل، و«إعادة الإرسال؟» زر في كل
     * متصفح) يمر مرة ثانية بالرمز نفسه.
     */
    public function consume_state($state, $provider)
    {
        $this->install_schema();
        $state = (string) $state;
        if ($state === '') return null;

        try {
            $row = $this->db->where('state_hash', hash('sha256', $state))
                            ->where('provider', $provider)
                            ->get('tq_social_states')->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
        if (!$row) return null;

        try { $this->db->where('id', (int) $row['id'])->delete('tq_social_states'); }
        catch (Throwable $e) { $this->db->reset_query(); }

        if (strtotime((string) $row['created_at']) < time() - self::STATE_TTL) return null;

        return $row;
    }

    /** ما مضى أجله يذهب — جدول حال لا سجل. */
    private function sweep_states()
    {
        try {
            $this->db->where('created_at <', date('Y-m-d H:i:s', time() - self::STATE_TTL))
                     ->delete('tq_social_states');
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    /* =====================================================================
       العودة — تبادل الرمز، والتحقق من الشهادة
       ===================================================================== */

    /**
     * رمز التفويض ← `id_token` مفكوكا موثقا.
     *
     * والرد `['ok'=>bool, 'claims'=>[], 'error'=>'…']` — والنص عربي يعرض
     * للزائر، فلا يقرأ `invalid_grant` من يظن أنه ضغط زرا.
     */
    public function exchange($provider, $code, $nonce)
    {
        $c = $this->config();

        if ($provider === 'google') {
            $body = array(
                'code'          => $code,
                'client_id'     => $c['google_id'],
                'client_secret' => $c['google_secret'],
                'redirect_uri'  => $this->redirect_uri('google'),
                'grant_type'    => 'authorization_code',
            );
            $url = self::GOOGLE_TOKEN;
        } else {
            $secret = $this->apple_client_secret();
            if ($secret === '') {
                return array('ok' => false, 'claims' => array(),
                             'error' => 'تعذر توليد سر أبل. راجع المفتاح الخاص في إعدادات الدخول.');
            }
            $body = array(
                'code'          => $code,
                'client_id'     => $c['apple_id'],
                'client_secret' => $secret,
                'redirect_uri'  => $this->redirect_uri('apple'),
                'grant_type'    => 'authorization_code',
            );
            $url = self::APPLE_TOKEN;
        }

        $r = $this->http_post($url, $body);
        if (!empty($r['data']['error'])) {
            log_message('error', 'TQ-SOCIAL: ' . $provider . ' token — '
                . json_encode($r['data'], JSON_UNESCAPED_UNICODE));
            return array('ok' => false, 'claims' => array(),
                         'error' => 'تعذر إتمام الدخول مع المزود. أعد المحاولة.');
        }
        if (empty($r['data']['id_token'])) {
            return array('ok' => false, 'claims' => array(),
                         'error' => $r['msg'] !== '' ? $r['msg'] : 'لم يرد المزود بهوية. أعد المحاولة.');
        }

        return $this->verify_id_token($provider, (string) $r['data']['id_token'], $nonce);
    }

    /**
     * التحقق من `id_token`: التوقيع ثم المصدر ثم الجمهور ثم الأجل ثم
     * `nonce`.
     *
     * **والخمسة لا أربعة.** إسقاط `aud` وحده يجعل `id_token` صادرا لأي
     * تطبيق آخر عند المزود نفسه مقبولا هنا — وهو رمز يحصل عليه أي مطور
     * بتسجيل تطبيق مجاني. وإسقاط `nonce` يجعل رمزا التقط من رحلة سابقة
     * يعاد.
     *
     * و`$nonce = null` تعني «لا تفحصه»: نقطة التطبيق (TQ-SOCIAL-API)
     * تستقبل رمزا ولدته مكتبة المزود على الجهاز، و`nonce` هناك شأن
     * العميل. والجمهور وحده هو الحارس في تلك الحال، ولذلك يقبل جمهور
     * التطبيق **صراحة** لا كل جمهور.
     */
    public function verify_id_token($provider, $jwt, $nonce = null, $audiences = null)
    {
        $parts = explode('.', (string) $jwt);
        if (count($parts) !== 3) {
            return array('ok' => false, 'claims' => array(), 'error' => 'هوية غير مقروءة من المزود.');
        }

        $head   = json_decode((string) $this->b64url_decode($parts[0]), true);
        $claims = json_decode((string) $this->b64url_decode($parts[1]), true);
        $sig    = $this->b64url_decode($parts[2]);

        if (!is_array($head) || !is_array($claims) || $sig === '') {
            return array('ok' => false, 'claims' => array(), 'error' => 'هوية غير مقروءة من المزود.');
        }

        /* الخوارزمية تفحص وترفض ما ليس RS256 صراحة: `alg: none` هو
           الثغرة الأولى في كل تنفيذ لـJWT كتب بلا هذا السطر، و`HS256`
           بمفتاح عام منشور هي الثانية. وجوجل وأبل كلاهما RS256. */
        if (strtoupper((string) (isset($head['alg']) ? $head['alg'] : '')) !== 'RS256') {
            return array('ok' => false, 'claims' => array(), 'error' => 'توقيع الهوية غير مقبول.');
        }

        $jwk = $this->jwk_for($provider, (string) (isset($head['kid']) ? $head['kid'] : ''));
        if (!$jwk) {
            return array('ok' => false, 'claims' => array(),
                         'error' => 'تعذر قراءة مفاتيح المزود. أعد المحاولة بعد قليل.');
        }

        $pem = $this->rsa_pem_from_jwk($jwk);
        if ($pem === '' || openssl_verify($parts[0] . '.' . $parts[1], $sig, $pem, OPENSSL_ALGO_SHA256) !== 1) {
            return array('ok' => false, 'claims' => array(), 'error' => 'توقيع الهوية غير صحيح.');
        }

        $iss = (string) (isset($claims['iss']) ? $claims['iss'] : '');
        $ok_iss = ($provider === 'google')
                ? in_array($iss, array('https://accounts.google.com', 'accounts.google.com'), true)
                : ($iss === self::APPLE_ISS);
        if (!$ok_iss) {
            return array('ok' => false, 'claims' => array(), 'error' => 'مصدر الهوية غير متوقع.');
        }

        if ($audiences === null) $audiences = $this->audiences($provider);
        $aud = isset($claims['aud']) ? $claims['aud'] : '';
        /* والجمهور قد يرد مصفوفة في المواصفة، وإن كان جوجل وأبل يردانه
           نصا: قراءة النص وحده تجعل تغييرا عندهما يرد كل دخول. */
        $aud_list = is_array($aud) ? $aud : array((string) $aud);
        if (count(array_intersect($aud_list, $audiences)) === 0) {
            return array('ok' => false, 'claims' => array(),
                         'error' => 'هذه الهوية صادرة لتطبيق آخر.');
        }

        /* هامش دقيقتين للفارق بين ساعة الخادم وساعة المزود: خادم متأخر
           ثلاثين ثانية يرد كل دخول بـ«انتهت صلاحية الهوية» ولا شيء يقول
           إن العلة في الساعة. */
        $now = time();
        if ((int) (isset($claims['exp']) ? $claims['exp'] : 0) < $now - 120) {
            return array('ok' => false, 'claims' => array(), 'error' => 'انتهت صلاحية الهوية. أعد المحاولة.');
        }
        if ((int) (isset($claims['iat']) ? $claims['iat'] : 0) > $now + 120) {
            return array('ok' => false, 'claims' => array(), 'error' => 'هوية بتاريخ غير صحيح.');
        }

        if ($nonce !== null && $nonce !== ''
            && !hash_equals((string) $nonce, (string) (isset($claims['nonce']) ? $claims['nonce'] : ''))) {
            return array('ok' => false, 'claims' => array(), 'error' => 'لا تطابق بين الطلب والرد. أعد المحاولة.');
        }

        if (trim((string) (isset($claims['sub']) ? $claims['sub'] : '')) === '') {
            return array('ok' => false, 'claims' => array(), 'error' => 'هوية بلا معرف ثابت.');
        }

        return array('ok' => true, 'claims' => $claims, 'error' => '');
    }

    /** الجماهير المقبولة لهذا المزود: الويب ومعه التطبيق متى ضبط. */
    public function audiences($provider)
    {
        $c = $this->config();
        $out = array();
        if ($provider === 'google') {
            foreach (array('google_id', 'google_ios', 'google_and') as $k) {
                if ($c[$k] !== '') $out[] = $c[$k];
            }
        } else {
            foreach (array('apple_id', 'apple_bundle') as $k) {
                if ($c[$k] !== '') $out[] = $c[$k];
            }
        }
        return array_values(array_unique($out));
    }

    /* =====================================================================
       مفاتيح المزود المعلنة (JWKS)
       ===================================================================== */

    /**
     * المفتاح الذي وقع به هذا الرمز.
     *
     * والكاش يوم، **ويعاد الجلب متى لم يوجد `kid`**: المزود يدور مفاتيحه
     * بلا إعلان، فكاش لا يبطل إلا بالزمن يجعل كل دخول يفشل ساعات بعد كل
     * تدوير — ولا شيء يقول لماذا.
     */
    private function jwk_for($provider, $kid)
    {
        $hit = $this->pick_jwk($this->jwks($provider, false), $kid);
        if ($hit) return $hit;

        return $this->pick_jwk($this->jwks($provider, true), $kid);
    }

    private function pick_jwk($set, $kid)
    {
        if (!is_array($set)) return null;
        foreach ($set as $k) {
            if (!is_array($k)) continue;
            if ((string) (isset($k['kty']) ? $k['kty'] : '') !== 'RSA') continue;
            if ($kid === '' || (string) (isset($k['kid']) ? $k['kid'] : '') === $kid) return $k;
        }
        return null;
    }

    private function jwks($provider, $force)
    {
        $key = 'tq_social_jwks_' . $provider;

        if (!$force) {
            $raw = (string) get_settings($key);
            if ($raw !== '') {
                $c = json_decode($raw, true);
                if (is_array($c) && (int) (isset($c['at']) ? $c['at'] : 0) > time() - self::JWKS_TTL
                    && !empty($c['keys'])) {
                    return $c['keys'];
                }
            }
        }

        $url = ($provider === 'google') ? self::GOOGLE_JWKS : self::APPLE_JWKS;
        $r   = $this->http_get($url);
        if (empty($r['data']['keys']) || !is_array($r['data']['keys'])) {
            /* الجلب فشل: يرد الكاش القديم ولو مضى أجله. مفتاح قديم صالح
               خير من صفحة دخول معطلة لأن شبكة الخادم تعثرت ثانية. */
            $c = json_decode((string) get_settings($key), true);
            return (is_array($c) && !empty($c['keys'])) ? $c['keys'] : array();
        }

        $this->put_setting($key, json_encode(array('at' => time(), 'keys' => $r['data']['keys'])));
        return $r['data']['keys'];
    }

    /* =====================================================================
       سر أبل — JWT يولد ويوقع، لا نص يلصقه مسؤول
       ===================================================================== */

    /**
     * TQ-APPLE-SECRET — «سر العميل» عند أبل ليس سرا يلصق.
     *
     * أبل لا تعطي سرا ثابتا كجوجل: تعطي **مفتاحا خاصا** (ملف `.p8`)،
     * ويبنى منه في كل مرة JWT موقع بـES256 هو ما يرسل مكان السر. وأجله
     * ستة أشهر على الأكثر، فسر يكتب بيد يتوقف بعد نصف عام: يعمل اليوم
     * ويعمل غدا ثم يرد `invalid_client` في شهر لا يذكر فيه أحد أنه كتب
     * شيئا. فيولد هنا ويكاش شهرا.
     *
     * والتوقيع يحتاج تحويلا لا يفعله `openssl_sign`: هو يخرج توقيع
     * المنحنيات بصيغة DER، وJWS يشترط `R‖S` خامين بطول ثابت. ورمز يرسل
     * بصيغة DER ترده أبل بـ`invalid_client` كذلك — وهو الرمز الذي يعطى
     * لكل خطأ في هذا الباب، فلا يفرق بين مفتاح خاطئ وتوقيع بصيغة خاطئة.
     */
    public function apple_client_secret()
    {
        $c = $this->config();
        if ($c['apple_key'] === '' || $c['apple_team'] === ''
            || $c['apple_key_id'] === '' || $c['apple_id'] === '') return '';

        $cached = json_decode((string) get_settings('tq_apple_secret_cache'), true);
        if (is_array($cached) && !empty($cached['jwt'])
            && (int) (isset($cached['exp']) ? $cached['exp'] : 0) > time() + 3600
            && (string) (isset($cached['fp']) ? $cached['fp'] : '') === $this->apple_fingerprint($c)) {
            return (string) $cached['jwt'];
        }

        $now = time();
        $exp = $now + self::APPLE_SECRET_TTL;

        $head = array('alg' => 'ES256', 'kid' => $c['apple_key_id'], 'typ' => 'JWT');
        $body = array(
            'iss' => $c['apple_team'],
            'iat' => $now,
            'exp' => $exp,
            'aud' => self::APPLE_ISS,
            'sub' => $c['apple_id'],
        );

        $data = $this->b64url_encode(json_encode($head, JSON_UNESCAPED_SLASHES)) . '.'
              . $this->b64url_encode(json_encode($body, JSON_UNESCAPED_SLASHES));

        $key = @openssl_pkey_get_private($this->apple_key_pem($c['apple_key']));
        if ($key === false) {
            log_message('error', 'TQ-SOCIAL: مفتاح أبل الخاص غير مقروء.');
            return '';
        }

        $der = '';
        if (!@openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256)) {
            log_message('error', 'TQ-SOCIAL: تعذر توقيع سر أبل.');
            return '';
        }

        $raw = $this->der_to_raw_sig($der, 32);
        if ($raw === '') {
            log_message('error', 'TQ-SOCIAL: توقيع أبل بصيغة غير متوقعة.');
            return '';
        }

        $jwt = $data . '.' . $this->b64url_encode($raw);

        $this->put_setting('tq_apple_secret_cache', json_encode(array(
            'jwt' => $jwt, 'exp' => $exp, 'fp' => $this->apple_fingerprint($c),
        )));

        return $jwt;
    }

    /** بصمة الإعداد: تبديل مفتاح أو فريق يبطل الكاش في اللحظة. */
    private function apple_fingerprint(array $c)
    {
        return substr(hash('sha256', $c['apple_team'] . '|' . $c['apple_key_id']
                                   . '|' . $c['apple_id'] . '|' . $c['apple_key']), 0, 32);
    }

    /**
     * المفتاح كما لصقه المسؤول ← PEM سليم.
     *
     * وهو يلصق من ملف `.p8` فيفقد أسطره غالبا (نسخ من محرر يطويها، أو
     * حقل نصي يبدل `\r\n`). و`openssl_pkey_get_private` ترد `false` على
     * PEM بلا أسطر **بلا رسالة** — فيعاد بناؤه هنا.
     */
    private function apple_key_pem($raw)
    {
        $raw = trim(str_replace(array("\r\n", "\r"), "\n", (string) $raw));
        if (strpos($raw, '-----BEGIN') === 0 && strpos($raw, "\n") !== false) return $raw;

        $body = preg_replace('/-----(BEGIN|END)[^-]+-----/', '', $raw);
        $body = preg_replace('/\s+/', '', (string) $body);
        if ($body === '') return '';

        return "-----BEGIN PRIVATE KEY-----\n"
             . chunk_split($body, 64, "\n")
             . "-----END PRIVATE KEY-----\n";
    }

    /** توقيع ECDSA من DER إلى `R‖S` بطول ثابت — انظر TQ-APPLE-SECRET. */
    private function der_to_raw_sig($der, $len)
    {
        $p = 0;
        if (!isset($der[$p]) || $der[$p] !== "\x30") return '';
        $p++;
        $l = isset($der[$p]) ? ord($der[$p]) : 0;
        $p++;
        if ($l > 0x80) $p += ($l - 0x80);

        $read = function () use ($der, &$p, $len) {
            if (!isset($der[$p]) || $der[$p] !== "\x02") return null;
            $p++;
            $n = isset($der[$p]) ? ord($der[$p]) : 0;
            $p++;
            $v = substr($der, $p, $n);
            $p += $n;
            $v = ltrim($v, "\x00");
            if (strlen($v) > $len) return null;
            return str_pad($v, $len, "\x00", STR_PAD_LEFT);
        };

        $r = $read();
        $s = $read();
        if ($r === null || $s === null) return '';
        return $r . $s;
    }

    /* =====================================================================
       من هذا؟ — الهوية المسواة، ثم الحساب
       ===================================================================== */

    /**
     * ما يعنينا من دعاوى المزود، مسوى بشكل واحد.
     *
     * TQ-APPLE-NAME — و`$extra` هو اسم أبل. أبل ترسل الاسم **مرة واحدة
     * في العمر**: في حقل `user` من نموذج العودة عند أول تفويض، لا في
     * `id_token` ولا في أي نقطة تسأل بعدها. فمن لم يلتقطه في تلك اللحظة
     * لن يعرف اسم صاحبه أبدا — ويقرأ في اللوحة اسما مشتقا من بريد مرحل
     * على حساب حقيقي. **ولا يظهر في التجربة الثانية**: التفويض الثاني
     * يمر بلا اسم وهو الصواب عند أبل.
     */
    public function identity_from($provider, array $claims, array $extra = array())
    {
        $email = strtolower(trim((string) (isset($claims['email']) ? $claims['email'] : '')));
        $vmail = isset($claims['email_verified']) ? $claims['email_verified'] : false;
        /* أبل ترسلها نصا `"true"` وجوجل قيمة منطقية. وقراءة واحدة منهما
           تجعل كل دخول بأبل يقرأ «بريد غير موثق» فلا يربط بحساب قائم:
           يصير لصاحبه حسابان، واشتراكه في الأول. */
        $verified = ($vmail === true || $vmail === 1 || $vmail === '1'
                  || (is_string($vmail) && strtolower($vmail) === 'true'));

        $first = trim((string) (isset($claims['given_name']) ? $claims['given_name'] : ''));
        $last  = trim((string) (isset($claims['family_name']) ? $claims['family_name'] : ''));

        if ($provider === 'apple') {
            if (isset($extra['name']) && is_array($extra['name'])) {
                if ($first === '') $first = trim((string) (isset($extra['name']['firstName']) ? $extra['name']['firstName'] : ''));
                if ($last === '')  $last  = trim((string) (isset($extra['name']['lastName'])  ? $extra['name']['lastName']  : ''));
            }
            if ($email === '') $email = strtolower(trim((string) (isset($extra['email']) ? $extra['email'] : '')));
        }

        $full = trim((string) (isset($claims['name']) ? $claims['name'] : ''));
        if ($first === '' && $last === '' && $full !== '') {
            $bits  = preg_split('/\s+/', $full);
            $first = (string) array_shift($bits);
            $last  = trim(implode(' ', $bits));
        }

        $relay_suffix = '@privaterelay.appleid.com';

        return array(
            'provider' => $provider,
            'uid'      => (string) $claims['sub'],
            'email'    => $email,
            'verified' => $verified,
            'first'    => $first,
            'last'     => $last,
            /* بريد أبل المرحل: صندوق يحول إلى بريده الحقيقي ولا يكشفه.
               يعمل للإرسال، ولا يعرف صاحبه — فلا يطابق به حساب قديم
               بريده الحقيقي، ويقال لصاحبه ذلك في شاشة إكمال البيانات. */
            'relay'    => ($provider === 'apple' && $email !== ''
                        && substr($email, -strlen($relay_suffix)) === $relay_suffix),
        );
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * القرار — من يدخل، ومن ينشأ له حساب، ومن يرد ولماذا
     * ═══════════════════════════════════════════════════════════════════
     *
     * وترتيب البحث مقصود:
     *
     *   ١ — **الربط القائم** (مزود + معرفه الثابت). وهو وحده الجواب
     *       اليقيني: من دخل بهذا الحساب من قبل هو هو، ولو بدل بريده عند
     *       المزود أو عندنا.
     *   ٢ — **البريد الموثق**. حساب قائم بالبريد نفسه يربط ويدخل: ومن
     *       سجل بكلمة مرور في رمضان ثم ضغط «ادخل بجوجل» اليوم يجد
     *       اشتراكه ودروسه، لا حسابا ثانيا فارغا واشتراكه في الأول.
     *   ٣ — **حساب جديد**.
     *
     * و«البريد **الموثق**» شرط لا زينة — انظر القاعدة الثالثة في رأس
     * الملف.
     */
    public function resolve(array $id, array $ctx = array())
    {
        $this->install_schema();

        $link = $this->link_row($id['provider'], $id['uid']);
        if ($link) {
            $user = $this->db->where('id', (int) $link['user_id'])->get('users')->row_array();
            if (!$user) {
                /* الحساب حذف والربط باق: صف يتيم يشير إلى لا شيء. يطرح
                   ويمضي كأنه أول مرة، ولا يرد الزائر بخطأ لا يفهمه. */
                try { $this->db->where('id', (int) $link['id'])->delete('tq_social_accounts'); }
                catch (Throwable $e) { $this->db->reset_query(); }
            } else {
                $this->touch_link((int) $link['id'], $id);
                return $this->gate_check($user, false, $id);
            }
        }

        /* ── الحارس: بريد غير موثق لا يعبر من هنا بحال ──────────────
           وموضعه **بعد** الربط القائم وقبل كل ما سواه. الربط بالمعرف
           الثابت جواب يقيني لا يتوقف على بريد، أما ما تحته فيتوقف على
           البريد كله — والمزود قد يعطي بريدا لم يثبت أنه لصاحبه.

           TQ-SOCIAL-HIJACK — وكان القيد مكتوبا على فرع الربط وحده،
           فيمر غير الموثق إلى `create()` فيرتد إدراجه على `users.email`
           الفريد، **فيلتقطه فرع التزاحم ويسلمه الحساب القائم**. أي أن
           الشرط كان يقرأ في الشيفرة ولا يعمل: من سجل عند مزود ببريد
           غيره ولم يوثقه دخل حسابه بلا كلمة مرور. ولا يظهر في التجربة
           أبدا — جوجل توثق بريد كل حساب عادي، فكل تجربة تمر. */
        /* وحالان لا حال: «لم يشارك بريدا» غير «شارك بريدا لم يوثقه»،
           ويعالجان بغير ما يعالج به الآخر — الأول يسمح بالبريد في شاشة
           المزود، والثاني يوثقه عنده. ورسالة واحدة لهما تترك صاحبها
           يجرب الشيء نفسه مرتين. */
        if ($id['email'] === '') {
            return array('ok' => false, 'code' => 'no_email',
                'user_id' => 0, 'created' => false,
                'error' => 'لم يشارك المزود بريدك، ولا يفتح حساب بلا بريد. '
                         . 'أعد المحاولة واسمح بمشاركة البريد، أو أنشئ حسابا بالبريد وكلمة المرور.');
        }
        if (!$id['verified']) {
            return array('ok' => false, 'code' => 'email_unverified',
                'user_id' => 0, 'created' => false,
                'error' => 'لم يؤكد المزود ملكيتك لهذا البريد، فلا يفتح به حساب. '
                         . 'وثق بريدك عند المزود ثم أعد المحاولة، أو ادخل بالبريد وكلمة المرور.');
        }

        $user = $this->db->where('email', $id['email'])->get('users')->row_array();
        if ($user) {
            $this->link((int) $user['id'], $id);
            return $this->gate_check($user, false, $id);
        }

        return $this->create($id, $ctx);
    }

    /**
     * الحساب وجد — أيفتح؟
     *
     * والأجوبة هي أجوبة `Login::validate_login()` نفسها بنصها: من ينتظر
     * اعتمادا يقال له ذلك، ومن أوقف حسابه يقال له ذلك. ورسالة واحدة
     * لهما تترك المعلم ينتظر رمزا لا يأتي.
     */
    private function gate_check(array $user, $created, array $id)
    {
        if ((int) $user['status'] !== 1) {
            if (!empty($user['is_instructor']) || (string) $user['tq_gate'] === 'teacher') {
                return array('ok' => false, 'code' => 'teacher_pending', 'user_id' => 0,
                    'created' => false,
                    'error' => 'طلب انضمامك معلما ما زال قيد المراجعة. نتواصل معك عند الاعتماد، ويفتح الدخول بعده.');
            }
            /* TQ-SOCIAL-RESCUE — حساب لم يؤكد بريده قط، وجاء صاحبه من
               باب يشهد المزود فيه على البريد نفسه. فالشهادة التي كان
               ينتظرها وصلت، ولا معنى لأن يرد إلى رمز يرسل إلى البريد
               الذي أثبت المزود ملكيته له — بابان مغلقان على من يملك
               حسابه فعلا. فيفتح ويختم. */
            if ($id['verified'] && $id['email'] !== ''
                && strtolower((string) $user['email']) === $id['email']) {
                try {
                    $this->db->where('id', (int) $user['id'])->update('users', array(
                        'status'         => 1,
                        'tq_verified_at' => date('Y-m-d H:i:s'),
                    ));
                } catch (Throwable $e) { $this->db->reset_query(); }
                $user['status'] = 1;
            } else {
                return array('ok' => false, 'code' => 'blocked', 'user_id' => 0, 'created' => false,
                    'error' => 'هذا الحساب موقوف. تواصل مع الإدارة.');
            }
        }

        return array('ok' => true, 'user_id' => (int) $user['id'],
                     'created' => (bool) $created, 'code' => '', 'error' => '');
    }

    /**
     * حساب جديد — طالب أو ولي أمر، بلا كلمة مرور يعرفها أحد.
     *
     * و**بلا بريد لا حساب**: أبل تتيح إخفاء البريد بالمرحل وهو مقبول،
     * ولكنها قد ترد بلا بريد أصلا في حالات (تفويض ثان بعد أن حذف الربط
     * عندنا وبقي عندها). وحساب بلا بريد لا يستعيد كلمة مرور ولا تصله
     * فاتورة ولا يميز عن غيره — و`users.email` فريد فيرتد الإدراج على
     * الفراغ عند ثاني حساب مثله.
     */
    private function create(array $id, array $ctx)
    {
        $gate = isset($ctx['gate']) ? (string) $ctx['gate'] : 'student';
        if (!in_array($gate, array('student', 'parent'), true)) $gate = 'student';

        if ($id['email'] === '') {
            return array('ok' => false, 'code' => 'no_email', 'user_id' => 0, 'created' => false,
                'error' => 'لم يشارك المزود بريدك، ولا ينشأ حساب بلا بريد. '
                         . 'أعد المحاولة واسمح بمشاركة البريد، أو أنشئ حسابا بالبريد وكلمة المرور.');
        }

        if (mb_strlen($id['email']) > 50) {
            /* خمسون: طول `users.email`. وما زاد كان يقص عند الحفظ فينشأ
               حساب ببريد لا يصل إليه شيء — وهي علة `register()` نفسها. */
            return array('ok' => false, 'code' => 'email_long', 'user_id' => 0, 'created' => false,
                'error' => 'بريد هذا الحساب أطول مما يقبله النظام. أنشئ حسابا ببريد آخر.');
        }

        $first = $id['first'] !== '' ? $id['first'] : $this->name_from_email($id['email']);
        $last  = $id['last'];

        $now = date('Y-m-d H:i:s');
        $row = array(
            'first_name'        => html_escape(mb_substr($first, 0, 40)),
            'last_name'         => html_escape(mb_substr($last, 0, 40)),
            'email'             => $id['email'],
            /* كلمة مرور عشوائية لا يعرفها أحد — لا فراغا.
               `tq_password_authenticate()` تقابل الهاش، وعمود فارغ فيها
               باب قد يفتح بكلمة فارغة عند أول تعديل يغفل عنه. ومن أراد
               كلمة يضعها من «نسيت كلمة المرور» ببريده الموثق. */
            'password'          => tq_password_hash(bin2hex(random_bytes(32))),
            'status'            => 1,
            'role_id'           => 2,
            'is_instructor'     => 0,
            'tq_gate'           => $gate,
            /* المزود شهد على البريد، فالختم يقع الآن ولا يرسل رمز. */
            'tq_verified_at'    => $now,
            'terms_accepted_at' => $now,
            'date_added'        => strtotime($now),
            'wishlist'          => json_encode(array()),
            'payment_keys'      => json_encode(array()),
            'social_links'      => json_encode(array('facebook' => '', 'twitter' => '', 'linkedin' => '')),
            'skills'            => json_encode(array()),
            'sessions'          => json_encode(array()),
        );

        try {
            $this->db->insert('users', $row);
            $uid = (int) $this->db->insert_id();
        } catch (Throwable $e) {
            $this->db->reset_query();
            /* `email` فريد: نداءان متوازيان (نقرة مزدوجة على الزر) كلاهما
               يقرأ «لا حساب». والثاني يرتد هنا فيقرأ الصف الذي كتبه
               الأول — ولا يرد بخطأ على مستخدم حسابه أنشئ قبل جزء من
               الثانية. */
            /* والالتقاط هنا مقصور على الهوية الموثقة كذلك — حزام ثان
               تحت حارس `resolve()`: هذا الفرع هو الذي سلم الحساب القائم
               لهوية غير موثقة (TQ-SOCIAL-HIJACK)، وقيد واحد في دالة
               واحدة يسقط متى نودي `create()` من موضع ثالث غدا. */
            $exist = ($id['verified'] && $id['email'] !== '')
                   ? $this->db->where('email', $id['email'])->get('users')->row_array()
                   : null;
            if (!$exist) {
                log_message('error', 'TQ-SOCIAL: تعذر إنشاء حساب — ' . $e->getMessage());
                return array('ok' => false, 'code' => 'create_failed', 'user_id' => 0,
                             'created' => false, 'error' => 'تعذر إنشاء الحساب. أعد المحاولة.');
            }
            $this->link((int) $exist['id'], $id);
            return $this->gate_check($exist, false, $id);
        }

        if ($uid <= 0) {
            return array('ok' => false, 'code' => 'create_failed', 'user_id' => 0,
                         'created' => false, 'error' => 'تعذر إنشاء الحساب. أعد المحاولة.');
        }

        $this->link($uid, $id);

        $this->audit('social.signup', $uid, array(
            'provider' => $id['provider'], 'gate' => $gate, 'relay' => $id['relay'] ? 1 : 0,
        ));

        return array('ok' => true, 'user_id' => $uid, 'created' => true, 'code' => '', 'error' => '');
    }

    /** اسم من البريد حين لا يعطي المزود اسما — خير من «مستخدم». */
    private function name_from_email($email)
    {
        $local = (string) strstr($email, '@', true);
        $local = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $local);
        $local = trim((string) preg_replace('/\s+/u', ' ', (string) $local));
        return $local !== '' ? mb_substr($local, 0, 40) : 'مستخدم';
    }

    /* =====================================================================
       الربط — قراءة وكتابة وفصل
       ===================================================================== */

    public function link_row($provider, $uid)
    {
        $this->install_schema();
        try {
            return $this->db->where('provider', $provider)
                            ->where('provider_uid', $uid)
                            ->get('tq_social_accounts')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return null; }
    }

    /** يربط، ويحدث البريد المحفوظ إن تغير عند المزود. */
    public function link($user_id, array $id)
    {
        $this->install_schema();
        $now = date('Y-m-d H:i:s');
        try {
            $exist = $this->db->where('provider', $id['provider'])
                              ->where('provider_uid', $id['uid'])
                              ->get('tq_social_accounts')->row_array();
            if ($exist) {
                $this->db->where('id', (int) $exist['id'])->update('tq_social_accounts', array(
                    'user_id'       => (int) $user_id,
                    'email'         => $id['email'],
                    'is_relay'      => $id['relay'] ? 1 : 0,
                    'last_login_at' => $now,
                    'logins'        => (int) $exist['logins'] + 1,
                ));
                return true;
            }
            $this->db->insert('tq_social_accounts', array(
                'user_id'       => (int) $user_id,
                'provider'      => $id['provider'],
                'provider_uid'  => $id['uid'],
                'email'         => $id['email'],
                'is_relay'      => $id['relay'] ? 1 : 0,
                'linked_at'     => $now,
                'last_login_at' => $now,
                'logins'        => 1,
            ));
            return true;
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-SOCIAL: تعذر الربط — ' . $e->getMessage());
            return false;
        }
    }

    private function touch_link($id, array $ident)
    {
        try {
            $this->db->set('logins', 'logins + 1', false)
                     ->where('id', (int) $id)
                     ->update('tq_social_accounts', array(
                        'last_login_at' => date('Y-m-d H:i:s'),
                        'email'         => $ident['email'],
                     ));
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    /** روابط هذا المستخدم — تقرؤها شاشة إعداداته. */
    public function links_of($user_id)
    {
        $this->install_schema();
        try {
            return $this->db->where('user_id', (int) $user_id)
                            ->get('tq_social_accounts')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    /**
     * TQ-SOCIAL-LASTDOOR — والفصل يشترط بابا ثانيا.
     *
     * من أنشئ حسابه بجوجل لا يعرف كلمة مروره (عشوائية بحكم البناء)،
     * فبفصل الربط يقفل على نفسه حسابه واشتراكه ولا يبقى له إلا «نسيت
     * كلمة المرور» — إن تذكر أن يفعل، وإن وصلت الرسالة. فالفصل يرد حتى
     * يضع كلمة مرور أو يربط مزودا ثانيا.
     */
    public function unlink($user_id, $provider)
    {
        $this->install_schema();
        $links = $this->links_of($user_id);
        if (count($links) <= 1 && !$this->has_own_password($user_id)) {
            return array('ok' => false,
                'error' => 'هذا هو بابك الوحيد إلى حسابك. ضع كلمة مرور من «نسيت كلمة المرور» أولا، ثم افصل الربط.');
        }
        try {
            $this->db->where('user_id', (int) $user_id)->where('provider', $provider)
                     ->delete('tq_social_accounts');
        } catch (Throwable $e) { $this->db->reset_query(); }

        $this->audit('social.unlink', (int) $user_id, array('provider' => $provider));
        return array('ok' => true, 'error' => '');
    }

    /**
     * أللمستخدم كلمة مرور يعرفها؟
     *
     * ولا عمود في `users` يقول ذلك، ولا يميز هاش عشوائي عن هاش حقيقي
     * بالنظر إليه. **والعلامة أثر لا عمود**: `tq_social_pw_<معرف>` يكتب
     * متى وضع صاحب الحساب كلمة بيده — من «نسيت كلمة المرور» أو من شاشة
     * إعداداته — ولا يكتب عند الإنشاء الاجتماعي.
     *
     * وحساب قديم أنشئ بكلمة مرور ليس له هذا الصف، وهو صحيح كذلك: لا
     * يعنيه الفحص أصلا — `unlink()` تناديه على من له ربط، ومن له ربط
     * وحساب قديم بكلمة مرور له رابطان أو له كلمة، وفي الحالين يمر.
     * والأسوأ الذي يقع هو رسالة تدعوه إلى وضع كلمة يملكها — وهو أهون
     * ألف مرة من فصل يقفل حسابا.
     */
    public function has_own_password($user_id)
    {
        try {
            $n = $this->db->where('key', 'tq_social_pw_' . (int) $user_id)
                          ->count_all_results('settings');
        } catch (Throwable $e) { $this->db->reset_query(); return false; }
        return $n > 0;
    }

    /** يعلم أن صاحب هذا الحساب صار يعرف كلمة مروره. */
    public function mark_own_password($user_id)
    {
        $this->put_setting('tq_social_pw_' . (int) $user_id, (string) time());
    }

    /* =====================================================================
       ما ينقص بعد الدخول — TQ-SOCIAL-COMPLETE
       ===================================================================== */

    /**
     * الحقول التي لا يعطيها مزود ويحتاجها المنتج.
     *
     * والجوال ليس تزيدا: عليه يصل رمز التأكيد بواتساب، وعليه تصل
     * تنبيهات الأبناء لولي الأمر — وحساب بلا جوال يقرأ صاحبه في لوحته
     * لافتة تطلبه إلى الأبد. والعمر والصف يفتحان `‎/catalog‎` على مرحلة
     * صاحبه (`with_scope()`)؛ وبلاهما يقرأ الطالب كتالوج المنصة كله.
     */
    public function missing_of($user_id)
    {
        try {
            $u = $this->db->select('phone, age, grade_id, tq_gate')
                          ->where('id', (int) $user_id)->get('users')->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
        if (!$u) return array();

        $need = array();
        if (trim((string) $u['phone']) === '') $need[] = 'phone';
        if ((string) $u['tq_gate'] !== 'parent' && (int) $u['age'] <= 0) $need[] = 'age';

        return $need;
    }

    /** هل بقي شيء؟ — تقرؤها لافتة اللوحة والشاشة معا. */
    public function needs_profile($user_id)
    {
        return count($this->missing_of($user_id)) > 0;
    }

    /* =====================================================================
       أدوات — نداء، ترميز، إعداد، أثر
       ===================================================================== */

    private function http_get($url)  { return $this->http($url, null); }

    private function http_post($url, array $body) { return $this->http($url, $body); }

    private function http($url, $body)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER,
                array('Content-Type: application/x-www-form-urlencoded'));
        }
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return array('data' => array(), 'code' => 0,
                         'msg' => ($cerr !== '' ? $cerr : 'تعذر الاتصال بالمزود.'));
        }
        $d = json_decode((string) $raw, true);
        if (!is_array($d)) $d = array();

        $msg = '';
        if (isset($d['error_description'])) $msg = (string) $d['error_description'];
        elseif (isset($d['error']) && is_string($d['error'])) $msg = (string) $d['error'];

        return array('data' => $d, 'code' => $code, 'msg' => $msg);
    }

    public function b64url_decode($s)
    {
        $s = strtr((string) $s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) $s .= str_repeat('=', 4 - $pad);
        $out = base64_decode($s, true);
        return $out === false ? '' : $out;
    }

    public function b64url_encode($s)
    {
        return rtrim(strtr(base64_encode((string) $s), '+/', '-_'), '=');
    }

    /**
     * JWK (n · e) ← PEM.
     *
     * ولا دالة في PHP تفعل ذلك، ولا مكتبة في هذا المستودع: `JWT.php`
     * الموروثة تتحقق بمفتاح جاهز ولا تبني واحدا من JWK، وهي طرف ثالث لا
     * تعدل. فيبنى DER هنا — وهو تركيب معلن ثابت لا يتغير.
     */
    private function rsa_pem_from_jwk(array $jwk)
    {
        $n = $this->b64url_decode((string) (isset($jwk['n']) ? $jwk['n'] : ''));
        $e = $this->b64url_decode((string) (isset($jwk['e']) ? $jwk['e'] : ''));
        if ($n === '' || $e === '') return '';

        $seq  = $this->der_tag(0x30, $this->der_uint($n) . $this->der_uint($e));
        $bit  = $this->der_tag(0x03, "\x00" . $seq);
        $alg  = $this->der_tag(0x30,
                  $this->der_tag(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01")
                . $this->der_tag(0x05, ''));
        $spki = $this->der_tag(0x30, $alg . $bit);

        return "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($spki), 64, "\n")
             . "-----END PUBLIC KEY-----\n";
    }

    /** عدد صحيح موجب في DER — وبايت الصفر يسبقه إن كان أعلى بتاته مضاء. */
    private function der_uint($bytes)
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') $bytes = "\x00";
        if (ord($bytes[0]) > 0x7f) $bytes = "\x00" . $bytes;
        return $this->der_tag(0x02, $bytes);
    }

    private function der_tag($tag, $body)
    {
        $len = strlen($body);
        if ($len < 0x80) {
            $l = chr($len);
        } else {
            $l = ltrim(pack('N', $len), "\x00");
            $l = chr(0x80 | strlen($l)) . $l;
        }
        return chr($tag) . $l . $body;
    }

    /**
     * كتابة مفتاح في `settings` — والصف يكتب ولو كانت القيمة فارغة.
     *
     * وجود الصف هو ما يميز «أطفأه مسؤول» عن «لم يضبط أحد شيئا» — وهي
     * قاعدة `tracking_put()` نفسها.
     */
    public function put_setting($key, $value)
    {
        try {
            $has = $this->db->where('key', $key)->count_all_results('settings') > 0;
            if ($has) $this->db->where('key', $key)->update('settings', array('value' => (string) $value));
            else      $this->db->insert('settings', array('key' => $key, 'value' => (string) $value));
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    /** أثر في `audit_log` — ولا يكتب فيه سر ولا رمز. */
    private function audit($action, $user_id, array $meta = array())
    {
        try {
            if (!$this->db->table_exists('audit_log')) return;
            $this->db->insert('audit_log', array(
                'actor_id' => (int) $user_id,
                'action'   => $action,
                'entity'   => 'users#' . (int) $user_id,
                'before'   => null,
                'after'    => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'ip'       => $this->input->is_cli_request() ? 'cli' : $this->input->ip_address(),
                'at'       => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) { $this->db->reset_query(); }
    }
}
