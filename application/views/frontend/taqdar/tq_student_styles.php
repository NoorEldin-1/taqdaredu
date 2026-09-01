<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * شاشات الطالب — الأنماط والدوال المشتركة بين الشاشات الست.
 *
 * يضمن مرة واحدة من كل شاشة بعد portal_open.php. سببه أن الأساس
 * (tokens/base/components/layout) لا يعدل، وأن أيقونة نوع الملف ولونه
 * يجب أن يكونا واحدين في كل شاشة تعرض ملفا — فمصدرهما واحد هنا.
 *
 * كل لون من التوكنات، وكل تخطيط بـ start/end، وكل رقم يمر من tq_num/tq_iso.
 */

if (!function_exists('tq_s_month')) {
    /** أسماء الشهور الميلادية كما تكتب في السوق السعودي. */
    function tq_s_month($m)
    {
        static $names = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                         'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $i = ((int) $m) - 1;
        /* TQ-I18N — الترجمة عند الخروج: `static` لا يقبل نداء في تهيئته. */
        return isset($names[$i]) ? t($names[$i]) : '';
    }
}

if (!function_exists('tq_s_date')) {
    /** «20 مايو 2026» — الرقمان معزولان داخل الجملة. */
    function tq_s_date($ts)
    {
        $ts = (int) $ts;
        if ($ts <= 0) return '';
        return tq_iso(date('j', $ts) . ' ' . tq_s_month(date('n', $ts)) . ' ' . date('Y', $ts));
    }
}

if (!function_exists('tq_s_time')) {
    /** «10:00 ص» — الساعة وحدة واحدة لا رقمان. */
    function tq_s_time($ts)
    {
        $ts = (int) $ts;
        if ($ts <= 0) return '';
        $mer = (int) date('G', $ts) < 12 ? t('ص') : t('م');
        return tq_num(date('g:i', $ts)) . ' ' . $mer;
    }
}

/* `tq_s_secs` و`tq_file_kind` انتقلتا إلى
   [taqdar_student_helper.php](../../../helpers/taqdar_student_helper.php)
   — تحملان تلقائيا، ويقرؤهما `Api_v1` كما يقرؤهما القالب. وضمهما هنا
   كان يعني أن كل نداء عليهما من الواجهة يطبع كتلة `<style>` هذا الملف
   فوق JSON. */

if (!function_exists('tq_s_clock')) {
    /** ثوان إلى «45:30» — وحدة واحدة معزولة لا طرفان. */
    function tq_s_clock($seconds)
    {
        $s = max(0, (int) $seconds);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $x = $s % 60;
        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $x)
            : sprintf('%02d:%02d', $m, $x);
    }
}

if (!function_exists('tq_s_hours')) {
    /** ثوان إلى «12 س 45 د» — وحدة واحدة، والمثنى والجمع صحيحان. */
    function tq_s_hours($seconds)
    {
        $s = max(0, (int) $seconds);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        if ($h > 0) return $h . t(' س ') . $m . t(' د');
        return $m . t(' د');
    }
}

if (!function_exists('tq_s_minutes')) {
    /** «60 دقيقة» بصيغة عربية سليمة. */
    function tq_s_minutes($minutes)
    {
        $n = (int) $minutes;
        if ($n === 1) return t(' دقيقة');
        if ($n === 2) return t('دقيقتان');
        if ($n <= 10) return tq_iso($n . t(' دقائق'));
        return tq_iso($n . t(' دقيقة'));
    }
}

if (!function_exists('tq_s_level')) {
    /**
     * مستوى الكورس بالعربية.
     *
     * `course.level` يخزن قيم Academy الإنجليزية (`beginner` · `intermediate`
     * · `advanced`)، وكانت تطبع كما هي تحت عنوان عربي في بطاقة كل كورس —
     * كلمة لاتينية وحيدة وسط شاشة عربية بالكامل. وما ليس من القيم المعروفة
     * يعرض كما هو: قد يكون المعلم كتب مرحلة عربية بيده، وترجمتها تخريف.
     */
    function tq_s_level($level)
    {
        static $map = [
            'beginner'     => 'مستوى مبتدئ',
            'intermediate' => 'مستوى متوسط',
            'advanced'     => 'مستوى متقدم',
            'expert'       => 'مستوى متقدم',
        ];
        $k = strtolower(trim((string) $level));
        /* TQ-I18N — الترجمة عند الخروج: `static` لا يقبل نداء دالة في تهيئته. */
        return t($map[$k] ?? trim((string) $level));
    }
}

if (!function_exists('tq_s_lessons_word')) {
    /** «12 من 20 درسا» — التمييز مفرد منصوب بعد الأحد عشر فما فوق. */
    function tq_s_lessons_word($done, $total)
    {
        $total = (int) $total;
        $word = $total === 1 ? t('درس') : ($total === 2 ? t('درسان') : ($total <= 10 ? t('دروس') : t('درسا')));
        return tq_iso((int) $done . t(' من ') . $total . ' ' . $word);
    }
}

if (!function_exists('tq_s_size')) {
    /** حجم الملف — وحدة واحدة معزولة، والوحدة لاتينية كما تقرأ. */
    function tq_s_size($bytes)
    {
        $b = (float) $bytes;
        if ($b <= 0) return '';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < count($units) - 1) { $b /= 1024; $i++; }
        return ($i >= 2 ? number_format($b, 1) : round($b)) . ' ' . $units[$i];
    }
}


if (!function_exists('tq_s_cover')) {
    /** برنامج غلاف الكورس إن وجد فعلا على القرص، وإلا فارغ فيرسم بديل CSS. */
    function tq_s_cover($thumbnail)
    {
        $t = trim((string) $thumbnail);
        if ($t === '') return '';
        $rel = strpos($t, 'uploads/') === 0 ? $t : 'uploads/thumbnails/course_thumbnails/' . $t;
        return is_file(FCPATH . $rel) ? base_url($rel) : '';
    }
}

if (!function_exists('tq_s_thumb')) {
    /**
     * غلاف البطاقة: الصورة إن وجدت، وإلا تدرج CSS بحرف المادة —
     * ولا صورة من الإنترنت ولا حزمة أيقونات خارجية.
     */
    function tq_s_thumb($title, $thumbnail = '', $index = 0, $badge = '', $tag = '')
    {
        $src  = tq_s_cover($thumbnail);
        $fam  = tq_pastel($index);
        $html = '<div class="tq-s-thumb tq-s-thumb--' . $fam . '">';
        if ($src !== '') {
            $html .= '<img src="' . html_escape($src) . '" alt="' . html_escape(t('غلاف ') . $title) . '" loading="lazy">';
        } else {
            $html .= '<span class="tq-s-thumb__glyph" aria-hidden="true">'
                  . html_escape(mb_substr(trim((string) $title), 0, 1, 'UTF-8')) . '</span>';
        }
        if ($badge !== '') $html .= '<span class="tq-s-thumb__badge">' . $badge . '</span>';
        if ($tag !== '')   $html .= '<span class="tq-s-thumb__tag">' . $tag . '</span>';
        return $html . '</div>';
    }
}

if (!function_exists('tq_s_empty')) {
    /**
     * الحالة الفارغة: رسم + سطر يقول ما الذي سيظهر هنا + زر يبدأ الفعل.
     * لا نملأ الفراغ ببيانات وهمية — الفراغ الصادق أنفع من رقم مخترع.
     */
    function tq_s_empty($icon, $pastel, $title, $text, $cta = '', $href = '', $tight = false, $variant = 'secondary')
    {
        $h  = '<div class="tq-empty' . ($tight ? ' tq-s-empty--tight' : '') . '">';
        $h .= '<span class="tq-s-art tq-pastel tq-pastel--' . html_escape($pastel) . '" aria-hidden="true">'
            . '<span class="tq-pastel__icon">' . tq_icon($icon, $tight ? 24 : 34) . '</span></span>';
        $h .= '<h3 class="tq-empty__title">' . html_escape($title) . '</h3>';
        $h .= '<p class="tq-empty__text">' . html_escape($text) . '</p>';
        // الزر الأساسي واحد في الشاشة، فالحالة الفارغة ثانوية إلا حين تكون
        // هي الفعل الوحيد في الصفحة — عندئذ تطلب primary صراحة.
        if ($cta !== '' && $href !== '') {
            $kind = $variant === 'primary' ? 'tq-btn--primary' : 'tq-btn--secondary';
            $h .= '<a class="tq-btn ' . $kind . ($tight ? ' tq-btn--sm' : '') . '" href="'
                . html_escape($href) . '">' . html_escape($cta) . '</a>';
        }
        return $h . '</div>';
    }
}

if (!function_exists('tq_s_days_left')) {
    /** عدد الأيام حتى الموعد؛ سالبا إن فات. */
    function tq_s_days_left($ts)
    {
        $today = strtotime('today');
        return (int) floor((strtotime('today', (int) $ts) - $today) / 86400);
    }
}

if (!function_exists('tq_s_when')) {
    /** «بعد يومين» · «متأخرة» — النص يقول القرب، واللون يؤكده ولا يحمله وحده. */
    function tq_s_when($ts)
    {
        $d = tq_s_days_left($ts);
        if ($d < 0)  return ['kind' => 'late', 'text' => t('متأخرة')];
        if ($d === 0) return ['kind' => 'late', 'text' => t('اليوم')];
        if ($d === 1) return ['kind' => 'due',  'text' => t('غدا')];
        if ($d === 2) return ['kind' => 'due',  'text' => t('بعد يومين')];
        if ($d <= 10) return ['kind' => 'due',  'text' => tq_iso(t('بعد ') . $d . t(' أيام'))];
        return ['kind' => 'progress', 'text' => tq_iso(t('بعد ') . $d . t(' يوما'))];
    }
}

if (!function_exists('tq_s_calendar')) {
    /**
     * تقويم شهر واحد. الأسبوع يبدأ الأحد — السوق سعودي.
     * $marks: [يوم الشهر => 'done'|'due'|'late'] ولا يعلم يوم بلا مصدر حقيقي.
     */
    function tq_s_calendar($ts, $marks = [])
    {
        $ts    = (int) $ts ?: time();
        $y     = (int) date('Y', $ts);
        $m     = (int) date('n', $ts);
        $first = mktime(0, 0, 0, $m, 1, $y);
        $lead  = (int) date('w', $first);          // 0 = الأحد
        $days  = (int) date('t', $first);
        $today = (date('Y-n') === $y . '-' . $m) ? (int) date('j') : 0;

        $names = [t('أحد'), t('إثنين'), t('ثلاثاء'), t('أربعاء'), t('خميس'), t('جمعة'), t('سبت')];

        $h  = '<table class="tq-s-cal"><caption class="tq-s-cal__cap">'
            . tq_iso(tq_s_month($m) . ' ' . $y) . '</caption><thead><tr>';
        foreach ($names as $n) $h .= '<th scope="col">' . $n . '</th>';
        $h .= '</tr></thead><tbody><tr>';

        for ($i = 0; $i < $lead; $i++) $h .= '<td></td>';
        $col = $lead;
        for ($d = 1; $d <= $days; $d++) {
            if ($col === 7) { $h .= '</tr><tr>'; $col = 0; }
            $kind = $marks[$d] ?? '';
            $cls  = 'tq-s-cal__day';
            if ($kind !== '') $cls .= ' tq-s-cal__day--' . $kind;
            if ($d === $today) $cls .= ' tq-s-cal__day--today';
            $sr = $d === $today ? t('<span class="tq-sr">اليوم</span>') : '';
            $h .= '<td><span class="' . $cls . '">' . tq_num($d, 'tq-num--sm') . $sr . '</span></td>';
            $col++;
        }
        while ($col++ < 7) $h .= '<td></td>';

        return $h . '</tr></tbody></table>';
    }
}

if (!function_exists('tq_s_key')) {
    /** مفتاح ألوان التقويم — اللون لا يحمل معنى وحده، فالنص بجواره. */
    function tq_s_key($items)
    {
        $h = '<ul class="tq-s-key">';
        foreach ($items as $kind => $label) {
            $h .= '<li><span class="tq-s-dot tq-s-dot--' . html_escape($kind) . '" aria-hidden="true"></span>'
                . html_escape($label) . '</li>';
        }
        return $h . '</ul>';
    }
}

if (!function_exists('tq_s_stat')) {
    /** بطاقة رقم في العمود الجانبي: الرقم أولا ثم تسميته، والفرق إن كان له مصدر. */
    function tq_s_stat($value, $label, $icon = '', $pastel = 'sky', $note = '')
    {
        $h  = '<div class="tq-s-stat tq-pastel tq-pastel--' . html_escape($pastel) . '">';
        if ($icon !== '') {
            $h .= '<span class="tq-pastel__icon tq-s-stat__icon" aria-hidden="true">' . tq_icon($icon, 18) . '</span>';
        }
        $h .= '<span class="tq-s-stat__value tq-pastel__title">' . $value . '</span>';
        $h .= '<span class="tq-s-stat__label tq-pastel__body">' . html_escape($label) . '</span>';
        if ($note !== '') $h .= '<span class="tq-s-stat__note">' . $note . '</span>';
        return $h . '</div>';
    }
}
?>
<style data-tq-student-styles>
/* شاشات الطالب — أنماط الصفحة. توكنات فقط، وبلا left/right. */

/* --- الأغلفة --- */
.tq-s-thumb {
  position: relative;
  aspect-ratio: 16 / 9;
  border-radius: var(--tq-radius-medium);
  overflow: hidden;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, var(--tq-actionPrimary), var(--tq-actionMastery));
}
.tq-s-thumb img { inline-size: 100%; block-size: 100%; object-fit: cover; }
.tq-s-thumb__glyph { font: var(--tq-type-hero); color: var(--tq-navInkOn); opacity: .5; }
.tq-s-thumb--mint  { background: linear-gradient(135deg, var(--tq-actionMastery), var(--tq-actionPrimary)); }
.tq-s-thumb--sky   { background: linear-gradient(135deg, var(--tq-navySoft), var(--tq-navyDeep)); }
.tq-s-thumb--peach { background: linear-gradient(160deg, var(--tq-actionPrimary), var(--tq-actionMastery)); }
.tq-s-thumb--lilac { background: linear-gradient(135deg, var(--tq-navyDeep), var(--tq-navySoft)); }
.tq-s-thumb--rose  { background: linear-gradient(135deg, var(--tq-navySoft), var(--tq-teal)); }
.tq-s-thumb--sand  { background: linear-gradient(135deg, var(--tq-navyDeep), var(--tq-actionPrimary)); }
.tq-s-thumb__badge { position: absolute; inset-block-start: var(--tq-space-s); inset-inline-start: var(--tq-space-s); }
.tq-s-thumb__tag {
  position: absolute; inset-block-end: var(--tq-space-s); inset-inline-end: var(--tq-space-s);
  padding: 2px var(--tq-space-s);
  border-radius: var(--tq-radius-pill);
  background: var(--tq-navyDeep); color: var(--tq-navInkOn);
  font: var(--tq-type-numeralSm);
  unicode-bidi: isolate; direction: ltr;
}

/* --- بطاقة كورس --- */
.tq-s-course { display: flex; flex-direction: column; gap: var(--tq-space-m); }
.tq-s-course__title { font: var(--tq-type-bodyStrong); color: var(--tq-navy); margin: 0; }
.tq-s-course a:hover { text-decoration: none; }

/* بطاقة كاملة قابلة للنقر: الرابط كتلة لا سطر، فلا ينكسر محتواها */
.tq-s-tile { display: block; }
.tq-s-tile:hover { text-decoration: none; }

/* --- صفوف الميتا --- */
.tq-s-meta { display: flex; flex-wrap: wrap; align-items: center; gap: var(--tq-space-m); color: var(--tq-text2); font: var(--tq-type-caption); }
.tq-s-meta > span { display: inline-flex; align-items: center; gap: var(--tq-space-xs); }
.tq-s-meta svg { color: var(--tq-text2); }

/* --- التبويبات الرابطة (تصفية من الخادم، تعمل بلا جافاسكربت) --- */
.tq-s-tabs { overflow-x: auto; scrollbar-width: thin; }
.tq-s-tabs a.tq-tab { color: var(--tq-text2); }
.tq-s-tabs a.tq-tab:hover { color: var(--tq-navy); text-decoration: none; }
.tq-s-tabs a.tq-tab[aria-current='page'] {
  color: var(--tq-navy); border-block-end-color: var(--tq-navy); font-weight: 700;
}
.tq-s-tabs .tq-tab__n {
  margin-inline-start: var(--tq-space-xs);
  padding: 1px var(--tq-space-s);
  border-radius: var(--tq-radius-pill);
  background: var(--tq-navyWash); color: var(--tq-navy);
  font: var(--tq-type-numeralSm);
  unicode-bidi: isolate; direction: ltr;
}

/* --- الحالة الفارغة --- */
.tq-s-art {
  inline-size: 88px; block-size: 88px;
  border-radius: var(--tq-radius-pill);
  display: grid; place-items: center;
}
.tq-s-empty--tight { padding: var(--tq-space-xl) var(--tq-space-m); gap: var(--tq-space-s); }
.tq-s-empty--tight .tq-s-art { inline-size: 56px; block-size: 56px; }
.tq-s-empty--tight .tq-empty__title { font: var(--tq-type-bodyStrong); color: var(--tq-navy); }
.tq-s-empty--tight .tq-empty__text { font: var(--tq-type-caption); }

/* --- بطاقات الأرقام --- */
.tq-s-stat { display: grid; gap: var(--tq-space-xs); padding: var(--tq-space-l); border-radius: var(--tq-radius-medium); }
/* القيمة قد تخلط رقما بوحدة عربية («12 س 45 د») فتبقى الجملة عربية
   والأرقام معزولة داخلها — ولا يفرض اتجاه لاتيني على السطر كله. */
.tq-s-stat__value { font: var(--tq-type-numeralXl); unicode-bidi: isolate; }
.tq-s-stat__label { font: var(--tq-type-caption); }
.tq-s-stat__note  { font: var(--tq-type-micro); color: var(--tq-text2); }
.tq-s-stat__icon  { display: inline-flex; }
.tq-s-stat--plain { background: var(--tq-ground); }
.tq-s-2x2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--tq-space-m); }

/* --- النقاط ومفتاح الألوان --- */
.tq-s-dot { inline-size: 9px; block-size: 9px; border-radius: var(--tq-radius-pill); flex: none; display: inline-block; }
.tq-s-dot--done { background: var(--tq-teal); }
.tq-s-dot--due  { background: var(--tq-amber); }
.tq-s-dot--late { background: var(--tq-danger); }
.tq-s-dot--idle { background: var(--tq-line); }
.tq-s-dot--live { background: var(--tq-teal); }
.tq-s-key { display: flex; flex-wrap: wrap; gap: var(--tq-space-m); font: var(--tq-type-micro); color: var(--tq-text2); margin-block-start: var(--tq-space-m); }
.tq-s-key li { display: inline-flex; align-items: center; gap: var(--tq-space-xs); }

/* --- التقويم --- */
.tq-s-cal { inline-size: 100%; border-collapse: collapse; text-align: center; }
.tq-s-cal__cap { font: var(--tq-type-bodyStrong); color: var(--tq-navy); padding-block-end: var(--tq-space-m); }
.tq-s-cal th { font: var(--tq-type-micro); color: var(--tq-text2); font-weight: 600; padding-block-end: var(--tq-space-s); text-align: center; border: 0; }
.tq-s-cal td { padding: 2px; border: 0; }
.tq-s-cal__day {
  display: grid; place-items: center;
  inline-size: 32px; block-size: 32px; margin-inline: auto;
  border-radius: var(--tq-radius-pill);
  color: var(--tq-text2);
}
/* «اليوم» حلقة لا خلفية وحدها: قواعد الحالات بعده بالوزن نفسه فتغلب
   خلفيته، وكان اليوم الذي عليه مهمة يفقد علامته تماما — والطالب يفتح
   التقويم أول ما يفتحه ليعرف أين هو منه. والحلقة تنجو من الغلبة. */
.tq-s-cal__day--today {
  background: var(--tq-actionPrimary); color: var(--tq-onAction);
  box-shadow: 0 0 0 2px var(--tq-actionPrimary);
}
.tq-s-cal__day--done  { background: var(--tq-tealSoft); color: var(--tq-teal); }
.tq-s-cal__day--due   { background: var(--tq-amberSoft); color: var(--tq-navy); }
.tq-s-cal__day--late  { background: var(--tq-dangerSoft); color: var(--tq-danger); }

/* --- بانر --- */
.tq-s-banner {
  position: relative; overflow: hidden;
  border-radius: var(--tq-radius-soft);
  padding: var(--tq-space-h1);
  background:
    radial-gradient(560px 240px at 85% -20%, var(--tq-mint-fill), transparent 65%),
    linear-gradient(120deg, var(--tq-sky-fill), var(--tq-surface));
}
.tq-s-banner__art {
  position: absolute; inset-block-start: 50%; inset-inline-end: var(--tq-space-h1);
  transform: translateY(-50%);
  inline-size: 160px; block-size: 160px;
  border-radius: var(--tq-radius-pill);
  background: linear-gradient(145deg, var(--tq-actionPrimary), var(--tq-actionMastery));
  display: grid; place-items: center; color: var(--tq-navInkOn);
  opacity: .92;
}
.tq-s-banner__body { max-inline-size: 46ch; position: relative; }
@media (max-width: 767.98px) { .tq-s-banner__art { display: none; } }

/* بانر بلا زخرفة: النص وحده. `46ch` كانت تحجز نصف البطاقة للقرص، فبلا قرص
   تصير فجوة. والقياس يبقى محدودا (`68ch`) لأن سطرا يمتد بعرض الشاشة يفقد
   القارئ موضعه في السطر التالي. والوهج ينقل من `85%` — حيث كان القرص —
   إلى ناحية النص فيضيء ما يقرأ لا فراغا. */
/* --------------------------------------------------------------------------
   حلقة الهدف والسلسلة — نظام التلعيب (F2.6)
   --------------------------------------------------------------------------
   الحلقة `stroke-dasharray` على دائرة نصف قطرها 15.9 — محيطها 99.9 ≈ 100،
   فالنسبة المئوية تكتب في `dasharray` كما هي بلا حساب. و`dashoffset: 25`
   يدير نقطة البدء إلى أعلى الدائرة.
   والاتجاه: الحلقة رقم لا نص، فلا تنعكس في RTL — ولذلك لا `transform`
   هنا يقلبها. */
.tq-s-goal {
  position: absolute; inset-block-start: 50%; inset-inline-end: var(--tq-space-h1);
  transform: translateY(-50%);
  display: flex; flex-direction: column; align-items: center; gap: var(--tq-space-xs);
  text-align: center;
}
.tq-s-goal__ring { position: relative; display: grid; place-items: center; }
.tq-s-goal__ring svg { transform: rotate(-90deg); }
.tq-s-goal__track { stroke: var(--tq-line); }
.tq-s-goal__fill {
  stroke: var(--tq-actionMastery);
  transition: stroke-dasharray var(--tq-motion-ringFill) var(--tq-ease-enter);
}
.tq-s-goal__fill.is-met { stroke: var(--tq-tealLift); }
.tq-s-goal__num {
  position: absolute; font-weight: 800; font-size: .95rem;
  unicode-bidi: isolate; direction: ltr; color: var(--tq-navy);
}
.tq-s-goal__label { margin: 0; font-size: .78rem; color: var(--tq-text2); }
.tq-s-goal__streak {
  margin: 0; display: inline-flex; align-items: center; gap: 4px;
  font-size: .78rem; font-weight: 700; color: var(--tq-peach-ink);
}
.tq-s-goal__streak--off { color: var(--tq-text3); font-weight: 400; }

/* دون 900 بكسل تنزل الحلقة تحت النص بدل أن تزاحمه — والبطاقة تعود
   تدفقا عاديا فلا تحتاج `position` مطلقا. */
@media (max-width: 899.98px) {
  .tq-s-goal {
    position: static; transform: none;
    flex-direction: row; flex-wrap: wrap; justify-content: flex-start;
    gap: var(--tq-space-m); margin-block-start: var(--tq-space-xl);
  }
  .tq-s-goal__ring svg { width: 64px; height: 64px; }
}

/* شريط وضع الامتحان */
.tq-s-examstrip {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m); align-items: center;
  padding: var(--tq-space-m) var(--tq-space-l);
  border-radius: var(--tq-radius-medium);
  background: var(--tq-peach-fill); color: var(--tq-peach-ink);
  border: 1px solid color-mix(in srgb, var(--tq-amber) 28%, transparent);
}
.tq-s-examstrip__i { display: inline-flex; flex: none; }
.tq-s-examstrip p { margin: 0; flex: 1; min-inline-size: 200px; font-size: .9rem; }

.tq-s-banner--plain {
  background:
    radial-gradient(620px 260px at 92% -30%, var(--tq-mint-fill), transparent 70%),
    linear-gradient(120deg, var(--tq-sky-fill), var(--tq-surface));
}
.tq-s-banner--plain .tq-s-banner__body { max-inline-size: 68ch; }

/* --- صف المهمة والاختبار --- */
.tq-s-row {
  display: flex; align-items: center; gap: var(--tq-space-l);
  padding: var(--tq-space-l);
  background: var(--tq-surface);
  border: 1px solid var(--tq-line);
  border-radius: var(--tq-radius-medium);
}
.tq-s-row + .tq-s-row { margin-block-start: var(--tq-space-m); }
.tq-s-row__main { flex: 1; min-inline-size: 0; }
.tq-s-row__title { font: var(--tq-type-bodyStrong); color: var(--tq-navy); margin: 0 0 var(--tq-space-xs); }
.tq-s-row__end { display: grid; justify-items: center; gap: var(--tq-space-s); flex: none; }
.tq-s-date {
  flex: none; text-align: center;
  min-inline-size: 84px;
  padding: var(--tq-space-s);
  border-radius: var(--tq-radius-small);
  background: var(--tq-ground);
}
.tq-s-date__label { display: block; font: var(--tq-type-micro); color: var(--tq-text2); }
.tq-s-date__day   { display: block; font: var(--tq-type-numeralXl); color: var(--tq-navy); unicode-bidi: isolate; direction: ltr; }
.tq-s-date__month { display: block; font: var(--tq-type-micro); color: var(--tq-text2); }
@media (max-width: 639.98px) { .tq-s-row { flex-wrap: wrap; } }

/* --- عناوين المجموعات --- */
.tq-s-group { display: flex; align-items: center; gap: var(--tq-space-s); margin-block: var(--tq-space-xl) var(--tq-space-m); }
.tq-s-group h2 { margin: 0; font: var(--tq-type-h2); color: var(--tq-navy); }

/* --- الخطوات المرقمة --- */
.tq-s-steps { display: grid; gap: var(--tq-space-l); }
.tq-s-step { display: flex; gap: var(--tq-space-m); align-items: flex-start; }
.tq-s-step__n {
  flex: none; inline-size: 26px; block-size: 26px;
  border-radius: var(--tq-radius-pill);
  background: var(--tq-actionPrimary); color: var(--tq-onAction);
  display: grid; place-items: center;
  font: var(--tq-type-numeralSm);
  unicode-bidi: isolate; direction: ltr;
}
.tq-s-step__t { font: var(--tq-type-bodyStrong); color: var(--tq-navy); display: block; }
.tq-s-step__d { font: var(--tq-type-caption); color: var(--tq-text2); }

/* --- المواد: أيقونة النوع --- */
.tq-s-file { display: inline-flex; align-items: center; gap: var(--tq-space-s); }
.tq-s-file__box { inline-size: 36px; block-size: 36px; border-radius: var(--tq-radius-small); display: grid; place-items: center; flex: none; }

/* --- الترقيم --- */
.tq-s-pager { display: flex; justify-content: center; align-items: center; gap: var(--tq-space-xs); margin-block-start: var(--tq-space-xl); flex-wrap: wrap; }
.tq-s-pager a, .tq-s-pager span {
  min-inline-size: 40px; min-block-size: 40px;
  display: grid; place-items: center;
  padding-inline: var(--tq-space-m);
  border-radius: var(--tq-radius-small);
  color: var(--tq-text2); font: var(--tq-type-numeralSm);
}
.tq-s-pager a:hover { background: var(--tq-navyWash); color: var(--tq-navy); text-decoration: none; }
.tq-s-pager [aria-current='page'] { background: var(--tq-actionPrimary); color: var(--tq-onAction); }

/* --- شبكة بطاقات مضغوطة --- */
.tq-s-grid3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--tq-space-l); }
.tq-s-grid4 { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--tq-space-l); }
.tq-s-grid5 { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: var(--tq-space-m); }
@media (max-width: 1279.98px) { .tq-s-grid5 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1023.98px) { .tq-s-grid3, .tq-s-grid4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 639.98px)  { .tq-s-grid3, .tq-s-grid4, .tq-s-grid5 { grid-template-columns: minmax(0, 1fr); } }

/* --- قائمة العمود الجانبي --- */
.tq-s-list { display: grid; gap: var(--tq-space-m); }
.tq-s-item { display: flex; align-items: center; gap: var(--tq-space-m); }
/* عنصر بصف علوي وفعل تحته — الحجز الذي له باب حصة يفتح. `--stack` يقلب
   الاتجاه فقط، وبقية أصناف `tq-s-item__*` تعمل كما هي داخل الصف العلوي. */
.tq-s-item--stack { flex-direction: column; align-items: stretch; }
.tq-s-item__body { flex: 1; min-inline-size: 0; }
.tq-s-item__t { display: block; font: var(--tq-type-caption); font-weight: 700; color: var(--tq-navy); }
.tq-s-item__s { display: block; font: var(--tq-type-micro); color: var(--tq-text2); }
.tq-s-trunc { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* --- شريط زمني للاختبار الجاري --- */
.tq-s-timebar { block-size: var(--tq-progress-h); border-radius: var(--tq-radius-pill); background: var(--tq-line); overflow: hidden; }
.tq-s-timebar__fill { block-size: 100%; background: var(--tq-navy); border-radius: var(--tq-radius-pill); }
</style>
