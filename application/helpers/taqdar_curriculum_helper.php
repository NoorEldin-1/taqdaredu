<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * رسم حقول المنهج — نموذج واحد يطبع في لوحتين.
 *
 * الطلب كان: «نفس الشاشات اللي في الكورسات وإضافة الدروس تكون موجودة
 * بالظبط عند المدرس». و«بالظبط» لا تصمد إن كتبت مرتين: أول نوع درس
 * يضاف بعد اليوم يضاف في واحدة وينسى في الأخرى، فيرفع المعلم درسا
 * ترفضه اللوحة أو العكس.
 *
 * فالحقول تطبع من `Taqdar_curriculum_model::lesson_types()` — الوصف
 * الوحيد — ومن هنا. والفرق بين اللوحتين **أصناف CSS لا أكثر**:
 *
 *   · اللوحة  `tqa-*`  (Bootstrap معاد تعريفه من التوكنات)
 *   · البوابة `tq-*`   (محرك الموقع والبوابات)
 *
 * وكلاهما يقرأ من `tokens.css` نفسها، فالحقل يخرج بالمظهر الصحيح في
 * الجهتين بلا نسخة ثانية من الوسم.
 *
 * والدوال هنا **تطبع ولا تحكم**: التحقق كله في النموذج، وقاعدة تكتب في
 * قالب تنسى في القالب الثاني.
 */

if (!function_exists('tq_cur_types')) {
    /**
     * وصف أنواع الدروس — ويضمن تحميل النموذج قبل قراءته.
     *
     * CodeIgniter 3 لا يحمل أصناف النماذج بالاسم: النداء الساكن على
     * صنف لم يحمل خطأ قاتل لا تحذير. والقوالب تنادى من مواضع شتى، فمنها
     * ما يحمل النموذج ومنها ما لا يحمله — والفرق لا يظهر إلا حين تفتح
     * الشاشة التي نسيت.
     */
    function tq_cur_types()
    {
        $ci = &get_instance();
        if (!isset($ci->taqdar_curriculum_model)) {
            $ci->load->model('taqdar_curriculum_model');
        }
        return Taqdar_curriculum_model::lesson_types();
    }
}

if (!function_exists('tq_cur_type')) {
    /** نوع بمفتاحه، والغائب يسقط إلى يوتيوب — لا إلى صفحة بيضاء. */
    function tq_cur_type($key)
    {
        $all = tq_cur_types();
        return isset($all[$key]) ? $all[$key] : $all['youtube'];
    }
}

if (!function_exists('tq_cur_kind_of')) {
    /** مفتاح النوع من صف درس محفوظ. */
    function tq_cur_kind_of($lesson)
    {
        tq_cur_types();   // يضمن التحميل
        return Taqdar_curriculum_model::kind_of($lesson);
    }
}

if (!function_exists('tq_cur_skin')) {
    /**
     * أصناف الجلد — `tqa` للوحة و`tq` للبوابة.
     *
     * تجمع هنا لا تبعثر في الوسم: صنف يكتب في عشرة مواضع يتغير في تسعة.
     */
    function tq_cur_skin($skin = 'tqa')
    {
        if ($skin === 'tq') {
            return array(
                'field'  => 'tq-field',
                'label'  => 'tq-field__label',
                'input'  => 'tq-input',
                'select' => 'tq-select',
                'area'   => 'tq-textarea',
                'hint'   => 'tq-caption',
                'req'    => 'tqc-req',
                'err'    => 'tq-alert tq-alert--no',
                'note'   => 'tqc-note',
                'ltr'    => 'tq-ltr',
            );
        }
        return array(
            'field'  => 'tqa-field',
            'label'  => 'tqa-field__label',
            'input'  => 'tqa-input',
            'select' => 'tqa-select',
            'area'   => 'tqa-textarea',
            'hint'   => 'tqa-field__hint',
            'req'    => 'tqa-field__req',
            'err'    => 'tqa-flash tqa-flash--err',
            'note'   => 'tqa-note',
            'ltr'    => 'tqa-input--ltr',
        );
    }
}

if (!function_exists('tq_cur_field')) {
    /**
     * يطبع حقلا واحدا من وصفه.
     *
     * @param string $name  اسم الحقل في النموذج
     * @param array  $f     وصفه من `lesson_types()`
     * @param mixed  $value قيمته الحالية (تحرير) أو '' (إنشاء)
     * @param string $skin  'tqa' | 'tq'
     */
    function tq_cur_field($name, $f, $value = '', $skin = 'tqa')
    {
        $c    = tq_cur_skin($skin);
        $id   = 'tqf_' . preg_replace('/[^a-z0-9_]/i', '', $name);
        $req  = !empty($f['required']);
        $ltr  = !empty($f['ltr']) || in_array($f['kind'], array('url', 'duration'), true);
        $hint = isset($f['hint']) ? $f['hint'] : '';

        echo '<div class="' . $c['field'] . '">';
        echo '<label' . ($c['label'] ? ' class="' . $c['label'] . '"' : '') . ' for="' . $id . '">'
           . html_escape($f['label'])
           . ($req ? ' <span class="' . $c['req'] . '" aria-hidden="true">*</span>' : '')
           . '</label>';

        switch ($f['kind']) {

            case 'url':
                /* `type="url"` لا `text`: المتصفح يفحص الصيغة قبل الإرسال،
                   ولوحة الجوال تعطي مفاتيح الرابط. */
                echo '<input class="' . $c['input'] . ' ' . $c['ltr'] . '" type="url"'
                   . ' id="' . $id . '" name="' . html_escape($name) . '" dir="ltr"'
                   . ' value="' . html_escape((string) $value) . '"'
                   . ($req ? ' required' : '')
                   . ' data-tq-cur="url"'
                   . (!empty($f['probe']) ? ' data-tq-probe="1"' : '')
                   . ' placeholder="' . html_escape(isset($f['placeholder']) ? $f['placeholder'] : '') . '">';
                if (!empty($f['probe'])) {
                    /* قارئ المدة يكتب هنا: «يقرأ…» ثم النتيجة أو سبب
                       التعذر. وهو يعمل في المتصفح لا في الخادم — يوتيوب
                       يعلن مدته لمشغله، والخادم يحتاج مفتاح واجهة برمجة
                       لا يملكه هذا التركيب. */
                    echo '<span class="' . $c['hint'] . '" data-tq-probe-out hidden></span>';
                }
                break;

            case 'duration':
                $sec = is_numeric($value) ? (int) $value : 0;
                $txt = ($sec > 0)
                    ? sprintf('%02d:%02d:%02d', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60)
                    : (string) $value;
                echo '<input class="' . $c['input'] . ' ' . $c['ltr'] . '" type="text"'
                   . ' id="' . $id . '" name="' . html_escape($name) . '" dir="ltr"'
                   . ' value="' . html_escape($txt) . '"'
                   . ' inputmode="numeric" pattern="[0-9]{1,3}(:[0-9]{1,2}){1,2}"'
                   . ' data-tq-cur="duration"'
                   . ($req ? ' required' : '')
                   . ' placeholder="00:00:00">';
                break;

            case 'file':
                if (trim((string) $value) !== '') {
                    /* الملف القائم يعرض باسمه ورابطه: «اختر ملفا» وحدها
                       تجعل من يحرر درسا لا يعرف أله ملف أصلا، فيرفع
                       ثانية بلا حاجة. */
                    $href = (strpos((string) $value, 'http') === 0)
                        ? (string) $value
                        : base_url((isset($f['dir']) ? $f['dir'] : 'uploads/lesson_files') . '/' . basename((string) $value));
                    echo '<p class="' . $c['hint'] . '" style="margin-block-end:var(--tq-space-s)">'
                       . 'الملف الحالي: <a href="' . html_escape($href) . '" target="_blank" rel="noopener">'
                       . html_escape(basename((string) $value)) . '</a>'
                       . ' — واختيار ملف جديد يستبدله.</p>';
                }
                echo '<input type="file" id="' . $id . '" name="' . html_escape($name) . '"'
                   . (isset($f['accept']) ? ' accept="' . html_escape($f['accept']) . '"' : '')
                   . ($req && trim((string) $value) === '' ? ' required' : '')
                   . ' data-tq-cur="file" data-tqa-file>';
                break;

            case 'richtext':
            case 'textarea':
                echo '<textarea class="' . $c['area'] . ($ltr ? ' ' . $c['ltr'] : '') . '"'
                   . ' id="' . $id . '" name="' . html_escape($name) . '" rows="'
                   . ($f['kind'] === 'richtext' ? 10 : 3) . '"'
                   . ($ltr ? ' dir="ltr"' : '')
                   . ($req ? ' required' : '')
                   . ' data-tq-cur="' . $f['kind'] . '">'
                   . html_escape((string) $value) . '</textarea>';
                break;

            default:
                echo '<input class="' . $c['input'] . '" type="text"'
                   . ' id="' . $id . '" name="' . html_escape($name) . '"'
                   . ' value="' . html_escape((string) $value) . '"'
                   . ($req ? ' required' : '') . '>';
        }

        if ($hint !== '') {
            echo '<span class="' . $c['hint'] . '">' . html_escape($hint) . '</span>';
        }
        echo '</div>';
    }
}

if (!function_exists('tq_cur_fields')) {
    /**
     * يطبع حقول نوع درس كاملة.
     *
     * @param array  $spec   النوع من `lesson_types()`
     * @param array  $lesson صف الدرس عند التحرير، أو array() عند الإنشاء
     * @param string $skin   'tqa' | 'tq'
     */
    function tq_cur_fields($spec, $lesson = array(), $skin = 'tqa')
    {
        if (empty($spec['fields'])) return;

        foreach ($spec['fields'] as $name => $f) {
            /* القيمة تقرأ من العمود الذي يكتب فيه الحقل لا من اسمه:
               `video_file` يكتب في `video_url`، وقراءتها باسم الحقل
               تعطي فارغا أبدا فيبدو كل درس بلا ملف. */
            $col = $f['col'];
            $val = '';
            if ($f['kind'] === 'duration') {
                $val = isset($lesson['duration_sec']) && (int) $lesson['duration_sec'] > 0
                    ? (int) $lesson['duration_sec']
                    : (isset($lesson['duration']) ? $lesson['duration'] : '');
            } elseif (isset($lesson[$col])) {
                $val = $lesson[$col];
            }
            tq_cur_field($name, $f, $val, $skin);
        }
    }
}

if (!function_exists('tq_cur_type_picker')) {
    /**
     * منتقي نوع الدرس — بطاقات لا قائمة منسدلة.
     *
     * والفرق ليس ذوقا: القائمة تعرض الاسم وحده، والفرق بين «رابط ملف
     * مباشر» و«فيديو جوجل درايف» يقرأ من سطر التوضيح لا من الاسم. ومن
     * اختار خطأ يكتشفه بعد أن يرفع.
     */
    function tq_cur_type_picker($current = 'youtube', $skin = 'tqa', $name = 'tq_kind')
    {
        $types = tq_cur_types();
        $cls   = $skin === 'tq' ? 'tqc-pick' : 'tqc-pick tqc-pick--admin';

        echo '<div class="' . $cls . '" role="radiogroup" aria-label="نوع الدرس">';
        foreach ($types as $k => $t) {
            $on = ($k === $current);
            echo '<label class="tqc-pick__one' . ($on ? ' is-on' : '') . '">'
               . '<input type="radio" name="' . html_escape($name) . '" value="' . html_escape($k) . '"'
               . ($on ? ' checked' : '') . ' data-tq-kind>'
               . '<span class="tqc-pick__ic" aria-hidden="true">' . tq_icon($t['icon'], 20) . '</span>'
               . '<span class="tqc-pick__t">'
               . '<b>' . html_escape($t['label']) . '</b>'
               . '<i>' . html_escape($t['hint']) . '</i>'
               . '</span></label>';
        }
        echo '</div>';
    }
}

if (!function_exists('tq_cur_track_note')) {
    /**
     * ما تقوله المنصة عن تتبع هذا النوع — **قبل** أن يرفع لا بعده.
     *
     * ثلاث حالات لا حالتان، ولكل واحدة أثر مختلف على الطالب:
     *
     *   api    — يوتيوب وفيميو: مشغلهما يعلن موضعه، فيقاس ما شوهد فعلا
     *   native — عنصر وسائط في صفحتنا: الأدق، ومعه ضبط السرعة والنص
     *   none   — درايف وإطار خارجي: لا موضع يقرأ. والطالب يعلن إتمامه
     *            بنفسه، وهو إقرار لا قياس
     *
     * وقولها هنا يمنع أن يبني معلم كورسا كاملا على درايف ثم يكتشف أن
     * بوابة الإتقان لا تفتح لطلابه.
     */
    function tq_cur_track_note($spec, $skin = 'tqa')
    {
        $c = tq_cur_skin($skin);
        $track = isset($spec['track']) ? $spec['track'] : 'none';

        if ($track === 'native') return;   // الأدق: لا شيء يقال

        if ($track === 'api') {
            echo '<p class="' . $c['note'] . '">'
               . '<span aria-hidden="true">' . tq_icon('help', 18) . '</span>'
               . '<span>تقاس مشاهدة الطالب من مشغل المصدر نفسه، فتحسب الثواني التي شوهدت فعلا.'
               . ' والرابط عام دائم بحكم استضافته عند غيرنا — فلا تضع فيه ما لا يعرض لغير المشترك.</span>'
               . '</p>';
            return;
        }

        echo '<p class="' . $c['note'] . ($skin === 'tqa' ? ' tqa-note--warn' : '') . '">'
           . '<span aria-hidden="true">' . tq_icon('alert', 18) . '</span>'
           . '<span><strong>هذا المصدر لا يعلن موضع تشغيله.</strong>'
           . ' فلا تقاس مشاهدة الطالب فيه، ويفتح الدرس التالي بإقرار الطالب أنه أنهاه لا بقياس.'
           . ' واستعمل يوتيوب أو ملفا مرفوعا حيث يهمك القياس.</span>'
           . '</p>';
    }
}

if (!function_exists('tq_cur_course_field')) {
    /**
     * يطبع حقل كورس واحدا من وصفه — TQ-COURSE-SPLIT.
     *
     * أخته `tq_cur_field()` تطبع حقول **الدرس**، وهذه حقول **الكورس**.
     * والفصل بينهما لأن أنواعهما تختلف: الدرس فيه رابط ومدة وملف،
     * والكورس فيه مرحلة وحالة وصف ومادة. وجمعهما في دالة واحدة يعني
     * مفتاح `switch` بأربعة عشر فرعا نصفها لا يقع أبدا.
     *
     * والجلد واحد (`tq_cur_skin`)، فالحقل يخرج بمظهر لوحته في الجهتين.
     *
     * @param string $name اسم الحقل في النموذج
     * @param array  $f    وصفه من `Taqdar_curriculum_model::course_fields()`
     * @param mixed  $val  قيمته الحالية
     * @param string $skin 'tqa' | 'tq'
     */
    function tq_cur_course_field($name, $f, $val = '', $skin = 'tq')
    {
        $c    = tq_cur_skin($skin);
        $id   = 'tqc_' . preg_replace('/[^a-z0-9_]/i', '', $name);
        $req  = !empty($f['required']);
        $hint = isset($f['hint']) ? $f['hint'] : '';
        $kind = $f['kind'];

        /* الخانة والحالة يرسمان لوحيهما بأنفسهما: ليس لهما `<label>`
           واحد يعلو حقلا واحدا. */
        if ($kind === 'bool') {
            echo '<div class="' . $c['field'] . '" style="grid-column:1/-1">';
            /* الخانة غير المؤشرة لا ترسل أصلا، فلا يفرق النموذج بين «لم
               تعرض» و«أطفئت». وهذا المرافق هو ما يفرق. */
            echo '<input type="hidden" name="' . html_escape($name) . '_sent" value="1">';
            echo '<label class="tqc-check">';
            echo '<input type="checkbox" id="' . $id . '" name="' . html_escape($name) . '" value="1"'
               . ((int) $val === 1 ? ' checked' : '') . '>';
            echo '<span><strong>' . html_escape($f['label']) . '</strong>';
            if ($hint !== '') echo '<span style="display:block" class="' . $c['hint'] . '">' . html_escape($hint) . '</span>';
            echo '</span></label></div>';
            return;
        }

        if ($kind === 'status') {
            echo '<div class="' . $c['field'] . '" style="grid-column:1/-1">';
            echo '<span class="' . $c['label'] . '">' . html_escape($f['label']) . '</span>';
            $cur = (string) ($val !== '' ? $val : $f['default']);
            foreach (Taqdar_curriculum_model::course_statuses() as $k => $sd) {
                /* والحالات كلها تعرض ولو لم يملكها المعلم: إخفاء «منشور»
                   يجعله يظن أنها غير موجودة، وعرضها مع سبب تعذرها يقول
                   له أين القرار. والنموذج هو من يحكم لا هذا الوسم. */
                echo '<label class="tqc-check">';
                echo '<input type="radio" name="' . html_escape($name) . '" value="' . $k . '"'
                   . ($cur === $k ? ' checked' : '') . '>';
                echo '<span><strong>' . html_escape($sd[0]) . '</strong>'
                   . '<span style="display:block" class="' . $c['hint'] . '">' . html_escape($sd[1]) . '</span>'
                   . '</span></label>';
            }
            echo '</div>';
            return;
        }

        echo '<div class="' . $c['field'] . '"' . (!empty($f['full']) ? ' style="grid-column:1/-1"' : '') . '>';
        echo '<label class="' . $c['label'] . '" for="' . $id . '">' . html_escape($f['label'])
           . ($req ? ' <span class="' . $c['req'] . '" aria-hidden="true">*</span>' : '') . '</label>';

        switch ($kind) {

            case 'category':
                echo '<select class="' . $c['select'] . '" id="' . $id . '" name="' . html_escape($name) . '">'
                   . '<option value="0">— بلا مرحلة</option>'
                   . tqa_category_options((int) $val)
                   . '</select>';
                break;

            case 'ref':
                echo '<select class="' . $c['select'] . '" id="' . $id . '" name="' . html_escape($name) . '">'
                   . '<option value="0">' . html_escape(isset($f['empty']) ? $f['empty'] : '—') . '</option>'
                   . tqa_ref_options($f['table'], (int) $val)
                   . '</select>';
                break;

            case 'enum':
                echo '<select class="' . $c['select'] . '" id="' . $id . '" name="' . html_escape($name) . '">';
                foreach ($f['options'] as $k => $l) {
                    echo '<option value="' . html_escape($k) . '"'
                       . ((string) $val === (string) $k ? ' selected' : '') . '>' . html_escape($l) . '</option>';
                }
                echo '</select>';
                break;

            case 'language':
                /* القائمة من `Crud_model` نفسها التي تغذي شاشة اللوحة —
                   لا مصفوفة مكتوبة هنا تفترق عنها عند أول لغة تضاف. */
                $CI = get_instance();
                $langs = array();
                try {
                    $langs = (array) $CI->crud_model->get_all_languages();
                } catch (Throwable $e) { /* الشاشة تعمل بلا قائمة */ }
                if (!$langs) $langs = array('arabic', 'english');
                echo '<select class="' . $c['select'] . '" id="' . $id . '" name="' . html_escape($name) . '">';
                foreach ($langs as $l) {
                    echo '<option value="' . html_escape($l) . '"'
                       . ((string) $val === (string) $l ? ' selected' : '') . '>' . html_escape(ucfirst($l)) . '</option>';
                }
                echo '</select>';
                break;

            case 'image':
                if (trim((string) $val) !== '') {
                    $src = strpos((string) $val, 'uploads/') === 0
                        ? base_url((string) $val)
                        : base_url(rtrim($f['dir'], '/') . '/' . basename((string) $val));
                    echo '<img src="' . html_escape($src) . '" alt=""'
                       . ' style="max-inline-size:220px;border-radius:var(--tq-radius-small);'
                       . 'margin-block-end:var(--tq-space-s);display:block">';
                    echo '<p class="' . $c['hint'] . '">واختيار صورة جديدة يستبدلها.</p>';
                }
                echo '<input type="file" id="' . $id . '" name="' . html_escape($name) . '"'
                   . ' accept="' . html_escape(isset($f['accept']) ? $f['accept'] : 'image/*') . '">';
                break;

            case 'url':
                echo '<input class="' . $c['input'] . ' ' . $c['ltr'] . '" type="url" dir="ltr"'
                   . ' id="' . $id . '" name="' . html_escape($name) . '"'
                   . ' value="' . html_escape((string) $val) . '" placeholder="https://...">';
                break;

            case 'money':
                echo '<input class="' . $c['input'] . ' ' . $c['ltr'] . '" type="number" dir="ltr"'
                   . ' min="0" step="0.01" id="' . $id . '" name="' . html_escape($name) . '"'
                   . ' value="' . html_escape((string) ($val === '' ? '0' : $val)) . '">';
                break;

            case 'datetime':
                echo '<input class="' . $c['input'] . ' ' . $c['ltr'] . '" type="datetime-local" dir="ltr"'
                   . ' id="' . $id . '" name="' . html_escape($name) . '"'
                   . ' value="' . html_escape((string) $val) . '">';
                break;

            case 'lines':
                /* المصفوفة تخزن JSON وتحرر أسطرا: صف مكرر بزر «أضف بندا»
                   يحتاج جافاسكربت ليعمل، وسطر في مربع نص يعمل بلا شيء. */
                $rows = json_decode((string) $val, true);
                $txt  = is_array($rows) ? implode("\n", $rows) : (string) $val;
                echo '<textarea class="' . $c['area'] . '" id="' . $id . '" name="' . html_escape($name) . '"'
                   . ' rows="4">' . html_escape($txt) . '</textarea>';
                break;

            case 'richtext':
                echo '<textarea class="' . $c['area'] . '" id="' . $id . '" name="' . html_escape($name) . '"'
                   . ' rows="8">' . html_escape((string) $val) . '</textarea>';
                break;

            case 'textarea':
                echo '<textarea class="' . $c['area'] . '" id="' . $id . '" name="' . html_escape($name) . '"'
                   . ' rows="3">' . html_escape((string) $val) . '</textarea>';
                break;

            default:
                echo '<input class="' . $c['input'] . '" type="text" id="' . $id . '"'
                   . ' name="' . html_escape($name) . '" value="' . html_escape((string) $val) . '"'
                   . (!empty($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '')
                   . ($req ? ' required' : '') . '>';
        }

        if ($hint !== '') echo '<span class="' . $c['hint'] . '">' . html_escape($hint) . '</span>';
        echo '</div>';
    }
}

if (!function_exists('tq_cur_course_form')) {
    /**
     * يطبع نموذج الكورس كاملا من وصفه، مجموعا في بطاقات بمفتاح `section`.
     *
     * والقيمة تقرأ من **العمود** لا من اسم الحقل: `language_made_in`
     * يكتب في `language`، وقراءته باسمه تعطي فارغا أبدا فتظهر لغة كل
     * كورس على أول خيار في القائمة.
     *
     * @param array  $spec   من `Taqdar_curriculum_model::course_fields()`
     * @param array  $course صف الكورس عند التحرير، أو array() عند الإنشاء
     * @param string $skin   'tqa' | 'tq'
     */
    function tq_cur_course_form($spec, $course = array(), $skin = 'tq')
    {
        $open = false;
        foreach ($spec as $name => $f) {
            if (!empty($f['section'])) {
                if ($open) echo '</div></section>';
                echo '<section class="' . ($skin === 'tq' ? 'tq-card tq-section' : 'tqa-card tqa-section') . '">';
                echo '<h2 class="' . ($skin === 'tq' ? 'tq-card__title' : 'tqa-card__head') . '"'
                   . ' style="margin-block-end:var(--tq-space-l)">' . html_escape($f['section']) . '</h2>';
                echo '<div class="' . ($skin === 'tq' ? 'tqc-grid' : 'tqa-fieldgrid') . '">';
                $open = true;
            }

            $col = isset($f['col']) && $f['col'] !== null ? $f['col'] : $name;
            $val = array_key_exists($col, $course) ? $course[$col]
                 : (array_key_exists($name, $course) ? $course[$name]
                 : (isset($f['default']) ? $f['default'] : ''));

            tq_cur_course_field($name, $f, $val, $skin);
        }
        if ($open) echo '</div></section>';
    }
}

if (!function_exists('tq_cur_status_face')) {
    /** حالة الدرس: شارة ونصها — مصدر واحد للجهتين. */
    function tq_cur_status_face($status)
    {
        switch ((string) $status) {
            case 'published': return array('ok',    'منشور');
            case 'review':    return array('warn',  'قيد المراجعة');
            case 'rejected':  return array('danger', 'رد للتعديل');
            default:          return array('muted', 'مسودة');
        }
    }
}

if (!function_exists('tq_cur_styles')) {
    /**
     * ورقة منتقي النوع — تطبع مرة في الصفحة.
     *
     * موضعها هنا لا في `admin.css` ولا في `components.css`: هي تخص هذا
     * المكون وحده ويطبع في المحركين، فوضعها في أحدهما يعني نسخها في
     * الثاني. وكل قيمة من التوكنات — لا لون مباشر ولا مسافة.
     */
    function tq_cur_styles()
    {
        static $done = false;
        if ($done) return;
        $done = true;
        ?>
<style>
.tqc-pick { display: grid; gap: var(--tq-space-s);
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            margin-block-end: var(--tq-space-l); }
.tqc-pick__one { display: flex; gap: var(--tq-space-s); align-items: flex-start;
                 border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
                 padding: var(--tq-space-m); cursor: pointer; background: var(--tq-surface); }
.tqc-pick__one:hover { border-color: var(--tq-teal); }
.tqc-pick__one.is-on { border-color: var(--tq-teal); background: var(--tq-mint-fill); }
.tqc-pick__one:focus-within { outline: 2px solid var(--tq-focusRing); outline-offset: 1px; }
.tqc-pick__one input { flex: none; margin-block-start: 3px; accent-color: var(--tq-teal); }
.tqc-pick__ic { flex: none; color: var(--tq-teal); display: grid; place-items: center; }
.tqc-pick__t  { display: flex; flex-direction: column; gap: 2px; min-inline-size: 0; }
.tqc-pick__t b { font-size: .92rem; }
.tqc-pick__t i { font-style: normal; font-size: .78rem; color: var(--tq-text2); }

/* المدة تقرأ يمينا-يسارا دائما: `00:12:30` مقلوبة تقرأ خطأ. */
[data-tq-cur="duration"], [data-tq-cur="url"] { unicode-bidi: isolate; }

/* البوابة ليس فيها `tq-note` ولا `tq-req` — واللوحة فيها نظيراهما.
   فيعرفان هنا بالبادئة `tqc-` (مكون المنهج) فيعملان في المحركين، ولا
   يضاف صنف إلى ورقة عامة لأجل شاشة واحدة. */
.tqc-req  { color: var(--tq-danger); }
.tqc-note { display: flex; gap: var(--tq-space-s); align-items: flex-start;
            padding: var(--tq-space-m); border-radius: var(--tq-radius-small);
            background: var(--tq-amberSoft); color: var(--tq-text);
            font: var(--tq-type-caption); margin-block-end: var(--tq-space-l); }
.tqc-note > span:first-child { flex: none; color: var(--tq-navy); }

/* شبكة حقول الكورس وخانة الاختيار — بالبادئة نفسها وللسبب نفسه:
   `tq-formgrid` و`tq-check` **غير معرفتين في أي ورقة** (`tq_teacher_courses`
   يكتب الأولى منذ اليوم الأول فتخرج حقوله مكدسة بلا شبكة)، ونظيراهما في
   اللوحة `tqa-fieldgrid` و`tqa-check`. فيعرفان هنا فيعملان في المحركين. */
.tqc-grid  { display: grid; gap: var(--tq-space-l);
             grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
.tqc-check { display: flex; gap: var(--tq-space-m); align-items: flex-start;
             cursor: pointer; margin-block-end: var(--tq-space-s); }
.tqc-check input { margin-block-start: 3px; flex: none; }
</style>
        <?php
    }
}
