<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * النموذج العام لكل وحدة موصوفة.
 *
 * الحقل يرسم حسب نوعه المعلن في `spec()`، فلا نموذج مكتوب لكل وحدة.
 * وكان يفتقر إلى توكن CSRF — والحماية مفعلة، فكل حفظ كان يرد 403.
 *
 * وثلاثة أشياء أضيفت حين طالت الوحدات:
 *
 * ١ · **`section`** على أول حقل في كل مجموعة يطبع عنوانا فاصلا. نموذج
 *     الباقة ستة عشر حقلا، وستة عشر حقلا في شبكة بلا فواصل يقرأ قائمة
 *     لا نموذجا: أين ينتهي التسعير ويبدأ المحتوى؟
 *
 * ٢ · **`show_when`** يخفي حقلا لا يعنيه اختيار آخر. وحقل «الصفوف»
 *     المعروض بينما النطاق «مادة واحدة» يدعو إلى ملئه ثم يهمل ما ملئ —
 *     والحالة الابتدائية تحسب هنا من القيمة المحفوظة لا في المتصفح،
 *     فتصح الشاشة قبل أن يصل السكربت وإن لم يصل.
 *
 * ٣ · **`refswitch`** عمود واحد يفسر حسب حقل آخر (`plans.scope_id`:
 *     مادة أم مسار؟). فمنتق لكل تفسير، والمعطل لا يرسل — فلا يكتب في
 *     العمود رقم مسار وقد اختير النطاق مادة.
 */
$M       = &get_instance()->taqdar_admin_model;
$is_edit = ($rid > 0 && $row);

$tools = '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('taqdar_admin/module/' . $mkey) . '">'
       . 'رجوع إلى القائمة</a>';

/**
 * القيمة الحالية لحقل شرطي — من الصف المحفوظ، أو من افتراضه في الوصف.
 * وهي ما يقرر أي الحقول تظهر عند أول رسم.
 */
$tqa_now = function ($field_name) use ($row, $spec) {
    if ($row && array_key_exists($field_name, $row)) return (string) $row[$field_name];
    return isset($spec['fields'][$field_name]['default'])
        ? (string) $spec['fields'][$field_name]['default'] : '';
};
?>

<?php tqa_head(
    ($is_edit ? 'تعديل' : 'إضافة') . ' — ' . $spec['title'],
    $spec['lead'],
    isset($spec['icon']) ? $spec['icon'] : 'edit',
    $tools
); ?>

<?php if (!empty($spec['note'])): ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
        <span><?php echo html_escape($spec['note']); ?></span>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo site_url('taqdar_admin/save/' . $mkey . '/' . (int) $rid); ?>"
      data-tqa-form="<?php echo html_escape($mkey); ?>">
    <?php echo tq_csrf(); ?>

    <div class="tqa-card" style="margin-block-end:var(--tq-space-l)">
        <div class="tqa-grid tqa-grid--2">

        <?php foreach ($spec['fields'] as $name => $f):
            $val  = tqa_val($row, $name, $f);
            $id   = 'f_' . $name;
            /* الحقول الطويلة تأخذ العرض كله: فقرة في نصف عمود تكسر كل
               ثلاث كلمات فيصير تحرير سطر واحد قراءة عمود. */
            $wide = in_array($f['type'], array('textarea', 'lines', 'multiref'), true);

            /* الشرط: على أي حقل يعلق، وبأي قيم يظهر، وهل يظهر الآن. */
            $when_on   = '';
            $when_vals = array();
            $visible   = true;
            if (!empty($f['show_when'])) {
                $when_on   = key($f['show_when']);
                $when_vals = (array) $f['show_when'][$when_on];
                $visible   = in_array($tqa_now($when_on), $when_vals, true);
            }
        ?>
            <?php if (!empty($f['section'])): ?>
                <p class="tqa-formsec" style="grid-column:1/-1"><?php echo html_escape($f['section']); ?></p>
            <?php endif; ?>

            <div class="tqa-field"
                 <?php if ($when_on !== ''): ?>
                     data-tqa-when="<?php echo html_escape($when_on); ?>"
                     data-tqa-when-val="<?php echo html_escape(implode(',', $when_vals)); ?>"
                     <?php echo $visible ? '' : 'hidden'; ?>
                 <?php endif; ?>
                 style="<?php echo $wide ? 'grid-column:1/-1' : ''; ?>">
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

                    <select class="tqa-select" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                            data-tqa-field="<?php echo html_escape($name); ?>">
                        <?php foreach ($f['options'] as $k => $lbl): ?>
                            <option value="<?php echo html_escape($k); ?>"
                                <?php echo ((string) $val === (string) $k) ? 'selected' : ''; ?>>
                                <?php echo html_escape($lbl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($f['type'] === 'pick'):
                    $opts = $M->options($f['ref']);
                    $prev = (!empty($f['preview']) && $f['preview'] === 'site_image');
                ?>

                    <select class="tqa-select" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                            data-tqa-field="<?php echo html_escape($name); ?>"
                            <?php echo $prev ? 'data-tqa-imgpick="1"' : ''; ?>>
                        <option value="">— بلا تحديد —</option>
                        <?php foreach ($opts as $k => $lbl): ?>
                            <option value="<?php echo html_escape($k); ?>"
                                <?php echo ((string) $val === (string) $k) ? 'selected' : ''; ?>>
                                <?php echo html_escape($lbl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($prev): ?>
                        <?php /* أسماء الصور لا تقرأ صورا: `bundle-plus-middle`
                                 و`path-middle` يتشابهان في السطر ويختلفان في
                                 البطاقة. فالمعاينة هنا هي الاختيار الفعلي. */ ?>
                        <div class="tqa-checker" style="margin-block-start:var(--tq-space-s)"
                             data-tqa-imgpreview="<?php echo $id; ?>"
                             <?php echo ((string) $val === '') ? 'hidden' : ''; ?>>
                            <img src="<?php echo (string) $val !== ''
                                ? html_escape(base_url('assets/taqdar/site/img/' . $val . '.webp')) : ''; ?>"
                                 alt="" loading="lazy" decoding="async">
                        </div>
                    <?php endif; ?>

                <?php elseif ($f['type'] === 'multiref'):
                    $opts = $M->options($f['ref']);
                    $on   = array_filter(array_map('intval', explode(',', (string) $val)));
                ?>

                    <div class="tqa-picks" data-tqa-picks="<?php echo html_escape($name); ?>">
                        <?php if (!$opts): ?>
                            <span class="tqa-field__hint" style="color:var(--tq-amber)">
                                لا عناصر في هذه القائمة بعد — املأ وحدتها أولا ثم عد إلى هنا.
                            </span>
                        <?php else: ?>
                            <div class="tqa-picks__acts">
                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        data-tqa-picks-all>حدد الكل</button>
                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        data-tqa-picks-none>امسح التحديد</button>
                                <span class="tqa-picks__count" data-tqa-picks-count></span>
                            </div>
                            <div class="tqa-picks__grid">
                                <?php foreach ($opts as $k => $lbl): ?>
                                    <label class="tqa-pick">
                                        <input type="checkbox" name="<?php echo $name; ?>[]"
                                               value="<?php echo (int) $k; ?>"
                                            <?php echo in_array((int) $k, $on, true) ? 'checked' : ''; ?>>
                                        <span><?php echo html_escape($lbl); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php /* حقل فارغ يحمل الاسم نفسه: بلا مربع محدد لا
                                 يرسل المتصفح المفتاح أصلا، فيقرأ الحفظ «لم
                                 يتغير» بينما المسؤول مسح التحديد قصدا. */ ?>
                        <input type="hidden" name="<?php echo $name; ?>[]" value="">
                    </div>

                <?php elseif ($f['type'] === 'refswitch'):
                    $on_field = isset($f['on']) ? $f['on'] : '';
                    $on_now   = $tqa_now($on_field);
                ?>

                    <?php foreach ($f['refs'] as $case => $ref):
                        $opts = $M->options($ref);
                        $act  = ((string) $on_now === (string) $case);
                    ?>
                        <select class="tqa-select" name="<?php echo $name; ?>"
                                id="<?php echo $id . '_' . $case; ?>"
                                data-tqa-case="<?php echo html_escape($on_field . ':' . $case); ?>"
                                <?php echo $act ? '' : 'disabled hidden'; ?>>
                            <option value="0">— بلا تحديد —</option>
                            <?php foreach ($opts as $k => $lbl): ?>
                                <option value="<?php echo (int) $k; ?>"
                                    <?php echo ((int) $val === (int) $k) ? 'selected' : ''; ?>>
                                    <?php echo html_escape($lbl); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endforeach; ?>

                <?php elseif ($f['type'] === 'ref'):
                    $opts = $M->options($f['ref']);
                ?>

                    <select class="tqa-select" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                            data-tqa-field="<?php echo html_escape($name); ?>">
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

                <?php elseif ($f['type'] === 'percent'): ?>

                    <?php /* النسبة ومتممها في سطر واحد. والمتمم يحسب في المتصفح
                             وهو يكتب لا بعد الحفظ: من يكتب ١٥ يجب أن يرى ٨٥
                             فورا، وإلا حفظ وهو يظن أنه ضبط نسبة المنصة.
                             والمخزن رقم واحد — فلا تفترق النسبتان أبدا. */ ?>
                    <div style="display:flex;align-items:center;gap:var(--tq-space-s);flex-wrap:wrap">
                        <input class="tqa-input tqa-input--ltr" dir="ltr" type="number"
                               min="0" max="100" step="0.01" style="max-inline-size:9rem"
                               id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                               data-tqa-percent
                               <?php if (!empty($f['mirror'])): ?>
                                   data-tqa-percent-mirror="<?php echo html_escape($id); ?>-mirror"
                               <?php endif; ?>
                               <?php if (isset($f['placeholder'])): ?>
                                   placeholder="<?php echo html_escape((string) $f['placeholder']); ?>"
                               <?php endif; ?>
                               value="<?php echo ($val === null || $val === '') ? '' : html_escape(rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.')); ?>">
                        <span style="color:var(--tq-text2);font:var(--tq-type-caption);flex:none">%</span>
                        <?php if (!empty($f['mirror'])): ?>
                            <span class="tqa-pill" id="<?php echo $id; ?>-mirror"
                                  style="flex:none"><?php echo html_escape($f['mirror']); ?> —</span>
                        <?php endif; ?>
                    </div>

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

    <?php /* لوحة تخص الوحدة تعرض ما لا يعرفه النموذج العام — مثل ما
             تفتحه الباقة فعلا. وهي داخل النموذج كي يصلها السكربت
             ويحدثها مع الاختيار قبل الحفظ. */ ?>
    <?php /* لوحة واحدة أو عدة: الوحدة قد تحتاج أن تجيب سؤالين مستقلين
             قبل الحفظ (ماذا تفتح الباقة؟ ولمن يذهب مالها؟)، وحشرهما في
             ملف واحد يجعل تعديل أحدهما يمس الآخر. */ ?>
    <?php if (!empty($spec['form_extra'])): ?>
        <?php foreach ((array) $spec['form_extra'] as $extra_view): ?>
            <?php $this->load->view('backend/admin/' . $extra_view, array(
                'mkey' => $mkey, 'spec' => $spec, 'row' => $row, 'rid' => $rid,
            )); ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="display:flex;gap:var(--tq-space-s);flex-wrap:wrap">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo $is_edit ? 'احفظ التعديل' : 'أضف'; ?>
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/module/' . $mkey); ?>">إلغاء</a>
    </div>
</form>

<?php include 'tqa_formlogic_js.php'; ?>
<?php if (!empty($spec['form_js'])): ?>
    <?php include $spec['form_js'] . '.php'; ?>
<?php endif; ?>
