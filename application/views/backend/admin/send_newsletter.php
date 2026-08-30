<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إرسال نشرة.
 *
 * TQ-EMPTY-SELECT — كان منتقي المستخدمين **فارغا بلا خيار واحد**،
 * ينتظر select2 ليملأه بـAJAX — وهو غير محمل في اللوحة (انظر
 * TQ-SELECT2-GONE). أي أن خيار «مستخدمون محددون» — وهو **الخيار الأول
 * والافتراضي** في قائمة الوجهة — لم يكن يمكن استعماله إطلاقا: تفتح
 * الشاشة، فترى قائمة اختيار فارغة، ولا سبيل إلى وضع اسم فيها.
 *
 * والحسابات هنا تملأ من الخادم، وفوقها حقل ترشيح يعمل في المتصفح.
 */
if (!$newsletter) {
    tqa_head(t('قالب غير موجود'), '', 'send');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty(t('لا قالب بهذا المعرف'), t('قد يكون حذف من شاشة أخرى.'),
        t('كل القوالب'), site_url('admin/newsletters'), 'send');
    echo '</div>';
    return;
}

$tq_users = $this->db->select('id, first_name, last_name, email')
                     ->where('status', 1)
                     ->order_by('first_name', 'ASC')
                     ->limit(2000)
                     ->get('users')->result_array();

$tq_targets = array(
    'selected_user'             => t('مستخدمون أختارهم'),
    'all'                       => t('كل الحسابات'),
    'student'                   => t('الطلاب وحدهم'),
    'instructor'                => t('المعلمون وحدهم'),
    'all_subscriber'            => t('كل مشتركي النشرة'),
    'registered_subscriber'     => t('مشتركو النشرة المسجلون في المنصة'),
    'non_registered_subscriber' => t('مشتركو النشرة غير المسجلين'),
);
?>

<?php tqa_head(t('إرسال النشرة'), $newsletter['subject'], 'send',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/newsletters') . '">'
  . tq_icon('chev-prev', 16) . t('كل القوالب</a>')); ?>

<div class="tqa-note tqa-note--warn tqa-section" style="max-inline-size:820px">
    <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
    <span>
        <strong><?php echo t('لا رجعة بعد الإرسال.'); ?></strong>
        <?php echo t('الرسائل تدخل الطابور فورا، وترسل على دفعات من عشرين في الدقيقة. راجع النص والوجهة قبل الضغط.'); ?>
    </span>
</div>

<form class="tqa-card" action="<?php echo site_url('admin/newsletters/send'); ?>" method="post"
      style="max-inline-size:820px"
      data-tqa-confirm-title="<?php echo te('إرسال النشرة'); ?>"
      data-tqa-confirm="ستدخل الرسائل الطابور فورا، ولا يمكن سحبها بعدها."
      data-tqa-confirm-ok="نعم، أرسل">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="send_to">
            <?php echo t('الوجهة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <select class="tqa-select" id="send_to" name="send_to" required data-tqa-target>
            <?php foreach ($tq_targets as $tq_k => $tq_l): ?>
                <option value="<?php echo $tq_k; ?>"><?php echo html_escape($tq_l); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="tqa-field" data-tqa-picklist>
        <label class="tqa-field__label" for="multiple_user_id"><?php echo t('اختر الحسابات'); ?></label>

        <input class="tqa-input" type="search" data-tqa-filter="multiple_user_id"
               placeholder="<?php echo te('اكتب للترشيح بالاسم أو البريد…'); ?>" autocomplete="off"
               style="margin-block-end:var(--tq-space-s)">

        <select class="tqa-select" id="multiple_user_id" name="user_id[]" multiple size="10"
                style="min-block-size:auto">
            <?php foreach ($tq_users as $tq_u):
                $tq_n = trim($tq_u['first_name'] . ' ' . $tq_u['last_name']);
            ?>
                <option value="<?php echo (int) $tq_u['id']; ?>">
                    <?php echo html_escape(($tq_n !== '' ? $tq_n . ' — ' : '') . $tq_u['email']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="tqa-field__hint">
            <?php echo t('اختر واحدا أو أكثر — بـ'); ?><span class="tq-ltr" dir="ltr">Ctrl</span> <?php echo t('أو'); ?>
            <span class="tq-ltr" dir="ltr">⌘</span> <?php echo t('للاختيار المتعدد. المعروض'); ?> <span class="tqa-num"><?php echo count($tq_users); ?></span> <?php echo t('حسابا.'); ?>
        </span>
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="newsletter_subject">
            <?php echo t('عنوان الرسالة'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="newsletter_subject" name="subject" required maxlength="190"
               value="<?php echo html_escape($newsletter['subject']); ?>">
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="newsletter_description"><?php echo t('نص الرسالة'); ?></label>
        <textarea class="tqa-textarea" id="newsletter_description" name="description" rows="10"
                  data-tqa-rich><?php echo html_escape($newsletter['description']); ?></textarea>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('send', 16); ?> أرسل النشرة
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/newsletters'); ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>

<script>
(function () {
    'use strict';

    /* قائمة الأسماء تظهر مع «مستخدمون أختارهم» وحدها. */
    var target = document.querySelector('[data-tqa-target]');
    var list   = document.querySelector('[data-tqa-picklist]');

    if (target && list) {
        var sync = function () { list.hidden = target.value !== 'selected_user'; };
        target.addEventListener('change', sync);
        sync();
    }

    /* ترشيح في المتصفح: ألفا حساب لا تقرأ بالتمرير. */
    var filter = document.querySelector('[data-tqa-filter]');
    if (!filter) return;

    var select = document.getElementById(filter.getAttribute('data-tqa-filter'));
    if (!select) return;

    var all = Array.prototype.slice.call(select.options);

    filter.addEventListener('input', function () {
        var q = filter.value.trim().toLowerCase();

        all.forEach(function (opt) {
            /* الخيار المحدد يبقى ظاهرا مهما كان الترشيح — وإلا اختفى من
               الشاشة وهو مرسل، فيرسل المستخدم إلى من لا يراه. */
            opt.hidden = !(opt.selected || q === '' || opt.text.toLowerCase().indexOf(q) !== -1);
        });
    });
})();
</script>
