<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * اختبار الدرس في اللوحة.
 *
 * الشاشة نفسها التي عند المعلم ([tq_teacher_quiz.php])، والمحرر واحد
 * ([components/tq_question_editor.php]) — فلا يقبل أحدهما ما يرفضه
 * الآخر. والفرق أن اللوحة تصل إلى كل درس والمعلم إلى دروسه وحدها، وهو
 * فرق في الملكية لا في القدرة.
 *
 * وهذا **ليس اختبارا رابعا بجوار الثلاثة في القاعدة**: هو بوابة الإتقان
 * نفسها (`assessments.type = 'review'`)، والذي يتغير مصدر أسئلتها وحده.
 * انظر رأس [Taqdar_quiz_model.php].
 */

$CI = get_instance();
$CI->load->model('taqdar_curriculum_model', 'tq_curric');
$CI->load->model('taqdar_quiz_model', 'tq_quiz');

$lid = (int) $lesson_id;
$L   = $lesson;

$tq_course = $CI->db->select('id, title')->where('id', (int) $L['course_id'])
                    ->get('course')->row_array();

$tq_quiz  = $CI->tq_quiz->quiz_of($lid);
$tq_rows  = $CI->tq_quiz->questions($lid, true);
$tq_ready = $CI->tq_quiz->readiness($lid);
$tq_stats = $CI->tq_quiz->question_stats($lid);
$tq_att   = $CI->tq_quiz->attempts_of_lesson($lid, 100);

$tq_objectives = array();
foreach ($CI->tq_curric->objectives_of($lid) as $o) {
    $tq_objectives[(int) $o['id']] = $o['text'];
}

$tq_pass = $tq_quiz ? (int) $CI->tq_quiz->pass_mark($tq_quiz) : 3;
$tq_secs = $tq_quiz && $tq_quiz['time_limit_sec'] !== null ? (int) $tq_quiz['time_limit_sec'] : 0;

$tq_answered = 0;
foreach ($tq_stats as $s) $tq_answered += (int) $s['answered'];
?>

<style>
.tqz-gate    { display: grid; gap: var(--tq-space-m);
               grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
               margin-block-end: var(--tq-space-l); }
.tqz-gate__c { border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
               padding: var(--tq-space-m); background: var(--tq-surface); }
.tqz-gate__n { font: var(--tq-type-h2); color: var(--tq-navy); display: block; }
.tqz-gate__l { font: var(--tq-type-caption); color: var(--tq-text2); }
.tqz-hard    { color: var(--tq-danger); font-weight: 700; }
</style>

<?php tqa_head('اختبار الدرس', $L['title'], 'help',
    ($tq_course
        ? '<a class="tqa-btn tqa-btn--ghost" href="'
          . site_url('admin/course_form/course_edit/' . (int) $tq_course['id']) . '?tab=curriculum">'
          . tq_icon('chev-prev', 16) . ' مقرر «' . html_escape($tq_course['title']) . '»</a>'
        : '')); ?>

<?php /* ── ما يفعله هذا الاختبار بالطالب ─────────────────────────── */ ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__head"><h4 class="header-title">ما الذي يفعله هذا الاختبار</h4></div>
    <div class="tqa-card__body">
        <p class="tqa-dim" style="margin-block-end:var(--tq-space-l)">
            الطالب يشاهد الدرس، ثم يفتح له هذا الاختبار.
            <strong>ولا يفتح له الدرس التالي حتى يجتازه</strong> — ومن لم يجتز يعاد إلى دقيقة
            المفهوم الذي أخطأ فيه، ثم يعرض له شرح بديل، ثم يحال إلى معلمه في المحاولة الثالثة.
            ولا حد لعدد المحاولات: العقاب بقاء القفل لا منع الإعادة.
        </p>

        <div class="tqz-gate">
            <div class="tqz-gate__c">
                <span class="tqz-gate__n"><?php echo (int) $tq_ready['questions']; ?></span>
                <span class="tqz-gate__l">سؤالا في الاختبار</span>
            </div>
            <div class="tqz-gate__c">
                <span class="tqz-gate__n"><?php echo $tq_pass; ?></span>
                <span class="tqz-gate__l">حد النجاح</span>
            </div>
            <div class="tqz-gate__c">
                <span class="tqz-gate__n"><?php echo $tq_secs > 0 ? intdiv($tq_secs, 60) : '∞'; ?></span>
                <span class="tqz-gate__l"><?php echo $tq_secs > 0 ? 'دقيقة حدا زمنيا' : 'بلا حد زمني'; ?></span>
            </div>
            <div class="tqz-gate__c">
                <span class="tqz-gate__n"><?php echo count($tq_att); ?></span>
                <span class="tqz-gate__l">محاولة مسلمة</span>
            </div>
        </div>

        <?php if ($tq_ready['why']): ?>
            <div class="tqa-note<?php echo $tq_ready['ok'] ? '' : ' tqa-note--warn'; ?>">
                <span aria-hidden="true"><?php echo tq_icon($tq_ready['ok'] ? 'help' : 'alert', 18); ?></span>
                <span>
                    <strong><?php echo $tq_ready['ok']
                        ? 'الاختبار يعمل، وهذا ما يمكن تحسينه:'
                        : 'هذا الاختبار لا يعمل بعد:'; ?></strong>
                    <span style="display:block"><?php echo html_escape(implode(' ', $tq_ready['why'])); ?></span>
                    <?php if ((int) $tq_ready['questions'] === 0): ?>
                        <span style="display:block;margin-block-start:var(--tq-space-s)">
                            وما دام بلا أسئلة فالبوابة تعمل بالطريقة القديمة: أسئلة مربوطة بأهداف
                            هذا الدرس إن وجدت، وإلا فتح الدرس التالي بإتمام المشاهدة وحدها.
                        </span>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php /* ── الإعدادات ─────────────────────────────────────────────── */ ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__head"><h4 class="header-title">إعدادات الاختبار</h4></div>
    <div class="tqa-card__body">
        <form method="post" action="<?php echo site_url('taqdar_admin/lesson_quiz_settings/' . $lid); ?>">
            <?php echo tq_csrf(); ?>
            <input type="hidden" name="lesson_id" value="<?php echo $lid; ?>">

            <div class="tqa-fieldgrid">
                <div class="tqa-field">
                    <label class="tqa-field__label" for="qz_pass">حد النجاح</label>
                    <input class="tqa-input" type="number" id="qz_pass" name="pass_mark"
                           min="1" max="50" value="<?php echo $tq_pass; ?>">
                    <span class="tqa-field__hint">
                        عدد الإجابات الصحيحة اللازمة للاجتياز. ولا يكون أكبر من عدد الأسئلة —
                        وإلا لم يجتزه أحد أبدا وبقي الدرس التالي مقفلا على الجميع.
                    </span>
                </div>
                <div class="tqa-field">
                    <label class="tqa-field__label" for="qz_secs">الحد الزمني (ثوان)</label>
                    <input class="tqa-input" type="number" id="qz_secs" name="time_limit_sec"
                           min="0" step="30" value="<?php echo $tq_secs; ?>">
                    <span class="tqa-field__hint">صفر = بلا حد. والضغط الزمني يقيس السرعة لا الفهم.</span>
                </div>
            </div>

            <div class="tqa-actions">
                <button class="tqa-btn tqa-btn--primary" type="submit">
                    <?php echo tq_icon('check', 16); ?> احفظ الإعدادات
                </button>
            </div>
        </form>
    </div>
</div>

<?php /* ── المحرر المشترك ────────────────────────────────────────── */ ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__body">
        <?php
        $q_skin       = 'tqa';
        $q_action     = site_url('taqdar_admin/lesson_quiz_save/' . $lid);
        $q_delete     = site_url('taqdar_admin/lesson_quiz_delete/' . $lid);
        $q_hidden     = array('lesson_id' => $lid);
        $q_objectives = $tq_objectives;
        $q_rows       = $tq_rows;
        $q_intro      = 'سؤال اختيار من متعدد. والموصى به خمسة أسئلة تغطي أهداف الدرس كلها — '
                      . 'سؤالان يقيسان الحظ، وعشرون يرهقان.'
                      . ($tq_objectives ? '' : ' <strong>وهذا الدرس بلا أهداف بعد</strong> — أضفها من شاشة الأهداف التعليمية ليمكن ربط كل سؤال بما يقيسه.');

        include APPPATH . 'views/components/tq_question_editor.php';
        ?>
    </div>
</div>

<?php /* ── أداء الطلاب في كل سؤال ─────────────────────────────────── */ ?>
<?php if ($tq_answered > 0): ?>
<div class="tqa-card tqa-section">
    <div class="tqa-card__head"><h4 class="header-title">أداء الطلاب في كل سؤال</h4></div>
    <div class="tqa-card__body">
        <p class="tqa-dim" style="margin-block-end:var(--tq-space-m)">
            سؤال يخطئ فيه أكثر الطلاب يقرأ عن الشرح لا عن الطلاب: إما أن صياغته ملتبسة،
            وإما أن مفهومه لم يشرح في الدرس.
        </p>
        <table class="table">
            <thead>
                <tr>
                    <th>السؤال</th><th>أجاب</th><th>أصاب</th><th>نسبة الإصابة</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tq_stats as $s):
                $n = (int) $s['answered']; $c = (int) $s['correct'];
                $p = $n > 0 ? (int) round($c * 100 / $n) : 0; ?>
                <tr>
                    <td><?php echo html_escape(mb_substr((string) $s['title'], 0, 90)); ?></td>
                    <td><span class="tqa-num"><?php echo $n; ?></span></td>
                    <td><span class="tqa-num"><?php echo $c; ?></span></td>
                    <td>
                        <span class="<?php echo ($n >= 5 && $p < 40) ? 'tqz-hard' : ''; ?>">
                            <span class="tqa-num"><?php echo $p; ?></span>%
                        </span>
                        <?php if ($n >= 5 && $p < 40): ?>
                            <span class="tqa-media__sub" style="display:block">راجع صياغته أو أعد شرح مفهومه</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php /* ── المحاولات: تقرأ ولا تحرر ──────────────────────────────── */ ?>
<?php if ($tq_att): ?>
<div class="tqa-card">
    <div class="tqa-card__head"><h4 class="header-title">من أدى هذا الاختبار</h4></div>
    <div class="tqa-card__body">
        <p class="tqa-dim" style="margin-block-end:var(--tq-space-m)">
            آخر مئة محاولة مسلمة. والنتيجة فعل الطالب فتقرأ ولا تحرر — وتحريرها من اللوحة
            يجعل الكشف شيئا آخر غير ما جرى.
        </p>
        <table class="table">
            <thead>
                <tr><th>الطالب</th><th>المحاولة</th><th>الصحيح</th><th>النتيجة</th><th>التسليم</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tq_att as $a): ?>
                <tr>
                    <td>
                        <?php echo html_escape(trim($a['first_name'] . ' ' . $a['last_name'])); ?>
                        <span class="tqa-media__sub" style="display:block"><?php echo html_escape($a['email']); ?></span>
                    </td>
                    <td><span class="tqa-num"><?php echo (int) $a['attempt_no']; ?></span></td>
                    <td><span class="tqa-num"><?php echo (int) $a['score']; ?></span> من <span class="tqa-num"><?php echo count($tq_rows); ?></span></td>
                    <td>
                        <span class="tqa-badge tqa-badge--<?php echo (int) $a['passed'] === 1 ? 'ok' : 'danger'; ?>">
                            <?php echo (int) $a['passed'] === 1 ? 'اجتاز' : 'لم يجتز'; ?>
                        </span>
                    </td>
                    <td><span class="tq-ltr" dir="ltr"><?php echo html_escape((string) $a['submitted_at']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
