<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-CONTACT-SPAM — نموذج «تواصل معنا» كان بابا مفتوحا، وأسوأ من ذلك مكبر صوت.
 *
 * ثلاثة أعطال اجتمعت فصارت المنصة ترسل بريدا دعائيا نيابة عن غيرها:
 *
 * ١ — **لا حارس أصلا.** reCAPTCHA مطفأة ومفاتيحها قوالب (`Valid-site-key`)،
 *     و`csrf_regenerate = FALSE` فالرمز الواحد يصلح ساعتين ويعاد استعماله
 *     بلا حد — أي أن آلة تجلب الصفحة مرة ثم ترسل ألف طلب بالرمز نفسه.
 *     ولا مهلة بين إرسالين، ولا سقف لعنوان، ولا مصيدة، ولا فحص لما يكتب.
 *
 * ٢ — **الإيصال كان يعيد نص المرسل إلى بريد يكتبه المرسل.** الرسالة تحفظ،
 *     ثم يخرج «استلمنا رسالتك» إلى العنوان المكتوب في الحقل **وفيه نسخة مما
 *     أرسل**. فمن كتب نص إعلانه في «الرسالة» وبريد ضحيته في «البريد» جعل
 *     خادمنا يسلم إعلانه إلى الضحية موقعا باسم المنصة — وهو ما تريه صورة
 *     اللوحة حرفا بحرف: نص روسي ورابط، وبريد مستلم لا حساب له عندنا.
 *     والثمن ليس رسائل في اللوحة وحدها: سمعة النطاق عند مزودي البريد، ومعها
 *     كل رسالة تفعيل واستعادة كلمة مرور تخرج بعدها.
 *
 * ٣ — **كل محاولة تكتب صفا في `contact`.** فالجدول يمتلئ، وشاشة اللوحة
 *     تغرق، والرسالة الحقيقية تضيع بين مئتين.
 *
 * والقواعد كلها هنا، والشاشة تعرض ولا تحكم — كما في سائر طبقة `Taqdar_*`.
 * وما يمنع لا يكتب في `contact` أصلا؛ ويكتب في `tq_contact_log` سطرا واحدا
 * (بلا نص الرسالة) لأن السقوف تعد منه، ولأن من يفتح اللوحة غدا يجب أن يعرف
 * لماذا هدأ الجدول فجأة.
 */
class Taqdar_contact_model extends CI_Model
{
    /**
     * موضوعات النموذج — المصدر الواحد: القالب يطبع منها، والخادم يفحص بها.
     *
     * وهي **قيم تخزن وتقارن** لا نص يترجم: صف قديم في `contact` يحمل الموضوع
     * كما كتب يوم أرسل، ومرشح الموضوع في اللوحة يقارن به. فلو ترجمت لصار
     * الخادم يرد كل رسالة ترسل من صفحة إنجليزية، ولانقسم المرشح نصفين.
     * ولذلك تكتب في نص واحد يفكك — و`explode` مما يستثنيه فاحص الترجمة.
     */
    public static function subjects()
    {
        return explode('|', 'استفسار عن البرامج|الدعم الفني|الاشتراكات والفواتير|الانضمام كمعلم|أخرى');
    }

    /* السقوف والحدود — تقرأ من `settings` وهذه افتراضاتها. */
    private $D = array(
        'tq_contact_min_seconds' => 5,      // أقل زمن معقول بين فتح الصفحة والإرسال
        'tq_contact_form_ttl'    => 7200,   // عمر ختم النموذج
        'tq_contact_ip_hour'     => 3,
        'tq_contact_ip_day'      => 8,
        'tq_contact_mail_hour'   => 2,
        'tq_contact_mail_day'    => 4,
        'tq_contact_score_block' => 4,      // حد الحجب من فحص المحتوى
        'tq_contact_max_len'     => 3000,
        'tq_contact_min_len'     => 10,
    );

    // ───────────────────────────── المخطط ─────────────────────────────

    /**
     * ينشئ سجل المحاولات. ينادى من مسار العرض ومن مسار الكتابة معا: السقوف
     * تقرأ من الجدول، وقراءة جدول قبل إنشائه ترد «Table doesn't exist» فتبيض
     * صفحة عامة يقرؤها كل زائر.
     */
    public function install_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_contact_log` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `ip` VARCHAR(45) NOT NULL DEFAULT "",
                    `email` VARCHAR(191) NOT NULL DEFAULT "",
                    `verdict` VARCHAR(16) NOT NULL DEFAULT "",
                    `reason` VARCHAR(64) NOT NULL DEFAULT "",
                    `score` INT(11) NOT NULL DEFAULT 0,
                    `created_at` INT(11) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `k_ip` (`ip`, `created_at`),
                    KEY `k_mail` (`email`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-CONTACT schema: ' . $e->getMessage());
        }
    }

    // ───────────────────────── ختم النموذج ─────────────────────────

    /**
     * السر يزرع في `settings` كما يزرع `tq_media_secret` — المستودع عام
     * والنشر `git reset --hard`، فسر في ملف يضيع أو يتسرب.
     */
    private function secret()
    {
        static $s = null;
        if ($s !== null) return $s;

        $row = $this->db->select('value')->where('key', 'tq_contact_secret')
                        ->get('settings')->row_array();
        $s = $row ? (string) $row['value'] : '';

        if ($s === '') {
            $s = bin2hex(random_bytes(32));
            $this->db->replace('settings', array('key' => 'tq_contact_secret', 'value' => $s));
        }
        return $s;
    }

    /** ختم موقع بوقت طبع الصفحة. */
    public function stamp()
    {
        $t = time();
        return $t . '.' . hash_hmac('sha256', (string) $t, $this->secret());
    }

    /**
     * يرد عمر الختم بالثواني، و`-1` لختم مفقود أو مزور.
     * والتوقيع ليس زينة: بلاه يكتب البوت `ts` بما يشاء فيمر فحص المهلة.
     */
    private function stamp_age($raw)
    {
        $raw = (string) $raw;
        if (strpos($raw, '.') === false) return -1;

        list($t, $sig) = explode('.', $raw, 2);
        if (!ctype_digit($t)) return -1;
        if (!hash_equals(hash_hmac('sha256', $t, $this->secret()), (string) $sig)) return -1;

        return time() - (int) $t;
    }

    // ───────────────────────────── الفحص ─────────────────────────────

    private function opt($key)
    {
        $v = get_settings($key);
        return ($v === null || $v === '') ? $this->D[$key] : (int) $v;
    }

    /**
     * الحكم على محاولة إرسال.
     *
     * يرد: `verdict` (`ok` · `bot` · `rate` · `content` · `invalid`)،
     * و`reason` مفتاحا ثابتا، و`score`، و`msg` نصا يعرض للزائر (فارغ حيث
     * يحسن الصمت)، و`silent` — أي: أعرض له نجاحا ولا تكتب شيئا.
     *
     * والصمت مقصود في إشارات الآلة وحدها (المصيدة والختم): رسالة خطأ
     * صريحة تعلم البوت أي حقل يملؤه في المحاولة التالية. أما فحص المحتوى
     * فيقال صراحة — إنسان وقع في المرشح يحتاج طريقا آخر يعرفه.
     */
    public function screen(array $d, $ip)
    {
        $this->install_schema();

        $name    = trim(trim((string) $this->get($d, 'first_name')) . ' ' . trim((string) $this->get($d, 'last_name')));
        $email   = trim((string) $this->get($d, 'email'));
        $phone   = trim((string) $this->get($d, 'phone'));
        $subject = trim((string) $this->get($d, 'subject'));
        $message = trim((string) $this->get($d, 'message'));
        $address = trim((string) $this->get($d, 'address'));

        /* ١ — المصيدة: حقل مخفي لا يراه إنسان ولا يتركه فارغا أكثر البوتات. */
        if (trim((string) $this->get($d, 'website')) !== '' || trim((string) $this->get($d, 'company')) !== '') {
            return $this->verdict('bot', 'honeypot', 0, '', true);
        }

        /* ٢ — الختم: مفقود أو مزور أو أسرع من إنسان أو أقدم من ساعتين. */
        $age = $this->stamp_age($this->get($d, 'tq_ts'));
        if ($age < 0) {
            return $this->verdict('bot', 'stamp_missing', 0, '', true);
        }
        if ($age < $this->opt('tq_contact_min_seconds')) {
            return $this->verdict('bot', 'too_fast', 0, '', true);
        }
        if ($age > $this->opt('tq_contact_form_ttl')) {
            return $this->verdict('invalid', 'stamp_expired', 0,
                'انتهت صلاحية النموذج. حدث الصفحة ثم أعد الإرسال.', false);
        }

        /* ٣ — حقن ترويسات البريد: سطر جديد في حقل يدخل في ترويسة `From`
               أو `Subject` يضيف مستلمين لا نراهم. */
        foreach (array($name, $email, $phone, $subject) as $one) {
            if (preg_match('/[\r\n]/', (string) $one)) {
                return $this->verdict('bot', 'header_injection', 0, '', true);
            }
        }

        /* ٤ — الأطوال: نص من ثلاثة أحرف ليس رسالة، ونص من عشرين ألفا ليس منا. */
        $len = $this->len($message);
        if ($len < $this->opt('tq_contact_min_len')) {
            return $this->verdict('invalid', 'too_short', 0, 'الرسالة قصيرة جدا. اكتب استفسارك كاملا.', false);
        }
        if ($len > $this->opt('tq_contact_max_len')) {
            return $this->verdict('invalid', 'too_long', 0, 'الرسالة أطول مما يقبله النموذج.', false);
        }
        if ($this->len($name) > 120) {
            return $this->verdict('invalid', 'name_long', 0, 'الاسم أطول مما يقبله النموذج.', false);
        }

        /* ٥ — الموضوع من القائمة. وفارغ يمر: القالب الموروث بلا هذا الحقل. */
        if ($subject !== '' && !in_array($subject, self::subjects(), true)) {
            return $this->verdict('bot', 'subject_unknown', 0, '', true);
        }

        /* ٦ — السقوف قبل فحص المحتوى: من تجاوز عنوانه السقف لا معنى لقراءة
               نصه، والجواب له واحد مهما كتب. */
        $rate = $this->rate_check($ip, $email);
        if ($rate !== '') {
            return $this->verdict('rate', $rate, 0,
                'وصلتنا رسائل عدة من هذا العنوان. أمهلنا قليلا ثم أعد المحاولة، أو راسلنا على info@taqdaredu.com.', false);
        }

        /* ٧ — المحتوى. */
        $s = $this->score($name, $email, $phone, $subject, $message, $address);
        if ($s['score'] >= $this->opt('tq_contact_score_block')) {
            return $this->verdict('content', $s['reason'], $s['score'],
                'تعذر إرسال رسالتك. إن كانت تحتوي روابط فاحذفها وأعد المحاولة، أو راسلنا مباشرة على info@taqdaredu.com.', false);
        }

        return $this->verdict('ok', '', $s['score'], '', false);
    }

    private function len($s)
    {
        return function_exists('mb_strlen') ? mb_strlen((string) $s, 'UTF-8') : strlen((string) $s);
    }

    private function get(array $d, $k)
    {
        return isset($d[$k]) ? $d[$k] : '';
    }

    private function verdict($v, $reason, $score, $msg, $silent)
    {
        return array(
            'ok'      => $v === 'ok',
            'verdict' => $v,
            'reason'  => $reason,
            'score'   => (int) $score,
            'msg'     => $msg,
            'silent'  => (bool) $silent,
        );
    }

    // ─────────────────────────── فحص المحتوى ───────────────────────────

    /**
     * نقاط لا قرار واحد: رابط في رسالة قد يكتبه معلم يحيلنا إلى ملفه،
     * وحرف روسي في منصة عربية سعودية لا يكتبه أحد. فالقرار مجموع لا شرط
     * أول — وقاعدة واحدة صارمة ترد رسائل حقيقية.
     */
    private function score($name, $email, $phone, $subject, $message, $address)
    {
        $score = 0;
        $why   = array();
        $all   = $name . ' ' . $email . ' ' . $subject . ' ' . $message . ' ' . $address;

        /* أبجدية أجنبية عن المنصة: روسية · يونانية · عبرية · صينية ·
           يابانية · كورية · تايلندية. وهي وحدها كافية عمليا. */
        if (preg_match('/[\p{Cyrillic}\p{Greek}\p{Hebrew}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Thai}]/u', $all)) {
            $score += 4;
            $why[] = 'foreign_script';
        }

        /* روابط: في الرسالة تحتمل، وفي الاسم أو الموضوع أو الجوال لا تحتمل. */
        $links = preg_match_all('~(https?://|www\.|[a-z0-9\-]+\.(?:ru|su|top|xyz|click|link|shop|online|site|icu|cn|tk))~i', $message);
        if ($links >= 1) { $score += 2; $why[] = 'link'; }
        if ($links >= 2) { $score += 2; $why[] = 'links'; }

        if (preg_match('~(https?://|www\.)~i', $name . ' ' . $subject . ' ' . $phone)) {
            $score += 5;
            $why[] = 'link_in_name';
        }

        /* مختصرات الروابط ومنصات المراسلة — ما يستعمله الإعلان لا الزائر. */
        if (preg_match('~(bit\.ly|tinyurl|t\.me|telegram\.me|is\.gd|cutt\.ly|goo\.gl|shorturl|rebrand\.ly|share\.google)~i', $all)) {
            $score += 3;
            $why[] = 'shortener';
        }

        /* وسم يدرج في نص عاد ليعرض عند من يقرأ الرسالة في اللوحة. */
        if (preg_match('~<\s*a\b|\[url|<\s*script~i', $all)) {
            $score += 4;
            $why[] = 'markup';
        }

        /* كلمات الإعلان الشائعة — إنجليزية وروسية وعربية. وهي **مطابقات لا
           نص يعرض**، فتكتب في نمط واحد: ترجمة كلمة يبحث عنها تعطل البحث. */
        if (preg_match(
            '~viagra|casino|crypto|bitcoin|forex|binary option|seo service|backlink'
            . '|loan offer|earn money|make money|investment plan|porn'
            . '|приз|розыгрыш|акци|бонус|выигр|ставк'
            . '|قرض|ربح مضمون|عملات رقمية|كازينو~ui',
            $all
        )) {
            $score += 3;
            $why[] = 'keyword';
        }

        /* الرسالة الدعائية الثانية لا تحمل رابطا ولا حرفا روسيا: عرض خدمة
           بالإنجليزية («صمم لك فيديو بـ195 دولارا» · «سجل موقعك في جوجل»).
           فثلاث علامات تجتمع فيها ولا تجتمع في رسالة زائر عربي:

           ١ — لا حرف عربي في الرسالة كلها. وحدها لا تكفي — قد يكتب مقيم
               بالإنجليزية — فنقطتان لا أربع.
           ٢ — نطاق مكتوب عاريا بلا `http`. ونطاقنا مستثنى: من يسأل عنا
               يسمينا. */
        if (!preg_match('/\p{Arabic}/u', $message)) {
            $score += 2;
            $why[] = 'no_arabic';
        }

        $bare = preg_replace('~\btaqdaredu\.com\b~i', '', $message);
        if (preg_match('~\b[a-z0-9][a-z0-9\-]{1,}\.(com|net|org|io|co|info|biz|me|us|uk|de)\b~i', $bare)) {
            $score += 2;
            $why[] = 'bare_domain';
        }

        /* ٣ — سعر بعملة أجنبية: عرض بيع لا استفسار. */
        if (preg_match('~[\$€£]\s?\d|\bUSD\b|\bEUR\b~i', $all)) {
            $score += 2;
            $why[] = 'price_offer';
        }

        /* جوال بلا رقم واحد — حشو آلة. */
        if ($phone !== '' && !preg_match('/\d/', $phone)) {
            $score += 1;
            $why[] = 'phone_junk';
        }

        return array('score' => $score, 'reason' => $why ? implode(',', array_unique($why)) : '');
    }

    // ──────────────────────────── السقوف ────────────────────────────

    /** يرد مفتاح السقف المتجاوز، أو نصا فارغا. */
    private function rate_check($ip, $email)
    {
        $ip    = (string) $ip;
        $email = strtolower(trim((string) $email));
        $now   = time();

        try {
            if ($ip !== '') {
                if ($this->count_since('ip', $ip, $now - 3600) >= $this->opt('tq_contact_ip_hour'))  return 'ip_hour';
                if ($this->count_since('ip', $ip, $now - 86400) >= $this->opt('tq_contact_ip_day'))  return 'ip_day';
            }
            if ($email !== '') {
                if ($this->count_since('email', $email, $now - 3600) >= $this->opt('tq_contact_mail_hour')) return 'mail_hour';
                if ($this->count_since('email', $email, $now - 86400) >= $this->opt('tq_contact_mail_day')) return 'mail_day';
            }
        } catch (Throwable $e) {
            /* السجل لا يقرأ: تمرير الرسالة أهون من رد رسائل الناس كلها،
               وبقية الحراس قائمة. و`reset_query()` لأن الاستثناء يترك بناء
               الاستعلام معلقا فيرث التالي ضمومه (TQ-BUILDER-DIRTY). */
            $this->db->reset_query();
            log_message('error', 'TQ-CONTACT rate: ' . $e->getMessage());
        }

        return '';
    }

    /**
     * و`invalid` لا تعد: تلك أخطاء إنسان — رسالة قصيرة، ونموذج بقي مفتوحا
     * حتى انتهى ختمه. ومن عدها أقفل الباب على من أخطأ ثلاث مرات، وهو أول
     * من يحتاج أن يرسل.
     */
    private function count_since($col, $val, $from)
    {
        return (int) $this->db->where($col, $val)->where('created_at >=', (int) $from)
                              ->where('verdict !=', 'invalid')
                              ->count_all_results('tq_contact_log');
    }

    /**
     * يسجل المحاولة — بلا نص الرسالة ولا اسم صاحبها: هذا سجل حراسة تعد
     * منه السقوف، لا نسخة ثانية من الجدول. والفشل هنا لا يمنع رسالة صحيحة.
     */
    public function note($ip, $email, $verdict)
    {
        try {
            $this->install_schema();
            $this->db->insert('tq_contact_log', array(
                'ip'         => (string) $ip,
                'email'      => strtolower(trim((string) $email)),
                'verdict'    => (string) $verdict['verdict'],
                'reason'     => (string) $verdict['reason'],
                'score'      => (int) $verdict['score'],
                'created_at' => time(),
            ));
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-CONTACT note: ' . $e->getMessage());
        }
    }

    /** ينظف السجل — ينادى من `taqdar_cron purge`. */
    public function purge($days = 30)
    {
        try {
            $this->install_schema();
            $this->db->where('created_at <', time() - ((int) $days * 86400))->delete('tq_contact_log');
            return (int) $this->db->affected_rows();
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-CONTACT purge: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * يحكم على صف محفوظ في `contact` بمقياس المحتوى وحده — لتنظيف ما دخل
     * قبل هذا الحارس. ولا مصيدة هنا ولا ختم ولا سقف: تلك أحكام على طلب
     * لم يعد موجودا.
     */
    public function score_row(array $r)
    {
        return $this->score(
            trim($this->get($r, 'first_name') . ' ' . $this->get($r, 'last_name')),
            (string) $this->get($r, 'email'),
            (string) $this->get($r, 'phone'),
            (string) $this->get($r, 'subject'),
            (string) $this->get($r, 'message'),
            (string) $this->get($r, 'address')
        );
    }

    public function block_score()
    {
        return $this->opt('tq_contact_score_block');
    }
}
