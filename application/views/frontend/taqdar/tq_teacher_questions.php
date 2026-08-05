<?php
/**
 * بوّابة المعلم — بنك الأسئلة.
 *
 * القاعدة الحاكمة لبوّابة المعلم كلها:
 * المعلم مُسنَد إلى مادة وصفّ بعينهما، وما لم يُسنَد إليه لا يظهر في لوحته
 * أصلًا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يُفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زرّ في الواجهة ليس صلاحية. لذلك بنك الأسئلة أدناه
 * يمرّ عبر `lesson` ثمّ `course` ويُقيَّد بملكية الكورس، فلا يظهر سؤال لغيره.
 *
 * السؤال غير المربوط بهدف تعليمي عديم القيمة تشخيصيًّا: يعطيك «أخطأ» ولا
 * يعطيك «في ماذا». ولذلك الربط بالهدف إلزامي في هذه الشاشة.
 *
 * ما ينتظر جدولًا:
 *   `objectives`            — الأهداف نفسها (هدف × درس × عتبة إتقان)
 *   `question.objective_id` — عمود الربط الإلزامي بين السؤال وهدفه
 *   `question_tags`         — الوسوم
 *   `question.archived_at`  — الأرشفة بدل الحذف (السؤال المؤرشف تاريخ لا يُمحى)
 * وحتى توجد، الأسئلة القائمة تُعرض موسومة «بلا هدف» بصدق، ولا يُخترع لها ربط.
 *
 * ولذلك لا أزرار إجراء في هذا الجدول اليوم: كانت فيه «أرشفة» بلا معالج
 * و«تحرير» تعيد الصفحة نفسها. زرّ يوهم بفعل لا يقع أسوأ من غياب الزرّ،
 * فحُذفا. ويعودان يوم يوجد `question.archived_at` وبرنامج تحرير للمعلّم.
 * وكذلك استيراد CSV: مواصفته أدناه مكتوبة، وحقل رفعه لا يُعرض قبل معالجه.
 */

$tq_nav   = 'questions';
$tq_role  = 'teacher';
$tq_title = 'بنك الأسئلة';
$tq_sub   = 'كل سؤال مربوط بهدفه — وإلا فهو بلا قيمة تشخيصية';
$tq_icon  = 'help';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_my_courses = $this->db->query(
    "SELECT id, title FROM course
      WHERE creator = ? OR FIND_IN_SET(?, user_id) > 0
      ORDER BY title ASC",
    [$tq_uid, $tq_uid]
)->result_array();

$tq_course_ids = array_map('intval', array_column($tq_my_courses, 'id'));

/* تصفية بكورس بعينه — تُضاف إلى شرط الملكية لا تحلّ محلّه. */
$tq_course = (int) $this->input->get('course');
if ($tq_course && !in_array($tq_course, $tq_course_ids, true)) {
    $tq_course = 0;
}

/* عمود الربط بالهدف وجدول الأهداف صارا موجودين على الخادم، لكنّ وجودهما
   ليس مضمونًا في كل بيئة، فيُفحصان قبل القراءة. وحين يغيبان تُعرض الحقيقة
   نفسها: «بلا هدف» — لا ربط مُخترَع ولا صفحة مكسورة. */
$tq_has_objectives = $this->db->table_exists('objectives')
                  && $this->db->field_exists('objective_id', 'question');

$tq_questions  = [];
$tq_quizzes    = [];
$tq_quiz_count = 0;
if ($tq_course_ids) {
    $tq_scope = $tq_course ? (string) $tq_course : implode(',', $tq_course_ids);

    $tq_obj_select = $tq_has_objectives ? ', o.text AS objective_text' : '';
    $tq_obj_join   = $tq_has_objectives ? ' LEFT JOIN objectives o ON o.id = q.objective_id' : '';

    $tq_questions = $this->db->query(
        "SELECT q.id, q.title, q.type, q.number_of_options,
                l.id AS quiz_id, l.title AS quiz_title,
                c.id AS course_id, c.title AS course_title
                $tq_obj_select
           FROM question q
           JOIN lesson l ON l.id = q.quiz_id
           JOIN course c ON c.id = l.course_id
           $tq_obj_join
          WHERE c.id IN ($tq_scope)
          ORDER BY q.id DESC
          LIMIT 100"
    )->result_array();

    /* اختبارات هذا المعلّم — تُملأ منها قائمة وجهة الاستيراد، فلا يستورد
       إلى اختبار ليس في كورساته. والخادم يعيد فحص الملكية بعدها. */
    $tq_quizzes = $this->db->query(
        "SELECT l.id, l.title, c.id AS course_id, c.title AS course_title
           FROM lesson l
           JOIN course c ON c.id = l.course_id
          WHERE l.lesson_type = 'quiz' AND l.course_id IN ($tq_scope)
          ORDER BY c.title ASC, l.title ASC"
    )->result_array();
    $tq_quiz_count = count($tq_quizzes);
}

/* الاستيراد له برنامج كتابة قائم (`teacher/questions/import`) ودالّة تحرسه
   وتفحص ملكية الدرس والملفّ. `$this` هنا المُحمِّل لا المتحكّم، فالفحص على
   `get_instance()`. وغياب الدالّة يُخفي النموذج ولا يكسر الصفحة. */
$tq_import_ready = method_exists(get_instance(), 'questions_import');

$tq_type_label = [
    'radio'    => 'اختيار واحد',
    'checkbox' => 'اختيار متعدّد',
    'text'     => 'إجابة قصيرة',
    'essay'    => 'مقالي',
];

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <?php /* نتيجة الاستيراد: المتحكّم يكتبها بمفتاحَي `tq_ok`/`tq_error`
                 ومفتاحَي المنصّة معًا — تُقرأ هنا بالاصطلاحين. */ ?>
        <?php if ($tq_ok = ($this->session->flashdata('tq_ok') ?: $this->session->flashdata('flash_message'))): ?>
            <div class="tq-pastel tq-pastel--mint tq-section" role="status">
                <p class="tq-pastel__body" style="margin:0"><?php echo html_escape($tq_ok); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($tq_no = ($this->session->flashdata('tq_error') ?: $this->session->flashdata('error_message'))): ?>
            <div class="tq-pastel tq-pastel--rose tq-section" role="alert">
                <p class="tq-pastel__body" style="margin:0"><?php echo html_escape($tq_no); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($tq_my_courses): ?>
            <nav class="tq-row tq-section" aria-label="تصفية الأسئلة بالكورس" style="flex-wrap:wrap">
                <a class="tq-pill" aria-pressed="<?php echo $tq_course ? 'false' : 'true'; ?>"
                   href="<?php echo base_url('teacher/questions'); ?>">كل كورساتي</a>
                <?php foreach ($tq_my_courses as $tq_c): ?>
                    <a class="tq-pill" aria-pressed="<?php echo $tq_course === (int) $tq_c['id'] ? 'true' : 'false'; ?>"
                       href="<?php echo base_url('teacher/questions'); ?>?course=<?php echo (int) $tq_c['id']; ?>">
                        <?php echo html_escape($tq_c['title']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if ($tq_questions): ?>
            <div class="tq-card">
                <div class="tq-card__head">
                    <h2 class="tq-card__title">الأسئلة</h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_questions) . TQ_PDI; ?></span>
                </div>
                <table class="tq-table">
                    <caption class="tq-sr">أسئلة بنك كورساتك مع هدف كل سؤال ونوعه</caption>
                    <thead>
                        <tr>
                            <th scope="col">السؤال</th>
                            <th scope="col">الهدف المرتبط</th>
                            <th scope="col">النوع</th>
                            <th scope="col">الاختبار</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tq_questions as $tq_q): ?>
                            <tr>
                                <td data-label="السؤال">
                                    <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_q['title']); ?></span>
                                    <span class="tq-micro" style="display:block"><?php echo html_escape($tq_q['course_title']); ?></span>
                                </td>
                                <td data-label="الهدف المرتبط">
                                    <?php if (!empty($tq_q['objective_text'])): ?>
                                        <span class="tq-strong"><?php echo html_escape($tq_q['objective_text']); ?></span>
                                    <?php else: ?>
                                        <?php echo tq_badge('due', 'بلا هدف'); ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="النوع"><?php echo html_escape($tq_type_label[$tq_q['type']] ?? $tq_q['type']); ?></td>
                                <td data-label="الاختبار"><?php echo html_escape($tq_q['quiz_title']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="tq-field__hint tq-micro" style="margin-block-start:var(--tq-space-l)">
                    التحرير والربط بالهدف والوسم والأرشفة تُفتح فور تفعيل الأهداف
                    وعمود الأرشفة على الخادم. ولا يُعرض هنا زرّ قبل معالجه.
                </p>
            </div>
        <?php else: ?>
            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('help', 24); ?></span>
                <h2 class="tq-empty__title">بنك أسئلتك فارغ</h2>
                <p class="tq-empty__text">
                    <?php echo tq_iso('ابدأ بهدف واحد ثمّ اكتب له 5 أسئلة على الأقل. السؤال المربوط بهدفه يخبرك بما لم يُتقَن، والسؤال الحرّ يخبرك بأن الطالب أخطأ فقط.'); ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/upload'); ?>">ابدأ من درس وأهدافه</a>
            </div>
        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--rose">
            <span class="tq-pastel__label tq-micro">قاعدة البنك</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                السؤال غير المربوط بهدف تعليمي عديم القيمة تشخيصيًّا: يقول «أخطأ» ولا يقول «في ماذا».
            </p>
        </div>

        <!-- استيراد CSV: أعمدة معلومة سلفًا، لا تخمين.
             النموذج يقصد برنامج كتابة قائمًا (`teacher/questions/import`) يحرس
             الدور والملكية والملفّ ويردّ بنتيجة صريحة. وإن غاب البرنامج لا
             يُعرض زرّ رفع أصلًا. -->
        <?php if ($tq_import_ready && $tq_quizzes): ?>
        <form class="tq-card" method="post" enctype="multipart/form-data"
              action="<?php echo base_url('teacher/questions/import'); ?>">
            <div class="tq-card__head"><h2 class="tq-card__title">استيراد CSV</h2></div>

            <input type="hidden" name="course_id" value="<?php echo (int) $tq_course; ?>">

            <div class="tq-field">
                <label class="tq-field__label" for="tq-quiz">وجهة الاستيراد</label>
                <select class="tq-select" id="tq-quiz" name="lesson_id" required>
                    <option value="">اختر اختبارًا من كورساتك…</option>
                    <?php foreach ($tq_quizzes as $tq_z): ?>
                        <option value="<?php echo (int) $tq_z['id']; ?>">
                            <?php echo html_escape($tq_z['course_title'] . ' — ' . $tq_z['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="tq-field__msg tq-field__hint">اختباراتك وحدها، والخادم يعيد فحص ملكيتها.</span>
            </div>

            <div class="tq-field">
                <label class="tq-field__label" for="tq-csv">ملف الأسئلة</label>
                <input class="tq-input" id="tq-csv" type="file" name="csv" accept=".csv,.txt" required>
                <span class="tq-field__msg tq-field__hint">
                    ترميز UTF-8، وأوّل سطر أسماء الأعمدة، والحدّ الأقصى <?php echo TQ_LRI . '2' . TQ_PDI; ?> ميغابايت.
                </span>
            </div>

            <p class="tq-caption" style="margin-block-end:var(--tq-space-s)">الأعمدة المتوقّعة:</p>
            <ul class="tq-micro" style="margin:0 0 var(--tq-space-l);padding-inline-start:var(--tq-space-l);list-style:disc">
                <li>objective — نصّ الهدف أو رقمه (إلزامي)</li>
                <li>question — نصّ السؤال</li>
                <li>type — radio أو checkbox</li>
                <li><?php echo tq_iso('option_1 … option_4 — الخيارات'); ?></li>
                <li>correct — رقم الخيار الصحيح أو أرقامه</li>
                <li>tags — وسوم مفصولة بفاصلة (اختياري)</li>
            </ul>

            <button class="tq-btn tq-btn--primary tq-btn--block" type="submit">استيراد</button>
            <p class="tq-field__hint tq-micro" style="margin-block-start:var(--tq-space-m)">
                الاستيراد يرفض أي صفّ بلا هدف — ولا يستورد نصفه. وتصلك نتيجته مكتوبة.
            </p>
        </form>
        <?php else: ?>
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">مواصفة ملف الاستيراد</h2></div>

            <p class="tq-caption" style="margin-block-end:var(--tq-space-s)">
                <?php echo $tq_quizzes
                    ? 'برنامج الاستيراد غير مفعَّل على الخادم بعد، ولن يُعرض زرّ رفع قبل معالجه.'
                    : 'لا اختبار في كورساتك بعد، فلا وجهة للاستيراد.'; ?>
                جهّز ملفك على هذه الأعمدة الآن ليستورد كما هو حين يُفتح. الترميز UTF-8،
                وأوّل سطر أسماء الأعمدة:
            </p>
            <ul class="tq-micro" style="margin:0 0 var(--tq-space-l);padding-inline-start:var(--tq-space-l);list-style:disc">
                <li>objective — نصّ الهدف أو رقمه (إلزامي)</li>
                <li>question — نصّ السؤال</li>
                <li>type — radio أو checkbox</li>
                <li><?php echo tq_iso('option_1 … option_4 — الخيارات'); ?></li>
                <li>correct — رقم الخيار الصحيح أو أرقامه</li>
                <li>tags — وسوم مفصولة بفاصلة (اختياري)</li>
            </ul>

            <p class="tq-field__hint tq-micro" style="margin:0">
                وحين يُفتح: يرفض أي صفّ بلا هدف، ولا يستورد نصف ملفّ.
            </p>
        </div>
        <?php endif; ?>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">في كورساتك</h2></div>
            <ul class="tq-stack">
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">اختبارات</span>
                    <?php echo tq_num($tq_quiz_count); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">أسئلة</span>
                    <?php echo tq_num(count($tq_questions)); ?>
                </li>
            </ul>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
