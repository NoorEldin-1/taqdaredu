<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * عرض قيم وحدات تقدر في اللوحة.
 * منفصلة عن العرض لأن الصياغة نفسها تتكرر في القائمة والنموذج والسجل،
 * ولأن أخطاء صياغة النقود والتواريخ لا تكتشف بالنظر بل بالتكرار.
 */

if (!function_exists('tqa_ltr')) {
    /** يعزل نصا لاتينيا أو رقما داخل فقرة عربية فلا ينقلب ترتيبه. */
    function tqa_ltr($v)
    {
        return '<span class="tq-ltr" dir="ltr">' . html_escape((string) $v) . '</span>';
    }
}

if (!function_exists('tqa_money')) {
    /** الهللات تخزن أعدادا صحيحة وتعرض ريالات — القسمة هنا وحدها. */
    function tqa_money($halalas)
    {
        return '<span class="tq-ltr" dir="ltr">' . number_format(((int) $halalas) / 100, 2) . '</span> ر.س';
    }
}

if (!function_exists('tqa_seconds')) {
    function tqa_seconds($s)
    {
        $s = max(0, (int) $s);
        return '<span class="tq-ltr" dir="ltr">' . sprintf('%02d:%02d', intdiv($s, 60), $s % 60) . '</span>';
    }
}

if (!function_exists('tqa_when')) {
    /**
     * يعرض وقتا قد يكون طابع يونكس أو نصا زمنيا.
     *
     * TQ-NOTIF-TIME — عمود `notifications.created_at` من نوع
     * `varchar(255)` تكتب فيه Academy **طابع يونكس نصا**، وكتبت فيه
     * `push_notification` و`broadcast` نصا زمنيا `Y-m-d H:i:s` فترة.
     * فالجدول اليوم فيه الصيغتان معا، وأي قارئ يفترض واحدة يخطئ في
     * النصف الآخر: `strtotime("1786468124")` ترد `false` فيعرض
     * ١٩٧٠-٠١-٠١، و`(int) "2026-08-11 …"` ترد 2026 فيعرض «منذ ٥٦ سنة».
     *
     * فتقرأ الصيغتان هنا في موضع واحد. والكتابة توحدت على الطابع.
     */
    function tqa_when($value, $format = 'Y-m-d H:i')
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '—';
        }
        $ts = ctype_digit($v) ? (int) $v : strtotime($v);
        if (!$ts || $ts < 86400) {
            return '—';
        }
        return '<span class="tq-ltr" dir="ltr">' . date($format, $ts) . '</span>';
    }
}

if (!function_exists('tqa_cell')) {
    /** يصوغ قيمة عمود حسب نوعه المعلن في الوصف. */
    function tqa_cell($field, $value, $model)
    {
        if ($value === null || $value === '') {
            return '<span class="tqa-dim">—</span>';
        }

        switch ($field['type']) {

            case 'bool':
                return $value
                    ? '<span class="badge badge-success">نعم</span>'
                    : '<span class="tqa-dim">لا</span>';

            case 'money':
                return tqa_money($value);

            case 'seconds':
                return tqa_seconds($value);

            case 'number':
                return tqa_ltr($value);

            case 'datetime':
                return tqa_ltr(date('Y-m-d H:i', strtotime($value)));

            case 'enum':
                $label = isset($field['options'][$value]) ? $field['options'][$value] : $value;
                $tone  = in_array($value, array('published', 'active'), true) ? 'success'
                       : (in_array($value, array('revoked'), true) ? 'danger' : 'warning');
                return '<span class="badge badge-' . $tone . '">' . html_escape($label) . '</span>';

            case 'lines':
                $arr = json_decode((string) $value, true);
                if (!is_array($arr) || !$arr) return '<span class="tqa-dim">—</span>';
                return html_escape(implode(' · ', $arr));

            case 'ref':
                $opts = $model->options($field['ref']);
                return isset($opts[$value])
                    ? html_escape($opts[$value])
                    : '<span class="tqa-dim">#' . (int) $value . ' (محذوف)</span>';

            /* المفتاح النصي يعرض باسمه لا بمفتاحه: عمود يقرأ `primary`
               في شاشة عربية لا يقرأ. */
            case 'pick':
                $opts = $model->options($field['ref']);
                return isset($opts[$value])
                    ? html_escape($opts[$value])
                    : '<span class="tqa-dim">' . html_escape((string) $value) . ' (غير معروف)</span>';

            /* قائمة معرفات: الأسماء لا الأرقام، وما زاد على أربعة يعد
               ولا يسرد — اثنا عشر صفا في خلية تكسر الجدول. */
            case 'multiref':
                $opts = $model->options($field['ref']);
                $ids  = array_filter(array_map('intval', explode(',', (string) $value)));
                if (!$ids) return '<span class="tqa-dim">—</span>';

                $names = array();
                foreach ($ids as $i) {
                    $names[] = isset($opts[$i]) ? $opts[$i] : '#' . $i;
                }
                $shown = array_slice($names, 0, 3);
                $more  = count($names) - count($shown);
                $out   = '';
                foreach ($shown as $n) $out .= '<span class="tqa-chip">' . html_escape($n) . '</span> ';
                if ($more > 0) $out .= '<span class="tqa-dim">و' . $more . ' غيرها</span>';
                return $out;

            /* `refswitch` عمود واحد يفسر حسب حقل آخر، والخلية لا تعرف
               ذلك الحقل — فالرقم وحده، وشاشة التعديل تقول ما هو. */
            case 'refswitch':
                return '<span class="tq-ltr" dir="ltr">#' . (int) $value . '</span>';

            default:
                $txt = html_escape(mb_strimwidth((string) $value, 0, 90, '…', 'UTF-8'));
                return !empty($field['ltr']) ? '<span class="tq-ltr" dir="ltr">' . $txt . '</span>' : $txt;
        }
    }
}

if (!function_exists('tqa_val')) {
    /**
     * القيمة المعروضة في النموذج: الصف المحفوظ إن وجد وإلا الافتراضي.
     * النقود تقسم هنا لأن الحقل يدخل بالريال، والتاريخ يصاغ لصيغة
     * `datetime-local` وحدها — أي صيغة أخرى يتجاهلها المتصفح صامتا.
     */
    function tqa_val($row, $name, $f)
    {
        $v = ($row && array_key_exists($name, $row))
            ? $row[$name]
            : (isset($f['default']) ? $f['default'] : '');

        if ($f['type'] === 'money' && $v !== '' && $v !== null) {
            return number_format(((int) $v) / 100, 2, '.', '');
        }
        if ($f['type'] === 'datetime' && $v) {
            return date('Y-m-d\TH:i', strtotime($v));
        }
        if ($f['type'] === 'lines') {
            $arr = json_decode((string) $v, true);
            return is_array($arr) ? implode("\n", $arr) : '';
        }
        return $v;
    }
}

if (!function_exists('tqa_flash')) {
    /** يعرض رسالة الجولة السابقة إن وجدت. */
    function tqa_flash()
    {
        $CI = &get_instance();
        $ok = $CI->session->flashdata('flash_message');
        $no = $CI->session->flashdata('error_message');
        if ($ok) echo '<div class="tqa-flash tqa-flash--ok" role="status">' . html_escape($ok) . '</div>';
        if ($no) echo '<div class="tqa-flash tqa-flash--err" role="alert">' . html_escape($no) . '</div>';
    }
}

if (!function_exists('tqa_nav_counts')) {
    /**
     * شارات الشريط الجانبي — استعلام واحد لكل شارة، مرة واحدة لكل طلب.
     *
     * كانت ستة استعلامات مكتوبة بين وسوم `navigation.php`، تنفذ في كل
     * صفحة من صفحات اللوحة. وشارة تعد صفوفا في جدول قد لا يوجد (الحصص
     * ينشئ جدولها `Taqdar_sessions_model::ensure_schema()` عند أول
     * استعمال) تسقط الشريط كله — والشريط يعرض في كل شاشة. فكل عد
     * محاط بـ`try` ويرجع صفرا عند التعثر: شارة ناقصة أهون من لوحة بيضاء.
     */
    function tqa_nav_counts()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $CI  = &get_instance();
        $uid = (int) $CI->session->userdata('user_id');

        /** يعد بأمان: الجدول الغائب أو العمود المفقود يرجع صفرا لا استثناء. */
        $count = function ($sql, $args = array()) use ($CI) {
            try {
                $q = $CI->db->query($sql, $args);
                if (!$q) return 0;
                $r = $q->row_array();
                return (int) ($r['n'] ?? 0);
            } catch (Throwable $e) {
                return 0;
            }
        };

        $cache = array(
            'teacher_apps'  => $count('SELECT COUNT(*) n FROM `applications` WHERE `status` = 0'),
            'parent_links'  => $count('SELECT COUNT(*) n FROM `parent_links` WHERE `status` = "pending"'),
            'sessions'      => $count('SELECT COUNT(*) n FROM `tutoring_sessions` WHERE `status` = "requested"'),
            'payouts'       => $count('SELECT COUNT(*) n FROM `payout` WHERE `status` = 0'),
            'subs_pending'  => $count('SELECT COUNT(*) n FROM `subscriptions` WHERE `status` = "pending"'),
            'messages'      => $count('SELECT COUNT(*) n FROM `message` WHERE `receiver` = ? AND `read_status` != 1', array($uid)),
            'contact'       => $count('SELECT COUNT(*) n FROM `contact` WHERE `has_read` IS NULL'),
            'pending_blogs' => $count('SELECT COUNT(*) n FROM `blogs` WHERE `status` = 0'),
            'pending_courses' => $count('SELECT COUNT(*) n FROM `course` WHERE `status` = "pending"'),
        );

        return $cache;
    }
}

if (!function_exists('tqa_head')) {
    /**
     * رأس الصفحة: أيقونة وعنوان وسطر تحته وأدوات.
     * موحد لأن الشاشة التي تكتب رأسها بيدها تخرج بمقاس مختلف عن جارتها.
     */
    function tqa_head($title, $sub = '', $icon = 'circle', $tools = '')
    {
        echo '<div class="tqa-pagehead"><div><div class="tqa-pagehead__title">';
        if ($icon) {
            echo '<span class="tqa-iconbox" aria-hidden="true">' . tq_icon($icon, 22) . '</span>';
        }
        echo '<h1>' . html_escape($title) . '</h1></div>';
        if ($sub !== '') {
            echo '<p class="tqa-pagehead__sub">' . html_escape($sub) . '</p>';
        }
        echo '</div>';
        if ($tools !== '') {
            echo '<div class="tqa-pagehead__tools">' . $tools . '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('tqa_empty')) {
    /**
     * حالة الفراغ.
     *
     * «لا بيانات» تخبر بما هو حاصل ولا تخبر بما يفعل: من يفتح شاشة
     * فارغة يريد أن يعرف الخطوة التالية، لا أن يؤكد له أنه لا يرى شيئا.
     */
    function tqa_empty($title, $body = '', $cta_label = '', $cta_href = '', $icon = 'folder')
    {
        echo '<div class="tqa-empty">'
           . '<span class="tqa-empty__icon" aria-hidden="true">' . tq_icon($icon, 26) . '</span>'
           . '<h3>' . html_escape($title) . '</h3>';
        if ($body !== '') echo '<p>' . html_escape($body) . '</p>';
        if ($cta_label !== '' && $cta_href !== '') {
            echo '<a class="tqa-btn tqa-btn--primary" href="' . html_escape($cta_href) . '">'
               . html_escape($cta_label) . '</a>';
        }
        echo '</div>';
    }
}
