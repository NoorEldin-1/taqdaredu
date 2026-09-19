<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-FOUNDATION — صفحة مسار تأسيس بعينه: `/foundation/<slug>`.
 *
 * وهي تجيب سؤالين لا واحدا — كما تفعل صفحة الكتاب وصفحة الكورس:
 *
 *   ما هذا المسار؟   وصفه ومخرجاته ومن يدرسه
 *   وكيف أحجز؟       الثمن، والمواعيد المتاحة فعلا، وباب الحجز
 *
 * فالصفحة عمودان: المحتوى في الاول، و**بطاقة الحجز لاصقة** في الثاني —
 * من قرأ المخرجات ثم المواعيد يجد الثمن والزر بجواره اينما وقف. وكانت
 * الصفحة تستعير `co-cols` من شاشة الدفع، وتلك الورقة لا تحمل هنا، فخرجت
 * الاعمدة مبعثرة والمخرجات نصا عاريا.
 *
 * **والمواعيد المعروضة هي المفتوحة فعلا** لا جدول عام: قسم يعد الزائر
 * بمواعيد ثم يجدها كلها محجوزة اسوأ من قسم يقول «لا موعد الان». وهي من
 * `available_teachers()` — الاستعلام نفسه الذي تقرأ منه شاشة الطالب،
 * فلا يعد الزائر بموعد يرده الخادم.
 *
 * **والحجز يمر بالبوابة لا بهذه الصفحة**: الطلب يكتب صفا باسم صاحبه،
 * وزائر بلا حساب لا صف له. فالزر يقود الى `student/foundation`، ومن لا
 * حساب له يمر بشاشة الدخول ثم يعود اليها — وهو المسار نفسه الذي يمر به
 * شراء كتاب او كورس مفرد.
 */

$tq_t     = isset($track) ? $track : null;
if (!$tq_t) return;

$tq_teach = isset($teachers) ? $teachers : array();
$tq_tut   = isset($tutors) ? $tutors : array();
$tq_p     = isset($pricing) ? $pricing : array('price' => 0);
$tq_packs = isset($packs) ? $packs : array();
$tq_ses   = isset($ses) ? $ses : null;

/* TQ-FND-PACK — أرخص حصة في باقات هذا المسار: هي الرقم الذي يقارن به
   من يقرأ «١٢٠ للحصة» في بطاقة الحجز. ورقم يحسب في القالب مرة وفي
   البطاقة مرة يفترقان، فيحسب هنا مرة ويقرأ في الموضعين. */
$tq_best = null;
foreach ($tq_packs as $tq_bid => $tq_bp) {
    if ($tq_best === null || (int) $tq_bp['unit'] < (int) $tq_best['unit']) {
        $tq_best = $tq_bp + array('_id' => $tq_bid);
    }
}
$tq_cfg   = $tq_ses ? $tq_ses->config() : array('minutes' => 60, 'pay_hours' => 12);

$tq_h1   = $tq_t['name'];
$tq_lead = $tq_t['tagline'] !== '' ? $tq_t['tagline'] : $tq_t['description'];
include __DIR__ . '/site/site_pagehero.php';

$tq_book = base_url('student/foundation?track=' . (int) $tq_t['id']);

/* عدد المواعيد المعروضة — رقم يقال لا يقدر، وهو ما في الصفحة نفسها. */
$tq_slots = 0;
foreach ($tq_tut as $tq_one) $tq_slots += count($tq_one['slots']);

/* معلمو المسار الذين لا مواعيد لهم الآن — من له مواعيد ظاهر فوق باسمه،
   وتكراره في قسم ثان يطيل الصفحة بلا خبر.
   و`available_teachers()` ترد قائمة مرقمة من صفر لا مفتاحها المعلم،
   فالمقارنة بالمعرف لا بالمفتاح — وبلاها يظهر من له مواعيد مرة ثانية
   تحت «معلمون آخرون» ومعه «لا مواعيد مفتوحة الآن». */
$tq_busy = array();
foreach ($tq_tut as $tq_one) $tq_busy[(int) $tq_one['id']] = true;
$tq_idle = array_diff_key($tq_teach, $tq_busy);

$tq_price_html = function ($price) {
    return (int) $price > 0 ? tqs_money($price) : '<b>' . t('مجانية') . '</b>';
};
?>

<section class="section fndp-track" id="track">
  <div class="shell">
    <div class="fndp-cols">

      <div class="fndp-main">

        <?php if ($tq_t['description'] !== '' || $tq_t['outcomes']): ?>
        <article class="fndp-panel reveal" aria-labelledby="fndp-about">
          <header class="fndp-panel__head">
            <span class="fndp-panel__ico" aria-hidden="true"><svg><use href="#i-book"></use></svg></span>
            <h2 id="fndp-about"><?php echo t('عن المسار'); ?></h2>
          </header>
          <?php if ($tq_t['description'] !== ''): ?>
            <p class="fndp-lede"><?php echo html_escape($tq_t['description']); ?></p>
          <?php endif; ?>

          <?php if ($tq_t['outcomes']): ?>
            <h3 class="fndp-sub"><?php echo t('ماذا يتقن بعده'); ?></h3>
            <ul class="fndp-checks">
              <?php foreach ($tq_t['outcomes'] as $tq_i => $tq_o): ?>
                <li>
                  <span class="fndp-checks__n" aria-hidden="true"><?php echo (int) ($tq_i + 1); ?></span>
                  <span><?php echo html_escape($tq_o); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </article>
        <?php endif; ?>

        <?php /* ---- TQ-FND-PACK — الباقات -------------------------------
                 وموضعها **قبل المواعيد**: من قرأ وصف المسار يسأل «بكم؟»
                 قبل «متى؟»، ومن رأى ثمن الحصة المفردة وحده يضرب في ست
                 وينصرف. والبطاقة تقول الثلاثة معا — العدد والثمن وثمن
                 الحصة داخلها — لأن الرقم الأخير هو ما يقارن به. */ ?>
        <?php if ($tq_packs): ?>
        <section class="fndp-panel reveal" aria-labelledby="fndp-packs" id="packs">
          <header class="fndp-panel__head">
            <span class="fndp-panel__ico" aria-hidden="true"><svg><use href="#i-price"></use></svg></span>
            <div>
              <h2 id="fndp-packs"><?php echo t('باقات الحصص'); ?></h2>
              <p><?php echo t('تدفع مرة واحدة، وتحجز حصصك متى شئت — مع أي معلم في هذا المسار.'); ?></p>
            </div>
          </header>

          <div class="fndpk" style="--fndpk-cols:<?php echo min(3, count($tq_packs)); ?>">
            <?php foreach ($tq_packs as $tq_pid => $tq_pk): ?>
              <article class="fndpk-card<?php echo !empty($tq_pk['featured']) ? ' fndpk-card--hot' : ''; ?>">
                <?php if (!empty($tq_pk['featured'])): ?>
                  <span class="fndpk-card__flag"><?php echo t('الأكثر طلبا'); ?></span>
                <?php endif; ?>

                <span class="fndpk-card__n" aria-hidden="true">
                  <b><?php echo (int) $tq_pk['sessions']; ?></b>
                  <small><?php echo t('حصة'); ?></small>
                </span>

                <h3 class="fndpk-card__title"><?php echo html_escape($tq_pk['name']); ?></h3>
                <?php if ($tq_pk['tagline'] !== ''): ?>
                  <p class="fndpk-card__tag"><?php echo html_escape($tq_pk['tagline']); ?></p>
                <?php endif; ?>

                <p class="fndpk-card__price">
                  <?php echo tqs_money((int) $tq_pk['price']); ?>
                  <?php if ((int) $tq_pk['save'] > 0): ?>
                    <?php /* السعر المشطوب **مشتق** من سعر الحصة المفردة في
                             المسار، فلا رقمان لحقيقة واحدة يفترقان. */ ?>
                    <del><?php echo number_format((int) $tq_pk['list'] / 100); ?> <?php echo t('ر.س'); ?></del>
                    <span class="fndpk-card__save"><?php echo t('وفر ____٪', array((string) (int) $tq_pk['save_pct'])); ?></span>
                  <?php endif; ?>
                </p>

                <ul class="fndpk-card__list">
                  <li>
                    <svg aria-hidden="true"><use href="#i-check"></use></svg>
                    <span><?php echo t('الحصة فيها'); ?> <b><?php echo tqs_money((int) $tq_pk['unit']); ?></b>
                      <?php if ((int) $tq_pk['single'] > (int) $tq_pk['unit']): ?>
                        <?php echo t('بدل'); ?> <?php echo tqs_money((int) $tq_pk['single']); ?> <?php echo t('مفردة'); ?>
                      <?php endif; ?></span>
                  </li>
                  <li>
                    <svg aria-hidden="true"><use href="#i-calendar"></use></svg>
                    <span><?php echo t('تحجزها متى شئت، مع أي معلم في المسار'); ?></span>
                  </li>
                  <li>
                    <svg aria-hidden="true"><use href="#i-clock"></use></svg>
                    <span><?php echo (int) $tq_pk['days'] > 0
                        ? t('صالحة ') . (int) $tq_pk['days'] . t(' يوما من الدفع')
                        : t('لا تنتهي صلاحيتها'); ?></span>
                  </li>
                </ul>

                <a class="fndpk-card__cta" href="<?php echo base_url('foundation-checkout/' . (int) $tq_pid); ?>">
                  <?php echo t('اشترِ الباقة'); ?>
                  <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg>
                </a>
              </article>
            <?php endforeach; ?>
          </div>

          <?php /* والحد يقال تحت البطاقات: الباقة رصيد لا جدول محجوز. من
                   ظن أنه اشترى مواعيد ينتظر رسالة لا تجيء. */ ?>
          <p class="fndpk-note">
            <svg aria-hidden="true"><use href="#i-bulb"></use></svg>
            <span><?php echo t('الباقة رصيد حصص لا مواعيد محجوزة: تدفع مرة، ثم تحجز بنفسك من بوابتك موعدا بعد موعد. وإن اعتذر المعلم عن موعد عادت الحصة إلى رصيدك.'); ?></span>
          </p>
        </section>
        <?php endif; ?>

        <?php /* المواعيد المفتوحة فعلا: «متاح» كلمة تقال بمواعيد لا
                 بوعد. ومن لم يجد موعدا يقال له متى تفتح لا يترك يخمن. */ ?>
        <section class="fndp-panel reveal" aria-labelledby="fndp-slots">
          <header class="fndp-panel__head">
            <span class="fndp-panel__ico" aria-hidden="true"><svg><use href="#i-calendar"></use></svg></span>
            <div>
              <h2 id="fndp-slots"><?php echo t('مواعيد متاحة الآن'); ?></h2>
              <p><?php echo t('يفتح المعلمون ساعاتهم أسبوعيا، وهذه المتاحة في الأيام القادمة.'); ?></p>
            </div>
          </header>

          <?php if (!$tq_tut): ?>
            <div class="fndp-empty fndp-empty--inline">
              <span class="fndp-empty__ico" aria-hidden="true"><svg><use href="#i-clock"></use></svg></span>
              <p><?php echo t('لا مواعيد مفتوحة في هذا المسار الآن. المعلمون يفتحون ساعاتهم أسبوعيا، فعد بعد قليل.'); ?></p>
            </div>
          <?php else: ?>
            <div class="fndp-tutors">
              <?php foreach ($tq_tut as $tq_one): ?>
                <div class="fndp-tutor">
                  <div class="fndp-tutor__who">
                    <?php echo tqs_person_avatar($tq_one['image'], $tq_one['name'], 'fndp-tutor__img', 96); ?>
                    <div>
                      <h3><?php echo html_escape($tq_one['name']); ?></h3>
                      <?php if ((string) $tq_one['title'] !== ''): ?>
                        <p><?php echo html_escape($tq_one['title']); ?></p>
                      <?php endif; ?>
                    </div>
                    <?php /* الثمن يقال هنا حين يخالف ثمن المسار وحده: بطاقة
                             الحجز تقوله مرة، وتكراره عند كل معلم ضجيج. */ ?>
                    <?php if ((int) $tq_one['pricing']['price'] !== (int) $tq_p['price']): ?>
                      <p class="fndp-tutor__price"><?php echo $tq_price_html($tq_one['pricing']['price']); ?>
                        <small><?php echo t('للحصة'); ?></small></p>
                    <?php endif; ?>
                  </div>
                  <ul class="fndp-slots">
                    <?php foreach ($tq_one['slots'] as $tq_sl):
                        $tq_parts = explode(' · ', (string) $tq_sl['when_text'], 2); ?>
                      <li class="fndp-slot">
                        <span class="fndp-slot__day"><?php echo html_escape($tq_parts[0]); ?></span>
                        <?php if (isset($tq_parts[1])): ?>
                          <b class="fndp-slot__time tq-ltr"><?php echo html_escape($tq_parts[1]); ?></b>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endforeach; ?>
            </div>
            <a class="fndp-panel__link" href="<?php echo $tq_book; ?>">
              <?php echo t('احجز أحد هذه المواعيد'); ?>
              <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg>
            </a>
          <?php endif; ?>
        </section>

        <?php if ($tq_idle): ?>
        <section class="fndp-panel reveal" aria-labelledby="fndp-team">
          <header class="fndp-panel__head">
            <span class="fndp-panel__ico" aria-hidden="true"><svg><use href="#i-users"></use></svg></span>
            <h2 id="fndp-team"><?php echo $tq_tut ? t('معلمون آخرون في المسار') : t('معلمو المسار'); ?></h2>
          </header>
          <ul class="fndp-team">
            <?php foreach ($tq_idle as $tq_one): ?>
              <li>
                <?php echo tqs_person_avatar($tq_one['image'], $tq_one['name'], 'fndp-tutor__img', 96); ?>
                <div>
                  <b><?php echo html_escape($tq_one['name']); ?></b>
                  <span><?php echo $tq_one['title'] !== '' ? html_escape($tq_one['title']) : t('لا مواعيد مفتوحة الآن'); ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
        <?php endif; ?>

        <section class="fndp-panel fndp-panel--flat reveal" aria-labelledby="fndp-how">
          <header class="fndp-panel__head">
            <span class="fndp-panel__ico" aria-hidden="true"><svg><use href="#i-clipboard"></use></svg></span>
            <h2 id="fndp-how"><?php echo t('كيف تحجز؟'); ?></h2>
          </header>
          <?php /* TQ-FND-PACK — وطريقان لا طريق، فالخطوات تختلف بينهما في
                   موضع الدفع وحده: بالباقة يقع قبل الحجز، وبالحصة المفردة
                   بعد تأكيد المعلم. وخطوات واحدة تقول «تدفع خلال ١٢ ساعة»
                   لمن دفع بالفعل تجعله يظن أن عليه دفعا ثانيا. */ ?>
          <?php if ($tq_packs): ?>
            <div class="fndp-ways">
              <div class="fndp-way fndp-way--key">
                <h3><?php echo t('بباقة حصص'); ?></h3>
                <ol class="fndp-mini">
                  <li><b><?php echo t('تشتري الباقة'); ?></b><span><?php echo t('دفعة واحدة، ويفتح رصيدك.'); ?></span></li>
                  <li><b><?php echo t('تحجز موعدا'); ?></b><span><?php echo t('من رصيدك، بلا دفع ولا فاتورة.'); ?></span></li>
                  <li><b><?php echo t('يؤكد المعلم'); ?></b><span><?php echo t('فتثبت الحصة في الحال — وإن اعتذر عادت الحصة إلى رصيدك.'); ?></span></li>
                  <li><b><?php echo t('تدخل الحصة'); ?></b><span><?php echo t('الرابط يفتح في بوابتك قبل الموعد.'); ?></span></li>
                </ol>
              </div>
              <div class="fndp-way">
                <h3><?php echo t('بحصة مفردة'); ?></h3>
                <ol class="fndp-mini">
                  <li><b><?php echo t('تختار موعدا'); ?></b><span><?php echo t('من المواعيد المفتوحة أعلاه.'); ?></span></li>
                  <li><b><?php echo t('يؤكد المعلم'); ?></b><span><?php echo t('ولا يخصم منك شيء قبل التأكيد.'); ?></span></li>
                  <li><b><?php echo t('تدفع'); ?></b><span><?php echo t('خلال ') . (int) $tq_cfg['pay_hours'] . t(' ساعة من التأكيد.'); ?></span></li>
                  <li><b><?php echo t('تدخل الحصة'); ?></b><span><?php echo t('الرابط يفتح في بوابتك قبل الموعد.'); ?></span></li>
                </ol>
              </div>
            </div>
          <?php else: ?>
            <ol class="fndp-mini">
              <li><b><?php echo t('اختر موعدا'); ?></b><span><?php echo t('من المواعيد المفتوحة أعلاه.'); ?></span></li>
              <li><b><?php echo t('يؤكد المعلم'); ?></b><span><?php echo t('ولا يخصم منك شيء قبل التأكيد.'); ?></span></li>
              <li><b><?php echo t('تدفع'); ?></b><span><?php echo t('خلال ') . (int) $tq_cfg['pay_hours'] . t(' ساعة من التأكيد.'); ?></span></li>
              <li><b><?php echo t('تدخل الحصة'); ?></b><span><?php echo t('الرابط يفتح في بوابتك قبل الموعد.'); ?></span></li>
            </ol>
          <?php endif; ?>
        </section>

      </div>

      <aside class="fndp-side">
        <div class="fndp-book">
          <?php echo tqs_foundation_media($tq_t, 'fnd26-card__media fndp-book__media'); ?>
          <h2 class="fndp-book__name"><?php echo html_escape($tq_t['name']); ?></h2>

          <p class="fndp-book__price">
            <?php echo $tq_price_html($tq_p['price']); ?>
            <?php if ((int) $tq_p['price'] > 0): ?><small><?php echo t('للحصة الواحدة'); ?></small><?php endif; ?>
          </p>

          <?php /* TQ-FND-PACK — وأرخص حصة في الباقات تحت سعر المفردة
                   مباشرة: **بفارق الثمن لا بثمنها** — «أو ٣٣ ر.س للحصة
                   في باقة ٦» يقارن ما يقارن، ورقمان متجاوران بلا جسر
                   يجعلان المشتري يوازن ولا يعرف بكم. وهو مبدأ سطر
                   «وبكذا زيادة تفتح المرحلة كلها» في صفحة الكورس. */ ?>
          <?php if ($tq_best && (int) $tq_best['unit'] < (int) $tq_p['price']): ?>
            <a class="fndp-book__pack" href="#packs">
              <b><?php echo t('أو'); ?> <?php echo tqs_money((int) $tq_best['unit']); ?> <?php echo t('للحصة'); ?></b>
              <span><?php echo t('في باقة فيها'); ?> <?php echo (int) $tq_best['sessions']; ?> <?php echo t('حصة'); ?>
                — <?php echo t('وفر ____٪', array((string) (int) $tq_best['save_pct'])); ?></span>
            </a>
          <?php endif; ?>

          <?php echo tqs_foundation_facts($tq_cfg, 'fndp-book__facts'); ?>

          <p class="fndp-book__status<?php echo $tq_slots > 0 ? ' is-open' : ''; ?>">
            <i aria-hidden="true"></i>
            <?php if ($tq_slots > 0): ?>
              <span><?php echo tqs_ar_count($tq_slots, array(
                  t('موعد متاح في الأيام القادمة'), t('موعدان متاحان في الأيام القادمة'),
                  t('مواعيد متاحة في الأيام القادمة'), t('موعدا متاحا في الأيام القادمة'))); ?></span>
            <?php else: ?>
              <span><?php echo t('لا مواعيد مفتوحة الآن'); ?></span>
            <?php endif; ?>
          </p>

          <?php /* الحجز في البوابة: الطلب يكتب صفا باسم صاحبه، وزائر بلا
                   حساب لا صف له. وشاشة الدخول تعيده إلى هنا. */ ?>
          <a class="fndp-book__cta" href="<?php echo $tq_book; ?>">
            <?php echo t('احجز موعدك'); ?>
            <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg>
          </a>

          <p class="fndp-book__note">
            <?php echo t('التأسيس مستقل عن الباقات: لا يشترط اشتراكا، ولا تفتحه باقة.'); ?>
            <a href="<?php echo base_url('foundation'); ?>"><?php echo t('كل المسارات'); ?></a>
          </p>
        </div>
      </aside>

    </div>
  </div>
</section>

<?php echo tqs_foundation_band(array(
    'id'      => 'more-tracks',
    'exclude' => (int) $tq_t['id'],
    'eyebrow' => t('قسم التأسيس'),
    'title'   => t('مسارات تأسيس أخرى'),
    'lede'    => '',
    'facts'   => false,
    'foot'    => false,
)); ?>
