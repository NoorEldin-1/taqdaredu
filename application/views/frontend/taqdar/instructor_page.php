<?php
/**
 * ملفّ المعلّم.
 *
 * كارت المعلّم كان يبدو قابلًا للنقر ولا يُنقر: النموذج يبني `url` ولا
 * أحد يستعمله، ولا برنامج ولا عرض. فالرابط صار حيًّا وهذه وجهته.
 *
 * والتقييم يُعرض **فقط إن وُجد**: منصّةٌ جديدة تُظهر «٠٫٠» فتبدو رديئة
 * لا جديدة.
 */
$tq_t  = isset($tq_teacher) ? $tq_teacher : array();
$tq_ci = &get_instance();

$tq_h1   = $tq_t['name'] ?? 'معلّم';
$tq_lead = trim((string) ($tq_t['bio'] ?? ''));
include __DIR__ . '/site/site_pagehero.php';
?>
<section class="section">
  <div class="shell tq-cols-2">
    <div class="path-main">
      <div class="icard">
        <h2>نبذة</h2>
        <p><?php echo html_escape($tq_t['bio'] ?? '') ?: 'لم تُكتب نبذة هذا المعلّم بعد.'; ?></p>

        <?php if (!empty($tq_t['chips'])): ?>
          <div class="chips">
            <?php foreach ((array) $tq_t['chips'] as $tq_c): ?>
              <span class="post-tag"><?php echo html_escape($tq_c); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php
      /* برامجه: من `paths.teacher_id` — لا من عدد مكتوب في عمود. */
      $tq_paths = $tq_ci->db->select('title, slug, short_description, price')
                            ->from('paths')->where('status', 'published')
                            ->where('teacher_id', (int) ($tq_t['id'] ?? 0))
                            ->order_by('tq_order', 'ASC')->get()->result_array();
      ?>
      <?php if ($tq_paths): ?>
        <div class="icard">
          <h2>برامجه</h2>
          <ul class="path-syllabus">
            <?php foreach ($tq_paths as $tq_p): ?>
              <li>
                <svg aria-hidden="true"><use href="#i-cap"></use></svg>
                <a href="<?php echo base_url('path/' . ($tq_p['slug'] ?: '')); ?>"><?php echo html_escape($tq_p['title']); ?></a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>

    <aside>
      <div class="icard icard--sticky">
        <?php if (!empty($tq_t['img'])): ?>
          <img src="<?php echo tqs_img($tq_t['img'], 'teacher-1'); ?>" alt=""
               width="360" height="360" style="width:100%;height:auto;border-radius:14px;display:block;margin-block-end:12px">
        <?php endif; ?>

        <?php if ((float) ($tq_t['rating'] ?? 0) > 0): ?>
          <p class="path-price">
            <span class="tq-ltr"><?php echo html_escape(number_format((float) $tq_t['rating'], 1)); ?></span>
            <svg aria-hidden="true" style="width:20px;height:20px"><use href="#i-star"></use></svg>
          </p>
          <p class="tq-caption"><span class="tq-ltr"><?php echo html_escape(number_format((int) $tq_t['reviews'])); ?></span> تقييمًا</p>
        <?php endif; ?>

        <?php if (!empty($tq_t['stage'])): ?>
          <p class="tq-caption">المرحلة: <?php echo html_escape($tq_t['stage']); ?></p>
        <?php endif; ?>

        <a class="path-back" href="<?php echo base_url('teachers'); ?>">عودة إلى المعلمين</a>
      </div>
    </aside>
  </div>
</section>
