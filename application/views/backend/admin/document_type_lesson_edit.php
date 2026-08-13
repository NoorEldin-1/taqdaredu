<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="tqa-field">
    <label class="tqa-field__label" for="lesson_type">
        نوع المستند <span class="tqa-field__req" aria-hidden="true">*</span>
    </label>
    <select class="tqa-select" id="lesson_type" name="lesson_type" required>
        <?php foreach (array(
            'other-pdf' => array('pdf', 'ملف PDF'),
            'other-doc' => array('doc', 'مستند Word'),
            'other-txt' => array('txt', 'ملف نصي'),
        ) as $tq_v => [$tq_att, $tq_label]): ?>
            <option value="<?php echo $tq_v; ?>"
                <?php echo $lesson_details['attachment_type'] === $tq_att ? 'selected' : ''; ?>>
                <?php echo $tq_label; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="tqa-field">
    <span class="tqa-field__label">الملف</span>
    <div class="tqa-file">
        <input type="file" id="attachment" name="attachment" accept=".pdf,.doc,.docx,.txt" data-tqa-file>
        <label class="tqa-file__btn" for="attachment"><?php echo tq_icon('upload', 16); ?> استبدل الملف</label>
        <span class="tqa-file__name" data-tqa-file-name>اتركه فارغا لإبقاء الملف الحالي</span>
    </div>
</div>
