<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** إضافة معلم. النموذج مشترك — انظر [_tq_account_form.php]. */
$tq_action = site_url('admin/instructors/add');
$tq_back   = site_url('admin/instructors');
$tq_cta    = 'أنشئ حساب المعلم';
$tq_row    = null;
$tq_skills = true;
?>

<?php tqa_head('إضافة معلم',
    'الحساب ينشأ هنا، والإسناد إلى المواد والصفوف من شاشة «إسناد المعلمين».',
    'graduation',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/instructors') . '">'
  . tq_icon('chev-prev', 16) . ' كل المعلمين</a>'); ?>

<?php include '_tq_account_form.php'; ?>
