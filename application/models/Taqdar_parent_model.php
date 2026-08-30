<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * نموذج بوابة ولي الأمر — كل كتابة تخص ولي الأمر تمر من هنا.
 *
 * البوابة كانت تقرأ ولا تكتب: الربط ينشأ يدويا في القاعدة، ولا زر
 * لبدء محادثة، والإعدادات صفر نماذج. هذا الملف هو طبقة الكتابة الوحيدة،
 * وقواعده ثلاث:
 *
 *   1) **الموافقة بيان لا خانة.** الرابط يبدأ `pending` بلا تاريخ، ولا يصير
 *      `active` إلا بتاريخ موافقة في `consent_at` مع نسخة نص الموافقة ومن
 *      أعطاها ومن أي عنوان. والموافقة تعطى من حساب الابن نفسه أو من الإدارة،
 *      لا من ولي الأمر — ولو أعطاها لنفسه لما كانت موافقة.
 *
 *   2) **الملكية تفحص في الخادم لا في الواجهة.** كل دالة هنا تأخذ معرف
 *      ولي الأمر من الجلسة وتقيد استعلامها به؛ إخفاء زر ليس صلاحية.
 *
 *   3) **لا مراسلة إلا من قائمة بيضاء.** ولي الأمر يراسل معلمي كورسات
 *      أبنائه المربوطين وحدهم — تحسب القائمة في الخادم، ويفحص المستقبل
 *      عليها قبل الكتابة، لا عند رسم القائمة.
 *
 * والدوال العامة هنا صالحة لاستدعائها من متحكم (`Taqdar.php`) كما هي:
 * ما من دالة تقرأ `$_POST` إلا `handle_post()` وحدها، وما عداها يأخذ
 * وسائطه صريحة ويرجع `['ok' => bool, 'message' => string, ...]`.
 */
class Taqdar_parent_model extends CI_Model
{
    /** نص الموافقة الذي يعرض على الابن ويحفظ حرفيا مع تاريخها. */
    const CONSENT_TEXT = 'أوافق على ربط حسابي بحساب ولي أمري، وعلى أن يرى تقدمي في المواد '
                       . 'وأيام نشاطي ونتائج اختباراتي ومدفوعاتي وملاحظات معلمي. ولا يرى '
                       . 'محادثاتي مع المساعد الذكي ولا منشوراتي ولا إجاباتي الخاطئة مفردة. '
                       . 'ولي أن أسحب هذه الموافقة متى شئت.';

    /** خطة الأسبوع الافتراضية حين لا يحددها ولي الأمر — معلنة في الشاشة لا مموهة. */
    const PLAN_DAYS_DEFAULT = 5;

    /** الأحداث التي تستحق المقاطعة — مفاتيحها مفاتيح `notifications.type`. */
    public function notify_keys()
    {
        return [
            'exam_result'      => 'نتيجة امتحان',
            'placement_result' => 'نتيجة تحديد المستوى',
            'station_failed'   => 'رسوب في اختبار محطة',
            'inactivity_3days' => 'انقطاع ثلاثة أيام',
            'session_request'  => 'طلب حصة خاصة',
            'certificate'      => 'شهادة جديدة',
        ];
    }

    /** التفضيلات الافتراضية: كل شيء مفتوح، فالكتم قرار المستخدم لا قرارنا. */
    public function notify_defaults()
    {
        $out = ['weekly' => 1];
        foreach (array_keys($this->notify_keys()) as $k) $out[$k] = 1;
        return $out;
    }

    /* ================================================================
       قراءة
       ================================================================ */

    /** روابط ولي الأمر كلها أو بحالة بعينها. */
    public function links($parent_id, $status = null)
    {
        $parent_id = (int) $parent_id;
        if ($parent_id <= 0 || !$this->db->table_exists('parent_links')) return [];

        $sql = "SELECT pl.id, pl.student_id, pl.status, pl.consent_at, pl.scope,
                       u.first_name, u.last_name, u.email, u.image
                  FROM parent_links pl
                  JOIN users u ON u.id = pl.student_id
                 WHERE pl.parent_user_id = ?";
        $bind = [$parent_id];

        if ($status !== null) { $sql .= " AND pl.status = ?"; $bind[] = $status; }
        $sql .= " ORDER BY FIELD(pl.status,'active','pending','revoked'), u.first_name ASC";

        $rows = $this->db->query($sql, $bind)->result_array();
        foreach ($rows as &$r) {
            $r['name']  = trim($r['first_name'] . ' ' . $r['last_name']);
            $r['prefs'] = $this->scope_of($r);
        }
        return $rows;
    }

    /** الأبناء المربوطون فعلا (رابط نشط بموافقة موثقة). */
    public function children($parent_id)
    {
        return $this->links($parent_id, 'active');
    }

    /** هل هذا الابن ابن هذا الولي فعلا؟ الفحص في الخادم وبرابط نشط وحده. */
    public function owns($parent_id, $student_id)
    {
        $parent_id  = (int) $parent_id;
        $student_id = (int) $student_id;
        if ($parent_id <= 0 || $student_id <= 0 || !$this->db->table_exists('parent_links')) return false;

        return (int) $this->db->where('parent_user_id', $parent_id)
                              ->where('student_id', $student_id)
                              ->where('status', 'active')
                              ->count_all_results('parent_links') > 0;
    }

    /** صف الابن إن كان مربوطا — وإلا `null`، فلا تفتح بيانات غير ابنه. */
    public function child($parent_id, $student_id)
    {
        if (!$this->owns($parent_id, $student_id)) return null;

        return $this->db->query(
            "SELECT u.id, u.first_name, u.last_name, u.image, u.email
               FROM users u WHERE u.id = ? LIMIT 1", [(int) $student_id]
        )->row_array();
    }

    /**
     * أيام خطة الأسبوع لابن بعينه.
     * كانت `5` مزروعة في شيفرة `tq_parent_weekly.php` و`tq_parent_child.php`
     * تعرض كأنها بيانات الأسرة. صارت تقرأ من `parent_links.scope` حين
     * يحددها ولي الأمر، وحين لا يحددها تعلن افتراضيتها في الشاشة.
     *
     * @return array{days:int, is_default:bool}
     */
    public function plan_days($parent_id, $student_id)
    {
        $row = $this->db->query(
            "SELECT scope FROM parent_links
              WHERE parent_user_id = ? AND student_id = ? AND status = 'active' LIMIT 1",
            [(int) $parent_id, (int) $student_id]
        )->row_array();

        $scope = $this->scope_of($row ?: []);
        $days  = isset($scope['plan_days']) ? (int) $scope['plan_days'] : 0;

        return $days > 0
            ? ['days' => min(7, $days), 'is_default' => false]
            : ['days' => self::PLAN_DAYS_DEFAULT, 'is_default' => true];
    }

    /** قناة تفضيلات ولي الأمر في `tq_prefs_notify` — البوابة نفسها لا البريد. */
    const NOTIFY_CHANNEL = 'portal';

    /**
     * تفضيلات الإشعارات — «ما يصلك ومتى».
     *
     * موضعها `tq_prefs_notify` (مستخدم × نوع × قناة)، وهو موضعها الصحيح:
     * السؤال «أي الأحداث تقطع يومي؟» سؤال عن **صاحب الحساب** لا عن رابط
     * ابن بعينه. وكانت تحفظ في `parent_links.scope` — فولي أمر بلا رابط
     * نشط بعد (طلبه معلق، أو ألغى ربطه) يفتح شاشة الإعدادات ويرى النموذج
     * كاملا، فإذا حفظ رد عليه: «لا روابط في حسابك لتحفظ عليها التفضيلات».
     * نموذج معروض لا يحفظ.
     *
     * وخطة الأيام تبقى في `scope` — تلك **فعلا** عن الابن لا عن أبيه.
     *
     * والقراءة تسقط إلى `scope` حين لا صف في الجدول الجديد: ما حفظه ولي
     * أمر قبل اليوم لا يضيع لأننا نقلنا الموضع.
     */
    public function prefs($parent_id)
    {
        $parent_id = (int) $parent_id;
        $out = $this->notify_defaults();
        if ($parent_id <= 0) return $out;

        if ($this->db->table_exists('tq_prefs_notify')) {
            $rows = $this->db->where('user_id', $parent_id)
                             ->where('channel', self::NOTIFY_CHANNEL)
                             ->get('tq_prefs_notify')->result_array();
            if ($rows) {
                foreach ($rows as $r) {
                    if (array_key_exists($r['notify_type'], $out)) {
                        $out[$r['notify_type']] = (int) $r['enabled'] ? 1 : 0;
                    }
                }
                return $out;
            }
        }

        // الموضع القديم: `parent_links.scope.notify`
        foreach ($this->links($parent_id) as $r) {
            if (!empty($r['prefs']['notify']) && is_array($r['prefs']['notify'])) {
                foreach ($out as $k => $v) {
                    if (isset($r['prefs']['notify'][$k])) $out[$k] = (int) $r['prefs']['notify'][$k] ? 1 : 0;
                }
                break;
            }
        }
        return $out;
    }

    /* ================================================================
       الربط — طلب · موافقة · سحب
       ================================================================ */

    /**
     * طلب ربط ابن. ينشئ صفا `pending` **بلا** `consent_at` — ولا يفتح شيئا.
     * ويبلغ الابن بإشعار ورسالة خاصة، فالطلب لا يقع صامتا.
     */
    public function request_link($parent_id, $identifier)
    {
        $parent_id  = (int) $parent_id;
        $identifier = trim((string) $identifier);

        if ($parent_id <= 0)      return $this->fail('لا جلسة مفتوحة.');
        if ($identifier === '')   return $this->fail('اكتب بريد حساب ابنك في المنصة أو رقم حسابه.');
        if (!$this->db->table_exists('parent_links')) return $this->fail('جدول الروابط غير موجود.');

        $student = ctype_digit($identifier)
            ? $this->db->where('id', (int) $identifier)->get('users')->row_array()
            : $this->db->where('email', $identifier)->get('users')->row_array();

        if (!$student) {
            return $this->fail('لا حساب بهذا البريد أو الرقم. الربط يكون بحساب ابنك في المنصة نفسها — '
                             . 'ولا نبحث بتشابه اسم، فخطأ واحد هنا يفتح بيانات طفل لغير أهله.');
        }
        if ((int) $student['id'] === $parent_id) return $this->fail('لا يكون المستخدم ولي أمر نفسه.');
        if ((int) $student['status'] !== 1)      return $this->fail('حساب ابنك غير مفعل بعد.');

        $name = trim($student['first_name'] . ' ' . $student['last_name']);
        $role = tq_role((int) $student['id']);
        if ($role === 'admin' || $role === 'teacher') {
            return $this->fail('هذا الحساب حساب ' . ($role === 'admin' ? 'إدارة' : 'معلم') . '، لا حساب طالب.');
        }

        $existing = $this->db->where('parent_user_id', $parent_id)
                             ->where('student_id', (int) $student['id'])
                             ->get('parent_links')->row_array();

        $scope = [
            'requested_at' => $this->now(),
            'requested_ip' => $this->input->ip_address(),
        ];
        // لا تكتب `plan_days` هنا: خطة لم يحددها ولي الأمر تبقى غير محددة،
        // وتعلن في الشاشة افتراضية بدل أن تعرض كأنها اختياره.
        //
        // ولا `notify` كذلك: موضعها `tq_prefs_notify` باسم صاحب الحساب،
        // ونسخها هنا يصنع مصدر حقيقة ثانيا يتباعد عن الأول عند أول حفظ.

        if ($existing) {
            if ($existing['status'] === 'active')  return $this->fail('حساب ' . $name . ' مرتبط بحسابك.');
            if ($existing['status'] === 'pending') return $this->fail('طلبك السابق ما زال بانتظار موافقة ' . $name . '.');

            // رابط مسحوب: يعاد الطلب من الصفر — والموافقة القديمة لا تورث.
            $old = $this->scope_of($existing);
            if (!empty($old['consent'])) $scope['previous_consent'] = $old['consent'];

            $this->db->where('id', (int) $existing['id'])->update('parent_links', [
                'status'     => 'pending',
                'consent_at' => null,
                'scope'      => $this->json($scope),
            ]);
            $link_id = (int) $existing['id'];
        } else {
            $this->db->insert('parent_links', [
                'parent_user_id' => $parent_id,
                'student_id'     => (int) $student['id'],
                'status'         => 'pending',
                'consent_at'     => null,
                'scope'          => $this->json($scope),
            ]);
            $link_id = (int) $this->db->insert_id();
        }

        if ($link_id <= 0) return $this->fail('تعذر حفظ الطلب. حاول مرة أخرى.');

        $this->announce_request($parent_id, $student, $link_id);

        return [
            'ok'      => true,
            'link_id' => $link_id,
            'message' => 'أرسل طلب الربط إلى ' . $name . '، ووصلته رسالة بنص الموافقة. '
                       . 'ولا يفتح شيء من بياناته قبل أن يوافق من حسابه.',
        ];
    }

    /**
     * تسجيل الموافقة — من حساب الابن نفسه أو من الإدارة، لا من ولي الأمر.
     * ولا تفعيل بلا تاريخ: `consent_at` و`status` يكتبان معا في تحديث واحد.
     */
    public function grant_consent($link_id, $actor_id, $text = null)
    {
        $link_id  = (int) $link_id;
        $actor_id = (int) $actor_id;

        $row = $this->db->where('id', $link_id)->get('parent_links')->row_array();
        if (!$row)                        return $this->fail('طلب الربط غير موجود.');
        if ($row['status'] !== 'pending') return $this->fail('لا موافقة إلا على طلب معلق.');

        $is_student = ($actor_id === (int) $row['student_id']);
        $is_admin   = (tq_role($actor_id) === 'admin');
        if (!$is_student && !$is_admin) {
            return $this->fail('الموافقة تعطى من حساب الابن نفسه أو من الإدارة — لا من ولي الأمر.');
        }

        $now   = $this->now();
        $scope = $this->scope_of($row);
        $scope['consent'] = [
            'at'      => $now,
            'by'      => $actor_id,
            'by_role' => $is_student ? 'student' : 'admin',
            'ip'      => $this->input->ip_address(),
            'text'    => $text !== null && trim((string) $text) !== '' ? (string) $text : self::CONSENT_TEXT,
        ];

        $this->db->where('id', $link_id)->where('status', 'pending')->update('parent_links', [
            'consent_at' => $now,
            'status'     => 'active',
            'scope'      => $this->json($scope),
        ]);

        if ($this->db->affected_rows() < 1) return $this->fail('تعذر تسجيل الموافقة.');

        /* ولي الأمر يبلغ بالموافقة.
           كان الطلب يرسل بإشعار ورسالة، ثم يوافق الابن فلا يصل ولي الأمر
           شيء: يبقى ينتظر ويعيد فتح الشاشة ليرى هل وافق. والطرف الذي بدأ
           الطلب أولى الناس بخبر نتيجته. */
        $this->announce_to_parent(
            (int) $row['parent_user_id'], (int) $row['student_id'], 'parent_link_granted',
            'وافق ' . $this->name_of($row['student_id']) . ' على الربط',
            $this->name_of($row['student_id']) . ' وافق على ربط حسابه بحسابك بتاريخ ' . $now
            . '. تجد متابعته الآن في «أبنائي».'
        );

        return ['ok' => true, 'message' => 'سجلت الموافقة بتاريخها، وفتح الرابط.'];
    }

    /**
     * رفض الابن طلب ربط معلقا.
     *
     * كان الرفض ينادي `revoke_link($link_id, $uid)` — ووسائط تلك الدالة
     * `($parent_id, $student_id)` بمعنى آخر تماما، فتبحث عن رابط
     * `parent_user_id = <رقم الطلب>` وهو لا يطابق شيئا أبدا: يضغط الطالب
     * «أرفض» فيقرأ «لا رابط نشط بهذا الابن في حسابك» ويبقى الطلب معلقا
     * في شاشته إلى الأبد. وهذه دالته الصحيحة.
     *
     * والصف يبقى `revoked` لا يحذف: من رفض مرة له أن يحتج بأنه رفض،
     * ومن أعاد الطلب بعد رفض يجب أن يقرأ الرفض السابق في السجل.
     */
    public function reject_request($link_id, $student_id)
    {
        $link_id    = (int) $link_id;
        $student_id = (int) $student_id;

        $row = $this->db->where('id', $link_id)
                        ->where('student_id', $student_id)
                        ->where('status', 'pending')
                        ->get('parent_links')->row_array();

        if (!$row) return $this->fail('لا طلب ربط معلق بهذا الرقم على حسابك.');

        $scope = $this->scope_of($row);
        $scope['rejected'] = [
            'at' => $this->now(),
            'by' => $student_id,
            'ip' => $this->input->ip_address(),
        ];

        $this->db->where('id', $link_id)->where('status', 'pending')
                 ->update('parent_links', ['status' => 'revoked', 'scope' => $this->json($scope)]);

        if ($this->db->affected_rows() < 1) return $this->fail('تعذر تسجيل الرفض.');

        $this->announce_to_parent(
            (int) $row['parent_user_id'], $student_id, 'parent_link_rejected',
            'لم يوافق ' . $this->name_of($student_id) . ' على الربط',
            'رفض ' . $this->name_of($student_id) . ' طلب ربط حسابه بحسابك. '
            . 'ولا يفتح شيء من بياناته. تحدث إليه قبل أن ترسل طلبا جديدا.'
        );

        return ['ok' => true, 'message' => 'رفضت الطلب، ولم يفتح شيء من بياناتك.'];
    }

    /**
     * سحب الابن موافقته على رابط نشط.
     *
     * نص الموافقة نفسه يقول «ولي أن أسحب هذه الموافقة متى شئت» — وكان
     * ذلك وعدا بلا باب: `revoke_link` تسحب باسم ولي الأمر وحده، ولا زر
     * في شاشة الطالب يفعل ذلك. الوعد المكتوب في بيان قانوني ينفذ.
     */
    public function withdraw_consent($student_id, $link_id)
    {
        $student_id = (int) $student_id;
        $link_id    = (int) $link_id;

        $row = $this->db->where('id', $link_id)
                        ->where('student_id', $student_id)
                        ->where('status', 'active')
                        ->get('parent_links')->row_array();

        if (!$row) return $this->fail('لا رابط نشط بهذا الرقم على حسابك.');

        $scope = $this->scope_of($row);
        $scope['revoked'] = [
            'at' => $this->now(),
            'by' => $student_id,
            'by_role' => 'student',
            'ip' => $this->input->ip_address(),
        ];

        $this->db->where('id', $link_id)->update('parent_links', [
            'status' => 'revoked',
            'scope'  => $this->json($scope),
        ]);

        if ($this->db->affected_rows() < 1) return $this->fail('تعذر سحب الموافقة.');

        $this->announce_to_parent(
            (int) $row['parent_user_id'], $student_id, 'parent_link_withdrawn',
            'سحب ' . $this->name_of($student_id) . ' موافقته على الربط',
            'سحب ' . $this->name_of($student_id) . ' موافقته على ربط حسابه بحسابك، '
            . 'ولم تعد ترى شيئا من بياناته. ويبقى في السجل تاريخ موافقته وتاريخ سحبها.'
        );

        return ['ok' => true, 'message' => 'سحبت موافقتك، ولم يعد ولي أمرك يرى شيئا من بياناتك.'];
    }

    /** سحب طلب معلق — يحذف الصف، فلا شيء وقع ليؤرخ. */
    public function cancel_request($parent_id, $link_id)
    {
        $this->db->where('id', (int) $link_id)
                 ->where('parent_user_id', (int) $parent_id)
                 ->where('status', 'pending')
                 ->delete('parent_links');

        return $this->db->affected_rows() > 0
            ? ['ok' => true, 'message' => 'سحب طلب الربط.']
            : $this->fail('لا طلب معلق بهذا الرقم في حسابك.');
    }

    /**
     * إلغاء ربط ابن. الصف يبقى بحالة `revoked` ومعه تاريخ الموافقة الأصلي
     * وتاريخ السحب — الأثر لا يمحى، فالسجل هو ما يحتج به لاحقا.
     */
    public function revoke_link($parent_id, $student_id)
    {
        $parent_id  = (int) $parent_id;
        $student_id = (int) $student_id;

        $row = $this->db->where('parent_user_id', $parent_id)
                        ->where('student_id', $student_id)
                        ->where('status', 'active')
                        ->get('parent_links')->row_array();

        if (!$row) return $this->fail('لا رابط نشط بهذا الابن في حسابك.');

        $scope = $this->scope_of($row);
        $scope['revoked'] = [
            'at' => $this->now(),
            'by' => $parent_id,
            'ip' => $this->input->ip_address(),
        ];

        $this->db->where('id', (int) $row['id'])->update('parent_links', [
            'status' => 'revoked',
            'scope'  => $this->json($scope),
        ]);

        if ($this->db->affected_rows() < 1) return $this->fail('تعذر إلغاء الربط.');

        /* الابن يبلغ بفك الربط.
           كان الربط يقع بعلمه ويفك بلا علمه: يظل يحسب أن وليه يتابعه —
           وهو حكم يغير سلوكه. وطرفا العلاقة يبلغان بانتهائها كما بلغا ببدئها. */
        $this->announce_to_student(
            $student_id, $parent_id, 'parent_link_revoked',
            'أنهى ' . $this->name_of($parent_id) . ' متابعة حسابك',
            'ألغى ' . $this->name_of($parent_id) . ' ربط حسابك بحسابه، ولم يعد يرى شيئا من بياناتك. '
            . 'وأي متابعة جديدة تحتاج طلبا جديدا وموافقة جديدة منك.'
        );

        $name = $this->name_of($student_id);
        return ['ok' => true, 'message' => 'ألغي ربط ' . $name . '، ولم تعد ترى شيئا من بياناته.'];
    }

    /** روابط الطالب مع أولياء أمره — تقرأ من شاشة إعدادات الطالب. */
    public function links_of_student($student_id, $status = null)
    {
        $student_id = (int) $student_id;
        if ($student_id <= 0 || !$this->db->table_exists('parent_links')) return [];

        $sql = "SELECT pl.id, pl.parent_user_id, pl.status, pl.consent_at, pl.scope,
                       u.first_name, u.last_name, u.email
                  FROM parent_links pl
                  JOIN users u ON u.id = pl.parent_user_id
                 WHERE pl.student_id = ?";
        $bind = [$student_id];
        if ($status !== null) { $sql .= " AND pl.status = ?"; $bind[] = $status; }
        $sql .= " ORDER BY FIELD(pl.status,'pending','active','revoked'), pl.id DESC";

        $rows = $this->db->query($sql, $bind)->result_array();
        foreach ($rows as &$r) {
            $r['name']  = trim($r['first_name'] . ' ' . $r['last_name']);
            $r['prefs'] = $this->scope_of($r);
        }
        return $rows;
    }

    /* ================================================================
       الرسائل — بدء محادثة مع معلم ابن مربوط
       ================================================================ */

    /**
     * القائمة البيضاء: معلمو كورسات الأبناء المربوطين وحدهم.
     * `course.user_id` نص قد يحمل أكثر من معرف حين يكون الكورس بمعلمين،
     * فيفكك ويفحص كل معرف على `users` (معلم فعلا وحسابه مفعل).
     */
    public function teachers_for($parent_id)
    {
        $parent_id = (int) $parent_id;
        if ($parent_id <= 0 || !$this->db->table_exists('parent_links')) return [];

        $rows = $this->db->query(
            "SELECT c.id AS course_id, c.title AS course_title, c.user_id AS instructors,
                    u.first_name AS child_first, u.last_name AS child_last
               FROM parent_links pl
               JOIN users u  ON u.id = pl.student_id
               JOIN enrol e  ON e.user_id = pl.student_id
               JOIN course c ON c.id = e.course_id
              WHERE pl.parent_user_id = ? AND pl.status = 'active'",
            [$parent_id]
        )->result_array();

        $out = [];
        foreach ($rows as $r) {
            $child = trim($r['child_first'] . ' ' . $r['child_last']);
            foreach (explode(',', (string) $r['instructors']) as $raw) {
                $tid = (int) trim($raw);
                if ($tid <= 0) continue;

                if (!isset($out[$tid])) {
                    $t = $this->db->select('id, first_name, last_name, is_instructor, status')
                                  ->where('id', $tid)->get('users')->row_array();
                    if (!$t || (int) $t['status'] !== 1 || empty($t['is_instructor'])) continue;

                    $out[$tid] = [
                        'id'       => $tid,
                        'name'     => trim($t['first_name'] . ' ' . $t['last_name']),
                        'courses'  => [],
                        'children' => [],
                    ];
                }
                $out[$tid]['courses'][$r['course_title']] = true;
                $out[$tid]['children'][$child] = true;
            }
        }

        foreach ($out as &$t) {
            $t['courses']  = array_keys($t['courses']);
            $t['children'] = array_keys($t['children']);
        }
        return array_values($out);
    }

    /**
     * قائمة من يجوز لولي الأمر مراسلته — معلمو مواد أبنائه، ثم الدعم.
     *
     * الدعم كان ناقصا: الشاشة تقول تحت عنوانها «محادثاتك مع معلمي أبنائك
     * **وإدارة المنصة**»، والمتحكم يسمح بمراسلة الإدارة صراحة — ثم يفوض
     * إلى `start_thread` التي تفحص قائمة المعلمين وحدها فترفض. وعد مكتوب
     * في ثلاثة مواضع وباب مغلق في الرابع. وصفحة المدفوعات تحيل إليه
     * كذلك: «راسلنا من صفحة الرسائل» لطلب الاسترداد.
     *
     * والقائمة واحدة للرسم وللفحص: قائمة ترسم من مصدر وتفحص من آخر
     * تتباعد، فيرى ولي الأمر اسما في القائمة ويرفض إرساله إليه.
     */
    public function recipients_for($parent_id)
    {
        $out = $this->teachers_for($parent_id);

        $admin = $this->db->select('id, first_name, last_name')
                          ->where('role_id', 1)->where('status', 1)
                          ->order_by('id', 'ASC')->limit(1)
                          ->get('users')->row_array();

        if ($admin) {
            $out[] = [
                'id'       => (int) $admin['id'],
                'name'     => 'إدارة المنصة',
                'courses'  => ['الدعم والاستفسارات والاسترداد'],
                'children' => [],
                'support'  => true,
            ];
        }
        return $out;
    }

    /** هل يجوز لولي الأمر مراسلة هذا الحساب؟ يفحص قبل الكتابة لا عند الرسم. */
    public function may_message($parent_id, $teacher_id)
    {
        foreach ($this->recipients_for($parent_id) as $t) {
            if ((int) $t['id'] === (int) $teacher_id) return true;
        }
        return false;
    }

    /**
     * بدء محادثة: خيط إن لم يوجد، ثم رسالة. وتحدث `last_message_timestamp`
     * لأن قائمة المحادثات ترتب عليها — ومسار المنصة القديم لا يكتبها،
     * فكان الخيط الجديد يسقط إلى آخر القائمة بطابع فارغ.
     *
     * @return array{ok:bool, message:string, thread?:string}
     */
    public function start_thread($parent_id, $teacher_id, $text)
    {
        $parent_id  = (int) $parent_id;
        $teacher_id = (int) $teacher_id;
        $text       = trim((string) $text);

        if ($parent_id <= 0)  return $this->fail('لا جلسة مفتوحة.');
        if ($teacher_id <= 0) return $this->fail('اختر المعلم الذي تريد مراسلته.');
        if ($text === '')     return $this->fail('اكتب نص رسالتك.');
        if (mb_strlen($text) > 4000) return $this->fail('الرسالة أطول من أربعة آلاف حرف.');

        if (!$this->may_message($parent_id, $teacher_id)) {
            return $this->fail('لا تراسل إلا معلمي مواد أبنائك المربوطين أو إدارة المنصة.');
        }

        $thread = $this->db->query(
            "SELECT message_thread_code FROM message_thread
              WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?)
              LIMIT 1",
            [$parent_id, $teacher_id, $teacher_id, $parent_id]
        )->row_array();

        $now = time();

        if ($thread) {
            $code = $thread['message_thread_code'];
        } else {
            $code = $this->thread_code();
            $this->db->insert('message_thread', [
                'message_thread_code'    => $code,
                'sender'                 => $parent_id,
                'receiver'               => $teacher_id,
                'last_message_timestamp' => $now,
            ]);
            if ((int) $this->db->insert_id() <= 0) return $this->fail('تعذر فتح المحادثة.');
        }

        $this->db->insert('message', [
            'message_thread_code' => $code,
            'message'             => html_escape($text),
            'sender'              => $parent_id,
            'receiver'            => $teacher_id,
            'timestamp'           => $now,
            'read_status'         => 0,
        ]);
        if ((int) $this->db->insert_id() <= 0) return $this->fail('تعذر حفظ الرسالة.');

        $this->db->where('message_thread_code', $code)
                 ->update('message_thread', ['last_message_timestamp' => $now]);

        return ['ok' => true, 'thread' => $code, 'message' => 'أرسلت رسالتك.'];
    }

    /**
     * رد داخل خيط قائم — لا يفتح خيط ليس ولي الأمر طرفا فيه، ويفحص ذلك
     * في الاستعلام نفسه لا في الواجهة. ويحدث طابع آخر رسالة كي لا يسقط
     * الخيط إلى آخر القائمة (مسار المنصة القديم لا يكتبه).
     */
    public function reply_thread($parent_id, $code, $text)
    {
        $parent_id = (int) $parent_id;
        $code      = (string) $code;
        $text      = trim((string) $text);

        if ($parent_id <= 0) return $this->fail('لا جلسة مفتوحة.');
        if ($text === '')    return $this->fail('اكتب نص ردك.');

        $thread = $this->db->query(
            "SELECT sender, receiver FROM message_thread
              WHERE message_thread_code = ? AND (sender = ? OR receiver = ?) LIMIT 1",
            [$code, $parent_id, $parent_id]
        )->row_array();

        if (!$thread) return $this->fail('لا محادثة بهذا الرمز في حسابك.');

        $other = ((int) $thread['sender'] === $parent_id) ? (int) $thread['receiver'] : (int) $thread['sender'];
        $now   = time();

        $this->db->insert('message', [
            'message_thread_code' => $code,
            'message'             => html_escape($text),
            'sender'              => $parent_id,
            'receiver'            => $other,
            'timestamp'           => $now,
            'read_status'         => 0,
        ]);
        if ((int) $this->db->insert_id() <= 0) return $this->fail('تعذر حفظ الرد.');

        $this->db->where('message_thread_code', $code)
                 ->update('message_thread', ['last_message_timestamp' => $now]);

        return ['ok' => true, 'thread' => $code, 'message' => 'أرسل ردك.'];
    }

    /* ================================================================
       المال — دفتر واحد من مصدريه
       ================================================================
       المال في هذه المنصة في مكانين لا مكان واحد:

         · `invoices` + `subscriptions` — مسار تقدر، وهو المسار العامل
           اليوم (اشتراك بباقة، فاتورة بمبلغ **بالهللات**).
         · `payment` — جدول Academy الأصلي، شراء كورس مفرد بمبلغ
           **بالريالات**.

       وشاشة المدفوعات كانت تقرأ `payment` وحده. وهو فارغ في هذه القاعدة
       بينما لابن ولي الأمر أربعة اشتراكات وأربع فواتير وواحد منها نشط
       بثلاثمئة وتسعين ريالا — فيقرأ ولي أمر يدفع فعلا: «لا مدفوعات بعد
       · 0 ريال». وهذا أسوأ من صفحة معطوبة: صفحة تعمل وتكذب.

       والوحدة تنقل هنا مرة واحدة: الدفتر يخرج بالريالات دائما، فلا تقسم
       على مئة في شاشة وتنسى في أخرى. */

    /**
     * دفتر مدفوعات مستخدم — مصدراه مدمجان ومرتبان بالتاريخ.
     *
     * @return array صفوف: [ts, amount (ريال), title, ref, method, status, source]
     */
    public function payments_of($user_id, $limit = 50)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return [];

        $rows = [];

        /* فواتير تقدر — المبلغ بالهللات، والحالة تعرض كما هي:
           «مدفوعة» و«بانتظار التحويل» و«مستردة» ثلاث حقائق لا واحدة. */
        if ($this->db->table_exists('invoices')) {
            /* TQ-I18N — تسميات تعرض ولا تخزن، فتترجم بلغة من يقرؤها. */
            $labels = ['paid' => t('مدفوعة'), 'unpaid' => t('بانتظار التحويل'), 'refunded' => t('مستردة')];
            foreach ($this->db->query(
                "SELECT i.id, i.invoice_no, i.total, i.status, i.method, i.transaction_id,
                        i.issued_at, i.paid_at, p.name_ar AS plan_name
                   FROM invoices i
                   LEFT JOIN subscriptions s ON s.id = i.subscription_id
                   LEFT JOIN plans p ON p.id = s.plan_id
                  WHERE i.user_id = ?
                  ORDER BY i.id DESC LIMIT ?",
                [$user_id, (int) $limit]
            )->result_array() as $r) {
                $when = !empty($r['paid_at']) ? $r['paid_at'] : $r['issued_at'];
                $rows[] = [
                    'ts'     => $when ? strtotime($when) : 0,
                    'amount' => ((int) $r['total']) / 100,
                    /* اسم الباقة بيانات صاحبها فلا يترجم؛ والبديل حين لا اسم
                       نص من الشيفرة فيترجم. */
                    'title'  => $r['plan_name'] ?: t('اشتراك'),
                    'ref'    => $r['transaction_id'] ?: $r['invoice_no'],
                    'method' => (string) $r['method'],
                    'status' => $r['status'],
                    'label'  => $labels[$r['status']] ?? $r['status'],
                    'source' => 'invoice',
                ];
            }
        }

        /* مدفوعات Academy — المبلغ بالريالات، ولا حالة لها: صف هنا يعني
           عملية تمت. */
        foreach ($this->db->query(
            "SELECT p.id, p.amount, p.date_added, p.payment_type, p.transaction_id,
                    c.title AS course_title
               FROM payment p
               LEFT JOIN course c ON c.id = p.course_id
              WHERE p.user_id = ?
              ORDER BY p.date_added DESC LIMIT ?",
            [$user_id, (int) $limit]
        )->result_array() as $r) {
            $rows[] = [
                'ts'     => (int) $r['date_added'],
                'amount' => (float) $r['amount'],
                'title'  => $r['course_title'] ?: t('شراء'),
                'ref'    => (string) $r['transaction_id'],
                'method' => (string) $r['payment_type'],
                'status' => 'paid',
                'label'  => t('مدفوعة'),
                'source' => 'payment',
            ];
        }

        usort($rows, function ($a, $b) { return $b['ts'] <=> $a['ts']; });

        return array_slice($rows, 0, (int) $limit);
    }

    /**
     * مجاميع الدفتر — المدفوع وحده يجمع.
     *
     * فاتورة معلقة ليست مالا خرج من الجيب، وعدها في «مجموع ما دفعته»
     * يعطي ولي الأمر رقما أكبر مما دفع فعلا. وتعرض معلقة على حدة.
     */
    public function payment_totals($rows, $month_start = null)
    {
        $month_start = $month_start === null ? strtotime(date('Y-m-01 00:00:00')) : (int) $month_start;
        $out = ['month' => 0.0, 'all' => 0.0, 'pending' => 0.0, 'pending_count' => 0];

        foreach ((array) $rows as $r) {
            if ($r['status'] === 'unpaid') {
                $out['pending'] += (float) $r['amount'];
                $out['pending_count']++;
                continue;
            }
            if ($r['status'] === 'refunded') continue;

            $out['all'] += (float) $r['amount'];
            if ((int) $r['ts'] >= $month_start) $out['month'] += (float) $r['amount'];
        }
        return $out;
    }

    /* ================================================================
       الإعدادات
       ================================================================ */

    /** بيانات ولي الأمر. */
    public function profile($parent_id)
    {
        return $this->db->select('id, first_name, last_name, email, phone, address, image')
                        ->where('id', (int) $parent_id)->get('users')->row_array() ?: [];
    }

    /** تعديل بيانات ولي الأمر — البريد فريد في `users` فيفحص قبل الكتابة. */
    public function save_profile($parent_id, $data)
    {
        $parent_id = (int) $parent_id;
        if ($parent_id <= 0) return $this->fail('لا جلسة مفتوحة.');

        $first = trim((string) ($data['first_name'] ?? ''));
        $last  = trim((string) ($data['last_name']  ?? ''));
        $email = trim((string) ($data['email']      ?? ''));
        $phone = trim((string) ($data['phone']      ?? ''));
        $addr  = trim((string) ($data['address']    ?? ''));

        if ($first === '' || $last === '') return $this->fail('الاسم الأول واسم العائلة مطلوبان.');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->fail('البريد الإلكتروني غير صحيح.');
        if (mb_strlen($email) > 50) return $this->fail('البريد الإلكتروني أطول مما يقبله الحساب.');

        $taken = (int) $this->db->where('email', $email)->where('id !=', $parent_id)
                                ->count_all_results('users');
        if ($taken > 0) return $this->fail('هذا البريد مستعمل في حساب آخر.');

        /* TQ-PHONE-INTL — يخزن `+<رمز><وطني>` كما يخزنه التسجيل.
           وعلى هذا الرقم تصل تنبيهات الأبناء بواتساب، فرقم يحفظ عاريا
           من رمز دولته يذهب إلى بلد آخر أو لا يذهب. */
        if ($phone !== '') {
            $ph = tq_phone_check($phone, $data['phone_cc'] ?? '');
            if (!$ph['ok']) return $this->fail($ph['error']);
            $phone = $ph['e164'];
        }

        $this->db->where('id', $parent_id)->update('users', [
            'first_name'    => $first,
            'last_name'     => $last,
            'email'         => $email,
            'phone'         => $phone,
            'address'       => $addr,
            'last_modified' => time(),
        ]);

        return ['ok' => true, 'message' => 'حفظت بياناتك.'];
    }

    /**
     * تغيير كلمة المرور من داخل البوابة.
     * زر «تغيير» كان يشير إلى `/profile` وهو يرجع 500، فالمستخدم يصطدم
     * بجدار. والتحقق والتعمية يمران بدالتي المنصة نفسهما
     * (`tq_password_matches` و`tq_password_hash` في config.php): المنصة
     * ترقي كلمات المرور القديمة إلى `password_hash()` عند أول دخول ناجح،
     * فكتابة `sha1` هنا كانت تنزل الحساب درجة في الأمان وتكسر التحقق.
     */
    public function change_password($parent_id, $current, $new, $confirm)
    {
        $parent_id = (int) $parent_id;
        if ($parent_id <= 0) return $this->fail('لا جلسة مفتوحة.');

        if ($current === '' || $new === '' || $confirm === '') return $this->fail('املأ الحقول الثلاثة.');
        if ($new !== $confirm) return $this->fail('كلمة المرور الجديدة وتأكيدها غير متطابقين.');
        if (mb_strlen($new) < 6) return $this->fail('اجعل كلمة المرور ستة أحرف فأكثر.');

        $row = $this->db->select('password')->where('id', $parent_id)->get('users')->row_array();
        if (!$row) return $this->fail('الحساب غير موجود.');

        $matches = function_exists('tq_password_matches')
            ? tq_password_matches($current, (string) $row['password'])
            : hash_equals((string) $row['password'], sha1($current));
        if (!$matches) return $this->fail('كلمة المرور الحالية غير صحيحة.');

        $this->db->where('id', $parent_id)->update('users', [
            'password'      => function_exists('tq_password_hash') ? tq_password_hash($new) : sha1($new),
            'last_modified' => time(),
        ]);

        return ['ok' => true, 'message' => 'غيرت كلمة المرور.'];
    }

    /**
     * حفظ تفضيلات الإشعارات وخطة الأسبوع لكل ابن — في `parent_links.scope`،
     * وهو موضعها الطبيعي: نطاق ما يصل ولي الأمر عن هذا الابن بعينه.
     */
    public function save_prefs($parent_id, $notify, $plan_days = [])
    {
        $parent_id = (int) $parent_id;
        if ($parent_id <= 0) return $this->fail('لا جلسة مفتوحة.');

        $clean = [];
        foreach ($this->notify_defaults() as $k => $v) {
            $clean[$k] = !empty($notify[$k]) ? 1 : 0;
        }

        /* التنبيهات: صف لكل نوع باسم صاحب الحساب — تحفظ سواء كان له
           رابط أم لا. وكانت مشروطة بوجود رابط، فمن ألغى ربطه أو ينتظر
           موافقة ابنه يجد نموذجا كاملا يرفض أن يحفظ. */
        $saved_notify = false;
        if ($this->db->table_exists('tq_prefs_notify')) {
            $now = time();
            foreach ($clean as $type => $on) {
                $this->db->replace('tq_prefs_notify', [
                    'user_id'     => $parent_id,
                    'notify_type' => $type,
                    'channel'     => self::NOTIFY_CHANNEL,
                    'enabled'     => $on,
                    'updated_at'  => $now,
                ]);
            }
            $saved_notify = true;
        }

        /* خطة الأيام: في نطاق الرابط لأنها عن الابن لا عن أبيه.
           والقيمة الفارغة تعني «غير محددة» فتحذف بدل أن تكتب رقما لم
           يختره أحد — الخانة المنسدلة كانت تنتقي أول خيار (يوم واحد)
           حين لا خطة، فأول حفظ يكتب خطة يوم واحد لكل ابن بلا أن يلمس
           ولي الأمر الحقل، ثم يقرأ في التقرير «باقية أربعة أيام» وقد
           صارت خطته يوما. */
        $rows = $this->db->where('parent_user_id', $parent_id)->get('parent_links')->result_array();

        foreach ($rows as $r) {
            $scope = $this->scope_of($r);
            $sid   = (int) $r['student_id'];
            $dirty = false;

            // الموضع القديم يفرغ حين ينجح الجديد، فلا مصدران للحقيقة
            if ($saved_notify && isset($scope['notify'])) {
                unset($scope['notify']);
                $dirty = true;
            } elseif (!$saved_notify) {
                $scope['notify'] = $clean;
                $dirty = true;
            }

            if (array_key_exists($sid, (array) $plan_days)) {
                $raw = trim((string) $plan_days[$sid]);
                if ($raw === '') {
                    if (isset($scope['plan_days'])) { unset($scope['plan_days']); $dirty = true; }
                } else {
                    $d = (int) $raw;
                    if ($d >= 1 && $d <= 7) { $scope['plan_days'] = $d; $dirty = true; }
                }
            }

            if ($dirty) {
                $this->db->where('id', (int) $r['id'])->update('parent_links', ['scope' => $this->json($scope)]);
            }
        }

        if (!$saved_notify && !$rows) {
            return $this->fail('تعذر حفظ التفضيلات — لا جدول تفضيلات ولا روابط في حسابك.');
        }

        return ['ok' => true, 'message' => 'حفظت تفضيلاتك.'];
    }

    /* ================================================================
       منفذ POST للشاشات
       ================================================================
       الشاشات تنشر إلى مسارها نفسه (`parent/<section>`) وتستدعي هذه الدالة
       أول سطر. وحين يوجد متحكم بمسارات مخصصة، يستدعي الدوال أعلاه مباشرة
       ويغير `action` في النموذج — الطبقة المكتوبة واحدة في الحالتين.
    */
    public function handle_post($section)
    {
        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') return;

        $parent_id = (int) $this->session->userdata('user_id');
        if ($parent_id <= 0) return;

        /* حارس نفس الموقع: حماية CSRF في المنصة معطلة في `config.php`
           (`csrf_protection = FALSE`) لأن العروض لا تحمل رمزا بعد، ومسارات
           الدخول تقارن Origin/Referer بالمضيف بدلا منه. وهذه نماذج تكتب —
           إلغاء ربط أو رسالة أو تغيير بيانات — فتمر بالحارس نفسه. */
        if (function_exists('tq_auth_verify_origin') && !tq_auth_verify_origin()) {
            $this->session->set_flashdata('tq_error', 'طلب من موقع آخر — لم ينفذ. أعد المحاولة من هذه الصفحة.');
            redirect(site_url('parent/' . $section), 'location', 303);
        }

        $action = (string) $this->input->post('tq_action');
        $back   = site_url('parent/' . $section);
        $res    = null;

        switch ($action) {

            case 'link_request':
                $res = $this->request_link($parent_id, $this->input->post('identifier'));
                break;

            case 'link_cancel':
                $res = $this->cancel_request($parent_id, (int) $this->input->post('link_id'));
                break;

            case 'link_revoke':
                $res = $this->revoke_link($parent_id, (int) $this->input->post('student_id'));
                break;

            case 'message_new':
                $res = $this->start_thread($parent_id, (int) $this->input->post('receiver'), $this->input->post('message'));
                if (!empty($res['ok'])) $back = site_url('parent/messages') . '?thread=' . rawurlencode($res['thread']);
                break;

            case 'message_reply':
                $res = $this->reply_thread($parent_id, $this->input->post('thread'), $this->input->post('message'));
                if (!empty($res['ok'])) $back = site_url('parent/messages') . '?thread=' . rawurlencode($res['thread']);
                break;

            case 'profile_save':
                $res = $this->save_profile($parent_id, [
                    'first_name' => $this->input->post('first_name'),
                    'last_name'  => $this->input->post('last_name'),
                    'email'      => $this->input->post('email'),
                    'phone'      => $this->input->post('phone'),
                    'address'    => $this->input->post('address'),
                ]);
                break;

            case 'password_change':
                $res = $this->change_password(
                    $parent_id,
                    (string) $this->input->post('current_password'),
                    (string) $this->input->post('new_password'),
                    (string) $this->input->post('confirm_password')
                );
                break;

            case 'prefs_save':
                $res = $this->save_prefs(
                    $parent_id,
                    (array) ($this->input->post('notify') ?: []),
                    (array) ($this->input->post('plan_days') ?: [])
                );
                break;

            default:
                return;
        }

        $this->session->set_flashdata(
            empty($res['ok']) ? 'tq_error' : 'tq_ok',
            (string) ($res['message'] ?? '')
        );

        // 303 لأن ما بعد النشر عرض لا إعادة نشر — ويتبعه العميل بلا لبس.
        redirect($back, 'location', 303);
    }

    /** شريط الرسالة بعد النشر — تطبع في أعلى الشاشات الثلاث التي تكتب. */
    public function flash_html()
    {
        /* مفتاحان: مفاتيح شاشات تقدر ومفاتيح المنصة. الحارس `tq_guard`
           ومسارات أكاديمي تكتب بالثانية، ورسالة لا تقرأ كأنها لم تكتب. */
        $ok  = $this->session->flashdata('tq_ok')    ?: $this->session->flashdata('flash_message');
        $err = $this->session->flashdata('tq_error') ?: $this->session->flashdata('error_message');
        if (!$ok && !$err) return '';

        $text = html_escape((string) ($err ?: $ok));
        $fam  = $err ? 'rose' : 'mint';
        $lab  = $err ? 'لم يتم' : 'تم';

        return '<div class="tq-pastel tq-pastel--' . $fam . '" role="status" style="margin-block-end:var(--tq-space-xl)">'
             . '<span class="tq-pastel__label tq-micro">' . $lab . '</span>'
             . '<p class="tq-pastel__body" style="margin:var(--tq-space-xs) 0 0">' . $text . '</p>'
             . '</div>';
    }

    /* ================================================================
       داخليات
       ================================================================ */

    private function fail($message)
    {
        return ['ok' => false, 'message' => $message];
    }

    /**
     * ساعة واحدة لتواريخ `parent_links`: ساعة القاعدة.
     * ساعة PHP على الويب هنا تسبق ساعة القاعدة بثلاث ساعات (توقيت الرياض
     * في lsphp مقابل UTC في MariaDB)، فلو كتب تاريخ الموافقة بساعة PHP
     * لاختلف عن `NOW()` الذي تكتب به الإدارة الصف نفسه — وتاريخ الموافقة
     * حجة قانونية لا يقبل فيها اختلاف الساعتين.
     */
    private function now()
    {
        $row = $this->db->query('SELECT NOW() AS n')->row_array();
        return !empty($row['n']) ? (string) $row['n'] : date('Y-m-d H:i:s');
    }

    private function json($scope)
    {
        $out = json_encode($scope, JSON_UNESCAPED_UNICODE);
        return $out === false ? '{}' : $out;   // العمود يشترط JSON صالحا
    }

    private function scope_of($row)
    {
        if (empty($row['scope'])) return [];
        $d = json_decode((string) $row['scope'], true);
        return is_array($d) ? $d : [];
    }

    private function name_of($user_id)
    {
        $u = $this->db->select('first_name, last_name')->where('id', (int) $user_id)->get('users')->row_array();
        return $u ? trim($u['first_name'] . ' ' . $u['last_name']) : 'الحساب';
    }

    /** رمز خيط بطول رموز المنصة نفسه (30 محرفا) وبعشوائية صالحة. */
    private function thread_code()
    {
        $abc = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        for ($i = 0; $i < 30; $i++) $out .= $abc[random_int(0, strlen($abc) - 1)];
        return $out;
    }

    /**
     * إشعار واحد يكتب في `notifications` — أساس كل تبليغ في هذه الطبقة.
     *
     * كل أحداث الربط تكتب هنا بلا مرور بـ`Taqdar_events_model`: تلك تفرض
     * تفضيلات ولي الأمر وتكتم ما أوقفه، وأحداث الربط نفسه ليست من الخمسة
     * التي يختارها — ولا يكتم عن أحد خبر فتح بياناته أو إغلاقها.
     */
    private function announce($to_user, $from_user, $type, $title, $body)
    {
        if (!$this->db->table_exists('notifications')) return;
        $now = time();

        $this->db->insert('notifications', [
            'from_user'   => (int) $from_user ?: null,
            'to_user'     => (int) $to_user,
            'type'        => $type,
            'title'       => mb_substr($title, 0, 250),
            'description' => $body,
            'status'      => 0,
            'created_at'  => (string) $now,
            'updated_at'  => null,
        ]);

        $this->announce_by_mail($to_user, $title, $body);
    }

    /**
     * TQ-LINK-MAIL — أحداث الربط تصل بالبريد أيضا.
     *
     * الإشعار داخل المنصة لا يكفي هنا وحده، وهو أوضح ما يكون في هذا
     * المسار بالذات: **ولي الأمر لا يدخل المنصة يوميا.** يسجل، ويطلب
     * ربط ابنه، ثم ينتظر. وطلبه يعرض على الابن في جرسه — وحين يوافق أو
     * يرفض يكتب لولي الأمر إشعار في جرس لا يفتحه أحد. فيبقى ولي الأمر
     * يظن الطلب معلقا أسابيع، أو يرسله ثانية فيصير طلبان.
     *
     * وكذلك الطالب: طلب ربط يفتح تقاريره لطرف آخر لا ينبغي أن يعلم به
     * حين يمر بالمنصة صدفة.
     *
     * والبريد **تابع لا شرط**: `Taqdar_mail_model` يرد `false` بهدوء حين
     * لا يكون مضبوطا، فلا يمنع فشل الإرسال ربطا تم في القاعدة أصلا.
     */
    private function announce_by_mail($to_user, $title, $body)
    {
        try {
            $u = $this->db->select('email')->where('id', (int) $to_user)
                          ->get('users')->row_array();
            if (!$u || empty($u['email'])) return;

            $this->load->model('taqdar_mail_model');
            $this->taqdar_mail_model->send_lines(
                $u['email'],
                $title,
                [strip_tags((string) $body)],
                ['label' => 'افتح المنصة', 'href' => site_url('login')]
            );
        } catch (Throwable $e) {
            log_message('error', 'parent_links mail: ' . $e->getMessage());
        }
    }

    /** تبليغ ولي الأمر بنتيجة طلبه — موافقة أو رفضا أو سحبا. */
    private function announce_to_parent($parent_id, $student_id, $type, $title, $body)
    {
        $this->announce($parent_id, $student_id, $type, $title, $body);
    }

    /** تبليغ الابن بما يقع على رابطه من طرف وليه. */
    private function announce_to_student($student_id, $parent_id, $type, $title, $body)
    {
        $this->announce($student_id, $parent_id, $type, $title, $body);
    }

    /**
     * تبليغ الابن بطلب الربط: إشعار في `notifications` ورسالة خاصة بنص
     * الموافقة. طلب لا يراه صاحبه ليس طلبا.
     */
    private function announce_request($parent_id, $student, $link_id)
    {
        $parent = $this->name_of($parent_id);
        $sid    = (int) $student['id'];
        $now    = time();

        if ($this->db->table_exists('notifications')) {
            $this->db->insert('notifications', [
                'from_user'   => $parent_id,
                'to_user'     => $sid,
                'type'        => 'parent_link_request',
                'title'       => 'طلب ربط ولي أمر',
                'description' => $parent . ' يطلب ربط حسابك بحسابه. لا يفتح شيء من بياناتك قبل موافقتك.',
                'status'      => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        /* والبريد كذلك — انظر TQ-LINK-MAIL: الطالب قد لا يفتح جرسه أياما،
           وطلب يفتح تقاريره لطرف آخر لا ينتظر أن يمر صاحبه صدفة. */
        $this->announce_by_mail($sid, 'طلب ربط ولي أمر',
            $parent . ' يطلب ربط حسابك بحسابه في تقدر. ادخل إلى إعدادات حسابك لتوافق أو ترفض. '
          . 'ولا يفتح شيء من بياناتك قبل موافقتك.');

        $body = $parent . ' طلب ربط حسابك بحسابه في تقدر (طلب رقم ' . (int) $link_id . '). '
              . 'نص الموافقة: ' . self::CONSENT_TEXT . ' '
              . 'ولا يفتح شيء من بياناتك قبل أن توافق أنت.';

        $thread = $this->db->query(
            "SELECT message_thread_code FROM message_thread
              WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?) LIMIT 1",
            [$parent_id, $sid, $sid, $parent_id]
        )->row_array();

        if ($thread) {
            $code = $thread['message_thread_code'];
        } else {
            $code = $this->thread_code();
            $this->db->insert('message_thread', [
                'message_thread_code'    => $code,
                'sender'                 => $parent_id,
                'receiver'               => $sid,
                'last_message_timestamp' => $now,
            ]);
        }

        $this->db->insert('message', [
            'message_thread_code' => $code,
            'message'             => html_escape($body),
            'sender'              => $parent_id,
            'receiver'            => $sid,
            'timestamp'           => $now,
            'read_status'         => 0,
        ]);

        $this->db->where('message_thread_code', $code)
                 ->update('message_thread', ['last_message_timestamp' => $now]);
    }
}
