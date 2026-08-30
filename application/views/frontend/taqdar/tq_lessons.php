<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * دروسي — الدرس وحدة هذه الشاشة، لا الكورس.
 *
 * **ما كان هنا قبل:** شبكة بطاقات كورسات تحت عنوان «دروسي». والشاشة
 * التي تسمى بالدرس وتعطي الكورس تجعل السؤال الطبيعي — «أين درس الكسور؟» —
 * بلا جواب في البوابة كلها: يفتح الطالب الكورس، ثم يمسح منهجه بعينه.
 * وشاشة المفضلة تقول له «اضغط القلب على أي درس» ولا موضع في البوابة
 * يعرض له درسا مفردا ليضغطه.
 *
 * فالكورسات انتقلت إلى `tq_courses.php` بعنوانها الصحيح «كورساتي»،
 * وبقيت هنا **الدروس**: كل درس في كل كورس مسجل، مرتبا كما يدرس —
 * كورسا فوحدة فدرسا — وبحالته الحقيقية من `watch_histories`.
 *
 * ثلاثة قرارات في الشكل، وكلها عن قراءة لا عن ذوق:
 *
 * ١ — **قائمة لا شبكة.** الطالب هنا عنده مئة درس وأكثر (١١٢ في حساب
 *     الاختبار)، وشبكة أغلفة من مئة بطاقة لا تمسح بالعين. والصف الواحد
 *     يقرأ سطرا: حالة · اسم · وحدة · مدة.
 *
 * ٢ — **مطوية بالكورس.** الكورسات `<details>`، والمفتوح منها ما فيه
 *     درسك الحالي — فما تفتح عليه الصفحة هو ما أنت فيه فعلا، لا أول
 *     كورس في الترتيب.
 *
 * ٣ — **الحالة والتصفية في الرابط** كما في بقية شاشات البوابة: تحفظ
 *     وتشارك وتعمل بلا جافاسكربت. و«دروسه» في بطاقة الكورس تصل إلى
 *     هنا بـ`?course=`.
 *
 * موصول بالقاعدة: lesson · section · enrol · course · watch_histories
 * · tq_favourites (قلب الدرس).
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'lessons';
$tq_role  = 'student';
$tq_title = t('دروسي');
$tq_sub   = t('كل دروس كورساتك، درسا درسا');
$tq_icon  = 'play';

$tq_all      = tq_s_lessons($tq_uid);
$tq_courses  = tq_s_enrolled($tq_uid);

/* --- الفلاتر: قائمة بيضاء لا ما يرسله المتصفح --- */
$f_course = (int) $this->input->get('course');
$f_state  = (string) $this->input->get('state', true);
$f_q      = trim((string) $this->input->get('q', true));
if (!in_array($f_state, ['todo', 'current', 'done'], true)) $f_state = '';
if ($f_q !== '') $f_q = mb_substr($f_q, 0, 80);

/* الكورس المطلوب لا بد أن يكون من كورسات الطالب: معرف يخمن في الرابط
   لا يفتح شيئا، ولا يعرض قائمة فارغة بلا سبب مقروء. */
$tq_course_ids = array_map(static function ($c) { return (int) $c['id']; }, $tq_courses);
if ($f_course > 0 && !in_array($f_course, $tq_course_ids, true)) $f_course = 0;

$tq_has_filter = ($f_course > 0 || $f_state !== '' || $f_q !== '');

$tq_by_state = ['all' => count($tq_all), 'done' => 0, 'current' => 0, 'todo' => 0];
foreach ($tq_all as $l) $tq_by_state[$l['state']]++;

$tq_list = array_values(array_filter($tq_all, static function ($l) use ($f_course, $f_state, $f_q) {
    if ($f_course > 0 && (int) $l['course_id'] !== $f_course) return false;
    if ($f_state !== '' && $l['state'] !== $f_state) return false;
    if ($f_q !== '' && mb_stripos($l['title'] . ' ' . $l['unit'] . ' ' . $l['course'], $f_q) === false) return false;
    return true;
}));

/* --- روابط تحفظ بقية الفلاتر --- */
$tq_query = static function ($over = []) use ($f_course, $f_state, $f_q) {
    $p = array_merge(['course' => $f_course ?: '', 'state' => $f_state, 'q' => $f_q], $over);
    $p = array_filter($p, static function ($v) { return $v !== '' && $v !== null && $v !== 0; });
    return base_url('student/lessons') . ($p ? '?' . http_build_query($p) : '');
};

/* --- التجميع: كورس ← وحدة ← دروس --- */
$tq_groups = [];
foreach ($tq_list as $l) {
    $cid = (int) $l['course_id'];
    if (!isset($tq_groups[$cid])) {
        $tq_groups[$cid] = [
            'id' => $cid, 'title' => $l['course'], 'subject' => $l['subject'],
            'level' => $l['level'], 'done' => 0, 'total' => 0, 'units' => [],
        ];
    }
    $unit = $l['unit'] !== '' ? $l['unit'] : t('دروس بلا وحدة');
    $tq_groups[$cid]['units'][$unit][] = $l;
    $tq_groups[$cid]['total']++;
    if ($l['state'] === 'done') $tq_groups[$cid]['done']++;
}

/* --- موضع التوقف: هو ما تفتح عليه الصفحة --- */
$tq_current = null;
foreach ($tq_all as $l) {
    if ($l['state'] !== 'current') continue;
    if ($tq_current === null || $l['at'] > $tq_current['at']) $tq_current = $l;
}
/* ولا درس جاريا؟ فأول ما لم يبدأ في أحدث كورس لمسه — لا أول درس في
   القائمة كلها: من أنهى كورسا لا يعاد إلى أوله. */
if ($tq_current === null) {
    $tq_resume_course = tq_s_resume($tq_uid);
    if ($tq_resume_course) {
        foreach ($tq_all as $l) {
            if ((int) $l['course_id'] === (int) $tq_resume_course['id'] && $l['state'] !== 'done') {
                $tq_current = $l;
                break;
            }
        }
    }
}
$tq_open_course = $tq_current ? (int) $tq_current['course_id'] : 0;

/* --- أرقام العمود الجانبي --- */
$tq_pct     = $tq_by_state['all'] > 0
    ? (int) round($tq_by_state['done'] * 100 / $tq_by_state['all']) : 0;
$tq_secs    = 0;
$tq_left    = 0;
foreach ($tq_all as $l) {
    $tq_secs += $l['seconds'];
    if ($l['state'] !== 'done') $tq_left += $l['seconds'];
}

/* --- قلب الدرس: النوع `lesson` هنا لا `course` --- */
$tq_CI_fav = get_instance();
$tq_CI_fav->load->model('taqdar_favourites_model');
$tq_fav_ids = array_flip($tq_CI_fav->taqdar_favourites_model->ids($tq_uid, 'lesson'));

$tq_heart = static function ($lesson_id, $on) use ($f_course, $f_state, $f_q) {
    ob_start(); ?>
    <form method="post" action="<?php echo base_url('student/favourite'); ?>" class="tq-form-inline">
        <?php echo tq_csrf(); ?>
        <input type="hidden" name="kind" value="lesson">
        <input type="hidden" name="item_id" value="<?php echo (int) $lesson_id; ?>">
        <input type="hidden" name="back" value="lessons">
        <?php /* الفلاتر تسافر مع النموذج فيعود الطالب إلى موضعه من القائمة
                 لا إلى أولها — ولا يفقد كورسا صفاه ولا كلمة بحث كتبها. */ ?>
        <input type="hidden" name="back_course" value="<?php echo (int) $f_course; ?>">
        <input type="hidden" name="back_state" value="<?php echo html_escape($f_state); ?>">
        <input type="hidden" name="back_q" value="<?php echo html_escape($f_q); ?>">
        <button class="tq-fav-heart" type="submit"
                aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"
                title="<?php echo $on ? t('إزالة الدرس من المفضلة') : t('إضافة الدرس إلى المفضلة'); ?>"
                aria-label="<?php echo $on ? t('إزالة الدرس من المفضلة') : t('إضافة الدرس إلى المفضلة'); ?>">
            <?php echo tq_icon('heart', 18); ?>
        </button>
    </form>
    <?php return ob_get_clean();
};

/** شكل الحالة: أيقونة وعائلة لون وكلمة — الثلاثة معا لا اللون وحده. */
$tq_state_face = [
    'done'    => ['award', 'mint',  t('أتممته')],
    'current' => ['flame', 'peach', t('تشاهده الآن')],
    'todo'    => ['play',  'sky',   t('لم يبدأ')],
];

include 'portal_open.php';
?>

<style>
/* --- قائمة الدروس ------------------------------------------------------
   صف الدرس شبكة من أربعة أعمدة ثابتة: علامة الحالة · النص · المدة ·
   الأفعال. والمدة عمود مستقل لا نص داخل السطر حتى تصطف الأرقام تحت
   بعضها فتقرأ عمودا واحدا بلا أن تلاحقها العين. */
.tq-lgroup {
  background: var(--tq-surface); border: 1px solid var(--tq-line);
  border-radius: var(--tq-radius-medium); margin-block-end: var(--tq-space-m);
  overflow: hidden;
}
.tq-lgroup > summary {
  display: flex; align-items: center; gap: var(--tq-space-m);
  padding: var(--tq-space-l); cursor: pointer; list-style: none;
}
.tq-lgroup > summary::-webkit-details-marker { display: none; }
.tq-lgroup__mark { flex: none; color: var(--tq-text2); display: inline-flex;
  transition: transform var(--tq-motion-hover) ease; }
.tq-lgroup[open] .tq-lgroup__mark { transform: rotate(90deg); }
html[dir='rtl'] .tq-lgroup[open] .tq-lgroup__mark { transform: rotate(-90deg); }
.tq-lgroup__name { font: var(--tq-type-bodyStrong); color: var(--tq-navy);
  flex: 1; min-inline-size: 0; }
.tq-lgroup__sub  { display: block; font: var(--tq-type-micro); color: var(--tq-text2); font-weight: 400; }
.tq-lgroup__n    { flex: none; color: var(--tq-text2); font: var(--tq-type-caption); }

.tq-lunit {
  padding: var(--tq-space-m) var(--tq-space-l);
  border-block-start: 1px solid var(--tq-line);
  background: var(--tq-navyWash);
  font: var(--tq-type-micro); color: var(--tq-text2); margin: 0;
}

.tq-lrow {
  display: grid; align-items: center; gap: var(--tq-space-m);
  grid-template-columns: 40px minmax(0, 1fr) auto auto;
  padding: var(--tq-space-m) var(--tq-space-l);
  border-block-start: 1px solid var(--tq-line);
}
.tq-lrow:hover { background: var(--tq-navyWash); }
.tq-lrow__mark {
  inline-size: 40px; block-size: 40px; border-radius: var(--tq-radius-pill);
  display: grid; place-items: center; color: var(--tq-pastel-ink);
}
.tq-lrow__main { min-inline-size: 0; }
.tq-lrow__title { font: var(--tq-type-body); color: var(--tq-navy); margin: 0; }
.tq-lrow__title a { color: inherit; }
.tq-lrow__meta { display: flex; align-items: center; flex-wrap: wrap; gap: var(--tq-space-s);
  font: var(--tq-type-micro); color: var(--tq-text2); margin-block-start: 2px; }
.tq-lrow__time { font: var(--tq-type-numeralSm); color: var(--tq-text2);
  direction: ltr; unicode-bidi: isolate; }
.tq-lrow__acts { display: flex; align-items: center; gap: var(--tq-space-xs); }

@media (max-width: 639.98px) {
  /* الوقت ينزل تحت الاسم والأفعال تبقى في الصف: الشاشة الضيقة تكسر
     أربعة أعمدة إلى سطرين، وزر الفتح أولى بالبقاء في متناول الإبهام. */
  .tq-lrow { grid-template-columns: 36px minmax(0, 1fr) auto; }
  .tq-lrow__mark { inline-size: 36px; block-size: 36px; }
  .tq-lrow__time { grid-column: 2; grid-row: 2; }
}

/* --- رقائق التصفية: نفس رقائق «كورساتي» حرفا بحرف --- */
.tq-chips { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); }
.tq-chip { display: inline-flex; align-items: center; gap: var(--tq-space-xs);
  min-block-size: var(--tq-touch-min); padding: 0 var(--tq-space-m);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-pill);
  background: var(--tq-surface); color: var(--tq-text2); font: var(--tq-type-caption); }
.tq-chip:hover { border-color: var(--tq-navy); color: var(--tq-navy); text-decoration: none; }
.tq-chip[aria-current='true'] { background: var(--tq-navy); border-color: var(--tq-navy);
  color: var(--tq-onAction); font-weight: 700; }
.tq-chip__n { font: var(--tq-type-numeralSm); direction: ltr; unicode-bidi: isolate; opacity: .8; }

.tq-lsearch { display: flex; gap: var(--tq-space-s); margin-block-start: var(--tq-space-l); }
.tq-lsearch .tq-input { flex: 1; min-inline-size: 0; }
</style>

<div class="tq-cols">
    <div>

        <?php if ($tq_current): ?>
            <?php /* بطاقة الاستئناف قبل القائمة: من فتح «دروسي» أكثر ما يريده
                     درس واحد بعينه — الذي كان فيه. وهي تحمل اسمه لا اسم كورسه،
                     فلا يحتاج أن يفتح مجموعة ليصل إليه. */ ?>
            <section class="tq-card tq-card--panel tq-pastel tq-pastel--peach tq-section">
                <div class="tq-row tq-row--between" style="gap:var(--tq-space-l);flex-wrap:wrap">
                    <div style="flex:1;min-inline-size:220px">
                        <span class="tq-pastel__label tq-micro">
                            <?php echo $tq_current['state'] === 'current' ? t('توقفت هنا') : t('درسك التالي'); ?>
                        </span>
                        <h2 class="tq-card__title tq-pastel__title" style="margin:var(--tq-space-xs) 0 0">
                            <?php echo html_escape($tq_current['title']); ?>
                        </h2>
                        <p class="tq-caption" style="margin:var(--tq-space-xs) 0 0">
                            <?php echo html_escape($tq_current['course']); ?>
                            <?php if ($tq_current['unit'] !== ''): ?>
                                · <?php echo html_escape($tq_current['unit']); ?>
                            <?php endif; ?>
                            <?php if ($tq_current['seconds'] > 0): ?>
                                · <?php echo tq_num(tq_s_clock($tq_current['seconds']), 'tq-num--sm'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <a class="tq-btn tq-btn--mastery" href="<?php echo html_escape($tq_current['url']); ?>">
                        <?php echo tq_icon('play', 18); ?>
                        <?php echo $tq_current['state'] === 'current' ? t('أكمل الدرس') : t('ابدأ الدرس'); ?>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($tq_all): ?>
            <section class="tq-card tq-card--panel tq-section" aria-labelledby="tq-lf-h">
                <div class="tq-card__head">
                    <h2 class="tq-card__title" id="tq-lf-h"><?php echo t('اعثر على درس'); ?></h2>
                    <?php if ($tq_has_filter): ?>
                        <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/lessons'); ?>"><?php echo t('مسح التصفية'); ?></a>
                    <?php endif; ?>
                </div>

                <p class="tq-field__label" id="tq-lf-state"><?php echo t('الحالة'); ?></p>
                <div class="tq-chips" role="group" aria-labelledby="tq-lf-state" style="margin-block-end:var(--tq-space-l)">
                    <?php
                    $tq_state_chips = [
                        ['',        t('كل دروسي'),   $tq_by_state['all']],
                        ['current', t('قيد المشاهدة'), $tq_by_state['current']],
                        ['todo',    t('لم تبدأ'),     $tq_by_state['todo']],
                        ['done',    t('أتممتها'),     $tq_by_state['done']],
                    ];
                    foreach ($tq_state_chips as [$key, $label, $n]):
                        $on = ($f_state === $key);
                        ?>
                        <a class="tq-chip" href="<?php echo $tq_query(['state' => $key]); ?>"
                           <?php echo $on ? 'aria-current="true"' : ''; ?>>
                            <?php echo html_escape($label); ?>
                            <span class="tq-chip__n"><?php echo TQ_LRI . (int) $n . TQ_PDI; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if (count($tq_courses) > 1): ?>
                    <p class="tq-field__label" id="tq-lf-course"><?php echo t('الكورس'); ?></p>
                    <div class="tq-chips" role="group" aria-labelledby="tq-lf-course">
                        <a class="tq-chip" href="<?php echo $tq_query(['course' => '']); ?>"
                           <?php echo $f_course === 0 ? 'aria-current="true"' : ''; ?>><?php echo t('كل الكورسات'); ?></a>
                        <?php foreach ($tq_courses as $tq_c): ?>
                            <?php $on = ((int) $tq_c['id'] === $f_course); ?>
                            <a class="tq-chip" href="<?php echo $tq_query(['course' => $on ? '' : (int) $tq_c['id']]); ?>"
                               <?php echo $on ? 'aria-current="true"' : ''; ?>>
                                <?php echo html_escape($tq_c['title']); ?>
                                <?php if ($on): ?>
                                    <span class="tq-chip__x" aria-hidden="true"><?php echo tq_icon('x', 14); ?></span>
                                    <span class="tq-sr"><?php echo t('— اضغط لإلغاء تصفية هذا الكورس'); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php /* البحث نموذج GET يحمل بقية الفلاتر في حقول مخفية: من صفى
                         بكورس ثم بحث لا يفقد كورسه. */ ?>
                <form class="tq-lsearch" method="get" action="<?php echo base_url('student/lessons'); ?>" role="search">
                    <?php if ($f_course > 0): ?><input type="hidden" name="course" value="<?php echo (int) $f_course; ?>"><?php endif; ?>
                    <?php if ($f_state !== ''): ?><input type="hidden" name="state" value="<?php echo html_escape($f_state); ?>"><?php endif; ?>
                    <label class="tq-sr" for="tq-lq"><?php echo t('ابحث في دروسك بالاسم أو الوحدة'); ?></label>
                    <input class="tq-input" id="tq-lq" name="q" type="search" maxlength="80"
                           value="<?php echo html_escape($f_q); ?>" placeholder="<?php echo te('اسم الدرس أو الوحدة…'); ?>">
                    <button class="tq-btn tq-btn--secondary" type="submit"><?php echo t('ابحث'); ?></button>
                </form>
            </section>
        <?php endif; ?>

        <?php if (empty($tq_all)): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'play', 'sky',
                    t('لا دروس بعد'),
                    t('دروس كورساتك تظهر هنا واحدا واحدا: اسم الدرس ووحدته ومدته وحالتك فيه. ')
                    . t('سجل في كورس أو اشترك في باقة صفك ليمتلئ هذا المكان.'),
                    t('عرض الباقات'),
                    base_url('plans')
                ); ?>
            </div>

        <?php elseif (empty($tq_list)): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'search', 'lilac',
                    t('لا درس بهذه التصفية'),
                    $f_q !== ''
                        ? t('لا درس في كورساتك يحمل هذه الكلمة. جرب كلمة أقصر أو امسح التصفية.')
                        : t('جرب حالة أخرى أو كورسا آخر، أو امسح التصفية لترى كل دروسك.'),
                    t('مسح التصفية'),
                    base_url('student/lessons')
                ); ?>
            </div>

        <?php else: ?>
            <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">
                <?php echo tq_iso(tq_lessons_word(count($tq_list), t('لا دروس'), 'nom')); ?>
                <?php echo count($tq_groups) > 1
                    ? tq_iso(t('في ') . tq_count_units(count($tq_groups), t('كورس'), t('كورسان'), t('كورسين'), t('كورسات'), t('كورسا'), '', 'obl'))
                    : ''; ?>
            </p>

            <?php foreach ($tq_groups as $g): ?>
                <?php
                /* تفتح المجموعة إذا: صفيت على كورسها، أو فيها درسك الحالي،
                   أو كانت المجموعة الوحيدة، أو كان الطالب يبحث بكلمة —
                   والبحث بلا فتح يعرض نتائج مخبأة خلف مثلثات مغلقة. */
                $open = ($f_course > 0) || (count($tq_groups) === 1)
                     || ($f_q !== '') || ((int) $g['id'] === $tq_open_course);
                ?>
                <details class="tq-lgroup" <?php echo $open ? 'open' : ''; ?>>
                    <summary>
                        <span class="tq-lgroup__mark" aria-hidden="true"><?php echo tq_icon('chev-next', 18); ?></span>
                        <span class="tq-lgroup__name">
                            <?php echo html_escape($g['title']); ?>
                            <span class="tq-lgroup__sub">
                                <?php echo html_escape($g['subject'] !== '' ? $g['subject'] : t('كورس')); ?>
                                <?php if ($g['level'] !== ''): ?>
                                    · <?php echo html_escape(tq_s_level($g['level'])); ?>
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="tq-lgroup__n">
                            <?php echo tq_iso($g['done'] . t(' من ') . $g['total']); ?>
                        </span>
                    </summary>

                    <?php foreach ($g['units'] as $unit_title => $unit_lessons): ?>
                        <?php if (count($g['units']) > 1 || $unit_title !== 'دروس بلا وحدة'): ?>
                            <p class="tq-lunit"><?php echo html_escape($unit_title); ?></p>
                        <?php endif; ?>

                        <?php foreach ($unit_lessons as $l): ?>
                            <?php [$ic, $fam, $state_word] = $tq_state_face[$l['state']]; ?>
                            <div class="tq-lrow">
                                <span class="tq-lrow__mark tq-pastel tq-pastel--<?php echo $fam; ?>" aria-hidden="true">
                                    <?php echo tq_icon($ic, 20); ?>
                                </span>

                                <div class="tq-lrow__main">
                                    <p class="tq-lrow__title">
                                        <a href="<?php echo html_escape($l['url']); ?>"><?php echo html_escape($l['title']); ?></a>
                                    </p>
                                    <p class="tq-lrow__meta">
                                        <span><?php echo html_escape($state_word); ?></span>
                                        <?php if ($l['free']): ?>
                                            <?php echo tq_badge('progress', t('درس تجريبي')); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <span class="tq-lrow__time">
                                    <?php if ($l['seconds'] > 0): ?>
                                        <span class="tq-sr"><?php echo t('مدة الدرس'); ?></span>
                                        <?php echo TQ_LRI . html_escape(tq_s_clock($l['seconds'])) . TQ_PDI; ?>
                                    <?php endif; ?>
                                </span>

                                <span class="tq-lrow__acts">
                                    <?php echo $tq_heart($l['id'], isset($tq_fav_ids[(int) $l['id']])); ?>
                                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo html_escape($l['url']); ?>">
                                        <?php echo $l['state'] === 'done' ? t('راجعه') : ($l['state'] === 'current' ? t('أكمله') : t('شاهده')); ?>
                                        <span class="tq-sr">— <?php echo html_escape($l['title']); ?></span>
                                    </a>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <aside class="tq-aside">

        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('دروسك بالأرقام'); ?></h2></div>

            <?php if (empty($tq_all)): ?>
                <?php echo tq_s_empty('chart', 'mint', t('لا أرقام بعد'),
                    t('يظهر هنا عدد ما أتممت من دروسك وما بقي، وكم يستغرق الباقي.'), '', '', true); ?>
            <?php else: ?>
                <div class="tq-row" style="gap:var(--tq-space-l)">
                    <?php echo tq_ring($tq_pct, 108, 10); ?>
                    <div>
                        <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo t('من دروسك'); ?></p>
                        <p class="tq-caption" style="margin:0">
                            <?php echo tq_s_lessons_word($tq_by_state['done'], $tq_by_state['all']); ?>
                        </p>
                    </div>
                </div>

                <ul class="tq-s-list" style="margin-block-start:var(--tq-space-l)">
                    <?php
                    $rows = [
                        ['done', t('دروس أتممتها'),    $tq_by_state['done']],
                        ['due',  t('دروس قيد المشاهدة'), $tq_by_state['current']],
                        ['idle', t('دروس لم تبدأ'),    $tq_by_state['todo']],
                    ];
                    foreach ($rows as [$dot, $label, $n]):
                        ?>
                        <li class="tq-row tq-row--between">
                            <span class="tq-row" style="gap:var(--tq-space-s)">
                                <span class="tq-s-dot tq-s-dot--<?php echo $dot; ?>" aria-hidden="true"></span>
                                <span class="tq-caption"><?php echo html_escape($label); ?></span>
                            </span>
                            <?php echo tq_num($n, 'tq-num--sm'); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($tq_left > 0): ?>
                    <p class="tq-micro" style="margin-block-start:var(--tq-space-l)">
                        <?php /* رقم يقرر: «كم بقي علي؟» جوابه وقت لا عدد. */ ?>
                        <?php /* `tq_iso` لا `tq_num`: النص «٣٤ س ١٤ د» عربي فيه أرقام،
                                 و`tq_num` يعزله كوحدة **يسارية** فينقلب ترتيبه على الشاشة
                                 («س ٣٤ د ١٤»). و`tq_iso` يعزل تتابع الأرقام وحده. */ ?>
                        <?php echo t('يتبقى من دروسك ____ من أصل ____.',
                            array(tq_iso(tq_s_hours($tq_left)), tq_iso(tq_s_hours($tq_secs)))); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php /* الشاشة الأخت: من فتح «دروسي» يريد درسا، ومن أراد الكورس كاملا
                 — غلافه وحالته ونسبته — بابه هنا بنقرة واحدة لا بحثا في القائمة. */ ?>
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('كورساتك'); ?></h2></div>
            <p class="tq-caption" style="margin:0">
                <?php echo tq_iso(tq_count_units(count($tq_courses), t('كورس مسجل'), t('كورسان مسجلان'),
                    t('كورسين مسجلين'), t('كورسات مسجلة'), t('كورسا مسجلا'), t('لا كورسات مسجلة'), 'nom')); ?>
                <?php echo t('— بأغلفتها وحالاتها ونسبة تقدمك في كل واحد.'); ?>
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--block" style="margin-block-start:var(--tq-space-l)"
               href="<?php echo base_url('student/courses'); ?>"><?php echo t('افتح كورساتي'); ?></a>
        </section>

        <section class="tq-card tq-card--panel tq-pastel tq-pastel--lilac">
            <div class="tq-card__head">
                <h2 class="tq-card__title tq-pastel__title"><?php echo t('دروسك المفضلة'); ?></h2>
                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('heart', 24); ?></span>
            </div>
            <p class="tq-pastel__body">
                <?php echo t('اضغط القلب بجوار أي درس هنا ليصير في متناولك من شاشة المفضلة، بلا بحث في القائمة.'); ?>
            </p>
            <a class="tq-btn tq-btn--secondary tq-btn--block" href="<?php echo base_url('student/favourites'); ?>">
                <?php echo t('افتح المفضلة'); ?>
            </a>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
