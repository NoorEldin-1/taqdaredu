<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * اختبار الدرس عند المعلم.
 *
 * وهذا **ليس اختبارا رابعا بجوار الثلاثة** — هو بوابة الإتقان نفسها:
 * الأسئلة تنسب إلى تقييم `type='review'` الذي يقرر فتح الدرس التالي.
 * فما يؤلف هنا يقفل ويفتح، وتدخل نتائجه دفتر الأخطاء وخريطة الإتقان
 * وتقارير ولي الأمر — بلا سطر إضافي. انظر رأس [Taqdar_quiz_model.php].
 *
 * والمحرر هو محرر الاختبار التشخيصي حرفا بحرف
 * ([components/tq_question_editor.php])، فلا يفترق نموذجان عند أول
 * تعديل على قاعدة.
 *
 * وشيء واحد يقال هنا ولا يقال في التشخيصي: **ما الذي يحدث للطالب**.
 * المعلم يؤلف أسئلة وهو لا يرى أنه يبني قفلا — فلوح الجهوزية يقول ذلك
 * صراحة، ويقول ما ينقص قبل أن يحبس أحدا.
 */

$tq_nav   = 'courses';
$tq_role  = 'teacher';
$tq_icon  = 'help';

$tq_lid = isset($lesson_id) ? (int) $lesson_id : 0;

$CI = get_instance();
$CI->load->model('taqdar_curriculum_model', 'tq_curric');
$CI->load->model('taqdar_quiz_model', 'tq_quiz');

$tq_lesson = $CI->tq_curric->lesson($tq_lid);
$tq_course = $tq_lesson
    ? $CI->db->select('id, title')->where('id', (int) $tq_lesson['course_id'])->get('course')->row_array()
    : null;

$tq_title = 'اختبار: ' . (string) ($tq_lesson['title'] ?? '');
$tq_sub   = 'الأسئلة التي يجيب عنها الطالب بعد الدرس — وبها يفتح الدرس التالي.';

$tq_quiz  = $CI->tq_quiz->quiz_of($tq_lid);
$tq_rows  = $CI->tq_quiz->questions($tq_lid, true);
$tq_ready = $CI->tq_quiz->readiness($tq_lid);
$tq_stats = $CI->tq_quiz->question_stats($tq_lid);

/** الأهداف بمعرفاتها — يربط بها السؤال فتعرف البوابة ما تعيد إليه. */
$tq_objectives = array();
foreach ($CI->tq_curric->objectives_of($tq_lid) as $tq_o) {
    $tq_objectives[(int) $tq_o['id']] = $tq_o['text'];
}

/** إحصاء كل سؤال بمعرفه — للعرض بجوار السؤال بلا استعلام في حلقة. */
$tq_stat_by = array();
foreach ($tq_stats as $tq_s) $tq_stat_by[(int) $tq_s['id']] = $tq_s;

$tq_pass = $tq_quiz ? (int) $CI->tq_quiz->pass_mark($tq_quiz) : 3;
$tq_secs = $tq_quiz && $tq_quiz['time_limit_sec'] !== null ? (int) $tq_quiz['time_limit_sec'] : 0;

include 'portal_open.php';
?>

<style>
.tqz-bar   { display: flex; flex-wrap: wrap; gap: var(--tq-space-s);
             margin-block-end: var(--tq-space-l); }
.tqz-gate  { display: grid; gap: var(--tq-space-m);
             grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
             margin-block-end: var(--tq-space-l); }
.tqz-gate__c { border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
               padding: var(--tq-space-m); background: var(--tq-surface); }
.tqz-gate__n { font: var(--tq-type-h2); color: var(--tq-navy); display: block; }
.tqz-gate__l { font: var(--tq-type-caption); color: var(--tq-text2); }
.tqz-hard    { color: var(--tq-danger); font-weight: 700; }
</style>

<div class="tqz-bar">
    <?php if ($tq_course): ?>
        <a class="tq-btn tq-btn--ghost tq-btn--sm"
           href="<?php echo base_url('teacher/course/' . (int) $tq_course['id']); ?>">
            <?php echo tq_icon('chev-prev', 14); ?> مقرر «<?php echo html_escape($tq_course['title']); ?>»
        </a>
        <a class="tq-btn tq-btn--ghost tq-btn--sm"
           href="<?php echo base_url('teacher/course/' . (int) $tq_course['id']) . '?lesson=' . $tq_lid; ?>">
            <?php echo tq_icon('pen', 14); ?> تحرير الدرس
        </a>
    <?php endif; ?>
</div>

<?php /* ── ما يحدث للطالب: يقال أولا، لا بعد أن يؤلف عشرين سؤالا ─── */ ?>
<div class="tq-card tq-section">
    <h2 class="tq-card__title">ما الذي يفعله هذا الاختبار</h2>
    <p class="tq-caption" style="margin-block:var(--tq-space-s) var(--tq-space-m)">
        الطالب يشاهد الدرس، ثم يفتح له هذا الاختبار.
        <strong>ولا يفتح له الدرس التالي حتى يجتازه</strong> —
        ومن لم يجتز يعاد إلى دقيقة المفهوم الذي أخطأ فيه، ثم يعرض له شرح بديل،
        ثم يحال إلى معلمه في المحاولة الثالثة. ولا حد لعدد المحاولات: العقاب بقاء
        القفل لا منع الإعادة.
    </p>

    <div class="tqz-gate">
        <div class="tqz-gate__c">
            <span class="tqz-gate__n"><?php echo tq_iso($tq_ready['questions']); ?></span>
            <span class="tqz-gate__l">سؤالا في الاختبار</span>
        </div>
        <div class="tqz-gate__c">
            <span class="tqz-gate__n"><?php echo tq_iso($tq_pass); ?></span>
            <span class="tqz-gate__l">حد النجاح — الصحيح اللازم للاجتياز</span>
        </div>
        <div class="tqz-gate__c">
            <span class="tqz-gate__n"><?php echo $tq_secs > 0 ? tq_iso(intdiv($tq_secs, 60)) : '∞'; ?></span>
            <span class="tqz-gate__l"><?php echo $tq_secs > 0 ? 'دقيقة حدا زمنيا' : 'بلا حد زمني'; ?></span>
        </div>
    </div>

    <?php if (!$tq_ready['ok'] || $tq_ready['why']): ?>
        <div class="tq-alert <?php echo $tq_ready['ok'] ? 'tq-alert--ok' : 'tq-alert--no'; ?>"
             role="<?php echo $tq_ready['ok'] ? 'status' : 'alert'; ?>">
            <strong>
                <?php echo $tq_ready['ok']
                    ? 'الاختبار يعمل، وهذا ما يمكن تحسينه:'
                    : 'هذا الاختبار لا يعمل بعد:'; ?>
            </strong>
            <ul style="margin:var(--tq-space-s) 0 0;padding-inline-start:var(--tq-space-l)">
                <?php foreach ($tq_ready['why'] as $tq_w): ?>
                    <li><?php echo html_escape($tq_w); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($tq_ready['questions'] === 0): ?>
                <p style="margin-block-start:var(--tq-space-s)">
                    وما دام بلا أسئلة فالبوابة تعمل بالطريقة القديمة:
                    أسئلة مربوطة بأهداف هذا الدرس إن وجدت، وإلا فتح الدرس التالي بإتمام المشاهدة وحدها.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php /* ── إعدادات الاختبار ──────────────────────────────────────── */ ?>
<details class="tq-card tq-section">
    <summary class="tq-card__title" style="cursor:pointer">إعدادات الاختبار</summary>
    <form method="post" action="<?php echo base_url('teacher/quiz/settings'); ?>"
          style="margin-block-start:var(--tq-space-l)">
        <?php echo tq_csrf(); ?>
        <input type="hidden" name="lesson_id" value="<?php echo $tq_lid; ?>">

        <div class="tq-field">
            <label class="tq-field__label" for="qz_pass">حد النجاح</label>
            <input class="tq-input tq-ltr" type="number" id="qz_pass" name="pass_mark" dir="ltr"
                   min="1" max="50" value="<?php echo $tq_pass; ?>">
            <span class="tq-caption">
                عدد الإجابات الصحيحة اللازمة للاجتياز. ولا يكون أكبر من عدد الأسئلة —
                وإلا لم يجتزه أحد أبدا وبقي الدرس التالي مقفلا على الجميع.
            </span>
        </div>

        <div class="tq-field">
            <label class="tq-field__label" for="qz_secs">الحد الزمني (بالثواني)</label>
            <input class="tq-input tq-ltr" type="number" id="qz_secs" name="time_limit_sec" dir="ltr"
                   min="0" step="30" value="<?php echo $tq_secs; ?>">
            <span class="tq-caption">
                صفر = بلا حد. والضغط الزمني يقيس السرعة لا الفهم — فاتركه صفرا
                إلا أن يكون الاختبار عن سرعة الحساب نفسها.
            </span>
        </div>

        <button class="tq-btn tq-btn--primary" type="submit">
            <?php echo tq_icon('check', 16); ?> احفظ الإعدادات
        </button>
    </form>
</details>

<?php /* ── المحرر: القالب نفسه الذي تستعمله شاشة التشخيصي ──────── */ ?>
<div class="tq-card">
    <?php
    $q_skin       = 'tq';
    $q_action     = base_url('teacher/quiz/question');
    $q_delete     = base_url('teacher/quiz/delete');
    $q_hidden     = array('lesson_id' => $tq_lid);
    $q_objectives = $tq_objectives;
    $q_rows       = $tq_rows;
    $q_intro      = 'سؤال اختيار من متعدد. والموصى به خمسة أسئلة تغطي أهداف الدرس '
                  . 'كلها — سؤالان يقيسان الحظ، وعشرون يرهقان.';

    if (!$tq_objectives) {
        $q_intro .= ' <strong>وهذا الدرس بلا أهداف بعد</strong> — أضفها من شاشة تحرير الدرس '
                  . 'ليمكن ربط كل سؤال بما يقيسه.';
    }

    include APPPATH . 'views/components/tq_question_editor.php';
    ?>
</div>

<?php /* ── أي سؤال يسقط فيه أكثر الطلاب ─────────────────────────── */ ?>
<?php
$tq_answered = 0;
foreach ($tq_stats as $tq_s) $tq_answered += (int) $tq_s['answered'];
?>
<?php if ($tq_answered > 0): ?>
<div class="tq-card tq-section">
    <h2 class="tq-card__title">أداء الطلاب في كل سؤال</h2>
    <p class="tq-caption" style="margin-block:var(--tq-space-s) var(--tq-space-m)">
        سؤال يخطئ فيه أكثر الطلاب يقرأ عن الشرح لا عن الطلاب: إما أن صياغته ملتبسة،
        وإما أن مفهومه لم يشرح في الدرس.
    </p>
    <table class="tq-table">
        <thead>
            <tr>
                <th scope="col">السؤال</th>
                <th scope="col">أجاب</th>
                <th scope="col">أصاب</th>
                <th scope="col">نسبة الإصابة</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tq_stats as $tq_s):
            $tq_n = (int) $tq_s['answered'];
            $tq_c = (int) $tq_s['correct'];
            $tq_p = $tq_n > 0 ? (int) round($tq_c * 100 / $tq_n) : 0; ?>
            <tr>
                <td data-label="السؤال"><?php echo html_escape(mb_substr((string) $tq_s['title'], 0, 90)); ?></td>
                <td data-label="أجاب"><?php echo tq_iso($tq_n); ?></td>
                <td data-label="أصاب"><?php echo tq_iso($tq_c); ?></td>
                <td data-label="نسبة الإصابة">
                    <span class="<?php echo ($tq_n >= 5 && $tq_p < 40) ? 'tqz-hard' : ''; ?>">
                        <?php echo tq_iso($tq_p . '%'); ?>
                    </span>
                    <?php if ($tq_n >= 5 && $tq_p < 40): ?>
                        <span class="tq-caption" style="display:block">راجع صياغته أو أعد شرح مفهومه</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php /* ── من أدى هذا الاختبار ───────────────────────────────────────
         المحاولات تقرأ ولا تحرر: النتيجة فعل الطالب، وتحريرها من شاشة
         المعلم يجعل الكشف شيئا آخر غير ما جرى. والمعلم يرى طلابه وحدهم
         لأن الاختبار من درسه، والدرس من كورسه. */ ?>
<?php $tq_att = $CI->tq_quiz->attempts_of_lesson($tq_lid, 100); ?>
<?php if ($tq_att): ?>
<div class="tq-card tq-section">
    <div class="tq-card__head">
        <h2 class="tq-card__title">من أدى هذا الاختبار</h2>
        <span class="tq-caption"><?php echo tq_iso(count($tq_att)); ?> محاولة مسلمة</span>
    </div>
    <table class="tq-table">
        <caption class="tq-sr">محاولات اختبار هذا الدرس: الطالب ورقم المحاولة ونتيجتها</caption>
        <thead>
            <tr>
                <th scope="col">الطالب</th>
                <th scope="col">المحاولة</th>
                <th scope="col">الصحيح</th>
                <th scope="col">النتيجة</th>
                <th scope="col">التسليم</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tq_att as $tq_a): ?>
            <tr>
                <td data-label="الطالب">
                    <span class="tq-strong"><?php echo html_escape(trim($tq_a['first_name'] . ' ' . $tq_a['last_name'])); ?></span>
                </td>
                <td data-label="المحاولة"><?php echo tq_iso((int) $tq_a['attempt_no']); ?></td>
                <td data-label="الصحيح">
                    <?php echo tq_iso((int) $tq_a['score']); ?> من <?php echo tq_iso(count($tq_rows)); ?>
                </td>
                <td data-label="النتيجة">
                    <?php echo tq_badge((int) $tq_a['passed'] === 1 ? 'mastered' : 'late',
                                        (int) $tq_a['passed'] === 1 ? 'اجتاز' : 'لم يجتز'); ?>
                </td>
                <td data-label="التسليم">
                    <span class="tq-ltr" dir="ltr"><?php echo html_escape(substr((string) $tq_a['submitted_at'], 0, 16)); ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include 'portal_close.php'; ?>
