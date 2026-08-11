<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * صفحة الدورة — على تصميم الموقع، لا في جزيرة وحدها.
 *
 * كانت هذه الصفحة تعرض بترويسة أخرى وتذييل آخر وورقة أخرى: قادم من
 * `/plans` يرى قائمة من عشرة بنود وخلفية حروفيات ولونا كريميا، ثم يضغط
 * دورة فيهبط في صفحة قائمتها ستة بنود وخلفيتها بيضاء. لا شيء يقول إنه
 * غادر الموقع — لأنه لم يغادره. والسبب سطر واحد: اسم `course_page` لم
 * يكن في `$tq_site_pages` بـ`index.php`، فيسقط إلى ترويسة الثيم القديمة.
 * وقد أضيف، وأعيدت كتابة هذه الصفحة بمفردات التصميم نفسها: `page-hero`
 * و`shell` و`icard` و`plan-card` و`curric` — وهي المفردات التي تكتب بها
 * صفحة الباقة أختها.
 *
 * **والزران.** كان «اشتر الدورة» يخاطب `home/handle_buy_now` فيرد
 * `redirectTo: home/shopping_cart` — وهي سلة Academy، ولا قالب لها في
 * ثيم تقدر: من ضغطه قرأ «Page Not Found 404». وهذا ليس عطبا في الرابط
 * إنما في العرض نفسه: **الدورة لا تباع مفردة على هذه المنصة.** وحدة
 * البيع هي الباقة — كما تقول صفحة البرنامج حرفا («لا سعر ولا شراء هنا:
 * وحدة البيع صارت الباقة») وكما تقول `/plans`. فسعر مكتوب على دورة
 * يعرض منتجا لا يباع، وزر شراء تحته يقود إلى لا مكان.
 *
 * فالسعر رفع، وصار الفعل واحدا صادقا:
 *   · مشترك فيها (تسجيل أو اشتراك يمنحها) → تابع الدراسة
 *   · دورة مجانية                          → ابدأها مجانا
 *   · ما عدا ذلك                            → الباقة التي تفتحها
 * والباقة تعرف من القاعدة لا من العرض: `plans_for_course()` تمر بـ`paths`
 * كما يمر `subscription_grants()` تماما — فما تعد به الصفحة هو ما يفتحه
 * الاشتراك فعلا.
 */

$CI = get_instance();
$CI->load->model('taqdar_site_model', 'tq_sm');
$CI->load->model('taqdar_billing_model', 'tq_bm');

$course = $CI->crud_model->get_course_by_id($course_id)->row_array();

if (empty($course)) {
    ?>
    <section class="section">
      <div class="shell shell--narrow">
        <div class="icard" style="text-align:center">
          <h1>هذه الدورة غير متاحة</h1>
          <p class="tq-caption">قد تكون أخفيت أو حذفت. تصفح بقية ما هو منشور.</p>
          <a class="btn btn--primary" href="<?php echo base_url('plans'); ?>">المواد والبرامج</a>
        </div>
      </div>
    </section>
    <?php
    return;
}

$cid        = (int) $course['id'];
$uid        = (int) $CI->session->userdata('user_id');
$role       = function_exists('tq_role') ? tq_role() : 'guest';
$instructor = $CI->user_model->get_all_user($course['creator'])->row_array();
/* اسم المعلم يربط بصفحته **إن كانت له صفحة**: `/instructor/<id>` تمر
   بـ`Taqdar::instructor_page` وهي لا تعرف إلا من كان `is_public = 1`،
   فتعطي 404 لغيره. وكان الاسم هنا رابطا بلا شرط — أي رابطا مكسورا مع
   كل معلم لم تنشر صفحته. (وصفحة الباقة تفعل هذا الشرط منذ كتبت.) */
$teacher_public = !empty($instructor) && (int) ($instructor['is_public'] ?? 0) === 1;
$sections   = $CI->crud_model->get_section('course', $cid)->result_array();
$students   = $CI->crud_model->enrol_history($cid)->num_rows();

/* هل يفتح له؟ سببان لا سبب: تسجيل صريح في `enrol`، أو اشتراك يمنح صفه.
   والثاني يقرأ من محرك الفوترة نفسه الذي يحرس مشغل الدرس، فلا تعد
   الصفحة بما يمنعه الحارس ولا تمنع ما يسمح به. */
$enrolled = is_purchased($cid);
$granted  = ($uid > 0 && $role === 'student') ? $CI->tq_bm->subscription_grants($uid, $cid) : false;
$has      = ($enrolled || $granted);
$is_free  = !empty($course['is_free_course']);

/* --- المنهج: وحدات ودروس، بوسم `tqs_curriculum` نفسه المستعمل في صفحة
       الباقة و«محتوى باقتي» — مصدر شكل واحد، فلا تختلف القراءة بين
       الشاشتين. والدورة هنا «مادة» واحدة في بنية الباقة. --- */
$units       = array();
$n_lessons   = 0;
$n_quizzes   = 0;
$n_free      = 0;
$total_secs  = 0;
$first_free  = null;

foreach ($sections as $sec) {
    $rows = $CI->crud_model->get_lessons('section', $sec['id'])->result_array();
    $items = array();
    foreach ($rows as $l) {
        $is_quiz = ((string) $l['lesson_type'] === 'quiz');
        $free    = !empty($l['is_free']);
        if ($is_quiz) $n_quizzes++; else $n_lessons++;
        if ($free)    $n_free++;

        $p = explode(':', trim((string) $l['duration']));
        if (count($p) === 3) $total_secs += ((int) $p[0]) * 3600 + ((int) $p[1]) * 60 + (int) $p[2];
        elseif (count($p) === 2) $total_secs += ((int) $p[0]) * 60 + (int) $p[1];

        if ($free && !$is_quiz && $first_free === null) {
            $first_free = array('id' => (int) $l['id'], 'title' => (string) $l['title']);
        }

        $items[] = array(
            'id' => (int) $l['id'], 'title' => (string) $l['title'],
            'duration' => (string) $l['duration'], 'is_quiz' => $is_quiz, 'is_free' => $free,
        );
    }
    $units[] = array('title' => (string) $sec['title'], 'lessons' => $items);
}

/* دورة بلا وحدات قد تحمل دروسا مباشرة — فلا يعرض منهجها فارغا. */
if (!$units) {
    $loose = $CI->crud_model->get_lessons('course', $cid)->result_array();
    $items = array();
    foreach ($loose as $l) {
        $is_quiz = ((string) $l['lesson_type'] === 'quiz');
        if ($is_quiz) $n_quizzes++; else $n_lessons++;
        if (!empty($l['is_free'])) $n_free++;
        $items[] = array(
            'id' => (int) $l['id'], 'title' => (string) $l['title'],
            'duration' => (string) $l['duration'], 'is_quiz' => $is_quiz,
            'is_free' => !empty($l['is_free']),
        );
    }
    if ($items) $units[] = array('title' => 'دروس الدورة', 'lessons' => $items);
}

$curriculum = tqs_curriculum(array('subjects' => array(array(
    'title'     => (string) $course['title'],
    'course_id' => $cid,
    'units'     => $units,
    'lessons'   => $n_lessons,
    'quizzes'   => $n_quizzes,
    'ready'     => ($n_lessons + $n_quizzes) > 0,
))), array('mode' => $has ? 'student' : 'public', 'open' => 1));

/* --- التقييمات --- */
$rating_sum   = (float) $CI->crud_model->get_ratings('course', $cid, true)->row()->rating;
$ratings      = $CI->crud_model->get_ratings('course', $cid)->result_array();
$rating_count = count($ratings);
$rating_avg   = $rating_count > 0 ? $rating_sum / $rating_count : 0;

/* --- الباقة التي تفتح هذه الدورة: الأرخص أولا، والمميزة تعرض بجوارها --- */
$plans = $has ? array() : $CI->tq_sm->plans_for_course($cid);
$pick  = $plans ? $plans[0] : null;
foreach ($plans as $p) {   // المرشحة إن وجدت تسبق الأرخص: هي ما توصي به المنصة
    if ((int) $p['featured'] === 1) { $pick = $p; break; }
}

$thumb_url = (string) $CI->crud_model->get_course_thumbnail_url($cid);
if (rtrim($thumb_url, '/') === rtrim(base_url(), '/')) $thumb_url = '';

$outcomes = json_decode($course['outcomes'] ?? '[]', true);
$requires = json_decode($course['requirements'] ?? '[]', true);
$faqs     = json_decode($course['faqs'] ?? '[]', true);
$outcomes = is_array($outcomes) ? array_filter($outcomes) : array();
$requires = is_array($requires) ? array_filter($requires) : array();
$faqs     = is_array($faqs) ? $faqs : array();

$level_ar = array('beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم');
$level    = $level_ar[(string) $course['level']] ?? '';

/** رابط تشغيل الدرس داخل البوابة. */
$play = function ($lesson_id = 0) use ($cid) {
    return base_url('student/lesson/' . $cid . ($lesson_id ? '/' . (int) $lesson_id : ''));
};
?>

<section class="page-hero page-hero--plan">
  <?php include __DIR__ . '/site/site_arch.php'; ?>

  <div class="shell">
    <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url(); ?>">الرئيسية</a> ›
      <a href="<?php echo base_url('plans'); ?>">المواد والبرامج</a> ›
      <span aria-current="page"><?php echo html_escape($course['title']); ?></span>
    </nav>

    <?php if ($level !== ''): ?>
      <p class="plan-hero__stage"><?php echo html_escape($level); ?></p>
    <?php endif; ?>

    <h1><?php echo html_escape($course['title']); ?></h1>

    <?php if (!empty($course['short_description'])): ?>
      <p class="page-hero__lead"><?php echo html_escape($course['short_description']); ?></p>
    <?php endif; ?>

    <?php
    /* أربعة أرقام تصف الحجم، و**الصفر يخفي نفسه**: دورة لم ترفع دروسها
       لا تعد بأربعة وعشرين درسا.

       والتمييز يتبع العدد: الشريط يطبع الرقم في `<b>` والوصف في `<span>`،
       فيكتب الوصف بالصيغة التي تصح مع رقمه — «٤ دروس» لا «٤ درسا»،
       و«٢٠ درسا» لا «٢٠ دروس». وأعداد الدورة صغيرة (٤ · ٦ · ٢٠) فتقع
       في جمع القلة أكثر مما تقع فيما فوق العشرة. */
    $unit_for = static function ($n, $one, $two, $few, $many) {
        $n = (int) $n;
        if ($n === 1) return $one;
        if ($n === 2) return $two;
        return $n <= 10 ? $few : $many;
    };
    echo tqs_stat_strip(array(
        array($n_lessons, $unit_for($n_lessons, 'درس', 'درسان', 'دروس', 'درسا'), 'i-play'),
        array($n_quizzes, $unit_for($n_quizzes, 'اختبار', 'اختباران', 'اختبارات', 'اختبارا'), 'i-clipboard'),
        array($students,  $unit_for($students,  'طالب', 'طالبان', 'طلاب', 'طالبا'), 'i-users'),
        array($n_free,    $unit_for($n_free, 'درس مفتوح', 'درسان مفتوحان', 'دروس مفتوحة', 'درسا مفتوحا'), 'i-unlock'),
    ), 'plan-stats');
    ?>

    <?php if (!empty($instructor)): ?>
      <p class="tq-caption" style="margin-block-start:14px">
        إعداد وتقديم
        <?php if ($teacher_public): ?>
          <a href="<?php echo base_url('instructor/' . (int) $instructor['id']); ?>">
            <?php echo html_escape(trim($instructor['first_name'] . ' ' . $instructor['last_name'])); ?>
          </a>
        <?php else: ?>
          <b><?php echo html_escape(trim($instructor['first_name'] . ' ' . $instructor['last_name'])); ?></b>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
</section>

<section class="section section--tight">
  <div class="shell plan-cols">

    <div class="plan-main">

      <?php if ($m = $CI->session->flashdata('flash_message')): ?>
        <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($m); ?></p>
      <?php endif; ?>
      <?php if ($m = $CI->session->flashdata('error_message')): ?>
        <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($m); ?></p>
      <?php endif; ?>

      <?php /* ── المنهج أولا: هو أول ما يسأل عنه من يوازن قبل الاشتراك ── */ ?>
      <div class="icard">
        <h2 id="curriculum">ماذا في هذه الدورة؟</h2>
        <?php if ($n_lessons + $n_quizzes > 0): ?>
          <p class="tq-caption">
            <?php
            /* سطر الحجم: ما كان صفرا لا يذكر — «٠ اختبارا» ادعاء معكوس. */
            $bits = array();
            if (count($units) > 0) $bits[] = tq_count_units(count($units), 'وحدة', 'وحدتان', 'وحدتين',
                                                            'وحدات', 'وحدة', '', 'nom', true);
            if ($n_lessons > 0)    $bits[] = tq_lessons_word($n_lessons, '', 'nom');
            if ($n_quizzes > 0)    $bits[] = tq_count_units($n_quizzes, 'اختبار', 'اختباران', 'اختبارين',
                                                            'اختبارات', 'اختبارا', '', 'nom');
            if ($total_secs > 0)   $bits[] = tqs_dur(gmdate('H:i:s', $total_secs)) . ' من المشاهدة';
            echo html_escape(implode(' · ', $bits));
            ?>
            <?php if ($n_free > 0 && !$has): ?>
              — ومنها <b class="tq-ltr"><?php echo (int) $n_free; ?></b> درسا مفتوحا تعاينه قبل الاشتراك
            <?php endif; ?>
          </p>
        <?php else: ?>
          <p class="tq-caption">دروس هذه الدورة قيد التجهيز، وتفتح لمشتركيها فور نشرها.</p>
        <?php endif; ?>

        <?php echo $curriculum; ?>
      </div>

      <?php if ($outcomes || $requires || !empty($course['description'])): ?>
        <div class="icard">
          <h2>عن الدورة</h2>

          <?php if ($outcomes): ?>
            <h3 style="margin-block:18px 8px">ماذا ستتعلم</h3>
            <ul class="incl">
              <?php foreach ($outcomes as $item): ?>
                <li class="incl__i">
                  <span class="incl__ico"><svg aria-hidden="true"><use href="#i-check"></use></svg></span>
                  <span><?php echo html_escape($item); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ($requires): ?>
            <h3 style="margin-block:18px 8px">ما تحتاجه قبلها</h3>
            <ul class="incl">
              <?php foreach ($requires as $item): ?>
                <li class="incl__i">
                  <span class="incl__ico"><svg aria-hidden="true"><use href="#i-target"></use></svg></span>
                  <span><?php echo html_escape($item); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (!empty($course['description'])): ?>
            <div class="prose" style="margin-block-start:18px">
              <?php echo htmlspecialchars_decode_($course['description']); ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($instructor)): ?>
        <div class="icard">
          <h2>من يدرس؟</h2>
          <ul class="tlist">
            <?php /* الصف دائما داخل وعاء واحد — رابطا كان أو لا — كما في
                     صفحة الباقة، فلا يختلف تخطيط البطاقة بين معلم معلن وآخر. */ ?>
            <li class="tlist__i">
              <?php if ($teacher_public): ?>
                <a class="tlist__l" href="<?php echo base_url('instructor/' . (int) $instructor['id']); ?>">
              <?php else: ?>
                <span class="tlist__l">
              <?php endif; ?>
                <img src="<?php echo tqs_person_img($instructor['image']); ?>" alt=""
                     width="64" height="64" loading="lazy" decoding="async">
                <span class="tlist__b">
                  <b><?php echo html_escape(trim($instructor['first_name'] . ' ' . $instructor['last_name'])); ?></b>
                  <small><?php echo html_escape($instructor['title'] !== '' ? $instructor['title'] : 'معلم في تقدر'); ?></small>
                </span>
              <?php echo $teacher_public ? '</a>' : '</span>'; ?>
            </li>
          </ul>
          <?php if (!empty($instructor['biography'])): ?>
            <div class="prose" style="margin-block-start:14px">
              <?php echo htmlspecialchars_decode_($instructor['biography']); ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php /* التقييمات تعرض إن وجدت وحدها — و«لا تقييمات بعد» في بطاقة
               كاملة يشغل مساحة ليقول لا شيء. */ ?>
      <?php if ($rating_count > 0): ?>
        <div class="icard">
          <h2>ماذا قال من درسها؟</h2>
          <p class="tq-caption">
            <span class="tq-ltr"><?php echo number_format($rating_avg, 1); ?></span> من ٥
            · <?php echo html_escape(tq_count_units($rating_count, 'تقييم', 'تقييمان', 'تقييمين',
                'تقييمات', 'تقييما', '', 'obl')); ?>
          </p>
          <ul class="tlist">
            <?php foreach (array_slice($ratings, 0, 6) as $r): ?>
              <?php $author = $CI->user_model->get_all_user($r['user_id'])->row_array(); ?>
              <li class="tlist__i" style="display:block">
                <div class="tlist__l" style="cursor:default">
                  <img src="<?php echo $CI->user_model->get_user_image_url($r['user_id']); ?>" alt=""
                       width="64" height="64" loading="lazy" decoding="async">
                  <span class="tlist__b">
                    <b><?php echo !empty($author)
                        ? html_escape(trim($author['first_name'] . ' ' . $author['last_name']))
                        : 'طالب في تقدر'; ?></b>
                    <small class="tq-ltr"><?php echo str_repeat('★', max(1, min(5, (int) round((float) $r['rating'])))); ?></small>
                  </span>
                </div>
                <?php if (!empty($r['review'])): ?>
                  <p style="margin:8px 0 0"><?php echo html_escape($r['review']); ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php /* أسئلة الدورة المكتوبة في لوحة التحكم، وإن لم تكتب فأسئلة
               الاشتراك نفسها التي تجيب صفحة الباقة — لا سؤال مخترع. */ ?>
      <div class="icard">
        <h2>أسئلة قبل البدء</h2>
        <div class="faq2">
          <?php
          $qa = array();
          foreach ($faqs as $f) {
              if (empty($f['title'])) continue;
              $qa[] = array($f['title'], (string) ($f['description'] ?? ''));
          }
          if (!$qa) {
              $qa[] = array('كيف أفتح هذه الدورة؟', $is_free
                  ? 'هذه الدورة مجانية: سجل دخولك واضغط «ابدأ الدورة مجانا» فتفتح لك في الحال.'
                  : 'تفتح بالاشتراك في باقة صفك — والباقة الواحدة تفتح مواد المرحلة كلها لا هذه الدورة وحدها.');
              $qa[] = array('هل أعاين قبل أن أشترك؟', $n_free > 0
                  ? 'نعم — ' . (int) $n_free . ' درسا في هذه الدورة مفتوح للمعاينة، تجده بعلامة «معاينة مجانية» في المنهج أعلاه.'
                  : 'دروس المعاينة في هذه الدورة قيد التجهيز، وتجد في الباقة المجانية دروسا تجريبية تتصفحها بلا دفع.');
              $qa[] = array('كم يدوم وصولي إليها؟',
                  (int) $course['expiry_period'] > 0
                      ? 'وصولك إليها ' . (int) $course['expiry_period'] . ' شهرا من يوم فتحها.'
                      : 'ما دام اشتراكك ساريا، ولا تنتهي صلاحيتها في مدة أقصر.');
              $qa[] = array('هل أحصل على شهادة؟',
                  'تصدر لك شهادة إتمام عند اجتياز اختبارات الدورة، وتحمل رمزا يتحقق منه أي جهة.');
          }
          foreach ($qa as $q):
          ?>
            <details class="faq2__i">
              <summary><?php echo html_escape($q[0]); ?>
                <svg aria-hidden="true"><use href="#i-chevron"></use></svg></summary>
              <p><?php echo nl2br(html_escape($q[1])); ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <?php /* ── بطاقة الفعل اللزجة — بمفردات بطاقة الباقة نفسها ────────── */ ?>
    <aside class="plan-buy">
      <div class="icard icard--sticky plan-card">

        <?php if ($thumb_url !== ''): ?>
          <?php if ($first_free && !$has): ?>
            <?php /* الغلاف يصير مشغلا حين يكون في الدورة درس مفتوح: علامة
                     تشغيل وشارة تقول ما هي. وبلا درس مفتوح يبقى صورة —
                     ولا نعد بتشغيل لا يفتح. */ ?>
            <a class="plan-card__media plan-card__media--promo" href="<?php echo $play($first_free['id']); ?>"
               aria-label="شاهد معاينة مجانية: <?php echo html_escape($first_free['title']); ?>">
              <img src="<?php echo html_escape($thumb_url); ?>" alt="" decoding="async" fetchpriority="high">
              <span class="plan-promo__scrim" aria-hidden="true"></span>
              <span class="plan-promo__play" aria-hidden="true"><svg><use href="#i-play"></use></svg></span>
              <span class="plan-promo__tag">
                <svg aria-hidden="true"><use href="#i-video"></use></svg>
                معاينة مجانية
              </span>
              <span class="plan-promo__cap">
                <b><?php echo html_escape($first_free['title']); ?></b>
                <i><?php echo html_escape($course['title']); ?></i>
              </span>
            </a>
          <?php else: ?>
            <div class="plan-card__media">
              <img src="<?php echo html_escape($thumb_url); ?>" alt="" loading="lazy" decoding="async">
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($has): ?>
          <p class="plan-card__owned">
            <svg aria-hidden="true"><use href="#i-check"></use></svg>
            <?php echo $enrolled ? 'أنت مسجل في هذه الدورة' : 'باقتك تفتح هذه الدورة'; ?>
          </p>
          <a class="btn btn--primary btn--block" href="<?php echo $play(); ?>">تابع الدراسة</a>
          <p class="tq-caption plan-card__hint">تفتح من حيث توقفت، لا من أول الدورة.</p>

        <?php elseif ($is_free): ?>
          <p class="plan-card__price"><?php echo 'مجانية'; ?><small>بلا اشتراك</small></p>
          <a class="btn btn--primary btn--block"
             href="<?php echo site_url('home/get_enrolled_to_free_course/' . $cid); ?>">
            ابدأ الدورة مجانا
          </a>
          <?php if ($uid <= 0): ?>
            <p class="tq-caption plan-card__hint">ستسجل الدخول أولا، ثم تعود إلى هذه الصفحة.</p>
          <?php endif; ?>

        <?php elseif ($pick): ?>
          <?php /* لا سعر للدورة هنا: الذي يدفع هو ثمن الباقة، وسعران على
                   شاشة واحدة يجعلان المشتري يوازن بين خيارين أحدهما وهم. */ ?>
          <p class="path-inbundle">
            <svg aria-hidden="true"><use href="#i-check"></use></svg>
            هذه الدورة ضمن <?php echo html_escape(tqs_bundle_tier($pick['name_ar'])); ?>
          </p>
          <p class="plan-card__price">
            <?php echo tqs_money((int) $pick['price']); ?>
            <small><?php echo html_escape(tqs_period_label((int) $pick['duration_days'])); ?></small>
          </p>
          <p class="tq-caption">
            الباقة تفتح منهج المرحلة كاملا — مواد الصف وبرامجها ودروسها واختباراتها،
            لا هذه الدورة وحدها.
          </p>

          <a class="btn btn--primary btn--block" href="<?php echo base_url('checkout/' . $pick['code']); ?>">
            اشترك في الباقة
          </a>
          <a class="btn btn--ghost btn--block" href="<?php echo base_url('plan/' . $pick['code']); ?>">
            ما في هذه الباقة؟
          </a>
          <?php if ($uid <= 0): ?>
            <p class="tq-caption plan-card__hint">ستسجل الدخول أولا، ثم تعود إلى شاشة الاشتراك.</p>
          <?php endif; ?>

          <?php if (count($plans) > 1): ?>
            <a class="plan-card__back" href="<?php echo base_url('plans'); ?>">
              <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
              قارن بباقات المرحلة كلها
            </a>
          <?php endif; ?>

        <?php else: ?>
          <?php /* دورة لم تربط بصف ولا تفتحها باقة قائمة: لا يخترع لها زر
                   شراء يقود إلى خطأ — يقال الحال ويعرض الباب الصحيح. */ ?>
          <p class="plan-card__soon">لم تدرج هذه الدورة في باقة بعد.</p>
          <a class="btn btn--primary btn--block" href="<?php echo base_url('plans'); ?>">شاهد الباقات</a>
          <a class="btn btn--ghost btn--block" href="<?php echo base_url('contact'); ?>">اسأل عنها</a>
        <?php endif; ?>

        <?php
        /* المفضلة — نموذج POST إلى مسار البوابة نفسه، والصفحة تعود إلى
           نفسها. وهي للطالب وحده: قلب في حساب معلم يحفظ في جدول لا يقرؤه
           شيء في بوابته. */
        if ($uid > 0 && $role === 'student'):
            $CI->load->model('taqdar_favourites_model');
            $fav = in_array($cid, $CI->taqdar_favourites_model->course_ids($uid), true);
        ?>
          <form method="post" action="<?php echo base_url('student/favourite'); ?>" style="margin-block-start:10px">
            <?php echo tq_csrf(); ?>
            <input type="hidden" name="kind" value="course">
            <input type="hidden" name="item_id" value="<?php echo $cid; ?>">
            <input type="hidden" name="back" value="course">
            <button class="btn btn--ghost btn--block" type="submit"
                    aria-pressed="<?php echo $fav ? 'true' : 'false'; ?>">
              <?php echo $fav ? 'إزالة من المفضلة' : 'أضف إلى المفضلة'; ?>
            </button>
          </form>
        <?php endif; ?>

        <ul class="plan-card__facts">
          <?php if ($n_lessons > 0): ?>
            <li><svg aria-hidden="true"><use href="#i-play"></use></svg>
              <?php echo html_escape(tq_lessons_word($n_lessons, 'لا دروس', 'nom')); ?></li>
          <?php endif; ?>
          <?php if ($total_secs > 0): ?>
            <li><svg aria-hidden="true"><use href="#i-clock"></use></svg>
              <?php echo html_escape(tqs_dur(gmdate('H:i:s', $total_secs))); ?> من المشاهدة</li>
          <?php endif; ?>
          <?php if ($n_quizzes > 0): ?>
            <li><svg aria-hidden="true"><use href="#i-clipboard"></use></svg>
              <?php echo html_escape(tq_count_units($n_quizzes, 'اختبار', 'اختباران', 'اختبارين',
                  'اختبارات', 'اختبارا', '', 'nom')); ?></li>
          <?php endif; ?>
          <li><svg aria-hidden="true"><use href="#i-lock"></use></svg>
            <?php echo (int) $course['expiry_period'] > 0
                ? 'وصول ' . (int) $course['expiry_period'] . ' شهرا'
                : 'وصول ما دام اشتراكك ساريا'; ?></li>
          <li><svg aria-hidden="true"><use href="#i-certificate"></use></svg>
            شهادة إتمام عند اجتياز الاختبارات</li>
        </ul>
      </div>
    </aside>

  </div>
</section>

<?php
/* وسم الدورة للمحركات. `offers` من الباقة التي تفتحها لا من `course.price`:
   ذاك السعر لا يباع به شيء على هذه المنصة، ووسمه يعرض للمحرك ثمنا لا
   يجده الزائر على الصفحة. */
$ld = array(
    '@context'    => 'https://schema.org',
    '@type'       => 'Course',
    'name'        => (string) $course['title'],
    'description' => (string) ($course['short_description'] !== '' ? $course['short_description'] : $course['title']),
    'provider'    => array('@type' => 'Organization', 'name' => 'تقدر', 'sameAs' => base_url()),
);
if ($thumb_url !== '') $ld['image'] = $thumb_url;
if ($pick) {
    $ld['offers'] = array(
        '@type'         => 'Offer',
        'price'         => number_format((int) $pick['price'] / 100, 2, '.', ''),
        'priceCurrency' => 'SAR',
        'availability'  => 'https://schema.org/InStock',
        'url'           => base_url('plan/' . $pick['code']),
    );
} elseif ($is_free) {
    $ld['offers'] = array(
        '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'SAR',
        'availability' => 'https://schema.org/InStock', 'url' => base_url('plans'),
    );
}
?>
<script type="application/ld+json"><?php
echo json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?></script>
