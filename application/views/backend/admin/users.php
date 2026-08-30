<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الطلاب. الجدول مشترك مع شاشة المعلمين — انظر [_tq_people_table.php].
 */
$tq_kind = 'user';
$tq_base = 'admin/users';
$tq_form = 'admin/user_form/edit_user_form';
?>

<?php tqa_head(t('الطلاب'), t('حسابات الطلاب المسجلة في المنصة.'), 'users',
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('admin/user_form/add_user_form') . '">'
  . tq_icon('plus', 17) . t(' إضافة طالب</a>')
  . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/people') . '">'
  . tq_icon('users', 16) . t(' كل الحسابات</a>')); ?>

<?php include '_tq_people_table.php'; ?>
