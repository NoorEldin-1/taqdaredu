<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php tqa_head('قالب نشرة جديد', 'القالب يكتب مرة ويرسل مرات. والإرسال شاشة أخرى.', 'send',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/newsletters') . '">'
  . tq_icon('chev-prev', 16) . ' كل القوالب</a>'); ?>

<form class="tqa-card" action="<?php echo site_url('admin/newsletters/add'); ?>" method="post"
      style="max-inline-size:820px">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="newsletter_subject">
            عنوان الرسالة <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="text" id="newsletter_subject" name="subject" required maxlength="190">
        <span class="tqa-field__hint">هذا ما يقرؤه المستقبل في صندوقه قبل أن يفتح.</span>
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="newsletter_description">نص الرسالة</label>
        <textarea class="tqa-textarea" id="newsletter_description" name="description" rows="10"
                  data-tqa-rich></textarea>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> احفظ القالب
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/newsletters'); ?>">إلغاء</a>
    </div>
</form>
