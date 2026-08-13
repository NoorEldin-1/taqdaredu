<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أبواب الدفع بالبطاقة — ثلاثة لا أكثر.
 *
 * `start`    — يبدأ دفعة لفاتورة ويحول إلى صفحة تاب. POST وحده وبملكية.
 * `back`     — عودة الطالب من تاب. GET، ولا تصدق شيئا من الرابط.
 * `webhook`  — نداء تاب على الخادم. POST، بلا جلسة ولا CSRF.
 *
 * والثلاثة لا تقرر شيئا بأنفسها: كلها تنادي `Taqdar_tap_model::settle()`
 * أو `start()`، والقرار هناك على رد تاب لا على الطلب الوارد. فمن اخترع
 * `tap_id` أو صنع جسم ويبهوك لم يفعل شيئا.
 *
 * ولماذا `payment/tap/...` لا `pay/...`: `csrf_exclude_uris` في
 * [config.php](../config/config.php) يستثني `payment/.*` وحدها، والويبهوك
 * يأتي من خادم تاب بلا كعكة ولا رمز — فبادئة أخرى تعني أن كل نداء يرد
 * بـ403 ولا يفعل اشتراكا واحدا.
 */
class Taqdar_pay extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->model('taqdar_tap_model');

        /* الجلسة تحمل في أبواب المتصفح لا في الويبهوك: نداء تاب يأتي بلا
           كعكة، فتحميل الجلسة له يعني صفا جديدا في `ci_sessions` عند كل
           دفعة — سجلا ينمو بلا قارئ. */
    }

    /* =====================================================================
       بدء الدفع
       ===================================================================== */

    /**
     * POST student/pay-invoice — ادفع فاتورة قائمة بالبطاقة.
     *
     * تنادى من صفحة «اشتراكي» لفاتورة صدرت ولم تدفع، ومن
     * `Taqdar::subscribe()` مباشرة بعد إصدار الفاتورة.
     */
    public function start()
    {
        if ($this->input->method(true) !== 'POST') show_404();
        $this->load->library('session');

        $uid = (int) $this->session->userdata('user_id');
        if ($uid <= 0) {
            redirect(site_url('login'), 'location', 302);
            return;
        }

        $r = $this->taqdar_tap_model->start((int) $this->input->post('invoice_id'), $uid);

        if (empty($r['ok'])) {
            $this->session->set_flashdata('error_message', implode(' ', $r['errors']));
            $this->session->set_flashdata('tq_error', implode(' ', $r['errors']));
            redirect(site_url('student/subscription'), 'location', 302);
            return;
        }

        /* تحويل إلى خارج الموقع: `redirect()` تقبل العنوان الكامل، ولا
           يوضع الرابط في صفحة وسيطة — كل شاشة بين الضغط والدفع تسقط
           مشترين. */
        redirect($r['url'], 'location', 302);
    }

    /* =====================================================================
       عودة الطالب
       ===================================================================== */

    /**
     * GET payment/tap/return?tap_id=chg_xxx
     *
     * `tap_id` مفتاح جلب لا دليل دفع: `settle()` تسأل تاب عن الدفعة
     * وتقارن مبلغها بالمحاولة المسجلة، فلا يفعل شيء بطلب مصنوع.
     */
    public function back()
    {
        $this->load->library('session');
        $cid = trim((string) ($this->input->get('tap_id') ?: $this->input->get('id')));

        $uid  = (int) $this->session->userdata('user_id');
        $home = $uid > 0 ? 'student/subscription' : 'plans';

        if ($cid === '') {
            /* عودة بلا معرف: يقع حين يضغط الطالب «رجوع» في صفحة تاب.
               لا شيء دفع ولا شيء يقال إلا الحق. */
            $this->flash(false, 'لم تكتمل عملية الدفع. لم يخصم منك شيء، ويمكنك المحاولة مرة أخرى.');
            redirect(site_url($home), 'location', 302);
            return;
        }

        $r = $this->taqdar_tap_model->settle($cid, 'return');

        if (!empty($r['ok'])) {
            $this->notify_paid($r);
            $this->flash(true, !empty($r['already'])
                ? 'دفعتك مسجلة واشتراكك مفعل.'
                : 'نجح الدفع وفعل اشتراكك. المحتوى مفتوح لك الآن.');
            redirect(site_url($uid > 0 ? 'student/bundle' : $home), 'location', 302);
            return;
        }

        $this->flash(false, isset($r['errors']) ? implode(' ', $r['errors']) : 'تعذر التحقق من الدفعة.');
        redirect(site_url($home), 'location', 302);
    }

    /* =====================================================================
       الويبهوك
       ===================================================================== */

    /**
     * POST payment/tap/webhook — نداء تاب حين تنتهي الدفعة.
     *
     * وهو ما يسد الحالة التي تقع فعلا: يدفع الطالب ثم يغلق المتصفح قبل
     * أن يعود، فلا نقطة عودة تنادى — ويبقى المال محصلا والاشتراك مقفلا
     * لولا هذا الباب.
     *
     * يرد 200 دائما ما لم يكن الطلب نفسه معتلا: تاب تعيد النداء على
     * الخطأ، وإعادة النداء على حالة قررناها بحق تكرر بلا فائدة.
     */
    public function webhook()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $raw     = (string) $this->input->raw_input_stream;
        $payload = json_decode($raw, true);
        if (!is_array($payload)) $payload = array();

        $cid = trim((string) ($payload['id'] ?? ''));
        if ($cid === '') {
            log_message('error', 'TQ-TAP-HOOK: جسم بلا معرف دفعة.');
            $this->plain(400, 'no charge id');
            return;
        }

        /* التوقيع يفحص ويسجل ولا يمنع: القرار على `GET /charges/{id}`
           وحده. واختلافه خبر — جسم من غير تاب وصل إلى هذا الباب. */
        $hash = $this->taqdar_tap_model->hash_ok($payload, $this->input->get_request_header('hashstring', true));
        if ($hash === false) {
            log_message('error', 'TQ-TAP-HOOK: توقيع لا يطابق — ' . $cid);
        }

        $r = $this->taqdar_tap_model->settle($cid, 'webhook');
        if (!empty($r['ok']) && empty($r['already'])) $this->notify_paid($r);

        $this->plain(200, !empty($r['ok']) ? 'ok' : ('noted: ' . (string) ($r['state'] ?? 'unknown')));
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    /**
     * يخطر صاحب الاشتراك أن اشتراكه فتح.
     *
     * نفس ما تفعله اللوحة عند التفعيل اليدوي (`Taqdar_admin::subscription_activate`):
     * من دفع ينتظر خبرا، ومن لا يخبر يدخل كل يوم يجرب أو يتصل بالدعم.
     */
    private function notify_paid($r)
    {
        $sid = (int) ($r['subscription_id'] ?? 0);
        if ($sid <= 0) return;

        $sub = $this->db->select('s.user_id, s.ends_at, p.name_ar AS plan_name', false)
                        ->from('subscriptions s')
                        ->join('plans p', 'p.id = s.plan_id', 'left')
                        ->where('s.id', $sid)->get()->row_array();
        if (!$sub || empty($sub['user_id'])) return;

        $this->load->model('taqdar_admin_model');
        $this->taqdar_admin_model->push_notification(
            (int) $sub['user_id'],
            'نجح الدفع وفعل اشتراكك',
            'استلمنا دفعتك وفعلت باقة «' . ($sub['plan_name'] ?: 'الاشتراك') . '»'
            . (!empty($sub['ends_at']) ? ' حتى ' . date('Y-m-d', strtotime($sub['ends_at'])) : '')
            . '. صار المحتوى مفتوحا لك الآن.',
            'subscription'
        );
    }

    /** الرسالة تكتب بمفتاحي المنصة ومفتاحي شاشات تقدر معا. */
    private function flash($ok, $message)
    {
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message', $message);
        $this->session->set_flashdata($ok ? 'tq_ok' : 'tq_error', $message);
    }

    private function plain($code, $text)
    {
        $this->output->set_status_header($code)
                     ->set_content_type('text/plain', 'utf-8')
                     ->set_output($text);
    }
}
