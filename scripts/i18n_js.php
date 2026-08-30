<?php
/**
 * TQ-I18N — يلف النص العربي في سكربتات اللوحات بـ`TQ.t()`، ويجرده.
 *
 * PHP يعطينا `token_get_all` تفرق النص من التعليق؛ والجافاسكربت لا مقطع
 * له هنا، فيقطع بيد: مسح حرفا حرفا يعرف السلسلة من التعليق من الصيغة
 * النظامية (regex). والفرق ليس تفصيلا — `admin.js` وحده فيه مئة واثنان
 * وثلاثون سطرا عربيا، وأكثرها **تعليق** يشرح لماذا كتب السطر، ولفه يخرب
 * الشيفرة ويضخم القاموس بما لا يقرؤه أحد.
 *
 *     php scripts/i18n_js.php                  # عرض
 *     php scripts/i18n_js.php --apply
 *     php scripts/i18n_js.php --dump=js.json
 *
 * ويترك وحده:
 *   - النص الموصول بمتغير (`'لديك ' + n`) — يعلم ويترك لليد.
 *   - ما لف من قبل، فالتشغيل مرتين لا يغير شيئا في الثانية.
 *   - مفتاح كائن (`{'نص': 1}`) — قد يخزن أو يقارن.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only.\n"); }
chdir(dirname(__DIR__));

$apply = in_array('--apply', $argv, true);
$dump  = null;
foreach ($argv as $a) if (strpos($a, '--dump=') === 0) $dump = substr($a, 7);

/** سكربتات اللوحات الأربع. `apidocs.js` خارجها — صفحة وثائق إنجليزية أصلا. */
$files = array(
    'assets/taqdar/js/admin.js',
    'assets/taqdar/js/taqdar.js',
    'assets/taqdar/js/taqdar-lesson.js',
    'assets/taqdar/js/taqdar-reviews.js',
    'assets/taqdar/js/tq-player.js',
    'assets/taqdar/js/tq-phone.js',
    'assets/taqdar/js/tq-duration-probe.js',
);

function has_arabic($s) { return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', (string) $s); }
function norm($s) { return trim(preg_replace('/\s+/u', ' ', preg_replace('/\x{00A0}/u', ' ', (string) $s))); }

/**
 * يقطع سكربتا إلى سلاسل حرفية، متخطيا التعليق والصيغ النظامية.
 *
 * @return array  [offset, length, quote, value]
 */
function js_strings($src)
{
    $out = array();
    $n   = strlen($src);
    $i   = 0;
    $prev = '';              // آخر رمز ذي معنى — به تعرف `/` قسمة من صيغة

    while ($i < $n) {
        $c = $src[$i];

        /* تعليق سطر */
        if ($c === '/' && $i + 1 < $n && $src[$i + 1] === '/') {
            while ($i < $n && $src[$i] !== "\n") $i++;
            continue;
        }
        /* تعليق كتلة */
        if ($c === '/' && $i + 1 < $n && $src[$i + 1] === '*') {
            $end = strpos($src, '*/', $i + 2);
            $i = ($end === false) ? $n : $end + 2;
            continue;
        }
        /* صيغة نظامية — تعرف بما قبلها: `(`، `,`، `=`، `:`، `[`، `!`، `&`، `|`، `return` */
        if ($c === '/' && preg_match('/[(,=:\[!&|?{;]$|\breturn$|\btypeof$/', $prev)) {
            $i++;
            $inClass = false;
            while ($i < $n) {
                if ($src[$i] === '\\') { $i += 2; continue; }
                if ($src[$i] === '[') $inClass = true;
                elseif ($src[$i] === ']') $inClass = false;
                elseif ($src[$i] === '/' && !$inClass) { $i++; break; }
                elseif ($src[$i] === "\n") break;
                $i++;
            }
            $prev = '/';
            continue;
        }
        /* سلسلة */
        if ($c === "'" || $c === '"' || $c === '`') {
            $q = $c; $start = $i; $i++; $val = '';
            $interp = false;
            while ($i < $n) {
                if ($src[$i] === '\\') { $val .= $src[$i] . $src[$i + 1]; $i += 2; continue; }
                if ($q === '`' && $src[$i] === '$' && isset($src[$i + 1]) && $src[$i + 1] === '{') $interp = true;
                if ($src[$i] === $q) { $i++; break; }
                $val .= $src[$i];
                $i++;
            }
            $out[] = array($start, $i - $start, $q, $val, $interp);
            $prev = 'STR';
            continue;
        }

        if (!ctype_space($c)) $prev = $c;
        $i++;
    }
    return $out;
}

/** هل السلسلة في موضع لا يترجم؟ */
function js_reject($src, $s)
{
    list($off, $len, $q, $val, $interp) = $s;

    if ($interp) return 'template-interp';

    $after  = ltrim(substr($src, $off + $len, 4));
    $before = rtrim(substr($src, max(0, $off - 4), min(4, $off)));

    /* مفتاح كائن: يليه `:` **ويسبقه** `{` أو `,`.
       والشرط الثاني ليس زينة: فرع الشرط الثلاثي يليه `:` كذلك —
       `x ? 'نعم، احذف' : 'تراجع'` — فبلاه يسقط نصف كل سؤال تأكيد في
       اللوحة من الترجمة، ويبقى «نعم، احذف» عربيا فوق زر أحمر. */
    if (isset($after[0]) && $after[0] === ':'
        && preg_match('/[{,]$/', $before)) return 'object-key';

    /* طرف مقارنة. */
    if (preg_match('/(===|!==|==|!=)$/', $before)) return 'comparison';
    if (preg_match('/^(===|!==|==|!=)/', $after)) return 'comparison';

    /* ملفوف من قبل: `TQ.t('...')` أو `TQA.t('...')`. */
    if (preg_match('/\b(TQ|TQA)\.t\(\s*$/', substr($src, max(0, $off - 12), min(12, $off)))) return 'already';

    /* موصول: `'نص' + x` أو `x + 'نص'` — الترجمة نصفا تفسد ترتيب الجملة. */
    if (preg_match('/^\s*\+/', $after) || preg_match('/\+\s*$/', $before)) return 'concat';

    return null;
}

$catalog = array();
$stats   = array('wrapped' => 0, 'files' => 0);
$skipped = array();

foreach ($files as $file) {
    if (!is_file($file)) { fwrite(STDERR, "missing $file\n"); continue; }
    $src = file_get_contents($file);
    if (!has_arabic($src)) continue;

    $edits = array();
    foreach (js_strings($src) as $s) {
        list($off, $len, $q, $val, $interp) = $s;
        if (!has_arabic($val)) continue;

        $reason = js_reject($src, $s);
        if ($reason !== null) { $skipped[$reason][] = $file . ' :: ' . norm($val); continue; }

        $k = norm($val);
        if ($k === '') continue;
        $catalog[$k] = $file;
        $edits[] = array($off, $len, 'TQ.t(' . $q . $val . $q . ')');
    }
    if (!$edits) continue;

    usort($edits, function ($a, $b) { return $b[0] <=> $a[0]; });
    $out = $src;
    foreach ($edits as $e) $out = substr_replace($out, $e[2], $e[0], $e[1]);

    $stats['files']++;
    $stats['wrapped'] += count($edits);
    if ($apply) file_put_contents($file, $out);
    else printf("%-46s %4d strings\n", $file, count($edits));
}

printf("\nfiles=%d wrapped=%d unique-keys=%d  %s\n",
    $stats['files'], $stats['wrapped'], count($catalog), $apply ? 'APPLIED' : '(dry run)');
foreach ($skipped as $r => $l) printf("  skipped %-18s %4d\n", $r, count($l));

if ($dump) {
    file_put_contents($dump, json_encode(array(
        'keys' => array_keys($catalog), 'skipped' => $skipped,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "wrote $dump\n";
}
