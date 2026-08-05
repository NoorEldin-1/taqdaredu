<?php
/**
 * صفحة تفصيل البرنامج — وبها يكتمل طريق الشراء.
 *
 * كان زرّ «ابدأ البرنامج» في اثنين وعشرين موضعًا يقود إلى `#`: الزائر يرى ما
 * يُعجبه ولا يجد بابًا يدخل منه. وهذه الصفحة هي الباب.
 *
 * والسعر يُعرض بالريال والقاعدة تخزّنه بالهللات — تُقسَم **هنا مرّة واحدة**
 * ولا تُقسم في النموذج، فازدواج القسمة أو إغفالها خطأٌ بمعامل مئة.
 */
$tq_p       = $tq_path;
$tq_price   = (int) $tq_p['price'];
$tq_course  = (int) $tq_p['course_id'];
$tq_saleable = ($tq_price > 0 && $tq_course > 0);
$tq_uid     = (int) $this->session->userdata('user_id');

/* سبب المنع يُقال صراحةً: «غير متاح» بلا سبب يجعل الزائر يظنّ العطب في متصفّحه */
$tq_why = '';
if     ($tq_course <= 0) $tq_why = 'هذا البرنامج قيد الإعداد، ومحتواه لم يُربَط به بعد.';
elseif ($tq_price  <= 0) $tq_why = 'هذا البرنامج لم يُسعَّر بعد.';
?>
<section class="page-hero page-hero--path">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <?php include __DIR__ . '/site/site_lantern.php'; ?>
  <div class="shell">
    <nav class="crumbs" aria-label="برنامج التصفّح">
      <a href="<?php echo base_url(); ?>">الرئيسية</a>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('plans'); ?>">المواد والبرامج</a>
      <?php if (!empty($tq_p['cat_name'])): ?>
        <span aria-hidden="true">›</span>
        <a href="<?php echo base_url('plans'); ?>?cat=<?php echo html_escape($tq_p['cat_slug']); ?>"><?php echo html_escape($tq_p['cat_name']); ?></a>
      <?php endif; ?>
    </nav>
    <h1><?php echo html_escape($tq_p['title']); ?></h1>
    <?php if (!empty($tq_p['short_description'])): ?>
      <p class="page-hero__lead"><?php echo html_escape($tq_p['short_description']); ?></p>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="shell tq-cols-2">

    <div class="path-main">
      <div class="icard">
        <h2>عن هذا البرنامج</h2>
        <p><?php echo html_escape($tq_p['short_description'] ?: 'سيُضاف وصف هذا البرنامج قريبًا.'); ?></p>

        <div class="path-facts">
          <?php if (!empty($tq_p['teacher_name'])): ?>
            <span><svg aria-hidden="true"><use href="#i-users"></use></svg><?php echo html_escape($tq_p['teacher_name']); ?></span>
          <?php endif; ?>
          <?php if ((int) $tq_p['expected_weeks'] > 0): ?>
            <span><svg aria-hidden="true"><use href="#i-clock"></use></svg><span class="tq-ltr"><?php echo (int) $tq_p['expected_weeks']; ?></span> أسبوعًا</span>
          <?php endif; ?>
          <?php if (!empty($tq_p['course_title'])): ?>
            <span><svg aria-hidden="true"><use href="#i-book"></use></svg><?php echo html_escape($tq_p['course_title']); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php
      /* المنهج من الدورة المرتبطة — و`get_instance()` لا `$this->load`:
         تحميل نموذج داخل عرض CI3 يُنتج بترًا صامتًا للصفحة لا خطأً يُنبّه. */
      $tq_lessons = array();
      if ($tq_course > 0) {
          $tq_ci = &get_instance();
          $tq_lessons = $tq_ci->db->select('title, lesson_type')->from('lesson')
                                  ->where('course_id', $tq_course)
                                  ->order_by('order', 'ASC')->limit(30)
                                  ->get()->result_array();
      }
      ?>
      <?php if ($tq_lessons): ?>
        <div class="icard">
          <h2>المنهج</h2>
          <ol class="path-syllabus">
            <?php foreach ($tq_lessons as $tq_l): ?>
              <li>
                <svg aria-hidden="true"><use href="#<?php echo ($tq_l['lesson_type'] === 'quiz') ? 'i-clipboard' : 'i-play'; ?>"></use></svg>
                <?php echo html_escape($tq_l['title']); ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      <?php endif; ?>
    </div>

    <aside class="path-buy">
      <div class="icard icard--sticky">
        <?php /* لا سعر ولا شراء هنا: وحدة البيع صارت الباقة. وسعرٌ على
                 صفحة برنامجٍ يعرض منتجًا لا يُباع، ويجعل الزائر يوازن
                 بين خيارين أحدهما وهم. */ ?>
        <p class="path-inbundle">
          <svg aria-hidden="true"><use href="#i-check"></use></svg>
          هذا البرنامج ضمن باقات تقدّر
        </p>
        <p class="tq-caption">اشترك في باقةٍ واحدة وافتح منهج الصفّ كاملًا — لا مادّةً مادّة.</p>
        <a class="btn btn--primary btn--block" href="<?php echo base_url('plans'); ?>">شاهد الباقات</a>
<?php if ($tq_uid <= 0): ?>
        <p class="tq-caption">ليس لديك حساب؟ <a href="<?php echo base_url('sign_up'); ?>">أنشئ حسابًا مجّانًا</a></p>
<?php endif; ?>
        <a class="path-back" href="<?php echo base_url('plans'); ?>">عودة إلى كل البرامج</a>
      </div>
    </aside>

  </div>
</section>
