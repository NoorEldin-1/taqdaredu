<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * TQ-I18N — باب تبديل اللغة، واحد للوحات الأربع.
 *
 * وكان لكل بوابة بابها: شاشة الإعدادات عند الطالب والمعلم تحفظ اللغة في
 * `tq_prefs_user` عبر `Taqdar_settings_model::save_prefs()`، وولي الأمر
 * والإدارة **بلا باب أصلا** — فمن يدير المنصة لا يستطيع أن يقرأها بلغته.
 * وباب رابع في كل لوحة يعني أربع نسخا من قواعد الحفظ تفترق عند أول تعديل.
 *
 * والقرار في `tq_lang_set()` و`Taqdar_settings_model` — وهذا المتحكم يستقبل
 * ويرد إلى حيث جاء لا أكثر.
 */
class Taqdar_lang extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        $this->load->database();
    }

    /**
     * يبدل اللغة ويعيد إلى الصفحة نفسها.
     *
     * **كتابة، فتشترط POST.** واللغة تفضيل يقرأ في كل صفحة، ورابط GET يغيره
     * يعني أن صورة أو `<img>` في بريد أو رابط يرسله غيرك يقلب لغة حسابك —
     * وهي CSRF بعينها. فالمبدل نموذج، ورمزه يفحص كما يفحص كل نموذج.
     */
    public function set()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $lang = strtolower(trim((string) $this->input->post('lang', true)));
        $back = (string) $this->input->post('back', true);

        if (!tq_lang_set($lang)) {
            $this->session->set_flashdata('error_message', t('لغة غير متاحة.'));
            return $this->bounce($back);
        }

        /* الكعكة للزائر: من بدل قبل أن يسجل يعود فيجدها كما تركها. سنة
           كاملة، ومسارها الجذر — والجلسة تنتهي بإغلاق المتصفح. */
        $this->input->set_cookie(array(
            'name'   => 'tq_lang',
            'value'  => $lang,
            'expire' => 31536000,
            'path'   => '/',
        ));

        /* والحساب: التفضيل يتبع صاحبه على كل جهاز، ولا يبقى حبيس هذا
           المتصفح. والكتابة تمر بالنموذج نفسه الذي تمر به شاشة الإعدادات —
           فلا قاعدة ثانية للحفظ. */
        $uid = (int) $this->session->userdata('user_id');
        if ($uid) {
            $this->load->model('taqdar_settings_model');
            $this->taqdar_settings_model->set_language($uid, $lang);
        }

        return $this->bounce($back);
    }

    /**
     * يعود إلى الصفحة التي جاء منها.
     *
     * والوجهة **تفحص**: `back` يصل من `$_POST` فيكتبه من يشاء، ووجهة مطلقة
     * تجعل زر لغة في منصتنا يقذف من يضغطه إلى موقع غيرنا — وهو تحويل مفتوح.
     * فلا يقبل إلا مسارا نسبيا داخل المنصة، وما سواه يرد إلى الجذر.
     */
    private function bounce($back)
    {
        $back = trim((string) $back);

        $safe = ($back !== ''
            && $back[0] === '/'
            && strpos($back, '//') !== 0        // `//host` وجهة مطلقة متنكرة
            && strpos($back, "\n") === false
            && strpos($back, "\r") === false);

        if (!$safe) {
            $ref = (string) $this->input->server('HTTP_REFERER');
            $base = rtrim(base_url(), '/');
            if ($ref !== '' && strpos($ref, $base) === 0) {
                return redirect($ref, 'refresh');
            }
            return redirect(base_url(), 'refresh');
        }

        return redirect(rtrim(base_url(), '/') . $back, 'refresh');
    }
}
