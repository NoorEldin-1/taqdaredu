<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * وحدات تقدر داخل لوحة الإدارة.
 *
 * تعرض في **غلاف اللوحة نفسه** (`backend/index.php`) لا في غلاف ثان:
 * لوحتان تعنيان قائمتين وترويستين وحالتي دخول، ويكفي أن تنسى إحداهما
 * عند إضافة صلاحية ليصير الأمن ثقبا. فالشاشة الجديدة ملف في
 * `backend/admin/` كسائر الشاشات، والفارق في محتواها لا في إطارها.
 */
class Taqdar_admin extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->load->model('taqdar_admin_model');
        $this->load->model('taqdar_billing_model');

        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');

        // نفس بوابة اللوحة القديمة: لا نخترع فحصا موازيا يمكن أن يتخلف عنها
        $this->user_model->check_session_data('admin');

        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'));
        }

        // وحدات تقدر صلاحية مستقلة كسائر الوحدات
        check_permission('taqdar');
    }

    /**
     * الغلاف القديم يدرج `admin/<page_name>.php` — فنكتفي بتسمية الصفحة.
     *
     * TQ-RAIL-NOACTIVE — و`page_name` لا يصلح وحده لتظليل الشريط الجانبي:
     * الوحدات الموصوفة كلها تعرض بقالبين اثنين، `tqa_list` و`tqa_form`،
     * فست عشرة شاشة — المواد والصفوف والمسارات والمحطات والأهداف
     * والتقييمات والباقات والفواتير والمحافظ والكتب والمسابقات وسجل
     * التدقيق … — كانت ترسل إلى `navigation.php` الاسم نفسه، ولا بند في
     * الشريط اسمه `tqa_list`. أي أن **الشريط لا يظلل شيئا في أي منها**،
     * ولا يجلب المستخدم إلى موضعه في قائمة من ثمانية وثلاثين بندا.
     *
     * فصار للشريط مفتاحه: `nav_key` يسمي **البند** لا القالب، وافتراضه
     * `page_name` فلا تتغير الشاشات التي كان اسمها يطابق بندها أصلا.
     */
    private function render($page_name, $title, $data = array())
    {
        $data['page_name']  = $page_name;
        $data['page_title'] = $title;
        if (!isset($data['nav_key'])) {
            $data['nav_key'] = $page_name;
        }
        $this->load->view('backend/index.php', $data);
    }

    public function index()
    {
        $this->overview();
    }

    /**
     * لوحة القيادة.
     *
     * كانت `admin/dashboard` هي الشاشة الأولى، وتقرأ جدول `payment` القديم
     * وعدد كورسات Academy: مبيعات لا تجري ورسم بياني لسنة لا تباع فيها
     * دورة واحدة بهذه الطريقة. والحقيقة في `subscriptions` و`invoices`
     * و`paths`. فالشاشة الأولى صارت تقرأ من حيث يجري المال فعلا، ومعها
     * صف «ما ينتظرك» — لأن اللوحة تفتح لتعرف ما تفعل لا لتقرأ أرقاما.
     */
    public function overview()
    {
        $this->render('tqa_overview', 'لوحة القيادة', array(
            'readiness' => $this->taqdar_admin_model->readiness(),
            'pulse'     => $this->taqdar_admin_model->pulse(),
            'queue'     => tqa_nav_counts(),
        ));
    }

    /* =====================================================================
       الحصص بالطلب

       المعلم يفتح وقتا والطالب يحجزه والمعلم يقرر — ولم يكن في اللوحة
       شاشة واحدة تقول كم حصة طلبت اليوم ولا كم منها بلا رد. فالإدارة
       تعرف بالشكوى وحدها.
       ===================================================================== */

    public function sessions()
    {
        $status = (string) $this->input->get('status');
        $this->render('tqa_sessions', 'الحصص', array(
            'rows'   => $this->taqdar_admin_model->sessions($status),
            'tally'  => $this->taqdar_admin_model->session_tally(),
            'status' => $status,
        ));
    }

    /**
     * إلغاء حصة من الإدارة.
     *
     * الإلغاء وحده لا الاعتماد: القبول قرار المعلم وحده — إدارة تقبل
     * نيابة عنه تحجز وقتا قد لا يكون فارغا. والإلغاء إداري بحق: طلب
     * علق أسبوعا يمنع الطالب من طلب غيره ويشغل وقتا لا يستعمل.
     */
    public function session_cancel()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $id = (int) $this->input->post('session_id');
        $ok = $this->taqdar_admin_model->cancel_session($id, (string) $this->input->post('reason'));

        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message', $ok
            ? 'ألغيت الحصة، وحرر وقتها، وأخطر الطرفان.'
            : 'تعذر الإلغاء — الحصة غير موجودة أو انتهت أصلا.');
        redirect(site_url('taqdar_admin/sessions'), 'location', 302);
    }

    /** أوقات المعلمين المفتوحة — من فتح وقتا ومن لم يفتح. */
    public function slots()
    {
        $this->render('tqa_slots', 'أوقات المعلمين', array(
            'rows'     => $this->taqdar_admin_model->slots(),
            'teachers' => $this->taqdar_admin_model->teacher_slot_summary(),
        ));
    }

    /* =====================================================================
       طلبات السحب

       المعلم يطلب من `POST teacher/wallet/withdraw` فيكتب صفا في `payout`
       ويحجز المبلغ في دفتر محفظته. وكانت شاشة الاعتماد الوحيدة هي
       `admin/instructor_payout` الموروثة: تدفع بـPayPal أو Stripe أو
       Razorpay، ولا واحدة منها مفعلة، ولا تعرف الدفتر أصلا — فاعتمادها
       يترك الرصيد محجوزا إلى الأبد.

       وهذه الشاشة تحول بالحوالة البنكية (وهي وسيلة الدفع الفعلية) وتمر
       بـ`Taqdar_wallet_model` فيغادر المال دلو الحجز كما ينبغي.
       ===================================================================== */

    public function payouts()
    {
        $this->load->model('taqdar_wallet_model');
        $this->render('tqa_payouts', 'طلبات السحب', array(
            'rows'     => $this->taqdar_admin_model->payouts((string) $this->input->get('status')),
            'status'   => (string) $this->input->get('status'),
            'channels' => Taqdar_wallet_model::$CHANNELS,
            'totals'   => $this->taqdar_admin_model->payout_totals(),
        ));
    }

    public function payout_decide()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $id  = (int) $this->input->post('payout_id');
        $act = (string) $this->input->post('act');
        $ref = trim((string) $this->input->post('reference'));

        $this->load->model('taqdar_wallet_model');

        if ($act === 'pay') {
            /* المرجع شرط لا زينة: تحويل بلا رقم عملية لا يطابق بكشف
               البنك، ولا يجاب به معلم يقول «لم يصلني». */
            if ($ref === '') {
                $this->session->set_flashdata('error_message',
                    'اكتب رقم عملية التحويل قبل الاعتماد — تحويل بلا مرجع لا يطابق بكشف البنك.');
                redirect(site_url('taqdar_admin/payouts'), 'location', 302);
                return;
            }

            $ok = $this->taqdar_wallet_model->mark_payout_paid($id, 'bank:' . $ref);
            if ($ok) {
                $this->taqdar_admin_model->audit('payout_paid', 'payout#' . $id, null, array('reference' => $ref));
                $this->taqdar_admin_model->notify_payout($id, true, $ref);
            }
            $msg = $ok ? 'اعتمد التحويل، وخصم المبلغ من المحجوز، وأخطر المعلم.' : 'تعذر الاعتماد.';

        } else {
            $ok = $this->taqdar_wallet_model->cancel_payout($id);
            if ($ok) {
                $this->taqdar_admin_model->audit('payout_rejected', 'payout#' . $id, null, array('reason' => $ref));
                $this->taqdar_admin_model->notify_payout($id, false, $ref);
            }
            $msg = $ok
                ? 'رفض الطلب، وأعيد المبلغ إلى رصيد المعلم المتاح.'
                : 'تعذر الرفض — الطلب محول أصلا أو غير موجود.';
        }

        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message', $msg);
        redirect(site_url('taqdar_admin/payouts'), 'location', 302);
    }

    /* =====================================================================
       الإشعارات

       جدول `notifications` يكتب فيه النظام والمهام الدورية، ولم يكن في
       اللوحة باب واحد إليه: لا مراجعة لما أرسل، ولا وسيلة لإخطار الطلاب
       بموعد امتحان أو بتوقف مؤقت للخدمة إلا برسالة بريدية جماعية.
       ===================================================================== */

    public function notify()
    {
        $this->render('tqa_notify', 'إرسال إشعار', array(
            'recent' => $this->taqdar_admin_model->recent_notifications(),
            'sizes'  => $this->taqdar_admin_model->audience_sizes(),
        ));
    }

    public function notify_send()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $title = trim((string) $this->input->post('title'));
        $body  = trim((string) $this->input->post('description'));
        $to    = (string) $this->input->post('audience');

        $errors = array();
        if ($title === '')            $errors[] = 'العنوان مطلوب.';
        if (mb_strlen($title) > 120)  $errors[] = 'العنوان أطول من ١٢٠ حرفا.';
        if ($body === '')             $errors[] = 'نص الإشعار مطلوب.';

        if ($errors) {
            $this->session->set_flashdata('error_message', implode(' ', $errors));
            redirect(site_url('taqdar_admin/notify'), 'location', 302);
            return;
        }

        $by_mail = ((string) $this->input->post('by_mail') === '1');
        $n = $this->taqdar_admin_model->broadcast($to, $title, $body, $by_mail);

        if ($n <= 0) {
            $this->session->set_flashdata('error_message', 'لم يرسل الإشعار — لا مستخدم في هذه الفئة.');
            redirect(site_url('taqdar_admin/notify'), 'location', 302);
            return;
        }

        $msg = 'أرسل الإشعار إلى ' . $n . ' مستخدما. ويظهر لهم في جرس بوابتهم فورا.';
        if ($by_mail) {
            /* الرقم يقال كما هو: «أرسل بالبريد» بلا عدد يخفي أن نصف
               المستلمين بلا بريد، وأن البريد قد لا يكون مضبوطا أصلا. */
            $m = (int) $this->taqdar_admin_model->last_broadcast_mailed;
            $msg .= $m > 0
                ? ' ووصلت نسخة بريدية إلى ' . $m . ' منهم.'
                : ' ولم ترسل نسخة بريدية — البريد الصادر غير مضبوط أو لا بريد لهم.';
        }

        $this->session->set_flashdata('flash_message', $msg);
        redirect(site_url('taqdar_admin/notify'), 'location', 302);
    }

    /* =====================================================================
       خريطة الإتقان

       `attempts` و`skill_state` و`review_queue` قلب المنتج: كل إجابة طالب
       تكتب فيها، وعليها يبنى جدول المراجعة والتقرير الأسبوعي. ولم تكن في
       اللوحة نافذة واحدة عليها — فلا سبيل لمعرفة أن هدفا بعينه يسقط فيه
       كل من مر به، وهو أنفع رقم في المنصة كلها لمن يحرر المنهج.
       ===================================================================== */

    public function mastery()
    {
        $this->render('tqa_mastery', 'خريطة الإتقان', array(
            'summary'   => $this->taqdar_admin_model->mastery_summary(),
            'hardest'   => $this->taqdar_admin_model->hardest_objectives(),
            'by_path'   => $this->taqdar_admin_model->mastery_by_path(),
        ));
    }

    /* =====================================================================
       الأشخاص

       كانت الحسابات في ثلاث شاشات موروثة (`admins` · `instructors` ·
       `users`) تقسم الناس بقسمة Academy: مسؤول ومحاضر وطالب. وأدوار تقدر
       أربعة — والرابع (**ولي الأمر**) لم تكن له شاشة أصلا: يسجل ويربط
       بأبنائه ولا يظهر في أي قائمة.
       ===================================================================== */

    public function people()
    {
        $this->render('tqa_people', 'كل الحسابات', array(
            'rows'  => $this->taqdar_admin_model->people(
                (string) $this->input->get('role'),
                (string) $this->input->get('q')
            ),
            'role'  => (string) $this->input->get('role'),
            'q'     => (string) $this->input->get('q'),
            'tally' => $this->taqdar_admin_model->role_tally(),
        ));
    }

    /** فتح حساب أو إغلاقه — الفعل الإداري الوحيد المتاح هنا. */
    public function people_toggle()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $id = (int) $this->input->post('user_id');
        $me = (int) $this->session->userdata('user_id');

        /* لا يغلق المسؤول حسابه: يخرج فورا ولا يستطيع الدخول ليفتحه —
           ولا أحد غيره قد يملك صلاحية فتحه. */
        if ($id === $me) {
            $this->session->set_flashdata('error_message', 'لا تغلق حسابك أنت — لن تستطيع الدخول لفتحه.');
            redirect(site_url('taqdar_admin/people'), 'location', 302);
            return;
        }

        $r = $this->taqdar_admin_model->toggle_user($id);
        $this->session->set_flashdata($r['ok'] ? 'flash_message' : 'error_message', $r['message']);
        redirect(site_url('taqdar_admin/people' . ($this->input->post('back') ? '?' . $this->input->post('back') : '')),
                 'location', 302);
    }

    /* =====================================================================
       نصوص الموقع العام

       تسع صفحات منشورة كانت نصوصها كلها مكتوبة في القوالب — صفر مرجع
       ديناميكي في ثمان منها. أي: تغيير كلمة في الصفحة الرئيسية يحتاج
       تحرير ملف ودفعا ونشرا. وهذه الشاشة تجعل اللوحة تملك ما ينشر.
       ===================================================================== */

    public function content($page = '')
    {
        $this->load->model('taqdar_content_model');

        if ($page === '') {
            $this->render('tqa_content', 'نصوص الصفحات', array(
                'pages'  => $this->taqdar_content_model->registry(),
                'edited' => $this->taqdar_content_model->edited_counts(),
            ));
            return;
        }

        $spec = $this->taqdar_content_model->registry($page);
        if (!$spec) show_404();

        $this->render('tqa_content_edit', $spec['title'], array(
            'page'   => $page,
            'spec'   => $spec,
            'values' => $this->taqdar_content_model->page_values($page),
        ));
    }

    public function content_save($page = '')
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_content_model');
        $r = $this->taqdar_content_model->save_page($page, $this->input->post(null, false));

        if (empty($r['ok'])) {
            $this->session->set_flashdata('error_message', implode(' ', $r['errors']));
            redirect(site_url('taqdar_admin/content'), 'location', 302);
            return;
        }

        $msg = 'حفظ ' . (int) $r['set'] . ' نصا';
        if (!empty($r['reset'])) {
            $msg .= '، وأرجع ' . (int) $r['reset'] . ' إلى الافتراضي';
        }
        $this->session->set_flashdata('flash_message', $msg . '. والتغيير ظاهر على الموقع الآن.');
        redirect(site_url('taqdar_admin/content/' . $page), 'location', 302);
    }

    /* =====================================================================
       الوحدات العامة
       ===================================================================== */

    public function module($key = '')
    {
        $spec = $this->taqdar_admin_model->spec($key);
        if (!$spec) show_404();

        $this->render('tqa_list', $spec['title'], array(
            // بند الشريط اسمه `tqa_<المفتاح>` — انظر التعليق على `render()`
            'nav_key' => 'tqa_' . $key,
            'mkey' => $key,
            'spec' => $spec,
            'rows' => $this->taqdar_admin_model->listing($key),
        ));
    }

    public function form($key = '', $id = 0)
    {
        $spec = $this->taqdar_admin_model->spec($key);
        if (!$spec || !empty($spec['readonly'])) show_404();

        $this->render('tqa_form', $spec['title'], array(
            'nav_key' => 'tqa_' . $key,
            'mkey' => $key,
            'spec' => $spec,
            'row'  => $id ? $this->taqdar_admin_model->row($key, $id) : null,
            'rid'  => (int) $id,
        ));
    }

    public function save($key = '', $id = 0)
    {
        // الكتابة بـ POST وحده: رابط GET يكتب يمكن أن يستدعيه زاحف أو صورة
        if ($this->input->method(true) !== 'POST') show_404();

        $r = $this->taqdar_admin_model->save($key, (int) $id, $this->input->post(null, false));

        if (!$r['ok']) {
            $this->session->set_flashdata('error_message', implode(' ', $r['errors']));
            redirect(site_url('taqdar_admin/form/' . $key . '/' . (int) $id));
        }

        $this->session->set_flashdata('flash_message', 'تم الحفظ.');
        redirect(site_url('taqdar_admin/module/' . $key));
    }

    public function delete($key = '', $id = 0)
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $ok = $this->taqdar_admin_model->remove($key, (int) $id);
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message',
            $ok ? 'تم الحذف.' : 'تعذر الحذف — الوحدة لا تسمح به.');
        redirect(site_url('taqdar_admin/module/' . $key));
    }

    /* =====================================================================
       الاشتراكات
       ===================================================================== */

    public function subscriptions()
    {
        // الاسم يجلب بضمة واحدة لا باستعلام لكل صف
        $rows = $this->db->select('s.*, p.name_ar AS plan_name,'
                . ' TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) AS user_name', false)
            ->from('subscriptions s')
            ->join('plans p', 'p.id = s.plan_id', 'left')
            ->join('users u', 'u.id = s.user_id', 'left')
            ->order_by('s.id', 'DESC')->limit(300)
            ->get()->result_array();

        $this->render('tqa_subscriptions', 'الاشتراكات', array(
            'rows'           => $rows,
            'stats'          => $this->taqdar_billing_model->stats(),
            'gateway_active' => $this->gateway_active(),
        ));
    }

    /**
     * هل يستطيع الطالب أن يدفع أونلاين الآن؟
     *
     * كانت هذه الدالة تقرأ `paypal` و`stripe_keys` من `settings` — ولا صف
     * لهما في هذه القاعدة أصلا، فترد `false` أبدا. أي أن شاشة الاشتراكات
     * كانت تنذر بأن «لا بوابة مفعلة» ولو فعلت عشر بوابات.
     *
     * والجواب اليوم من حيث يقع الدفع فعلا: `Taqdar_tap_model::ready()` —
     * وهي نفسها التي يسأل عنها القالب قبل أن يعرض للطالب خيار البطاقة،
     * فلا يفترق ما تقوله اللوحة عما يراه المشتري.
     */
    private function gateway_active()
    {
        $this->load->model('taqdar_tap_model');
        return $this->taqdar_tap_model->ready();
    }

    public function subscription_activate($id = 0)
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $ref = trim((string) $this->input->post('reference'));
        if ($ref === '') {
            $this->session->set_flashdata('error_message', 'اكتب مرجع الحوالة قبل التفعيل — التفعيل بلا مرجع لا يمكن تدقيقه.');
            redirect(site_url('taqdar_admin/subscriptions'));
        }

        $ok = $this->taqdar_billing_model->activate_manually((int) $id, $ref);

        /* TQ-SUB-TOLD — المشترك يعلم أن اشتراكه فتح.
           كان التفعيل يقع في القاعدة بلا خبر: من حول حوالة بنكية ينتظر
           ولا يعرف متى تراجع، فيدخل كل يوم يجرب — أو يتصل بالدعم. */
        if ($ok) {
            $sub = $this->db->select('s.user_id, p.name_ar AS plan_name, s.ends_at', false)
                            ->from('subscriptions s')
                            ->join('plans p', 'p.id = s.plan_id', 'left')
                            ->where('s.id', (int) $id)->get()->row_array();
            if ($sub && !empty($sub['user_id'])) {
                $this->taqdar_admin_model->push_notification(
                    (int) $sub['user_id'],
                    'فعل اشتراكك',
                    'فعلت باقة «' . ($sub['plan_name'] ?: 'الاشتراك') . '»'
                    . (!empty($sub['ends_at']) ? ' حتى ' . date('Y-m-d', strtotime($sub['ends_at'])) : '')
                    . '. صار المحتوى مفتوحا لك الآن.',
                    'subscription'
                );
            }
        }

        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message',
            $ok ? 'فعل الاشتراك وسددت فاتورته، وأخطر صاحبه.' : 'تعذر التفعيل.');
        redirect(site_url('taqdar_admin/subscriptions'));
    }

    public function subscription_cancel($id = 0)
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $ok = $this->taqdar_billing_model->cancel((int) $id, 'ألغته الإدارة');
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message',
            $ok ? 'ألغي التجديد — ويبقى الاشتراك صالحا حتى تاريخ انتهائه.' : 'تعذر الإلغاء.');
        redirect(site_url('taqdar_admin/subscriptions'));
    }

    /* =====================================================================
       البريد الصادر
       ===================================================================== */

    /** المفاتيح التي تدار من هذه الشاشة، وكلها في جدول `settings`. */
    private function mail_keys()
    {
        return array('smtp_host', 'smtp_port', 'smtp_crypto', 'smtp_user',
                     'smtp_pass', 'smtp_from_email', 'system_email');
    }

    private function mail_values()
    {
        $out = array();
        foreach ($this->mail_keys() as $k) {
            $out[$k] = (string) get_settings($k);
        }
        return $out;
    }

    /**
     * مضبوط = خادم ومستخدم وكلمة مرور ومرسل. ما دون ذلك ادعاء.
     *
     * الجواب يأتي من `Taqdar_mail_model` لا من نسخة هنا: كانت الشاشة
     * تحكم بأربعة مفاتيح، ومحرك الإرسال لا يسأل أصلا، و
     * `Taqdar_events_model` يسأل عن `smtp_user` وحدها. ثلاثة تعريفات
     * لكلمة واحدة تعني أن الشاشة قد تقول «مضبوط» ولا يرسل شيء.
     */
    private function mail_configured($m = null)
    {
        $this->load->model('taqdar_mail_model');
        return $this->taqdar_mail_model->configured();
    }

    public function mail()
    {
        $m = $this->mail_values();

        /* التشخيص يقرأ DNS، فقد يتأخر ثانية أو ثانيتين على شبكة بطيئة —
           وهو ثمن مقبول في شاشة تفتح مرة عند الضبط، ولا يدفع في أي مسار
           يراه مستخدم. */
        $this->load->model('taqdar_mail_model');

        $this->render('tqa_mail', 'البريد الصادر', array(
            'mail'         => $m,
            'configured'   => $this->mail_configured($m),
            'health'       => $this->taqdar_mail_model->diagnose(),
            'events_email' => get_settings('taqdar_events_email') === '1',
            'debug'        => $this->session->flashdata('mail_debug'),
        ));
    }

    public function mail_save()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $errors = array();
        $host = trim((string) $this->input->post('smtp_host'));
        $user = trim((string) $this->input->post('smtp_user'));
        $from = trim((string) $this->input->post('smtp_from_email'));
        $port = (int) $this->input->post('smtp_port');

        if ($host === '') $errors[] = 'خادم البريد مطلوب.';
        if ($user === '') $errors[] = 'اسم المستخدم مطلوب.';
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $errors[] = 'المرسل الظاهر ليس بريدا صالحا.';
        if ($port < 1 || $port > 65535) $errors[] = 'المنفذ خارج المدى.';

        // كلمة المرور: الفارغ يعني «أبق المحفوظة» لا «امسحها»
        $pass_in  = (string) $this->input->post('smtp_pass');
        $pass_old = (string) get_settings('smtp_pass');
        $pass     = ($pass_in === '') ? $pass_old : $pass_in;
        if ($pass === '') $errors[] = 'كلمة المرور مطلوبة في أول ضبط.';

        if ($errors) {
            $this->session->set_flashdata('error_message', implode(' ', $errors));
            redirect(site_url('taqdar_admin/mail'));
            return;
        }

        $vals = array(
            'protocol'        => 'smtp',
            'smtp_host'       => $host,
            'smtp_port'       => (string) $port,
            'smtp_crypto'     => (string) $this->input->post('smtp_crypto'),
            'smtp_user'       => $user,
            'smtp_pass'       => $pass,
            'smtp_from_email' => $from,
            'system_email'    => trim((string) $this->input->post('system_email')) ?: $from,
        );

        foreach ($vals as $k => $v) {
            // كلمة المرور لا تهرب: `html_escape` يفسد ما فيه & أو < أو "
            if ($this->db->where('key', $k)->count_all_results('settings') > 0) {
                $this->db->where('key', $k)->update('settings', array('value' => $v));
            } else {
                $this->db->insert('settings', array('key' => $k, 'value' => $v));
            }
        }

        // السر لا يقيد في السجل — يقيد أنه تغير
        $this->taqdar_admin_model->audit('mail_settings_update', 'settings',
            null, array('host' => $host, 'user' => $user, 'from' => $from,
                        'pass_changed' => $pass_in !== ''));

        /* الضبط يقرأ مرة في الطلب ويحفظ ساكنا — فينسى بعد الكتابة، وإلا
           قرأت الصفحة التالية القيم القديمة وقالت «غير مضبوط» بعد الحفظ. */
        $this->load->model('taqdar_mail_model');
        $this->taqdar_mail_model->forget();

        $this->session->set_flashdata('flash_message', 'حفظت إعدادات البريد. أرسل رسالة فحص للتأكد.');
        redirect(site_url('taqdar_admin/mail'));
    }

    /**
     * يرسل رسالة حقيقية بالمسار الذي تستعمله المنصة نفسها — لا بمسار مواز
     * يظهر نجاحا لا يتكرر في الاستعمال الفعلي.
     */
    public function mail_test()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $to = trim((string) $this->input->post('to'));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->session->set_flashdata('error_message', 'عنوان المستلم غير صالح.');
            redirect(site_url('taqdar_admin/mail'));
            return;
        }
        if (!$this->mail_configured()) {
            $this->session->set_flashdata('error_message', 'احفظ بيانات الخادم أولا.');
            redirect(site_url('taqdar_admin/mail'));
            return;
        }

        /* بالمسار الذي تستعمله المنصة نفسها — لا بمسار مواز يظهر نجاحا
           لا يتكرر في الاستعمال الفعلي. */
        $this->load->model('taqdar_mail_model');
        $ok = $this->taqdar_mail_model->send_lines($to, 'رسالة فحص من منصة تقدر', array(
            'هذه رسالة فحص من لوحة تقدر.',
            'وصولها يعني أن إعدادات البريد صحيحة، وأن استعادة كلمة المرور '
            . 'وقرارات الطلبات والتقارير والتنبيهات صارت تصل فعلا.',
            'أرسلت في ' . date('Y-m-d H:i') . '.',
        ), array('label' => 'افتح لوحة الإدارة', 'href' => site_url('taqdar_admin/overview')),
           array('debug' => true));

        if ($ok) {
            $this->session->set_flashdata('flash_message',
                'أرسلت الرسالة إلى ' . $to . ' — تحقق من الصندوق (ومن مجلد المهملات).');
        } else {
            $this->session->set_flashdata('error_message', 'لم ترسل الرسالة. السبب أدناه كما قاله الخادم.');
            $this->session->set_flashdata('mail_debug', $this->taqdar_mail_model->last_error);
        }

        redirect(site_url('taqdar_admin/mail'));
    }

    public function mail_toggle_events()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $on  = get_settings('taqdar_events_email') === '1';
        $new = $on ? '0' : '1';

        if ($this->db->where('key', 'taqdar_events_email')->count_all_results('settings') > 0) {
            $this->db->where('key', 'taqdar_events_email')->update('settings', array('value' => $new));
        } else {
            $this->db->insert('settings', array('key' => 'taqdar_events_email', 'value' => $new));
        }

        $this->session->set_flashdata('flash_message',
            $new === '1' ? 'فعلت التنبيهات البريدية.' : 'أوقفت التنبيهات البريدية.');
        redirect(site_url('taqdar_admin/mail'));
    }

    /* =====================================================================
       واتساب الصادر

       شاشة أخت لشاشة البريد: بيانات اتصال، وفحوص تسأل المزود قبل أن
       تفشل رسالة على مستخدم، ورسالة فحص حقيقية بالمسار الذي تستعمله
       المنصة نفسها.

       والفرق الوحيد الذي يستحق الذكر: البريد يرسل ما تكتبه، وواتساب لا
       يرسل إلا **قالبا اعتمدته ميتا مسبقا** — فالشاشة تحمل دليلا خطوة
       بخطوة يقول من أين يجاء كل بيان، ويعطي نص القالبين جاهزا للنسخ.
       ===================================================================== */

    /** المفاتيح التي تدار من هذه الشاشة، وكلها في جدول `settings`. */
    private function wa_values()
    {
        $out = array();
        foreach (Taqdar_wa_model::$KEYS as $k) {
            $out[$k] = (string) get_settings($k);
        }
        return $out;
    }

    public function whatsapp()
    {
        $this->load->model('taqdar_wa_model');

        /* التشخيص ينادي ميتا مرتين، فقد يتأخر ثانية أو ثانيتين — وهو ثمن
           مقبول في شاشة تفتح عند الضبط، ولا يدفع في أي مسار يراه مستخدم. */
        $this->render('tqa_whatsapp', 'إشعارات واتساب', array(
            'wa'         => $this->wa_values(),
            'cfg'        => $this->taqdar_wa_model->config(),
            'configured' => $this->taqdar_wa_model->configured(),
            'health'     => $this->taqdar_wa_model->diagnose(),
            'log'        => $this->taqdar_wa_model->recent(25),
            'totals'     => $this->taqdar_wa_model->totals(),
            'otp_on'     => (string) get_settings('tq_signup_otp') !== '0',
            'debug'      => $this->session->flashdata('wa_debug'),
        ));
    }

    /**
     * حفظ إعدادات واتساب.
     *
     * ورمز الوصول **لا يعاد إلى الصفحة أبدا** ولا يمحوه إرسال فارغ:
     * الحقل يترك خاليا للإبقاء على المحفوظ، كما في كلمة مرور البريد.
     * ومن فتح الشاشة ليصحح اسم قالب لا يقصد أن يمسح رمزه.
     */
    public function whatsapp_save()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_wa_model');
        $before = $this->taqdar_wa_model->config();

        $errors   = array();
        $enabled  = (string) $this->input->post('tq_wa_enabled') === '1';
        $phone_id = preg_replace('/\D/', '', (string) $this->input->post('tq_wa_phone_id'));
        $waba_id  = preg_replace('/\D/', '', (string) $this->input->post('tq_wa_waba_id'));
        $ver      = trim((string) $this->input->post('tq_wa_api_ver'));

        /* الرمز الفارغ يعني «أبق المحفوظ» لا «امسحه» — والمسح صريح. */
        $tok_in  = trim((string) $this->input->post('tq_wa_token'));
        $tok_old = (string) get_settings('tq_wa_token');
        $token   = ((string) $this->input->post('tq_wa_token_clear') === '1')
                 ? '' : ($tok_in !== '' ? $tok_in : $tok_old);

        if ($ver !== '' && !preg_match('/^v[0-9]+\.[0-9]+$/', $ver)) {
            $errors[] = 'نسخة الواجهة تكتب هكذا: v21.0';
        }
        if ($enabled && $token === '')    $errors[] = 'التفعيل بلا رمز وصول لا يرسل شيئا.';
        if ($enabled && $phone_id === '') $errors[] = 'التفعيل بلا معرف رقم المرسل لا يرسل شيئا.';

        /* أسماء القوالب: حروف لاتينية صغيرة وأرقام وشرطة سفلية — هذا شرط
           ميتا نفسه، وفحصه هنا يوفر على المسؤول رسالة `132001` غامضة. */
        $tpl_otp    = strtolower(trim((string) $this->input->post('tq_wa_tpl_otp')));
        $tpl_notice = strtolower(trim((string) $this->input->post('tq_wa_tpl_notice')));
        foreach (array('قالب الرمز' => $tpl_otp, 'قالب الإشعارات' => $tpl_notice) as $lbl => $nm) {
            if ($nm !== '' && !preg_match('/^[a-z0-9_]{1,512}$/', $nm)) {
                $errors[] = $lbl . ': الاسم حروف لاتينية صغيرة وأرقام وشرطة سفلية فقط.';
            }
        }

        $lang_otp    = trim((string) $this->input->post('tq_wa_tpl_otp_lang'));
        $lang_notice = trim((string) $this->input->post('tq_wa_tpl_notice_lang'));
        foreach (array($lang_otp, $lang_notice) as $lg) {
            if ($lg !== '' && !preg_match('/^[a-zA-Z]{2}(_[a-zA-Z]{2})?$/', $lg)) {
                $errors[] = 'رمز اللغة يكتب هكذا: ar أو ar_SA أو en_US.';
                break;
            }
        }

        if ($errors) {
            $this->session->set_flashdata('error_message', implode(' ', $errors));
            redirect(site_url('taqdar_admin/whatsapp'), 'location', 302);
            return;
        }

        $vals = array(
            'tq_wa_enabled'           => $enabled ? '1' : '0',
            'tq_wa_token'             => $token,
            'tq_wa_phone_id'          => $phone_id,
            'tq_wa_waba_id'           => $waba_id,
            'tq_wa_api_ver'           => $ver ?: Taqdar_wa_model::DEFAULT_VER,
            'tq_wa_tpl_otp'           => $tpl_otp,
            'tq_wa_tpl_otp_lang'      => $lang_otp ?: 'ar',
            'tq_wa_tpl_otp_button'    => (string) $this->input->post('tq_wa_tpl_otp_button') === '1' ? '1' : '0',
            'tq_wa_tpl_notice'        => $tpl_notice,
            'tq_wa_tpl_notice_lang'   => $lang_notice ?: 'ar',
            'tq_wa_tpl_notice_params' => ((int) $this->input->post('tq_wa_tpl_notice_params') === 1) ? '1' : '2',
            'tq_wa_notify_payments'   => (string) $this->input->post('tq_wa_notify_payments') === '1' ? '1' : '0',
            'tq_wa_otp_allowed'       => (string) $this->input->post('tq_wa_otp_allowed') === '1' ? '1' : '0',
        );

        $this->settings_put($vals);

        /* السجل يحفظ الحدث لا السر: سجل تدقيق فيه رمز وصول نسخة ثانية
           منه في مكان لا يحرس كما يحرس. */
        $this->taqdar_admin_model->audit('wa_settings_save', 'settings#whatsapp',
            array('enabled' => $before['enabled'], 'phone_id' => $before['phone_id']),
            array('enabled' => $enabled, 'phone_id' => $phone_id,
                  'token_changed' => ($tok_in !== ''), 'tpl_otp' => $tpl_otp,
                  'tpl_notice' => $tpl_notice));

        /* الضبط يقرأ مرة في الطلب ويحفظ — فينسى بعد الكتابة، وإلا قرأت
           الصفحة التالية القيم القديمة وقالت «غير مضبوط» بعد الحفظ. */
        $this->taqdar_wa_model->forget();

        if (!$enabled) {
            $msg = 'حفظت — وواتساب معطل، فتبقى الإشعارات في المنصة والبريد كما هي.';
        } elseif ($tpl_notice === '' && $tpl_otp === '') {
            $msg = 'حفظت — ولا قالب محفوظا، فلا يخرج شيء إلا لمن راسل الرقم في آخر '
                 . 'أربع وعشرين ساعة. راجع الدليل أسفل الشاشة.';
        } else {
            $msg = 'حفظت. راجع بطاقة «حال الاتصال» أعلى الشاشة، ثم أرسل رسالة فحص.';
        }

        $this->session->set_flashdata('flash_message', $msg);
        redirect(site_url('taqdar_admin/whatsapp'), 'location', 302);
    }

    /**
     * يرسل رسالة حقيقية بالمسار الذي تستعمله المنصة نفسها.
     *
     * وثلاثة أنواع لا واحد، لأن الفشل يختلف باختلافها: النص الحر يفشل
     * بـ`131047` إن مضى يوم على آخر رسالة من الرقم، والقالب يفشل
     * بـ`132001` إن كان اسمه أو لغته خطأ، وقالب الرمز يفشل بـ`132000`
     * إن اختلف عدد بدائله. ورسالة فحص واحدة تخفي عطبين من الثلاثة.
     */
    public function whatsapp_test()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_wa_model');

        $to = trim((string) $this->input->post('to'));
        $e164 = $this->taqdar_wa_model->to_e164($to);

        if ($e164 === '') {
            $this->session->set_flashdata('error_message',
                'الرقم غير صالح. اكتبه هكذا: 0501234567 — أو برمز دولته: ‎+201001234567');
            redirect(site_url('taqdar_admin/whatsapp'), 'location', 302);
            return;
        }
        if (!$this->taqdar_wa_model->configured()) {
            $this->session->set_flashdata('error_message', 'احفظ بيانات الاتصال أولا وفعلها.');
            redirect(site_url('taqdar_admin/whatsapp'), 'location', 302);
            return;
        }

        $kind = (string) $this->input->post('kind');
        $cfg  = $this->taqdar_wa_model->config();

        if ($kind === 'otp') {
            if ($cfg['tpl_otp'] === '') {
                $this->session->set_flashdata('error_message',
                    'لا قالب رمز محفوظ — احفظ اسمه أولا، أو افحص بالنص الحر.');
                redirect(site_url('taqdar_admin/whatsapp'), 'location', 302);
                return;
            }
            /* رمز فحص لا رمز حقيقي: لا يقبل في أي شاشة، ولا يكتب في
               `tq_otp`. من يفحص لا يريد أن يفتح حسابا. */
            $ok   = $this->taqdar_wa_model->send_otp($e164, '000000', array('purpose' => 'test'));
            $what = 'قالب رمز التحقق';

        } elseif ($kind === 'notice') {
            if ($cfg['tpl_notice'] === '') {
                $this->session->set_flashdata('error_message',
                    'لا قالب إشعارات محفوظ — احفظ اسمه أولا، أو افحص بالنص الحر.');
                redirect(site_url('taqdar_admin/whatsapp'), 'location', 302);
                return;
            }
            $ok = $this->taqdar_wa_model->send_template(
                $e164, $cfg['tpl_notice'], $cfg['tpl_notice_lang'],
                ($cfg['tpl_notice_params'] >= 2)
                    ? array('رسالة فحص من منصة تقدر',
                            'وصولها يعني أن إشعارات الدفع تخرج فعلا. أرسلت في ' . date('Y-m-d H:i') . '.')
                    : array('رسالة فحص من منصة تقدر — أرسلت في ' . date('Y-m-d H:i')),
                array('purpose' => 'test'));
            $what = 'قالب الإشعارات';

        } else {
            $ok = $this->taqdar_wa_model->send_text($e164,
                'رسالة فحص من منصة تقدر.' . "\n"
                . 'وصولها يعني أن رمز الوصول ومعرف الرقم صحيحان.' . "\n"
                . 'أرسلت في ' . date('Y-m-d H:i') . '.',
                array('purpose' => 'test'));
            $what = 'النص الحر';
        }

        if ($ok) {
            $this->session->set_flashdata('flash_message',
                'أرسلت (' . $what . ') إلى ' . $this->taqdar_wa_model->pretty($e164)
                . ' — راجع الجهاز. وقبول ميتا للطلب لا يعني وصولها بعد، فالسجل أسفل الشاشة يقول متى.');
        } else {
            $this->session->set_flashdata('error_message',
                'لم ترسل (' . $what . '). السبب أدناه كما قالته ميتا.');
            $this->session->set_flashdata('wa_debug', $this->taqdar_wa_model->last_error);
        }

        redirect(site_url('taqdar_admin/whatsapp'), 'location', 302);
    }

    /** يفعل تأكيد الحساب بالرمز أو يوقفه — مفتاح واحد يمس التسجيل كله. */
    public function whatsapp_toggle_otp()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $on = (string) get_settings('tq_signup_otp') !== '0';
        $this->settings_put(array('tq_signup_otp' => $on ? '0' : '1'));

        $this->session->set_flashdata('flash_message', $on
            ? 'أوقفت تأكيد الحساب بالرمز — الحسابات الجديدة تفتح فورا.'
            : 'فعلت تأكيد الحساب بالرمز. ولو تعذر إرسال الرمز (لا بريد ولا واتساب) '
            . 'فتح الحساب كما كان، فلا يتعطل التسجيل.');
        redirect(site_url('taqdar_admin/whatsapp'), 'location', 302);
    }

    /** كتابة مفاتيح في `settings` — upsert، كما في `bank_save` و`tap_save`. */
    private function settings_put($vals)
    {
        foreach ((array) $vals as $k => $v) {
            if ($this->db->where('key', $k)->count_all_results('settings') > 0) {
                $this->db->where('key', $k)->update('settings', array('value' => (string) $v));
            } else {
                $this->db->insert('settings', array('key' => $k, 'value' => (string) $v));
            }
        }
    }

    /* =====================================================================
       استيراد المنهج
       ===================================================================== */

    public function import()
    {
        $this->render('tqa_import', 'استيراد المنهج', array(
            'preview' => $this->session->flashdata('import_preview'),
        ));
    }

    /** يقرأ ويفحص ويعرض — **ولا يكتب**. الكتابة خطوة مستقلة يؤكدها الإنسان. */
    public function import_preview()
    {
        if ($this->input->method(true) !== "POST") show_404();

        if (empty($_FILES['curriculum']['name'])) {
            $this->session->set_flashdata('error_message', 'اختر ملفا أولا.');
            redirect(site_url('taqdar_admin/import'));
            return;
        }
        if ((int) $_FILES['curriculum']['size'] > 2097152) {
            $this->session->set_flashdata('error_message', 'الملف أكبر من ٢ ميغابايت.');
            redirect(site_url('taqdar_admin/import'));
            return;
        }

        $ext = strtolower(pathinfo($_FILES['curriculum']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv', 'json'), true)) {
            $this->session->set_flashdata('error_message', 'يقبل CSV أو JSON فقط.');
            redirect(site_url('taqdar_admin/import'));
            return;
        }

        $this->load->model('taqdar_import_model');
        $parsed = $this->taqdar_import_model->parse($_FILES['curriculum']['tmp_name'], $ext);

        if (empty($parsed['ok'])) {
            $this->session->set_flashdata('error_message', implode(' ', $parsed['errors']));
            redirect(site_url('taqdar_admin/import'));
            return;
        }

        $preview = $this->taqdar_import_model->validate_rows($parsed['rows']);

        // تحفظ المعاينة في الجلسة لا في حقل مخفي: حقل يحمل الصفوف يعود
        // من المتصفح فيكتب ما لم يفحص
        $this->session->set_userdata('tq_import_rows', $preview);
        $this->session->set_flashdata('import_preview', $preview);
        redirect(site_url('taqdar_admin/import'));
    }

    public function import_commit()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $rows = $this->session->userdata('tq_import_rows');
        if (!is_array($rows) || !$rows) {
            $this->session->set_flashdata('error_message', 'لا معاينة محفوظة. أعد رفع الملف.');
            redirect(site_url('taqdar_admin/import'));
            return;
        }

        $this->load->model('taqdar_import_model');
        $st = $this->taqdar_import_model->commit($rows);
        $this->session->unset_userdata('tq_import_rows');

        $this->session->set_flashdata('flash_message',
            'تم الاستيراد: أنشئ ' . $st['created'] . ' مسارا، وحدث ' . $st['updated'] .
            '، وأنشئ ' . $st['subjects'] . ' مادة و' . $st['grades'] . ' صفا' .
            ($st['skipped'] ? '، وتجووز ' . $st['skipped'] . ' صفا معطوبا.' : '.'));
        redirect(site_url('taqdar_admin/module/paths'));
    }

    /** ملف نموذجي بالأعمدة الصحيحة ومثال مملوء — أسرع من شرح مكتوب. */
    public function import_template()
    {
        $rows = array(
            array('المادة', 'الصف', 'المسار', 'السعر', 'المعلم', 'النسبة', 'الاسابيع', 'الدورة', 'الحالة'),
            array('الرياضيات', 'الصف السادس', 'رياضيات السادس — الفصل الأول', '990', 'teacher@example.com', '15', '12', '', 'مسودة'),
            array('اللغة العربية', 'الصف السادس', 'عربي السادس — النحو', '790', '', '', '10', '', 'مسودة'),
        );

        $this->output
             ->set_content_type('text/csv; charset=utf-8')
             ->set_header('Content-Disposition: attachment; filename="taqdar-curriculum-template.csv"');

        // شارة BOM ليفتحه Excel بالعربية سليمة لا رموزا
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $r) {
            $out .= '"' . implode('","', $r) . '"' . "\r\n";
        }
        $this->output->set_output($out);
    }

    /* =====================================================================
       ربط الأسئلة بالأهداف

       النطاق دورة لا درس: السؤال يسكن درس الاختبار والهدف يسكن درس الفيديو،
       فلا يلتقيان إلا فوقهما — في الدورة التي تضم الدرسين.
       ===================================================================== */

    public function bindings()
    {
        $this->render('tqa_bindings', 'ربط الأسئلة بالأهداف', array(
            'courses' => $this->taqdar_admin_model->question_binding_overview(),
        ));
    }

    public function bind($course_id = 0)
    {
        $course_id = (int) $course_id;
        $course    = $this->db->where('id', $course_id)->get('course')->row_array();
        if (!$course) show_404();

        $this->render('tqa_bind', 'ربط أسئلة الدورة', array(
            'course'     => $course,
            'questions'  => $this->taqdar_admin_model->questions_of_course($course_id),
            'objectives' => $this->taqdar_admin_model->objectives_of_course($course_id),
        ));
    }

    public function bind_save($course_id = 0)
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $course_id = (int) $course_id;
        $map       = $this->input->post('objective', false);
        $r         = $this->taqdar_admin_model->bind_questions($course_id, is_array($map) ? $map : array());

        // الرفض يقال ولا يبتلع: مرب يظن أنه ربط سؤالا ولم يربط
        // يبني عليه بقية عمله، ولا يكتشف الخلل إلا من شكوى طالب
        $msg = 'حفظ الربط: ' . $r['bound'] . ' سؤالا مربوطا'
             . ($r['cleared']  ? '، و' . $r['cleared'] . ' بلا هدف' : '') . '.';

        if ($r['rejected']) {
            $this->session->set_flashdata('error_message',
                $msg . ' ورفض ' . $r['rejected'] . ' — سؤال أو هدف من خارج هذه الدورة.');
        } else {
            $this->session->set_flashdata('flash_message', $msg);
        }

        redirect(site_url('taqdar_admin/bind/' . $course_id));
    }

    /* =====================================================================
       الاختبار التشخيصي — الأسئلة والنتائج

       رأس الاختبار وحدة موصوفة (`spec('diag_exams')`) لأنه صف بحقول.
       والأسئلة ليست حقولا في صف: كل سؤال صف في جدول ثان، له مستواه
       وخياراته وإجابته. فوصفها في `spec()` كان يعني حقلا نصيا يكتب فيه
       المسؤول JSON بيده — وهو ما يفعله phpMyAdmin بلا لوحة.
       ===================================================================== */

    /** شاشة أسئلة اختبار واحد. */
    public function diag_questions($exam_id = 0)
    {
        $this->load->model('taqdar_diag_model');

        $exam = $this->taqdar_diag_model->exam($exam_id);
        if (!$exam) show_404();

        /* بند الشريط هو بند الاختبارات نفسه: شاشة الأسئلة امتداد له لا
           وجهة مستقلة، وبند ثالث في القائمة لشيء يفتح من الجدول يزيد
           القائمة ولا يزيد ما يبلغ. */
        $this->render('tqa_diag_questions', 'أسئلة: ' . $exam['title'], array(
            'nav_key'   => 'tqa_diag_exams',
            'exam'      => $exam,
            'levels'    => Taqdar_diag_model::levels(),
            'by_level'  => $this->taqdar_diag_model->questions_by_level((int) $exam['id'], true),
            'readiness' => $this->taqdar_diag_model->readiness($exam),
            'dist'      => $this->taqdar_diag_model->distribution((int) $exam['id']),
            'plans'     => $this->taqdar_admin_model->options('plans'),
        ));
    }

    /** حفظ سؤال — إنشاء أو تحرير، والقرار في النموذج لا في الباب. */
    public function diag_question_save($exam_id = 0, $id = 0)
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_diag_model');

        /* TQ-QIMG · الرفع هنا لا في النموذج: `$this->input->post()` لا
           ترى `$_FILES` اصلا، والنموذج يستقبل مصفوفة `post` وحدها. فما
           يمرر اليه اسم الملف بعد ان يحفظ. */
        $post = $this->input->post(null, false);
        $img  = tq_qimage_upload('image');
        if ($img === false) {
            $this->session->set_flashdata('error_message',
                'الصورة مرفوضة — صيغة مقبولة (jpg · png · gif · webp) وحجم دون 4 ميجابايت.');
            redirect(site_url('taqdar_admin/diag_questions/' . (int) $exam_id));
            return;
        }
        $post['image'] = $img;

        $r = $this->taqdar_diag_model->save_question(
            (int) $exam_id, (int) $id, $post
        );

        if (!$r['ok']) {
            $this->session->set_flashdata('error_message', implode(' ', $r['errors']));
        } else {
            $this->session->set_flashdata('flash_message', 'حفظ السؤال.');
            $this->taqdar_admin_model->audit(
                $id ? 'update' : 'create',
                'tq_diag_questions#' . (int) $r['id'], null, null
            );
        }

        redirect(site_url('taqdar_admin/diag_questions/' . (int) $exam_id));
    }

    /** حذف سؤال. POST وحده: رابط GET يحذف ينفذ بمجرد جلبه. */
    public function diag_question_delete($exam_id = 0, $id = 0)
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_diag_model');
        $ok = $this->taqdar_diag_model->delete_question((int) $exam_id, (int) $id);

        if ($ok) {
            $this->taqdar_admin_model->audit('delete', 'tq_diag_questions#' . (int) $id, null, null);
        }
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message',
            $ok ? 'حذف السؤال.' : 'تعذر الحذف — السؤال ليس من هذا الاختبار.');

        redirect(site_url('taqdar_admin/diag_questions/' . (int) $exam_id));
    }

    /** شاشة أرقام الموقع. */
    public function stats()
    {
        $this->render('tqa_stats', 'أرقام الموقع');
    }

    /**
     * حفظ الأرقام — **upsert لا update**.
     *
     * `update` وحدها على مفتاح غير موجود تصيب صفر صف وترجع نجاحا:
     * تكتب القيمة، وتظهر رسالة «تم الحفظ»، ولا يحفظ شيء.
     */
    public function stats_save()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $keys = array('students','teachers','paths','subjects','lessons','books','hours','rating');
        $n = 0;
        foreach ($keys as $k) {
            $key = 'taqdar_stat_' . $k;
            $val = trim((string) $this->input->post($k));

            $exists = $this->db->where('key', $key)->count_all_results('settings') > 0;
            if ($exists) $this->db->where('key', $key)->update('settings', array('value' => $val));
            else         $this->db->insert('settings', array('key' => $key, 'value' => $val));
            $n++;
        }

        $this->session->set_flashdata('flash_message', 'حفظت ' . $n . ' قيمة. والفارغ منها لا يعرض على الموقع.');
        redirect(site_url('taqdar_admin/stats'), 'location', 302);
    }


    /** طلبات المعلمين — العرض والقرار في شاشة واحدة. */
    public function teachers()
    {
        $this->load->model('taqdar_teacher_model', 'tq_teach');
        $this->tq_teach->ensure_apply_schema();

        $apps = $this->db->select('a.id, a.phone, a.message, a.document, a.status,
                                   a.sample_url, a.sample_note, a.identity_ok, a.subject_hint,
                                   u.first_name, u.last_name, u.email', false)
                         ->from('applications a')
                         ->join('users u', 'u.id = a.user_id', 'left')
                         ->order_by('a.status', 'ASC')->order_by('a.id', 'DESC')
                         ->get()->result_array();

        $this->render('tqa_teachers', 'طلبات المعلمين', array(
            'apps' => $apps,
            'me'   => (int) $this->session->userdata('user_id'),
        ));
    }

    /** توثيق الهوية والمؤهل — الخطوة الثانية في فلو الانضمام. */
    public function teacher_identity()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_teacher_model', 'tq_teach');
        $this->tq_teach->ensure_apply_schema();

        $id = (int) $this->input->post('app_id');
        $on = (string) $this->input->post('identity_ok') === '1' ? 1 : 0;

        $this->db->where('id', $id)->update('applications', array('identity_ok' => $on));
        $this->taqdar_admin_model->audit('teacher_identity', 'application#' . $id, null,
                                         array('identity_ok' => $on));

        $this->session->set_flashdata('flash_message',
            $on ? 'وثقت هوية المتقدم ومؤهله.' : 'ألغي توثيق الهوية.');
        redirect(site_url('taqdar_admin/teachers'), 'location', 302);
    }

    /**
     * اعتماد المعلم أو رفضه.
     *
     * الاعتماد يفتح الحساب (`status=1`) ويجعله معلما؛ والرفض يبقيه
     * مغلقا **ويسجل** — قرار بلا أثر لا يراجع ولا يفسر لصاحبه.
     */
    public function teacher_review()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $id  = (int) $this->input->post('app_id');
        $act = (string) $this->input->post('act');
        $app = $this->db->where('id', $id)->get('applications')->row_array();
        if (!$app) { $this->session->set_flashdata('error_message', 'الطلب غير موجود.');
                     redirect(site_url('taqdar_admin/teachers'), 'location', 302); return; }

        $uid = (int) $app['user_id'];
        $who = $this->db->select('first_name, last_name, email')->where('id', $uid)
                        ->get('users')->row_array();

        /* الحساب حذف بعد أن تقدم صاحبه: القرار لا معنى له، ويقال ذلك بدل
           أن يظهر «اعتمد المعلم» ولا يعتمد أحد. */
        if (!$who) {
            $this->db->where('id', $id)->update('applications', array(
                'status' => 2, 'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewed_by' => (int) $this->session->userdata('user_id')));
            $this->session->set_flashdata('error_message',
                'حساب صاحب هذا الطلب لم يعد موجودا. أغلق الطلب ولم يعتمد أحد.');
            redirect(site_url('taqdar_admin/teachers'), 'location', 302);
            return;
        }

        $name = trim($who['first_name'] . ' ' . $who['last_name']) ?: 'المعلم';

        /* التوثيق قبل الاعتماد — والحارس هنا لا في الشاشة وحدها:
           الشاشة تعطل الزر، ومن يرسل النموذج بيده يصل إلى هذا السطر. */
        if ($act === 'approve' && (int) $app['identity_ok'] !== 1) {
            $this->session->set_flashdata('error_message',
                'وثق هوية المتقدم ومؤهله قبل الاعتماد — وهي الخطوة الثانية في مسار الانضمام.');
            redirect(site_url('taqdar_admin/teachers'), 'location', 302);
            return;
        }

        if ($act === 'approve') {
            $this->db->where('id', $uid)->update('users',
                array('status' => 1, 'is_instructor' => 1));
            $this->db->where('id', $id)->update('applications', array(
                'status' => 1, 'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewed_by' => (int) $this->session->userdata('user_id')));
            $msg = 'اعتمد المعلم، وصار بإمكانه الدخول إلى لوحته.';

        } else {
            $this->db->where('id', $uid)->update('users', array('status' => 0));
            $this->db->where('id', $id)->update('applications', array(
                'status' => 2, 'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewed_by' => (int) $this->session->userdata('user_id')));
            $msg = 'رفض الطلب، ويبقى الحساب مغلقا.';
        }

        /* TQ-TEACHER-TOLD — القرار يبلغ صاحبه.
           كان الاعتماد والرفض يغيران الحساب ولا يخطران أحدا: من سجل
           معلما يبقى يجرب الدخول كل يوم حتى يوفق صدفة بعد الاعتماد،
           ومن رفض ينتظر إلى الأبد. وصفحة التسجيل نفسها تعده بأن
           «نتواصل معك». */
        $this->notify_teacher_decision($uid, $name, $who['email'], $act === 'approve',
            trim((string) $this->input->post('reason')));

        $this->taqdar_admin_model->audit(
            $act === 'approve' ? 'teacher_approved' : 'teacher_rejected',
            'application#' . $id, null, array('user_id' => $uid));

        $this->session->set_flashdata('flash_message', $msg);
        redirect(site_url('taqdar_admin/teachers'), 'location', 302);
    }

    /**
     * يخطر المعلم بقرار طلبه — داخل المنصة وبالبريد.
     *
     * الإشعار داخل المنصة يكتب دائما، والبريد يحاول ولا يشترط: بريد غير
     * مضبوط يرد `false` بهدوء (انظر `Taqdar_mail_model`)، فلا يمنع
     * فشل الإرسال قرارا قد تم في القاعدة أصلا.
     */
    private function notify_teacher_decision($uid, $name, $email, $approved, $reason = '')
    {
        $title = $approved ? 'اعتمد طلب انضمامك معلما' : 'لم يقبل طلب انضمامك';

        $lines = $approved
            ? array(
                'مرحبا ' . $name . '،',
                'راجعنا طلبك ووافقنا عليه. صار بإمكانك الدخول إلى لوحة المعلم '
                . 'ورفع دروسك وفتح أوقاتك للحصص.',
              )
            : array(
                'مرحبا ' . $name . '،',
                'راجعنا طلب انضمامك ولم نتمكن من قبوله في هذه المرة.'
                . ($reason !== '' ? ' السبب: ' . $reason : ''),
                'يبقى حسابك مغلقا. وإن كان لديك ما يستدرك فتواصل معنا وسنعيد النظر.',
              );

        /* داخل المنصة: يقرؤه متى دخل، ولا يعتمد على وصول بريد. */
        try {
            $this->db->insert('notifications', array(
                'status'      => 0,
                'type'        => $approved ? 'teacher_approved' : 'teacher_rejected',
                'from_user'   => (int) $this->session->userdata('user_id'),
                'to_user'     => (int) $uid,
                'title'       => $title,
                'description' => implode(' ', array_slice($lines, 1)),
                'created_at'  => time(),      // طابع يونكس نصا — كما تقرؤه الشاشات
            ));
        } catch (Throwable $e) {
            log_message('error', 'teacher_review notification: ' . $e->getMessage());
        }

        if (empty($email)) {
            return;
        }

        $this->load->model('taqdar_mail_model');
        $this->taqdar_mail_model->send_lines($email, $title, $lines, $approved
            ? array('label' => 'ادخل إلى لوحتك', 'href' => site_url('teacher'))
            : array('label' => 'تواصل معنا',     'href' => site_url('contact')));
    }

    /** بيانات التحويل البنكي — وجهة المال. */
    public function bank()
    {
        $this->render('tqa_bank', 'بيانات التحويل البنكي');
    }

    /**
     * حفظ بيانات التحويل — upsert لا update، كما في `stats_save()`.
     *
     * والآيبان ينظف من المسافات: البنوك تعرضه مجموعات رباعية،
     * فيلصق كما نسخ ويصير رقما لا يطابق.
     */
    public function bank_save()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $keys = array('tq_bank_beneficiary', 'tq_bank_iban', 'tq_bank_name', 'tq_bank_note');
        foreach ($keys as $k) {
            $val = trim((string) $this->input->post($k));
            if ($k === 'tq_bank_iban') {
                $val = strtoupper(preg_replace('/\\s+/', '', $val));
            }
            $exists = $this->db->where('key', $k)->count_all_results('settings') > 0;
            if ($exists) $this->db->where('key', $k)->update('settings', array('value' => $val));
            else         $this->db->insert('settings', array('key' => $k, 'value' => $val));
        }

        $ok = trim((string) $this->input->post('tq_bank_iban')) !== ''
           && trim((string) $this->input->post('tq_bank_beneficiary')) !== '';

        $this->session->set_flashdata('flash_message', $ok
            ? 'حفظت البيانات، وصارت تظهر للطلاب في شاشة الاشتراك والفاتورة.'
            : 'حفظت — ولن تعرض للطلاب حتى يملأ اسم المستفيد والآيبان معا.');
        redirect(site_url('taqdar_admin/bank'), 'location', 302);
    }

    /* =====================================================================
       بوابة تاب
       ===================================================================== */

    /**
     * شاشة بوابة الدفع — الإعدادات وسجل المحاولات في مكان واحد.
     *
     * ولماذا شاشة مستقلة لا صف في `admin/payment_settings`: تلك الشاشة
     * تعرض حقول البوابة كلها في سطح واحد وتفرض `required` على كل مفتاح
     * متى فعلت البوابة. وتاب لها أربعة مفاتيح — اثنان للاختبار واثنان
     * للإنتاج — ومن يبدأ بالاختبار وحده لا يستطيع أن يحفظ حتى يخترع
     * مفاتيح إنتاج. والأخطر: الوضع الجاري لا يظهر في تلك الشاشة، وهو
     * أهم ما في هذه الصفحة كلها.
     */
    public function tap()
    {
        $this->load->model('taqdar_tap_model');
        $this->render('tqa_tap', 'بوابة الدفع — تاب', array(
            'cfg'      => $this->taqdar_tap_model->config(),
            'ready'    => $this->taqdar_tap_model->ready(),
            'attempts' => $this->taqdar_tap_model->attempts(30),
            'totals'   => $this->taqdar_tap_model->attempt_totals(),
        ));
    }

    /**
     * حفظ إعدادات تاب — upsert كما في `bank_save()`.
     *
     * والمفتاح الفارغ في الإرسال **لا يمحو المحفوظ**: الحقول تعرض ملثمة
     * (آخر أربعة محارف)، ومن فتح الشاشة ليبدل الوضع وحده لا يقصد أن
     * يمسح مفاتيحه. والمسح صريح بمربع «امسح هذا المفتاح».
     */
    public function tap_save()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_tap_model');
        $before = $this->taqdar_tap_model->config();

        $mode = (string) $this->input->post('tq_tap_mode') === 'live' ? 'live' : 'test';
        $vals = array(
            'tq_tap_enabled'  => (string) $this->input->post('tq_tap_enabled') === '1' ? '1' : '0',
            'tq_tap_mode'     => $mode,
            'tq_tap_merchant' => preg_replace('/\s+/', '', (string) $this->input->post('tq_tap_merchant')),
        );

        $clear = (array) $this->input->post('clear');
        foreach (array('test_secret', 'test_public', 'live_secret', 'live_public') as $k) {
            $sent = trim((string) $this->input->post('tq_tap_' . $k));
            if (in_array($k, $clear, true))      $vals['tq_tap_' . $k] = '';
            elseif ($sent !== '')                $vals['tq_tap_' . $k] = $sent;
        }

        foreach ($vals as $k => $v) {
            $exists = $this->db->where('key', $k)->count_all_results('settings') > 0;
            if ($exists) $this->db->where('key', $k)->update('settings', array('value' => $v));
            else         $this->db->insert('settings', array('key' => $k, 'value' => $v));
        }

        /* السجل يحفظ الحدث لا المفاتيح: سجل تدقيق فيه مفتاح سري يصير
           نسخة ثانية من السر في مكان لا يحرس كما يحرس. */
        $this->taqdar_admin_model->audit('tap_settings_save', 'settings#tap',
            array('enabled' => $before['enabled'], 'mode' => $before['mode']),
            array('enabled' => $vals['tq_tap_enabled'] === '1', 'mode' => $mode));

        $msg = 'حفظت إعدادات البوابة.';
        if ($vals['tq_tap_enabled'] === '1') {
            $secret = $vals['tq_tap_' . $mode . '_secret'] ?? $before['keys'][$mode . '_secret'];
            if (trim((string) $secret) === '') {
                $msg = 'حفظت — والبوابة مفعلة بلا مفتاح سري لوضع '
                     . ($mode === 'live' ? 'الإنتاج' : 'الاختبار')
                     . '، فلا يعرض للطالب خيار البطاقة حتى يضاف.';
            } elseif ($mode === 'live') {
                $msg = 'حفظت — والبوابة تعمل الآن في وضع الإنتاج: كل دفعة تخصم مالا حقيقيا.';
            } else {
                $msg = 'حفظت — والبوابة في وضع الاختبار: الدفع ينجح ظاهريا ولا يصل مال.';
            }
        } else {
            $msg = 'حفظت — والبوابة معطلة، فيبقى التحويل البنكي وحده معروضا للطلاب.';
        }

        $this->session->set_flashdata('flash_message', $msg);
        redirect(site_url('taqdar_admin/tap'), 'location', 302);
    }

    /** يسأل تاب عن المفتاح المحفوظ — «هل يعمل؟» يجاب بنداء لا بظن. */
    public function tap_probe()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_tap_model');
        $r = $this->taqdar_tap_model->probe((string) $this->input->post('mode'));

        $this->session->set_flashdata($r['ok'] ? 'flash_message' : 'error_message',
            'فحص مفتاح ' . ($r['mode'] === 'live' ? 'الإنتاج' : 'الاختبار') . ': ' . $r['message']);
        redirect(site_url('taqdar_admin/tap'), 'location', 302);
    }

    /**
     * إعادة سؤال تاب عن محاولة بعينها.
     *
     * الباب الذي يحتاج حين يقول طالب «دفعت ولم يفتح»: يقرأ من البوابة
     * نفسها ويسوي على ما ترده — لا يفعل بيد على قوله.
     */
    public function tap_recheck()
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $this->load->model('taqdar_tap_model');
        $r = $this->taqdar_tap_model->settle((string) $this->input->post('charge_id'), 'admin');

        $this->session->set_flashdata(!empty($r['ok']) ? 'flash_message' : 'error_message',
            !empty($r['ok'])
                ? ($r['message'] ?? 'سويت الدفعة.')
                : 'لم تسو: ' . implode(' ', (array) ($r['errors'] ?? array())));
        redirect(site_url('taqdar_admin/tap'), 'location', 302);
    }

    /**
     * إصلاح بنود الاشتراكات النشطة التي فعلت بلا مرور بـ`activate()`.
     *
     * `POST` لا `GET`: يكتب في القاعدة، ورابط يجلب بمجرد فتحه — أو
     * يجلبه زاحف — لا يصلح لفعل يكتب.
     */
    public function subscriptions_repair()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $this->load->model('taqdar_billing_model');
        $r = $this->taqdar_billing_model->repair_items();

        $msg = 'أصلح ' . (int) $r['fixed'] . ' اشتراكا، وترك '
             . (int) $r['skipped'] . ' لأن بنوده موجودة.';
        if (!empty($r['errors'])) $msg .= ' وتعذر: ' . implode(' ', $r['errors']);

        $this->session->set_flashdata('flash_message', $msg);
        redirect(site_url('taqdar_admin/subscriptions'), 'location', 302);
    }

}
