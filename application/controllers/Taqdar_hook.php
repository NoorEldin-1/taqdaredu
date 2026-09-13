<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أبواب النداءات الواردة من خارج — TQ-META-LEADS.
 *
 * بابان لا أكثر، وكلاهما على المسار نفسه `webhook/meta/leads`:
 *
 * `GET`  — توثق ميتا من الباب مرة واحدة عند تسجيله: ترسل رمز تحقق
 *          وتحديا، فنرد التحدي كما جاء إن طابق الرمز المحفوظ. وبلا ذلك
 *          لا يسجل الاشتراك أصلا فلا يصل نداء واحد.
 * `POST` — العميل المحتمل الجديد. والقرار كله في
 *          `Taqdar_lead_model::receive()` — هنا فحص توقيع ورد نص.
 *
 * ولماذا `webhook/` لا `pay/` ولا `taqdar_admin/`: `csrf_exclude_uris`
 * في [config.php](../config/config.php) يستثني هذه البادئة، ونداء ميتا
 * يأتي من خادمها بلا كعكة ولا رمز حماية — فبادئة أخرى تعني 403 على كل
 * نداء، وحملة تجلب مئة عميل لا يصل منهم واحد ولا يظهر سبب. وهي علة
 * ويبهوك تاب نفسها (TQ-GATE-CSRF بوجه آخر).
 *
 * **ولا جلسة تحمل هنا.** نداء ميتا بلا كعكة، وتحميل الجلسة له يعني صفا
 * جديدا في `ci_sessions` عند كل عميل — سجلا ينمو بلا قارئ. وهو ما
 * يفعله `Taqdar_pay` في ويبهوك تاب حرفا.
 */
class Taqdar_hook extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->model('taqdar_lead_model', 'leads');
    }

    /* =====================================================================
       عملاء ميتا المحتملون
       ===================================================================== */

    public function meta_leads()
    {
        if ($this->input->method(true) === 'GET') {
            $this->verify();
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            $this->plain(405, 'method not allowed');
            return;
        }
        $this->receive();
    }

    /* =====================================================================
       التوثق — مرة واحدة عند التسجيل
       ===================================================================== */

    /**
     * يرد `hub.challenge` كما جاء متى طابق `hub.verify_token` المحفوظ.
     *
     * TQ-HUB-DOT — و**المعاملات تقرأ من سلسلة الاستعلام لا من `$_GET`**:
     * أسماؤها عند ميتا فيها نقطة (`hub.mode`)، وPHP يبدل كل نقطة في
     * اسم معامل بشرطة سفلية وهو يبني `$_GET` — فـ`$this->input->get('hub.mode')`
     * ترد فراغا **دائما** على طلب صحيح تماما، فيرد الباب 403 على ميتا
     * ولا يسجل الاشتراك، ولا شيء في الشاشة يقول إلا «Verification failed».
     * فتقرأ من `QUERY_STRING` أولا، وتقبل الصورة المبدلة كذلك — بعض
     * الوسائط تعيد الطلب بأسماء مسوية.
     */
    private function verify()
    {
        $parsed = array();
        parse_str((string) (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''), $parsed);

        $pick = function ($name) use ($parsed) {
            $dot = 'hub.' . $name;
            $und = 'hub_' . $name;
            if (isset($parsed[$dot]))  return (string) $parsed[$dot];
            if (isset($parsed[$und]))  return (string) $parsed[$und];
            if (isset($_GET[$und]))    return (string) $_GET[$und];
            if (isset($_GET[$dot]))    return (string) $_GET[$dot];
            return '';
        };

        $mode      = $pick('mode');
        $token     = $pick('verify_token');
        $challenge = $pick('challenge');

        if ($mode !== 'subscribe' || $challenge === '') {
            /* زائر فتح الرابط بمتصفحه: لا شيء يقال له إلا أن الباب باب
               آلة. ولا 404 — من يشخص الربط يحتاج أن يعرف أن المسار قائم. */
            $this->plain(200, 'meta leads webhook');
            return;
        }

        if (!$this->leads->verify_token_ok($token)) {
            $this->leads->log_rejected('رمز تحقق لا يطابق في نداء التسجيل.', $this->input->ip_address());
            log_message('error', 'TQ-LEADS-HOOK: verify token mismatch');
            $this->plain(403, 'bad verify token');
            return;
        }

        /* والتحدي يرد **نصا عاريا** لا JSON ولا صفحة: ميتا تقارن الجسم
           كله بما أرسلت، فسطر واحد زائد يفشل التسجيل. */
        $this->plain(200, $challenge);
    }

    /* =====================================================================
       الاستقبال
       ===================================================================== */

    /**
     * POST webhook/meta/leads — عميل محتمل جديد (أو أكثر).
     *
     * والترتيب: توقيع، فتفكيك، فتقييد، ثم رد. والرد **200 دائما** بعد
     * قبول التوقيع وإن فشل الجلب: ميتا تعيد النداء على كل رد ليس 200
     * بتوقيت عشوائي ثم تكف — وإعادتنا من `taqdar_cron reconcile` منتظمة
     * ومعها سبب كل تعثر في `tq_lead_hooks`. فالفشل يقيد ولا يبتلع، ولا
     * يترك ميتا تقرر متى يعاد.
     */
    private function receive()
    {
        $raw = (string) $this->input->raw_input_stream;
        $ip  = (string) $this->input->ip_address();

        /* التوقيع أولا، وقبل التفكيك: جسم ضخم يرسله من يشاء لا يفكك
           أصلا إن لم يكن موقعا. */
        $sig = $this->leads->signature_state(
            $raw, $this->input->get_request_header('X-Hub-Signature-256', true)
        );

        if ($sig === 'bad') {
            /* وهنا يرد ولا يسجل وحده — خلافا لتاب: هناك القرار على جلب
               مستقل بمفتاح سري، وهنا الجلب يقع بمعرف يأتي في الجسم.
               ومن يرسل أجساما مخترعة يجعلنا نطرق ميتا عنها. */
            $this->leads->log_rejected('توقيع X-Hub-Signature-256 لا يطابق سر التطبيق.', $ip);
            log_message('error', 'TQ-LEADS-HOOK: signature mismatch');
            $this->plain(403, 'bad signature');
            return;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $this->leads->log_rejected('جسم وارد ليس JSON صالحا.', $ip);
            $this->plain(400, 'bad body');
            return;
        }

        $r = $this->leads->receive($payload, $sig, $ip);

        /* ورد الباب يقول ما جرى بالرقم: من يفتح سجل خادم ميتا («Recent
           Error Responses» في شاشة الويبهوك) يقرأ هذا السطر، وهو أول
           ما ينظر فيه حين يقال «العملاء لا يصلون». */
        $this->plain(200, 'ok leads=' . (int) $r['leads']
            . ' stored=' . (int) $r['stored']
            . ' dup=' . (int) $r['duplicate']
            . ' failed=' . (int) $r['failed']
            . ' ignored=' . (int) $r['ignored']);
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    private function plain($code, $text)
    {
        $this->output->set_status_header($code)
                     ->set_content_type('text/plain', 'utf-8')
                     ->set_output((string) $text);
    }
}
