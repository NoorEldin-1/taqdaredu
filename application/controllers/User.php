<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * لوحة المحاضر الموروثة — محولة إلى بوابة المعلم.
 *
 * ═══ ما كان هنا ═══
 *
 * متحكم من ٦٧ كيلوبايت و٢٩ دالة، و٧٣ ملف عرض في `views/backend/user/`:
 * لوحة المحاضر التي تأتي مع Academy LMS.
 *
 * ═══ ولماذا حذف ═══
 *
 * **لا يصلها أحد.** الدخول كله يمر بـ`tq_home_for()` في
 * `taqdar_role_helper.php`، وهي ترد المعلم إلى `/teacher` — بوابة تقدر.
 * ولا رابط واحد إلى `user/*` في ثيم `taqdar` كله؛ الروابط الباقية إليها
 * كانت في ثيم `default-new` الذي لا يعرض، وفي `views/components` الموروثة.
 *
 * وهي فوق ذلك **تكرار**: كل ما فيها له نظير في بوابة المعلم — الكورسات
 * والدروس والرفع وبنك الأسئلة والتصحيح والطلاب والحصص والمحفظة. ولوحتان
 * للمعلم تعنيان مكانين يرفع فيهما، وواحدا منهما لا يكتب في `lesson_progress`
 * ولا يعرف المسارات ولا الأهداف — فيرفع المعلم درسا لا يظهر لطالبه.
 *
 * ═══ ولماذا تحويل لا 404 ═══
 *
 * الحذف الكامل يجعل كل رابط قديم — إشارة مرجعية، رسالة بريد قديمة، رابط
 * في `default-new` — يرد خطأ توجيه لا صفحة. والتحويل يوصل صاحبه إلى
 * الشاشة التي حلت محل ما طلب. وهو النمط نفسه المتبع في
 * `Admin::dashboard()` بعد نقل لوحة القيادة.
 *
 * والخريطة تترجم المقصد لا ترمي الجميع إلى الجذر: من طلب «كورساتي»
 * يصل إلى كورساته، لا إلى لوحة يبحث فيها عنها.
 */
class User extends CI_Controller
{
    /** ما يقابل كل قسم من اللوحة القديمة في بوابة المعلم. */
    private static $MAP = array(
        'dashboard'            => 'teacher',
        'courses'              => 'teacher/courses',
        'courses-server-side'  => 'teacher/courses',
        'course_form'          => 'teacher/courses',
        'course_actions'       => 'teacher/courses',
        'course_list'          => 'teacher/courses',
        'curriculum'           => 'teacher/courses',
        'lessons'              => 'teacher/lessons',
        'sections'             => 'teacher/lessons',
        'quizes'               => 'teacher/questions',
        'quiz_questions'       => 'teacher/questions',
        'students'             => 'teacher/students',
        'my_students'          => 'teacher/students',
        'message'              => 'teacher/messages',
        'payout_report'        => 'teacher/wallet',
        'payout_settings'      => 'teacher/wallet',
        'request_withdrawal'   => 'teacher/wallet',
        'revenue'              => 'teacher/wallet',
        'manage_profile'       => 'teacher/settings',
        'blog'                 => 'teacher',
        'pending_blog'         => 'teacher',
        /* التقدم بطلب معلم صار من صفحة التسجيل ببوابة المعلم
           (`Login::register()` ينشئ صف `applications` حين `tq_gate=teacher`). */
        'become_an_instructor' => 'sign_up?as=teacher',
        'application_form'     => 'sign_up?as=teacher',
    );

    /**
     * الجلسة وقاعدة البيانات ليستا محملتين تلقائيا.
     *
     * `autoload.php` يحمل النماذج والمساعدات لا `session` ولا `database`،
     * وكان المتحكم القديم يحملهما في بانيه. وحذف البناء مع الجسم ترك
     * `$this->session` غير معرفة، فكان كل مسار `user/*` يرد 500 بدل أن
     * يحول — وهو أسوأ مما كان.
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
    }

    /**
     * كل مسار تحت `user/` يحول.
     *
     * `_remap` تلتقط كل دالة مطلوبة — الموجودة والمعدومة — فلا يبقى قسم
     * من التسعة والعشرين يرد 404 لأنه سقط من الخريطة.
     */
    public function _remap($method, $params = array())
    {
        $target = isset(self::$MAP[$method]) ? self::$MAP[$method] : 'teacher';

        // من ليس معلما لا يرمى إلى بوابة معلم: يرد إلى بوابته هو
        $uid = (int) $this->session->userdata('user_id');
        if (!$uid) {
            $this->session->set_userdata('url_history', current_url());
            redirect(site_url('login'), 'location', 302);
            return;
        }
        if (function_exists('tq_role') && tq_role($uid) !== 'teacher') {
            redirect(tq_home_for(tq_role($uid)), 'location', 302);
            return;
        }

        redirect(site_url($target), 'location', 301);
    }
}
