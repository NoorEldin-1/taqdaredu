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

        $invoice_id = (int) $this->input->post('invoice_id');

        /* أين يرجع لو تعثر البدء؟ الفاتورة وحدها تقول: فاتورة حصة ترجع
           صاحبها إلى شاشة حصصه لا إلى «اشتراكي» — وهي شاشة لا يجد فيها
           ما دفع من أجله ولا زر يعيد المحاولة. */
        $home = $this->invoice_home($invoice_id);

        $r = $this->taqdar_tap_model->start($invoice_id, $uid);

        if (empty($r['ok'])) {
            $this->flash(false, implode(' ', $r['errors']));
            redirect(site_url($home), 'location', 302);
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

        /* والوجهة تتبع ما اشتري: الحصة ترجع إلى شاشة الحصص حيث رابط
           الدخول وموعده، والاشتراك إلى محتواه. وشاشة واحدة للاثنين تعني
           أن نصف من يدفع يعود إلى صفحة لا يجد فيها ما دفع من أجله.

           و`kind` لا يرد إلا حين تبلغ التسوية فحص المبلغ: دفعة رفضتها
           البوابة أو لم تكتمل ترجع بلا نوع — وهي **الحال التي يحتاج فيها
           الطالب أن يعود إلى مكانه** ليعيد المحاولة. فالنوع يقرأ منه إن
           جاء، ومن الفاتورة إن لم يجئ. */
        $session = (string) ($r['kind'] ?? '') === 'session';
        if (!$session && !empty($r['invoice_id'])) {
            $session = $this->invoice_home((int) $r['invoice_id']) === 'student/on-demand';
        }
        if ($session) $home = 'student/on-demand';

        if (!empty($r['ok'])) {
            $this->notify_paid($r);
            if ($session) {
                $this->flash(true, !empty($r['already'])
                    ? 'دفعتك مسجلة وحصتك مثبتة.'
                    : 'نجح الدفع وثبتت حصتك. رابط الدخول في بطاقتها أدناه.');
                redirect(site_url('student/on-demand'), 'location', 302);
                return;
            }
            $this->flash(true, !empty($r['already'])
                ? 'دفعتك مسجلة واشتراكك مفعل.'
                : 'نجح الدفع وفعل اشتراكك. المحتوى مفتوح لك الآن.');
            redirect(site_url($uid > 0 ? 'student/bundle' : $home), 'location', 302);
            return;
        }

        $this->flash(false, isset($r['errors']) ? implode(' ', $r['errors']) : 'تعذر التحقق من الدفعة.');
        redirect(site_url($home), 'location', 302);
    }

    /**
     * الشاشة التي تخص فاتورة — تسأل الحصص أولا.
     *
     * وهي قراءة واحدة لا فرع في كل موضع: `start()` و`back()` يحتاجان
     * الجواب نفسه، ونسختان منه تفترقان عند إضافة ما يباع ثالثا.
     */
    private function invoice_home($invoice_id)
    {
        if ((int) $invoice_id <= 0) return 'student/subscription';
        $this->load->model('taqdar_sessions_model');
        $s = $this->taqdar_sessions_model->by_invoice((int) $invoice_id);
        return $s ? 'student/on-demand' : 'student/subscription';
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
        /* الحصة يخبر بها **طرفاها**: الطالب ليعرف أن حصته ثبتت، والمعلم
           ليعرف أن موعده صار حقا لا انتظارا — وهو الطرف الذي كان يبقى
           بلا خبر فيقرأ «بانتظار الدفع» في شاشته ولا يدري متى تغيرت. */
        if ((string) ($r['kind'] ?? '') === 'session') {
            $this->notify_session_paid((int) ($r['session_id'] ?? 0));
            return;
        }

        $sid = (int) ($r['subscription_id'] ?? 0);
        if ($sid <= 0) return;

        /* TQ-SOLD-NAME — والاسم من `Taqdar_billing_model::sold()` وحدها.
           كان هنا ضم ثلاثي مكتوب بيده (`plans` · `paths` · `course`)،
           وهو الضم نفسه في خمسة مواضع أخرى — فوحدة البيع الرابعة (الكتاب)
           أضيفت في محرك الشراء ولم تبلغ واحدا منها: من دفع ثمن كتاب
           بالبطاقة يصله «نجح الدفع وفعل اشتراكك … «الاشتراك»». */
        $sub = $this->db->where('id', $sid)->get('subscriptions')->row_array();
        if (!$sub || empty($sub['user_id'])) return;

        $this->load->model('taqdar_billing_model');
        $sold = $this->taqdar_billing_model->sold($sub);
        $what = (string) $sold['title'];
        /* «الباقة» تجدد وتنتهي، والمفرد قد يفتح دائما — فالنص يفرق
           بينهما لا بين الكورس وما سواه وحده. */
        $single = in_array($sold['kind'], array('course', 'book'), true);

        /* والجملة مفتاح واحد ببدائل `____` لا قطع تلصق: قطعة مثل «وصلت
           حوالتك وفتح» لا تترجم وحدها — المترجم لا يعرف ما يليها، ولغة
           أخرى قد ترتبها غير هذا الترتيب. */
        $when = !empty($sub['ends_at'])
              ? ' ' . t('حتى ____', date('Y-m-d', strtotime($sub['ends_at'])))
              : ($single ? ' ' . t('بوصول دائم') : '');

        $this->load->model('taqdar_admin_model');
        $this->taqdar_admin_model->push_notification(
            (int) $sub['user_id'],
            $single ? t('نجح الدفع وفتح ____', $sold['noun_def'])
                    : t('نجح الدفع وفعل اشتراكك'),
            ($single ? t('استلمنا دفعتك وفتح ____ «____»',
                         array($sold['noun'], ($what !== '' ? $what : t('الاشتراك'))))
                     : t('استلمنا دفعتك وفتح «____»', ($what !== '' ? $what : t('الاشتراك'))))
            . $when . '. ' . t('صار المحتوى مفتوحا لك الآن.'),
            'subscription'
        );
    }

    /** يخبر طرفي الحصة أن ثمنها وصل وأن الموعد ثبت. */
    private function notify_session_paid($session_id)
    {
        if ((int) $session_id <= 0) return;

        $row = $this->db->query(
            'SELECT t.`student_id`, t.`teacher_id`, t.`price_halalas`, t.`teacher_share_halalas`,
                    a.`starts_at`, a.`duration_min`
               FROM `tutoring_sessions` t
          LEFT JOIN `availability_slots` a ON a.`id` = t.`slot_id`
              WHERE t.`id` = ? LIMIT 1',
            array((int) $session_id)
        )->row_array();
        if (!$row) return;

        $this->load->model('taqdar_sessions_model');
        $this->load->model('taqdar_admin_model');

        $when = !empty($row['starts_at'])
              ? $this->taqdar_sessions_model->when_text($row['starts_at'], (int) $row['duration_min'])
              : '';

        $this->taqdar_admin_model->push_notification(
            (int) $row['student_id'], 'ثبتت حصتك الخاصة',
            'وصل دفعك وثبتت الحصة' . ($when !== '' ? ' — ' . $when : '')
            . '. رابط الدخول في شاشة «حصص بالطلب».',
            'session'
        );

        $this->taqdar_admin_model->push_notification(
            (int) $row['teacher_id'], 'دفع الطالب ثمن الحصة',
            'ثبتت الحصة' . ($when !== '' ? ' — ' . $when : '')
            . '. ونصيبك ' . number_format(((int) $row['teacher_share_halalas']) / 100, 2)
            . ' ريال يقيد في محفظتك حين تعلن انتهاءها.',
            'session'
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
