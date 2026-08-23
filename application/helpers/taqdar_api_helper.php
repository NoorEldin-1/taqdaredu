<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أدوات واجهة البرمجة — الغلاف والتحقق والتشكيل.
 *
 * كل ما هنا **دوال خالصة** لا تعرف المتحكم: تبني مصفوفة أو تفحص قيمة.
 * والإخراج نفسه في `Api_v1::respond()` — فالنوع الوحيد الذي يعرف
 * الترويسات ورمز الحال موضع واحد.
 *
 * ولا يوضع هذا في `libraries/`: ذاك مجلد طرف ثالث لا يعدل (CLAUDE.md)،
 * وملف تقدر فيه يوهم لاحقا أنه من القالب فيضيع مع أول تحديث.
 */

if (!function_exists('tq_api_ok')) {
    /**
     * غلاف النجاح: `{data, message, meta}`.
     *
     * والغلاف ثابت لا يختصر: رد يعيد المصفوفة عارية مرة وداخل `data` مرة
     * يجعل عميل Flutter يكتب فرعين لكل نقطة. و`meta` تبقى حاضرة ولو
     * فارغة لأن الترقيم يضاف إليها لاحقا فلا يتغير الشكل.
     */
    function tq_api_ok($data = null, $message = '', $meta = array())
    {
        return array(
            'data'    => $data,
            'message' => (string) $message,
            'meta'    => (object) $meta,
        );
    }
}

if (!function_exists('tq_api_error')) {
    /**
     * غلاف الخطأ: `{message, code, errors}`.
     *
     * `code` نص ثابت لا يترجم — عليه يفرع التطبيق. و`message` عربية لأنها
     * هي التي تعرض للمستخدم، فلا يترجم Flutter رسائل الخادم من جديد.
     * و`errors` خريطة حقل إلى قائمة رسائل، فيعلم الحقل نفسه في النموذج.
     */
    function tq_api_error($message, $code = 'error', $errors = array())
    {
        $out = array(
            'message' => (string) $message,
            'code'    => (string) $code,
        );
        if (!empty($errors)) $out['errors'] = $errors;
        return $out;
    }
}

if (!function_exists('tq_api_money')) {
    /**
     * المال يخرج بثلاثة أوجه لا بواحد.
     *
     * القاعدة تخزن **هللات** عددا صحيحا (39900 = 399.00 ر.س)، فلو رد
     * الخادم الكسر وحده لحسب العميل بعائم وأخطأ الهللة في الجمع. والصيغة
     * الجاهزة ترد كذلك حتى لا تكتب كل شاشة تنسيقها فتفترق الشاشات.
     */
    function tq_api_money($minor, $currency = 'SAR')
    {
        $minor = (int) $minor;
        return array(
            'amount'    => $minor,                       // هللات — هو المرجع
            'decimal'   => number_format($minor / 100, 2, '.', ''),
            'currency'  => $currency,
            'formatted' => number_format($minor / 100, 2) . ' ' . ($currency === 'SAR' ? 'ر.س' : $currency),
        );
    }
}

if (!function_exists('tq_api_date')) {
    /**
     * التواريخ كلها ISO-8601 بمنطقة زمنية صريحة.
     *
     * القاعدة فيها `datetime` بلا منطقة وأعمدة `int` بطابع يونكس معا،
     * فلو خرج كل عمود بشكله لحسب التطبيق فرق ثلاث ساعات على نصف الشاشات.
     * والرد `null` لا سلسلة فارغة: الحقل الفارغ في Dart `DateTime?`.
     */
    function tq_api_date($value)
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') return null;
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (!$ts) return null;
        return date('c', $ts);
    }
}

if (!function_exists('tq_api_avatar')) {
    /**
     * رابط الصورة كاملا لا رمزا.
     *
     * `users.image` رمز بلا امتداد والملف `<code>.jpg` — وهذا بالضبط ما
     * كسر عشر شاشات في الويب قبل `tqs_person_img`. والتطبيق لا يملك أن
     * يعرف هذه القاعدة، فيرد الرابط مبنيا هنا.
     */
    function tq_api_avatar($code)
    {
        $code = trim((string) $code);
        if ($code === '') return base_url('assets/taqdar/brand/avatar.svg');
        if (filter_var($code, FILTER_VALIDATE_URL)) return $code;
        if (is_file(FCPATH . 'uploads/user_image/' . $code . '.jpg')) {
            return base_url('uploads/user_image/' . $code . '.jpg');
        }
        return base_url('assets/taqdar/brand/avatar.svg');
    }
}

if (!function_exists('tq_api_user')) {
    /**
     * شكل المستخدم الواحد — يخرج من كل نقطة تذكر مستخدما.
     *
     * وما يحذف منه مقصود: `password` و`verification_code` و`payment_keys`
     * و`sessions` و`temp`. وتعداد ما يخرج أسلم من تعداد ما يحجب: عمود
     * جديد في `users` غدا لا يتسرب لأن أحدا نسي أن يضيفه إلى قائمة الحجب.
     */
    function tq_api_user($u, $extra = array())
    {
        if (!$u) return null;
        $u = (array) $u;

        $out = array(
            'id'         => (int) $u['id'],
            'first_name' => (string) ($u['first_name'] ?? ''),
            'last_name'  => (string) ($u['last_name'] ?? ''),
            'name'       => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')),
            'email'      => (string) ($u['email'] ?? ''),
            'phone'      => (string) ($u['phone'] ?? ''),
            'avatar_url' => tq_api_avatar($u['image'] ?? ''),
            'role'       => function_exists('tq_role') ? tq_role((int) $u['id']) : 'student',
            'status'     => ((int) ($u['status'] ?? 0) === 1) ? 'active' : 'suspended',
            'grade_id'   => isset($u['grade_id']) && $u['grade_id'] !== null ? (int) $u['grade_id'] : null,
            'created_at' => tq_api_date($u['date_added'] ?? null),
        );
        return array_merge($out, $extra);
    }
}

/* ====================================================================
   التحقق من المدخلات
   ==================================================================== */

if (!function_exists('tq_api_validate')) {
    /**
     * فاحص صغير يرد خريطة أخطاء بأسماء الحقول.
     *
     * القواعد نص مفصول بأنبوب كما في Laravel، فيقرأه أي مطور بلا وثيقة:
     *   required · email · string · int · bool · min:n · max:n · in:a,b
     *
     * ولا يستعمل `form_validation` من CI3: ذاك يبني رسائل HTML ويقرأ من
     * `$_POST` وحدها — وجسم الطلب هنا JSON لا نموذجا.
     *
     * @return array خريطة حقل إلى قائمة رسائل. فارغة تعني سلامة المدخل.
     */
    function tq_api_validate(array $input, array $rules)
    {
        $errors = array();

        foreach ($rules as $field => $spec) {
            $parts    = explode('|', $spec);
            $has      = array_key_exists($field, $input);
            $value    = $has ? $input[$field] : null;
            $required = in_array('required', $parts, true);
            $blank    = ($value === null || (is_string($value) && trim($value) === ''));

            if ($required && $blank) {
                $errors[$field][] = 'هذا الحقل مطلوب.';
                continue;
            }
            if (!$has || $blank) continue;   // اختياري وغائب: لا يفحص

            foreach ($parts as $rule) {
                $arg = null;
                if (strpos($rule, ':') !== false) list($rule, $arg) = explode(':', $rule, 2);

                switch ($rule) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = 'صيغة البريد غير صحيحة — مثال: name@example.com';
                        }
                        break;

                    case 'int':
                        if (!is_numeric($value) || (int) $value != $value) {
                            $errors[$field][] = 'القيمة يجب أن تكون رقما صحيحا.';
                        }
                        break;

                    case 'bool':
                        if (!in_array($value, array(true, false, 0, 1, '0', '1', 'true', 'false'), true)) {
                            $errors[$field][] = 'القيمة يجب أن تكون صحيحة أو خاطئة.';
                        }
                        break;

                    case 'min':
                        if (mb_strlen((string) $value) < (int) $arg) {
                            $errors[$field][] = 'أقل طول مسموح ' . (int) $arg . ' محارف.';
                        }
                        break;

                    case 'max':
                        if (mb_strlen((string) $value) > (int) $arg) {
                            $errors[$field][] = 'أكثر طول مسموح ' . (int) $arg . ' محرفا.';
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', (string) $arg);
                        if (!in_array((string) $value, $allowed, true)) {
                            $errors[$field][] = 'القيمة غير مقبولة. المتاح: ' . implode(' · ', $allowed);
                        }
                        break;

                    case 'phone':
                        if (!preg_match('/^[0-9+()\-\s]{6,25}$/u', (string) $value)) {
                            $errors[$field][] = 'رقم الجوال يقبل الأرقام و + و - والمسافات فقط.';
                        }
                        break;
                }
            }
        }

        return $errors;
    }
}

if (!function_exists('tq_api_bool')) {
    /** JSON يرسل `true`، والنموذج يرسل `"1"`. الاثنان يعنيان نعم. */
    function tq_api_bool($v)
    {
        return in_array($v, array(true, 1, '1', 'true', 'on', 'yes'), true);
    }
}

if (!function_exists('tq_api_page')) {
    /**
     * معاملا الترقيم بحد أعلى مفروض.
     *
     * `per_page` بلا سقف يعني أن طلبا واحدا يجر الجدول كله — والحد هنا
     * لا في العميل، فالعميل هو من نحمي منه لا من نثق به.
     */
    function tq_api_page($page, $per_page, $max = 100, $default = 20)
    {
        $page     = max(1, (int) $page);
        $per_page = (int) $per_page ?: $default;
        $per_page = max(1, min($max, $per_page));
        return array($page, $per_page, ($page - 1) * $per_page);
    }
}

if (!function_exists('tq_api_meta_page')) {
    /** شكل الترقيم في `meta` — واحد لكل قائمة في الواجهة كلها. */
    function tq_api_meta_page($page, $per_page, $total)
    {
        $total = (int) $total;
        return array('pagination' => array(
            'page'        => (int) $page,
            'per_page'    => (int) $per_page,
            'total'       => $total,
            'total_pages' => (int) ceil($total / max(1, $per_page)),
            'has_more'    => ($page * $per_page) < $total,
        ));
    }
}
