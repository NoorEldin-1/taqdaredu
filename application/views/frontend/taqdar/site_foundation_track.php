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

$tq_sar = function ($h) {
    return '<b class="tq-ltr">' . number_format(((int) $h) / 100, 2) . '</b> ' . t('ر.س');
};

/* عدد المواعيد المفتوحة في هذا المسار — رقم يقال لا يقدر. */
$tq_slots = 0;
foreach ($tq_tut as $tq_one) $tq_slots += count($tq_one['slots']);
?>

<section class="section" id="track">
  <div class="shell">

    <div class="co-cols">
      <div class="co-main">

        <?php if ($tq_t['description'] !== ''): ?>
          <div class="section-head">
            <h2><span><?php echo t('عن المسار'); ?></span></h2>
          </div>
          <p class="tq-body"><?php echo html_escape($tq_t['description']); ?></p>
        <?php endif; ?>

        <?php if ($tq_t['outcomes']): ?>
          <div class="section-head" style="margin-block-start:2rem">
            <h2><span><?php echo t('ماذا يتقن بعده'); ?></span></h2>
          </div>
          <ul class="bundle__list">
            <?php foreach ($tq_t['outcomes'] as $tq_o): ?>
              <li><?php echo html_escape($tq_o); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php /* المواعيد المفتوحة فعلا: «متاح» كلمة تقال بمواعيد لا
                 بوعد. ومن لم يجد موعدا يقال له متى تفتح لا يترك يخمن. */ ?>
        <div class="section-head" style="margin-block-start:2rem">
          <h2><span><?php echo t('مواعيد متاحة الآن'); ?></span></h2>
          <p><?php echo t('يفتح المعلمون ساعاتهم أسبوعيا، وهذه المتاحة في الأيام القادمة.'); ?></p>
        </div>

        <?php if (!$tq_tut): ?>
          <p class="dir-empty">
            <?php echo t('لا مواعيد مفتوحة في هذا المسار الآن. المعلمون يفتحون ساعاتهم أسبوعيا، فعد بعد قليل.'); ?>
          </p>
        <?php else: ?>
          <div class="cgrid">
            <?php foreach ($tq_tut as $tq_one): ?>
              <article class="ccard">
                <div class="ccard__body">
                  <h3 class="ccard__title"><?php echo html_escape($tq_one['name']); ?></h3>
                  <?php if ((string) $tq_one['title'] !== ''): ?>
                    <p class="ccard__blurb"><?php echo html_escape($tq_one['title']); ?></p>
                  <?php endif; ?>
                  <ul class="bundle__list">
                    <?php foreach ($tq_one['slots'] as $tq_sl): ?>
                      <li><?php echo html_escape($tq_sl['when_text']); ?></li>
                    <?php endforeach; ?>
                  </ul>
                  <div class="ccard__foot">
                    <?php if ((int) $tq_one['pricing']['price'] > 0): ?>
                      <p class="ccard__price"><?php echo $tq_sar($tq_one['pricing']['price']); ?>
                         <span><?php echo t('للحصة'); ?></span></p>
                    <?php else: ?>
                      <p class="ccard__price ccard__price--free"><?php echo t('مجانية'); ?></p>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($tq_teach): ?>
          <div class="section-head" style="margin-block-start:2rem">
            <h2><span><?php echo t('معلمو المسار'); ?></span></h2>
          </div>
          <div class="cgrid">
            <?php foreach ($tq_teach as $tq_one): ?>
              <article class="ccard">
                <div class="ccard__body">
                  <h3 class="ccard__title"><?php echo html_escape($tq_one['name']); ?></h3>
                  <?php if ($tq_one['title'] !== ''): ?>
                    <p class="ccard__blurb"><?php echo html_escape($tq_one['title']); ?></p>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>

      <aside class="co-side">
        <div class="co-side__plan">
          <h2 class="co-side__h"><?php echo html_escape($tq_t['name']); ?></h2>

          <p class="co-side__total">
            <?php if ((int) $tq_p['price'] > 0): ?>
              <?php echo $tq_sar($tq_p['price']); ?>
              <span><?php echo t('للحصة الواحدة'); ?></span>
            <?php else: ?>
              <?php echo t('مجانية'); ?>
            <?php endif; ?>
          </p>

          <p class="co-side__note">
            <?php echo t('مدة الحصة'); ?> <b class="tq-ltr"><?php echo (int) $tq_cfg['minutes']; ?></b>
            <?php echo t('دقيقة، فردية ومباشرة مع معلمك.'); ?>
            <?php echo t('ولا يخصم شيء إلا بعد أن يؤكد المعلم موعدك.'); ?>
          </p>

          <?php if ($tq_slots > 0): ?>
            <p class="co-side__note">
              <b class="tq-ltr"><?php echo (int) $tq_slots; ?></b>
              <?php echo t('موعدا متاحا في الأيام القادمة.'); ?>
            </p>
          <?php endif; ?>

          <?php /* الحجز في البوابة: الطلب يكتب صفا باسم صاحبه، وزائر بلا
                   حساب لا صف له. وشاشة الدخول تعيده إلى هنا. */ ?>
          <a class="btn btn--gold" href="<?php echo base_url('student/foundation?track=' . (int) $tq_t['id']); ?>">
            <?php echo t('احجز موعدك'); ?>
          </a>

          <p class="co-side__note">
            <?php echo t('التأسيس مستقل عن الباقات: لا يشترط اشتراكا، ولا تفتحه باقة.'); ?>
            <a href="<?php echo base_url('foundation'); ?>"><?php echo t('كل المسارات'); ?></a>
          </p>
        </div>
      </aside>
    </div>

  </div>
</section>
