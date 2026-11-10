<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * TQ-GROUP — `/groups`: المجموعات المباشرة المنشورة.
 *
 * الشاشة تعرض ولا تحكم: كل بطاقة من `Taqdar_group_model::summary()`، والسعر
 * **بالهللات** كما يخزن فيطبع بـ`tqs_money()` لا بقسمة يد. والمقاعد جملة
 * عربية (`seats_text()`) لا «2 مقاعد».
 */
$tq_rows = isset($tq_groups) ? (array) $tq_groups : array();

$tq_h1   = t('المجموعات المباشرة');
$tq_lead = t('حصص مباشرة مع معلمك ومجموعة صغيرة من زملائك، بجدول معلن قبل أن تنضم. تدفع مرة وتحضر الجدول كله، والتسجيلات تبقى لك.');
include __DIR__ . '/site/site_pagehero.php';
?>

<section class="section" id="groups">
  <div class="shell">
    <?php if (!$tq_rows): ?>
      <div class="fndp-empty">
        <span class="fndp-empty__ico" aria-hidden="true"><svg><use href="#i-calendar"></use></svg></span>
        <p><?php echo t('لا مجموعات مفتوحة الآن. عد بعد قليل، أو تواصل معنا لنخبرك حين تفتح مجموعة جديدة.'); ?></p>
        <a class="btn btn--primary" href="<?php echo base_url('contact'); ?>"><?php echo t('تواصل معنا'); ?></a>
      </div>
    <?php else: ?>
      <div class="fnd26__grid grp26-grid" style="--fnd-cols:<?php echo min(3, count($tq_rows)); ?>">
        <?php foreach ($tq_rows as $g): if (!$g) continue; ?>
          <?php echo tqs_group_card($g); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <aside class="fndp-note">
      <span class="fndp-note__ico" aria-hidden="true"><svg><use href="#i-price"></use></svg></span>
      <div class="fndp-note__txt">
        <h3><?php echo t('المجموعة أم الحصة الفردية؟'); ?></h3>
        <p><?php echo t('المجموعة جدول ثابت مع زملاء بثمن أقل للحصة، والحصة الفردية وقت معلم تحجزه وحدك. ومن يريد منهج صفه كاملا فموضعه الباقات.'); ?></p>
      </div>
      <a class="fndp-note__btn" href="<?php echo base_url('plans'); ?>">
        <?php echo t('قارن الباقات'); ?>
        <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg>
      </a>
    </aside>
  </div>
</section>
