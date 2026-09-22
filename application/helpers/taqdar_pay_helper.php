<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-EXPRESS-PAY — الدفع المباشر في صفحاتنا: Apple Pay · Google Pay · البطاقة.
 *
 * كان كل دفع بالبطاقة يمر بصفحة تاب: يضغط المشتري «ادفع» فيغادر إلى صفحة
 * أخرى، ثم يختار هناك Apple Pay أو يكتب بطاقته. وهذه الطبقة تضع الأزرار
 * في شاشة التأكيد نفسها: زر Apple Pay يفتح نافذة الجهاز فيوثق ببصمته،
 * وزر Google Pay كذلك، وخانات البطاقة تكتب في صفحتنا (يرسمها سكربت تاب
 * في إطار منه، فلا يلمس خادمنا رقم بطاقة أبدا).
 *
 * **ولا محرك ثان.** ما يخرج من كل زر رمز لمرة واحدة (`tok_…`)، يرسل مع
 * نموذج الشراء نفسه في حقول ثلاثة (`tap_via` · `tap_token` · `tap_gpay`)،
 * والمتحكم يمرره إلى `Taqdar_tap_model::start()` نفسها. فالاشتراك يصدر
 * والفاتورة أولا كما كان، والدفعة تنشأ عند تاب بالرمز بدل صفحتها، والتسوية
 * والتفعيل والإخطار والقسمة كلها من الباب نفسه (`payment/tap/return`).
 *
 * **وبلا طريقة مفعلة لا شيء يتغير**: `tq_express_pay()` ترد نصا فارغا،
 * و`tq_pay_invoice_button()` تطبع النموذج القديم بحرفه.
 */

if (!function_exists('tq_tap_pay_input')) {
    /**
     * حقول الدفع المباشر من الطلب — لتمرر إلى `start()` كما هي.
     *
     * لا تفحص هنا: الفحص في النموذج (`resolve_source()`)، لأن هذه الحقول
     * تصل من متصفح، والطبقة التي توقع بالمفتاح السري هي التي تقرر.
     */
    function tq_tap_pay_input()
    {
        $CI = get_instance();
        return array(
            'via'   => (string) $CI->input->post('tap_via'),
            'token' => (string) $CI->input->post('tap_token'),
            'gpay'  => (string) $CI->input->post('tap_gpay'),
        );
    }
}

if (!function_exists('tq_express')) {
    /** إعداد الطرق المباشرة للطلب — مرة واحدة مهما نودي. */
    function tq_express()
    {
        static $cfg = null;
        if ($cfg !== null) return $cfg;

        $CI = get_instance();
        try {
            $CI->load->model('taqdar_tap_model');
            $cfg = $CI->taqdar_tap_model->express();
        } catch (Throwable $e) {
            log_message('error', 'TQ-EXPRESS-PAY: ' . $e->getMessage());
            $cfg = array('any' => false, 'methods' => array());
        }
        return $cfg;
    }
}

if (!function_exists('tq_express_pay')) {
    /**
     * لوح «ادفع مباشرة» — يوضع داخل بطاقة «طريقة الدفع» في أي شاشة شراء.
     *
     * @param array $o amount  المبلغ بالهللات — هو ما سيخصم، ويعرض في نافذة الجهاز
     *                 form    معرف النموذج الذي يرسل الرمز معه
     *                 label   ما يشترى — يظهر في نافذة Google Pay
     *                 note    سطر تحت الأزرار (اختياري)
     */
    function tq_express_pay($o)
    {
        $x = tq_express();
        if (empty($x['any'])) return '';

        $amount = (int) ($o['amount'] ?? 0);
        $form   = (string) ($o['form'] ?? '');
        if ($amount <= 0 || $form === '') return '';

        /* TQ-COUPON — المبلغ صافي الكود لا السعر: نافذة Apple Pay توقع على
           ما تعرضه، والفاتورة تصدر بالصافي. والكود يطبق بلا إعادة تحميل،
           فيتبعه السكربت بحدث `tq:coupon`. وصاف صفر (هدية كاملة) لا يدفع
           بشيء: يطبع اللوح مخفيا بالسعر الكامل ليظهر إن أزيل الكود. */
        $hide = false;
        if (!empty($o['coupon']) && function_exists('tq_coupon_net')) {
            $net = (int) tq_coupon_net($o['coupon'], $amount);
            if ($net <= 0) $hide = true;
            else           $amount = $net;
        }

        static $n = 0;
        $n++;

        /* بيانات المشتري لنافذة Apple Pay وخانات البطاقة: تاب تشترط الاسم
           والبريد، ومن يدفع هو صاحب الجلسة (الطالب أو ولي أمره). */
        $CI  = get_instance();
        $uid = (int) $CI->session->userdata('user_id');
        $u   = $uid > 0
             ? $CI->db->select('first_name, last_name, email')->where('id', $uid)->get('users')->row_array()
             : null;

        $methods = $x['methods'];
        /* خانات البطاقة سكربت واحد في الصفحة (`window.CardSDK.tokenize()`
           نداء عام بلا مقبض)، فلوح ثان في الصفحة نفسها بلا بطاقة. */
        if ($n > 1) $methods = array_values(array_diff($methods, array('card')));
        if (!$methods) return '';

        $cfg = array(
            'amount'       => $amount,
            'currency'     => $x['currency'],
            'country'      => $x['country'],
            'mode'         => $x['mode'],
            'public'       => $x['public'],
            'merchant'     => $x['merchant'],
            'domain'       => $x['domain'],
            'gpayMerchant' => $x['gpay_merchant'],
            'gpayName'     => $x['gpay_name'],
            'label'        => (string) ($o['label'] ?? ''),
            'methods'      => $methods,
            'lang'         => function_exists('tq_lang') ? tq_lang() : 'ar',
            'customer'     => array(
                'first' => trim((string) ($u['first_name'] ?? '')) ?: 'Taqdar',
                'last'  => trim((string) ($u['last_name'] ?? '')) ?: 'Customer',
                'email' => trim((string) ($u['email'] ?? '')),
            ),
            'msg' => array(
                'paying'   => t('جار إتمام الدفع… لا تغلق الصفحة.'),
                'failed'   => t('تعذر إتمام الدفع. لم يخصم منك شيء، ويمكنك المحاولة مرة أخرى.'),
                'cardFail' => t('راجع بيانات البطاقة ثم أعد المحاولة.'),
                'load'     => t('تعذر تحميل هذه الطريقة الآن. استعمل غيرها.'),
                'cardBtn'  => t('ادفع ____ ر.س بالبطاقة'),
            ),
        );

        $id = 'tqx' . $n;
        $sar = number_format($amount / 100, ($amount % 100) ? 2 : 0);

        $h  = '<div class="tqx" id="' . $id . '" data-tqx data-tqx-form="' . html_escape($form) . '"'
            . ' data-tqx-cfg="' . html_escape(json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '"'
            . ($hide ? ' hidden' : '') . '>' . "\n";
        $h .= '  <p class="tqx__h">' . t('ادفع مباشرة من هنا') . '</p>' . "\n";

        /* الزران مخفيان حتى يقول الجهاز إنه يدعمهما: زر Apple Pay على
           أندرويد، أو زر Google Pay بلا بطاقة في الحساب، يعرض للمشتري زرا
           لا يفعل شيئا. والإخفاء يرفعه السكربت متى تحقق. */
        $w = '';
        if (in_array('applepay', $methods, true)) {
            $w .= '    <div class="tqx__wallet tqx__wallet--apple" id="' . $id . '-apple" data-tqx-apple hidden></div>' . "\n";
        }
        if (in_array('googlepay', $methods, true)) {
            $w .= '    <div class="tqx__wallet tqx__wallet--gpay" id="' . $id . '-gpay" data-tqx-gpay hidden></div>' . "\n";
        }
        if ($w !== '') $h .= '  <div class="tqx__wallets">' . "\n" . $w . '  </div>' . "\n";

        if (in_array('card', $methods, true)) {
            $h .= '  <div class="tqx__card" data-tqx-card-box>' . "\n";
            $h .= '    <p class="tqx__sub"><svg aria-hidden="true"><use href="#i-card"></use></svg> '
                . t('أو اكتب بطاقتك — مدى · فيزا · ماستركارد') . '</p>' . "\n";
            $h .= '    <div class="tqx__cardform" id="' . $id . '-card" data-tqx-card></div>' . "\n";
            /* صنف الزر من الشاشة: الموقع يرسم `btn` والبوابة `tq-btn`، وزر
               بصنف لا تعرفه الورقة يخرج نصا عاريا في آخر ما يضغطه المشتري. */
            $btn = (string) ($o['btn'] ?? 'btn btn--primary btn--block');
            $h .= '    <button type="button" class="' . html_escape($btn) . ' tqx__cardpay" data-tqx-card-pay disabled>'
                . t('ادفع ____ ر.س بالبطاقة', array($sar)) . '</button>' . "\n";
            $h .= '  </div>' . "\n";
        }

        $h .= '  <p class="tqx__status" data-tqx-status role="status" aria-live="polite" hidden></p>' . "\n";
        $h .= '  <p class="tqx__safe"><svg aria-hidden="true"><use href="#i-lock"></use></svg> '
            . t('يعالج الدفع عبر تاب، ولا تحفظ بيانات بطاقتك عندنا.')
            . ($x['mode'] === 'test' ? ' <b>' . t('وضع الاختبار: لا يخصم مال حقيقي.') . '</b>' : '')
            . '</p>' . "\n";
        if (!empty($o['note'])) $h .= '  <p class="tqx__note">' . html_escape((string) $o['note']) . '</p>' . "\n";
        $h .= '</div>' . "\n";

        /* السكربت مرة واحدة للصفحة، ومؤجل: يجلب مكتبة كل طريقة عند الحاجة
           وحدها، فلا تثقل صفحة لم تفعل فيها Apple Pay بمكتبتها. */
        static $js = false;
        if (!$js) {
            $js = true;
            $h .= '<script src="' . tq_asset('js/tq-pay.js') . '" defer></script>' . "\n";
        }
        return $h;
    }
}

if (!function_exists('tq_pay_invoice_button')) {
    /**
     * زر «ادفع» لفاتورة قائمة — في قوائم الفواتير والحصص.
     *
     * بلا طريقة مباشرة: النموذج القديم نفسه إلى `student/pay-invoice`
     * (صفحة تاب). ومعها: رابط إلى شاشة دفع الفاتورة (`pay/<رقم>`) حيث
     * الأزرار الثلاثة وصفحة تاب معا — فالقائمة لا تحمل نافذة دفع في كل صف.
     */
    /**
     * @param int    $invoice_id
     * @param string $inner  محتوى الزر **وسما** (أيقونة ونص) — من القالب لا من مستخدم
     * @param string $class  أصناف الزر كما كانت في موضعه
     * @param string $action مسار النموذج القديم — `student/sessions/pay` للحصة
     * @param array  $fields حقوله المخفية — `invoice_id` افتراضا
     * @param string $form_attr سمات النموذج القديم كما كانت (صنف · نمط)
     */
    function tq_pay_invoice_button($invoice_id, $inner, $class = 'tq-btn tq-btn--primary',
                                   $action = 'student/pay-invoice', $fields = null, $form_attr = '')
    {
        $x = tq_express();
        if (!empty($x['any']) && (int) $invoice_id > 0) {
            return '<a class="' . html_escape($class) . '" href="' . base_url('pay/' . (int) $invoice_id) . '">'
                . $inner . '</a>';
        }
        if ($fields === null) $fields = array('invoice_id' => (int) $invoice_id);

        $h = '<form method="post" action="' . base_url($action) . '"' . $form_attr . '>' . tq_csrf();
        foreach ($fields as $k => $v) {
            $h .= '<input type="hidden" name="' . html_escape($k) . '" value="' . html_escape((string) $v) . '">';
        }
        return $h . '<button class="' . html_escape($class) . '" type="submit">' . $inner . '</button></form>';
    }
}
