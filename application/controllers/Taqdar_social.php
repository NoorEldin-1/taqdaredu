<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-SOCIAL — بابا الدخول بجوجل وأبل، وشاشة إكمال ما ينقص.
 *
 * **ولا قاعدة عمل واحدة في هذا الملف.** القرار كله في
 * [Taqdar_social_model](../models/Taqdar_social_model.php): من يدخل،
 * ومن ينشأ له حساب، وبأي صفة، ومتى يرد ولماذا. وهذا يعرض ويوجه —
 * كما تعرض شاشتا المقرر ولا تحكمان.
 *
 * ونهايته نهاية `Login::validate_login()` حرفا بحرف:
 * `new_device_login_tracker()` ثم `set_login_userdata()`. فالوجهة
 * وحد الأجهزة والجلسة ولوحة كل دور تعمل هنا بلا سطر يضاف.
 */
class Taqdar_social extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->load->model('taqdar_social_model', 'social');

        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->output->set_header('Pragma: no-cache');
    }

    /* =====================================================================
       الذهاب — /auth/<مزود>
       ===================================================================== */

    /**
     * يبني رابط التفويض ويذهب إليه.
     *
     * والبوابة والوجهة تسافران في **صف الحال** لا في الرابط: ما يعود من
     * المزود هو `state` وحده، وكل ما نعلقه على الرابط يعود إلينا من
     * خارج فيقرأ كما لو كتبناه. وهي قاعدة `tqs_safe_next()` نفسها بوجه
     * آخر.
     */
    public function start($provider = '')
    {
        $provider = $this->provider_ok($provider);

        if (!$this->social->enabled($provider)) {
            $this->bounce('هذه الطريقة غير متاحة حاليا. ادخل بالبريد وكلمة المرور.');
            return;
        }

        /* من هو داخل أصلا لا يستوثق من جديد: نقرة على الزر من صفحة
           مخبأة في متصفحه كانت تبدأ رحلة تنتهي بجلسة ثانية فوق جلسته. */
        if ($this->session->userdata('user_id')) {
            redirect(tq_home_for(tq_role()), 'location', 302);
            return;
        }

        $url = $this->social->start_url($provider, array(
            'gate' => (string) $this->input->get('as'),
            'next' => tqs_safe_next($this->input->get('next')),
        ));

        if ($url === '') {
            $this->bounce('تعذر بدء الدخول. أعد المحاولة.');
            return;
        }

        redirect($url, 'location', 302);
    }

    /**
     * ربط مزود بحساب قائم — من شاشة الإعدادات.
     *
     * ويفترق عن `start()` في شيء واحد: صاحبه معروف، فالعودة لا تنشئ
     * حسابا ولا تبحث ببريد — تربط هذا المزود بهذا الحساب بعينه.
     */
    public function link($provider = '')
    {
        $provider = $this->provider_ok($provider);
        $uid = (int) $this->session->userdata('user_id');

        if ($uid <= 0) { redirect(site_url('login'), 'location', 302); return; }

        if (!$this->social->enabled($provider)) {
            $this->back_to_settings('هذه الطريقة غير متاحة حاليا.', false);
            return;
        }

        $url = $this->social->start_url($provider, array(
            'mode'      => 'link',
            'link_user' => $uid,
        ));
        if ($url === '') {
            $this->back_to_settings('تعذر بدء الربط. أعد المحاولة.', false);
            return;
        }
        redirect($url, 'location', 302);
    }

    /* =====================================================================
       العودة — /auth/<مزود>/callback
       ===================================================================== */

    /**
     * ما يعود من المزود.
     *
     * جوجل تعود بـ`GET` وأبل بـ`POST` (TQ-APPLE-POST)، والدالة واحدة:
     * المعاملات تقرأ من الاثنين معا، والقاعدة التي تحكمها واحدة.
     *
     * TQ-APPLE-CSRF — والبادئة `auth/apple/callback` مستثناة من فحص
     * CSRF في [config.php](../config/config.php): أبل ترسل نموذجا من
     * نطاقها بلا رمز حماية ولا كعكة. وليس في ذلك إسقاط للحماية — الحال
     * (`state`) هي الحارس، وهي صف يستهلك مرة ولا يقرأ من كعكة أصلا.
     */
    public function callback($provider = '')
    {
        $provider = $this->provider_ok($provider);

        $q = function ($k) {
            $v = $this->input->post($k);
            if ($v === null || $v === '') $v = $this->input->get($k);
            return (string) $v;
        };

        /* «ألغيت» ليست خطأ: من ضغط «إلغاء» في شاشة المزود يعود إلى حيث
           كان بلا لافتة حمراء تقول له إن شيئا تعطل. */
        $err = $q('error');
        if ($err !== '') {
            if ($err === 'access_denied' || $err === 'user_cancelled_authorize') {
                $this->bounce('');
            } else {
                log_message('error', 'TQ-SOCIAL: ' . $provider . ' authorize — ' . $err);
                $this->bounce('تعذر إتمام الدخول مع المزود. أعد المحاولة.');
            }
            return;
        }

        $state = $this->social->consume_state($q('state'), $provider);
        if (!$state) {
            $this->bounce('انتهت مهلة الدخول أو أعيد إرسال الصفحة. اضغط الزر من جديد.');
            return;
        }

        $code = $q('code');
        if ($code === '') {
            $this->bounce('لم يرد المزود برمز تفويض. أعد المحاولة.');
            return;
        }

        $v = $this->social->exchange($provider, $code, (string) $state['nonce']);
        if (empty($v['ok'])) {
            $this->bounce($v['error']);
            return;
        }

        /* TQ-APPLE-NAME — الاسم يلتقط الآن أو لا يعرف أبدا. */
        $extra = array();
        $raw_user = $q('user');
        if ($raw_user !== '') {
            $d = json_decode($raw_user, true);
            if (is_array($d)) $extra = $d;
        }

        $id = $this->social->identity_from($provider, $v['claims'], $extra);

        /* ── رحلة ربط: صاحبها معروف، ولا بحث ولا إنشاء ── */
        if ((string) $state['mode'] === 'link') {
            $this->finish_link($id, (int) $state['link_user']);
            return;
        }

        $r = $this->social->resolve($id, array('gate' => (string) $state['gate']));
        if (empty($r['ok'])) {
            $this->bounce($r['error'], (string) $state['gate']);
            return;
        }

        $this->enter((int) $r['user_id'], !empty($r['created']),
                     tqs_safe_next((string) $state['next_url']), $provider);
    }

    /**
     * الربط ينتهي إلى شاشة الإعدادات برسالته.
     *
     * و**حساب المزود لا يربط بحسابين**: `uq_provider_uid` يمنعه في
     * المخطط، ولكن الرسالة أولى من ارتداد قاعدة — من ربط حساب جوجل
     * يستعمله أخوه يجب أن يعرف لماذا رد.
     */
    private function finish_link(array $id, $uid)
    {
        if ($uid <= 0 || (int) $this->session->userdata('user_id') !== $uid) {
            $this->bounce('انتهت جلستك أثناء الربط. سجل دخولك ثم أعد المحاولة.');
            return;
        }

        /* القرار في النموذج — والتطبيق يناديه نفسه (TQ-SOCIAL-API). */
        $r = $this->social->link_for($uid, $id);
        $this->back_to_settings($r['error'], !empty($r['ok']));
    }

    /**
     * فصل الربط — POST من شاشة الإعدادات.
     *
     * والحارس في النموذج لا في الشاشة (TQ-SOCIAL-LASTDOOR): الشاشة
     * تخفي الزر، والمسار يبقى مفتوحا لمن يعرفه.
     */
    public function unlink()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $uid = (int) $this->session->userdata('user_id');
        if ($uid <= 0) { redirect(site_url('login'), 'location', 302); return; }

        $provider = $this->provider_ok((string) $this->input->post('provider'));
        $r = $this->social->unlink($uid, $provider);

        $this->back_to_settings($r['ok']
            ? ('فصل ربط ' . $this->label($provider) . '.')
            : $r['error'], !empty($r['ok']));
    }

    /* =====================================================================
       TQ-SOCIAL-COMPLETE — ما لا يعطيه مزود
       ===================================================================== */

    /**
     * شاشة واحدة تطلب الجوال (والعمر للطالب).
     *
     * **وهي بعد الدخول لا قبله.** حجب الحساب حتى تملأ يعيد النموذج
     * الذي جاء هذا الباب ليتجاوزه — ومن وصل إلى هنا حسابه مفتوح
     * واشتراكه يعمل ولوحته تفتح، وهذه دعوة لا بوابة. و«لاحقا» موجودة
     * لأن زرا لا يوجد يجعل من لا يريد أن يكتب جواله الآن يغلق الصفحة
     * كلها.
     */
    public function complete()
    {
        $uid = (int) $this->session->userdata('user_id');
        if ($uid <= 0) { redirect(site_url('login'), 'location', 302); return; }

        $missing = $this->social->missing_of($uid);
        if (!$missing) {
            redirect($this->after_complete(), 'location', 302);
            return;
        }

        $u = $this->db->select('first_name, last_name, email, phone, age, grade_id, tq_gate')
                      ->where('id', $uid)->get('users')->row_array();

        $page['page_name']  = 'social_complete';
        $page['page_title'] = 'أكمل بياناتك';
        $page['tq_user']    = $u;
        $page['tq_missing'] = $missing;
        $page['tq_next']    = tqs_safe_next($this->input->get('next'));
        $this->load->view('frontend/' . get_frontend_settings('theme') . '/index', $page);
    }

    /** حفظ ما كتب — والفحص هنا كما يفحص `Login::register()` حرفا. */
    public function complete_save()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $uid = (int) $this->session->userdata('user_id');
        if ($uid <= 0) { redirect(site_url('login'), 'location', 302); return; }

        $next = tqs_safe_next($this->input->post('tq_next'));
        $back = site_url('account/complete') . ($next !== '' ? '?next=' . rawurlencode($next) : '');

        /* القواعد في النموذج — ونقطة التطبيق تناديها نفسها، ففحصان
           لحقل واحد يقبل أحدهما ما يرده الآخر. */
        $r = $this->social->complete_profile($uid, array(
            'password' => (string) $this->input->post('password'),
            'phone'    => (string) $this->input->post('phone'),
            'phone_cc' => (string) $this->input->post('phone_cc'),
            'age'      => (string) $this->input->post('age'),
            'grade_id' => (string) $this->input->post('grade_id'),
        ));
        if (empty($r['ok'])) {
            $first = reset($r['errors']);
            $this->session->set_flashdata('error_message', (string) $first[0]);
            redirect($back, 'location', 302);
            return;
        }

        $this->session->set_flashdata('flash_message', 'حفظت بياناتك.');
        redirect($next !== '' ? base_url($next) : $this->after_complete(), 'location', 302);
    }

    /* =====================================================================
       أدوات الشاشة
       ===================================================================== */

    /** مزود من قائمة لا نص حر: مقطع يصل من الرابط ويقرر أي مفاتيح تقرأ. */
    private function provider_ok($p)
    {
        $p = strtolower(trim((string) $p));
        if (!in_array($p, array('google', 'apple'), true)) show_404();
        return $p;
    }

    private function label($p)
    {
        return $p === 'apple' ? 'أبل' : 'جوجل';
    }

    /**
     * الدخول — نهاية `validate_login()` نفسها.
     *
     * و`true` للحساب الذي أنشئ الآن وحده: تأكيد «جهاز جديد» في منتصف
     * تسجيل لم يكمل بعد يرسل رمزا إلى بريد لم يفتح صاحبه صفحته قط.
     * ومن له حساب قائم يمر بالحارس نفسه الذي يمر به لو دخل بكلمته.
     */
    private function enter($uid, $created, $next, $provider)
    {
        $this->user_model->new_device_login_tracker($uid, $created ? true : '');

        /* الوجهة تمر عبر `url_history` لأن `set_login_userdata()` هي من
           يقرؤها — ومسار عودة ثان يعني وجهتين تفترقان. */
        /* TQ-QUICK-BUY — من ضغط جوجل في شاشة دفع جاء ليدفع: يعود إليها في
           الحال، وما ينقص حسابه تطلبه لافتة اللوحة بعد الدفع. */
        $to_pay = (bool) preg_match('#^(checkout|course-checkout|book-checkout|foundation-checkout)/#', $next);
        if (!$to_pay && $this->social->needs_profile($uid)) {
            $dest = site_url('account/complete')
                  . ($next !== '' ? '?next=' . rawurlencode($next) : '');
            $this->session->set_userdata('url_history', $dest);
        } elseif ($next !== '') {
            $this->session->set_userdata('url_history', base_url($next));
        } else {
            $this->session->unset_userdata('url_history');
        }
        $this->session->unset_userdata('tq_next');

        $this->session->set_flashdata($created ? 'flash_message' : 'info_message',
            $created
                ? ('أنشئ حسابك بـ' . $this->label($provider) . ' ودخلت.')
                : ('دخلت بحساب ' . $this->label($provider) . '.'));

        $this->user_model->set_login_userdata($uid);
    }

    /** الرد إلى صفحة الدخول برسالته — أو بلا رسالة لمن ألغى. */
    private function bounce($msg, $gate = '')
    {
        if ((string) $msg !== '') {
            $this->session->set_flashdata('error_message', $msg);
        }
        redirect(site_url('login') . ($gate !== '' && $gate !== 'student' ? '?as=' . $gate : ''),
                 'location', 302);
    }

    /** الرد إلى إعدادات صاحب الحساب — كل دور إلى شاشته. */
    private function back_to_settings($msg, $ok)
    {
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message', $msg);

        $role = tq_role();
        $map  = array(
            'teacher' => 'teacher/settings',
            'parent'  => 'parent/settings',
            'student' => 'student/settings',
        );
        redirect(site_url(isset($map[$role]) ? $map[$role] : 'student/settings'), 'location', 302);
    }

    /** بعد الإكمال: لوحة صاحبه — لا الصفحة العامة. */
    private function after_complete()
    {
        return tq_home_for(tq_role());
    }
}
