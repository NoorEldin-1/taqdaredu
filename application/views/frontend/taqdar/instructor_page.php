<?php
/**
 * ملف المعلم.
 *
 * كارت المعلم كان يبدو قابلا للنقر ولا ينقر: النموذج يبني `url` ولا
 * أحد يستعمله، ولا برنامج ولا عرض. فالرابط صار حيا وهذه وجهته.
 *
 * والتقييم يعرض **فقط إن وجد**: منصة جديدة تظهر «٠٫٠» فتبدو رديئة
 * لا جديدة.
 */
$tq_t  = isset($tq_teacher) ? $tq_teacher : array();
$tq_ci = &get_instance();

$tq_h1   = $tq_t['name'] ?? 'معلم';
$tq_lead = trim((string) ($tq_t['bio'] ?? ''));
include __DIR__ . '/site/site_pagehero.php';
?>
<section class="section">
  <div class="shell tq-cols-2">
    <div class="path-main">
      <div class="icard">
        <h2>التخصص والصفوف</h2>
<?php /* الأربعة وحدها بقرار المالك: الصورة والاسم والتخصص والصفوف.
        ولا معلومة تواصل — والنموذج لا يختار `email` ولا `phone` أصلا. */ ?>
        <?php if (!empty($tq_t['title'])): ?>
          <p class="tq-lead"><?php echo html_escape($tq_t['title']); ?></p>
        <?php endif; ?>
        <?php if (trim((string) ($tq_t['bio'] ?? '')) !== ''): ?>
          <p class="path-facts"><span>
            <svg aria-hidden="true"><use href="#i-cap"></use></svg>
            يدرس: <?php echo html_escape($tq_t['bio']); ?>
          </span></p>
        <?php endif; ?>

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
<?php /* الصورة أو حرف الاسم — دالة واحدة تخدم البطاقة وهذه الصفحة
        و«من يدرس؟»، فلا ثلاث نسخ من القرار نفسه. */ ?>
        <div class="tq-profile-photo"><?php
          echo tqs_person_avatar($tq_t['img'] ?? '', $tq_t['name'] ?? '', 'tq-ini--lg');
        ?></div>


        <?php /* `teacher_stage` مفتاح إنجليزي في القاعدة (`secondary`)،
                 وكان يطبع خاما فيقرأ الزائر «المرحلة: secondary» في صفحة
                 عربية بالكامل. و`tqs_stage_label` هي المترجم القائم. */ ?>
        <?php if (!empty($tq_t['stage'])): ?>
          <p class="tq-caption">المرحلة: <?php echo html_escape(tqs_stage_label($tq_t['stage'])); ?></p>
        <?php endif; ?>

        <a class="path-back" href="<?php echo base_url('teachers'); ?>">عودة إلى المعلمين</a>
      </div>
    </aside>
  </div>
</section>
