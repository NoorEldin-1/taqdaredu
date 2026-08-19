<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * رموز التحقق — إصدارها وإرسالها والحكم عليها.
 *
 * ولم تكن في المنصة رموز تحقق أصلا. الموروث من Academy شيء آخر يشبهها:
 * `users.verification_code` عمود `longtext` يكتب فيه `rand(100000, 200000)`
 * **ويبقى فيه**. وفيه أربعة عيوب لا واحد:
 *
 *   ١ — `rand()` لا `random_int()`: مولد لا يصلح لسر، ومداه هنا مئة ألف
 *       قيمة لا تسعمئة ألف — لأن `200000` حد أعلى لا رقم من ست خانات.
 *   ٢ — يخزن **كما هو**. من قرأ الجدول قرأ رموز الناس.
 *   ٣ — **لا ينتهي أبدا**. رمز أرسل في يناير يقبل في ديسمبر.
 *   ٤ — لا يعد المحاولات. ست خانات تخمن في ساعة بلا عداد يوقفها.
 *
 * فهذا الملف يبني الرموز من جديد: `random_int` على ستمئة ألف قيمة،
 * ومخزنة مهشومة (`password_hash`)، وتموت بعد عشر دقائق، وبعد خمس
 * محاولات، وبعد أول استعمال. والصف يكتب في `tq_otp` — لا في `users`،
 * فحقل التحقق ليس صفة من صفات الحساب بل حدث له عمر.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * أين يذهب الرمز؟
 * ═══════════════════════════════════════════════════════════════════════
 *
 * `signup_route()` وحدها تقرر، وهي القاعدة كلها في موضع واحد:
 *
 *   **المعلم وولي الأمر** يكتبان بريدا وجوالا معا، فيختاران القناة عند
 *   التسجيل — بريد أو واتساب. ولهما أن يبدلا من شاشة التأكيد نفسها،
 *   لأن الرقم الخاطئ يكتشف بعد الإرسال لا قبله.
 *
 *   **الطالب** لا يسأل عن جوال أصلا، فقناته البريد وحده. **وإلى أي
 *   بريد؟** إن كان دون الخامسة عشرة فقد كتب بريد ولي أمره وهو شرط
 *   تسجيله — فالرمز إلى ولي الأمر، وبه يفتح الحساب. وهذا ليس تشددا
 *   زائدا: صفحة التسجيل تقول له صراحة «نرسل إليه طلب موافقة قبل تفعيل
 *   الحساب»، وحساب يفتحه القاصر وحده يجعل تلك العبارة كذبا. ومن هو في
 *   الخامسة عشرة فما فوق لا بريد ولي أمر عنده أصلا، فالرمز إليه هو.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * وحين لا تكون هناك قناة
 * ═══════════════════════════════════════════════════════════════════════
 *
 * بريد غير مضبوط وواتساب غير مضبوط يعني رمزا لا يخرج — ولو أوقفنا
 * الحساب على رمز لا يخرج لصار **التسجيل معطلا** على منصة يظن صاحبها
 * أنه أضاف طبقة أمان. فالقاعدة هنا كقاعدة البريد: ما لا يستطاع لا
 * يشترط. `Login::register` يسأل `can_send()` قبل أن يوقف حسابا، وإن
 * كان الجواب لا فتح الحساب كما كان يفتح، وكتب سطر في السجل.
 */
class Taqdar_otp_model extends CI_Model
{
    /** عمر الرمز — عشر دقائق. أطول منها يوسع نافذة من قرأ الرسالة. */
    const TTL = 600;

    /** كم محاولة تخمين قبل أن يموت الرمز؟ */
    const MAX_TRIES = 5;

    /** أقل فاصل بين إرسالين للهوية نفسها. */
    const RESEND_GAP = 60;

    /** كم رمزا في الساعة للهوية الواحدة؟ حاجز إغراق لا حاجز أمن. */
    const MAX_SENDS_HOUR = 5;

    private $schema_checked = false;

    /* =====================================================================
       المخطط
       ===================================================================== */

    /**
     * ينشأ وقت التشغيل لا بهجرة — كما `payment_attempts` و`site_content`.
     * فالنشر `git reset --hard` بلا خطوة ترحيل.
     */
    public function ensure_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_otp` (
                    `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `purpose`    varchar(24)  NOT NULL DEFAULT "signup",
                    `identity`   varchar(190) NOT NULL,
                    `user_id`    int(10) unsigned NOT NULL DEFAULT 0,
                    `channel`    varchar(12)  NOT NULL DEFAULT "email",
                    `dest`       varchar(190) NOT NULL DEFAULT "",
                    `code_hash`  varchar(255) NOT NULL,
                    `tries`      tinyint(3) unsigned NOT NULL DEFAULT 0,
                    `sent_ok`    tinyint(1)   NOT NULL DEFAULT 0,
                    `created_at` datetime     DEFAULT NULL,
                    `expires_at` datetime     DEFAULT NULL,
                    `consumed_at` datetime    DEFAULT NULL,
                    `ip`         varchar(45)  DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `k_lookup` (`purpose`, `identity`, `id`),
                    KEY `k_expires` (`expires_at`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-OTP: تعذر إنشاء tq_otp — ' . $e->getMessage());
        }

        /**
         * `users.tq_verified_at` — متى أثبت صاحب الحساب أنه يملك وجهته؟
         *
         * وهي ليست `status`. حساب المعلم `status = 0` لسببين: تأكيد
         * التواصل، واعتماد الإدارة لأوراقه — والرمز يرفع الأول وحده،
         * فبعمود واحد لا يفرق بينهما. وصفوف `tq_otp` تنظف بعد أيام،
         * فالحقيقة الدائمة تكتب على الحساب لا في سجل عابر.
         *
         * و`IF NOT EXISTS` تقبلها MariaDB، وهي ما يجعل هذا آمنا في كل
         * طلب — لا هجرات في هذا المستودع، والنشر `git reset --hard`.
         */
        try {
            $this->db->query('ALTER TABLE `users`
                ADD COLUMN IF NOT EXISTS `tq_verified_at` DATETIME NULL DEFAULT NULL');
        } catch (Throwable $e) {
            /* قاعدة لا تعرف `IF NOT EXISTS` أو مستخدم بلا صلاحية ALTER:
               التأكيد يعمل كما هو، ويفقد التاريخ وحده. */
            log_message('info', 'TQ-OTP: تعذر ضمان users.tq_verified_at — ' . $e->getMessage());
        }
    }

    /* =====================================================================
       أي قناة، وإلى أين؟
       ===================================================================== */

    /**
     * وجهات الرمز الممكنة لحساب يسجل الآن.
     *
     * @param string $gate     student · teacher · parent
     * @param string $email    بريد صاحب الحساب
     * @param string $guardian بريد ولي الأمر (للطالب دون الخامسة عشرة)
     * @param string $phone    جواله كما حفظ (`501234567`)
     *
     * @return array channels[كل قناة متاحة] · default · why
     *               وكل قناة: to (الوجهة) · label (ما يقرؤه المسجل) ·
     *               shown (الوجهة ملثمة)
     */
    public function signup_route($gate, $email, $guardian = '', $phone = '')
    {
        $email    = trim((string) $email);
        $guardian = trim((string) $guardian);
        $phone    = trim((string) $phone);

        $this->load->model('taqdar_mail_model');
        $this->load->model('taqdar_wa_model');

        $out = array('channels' => array(), 'default' => '', 'why' => '');

        /* ── البريد ─────────────────────────────────────────────────── */
        if ($this->taqdar_mail_model->configured()) {
            /* الطالب القاصر: الوجهة ولي أمره لا هو. */
            $to = ($gate === 'student' && filter_var($guardian, FILTER_VALIDATE_EMAIL))
                ? $guardian : $email;

            if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $to_guardian = ($to !== $email);
                $out['channels']['email'] = array(
                    'to'    => $to,
                    'label' => $to_guardian ? 'بريد ولي أمرك' : 'بريدك الإلكتروني',
                    'shown' => $this->mask_email($to),
                    'guardian' => $to_guardian,
                );
                if ($to_guardian) {
                    $out['why'] = 'أنت دون الخامسة عشرة، فالرمز يذهب إلى ولي أمرك — '
                                . 'وبه يفتح حسابك.';
                }
            }
        }

        /* ── واتساب ─────────────────────────────────────────────────
           للطالب لا يعرض بحال: نموذج التسجيل لا يطلب جواله، ولو أخذناه
           من مكان آخر لأرسلنا رمز حساب قاصر إلى رقم لم يقره وليه. */
        if ($gate !== 'student' && $this->taqdar_wa_model->otp_on()) {
            $e164 = $this->taqdar_wa_model->to_e164($phone);
            if ($e164 !== '') {
                $out['channels']['whatsapp'] = array(
                    'to'    => $e164,
                    'label' => 'واتساب',
                    'shown' => $this->taqdar_wa_model->masked($e164),
                    'guardian' => false,
                );
            }
        }

        if (isset($out['channels']['email']))         $out['default'] = 'email';
        elseif (isset($out['channels']['whatsapp']))  $out['default'] = 'whatsapp';

        return $out;
    }

    /** هل تستطيع المنصة أن ترسل رمزا أصلا؟ سؤال يسبق إيقاف الحساب. */
    public function can_send()
    {
        $this->load->model('taqdar_mail_model');
        $this->load->model('taqdar_wa_model');
        return $this->taqdar_mail_model->configured() || $this->taqdar_wa_model->otp_on();
    }

    /** هل التحقق مطلوب عند إنشاء الحساب؟ مفتاح واحد في `settings`. */
    public function signup_required()
    {
        return (string) get_settings('tq_signup_otp') !== '0';
    }

    /* =====================================================================
       الإصدار والإرسال
       ===================================================================== */

    /**
     * يصدر رمزا ويرسله.
     *
     * @return array ok · channel · shown · error · retry_after
     */
    public function send($purpose, $identity, $channel, $dest, $user_id = 0, $name = '')
    {
        $this->ensure_schema();

        $purpose  = trim((string) $purpose) ?: 'signup';
        $identity = mb_strtolower(trim((string) $identity));
        $channel  = ($channel === 'whatsapp') ? 'whatsapp' : 'email';
        $dest     = trim((string) $dest);

        if ($identity === '' || $dest === '') {
            return $this->no('لا وجهة صالحة لإرسال الرمز.');
        }

        /* ── الخنق: فاصل بين إرسالين، وسقف في الساعة ────────────────
           وهما حاجزان مختلفان: الأول يمنع من ينقر «أعد الإرسال» مرارا،
           والثاني يمنع من يستعمل الصفحة ليرسل رسائل إلى رقم غيره. */
        $last = $this->last_row($purpose, $identity);
        if ($last && !empty($last['created_at'])) {
            $since = time() - strtotime($last['created_at']);
            if ($since < self::RESEND_GAP) {
                return array('ok' => false, 'channel' => $channel, 'shown' => '',
                             'error' => 'أمهل قليلا قبل طلب رمز جديد.',
                             'retry_after' => self::RESEND_GAP - $since);
            }
        }
        if ($this->sends_last_hour($purpose, $identity) >= self::MAX_SENDS_HOUR) {
            return $this->no('طلبت رموزا كثيرة في وقت قصير. حاول بعد ساعة.');
        }

        /* الرمز الجديد يبطل ما قبله: رمزان صالحان معا يوسعان نافذة
           التخمين ويربكان من يقرأ رسالتين. */
        $this->invalidate($purpose, $identity);

        $code = (string) random_int(100000, 999999);
        $now  = time();

        try {
            $this->db->insert('tq_otp', array(
                'purpose'    => $purpose,
                'identity'   => mb_substr($identity, 0, 190),
                'user_id'    => (int) $user_id,
                'channel'    => $channel,
                'dest'       => mb_substr($dest, 0, 190),
                'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
                'tries'      => 0,
                'sent_ok'    => 0,
                'created_at' => date('Y-m-d H:i:s', $now),
                'expires_at' => date('Y-m-d H:i:s', $now + self::TTL),
                'ip'         => $this->input->is_cli_request() ? 'cli' : $this->input->ip_address(),
            ));
            $row_id = (int) $this->db->insert_id();
        } catch (Throwable $e) {
            log_message('error', 'TQ-OTP: تعذر كتابة الرمز — ' . $e->getMessage());
            return $this->no('تعذر إصدار الرمز. حاول مرة أخرى.');
        }

        /* ── الإرسال ───────────────────────────────────────────────── */
        $sent  = false;
        $shown = '';
        $why   = '';

        if ($channel === 'whatsapp') {
            $this->load->model('taqdar_wa_model');
            $shown = $this->taqdar_wa_model->masked($dest);
            $sent  = (bool) $this->taqdar_wa_model->send_otp($dest, $code,
                        array('purpose' => 'otp', 'user_id' => (int) $user_id));
            if (!$sent) $why = $this->taqdar_wa_model->last_error;
        } else {
            $this->load->model('taqdar_mail_model');
            $shown = $this->mask_email($dest);
            $sent  = (bool) $this->taqdar_mail_model->send_lines(
                $dest,
                'رمز تأكيد حسابك في تقدر: ' . $code,
                array(
                    ($name !== '' ? 'مرحبا ' . $name . '،' : 'مرحبا،'),
                    'رمز تأكيد الحساب هو: ' . $code,
                    'صالح عشر دقائق من وقت إرساله، ولا يستعمل إلا مرة واحدة.',
                    'ولا تشاركه مع أحد — لا يطلبه منك موظف في تقدر أبدا.',
                    'وإن لم تكن أنت من طلبه فتجاهل هذه الرسالة، ولن يفتح الحساب.',
                )
            );
            if (!$sent) $why = 'تعذر إرسال البريد.';
        }

        try {
            $this->db->where('id', $row_id)->update('tq_otp', array('sent_ok' => $sent ? 1 : 0));
        } catch (Throwable $e) {
            // لا يؤثر في القبول: العمود للتشخيص وحده
        }

        if (!$sent) {
            log_message('error', 'TQ-OTP: لم يرسل رمز ' . $purpose . ' عبر ' . $channel
                . ' — ' . ($why ?: 'بلا سبب معلن'));
            return array('ok' => false, 'channel' => $channel, 'shown' => $shown,
                         'error' => ($channel === 'whatsapp'
                             ? 'تعذر إرسال الرمز بواتساب. جرب البريد الإلكتروني.'
                             : 'تعذر إرسال الرمز إلى بريدك. حاول مرة أخرى.'),
                         'retry_after' => 0);
        }

        return array('ok' => true, 'channel' => $channel, 'shown' => $shown,
                     'error' => '', 'retry_after' => self::RESEND_GAP);
    }

    /* =====================================================================
       الحكم
       ===================================================================== */

    /**
     * هل هذا الرمز صحيح؟
     *
     * @return array ok · error · user_id
     *
     * والرمز يستهلك عند القبول: صف بلا `consumed_at` مفتوح، وبها مغلق.
     * فمن أعاد إرسال النموذج مرتين لا يفتح حسابا مرتين، ومن التقط الرمز
     * من رسالة لا يستعمله بعد صاحبه.
     */
    public function verify($purpose, $identity, $code)
    {
        $this->ensure_schema();

        $purpose  = trim((string) $purpose) ?: 'signup';
        $identity = mb_strtolower(trim((string) $identity));
        $code     = preg_replace('/\D/', '', (string) $code);

        if ($code === '' || strlen($code) !== 6) {
            return array('ok' => false, 'error' => 'الرمز ست خانات رقمية.', 'user_id' => 0);
        }

        $row = $this->last_row($purpose, $identity);
        if (!$row) {
            return array('ok' => false, 'error' => 'لا رمز مطلوب لهذا الحساب. اطلب رمزا جديدا.',
                         'user_id' => 0);
        }
        if (!empty($row['consumed_at'])) {
            return array('ok' => false, 'error' => 'استعمل هذا الرمز من قبل. اطلب رمزا جديدا.',
                         'user_id' => 0);
        }
        if (strtotime($row['expires_at']) < time()) {
            return array('ok' => false, 'error' => 'انتهت صلاحية الرمز. اطلب رمزا جديدا.',
                         'user_id' => 0);
        }
        if ((int) $row['tries'] >= self::MAX_TRIES) {
            return array('ok' => false,
                         'error' => 'تجاوزت عدد المحاولات على هذا الرمز. اطلب رمزا جديدا.',
                         'user_id' => 0);
        }

        if (!password_verify($code, (string) $row['code_hash'])) {
            /* العداد يزاد **قبل** أن يقال «خطأ»: من قطع الاتصال بعد
               الفحص وقبل الزيادة كان يخمن بلا حد. */
            try {
                $this->db->where('id', (int) $row['id'])
                         ->set('tries', 'tries + 1', false)->update('tq_otp');
            } catch (Throwable $e) {
                // العداد يفشل ولا يقبل الرمز الخاطئ
            }
            $left = max(0, self::MAX_TRIES - ((int) $row['tries'] + 1));
            return array('ok' => false, 'user_id' => 0,
                         'error' => 'الرمز غير صحيح.'
                                  . ($left > 0 ? ' بقيت لك ' . $left . ' محاولة.' : ''));
        }

        try {
            $this->db->where('id', (int) $row['id'])
                     ->update('tq_otp', array('consumed_at' => date('Y-m-d H:i:s')));
        } catch (Throwable $e) {
            log_message('error', 'TQ-OTP: تعذر استهلاك الرمز — ' . $e->getMessage());
        }

        return array('ok' => true, 'error' => '', 'user_id' => (int) $row['user_id']);
    }

    /** حال الرمز الجاري — تقرؤه شاشة التأكيد فتعرف ماذا تعرض. */
    public function state($purpose, $identity)
    {
        $this->ensure_schema();
        $row = $this->last_row($purpose, mb_strtolower(trim((string) $identity)));
        if (!$row) return null;

        $left = strtotime($row['expires_at']) - time();
        $gap  = self::RESEND_GAP - (time() - strtotime($row['created_at']));

        return array(
            'channel'     => (string) $row['channel'],
            'dest'        => (string) $row['dest'],
            'shown'       => ($row['channel'] === 'whatsapp')
                           ? $this->wa_mask((string) $row['dest'])
                           : $this->mask_email((string) $row['dest']),
            'sent_ok'     => (int) $row['sent_ok'] === 1,
            'expires_in'  => max(0, $left),
            'resend_in'   => max(0, $gap),
            'tries_left'  => max(0, self::MAX_TRIES - (int) $row['tries']),
            'consumed'    => !empty($row['consumed_at']),
        );
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    /** آخر صف لهذه الهوية في هذا الغرض. */
    private function last_row($purpose, $identity)
    {
        try {
            return $this->db->where('purpose', $purpose)->where('identity', $identity)
                            ->order_by('id', 'DESC')->limit(1)
                            ->get('tq_otp')->row_array();
        } catch (Throwable $e) {
            return null;
        }
    }

    private function sends_last_hour($purpose, $identity)
    {
        try {
            return (int) $this->db->where('purpose', $purpose)->where('identity', $identity)
                                  ->where('created_at >=', date('Y-m-d H:i:s', time() - 3600))
                                  ->count_all_results('tq_otp');
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** يبطل ما بقي من رموز هذه الهوية — بالاستهلاك لا بالحذف، فالسجل يفيد. */
    private function invalidate($purpose, $identity)
    {
        try {
            $this->db->where('purpose', $purpose)->where('identity', $identity)
                     ->where('consumed_at IS NULL', null, false)
                     ->update('tq_otp', array('consumed_at' => date('Y-m-d H:i:s')));
        } catch (Throwable $e) {
            // إبطال يفشل لا يمنع إصدارا: الأحدث هو ما يقرأ في `verify`
        }
    }

    /**
     * ينظف ما مضى عليه يوم. ينادى من الكرون.
     *
     * والجدول ينمو بصف لكل محاولة تسجيل — وأكثرها لحسابات لم تكتمل.
     */
    public function purge($days = 3)
    {
        $this->ensure_schema();
        try {
            $this->db->where('created_at <', date('Y-m-d H:i:s', time() - max(1, (int) $days) * 86400))
                     ->delete('tq_otp');
            return (int) $this->db->affected_rows();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** `ahmed@taqdaredu.com` → `ah•••@taqdaredu.com`. */
    public function mask_email($email)
    {
        $email = trim((string) $email);
        $at    = strrpos($email, '@');
        if ($at === false || $at < 1) return '';

        $user = substr($email, 0, $at);
        $dom  = substr($email, $at);
        $keep = ($at >= 3) ? 2 : 1;

        return substr($user, 0, $keep) . str_repeat('•', max(2, strlen($user) - $keep)) . $dom;
    }

    private function wa_mask($e164)
    {
        $this->load->model('taqdar_wa_model');
        return $this->taqdar_wa_model->masked($e164);
    }

    private function no($msg)
    {
        return array('ok' => false, 'channel' => '', 'shown' => '',
                     'error' => $msg, 'retry_after' => 0);
    }
}
