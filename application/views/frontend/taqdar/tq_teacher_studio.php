<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * استوديو المحتوى — الخطوتان الغائبتان من دورة الإنتاج.
 *
 * دورة الإنتاج في وثيقة المنتج خمس خطوات، وكان المنفذ منها ثلاثا:
 * الرفع (١)، والإرسال للمراجعة (٤)، والاعتماد الإداري (٥). والغائبتان
 * هما الثانية والثالثة — **التوليد الآلي**، و**اعتماد المعلم لكل مخرج**
 * — وهما ما تبنيه هذه الشاشة.
 *
 * وشرط `F3.2` مكتوب فوق كل زر هنا: **«لا نشر تلقائي — كل مخرج يمر
 * باعتماد صريح»**. فالتوليد يكتب مسودات، والمسودة لا يراها طالب، ولا
 * يصل شيء إليه حتى يضغط المعلم «اعتمد» على المخرج بعينه. وزر «اعتمد
 * الكل» غير موجود عمدا: اعتماد بضغطة واحدة على أربعة مخرجات لم تقرأ هو
 * النشر التلقائي نفسه باسم آخر.
 *
 * والشاشة تعمل بلا جافاسكربت: كل زر نموذج `POST`، وكل محرر `textarea`.
 */
include 'tq_student_styles.php';

$tq_nav   = 'studio';
$tq_role  = 'teacher';
$tq_title = 'استوديو المحتوى';
$tq_sub   = 'ولد مخرجات الدرس، راجعها، ثم اعتمد كل واحد — لا ينشر شيء قبل اعتمادك.';
$tq_icon  = 'pen';

$CI  = &get_instance();
$tid = isset($user_id) ? (int) $user_id : (int) $CI->session->userdata('user_id');

$CI->load->model('taqdar_studio_model', 'tq_studio');
$CI->load->model('taqdar_learn_model',  'tq_learn');
$CI->load->model('taqdar_teacher_model', 'tq_teach');

/* دروس المعلم — نطاقه مفروض في الاستعلام لا في شرط عرض.
   ومرتبة كما يراها الطالب: كورسا فقسما فترتيبا. وكانت `ORDER BY l.id DESC`
   قائمة مسطحة بمئتي صف بلا تجميع — والمعلم يبحث عن «الدرس الثالث في
   الوحدة الثانية» لا عن «الدرس رقم ٣٩١». وحالة الدرس تعرض معه: مسودة
   واقعة في وسط القائمة تشبه المنشور تماما. */
$tq_lessons = array();
try {
    $tq_lessons = $CI->db->query(
        'SELECT l.`id`, l.`title`, l.`tq_status`, l.`duration`,
                c.`title` AS course_title, c.`id` AS course_id,
                s.`title` AS section_title, s.`order` AS section_order
           FROM `lesson` l
           JOIN `course` c ON c.`id` = l.`course_id`
      LEFT JOIN `section` s ON s.`id` = l.`section_id`
          WHERE c.`creator` = ? OR FIND_IN_SET(?, c.`user_id`) > 0
          ORDER BY c.`title` ASC, s.`order` ASC, s.`id` ASC, l.`order` ASC, l.`id` ASC
          LIMIT 300', array($tid, $tid))->result_array();
} catch (Throwable $e) { $tq_lessons = array(); }

/** حالة الدرس بعبارة تقرأ في قائمة منسدلة. */
$tq_status_word = function ($s) {
    $s = (string) $s;
    if ($s === 'review')   return 'قيد المراجعة';
    if ($s === 'rejected') return 'مرفوض';
    if ($s === 'draft')    return 'مسودة';
    return 'منشور';
};

/**
 * شكل المخرج — يعرض في `placeholder` لا داخل الحقل.
 *
 * المحرر خام في هذه النسخة، والخانة الفارغة أمام معلم لا تقول شيئا.
 * والقالب هنا يقول ما بنية المطلوب بلا أن يصير قيمة تحفظ.
 */
$tq_shape = function ($kind) {
    switch ($kind) {
        case 'summary':
            return "{\n  \"text\": \"…\",\n  \"points\": [\"…\", \"…\"]\n}";
        case 'concept_map':
            return "{\n  \"nodes\": [{\"id\": 1, \"label\": \"…\"}],\n  \"edges\": [{\"from\": 1, \"to\": 2}]\n}";
        case 'flashcards':
            return "{\n  \"cards\": [{\"front\": \"…\", \"back\": \"…\"}]\n}";
        case 'questions':
            return "{\n  \"items\": [{\"q\": \"…\", \"options\": [\"…\", \"…\"], \"correct\": 0}]\n}";
    }
    return '{}';
};

$tq_lid = (int) $CI->input->get('lesson');
if (!$tq_lid && $tq_lessons) $tq_lid = (int) $tq_lessons[0]['id'];

/* الملكية تفحص هنا أيضا لا في مسار الكتابة وحده: من غير الرقم في الرابط
   لا يقرأ درس غيره — والقراءة تسريب كالكتابة. */
$tq_owned = false;
foreach ($tq_lessons as $l) if ((int) $l['id'] === $tq_lid) { $tq_owned = true; break; }
if (!$tq_owned) $tq_lid = 0;

$tq_asset   = $tq_lid ? $CI->tq_studio->asset($tq_lid) : null;
$tq_out     = $tq_lid ? $CI->tq_studio->outputs($tq_lid) : array();
$tq_cues    = $tq_lid ? $CI->tq_learn->transcript($tq_lid) : array();
$tq_kinds   = Taqdar_studio_model::kinds();

include 'portal_open.php';
?>

<?php if (!$tq_lessons): ?>

  <section class="tq-card">
    <?php echo tq_s_empty('pen', 'sand', 'لا دروس في نطاقك بعد',
          'ارفع درسك الأول، ثم عد إلى هنا لتوليد ملخصه وبطاقاته وأسئلته.',
          'ارفع درسا', base_url('teacher/upload'), false, 'primary'); ?>
  </section>

<?php else: ?>

  <form method="get" class="tq-st-pick" action="<?php echo base_url('teacher/studio'); ?>">
    <label class="sr-only" for="tqStLesson">اختر درسا</label>
    <?php /* مجمع بالكورس والقسم كما يراه الطالب — لا قائمة مسطحة
             بمئتي صف. والحالة مع كل درس: مسودة في وسط القائمة تشبه
             المنشور تماما بلا هذا. */ ?>
    <select class="tq-select" id="tqStLesson" name="lesson" onchange="this.form.submit()">
      <?php
      $tq_g = '';
      foreach ($tq_lessons as $l):
          $g = trim((string) $l['course_title'] . ' · ' . (string) $l['section_title'], ' ·');
          if ($g !== $tq_g):
              if ($tq_g !== '') echo '</optgroup>';
              $tq_g = $g;
              echo '<optgroup label="' . html_escape($g) . '">';
          endif; ?>
        <option value="<?php echo (int) $l['id']; ?>"
          <?php echo $tq_lid === (int) $l['id'] ? ' selected' : ''; ?>>
          <?php echo html_escape($l['title']); ?>
          — <?php echo html_escape($tq_status_word($l['tq_status'])); ?>
        </option>
      <?php endforeach; ?>
      <?php if ($tq_g !== '') echo '</optgroup>'; ?>
    </select>
    <noscript><button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">افتح</button></noscript>
  </form>

  <?php if (!$tq_lid): ?>
    <section class="tq-card" style="margin-block-start:var(--tq-space-l)">
      <?php echo tq_s_empty('lock', 'peach', 'هذا الدرس ليس في نطاقك',
            'اختر درسا من دروسك في القائمة أعلاه.', '', '', true); ?>
    </section>
  <?php else: ?>

  <?php /* ── حالة الأصل ────────────────────────────────────────────
          آلة الحالة معروضة كخط لا كشارة: المعلم يريد أن يعرف أين درسه
          من الطريق، لا اسم محطته وحدها. */ ?>
  <section class="tq-card" style="margin-block:var(--tq-space-l)">
    <div class="tq-card__head">
      <h2 class="tq-card__title">حالة الدرس</h2>
      <?php if (!empty($tq_asset['legacy'])): ?>
        <span class="tq-caption">رفع قبل أن توجد المراجعة، فيعامل منشورا</span>
      <?php endif; ?>
    </div>

    <ol class="tq-st-flow">
      <?php
      $flow  = array('uploading', 'processed', 'in_review', 'published');
      $curix = array_search($tq_asset['state'], $flow, true);
      foreach ($flow as $i => $s):
        $cls = ($tq_asset['state'] === 'rejected') ? 'off'
             : (($curix !== false && $i < $curix) ? 'done'
             : (($curix !== false && $i === $curix) ? 'now' : 'off'));
      ?>
        <li class="tq-st-step tq-st-step--<?php echo $cls; ?>">
          <span class="tq-st-step__d" aria-hidden="true"></span>
          <span><?php echo html_escape(Taqdar_studio_model::state_label($s)); ?></span>
        </li>
      <?php endforeach; ?>
    </ol>

    <?php if ($tq_asset['state'] === 'rejected'): ?>
      <p class="tq-flash tq-flash--err" style="margin-block-start:var(--tq-space-m)">
        رفض الدرس<?php echo !empty($tq_asset['reason'])
          ? ': ' . html_escape($tq_asset['reason']) : '.'; ?>
        أصلح ما ذكر ثم أعد إرساله.
      </p>
    <?php endif; ?>

    <?php /* الحماية تقال صراحة — ولا يوهم المعلم بما لا يقع.
            يوتيوب وفيميو رابطهما عام دائم بحكم استضافتهما. */ ?>
    <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
      <?php if (($tq_asset['protection'] ?? '') === 'signed'): ?>
        الحماية: رابط موقع ينتهي بعد خمس دقائق ومقيد بحساب الطالب. لا يعمل من حساب آخر ولا بعد انتهائه.
      <?php else: ?>
        الحماية: لا شيء — المقطع مستضاف خارج المنصة برابط عام دائم. ارفع الملف إلى المنصة ليحمى.
      <?php endif; ?>
    </p>

    <?php if (in_array($tq_asset['state'], array('uploading', 'processed', 'rejected'), true)): ?>
      <form method="post" action="<?php echo base_url('teacher/studio/state'); ?>"
            style="margin-block-start:var(--tq-space-l)">
        <?php echo tq_csrf(); ?>
        <input type="hidden" name="lesson_id" value="<?php echo $tq_lid; ?>">
        <input type="hidden" name="to" value="<?php
          echo $tq_asset['state'] === 'uploading' ? 'processed' : 'in_review'; ?>">
        <button class="tq-btn tq-btn--primary" type="submit"><?php
          echo $tq_asset['state'] === 'uploading'
             ? 'علمه جاهزا للمراجعة'
             : 'أرسله للمراجعة العلمية والفنية'; ?></button>
      </form>
    <?php endif; ?>
  </section>

  <div class="tq-cols">
    <div class="tq-stack">

      <?php /* ── المخرجات ──────────────────────────────────────────── */ ?>
      <section class="tq-card">
        <div class="tq-card__head">
          <h2 class="tq-card__title">مخرجات الدرس</h2>
          <form method="post" action="<?php echo base_url('teacher/studio/generate'); ?>">
            <?php echo tq_csrf(); ?>
            <input type="hidden" name="lesson_id" value="<?php echo $tq_lid; ?>">
            <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">ولد المسودات</button>
          </form>
        </div>

        <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
          التوليد يكتب مسودات فقط. ولا يصل الطالب مخرجا حتى تعتمده بعينه —
          والمعتمد لا يمس عند إعادة التوليد.
        </p>

        <?php foreach ($tq_kinds as $kind => $label):
          $o = isset($tq_out[$kind]) ? $tq_out[$kind] : null;
          $state = $o ? $o['state'] : 'none';
        ?>
          <details class="tq-st-out" <?php echo $state === 'draft' ? ' open' : ''; ?>>
            <summary>
              <span class="tq-st-out__t"><?php echo html_escape($label); ?></span>
              <span class="tq-st-out__b tq-st-out__b--<?php echo $state; ?>"><?php
                echo $state === 'approved' ? 'معتمد — يعرض للطالب'
                   : ($state === 'draft' ? 'مسودة — لا تعرض' : 'لم يولد بعد'); ?></span>
            </summary>

            <div class="tq-st-out__body">
              <?php if (!$o): ?>
                <p class="tq-caption">اضغط «ولد المسودات» أعلاه، أو اكتبه بنفسك أدناه.</p>
              <?php elseif (!empty($o['reason'])): ?>
                <p class="tq-flash tq-flash--err"><?php echo html_escape($o['reason']); ?></p>
              <?php endif; ?>

              <?php /* المحرر JSON خام عمدا في هذه النسخة: محرر بشكل لكل
                      نوع (شبكة بطاقات، ورسم خريطة) شاشة قائمة بذاتها،
                      وتأجيله لا يمنع الدورة من العمل — والمعلم يرى بنية
                      واضحة ويعدل فيها. وما يحفظ يفحص عند الاعتماد فلا
                      يمر ناقص.

                      والفارغ يطبع **فارغا**: كانت الخانة تملأ بالنص
                      الحرفي `{}` حين لا مخرج، فيقرأ المعلم رمزين لا
                      يعنيان له شيئا، ويحفظهما، فيصير المخرج «مسودة»
                      خاوية تعد بشيء لا وجود له. والقالب في `placeholder`
                      يقول ما شكل المطلوب بلا أن يدخل في الحقل. */ ?>
              <form method="post" action="<?php echo base_url('teacher/studio/save'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="lesson_id" value="<?php echo $tq_lid; ?>">
                <input type="hidden" name="kind" value="<?php echo html_escape($kind); ?>">
                <textarea name="data" rows="10" class="tq-st-json" dir="ltr" spellcheck="false"
                          placeholder="<?php echo html_escape($tq_shape($kind)); ?>"><?php
                  echo html_escape($o && $o['data']
                      ? json_encode($o['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                      : ''); ?></textarea>
                <div class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap;margin-block-start:var(--tq-space-s)">
                  <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">احفظ مسودة</button>
                </div>
              </form>

              <?php /* ── الجسر إلى اختبار الدرس — TQ-STUDIO-QBRIDGE ────
                      «أسئلة مقترحة» كانت **مخزن أسئلة خامسا** في
                      `tq_lesson_output`، ومحرر الاختبار في
                      `teacher/quiz/<lesson>` لا يعرف عنه شيئا. فيولد
                      المعلم أسئلة، ويعتمدها، ولا يجيب عنها طالب واحد
                      أبدا — لأن `Taqdar_quiz_model` يقرأ
                      `question.assessment_id` وحده.
                      فيقال هنا صراحة، ويوضع الباب. */ ?>
              <?php if ($kind === 'questions'): ?>
                <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                  <strong>هذه مسودات لا اختبار.</strong>
                  اختبار الدرس — وهو الذي يفتح الدرس التالي — يؤلف في
                  <a href="<?php echo base_url('teacher/quiz/' . $tq_lid); ?>">محرر الاختبار</a>،
                  وأسئلته تربط بأهداف الدرس فتغذي خريطة الإتقان ودفتر الأخطاء.
                  انقل ما أعجبك من هنا إلى هناك.
                </p>
                <a class="tq-btn tq-btn--ghost tq-btn--sm" style="margin-block-start:var(--tq-space-s)"
                   href="<?php echo base_url('teacher/quiz/' . $tq_lid); ?>">افتح محرر الاختبار</a>
              <?php endif; ?>

              <?php if ($o): ?>
                <div class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap;margin-block-start:var(--tq-space-m)">
                  <?php if ($state !== 'approved'): ?>
                    <form method="post" action="<?php echo base_url('teacher/studio/approve'); ?>">
                      <?php echo tq_csrf(); ?>
                      <input type="hidden" name="lesson_id" value="<?php echo $tq_lid; ?>">
                      <input type="hidden" name="kind" value="<?php echo html_escape($kind); ?>">
                      <input type="hidden" name="act" value="approve">
                      <button class="tq-btn tq-btn--primary tq-btn--sm" type="submit">
                        اعتمد «<?php echo html_escape($label); ?>»
                      </button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="<?php echo base_url('teacher/studio/approve'); ?>">
                      <?php echo tq_csrf(); ?>
                      <input type="hidden" name="lesson_id" value="<?php echo $tq_lid; ?>">
                      <input type="hidden" name="kind" value="<?php echo html_escape($kind); ?>">
                      <input type="hidden" name="act" value="reject">
                      <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit">اسحب الاعتماد</button>
                    </form>
                    <span class="tq-caption">اعتمد في <?php
                      echo tq_num(html_escape((string) $o['approved_at'])); ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </section>
    </div>

    <aside class="tq-aside">
      <?php /* ── نص الدرس ──────────────────────────────────────────
              مادة التوليد، ومصدر البحث والقفز في مشغل الطالب. */ ?>
      <section class="tq-card">
        <div class="tq-card__head">
          <h2 class="tq-card__title">نص الدرس</h2>
          <?php if ($tq_cues): ?>
            <span class="tq-caption"><?php echo tq_num(count($tq_cues)); ?> مقطعا</span>
          <?php endif; ?>
        </div>

        <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">
          سطر لكل مقطع، يبدأ بوقته: <code dir="ltr">1:20 نص المقطع</code>.
          ومنه يبحث الطالب في الدرس ويقفز إلى موضع أي جملة، ومنه يولد الملخص.
        </p>

        <form method="post" action="<?php echo base_url('teacher/studio/transcript'); ?>">
          <?php echo tq_csrf(); ?>
          <input type="hidden" name="lesson_id" value="<?php echo $tq_lid; ?>">
          <textarea name="transcript" rows="14" class="tq-st-json"
                    placeholder="0:00 مقدمة الدرس&#10;1:20 تعريف المفهوم"><?php
            $lines = array();
            foreach ($tq_cues as $c) $lines[] = $c['at_label'] . ' ' . $c['text'];
            echo html_escape(implode("\n", $lines));
          ?></textarea>
          <div class="tq-row" style="margin-block-start:var(--tq-space-s)">
            <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">احفظ النص</button>
          </div>
        </form>
      </section>
    </aside>
  </div>

  <?php endif; ?>
<?php endif; ?>

<style>
.tq-st-pick { display: flex; gap: var(--tq-space-s); align-items: center; }

/* خط الحالة */
.tq-st-flow {
  list-style: none; margin: 0; padding: 0;
  display: flex; flex-wrap: wrap; gap: var(--tq-space-l);
}
.tq-st-step { display: flex; align-items: center; gap: var(--tq-space-s); font-size: .86rem; }
.tq-st-step__d {
  inline-size: 10px; block-size: 10px; border-radius: 50%;
  background: var(--tq-line); flex: none;
}
.tq-st-step--done { color: var(--tq-text2); }
.tq-st-step--done .tq-st-step__d { background: var(--tq-actionMastery); }
.tq-st-step--now  { font-weight: 800; color: var(--tq-navy); }
.tq-st-step--now  .tq-st-step__d { background: var(--tq-navy); box-shadow: 0 0 0 4px var(--tq-navyWash); }
.tq-st-step--off  { color: var(--tq-text3); }

/* المخرجات */
.tq-st-out {
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  margin-block-end: var(--tq-space-s); background: var(--tq-surface);
}
.tq-st-out > summary {
  cursor: pointer; padding: var(--tq-space-m) var(--tq-space-l);
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m);
  align-items: center; justify-content: space-between;
}
.tq-st-out__t { font-weight: 700; }
.tq-st-out__b {
  font-size: .74rem; font-weight: 700; padding: 3px 10px;
  border-radius: var(--tq-radius-pill);
  background: var(--tq-line); color: var(--tq-text2);
}
.tq-st-out__b--approved { background: var(--tq-mint-fill);  color: var(--tq-mint-ink); }
.tq-st-out__b--draft    { background: var(--tq-peach-fill); color: var(--tq-peach-ink); }
.tq-st-out__body {
  padding: 0 var(--tq-space-l) var(--tq-space-l);
  display: flex; flex-direction: column; gap: var(--tq-space-s);
}

.tq-st-json {
  inline-size: 100%; font-family: ui-monospace, "Cascadia Mono", Consolas, monospace;
  font-size: .82rem; line-height: 1.6;
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  padding: var(--tq-space-m); background: var(--tq-ground); color: var(--tq-text);
  resize: vertical;
}
.tq-st-json:focus { outline: none; border-color: var(--tq-teal); }
</style>

<?php include 'portal_close.php'; ?>
