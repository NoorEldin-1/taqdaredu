<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * التحليلات والخريطة الحرارية — `B3.9`.
 *
 * ومعيار قبول الوثيقة ليس رسما: **«كل نمط انخفاض مشاهدة له اقتراح إجراء
 * واضح»**. فالشاشة كلها مبنية على ذلك: كل صف يقول ما وقع، ثم يسميه، ثم
 * يقول ماذا يفعل المعلم — والثلاثة معا لا الأول وحده. وخريطة ملونة بلا
 * جملة إجراء تقرير يفتح مرة ولا يعاد.
 *
 * **والترتيب بالأشد لا بالترتيب الدراسي**: من يفتح هذه الشاشة عنده وقت
 * لدرسين لا لعشرين، فالأولان يجب أن يكونا الأسوأ.
 *
 * ولا مقارنة بمعلمين آخرين: القياس على الدرس نفسه ومقارنته بأخيه في
 * الكورس نفسه — ولوحة صدارة للمعلمين تجعل الشاشة تقرأ تقييم أداء لا أداة
 * عمل، فتغلق.
 */
include 'tq_student_styles.php';

$tq_nav   = 'analytics';
$tq_role  = 'teacher';
$tq_title = t('التحليلات والخريطة الحرارية');
$tq_sub   = t('أين ينصرف طلابك، وأي مفهوم لا يثبت — ومع كل نمط ما تفعله فيه.');
$tq_icon  = 'chart';

$CI  = &get_instance();
$tid = isset($user_id) ? (int) $user_id : (int) $CI->session->userdata('user_id');

$CI->load->model('taqdar_analytics_model', 'tq_an');

$tq_courses = $CI->tq_an->courses_of($tid);
$tq_cid     = (int) $CI->input->get('course');
$tq_heat    = $CI->tq_an->heatmap($tid, $tq_cid, 120);
$tq_sum     = $CI->tq_an->summary($tid, $tq_cid);
$tq_weak    = $CI->tq_an->weak_objectives($tid, $tq_cid, 8);
$tq_hard    = $CI->tq_an->hard_questions($tid, $tq_cid, 8);

/* الترتيب بالأشد: الأحمر ثم البرتقالي ثم السليم ثم ما لا بيانات له.
   وداخل كل درجة بالأقل إكمالا. */
$tq_rank = array('high' => 0, 'mid' => 1, 'ok' => 2, 'none' => 3);
usort($tq_heat, function ($a, $b) use ($tq_rank) {
    $ra = $tq_rank[$a['severity']]; $rb = $tq_rank[$b['severity']];
    if ($ra !== $rb) return $ra - $rb;
    return ((int) $a['finish_rate']) - ((int) $b['finish_rate']);
});

include 'portal_open.php';
?>

<?php if (!$tq_courses): ?>

  <section class="tq-card">
    <?php echo tq_s_empty('chart', 'sand', t('لا كورسات في نطاقك بعد'),
          t('تظهر التحليلات حين يسند إليك كورس ويبدأ طلابك دروسه.'),
          t('ارفع درسك الأول'), base_url('teacher/upload'), false, 'primary'); ?>
  </section>

<?php else: ?>

  <?php /* مرشح الكورس: نموذج GET لا سكربت — يعمل بلا جافاسكربت، والحال
          في الرابط فينسخ ويشارك ويعود إليه المتصفح. */ ?>
  <form method="get" class="tq-an-filter" action="<?php echo base_url('teacher/analytics'); ?>">
    <label class="sr-only" for="tqAnCourse"><?php echo t('اختر كورسا'); ?></label>
    <select class="tq-select" id="tqAnCourse" name="course" onchange="this.form.submit()">
      <option value=""><?php echo t('كل كورساتي'); ?></option>
      <?php foreach ($tq_courses as $c): ?>
        <option value="<?php echo (int) $c['id']; ?>"
          <?php echo $tq_cid === (int) $c['id'] ? ' selected' : ''; ?>><?php
          echo html_escape($c['title']); ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="tq-btn tq-btn--secondary tq-btn--sm" type="submit"><?php echo t('رشح'); ?></button></noscript>
  </form>

  <section class="tq-s-grid4" style="margin-block:var(--tq-space-l) var(--tq-space-xl)">
    <?php
    echo tq_s_stat(tq_num((int) $tq_sum['urgent']), t('درسا يحتاج تدخلا الآن'), 'flame',
                   $tq_sum['urgent'] ? 'rose' : 'mint');
    echo tq_s_stat(tq_num((int) $tq_sum['needs_action']), t('درسا عليه ملاحظة'), 'help', 'peach');
    echo tq_s_stat(
        $tq_sum['avg_finish'] === null ? '<span class="tq-muted">—</span>' : tq_num($tq_sum['avg_finish'] . '%'),
        t('متوسط الإكمال'), 'check', 'sky',
        $tq_sum['avg_finish'] === null ? t('يظهر بعد أول خمسة طلاب') : '');
    echo tq_s_stat(tq_num((int) $tq_sum['starters']), t('بداية درس مسجلة'), 'play', 'lilac');
    ?>
  </section>

  <!-- الخريطة -->
  <section class="tq-card" style="margin-block-end:var(--tq-space-l)">
    <div class="tq-card__head">
      <h2 class="tq-card__title"><?php echo t('الخريطة الحرارية'); ?></h2>
      <span class="tq-caption"><?php echo t('مرتبة بالأشد — ابدأ من أعلاها'); ?></span>
    </div>

    <?php if (!$tq_heat): ?>
      <?php echo tq_s_empty('chart', 'sand', t('لا بيانات بعد'),
            t('تظهر الحرارة حين يبدأ طلابك دروسك. ولا يحكم على درس قبل أن يبدأه خمسة.'), '', '', true); ?>
    <?php else: ?>
      <div class="tq-heat">
        <?php foreach ($tq_heat as $h):
          $sev = $h['severity'];
          $fin = $h['finish_rate'];
          $mas = $h['master_rate'];
        ?>
          <article class="tq-heat__row tq-heat__row--<?php echo $sev; ?>">
            <div class="tq-heat__head">
              <div class="tq-heat__id">
                <span class="tq-heat__t"><?php echo html_escape($h['title']); ?></span>
                <span class="tq-heat__c"><?php echo html_escape($h['course_title']); ?></span>
              </div>
              <span class="tq-heat__tag tq-heat__tag--<?php echo $sev; ?>"><?php
                echo html_escape($h['pattern']); ?></span>
            </div>

            <?php /* الشريط: أين يقف من أكمل الدرس على طوله. والعلامة
                    فوقه موضع الانصراف — وهو الرقم الذي يقول للمعلم أي
                    دقيقة يعيد مشاهدتها. */ ?>
            <?php if ($h['duration_sec'] > 0): ?>
              <div class="tq-heat__bar" role="img"
                   aria-label="<?php echo $fin === null ? t('لا بيانات') : t('الإكمال ') . (int) $fin . t(' بالمئة'); ?>">
                <span class="tq-heat__fill" style="inline-size:<?php echo (int) $fin; ?>%"></span>
                <?php if ($h['drop_percent'] !== null): ?>
                  <span class="tq-heat__drop" style="inset-inline-start:<?php
                    echo min(98, (int) $h['drop_percent']); ?>%"
                        title="<?php echo te('متوسط موضع الانصراف'); ?>"></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="tq-heat__nums">
              <span><?php echo tq_num((int) $h['starters']); ?> <?php echo t('بدأ'); ?></span>
              <span><?php echo $fin === null ? '—' : tq_num($fin . '%'); ?> <?php echo t('أكمل'); ?></span>
              <span><?php echo $mas === null ? '—' : tq_num($mas . '%'); ?> <?php echo t('أتقن'); ?></span>
              <?php if ($h['drop_at'] > 0): ?>
                <span><?php echo t('ينصرف عند'); ?> <?php echo tq_num(sprintf('%d:%02d', intdiv($h['drop_at'], 60), $h['drop_at'] % 60)); ?></span>
              <?php endif; ?>
            </div>

            <?php /* الإجراء — وهو الغرض من الصف كله */ ?>
            <p class="tq-heat__act"><?php echo html_escape($h['action']); ?></p>

            <div class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap">
              <a class="tq-btn tq-btn--ghost tq-btn--sm"
                 href="<?php echo base_url('teacher/studio?lesson=' . (int) $h['lesson_id']); ?>"><?php echo t('افتح في الاستوديو'); ?></a>
              <a class="tq-btn tq-btn--ghost tq-btn--sm"
                 href="<?php echo base_url('teacher/lessons'); ?>"><?php echo t('في دروسي'); ?></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="tq-cols">
    <div class="tq-stack">
      <!-- الأهداف الأضعف -->
      <section class="tq-card">
        <div class="tq-card__head">
          <h2 class="tq-card__title"><?php echo t('أهداف لا تثبت'); ?></h2>
          <span class="tq-caption"><?php echo t('متوسط الإتقان عبر طلابك'); ?></span>
        </div>

        <?php if (!$tq_weak): ?>
          <?php echo tq_s_empty('target', 'mint', t('لا هدف ضعيفا'),
                t('يظهر الهدف هنا حين يقاس على ثلاثة طلاب على الأقل.'), '', '', true); ?>
        <?php else: ?>
          <div class="tq-an-list">
            <?php foreach ($tq_weak as $w):
              $lv = (int) round((float) $w['avg_level']);
            ?>
              <div class="tq-an-row">
                <div class="tq-an-row__m">
                  <span class="tq-an-row__t"><?php echo html_escape($w['text']); ?></span>
                  <span class="tq-an-row__s"><?php echo html_escape($w['lesson_title']); ?>
                    · <?php echo tq_num((int) $w['students']); ?> <?php echo t('طالبا'); ?></span>
                </div>
                <span class="tq-an-pct tq-an-pct--<?php
                  echo $lv < 50 ? 'low' : ($lv < 80 ? 'mid' : 'high'); ?>"><?php
                  echo tq_num($lv . '%'); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <aside class="tq-aside">
      <!-- الأسئلة الأصعب -->
      <section class="tq-card">
        <div class="tq-card__head">
          <h2 class="tq-card__title"><?php echo t('أسئلة يخطئها أكثرهم'); ?></h2>
        </div>
        <p class="tq-caption" style="margin-block-end:var(--tq-space-m)">
          <?php echo t('سؤال يخطئه أكثر من ثلثهم إما صعب بلا داع، أو مكتوب بلبس، أو يقيس ما لم يشرح.'); ?>
        </p>

        <?php if (!$tq_hard): ?>
          <?php echo tq_s_empty('help', 'mint', t('لا سؤال لافتا'),
                t('يظهر السؤال هنا بعد أن يجيبه خمسة طلاب.'), '', '', true); ?>
        <?php else: ?>
          <div class="tq-an-list">
            <?php foreach ($tq_hard as $q): ?>
              <div class="tq-an-row">
                <div class="tq-an-row__m">
                  <span class="tq-an-row__t"><?php echo html_escape(mb_substr($q['title'], 0, 90)); ?></span>
                  <span class="tq-an-row__s"><?php echo html_escape($q['lesson_title']); ?></span>
                </div>
                <span class="tq-an-pct tq-an-pct--<?php
                  echo (int) $q['wrong_rate'] >= 60 ? 'low' : 'mid'; ?>"><?php
                  echo tq_num((int) $q['wrong_rate'] . '%'); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </aside>
  </div>

<?php endif; ?>

<style>
.tq-an-filter { display: flex; gap: var(--tq-space-s); align-items: center; }

.tq-heat { display: flex; flex-direction: column; gap: var(--tq-space-m); }
.tq-heat__row {
  padding: var(--tq-space-l);
  border: 1px solid var(--tq-line);
  border-inline-start: 4px solid var(--tq-line);
  border-radius: var(--tq-radius-small);
  background: var(--tq-surface);
  display: flex; flex-direction: column; gap: var(--tq-space-s);
}
.tq-heat__row--high { border-inline-start-color: var(--tq-danger); }
.tq-heat__row--mid  { border-inline-start-color: var(--tq-amber); }
.tq-heat__row--ok   { border-inline-start-color: var(--tq-actionMastery); }
.tq-heat__row--none { border-inline-start-color: var(--tq-line); }

.tq-heat__head { display: flex; flex-wrap: wrap; gap: var(--tq-space-m); align-items: flex-start; }
.tq-heat__id { flex: 1; min-inline-size: 180px; display: flex; flex-direction: column; gap: 2px; }
.tq-heat__t { font-weight: 700; }
.tq-heat__c { font-size: .8rem; color: var(--tq-text3); }

.tq-heat__tag {
  flex: none; font-size: .75rem; font-weight: 700;
  padding: 3px 10px; border-radius: var(--tq-radius-pill);
  background: var(--tq-line); color: var(--tq-text2);
}
.tq-heat__tag--high { background: var(--tq-rose-fill);  color: var(--tq-rose-ink); }
.tq-heat__tag--mid  { background: var(--tq-peach-fill); color: var(--tq-peach-ink); }
.tq-heat__tag--ok   { background: var(--tq-mint-fill);  color: var(--tq-mint-ink); }

.tq-heat__bar {
  position: relative; block-size: 8px; border-radius: var(--tq-radius-pill);
  background: var(--tq-line); overflow: visible;
}
.tq-heat__fill {
  display: block; block-size: 100%; border-radius: var(--tq-radius-pill);
  background: var(--tq-actionMastery);
}
/* علامة الانصراف: خط رأسي فوق الشريط لا داخله، فيقرأ موضعا لا قيمة */
.tq-heat__drop {
  position: absolute; inset-block: -4px; inline-size: 2px;
  background: var(--tq-danger); border-radius: 2px;
}

.tq-heat__nums {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m);
  font-size: .8rem; color: var(--tq-text2);
}
.tq-heat__act {
  margin: 0; font-size: .88rem; color: var(--tq-text);
  padding: var(--tq-space-s) var(--tq-space-m);
  background: var(--tq-ground); border-radius: var(--tq-radius-small);
}

.tq-an-list { display: flex; flex-direction: column; gap: var(--tq-space-s); }
.tq-an-row {
  display: flex; gap: var(--tq-space-m); align-items: center;
  padding: var(--tq-space-m); border-radius: var(--tq-radius-small); background: var(--tq-ground);
}
.tq-an-row__m { flex: 1; min-inline-size: 0; display: flex; flex-direction: column; gap: 2px; }
.tq-an-row__t { font-weight: 600; font-size: .9rem; }
.tq-an-row__s { font-size: .78rem; color: var(--tq-text3); }
.tq-an-pct { font-weight: 800; unicode-bidi: isolate; flex: none; }
.tq-an-pct--low  { color: var(--tq-danger); }
.tq-an-pct--mid  { color: var(--tq-amber); }
.tq-an-pct--high { color: var(--tq-actionMastery); }
</style>

<?php include 'portal_close.php'; ?>
