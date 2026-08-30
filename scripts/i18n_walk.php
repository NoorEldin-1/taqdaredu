<?php
/**
 * TQ-I18N — يمشي في اللوحات الأربع باللغتين، ويقول ما انكسر.
 *
 * الفاحص الساكن (`i18n_lint.php`) يقرأ الشيفرة، وهو لا يرى شيئا من هذا:
 * صفحة ترد 500 بعد لف نصها، وشاشة تفتح باتجاه لا يوافق لغتها، ولوحة بلا
 * مبدل يبدل به من فتحها. فالحكم الأخير لما يخرج من الخادم فعلا.
 *
 * ويشترط حسابات اختبار للأدوار الأربعة بكلمة المرور نفسها — وهي بيئة
 * التطوير وحدها، فلا تشغل على خادم حي.
 *
 *     php scripts/i18n_walk.php
 *     php scripts/i18n_walk.php  # يرد 1 إن سقطت مجموعة، فيصلح لبوابة نشر
 */
$BASE = getenv('TQ_BASE') ?: 'http://localhost:8081';
$PASS = getenv('TQ_TEST_PASS') ?: 'Taqdar#Test1';

$panels = array(
    'student' => array('student.test@taqdaredu.com', array(
        '/student/home','/student/courses','/student/lessons','/student/tasks','/student/exams',
        '/student/materials','/student/library','/student/favourites','/student/reports',
        '/student/certificates','/student/payments','/student/subscription','/student/messages',
        '/student/notifications','/student/calendar','/student/settings','/student/reviews',
        '/student/mistakes','/student/mastery','/student/on-demand','/student/search','/student/profile',
    )),
    'teacher' => array('teacher.test@taqdaredu.com', array(
        '/teacher/dashboard','/teacher/courses','/teacher/lessons','/teacher/upload','/teacher/studio',
        '/teacher/questions','/teacher/marking','/teacher/students','/teacher/sessions','/teacher/wallet',
        '/teacher/analytics','/teacher/messages','/teacher/notifications','/teacher/settings','/teacher/course/new',
    )),
    'parent' => array('parent.test@taqdaredu.com', array(
        '/parent/children','/parent/reports','/parent/weekly','/parent/payments',
        '/parent/messages','/parent/alerts','/parent/settings','/parent/pay',
    )),
    'admin' => array('admin.test@taqdaredu.com', array(
        '/taqdar_admin/overview','/taqdar_admin/review','/taqdar_admin/subscriptions',
        '/taqdar_admin/course_sales','/taqdar_admin/sessions','/taqdar_admin/slots','/taqdar_admin/tap',
        '/taqdar_admin/bank','/taqdar_admin/mail','/taqdar_admin/whatsapp','/taqdar_admin/content',
        '/taqdar_admin/tracking','/taqdar_admin/teacher_new','/taqdar_admin/payouts',
        '/taqdar_admin/module/plans','/taqdar_admin/module/paths','/admin/courses',
        '/admin/manage_profile','/admin/admins','/admin/blog','/admin/contact','/admin/frontend_settings',
    )),
);

$jar = null;
function req($url, $post = null)
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $b = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($c, (string) $b);
}
function csrf($h) { return preg_match('/name="csrf_test_name" value="([a-f0-9]+)"/', $h, $m) ? $m[1] : ''; }

$ERRORS = array('Fatal error', 'Parse error', 'Undefined variable', 'Undefined index',
                'Undefined array key', 'Call to a member function', 'A PHP Error was encountered',
                'Unknown column', 'Allowed memory size');

$fails = 0; $checked = 0;

foreach ($panels as $panel => $cfg) {
    list($email, $urls) = $cfg;
    foreach (array('arabic' => 'rtl', 'english' => 'ltr') as $lang => $wantDir) {

        $jar = tempnam(sys_get_temp_dir(), 'tqv');
        list(, $h) = req($BASE . '/login');
        req($BASE . '/login/validate_login', array(
            'email' => $email, 'password' => $PASS, 'csrf_test_name' => csrf($h)));

        list(, $h2) = req($BASE . $urls[0]);
        $tok = csrf($h2);
        if ($tok === '') { printf("%-8s %-8s  !! no csrf — cannot set language\n", $panel, $lang); $fails++; continue; }
        req($BASE . '/language/set', array('lang' => $lang, 'back' => $urls[0], 'csrf_test_name' => $tok));

        $bad = array(); $noSwitch = 0; $wrongDir = 0;
        foreach ($urls as $u) {
            list($code, $body) = req($BASE . $u);
            $checked++;
            if ($code !== 200) { $bad[] = "$u:HTTP$code"; continue; }
            foreach ($ERRORS as $e) {
                if (stripos($body, $e) !== false) { $bad[] = "$u:$e"; break; }
            }
            if (preg_match('/<html[^>]*dir="([a-z]+)"/', $body, $m)) {
                if ($m[1] !== $wantDir) { $wrongDir++; $bad[] = "$u:dir={$m[1]}"; }
            }
            if (strpos($body, 'langsw') === false) $noSwitch++;
        }
        @unlink($jar);

        printf("%-8s %-8s  urls=%-3d  switcher-missing=%-2d  wrong-dir=%-2d  %s\n",
            $panel, $lang, count($urls), $noSwitch, $wrongDir,
            $bad ? ('FAIL: ' . implode(' | ', array_slice($bad, 0, 4))) : 'ok');
        if ($bad) $fails++;
    }
}

printf("\npages checked: %d   failing groups: %d\n", $checked, $fails);
exit($fails ? 1 : 0);
