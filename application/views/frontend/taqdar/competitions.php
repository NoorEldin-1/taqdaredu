<?php
/**
 * المسابقات — صفحة كاملة.
 *
 * **نافس برنامج وطني رسمي تديره هيئة تقويم التعليم والتدريب.** ومسابقات
 * تقدر **على نمطه** لا هو، والإيضاح مكتوب في أعلى الصفحة لا مطوي في
 * الشروط: إيهام ارتباط رسمي لا وجود له مخاطرة لا داعي لها، والقيمة
 * للطالب تبقى كاملة بلا استعارة الاسم.
 *
 * وبنيتها بنية بقية الصفحات الداخلية (هيرو بقوس ← أقسام ← نداء ختام)
 * فلا تنفرد بمظهرها. وكل قسم فيها يصف **ما تفعله المنصة فعلا**: لا
 * جائزة توعد ولا رقم يختلق — والأقسام التي تحتاج بيانات تقرؤها من
 * القاعدة نفسها التي تقرؤها البطاقات.
 */
?>
<!--
title: المسابقات — منصة تقدر
desc: تحديات دورية على نمط أسئلة نافس، يقيس بها الطالب مستواه ويقارن تقدمه، بشهادة مشاركة ولوحة صدارة.
active: competitions
header: solid
css: pages
-->
<?php
$tq_ci  = &get_instance();
$tq_uid = (int) $tq_ci->session->userdata('user_id');
$tq_now = date('Y-m-d');

$tq_comps = $tq_ci->db->select('c.*, cat.name AS cat_name,
                                (SELECT COUNT(*) FROM competition_entries e WHERE e.competition_id = c.id) AS entries', false)
                      ->from('competitions c')
                      ->join('category cat', 'cat.id = c.category_id', 'left')
                      ->where_in('c.status', array('open', 'closed', 'done'))
                      ->order_by('c.tq_order', 'ASC')->order_by('c.id', 'DESC')
                      ->get()->result_array();

$tq_mine = array();
if ($tq_uid > 0) {
    foreach ($tq_ci->db->select('competition_id')->from('competition_entries')
                       ->where('user_id', $tq_uid)->get()->result_array() as $tq_e) {
        $tq_mine[(int) $tq_e['competition_id']] = true;
    }
}

/* عدّ المفتوح للآن — يُذكر في الهيرو، ولا يُعرض إن كان صفرا:
   «صفر مسابقة مفتوحة» خبر لا يشجّع، والغياب أصدق من رقم يحبط. */
$tq_open_n = 0;
foreach ($tq_comps as $tq_c) {
    if ($tq_c['status'] === 'open'
        && (empty($tq_c['ends_at']) || $tq_c['ends_at'] >= $tq_now)) $tq_open_n++;
}
?>

<!-- ══════════ الهيرو ══════════ -->
<section class="page-hero">
  <div class="shell">
    <div class="page-hero__grid">
      <span class="lantern lantern--l" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="page-hero__copy reveal">
        <h1><?php echo tq_text('competitions', 'hero_title', 'المسابقات'); ?>
          <span class="page-hero__sub"><?php
            echo tq_text('competitions', 'hero_sub', 'نافس نفسك قبل أن تنافس غيرك'); ?></span>
        </h1>
        <p class="page-hero__lede">
          <?php echo tq_text('competitions', 'hero_lede',
              'تحديات دورية على نمط أسئلة نافس، يقيس بها الطالب مستواه ويقارن تقدمه — '
            . 'باختبار قصير مؤقّت، ونتيجة فورية، وشهادة مشاركة.'); ?>
        </p>
        <div class="page-hero__cta">
          <a class="btn btn--primary" href="#list"><?php
            echo tq_text('competitions', 'hero_cta_1', 'تصفح المسابقات'); ?></a>
<?php if ($tq_uid <= 0): ?>
          <a class="btn btn--ghost" href="<?php echo base_url('sign_up'); ?>"><?php
            echo tq_text('competitions', 'hero_cta_2', 'أنشئ حسابا'); ?></a>
<?php else: ?>
          <a class="btn btn--ghost" href="<?php echo base_url('catalog'); ?>">تصفح البرامج</a>
<?php endif; ?>
        </div>

        <div class="hero-mini">
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-clipboard"></use></svg></span>
            <div><b>على نمط نافس</b><span>أسئلة تشبه ما يقابله الطالب</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-clock"></use></svg></span>
            <div><b>اختبار قصير</b><span>وقت محدّد لكل تحدٍّ</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-certificate"></use></svg></span>
            <div><b>شهادة مشاركة</b><span>لكل من أنهى التحدي</span></div>
          </div>
          <div class="hero-mini__item">
            <span class="ico"><svg aria-hidden="true"><use href="#i-chart"></use></svg></span>
            <div><b>نتيجة تُقاس</b><span>تقارن تقدمك مرة بعد مرة</span></div>
          </div>
        </div>
      </div>

      <div class="page-hero__art reveal">
        <div class="page-hero__arch">
          <div>
            <img src="<?php echo tq_site_asset('img/competitions-hero.webp'); ?>" width="960" height="1440"
                 alt="طالبان سعوديان يخوضان اختبارا مؤقتا على جهاز لوحي في الفصل">
          </div>
<?php include __DIR__ . '/site/site_arch.php'; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ التنويه: علاقتنا بنافس ══════════ -->
<section class="section section--plain">
  <div class="shell">
    <?php if ($tq_f = $tq_ci->session->flashdata('flash_message')): ?>
      <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_f); ?></p>
    <?php endif; ?>
    <?php if ($tq_e2 = $tq_ci->session->flashdata('error_message')): ?>
      <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($tq_e2); ?></p>
    <?php endif; ?>

    <div class="icard comp-note">
      <svg aria-hidden="true"><use href="#i-shield"></use></svg>
      <p>
        مسابقات <strong>تقدر</strong> من إعدادنا و<strong>على نمط أسئلة نافس</strong>،
        وهي <strong>غير مرتبطة بهيئة تقويم التعليم والتدريب</strong> ولا تغني عن
        اختبارات نافس الرسمية.
      </p>
    </div>
  </div>
</section>

<!-- ══════════ المسابقات المتاحة ══════════ -->
<section class="section" id="list">
  <div class="shell">
    <div class="section-head reveal">
      <h2><span>المسابقات المتاحة</span></h2>
<?php /* العدد يُذكر إن وُجد: رقم يقرأه الزائر أصدق من وعد بـ«تحديات
        كثيرة». وصفرٌ لا يُذكر — الغياب أصدق من رقم يحبط. */ ?>
<?php if ($tq_open_n > 0): ?>
      <p><span class="tq-ltr"><?php echo (int) $tq_open_n; ?></span>
         <?php echo ($tq_open_n === 1) ? 'مسابقة مفتوحة للتسجيل الآن' : 'مسابقات مفتوحة للتسجيل الآن'; ?></p>
<?php endif; ?>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>

    <?php if ($tq_comps): ?>
      <?php /* TQ-DIR-FEW: مسابقة واحدة في شبكة رباعية تجلس في ربع
               الصف وتترك ثلاثة أرباعه فراغا. والمرن يتوسط ما وجد. */ ?>
      <div class="cmp-grid">
        <?php foreach ($tq_comps as $tq_c):
          $tq_open  = ($tq_c['status'] === 'open')
                   && (empty($tq_c['ends_at']) || $tq_c['ends_at'] >= $tq_now);
          $tq_in    = isset($tq_mine[(int) $tq_c['id']]);
          $tq_full  = ((int) $tq_c['seats'] > 0 && (int) $tq_c['entries'] >= (int) $tq_c['seats']);
          /* الحال المعروضة من التاريخين، والمنطق أدناه من `status` كما كان:
             العرض يقول للزائر أين هو من الموعد، والزر يقول ما يستطيعه. */
          $tq_st    = tqs_comp_state($tq_c['starts_at'], $tq_c['ends_at']);
        ?>
          <article class="cmp-card reveal">
            <div class="cmp-card__head">
              <?php if (!empty($tq_c['cat_name'])): ?>
                <span class="cmp-card__stage"><?php echo html_escape($tq_c['cat_name']); ?></span>
              <?php endif; ?>
              <span class="cmp-state cmp-state--<?php echo $tq_st['kind']; ?>"><?php
                echo html_escape($tq_st['label']); ?></span>
            </div>

            <div class="cmp-card__body">
            <?php /* العنوان رابط إلى صفحة المسابقة المفردة (`/competition/<slug>`)
                     — وفيها وصفها وشروطها. وكانت البطاقة تعرض سطرا واحدا
                     وزر تسجيل، فيسجل الطالب في مسابقة لم يقرأ عنها شيئا.
                     وبلا `slug` لا رابط: `competition_by_slug` تقبل الرقم
                     أيضا، لكن الرابط الرقمي لا يقرأ ولا يشارك. */ ?>
              <h3>
                <?php if (!empty($tq_c['slug'])): ?>
                  <a href="<?php echo base_url('competition/' . rawurlencode((string) $tq_c['slug'])); ?>">
                    <?php echo html_escape($tq_c['title']); ?>
                  </a>
                <?php else: ?>
                  <?php echo html_escape($tq_c['title']); ?>
                <?php endif; ?>
              </h3>
              <?php if (!empty($tq_c['tagline'])): ?>
                <p><?php echo html_escape($tq_c['tagline']); ?></p>
              <?php endif; ?>

              <div class="cmp-card__facts">
                <?php if (!empty($tq_c['starts_at'])): ?>
                  <span><svg aria-hidden="true"><use href="#i-calendar"></use></svg>
                    تبدأ <b class="tq-ltr"><?php echo html_escape(tqs_date_ar($tq_c['starts_at'])); ?></b></span>
                <?php endif; ?>
                <?php if (!empty($tq_c['ends_at'])): ?>
                  <span><svg aria-hidden="true"><use href="#i-clock"></use></svg>
                    تنتهي <b class="tq-ltr"><?php echo html_escape(tqs_date_ar($tq_c['ends_at'])); ?></b></span>
                <?php endif; ?>
                <?php if ((int) $tq_c['entries'] > 0): ?>
                  <span><svg aria-hidden="true"><use href="#i-users"></use></svg>
                    <b class="tq-ltr"><?php echo (int) $tq_c['entries']; ?></b> مشارك</span>
                <?php endif; ?>
                <?php if (!empty($tq_c['prize'])): ?>
                  <span><svg aria-hidden="true"><use href="#i-badge"></use></svg><?php echo html_escape($tq_c['prize']); ?></span>
                <?php endif; ?>
              </div>
            </div>

            <div class="cmp-card__act">
            <?php if ($tq_in): ?>
              <p class="comp-state comp-state--in">
                <svg aria-hidden="true"><use href="#i-check"></use></svg>أنت مسجل في هذه المسابقة
              </p>
            <?php elseif (!$tq_open): ?>
              <button class="btn btn--primary btn--block" type="button" disabled>
                <?php echo ($tq_c['status'] === 'done') ? 'انتهت' : 'التسجيل مغلق'; ?>
              </button>
            <?php elseif ($tq_full): ?>
              <button class="btn btn--primary btn--block" type="button" disabled>اكتمل العدد</button>
            <?php elseif ($tq_uid > 0): ?>
              <form method="post" action="<?php echo base_url('competitions/join'); ?>">
                <input type="hidden" name="competition_id" value="<?php echo (int) $tq_c['id']; ?>">
                <button class="btn btn--primary btn--block" type="submit">سجل في المسابقة</button>
              </form>
            <?php else: ?>
              <a class="btn btn--primary btn--block" href="<?php echo base_url('login'); ?>">سجل الدخول للمشاركة</a>
              <p class="tq-caption">المشاركة لطلاب المنصة — فالنتائج تقاس والشهادات تنسب.</p>
            <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="dir-empty">لا مسابقات معلنة الآن. تابعنا — نطلق تحديا جديدا كل فترة.</p>
    <?php endif; ?>
  </div>
</section>

<!-- ══════════ كيف تشارك؟ ══════════ -->
<section class="section" id="how">
  <div class="shell">
    <div class="panel">
      <span class="lantern lantern--corner" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <div class="section-head">
        <h2><span>كيف تشارك؟</span></h2>
        <p>أربع خطوات، ولا شيء منها يحتاج أكثر من دقيقة.</p>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>

      <div class="steps">
        <div class="step reveal">
          <span class="step__n">1</span>
          <h3>سجل دخولك</h3>
          <p>المشاركة لطلاب المنصة — فالنتيجة تُقاس والشهادة تُنسب إلى صاحبها.</p>
        </div>
        <div class="step reveal">
          <span class="step__n">2</span>
          <h3>اختر التحدي</h3>
          <p>افتح صفحة المسابقة واقرأ مادتها ومرحلتها وموعدها قبل أن تسجل.</p>
        </div>
        <div class="step reveal">
          <span class="step__n">3</span>
          <h3>نافس في وقته</h3>
          <p>اختبار قصير بوقت محدّد، على نمط الأسئلة التي يقابلها الطالب في نافس.</p>
        </div>
        <div class="step reveal">
          <span class="step__n">4</span>
          <h3>اقرأ نتيجتك</h3>
          <p>نتيجة تُحفظ في حسابك فتقارن بها تقدمك في التحدي التالي.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ ماذا تكسب؟ ══════════ -->
<section class="section" id="gain">
  <div class="shell">
    <div class="section-head reveal">
      <h2><span>ماذا تكسب من المسابقة؟</span></h2>
      <p>قيمتها ليست في الجائزة وحدها — بل في أنك ترى مستواك رقما تقارنه.</p>
      <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
    </div>

    <div class="grid-4">
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-target"></use></svg></span>
        <h3>قياس صادق لمستواك</h3>
        <p>أسئلة على نمط نافس تكشف أين تقف اليوم، لا أين تظن أنك تقف.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-certificate"></use></svg></span>
        <h3>شهادة مشاركة</h3>
        <p>لكل من أنهى التحدي شهادة باسمه، تُحفظ في حسابه على المنصة.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-growth"></use></svg></span>
        <h3>تدريب بلا كلفة</h3>
        <p>المسابقات مفتوحة لطلاب الباقات بلا رسوم إضافية — تدريب على المنافسة.</p>
      </article>
      <article class="icard reveal">
        <span class="ico"><svg aria-hidden="true"><use href="#i-clock"></use></svg></span>
        <h3>إدارة الوقت</h3>
        <p>الاختبار مؤقّت، وهي المهارة التي تفرق في القاعة يوم الاختبار الحقيقي.</p>
      </article>
    </div>
  </div>
</section>

<!-- ══════════ أسئلة شائعة ══════════ -->
<section class="section" id="faq">
  <div class="shell">
    <div class="panel">
      <div class="section-head">
        <h2><span>أسئلة شائعة</span></h2>
        <div class="rule"><svg aria-hidden="true"><use href="#i-star8"></use></svg></div>
      </div>

<?php
/* الأجوبة من واقع المنصة لا من العموم: كل جواب هنا يصف ما يقع فعلا
   عند الضغط على الأزرار في هذه الصفحة نفسها. */
$tq_faq = array(
    array('هل هذه المسابقات هي اختبارات نافس؟',
          'لا. نافس برنامج وطني رسمي تديره هيئة تقويم التعليم والتدريب، ومسابقات تقدر '
        . 'من إعدادنا وعلى نمط أسئلته فقط. لا ترتبط بالهيئة ولا تغني عن اختباراتها.'),
    array('من يستطيع المشاركة؟',
          'طلاب المنصة. تسجيل الدخول شرط للمشاركة لأن النتيجة تُحفظ في حساب الطالب '
        . 'والشهادة تُنسب إلى صاحبها.'),
    array('هل المشاركة برسوم إضافية؟',
          'لا. المسابقات مفتوحة لطلاب الباقات بلا رسوم إضافية.'),
    array('ماذا لو اكتمل العدد أو أُغلق التسجيل؟',
          'يظهر ذلك على بطاقة المسابقة مباشرة، ويبقى زر التسجيل معطلا حتى لا تحجز مقعدا '
        . 'غير متاح. وتُعلن مسابقة جديدة كل فترة.'),
    array('متى تظهر النتيجة؟',
          'بعد انتهاء وقت التحدي، وتُحفظ في حسابك فتقارن بها تقدمك في المسابقة التالية.'),
    array('هل تُشترط مادة أو مرحلة معينة؟',
          'كل مسابقة تعلن مادتها ومرحلتها على بطاقتها وفي صفحتها، فتختار ما يناسب صفك.'),
);
?>
      <div class="faq-grid">
<?php foreach ($tq_faq as $tq_i => $tq_q): ?>
        <div class="faq-item reveal">
          <button class="faq-q" type="button" aria-expanded="false"
                  aria-controls="cmpfaq-a-<?php echo (int) $tq_i; ?>"
                  id="cmpfaq-q-<?php echo (int) $tq_i; ?>">
            <span><?php echo html_escape($tq_q[0]); ?></span>
            <svg aria-hidden="true"><use href="#i-plus"></use></svg>
          </button>
<?php /* الغلاف الداخلي عقد لا زينة: الطي `grid-template-rows:0fr` على
        `.faq-a`، والقص على ابنها المباشر. وبلاه يظهر كل جواب مفتوحا. */ ?>
          <div class="faq-a" id="cmpfaq-a-<?php echo (int) $tq_i; ?>" role="region"
               aria-labelledby="cmpfaq-q-<?php echo (int) $tq_i; ?>"><div><p><?php
            echo html_escape($tq_q[1]);
          ?></p></div></div>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ══════════ نداء الختام ══════════ -->
<section class="section" id="signup">
  <div class="shell">
    <div class="cta">
      <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
      <span class="lantern lantern--l lantern--slow" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>

      <div class="cta__copy">
<?php if ($tq_uid > 0): ?>
        <h2>جاهز للتحدي؟</h2>
        <p>اختر مسابقة مفتوحة وسجل فيها الآن</p>
        <a class="btn btn--gold" href="#list">تصفح المسابقات</a>
<?php else: ?>
        <h2>انضم وشارك في التحدي</h2>
        <p>أنشئ حسابك، واختر مسابقتك، وقس مستواك</p>
        <a class="btn btn--gold" href="<?php echo base_url('sign_up'); ?>">إنشاء حساب مجاني</a>
        <a class="cta__login" href="<?php echo base_url('login'); ?>">أو تسجيل الدخول</a>
<?php endif; ?>
      </div>

      <?php /* العمود الثاني: `.cta` شبكة عمودين (`1.15fr .85fr`) — وكانت هذه
               الصفحة وحدها بلا `.cta__art`، فيبقى نصف البانر فراغا داكنا.
               والصورة نفسها التي في الرئيسية والمدونة والكتالوج وصفحة
               الطلاب، فينظر الزائر إلى بانر واحد لا خمسة. */ ?>
      <div class="cta__art">
        <img src="<?php echo tq_site_asset('img/cta-kids-cut.webp'); ?>" width="660" height="990"
             alt="طفلان سعوديان يبتسمان ويحملان جهازا لوحيا" loading="lazy" decoding="async">
      </div>
    </div>
  </div>
</section>
