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
       . t('رجوع إلى القائمة</a>');

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
    ($is_edit ? t('تعديل') : t('إضافة')) . ' — ' . $spec['title'],
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

<?php /* `enctype` شرط حقل الملف — TQ-PLAN-IMG. وبلاها يصل `$_FILES`
         فارغا ويصل اسم الملف نصا في `$_POST`، فيحفظ الصف بلا صورة ولا
         خطأ يظهر: المسؤول يرفع ويحفظ ويقرأ «تم الحفظ» ولا يرى شيئا. */ ?>
<form method="post" action="<?php echo site_url('taqdar_admin/save/' . $mkey . '/' . (int) $rid); ?>"
      enctype="multipart/form-data"
      data-tqa-form="<?php echo html_escape($mkey); ?>"
      <?php /* TQ-FORM-DIRTY — الحارس يعلم أن في النموذج تعديلا لم يحفظ،
               فيقوله في الشريط ويسأل عند المغادرة. نموذج الباقة ستة عشر
               حقلا، ومن عدلها ثم ضغط بندا في الشريط الجانبي كان يفقدها
               كلها بلا سؤال ولا أثر. */ ?>
      data-tqa-dirty="1">
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

            /* TQ-REQ-HIDDEN — `required` على حقل شرطي يعطل النموذج كله.
               المتصفح يمنع الإرسال حتى يملأ الحقل المطلوب، ثم يحاول أن
               يمرر إليه ليقول أين هو — فيصطدم بحقل `hidden`: يرمي
               «An invalid form control is not focusable» في وحدة التطوير
               ولا يعرض شيئا في الصفحة. فيضغط المسؤول «احفظ» ولا يقع شيء
               إطلاقا: لا حفظ ولا رسالة ولا حقل محدد.

               فالإلزام يفرض على ما يظهر دائما وحده، وما يخفيه اختيار آخر
               يفحص في النموذج حيث يعرف السياق. */
            $req = !empty($f['required']) && $when_on === '';
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
                        <span><?php echo t('نعم'); ?></span>
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

                <?php elseif ($f['type'] === 'file'):
                    /* ثلاثة أشياء في مكان واحد: ما هو محفوظ الآن، وزر
                       يختار بديلا، ومربع يمحو. والمحفوظ **يعرض صورة لا
                       اسم ملف**: `plan-31-a3f9c1d2.webp` لا يقول أي
                       صورة هي، والمسؤول يفتح الشاشة ليرى ما اختار. */
                    $cur = trim((string) $val);
                    $cur_src = '';
                    if ($cur !== '') {
                        $cur_src = (strpos($cur, '/') !== false)
                                 ? base_url(ltrim($cur, '/'))
                                 : base_url('assets/taqdar/site/img/' . $cur . '.webp');
                    }
                ?>

                    <?php /* TQA-FILE — الوسم هنا هو **الحال بلا جافاسكربت**:
                             الصورة المحفوظة معروضة، ومربع المحو ظاهر، والحقل
                             يرفع. و[tqa-file.js] يستوعبه فيبني عليه صندوق
                             السحب والمعاينة وزري «احذف/تراجع» — فمن تعثر عنده
                             الملف يرى حقلا يعمل، ومن وصله يرى الصندوق كاملا. */ ?>
                    <div class="tqa-filefield">
                        <?php if ($cur_src !== ''): ?>
                            <div class="tqa-filefield__now">
                                <img src="<?php echo html_escape($cur_src); ?>" alt=""
                                     loading="lazy" decoding="async" data-tqa-file-cur>
                            </div>
                        <?php endif; ?>

                        <div class="tqa-filefield__ctl">
                            <input type="file" id="<?php echo $id; ?>"
                                   name="<?php echo $name; ?>"
                                   accept="<?php echo html_escape(isset($f['accept']) ? $f['accept'] : 'image/*'); ?>"
                                   <?php if (!empty($f['max_mb'])): ?>data-tqa-max="<?php echo (float) $f['max_mb']; ?>"<?php endif; ?>>

                            <?php if ($cur !== ''): ?>
                                <?php /* المحو صريح: حفظ بلا اختيار ملف **لا يمس**
                                         الصورة (انظر `case 'file'` في النموذج)،
                                         فمن أراد إزالتها يقولها. */ ?>
                                <label class="tqa-check">
                                    <input type="checkbox" name="<?php echo $name; ?>__clear" value="1">
                                    <span><?php echo t('احذف الصورة الحالية'); ?></span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($f['type'] === 'doc'):
                    /* TQ-BOOK-FILE — مستند لا صورة، فلا معاينة تعرض.
                       والمحفوظ يقال باسمه وحجمه ورابط يفتحه: اسم بصمة
                       (`books-14-a3f9c1d2.pdf`) لا يقول أي ملف هو،
                       والمسؤول يفتح الشاشة ليتأكد أنه رفع الصحيح. */
                    $cur     = trim((string) $val);
                    $cur_src = $cur !== '' ? base_url(ltrim($cur, '/')) : '';
                    $cur_abs = $cur !== '' ? FCPATH . ltrim($cur, '/') : '';
                    $cur_mb  = ($cur_abs !== '' && is_file($cur_abs))
                             ? round(filesize($cur_abs) / 1048576, 1) : 0;
                ?>

                    <div class="tqa-filefield tqa-filefield--doc">
                        <?php if ($cur_src !== ''): ?>
                            <p class="tqa-hint" style="margin-block-end:var(--tq-space-s)">
                                <a href="<?php echo html_escape($cur_src); ?>" target="_blank" rel="noopener">
                                    <?php echo html_escape(basename($cur)); ?></a>
                                <?php if ($cur_mb > 0): ?>
                                    — <span class="tq-ltr"><?php echo $cur_mb; ?></span> <?php echo t('ميغابايت'); ?>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p class="tqa-hint" style="margin-block-end:var(--tq-space-s)">
                                <?php echo t('لا ملف مرفوع بعد.'); ?>
                            </p>
                        <?php endif; ?>

                        <div class="tqa-filefield__ctl">
                            <input type="file" id="<?php echo $id; ?>"
                                   name="<?php echo $name; ?>"
                                   accept="<?php echo html_escape(isset($f['accept']) ? $f['accept'] : '.pdf'); ?>"
                                   <?php if (!empty($f['max_mb'])): ?>data-tqa-max="<?php echo (float) $f['max_mb']; ?>"<?php endif; ?>>

                            <?php if ($cur !== ''): ?>
                                <?php /* المحو صريح: حفظ بلا اختيار ملف **لا يمس**
                                         الملف، فمن أراد إزالته يقولها. */ ?>
                                <label class="tqa-check">
                                    <input type="checkbox" name="<?php echo $name; ?>__clear" value="1">
                                    <span><?php echo t('احذف الملف الحالي'); ?></span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($f['type'] === 'pick'):
                    $opts = $M->options($f['ref']);
                    $prev = (!empty($f['preview']) && $f['preview'] === 'site_image');
                ?>

                    <select class="tqa-select" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                            data-tqa-field="<?php echo html_escape($name); ?>"
                            <?php echo $prev ? 'data-tqa-imgpick="1"' : ''; ?>>
                        <option value=""><?php echo t('— بلا تحديد —'); ?></option>
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
                                <?php echo t('لا عناصر في هذه القائمة بعد — املأ وحدتها أولا ثم عد إلى هنا.'); ?>
                            </span>
                        <?php else: ?>
                            <div class="tqa-picks__acts">
                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        data-tqa-picks-all><?php echo t('حدد الكل'); ?></button>
                                <button type="button" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        data-tqa-picks-none><?php echo t('امسح التحديد'); ?></button>
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
                            <option value="0"><?php echo t('— بلا تحديد —'); ?></option>
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
                            data-tqa-field="<?php echo html_escape($name); ?>"
                            <?php echo $req ? 'required' : ''; ?>>
                        <?php /* الخيار النائب يفرغ قيمته حين يكون الحقل مطلوبا:
                                 `value="0"` قيمة غير فارغة، و`required` معها
                                 لا يمنع شيئا — فيمر الحفظ بمرجع صفر. */ ?>
                        <option value="<?php echo $req ? '' : '0'; ?>"><?php echo t('— بلا تحديد —'); ?></option>
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
                            <?php echo t('لا عناصر في هذه القائمة بعد — املأ وحدتها أولا ثم عد إلى هنا.'); ?>
                        </span>
                    <?php endif; ?>

                <?php elseif ($f['type'] === 'textarea' || $f['type'] === 'lines'): ?>

                    <textarea class="tqa-textarea" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                              rows="5"
                              <?php echo $req ? 'required' : ''; ?>><?php echo html_escape((string) $val); ?></textarea>
                    <?php if ($f['type'] === 'lines'): ?>
                        <span class="tqa-field__hint"><?php echo t('سطر واحد لكل بند.'); ?></span>
                    <?php endif; ?>

                <?php elseif ($f['type'] === 'datetime'): ?>

                    <input class="tqa-input tqa-input--ltr" dir="ltr" type="datetime-local"
                           id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                           value="<?php echo html_escape((string) $val); ?>">

                <?php elseif (in_array($f['type'], array('number', 'seconds'), true)): ?>

                    <?php /* TQ-NULLNUM — والحقل `nullable` يعرض فارغا حين
                             تكون القيمة `NULL`، و`placeholder` يقول ما
                             يقع حينها. و`(string) null` تساوي `''`
                             أصلا، لكن الصفر الصريح يجب أن يبقى ظاهرا:
                             هو قرار كتبه أحدهم، لا فراغا. */ ?>
                    <input class="tqa-input tqa-input--ltr" dir="ltr" type="number" min="0" step="1"
                           id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                           value="<?php echo ($val === null) ? '' : html_escape((string) $val); ?>"
                           <?php if (!empty($f['placeholder'])): ?>placeholder="<?php echo html_escape($f['placeholder']); ?>"<?php endif; ?>
                           <?php echo $req ? 'required' : ''; ?>>

                <?php elseif ($f['type'] === 'percent'): ?>

                    <?php /* النسبة ومتممها في سطر واحد. والمتمم يحسب في المتصفح
                             وهو يكتب لا بعد الحفظ: من يكتب ١٥ يجب أن يرى ٨٥
                             فورا، وإلا حفظ وهو يظن أنه ضبط نسبة المنصة.
                             والمخزن رقم واحد — فلا تفترق النسبتان أبدا. */ ?>
                    <div class="tqa-field__row">
                        <input class="tqa-input tqa-input--ltr" dir="ltr" type="number"
                               min="0" max="100" step="0.01"
                               id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                               data-tqa-percent
                               <?php if (!empty($f['mirror'])): ?>
                                   data-tqa-percent-mirror="<?php echo html_escape($id); ?>-mirror"
                               <?php endif; ?>
                               <?php if (isset($f['placeholder'])): ?>
                                   placeholder="<?php echo html_escape((string) $f['placeholder']); ?>"
                               <?php endif; ?>
                               value="<?php echo ($val === null || $val === '') ? '' : html_escape(rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.')); ?>">
                        <span class="tqa-field__unit">%</span>
                        <?php if (!empty($f['mirror'])): ?>
                            <span class="tqa-pill" id="<?php echo $id; ?>-mirror"><?php echo html_escape($f['mirror']); ?> —</span>
                        <?php endif; ?>
                    </div>

                <?php elseif ($f['type'] === 'money'): ?>

                    <div class="tqa-field__row">
                        <input class="tqa-input tqa-input--ltr" dir="ltr" type="number" min="0" step="0.01"
                               id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                               value="<?php echo html_escape((string) $val); ?>"
                               <?php echo $req ? 'required' : ''; ?>>
                        <span class="tqa-field__unit"><?php echo t('ر.س'); ?></span>
                    </div>
                    <span class="tqa-field__hint"><?php echo t('يدخل بالريال ويخزن بالهللات — بلا فقد كسور عند الجمع.'); ?></span>

                <?php else: ?>

                    <input class="tqa-input<?php echo !empty($f['ltr']) ? ' tqa-input--ltr' : ''; ?>"
                           <?php echo !empty($f['ltr']) ? 'dir="ltr"' : ''; ?>
                           type="text" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                           value="<?php echo html_escape((string) $val); ?>"
                           <?php echo $req ? 'required' : ''; ?>>

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

    <?php /* TQ-SAVE-BELOW — الشريط يلتصق بأسفل الشاشة.
             نموذج الباقة ستة عشر حقلا، وستة عشر حقلا في شبكة عمودين تعني
             شاشة ونصفا: زر «احفظ» في القاع يكلف تمريرة كاملة عن كل تعديل
             ولو كان حرفا في الحقل الأول. وأثقل منه أن من لا يرى زر حفظ
             لا يعرف أن الشاشة تحفظ أصلا، فينتقل ويعود فيجد ما كتبه ذهب. */ ?>
    <div class="tqa-formbar">
        <button type="submit" class="tqa-btn tqa-btn--primary">
            <?php echo tq_icon('check', 16); ?>
            <span><?php echo $is_edit ? t('احفظ التعديل') : t('أضف'); ?></span>
        </button>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/module/' . $mkey); ?>"><?php echo t('إلغاء'); ?></a>
        <span class="tqa-formbar__dirty"><?php echo t('فيه تعديل لم يحفظ'); ?></span>
    </div>
</form>

<?php include 'tqa_formlogic_js.php'; ?>
<?php if (!empty($spec['form_js'])): ?>
    <?php include $spec['form_js'] . '.php'; ?>
<?php endif; ?>
