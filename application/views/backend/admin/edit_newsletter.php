<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!$newsletter) {
    tqa_head(t('قالب غير موجود'), '', 'send');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty(t('لا قالب بهذا المعرف'), t('قد يكون حذف من شاشة أخرى.'),
        t('كل القوالب'), site_url('admin/newsletters'), 'send');
    echo '</div>';
    return;
}
?>

<?php tqa_head(t('تعديل قالب النشرة'), $newsletter['subject'], 'send',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/newsletter_send_form/' . (int) $newsletter['id']) . '">'
  . tq_icon('send', 16) . t('أرسله</a>')
  . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/newsletters') . '">'
  . tq_icon('chev-prev', 16) . t('كل القوالب</a>')); ?>

<form class="tqa-card" method="post" style="max-inline-size:820px"
      action="<?php echo site_url('admin/newsletters/edit/' . (int) $newsletter['id']); ?>">
    <?php echo tq_csrf(); ?>

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
            <?php echo tq_icon('check', 16); ?> احفظ التعديل
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/newsletters'); ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>
