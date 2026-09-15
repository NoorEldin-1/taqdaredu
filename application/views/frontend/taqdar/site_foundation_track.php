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
$tq_ses   = isset($ses) ? $ses : null;
$tq_cfg   = $tq_ses ? $tq_ses->config() : array('minutes' => 60, 'pay_hours' => 12);

$tq_h1   = $tq_t['name'];
$tq_lead = $tq_t['tagline'] !== '' ? $tq_t['tagline'] : $tq_t['description'];
include __DIR__ . '/site/site_pagehero.php';

$tq_book = base_url('student/foundation?track=' . (int) $tq_t['id']);

/* عدد المواعيد المعروضة — رقم يقال لا يقدر، وهو ما في الصفحة نفسها. */
$tq_slots = 0;
foreach ($tq_tut as $tq_one) $tq_slots += count($tq_one['slots']);

/* معلمو المسار الذين لا مواعيد لهم الآن — من له مواعيد ظاهر فوق باسمه،
   وتكراره في قسم ثان يطيل الصفحة بلا خبر. */
$tq_idle = array_diff_key($tq_teach, $tq_tut);

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
          <ol class="fndp-mini">
            <li><b><?php echo t('اختر موعدا'); ?></b><span><?php echo t('من المواعيد المفتوحة أعلاه.'); ?></span></li>
            <li><b><?php echo t('يؤكد المعلم'); ?></b><span><?php echo t('ولا يخصم منك شيء قبل التأكيد.'); ?></span></li>
            <li><b><?php echo t('تدفع'); ?></b><span><?php echo t('خلال ') . (int) $tq_cfg['pay_hours'] . t(' ساعة من التأكيد.'); ?></span></li>
            <li><b><?php echo t('تدخل الحصة'); ?></b><span><?php echo t('الرابط يفتح في بوابتك قبل الموعد.'); ?></span></li>
          </ol>
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
