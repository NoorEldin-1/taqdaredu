<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * عرض قيم وحدات تقدّر في اللوحة.
 * منفصلة عن العرض لأن الصياغة نفسها تتكرّر في القائمة والنموذج والسجلّ،
 * ولأن أخطاء صياغة النقود والتواريخ لا تُكتشف بالنظر بل بالتكرار.
 */

if (!function_exists('tqa_ltr')) {
    /** يعزل نصًّا لاتينيًّا أو رقمًا داخل فقرة عربية فلا ينقلب ترتيبه. */
    function tqa_ltr($v)
    {
        return '<span class="tq-ltr" dir="ltr">' . html_escape((string) $v) . '</span>';
    }
}

if (!function_exists('tqa_money')) {
    /** الهللات تُخزَّن أعدادًا صحيحة وتُعرَض ريالات — القسمة هنا وحدها. */
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

            default:
                $txt = html_escape(mb_strimwidth((string) $value, 0, 90, '…', 'UTF-8'));
                return !empty($field['ltr']) ? '<span class="tq-ltr" dir="ltr">' . $txt . '</span>' : $txt;
        }
    }
}

if (!function_exists('tqa_val')) {
    /**
     * القيمة المعروضة في النموذج: الصفّ المحفوظ إن وُجد وإلّا الافتراضي.
     * النقود تُقسَم هنا لأن الحقل يُدخَل بالريال، والتاريخ يُصاغ لصيغة
     * `datetime-local` وحدها — أي صيغة أخرى يتجاهلها المتصفّح صامتًا.
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
    /** يعرض رسالة الجولة السابقة إن وُجدت. */
    function tqa_flash()
    {
        $CI = &get_instance();
        $ok = $CI->session->flashdata('flash_message');
        $no = $CI->session->flashdata('error_message');
        if ($ok) echo '<div class="tqa-flash tqa-flash-ok">' . html_escape($ok) . '</div>';
        if ($no) echo '<div class="tqa-flash tqa-flash-no">' . html_escape($no) . '</div>';
    }
}
