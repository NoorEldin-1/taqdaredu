<?php
/**
 * يولد صورة الغلاف البديلة لكورس بلا صورة.
 *
 * لا كورس في القاعدة يحمل `thumbnail`، فالبديل هو ما يظهر على **كل** صفحة
 * كورس وفي `og:image` لكل رابط يشارك. وكان البديل المتاح صندوقا رماديا
 * مكتوبا عليه «400 x 250» — علامة نقص من قالب المطور لا صورة منصة.
 *
 * يشغل مرة واحدة، والناتج يرفع مع المستودع:
 *     php scripts/make_course_placeholder.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only.\n"); }
if (!extension_loaded('gd')) exit("GD غير مفعلة.\n");

$root = __DIR__;
while ($root !== dirname($root)) {
    if (is_file($root . '/index.php') && is_dir($root . '/application')) break;
    $root = dirname($root);
}

$W = 800;   // ضعف 400x250 ليبقى حادا على الشاشات المضاعفة
$H = 500;
$out  = $root . '/assets/taqdar/brand/course-placeholder.png';
/* `wordmark.png` لا `icon.png`: الثاني بلاطة مربعة بخلفية كحلية مصمتة،
   فتظهر مربعا داكنا حادا وسط تدرج فاتح. والأول شعار على خلفية شفافة. */
$icon = $root . '/assets/taqdar/brand/wordmark.png';

$im = imagecreatetruecolor($W, $H);
imagesavealpha($im, true);

/* تدرج قطري بين تعبئتي السماوي والنعناعي — وهما تعبئتا الهوية نفسها
   المستعملتان في بطاقات الشاشات، فالبديل يبدو من المنصة لا من قالبها. */
$from = [0xE9, 0xF0, 0xF5];   // --tq-sky-fill
$to   = [0xE8, 0xF4, 0xF1];   // --tq-mint-fill
for ($y = 0; $y < $H; $y++) {
    for ($x = 0; $x < $W; $x += 8) {
        $t = (($x / $W) + ($y / $H)) / 2;
        $c = imagecolorallocate($im,
            (int) round($from[0] + ($to[0] - $from[0]) * $t),
            (int) round($from[1] + ($to[1] - $from[1]) * $t),
            (int) round($from[2] + ($to[2] - $from[2]) * $t));
        imagefilledrectangle($im, $x, $y, $x + 7, $y, $c);
    }
}

/* حلقتان خافتتان بلون العلامة تكسران فراغ السطح بلا أن تنافسا العنوان. */
$ring = imagecolorallocatealpha($im, 0x02, 0x33, 0x31, 118);
imagesetthickness($im, 3);
imageellipse($im, (int) ($W * 0.80), (int) ($H * 0.22), 260, 260, $ring);
imageellipse($im, (int) ($W * 0.16), (int) ($H * 0.86), 200, 200, $ring);

/* شعار المنصة في الوسط، بحجم يقرأ ولا يملأ.
   النسبة تحسب من أبعاد الملف نفسه — الشعار عريض لا مربع، وفرض مربع عليه
   يمططه. */
if (is_file($icon)) {
    $ic = imagecreatefrompng($icon);
    if ($ic) {
        $sw = imagesx($ic);
        $sh = imagesy($ic);
        $dw = (int) round($W * 0.42);
        $dh = (int) round($dw * $sh / $sw);

        imagealphablending($im, true);
        imagecopyresampled($im, $ic,
            (int) round(($W - $dw) / 2), (int) round(($H - $dh) / 2),
            0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($ic);
    }
}

if (!is_dir(dirname($out))) mkdir(dirname($out), 0775, true);
imagepng($im, $out, 9);
imagedestroy($im);

printf("كتبت %s (%s كيلوبايت)\n", str_replace($root . '/', '', $out), round(filesize($out) / 1024, 1));
