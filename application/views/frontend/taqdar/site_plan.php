<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * صفحة الباقة — ما يشترى، مفصلا، قبل أن يطلب ريال واحد.
 *
 * كان طريق الشراء ينتهي عند بطاقة في الكتالوج: اسم وسعر وست كلمات
 * تسويقية، ثم زر يطلب ثلاثمئة وتسعين ريالا. والمشتري هنا ولي أمر
 * يشتري لابنه سنة كاملة — وهو يسأل سؤالا واحدا: **ماذا سيدرس؟**
 *
 * فالمنهج هو بطل هذه الصفحة لا السعر: مادة مادة، ووحدة وحدة، ودرسا
 * درسا — قبل الدفع لا بعده.
 *
 * وكل رقم هنا مقروء من القاعدة عبر `bundle_by_code()`، و**الصفر يخفي
 * نفسه**: باقة لم تستورد دروسها لا تعد بمئة وعشرين درسا. الوعد الذي
 * لا يقابله صف في القاعدة يكتشفه المشتري بعد الدفع لا قبله.
 *
 * والتقييمات ليست هنا عمدا: لا مراجعة واحدة في القاعدة، و«٤٫٩ من ٥»
 * مكتوبة في العرض رقم مخترع. يعود القسم يوم يوجد ما يعرض.
 */
$b = isset($tq_bundle) ? $tq_bundle : null;
if (!$b) return;

$t     = $b['totals'];
$owns  = !empty($tq_owns);
$uid   = (int) $this->session->userdata('user_id');
$stage = tqs_stage_label($b['stage']);
$tier  = tqs_bundle_tier($b['name']);
?>

<section class="page-hero page-hero--plan">
  <?php include __DIR__ . '/site/site_arch.php'; ?>

  <div class="shell">
    <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url(); ?>">الرئيسية</a> ›
      <a href="<?php echo base_url('plans'); ?>">الباقات</a> ›
      <span aria-current="page"><?php echo html_escape($tier); ?></span>
    </nav>

    <?php if ($stage !== ''): ?>
      <p class="plan-hero__stage"><?php echo html_escape($stage); ?></p>
    <?php endif; ?>

    <?php
    /* العنوان **هو** المبدل.
     *
     * كان الخروج من باقة إلى أختها يمر بالكتالوج ثم بالعودة — ثلاث
     * صفحات لمقارنة رقمين، والمقارنة هي عين ما يفعله المشتري هنا.
     * فصار العنوان نفسه بابا: نفس الخط ونفس الحجم ونفس اللون، وإنما
     * تحته خط منقط وسهم يقول «هذا يفتح».
     *
     * و`<details>` لا قائمة بجافاسكربت: تفتح بلا سكربت، ويعرفها قارئ
     * الشاشة، وتغلق بـEscape من المتصفح نفسه. والسكربت يضيف إغلاق
     * النقر خارجها وحده — تحسين إن عمل، ولا شيء ينكسر إن لم يعمل.
     * والباقة الحالية معلمة `aria-current` لا مخفية: من فتح القائمة
     * يريد أن يعرف أين هو منها.
     */
    $sibs = isset($tq_siblings) ? (array) $tq_siblings : array();
    ?>
    <?php if (count($sibs) > 1): ?>
      <details class="plan-switch" data-tq-switch>
        <summary class="plan-switch__cur">
          <h1><?php echo html_escape($b['name']); ?></h1>
          <span class="plan-switch__chev" aria-hidden="true">
            <svg><use href="#i-chevron"></use></svg>
          </span>
          <span class="sr-only">بدل الباقة</span>
        </summary>

        <div class="plan-switch__menu" role="group" aria-label="باقات أخرى">
          <p class="plan-switch__h">باقات أخرى</p>
          <?php
          /* مجموعة بالمرحلة: «الأساسية» في الابتدائي غير «الأساسية» في
             المتوسط، وقائمة مسطحة تعرض الاسم مرتين بلا فارق يقرأ. */
          $by_stage = array();
          foreach ($sibs as $s) $by_stage[$s['stage']][] = $s;
          foreach ($by_stage as $stage_key => $group):
            $stage_lbl = tqs_stage_label($stage_key);
          ?>
            <?php if ($stage_lbl !== ''): ?>
              <p class="plan-switch__stage"><?php echo html_escape($stage_lbl); ?></p>
            <?php endif; ?>
            <ul class="plan-switch__list">
              <?php foreach ($group as $s): ?>
                <li>
                  <a class="plan-switch__i<?php echo $s['current'] ? ' is-current' : ''; ?>"
                     href="<?php echo base_url('plan/' . $s['code']); ?>"
                     <?php echo $s['current'] ? 'aria-current="page"' : ''; ?>>
                    <span class="plan-switch__tier"><?php echo html_escape($s['tier']); ?></span>
                    <span class="plan-switch__price"><?php
                      /* TQ-CYCLE-BUY — من الدالة الواحدة، لا حسابا هنا:
                         `$s['price']` سنوي خام، وطبعه بجوار بطاقة تقول
                         «٣٩٩ / شهريا» يعطي رقمين لباقة واحدة في شاشة واحدة.
                         والشرط ليس تزيدا: الشهري للسنوية وحدها — وباقة
                         ربع سنوية تضاف غدا كانت ستعرض شهريا لا يشترى. */
                      $s_p = tqs_plan_price($s);
                      if (!empty($s_p['has_alt'])) {
                          echo '<b class="tq-ltr">' . number_format($s_p['month'])
                             . '</b> <span>' . t('ر.س / شهريا') . '</span>';
                      } else {
                          echo '<b class="tq-ltr">' . number_format($s_p['total'])
                             . '</b> <span>' . t('ر.س / ') . html_escape($s_p['unit']) . '</span>';
                      }
                    ?></span>
                    <?php if ($s['current']): ?>
                      <svg class="plan-switch__tick" aria-hidden="true"><use href="#i-check"></use></svg>
                    <?php endif; ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endforeach; ?>

          <a class="plan-switch__all" href="<?php echo base_url('plans'); ?>">
            قارن الباقات كلها
            <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          </a>
        </div>
      </details>
    <?php else: ?>
      <h1><?php echo html_escape($b['name']); ?></h1>
    <?php endif; ?>

    <?php if ($b['note'] !== ''): ?>
      <p class="page-hero__lead"><?php echo html_escape($b['note']); ?></p>
    <?php endif; ?>

    <?php
    /* أربعة أرقام تصف الحجم — وما كان صفرا لا يظهر بندا فارغا.
       والباقة قيد الإعداد تعرض شريطا خاليا بدل أن تعد كذبا. */
    echo tqs_stat_strip(array(
        array($t['subjects'], 'مادة جاهزة', 'i-book'),
        array($t['units'],    'وحدة',     'i-grid'),
        array($t['lessons'],  'درسا',    'i-play'),
        array($t['quizzes'],  'اختبارا', 'i-clipboard'),
    ), 'plan-stats');
    ?>
  </div>
</section>

<section class="section section--tight">
  <div class="shell plan-cols">

    <div class="plan-main">

      <?php /* ── المنهج: أول ما يرى، لأنه أول ما يسأل عنه ────────── */ ?>
      <div class="icard">
        <h2 id="curriculum">ماذا سيدرس ابنك؟</h2>
        <?php /* TQ-CURRIC-TREE — الشجرة تغني عن سطر يلخصها: «٣ مادة · ٣٠
                 وحدة — ومنها ٣ درسا مجانيا» رقم يقرأ قبل ما يصفه، والقارئ
                 يعده بعينه في الشجرة تحته فيجد الفرق. والشجرة نفسها هي
                 الجواب: صف يفتح على مواده، ومادة تفتح على دروسها. */ ?>
        <p class="tq-caption">اشتراك واحد يفتح المرحلة كاملة — افتح الصف لترى مواده، والمادة لترى دروسها.</p>

        <?php echo tqs_curriculum_tree($b, array('mode' => 'public')); ?>
      </div>


      <?php /* ── كتب الباقة — TQ-BOOK ─────────────────────────────────
               الباقة تفتح كتب صفوفها كما تفتح برامجها، وصفحتها كانت
               تعد بالبرامج وحدها: فمن يشتري باقة فيها أربعة كتب لا
               يعرف بها حتى يفتح مكتبته **بعد** الدفع — قيمة دفع ثمنها
               ولم تعرض عليه.
               وموضعها بعد المنهج لا قبله: البرنامج هو ما يشترى، والكتاب
               ما يرافقه. */ ?>
      <?php if (!empty($b['books'])): ?>
        <div class="icard">
          <h2><?php echo t('وكتب مرحلته معها'); ?></h2>
          <p class="tq-caption">
            <?php echo tq_count_units(count($b['books']), 'كتاب', 'كتابان', 'كتابين',
                                      'كتب', 'كتابا', '', 'nom'); ?>
            <?php echo t('تفتح باشتراكه — تقرأ في مكتبته صفحة صفحة داخل المنصة، بلا تحميل ولا تطبيق ثان.'); ?>
          </p>

          <ul class="plan-books">
            <?php foreach ($b['books'] as $bk):
              $bk_tone = in_array((string) $bk['tone'],
                  array('math','arabic','science','islamic','english'), true)
                  ? (string) $bk['tone'] : 'math';
              /* TQ-COVER-BLANK — الغلاف الخالي يحمل اسمه: المادة وجها
                 والصف تحته، كما في مكتبة الطالب حرفا بحرف. */
              $bk_bits = preg_split('/\s+[—–-]\s+/u', (string) $bk['title'], 2);
              $bk_face = trim((string) ($bk['subject'] ?: $bk_bits[0]));
            ?>
              <li class="plan-books__i">
                <a class="plan-books__a" href="<?php echo html_escape($bk['href']); ?>">
                  <span class="bookface bookface--xs" data-tone="<?php echo $bk_tone; ?>">
                    <?php if (trim((string) $bk['cover']) !== ''): ?>
                      <img src="<?php echo html_escape(tqs_img($bk['cover'], 'subj-math')); ?>"
                           alt="" width="60" height="84" loading="lazy" decoding="async">
                    <?php else: ?>
                      <span class="book-card__label"><?php echo html_escape($bk_face); ?></span>
                    <?php endif; ?>
                  </span>
                  <span class="plan-books__t">
                    <b><?php echo html_escape($bk['title']); ?></b>
                    <?php if ((int) $bk['pages'] > 0): ?>
                      <small><span class="tq-ltr"><?php echo (int) $bk['pages']; ?></span> <?php echo t('صفحة'); ?></small>
                    <?php endif; ?>
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php /* ── ماذا تشمل: المزايا من `plans.features` ─────────────── */ ?>
      <?php if ($b['features']): ?>
        <div class="icard">
          <h2>ماذا تشمل هذه الباقة؟</h2>
          <ul class="incl">
            <?php
            /* أيقونة لكل ميزة بالدور: قائمة بصح مكرر ست مرات
               لا تقرأ، والعين تمسحها سطرا واحدا. */
            $icons = array('i-video', 'i-clipboard', 'i-report', 'i-teacher', 'i-support', 'i-certificate');
            foreach ($b['features'] as $i => $f):
              $ic = $icons[$i % count($icons)];
            ?>
              <li class="incl__i">
                <span class="incl__ico"><svg aria-hidden="true"><use href="#<?php echo $ic; ?>"></use></svg></span>
                <span><?php echo html_escape($f); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php /* ── الصفوف التي تفتحها ──────────────────────────────── */ ?>
      <?php if (count($b['grades']) > 1): ?>
        <div class="icard">
          <h2>الصفوف التي تفتحها</h2>
          <p class="tq-caption">اشتراك واحد يفتح المرحلة كاملة — فلا تشترى سنة بعد سنة.</p>
          <ul class="gradelist">
            <?php foreach ($b['grades'] as $g): ?>
              <?php $g_soon = !empty($g['soon']); ?>
              <?php /* TQ-HIDE-SOON — الصف يبقى مسرودا دائما (الباقة تفتحه
                       فعلا، وحذفه ينكر على المشتري حقا اشتراه)، ويسقط عنه
                       الوسم والحد المتقطع ما دام المفتاح مرفوعا. واعادته
                       الى `false` تعيد الوسم كما كان حرفا. */ ?>
              <?php $g_prep = ($g_soon && !TQ_HIDE_SOON); ?>
              <li class="gradelist__i<?php echo $g_prep ? ' is-soon' : ''; ?>">
                <b><?php echo html_escape($g['name']); ?></b>
                <?php if (!$g_soon): ?>
                  <?php /* الجاهز لا المبذور: بعد بذر «قيد الإعداد» صار لكل
                           صف خمس مواد، فصف فيه مادة واحدة بها درس كان يقرأ
                           «٥ مادة». الرقم يعد ما يفتح اليوم. */ ?>
                  <span><?php echo (int) $g['ready_n']; ?> مادة جاهزة</span>
                <?php elseif (!TQ_HIDE_SOON): ?>
                  <span class="gradelist__prep">قيد الإعداد</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php /* ── المعلمون — ويخفى القسم إن لم يسند معلم ─────────── */ ?>
      <?php if ($b['teachers']): ?>
        <div class="icard">
          <h2>من يدرس؟</h2>
          <ul class="tlist">
            <?php foreach ($b['teachers'] as $tt): ?>
              <?php
              /* `users.image` قد يكون اسم أصل أو رفعا أو مسارا — و`tqs_person_img`
                 وحدها تعرف الفرق. كان هنا `base_url($tt['image'])` فينتج
                 `…/teacher-3`: خمسة مربعات مكسورة تحت «من يدرس؟».
                 والاسم يربط بصفحته إن كان معلنا وحسب — انظر `bundle_by_code()`. */
              /* الصورة أو حرف الاسم — الدالّة نفسها في البطاقة والبروفايل. */
              $tt_img = tqs_person_avatar($tt['image'], $tt['name'], '', 64);
              $tt_b   = '<span class="tlist__b"><b>' . html_escape($tt['name']) . '</b>'
                      . '<small>' . (int) $tt['n'] . ' برنامجا في هذه الباقة</small></span>';
              ?>
              <?php /* الصف دائما داخل وعاء واحد — رابطا كان أو لا. ولولا
                       ذلك لاختلف عدد أبناء البطاقة بين معلم معلن وآخر،
                       فاختلف تخطيطها بلا سبب يقرأ في الورقة. */ ?>
              <li class="tlist__i">
                <?php if (!empty($tt['public'])): ?>
                  <a class="tlist__l" href="<?php echo base_url('instructor/' . (int) $tt['id']); ?>">
                <?php else: ?>
                  <span class="tlist__l">
                <?php endif; ?>
                  <?php echo $tt_img . $tt_b; ?>
                <?php echo !empty($tt['public']) ? '</a>' : '</span>'; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php /* ── أسئلة يسألها المشتري فعلا قبل الدفع ──────────────── */ ?>
      <div class="icard">
        <h2>أسئلة قبل الاشتراك</h2>
        <div class="faq2">
          <?php
          $faq = array(
            array('كم يدوم الاشتراك؟',
                  'الاشتراك ' . mb_strtolower(tqs_period_label($b['days']), 'UTF-8')
                  . ' من يوم تفعيله، ولا يجدد تلقائيا — فلا يخصم منك شيء دون علمك.'),
            array('كيف أدفع؟',
                  'بتحويل بنكي إلى حساب المنصة. تصدر لك فاتورة برقم مرجعي، '
                  . 'وتفعل باقتك بعد التحقق من الحوالة.'),
            array('هل يمكن أن أعاين قبل أن أدفع؟',
                  $t['free'] > 0
                    ? 'نعم — ' . (int) $t['free'] . ' درسا في هذه الباقة مفتوحة للمعاينة، تجدها بعلامة «معاينة مجانية» في المنهج أعلاه.'
                    : 'دروس المعاينة قيد التجهيز. وتجد في الباقة المجانية دروسا تجريبية تتصفحها بلا دفع.'),
            array('ماذا لو اشتركت ثم غيرت رأيي؟',
                  'يمكنك إيقاف التجديد متى شئت من صفحة اشتراكك، ويبقى ما دفعت صالحا حتى نهاية مدته.'),
            array('هل يفتح المحتوى كله دفعة واحدة؟',
                  'المنهج مرتب بالتسلسل: يفتح الدرس التالي بعد إتقان الذي قبله. '
                  . 'وهذا يمنع القفز فوق أساس لم يتقن.'),
          );
          foreach ($faq as $q):
          ?>
            <details class="faq2__i">
              <summary><?php echo html_escape($q[0]); ?>
                <svg aria-hidden="true"><use href="#i-chevron"></use></svg></summary>
              <p><?php echo html_escape($q[1]); ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <?php /* ── بطاقة الشراء اللزجة ─────────────────────────────────── */ ?>
    <aside class="plan-buy">
      <div class="icard icard--sticky plan-card">

        <?php /* الغلاف يعرض دائما: `tqs_plan_cover()` ترد صورة بحال —
                 رفع المسؤول، أو المشتقة، أو غلاف المرحلة. وكان الشرط
                 `plans.image !== ''` يترك بطاقة الشراء بلا وجه لباقة
                 لم يرفع لها المسؤول شيئا، بينما بطاقتها في الرئيسية
                 تحمل صورة — الصفحتان للباقة الواحدة. */ ?>
        <?php
        /* TQ-PLAN-PROMO — **الفيديو التعريفي، لا معاينة الدرس.**

           كانت هذه البطاقة تعرض `tqs_free_preview($b)`: أول درس مجاني في
           الباقة. والصنف اسمه `--promo` منذ كتب، فكان الاسم يقول برومو
           والمحتوى يعطي درسا — ومر بريئا يوم لم يكن في المنصة فيديو
           واحد. فلما دخل درس حقيقي انكشف: بطاقة الشراء يعلوها درس واحد
           في مادة واحدة، والباقة مرحلة كاملة بموادها.

           والصحيح مقطع **واحد للمنصة كلها** فوق السعر. ومعاينة الدرس لم
           تفقد: شارة «معاينة مجانية» في المنهج أسفل الصفحة هي موضعها،
           وهناك تقرأ على أنها ما هي.

           والرابط يحرر من اللوحة: «نصوص الصفحات» ← «صفحة الباقة الواحدة».
           والفارغ يعني صورة **بلا علامة تشغيل** — لا زر يعد بما لا يفتح. */
        $tq_promo_url   = trim((string) tq_text_raw('plan_detail', 'promo_video', ''));
        $tq_promo_label = trim((string) tq_text_raw('plan_detail', 'promo_label', 'فيديو تعريفي'));
        if ($tq_promo_label === '') $tq_promo_label = 'فيديو تعريفي';

        /* والنوع يشتق من الرابط نفسه — `tqs_video_embed()` تعرف فيميو
           ويوتيوب وتعد ما سواهما ملفا. فلا حقل ثان يطلب من المحرر أن
           يصنف ما لصقه، ولا رابط يكسر لأن أحدا نسي اختيار نوعه. */
        $tq_promo_embed = $tq_promo_url !== ''
            ? tqs_video_embed('', $tq_promo_url, $tq_promo_label)
            : '';
        ?>
        <?php if ($tq_promo_embed !== ''): ?>
          <a class="plan-card__media plan-card__media--promo"
             href="<?php echo html_escape($tq_promo_url); ?>"
             aria-label="<?php echo html_escape($tq_promo_label); ?>">
            <?php /* لا `lazy` هنا: البطاقة أعلى العمود الجانبي وتظهر مع
                     أول رسم، والتأجيل يجعل مربعا بيجيا يومض تحت علامة
                     التشغيل قبل أن تصل الصورة. */ ?>
            <img src="<?php echo html_escape(tqs_plan_cover($b)); ?>" alt=""
                 width="1200" height="800" decoding="async" fetchpriority="high">
            <span class="plan-promo__scrim" aria-hidden="true"></span>
            <span class="plan-promo__play" aria-hidden="true">
              <svg><use href="#i-play"></use></svg>
            </span>
            <span class="plan-promo__tag">
              <svg aria-hidden="true"><use href="#i-video"></use></svg>
              <?php echo html_escape($tq_promo_label); ?>
            </span>
          </a>
          <?php /* المشغل في `<template>`: محتواه خامل فلا يحمل الإطار قبل
                   النقر، فلا تدفع الصفحة ثمن فيديو قد لا يفتح. والسكربت
                   في `site.js` ينقله مكان الصورة عند النقر. وبلا سكربت
                   يبقى الرابط عاملا — يفتح المقطع كما هو. */ ?>
          <template data-tq-inline-video><?php echo $tq_promo_embed; ?></template>
        <?php else: ?>
          <div class="plan-card__media">
            <img src="<?php echo html_escape(tqs_plan_cover($b)); ?>" alt=""
                 width="1200" height="800" loading="lazy" decoding="async">
          </div>
        <?php endif; ?>

        <?php
        /* **عرض لا فوترة**: القاعدة تحفظ السعر السنويّ، والشهريّ معادله
           المعروض ومعه «تدفع سنويا». والمدفوع لا يتغير بحرف — ولذلك لا
           يمس هذا المبدّل الزر ولا الرابط ولا الخادم. والافتراضيّ شهريّ
           بقرار المالك، وبلا سكربت يبقى الشهريّ ظاهرا وهو المطلوب.
           والباقة غير السنويّة لا معادل شهريّ لها فتبقى بفقرة واحدة. */
        /* TQ-PLAN-CYCLE — الدورة من `period` لا من المدة، والأربع دورات
           كلها. وكان الشرط `days >= 360` وحده: الباقة الشهرية تطبع
           فقرة واحدة بلا `data-cycle`، فمن بدل إلى «سنوي» في صفحة
           الباقات ثم فتح هذه وجد الرقم كما هو ولا مبدل يفسره. */
        $tq_p = tqs_plan_price($b);
        ?>
        <?php /* TQ-CYCLE-CHARGE — المبدل عدسة لا دورة فوترة، فيسمى بذلك:
                 «اعرض السعر: شهريا / الإجمالي». وزران عليهما «شهري»
                 و«سنوي» يقرآن اختيارا للدفع، وهذا المحرك لا يعرف فوترة
                 شهرية (`auto_renew = 0`) — فمن ضغط «شهري» بلغ شاشة
                 التأكيد على السعر السنوي وظن أن اختياره ضاع. */ ?>
        <?php if ($tq_p['has_alt']): ?>
          <p class="plan-cycle__lead" id="tqPlanCycleLead">اعرض السعر</p>
          <div class="plan-cycle" role="group" aria-labelledby="tqPlanCycleLead">
            <button type="button" data-tq-cycle="month" aria-pressed="true">شهريا</button>
            <button type="button" data-tq-cycle="year" aria-pressed="false">الإجمالي<span class="plan-cycle__save">وفر <span class="tq-ltr">20%</span></span></button>
          </div>
        <?php endif; ?>
        <?php if ($tq_p['free']): ?>
          <p class="plan-card__price">
            <b>مجانا</b>
            <small>بلا رسوم — يفتح بالتسجيل وحده.</small>
          </p>
        <?php elseif ($tq_p['has_alt']): ?>
          <?php /* المخصوم تحت الرقم في الحالين — لا حاشية خافتة تحت رقم
                   ضخم: هو المبلغ الذي ستطلبه شاشة التأكيد بعد نقرة. */ ?>
          <p class="plan-card__price" data-cycle="month">
            <b class="tq-ltr"><?php echo number_format($tq_p['month']); ?></b> <span>ر.س / شهريا</span>
            <small class="tq-pay"><?php echo html_escape($tq_p['pay_note']); ?></small>
          </p>
          <p class="plan-card__price" data-cycle="year" hidden>
            <b class="tq-ltr"><?php echo number_format($tq_p['total']); ?></b> <span>ر.س / <?php echo html_escape($tq_p['unit']); ?></span>
            <small class="tq-pay"><?php echo html_escape($tq_p['pay_note']); ?></small>
          </p>
        <?php else: ?>
          <p class="plan-card__price">
            <?php echo tqs_money($b['price']); ?>
            <small><?php echo html_escape(tqs_period_label($b['days'])); ?></small>
          </p>
        <?php endif; ?>


        <?php if ($owns): ?>
          <p class="plan-card__owned">
            <svg aria-hidden="true"><use href="#i-check"></use></svg>
            أنت مشترك في هذه الباقة
          </p>
          <a class="btn btn--primary btn--block" href="<?php echo base_url('student/bundle'); ?>">
            افتح محتواك
          </a>
        <?php elseif ((int) $b['price'] <= 0 && $b['period'] !== 'free'): ?>
          <?php /* باقة لم تسعر: `subscribe()` ترفضها بـ`PLAN_NOT_PRICED`،
                   فزر شراء هنا يقود إلى خطأ لا إلى بيع. */ ?>
          <p class="plan-card__soon">لم تسعر هذه الباقة بعد.</p>
          <a class="btn btn--ghost btn--block" href="<?php echo base_url('contact'); ?>">تواصل معنا</a>
        <?php else: ?>
          <?php /* TQ-CYCLE-BUY — الرابط يحمل الدورة المعروضة، والسكربت
                   يبدلها مع المبدل فوقه. وبلا سكربت يشتري ما يرى. */ ?>
          <a class="btn btn--primary btn--block" data-tq-buy="<?php echo html_escape($b['code']); ?>"
             data-tq-plan="<?php echo html_escape($b['name']); ?>"
             href="<?php echo html_escape(tqs_checkout_url($b)); ?>">
            اشترك الآن
          </a>
          <?php if ($uid <= 0): ?>
            <p class="tq-caption plan-card__hint">
              خطوة واحدة قبل الدفع: تدخل بحسابك أو تنشئه هنا، ثم تكمل الدفع.
            </p>
          <?php endif; ?>
        <?php endif; ?>

        <?php
        /* ما تفتحه الباقة، بالأرقام، بجوار السعر تماما — القرار يتخذ
           هنا، فلا يطلب من العين أن تصعد لتقرأ ثم تنزل لتضغط. */
        $facts = array();
        if ($t['grades']   > 0) $facts[] = array('i-cap',       $t['grades'] . ' صفوف دراسية');
        if ($t['subjects'] > 0) $facts[] = array('i-book',      $t['subjects'] . ' مادة جاهزة');
        if ($t['lessons']  > 0) $facts[] = array('i-play',      $t['lessons'] . ' درسا مصورا');
        if ($t['quizzes']  > 0) $facts[] = array('i-clipboard', $t['quizzes'] . ' اختبارا');
        if ($t['teachers'] > 0) $facts[] = array('i-teacher',   $t['teachers'] . ' معلما');
        if ($facts):
        ?>
          <ul class="plan-card__facts">
            <?php foreach ($facts as $f): ?>
              <li><svg aria-hidden="true"><use href="#<?php echo $f[0]; ?>"></use></svg>
                <?php echo html_escape($f[1]); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <a class="plan-card__back" href="<?php echo base_url('plans'); ?>">
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          قارن بالباقات الأخرى
        </a>
      </div>
    </aside>

  </div>
</section>

<?php /* ── باقات أخرى ────────────────────────────────────────────────
     المبدل في العنوان قائمة أسماء وأسعار: يخدم من **قرر** أن يوازن ففتحه
     بنفسه. وهذه البطاقات لمن لم يقرر — تعرض عليه ما لم يكن يعلم أنه
     موجود: صورته وسطر وصفه ومدته وشارة «الأكثر طلبا». والمرحلة نفسها
     أولا، فمن يقرأ باقة متوسطة يوازن بأختيها لا بباقة ابتدائية لا تخصه.

     وموضعها **قبل** النداء الختامي لا بعده: الصفحة تنتهي بفعل واحد، وشبكة
     خيارات بعد «اشترك الآن» تعيد فتح قرار أغلق للتو.

     والبطاقة من `tqs_cat_card` — مولد بطاقات الكتالوج نفسه. ونسخة ثانية
     منها هنا تفترق عن أختها في `/catalog` عند أول تعديل في السعر أو
     الشارة، فيقرأ الزائر وجهين لشيء واحد. */ ?>
<?php $rel = isset($tq_related) ? (array) $tq_related : array(); ?>
<?php if ($rel): ?>
<section class="section section--tint">
  <div class="shell">
    <div class="section-head">
      <h2><span>باقات أخرى</span></h2>
      <p>قارن قبل أن تقرر — الاشتراك واحد في السنة.</p>
    </div>
    <div class="cgrid">
<?php foreach ($rel as $tq_rp) echo tqs_cat_card($tq_rp); ?>
    </div>
    <p class="plan-rel__all">
      <a class="btn btn--ghost" href="<?php echo base_url('plans'); ?>">
        شاهد الباقات كلها
        <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
      </a>
    </p>
  </div>
</section>
<?php endif; ?>

<?php /* ── نداء ختامي ──────────────────────────────────────────────
     ثلاث علل كانت هنا:
     ١· الزر `btn--primary` — و`--petrol` هو **لون خلفية اللوح نفسه**.
        فالزر يذوب فيها: يقرأ نصا أبيض معلقا لا زرا يضغط. والذهبي هو زر
        الأسطح الغامقة في هذا التصميم (`.cta` في خمس صفحات تفعلها).
     ٢· «ر.س» مرتين: `tqs_money()` تلحق العملة بنفسها، والسطر يلحقها
        ثانية — «٣٩٩ ر.س ر.س».
     ٣· السعر مذاب في جملة. وهو الرقم الذي يقرر، فيرفع إلى شارة تقرأ
        وحدها بلا أن تقرأ الجملة كلها. */ ?>
<?php if (!$owns): ?>
<section class="section plan-cta">
  <div class="shell plan-cta__in on-dark">
    <p class="plan-cta__eyebrow"><?php echo html_escape(tqs_stage_label($b['stage'])); ?></p>
    <h2>ابدأ سنة ابنك من اليوم</h2>
    <p class="plan-cta__lead"><?php echo html_escape($b['name']); ?></p>

    <?php /* يتبع المبدّل الذي في بطاقة الشراء — مبدّلان في صفحة واحدة
             يسألان السؤال نفسه مرتين. و`$tq_p` نفسها التي حسبتها
             البطاقة أعلاه: حسابان للسعر في صفحة واحدة يفترقان عند أول
             تعديل، فيقرأ الزائر رقمين لباقة واحدة. */ ?>
    <?php if ($tq_p['free']): ?>
      <p class="plan-cta__price"><b>مجانا</b><small>بلا رسوم</small></p>
    <?php /* TQ-CYCLE-CHARGE — وهذا الشريط زره يذهب إلى شاشة التأكيد
             مباشرة، فهو آخر رقم تقرأه العين قبل أن يطلب المال. والمخصوم
             فيه بصنف `tq-pay` كما في البطاقة أعلاه، لا حاشية خافتة. */ ?>
    <?php elseif ($tq_p['has_alt']): ?>
      <p class="plan-cta__price" data-cycle="month">
        <b class="tq-ltr"><?php echo number_format($tq_p['month']); ?></b> <span>ر.س / شهريا</span>
        <small class="tq-pay"><?php echo html_escape($tq_p['pay_note']); ?></small>
      </p>
      <p class="plan-cta__price" data-cycle="year" hidden>
        <b class="tq-ltr"><?php echo number_format($tq_p['total']); ?></b> <span>ر.س / <?php echo html_escape($tq_p['unit']); ?></span>
        <small class="tq-pay"><?php echo html_escape($tq_p['pay_note']); ?></small>
      </p>
    <?php else: ?>
      <p class="plan-cta__price">
        <?php echo tqs_money($b['price']); ?>
        <small><?php echo html_escape(tqs_period_label($b['days'])); ?></small>
      </p>
    <?php endif; ?>

    <div class="plan-cta__acts">
      <a class="btn btn--gold" data-tq-buy="<?php echo html_escape($b['code']); ?>"
         data-tq-plan="<?php echo html_escape($b['name']); ?>"
         href="<?php echo html_escape(tqs_checkout_url($b)); ?>">اشترك الآن</a>
      <a class="btn btn--ghost" href="#curriculum">راجع المنهج أولا</a>
    </div>

    <p class="plan-cta__note">
      <svg aria-hidden="true"><use href="#i-shield"></use></svg>
      لا تجديد تلقائي ولا خصم متكرر — تدفع مرة، ويبقى الباب مفتوحا المدة كاملة.
    </p>
  </div>
</section>
<?php endif; ?>

<?php
/* وسم المنتج للمحركات — سعر وعملة وتوفر من القاعدة نفسها.
   `Product` بلا `offers` يرفضه فاحص البيانات المنظمة. */
$ld = array(
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $b['name'],
    'description' => ($b['note'] !== '' ? $b['note'] : $b['name']),
    'brand'       => array('@type' => 'Brand', 'name' => 'تقدر'),
    'offers'      => array(
        '@type'         => 'Offer',
        'price'         => number_format($b['price'] / 100, 2, '.', ''),
        'priceCurrency' => 'SAR',
        'availability'  => 'https://schema.org/InStock',
        'url'           => base_url('plan/' . $b['code']),
    ),
);
/* بيانات الوسم المهيكل تقرأ الغلاف من مصدره الواحد كما تقرؤه
   البطاقة: صورة في جوجل تخالف صورة الصفحة تربك من يقارنهما. */
$ld['image'] = tqs_plan_cover($b);
?>
<script type="application/ld+json"><?php
echo json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?></script>
