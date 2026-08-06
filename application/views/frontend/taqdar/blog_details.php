<?php
/**
 * صفحة المقال.
 *
 * عنوان التبويب كان «تفاصيل المدونة» **لكل مقال** — ضرر أرشيفي على كل
 * رابط مشارك. ويصلح في المتحكم لا هنا، فالعنوان يضبط قبل العرض.
 *
 * والمتن يطبع كما هو بلا `html_escape` — لأن كاتبه الإدارة عبر محرر
 * اللوحة، وتهريبه يظهر وسومه حروفا.
 */
$tq_b   = isset($blog_details) ? $blog_details : array();
$tq_ci  = &get_instance();
$tq_ci->load->model('taqdar_site_model', 'tq_m');

$tq_body = isset($tq_b['description']) ? (string) $tq_b['description'] : '';
$tq_cat  = '';
if (!empty($tq_b['blog_category_id'])) {
    $tq_row = $tq_ci->db->select('title')->from('blog_category')
                        ->where('blog_category_id', (int) $tq_b['blog_category_id'])
                        ->get()->row_array();
    $tq_cat = $tq_row ? (string) $tq_row['title'] : '';
}
$tq_rel = $tq_ci->tq_m->posts($tq_cat, 4);
$tq_rel = array_values(array_filter($tq_rel, function ($p) use ($tq_b) {
    return (int) $p['id'] !== (int) ($tq_b['blog_id'] ?? 0);
}));
?>
<section class="page-hero">
  <?php include __DIR__ . '/site/site_lantern.php'; ?>
  <div class="shell">
    <nav class="crumbs" aria-label="برنامج التصفح">
      <a href="<?php echo base_url(); ?>">الرئيسية</a>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('blog'); ?>">المدونة</a>
      <?php if ($tq_cat !== ''): ?>
        <span aria-hidden="true">›</span>
        <span><?php echo html_escape($tq_cat); ?></span>
      <?php endif; ?>
    </nav>
    <h1><?php echo html_escape($tq_b['title'] ?? 'مقال'); ?></h1>
    <?php if (!empty($tq_b['added_date'])): ?>
      <p class="page-hero__lead"><?php echo html_escape(tq_since($tq_b['added_date'])); ?></p>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="shell tq-cols-2">
    <div class="path-main">
      <?php if (!empty($tq_b['thumbnail'])): ?>
        <div class="icard" style="padding:0;overflow:hidden">
          <img src="<?php echo tqs_img($tq_b['thumbnail']); ?>" alt=""
               width="900" height="500" style="width:100%;height:auto;display:block">
        </div>
      <?php endif; ?>

      <div class="icard prose">
        <?php if (trim(strip_tags($tq_body)) !== ''): ?>
          <?php echo htmlspecialchars_decode_($tq_body); ?>
        <?php else: ?>
          <p class="dir-empty">لم يكتب متن هذا المقال بعد.</p>
        <?php endif; ?>
      </div>

      <?php if (!empty($tq_b['keywords'])): ?>
        <div class="icard">
          <h2>وسوم</h2>
          <div class="chips">
            <?php foreach (array_filter(array_map('trim', explode(',', (string) $tq_b['keywords']))) as $tq_k): ?>
              <span class="post-tag"><?php echo html_escape($tq_k); ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <aside>
      <div class="icard icard--sticky">
        <h2>مقالات ذات صلة</h2>
        <?php if ($tq_rel): ?>
          <?php echo tqs_side_reads($tq_rel, 4); ?>
        <?php else: ?>
          <p class="dir-empty">لا مقالات أخرى في هذا التصنيف بعد.</p>
        <?php endif; ?>
        <a class="path-back" href="<?php echo base_url('blog'); ?>">عودة إلى المدونة</a>
      </div>
    </aside>
  </div>
</section>
