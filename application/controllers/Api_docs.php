<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * وثائق واجهة البرمجة — ثلاثة مخرجات من مصدر واحد.
 *
 *   GET /api/docs                  صفحة الوثائق
 *   GET /api/docs/openapi.json     مواصفة OpenAPI 3.1
 *   GET /api/docs/collection.json  مجموعة Postman 2.1
 *
 * والمصدر [taqdar_api_spec.php](../config/taqdar_api_spec.php) وحده. ولا
 * يبنى شيء منها **وقت النشر**: `deploy.sh` سحب `git reset --hard` بلا
 * خطوة بناء، فمخرج مولد ومحفوظ في المستودع يفترق عن مصدره عند أول تعديل
 * ينسى صاحبه أن يعيد التوليد. والتوليد وقت الطلب لا يفترق أبدا.
 *
 * والصفحة **عامة بلا رمز**: وثيقة تحتاج مفتاحا لا يقرؤها من يبني العميل.
 * ولا تحمل سرا: أسماء نقاط وأشكال ردود، وهي ما ينشره كل من ينشر واجهة.
 */
class Api_docs extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('url');
    }

    /** المواصفة — تقرأ مرة في الطلب. */
    private function spec()
    {
        static $spec = null;
        if ($spec === null) $spec = include APPPATH . 'config/taqdar_api_spec.php';
        return $spec;
    }

    /**
     * الصفحة.
     *
     * `.htaccess` يمنع `application/` كلها، فالمواصفة لا تخدم ملفا — وهذا
     * هو مسارها الوحيد إلى الخارج.
     */
    public function index()
    {
        $this->load->view('api/docs', array('spec' => $this->spec()));
    }

    /** المواصفة كما هي — لمولدات العملاء (openapi-generator وأخواتها). */
    public function openapi()
    {
        $this->json($this->spec(), 'taqdar-openapi.json');
    }

    /** مجموعة Postman — تبنى من المواصفة نفسها. */
    public function collection()
    {
        $this->json($this->postman($this->spec()), 'taqdar-api.postman_collection.json');
    }

    /**
     * إخراج JSON.
     *
     * `?download=1` وحدها تضيف `Content-Disposition`: بدون ذلك يستحيل فتح
     * الملف في تبويب لقراءته، ومطور يريد أن يقرأ لا أن يحفظ.
     *
     * و`Access-Control-Allow-Origin: *` لأن أدوات التوثيق تجلب المواصفة
     * من نطاقها هي — وهي بيان عام لا سر.
     */
    private function json($payload, $filename)
    {
        $body = json_encode($payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $this->output
             ->set_content_type('application/json', 'utf-8')
             ->set_header('Access-Control-Allow-Origin: *')
             ->set_header('X-Content-Type-Options: nosniff')
             ->set_header('Cache-Control: public, max-age=300')
             ->set_header('ETag: "' . md5($body) . '"');

        if ($this->input->get('download')) {
            $this->output->set_header('Content-Disposition: attachment; filename="' . $filename . '"');
        }

        $this->output->set_output($body);
    }

    /* ================================================================
       مولد Postman
       ================================================================ */

    /**
     * OpenAPI 3.1 إلى Postman Collection v2.1.
     *
     * ثلاثة قرارات تجعل المجموعة تعمل بلا ضبط يدوي:
     *
     * ١ · **متغيرات لا قيم ثابتة.** `{{base_url}}` و`{{access_token}}`،
     *     فالتبديل بين الإنتاج والمحلي تعديل حقل واحد لا بحث واستبدال في
     *     ثلاثين طلبا.
     *
     * ٢ · **الدخول يحفظ رمزه بنفسه.** سكربت اختبار على نقطة الدخول يكتب
     *     الرمزين في متغيرات المجموعة. وبدونه ينسخ المطور الرمز بيده بعد
     *     كل انتهاء صلاحية — كل ربع ساعة.
     *
     * ٣ · **التجديد يدور المتغير كذلك.** التدوير يبطل الرمز القديم، فمن
     *     جدد ولم يحدث متغيره وجد بقية الطلبات ترد 401 ولم يعرف لماذا.
     */
    private function postman($spec)
    {
        $folders = array();

        foreach ($spec['paths'] as $path => $ops) {
            foreach ($ops as $method => $op) {
                $tag = isset($op['tags'][0]) ? $op['tags'][0] : 'Other';
                if (!isset($folders[$tag])) $folders[$tag] = array();
                $folders[$tag][] = $this->postman_item($path, $method, $op);
            }
        }

        $items = array();
        foreach ($spec['tags'] as $t) {
            if (empty($folders[$t['name']])) continue;
            $items[] = array(
                'name'        => $t['name'],
                'description' => $t['description'],
                'item'        => $folders[$t['name']],
            );
            unset($folders[$t['name']]);
        }
        foreach ($folders as $name => $reqs) {
            $items[] = array('name' => $name, 'item' => $reqs);
        }

        return array(
            'info' => array(
                'name'        => $spec['info']['title'] . ' — v' . $spec['info']['version'],
                '_postman_id' => md5('taqdar-api-v1'),
                'description' => $this->postman_intro($spec),
                'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ),
            'auth' => array(
                'type'   => 'bearer',
                'bearer' => array(array('key' => 'token', 'value' => '{{access_token}}', 'type' => 'string')),
            ),
            'variable' => array(
                array('key' => 'base_url', 'value' => rtrim(base_url(), '/'), 'type' => 'string'),
                array('key' => 'access_token',  'value' => '', 'type' => 'string'),
                array('key' => 'refresh_token', 'value' => '', 'type' => 'string'),
                array('key' => 'email',    'value' => '', 'type' => 'string'),
                array('key' => 'password', 'value' => '', 'type' => 'string'),
            ),
            'item' => $items,
        );
    }

    private function postman_intro($spec)
    {
        return implode("\n", array(
            $spec['info']['summary'],
            '',
            '## Quick start',
            '',
            '1. Set the `email` and `password` collection variables.',
            '2. Run **Authentication → Log in**. Its test script stores `access_token` and',
            '   `refresh_token` for you — every other request picks them up automatically.',
            '3. Access tokens expire after 15 minutes. Run **Refresh the token pair** and the',
            '   variables rotate in place.',
            '',
            'Switch environments by editing `base_url` (default: ' . rtrim(base_url(), '/') . ').',
            '',
            'Full documentation: ' . base_url('api/docs'),
        ));
    }

    private function postman_item($path, $method, $op)
    {
        /* OpenAPI يكتب الوسيط `{id}` وPostman يقرؤه `:id` — وهو ليس فرق
           ذوق: بغير النقطتين لا يربط Postman الوسيط بقيمته في
           `url.variable`، فيرسل الطلب إلى مسار فيه القوسان حرفيا ويرد 404
           بلا أن يفهم أحد لماذا. */
        $pm_path  = preg_replace('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', ':$1', $path);
        $segments = array_values(array_filter(explode('/', trim($pm_path, '/'))));

        $query = array();
        $vars  = array();
        foreach ((isset($op['parameters']) ? $op['parameters'] : array()) as $p) {
            if ($p['in'] === 'query') {
                $query[] = array(
                    'key'         => $p['name'],
                    'value'       => (string) ($p['schema']['default'] ?? ''),
                    'description' => $p['description'] ?? '',
                    /* المعاملات الاختيارية معطلة لا محذوفة: من أرادها علم
                       بها ووجدها، ومن لم يردها لم يرسل فارغا يفسد الترشيح. */
                    'disabled'    => empty($p['required']),
                );
            } elseif ($p['in'] === 'path') {
                $vars[] = array('key' => $p['name'], 'value' => '1', 'description' => $p['description'] ?? '');
            }
        }

        $headers = array(array('key' => 'Accept', 'value' => 'application/json'));

        $request = array(
            'method'      => strtoupper($method),
            'header'      => $headers,
            'description' => $op['description'] ?? '',
            'url'         => array(
                /* المعطل لا يكتب في `raw`: Postman يعيد بناءها من مصفوفة
                   `query` عند الاستيراد، وذيل `?status=` في العنوان يقرأ
                   خطأ في الطلب لا خيارا معطلا. */
                'raw'  => '{{base_url}}/' . implode('/', $segments) . $this->postman_qs($query),
                'host' => array('{{base_url}}'),
                'path' => $segments,
            ),
        );
        if ($query) $request['url']['query'] = $query;
        if ($vars)  $request['url']['variable'] = $vars;

        if (!empty($op['requestBody']['content'])) {
            $content = $op['requestBody']['content'];

            if (isset($content['multipart/form-data'])) {
                $fields = array();
                foreach ($content['multipart/form-data']['schema']['properties'] as $name => $s) {
                    $fields[] = array('key' => $name, 'type' => ($s['format'] ?? '') === 'binary' ? 'file' : 'text', 'src' => '');
                }
                $request['body'] = array('mode' => 'formdata', 'formdata' => $fields);
            } elseif (isset($content['application/json'])) {
                $headers[] = array('key' => 'Content-Type', 'value' => 'application/json');
                $request['header'] = $headers;
                $example = $content['application/json']['example'] ?? new stdClass();

                /* بيانات الدخول من متغيرات المجموعة لا مكتوبة في الطلب:
                   من يشارك مجموعته لا يشارك كلمة مروره معها. */
                if ($path === '/api/v1/auth/login') {
                    $example['email']    = '{{email}}';
                    $example['password'] = '{{password}}';
                } elseif ($path === '/api/v1/auth/refresh') {
                    $example['refresh_token'] = '{{refresh_token}}';
                }

                $request['body'] = array(
                    'mode' => 'raw',
                    'raw'  => json_encode($example, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    'options' => array('raw' => array('language' => 'json')),
                );
            }
        }

        if (empty($op['security']) && isset($op['security'])) {
            $request['auth'] = array('type' => 'noauth');
        }

        $item = array(
            'name'    => $op['summary'],
            'request' => $request,
        );

        $script = $this->postman_script($path);
        if ($script) $item['event'] = $script;

        return $item;
    }

    /** سلسلة الاستعلام من المفعل وحده. */
    private function postman_qs($query)
    {
        $on = array();
        foreach ($query as $q) {
            if (!empty($q['disabled'])) continue;
            $on[] = $q['key'] . '=' . $q['value'];
        }
        return $on ? '?' . implode('&', $on) : '';
    }

    /** سكربت حفظ الرمزين — على الدخول والتجديد وحدهما. */
    private function postman_script($path)
    {
        if ($path !== '/api/v1/auth/login' && $path !== '/api/v1/auth/refresh') return null;

        $lines = array(
            'const res = pm.response.json();',
            'if (res && res.data && res.data.token) {',
            '    pm.collectionVariables.set("access_token",  res.data.token.access_token);',
            '    pm.collectionVariables.set("refresh_token", res.data.token.refresh_token);',
            '    console.log("Tokens stored. Access token expires in " + res.data.token.expires_in + "s.");',
            '}',
            'pm.test("Envelope is well formed", function () {',
            '    pm.expect(res).to.have.property("data");',
            '    pm.expect(res).to.have.property("meta");',
            '});',
        );

        return array(array(
            'listen' => 'test',
            'script' => array('type' => 'text/javascript', 'exec' => $lines),
        ));
    }
}
