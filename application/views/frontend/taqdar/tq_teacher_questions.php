<?php
/**
 * بوابة المعلم — بنك الأسئلة.
 *
 * القاعدة الحاكمة لبوابة المعلم كلها:
 * المعلم مسند إلى مادة وصف بعينهما، وما لم يسند إليه لا يظهر في لوحته
 * أصلا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زر في الواجهة ليس صلاحية. لذلك بنك الأسئلة أدناه
 * يمر عبر `lesson` ثم `course` ويقيد بملكية الكورس، فلا يظهر سؤال لغيره.
 *
 * السؤال غير المربوط بهدف تعليمي عديم القيمة تشخيصيا: يعطيك «أخطأ» ولا
 * يعطيك «في ماذا». ولذلك الربط بالهدف إلزامي في هذه الشاشة.
 *
 * ما ينتظر جدولا:
 *   `objectives`            — الأهداف نفسها (هدف × درس × عتبة إتقان)
 *   `question.objective_id` — عمود الربط الإلزامي بين السؤال وهدفه
 *   `question_tags`         — الوسوم
 *   `question.archived_at`  — الأرشفة بدل الحذف (السؤال المؤرشف تاريخ لا يمحى)
 * وحتى توجد، الأسئلة القائمة تعرض موسومة «بلا هدف» بصدق، ولا يخترع لها ربط.
 *
 * ولذلك لا أزرار إجراء في هذا الجدول اليوم: كانت فيه «أرشفة» بلا معالج
 * و«تحرير» تعيد الصفحة نفسها. زر يوهم بفعل لا يقع أسوأ من غياب الزر،
 * فحذفا. ويعودان يوم يوجد `question.archived_at` وبرنامج تحرير للمعلم.
 * وكذلك استيراد CSV: مواصفته أدناه مكتوبة، وحقل رفعه لا يعرض قبل معالجه.
 */

$tq_nav   = 'questions';
$tq_role  = 'teacher';
$tq_title = t('بنك الأسئلة');
$tq_sub   = t('كل سؤال مربوط بهدفه — وإلا فهو بلا قيمة تشخيصية');
$tq_icon  = 'help';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_my_courses = $this->db->query(
    "SELECT id, title FROM course
      WHERE creator = ? OR FIND_IN_SET(?, user_id) > 0
      ORDER BY title ASC",
    [$tq_uid, $tq_uid]
)->result_array();

$tq_course_ids = array_map('intval', array_column($tq_my_courses, 'id'));

/* تصفية بكورس بعينه — تضاف إلى شرط الملكية لا تحل محله. */
$tq_course = (int) $this->input->get('course');
if ($tq_course && !in_array($tq_course, $tq_course_ids, true)) {
    $tq_course = 0;
}

/* عمود الربط بالهدف وجدول الأهداف صارا موجودين على الخادم، لكن وجودهما
   ليس مضمونا في كل بيئة، فيفحصان قبل القراءة. وحين يغيبان تعرض الحقيقة
   نفسها: «بلا هدف» — لا ربط مخترع ولا صفحة مكسورة. */
$tq_has_objectives = $this->db->table_exists('objectives')
                  && $this->db->field_exists('objective_id', 'question');

$tq_questions   = [];
$tq_quizzes     = [];
$tq_quiz_count  = 0;
$tq_total_count = 0;   // كل الأسئلة في النطاق، لا المعروض منها
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

    /* العدد الحقيقي في النطاق — لا طول القائمة المعروضة.
       كان بطاقة الجانب تكتب `count($tq_questions)` والاستعلام أعلاه محدود
       بمئة: فبنك فيه ثلاثمئة سؤال يقرأ صاحبه أن فيه مئة، ولا شيء يقول له
       إن القائمة مقطوعة. */
    $tq_total_count = (int) $this->db->query(
        "SELECT COUNT(*) AS n
           FROM question q
           JOIN lesson l ON l.id = q.quiz_id
          WHERE l.course_id IN ($tq_scope)"
    )->row('n');

    /* اختبارات هذا المعلم — تملأ منها قائمة وجهة الاستيراد، فلا يستورد
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

/* الاستيراد له برنامج كتابة قائم (`teacher/questions/import`) ودالة تحرسه
   وتفحص ملكية الدرس والملف. `$this` هنا المحمل لا المتحكم، فالفحص على
   `get_instance()`. وغياب الدالة يخفي النموذج ولا يكسر الصفحة. */
$tq_import_ready = method_exists(get_instance(), 'questions_import');

$tq_type_label = [
    'radio'    => t('اختيار واحد'),
    'checkbox' => t('اختيار متعدد'),
    'text'     => t('إجابة قصيرة'),
    'essay'    => t('مقالي'),
];

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <?php /* نتيجة الاستيراد: المتحكم يكتبها بمفتاحي `tq_ok`/`tq_error`
                 ومفتاحي المنصة معا — تقرأ هنا بالاصطلاحين. */ ?>
        <?php if ($tq_ok = (tq_flash('tq_ok') ?: tq_flash('flash_message'))): ?>
            <div class="tq-pastel tq-pastel--mint tq-section" role="status">
                <p class="tq-pastel__body" style="margin:0"><?php echo html_escape($tq_ok); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($tq_no = (tq_flash('tq_error') ?: tq_flash('error_message'))): ?>
            <div class="tq-pastel tq-pastel--rose tq-section" role="alert">
                <p class="tq-pastel__body" style="margin:0"><?php echo html_escape($tq_no); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($tq_my_courses): ?>
            <nav class="tq-row tq-section" aria-label="<?php echo te('تصفية الأسئلة بالكورس'); ?>" style="flex-wrap:wrap">
                <a class="tq-pill" aria-pressed="<?php echo $tq_course ? 'false' : 'true'; ?>"
                   href="<?php echo base_url('teacher/questions'); ?>"><?php echo t('كل كورساتي'); ?></a>
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
                    <h2 class="tq-card__title"><?php echo t('الأسئلة'); ?></h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_questions) . TQ_PDI; ?></span>
                </div>
                <table class="tq-table">
                    <caption class="tq-sr"><?php echo t('أسئلة بنك كورساتك مع هدف كل سؤال ونوعه'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo t('السؤال'); ?></th>
                            <th scope="col"><?php echo t('الهدف المرتبط'); ?></th>
                            <th scope="col"><?php echo t('النوع'); ?></th>
                            <th scope="col"><?php echo t('الاختبار'); ?></th>
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
                                        <?php echo tq_badge('due', t('بلا هدف')); ?>
                                    <?php endif; ?>
                                </td>
                                <td data-label="النوع"><?php echo html_escape($tq_type_label[$tq_q['type']] ?? $tq_q['type']); ?></td>
                                <td data-label="الاختبار"><?php echo html_escape($tq_q['quiz_title']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($tq_total_count > count($tq_questions)): ?>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
                        <?php echo tq_iso(t('تعرض هنا أحدث ') . count($tq_questions) . t(' سؤالا من ') . $tq_total_count
                            . t('. صفي بكورس بعينه من الأعلى لترى بنكه كاملا.')); ?>
                    </p>
                <?php endif; ?>
                <p class="tq-field__hint tq-micro" style="margin-block-start:var(--tq-space-l)">
                    <?php echo t('التحرير والربط بالهدف والوسم والأرشفة تفتح فور تفعيل الأهداف وعمود الأرشفة على الخادم. ولا يعرض هنا زر قبل معالجه.'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--lilac" style="color:var(--tq-lilac-ink)" aria-hidden="true"><?php echo tq_icon('help', 24); ?></span>
                <h2 class="tq-empty__title"><?php echo t('بنك أسئلتك فارغ'); ?></h2>
                <p class="tq-empty__text">
                    <?php echo tq_iso(t('ابدأ بهدف واحد ثم اكتب له 5 أسئلة على الأقل. السؤال المربوط بهدفه يخبرك بما لم يتقن، والسؤال الحر يخبرك بأن الطالب أخطأ فقط.')); ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/upload'); ?>"><?php echo t('ابدأ من درس وأهدافه'); ?></a>
            </div>
        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--rose">
            <span class="tq-pastel__label tq-micro"><?php echo t('قاعدة البنك'); ?></span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                <?php echo t('السؤال غير المربوط بهدف تعليمي عديم القيمة تشخيصيا: يقول «أخطأ» ولا يقول «في ماذا».'); ?>
            </p>
        </div>

        <!-- استيراد CSV: أعمدة معلومة سلفا، لا تخمين.
             النموذج يقصد برنامج كتابة قائما (`teacher/questions/import`) يحرس
             الدور والملكية والملف ويرد بنتيجة صريحة. وإن غاب البرنامج لا
             يعرض زر رفع أصلا. -->
        <?php if ($tq_import_ready && $tq_quizzes): ?>
        <form class="tq-card" method="post" enctype="multipart/form-data"
              action="<?php echo base_url('teacher/questions/import'); ?>">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('استيراد CSV'); ?></h2></div>

            <input type="hidden" name="course_id" value="<?php echo (int) $tq_course; ?>">

            <div class="tq-field">
                <label class="tq-field__label" for="tq-quiz"><?php echo t('وجهة الاستيراد'); ?></label>
                <select class="tq-select" id="tq-quiz" name="lesson_id" required>
                    <option value=""><?php echo t('اختر اختبارا من كورساتك…'); ?></option>
                    <?php foreach ($tq_quizzes as $tq_z): ?>
                        <option value="<?php echo (int) $tq_z['id']; ?>">
                            <?php echo html_escape($tq_z['course_title'] . ' — ' . $tq_z['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="tq-field__msg tq-field__hint"><?php echo t('اختباراتك وحدها، والخادم يعيد فحص ملكيتها.'); ?></span>
            </div>

            <div class="tq-field">
                <label class="tq-field__label" for="tq-csv"><?php echo t('ملف الأسئلة'); ?></label>
                <?php /* `accept` ترشيح لا فرض — ونافذة الملفات تعرض «كل الملفات»،
                                 والسحب والإفلات يتخطاها. و`data-tq-maxmb` يجعل حارس
                                 `taqdar.js` يرد الملف قبل الإرسال: ملف كبير يتجاوز
                                 `post_max_size` يرده الخادم بـ413 خاما **قبل أن يعمل
                                 PHP**، فلا تنفذ فحوص `questions_import()` مهما أحكمت. */ ?>
                <input class="tq-input" id="tq-csv" type="file" name="csv"
                       accept=".csv,.txt" data-tq-maxmb="2" required>
                <span class="tq-field__msg tq-field__hint">
                    <?php echo t('ترميز UTF-8، وأول سطر أسماء الأعمدة، والحد الأقصى ____ ميغابايت.', array(TQ_LRI . '2' . TQ_PDI)); ?>
                </span>
            </div>

            <p class="tq-caption" style="margin-block-end:var(--tq-space-s)"><?php echo t('الأعمدة المتوقعة:'); ?></p>
            <ul class="tq-micro" style="margin:0 0 var(--tq-space-l);padding-inline-start:var(--tq-space-l);list-style:disc">
                <li><?php echo t('objective — نص الهدف أو رقمه (إلزامي)'); ?></li>
                <li><?php echo t('question — نص السؤال'); ?></li>
                <li><?php echo t('type — radio أو checkbox'); ?></li>
                <li><?php echo tq_iso(t('option_1 … option_4 — الخيارات')); ?></li>
                <li><?php echo t('correct — رقم الخيار الصحيح أو أرقامه'); ?></li>
                <li><?php echo t('tags — وسوم مفصولة بفاصلة (اختياري)'); ?></li>
            </ul>

            <button class="tq-btn tq-btn--primary tq-btn--block" type="submit"><?php echo t('استيراد'); ?></button>
            <p class="tq-field__hint tq-micro" style="margin-block-start:var(--tq-space-m)">
                <?php echo t('الاستيراد يرفض أي صف بلا هدف — ولا يستورد نصفه. وتصلك نتيجته مكتوبة.'); ?>
            </p>
        </form>
        <?php else: ?>
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('مواصفة ملف الاستيراد'); ?></h2></div>

            <p class="tq-caption" style="margin-block-end:var(--tq-space-s)">
                <?php echo $tq_quizzes
                    ? t('برنامج الاستيراد غير مفعل على الخادم بعد، ولن يعرض زر رفع قبل معالجه.')
                    : t('لا اختبار في كورساتك بعد، فلا وجهة للاستيراد.'); ?>
                <?php echo t('جهز ملفك على هذه الأعمدة الآن ليستورد كما هو حين يفتح. الترميز UTF-8، وأول سطر أسماء الأعمدة:'); ?>
            </p>
            <ul class="tq-micro" style="margin:0 0 var(--tq-space-l);padding-inline-start:var(--tq-space-l);list-style:disc">
                <li><?php echo t('objective — نص الهدف أو رقمه (إلزامي)'); ?></li>
                <li><?php echo t('question — نص السؤال'); ?></li>
                <li><?php echo t('type — radio أو checkbox'); ?></li>
                <li><?php echo tq_iso(t('option_1 … option_4 — الخيارات')); ?></li>
                <li><?php echo t('correct — رقم الخيار الصحيح أو أرقامه'); ?></li>
                <li><?php echo t('tags — وسوم مفصولة بفاصلة (اختياري)'); ?></li>
            </ul>

            <p class="tq-field__hint tq-micro" style="margin:0">
                <?php echo t('وحين يفتح: يرفض أي صف بلا هدف، ولا يستورد نصف ملف.'); ?>
            </p>
        </div>
        <?php endif; ?>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title"><?php echo t('في كورساتك'); ?></h2></div>
            <ul class="tq-stack">
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('اختبارات'); ?></span>
                    <?php echo tq_num($tq_quiz_count); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption"><?php echo t('أسئلة'); ?></span>
                    <?php echo tq_num($tq_total_count); ?>
                </li>
            </ul>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
