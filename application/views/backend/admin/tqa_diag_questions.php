<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * أسئلة الاختبار التشخيصي.
 *
 * شاشة واحدة لا ثلاث: الإضافة والتحرير والحذف كلها هنا. والسبب أن السؤال
 * لا يقرأ وحده — يقرأ بجوار إخوته في مستواه: أربعة أسئلة «مبتدئ» متقاربة
 * الصعوبة تقيس، وأربعة متفرقة لا تقيس. فالمسؤول يحتاج أن يرى المستوى
 * كاملا وهو يكتب فيه، لا أن ينتقل بين شاشة قائمة وشاشة نموذج.
 *
 * والتحرير في `<details>` مطوي بجوار كل سؤال: يفتح بلا سكربت، ويغلقه
 * المتصفح نفسه، ولا يخرج المسؤول من موضعه في الصفحة.
 *
 * والخيارات ستة حقول ثابتة لا مكرر بجافاسكربت: الفارغ يسقط في النموذج
 * (`save_question`)، والحد الأعلى ستة هناك أيضا. فنموذج يعمل بلا سطر
 * جافاسكربت واحد — ولا يسقط صامتا متى تعثر ملف.
 */

$exam_id = (int) $exam['id'];
$total   = 0;
foreach ($by_level as $rows) $total += count($rows);

/** الخيارات ستة دائما في النموذج، وما جاء أقل يكمل فارغا. */
$slots = function ($opts) {
    $out = array();
    for ($i = 0; $i < 6; $i++) $out[$i] = isset($opts[$i]) ? $opts[$i] : '';
    return $out;
};

/** نص الإجابة الصحيحة من صف السؤال — وهو أول عنصر في `correct_answers`. */
$correct_of = function ($q) {
    $c = json_decode((string) (isset($q['correct_answers']) ? $q['correct_answers'] : ''), true);
    return (is_array($c) && count($c)) ? (string) reset($c) : '';
};
?>

<style>
/* موضعي لهذه الشاشة وحدها، وكل قيمة من التوكنات — لا لون ولا مسافة مباشرة. */
.tqd-opt      { display: flex; align-items: center; gap: var(--tq-space-s); margin-block-end: var(--tq-space-s); }
.tqd-opt input[type="radio"] { flex: 0 0 auto; inline-size: 18px; block-size: 18px; accent-color: var(--tq-primary, #0d6b5f); }
.tqd-opt .tqa-input          { flex: 1 1 auto; }
.tqd-q        { border: 1px solid var(--tq-line); border-radius: var(--tqa-radius); padding: var(--tq-space-m);
                margin-block-end: var(--tq-space-m); background: var(--tq-surface); }
.tqd-q__top   { display: flex; align-items: flex-start; gap: var(--tq-space-m); justify-content: space-between; }
.tqd-q__opts  { margin: var(--tq-space-s) 0 0; padding-inline-start: var(--tq-space-l); font: var(--tq-type-caption); color: var(--tq-text2); }
.tqd-q__opts li { margin-block-end: 2px; }
.tqd-q__opts li.is-right { color: var(--tq-navy); font-weight: 700; }
.tqd-edit     { margin-block-start: var(--tq-space-m); padding-block-start: var(--tq-space-m); border-block-start: 1px dashed var(--tq-line); }
.tqd-edit > summary { cursor: pointer; font: var(--tq-type-caption); color: var(--tq-text2); }
.tqd-lvl__head { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m); }
</style>

<div class="tqa-head">
    <div>
        <h1>أسئلة: <?php echo html_escape($exam['title']); ?></h1>
        <p>لكل سؤال مستواه، والمستوى هو ما يحسب به موضع الطالب — لا مجموع الإجابات.</p>
    </div>
    <div class="tqa-actions">
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/form/diag_exams/' . $exam_id); ?>">
            <?php echo tq_icon('edit', 16); ?> إعدادات الاختبار
        </a>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo site_url('taqdar_admin/module/diag_exams'); ?>">رجوع</a>
    </div>
</div>

<?php /* ── الجهوزية: ما يمنع هذا الاختبار من العمل، صراحة ──────────── */ ?>
<?php if (!$readiness['ok']): ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
        <span>
            <strong><?php echo ((string) $exam['status'] === 'published')
                ? 'هذا الاختبار منشور وناقص — وطلاب هذا الصف محبوسون عنده:'
                : 'ينقص هذا الاختبار قبل أن ينشر:'; ?></strong>
            <?php echo html_escape(implode(' ', $readiness['why'])); ?>
        </span>
    </div>
<?php elseif ((string) $exam['status'] !== 'published'): ?>
    <div class="tqa-note" style="margin-block-end:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('check-badge', 18); ?></span>
        <span>
            الاختبار مكتمل ولم ينشر بعد — فلا يعرض على أحد ولا يحبس أحدا.
            <a href="<?php echo site_url('taqdar_admin/form/diag_exams/' . $exam_id); ?>">انشره من إعداداته</a>.
        </span>
    </div>
<?php endif; ?>

<?php /* ── ثلاثة أرقام: كم سؤالا في كل مستوى، وكم طالبا خرج به ──────── */ ?>
<div class="tqa-grid tqa-grid--3" style="margin-block-end:var(--tq-space-xl)">
    <?php
    $tones = array('beginner' => 'tqa-peach', 'intermediate' => 'tqa-sky', 'advanced' => 'tqa-mint');
    foreach ($levels as $key => $meta):
        $n    = count($by_level[$key]);
        $went = (int) (isset($dist[$key]) ? $dist[$key] : 0);
    ?>
        <div class="tqa-stat">
            <div class="tqa-stat__top">
                <span class="tqa-stat__label"><?php echo html_escape($meta['label']); ?></span>
                <span class="tqa-stat__icon <?php echo $tones[$key]; ?>"><?php echo tq_icon('help', 17); ?></span>
            </div>
            <span class="tqa-stat__value"><?php echo $n; ?></span>
            <span class="tqa-stat__hint">
                <?php echo $n > 0 ? 'سؤالا' : 'لا أسئلة في هذا المستوى'; ?>
                <?php if ((int) $exam['plan_' . $key] > 0): ?>
                    — <?php echo html_escape(isset($plans[$exam['plan_' . $key]]) ? $plans[$exam['plan_' . $key]] : 'باقة'); ?>
                <?php elseif ($n > 0): ?>
                    — <span style="color:var(--tq-danger)">بلا باقة مربوطة</span>
                <?php endif; ?>
                <?php if ($went > 0): ?>
                    · خرج به <?php echo $went; ?> طالبا
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<?php /* ── إضافة سؤال ──────────────────────────────────────────────── */ ?>
<div class="tqa-card" style="margin-block-end:var(--tq-space-xl)">
    <div class="tqa-card__head"><h4 class="header-title">أضف سؤالا</h4></div>
    <div class="tqa-card__body">
        <form method="post" action="<?php echo site_url('taqdar_admin/diag_question_save/' . $exam_id); ?>">
            <?php echo tq_csrf(); ?>

            <div class="tqa-fieldgrid">
                <div class="tqa-field tqa-field--full">
                    <label class="tqa-field__label" for="tqd-title">نص السؤال <span class="tqa-field__req">*</span></label>
                    <textarea class="tqa-textarea" id="tqd-title" name="title" rows="2" required></textarea>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="tqd-level">المستوى <span class="tqa-field__req">*</span></label>
                    <select class="tqa-select" id="tqd-level" name="level">
                        <?php foreach ($levels as $key => $meta): ?>
                            <option value="<?php echo html_escape($key); ?>"><?php echo html_escape($meta['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="tqa-field__hint">مستوى السؤال هو ما يقاس به، لا صعوبته في نظر كاتبه.</span>
                </div>

                <div class="tqa-field">
                    <label class="tqa-field__label" for="tqd-order">الترتيب</label>
                    <input class="tqa-input" id="tqd-order" name="order" type="number" value="0">
                    <span class="tqa-field__hint">صفر = في آخر المستوى.</span>
                </div>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label">الخيارات والإجابة الصحيحة <span class="tqa-field__req">*</span></label>
                <span class="tqa-field__hint" style="margin-block-end:var(--tq-space-s)">
                    خياران على الأقل وستة على الأكثر. علم الدائرة أمام الصحيح، والفارغ يهمل.
                </span>
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="tqd-opt">
                        <input type="radio" name="correct" value="<?php echo $i; ?>"
                               <?php echo $i === 0 ? 'checked' : ''; ?>
                               aria-label="الخيار <?php echo $i + 1; ?> هو الصحيح">
                        <input class="tqa-input" type="text" name="options[<?php echo $i; ?>]"
                               placeholder="الخيار <?php echo $i + 1; ?><?php echo $i < 2 ? '' : ' (اختياري)'; ?>">
                    </div>
                <?php endfor; ?>
            </div>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--primary"><?php echo tq_icon('plus', 16); ?> أضف السؤال</button>
            </div>
        </form>
    </div>
</div>

<?php /* ── الأسئلة مرتبة بمستوياتها ─────────────────────────────────── */ ?>
<?php if ($total < 1): ?>

    <?php tqa_empty(
        'لا أسئلة بعد',
        'ابدأ بأسئلة المستوى المبتدئ، ثم اصعد. والتدرج الصاعد هو ما يبني ثقة الطالب قبل أن يقيس حده.',
        '', '', 'help'
    ); ?>

<?php else: ?>

    <?php foreach ($levels as $key => $meta):
        $rows = $by_level[$key];
    ?>
        <div class="tqa-card" style="margin-block-end:var(--tq-space-xl)">
            <div class="tqa-card__head">
                <div class="tqd-lvl__head" style="inline-size:100%">
                    <h4 class="header-title"><?php echo html_escape($meta['label']); ?></h4>
                    <span class="tqa-badge tqa-badge--<?php echo count($rows) ? 'ok' : 'muted'; ?>">
                        <?php echo count($rows); ?> سؤالا
                    </span>
                </div>
            </div>
            <div class="tqa-card__body">

                <?php if (!$rows): ?>
                    <p class="tqa-dim">
                        لا سؤال في هذا المستوى — ويتخطى في الحساب، فلا يحبس أحدا.
                        وحين تضيف إليه أسئلة لا تنس ربط باقته.
                    </p>
                <?php endif; ?>

                <?php foreach ($rows as $q):
                    $right = $correct_of($q);
                    $vals  = $slots($q['options']);
                ?>
                    <div class="tqd-q">
                        <div class="tqd-q__top">
                            <div>
                                <strong><?php echo html_escape($q['title']); ?></strong>
                                <ol class="tqd-q__opts">
                                    <?php foreach ($q['options'] as $o): ?>
                                        <li class="<?php echo ((string) $o === $right) ? 'is-right' : ''; ?>">
                                            <?php echo html_escape($o); ?>
                                            <?php if ((string) $o === $right): ?>
                                                <span class="tqa-badge tqa-badge--ok">الصحيح</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>

                            <?php /* الحذف نموذج لا رابط: رابط GET يحذف ينفذ بمجرد جلبه. */ ?>
                            <form method="post" style="margin:0"
                                  action="<?php echo site_url('taqdar_admin/diag_question_delete/' . $exam_id . '/' . (int) $q['id']); ?>"
                                  data-tqa-confirm-title="حذف السؤال"
                                  data-tqa-confirm="لا رجعة في هذا الحذف. وإجابات من أداه تبقى في سجل نتائجهم."
                                  data-tqa-confirm-ok="نعم، احذف"
                                  data-tqa-confirm-tone="danger">
                                <?php echo tq_csrf(); ?>
                                <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">
                                    <?php echo tq_icon('trash', 15); ?><span class="tqa-sr">حذف</span>
                                </button>
                            </form>
                        </div>

                        <details class="tqd-edit">
                            <summary>تحرير هذا السؤال</summary>
                            <form method="post"
                                  action="<?php echo site_url('taqdar_admin/diag_question_save/' . $exam_id . '/' . (int) $q['id']); ?>"
                                  style="margin-block-start:var(--tq-space-m)">
                                <?php echo tq_csrf(); ?>

                                <div class="tqa-field">
                                    <label class="tqa-field__label">نص السؤال</label>
                                    <textarea class="tqa-textarea" name="title" rows="2" required><?php echo html_escape($q['title']); ?></textarea>
                                </div>

                                <div class="tqa-fieldgrid">
                                    <div class="tqa-field">
                                        <label class="tqa-field__label">المستوى</label>
                                        <select class="tqa-select" name="level">
                                            <?php foreach ($levels as $lk => $lm): ?>
                                                <option value="<?php echo html_escape($lk); ?>"
                                                    <?php echo ($lk === $q['level']) ? 'selected' : ''; ?>>
                                                    <?php echo html_escape($lm['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="tqa-field">
                                        <label class="tqa-field__label">الترتيب</label>
                                        <input class="tqa-input" name="order" type="number" value="<?php echo (int) $q['order']; ?>">
                                    </div>
                                </div>

                                <div class="tqa-field">
                                    <label class="tqa-field__label">الخيارات والإجابة الصحيحة</label>
                                    <?php foreach ($vals as $i => $v): ?>
                                        <div class="tqd-opt">
                                            <input type="radio" name="correct" value="<?php echo $i; ?>"
                                                   <?php echo ($v !== '' && (string) $v === $right) ? 'checked' : ''; ?>
                                                   aria-label="الخيار <?php echo $i + 1; ?> هو الصحيح">
                                            <input class="tqa-input" type="text" name="options[<?php echo $i; ?>]"
                                                   value="<?php echo html_escape($v); ?>"
                                                   placeholder="الخيار <?php echo $i + 1; ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="tqa-actions">
                                    <button type="submit" class="tqa-btn tqa-btn--primary tqa-btn--sm">حفظ التعديل</button>
                                </div>
                            </form>
                        </details>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    <?php endforeach; ?>

    <p class="tqa-count">
        <?php echo $total; ?> سؤالا في هذا الاختبار.
        وعتبة اتقان المستوى <?php echo (int) $exam['level_threshold']; ?>% —
        يبلغ الطالب أعلى مستوى بلغ عتبته، وإلا فما دونه، وإلا فمبتدئ.
    </p>

<?php endif; ?>
