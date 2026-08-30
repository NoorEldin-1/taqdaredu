<?php
/**
 * TQ-I18N — يحول الجملة المركبة إلى مفتاح واحد بعلامات `____`.
 *
 * الجملة التي تخللها متغير لا تترجم قطعا:
 *
 *     <p>أنجزت <?php echo $done; ?> من <?php echo $target; ?> اليوم</p>
 *
 * فمن يترجم «أنجزت» و«من» و«اليوم» ثلاث مرات يخرج بجملة إنجليزية بترتيب
 * عربي. فالمفتاح **الجملة كلها** وفيها `____` بعدد متغيراتها — وهي علامة
 * `t()` نفسها في PHP والجافاسكربت، فلا اصطلاح ثالث.
 *
 * **والمواضع من `tq_segment_view()` لا من صيغة حرة.** ونسخة أولى من هذا
 * الملف بحثت بـ`/>(نص)<\?php …\?>(نص)</` مباشرة في المصدر، فابتلعت كتلة
 * `<script>` كاملة (النص بين `>` و`<` فيها هو الشيفرة نفسها) وابتلعت
 * حدود السمات — فأخرجت مفتاحا فيه ثلاثون سطر جافاسكربت. والمقطع يعرف
 * السكربت من النص ويعرف حدود الوسم، وهو نفسه الذي يقود التعديل الآلي
 * والفحص: فلا يعالج هذا ما لا يراه ذاك.
 *
 *     php scripts/i18n_interp.php
 *     php scripts/i18n_interp.php --apply
 */

require __DIR__ . '/i18n_lib.php';
chdir(dirname(__DIR__));

$apply = in_array('--apply', $argv, true);
$total = 0; $files = 0; $left = 0;

foreach (tq_i18n_targets() as $gname => $group) {
    if (empty($group['wrap'])) continue;

    foreach (tq_i18n_files($group) as $file) {
        $src = file_get_contents($file);
        if (!tq_has_arabic($src)) continue;

        $edits = array();
        foreach (tq_segment_view($src) as $seg) {
            if (empty($seg['dynamic'])) continue;

            $chunk = substr($src, $seg['start'], $seg['len']);
            $r = tq_interp_rewrite($chunk, $seg['kind'] === 'attr');
            if ($r === null) { $left++; continue; }

            $edits[] = array($seg['start'], $seg['len'], $r);
        }
        if (!$edits) continue;

        usort($edits, function ($a, $b) { return $b[0] <=> $a[0]; });
        $out = $src;
        foreach ($edits as $e) $out = substr_replace($out, $e[2], $e[0], $e[1]);

        $err = tq_lint_str($out);
        if ($err !== null) { fwrite(STDERR, "!! SKIPPED (syntax) $file — $err\n"); continue; }

        $files++; $total += count($edits);
        if ($apply) file_put_contents($file, $out);
        else printf("%-62s %3d\n", $file, count($edits));
    }
}

printf("\nfiles=%d rewritten=%d left-for-hand=%d  %s\n",
    $files, $total, $left, $apply ? 'APPLIED' : '(dry run)');

/* ------------------------------------------------------------------ */

/**
 * يعيد كتابة نص متقطع مفتاحا واحدا — أو null إن لم يصلح.
 *
 * ولا يعالج إلا `<?php echo …; ?>` **بسيطة**: تعبير واحد بلا `?>` داخله
 * ولا بنية تحكم. و`<?php if …: ?>` تقطع النص شرطا لا قيمة — وجملة نصفها
 * مشروط تحتاج مفتاحين لا مفتاحا، وهو قرار لا يتخذه نمط.
 */
function tq_interp_rewrite($chunk, $isAttr)
{
    /* كتل PHP وحدودها. */
    if (!preg_match_all('/<\?php\s+echo\s+(.+?);?\s*\?>/s', $chunk, $m, PREG_OFFSET_CAPTURE)) return null;

    /* أي كتلة PHP أخرى (شرط، تعليق، وسم مفتوح) تمنع. */
    $all = preg_match_all('/<\?/', $chunk);
    if ($all !== count($m[0])) return null;

    /* السمة: علامة اقتباس مزدوجة داخل التعبير تكسر الوسم عند إعادة البناء. */
    foreach ($m[1] as $e) {
        if (strpos($e[0], '?>') !== false) return null;
        if (preg_match('/\b(if|for|foreach|while|switch|endif|else)\b/', $e[0])) return null;
    }

    /* يبنى المفتاح: النص كما هو والكتل تصير `____`. */
    $key = '';
    $exprs = array();
    $pos = 0;
    foreach ($m[0] as $i => $blk) {
        $key .= substr($chunk, $pos, $blk[1] - $pos);
        $key .= '____';
        $exprs[] = trim($m[1][$i][0]);
        $pos = $blk[1] + strlen($blk[0]);
    }
    $key .= substr($chunk, $pos);

    $key = tq_key($key);
    if ($key === '' || !tq_has_arabic($key)) return null;
    /* المفتاح كله بدائل أو وسم — لا جملة. */
    if (strpos($key, '<') !== false || strpos($key, '"') !== false) return null;

    $fn = $isAttr ? 'te' : 't';
    return '<?php echo ' . $fn . "('" . str_replace(array('\\', "'"), array('\\\\', "\\'"), $key)
         . "', array(" . implode(', ', $exprs) . ')); ?>';
}

function tq_lint_str($code)
{
    $tmp = tempnam(sys_get_temp_dir(), 'tqi');
    file_put_contents($tmp, $code);
    $o = array(); $rc = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
    unlink($tmp);
    return $rc === 0 ? null : trim(implode(' ', $o));
}
