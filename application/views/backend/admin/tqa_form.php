<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * النموذج العام لكل وحدة موصوفة.
 *
 * الحقل يرسم حسب نوعه المعلن في `spec()`، فلا نموذج مكتوب لكل وحدة.
 * وكان يفتقر إلى توكن CSRF — والحماية مفعلة، فكل حفظ كان يرد 403.
 */
$M       = &get_instance()->taqdar_admin_model;
$is_edit = ($rid > 0 && $row);

$tools = '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/module/' . $mkey) . '">'
       . 'رجوع إلى القائمة</a>';
?>

<?php tqa_head(
    ($is_edit ? 'تعديل' : 'إضافة') . ' — ' . $spec['title'],
    $spec['lead'],
    isset($spec['icon']) ? $spec['icon'] : 'edit',
    $tools
); ?>

<form method="post" action="<?php echo site_url('taqdar_admin/save/' . $mkey . '/' . (int) $rid); ?>">
    <?php echo tq_csrf(); ?>

    <div class="tqa-card" style="margin-block-end:var(--tq-space-l)">
        <div class="tqa-grid tqa-grid--2">

        <?php foreach ($spec['fields'] as $name => $f):
            $val  = tqa_val($row, $name, $f);
            $id   = 'f_' . $name;
            /* الحقول الطويلة تأخذ العرض كله: فقرة في نصف عمود تكسر كل
               ثلاث كلمات فيصير تحرير سطر واحد قراءة عمود. */
            $wide = in_array($f['type'], array('textarea', 'lines'), true);
        ?>
            <div class="tqa-field" <?php echo $wide ? 'style="grid-column:1/-1"' : ''; ?>>
                <label class="tqa-field__label" for="<?php echo $id; ?>">
                    <?php echo html_escape($f['label']); ?>
                    <?php if (!empty($f['required'])): ?><span class="tqa-field__req">*</span><?php endif; ?>
                </label>

                <?php if ($f['type'] === 'bool'): ?>

                    <label class="tqa-switch">
                        <input type="checkbox" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                               value="1" <?php echo $val ? 'checked' : ''; ?>>
                        <span class="tqa-switch__track" aria-hidden="true"></span>
                        <span>نعم</span>
                    </label>

                <?php elseif ($f['type'] === 'enum'): ?>

                    <select class="tqa-select" id="<?php echo $id; ?>" name="<?php echo $name; ?>">
                        <?php foreach ($f['options'] as $k => $lbl): ?>
                            <option value="<?php echo html_escape($k); ?>"
                                <?php echo ((string) $val === (string) $k) ? 'selected' : ''; ?>>
                                <?php echo html_escape($lbl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($f['type'] === 'ref'):
                    $opts = $M->options($f['ref']);
                ?>

                    <select class="tqa-select" id="<?php echo $id; ?>" name="<?php echo $name; ?>">
                        <option value="0">— بلا تحديد —</option>
                        <?php foreach ($opts as $k => $lbl): ?>
                            <option value="<?php echo (int) $k; ?>"
                                <?php echo ((int) $val === (int) $k) ? 'selected' : ''; ?>>
                                <?php echo html_escape($lbl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$opts): ?>
                        <?php /* قائمة فارغة تقرأ عطلا: من يفتح المنتقي فلا يجد
                                 فيه شيئا يظن الشاشة معطوبة، والصواب أن الوحدة
                                 التي يشير إليها لم تملأ بعد. */ ?>
                        <span class="tqa-field__hint" style="color:var(--tq-amber)">
                            لا عناصر في هذه القائمة بعد — املأ وحدتها أولا ثم عد إلى هنا.
                        </span>
                    <?php endif; ?>

                <?php elseif ($f['type'] === 'textarea' || $f['type'] === 'lines'): ?>

                    <textarea class="tqa-textarea" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                              rows="5"><?php echo html_escape((string) $val); ?></textarea>
                    <?php if ($f['type'] === 'lines'): ?>
                        <span class="tqa-field__hint">سطر واحد لكل بند.</span>
                    <?php endif; ?>

                <?php elseif ($f['type'] === 'datetime'): ?>

                    <input class="tqa-input tqa-input--ltr" dir="ltr" type="datetime-local"
                           id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                           value="<?php echo html_escape((string) $val); ?>">

                <?php elseif (in_array($f['type'], array('number', 'seconds'), true)): ?>

                    <input class="tqa-input tqa-input--ltr" dir="ltr" type="number" min="0" step="1"
                           id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                           value="<?php echo html_escape((string) $val); ?>">

                <?php elseif ($f['type'] === 'money'): ?>

                    <div style="display:flex;align-items:center;gap:var(--tq-space-s)">
                        <input class="tqa-input tqa-input--ltr" dir="ltr" type="number" min="0" step="0.01"
                               id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                               value="<?php echo html_escape((string) $val); ?>">
                        <span style="color:var(--tq-text2);font:var(--tq-type-caption);flex:none">ر.س</span>
                    </div>
                    <span class="tqa-field__hint">يدخل بالريال ويخزن بالهللات — بلا فقد كسور عند الجمع.</span>

                <?php else: ?>

                    <input class="tqa-input<?php echo !empty($f['ltr']) ? ' tqa-input--ltr' : ''; ?>"
                           <?php echo !empty($f['ltr']) ? 'dir="ltr"' : ''; ?>
                           type="text" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                           value="<?php echo html_escape((string) $val); ?>"
                           <?php echo !empty($f['required']) ? 'required' : ''; ?>>

                <?php endif; ?>

                <?php if (!empty($f['hint'])): ?>
                    <span class="tqa-field__hint"><?php echo html_escape($f['hint']); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        </div>
    </div>

    <div style="display:flex;gap:var(--tq-space-s);flex-wrap:wrap">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo $is_edit ? 'احفظ التعديل' : 'أضف'; ?>
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/module/' . $mkey); ?>">إلغاء</a>
    </div>
</form>
