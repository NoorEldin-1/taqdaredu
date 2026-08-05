<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * محرّك الاشتراكات: الباقات ودورة حياة الاشتراك والفواتير والاستحقاق.
 *
 * المبدأ الحاكم: **الاشتراك سجلّ لما حدث، لا انعكاسٌ لما هو معروض اليوم.**
 * السعر يُنسَخ وقت الشراء، والنطاق يُنسَخ وقت التفعيل — فتغيير الباقة غدًا
 * لا يرفع ما دفعه طالب أمس ولا يقلّص ما اشتراه. الجداول القديمة لا تُمسّ.
 *
 * والنقود هللات صحيحة في كل هذا الملفّ. لا `float` ولا `round()` إلّا عند
 * التحويل الواحد من مدخل المستخدم بالريال.
 */
class Taqdar_billing_model extends CI_Model
{
    /** الضريبة كنسبة مئوية من الإعدادات — صفر ما لم تُضبط. */
    private function tax_percent()
    {
        return (float) (get_settings('taqdar_vat_percent') ?: 0);
    }

    /* =====================================================================
       الباقات
       ===================================================================== */

    public function plans($only_active = true)
    {
        if ($only_active) $this->db->where('active', 1);
        return $this->db->order_by('order', 'ASC')->order_by('price', 'ASC')
                        ->get('plans')->result_array();
    }

    public function plan($id)
    {
        return $this->db->where('id', (int) $id)->get('plans')->row_array();
    }

    public function plan_by_code($code)
    {
        return $this->db->where('code', $code)->get('plans')->row_array();
    }

    /* =====================================================================
       الاشتراك النشط
       ===================================================================== */

    /**
     * الاشتراك السّاري للمستخدم، أو null.
     *
     * ينتهي بالتاريخ لا بالحالة وحدها: قد يمرّ الوقت قبل أن يمرّ الكرون،
     * فمن يقرأ الآن يجب ألّا يرى منتهيًا على أنه نشط.
     *
     * والملغى الذي لم يبلغ أجله سارٍ: `cancel()` يوقف التجديد ولا يصادر
     * المدفوع، و`expire_due()` يعدّه جاريًا حتى `ends_at` — فلو استُثني هنا
     * لانقطع الوصول لحظةَ الإلغاء، وهو نقض لما تَعِد به صفحتا الاشتراك
     * والباقات نصًّا («يبقى صالحًا حتى تاريخ انتهائه — ولا يُصادَر ما دُفع عنه»).
     * ومنعُ شراء اشتراك فوق اشتراك يبقى على «النشط» وحده — انظر `subscribe()`.
     */
    public function active_subscription($user_id)
    {
        $rows = $this->db->where('user_id', (int) $user_id)
                         ->where_in('status', array('active', 'cancelled'))
                         ->order_by('ends_at', 'DESC')
                         ->get('subscriptions')->result_array();

        foreach ($rows as $row) {
            // مضى أجله وإن لم يمرّ عليه الكرون بعد
            if (!empty($row['ends_at']) && strtotime($row['ends_at']) < time()) continue;
            // ألغي قبل أن يُفعَّل أصلًا: لا مدّة له تُكمَل
            if ($row['status'] === 'cancelled' && empty($row['ends_at']))         continue;
            return $row;
        }
        return null;
    }

    public function subscriptions_of($user_id)
    {
        return $this->db->where('user_id', (int) $user_id)
                        ->order_by('id', 'DESC')->get('subscriptions')->result_array();
    }

    public function subscription($id)
    {
        return $this->db->where('id', (int) $id)->get('subscriptions')->row_array();
    }

    public function items_of($subscription_id)
    {
        return $this->db->where('subscription_id', (int) $subscription_id)
                        ->get('subscription_items')->result_array();
    }

    /* =====================================================================
       الاستحقاق
       ===================================================================== */

    /**
     * هل يمنح اشتراكُ المستخدم السّاري الوصولَ إلى هذا المقرّر؟
     *
     * المقرّر يُبلَغ من المسار: `paths.course_id`. فبند النطاق `all` يمنح كل
     * شيء، و`path` يمنح مقرّر مسار بعينه، و`subject` يمنح مقرّرات كل مسارات
     * تلك المادّة، و`course` يمنح مقرّرًا مباشرة.
     *
     * و`trial` نطاق من رتبة أخرى: وحدته الدرس لا المقرّر. ولا يُعبَّر عنه
     * بمنح مقرّر — فمنحُ المقرّر يفتح دروسه كلّها، وهو عين ما جاء `trial`
     * ليمنعه. فالسؤال بلا درس بعينه جوابه **لا**، والسؤال عن درس معلَّم
     * `is_free = 1` في هذا المقرّر جوابه نعم. ولهذا وُسِّع التوقيع بمعامل
     * ثالث اختياري: المستدعي الذي يعرف الدرس يذكره، ومن لا يعرفه لا يُمنَح.
     *
     * @param int      $user_id
     * @param int      $course_id
     * @param int|null $lesson_id درس بعينه — يلزم وحده لنطاق `trial`
     */
    public function subscription_grants($user_id, $course_id, $lesson_id = null)
    {
        $sub = $this->active_subscription($user_id);
        if (!$sub) return false;

        $course_id = (int) $course_id;
        $items     = $this->items_of($sub['id']);
        if (!$items) return false;

        foreach ($items as $it) {
            switch ($it['entity_type']) {

                case 'all':
                    return true;

                case 'course':
                    if ((int) $it['entity_id'] === $course_id) return true;
                    break;

                case 'path':
                    $p = $this->db->select('course_id')->where('id', (int) $it['entity_id'])
                                  ->get('paths')->row_array();
                    if ($p && (int) $p['course_id'] === $course_id) return true;
                    break;

                case 'subject':
                    $n = $this->db->where('subject_id', (int) $it['entity_id'])
                                  ->where('course_id', $course_id)
                                  ->count_all_results('paths');
                    if ($n > 0) return true;
                    break;

                /* TQ-GRADE-GRANT — برنامجٌ ينتمي إلى صفٍّ في الباقة يُفتح.
                   والجسر `paths` لأنّه الوحيد الذي يربط الصفّ بالدورة. */
                case 'grade':
                    $n = $this->db->where('grade_id', (int) $it['entity_id'])
                                  ->where('course_id', $course_id)
                                  ->count_all_results('paths');
                    if ($n > 0) return true;
                    break;

                case 'trial':
                    // لا منح على مستوى المقرّر البتّة
                    if ($lesson_id === null || (int) $lesson_id <= 0) break;
                    $n = $this->db->where('id', (int) $lesson_id)
                                  ->where('course_id', $course_id)
                                  ->where('is_free', 1)
                                  ->count_all_results('lesson');
                    if ($n > 0) return true;
                    break;
            }
        }
        return false;
    }

    /**
     * هل يمنح الاشتراكُ هذا الدرسَ بعينه؟
     *
     * وجهُ `subscription_grants` الذي يعرف الدرس: يستخرج مقرّره من الجدول
     * ثم يسأل السؤال كاملًا. وهو المدخل الذي ينبغي أن ينادى من طبقة الدرس
     * حتى يُقرأ نطاق `trial` كما وُضع.
     */
    public function subscription_grants_lesson($user_id, $lesson_id)
    {
        $l = $this->db->select('id, course_id')->where('id', (int) $lesson_id)
                      ->get('lesson')->row_array();
        if (!$l) return false;

        return $this->subscription_grants($user_id, (int) $l['course_id'], (int) $l['id']);
    }


    /* =====================================================================
       البيع بالمسار — وحدة البيع الأساسية
       ===================================================================== */

    /** المسار مع سعره ونسبة معلّمه. */
    public function path($path_id)
    {
        return $this->db->where("id", (int) $path_id)->get("paths")->row_array();
    }

    /** النسبة الفعلية للمسار: ما ضُبط له، وإلّا الافتراض العامّ. */
    public function path_share_percent($path)
    {
        if (isset($path["teacher_share_percent"]) && $path["teacher_share_percent"] !== null
            && $path["teacher_share_percent"] !== "") {
            return max(0, min(100, (float) $path["teacher_share_percent"]));
        }
        return max(0, min(100, (float) (get_settings("taqdar_teacher_share_default") ?: 15)));
    }

    /**
     * يشتري الطالبُ مسارًا: اشتراك معلّق وفاتورته. لا يمنح شيئًا قبل التفعيل.
     *
     * المسار غير المسعَّر يُرفض كما تُرفض الباقة غير المسعَّرة — فمنصّة لم
     * تُضبط أسعارها لا يجوز أن تفتح محتواها بضغطة.
     */
    public function subscribe_path($user_id, $path_id, $method = "manual")
    {
        $user_id = (int) $user_id;
        $path    = $this->path($path_id);

        if (!$user_id)  return array("ok" => false, "errors" => array("لا مستخدم."));
        if (!$path)     return array("ok" => false, "errors" => array("المسار غير موجود."));
        if ($path["status"] !== "published") {
            return array("ok" => false, "errors" => array("هذا المسار غير منشور."));
        }
        if ((int) $path["course_id"] <= 0) {
            return array("ok" => false, "errors" => array(
                "هذا المسار غير مرتبط بمحتوى بعد، فلا يُشترى."
            ), "code" => "PATH_NO_COURSE");
        }
        if ((int) $path["price"] <= 0) {
            return array("ok" => false, "errors" => array("هذا المسار لم يُسعَّر بعد، فلا يمكن شراؤه."),
                         "code" => "PATH_NOT_PRICED");
        }
        if ($this->has_path($user_id, (int) $path["id"])) {
            return array("ok" => false, "errors" => array("هذا المسار مفتوح لك بالفعل."));
        }

        $this->db->insert("subscriptions", array(
            "user_id"    => $user_id,
            "plan_id"    => 0,
            "path_id"    => (int) $path["id"],
            "status"     => "pending",
            "price"      => (int) $path["price"],
            "auto_renew" => 0,
            "method"     => $method,
            "created_at" => date("Y-m-d H:i:s"),
        ));
        $sid = (int) $this->db->insert_id();
        $inv = $this->issue_invoice($sid, $user_id, (int) $path["price"], $method);

        return array("ok" => true, "subscription_id" => $sid, "invoice_id" => $inv, "free" => false);
    }

    /** هل للمستخدم اشتراك نشط في هذا المسار بعينه؟ */
    public function has_path($user_id, $path_id)
    {
        $rows = $this->db->where("user_id", (int) $user_id)
                         ->where("path_id", (int) $path_id)
                         ->where_in("status", array("active", "cancelled"))
                         ->get("subscriptions")->result_array();
        foreach ($rows as $r) {
            if (empty($r["ends_at"]) || strtotime($r["ends_at"]) >= time()) return true;
        }
        return false;
    }

    /* =====================================================================
       الشراء والتفعيل
       ===================================================================== */

    /**
     * ينشئ اشتراكًا معلّقًا وفاتورته. لا يمنح شيئًا بعد — المنح في التفعيل.
     *
     * الباقة المجّانية تُفعَّل فورًا: لا فاتورة تُدفع فلا معنى لتعليقها.
     *
     * @return array ok · subscription_id · invoice_id · errors
     */
    public function subscribe($user_id, $plan_id, $method = 'manual')
    {
        $user_id = (int) $user_id;
        $plan    = $this->plan($plan_id);

        if (!$user_id)                return array('ok' => false, 'errors' => array('لا مستخدم.'));
        if (!$plan)                   return array('ok' => false, 'errors' => array('الباقة غير موجودة.'));
        if ((int) $plan['active'] !== 1) return array('ok' => false, 'errors' => array('الباقة غير متاحة.'));

        // اشتراك نشط قائم: لا يُشترى فوقه اشتراك ثانٍ صامتًا.
        // و«النشط» هنا بالحرف: من أوقف التجديد فقد أعلن انصرافه عن هذه
        // الباقة، فلا يُحبس عن غيرها إلى أن يمضي أجل ما ألغاه.
        $current = $this->active_subscription($user_id);
        if ($current && $current['status'] === 'active') {
            return array('ok' => false, 'errors' => array('لديك اشتراك نشط بالفعل. ألغِه أو انتظر انتهاءه.'));
        }

        // «مجّانية» صفة الباقة لا نتيجةُ خلوّ سعرها.
        // لولا هذا التمييز لصارت كل باقة لم تُسعَّر بعدُ بابًا لفتح المحتوى
        // مجّانًا — وهو ما يحدث تحديدًا في منصّة لم تُضبط أسعارها بعد.
        $free = ($plan['period'] === 'free');
        if (!$free && (int) $plan['price'] <= 0) {
            return array('ok' => false, 'errors' => array(
                'هذه الباقة لم تُسعَّر بعد، فلا يمكن الاشتراك فيها.'
            ), 'code' => 'PLAN_NOT_PRICED');
        }

        $now = date('Y-m-d H:i:s');

        $this->db->insert('subscriptions', array(
            'user_id'    => $user_id,
            'plan_id'    => (int) $plan['id'],
            'status'     => 'pending',
            'price'      => (int) $plan['price'],   // السعر وقت الشراء
            'auto_renew' => 0,
            'method'     => $method,
            'created_at' => $now,
        ));
        $sid = (int) $this->db->insert_id();

        $inv = $this->issue_invoice($sid, $user_id, (int) $plan['price'], $method);

        if ($free) {
            $this->activate($sid, $method, 'free');
            $this->mark_invoice_paid($inv, 'free');
        }

        return array('ok' => true, 'subscription_id' => $sid, 'invoice_id' => $inv, 'free' => $free);
    }

    /**
     * يفعّل اشتراكًا معلّقًا: يضبط المدّة، وينسخ نطاق الباقة بنودًا.
     *
     * النسخ هنا مقصود — البنود صورة النطاق وقت التفعيل. ولو قُرئ النطاق من
     * الباقة عند كل فحص لتغيّر ما يملكه الطالب كلّما حُرّرت الباقة.
     */
    public function activate($subscription_id, $method = null, $transaction_id = null)
    {
        $sub = $this->subscription($subscription_id);
        if (!$sub) return false;
        if ($sub['status'] === 'active') return true;   // التفعيل مرّتين لا يمدّد المدّة

        // اشتراك مسار: مدّته وبنده من المسار لا من باقة، وحصّة معلّمه تُقيَّد
        if ((int) $sub['path_id'] > 0) {
            return $this->activate_path_subscription($sub, $method, $transaction_id);
        }

        $plan = $this->plan($sub['plan_id']);
        if (!$plan) return false;

        $days  = max(1, (int) $plan['duration_days']);
        $start = time();
        $data  = array(
            'status'     => 'active',
            'started_at' => date('Y-m-d H:i:s', $start),
            'ends_at'    => date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $start)),
        );
        if ($method)         $data['method']         = $method;
        if ($transaction_id) $data['transaction_id'] = $transaction_id;

        $this->db->where('id', (int) $subscription_id)->update('subscriptions', $data);

        // البنود تُكتب مرّة واحدة.
        // و`all` و`trial` نطاقان لا يشيران إلى كيان بعينه، فرقمهما صفر —
        // ورقمٌ منقول من `scope_id` هنا يوهم بمرجع لا وجود له.
        $scope   = $plan['scope'];
        $unbound = in_array($scope, array('all', 'trial'), true);

        /* TQ-GRADE-SCOPE — الباقة تشمل عدّة صفوف، و`scope_id` رقمٌ واحد.
           فتُوسَّع هنا إلى بندٍ لكلّ صفّ. والتوسيع عند **التفعيل** لا عند
           الفحص: النطاق يُنسَخ بنودًا وقت الشراء، فتعديل الباقة لاحقًا
           لا يُوسّع ما دُفع ولا يُضيّقه. */
        /* التنظيف **قبل** الكتابة ولكلّ نطاق: كان فرع `grade` يعود من
           هنا قبل أن يبلغ `DELETE`، فتفعيلٌ ثانٍ يُضيف ستّة بنودٍ فوق
           ستّة — والاستحقاق يصحّ ظاهرًا بينما الجدول يتضخّم بلا حدّ.
           وكان يعود قبل `audit()` أيضًا، فلا أثرَ في السجلّ لأكثرِ
           التفعيلات وقوعًا. والسجلّ الذي يسقط منه الشائع لا يُدقَّق. */
        $this->db->where('subscription_id', (int) $subscription_id)->delete('subscription_items');

        if ($scope === 'grade') {
            $ids = array_filter(array_map('intval',
                       explode(',', (string) $plan['scope_ids'])));
            if (!$ids) $ids = array((int) $plan['scope_id']);
            foreach (array_unique($ids) as $gid) {
                if ($gid <= 0) continue;
                $this->db->insert('subscription_items', array(
                    'subscription_id' => (int) $subscription_id,
                    'entity_type'     => 'grade',
                    'entity_id'       => $gid,
                ));
            }
        } else {
            $this->db->insert('subscription_items', array(
                'subscription_id' => (int) $subscription_id,
                'entity_type'     => $scope,
                'entity_id'       => $unbound ? 0 : (int) $plan['scope_id'],
            ));
        }

        /* التجسيد بعد البنود مباشرةً: البنود تُجيب `is_entitled()`،
           وصفوف `enrol` تُجيب الشاشات التي لا تسأل غيرها. */
        $this->sync_enrolments((int) $subscription_id);

        $this->audit('subscription_activate', 'subscriptions#' . (int) $subscription_id,
                     $sub, $this->subscription($subscription_id));
        return true;
    }

    public function cancel($subscription_id, $reason = '')
    {
        $sub = $this->subscription($subscription_id);
        if (!$sub || $sub['status'] === 'cancelled') return false;

        // الإلغاء يوقف التجديد ولا يصادر المدفوع: المدّة تُكمَل حتى نهايتها
        $this->db->where('id', (int) $subscription_id)->update('subscriptions', array(
            'status'        => 'cancelled',
            'auto_renew'    => 0,
            'cancelled_at'  => date('Y-m-d H:i:s'),
            'cancel_reason' => mb_substr((string) $reason, 0, 255),
        ));

        $this->audit('subscription_cancel', 'subscriptions#' . (int) $subscription_id,
                     $sub, $this->subscription($subscription_id));
        return true;
    }

    /**
     * ينهي الاشتراكات التي مضى أجلها. يُنادى من الكرون.
     * والملغى الذي لم ينتهِ أجله يبقى صالحًا حتى `ends_at` — دفع ثمنه.
     */
    public function expire_due()
    {
        $now  = date('Y-m-d H:i:s');
        $rows = $this->db->where_in('status', array('active', 'cancelled'))
                         ->where('ends_at <', $now)
                         ->where('ends_at IS NOT NULL', null, false)
                         ->get('subscriptions')->result_array();

        foreach ($rows as $r) {
            $this->db->where('id', (int) $r['id'])->update('subscriptions', array('status' => 'expired'));
        }
        if ($rows) {
            $this->audit('subscription_expire_batch', 'subscriptions', null, array('count' => count($rows)));
        }
        return count($rows);
    }

    /* =====================================================================
       الفواتير
       ===================================================================== */

    public function issue_invoice($subscription_id, $user_id, $amount, $method = null)
    {
        $amount = (int) $amount;
        $tax    = (int) round($amount * $this->tax_percent() / 100);

        $this->db->insert('invoices', array(
            'invoice_no'      => $this->next_invoice_no(),
            'subscription_id' => (int) $subscription_id,
            'user_id'         => (int) $user_id,
            'amount'          => $amount,
            'tax'             => $tax,
            'total'           => $amount + $tax,
            'status'          => 'unpaid',
            'method'          => $method,
            'issued_at'       => date('Y-m-d H:i:s'),
        ));
        return (int) $this->db->insert_id();
    }

    /**
     * رقم فاتورة متسلسل داخل السنة.
     * يُشتقّ من أعلى رقم قائم لا من عدّ الصفوف: الحذف يجعل العدّ يكرّر رقمًا،
     * ورقم فاتورة مكرّر عطبٌ محاسبيّ لا عطب عرض.
     */
    private function next_invoice_no()
    {
        $year   = date('Y');
        $prefix = 'TQ-' . $year . '-';

        $row = $this->db->select('invoice_no')->like('invoice_no', $prefix, 'after')
                        ->order_by('invoice_no', 'DESC')->limit(1)
                        ->get('invoices')->row_array();

        $seq = $row ? ((int) substr($row['invoice_no'], strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function mark_invoice_paid($invoice_id, $transaction_id = null)
    {
        $this->db->where('id', (int) $invoice_id)->update('invoices', array(
            'status'         => 'paid',
            'paid_at'        => date('Y-m-d H:i:s'),
            'transaction_id' => $transaction_id,
        ));
        return true;
    }

    public function invoices_of($user_id)
    {
        return $this->db->where('user_id', (int) $user_id)
                        ->order_by('id', 'DESC')->get('invoices')->result_array();
    }

    public function invoice_of_subscription($subscription_id)
    {
        return $this->db->where('subscription_id', (int) $subscription_id)
                        ->order_by('id', 'DESC')->limit(1)
                        ->get('invoices')->row_array();
    }

    /**
     * تفعيل يدوي — التحويل البنكي. تُنادى من اللوحة بعد التحقّق من الحوالة.
     *
     * هذا هو المسار الوحيد العامل اليوم: لا بوّابة دفع مفعّلة في الإعدادات
     * (`paypal.active=0` و`stripe.active=0`)، فوصلة البوّابة أدناه مكتوبة
     * وجاهزة لكنها لم تُختبر بدفعة حقيقية ولا يصحّ ادّعاء أنها مجرَّبة.
     */
    public function activate_manually($subscription_id, $reference = '')
    {
        $ok = $this->activate($subscription_id, 'manual', $reference);
        if ($ok) {
            $inv = $this->invoice_of_subscription($subscription_id);
            if ($inv && $inv['status'] !== 'paid') {
                $this->mark_invoice_paid($inv['id'], $reference);
            }
        }
        return $ok;
    }

    /** نقطة الوصل للبوّابات حين تُفعَّل مفاتيحها. */
    public function activate_from_gateway($subscription_id, $gateway, $transaction_id)
    {
        $ok = $this->activate($subscription_id, $gateway, $transaction_id);
        if ($ok) {
            $inv = $this->invoice_of_subscription($subscription_id);
            if ($inv) $this->mark_invoice_paid($inv['id'], $transaction_id);
        }
        return $ok;
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    private function audit($action, $entity, $before, $after)
    {
        $this->db->insert('audit_log', array(
            'actor_id' => $this->tq_actor_id(),
            'action'   => $action,
            'entity'   => $entity,
            'before'   => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after'    => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip'       => $this->input->is_cli_request() ? 'cli' : $this->input->ip_address(),
            'at'       => date('Y-m-d H:i:s'),
        ));
    }

    /** ملخّص للوحة. */
    public function stats()
    {
        $now = date('Y-m-d H:i:s');
        return array(
            'plans'    => (int) $this->db->count_all_results('plans'),
            'active'   => (int) $this->db->where('status', 'active')->where('ends_at >=', $now)
                                         ->count_all_results('subscriptions'),
            'pending'  => (int) $this->db->where('status', 'pending')->count_all_results('subscriptions'),
            'unpaid'   => (int) $this->db->where('status', 'unpaid')->count_all_results('invoices'),
            'revenue'  => (int) ($this->db->select_sum('total')->where('status', 'paid')
                                          ->get('invoices')->row()->total ?: 0),
        );
    }


    /**
     * الفاعل: المستخدم في الطلب الوِبّي، و0 (النظام) في المهامّ الدورية.
     * مكتبة الجلسة غير محمَّلة في سطر الأوامر، وقراءتها هناك تُسقط العملية.
     *
     * والفحص على `get_instance()` لا على `$this`: النموذج في CodeIgniter
     * يخدم خصائصه بـ`__get` وحدها ولا يعرّف `__isset`، فـ`isset($this->session)`
     * كاذبة **دائمًا** وإن كانت الجلسة محمَّلة — القراءة تنجح والفحص يفشل.
     * ولهذا كان كل قيد اشتراك أو فوترة يُنسب إلى «النظام» بدل صاحبه.
     * أمّا `get_instance()` فمتحكّم بخصائص حقيقية، فـ`isset` عليه تصدق.
     */
    private function tq_actor_id()
    {
        $CI = function_exists('get_instance') ? get_instance() : null;
        if (!$CI || !isset($CI->session) || !is_object($CI->session)) return 0;
        return (int) $CI->session->userdata('user_id');
    }



    /**
     * تفعيل اشتراك مسار: المدّة من `expected_weeks`، والبند `path`،
     * وحصّة المعلّم تُقيَّد في دفتره — **من هذا المسار وحده**.
     *
     * فشل قيد المحفظة لا يُبطل التفعيل: الطالب دفع واستحقّ وصولَه، ودفتر
     * المعلّم يُصالَح لاحقًا. والعكس — منعُ الطالب لأن دفترًا لم يُكتب —
     * يعاقب من لا ذنب له.
     */
    private function activate_path_subscription($sub, $method = null, $transaction_id = null)
    {
        $path = $this->path($sub['path_id']);
        if (!$path) return false;

        $weeks = (int) $path['expected_weeks'];
        $days  = $weeks > 0 ? $weeks * 7 : (int) (get_settings('taqdar_path_default_days') ?: 180);
        $start = time();

        $data = array(
            'status'     => 'active',
            'started_at' => date('Y-m-d H:i:s', $start),
            'ends_at'    => date('Y-m-d H:i:s', strtotime('+' . max(1, $days) . ' days', $start)),
        );
        if ($method)         $data['method']         = $method;
        if ($transaction_id) $data['transaction_id'] = $transaction_id;

        $this->db->where('id', (int) $sub['id'])->update('subscriptions', $data);

        $this->db->where('subscription_id', (int) $sub['id'])->delete('subscription_items');
        $this->db->insert('subscription_items', array(
            'subscription_id' => (int) $sub['id'],
            'entity_type'     => 'path',
            'entity_id'       => (int) $path['id'],
        ));

        // حصّة معلّم هذا المسار
        if ((int) $path['teacher_id'] > 0 && (int) $sub['price'] > 0) {
            $this->load->model('taqdar_wallet_model');
            $this->taqdar_wallet_model->credit_path_sale(
                (int) $path['teacher_id'], (int) $path['id'], (int) $sub['id'],
                (int) $sub['price'], $this->path_share_percent($path)
            );
        }

        $this->audit('subscription_activate_path', 'subscriptions#' . (int) $sub['id'],
                     $sub, $this->subscription($sub['id']));
        return true;
    }

    /**
     * يُعيد بناء بنود الاشتراكات النشطة من نطاق باقاتها.
     *
     * البنود تُكتب في `activate()` وحدها. واشتراكٌ فُعِّل بـ`UPDATE` مباشر
     * على الجدول — من لوحةٍ قديمة أو من يدٍ في قاعدة البيانات — يصير
     * `active` بلا بندٍ واحد. و`subscription_grants()` تخرج عندها بـ
     * `if (!$items) return false;` فيسقط الاستحقاق إلى `enrol`: الطالب
     * دفع، واشتراكه نشط، وبوّابته فارغة — ولا خطأ يظهر في أيّ سجلّ.
     *
     * والإصلاح **لا يمسّ اشتراكًا له بنود**: البنود صورة النطاق وقت
     * الشراء، وإعادة بنائها من الباقة اليوم تُغيّر ما اشتُري بالأمس.
     *
     * @return array fixed · skipped · errors
     */
    public function repair_items()
    {
        $subs = $this->db->select('id, plan_id, path_id, status')
                         ->from('subscriptions')
                         ->where_in('status', array('active', 'cancelled'))
                         ->get()->result_array();

        $fixed = 0; $skipped = 0; $errors = array();

        foreach ($subs as $sub) {
            $sid = (int) $sub['id'];

            $n = (int) $this->db->where('subscription_id', $sid)
                                ->count_all_results('subscription_items');
            if ($n > 0) { $skipped++; continue; }

            /* اشتراك مسار: بندُه المسار نفسه لا نطاق باقة. */
            if ((int) $sub['path_id'] > 0) {
                $this->db->insert('subscription_items', array(
                    'subscription_id' => $sid,
                    'entity_type'     => 'path',
                    'entity_id'       => (int) $sub['path_id'],
                ));
                $fixed++;
                continue;
            }

            $plan = $this->plan($sub['plan_id']);
            if (!$plan) { $errors[] = 'اشتراك #' . $sid . ': باقته غير موجودة.'; continue; }

            $scope   = $plan['scope'];
            $unbound = in_array($scope, array('all', 'trial'), true);

            if ($scope === 'grade') {
                $ids = array_filter(array_map('intval',
                           explode(',', (string) $plan['scope_ids'])));
                if (!$ids) $ids = array((int) $plan['scope_id']);
                $wrote = 0;
                foreach (array_unique($ids) as $gid) {
                    if ($gid <= 0) continue;
                    $this->db->insert('subscription_items', array(
                        'subscription_id' => $sid,
                        'entity_type'     => 'grade',
                        'entity_id'       => $gid,
                    ));
                    $wrote++;
                }
                if (!$wrote) { $errors[] = 'اشتراك #' . $sid . ': الباقة بلا صفوف.'; continue; }
            } else {
                $this->db->insert('subscription_items', array(
                    'subscription_id' => $sid,
                    'entity_type'     => $scope,
                    'entity_id'       => $unbound ? 0 : (int) $plan['scope_id'],
                ));
            }

            $this->sync_enrolments($sid);
            $this->audit('subscription_repair_items', 'subscriptions#' . $sid, null,
                         array('plan' => $plan['code'], 'scope' => $scope));
            $fixed++;
        }

        return array('fixed' => $fixed, 'skipped' => $skipped, 'errors' => $errors);
    }

    /**
     * يُجسّد ما يمنحه الاشتراك صفوفَ `enrol` تنتهي بانتهائه.
     *
     * `is_entitled()` تعرف الاشتراكات، لكنّ عشر شاشاتٍ في بوّابة الطالب
     * لا تعرفها: المواد التعليمية والتقارير والمهامّ والتقويم وقوائم
     * المعلّم كلّها تضمّ `enrol` ضمًّا داخليًّا مباشرًا. فالمشترك النشط
     * يقرأ «لا موادّ بعد» في شاشةٍ ويشاهد دروسه في أخرى — والخلل غير
     * مرئيّ لأنّ كلتيهما تعمل بلا خطأ.
     *
     * والعلاج تجسيدٌ لا إعادةُ كتابةِ عشرِ شاشات: `enrol` هي علامة
     * Academy على «هذا الطالب يملك هذا المقرّر»، و`expiry_date` فيها
     * تجعل الصفّ يسقط وحده يوم ينتهي الاشتراك.
     *
     * وقاعدتان تحرسان ما اشتُري قبل الاشتراكات:
     *   • لا يُنقَص أجلٌ قائم — من اشترى دورةً بلا أجل يبقى مالكًا لها
     *   • لا يُحذف صفٌّ لم نكتبه
     *
     * @return int عدد المقرّرات التي صار الطالب مسجَّلًا فيها
     */
    public function sync_enrolments($subscription_id)
    {
        $sub = $this->subscription($subscription_id);
        if (!$sub) return 0;
        if (!in_array($sub['status'], array('active', 'cancelled'), true)) return 0;

        $uid = (int) $sub['user_id'];
        if ($uid <= 0) return 0;

        /* الأجل: نهاية الاشتراك. و`0` تعني بلا أجل — وهي ما يُكتب حين
           لا تاريخ انتهاء، فلا يُحبَس الطالب بأجلٍ مخترع. */
        $exp = !empty($sub['ends_at']) ? (int) strtotime($sub['ends_at']) : 0;

        $courses = array();
        foreach ($this->items_of((int) $sub['id']) as $it) {
            $type = $it['entity_type'];
            $eid  = (int) $it['entity_id'];

            if ($type === 'course') { $courses[] = $eid; continue; }

            $q = $this->db->select('course_id')->from('paths')
                          ->where('status', 'published')->where('course_id >', 0);
            if      ($type === 'grade')   $q->where('grade_id', $eid);
            elseif  ($type === 'subject') $q->where('subject_id', $eid);
            elseif  ($type === 'path')    $q->where('id', $eid);
            elseif  ($type === 'all')     { /* بلا قيد: الكتالوج المنشور كلّه */ }
            else    { $q->reset_query(); continue; }   // `trial` لا يمنح مقرّرًا

            foreach ($q->get()->result_array() as $r) $courses[] = (int) $r['course_id'];
        }

        $courses = array_values(array_unique(array_filter($courses)));
        if (!$courses) return 0;

        $have = array();
        foreach ($this->db->select('id, course_id, expiry_date')->from('enrol')
                          ->where('user_id', $uid)->where_in('course_id', $courses)
                          ->get()->result_array() as $r) {
            $have[(int) $r['course_id']] = $r;
        }

        $now = time();
        $n   = 0;
        foreach ($courses as $cid) {
            if (!isset($have[$cid])) {
                $this->db->insert('enrol', array(
                    'user_id'       => $uid,
                    'course_id'     => $cid,
                    'gifted_by'     => 0,
                    'expiry_date'   => $exp > 0 ? (string) $exp : null,
                    'date_added'    => $now,
                    'last_modified' => $now,
                ));
                $n++;
                continue;
            }

            /* صفٌّ قائم: يُمدَّد أجلُه ولا يُقصَّر. والصفر «بلا أجل» فلا
               يُستبدل به تاريخٌ ينتهي. */
            $cur = (int) $have[$cid]['expiry_date'];
            if ($cur > 0 && $exp > $cur) {
                $this->db->where('id', (int) $have[$cid]['id'])
                         ->update('enrol', array('expiry_date' => (string) $exp,
                                                 'last_modified' => $now));
                $n++;
            }
        }
        return $n;
    }

}
