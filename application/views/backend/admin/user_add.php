<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** إضافة طالب. النموذج مشترك — انظر [_tq_account_form.php]. */
$tq_action = site_url('admin/users/add');
$tq_back   = site_url('admin/users');
$tq_cta    = t('أنشئ حساب الطالب');
$tq_row    = null;
$tq_skills = false;
?>

<?php tqa_head(t('إضافة طالب'), t('الحساب الجديد نشط فورا ويستطيع الدخول.'), 'users',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/users') . '">'
  . tq_icon('chev-prev', 16) . t('كل الطلاب</a>')); ?>

<?php include '_tq_account_form.php'; ?>
