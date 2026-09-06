<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-META-CAPI — حدث الشراء يرسل من الخادم، والمتصفح يكرره لا يبدؤه.
 *
 * البكسل يقيس ما يقع في متصفحنا، وصفحة الدفع ليست فيه: تاب تأخذ المشتري
 * إلى نطاقها فيدفع هناك ثم يعود. فالحدث الوحيد الذي تبنى عليه حملة
 * الإعلان — `Purchase` — يقع في الفجوة بين صفحتين، ولا شيء يسجله:
 *
 *   ١ — **من أغلق المتصفح بعد الدفع لا يعود أصلا.** رسالة البنك تصل على
 *       جواله فيطمئن ويغلق. والمال محصل والاشتراك مفتوح — ولا شراء في
 *       مدير الأحداث. وهي الحال نفسها التي كتب الويبهوك من أجلها.
 *   ٢ — **ومانع الإعلانات يمنع البكسل لا الدفع.** فمن يشتري ومعه مانع
 *       يشتري بلا أن يعد، وهم شريحة معتبرة.
 *   ٣ — **والتحويل البنكي يفعل بعد أيام** بيد مسؤول في اللوحة، ولا
 *       متصفح للمشتري في تلك اللحظة أصلا.
 *
 * والنقص **لا يقال**: ميتا توجه الميزانية إلى شبيه من تراهم يشترون، فإذا
 * خفي عنها ثلث المشترين تعلمت من الثلثين وحدهم — ورقم أقل من الحق يبدو
 * معقولا فلا يشك فيه أحد. وهو عطل `fbq('init')` المكرر نفسه معكوسا.
 *
 * ═══ ست قواعد بني عليها هذا الملف ═══
 *
 * ١ — **الفاتورة هي المرساة هنا كذلك.** الحدث يطلق من
 *     `Taqdar_billing_model::mark_invoice_paid()` — الموضع الوحيد الذي
 *     تصير فيه فاتورة مدفوعة في المنصة كلها: بطاقة وحوالة وحصة وباقة
 *     مجانية. فوحدة البيع الخامسة تقاس يوم تكتب بلا سطر يضاف هنا، وهو
 *     مبدأ `sold()` نفسه (TQ-SOLD-NAME).
 *
 * ٢ — **و`event_id` مشتق لا مولد.** `tq-inv-<رقم الفاتورة>`: المتصفح
 *     يحسبه والخادم يحسبه فيتفقان بلا أن يمر شيء عبر تحويل تاب. وميتا
 *     تطرح المكرر بـ(الاسم + المعرف)، فمن عاد إلى الموقع يرسل حدثه
 *     مرتين ويعد **مرة**. ومعرف عشوائي يعني شراءين لكل مشتر عاد.
 *
 * ٣ — **والصف يحجز قبل الإرسال لا بعده.** `settle()` تنادى من أربعة
 *     أبواب (عودة · ويبهوك · كرون · زر اللوحة)، وقد يصل بابان في
 *     الثانية نفسها. فالحجز صف بمفتاح فريد: من ظفر به أرسل، ومن رد
 *     عليه المفتاح انصرف. وبلا ذلك يرسل الحدث مرتين من داخلنا نحن.
 *
 * ٤ — **والرفض يسري على الخادم كما يسري على المتصفح.** من ضغط «رفض غير
 *     الضروري» لا يحمل له البكسل — وإرسال شرائه من الخادم بعد ذلك يجعل
 *     الزر تمثيلا ويخالف ما وعد به الشريط حرفا. فالقرار يمرآ في كعكة
 *     يقرؤها الخادم (`tq_consent`)، وتحفظ مع الفاتورة وقت إصدارها لأن
 *     التفعيل قد يقع بعد أيام في طلب ليس فيه متصفح أصلا.
 *
 * ٥ — **بلا رمز لا شيء يتغير.** `tq_meta_capi_token` فارغ افتراضا،
 *     وحينها لا يخرج من هنا طلب واحد ويبقى البكسل وحده كما كان حرفا
 *     بحرف. وهي قاعدة تاب نفسها.
 *
 * ٦ — **وفشله لا يبطل شيئا.** كل ما هنا ملفوف، وأسوأ ما يقع أن يقيد صف
 *     `failed` يعيده الكرون. الطالب دفع واستحق وصوله، ومنعه لأن قياسا
 *     إعلانيا لم يخرج يعاقب من لا ذنب له — وهي قاعدة قسمة الإيراد نفسها.
 */
class Taqdar_meta_model extends CI_Model
{
    /** جذر واجهة ميتا. والإصدار مثبت: ترقيته قرار يتخذ لا يقع. */
    const API = 'https://graph.facebook.com/v21.0/';

    /** ميتا ترفض حدثا أقدم من سبعة أيام. والهامش ساعة قبل الحد. */
    const FRESH_SEC = 601200;

    /** مفاتيح الإعدادات — كلها في `settings` بالبادئة `tq_meta_`. */
    public static $KEYS = array(
        'tq_meta_pixel_id',
        'tq_meta_capi_token',
        'tq_meta_test_code',
    );

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
            log_message('error', 'TQ-META: تعذر قراءة الإعدادات — ' . $e->getMessage());
        }

        /* المعرف من المساعد لا من الصف: هو الذي يفرق بين «لا صف» و«صف
           فارغ» ويسقط إلى الافتراضي — وقراءته هنا بغير ذلك تجعل الخادم
           يرسل بمعرف والمتصفح يرسل بآخر. */
        $pixel = function_exists('tq_meta_pixel_id')
               ? tq_meta_pixel_id()
               : preg_replace('/\D+/', '', (string) (isset($vals['tq_meta_pixel_id']) ? $vals['tq_meta_pixel_id'] : ''));

        $currency = strtoupper(trim((string) get_settings('system_currency')));
        if ($currency === '') $currency = 'SAR';

        $this->cfg = array(
            'pixel'    => (string) $pixel,
            'token'    => trim((string) (isset($vals['tq_meta_capi_token']) ? $vals['tq_meta_capi_token'] : '')),
            'test'     => trim((string) (isset($vals['tq_meta_test_code']) ? $vals['tq_meta_test_code'] : '')),
            'currency' => $currency,
        );
        return $this->cfg;
    }

    /**
     * هل يرسل الخادم شيئا الآن؟
     *
     * شرطان: معرف بكسل ورمز وصول. والثاني وحده هو الجديد — فمن لم يصنع
     * الرمز بعد يبقى على البكسل وحده ولا يتغير عنده شيء.
     */
    public function capi_ready()
    {
        $c = $this->config();
        return $c['pixel'] !== '' && $c['token'] !== '';
    }

    /* =====================================================================
       المخطط
       ===================================================================== */

    /**
     * جدولان ينشآن وقت التشغيل كأخواتهما.
     *
     * و`tq_meta_ids` جدول مستقل لا أعمدة على `invoices`: الفاتورة سجل
     * محاسبي، وكعكات إعلان لا مكان لها فيه — ولا على `payment_attempts`
     * لأن الحوالة البنكية بلا محاولة، وهي أحوج ما يكون إلى هذه الهوية.
     */
    public function ensure_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_meta_events` (
                    `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `event_name` varchar(40)  NOT NULL DEFAULT "Purchase",
                    `event_id`   varchar(64)  NOT NULL,
                    `invoice_id` int(10) unsigned NOT NULL DEFAULT 0,
                    `user_id`    int(10) unsigned NOT NULL DEFAULT 0,
                    `value`      bigint(20)   NOT NULL DEFAULT 0,
                    `currency`   varchar(8)   NOT NULL DEFAULT "SAR",
                    `source`     varchar(24)  NOT NULL DEFAULT "",
                    `status`     varchar(16)  NOT NULL DEFAULT "sending",
                    `http_code`  smallint(5) unsigned NOT NULL DEFAULT 0,
                    `tries`      smallint(5) unsigned NOT NULL DEFAULT 0,
                    `note`       varchar(255) DEFAULT NULL,
                    `created_at` datetime     DEFAULT NULL,
                    `sent_at`    datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_event` (`event_name`, `event_id`),
                    KEY `k_status` (`status`),
                    KEY `k_invoice` (`invoice_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_meta_ids` (
                    `invoice_id` int(10) unsigned NOT NULL,
                    `fbp`        varchar(128) DEFAULT NULL,
                    `fbc`        varchar(255) DEFAULT NULL,
                    `client_ip`  varchar(45)  DEFAULT NULL,
                    `user_agent` varchar(255) DEFAULT NULL,
                    `consent`    varchar(12)  DEFAULT NULL,
                    `source_url` varchar(255) DEFAULT NULL,
                    `created_at` datetime     DEFAULT NULL,
                    PRIMARY KEY (`invoice_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-META: تعذر إنشاء جداول القياس — ' . $e->getMessage());
        }
    }

    /* =====================================================================
       هوية المتصفح — تلتقط وقت الشراء وتقرأ وقت التفعيل
       ===================================================================== */

    /**
     * `_fbp` و`_fbc` وعنوانه ومتصفحه وقراره في الارتباط.
     *
     * وهذان المفتاحان هما ما يرفع «جودة المطابقة» عند ميتا من ضعيفة إلى
     * عالية: `_fbp` يعرف الزائر في نطاقنا، و`_fbc` يحمل نقرة الإعلان
     * التي جاء منها (`fbclid`) — وبها وحدها ينسب الشراء إلى الحملة التي
     * دفع فيها المال. وبلاهما يصل الحدث ويعد ولا ينسب إلى إعلان.
     */
    public function browser_ids()
    {
        if (!isset($_SERVER['REQUEST_METHOD'])) return array();   // كرون: لا طلب ولا كعكة

        $fbp = isset($_COOKIE['_fbp']) ? mb_substr((string) $_COOKIE['_fbp'], 0, 128) : '';
        $fbc = isset($_COOKIE['_fbc']) ? mb_substr((string) $_COOKIE['_fbc'], 0, 255) : '';

        /* البكسل مؤجل إلى `load` (TQ-PERF-PIXEL)، فقد يصل الطلب قبل أن
           يكتب `_fbc`. و`fbclid` في الرابط يبنيه بالصيغة نفسها. */
        if ($fbc === '') {
            $clid = trim((string) $this->input->get('fbclid'));
            if ($clid !== '') $fbc = 'fb.1.' . (time() * 1000) . '.' . $clid;
        }

        return array(
            'fbp'        => $fbp,
            'fbc'        => $fbc,
            'client_ip'  => (string) $this->input->ip_address(),
            'user_agent' => mb_substr((string) $this->input->user_agent(), 0, 255),
            'consent'    => isset($_COOKIE['tq_consent'])
                          ? mb_substr((string) $_COOKIE['tq_consent'], 0, 12) : '',
            'source_url' => mb_substr(current_url(), 0, 255),
        );
    }

    /**
     * يحفظ هوية المتصفح مع الفاتورة ساعة تصدر.
     *
     * ولماذا وقت الإصدار لا وقت الدفع: التحويل البنكي يفعل بعد أيام في
     * طلب إداري لا كعكة فيه، وعودة تاب قد لا تقع أصلا. واللحظة الوحيدة
     * التي نملك فيها متصفح المشتري بيقين هي اللحظة التي ضغط فيها «اشترك».
     */
    public function remember($invoice_id)
    {
        $invoice_id = (int) $invoice_id;
        if ($invoice_id <= 0) return false;

        $ids = $this->browser_ids();
        if (!$ids) return false;

        $this->ensure_schema();
        try {
            $this->db->replace('tq_meta_ids', array_merge(
                array('invoice_id' => $invoice_id),
                $ids,
                array('created_at' => date('Y-m-d H:i:s'))
            ));
            return true;
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-META: تعذر حفظ هوية الفاتورة #' . $invoice_id . ' — ' . $e->getMessage());
            return false;
        }
    }

    private function ids_of($invoice_id)
    {
        try {
            $row = $this->db->where('invoice_id', (int) $invoice_id)
                            ->get('tq_meta_ids')->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
        return $row ? $row : array();
    }

    /* =====================================================================
       الحدث
       ===================================================================== */

    /** معرف الحدث — مشتق من الفاتورة فيتفق عليه الطرفان بلا تفاهم. */
    public function event_id($invoice_id)
    {
        return 'tq-inv-' . (int) $invoice_id;
    }

    /**
     * يرسل `Purchase` عن فاتورة سددت. مأمون التكرار.
     *
     * @param int    $invoice_id
     * @param string $source return · webhook · cron · manual · free — للسجل
     */
    public function purchase($invoice_id, $source = 'system')
    {
        try {
            return $this->fire('Purchase', (int) $invoice_id, (string) $source);
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-META: تعذر إرسال شراء الفاتورة #'
                . (int) $invoice_id . ' — ' . $e->getMessage());
            return false;
        }
    }

    private function fire($name, $invoice_id, $source)
    {
        if (!$this->capi_ready()) return false;
        $this->ensure_schema();

        $inv = $this->db->where('id', $invoice_id)->get('invoices')->row_array();
        if (!$inv) return false;

        /* الباقة المجانية تصدر فاتورة بصفر وتسدد بـ`free`. وشراء بقيمة
           صفر ليس شراء: يلوث تعلم ميتا بمشترين لم يدفعوا شيئا، ويخفض
           «متوسط قيمة الشراء» الذي تبنى عليه المزايدة. */
        $halalas = (int) $inv['total'];
        if ($halalas <= 0) return false;

        /* والفاتورة تفحص وإن كان المستدعي `mark_invoice_paid()` نفسها.
           السبب `retry_failed()`: نداء ميتا يتعثر لحظة، ويعاد بعد ربع
           ساعة من الكرون — وقد تكون الفاتورة استردت في تلك الفجوة. فيبلغ
           عن شراء عكس، ولا شيء يعكسه عند ميتا. */
        if ((string) $inv['status'] !== 'paid') return false;

        $eid = $this->event_id($invoice_id);
        $ids = $this->ids_of($invoice_id);

        $base = array(
            'invoice_id' => $invoice_id,
            'user_id'    => (int) $inv['user_id'],
            'value'      => $halalas,
            'currency'   => $this->config()['currency'],
            'source'     => mb_substr($source, 0, 24),
        );

        /* الرفض أولا: صف `skipped` يقال ولا يسكت عنه — من يفتح السجل غدا
           ويجد شراء بلا حدث يحتاج أن يعرف أنه رفض لا عطل. */
        if ((string) (isset($ids['consent']) ? $ids['consent'] : '') === 'denied') {
            $this->mark($name, $eid, $base, 'skipped', 0, 'رفض الزائر ملفات الارتباط.');
            return false;
        }

        /* ميتا ترفض ما هو أقدم من سبعة أيام. وحوالة تراجع بعد أسبوعين
           تقع في ذلك — فتقيد `skipped` بسببها ولا يخترع لها تاريخ اليوم:
           شراء يؤرخ بغير وقته يفسد نافذة النسب التي تقاس بها الحملة. */
        $stamp = (string) ($inv['paid_at'] ? $inv['paid_at'] : $inv['issued_at']);
        $when  = $stamp !== '' ? (int) strtotime($stamp) : time();
        if ($when <= 0) $when = time();
        if (time() - $when > self::FRESH_SEC) {
            $this->mark($name, $eid, $base, 'skipped', 0, 'مضى على الدفعة أكثر من سبعة أيام.');
            return false;
        }

        $row_id = $this->claim($name, $eid, $base);
        if ($row_id <= 0) return false;             // أرسل من قبل، أو باب آخر يرسله الآن

        $r = $this->send($this->payload($name, $eid, $inv, $ids, $when, $halalas));

        $this->db->where('id', $row_id)->update('tq_meta_events', array(
            'status'    => $r['ok'] ? 'sent' : 'failed',
            'http_code' => (int) $r['code'],
            'note'      => mb_substr((string) $r['note'], 0, 255),
            'sent_at'   => $r['ok'] ? date('Y-m-d H:i:s') : null,
        ));

        if (!$r['ok']) {
            log_message('error', 'TQ-META: رفضت ميتا حدث ' . $eid . ' — ' . $r['note']);
        }
        return $r['ok'];
    }

    /**
     * حجز الصف قبل الإرسال — هو ما يمنع الشراء المكرر من داخلنا.
     *
     * يرد معرف الصف لمن يرسل، وصفرا لمن لا يرسل: أرسل من قبل، أو رفض،
     * أو باب آخر يرسله في هذه اللحظة. والمفتاح الفريد هو الحكم لا القراءة
     * التي تسبقه — بابان في الثانية نفسها يقرآن «لا صف» كلاهما.
     */
    private function claim($name, $eid, $base)
    {
        $now = date('Y-m-d H:i:s');
        try {
            $ex = $this->db->where('event_name', $name)->where('event_id', $eid)
                           ->get('tq_meta_events')->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return 0;
        }

        if ($ex) {
            if (in_array($ex['status'], array('sent', 'skipped'), true)) return 0;
            /* `sending` حديث يعني بابا آخر في الطريق، وقديم يعني طلبا مات
               في منتصفه — والثاني يعاد ولا ينسى. */
            if ($ex['status'] === 'sending' && strtotime((string) $ex['created_at']) > time() - 180) return 0;

            $this->db->where('id', (int) $ex['id'])->update('tq_meta_events', array(
                'status'     => 'sending',
                'tries'      => (int) $ex['tries'] + 1,
                'source'     => $base['source'],
                'created_at' => $now,
            ));
            return (int) $ex['id'];
        }

        try {
            $this->db->insert('tq_meta_events', array_merge($base, array(
                'event_name' => $name,
                'event_id'   => $eid,
                'status'     => 'sending',
                'tries'      => 1,
                'created_at' => $now,
            )));
        } catch (Throwable $e) {
            /* سباق على المفتاح الفريد: من خسره لم يخسر شيئا — الفائز يرسل. */
            $this->db->reset_query();
            return 0;
        }
        return (int) $this->db->insert_id();
    }

    /** يقيد صفا لا يرسل — الرفض والقدم يقالان ولا يبتلعان. */
    private function mark($name, $eid, $base, $status, $code, $note)
    {
        try {
            $ex = $this->db->where('event_name', $name)->where('event_id', $eid)
                           ->count_all_results('tq_meta_events');
            if ($ex) return;
            $this->db->insert('tq_meta_events', array_merge($base, array(
                'event_name' => $name,
                'event_id'   => $eid,
                'status'     => $status,
                'http_code'  => (int) $code,
                'note'       => mb_substr((string) $note, 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
            )));
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
    }

    /* =====================================================================
       الحمولة
       ===================================================================== */

    private function payload($name, $eid, $inv, $ids, $when, $halalas)
    {
        $c    = $this->config();
        $user = array();
        try {
            $row  = $this->db->select('first_name, last_name, email, phone')
                             ->where('id', (int) $inv['user_id'])
                             ->get('users')->row_array();
            $user = $row ? $row : array();
        } catch (Throwable $e) {
            $this->db->reset_query();
        }

        /* المطابقة المتقدمة: ميتا لا ترى شيئا من هذا نصا — كل حقل يمر
           بـsha256 بعد تسوية تكتبها هي (خفض الحرف، قص الفراغ، الجوال
           أرقاما بلا `+`). وتسوية مختلفة تعني هاشا لا يطابق أحدا، فيصل
           الحدث ولا ينسب إلى شخص. */
        $ud = array();
        $em = strtolower(trim((string) (isset($user['email']) ? $user['email'] : '')));
        if ($em !== '') $ud['em'] = array(hash('sha256', $em));

        $ph = preg_replace('/\D+/', '', (string) (isset($user['phone']) ? $user['phone'] : ''));
        if (strlen($ph) >= 8) $ud['ph'] = array(hash('sha256', $ph));

        $fn = mb_strtolower(trim((string) (isset($user['first_name']) ? $user['first_name'] : '')));
        if ($fn !== '') $ud['fn'] = array(hash('sha256', $fn));
        $ln = mb_strtolower(trim((string) (isset($user['last_name']) ? $user['last_name'] : '')));
        if ($ln !== '') $ud['ln'] = array(hash('sha256', $ln));

        if ((int) $inv['user_id'] > 0) {
            $ud['external_id'] = array(hash('sha256', 'tq-user-' . (int) $inv['user_id']));
        }

        if (!empty($ids['client_ip']))  $ud['client_ip_address'] = (string) $ids['client_ip'];
        if (!empty($ids['user_agent'])) $ud['client_user_agent'] = (string) $ids['user_agent'];
        if (!empty($ids['fbp']))        $ud['fbp'] = (string) $ids['fbp'];
        if (!empty($ids['fbc']))        $ud['fbc'] = (string) $ids['fbc'];

        $what = $this->sold_of($inv);
        $url  = (string) (!empty($ids['source_url']) ? $ids['source_url'] : site_url('plans'));

        return array(
            'event_name'       => $name,
            'event_time'       => (int) $when,
            'event_id'         => $eid,
            'action_source'    => 'website',
            'event_source_url' => $url,
            'user_data'        => $ud,
            'custom_data'      => array(
                'currency'         => $c['currency'],
                'value'            => round($halalas / 100, 2),
                'content_type'     => 'product',
                'content_ids'      => array($what['sku']),
                'content_name'     => $what['title'],
                'content_category' => $what['label'],
                'num_items'        => 1,
                'order_id'         => (string) $inv['invoice_no'],
            ),
        );
    }

    /**
     * ما الذي بيع بهذه الفاتورة — من `sold()` وحدها (TQ-SOLD-NAME).
     *
     * وفاتورة بـ`subscription_id = 0` حصة خاصة لا اشتراك، فلا تسأل عنها
     * طبقة الاشتراكات: كانت ترد «باقة #0» على كل حصة تباع.
     */
    private function sold_of($inv)
    {
        $sid = (int) $inv['subscription_id'];
        if ($sid <= 0) {
            return array('sku' => 'session', 'title' => 'حصة خاصة', 'label' => 'حصة');
        }
        try {
            $this->load->model('taqdar_billing_model');
            $sub  = $this->taqdar_billing_model->subscription($sid);
            $sold = $this->taqdar_billing_model->sold($sub);
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array('sku' => 'sub-' . $sid, 'title' => 'اشتراك', 'label' => 'اشتراك');
        }
        return array(
            'sku'   => $sold['kind'] . '-' . (int) $sold['id'],
            'title' => (string) $sold['title'],
            'label' => (string) $sold['label'],
        );
    }

    /* =====================================================================
       النداء
       ===================================================================== */

    /**
     * نداء ميتا — منفذ واحد لكل الطلبات.
     *
     * والمهلة خمس ثوان لا عشرون: هذا النداء يقع **داخل طلب عودة الطالب**
     * من صفحة الدفع، فبطء ميتا يصير انتظارا أمام من دفع للتو. وما يفشل
     * هنا يعاد من الكرون، فالتقصير لا يفقد حدثا.
     */
    private function send($payload)
    {
        $c    = $this->config();
        $body = array('data' => array($payload), 'access_token' => $c['token']);
        if ($c['test'] !== '') $body['test_event_code'] = $c['test'];

        $ch = curl_init(self::API . rawurlencode($c['pixel']) . '/events');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return array('ok' => false, 'code' => 0, 'note' => ($cerr ? $cerr : 'تعذر الاتصال بميتا.'));
        }

        $d = json_decode((string) $raw, true);
        if (!is_array($d)) $d = array();

        $got = (int) (isset($d['events_received']) ? $d['events_received'] : 0);
        if ($code >= 200 && $code < 300 && $got > 0) {
            return array('ok' => true, 'code' => $code, 'note' => 'استقبلت ' . $got . ' حدثا.');
        }

        $msg = (string) (isset($d['error']['message']) ? $d['error']['message'] : '');
        if ($msg === '') $msg = mb_substr((string) $raw, 0, 200);
        return array('ok' => false, 'code' => $code, 'note' => $msg);
    }

    /* =====================================================================
       المتصفح — الحدث نفسه بمعرفه نفسه
       ===================================================================== */

    /**
     * حمولة الحدث للمتصفح، أو `null` إن لم يكن ثمة ما يرسل.
     *
     * والمتصفح يرسل **ولو أرسل الخادم**: ميتا تفضل الحدثين معا وتطرح
     * المكرر بـ`event_id`، ولحدث المتصفح ما ليس للخادم — كعكة الطرف
     * الأول التي تربطه بجلسة التصفح كلها. فالاثنان أدق من أحدهما.
     */
    public function browser_purchase($invoice_id)
    {
        $c = $this->config();
        if ($c['pixel'] === '') return null;

        try {
            $inv = $this->db->where('id', (int) $invoice_id)->get('invoices')->row_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return null;
        }
        if (!$inv || (int) $inv['total'] <= 0) return null;

        $what = $this->sold_of($inv);

        return array(
            'name'   => 'Purchase',
            'id'     => $this->event_id((int) $invoice_id),
            'params' => array(
                'currency'     => $c['currency'],
                'value'        => round(((int) $inv['total']) / 100, 2),
                'content_type' => 'product',
                'content_ids'  => array($what['sku']),
                'content_name' => $what['title'],
                'num_items'    => 1,
                'order_id'     => (string) $inv['invoice_no'],
            ),
        );
    }

    /* =====================================================================
       الكرون واللوحة
       ===================================================================== */

    /**
     * يعيد ما فشل — شبكة الأمان تحت النداء الحي.
     *
     * ميتا تسقط أحيانا لثوان، والنداء وقتها يقع في `failed`. وبلا إعادة
     * يضيع الشراء كله من القياس لأن الشبكة تعثرت لحظة — وهو أثقل من
     * ألا يرسل أصلا، لأنه يقع في المبيعات لا في السكون.
     */
    public function retry_failed($limit = 25)
    {
        if (!$this->capi_ready()) return array('tried' => 0, 'sent' => 0);
        $this->ensure_schema();

        try {
            $rows = $this->db->where('status', 'failed')->where('tries <', 5)
                             ->where('created_at >', date('Y-m-d H:i:s', time() - self::FRESH_SEC))
                             ->order_by('id', 'ASC')->limit((int) $limit)
                             ->get('tq_meta_events')->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array('tried' => 0, 'sent' => 0);
        }

        $sent = 0;
        foreach ($rows as $r) {
            /* الصف يقدم تاريخه قبل الإعادة كي لا يمنعه حجزه هو: `claim()`
               ترد صفرا على `sending` عمره أقل من ثلاث دقائق. */
            $this->db->where('id', (int) $r['id'])->update('tq_meta_events',
                array('created_at' => date('Y-m-d H:i:s', time() - 3600)));
            if ($this->purchase((int) $r['invoice_id'], 'cron')) $sent++;
        }
        return array('tried' => count($rows), 'sent' => $sent);
    }

    /** آخر ما أرسل — للوحة: «هل وصل الشراء؟» يجاب من صف لا من ظن. */
    public function recent($limit = 25)
    {
        $this->ensure_schema();
        try {
            return $this->db->select('e.*, i.invoice_no,'
                    . ' TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) AS user_name', false)
                ->from('tq_meta_events e')
                ->join('invoices i', 'i.id = e.invoice_id', 'left')
                ->join('users u', 'u.id = e.user_id', 'left')
                ->order_by('e.id', 'DESC')->limit((int) $limit)
                ->get()->result_array();
        } catch (Throwable $e) {
            $this->db->reset_query();
            return array();
        }
    }

    /** عدد الأحداث بكل حال — سطر الحال أعلى الشاشة. */
    public function totals()
    {
        $this->ensure_schema();
        $out = array('sent' => 0, 'failed' => 0, 'skipped' => 0, 'sending' => 0);
        try {
            $rows = $this->db->select('status, COUNT(*) AS n', false)
                             ->group_by('status')->get('tq_meta_events')->result_array();
            foreach ($rows as $r) {
                if (isset($out[$r['status']])) $out[$r['status']] = (int) $r['n'];
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
        return $out;
    }

    /**
     * يسأل ميتا عن البكسل نفسه — تحقق بلا حدث مخترع.
     *
     * وهذا هو الفحص الصحيح: إرسال حدث تجريبي يلوث الحساب بشراء لم يقع،
     * وقراءة اسم البكسل بالرمز تثبت الاثنين معا — أن الرمز صحيح، وأنه
     * يملك هذا البكسل بعينه. وهو الخطأ الأكثر وقوعا: رمز صحيح لحساب آخر.
     */
    public function probe()
    {
        $c = $this->config();
        if ($c['pixel'] === '') return array('ok' => false, 'msg' => 'لا معرف بكسل محفوظ.');
        if ($c['token'] === '') return array('ok' => false, 'msg' => 'لا رمز وصول محفوظ، فلا يرسل الخادم شيئا.');

        $url = self::API . rawurlencode($c['pixel']) . '?fields=name,id&access_token=' . rawurlencode($c['token']);
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) return array('ok' => false, 'msg' => ($cerr ? $cerr : 'تعذر الاتصال بميتا.'));

        $d = json_decode((string) $raw, true);
        if (!is_array($d)) $d = array();

        if ($code >= 200 && $code < 300 && !empty($d['id'])) {
            $nm = (string) (!empty($d['name']) ? $d['name'] : $d['id']);
            return array('ok' => true, 'msg' => 'الرمز صحيح ويملك البكسل «' . $nm . '».');
        }
        $err = (string) (isset($d['error']['message']) ? $d['error']['message'] : '');
        return array('ok' => false, 'msg' => ($err !== '' ? $err : ('رد ميتا برمز ' . $code)));
    }
}
