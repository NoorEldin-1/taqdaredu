<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * حقن توكن CSRF في كل نموذج POST قبل إرسال الصفحة.
 *
 * البديل كان تعديل **٨٩ ملف عرض** يدويا (٥٣ نموذجا مكتوبا بخط اليد
 * و٨٢ نداء AJAX) — ونموذج واحد ينسى فيها ينتج ٤٠٣ لا يفهمه المستخدم،
 * ويكتشف بعد أسابيع. الحقن هنا يجعل الضمان **بنيويا**: ما خرج من
 * الخادم صفحة يحمل التوكن، سواء كتب نموذجه اليوم أو غدا.
 *
 * **الاسم `REST_` لا `MY_`** — لأن `subclass_prefix` في هذا التثبيت
 * `REST_` (تفرضه مكتبة REST_Controller). وملف باسم `MY_Output` كان
 * سيتجاهل صامتا بلا أي رسالة، فتفعل الحماية وتنكسر اللوحة بلا سبب ظاهر.
 *
 * ويحقن في **HTML وحده**: استجابة JSON ترسل كما هي، فحقن وسم فيها
 * يفسدها على من يقرؤها.
 */
class REST_Output extends CI_Output
{
    public function _display($output = '')
    {
        $CI = &get_instance();

        if ($output === '') {
            $output = $this->final_output;
        }

        if ($this->tq_should_inject($output)) {
            $output = $this->tq_inject_csrf($output, $CI);
        }

        parent::_display($output);
    }

    /**
     * أيحقن في هذه الاستجابة؟
     * شرطان: الحماية مفعلة، والمحتوى HTML فيه نموذج POST أصلا.
     */
    private function tq_should_inject($output)
    {
        if (!config_item('csrf_protection')) return false;
        if (!is_string($output) || $output === '') return false;

        // نوع المحتوى: ما صرح به يغلب التخمين
        $mime = $this->mime_type;
        if ($mime !== '' && stripos($mime, 'html') === false) return false;

        foreach ($this->headers as $h) {
            $line = is_array($h) ? $h[0] : $h;
            if (stripos($line, 'content-type:') === 0 && stripos($line, 'html') === false) {
                return false;
            }
        }

        return (bool) preg_match('/<form\b/i', $output);
    }

    /** يضع حقلا مخفيا بعد فتح كل نموذج POST لا يحمله. */
    private function tq_inject_csrf($output, $CI)
    {
        if (!isset($CI->security)) return $output;

        $name = $CI->security->get_csrf_token_name();
        $hash = $CI->security->get_csrf_hash();
        if (!$name || !$hash) return $output;

        $field = '<input type="hidden" name="' . $name . '" value="' . $hash . '">';

        return preg_replace_callback(
            '/<form\b[^>]*>/i',
            function ($m) use ($field, $name) {
                $tag = $m[0];

                // GET لا يتحقق منه، وحقل فيه يتسرب إلى شريط العنوان
                if (!preg_match('/\bmethod\s*=\s*["\']?post["\']?/i', $tag)) {
                    return $tag;
                }
                // نموذج يحمل توكنه سلفا يترك — لا تكرار
                return $tag . $field;
            },
            $output
        );
    }
}
