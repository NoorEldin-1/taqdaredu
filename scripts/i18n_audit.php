<?php
/**
 * TQ-I18N — يقيس ما بقي عربيا في **الصفحة المعروضة**، لا في الشيفرة.
 *
 * `i18n_lint.php` يقرأ الشيفرة فيقول إن كل نص ملفوف وكل مفتاح مترجم. وذلك
 * صحيح ولا يكفي: النص قد يجيء من عمود في القاعدة (اسم كورس)، أو من صف
 * `settings`، أو من قالب موروث لم يدخل النطاق — وكلها تعرض عربية على شاشة
 * إنجليزية والفاحص ساكت. فالحكم الأخير لما يخرج من الخادم.
 *
 * يجلب الصفحات بحساب حي، ويعد الكلمات العربية في النص الظاهر وحده — بعد
 * قص السكربت والنمط والوسوم. ويسمي أكثرها تكرارا، فيقال أين يبدأ العمل.
 *
 *     php scripts/i18n_audit.php --base=http://localhost:8081 \
 *         --email=student.test@taqdaredu.com --pass=... --urls=urls.txt
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only.\n"); }
chdir(dirname(__DIR__));

$opt = array('base' => 'http://localhost:8081', 'email' => '', 'pass' => '', 'urls' => '', 'lang' => 'english', 'top' => 25);
foreach ($argv as $a) if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) $opt[$m[1]] = $m[2];
if ($opt['urls'] === '' || !is_file($opt['urls'])) { fwrite(STDERR, "need --urls=<file>\n"); exit(1); }

$jar = tempnam(sys_get_temp_dir(), 'tqjar');

/** نداء واحد بكعكة محفوظة. */
function req($url, $post = null)
{
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, (string) $body);
}

function csrf($html)
{
    return preg_match('/name="csrf_test_name" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
}

/* ---- دخول ثم تثبيت اللغة ---- */
if ($opt['email'] !== '') {
    list(, $h) = req($opt['base'] . '/login');
    req($opt['base'] . '/login/validate_login', array(
        'email' => $opt['email'], 'password' => $opt['pass'], 'csrf_test_name' => csrf($h),
    ));
}
/* الرمز يؤخذ من صفحة فيها نموذج فعلا — والرئيسية بلا نموذج، فرمزها فارغ
   والتبديل يرد 403 صامتا فيقيس الفاحص الصفحة العربية ويظنها لم تترجم. */
/* من دخل حسابه يحول `/login` إلى لوحته بترويسة `Refresh` — وcurl لا
   يتبعها، فيعود الجسم كعبا بلا رمز. فالرمز يطلب من أول صفحة في القائمة
   كذلك. */
list(, $h) = req($opt['base'] . '/login');
if (csrf($h) === '') {
    $urls = array_values(array_filter(
        file($opt['urls'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
        function ($l) { $l = trim($l); return $l !== '' && $l[0] !== '#'; }));
    $first = $urls ? trim((string) $urls[0]) : '';
    if ($first !== '') list(, $h) = req($opt['base'] . $first);
}
if (csrf($h) === '') { fwrite(STDERR, "no csrf token found — cannot switch language
"); exit(1); }
$urls2 = array_values(array_filter(
    file($opt['urls'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
    function ($l) { $l = trim($l); return $l !== '' && $l[0] !== '#'; }));
$first = $urls2 ? trim((string) $urls2[0]) : '';

req($opt['base'] . '/language/set', array(
    'lang' => $opt['lang'], 'back' => $first, 'csrf_test_name' => csrf($h),
));

/* والتبديل يثبت قبل القياس: صفحة تفتح بالعربية تجعل التقرير كله كذبا. */
list(, $probe) = req($opt['base'] . ($first !== '' ? $first : '/login'));
if (!preg_match('/<html[^>]*lang="' . preg_quote(substr($opt['lang'], 0, 2) === 'en' ? 'en' : 'ar', '/') . '"/', $probe)) {
    fwrite(STDERR, "language did not switch to {$opt['lang']} — the report below would be meaningless
");
    exit(1);
}

/**
 * النص الظاهر وحده: يقص السكربت والنمط والتعليق والوسوم، ويفك الكيانات.
 */
function visible_text($html)
{
    $x = preg_replace('/<(script|style|template)\b[^>]*>.*?<\/\1>/is', ' ', $html);
    $x = preg_replace('/<!--.*?-->/s', ' ', $x);
    /* السمات المعروضة تحسب كذلك — نص يقرؤه المستخدم وقارئ الشاشة. */
    preg_match_all('/\b(title|placeholder|alt|aria-label)="([^"]*)"/u', $x, $m);
    $attrs = implode(' ', $m[2]);
    $x = preg_replace('/<[^>]*>/', ' ', $x);
    return html_entity_decode($x . ' ' . $attrs, ENT_QUOTES, 'UTF-8');
}

$total = 0; $arabicTotal = 0;
$rows = array(); $words = array();

foreach (file($opt['urls'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $u) {
    $u = trim($u);
    if ($u === '' || $u[0] === '#') continue;

    list($code, $body) = req($opt['base'] . $u);
    $txt = visible_text($body);

    preg_match_all('/[\p{Arabic}]+(?:[ \x{060C}][\p{Arabic}]+){0,6}/u', $txt, $ar);
    $n = 0;
    foreach ($ar[0] as $phrase) {
        $phrase = trim($phrase);
        if (mb_strlen($phrase) < 2) continue;
        $words[$phrase] = ($words[$phrase] ?? 0) + 1;
        $n += count(preg_split('/\s+/u', $phrase));
    }
    $all = count(preg_split('/\s+/u', trim(preg_replace('/\s+/u', ' ', $txt))));

    $rows[] = array($u, $code, $all, $n);
    $total += $all; $arabicTotal += $n;
}

printf("%-38s %5s %8s %8s %7s\n", 'URL', 'code', 'words', 'arabic', '%');
foreach ($rows as $r) {
    printf("%-38s %5d %8d %8d %6.1f%s\n", $r[0], $r[1], $r[2], $r[3],
        $r[2] ? ($r[3] / $r[2] * 100) : 0, $r[3] ? '  <<<' : '');
}
printf("\nTOTAL words=%d  arabic=%d  (%.2f%%)\n", $total, $arabicTotal,
    $total ? ($arabicTotal / $total * 100) : 0);

arsort($words);
if ($words) {
    echo "\nTOP ARABIC PHRASES STILL RENDERED\n";
    $i = 0;
    foreach ($words as $w => $c) {
        printf("%4d  %s\n", $c, mb_substr($w, 0, 90));
        if (++$i >= (int) $opt['top']) break;
    }
}
@unlink($jar);
