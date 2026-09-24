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
            'quiz_result'      => 'نتيجة اختبار درس',
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

        /* TQ-DELETED-CHILD — الحساب المحذوف لا يعرض. حذف الحساب يجهله
           (`Taqdar::delete_account()` يكتب «حساب محذوف» ويغلقه) ولا يمس
           `parent_links`، فكان يبقى عند وليه ابنا «مفعلا» باسم «حساب محذوف»:
           في التقرير الأسبوعي، وفي الإعدادات بزر إلغاء ربط، وفي منتقي من يدفع
           عنه. والشرط هنا في مصدر الروابط الواحد، فتسقط من كل الشاشات معا. */
        $sql = "SELECT pl.id, pl.parent_user_id, pl.student_id, pl.status, pl.consent_at, pl.scope,
                       u.first_name, u.last_name, u.email, u.image
                  FROM parent_links pl
                  JOIN users u ON u.id = pl.student_id
                 WHERE pl.parent_user_id = ?
                   AND u.email NOT LIKE '%@deleted.invalid'";
        $bind = [$parent_id];

        if ($status !== null) { $sql .= " AND pl.status = ?"; $bind[] = $status; }
        $sql .= " ORDER BY FIELD(pl.status,'active','pending','revoked'), u.first_name ASC";

        $rows = $this->db->query($sql, $bind)->result_array();
        foreach ($rows as &$r) {
            $r['name']   = trim($r['first_name'] . ' ' . $r['last_name']);
            $r['prefs']  = $this->scope_of($r);
            /* TQ-LINK-ENUM — من لم يوافق قط لا يكشف اسمه ولا صورته: يعرف
               بالبريد الذي كتبه ولي الأمر. والاسم لمن وافق يوما (نشط، أو
               موافقة في النطاق، أو موافقة سابقة ورثها طلب معاد). والقاعدة
               هنا مرة: شاشة الإعدادات والتطبيق يقرآنها ولا يعيدان حسابها. */
            $r['named']  = $r['status'] === 'active'
                        || !empty($r['prefs']['consent']) || !empty($r['prefs']['previous_consent']);
            $r['closed'] = $this->closed_of($r);
        }
        return $rows;
    }

    /**
     * لماذا أغلق الرابط، ومن أغلقه، ومتى — `null` لما ليس `revoked`.
     *
     * «رفض» غير «سحب»: الأول قرار الابن قبل أن يوافق (`scope.rejected`)،
     * والثاني فك رابط كان قائما (`scope.revoked`). و`by` من صاحب المعرف
     * المختوم: الابن أو ولي الأمر، وما سواهما إدارة.
     */
    public function closed_of($link)
    {
        if (($link['status'] ?? '') !== 'revoked') return null;
        $p = isset($link['prefs']) ? $link['prefs'] : $this->scope_of($link);

        $rej = (isset($p['rejected']) && is_array($p['rejected'])) ? $p['rejected'] : null;
        $rev = (isset($p['revoked'])  && is_array($p['revoked']))  ? $p['revoked']  : null;
        $ev  = $rej ?: $rev;
        if (!$ev) return array('reason' => 'revoked', 'by' => null, 'at' => null);

        $by_id = (int) ($ev['by'] ?? 0);
        if ($by_id > 0 && $by_id === (int) $link['student_id'])          $by = 'student';
        elseif ($by_id > 0 && $by_id === (int) $link['parent_user_id'])  $by = 'parent';
        elseif ($by_id > 0)                                              $by = 'admin';
        else                                                             $by = null;

        return array('reason' => $rej ? 'rejected' : 'revoked', 'by' => $by, 'at' => $ev['at'] ?? null);
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

        /* والمحذوف لا يملكه أحد (TQ-DELETED-CHILD). */
        return (int) $this->db->query(
            "SELECT COUNT(*) n FROM parent_links pl JOIN users u ON u.id = pl.student_id
              WHERE pl.parent_user_id = ? AND pl.student_id = ? AND pl.status = 'active'
                AND u.email NOT LIKE '%@deleted.invalid'",
            [$parent_id, $student_id])->row('n') > 0;
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

    /**
     * يكتب خطة أيام ابن واحد — ولا يمس غيرها.
     *
     * `save_prefs()` تكتب مفاتيح التنبيه من الجسم نفسه، فنداؤها لأجل خطة
     * ابن يطفئ كل تنبيه لم يرسل معها. وهذه تمس `scope.plan_days` وحدها.
     * و`null` = «غير محددة» فتحذف ولا يكتب رقم لم يختره أحد.
     *
     * @return array {ok, days, is_default} أو {ok:false, message}
     */
    public function set_plan_days($parent_id, $student_id, $days)
    {
        $row = $this->db->query(
            "SELECT id, scope FROM parent_links
              WHERE parent_user_id = ? AND student_id = ? AND status = 'active' LIMIT 1",
            [(int) $parent_id, (int) $student_id]
        )->row_array();
        if (!$row || !$this->owns($parent_id, $student_id)) {
            return $this->fail('لا رابط نشط بهذا الابن في حسابك.');
        }

        $scope = $this->scope_of($row);
        if ($days === null) {
            unset($scope['plan_days']);
        } else {
            $d = (int) $days;
            if ($d < 1 || $d > 7) return $this->fail('خطة الأسبوع من يوم إلى سبعة أيام.');
            $scope['plan_days'] = $d;
        }
        $this->db->where('id', (int) $row['id'])->update('parent_links', ['scope' => $this->json($scope)]);

        $p = $this->plan_days($parent_id, $student_id);
        return ['ok' => true, 'days' => $p['days'], 'is_default' => $p['is_default'],
                'message' => 'حفظت خطة الأسبوع.'];
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
        $identifier = strtolower(trim((string) $identifier));

        if ($parent_id <= 0) return $this->fail('لا جلسة مفتوحة.');

        /* ═══ TQ-LINK-ENUM — النموذج كان كشافا لحسابات المنصة ═══
           كان يقبل **رقم الحساب**، والأرقام متسلسلة (١ ثم ٢ ثم ٣…)، ولكل رقم
           رد يختلف: «لا حساب بهذا الرقم» · «حساب ابنك غير مفعل» · «هذا حساب
           إدارة» · «هذا حساب معلم» · «أرسل طلب الربط إلى [الاسم الكامل]». فأي
           زائر يفتح حساب ولي أمر من صفحة التسجيل يعد الأرقام فيعرف حسابات
           الإدارة والمعلمين والاسم الكامل لكل طالب — وأكثرهم قاصرون — ويرسل
           إلى مئات منهم طلب ربط من «ولي أمر» لا يعرفونه. ولا حد لعدد المحاولات.
           فالعلاج ثلاثة معا:
             · **البريد وحده**: لا يخمن كما يخمن رقم متسلسل.
             · **رد واحد** مهما كان الحساب: موجودا أو لا، طالبا أو لا، مفعلا أو
               لا — ولا اسم فيه. والاسم يعرفه ولي الأمر حين يوافق ابنه.
             · **حد للمحاولات** بآلة الخنق نفسها التي تحرس الدخول. */
        if ($identifier === '' || !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('اكتب البريد الإلكتروني الذي يدخل به ابنك إلى تقدر.');
        }
        if (!$this->db->table_exists('parent_links')) return $this->fail('جدول الروابط غير موجود.');

        $tq_key = 'parent:' . $parent_id;
        if (function_exists('tq_auth_is_throttled') && tq_auth_is_throttled($tq_key, 'parent_link')) {
            return $this->fail(function_exists('tq_auth_throttle_message')
                ? tq_auth_throttle_message() : 'أرسلت طلبات كثيرة في وقت قصير. أعد المحاولة لاحقا.');
        }
        if (function_exists('tq_auth_record_failure')) tq_auth_record_failure($tq_key, 'parent_link');

        $neutral = [
            'ok'      => true,
            'link_id' => 0,
            'message' => 'إن كان هذا البريد لحساب طالب مفعل في تقدر فقد وصله طلب الربط بنص الموافقة، '
                       . 'ويظهر ابنك في حسابك بعد أن يوافق من حسابه. ولا يفتح شيء من بياناته قبل ذلك.',
        ];

        $student = $this->db->where('email', $identifier)->get('users')->row_array();

        if (!$student || (int) $student['status'] !== 1
            || substr((string) $student['email'], -16) === '@deleted.invalid') {
            return $neutral;
        }
        if ((int) $student['id'] === $parent_id) return $this->fail('لا يكون المستخدم ولي أمر نفسه.');

        $role = tq_role((int) $student['id']);
        if ($role !== 'student') return $neutral;

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
            /* المرتبط فعلا ابنه ويعرفه، فيقال له؛ والمعلق يرد بالرد الواحد —
               «طلبك ما زال بانتظار فلان» اسم لم يوافق صاحبه على كشفه. */
            if ($existing['status'] === 'active')  return $this->fail('هذا الحساب مرتبط بحسابك بالفعل.');
            if ($existing['status'] === 'pending') return $neutral;

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

        $neutral['link_id'] = $link_id;
        return $neutral;
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
            /* TQ-TZ-DISPLAY — `$now` ساعة القاعدة (UTC) تلصق في نص يقرؤه ولي الأمر
               ويخرج بالبريد والواتساب معه. والتاريخ المعروض يبنى من ساعة التطبيق
               بصيغة المنصة المطلقة. ولا يمس ما يخزن. */
            $this->name_of($row['student_id']) . ' وافق على ربط حسابه بحسابك بتاريخ ' . date('Y/m/d — H:i')
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
            "SELECT c.id AS course_id, c.title AS course_title,
                    CONCAT_WS(',', c.user_id, c.creator) AS instructors,
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

        /* TQ-MSG-UNLINKED — الرد يمر بالقائمة البيضاء نفسها التي يمر بها البدء.
           كان يفحص أن ولي الأمر طرف في الخيط وحده: فمن فك ربط ابنه يبقى يراسل
           معلمه من المحادثة القديمة — وقد انتهى السبب الذي أجاز المراسلة.
           وخيطه مع ابنه المرتبط (طلب الربط) يبقى مفتوحا. */
        if (!$this->may_message($parent_id, $other) && !$this->owns($parent_id, $other)) {
            return $this->fail('لم يعد هذا الحساب معلما لأحد أبنائك المرتبطين، فلا يرد عليه من هنا. وإن احتجت فراسل إدارة المنصة.');
        }
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
            $this->load->model('taqdar_sessions_model');
            /* TQ-SOLD-NAME — الاسم من `sold()` لا من ضم على `plans` وحده.
               كان كل ما لا باقة له يقرأ «اشتراك»: من دفع عن ابنه ثمن
               كورس مفرد أو كتاب يقرأ في «المدفوعات» سطرين متطابقين
               بمبلغين مختلفين، ولا يعرف أيهما أيهما. */
            $this->load->model('taqdar_billing_model');
            foreach ($this->db->query(
                "SELECT i.id, i.invoice_no, i.total, i.status, i.method, i.transaction_id,
                        i.issued_at, i.paid_at, i.subscription_id
                   FROM invoices i
                  WHERE i.user_id = ?
                  ORDER BY i.id DESC LIMIT ?",
                [$user_id, (int) $limit]
            )->result_array() as $r) {
                $when = !empty($r['paid_at']) ? $r['paid_at'] : $r['issued_at'];
                $is_session = (int) $r['subscription_id'] <= 0;
                $sold = !$is_session
                      ? $this->taqdar_billing_model->sold((int) $r['subscription_id'])
                      : null;

                /* TQ-PAY-LABEL — الاسم والحال كما وقعا.
                   · **الفاتورة اليتيمة فاتورة حصة** (TQ-SESSION-PAY): كانت تقرأ
                     «اشتراك»، فيبحث ولي الأمر عن اشتراك لم يشتره.
                   · **«غير مدفوعة» ثلاث حالات لا واحدة**: كل ما لم يدفع كان يقرأ
                     «بانتظار التحويل» ولو اختير الدفع بالبطاقة ولم يكتمل.
                   · **«مستردة» لما دفع ثم رد**: فاتورة شطبت قبل أن تدفع — حصة
                     ألغيت، أو شراء بدل — ليست مالا رجع، فهي «ملغاة». */
                if ($is_session) {
                    $srow  = $this->taqdar_sessions_model->by_invoice((int) $r['id']);
                    $title = $srow ? t('حصة خاصة') . ' — ' . $this->taqdar_sessions_model->scope_of($srow)['label']
                                   : t('حصة خاصة');
                } else {
                    $title = $sold ? $sold['title'] : t('اشتراك');
                }
                if ($r['status'] === 'paid') {
                    $label = t('مدفوعة');
                } elseif ($r['status'] === 'unpaid') {
                    $label = (string) $r['method'] === 'tap' ? t('لم يكتمل الدفع بالبطاقة')
                           : (in_array((string) $r['method'], array('manual', 'bank', 'bank_transfer'), true)
                               ? t('بانتظار التحويل') : t('غير مدفوعة'));
                } else {
                    $label = empty($r['paid_at']) ? t('ملغاة') : t('مستردة');
                }

                $rows[] = [
                    'ts'          => $when ? strtotime($when) : 0,
                    'amount'      => ((int) $r['total']) / 100,
                    'title'       => $title,
                    'ref'         => $r['transaction_id'] ?: $r['invoice_no'],
                    'method'      => (string) $r['method'],
                    'status'      => $r['status'] === 'refunded' && empty($r['paid_at']) ? 'cancelled' : $r['status'],
                    'label'       => $label,
                    'source'      => 'invoice',
                    'invoice_id'  => (int) $r['id'],
                    'payable'     => $r['status'] === 'unpaid',
                    /* فاتورة الحصة تلغى بإلغاء حجزها لا من هنا. */
                    'cancellable' => $r['status'] === 'unpaid' && !$is_session,
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
    /**
     * TQ-PAY-TOTALS — المجاميع من القاعدة كلها لا من الصفوف المعروضة.
     * «الإجمالي منذ أول اشتراك» كان يجمع آخر خمسين عملية وحدها، فيقل عند
     * من تجاوزها عما دفعه فعلا.
     */
    public function payment_totals_all($user_id, $month_start = null)
    {
        $user_id = (int) $user_id;
        $ms = $month_start === null ? strtotime(date('Y-m-01 00:00:00')) : (int) $month_start;
        $out = ['month' => 0.0, 'all' => 0.0, 'pending' => 0.0, 'pending_count' => 0];
        if ($user_id <= 0) return $out;

        $r = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN total END), 0) paid_all,
                    COALESCE(SUM(CASE WHEN status = 'paid' AND COALESCE(paid_at, issued_at) >= FROM_UNIXTIME(?) THEN total END), 0) paid_month,
                    COALESCE(SUM(CASE WHEN status = 'unpaid' THEN total END), 0) pend,
                    SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) pend_n
               FROM invoices WHERE user_id = ?", [$ms, $user_id])->row_array();
        $a = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) s_all,
                    COALESCE(SUM(CASE WHEN date_added >= ? THEN amount END), 0) s_month
               FROM payment WHERE user_id = ?", [$ms, $user_id])->row_array();

        $out['all']           = ((int) $r['paid_all']) / 100 + (float) $a['s_all'];
        $out['month']         = ((int) $r['paid_month']) / 100 + (float) $a['s_month'];
        $out['pending']       = ((int) $r['pend']) / 100;
        $out['pending_count'] = (int) $r['pend_n'];
        return $out;
    }

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

        /* ═══ TQ-EMAIL-CHANGE — البريد اسم الدخول، فتغييره لا يمر بحفظ عادي ═══
           كان يتغير بلا كلمة مرور ولا تأكيد: حرف واحد خطأ يغلق على صاحبه
           الدخول واستعادة كلمة المرور معا (الرابط يذهب إلى بريد لا يملكه)،
           ومن وجد الجهاز مفتوحا يكتب بريده هو فيملك الحساب في دقيقة. فثلاثة
           شروط حين يتغير البريد وحده: كلمة المرور الحالية، والبريد الجديد
           مكتوبا مرتين، ورسالة إلى **البريد القديم** بأنه تغير. */
        $row = $this->db->select('email, password')->where('id', $parent_id)->get('users')->row_array();
        $old = $row ? strtolower(trim((string) $row['email'])) : '';
        if ($row && strtolower($email) !== $old) {
            $confirm = strtolower(trim((string) ($data['email_confirm'] ?? '')));
            if ($confirm !== strtolower($email)) {
                return $this->fail('اكتب البريد الجديد مرتين متطابقتين — هو ما ستدخل به، وخطأ حرف فيه يغلق عليك حسابك.');
            }
            $cur = (string) ($data['current_password'] ?? '');
            $ok  = $cur !== '' && (function_exists('tq_password_matches')
                ? tq_password_matches($cur, (string) $row['password'])
                : hash_equals((string) $row['password'], sha1($cur)));
            if (!$ok) {
                return $this->fail('لتغيير البريد اكتب كلمة مرورك الحالية — البريد اسم دخولك، فلا يتغير بلا إثبات أنك صاحب الحساب.');
            }
            $this->announce_by_mail($parent_id, 'تغير بريد حسابك في تقدر',
                'غير بريد الدخول إلى حسابك في تقدر إلى ' . $email . '. '
              . 'إن لم تكن أنت من غيره فتواصل مع الدعم فورا.');
        }

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
        /* ثمانية لا ستة — وهو حد التسجيل وإعدادات الطالب والمعلم. وكلمة
           تقبل هنا بستة وترد هناك بستة تجعل الحساب الواحد بقاعدتين. */
        if (mb_strlen($new) < 8) return $this->fail('اجعل كلمة المرور ثمانية أحرف فأكثر.');
        if ($new === $current) return $this->fail('اختر كلمة مرور جديدة غير الحالية.');

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
                    'first_name'       => $this->input->post('first_name'),
                    'last_name'        => $this->input->post('last_name'),
                    'email'            => $this->input->post('email'),
                    'email_confirm'    => $this->input->post('email_confirm'),
                    'current_password' => (string) $this->input->post('current_password'),
                    'phone'            => $this->input->post('phone'),
                    'phone_cc'         => $this->input->post('phone_cc'),
                    'address'          => $this->input->post('address'),
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
        /* TQ-FLASH-TWICE — مفاتيح هذا النموذج وحدها (`tq_ok`/`tq_error`).
           كانت تقرأ `flash_message`/`error_message` كذلك، و`portal_open.php`
           يطبعهما لكل شاشات البوابة — ومسارات المتحكم تكتب بهما. فكل حفظ
           من الإعدادات يظهر رسالته مرتين في صندوقين، ويظن ولي الأمر أنه
           حفظ مرتين أو أن أحد الحفظين فشل. */
        /* ومسارات المتحكم (`done()`) تكتب المفتاحين معا — فحين يحمل
           `flash_message`/`error_message` الرسالة نفسها يطبعها الغلاف مرة،
           ولا يعاد طبعها هنا. */
        if ($this->session->flashdata('flash_message') || $this->session->flashdata('error_message')) return '';
        $ok  = $this->session->flashdata('tq_ok');
        $err = $this->session->flashdata('tq_error');
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

        $nid = (int) $this->db->insert_id();

        $this->announce_by_mail($to_user, $title, $body);
        $this->announce_by_wa($to_user, $title, $body, $type);
        $this->announce_by_push($to_user, $title, $body, $type, $nid);
    }

    /**
     * TQ-PUSH — وإشعار التطبيق مع الصف، كأخويه البريد وواتساب. والباب
     * `push_user()` نفسه الذي يمر به `push_notification()`، لا نسخة ثانية.
     */
    private function announce_by_push($to_user, $title, $body, $type, $nid = 0)
    {
        try {
            $this->load->model('taqdar_admin_model');
            $this->taqdar_admin_model->push_user(
                (int) $to_user, (string) $title, strip_tags((string) $body), (string) $type, (int) $nid);
        } catch (Throwable $e) {
            log_message('error', 'parent_links push: ' . $e->getMessage());
        }
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

    /**
     * وواتساب معه (TQ-WA-ALL) — للحجة نفسها المكتوبة أعلاه، وهي هنا
     * أقوى: ولي الأمر لا يفتح المنصة يوميا، وقد لا يفتح بريده كذلك.
     *
     * والحارس ليس هنا: `Taqdar_admin_model::notify_wa()` هي الباب، وفيه
     * سياسة العائلة وتفضيل صاحب الحساب وساعات صمته.
     */
    private function announce_by_wa($to_user, $title, $body, $type)
    {
        try {
            $this->load->model('taqdar_admin_model');
            $this->taqdar_admin_model->notify_wa(
                (int) $to_user, (string) $title, strip_tags((string) $body), (string) $type);
        } catch (Throwable $e) {
            log_message('error', 'parent_links wa: ' . $e->getMessage());
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
            $this->announce_by_push($sid, 'طلب ربط ولي أمر',
                $parent . ' يطلب ربط حسابك بحسابه. لا يفتح شيء من بياناتك قبل موافقتك.',
                'parent_link_request', (int) $this->db->insert_id());
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

    /* =====================================================================
       بوابة ولي الأمر — طبقة القراءة
       ---------------------------------------------------------------------
       ثلاث شاشات كانت تكتب استعلاماتها **داخل ملف العرض**:
       `tq_parent_child.php` و`tq_parent_weekly.php` و`tq_parent_reports.php`.
       فلما جاءت واجهة التطبيق تسأل الأسئلة نفسها لم تجد ما تناديه إلا
       قالبا يطبع HTML. والقواعد هنا، والقالب والواجهة يعرضان.

       **وحاجز الرؤية مطبق في طبقة الاستعلام لا في إخفاء عنصر**: لا استعلام
       واحد هنا على محادثات المساعد الذكي، ولا على المنشورات، ولا على
       `quiz_results.user_answers` — «الرقابة الكاملة تنتج طالبا يخفي، لا
       طالبا يتعلم». وما ينقل من القالب ينقل بحاجزه.
       ===================================================================== */

    /**
     * الأسبوع يبدأ الأحد (السوق سعودي)، والمقارنة على **مدى واحد**:
     * ما مضى من هذا الأسبوع مقابل الأيام نفسها من الأسبوع الماضي.
     *
     * وكانت المقارنة بالأسبوع الماضي كاملا: فصباح الأحد — وهو موعد إرسال
     * التقرير نفسه — يقرأ كل ولي أمر أن نشاط ابنه «نزل»، لأن أسبوعا لم
     * يبدأ بعد يقارن بأسبوع تم. رسالة تصل أسبوعيا وتقول لكل أب إن ابنه
     * تراجع لا تقرأ مرتين.
     *
     * @return array{start:int, prev_start:int, elapsed:int, days_left:int}
     */
    public function week_window($now = null)
    {
        $now = $now ? (int) $now : time();
        $dow = (int) date('w', $now);

        return array(
            'start'      => strtotime('today', $now) - $dow * 86400,
            'prev_start' => strtotime('today', $now) - ($dow + 7) * 86400,
            'elapsed'    => $dow + 1,   // ما مضى بما فيه اليوم
            'days_left'  => 6 - $dow,
        );
    }

    /**
     * أيام نشاط الابن — من ثلاثة مصادر، و`lesson_progress` أصدقها.
     *
     * فيه صف **لكل درس** بتاريخ إنهائه؛ أما `watch_histories` فصف واحد لكل
     * مادة بآخر تحديث لها وحده — فمن واظب خمسة أيام على مادة واحدة كان
     * يحسب له يوم، ومن سجل في خمس مواد ولمسها مرة تحسب له خمسة. المقياس
     * كان يكافئ تعدد المواد لا المواظبة.
     *
     * @return array مفاتيحها طوابع بداية اليوم
     */
    private function activity_days($student_id)
    {
        $days = array();
        foreach ($this->activity_stamps($student_id) as $ts) {
            if ($ts > 0) $days[strtotime('today', $ts)] = true;
        }
        return $days;
    }

    /**
     * TQ-ACTIVITY-ALL — كل ما يعد نشاطا دراسيا، لا مشاهدة الفيديو وحدها.
     *
     * كانت أيام النشاط و«آخر نشاط» و«غاب كذا يوما» تعد إتمام درس وتحديث
     * `watch_histories` واختبارا موروثا — فابن يفتح المنصة كل يوم ليراجع
     * ويحل اختباراته بلا أن يشغل فيديو جديدا يقرأ عنه أهله «غاب ٣٠ يوما»،
     * وطالب شاهد نصف درس أمس لم يحسب له يوم. والمنصة تسجل ذلك كله:
     *   · `tq_activity_day` — يوم لكل ما يعمله (مراجعات · دروس · وقت)
     *   · `lesson_progress.last_ping_at` — آخر نبضة مشاهدة ولو لم يكمل
     *   · `attempts.submitted_at` — كل اختبار سلمه في النظام الحي
     * والمصادر الثلاثة القديمة باقية، فلا يسقط تاريخ سجل قبلها.
     *
     * @return int[] طوابع يونكس
     */
    private function activity_stamps($student_id)
    {
        $student_id = (int) $student_id;
        $stamps = array();
        $read = function ($sql) use ($student_id, &$stamps) {
            try {
                foreach ($this->db->query($sql, array($student_id))->result_array() as $r) {
                    $stamps[] = (int) $r['ts'];
                }
            } catch (Throwable $e) {
                $this->db->reset_query();   // TQ-BUILDER-DIRTY
            }
        };

        $read('SELECT UNIX_TIMESTAMP(`completed_at`) ts FROM `lesson_progress`
                WHERE `student_id` = ? AND `completed_at` IS NOT NULL');
        $read('SELECT UNIX_TIMESTAMP(`last_ping_at`) ts FROM `lesson_progress`
                WHERE `student_id` = ? AND `last_ping_at` IS NOT NULL');
        $read('SELECT `date_updated` ts FROM `watch_histories` WHERE `student_id` = ?');
        $read('SELECT `date_added` ts FROM `quiz_results` WHERE `user_id` = ? AND `is_submitted` = 1');
        $read('SELECT UNIX_TIMESTAMP(`submitted_at`) ts FROM `attempts`
                WHERE `student_id` = ? AND `submitted_at` IS NOT NULL');
        if ($this->db->table_exists('tq_activity_day')) {
            $read('SELECT UNIX_TIMESTAMP(COALESCE(`last_at`, `day`)) ts FROM `tq_activity_day` WHERE `student_id` = ?');
        }
        return $stamps;
    }

    /** آخر نشاط دراسي — من المصادر نفسها. صفر = لم يبدأ. */
    public function last_active_at($student_id)
    {
        $s = $this->activity_stamps($student_id);
        return $s ? max(0, max($s)) : 0;
    }

    /**
     * TQ-PROGRESS-ONE — مواد الابن صفا صفا: ما أنهاه، ومتى لمسها، وإتقانها.
     *
     * كانت ثلاث شاشات لولي الأمر تقرأ `watch_histories.course_progress` —
     * رقما مخزنا انحرف عن الدروس: «الرياضيات ١٠٠٪» وابنه أنهى ٢٠ من ٢١،
     * و«٧٢٪» في مادة لم يكمل فيها درسا. والنسبة هنا `course_state()` نفسها
     * التي يراها ابنه في «كورساتي» ويفتح بها القفل درسه التالي. و«آخر لمس»
     * من نبضات المشاهدة لا من صف واحد يحدث مرة لكل مادة.
     */
    public function course_rows($student_id)
    {
        $sid = (int) $student_id;
        if ($sid <= 0) return array();

        $courses = $this->db->query(
            "SELECT c.`id`, c.`title`, COALESCE(w.`date_updated`, 0) wh_seen
               FROM `enrol` e
               JOIN `course` c ON c.`id` = e.`course_id`
          LEFT JOIN `watch_histories` w ON w.`student_id` = e.`user_id` AND w.`course_id` = e.`course_id`
              WHERE e.`user_id` = ?
              ORDER BY c.`title` ASC", array($sid))->result_array();
        if (!$courses) return array();

        $seen = array();
        try {
            foreach ($this->db->query(
                'SELECT l.`course_id`,
                        MAX(UNIX_TIMESTAMP(GREATEST(COALESCE(lp.`last_ping_at`, "1970-01-02"),
                                                     COALESCE(lp.`completed_at`, "1970-01-02")))) t
                   FROM `lesson_progress` lp JOIN `lesson` l ON l.`id` = lp.`lesson_id`
                  WHERE lp.`student_id` = ? GROUP BY l.`course_id`', array($sid))->result_array() as $r) {
                $seen[(int) $r['course_id']] = (int) $r['t'];
            }
        } catch (Throwable $e) { $this->db->reset_query(); }

        $mastery = array();
        try {
            foreach ($this->db->query(
                "SELECT l.`course_id`, COUNT(*) open_n,
                        SUM(CASE WHEN ss.`level` >= 80 THEN 1 ELSE 0 END) mastered_n
                   FROM `objectives` o
                   JOIN `lesson` l ON l.`id` = o.`lesson_id`
              LEFT JOIN `skill_state` ss ON ss.`objective_id` = o.`id` AND ss.`student_id` = ?
                  WHERE EXISTS (SELECT 1 FROM `lesson_progress` lp WHERE lp.`student_id` = ? AND lp.`lesson_id` = l.`id`)
                  GROUP BY l.`course_id`", array($sid, $sid))->result_array() as $r) {
                $mastery[(int) $r['course_id']] = $r;
            }
        } catch (Throwable $e) { $this->db->reset_query(); }

        $this->load->model('taqdar_repo_model');
        $out = array();
        foreach ($courses as $c) {
            $cid = (int) $c['id'];
            $st  = $this->taqdar_repo_model->course_state($sid, $cid);
            $mo  = (int) ($mastery[$cid]['open_n'] ?? 0);
            $mm  = (int) ($mastery[$cid]['mastered_n'] ?? 0);
            $out[] = array(
                'id'        => $cid,
                'title'     => (string) $c['title'],
                'progress'  => (int) $st['percent'],
                'done_n'    => (int) $st['done'],
                'lessons_n' => (int) $st['total'],
                'lessons'   => (int) $st['total'],
                'last_seen' => max((int) ($seen[$cid] ?? 0), (int) $c['wh_seen']),
                'mastery'   => array('open' => $mo, 'mastered' => $mm,
                                     'percent' => $mo > 0 ? (int) round(100 * $mm / $mo) : null),
            );
        }
        return $out;
    }

    /**
     * بطاقات «أبنائي» — كانت تحسب في القالب باستعلام على `watch_histories`
     * (TQ-PROGRESS-ONE، TQ-ACTIVITY-ALL): التطبيق يسأل النموذج فيجد رقما،
     * والصفحة تحسب رقما آخر.
     */
    public function child_cards($parent_id)
    {
        $out = array();
        foreach ($this->children($parent_id) as $c) {
            $sid  = (int) $c['student_id'];
            $rows = $this->course_rows($sid);
            $done = 0; $total = 0;
            foreach ($rows as $r) { $done += $r['done_n']; $total += $r['lessons_n']; }
            $last = $this->last_active_at($sid);

            $c['courses']   = count($rows);
            $c['progress']  = $total > 0 ? (int) round($done * 100 / $total) : 0;
            $c['done']      = $done;
            $c['lessons']   = $total;
            $c['last_seen'] = $last;
            $c['days']      = $last > 0 ? max(0, (int) floor((time() - $last) / 86400)) : null;
            $out[] = $c;
        }
        return $out;
    }

    /**
     * تفاصيل الابن — المقياس الثلاثي المبسط: الالتزام · الفهم · الاتجاه.
     *
     * والملكية تفحص أولا عبر `child()`، مصدر الحقيقة الواحد — ومن طلب ابنا
     * ليس ابنه يرد `null` ولا فرق عنده بين «غير موجود» و«ليس لك».
     */
    /**
     * المقاييس الثلاثة لابن واحد: الالتزام والفهم والاتجاه.
     *
     * خرجت من `child_detail()` لأن **شاشتين تسألانها**: تفاصيل الابن،
     * ولوحة ولي الأمر. ومعادلة الالتزام مكتوبة مرتين تعني رقمين عن الابن
     * الواحد في شاشتين متجاورتين، وهي علة TQ-SOLD-NAME نفسها.
     *
     * ولا تفحص الملكية: من ناداها فحصها قبلها (`child()` أو `links()`).
     */
    public function measures($parent_id, $student_id)
    {
        $parent_id  = (int) $parent_id;
        $student_id = (int) $student_id;

        $w    = $this->week_window();
        $plan = $this->plan_days($parent_id, $student_id);

        $days_this = 0; $days_prev = 0;
        $flags     = array_fill(0, 7, false);

        foreach (array_keys($this->activity_days($student_id)) as $day) {
            if ($day >= $w['start']) {
                $days_this++;
                $i = (int) floor(($day - $w['start']) / 86400);
                if ($i >= 0 && $i < 7) $flags[$i] = true;
            } elseif ($day >= $w['prev_start']
                   && $day < $w['prev_start'] + $w['elapsed'] * 86400) {
                $days_prev++;
            }
        }

        /* الفهم: هدف متقن من هدف فتح له. والمقياس مئوي لا كسري —
           `touch_skill_state()` يكتب `($ok/$total)*100` ويقص على [0,100]،
           فعتبة `0.80` هنا تعد كل شيء متقنا وعتبة `80` على كسور تعد كل
           شيء غير متقن. */
        $sk = $this->db->query(
            "SELECT COUNT(*) open_n,
                    SUM(CASE WHEN ss.`level` >= 80 THEN 1 ELSE 0 END) mastered_n
               FROM `objectives` o
               JOIN `lesson` l ON l.`id` = o.`lesson_id`
               JOIN `enrol`  e ON e.`course_id` = l.`course_id` AND e.`user_id` = ?
          LEFT JOIN `skill_state` ss ON ss.`objective_id` = o.`id` AND ss.`student_id` = ?
              WHERE EXISTS (SELECT 1 FROM `lesson_progress` lp
                             WHERE lp.`student_id` = ? AND lp.`lesson_id` = l.`id`)",
            array($student_id, $student_id, $student_id)
        )->row_array();

        $open     = (int) ($sk['open_n'] ?? 0);
        $mastered = (int) ($sk['mastered_n'] ?? 0);

        return [
            'week'            => $w,
            'plan_days'       => (int) $plan['days'],
            'plan_is_default' => !empty($plan['is_default']),
            'days_this'       => $days_this,
            'days_prev'       => $days_prev,
            'day_flags'       => $flags,
            'commitment'      => (int) round(100 * min($days_this, (int) $plan['days'])
                                             / max(1, (int) $plan['days'])),
            'skill'           => [
                'open'     => $open,
                'mastered' => $mastered,
                'percent'  => $open > 0 ? (int) round(100 * $mastered / $open) : 0,
            ],
            'trend'           => $days_this > $days_prev ? 'up'
                               : ($days_this < $days_prev ? 'down' : 'flat'),
        ];
    }

    /**
     * فواتير أبنائه التي تنتظر السداد.
     *
     * وهي سؤال ولي الأمر لا سؤال الطالب: الفاتورة تصدر **باسم الابن**
     * دائما (هو صاحب الاشتراك)، فقائمة تقرأ `invoices.user_id = الأب`
     * ترد فارغة أبدا. والضم على `parent_links` برابط نشط هو الحارس نفسه
     * الذي تقرأ به بقية بوابة ولي الأمر.
     *
     * وموضعها هنا لا في المتحكم: تقرؤها لوحة ولي الأمر وشاشة الشراء معا.
     *
     * **والمستحقة `unpaid` وحدها لا «كل ما ليس مدفوعا»**: المستردة ليست
     * مستحقة — يردها `pay_invoice_now()` بـ`invoice_not_payable`، فعرضها
     * تحت «فواتير تنتظر السداد» يضع زرا يعد بباب ثم يرد عليه. وموضعها
     * سجل المدفوعات لا شاشة الشراء.
     */
    public function due_invoices($parent_id, $limit = 20)
    {
        $parent_id = (int) $parent_id;
        if ($parent_id <= 0) return [];

        try {
            return $this->db->query(
                'SELECT i.`id`, i.`invoice_no`, i.`total`, i.`status`, i.`user_id`, i.`issued_at`,
                        i.`subscription_id`, i.`method`,
                        TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) AS holder
                   FROM `invoices` i
                   JOIN `parent_links` pl ON pl.`student_id` = i.`user_id`
                                         AND pl.`parent_user_id` = ? AND pl.`status` = "active"
              LEFT JOIN `users` u ON u.`id` = i.`user_id`
                  WHERE i.`status` = "unpaid"
               ORDER BY i.`id` DESC LIMIT ' . max(1, (int) $limit), [$parent_id])->result_array();
        } catch (Throwable $e) {
            /* TQ-BUILDER-DIRTY — واستثناء وسط سلسلة يترك حالتها خلفه. */
            $this->db->reset_query();
            return [];
        }
    }

    /**
     * لوحة ولي الأمر — «ما حال أبنائي اليوم؟» في نداء واحد.
     *
     * وكان الجواب ثلاثة نداءات لا واحدا: `children` ثم `children/{id}`
     * لكل ابن ثم `pay` للفواتير. والأثقل منه أن **شارات القائمة** كانت
     * تقرأ من نداءين (`messages` و`notifications`) عند كل فتح، بينما
     * يكفي الآخرين نداء واحد: `/student/home` و`/teacher/home` تحملانها.
     */
    public function home($parent_id)
    {
        $parent_id = (int) $parent_id;

        $kids = [];
        foreach ($this->links($parent_id) as $l) {
            $sid = (int) $l['student_id'];

            $row = [
                'student_id'    => $sid,
                'name'          => (string) $l['name'],
                'image'         => (string) $l['image'],
                /* البريد كتبه ولي الأمر بيده فلا يكشف شيئا، وبه يعرف من لم
                   يوافق بعد (`named` كاذبة). */
                'email'         => (string) $l['email'],
                'named'         => (bool) $l['named'],
                'link_status'   => (string) $l['status'],
                'commitment'    => null,
                'understanding' => null,
                'trend'         => null,
            ];

            /* والمعلق لا يقاس: رابط لم يوافق عليه ابنه لا يفتح بياناته،
               وصفر في خانة الالتزام يقرأ «ابنك لا يذاكر» عن ابن لم يربط
               بعد. فتبقى `null` ويقرأ التطبيق حالة الرابط. */
            if ((string) $l['status'] === 'active') {
                $m = $this->measures($parent_id, $sid);
                $row['commitment']    = (int) $m['commitment'];
                $row['understanding'] = (int) $m['skill']['percent'];
                $row['trend']         = (string) $m['trend'];
            }

            $kids[] = $row;
        }

        return [
            'children'     => $kids,
            'due_invoices' => $this->due_invoices($parent_id, 20),
        ];
    }

    public function child_detail($parent_id, $student_id)
    {
        $parent_id  = (int) $parent_id;
        $student_id = (int) $student_id;

        $child = $this->child($parent_id, $student_id);
        if (!$child) return null;

        /* المقاييس الثلاثة من `measures()` وحدها — تقرؤها هذه الشاشة
           وتقرؤها لوحة ولي الأمر (`home()`). ونسختان من معادلة الالتزام
           تجعلان الشاشتين تقولان رقمين عن الابن الواحد. */
        $m = $this->measures($parent_id, $student_id);

        $w         = $m['week'];
        $plan      = ['days' => $m['plan_days'], 'is_default' => $m['plan_is_default']];
        $days_this = $m['days_this'];
        $days_prev = $m['days_prev'];
        $flags     = $m['day_flags'];

        /* المواد: النسبة وحدها لا تقول أيهما — «٤٤٪» في مادة من عشرين درسا
           غير «٤٤٪» في مادة من ثلاثة. فيقرأ عدد دروس كل مادة معها.
           (والاختبارات مستثناة من العد كما تستثنى في بوابة الطالب، فلا
           يختلف رقم بين شاشتين.) */
        /* TQ-PROGRESS-ONE — من `course_rows()` لا من `watch_histories`. */
        $subjects  = $this->course_rows($student_id);
        $completed = 0;
        foreach ($subjects as $s) $completed += (int) $s['done_n'];

        /* الحصص القادمة — المطلوبة والمؤكدة وحدهما: المعتذر عنها والمنتهية
           ليست «قادمة»، وعرضها يجعل ولي الأمر يترقب موعدا لن يقع. */
        $sessions = array();
        if ($this->db->table_exists('tutoring_sessions')) {
            $sessions = $this->db->query(
                "SELECT ts.`id`, ts.`status`, ts.`meet_url`, s.`starts_at`, s.`duration_min`,
                        s.`grade_id`, g.`name_ar` grade_name, sj.`name_ar` subject_name,
                        TRIM(CONCAT(COALESCE(u.`first_name`,''), ' ',
                                    COALESCE(u.`last_name`,''))) teacher
                   FROM `tutoring_sessions` ts
              LEFT JOIN `availability_slots` s ON s.`id` = ts.`slot_id`
              LEFT JOIN `grades`   g  ON g.`id`  = s.`grade_id`
              LEFT JOIN `subjects` sj ON sj.`id` = s.`subject_id`
              LEFT JOIN `users`    u  ON u.`id`  = ts.`teacher_id`
                  WHERE ts.`student_id` = ?
                    AND ts.`status` IN ('requested','awaiting_payment','confirmed','live')
                    AND (s.`starts_at` IS NULL OR s.`starts_at` >= NOW() - INTERVAL 2 HOUR)
                  ORDER BY s.`starts_at` ASC
                  LIMIT 5",
                array($student_id)
            )->result_array();
        }

        return array(
            'child'      => $child,
            'week'       => $w,
            'plan_days'  => (int) $plan['days'],
            'plan_is_default' => !empty($plan['is_default']),
            'days_this'  => $days_this,
            'days_prev'  => $days_prev,
            'day_flags'  => $flags,
            'commitment' => (int) $m['commitment'],
            'subjects'   => $subjects,
            'completed'  => $completed,
            'skill'      => $m['skill'],
            'sessions'   => $sessions,
            'notes'      => $this->teacher_notes($student_id, 5),
            'payments'   => array_slice($this->payments_of($student_id, 10), 0, 10),
        );
    }

    /**
     * ملاحظات المعلمين — **المعتمدة وحدها**، ومن مصدرين لا واحد.
     *
     * الدرجة قبل اعتمادها لا يراها الطالب، ورؤية وليه لها تسبقه بخبر عن
     * نفسه — وهو أسوأ ما يقع بين مراهق وأهله. وملاحظة الاختبار في
     * `quiz_results` وملاحظة الواجب في `attempts`: كانت الأولى وحدها تقرأ
     * لأن الواجبات لم تكن تصل معلما أصلا.
     */
    public function teacher_notes($student_id, $limit = 5)
    {
        $student_id = (int) $student_id;
        $limit      = max(1, (int) $limit);

        $notes = $this->db->query(
            "SELECT r.`quiz_result_id` id, r.`teacher_note`, r.`approved_at`,
                    l.`title` lesson_title, c.`title` course_title, 'quiz' kind,
                    TRIM(CONCAT(COALESCE(u.`first_name`,''), ' ',
                                COALESCE(u.`last_name`,''))) teacher
               FROM `quiz_results` r
               JOIN `lesson` l ON l.`id` = r.`quiz_id`
          LEFT JOIN `course` c ON c.`id` = l.`course_id`
          LEFT JOIN `users`  u ON u.`id` = r.`approved_by`
              WHERE r.`user_id` = ? AND r.`approved_at` IS NOT NULL
                AND r.`teacher_note` IS NOT NULL AND TRIM(r.`teacher_note`) <> ''
              ORDER BY r.`approved_at` DESC
              LIMIT $limit",
            array($student_id)
        )->result_array();

        /* أعمدة اعتماد الواجب تنشأ وقت التشغيل، فقد لا تكون على هذه
           البيئة بعد — و`ensure_schema()` قبل القراءة لا بعد الخطأ. */
        $CI = get_instance();
        $CI->load->model('taqdar_marking_model');
        $CI->taqdar_marking_model->ensure_schema();

        foreach ($this->db->query(
            "SELECT t.`id`, t.`teacher_note`, t.`approved_at`,
                    l.`title` lesson_title, c.`title` course_title, 'homework' kind,
                    TRIM(CONCAT(COALESCE(u.`first_name`,''), ' ',
                                COALESCE(u.`last_name`,''))) teacher
               FROM `attempts` t
               JOIN `assessments` a ON a.`id` = t.`assessment_id`
               JOIN `lesson` l ON l.`id` = a.`lesson_id`
          LEFT JOIN `course` c ON c.`id` = l.`course_id`
          LEFT JOIN `users`  u ON u.`id` = t.`approved_by`
              WHERE t.`student_id` = ? AND t.`approved_at` IS NOT NULL
                AND t.`teacher_note` IS NOT NULL AND TRIM(t.`teacher_note`) <> ''
              ORDER BY t.`approved_at` DESC
              LIMIT $limit",
            array($student_id)
        )->result_array() as $hn) {
            $notes[] = $hn;
        }

        usort($notes, function ($a, $b) {
            return (int) $b['approved_at'] <=> (int) $a['approved_at'];
        });

        return array_slice($notes, 0, $limit);
    }

    /**
     * التقرير الأسبوعي — أربعة أرقام لكل ابن تقرأ في عشر ثوان.
     *
     * ودروس **هذا الأسبوع** من `lesson_progress.completed_at` لا من مجموع
     * `watch_histories.completed_lesson`: كان الأخير يجمع العمر كله ثم
     * يكتب في السطر «هذا الأسبوع»، فيقرأ ولي أمر ابنه لم يفتح المنصة منذ
     * شهر «أكمل ٣٥ درسا هذا الأسبوع» فيطمئن — وهو أخطر ما يفعله تقرير.
     * والحصيلة الكلية تعرض إلى جانبه لا بدلا منه.
     */
    public function weekly($parent_id, $student_id = 0)
    {
        $parent_id = (int) $parent_id;
        $w         = $this->week_window();

        $kids = array();
        foreach ($this->children($parent_id) as $c) {
            $cid = (int) $c['student_id'];
            if ($student_id && $cid !== (int) $student_id) continue;

            $plan = $this->plan_days($parent_id, $cid);

            $days_this = 0; $days_prev = 0;
            foreach (array_keys($this->activity_days($cid)) as $day) {
                if ($day >= $w['start']) $days_this++;
                elseif ($day >= $w['prev_start']
                     && $day < $w['prev_start'] + $w['elapsed'] * 86400) $days_prev++;
            }

            $done = (int) $this->db->query(
                'SELECT COUNT(*) n FROM `lesson_progress`
                  WHERE `student_id` = ? AND `completed_at` IS NOT NULL
                    AND `completed_at` >= FROM_UNIXTIME(?)',
                array($cid, $w['start'])
            )->row('n');

            $done_all = (int) $this->db->query(
                'SELECT COUNT(*) n FROM `lesson_progress`
                  WHERE `student_id` = ? AND `completed_at` IS NOT NULL',
                array($cid)
            )->row('n');

            /* TQ-QUIZ-WEEKLY — الاختبارات من النظامين: كانت الصفحة تعد
               `quiz_results` الموروث وحده والبريد يعد الاثنين، فيقرأ ولي
               الأمر «لم يسلم اختبارا» في الصفحة و«أنهى خمسة» في البريد. */
            $quizzes = (int) $this->db->query(
                'SELECT COUNT(*) n FROM `quiz_results`
                  WHERE `user_id` = ? AND `is_submitted` = 1 AND CAST(`date_added` AS UNSIGNED) >= ?',
                array($cid, $w['start'])
            )->row('n');
            $quizzes += (int) $this->db->query(
                'SELECT COUNT(*) n FROM `attempts`
                  WHERE `student_id` = ? AND `submitted_at` IS NOT NULL AND `submitted_at` >= FROM_UNIXTIME(?)',
                array($cid, $w['start'])
            )->row('n');

            /* المادة المتوقفة: أطول غياب بين مواده **التي بدأها** — من آخر
               لمس لها بأي نشاط (TQ-ACTIVITY-ALL)، لا من صف مشاهدة واحد.
               وغير المبدوءة تذكر حين لا متوقف. */
            $stalled = null;
            foreach ($this->course_rows($cid) as $r) {
                $row = array('title' => $r['title'], 'last_seen' => (int) $r['last_seen']);
                if ($stalled === null) { $stalled = $row; continue; }
                $a = (int) $stalled['last_seen']; $b = (int) $row['last_seen'];
                if (($a === 0 && $b > 0) || ($a > 0 && $b > 0 && $b < $a)) $stalled = $row;
            }

            $kids[] = array(
                'student_id'      => $cid,
                'name'            => trim($c['first_name'] . ' ' . $c['last_name']),
                'image'           => (string) $c['image'],
                'plan_days'       => (int) $plan['days'],
                'plan_is_default' => !empty($plan['is_default']),
                'days_this'       => $days_this,
                'days_prev'       => $days_prev,
                'lessons_done'    => $done,
                'lessons_total'   => $done_all,
                'quizzes'         => $quizzes,
                'needed'          => max(0, (int) $plan['days'] - $days_this),
                'trend'           => $days_this > $days_prev ? 'up'
                                   : ($days_this < $days_prev ? 'down' : 'flat'),
                'stalled'         => $stalled ? array(
                    'title'     => (string) $stalled['title'],
                    'last_seen' => (int) $stalled['last_seen'],
                    'days'      => (int) $stalled['last_seen'] > 0
                                 ? (int) floor((time() - (int) $stalled['last_seen']) / 86400)
                                 : null,
                ) : null,
            );
        }

        return array('week' => $w, 'children' => $kids);
    }

    /**
     * التقارير — كل مادة في سطر واحد، لكل ابن.
     *
     * **والدرجة المعروضة هي التي يراها ابنك نفسه** لا الدرجة الخام:
     * `Taqdar_marking_model::student_view()` هي الحكم الواحد. وكان الحساب
     * يجمع `total_obtained_marks` الخام، فيرى ولي الأمر رقما ولا يراه ابنه
     * — وأسرع طريق إلى شجار بينهما أن تعطيهما المنصة رقمين.
     */
    public function reports($parent_id, $student_id = 0)
    {
        $parent_id = (int) $parent_id;
        $CI = get_instance();
        $CI->load->model('taqdar_marking_model');
        $mk = $CI->taqdar_marking_model;

        $out = array();
        foreach ($this->children($parent_id) as $c) {
            $cid = (int) $c['student_id'];
            if ($student_id && $cid !== (int) $student_id) continue;

            /* TQ-PROGRESS-ONE — «ما أنهاه» و«آخر نشاط» والإتقان من `course_rows()`. */
            $subjects = $this->course_rows($cid);

            /* صفا صفا لا بمتوسط في SQL: الحكم على كل محاولة يمر بدالة
               واحدة فلا تكتب قاعدة الحجب مرتين وتتباعد. */
            $scores = array();
            foreach ($this->db->query(
                "SELECT r.`quiz_result_id`, r.`quiz_id`, r.`total_obtained_marks`,
                        r.`is_submitted`, r.`teacher_score`, r.`teacher_note`,
                        r.`approved_at`, l.`course_id`,
                        (SELECT COUNT(*) FROM `question` q WHERE q.`quiz_id` = r.`quiz_id`) q_count
                   FROM `quiz_results` r
                   JOIN `lesson` l ON l.`id` = r.`quiz_id`
                  WHERE r.`user_id` = ? AND r.`is_submitted` = 1",
                array($cid)
            )->result_array() as $r) {
                $k = (int) $r['course_id'];
                if (!isset($scores[$k])) $scores[$k] = array('sum' => 0.0, 'n' => 0, 'held' => 0);

                $view = $mk->student_view($r);
                if (empty($view['visible'])) { $scores[$k]['held']++; continue; }

                $qn = (int) $r['q_count'];
                if ($qn < 1) continue;   // اختبار بلا أسئلة لا نسبة له

                $scores[$k]['sum'] += 100 * (float) $view['score'] / $qn;
                $scores[$k]['n']++;
            }

            /* ═══ TQ-REPORT-RESULTS — والنظام الحي، وامتحانات المحطات، والواجبات ═══
               كان العمود يقرأ `quiz_results` وحده، وكل اختبار يؤلف اليوم يكتب في
               `attempts`: فابن حل ثمانية اختبارات دروس وأربعة امتحانات محطات
               يقرأ عنه أهله «لم يبدأ اختبارا» في كل مادة. والآن آخر محاولة مسلمة
               لكل تقييم — اختبار درس (`review`/`quiz`)، وامتحان محطة (`exam`)،
               وواجب **معتمد** (`homework`) بحكم `homework_student_view()` نفسه. */
            $last = array();
            try {
                foreach ($this->db->query(
                    "SELECT t.`id`, t.`assessment_id`, t.`score`, t.`teacher_score`, t.`teacher_note`,
                            t.`approved_at`, t.`submitted_at`, a.`type`,
                            COALESCE(l.`course_id`, p.`course_id`, 0) course_id,
                            (SELECT COUNT(*) FROM `answers` an WHERE an.`attempt_id` = t.`id`) out_of
                       FROM `attempts` t
                       JOIN `assessments` a ON a.`id` = t.`assessment_id`
                                           AND a.`type` IN ('review','quiz','exam','homework')
                  LEFT JOIN `lesson` l ON l.`id` = a.`lesson_id`
                  LEFT JOIN `milestones` m ON m.`id` = a.`milestone_id`
                  LEFT JOIN `paths` p ON p.`id` = COALESCE(a.`path_id`, m.`path_id`)
                      WHERE t.`student_id` = ? AND t.`submitted_at` IS NOT NULL
                      ORDER BY t.`id` ASC", array($cid))->result_array() as $r) {
                    $last[(int) $r['assessment_id']] = $r;   // الأحدث يغلب
                }
            } catch (Throwable $e) {
                $this->db->reset_query();
                log_message('error', 'TQ-REPORT-RESULTS: ' . $e->getMessage());
            }
            foreach ($last as $r) {
                $k = (int) $r['course_id'];
                if (!isset($scores[$k])) $scores[$k] = array('sum' => 0.0, 'n' => 0, 'held' => 0);

                if ((string) $r['type'] === 'homework') {
                    $view = $mk->homework_student_view($r);
                    if (empty($view['visible']) || $view['score'] === null) { $scores[$k]['held']++; continue; }
                    $scores[$k]['sum'] += min(100, (float) $view['score']);   // الواجب نسبة مئوية
                    $scores[$k]['n']++;
                    continue;
                }
                $out_of = (int) $r['out_of'];
                if ($out_of < 1) continue;
                $scores[$k]['sum'] += 100 * (float) $r['score'] / $out_of;
                $scores[$k]['n']++;
            }

            foreach ($subjects as $i => $s) {
                $a = isset($scores[(int) $s['id']])
                   ? $scores[(int) $s['id']]
                   : array('sum' => 0.0, 'n' => 0, 'held' => 0);

                $subjects[$i]['attempts']    = (int) $a['n'];
                $subjects[$i]['held']        = (int) $a['held'];
                $subjects[$i]['avg_percent'] = $a['n'] > 0
                    ? (int) round(min(100, $a['sum'] / $a['n'])) : null;
            }

            $out[] = array(
                'student_id' => $cid,
                'name'       => trim($c['first_name'] . ' ' . $c['last_name']),
                'image'      => (string) $c['image'],
                'subjects'   => $subjects,
            );
        }

        return $out;
    }
}
