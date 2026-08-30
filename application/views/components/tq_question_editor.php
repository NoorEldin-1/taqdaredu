<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * محرر أسئلة الاختيار — قالب واحد يركب في ثلاث شاشات.
 *
 *   · أسئلة الاختبار التشخيصي   (اللوحة، `tqa-*`)
 *   · اختبار الدرس في اللوحة     (اللوحة، `tqa-*`)
 *   · اختبار الدرس عند المعلم    (البوابة، `tq-*`)
 *
 * ونسخة ثالثة من النموذج تعني أن قاعدة تصحح في واحدة وتبقى في اثنتين:
 * حد الخيارات الستة، ومنع التكرار، وأن الصحيح **من الخيارات** لا رقم
 * حر. والقواعد كلها في النموذج (`save_question`)، وهذا يعرضها ولا
 * يحكم — لكنه يعرضها بالشكل نفسه في المواضع الثلاثة.
 *
 * ----------------------------------------------------------------------
 * المتغيرات المتوقعة:
 *
 *   $q_action     مسار الحفظ (POST)
 *   $q_delete     مسار الحذف (POST) — بلاه لا تعرض أزرار حذف
 *   $q_rows       الأسئلة القائمة، ولكل واحد:
 *                   id · title · options[] · correct (نصا) · image (رابطا)
 *                   objective_id · order
 *   $q_skin       'tqa' | 'tq'
 *   $q_extra      حقول تخص الشاشة، تطبع داخل النموذج (مصفوفة HTML)
 *   $q_hidden     حقول مخفية تحمل السياق (مصفوفة اسم => قيمة)
 *   $q_objectives أهداف يختار منها (id => نص) — اختياري
 *   $q_intro      سطر تحت العنوان
 * ----------------------------------------------------------------------
 *
 * والنموذج **يعمل بلا جافاسكربت**: ستة حقول ثابتة لا مكرر يبنى بسكربت،
 * والفارغ يسقط في النموذج والحد الأعلى ستة هناك أيضا. فلا يسقط الحفظ
 * صامتا متى تعثر ملف.
 */

$c = tq_cur_skin(isset($q_skin) ? $q_skin : 'tqa');

$q_rows       = isset($q_rows) ? $q_rows : array();
$q_hidden     = isset($q_hidden) ? $q_hidden : array();
$q_extra      = isset($q_extra) ? $q_extra : array();
$q_objectives = isset($q_objectives) ? $q_objectives : array();
$q_delete     = isset($q_delete) ? $q_delete : '';
$q_intro      = isset($q_intro) ? $q_intro : '';

/** ستة خانات دائما، وما جاء أقل يكمل فارغا. */
$q_slots = function ($opts) {
    $out = array();
    for ($i = 0; $i < 6; $i++) $out[$i] = isset($opts[$i]) ? $opts[$i] : '';
    return $out;
};

/** نص الإجابة الصحيحة من صف السؤال — أول عنصر في `correct_answers`. */
$q_right = function ($q) {
    if (isset($q['correct']) && is_string($q['correct'])) return $q['correct'];
    $v = json_decode((string) (isset($q['correct_answers']) ? $q['correct_answers'] : ''), true);
    return (is_array($v) && count($v)) ? (string) reset($v) : '';
};

/**
 * يطبع نموذج سؤال — إضافة (بلا `$q`) أو تحرير.
 *
 * دالة لا قالبان: نموذج الإضافة ونموذج التحرير كانا يكتبان مرتين في كل
 * شاشة أسئلة، فيضاف حقل في أحدهما وينسى في الآخر — ويقع ذلك فعلا: حقل
 * الصورة أضيف للإضافة، والتحرير بقي بلا صورة حتى نبه إليه.
 */
/* بادئة الزر بحسب الجلد — تحسب مرة، ولا تستنتج داخل الوسم. */
$q_btn = (isset($q_skin) && $q_skin === 'tq') ? 'tq-btn' : 'tqa-btn';

$q_form = function ($q = null) use ($c, $q_btn, $q_action, $q_hidden, $q_extra,
                                    $q_objectives, $q_slots, $q_right) {
    $id   = $q ? (int) $q['id'] : 0;
    $vals = $q_slots($q ? (array) $q['options'] : array());
    $right = $q ? $q_right($q) : '';
    $u    = $id > 0 ? ('e' . $id) : 'n';
    ?>
    <?php /* `enctype` شرط لا زينة: بدونه يصل الطلب بلا `$_FILES` أصلا،
             فيحفظ السؤال بلا صورة ولا خطأ يقال. */ ?>
    <form method="post" enctype="multipart/form-data" action="<?php echo html_escape($q_action); ?>">
        <?php echo tq_csrf(); ?>
        <?php foreach ($q_hidden as $hk => $hv): ?>
            <input type="hidden" name="<?php echo html_escape($hk); ?>" value="<?php echo html_escape($hv); ?>">
        <?php endforeach; ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div class="<?php echo $c['field']; ?>">
            <label class="<?php echo $c['label']; ?>" for="qt<?php echo $u; ?>">
                <?php echo t('نص السؤال'); ?> <span class="<?php echo $c['req']; ?>" aria-hidden="true">*</span>
            </label>
            <textarea class="<?php echo $c['area']; ?>" id="qt<?php echo $u; ?>" name="title"
                      rows="2" required><?php echo html_escape($q['title'] ?? ''); ?></textarea>
        </div>

        <?php if ($q_objectives): ?>
        <div class="<?php echo $c['field']; ?>">
            <label class="<?php echo $c['label']; ?>" for="qo<?php echo $u; ?>"><?php echo t('الهدف الذي يقيسه'); ?></label>
            <?php /* TQ-QOBJ — الافتراضي أول هدف لا «بلا هدف».
                     كان السؤال الجديد يفتح على الخيار الأسوأ: المعلم يكتب
                     سؤاله ويحفظ ولا يمر على القائمة، فتحفظ الأسئلة كلها
                     بـ`objective_id = NULL` — وهو ما وقع فعلا على كل سؤال
                     في القاعدة. وحينها **لا يكتب صف `skill_state` واحد**:
                     خريطة الإتقان فارغة، ودفتر الأخطاء فارغ، والمراجعة
                     المتباعدة فارغة، والسؤال يصحح ولا يعلم شيئا بعد ذلك.
                     ولا يجبر عليه: «بلا هدف» يبقى خيارا لمن أراده صراحة،
                     ولكن لا يكون هو ما يقع بالسكوت. */ ?>
            <?php $q_sel = $q ? (int) ($q['objective_id'] ?? 0)
                              : (int) key($q_objectives); ?>
            <select class="<?php echo $c['select']; ?>" id="qo<?php echo $u; ?>" name="objective_id">
                <?php foreach ($q_objectives as $oid => $otext): ?>
                    <option value="<?php echo (int) $oid; ?>"
                        <?php echo $q_sel === (int) $oid ? 'selected' : ''; ?>>
                        <?php echo html_escape($otext); ?>
                    </option>
                <?php endforeach; ?>
                <option value="0" <?php echo $q_sel === 0 ? 'selected' : ''; ?>><?php echo t('— بلا هدف'); ?></option>
            </select>
            <span class="<?php echo $c['hint']; ?>">
                <?php echo t('بالهدف يعرف النظام أي مفهوم تعثر فيه الطالب، فيعيده إلى دقيقته في الشرح ويكتب الخطأ في دفتره وخريطة إتقانه. وسؤال بلا هدف يصحح ولا يعلم شيئا بعد ذلك.'); ?>
            </span>
        </div>
        <?php endif; ?>

        <?php foreach ($q_extra as $x) echo $x; ?>

        <div class="<?php echo $c['field']; ?>">
            <label class="<?php echo $c['label']; ?>" for="qi<?php echo $u; ?>"><?php echo t('صورة السؤال'); ?></label>
            <?php if ($q && !empty($q['image'])): ?>
                <img class="tqq-img" src="<?php echo html_escape($q['image']); ?>" alt="">
                <label class="tqq-drop">
                    <input type="checkbox" name="image_remove" value="1">
                    <span><?php echo t('احذف الصورة الحالية'); ?></span>
                </label>
            <?php endif; ?>
            <input class="<?php echo $c['input']; ?>" id="qi<?php echo $u; ?>" name="image" type="file"
                   accept="image/png,image/jpeg,image/gif,image/webp">
            <span class="<?php echo $c['hint']; ?>">
                <?php echo t('للمعادلات والرسوم البيانية ولقطات الشاشة — تعرض تحت نص السؤال. jpg · png · gif · webp، وحتى'); ?> <span class="tq-ltr">4</span> <?php echo t('ميجابايت.'); ?>
            </span>
        </div>

        <div class="<?php echo $c['field']; ?>">
            <label class="<?php echo $c['label']; ?>">
                <?php echo t('الخيارات والإجابة الصحيحة'); ?> <span class="<?php echo $c['req']; ?>" aria-hidden="true">*</span>
            </label>
            <span class="<?php echo $c['hint']; ?>" style="display:block;margin-block-end:var(--tq-space-s)">
                <?php echo t('خياران على الأقل وستة على الأكثر. علم الدائرة أمام الصحيح، والفارغ يهمل.'); ?>
            </span>
            <?php for ($i = 0; $i < 6; $i++):
                $val = $vals[$i];
                $on  = ($val !== '' && $val === $right) || ($right === '' && $i === 0); ?>
                <div class="tqq-opt">
                    <input type="radio" name="correct" value="<?php echo $i; ?>"
                           <?php echo $on ? 'checked' : ''; ?>
                           aria-label="الخيار <?php echo $i + 1; ?> هو الصحيح">
                    <input class="<?php echo $c['input']; ?>" type="text" name="options[<?php echo $i; ?>]"
                           value="<?php echo html_escape($val); ?>"
                           placeholder="الخيار <?php echo $i + 1; ?><?php echo $i < 2 ? '' : t('(اختياري)'); ?>">
                </div>
            <?php endfor; ?>
        </div>

        <div class="tqq-acts">
            <button class="<?php echo $q_btn; ?> <?php echo $q_btn; ?>--primary" type="submit">
                <?php echo tq_icon($id ? 'check' : 'plus', 16); ?>
                <?php echo $id ? t('احفظ التعديل') : t('أضف السؤال'); ?>
            </button>
        </div>
    </form>
    <?php
};

?>

<style>
/* موضعي لهذا المكون، ويطبع في المحركين. كل قيمة من التوكنات. */
.tqq-opt   { display: flex; align-items: center; gap: var(--tq-space-s);
             margin-block-end: var(--tq-space-s); }
.tqq-opt input[type="radio"] { flex: 0 0 auto; inline-size: 18px; block-size: 18px;
                               accent-color: var(--tq-teal); }
.tqq-opt input[type="text"]  { flex: 1 1 auto; }
.tqq-img   { display: block; max-inline-size: min(100%, 420px); block-size: auto;
             margin-block: var(--tq-space-s); border-radius: var(--tq-radius-small);
             border: 1px solid var(--tq-line); }
.tqq-drop  { display: flex; gap: var(--tq-space-xs); align-items: center;
             font: var(--tq-type-caption); color: var(--tq-danger);
             margin-block-end: var(--tq-space-s); cursor: pointer; }
.tqq-acts  { display: flex; gap: var(--tq-space-s); flex-wrap: wrap;
             margin-block-start: var(--tq-space-m); }

.tqq-q     { border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
             padding: var(--tq-space-m); margin-block-end: var(--tq-space-m);
             background: var(--tq-surface); }
.tqq-q__top{ display: flex; align-items: flex-start; gap: var(--tq-space-m);
             justify-content: space-between; }
.tqq-q__o  { margin: var(--tq-space-s) 0 0; padding-inline-start: var(--tq-space-l);
             font: var(--tq-type-caption); color: var(--tq-text2); }
.tqq-q__o li { margin-block-end: 2px; }
.tqq-q__o li.is-right { color: var(--tq-teal); font-weight: 700; }
.tqq-q__meta { font: var(--tq-type-micro); color: var(--tq-text3);
               margin-block-start: var(--tq-space-s); }
.tqq-edit  { margin-block-start: var(--tq-space-m); padding-block-start: var(--tq-space-m);
             border-block-start: 1px dashed var(--tq-line); }
.tqq-edit > summary { cursor: pointer; font: var(--tq-type-caption); color: var(--tq-text2); }
</style>

<?php /* ── نموذج الإضافة ─────────────────────────────────────────── */ ?>
<div class="tqq-add">
    <h3 style="font:var(--tq-type-h3);margin:0 0 var(--tq-space-s)"><?php echo t('أضف سؤالا'); ?></h3>
    <?php if ($q_intro !== ''): ?>
        <p class="<?php echo $c['hint']; ?>" style="margin-block-end:var(--tq-space-l)">
            <?php echo $q_intro; ?>
        </p>
    <?php endif; ?>
    <?php $q_form(null); ?>
</div>

<?php /* ── الأسئلة القائمة ───────────────────────────────────────── */ ?>
<?php if ($q_rows): ?>
    <h3 style="font:var(--tq-type-h3);margin:var(--tq-space-xl) 0 var(--tq-space-m)">
        أسئلة هذا الاختبار (<?php echo tq_iso(count($q_rows)); ?>)
    </h3>

    <?php /* «كذا من الأسئلة بلا هدف مرتبط» يقال في لوح الجاهزية أعلى
             الشاشة، من `Taqdar_quiz_model::readiness()` — وهو المصدر
             الواحد لذلك الخبر في الشاشتين. ولا يكرر هنا: تحذيران
             بالمعنى نفسه في صفحة واحدة يعلمان القارئ تجاهلهما. */ ?>

    <?php foreach ($q_rows as $qi => $q): $right = $q_right($q); ?>
        <div class="tqq-q">
            <div class="tqq-q__top">
                <div style="min-inline-size:0;flex:1">
                    <strong><?php echo tq_iso($qi + 1); ?>. <?php echo html_escape($q['title']); ?></strong>
                    <?php if (!empty($q['image'])): ?>
                        <img class="tqq-img" src="<?php echo html_escape($q['image']); ?>" alt="">
                    <?php endif; ?>
                    <ol class="tqq-q__o">
                        <?php foreach ((array) $q['options'] as $o): ?>
                            <li class="<?php echo ((string) $o === $right) ? 'is-right' : ''; ?>">
                                <?php echo html_escape($o); ?>
                                <?php if ((string) $o === $right): ?> — الصحيح<?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php if (!empty($q['objective_text'])): ?>
                        <p class="tqq-q__meta">يقيس: <?php echo html_escape($q['objective_text']); ?></p>
                    <?php elseif ($q_objectives): ?>
                        <p class="tqq-q__meta" style="color:var(--tq-danger)">
                            <?php echo t('بلا هدف — لا يدخل خريطة الإتقان ولا دفتر الأخطاء.'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($q_delete !== ''): ?>
                <?php /* الحذف نموذج لا رابط: رابط GET يحذف ينفذ بمجرد جلبه. */ ?>
                <form method="post" style="margin:0" action="<?php echo html_escape($q_delete); ?>"
                      data-tqq-confirm="لا رجعة في هذا الحذف. وإجابات من أداه تبقى في سجل نتائجهم.">
                    <?php echo tq_csrf(); ?>
                    <?php foreach ($q_hidden as $hk => $hv): ?>
                        <input type="hidden" name="<?php echo html_escape($hk); ?>" value="<?php echo html_escape($hv); ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="id" value="<?php echo (int) $q['id']; ?>">
                    <button class="<?php echo $q_btn; ?> <?php echo $q_btn; ?>--ghost <?php echo $q_btn; ?>--sm"
                            type="submit" aria-label="<?php echo te('احذف هذا السؤال'); ?>">
                        <?php echo tq_icon('trash', 14); ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php /* التحرير مطوي بجوار سؤاله: يفتح بلا سكربت، ويغلقه
                     المتصفح نفسه، ولا يخرج المؤلف من موضعه في الصفحة. */ ?>
            <details class="tqq-edit">
                <summary><?php echo t('تحرير هذا السؤال'); ?></summary>
                <div style="margin-block-start:var(--tq-space-m)"><?php $q_form($q); ?></div>
            </details>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.querySelectorAll('[data-tqq-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
        if (!confirm(f.getAttribute('data-tqq-confirm'))) e.preventDefault();
    });
});
</script>
