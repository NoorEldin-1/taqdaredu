<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * معاينة الدرس المجاني — بلا حساب ولا تسجيل دخول.
 *
 * كانت شارة «معاينة مجانية» في صفحة الباقة وفي منهجها تقود إلى
 * `student/lesson/…`، وذلك مسار محروس بـ`require_role('student')`:
 * فالزائر الذي جاء يقيس المحتوى **قبل** أن يدفع يرتد إلى صفحة الدخول.
 * والوعد المكتوب على الشارة — «مجانية» — يصير وعدا لا ينفذ، وهو أسوأ
 * من غياب الشارة أصلا.
 *
 * وهذا متحكم مستقل لا دالة في `Taqdar.php`: بادئة `taqdar/*` كلها محولة
 * بـ301 في `.htaccess` إلى `/student/*` المحروس، فأي نقطة تكتب هناك
 * ترث الحراسة نفسها التي جئنا نتجاوزها. والملف المستقل يأخذ مساره
 * `/preview/…` من توجيه CI الافتراضي، بلا قاعدة في `routes.php`.
 *
 * **والحر هو `lesson.is_free` وحده.** لا يقرأ من الرابط إلا رقمان،
 * والقرار يبنى على صف الدرس في القاعدة: مجاني، ومنشور، وليس اختبارا
 * (اختبار بلا حساب لا نتيجة له تسجل)، ومن الكورس المذكور. وما عدا ذلك
 * `show_404()` — لا رسالة تقول «هذا الدرس ليس مجانيا» فتدل من يجرب
 * الأرقام على ما يوجد.
 */
class Preview extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->model('crud_model');
    }

    /**
     * `/preview/<كورس>/<درس>`.
     *
     * `_remap` لأن المقطع الثاني رقم لا اسم دالة: بلاه يبحث CI عن دالة
     * اسمها «88» فيرد 404 على رابط صحيح.
     */
    public function _remap($method, $params = array())
    {
        /* `embed` ليس رقما فلا يلتبس بمعرف كورس: أول مقطع إما رقم
           (الصفحة الكاملة) أو هذا الاسم الواحد (حمولة النافذة). */
        if ((string) $method === 'embed') {
            $this->embed(
                (int) (isset($params[0]) ? $params[0] : 0),
                (int) (isset($params[1]) ? $params[1] : 0)
            );
            return;
        }

        $course_id = (int) $method;
        $lesson_id = (int) (isset($params[0]) ? $params[0] : 0);
        $this->lesson($course_id, $lesson_id);
    }

    /**
     * `/preview/embed/<كورس>/<درس>` — وسم المشغل وحده بـJSON.
     *
     * TQ-PREVIEW-MODAL. شارة «معاينة مجانية» في المنهج كانت تنقل الزائر
     * إلى صفحة أخرى: يفقد موضعه من المنهج، ويفقد بطاقة الشراء التي كان
     * ينظر إليها، ويحتاج رجوعا ليكمل تصفحه. والنافذة تبقيه حيث هو.
     *
     * وحمولة مستقلة لا عمودان يضافان إلى `bundle_by_code()`: المنهج
     * مئات الدروس، وحمل `video_url` مع كل واحد منها ليفتح واحد ثمن
     * يدفعه كل زائر عن نقرة قد لا تقع. وهذا نداء واحد لدرس واحد
     * **بعد** النقر.
     *
     * والحراسة هي حراسة الصفحة نفسها — `free_lesson()` واحدة للاثنتين:
     * فلا يفتح هذا الباب درسا لا تفتحه تلك، ولا يفترقان عند أول تعديل.
     */
    private function embed($course_id, $lesson_id)
    {
        $out = array('ok' => false, 'html' => '', 'title' => '', 'meta' => '');

        $l = $this->free_lesson($course_id, $lesson_id);
        if ($l) {
            $html = tqs_video_embed($l['video_type'], $l['video_url'], $l['title']);
            if ($html !== '') {
                $c = $this->db->select('title')->where('id', (int) $course_id)
                              ->get('course')->row_array();

                $meta = array();
                if ($c && trim((string) $c['title']) !== '') $meta[] = $c['title'];
                $dur = tqs_dur($l['duration']);
                if ($dur !== '') $meta[] = $dur;

                $out = array(
                    'ok'    => true,
                    'html'  => $html,
                    'title' => (string) $l['title'],
                    'meta'  => implode(' · ', $meta),
                );
            }
        }

        /* `show_404()` يرد صفحة HTML، وقارئ JSON يرمي عليها استثناء
           تفسيرا لا رسالة تعرض. فالغلاف واحد في الحالين والفرق في
           `ok` — والسكربت يعيد الزائر إلى الصفحة الكاملة عند الكذب. */
        $this->output
             ->set_status_header($out['ok'] ? 200 : 404)
             ->set_content_type('application/json', 'utf-8')
             ->set_output(json_encode($out, JSON_UNESCAPED_UNICODE));
    }

    /**
     * صف الدرس إن كان مما يعاين — وإلا `false`.
     *
     * موضع الشروط الأربعة الواحد: مجاني، ومنشور، وليس اختبارا، ومن
     * الكورس المذكور.
     */
    private function free_lesson($course_id, $lesson_id)
    {
        $course_id = (int) $course_id;
        $lesson_id = (int) $lesson_id;
        if ($course_id < 1 || $lesson_id < 1) return false;

        $l = $this->db->select('id, title, duration, course_id, section_id, video_type,
                                video_url, lesson_type, summary, is_free, tq_status', false)
                      ->where('id', $lesson_id)
                      ->where('course_id', $course_id)
                      ->get('lesson')->row_array();

        if (!$l)                                      return false;
        if ((int) $l['is_free'] !== 1)                return false;
        if ((string) $l['lesson_type'] === 'quiz')    return false;
        if ((string) $l['tq_status'] !== 'published') return false;

        return $l;
    }

    private function lesson($course_id, $lesson_id)
    {
        $l = $this->free_lesson($course_id, $lesson_id);
        if (!$l) show_404();

        $c = $this->db->select('id, title, short_description, thumbnail')
                      ->where('id', $course_id)->get('course')->row_array();
        if (!$c) show_404();

        /* الباقة التي يقع فيها هذا الدرس — لتكون نقرة الشراء واحدة بعد
           المشاهدة، لا رحلة بحث في الكتالوج عمن يبيع ما شوهد للتو. */
        $plan = $this->plan_of_course($course_id);

        $data = array(
            'page_name'  => 'site_preview',
            'page_title' => $l['title'],
            'tq_lesson'  => $l,
            'tq_course'  => $c,
            'tq_plan'    => $plan,
        );
        $this->load->view('frontend/' . $this->theme() . '/index', $data);
    }

    /** أول باقة منشورة يقع هذا الكورس في نطاقها — أو `null`. */
    private function plan_of_course($course_id)
    {
        $path = $this->db->select('grade_id, title, slug')
                         ->where('course_id', $course_id)
                         ->where('status', 'published')
                         ->limit(1)->get('paths')->row_array();
        if (!$path) return null;

        $gid = (int) $path['grade_id'];
        if ($gid < 1) return null;

        /* `scope_ids` قائمة معرفات بفواصل في عمود نصي (`multiref`)،
           فـ`FIND_IN_SET` لا `=` — والأخير يصيب باقة الصف الواحد ويخطئ
           كل باقة تجمع صفين. */
        $plan = $this->db->select('code, name_ar AS title, price')
                         ->from('plans')
                         ->where('scope', 'grade')
                         ->where('active', 1)
                         ->where("FIND_IN_SET(" . $gid . ", scope_ids) > 0", null, false)
                         ->order_by('price', 'ASC')->limit(1)
                         ->get()->row_array();

        return $plan ?: null;
    }

    /** الثيم كما يشتقه بقية الموقع. */
    private function theme()
    {
        $t = get_frontend_settings('theme');
        return $t ?: 'taqdar';
    }
}
