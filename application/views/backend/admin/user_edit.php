<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** تعديل طالب. النموذج مشترك — انظر [_tq_account_form.php]. */
$tq_row = $this->db->get_where('users', array('id' => (int) $user_id))->row_array();

if (!$tq_row) {
    tqa_head('حساب غير موجود', '', 'users');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty('لا حساب بهذا المعرف', 'قد يكون حذف من شاشة أخرى.',
        'كل الطلاب', site_url('admin/users'), 'users');
    echo '</div>';
    return;
}

$tq_name   = trim($tq_row['first_name'] . ' ' . $tq_row['last_name']) ?: $tq_row['email'];
$tq_action = site_url('admin/users/edit/' . (int) $user_id);
$tq_back   = site_url('admin/users');
$tq_cta    = 'احفظ التعديل';
$tq_skills = false;
?>

<?php tqa_head('تعديل الطالب', $tq_name, 'users',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/users') . '">'
  . tq_icon('chev-prev', 16) . ' كل الطلاب</a>'); ?>

<?php include '_tq_account_form.php'; ?>
