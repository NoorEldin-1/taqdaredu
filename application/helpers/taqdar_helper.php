<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * منصة تقدر — دوال الواجهة المشتركة.
 *
 * أهم ما فيه عزل الأرقام. خوارزمية bidi تعيد ترتيب المقاطع حول الفواصل
 * المحايدة لا الحروف، فـ «1 – 2» بمسافات ينعكس إلى «2 – 1» بينما
 * «2026-08-01» بلا مسافات يسلم. لذلك كل رقم يظهر للمستخدم يمر من هنا،
 * بلا استثناء — لأن الاستثناء الواحد هو الذي يظهر في لقطة الشاشة أمام العميل.
 */

if (!defined('TQ_LRI')) {
    define('TQ_LRI', "\u{2066}"); // Left-to-Right Isolate
    define('TQ_PDI', "\u{2069}"); // Pop Directional Isolate
}

if (!function_exists('tq_num')) {
    /**
     * رقم قائم بذاته. يعزل كوحدة واحدة — والنطاق «4:17 – 6:02» وحدة واحدة
     * لا طرفان، فتقسيمه يعكس ترتيبه ويعرض رقما غير المقصود.
     */
    function tq_num($value, $class = '')
    {
        $cls = trim('tq-num ' . $class);
        return '<span class="' . $cls . '">' . TQ_LRI . html_escape((string) $value) . TQ_PDI . '</span>';
    }
}

if (!function_exists('tq_iso')) {
    /** رقم داخل جملة: يعزل كل تتابع رقمي في النص ويعاد النص كوحدة واحدة. */
    function tq_iso($text)
    {
        return preg_replace(
            '/([0-9][0-9:،.,\/\-–%]*[0-9]|[0-9])/u',
            TQ_LRI . '$1' . TQ_PDI,
            (string) $text
        );
    }
}

if (!function_exists('tq_sar')) {
    /** مبلغ بالريال السعودي — السوق سعودي، والعملة لا تخمن في الشاشة. */
    function tq_sar($amount, $decimals = 0)
    {
        $n = number_format((float) $amount, $decimals, '.', ',');
        return '<span class="tq-money">' . TQ_LRI . $n . TQ_PDI . ' ريال</span>';
    }
}

if (!function_exists('tq_pct')) {
    /** نسبة مئوية — تعرض دائما بجوار شريط التقدم، لا وحدها. */
    function tq_pct($value)
    {
        return '<span class="tq-progress__value">' . TQ_LRI . (int) $value . '%' . TQ_PDI . '</span>';
    }
}

if (!function_exists('tq_progress')) {
    /**
     * شريط التقدم بارتفاع 7px ونسبته بجواره إلزاميا.
     * شريط بلا رقم يقول «تقريبا» ولا يقول كم.
     */
    function tq_progress($percent, $label = '')
    {
        $p = max(0, min(100, (int) $percent));
        $aria = $label !== '' ? ' aria-label="' . html_escape($label) . '"' : '';
        return '<div class="tq-progress" role="progressbar" aria-valuenow="' . $p . '"'
            . ' aria-valuemin="0" aria-valuemax="100"' . $aria . '>'
            . '<div class="tq-progress__track"><div class="tq-progress__fill" style="inline-size:' . $p . '%"></div></div>'
            . tq_pct($p)
            . '</div>';
    }
}

if (!function_exists('tq_ring')) {
    /** حلقة تقدم — نفس قاعدة الرقم: النسبة مكتوبة في مركزها. */
    function tq_ring($percent, $size = 120, $stroke = 10, $caption = '')
    {
        $p = max(0, min(100, (int) $percent));
        $r = ($size - $stroke) / 2;
        $c = 2 * M_PI * $r;
        $off = $c * (1 - $p / 100);
        $mid = $size / 2;
        $cap = $caption !== '' ? '<span class="tq-caption">' . html_escape($caption) . '</span>' : '';
        return '<div class="tq-ring" style="inline-size:' . $size . 'px;block-size:' . $size . 'px">'
            . '<svg width="' . $size . '" height="' . $size . '" aria-hidden="true">'
            . '<circle class="tq-ring__track" cx="' . $mid . '" cy="' . $mid . '" r="' . $r . '" stroke-width="' . $stroke . '"/>'
            . '<circle class="tq-ring__fill" cx="' . $mid . '" cy="' . $mid . '" r="' . $r . '" stroke-width="' . $stroke . '"'
            . ' stroke-dasharray="' . round($c, 2) . '" stroke-dashoffset="' . round($off, 2) . '"/>'
            . '</svg>'
            . '<span class="tq-ring__label">' . tq_num($p . '%', 'tq-num--xl') . $cap . '</span>'
            . '</div>';
    }
}

if (!function_exists('tq_badge')) {
    /**
     * شارة حالة — خمس فقط.
     * mastered متقن · progress قيد التقدم · due يقترب موعده · late متأخر · idle لم يبدأ
     * البرتقالي (due) إشارة لا نجاح — والنجاح tealSoft وحده.
     */
    function tq_badge($kind, $text)
    {
        $allowed = ['mastered', 'progress', 'due', 'late', 'idle'];
        $k = in_array($kind, $allowed, true) ? $kind : 'idle';
        return '<span class="tq-badge tq-badge--' . $k . '">' . html_escape($text) . '</span>';
    }
}

if (!function_exists('tq_pastel')) {
    /** يدور عائلات الباستيل الست بثبات، فتبقى القوائم متنوعة ومستقرة. */
    function tq_pastel($index)
    {
        $families = ['mint', 'sky', 'peach', 'lilac', 'rose', 'sand'];
        return $families[((int) $index) % 6];
    }
}

if (!function_exists('tq_since')) {
    /** «منذ ساعتين» — فرق زمني بصيغة عربية سليمة بلا أرقام حيثما أمكن. */
    function tq_since($timestamp)
    {
        $d = time() - (int) $timestamp;
        if ($d < 60)      return 'الآن';
        if ($d < 3600)    return tq_units(intdiv($d, 60), 'دقيقة', 'دقيقتين', 'دقائق');
        if ($d < 86400)   return tq_units(intdiv($d, 3600), 'ساعة', 'ساعتين', 'ساعات');
        if ($d < 2592000) return tq_units(intdiv($d, 86400), 'يوم', 'يومين', 'أيام');
        return tq_units(intdiv($d, 2592000), 'شهر', 'شهرين', 'أشهر');
    }
}

if (!function_exists('tq_units')) {
    /** المثنى والجمع في العربية ليسا s تضاف — «منذ 2 ساعة» خطأ لغوي ظاهر. */
    function tq_units($n, $one, $two, $many)
    {
        if ($n == 1) return 'منذ ' . $one;
        if ($n == 2) return 'منذ ' . $two;
        if ($n <= 10) return 'منذ ' . TQ_LRI . $n . TQ_PDI . ' ' . $many;
        return 'منذ ' . TQ_LRI . $n . TQ_PDI . ' ' . $one;
    }
}

if (!function_exists('tq_asset')) {
    /** أصل من ثيم تقدر مع بصمة تبطل الذاكرة المؤقتة عند التعديل. */
    function tq_asset($path)
    {
        $rel = 'assets/taqdar/' . ltrim($path, '/');
        $abs = FCPATH . $rel;
        $v = is_file($abs) ? filemtime($abs) : 1;
        return base_url($rel) . '?v=' . $v;
    }
}

if (!function_exists('tq_active')) {
    /** يضع aria-current على عنصر القائمة النشط — الحالة تقرأ لا ترى فقط. */
    function tq_active($name, $current)
    {
        return $name === $current ? ' aria-current="page"' : '';
    }
}

if (!function_exists('tq_safe_upload_extension')) {
    /**
     * يعيد امتدادا آمنا للحفظ، أو false إن كان ممنوعا.
     *
     * قائمة بيضاء لا سوداء: القائمة السوداء تنسى فيها صيغة (`phar`، `phtml`،
     * `php7`، `shtml`…) فتصير ثغرة، والبيضاء تخطئ في اتجاه المنع لا الإباحة.
     */
    function tq_safe_upload_extension($original_name)
    {
        $allowed = array(
            // مستندات
            'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'rtf', 'csv',
            // صور
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
            // صوت وفيديو
            'mp3', 'wav', 'ogg', 'm4a', 'mp4', 'webm', 'mov', 'mkv',
            // حزم
            'zip', 'rar', '7z',
        );

        $ext = strtolower(trim(pathinfo((string) $original_name, PATHINFO_EXTENSION)));
        if ($ext === '' || !in_array($ext, $allowed, true)) {
            return false;
        }
        return $ext;
    }
}

if (!function_exists('tq_grade_visible')) {
    /**
     * أتعرض درجة هذه المحاولة لصاحبها؟
     *
     * نفس قاعدة `Taqdar_marking_model::student_view()` مطبقة على القراءة
     * المباشرة من `quiz_results` — فلا يفترق رقم يراه الطالب عن رقم يراه
     * وليه. ووجودها هنا لا في كل عرض يمنع أن تنسى في شاشة فتتسرب منها.
     */
    function tq_grade_visible($row)
    {
        if (!is_array($row)) return false;
        if (isset($row['is_submitted']) && (int) $row['is_submitted'] !== 1) return false;
        if (!empty($row['approved_at'])) return true;

        $CI = get_instance();
        $CI->load->model('taqdar_marking_model');
        return !$CI->taqdar_marking_model->has_manual_questions(
            isset($row['quiz_id']) ? (int) $row['quiz_id'] : 0
        );
    }
}
