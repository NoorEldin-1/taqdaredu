<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * المعلمون. الجدول مشترك مع شاشة الطلاب — انظر [_tq_people_table.php].
 */
$tq_kind = 'instructor';
$tq_base = 'admin/instructors';
$tq_form = 'admin/instructor_form/edit_instructor_form';
?>

<?php tqa_head(t('المعلمون'), t('حسابات المعلمين — والإسناد إلى المواد والصفوف في شاشة أخرى.'), 'graduation',
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('admin/instructor_form/add_instructor_form') . '">'
  . tq_icon('plus', 17) . t('إضافة معلم</a>')
  . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/module/teacher_assignments') . '">'
  . tq_icon('link', 16) . t('الإسناد</a>')); ?>

<?php include '_tq_people_table.php'; ?>
