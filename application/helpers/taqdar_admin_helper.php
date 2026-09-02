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
        return '<span class="tq-ltr" dir="ltr">' . number_format(((int) $halalas) / 100, 2) . t('</span> ر.س');
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
                    ? t('<span class="badge badge-success">نعم</span>')
                    : t('<span class="tqa-dim">لا</span>');

            case 'money':
                return tqa_money($value);

            case 'seconds':
                return tqa_seconds($value);

            case 'number':
                return tqa_ltr($value);

            /* النسبة ومتممها معا في خلية واحدة: عمود يقول «١٥» وحدها
               يترك القارئ يحسب ٨٥ في رأسه على كل صف، وهو الرقم الذي
               فتح الشاشة من أجله. */
            case 'percent':
                $p = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
                $q = rtrim(rtrim(number_format(100 - (float) $value, 2, '.', ''), '0'), '.');
                return tqa_ltr($p . '%') . ' <span class="tqa-dim">/ ' . tqa_ltr($q . '%') . '</span>';

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
                    : '<span class="tqa-dim">#' . (int) $value . t(' (محذوف)</span>');

            /* المفتاح النصي يعرض باسمه لا بمفتاحه: عمود يقرأ `primary`
               في شاشة عربية لا يقرأ. */
            case 'pick':
                $opts = $model->options($field['ref']);
                return isset($opts[$value])
                    ? html_escape($opts[$value])
                    : '<span class="tqa-dim">' . html_escape((string) $value) . t(' (غير معروف)</span>');

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
                if ($more > 0) $out .= t('<span class="tqa-dim">و') . $more . t(' غيرها</span>');
                return $out;

            /* الصورة تعرض صورة لا مسارا: `uploads/plans/plan-31-a3f9.webp`
               اسم لا يقول أي صورة هي، والعمود موجود ليقال به «أهذه
               الباقة لها وجه؟». */
            case 'file':
                $src = (strpos((string) $value, '/') !== false)
                     ? base_url(ltrim((string) $value, '/'))
                     : base_url('assets/taqdar/site/img/' . $value . '.webp');
                return '<img class="tqa-thumb" src="' . html_escape($src) . '" alt=""'
                     . ' loading="lazy" decoding="async" width="72" height="48">';

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
            /* عدادان لا واحد، لأنهما سؤالان:
               `contact`      — «هل وصل جديد؟» ويصفر بمجرد فتح الشاشة
                                (`Admin::contact()` تعلم الكل مقروءا)، فهو
                                نقطة الجرس في الشريط العلوي لا أكثر.
               `contact_open` — «ما الذي ينتظر ردي؟» ولا يصفر إلا بالرد
                                فعلا. وهذا ما تعرضه لوحة القيادة، وإلا
                                اختفى بند «ينتظر إجراء منك» بعد نظرة
                                واحدة ولم يرد على أحد. */
            'contact'       => $count('SELECT COUNT(*) n FROM `contact` WHERE `has_read` IS NULL'),
            'contact_open'  => $count('SELECT COUNT(*) n FROM `contact` WHERE `replied` IS NULL OR `replied` != 1'),
            'pending_blogs' => $count('SELECT COUNT(*) n FROM `blogs` WHERE `status` = 0'),
            'pending_courses' => $count('SELECT COUNT(*) n FROM `course` WHERE `status` = "pending"'),
            /* مراجعة المحتوى: عددان في رقم — درس جديد ينتظر نشرا، وتعديل
               على منشور ينتظر تطبيقا. وهما سؤال واحد على المسؤول، فرقم
               واحد يجيبه. */
            'content_review' => $count('SELECT COUNT(*) n FROM `lesson` WHERE `tq_status` = "review"')
                              + $count('SELECT COUNT(*) n FROM `tq_content_revisions` WHERE `status` = "pending"'),
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

/* =====================================================================
   الكورس: تصنيفه وربطه بالبرنامج
   ===================================================================== */

if (!function_exists('tqa_category_options')) {
    /**
     * خيارات منتقي «المرحلة» في نموذج الكورس.
     *
     * TQ-CAT-EMPTY — كان المنتقي يطبع `optgroup` لكل فئة أب ثم يملؤها
     * بأبنائها. و`category` في هذه القاعدة **خمسة صفوف كلها `parent = 0`**
     * ولا ابن لواحد منها: فيخرج المنتقي خمس مجموعات فارغة، أي خيارا
     * واحدا هو «— اختر تصنيفا». والحقل `required`، فالنموذج لا يمكن
     * إرساله إطلاقا — وهذا ما يوقف إنشاء أي كورس من اللوحة.
     *
     * والقاعدة هنا: **الأب خيار بذاته**، وأبناؤه — إن وجدوا — يعرضون
     * تحته مزاحين. فالشجرة تعمل بمستوى واحد كما هي اليوم، وبمستويين لو
     * أضيفت تصنيفات فرعية غدا، بلا تغيير في هذا الموضع.
     */
    function tqa_category_options($selected = 0)
    {
        $CI  = &get_instance();
        $sel = (int) $selected;

        $rows = $CI->db->select('id, name, parent, tq_order')
                       ->order_by('tq_order', 'ASC')->order_by('id', 'ASC')
                       ->get('category')->result_array();

        $kids = array();
        foreach ($rows as $r) {
            if ((int) $r['parent'] !== 0) $kids[(int) $r['parent']][] = $r;
        }

        $html = '';
        foreach ($rows as $r) {
            if ((int) $r['parent'] !== 0) continue;
            $id = (int) $r['id'];

            $html .= '<option value="' . $id . '"' . ($sel === $id ? ' selected' : '') . '>'
                   . html_escape($r['name']) . '</option>';

            foreach ($kids[$id] ?? array() as $k) {
                $kid = (int) $k['id'];
                $html .= '<option value="' . $kid . '"' . ($sel === $kid ? ' selected' : '') . '>'
                       . '&nbsp;&nbsp;— ' . html_escape($k['name']) . '</option>';
            }
        }
        return $html;
    }
}

if (!function_exists('tqa_ref_options')) {
    /** خيارات من جدول مرجعي بسيط (`grades` · `subjects`) — النشط منه وحده. */
    function tqa_ref_options($table, $selected = 0, $label = 'name_ar')
    {
        $CI  = &get_instance();
        $sel = (int) $selected;

        $CI->db->select('id, ' . $label . ' AS label');
        if ($CI->db->field_exists('active', $table)) $CI->db->where('active', 1);
        if ($CI->db->field_exists('order',  $table)) $CI->db->order_by('`order`', 'ASC', false);
        $rows = $CI->db->order_by('id', 'ASC')->get($table)->result_array();

        $html = '';
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $html .= '<option value="' . $id . '"' . ($sel === $id ? ' selected' : '') . '>'
                   . html_escape($r['label']) . '</option>';
        }
        return $html;
    }
}

if (!function_exists('tqa_course_faqs')) {
    /**
     * أسئلة الكورس الشائعة بشكل واحد مهما كتبت.
     *
     * الشكل المكتوب اليوم قائمة من `{title, description}`. وما كتب قبل
     * إصلاح TQ-FAQ-SHAPE خريطة `{السؤال: الإجابة}` — انظر
     * [Crud_model::tq_course_faqs()]. والدورات القائمة تحمل الشكل
     * القديم، فالقراءة تقبل الاثنين وإلا ضاعت أسئلة كتبت فعلا.
     */
    function tqa_course_faqs($raw)
    {
        $d = json_decode((string) $raw, true);
        if (!is_array($d)) return array();

        $out = array();
        foreach ($d as $k => $v) {
            if (is_array($v)) {
                $t = trim((string) ($v['title'] ?? ''));
                if ($t === '') continue;
                $out[] = array('title' => $t, 'description' => (string) ($v['description'] ?? ''));
            } else {
                $t = trim((string) $k);
                if ($t === '') continue;
                $out[] = array('title' => $t, 'description' => (string) $v);
            }
        }
        return $out;
    }
}

/* =====================================================================
   التصفح: الفرز والترقيم
   ===================================================================== */

if (!function_exists('tqa_url_with')) {
    /**
     * الرابط الحالي ومعه معاملات مبدلة.
     *
     * والبناء من `$_GET` كله لا من المعاملين المعروفين: رابط يبنى من
     * الفرز وحده يمحو البحث، فيضغط المسؤول على رأس عمود ليرتب نتيجته
     * فتتسع أمامه إلى الجدول كله بلا أن يطلب — وهو عطل الكتالوج نفسه.
     */
    function tqa_url_with($base, $changes = array())
    {
        $q = $_GET;
        foreach ($changes as $k => $v) {
            if ($v === null || $v === '') unset($q[$k]);
            else $q[$k] = $v;
        }
        return site_url($base) . ($q ? '?' . http_build_query($q) : '');
    }
}

if (!function_exists('tqa_sort_th')) {
    /**
     * رأس عمود يفرز. والاتجاه يعلن بسهم وبـ`aria-sort` معا: من يقرأ
     * بالشاشة لا يرى السهم، ومن يبصر لا يقرأ الصفة.
     */
    function tqa_sort_th($base, $col, $label, $sort, $dir, $sortable)
    {
        if (!isset($sortable[$col])) return '<th>' . html_escape($label) . '</th>';

        $on   = ($sort === $col);
        $next = ($on && $dir === 'ASC') ? 'DESC' : 'ASC';
        $aria = $on ? ($dir === 'ASC' ? 'ascending' : 'descending') : 'none';
        $href = tqa_url_with($base, array('sort' => $col, 'dir' => $next, 'p' => null));

        return '<th aria-sort="' . $aria . '"><a class="tqa-sort' . ($on ? ' is-on' : '') . '"'
             . ' href="' . html_escape($href) . '">' . html_escape($label)
             . '<span class="tqa-sort__mark" aria-hidden="true">' . ($on ? ($dir === 'ASC' ? '↑' : '↓') : '↕') . '</span>'
             . '</a></th>';
    }
}

if (!function_exists('tqa_pager')) {
    /**
     * الترقيم. لا يطبع شيئا على صفحة واحدة — شريط بحبة واحدة يقرأ عطلا.
     *
     * والنافذة حول الصفحة الحالية لا القائمة كلها: جدول بألف صفحة يطبع
     * ألف حبة تملأ الشاشة وتخفي ما تحتها.
     */
    function tqa_pager($base, $page, $pages, $total)
    {
        if ($pages < 2) return;
        $win  = 2;
        $from = max(1, $page - $win);
        $to   = min($pages, $page + $win);

        echo '<nav class="tqa-pager" aria-label="' . te('صفحات النتائج') . '">';
        echo '<span class="tqa-pager__info">' . t('صفحة') . ' <span class="tqa-num">' . (int) $page
           . '</span> ' . t('من') . ' <span class="tqa-num">' . (int) $pages . '</span> — '
           . '<span class="tqa-num">' . (int) $total . '</span> ' . t('عنصرا') . '</span>';

        if ($page > 1) {
            echo '<a href="' . html_escape(tqa_url_with($base, array('p' => $page - 1))) . '"'
               . ' rel="prev">' . t('السابق') . '</a>';
        }
        if ($from > 1) {
            echo '<a href="' . html_escape(tqa_url_with($base, array('p' => 1))) . '">1</a>';
            if ($from > 2) echo '<span class="tqa-pager__gap">…</span>';
        }
        for ($i = $from; $i <= $to; $i++) {
            if ($i === (int) $page) {
                echo '<span aria-current="page">' . $i . '</span>';
            } else {
                echo '<a href="' . html_escape(tqa_url_with($base, array('p' => $i))) . '">' . $i . '</a>';
            }
        }
        if ($to < $pages) {
            if ($to < $pages - 1) echo '<span class="tqa-pager__gap">…</span>';
            echo '<a href="' . html_escape(tqa_url_with($base, array('p' => $pages))) . '">' . (int) $pages . '</a>';
        }
        if ($page < $pages) {
            echo '<a href="' . html_escape(tqa_url_with($base, array('p' => $page + 1))) . '"'
               . ' rel="next">' . t('التالي') . '</a>';
        }
        echo '</nav>';
    }
}

if (!function_exists('tqa_avatar')) {
    /**
     * وجه الحساب في صف الجدول.
     *
     * `users.image` **رمز بلا امتداد** والملف `uploads/user_image/<رمز>.jpg`
     * — وهذه القاعدة هي التي كسرت عشر شاشات قبل أن تجمع في موضع واحد.
     * وهنا تجمع للوحة: القيمة تأتي من الصف المقروء أصلا، فلا استعلام
     * لكل صف كما يفعل `User_model::get_user_image_url()`.
     *
     * وبلا ملف **حرف أول على أرضية باستيل** لا أيقونة متصفح مكسورة ولا
     * صورة نائبة واحدة تتكرر خمسين مرة: الحرف يفرق بين صف وصف، والنائبة
     * الواحدة تجعل العمود عمود ضجيج.
     */
    function tqa_avatar($code, $name = '', $extra = '')
    {
        $code = trim((string) $code);
        $cls  = 'tqa-avatar' . ($extra !== '' ? ' ' . $extra : '');

        if ($code !== '' && strpos($code, '/') === false
            && is_file(FCPATH . 'uploads/user_image/' . $code . '.jpg')) {
            return '<img class="' . $cls . '" src="'
                 . html_escape(base_url('uploads/user_image/' . $code . '.jpg'))
                 . '" alt="" width="38" height="38" loading="lazy" decoding="async">';
        }

        /* الحرف الأول بالعربية قد يكون حرفين بايتيا — `mb_substr` لا
           `substr`، وإلا خرج نصف حرف مربعا أسود. */
        $name  = trim((string) $name);
        $first = $name !== '' ? mb_substr($name, 0, 1, 'UTF-8') : '?';

        return '<span class="' . $cls . ' tqa-avatar--none" aria-hidden="true">'
             . html_escape($first) . '</span>';
    }
}

if (!function_exists('tqa_active_filters')) {
    /**
     * رقاقات المرشحات الفعالة، ولكل واحدة زر ينزعها وحدها.
     *
     * TQ-FILTER-AMNESIA — المرشح يعيش في الرابط (وهو الصواب)، ولا شيء في
     * الشاشة يقول ما هو فاعل الآن إلا المنتقي نفسه — وهو أعلى الصفحة،
     * فمن مرر إلى الصف الأربعين يقرأ عددا ناقصا ولا يرى سببه. ويسأل «أين
     * ذهبت الاشتراكات؟» والجواب منتق اختاره هو قبل دقيقتين.
     *
     * و«مسح الكل» وحدها لا تكفي: من رشح بثلاثة يريد أن ينزع واحدا لا أن
     * يبدأ من الصفر.
     *
     * `$on` مصفوفة: المفتاح اسم معامل `GET`، والقيمة نصه المعروض.
     */
    function tqa_active_filters($base, $on = array(), $clear_extra = array())
    {
        $on = array_filter($on, function ($v) { return trim((string) $v) !== ''; });
        if (!$on) return;

        echo '<div class="tqa-activefilters">';
        echo '<span>' . t('مرشح الآن:') . '</span>';

        foreach ($on as $key => $label) {
            $drop = array_merge(array($key => null, 'p' => null), $clear_extra);
            echo '<span class="tqa-chip">' . html_escape($label)
               . '<a class="tqa-chip__x" href="' . html_escape(tqa_url_with($base, $drop)) . '"'
               . ' aria-label="' . te('انزع مرشح ____', array($label)) . '"'
               . ' title="' . te('انزع هذا المرشح') . '">&times;</a></span>';
        }

        if (count($on) > 1) {
            $all = array('p' => null);
            foreach (array_keys($on) as $k) $all[$k] = null;
            echo '<a class="tqa-chip" href="' . html_escape(tqa_url_with($base, array_merge($all, $clear_extra))) . '">'
               . t('امسح الكل') . '</a>';
        }

        echo '</div>';
    }
}

if (!function_exists('tqa_rowmenu')) {
    /**
     * TQ-ROW-CLUTTER — قائمة إجراءات الصف.
     *
     * عمود الإجراءات كان يكتب بيد في كل شاشة: `<div style="display:flex">`
     * وفيه رابط تعديل ونموذج حذف، وفي المقرر أربعة أزرار، وفي الاشتراكات
     * **حقل نص وزر وكاشف فيه نموذج ثان بحقله**. وثلاثة آثار لا واحد:
     * العمود يأكل ثلث الجدول، وأربعة أزرار متساوية الوزن لا تقول أيها
     * الخطر، وحقل نص في خلية يرفع الصف إلى ضعف جاره.
     *
     * فصار ما يفعل بالصف **قائمة واحدة**: زر في العمود، وفيه كل شيء
     * مرتبا — المقصود أولا، ثم التحرير، ثم الخطر تحت فاصل. والحقول التي
     * يلزمها إجراء (مرجع الحوالة · سبب إعادة القسمة) لوح داخل القائمة.
     *
     * وهي دالة واحدة لا شكل ينسخ: الشاشة التي تكتب غدا تصف ما تريد،
     * والشكل والسلوك وحراسة `CSRF` وترتيب الخطر تكتب هنا مرة.
     *
     * وكل بند صف في `$items`:
     *
     *   sep    => true                        فاصل
     *   note   => 'نص'                        سطر يقرأ ولا ينقر
     *   label  => 'نص'  مع href               رابط
     *                   مع onclick            زر جافاسكربت (النوافذ الموروثة)
     *                   مع action             نموذج POST، زره هو البند
     *   panel  => 'عنوان'  مع action و fields  لوح فيه حقول ثم زر
     *   list   => array(array('name','value')) صفوف تقرأ (قسمة الإيراد)
     *   html   => '<...>'                      وسم كما هو
     *
     * والمشترك: `icon` و`sub` (سطر تحت التسمية) و`tone` (`danger` ·
     * `go` للفعل المقصود) و`confirm` (مصفوفة `title`/`body`/`ok`/`tone`)
     * و`hidden` (حقول مخفية للنموذج).
     *
     * @param array $items بنود القائمة
     * @param array $opts  title · sub · label · icon · wide
     */
    function tqa_rowmenu($items, $opts = array())
    {
        static $seq = 0;

        $items = array_values(array_filter($items));
        if (!$items) return '';

        $seq++;
        $id = 'tqam' . $seq;

        $label = isset($opts['label']) && $opts['label'] !== '' ? $opts['label'] : t('إجراءات');

        $out  = '<div class="tqa-menu">';
        $out .= '<button type="button" class="tqa-menu__trigger" aria-haspopup="true"'
              . ' aria-expanded="false" aria-controls="' . $id . '">'
              . tq_icon('dots', 15)
              . '<span>' . html_escape($label) . '</span>'
              . '<span class="tqa-menu__caret" aria-hidden="true">' . tq_icon('chev-down', 13) . '</span>'
              . '</button>';

        /* بعض القوائم تحمل تفصيلا لا أمرا — قسمة إيراد بيعة على خمسة
           معلمين، ولوحين فيهما حقول وسطر يشرح. تلك تطلب عرضها صراحة،
           والباقي يبقى على العرض القاطع (انظر الكتلة ١١٫٤ في `admin.css`). */
        $out .= '<div class="tqa-menu__pop' . (!empty($opts['wide']) ? ' tqa-menu__pop--wide' : '')
              . '" id="' . $id . '" hidden>';

        /* رأس يقول أي صف هذا: اللوح يفتح **فوق** الجدول فيحجب صفه،
           فمن فتح قائمة الصف الحادي عشر ثم تردد لا يعرف أهي قائمته. */
        if (!empty($opts['title'])) {
            $out .= '<div class="tqa-menu__head"><b>' . html_escape($opts['title']) . '</b>'
                  . (!empty($opts['sub']) ? '<span>' . html_escape($opts['sub']) . '</span>' : '')
                  . '</div>';
        }

        foreach ($items as $it) {
            $out .= tqa_rowmenu_item($it);
        }

        $out .= '</div></div>';
        return $out;
    }
}

if (!function_exists('tqa_rowmenu_item')) {
    /** بند واحد من قائمة الصف. مفصولة لأن `tqa_rowmenu` تقرأ بها. */
    function tqa_rowmenu_item($it)
    {
        if (!is_array($it)) return '';

        if (!empty($it['sep']))  return '<div class="tqa-menu__sep" role="separator"></div>';
        if (!empty($it['html'])) return (string) $it['html'];

        if (isset($it['note'])) {
            return '<p class="tqa-menu__note">' . html_escape($it['note']) . '</p>';
        }

        /* صفوف تقرأ: «قسمة إيراد هذه البيعة» اسم ومبلغ لكل معلم. وهي
           في القائمة لا في الخلية لأنها جواب سؤال يسأل أحيانا، والخلية
           التي تحملها دائما تطول بطول عدد المعلمين. */
        if (isset($it['list'])) {
            $out = !empty($it['title'])
                 ? '<span class="tqa-menu__label">' . html_escape($it['title']) . '</span>' : '';
            /* قائمة بلا صف لا تطبع وعاءها: `<ul>` فارغة تترك حدا فاصلا
               فوق فراغ، فيقرأ اللوح مقطوعا. والعنوان يبقى لأن بعده سطر
               يقول لماذا لا صف فيها. */
            if (!$it['list']) return $out;
            $out .= '<ul class="tqa-menu__list">';
            foreach ((array) $it['list'] as $row) {
                $out .= '<li><b>' . html_escape(isset($row['name']) ? $row['name'] : '') . '</b>'
                      . '<span>' . (isset($row['value']) ? $row['value'] : '') . '</span></li>';
            }
            $out .= '</ul>';
            return $out;
        }

        $icon = isset($it['icon']) ? $it['icon'] : 'circle';
        $tone = isset($it['tone']) ? $it['tone'] : '';
        $cls  = 'tqa-menu__item' . ($tone !== '' ? ' tqa-menu__item--' . $tone : '');

        $body = '<span class="tqa-menu__ic" aria-hidden="true">' . tq_icon($icon, 15) . '</span>'
              . '<span class="tqa-menu__txt"><b>' . html_escape(isset($it['label']) ? $it['label'] : '') . '</b>'
              . (!empty($it['sub']) ? '<span>' . html_escape($it['sub']) . '</span>' : '')
              . '</span>';

        /* ---- لوح بحقول: إجراء لا ينفذ حتى تكتب قيمته ----
           والحقل داخل لوح مخفي **لا يقع في TQ-REQ-HIDDEN**: النموذج
           لا يرسل إلا من زر داخله، وهو لا يضغط إلا واللوح مفتوح. */
        if (isset($it['panel'])) {
            $out  = '<div class="tqa-menu__form">';
            $out .= '<b>' . tq_icon($icon, 14) . '<span>' . html_escape($it['panel']) . '</span></b>';
            $out .= '<form method="post" action="' . html_escape(site_url($it['action'])) . '"'
                  . tqa_rowmenu_confirm($it) . '>' . tq_csrf();

            foreach ((array) (isset($it['hidden']) ? $it['hidden'] : array()) as $k => $v) {
                $out .= '<input type="hidden" name="' . html_escape($k) . '" value="' . html_escape($v) . '">';
            }

            foreach ((array) (isset($it['fields']) ? $it['fields'] : array()) as $f) {
                $out .= '<input class="tqa-input' . (!empty($f['ltr']) ? ' tq-ltr' : '') . '"'
                      . ' type="' . html_escape(isset($f['type']) ? $f['type'] : 'text') . '"'
                      . ' name="' . html_escape($f['name']) . '"'
                      . (!empty($f['ltr']) ? ' dir="ltr"' : '')
                      . (isset($f['value']) ? ' value="' . html_escape($f['value']) . '"' : '')
                      . (!empty($f['maxlength']) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '')
                      . (!empty($f['required']) ? ' required' : '')
                      . ' placeholder="' . html_escape(isset($f['placeholder']) ? $f['placeholder'] : '') . '"'
                      . ' aria-label="' . html_escape(isset($f['placeholder']) ? $f['placeholder'] : $f['name']) . '">';
            }

            $btn = 'tqa-btn tqa-btn--sm ' . ($tone === 'danger' ? 'tqa-btn--danger' : 'tqa-btn--mastery');
            $out .= '<button type="submit" class="' . $btn . '">'
                  . html_escape(isset($it['submit']) ? $it['submit'] : t('نفذ')) . '</button>';

            if (!empty($it['sub'])) $out .= '<small>' . html_escape($it['sub']) . '</small>';

            $out .= '</form></div>';
            return $out;
        }

        /* ---- نموذج POST: البند نفسه هو زر الإرسال ----
           والحذف نموذج لا رابط: رابط GET يحذف ينفذ بمجرد جلبه — من
           زاحف أو من استباق تحميل يفعله المتصفح. */
        if (!empty($it['action'])) {
            $out  = '<form method="post" action="' . html_escape(site_url($it['action'])) . '"'
                  . ' style="margin:0"' . tqa_rowmenu_confirm($it) . '>' . tq_csrf();
            foreach ((array) (isset($it['hidden']) ? $it['hidden'] : array()) as $k => $v) {
                $out .= '<input type="hidden" name="' . html_escape($k) . '" value="' . html_escape($v) . '">';
            }
            $out .= '<button type="submit" class="' . $cls . '">' . $body . '</button></form>';
            return $out;
        }

        /* ---- زر جافاسكربت: النوافذ الموروثة (`showAjaxModal`) ---- */
        if (!empty($it['onclick'])) {
            return '<button type="button" class="' . $cls . '" onclick="' . html_escape($it['onclick']) . '">'
                 . $body . '</button>';
        }

        /* ---- رابط ---- */
        if (!empty($it['href'])) {
            return '<a class="' . $cls . '" href="' . html_escape($it['href']) . '"'
                 . (!empty($it['target']) ? ' target="' . html_escape($it['target']) . '" rel="noopener"' : '')
                 . tqa_rowmenu_confirm($it) . '>' . $body . '</a>';
        }

        /* بند بلا وجهة يعرض معطلا لا يحذف: الشاشة التي بنته تقصد شيئا،
           وحذفه صامتا يخفي عنها أنها نسيت مساره. */
        return '<span class="' . $cls . '" aria-disabled="true">' . $body . '</span>';
    }
}

if (!function_exists('tqa_rowmenu_confirm')) {
    /** سمات التأكيد — تقرؤها `TQA.confirm()` من [admin.js]، وهي الباب الواحد. */
    function tqa_rowmenu_confirm($it)
    {
        if (empty($it['confirm'])) return '';
        $c = (array) $it['confirm'];
        $s = ' data-tqa-confirm="' . html_escape(isset($c['body']) ? $c['body'] : '') . '"';
        if (!empty($c['title'])) $s .= ' data-tqa-confirm-title="' . html_escape($c['title']) . '"';
        if (!empty($c['ok']))    $s .= ' data-tqa-confirm-ok="' . html_escape($c['ok']) . '"';
        $tone = !empty($c['tone']) ? $c['tone'] : (isset($it['tone']) && $it['tone'] === 'danger' ? 'danger' : '');
        if ($tone !== '') $s .= ' data-tqa-confirm-tone="' . html_escape($tone) . '"';
        return $s;
    }
}

if (!function_exists('tqa_stat')) {
    /**
     * بطاقة رقم.
     *
     * كانت تكتب بيد في كل شاشة بأربعة أشكال: `tqa-stat-num` في أربع،
     * و`tqa-stat__value` في أخرى، وبلا أيقونة ولا نبرة في كلها — فأربعة
     * أرقام بيضاء متطابقة لا تقول أيها خبر سار وأيها عمل ينتظر.
     *
     * والنبرة ليست زخرفة: «فواتير غير مدفوعة ١١» و«اشتراكات نشطة ٧»
     * رقمان بمعنيين متضادين، ومن يمسح الشاشة بعينه في ثانيتين يحتاج
     * أن يفرق بينهما قبل أن يقرأ عنوانيهما.
     *
     * @param string $label العنوان
     * @param mixed  $value الرقم أو النص — يطبع كما هو (قد يكون مبلغا مصاغا)
     * @param array  $o     icon · tone (ok · warn · danger · info) · hint · href · fill
     */
    function tqa_stat($label, $value, $o = array())
    {
        $tone = isset($o['tone']) ? $o['tone'] : '';
        $cls  = 'tqa-stat' . ($tone !== '' ? ' tqa-stat--tone tqa-stat--' . $tone : '');
        $href = isset($o['href']) ? $o['href'] : '';

        /* الأرضية الباستيلية للأيقونة تشتق من النبرة، فلا تكتب مرتين
           ولا تفترق عن شريط الطرف. */
        $fill = isset($o['fill']) ? $o['fill'] : array(
            'ok' => 'tqa-mint', 'warn' => 'tqa-peach',
            'danger' => 'tqa-rose', 'info' => 'tqa-sky',
        );
        $fill = is_array($fill) ? (isset($fill[$tone]) ? $fill[$tone] : 'tqa-sky') : $fill;

        $out  = $href !== ''
              ? '<a class="' . $cls . '" href="' . html_escape($href) . '">'
              : '<div class="' . $cls . '">';

        $out .= '<div class="tqa-stat__top">'
              . '<span class="tqa-stat__label">' . html_escape($label) . '</span>'
              . (!empty($o['icon'])
                    ? '<span class="tqa-stat__icon ' . $fill . '" aria-hidden="true">'
                      . tq_icon($o['icon'], 17) . '</span>'
                    : '')
              . '</div>';

        $out .= '<span class="tqa-stat__value">' . $value . '</span>';
        if (!empty($o['hint'])) $out .= '<span class="tqa-stat__hint">' . html_escape($o['hint']) . '</span>';

        $out .= $href !== '' ? '</a>' : '</div>';
        return $out;
    }
}
