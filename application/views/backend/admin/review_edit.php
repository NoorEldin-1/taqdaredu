<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!$rating) {
    tqa_head(t('رأي غير موجود'), '', 'star');
    echo '<div class="tqa-card tqa-card--flush">';
    tqa_empty(t('لا رأي بهذا المعرف'), t('قد يكون حذف من شاشة أخرى.'),
        t('كل الآراء'), site_url('admin/frontend_settings?tab=reviews'), 'star');
    echo '</div>';
    return;
}

$tq_users = $this->db->select('id, first_name, last_name, email')
                     ->where('role_id', 2)->where('is_instructor', 0)
                     ->order_by('first_name', 'ASC')
                     ->get('users')->result_array();
?>

<?php tqa_head(t('تعديل الرأي'), '', 'star',
    '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/frontend_settings?tab=reviews') . '">'
  . tq_icon('chev-prev', 16) . t('كل الآراء</a>')); ?>

<form class="tqa-card" action="<?php echo site_url('admin/frontend_settings/review_update'); ?>" method="post"
      style="max-inline-size:680px">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="id" value="<?php echo (int) $rating['id']; ?>">

    <div class="tqa-field">
        <label class="tqa-field__label" for="user_id">
            <?php echo t('صاحب الرأي'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <input class="tqa-input" type="search" data-tqa-filter="user_id" autocomplete="off"
               placeholder="<?php echo te('اكتب للترشيح…'); ?>" style="margin-block-end:var(--tq-space-s)">
        <select class="tqa-select" id="user_id" name="user_id" required>
            <option value=""><?php echo t('— اختر حسابا'); ?></option>
            <?php foreach ($tq_users as $tq_u):
                $tq_n = trim($tq_u['first_name'] . ' ' . $tq_u['last_name']);
            ?>
                <option value="<?php echo (int) $tq_u['id']; ?>"
                    <?php echo (int) $rating['user_id'] === (int) $tq_u['id'] ? 'selected' : ''; ?>>
                    <?php echo html_escape(($tq_n !== '' ? $tq_n . ' — ' : '') . $tq_u['email']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="rating">
            <?php echo t('التقييم'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <select class="tqa-select" id="rating" name="rating" required>
            <option value=""><?php echo t('— اختر تقييما'); ?></option>
            <?php for ($tq_i = 5; $tq_i >= 1; $tq_i--): ?>
                <option value="<?php echo $tq_i; ?>"
                    <?php echo (int) $rating['rating'] === $tq_i ? 'selected' : ''; ?>>
                    <?php echo $tq_i; ?> من 5
                </option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="tqa-field">
        <label class="tqa-field__label" for="review">
            <?php echo t('نص الرأي'); ?> <span class="tqa-field__req" aria-hidden="true">*</span>
        </label>
        <textarea class="tqa-textarea" id="review" name="review" rows="4" required><?php
            echo html_escape($rating['review']); ?></textarea>
    </div>

    <div class="tqa-actions">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?> احفظ التعديل
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/frontend_settings?tab=reviews'); ?>"><?php echo t('إلغاء'); ?></a>
    </div>
</form>

<?php include 'tqa_selectfilter_js.php'; ?>
