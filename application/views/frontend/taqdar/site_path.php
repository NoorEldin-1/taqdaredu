<?php
/**
 * صفحة تفصيل البرنامج — ما يدرس فيه، مفصلا، وكيف يفتح.
 *
 * ═══ ما كانت عليه ═══
 *
 * سطر وصف، وقائمة مسطحة بثلاثين عنوان درس، وبطاقة جانبية تقول «هذا
 * البرنامج ضمن باقات تقدر» — بلا اسم باقة واحدة. والمشتري هنا ولي أمر
 * يسأل سؤالين: **ماذا سيدرس ابني؟** و**كيف أحصل عليه؟** ولم يكن في
 * الصفحة جواب مكتمل لأي منهما.
 *
 * ═══ ما صارت عليه ═══
 *
 * أربعة أقسام، كل قسم جواب سؤال يسأله الزائر بهذا الترتيب:
 *   ١ · شريط أرقام يقول الحجم في لمحة — وحدات ودروس واختبارات ومدة.
 *   ٢ · المنهج بوحداته، كل وحدة تفتح على دروسها بمددها ونوعها.
 *   ٣ · **الباقات التي تفتحه** ببطاقاتها وأسعارها — لا وعد بلا باب.
 *   ٤ · ما يجاوره في قسمه: برامج وكتب ومسابقات.
 *
 * ═══ الصفر يخفي نفسه ═══
 *
 * كل رقم محسوب من صفوف فعلية في `path_detail()`. وبرنامج لم تستورد
 * دروسه لا يعد بأربعين درسا ولا يعرض منهجا فارغا — يقول «قيد الإعداد»
 * ويقول لماذا. والوعد الذي لا يقابله صف في القاعدة يكتشفه المشتري بعد
 * الدفع لا قبله.
 *
 * ═══ لا سعر هنا ولا شراء ═══
 *
 * وحدة البيع الباقة. وسعر على صفحة برنامج يعرض منتجا لا يباع، ويجعل
 * الزائر يوازن بين خيارين أحدهما وهم.
 */
$tq_p      = $tq_path;
$tq_course = (int) $tq_p['course_id'];
$tq_uid    = (int) $this->session->userdata('user_id');

$tq_d      = isset($tq_detail) ? $tq_detail : array('units' => array(), 'plans' => array(),
                'totals' => array('units' => 0, 'lessons' => 0, 'quizzes' => 0, 'free' => 0, 'minutes' => 0));
$tq_t      = $tq_d['totals'];
$tq_plans  = (array) $tq_d['plans'];
$tq_near   = isset($tq_near) ? (array) $tq_near : array();

/* سبب النقص يقال صراحة: «قيد الإعداد» بلا سبب يجعل الزائر يظن العطب في
   متصفحه، ويجعل المسؤول لا يعرف ما ينقصه. */
$tq_why = '';
if     ($tq_course <= 0)      $tq_why = 'محتوى هذا البرنامج لم يربط به بعد.';
elseif ($tq_t['lessons'] < 1) $tq_why = 'دروس هذا البرنامج قيد التجهيز، وتفتح لمشتركيه فور نشرها.';

/** المدة بالدقائق تقرأ ساعات حين تطول — «٤٨٠ دقيقة» لا تقاس في الذهن. */
$tq_dur = function ($m) {
    $m = (int) $m;
    if ($m < 1)  return '';
    if ($m < 60) return $m . ' دقيقة';
    $h = intdiv($m, 60);
    $r = $m % 60;
    return tq_count_units($h, 'ساعة', 'ساعتان', 'ساعتين', 'ساعات', 'ساعة')
         . ($r > 0 ? ' و' . $r . ' دقيقة' : '');
};
?>
<section class="page-hero page-hero--path">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url(); ?>">الرئيسية</a>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('catalog'); ?>">المواد والبرامج</a>
      <?php if (!empty($tq_p['cat_name'])): ?>
        <span aria-hidden="true">›</span>
        <a href="<?php echo base_url('catalog'); ?>?type=path&amp;cat=<?php echo html_escape($tq_p['cat_slug']); ?>"><?php echo html_escape($tq_p['cat_name']); ?></a>
      <?php endif; ?>
    </nav>
    <h1><?php echo html_escape($tq_p['title']); ?></h1>
    <?php if (!empty($tq_p['short_description'])): ?>
      <p class="page-hero__lead"><?php echo html_escape($tq_p['short_description']); ?></p>
    <?php endif; ?>

    <?php
    /* شريط الأرقام — والصفر لا يظهر بندا فارغا. برنامج قيد الإعداد يعرض
       شريطا خاليا بدل أن يعد كذبا، وهو ما تفعله صفحة الباقة نفسها. */
    echo tqs_stat_strip(array(
        array($tq_t['units'],   'وحدة',    'i-grid'),
        array($tq_t['lessons'], 'درسا',    'i-play'),
        array($tq_t['quizzes'], 'اختبارا', 'i-clipboard'),
        array((int) $tq_p['expected_weeks'], 'أسبوعا', 'i-clock'),
    ), 'plan-stats');
    ?>
  </div>
</section>

<section class="section">
  <div class="shell tq-cols-2">

    <div class="path-main">

      <?php /* ── عن البرنامج ─────────────────────────────────────── */ ?>
      <div class="icard">
        <h2>عن هذا البرنامج</h2>
        <p><?php echo html_escape($tq_p['short_description'] ?: 'سيضاف وصف هذا البرنامج قريبا.'); ?></p>

        <div class="path-facts">
          <?php if (!empty($tq_p['teacher_name'])): ?>
            <span><svg aria-hidden="true"><use href="#i-users"></use></svg><?php echo html_escape($tq_p['teacher_name']); ?></span>
          <?php endif; ?>
          <?php if ((int) $tq_p['expected_weeks'] > 0): ?>
            <span><svg aria-hidden="true"><use href="#i-clock"></use></svg><span class="tq-ltr"><?php echo (int) $tq_p['expected_weeks']; ?></span> أسبوعا</span>
          <?php endif; ?>
          <?php if ($tq_t['minutes'] > 0): ?>
            <span><svg aria-hidden="true"><use href="#i-video"></use></svg><?php echo html_escape($tq_dur($tq_t['minutes'])); ?> من الشرح</span>
          <?php endif; ?>
          <?php if ($tq_t['free'] > 0): ?>
            <span><svg aria-hidden="true"><use href="#i-check"></use></svg><span class="tq-ltr"><?php echo (int) $tq_t['free']; ?></span> درسا للمعاينة</span>
          <?php endif; ?>
        </div>
      </div>

      <?php /* ── المنهج بوحداته ──────────────────────────────────── */ ?>
      <div class="icard">
        <h2 id="curriculum">ماذا يدرس في هذا البرنامج؟</h2>

        <?php if ($tq_why !== ''): ?>
          <p class="tq-caption"><?php echo html_escape($tq_why); ?></p>

        <?php else: ?>
          <p class="tq-caption">
            <?php echo (int) $tq_t['units']; ?> وحدة
            · <?php echo (int) $tq_t['lessons']; ?> درسا
            <?php if ($tq_t['quizzes'] > 0): ?>· <?php echo (int) $tq_t['quizzes']; ?> اختبارا<?php endif; ?>
            <?php if ($tq_t['free'] > 0): ?>
              — ومنها <b><?php echo (int) $tq_t['free']; ?></b> درسا مفتوحة للمعاينة قبل الاشتراك
            <?php endif; ?>
          </p>

          <?php /* البنية هي بنية `tqs_curriculum` حرفا — الأصناف نفسها
                   والترتيب نفسه — فمنهج البرنامج يخرج بمظهر منهج الباقة
                   بلا سطر CSS جديد. والفارق أن `<details>` هنا للوحدة لا
                   للمادة: البرنامج مادة واحدة، فطي مادة واحدة على نفسها
                   طبقة لا تطوي شيئا.

                   و`<details>` لا قائمة بجافاسكربت: تفتح بلا سكربت،
                   ويعرفها قارئ الشاشة، ويغلقها المتصفح نفسه. والأولى
                   مفتوحة — الزائر يريد أن يرى شكل الوحدة قبل أن يقرر. */ ?>
          <div class="curric">
            <?php foreach ($tq_d['units'] as $tq_ui => $tq_u): ?>
              <details class="curric__subj"<?php echo $tq_ui === 0 ? ' open' : ''; ?>>
                <summary class="curric__sum">
                  <span class="curric__name"><?php echo html_escape($tq_u['title']); ?></span>
                  <span class="curric__meta"><?php
                    $tq_meta = array(count($tq_u['lessons']) . ' درسا');
                    if ($tq_u['quizzes'] > 0) $tq_meta[] = $tq_u['quizzes'] . ' اختبارا';
                    if ($tq_u['minutes'] > 0) $tq_meta[] = $tq_dur($tq_u['minutes']);
                    echo html_escape(implode(' · ', $tq_meta));
                  ?></span>
                  <svg class="curric__chev" aria-hidden="true"><use href="#i-chevron"></use></svg>
                </summary>

                <div class="curric__body">
                  <ol class="curric__lessons">
                    <?php foreach ($tq_u['lessons'] as $tq_l): ?>
                      <li class="curric__lesson<?php echo !empty($tq_l['free']) ? ' is-free' : ''; ?>">
                        <svg class="curric__ico" aria-hidden="true"><use href="#<?php echo $tq_l['quiz'] ? 'i-clipboard' : 'i-play'; ?>"></use></svg>
                        <span class="curric__t"><?php echo html_escape($tq_l['title']); ?></span>

                        <?php if ($tq_l['minutes'] > 0): ?>
                          <span class="curric__dur"><span class="tq-ltr"><?php echo (int) $tq_l['minutes']; ?></span> د</span>
                        <?php endif; ?>

                        <?php /* المفتوح رابط والمقفل شارة — كما في منهج
                                 الباقة. والقفل مرئي عمدا: المشتري يمسح
                                 المنهج ليعرف كم فيه مما يدفع مقابله، وقفل
                                 صامت يقرأ عطبا لا سياسة. */ ?>
                        <?php if (!empty($tq_l['free'])): ?>
                          <a class="curric__free" href="<?php echo base_url('student/lesson/' . $tq_course . '/' . (int) $tq_l['id']); ?>">
                            <svg aria-hidden="true"><use href="#i-unlock"></use></svg> معاينة مجانية
                          </a>
                        <?php else: ?>
                          <span class="curric__soonlock">
                            <svg class="curric__lock" aria-hidden="true"><use href="#i-lock"></use></svg>يفتح بالاشتراك
                          </span>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ol>
                </div>
              </details>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php /* ── المعلم ──────────────────────────────────────────── */ ?>
      <?php if (!empty($tq_p['teacher_name'])): ?>
        <div class="icard">
          <h2>من يدرس؟</h2>
          <ul class="tlist">
            <li class="tlist__i">
              <?php
              /* الصورة أو حرف الاسم — الدالّة نفسها في البطاقة والبروفايل. */
              $tq_tl  = tqs_person_avatar($tq_p['teacher_image'], $tq_p['teacher_name'], '', 64)
                      . '<span class="tlist__b"><b>' . html_escape($tq_p['teacher_name']) . '</b>'
                      . '<small>معلم هذا البرنامج</small></span>';
              /* الرابط لمن أعلن ملفه وحده: المعروض علنا اختيار صريح لا أثر
                 جانبي لكون المستخدم معلما — كما في صفحة الباقة. */
              $tq_pub = trim((string) $tq_p['teacher_slug']) !== '' || (int) $tq_p['teacher_id'] > 0;
              ?>
              <?php if (!empty($tq_p['teacher_slug'])): ?>
                <a class="tlist__l" href="<?php echo base_url('instructor/' . (int) $tq_p['teacher_id']); ?>"><?php echo $tq_tl; ?></a>
              <?php else: ?>
                <span class="tlist__l"><?php echo $tq_tl; ?></span>
              <?php endif; ?>
            </li>
          </ul>
        </div>
      <?php endif; ?>
    </div>

    <?php /* ── البطاقة الجانبية ─────────────────────────────────── */ ?>
    <aside class="path-buy">
      <div class="icard icard--sticky">
        <p class="path-inbundle">
          <svg aria-hidden="true"><use href="#i-check"></use></svg>
          هذا البرنامج ضمن باقات تقدر
        </p>
        <p class="tq-caption">اشترك في باقة واحدة وافتح منهج الصف كاملا — لا مادة مادة.</p>

        <?php
        /* أرخص باقة تفتحه: الزر يقود إلى **باقة بعينها** لا إلى قائمة.
           و`plans_for_course()` ترد مرتبة بالسعر صاعدا، فأولها أرخصها —
           وهو أقل ما يلزم لفتح هذا البرنامج، وهو ما يسأل عنه الزائر. */
        $tq_cheap = $tq_plans ? $tq_plans[0] : null;
        ?>
        <?php if ($tq_cheap): ?>
          <p class="path-from">
            <span>يفتح ابتداء من</span>
            <b><?php echo tqs_money((int) $tq_cheap['price']); ?></b>
          </p>
          <a class="btn btn--primary btn--block" href="<?php echo base_url('plan/' . html_escape($tq_cheap['code'])); ?>">
            <?php echo html_escape($tq_cheap['name_ar']); ?>
          </a>
          <a class="btn btn--ghost btn--block" href="<?php echo base_url('plans'); ?>">شاهد الباقات كلها</a>
        <?php else: ?>
          <?php /* لا باقة تفتحه: عطل إداري لا يقال للزائر بلغته، ولا يعرض
                   زرا يقود إلى صفحة لا تجد فيها ما يخصه. */ ?>
          <a class="btn btn--primary btn--block" href="<?php echo base_url('plans'); ?>">شاهد الباقات</a>
        <?php endif; ?>

        <?php if ($tq_uid <= 0): ?>
          <p class="tq-caption">ليس لديك حساب؟ <a href="<?php echo base_url('sign_up'); ?>">أنشئ حسابا مجانا</a></p>
        <?php endif; ?>
        <a class="path-back" href="<?php echo base_url('catalog'); ?>?type=path">عودة إلى كل البرامج</a>
      </div>
    </aside>

  </div>
</section>

<?php /* ── الباقات التي تفتح هذا البرنامج ────────────────────────────
     جواب السؤال الثاني: «كيف أحصل عليه؟». وكانت الصفحة تقول «ضمن باقات
     تقدر» بلا اسم باقة واحدة — وعد بلا باب.

     والقائمة من `plans_for_course()`، وهي **علاقة المنح نفسها** التي
     يقرأها `Taqdar_billing_model::subscription_grants()` عند فتح الدرس.
     فما تعد به هذه الصفحة هو ما يفتحه الاشتراك فعلا، لا وعدا في العرض. */ ?>
<?php if (count($tq_plans) > 0): ?>
<section class="section section--tint">
  <div class="shell">
    <div class="section-head">
      <h2><span>ما الذي يفتح هذا البرنامج؟</span></h2>
      <p>أي من هذه الباقات يفتحه — ومعه بقية منهج الصف.</p>
    </div>
    <div class="cgrid">
      <?php
      /* البطاقة من مولد بطاقات الكتالوج: صف `plans` يحول إلى شكله الموحد
         هنا لا في مولد ثان — بطاقتان لباقة واحدة تفترقان عند أول تعديل. */
      $tq_ci_p = &get_instance();
      $tq_ci_p->load->model('taqdar_catalog_model', 'tq_cat_v');
      $tq_codes = array();
      foreach ($tq_plans as $tq_pl) $tq_codes[(string) $tq_pl['code']] = true;

      $tq_shown = 0;
      foreach ($tq_ci_p->tq_cat_v->all() as $tq_it) {
          if ($tq_it['kind'] !== 'plan') continue;
          if (!isset($tq_it['extra']['code']) || !isset($tq_codes[$tq_it['extra']['code']])) continue;
          echo tqs_cat_card($tq_it);
          if (++$tq_shown >= 3) break;
      }
      ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* ── ما يجاوره ─────────────────────────────────────────────────
     برامج وكتب ومسابقات في مادته أو قسمه. والباقات ليست هنا: لها قسمها
     أعلاه، وهو جواب سؤال آخر — «كيف أحصل عليه؟» لا «وماذا أيضا؟». */ ?>
<?php if ($tq_near): ?>
<section class="section">
  <div class="shell">
    <div class="section-head">
      <h2><span>وماذا أيضا في هذا القسم؟</span></h2>
    </div>
    <div class="cgrid">
<?php foreach ($tq_near as $tq_it) echo tqs_cat_card($tq_it); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
/* وسم الدورة للمحركات — من القاعدة نفسها، وبلا سعر: البرنامج لا يباع
   مفردا، و`Offer` بسعر لا يقابله زر شراء بيان كاذب يرفضه الفاحص. */
$tq_ld = array(
    '@context'    => 'https://schema.org',
    '@type'       => 'Course',
    'name'        => (string) $tq_p['title'],
    'description' => (string) ($tq_p['short_description'] ?: $tq_p['title']),
    'provider'    => array('@type' => 'Organization', 'name' => 'تقدر', 'url' => base_url()),
);
if (!empty($tq_p['teacher_name'])) {
    $tq_ld['instructor'] = array('@type' => 'Person', 'name' => (string) $tq_p['teacher_name']);
}
?>
<script type="application/ld+json"><?php
echo json_encode($tq_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?></script>
