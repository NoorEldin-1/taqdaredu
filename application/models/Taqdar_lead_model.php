<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-META-LEADS — العميل المحتمل يصل من الإعلان إلى اللوحة بلا يد تنقله.
 *
 * حملات فيسبوك وإنستغرام تجمع الأسماء في نموذج داخل التطبيق نفسه
 * (Lead Ads): يضغط من رآه فيفتح نموذجا ممتلئا ببيانات حسابه، فيضغط
 * «إرسال» ولا يخرج من التطبيق. وهذا هو سر نجاحه — ولا يصل من كتب اسمه
 * إلينا في سطر واحد: البيانات تجلس في «مركز الأعمال» عند ميتا، ومن
 * يديرها يدخل كل صباح فينسخها بيده أو ينزلها ملفا.
 *
 * وثلاثة أعطال في ذلك، وكلها في **المال لا في الراحة**:
 *
 *   ١ — **السرعة هي البيعة.** من ملأ نموذجا في إعلان يملأ نموذج غيرنا
 *       بعده بدقائق، ومن يتصل أولا يبيع. ونسخ يدوي مرة في اليوم يعني
 *       أن كل عميل ينتظر ساعات قبل أن يعلم به أحد.
 *   ٢ — **والذي ينسخ ينسى ويخطئ.** سطر يزاغ عنه في ملف من مئة، ورقم
 *       جوال يكتب بخانة ناقصة — ولا شيء يقول إن أحدا ضاع.
 *   ٣ — **ومصدر العميل يسقط أول ما يسقط.** الاسم والجوال ينسخان، وأي
 *       حملة جاء منها وأي إعلان وأي نموذج لا ينسخ — فلا يعرف أحد أي
 *       إعلان يأتي بمن يشتري وأيها يحرق الميزانية.
 *
 * ═══ سبع قواعد بني عليها هذا الملف ═══
 *
 * ١ — **الويبهوك مفتاح جلب لا بيان.** ميتا لا ترسل بيانات العميل في
 *     النداء، ترسل `leadgen_id` وحده — والقرار على `GET /<id>` برمز
 *     الصفحة. فمن اخترع جسم ويبهوك لم يفعل شيئا: يقيد سطرا في السجل
 *     ويرد الجلب «لا عميل بهذا المعرف». وهي قاعدة تاب نفسها حرفا.
 *
 * ٢ — **والتوقيع يفحص ويرد لا يسجل وحده.** خلافا لتاب — وهذا فرق مقصود:
 *     هناك التوقيع خبر لأن القرار على جلب مستقل بمفتاح سري، وهنا الجلب
 *     يقع **بمعرف يأتي في الجسم** فمن يرسل أجساما مخترعة يجعلنا نطرق
 *     ميتا مئة مرة في الثانية عنه. فمتى حفظ سر التطبيق رد ما لا يطابق
 *     بـ403 — ويقيد قبل أن يرد، فمن يفتح السجل يعرف أن طلبا من غير
 *     ميتا وصل.
 *
 * ٣ — **و`meta_lead_id` فريد في المخطط لا في شرط `if`.** ميتا تعيد
 *     النداء عند كل رد ليس 200، وعند الشك تعيده بلا سبب — فالعميل
 *     الواحد يصل مرتين وثلاثا. وفحص «هل هو موجود؟» قبل الإدراج يمر
 *     منه نداءان في الثانية نفسها كلاهما يقرأ «لا». فالمفتاح الفريد
 *     هو الحكم، والثاني يرتد عليه بلا ضرر.
 *
 * ٤ — **والسجل يقيد قبل الجلب لا بعده.** `tq_lead_hooks` صف لكل نداء
 *     وارد: بحاله وسبب تعثره وعدد محاولاته. فسؤال «لماذا لم يصل هذا
 *     العميل؟» يجاب من صف لا من ظن — وهو السؤال الذي يسأل عند أول
 *     شكوى، ولا جواب له في سجل خادم يمسح كل أسبوع.
 *
 * ٥ — **وما فشل يعاد من الكرون لا من ميتا.** نرد 200 على النداء الذي
 *     وصل وصح توقيعه **وإن فشل جلبه**: إعادة ميتا عشوائية التوقيت
 *     وتتوقف بعد محاولات، وإعادتنا منتظمة ومعها سبب كل تعثر. وأثر ذلك
 *     أن العميل الذي وصل **قبل أن يحفظ الرمز أصلا** يجلب في أول دورة
 *     بعد حفظه — فلا يفقد من طرق الباب قبل أن يفتح.
 *
 * ٦ — **وكل ما ترده ميتا يحفظ كما جاء** في `answers` و`raw`: النموذج
 *     يعدل في الحملة فتضاف فيه أسئلة لا يعرفها عمود في جدولنا، وأعمدة
 *     نصنعها اليوم لأسئلة اليوم تسقط سؤال الغد صامتة. فالمعروف يفرد
 *     في أعمدته ليرشح ويبحث، والكل يبقى كما ورد.
 *
 * ٧ — **بلا رمز صفحة لا شيء يتغير.** لا نداء يخرج من هنا، ولا بند في
 *     الشريط الجانبي يعد شيئا، ولا صفحة تتغير — وهي قاعدة تاب وواتساب
 *     وبيع الكورسات نفسها.
 *
 * ── وأين «الـCRM»؟ ──
 *
 * لا نظام ثالث: **لوحة تقدر هي مستودع العملاء المحتملين.** إقحام نظام
 * خارجي هنا يعني مصدري حقيقة لعميل واحد يفترقان أول ما يغير أحدهما
 * حاله، ومزامنة بينهما تكتب مرتين وتنسى مرة. وشاشة `taqdar_admin/leads`
 * تجيب أسئلة المتابعة كلها — ومن أراد نقل الصفوف إلى نظام آخر يوما
 * ينقلها بتصدير الشاشة، وهو باب واحد لا خط مزامنة.
 */
class Taqdar_lead_model extends CI_Model
{
    /** مفاتيح الإعدادات — كلها في `settings` بالبادئة `tq_meta_`. */
    public static $KEYS = array(
        'tq_meta_page_id',
        'tq_meta_page_token',
        'tq_meta_app_secret',
        'tq_meta_verify_token',
        'tq_meta_lead_notify',
    );

    /**
     * حقول عقدة العميل عند ميتا — المصدر الواحد لما يسأل عنه.
     *
     * و`page_id` ليس منها: عقدة العميل لا تحمله، ويأتي في جسم الويبهوك
     * وحده. فمن يضيفه هنا يرد عليه «(#100) Unknown field» فيسقط الجلب
     * كله — ومعه اسم الحملة والإعلان اللذان يقرآن في السطر نفسه.
     */
    const LEAD_FIELDS = 'id,created_time,field_data,platform,is_organic,form_id,'
                      . 'ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name';

    /** ميتا تحفظ العميل تسعين يوما، فما مضى عليه ذلك لا يجلب أبدا. */
    const KEEP_DAYS = 90;

    /** أكثر ما يحاول الجلب قبل أن يترك الصف بحاله. */
    const MAX_TRIES = 6;

    private $cfg = null;
    private $schema_checked = false;

    /* =====================================================================
       الإعدادات
       ===================================================================== */

    public function config()
    {
        if ($this->cfg !== null) return $this->cfg;

        $vals = array();
        try {
            $rows = $this->db->select('key, value')->where_in('key', self::$KEYS)
                             ->get('settings')->result_array();
            foreach ($rows as $r) $vals[$r['key']] = (string) $r['value'];
        } catch (Throwable $e) {
            /* TQ-BUILDER-DIRTY — الاستثناء يترك بناء الاستعلام موصولا. */
            $this->db->reset_query();
            log_message('error', 'TQ-LEADS: could not read settings -- ' . $e->getMessage());
        }

        $g = function ($k) use ($vals) {
            return isset($vals[$k]) ? trim((string) $vals[$k]) : '';
        };

        $this->cfg = array(
            'page_id' => preg_replace('/\D+/', '', $g('tq_meta_page_id')),
            'token'   => $g('tq_meta_page_token'),
            'secret'  => $g('tq_meta_app_secret'),
            'verify'  => $g('tq_meta_verify_token'),
            /* والإشعار افتراضه **مشتعل**: من يضبط هذا الربط يضبطه ليعلم
               بالعميل في دقيقته، وقياس يصل إلى جدول لا يفتحه أحد يعيد
               العطل الذي كتب الربط من أجله. ولا صف يعني «لم يضبط» لا
               «أطفأه مسؤول» — والثاني يكتب صفا فارغا (قاعدة البكسل). */
            'notify'  => !isset($vals['tq_meta_lead_notify'])
                       || trim((string) $vals['tq_meta_lead_notify']) === '1',
        );
        return $this->cfg;
    }

    /** أيجلب الخادم عميلا الآن؟ رمز الصفحة وحده هو الشرط. */
    public function ready()
    {
        return $this->config()['token'] !== '';
    }

    /** أيستطيع الويبهوك أن يوثق نفسه عند ميتا؟ */
    public function verify_ready()
    {
        return $this->config()['verify'] !== '';
    }

    /** عنوان الويبهوك كما يكتب في شاشة ميتا — موضع واحد لا يخطأ نسخه. */
    public function callback_url()
    {
        return site_url('webhook/meta/leads');
    }

    /**
     * رمز تحقق يقترح على من لم يكتب واحدا.
     *
     * ولا يحفظ من هنا: الرمز يكتب في موضعين — عندنا وفي شاشة ميتا —
     * فتوليده وحفظه بلا أن يراه أحد يعني أن طرفا واحدا يعرفه، والويبهوك
     * لا يوثق أبدا ولا يقول أحد لماذا.
     */
    public function suggest_verify_token()
    {
        try {
            return 'tq-' . bin2hex(random_bytes(12));
        } catch (Throwable $e) {
            return 'tq-' . md5(uniqid('', true));
        }
    }

    /* =====================================================================
       المخطط — جدولان ينشآن وقت التشغيل كأخواتهما
       ===================================================================== */

    public function install_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        try {
            /* والصفوف تحمل ما يرشح به في أعمدة، وما يقرأ منه في نص:
               `answers` أسئلة النموذج كما وردت، و`raw` رد ميتا كله.
               ومن يفرد كل سؤال عمودا يبني جدولا يتغير مع كل حملة. */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_leads` (
                    `id`            int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `meta_lead_id`  varchar(64)  NOT NULL,
                    `page_id`       varchar(64)  DEFAULT NULL,
                    `form_id`       varchar(64)  DEFAULT NULL,
                    `form_name`     varchar(190) DEFAULT NULL,
                    `campaign_id`   varchar(64)  DEFAULT NULL,
                    `campaign_name` varchar(190) DEFAULT NULL,
                    `adset_id`      varchar(64)  DEFAULT NULL,
                    `adset_name`    varchar(190) DEFAULT NULL,
                    `ad_id`         varchar(64)  DEFAULT NULL,
                    `ad_name`       varchar(190) DEFAULT NULL,
                    `platform`      varchar(24)  DEFAULT NULL,
                    `is_organic`    tinyint(1)   NOT NULL DEFAULT 0,
                    `full_name`     varchar(190) DEFAULT NULL,
                    `phone`         varchar(32)  DEFAULT NULL,
                    `email`         varchar(190) DEFAULT NULL,
                    `city`          varchar(120) DEFAULT NULL,
                    `answers`       mediumtext   DEFAULT NULL,
                    `raw`           mediumtext   DEFAULT NULL,
                    `user_id`       int(10) unsigned NOT NULL DEFAULT 0,
                    `status`        varchar(16)  NOT NULL DEFAULT "new",
                    `note`          text         DEFAULT NULL,
                    `decided_by`    int(10) unsigned NOT NULL DEFAULT 0,
                    `decided_at`    datetime     DEFAULT NULL,
                    `created_time`  datetime     DEFAULT NULL,
                    `received_at`   datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_lead` (`meta_lead_id`),
                    KEY `k_status` (`status`),
                    KEY `k_when` (`created_time`),
                    KEY `k_campaign` (`campaign_id`),
                    KEY `k_form` (`form_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            /* سجل النداءات — صف لكل نداء وارد لا لكل عميل: النداء قد
               يصل ولا يصير عميلا (توقيع لا يطابق، جلب يفشل، تكرار)،
               وهي الحالات التي يسأل عنها. */
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_lead_hooks` (
                    `id`           int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `event`        varchar(32)  NOT NULL DEFAULT "leadgen",
                    `meta_lead_id` varchar(64)  DEFAULT NULL,
                    `page_id`      varchar(64)  DEFAULT NULL,
                    `form_id`      varchar(64)  DEFAULT NULL,
                    `lead_row`     int(10) unsigned NOT NULL DEFAULT 0,
                    `signature`    varchar(12)  NOT NULL DEFAULT "",
                    `status`       varchar(16)  NOT NULL DEFAULT "pending",
                    `tries`        smallint(5) unsigned NOT NULL DEFAULT 0,
                    `http_code`    smallint(5) unsigned NOT NULL DEFAULT 0,
                    `note`         varchar(255) DEFAULT NULL,
                    `body`         mediumtext   DEFAULT NULL,
                    `ip`           varchar(45)  DEFAULT NULL,
                    `created_at`   datetime     DEFAULT NULL,
                    `done_at`      datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `k_status` (`status`),
                    KEY `k_lead` (`meta_lead_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-LEADS: could not create lead tables -- ' . $e->getMessage());
        }
    }

    /* =====================================================================
       حال العميل — وصف واحد تقرأ منه الشاشتان
       ===================================================================== */

    /**
     * الحالات الخمس، ومعها ما يقال عنها.
     *
     * وخمس لا اثنتان لأن السؤال الذي تجيبه الشاشة ليس «أتابعناه؟» بل
     * «أين هو في القمع؟»: من اتصل به ولم يجب غير من اتصل ولم يناسبه
     * السعر، والثاني قرار ينهي المتابعة والأول يستأنفها غدا. ودمجهما
     * في «متابع» يجعل القائمة لا تقول لأحد ما يفعل.
     */
    public function statuses()
    {
        return tq_t_deep(array(
            /* والنغمات من نغمات `tqa-badge--*` الخمس وحدها (ok · info ·
               warn · danger · muted): نغمة مخترعة تطبع صنفا لا قاعدة
               له في الورقة، فتخرج الرقاقة بلا لون ولا يظهر ذلك إلا
               بالمقارنة البصرية. و«جديد» تحمل `warn` لأنها بند عمل لا
               خبر: من وصل ولم يتصل به أحد هو الذي يضيع. */
            'new'         => array('label' => 'جديد',          'tone' => 'warn'),
            'contacted'   => array('label' => 'تم التواصل',    'tone' => 'info'),
            'qualified'   => array('label' => 'مؤهل',          'tone' => 'ok'),
            'unqualified' => array('label' => 'غير مؤهل',      'tone' => 'muted'),
            'converted'   => array('label' => 'تحول إلى عميل', 'tone' => 'ok'),
        ));
    }

    public function status_ok($s)
    {
        return array_key_exists((string) $s, $this->statuses());
    }

    /** فيسبوك أو إنستغرام بالعربية — ومصدر واحد للتسمية. */
    public function platform_word($p)
    {
        $p = strtolower(trim((string) $p));
        $m = array('fb' => 'فيسبوك', 'facebook' => 'فيسبوك',
                   'ig' => 'إنستغرام', 'instagram' => 'إنستغرام');
        if (isset($m[$p])) return t($m[$p]);
        return $p !== '' ? $p : t('غير محدد');
    }

    /* =====================================================================
       الويبهوك — التوثق والتوقيع
       ===================================================================== */

    /**
     * فحص رمز التحقق عند تسجيل الويبهوك (`hub.verify_token`).
     *
     * و`hash_equals` لا `===`: المقارنة الحرفية تنتهي عند أول حرف يختلف،
     * وفرق التوقيت بين ردين يقرأ منه المحرف كم حرفا أصاب. والرمز هنا
     * سر يفتح بابا يكتب في جدولنا.
     */
    public function verify_token_ok($given)
    {
        $want = $this->config()['verify'];
        if ($want === '') return false;
        return hash_equals($want, (string) $given);
    }

    /**
     * فحص توقيع ميتا على الجسم كما ورد حرفا.
     *
     * @return string 'ok' · 'bad' · 'absent' (لا سر محفوظ فلا حكم)
     *
     * والتوقيع على **الجسم الخام** لا على ما فككه `json_decode` وأعاد
     * ترميزه: فراغ واحد أو ترتيب مفتاحين يختلف يخرج هاشا آخر، فيرد
     * الباب 403 على ميتا نفسها ولا يصل عميل واحد.
     */
    public function signature_state($raw, $header)
    {
        $secret = $this->config()['secret'];
        if ($secret === '') return 'absent';

        $given = trim((string) $header);
        if (strpos($given, 'sha256=') === 0) $given = substr($given, 7);
        if ($given === '') return 'bad';

        $want = hash_hmac('sha256', (string) $raw, $secret);
        return hash_equals($want, $given) ? 'ok' : 'bad';
    }

    /**
     * يقيد نداء رد بـ403 — فمن يفتح السجل يعرف أن طلبا غير موثق وصل.
     *
     * ولا يحفظ جسمه: جسم يرسله من يشاء قد يكون ميغابايت من نص، وسجل
     * يملأ من الخارج باب إشغال لا سجل.
     */
    public function log_rejected($why, $ip)
    {
        $this->install_schema();
        $this->log_hook(array(
            'event' => 'rejected', 'signature' => 'bad', 'status' => 'rejected',
            'ip' => $ip, 'note' => (string) $why,
        ), null);
    }

    /* =====================================================================
       الاستقبال
       ===================================================================== */

    /**
     * يستقبل جسم ويبهوك ميتا كله ويقيد ما فيه.
     *
     * والجسم قد يحمل **أكثر من عميل**: ميتا تجمع نداءات الثانية الواحدة
     * في `entry` واحد بعدة `changes`، فالمرور على الكل لا على أوله —
     * وقراءة الأول وحده تعني أن ذروة الحملة هي التي تفقد فيها العملاء.
     *
     * @return array عداد لكل ما جرى — يطبع في رد الباب ويقرأ في السجل
     */
    public function receive(array $payload, $sig_state, $ip)
    {
        $this->install_schema();

        $out = array('leads' => 0, 'stored' => 0, 'duplicate' => 0,
                     'failed' => 0, 'ignored' => 0);

        $object  = (string) (isset($payload['object']) ? $payload['object'] : '');
        $entries = (isset($payload['entry']) && is_array($payload['entry'])) ? $payload['entry'] : array();

        /* والاشتراك قد يكون على أشياء أخرى في التطبيق نفسه (رسائل
           الصفحة، تعليقاتها). فما ليس `leadgen` يقيد «تجاهل» ولا يرد
           بخطأ: خطأ يجعل ميتا تعيد نداء لا شأن لنا به إلى الأبد. */
        if ($object !== 'page' || !$entries) {
            $this->log_hook(array(
                'event' => $object !== 'page' ? 'object:' . mb_substr($object, 0, 18) : 'empty',
                'signature' => $sig_state, 'status' => 'ignored', 'ip' => $ip,
                'note' => 'ليس نداء عميل محتمل من صفحة.',
            ), $payload);
            $out['ignored']++;
            return $out;
        }

        foreach ($entries as $entry) {
            $changes = (isset($entry['changes']) && is_array($entry['changes'])) ? $entry['changes'] : array();
            foreach ($changes as $ch) {
                $field = (string) (isset($ch['field']) ? $ch['field'] : '');
                $v     = (isset($ch['value']) && is_array($ch['value'])) ? $ch['value'] : array();

                if ($field !== 'leadgen') {
                    $this->log_hook(array(
                        'event' => 'field:' . mb_substr($field, 0, 18),
                        'signature' => $sig_state, 'status' => 'ignored', 'ip' => $ip,
                        'note' => 'حدث غير leadgen في الاشتراك نفسه.',
                    ), $ch);
                    $out['ignored']++;
                    continue;
                }

                $lid  = trim((string) (isset($v['leadgen_id']) ? $v['leadgen_id'] : ''));
                $hint = array(
                    'page_id'      => (string) (isset($v['page_id']) ? $v['page_id']
                                              : (isset($entry['id']) ? $entry['id'] : '')),
                    'form_id'      => (string) (isset($v['form_id']) ? $v['form_id'] : ''),
                    'ad_id'        => (string) (isset($v['ad_id']) ? $v['ad_id'] : ''),
                    'created_time' => (int) (isset($v['created_time']) ? $v['created_time'] : 0),
                );

                $hook = $this->log_hook(array(
                    'event' => 'leadgen', 'meta_lead_id' => $lid,
                    'page_id' => $hint['page_id'], 'form_id' => $hint['form_id'],
                    'signature' => $sig_state, 'status' => 'pending', 'ip' => $ip,
                ), $ch);

                $out['leads']++;

                if ($lid === '') {
                    $this->close_hook($hook, 'failed', 0, 'نداء leadgen بلا معرف عميل.');
                    $out['failed']++;
                    continue;
                }

                $r = $this->pull($lid, $hint, $hook);
                if (!empty($r['duplicate']))   $out['duplicate']++;
                elseif (!empty($r['ok']))      $out['stored']++;
                else                           $out['failed']++;
            }
        }

        return $out;
    }

    /* =====================================================================
       الجلب من ميتا
       ===================================================================== */

    /**
     * يجلب عميلا بمعرفه ويخزنه. مأمون التكرار.
     *
     * @param string $lid  معرف العميل عند ميتا
     * @param array  $hint ما جاء في جسم الويبهوك (الصفحة والنموذج والإعلان)
     * @param int    $hook صف السجل الذي يغلق بالنتيجة
     */
    public function pull($lid, array $hint = array(), $hook = 0)
    {
        $this->install_schema();
        $lid = trim((string) $lid);
        if ($lid === '') {
            $this->close_hook($hook, 'failed', 0, 'لا معرف عميل.');
            return array('ok' => false, 'msg' => 'لا معرف عميل.');
        }

        /* الموجود لا يجلب مرتين: نداء ميتا المكرر يكلفنا طلبا على واجهتها
           وحدها، ولها سقف. والتكرار يقيد ولا يبتلع — من يفتح السجل
           يحتاج أن يفرق «وصل مرتين» عن «لم يصل». */
        $have = $this->by_meta_id($lid);
        if ($have) {
            $this->close_hook($hook, 'duplicate', 0, 'وصل هذا العميل من قبل.', (int) $have['id']);
            return array('ok' => true, 'duplicate' => true, 'lead_id' => (int) $have['id']);
        }

        $c = $this->config();
        if ($c['token'] === '') {
            /* والصف يبقى `failed` قصدا لا `ignored`: يعاد من الكرون،
               فمن ضبط الرمز بعد ظهر اليوم يجد عملاء الصباح في اللوحة. */
            $this->close_hook($hook, 'failed', 0, 'لا رمز صفحة محفوظ، فلا يجلب شيء. يعاد بعد حفظه.');
            return array('ok' => false, 'msg' => 'لا رمز صفحة محفوظ.');
        }

        $this->load->model('taqdar_meta_model');
        $r = $this->taqdar_meta_model->graph_get(
            rawurlencode($lid) . '?fields=' . self::LEAD_FIELDS
            . '&access_token=' . rawurlencode($c['token'])
        );

        if ($r['msg'] !== '' || empty($r['data']['id'])) {
            $msg = $r['msg'] !== '' ? $r['msg'] : ('ردت ميتا برمز ' . $r['code'] . ' بلا بيانات.');
            $this->close_hook($hook, 'failed', (int) $r['code'], $msg);
            log_message('error', 'TQ-LEADS: fetch failed for ' . $lid . ' -- ' . $msg);
            return array('ok' => false, 'msg' => $msg, 'code' => (int) $r['code']);
        }

        $s = $this->store($r['data'], $hint);
        if (!empty($s['duplicate'])) {
            $this->close_hook($hook, 'duplicate', 200, 'وصل هذا العميل من قبل.', (int) $s['lead_id']);
        } elseif (!empty($s['ok'])) {
            $this->close_hook($hook, 'stored', 200, null, (int) $s['lead_id']);
        } else {
            $this->close_hook($hook, 'failed', 200, (string) $s['msg']);
        }
        return $s;
    }

    /**
     * يكتب صف العميل من رد ميتا. المفتاح الفريد هو ما يمنع التكرار.
     *
     * وهي عامة لا خاصة لأن الجلب والتخزين سؤالان: من يستقبل من ميتا
     * ينادي `pull()`، ومن بيده الحمولة أصلا — استيراد ملف يوما — يكتبها
     * بالقواعد نفسها بلا أن يخترع ثانية.
     */
    public function store(array $lead, array $hint = array())
    {
        $this->install_schema();

        $lid = trim((string) (isset($lead['id']) ? $lead['id'] : ''));
        if ($lid === '') return array('ok' => false, 'msg' => 'رد ميتا بلا معرف عميل.');

        $fields = $this->fields_of($lead);

        $when = '';
        if (!empty($lead['created_time']))     $when = (string) $lead['created_time'];
        elseif (!empty($hint['created_time'])) $when = '@' . (int) $hint['created_time'];
        $ts = $when !== '' ? (int) strtotime($when) : 0;
        if ($ts <= 0) $ts = time();

        $row = array(
            'meta_lead_id'  => mb_substr($lid, 0, 64),
            'page_id'       => mb_substr((string) (isset($hint['page_id']) ? $hint['page_id'] : ''), 0, 64),
            'form_id'       => mb_substr((string) (isset($lead['form_id']) ? $lead['form_id']
                                                 : (isset($hint['form_id']) ? $hint['form_id'] : '')), 0, 64),
            'campaign_id'   => mb_substr((string) (isset($lead['campaign_id']) ? $lead['campaign_id'] : ''), 0, 64),
            'campaign_name' => mb_substr((string) (isset($lead['campaign_name']) ? $lead['campaign_name'] : ''), 0, 190),
            'adset_id'      => mb_substr((string) (isset($lead['adset_id']) ? $lead['adset_id'] : ''), 0, 64),
            'adset_name'    => mb_substr((string) (isset($lead['adset_name']) ? $lead['adset_name'] : ''), 0, 190),
            'ad_id'         => mb_substr((string) (isset($lead['ad_id']) ? $lead['ad_id']
                                                 : (isset($hint['ad_id']) ? $hint['ad_id'] : '')), 0, 64),
            'ad_name'       => mb_substr((string) (isset($lead['ad_name']) ? $lead['ad_name'] : ''), 0, 190),
            /* والمصدر فيسبوك أو إنستغرام — وهو ما يسأل عنه فريق الإعلان
               أول ما يسأل، ولا يشتق من الحملة: الحملة الواحدة تنشر في
               الاثنين. وميتا تسميه `platform`، ومعه `is_organic` لمن جاء
               من منشور لا من إعلان مدفوع. */
            'platform'      => mb_substr((string) (isset($lead['platform']) ? $lead['platform'] : ''), 0, 24),
            'is_organic'    => !empty($lead['is_organic']) ? 1 : 0,
            'full_name'     => mb_substr($fields['full_name'], 0, 190),
            'phone'         => mb_substr($fields['phone'], 0, 32),
            'email'         => mb_substr($fields['email'], 0, 190),
            'city'          => mb_substr($fields['city'], 0, 120),
            'answers'       => json_encode($fields['answers'], JSON_UNESCAPED_UNICODE),
            'raw'           => mb_substr((string) json_encode($lead, JSON_UNESCAPED_UNICODE), 0, 60000),
            'status'        => 'new',
            'created_time'  => date('Y-m-d H:i:s', $ts),
            'received_at'   => date('Y-m-d H:i:s'),
        );

        /* واسم النموذج لا يرد مع العميل — عقدة أخرى عند ميتا. ويقرأ من
           عميل سابق للنموذج نفسه قبل أن يطرق بابها: الحملة الواحدة
           تجلب مئة عميل من نموذج واحد، وطلب لاسمه مع كل واحد منهم مئة
           طلب على سقف الواجهة في شيء لا يتغير. */
        $row['form_name'] = mb_substr($this->form_name($row['form_id']), 0, 190);

        /* «أهو مسجل عندنا أصلا؟» — سؤال أول من يفتح الصف: من ملأ نموذج
           إعلان وله حساب قائم لا يباع له من جديد، يسأل عن اشتراكه. */
        $row['user_id'] = $this->match_user($row['email'], $row['phone']);

        try {
            $this->db->insert('tq_leads', $row);
            $id = (int) $this->db->insert_id();
        } catch (Throwable $e) {
            /* سباق على المفتاح الفريد — نداءان في الثانية نفسها، ومن
               خسره لم يخسر شيئا: الفائز كتب الصف كاملا. */
            $this->db->reset_query();
            $ex = $this->by_meta_id($lid);
            if ($ex) return array('ok' => true, 'duplicate' => true, 'lead_id' => (int) $ex['id']);

            log_message('error', 'TQ-LEADS: insert failed for ' . $lid . ' -- ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'تعذر كتابة الصف.');
        }

        $row['id'] = $id;
        $this->notify_new($row);

        return array('ok' => true, 'lead_id' => $id, 'row' => $row);
    }

    /**
     * يفرد المعروف من أسئلة النموذج، ويبقي الكل.
     *
     * وأسماء ميتا القياسية وحدها تفرد (`full_name` · `email` ·
     * `phone_number` …)، وما سواها سؤال كتبه صاحب الحملة بالعربية أو
     * بغيرها فيبقى في `answers` باسمه كما ورد. وتخمين عمود من نص سؤال
     * عربي يضع «متى يناسبك الاتصال؟» في خانة المدينة.
     */
    private function fields_of(array $lead)
    {
        $out  = array('full_name' => '', 'phone' => '', 'email' => '', 'city' => '', 'answers' => array());
        $data = (isset($lead['field_data']) && is_array($lead['field_data'])) ? $lead['field_data'] : array();

        $first = $last = '';

        foreach ($data as $f) {
            $name = trim((string) (isset($f['name']) ? $f['name'] : ''));
            $vals = (isset($f['values']) && is_array($f['values'])) ? $f['values'] : array();
            $val  = trim(implode(', ', array_map('strval', $vals)));
            if ($name === '') continue;

            $out['answers'][] = array(
                'name'  => $name,
                'value' => $val,
            );

            switch (strtolower($name)) {
                case 'full_name':
                case 'name':
                    if ($out['full_name'] === '') $out['full_name'] = $val;
                    break;
                case 'first_name': $first = $val; break;
                case 'last_name':  $last  = $val; break;
                case 'email':
                    if ($out['email'] === '') $out['email'] = $val;
                    break;
                case 'phone_number':
                case 'phone':
                    if ($out['phone'] === '') $out['phone'] = $this->phone_e164($val);
                    break;
                case 'city':
                    if ($out['city'] === '') $out['city'] = $val;
                    break;
            }
        }

        if ($out['full_name'] === '') {
            $out['full_name'] = trim($first . ' ' . $last);
        }
        return $out;
    }

    /**
     * الجوال كما يخزنه سائر المنصة: `+<رمز><وطني>` (TQ-PHONE-INTL).
     *
     * وميتا ترده بصور شتى — `+966 50 123 4567` و`00966501234567` وأحيانا
     * وطنيا عاريا. والصفر المزدوج بادئة اتصال دولي لا رمز دولة، وحفظه
     * كما جاء يجعل `to_e164()` تقرأ دولة لا وجود لها فيسقط واتساب صامتا.
     */
    private function phone_e164($raw)
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') return '';
        if (strpos($d, '00') === 0) $d = substr($d, 2);
        if (strlen($d) < 8 || strlen($d) > 15) return trim((string) $raw);
        return '+' . $d;
    }

    /** أهذا العميل صاحب حساب عندنا؟ بالبريد ثم بالجوال. */
    private function match_user($email, $phone)
    {
        $email = trim((string) $email);
        $phone = preg_replace('/\D+/', '', (string) $phone);

        try {
            if ($email !== '') {
                $u = $this->db->select('id')->where('email', $email)->limit(1)
                              ->get('users')->row_array();
                if ($u) return (int) $u['id'];
            }
            /* والجوال يقارن بآخر تسع خانات: ما في القاعدة منه وطني عار
               وما يأتي من ميتا دولي، ومقارنة النصين تقرأ «لا حساب» على
               رقم واحد كتب بصورتين. */
            if (strlen($phone) >= 9) {
                $tail = substr($phone, -9);
                $u = $this->db->select('id')
                              ->where("REPLACE(REPLACE(`phone`, ' ', ''), '-', '') LIKE "
                                    . $this->db->escape('%' . $tail), null, false)
                              ->limit(1)->get('users')->row_array();
                if ($u) return (int) $u['id'];
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
        return 0;
    }

    /**
     * اسم النموذج — من صف سابق له، وإلا من ميتا مرة واحدة.
     *
     * ولا يبطل شيئا إن لم يجئ: هو تسمية تقرأ، والعميل يصل بلا اسم
     * نموذجه كما يصل به.
     */
    private function form_name($form_id)
    {
        $form_id = trim((string) $form_id);
        if ($form_id === '') return '';

        static $seen = array();
        if (isset($seen[$form_id])) return $seen[$form_id];

        try {
            $r = $this->db->select('form_name')->where('form_id', $form_id)
                          ->where('form_name IS NOT NULL')->where('form_name !=', '')
                          ->limit(1)->get('tq_leads')->row_array();
            if ($r && trim((string) $r['form_name']) !== '') {
                return $seen[$form_id] = (string) $r['form_name'];
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
        }

        $c = $this->config();
        if ($c['token'] === '') return $seen[$form_id] = '';

        $this->load->model('taqdar_meta_model');
        $g = $this->taqdar_meta_model->graph_get(
            rawurlencode($form_id) . '?fields=name&access_token=' . rawurlencode($c['token'])
        );
        return $seen[$form_id] = (string) (isset($g['data']['name']) ? $g['data']['name'] : '');
    }

    /* =====================================================================
       السجل
       ===================================================================== */

    /** يقيد نداء واردا ويرد معرف صفه. */
    private function log_hook(array $d, $body = null)
    {
        $status = (string) (isset($d['status']) ? $d['status'] : 'pending');
        try {
            $this->db->insert('tq_lead_hooks', array(
                'event'        => mb_substr((string) (isset($d['event']) ? $d['event'] : 'leadgen'), 0, 32),
                'meta_lead_id' => mb_substr((string) (isset($d['meta_lead_id']) ? $d['meta_lead_id'] : ''), 0, 64),
                'page_id'      => mb_substr((string) (isset($d['page_id']) ? $d['page_id'] : ''), 0, 64),
                'form_id'      => mb_substr((string) (isset($d['form_id']) ? $d['form_id'] : ''), 0, 64),
                'signature'    => mb_substr((string) (isset($d['signature']) ? $d['signature'] : ''), 0, 12),
                'status'       => mb_substr($status, 0, 16),
                'note'         => isset($d['note']) ? mb_substr((string) $d['note'], 0, 255) : null,
                /* والجسم يحفظ لأن «ما الذي وصل بالضبط؟» لا يجاب بعده من
                   موضع آخر: ميتا لا تحفظ ما أرسلت، ولا سجل الخادم يحمل
                   أجسام POST. */
                'body'         => $body === null ? null
                                : mb_substr((string) json_encode($body, JSON_UNESCAPED_UNICODE), 0, 20000),
                'ip'           => mb_substr((string) (isset($d['ip']) ? $d['ip'] : ''), 0, 45),
                'tries'        => $status === 'pending' ? 1 : 0,
                'created_at'   => date('Y-m-d H:i:s'),
            ));
            return (int) $this->db->insert_id();
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-LEADS: could not log incoming hook -- ' . $e->getMessage());
            return 0;
        }
    }

    /** يغلق صف السجل بنتيجته. */
    private function close_hook($id, $status, $code, $note, $lead_row = 0)
    {
        if ((int) $id <= 0) return;
        try {
            $this->db->where('id', (int) $id)->update('tq_lead_hooks', array(
                'status'    => (string) $status,
                'http_code' => (int) $code,
                'note'      => $note === null ? null : mb_substr((string) $note, 0, 255),
                'lead_row'  => (int) $lead_row,
                'done_at'   => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
    }

    /* =====================================================================
       الإعادة — شبكة الأمان تحت الجلب الحي
       ===================================================================== */

    /**
     * يعيد جلب ما تعثر. تنادى من `taqdar_cron reconcile` ومن زر اللوحة.
     *
     * والقيد `tries < MAX_TRIES`: عميل محذوف عند ميتا أو نموذج سحبت
     * صلاحيته لا يجلب أبدا، وإعادة بلا حد تطرق واجهة ميتا كل ربع ساعة
     * إلى الأبد على صف لا يرجى.
     */
    public function retry_failed($limit = 25)
    {
        $this->install_schema();
        $out = array('tried' => 0, 'stored' => 0);

        if (!$this->ready()) return $out;

        try {
            $rows = $this->db->where('status', 'failed')
                             ->where('tries <', self::MAX_TRIES)
                             ->where('meta_lead_id !=', '')
                             ->where('meta_lead_id IS NOT NULL')
                             ->where('created_at >', date('Y-m-d H:i:s', time() - (self::KEEP_DAYS * 86400)))
                             ->order_by('id', 'ASC')->limit((int) $limit)
                             ->get('tq_lead_hooks')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return $out;
        }

        foreach ($rows as $r) {
            $out['tried']++;
            try {
                $this->db->where('id', (int) $r['id'])
                         ->set('tries', 'tries + 1', false)
                         ->update('tq_lead_hooks');
            } catch (Throwable $e) {
                $this->db->reset_query();
            }

            /* والتلميحة تقرأ من الجسم المحفوظ: `page_id` لا يرد مع
               العميل من ميتا أصلا، فإعادة بلا الجسم تكتب صفا بلا صفحة
               — ولا يعرف من يقرؤه من أي صفحة جاء. */
            $hint = array(
                'page_id' => (string) $r['page_id'],
                'form_id' => (string) $r['form_id'],
            );
            $body = json_decode((string) $r['body'], true);
            if (is_array($body) && isset($body['value']) && is_array($body['value'])) {
                $v = $body['value'];
                if (!empty($v['ad_id']))        $hint['ad_id']        = (string) $v['ad_id'];
                if (!empty($v['created_time'])) $hint['created_time'] = (int) $v['created_time'];
            }

            $res = $this->pull((string) $r['meta_lead_id'], $hint, (int) $r['id']);
            if (!empty($res['ok']) && empty($res['duplicate'])) $out['stored']++;
        }

        return $out;
    }

    /* =====================================================================
       الإشعار — السرعة هي البيعة
       ===================================================================== */

    /**
     * يخبر الإدارة بعميل جديد.
     *
     * ومعه البريد: القناة في `push_notification()` تابعة لا شرط، ومن لا
     * يفتح اللوحة كل ساعة يقرؤه في بريده. **وبلا واتساب** — `$wa = false`
     * صراحة: `Taqdar_wa_model::$PAY_TYPES` تقصر واتساب على المال ورموز
     * التحقق، ومن يصله خبر كل عميل محتمل يبلغ عن الرقم فتخفض ميتا حده
     * فتضيع معه إشعارات المال نفسها.
     *
     * ولا يبطل شيئا إن فشل: العميل كتب في الجدول، وهو المرساة.
     */
    private function notify_new(array $row)
    {
        if (!$this->config()['notify']) return;

        try {
            $admins = $this->db->select('id')->where('role_id', 1)->where('status', 1)
                               ->get('users')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return;
        }
        if (!$admins) return;

        $who   = trim((string) $row['full_name']);
        $lines = array();
        if ($who !== '')                                 $lines[] = 'الاسم: ' . $who;
        if (trim((string) $row['phone']) !== '')         $lines[] = 'الجوال: ' . $row['phone'];
        if (trim((string) $row['email']) !== '')         $lines[] = 'البريد: ' . $row['email'];
        if (trim((string) $row['campaign_name']) !== '') $lines[] = 'الحملة: ' . $row['campaign_name'];
        if (trim((string) $row['platform']) !== '')      $lines[] = 'المصدر: ' . $row['platform'];

        $body = implode("\n", $lines) . "\n\n"
              . 'افتح صفحته من «عملاء الإعلانات» في لوحة الإدارة، وسجل نتيجة تواصلك عليها.';

        try {
            $this->load->model('taqdar_admin_model');
            foreach ($admins as $a) {
                $this->taqdar_admin_model->push_notification(
                    (int) $a['id'],
                    'عميل محتمل جديد من إعلانات ميتا' . ($who !== '' ? ' — ' . $who : ''),
                    $body, 'system', true, false
                );
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-LEADS: could not notify admins -- ' . $e->getMessage());
        }
    }

    /* =====================================================================
       القراءة — اللوحة تعرض ولا تحكم
       ===================================================================== */

    public function by_meta_id($lid)
    {
        try {
            $r = $this->db->where('meta_lead_id', (string) $lid)->limit(1)
                          ->get('tq_leads')->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
        return $r ? $r : null;
    }

    /** صف واحد بكل ما فيه، ومعه اسم صاحب الحساب إن طابق. */
    public function lead($id)
    {
        $this->install_schema();
        try {
            $r = $this->db->select('l.*, TRIM(CONCAT(COALESCE(u.first_name, ""), " ",'
                    . ' COALESCE(u.last_name, ""))) AS user_name, u.email AS user_email', false)
                ->from('tq_leads l')
                ->join('users u', 'u.id = l.user_id', 'left')
                ->where('l.id', (int) $id)->limit(1)
                ->get()->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
        return $r ? $r : null;
    }

    /**
     * القائمة بمرشحاتها — الحال والحملة والبحث.
     *
     * والترشيح في الاستعلام لا في العرض: صفحة تقرأ ألفا ثم ترشح عشرة
     * تقرأ ألفا في كل فتح. وهي قاعدة الكتالوج نفسها.
     */
    public function leads(array $f = array(), $limit = 100, $offset = 0)
    {
        $this->install_schema();
        try {
            $this->db->select('l.*, TRIM(CONCAT(COALESCE(u.first_name, ""), " ",'
                    . ' COALESCE(u.last_name, ""))) AS user_name', false)
                ->from('tq_leads l')
                ->join('users u', 'u.id = l.user_id', 'left');

            if (!empty($f['status']) && $this->status_ok($f['status'])) {
                $this->db->where('l.status', (string) $f['status']);
            }
            if (!empty($f['campaign'])) {
                $this->db->where('l.campaign_id', (string) $f['campaign']);
            }
            if (!empty($f['platform'])) {
                $this->db->where('l.platform', (string) $f['platform']);
            }
            if (isset($f['q']) && trim((string) $f['q']) !== '') {
                $q = trim((string) $f['q']);
                $this->db->group_start()
                         ->like('l.full_name', $q)
                         ->or_like('l.phone', $q)
                         ->or_like('l.email', $q)
                         ->or_like('l.meta_lead_id', $q);

                /* والجوال يبحث بآخر تسع خانات كما يطابق `match_user()`:
                   المسؤول يلصق الرقم كما قرأه في هاتفه — `0501234567`
                   وطنيا — والمحفوظ `+966501234567`، فالبحث النصي يرد
                   «لا نتائج» على رقم قائم في الجدول. وهي علة واحدة في
                   موضعين، فالقاعدة واحدة. */
                $d = preg_replace('/\D+/', '', $q);
                if (strlen($d) >= 9) {
                    $this->db->or_like('l.phone', substr($d, -9));
                }
                $this->db->group_end();
            }

            return $this->db->order_by('l.created_time', 'DESC')->order_by('l.id', 'DESC')
                            ->limit((int) $limit, (int) $offset)
                            ->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-LEADS: list query failed -- ' . $e->getMessage());
            return array();
        }
    }

    /** عدد العملاء بكل حال — سطر الحال أعلى الشاشة، واستعلام واحد. */
    public function totals()
    {
        $this->install_schema();
        $out = array('all' => 0);
        foreach (array_keys($this->statuses()) as $k) $out[$k] = 0;

        try {
            $rows = $this->db->select('status, COUNT(*) AS n', false)
                             ->group_by('status')->get('tq_leads')->result_array();
            foreach ($rows as $r) {
                $out['all'] += (int) $r['n'];
                if (isset($out[$r['status']])) $out[$r['status']] = (int) $r['n'];
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
        return $out;
    }

    /**
     * الحملات التي وصل منها عميل — خيارات المرشح.
     *
     * ومن **المعروض** لا من واجهة ميتا: حملة أوقفت أمس تبقى في القائمة
     * لأن عملاءها في الجدول، وقائمة مبنية من ميتا تسقطها فيقرأ المسؤول
     * صفوفا لا يستطيع أن يرشح بها. وهي قاعدة مرشحات الكتالوج نفسها.
     */
    public function campaigns()
    {
        $this->install_schema();
        try {
            return $this->db->select('campaign_id, campaign_name, COUNT(*) AS n', false)
                            ->where('campaign_id IS NOT NULL')->where('campaign_id !=', '')
                            ->group_by(array('campaign_id', 'campaign_name'))
                            ->order_by('n', 'DESC')->limit(60)
                            ->get('tq_leads')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
    }

    /** آخر النداءات الواردة — «لماذا لم يصل هذا العميل؟» يجاب من صف. */
    public function hooks($limit = 30)
    {
        $this->install_schema();
        try {
            return $this->db->order_by('id', 'DESC')->limit((int) $limit)
                            ->get('tq_lead_hooks')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
    }

    public function hook_totals()
    {
        $this->install_schema();
        $out = array('stored' => 0, 'duplicate' => 0, 'failed' => 0,
                     'ignored' => 0, 'rejected' => 0, 'pending' => 0);
        try {
            $rows = $this->db->select('status, COUNT(*) AS n', false)
                             ->group_by('status')->get('tq_lead_hooks')->result_array();
            foreach ($rows as $r) {
                if (isset($out[$r['status']])) $out[$r['status']] = (int) $r['n'];
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
        return $out;
    }

    /** أسئلة النموذج مفككة — القالب يعرض ولا يفكك. */
    public function answers_of($row)
    {
        $a = json_decode((string) (isset($row['answers']) ? $row['answers'] : ''), true);
        return is_array($a) ? $a : array();
    }

    /* =====================================================================
       الكتابة — قرار المتابعة
       ===================================================================== */

    /**
     * يسجل حال العميل وملاحظة المتابعة.
     *
     * والقرار في النموذج لا في الشاشة: حال مخترعة تصل من `$_POST` كما
     * يصل غيرها، وصف يحمل `status = 'x'` يسقط من كل مرشح ومن كل عداد —
     * فيغيب العميل عن القائمة كلها ولا شيء يقول إنه هناك.
     */
    public function set_status($id, $status, $note, $actor = 0)
    {
        $this->install_schema();
        $id = (int) $id;

        $row = $this->lead($id);
        if (!$row) return array('ok' => false, 'msg' => 'لا عميل بهذا الرقم.');

        $status = (string) $status;
        if (!$this->status_ok($status)) {
            return array('ok' => false, 'msg' => 'حال غير معروفة.');
        }

        try {
            $this->db->where('id', $id)->update('tq_leads', array(
                'status'     => $status,
                'note'       => mb_substr(trim((string) $note), 0, 4000),
                'decided_by' => (int) $actor,
                'decided_at' => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-LEADS: status update failed -- ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'تعذر حفظ الحال.');
        }

        return array('ok' => true, 'was' => (string) $row['status'], 'now' => $status);
    }

    /* =====================================================================
       التحقق — «أيعمل الربط الآن؟» يجاب من ميتا لا من ظن
       ===================================================================== */

    /**
     * يسأل ميتا ثلاثة أسئلة في نداء واحد ونصف:
     *
     *   ١ — أرمز الصفحة صالح؟ وهل هو **دائم** أم ينتهي بعد ساعات؟ —
     *       وهذا ما طلب صراحة: رمز مؤقت في الإنتاج يعمل يوما ثم يسكت
     *       الربط كله، ولا رسالة خطأ في أي موضع إلا صفوف `failed`.
     *   ٢ — أفيه صلاحية `leads_retrieval`؟ وبلاها يصل الويبهوك ويرد
     *       الجلب «(#190) Missing permission» على كل عميل.
     *   ٣ — وهل **الصفحة مشتركة في حدث `leadgen`**؟ وهذا أكثر ما ينسى:
     *       التطبيق مضبوط والرمز صحيح، ولا يصل نداء واحد أبدا لأن أحدا
     *       لم يشترك الصفحة في الحدث. والسجل فارغ فيظن من يفحص أن
     *       الحملة لم تجلب أحدا.
     */
    public function probe()
    {
        $c = $this->config();
        if ($c['token'] === '') {
            return array('ok' => false, 'msg' => 'لا رمز صفحة محفوظ، فلا يجلب الخادم عميلا واحدا.');
        }

        $this->load->model('taqdar_meta_model');
        $tok = rawurlencode($c['token']);

        $r = $this->taqdar_meta_model->graph_get(
            'debug_token?input_token=' . $tok . '&access_token=' . $tok
        );
        if ($r['msg'] !== '') return array('ok' => false, 'msg' => $r['msg']);

        $d = (isset($r['data']['data']) && is_array($r['data']['data'])) ? $r['data']['data'] : array();
        if (!$d) {
            return array('ok' => false, 'msg' => 'رد ميتا برمز ' . $r['code'] . ' بلا بيانات عن الرمز.');
        }
        if (empty($d['is_valid'])) {
            return array('ok' => false, 'msg' => 'الرمز غير صالح أو انتهت صلاحيته — ولد رمزا جديدا من «مستخدم النظام».');
        }

        $say = array();

        /* الدوام: ميتا تكتب `expires_at = 0` للرمز الذي لا ينتهي. */
        $exp = (int) (isset($d['expires_at']) ? $d['expires_at'] : 0);
        $say[] = $exp === 0
               ? 'الرمز صالح ودائم لا ينتهي.'
               : ('الرمز صالح ولكنه مؤقت: ينتهي في ' . date('Y-m-d H:i', $exp)
                  . ' — ولا يستعمل مثله في التشغيل الحقيقي، فالربط يسكت بعده بلا خطأ يظهر.');

        /* والنوع: رمز صفحة لا رمز مستخدم. وقراءة العميل تحتاج الأول. */
        $type = strtoupper((string) (isset($d['type']) ? $d['type'] : ''));
        if ($type !== '' && $type !== 'PAGE') {
            $say[] = 'وهو رمز ' . $type . ' لا رمز صفحة — وقراءة العملاء تحتاج رمز الصفحة نفسها.';
        }

        $scopes = (isset($d['scopes']) && is_array($d['scopes'])) ? $d['scopes'] : array();
        $has    = in_array('leads_retrieval', $scopes, true);
        $say[]  = $has
                ? 'وفيه صلاحية leads_retrieval.'
                : 'ولا صلاحية leads_retrieval فيه — فيصل النداء ويرد الجلب بخطأ صلاحية على كل عميل.';

        /* والصفحة: معرفها في `profile_id` للرمز، ويقارن بالمحفوظ. */
        $pid = (string) (isset($d['profile_id']) ? $d['profile_id'] : '');
        if ($pid !== '' && $c['page_id'] !== '' && $pid !== $c['page_id']) {
            $say[] = 'وهو رمز الصفحة ' . $pid . ' لا الصفحة المحفوظة ' . $c['page_id']
                   . ' — فالاشتراك على صفحة والجلب على أخرى.';
            $has = false;
        }

        /* وسؤال الاشتراك — نداء ثان، ويحتاج معرف الصفحة. */
        $page = $pid !== '' ? $pid : $c['page_id'];
        if ($page !== '') {
            $s = $this->taqdar_meta_model->graph_get(
                rawurlencode($page) . '/subscribed_apps?access_token=' . $tok
            );
            $apps = (isset($s['data']['data']) && is_array($s['data']['data'])) ? $s['data']['data'] : array();
            $on   = false;
            foreach ($apps as $a) {
                $fields = (isset($a['subscribed_fields']) && is_array($a['subscribed_fields']))
                        ? $a['subscribed_fields'] : array();
                if (in_array('leadgen', $fields, true)) { $on = true; break; }
            }
            if ($on) {
                $say[] = 'والصفحة مشتركة في حدث leadgen، فالنداء يصل عند أول عميل.';
            } elseif ($apps) {
                $say[] = 'والصفحة مربوطة بالتطبيق ولكن بلا حدث leadgen — اضغط «اشترك في حدث العملاء» أدناه.';
            } else {
                $say[] = 'ولا تطبيق مشترك على هذه الصفحة أصلا — اضغط «اشترك في حدث العملاء» أدناه، وبلا ذلك لا يصل نداء واحد أبدا.';
            }
        } else {
            $say[] = 'ولم يحفظ معرف الصفحة، فلا يسأل عن اشتراكها في حدث العملاء.';
        }

        return array('ok' => $has, 'msg' => implode(' ', $say));
    }

    /**
     * يشترك الصفحة في حدث `leadgen` — الخطوة التي لا يقول أحد إنها نقصت.
     *
     * والنداء يكتب لا يقرأ، فمنفذه `graph_post()`. ويحتاج رمز صفحة فيه
     * `pages_manage_metadata` — وهذا ما يقال في رسالة الرفض إن نقص،
     * فلا يقرأ المسؤول «(#200)» ولا يعرف ما يفعل.
     */
    public function subscribe_page()
    {
        $c = $this->config();
        if ($c['token'] === '')   return array('ok' => false, 'msg' => 'لا رمز صفحة محفوظ.');
        if ($c['page_id'] === '') return array('ok' => false, 'msg' => 'لا معرف صفحة محفوظ، ولا يعرف أي صفحة تشترك.');

        $this->load->model('taqdar_meta_model');
        $r = $this->taqdar_meta_model->graph_post(
            rawurlencode($c['page_id']) . '/subscribed_apps',
            array('subscribed_fields' => 'leadgen', 'access_token' => $c['token'])
        );

        if (!empty($r['data']['success'])) {
            return array('ok' => true, 'msg' => 'اشتركت الصفحة في حدث العملاء المحتملين. '
                . 'والنداء يصل عند أول عميل — أو جربه الآن بأداة Lead Ads Testing Tool عند ميتا.');
        }

        $msg = $r['msg'] !== '' ? $r['msg'] : ('ردت ميتا برمز ' . $r['code'] . ' بلا تأكيد.');
        if (strpos($msg, 'permission') !== false || strpos($msg, 'Permission') !== false) {
            $msg .= ' — والصلاحية الناقصة في الغالب pages_manage_metadata على رمز الصفحة.';
        }
        return array('ok' => false, 'msg' => $msg);
    }

    /* =====================================================================
       التصدير — الباب الواحد إلى أي نظام خارج
       ===================================================================== */

    /**
     * صفوف التصدير بالمرشحات نفسها التي تعرض بها الشاشة.
     *
     * وبالمرشحات نفسها قصدا: من رشح «مؤهل من حملة رمضان» ثم صدر يتوقع
     * ما رأى — وتصدير يخرج الجدول كله يجعله يرشح الملف مرة ثانية بيده.
     */
    public function export_rows(array $f = array(), $limit = 5000)
    {
        return $this->leads($f, (int) $limit, 0);
    }

    /** ترويسة الملف وأعمدته — مصدر واحد للترتيب فلا يزاغ عمود عن عنوانه. */
    public function export_columns()
    {
        return array(
            'meta_lead_id'  => 'معرف ميتا',
            'created_time'  => 'وقت التسجيل',
            'full_name'     => 'الاسم',
            'phone'         => 'الجوال',
            'email'         => 'البريد',
            'city'          => 'المدينة',
            'platform'      => 'المصدر',
            'campaign_name' => 'الحملة',
            'adset_name'    => 'المجموعة',
            'ad_name'       => 'الإعلان',
            'form_name'     => 'النموذج',
            'status'        => 'الحال',
            'note'          => 'ملاحظة المتابعة',
        );
    }
}
