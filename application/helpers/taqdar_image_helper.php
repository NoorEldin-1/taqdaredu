<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الصور المرفوعة من اللوحة — رفع واحد ينتج ملفا واحدا بمقاس واحد.
 *
 * ----------------------------------------------------------------------
 * TQ-IMG-NORM — لماذا يعالج ما يرفع ولا يخزن كما جاء
 *
 * حقل صورة الباقة كان `select` من مجلد السمة: المسؤول يختار من ملفات
 * رفعها مبرمج بـFTP، فلا يستطيع أن يضع صورة باقته أبدا. وفتحه رفعا
 * يفتح معه ثلاث مشكلات لا تظهر إلا عند الزائر:
 *
 * ١ — **المقاس.** بطاقة الباقة صندوق `aspect-ratio: 3/2` و
 *     `object-fit: cover`. فصورة رأسية ٩:١٦ يقتطع منها الصندوق شريطا
 *     أفقيا من وسطها — تخرج رأس الطالب من الكادر ولا يرى الرافع ذلك
 *     في اللوحة. فالقص يقع **عند الرفع** بالنسبة نفسها، ويرى الرافع
 *     ما سيراه الزائر.
 *
 * ٢ — **الوزن.** هاتف حديث يخرج صورة بثمانية ميغابايت، وأربع بطاقات
 *     في الصفحة تعني اثنين وثلاثين ميغابايت على من يفتحها بشبكة
 *     جوال. فتخرج ١٢٠٠×٨٠٠ بصيغة webp — عشرات الكيلوبايتات.
 *
 * ٣ — **ما ليس صورة.** الامتداد لا يثبت شيئا: `x.php` يسمى `x.jpg`
 *     ويرفع. فالفحص بـ`getimagesize()` على المحتوى، والاسم يولد هنا
 *     ولا يؤخذ من الرافع بحال — واسم الرافع قد يحمل `../` أو نقطتين.
 *
 * ----------------------------------------------------------------------
 * والاسم يحمل بصمة محتواه (`plan-12-a3f9c1d2.webp`): استبدال الصورة
 * يكتب اسما جديدا، فلا كاش متصفح ولا كاش LiteSpeed يعرض القديمة —
 * وهي أول ما يشتكى منه بعد كل استبدال («رفعت صورة ولم تتغير»).
 */

if (!function_exists('tq_img_dir')) {
    /** مجلد الرفع، ينشأ عند أول حاجة. `uploads/` خارج git أصلا. */
    function tq_img_dir($bucket)
    {
        $bucket = preg_replace('/[^a-z0-9_-]/i', '', (string) $bucket);
        $dir    = FCPATH . 'uploads/' . $bucket . '/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}

if (!function_exists('tq_img_read')) {
    /**
     * يقرأ الملف صورة GD أيا كانت صيغته. `null` لما ليس صورة.
     *
     * النوع من `getimagesize()` لا من الامتداد: هي تقرأ توقيع الملف،
     * والامتداد نص يكتبه الرافع.
     */
    function tq_img_read($path)
    {
        $info = @getimagesize($path);
        if (!$info || empty($info[0]) || empty($info[1])) return null;

        switch ($info[2]) {
            case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG:  $im = @imagecreatefrompng($path);  break;
            case IMAGETYPE_GIF:  $im = @imagecreatefromgif($path);  break;
            case IMAGETYPE_WEBP:
                $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
                break;
            default: return null;
        }
        if (!$im) return null;
        return array('im' => $im, 'w' => (int) $info[0], 'h' => (int) $info[1], 'type' => (int) $info[2]);
    }
}

if (!function_exists('tq_img_cover')) {
    /**
     * يقص الصورة إلى نسبة الصندوق ثم يقيسها إليه — `object-fit: cover`
     * محسوبة مرة عند الرفع بدل أن تحسب في كل متصفح على صورة كاملة.
     *
     * والقص من **وسط العرض وأعلى الثلث** لا من الوسط تماما: صور
     * الأشخاص تضع الوجه في الثلث العلوي، وقص الوسط يقطع الرؤوس. وهو
     * `object-position: 50% 28%` نفسه المكتوب في بطاقة الباقة.
     */
    function tq_img_cover($im, $sw, $sh, $tw, $th)
    {
        $sr = $sw / max(1, $sh);
        $tr = $tw / max(1, $th);

        if ($sr > $tr) {                 /* المصدر أعرض: يقص من الجانبين */
            $ch = $sh;
            $cw = (int) round($sh * $tr);
            $cx = (int) round(($sw - $cw) / 2);
            $cy = 0;
        } else {                         /* المصدر أطول: يقص من أعلى وأسفل */
            $cw = $sw;
            $ch = (int) round($sw / $tr);
            $cx = 0;
            $cy = (int) round(($sh - $ch) * 0.28);
            if ($cy < 0) $cy = 0;
            if ($cy + $ch > $sh) $cy = $sh - $ch;
        }

        $out = imagecreatetruecolor($tw, $th);
        /* الشفافية تحفظ إن كان المصدر PNG/WebP شفافا؛ وبلا هذين
           السطرين يخرج الشفاف أسود صلبا. */
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $im, 0, 0, $cx, $cy, $tw, $th, $cw, $ch);
        return $out;
    }
}

if (!function_exists('tq_img_store')) {
    /**
     * يستقبل ملفا من `$_FILES` ويخزنه مقيسا مقصوصا، ويرد مساره النسبي.
     *
     * @param array $file  صف من `$_FILES`
     * @param array $o     bucket · w · h · prefix · max_mb · min_w · min_h
     * @return array ok · path (`uploads/…`) · error
     */
    function tq_img_store($file, $o = array())
    {
        $bucket = isset($o['bucket']) ? $o['bucket'] : 'admin';
        $tw     = isset($o['w'])      ? (int) $o['w'] : 1200;
        $th     = isset($o['h'])      ? (int) $o['h'] : 800;
        $prefix = isset($o['prefix']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $o['prefix']) : 'img';
        $maxmb  = isset($o['max_mb']) ? (float) $o['max_mb'] : 8;
        $minw   = isset($o['min_w'])  ? (int) $o['min_w'] : 600;
        $minh   = isset($o['min_h'])  ? (int) $o['min_h'] : 400;

        $fail = function ($msg) { return array('ok' => false, 'path' => '', 'error' => $msg); };

        if (!is_array($file) || !isset($file['error'])) return $fail('لم يصل ملف.');

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            /* الرفع الفاشل يصل بـ`name` مملوءا و`tmp_name` فارغا —
               فبلا هذا الفرع يحفظ الصف باسم ملف لم ينسخ. */
            if (in_array((int) $file['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
                return $fail('حجم الصورة أكبر مما يقبله الخادم. اختر صورة أصغر.');
            }
            if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) return $fail('لم تختر صورة.');
            return $fail('تعذر رفع الصورة. حاول مرة أخرى.');
        }

        if ((int) $file['size'] > $maxmb * 1024 * 1024) {
            return $fail('حجم الصورة أكبر من ' . rtrim(rtrim(number_format($maxmb, 1), '0'), '.') . ' ميغابايت.');
        }

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) return $fail('مصدر الملف غير مقبول.');

        $src = tq_img_read($tmp);
        if (!$src) return $fail('هذا ليس ملف صورة مقروءا. المقبول: JPG · PNG · WebP · GIF.');

        if ($src['w'] < $minw || $src['h'] < $minh) {
            /* صورة صغيرة تكبر إلى حجم البطاقة فتخرج مهترئة، والرافع
               يراها سليمة في اللوحة ولا يراها الزائر كذلك. فترد
               بمقاسها المطلوب لا برفض غامض. */
            imagedestroy($src['im']);
            return $fail('الصورة صغيرة (' . $src['w'] . '×' . $src['h'] . ') وتظهر مهترئة في البطاقة. '
                       . 'أقل مقاس ' . $minw . '×' . $minh . '، والأفضل ' . $tw . '×' . $th . '.');
        }

        $out = tq_img_cover($src['im'], $src['w'], $src['h'], $tw, $th);
        imagedestroy($src['im']);

        /* webp حيث تدعم، وإلا jpeg. والصيغة تتبع الامتداد فلا يخرج
           ملف `.webp` محتواه jpeg — وبعض المتصفحات ترفضه. */
        $webp = function_exists('imagewebp');
        $ext  = $webp ? 'webp' : 'jpg';

        ob_start();
        if ($webp) {
            imagewebp($out, null, 82);
        } else {
            /* jpeg لا يعرف الشفافية، فالشفاف يسطح على أبيض — وإلا خرج أسود. */
            $flat = imagecreatetruecolor($tw, $th);
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $out, 0, 0, 0, 0, $tw, $th);
            imagejpeg($flat, null, 85);
            imagedestroy($flat);
        }
        $bytes = ob_get_clean();
        imagedestroy($out);

        if ($bytes === '' || $bytes === false) return $fail('تعذر معالجة الصورة.');

        $name = $prefix . '-' . substr(sha1($bytes), 0, 12) . '.' . $ext;
        $dir  = tq_img_dir($bucket);
        if (!is_dir($dir) || !is_writable($dir)) {
            return $fail('مجلد الرفع غير قابل للكتابة: uploads/' . $bucket);
        }
        if (@file_put_contents($dir . $name, $bytes) === false) {
            return $fail('تعذر حفظ الصورة على الخادم.');
        }
        @chmod($dir . $name, 0644);

        return array('ok' => true, 'path' => 'uploads/' . $bucket . '/' . $name, 'error' => '');
    }
}

if (!function_exists('tq_img_drop')) {
    /**
     * يحذف ملفا رفع من اللوحة — ولا يمس أصلا من السمة.
     *
     * الشرط `uploads/` ليس زينة: العمود قد يحمل اسم أصل قديما
     * (`plan-basic-primary`) وهو ملف في `assets/` يشترك فيه غير صف —
     * فحذف صف واحد يمحو صورة من كل البطاقات.
     */
    function tq_img_drop($path)
    {
        $p = ltrim(trim((string) $path), '/');
        if ($p === '' || strpos($p, 'uploads/') !== 0) return false;
        if (strpos($p, '..') !== false) return false;

        $full = FCPATH . $p;
        $real = realpath($full);
        $root = realpath(FCPATH . 'uploads');
        if (!$real || !$root || strpos($real, $root) !== 0) return false;
        if (!is_file($real)) return false;

        return @unlink($real);
    }
}
