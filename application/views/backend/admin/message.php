<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * الرسائل الخاصة — الغلاف.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وثلاثة أشياء تغيرت عن النسخة السابقة:
 *
 * ١ — **كانت تستعلم داخل الحلقة.** لكل محادثة استعلامان: صف المستخدم،
 *     وعدد غير المقروء. وصفحة بثلاثين محادثة تنفذ ستين استعلاما لترسم
 *     عمودا جانبيا. صارت استعلامين اثنين لكل الصفحة.
 * ٢ — **`$user_to_show_id` كان يتسرب من دورة إلى دورة.** يسند داخل
 *     شرطين متتاليين بلا `else`، فمحادثة يكون فيها المرسل والمستقبل
 *     الشخص نفسه (وهي واردة) تحمل قيمة الدورة السابقة، فيظهر اسم شخص
 *     آخر على محادثة ليست له.
 * ٣ — **`$user_details['first_name']` بلا فحص.** حساب حذف يرد `null`،
 *     وقراءة فهرس من `null` تحذير في PHP 8.2 يطبع فوق الصفحة.
 */
$tq_me = (int) $this->session->userdata('user_id');

$tq_threads = $this->db->where('sender', $tq_me)
                       ->or_where('receiver', $tq_me)
                       ->get('message_thread')->result_array();

/* الطرف الآخر في كل محادثة، ثم أسماء أولئك جميعا باستعلام واحد. */
$tq_others = array();
foreach ($tq_threads as $tq_t) {
    $tq_others[$tq_t['message_thread_code']] = (int) $tq_t['sender'] === $tq_me
        ? (int) $tq_t['receiver']
        : (int) $tq_t['sender'];
}

$tq_people = array();
if ($tq_others) {
    foreach ($this->db->select('id, first_name, last_name, email')
                      ->where_in('id', array_unique(array_values($tq_others)))
                      ->get('users')->result_array() as $tq_p) {
        $tq_people[(int) $tq_p['id']] = $tq_p;
    }
}

$tq_current = isset($current_message_thread_code) ? $current_message_thread_code : '';
?>

<?php tqa_head(t('الرسائل الخاصة'), t('محادثات الإدارة مع الطلاب والمعلمين.'), 'chat',
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('admin/message/message_new') . '">'
  . tq_icon('edit', 16) . t('رسالة جديدة</a>')); ?>

<div class="tqa-chat">

    <aside class="tqa-card tqa-card--flush">
        <div class="tqa-card__head" style="padding:var(--tq-space-m) var(--tq-space-l)">
            <h2 style="font:var(--tq-type-bodyStrong);font-family:var(--tq-font-title)"><?php echo t('المحادثات'); ?></h2>
        </div>

        <?php if (empty($tq_threads)): ?>
            <p style="padding:var(--tq-space-l);margin:0;font:var(--tq-type-caption);color:var(--tq-text2)">
                <?php echo t('لا محادثات بعد. ابدأ واحدة من زر «رسالة جديدة».'); ?>
            </p>
        <?php else: ?>
            <nav class="tqa-threads" style="padding:var(--tq-space-s)" aria-label="<?php echo te('قائمة المحادثات'); ?>">
                <?php foreach ($tq_threads as $tq_t):
                    $tq_code  = $tq_t['message_thread_code'];
                    $tq_who   = $tq_people[$tq_others[$tq_code]] ?? null;
                    $tq_label = $tq_who ? trim($tq_who['first_name'] . ' ' . $tq_who['last_name']) : '';
                    if ($tq_label === '') $tq_label = $tq_who ? $tq_who['email'] : t('حساب محذوف');
                    $tq_unread = (int) $this->crud_model->count_unread_message_of_thread($tq_code);
                ?>
                    <a class="tqa-thread" href="<?php echo site_url('admin/message/message_read/' . $tq_code); ?>"
                       <?php echo $tq_current === $tq_code ? 'aria-current="page"' : ''; ?>>
                        <span class="tqa-thread__name"><?php echo html_escape($tq_label); ?></span>
                        <?php if ($tq_unread > 0): ?>
                            <span class="tqa-thread__count"><?php echo $tq_unread; ?></span>
                            <span class="tqa-sr"><?php echo t('رسالة غير مقروءة'); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </aside>

    <div>
        <?php
        /* الشاشة الداخلية: القراءة أو الإنشاء أو الترحيب. والاسم يقيد
           بقائمة بيضاء — كان يضمن من `$param1` مباشرة، أي أن مقطعا في
           الرابط يختار ملفا من القرص. */
        $tq_inner = isset($message_inner_page_name) ? $message_inner_page_name : 'message_home';
        if (!in_array($tq_inner, array('message_home', 'message_new', 'message_read'), true)) {
            $tq_inner = 'message_home';
        }
        include $tq_inner . '.php';
        ?>
    </div>
</div>
