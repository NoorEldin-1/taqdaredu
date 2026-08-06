<?php
/**
 * المفضلة — بوابة الطالب.
 *
 * المفضلة مجمعة بالنوع لا مخلوطة في شبكة واحدة: الدرس والمادة والكورس
 * ثلاثة أشياء يفعل بها الطالب ثلاثة أفعال مختلفة، فخلطها يجعل الشاشة كومة.
 *
 * مصدر التفضيل الوحيد في قاعدة taqd_lms هو users.wishlist (معرفات كورسات)،
 * فقسم الكورسات موصول ببيانات حقيقية، وتقدمه من watch_histories، و«الأكثر
 * استخداما» من watched_duration — وقت مشاهدة مسجل لا عداد مخترع.
 *
 * أما تفضيل درس بعينه أو ملف تعليمي بعينه أو قائمة باسم الطالب فلا جدول
 * لأي منها بعد — ولذلك يعرض في قسمه حالة فارغة صحيحة تشرح ما سيظهر
 * وتضع الزر الذي يبدأ الفعل، لا بطاقات وهمية.
 */

$tq_nav   = 'favourites';
$tq_role  = 'student';
$tq_title = 'المفضلة';
$tq_sub   = 'كل ما تضيفه إلى المفضلة تجده هنا بسهولة';
$tq_icon  = 'heart';

$uid = (int) $this->session->userdata('user_id');

/* ---- الكورسات المفضلة: users.wishlist ------------------------------- */
$tq_wishlist_raw = $this->db->select('wishlist')->where('id', $uid)->get('users')->row('wishlist');
$tq_wishlist     = json_decode((string) $tq_wishlist_raw, true);
$tq_wishlist     = is_array($tq_wishlist) ? array_values(array_filter(array_map('intval', $tq_wishlist))) : [];

$tq_fav_courses = [];
if ($tq_wishlist) {
    $tq_fav_courses = $this->db->select('id, title, thumbnail, user_id, price, discounted_price, discount_flag')
        ->where_in('id', $tq_wishlist)
        ->get('course')->result_array();
}

/* تقدم الطالب في كل كورس مفضل — من watch_histories */
$tq_course_progress = [];
if ($tq_fav_courses) {
    $rows = $this->db->select('course_id, course_progress')
        ->where('student_id', $uid)
        ->where_in('course_id', array_map(static function ($c) { return (int) $c['id']; }, $tq_fav_courses))
        ->get('watch_histories')->result_array();
    foreach ($rows as $r) {
        $tq_course_progress[(int) $r['course_id']] = (int) $r['course_progress'];
    }
}

/* أسماء المعلمين */
$tq_teachers = [];
if ($tq_fav_courses) {
    $ids = array_values(array_unique(array_filter(array_map(static function ($c) { return (int) $c['user_id']; }, $tq_fav_courses))));
    if ($ids) {
        foreach ($this->db->select('id, first_name, last_name')->where_in('id', $ids)->get('users')->result_array() as $u) {
            $tq_teachers[(int) $u['id']] = trim($u['first_name'] . ' ' . $u['last_name']);
        }
    }
}

/* ---- الأكثر استخداما: وقت مشاهدة مسجل داخل كورسات مفضلتك -----------
   لا عداد «مرات فتح» في القاعدة، ولا يخترع واحد. المقياس الحقيقي المتاح
   هو ثواني المشاهدة المسجلة لكل كورس (watched_duration.current_duration)،
   فهو الذي يعرض، والتسمية تقول ما يقيسه بالضبط. */
$tq_most_used = [];
if ($tq_fav_courses) {
    $CI      = get_instance();
    $fav_ids = array_map(static function ($c) { return (int) $c['id']; }, $tq_fav_courses);
    $fav_titles = [];
    foreach ($tq_fav_courses as $c) $fav_titles[(int) $c['id']] = (string) $c['title'];

    foreach ($CI->db->select('watched_course_id, SUM(current_duration) AS secs', false)
                    ->from('watched_duration')
                    ->where('watched_student_id', $uid)
                    ->where_in('watched_course_id', $fav_ids)
                    ->group_by('watched_course_id')
                    ->order_by('secs', 'DESC')
                    ->limit(3)->get()->result_array() as $r) {
        $secs = (int) $r['secs'];
        if ($secs <= 0) continue;
        $cid = (int) $r['watched_course_id'];
        $tq_most_used[] = [
            'title' => $fav_titles[$cid] ?? '',
            'secs'  => $secs,
            'href'  => base_url('home/course_page/' . $cid),
        ];
    }
}

/* ---- ما لا مصدر له بعد ------------------------------------------------ */
$tq_fav_lessons   = [];   // لا جدول تفضيل للدروس في taqd_lms
$tq_fav_materials = [];   // ولا للمواد التعليمية (resource_files بلا تفضيل)
$tq_lists         = [];   // ولا للقوائم المخصصة

$tq_total_fav = count($tq_fav_courses) + count($tq_fav_lessons) + count($tq_fav_materials);

/* تبويب النوع يعمل على الخادم لا في المتصفح: الرابط قابل للمشاركة والعودة
   إليه، ويعمل بلا جافاسكربت. */
$tq_type = $this->input->get('type', true);
$tq_type = in_array($tq_type, ['lessons', 'materials', 'courses'], true) ? $tq_type : 'all';
$tq_show = static function ($section) use ($tq_type) {
    return $tq_type === 'all' || $tq_type === $section;
};
$tq_types = [
    'all'       => 'الكل',
    'lessons'   => 'الدروس',
    'materials' => 'المواد التعليمية',
    'courses'   => 'الكورسات',
];

/* ---- الترتيب: يعمل فعلا على الخادم -----------------------------------
   قائمة منسدلة لا ترتب شيئا كذبة صغيرة تتكرر في كل زيارة. الترتيب هنا
   في الرابط، فيعمل بلا جافاسكربت ويحفظ ويشارك كتبويب النوع. */
$tq_sort = $this->input->get('sort', true);
$tq_sort = in_array($tq_sort, ['recent', 'title', 'progress'], true) ? $tq_sort : 'recent';
$tq_sorts = [
    'recent'   => 'الأحدث إضافة',
    'title'    => 'الاسم',
    'progress' => 'الأقرب للاكتمال',
];

if ($tq_fav_courses) {
    $rank = array_flip($tq_wishlist);   // ترتيب الإضافة كما خزنته القائمة
    usort($tq_fav_courses, static function ($a, $b) use ($tq_sort, $rank, $tq_course_progress) {
        $ia = (int) $a['id'];
        $ib = (int) $b['id'];
        if ($tq_sort === 'title')    return strcmp((string) $a['title'], (string) $b['title']);
        if ($tq_sort === 'progress') return ($tq_course_progress[$ib] ?? 0) <=> ($tq_course_progress[$ia] ?? 0);
        return ($rank[$ib] ?? 0) <=> ($rank[$ia] ?? 0);   // الأحدث إضافة أولا
    });
}

include 'portal_open.php';
?>

<style>
/* المفضلة — الأقسام مجمعة بالنوع، وكل بطاقة تشبه ما تمثله. */
.tq-icon-box[class*='tq-pastel--'] { color: var(--tq-pastel-ink); }
/* تبويب النوع رابط حقيقي، فحالته aria-current لا aria-selected. */
.tq-tab[aria-current='page'] { color: var(--tq-navy); border-block-end-color: var(--tq-navy); font-weight: 700; }
.tq-tab:hover { text-decoration: none; }

.tq-favbar { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m); flex-wrap: wrap; margin-block-end: var(--tq-space-xl); }
.tq-favbar__tools { display: flex; align-items: center; gap: var(--tq-space-s); margin-inline-start: auto; }

.tq-cardgrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--tq-space-l); }

.tq-lesson-card { background: var(--tq-surface); border-radius: var(--tq-radius-card); box-shadow: var(--tq-shadow-soft); overflow: hidden; }
.tq-lesson-card__cover { position: relative; aspect-ratio: 16 / 10; display: grid; place-items: center;
  background: linear-gradient(140deg, var(--tq-sky-fill), var(--tq-mint-fill)); }
.tq-lesson-card__cover img { inline-size: 100%; block-size: 100%; object-fit: cover; }
.tq-lesson-card__play { position: absolute; inline-size: 48px; block-size: 48px; border-radius: var(--tq-radius-pill);
  background: var(--tq-surface); color: var(--tq-navy); display: grid; place-items: center; box-shadow: var(--tq-shadow-raised); }
.tq-lesson-card__time { position: absolute; inset-block-end: var(--tq-space-s); inset-inline-end: var(--tq-space-s);
  background: var(--tq-navyDeep); color: var(--tq-navInkOn); border-radius: var(--tq-radius-pill);
  padding: 2px var(--tq-space-s); font: var(--tq-type-numeralSm); unicode-bidi: isolate; direction: ltr; }
.tq-lesson-card__body { padding: var(--tq-space-m) var(--tq-space-l) var(--tq-space-l); }
.tq-lesson-card__foot { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-s); margin-block-start: var(--tq-space-m); }

.tq-fav-heart { color: var(--tq-navy); display: grid; place-items: center; inline-size: var(--tq-touch-min); block-size: var(--tq-touch-min); border-radius: var(--tq-radius-small); }
.tq-fav-heart:hover { background: var(--tq-navyWash); }

.tq-course-row { display: grid; grid-template-columns: 96px minmax(0, 1fr); gap: var(--tq-space-l); align-items: center;
  background: var(--tq-surface); border-radius: var(--tq-radius-card); box-shadow: var(--tq-shadow-soft); padding: var(--tq-space-l); }
.tq-course-row__art { inline-size: 96px; block-size: 72px; border-radius: var(--tq-radius-medium); overflow: hidden;
  background: linear-gradient(140deg, var(--tq-lilac-fill), var(--tq-sky-fill)); display: grid; place-items: center; color: var(--tq-navy); }
.tq-course-row__art img { inline-size: 100%; block-size: 100%; object-fit: cover; }

.tq-filestrip { display: flex; align-items: center; gap: var(--tq-space-m); }
.tq-ext { inline-size: 44px; block-size: 44px; border-radius: var(--tq-radius-small); display: grid; place-items: center;
  font: var(--tq-type-micro); flex: none; unicode-bidi: isolate; direction: ltr; }

.tq-hintbar { display: flex; align-items: center; justify-content: center; gap: var(--tq-space-s);
  background: var(--tq-surface); border: 1px solid var(--tq-line); border-radius: var(--tq-radius-pill);
  padding: var(--tq-space-m) var(--tq-space-xl); color: var(--tq-text2); font: var(--tq-type-caption); text-align: center; }

.tq-sumrow { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m); }
.tq-sumrow + .tq-sumrow { margin-block-start: var(--tq-space-m); }

@media (max-width: 1023.98px) { .tq-cardgrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 639.98px) { .tq-cardgrid { grid-template-columns: minmax(0, 1fr); } }
</style>

<div class="tq-favbar">
    <nav class="tq-tabs" aria-label="تصفية المفضلة بالنوع" style="margin-block-end:0;border-block-end:0">
        <?php foreach ($tq_types as $key => $label): ?>
            <a class="tq-tab"
               href="<?php echo base_url('student/favourites') . ($key === 'all' ? '' : '?type=' . $key); ?>"
               <?php echo tq_active($key, $tq_type); ?>>
                <?php echo html_escape($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <form class="tq-favbar__tools" method="get" action="<?php echo base_url('student/favourites'); ?>">
        <?php if ($tq_type !== 'all'): ?>
            <input type="hidden" name="type" value="<?php echo html_escape($tq_type); ?>">
        <?php endif; ?>
        <label class="tq-sr" for="tq-fav-sort">ترتيب المفضلة</label>
        <select class="tq-select" id="tq-fav-sort" name="sort" style="inline-size:auto">
            <?php foreach ($tq_sorts as $key => $label): ?>
                <option value="<?php echo html_escape($key); ?>" <?php echo $tq_sort === $key ? 'selected' : ''; ?>>
                    <?php echo html_escape($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">رتب</button>
    </form>
</div>

<div class="tq-cols">
    <div>

        <!-- الدروس -->
        <?php if ($tq_show('lessons')): ?>
        <section class="tq-section" aria-labelledby="tq-fs-lessons">
            <div class="tq-sectionhead">
                <div class="tq-row">
                    <h2 id="tq-fs-lessons">الدروس</h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_fav_lessons) . TQ_PDI; ?></span>
                </div>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/lessons'); ?>">عرض الكل</a>
            </div>

            <?php if (!$tq_fav_lessons): ?>
                <div class="tq-card tq-card--panel">
                    <div class="tq-empty">
                        <div class="tq-empty__art tq-pastel tq-pastel--sky" style="display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                            <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('play', 44); ?></span>
                        </div>
                        <h3 class="tq-empty__title">لا دروس في مفضلتك</h3>
                        <p class="tq-empty__text">
                            اضغط القلب على أي درس وسيظهر هنا ببطاقته: الغلاف، وزر التشغيل، ومدته، واسم مادته —
                            فتعود إليه في ثانية بدل البحث عنه.
                        </p>
                        <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/lessons'); ?>">تصفح دروسي</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="tq-cardgrid tq-stagger">
                    <?php foreach ($tq_fav_lessons as $i => $ls): ?>
                        <article class="tq-lesson-card">
                            <div class="tq-lesson-card__cover">
                                <span class="tq-lesson-card__play" aria-hidden="true"><?php echo tq_icon('play'); ?></span>
                                <span class="tq-lesson-card__time"><?php echo TQ_LRI . html_escape($ls['duration']) . TQ_PDI; ?></span>
                            </div>
                            <div class="tq-lesson-card__body">
                                <h3 class="tq-h2" style="font:var(--tq-type-bodyStrong);margin:0"><?php echo html_escape($ls['title']); ?></h3>
                                <div class="tq-lesson-card__foot">
                                    <span class="tq-micro"><?php echo html_escape($ls['subject']); ?></span>
                                    <button class="tq-fav-heart" type="button" aria-pressed="true" aria-label="إزالة الدرس من المفضلة">
                                        <?php echo tq_icon('heart'); ?>
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- المواد التعليمية -->
        <?php if ($tq_show('materials')): ?>
        <section class="tq-section" aria-labelledby="tq-fs-materials">
            <div class="tq-sectionhead">
                <div class="tq-row">
                    <h2 id="tq-fs-materials">المواد التعليمية</h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_fav_materials) . TQ_PDI; ?></span>
                </div>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/materials'); ?>">عرض الكل</a>
            </div>

            <?php if (!$tq_fav_materials): ?>
                <div class="tq-card tq-card--panel">
                    <div class="tq-empty">
                        <div class="tq-empty__art tq-pastel tq-pastel--peach" style="display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                            <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('file', 44); ?></span>
                        </div>
                        <h3 class="tq-empty__title">لا ملفات محفوظة</h3>
                        <p class="tq-empty__text">
                            الملخصات وأوراق العمل التي تحفظها تظهر هنا بامتدادها وحجمها، جاهزة للتنزيل قبل المذاكرة.
                        </p>
                        <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/materials'); ?>">تصفح المواد التعليمية</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="tq-cardgrid tq-stagger">
                    <?php foreach ($tq_fav_materials as $i => $f): ?>
                        <article class="tq-card">
                            <div class="tq-filestrip">
                                <span class="tq-ext tq-pastel--<?php echo tq_pastel($i); ?>" style="color:var(--tq-pastel-ink)" aria-hidden="true">
                                    <?php echo html_escape(strtoupper($f['ext'])); ?>
                                </span>
                                <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($f['title']); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- الكورسات — موصولة ببيانات حقيقية من users.wishlist -->
        <?php if ($tq_show('courses')): ?>
        <section class="tq-section" aria-labelledby="tq-fs-courses">
            <div class="tq-sectionhead">
                <div class="tq-row">
                    <h2 id="tq-fs-courses">الكورسات</h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_fav_courses) . TQ_PDI; ?></span>
                </div>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('my-courses'); ?>">عرض الكل</a>
            </div>

            <?php if (!$tq_fav_courses): ?>
                <div class="tq-card tq-card--panel">
                    <div class="tq-empty">
                        <div class="tq-empty__art tq-pastel tq-pastel--lilac" style="display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                            <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('book', 44); ?></span>
                        </div>
                        <h3 class="tq-empty__title">لا كورسات في مفضلتك</h3>
                        <p class="tq-empty__text">
                            أضف كورسا إلى المفضلة لتتابع تقدمك فيه من هنا بشريط ونسبة، وتعود إليه من حيث توقفت.
                        </p>
                        <a class="tq-btn tq-btn--primary" href="<?php echo base_url('plans'); ?>">تصفح الكورسات</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="tq-stack tq-stagger">
                    <?php foreach ($tq_fav_courses as $c): ?>
                        <?php
                        $cid   = (int) $c['id'];
                        $pct   = $tq_course_progress[$cid] ?? 0;
                        $thumb = !empty($c['thumbnail']) ? base_url($c['thumbnail']) : '';
                        ?>
                        <article class="tq-course-row">
                            <div class="tq-course-row__art">
                                <?php if ($thumb !== ''): ?>
                                    <img src="<?php echo html_escape($thumb); ?>" alt="غلاف كورس <?php echo html_escape($c['title']); ?>">
                                <?php else: ?>
                                    <span aria-hidden="true"><?php echo tq_icon('book', 28); ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="tq-row tq-row--between">
                                    <h3 style="font:var(--tq-type-bodyStrong);margin:0">
                                        <a href="<?php echo base_url('home/course_page/' . $cid); ?>" style="color:var(--tq-navy)">
                                            <?php echo html_escape($c['title']); ?>
                                        </a>
                                    </h3>
                                    <button class="tq-fav-heart" type="button" aria-pressed="true" aria-label="إزالة الكورس من المفضلة">
                                        <?php echo tq_icon('heart'); ?>
                                    </button>
                                </div>
                                <p class="tq-micro" style="margin:0 0 var(--tq-space-s)">
                                    <?php echo html_escape($tq_teachers[(int) $c['user_id']] ?? 'فريق تقدر'); ?>
                                </p>
                                <?php echo tq_progress($pct, 'تقدمك في الكورس'); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <p class="tq-hintbar">
            <span aria-hidden="true"><?php echo tq_icon('heart', 16); ?></span>
            يمكنك إزالة أي عنصر من المفضلة بالضغط على القلب في البطاقة.
        </p>

    </div>

    <aside class="tq-aside">

        <!-- ملخص المفضلة -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-favsum-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-favsum-h">ملخص المفضلة</h2></div>
            <div class="tq-row" style="gap:var(--tq-space-l);margin-block-end:var(--tq-space-xl)">
                <span class="tq-icon-box tq-pastel--rose" aria-hidden="true"><?php echo tq_icon('heart', 26); ?></span>
                <span>
                    <span style="display:block"><?php echo tq_num($tq_total_fav, 'tq-num--xl'); ?></span>
                    <span class="tq-micro">إجمالي العناصر</span>
                </span>
            </div>
            <div>
                <div class="tq-sumrow">
                    <span class="tq-row"><span class="tq-icon-box tq-pastel--sky" aria-hidden="true"><?php echo tq_icon('play', 18); ?></span><span class="tq-caption">الدروس</span></span>
                    <?php echo tq_num(count($tq_fav_lessons), 'tq-num--sm'); ?>
                </div>
                <div class="tq-sumrow">
                    <span class="tq-row"><span class="tq-icon-box tq-pastel--peach" aria-hidden="true"><?php echo tq_icon('file', 18); ?></span><span class="tq-caption">المواد التعليمية</span></span>
                    <?php echo tq_num(count($tq_fav_materials), 'tq-num--sm'); ?>
                </div>
                <div class="tq-sumrow">
                    <span class="tq-row"><span class="tq-icon-box tq-pastel--lilac" aria-hidden="true"><?php echo tq_icon('book', 18); ?></span><span class="tq-caption">الكورسات</span></span>
                    <?php echo tq_num(count($tq_fav_courses), 'tq-num--sm'); ?>
                </div>
            </div>
            <a class="tq-btn tq-btn--secondary tq-btn--block" href="<?php echo base_url('home/my_wishlist'); ?>" style="margin-block-start:var(--tq-space-xl)">إدارة المفضلة</a>
        </section>

        <!-- القوائم المخصصة: لا جدول قوائم في القاعدة بعد -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-lists-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-lists-h">القوائم المخصصة</h2></div>
            <?php if (!$tq_lists): ?>
                <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                    اجمع مفضلتك في قوائم باسمك: «مراجعة الاختبار النهائي» أو «دروس مهمة»،
                    وسيظهر لكل قائمة عدد عناصرها هنا.
                </p>
            <?php else: ?>
                <ul class="tq-stack" style="margin-block-end:var(--tq-space-l)">
                    <?php foreach ($tq_lists as $i => $l): ?>
                        <li class="tq-row tq-row--between">
                            <span class="tq-row">
                                <span class="tq-icon-box tq-pastel--<?php echo tq_pastel($i); ?>" aria-hidden="true"><?php echo tq_icon('folder', 18); ?></span>
                                <span>
                                    <span class="tq-caption" style="display:block;color:var(--tq-navy)"><?php echo html_escape($l['name']); ?></span>
                                    <span class="tq-micro"><?php echo tq_iso($l['count'] . ' عناصر'); ?></span>
                                </span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php /* لا زر «إنشاء قائمة»: لا جدول قوائم في القاعدة، وزر لا
                     يفعل شيئا يعد بما لا يقع — والوعد الكاذب أسوأ من غيابه. */ ?>
        </section>

        <!-- الأكثر مشاهدة: وقت مسجل من watched_duration، لا عداد فتح مخترع -->
        <section class="tq-card tq-card--panel" aria-labelledby="tq-most-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-most-h">الأكثر مشاهدة في مفضلتك</h2></div>
            <?php if (!$tq_most_used): ?>
                <p class="tq-caption" style="margin:0">
                    بعد أن تشاهد دروس كورسات مفضلتك سيظهر هنا أكثر ثلاثة قضيت فيها وقتا، بزمن مشاهدتك المسجل.
                </p>
            <?php else: ?>
                <ul class="tq-stack">
                    <?php foreach ($tq_most_used as $m): ?>
                        <?php
                        $h = intdiv($m['secs'], 3600);
                        $mn = intdiv($m['secs'] % 3600, 60);
                        $spent = $h > 0 ? $h . ' س ' . $mn . ' د' : $mn . ' د';
                        ?>
                        <li class="tq-row tq-row--between">
                            <a class="tq-caption tq-s-trunc" style="color:var(--tq-navy)" href="<?php echo html_escape($m['href']); ?>">
                                <?php echo html_escape($m['title']); ?>
                            </a>
                            <span class="tq-micro"><?php echo tq_iso($spent); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
