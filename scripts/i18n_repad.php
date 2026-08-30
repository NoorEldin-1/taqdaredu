<?php
/**
 * TQ-I18N — يعيد المسافة الطرفية التي قصها اللف.
 *
 * التعديل الآلي كان يكتب `t('نص')` مكان `'نص '` — يسوي المفتاح ويقص طرفيه.
 * وذلك سليم في وسم HTML (المتصفح يطوي الفراغ)، ويفسد في **الوصل**:
 *
 *     'هذه الصفحة تخص بوابة ' . $name      →  «هذه الصفحة تخص بوابةالمعلم»
 *
 * كلمتان تلتصقان في رسالة يقرؤها المستخدم، ولا خطأ يظهر.
 *
 * و`tq_translate()` تعيد المسافة حول الترجمة إن وجدتها **في النص الممرر**
 * (`tq_i18n_reindent`) — فالعلاج أن يبقى الأصل بمسافته داخل `t()`، لا أن
 * تكتب المسافة خارجها. فالمفتاح مسوى في الحالين، والمطبوع يبقى كما كان.
 *
 * والأصل يقرأ من نسخة سابقة: مراجعة git للملفات المودعة، أو مجلد نسخ.
 *
 *     php scripts/i18n_repad.php --from=4bc1efa
 *     php scripts/i18n_repad.php --from=4bc1efa --apply
 *     php scripts/i18n_repad.php --dir=/path/to/backup --only=application/helpers --apply
 */

require __DIR__ . '/i18n_lib.php';
chdir(dirname(__DIR__));

$apply = in_array('--apply', $argv, true);
$rev = null; $bdir = null; $only = null;
foreach ($argv as $a) {
    if (strpos($a, '--from=') === 0) $rev  = substr($a, 7);
    if (strpos($a, '--dir=')  === 0) $bdir = rtrim(substr($a, 6), '/');
    if (strpos($a, '--only=') === 0) $only = strtr(substr($a, 7), '\\', '/');
}
if ($rev === null && $bdir === null) { fwrite(STDERR, "need --from=<git-rev> or --dir=<backup>\n"); exit(1); }

/** نص الملف كما كان — من git أو من نسخة. */
function original($file, $rev, $bdir)
{
    if ($bdir !== null) {
        $p = $bdir . '/' . basename($file);
        return is_file($p) ? file_get_contents($p) : null;
    }
    $out = array(); $rc = 0;
    exec('git show ' . escapeshellarg($rev . ':' . $file) . ' 2>' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null'), $out, $rc);
    return $rc === 0 ? implode("\n", $out) : null;
}

/** كل سلسلة عربية في الأصل: المفتاح المسوى => النص بمسافته. */
function padded_map($src)
{
    $map = array();
    foreach (token_get_all($src) as $tok) {
        if (!is_array($tok) || $tok[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
        if (!tq_has_arabic($tok[1])) continue;
        $val = tq_tok_value($tok);
        $key = tq_key($val);
        if ($key === '' || $key === $val) continue;      // لا فرق، فلا شيء يعاد
        if (tq_key($val) !== $key) continue;
        /* الفرق يجب أن يكون في الأطراف وحدها: نص طوي فراغه الداخلي لا
           يعاد، فإعادته تغير المفتاح ويسقط من القاموس. */
        if (trim($val) !== $key) continue;
        $map[$key] = $val;
    }
    return $map;
}

$files = 0; $fixed = 0;

foreach (tq_i18n_targets() as $gname => $group) {
    foreach (tq_i18n_files($group) as $file) {
        if ($only !== null && strpos($file, $only) !== 0) continue;

        $cur = file_get_contents($file);
        if (strpos($cur, 't(') === false) continue;

        $org = original($file, $rev, $bdir);
        if ($org === null) continue;
        $map = padded_map($org);
        if (!$map) continue;

        $n = 0;
        $out = preg_replace_callback("/\\bt(e?)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/u",
            function ($m) use ($map, &$n) {
                $key = str_replace(array("\\'", '\\\\'), array("'", '\\'), $m[2]);
                if (!isset($map[$key])) return $m[0];
                $n++;
                return 't' . $m[1] . "('"
                     . str_replace(array('\\', "'"), array('\\\\', "\\'"), $map[$key]) . "'";
            }, $cur);

        if (!$n) continue;

        $tmp = tempnam(sys_get_temp_dir(), 'tqp');
        file_put_contents($tmp, $out);
        $o = array(); $rc = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
        unlink($tmp);
        if ($rc !== 0) { fwrite(STDERR, "!! SKIPPED (syntax) $file\n"); continue; }

        $files++; $fixed += $n;
        if ($apply) file_put_contents($file, $out);
        else printf("%-62s %3d\n", $file, $n);
    }
}

printf("\nfiles=%d restored=%d  %s\n", $files, $fixed, $apply ? 'APPLIED' : '(dry run)');
