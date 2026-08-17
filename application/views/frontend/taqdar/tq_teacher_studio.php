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

/* دروس المعلم — نطاقه مفروض في الاستعلام لا في شرط عرض. */
$tq_lessons = array();
try {
    $tq_lessons = $CI->db->query(
        'SELECT l.`id`, l.`title`, c.`title` AS course_title
           FROM `lesson` l
           JOIN `course` c ON c.`id` = l.`course_id`
          WHERE c.`creator` = ? OR FIND_IN_SET(?, c.`user_id`) > 0
          ORDER BY l.`id` DESC LIMIT 200', array($tid, $tid))->result_array();
} catch (Throwable $e) { $tq_lessons = array(); }

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
    <select class="tq-select" id="tqStLesson" name="lesson" onchange="this.form.submit()">
      <?php foreach ($tq_lessons as $l): ?>
        <option value="<?php echo (int) $l['id']; ?>"
          <?php echo $tq_lid === (int) $l['id'] ? ' selected' : ''; ?>>
          <?php echo html_escape($l['title']); ?> — <?php echo html_escape($l['course_title']); ?>
        </option>
      <?php endforeach; ?>
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
                      يمر ناقص. */ ?>
              <form method="post" action="<?php echo base_url('teacher/studio/save'); ?>">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="lesson_id" value="<?php echo $tq_lid; ?>">
                <input type="hidden" name="kind" value="<?php echo html_escape($kind); ?>">
                <textarea name="data" rows="10" class="tq-st-json" dir="ltr" spellcheck="false"><?php
                  echo html_escape($o && $o['data']
                      ? json_encode($o['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                      : '{}'); ?></textarea>
                <div class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap;margin-block-start:var(--tq-space-s)">
                  <button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit">احفظ مسودة</button>
                </div>
              </form>

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
