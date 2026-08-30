<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أرقام النشرة البريدية.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وأربعة استعلامات صارت واحدا مجمعا: كانت
 * `->where('status', …)->get('newsletter_histories')->num_rows()` أربع
 * مرات — أي أن الجدول كله يقرأ من القرص أربع مرات لتعرض أربعة أرقام.
 *
 * و«faild» مكتوبة هكذا في قاعدة البيانات — بخطئها الإملائي. لا تصحح
 * هنا: الكتابة إليها في `Email_model` بالإملاء نفسه، وتصحيح القراءة
 * وحدها يجعل العداد صفرا أبدا.
 */
$tq_counts = array('pending' => 0, 'sent' => 0, 'faild' => 0, 'unable' => 0);

try {
    foreach ($this->db->select('status, COUNT(*) AS n')
                      ->group_by('status')
                      ->get('newsletter_histories')->result_array() as $tq_r) {
        $tq_counts[$tq_r['status']] = (int) $tq_r['n'];
    }
} catch (Throwable $tq_e) {
    /* جدول لم ينشأ بعد — أصفار أهون من شاشة بيضاء. */
}

$tq_cards = array(
    'pending' => array(t('بانتظار الإرسال'), t('في الطابور الآن'),           'peach', 'clock'),
    'sent'    => array(t('أرسلت'),           t('وصلت إلى صناديق المستقبلين'), 'mint',  'check-badge'),
    'faild'   => array(t('تعثرت'),           t('تعاد في الجولة التالية'),     'sand',  'refresh'),
    'unable'  => array(t('تعذر إرسالها'),    t('عشر محاولات فاشلة'),          'rose',  'alert'),
);
?>

<div class="tqa-grid tqa-grid--4 tqa-section">
    <?php foreach ($tq_cards as $tq_k => [$tq_label, $tq_hint, $tq_tone, $tq_ic]): ?>
        <a class="tqa-stat" href="<?php echo site_url('admin/newsletter_history/' . $tq_k); ?>">
            <span class="tqa-stat__top">
                <span class="tqa-stat__label"><?php echo $tq_label; ?></span>
                <span class="tqa-stat__icon tqa-<?php echo $tq_tone; ?>" aria-hidden="true">
                    <?php echo tq_icon($tq_ic, 17); ?>
                </span>
            </span>
            <span class="tqa-stat__value"><?php echo (int) $tq_counts[$tq_k]; ?></span>
            <span class="tqa-stat__hint"><?php echo $tq_hint; ?></span>
        </a>
    <?php endforeach; ?>
</div>
