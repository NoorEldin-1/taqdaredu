<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ملفي — ما بلغه الطالب، لا ما يضبطه.
 *
 * الفرق بينه وبين «الإعدادات» هو الفرق بين سجل وأداة: هذه تقول أين وصل
 * (إتقانه وشهاداته وانتظامه)، وتلك تقول كيف يضبط حسابه. وكانتا شاشة
 * واحدة، فكان الطالب يفتح «الإعدادات» ليرى شهاداته.
 *
 * وهو أيضا **ملف الطالب النهائي** الذي تصفه `F4.4`: خريطة الإتقان
 * والشهادات في موضع واحد.
 *
 * **ولا مقارنة بأحد.** لا ترتيب ولا نسبة مئوية مقابل زملاء ولا شارة
 * «أفضل من». قاعدة الحماية في الوثيقة تسري هنا كما تسري في بوابة ولي
 * الأمر — ومصدر ضغط منزلي لا يصير أخف لأنه في شاشة الطالب نفسه.
 */
/* الملفان معا: `tq_student_styles` فيه المكونات (`tq_s_empty` و
   `tq_s_stat` و`tq_s_date`)، و`tq_student_data` فيه قراء البيانات
   (`tq_s_ts` وأخواته). وهذه الشاشة تستعمل من الاثنين. */
include 'tq_student_styles.php';
include 'tq_student_data.php';

$tq_nav   = 'profile';
$tq_role  = 'student';
$tq_title = t('ملفي');
$tq_sub   = t('ما بلغته حتى اليوم — إتقانك وانتظامك وشهاداتك.');
$tq_icon  = 'user';

$CI  = &get_instance();
$uid = isset($user_id) ? (int) $user_id : (int) $CI->session->userdata('user_id');

$CI->load->model('taqdar_learn_model', 'tq_learn');
$CI->load->model('taqdar_repo_model', 'tq_repo');

$tq_me = $CI->db->select('first_name, last_name, email, image, date_added')
                ->where('id', $uid)->get('users')->row_array() ?: array();

$tq_grade = $CI->db->query(
    'SELECT g.`name_ar` FROM `users` u JOIN `grades` g ON g.`id` = u.`grade_id`
      WHERE u.`id` = ? LIMIT 1', array($uid))->row('name_ar');

$tq_streak = $CI->tq_learn->streak($uid);
$tq_goal   = $CI->tq_learn->goal_today($uid);
$tq_days   = $CI->tq_learn->activity_range($uid, 91);
$tq_exam   = $CI->tq_learn->exam_mode($uid);

$tq_map = array('count' => 0, 'average_level' => 0, 'weakest' => array(), 'objectives' => array());
try { $tq_map = $CI->tq_repo->get_skill_map($uid); } catch (Throwable $e) {}

$tq_mastered = 0;
foreach ($tq_map['objectives'] as $o) if ((float) $o['level'] >= 80) $tq_mastered++;

/* الشهادات: التعريف نفسه الذي في `tq_certificates.php` و
   `Taqdar::certificate_row()` حرفا — اجتياز تقييم من نوع `exam`. وتعريف
   ثان هنا يعني شاشتين تعدان الشهادات عددين. */
$tq_certs = array();
try {
    if ($CI->db->table_exists('attempts')) {
        $tq_certs = $CI->db->query(
            "SELECT a.id, a.score, a.submitted_at,
                    m.title AS milestone_title, p.title AS path_title
               FROM attempts a
               JOIN assessments s ON s.id = a.assessment_id AND s.type = 'exam'
               LEFT JOIN milestones m ON m.id = s.milestone_id
               LEFT JOIN paths p ON p.id = COALESCE(s.path_id, m.path_id)
              WHERE a.student_id = ? AND a.passed = 1
              ORDER BY a.submitted_at DESC LIMIT 12", array($uid))->result_array();
    }
} catch (Throwable $e) { $tq_certs = array(); }

$tq_active_days = 0;
foreach ($tq_days as $d) if (!empty($d['active'])) $tq_active_days++;

include 'portal_open.php';
?>

<div class="tq-profile">

  <!-- الهوية -->
  <section class="tq-card tq-pf-head">
    <span class="tq-pf-avatar" aria-hidden="true">
      <?php if (!empty($tq_me['image'])): ?>
        <img src="<?php echo base_url(html_escape($tq_me['image'])); ?>" alt="" width="72" height="72">
      <?php else: ?>
        <?php echo tq_icon('user', 30); ?>
      <?php endif; ?>
    </span>
    <div class="tq-pf-head__body">
      <h2 class="tq-h2" style="margin:0"><?php
        echo html_escape(trim(($tq_me['first_name'] ?? '') . ' ' . ($tq_me['last_name'] ?? '')) ?: t('طالب في تقدر')); ?></h2>
      <p class="tq-caption" style="margin:2px 0 0">
        <?php if ($tq_grade): ?><?php echo html_escape($tq_grade); ?> · <?php endif; ?>
        عضو منذ <?php echo tq_num(tq_s_date(tq_s_ts($tq_me['date_added'] ?? 0))); ?>
      </p>
      <?php if (!empty($tq_exam['active'])): ?>
        <p class="tq-pf-exam">
          <?php echo tq_icon('check-badge', 14); ?>
          وضع الامتحان سار — بقي <?php echo tq_num((int) $tq_exam['days_left']); ?> يوما
        </p>
      <?php endif; ?>
    </div>
    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('student/settings'); ?>"><?php echo t('اضبط حسابك'); ?></a>
  </section>

  <!-- الأرقام. والسلسلة تخفى لمن أوقف التلعيب — الإيقاف يوقف فعلا -->
  <section class="tq-s-grid4" style="margin-block:var(--tq-space-xl)">
    <?php
    if (!empty($tq_goal['gamify'])) {
        echo tq_s_stat(tq_num((int) $tq_streak['days']), t('يوما متتاليا'), 'flame', 'peach',
              (int) $tq_streak['best'] > (int) $tq_streak['days']
                  ? t('أطول سلسلة لك') . (int) $tq_streak['best'] : '');
    }
    echo tq_s_stat(tq_num($tq_active_days), t('يوم دراسة في تسعين'), 'calendar', 'sky');
    echo tq_s_stat(
        $tq_map['count'] ? tq_num(round($tq_map['average_level']) . '%') : '<span class="tq-muted">—</span>',
        t('متوسط إتقانك'), 'target', 'mint',
        $tq_map['count'] ? '' : t('يظهر بعد أول تقييم'));
    echo tq_s_stat(tq_num(count($tq_certs)), t('شهادة'), 'award', 'lilac');
    ?>
  </section>

  <!-- خريطة الانتظام: ثلاثة أشهر، يوما يوما.
       ولا رقم فوقها: الشكل يقول القصة، والرقم فوق الشكل يكررها. -->
  <section class="tq-card" style="margin-block-end:var(--tq-space-l)">
    <div class="tq-card__head">
      <h2 class="tq-card__title"><?php echo t('انتظامك'); ?></h2>
      <span class="tq-caption"><?php echo t('آخر ثلاثة أشهر'); ?></span>
    </div>
    <div class="tq-pf-heat" role="img"
         aria-label="خريطة أيام الدراسة في آخر ثلاثة أشهر: <?php echo (int) $tq_active_days; ?> يوما نشطا">
      <?php foreach ($tq_days as $d):
        $n = (int) $d['lessons'] + (int) $d['reviews'];
        $lv = !$d['active'] ? 0 : ($n >= 8 ? 3 : ($n >= 3 ? 2 : 1));
      ?>
        <span class="tq-pf-cell tq-pf-cell--<?php echo $lv; ?>"
              title="<?php echo html_escape($d['day']); ?><?php
                echo $d['active'] ? ' — ' . $n . t('نشاطا') : t('— لا نشاط'); ?>"></span>
      <?php endforeach; ?>
    </div>
    <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
      <?php echo t('كل مربع يوم. وكلما غمق لونه زاد ما أنجزته فيه.'); ?>
    </p>
  </section>

  <div class="tq-cols">
    <div class="tq-stack">

      <!-- أضعف الأهداف: الجواب العملي، فيسبق كل عرض آخر -->
      <section class="tq-card">
        <div class="tq-card__head">
          <h2 class="tq-card__title"><?php echo t('ما يحتاج عملك'); ?></h2>
          <a class="tq-btn tq-btn--ghost tq-btn--sm"
             href="<?php echo base_url('student/mastery'); ?>"><?php echo t('الخريطة كاملة'); ?></a>
        </div>

        <?php $tq_weak = array_filter($tq_map['weakest'], function ($o) { return (float) $o['level'] < 80; }); ?>
        <?php if (!$tq_weak): ?>
          <?php echo tq_s_empty('check-badge', 'mint',
                $tq_map['count'] ? t('لا هدف ضعيفا الآن') : t('خريطتك تبدأ بأول تقييم'),
                $tq_map['count']
                    ? t('كل ما قيس عليك حتى الآن في مستوى الإتقان. تابع دروسك ليتسع القياس.')
                    : t('تقاس مستوياتك من إجاباتك على أسئلة الدرس وعلى المراجعة.'),
                t('تابع دروسك'), base_url('student/lessons'), true); ?>
        <?php else: ?>
          <div class="tq-pf-weak">
            <?php foreach ($tq_weak as $o):
              $lv   = (int) round((float) $o['level']);
              $band = $lv >= 50 ? 'mid' : 'low';
              /* الرابط يبنى هنا لا في الوسم: تعبير يمتد سطرين داخل
                 `href` يقرأ بصعوبة ويكسر عند أول تعديل. */
              $lref = '';
              if (!empty($o['lesson_id']) && !empty($o['course_id'])) {
                  $lref = base_url('student/lesson/' . (int) $o['course_id'] . '/' . (int) $o['lesson_id']);
                  if (!empty($o['at_second'])) $lref .= '?t=' . (int) $o['at_second'];
              }
            ?>
              <div class="tq-pf-weak__row">
                <div class="tq-pf-weak__main">
                  <span class="tq-pf-weak__t"><?php echo html_escape($o['objective_text'] ?: t('هدف')); ?></span>
                  <?php if (!empty($o['lesson_title'])): ?>
                    <span class="tq-pf-weak__w"><?php echo html_escape($o['lesson_title']); ?></span>
                  <?php endif; ?>
                </div>
                <span class="tq-pf-weak__pct tq-pf-weak__pct--<?php echo $band; ?>"><?php
                  echo tq_num($lv . '%'); ?></span>
                <?php if ($lref !== ''): ?>
                  <a class="tq-btn tq-btn--ghost tq-btn--sm"
                     href="<?php echo html_escape($lref); ?>"><?php echo t('راجع'); ?></a>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <aside class="tq-aside">
      <!-- الشهادات -->
      <section class="tq-card">
        <div class="tq-card__head">
          <h2 class="tq-card__title"><?php echo t('شهاداتك'); ?></h2>
          <?php if ($tq_certs): ?>
            <a class="tq-btn tq-btn--ghost tq-btn--sm"
               href="<?php echo base_url('student/certificates'); ?>"><?php echo t('الكل'); ?></a>
          <?php endif; ?>
        </div>

        <?php if (!$tq_certs): ?>
          <?php echo tq_s_empty('award', 'sand', t('لا شهادة بعد'),
                t('الشهادة تصدر على إتقان مقاس لا على مشاهدة: تنهي المحطة وتجتاز اختبارها.'),
                '', '', true); ?>
        <?php else: ?>
          <ul class="tq-pf-certs">
            <?php foreach (array_slice($tq_certs, 0, 5) as $c): ?>
              <li>
                <a href="<?php echo base_url('student/certificate/' . (int) $c['id']); ?>">
                  <span class="tq-pf-certs__t"><?php
                    echo html_escape($c['milestone_title'] ?: ($c['path_title'] ?: t('شهادة إتقان'))); ?></span>
                  <span class="tq-pf-certs__m"><?php
                    echo tq_num((int) $c['score'] . '%'); ?> · <?php
                    echo tq_num(tq_s_date(strtotime((string) $c['submitted_at']))); ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </aside>
  </div>
</div>

<style>
.tq-pf-head { display: flex; flex-wrap: wrap; gap: var(--tq-space-l); align-items: center; }
.tq-pf-head__body { flex: 1; min-inline-size: 180px; }
.tq-pf-avatar {
  inline-size: 72px; block-size: 72px; flex: none;
  border-radius: var(--tq-radius-pill); overflow: hidden;
  background: var(--tq-mint-fill); color: var(--tq-mint-ink);
  display: grid; place-items: center;
}
.tq-pf-avatar img { inline-size: 100%; block-size: 100%; object-fit: cover; }
.tq-pf-exam {
  display: inline-flex; align-items: center; gap: 6px; margin: var(--tq-space-s) 0 0;
  font-size: .8rem; font-weight: 700;
  color: var(--tq-peach-ink); background: var(--tq-peach-fill);
  padding: 3px 10px; border-radius: var(--tq-radius-pill);
}

/* خريطة الانتظام: تتدفق طبيعيا فتلتف على عرض الشاشة بلا شبكة صلبة —
   وشبكة بسبعة أعمدة ثابتة تخرج عن الجوال أو تصغر المربع حتى يختفي. */
.tq-pf-heat { display: flex; flex-wrap: wrap; gap: 3px; }
.tq-pf-cell {
  inline-size: 12px; block-size: 12px; border-radius: 3px;
  background: var(--tq-line);
}
.tq-pf-cell--1 { background: color-mix(in srgb, var(--tq-actionMastery) 30%, var(--tq-line)); }
.tq-pf-cell--2 { background: color-mix(in srgb, var(--tq-actionMastery) 62%, var(--tq-line)); }
.tq-pf-cell--3 { background: var(--tq-actionMastery); }

.tq-pf-weak { display: flex; flex-direction: column; gap: var(--tq-space-s); }
.tq-pf-weak__row {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m); align-items: center;
  padding: var(--tq-space-m); border-radius: var(--tq-radius-small);
  background: var(--tq-ground);
}
.tq-pf-weak__main { flex: 1; min-inline-size: 160px; display: flex; flex-direction: column; gap: 2px; }
.tq-pf-weak__t { font-weight: 600; }
.tq-pf-weak__w { font-size: .8rem; color: var(--tq-text3); }
.tq-pf-weak__pct { font-weight: 800; unicode-bidi: isolate; }
.tq-pf-weak__pct--low { color: var(--tq-danger); }
.tq-pf-weak__pct--mid { color: var(--tq-amber); }

.tq-pf-certs { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--tq-space-s); }
.tq-pf-certs a {
  display: flex; flex-direction: column; gap: 2px; text-decoration: none;
  padding: var(--tq-space-m); border-radius: var(--tq-radius-small);
  background: var(--tq-ground); color: inherit;
}
.tq-pf-certs a:hover { background: var(--tq-navyWash); }
.tq-pf-certs__t { font-weight: 600; }
.tq-pf-certs__m { font-size: .8rem; color: var(--tq-text3); }
</style>

<?php include 'portal_close.php'; ?>
