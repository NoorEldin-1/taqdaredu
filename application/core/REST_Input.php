<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * يمرر توكن CSRF من جسم JSON إلى `$_POST` قبل أن يفحصه CodeIgniter.
 *
 * ── العطل ────────────────────────────────────────────────────────────────
 * `CI_Security::csrf_verify()` يقرأ التوكن من `$_POST` وحده. وPHP لا يملأ
 * `$_POST` إلا لنموذج مرمز (`form-urlencoded` أو `multipart`) — أما طلب
 * بـ`Content-Type: application/json` فجسمه يبقى في `php://input` و`$_POST`
 * فارغ. فكل نداء كتابة من واجهة تقدر يرد **٤٠٣** مهما حمل من توكن:
 *
 *   • `taqdar_gate/review_answer`  — شاشة المراجعة كلها: كل إجابة تفشل.
 *   • `taqdar_gate/review_submit` و`lesson_progress` — مشغل الدرس.
 *
 * والغلاف في `includes_bottom.php` كان يحقن التوكن **داخل** JSON بحسن نية
 * (`o[NAME] = HASH`) — وهو موضع لا يقرؤه CI أصلا. فالتوكن يرسل ولا يصل،
 * والمستخدم يرى «تعذر إتمام الطلب» بلا سبب ظاهر في الكونسول ولا في السجل.
 *
 * ── الحل ─────────────────────────────────────────────────────────────────
 * البناء هنا يسبق `_sanitize_globals()` التي تنادي الفحص، فيرفع التوكن من
 * جسم JSON أو من ترويسة `X-CSRF-Token` إلى `$_POST` قبل أن يسأل عنه.
 *
 * ولا يضعف هذا الحماية: التوكن لا يقرأ من موقع آخر (كوكيه `HttpOnly`
 * ونسخته في HTML لا تقرأ عبر الأصول)، و`application/json` لا يرسل من أصل
 * مختلف بلا preflight يرفضه المتصفح. فقبوله من جسم JSON مساو تماما لقبوله
 * من حقل نموذج، ورفض الطلب بلا توكن يبقى كما هو.
 *
 * والاسم `REST_` لا `MY_` — `subclass_prefix` في هذا التثبيت `REST_`،
 * وملف `MY_Input.php` كان سيتجاهل صامتا كما نبه `REST_Output`.
 */
class REST_Input extends CI_Input
{
    public function __construct()
    {
        $this->tq_lift_csrf_token();
        parent::__construct();
    }

    /** يضع التوكن في `$_POST` إن جاء بجسم JSON أو بترويسة، ولم يكن فيه. */
    private function tq_lift_csrf_token()
    {
        if (config_item('csrf_protection') !== TRUE) return;
        if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') return;

        $name = (string) config_item('csrf_token_name');
        if ($name === '' || isset($_POST[$name])) return;

        // ١ · الترويسة — الأوضح، ولا تلوث الجسم
        foreach (array('HTTP_X_CSRF_TOKEN', 'HTTP_X_CSRF_TOKEN_NAME') as $h) {
            if (!empty($_SERVER[$h]) && is_string($_SERVER[$h])) {
                $_POST[$name] = $_SERVER[$h];
                return;
            }
        }

        // ٢ · جسم JSON — وهو ما يرسله غلاف fetch اليوم
        $type = isset($_SERVER['CONTENT_TYPE']) ? (string) $_SERVER['CONTENT_TYPE'] : '';
        if (stripos($type, 'json') === false) return;

        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) return;

        /* الجسم يقرأ مرة واحدة من `php://input` وهو قابل لإعادة القراءة في
           PHP 5.6+، فقراءته هنا لا تحرم المتحكم منه. */
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json[$name]) && is_string($json[$name])) {
            $_POST[$name] = $json[$name];
        }
    }
}
