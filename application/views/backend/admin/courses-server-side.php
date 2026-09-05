<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الكورسات.
 *
 * أعيدت كتابتها بهيكل `tqa-*` وبعرض من الخادم — انظر TQ-DT-GONE في
 * [Admin::courses()]. ما كان هنا: خمس بطاقات إحصاء من قالب Hyper، ونموذج
 * ترشيح بأربعة منتقيات `select2` غير محملة، وجدول DataTables يجلب صفوفه
 * بـPOST فيرد «Ajax error» في نافذة `alert()` بيضاء فوق اللوحة.
 *
 * والأعداد الثلاثة لكل صف (الدروس والأقسام والمسجلون) تجلب هنا **بثلاثة
 * استعلامات مجمعة لا بستين**: الشاشة السابقة كانت تنادي ثلاث دوال نموذج
 * لكل صف داخل حلقة الصفوف.
 */

$tq_ids = array();
foreach ($courses as $tq_c) {
    $tq_ids[] = (int) $tq_c['id'];
}

/** عد مجمع: صف واحد لكل كورس، لا استعلام لكل كورس. */
$tq_tally = function ($table, $col) use ($tq_ids) {
    $out = array();
    if (!$tq_ids) return $out;
    $ci = &get_instance();
    try {
        $rows = $ci->db->select($col . ' AS k, COUNT(*) AS n')
                       ->where_in($col, $tq_ids)
                       ->group_by($col)
                       ->get($table)->result_array();
        foreach ($rows as $r) $out[(int) $r['k']] = (int) $r['n'];
    } catch (Throwable $e) {
        /* جدول غائب لا يبيض الشاشة — عمود أرقام فارغ أهون. */
    }
    return $out;
};

$tq_lessons  = $tq_tally('lesson',  'course_id');
$tq_sections = $tq_tally('section', 'course_id');
$tq_enrols   = $tq_tally('enrol',   'course_id');

/** أسماء الأقسام مرة واحدة — لا `get_category_details_by_id` لكل صف. */
$tq_catnames = array();
foreach ($this->db->select('id, name')->get('category')->result_array() as $tq_r) {
    $tq_catnames[(int) $tq_r['id']] = $tq_r['name'];
}

/**
 * الربط بالبرنامج — صف واحد لكل كورس، باستعلام واحد للصفحة كلها.
 *
 * الكورس غير المربوط لا يظهر في «المواد والبرامج» ولا تفتحه باقة (انظر
 * [Taqdar_course_link_model.php])، وهو أهم ما يريد المسؤول أن يراه في
 * قائمة كورساته — وكانت القائمة تقول «منشور» ولا تقول ذلك.
 */
$tq_links = array();
if ($tq_ids) {
    try {
        $tq_rows = $this->db->select('p.course_id, p.slug, p.status,
                                      s.name_ar AS subject_ar, g.name_ar AS grade_ar', false)
                            ->from('paths p')
                            ->join('subjects s', 's.id = p.subject_id', 'left')
                            ->join('grades   g', 'g.id = p.grade_id',   'left')
                            ->where_in('p.course_id', $tq_ids)
                            ->get()->result_array();
        foreach ($tq_rows as $tq_r) $tq_links[(int) $tq_r['course_id']] = $tq_r;
    } catch (Throwable $e) {
        /* جدول غائب لا يبيض الشاشة. */
    }
}

/** أسماء المعلمين مرة واحدة، من قائمة المنتقي نفسها. */
$tq_insnames = array();
foreach ($instructors as $tq_i) {
    $tq_insnames[(int) $tq_i['id']] = trim($tq_i['first_name'] . ' ' . $tq_i['last_name']) ?: $tq_i['email'];
}

/* حالات Academy الخمس تترجم إلى نبرات الشارة. البرتقالي انتظار لا نجاح. */
$tq_status_map = array(
    'active'   => array('ok',     t('منشور')),
    'pending'  => array('warn',   t('قيد المراجعة')),
    'draft'    => array('muted',  t('مسودة')),
    'private'  => array('info',   t('خاص')),
    'upcoming' => array('info',   t('قادم')),
);

$tq_statuses = array(
    'all'      => t('كل الحالات'),
    'active'   => t('منشور'),
    'pending'  => t('قيد المراجعة'),
    'private'  => t('خاص'),
    'upcoming' => t('قادم'),
    'draft'    => t('مسودة'),
);

/** رابط يحفظ المرشحات الحالية ويغير واحدا منها. */
$tq_link = function ($over = array()) use ($selected_category_id, $selected_instructor_id, $selected_price, $selected_status, $search_term) {
    $q = array_merge(array(
        'category_id'   => $selected_category_id,
        'instructor_id' => $selected_instructor_id,
        'price'         => $selected_price,
        'status'        => $selected_status,
        'q'             => $search_term,
    ), $over);
    $q = array_filter($q, function ($v) { return $v !== '' && $v !== 'all'; });
    return site_url('admin/courses') . ($q ? '?' . http_build_query($q) : '');
};

$tq_tools = '<a class="tqa-btn tqa-btn--primary" href="' . site_url('admin/course_form/add_course') . '">'
          . tq_icon('plus', 17) . t(' إضافة كورس</a>');
?>

<?php tqa_head(t('الكورسات'), t('كل كورسات المنصة — والنشر قرار إدارة لا قرار معلم.'), 'book', $tq_tools); ?>

<?php /* حبات الحالة: ترشيح بضغطة واحدة، والحالة تعيش في الرابط فتحفظ
         وترسل ويعود إليها زر الرجوع. */ ?>
<nav class="tqa-tabs" aria-label="<?php echo te('تصفية الكورسات بالحالة'); ?>">
    <?php foreach ($tq_statuses as $tq_k => $tq_lbl): ?>
        <a href="<?php echo $tq_link(array('status' => $tq_k, 'page' => null)); ?>"
           <?php echo (string) $selected_status === (string) $tq_k ? 'aria-current="page"' : ''; ?>>
            <?php echo html_escape($tq_lbl); ?>
        </a>
    <?php endforeach; ?>
</nav>

<form class="tqa-toolbar" method="get" action="<?php echo site_url('admin/courses'); ?>">

    <label class="tqa-sr" for="f_q"><?php echo t('ابحث في الكورسات'); ?></label>
    <input class="tqa-input" type="search" id="f_q" name="q" placeholder="<?php echo te('ابحث بعنوان الكورس…'); ?>"
           value="<?php echo html_escape($search_term); ?>">

    <?php /* المنتقي كان يعرض التصنيفات الفرعية وحدها، ولا فرعي واحد في
             هذه القاعدة — فيخرج بخيار «كل الأقسام» لا غير (TQ-CAT-EMPTY). */ ?>
    <label class="tqa-sr" for="f_cat"><?php echo t('المرحلة'); ?></label>
    <select class="tqa-select" id="f_cat" name="category_id">
        <option value="all"><?php echo t('كل المراحل'); ?></option>
        <?php echo tqa_category_options((int) $selected_category_id); ?>
    </select>

    <label class="tqa-sr" for="f_ins"><?php echo t('المعلم'); ?></label>
    <select class="tqa-select" id="f_ins" name="instructor_id">
        <option value="all"><?php echo t('كل المعلمين'); ?></option>
        <?php foreach ($instructors as $tq_i): ?>
            <option value="<?php echo (int) $tq_i['id']; ?>"
                <?php echo (string) $selected_instructor_id === (string) $tq_i['id'] ? 'selected' : ''; ?>>
                <?php echo html_escape($tq_insnames[(int) $tq_i['id']]); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="tqa-sr" for="f_price"><?php echo t('السعر'); ?></label>
    <select class="tqa-select" id="f_price" name="price">
        <option value="all"><?php echo t('مجاني ومدفوع'); ?></option>
        <option value="free" <?php echo $selected_price === 'free' ? 'selected' : ''; ?>><?php echo t('مجاني'); ?></option>
        <option value="paid" <?php echo $selected_price === 'paid' ? 'selected' : ''; ?>><?php echo t('مدفوع'); ?></option>
    </select>

    <input type="hidden" name="status" value="<?php echo html_escape($selected_status); ?>">

    <button type="submit" class="tqa-btn tqa-btn--primary">
        <?php echo tq_icon('filter', 16); ?> <?php echo t('رشح'); ?>
    </button>

    <?php if ($search_term !== '' || $selected_category_id !== 'all' || $selected_instructor_id !== 'all' || $selected_price !== 'all'): ?>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/courses'); ?>"><?php echo t('مسح المرشحات'); ?></a>
    <?php endif; ?>
</form>

<?php
/* TQ-FILTER-AMNESIA — المرشح الفعال يقال وينزع في موضع واحد.
   الشريط أعلى الصفحة، والجدول تحته خمسون صفا: من مرر إلى الصف
   الأربعين يقرأ «٧ كورسات» ولا يرى في الشاشة ما يقول إن أربعمئة
   منها محجوبة بمنتق اختاره قبل دقيقتين. و«مسح المرشحات» وحدها لا
   تكفي — من رشح بثلاثة يريد أن ينزع واحدا لا أن يبدأ من الصفر. */
$tq_active = array();
if ($search_term !== '') {
    $tq_active[] = array(t('بحث:') . ' ' . $search_term, $tq_link(array('q' => '', 'page' => null)));
}
if ($selected_category_id !== 'all' && isset($tq_catnames[(int) $selected_category_id])) {
    $tq_active[] = array($tq_catnames[(int) $selected_category_id],
                         $tq_link(array('category_id' => 'all', 'page' => null)));
}
if ($selected_instructor_id !== 'all' && isset($tq_insnames[(int) $selected_instructor_id])) {
    $tq_active[] = array($tq_insnames[(int) $selected_instructor_id],
                         $tq_link(array('instructor_id' => 'all', 'page' => null)));
}
if ($selected_price === 'free' || $selected_price === 'paid') {
    $tq_active[] = array($selected_price === 'free' ? t('مجاني') : t('مدفوع'),
                         $tq_link(array('price' => 'all', 'page' => null)));
}
?>
<?php if ($tq_active): ?>
    <div class="tqa-activefilters">
        <span><?php echo t('مرشح الآن:'); ?></span>
        <?php foreach ($tq_active as [$tq_lbl, $tq_off]): ?>
            <span class="tqa-chip"><?php echo html_escape($tq_lbl); ?>
                <a class="tqa-chip__x" href="<?php echo html_escape($tq_off); ?>"
                   title="<?php echo te('انزع هذا المرشح'); ?>"
                   aria-label="<?php echo te('انزع مرشح ____', array($tq_lbl)); ?>">&times;</a>
            </span>
        <?php endforeach; ?>
        <?php if (count($tq_active) > 1): ?>
            <a class="tqa-chip" href="<?php echo $tq_link(array('q' => '', 'category_id' => 'all',
                'instructor_id' => 'all', 'price' => 'all', 'page' => null)); ?>"><?php echo t('امسح الكل'); ?></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="tqa-card tqa-card--flush">
<?php if (empty($courses)): ?>

    <?php tqa_empty(
        $total_courses === 0 && $search_term === '' ? t('لا كورسات بعد') : t('لا كورس يطابق هذا الترشيح'),
        $total_courses === 0 && $search_term === ''
            ? t('الكورس وعاء الدروس، وهو ما يشترك فيه الطالب. ابدأ بأول كورس.')
            : t('غير المرشحات أو امسحها لتعود إلى كل الكورسات.'),
        $total_courses === 0 && $search_term === '' ? t('إضافة كورس') : t('مسح المرشحات'),
        $total_courses === 0 && $search_term === '' ? site_url('admin/course_form/add_course') : site_url('admin/courses'),
        'book'
    ); ?>

<?php else: ?>

    <div class="tqa-table__wrap">
        <table class="tqa-table">
            <caption class="tqa-sr"><?php echo t('الكورسات: المرحلة والمادة والمحتوى والمسجلون والحالة والسعر'); ?></caption>
            <thead>
                <tr>
                    <th class="tqa-col--tight">#</th>
                    <th><?php echo t('الكورس'); ?></th>
                    <th><?php echo t('المرحلة والمادة'); ?></th>
                    <th><?php echo t('المحتوى'); ?></th>
                    <th><?php echo t('المسجلون'); ?></th>
                    <th><?php echo t('الحالة'); ?></th>
                    <th><?php echo t('السعر'); ?></th>
                    <th class="tqa-col--acts"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $tq_i => $tq_c):
                $tq_id  = (int) $tq_c['id'];
                $tq_ord = ($page_no - 1) * $per_page + $tq_i + 1;

                [$tq_tone, $tq_slabel] = $tq_status_map[$tq_c['status']] ?? array('muted', $tq_c['status']);

                $tq_free = (int) $tq_c['is_free_course'] === 1;
                $tq_amt  = (int) $tq_c['discount_flag'] === 1 ? $tq_c['discounted_price'] : $tq_c['price'];

                $tq_creator = (int) $tq_c['creator'];
                $tq_owner   = $tq_insnames[$tq_creator] ?? '—';

                $tq_slug = site_url('home/course/' . rawurlencode(slugify($tq_c['title'])) . '/' . $tq_id);
            ?>
                <tr>
                    <td data-label="#"><span class="tqa-num"><?php echo $tq_ord; ?></span></td>

                    <td data-label="<?php echo te('الكورس'); ?>">
                        <a class="tqa-media__title" href="<?php echo site_url('admin/course_form/course_edit/' . $tq_id); ?>">
                            <?php echo html_escape($tq_c['title']); ?>
                        </a>
                        <span class="tqa-media__sub"><?php echo t('المعلم:'); ?> <?php echo html_escape($tq_owner); ?></span>
                    </td>

                    <td data-label="<?php echo te('المرحلة والمادة'); ?>">
                        <?php
                        $tq_cn = $tq_catnames[(int) $tq_c['sub_category_id']]
                              ?? ($tq_catnames[(int) $tq_c['category_id']] ?? '');
                        $tq_lk = $tq_links[$tq_id] ?? null;
                        ?>
                        <?php if ($tq_cn !== ''): ?>
                            <span class="tqa-badge tqa-badge--muted"><?php echo html_escape($tq_cn); ?></span>
                        <?php else: ?>
                            <span class="tqa-dim"><?php echo t('بلا مرحلة'); ?></span>
                        <?php endif; ?>

                        <span class="tqa-media__sub">
                            <?php if ($tq_lk && ($tq_lk['grade_ar'] || $tq_lk['subject_ar'])): ?>
                                <?php echo html_escape(trim($tq_lk['subject_ar'] . ' · ' . $tq_lk['grade_ar'], ' ·')); ?>
                            <?php else: ?>
                                <span style="color:var(--tq-danger)"><?php echo t('بلا صف ولا مادة — لا يظهر في الموقع'); ?></span>
                            <?php endif; ?>
                        </span>
                    </td>

                    <td data-label="<?php echo te('المحتوى'); ?>">
                        <?php if ($tq_c['course_type'] === 'general'): ?>
                            <span class="tqa-num"><?php echo (int) ($tq_lessons[$tq_id] ?? 0); ?></span> <?php echo t('درسا'); ?>
                            <span class="tqa-media__sub">
                                <?php echo t('في'); ?> <span class="tqa-num"><?php echo (int) ($tq_sections[$tq_id] ?? 0); ?></span> <?php echo t('قسما'); ?>
                            </span>
                        <?php else: ?>
                            <span class="tqa-badge tqa-badge--info"><?php echo html_escape(strtoupper($tq_c['course_type'])); ?></span>
                        <?php endif; ?>
                    </td>

                    <td data-label="<?php echo te('المسجلون'); ?>"><span class="tqa-num"><?php echo (int) ($tq_enrols[$tq_id] ?? 0); ?></span></td>

                    <td data-label="<?php echo te('الحالة'); ?>">
                        <span class="tqa-badge tqa-badge--<?php echo $tq_tone; ?>"><?php echo html_escape($tq_slabel); ?></span>
                    </td>

                    <td data-label="<?php echo te('السعر'); ?>">
                        <?php if ($tq_free): ?>
                            <span class="tqa-badge tqa-badge--ok"><?php echo t('مجاني'); ?></span>
                        <?php else: ?>
                            <span class="tqa-num"><?php echo html_escape(currency($tq_amt)); ?></span>
                        <?php endif; ?>
                        <span class="tqa-media__sub">
                            <?php echo (int) $tq_c['expiry_period'] > 0
                                ? t('لمدة ') . (int) $tq_c['expiry_period'] . t(' شهرا')
                                : t('وصول دائم'); ?>
                        </span>
                    </td>

                    <?php /* والقائمة هنا **لا تعيد عطل `dropright`**: تلك كانت
                             تفتح «إلى اليمين» حرفيا داخل جدول في صفحة عربية
                             فتقص عند حافته، وهذه `position: fixed` يحسب
                             موضعها [admin.js] من مستطيل زرها — فلا يقصها
                             `overflow` ولا تخرج من الشاشة. */ ?>
                    <td class="tqa-col--acts" data-label="<?php echo te('إجراءات'); ?>">
                        <?php
                        /* TQ-ROW-CLUTTER — أربعة إجراءات في عمود صارت قائمة.

                           كانت مجموعة ملتحمة بعرض ٢٣٠ بكسلا في جدول من
                           ثمانية أعمدة، فتخرج أعمدة المحتوى والمسجلين
                           والسعر من الإطار على شاشة لوح — وهي التي يفتح
                           المسؤول الشاشة ليقرأها.

                           و«المقرر» أضيف على رأسها: هو ما يفتح لأجله
                           الكورس تسع مرات من عشر، وكان يبلغ بتبويب داخل
                           شاشة التحرير — أي نقرتين بعد أن يحمل نموذج من
                           تسعة تبويبات. */
                        $tq_to   = $tq_c['status'] === 'active' ? 'pending' : 'active';
                        $tq_verb = $tq_c['status'] === 'active' ? t('أوقف النشر') : t('انشر');

                        $tq_acts = array(
                            array(
                                'label' => t('المقرر'),
                                'sub'   => t('الأقسام والدروس والاختبارات'),
                                'icon'  => 'layers',
                                'tone'  => 'go',
                                'href'  => site_url('admin/course_form/course_edit/' . $tq_id) . '?tab=curriculum',
                            ),
                            array(
                                'label' => t('تحرير الكورس'),
                                'sub'   => t('البيانات والتسعير والصورة'),
                                'icon'  => 'edit',
                                'href'  => site_url('admin/course_form/course_edit/' . $tq_id),
                            ),
                            array(
                                'label'  => t('صفحته في الموقع'),
                                'sub'    => t('تفتح في لسان جديد'),
                                'icon'   => 'external',
                                'href'   => $tq_slug,
                                'target' => '_blank',
                            ),
                            array('sep' => true),
                            /* TQ-GET-DESTROY — كان الزران رابطين `<a href>`، فالنشر
                               والحذف يقعان **بمجرد جلب العنوان**: التأكيد كان في
                               المتصفح وحده، ولا يقف أمام جالب مسبق ولا أمام فتح
                               الرابط من سجل أو من زر رجوع. وحذف كورس يحذف دروسه
                               وتسجيلات طلابه معه. فصارا نموذجي POST بتوكن —
                               و`tqa_rowmenu()` تكتب النموذج والتوكن معا. */
                            array(
                                'label'   => $tq_verb,
                                'icon'    => $tq_c['status'] === 'active' ? 'eye' : 'check',
                                'action'  => 'admin/change_course_status_for_admin/' . $tq_to . '/' . $tq_id
                                           . '/' . $selected_category_id . '/' . $selected_instructor_id
                                           . '/all/' . $selected_status,
                                'confirm' => array(
                                    'title' => $tq_verb,
                                    'body'  => t('سيتغير ظهور «____» في الموقع العام.', array($tq_c['title'])),
                                    'ok'    => $tq_verb,
                                ),
                            ),
                            array(
                                'label'   => t('حذف الكورس'),
                                'icon'    => 'trash',
                                'tone'    => 'danger',
                                'action'  => 'admin/course_actions/delete/' . $tq_id,
                                'confirm' => array(
                                    'title' => t('حذف الكورس'),
                                    'body'  => t('سيحذف «____» ودروسه وتسجيلات طلابه. لا رجعة في هذا.', array($tq_c['title'])),
                                    'ok'    => t('نعم، احذف'),
                                    'tone'  => 'danger',
                                ),
                            ),
                        );
                        echo tqa_rowmenu($tq_acts, array('title' => $tq_c['title'], 'sub' => '#' . $tq_id));
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    /* الترقيم: نافذة من خمس صفحات حول الحالية — لا ستون زرا حين تكثر. */
    $tq_from = max(1, $page_no - 2);
    $tq_to_p = min($page_count, $tq_from + 4);
    $tq_from = max(1, $tq_to_p - 4);
    ?>
    <nav class="tqa-pager" aria-label="<?php echo te('صفحات الكورسات'); ?>">
        <span class="tqa-pager__info">
            <?php echo t('المعروض'); ?> <span class="tqa-num"><?php echo count($courses); ?></span>
            <?php echo t('من'); ?> <span class="tqa-num"><?php echo (int) $total_courses; ?></span> <?php echo t('كورسا'); ?>
        </span>

        <?php if ($page_no > 1): ?>
            <a href="<?php echo $tq_link(array('page' => $page_no - 1)); ?>" rel="prev" aria-label="<?php echo te('الصفحة السابقة'); ?>">
                <?php echo tq_icon('chev-prev', 16); ?>
            </a>
        <?php endif; ?>

        <?php for ($tq_p = $tq_from; $tq_p <= $tq_to_p; $tq_p++): ?>
            <?php if ($tq_p === (int) $page_no): ?>
                <span aria-current="page"><?php echo $tq_p; ?></span>
            <?php else: ?>
                <a href="<?php echo $tq_link(array('page' => $tq_p)); ?>"><?php echo $tq_p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page_no < $page_count): ?>
            <a href="<?php echo $tq_link(array('page' => $page_no + 1)); ?>" rel="next" aria-label="<?php echo te('الصفحة التالية'); ?>">
                <?php echo tq_icon('chev-next', 16); ?>
            </a>
        <?php endif; ?>
    </nav>

<?php endif; ?>
</div>
