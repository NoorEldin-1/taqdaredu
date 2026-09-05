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
    /**
     * رقم داخل جملة: يعزل كل تتابع رقمي في النص ويعاد النص كوحدة واحدة.
     *
     * TQ-PCT-FLIP — والعلامة الطرفية تدخل العزل معه.
     * كان النمط يشترط أن **ينتهي المطابق برقم** (`[0-9]...[0-9]`)، فـ«60%»
     * يعزل منه «60» وحده وتبقى `%` خارجه. وهي عند يونيكود من صنف
     * European Terminator: محايدة تتبع اتجاه الفقرة — والفقرة عربية، فتقفز
     * إلى يسار الرقم ويقرأ المعلم «%60». ولا خطأ يظهر: النص صحيح في
     * القاعدة، والانقلاب في الرسم وحده. فصارت `%` تدخل العزل طرفا مسموحا.
     */
    function tq_iso($text)
    {
        return preg_replace(
            '/([0-9][0-9:،.,\/\-–%]*[0-9]%?|[0-9]%?)/u',
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
        return '<span class="tq-money">' . TQ_LRI . $n . TQ_PDI . t(' ريال</span>');
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
        if ($d < 60)      return t('الآن');
        if ($d < 3600)    return tq_units(intdiv($d, 60), t('دقيقة'), t('دقيقتين'), t('دقائق'));
        if ($d < 86400)   return tq_units(intdiv($d, 3600), t('ساعة'), t('ساعتين'), t('ساعات'));
        if ($d < 2592000) return tq_units(intdiv($d, 86400), t('يوم'), t('يومين'), t('أيام'));
        return tq_units(intdiv($d, 2592000), t('شهر'), t('شهرين'), t('أشهر'));
    }
}

if (!function_exists('tq_units')) {
    /** المثنى والجمع في العربية ليسا s تضاف — «منذ 2 ساعة» خطأ لغوي ظاهر. */
    function tq_units($n, $one, $two, $many)
    {
        if ($n == 1) return t('منذ ') . $one;
        if ($n == 2) return t('منذ ') . $two;
        if ($n <= 10) return t('منذ ') . TQ_LRI . $n . TQ_PDI . ' ' . $many;
        return t('منذ ') . TQ_LRI . $n . TQ_PDI . ' ' . $one;
    }
}

if (!function_exists('tq_csrf')) {
    /**
     * حقل رمز CSRF المخفي — يوضع داخل كل نموذج يكتب.
     *
     * `csrf_protection` صار `TRUE` في `config.php`، وشرطه أن يحمل **كل** جسم
     * POST مفتاح `csrf_token_name`. ونماذج البوابات مكتوبة بيدها لا بـ
     * `form_open()`، فلا يحقن فيها CodeIgniter شيئا: كل حفظ وربط وإرسال في
     * بوابة ولي الأمر كان يرد `403 The action you have requested is not
     * allowed` بصفحة إنجليزية خام — لا رسالة عربية، ولا حفظ، ولا تفسير.
     *
     * والحقل هنا لا في سكربت وحده: نموذج يعتمد على JS ليمر يسقط عند من
     * أوقف JS أو عند فشل تحميل الملف، وسقوطه صامت. والسكربت في
     * `includes_bottom.php` يبقى شبكة أمان لنماذج المنصة الأخرى.
     *
     * ويرجع نصا فارغا حين تكون الحماية موقوفة، فلا يزرع حقلا لا يقرأ.
     */
    function tq_csrf()
    {
        if (!config_item('csrf_protection')) return '';

        $CI = get_instance();
        if (!isset($CI->security)) return '';

        return '<input type="hidden" name="' . html_escape($CI->security->get_csrf_token_name())
             . '" value="' . html_escape($CI->security->get_csrf_hash()) . '">';
    }
}

if (!function_exists('tq_count_units')) {
    /**
     * عدد بصيغته العربية السليمة — بلا «منذ».
     *
     * `tq_units` أختها تبدأ بـ«منذ» دائما لأنها كتبت لـ`tq_since`، فلا تصلح
     * لـ«أنهى ٣ دروس» ولا لـ«خطة ١ أيام». والعربية لا تجمع بإضافة حرف:
     * «١ أيام» و«٢ اختبار» و«٥ درس» أخطاء ظاهرة تقرأ في أول سطر من التقرير.
     *
     * تمييز العدد في العربية أربع حالات لا حالتان:
     *   ٠      صيغة نفي         «لا يوم»
     *   ١      المفرد           «يوم واحد»
     *   ٢      المثنى           «يومان» أو «يومين» — بحسب موقعه
     *   ٣–١٠   جمع قلة مجرور    «٥ أيام»
     *   ١١+    مفرد منصوب       «٢٩ يوما»
     *
     * والمثنى وحده يتغير بموقعه من الجملة، ولا يغني فيه شكل واحد:
     *   «نشاطه نزل من **يومين**»   بعد حرف جر → مجرور
     *   «بقي من الأسبوع **يومان**» فاعل        → مرفوع
     * فكانت صيغة واحدة تعطي «نزل من يومان» — خطأ يقرؤه كل عربي في أول
     * سطر من التقرير. و`$case` يحسمه: `obl` هو الافتراض لأن أكثر مواضع
     * هذه البوابة بعد حرف جر أو مفعول به.
     *
     * ولا يعزل الرقم هنا: النص يمر بـ`tq_iso()` عند الطباعة فيعزل كل
     * تتابع رقمي مرة واحدة — والعزل مرتين يطبع علامتي عزل متداخلتين
     * (`⁦⁦29⁩⁩`) تظهران مربعين فارغين في بعض الخطوط.
     *
     * @param int    $n            العدد
     * @param string $one          المفرد               «يوم»
     * @param string $two_nom      المثنى المرفوع       «يومان»
     * @param string $two_obl      المثنى المنصوب المجرور «يومين»
     * @param string $many         جمع القلة            «أيام»
     * @param string $singular_acc المفرد المنصوب لما فوق العشرة «يوما»
     * @param string $zero         صيغة الصفر
     * @param string $case         nom مرفوع · obl منصوب أو مجرور (الافتراض)
     * @param bool   $fem          مؤنث؛ فـ«مادة واحدة» لا «مادة واحد»
     */
    function tq_count_units($n, $one, $two_nom, $two_obl, $many,
                            $singular_acc = null, $zero = null, $case = 'obl', $fem = false)
    {
        $n = (int) $n;
        if ($singular_acc === null) $singular_acc = $one;

        if ($n <= 0)  return $zero !== null ? $zero : t('لا ') . $one;
        if ($n === 1) return $one . ($fem ? t(' واحدة') : t(' واحد'));
        if ($n === 2) return $case === 'nom' ? $two_nom : $two_obl;
        if ($n <= 10) return $n . ' ' . $many;
        return $n . ' ' . $singular_acc;
    }
}

if (!function_exists('tq_days')) {
    /** «٥ أيام» · «يومين»/«يومان» · «يوم واحد» · «٢٩ يوما». */
    function tq_days($n, $zero = 'لا يوم', $case = 'obl')
    {
        return tq_count_units($n, t('يوم'), t('يومان'), t('يومين'), t('أيام'), t('يوما'), $zero, $case);
    }
}

if (!function_exists('tq_lessons_word')) {
    /** «٤ دروس» · «درسين»/«درسان» · «درس واحد» · «٣٥ درسا». */
    function tq_lessons_word($n, $zero = 'لا دروس', $case = 'obl')
    {
        return tq_count_units($n, t('درس'), t('درسان'), t('درسين'), t('دروس'), t('درسا'), $zero, $case);
    }
}

if (!function_exists('tq_homework_word')) {
    /** «٤ واجبات» · «واجبين»/«واجبان» · «واجب واحد» · «١٢ واجبا». */
    function tq_homework_word($n, $zero = 'لا واجبات', $case = 'obl')
    {
        return tq_count_units($n, t('واجب'), t('واجبان'), t('واجبين'), t('واجبات'), t('واجبا'), $zero, $case);
    }
}

if (!function_exists('tq_minutes_word')) {
    /** «٤٥ دقيقة» · «دقيقتين»/«دقيقتان» · «دقيقة واحدة» · «٥ دقائق». مؤنث. */
    function tq_minutes_word($n, $zero = 'لا دقائق', $case = 'obl')
    {
        return tq_count_units($n, t('دقيقة'), t('دقيقتان'), t('دقيقتين'), t('دقائق'), t('دقيقة'), $zero, $case, true);
    }
}

if (!function_exists('tq_students_word')) {
    /** «٤ طلاب» · «طالبين»/«طالبان» · «طالب واحد» · «١٢ طالبا». */
    function tq_students_word($n, $zero = 'لا طلاب', $case = 'obl')
    {
        return tq_count_units($n, t('طالب'), t('طالبان'), t('طالبين'), t('طلاب'), t('طالبا'), $zero, $case);
    }
}

if (!function_exists('tq_sessions_word')) {
    /** «٤ حصص» · «حصتين»/«حصتان» · «حصة واحدة» · «١٢ حصة». مؤنث. */
    function tq_sessions_word($n, $zero = 'لا حصص', $case = 'obl')
    {
        return tq_count_units($n, t('حصة'), t('حصتان'), t('حصتين'), t('حصص'), t('حصة'), $zero, $case, true);
    }
}

if (!function_exists('tq_exams_word')) {
    /** «٤ اختبارات» · «اختبارين»/«اختباران» · «اختبار واحد» · «١٢ اختبارا». */
    function tq_exams_word($n, $zero = 'لا اختبارات', $case = 'obl')
    {
        return tq_count_units($n, t('اختبار'), t('اختباران'), t('اختبارين'), t('اختبارات'), t('اختبارا'), $zero, $case);
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

if (!function_exists('tq_course_owner_ids')) {
    /**
     * معرفات معلمي مجموعة كورسات، مفكوكة من `course.user_id` و`course.creator`.
     *
     * `course.user_id` **قائمة معرفات مفصولة بفواصل** («148,289») لا معرفا
     * واحدا، ومعه `creator` للمنشئ — وهو نموذج الملكية المعتمد في
     * `Taqdar_teacher_model::owns_course()` و`Taqdar::teacher_owns_course()`
     * كليهما (`creator = ? OR FIND_IN_SET(?, user_id)`).
     *
     * و`(int)` على تلك القائمة تقرأ أولها وتسقط الباقي بلا خطأ ظاهر: كان
     * ذلك يسقط كل معلم ثان في كورس مشترك من قائمة من يجوز للطالب مراسلتهم
     * في `tq_messages.php` — فيقال للطالب عن معلمه إنه ليس من معلميه.
     *
     * @param array $rows صفوف فيها `user_id` وقد يكون فيها `creator`
     * @return int[] معرفات فريدة موجبة
     */
    function tq_course_owner_ids($rows)
    {
        $out = array();
        foreach ((array) $rows as $r) {
            foreach (array('user_id', 'creator') as $col) {
                if (!isset($r[$col])) continue;
                foreach (explode(',', (string) $r[$col]) as $one) {
                    $one = (int) trim($one);
                    if ($one > 0) $out[$one] = true;
                }
            }
        }
        return array_keys($out);
    }
}

if (!function_exists('tq_uploaded')) {
    /**
     * هل رفع ملف بهذا الاسم فعلا؟
     *
     * TQ-FILES-BLIND — الشيفرة الموروثة تقرأ `$_FILES['x']['name']`
     * مباشرة. و`$_FILES` لا يحمل إلا ما أرسل: نموذج تحرير الكورس صار
     * تبويبات، فحقل «صورة الكورس القادم» يعرض في تبويب «الأساسيات»
     * وحده — وحفظ «التسعير» يقرأ مفتاحا غير موجود فيطبع تحذيري PHP في
     * كل حفظ. والتحذير مطبوع **قبل** `redirect()`، فمع أول ضبط يمنع
     * الترويسات بعد المخرجات يتحول إلى انهيار.
     *
     * وتفحص `error` مع الاسم: خانة ملف تجاوز حده في PHP تصل باسم ملء
     * وبـ`UPLOAD_ERR_INI_SIZE` وبمسار مؤقت فارغ، فالنقل يفشل صامتا.
     */
    function tq_uploaded($field)
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return false;

        $f = $_FILES[$field];
        if (!isset($f['name']) || is_array($f['name'])) return false;   /* المتعدد يفحص بيده */

        return (string) $f['name'] !== ''
            && (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }
}

/* ══════════════════════════════════════════════════════════════════════
   TQ-QIMG · صورة داخل السؤال

   المعادلة والرسم البياني ولقطة الشاشة لا تكتب حروفا. وكان بنك الأسئلة
   نصا خالصا، فسؤال رياضيات عن كسر مركب يكتب «(٣س+٢)/(س-١)» في سطر واحد
   بلا بسط ولا مقام، وسؤال عن رسم بياني يوصف بالكلام. والمعلم الذي عنده
   الصورة جاهزة في كتابه يعيد رسمها بالنص أو يترك السؤال.

   ثلاث دوال يشترك فيها الاختبار التشخيصي (`tq_diag_questions`) واختبار
   ما بعد الدرس (`question`): الرفع، والحذف، والعرض.
   ══════════════════════════════════════════════════════════════════════ */

if (!function_exists('tq_qimage_dir')) {
    /** مجلد صور الأسئلة — ينشأ عند أول رفع. */
    function tq_qimage_dir()
    {
        $dir = FCPATH . 'uploads/question_images/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}

if (!function_exists('tq_qimage_upload')) {
    /**
     * يرفع صورة سؤال ويعيد اسم الملف المحفوظ — أو `''` إن لم يرفع شيء،
     * أو `false` إن رفض.
     *
     * **الصور وحدها.** `tq_safe_upload_extension` تبيح المستند والصوت
     * والحزمة أيضا، وهي قائمة المرفقات العامة — وحقل يقول «صورة» ويقبل
     * `zip` يجعل السؤال يعرض رابطا مكسورا. و`svg` مستثنى: ملف نصي يحمل
     * `<script>`، ويعرض هنا داخل `<img>` من نطاقنا نفسه.
     *
     * والاسم يولد ولا يؤخذ من المستخدم: اسم الملف الوارد يحمل مسارات
     * وحروفا لا تصلح، ويكشف ما في جهاز رافعه.
     */
    function tq_qimage_upload($field)
    {
        if (!tq_uploaded($field)) return '';

        $f   = $_FILES[$field];
        $ext = tq_safe_upload_extension($f['name']);
        if ($ext === false || !in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) {
            return false;
        }

        /* والامتداد وحده لا يكفي: ملف اسمه `.png` وليس صورة يمر منه.
           و`getimagesize` تقرأ الترويسة نفسها. */
        $info = @getimagesize($f['tmp_name']);
        if ($info === false) return false;

        /* حد الحجم هنا لا في `php.ini` وحده: ملف يتجاوزه يصل بخطأ
           `UPLOAD_ERR_INI_SIZE` فتصده `tq_uploaded` قبل هذا السطر،
           وهذا للحد الذي نختاره نحن دون حد الخادم. */
        if ((int) $f['size'] > 4 * 1024 * 1024) return false;

        $name = 'q' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!@move_uploaded_file($f['tmp_name'], tq_qimage_dir() . $name)) return false;

        return $name;
    }
}

if (!function_exists('tq_qimage_delete')) {
    /**
     * يحذف صورة سؤال.
     *
     * `basename` عقد لا زينة: القيمة تأتي من عمود في القاعدة، ولو حمل
     * يوما `../../` لحذف الحذف ما ليس في المجلد.
     */
    function tq_qimage_delete($name)
    {
        $name = basename((string) $name);
        if ($name === '' || $name === '.' || $name === '..') return;

        $path = tq_qimage_dir() . $name;
        if (is_file($path)) @unlink($path);
    }
}

if (!function_exists('tq_qimage_url')) {
    /** رابط صورة السؤال — أو `''` إن لم يبق لها ملف. */
    function tq_qimage_url($name)
    {
        $name = basename((string) $name);
        if ($name === '') return '';
        if (!is_file(tq_qimage_dir() . $name)) return '';

        return base_url('uploads/question_images/' . rawurlencode($name));
    }
}

if (!function_exists('tq_qimage_tag')) {
    /**
     * وسم صورة السؤال كما تعرض للطالب وللمعلم.
     *
     * `alt` فارغة عمدا: الصورة **هي** السؤال لا زينة له، ووصفها بنص
     * بديل يعني كتابة السؤال مرتين — ومن قرأها بقارئ شاشة سمع النص
     * ثم سمع وصفا يقول الشيء نفسه أو يفشي الجواب.
     */
    function tq_qimage_tag($name, $cls = 'tq-qimg')
    {
        $url = tq_qimage_url($name);
        if ($url === '') return '';

        return '<img class="' . html_escape($cls) . '" src="' . html_escape($url) . '"'
             . ' alt="" loading="lazy" decoding="async">';
    }
}

if (!function_exists('tq_qimage_ensure')) {
    /**
     * يضمن وجود عمود `image` في جدول أسئلة.
     *
     * `tq_diag_questions` له `ensure_schema()` تنشئه، و`question` جدول
     * موروث من Academy لا هجرة له ولا منشئ. ولا هجرات في هذا المستودع
     * والنشر `git reset --hard`، فالعمود يضاف من الشيفرة أو لا يضاف —
     * وملف SQL يستورد بيد ينسى، فتسقط الشاشة عند من نشر ولم يستورد.
     *
     * ويفحص مرة واحدة لكل طلب: `field_exists` استعلام، وتكراره على كل
     * سؤال في كل اختبار عمل ضائع.
     */
    function tq_qimage_ensure($table = 'question')
    {
        static $done = array();
        if (isset($done[$table])) return $done[$table];

        $ci = &get_instance();
        try {
            /* الخبيئة تفرغ قبل الفحص: CodeIgniter يحفظ أسماء أعمدة كل
               جدول في الطلب الواحد، فمن قرأ الجدول قبل هذا السطر يعطي
               قائمة بائتة فيعاد `ADD COLUMN` على عمود قائم. */
            $ci->db->data_cache = array();
            if (!$ci->db->field_exists('image', $table)) {
                $ci->db->query('ALTER TABLE `' . $table . '`
                                ADD COLUMN `image` varchar(190) DEFAULT NULL');
            }
            $done[$table] = true;
        } catch (Throwable $e) {
            log_message('error', 'TQ-QIMG: تعذر اضافة عمود الصورة على ' . $table . ' — ' . $e->getMessage());
            $done[$table] = false;
        }
        return $done[$table];
    }
}

/* =====================================================================
   TQ-URLESC · روابط الوسائط تخزن كما كتبت
   ===================================================================== */

if (!function_exists('tq_clean_url')) {
    /**
     * ينظف رابط وسائط قبل تخزينه — ولا يهربه.
     *
     * `Crud_model` كان يكتب `html_escape($this->input->post('video_url'))`
     * في ستة مواضع. والرابط ليس نصا يعرض في صفحة، هو **قيمة تفكك**:
     * يقرؤها محلل الرابط في المتصفح ويقرأ منها المشغل معرف الفيديو. فـ`&`
     * تصير `&amp;` في القاعدة، ويقرأ المشغل معاملا اسمه `amp;list`.
     *
     * وأسوأ منه أن الحفظ كان **يهرب المهرب**: من فتح درسا وحفظه بلا تعديل
     * صار رابطه `&amp;amp;`. والتهريب موضعه العرض: القالب يهرب حين يطبع.
     *
     * وما يفعله هذا بدلا منه: يفك ما هرب سابقا، ويقص المسافات وحروف
     * التحكم، ويرفض ما ليس بروتوكولا صالحا — فلا يخزن `javascript:` في
     * عمود يوضع في `src`.
     *
     * @param  string $raw ما كتبه صاحبه
     * @return string الرابط نظيفا، أو '' إن لم يكن رابطا مقبولا
     */
    function tq_clean_url($raw)
    {
        $s = trim((string) $raw);
        if ($s === '') return '';

        /* يفك حتى يستقر: مرة واحدة لا تكفي — `&amp;amp;` تصير `&amp;`. */
        for ($i = 0; $i < 8; $i++) {
            $next = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $s) break;
            $s = $next;
        }

        /* حروف التحكم تحذف: سطر داخل قيمة توضع في سمة HTML يكسر الوسم. */
        $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s);
        $s = trim($s);
        if ($s === '') return '';

        /* المسار النسبي والرابط بلا بروتوكول يمران: الملف المرفوع يخزن
           بمساره عندنا، لا بعنوان كامل. */
        if ($s[0] === '/' || strpos($s, '//') === 0) return $s;
        if (!preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*:~', $s)) return $s;

        /* البروتوكول معلن: قائمة سماح لا منع — `javascript:` و`data:`
           تدخلان أي قائمة منع تنسى واحدا منهما. */
        $scheme = strtolower(strstr($s, ':', true));
        return in_array($scheme, array('http', 'https'), true) ? $s : '';
    }
}

/* =====================================================================
   بكسل ميتا — قياس الإعلان
   ===================================================================== */

if (!defined('TQ_META_PIXEL_DEFAULT')) {
    /**
     * معرف البكسل الذي سلمه فريق التسويق. وهو **ليس سرا**: يطبع في وسم
     * كل صفحة يقرؤها كل زائر، ويقرأ من أدوات المتصفح في ثانية. فمكانه
     * الشيفرة كالنص الافتراضي في القالب، لا `taqdar_secret.php`.
     */
    define('TQ_META_PIXEL_DEFAULT', '1616041446836504');
}

if (!function_exists('tq_meta_pixel_id')) {
    /**
     * المعرف الفعال. والمفتاح في `settings` يعلو على الافتراضي أعلاه،
     * بقاعدة تفرق بين حالين يخلطهما `empty()`:
     *
     *   لا صف أصلا (NULL)   ⇐ لم يضبط أحد شيئا  ⇐ الافتراضي
     *   صف بقيمة فارغة ('') ⇐ مسؤول مسحه عمدا   ⇐ لا بكسل
     *
     * فبلا هذا التفريق لا يوجد سبيل إلى **إيقاف** البكسل من اللوحة:
     * كل مسح يعيده من الشيفرة، ومن يطفئه يجده يعمل.
     */
    function tq_meta_pixel_id()
    {
        $v = get_settings('tq_meta_pixel_id');
        if ($v === null) $v = TQ_META_PIXEL_DEFAULT;

        /* أرقام وحدها: القيمة تدخل حرفيا في سلسلة جافاسكربت وفي رابط
           صورة، فما ليس رقما لا يكتب أصلا. */
        $v = preg_replace('/\D+/', '', (string) $v);
        return $v !== '' ? $v : '';
    }
}

if (!function_exists('tq_meta_pixel')) {
    /**
     * وسم البكسل — يطبع **مرة واحدة لكل صفحة**.
     *
     * وهذا الشرط ليس حرصا زائدا: `fbq('init')` مرتين بالمعرف نفسه يسجل
     * زيارتين لكل زائر، فيرى فريق الإعلان ضعف الزيارات ونصف نسبة
     * التحويل — والرقم يبدو معقولا فلا يشك فيه أحد. وقد وصلت القصاصة
     * من العميل **مكررة** حرفا بحرف، وهي أول طريق إلى ذلك الخطأ.
     *
     * والرفض يحترم: من ضغط «رفض غير الضروري» في شريط الارتباط لا يحمل
     * له السكربت أصلا — والشريط يقول ذلك، فبلا أثر يصير الزر تمثيلا.
     * وشرط `denied` لا `!== 'accepted'`: الشريط قد يكون مطفأ من اللوحة،
     * فلا قرار يكتب أبدا، فينتظر البكسل موافقة لا تجيء.
     */
    function tq_meta_pixel()
    {
        static $printed = false;
        if ($printed) return '';

        $id = tq_meta_pixel_id();
        if ($id === '') return '';
        $printed = true;

        ob_start(); ?>
<!-- Meta Pixel Code -->
<script>
/* TQ-PERF-PIXEL — البكسل ينتظر اكتمال الرسم، ولا يزاحمه.

   كان ينفذ وقت التحليل في الراس: ١٩١ ك.ب من نطاق خارجي و٣٣٥ جزءا من
   الالف على الخيط الرئيسي — اكبر سبب منفرد لتاخر الاستجابة، قبل ان
   يرى الزائر حرفا. وهو قياس تسويقي لا يرسم شيئا، فلا وجه لسبقه المحتوى.

   والتاجيل الى `load` لا يفقد شيئا عمليا: `PageView` يسجل بعد اكتمال
   الصفحة باجزاء من الثانية، ولا يضيع الا زائر يغادر قبل ان تكتمل — وهو
   زائر لم ير الصفحة اصلا. ولم يؤجل الى اول تفاعل عمدا: ذلك يسقط كل من
   يفتح ويقرا ويغادر، وهم شريحة معتبرة في قياس الاعلانات.

   و`requestIdleCallback` بمهلة، و`setTimeout` بديلا لسفاري. والحارس
   `tq-cookie === 'denied'` كما هو، ويقرا مبكرا كي لا يجدول شيئا اصلا. */
(function () {
    try { if (localStorage.getItem('tq-cookie') === 'denied') return; } catch (e) {}

    function start() {
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');

        fbq('init', '<?php echo $id; ?>');
        fbq('track', 'PageView');
    }

    function later() {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(start, { timeout: 2500 });
        } else {
            setTimeout(start, 900);
        }
    }

    if (document.readyState === 'complete') later();
    else window.addEventListener('load', later, { once: true });
})();
</script>
<noscript><img height="1" width="1" style="display:none" alt=""
src="https://www.facebook.com/tr?id=<?php echo $id; ?>&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
<?php
        return ob_get_clean();
    }
}

if (!function_exists('tq_meta_track')) {
    /**
     * حدث تحويل — يطبع في الصفحة التي وقع فيها الحدث لا في كل صفحة.
     *
     *   echo tq_meta_track('Purchase', array('value' => 299, 'currency' => 'SAR'));
     *
     * ولا يطبع شيئا إن كان البكسل مطفأ، ولا يبني `fbq` بنفسه: هو يفترض
     * أن `tq_meta_pixel()` سبقه في الوسم — وهي في الرأس، فأي موضع في
     * المتن بعدها. والحارس `window.fbq` يبقى: من رفض الارتباط لا تعرف
     * صفحته `fbq` أصلا، ونداء دالة غير موجودة يوقف بقية سكربت الصفحة.
     */
    function tq_meta_track($event, $params = array())
    {
        if (tq_meta_pixel_id() === '') return '';

        $event = preg_replace('/[^A-Za-z0-9_]/', '', (string) $event);
        if ($event === '') return '';

        $json = json_encode($params ? $params : new stdClass(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

        return "<script>window.fbq && fbq('track', '" . $event . "', " . $json . ");</script>\n";
    }
}


if (!function_exists('tq_filterbar')) {
    /**
     * TQ-FILTERBAR — شريط الترشيح: مكون واحد للوحات الثلاث.
     *
     * ═══ لماذا مكون ═══
     *
     * ثماني شاشات في البوابات الثلاث تسأل السؤال نفسه («أي جزء من هذه
     * القائمة تريد؟») وتكتب جوابه ثماني مرات: `tq_materials` و
     * `tq_favourites` و`tq_tasks` و`tq_exams` و`tq_notifications`
     * و`tq_messages` وأختاهما عند المعلم. وكل نسخة اختلفت عن أختها في
     * شيء — هذه تلغي حدها السفلي بـ`style` سطري، وتلك تضيق فجوتها،
     * وثالثة تلف نفسها في `.tq-s-tabs` فتأخذ شريط تمرير النظام. فبقيت
     * ثمانية شرائط لا يقول أحدها إنه أخو الآخر.
     *
     * ═══ ثلاثة قرارات في التصميم ═══
     *
     * ١ — **قطاعات على مسار غائر، لا خط تحت النص.** الخط السفلي يقرأ
     *     من مسافة **حدا** لا اختيارا، ولا يقول أين ينتهي أحد التبويبات
     *     ويبدأ الآخر — فمن يشير بإصبعه لا يعرف أين يضغط. والقطاع
     *     المرتفع (أبيض على غائر) هدف نقر ظاهر الحدود.
     *
     * ٢ — **المختار لا يلبس الأخضر.** قاعدة الـDS: «بيان أخضر واحد في
     *     الشاشة»، والزر الذي يخرج من الحالة الفارغة أحق به من تبويب
     *     يقول أين أنت. فالمختار **يرتفع** ولا يشتعل: سطح أبيض وظل
     *     وحبر داكن — والأخضر يبقى في عداده وحده.
     *
     * ٣ — **العدد جزء من التبويب لا ملحق به.** وصفر يقال هامسا: تبويب
     *     بلا محتوى يبقى (اختفاؤه يجعل الشريط يتغير بين زيارتين فيبحث
     *     صاحبه عما رآه أمس) لكنه لا يزاحم بصر من يمسح الشريط.
     *
     * ═══ والتمرير يعلن عن نفسه بلا سكربت ═══
     *
     * سبعة تبويبات على جوال لا تسع، و`overflow-x: auto` يخفي ما زاد
     * **بلا أن يقول**. والورقة تحل هذه بخدعة `background-attachment`:
     * غطاءان يتحركان مع المحتوى وظلان يثبتان عند الحافتين، فيظهر الظل
     * متى كان وراءه شيء ويختفي عند الطرف. بلا سطر جافاسكربت، وبلا
     * مراقب حجم، وبلا شريط تمرير من النظام وسط تصميم.
     *
     * @param array  $tabs  كل بند: url · label · count (أو null) · active
     * @param string $label وصف الشريط لقارئ الشاشة
     */
    function tq_filterbar(array $tabs, $label = '')
    {
        $out = '<nav class="tq-filterbar" aria-label="' . html_escape($label) . '">'
             . '<div class="tq-filterbar__track">';

        foreach ($tabs as $t) {
            $on    = !empty($t['active']);
            $n     = array_key_exists('count', $t) ? $t['count'] : null;
            $has_n = $n !== null;
            $zero  = $has_n && (int) $n === 0;

            $out .= '<a class="tq-filterbar__seg" href="' . html_escape((string) $t['url']) . '"'
                  . ($on ? ' aria-current="page"' : '')
                  . ($has_n ? ' data-count="' . (int) $n . '"' : '')
                  . ($zero ? ' data-empty="1"' : '')
                  . '>'
                  . '<span class="tq-filterbar__label">' . html_escape((string) $t['label']) . '</span>';

            if ($has_n) {
                $out .= '<span class="tq-filterbar__n">' . TQ_LRI . (int) $n . TQ_PDI . '</span>';
            }
            $out .= '</a>';
        }

        return $out . '</div></nav>';
    }
}
