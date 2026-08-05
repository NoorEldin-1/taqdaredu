<?php
defined('BASEPATH') or exit('No direct script access allowed');
$M  = &get_instance()->taqdar_admin_model;
$is_edit = ($rid > 0 && $row);
?>

<div class="tqa-head">
    <div>
        <h1><?php echo $is_edit ? 'تعديل' : 'إضافة'; ?> — <?php echo html_escape($spec['title']); ?></h1>
        <p><?php echo html_escape($spec['lead']); ?></p>
    </div>
    <a class="btn btn-secondary" href="<?php echo site_url('taqdar_admin/module/' . $mkey); ?>">رجوع إلى القائمة</a>
</div>

<?php tqa_flash(); ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?php echo site_url('taqdar_admin/save/' . $mkey . '/' . (int) $rid); ?>">
            <div class="row">

            <?php foreach ($spec['fields'] as $name => $f):
                $val = tqa_val($row, $name, $f);
                $id  = 'f_' . $name;
                $wide = in_array($f['type'], array('textarea', 'lines'), true);
            ?>
                <div class="<?php echo $wide ? 'col-12' : 'col-md-6'; ?> tqa-field">
                    <label for="<?php echo $id; ?>">
                        <?php echo html_escape($f['label']); ?>
                        <?php if (!empty($f['required'])): ?><span class="tqa-req">*</span><?php endif; ?>
                    </label>

                    <?php if ($f['type'] === 'bool'): ?>

                        <div class="tqa-switch">
                            <input type="checkbox" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                                   value="1" <?php echo $val ? 'checked' : ''; ?>>
                            <label for="<?php echo $id; ?>">نعم</label>
                        </div>

                    <?php elseif ($f['type'] === 'enum'): ?>

                        <select class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>">
                            <?php foreach ($f['options'] as $k => $lbl): ?>
                                <option value="<?php echo html_escape($k); ?>" <?php echo ((string) $val === (string) $k) ? 'selected' : ''; ?>>
                                    <?php echo html_escape($lbl); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    <?php elseif ($f['type'] === 'ref'): ?>

                        <select class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>">
                            <option value="0">— بلا تحديد —</option>
                            <?php foreach ($M->options($f['ref']) as $k => $lbl): ?>
                                <option value="<?php echo (int) $k; ?>" <?php echo ((int) $val === (int) $k) ? 'selected' : ''; ?>>
                                    <?php echo html_escape($lbl); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$M->options($f['ref'])): ?>
                            <small class="tqa-warn">لا توجد عناصر بعد في «<?php echo html_escape($f['ref']); ?>» — أضِفها أوّلًا.</small>
                        <?php endif; ?>

                    <?php elseif ($f['type'] === 'textarea' || $f['type'] === 'lines'): ?>

                        <textarea class="form-control" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                                  rows="4"><?php echo html_escape((string) $val); ?></textarea>

                    <?php elseif ($f['type'] === 'datetime'): ?>

                        <input class="form-control tq-ltr" dir="ltr" type="datetime-local"
                               id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                               value="<?php echo html_escape((string) $val); ?>">

                    <?php elseif (in_array($f['type'], array('number', 'seconds'), true)): ?>

                        <input class="form-control tq-ltr" dir="ltr" type="number" min="0" step="1"
                               id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                               value="<?php echo html_escape((string) $val); ?>">

                    <?php elseif ($f['type'] === 'money'): ?>

                        <div class="tqa-money">
                            <input class="form-control tq-ltr" dir="ltr" type="number" min="0" step="0.01"
                                   id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                                   value="<?php echo html_escape((string) $val); ?>">
                            <span class="tqa-money-unit">ر.س</span>
                        </div>

                    <?php else: ?>

                        <input class="form-control<?php echo !empty($f['ltr']) ? ' tq-ltr' : ''; ?>"
                               <?php echo !empty($f['ltr']) ? 'dir="ltr"' : ''; ?>
                               type="text" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                               value="<?php echo html_escape((string) $val); ?>"
                               <?php echo !empty($f['required']) ? 'required' : ''; ?>>

                    <?php endif; ?>

                    <?php if (!empty($f['hint'])): ?>
                        <small class="tqa-hint"><?php echo html_escape($f['hint']); ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            </div>

            <div class="tqa-actions">
                <button type="submit" class="btn btn-primary">حفظ</button>
                <a class="btn btn-secondary" href="<?php echo site_url('taqdar_admin/module/' . $mkey); ?>">إلغاء</a>
            </div>
        </form>
    </div>
</div>
