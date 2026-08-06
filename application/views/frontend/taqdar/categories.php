<?php
/**
 * الأقسام.
 *
 * كانت تعد **دورات Academy** بشرط `status='active'` — وفي القاعدة دورة
 * واحدة بلا قسم، فتقول كل قسم «صفر دورة» بينما الكتالوج يعرض خمسة عشر
 * برنامجا. كيانان مختلفان يعدان في صفحتين، لا بيانات غير متزامنة.
 *
 * وروابطها كانت تصدر `?category=` والكتالوج يقرأ `?cat=` — فالضغط على
 * أي قسم يفتح كل المواد. و`tqs_category_links()` تبني الرابط الصحيح.
 */
$tq_h1   = 'الأقسام';
$tq_lead = 'اختر المرحلة أو المجال، فتصل إلى برامجه مباشرة.';
include __DIR__ . '/site/site_pagehero.php';

$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_site_model', 'tq_m');
$tq_cats = $tq_ci->tq_m->categories();
?>
<section class="section">
  <div class="shell">
    <?php if ($tq_cats): ?>
      <div class="stage-picker">
        <?php foreach ($tq_cats as $tq_c): ?>
          <a class="stage-card reveal" href="<?php echo base_url('plans'); ?>?cat=<?php echo html_escape($tq_c['id']); ?>">
            <span class="ico"><svg aria-hidden="true"><use href="#<?php echo html_escape($tq_c['icon']); ?>"></use></svg></span>
            <b><?php echo html_escape($tq_c['label']); ?></b>
            <span><?php
              echo ((int) $tq_c['n'] > 0)
                 ? html_escape($tq_c['n']) . ' ' . (((int) $tq_c['n']) === 1 ? 'برنامج' : (((int) $tq_c['n']) === 2 ? 'برنامجان' : 'برامج'))
                 : html_escape($tq_c['sub']);
            ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="dir-empty">لم تضف أقسام بعد.</p>
    <?php endif; ?>
  </div>
</section>
