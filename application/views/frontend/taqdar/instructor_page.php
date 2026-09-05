<?php
/**
 * ملف المعلم.
 *
 * TQ-PROFILE — أعيد بناء الصفحة، وثلاث علل كانت فيها:
 *
 * ١ — **التكرار**: البطاقة تعيد ما قاله الهيرو حرفا. الهيرو يكتب
 *     «ثاني متوسط والسادس الابتدائي» تحت الاسم، ثم تقول البطاقة «يدرس:
 *     ثاني متوسط والسادس الابتدائي». والرقاقة تحت العنوان تكتب «لغة
 *     إنجليزية» والعنوان فوقها «معلم اللغة الإنجليزية». فثلاثة أسطر
 *     تقول شيئا واحدا، والصفحة تبدو ممتلئة وهي فارغة.
 *
 * ٢ — **«برامجه» يكرر نفسه**: المعلم قد يدرّس المادة نفسها لصفين، و
 *     `paths.title` واحد فيهما («اللغة الإنجليزية»)، فيقرأ الزائر سطرين
 *     متطابقين ويظنهما خطأ. والصف هو ما يفرق بينهما، وهو في القاعدة ولم
 *     يكن يقرأ. فالاستعلام يضم `grades` ويعرضه.
 *
 * ٣ — **الميزان**: عمودان، أحدهما بطاقتان شبه فارغتين والآخر صورة
 *     يتبعها سطران. فصار العمود الجانبي بطاقة هوية مكتملة (صورة واسم
 *     ومادة ومرحلة وزر رجوع)، والعمود الرئيس شبكة حقائق تقول ما لم
 *     يقله الهيرو، ثم البرامج.
 *
 * والهيرو يحمل **العنوان المهني** لا الصفوف: الصفوف تفصيل يقال في
 * موضعه، والعنوان هو ما يعرّف الرجل في سطر.
 */
$tq_t  = isset($tq_teacher) ? $tq_teacher : array();
$tq_ci = &get_instance();

$tq_h1   = $tq_t['name'] ?? 'معلم';
$tq_lead = trim((string) ($tq_t['title'] ?? ''));
include __DIR__ . '/site/site_pagehero.php';

/* برامجه: من `paths.teacher_id` — لا من عدد مكتوب في عمود.
   و`grades` تضم لأن العنوان وحده لا يفرق بين مسارين لمادة واحدة. */
$tq_paths = $tq_ci->db->select('p.title, p.slug, p.short_description, g.name_ar AS grade_name', false)
                      ->from('paths p')
                      ->join('grades g', 'g.id = p.grade_id', 'left')
                      ->where('p.status', 'published')
                      ->where('p.teacher_id', (int) ($tq_t['id'] ?? 0))
                      ->order_by('g.order', 'ASC')->order_by('p.tq_order', 'ASC')
                      ->get()->result_array();

/* الحقائق تبنى مرة ثم تعرض: بطاقة بحقيقتين لا تستحق شبكة، وبأربع
   تستحقها — والقرار يقرأ من العدد لا من الظن. */
$tq_facts = array();
if (!empty($tq_t['title']))  $tq_facts[] = array('i' => 'i-cap',   'k' => 'التخصص',  'v' => $tq_t['title']);
if (!empty($tq_t['stage']))  $tq_facts[] = array('i' => 'i-check', 'k' => 'المرحلة',  'v' => tqs_stage_label($tq_t['stage']));
if (trim((string) ($tq_t['bio'] ?? '')) !== '')
                             $tq_facts[] = array('i' => 'i-list',  'k' => 'الصفوف',   'v' => $tq_t['bio']);
/* العدد العربي يتصرف: «برنامجان» لا «2 برنامجا». و`tq_count_units`
   هي الدالة القائمة لذلك — تستعملها شاشة التشخيص نفسها. */
if ($tq_paths)               $tq_facts[] = array('i' => 'i-book',  'k' => 'البرامج',
    'v' => tq_count_units(count($tq_paths), t('برنامج'), t('برنامجان'), t('برنامجين'), t('برامج'), t('برنامجا'), null, 'nom'));
?>
<section class="section">
  <div class="shell tq-cols-2 tprof">

    <div class="path-main">
      <?php if ($tq_facts): ?>
      <div class="icard">
        <h2>عن المعلم</h2>
        <?php /* قائمة لا `<dl>`: نموذج محتوى قائمة التعريف لا يقبل
                 `<dt>`/`<dd>` معشّشين على مستويين ولا عنصرا ثالثا
                 بينهما — وأيقونة إلى جوارهما تكسره. والمدقّق يمسك ذلك
                 (`definition-list` و`dlitem`). وهذه حقائق تعرض، لا
                 مصطلحات تعرّف، فالقائمة أصدق وصفا لها. */ ?>
        <ul class="tprof__facts">
          <?php foreach ($tq_facts as $tq_f): ?>
            <li class="tprof__fact">
              <span class="tprof__ico" aria-hidden="true">
                <svg><use href="#<?php echo $tq_f['i']; ?>"></use></svg>
              </span>
              <span class="tprof__fv">
                <span class="tprof__fk"><?php echo html_escape($tq_f['k']); ?></span>
                <span class="tprof__fval"><?php echo html_escape($tq_f['v']); ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ($tq_paths): ?>
        <div class="icard">
          <h2>برامجه</h2>
          <ul class="tprof__progs">
            <?php foreach ($tq_paths as $tq_p): ?>
              <li>
                <a href="<?php echo base_url('path/' . ($tq_p['slug'] ?: '')); ?>">
                  <span class="tprof__ico tprof__ico--sm" aria-hidden="true">
                    <svg><use href="#i-cap"></use></svg>
                  </span>
                  <span class="tprof__pname"><?php echo html_escape($tq_p['title']); ?></span>
                  <?php /* الصف هو ما يفرق مسارين بعنوان واحد — فلا يحذف
                           حين يغيب، بل يسكت السطر عنه وحده. */ ?>
                  <?php if (!empty($tq_p['grade_name'])): ?>
                    <span class="tprof__pgrade"><?php echo html_escape($tq_p['grade_name']); ?></span>
                  <?php endif; ?>
                  <svg class="tprof__go" aria-hidden="true"><use href="#i-arrow-back"></use></svg>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>

    <aside>
      <div class="icard icard--sticky tprof__card">
        <?php /* الصورة أو الظل الرمادي — دالة واحدة تخدم البطاقة وهذه
                 الصفحة و«من يدرس؟»، فلا ثلاث نسخ من القرار نفسه.
                 و`true`: تعرض بـ450px فتلزمها النسخة الأصل. */ ?>
        <div class="tprof__photo"><?php
          echo tqs_person_avatar($tq_t['img'] ?? '', $tq_t['name'] ?? '', 'tq-ini--lg', 360, true);
        ?></div>

        <p class="tprof__name"><?php echo html_escape($tq_t['name'] ?? ''); ?></p>

        <?php if (!empty($tq_t['title'])): ?>
          <p class="tprof__role"><?php echo html_escape($tq_t['title']); ?></p>
        <?php endif; ?>

        <?php if (!empty($tq_t['stage'])): ?>
          <p class="tprof__stage"><?php echo html_escape(tqs_stage_label($tq_t['stage'])); ?></p>
        <?php endif; ?>

        <a class="btn btn--ghost tprof__back" href="<?php echo base_url('teachers'); ?>">
          <svg aria-hidden="true"><use href="#i-arrow-back"></use></svg>
          عودة إلى المعلمين
        </a>
      </div>
    </aside>

  </div>
</section>
