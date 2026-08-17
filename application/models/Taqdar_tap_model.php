<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * بوابة تاب — الدفع بالبطاقة (مدى · فيزا · ماستركارد · Apple Pay).
 *
 * ثلاث قواعد بنيت عليها هذه الطبقة كلها، وكل واحدة منها تسد بابا:
 *
 * ١ — **المفاتيح في القاعدة لا في الشيفرة.** المستودع عام، والنشر
 *     `git reset --hard`. فالمفاتيح تحرر من `taqdar_admin/tap` وتخزن في
 *     جدول `settings` كما تخزن بيانات التحويل البنكي — ولا سطر واحد
 *     منها في ملف يرفع.
 *
 * ٢ — **لا يصدق ما يرد من المتصفح ولا من الويبهوك.** عودة الطالب من تاب
 *     تحمل `tap_id` وحده، والويبهوك يحمل جسما كاملا يمكن أن يصنع بيد.
 *     فكلاهما لا يفعل شيئا إلا **مفتاحا لجلب الدفعة من تاب نفسها**
 *     (`GET /charges/{id}` بالمفتاح السري)، والقرار على ما ترده تاب
 *     وحدها. وبهذا يصير طلب مصنوع بلا أثر: من يخترع `tap_id` تجيب عنه
 *     تاب بـ404، ومن ينسخ `tap_id` دفعة صحيحة يفعل ما فعلته الدفعة
 *     أصلا ولا شيء زائد.
 *
 * ٣ — **المبلغ يقابل الفاتورة قبل التفعيل.** لكل محاولة صف في
 *     `payment_attempts` فيه قيمة الفاتورة بالهللات وقت البدء، وما
 *     ترده تاب يقابل هذا الصف. فلا تفتح باقة بستمئة وتسعين ريالا
 *     دفعة قيمتها ريال واحد.
 *
 * وحين لا تضبط المفاتيح: `ready()` كاذبة، فلا يعرض للطالب خيار البطاقة
 * أصلا ويبقى التحويل البنكي وحده كما كان قبل هذا الملف حرفا بحرف.
 */
class Taqdar_tap_model extends CI_Model
{
    /** جذر واجهة تاب. واحد للاختبار والإنتاج — المفتاح هو ما يفرق. */
    const API = 'https://api.tap.company/v2/';

    /** مفاتيح الإعدادات — كلها في `settings` بالبادئة `tq_tap_`. */
    public static $KEYS = array(
        'tq_tap_enabled',
        'tq_tap_mode',
        'tq_tap_test_secret',
        'tq_tap_test_public',
        'tq_tap_live_secret',
        'tq_tap_live_public',
        'tq_tap_merchant',
    );

    /** حالات تاب التي تعني «حصل المال». وما عداها لا يفعل اشتراكا. */
    private static $PAID = array('CAPTURED');

    /** حالات تاب التي تعني «لم ينته بعد» — تعاد قراءتها لا تحسب فشلا. */
    private static $OPEN = array('INITIATED', 'IN_PROGRESS');

    private $cfg = null;
    private $schema_checked = false;

    /* =====================================================================
       الإعدادات
       ===================================================================== */

    /**
     * إعدادات البوابة — بضمة واحدة لا سبع.
     *
     * `get_settings()` استعلام لكل مفتاح، وهذه الدالة تنادى في كل صفحة
     * دفع وفي كل ويبهوك. فتقرأ السبعة معا وتحفظ في الذاكرة للطلب.
     */
    public function config()
    {
        if ($this->cfg !== null) return $this->cfg;

        $vals = array();
        try {
            $rows = $this->db->select('key, value')
                             ->where_in('key', self::$KEYS)
                             ->get('settings')->result_array();
            foreach ($rows as $r) $vals[$r['key']] = (string) $r['value'];
        } catch (Throwable $e) {
            log_message('error', 'TQ-TAP: تعذر قراءة الإعدادات — ' . $e->getMessage());
        }

        $mode = (isset($vals['tq_tap_mode']) && $vals['tq_tap_mode'] === 'live') ? 'live' : 'test';

        /* عملة النظام هي عملة الدفع: عملة أخرى تعني أن ما يخصم يخالف ما
           عرض على الطالب، وهو خلل لا يظهر إلا في كشف البنك. */
        $currency = strtoupper(trim((string) get_settings('system_currency')));
        if ($currency === '') $currency = 'SAR';

        $this->cfg = array(
            'enabled'  => isset($vals['tq_tap_enabled']) && $vals['tq_tap_enabled'] === '1',
            'mode'     => $mode,
            'secret'   => trim((string) ($vals['tq_tap_' . $mode . '_secret'] ?? '')),
            'public'   => trim((string) ($vals['tq_tap_' . $mode . '_public'] ?? '')),
            'merchant' => trim((string) ($vals['tq_tap_merchant'] ?? '')),
            'currency' => $currency,
            'keys'     => array(
                'test_secret' => trim((string) ($vals['tq_tap_test_secret'] ?? '')),
                'test_public' => trim((string) ($vals['tq_tap_test_public'] ?? '')),
                'live_secret' => trim((string) ($vals['tq_tap_live_secret'] ?? '')),
                'live_public' => trim((string) ($vals['tq_tap_live_public'] ?? '')),
            ),
        );
        return $this->cfg;
    }

    /**
     * هل الدفع بالبطاقة معروض للطالب الآن؟
     *
     * شرطان: مفعلة، ولها مفتاح سري في الوضع الجاري. والثاني ليس زينة —
     * بوابة مفعلة بلا مفتاح تعرض للطالب زرا ينتهي إلى خطأ، وهو أسوأ من
     * ألا يعرض الزر.
     */
    public function ready()
    {
        $c = $this->config();
        return $c['enabled'] && $c['secret'] !== '';
    }

    public function mode()     { $c = $this->config(); return $c['mode']; }
    public function is_live()  { return $this->mode() === 'live'; }

    /** وضع الاختبار مفعل: الدفع ينجح ظاهريا ولا يصل مال. */
    public function is_test_ready()
    {
        $c = $this->config();
        return $c['enabled'] && $c['secret'] !== '' && $c['mode'] === 'test';
    }

    /* =====================================================================
       سجل المحاولات
       ===================================================================== */

    /**
     * جدول المحاولات — ينشأ وقت التشغيل لا بهجرة، كما في
     * `Taqdar_content_model::ensure_schema()`.
     *
     * وهو ما يربط `tap_id` العائد من البوابة بفاتورة عندنا: بدونه لا
     * يعرف من عاد بأي شيء دفع، ولا يقابل مبلغ بمبلغ.
     */
    public function ensure_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `payment_attempts` (
                    `id`              int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `gateway`         varchar(24)  NOT NULL DEFAULT "tap",
                    `mode`            varchar(8)   NOT NULL DEFAULT "test",
                    `charge_id`       varchar(64)  DEFAULT NULL,
                    `invoice_id`      int(10) unsigned NOT NULL DEFAULT 0,
                    `subscription_id` int(10) unsigned NOT NULL DEFAULT 0,
                    `user_id`         int(10) unsigned NOT NULL DEFAULT 0,
                    `amount`          bigint(20)   NOT NULL DEFAULT 0,
                    `currency`        varchar(8)   NOT NULL DEFAULT "SAR",
                    `status`          varchar(16)  NOT NULL DEFAULT "initiated",
                    `gateway_status`  varchar(32)  DEFAULT NULL,
                    `message`         varchar(255) DEFAULT NULL,
                    `raw`             text         DEFAULT NULL,
                    `created_at`      datetime     DEFAULT NULL,
                    `updated_at`      datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_charge` (`charge_id`),
                    KEY `k_invoice` (`invoice_id`),
                    KEY `k_status` (`status`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-TAP: تعذر إنشاء payment_attempts — ' . $e->getMessage());
        }
    }

    /** آخر المحاولات — للوحة: «هل وصل الدفع؟» يجاب من صف لا من ظن. */
    public function attempts($limit = 40)
    {
        $this->ensure_schema();
        try {
            return $this->db->select('a.*, i.invoice_no,'
                    . ' TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) AS user_name', false)
                ->from('payment_attempts a')
                ->join('invoices i', 'i.id = a.invoice_id', 'left')
                ->join('users u', 'u.id = a.user_id', 'left')
                ->order_by('a.id', 'DESC')->limit((int) $limit)
                ->get()->result_array();
        } catch (Throwable $e) {
            return array();
        }
    }

    /** عدد المحاولات بكل حالة — سطر الحال في أعلى شاشة البوابة. */
    public function attempt_totals()
    {
        $this->ensure_schema();
        $out = array('initiated' => 0, 'paid' => 0, 'failed' => 0, 'mismatch' => 0, 'sum_paid' => 0);
        try {
            $rows = $this->db->select('status, COUNT(*) AS n, SUM(amount) AS s', false)
                             ->group_by('status')->get('payment_attempts')->result_array();
            foreach ($rows as $r) {
                $out[$r['status']] = (int) $r['n'];
                if ($r['status'] === 'paid') $out['sum_paid'] = (int) $r['s'];
            }
        } catch (Throwable $e) {
        }
        return $out;
    }

    private function touch($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        try {
            $this->db->where('id', (int) $id)->update('payment_attempts', $data);
        } catch (Throwable $e) {
            log_message('error', 'TQ-TAP: تعذر تحديث المحاولة #' . (int) $id . ' — ' . $e->getMessage());
        }
    }

    /* =====================================================================
       بدء الدفع
       ===================================================================== */

    /**
     * ينشئ دفعة عند تاب لفاتورة قائمة، ويرد رابط صفحة الدفع.
     *
     * الفاتورة هي المرساة لا الباقة: الاشتراك والفاتورة يصدران أولا
     * (`Taqdar_billing_model::subscribe()`) ثم يدفع ما صدر. وبهذا يعمل
     * المسار نفسه لباقة ولمسار ولفاتورة قديمة يعود صاحبها ليدفعها
     * بالبطاقة بعد أن اختار التحويل — بلا فرع ثان.
     *
     * @param int $invoice_id
     * @param int $user_id لو أرسل: تفحص الملكية. صفر للنداء الإداري.
     * @return array ok · url · errors · attempt_id
     */
    public function start($invoice_id, $user_id = 0)
    {
        $this->ensure_schema();

        if (!$this->ready()) {
            return $this->fail('الدفع بالبطاقة غير مفعل حاليا.');
        }

        $inv = $this->db->where('id', (int) $invoice_id)->get('invoices')->row_array();
        if (!$inv) return $this->fail('الفاتورة غير موجودة.');

        /* الملكية تفحص هنا لا في المتحكم وحده: هذه الدالة تنادى من أكثر
           من باب، ورقم فاتورة مخمن يصير صفحة دفع لفاتورة غيرك.

           TQ-PARENT-PAYS — وولي الأمر يدفع عن ابنه (`B4.6`).
           الفاتورة باسم الابن دائما — هو صاحب الاشتراك والمحتوى — فلو
           بقي الشرط على المطابقة وحدها لرد ولي الأمر «هذه الفاتورة ليست
           لك» وهو يدفعها فعلا. والاستثناء يشترط **رابطا نشطا**: لا
           `pending` ولا `revoked`، وهو الشرط نفسه الذي تقرأ به بقية
           بوابة ولي الأمر — فلا يفتح هذا الباب ما أغلقه ذاك.

           ولا يوسع هذا قراءته: يدفع ولا يقرأ. */
        if ((int) $user_id > 0 && (int) $inv['user_id'] !== (int) $user_id) {
            $linked = (int) $this->db->where('parent_user_id', (int) $user_id)
                                     ->where('student_id', (int) $inv['user_id'])
                                     ->where('status', 'active')
                                     ->count_all_results('parent_links');
            if (!$linked) {
                return $this->fail('هذه الفاتورة ليست لك.');
            }
        }
        if ($inv['status'] === 'paid')     return $this->fail('هذه الفاتورة مدفوعة بالفعل.');
        if ($inv['status'] === 'refunded') return $this->fail('هذه الفاتورة مستردة، فلا تدفع.');
        if ((int) $inv['total'] <= 0)      return $this->fail('قيمة الفاتورة صفر، فلا شيء يدفع.');

        $c        = $this->config();
        $halalas  = (int) $inv['total'];
        $user     = $this->db->select('first_name, last_name, email')
                             ->where('id', (int) $inv['user_id'])->get('users')->row_array();

        /* تاب تشترط اسما وبريدا للعميل، وترد 400 على جسم ناقص. والاسم
           الفارغ يقبل بديلا معقولا؛ البريد الفارغ لا بديل له فيرفض هنا
           برسالة تقرأ، لا برسالة بوابة بالإنجليزية. */
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('لا بريد صحيح في حسابك، والبوابة تشترطه. حدث بريدك من الإعدادات ثم أعد المحاولة.');
        }
        $first = trim((string) ($user['first_name'] ?? '')) ?: 'طالب';
        $last  = trim((string) ($user['last_name'] ?? ''));

        /* صف المحاولة **قبل** النداء: لو نجح النداء وسقط الاتصال قبل أن
           نكتب، عاد الطالب بـ`tap_id` لا يقابل شيئا عندنا. فيكتب أولا
           ويحدث بمعرف الدفعة بعد. */
        $this->db->insert('payment_attempts', array(
            'gateway'         => 'tap',
            'mode'            => $c['mode'],
            'invoice_id'      => (int) $inv['id'],
            'subscription_id' => (int) $inv['subscription_id'],
            'user_id'         => (int) $inv['user_id'],
            'amount'          => $halalas,
            'currency'        => $c['currency'],
            'status'          => 'initiated',
            'created_at'      => date('Y-m-d H:i:s'),
        ));
        $aid = (int) $this->db->insert_id();

        $body = array(
            'amount'       => round($halalas / 100, 2),
            'currency'     => $c['currency'],
            'threeDSecure' => true,
            'save_card'    => false,
            'description'  => 'اشتراك منصة تقدر — فاتورة ' . $inv['invoice_no'],
            'reference'    => array(
                'transaction' => 'tq-' . $aid . '-' . time(),
                'order'       => (string) $inv['invoice_no'],
            ),
            'receipt'      => array('email' => true, 'sms' => false),
            'customer'     => array(
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => $email,
            ),
            'source'       => array('id' => 'src_all'),
            'redirect'     => array('url' => site_url('payment/tap/return')),
            'metadata'     => array(
                'attempt_id'      => (string) $aid,
                'invoice_id'      => (string) $inv['id'],
                'invoice_no'      => (string) $inv['invoice_no'],
                'subscription_id' => (string) $inv['subscription_id'],
            ),
        );
        if ($last === '') unset($body['customer']['last_name']);
        if ($c['merchant'] !== '') $body['merchant'] = array('id' => $c['merchant']);

        /* الويبهوك يرسل عنوانه فقط إن كان عنوانا تصل إليه تاب. وعلى
           `localhost` لا تصل، فيسقط الحقل ويبقى التحقق على العودة —
           وهو تحقق كامل لأنه يجلب الدفعة من تاب لا يقرأ الطلب. */
        $hook = $this->webhook_url();
        if ($hook) $body['post'] = array('url' => $hook);

        $r = $this->api('POST', 'charges', $body);

        if (!$r['ok']) {
            $this->touch($aid, array(
                'status'  => 'failed',
                'message' => mb_substr($r['error'], 0, 255),
                'raw'     => $r['body'],
            ));
            log_message('error', 'TQ-TAP: تعذر إنشاء دفعة للفاتورة #' . (int) $inv['id'] . ' — ' . $r['error']);
            return $this->fail('تعذر بدء الدفع بالبطاقة: ' . $r['error']);
        }

        $data = $r['data'];
        $cid  = trim((string) ($data['id'] ?? ''));
        $url  = trim((string) ($data['transaction']['url'] ?? ''));

        if ($cid === '' || $url === '') {
            $this->touch($aid, array('status' => 'failed',
                'message' => 'رد البوابة بلا معرف دفعة أو بلا رابط.', 'raw' => $r['body']));
            return $this->fail('رد البوابة غير مفهوم. حاول مرة أخرى أو استعمل التحويل البنكي.');
        }

        $this->touch($aid, array(
            'charge_id'      => $cid,
            'gateway_status' => strtoupper((string) ($data['status'] ?? '')),
            'raw'            => $r['body'],
        ));

        /* وسيلة الفاتورة تكتب `tap` من الآن: من يقرأ الفواتير في اللوحة
           يرى كيف يدفع هذا الطالب قبل أن يدفع لا بعده. */
        $this->db->where('id', (int) $inv['id'])->update('invoices', array('method' => 'tap'));
        if ((int) $inv['subscription_id'] > 0) {
            $this->db->where('id', (int) $inv['subscription_id'])
                     ->where('status', 'pending')
                     ->update('subscriptions', array('method' => 'tap'));
        }

        return array('ok' => true, 'url' => $url, 'charge_id' => $cid, 'attempt_id' => $aid, 'errors' => array());
    }

    /* =====================================================================
       تسوية الدفعة
       ===================================================================== */

    /**
     * يقرر مصير محاولة من **رد تاب** لا من الطلب الوارد.
     *
     * تنادى من ثلاثة أبواب: عودة الطالب، والويبهوك، ومزامنة الكرون. وكلها
     * لا تمرر إلا معرف الدفعة، والباقي يجلب.
     *
     * @param string $charge_id
     * @param string $source return · webhook · cron · admin — للسجل
     * @return array ok · state · message · subscription_id · invoice_id
     */
    public function settle($charge_id, $source = 'return')
    {
        $this->ensure_schema();
        $charge_id = trim((string) $charge_id);
        if ($charge_id === '') return $this->fail('لا معرف دفعة في الطلب.');

        $att = $this->db->where('charge_id', $charge_id)->get('payment_attempts')->row_array();
        if (!$att) {
            /* دفعة لا يقابلها صف: إما `tap_id` مخترع، وإما محاولة أنشأتها
               نسخة أخرى من الموقع بالمفاتيح نفسها. وفي الحالتين لا تفعل
               شيئا وتسجل — التفعيل بلا مرجع عندنا تخمين. */
            log_message('error', 'TQ-TAP: دفعة بلا محاولة مسجلة (' . $source . ') — ' . $charge_id);
            return $this->fail('هذه الدفعة لا تقابل محاولة مسجلة عندنا.');
        }

        if ($att['status'] === 'paid') {
            return array('ok' => true, 'already' => true, 'state' => 'paid',
                'message' => 'هذه الدفعة سويت من قبل.',
                'subscription_id' => (int) $att['subscription_id'],
                'invoice_id' => (int) $att['invoice_id'], 'errors' => array());
        }

        $r = $this->api('GET', 'charges/' . rawurlencode($charge_id));
        if (!$r['ok']) {
            log_message('error', 'TQ-TAP: تعذر جلب الدفعة (' . $source . ') ' . $charge_id . ' — ' . $r['error']);
            return $this->fail('تعذر التحقق من الدفعة عند البوابة: ' . $r['error']);
        }

        $c        = $r['data'];
        $gstatus  = strtoupper(trim((string) ($c['status'] ?? '')));
        $paid     = (int) round(((float) ($c['amount'] ?? 0)) * 100);
        $currency = strtoupper(trim((string) ($c['currency'] ?? '')));

        if (!in_array($gstatus, self::$PAID, true)) {
            $open = in_array($gstatus, self::$OPEN, true);
            $this->touch($att['id'], array(
                'status'         => $open ? 'initiated' : 'failed',
                'gateway_status' => $gstatus,
                'message'        => $open ? 'لم تكتمل بعد.' : 'لم تنجح الدفعة عند البوابة.',
                'raw'            => $r['body'],
            ));
            return array('ok' => false, 'state' => $open ? 'open' : 'failed',
                'gateway_status' => $gstatus,
                'errors' => array($open
                    ? 'لم تكتمل الدفعة بعد. أكمل الدفع أو أعد المحاولة.'
                    : 'لم تنجح الدفعة. لم يخصم منك شيء، ويمكنك المحاولة مرة أخرى أو التحويل البنكي.'),
                'subscription_id' => (int) $att['subscription_id'],
                'invoice_id' => (int) $att['invoice_id']);
        }

        /* المبلغ يقابل المحاولة لا الرد نفسه: هذا هو الفحص الذي يمنع أن
           تفعل باقة بستمئة وتسعين ريالا دفعة بريال. والعملة معه —
           ستمئة روبية ليست ستمئة ريال. */
        if ($paid !== (int) $att['amount']
            || ($att['currency'] !== '' && $currency !== strtoupper($att['currency']))) {
            $this->touch($att['id'], array(
                'status'         => 'mismatch',
                'gateway_status' => $gstatus,
                'message'        => 'المحصل ' . $paid . ' ' . $currency
                                  . ' والمنتظر ' . (int) $att['amount'] . ' ' . $att['currency'],
                'raw'            => $r['body'],
            ));
            log_message('error', 'TQ-TAP: مبلغ لا يطابق — دفعة ' . $charge_id
                . ' محصل ' . $paid . $currency . ' منتظر ' . (int) $att['amount'] . $att['currency']);
            return $this->fail('المبلغ المحصل لا يطابق قيمة الفاتورة. راجعنا ولا تعد الدفع.');
        }

        $this->load->model('taqdar_billing_model');
        $ok = $this->taqdar_billing_model->activate_from_gateway(
            (int) $att['subscription_id'], 'tap', $charge_id, (int) $att['invoice_id']);

        $this->touch($att['id'], array(
            'status'         => 'paid',
            'gateway_status' => $gstatus,
            'message'        => $ok ? 'فعل الاشتراك.' : 'حصل المال ولم يفعل الاشتراك — يراجع بيد.',
            'raw'            => $r['body'],
        ));

        if (!$ok) {
            /* حالة تستحق أن تصرخ: المال حصل والاشتراك لم يفتح. تسجل
               بصراحة ليفعله المسؤول من شاشة الاشتراكات، ولا يقال للطالب
               «فشل الدفع» وقد خصم منه. */
            log_message('error', 'TQ-TAP-STUCK: حصلت الدفعة ' . $charge_id
                . ' ولم يفعل الاشتراك #' . (int) $att['subscription_id']);
            return array('ok' => false, 'state' => 'stuck',
                'errors' => array('وصل الدفع وسجل، ويفعل اشتراكك بعد مراجعة سريعة. لا تعد الدفع.'),
                'subscription_id' => (int) $att['subscription_id'],
                'invoice_id' => (int) $att['invoice_id']);
        }

        return array('ok' => true, 'state' => 'paid', 'message' => 'نجح الدفع وفعل اشتراكك.',
            'subscription_id' => (int) $att['subscription_id'],
            'invoice_id' => (int) $att['invoice_id'], 'errors' => array());
    }

    /**
     * يعيد قراءة المحاولات المعلقة من تاب — للكرون.
     *
     * الويبهوك يسد الحالة التي يغلق فيها الطالب المتصفح بعد الدفع وقبل
     * العودة. وهذه تسد ما لو لم يصل الويبهوك أصلا: يقرأ ما بقي «معلقا»
     * بعد مهلة ويسأل تاب عنه. فلا تبقى دفعة حصلت بلا اشتراك.
     */
    public function reconcile($older_than_minutes = 15, $limit = 60)
    {
        $this->ensure_schema();
        if (!$this->ready()) return array('checked' => 0, 'settled' => 0);

        $cut  = date('Y-m-d H:i:s', time() - max(1, (int) $older_than_minutes) * 60);
        $rows = $this->db->where('status', 'initiated')
                         ->where('charge_id IS NOT NULL', null, false)
                         ->where('created_at <', $cut)
                         ->order_by('id', 'ASC')->limit((int) $limit)
                         ->get('payment_attempts')->result_array();

        $settled = 0;
        foreach ($rows as $a) {
            $r = $this->settle($a['charge_id'], 'cron');
            if (!empty($r['ok'])) $settled++;
        }
        return array('checked' => count($rows), 'settled' => $settled);
    }

    /* =====================================================================
       التحقق من المفاتيح
       ===================================================================== */

    /**
     * يسأل تاب: هل هذا المفتاح مقبول؟
     *
     * لا نقطة «تحقق» في واجهة تاب، فيجلب معرف دفعة لا وجود له: المفتاح
     * الخطأ يرد 401، والصحيح يرد 404 على المعرف. أي أن **404 نجاح** هنا،
     * وهو ما يفرق «مفتاح مرفوض» عن «مفتاح مقبول ومعرف مخترع».
     *
     * @param string $mode test · live — أو فارغ للوضع الجاري
     */
    public function probe($mode = '')
    {
        $c   = $this->config();
        $m   = ($mode === 'test' || $mode === 'live') ? $mode : $c['mode'];
        $key = $c['keys'][$m . '_secret'];

        if ($key === '') {
            return array('ok' => false, 'mode' => $m,
                'message' => 'لا مفتاح سري محفوظ لوضع ' . ($m === 'live' ? 'الإنتاج' : 'الاختبار') . '.');
        }

        /* المفتاح يفحص بحرفه أيضا قبل الرحلة: `sk_test_` في خانة الإنتاج
           خطأ يقع فعلا عند اللصق، ونتيجته «الدفع ينجح ولا يصل مال». */
        $want = 'sk_' . $m . '_';
        if (strpos($key, $want) !== 0) {
            return array('ok' => false, 'mode' => $m,
                'message' => 'المفتاح المحفوظ لا يبدأ بـ' . $want . ' — راجع أنك لصقت مفتاح الوضع الصحيح.');
        }

        $r = $this->api('GET', 'charges/chg_tq_probe_' . substr(md5((string) time()), 0, 10), null, $key);

        if ($r['code'] === 401 || $r['code'] === 403) {
            return array('ok' => false, 'mode' => $m, 'code' => $r['code'],
                'message' => 'رفضت تاب المفتاح. انسخه من لوحة تاب لهذا الوضع مرة أخرى.');
        }
        if ($r['code'] === 0) {
            return array('ok' => false, 'mode' => $m, 'code' => 0,
                'message' => 'تعذر الوصول إلى تاب من هذا الخادم: ' . $r['error']);
        }
        return array('ok' => true, 'mode' => $m, 'code' => $r['code'],
            'message' => 'قبلت تاب المفتاح (رد ' . $r['code'] . ' على معرف دفعة غير موجود، وهو المنتظر).');
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    /**
     * توقيع الويبهوك — يفحص ويسجل، ولا يقرر.
     *
     * القرار كله على `GET /charges/{id}`، فلا حاجة إلى التوقيع لصحة
     * المنطق. ويفحص مع ذلك: اختلافه يعني أن جسما وصل من غير تاب — خبر
     * يستحق أن يقرأ في السجل لا أن يمر صامتا.
     */
    public function hash_ok($charge, $header)
    {
        $header = trim((string) $header);
        if ($header === '') return null;      // لا ترويسة: لا حكم

        $c = $this->config();
        if ($c['secret'] === '') return null;

        $s = 'x_id' . (string) ($charge['id'] ?? '')
           . 'x_amount' . number_format((float) ($charge['amount'] ?? 0), 2, '.', '')
           . 'x_currency' . (string) ($charge['currency'] ?? '')
           . 'x_gateway_reference' . (string) ($charge['reference']['gateway'] ?? '')
           . 'x_payment_reference' . (string) ($charge['reference']['payment'] ?? '')
           . 'x_status' . (string) ($charge['status'] ?? '')
           . 'x_created' . (string) ($charge['transaction']['created'] ?? '');

        return hash_equals(hash_hmac('sha256', $s, $c['secret']), $header);
    }

    /**
     * عنوان الويبهوك — أو `null` إن كان عنوانا لا تصل إليه تاب.
     *
     * إرسال `http://localhost/...` إلى بوابة يعني أن كل دفعة تنتظر نداء
     * لا يجيء. فيسقط الحقل صراحة في التطوير بدل أن يرسل وعدا كاذبا.
     */
    private function webhook_url()
    {
        $url  = site_url('payment/tap/webhook');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (strpos($url, 'https://') !== 0) return null;
        if ($host === 'localhost' || $host === '127.0.0.1' || substr($host, -6) === '.local') return null;
        if (strpos($host, '.') === false) return null;

        return $url;
    }

    /**
     * نداء واجهة تاب — منفذ واحد لكل الطلبات.
     *
     * `ok` تعني «رد بنجاح وبجسم مفهوم» لا «وصل الطلب». والمهلة عشرون
     * ثانية: صفحة دفع تنتظر دقيقة تفقد المشتري.
     */
    private function api($method, $path, $body = null, $key = null)
    {
        $secret = $key !== null ? $key : $this->config()['secret'];
        if ($secret === '') {
            return array('ok' => false, 'code' => 0, 'error' => 'لا مفتاح سري محفوظ.', 'body' => '', 'data' => array());
        }

        $ch = curl_init(self::API . $path);
        $headers = array(
            'Authorization: Bearer ' . $secret,
            'Accept: application/json',
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return array('ok' => false, 'code' => 0, 'error' => ($cerr ?: 'تعذر الاتصال بالبوابة.'),
                         'body' => '', 'data' => array());
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) $data = array();

        if ($code < 200 || $code >= 300) {
            return array('ok' => false, 'code' => $code, 'error' => $this->api_error($data, $code),
                         'body' => (string) $raw, 'data' => $data);
        }

        return array('ok' => true, 'code' => $code, 'error' => '', 'body' => (string) $raw, 'data' => $data);
    }

    /** رسالة خطأ تاب بصيغة تقرأ — لا `Array` ولا رمز عار. */
    private function api_error($data, $code)
    {
        if (!empty($data['errors']) && is_array($data['errors'])) {
            $parts = array();
            foreach ($data['errors'] as $e) {
                $parts[] = trim((string) ($e['description'] ?? ($e['code'] ?? '')));
            }
            $msg = trim(implode(' · ', array_filter($parts)));
            if ($msg !== '') return $msg . ' (' . $code . ')';
        }
        if (!empty($data['message'])) return (string) $data['message'] . ' (' . $code . ')';
        return 'رد البوابة برمز ' . $code . '.';
    }

    private function fail($message)
    {
        return array('ok' => false, 'state' => 'error', 'errors' => array($message), 'message' => $message);
    }
}
