<?php
/**
 * صفحة معاينة الدرس المجاني — للزائر بلا حساب.
 *
 * القرار كله وقع في `Preview.php`: ما وصل إلى هنا درس مجاني منشور،
 * وليس اختبارا. فالعرض يعرض ولا يحرس.
 */
$tq_l = isset($tq_lesson) ? $tq_lesson : array();
$tq_c = isset($tq_course) ? $tq_course : array();
$tq_p = isset($tq_plan)   ? $tq_plan   : null;

$tq_h1 = (string) ($tq_l['title'] ?? 'معاينة');

/* TQ-PREVIEW-CAP — حد المعاينة صف في `settings` لا رقم في قالب، فتغيير
   السياسة لا يحتاج نشر شيفرة. وصفر أو غياب = المعاينة كاملة كما كانت،
   فالصفحة لا تنكسر إن حذف الصف. */
$tq_cap     = (int) get_settings('tq_preview_seconds');
$tq_cap_min = $tq_cap > 0 ? (int) round($tq_cap / 60) : 0;

/* والنص يقول ما يحدث فعلا. كان «شاهده كاملا» وهو صادق يوم كتب، فلما
   صار الدرس يقف عند الحد وجب أن يتغير معه — وإلا صار وعدا يكتشف
   الزائر كذبه عند الدقيقة الخامسة، وذلك أسوأ من ألا يوعد أصلا. */
$tq_lead = $tq_cap > 0
    ? 'درس مجاني من ' . (string) ($tq_c['title'] ?? 'المنصة')
      . ' — أول ' . $tq_cap_min . ' دقائق منه مفتوحة بلا اشتراك.'
    : 'درس مجاني من ' . (string) ($tq_c['title'] ?? 'المنصة') . ' — شاهده كاملا قبل أن تشترك.';

include __DIR__ . '/site/site_pagehero.php';

$tq_embed = tqs_video_embed($tq_l['video_type'] ?? '', $tq_l['video_url'] ?? '', $tq_h1);
$tq_dur   = tqs_dur($tq_l['duration'] ?? '');
?>
<section class="section">
  <div class="shell shell--read">

    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url(); ?>">الرئيسية</a> ›
      <a href="<?php echo base_url('catalog'); ?>">المواد والبرامج</a> ›
      <span aria-current="page"><?php echo html_escape($tq_h1); ?></span>
    </nav>

    <div class="icard preview-card">
      <div class="preview-player" data-tq-preview-cap="<?php echo (int) $tq_cap; ?>">
        <div data-tq-preview-player>
          <?php if ($tq_embed !== ''): ?>
            <?php echo $tq_embed; ?>
          <?php else: ?>
            <div class="tq-empty">
              <p class="dir-empty">لا يوجد مقطع لهذا الدرس بعد.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php
      /* بطاقة تحل محل المشغل عند الحد — لا طبقة شفافة فوقه: الطبقة
         تبقي الصوت يعمل خلفها، وتترك المؤشر يسحب من تحتها. والبطاقة
         تطبع هنا مخفية لا يبنيها السكربت، فنصها يترجم بـ`t()` ويرثه
         من لا جافاسكربت عنده معطلا لا مكسورا. */
      if ($tq_cap > 0 && $tq_embed !== ''):
      ?>
        <div class="tq-empty preview-wall" data-tq-preview-wall hidden>
          <p class="tq-empty__title"><?php echo t('انتهت المعاينة'); ?></p>
          <p class="dir-empty">
            <?php echo t('شاهدت أول'); ?> <?php echo (int) $tq_cap_min; ?> <?php echo t('دقائق من الدرس. وبقيته — وبقية المنهج مادة مادة — تفتح بالاشتراك.'); ?>
          </p>
          <div class="cta__actions">
            <?php if ($tq_p): ?>
              <a class="btn btn--primary" href="<?php echo base_url('plan/' . rawurlencode((string) $tq_p['code'])); ?>">
                <?php echo t('اشترك الآن'); ?>
              </a>
            <?php else: ?>
              <a class="btn btn--primary" href="<?php echo base_url('plans'); ?>"><?php echo t('اشترك الآن'); ?></a>
            <?php endif; ?>
            <button class="btn btn--ghost" type="button" data-tq-preview-again>
              <?php echo t('أعد المعاينة من البداية'); ?>
            </button>
          </div>
        </div>
      <?php endif; ?>

      <div class="preview-meta">
        <span class="post-tag"><svg aria-hidden="true"><use href="#i-unlock"></use></svg> معاينة مجانية</span>
        <?php if ($tq_dur !== ''): ?>
          <span class="preview-meta__dur">
            <svg aria-hidden="true"><use href="#i-clock"></use></svg>
            <span class="tq-ltr"><?php echo html_escape($tq_dur); ?></span>
          </span>
        <?php endif; ?>
        <span class="preview-meta__course">
          <svg aria-hidden="true"><use href="#i-book"></use></svg>
          <?php echo html_escape($tq_c['title'] ?? ''); ?>
        </span>
      </div>

      <?php
      /* الملخص يكتب في اللوحة بمحرر غني، فيخرج وسمه كما هو — و`strip_tags`
         بقائمة بيضاء ضيقة تبقي الفقرة والقائمة وتسقط ما سواهما. */
      $tq_sum = trim((string) ($tq_l['summary'] ?? ''));
      if ($tq_sum !== ''):
      ?>
        <div class="preview-summary prose">
          <?php echo strip_tags($tq_sum, '<p><br><ul><ol><li><strong><b><em><i><h3><h4>'); ?>
        </div>
      <?php endif; ?>
    </div>

    <?php /* ── ما بعد المشاهدة ─────────────────────────────────────────
             من شاهد ووجد ما يبحث عنه لا يترك في صفحة بلا مخرج: باقة
             هذا الدرس نفسه أولا، وإلا فالكتالوج. */ ?>
    <div class="icard preview-next">
      <h2>أعجبك الدرس؟</h2>
      <?php if ($tq_p): ?>
        <p>هذا الدرس ضمن باقة <strong><?php echo html_escape($tq_p['title']); ?></strong> —
           وفيها بقية المنهج مادة مادة.</p>
        <div class="cta__actions">
          <a class="btn btn--primary" href="<?php echo base_url('plan/' . rawurlencode((string) $tq_p['code'])); ?>">
            اطلع على الباقة
            <svg aria-hidden="true" style="width:16px;height:16px"><use href="#i-arrow"></use></svg>
          </a>
          <a class="btn btn--ghost" href="<?php echo base_url('catalog'); ?>">تصفح كل البرامج</a>
        </div>
      <?php else: ?>
        <p>تصفح باقي المواد والبرامج، وفي كل برنامج دروس مفتوحة للمعاينة مثل هذا.</p>
        <div class="cta__actions">
          <a class="btn btn--primary" href="<?php echo base_url('catalog'); ?>">تصفح المواد والبرامج</a>
          <a class="btn btn--ghost" href="<?php echo base_url('plans'); ?>">الباقات</a>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>
