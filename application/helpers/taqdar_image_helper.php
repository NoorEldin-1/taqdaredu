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

        if (!is_array($file) || !isset($file['error'])) return $fail(t('لم يصل ملف.'));

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            /* الرفع الفاشل يصل بـ`name` مملوءا و`tmp_name` فارغا —
               فبلا هذا الفرع يحفظ الصف باسم ملف لم ينسخ. */
            if (in_array((int) $file['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
                return $fail(t('حجم الصورة أكبر مما يقبله الخادم. اختر صورة أصغر.'));
            }
            if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) return $fail(t('لم تختر صورة.'));
            return $fail(t('تعذر رفع الصورة. حاول مرة أخرى.'));
        }

        if ((int) $file['size'] > $maxmb * 1024 * 1024) {
            return $fail(t('حجم الصورة أكبر من ') . rtrim(rtrim(number_format($maxmb, 1), '0'), '.') . t(' ميغابايت.'));
        }

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) return $fail(t('مصدر الملف غير مقبول.'));

        $src = tq_img_read($tmp);
        if (!$src) return $fail(t('هذا ليس ملف صورة مقروءا. المقبول: JPG · PNG · WebP · GIF.'));

        if ($src['w'] < $minw || $src['h'] < $minh) {
            /* صورة صغيرة تكبر إلى حجم البطاقة فتخرج مهترئة، والرافع
               يراها سليمة في اللوحة ولا يراها الزائر كذلك. فترد
               بمقاسها المطلوب لا برفض غامض. */
            imagedestroy($src['im']);
            return $fail(t('الصورة صغيرة (') . $src['w'] . '×' . $src['h'] . t(') وتظهر مهترئة في البطاقة. ')
                       . t('أقل مقاس ') . $minw . '×' . $minh . t('، والأفضل ') . $tw . '×' . $th . '.');
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

        if ($bytes === '' || $bytes === false) return $fail(t('تعذر معالجة الصورة.'));

        $name = $prefix . '-' . substr(sha1($bytes), 0, 12) . '.' . $ext;
        $dir  = tq_img_dir($bucket);
        if (!is_dir($dir) || !is_writable($dir)) {
            return $fail(t('مجلد الرفع غير قابل للكتابة: uploads/') . $bucket);
        }
        if (@file_put_contents($dir . $name, $bytes) === false) {
            return $fail(t('تعذر حفظ الصورة على الخادم.'));
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

if (!function_exists('tq_doc_dir')) {
    /** مجلد الرفع لدلو، ينشأ إن لم يكن — وهو `tq_img_dir()` نفسه بابا. */
    function tq_doc_dir($bucket)
    {
        $bucket = preg_replace('/[^a-z0-9_-]/i', '', (string) $bucket);
        if ($bucket === '') $bucket = 'docs';
        $dir = FCPATH . 'uploads/' . $bucket . '/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}

if (!function_exists('tq_doc_store')) {
    /**
     * TQ-BOOK-FILE — يخزن مستندا مرفوعا (PDF) بعد فحص **محتواه**.
     *
     * وهو أخو `tq_img_store()` لا نسخة منه: الصورة تعالج وتقص وتعاد
     * ترميزا، والمستند يخزن كما جاء — لكن الفحص واحد في مبدئه:
     *
     * · **المحتوى لا الامتداد.** ملف اسمه `book.pdf` وأوله `<?php` هو
     *   شيفرة تنفذ لو وضعت في مجلد يخدم. فالبصمة تقرأ من أول البايتات
     *   (`%PDF-`)، والامتداد يشتق منها لا من اسم الرافع.
     *
     * · **والاسم بصمة محتواه** كما في الصور: فلا يعرض كاش متصفح ولا
     *   LiteSpeed ملفا قديما بعد استبدال، ورفع الملف نفسه مرتين لا
     *   يترك نسختين.
     *
     * · **والحجم يحد.** كتاب المنهج عشرات الميغابايتات، وحد الصور
     *   (ثمانية) يرد كتابا صحيحا. فالافتراض هنا أربعون.
     *
     * @param  array $file صف من `$_FILES`
     * @param  array $o    bucket · max_mb
     * @return array ok · path · error · size
     */
    function tq_doc_store($file, $o = array())
    {
        $bucket = isset($o['bucket']) ? $o['bucket'] : 'books';
        $prefix = isset($o['prefix']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $o['prefix']) : 'doc';
        $maxmb  = isset($o['max_mb']) ? (float) $o['max_mb'] : 40;

        $fail = function ($msg) { return array('ok' => false, 'path' => '', 'size' => 0, 'error' => $msg); };

        if (!is_array($file) || !isset($file['error'])) return $fail(t('لم يصل ملف.'));

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            if (in_array((int) $file['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
                return $fail(t('حجم الملف أكبر مما يقبله الخادم. اضغط الملف أو ارفعه مجزأ.'));
            }
            if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) return $fail(t('لم تختر ملفا.'));
            return $fail(t('تعذر رفع الملف. حاول مرة أخرى.'));
        }

        if ((int) $file['size'] > $maxmb * 1024 * 1024) {
            return $fail(t('حجم الملف أكبر من ') . rtrim(rtrim(number_format($maxmb, 1), '0'), '.')
                       . t(' ميغابايت.'));
        }

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) return $fail(t('مصدر الملف غير مقبول.'));

        /* البصمة من أول البايتات: `%PDF-` هي ما يقرؤه `pdf.js` نفسه،
           وما لا يبدأ بها لن يفتحه القارئ مهما كان امتداده. */
        $head = @file_get_contents($tmp, false, null, 0, 5);
        if ($head === false || strncmp($head, '%PDF-', 5) !== 0) {
            return $fail(t('هذا ليس ملف PDF. القارئ في بوابة الطالب لا يفتح غيره.'));
        }

        $bytes = @file_get_contents($tmp);
        if ($bytes === false || $bytes === '') return $fail(t('تعذرت قراءة الملف المرفوع.'));

        $name = $prefix . '-' . substr(sha1($bytes), 0, 12) . '.pdf';
        $dir  = tq_doc_dir($bucket);
        if (!is_dir($dir) || !is_writable($dir)) {
            return $fail(t('مجلد الرفع غير قابل للكتابة: uploads/') . $bucket);
        }

        $dest = $dir . $name;
        /* الملف نفسه مرفوعا مرتين يعطي المسار نفسه — فلا ينسخ ثانية. */
        if (!is_file($dest) && !@move_uploaded_file($tmp, $dest)) {
            if (@file_put_contents($dest, $bytes) === false) {
                return $fail(t('تعذر حفظ الملف على الخادم.'));
            }
        }
        @chmod($dest, 0644);

        return array('ok' => true, 'path' => 'uploads/' . $bucket . '/' . $name,
                     'size' => (int) filesize($dest), 'error' => '');
    }
}

if (!function_exists('tq_doc_pages')) {
    /**
     * عدد صفحات ملف PDF — يقرأ من الملف لا يكتب بيد.
     *
     * والعد اقتراح لا فرض (TQ-PROBE نفسه): يملأ الحقل الفارغ وحده،
     * وما كتبه صاحبه يبقى. والقراءة بلا مكتبة: `/Type /Page` تحصى في
     * الملف، وهو تقدير يصيب في الأغلب الساحق من ملفات المنهج.
     */
    function tq_doc_pages($rel_path)
    {
        $p = ltrim(trim((string) $rel_path), '/');
        if ($p === '' || strpos($p, 'uploads/') !== 0 || strpos($p, '..') !== false) return 0;

        $full = FCPATH . $p;
        if (!is_file($full)) return 0;

        $raw = @file_get_contents($full);
        if ($raw === false || $raw === '') return 0;

        /* `/Count n` في شجرة الصفحات أدق من حصر `/Type /Page`، فيجرب
           أولا — وأكبر قيمة فيه هي جذر الشجرة. */
        if (preg_match_all('#/Count\s+(\d+)#', $raw, $m) && !empty($m[1])) {
            $n = max(array_map('intval', $m[1]));
            if ($n > 0 && $n < 20000) return $n;
        }
        $n = preg_match_all('#/Type\s*/Page[^s]#', $raw);
        return ($n > 0 && $n < 20000) ? $n : 0;
    }
}
