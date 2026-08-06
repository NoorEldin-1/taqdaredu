<?php
/**
 * بوابة المعلم — الواجبات والتصحيح.
 *
 * القاعدة الحاكمة لبوابة المعلم كلها:
 * المعلم مسند إلى مادة وصف بعينهما، وما لم يسند إليه لا يظهر في لوحته
 * أصلا: لا محتواه ولا طلابه ولا تقاريره. والنطاق يفرض في طبقة الاستعلام
 * لا في الواجهة — إخفاء زر في الواجهة ليس صلاحية. لذلك كل استعلام هنا
 * انتقل إلى `Taqdar_marking_model`، ويمر عبر `lesson` ثم `course`
 * ويقيد بملكية الكورس، وتعاد قراءة الملكية عند الحفظ لا عند العرض فقط.
 *
 * التصحيح الآلي مساعد لا بديل: يقترح الدرجة ولا يعتمدها.
 * والدرجة النهائية للسؤال المقالي تظهر للطالب بعد اعتماد المعلم وحده —
 * وهذا الحاجز منفذ في `Taqdar_marking_model::student_view()` ليقرأه كل
 * عارض لنتيجة الطالب من موضع واحد، لا وعدا مكتوبا في شاشة المعلم.
 *
 * الاعتماد نفسه يحفظ في `quiz_results`:
 *   teacher_score · teacher_note · approved_at · approved_by
 *
 * ما يزال ينتظر جدولا:
 *   `assignments` — الواجبات المقالية المستقلة عن الاختبارات
 *   `objectives`  — عتبة إتقان لكل هدف، بدل عتبة واحدة للنظام كله
 */

$tq_nav   = 'marking';
$tq_role  = 'teacher';
$tq_title = 'الواجبات والتصحيح';
$tq_sub   = 'الاقتراح الآلي يسبقك، والاعتماد لك وحدك';
$tq_icon  = 'clipboard';

$tq_uid = (int) $this->session->userdata('user_id');

/* النموذج يحمل عبر get_instance(): العارض في CI3 ينسخ خصائص المتحكم إلى
   المحمل مرة واحدة قبل التصيير، فما حمل بعد بدء التصيير لا يظهر في `$this`. */
$tq_CI = get_instance();
$tq_CI->load->model('taqdar_marking_model');
$tq_mark = $tq_CI->taqdar_marking_model;

$tq_queue    = $tq_mark->queue($tq_uid);
$tq_approved = $tq_mark->approved_recent($tq_uid, 8);
$tq_pass     = $tq_mark->pass_percent();

/* التصحيح المفرد: المعرف من الرابط لا يصدق — يعاد التحقق من نطاقه. */
$tq_rid    = (int) $this->input->get('result');
$tq_single = $tq_rid ? $tq_mark->attempt($tq_rid, $tq_uid) : null;

include 'portal_open.php';
?>

<?php if ($tq_msg = $this->session->flashdata('flash_message')): ?>
    <p class="tq-card tq-section" role="status" style="border-inline-start:4px solid var(--tq-mint-ink)">
        <?php echo tq_iso(html_escape($tq_msg)); ?>
    </p>
<?php endif; ?>
<?php if ($tq_err = $this->session->flashdata('error_message')): ?>
    <p class="tq-card tq-section" role="alert" style="border-inline-start:4px solid var(--tq-rose-ink)">
        <?php echo tq_iso(html_escape($tq_err)); ?>
    </p>
<?php endif; ?>

<div class="tq-cols">
    <div>

        <?php if ($tq_single): ?>
            <?php
            $tq_name     = trim($tq_single['first_name'] . ' ' . $tq_single['last_name']);
            $tq_total    = max(1, (int) $tq_single['total_marks']);
            $tq_auto     = (int) round((float) $tq_single['total_obtained_marks']);
            $tq_is_appr  = !empty($tq_single['approved_at']);
            $tq_essays   = $tq_mark->manual_questions((int) $tq_single['quiz_id']);
            $tq_value    = $tq_is_appr && $tq_single['teacher_score'] !== null
                            ? (float) $tq_single['teacher_score'] : $tq_auto;
            $tq_mastery  = $tq_mark->mastery($tq_value, $tq_total);
            ?>
            <!-- تصحيح مفرد -->
            <form class="tq-card tq-card--panel tq-section" method="post"
                  action="<?php echo base_url('teacher/marking/approve'); ?>">
                <input type="hidden" name="result_id" value="<?php echo (int) $tq_single['quiz_result_id']; ?>">

                <div class="tq-row" style="gap:var(--tq-space-l);margin-block-end:var(--tq-space-xl)">
                    <img class="tq-avatar tq-avatar--lg"
                         src="<?php echo tqs_person_img($tq_single['image']); ?>"
                         alt="صورة <?php echo html_escape($tq_name); ?>">
                    <div style="flex:1;min-inline-size:0">
                        <h2 class="tq-h2" style="margin:0"><?php echo html_escape($tq_name); ?></h2>
                        <p class="tq-caption" style="margin:0">
                            <?php echo html_escape($tq_single['quiz_title'] . ' · ' . $tq_single['course_title']); ?>
                        </p>
                    </div>
                    <?php echo $tq_is_appr
                        ? tq_badge('mastered', 'معتمدة')
                        : tq_badge('due', 'ينتظر اعتمادك'); ?>
                </div>

                <?php if ($tq_is_appr): ?>
                    <div class="tq-pastel tq-pastel--mint" style="margin-block-end:var(--tq-space-xl)">
                        <span class="tq-pastel__label tq-micro">درجة معتمدة</span>
                        <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)">
                            <?php echo tq_num(((float) $tq_single['teacher_score'] == (int) $tq_single['teacher_score']
                                ? (int) $tq_single['teacher_score'] : (float) $tq_single['teacher_score']) . ' / ' . $tq_total); ?>
                        </p>
                        <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-s) 0 0">
                            <?php echo tq_iso('اعتمدتها ' . tq_since((int) $tq_single['approved_at']) . '، وهي ظاهرة للطالب الآن. تعديلها يحل محلها.'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="tq-pastel tq-pastel--sky" style="margin-block-end:var(--tq-space-xl)">
                    <span class="tq-pastel__label tq-micro">اقتراح آلي للدرجة</span>
                    <p class="tq-pastel__title" style="margin:var(--tq-space-s) 0 0;font:var(--tq-type-numeralXl)">
                        <?php echo tq_num($tq_auto . ' / ' . $tq_total); ?>
                    </p>
                    <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-s) 0 0">
                        محسوب من الأسئلة الموضوعية وحدها. التصحيح الآلي مساعد لا بديل —
                        عدل الدرجة متى رأيت في الإجابة ما لا يراه الحاسوب.
                        <?php if ($tq_essays > 0): ?>
                            <br><?php echo tq_iso('وفي هذا الاختبار ' . $tq_essays . ' من الأسئلة لا يقرؤها السكربت أصلا.'); ?>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="tq-grid tq-grid--2">
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-score">الدرجة</label>
                        <input class="tq-input" id="tq-score" type="number" name="score"
                               min="0" max="<?php echo $tq_total; ?>" step="0.5" inputmode="decimal"
                               value="<?php echo html_escape((string) $tq_value); ?>" required>
                        <span class="tq-field__msg tq-field__hint">
                            <?php echo tq_iso('من 0 إلى ' . $tq_total . ' درجة.'); ?>
                        </span>
                    </div>

                    <div class="tq-field">
                        <span class="tq-field__label">حالة الهدف</span>
                        <p class="tq-input" style="display:flex;align-items:center;margin:0">
                            <?php echo tq_badge($tq_mastery['key'], $tq_mastery['label']); ?>
                        </p>
                        <span class="tq-field__msg tq-field__hint">
                            <?php echo tq_iso('تشتق من الدرجة وعتبة النجاح (' . $tq_pass . '%) — لا تسأل عنها مرتين.'); ?>
                        </span>
                    </div>
                </div>

                <div class="tq-field">
                    <label class="tq-field__label" for="tq-note">ملاحظتك للطالب</label>
                    <textarea class="tq-textarea" id="tq-note" name="note" rows="4"
                              placeholder="اكتب ما يفعله في المحاولة القادمة، لا ما أخطأ فيه فقط"><?php
                        echo html_escape((string) $tq_single['teacher_note']);
                    ?></textarea>
                    <span class="tq-field__msg tq-field__hint">تظهر للطالب مع الدرجة، ولولي أمره ضمن ملاحظات المعلمين.</span>
                </div>

                <div class="tq-row" style="gap:var(--tq-space-m);flex-wrap:wrap">
                    <button class="tq-btn tq-btn--mastery" type="submit" aria-describedby="tq-approve-note">
                        <?php echo $tq_is_appr ? 'تحديث الدرجة المعتمدة' : 'اعتماد الدرجة'; ?>
                    </button>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/marking'); ?>">رجوع إلى الصف</a>
                </div>
                <p class="tq-field__msg tq-field__hint" id="tq-approve-note" style="margin-block-start:var(--tq-space-m)">
                    <?php if ($tq_essays > 0): ?>
                        الدرجة النهائية للسؤال المقالي لا تظهر للطالب قبل اعتمادك — والحجب مطبق في الاستعلام لا في الشاشة.
                    <?php else: ?>
                        كل أسئلة هذا الاختبار موضوعية، فدرجته ظاهرة لصاحبها. واعتمادك يثبتها ويضيف ملاحظتك إليها.
                    <?php endif; ?>
                </p>
            </form>
        <?php endif; ?>

        <section aria-labelledby="tq-queue-h">
            <div class="tq-sectionhead">
                <h2 id="tq-queue-h">صف التصحيح</h2>
                <?php if ($tq_queue): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_queue) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_queue): ?>
                <div class="tq-card">
                    <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                        الأقدم أولا: الطالب الذي انتظر أطول يصحح أولا.
                    </p>
                    <table class="tq-table">
                        <caption class="tq-sr">المحاولات المسلمة التي تنتظر اعتمادك</caption>
                        <thead>
                            <tr>
                                <th scope="col">الطالب</th>
                                <th scope="col">الاختبار</th>
                                <th scope="col">الاقتراح الآلي</th>
                                <th scope="col">انتظر</th>
                                <th scope="col"><span class="tq-sr">إجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_queue as $tq_r): ?>
                                <?php
                                $tq_name  = trim($tq_r['first_name'] . ' ' . $tq_r['last_name']);
                                $tq_total = max(1, (int) $tq_r['total_marks']);
                                $tq_auto  = (int) round((float) $tq_r['total_obtained_marks']);
                                ?>
                                <tr>
                                    <td data-label="الطالب">
                                        <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_name); ?></span>
                                        <span class="tq-micro" style="display:block"><?php echo html_escape($tq_r['course_title']); ?></span>
                                    </td>
                                    <td data-label="الاختبار"><?php echo html_escape($tq_r['quiz_title']); ?></td>
                                    <td data-label="الاقتراح الآلي"><?php echo tq_num($tq_auto . ' / ' . $tq_total, 'tq-num--sm'); ?></td>
                                    <td data-label="انتظر"><?php echo html_escape(tq_since((int) $tq_r['date_added'])); ?></td>
                                    <td data-label="إجراءات">
                                        <a class="tq-btn tq-btn--primary tq-btn--sm"
                                           href="<?php echo base_url('teacher/marking'); ?>?result=<?php echo (int) $tq_r['quiz_result_id']; ?>">
                                            صحح
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="color:var(--tq-mint-ink)" aria-hidden="true"><?php echo tq_icon('check', 24); ?></span>
                    <h3 class="tq-empty__title">لا شيء ينتظر تصحيحا</h3>
                    <p class="tq-empty__text">
                        حين يسلم أحد طلابك اختبارا أو واجبا في كورساتك، يظهر هنا مرتبا بالأقدم انتظارا،
                        ومعه اقتراح آلي للدرجة تعتمده أو تعدله.
                    </p>
                    <a class="tq-btn tq-btn--secondary" href="<?php echo base_url('teacher/students'); ?>">طلابي</a>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($tq_approved): ?>
            <section aria-labelledby="tq-approved-h" class="tq-section">
                <div class="tq-sectionhead">
                    <h2 id="tq-approved-h">اعتمدتها أخيرا</h2>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_approved) . TQ_PDI; ?></span>
                </div>
                <div class="tq-card">
                    <table class="tq-table">
                        <caption class="tq-sr">آخر الدرجات التي اعتمدتها</caption>
                        <thead>
                            <tr>
                                <th scope="col">الطالب</th>
                                <th scope="col">الاختبار</th>
                                <th scope="col">الدرجة المعتمدة</th>
                                <th scope="col">منذ</th>
                                <th scope="col"><span class="tq-sr">إجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tq_approved as $tq_a): ?>
                                <?php
                                $tq_name  = trim($tq_a['first_name'] . ' ' . $tq_a['last_name']);
                                $tq_total = max(1, (int) $tq_a['total_marks']);
                                $tq_sc    = (float) $tq_a['teacher_score'];
                                $tq_m     = $tq_mark->mastery($tq_sc, $tq_total);
                                ?>
                                <tr>
                                    <td data-label="الطالب">
                                        <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_name); ?></span>
                                        <span class="tq-micro" style="display:block"><?php echo html_escape($tq_a['course_title']); ?></span>
                                    </td>
                                    <td data-label="الاختبار"><?php echo html_escape($tq_a['quiz_title']); ?></td>
                                    <td data-label="الدرجة المعتمدة">
                                        <?php echo tq_num(($tq_sc == (int) $tq_sc ? (int) $tq_sc : $tq_sc) . ' / ' . $tq_total, 'tq-num--sm'); ?>
                                        <?php echo tq_badge($tq_m['key'], $tq_m['label']); ?>
                                    </td>
                                    <td data-label="منذ"><?php echo html_escape(tq_since((int) $tq_a['approved_at'])); ?></td>
                                    <td data-label="إجراءات">
                                        <a class="tq-btn tq-btn--secondary tq-btn--sm"
                                           href="<?php echo base_url('teacher/marking'); ?>?result=<?php echo (int) $tq_a['quiz_result_id']; ?>">
                                            راجع
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro">حد التصحيح الآلي</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                التصحيح الآلي مساعد لا بديل. يقرأ الاختيار الصحيح، ولا يقرأ محاولة الطالب
                أن يشرح فكرته بعبارة أخرى.
            </p>
            <p class="tq-pastel__body tq-caption" style="margin:var(--tq-space-m) 0 0">
                ولذلك الدرجة النهائية للمقالي تظهر للطالب بعد اعتماد المعلم.
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">حالة الصف</h2></div>
            <ul class="tq-stack">
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">ينتظر اعتمادك</span>
                    <?php echo tq_num(count($tq_queue)); ?>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">أقدم انتظار</span>
                    <span class="tq-caption" style="color:var(--tq-navy)">
                        <?php echo $tq_queue ? html_escape(tq_since((int) $tq_queue[0]['date_added'])) : '—'; ?>
                    </span>
                </li>
                <li class="tq-row tq-row--between">
                    <span class="tq-caption">عتبة النجاح</span>
                    <span class="tq-caption" style="color:var(--tq-navy)"><?php echo tq_iso($tq_pass . '%'); ?></span>
                </li>
            </ul>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
