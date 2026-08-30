<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ملفات الدرس — يفتح في نافذة.
 *
 * TQ-FILES-404 — زر «الملفات» في [curriculum.php] كان يفتح
 * `modal/popup/resource_files/<الدرس>`، **وهذا القالب غير موجود**: يرد
 * المسار 404 فتظهر النافذة فارغة بيضاء. أي أن الزر كان معروضا في كل
 * صف درس منذ كتبت الشاشة ولا يقود إلى شيء.
 *
 * وجدول `resource_files` نفسه كان **يقرأ ولا يكتب**: يعرضه الطالب في
 * «المواد» و«المفضلة» ويعده `tq_student_data`، ولا موضع واحد في هذا
 * المستودع ينشئ فيه صفا. فخمسة عشر صفا فيه من بذرة قديمة، ولا سبيل
 * إلى ملف سادس.
 *
 * فهذه النافذة هي الوجه الناقص: ترفع وتحذف، والطالب يرى ما يرفع في
 * صفحة «المواد التعليمية» بلا تغيير في جهته.
 */
$tq_l = $this->db->select('l.id, l.title, l.course_id, c.title AS course_title', false)
                 ->from('lesson l')->join('course c', 'c.id = l.course_id', 'left')
                 ->where('l.id', (int) $param2)->get()->row_array();

if (!$tq_l) {
    echo t('<p class="tqa-note tqa-note--warn">لا درس بهذا المعرف — قد يكون حذف من نافذة أخرى.</p>');
    return;
}

$tq_files = $this->db->where('lesson_id', (int) $tq_l['id'])
                     ->order_by('id', 'DESC')->get('resource_files')->result_array();
?>

<div class="tqa-note tqa-section">
    <span aria-hidden="true"><?php echo tq_icon('folder', 18); ?></span>
    <span>
        <?php echo t('ملفات درس'); ?> <strong><?php echo html_escape($tq_l['title']); ?></strong><?php echo t('. تظهر للطالب المسجل في «المواد التعليمية» ويحملها من هناك.'); ?>
    </span>
</div>

<form method="post" enctype="multipart/form-data"
      action="<?php echo site_url('admin/resource_file_add/' . (int) $tq_l['id']); ?>">
    <?php echo tq_csrf(); ?>

    <div class="tqa-field">
        <label class="tqa-field__label" for="rf_title"><?php echo t('اسم الملف كما يراه الطالب'); ?></label>
        <input class="tqa-input" type="text" id="rf_title" name="title" maxlength="190"
               placeholder="<?php echo te('مثال: ورقة تدريبات الوحدة الأولى'); ?>">
        <span class="tqa-field__hint"><?php echo t('اتركه فارغا ليعرض اسم الملف المرفوع.'); ?></span>
    </div>

    <div class="tqa-field">
        <span class="tqa-field__label"><?php echo t('الملف'); ?> <span class="tqa-field__req" aria-hidden="true">*</span></span>
        <div class="tqa-file">
            <input type="file" id="rf_file" name="resource_file" required data-tqa-file>
            <label class="tqa-file__btn" for="rf_file"><?php echo tq_icon('upload', 16); ?> اختر ملفا</label>
            <span class="tqa-file__name" data-tqa-file-name><?php echo t('PDF أو مستند أو صورة أو صوت'); ?></span>
        </div>
    </div>

    <div class="tqa-actions">
        <button class="tqa-btn tqa-btn--primary tqa-btn--block" type="submit">
            <?php echo tq_icon('plus', 16); ?> أضف الملف
        </button>
    </div>
</form>

<?php if (!$tq_files): ?>

    <p style="margin-block-start:var(--tq-space-xl);font:var(--tq-type-caption);color:var(--tq-text2)">
        <?php echo t('لا ملفات في هذا الدرس بعد.'); ?>
    </p>

<?php else: ?>

<ul style="list-style:none;margin:var(--tq-space-xl) 0 0;padding:0">
<?php foreach ($tq_files as $tq_f):
    $tq_rel  = 'uploads/resource_files/' . $tq_f['file_name'];
    $tq_here = is_file(FCPATH . $tq_rel);
?>
    <li style="display:flex;align-items:center;gap:var(--tq-space-m);
               padding:var(--tq-space-m) 0;border-block-start:1px solid var(--tq-line)">

        <span class="tqa-iconbox tqa-sand" aria-hidden="true" style="inline-size:34px;block-size:34px">
            <?php echo tq_icon('file', 16); ?>
        </span>

        <span style="flex:1;min-inline-size:0">
            <span class="tqa-media__title">
                <?php echo html_escape($tq_f['title'] !== '' ? $tq_f['title'] : $tq_f['file_name']); ?>
            </span>
            <span class="tqa-media__sub">
                <?php if ($tq_here): ?>
                    <?php echo html_escape(number_format(filesize(FCPATH . $tq_rel) / 1024, 0)); ?> ك.ب
                <?php else: ?>
                    <span style="color:var(--tq-danger)"><?php echo t('الملف مفقود على الخادم'); ?></span>
                <?php endif; ?>
            </span>
        </span>

        <span class="tqa-rowacts">
            <?php if ($tq_here): ?>
                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" href="<?php echo base_url($tq_rel); ?>"
                   target="_blank" rel="noopener">
                    <?php echo tq_icon('download', 14); ?> افتح
                </a>
            <?php endif; ?>

            <form method="post"
                  action="<?php echo site_url('admin/resource_file_delete/' . (int) $tq_f['id']); ?>"
                  data-tqa-confirm-title="<?php echo te('حذف الملف'); ?>"
                  data-tqa-confirm="سيحذف «<?php echo html_escape($tq_f['title'] !== '' ? $tq_f['title'] : $tq_f['file_name']); ?>» من الدرس ومن الخادم."
                  data-tqa-confirm-ok="نعم، احذف"
                  data-tqa-confirm-tone="danger">
                <?php echo tq_csrf(); ?>
                <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">
                    <?php echo tq_icon('trash', 14); ?>
                    <span class="tqa-sr"><?php echo t('حذف'); ?></span>
                </button>
            </form>
        </span>
    </li>
<?php endforeach; ?>
</ul>

<?php endif; ?>

<?php include 'tqa_file_js.php'; ?>
