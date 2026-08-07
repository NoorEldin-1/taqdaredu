<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * نتائج البحث داخل البوابة.
 *
 * صندوق البحث في ترويسة البوابة كان يعد ولا ينفذ: `Taqdar::search()` تبحث
 * عن هذا الملف، ولما لم تجده كانت ترسل الطالب إلى «دروسي» برسالة «شاشة
 * النتائج قيد الإعداد» — و«دروسي» لا تقرأ `q` أصلا. فكل بحث كان ينتهي
 * بقائمة كورسات كاملة ورسالة اعتذار. هذا هو الملف الغائب.
 *
 * وترتيب النتائج يتبع ملكية المستخدم لا حجم الفهرس: **ما يملكه أولا**
 * (كورساته ودروسه واختباراته وملفاته) ثم ما تعرضه المنصة عامة. فمن يبحث
 * عن «الكسور» وهو مشترك يريد درسه هو قبل صفحة تسويقية عن المادة.
 *
 * ولا استعلام جديد للطالب: المصادر هي دوال `tq_student_data.php` نفسها
 * التي تغذي بقية شاشاته، فلا يفترق عنوان هنا عن عنوان هناك.
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid  = isset($user_id) ? (int) $user_id : tq_s_uid();
$tq_role = function_exists('tq_role') ? tq_role() : 'student';
if (!in_array($tq_role, array('student', 'teacher', 'parent'), true)) $tq_role = 'student';

$tq_nav   = '';
$tq_title = 'نتائج البحث';
$tq_icon  = 'search';

$tq_q = trim((string) (isset($tq_query) ? $tq_query : $this->input->get('q', true)));
$tq_sub = $tq_q === ''
    ? 'اكتب ما تبحث عنه: درسا أو مادة أو ملفا أو معلما.'
    : 'ما وجدناه لك عن «' . $tq_q . '».';

/** مطابقة نصية غير حساسة لحالة الحرف، عربية كانت أو لاتينية. */
$tq_hit = function ($hay) use ($tq_q) {
    if ($tq_q === '') return false;
    return mb_strpos(mb_strtolower((string) $hay, 'UTF-8'), mb_strtolower($tq_q, 'UTF-8'), 0, 'UTF-8') !== false;
};

/* ---- ما يملكه المستخدم -------------------------------------------------
   لكل دور مصادره هو، ولا يبحث دور في بيانات دور آخر. */
$tq_mine = array();   // [العنوان، السطر الثاني، الرابط، الأيقونة، الشارة؟]

if ($tq_q !== '' && $tq_uid > 0 && $tq_role === 'student') {

    foreach (tq_s_enrolled($tq_uid) as $c) {
        if (!$tq_hit($c['title']) && !$tq_hit(tq_s_subject($c['category_id'], '', $c['id']))) continue;
        $tq_mine[] = array(
            $c['title'],
            tq_s_lessons_word($c['done'], $c['lessons']),
            tq_s_lesson_url($c['id'], $c['resume_id']),
            'book',
            $c['status'] === 'done' ? array('mastered', 'مكتمل')
                : ($c['status'] === 'progress' ? array('progress', 'قيد التقدم') : array('idle', 'لم يبدأ')),
        );
    }

    /* الدروس نفسها — وهي ما يبحث عنه الطالب فعلا حين يكتب اسم مفهوم.
       والنطاق قيد في الاستعلام: دروس كورساته وحدها. */
    $tq_sc = get_instance();
    $tq_cids = array_map(static function ($c) { return (int) $c['id']; }, tq_s_enrolled($tq_uid));
    if ($tq_cids) {
        $tq_rows = $tq_sc->db->select('l.id, l.title, l.course_id, l.lesson_type, c.title AS course_title')
            ->from('lesson l')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->where_in('l.course_id', $tq_cids)
            ->like('l.title', $tq_q)
            ->order_by('l.course_id', 'ASC')->order_by('l.order', 'ASC')
            ->limit(20)->get()->result_array();
        foreach ($tq_rows as $r) {
            $tq_mine[] = array(
                $r['title'],
                $r['course_title'],
                tq_s_lesson_url((int) $r['course_id'], (int) $r['id']),
                ($r['lesson_type'] === 'quiz') ? 'check-badge' : 'play',
                ($r['lesson_type'] === 'quiz') ? array('due', 'اختبار') : null,
            );
        }
    }

    foreach (tq_s_materials($tq_uid) as $m) {
        if (!$tq_hit($m['title']) && !$tq_hit($m['lesson']) && !$tq_hit($m['course'])) continue;
        $tq_mine[] = array($m['title'], $m['course'] . ' · ' . $m['kind']['label'], $m['url'], $m['kind']['icon'], null);
    }

} elseif ($tq_q !== '' && $tq_uid > 0 && $tq_role === 'teacher') {

    $tq_sc = get_instance();
    $tq_rows = $tq_sc->db->query(
        'SELECT id, title FROM `course`
          WHERE (`creator` = ? OR FIND_IN_SET(?, `user_id`) > 0) AND `title` LIKE ?
          ORDER BY id DESC LIMIT 20',
        array($tq_uid, $tq_uid, '%' . $tq_q . '%')
    )->result_array();
    foreach ($tq_rows as $r) {
        $tq_mine[] = array($r['title'], 'من كورساتك', base_url('teacher/courses'), 'book', null);
    }

} elseif ($tq_q !== '' && $tq_uid > 0 && $tq_role === 'parent') {

    $tq_sc = get_instance();
    if ($tq_sc->db->table_exists('parent_links')) {
        $tq_rows = $tq_sc->db->select('u.id, TRIM(CONCAT(COALESCE(u.first_name,""), " ", COALESCE(u.last_name,""))) AS name', false)
            ->from('parent_links p')->join('users u', 'u.id = p.student_id', 'inner')
            ->where('p.parent_user_id', $tq_uid)->where('p.status', 'active')
            ->get()->result_array();
        foreach ($tq_rows as $r) {
            if (!$tq_hit($r['name'])) continue;
            $tq_mine[] = array($r['name'], 'من أبنائك المرتبطين', base_url('parent'), 'users', null);
        }
    }
}

/* ---- ما تعرضه المنصة عامة --------------------------------------------- */
$tq_pub = array('paths' => array(), 'teachers' => array(), 'books' => array(), 'posts' => array(), 'total' => 0);
if ($tq_q !== '') {
    $tq_sc = get_instance();
    $tq_sc->load->model('taqdar_site_model', 'tq_search_site');
    $tq_pub = $tq_sc->tq_search_site->site_search($tq_q);
}

$tq_total = count($tq_mine) + (int) $tq_pub['total'];

include 'portal_open.php';
?>

<div class="tq-stack">

    <form class="tq-card tq-card--panel" role="search" method="get"
          action="<?php echo base_url($tq_role . '/search'); ?>">
        <p class="tq-field" style="margin:0">
            <label class="tq-field__label" for="tq-search-q">ابحث في المنصة</label>
            <span class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap">
                <input class="tq-input" id="tq-search-q" name="q" type="search" style="flex:1;min-inline-size:14rem"
                       value="<?php echo html_escape($tq_q); ?>" autocomplete="off"
                       placeholder="ابحث عن درس أو مادة أو ملف أو معلم…">
                <button class="tq-btn tq-btn--primary" type="submit">بحث</button>
            </span>
        </p>
    </form>

    <?php if ($tq_q === ''): ?>

        <div class="tq-card">
            <?php echo tq_s_empty(
                'search', 'sky',
                'اكتب ما تبحث عنه',
                'نبحث في دروسك وملفاتك واختباراتك أولا، ثم في مواد المنصة ومعلميها وكتبها.',
                '', '', false
            ); ?>
        </div>

    <?php elseif ($tq_total === 0): ?>

        <div class="tq-card">
            <?php echo tq_s_empty(
                'search', 'peach',
                'لم نجد شيئا يطابق «' . $tq_q . '»',
                'جرب كلمة أقصر أو أعم، أو تصفح موادك من قائمة البوابة.',
                'إلى دروسي',
                base_url($tq_role === 'student' ? 'student/lessons' : ($tq_role === 'teacher' ? 'teacher/courses' : 'parent'))
            ); ?>
        </div>

    <?php else: ?>

        <?php if ($tq_mine): ?>
            <section class="tq-section">
                <div class="tq-sectionhead">
                    <h2>في بوابتك</h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_mine) . TQ_PDI; ?></span>
                </div>
                <?php foreach ($tq_mine as $i => $m): ?>
                    <article class="tq-s-row">
                        <span class="tq-icon-box tq-pastel tq-pastel--<?php echo tq_pastel($i); ?>" aria-hidden="true">
                            <span class="tq-pastel__icon"><?php echo tq_icon($m[3]); ?></span>
                        </span>
                        <div class="tq-s-row__main">
                            <h3 class="tq-s-row__title tq-s-trunc"><?php echo html_escape($m[0]); ?></h3>
                            <p class="tq-micro tq-s-trunc" style="margin:0"><?php echo html_escape($m[1]); ?></p>
                        </div>
                        <div class="tq-s-row__end">
                            <?php if (!empty($m[4])) echo tq_badge($m[4][0], $m[4][1]); ?>
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo html_escape($m[2]); ?>">افتح</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ((int) $tq_pub['total'] > 0): ?>
            <section class="tq-section">
                <div class="tq-sectionhead">
                    <h2>في المنصة</h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . (int) $tq_pub['total'] . TQ_PDI; ?></span>
                </div>

                <div class="tq-s-grid3">
                    <?php
                    /* أربعة أنواع بشكل بطاقة واحد: العنوان، سطر يعرف به،
                       ورابط إلى صفحته. والخلط بينها لا يضر لأن كل بطاقة
                       تحمل تسمية نوعها. */
                    $tq_cards = array();
                    foreach ($tq_pub['paths'] as $r) {
                        $tq_cards[] = array($r['title'], (string) $r['blurb'], $r['href'], 'book', 'مادة');
                    }
                    foreach ($tq_pub['teachers'] as $r) {
                        $tq_cards[] = array($r['name'], (string) $r['bio'], $r['url'], 'users', 'معلم');
                    }
                    foreach ($tq_pub['books'] as $r) {
                        $tq_cards[] = array($r['title'], (string) $r['subject'], base_url('books'), 'file', 'كتاب');
                    }
                    foreach ($tq_pub['posts'] as $r) {
                        $tq_cards[] = array($r['title'], (string) $r['excerpt'], $r['url'], 'chat', 'مقال');
                    }
                    foreach (array_slice($tq_cards, 0, 12) as $i => $c):
                        ?>
                        <article class="tq-card tq-s-course">
                            <div class="tq-row tq-row--between" style="align-items:flex-start">
                                <span class="tq-icon-box tq-pastel tq-pastel--<?php echo tq_pastel($i); ?>" aria-hidden="true">
                                    <span class="tq-pastel__icon"><?php echo tq_icon($c[3]); ?></span>
                                </span>
                                <span class="tq-micro"><?php echo html_escape($c[4]); ?></span>
                            </div>
                            <h3 class="tq-s-course__title"><?php echo html_escape($c[0]); ?></h3>
                            <?php if (trim($c[1]) !== ''): ?>
                                <p class="tq-micro" style="margin:0"><?php echo html_escape(mb_substr(strip_tags($c[1]), 0, 120, 'UTF-8')); ?></p>
                            <?php endif; ?>
                            <a class="tq-btn tq-btn--ghost tq-btn--sm tq-btn--block" href="<?php echo html_escape($c[2]); ?>">
                                افتح الصفحة
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include 'portal_close.php'; ?>
