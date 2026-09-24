<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-SIGNUP — إنشاء الحساب: قاعدة واحدة يناديها بابان.
 *
 * كان التسجيل كله في `Login::register()` — الفحص وبناء الصف والتكرار
 * والطلب والرمز — ممزوجا بالجلسة والتحويل. وذلك يعمل ما دام الباب
 * الوحيد نموذج ويب؛ فلما جاء التطبيق يسجل لم يجد ما يناديه إلا متحكما
 * يرد `redirect()`. ونسخة ثانية من قواعده في `Api_v1` تفترق عن أختها عند
 * أول تعديل: يشدد شرط الجوال هنا وينسى هناك، فيقبل التطبيق حسابا يرفضه
 * الموقع. وهي علة TQ-SOLD-NAME نفسها.
 *
 * فالقواعد هنا، والبابان يعرضان ولا يحكمان:
 *
 *   · `validate($in)` — المدخل الخام إلى مدخل نظيف أو أخطاء **بأسماء
 *     الحقول**. والأخطاء كلها تجمع لا أولها وحده: التطبيق يعلم كل حقل
 *     خاطئ في نموذجه مرة واحدة، والويب يعرض `error` (أولها بترتيب
 *     النموذج) كما كان يعرضه حرفا بحرف.
 *   · `create($clean)` — الصف والطلب والرمز ورسالة الترحيب. ولا جلسة ولا
 *     تحويل: ذاك شأن الباب.
 *
 * ومسار الرمز كله في `Taqdar_otp_model`، ولا يكرر منه شيء هنا.
 */
class Taqdar_signup_model extends CI_Model
{
    /** البوابات التي تسجل من خارج. والإدارة ليست منها بحال. */
    const GATES = array('student', 'teacher', 'parent');

    /** مستند المعلم: الصيغ والحجم — القاعدة نفسها في `instructor_application()`. */
    const DOC_EXT = array('pdf', 'jpg', 'jpeg', 'png');
    const DOC_MAX = 5242880;   // خمسة ميغابايت

    /**
     * يفحص المدخل ويرد `{ok, error, errors, clean}`.
     *
     * المفاتيح المقبولة (والباب يترجم إليها أسماء حقوله):
     *   gate · first_name · last_name · email · password · accept_terms ·
     *   age · grade_id · national_id · phone · phone_cc · otp_channel ·
     *   message · sample_url · sample_note · subject_hint · document ($_FILES).
     *
     * **والترتيب ترتيب `Login::register()` القديم**: `error` هو أول خطأ
     * بذلك الترتيب، فشاشة الويب تقرأ الرسالة نفسها التي كانت تقرؤها.
     */
    public function validate(array $in)
    {
        $s = function ($k) use ($in) { return isset($in[$k]) && !is_array($in[$k]) ? trim((string) $in[$k]) : ''; };

        /* البوابة قيمة مغلقة: ما سوى المعلم وولي الأمر طالب. */
        $gate = $s('gate');
        if (!in_array($gate, array('teacher', 'parent'), true)) $gate = 'student';

        $first = $s('first_name');
        $last  = $s('last_name');
        $email = $s('email');
        $pass  = isset($in['password']) && !is_array($in['password']) ? (string) $in['password'] : '';
        $age   = (int) $s('age');
        $grade = (int) $s('grade_id');
        $nid   = preg_replace('/\D/', '', $s('national_id'));
        $iso   = tq_phone_iso_ok($s('phone_cc') !== '' ? $s('phone_cc') : tq_phone_default_iso());

        $errors = array();
        $add = function ($field, $msg) use (&$errors) {
            if (!isset($errors[$field])) $errors[$field] = array($msg);
        };

        if (mb_strlen($first) < 2 || mb_strlen($first) > 40) {
            $add('first_name', 'اكتب اسمك الأول (حرفان على الأقل).');
        }
        if (mb_strlen($last) < 2 || mb_strlen($last) > 40) {
            $add('last_name', 'اكتب اسم عائلتك (حرفان على الأقل).');
        }
        /* خمسون: طول `users.email`. وما زاد كان يقص عند الحفظ فينشأ حساب
           ببريد لا يصل إليه شيء — ولا استعادة له. */
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 50) {
            $add('email', 'البريد الإلكتروني غير صحيح، أو أطول من خمسين محرفا.');
        }
        if (mb_strlen($pass) < 8) {
            $add('password', 'كلمة المرور ثمانية محارف على الأقل.');
        } elseif (strlen($pass) > 72) {
            /* bcrypt يقص عند اثنتين وسبعين بايت بلا إشعار. */
            $add('password', 'كلمة المرور أطول من اللازم. اجعلها دون اثنتين وسبعين خانة.');
        }
        if (!$this->truthy($in['accept_terms'] ?? null)) {
            $add('accept_terms', 'لا بد من الموافقة على الشروط وسياسة الخصوصية.');
        }

        $phone = '';
        $doc   = null;

        if ($gate === 'student') {
            /* TQ-AGE-REQUIRED — مدى صريح: الفارغ صفر فلا يمر. */
            if ($age < 5 || $age > 99) $add('age', 'اكتب عمرا صحيحا بين 5 و99.');
            $ph = tq_phone_check($s('phone'), $iso);
            if ($ph['ok']) $phone = $ph['e164']; else $add('phone', $ph['error']);
            /* معرف لا يقابل صفا فعالا كان يحفظ فيصير الحساب في صف لا وجود له. */
            if ($grade > 0 && $this->db->where(array('id' => $grade, 'active' => 1))
                                        ->count_all_results('grades') === 0) {
                $add('grade_id', 'الصف الدراسي المختار غير متاح. اختر من القائمة.');
            }
        } elseif ($gate === 'teacher') {
            /* TQ-TEACHER-GATE — الفحص كاملا قبل إنشاء الحساب. */
            if (!get_settings('allow_instructor')) {
                $add('gate', 'التسجيل معلما متوقف حاليا. تواصل معنا للانضمام.');
            } else {
                $ph = tq_phone_check($s('phone'), $iso);
                if ($ph['ok']) $phone = $ph['e164']; else $add('phone', $ph['error']);

                $doc = (isset($in['document']) && is_array($in['document'])) ? $in['document'] : null;
                $ext = ($doc && !empty($doc['name']))
                     ? strtolower((string) pathinfo($doc['name'], PATHINFO_EXTENSION)) : '';
                if ($doc === null || empty($doc['name'])) {
                    $add('document', 'مستند التعريف مطلوب لطلب الانضمام معلما.');
                } elseif ((int) $doc['error'] !== UPLOAD_ERR_OK) {
                    /* رفع فشل يصل بـ`name` مملوءا و`tmp_name` فارغا. */
                    $add('document', ((int) $doc['error'] === UPLOAD_ERR_INI_SIZE
                                   || (int) $doc['error'] === UPLOAD_ERR_FORM_SIZE)
                        ? 'حجم المستند أكبر مما يقبله الخادم. اختر ملفا أصغر.'
                        : 'تعذر رفع المستند. حاول مرة أخرى.');
                } elseif (!in_array($ext, self::DOC_EXT, true)) {
                    $add('document', 'صيغة المستند غير مقبولة. المقبول: PDF · JPG · PNG.');
                } elseif ((int) $doc['size'] > self::DOC_MAX) {
                    $add('document', 'حجم المستند أكبر من خمسة ميغابايت.');
                }
            }
        } else {
            /* ولي الأمر: حسابه يفتح فورا، والجوال مطلوب لتنبيهات الأبناء. */
            $ph = tq_phone_check($s('phone'), $iso);
            if ($ph['ok']) $phone = $ph['e164']; else $add('phone', $ph['error']);
        }

        /* رقم الهوية اختياري، وما لا يطابق صيغته يسقط لا يرد: رقم ناقص يصل
           الجهة فيرفض هناك بعد شهر. */
        if ($nid !== '' && !preg_match('/^[0-9]{10}$/', $nid)) $nid = '';

        $out = array('ok' => !$errors, 'error' => '', 'errors' => $errors, 'clean' => null);
        if ($errors) {
            $first_err = reset($errors);
            $out['error'] = $first_err[0];
            return $out;
        }

        $chan = $s('otp_channel');
        $out['clean'] = array(
            'gate'         => $gate,
            'first_name'   => $first,
            'last_name'    => $last,
            'email'        => $email,
            'password'     => $pass,
            'age'          => $age,
            'grade_id'     => $grade,
            'national_id'  => $nid,
            'phone'        => $phone,
            'otp_channel'  => in_array($chan, array('email', 'whatsapp'), true) ? $chan : '',
            'message'      => $s('message'),
            'sample_url'   => $s('sample_url'),
            'sample_note'  => $s('sample_note'),
            'subject_hint' => $s('subject_hint'),
        );
        return $out;
    }

    /**
     * ينشئ الحساب من مدخل نظيف ويرد:
     *
     *   {ok:true,  user_id, resumed, status, otp:{on, channel, route, sent}}
     *   {ok:false, code:'email_taken', error}
     *
     * `resumed` حساب قائم لم يؤكد عاد صاحبه يسجل ببريده (TQ-NO-TAKEOVER
     * يحرسه في `check_duplication()`)، و`sent` رد `Taqdar_otp_model::send()`
     * أو `null` حين لا رمز.
     */
    public function create(array $c)
    {
        $gate = $c['gate'];

        $data = array(
            'first_name' => html_escape($c['first_name']),
            'last_name'  => html_escape($c['last_name']),
            'email'      => html_escape($c['email']),
            'password'   => tq_password_hash($c['password']),
            /* العمود الموروث يبقى يكتب: شاشات Academy تقرؤه. والتأكيد
               الفعلي في `tq_otp`. */
            'verification_code' => rand(100000, 200000),
        );

        /* ── الرمز: أين يذهب؟ `signup_route()` وحدها تقرر ─────────────
           والقناة المطلوبة تقبل من القائمة لا كما جاءت: قيمة مخترعة كانت
           تصير وجهة إرسال. */
        $this->load->model('taqdar_otp_model');
        $route = $this->taqdar_otp_model->signup_route($gate, $c['email'], '', $c['phone']);
        $chan  = isset($route['channels'][$c['otp_channel']]) ? $c['otp_channel'] : $route['default'];
        $otp_on = $this->taqdar_otp_model->signup_required() && $chan !== '';

        /* TQ-INSTANT — الطالب وولي الأمر يفتحان فورا، والمعلم موقوف حتى
           الاعتماد. وحين لا قناة يفتح الحساب كما كان: ما لا يستطاع لا يشترط. */
        if ($otp_on) {
            $data['status'] = ($gate === 'teacher') ? 0 : 1;
        } else {
            $data['status'] = 1;
            if ($this->taqdar_otp_model->signup_required()) {
                log_message('info', 'TQ-OTP: لا قناة ترسل رمزا (' . $gate . ') — '
                    . 'فتح الحساب بلا تأكيد. اضبط البريد أو واتساب.');
            }
        }

        $data['wishlist']     = json_encode(array());
        $data['date_added']   = strtotime(date('Y-m-d H:i:s'));
        $data['social_links'] = json_encode(array('facebook' => '', 'twitter' => '', 'linkedin' => ''));
        $data['role_id']      = 2;
        $data['payment_keys'] = json_encode(array());

        $validity = $this->user_model->check_duplication('on_create', $data['email']);
        if ($validity !== 'unverified_user' && $validity != true) {
            return array('ok' => false, 'code' => 'email_taken',
                         'error' => 'هذا البريد مسجل بالفعل — أدخل كلمة المرور لتدخل، أو استعدها إن نسيتها.');
        }

        if ($gate === 'teacher') {
            /* TQ-TEACHER-PENDING و TQ-INSTRUCTOR-FLAG: موقوف حتى تراجع
               أوراقه، و`is_instructor` يكتب هنا وإلا بقي طالبا في اشتقاق
               الدور حتى بعد اعتماده. */
            $data['status']        = 0;
            $data['is_instructor'] = 1;
        }
        if ($c['phone'] !== '') $data['phone'] = $c['phone'];
        $data['tq_gate']           = $gate;
        $data['terms_accepted_at'] = date('Y-m-d H:i:s');

        if ($gate === 'student') {
            $data['age'] = $c['age'];
            if ($c['grade_id'] > 0) $data['grade_id'] = $c['grade_id'];
            /* والعمود يفحص: نسخة لم يمر عليها `ensure_columns()` ترمي
               «Unknown column» على أول من يسجل. */
            if ($c['national_id'] !== '') {
                try {
                    if ($this->db->field_exists('national_id', 'users')) {
                        $data['national_id'] = $c['national_id'];
                    }
                } catch (Throwable $e) { $this->db->reset_query(); }
            }
        }

        $resumed = ($validity === 'unverified_user');
        if ($resumed) {
            $this->user_model->register_user_update_code($data, $data['status']);
            $u = $this->db->select('id')->where('email', $data['email'])->get('users')->row_array();
            $user_id = $u ? (int) $u['id'] : 0;
        } else {
            $user_id = (int) $this->user_model->register_user($data);
        }

        /* طلب الانضمام معلما: `instructor_application()` تقرأ من `$_POST`
           و`$_FILES` لأنها كتبت لنموذج الويب. فيوحد ما تقرؤه هنا — البريد
           كما خزن (`html_escape`) وإلا فبريد فيه فاصلة عليا لا يطابق ما
           تبحث به فلا ينشأ الطلب ولا يظهر خطأ. */
        if ($gate === 'teacher') {
            $_POST['instructor'] = 'yes';
            $_POST['email']      = $data['email'];
            $_POST['phone']      = $c['phone'];
            foreach (array('message', 'sample_url', 'sample_note', 'subject_hint') as $k) {
                $_POST[$k] = $c[$k];
            }
            $this->user_model->instructor_application();
        }

        $sent = null;
        if ($otp_on && $user_id > 0) {
            $sent = $this->taqdar_otp_model->send('signup', $c['email'], $chan,
                        $route['channels'][$chan]['to'], $user_id, $c['first_name']);
            if (empty($sent['ok'])) {
                log_message('info', 'TQ-OTP: تعذر إرسال رمز التسجيل (' . $gate . ') — '
                    . (string) $sent['error']);
            }
        }

        /* رسالة الترحيب: المعلم ينتظر اعتمادا فلا يرحب به والرمز بوابته،
           ومن سواه يرحب به الآن كما كان. */
        if ($user_id > 0 && ($gate !== 'teacher' || !$otp_on)) {
            $this->welcome($user_id);
        }

        return array(
            'ok'      => $user_id > 0,
            'code'    => $user_id > 0 ? '' : 'create_failed',
            'error'   => $user_id > 0 ? '' : 'تعذر إنشاء الحساب. حاول مرة أخرى.',
            'user_id' => $user_id,
            'resumed' => $resumed,
            'status'  => (int) $data['status'],
            'otp'     => array('on' => $otp_on, 'channel' => $otp_on ? $chan : '',
                               'route' => $route, 'sent' => $sent),
        );
    }

    /**
     * يفتح الحساب بعد رمز صحيح — ما يفعله `Login::otp_verify()` بالحرف.
     *
     * **المعلم يبقى موقوفا**: الرمز يرفع تأكيد التواصل وحده، واعتماد
     * أوراقه قرار إدارة لا يرفعه رمز.
     */
    public function mark_verified($user_id, $gate)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return false;
        try {
            $upd = array('tq_verified_at' => date('Y-m-d H:i:s'));
            if ($gate !== 'teacher') $upd['status'] = 1;
            $this->db->where('id', $user_id)->update('users', $upd);
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-OTP: تعذر فتح الحساب بعد التأكيد — ' . $e->getMessage());
            return false;
        }
        /* رسالة الترحيب بعد التأكيد لا قبله: من لم يؤكد قد لا يكون صاحب البريد. */
        if ($gate !== 'teacher') $this->welcome($user_id);
        return true;
    }

    /**
     * رسالة الترحيب الموروثة (`Email_model::signup_mail`) — تابعة لا شرط.
     * الحساب كتب قبلها، ورد التسجيل كله بخطأ رسالة بعده يترك صاحبه يظن
     * أنه لم ينشأ فيعيد التسجيل فيقرأ «البريد مسجل». فيسجل ولا يرمى.
     */
    private function welcome($user_id)
    {
        try {
            $this->email_model->signup_mail((int) $user_id);
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-SIGNUP: تعذرت رسالة الترحيب للمستخدم ' . (int) $user_id
                . ' — ' . $e->getMessage());
        }
    }

    /* =====================================================================
       TQ-QUICK-BUY — الحساب الخفيف من شاشة الدفع
       ===================================================================== */

    /**
     * يفحص حقول الشراء السريع الثلاثة: الاسم والجوال والبريد.
     *
     * **ولماذا البريد مع الجوال لا الجوال وحده**: البريد هو الباب الوحيد
     * الذي يعود منه صاحب الحساب إليه — «نسيت كلمة المرور» تعمل به، والفاتورة
     * وتعليمات التحويل تصل إليه، وتاب تشترطه في طلب الدفع. وحساب بجوال وحده
     * ينشأ بلا كلمة مرور يعرفها صاحبه، فأول جلسة تنتهي تقفله على اشتراك
     * دفع ثمنه — ولا شيء في المنصة يفتحه له إلا الإدارة بيدها.
     *
     * والقواعد قواعد `validate()` نفسها حرفا: طول البريد، ودولة الجوال.
     * وما سقط سقط عمدا — كلمة المرور والعمر والصف تطلب **بعد** الدفع
     * (`/account/complete`)، ولا شيء منها يحتاجه الشراء.
     *
     * @return array {ok, error, errors, clean:{first_name,last_name,email,phone}}
     */
    public function validate_quick(array $in)
    {
        $s = function ($k) use ($in) { return isset($in[$k]) && !is_array($in[$k]) ? trim((string) $in[$k]) : ''; };

        /* الاسم حقل واحد لا حقلان: من يكتب «محمد أحمد» في خانة واحدة أسرع
           ممن يتنقل بين خانتين، والقسمة هنا لا عنده. */
        $name  = preg_replace('/\s+/u', ' ', $s('full_name'));
        $email = $s('email');
        $iso   = tq_phone_iso_ok($s('phone_cc') !== '' ? $s('phone_cc') : tq_phone_default_iso());

        $errors = array();
        $add = function ($field, $msg) use (&$errors) {
            if (!isset($errors[$field])) $errors[$field] = array($msg);
        };

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $add('full_name', 'اكتب اسمك (حرفان على الأقل).');
        }
        $ph = tq_phone_check($s('phone'), $iso);
        if (!$ph['ok']) $add('phone', $ph['error']);
        /* خمسون: طول `users.email` — والقاعدة نفسها في `validate()`. */
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 50) {
            $add('email', 'البريد الإلكتروني غير صحيح، أو أطول من خمسين محرفا.');
        }

        $out = array('ok' => !$errors, 'error' => '', 'errors' => $errors, 'clean' => null);
        if ($errors) {
            $first_err = reset($errors);
            $out['error'] = $first_err[0];
            return $out;
        }

        $parts = explode(' ', $name, 2);
        $out['clean'] = array(
            'first_name' => mb_substr($parts[0], 0, 40),
            'last_name'  => isset($parts[1]) ? mb_substr($parts[1], 0, 40) : '',
            'email'      => $email,
            'phone'      => $ph['e164'],
        );
        return $out;
    }

    /**
     * ينشئ حساب الطالب الخفيف ويرد `{ok, user_id, code, error}`.
     *
     * **حساب حقيقي لا ضيف**: الفاتورة والاشتراك والاستحقاق والقيد كلها
     * تشير إلى `users.id`، و«الدفع بلا حساب» يعني صفا ثانيا لكل واحد منها
     * أو اشتراكا يتيما لا يفتح لأحد. فالحساب ينشأ صامتا بما كتب، وكل ما بعد
     * الفاتورة يعمل له بلا سطر.
     *
     * وكلمة المرور **عشوائية لا يعرفها أحد** (`random_bytes`): صاحبها يضعها
     * بعد الدفع، أو يستعيدها ببريده متى شاء. والعلامة `tq_quick_<معرف>` في
     * `settings` تقول إن الحساب لم يكمل — وهي نمط `tq_social_pw_<معرف>` نفسه:
     * أثر لا عمود، ويمحى حين يضع صاحبه كلمته.
     *
     * **والبريد القائم لا يسلم حسابه**: `email_taken` يرد، والشاشة تفتح لوح
     * «لدي حساب» ببريده. ولو دخل الزائر حسابا قائما بكتابة بريد صاحبه لصار
     * كل حساب في المنصة مفتوحا لمن يعرف بريده.
     */
    public function create_quick(array $c)
    {
        $validity = $this->user_model->check_duplication('on_create', html_escape($c['email']));
        if ($validity !== true) {
            /* `unverified_user` كذلك: حساب قائم لم يؤكد له صاحب يعرف كلمته،
               والدخول به من هنا تسليم له بلا كلمة. */
            return array('ok' => false, 'user_id' => 0, 'code' => 'email_taken',
                         'error' => 'هذا البريد له حساب في تقدر — اكتب كلمة المرور لتدخل وتكمل الدفع.');
        }

        $data = array(
            'first_name'        => html_escape($c['first_name']),
            'last_name'         => html_escape($c['last_name']),
            'email'             => html_escape($c['email']),
            'password'          => tq_password_hash(bin2hex(random_bytes(24))),
            'verification_code' => rand(100000, 200000),
            'status'            => 1,
            'phone'             => $c['phone'],
            'tq_gate'           => 'student',
            'role_id'           => 2,
            'wishlist'          => json_encode(array()),
            'date_added'        => time(),
            'social_links'      => json_encode(array('facebook' => '', 'twitter' => '', 'linkedin' => '')),
            'payment_keys'      => json_encode(array()),
            /* الزر نفسه يقول «بالمتابعة توافق على الشروط» — وهو سطر تحته
               لا مربع قبله، كما في أزرار جوجل وأبل (TQ-SOCIAL-TERMS). */
            'terms_accepted_at' => date('Y-m-d H:i:s'),
        );

        /* نقرتان على «ادفع» في الثانية نفسها تمران من الفحص أعلاه معا، و
           `uk_email` يرد الثانية. و`db_debug` يجعل ذلك الرد صفحة خطأ قاعدة
           بيضاء في وجه من يدفع — فيطفأ حول الإدراج، والثانية تقرأ «له حساب»
           وتدخل من بابه. */
        $dbg = $this->db->db_debug;
        $this->db->db_debug = false;
        $user_id = (int) $this->user_model->register_user($data);
        $this->db->db_debug = $dbg;
        if ($user_id <= 0) {
            if ($this->user_model->check_duplication('on_create', $data['email']) !== true) {
                return array('ok' => false, 'user_id' => 0, 'code' => 'email_taken',
                             'error' => 'هذا البريد له حساب في تقدر — اكتب كلمة المرور لتدخل وتكمل الدفع.');
            }
            log_message('error', 'TQ-QUICK-BUY: تعذر إدراج الحساب — ' . json_encode($this->db->error()));
            return array('ok' => false, 'user_id' => 0, 'code' => 'create_failed',
                         'error' => 'تعذر إنشاء الحساب. حاول مرة أخرى.');
        }

        $this->mark_quick($user_id);
        $this->welcome($user_id);

        return array('ok' => true, 'user_id' => $user_id, 'code' => '', 'error' => '');
    }

    /** هل هذا حساب خفيف لم يضع صاحبه كلمة مروره بعد؟ */
    public function is_quick($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return false;
        try {
            return $this->db->where('key', 'tq_quick_' . $user_id)->count_all_results('settings') > 0;
        } catch (Throwable $e) {
            $this->db->reset_query();
            return false;
        }
    }

    public function mark_quick($user_id)
    {
        try {
            $this->db->insert('settings', array('key' => 'tq_quick_' . (int) $user_id,
                                                'value' => (string) time()));
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-QUICK-BUY: تعذر وسم الحساب ' . (int) $user_id . ' — ' . $e->getMessage());
        }
    }

    /** وضع صاحب الحساب كلمته — بيده أو من «نسيت كلمة المرور». */
    public function clear_quick($user_id)
    {
        try {
            $this->db->where('key', 'tq_quick_' . (int) $user_id)->delete('settings');
        } catch (Throwable $e) {
            $this->db->reset_query();
        }
    }

    /**
     * قنوات الرمز لحساب قائم — من صفه لا من الطلب (`otp_start()` نفسها):
     * قناة تأتي في الجسم بوجهتها تجعل النقطة بابا يرسل إلى أي رقم.
     */
    public function route_of(array $u)
    {
        $this->load->model('taqdar_otp_model');
        $gate = (string) ($u['tq_gate'] ?? '') !== '' ? (string) $u['tq_gate'] : 'student';
        return $this->taqdar_otp_model->signup_route($gate, (string) $u['email'],
            (string) ($u['guardian_email'] ?? ''), (string) ($u['phone'] ?? ''));
    }

    /** JSON يرسل `true`، والنموذج `"1"`. */
    private function truthy($v)
    {
        return in_array($v, array(true, 1, '1', 'true', 'on', 'yes'), true);
    }
}
