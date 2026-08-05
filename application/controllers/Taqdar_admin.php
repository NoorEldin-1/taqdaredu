<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * وحدات تقدّر داخل لوحة الإدارة.
 *
 * تُعرَض في **غلاف اللوحة نفسه** (`backend/index.php`) لا في غلاف ثانٍ:
 * لوحتان تعنيان قائمتين وترويستين وحالتَي دخول، ويكفي أن تُنسى إحداهما
 * عند إضافة صلاحية ليصير الأمن ثقبًا. فالشاشة الجديدة ملفّ في
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

        // نفس بوّابة اللوحة القديمة: لا نخترع فحصًا موازيًا يمكن أن يتخلّف عنها
        $this->user_model->check_session_data('admin');

        if ($this->session->userdata('admin_login') != true) {
            redirect(site_url('login'));
        }

        // وحدات تقدّر صلاحية مستقلّة كسائر الوحدات
        check_permission('taqdar');
    }

    /** الغلاف القديم يُدرج `admin/<page_name>.php` — فنكتفي بتسمية الصفحة. */
    private function render($page_name, $title, $data = array())
    {
        $data['page_name']  = $page_name;
        $data['page_title'] = $title;
        $this->load->view('backend/index.php', $data);
    }

    public function index()
    {
        $this->overview();
    }

    /** لوحة جاهزية: ما امتلأ وما بقي فارغًا، بلا تجميل. */
    public function overview()
    {
        $this->render('tqa_overview', 'منصّة تقدّر', array(
            'readiness' => $this->taqdar_admin_model->readiness(),
        ));
    }

    /* =====================================================================
       الوحدات العامّة
       ===================================================================== */

    public function module($key = '')
    {
        $spec = $this->taqdar_admin_model->spec($key);
        if (!$spec) show_404();

        $this->render('tqa_list', $spec['title'], array(
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

        $this->session->set_flashdata('flash_message', 'تمّ الحفظ.');
        redirect(site_url('taqdar_admin/module/' . $key));
    }

    public function delete($key = '', $id = 0)
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $ok = $this->taqdar_admin_model->remove($key, (int) $id);
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message',
            $ok ? 'تمّ الحذف.' : 'تعذّر الحذف — الوحدة لا تسمح به.');
        redirect(site_url('taqdar_admin/module/' . $key));
    }

    /* =====================================================================
       الاشتراكات
       ===================================================================== */

    public function subscriptions()
    {
        // الاسم يُجلب بضمّة واحدة لا باستعلام لكل صفّ
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
     * هل توجد بوّابة دفع مفعّلة فعلًا؟
     * الإعدادات تخزّنها JSON بمفتاح `active`، ووجود المفتاح لا يعني تفعيله.
     */
    private function gateway_active()
    {
        foreach (array('paypal', 'stripe_keys') as $key) {
            $raw = get_settings($key);
            if (!$raw) continue;
            $cfg = json_decode($raw, true);
            if (is_array($cfg) && isset($cfg[0]['active']) && (string) $cfg[0]['active'] === '1') {
                return true;
            }
        }
        return false;
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
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message',
            $ok ? 'فُعِّل الاشتراك وسُدِّدت فاتورته.' : 'تعذّر التفعيل.');
        redirect(site_url('taqdar_admin/subscriptions'));
    }

    public function subscription_cancel($id = 0)
    {
        if ($this->input->method(true) !== 'POST') show_404();

        $ok = $this->taqdar_billing_model->cancel((int) $id, 'ألغته الإدارة');
        $this->session->set_flashdata($ok ? 'flash_message' : 'error_message',
            $ok ? 'أُلغي التجديد — ويبقى الاشتراك صالحًا حتى تاريخ انتهائه.' : 'تعذّر الإلغاء.');
        redirect(site_url('taqdar_admin/subscriptions'));
    }

    /* =====================================================================
       البريد الصادر
       ===================================================================== */

    /** المفاتيح التي تُدار من هذه الشاشة، وكلّها في جدول `settings`. */
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

    /** مضبوط = خادم ومستخدم وكلمة مرور ومرسِل. ما دون ذلك ادّعاء. */
    private function mail_configured($m = null)
    {
        $m = $m ?: $this->mail_values();
        foreach (array('smtp_host', 'smtp_user', 'smtp_pass', 'smtp_from_email') as $k) {
            if (trim($m[$k]) === '') return false;
        }
        return true;
    }

    public function mail()
    {
        $m = $this->mail_values();
        $this->render('tqa_mail', 'البريد الصادر', array(
            'mail'         => $m,
            'configured'   => $this->mail_configured($m),
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
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $errors[] = 'المرسِل الظاهر ليس بريدًا صالحًا.';
        if ($port < 1 || $port > 65535) $errors[] = 'المنفذ خارج المدى.';

        // كلمة المرور: الفارغ يعني «أبقِ المحفوظة» لا «امسحها»
        $pass_in  = (string) $this->input->post('smtp_pass');
        $pass_old = (string) get_settings('smtp_pass');
        $pass     = ($pass_in === '') ? $pass_old : $pass_in;
        if ($pass === '') $errors[] = 'كلمة المرور مطلوبة في أوّل ضبط.';

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
            // كلمة المرور لا تُهرَّب: `html_escape` يفسد ما فيه & أو < أو "
            if ($this->db->where('key', $k)->count_all_results('settings') > 0) {
                $this->db->where('key', $k)->update('settings', array('value' => $v));
            } else {
                $this->db->insert('settings', array('key' => $k, 'value' => $v));
            }
        }

        // السرّ لا يُقيَّد في السجلّ — يُقيَّد أنه تغيّر
        $this->taqdar_admin_model->audit('mail_settings_update', 'settings',
            null, array('host' => $host, 'user' => $user, 'from' => $from,
                        'pass_changed' => $pass_in !== ''));

        $this->session->set_flashdata('flash_message', 'حُفظت إعدادات البريد. أرسِل رسالة فحص للتأكّد.');
        redirect(site_url('taqdar_admin/mail'));
    }

    /**
     * يرسل رسالة حقيقية بالمسار الذي تستعمله المنصّة نفسها — لا بمسار موازٍ
     * يُظهر نجاحًا لا يتكرّر في الاستعمال الفعلي.
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
            $this->session->set_flashdata('error_message', 'احفظ بيانات الخادم أوّلًا.');
            redirect(site_url('taqdar_admin/mail'));
            return;
        }

        $this->load->library('email');
        $this->email->clear(true);
        $this->email->initialize(array(
            'protocol'     => 'smtp',
            'smtp_host'    => get_settings('smtp_host'),
            'smtp_port'    => get_settings('smtp_port'),
            'smtp_user'    => get_settings('smtp_user'),
            'smtp_pass'    => get_settings('smtp_pass'),
            'smtp_crypto'  => get_settings('smtp_crypto'),
            'mailtype'     => 'html',
            'newline'      => "\r\n",
            'charset'      => 'utf-8',
            'smtp_timeout' => 20,
        ));
        $this->email->set_crlf("\r\n");
        $this->email->from(get_settings('smtp_from_email'), get_settings('system_name') ?: 'تقدّر');
        $this->email->to($to);
        $this->email->subject('رسالة فحص من منصّة تقدّر');
        $this->email->message(
            '<div dir="rtl" style="font-family:sans-serif">'
            . '<p>هذه رسالة فحص من لوحة تقدّر.</p>'
            . '<p>وصولها يعني أن إعدادات البريد صحيحة، وأن استعادة كلمة المرور '
            . 'والتقارير والتنبيهات صارت تعمل.</p>'
            . '<p style="color:#657280;font-size:13px">أُرسلت في '
            . date('Y-m-d H:i') . '</p></div>'
        );

        if ($this->email->send(false)) {
            $this->session->set_flashdata('flash_message', 'أُرسلت الرسالة إلى ' . $to . ' — تحقّق من الصندوق (ومن مجلّد المهملات).');
        } else {
            $this->session->set_flashdata('error_message', 'لم تُرسَل الرسالة. السبب أدناه كما قاله الخادم.');
            $this->session->set_flashdata('mail_debug', $this->email->print_debugger(array('headers')));
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
            $new === '1' ? 'فُعِّلت التنبيهات البريدية.' : 'أُوقفت التنبيهات البريدية.');
        redirect(site_url('taqdar_admin/mail'));
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

    /** يقرأ ويفحص ويعرض — **ولا يكتب**. الكتابة خطوة مستقلّة يؤكّدها الإنسان. */
    public function import_preview()
    {
        if ($this->input->method(true) !== "POST") show_404();

        if (empty($_FILES['curriculum']['name'])) {
            $this->session->set_flashdata('error_message', 'اختر ملفًّا أوّلًا.');
            redirect(site_url('taqdar_admin/import'));
            return;
        }
        if ((int) $_FILES['curriculum']['size'] > 2097152) {
            $this->session->set_flashdata('error_message', 'الملفّ أكبر من ٢ ميغابايت.');
            redirect(site_url('taqdar_admin/import'));
            return;
        }

        $ext = strtolower(pathinfo($_FILES['curriculum']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv', 'json'), true)) {
            $this->session->set_flashdata('error_message', 'يُقبل CSV أو JSON فقط.');
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

        // تُحفظ المعاينة في الجلسة لا في حقل مخفيّ: حقلٌ يحمل الصفوف يعود
        // من المتصفّح فيُكتب ما لم يُفحَص
        $this->session->set_userdata('tq_import_rows', $preview);
        $this->session->set_flashdata('import_preview', $preview);
        redirect(site_url('taqdar_admin/import'));
    }

    public function import_commit()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $rows = $this->session->userdata('tq_import_rows');
        if (!is_array($rows) || !$rows) {
            $this->session->set_flashdata('error_message', 'لا معاينة محفوظة. أعِد رفع الملفّ.');
            redirect(site_url('taqdar_admin/import'));
            return;
        }

        $this->load->model('taqdar_import_model');
        $st = $this->taqdar_import_model->commit($rows);
        $this->session->unset_userdata('tq_import_rows');

        $this->session->set_flashdata('flash_message',
            'تمّ الاستيراد: أُنشئ ' . $st['created'] . ' مسارًا، وحُدِّث ' . $st['updated'] .
            '، وأُنشئ ' . $st['subjects'] . ' مادة و' . $st['grades'] . ' صفًّا' .
            ($st['skipped'] ? '، وتُجووِز ' . $st['skipped'] . ' صفًّا معطوبًا.' : '.'));
        redirect(site_url('taqdar_admin/module/paths'));
    }

    /** ملفّ نموذجيّ بالأعمدة الصحيحة ومثال مملوء — أسرع من شرح مكتوب. */
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

        // شارة BOM ليفتحه Excel بالعربية سليمةً لا رموزًا
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $r) {
            $out .= '"' . implode('","', $r) . '"' . "\r\n";
        }
        $this->output->set_output($out);
    }

    /* =====================================================================
       ربط الأسئلة بالأهداف

       النطاق دورة لا درس: السؤال يسكن درس الاختبار والهدف يسكن درس الفيديو،
       فلا يلتقيان إلّا فوقهما — في الدورة التي تضمّ الدرسين.
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

        // الرفض يُقال ولا يُبتلع: مربٍّ يظنّ أنه ربط سؤالًا ولم يُربَط
        // يبني عليه بقية عمله، ولا يكتشف الخلل إلّا من شكوى طالب
        $msg = 'حُفِظ الربط: ' . $r['bound'] . ' سؤالًا مربوطًا'
             . ($r['cleared']  ? '، و' . $r['cleared'] . ' بلا هدف' : '') . '.';

        if ($r['rejected']) {
            $this->session->set_flashdata('error_message',
                $msg . ' ورُفِض ' . $r['rejected'] . ' — سؤال أو هدف من خارج هذه الدورة.');
        } else {
            $this->session->set_flashdata('flash_message', $msg);
        }

        redirect(site_url('taqdar_admin/bind/' . $course_id));
    }

    /** شاشة أرقام الموقع. */
    public function stats()
    {
        $this->render('tqa_stats', 'أرقام الموقع');
    }

    /**
     * حفظ الأرقام — **upsert لا update**.
     *
     * `update` وحدها على مفتاح غير موجود تُصيب صفر صفّ وتُرجع نجاحًا:
     * تُكتب القيمة، وتظهر رسالة «تمّ الحفظ»، ولا يُحفظ شيء.
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

        $this->session->set_flashdata('flash_message', 'حُفظت ' . $n . ' قيمة. والفارغ منها لا يُعرَض على الموقع.');
        redirect(site_url('taqdar_admin/stats'), 'location', 302);
    }


    /** طلبات المعلّمين. */
    public function teachers()
    {
        $apps = $this->db->select('a.id, a.phone, a.message, a.document, a.status,
                                   u.first_name, u.last_name, u.email', false)
                         ->from('applications a')
                         ->join('users u', 'u.id = a.user_id', 'left')
                         ->order_by('a.status', 'ASC')->order_by('a.id', 'DESC')
                         ->get()->result_array();
        $this->render('tqa_teachers', 'طلبات المعلّمين', array('apps' => $apps));
    }

    /**
     * اعتماد المعلّم أو رفضه.
     *
     * الاعتماد يفتح الحساب (`status=1`) ويجعله معلّمًا؛ والرفض يُبقيه
     * مغلقًا **ويُسجَّل** — قرارٌ بلا أثر لا يُراجَع ولا يُفسَّر لصاحبه.
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
        if ($act === 'approve') {
            $this->db->where('id', $uid)->update('users',
                array('status' => 1, 'is_instructor' => 1));
            $this->db->where('id', $id)->update('applications', array(
                'status' => 1, 'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewed_by' => (int) $this->session->userdata('user_id')));
            $msg = 'اعتُمد المعلّم، وصار بإمكانه الدخول إلى لوحته.';
        } else {
            $this->db->where('id', $uid)->update('users', array('status' => 0));
            $this->db->where('id', $id)->update('applications', array(
                'status' => 2, 'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewed_by' => (int) $this->session->userdata('user_id')));
            $msg = 'رُفض الطلب، ويبقى الحساب مغلقًا.';
        }

        $this->session->set_flashdata('flash_message', $msg);
        redirect(site_url('taqdar_admin/teachers'), 'location', 302);
    }

    /** بيانات التحويل البنكيّ — وجهةُ المال. */
    public function bank()
    {
        $this->render('tqa_bank', 'بيانات التحويل البنكيّ');
    }

    /**
     * حفظ بيانات التحويل — upsert لا update، كما في `stats_save()`.
     *
     * والآيبان يُنظَّف من المسافات: البنوك تعرضه مجموعاتٍ رباعية،
     * فيُلصَق كما نُسِخ ويصير رقمًا لا يُطابِق.
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
            ? 'حُفظت البيانات، وصارت تظهر للطلاب في شاشة الاشتراك والفاتورة.'
            : 'حُفظت — ولن تُعرض للطلاب حتى يُملأ اسمُ المستفيد والآيبان معًا.');
        redirect(site_url('taqdar_admin/bank'), 'location', 302);
    }

    /**
     * إصلاح بنود الاشتراكات النشطة التي فُعِّلت بلا مرور بـ`activate()`.
     *
     * `POST` لا `GET`: يكتب في القاعدة، ورابطٌ يُجلَب بمجرّد فتحه — أو
     * يجلبه زاحفٌ — لا يصلح لفعلٍ يكتب.
     */
    public function subscriptions_repair()
    {
        if ($this->input->method(true) !== "POST") show_404();

        $this->load->model('taqdar_billing_model');
        $r = $this->taqdar_billing_model->repair_items();

        $msg = 'أُصلح ' . (int) $r['fixed'] . ' اشتراكًا، وتُرك '
             . (int) $r['skipped'] . ' لأنّ بنوده موجودة.';
        if (!empty($r['errors'])) $msg .= ' وتعذّر: ' . implode(' ', $r['errors']);

        $this->session->set_flashdata('flash_message', $msg);
        redirect(site_url('taqdar_admin/subscriptions'), 'location', 302);
    }

}
