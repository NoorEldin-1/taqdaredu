<?php
/**
 * اللوحة الجانبية لصفحات الحساب.
 *
 * كانت منسوخة حرفيا في خمسة قوالب، فتصحيح بنيتها يحتاج خمسة تعديلات
 * وينسى في واحد فينفرد بشكله. وهي هنا جزء واحد يضبط بمتغيرين:
 *
 *   $tq_aside_h2     — العنوان
 *   $tq_aside_points — صفوف: [اسم الأيقونة, العنوان, الشرح]
 *
 * والبنية طبقتان لا طبقة: **نافذة للصورة** ثم **لوح للنص**. وكانت
 * الصورة تملأ اللوحة كلها والنص فوقها بتدرج غامق، فيرتهن ارتفاع
 * الصورة بطول النموذج: في التسجيل معلما يبلغ ألف بكسل فتقص الصورة
 * شريطا. وبالطبقتين يأخذ اللوح فائض الطول والصورة تبقى صورة.
 */
$tq_aside_h2 = isset($tq_aside_h2) ? $tq_aside_h2 : 'منصة تعليمية سعودية';
$tq_aside_points = isset($tq_aside_points) && is_array($tq_aside_points) ? $tq_aside_points : array(
    array('i-cap',         'برامج متدرجة', 'مصممة وفق المناهج السعودية'),
    array('i-chart',       'متابعة تقدمك', 'تقارير دقيقة لك ولولي أمرك'),
    array('i-certificate', 'شهادات إتقان', 'تصدر عند اجتياز المحطات'),
);
?>
<aside class="auth-aside" aria-hidden="true">
  <div class="auth-aside__photo">
    <img src="<?php echo tq_site_asset('img/auth-panel.webp'); ?>" alt=""
         width="700" height="1050" loading="lazy" decoding="async">
  </div>
  <div class="auth-aside__body">
    <h2><?php echo html_escape($tq_aside_h2); ?></h2>
    <ul class="auth-points">
      <?php foreach ($tq_aside_points as $tq_p): ?>
        <li>
          <svg aria-hidden="true"><use href="#<?php echo html_escape($tq_p[0]); ?>"></use></svg>
          <span><b><?php echo html_escape($tq_p[1]); ?></b><?php echo html_escape($tq_p[2]); ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</aside>
