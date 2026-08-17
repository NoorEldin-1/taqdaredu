<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * التهيئة — ثلاثة أسئلة، ثم يبدأ.
 *
 * `F1.2` يشترط «لا أكثر من 4 إجابات قبل أول قيمة تعرض». وهي هنا ثلاث:
 * الصف، والمواد، والهدف اليومي. والرابع لا يوجد — وكل حقل يضاف هنا يشتري
 * بيانات بثمن طالب ينصرف قبل أن يرى درسا واحدا.
 *
 * **ولماذا شاشة مستقلة لا حقول في التسجيل؟** لأن نموذج التسجيل يسأل من
 * لا يعرف المنصة بعد، وهذه تسأل من دخلها. والفرق ليس ترتيبا: من رأى
 * لوحته يفهم لماذا يسأل عن هدف يومي، ومن يملأ نموذج تسجيل من أحد عشر
 * حقلا يقرأ السؤال ضريبة.
 *
 * والصف يكتب في `users.grade_id` — موضعه الأصلي الذي يقرؤه الكتالوج
 * والتشخيص والباقات. ولو خزن هنا ثانية لصار موضعان لحقيقة واحدة.
 *
 * **وتعمل بلا جافاسكربت**: النموذج `POST` عادي، والخيارات `checkbox` و
 * `radio` حقيقيان. والسكربت في آخر الملف يجمل ولا يشترط.
 */
include 'tq_student_styles.php';

$tq_nav   = 'setup';
$tq_role  = 'student';
$tq_title = '';   // هذه شاشة ترحيب لا صفحة في قائمة — عنوانها في متنها

$tq_s    = isset($tq_setup)  ? $tq_setup  : array();
$tq_subs = isset($tq_subjects) ? $tq_subjects : array();
$tq_un   = isset($tq_units)  ? $tq_units   : array();
$tq_gr   = isset($tq_grades) ? $tq_grades  : array();
$tq_gid  = isset($tq_grade_id) ? (int) $tq_grade_id : 0;

$tq_chosen  = isset($tq_s['subject_ids']) ? $tq_s['subject_ids'] : array();
$tq_unit    = isset($tq_s['goal_unit'])   ? $tq_s['goal_unit']   : 'minutes';
$tq_value   = isset($tq_s['goal_value'])  ? (int) $tq_s['goal_value'] : 30;
$tq_redo    = !empty($tq_s['done']);   // يعدل خطته لا يبدؤها

include 'portal_open.php';
?>

<div class="tq-setup">

  <header class="tq-setup__hero">
    <span class="tq-s-art tq-pastel tq-pastel--mint" aria-hidden="true">
      <span class="tq-pastel__icon"><?php echo tq_icon('target', 34); ?></span>
    </span>
    <h1 class="tq-display"><?php
      echo $tq_redo ? 'عدل خطتك' : 'قبل أن نبدأ — ثلاثة أسئلة'; ?></h1>
    <p class="tq-body tq-setup__lead"><?php
      echo $tq_redo
        ? 'ما تختاره هنا يحدد ما يعرض لك، وما يقاس عليه هدفك اليومي.'
        : 'دقيقة واحدة، ثم تفتح لوحتك على ما يخصك أنت. وتغيرها متى شئت من إعداداتك.';
    ?></p>
  </header>

  <form method="post" action="<?php echo base_url('student/setup/save'); ?>"
        class="tq-setup__form" data-tq-setup>
    <?php echo tq_csrf(); ?>

    <?php /* ── 1 · المرحلة ─────────────────────────────────────────── */ ?>
    <section class="tq-card tq-setup__step">
      <div class="tq-setup__num" aria-hidden="true">1</div>
      <div class="tq-setup__body">
        <h2 class="tq-card__title">في أي صف أنت؟</h2>
        <p class="tq-caption">عليه يبنى الكتالوج والاختبار التشخيصي وباقتك.</p>

        <?php if (!$tq_gr): ?>
          <p class="tq-field__msg">لا صفوف مفعلة بعد. تواصل مع الإدارة.</p>
        <?php else: ?>
          <div class="tq-setup__grid" role="radiogroup" aria-label="الصف الدراسي">
            <?php foreach ($tq_gr as $g): $gid = (int) $g['id']; ?>
              <label class="tq-pick<?php echo $gid === $tq_gid ? ' is-on' : ''; ?>">
                <input type="radio" name="grade_id" value="<?php echo $gid; ?>"
                       <?php echo $gid === $tq_gid ? ' checked' : ''; ?> required>
                <span class="tq-pick__label"><?php echo html_escape($g['name_ar']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <?php /* ── 2 · المواد ───────────────────────────────────────────
            الخيارات من **المعروض** لا من جدول التصنيف — كما تشتق مرشحات
            الكتالوج: في `paths` مواد بأرقام لا صف لها في `subjects`،
            وقائمة مبنية من الجدول تسقطها. */ ?>
    <section class="tq-card tq-setup__step">
      <div class="tq-setup__num" aria-hidden="true">2</div>
      <div class="tq-setup__body">
        <h2 class="tq-card__title">ما المواد التي تريد التركيز عليها؟</h2>
        <p class="tq-caption">اختر ما شئت. وترك الكل فارغا يعني «اعرض لي كل شيء».</p>

        <?php if (!$tq_subs): ?>
          <?php echo tq_s_empty('layers', 'sand', 'لا مواد منشورة لصفك بعد',
                'ستظهر هنا حالما تنشر برامج صفك. تابع الآن وعدل اختيارك لاحقا.', '', '', true); ?>
        <?php else: ?>
          <div class="tq-setup__grid">
            <?php foreach ($tq_subs as $s): $sid = (int) $s['id'];
                  $on = in_array($sid, $tq_chosen, true); ?>
              <label class="tq-pick<?php echo $on ? ' is-on' : ''; ?>">
                <input type="checkbox" name="subject_ids[]" value="<?php echo $sid; ?>"
                       <?php echo $on ? ' checked' : ''; ?>>
                <span class="tq-pick__label"><?php echo html_escape($s['name_ar']); ?></span>
                <?php if ((int) $s['paths'] > 0): ?>
                  <span class="tq-pick__note"><?php
                    echo tq_num((int) $s['paths']); ?> برنامجا</span>
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <?php /* ── 3 · الهدف اليومي ─────────────────────────────────────
            وهو ما تقاس عليه حلقة الهدف في اللوحة (`F2.6`)، فبلا هذا
            السؤال تصير الحلقة رقما بلا مقام. */ ?>
    <section class="tq-card tq-setup__step">
      <div class="tq-setup__num" aria-hidden="true">3</div>
      <div class="tq-setup__body">
        <h2 class="tq-card__title">كم تريد أن تنجز كل يوم؟</h2>
        <p class="tq-caption">هدف صغير تبلغه كل يوم خير من كبير تتركه في الأسبوع الأول.</p>

        <div class="tq-setup__goal">
          <div class="tq-setup__units" role="radiogroup" aria-label="وحدة الهدف">
            <?php foreach ($tq_un as $key => $u): ?>
              <label class="tq-pick tq-pick--sm<?php echo $key === $tq_unit ? ' is-on' : ''; ?>">
                <input type="radio" name="goal_unit" value="<?php echo html_escape($key); ?>"
                       data-tq-default="<?php echo (int) $u['default']; ?>"
                       <?php echo $key === $tq_unit ? ' checked' : ''; ?>>
                <span class="tq-pick__label"><?php echo html_escape($u['plural']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <label class="tq-setup__value">
            <span class="sr-only">مقدار الهدف</span>
            <input type="number" name="goal_value" id="tqGoalValue" min="1" max="600"
                   inputmode="numeric" value="<?php echo $tq_value; ?>" required>
            <span class="tq-setup__unit" data-tq-unit-label><?php
              echo html_escape(isset($tq_un[$tq_unit]) ? $tq_un[$tq_unit]['plural'] : ''); ?></span>
          </label>
        </div>
      </div>
    </section>

    <div class="tq-setup__actions">
      <button class="tq-btn tq-btn--primary" type="submit"><?php
        echo $tq_redo ? 'احفظ التعديل' : 'ابدأ رحلتي'; ?></button>
      <?php if ($tq_redo): ?>
        <a class="tq-btn tq-btn--ghost" href="<?php echo base_url('student'); ?>">عد بلا تعديل</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<style>
/* التهيئة — من التوكنات وحدها، وبلا left/right. */
.tq-setup { max-inline-size: 760px; margin-inline: auto; }

.tq-setup__hero { text-align: center; margin-block-end: var(--tq-space-h2); }
.tq-setup__hero .tq-s-art { margin-inline: auto; margin-block-end: var(--tq-space-l); }
.tq-setup__lead { color: var(--tq-text2); max-inline-size: 46ch; margin-inline: auto; }

.tq-setup__form { display: flex; flex-direction: column; gap: var(--tq-space-l); }

.tq-setup__step { display: flex; gap: var(--tq-space-l); align-items: flex-start; }
.tq-setup__num {
  inline-size: 34px; block-size: 34px; flex: none;
  border-radius: var(--tq-radius-small);
  background: var(--tq-mint-fill); color: var(--tq-mint-ink);
  display: grid; place-items: center; font-weight: 800;
}
.tq-setup__body { flex: 1; min-inline-size: 0; }
.tq-setup__body .tq-caption { margin-block: var(--tq-space-xs) var(--tq-space-l); }

.tq-setup__grid {
  display: grid; gap: var(--tq-space-s);
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
}

/* الخيار زر حقيقي: `input` مخفي بصريا ويبقى في شجرة الوصول، فلوح
   المفاتيح وقارئ الشاشة يصلان إليه، والتظليل من `:checked` لا من صنف
   يكتبه جافاسكربت — فيعمل الاختيار كاملا بلا سكربت. */
.tq-pick {
  position: relative; display: flex; flex-direction: column; gap: 2px;
  padding: var(--tq-space-m) var(--tq-space-l);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  background: var(--tq-surface); cursor: pointer;
  transition: border-color 140ms, background 140ms;
}
.tq-pick input { position: absolute; opacity: 0; inset-block-start: 0; inset-inline-start: 0; }
.tq-pick__label { font-weight: 600; }
.tq-pick__note { font-size: .8rem; color: var(--tq-text3); }
.tq-pick:hover { border-color: var(--tq-tealLift); }
.tq-pick:has(input:checked) {
  border-color: var(--tq-teal); background: var(--tq-mint-fill); color: var(--tq-mint-ink);
}
.tq-pick:has(input:focus-visible) { outline: 2px solid var(--tq-focusRing); outline-offset: 2px; }
.tq-pick--sm { padding: var(--tq-space-s) var(--tq-space-m); }

/* `:has` غير مدعوم في متصفح قديم: الصنف من السكربت شبكة أمان لا أساس. */
.tq-pick.is-on { border-color: var(--tq-teal); background: var(--tq-mint-fill); }

.tq-setup__goal { display: flex; flex-wrap: wrap; gap: var(--tq-space-l); align-items: center; }
.tq-setup__units { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); }
.tq-setup__value {
  display: inline-flex; align-items: center; gap: var(--tq-space-s);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  padding: var(--tq-space-s) var(--tq-space-l); background: var(--tq-surface);
}
.tq-setup__value input {
  inline-size: 84px; border: 0; background: transparent; color: inherit;
  font: inherit; font-weight: 800; font-size: 1.3rem; text-align: center;
  font-variant-numeric: tabular-nums;
}
.tq-setup__value input:focus { outline: none; }
.tq-setup__value:focus-within { border-color: var(--tq-teal); }
.tq-setup__unit { color: var(--tq-text2); }

.tq-setup__actions {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m);
  justify-content: center; margin-block-start: var(--tq-space-l);
}

@media (max-width: 560px) {
  .tq-setup__step { flex-direction: column; gap: var(--tq-space-m); }
}
</style>

<script>
/* تجميل لا اشتراط: النموذج يعمل كاملا بلا هذا الملف.
   ما يفعله شيئان — تظليل الخيار في متصفح بلا `:has`، وتبديل المقدار
   الافتراضي حين تتغير الوحدة (ثلاثون دقيقة معقولة، وثلاثون درسا ليست). */
(function () {
  var root = document.querySelector('[data-tq-setup]');
  if (!root) return;

  root.addEventListener('change', function (e) {
    var input = e.target;
    if (!input.name) return;

    if (input.type === 'radio') {
      var peers = root.querySelectorAll('input[name="' + input.name + '"]');
      for (var i = 0; i < peers.length; i++) {
        peers[i].closest('.tq-pick').classList.toggle('is-on', peers[i].checked);
      }
    } else if (input.type === 'checkbox') {
      input.closest('.tq-pick').classList.toggle('is-on', input.checked);
    }

    if (input.name === 'goal_unit' && input.checked) {
      var box = document.getElementById('tqGoalValue');
      var lbl = root.querySelector('[data-tq-unit-label]');
      if (box) box.value = input.getAttribute('data-tq-default') || box.value;
      if (lbl) lbl.textContent = input.closest('.tq-pick').querySelector('.tq-pick__label').textContent;
    }
  });
})();
</script>

<?php include 'portal_close.php'; ?>
