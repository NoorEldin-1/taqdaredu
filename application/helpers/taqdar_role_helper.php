<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * اشتقاق الدور — مصدر حقيقة واحد.
 *
 * السكربت لا يحمل إلا دورين (`role_id` 1 أدمن و2 مستخدم) وراية `is_instructor`
 * متعامدة عليهما، ولا دور لوليّ الأمر أصلًا. فالدور يُشتقّ هنا في موضع واحد،
 * ويستعمله كل متحكّم — وإلّا تباعدت القراءتان كما تباعدتا قبل هذا الملفّ:
 * `Taqdar.php` كان يفحص `is_instructor` وحده و`Taqdar_gate.php` يفحص `role_id`
 * أيضًا، فمرّ الأدمن من باب ومُنع من آخر.
 *
 * الترتيب مقصود: الأدمن أوّلًا لأنه قد يكون `is_instructor=1` كذلك.
 */

if (!function_exists('tq_role')) {
    /** @return string admin|teacher|parent|student|guest */
    function tq_role($user_id = null)
    {
        $CI = get_instance();
        static $cache = [];

        if ($user_id === null) {
            $user_id = $CI->session->userdata('user_id');
        }
        $user_id = (int) $user_id;
        if (!$user_id) return 'guest';

        if (isset($cache[$user_id])) return $cache[$user_id];

        $row = $CI->db->select('id, role_id, is_instructor, status, tq_gate')
                      ->where('id', $user_id)->get('users')->row_array();

        if (!$row || (int) $row['status'] !== 1) {
            return $cache[$user_id] = 'guest';
        }

        if ((int) $row['role_id'] === 1) {
            return $cache[$user_id] = 'admin';
        }

        /* TQ-GATE-ROLE — الدور المُعلَن عند التسجيل هو الأصل، والاستيثاق بعده.
           كان `tq_gate` يُكتب ولا يُقرأ، فيُصنَّف كلُّ مَن سجّل طالبًا. */
        $tq_gate = isset($row['tq_gate']) ? (string) $row['tq_gate'] : '';

        /* معلّمٌ: الإعلان لا يكفي — الإدارة هي التي تضبط `is_instructor`
           عند الاعتماد. فمن أعلن ولم يُعتمد ليس معلّمًا بعد. */
        if (!empty($row['is_instructor'])) {
            return $cache[$user_id] = 'teacher';
        }

        /* وليُّ أمرٍ: إعلانه يكفي. وبوّابته لا تكشف بيانات طالبٍ إلّا برابطٍ
           وافق عليه الطالب نفسه — فالصفة بلا رابطٍ لا تفتح شيئًا، وحجبُها
           عنه يمنعه من الوصول إلى الشاشة التي يطلب الربط منها أصلًا. */
        if ($tq_gate === 'parent') {
            return $cache[$user_id] = 'parent';
        }

        // وليّ الأمر ليس عمودًا بل علاقة موثّقة: رابط نشط بابن.
        if ($CI->db->table_exists('parent_links')) {
            $linked = $CI->db->where('parent_user_id', $user_id)
                             ->where('status', 'active')
                             ->count_all_results('parent_links');
            if ($linked > 0) return $cache[$user_id] = 'parent';
        }

        return $cache[$user_id] = 'student';
    }
}

if (!function_exists('tq_is')) {
    /** هل المستخدم الحالي بهذا الدور؟ */
    function tq_is($role, $user_id = null)
    {
        return tq_role($user_id) === $role;
    }
}

if (!function_exists('tq_home_for')) {
    /**
     * بوّابة كل دور. تُستعمل عند الدخول وعند منع دخول بوّابة غير بوّابته —
     * فالمستخدم يُعاد إلى مكانه لا إلى صفحة خطأ.
     */
    function tq_home_for($role)
    {
        switch ($role) {
            case 'admin':   return site_url('admin/dashboard');
            case 'teacher': return site_url('teacher');
            case 'parent':  return site_url('parent');
            case 'student': return site_url('student');
            default:        return site_url('login');
        }
    }
}

if (!function_exists('tq_guard')) {
    /**
     * حارس البوّابة. يمنع دخول بوّابة ليست لصاحبها، ويعيده إلى بوّابته
     * برسالة مفهومة بدل شاشة 403 صمّاء.
     *
     * الأدمن **لا يُستثنى**: خلط الأدوار يجعل الاختبار كاذبًا، ويجعل الأدمن
     * يرى شاشات طالب فارغة فيظنّها معطوبة.
     */
    function tq_guard($required)
    {
        $CI = get_instance();

        if (!$CI->session->userdata('user_id')) {
            $CI->session->set_userdata('url_history', current_url());
            redirect(site_url('login'), 'location', 302);
        }

        $role = tq_role();
        if ($role === $required) return $role;

        $names = [
            'admin'   => 'الإدارة',
            'teacher' => 'المعلّم',
            'parent'  => 'وليّ الأمر',
            'student' => 'الطالب',
        ];
        $CI->session->set_flashdata(
            'error_message',
            'هذه الصفحة تخصّ بوّابة ' . ($names[$required] ?? $required) .
            '، وحسابك مسجَّل بصفة ' . ($names[$role] ?? $role) . '.'
        );
        redirect(tq_home_for($role), 'location', 302);
    }
}
