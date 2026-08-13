<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * رسالة جديدة.
 *
 * ما تغير:
 *
 * ١ — **`get_user()` كانت ترجع كل حسابات المنصة** تحت عنوان «الطلاب»:
 *     معلمين وأولياء أمور ومسؤولين معهم، وكلهم في مجموعة اسمها «طلاب».
 *     صارت مجموعات حقيقية، وكل حساب في مجموعته.
 * ٢ — **`select2` غير محمل في اللوحة**، فالصنف زينة والمنتقي قائمة
 *     عادية أصلا. حذف الصنف، وأضيف بحث المتصفح الأصلي عبر `<optgroup>`.
 * ٣ — **`check_receiver()` كانت تنادي `toastr`** — وهي مكتبة غير محملة،
 *     فالنداء يرمي `toastr is not defined`. ولم تكن تنادى من أي مكان
 *     أصلا: الزر لا يحمل `onclick`. حذفت، والإلزام يفرضه `required`.
 */
$tq_people = $this->db->select('id, first_name, last_name, email, is_instructor, role_id')
                      ->order_by('first_name', 'ASC')
                      ->get('users')->result_array();

$tq_groups = array(
    'teacher' => array('المعلمون', array()),
    'student' => array('الطلاب',   array()),
    'admin'   => array('المسؤولون', array()),
);

foreach ($tq_people as $tq_p) {
    if ((int) $tq_p['role_id'] === 1)          $tq_key = 'admin';
    elseif ((int) $tq_p['is_instructor'] === 1) $tq_key = 'teacher';
    else                                        $tq_key = 'student';

    $tq_groups[$tq_key][1][] = $tq_p;
}
?>

<div class="tqa-card">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox" aria-hidden="true"><?php echo tq_icon('edit', 20); ?></span>
        <h2>رسالة جديدة</h2>
    </div>

    <form method="post" action="<?php echo site_url('admin/message/send_new'); ?>">
        <?php echo tq_csrf(); ?>

        <div class="tqa-field">
            <label class="tqa-field__label" for="receiver">
                المستقبل <span class="tqa-field__req" aria-hidden="true">*</span>
            </label>
            <select class="tqa-select" id="receiver" name="receiver" required>
                <option value="">— اختر حسابا</option>
                <?php foreach ($tq_groups as [$tq_glabel, $tq_gpeople]): ?>
                    <?php if (empty($tq_gpeople)) continue; ?>
                    <optgroup label="<?php echo html_escape($tq_glabel); ?>">
                        <?php foreach ($tq_gpeople as $tq_p):
                            $tq_n = trim($tq_p['first_name'] . ' ' . $tq_p['last_name']);
                        ?>
                            <option value="<?php echo (int) $tq_p['id']; ?>">
                                <?php echo html_escape($tq_n !== '' ? $tq_n : $tq_p['email']); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="tqa-field">
            <label class="tqa-field__label" for="message">
                نص الرسالة <span class="tqa-field__req" aria-hidden="true">*</span>
            </label>
            <textarea class="tqa-textarea" id="message" name="message" rows="6" required
                      placeholder="اكتب رسالتك…"></textarea>
        </div>

        <div class="tqa-actions">
            <button type="submit" class="tqa-btn tqa-btn--primary">
                <?php echo tq_icon('send', 16); ?> أرسل الرسالة
            </button>
            <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('admin/message'); ?>">إلغاء</a>
        </div>
    </form>
</div>
