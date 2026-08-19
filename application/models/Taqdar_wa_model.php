<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * واتساب الصادر — **الموضع الوحيد الذي يرسل رسالة واتساب**.
 *
 * وهو أخو `Taqdar_mail_model` في كل شيء: يقرأ ضبطه من `settings` لا من
 * ملف، ويرد `false` بهدوء حين لا يكون مضبوطا، ولا يسمح لفشله أن يسقط
 * ما استدعاه. والقاعدة نفسها صريحة هنا:
 *
 *   **واتساب غير مضبوط = لا محاولة ولا خطأ.**
 *
 * فالدفع يفعل الاشتراك سواء وصلت الرسالة أو لم تصل، والحساب ينشأ سواء
 * خرج الرمز بواتساب أو بالبريد. الرسالة تابع للعملية لا العملية نفسها.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * المزود: واجهة واتساب السحابية من ميتا (WhatsApp Cloud API)
 * ═══════════════════════════════════════════════════════════════════════
 *
 * لا وسيط ولا اشتراك شهري: الحساب يفتح في `developers.facebook.com`
 * والرسائل تخرج من `graph.facebook.com` مباشرة. وثلاثة بيانات تكفي —
 * رمز الوصول، ومعرف رقم المرسل، ومعرف حساب الأعمال.
 *
 * **وقاعدة واحدة تحكم كل ما يكتب هنا، ومن يجهلها يبني نظاما لا يرسل
 * شيئا:** واتساب لا يسمح لك أن تبدأ محادثة بنص تكتبه. النص الحر لا يخرج
 * إلا داخل **نافذة أربع وعشرين ساعة** من آخر رسالة أرسلها العميل إليك.
 * وكل ما ترسله هذه المنصة يبدأ من عندنا — رمز تحقق، وخبر دفعة نجحت —
 * فلا يخرج شيء منه إلا بـ**قالب معتمد** (`template`) صادقت عليه ميتا
 * مسبقا.
 *
 * ولذلك في هذا الملف مساران لا واحد:
 *
 *   `send_template()` — الطريق الحقيقي. قالب باسمه ولغته وبدائله.
 *   `send_text()`     — نص حر. لا يصل إلا لمن راسل الرقم في آخر يوم،
 *                       وهو هنا للفحص وحده ولا يعتمد عليه إشعار.
 *
 * ومن ضبط الرموز ونسي القوالب يرى كل رسالة ترد بالخطأ `131047`، وهو
 * جواب صحيح عن سؤال لم يفهمه — فتترجم رموز ميتا كلها إلى عربية تقول
 * ما العمل، لا إلى «تعذر الإرسال».
 *
 * ═══════════════════════════════════════════════════════════════════════
 * السجل
 * ═══════════════════════════════════════════════════════════════════════
 *
 * البريد يفشل بصوت — الخادم يرد بسبب يقرأ في شاشة الفحص. وواتساب يفشل
 * بصمت: الطلب يرجع 200 ورسالة لا تصل، أو يرجع رمزا لا يراه أحد لأنه وقع
 * في ويبهوك دفع. فكل محاولة تكتب صفا في `tq_wa_log` — إلى من، ولأي غرض،
 * وبأي قالب، وما ردت ميتا. بلا هذا السجل يصير سؤال «هل وصل الرمز؟»
 * جوابه ظن.
 *
 * **ولا يكتب نص الرسالة.** رمز التحقق سر، وسجل يحفظه يجعل كل من يفتح
 * اللوحة يقرأ رموز الناس.
 */
class Taqdar_wa_model extends CI_Model
{
    /** جذر واجهة ميتا. النسخة تركب من الضبط. */
    const GRAPH = 'https://graph.facebook.com/';

    /** النسخة الافتراضية حين لا يكتب المسؤول شيئا. */
    const DEFAULT_VER = 'v21.0';

    /** مفاتيح الإعدادات — كلها في `settings` بالبادئة `tq_wa_`. */
    public static $KEYS = array(
        'tq_wa_enabled',
        'tq_wa_token',
        'tq_wa_phone_id',
        'tq_wa_waba_id',
        'tq_wa_api_ver',
        'tq_wa_tpl_otp',
        'tq_wa_tpl_otp_lang',
        'tq_wa_tpl_otp_button',
        'tq_wa_tpl_notice',
        'tq_wa_tpl_notice_lang',
        'tq_wa_tpl_notice_params',
        'tq_wa_notify_payments',
        'tq_wa_otp_allowed',
    );

    /**
     * أنواع الإشعارات التي تخرج بواتساب.
     *
     * **ليست كل الإشعارات.** المطلوب إشعارات المال وحدها: تفعيل اشتراك
     * ودفعة نجحت وقرار سحب. وما عداها — حصة ألغيت، نتيجة امتحان،
     * تنبيه انقطاع — يبقى في المنصة والبريد كما كان. وواتساب قناة يدفع
     * ثمن كل رسالة فيها، وقناة تزعج إن أكثرت فيها فيسد المستلم الرقم.
     */
    public static $PAY_TYPES = array('subscription', 'wallet', 'invoice', 'payment', 'payout');

    private $cfg = null;
    private $schema_checked = false;

    /** تشخيص آخر فشل — تقرؤه شاشة الفحص وحدها. */
    public $last_error = '';

    /** رمز آخر خطأ كما قالته ميتا — يفيد في مقابلة الوثائق. */
    public $last_code = '';

    /* =====================================================================
       الضبط
       ===================================================================== */

    /**
     * إعدادات الواجهة — بضمة واحدة لا ثلاث عشرة.
     *
     * `get_settings()` استعلام لكل مفتاح، وهذه تنادى في كل ويبهوك دفع.
     */
    public function config()
    {
        if ($this->cfg !== null) return $this->cfg;

        $v = array();
        try {
            $rows = $this->db->select('key, value')->where_in('key', self::$KEYS)
                             ->get('settings')->result_array();
            foreach ($rows as $r) $v[$r['key']] = (string) $r['value'];
        } catch (Throwable $e) {
            log_message('error', 'TQ-WA: تعذر قراءة الإعدادات — ' . $e->getMessage());
        }

        $ver = trim((string) (isset($v['tq_wa_api_ver']) ? $v['tq_wa_api_ver'] : ''));
        if (!preg_match('/^v[0-9]+\.[0-9]+$/', $ver)) $ver = self::DEFAULT_VER;

        $g = function ($k, $d = '') use ($v) {
            return isset($v[$k]) ? trim((string) $v[$k]) : $d;
        };

        $this->cfg = array(
            'enabled'  => $g('tq_wa_enabled') === '1',
            'token'    => $g('tq_wa_token'),
            'phone_id' => preg_replace('/\D/', '', $g('tq_wa_phone_id')),
            'waba_id'  => preg_replace('/\D/', '', $g('tq_wa_waba_id')),
            'ver'      => $ver,

            'tpl_otp'      => $g('tq_wa_tpl_otp'),
            'tpl_otp_lang' => $g('tq_wa_tpl_otp_lang') ?: 'ar',
            /* زر «نسخ الرمز» في قوالب المصادقة: وجوده يغير جسم الطلب —
               بديل ثان في مكون الزر. وقالب فيه زر أرسل بلا بديله يرد
               `132000` ولا يصل. */
            'tpl_otp_button' => !isset($v['tq_wa_tpl_otp_button']) || $v['tq_wa_tpl_otp_button'] === '1',

            'tpl_notice'        => $g('tq_wa_tpl_notice'),
            'tpl_notice_lang'   => $g('tq_wa_tpl_notice_lang') ?: 'ar',
            'tpl_notice_params' => max(1, min(2, (int) $g('tq_wa_tpl_notice_params', '2'))),

            /* المفتاحان الوظيفيان: النية لا القدرة. */
            'notify_payments' => !isset($v['tq_wa_notify_payments']) || $v['tq_wa_notify_payments'] === '1',
            'otp_allowed'     => !isset($v['tq_wa_otp_allowed'])     || $v['tq_wa_otp_allowed'] === '1',
        );
        return $this->cfg;
    }

    /** ينسى المحفوظ — تنادى بعد الحفظ في شاشة الإعدادات. */
    public function forget()
    {
        $this->cfg = null;
    }

    /**
     * هل يمكن الإرسال فعلا؟
     *
     * ثلاثة لا واحد: مفعل، وله رمز وصول، وله معرف رقم مرسل. و`enabled`
     * وحده لا يعني شيئا — كما أن `smtp_host` وحده لا يعني أن البريد يرسل.
     */
    public function configured()
    {
        $c = $this->config();
        return $c['enabled'] && $c['token'] !== '' && $c['phone_id'] !== '';
    }

    /** اسم ثان لـ`configured()` — يقرأ في مواضع القرار. */
    public function ready()
    {
        return $this->configured();
    }

    /** ما ينقص، بعبارة تقرأ في الشاشة لا في السجل. */
    public function missing()
    {
        $c   = $this->config();
        $out = array();
        if (!$c['enabled'])        $out[] = 'التفعيل';
        if ($c['token'] === '')    $out[] = 'رمز الوصول';
        if ($c['phone_id'] === '') $out[] = 'معرف رقم المرسل';
        return $out;
    }

    /** هل تخرج إشعارات الدفع بواتساب الآن؟ نية وقدرة معا. */
    public function payments_on()
    {
        return $this->configured() && $this->config()['notify_payments'];
    }

    /** هل يعرض للمسجل خيار «الرمز بواتساب»؟ */
    public function otp_on()
    {
        return $this->configured() && $this->config()['otp_allowed'];
    }

    /* =====================================================================
       الأرقام

       رقم يكتبه إنسان في نموذج عربي قد يصل بأي من هذه الصور:
       `0501234567` · `501234567` · `+966 50 123 4567` · `00966501234567`
       · `٠٥٠١٢٣٤٥٦٧`. وواتساب يقبل صورة واحدة: أرقام متصلة تبدأ برمز
       الدولة بلا `+` ولا أصفار ولا فراغات.

       والخطأ هنا لا يظهر خطأ: `05...` يرسل إلى دولة أخرى أو يرد
       `131026` بلا سبب مفهوم.
       ===================================================================== */

    /**
     * أي صورة → `9665XXXXXXXX`. و`''` لما ليس رقما صالحا.
     *
     * وأرقام غير سعودية تقبل حين تأتي برمز دولة صريح (`+` أو `00`):
     * المعلمون قد يكونون خارج المملكة، وردها يمنع عنهم كل إشعار.
     */
    public function to_e164($raw)
    {
        $p = strtr((string) $raw, array(
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ));

        $intl = (strpos(trim($p), '+') === 0);
        $d    = preg_replace('/\D/', '', $p);
        if ($d === '') return '';

        if (strpos($d, '00') === 0) { $intl = true; $d = substr($d, 2); }

        /* سعودي في كل صوره */
        if (preg_match('/^966(5[0-9]{8})$/', $d, $m)) return '966' . $m[1];
        if (preg_match('/^0?(5[0-9]{8})$/', $d, $m))  return '966' . $m[1];

        /* رقم دولي صريح: يقبل بطوله المعقول ولا يفترض له بلد. */
        if ($intl && strlen($d) >= 8 && strlen($d) <= 15) return $d;

        /* وناتج هذه الدالة نفسها.
           `dispatch()` يطبع ما طبعه مستدعيه: شاشة الفحص و`phone_of()`
           و`signup_route()` ثلاثتها تنادي `to_e164` ثم تمرر الناتج إلى
           `send_*` التي تناديها ثانية. والناتج لا `+` فيه ولا `00`، فرقم
           غير سعودي يمر في المرة الأولى ويسقط في الثانية — بصمت، وبسطر
           سجل يقول «الرقم غير صالح» عن رقم صالح. فيبقى المعلم خارج
           المملكة بلا رمز تحقق ولا خبر دفعة، وهو ما جاء السطر الذي فوق
           ليمنعه.
           وأحد عشر رقما فأكثر لا يكون جوالا سعوديا محليا — وهو تسعة
           (`5XXXXXXXX`) أو عشرة (`05XXXXXXXX`) — فقبوله هنا لا يخمن
           بلدا، والقصير الغامض يبقى مردودا كما كان.
           والصفر في أوله يرده: رمز الدولة لا يبدأ بصفر، فطوله وحده لا
           يجعل `0201552678658` رقما — وقبوله يرسل إلى ميتا ما ترده
           بـ`131026`. */
        if (strlen($d) >= 11 && strlen($d) <= 15 && $d[0] !== '0') return $d;

        return '';
    }

    /** الرقم كما يعرض للإنسان: `+966 50 123 4567`. */
    public function pretty($e164)
    {
        $d = preg_replace('/\D/', '', (string) $e164);
        if (preg_match('/^966(5[0-9])([0-9]{3})([0-9]{4})$/', $d, $m)) {
            return '+966 ' . $m[1] . ' ' . $m[2] . ' ' . $m[3];
        }
        return $d === '' ? '' : '+' . $d;
    }

    /**
     * الرقم ملثما — آخر ثلاث خانات وحدها.
     *
     * تعرض لمن ينتظر رمزا: يتعرف على رقمه ولا يقرأ رقم غيره لو فتحت
     * الشاشة على جهاز مشترك.
     */
    public function masked($e164)
    {
        $d = preg_replace('/\D/', '', (string) $e164);
        if (strlen($d) < 5) return '';
        return '+' . substr($d, 0, 3) . str_repeat('•', max(0, strlen($d) - 6)) . substr($d, -3);
    }

    /**
     * رقم صاحب حساب، جاهزا للإرسال. `''` إن لا رقم له أو لم يصلح.
     *
     * العمود `users.phone` يحفظ فيه المسجل `501234567` (بلا صفر ولا رمز
     * دولة — انظر `Login::tq_normalize_phone`)، ولوحة الإعدادات قد تحفظ
     * `05...`. فالتطبيع هنا لا هناك.
     */
    public function phone_of($user_id)
    {
        try {
            $u = $this->db->select('phone')->where('id', (int) $user_id)
                          ->get('users')->row_array();
        } catch (Throwable $e) {
            return '';
        }
        return $u ? $this->to_e164((string) $u['phone']) : '';
    }

    /* =====================================================================
       الإرسال
       ===================================================================== */

    /**
     * إشعار — يخرج بالقالب إن وجد، وبنص حر إن لم يوجد.
     *
     * @param string $to    رقم بأي صورة.
     * @param string $title عنوان الإشعار.
     * @param string $body  جسمه.
     * @param array  $opts  purpose · user_id
     * @return bool
     */
    public function send_notice($to, $title, $body, $opts = array())
    {
        $c     = $this->config();
        $title = $this->flatten($title, 200);
        $body  = $this->flatten($body);

        if (!isset($opts['purpose'])) $opts['purpose'] = 'notice';

        if ($c['tpl_notice'] !== '') {
            $params = ($c['tpl_notice_params'] >= 2)
                    ? array($title, $body)
                    : array($this->flatten($title . ' — ' . $body));
            return $this->send_template($to, $c['tpl_notice'], $c['tpl_notice_lang'], $params, $opts);
        }

        /* بلا قالب: نص حر. يصل لمن راسل الرقم في آخر أربع وعشرين ساعة
           وحده — وهو ما يسجل في السجل بصراحة، لئلا يظن المسؤول أن
           الإشعارات تعمل وهي لا تعمل إلا لهؤلاء. */
        return $this->send_text($to, $title . "\n" . $body, $opts);
    }

    /**
     * رمز تحقق.
     *
     * قالب المصادقة عند ميتا نصه ثابت لا يكتبه أحد: «*{{1}}* هو رمز
     * التحقق الخاص بك»، والبديل الوحيد هو الرمز. وإن كان فيه زر «نسخ
     * الرمز» فالرمز يمرر مرتين — مرة في الجسم ومرة في الزر — وهذا شرط
     * الواجهة لا اختيارنا.
     */
    public function send_otp($to, $code, $opts = array())
    {
        $c    = $this->config();
        $code = trim((string) $code);
        if (!isset($opts['purpose'])) $opts['purpose'] = 'otp';

        if ($c['tpl_otp'] !== '') {
            $extra = $c['tpl_otp_button']
                ? array(array(
                    'type'       => 'button',
                    'sub_type'   => 'url',
                    'index'      => '0',
                    'parameters' => array(array('type' => 'text', 'text' => $code)),
                  ))
                : array();

            return $this->send_template($to, $c['tpl_otp'], $c['tpl_otp_lang'],
                                        array($code), $opts, $extra);
        }

        return $this->send_text($to,
            'رمز التحقق في منصة تقدر: ' . $code . "\n"
            . 'صالح عشر دقائق، ولا تشاركه مع أحد.', $opts);
    }

    /**
     * رسالة بقالب معتمد — الطريق الذي تخرج منه إشعارات المنصة فعلا.
     *
     * @param string $to
     * @param string $name   اسم القالب كما اعتمد عند ميتا.
     * @param string $lang   رمز لغته (`ar` · `ar_SA` · `en_US`).
     * @param array  $params بدائل الجسم بالترتيب.
     * @param array  $opts   purpose · user_id
     * @param array  $extra  مكونات إضافية (زر · ترويسة).
     */
    public function send_template($to, $name, $lang, $params = array(), $opts = array(), $extra = array())
    {
        $components = array();

        $clean = array();
        foreach ((array) $params as $p) {
            $p = $this->flatten($p);
            if ($p !== '') $clean[] = $p;
        }
        if ($clean) {
            $body = array();
            foreach ($clean as $p) $body[] = array('type' => 'text', 'text' => $p);
            $components[] = array('type' => 'body', 'parameters' => $body);
        }
        foreach ((array) $extra as $comp) $components[] = $comp;

        $payload = array(
            'type'     => 'template',
            'template' => array(
                'name'     => (string) $name,
                'language' => array('code' => (string) $lang),
            ),
        );
        if ($components) $payload['template']['components'] = $components;

        $opts['template'] = (string) $name;
        return $this->dispatch($to, $payload, $opts);
    }

    /**
     * نص حر.
     *
     * **لا يعتمد عليه إشعار.** واتساب لا يوصل نصا كتبته إلا داخل نافذة
     * أربع وعشرين ساعة من آخر رسالة أرسلها العميل إلى الرقم، وكل ما
     * ترسله المنصة يبدأ من عندها. فهذا للفحص، ولمن راسلنا لتوه.
     */
    public function send_text($to, $text, $opts = array())
    {
        $text = $this->flatten($text, 4000, true);
        if ($text === '') return false;

        return $this->dispatch($to, array(
            'type' => 'text',
            'text' => array('preview_url' => false, 'body' => $text),
        ), $opts);
    }

    /**
     * المنفذ الوحيد: يطبع الرقم، ويفحص الضبط، ويرسل، ويسجل.
     *
     * ولا يرمي شيئا أبدا. من ينادي هذه الدالة قد يكون في ويبهوك دفع أو
     * في مسار إنشاء حساب، واستثناء يخرج من هنا يعني دفعة بلا تفعيل أو
     * صفحة بيضاء لمن سجل.
     */
    private function dispatch($to, $payload, $opts = array())
    {
        $this->last_error = '';
        $this->last_code  = '';

        $purpose = isset($opts['purpose'])  ? (string) $opts['purpose']  : 'notice';
        $tpl     = isset($opts['template']) ? (string) $opts['template'] : '';
        $uid     = isset($opts['user_id'])  ? (int)    $opts['user_id']  : 0;

        $e164 = $this->to_e164($to);
        if ($e164 === '') {
            $this->last_error = 'الرقم غير صالح.';
            $this->log($to, $purpose, $tpl, 'skipped', '', 'invalid_number',
                       'الرقم غير صالح أو ليس عليه رمز دولة.', $uid);
            return false;
        }

        if (!$this->configured()) {
            /* السبب يكتب مرة لكل طلب لا لكل رسالة — كما في البريد. */
            static $said = false;
            if (!$said) {
                log_message('info', 'TQ-WA: واتساب غير مضبوط — لم ترسل الرسائل. '
                    . 'الناقص: ' . implode('، ', $this->missing()));
                $said = true;
            }
            $this->last_error = 'واتساب غير مضبوط.';
            $this->log($e164, $purpose, $tpl, 'skipped', '', 'not_configured',
                       'واتساب غير مضبوط — الناقص: ' . implode('، ', $this->missing()), $uid);
            return false;
        }

        $c    = $this->config();
        $body = array_merge(array(
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $e164,
        ), $payload);

        try {
            $r = $this->api('POST', $c['phone_id'] . '/messages', $body);
        } catch (Throwable $e) {
            $this->last_error = $e->getMessage();
            log_message('error', 'TQ-WA: استثناء أثناء الإرسال — ' . $e->getMessage());
            $this->log($e164, $purpose, $tpl, 'failed', '', 'exception',
                       mb_substr($e->getMessage(), 0, 255), $uid);
            return false;
        }

        if (!empty($r['ok'])) {
            $wamid = '';
            if (isset($r['data']['messages'][0]['id'])) {
                $wamid = (string) $r['data']['messages'][0]['id'];
            }
            $this->log($e164, $purpose, $tpl, 'sent', $wamid, '', '', $uid);
            return true;
        }

        $this->last_error = $r['error'];
        $this->last_code  = (string) $r['wa_code'];
        log_message('error', 'TQ-WA: تعذر الإرسال إلى ' . $e164 . ' — ' . $r['error']);
        $this->log($e164, $purpose, $tpl, 'failed', '', (string) $r['wa_code'], $r['error'], $uid);
        return false;
    }

    /**
     * نص صالح لواتساب.
     *
     * بدائل القوالب عند ميتا **لا تقبل سطرا جديدا ولا فراغين متتاليين
     * ولا جدولة** — ترد `132007` وتصف الرفض بأنه «مخالفة سياسة الصيغة»،
     * وهو وصف لا يهدي إلى شيء. فالنص يسطح هنا قبل أن يخرج، ولا يترك
     * القرار للمستدعي.
     *
     * والنص الحر يستثنى من التسطيح: هو ليس بديل قالب، والأسطر فيه تقرأ.
     */
    private function flatten($text, $limit = 900, $keep_lines = false)
    {
        $t = strip_tags((string) $text);
        $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');

        if ($keep_lines) {
            $t = preg_replace('/[ \t\x{00A0}]+/u', ' ', $t);
            $t = preg_replace('/[ ]*\n[ ]*/u', "\n", $t);
            $t = preg_replace('/\n{3,}/u', "\n\n", $t);
        } else {
            $t = preg_replace('/[\r\n\t]+/u', ' ', $t);
            $t = preg_replace('/\s{2,}/u', ' ', $t);
        }
        $t = trim($t);

        return (mb_strlen($t) > $limit) ? (mb_substr($t, 0, $limit - 1) . '…') : $t;
    }

    /* =====================================================================
       نداء الواجهة
       ===================================================================== */

    /**
     * نداء `graph.facebook.com` — منفذ واحد لكل الطلبات.
     *
     * المهلة اثنتا عشرة ثانية: هذا النداء يقع أحيانا داخل ويبهوك دفع،
     * وتاب تعيد النداء إن تأخر الرد. وواتساب لا يستحق أن يؤخر تفعيل
     * اشتراك.
     */
    private function api($method, $path, $body = null, $query = array())
    {
        $c = $this->config();
        if ($c['token'] === '') {
            return array('ok' => false, 'code' => 0, 'wa_code' => '',
                         'error' => 'لا رمز وصول محفوظ.', 'body' => '', 'data' => array());
        }
        if (!function_exists('curl_init')) {
            return array('ok' => false, 'code' => 0, 'wa_code' => 'network',
                         'error' => 'إضافة cURL غير مثبتة على هذا الخادم.',
                         'body' => '', 'data' => array());
        }

        $url = self::GRAPH . $c['ver'] . '/' . ltrim($path, '/');
        if ($query) $url .= '?' . http_build_query($query);

        $ch = curl_init($url);
        $headers = array(
            'Authorization: Bearer ' . $c['token'],
            'Accept: application/json',
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
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
            return array('ok' => false, 'code' => 0, 'wa_code' => 'network',
                         'error' => ($cerr ?: 'تعذر الاتصال بخوادم واتساب.'),
                         'body' => '', 'data' => array());
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) $data = array();

        if ($code < 200 || $code >= 300 || isset($data['error'])) {
            $wc = isset($data['error']['code']) ? (string) $data['error']['code'] : '';
            return array('ok' => false, 'code' => $code, 'wa_code' => $wc,
                         'error' => $this->api_error($data, $code),
                         'body' => (string) $raw, 'data' => $data);
        }

        return array('ok' => true, 'code' => $code, 'wa_code' => '', 'error' => '',
                     'body' => (string) $raw, 'data' => $data);
    }

    /**
     * خطأ ميتا بعبارة تقول ما العمل.
     *
     * وهذا نصف قيمة هذا الملف: الواجهة ترد `{"error":{"code":131047,…}}`
     * ونصا إنجليزيا يصف الحال ولا يصف العلاج. ومسؤول يقرأ
     * «Re-engagement message» لا يعرف أن عليه أن يعتمد قالبا — يظن
     * الرمز خاطئا فيولد رمزا جديدا، فلا يتغير شيء.
     */
    private function api_error($data, $http)
    {
        $e    = (isset($data['error']) && is_array($data['error'])) ? $data['error'] : array();
        $code = isset($e['code']) ? (string) $e['code'] : '';
        $sub  = isset($e['error_subcode']) ? (string) $e['error_subcode'] : '';

        $said = '';
        if (isset($e['error_data']['details'])) $said = trim((string) $e['error_data']['details']);
        elseif (isset($e['message']))           $said = trim((string) $e['message']);

        $map = array(
            '0'      => 'رمز الوصول غير مقبول. أعد توليده من إعدادات التطبيق عند ميتا.',
            '190'    => 'رمز الوصول منتهي أو مسحوب. الرمز المؤقت يعيش أربعا وعشرين ساعة — '
                      . 'ولد رمزا دائما من «مستخدم النظام» في مدير الأعمال.',
            '100'    => 'بيان في الطلب لا تقبله الواجهة — راجع معرف رقم المرسل واسم القالب.',
            '10'     => 'التطبيق لا يملك صلاحية whatsapp_business_messaging. أضفها لمستخدم النظام.',
            '200'    => 'لا صلاحية على هذا الرقم. تأكد أن مستخدم النظام مخول على حساب الأعمال.',
            '368'    => 'الحساب موقوف مؤقتا لمخالفة سياسة. راجع «حالة الحساب» في مدير واتساب.',
            '131000' => 'خطأ عام عند ميتا. أعد المحاولة بعد قليل.',
            '131005' => 'لا صلاحية للوصول إلى هذا المورد.',
            '131008' => 'ينقص الطلب بيان مطلوب.',
            '131009' => 'قيمة في الطلب خارج المقبول.',
            '131016' => 'الخدمة غير متاحة مؤقتا عند ميتا.',
            '131021' => 'لا يمكن إرسال رسالة من الرقم إلى نفسه.',
            '131026' => 'الرقم لا يستقبل: إما ليس عليه واتساب، وإما رفض الاستقبال، '
                      . 'وإما كتب برمز دولة خاطئ.',
            '131030' => 'الرقم ليس في قائمة المستقبلين المسموح بها. حسابك ما زال في وضع '
                      . 'التطوير — أضف الرقم في قائمة «To» بشاشة إعداد واتساب، أو انقل '
                      . 'التطبيق إلى الوضع الحي (Live).',
            '131031' => 'حساب الأعمال مقفل. راجع مدير الأعمال عند ميتا.',
            '131047' => 'مضى أكثر من أربع وعشرين ساعة على آخر رسالة من هذا الرقم إليك، '
                      . 'فلا يقبل نصا حرا. وهذه رسالة تبدأ منك، ولا تخرج إلا بقالب معتمد '
                      . '— اكتب اسم القالب في الإعدادات.',
            '131049' => 'منعت ميتا هذه الرسالة حفاظا على تجربة المستلم.',
            '131051' => 'نوع الرسالة غير مدعوم.',
            '131052' => 'تعذر تنزيل مرفق الرسالة.',
            '131053' => 'تعذر رفع مرفق الرسالة.',
            '131056' => 'أكثرت الإرسال إلى هذا الرقم في وقت قصير. أمهل قليلا.',
            '132000' => 'عدد بدائل القالب لا يطابق ما اعتمد. راجع عدد {{n}} في القالب، '
                      . 'وخانتي «عدد البدائل» و«فيه زر نسخ الرمز» في الإعدادات.',
            '132001' => 'لا قالب بهذا الاسم في هذه اللغة. راجع الاسم حرفا بحرف ورمز اللغة '
                      . '(ar غير ar_SA).',
            '132005' => 'النص بعد تركيب البدائل أطول مما يقبله القالب.',
            '132007' => 'نص البديل يخالف صيغة القوالب: لا سطر جديد ولا فراغان متتاليان.',
            '132012' => 'صيغة البديل لا تطابق ما عرف في القالب.',
            '132015' => 'القالب معطل عند ميتا (كثرت شكاوى المستلمين منه).',
            '132016' => 'القالب موقوف مؤقتا لجودة منخفضة.',
            '132068' => 'التدفق المرتبط بالقالب معطل.',
            '133004' => 'الخدمة تحت الصيانة عند ميتا.',
            '133010' => 'رقم المرسل غير مسجل في واتساب. أكمل تسجيله في مدير واتساب.',
            '130429' => 'تجاوزت حد الإرسال في هذه اللحظة. أمهل ثم أعد.',
            '80007'  => 'تجاوزت حد الإرسال لهذا الحساب.',
            'network' => 'تعذر الوصول إلى خوادم واتساب من هذا الخادم.',
        );

        if ($code !== '' && isset($map[$code])) {
            return $map[$code] . ' (' . $code . ($sub !== '' ? '/' . $sub : '') . ')'
                 . ($said !== '' ? ' — ' . $said : '');
        }
        if ($said !== '') {
            return $said . ($code !== '' ? ' (' . $code . ')' : '');
        }
        return 'ردت الواجهة برمز ' . (int) $http . '.';
    }

    /* =====================================================================
       الفحص — هل يعمل هذا الضبط قبل أن نعتمد عليه؟

       زر «أرسل رسالة فحص» يجيب عن سؤال واحد: هل قبلت ميتا هذا الطلب؟
       وهو نصف الجواب. والنصف الثاني — هل القالب معتمد؟ وهل الحساب ما
       زال في وضع التطوير؟ وهل جودة الرقم خضراء؟ — لا يظهر إلا حين تفشل
       رسالة حقيقية على طالب دفع وينتظر تأكيدا.

       فهذه الدالة تسأل ميتا عن الثلاثة **قبل** أن يقع ذلك.
       ===================================================================== */

    public function diagnose()
    {
        $c   = $this->config();
        $out = array();

        /* ١ — الضبط أصلا */
        if (!$this->configured()) {
            $out[] = $this->check('config', 'بيانات الاتصال', 'fail',
                'ناقص: ' . implode('، ', $this->missing()) . '.',
                'أكمل الحقول في البطاقة المجاورة. لا يخرج شيء قبلها، '
                . 'وتبقى الإشعارات في المنصة والبريد كما هي.');
            return $out;
        }
        $out[] = $this->check('config', 'بيانات الاتصال', 'ok',
            'الرمز ومعرف الرقم محفوظان، والواجهة ' . $c['ver'] . '.');

        /* ٢ — الرمز والرقم: نداء واحد يجيب عن الاثنين */
        $p = $this->api('GET', $c['phone_id'], null, array(
            'fields' => 'display_phone_number,verified_name,quality_rating,'
                      . 'code_verification_status,platform_type',
        ));

        if (empty($p['ok'])) {
            $out[] = $this->check('token', 'رمز الوصول ورقم المرسل', 'fail',
                $p['error'],
                'رمز منتهي أو معرف رقم خاطئ. أعدهما من شاشة إعداد واتساب في تطبيقك عند ميتا.');
            return $out;   /* بلا رمز صالح لا معنى لبقية الفحوص */
        }

        $d    = $p['data'];
        $num  = isset($d['display_phone_number']) ? (string) $d['display_phone_number'] : '';
        $name = isset($d['verified_name']) ? (string) $d['verified_name'] : '';
        $out[] = $this->check('token', 'رمز الوصول ورقم المرسل', 'ok',
            'تجيب ميتا: ترسل من ' . ($num ?: 'الرقم المحفوظ')
            . ($name !== '' ? ' باسم «' . $name . '»' : '') . '.');

        /* ٣ — جودة الرقم: هي التي تحدد كم رسالة تخرج في اليوم */
        $q = strtoupper(isset($d['quality_rating']) ? (string) $d['quality_rating'] : '');
        if ($q === 'GREEN') {
            $out[] = $this->check('quality', 'جودة الرقم', 'ok',
                'خضراء — لا قيود زائدة على الإرسال.');
        } elseif ($q === 'YELLOW') {
            $out[] = $this->check('quality', 'جودة الرقم', 'warn',
                'صفراء — بلغ مستلمون عن رسائل من هذا الرقم.',
                'قلل ما يرسل بلا طلب. الصفراء تسبق الحمراء، والحمراء تخفض حد الإرسال اليومي.');
        } elseif ($q === 'RED') {
            $out[] = $this->check('quality', 'جودة الرقم', 'fail',
                'حمراء — الرقم مهدد بخفض حد إرساله أو إيقافه.',
                'أوقف كل ما ليس ضروريا، وراجع «جودة الرقم» في مدير واتساب.');
        } else {
            $out[] = $this->check('quality', 'جودة الرقم', 'unknown',
                'لم تعطها ميتا بعد — تظهر بعد أن يرسل الرقم رسائل حقيقية.');
        }

        /* ٤ — التوثيق: رقم لم يكتمل تسجيله لا يرسل شيئا */
        $cv = strtoupper(isset($d['code_verification_status']) ? (string) $d['code_verification_status'] : '');
        if ($cv !== '' && $cv !== 'VERIFIED') {
            $out[] = $this->check('verify', 'تسجيل الرقم', 'warn',
                'حال التوثيق عند ميتا: ' . $cv . '.',
                'أكمل تسجيل الرقم في مدير واتساب — رقم غير مسجل يرد 133010.');
        }

        /* ٥ — القوالب: أهم بند بعد الرمز.
           بلا قالب لا يخرج إشعار واحد إلى من لم يراسلنا في آخر يوم،
           وهم كل الناس. */
        foreach ($this->template_checks() as $t) $out[] = $t;

        /* ٦ — من يستقبل فعلا: أرقام أهل المنصة */
        $out[] = $this->reach_check();

        return $out;
    }

    /** فحص القالبين: موجود؟ بهذه اللغة؟ معتمد؟ وبكم بديل؟ */
    private function template_checks()
    {
        $c   = $this->config();
        $out = array();

        $wanted = array(
            array('tpl_notice', 'قالب الإشعارات', $c['tpl_notice'], $c['tpl_notice_lang'],
                  $c['tpl_notice_params'],
                  'وبلاه لا يصل خبر دفعة إلى أحد، إلا من راسل الرقم في آخر أربع وعشرين '
                  . 'ساعة — وهذا لا يقع.'),
            array('tpl_otp', 'قالب رمز التحقق', $c['tpl_otp'], $c['tpl_otp_lang'], 1,
                  'وبلاه لا يخرج رمز بواتساب، ويبقى البريد وحده — فمن اختار واتساب '
                  . 'لا يصله شيء.'),
        );

        if ($c['waba_id'] === '') {
            foreach ($wanted as $w) {
                $out[] = ($w[2] === '')
                    ? $this->check($w[0], $w[1], 'fail', 'لا اسم قالب محفوظ. ' . $w[5],
                        'اعتمد القالب عند ميتا ثم اكتب اسمه في البطاقة المجاورة. '
                        . 'والدليل أسفل الشاشة يعطيك نصه جاهزا.')
                    : $this->check($w[0], $w[1], 'unknown',
                        'محفوظ باسم «' . $w[2] . '» ولغة ' . $w[3] . ' — ولا يتحقق من '
                        . 'اعتماده بلا «معرف حساب الأعمال».',
                        'أضف معرف حساب الأعمال (WABA ID) ليفحص القالب هنا، بدل أن '
                        . 'تكتشف رفضه عند أول رسالة حقيقية.');
            }
            return $out;
        }

        $r = $this->api('GET', $c['waba_id'] . '/message_templates', null,
                        array('fields' => 'name,status,language,components', 'limit' => 250));

        if (empty($r['ok'])) {
            foreach ($wanted as $w) {
                $out[] = $this->check($w[0], $w[1], 'unknown',
                    'تعذر سؤال ميتا عن القوالب: ' . $r['error']);
            }
            return $out;
        }

        $rows = (isset($r['data']['data']) && is_array($r['data']['data'])) ? $r['data']['data'] : array();

        foreach ($wanted as $w) {
            list($key, $label, $name, $lang, $need, $why) = $w;

            if ($name === '') {
                $out[] = $this->check($key, $label, 'fail', 'لا اسم قالب محفوظ. ' . $why,
                    'الدليل أسفل الشاشة يعطيك نص القالب جاهزا للنسخ.');
                continue;
            }

            $same_name = array();
            foreach ($rows as $t) {
                if (isset($t['name']) && strcasecmp((string) $t['name'], $name) === 0) {
                    $same_name[] = $t;
                }
            }

            if (!$same_name) {
                $out[] = $this->check($key, $label, 'fail',
                    'لا قالب باسم «' . $name . '» في هذا الحساب.',
                    'راجع الاسم حرفا بحرف — أسماء القوالب حروف لاتينية صغيرة '
                    . 'وشرطات سفلية فقط.');
                continue;
            }

            $hit = null;
            foreach ($same_name as $t) {
                if (isset($t['language']) && strcasecmp((string) $t['language'], $lang) === 0) {
                    $hit = $t;
                    break;
                }
            }

            if (!$hit) {
                $langs = array();
                foreach ($same_name as $t) {
                    if (isset($t['language'])) $langs[] = (string) $t['language'];
                }
                $out[] = $this->check($key, $label, 'fail',
                    'القالب موجود ولكن ليس بلغة «' . $lang . '» — الموجود: '
                    . implode('، ', array_unique($langs)) . '.',
                    'صحح رمز اللغة في البطاقة المجاورة ليطابق ما اعتمد.');
                continue;
            }

            $status = strtoupper(isset($hit['status']) ? (string) $hit['status'] : '');
            if ($status !== 'APPROVED') {
                $out[] = $this->check($key, $label, ($status === 'PENDING' ? 'warn' : 'fail'),
                    'حال القالب عند ميتا: ' . $status . '.',
                    $status === 'PENDING'
                        ? 'المراجعة تأخذ من دقائق إلى ساعات، ولا يرسل قبل الاعتماد.'
                        : 'رفض القالب. عدله وأعد رفعه من مدير القوالب.');
                continue;
            }

            /* عدد البدائل: أكثر ما يعطب بعد الاعتماد. */
            $found  = $this->count_params($hit);
            $button = $this->has_url_button($hit);
            $notes  = array();

            if ($found >= 0 && $found !== (int) $need) {
                $notes[] = 'القالب يطلب ' . $found . ' بديلا والإعدادات ترسل ' . (int) $need;
            }
            if ($key === 'tpl_otp' && $button !== null && $button !== $c['tpl_otp_button']) {
                $notes[] = $button
                    ? 'القالب فيه زر نسخ الرمز والإعدادات تقول لا'
                    : 'الإعدادات تقول فيه زر نسخ الرمز وليس فيه';
            }

            if ($notes) {
                $out[] = $this->check($key, $label, 'fail',
                    'معتمد — ولكن ' . implode('، و', $notes) . '. وهذا يرد 132000 عند كل رسالة.',
                    'صحح الإعدادات لتطابق القالب، أو عدل القالب.');
            } else {
                $out[] = $this->check($key, $label, 'ok',
                    'معتمد بلغة ' . $lang . '، وبدائله تطابق ما ترسله المنصة.');
            }
        }

        return $out;
    }

    /** كم `{{n}}` في جسم القالب؟ `-1` إن تعذر العد. */
    private function count_params($tpl)
    {
        if (empty($tpl['components']) || !is_array($tpl['components'])) return -1;

        foreach ($tpl['components'] as $comp) {
            if (strtoupper(isset($comp['type']) ? (string) $comp['type'] : '') !== 'BODY') continue;

            /* قوالب المصادقة لا تحمل نصا: ميتا تكتبه، وبديلها واحد أبدا. */
            $text = isset($comp['text']) ? (string) $comp['text'] : '';
            if ($text === '') {
                return isset($comp['add_security_recommendation']) ? 1 : -1;
            }
            preg_match_all('/\{\{\s*[0-9]+\s*\}\}/u', $text, $m);
            return count(array_unique($m[0]));
        }
        return -1;
    }

    /** هل في القالب زر يأخذ بديلا (نسخ الرمز · رابط)؟ `null` إن لم يعرف. */
    private function has_url_button($tpl)
    {
        if (empty($tpl['components']) || !is_array($tpl['components'])) return null;

        foreach ($tpl['components'] as $comp) {
            if (strtoupper(isset($comp['type']) ? (string) $comp['type'] : '') !== 'BUTTONS') continue;

            $btns = isset($comp['buttons']) ? (array) $comp['buttons'] : array();
            foreach ($btns as $b) {
                $t = strtoupper(isset($b['type']) ? (string) $b['type'] : '');
                if ($t === 'URL' || $t === 'OTP' || $t === 'COPY_CODE') return true;
            }
            return false;
        }
        return false;
    }

    /**
     * كم من أهل المنصة يستطيع أن يستقبل أصلا؟
     *
     * سؤال لا تجيب عنه ميتا: الضبط قد يكون سليما تماما وحسابات المنصة
     * بلا أرقام — فتخرج الإشعارات إلى لا أحد. والطلاب أكثرهم كذلك،
     * لأن نموذج التسجيل لا يطلب جوالا من الطالب أصلا.
     */
    private function reach_check()
    {
        try {
            $row = $this->db->query(
                'SELECT COUNT(*) AS n,
                        SUM(CASE WHEN `phone` IS NULL OR TRIM(`phone`) = "" THEN 0 ELSE 1 END) AS withp
                   FROM `users` WHERE `status` = 1')->row_array();
        } catch (Throwable $e) {
            return $this->check('reach', 'من يستقبل فعلا', 'unknown', 'تعذر عد الحسابات.');
        }

        $n = isset($row['n']) ? (int) $row['n'] : 0;
        $w = isset($row['withp']) ? (int) $row['withp'] : 0;

        if ($n === 0) {
            return $this->check('reach', 'من يستقبل فعلا', 'unknown', 'لا حسابات فعالة بعد.');
        }
        if ($w === 0) {
            return $this->check('reach', 'من يستقبل فعلا', 'fail',
                'لا حساب واحد من ' . $n . ' عليه رقم جوال — فلا يصل واتساب إلى أحد.',
                'المعلمون وأولياء الأمور يكتبون أرقامهم عند التسجيل، والطالب لا يسأل '
                . 'عن رقمه ويضيفه من «إعدادات حسابي».');
        }

        $pct = (int) round($w * 100 / $n);
        return $this->check('reach', 'من يستقبل فعلا', ($pct >= 50 ? 'ok' : 'warn'),
            $w . ' من ' . $n . ' حسابا عليه رقم جوال (' . $pct . '٪). ومن لا رقم له '
            . 'يبقى إشعاره في المنصة والبريد كما هو.',
            $pct >= 50 ? '' :
            'الطالب لا يسأل عن رقمه في التسجيل — فإشعارات دفعه تصل بالبريد وحده '
            . 'حتى يضيف رقما من «إعدادات حسابي».');
    }

    private function check($key, $label, $state, $says, $fix = '', $record = '')
    {
        return compact('key', 'label', 'state', 'says', 'fix', 'record');
    }

    /* =====================================================================
       السجل
       ===================================================================== */

    /**
     * جدول السجل — ينشأ وقت التشغيل لا بهجرة، كما `payment_attempts`.
     *
     * ولا يحفظ نص الرسالة: رمز التحقق سر، وسجل يحفظه يجعل كل من يفتح
     * اللوحة يقرأ رموز الناس. والغرض يكفي لتشخيص «لماذا لم يصل؟».
     */
    public function ensure_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        try {
            $this->db->query(
                'CREATE TABLE IF NOT EXISTS `tq_wa_log` (
                    `id`       int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `user_id`  int(10) unsigned NOT NULL DEFAULT 0,
                    `to_phone` varchar(24)  NOT NULL DEFAULT "",
                    `purpose`  varchar(24)  NOT NULL DEFAULT "notice",
                    `template` varchar(64)  DEFAULT NULL,
                    `status`   varchar(12)  NOT NULL DEFAULT "sent",
                    `wamid`    varchar(96)  DEFAULT NULL,
                    `code`     varchar(24)  DEFAULT NULL,
                    `error`    varchar(255) DEFAULT NULL,
                    `at`       datetime     DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `k_at` (`at`),
                    KEY `k_status` (`status`),
                    KEY `k_user` (`user_id`)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-WA: تعذر إنشاء tq_wa_log — ' . $e->getMessage());
        }
    }

    private function log($to, $purpose, $tpl, $status, $wamid, $code, $error, $user_id = 0)
    {
        try {
            $this->ensure_schema();
            $this->db->insert('tq_wa_log', array(
                'user_id'  => (int) $user_id,
                'to_phone' => mb_substr((string) $to, 0, 24),
                'purpose'  => mb_substr((string) $purpose, 0, 24),
                'template' => ($tpl === ''   ? null : mb_substr((string) $tpl, 0, 64)),
                'status'   => (string) $status,
                'wamid'    => ($wamid === '' ? null : mb_substr((string) $wamid, 0, 96)),
                'code'     => ($code === ''  ? null : mb_substr((string) $code, 0, 24)),
                'error'    => ($error === '' ? null : mb_substr((string) $error, 0, 255)),
                'at'       => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            /* سجل يفشل لا يمنع رسالة نجحت. */
            log_message('error', 'TQ-WA log: ' . $e->getMessage());
        }
    }

    /** آخر ما جرى — «هل وصل؟» يجاب من صف لا من ظن. */
    public function recent($limit = 25)
    {
        $this->ensure_schema();
        try {
            return $this->db->select('l.*,'
                    . ' TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) AS user_name', false)
                ->from('tq_wa_log l')
                ->join('users u', 'u.id = l.user_id', 'left')
                ->order_by('l.id', 'DESC')->limit((int) $limit)
                ->get()->result_array();
        } catch (Throwable $e) {
            return array();
        }
    }

    /** عد بكل حال — سطر الحال في أعلى الشاشة. */
    public function totals()
    {
        $this->ensure_schema();
        $out = array('sent' => 0, 'failed' => 0, 'skipped' => 0, 'today' => 0);
        try {
            $rows = $this->db->select('status, COUNT(*) AS n', false)
                             ->group_by('status')->get('tq_wa_log')->result_array();
            foreach ($rows as $r) $out[$r['status']] = (int) $r['n'];

            $out['today'] = (int) $this->db->where('at >=', date('Y-m-d 00:00:00'))
                                           ->where('status', 'sent')
                                           ->count_all_results('tq_wa_log');
        } catch (Throwable $e) {
            /* جدول لم يستعمل بعد: رقم ناقص أهون من شاشة بيضاء. */
        }
        return $out;
    }
}
