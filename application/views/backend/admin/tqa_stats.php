<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * أرقام الموقع — ما يعرض على الصفحات العامة.
 *
 * **ما يترك فارغا لا يعرض** على الموقع: بنده يختفي كله. وهذا مقصود —
 * «٠ طالب» ادعاء معكوس، وغياب البند أصدق منه.
 */
$tq_fields = array(
    'students' => array('طلاب', 'يظهر في: الطلاب · من نحن · المعلمون'),
    'teachers' => array('معلمون', 'يظهر في: المعلمون · من نحن'),
    'paths'    => array('مسارات تعليمية', 'يظهر في: الطلاب · من نحن'),
    'subjects' => array('مواد دراسية', 'يظهر في: المعلمون'),
    'lessons'  => array('دروس', 'يظهر في: من نحن'),
    'books'    => array('كتب', 'غير معروض حاليا'),
    'hours'    => array('ساعات تعلم', 'يظهر في: الطلاب · من نحن'),
    'rating'   => array('مستوى الرضا', 'يظهر في: المعلمون · الطلاب'),
);
?>
<div class="tqa-wrap">
  <?php echo tqa_flash(); ?>
  <div class="tqa-head">
    <h1>أرقام الموقع</h1>
    <p class="tqa-lead">
      ما تكتبه هنا يظهر في أشرطة الأرقام على الصفحات العامة كما تكتبه تماما
      (‏<span dir="ltr">+500</span> · <span dir="ltr">550K</span> · <span dir="ltr">98%</span>).
      <strong>وما تتركه فارغا لا يعرض إطلاقا</strong> — لا يظهر صفرا.
    </p>
  </div>

  <form method="post" action="<?php echo site_url('taqdar_admin/stats_save'); ?>" class="tqa-card">
    <div class="tqa-grid">
      <?php foreach ($tq_fields as $tq_k => $tq_m): ?>
        <label class="tqa-field">
          <span class="tqa-label"><?php echo $tq_m[0]; ?></span>
          <input type="text" name="<?php echo $tq_k; ?>" dir="ltr"
                 value="<?php echo html_escape(get_settings('taqdar_stat_' . $tq_k)); ?>"
                 placeholder="اتركه فارغا فلا يعرض">
          <span class="tqa-hint"><?php echo $tq_m[1]; ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="tqa-actions">
      <button type="submit" class="tqa-btn tqa-btn--primary">حفظ</button>
    </div>
  </form>
</div>
