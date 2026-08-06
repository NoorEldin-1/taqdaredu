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
                    <span class="plan-switch__price"><?php echo tqs_money($s['price']); ?></span>
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
        array($t['subjects'], 'مادة',    'i-book'),
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
        <?php if ($t['lessons'] > 0): ?>
          <p class="tq-caption">
            <?php echo (int) $t['subjects']; ?> مادة
            <?php if ($t['units'] > 0): ?>· <?php echo (int) $t['units']; ?> وحدة<?php endif; ?>
            <?php if ($t['free'] > 0): ?>
              — ومنها <b><?php echo (int) $t['free']; ?></b> درسا مجانيا تعاينه قبل الاشتراك
            <?php endif; ?>
          </p>
        <?php else: ?>
          <p class="tq-caption">دروس هذه الباقة قيد التجهيز، وتفتح لمشتركيها فور نشرها.</p>
        <?php endif; ?>

        <?php echo tqs_curriculum($b, array('mode' => 'public', 'open' => 1)); ?>
      </div>

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
              <li class="gradelist__i">
                <b><?php echo html_escape($g['name']); ?></b>
                <span><?php echo count($g['subjects']); ?> مادة</span>
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
              $tt_img = '<img src="' . tqs_person_img($tt['image']) . '" alt=""'
                      . ' width="64" height="64" loading="lazy" decoding="async">';
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

        <?php if ((string) $b['image'] !== ''): ?>
          <?php
          /* الصورة تصير مشغلا حين يكون في الباقة درس مفتوح للمعاينة:
             علامة تشغيل وشارة تقول ما هي، ونقرة تفتح الدرس نفسه. وبلا
             درس مجاني تبقى صورة — لا علامة تشغيل تعد بفيديو لا يفتح. */
          $promo = tqs_free_preview($b);
          ?>
          <?php if ($promo): ?>
            <a class="plan-card__media plan-card__media--promo"
               href="<?php echo base_url('student/lesson/' . $promo['course_id'] . '/' . $promo['lesson_id']); ?>"
               aria-label="شاهد معاينة مجانية: <?php echo html_escape($promo['title']); ?>">
              <?php /* لا `lazy` هنا: البطاقة أعلى العمود الجانبي وتظهر مع
                       أول رسم، والتأجيل يجعل مربعا بيجيا يومض تحت علامة
                       التشغيل قبل أن تصل الصورة. */ ?>
              <img src="<?php echo tqs_asset_img($b['image'], 'path-primary'); ?>" alt=""
                   width="880" height="587" decoding="async" fetchpriority="high">
              <span class="plan-promo__scrim" aria-hidden="true"></span>
              <span class="plan-promo__play" aria-hidden="true">
                <svg><use href="#i-play"></use></svg>
              </span>
              <span class="plan-promo__tag">
                <svg aria-hidden="true"><use href="#i-video"></use></svg>
                معاينة مجانية
              </span>
              <span class="plan-promo__cap">
                <b><?php echo html_escape($promo['title']); ?></b>
                <i><?php echo html_escape($promo['subject']); ?><?php
                    echo $promo['duration'] !== '' ? ' · ' . html_escape($promo['duration']) : ''; ?></i>
              </span>
            </a>
          <?php else: ?>
            <div class="plan-card__media">
              <img src="<?php echo tqs_asset_img($b['image'], 'path-primary'); ?>" alt=""
                   width="880" height="587" loading="lazy" decoding="async">
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <p class="plan-card__price">
          <?php echo tqs_money($b['price']); ?>
          <small><?php echo html_escape(tqs_period_label($b['days'])); ?></small>
        </p>

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
          <a class="btn btn--primary btn--block" href="<?php echo base_url('checkout/' . $b['code']); ?>">
            اشترك الآن
          </a>
          <?php if ($uid <= 0): ?>
            <p class="tq-caption plan-card__hint">
              ستسجل الدخول أولا، ثم تعود إلى هذه الصفحة.
            </p>
          <?php endif; ?>
        <?php endif; ?>

        <?php
        /* ما تفتحه الباقة، بالأرقام، بجوار السعر تماما — القرار يتخذ
           هنا، فلا يطلب من العين أن تصعد لتقرأ ثم تنزل لتضغط. */
        $facts = array();
        if ($t['grades']   > 0) $facts[] = array('i-cap',       $t['grades'] . ' صفوف دراسية');
        if ($t['subjects'] > 0) $facts[] = array('i-book',      $t['subjects'] . ' مادة');
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

    <p class="plan-cta__price">
      <?php echo tqs_money($b['price']); ?>
      <small><?php echo html_escape(tqs_period_label($b['days'])); ?></small>
    </p>

    <div class="plan-cta__acts">
      <a class="btn btn--gold" href="<?php echo base_url('checkout/' . $b['code']); ?>">اشترك الآن</a>
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
if ((string) $b['image'] !== '') $ld['image'] = tqs_asset_img($b['image'], 'path-primary');
?>
<script type="application/ld+json"><?php
echo json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?></script>
