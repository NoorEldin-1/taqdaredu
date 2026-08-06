<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * المواد التعليمية — ملفات دروس الطالب في مكان واحد.
 *
 * موصول بالقاعدة: resource_files (ملفات الدرس) ومرفقات lesson، في الكورسات
 * المسجلة وحدها (enrol)، وحجم كل ملف من القرص لا من عمود مخزن.
 *
 * لكل نوع أيقونة ولون ثابتان في كل شاشة من المنصة — مصدرهما tq_file_kind()
 * في tq_student_styles.php. تغييرهما في شاشة واحدة يجعل الطالب يعيد التعرف
 * على النوع في كل صفحة.
 *
 * بلا مصدر بعد — وقد فتشت القاعدة كلها قبل أن يقال ذلك:
 *   حصة التخزين: لا عمود في plans ولا في subscriptions ولا مفتاح في settings
 *   يحمل سعة مسموحة (plans.features نص تسويقي لا رقم). فلا نسبة ولا شريط،
 *   ويعرض الحجم الحقيقي المستهلك وحده.
 *   تفضيل المواد: users.wishlist معرفات كورسات لا ملفات، ولا جدول تفضيل
 *   للملفات. فحالة فارغة صادقة لا بطاقات وهمية.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'materials';
$tq_role  = 'student';
$tq_title = 'المواد التعليمية';
$tq_sub   = 'جميع الملفات والموارد الخاصة بدروسك';
$tq_icon  = 'folder';

$tq_all = tq_s_materials($tq_uid);

/* --- التصفية: النوع والبحث، كلاهما في الرابط فيمكن حفظه --- */
$tq_types = [
    ''      => 'الكل',
    'pdf'   => 'ملفات PDF',
    'video' => 'فيديوهات',
    'slide' => 'عروض تقديمية',
    'audio' => 'ملفات صوتية',
    'image' => 'صور',
    'link'  => 'روابط خارجية',
];

$f_type = (string) $this->input->get('type', true);
if (!isset($tq_types[$f_type])) $f_type = '';
$f_q    = trim((string) $this->input->get('q', true));
$f_page = max(1, (int) $this->input->get('page', true));

$tq_by_type = [];
$tq_by_subject = [];
$tq_bytes = 0;
foreach ($tq_all as $m) {
    $k = $m['kind']['key'];
    $tq_by_type[$k] = ($tq_by_type[$k] ?? 0) + 1;
    $s = $m['subject'] !== '' ? $m['subject'] : $m['course'];
    $tq_by_subject[$s] = ($tq_by_subject[$s] ?? 0) + 1;
    $tq_bytes += $m['bytes'];
}
arsort($tq_by_subject);

$tq_list = array_values(array_filter($tq_all, function ($m) use ($f_type, $f_q) {
    if ($f_type !== '' && $m['kind']['key'] !== $f_type) return false;
    if ($f_q !== '' && mb_stripos($m['title'] . ' ' . $m['course'] . ' ' . $m['lesson'], $f_q, 0, 'UTF-8') === false) return false;
    return true;
}));

$tq_per   = 10;
$tq_pages = max(1, (int) ceil(count($tq_list) / $tq_per));
if ($f_page > $tq_pages) $f_page = $tq_pages;
$tq_page_items = array_slice($tq_list, ($f_page - 1) * $tq_per, $tq_per);

$tq_url = function ($over = []) use ($f_type, $f_q, $f_page) {
    $p = array_merge(['type' => $f_type, 'q' => $f_q, 'page' => $f_page], $over);
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null && $v !== 1; });
    return base_url('student/materials') . ($p ? '?' . http_build_query($p) : '');
};

/* حصة التخزين: صفر لأنه لا مصدر لها في القاعدة — لا عمود سعة في plans ولا
   في subscriptions ولا مفتاح في settings. والصفر هنا ليس رقما مدعى: هو
   الشرط الذي يخفي الشريط ويبقي الحجم الحقيقي وحده معروضا أدناه. */
$tq_quota = 0;

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <nav class="tq-tabs tq-s-tabs" aria-label="تصفية المواد بالنوع">
            <?php foreach ($tq_types as $key => $label): ?>
                <?php $n = $key === '' ? count($tq_all) : ($tq_by_type[$key] ?? 0); ?>
                <a class="tq-tab" href="<?php echo $tq_url(['type' => $key, 'page' => null]); ?>"
                   <?php echo $f_type === $key ? 'aria-current="page"' : ''; ?>>
                    <?php echo html_escape($label); ?>
                    <span class="tq-tab__n"><?php echo TQ_LRI . (int) $n . TQ_PDI; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($tq_all)): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'folder', 'mint',
                    'لا مواد بعد',
                    'ملفات دروسك — من ملازم PDF وفيديوهات وعروض وملفات صوتية — تظهر هنا في جدول واحد بحجمها وتاريخها وزر تحميلها.',
                    'تصفح دروسك',
                    base_url('student/lessons'),
                    false,
                    'primary'
                ); ?>
            </div>
        <?php else: ?>

            <!-- بطاقات الإحصاء بالنوع -->
            <div class="tq-s-grid5 tq-section">
                <?php
                $cards = [
                    ['',      'إجمالي المواد', 'ملف ومورد', 'file',  'sky'],
                    ['pdf',   'ملفات PDF',    'ملف',       'file',  'rose'],
                    ['video', 'فيديوهات',      'فيديو',      'video', 'sky'],
                    ['slide', 'عروض تقديمية',  'عرض',        'chart', 'peach'],
                    ['audio', 'ملفات صوتية',  'ملف',       'play',  'lilac'],
                ];
                foreach ($cards as [$key, $label, $unit, $icon, $pastel]):
                    $n = $key === '' ? count($tq_all) : ($tq_by_type[$key] ?? 0);
                    ?>
                    <a class="tq-card tq-s-tile tq-pastel tq-pastel--<?php echo $pastel; ?>"
                       href="<?php echo $tq_url(['type' => $key, 'page' => null]); ?>">
                        <span class="tq-row" style="gap:var(--tq-space-s)">
                            <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon($icon); ?></span>
                            <span class="tq-pastel__title tq-strong"><?php echo html_escape($label); ?></span>
                        </span>
                        <span class="tq-pastel__title" style="display:block;font:var(--tq-type-numeralXl);unicode-bidi:isolate;direction:ltr;margin-block-start:var(--tq-space-s)">
                            <?php echo TQ_LRI . (int) $n . TQ_PDI; ?>
                        </span>
                        <span class="tq-pastel__body tq-micro"><?php echo html_escape($unit); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="tq-card">
                <div class="tq-card__head">
                    <h2 class="tq-card__title">
                        المواد
                        <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_list) . TQ_PDI; ?></span>
                    </h2>

                    <form method="get" action="<?php echo base_url('student/materials'); ?>" class="tq-row" style="gap:var(--tq-space-s)">
                        <?php if ($f_type !== ''): ?>
                            <input type="hidden" name="type" value="<?php echo html_escape($f_type); ?>">
                        <?php endif; ?>
                        <label class="tq-sr" for="tq-m-q">ابحث في المواد</label>
                        <input class="tq-input" id="tq-m-q" name="q" type="search"
                               value="<?php echo html_escape($f_q); ?>" placeholder="ابحث في المواد…">
                        <button class="tq-btn tq-btn--secondary" type="submit">بحث</button>
                    </form>
                </div>

                <?php if (empty($tq_list)): ?>
                    <?php echo tq_s_empty(
                        'search', 'sky',
                        'لا نتائج',
                        'لا مادة تطابق بحثك أو النوع المختار. جرب كلمة أخرى أو ارجع إلى كل المواد.',
                        'كل المواد',
                        base_url('student/materials'),
                        true
                    ); ?>
                <?php else: ?>
                    <table class="tq-table">
                        <caption class="tq-sr">مواد دروسك التعليمية</caption>
                        <thead>
                            <tr>
                                <th scope="col">الاسم</th>
                                <th scope="col">المادة</th>
                                <th scope="col">النوع</th>
                                <th scope="col">الحجم</th>
                                <th scope="col">التاريخ</th>
                                <th scope="col">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_page_items as $m): $k = $m['kind']; ?>
                                <tr>
                                    <td data-label="الاسم">
                                        <span class="tq-s-file">
                                            <span class="tq-s-file__box tq-pastel tq-pastel--<?php echo $k['pastel']; ?>" aria-hidden="true">
                                                <span class="tq-pastel__icon"><?php echo tq_icon($k['icon'], 18); ?></span>
                                            </span>
                                            <span>
                                                <span class="tq-s-item__t"><?php echo html_escape($m['title']); ?></span>
                                                <span class="tq-s-item__s"><?php echo html_escape($m['lesson']); ?></span>
                                            </span>
                                        </span>
                                    </td>
                                    <td data-label="المادة">
                                        <span class="tq-badge tq-badge--progress"><?php echo html_escape($m['subject'] !== '' ? $m['subject'] : $m['course']); ?></span>
                                    </td>
                                    <td data-label="النوع"><?php echo html_escape($k['label']); ?></td>
                                    <td data-label="الحجم">
                                        <?php echo $m['bytes'] > 0 ? tq_num(tq_s_size($m['bytes']), 'tq-num--sm') : '<span class="tq-muted">—</span>'; ?>
                                    </td>
                                    <td data-label="التاريخ">
                                        <?php echo $m['at'] > 0 ? tq_s_date($m['at']) : '<span class="tq-muted">—</span>'; ?>
                                    </td>
                                    <td data-label="الإجراء">
                                        <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo html_escape($m['url']); ?>"
                                           <?php echo $k['key'] === 'link' ? 'rel="noopener"' : 'download'; ?>>
                                            <?php echo tq_icon($k['key'] === 'link' ? 'play' : 'download', 16); ?>
                                            <?php echo $k['key'] === 'link' ? 'فتح' : 'تحميل'; ?>
                                            <span class="tq-sr"><?php echo html_escape($m['title']); ?></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($tq_pages > 1): ?>
                        <nav class="tq-s-pager" aria-label="صفحات المواد">
                            <?php if ($f_page > 1): ?>
                                <a href="<?php echo $tq_url(['page' => $f_page - 1]); ?>" rel="prev">السابق</a>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $tq_pages; $p++): ?>
                                <?php if ($p === $f_page): ?>
                                    <span aria-current="page"><?php echo TQ_LRI . $p . TQ_PDI; ?><span class="tq-sr">الصفحة الحالية</span></span>
                                <?php else: ?>
                                    <a href="<?php echo $tq_url(['page' => $p]); ?>"><?php echo TQ_LRI . $p . TQ_PDI; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($f_page < $tq_pages): ?>
                                <a href="<?php echo $tq_url(['page' => $f_page + 1]); ?>" rel="next">التالي</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <aside class="tq-aside">

        <!-- تصنيف المواد -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title">تصنيف المواد</h2></div>

            <?php if (empty($tq_by_subject)): ?>
                <?php echo tq_s_empty(
                    'folder', 'sky',
                    'لا تصنيفات بعد',
                    'مواد دروسك تجمع هنا حسب المادة مع عدد ملفات كل واحدة.',
                    '', '', true
                ); ?>
            <?php else: ?>
                <ul class="tq-s-list">
                    <?php $i = 0; foreach (array_slice($tq_by_subject, 0, 8, true) as $name => $n): ?>
                        <li class="tq-row tq-row--between">
                            <span class="tq-row" style="gap:var(--tq-space-s)">
                                <span class="tq-icon-box tq-pastel tq-pastel--<?php echo tq_pastel($i); ?>"
                                      style="inline-size:32px;block-size:32px" aria-hidden="true">
                                    <span class="tq-pastel__icon"><?php echo tq_icon('book', 16); ?></span>
                                </span>
                                <span class="tq-caption tq-s-trunc"><?php echo html_escape($name); ?></span>
                            </span>
                            <?php echo tq_num($n, 'tq-num--sm'); ?>
                        </li>
                    <?php $i++; endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- مساحة التخزين -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title">مساحة التخزين</h2></div>

            <?php if ($tq_quota > 0): ?>
                <p class="tq-caption" style="margin-block-end:var(--tq-space-s)">
                    <?php echo tq_iso(tq_s_size($tq_bytes) . ' من ' . tq_s_size($tq_quota) . ' مستخدمة'); ?>
                </p>
                <?php echo tq_progress((int) round($tq_bytes * 100 / $tq_quota), 'نسبة المساحة المستخدمة'); ?>
            <?php else: ?>
                <p class="tq-caption" style="margin-block-end:var(--tq-space-s)">حجم موادك الحالي</p>
                <p class="tq-strong" style="color:var(--tq-navy);margin-block-end:var(--tq-space-s)">
                    <?php echo $tq_bytes > 0 ? tq_num(tq_s_size($tq_bytes)) : '<span class="tq-muted">—</span>'; ?>
                </p>
                <p class="tq-micro" style="margin:0">
                    شريط النسبة يظهر هنا بعد تحديد حصة التخزين في باقتك.
                </p>
            <?php endif; ?>

            <a class="tq-btn tq-btn--secondary tq-btn--block" style="margin-block-start:var(--tq-space-l)"
               href="<?php echo base_url('plans'); ?>">إدارة الباقة</a>
        </section>

        <!-- المواد المفضلة: تفضيل الملفات لا مصدر له بعد (wishlist للكورسات). -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title">المواد المفضلة</h2>
                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('heart'); ?></span>
            </div>
            <?php echo tq_s_empty(
                'heart', 'rose',
                'لا مواد مفضلة',
                'أضف أي ملف إلى مفضلتك ليظهر هنا وتصل إليه دون بحث.',
                '', '', true
            ); ?>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
