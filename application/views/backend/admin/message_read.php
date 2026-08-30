<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * قراءة محادثة والرد عليها.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما كان قبلها:
 *
 * ١ — **مبنية على أصناف قالب Hyper** (`chat-conversation` · `ctext-wrap`
 *     · `conversation-list slimscroll`) وهي غير معرفة في أي ورقة تحمل في
 *     اللوحة اليوم — فالمحادثة تخرج فقرات عارية بعضها تحت بعض بلا فقاعة
 *     ولا محاذاة تميز المتكلمين.
 * ٢ — **ثلاثة استعلامات لكل رسالة.** `get_all_user()` ثم `get_where('users')`
 *     **مرتين في السطر نفسه** لطبع الاسم الأول واسم العائلة. أي أن
 *     محادثة من ثلاثين رسالة تنفذ تسعين استعلاما.
 * ٣ — **`$row['message']` يطبع خاما.** نص يكتبه مستخدم يطبع في HTML بلا
 *     تهريب — أي أن رسالة فيها وسم تنفذ في متصفح المسؤول.
 * ٤ — **جهة الفقاعة تحسب من `$first_sender`** — أي من أول من بدأ
 *     المحادثة، لا ممن يقرؤها. فرسائل الإدارة تظهر يمينا أو يسارا حسب
 *     من فتح الحديث، لا حسب من ينظر. صارت تحسب من المستخدم الحالي.
 */
$tq_me   = (int) $this->session->userdata('user_id');
$tq_code = $current_message_thread_code;

$tq_msgs = $this->db->where('message_thread_code', $tq_code)
                    ->order_by('timestamp', 'ASC')
                    ->get('message')->result_array();

/* أسماء المتكلمين وصورهم باستعلام واحد لا ثلاثة لكل رسالة. */
$tq_ids = array_unique(array_map(function ($m) { return (int) $m['sender']; }, $tq_msgs));
$tq_who = array();
if ($tq_ids) {
    foreach ($this->db->select('id, first_name, last_name, email')
                      ->where_in('id', $tq_ids)->get('users')->result_array() as $tq_u) {
        $tq_who[(int) $tq_u['id']] = trim($tq_u['first_name'] . ' ' . $tq_u['last_name']) ?: $tq_u['email'];
    }
}
?>

<div class="tqa-card tqa-card--flush">
    <div class="tqa-card__head">
        <span class="tqa-iconbox" aria-hidden="true"><?php echo tq_icon('chat', 20); ?></span>
        <h2><?php echo t('المحادثة'); ?></h2>
    </div>

    <?php if (empty($tq_msgs)): ?>
        <?php tqa_empty(t('لا رسائل في هذه المحادثة'), t('اكتب أول رسالة من الحقل أسفل الشاشة.'), '', '', 'chat'); ?>
    <?php else: ?>
        <ul class="tqa-msgs">
            <?php foreach ($tq_msgs as $tq_m):
                $tq_sid  = (int) $tq_m['sender'];
                $tq_mine = $tq_sid === $tq_me;
            ?>
                <li class="tqa-msg<?php echo $tq_mine ? ' tqa-msg--me' : ''; ?>">
                    <img class="tqa-avatar tqa-avatar--sm" alt="" width="30" height="30" loading="lazy"
                         src="<?php echo html_escape($this->user_model->get_user_image_url($tq_sid)); ?>">

                    <div style="min-inline-size:0">
                        <div class="tqa-msg__body">
                            <span class="tqa-msg__who"><?php
                                echo html_escape($tq_who[$tq_sid] ?? t('حساب محذوف')); ?></span>
                            <?php echo nl2br(html_escape($tq_m['message'])); ?>
                        </div>
                        <span class="tqa-msg__at tq-ltr" dir="ltr">
                            <?php echo date('Y-m-d H:i', (int) $tq_m['timestamp']); ?>
                        </span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="tqa-card__body" style="border-block-start:1px solid var(--tq-line)">
        <form class="tqa-composer" method="post"
              action="<?php echo site_url('admin/message/send_reply/' . $tq_code); ?>">
            <?php echo tq_csrf(); ?>
            <label class="tqa-sr" for="reply"><?php echo t('اكتب ردك'); ?></label>
            <input class="tqa-input" type="text" id="reply" name="message" required
                   placeholder="<?php echo te('اكتب ردك…'); ?>" autocomplete="off">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('send', 16); ?> <?php echo t('أرسل'); ?>
            </button>
        </form>
    </div>
</div>
