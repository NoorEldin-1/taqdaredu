<?php
/**
 * كتب صف بعينه — TQ-BOOK-DRIVE.
 *
 * ═══ ولماذا صفحة والمرشح يفعل ما تفعله ═══
 *
 * `‎/books?grade=16‎` يرشح الشبكة نفسها ويعطي البطاقات نفسها. لكنه لا
 * يفهرس ولا يشارك: العنوان «الكتب» لتسع صفحات، والوصف واحد، والرابط
 * لا يقرأ. ومن يبحث لا يكتب «كتب مرشحة بالصف السادس» — يكتب **«كتاب
 * العلوم سادس ابتدائي»**. فصفحة تحمل هذا الاسم هي ما يجاب به.
 *
 * ═══ ولماذا تقسم بالمادة ═══
 *
 * الصف ثلاثة عشر كتابا او اربعة عشر، وشبكة واحدة بهذا العدد تقرأ
 * قائمة. والطالب لا يفتح صفحة صفه ليتصفح: يفتحها ليجد **مادة بعينها**.
 * فالمادة عنوان يقفز اليه البصر، لا وسم على بطاقة بين ثلاث عشرة.
 *
 * ولا ترقيم: `search()` تقطع اثني عشر، وصف المنهج فوقها — فكانت الصفحة
 * تعرض المنهج ناقصا كتابا وتحيل بقيته الى صفحة ثانية. والصف وحدة تقرأ
 * كاملة (`books_of_grade()`).
 *
 * `$tq_grade` صف القاعدة · `$tq_books` كتبه بعد المرشح · `$tq_kind`
 * النوع المرشح · `$tq_counts` عدد كل نوع في الصف كله.
 */
$tq_g     = $tq_grade;
$tq_gname = trim((string) $tq_g['name_ar']);
$tq_kinds = Taqdar_catalog_model::$KINDS;
$tq_n     = count($tq_books);

/* التقسيم بالمادة، وبترتيب ورودها لا بالابجدية: الادخال يرتب المواد
   على ترتيب المنهج، والابجدية تضع «التربية الفنية» قبل «الرياضيات». */
$tq_by = array();
foreach ($tq_books as $tq_b) {
    $tq_s = trim((string) $tq_b['subject']);
    if ($tq_s === '') $tq_s = t('كتب أخرى');
    if (!isset($tq_by[$tq_s])) $tq_by[$tq_s] = array();
    $tq_by[$tq_s][] = $tq_b;
}

$tq_h1   = t('كتب ') . $tq_gname;
$tq_lead = $tq_n > 0
    ? t('كتب المنهج السعودي المعتمد لـ') . $tq_gname . t('، مرتبة بالمادة — تصفحها هنا أو حملها، مجانا وبلا تسجيل.')
    : t('لا كتب منشورة لهذا الصف بعد. وما يضاف يظهر هنا أول ما ينشر.');
include __DIR__ . '/site/site_pagehero.php';
?>
<section class="section">
  <div class="shell">

    <nav class="crumbs" aria-label="<?php echo te('مسار التصفح'); ?>">
      <a href="<?php echo base_url(); ?>"><?php echo t('الرئيسية'); ?></a>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('books'); ?>"><?php echo t('الكتب'); ?></a>
      <span aria-hidden="true">›</span>
      <span><?php echo html_escape($tq_gname); ?></span>
    </nav>

<?php /* ── مرشح النوع ─────────────────────────────────────────────
         والعدد يقرأ من الصف كله لا من المرشح: «دليل المعلم (٠)» بعد
         الضغط عليه لا يخبر بشيء. وما لا كتاب له لا تكتب رقاقته اصلا. */ ?>
<?php if ($tq_counts && count($tq_counts) > 1): ?>
    <div class="cactive" aria-label="<?php echo te('نوع الكتاب'); ?>">
      <a class="cactive__i<?php echo $tq_kind === '' ? ' is-on' : ''; ?>"
         href="<?php echo base_url('books/' . tqs_grade_slug($tq_g)); ?>">
        <?php echo t('الكل'); ?>
        <span class="tq-ltr"><?php echo tq_num((int) array_sum($tq_counts)); ?></span>
      </a>
<?php   foreach ($tq_kinds as $tq_k => $tq_kl): ?>
<?php     if (empty($tq_counts[$tq_k])) continue; ?>
      <a class="cactive__i<?php echo $tq_kind === $tq_k ? ' is-on' : ''; ?>"
         href="<?php echo base_url('books/' . tqs_grade_slug($tq_g)) . '?kind=' . $tq_k; ?>">
        <?php echo html_escape($tq_kl); ?>
        <span class="tq-ltr"><?php echo tq_num((int) $tq_counts[$tq_k]); ?></span>
      </a>
<?php   endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$tq_books): ?>
    <?php /* حالة فارغة مكتوبة لا شبكة خالية: صفحة بلا سطر يفسر فراغها
             تقرأ عطبا. ومعها باب يخرج منه الزائر بدل ان يرجع. */ ?>
    <div class="cempty">
      <p><?php echo t('لا كتب منشورة لـ'); ?><?php echo html_escape($tq_gname); ?><?php echo t(' حتى الآن.'); ?></p>
      <a class="btn btn--primary" href="<?php echo base_url('books'); ?>"><?php echo t('تصفح كل الكتب'); ?></a>
    </div>
<?php else: ?>

<?php   foreach ($tq_by as $tq_subj => $tq_rows): ?>
    <div class="section-head">
      <h2><?php echo html_escape($tq_subj); ?></h2>
      <p class="tq-caption"><?php echo tq_count_units(count($tq_rows), t('كتاب'), t('كتابان'),
          t('كتابين'), t('كتب'), t('كتابا'), null, 'nom'); ?></p>
    </div>
    <div class="cgrid">
<?php     foreach ($tq_rows as $tq_it) echo tqs_cat_card($tq_it); ?>
    </div>
<?php   endforeach; ?>

<?php endif; ?>

  </div>
</section>

<?php /* ── ما بعد الكتاب ──────────────────────────────────────────
         الكتاب يعرض المادة ولا يشرحها، ومن نزله وقف عند اول سؤال. وهي
         الوصلة نفسها التي في صفحة الكتاب المفردة، وفي موضعها: بعد ان
         يجد الزائر ما جاء له لا قبله. */ ?>
<section class="section section--tint">
  <div class="shell">
    <div class="section-head">
      <h2><?php echo t('وماذا بعد الكتاب؟'); ?></h2>
      <p><?php echo t('الكتاب يعرض المادة، والبرنامج يشرحها ويقيسها: دروس مصورة، واختبار بعد كل درس، وتقرير يقول أين تقف.'); ?></p>
    </div>
    <p>
      <a class="btn btn--primary" href="<?php echo base_url('catalog'); ?>?type=path&amp;grade=<?php
          echo (int) $tq_g['id']; ?>">
        <?php echo t('برامج '); ?><?php echo html_escape($tq_gname); ?>
      </a>
      <a class="path-back" href="<?php echo base_url('books'); ?>"><?php echo t('كل كتب المنصة'); ?></a>
    </p>
  </div>
</section>
