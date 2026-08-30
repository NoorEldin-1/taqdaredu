<?php
/**
 * TQ-I18N — أربعة أسئلة لا يجيبها فحص الشيفرة ولا مرور الصفحات.
 *
 *   ١ أيتبع التفضيل **الحساب** أم المتصفح؟ من بدل لغته على جهاز يجب أن
 *     يجدها على جهازه الثاني — وإلا فالتفضيل صف يكتب ولا يقرأ.
 *   ٢ أتحفظ كعكة الزائر اختياره قبل أن يسجل؟
 *   ٣ أتخرج **رسالة مسار الكتابة** مترجمة؟ وهي تولد في نموذج وتخزن في
 *     `flashdata` وتقرأ في طلب تال — ثلاث محطات يسقط النص في أيها.
 *   ٤ أيصل قاموس المتصفح فعلا؟
 *
 *     php scripts/i18n_behaviour.php
 */
$BASE = getenv('TQ_BASE') ?: 'http://localhost:8081';
$PASS = getenv('TQ_TEST_PASS') ?: 'Taqdar#Test1';
$jar = null;

function req($url, $post = null)
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($c, (string) $b);
}
function csrf($h) { return preg_match('/name="csrf_test_name" value="([a-f0-9]+)"/', $h, $m) ? $m[1] : ''; }
function dir_of($h) { return preg_match('/<html[^>]*dir="([a-z]+)"/', $h, $m) ? $m[1] : '?'; }
function say($ok, $msg) { printf("  [%s] %s\n", $ok ? 'PASS' : 'FAIL', $msg); return $ok ? 0 : 1; }

$fail = 0;

/* ── 1) التفضيل يتبع الحساب: جهاز أول يبدل، جهاز ثان (كعكة جديدة) يرث ── */
echo "1) preference follows the account, not the browser\n";
$jar = tempnam(sys_get_temp_dir(), 'A');
list(, $h) = req($BASE . '/login');
req($BASE . '/login/validate_login', array('email' => 'student.test@taqdaredu.com',
    'password' => $PASS, 'csrf_test_name' => csrf($h)));
list(, $h) = req($BASE . '/student/home');
req($BASE . '/language/set', array('lang' => 'english', 'back' => '/student/home',
    'csrf_test_name' => csrf($h)));
list(, $b) = req($BASE . '/student/home');
$fail += say(dir_of($b) === 'ltr', 'device A switched to English (dir=ltr)');
@unlink($jar);

$jar = tempnam(sys_get_temp_dir(), 'B');          // جهاز ثان، بلا كعكة
list(, $h) = req($BASE . '/login');
req($BASE . '/login/validate_login', array('email' => 'student.test@taqdaredu.com',
    'password' => $PASS, 'csrf_test_name' => csrf($h)));
list(, $b) = req($BASE . '/student/home');
$fail += say(dir_of($b) === 'ltr', 'device B inherits English from the account row');
@unlink($jar);

/* ── 2) الزائر: الكعكة تحفظ اختياره قبل الدخول ── */
echo "\n2) a guest's choice survives in a cookie\n";
$jar = tempnam(sys_get_temp_dir(), 'G');
list(, $h) = req($BASE . '/login');
req($BASE . '/language/set', array('lang' => 'english', 'back' => '/login',
    'csrf_test_name' => csrf($h)));
list(, $b) = req($BASE . '/login');
$fail += say(dir_of($b) === 'ltr', 'guest switched to English');
@unlink($jar);

/* ── 3) رسالة طائرة من مسار كتابة تخرج مترجمة ── */
echo "\n3) a write-path flash message comes back translated\n";
$jar = tempnam(sys_get_temp_dir(), 'F');
list(, $h) = req($BASE . '/login');
req($BASE . '/login/validate_login', array('email' => 'student.test@taqdaredu.com',
    'password' => $PASS, 'csrf_test_name' => csrf($h)));
list(, $h) = req($BASE . '/student/settings');
req($BASE . '/language/set', array('lang' => 'english', 'back' => '/student/settings',
    'csrf_test_name' => csrf($h)));

list(, $h) = req($BASE . '/student/settings');
/* حفظ التفضيلات بلغة غير متاحة — النموذج يرد برسالة خطأ. */
req($BASE . '/student/settings/save', array('action' => 'prefs', 's' => 'prefs', 'language' => 'klingon', 'csrf_test_name' => csrf($h)));
list(, $b) = req($BASE . '/student/settings?s=prefs');
$hasEn = (stripos($b, 'Language not available') !== false);
$hasAr = (strpos($b, 'لغة غير متاحة') !== false);
$fail += say($hasEn && !$hasAr, 'the rejection reads in English, not Arabic');

/* وحفظ صحيح يعيدنا إلى العربية ويقول ذلك بالعربية. */
list(, $h) = req($BASE . '/student/settings');
req($BASE . '/student/settings/save', array('action' => 'prefs', 's' => 'prefs', 'language' => 'arabic', 'csrf_test_name' => csrf($h)));
list(, $b) = req($BASE . '/student/settings');
$fail += say(dir_of($b) === 'rtl' && strpos($b, 'حفظت تفضيلاتك') !== false,
    'saving Arabic switches back and confirms in Arabic');
@unlink($jar);

/* ── 4) قاموس المتصفح يصل ── */
echo "\n4) the browser catalogue is delivered\n";
$jar = tempnam(sys_get_temp_dir(), 'J');
list(, $h) = req($BASE . '/login');
req($BASE . '/language/set', array('lang' => 'english', 'back' => '/login',
    'csrf_test_name' => csrf($h)));
list(, $b) = req($BASE . '/login');
$ok = preg_match('/window\.TQ_I18N=\{"lang":"english"/', $b)
   && strpos($b, 'Confirm this action') !== false;
$fail += say((bool) $ok, 'TQ_I18N carries lang=english and a translated JS string');
@unlink($jar);

printf("\n%s\n", $fail ? "FAILURES: $fail" : 'all checks passed');
exit($fail ? 1 : 0);
