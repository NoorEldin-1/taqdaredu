<?php
/**
 * بوابة المعلم — كورساتي.
 *
 * القاعدة الحاكمة لبوابة المعلم كلها:
 * المعلم مسند إلى مادة وصف بعينهما، وما لم يسند إليه لا يظهر في لوحته
 * أصلا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زر في الواجهة ليس صلاحية.
 *
 * الإسناد الصريح ينتظر جدول `teacher_assignments`؛ والنطاق المتاح اليوم
 * هو ملكية الكورس: `course.creator` أو ورود معرفه في `course.user_id`.
 * لاحظ أن التصفية أدناه تضاف إلى شرط الملكية لا تحل محله.
 */

$tq_nav   = 'courses';
$tq_role  = 'teacher';
$tq_title = 'كورساتي';
$tq_sub   = 'الكورسات المسندة إليك وحدها';
$tq_icon  = 'book';

$tq_uid = (int) $this->session->userdata('user_id');

/* تصفية الحالة من الرابط، بقائمة بيضاء لا بما يرسله المتصفح. */
$tq_filters = [
    ''         => 'الكل',
    'active'   => 'منشور',
    'pending'  => 'قيد المراجعة',
    'draft'    => 'مسودة',
    'private'  => 'خاص',
    /* `upcoming` كانت في خريطة الشارات ولا حبة تصفية لها: حالة تعرض ولا
       سبيل إلى ترشيحها، فيقرأ المعلم شارة لا يستطيع أن يجمع أخواتها. */
    'upcoming' => 'قادم',
];
$tq_status = (string) $this->input->get('status', true);
if (!array_key_exists($tq_status, $tq_filters)) {
    $tq_status = '';
}

$tq_where_status = $tq_status !== ''
    ? ' AND c.status = ' . $this->db->escape($tq_status)
    : '';

$tq_courses = $this->db->query(
    "SELECT c.id, c.title, c.status, c.thumbnail, c.date_added, c.level,
            (SELECT COUNT(*) FROM enrol e WHERE e.course_id = c.id)            AS students,
            (SELECT COUNT(*) FROM lesson l WHERE l.course_id = c.id)           AS lessons,
            (SELECT COALESCE(AVG(w.course_progress), 0)
               FROM watch_histories w WHERE w.course_id = c.id)                AS completion
       FROM course c
      WHERE (c.creator = ? OR FIND_IN_SET(?, c.user_id) > 0) $tq_where_status
      ORDER BY c.date_added DESC",
    [$tq_uid, $tq_uid]
)->result_array();

/* حالات أكاديمي الأربع تترجم إلى شارات تقدر الخمس.
   البرتقالي (due) إشارة انتظار لا نجاح، والنجاح تركوازي وحده. */
$tq_status_badge = [
    'active'  => ['mastered', 'منشور'],
    'pending' => ['due',      'قيد المراجعة'],
    'draft'   => ['idle',     'مسودة'],
    'private' => ['progress', 'خاص'],
    'upcoming' => ['progress', 'قادم'],
];

$tq_total_students = 0;
foreach ($tq_courses as $tq_c) {
    $tq_total_students += (int) $tq_c['students'];
}

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <?php if ($m = $this->session->flashdata('tq_ok')): ?>
            <div class="tq-alert tq-alert--ok tq-section" role="status"><?php echo html_escape($m); ?></div>
        <?php endif; ?>
        <?php if ($m = $this->session->flashdata('tq_error')): ?>
            <div class="tq-alert tq-alert--no tq-section" role="alert"><?php echo html_escape($m); ?></div>
        <?php endif; ?>

        <?php /* إنشاء كورس — يبدأ «بانتظار المراجعة»، فالنشر قرار إدارة.

                 وكانت هذه الكتلة مكتوبة **داخل** `<nav>` التصفية: نموذج كامل
                 في عنصر تنقل، فيقف زر «أنشئ كورسا جديدا» في صف حبات التصفية
                 كأنه إحداها، ويقرأ قارئ الشاشة نموذج الإنشاء كله بندا من
                 بنود قائمة «تصفية الكورسات بالحالة». */ ?>
        <details class="tq-card tq-card--panel tq-section">
            <summary class="tq-card__title" style="cursor:pointer">أنشئ كورسا جديدا</summary>

            <p class="tq-caption" style="margin-block:var(--tq-space-s) var(--tq-space-l)">
                الكورس الجديد يبدأ <strong>بانتظار مراجعة الإدارة</strong>، وتستطيع رفع دروسه
                من الآن. وينشر بعد المراجعة.
            </p>

            <form method="post" action="<?php echo base_url('teacher/courses/save'); ?>">
                <?php echo tq_csrf(); ?>
                <div class="tq-formgrid">
                    <div class="tq-field">
                        <label for="c_title">عنوان الكورس <span aria-hidden="true">*</span></label>
                        <input class="tq-input" type="text" id="c_title" name="title" required maxlength="190">
                    </div>

                    <div class="tq-field">
                        <label for="c_level">المستوى</label>
                        <select class="tq-input" id="c_level" name="level">
                            <option value="beginner">مبتدئ</option>
                            <option value="intermediate">متوسط</option>
                            <option value="advanced">متقدم</option>
                        </select>
                    </div>

                    <div class="tq-field" style="grid-column:1/-1">
                        <label for="c_short">وصف مختصر</label>
                        <input class="tq-input" type="text" id="c_short" name="short_description" maxlength="255">
                    </div>

                    <div class="tq-field" style="grid-column:1/-1">
                        <label for="c_desc">الوصف</label>
                        <textarea class="tq-input" id="c_desc" name="description" rows="3"></textarea>
                    </div>
                </div>

                <div style="margin-block-start:var(--tq-space-l)">
                    <button type="submit" class="tq-btn tq-btn--primary">أنشئ الكورس</button>
                </div>
            </form>
        </details>

        <nav class="tq-row tq-section" aria-label="تصفية الكورسات بالحالة" style="flex-wrap:wrap">
            <?php foreach ($tq_filters as $tq_key => $tq_label): ?>
                <a class="tq-pill" <?php echo $tq_key === $tq_status ? 'aria-pressed="true"' : 'aria-pressed="false"'; ?>
                   href="<?php echo base_url('teacher/courses') . ($tq_key !== '' ? '?status=' . $tq_key : ''); ?>">
                    <?php echo html_escape($tq_label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tq_courses): ?>
            <div class="tq-card">
                <table class="tq-table">
                    <caption class="tq-sr">كورساتك: الحالة وعدد المسجلين ونسبة الإكمال</caption>
                    <thead>
                        <tr>
                            <th scope="col">الكورس</th>
                            <th scope="col">الحالة</th>
                            <th scope="col">المسجلون</th>
                            <th scope="col">الدروس</th>
                            <th scope="col">نسبة الإكمال</th>
                            <th scope="col"><span class="tq-sr">إجراءات</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tq_courses as $tq_c): ?>
                            <?php
                            [$tq_kind, $tq_text] = $tq_status_badge[$tq_c['status']] ?? ['idle', $tq_c['status']];
                            $tq_completion = (int) round((float) $tq_c['completion']);
                            ?>
                            <tr>
                                <td data-label="الكورس">
                                    <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_c['title']); ?></span>
                                    <span class="tq-micro" style="display:block">أضيف <?php echo html_escape(tq_since((int) $tq_c['date_added'])); ?></span>
                                </td>
                                <td data-label="الحالة"><?php echo tq_badge($tq_kind, $tq_text); ?></td>
                                <td data-label="المسجلون"><?php echo tq_num($tq_c['students'], 'tq-num--sm'); ?></td>
                                <?php /* الرقم صار بابا: كان عددا مجملا لا يفتح على شيء،
                                         فمن أراد أن يعرف **أي** الدروس لم يجد إليها سبيلا. */ ?>
                                <td data-label="الدروس">
                                    <a href="<?php echo base_url('teacher/lessons') . '?course=' . (int) $tq_c['id']; ?>"
                                       title="دروس <?php echo html_escape($tq_c['title']); ?>">
                                        <?php echo tq_num($tq_c['lessons'], 'tq-num--sm'); ?>
                                        <span class="tq-sr">درسا — اضغط لعرضها</span>
                                    </a>
                                </td>
                                <td data-label="نسبة الإكمال" style="min-inline-size:180px">
                                    <?php echo tq_progress($tq_completion, 'متوسط إكمال ' . $tq_c['title']); ?>
                                </td>
                                <?php /* كان الإجراء الوحيد «طلابه»: صف كورس بلا باب إلى محتواه.
                                         والوجهات الثلاث كلها قائمة تعمل، وكل واحدة تحمل معرف
                                         الكورس فتفتح مقيدة به لا على كل شيء. */ ?>
                                <td data-label="إجراءات">
                                    <span class="tq-row" style="gap:var(--tq-space-xs);flex-wrap:wrap">
                                        <?php /* «المقرر» أولا وبارزا: هو الباب الذي لم يكن
                                                 موجودا أصلا — أقسام الكورس ودروسه، إضافة
                                                 وتعديلا وحذفا وترتيبا. وكان المعلم يملك
                                                 نموذج رفع درس وحده، بلا قسم يضعه فيه. */ ?>
                                        <a class="tq-btn tq-btn--secondary tq-btn--sm"
                                           href="<?php echo base_url('teacher/course/' . (int) $tq_c['id']); ?>">
                                            <?php echo tq_icon('layers', 14); ?> المقرر
                                        </a>
                                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                           href="<?php echo base_url('teacher/lessons'); ?>?course=<?php echo (int) $tq_c['id']; ?>">
                                            دروسه
                                        </a>
                                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                           href="<?php echo base_url('teacher/students'); ?>?course=<?php echo (int) $tq_c['id']; ?>">
                                            طلابه
                                        </a>
                                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                           href="<?php echo base_url('teacher/questions'); ?>?course=<?php echo (int) $tq_c['id']; ?>">
                                            أسئلته
                                        </a>
                                        <?php if ($tq_c['status'] === 'active'): ?>
                                            <a class="tq-btn tq-btn--ghost tq-btn--sm" target="_blank" rel="noopener"
                                               href="<?php echo site_url('home/course/' . rawurlencode(slugify($tq_c['title'])) . '/' . (int) $tq_c['id']); ?>">
                                                صفحته
                                            </a>
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="tq-card tq-empty">
                <span class="tq-icon-box tq-pastel--sand" style="color:var(--tq-sand-ink)" aria-hidden="true"><?php echo tq_icon('book', 24); ?></span>
                <h2 class="tq-empty__title">
                    <?php echo $tq_status === '' ? 'لم يسند إليك كورس بعد' : 'لا كورس بهذه الحالة'; ?>
                </h2>
                <p class="tq-empty__text">
                    <?php if ($tq_status === ''): ?>
                        هذه الصفحة تعرض ما أسند إليك وحده — لا تعرض كورسات زملائك ولو كانت في مادتك نفسها.
                        تواصل مع إدارة المنصة لإسناد مادتك وصفك، أو ابدأ برفع درسك الأول.
                    <?php else: ?>
                        غير التصفية لتعود إلى كل كورساتك.
                    <?php endif; ?>
                </p>
                <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/upload'); ?>">ارفع درسا جديدا</a>
            </div>
        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">ملخص</h2></div>
            <ul class="tq-stack">
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">عدد كورساتك</span>
                    <?php echo tq_num(count($tq_courses)); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">مجموع المسجلين</span>
                    <?php echo tq_num($tq_total_students); ?>
                </li>
            </ul>
        </div>

        <div class="tq-pastel tq-pastel--sky">
            <span class="tq-pastel__label tq-micro">لماذا لا أرى كورسات زملائي؟</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                لوحتك مقصورة على ما أسند إليك: مادتك وصفك. ما لم يسند إليك لا يظهر لك،
                لا محتوى ولا طلابا ولا تقريرا.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
