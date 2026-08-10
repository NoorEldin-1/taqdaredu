<?php
/**
 * بوابة المعلم — طلابي.
 *
 * القاعدة الحاكمة لبوابة المعلم كلها:
 * المعلم مسند إلى مادة وصف بعينهما، وما لم يسند إليه لا يظهر في لوحته
 * أصلا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زر في الواجهة ليس صلاحية. لذلك قائمة الطلاب أدناه
 * تبدأ من `enrol` مقيدا بكورساته، لا من `users`: المعلم لا يرى سجل
 * الطلاب، يرى طلابه هو.
 *
 * «من يوشك على الانقطاع» = غياب 5 أيام بعد نشاط منتظم.
 * وقياس «النشاط المنتظم» الصحيح يحتاج جدول أيام نشاط (`activity_days`)؛
 * والمتاح اليوم `watch_histories.date_updated` (آخر نشاط) و`course_progress`،
 * فالقاعدة المطبقة هنا: آخر نشاط ≥ 5 أيام، وتقدم بين 1% و99% — أي بدأ
 * فعلا ولم ينه. تستبدل بقاعدة أدق فور وجود جدول الأيام.
 *
 * وشاشة تقيس الانقطاع ولا تعطي المعلم ما يفعله به شاشة ناقصة. لذلك فيها
 * مراسلة الطالب: تكتب من هنا وترسل إلى `teacher/students/message` —
 * برنامج كتابة في طبقة المتحكم لا في العرض، على اصطلاح بقية برامج الكتابة
 * في البوابة. وما لم توجد دالته لا يعرض زر إرسال أصلا: زر لا يفعل
 * شيئا أسوأ من غياب الزر.
 */

$tq_nav   = 'students';
$tq_role  = 'teacher';
$tq_title = 'طلابي';
$tq_sub   = 'طلاب كورساتك وحدهم، وتقدم كل واحد منهم';
$tq_icon  = 'users';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_my_courses = $this->db->query(
    "SELECT id, title FROM course
      WHERE creator = ? OR FIND_IN_SET(?, user_id) > 0
      ORDER BY title ASC",
    [$tq_uid, $tq_uid]
)->result_array();

$tq_course_ids = array_map('intval', array_column($tq_my_courses, 'id'));

$tq_course = (int) $this->input->get('course');
if ($tq_course && !in_array($tq_course, $tq_course_ids, true)) {
    $tq_course = 0;
}

$tq_students    = [];
$tq_at_risk     = [];
$tq_msg_targets = [];

if ($tq_course_ids) {
    $tq_scope = $tq_course ? (string) $tq_course : implode(',', $tq_course_ids);

    $tq_students = $this->db->query(
        "SELECT u.id, u.first_name, u.last_name, u.image,
                c.id AS course_id, c.title AS course_title,
                COALESCE(w.course_progress, 0)              AS progress,
                COALESCE(w.date_updated, e.date_added)      AS last_seen,
                e.date_added                                AS enrolled_at
           FROM enrol e
           JOIN users  u ON u.id = e.user_id
           JOIN course c ON c.id = e.course_id
           LEFT JOIN watch_histories w
                  ON w.student_id = e.user_id AND w.course_id = e.course_id
          WHERE e.course_id IN ($tq_scope)
          ORDER BY last_seen DESC"
    )->result_array();

    /* متوسط نتائج كل طالب في اختبارات كورساتي — نسبة من عدد الأسئلة. */
    $tq_scores = [];
    foreach ($this->db->query(
        "SELECT r.user_id,
                COUNT(*) AS attempts,
                COALESCE(AVG(
                  CASE WHEN (SELECT COUNT(*) FROM question q WHERE q.quiz_id = r.quiz_id) > 0
                       THEN 100 * r.total_obtained_marks /
                            (SELECT COUNT(*) FROM question q WHERE q.quiz_id = r.quiz_id)
                       ELSE NULL END
                ), 0) AS avg_pct
           FROM quiz_results r
           JOIN lesson l ON l.id = r.quiz_id
          WHERE r.is_submitted = 1 AND l.course_id IN ($tq_scope)
          GROUP BY r.user_id"
    )->result_array() as $tq_s) {
        $tq_scores[(int) $tq_s['user_id']] = $tq_s;
    }

    foreach ($tq_students as &$tq_row) {
        $tq_row['days']     = max(0, (int) floor((time() - (int) $tq_row['last_seen']) / 86400));
        $tq_row['attempts'] = (int) ($tq_scores[(int) $tq_row['id']]['attempts'] ?? 0);
        $tq_row['avg_pct']  = (int) round((float) ($tq_scores[(int) $tq_row['id']]['avg_pct'] ?? 0));

        if ($tq_row['days'] >= 5 && (int) $tq_row['progress'] > 0 && (int) $tq_row['progress'] < 100) {
            $tq_at_risk[] = $tq_row;
        }
    }
    unset($tq_row);

    usort($tq_at_risk, static function ($a, $b) {
        return $b['days'] <=> $a['days'];
    });

    /* قائمة من يجوز مراسلتهم تبنى من الصفوف المعروضة نفسها، فالنطاق
       محفوظ بالبناء لا بفحص لاحق: من ليس في كورساتي ليس في القائمة
       أصلا. والطالب المسجل في أكثر من كورس لي يظهر مرة واحدة. */
    foreach ($tq_students as $tq_t) {
        $tq_msg_targets[(int) $tq_t['id']] = trim($tq_t['first_name'] . ' ' . $tq_t['last_name']);
    }
    asort($tq_msg_targets);
}

/* المراسلة تحتاج برنامج كتابة في المتحكم — على اصطلاح بقية برامج الكتابة
   في البوابة (`upload_save` و`questions_import` وأخواتها): البرنامج
   `teacher/students/message` والدالة `Taqdar::students_message()`.
   و`$this` هنا ليس المتحكم بل المحمل، فالفحص على `get_instance()` وحده.
   وغياب الدالة يخفي النموذج ولا يكسر الصفحة. */
$tq_msg_ready = method_exists(get_instance(), 'students_message');

$tq_msg_to = (int) $this->input->get('msg');
if ($tq_msg_to && !isset($tq_msg_targets[$tq_msg_to])) {
    $tq_msg_to = 0;
}

/* رابط «راسله» لطالب بعينه: يفتح الشاشة نفسها والنموذج مملوء باسمه.
   دالة مجهولة لا معرفة عالميا — العرض قد يضمن أكثر من مرة. */
$tq_msg_link = static function ($student_id) use ($tq_course) {
    return base_url('teacher/students')
         . ($tq_course ? '?course=' . (int) $tq_course . '&amp;' : '?')
         . 'msg=' . (int) $student_id . '#tq-compose';
};

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <?php if ($tq_my_courses): ?>
            <nav class="tq-row tq-section" aria-label="تصفية الطلاب بالكورس" style="flex-wrap:wrap">
                <a class="tq-pill" aria-pressed="<?php echo $tq_course ? 'false' : 'true'; ?>"
                   href="<?php echo base_url('teacher/students'); ?>">كل كورساتي</a>
                <?php foreach ($tq_my_courses as $tq_c): ?>
                    <a class="tq-pill" aria-pressed="<?php echo $tq_course === (int) $tq_c['id'] ? 'true' : 'false'; ?>"
                       href="<?php echo base_url('teacher/students'); ?>?course=<?php echo (int) $tq_c['id']; ?>">
                        <?php echo html_escape($tq_c['title']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <!-- من يوشك على الانقطاع: قبل أن ينقطع، لا بعده -->
        <section class="tq-section" aria-labelledby="tq-risk-h">
            <div class="tq-sectionhead">
                <h2 id="tq-risk-h">من يوشك على الانقطاع</h2>
                <?php if ($tq_at_risk): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_at_risk) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_at_risk): ?>
                <div class="tq-card tq-card--float">
                    <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                        <?php echo tq_iso('طالب بدأ بانتظام ثم غاب 5 أيام أو أكثر. رسالة قصيرة الآن أنفع من تقرير بعد شهر.'); ?>
                    </p>
                    <ul class="tq-stack">
                        <?php foreach ($tq_at_risk as $tq_s): ?>
                            <?php $tq_name = trim($tq_s['first_name'] . ' ' . $tq_s['last_name']); ?>
                            <li class="tq-row" style="gap:var(--tq-space-l);padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)">
                                <img class="tq-avatar"
                                     src="<?php echo tqs_person_img($tq_s['image']); ?>"
                                     alt="صورة <?php echo html_escape($tq_name); ?>">
                                <div style="flex:1;min-inline-size:0">
                                    <p class="tq-strong" style="margin:0;color:var(--tq-navy)"><?php echo html_escape($tq_name); ?></p>
                                    <p class="tq-micro" style="margin:0"><?php echo html_escape($tq_s['course_title']); ?></p>
                                </div>
                                <?php /* «غاب 6 يوما» خطأ ظاهر يقرؤه كل عربي: تمييز العدد من
                                         ثلاثة إلى عشرة جمع قلة. و`tq_days()` في المساعدات تحسمه
                                         منذ كتبت، وهذه الشاشة كانت تلصق « يوما» بيدها. */ ?>
                                <?php echo tq_badge('due', 'غاب ' . tq_days((int) $tq_s['days'])); ?>
                                <div style="inline-size:160px">
                                    <?php echo tq_progress((int) $tq_s['progress'], 'تقدم ' . $tq_name); ?>
                                </div>
                                <?php if ($tq_msg_ready): ?>
                                    <a class="tq-btn tq-btn--secondary tq-btn--sm"
                                       href="<?php echo $tq_msg_link((int) $tq_s['id']); ?>">راسله</a>
                                <?php else: ?>
                                    <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                       href="<?php echo base_url('home/my_messages'); ?>">صندوق الرسائل</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('flame', 24); ?></span>
                    <h3 class="tq-empty__title">لا أحد على وشك الانقطاع</h3>
                    <p class="tq-empty__text">
                        <?php echo tq_iso('نراقب من بدأ بانتظام ثم غاب 5 أيام. ما دامت هذه القائمة فارغة فطلابك مستمرون.'); ?>
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher'); ?>">عودة إلى اللوحة</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- تقدم كل طالب -->
        <section aria-labelledby="tq-students-h">
            <div class="tq-sectionhead">
                <h2 id="tq-students-h">تقدم كل طالب</h2>
            </div>

            <?php if ($tq_students): ?>
                <div class="tq-card">
                    <table class="tq-table">
                        <caption class="tq-sr">طلاب كورساتك: التقدم ومتوسط الاختبارات وآخر نشاط</caption>
                        <thead>
                            <tr>
                                <th scope="col">الطالب</th>
                                <th scope="col">الكورس</th>
                                <th scope="col">التقدم</th>
                                <th scope="col">متوسط الاختبارات</th>
                                <th scope="col">آخر نشاط</th>
                                <?php if ($tq_msg_ready): ?>
                                    <th scope="col"><span class="tq-sr">إجراءات</span></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_students as $tq_s): ?>
                                <?php $tq_name = trim($tq_s['first_name'] . ' ' . $tq_s['last_name']); ?>
                                <tr id="student-<?php echo (int) $tq_s['id']; ?>">
                                    <td data-label="الطالب">
                                        <span class="tq-row" style="gap:var(--tq-space-s)">
                                            <img class="tq-avatar tq-avatar--sm"
                                                 src="<?php echo tqs_person_img($tq_s['image']); ?>"
                                                 alt="صورة <?php echo html_escape($tq_name); ?>">
                                            <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_name); ?></span>
                                        </span>
                                    </td>
                                    <td data-label="الكورس"><?php echo html_escape($tq_s['course_title']); ?></td>
                                    <td data-label="التقدم" style="min-inline-size:170px">
                                        <?php echo tq_progress((int) $tq_s['progress'], 'تقدم ' . $tq_name); ?>
                                    </td>
                                    <td data-label="متوسط الاختبارات">
                                        <?php if ($tq_s['attempts'] > 0): ?>
                                            <?php echo tq_num($tq_s['avg_pct'] . '%', 'tq-num--sm'); ?>
                                        <?php else: ?>
                                            <span class="tq-caption">لم يبدأ اختبارا</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="آخر نشاط"><?php echo html_escape(tq_since((int) $tq_s['last_seen'])); ?></td>
                                    <?php if ($tq_msg_ready): ?>
                                        <td data-label="إجراءات">
                                            <a class="tq-btn tq-btn--ghost tq-btn--sm"
                                               href="<?php echo $tq_msg_link((int) $tq_s['id']); ?>">راسله</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('users', 24); ?></span>
                    <h3 class="tq-empty__title">لا طلاب في كورساتك بعد</h3>
                    <p class="tq-empty__text">
                        تظهر هنا أسماء من سجلوا في كورساتك المسندة إليك وحدهم، ومعها تقدم كل واحد
                        ومتوسط اختباراته وآخر نشاط له.
                    </p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('teacher/courses'); ?>">عرض كورساتي</a>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="tq-aside">

        <!-- التدخل: رسالة قصيرة الآن، لا تقرير بعد شهر -->
        <section class="tq-card" id="tq-compose" aria-labelledby="tq-compose-h">
            <div class="tq-card__head">
                <h2 class="tq-card__title" id="tq-compose-h">راسل طالبا</h2>
                <span class="tq-pastel__icon" style="color:var(--tq-navy)" aria-hidden="true"><?php echo tq_icon('chat'); ?></span>
            </div>

            <?php /* المتحكم يكتب نتيجته بمفتاحي `tq_ok`/`tq_error` ومفتاحي
                     المنصة `flash_message`/`error_message` معا — تقرأ هنا
                     بالاصطلاحين، فرسالة لا تقرأ كأنها لم تكتب. */ ?>
            <?php if ($tq_flash_ok = ($this->session->flashdata('tq_ok') ?: $this->session->flashdata('flash_message'))): ?>
                <div class="tq-pastel tq-pastel--mint" style="margin-block-end:var(--tq-space-l)" role="status">
                    <p class="tq-pastel__body" style="margin:0"><?php echo html_escape($tq_flash_ok); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($tq_flash_no = ($this->session->flashdata('tq_error') ?: $this->session->flashdata('error_message'))): ?>
                <div class="tq-pastel tq-pastel--rose" style="margin-block-end:var(--tq-space-l)" role="alert">
                    <p class="tq-pastel__body" style="margin:0"><?php echo html_escape($tq_flash_no); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$tq_msg_targets): ?>
                <p class="tq-caption" style="margin:0">
                    لا طالب مسجل في كورساتك بعد، فلا أحد تراسله من هنا.
                </p>

            <?php elseif ($tq_msg_ready): ?>
                <form method="post" action="<?php echo base_url('teacher/students/message'); ?>">
                    <input type="hidden" name="course_id" value="<?php echo (int) $tq_course; ?>">

                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-msg-student">الطالب</label>
                        <select class="tq-select" id="tq-msg-student" name="student_id" required>
                            <option value="">اختر من طلابك…</option>
                            <?php foreach ($tq_msg_targets as $tq_tid => $tq_tname): ?>
                                <option value="<?php echo (int) $tq_tid; ?>"<?php echo $tq_msg_to === (int) $tq_tid ? ' selected' : ''; ?>>
                                    <?php echo html_escape($tq_tname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="tq-field__msg tq-field__hint">القائمة طلاب كورساتك وحدهم.</span>
                    </div>

                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-msg-body">الرسالة</label>
                        <textarea class="tq-textarea" id="tq-msg-body" name="message" rows="4"
                                  required maxlength="1000"
                                  placeholder="سطران يكفيان: ما الذي لاحظته، وما الخطوة التالية."></textarea>
                        <span class="tq-field__msg tq-field__hint">
                            تصل إلى صندوق رسائله في المنصة ويرد عليها من مكانه.
                        </span>
                    </div>

                    <button class="tq-btn tq-btn--primary tq-btn--block" type="submit">أرسل</button>
                </form>

            <?php else: ?>
                <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                    الإرسال من هذه الشاشة ينتظر تفعيل برنامج المراسلة على الخادم. ولن يعرض هنا
                    زر إرسال قبل ذلك — زر لا يرسل أسوأ من غياب الزر. وصندوق رسائل المنصة
                    يعمل الآن.
                </p>
                <a class="tq-btn tq-btn--secondary tq-btn--block tq-btn--sm"
                   href="<?php echo base_url('home/my_messages'); ?>">افتح صندوق الرسائل</a>
            <?php endif; ?>
        </section>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">ملخص</h2></div>
            <ul class="tq-stack">
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">تسجيلات في كورساتك</span>
                    <?php echo tq_num(count($tq_students)); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">يوشكون على الانقطاع</span>
                    <?php echo tq_num(count($tq_at_risk)); ?>
                </li>
            </ul>
        </div>

        <div class="tq-pastel tq-pastel--lilac">
            <span class="tq-pastel__label tq-micro">لماذا لا أرى بقية الطلاب؟</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                لأنك ترى طلابك أنت: من سجل في كورس أسند إليك. من ليس في كورساتك ليس في نطاقك،
                ولا تظهر بياناته لك أصلا.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
