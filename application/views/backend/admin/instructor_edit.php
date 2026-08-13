<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** تعديل معلم. النموذج مشترك — انظر [_tq_account_form.php]. */
$tq_row = $this->db->get_where('users', array('id' => (int) $user_id))->row_array();

if (!$tq_row) {
    tqa_head('حساب غير موجود', '', 'graduation');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty('لا حساب بهذا المعرف', 'قد يكون حذف من شاشة أخرى.',
        'كل المعلمين', site_url('admin/instructors'), 'graduation');
    echo '</div>';
    return;
}

$tq_name   = trim($tq_row['first_name'] . ' ' . $tq_row['last_name']) ?: $tq_row['email'];
$tq_action = site_url('admin/instructors/edit/' . (int) $user_id);
$tq_back   = site_url('admin/instructors');
$tq_cta    = 'احفظ التعديل';
$tq_skills = true;
?>

<?php tqa_head('تعديل المعلم', $tq_name, 'graduation',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('home/instructor_page/' . (int) $user_id) . '"'
  . ' target="_blank" rel="noopener">' . tq_icon('external', 16) . ' صفحته في الموقع</a>'
  . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/instructors') . '">'
  . tq_icon('chev-prev', 16) . ' كل المعلمين</a>'); ?>

<?php include '_tq_account_form.php'; ?>
