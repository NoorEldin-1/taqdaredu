<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * كورساتي — كل الكورسات المسجلة وتقدمها.
 *
 * **كانت هذه الشاشة تسمى «دروسي»** وتعرض بطاقات كورسات: عنوان يعد بدرس
 * وشبكة تعطي كورسا. فلم يكن في بوابة الطالب مدخل واحد إلى **درس** بعينه:
 * من أراد «درس الكسور» فتح الكورس ومسح منهجه بعينه. والكورس والدرس رتبتان
 * لا اسمان لشيء واحد: الكورس ما يشترى ويسجل فيه، والدرس ما يشاهد ويكمل.
 *
 * فصارتا شاشتين: هذه للكورسات — الغلاف والحالة والمدة ونسبة التقدم —
 * و`tq_lessons.php` للدروس أنفسها. وكل بطاقة هنا لها باب إلى دروس كورسها.
 *
 * الحالة والتصفية تعملان في الخادم لا في المتصفح: الرابط يحمل الحالة،
 * فيمكن حفظه ومشاركته، ويعمل بلا جافاسكربت. وزر «مسح الفلاتر» لا يظهر
 * إلا حين يوجد فلتر فعلا — زر لا يفعل شيئا يعلم المستخدم تجاهل الأزرار.
 *
 * وشكل الضابطين تغير عن شريط تبويبات وقائمتين منسدلتين:
 *   • الحالة صارت **خريطة محطات** — الحالات الأربع ترتيب لا صف، والطريق
 *     بينها يمتد بنسبة الإنجاز الفعلية. انظر `$tq_stations` أدناه.
 *   • المادة والمستوى صارا **رقائق روابط** — نقرة واحدة بدل ثلاث، والخيارات
 *     كلها ظاهرة بلا فتح قائمة.
 * والحالة في الرابط في الحالين كما كانت، فلم يتغير شيء تحت الشكل.
 *
 * موصول بالقاعدة: enrol · course · lesson · watch_histories · category
 * · users.wishlist (قلب التفضيل على البطاقة).
 */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_uid = isset($user_id) ? (int) $user_id : tq_s_uid();
if (!isset($tq_counts)) $tq_counts = tq_s_counts($tq_uid);

$tq_nav   = 'courses';
$tq_role  = 'student';
$tq_title = t('كورساتي');
$tq_sub   = t('كل الكورسات المسجلة لديك وتقدمك فيها');
$tq_icon  = 'book';

$tq_all = tq_s_enrolled($tq_uid);

/* --- الفلاتر --- */
$f_state   = (string) $this->input->get('state', true);
$f_subject = (string) $this->input->get('subject', true);
$f_stage   = (string) $this->input->get('stage', true);
$f_all     = (string) $this->input->get('all', true);
if (!in_array($f_state, ['progress', 'done', 'idle'], true)) $f_state = '';

/* الحالة فلتر كغيرها: تضبط من التبويب ومن القائمة معا، وزر «مسح الفلاتر»
   الذي لا يظهر معها يترك الطالب أمام قائمة مصفاة بلا باب يعيده منها. */
$tq_has_filter = ($f_subject !== '' || $f_stage !== '' || $f_state !== '');

/* خيارات التصفية من كورسات الطالب نفسها لا من كل تصنيفات المنصة */
$opt_subjects = [];
$opt_stages   = [];
/* التصفية بالمادة **باسمها** لا بمعرف تصنيف: `course.category_id` صفر في كل
   كورس منشور، فالمفتاح الرقمي كان يجمع كل المواد في خيار واحد اسمه آخر ما
   مر. والاسم يأتي من `paths.subject_id` وهو ثابت ومقروء في الرابط. */
foreach ($tq_all as $c) {
    $s = tq_s_subject($c['category_id'], '', $c['id']);
    if ($s !== '') $opt_subjects[$s] = $s;
    if (trim((string) $c['level']) !== '') $opt_stages[$c['level']] = $c['level'];
}
ksort($opt_subjects);
ksort($opt_stages);

$tq_counts_by = ['all' => count($tq_all), 'progress' => 0, 'done' => 0, 'idle' => 0];
foreach ($tq_all as $c) $tq_counts_by[$c['status']]++;

$tq_list = array_values(array_filter($tq_all, function ($c) use ($f_state, $f_subject, $f_stage) {
    if ($f_state !== '' && $c['status'] !== $f_state) return false;
    if ($f_subject !== '' && tq_s_subject($c['category_id'], '', $c['id']) !== $f_subject) return false;
    if ($f_stage !== '' && (string) $c['level'] !== $f_stage) return false;
    return true;
}));

/* --- إجمالي التقدم: دروس مكتملة من إجمالي دروس الكورسات، لا متوسط نسب --- */
$tq_total_lessons = 0;
$tq_done_lessons  = 0;
foreach ($tq_all as $c) { $tq_total_lessons += $c['lessons']; $tq_done_lessons += $c['done']; }
$tq_overall = $tq_total_lessons > 0 ? (int) round($tq_done_lessons * 100 / $tq_total_lessons) : 0;

/* --- بناء روابط تحفظ بقية الفلاتر --- */
$tq_query = function ($over = []) use ($f_state, $f_subject, $f_stage) {
    $p = array_merge(['state' => $f_state, 'subject' => $f_subject, 'stage' => $f_stage], $over);
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return base_url('student/courses') . ($p ? '?' . http_build_query($p) : '');
};

$tq_limit   = 9;
$tq_visible = ($f_all === '1') ? $tq_list : array_slice($tq_list, 0, $tq_limit);

/**
 * محطات الرحلة — بديل شريط التبويبات.
 *
 * كانت الحالات الأربع شريط تبويبات نصية متساوية الوزن: أربع كلمات في سطر
 * لا تقول أين يقف الطالب من طريقه. وهي في الحقيقة **ترتيب**: ما لم يبدأ،
 * ثم ما هو جار، ثم ما أتقن. فترسم طريقا لا صفا، ويقرأ موضعه منه بالنظر.
 *
 * الترتيب هنا هو ترتيب المسير قصدا (`الكل` رأس الطريق ثم المحطات الثلاث
 * على ترتيبها الطبيعي)، ولا يعاد ترتيبه حسب الأعداد — طريق يتبدل ترتيبه
 * في كل زيارة ليس طريقا.
 */
$tq_stations = [
    ['',         t('كل كورساتي'), 'grid',  'sky',   $tq_counts_by['all'],      t('كل ما سجلت فيه')],
    ['idle',     t('لم تبدأ'),    'book',  'lilac', $tq_counts_by['idle'],     t('بانتظار أول درس')],
    ['progress', t('قيد التقدم'), 'flame', 'peach', $tq_counts_by['progress'], t('بدأتها ولم تنهها')],
    ['done',     t('مكتملة'),     'award', 'mint',  $tq_counts_by['done'],     t('أنهيت دروسها كلها')],
];

/* امتلاء الطريق = نسبة الإنجاز الفعلية نفسها المعروضة في الحلقة جانبا،
   لا قيمة زخرفية. فالطريق يطول بطول ما أنجز، وإلا صار رسما لا مقياسا. */
$tq_path_pct = $tq_overall;

/**
 * قلب التفضيل على البطاقة.
 *
 * شاشة المفضلة تقول للطالب «اضغط القلب على أي درس وسيظهر هنا» — ولم يكن
 * في هذه الشاشة ولا في المواد قلب يضغط. فكانت المفضلة بابا لا يفتح إلا
 * من الداخل: تعرض ما فيها وتشرح كيف يزاد، ولا موضع للزيادة.
 */
$tq_CI_fav  = get_instance();
$tq_CI_fav->load->model('taqdar_favourites_model');
$tq_wish    = array_flip($tq_CI_fav->taqdar_favourites_model->course_ids($tq_uid));

/* TQ-COURSE-SALE — أي هذه الكورسات اشتري مفردا؟
   يقرأ من `subscriptions` لا من `enrol`: الثاني يمتلئ من الباقة أيضا،
   فمن يسأل «أهذا لي أم لباقتي؟» لا يجد فيه جوابا. ونداء واحد للصفحة
   كلها لا نداء لكل بطاقة. */
$tq_CI_fav->load->model('taqdar_course_sale_model', 'tq_cs');
$tq_bought = $tq_CI_fav->tq_cs->owned_by($tq_uid);

$tq_fav_btn = static function ($course_id, $on) {
    ob_start(); ?>
    <form method="post" action="<?php echo base_url('student/favourite'); ?>" class="tq-form-inline">
        <input type="hidden" name="kind" value="course">
        <input type="hidden" name="item_id" value="<?php echo (int) $course_id; ?>">
        <input type="hidden" name="back" value="courses">
        <button class="tq-fav-heart" type="submit"
                aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"
                title="<?php echo $on ? t('إزالة من المفضلة') : t('إضافة إلى المفضلة'); ?>"
                aria-label="<?php echo $on ? t('إزالة الكورس من المفضلة') : t('إضافة الكورس إلى المفضلة'); ?>">
            <?php echo tq_icon('heart', 18); ?>
        </button>
    </form>
    <?php return ob_get_clean();
};

include 'portal_open.php';
?>

<style>
/* --- خريطة الرحلة -------------------------------------------------------
   أربع محطات على طريق منحن.

   كان الطريق شريطين مستقيمين بـ`::before/::after`، فقرئ شريط تقدم لا طريقا،
   ولم يكن للميداليات لون: `.tq-map__medal { background: var(--tq-surface) }`
   مكتوبة في `<style>` الصفحة، وهي تلي `components.css` في الورقة بوزن مساو —
   فتغلب `.tq-pastel--*` وتطفئ عائلة كل محطة. فصارت أربع دوائر بيضاء متشابهة.

   والطريق الآن مسار SVG واحد يمر بمراكز الميداليات الأربع، وينحني بينها
   صعودا وهبوطا — فيقرأ طريقا لا خطا. وثلاث طبقات فوق بعضها على المسار نفسه:
   الإسفلت، ثم ما قطع منه بلون العلامة، ثم خط الحارة المتقطع.

   الهندسة مقفلة على أرقام ثابتة، ولذلك تعمل:
     الميدالية 88 والإزاحة 56، فالارتفاع 144 وهو `viewBox` رأسيا بالضبط.
     مراكزها: الزوجية عند 44 والفردية عند 100 — وهي نقاط المسار نفسها.
     وأفقيا `preserveAspectRatio="none"` يمط المسار مع عرض البطاقة، و
     `vector-effect="non-scaling-stroke"` يمنع سماكة الخط أن تمط معه. */
.tq-map { position: relative; }
.tq-map__head { display: flex; align-items: center; justify-content: space-between;
  gap: var(--tq-space-m); flex-wrap: wrap; margin-block-end: var(--tq-space-l); }

.tq-map__road { position: relative; }

.tq-map__svg {
  position: absolute; inset-block-start: 0; inset-inline: 0;
  inline-size: 100%; block-size: 144px; overflow: visible;
}
/* الشاشة عربية والطريق يبدأ من اليمين، والمسار مرسوم من يساره — فيعكس. */
html[dir='rtl'] .tq-map__svg { transform: scaleX(-1); }

.tq-map__asphalt, .tq-map__done, .tq-map__lane { fill: none; stroke-linecap: round; }
.tq-map__asphalt { stroke: var(--tq-line); stroke-width: 16; }
.tq-map__done    { stroke: var(--tq-teal); stroke-width: 16; }
/* خط الحارة: أبيض على الإسفلت، فيظهر على ما قطع ويخفت على ما لم يقطع —
   والفرق نفسه يقول أين وصل الطالب مرة ثانية بلا لون إضافي. */
.tq-map__lane { stroke: var(--tq-surface); stroke-width: 2.5; stroke-dasharray: 9 13; opacity: .85; }

.tq-map__stops { position: relative; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--tq-space-s); align-items: start; }

.tq-map__stop { display: grid; justify-items: center; gap: 2px;
  padding: 0 var(--tq-space-xs) var(--tq-space-s);
  color: var(--tq-text2); text-align: center; }
.tq-map__stop:hover { text-decoration: none; }
/* المحطتان الثانية والرابعة أوطأ — وهو انحناء الطريق نفسه لا زخرفة. */
.tq-map__stop:nth-child(even) { margin-block-start: 56px; }

.tq-map__medal { inline-size: 88px; block-size: 88px; border-radius: var(--tq-radius-pill);
  display: grid; place-items: center; position: relative; padding: 0;
  color: var(--tq-pastel-ink);
  /* الحلقة البيضاء تفصل الميدالية عن الطريق المار خلفها */
  box-shadow: 0 0 0 6px var(--tq-surface), var(--tq-shadow-soft);
  transition: transform var(--tq-motion-hover) ease, box-shadow var(--tq-motion-hover) ease; }
.tq-map__stop:hover .tq-map__medal { transform: translateY(-4px); }

/* العدد على حافة الميدالية العليا، بحلقة بيضاء تفصله عنها وعن الطريق. */
.tq-map__n { position: absolute; inset-block-start: -4px; inset-inline-end: -4px;
  min-inline-size: 26px; padding: 2px 7px; border-radius: var(--tq-radius-pill);
  background: var(--tq-navy); color: var(--tq-onAction);
  font: var(--tq-type-numeralSm); font-weight: 700; line-height: 1.5; text-align: center;
  unicode-bidi: isolate; direction: ltr; box-shadow: 0 0 0 3px var(--tq-surface); }
.tq-map__n--zero { background: var(--tq-surface); color: var(--tq-text2); box-shadow: 0 0 0 1px var(--tq-line); }

.tq-map__label { font: var(--tq-type-bodyStrong); color: var(--tq-navy); margin-block-start: var(--tq-space-s); }
.tq-map__hint  { font: var(--tq-type-micro); color: var(--tq-text2); }

/* المحطة المعروضة: حلقة كحلية ملتصقة بالميدالية واسم مسطر — لا لون وحده،
   فالتمييز باللون وحده يسقط على من لا يفرق بين اللونين. */
.tq-map__stop[aria-current='page'] .tq-map__medal {
  box-shadow: 0 0 0 3px var(--tq-surface), 0 0 0 6px var(--tq-navy), var(--tq-shadow-raised);
}
/* الشارة تعلو الحلقة الكحلية لا تختفي تحتها */
.tq-map__stop[aria-current='page'] .tq-map__n { z-index: 1; }
.tq-map__stop[aria-current='page'] .tq-map__label { text-decoration: underline; text-underline-offset: 4px; }

@media (max-width: 767.98px) {
  /* الطريق يقصر فيتراكب مع الأسماء — فيصير شبكة محطات بلا طريق ولا إزاحة. */
  .tq-map__svg { display: none; }
  .tq-map__stops { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--tq-space-l); }
  .tq-map__stop:nth-child(even) { margin-block-start: 0; }
  .tq-map__medal { inline-size: 64px; block-size: 64px; }
}

/* --- رقائق التصفية ------------------------------------------------------
   كانت قائمتين منسدلتين وزر «طبق التصفية»: ثلاث نقرات لتصفية بمادة واحدة،
   ولا يرى الطالب خياراته حتى يفتح القائمة. والرقائق روابط: نقرة واحدة،
   والخيارات كلها ظاهرة، والحالة في الرابط كما كانت. */
.tq-chips { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); }
.tq-chip { display: inline-flex; align-items: center; gap: var(--tq-space-xs);
  min-block-size: var(--tq-touch-min); padding: 0 var(--tq-space-m);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-pill);
  background: var(--tq-surface); color: var(--tq-text2); font: var(--tq-type-caption); }
.tq-chip:hover { border-color: var(--tq-navy); color: var(--tq-navy); text-decoration: none; }
.tq-chip[aria-current='true'] { background: var(--tq-navy); border-color: var(--tq-navy);
  color: var(--tq-onAction); font-weight: 700; }
.tq-chip[aria-current='true'] .tq-chip__x { opacity: .85; }
</style>

<div class="tq-cols">
    <div>

        <?php if ($tq_all): ?>
        <section class="tq-card tq-card--panel tq-map tq-section" aria-labelledby="tq-map-h">
            <div class="tq-map__head">
                <div>
                    <h2 class="tq-card__title" id="tq-map-h" style="margin:0"><?php echo t('رحلتك في كورساتك'); ?></h2>
                    <p class="tq-micro" style="margin:var(--tq-space-xs) 0 0">
                        <?php echo t('اضغط أي محطة لترى كورساتها — والطريق يمتد بقدر ما أنجزت من دروسها.'); ?>
                    </p>
                </div>
                <span class="tq-pill">
                    <?php echo tq_num($tq_path_pct . '%', 'tq-num--sm'); ?>
                    <span class="tq-micro"><?php echo t('من دروس كورساتك'); ?></span>
                </span>
            </div>

            <nav class="tq-map__road" aria-label="<?php echo te('تصفية الكورسات بمحطة الرحلة'); ?>">
                <?php
                /* المسار يمر بمراكز الميداليات الأربع: (125,44) (375,100)
                   (625,44) (875,100) — والمنحنيات بينها مكعبة فيصير الطريق
                   موجة لا خطا مكسورا.

                   وما قطع منه يقص بمستطيل لا بـ`stroke-dasharray`: القص
                   بالمسافة الأفقية يقع عند النقطة نفسها التي تقف عندها
                   الميدالية، و`pathLength` مع `non-scaling-stroke` تحسب
                   طول الشرطة في فضاءين مختلفين فيختلف الامتلاء بين
                   المتصفحات. والمستطيل لا يحتمل تأويلا. */
                $tq_road = 'M125 44 C255 44 245 100 375 100 S495 44 625 44 S745 100 875 100';
                $tq_gone = max(0, min(100, (int) $tq_path_pct));
                /* الطريق يمتد من 125 إلى 875، فالقص عند بدايته زائد نسبته. */
                $tq_cut  = 125 + (750 * $tq_gone / 100);
                ?>
                <svg class="tq-map__svg" viewBox="0 0 1000 144" preserveAspectRatio="none"
                     aria-hidden="true" focusable="false">
                    <defs>
                        <clipPath id="tq-map-cut">
                            <rect x="0" y="-30" width="<?php echo $tq_cut; ?>" height="204"/>
                        </clipPath>
                    </defs>
                    <path class="tq-map__asphalt" d="<?php echo $tq_road; ?>" vector-effect="non-scaling-stroke"/>
                    <path class="tq-map__done" d="<?php echo $tq_road; ?>" vector-effect="non-scaling-stroke"
                          clip-path="url(#tq-map-cut)"/>
                    <path class="tq-map__lane" d="<?php echo $tq_road; ?>" vector-effect="non-scaling-stroke"/>
                </svg>

                <div class="tq-map__stops">
                    <?php foreach ($tq_stations as [$key, $label, $icon, $fam, $n, $hint]): ?>
                        <?php $is = ($f_state === $key); ?>
                        <a class="tq-map__stop" href="<?php echo $tq_query(['state' => $key, 'all' => null]); ?>"
                           <?php echo $is ? 'aria-current="page"' : ''; ?>>
                            <span class="tq-map__medal tq-pastel tq-pastel--<?php echo $fam; ?>">
                                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon($icon, 32); ?></span>
                                <?php /* بلا فراغ حول الرقم: الشارة حبة ضيقة، والمسافة
                                         البادئة تزيد عرضها فتخرج عن حافة الميدالية. */ ?>
                                <span class="tq-map__n<?php echo $n ? '' : ' tq-map__n--zero'; ?>"><?php echo TQ_LRI . (int) $n . TQ_PDI; ?></span>
                            </span>
                            <span class="tq-map__label"><?php echo html_escape($label); ?></span>
                            <span class="tq-map__hint"><?php echo html_escape($hint); ?></span>
                            <span class="tq-sr">
                                <?php echo tq_iso((int) $n . t(' كورسا')); ?><?php echo $is ? t(' — المحطة المعروضة الآن') : ''; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </nav>
        </section>
        <?php endif; ?>

        <?php if (empty($tq_all)): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'book', 'mint',
                    t('لا كورسات بعد'),
                    t('كل كورس تسجل فيه يظهر هنا ببطاقة تحمل غلافه وحالته ومدته ونسبة تقدمك فيه.'),
                    t('تصفح الكورسات'),
                    /* الوجهة `catalog` لا `plans`: الزر يقول «تصفح» فيجب أن يفتح
                       ما يتصفح — «المواد والبرامج التعليمية». و`plans` صفحة
                       شراء، ومن لا كورس له يريد أن يرى ما يدرس قبل أن يرى
                       ما يدفع. */
                    base_url('catalog')
                ); ?>
            </div>

        <?php elseif (empty($tq_list)): ?>
            <div class="tq-card">
                <?php echo tq_s_empty(
                    'search', 'sky',
                    t('لا نتائج لهذه التصفية'),
                    t('جرب توسيع التصفية أو امسحها لترى كل كورساتك مرة أخرى.'),
                    t('مسح الفلاتر'),
                    base_url('student/courses')
                ); ?>
            </div>

        <?php else: ?>
            <div class="tq-s-grid3 tq-stagger">
                <?php foreach ($tq_visible as $c): ?>
                    <?php
                    $badge = $c['status'] === 'done'
                        ? tq_badge('mastered', t('مكتملة'))
                        : ($c['status'] === 'progress' ? tq_badge('progress', t('قيد التقدم')) : tq_badge('idle', t('لم تبدأ')));
                    $href = tq_s_lesson_url($c['id'], $c['resume_id']);
                    ?>
                    <article class="tq-card tq-s-course">
                        <a href="<?php echo $href; ?>">
                            <?php echo tq_s_thumb($c['title'], $c['thumbnail'], $c['index'], $badge,
                                $c['seconds'] ? tq_s_clock($c['seconds']) : ''); ?>
                        </a>
                        <div class="tq-row tq-row--between" style="gap:var(--tq-space-s);align-items:flex-start">
                            <h3 class="tq-s-course__title" style="flex:1;min-inline-size:0">
                                <a href="<?php echo $href; ?>" style="color:var(--tq-navy)"><?php echo html_escape($c['title']); ?></a>
                            </h3>
                            <?php echo $tq_fav_btn($c['id'], isset($tq_wish[(int) $c['id']])); ?>
                        </div>
                        <p class="tq-micro" style="margin:0">
                            <?php echo html_escape($c['level'] !== '' ? tq_s_level($c['level']) : tq_s_subject($c['category_id'], t('كورس'), $c['id'])); ?>
                            <?php
                            /* TQ-COURSE-SALE — **ولماذا هو مفتوح.**
                               «كورساتي» تقرأ `enrol`، وهو يمتلئ من الباقة
                               ومن الشراء المفرد معا — فالبطاقتان تبدوان
                               سواء. وليستا: ما اشتري مفردا يبقى بعد انتهاء
                               الاشتراك، وما فتحته الباقة يقفل معها. وطالب
                               لا يعرف الفرق يظن أنه فقد ما دفع ثمنه. */
                            if (isset($tq_bought[(int) $c['id']])): ?>
                                · <span style="color:var(--tq-teal);font-weight:700"><?php echo t('مشتراة مفردة'); ?></span>
                            <?php endif; ?>
                        </p>
                        <?php echo tq_progress($c['progress'], t('تقدمك في ') . $c['title']); ?>
                        <p class="tq-caption" style="margin:0"><?php echo tq_s_lessons_word($c['done'], $c['lessons']); ?></p>

                        <?php /* بابان لا باب: الغلاف والعنوان يستأنفان من موضع التوقف —
                                 وهو ما يريده من يفتح كورسا ليكمل. وهذا الباب الثاني
                                 لمن يريد **درسا بعينه**: يفتح «دروسي» مصفاة على هذا
                                 الكورس وحده، فيرى منهجه سطرا سطرا بحالة كل درس. */ ?>
                        <div class="tq-row" style="gap:var(--tq-space-s);margin-block-start:var(--tq-space-m)">
                            <a class="tq-btn tq-btn--secondary tq-btn--sm" style="flex:1"
                               href="<?php echo $href; ?>">
                                <?php echo $c['status'] === 'idle' ? t('ابدأ الكورس') : t('تابع من حيث توقفت'); ?>
                            </a>
                            <a class="tq-btn tq-btn--ghost tq-btn--sm"
                               href="<?php echo base_url('student/lessons') . '?course=' . (int) $c['id']; ?>">
                                <?php echo t('دروسه'); ?>
                                <span class="tq-sr">— <?php echo html_escape($c['title']); ?></span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($f_all !== '1' && count($tq_list) > $tq_limit): ?>
                <p style="text-align:center;margin-block-start:var(--tq-space-xl)">
                    <a class="tq-btn tq-btn--secondary" href="<?php echo $tq_query(['all' => '1']); ?>">
                        <?php echo t('عرض المزيد'); ?>
                        <span class="tq-sr"><?php echo t('من الكورسات'); ?></span>
                    </a>
                </p>
            <?php endif; ?>
        <?php endif; ?>

    </div>

    <aside class="tq-aside">

        <!-- التصفية: نموذج GET يعمل بلا جافاسكربت، وكل حقل له تسمية مرتبطة. -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head">
                <h2 class="tq-card__title"><?php echo t('تصفية الكورسات'); ?></h2>
                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('cog'); ?></span>
            </div>

            <?php /* روابط لا نموذج: نقرة واحدة تصفي، والخيارات كلها ظاهرة بلا فتح
                     قائمة. والحالة تبقى في الرابط كما كانت، فيحفظ ويشارك ويعمل
                     بلا جافاسكربت. والرقيقة المختارة تحمل × تلغيها في مكانها —
                     فلا يحتاج الطالب أن ينزل إلى «مسح الفلاتر» ليلغي واحدا. */ ?>

            <?php if ($opt_subjects): ?>
                <p class="tq-field__label" id="tq-flt-subject"><?php echo t('المادة'); ?></p>
                <div class="tq-chips" role="group" aria-labelledby="tq-flt-subject" style="margin-block-end:var(--tq-space-l)">
                    <a class="tq-chip" href="<?php echo $tq_query(['subject' => null, 'all' => null]); ?>"
                       <?php echo $f_subject === '' ? 'aria-current="true"' : ''; ?>><?php echo t('كل المواد'); ?></a>
                    <?php foreach ($opt_subjects as $name): ?>
                        <?php $on = ((string) $name === $f_subject); ?>
                        <a class="tq-chip" href="<?php echo $tq_query(['subject' => $on ? null : $name, 'all' => null]); ?>"
                           <?php echo $on ? 'aria-current="true"' : ''; ?>>
                            <?php echo html_escape($name); ?>
                            <?php if ($on): ?><span class="tq-chip__x" aria-hidden="true"><?php echo tq_icon('x', 14); ?></span><?php endif; ?>
                            <?php if ($on): ?><span class="tq-sr"><?php echo t('— اضغط لإلغاء تصفية هذه المادة'); ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($opt_stages): ?>
                <p class="tq-field__label" id="tq-flt-stage"><?php echo t('المستوى'); ?></p>
                <div class="tq-chips" role="group" aria-labelledby="tq-flt-stage">
                    <a class="tq-chip" href="<?php echo $tq_query(['stage' => null, 'all' => null]); ?>"
                       <?php echo $f_stage === '' ? 'aria-current="true"' : ''; ?>><?php echo t('كل المستويات'); ?></a>
                    <?php foreach ($opt_stages as $lvl): ?>
                        <?php $on = ((string) $lvl === $f_stage); ?>
                        <a class="tq-chip" href="<?php echo $tq_query(['stage' => $on ? null : $lvl, 'all' => null]); ?>"
                           <?php echo $on ? 'aria-current="true"' : ''; ?>>
                            <?php echo html_escape(tq_s_level($lvl)); ?>
                            <?php if ($on): ?><span class="tq-chip__x" aria-hidden="true"><?php echo tq_icon('x', 14); ?></span><?php endif; ?>
                            <?php if ($on): ?><span class="tq-sr"><?php echo t('— اضغط لإلغاء تصفية هذا المستوى'); ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php /* الحالة لا تكرر هنا: محطات الخريطة أعلاه هي ضابطها، وضابطان
                     لقيمة واحدة يجعل أحدهما يبدو معطلا حين يحركها الآخر. */ ?>

            <?php if ($tq_has_filter): ?>
                <a class="tq-btn tq-btn--secondary tq-btn--block" style="margin-block-start:var(--tq-space-l)"
                   href="<?php echo base_url('student/courses'); ?>"><?php echo t('مسح كل الفلاتر'); ?></a>
            <?php endif; ?>
        </section>

        <!-- ملخص تقدمك -->
        <section class="tq-card tq-card--panel">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('ملخص تقدمك'); ?></h2></div>

            <?php if (empty($tq_all)): ?>
                <?php echo tq_s_empty(
                    'chart', 'mint',
                    t('لا تقدم بعد'),
                    t('حلقة تقدمك وتوزيع حالات كورساتك يظهران هنا بعد أول درس تكمله.'),
                    '', '', true
                ); ?>
            <?php else: ?>
                <div class="tq-row" style="gap:var(--tq-space-l)">
                    <?php echo tq_ring($tq_overall, 108, 10); ?>
                    <div>
                        <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo t('إجمالي التقدم'); ?></p>
                        <p class="tq-caption" style="margin:0">
                            <?php echo tq_s_lessons_word($tq_done_lessons, $tq_total_lessons); ?>
                        </p>
                    </div>
                </div>

                <ul class="tq-s-list" style="margin-block-start:var(--tq-space-l)">
                    <?php
                    $rows = [
                        ['done', t('كورسات مكتملة'),    $tq_counts_by['done']],
                        ['due',  t('كورسات قيد التقدم'), $tq_counts_by['progress']],
                        ['idle', t('كورسات لم تبدأ'),   $tq_counts_by['idle']],
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

                <a class="tq-btn tq-btn--secondary tq-btn--block" style="margin-block-start:var(--tq-space-l)"
                   href="<?php echo base_url('student/reports'); ?>"><?php echo t('عرض تقرير تفصيلي'); ?></a>
            <?php endif; ?>
        </section>

        <!-- حصص بالطلب -->
        <section class="tq-card tq-card--panel tq-pastel tq-pastel--sky">
            <div class="tq-card__head">
                <h2 class="tq-card__title tq-pastel__title"><?php echo t('حصص بالطلب'); ?></h2>
                <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('video', 24); ?></span>
            </div>
            <p class="tq-pastel__body"><?php echo t('احجز حصة مباشرة مع معلم عند حاجتك لفهم أعمق.'); ?></p>
            <a class="tq-btn tq-btn--mastery tq-btn--block" href="<?php echo base_url('student/on-demand'); ?>"><?php echo t('احجز الآن'); ?></a>
        </section>

    </aside>
</div>

<?php include 'portal_close.php'; ?>
