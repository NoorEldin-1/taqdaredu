<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-FOUNDATION — الصفحة العامة لقسم التأسيس.
 *
 * باب ثالث بجوار `/catalog` و`/plans`، وثلاثتها تجيب ثلاثة اسئلة لا
 * سؤالين:
 *
 *   /catalog     ماذا تقدم المنصة؟
 *   /plans       بكم الاشتراك؟
 *   /foundation  **وماذا عمن لا يريد منهج صف اصلا؟**
 *
 * والثالث لا يجيبه اي منهما: الكتالوج يعرض محتوى مسجلا مبوبا بالمرحلة
 * والمادة، والباقات تبيع منهج صف كامل — ومن يريد ان يتعلم القراءة من
 * اولها لا يجد نفسه في ايهما، فيقرأ صفحتين لا تعنيانه وينصرف.
 *
 * **ولماذا صفحة عامة لا شاشة في البوابة وحدها؟** لان من يبحث عن «تأسيس
 * انجليزي» يبحث قبل ان يسجل، وقسم خلف تسجيل الدخول لا يجده احد ولا
 * يفهرس. والثمن يعلن هنا كما يعلن في `/plans`: من يعرف الثمن قبل ان
 * ينشئ حسابا لا ينشئه ثم ينصرف.
 *
 * والاسعار من `pricing_for()` وحدها — الحاسبة نفسها التي تجمد السعر على
 * صف الحصة وقت الطلب. ونسخة ثانية من قواعدها في قالب تعد بسعر وتقبض
 * غيره (TQ-CYCLE-BUY في طبقة اخرى).
 */

$tq_tracks = isset($tracks) ? $tracks : array();
$tq_fnd    = isset($fnd) ? $fnd : null;
$tq_ses    = isset($ses) ? $ses : null;
$tq_cfg    = $tq_ses ? $tq_ses->config() : array('minutes' => 60, 'pay_hours' => 12);

$tq_h1   = tq_text_raw('foundation', 'hero_title', 'قسم التأسيس');
$tq_lead = tq_text_raw('foundation', 'hero_lede',
    'حصص مباشرة فردية مع معلم متخصص، تبدأ من حيث الطالب لا من حيث صفه. '
  . 'تأسيس في اللغة العربية والإنجليزية — بلا منهج صف، وبلا اشتراك: تحجز الساعة وتدفع ثمنها وحدها.');
include __DIR__ . '/site/site_pagehero.php';

$tq_sar = function ($h) {
    return '<b class="tq-ltr">' . number_format(((int) $h) / 100, 2) . '</b> ' . t('ر.س');
};
?>

<section class="section" id="tracks">
  <div class="shell">

<?php if (empty($tq_tracks)): ?>
    <?php /* لا 404 على قسم بلا مسار: القسم قائم، وانما لم يفتح فيه شيء
             بعد — و«لا وجود له» تصرف من جاء من اعلان. */ ?>
    <p class="dir-empty"><?php echo t('لا مسارات تأسيس متاحة الآن. عد بعد قليل، أو تواصل معنا لنخبرك حين تفتح.'); ?></p>
<?php else: ?>

    <div class="section-head">
      <h2><span><?php echo t('اختر مسارك'); ?></span></h2>
      <p><?php echo t('كل مسار حصص فردية مباشرة مع معلم متخصص، ومدة الحصة'); ?>
         <b class="tq-ltr"><?php echo (int) $tq_cfg['minutes']; ?></b> <?php echo t('دقيقة.'); ?></p>
    </div>

    <div class="cgrid">
    <?php foreach ($tq_tracks as $tq_id => $tq_t): ?>
      <?php $tq_p = $tq_fnd ? $tq_fnd->pricing_for((int) $tq_id, 0) : array('price' => 0); ?>
      <article class="ccard">
        <a class="ccard__hit" href="<?php echo base_url('foundation/' . rawurlencode($tq_t['slug'])); ?>">
          <span class="sr-only"><?php echo html_escape($tq_t['name']); ?></span>
        </a>
        <?php if ($tq_t['image'] !== ''): ?>
          <div class="ccard__media">
            <img src="<?php echo html_escape(base_url($tq_t['image'])); ?>"
                 alt="<?php echo html_escape($tq_t['name']); ?>" loading="lazy">
          </div>
        <?php endif; ?>
        <div class="ccard__body">
          <?php if ($tq_t['featured']): ?>
            <span class="ccard__flag"><?php echo t('الأكثر طلبا'); ?></span>
          <?php endif; ?>
          <h3 class="ccard__title"><?php echo html_escape($tq_t['name']); ?></h3>
          <?php if ($tq_t['tagline'] !== ''): ?>
            <p class="ccard__blurb"><?php echo html_escape($tq_t['tagline']); ?></p>
          <?php endif; ?>
          <div class="ccard__foot">
            <?php if ((int) $tq_p['price'] > 0): ?>
              <p class="ccard__price"><?php echo $tq_sar($tq_p['price']); ?> <span><?php echo t('للحصة'); ?></span></p>
            <?php else: ?>
              <p class="ccard__price ccard__price--free"><?php echo t('مجانية'); ?></p>
            <?php endif; ?>
            <span class="ccard__cta"><?php echo t('تفاصيل المسار'); ?></span>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    </div>

<?php endif; ?>

  </div>
</section>

<?php /* «كيف يعمل» تحت البطاقات لا فوقها: من عرف ما يريد لا يقرأ الشرح،
         ومن لم يعرف يقرؤه بعد ان يرى ما يشرح. */ ?>
<section class="section section--tight" id="how">
  <div class="shell">
    <div class="section-head">
      <h2><span><?php echo t('كيف يعمل التأسيس؟'); ?></span></h2>
      <p><?php echo t('خمس خطوات، ولا يخصم منك شيء قبل أن يؤكد المعلم موعدك.'); ?></p>
    </div>

    <?php /* الوسم وسم `.steps` في صفحة أولياء الأمور حرفا بحرف — نمط
             ثان لخطوات مرقمة يشيخ وحده. والخمس لا أربع، فالشبكة تعاد
             هنا وحدها ولا تمس القاعدة: تعديلها في الورقة يبدل صفحة
             أخرى لا علاقة لها. */ ?>
    <div class="steps" style="grid-template-columns:repeat(auto-fit,minmax(9rem,1fr))">
      <?php
      $tq_steps = array(
          array(t('اختر مسارك'), t('عربي أو إنجليزي، والثمن معلن على كل بطاقة.')),
          array(t('اختر معلمك وموعده'), t('المعلمون يفتحون ساعاتهم بأنفسهم، وترى المتاح منها في الأيام القادمة.')),
          array(t('يؤكد المعلم'), t('أو يعتذر — ولا يخصم منك شيء في هذه الخطوة.')),
          array(t('تدفع لتثبيت الموعد'),
                t('بعد التأكيد تصلك الفاتورة، وتدفعها خلال ') . (int) $tq_cfg['pay_hours'] . t(' ساعة.')),
          array(t('تدخل الحصة'), t('رابط اللقاء يفتح في بوابتك قبل الموعد، ويغلق حين تنتهي الحصة.')),
      );
      foreach ($tq_steps as $tq_i => $tq_s): ?>
        <div class="step reveal">
          <span class="step__n"><?php echo (int) ($tq_i + 1); ?></span>
          <h3><?php echo html_escape($tq_s[0]); ?></h3>
          <p><?php echo html_escape($tq_s[1]); ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <?php /* الفرق عن الباقة يقال هنا صراحة: من لم يقل له ذلك يظن التأسيس
             محتوى في باقته فينتظر ان يفتح له بلا حجز، او يشتري باقة
             ليحصل على شيء لا تحويه. */ ?>
    <p class="bundles__foot tq-caption">
      <?php echo t('والتأسيس مستقل عن الباقات والمسارات: لا يشترط اشتراكا، ولا تفتحه باقة — تدفع ثمن الحصة التي حجزتها وحدها. ومن يريد منهج صفه كاملا فموضعه'); ?>
      <a href="<?php echo base_url('plans'); ?>"><?php echo t('الباقات'); ?></a>.
    </p>
  </div>
</section>
